<?php

namespace Cpuchalver\LaravelCountry\Tests;

use Cpuchalver\LaravelCountry\CountryServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected const API = 'https://api.restcountries.com/countries/v5';

    protected function getPackageProviders($app): array
    {
        return [CountryServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('country.api.key', 'test-key');
        $app['config']->set('country.api.retry.sleep', 0);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function fixture(string $name): array
    {
        return json_decode(file_get_contents(__DIR__."/Fixtures/{$name}.json"), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Build a v5 list response.
     *
     * @param  list<array<string, mixed>>  $objects
     */
    protected function page(array $objects, int $total, int $offset = 0, int $limit = 100): array
    {
        return ['data' => [
            'objects' => $objects,
            'meta' => [
                'total' => $total,
                'count' => count($objects),
                'limit' => $limit,
                'offset' => $offset,
                'more' => $offset + count($objects) < $total,
            ],
        ]];
    }

    /**
     * Fake the list endpoint with the given objects, split in pages of $limit.
     *
     * @param  list<array<string, mixed>>  $objects
     */
    protected function fakeCountries(array $objects, int $limit = 100): void
    {
        config(['country.api.per_page' => $limit]);

        Http::fake(function ($request) use ($objects, $limit) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            if (parse_url($request->url(), PHP_URL_PATH) !== '/countries/v5') {
                return Http::response(['errors' => [['message' => 'Not found']]], 404);
            }

            $offset = (int) ($query['offset'] ?? 0);
            $slice = array_slice($objects, $offset, (int) ($query['limit'] ?? 25));

            return Http::response($this->page($slice, count($objects), $offset, $limit));
        });
    }
}
