<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use RectorLaravel\Set\LaravelLevelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/config',
        __DIR__ . '/database',
        __DIR__ . '/routes',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/bootstrap/cache',
        __DIR__ . '/storage',
        __DIR__ . '/vendor',
        __DIR__ . '/database/migrations',
    ])
    ->withCache(cacheDirectory: __DIR__ . '/storage/rector/cache')
    ->withParallel()
    ->withImportNames(removeUnusedImports: true)

    // === ETAPA 1: Laravel 11 → 12 ===
    // ->withSets([LaravelLevelSetList::UP_TO_LARAVEL_120])

    // === ETAPA 2: Laravel 12 → 13 ===
    // ->withSets([LaravelLevelSetList::UP_TO_LARAVEL_130])

    // === ETAPA 3: Modernização (após upgrade) ===
    // ->withPhpSets(php84: true)
    // ->withSets([
    //     SetList::CODE_QUALITY,
    //     SetList::DEAD_CODE,
    //     SetList::TYPE_DECLARATION,
    // ])
;
