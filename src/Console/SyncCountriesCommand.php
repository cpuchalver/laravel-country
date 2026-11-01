<?php

namespace Cpuchalver\LaravelCountry\Console;

use Cpuchalver\LaravelCountry\Exceptions\CountryApiException;
use Cpuchalver\LaravelCountry\Sync\CountrySynchronizer;
use Illuminate\Console\Command;

class SyncCountriesCommand extends Command
{
    protected $signature = 'country:sync
        {--code=* : Only sync these ISO alpha-2/alpha-3 codes (repeatable, or comma-separated)}
        {--prune : Delete local countries that no longer exist in the API (full sync only)}
        {--dry-run : Show what would change without writing to the database}';

    protected $description = 'Synchronize countries from the REST Countries API';

    public function handle(CountrySynchronizer $synchronizer): int
    {
        $codes = collect($this->option('code'))
            ->flatMap(fn ($value) => explode(',', (string) $value))
            ->map(fn ($code) => strtoupper(trim($code)))
            ->filter()
            ->values()
            ->all();

        $dryRun = (bool) $this->option('dry-run');

        if ($codes !== [] && $this->option('prune')) {
            $this->components->warn('--prune is ignored when syncing specific codes.');
        }

        try {
            $result = $synchronizer->sync(
                codes: $codes ?: null,
                prune: $this->option('prune') ? true : null,
                dryRun: $dryRun,
            );
        } catch (CountryApiException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->components->info('Dry run: nothing was written to the database.');
        }

        $this->table(
            ['Created', 'Updated', 'Unchanged', 'Deleted'],
            [[count($result->created), count($result->updated), count($result->unchanged), count($result->deleted)]],
        );

        if ($this->output->isVerbose()) {
            foreach (['created', 'updated', 'deleted'] as $type) {
                if ($result->{$type} !== []) {
                    $this->components->twoColumnDetail(ucfirst($type), implode(', ', $result->{$type}));
                }
            }
        }

        if ($result->missing !== []) {
            $this->components->warn('Not found in the API: '.implode(', ', $result->missing));

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            '%d countr%s %s.',
            $result->total(),
            $result->total() === 1 ? 'y' : 'ies',
            $dryRun ? 'checked' : 'synchronized',
        ));

        return self::SUCCESS;
    }
}
