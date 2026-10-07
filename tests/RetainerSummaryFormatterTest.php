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
use KimaiPlugin\HappinessBundle\Service\RetainerSummaryFormatter;
use PHPUnit\Framework\TestCase;

class RetainerSummaryFormatterTest extends TestCase
{
    /**
     * 102 hours saved from before, 40 per month, 35 logged in October: 107 are saved (the example in the ticket).
     */
    private function months(): array
    {
        return (new RetainerBalanceCalculator())->calculate('2026-09', '2026-10', 40.0, ['2026-10' => 35 * 3600], ['2026-09' => 102.0 - 40.0]);
    }

    public function testDefaultTextMatchesTheTicketExample(): void
    {
        self::assertSame([
            'RETAINER - 40 timmar/mån enligt avtal.',
            '35 tim nedlagt',
            'Ni hade 102 tim sparat från förra månaden, det innebär att 107 tim sparas till nästkommande månaders retainers',
        ], (new RetainerSummaryFormatter())->lines($this->months(), '2026-10'));
    }

    public function testCustomTextWithTokens(): void
    {
        $lines = (new RetainerSummaryFormatter())->lines(
            $this->months(),
            '2026-10',
            "RETAINER - arbete med webbplatsen. {hours} timmar/mån.\r\nNedlagt: {logged}, ingående {opening}, justering {adjustment}, saldo {closing} ({month})",
        );

        self::assertSame([
            'RETAINER - arbete med webbplatsen. 40 timmar/mån.',
            'Nedlagt: 35, ingående 102, justering 0, saldo 107 (2026-10)',
        ], $lines);
    }

    public function testDecimalsUseCommaAndDeficitKeepsItsSign(): void
    {
        $months = (new RetainerBalanceCalculator())->calculate('2026-10', '2026-10', 10.0, ['2026-10' => (int) (12.5 * 3600)], []);

        self::assertSame(['12,5 / -2,5'], (new RetainerSummaryFormatter())->lines($months, '2026-10', '{logged} / {closing}'));
    }

    public function testNoLinesWithoutBalanceForTheMonth(): void
    {
        $formatter = new RetainerSummaryFormatter();

        self::assertSame([], $formatter->lines([], '2026-10'));
        // the reported month is before the start month, so the last calculated month is a different one
        self::assertSame([], $formatter->lines($this->months(), '2026-08'));
    }
}
