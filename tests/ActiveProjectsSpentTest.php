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
use App\Entity\ProjectMeta;
use App\Entity\Timesheet;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use KimaiPlugin\HappinessBundle\Configuration\RetainerBalanceFields;
use KimaiPlugin\HappinessBundle\Repository\ActiveProjectsRepository;
use KimaiPlugin\HappinessBundle\Repository\RetainerAdjustmentRepository;
use KimaiPlugin\HappinessBundle\Service\RetainerBalanceCalculator;
use KimaiPlugin\HappinessBundle\Service\RetainerBalanceService;
use PHPUnit\Framework\TestCase;

/**
 * The breakdown of the spent time in the active projects report.
 */
class ActiveProjectsSpentTest extends TestCase
{
    /** @var list<QueryBuilder> */
    private array $builders = [];

    public function testAllTimeUsesFinishedBillableTimesheetsOfTheProject(): void
    {
        $timesheet = new Timesheet();
        $em = $this->entityManager([$timesheet]);
        $project = new Project();

        $result = $this->repository($em)->findSpentTimesheets($project, new \DateTimeImmutable('2026-11-09'));

        self::assertSame([$timesheet], $result);
        self::assertCount(1, $this->builders);
        $dql = $this->builders[0]->getDQL();
        self::assertStringContainsString('t.end IS NOT NULL', $dql);
        self::assertStringContainsString('t.billable = true', $dql);
        self::assertStringContainsString('t.project = :project', $dql);
        self::assertStringContainsString('ORDER BY t.begin ASC, t.id ASC', $dql);
        self::assertSame($project, $this->builders[0]->getParameter('project')?->getValue());
    }

    public function testRetainerUsesTheLoggedTimesheetsOfTheMonthInUtc(): void
    {
        $timesheet = new Timesheet();
        $em = $this->entityManager([$timesheet]);
        $project = $this->retainer('2026-01');

        $result = $this->repository($em)->findSpentTimesheets($project, new \DateTimeImmutable('2026-11-30'));

        self::assertSame([$timesheet], $result);
        self::assertCount(1, $this->builders);
        $qb = $this->builders[0];
        $dql = $qb->getDQL();
        self::assertStringContainsString('t.end IS NOT NULL', $dql);
        self::assertStringNotContainsString('billable', $dql);
        self::assertStringContainsString('LOWER(a.name) <> :excluded', $dql);
        self::assertStringContainsString('t.begin >= :monthStart', $dql);
        self::assertStringContainsString('t.begin < :monthEnd', $dql);
        self::assertSame('faktureras ej', $qb->getParameter('excluded')?->getValue());
        self::assertEquals(new \DateTimeImmutable('2026-11-01 00:00:00', new \DateTimeZone('UTC')), $qb->getParameter('monthStart')?->getValue());
        self::assertEquals(new \DateTimeImmutable('2026-12-01 00:00:00', new \DateTimeZone('UTC')), $qb->getParameter('monthEnd')?->getValue());
    }

    public function testRetainerWithoutStartMonthHasNoTimesheets(): void
    {
        $em = $this->entityManager([new Timesheet()]);

        self::assertSame([], $this->retainers($em)->findLoggedTimesheets($this->retainer(null), '2026-11'));
        self::assertSame([], $this->builders);
    }

    public function testRetainerMonthBeforeStartMonthHasNoTimesheets(): void
    {
        $em = $this->entityManager([new Timesheet()]);

        self::assertSame([], $this->retainers($em)->findLoggedTimesheets($this->retainer('2026-12'), '2026-11'));
        self::assertSame([], $this->builders);
    }

    public function testReportTemplateLinksToTheBreakdown(): void
    {
        $dir = \dirname(__DIR__) . '/Resources/views/reporting/';

        $report = (string) file_get_contents($dir . 'active-projects.html.twig');
        self::assertStringContainsString("path('happiness_active_projects_spent', {id: row.project.id, week: week})", $report);
        self::assertStringContainsString("is_granted('view_other_timesheet')", $report);

        $breakdown = (string) file_get_contents($dir . 'active-projects-spent.html.twig');
        self::assertStringContainsString("{% extends 'reporting/layout.html.twig' %}", $breakdown);
        self::assertStringContainsString('timesheet.duration|duration', $breakdown);
        self::assertStringContainsString('macros.hours(total)', $breakdown);
    }

    /**
     * @param list<Timesheet> $result
     */
    private function entityManager(array $result): EntityManagerInterface
    {
        $query = $this->createMock(AbstractQuery::class);
        $query->method('getResult')->willReturn($result);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getExpressionBuilder')->willReturn(new Expr());
        $em->method('createQueryBuilder')->willReturnCallback(function () use ($em, $query) {
            $qb = new class($em, $query) extends QueryBuilder {
                /**
                 * @param AbstractQuery<mixed, mixed> $mockQuery
                 */
                public function __construct(EntityManagerInterface $em, private readonly AbstractQuery $mockQuery)
                {
                    parent::__construct($em);
                }

                /**
                 * @return AbstractQuery<mixed, mixed>
                 */
                public function getQuery(): AbstractQuery // @phpstan-ignore method.childReturnType (Query is final and cannot be mocked)
                {
                    return $this->mockQuery;
                }
            };
            $this->builders[] = $qb;

            return $qb;
        });

        return $em;
    }

    private function retainers(EntityManagerInterface $em): RetainerBalanceService
    {
        return new RetainerBalanceService($em, $this->createMock(RetainerAdjustmentRepository::class), new RetainerBalanceCalculator());
    }

    private function repository(EntityManagerInterface $em): ActiveProjectsRepository
    {
        return new ActiveProjectsRepository($em, $this->retainers($em));
    }

    private function retainer(?string $start): Project
    {
        $project = new Project();
        $project->setMetaField((new ProjectMeta())->setName(RetainerBalanceFields::ENABLED)->setValue('1'));
        if ($start !== null) {
            $project->setMetaField((new ProjectMeta())->setName(RetainerBalanceFields::START)->setValue($start));
        }

        return $project;
    }
}
