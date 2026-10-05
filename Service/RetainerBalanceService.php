<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Service;

use App\Entity\Project;
use App\Entity\ProjectMeta;
use App\Entity\Timesheet;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\HappinessBundle\Configuration\RetainerBalanceFields;
use KimaiPlugin\HappinessBundle\Model\RetainerMonth;
use KimaiPlugin\HappinessBundle\Repository\RetainerAdjustmentRepository;

/**
 * Calculates the retainer hour balance of a project on demand from its timesheets,
 * so that edits of timesheets are reflected immediately.
 */
final class RetainerBalanceService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RetainerAdjustmentRepository $adjustments,
        private readonly RetainerBalanceCalculator $calculator,
    ) {
    }

    /**
     * Projects with the retainer checkbox enabled, ordered by customer and project name.
     *
     * @return list<Project>
     */
    public function findProjects(): array
    {
        $projects = [];
        /** @var ProjectMeta[] $metas */
        $metas = $this->entityManager->getRepository(ProjectMeta::class)->findBy(['name' => RetainerBalanceFields::ENABLED]);
        foreach ($metas as $meta) {
            $project = $meta->getEntity();
            if ($project instanceof Project && filter_var($meta->getValue(), FILTER_VALIDATE_BOOLEAN)) {
                $projects[(int) $project->getId()] = $project;
            }
        }

        usort($projects, static fn (Project $a, Project $b) => [$a->getCustomer()?->getName(), $a->getName()] <=> [$b->getCustomer()?->getName(), $b->getName()]);

        return array_values($projects);
    }

    public function isRetainer(Project $project): bool
    {
        return filter_var($project->getMetaField(RetainerBalanceFields::ENABLED)?->getValue(), FILTER_VALIDATE_BOOLEAN);
    }

    public function getHoursPerMonth(Project $project): float
    {
        return max(0.0, (float) $project->getMetaField(RetainerBalanceFields::HOURS)?->getValue());
    }

    /**
     * The first month (YYYY-MM) of the balance, null if the project has none configured.
     */
    public function getStartMonth(Project $project): ?string
    {
        $value = trim((string) $project->getMetaField(RetainerBalanceFields::START)?->getValue());

        return preg_match(RetainerBalanceFields::START_PATTERN, $value) === 1 ? $value : null;
    }

    /**
     * The month by month balance from the start month until the given month, empty if the start month is missing or lies after it.
     *
     * @return list<RetainerMonth>
     */
    public function getMonths(Project $project, \DateTimeInterface $until): array
    {
        $start = $this->getStartMonth($project);
        if ($start === null) {
            return [];
        }

        return $this->calculator->calculate(
            $start,
            $until->format('Y-m'),
            $this->getHoursPerMonth($project),
            $this->getLoggedSeconds($project, $start),
            $this->adjustments->findByProject($project),
        );
    }

    /**
     * @return array<string, int> logged seconds of finished timesheets indexed by month (YYYY-MM)
     */
    private function getLoggedSeconds(Project $project, string $start): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('t.begin AS begin', 't.duration AS duration')
            ->from(Timesheet::class, 't')
            ->where('t.project = :project')
            ->andWhere('t.begin >= :from')
            ->andWhere('t.end IS NOT NULL')
            ->setParameter('project', $project)
            ->setParameter('from', new \DateTime($start . '-01 00:00:00'))
            ->getQuery()
            ->getArrayResult();

        $seconds = [];
        foreach ($rows as $row) {
            $month = $row['begin']->format('Y-m');
            $seconds[$month] = ($seconds[$month] ?? 0) + (int) $row['duration'];
        }

        return $seconds;
    }
}
