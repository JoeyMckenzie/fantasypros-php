<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\ClassLikeConfig;
use Deptrac\Deptrac\Contract\Config\Collector\DirectoryConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

/**
 * The library's internal layering, from the outside in.
 *
 * The connector is the entry point and may reach anything. Requests describe
 * endpoints and hydrate their own responses, so they may reach the data layer.
 * Data is inert: it may not reach back up into requests, which would make the
 * DTOs impossible to build or test without the endpoint that returns them.
 * Enums and exceptions are leaves — they carry no dependencies of their own, so
 * every layer may use them and they may use nothing.
 *
 * Nothing may depend on the connector. That keeps requests and DTOs usable with
 * any Saloon connector, and keeps the dependency arrows pointing one way.
 */
return static function (DeptracConfig $config): void {
    $config
        ->paths('./src')
        ->layers(
            $connector = Layer::withName('connector')->collectors(
                ClassLikeConfig::create(FantasyPros\FantasyProsConnector::class),
            ),
            $requests = Layer::withName('requests')->collectors(
                DirectoryConfig::create('Requests'),
            ),
            $data = Layer::withName('data')->collectors(
                DirectoryConfig::create('Data'),
            ),
            $enums = Layer::withName('enums')->collectors(
                DirectoryConfig::create('Enums'),
            ),
            $exceptions = Layer::withName('exceptions')->collectors(
                DirectoryConfig::create('Exceptions'),
            ),
        )
        ->rulesets(
            Ruleset::forLayer($connector)->accesses($requests, $data, $enums, $exceptions),
            Ruleset::forLayer($requests)->accesses($data, $enums, $exceptions),
            Ruleset::forLayer($data)->accesses($enums, $exceptions),
            Ruleset::forLayer($enums),
            Ruleset::forLayer($exceptions),
        );
};
