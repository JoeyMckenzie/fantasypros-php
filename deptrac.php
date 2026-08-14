<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\DirectoryConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

return static function (DeptracConfig $config): void {
    $config
        ->paths('./src')
        ->excludeFiles('#.*test.*#')
        ->layers(
            $sdk = Layer::withName('sdk')->collectors(
                DirectoryConfig::create('Sdk'),
            ),
            $mcp = Layer::withName('mcp')->collectors(
                DirectoryConfig::create('mcp'),
            ),
        )
        ->rulesets(
            Ruleset::forLayer($mcp)->accesses($sdk),
            Ruleset::forLayer($sdk),
        );
};
