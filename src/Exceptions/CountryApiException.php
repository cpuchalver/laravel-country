<?php

namespace Cpuchalver\LaravelCountry\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

class CountryApiException extends RuntimeException
{
    public static function missingApiKey(): self
    {
        return new self(
            'No REST Countries API key configured. Set COUNTRY_API_KEY in your .env file '
            .'(get a key at https://restcountries.com/sign-up).'
        );
    }

    public static function fromResponse(Response $response): self
    {
        $message = $response->json('errors.0.message')
            ?? $response->json('message')
            ?? $response->reason();

        return new self(
            sprintf('REST Countries API error (HTTP %d): %s', $response->status(), $message),
            $response->status(),
        );
    }

    public static function connectionFailed(Throwable $previous): self
    {
        return new self('Could not reach the REST Countries API: '.$previous->getMessage(), 0, $previous);
    }

    public static function invalidPayload(string $detail): self
    {
        return new self('Unexpected REST Countries API response: '.$detail);
    }
}
