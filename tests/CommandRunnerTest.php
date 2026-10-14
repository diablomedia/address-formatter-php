<?php

namespace PredictHQ\AddressFormatter\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;

class CommandRunnerTest extends TestCase
{
    public function testLargeStderrDoesNotBlockStdout(): void
    {
        $child = 'fwrite(STDERR, str_repeat("x", 2 * 1024 * 1024)); fwrite(STDOUT, "completed\\n");';
        $code = $this->runnerCode($child) . 'echo runCommand($command, __DIR__);';
        [$status, $stdout, $stderr] = $this->runHarness($code);

        self::assertSame(0, $status);
        self::assertSame('completed', $stdout);
        self::assertSame('', $stderr);
    }

    public function testFailedCommandReportsBothOutputStreams(): void
    {
        $child = 'fwrite(STDERR, "diagnostic:" . str_repeat("x", 2 * 1024 * 1024)); fwrite(STDOUT, "stdout-marker"); exit(23);';
        $code = $this->runnerCode($child) . <<<'PHP'
            try {
                runCommand($command, __DIR__);
                exit(2);
            } catch (RuntimeException $error) {
                echo json_encode([
                    str_contains($error->getMessage(), ' failed: '),
                    str_ends_with($error->getMessage(), "\nstdout-marker"),
                    str_contains($error->getMessage(), 'diagnostic:' . str_repeat('x', 2 * 1024 * 1024)),
                ], JSON_THROW_ON_ERROR);
            }
            PHP;
        [$status, $stdout, $stderr] = $this->runHarness($code);

        self::assertSame(0, $status);
        self::assertSame('[true,true,true]', $stdout);
        self::assertSame('', $stderr);
    }

    public function testPrintedOutputKeepsStdoutAndStderrSeparate(): void
    {
        $child = 'fwrite(STDERR, str_repeat("x", 2 * 1024 * 1024)); fwrite(STDOUT, "completed\\n");';
        $code = $this->runnerCode($child) . '$result = runCommand($command, __DIR__, true); echo "return:" . $result;';
        [$status, $stdout, $stderr] = $this->runHarness($code);

        self::assertSame(0, $status);
        self::assertSame("completed\nreturn:completed", $stdout);
        self::assertSame(str_repeat('x', 2 * 1024 * 1024), $stderr);
    }

    public function testOutputCallbackReceivesBothStreams(): void
    {
        $child = 'fwrite(STDERR, str_repeat("x", 2 * 1024 * 1024)); fwrite(STDOUT, "completed\\n");';
        $code = $this->runnerCode($child) . <<<'PHP'
            $out = '';
            $err = '';
            $result = runCommand($command, __DIR__, false, static function (string $type, string $buffer) use (&$out, &$err): void {
                if ($type === \Symfony\Component\Process\Process::ERR) {
                    $err .= $buffer;
                } else {
                    $out .= $buffer;
                }
            });
            echo json_encode([$result === 'completed', $out === "completed\n", $err === str_repeat('x', 2 * 1024 * 1024)], JSON_THROW_ON_ERROR);
            PHP;
        [$status, $stdout, $stderr] = $this->runHarness($code);

        self::assertSame(0, $status);
        self::assertSame('[true,true,true]', $stdout);
        self::assertSame('', $stderr);
    }

    private function runnerCode(string $child): string
    {
        return 'namespace DiabloMedia\\AddressFormatter\\Maintenance; use RuntimeException; require '
            . var_export(dirname(__DIR__) . '/vendor/autoload.php', true)
            . '; require ' . var_export(dirname(__DIR__) . '/scripts/command.php', true)
            . '; $command = ' . var_export([PHP_BINARY, '-r', $child], true) . ';';
    }

    /** @return array{int, string, string} */
    private function runHarness(string $code): array
    {
        $stdout = tmpfile();
        $stderr = tmpfile();
        $process = null;

        if ($stdout === false || $stderr === false) {
            throw new RuntimeException('Cannot create command test output files.');
        }

        try {
            // Files keep the test harness itself from blocking on full pipes.
            $process = proc_open([PHP_BINARY, '-r', $code], [['pipe', 'r'], $stdout, $stderr], $pipes);
            self::assertIsResource($process);
            fclose($pipes[0]);
            $deadline = microtime(true) + 5;

            do {
                $status = proc_get_status($process);

                if (!$status['running']) {
                    break;
                }

                if (microtime(true) >= $deadline) {
                    self::fail('The command runner deadlocked while the child wrote to stderr.');
                }

                usleep(10000);
            } while (true);

            rewind($stdout);
            rewind($stderr);
            $stdoutText = stream_get_contents($stdout);
            $stderrText = stream_get_contents($stderr);
            self::assertIsString($stdoutText);
            self::assertIsString($stderrText);

            return [$status['exitcode'], $stdoutText, $stderrText];
        } finally {
            if (is_resource($process)) {
                if (proc_get_status($process)['running']) {
                    proc_terminate($process, 9);
                }

                proc_close($process);
            }

            fclose($stdout);
            fclose($stderr);
        }
    }
}
