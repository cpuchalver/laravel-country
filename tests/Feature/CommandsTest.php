<?php

namespace Cpuchalver\LaravelCountry\Tests\Feature;

use Cpuchalver\LaravelCountry\Models\Country;
use Cpuchalver\LaravelCountry\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class CommandsTest extends TestCase
{
    #[Test]
    public function status_shows_the_local_state(): void
    {
        Country::factory()->count(3)->create();

        $this->artisan('country:status')
            ->expectsOutputToContain('configured')
            ->expectsOutputToContain('Countries in database')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    #[Test]
    public function status_reports_a_missing_api_key(): void
    {
        config(['country.api.key' => null]);

        $this->artisan('country:status')
            ->expectsOutputToContain('missing (COUNTRY_API_KEY)')
            ->assertSuccessful();
    }

    #[Test]
    public function status_compares_with_the_api(): void
    {
        Country::factory()->count(3)->create();
        Http::fake([self::API.'*' => Http::response($this->page([['codes' => ['alpha_2' => 'AF']]], 250))]);

        $this->artisan('country:status', ['--remote' => true])
            ->expectsOutputToContain('Countries in API')
            ->expectsOutputToContain('out of sync')
            ->assertSuccessful();
    }

    #[Test]
    public function status_fails_when_the_api_is_unreachable(): void
    {
        Http::fake([self::API.'*' => Http::response(['errors' => [['message' => 'Invalid API key.']]], 401)]);

        $this->artisan('country:status', ['--remote' => true])
            ->expectsOutputToContain('Invalid API key.')
            ->assertFailed();
    }

    #[Test]
    public function show_displays_a_local_country(): void
    {
        Country::factory()->create(['alpha2' => 'FR', 'alpha3' => 'FRA', 'name' => 'France', 'capital' => 'Paris']);

        $this->artisan('country:show', ['code' => 'fra'])
            ->expectsOutputToContain('France')
            ->expectsOutputToContain('Paris')
            ->assertSuccessful();
    }

    #[Test]
    public function show_can_read_from_the_api(): void
    {
        Http::fake([self::API.'/codes.alpha_2/JP*' => Http::response($this->page([$this->fixture('countries')[3]], 1))]);

        $this->artisan('country:show', ['code' => 'JP', '--remote' => true])
            ->expectsOutputToContain('Tokyo')
            ->assertSuccessful();

        $this->assertSame(0, Country::count());
    }

    #[Test]
    public function show_fails_for_an_unknown_country(): void
    {
        $this->artisan('country:show', ['code' => 'ZZ'])
            ->expectsOutputToContain('Country [ZZ] not found in the database.')
            ->assertFailed();
    }

    #[Test]
    public function install_publishes_the_config(): void
    {
        $path = config_path('country.php');
        File::delete($path);

        $this->artisan('country:install')->assertSuccessful();

        $this->assertFileExists($path);
        $this->assertSame(require __DIR__.'/../../config/country.php', require $path);

        File::delete($path);
    }

    #[Test]
    public function install_with_sync_fails_without_an_api_key(): void
    {
        config(['country.api.key' => null]);

        $this->artisan('country:install', ['--sync' => true])
            ->expectsOutputToContain('COUNTRY_API_KEY')
            ->assertFailed();

        File::delete(config_path('country.php'));
    }

    #[Test]
    public function install_can_migrate_and_sync(): void
    {
        $this->fakeCountries($this->fixture('countries'));

        $this->artisan('country:install', ['--sync' => true])->assertSuccessful();

        $this->assertSame(6, Country::count());
        File::delete(config_path('country.php'));
    }
}
