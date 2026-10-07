<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Service;

use App\Configuration\SystemConfiguration;
use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Timesheet\DateTimeFactory;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Finds the customer/project/activity combinations a user logged most often lately.
 */
class CommonCombinationService
{
    public const AMOUNT = 5;

    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly SystemConfiguration $configuration)
    {
    }

    /**
     * @return list<array{project: Project, activity: Activity, count: int}>
     */
    public function getCombinations(User $user, int $amount = self::AMOUNT): array
    {
        if ($amount < 1) {
            return [];
        }

        $factory = DateTimeFactory::createByUser($user);
        $now = $factory->createDateTime();

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('IDENTITY(t.project) AS projectId', 'IDENTITY(t.activity) AS activityId', 'COUNT(t.id) AS cnt', 'MAX(t.begin) AS lastBegin')
            ->from(Timesheet::class, 't')
            ->andWhere($qb->expr()->eq('t.user', ':user'))
            ->groupBy('t.project', 't.activity')
            ->orderBy('cnt', 'DESC')
            ->addOrderBy('lastBegin', 'DESC')
            // fetch more than needed, as invisible combinations are removed afterwards
            ->setMaxResults($amount * 3)
            ->setParameter('user', $user);

        $weeks = $this->configuration->find('quick_entry.recent_activity_weeks');
        if (is_numeric($weeks) && (int) $weeks > 0) {
            $qb->andWhere($qb->expr()->gte('t.begin', ':begin'))
                ->setParameter('begin', \DateTimeImmutable::createFromInterface($now)->modify(\sprintf('-%d weeks', (int) $weeks)));
        }

        /** @var list<array{projectId: int|string, activityId: int|string, cnt: int|string}> $rows */
        $rows = $qb->getQuery()->getArrayResult();
        if ($rows === []) {
            return [];
        }

        $projectIds = array_unique(array_map('intval', array_column($rows, 'projectId')));
        $activityIds = array_unique(array_map('intval', array_column($rows, 'activityId')));

        $projects = [];
        foreach ($this->entityManager->getRepository(Project::class)->findBy(['id' => $projectIds]) as $project) {
            $projects[(int) $project->getId()] = $project;
        }
        $activities = [];
        foreach ($this->entityManager->getRepository(Activity::class)->findBy(['id' => $activityIds]) as $activity) {
            $activities[(int) $activity->getId()] = $activity;
        }

        $result = [];
        foreach ($rows as $row) {
            $project = $projects[(int) $row['projectId']] ?? null;
            $activity = $activities[(int) $row['activityId']] ?? null;
            if ($project === null || $activity === null) {
                continue;
            }

            $customer = $project->getCustomer();
            if ($customer === null || !$customer->isVisible() || !$project->isVisibleAtDate($now) || !$activity->isVisible()) {
                continue;
            }

            $result[] = ['project' => $project, 'activity' => $activity, 'count' => (int) $row['cnt']];
            if (\count($result) >= $amount) {
                break;
            }
        }

        return $result;
    }
}
