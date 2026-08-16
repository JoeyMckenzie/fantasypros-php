{ pkgs, ... }:

let
  mcpServers = {
    devenv = {
      command = "devenv";
      args = [ "mcp" ];
    };
    backlog = {
      command = "backlog";
      args = [
        "mcp"
        "start"
      ];
    };
    fantasypros = {
      url = "https://api.fantasypros.com/mcp";
    };
  };

  claudeMcpServers = builtins.mapAttrs (
    _name: server:
    server
    // {
      type = if server ? command then "stdio" else "http";
    }
  ) mcpServers;

  opencodeMcpServers = builtins.mapAttrs (
    _name: server:
    if server ? command then
      {
        type = "local";
        command = [ server.command ] ++ (server.args or [ ]);
      }
    else
      {
        type = "remote";
        inherit (server) url;
      }
  ) mcpServers;
in
{
  packages = [
    pkgs.figlet
    pkgs.git-cliff
  ];

  scripts = {
    ci-lint.exec = ''
      set -euo pipefail
      composer fmt:check
      composer refactor:check
      composer lint
      composer test:arch
    '';

    ci-test.exec = ''
      set -euo pipefail
      composer test
      composer test:mutate
    '';
  };

  files.".codex/config.toml".toml.mcp_servers = mcpServers;

  claude.code.enable = true;
  claude.code.mcpServers = claudeMcpServers;

  opencode.enable = true;
  opencode.mcp = opencodeMcpServers;

  languages.php = {
    enable = true;
    version = "8.5";
    extensions = [
      "bcmath"
      "calendar"
      "gd"
      "imagick"
      "zip"
      "pdo_mysql"
      "redis"
      "intl"
      "xdebug"
    ];
  };

  git-hooks.hooks = {
    pint = {
      enable = true;
      name = "pint";
      entry = "composer fmt";
      files = "\\.php$";
      language = "system";
      pass_filenames = false;
    };
    rector = {
      enable = true;
      name = "rector";
      entry = "composer refactor";
      files = "\\.php$";
      language = "system";
      pass_filenames = false;
    };
    composer-audit = {
      enable = true;
      name = "composer packages audit";
      entry = "composer audit";
      language = "system";
      pass_filenames = false;
      stages = [ "pre-push" ];
    };
  };

  enterShell = ''
    if [ ! -d vendor ]; then composer install; fi

    figlet "FantasyPros"
  '';
}
