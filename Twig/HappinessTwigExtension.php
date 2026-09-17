<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Twig;

use App\Entity\User;
use App\Timesheet\DateTimeFactory;
use App\Twig\Extensions;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class HappinessTwigExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('happiness_this_week_range', [$this, 'getThisWeekRange']),
        ];
    }

    public function getThisWeekRange(?User $user = null): string
    {
        $factory = $user !== null ? DateTimeFactory::createByUser($user) : new DateTimeFactory();
        $start = $factory->getStartOfWeek();
        $end = $factory->getEndOfWeek();

        return $start->format(Extensions::REPORT_DATE) . ' - ' . $end->format(Extensions::REPORT_DATE);
    }
}
