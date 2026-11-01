<?php

namespace Cpuchalver\LaravelCountry\Tests\Feature;

use Cpuchalver\LaravelCountry\Database\Seeders\CountrySeeder;
use Cpuchalver\LaravelCountry\Events\CountriesSynced;
use Cpuchalver\LaravelCountry\Models\Country;
use Cpuchalver\LaravelCountry\Sync\CountrySynchronizer;
use Cpuchalver\LaravelCountry\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;

class SyncCountriesTest extends TestCase
{
    #[Test]
    public function the_sync_command_creates_every_country(): void
    {
        $this->fakeCountries($this->fixture('countries'));

        $this->artisan('country:sync')
            ->expectsTable(['Created', 'Updated', 'Unchanged', 'Deleted'], [[6, 0, 0, 0]])
            ->expectsOutputToContain('6 countries synchronized.')
            ->assertSuccessful();

        $this->assertSame(6, Country::count());

        $france = Country::findByCode('FR');
        $this->assertSame('FRA', $france->alpha3);
        $this->assertSame('250', $france->numeric_code);
        $this->assertSame('France', $france->name);
        $this->assertSame('Europe', $france->region);
        $this->assertSame('Paris', $france->capital);
        $this->assertSame('33', $france->calling_code);
        $this->assertSame('🇫🇷', $france->flag_emoji);
        $this->assertSame('https://flags.restcountries.com/v5/w320/fr.png', $france->flag_png);
        $this->assertNotNull($france->synced_at);

        $this->assertSame('Pretoria', Country::findByCode('ZA')->capital);
        $this->assertNull(Country::findByCode('AQ')->capital);
    }

    #[Test]
    public function a_second_sync_reports_unchanged_rows_and_only_refreshes_synced_at(): void
    {
        $this->fakeCountries($this->fixture('countries'));
        $this->artisan('country:sync')->assertSuccessful();

        $updatedAt = Country::findByCode('FR')->updated_at;
        Carbon::setTestNow(now()->addDay());

        $this->artisan('country:sync')
            ->expectsTable(['Created', 'Updated', 'Unchanged', 'Deleted'], [[0, 0, 6, 0]])
            ->assertSuccessful();

        $france = Country::findByCode('FR');
        $this->assertEquals($updatedAt, $france->updated_at);
        $this->assertTrue($france->synced_at->isSameDay(now()));
    }

    #[Test]
    public function changed_countries_are_updated(): void
    {
        $this->fakeCountries($this->fixture('countries'));
        $this->artisan('country:sync')->assertSuccessful();

        $countries = $this->fixture('countries');
        $countries[1]['capitals'] = [['name' => 'Lyon', 'attributes' => ['primary' => true]]];
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        $this->fakeCountries($countries);

        $this->artisan('country:sync', ['-v' => true])
            ->expectsTable(['Created', 'Updated', 'Unchanged', 'Deleted'], [[0, 1, 5, 0]])
            ->assertSuccessful();

        $this->assertSame('Lyon', Country::findByCode('FR')->capital);
    }

    #[Test]
    public function prune_deletes_countries_missing_from_the_api_and_is_off_by_default(): void
    {
        Country::factory()->create(['alpha2' => 'YU', 'alpha3' => 'YUG', 'name' => 'Yugoslavia']);
        $this->fakeCountries($this->fixture('countries'));

        $this->artisan('country:sync')->assertSuccessful();
        $this->assertNotNull(Country::findByCode('YU'));

        $this->artisan('country:sync', ['--prune' => true])
            ->expectsTable(['Created', 'Updated', 'Unchanged', 'Deleted'], [[0, 0, 6, 1]])
            ->assertSuccessful();

        $this->assertNull(Country::findByCode('YU'));
        $this->assertSame(6, Country::count());
    }

    #[Test]
    public function prune_can_be_enabled_from_the_config(): void
    {
        config(['country.sync.prune' => true]);
        Country::factory()->create(['alpha2' => 'YU', 'alpha3' => 'YUG', 'name' => 'Yugoslavia']);
        $this->fakeCountries($this->fixture('countries'));

        $this->artisan('country:sync')->assertSuccessful();

        $this->assertNull(Country::findByCode('YU'));
    }

    #[Test]
    public function dry_run_writes_nothing(): void
    {
        Event::fake();
        $this->fakeCountries($this->fixture('countries'));

        $this->artisan('country:sync', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->expectsTable(['Created', 'Updated', 'Unchanged', 'Deleted'], [[6, 0, 0, 0]])
            ->assertSuccessful();

        $this->assertSame(0, Country::count());
        Event::assertNotDispatched(CountriesSynced::class);
    }

    #[Test]
    public function specific_codes_can_be_synced(): void
    {
        $countries = $this->fixture('countries');
        Http::fake([
            self::API.'/codes.alpha_2/FR*' => Http::response($this->page([$countries[1]], 1)),
            self::API.'/codes.alpha_3/JPN*' => Http::response($this->page([$countries[3]], 1)),
        ]);
        Country::factory()->create(['alpha2' => 'YU', 'alpha3' => 'YUG', 'name' => 'Yugoslavia']);

        $this->artisan('country:sync', ['--code' => ['fr,JPN'], '--prune' => true])
            ->expectsOutputToContain('--prune is ignored')
            ->expectsTable(['Created', 'Updated', 'Unchanged', 'Deleted'], [[2, 0, 0, 0]])
            ->assertSuccessful();

        $this->assertEqualsCanonicalizing(['FR', 'JP', 'YU'], Country::pluck('alpha2')->all());
    }

    #[Test]
    public function unknown_codes_make_the_command_fail(): void
    {
        Http::fake([self::API.'/*' => Http::response(['errors' => [['message' => 'Not found']]], 404)]);

        $this->artisan('country:sync', ['--code' => ['ZZ']])
            ->expectsOutputToContain('Not found in the API: ZZ')
            ->assertFailed();
    }

    #[Test]
    public function api_errors_are_reported_and_leave_the_database_untouched(): void
    {
        Country::factory()->count(2)->create();
        Http::fake([self::API.'*' => Http::response(['errors' => [['message' => 'Invalid API key.']]], 401)]);

        $this->artisan('country:sync', ['--prune' => true])
            ->expectsOutputToContain('Invalid API key.')
            ->assertFailed();

        $this->assertSame(2, Country::count());
    }

    #[Test]
    public function an_empty_api_answer_never_prunes_the_table(): void
    {
        Country::factory()->count(2)->create();
        $this->fakeCountries([]);

        $this->artisan('country:sync', ['--prune' => true])->assertFailed();

        $this->assertSame(2, Country::count());
    }

    #[Test]
    public function the_synchronizer_dispatches_an_event(): void
    {
        Event::fake([CountriesSynced::class]);
        $this->fakeCountries($this->fixture('countries'));

        $result = $this->app->make(CountrySynchronizer::class)->sync();

        $this->assertSame(['created' => 6, 'updated' => 0, 'unchanged' => 0, 'deleted' => 0, 'missing' => 0], $result->counts());
        Event::assertDispatched(CountriesSynced::class, fn ($e) => $e->result === $result);
    }

    #[Test]
    public function the_seeder_fills_the_table(): void
    {
        $this->fakeCountries($this->fixture('countries'));

        $this->seed(CountrySeeder::class);

        $this->assertSame(6, Country::count());
    }
}
