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
 * Builds the text about the retainer balance that is shown on the first row of the Fortnox time report.
 *
 * Available tokens in a project's own text: {hours}, {logged}, {opening}, {adjustment}, {closing} and {month}.
 */
final class RetainerSummaryFormatter
{
    public const DEFAULT_TEXT = "RETAINER - {hours} timmar/mån enligt avtal.\n{logged} tim nedlagt\nNi hade {opening} tim sparat från förra månaden, det innebär att {closing} tim sparas till nästkommande månaders retainers";

    /**
     * @param list<RetainerMonth> $months the balance up to and including the reported month
     *
     * @return list<string> the text lines, empty if there is no balance for the month
     */
    public function lines(array $months, string $month, string $text = ''): array
    {
        $item = $months === [] ? null : $months[array_key_last($months)];
        if ($item === null || $item->month !== $month) {
            return [];
        }

        $text = trim($text) === '' ? self::DEFAULT_TEXT : $text;
        $text = strtr($text, [
            '{hours}' => $this->hours($item->retainer),
            '{logged}' => $this->hours($item->logged),
            '{opening}' => $this->hours($item->opening),
            '{adjustment}' => $this->hours($item->adjustment),
            '{closing}' => $this->hours($item->getClosing()),
            '{month}' => $item->month,
        ]);

        return preg_split('/\R/', trim($text)) ?: [];
    }

    /**
     * Swedish number format without trailing zeros, e.g. 35 or 35,5.
     */
    private function hours(float $hours): string
    {
        $hours = round($hours, 2);
        if ($hours === 0.0) {
            return '0';
        }

        return rtrim(rtrim(number_format($hours, 2, ',', ''), '0'), ',');
    }
}
