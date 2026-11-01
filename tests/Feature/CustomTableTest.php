<?php

namespace Cpuchalver\LaravelCountry\Tests\Feature;

use Cpuchalver\LaravelCountry\Models\Country;
use Cpuchalver\LaravelCountry\Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

class CustomTableTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('country.table', 'geo_countries');
    }

    #[Test]
    public function the_table_name_comes_from_the_config(): void
    {
        $this->assertTrue(Schema::hasTable('geo_countries'));
        $this->assertFalse(Schema::hasTable('countries'));

        $this->fakeCountries($this->fixture('countries'));
        $this->artisan('country:sync')->assertSuccessful();

        $this->assertSame('geo_countries', (new Country)->getTable());
        $this->assertSame(6, Country::count());
    }
}
