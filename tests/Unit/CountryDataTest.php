<?php

namespace Cpuchalver\LaravelCountry\Tests\Unit;

use Cpuchalver\LaravelCountry\Data\CountryData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CountryDataTest extends TestCase
{
    #[Test]
    public function it_maps_a_v5_country_object(): void
    {
        $country = CountryData::fromApi([
            'names' => ['common' => 'France', 'official' => 'French Republic'],
            'codes' => ['alpha_2' => 'fr', 'alpha_3' => 'fra', 'ccn3' => '250'],
            'region' => 'Europe',
            'capitals' => [['name' => 'Paris', 'attributes' => ['primary' => true]]],
            'calling_codes' => ['33'],
            'flag' => ['emoji' => '🇫🇷', 'url_png' => 'https://x/fr.png', 'url_svg' => 'https://x/fr.svg'],
        ]);

        $this->assertSame([
            'alpha2' => 'FR',
            'alpha3' => 'FRA',
            'numeric_code' => '250',
            'name' => 'France',
            'region' => 'Europe',
            'capital' => 'Paris',
            'calling_code' => '33',
            'flag_emoji' => '🇫🇷',
            'flag_png' => 'https://x/fr.png',
            'flag_svg' => 'https://x/fr.svg',
        ], $country->toAttributes());
    }

    #[Test]
    public function it_prefers_the_primary_capital(): void
    {
        $country = CountryData::fromApi($this->minimal([
            'capitals' => [
                ['name' => 'Bloemfontein', 'attributes' => ['judicial' => true]],
                ['name' => 'Pretoria', 'attributes' => ['primary' => true]],
            ],
        ]));

        $this->assertSame('Pretoria', $country->capital);
    }

    #[Test]
    public function it_falls_back_to_the_first_capital_and_accepts_strings(): void
    {
        $this->assertSame('Amsterdam', CountryData::fromApi($this->minimal([
            'capitals' => [['name' => 'Amsterdam'], ['name' => 'The Hague']],
        ]))->capital);

        $this->assertSame('Bern', CountryData::fromApi($this->minimal(['capitals' => ['Bern']]))->capital);
        $this->assertSame('Rome', CountryData::fromApi($this->minimal(['capitals' => 'Rome']))->capital);
    }

    #[Test]
    public function it_normalizes_calling_and_numeric_codes(): void
    {
        $country = CountryData::fromApi($this->minimal([
            'calling_codes' => ['+1-684', '1'],
            'codes' => ['alpha_2' => 'AS', 'ccn3' => '16'],
        ]));

        $this->assertSame('1684', $country->callingCode);
        $this->assertSame('016', $country->numericCode);
    }

    #[Test]
    public function missing_optional_fields_become_null(): void
    {
        $country = CountryData::fromApi([
            'names' => ['common' => 'Antarctica'],
            'codes' => ['alpha_2' => 'AQ', 'alpha_3' => 'nope!'],
            'capitals' => [],
            'calling_codes' => [],
            'region' => '',
        ]);

        $this->assertNull($country->alpha3);
        $this->assertNull($country->numericCode);
        $this->assertNull($country->region);
        $this->assertNull($country->capital);
        $this->assertNull($country->callingCode);
        $this->assertNull($country->flagEmoji);
    }

    #[Test]
    public function it_rejects_records_without_alpha2_or_name(): void
    {
        $this->assertNull(CountryData::fromApi(['names' => ['common' => 'Kosovo'], 'codes' => ['alpha_3' => 'UNK']]));
        $this->assertNull(CountryData::fromApi(['codes' => ['alpha_2' => 'FR']]));
        $this->assertNull(CountryData::fromApi(['names' => ['common' => 'X'], 'codes' => ['alpha_2' => 'XYZ']]));
        $this->assertNull(CountryData::fromApi([]));
    }

    private function minimal(array $overrides = []): array
    {
        return array_replace(['names' => ['common' => 'Test'], 'codes' => ['alpha_2' => 'TT']], $overrides);
    }
}
