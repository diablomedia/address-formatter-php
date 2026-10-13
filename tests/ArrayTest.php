<?php

namespace PredictHQ\AddressFormatter\Tests;

use PHPUnit\Framework\TestCase;
use PredictHQ\AddressFormatter\Formatter;

class ArrayTest extends TestCase
{
    public function testAddressArray(): void
    {
        $address = [
            'city' => 'Wellington',
            'country' => 'New Zealand',
            'country_code' => 'NZ',
            'county' => 'Wellington City',
            'house_number' => 53,
            'postcode' => 6011,
            'road' => 'Pirie Street',
            'state' => 'Wellington',
            'suburb' => 'Mount Victoria',
        ];

        $expected = '53 Pirie Street
Mount Victoria
Wellington 6011
New Zealand
';

        $f = new Formatter();
        $actual = $f->formatArray($address);

        $this->assertSame($expected, $actual);
    }

    public function testUtf8AddressArray(): void
    {
        $address = [
            'county' => 'Città metropolitana di Roma Capitale',
            'region' => 'Latium',
            'country_code' => 'IT',
            'country' => 'Italy',
        ];

        $expected = 'Roma Capitale
Italy
';

        $f = new Formatter();
        $actual = $f->formatArray($address);

        $this->assertSame($expected, $actual);
    }

    public function testNullComponentsDoNotOverrideAliases(): void
    {
        $formatter = new Formatter();
        $address = [
            'city' => null,
            'town' => 'Wellington',
            'country' => 'New Zealand',
            'country_code' => 'NZ',
            'house_number' => 53,
            'postcode' => 6011,
            'road' => 'Pirie Street',
        ];

        self::assertSame("53 Pirie Street\nWellington 6011\nNew Zealand\n", $formatter->formatArray($address));
    }

}
