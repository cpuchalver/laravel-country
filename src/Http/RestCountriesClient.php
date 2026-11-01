<?php

namespace Cpuchalver\LaravelCountry\Http;

use Cpuchalver\LaravelCountry\Contracts\CountryProvider;
use Cpuchalver\LaravelCountry\Data\CountryData;
use Cpuchalver\LaravelCountry\Exceptions\CountryApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Client for the REST Countries API v5 (https://restcountries.com/docs/countries).
 */
class RestCountriesClient implements CountryProvider
{
    /** Hard stop against a misbehaving paginator. */
    private const MAX_PAGES = 100;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly ?string $apiKey,
        private readonly string $baseUrl = 'https://api.restcountries.com/countries/v5',
        private readonly int $timeout = 15,
        private readonly int $retryTimes = 3,
        private readonly int $retrySleep = 1000,
        private readonly int $perPage = 100,
    ) {}

    public function all(): array
    {
        $countries = [];
        $offset = 0;
        $limit = max(1, min($this->perPage, 500));

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $data = $this->get('', ['limit' => $limit, 'offset' => $offset]);
            $objects = $this->objects($data);

            foreach ($objects as $object) {
                if (is_array($object) && ($country = CountryData::fromApi($object)) !== null) {
                    $countries[$country->alpha2] = $country;
                }
            }

            $offset += count($objects);

            if ($objects === [] || ! ($data['meta']['more'] ?? false)) {
                break;
            }
        }

        return $countries;
    }

    public function find(string $code): ?CountryData
    {
        $code = strtoupper(trim($code));

        if (! preg_match('/^[A-Z]{2,3}$/', $code)) {
            return null;
        }

        $property = strlen($code) === 2 ? 'codes.alpha_2' : 'codes.alpha_3';
        $data = $this->get("/{$property}/{$code}", ['limit' => 1], allowNotFound: true);

        if ($data === null) {
            return null;
        }

        $object = $this->objects($data)[0] ?? null;

        return is_array($object) ? CountryData::fromApi($object) : null;
    }

    public function count(): int
    {
        $data = $this->get('', ['limit' => 1, 'response_fields' => 'codes.alpha_2']);

        return (int) ($data['meta']['total'] ?? count($this->objects($data)));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null The `data` block of the response.
     */
    private function get(string $path, array $query, bool $allowNotFound = false): ?array
    {
        $query += ['response_fields' => implode(',', CountryData::API_FIELDS)];

        try {
            $response = $this->request()->get(rtrim($this->baseUrl, '/').$path, $query);
        } catch (ConnectionException $e) {
            throw CountryApiException::connectionFailed($e);
        }

        if ($allowNotFound && $response->notFound()) {
            return null;
        }

        if ($response->failed()) {
            throw CountryApiException::fromResponse($response);
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            throw CountryApiException::invalidPayload('missing "data" key.');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<mixed>
     */
    private function objects(array $data): array
    {
        $objects = $data['objects'] ?? null;

        if (! is_array($objects)) {
            throw CountryApiException::invalidPayload('missing "data.objects" list.');
        }

        return array_values($objects);
    }

    private function request(): PendingRequest
    {
        if (blank($this->apiKey)) {
            throw CountryApiException::missingApiKey();
        }

        return $this->http
            ->withToken($this->apiKey)
            ->acceptJson()
            ->timeout($this->timeout)
            ->retry(
                max(1, $this->retryTimes),
                $this->retrySleep,
                fn (Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $this->isRetryable($e->response)),
                throw: false,
            );
    }

    private function isRetryable(Response $response): bool
    {
        return $response->status() === 429 || $response->serverError();
    }
}
