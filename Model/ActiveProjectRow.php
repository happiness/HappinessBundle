<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Model;

use App\Entity\Project;
use App\Entity\User;

/**
 * One row of the active projects report. All durations are seconds.
 */
final readonly class ActiveProjectRow
{
    public const SECTION_ONGOING = 'ongoing';
    public const SECTION_FIXED = 'fixed';
    public const SECTION_RETAINER = 'retainer';

    /**
     * @param list<array{user: User, seconds: int}> $participants users with time on the project the last weeks, ordered by user name, with their logged seconds in the shown week
     */
    public function __construct(
        public Project $project,
        public string $section,
        public array $participants,
        /** Time budget, for a retainer the hours of the month, null without estimate */
        public ?int $estimate,
        /** Logged time, for a retainer the time of the month */
        public int $spent,
        /** Retainer only: the balance carried in from earlier months */
        public ?int $saved,
    ) {
    }

    /**
     * Logged seconds this week of all users.
     */
    public function getWeekTotal(): int
    {
        return array_sum(array_column($this->participants, 'seconds'));
    }

    public function getRemaining(): ?int
    {
        return $this->estimate === null ? null : $this->estimate - $this->spent;
    }

    /**
     * Time beyond the estimate of a fixed price project.
     */
    public function getOvertime(): int
    {
        return $this->section === self::SECTION_FIXED && $this->estimate !== null ? max(0, $this->spent - $this->estimate) : 0;
    }
}
