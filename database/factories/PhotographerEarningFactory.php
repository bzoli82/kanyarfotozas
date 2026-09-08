<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\Order;
use App\Models\PhotographerEarning;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<PhotographerEarning>
 */
class PhotographerEarningFactory extends Factory
{
    protected $model = PhotographerEarning::class;

    public function definition(): array
    {
        $photographer = User::factory()->state(['role' => User::ROLE_PHOTOGRAPHER, 'revenue_share_percent' => 70]);
        $gross = fake()->numberBetween(990, 5000);

        return [
            'photographer_id' => $photographer,
            'order_id' => Order::factory()->paid(),
            'media_id' => fn (array $attrs) => Media::factory()->create(['photographer_id' => $attrs['photographer_id']])->id,
            'order_media_id' => function (array $attrs) use ($gross) {
                return DB::table('order_media')->insertGetId([
                    'order_id' => $attrs['order_id'],
                    'media_id' => $attrs['media_id'],
                    'price_cents' => $gross,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            },
            'gross_cents' => $gross,
            'share_percent' => 70,
            'amount_cents' => (int) round($gross * 70 / 100),
            'status' => PhotographerEarning::STATUS_PENDING,
            'earned_at' => now(),
        ];
    }
}
