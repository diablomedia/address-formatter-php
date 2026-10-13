<?php

namespace PredictHQ\AddressFormatter;

use Mustache\Engine;
use Symfony\Component\Yaml\Yaml;
use PredictHQ\AddressFormatter\Exception\TemplatesMissingException;

/**
 * Format an address based on the country template.
 *
 * Takes advantage of the OpenCageData address-formatting templates available at:
 * @link https://github.com/OpenCageData/address-formatting
 *
 * Currently using address templates version:
 * @link https://github.com/OpenCageData/address-formatting
 *
 * Also based on the Perl address formatter using the same address templates:
 * @link https://metacpan.org/pod/Geo::Address::Formatter
 *
 * Test cases come from the OpenCageData repo.
 *
 * @phpstan-type AddressParts array<string, string>
 * @phpstan-type FormatOptions array{country?: string, abbreviate?: bool}
 * @phpstan-type Replacements list<array{string, string}>
 * @phpstan-type Template array{address_template?: string, fallback_template?: string, replace?: Replacements, postformat_replace?: Replacements, use_country?: string, change_country?: string, add_component?: string}
 */
class Formatter
{
    /** @var array<string, list<string>> */
    private array $components = [];
    /** @var array<string, string> */
    private array $componentAliases = [];
    /** @var array<string, Template> */
    private array $templates = [];
    /** @var array<string, array<array-key, string|array<string, string>>> */
    private array $stateCodes = [];
    /** @var array<string, string> */
    private array $countryToLang = [];
    /** @var array<string, array<array-key, string|array<string, string>>> */
    private array $countyCodes = [];
    /** @var array<string, array<string, array<string, string>>> */
    private array $abbreviations = [];
    /** @var list<string> */
    private array $validReplacementComponents = [
        'state',
    ];

    public function __construct()
    {
        $this->loadTemplates();
    }

    /**
     * @param FormatOptions $options
     */
    public function format(Address $address, array $options = []): string
    {
        $addressArray = [];

        if (strlen((string) $address->getAttention()) > 0) {
            $addressArray['attention'] = $address->getAttention();
        }
        if (strlen((string) $address->getHouseNumber()) > 0) {
            $addressArray['house_number'] = $address->getHouseNumber();
        }
        if (strlen((string) $address->getHouse()) > 0) {
            $addressArray['house'] = $address->getHouse();
        }
        if (strlen((string) $address->getRoad()) > 0) {
            $addressArray['road'] = $address->getRoad();
        }
        if (strlen((string) $address->getVillage()) > 0) {
            $addressArray['village'] = $address->getVillage();
        }
        if (strlen((string) $address->getSuburb()) > 0) {
            $addressArray['suburb'] = $address->getSuburb();
        }
        if (strlen((string) $address->getCity()) > 0) {
            $addressArray['city'] = $address->getCity();
        }
        if (strlen((string) $address->getCounty()) > 0) {
            $addressArray['county'] = $address->getCounty();
        }
        if (strlen((string) $address->getPostcode()) > 0) {
            $addressArray['postcode'] = $address->getPostcode();
        }
        if (strlen((string) $address->getStateDistrict()) > 0) {
            $addressArray['state_district'] = $address->getStateDistrict();
        }
        if (strlen((string) $address->getState()) > 0) {
            $addressArray['state'] = $address->getState();
        }
        if (strlen((string) $address->getRegion()) > 0) {
            $addressArray['region'] = $address->getRegion();
        }
        if (strlen((string) $address->getIsland()) > 0) {
            $addressArray['island'] = $address->getIsland();
        }
        if (strlen((string) $address->getCountry()) > 0) {
            $addressArray['country'] = $address->getCountry();
        }
        if (strlen((string) $address->getCountryCode()) > 0) {
            $addressArray['country_code'] = $address->getCountryCode();
        }
        if (strlen((string) $address->getContinent()) > 0) {
            $addressArray['continent'] = $address->getContinent();
        }

        return $this->formatArray($addressArray, $options);
    }

    // $options
    // 'country', which should be an uppercase ISO 3166-1:alpha-2 code
    // e.g. 'GB' for Great Britain, 'DE' for Germany, etc.
    // If ommited we try to find the country in the address components.
    //
    // 'abbreviate', if supplied common abbreviations are applied
    // to the resulting output.
    /**
     * @param array<string, string|int|float|null> $addressArray
     * @param FormatOptions $options
     */
    public function formatArray(array $addressArray, array $options = []): string
    {
        $addressArray = array_filter($addressArray, static fn($value): bool => $value !== null);
        $addressArray = array_map(static fn($value): string => (string) $value, $addressArray);

        $countryCode = $options['country'] ?? $this->determineCountryCode($addressArray);

        if (strlen($countryCode) > 0) {
            $addressArray['country_code'] = $countryCode;
        }

        //Set the alias values (unless it would override something)
        foreach ($this->componentAliases as $key => $val) {
            if (isset($addressArray[$key]) && !isset($addressArray[$val])) {
                $addressArray[$val] = $addressArray[$key];
            }
        }

        //Do a quick and dirty sanity check
        $addressArray = $this->sanityCleanAddress($addressArray);

        //Figure out which template to use
        $tpl = $this->templates[strtoupper($countryCode)] ?? $this->templates['default'];
        $tplText = $tpl['address_template'] ?? '';

        //Do we have the minimum components for an address, or should we use the fallback template?
        if (!$this->hasMinimumAddressComponents($addressArray)) {
            if (isset($tpl['fallback_template'])) {
                $tplText = $tpl['fallback_template'];
            } elseif (isset($this->templates['default']['fallback_template'])) {
                $tplText = $this->templates['default']['fallback_template'];
            }
        }

        //Cleanup the components
        $addressArray = $this->fixCountry($addressArray);

        if (isset($tpl['replace'])) {
            $addressArray = $this->applyReplacements($addressArray, $tpl['replace']);
        }

        $addressArray = $this->addStateCode($addressArray);
        $addressArray = $this->addCountyCode($addressArray);

        //Add attention, but only if needed
        $unknownComponents = $this->findUnknownComponents($addressArray);

        if (count($unknownComponents) > 0) {
            $addressArray['attention'] = implode(', ', $unknownComponents);
        }

        if (isset($options['abbreviate']) && $options['abbreviate'] == true) {
            $addressArray = $this->abbreviate($addressArray);
        }

        //Render the template
        // US city and state names may legitimately be identical (New York, New York).
        $deduplicateParts = $countryCode !== 'US';
        $text = $this->render($tplText, $addressArray, $deduplicateParts);

        //Post render cleanup
        if (isset($tpl['postformat_replace'])) {
            $text = $this->postFormatReplace($text, $tpl['postformat_replace']);

            //Run through cleanup again now that we've done replacements etc
            $text = $this->cleanupRendered($text, $deduplicateParts);
        }

        return $text;
    }

    /**
     * @param AddressParts $addressArray
     * @return list<string>
     */
    private function findUnknownComponents(array $addressArray): array
    {
        $unknown = [];

        foreach ($addressArray as $key => $val) {
            if (!array_key_exists($key, $this->components) && !array_key_exists($key, $this->componentAliases)) {
                $unknown[] = $val;
            }
        }

        return $unknown;
    }

    /**
     * @param AddressParts $addressArray
     * @return AddressParts
     */
    private function abbreviate(array $addressArray): array
    {
        if (isset($addressArray['country_code'])) {
            $countryCode = strtoupper($addressArray['country_code']);

            if (array_key_exists($countryCode, $this->countryToLang)) {
                $langs = explode(',', $this->countryToLang[$countryCode]);

                foreach ($langs as $lang) {
                    $lang = strtoupper($lang);

                    // Do we have an abbreviation for this language?
                    if (array_key_exists($lang, $this->abbreviations)) {
                        $abbreviations = $this->abbreviations[$lang];

                        foreach ($abbreviations as $key => $val) {
                            if (array_key_exists($key, $addressArray)) {
                                foreach ($val as $long => $short) {
                                    $addressArray[$key] = preg_replace("/\b$long\b/u", $short, $addressArray[$key]) ?? $addressArray[$key];

                                }
                            }
                        }
                    }
                }
            }
        }

        return $addressArray;
    }

    /**
     * @param Replacements $replacements
     */
    private function postFormatReplace(string $text, array $replacements): string
    {
        //Remove duplicates
        $beforePieces = explode(', ', $text);
        $seen = [];
        $afterPieces = [];

        foreach ($beforePieces as $piece) {
            $piece = preg_replace('/^\s+/u', '', $piece) ?? $piece;

            $seen[$piece] ??= 0;

            $seen[$piece]++;

            if ($seen[$piece] > 1) {
                continue;
            }

            $afterPieces[] = $piece;
        }

        $text = implode(', ', $afterPieces);

        //Do any country-specific rules
        foreach ($replacements as $replacement) {
            $text = preg_replace('/' . $replacement[0] . '/u', $replacement[1], $text) ?? $text;
        }

        return $text;
    }

    /**
     * @param AddressParts $addressArray
     */
    private function render(string $tplText, array $addressArray, bool $deduplicateParts): string
    {
        $m = new Engine();

        $context = $addressArray;
        $context['first'] = function (string $text) use (&$m, &$addressArray) {
            $newText = $m->render($text, $addressArray);
            $matched = preg_split("/\s*\|\|\s*/", $newText) ?: [];
            $first = current(array_filter($matched));

            return $first === false ? '' : $first;
        };

        $text = $m->render($tplText, $context);

        //Cleanup the output
        $text = $this->cleanupRendered($text, $deduplicateParts);

        //Make sure we have at least something
        if (preg_match('/\w/u', $text) == 0) {
            $backupParts = [];

            foreach ($addressArray as $key => $val) {
                if (strlen($val) > 0) {
                    $backupParts[] = $val;
                }
            }

            $text = implode(', ', $backupParts);

            //Cleanup the output again
            $text = $this->cleanupRendered($text, $deduplicateParts);
        }

        return $text;
    }

    private function cleanupRendered(string $text, bool $deduplicateParts): string
    {
        $replacements = [
            '/[\},\s]+$/u' => '',
            '/^[,\s]+/u' => '',
            '/^- /u' => '', // line starting with dash due to a parameter missing
            '/,\s*,/u' => ', ', //multiple commas to one
            '/\h+,\h+/u' => ', ', //one horiz whitespace behind comma
            '/\h\h+/u' => ' ', //multiple horiz whitespace to one
            "/\h\n/u" => "\n", //horiz whitespace, newline to newline
            "/\n,/u" => "\n", //newline comma to just newline
            '/,,+/u' => ',', //multiple commas to one
            "/,\n/u" => "\n", //comma newline to just newline
            "/\n\h+/u" => "\n", //newline plus space to newline
            "/\n\n+/u" => "\n", //multiple newline to one
        ];

        foreach ($replacements as $key => $val) {
            $text = preg_replace($key, $val, $text) ?? $text;
        }

        //Final dedupe across lines and, where appropriate, within lines
        $beforeLines = explode("\n", $text);
        $seenLines = [];
        $afterLines = [];

        foreach ($beforeLines as $line) {
            $line = preg_replace('/^\h+/u', '', $line) ?? $line;
            $line = preg_replace('/\h+$/u', '', $line) ?? $line;

            $seenLines[$line] ??= 0;

            $seenLines[$line]++;

            if ($seenLines[$line] > 1) {
                //Don't repeat this line
                continue;
            }

            if ($deduplicateParts) {
                $line = implode(', ', array_unique(explode(', ', $line)));
            }

            $afterLines[] = $line;
        }

        $text = implode("\n", $afterLines);

        $text = preg_replace('/^\s+/u', '', $text) ?? $text; //remove leading whitespace
        $text = preg_replace('/\s+$/u', '', $text) ?? $text; //remove end whitespace

        $text .= "\n"; //add final newline

        return $text;
    }

    /**
     * @param AddressParts $addressArray
     * @return AddressParts
     */
    private function fixCountry(array $addressArray): array
    {
        /**
         * Hacks for bad country data
         */
        if (isset($addressArray['country'])) {
            if (isset($addressArray['state'])) {
                /**
                 * If the country is a number, use the state as country
                 */
                if (is_numeric($addressArray['country'])) {
                    $addressArray['country'] = $addressArray['state'];
                    unset($addressArray['state']);
                }
            }
        }

        return $addressArray;
    }

    /**
     * @param AddressParts $addressArray
     * @param Replacements $replacements
     * @return AddressParts
     */
    private function applyReplacements(array $addressArray, array $replacements): array
    {
        foreach ($replacements as [$pattern, $replacement]) {
            if (preg_match('/^([a-z_]+)=(.+)$/s', $pattern, $matches) === 1) {
                $component = $matches[1];

                if (isset($addressArray[$component])) {
                    $addressArray[$component] = preg_replace('/' . $matches[2] . '/iu', $replacement, $addressArray[$component]) ?? $addressArray[$component];
                }

                continue;
            }

            foreach ($addressArray as $key => $value) {
                $addressArray[$key] = preg_replace('/' . $pattern . '/u', $replacement, $value) ?? $value;
            }
        }

        return $addressArray;
    }

    /**
     * @param AddressParts $addressArray
     * @return AddressParts
     */
    private function addStateCode(array $addressArray): array
    {
        return $this->addCode('state', $addressArray);
    }

    /**
     * @param AddressParts $addressArray
     * @return AddressParts
     */
    private function addCountyCode(array $addressArray): array
    {
        return $this->addCode('county', $addressArray);
    }

    /**
     * @param AddressParts $addressArray
     * @return AddressParts
     */
    private function addCode(string $type, array $addressArray): array
    {
        if (array_key_exists('country_code', $addressArray) && array_key_exists($type, $addressArray)) {
            $code = $type . '_code';

            if (!array_key_exists($code, $addressArray)) {
                //Make sure country code is uppercase
                $addressArray['country_code'] = strtoupper($addressArray['country_code']);

                if ($type === 'state') {
                    if (array_key_exists($addressArray['country_code'], $this->stateCodes)) {
                        foreach ($this->stateCodes[$addressArray['country_code']] as $key => $val) {
                            if ($this->matchesCodeName($addressArray['state'], $val)) {
                                $addressArray['state_code'] = (string) $key;
                            }
                        }

                        // Try again for odd variants like "United States Virgin Islands"
                        if (!array_key_exists('state_code', $addressArray)) {
                            if ($addressArray['country_code'] == 'US') {
                                if (preg_match('/^united states/i', $addressArray['state']) > 0) {
                                    $state = $addressArray['state'];
                                    $state = preg_replace('/^United States/i', 'US', $state) ?? $state;

                                    foreach ($this->stateCodes[$addressArray['country_code']] as $key => $val) {
                                        if ($this->matchesCodeName($state, $val)) {
                                            $addressArray['state_code'] = (string) $key;
                                            break;
                                        }
                                    }
                                }

                                if (preg_match('/^washington,? d\.?c\.?/i', $addressArray['state']) > 0) {
                                    $addressArray['state_code'] = 'DC';
                                    $addressArray['state'] = 'District of Columbia';
                                    $addressArray['city'] = 'Washington';
                                }
                            }
                        }
                    }
                } elseif ($type === 'county') {
                    if (array_key_exists($addressArray['country_code'], $this->countyCodes)) {
                        foreach ($this->countyCodes[$addressArray['country_code']] as $key => $val) {
                            if ($this->matchesCodeName($addressArray['county'], $val)) {
                                $addressArray['county_code'] = (string) $key;
                            }
                        }
                    }
                }
            }
        }

        return $addressArray;
    }

    /**
     * @param string|array<string, string> $names
     */
    private function matchesCodeName(string $value, string|array $names): bool
    {
        foreach (is_array($names) ? $names : [$names] as $name) {
            if (strtoupper($value) === strtoupper($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param AddressParts $addressArray
     */
    private function determineCountryCode(array &$addressArray): string
    {
        $countryCode = $addressArray['country_code'] ?? '';

        //Make sure it is 2 characters
        if (strlen($countryCode) == 2) {
            if (strtoupper($countryCode) == 'UK') {
                $countryCode = 'GB';
            }

            $countryCode = strtoupper($countryCode);

            /**
             * Check if the country config tells us to use a different country code.
             * Used in cases of dependent territories like American Samoa (AS) and Puerto Rico (PR)
             */
            if (isset($this->templates[$countryCode])) {
                if (isset($this->templates[$countryCode]['use_country'])) {
                    $oldCountryCode = $countryCode;
                    $countryCode = $this->templates[$countryCode]['use_country'];

                    if (isset($this->templates[$oldCountryCode]['change_country'])) {
                        $newCountry = $this->templates[$oldCountryCode]['change_country'];

                        if (preg_match('/\$(\w*)/', $newCountry, $matches) > 0) {
                            $component = $matches[1];

                            if (isset($addressArray[$component])) {
                                $newCountry = preg_replace('/\$' . $component . '/', $addressArray[$component], $newCountry) ?? $newCountry;
                            } else {
                                $newCountry = preg_replace('/\$' . $component . '/', '', $newCountry) ?? $newCountry;
                            }
                        }

                        $addressArray['country'] = $newCountry;
                    }

                    if (isset($this->templates[$oldCountryCode]['add_component']) && str_contains($this->templates[$oldCountryCode]['add_component'], '=')) {
                        [$k, $v] = explode('=', $this->templates[$oldCountryCode]['add_component']);

                        if (in_array($k, $this->validReplacementComponents)) {
                            $addressArray[$k] = $v;
                        }
                    }
                }
            }

            if ($countryCode == 'NL') {
                if (isset($addressArray['state']) && $addressArray['state'] == 'Curaçao') {
                    $countryCode = 'CW';
                    $addressArray['country'] = 'Curaçao';
                } elseif (isset($addressArray['state']) && preg_match('/^sint maarten/i', $addressArray['state']) > 0) {
                    $countryCode = 'SX';
                    $addressArray['country'] = 'Sint Maarten';
                } elseif (isset($addressArray['state']) && preg_match('/^Aruba/i', $addressArray['state']) > 0) {
                    $countryCode = 'AW';
                    $addressArray['country'] = 'Aruba';
                }
            }
        }

        return $countryCode;
    }

    /**
     * @param AddressParts $addressArray
     * @return AddressParts
     */
    private function sanityCleanAddress(array $addressArray): array
    {
        if (isset($addressArray['postcode'])) {
            if (strlen($addressArray['postcode']) > 20) {
                unset($addressArray['postcode']);
            } elseif (preg_match('/\d+;\d+/', $addressArray['postcode']) > 0) {
                // Sometimes OSM has postcode ranges
                unset($addressArray['postcode']);
            } elseif (preg_match('/^(\d{5}),\d{5}/', $addressArray['postcode'], $matches) > 0) {
                // Use the first postcode from the range
                $addressArray['postcode'] = $matches[1];
            }
        }

        //Try and catch values containing URLs
        foreach ($addressArray as $key => $val) {
            if (preg_match('|https?://|', $val) > 0) {
                unset($addressArray[$key]);
            }
        }

        return $addressArray;
    }

    /**
     * @param AddressParts $addressArray
     */
    private function hasMinimumAddressComponents(array $addressArray): bool
    {
        $missing = 0;
        $minThreshold = 2;
        $requiredComponents = ['road', 'postcode']; //These should probably be provided in the templates or somewhere else other than here!

        foreach ($requiredComponents as $requiredComponent) {
            if (!isset($addressArray[$requiredComponent])) {
                $missing++;
            }

            if ($missing >= $minThreshold) {
                break;
            }
        }

        return ($missing < $minThreshold) ? true : false;
    }

    public function loadTemplates(): void
    {
        $templatesPath = dirname(__DIR__) . '/resources/templates';

        if (is_dir($templatesPath)) {
            $countriesPath = implode(DIRECTORY_SEPARATOR, [$templatesPath, 'countries', 'worldwide.yaml']);
            $componentsPath = implode(DIRECTORY_SEPARATOR, [$templatesPath, 'components.yaml']);
            $stateCodesPath = implode(DIRECTORY_SEPARATOR, [$templatesPath, 'state_codes.yaml']);
            $countryToLangPath = implode(DIRECTORY_SEPARATOR, [$templatesPath, 'country2lang.yaml']);
            $countyCodesPath = implode(DIRECTORY_SEPARATOR, [$templatesPath, 'county_codes.yaml']);
            $abbreviationFiles = glob(implode(DIRECTORY_SEPARATOR, [$templatesPath, 'abbreviations/*.yaml'])) ?: [];

            $components = [];
            $componentAliases = [];
            $templates = [];
            $stateCodes = [];
            $countryToLang = [];
            $countyCodes = [];
            $abbreviations = [];

            /**
             * The components file is made up of multiple yaml documents but the symfony yaml parser
             * doesn't support multiple docs in a single file. So we split it into multiple docs.
             */
            $componentYamlParts = explode('---', $this->readTemplateFile($componentsPath));

            foreach ($componentYamlParts as $key => $val) {
                $component = Yaml::parse($val);

                if (isset($component['aliases'])) {
                    foreach ($component['aliases'] as $k => $v) {
                        $componentAliases[$v] = $component['name'];
                    }
                }

                $components[$component['name']] = $component['aliases'] ?? [];
            }

            //Load the country templates, state codes and country2lang data
            $templates = Yaml::parse($this->readTemplateFile($countriesPath));
            $stateCodes = Yaml::parse($this->readTemplateFile($stateCodesPath));
            $countryToLang = Yaml::parse($this->readTemplateFile($countryToLangPath));
            $countyCodes = Yaml::parse($this->readTemplateFile($countyCodesPath));

            //Load the abbreviations files
            foreach ($abbreviationFiles as $key => $val) {
                $lang = strtoupper(basename($val, '.yaml'));
                $data = Yaml::parse($this->readTemplateFile($val));
                $abbreviations[$lang] = $data;
            }

            $this->components = $components;
            $this->componentAliases = $componentAliases;
            $this->templates = $templates;
            $this->stateCodes = $stateCodes;
            $this->countryToLang = $countryToLang;
            $this->countyCodes = $countyCodes;
            $this->abbreviations = $abbreviations;
        } else {
            throw new TemplatesMissingException('Address formatting templates path cannot be found.');
        }
    }

    private function readTemplateFile(string $path): string
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new TemplatesMissingException('Address formatting template cannot be read: ' . $path);
        }

        return $contents;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getComponents(): array
    {
        return $this->components;
    }

    /**
     * @return array<string, Template>
     */
    public function getCountries(): array
    {
        return $this->templates;
    }

    /**
     * @return array<string, array<array-key, string|array<string, string>>>
     */
    public function getStateCodes(): array
    {
        return $this->stateCodes;
    }
}
