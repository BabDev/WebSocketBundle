<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\PHPUnit\CodeQuality\Rector\Class_\AddSeeTestAnnotationRector;
use Rector\PHPUnit\Set\PHPUnitLevelSetList;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Set\SymfonyLevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__.'/config',
        __DIR__.'/src',
        __DIR__.'/tests',
    ]);

    $rectorConfig->skip([
        /*
         * Skip selected rules
         */
        AddSeeTestAnnotationRector::class,
    ]);

    $rectorConfig->importNames();
    $rectorConfig->importShortClasses(false);
    $rectorConfig->phpstanConfigs([
        __DIR__ . '/vendor/phpstan/phpstan-phpunit/extension.neon',
        __DIR__ . '/vendor/phpstan/phpstan-symfony/extension.neon',
        __DIR__ . '/phpstan.neon',
    ]);

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_81,
        SetList::CODE_QUALITY,
        PHPUnitLevelSetList::UP_TO_PHPUNIT_90,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        SymfonyLevelSetList::UP_TO_SYMFONY_54
    ]);
};
