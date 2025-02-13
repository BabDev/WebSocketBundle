<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php81\Rector\Array_\FirstClassCallableRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\Symfony\Set\SymfonySetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/config',
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withSkip([
        /*
         * Skip selected rules
         */

        PreferPHPUnitThisCallRector::class,

        /*
         * Skip selected rules in selected files
         */

        FirstClassCallableRector::class => [
            // Do not change callables in config
            __DIR__.'/config/*',
        ],
    ])
    ->withImportNames(importShortClasses: false)
    ->withPHPStanConfigs([
        __DIR__.'/vendor/phpstan/phpstan-phpunit/extension.neon',
        __DIR__.'/vendor/phpstan/phpstan-symfony/extension.neon',
        __DIR__.'/phpstan.neon',
    ])
    ->withPhpSets()
    ->withPreparedSets(codeQuality: true, phpunitCodeQuality: true)
    ->withSets([
        SymfonySetList::SYMFONY_64,
    ])
    ->withPreparedSets(codeQuality: true);
