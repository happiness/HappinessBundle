<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Tests;

use KimaiPlugin\HappinessBundle\Service\RetainerBalanceCalculator;
use PHPUnit\Framework\TestCase;

class RetainerBalanceCalculatorTest extends TestCase
{
    public function testBalanceIsCarriedOverMonthByMonth(): void
    {
        $months = (new RetainerBalanceCalculator())->calculate(
            '2026-08',
            '2026-10',
            40.0,
            ['2026-08' => 35 * 3600, '2026-09' => 45 * 3600, '2026-10' => 30 * 3600],
            [],
        );

        self::assertCount(3, $months);
        self::assertSame('2026-08', $months[0]->month);
        self::assertSame(0.0, $months[0]->opening);
        self::assertSame(5.0, $months[0]->getClosing());
        self::assertSame(5.0, $months[1]->opening);
        self::assertSame(0.0, $months[1]->getClosing());
        self::assertSame(0.0, $months[2]->opening);
        self::assertSame(10.0, $months[2]->getClosing());
    }

    public function testMatchesTicketExample(): void
    {
        // 102 hours saved, 40 per month, 35 logged: 107 are saved
        $months = (new RetainerBalanceCalculator())->calculate('2026-09', '2026-10', 40.0, ['2026-10' => 35 * 3600], ['2026-09' => 102.0 - 40.0]);

        self::assertSame(102.0, $months[0]->getClosing());
        self::assertSame(107.0, $months[1]->getClosing());
    }

    public function testAdjustmentsAndDeficit(): void
    {
        $months = (new RetainerBalanceCalculator())->calculate('2026-11', '2027-01', 10.0, ['2026-11' => 50 * 3600], ['2026-12' => 30.0]);

        self::assertSame(-40.0, $months[0]->getClosing());
        self::assertSame(30.0, $months[1]->adjustment);
        self::assertSame(0.0, $months[1]->getClosing());
        self::assertSame(10.0, $months[2]->getClosing());
    }

    public function testRollsOverTheYear(): void
    {
        $months = (new RetainerBalanceCalculator())->calculate('2026-12', '2027-01', 1.0, [], []);

        self::assertSame(['2026-12', '2027-01'], array_map(static fn ($m) => $m->month, $months));
    }

    public function testStartAfterLastMonthGivesNoMonths(): void
    {
        self::assertSame([], (new RetainerBalanceCalculator())->calculate('2027-01', '2026-10', 1.0, [], []));
    }
}
