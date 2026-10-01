<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Service;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\ProjectMeta;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Timesheet\TimesheetService;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\HappinessBundle\Configuration\RetainerConfiguration;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Books the monthly retainer hour on every project that has the retainer checkbox enabled.
 */
final class RetainerBookingService
{
    public const BOOKED = 'booked';
    public const SKIPPED = 'skipped';
    public const ERROR = 'error';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TimesheetService $timesheetService,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RetainerConfiguration $configuration,
    ) {
    }

    /**
     * @return list<array{project: string, status: string, message: string}>
     */
    public function book(\DateTimeInterface $month, bool $dryRun = false): array
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $this->configuration->getUsername()]);
        if (!$user instanceof User || !$user->isEnabled()) {
            throw new \RuntimeException(\sprintf('Retainer user "%s" not found or disabled.', $this->configuration->getUsername()));
        }

        $activityId = $this->configuration->getActivityId();
        if ($activityId <= 0) {
            throw new \RuntimeException('No retainer activity ID configured.');
        }
        $begin = (new \DateTime('now', new \DateTimeZone($user->getTimezone())))
            ->setDate((int) $month->format('Y'), (int) $month->format('n'), $this->configuration->getDayOfMonth())
            ->setTime(9, 0, 0);
        $seconds = (int) round($this->configuration->getHours() * 3600);

        $results = [];
        $previousToken = $this->tokenStorage->getToken();
        $this->tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        try {
            foreach ($this->findProjects() as $project) {
                $results[] = $this->bookProject($project, $user, $activityId, $begin, $seconds, $dryRun);
            }
        } finally {
            $this->tokenStorage->setToken($previousToken);
        }

        return $results;
    }

    /**
     * @return Project[]
     */
    private function findProjects(): array
    {
        $projects = [];
        /** @var ProjectMeta[] $metas */
        $metas = $this->entityManager->getRepository(ProjectMeta::class)->findBy(['name' => RetainerConfiguration::META_FIELD]);
        foreach ($metas as $meta) {
            // the meta type is not persisted, so a loaded value is a string like "1"
            $project = $meta->getEntity();
            if ($project instanceof Project && filter_var($meta->getValue(), FILTER_VALIDATE_BOOLEAN)) {
                $projects[$project->getId()] = $project;
            }
        }
        ksort($projects);

        return array_values($projects);
    }

    /**
     * @return array{project: string, status: string, message: string}
     */
    private function bookProject(Project $project, User $user, int $activityId, \DateTime $begin, int $seconds, bool $dryRun): array
    {
        $name = (string) $project->getName();

        if (!$project->isVisible() || !$project->getCustomer()?->isVisible() || !$project->isVisibleAtDate($begin)) {
            return $this->result($name, self::SKIPPED, 'Project is not active at the booking date');
        }

        $activity = $this->findActivity($project, $activityId);
        if ($activity === null) {
            return $this->result($name, self::SKIPPED, \sprintf('Activity %d not found or not usable on this project', $activityId));
        }

        if ($this->alreadyBooked($project, $activity, $user, $begin)) {
            return $this->result($name, self::SKIPPED, 'Already booked');
        }

        if ($dryRun) {
            return $this->result($name, self::BOOKED, 'Would book (dry run)');
        }

        try {
            $timesheet = new Timesheet();
            $timesheet->setUser($user);
            $timesheet->setProject($project);
            $timesheet->setActivity($activity);
            $timesheet->setBegin(clone $begin);
            $timesheet->setEnd((clone $begin)->modify('+' . $seconds . ' seconds'));
            $timesheet->setDuration($seconds);
            $timesheet->setDescription($this->configuration->getDescription());
            $timesheet->setBillableMode(Timesheet::BILLABLE_AUTOMATIC);

            $this->timesheetService->saveTimesheet($timesheet);
        } catch (\Throwable $ex) {
            return $this->result($name, self::ERROR, $ex->getMessage());
        }

        return $this->result($name, self::BOOKED, 'Booked');
    }

    /**
     * The activity must be visible and either belong to the project or be global (and allowed on the project).
     */
    private function findActivity(Project $project, int $id): ?Activity
    {
        $activity = $this->entityManager->getRepository(Activity::class)->find($id);
        if (!$activity instanceof Activity || !$activity->isVisible()) {
            return null;
        }

        if ($activity->isGlobal()) {
            return $project->isGlobalActivities() ? $activity : null;
        }

        return $activity->getProject() === $project ? $activity : null;
    }

    private function alreadyBooked(Project $project, Activity $activity, User $user, \DateTime $begin): bool
    {
        $day = $begin->format('Y-m-d');
        $timezone = $begin->getTimezone();

        $count = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(Timesheet::class, 't')
            ->where('t.user = :user')
            ->andWhere('t.project = :project')
            ->andWhere('t.activity = :activity')
            ->andWhere('t.description = :description')
            ->andWhere('t.begin >= :from')
            ->andWhere('t.begin < :to')
            ->setParameter('user', $user)
            ->setParameter('project', $project)
            ->setParameter('activity', $activity)
            ->setParameter('description', $this->configuration->getDescription())
            ->setParameter('from', new \DateTime($day . ' 00:00:00', $timezone))
            ->setParameter('to', new \DateTime($day . ' 23:59:59', $timezone))
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * @return array{project: string, status: string, message: string}
     */
    private function result(string $project, string $status, string $message): array
    {
        return ['project' => $project, 'status' => $status, 'message' => $message];
    }
}
