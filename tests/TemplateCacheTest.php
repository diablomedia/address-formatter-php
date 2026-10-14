<?php

namespace PredictHQ\AddressFormatter\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;

class TemplateCacheTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/address-template-cache-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/src', 0700, true);
        mkdir($this->root . '/resources/templates/abbreviations', 0700, true);
        mkdir($this->root . '/resources/templates/countries', 0700, true);
        copy(dirname(__DIR__) . '/src/Formatter.php', $this->root . '/src/Formatter.php');
        $this->write('components.yaml', "name: road\n---\nname: city\naliases: [town]\n---\nname: postcode\n---\nname: state\n---\nname: country_code\n");
        $this->write('countries/worldwide.yaml', "default: {address_template: '{{road}} {{city}} {{state_code}}'}\nUS: {address_template: 'Before {{road}} {{city}} {{state_code}}'}\n");
        $this->write('state_codes.yaml', "US: {EX: Example}\n");
        $this->write('country2lang.yaml', "US: en\n");
        $this->write('county_codes.yaml', "{}\n");
        $this->write('abbreviations/en.yaml', "road: {Street: St}\n");
    }

    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($this->root);
    }

    public function testChangedTemplatesAndExplicitReloadPreserveInstanceIsolation(): void
    {
        $this->runIsolated(<<<'PHP'
            $address = ['road' => 'Example Street', 'town' => 'Town', 'postcode' => '10001', 'state' => 'Example'];
            $options = ['country' => 'US'];
            $first = new Formatter();
            $second = new Formatter();
            $before = "Before Example Street Town EX\n";
            check($first->formatArray($address, $options) === $before);
            check($second->formatArray($address, $options) === $before);
            $countries = $first->getCountries();
            $countries['US']['address_template'] = 'Mutated';
            check($second->formatArray($address, $options) === $before);
            file_put_contents($root . '/resources/templates/countries/worldwide.yaml', "default: {address_template: '{{road}}'}\nUS: {address_template: 'After {{road}}'}\n");
            $third = new Formatter();
            check($third->formatArray($address, $options) === "After Example Street\n");
            check($first->formatArray($address, $options) === $before);
            check($second->formatArray($address, $options) === $before);
            $first->loadTemplates();
            check($first->formatArray($address, $options) === "After Example Street\n");
            check($second->formatArray($address, $options) === $before);
            PHP);
    }

    public function testChangedComponentsCodesAndAbbreviationsAreReparsed(): void
    {
        $this->runIsolated(<<<'PHP'
            $address = ['road' => 'Example Street', 'town' => 'Town', 'postcode' => '10001', 'state' => 'Example'];
            $options = ['country' => 'US', 'abbreviate' => true];
            $first = new Formatter();
            check($first->formatArray($address, $options) === "Before Example St Town EX\n");
            file_put_contents($root . '/resources/templates/components.yaml', "name: road\n---\nname: city\naliases: [village]\n---\nname: town\n---\nname: postcode\n---\nname: state\n---\nname: country_code\n");
            file_put_contents($root . '/resources/templates/state_codes.yaml', "US: {NEW: Example}\n");
            file_put_contents($root . '/resources/templates/abbreviations/en.yaml', "road: {Street: Str}\n");
            $second = new Formatter();
            check($second->formatArray($address, $options) === "Before Example Str NEW\n");
            check($first->formatArray($address, $options) === "Before Example St Town EX\n");
            $first->loadTemplates();
            check($first->formatArray($address, $options) === "Before Example Str NEW\n");
            PHP);
    }

    public function testMissingFileStillThrowsAfterTheCacheIsWarm(): void
    {
        $this->runIsolated(<<<'PHP'
            $first = new Formatter();
            unlink($root . '/resources/templates/state_codes.yaml');
            foreach ([static fn() => new Formatter(), static fn() => $first->loadTemplates()] as $load) {
                try {
                    $load();
                    throw new RuntimeException('Missing templates were accepted.');
                } catch (\PredictHQ\AddressFormatter\Exception\TemplatesMissingException $error) {
                    check(str_contains($error->getMessage(), 'state_codes.yaml'));
                }
            }
            PHP);
    }

    public function testInvalidYamlStillThrowsAndCanBeRepaired(): void
    {
        $this->runIsolated(<<<'PHP'
            $first = new Formatter();
            $path = $root . '/resources/templates/state_codes.yaml';
            file_put_contents($path, 'US: [');
            try {
                new Formatter();
                throw new RuntimeException('Invalid YAML was accepted.');
            } catch (\Symfony\Component\Yaml\Exception\ParseException) {
            }
            file_put_contents($path, "US: {REPAIRED: Example}\n");
            $second = new Formatter();
            check($second->getStateCodes()['US'] === ['REPAIRED' => 'Example']);
            check($first->getStateCodes()['US'] === ['EX' => 'Example']);
            PHP);
    }

    private function write(string $path, string $contents): void
    {
        file_put_contents($this->root . '/resources/templates/' . $path, $contents);
    }

    private function runIsolated(string $code): void
    {
        // Use an isolated copy so file edits never touch the bundled snapshot.
        $setup = 'use PredictHQ\\AddressFormatter\\Formatter; require '
            . var_export(dirname(__DIR__) . '/vendor/autoload.php', true)
            . '; require ' . var_export($this->root . '/src/Formatter.php', true)
            . '; $root = ' . var_export($this->root, true) . ';'
            . <<<'PHP'
                function check(bool $condition): void {
                    static $number = 0;
                    ++$number;
                    if (!$condition) {
                        throw new RuntimeException('Cache regression at check ' . $number);
                    }
                }
                PHP;
        $process = new Process([PHP_BINARY, '-r', $setup . $code]);
        $process->setTimeout(5);
        $process->run();
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput() . $process->getOutput());
    }
}
