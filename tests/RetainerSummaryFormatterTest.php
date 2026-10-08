<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Tests;

use KimaiPlugin\HappinessBundle\Model\RetainerMonth;
use KimaiPlugin\HappinessBundle\Service\RetainerBalanceCalculator;
use KimaiPlugin\HappinessBundle\Service\RetainerSummaryFormatter;
use PHPUnit\Framework\TestCase;

class RetainerSummaryFormatterTest extends TestCase
{
    /**
     * The example from the ticket: 8 hours per month, the balance carried in to October set to -5,5, 6,5 hours logged.
     *
     * @return list<RetainerMonth>
     */
    private function october(): array
    {
        return (new RetainerBalanceCalculator())->calculate('2026-10', '2026-10', 8.0, ['2026-10' => (int) (6.5 * 3600)], ['2026-10' => -5.5]);
    }

    public function testDefaultTextMatchesTheExampleFromTheTicket(): void
    {
        self::assertSame([
            'Retainer för oktober: 8 tim enligt överenskommelse.',
            'Timsaldo från förra månaden: -5,5 tim',
            'Nedlagd tid för månaden: 6,5 tim',
            'Timsaldo efter nedlagd tid: -4 tim',
            '',
            'Se bifogad tidsrapport',
        ], (new RetainerSummaryFormatter())->lines($this->october(), '2026-10'));
    }

    public function testOpeningIsTheCalculatedBalanceWithoutOverride(): void
    {
        $months = (new RetainerBalanceCalculator())->calculate('2026-09', '2026-10', 40.0, ['2026-10' => 35 * 3600], ['2026-09' => 62.0]);

        $lines = (new RetainerSummaryFormatter())->lines($months, '2026-10');

        self::assertSame('Timsaldo från förra månaden: 102 tim', $lines[1]);
        self::assertSame('Timsaldo efter nedlagd tid: 107 tim', $lines[3]);
    }

    public function testCustomTextWithTokens(): void
    {
        $lines = (new RetainerSummaryFormatter())->lines(
            $this->october(),
            '2026-10',
            "RETAINER - arbete med webbplatsen. {hours} timmar/mån.\r\nNedlagt: {logged}, ingående {opening}, ändring {adjustment}, saldo {closing} ({month})",
        );

        self::assertSame([
            'RETAINER - arbete med webbplatsen. 8 timmar/mån.',
            'Nedlagt: 6,5, ingående -5,5, ändring -5,5, saldo -4 (oktober)',
        ], $lines);
    }

    public function testAllMonthNames(): void
    {
        $names = [];
        for ($i = 1; $i <= 12; ++$i) {
            $month = \sprintf('2027-%02d', $i);
            $months = (new RetainerBalanceCalculator())->calculate($month, $month, 1.0, [], []);
            $names[] = (new RetainerSummaryFormatter())->lines($months, $month, '{month}')[0];
        }

        self::assertSame(['januari', 'februari', 'mars', 'april', 'maj', 'juni', 'juli', 'augusti', 'september', 'oktober', 'november', 'december'], $names);
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
        self::assertSame([], $formatter->lines($this->october(), '2026-08'));
    }
}
