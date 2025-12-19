<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

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
//    ->withAttributesSets()
    ->withPhpSets()
    ->withSkip([
        \Rector\Privatization\Rector\Class_\FinalizeTestCaseClassRector::class => [
            'tests/BaseFeatureTest.php'
        ],
    ])
    ;
