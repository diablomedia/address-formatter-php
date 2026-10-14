<?php

namespace PredictHQ\AddressFormatter;

use PredictHQ\AddressFormatter\Formatter;

class Address
{
    private string|int|float|null $attention = '';
    private string|int|float|null $houseNumber = '';
    private string|int|float|null $house = '';
    private string|int|float|null $road = '';
    private string|int|float|null $village = '';
    private string|int|float|null $suburb = '';
    private string|int|float|null $city = '';
    private string|int|float|null $county = '';
    private string|int|float|null $postcode = '';
    private string|int|float|null $stateDistrict = '';
    private string|int|float|null $state = '';
    private string|int|float|null $region = '';
    private string|int|float|null $island = '';
    private string|int|float|null $country = '';
    private string|int|float|null $countryCode = '';
    private string|int|float|null $continent = '';

    public function setAttention(string|int|float|null $val): self
    {
        $this->attention = $val;

        return $this;
    }

    public function setHouseNumber(string|int|float|null $val): self
    {
        $this->houseNumber = $val;

        return $this;
    }

    public function setHouse(string|int|float|null $val): self
    {
        $this->house = $val;

        return $this;
    }

    public function setRoad(string|int|float|null $val): self
    {
        $this->road = $val;

        return $this;
    }

    public function setVillage(string|int|float|null $val): self
    {
        $this->village = $val;

        return $this;
    }

    public function setSuburb(string|int|float|null $val): self
    {
        $this->suburb = $val;

        return $this;
    }

    public function setCity(string|int|float|null $val): self
    {
        $this->city = $val;

        return $this;
    }

    public function setCounty(string|int|float|null $val): self
    {
        $this->county = $val;

        return $this;
    }

    public function setPostcode(string|int|float|null $val): self
    {
        $this->postcode = $val;

        return $this;
    }

    public function setStateDistrict(string|int|float|null $val): self
    {
        $this->stateDistrict = $val;

        return $this;
    }

    public function setState(string|int|float|null $val): self
    {
        $this->state = $val;

        return $this;
    }

    public function setRegion(string|int|float|null $val): self
    {
        $this->region = $val;

        return $this;
    }

    public function setIsland(string|int|float|null $val): self
    {
        $this->island = $val;

        return $this;
    }

    public function setCountry(string|int|float|null $val): self
    {
        $this->country = $val;

        return $this;
    }

    public function setCountryCode(string|int|float|null $val): self
    {
        $this->countryCode = $val;

        return $this;
    }

    public function setContinent(string|int|float|null $val): self
    {
        $this->continent = $val;

        return $this;
    }

    public function getAttention(): string|int|float|null
    {
        return $this->attention;
    }

    public function getHouseNumber(): string|int|float|null
    {
        return $this->houseNumber;
    }

    public function getHouse(): string|int|float|null
    {
        return $this->house;
    }

    public function getRoad(): string|int|float|null
    {
        return $this->road;
    }

    public function getVillage(): string|int|float|null
    {
        return $this->village;
    }

    public function getSuburb(): string|int|float|null
    {
        return $this->suburb;
    }

    public function getCity(): string|int|float|null
    {
        return $this->city;
    }

    public function getCounty(): string|int|float|null
    {
        return $this->county;
    }

    public function getPostcode(): string|int|float|null
    {
        return $this->postcode;
    }

    public function getStateDistrict(): string|int|float|null
    {
        return $this->stateDistrict;
    }

    public function getState(): string|int|float|null
    {
        return $this->state;
    }

    public function getRegion(): string|int|float|null
    {
        return $this->region;
    }

    public function getIsland(): string|int|float|null
    {
        return $this->island;
    }

    public function getCountry(): string|int|float|null
    {
        return $this->country;
    }

    public function getCountryCode(): string|int|float|null
    {
        return $this->countryCode;
    }

    public function getContinent(): string|int|float|null
    {
        return $this->continent;
    }

    public function format(): string
    {
        $f = new Formatter();
        return $f->format($this);
    }
}
