#!/usr/bin/env bash
#
# Tag a release and push it.
#
# Works out the next version from the conventional commits since the last tag,
# checks the things that would make a release a bad idea, then tags and pushes.
# Packagist ingests a tag the moment it lands and there's no taking it back, so
# most of this script is refusing to tag rather than tagging.
#
#   ./release.sh                 # bump from the commits since the last tag
#   ./release.sh minor           # force a bump level
#   ./release.sh 1.4.0           # pin an exact version
#   ./release.sh --dry-run       # print what it would do and stop
#   ./release.sh --yes           # skip the confirmation prompt
#
set -euo pipefail

readonly RELEASE_BRANCH="main"
readonly FIRST_VERSION="0.1.0"

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

# Everything below reads state; nothing is written until the tag at the end.

branch=$(git rev-parse --abbrev-ref HEAD)
[ "$branch" = "$RELEASE_BRANCH" ] || die "on '$branch', but releases are cut from '$RELEASE_BRANCH'."

[ -z "$(git status --porcelain)" ] || die "working tree is dirty. Commit or stash first."

git fetch --quiet origin "$RELEASE_BRANCH"
local_head=$(git rev-parse HEAD)
remote_head=$(git rev-parse "origin/$RELEASE_BRANCH")
[ "$local_head" = "$remote_head" ] || die "local $RELEASE_BRANCH and origin/$RELEASE_BRANCH have diverged. Pull or push first."

# The last tag, and everything that has landed since it.
last_tag=$(git tag --list 'v*' --sort=-v:refname | head -n1)

if [ -z "$last_tag" ]; then
    range="HEAD"
    current="none"
else
    range="$last_tag..HEAD"
    current="${last_tag#v}"
fi

commits=$(git log "$range" --pretty='%s' --no-merges)
[ -n "$commits" ] || die "nothing has landed since $last_tag."

# Work out the bump from the commit subjects, unless one was passed in.
# `feat!:` or a `BREAKING CHANGE:` trailer is a major, `feat:` a minor, and
# anything else a patch.
if [ -z "$bump" ] && [ -z "$explicit_version" ]; then
    if echo "$commits" | grep -qE '^[a-z]+(\(.+\))?!:' ||
        git log "$range" --pretty='%b' --no-merges | grep -qE '^BREAKING[ -]CHANGE:'; then
        bump="major"
    elif echo "$commits" | grep -qE '^feat(\(.+\))?:'; then
        bump="minor"
    else
        bump="patch"
    fi
fi

if [ -n "$explicit_version" ]; then
    next="$explicit_version"
    bump="pinned"
elif [ -z "$last_tag" ]; then
    next="$FIRST_VERSION"
    bump="first release"
else
    IFS='.' read -r major minor patch <<<"$current"

    # Below 1.0.0 the public API isn't promised yet, so semver lets a breaking
    # change ride a minor and a feature ride a patch. Same rule release-please
    # uses. Pass an explicit version to override it.
    if [ "$major" -eq 0 ]; then
        case "$bump" in
            major)
                bump="minor"
                demoted=true
                ;;
            minor)
                bump="patch"
                demoted=true
                ;;
        esac
    fi

    case "$bump" in
        major) next="$((major + 1)).0.0" ;;
        minor) next="${major}.$((minor + 1)).0" ;;
        patch) next="${major}.${minor}.$((patch + 1))" ;;
    esac
fi

tag="v$next"

git rev-parse "$tag" >/dev/null 2>&1 && die "$tag already exists."

# Packagist has no staging and no undo, so the gate has to be green on the
# commit being tagged rather than some commit after it.
echo "checking CI on ${local_head:0:7}..."
checks=$(gh api "repos/{owner}/{repo}/commits/$local_head/check-runs" \
    --jq '.check_runs[] | "\(.name) \(.status) \(.conclusion // "pending")"')

[ -n "$checks" ] || die "no CI runs found for ${local_head:0:7}. Has the push landed?"

if echo "$checks" | grep -qv ' completed success$'; then
    echo "$checks" | sed 's/^/  /' >&2
    die "CI is not green on ${local_head:0:7}."
fi

echo
echo "  current   ${current}"
echo "  next      ${next}  (${bump})"
[ -n "${demoted:-}" ] && echo "            demoted: pre-1.0.0, so a ${bump} carries the change"
echo "  commit    ${local_head:0:7}"
echo "  CI        green"
echo
echo "  since ${last_tag:-the beginning}:"
echo "$commits" | sed 's/^/    /'
echo

if [ "$dry_run" = true ]; then
    echo "dry run, stopping here."
    exit 0
fi

if [ "$assume_yes" = false ]; then
    read -r -p "tag $tag and push? [y/N] " reply
    [[ "$reply" =~ ^[Yy]$ ]] || die "aborted."
fi

git tag -a "$tag" -m "$tag"
git push --quiet origin "$tag"

echo "pushed $tag"

gh release create "$tag" --title "$tag" --generate-notes

echo "released $tag. Packagist picks it up from the GitHub App within a minute."
