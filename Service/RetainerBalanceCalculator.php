<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Service;

use KimaiPlugin\HappinessBundle\Model\RetainerMonth;

/**
 * Pure calculation of the month by month retainer balance, nothing is stored.
 */
final class RetainerBalanceCalculator
{
    /**
     * @param string $start first month (YYYY-MM)
     * @param string $until last month (YYYY-MM), inclusive
     * @param array<string, int> $loggedSeconds logged seconds indexed by month
     * @param array<string, float> $overrides balance carried in to a month that replaces the calculated one, indexed by month
     *
     * @return list<RetainerMonth> ordered from the start month to the last month
     */
    public function calculate(string $start, string $until, float $retainerHours, array $loggedSeconds, array $overrides): array
    {
        $months = [];
        $carried = 0.0;

        for ($month = $start; $month <= $until; $month = $this->next($month)) {
            $item = new RetainerMonth(
                $month,
                $carried,
                $retainerHours,
                ($loggedSeconds[$month] ?? 0) / 3600,
                $overrides[$month] ?? null,
            );
            $months[] = $item;
            $carried = $item->getClosing();
        }

        return $months;
    }

    private function next(string $month): string
    {
        return (new \DateTimeImmutable($month . '-01'))->modify('+1 month')->format('Y-m');
    }
}
