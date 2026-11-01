<?php

namespace Cpuchalver\LaravelCountry\Database\Seeders;

use Cpuchalver\LaravelCountry\Sync\CountrySynchronizer;
use Illuminate\Database\Seeder;

/**
 * Seeds the countries table from the REST Countries API.
 *
 * php artisan db:seed --class="Cpuchalver\LaravelCountry\Database\Seeders\CountrySeeder"
 * or $this->call(CountrySeeder::class) from your DatabaseSeeder.
 */
class CountrySeeder extends Seeder
{
    public function run(CountrySynchronizer $synchronizer): void
    {
        $result = $synchronizer->sync();

        $this->command?->getOutput()?->writeln(sprintf(
            '  Countries: %d created, %d updated, %d unchanged.',
            count($result->created),
            count($result->updated),
            count($result->unchanged),
        ));
    }
}
