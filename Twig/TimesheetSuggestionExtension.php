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
use KimaiPlugin\HappinessBundle\Service\CommonCombinationService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TimesheetSuggestionExtension extends AbstractExtension
{
    public function __construct(private readonly CommonCombinationService $service)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('happiness_common_combinations', [$this, 'getCombinations']),
        ];
    }

    /**
     * @return list<array{project: \App\Entity\Project, activity: \App\Entity\Activity, count: int}>
     */
    public function getCombinations(?User $user): array
    {
        return $user === null ? [] : $this->service->getCombinations($user);
    }
}
