<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Repository;

use App\Entity\Project;
use App\Entity\Timesheet;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\HappinessBundle\Model\ActiveProjectRow;
use KimaiPlugin\HappinessBundle\Service\RetainerBalanceService;

/**
 * The data of the active projects report (billable timesheets only): visible projects that have not ended, in the sections ongoing, fixed price and retainer.
 */
final class ActiveProjectsRepository
{
    /**
     * Users with time on a project during this many days before the end of the shown week are listed as participants.
     */
    public const PARTICIPANT_DAYS = 90;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RetainerBalanceService $retainers,
    ) {
    }

    /**
     * The week column and the retainer month follow the given week, the other figures are all time.
     *
     * @param \DateTimeImmutable $weekStart Monday of the week to show
     * @return array<string, list<ActiveProjectRow>> rows indexed by section (ongoing projects without logged time in the week are left out), in the order ongoing, fixed price, retainer
     */
    public function getRows(\DateTimeImmutable $weekStart): array
    {
        $now = new \DateTimeImmutable();
        $totals = $this->getTotals();
        $users = $this->getUserTimes($weekStart);

        $sections = [
            ActiveProjectRow::SECTION_ONGOING => [],
            ActiveProjectRow::SECTION_FIXED => [],
            ActiveProjectRow::SECTION_RETAINER => [],
        ];

        foreach ($this->findProjects($now) as $project) {
            $id = (int) $project->getId();
            $isRetainer = $this->retainers->isRetainer($project);

            $participants = [];
            $weekTotal = 0;
            foreach ($users[$id] ?? [] as $name => $times) {
                $participants[$name] = ['user' => $times['user'], 'seconds' => $times['week']];
                $weekTotal += $times['week'];
            }
            ksort($participants);
            $participants = array_values($participants);

            $estimate = $project->hasTimeBudget() ? $project->getTimeBudget() : null;
            $spent = $totals[$id] ?? 0;
            $saved = null;
            $section = $isRetainer ? ActiveProjectRow::SECTION_RETAINER : ($estimate !== null ? ActiveProjectRow::SECTION_FIXED : ActiveProjectRow::SECTION_ONGOING);

            if ($isRetainer) {
                $months = $this->retainers->getMonths($project, $weekStart);
                $month = $months === [] ? null : $months[array_key_last($months)];
                $estimate = (int) round($this->retainers->getHoursPerMonth($project) * 3600);
                $spent = $month === null ? 0 : (int) round($month->logged * 3600);
                $saved = $month === null ? null : (int) round($month->getOpening() * 3600);
            }

            if ($section === ActiveProjectRow::SECTION_ONGOING && $weekTotal === 0) {
                continue;
            }

            $sections[$section][] = new ActiveProjectRow(
                $project,
                $section,
                $participants,
                $estimate,
                $spent,
                $saved,
            );
        }

        return $sections;
    }

    /**
     * @return list<Project>
     */
    private function findProjects(\DateTimeImmutable $now): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('p', 'c')
            ->from(Project::class, 'p')
            ->join('p.customer', 'c')
            ->where('p.visible = true')
            ->andWhere('c.visible = true')
            ->andWhere('p.end IS NULL OR p.end >= :today')
            ->orderBy('c.name')
            ->addOrderBy('p.name')
            ->setParameter('today', $now->setTime(0, 0));

        return array_values($qb->getQuery()->getResult());
    }

    /**
     * @return array<int, int> all time seconds of finished billable timesheets by project id
     */
    private function getTotals(): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(t.project) AS project', 'SUM(t.duration) AS spent')
            ->from(Timesheet::class, 't')
            ->where('t.end IS NOT NULL')
            ->andWhere('t.billable = true')
            ->groupBy('t.project')
            ->getQuery()
            ->getArrayResult();

        $totals = [];
        foreach ($rows as $row) {
            $totals[(int) $row['project']] = (int) $row['spent'];
        }

        return $totals;
    }

    /**
     * @return array<int, array<string, array{user: User, week: int}>> project id => user name => user and seconds this week, for users active the last PARTICIPANT_DAYS
     */
    private function getUserTimes(\DateTimeImmutable $weekStart): array
    {
        $weekEnd = $weekStart->modify('+7 days');

        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(t.project) AS project', 'IDENTITY(t.user) AS userId', 'u.alias AS alias', 'u.username AS username', 'SUM(CASE WHEN t.begin >= :week AND t.begin < :weekEnd THEN t.duration ELSE 0 END) AS week')
            ->from(Timesheet::class, 't')
            ->join('t.user', 'u')
            ->where('t.end IS NOT NULL')
            ->andWhere('t.billable = true')
            ->andWhere('t.begin >= :since')
            ->andWhere('t.begin < :weekEnd')
            ->groupBy('t.project', 'u.id')
            ->setParameter('week', $weekStart)
            ->setParameter('weekEnd', $weekEnd)
            ->setParameter('since', $weekEnd->modify('-' . self::PARTICIPANT_DAYS . ' days'))
            ->getQuery()
            ->getArrayResult();

        $entities = [];
        /** @var User $user */
        foreach ($this->entityManager->getRepository(User::class)->findBy(['id' => array_unique(array_map(static fn (array $row) => (int) $row['userId'], $rows))]) as $user) {
            $entities[(int) $user->getId()] = $user;
        }

        $users = [];
        foreach ($rows as $row) {
            $name = trim((string) $row['alias']) !== '' ? (string) $row['alias'] : (string) $row['username'];
            $users[(int) $row['project']][$name] = ['user' => $entities[(int) $row['userId']], 'week' => (int) $row['week']];
        }

        return $users;
    }
}
