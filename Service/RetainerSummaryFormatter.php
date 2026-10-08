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
 * Available tokens in a project's own text: {hours}, {logged}, {opening}, {adjustment}, {closing} and {month}
 * ({month} is the Swedish name of the reported month, {adjustment} the change an override made to {opening}).
 */
final class RetainerSummaryFormatter
{
    public const DEFAULT_TEXT = "Retainer för {month}: {hours} tim enligt överenskommelse.\nTimsaldo från förra månaden: {opening} tim\nNedlagd tid för månaden: {logged} tim\nTimsaldo efter nedlagd tid: {closing} tim\n\nSe bifogad tidsrapport";

    private const MONTHS = ['januari', 'februari', 'mars', 'april', 'maj', 'juni', 'juli', 'augusti', 'september', 'oktober', 'november', 'december'];

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
            '{opening}' => $this->hours($item->getOpening()),
            '{adjustment}' => $this->hours($item->getAdjustment()),
            '{closing}' => $this->hours($item->getClosing()),
            '{month}' => $this->monthName($item->month),
        ]);

        return preg_split('/\R/', rtrim(trim($text, "\r\n"))) ?: [];
    }

    private function monthName(string $month): string
    {
        return self::MONTHS[(int) substr($month, 5, 2) - 1] ?? $month;
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
