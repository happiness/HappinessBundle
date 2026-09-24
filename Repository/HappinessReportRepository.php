<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Repository;

use App\Entity\Activity;
use App\Entity\Timesheet;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\HappinessBundle\Reporting\HappinessReportQuery;

final class HappinessReportRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return array{
     *     users: array<int, array{
     *         user: User,
     *         duration: int,
     *         rate: float,
     *         internalRate: float,
     *         totalRecords: int,
     *         activities: array<int, array{
     *             activity: Activity,
     *             duration: int,
     *             rate: float,
     *             internalRate: float,
     *             totalRecords: int,
     *             timesheets: array<int, Timesheet>
     *         }>
     *     }>,
     *     totals: array{duration: int, rate: float, internalRate: float, totalRecords: int}
     * }
     */
    public function getGroupedByUserAndActivity(HappinessReportQuery $query): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('t')
            ->addSelect('u')
            ->addSelect('a')
            ->addSelect('p')
            ->addSelect('c')
            ->from(Timesheet::class, 't')
            ->join('t.user', 'u')
            ->join('t.activity', 'a')
            ->leftJoin('t.project', 'p')
            ->leftJoin('p.customer', 'c')
            ->where($qb->expr()->isNotNull('t.end'))
            ->addOrderBy('t.begin', 'DESC');

        if ($query->getDateRange()->getBegin() !== null) {
            $qb->andWhere($qb->expr()->gte('t.begin', ':begin'))
               ->setParameter('begin', $query->getDateRange()->getBegin());
        }

        if ($query->getDateRange()->getEnd() !== null) {
            $qb->andWhere($qb->expr()->lte('t.begin', ':end'))
               ->setParameter('end', $query->getDateRange()->getEnd());
        }

        if (!empty($query->getUsers())) {
            $qb->andWhere($qb->expr()->in('t.user', ':users'))
               ->setParameter('users', $query->getUsers());
        }

        if (!empty($query->getActivities())) {
            $qb->andWhere($qb->expr()->in('t.activity', ':activities'))
               ->setParameter('activities', $query->getActivities());
        }

        if (!empty($query->getProjects())) {
            $qb->andWhere($qb->expr()->in('t.project', ':projects'))
               ->setParameter('projects', $query->getProjects());
        }

        if (!empty($query->getCustomers())) {
            $qb->andWhere($qb->expr()->in('p.customer', ':customers'))
               ->setParameter('customers', $query->getCustomers());
        }

        /** @var Timesheet[] $timesheets */
        $timesheets = $qb->getQuery()->getResult();

        if (empty($timesheets)) {
            return [
                'users' => [],
                'totals' => ['duration' => 0, 'rate' => 0.0, 'internalRate' => 0.0, 'totalRecords' => 0],
            ];
        }

        $tree = [];
        $grandTotals = ['duration' => 0, 'rate' => 0.0, 'internalRate' => 0.0, 'totalRecords' => 0];

        foreach ($timesheets as $timesheet) {
            $user = $timesheet->getUser();
            $activity = $timesheet->getActivity();

            if ($user === null || $activity === null) {
                continue;
            }

            $uid = (int) $user->getId();
            $aid = (int) $activity->getId();
            $duration = (int) ($timesheet->getDuration() ?? 0);
            $rate = (float) $timesheet->getRate();
            $internalRate = (float) $timesheet->getInternalRate();

            if (!isset($tree[$uid])) {
                $tree[$uid] = [
                    'user' => $user,
                    'duration' => 0,
                    'rate' => 0.0,
                    'internalRate' => 0.0,
                    'totalRecords' => 0,
                    'activities' => [],
                ];
            }

            $tree[$uid]['duration'] += $duration;
            $tree[$uid]['rate'] += $rate;
            $tree[$uid]['internalRate'] += $internalRate;
            $tree[$uid]['totalRecords']++;

            if (!isset($tree[$uid]['activities'][$aid])) {
                $tree[$uid]['activities'][$aid] = [
                    'activity' => $activity,
                    'duration' => 0,
                    'rate' => 0.0,
                    'internalRate' => 0.0,
                    'totalRecords' => 0,
                    'timesheets' => [],
                ];
            }

            $tree[$uid]['activities'][$aid]['duration'] += $duration;
            $tree[$uid]['activities'][$aid]['rate'] += $rate;
            $tree[$uid]['activities'][$aid]['internalRate'] += $internalRate;
            $tree[$uid]['activities'][$aid]['totalRecords']++;
            $tree[$uid]['activities'][$aid]['timesheets'][] = $timesheet;

            $grandTotals['duration'] += $duration;
            $grandTotals['rate'] += $rate;
            $grandTotals['internalRate'] += $internalRate;
            $grandTotals['totalRecords']++;
        }

        return [
            'users' => $tree,
            'totals' => $grandTotals,
        ];
    }
}
