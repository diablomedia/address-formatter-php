<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/scripts'])
    ->append([__FILE__, __DIR__ . '/rector.php']);

return (new Config())
    ->setRules(['@PER-CS3x0' => true])
    ->setFinder($finder);
