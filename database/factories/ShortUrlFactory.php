<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LinkSource;
use App\Enums\SlugType;
use App\Models\ShortUrl;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShortUrl>
 */
class ShortUrlFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'domain' => '',
            'slug' => Str::random(7),
            'slug_type' => SlugType::Random,
            'source' => LinkSource::Page,
            'original_url' => fake()->url(),
            'expires_at' => null,
            'click_count' => fake()->numberBetween(0, 500),
        ];
    }

    public function guest(): static
    {
        return $this->state(fn (): array => [
            'user_id' => null,
            'expires_at' => now()->addDays(30),
            'creator_ip_hash' => hash('sha256', fake()->ipv4()),
            'deletion_token_hash' => hash('sha256', Str::random(40)),
        ]);
    }

    public function custom(string $slug): static
    {
        return $this->state(fn (): array => ['slug' => $slug, 'slug_type' => SlugType::Custom]);
    }

    public function onDomain(string $host): static
    {
        return $this->state(fn (): array => ['domain' => $host]);
    }

    public function viaApi(): static
    {
        return $this->state(fn (): array => ['source' => LinkSource::Api]);
    }

    public function expiresIn(int $days): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->addDays($days)]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subDay()]);
    }

    public function passwordProtected(): static
    {
        return $this->state(fn (): array => ['password_hash' => bcrypt('password')]);
    }
}
