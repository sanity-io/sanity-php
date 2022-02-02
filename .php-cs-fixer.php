<?php
$finder = new PhpCsFixer\Finder();
$config = new PhpCsFixer\Config('Sanity');

return $config
    ->setRules(['@PSR2' => true])
    ->setFinder($finder->in(__DIR__));
