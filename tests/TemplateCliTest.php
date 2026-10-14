<?php

namespace PredictHQ\AddressFormatter\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class TemplateCliTest extends TestCase
{
    public function testHelpAndCommandDiscovery(): void
    {
        $help = $this->runCli(['--help']);
        self::assertTrue($help->isSuccessful());
        self::assertStringContainsString('Verify bundled templates', $help->getOutput());
        self::assertStringContainsString('--quiet', $help->getOutput());

        $list = $this->runCli(['list', '--raw']);
        self::assertTrue($list->isSuccessful());

        foreach (['check', 'sync', 'update'] as $command) {
            self::assertMatchesRegularExpression('/^' . $command . '\s/m', $list->getOutput());
        }
    }

    public function testCheckRemainsTheDefaultCommand(): void
    {
        $process = $this->runCli([]);

        self::assertTrue($process->isSuccessful());
        self::assertStringContainsString('Bundled templates match upstream commit', $process->getOutput());
    }

    public function testUnknownCommandFailsWithHelpfulError(): void
    {
        $process = $this->runCli(['unknown']);

        self::assertFalse($process->isSuccessful());
        self::assertStringContainsString('not defined', $process->getErrorOutput());
    }

    /** @param list<string> $arguments */
    private function runCli(array $arguments): Process
    {
        $process = new Process([PHP_BINARY, dirname(__DIR__) . '/scripts/templates.php', ...$arguments]);
        $process->setTimeout(5);
        $process->run();

        return $process;
    }
}
