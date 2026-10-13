<?php

namespace DiabloMedia\AddressFormatter\Maintenance;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use FilesystemIterator;
use Throwable;

/** @param list<string> $command */
function runCommand(array $command, string $directory, bool $printOutput = false): string
{
    $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, $directory);

    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start ' . implode(' ', $command));
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);

    if ($stdout === false || $stderr === false) {
        throw new RuntimeException('Cannot read command output.');
    }

    if ($printOutput) {
        fwrite(STDOUT, $stdout);
        fwrite(STDERR, $stderr);
    }

    if ($status !== 0) {
        throw new RuntimeException(implode(' ', $command) . ' failed: ' . trim($stderr . "\n" . $stdout));
    }

    return trim($stdout);
}

/** @return array<string, string> */
function readFiles(string $directory): array
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

try {
    $mode = $argv[1] ?? 'check';
    $root = dirname(__DIR__);
    $submodule = $root . '/address-formatter-templates';
    $destination = $root . '/resources/templates';

    if (!in_array($mode, ['check', 'sync', 'update'], true)) {
        throw new RuntimeException('Usage: php scripts/templates.php [check|sync|update]');
    }

    if (file_exists($submodule . '/.git') && runCommand(['git', 'status', '--porcelain'], $submodule) !== '') {
        throw new RuntimeException('The templates submodule has local changes. Commit or discard them before synchronizing.');
    }

    if ($mode === 'update') {
        runCommand(['git', 'submodule', 'update', '--init', '--remote', '--', 'address-formatter-templates'], $root, true);
    }

    if (!file_exists($submodule . '/.git') || !is_dir($submodule . '/conf')) {
        throw new RuntimeException('Initialize the templates submodule with: git submodule update --init');
    }

    $commit = runCommand(['git', 'rev-parse', 'HEAD'], $submodule);
    $expected = readFiles($submodule . '/conf');
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
    $actual = readFiles($destination);

    if ($mode === 'check') {
        if ($actual !== $expected) {
            throw new RuntimeException('Bundled templates differ from the pinned submodule. Run composer templates:sync and commit resources/templates with the submodule pointer.');
        }

        fwrite(STDOUT, 'Bundled templates match upstream commit ' . $commit . ".\n");
        exit(0);
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

    fwrite(STDOUT, 'Synchronized bundled templates to upstream commit ' . $commit . ".\n");

    if ($mode === 'update') {
        runCommand(['composer', 'check'], $root, true);
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
