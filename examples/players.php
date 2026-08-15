<?php

declare(strict_types=1);

use FantasyPros\FantasyProsConnector;

require_once __DIR__.'/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../');
$dotenv->load();

/** @var string $apiKey */
$apiKey = $_ENV['FANTASYPROS_API_KEY'];
$client = FantasyProsConnector::fromEnvironment();
