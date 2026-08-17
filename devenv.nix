{ ... }:

# The toolchain lives in FantasyPHP/devenv, imported through devenv.yaml.
# Only what is specific to this client belongs here.
{
  fantasyphp.name = "FantasyPros";

  fantasyphp.mcpServers = {
    fantasypros.url = "https://api.fantasypros.com/mcp";
  };
}
