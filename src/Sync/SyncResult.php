<?php

namespace Cpuchalver\LaravelCountry\Sync;

final class SyncResult
{
    /** @var list<string> */
    public array $created = [];

    /** @var list<string> */
    public array $updated = [];

    /** @var list<string> */
    public array $unchanged = [];

    /** @var list<string> */
    public array $deleted = [];

    /** @var list<string> Codes requested but not found in the API. */
    public array $missing = [];

    public function __construct(public readonly bool $dryRun = false) {}

    public function total(): int
    {
        return count($this->created) + count($this->updated) + count($this->unchanged);
    }

    public function hasChanges(): bool
    {
        return $this->created !== [] || $this->updated !== [] || $this->deleted !== [];
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return [
            'created' => count($this->created),
            'updated' => count($this->updated),
            'unchanged' => count($this->unchanged),
            'deleted' => count($this->deleted),
            'missing' => count($this->missing),
        ];
    }
}
