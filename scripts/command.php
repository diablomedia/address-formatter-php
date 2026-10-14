<?php

namespace DiabloMedia\AddressFormatter\Maintenance;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * @param list<string> $command
 * @param (callable(string, string): void)|null $callback
 */
function runCommand(array $command, string $directory, bool $printOutput = false, ?callable $callback = null): string
{
    $process = new Process($command, $directory);
    // Quality checks can take longer than Symfony's default timeout.
    $process->setTimeout(null);
    $process->run($callback ?? ($printOutput ? static function (string $type, string $buffer): void {
        fwrite($type === Process::ERR ? STDERR : STDOUT, $buffer);
    } : null));

    if (!$process->isSuccessful()) {
        throw new RuntimeException(implode(' ', $command) . ' failed: ' . trim($process->getErrorOutput() . "\n" . $process->getOutput()));
    }

    return trim($process->getOutput());
}
