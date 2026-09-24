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
     *             totalRecords: int
     *         }>
     *     }>,
     *     totals: array{duration: int, rate: float, internalRate: float, totalRecords: int}
     * }
     */
    public function getGroupedByUserAndActivity(HappinessReportQuery $query): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select([
            'IDENTITY(t.user) AS user_id',
            'IDENTITY(t.activity) AS activity_id',
            'COALESCE(SUM(t.duration), 0) AS duration',
            'COALESCE(SUM(t.rate), 0) AS rate',
            'COALESCE(SUM(t.internalRate), 0) AS internalRate',
            'COUNT(t.id) AS total_records',
        ])
        ->from(Timesheet::class, 't')
        ->where($qb->expr()->isNotNull('t.end'))
        ->groupBy('user_id')
        ->addGroupBy('activity_id');

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
            $qb->leftJoin('t.project', 'p')
               ->andWhere($qb->expr()->in('p.customer', ':customers'))
               ->setParameter('customers', $query->getCustomers());
        }

        /** @var array<int, array{user_id: int|string|null, activity_id: int|string|null, duration: int|string, rate: float|string, internalRate: float|string, total_records: int|string}> $results */
        $results = $qb->getQuery()->getArrayResult();

        if (empty($results)) {
            return [
                'users' => [],
                'totals' => ['duration' => 0, 'rate' => 0.0, 'internalRate' => 0.0, 'totalRecords' => 0],
            ];
        }

        $userIds = array_values(array_filter(array_unique(array_map(fn ($r) => (int) $r['user_id'], $results))));
        $activityIds = array_values(array_filter(array_unique(array_map(fn ($r) => (int) $r['activity_id'], $results))));

        $users = $this->entityManager->getRepository(User::class)->findBy(['id' => $userIds]);
        $activities = $this->entityManager->getRepository(Activity::class)->findBy(['id' => $activityIds]);

        $userMap = [];
        foreach ($users as $user) {
            $userMap[$user->getId()] = $user;
        }

        $activityMap = [];
        foreach ($activities as $activity) {
            $activityMap[$activity->getId()] = $activity;
        }

        $tree = [];
        $grandTotals = ['duration' => 0, 'rate' => 0.0, 'internalRate' => 0.0, 'totalRecords' => 0];

        foreach ($results as $row) {
            $uid = (int) $row['user_id'];
            $aid = (int) $row['activity_id'];
            $duration = (int) $row['duration'];
            $rate = (float) $row['rate'];
            $internalRate = (float) $row['internalRate'];
            $totalRecords = (int) $row['total_records'];

            if (!isset($userMap[$uid]) || !isset($activityMap[$aid])) {
                continue;
            }

            if (!isset($tree[$uid])) {
                $tree[$uid] = [
                    'user' => $userMap[$uid],
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
            $tree[$uid]['totalRecords'] += $totalRecords;

            $tree[$uid]['activities'][$aid] = [
                'activity' => $activityMap[$aid],
                'duration' => $duration,
                'rate' => $rate,
                'internalRate' => $internalRate,
                'totalRecords' => $totalRecords,
            ];

            $grandTotals['duration'] += $duration;
            $grandTotals['rate'] += $rate;
            $grandTotals['internalRate'] += $internalRate;
            $grandTotals['totalRecords'] += $totalRecords;
        }

        return [
            'users' => $tree,
            'totals' => $grandTotals,
        ];
    }
}
