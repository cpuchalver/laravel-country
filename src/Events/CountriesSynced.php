<?php

namespace Cpuchalver\LaravelCountry\Events;

use Cpuchalver\LaravelCountry\Sync\SyncResult;

/**
 * Fired after a (non dry-run) synchronization has been written to the database.
 */
class CountriesSynced
{
    public function __construct(public readonly SyncResult $result) {}
}
