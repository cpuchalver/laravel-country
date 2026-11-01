<?php

namespace Cpuchalver\LaravelCountry\Console;

use Cpuchalver\LaravelCountry\Contracts\CountryProvider;
use Cpuchalver\LaravelCountry\Exceptions\CountryApiException;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class CountryStatusCommand extends Command
{
    protected $signature = 'country:status
        {--remote : Also query the API and compare its country count with the database}';

    protected $description = 'Show the configuration and synchronization state of the countries table';

    public function handle(CountryProvider $provider): int
    {
        /** @var Model $model */
        $model = new (config('country.model'));
        $table = $model->getTable();
        $tableExists = Schema::connection($model->getConnectionName())->hasTable($table);

        $this->components->twoColumnDetail('API URL', (string) config('country.api.base_url'));
        $this->components->twoColumnDetail(
            'API key',
            filled(config('country.api.key')) ? '<fg=green>configured</>' : '<fg=red>missing (COUNTRY_API_KEY)</>',
        );
        $this->components->twoColumnDetail('Table', $table.($tableExists ? '' : ' <fg=red>(not migrated)</>'));

        $local = null;

        if ($tableExists) {
            $local = $model->newQuery()->count();
            $lastSync = $model->newQuery()->max('synced_at');

            $this->components->twoColumnDetail('Countries in database', (string) $local);
            $this->components->twoColumnDetail('Last synchronization', $lastSync ? (string) $lastSync : 'never');
        }

        if (! $this->option('remote')) {
            return self::SUCCESS;
        }

        try {
            $remote = $provider->count();
        } catch (CountryApiException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Countries in API', (string) $remote);

        if ($local !== null && $local !== $remote) {
            $this->components->warn('The database is out of sync. Run `php artisan country:sync`.');
        } elseif ($local !== null) {
            $this->components->info('The database has as many countries as the API.');
        }

        return self::SUCCESS;
    }
}
