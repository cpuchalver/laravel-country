<?php

namespace Cpuchalver\LaravelCountry\Tests\Feature;

use Cpuchalver\LaravelCountry\Contracts\CountryProvider;
use Cpuchalver\LaravelCountry\Exceptions\CountryApiException;
use Cpuchalver\LaravelCountry\Http\RestCountriesClient;
use Cpuchalver\LaravelCountry\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class RestCountriesClientTest extends TestCase
{
    #[Test]
    public function the_provider_is_bound_to_the_rest_countries_client(): void
    {
        $this->assertInstanceOf(RestCountriesClient::class, $this->app->make(CountryProvider::class));
    }

    #[Test]
    public function it_walks_every_page_with_the_bearer_token_and_field_list(): void
    {
        $this->fakeCountries($this->fixture('countries'), limit: 3);

        $countries = $this->app->make(CountryProvider::class)->all();

        // 7 objects, one without alpha-2 is skipped.
        $this->assertSame(['BR', 'FR', 'DE', 'JP', 'ZA', 'AQ'], array_keys($countries));

        Http::assertSentCount(3);
        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'Bearer test-key')
                && parse_url($request->url(), PHP_URL_PATH) === '/countries/v5'
                && (int) $request['limit'] === 3
                && (int) $request['offset'] === 6
                && str_contains($request['response_fields'], 'codes.alpha_2')
                && str_contains($request['response_fields'], 'calling_codes');
        });
    }

    #[Test]
    public function it_finds_a_country_by_alpha2_or_alpha3_code(): void
    {
        Http::fake([
            self::API.'/codes.alpha_2/FR*' => Http::response($this->page([$this->fixture('countries')[1]], 1)),
            self::API.'/codes.alpha_3/JPN*' => Http::response($this->page([$this->fixture('countries')[3]], 1)),
            self::API.'/codes.alpha_2/XX*' => Http::response(['errors' => [['message' => 'Not found']]], 404),
        ]);

        $client = $this->app->make(CountryProvider::class);

        $this->assertSame('Paris', $client->find('fr')->capital);
        $this->assertSame('JP', $client->find('JPN')->alpha2);
        $this->assertNull($client->find('XX'));
        $this->assertNull($client->find('not-a-code'));
    }

    #[Test]
    public function it_returns_the_total_reported_by_the_api(): void
    {
        Http::fake([self::API.'*' => Http::response($this->page([['codes' => ['alpha_2' => 'AF']]], 250))]);

        $this->assertSame(250, $this->app->make(CountryProvider::class)->count());
    }

    #[Test]
    public function it_requires_an_api_key(): void
    {
        config(['country.api.key' => null]);

        $this->expectException(CountryApiException::class);
        $this->expectExceptionMessage('COUNTRY_API_KEY');

        $this->app->make(CountryProvider::class)->all();
    }

    #[Test]
    public function it_surfaces_the_api_error_message(): void
    {
        Http::fake([self::API.'*' => Http::response(['errors' => [['message' => 'Invalid API key.']]], 401)]);

        try {
            $this->app->make(CountryProvider::class)->all();
            $this->fail('Expected exception');
        } catch (CountryApiException $e) {
            $this->assertSame(401, $e->getCode());
            $this->assertStringContainsString('Invalid API key.', $e->getMessage());
        }

        Http::assertSentCount(1); // 401 is not retried
    }

    #[Test]
    public function it_retries_rate_limited_and_server_errors(): void
    {
        Http::fakeSequence(self::API.'*')
            ->push(['errors' => [['message' => 'Too many requests']]], 429)
            ->push(['errors' => [['message' => 'Oops']]], 503)
            ->push($this->page([$this->fixture('countries')[1]], 1));

        $countries = $this->app->make(CountryProvider::class)->all();

        $this->assertArrayHasKey('FR', $countries);
        Http::assertSentCount(3);
    }

    #[Test]
    public function it_rejects_an_unexpected_payload(): void
    {
        Http::fake([self::API.'*' => Http::response(['data' => ['nothing' => true]])]);

        $this->expectException(CountryApiException::class);
        $this->expectExceptionMessage('data.objects');

        $this->app->make(CountryProvider::class)->all();
    }
}
