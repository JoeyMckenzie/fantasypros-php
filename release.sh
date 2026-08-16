#!/usr/bin/env bash
#
# Tag a release and push it.
#
# git-cliff works out the next version from the conventional commits since the
# last tag and writes CHANGELOG.md; this script checks the things that would
# make a release a bad idea, then commits, tags and pushes. Packagist ingests a
# tag the moment it lands and there's no taking it back, so most of what follows
# is refusing to tag rather than tagging.
#
# The version policy lives in cliff.toml, not here.
#
#   ./release.sh                 # let git-cliff pick the version
#   ./release.sh minor           # force a bump level
#   ./release.sh 1.4.0           # pin an exact version
#   ./release.sh --dry-run       # print the plan and the changelog, then stop
#   ./release.sh --yes           # skip the confirmation prompt
#
set -euo pipefail

readonly RELEASE_BRANCH="main"
readonly CHANGELOG="CHANGELOG.md"

bump=""
explicit_version=""
dry_run=false
assume_yes=false

for arg in "$@"; do
    case "$arg" in
        major | minor | patch) bump="$arg" ;;
        --dry-run) dry_run=true ;;
        --yes | -y) assume_yes=true ;;
        [0-9]*.[0-9]*.[0-9]*) explicit_version="$arg" ;;
        *)
            echo "unknown argument: $arg" >&2
            echo "usage: ./release.sh [major|minor|patch|X.Y.Z] [--dry-run] [--yes]" >&2
            exit 1
            ;;
    esac
done

die() {
    echo "release: $1" >&2
    exit 1
}

command -v gh >/dev/null || die "the gh CLI is needed for the CI check and the release notes."
command -v git-cliff >/dev/null || die "git-cliff is needed. Run this inside 'devenv shell'."

# Everything below reads state. Nothing is written until the commit at the end.

branch=$(git rev-parse --abbrev-ref HEAD)
[ "$branch" = "$RELEASE_BRANCH" ] || die "on '$branch', but releases are cut from '$RELEASE_BRANCH'."

[ -z "$(git status --porcelain)" ] || die "working tree is dirty. Commit or stash first."

git fetch --quiet origin "$RELEASE_BRANCH"
local_head=$(git rev-parse HEAD)
remote_head=$(git rev-parse "origin/$RELEASE_BRANCH")
[ "$local_head" = "$remote_head" ] || die "local $RELEASE_BRANCH and origin/$RELEASE_BRANCH have diverged. Pull or push first."

last_tag=$(git tag --list 'v*' --sort=-v:refname | head -n1)
current="${last_tag:+${last_tag#v}}"

range="HEAD"
[ -n "$last_tag" ] && range="$last_tag..HEAD"

commits=$(git log "$range" --pretty='%s' --no-merges)
[ -n "$commits" ] || die "nothing has landed since $last_tag."

# git-cliff owns the commit parsing and the version policy. Forcing a level is
# plain arithmetic on the last tag, so it stays here.
if [ -n "$explicit_version" ]; then
    next="$explicit_version"
    reason="pinned"
elif [ -n "$bump" ]; then
    [ -n "$last_tag" ] || die "cannot force a '$bump' bump with no previous tag. Pin a version instead."

    IFS='.' read -r major minor patch <<<"$current"

    case "$bump" in
        major) next="$((major + 1)).0.0" ;;
        minor) next="${major}.$((minor + 1)).0" ;;
        patch) next="${major}.${minor}.$((patch + 1))" ;;
    esac

    reason="forced $bump"
else
    next=$(git-cliff --bumped-version 2>/dev/null | tail -n1)
    next="${next#v}"

    [ -n "$next" ] || die "git-cliff could not work out the next version."

    reason="from the commit log"
fi

tag="v$next"

git rev-parse "$tag" >/dev/null 2>&1 && die "$tag already exists."

# Packagist has no staging and no undo, so the gate has to be green on the
# commit being released rather than on some commit after it.
echo "checking CI on ${local_head:0:7}..."
checks=$(gh api "repos/{owner}/{repo}/commits/$local_head/check-runs" \
    --jq '.check_runs[] | "\(.name) \(.status) \(.conclusion // "pending")"')

[ -n "$checks" ] || die "no CI runs found for ${local_head:0:7}. Has the push landed?"

if echo "$checks" | grep -qv ' completed success$'; then
    echo "$checks" | sed 's/^/  /' >&2
    die "CI is not green on ${local_head:0:7}."
fi

# --tag labels the unreleased section as the version about to be cut, so the
# file is right before the tag exists.
changelog=$(git-cliff --tag "$tag" --unreleased 2>/dev/null)

echo
echo "  current   ${current:-none}"
echo "  next      ${next}  (${reason})"
echo "  commit    ${local_head:0:7}"
echo "  CI        green"
echo
echo "$changelog" | sed 's/^/  /'
echo

if [ "$dry_run" = true ]; then
    echo "dry run, stopping here."
    exit 0
fi

if [ "$assume_yes" = false ]; then
    read -r -p "release $tag? [y/N] " reply
    [[ "$reply" =~ ^[Yy]$ ]] || die "aborted."
fi

# The changelog commit rides on top of the commit CI just verified. It only
# touches CHANGELOG.md, which nothing in the gate reads.
git-cliff --tag "$tag" --output "$CHANGELOG" >/dev/null 2>&1
git add "$CHANGELOG"
git commit --quiet -m "chore(release): $tag"

git tag -a "$tag" -m "$tag"
git push --quiet origin "$RELEASE_BRANCH"
git push --quiet origin "$tag"

echo "pushed $tag"

gh release create "$tag" --title "$tag" --notes "$changelog"

echo "released $tag. Packagist picks it up from the GitHub App within a minute."
