<?php

namespace Cpuchalver\LaravelCountry;

use Cpuchalver\LaravelCountry\Console\CountryStatusCommand;
use Cpuchalver\LaravelCountry\Console\InstallCountryCommand;
use Cpuchalver\LaravelCountry\Console\ShowCountryCommand;
use Cpuchalver\LaravelCountry\Console\SyncCountriesCommand;
use Cpuchalver\LaravelCountry\Contracts\CountryProvider;
use Cpuchalver\LaravelCountry\Http\RestCountriesClient;
use Cpuchalver\LaravelCountry\Models\Country;
use Cpuchalver\LaravelCountry\Sync\CountrySynchronizer;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

class CountryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/country.php', 'country');

        $this->app->bind(CountryProvider::class, function (Application $app) {
            $config = $app['config']->get('country.api', []);

            return new RestCountriesClient(
                http: $app->make(HttpFactory::class),
                apiKey: $config['key'] ?? null,
                baseUrl: $config['base_url'] ?? 'https://api.restcountries.com/countries/v5',
                timeout: (int) ($config['timeout'] ?? 15),
                retryTimes: (int) ($config['retry']['times'] ?? 3),
                retrySleep: (int) ($config['retry']['sleep'] ?? 1000),
                perPage: (int) ($config['per_page'] ?? 100),
            );
        });

        $this->app->bind(CountrySynchronizer::class, fn (Application $app) => new CountrySynchronizer(
            provider: $app->make(CountryProvider::class),
            events: $app->make('events'),
            model: $app['config']->get('country.model', Country::class),
            pruneByDefault: (bool) $app['config']->get('country.sync.prune', false),
        ));
    }

    public function boot(): void
    {
        if (config('country.load_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/country.php' => config_path('country.php'),
        ], 'country-config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'country-migrations');

        $this->commands([
            SyncCountriesCommand::class,
            CountryStatusCommand::class,
            ShowCountryCommand::class,
            InstallCountryCommand::class,
        ]);
    }
}
