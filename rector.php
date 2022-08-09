<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src'
    ]);

    // register a single rule
    $rectorConfig->rule(InlineConstructorDefaultToPropertyRector::class);

    // define sets of rules
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_80
    ]);
    // / paths to refactor; solid alternative to CLI arguments
    // $rectorConfig->paths([__DIR__ . '/src', __DIR__ . '/tests']);

    // // is your PHP version different from the one you refactor to? [default: your PHP version], uses PHP_VERSION_ID format
    // $rectorConfig->phpVersion(PhpVersion::PHP_80);

};
