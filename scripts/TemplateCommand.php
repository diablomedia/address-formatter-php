<?php

namespace DiabloMedia\AddressFormatter\Maintenance;

use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

final class TemplateCommand extends Command
{
    public function __construct(private readonly string $mode, private readonly string $root)
    {
        parent::__construct($mode);
    }

    protected function configure(): void
    {
        $this->setDescription(match ($this->mode) {
            'check' => 'Verify bundled templates against the pinned upstream submodule.',
            'sync' => 'Regenerate bundled templates from the current upstream checkout.',
            'update' => 'Fetch upstream templates, regenerate the snapshot, and run quality checks.',
            default => throw new InvalidArgumentException('Unknown template command: ' . $this->mode),
        });
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $mode = $this->mode;
        $root = $this->root;
        $submodule = $root . '/address-formatter-templates';
        $destination = $root . '/resources/templates';

        if (file_exists($submodule . '/.git') && runCommand(['git', 'status', '--porcelain'], $submodule) !== '') {
            throw new RuntimeException('The templates submodule has local changes. Commit or discard them before synchronizing.');
        }

        if ($mode === 'update') {
            $this->runWithOutput(['git', 'submodule', 'update', '--init', '--remote', '--', 'address-formatter-templates'], $output);
        }

        if (!file_exists($submodule . '/.git') || !is_dir($submodule . '/conf')) {
            throw new RuntimeException('Initialize the templates submodule with: git submodule update --init');
        }

        $commit = runCommand(['git', 'rev-parse', 'HEAD'], $submodule);
        $expected = $this->readFiles($submodule . '/conf');
        $license = file_get_contents($submodule . '/LICENSE.txt');

        if ($expected === [] || $license === false) {
            throw new RuntimeException('The upstream templates or license are missing.');
        }

        $expected['LICENSE.txt'] = $license;
        $expected['UPSTREAM.json'] = json_encode([
            'repository' => 'https://github.com/OpenCageData/address-formatting',
            'commit' => $commit,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        ksort($expected);
        $actual = $this->readFiles($destination);

        if ($mode === 'check') {
            if ($actual !== $expected) {
                throw new RuntimeException('Bundled templates differ from the pinned submodule. Run composer templates:sync and commit resources/templates with the submodule pointer.');
            }

            $output->writeln('Bundled templates match upstream commit ' . $commit . '.');
            return Command::SUCCESS;
        }

        foreach ($actual as $path => $contents) {
            if (!array_key_exists($path, $expected) && !unlink($destination . '/' . $path)) {
                throw new RuntimeException('Cannot remove obsolete template ' . $path);
            }
        }

        foreach ($expected as $path => $contents) {
            $target = $destination . '/' . $path;
            $directory = dirname($target);

            if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException('Cannot create ' . $directory);
            }

            if (($actual[$path] ?? null) !== $contents && file_put_contents($target, $contents) === false) {
                throw new RuntimeException('Cannot write template ' . $path);
            }
        }

        $output->writeln('Synchronized bundled templates to upstream commit ' . $commit . '.');

        if ($mode === 'update') {
            $this->runWithOutput(['composer', 'check'], $output);
        }

        return Command::SUCCESS;
    }

    /** @return array<string, string> */
    private function readFiles(string $directory): array
    {
        $files = [];

        if (!is_dir($directory)) {
            return $files;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isLink()) {
                throw new RuntimeException('Symlinks are not supported in template snapshots: ' . $file->getPathname());
            }

            if (!$file->isFile()) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if ($contents === false) {
                throw new RuntimeException('Cannot read ' . $file->getPathname());
            }

            $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($directory) + 1));
            $files[$relativePath] = $contents;
        }

        ksort($files);

        return $files;
    }

    /** @param list<string> $command */
    private function runWithOutput(array $command, OutputInterface $output): void
    {
        runCommand($command, $this->root, false, static function (string $type, string $buffer) use ($output): void {
            $stream = $type === Process::ERR && $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $stream->write($buffer, false, OutputInterface::OUTPUT_RAW);
        });
    }
}
