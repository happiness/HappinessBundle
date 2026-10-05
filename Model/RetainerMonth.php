<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Model;

/**
 * The hour balance of a retainer project for one month. All values are hours.
 */
final readonly class RetainerMonth
{
    public function __construct(
        /** Format YYYY-MM */
        public string $month,
        /** Balance carried in from the previous month, negative is a deficit */
        public float $opening,
        /** Hours included in the retainer agreement for the month */
        public float $retainer,
        public float $logged,
        /** Manual change by an admin */
        public float $adjustment,
    ) {
    }

    public function getClosing(): float
    {
        return $this->opening + $this->retainer - $this->logged + $this->adjustment;
    }
}
