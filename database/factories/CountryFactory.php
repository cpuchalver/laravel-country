<?php

namespace Cpuchalver\LaravelCountry\Database\Factories;

use Cpuchalver\LaravelCountry\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    public function modelName(): string
    {
        return config('country.model', Country::class);
    }

    public function definition(): array
    {
        $alpha2 = $this->faker->unique()->lexify('??');

        return [
            'alpha2' => strtoupper($alpha2),
            'alpha3' => strtoupper($alpha2.Str::random(1)),
            'numeric_code' => $this->faker->numerify('###'),
            'name' => $this->faker->unique()->country(),
            'region' => $this->faker->randomElement(['Africa', 'Americas', 'Asia', 'Europe', 'Oceania']),
            'capital' => $this->faker->city(),
            'calling_code' => (string) $this->faker->numberBetween(1, 999),
            'flag_emoji' => null,
            'flag_png' => 'https://flags.restcountries.com/v5/w320/'.strtolower($alpha2).'.png',
            'flag_svg' => null,
            'synced_at' => now(),
        ];
    }
}
