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
        self::assertSame(0.0, $months[0]->getOpening());
        self::assertSame(5.0, $months[0]->getClosing());
        self::assertSame(5.0, $months[1]->getOpening());
        self::assertSame(0.0, $months[1]->getClosing());
        self::assertSame(0.0, $months[2]->getOpening());
        self::assertSame(10.0, $months[2]->getClosing());
    }

    public function testOverageIsDeductedFromTheBalance(): void
    {
        // balance 5, retainer 5 per month, 12 hours logged: 7 over, 5 - 7 = -2
        $months = (new RetainerBalanceCalculator())->calculate('2026-10', '2026-11', 5.0, ['2026-11' => 12 * 3600], ['2026-11' => 5.0]);

        self::assertSame(5.0, $months[1]->getOpening());
        self::assertSame(-2.0, $months[1]->getClosing());
    }

    public function testOverrideReplacesTheCalculatedBalanceAndIsCarriedOn(): void
    {
        // October 8 - 6.5 = 1.5, but the balance carried in is set to -5.5
        $months = (new RetainerBalanceCalculator())->calculate('2026-09', '2026-11', 8.0, ['2026-09' => 6 * 3600, '2026-10' => (int) (6.5 * 3600)], ['2026-10' => -5.5]);

        self::assertSame(2.0, $months[0]->getClosing());
        self::assertSame(2.0, $months[1]->carried);
        self::assertSame(-5.5, $months[1]->getOpening());
        self::assertSame(-7.5, $months[1]->getAdjustment());
        self::assertSame(-4.0, $months[1]->getClosing());
        // the override is not added again, the next month continues from the closing balance
        self::assertSame(-4.0, $months[2]->getOpening());
        self::assertSame(4.0, $months[2]->getClosing());
    }

    public function testOverrideOfZeroIsAValue(): void
    {
        $months = (new RetainerBalanceCalculator())->calculate('2026-10', '2026-11', 10.0, ['2026-10' => 30 * 3600], ['2026-11' => 0.0]);

        self::assertSame(-20.0, $months[0]->getClosing());
        self::assertSame(0.0, $months[1]->override);
        self::assertSame(0.0, $months[1]->getOpening());
        self::assertSame(10.0, $months[1]->getClosing());
    }

    public function testNegativeRetainerHours(): void
    {
        $months = (new RetainerBalanceCalculator())->calculate('2026-10', '2026-11', -2.0, [], []);

        self::assertSame(-2.0, $months[0]->getClosing());
        self::assertSame(-4.0, $months[1]->getClosing());
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
