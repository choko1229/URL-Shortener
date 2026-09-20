<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Inquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inquiry>
 */
class InquiryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => fake()->name(),
            'reply_to' => fake()->safeEmail(),
            'message' => fake()->realText(120),
            'handled_at' => null,
        ];
    }

    public function handled(): static
    {
        return $this->state(fn (): array => ['handled_at' => now()]);
    }
}
