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
        /** Balance calculated from the previous month, negative is a deficit */
        public float $carried,
        /** Hours included in the retainer agreement for the month */
        public float $retainer,
        public float $logged,
        /** Manual replacement of the balance carried in from the previous month, null if there is none */
        public ?float $override = null,
    ) {
    }

    /**
     * The balance carried in to the month: the manual override if there is one.
     */
    public function getOpening(): float
    {
        return $this->override ?? $this->carried;
    }

    /**
     * How much the override changed the carried in balance, zero without override.
     */
    public function getAdjustment(): float
    {
        return $this->getOpening() - $this->carried;
    }

    public function getClosing(): float
    {
        return $this->getOpening() + $this->retainer - $this->logged;
    }
}
