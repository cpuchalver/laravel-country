<?php

namespace Cpuchalver\LaravelCountry\Tests\Feature;

use Cpuchalver\LaravelCountry\Models\Country;
use Cpuchalver\LaravelCountry\Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

class CountryModelTest extends TestCase
{
    #[Test]
    public function the_migration_creates_the_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('countries', [
            'id', 'alpha2', 'alpha3', 'numeric_code', 'name', 'region', 'capital',
            'calling_code', 'flag_emoji', 'flag_png', 'flag_svg', 'synced_at', 'created_at', 'updated_at',
        ]));
    }

    #[Test]
    public function it_finds_countries_by_code_case_insensitively(): void
    {
        Country::factory()->create(['alpha2' => 'FR', 'alpha3' => 'FRA', 'name' => 'France']);

        $this->assertSame('France', Country::findByCode('fr')->name);
        $this->assertSame('France', Country::findByCode(' FRA ')->name);
        $this->assertNull(Country::findByCode('DE'));
    }

    #[Test]
    public function it_filters_by_region(): void
    {
        Country::factory()->create(['region' => 'Europe']);
        Country::factory()->create(['region' => 'Europe']);
        Country::factory()->create(['region' => 'Asia']);

        $this->assertSame(2, Country::region('Europe')->count());
    }

    #[Test]
    public function it_formats_the_dialing_code(): void
    {
        $this->assertSame('+33', Country::factory()->make(['calling_code' => '33'])->dialing_code);
        $this->assertNull(Country::factory()->make(['calling_code' => null])->dialing_code);
    }

    #[Test]
    public function alpha2_is_unique(): void
    {
        Country::factory()->create(['alpha2' => 'FR', 'alpha3' => 'FRA']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Country::factory()->create(['alpha2' => 'FR', 'alpha3' => 'FRX']);
    }
}
