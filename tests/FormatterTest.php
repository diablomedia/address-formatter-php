<?php

namespace PredictHQ\AddressFormatter\Tests;

use PHPUnit\Framework\TestCase;
use PredictHQ\AddressFormatter\Formatter;

class FormatterTest extends TestCase
{
    public function testCountryTemplatesAreAvailable(): void
    {
        $formatter = new Formatter();
        $countries = $formatter->getCountries();

        self::assertArrayHasKey('default', $countries);
        self::assertArrayHasKey('NZ', $countries);
        self::assertArrayHasKey('US', $countries);
        self::assertNotEmpty($countries['NZ']['address_template'] ?? '');
    }
}
