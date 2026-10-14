<?php

namespace DiabloMedia\AddressFormatter\Maintenance;

use Symfony\Component\Console\Application;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/command.php';
require_once __DIR__ . '/TemplateCommand.php';

$application = new Application('Address template maintenance');

foreach (['check', 'sync', 'update'] as $mode) {
    $application->addCommand(new TemplateCommand($mode, dirname(__DIR__)));
}

$application->setDefaultCommand('check');
exit($application->run());
