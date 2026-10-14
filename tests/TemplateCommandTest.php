<?php

namespace PredictHQ\AddressFormatter\Tests;

use DiabloMedia\AddressFormatter\Maintenance\TemplateCommand;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;

require_once dirname(__DIR__) . '/scripts/TemplateCommand.php';

class TemplateCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/address-template-console-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/address-formatter-templates/conf', 0700, true);
        file_put_contents($this->root . '/address-formatter-templates/conf/components.yaml', "name: city\n");
        file_put_contents($this->root . '/address-formatter-templates/LICENSE.txt', "Fixture license\n");
        $this->git(['init', '--quiet']);
        $this->git(['add', '.']);
        mkdir($this->root . '/empty-hooks');
        $this->git(['-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=' . $this->root . '/empty-hooks', '-c', 'user.name=Template test', '-c', 'user.email=templates@example.test', 'commit', '--quiet', '-m', 'Template fixtures']);
    }

    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($iterator as $path) {
            if ($path->isDir()) {
                rmdir($path->getPathname());
            } else {
                unlink($path->getPathname());
            }
        }

        rmdir($this->root);
    }

    public function testSyncRepairsDriftAndCheckValidatesThePinnedCommit(): void
    {
        $sync = new CommandTester(new TemplateCommand('sync', $this->root));
        $check = new CommandTester(new TemplateCommand('check', $this->root));
        self::assertSame(Command::SUCCESS, $sync->execute([]));
        self::assertSame(Command::SUCCESS, $check->execute([]));

        $destination = $this->root . '/resources/templates';
        file_put_contents($destination . '/components.yaml', "changed: true\n");
        unlink($destination . '/LICENSE.txt');
        file_put_contents($destination . '/obsolete.yaml', "obsolete: true\n");

        try {
            $check->execute([]);
            self::fail('Checking a modified snapshot must fail.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('differ from the pinned submodule', $error->getMessage());
        }

        self::assertSame(Command::SUCCESS, $sync->execute([]));
        self::assertSame("name: city\n", file_get_contents($destination . '/components.yaml'));
        self::assertSame("Fixture license\n", file_get_contents($destination . '/LICENSE.txt'));
        self::assertFileDoesNotExist($destination . '/obsolete.yaml');
        $metadata = file_get_contents($destination . '/UPSTREAM.json');
        self::assertIsString($metadata);
        self::assertSame($this->git(['rev-parse', 'HEAD']), json_decode($metadata, true, 512, JSON_THROW_ON_ERROR)['commit']);
        self::assertSame(Command::SUCCESS, $check->execute([]));
    }

    public function testQuietSyncStillCreatesTheSnapshot(): void
    {
        $tester = new CommandTester(new TemplateCommand('sync', $this->root));

        self::assertSame(Command::SUCCESS, $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_QUIET]));
        self::assertSame('', $tester->getDisplay());
        self::assertFileExists($this->root . '/resources/templates/components.yaml');
    }

    /** @param list<string> $arguments */
    private function git(array $arguments): string
    {
        $process = new Process(['git', ...$arguments], $this->root . '/address-formatter-templates');
        $process->mustRun();

        return trim($process->getOutput());
    }
}
