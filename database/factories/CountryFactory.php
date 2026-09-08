<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    public function definition(): array
    {
        $code = fake()->unique()->countryCode();

        return [
            'code' => $code,
            'name_hu' => fake()->country(),
            'name_en' => fake()->country(),
            'flag_emoji' => null,
            'active' => true,
        ];
    }
}
