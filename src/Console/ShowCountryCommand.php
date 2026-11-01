<?php

namespace Cpuchalver\LaravelCountry\Console;

use Cpuchalver\LaravelCountry\Contracts\CountryProvider;
use Cpuchalver\LaravelCountry\Exceptions\CountryApiException;
use Illuminate\Console\Command;

class ShowCountryCommand extends Command
{
    protected $signature = 'country:show
        {code : ISO alpha-2 or alpha-3 code}
        {--remote : Read the country from the API instead of the database}';

    protected $description = 'Display a country from the database (or the API)';

    public function handle(CountryProvider $provider): int
    {
        $code = strtoupper(trim((string) $this->argument('code')));

        if ($this->option('remote')) {
            try {
                $attributes = $provider->find($code)?->toAttributes();
            } catch (CountryApiException $e) {
                $this->components->error($e->getMessage());

                return self::FAILURE;
            }
        } else {
            $attributes = config('country.model')::findByCode($code)
                ?->only(['alpha2', 'alpha3', 'numeric_code', 'name', 'region', 'capital', 'calling_code', 'flag_emoji', 'flag_png', 'flag_svg', 'synced_at']);
        }

        if ($attributes === null) {
            $this->components->error("Country [{$code}] not found".($this->option('remote') ? ' in the API.' : ' in the database.'));

            return self::FAILURE;
        }

        foreach ($attributes as $key => $value) {
            $this->components->twoColumnDetail($key, $value === null ? '<fg=gray>—</>' : (string) $value);
        }

        return self::SUCCESS;
    }
}
