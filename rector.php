<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Privatization\Rector\Class_\FinalizeTestCaseClassRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPreparedSets(
        codeQuality: true,
        privatization: true,
        typeDeclarations: true,
        rectorPreset: true,
        earlyReturn: true,
        phpunitCodeQuality: true,
        doctrineCodeQuality: true,
        symfonyCodeQuality: true,
    )
    ->withImportNames(true)
    ->withAttributesSets()
    ->withPhpSets()
    ->withSets([
        PHPUnitSetList::PHPUNIT_70,
        PHPUnitSetList::PHPUNIT_80,
        PHPUnitSetList::PHPUNIT_90,
        PHPUnitSetList::PHPUNIT_100,
        PHPUnitSetList::PHPUNIT_110,
//        PHPUnitSetList::PHPUNIT_120,
    ])
    ->withSkip([
        FinalizeTestCaseClassRector::class => [
            'tests/BaseFeatureTest.php'
        ],
    ])
    ;
