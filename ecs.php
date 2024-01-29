<?php
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([__DIR__ . '/lib'])
    ->withPreparedSets(psr12: true);
