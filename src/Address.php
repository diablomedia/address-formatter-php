<?php

namespace PredictHQ\AddressFormatter;

use PredictHQ\AddressFormatter\Formatter;

class Address
{
    private string|int|float $attention = '';
    private string|int|float $houseNumber = '';
    private string|int|float $house = '';
    private string|int|float $road = '';
    private string|int|float $village = '';
    private string|int|float $suburb = '';
    private string|int|float $city = '';
    private string|int|float $county = '';
    private string|int|float $postcode = '';
    private string|int|float $stateDistrict = '';
    private string|int|float $state = '';
    private string|int|float $region = '';
    private string|int|float $island = '';
    private string|int|float $country = '';
    private string|int|float $countryCode = '';
    private string|int|float $continent = '';

    public function setAttention(string|int|float $val): self
    {
        $this->attention = $val;

        return $this;
    }

    public function setHouseNumber(string|int|float $val): self
    {
        $this->houseNumber = $val;

        return $this;
    }

    public function setHouse(string|int|float $val): self
    {
        $this->house = $val;

        return $this;
    }

    public function setRoad(string|int|float $val): self
    {
        $this->road = $val;

        return $this;
    }

    public function setVillage(string|int|float $val): self
    {
        $this->village = $val;

        return $this;
    }

    public function setSuburb(string|int|float $val): self
    {
        $this->suburb = $val;

        return $this;
    }

    public function setCity(string|int|float $val): self
    {
        $this->city = $val;

        return $this;
    }

    public function setCounty(string|int|float $val): self
    {
        $this->county = $val;

        return $this;
    }

    public function setPostcode(string|int|float $val): self
    {
        $this->postcode = $val;

        return $this;
    }

    public function setStateDistrict(string|int|float $val): self
    {
        $this->stateDistrict = $val;

        return $this;
    }

    public function setState(string|int|float $val): self
    {
        $this->state = $val;

        return $this;
    }

    public function setRegion(string|int|float $val): self
    {
        $this->region = $val;

        return $this;
    }

    public function setIsland(string|int|float $val): self
    {
        $this->island = $val;

        return $this;
    }

    public function setCountry(string|int|float $val): self
    {
        $this->country = $val;

        return $this;
    }

    public function setCountryCode(string|int|float $val): self
    {
        $this->countryCode = $val;

        return $this;
    }

    public function setContinent(string|int|float $val): self
    {
        $this->continent = $val;

        return $this;
    }

    public function getAttention(): string|int|float
    {
        return $this->attention;
    }

    public function getHouseNumber(): string|int|float
    {
        return $this->houseNumber;
    }

    public function getHouse(): string|int|float
    {
        return $this->house;
    }

    public function getRoad(): string|int|float
    {
        return $this->road;
    }

    public function getVillage(): string|int|float
    {
        return $this->village;
    }

    public function getSuburb(): string|int|float
    {
        return $this->suburb;
    }

    public function getCity(): string|int|float
    {
        return $this->city;
    }

    public function getCounty(): string|int|float
    {
        return $this->county;
    }

    public function getPostcode(): string|int|float
    {
        return $this->postcode;
    }

    public function getStateDistrict(): string|int|float
    {
        return $this->stateDistrict;
    }

    public function getState(): string|int|float
    {
        return $this->state;
    }

    public function getRegion(): string|int|float
    {
        return $this->region;
    }

    public function getIsland(): string|int|float
    {
        return $this->island;
    }

    public function getCountry(): string|int|float
    {
        return $this->country;
    }

    public function getCountryCode(): string|int|float
    {
        return $this->countryCode;
    }

    public function getContinent(): string|int|float
    {
        return $this->continent;
    }

    public function format(): string
    {
        $f = new Formatter();
        return $f->format($this);
    }
}
