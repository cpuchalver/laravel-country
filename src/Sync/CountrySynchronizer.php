<?php

namespace Cpuchalver\LaravelCountry\Sync;

use Cpuchalver\LaravelCountry\Contracts\CountryProvider;
use Cpuchalver\LaravelCountry\Data\CountryData;
use Cpuchalver\LaravelCountry\Events\CountriesSynced;
use Cpuchalver\LaravelCountry\Exceptions\CountryApiException;
use Cpuchalver\LaravelCountry\Models\Country;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Writes countries from a CountryProvider into the database.
 */
class CountrySynchronizer
{
    /**
     * @param  class-string<Country>  $model
     */
    public function __construct(
        private readonly CountryProvider $provider,
        private readonly Dispatcher $events,
        private readonly string $model = Country::class,
        private readonly bool $pruneByDefault = false,
    ) {}

    /**
     * @param  list<string>|null  $codes  Only sync these alpha-2/alpha-3 codes (null = every country).
     * @param  bool|null  $prune  Delete local countries absent from the API (full sync only). Null = config default.
     * @param  bool  $dryRun  Compute the changes without writing anything.
     *
     * @throws CountryApiException
     */
    public function sync(?array $codes = null, ?bool $prune = null, bool $dryRun = false): SyncResult
    {
        $result = new SyncResult($dryRun);
        $partial = $codes !== null && $codes !== [];
        $prune = ! $partial && ($prune ?? $this->pruneByDefault);

        $countries = $partial ? $this->fetchCodes($codes, $result) : $this->provider->all();

        if (! $partial && $countries === []) {
            // Never treat an empty answer as "every country was removed".
            throw CountryApiException::invalidPayload('the API returned no countries.');
        }

        $query = $this->newModel()->newQuery();
        $existing = ($partial ? $query->whereIn('alpha2', array_keys($countries)) : $query)
            ->get()
            ->keyBy('alpha2');

        $write = function () use ($countries, $existing, $prune, $result, $dryRun) {
            $now = Carbon::now();

            foreach ($countries as $alpha2 => $country) {
                /** @var Model $record */
                $record = $existing->get($alpha2) ?? $this->newModel();
                $record->fill($country->toAttributes());

                if (! $record->exists) {
                    $result->created[] = $alpha2;
                } elseif ($record->isDirty()) {
                    $result->updated[] = $alpha2;
                } else {
                    $result->unchanged[] = $alpha2;

                    continue;
                }

                if (! $dryRun) {
                    $record->setAttribute('synced_at', $now)->save();
                }
            }

            if ($prune) {
                $result->deleted = $existing->keys()
                    ->diff(array_keys($countries))
                    ->values()
                    ->all();
            }

            if ($dryRun) {
                return;
            }

            if ($result->unchanged !== []) {
                // Mark as synced without bumping updated_at.
                $this->newModel()->newQuery()
                    ->whereIn('alpha2', $result->unchanged)
                    ->toBase()
                    ->update(['synced_at' => $now]);
            }

            foreach (array_chunk($result->deleted, 500) as $chunk) {
                $this->newModel()->newQuery()->whereIn('alpha2', $chunk)->delete();
            }
        };

        $dryRun ? $write() : $this->newModel()->getConnection()->transaction($write);

        if (! $dryRun) {
            $this->events->dispatch(new CountriesSynced($result));
        }

        return $result;
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, CountryData>
     */
    private function fetchCodes(array $codes, SyncResult $result): array
    {
        $countries = [];

        foreach (array_unique(array_map(fn ($c) => strtoupper(trim($c)), $codes)) as $code) {
            $country = $this->provider->find($code);

            if ($country === null) {
                $result->missing[] = $code;
            } else {
                $countries[$country->alpha2] = $country;
            }
        }

        return $countries;
    }

    private function newModel(): Model
    {
        return new ($this->model);
    }
}
