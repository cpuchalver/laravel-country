<?php

namespace Cpuchalver\LaravelCountry\Models;

use Cpuchalver\LaravelCountry\Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $alpha2
 * @property string|null $alpha3
 * @property string|null $numeric_code
 * @property string $name
 * @property string|null $region
 * @property string|null $capital
 * @property string|null $calling_code
 * @property string|null $flag_emoji
 * @property string|null $flag_png
 * @property string|null $flag_svg
 * @property-read string|null $dialing_code
 * @property \Illuminate\Support\Carbon|null $synced_at
 */
class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
    use HasFactory;

    protected $fillable = [
        'alpha2',
        'alpha3',
        'numeric_code',
        'name',
        'region',
        'capital',
        'calling_code',
        'flag_emoji',
        'flag_png',
        'flag_svg',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return $this->table ?? config('country.table', 'countries');
    }

    /**
     * Find a country by its ISO alpha-2 or alpha-3 code (case-insensitive).
     */
    public static function findByCode(string $code): ?static
    {
        return static::query()->code($code)->first();
    }

    public function scopeCode(Builder $query, string $code): void
    {
        $code = strtoupper(trim($code));

        $query->where(strlen($code) === 3 ? 'alpha3' : 'alpha2', $code);
    }

    public function scopeRegion(Builder $query, string $region): void
    {
        $query->where('region', $region);
    }

    /**
     * Dialing code formatted with a leading "+", e.g. "+33".
     */
    protected function dialingCode(): Attribute
    {
        return Attribute::get(
            fn () => $this->calling_code ? '+'.$this->calling_code : null,
        );
    }

    protected static function newFactory(): CountryFactory
    {
        return CountryFactory::new();
    }
}
