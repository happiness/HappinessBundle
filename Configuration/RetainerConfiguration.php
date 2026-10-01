<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Configuration;

/**
 * Typed access to the monthly retainer settings (defaults registered in HappinessExtension).
 */
final class RetainerConfiguration
{
    public const META_FIELD = 'happiness_monthly_planning';

    public const DEFAULTS = [
        'enabled' => true,
        'day_of_month' => 1,
        'hours' => 1.0,
        'description' => 'Projektledning: Planeringsarbete för månaden, kontroll av Security status, genomgång av webbplatsens hälsa',
        'user' => 'happiness',
        'activity_id' => 10,
    ];

    public const MAX_DAY = 28;

    public function __construct(private readonly \App\Configuration\SystemConfiguration $configuration)
    {
    }

    public function isEnabled(): bool
    {
        return (bool) $this->get('enabled');
    }

    /**
     * Capped at 28, so that the day exists in every month.
     */
    public function getDayOfMonth(): int
    {
        return max(1, min(self::MAX_DAY, (int) $this->get('day_of_month')));
    }

    public function getHours(): float
    {
        return (float) $this->get('hours');
    }

    public function getDescription(): string
    {
        return (string) $this->get('description');
    }

    public function getUsername(): string
    {
        return trim((string) $this->get('user'));
    }

    /**
     * The ID of the activity to book on, 0 if not configured.
     */
    public function getActivityId(): int
    {
        return max(0, (int) $this->get('activity_id'));
    }

    private function get(string $key): string|int|bool|float|null
    {
        $value = $this->configuration->find('happiness.retainer.' . $key);

        return $value ?? self::DEFAULTS[$key];
    }
}
