<?php

namespace Cpuchalver\LaravelCountry\Console;

use Illuminate\Console\Command;

class InstallCountryCommand extends Command
{
    protected $signature = 'country:install
        {--migrate : Run the migrations after publishing}
        {--sync : Synchronize the countries after migrating}
        {--force : Overwrite an already published config file}';

    protected $description = 'Publish the country config and optionally migrate and synchronize';

    public function handle(): int
    {
        $this->call('vendor:publish', array_filter([
            '--tag' => 'country-config',
            '--force' => (bool) $this->option('force'),
        ]));

        if ($this->option('migrate') || $this->option('sync')) {
            if ($this->call('migrate', ['--force' => true]) !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        if (blank(config('country.api.key'))) {
            $this->components->warn('Add COUNTRY_API_KEY=your-key to .env (get one at https://restcountries.com/sign-up).');

            if ($this->option('sync')) {
                return self::FAILURE;
            }
        }

        if ($this->option('sync')) {
            return $this->call('country:sync');
        }

        $this->components->info('Done. Next: `php artisan migrate` then `php artisan country:sync`.');

        return self::SUCCESS;
    }
}
