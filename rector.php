<?php

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/scripts'])
    ->withPhpSets(php82: true)
    ->withImportNames()
    ->withoutParallel()
    ->withCache(__DIR__ . '/.rector.cache');
