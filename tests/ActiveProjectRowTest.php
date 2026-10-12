<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Tests;

use App\Entity\Project;
use KimaiPlugin\HappinessBundle\Model\ActiveProjectRow;
use PHPUnit\Framework\TestCase;

class ActiveProjectRowTest extends TestCase
{
    private function row(string $section, ?int $estimate, int $spent): ActiveProjectRow
    {
        return new ActiveProjectRow(new Project(), $section, [], $estimate, $spent, null);
    }

    public function testFixedPriceWithinEstimate(): void
    {
        $row = $this->row(ActiveProjectRow::SECTION_FIXED, 36000, 18000);

        self::assertSame(18000, $row->getRemaining());
        self::assertSame(0, $row->getOvertime());
    }

    public function testFixedPriceOverEstimate(): void
    {
        $row = $this->row(ActiveProjectRow::SECTION_FIXED, 36000, 54000);

        self::assertSame(-18000, $row->getRemaining());
        self::assertSame(18000, $row->getOvertime());
    }

    public function testOngoingHasNoEstimate(): void
    {
        $row = $this->row(ActiveProjectRow::SECTION_ONGOING, null, 3600);

        self::assertNull($row->getRemaining());
        self::assertSame(0, $row->getOvertime());
    }

    public function testRetainerRemaining(): void
    {
        self::assertSame(7200, $this->row(ActiveProjectRow::SECTION_RETAINER, 14400, 7200)->getRemaining());
    }
}
