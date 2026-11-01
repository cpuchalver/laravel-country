<?php

namespace Cpuchalver\LaravelCountry\Data;

use Illuminate\Support\Arr;

/**
 * Normalized country record built from a REST Countries v5 object.
 */
final readonly class CountryData
{
    public function __construct(
        public string $alpha2,
        public ?string $alpha3,
        public ?string $numericCode,
        public string $name,
        public ?string $region,
        public ?string $capital,
        public ?string $callingCode,
        public ?string $flagEmoji,
        public ?string $flagPng,
        public ?string $flagSvg,
    ) {}

    /**
     * The v5 properties the package needs (sent as `response_fields`).
     *
     * @var list<string>
     */
    public const API_FIELDS = [
        'names.common',
        'codes.alpha_2',
        'codes.alpha_3',
        'codes.ccn3',
        'region',
        'capitals',
        'calling_codes',
        'flag.emoji',
        'flag.url_png',
        'flag.url_svg',
    ];

    /**
     * Map an API object. Returns null when the record lacks an alpha-2 code
     * or a name, since those are required to store it.
     *
     * @param  array<string, mixed>  $country
     */
    public static function fromApi(array $country): ?self
    {
        $alpha2 = self::code(Arr::get($country, 'codes.alpha_2'), 2);
        $name = self::string(Arr::get($country, 'names.common'));

        if ($alpha2 === null || $name === null) {
            return null;
        }

        return new self(
            alpha2: $alpha2,
            alpha3: self::code(Arr::get($country, 'codes.alpha_3'), 3),
            numericCode: self::numeric(Arr::get($country, 'codes.ccn3')),
            name: $name,
            region: self::string(Arr::get($country, 'region')),
            capital: self::capital(Arr::get($country, 'capitals')),
            callingCode: self::callingCode(Arr::get($country, 'calling_codes')),
            flagEmoji: self::string(Arr::get($country, 'flag.emoji')),
            flagPng: self::string(Arr::get($country, 'flag.url_png')),
            flagSvg: self::string(Arr::get($country, 'flag.url_svg')),
        );
    }

    /**
     * Attributes for the Country model.
     *
     * @return array<string, string|null>
     */
    public function toAttributes(): array
    {
        return [
            'alpha2' => $this->alpha2,
            'alpha3' => $this->alpha3,
            'numeric_code' => $this->numericCode,
            'name' => $this->name,
            'region' => $this->region,
            'capital' => $this->capital,
            'calling_code' => $this->callingCode,
            'flag_emoji' => $this->flagEmoji,
            'flag_png' => $this->flagPng,
            'flag_svg' => $this->flagSvg,
        ];
    }

    private static function string(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function code(mixed $value, int $length): ?string
    {
        $value = self::string($value);

        return $value !== null && preg_match('/^[A-Za-z]{'.$length.'}$/', $value)
            ? strtoupper($value)
            : null;
    }

    private static function numeric(mixed $value): ?string
    {
        $value = self::string($value);

        return $value !== null && ctype_digit($value) && strlen($value) <= 3
            ? str_pad($value, 3, '0', STR_PAD_LEFT)
            : null;
    }

    /**
     * Capitals are objects ({name, coordinates, attributes: {primary, ...}}).
     * Prefer the primary capital, fall back to the first one. Plain strings
     * are accepted too.
     */
    private static function capital(mixed $capitals): ?string
    {
        if (! is_array($capitals) || $capitals === []) {
            return self::string($capitals);
        }

        $primary = Arr::first(
            $capitals,
            fn ($capital) => is_array($capital) && Arr::get($capital, 'attributes.primary') === true,
        );

        $capital = $primary ?? Arr::first($capitals);

        return self::string(is_array($capital) ? ($capital['name'] ?? null) : $capital);
    }

    private static function callingCode(mixed $codes): ?string
    {
        $code = is_array($codes) ? Arr::first($codes) : $codes;
        $code = self::string($code);

        if ($code === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $code);

        return $digits === '' ? null : substr($digits, 0, 16);
    }
}
