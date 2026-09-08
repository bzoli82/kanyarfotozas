<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('KANYAR-####')),
            'discount_percent' => fake()->randomElement([10, 15, 20, 25]),
            'max_uses' => 100,
            'used_count' => 0,
            'expires_at' => now()->addMonths(3),
            'active' => true,
        ];
    }
}
