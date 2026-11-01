<?php

namespace Cpuchalver\LaravelCountry\Contracts;

use Cpuchalver\LaravelCountry\Data\CountryData;
use Cpuchalver\LaravelCountry\Exceptions\CountryApiException;

/**
 * Source of country data. Bind your own implementation to use another API.
 */
interface CountryProvider
{
    /**
     * Every country available from the source, keyed by alpha-2 code.
     *
     * @return array<string, CountryData>
     *
     * @throws CountryApiException
     */
    public function all(): array;

    /**
     * A single country by ISO alpha-2 or alpha-3 code.
     *
     * @throws CountryApiException
     */
    public function find(string $code): ?CountryData;

    /**
     * Total number of countries reported by the source.
     *
     * @throws CountryApiException
     */
    public function count(): int;
}
