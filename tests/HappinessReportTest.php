<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Tests;

use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\User;
use App\Event\ReportingEvent;
use App\Form\Model\DateRange;
use App\Form\Type\ActivityType;
use App\Form\Type\CustomerType;
use App\Form\Type\DateRangeType;
use App\Form\Type\ProjectType;
use App\Form\Type\UserType;
use App\Reporting\Report;
use App\Repository\ActivityRepository;
use App\Repository\Query\ActivityFormTypeQuery;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use KimaiPlugin\HappinessBundle\EventSubscriber\ReportingSubscriber;
use KimaiPlugin\HappinessBundle\Form\HappinessReportForm;
use KimaiPlugin\HappinessBundle\Reporting\HappinessReportQuery;
use KimaiPlugin\HappinessBundle\Repository\HappinessReportRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class HappinessReportTest extends TestCase
{
    private string $bundleDir;

    protected function setUp(): void
    {
        $this->bundleDir = \dirname(__DIR__);
    }

    public function testReportingSubscriberNotGranted(): void
    {
        $security = $this->createMock(AuthorizationCheckerInterface::class);
        $security->method('isGranted')->with('view_reporting')->willReturn(false);

        $subscriber = new ReportingSubscriber($security);
        $user = new User();
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $event = new ReportingEvent($user, $tokenStorage);

        $subscriber->onReporting($event);

        self::assertEmpty($event->getReports());
    }

    public function testReportingSubscriberGranted(): void
    {
        $security = $this->createMock(AuthorizationCheckerInterface::class);
        $security->method('isGranted')->with('view_reporting')->willReturn(true);

        $subscriber = new ReportingSubscriber($security);
        $user = new User();
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $event = new ReportingEvent($user, $tokenStorage);

        $subscriber->onReporting($event);

        $reports = $event->getReports();
        self::assertCount(1, $reports);
        $report = $reports[0];
        self::assertInstanceOf(Report::class, $report);
        self::assertEquals('happiness_report', $report->getId());
        self::assertEquals('happiness_report', $report->getRoute());
        self::assertEquals('report_happiness', $report->getLabel());
        self::assertEquals('fas fa-smile', $report->getReportIcon());
        self::assertEquals('happiness', $report->getTranslationDomain());
    }

    public function testHappinessReportQuery(): void
    {
        $user = new User();
        $query = new HappinessReportQuery($user);

        self::assertSame($user, $query->getCurrentUser());
        self::assertInstanceOf(DateRange::class, $query->getDateRange());

        $dateRange = new DateRange(true);
        $begin = new \DateTime('2026-01-01');
        $end = new \DateTime('2026-01-31');
        $dateRange->setBegin($begin);
        $dateRange->setEnd($end);
        $query->setDateRange($dateRange);
        self::assertSame($dateRange, $query->getDateRange());

        $customer = new Customer('Customer 1');
        $project = new Project();
        $project->setName('Project 1');
        $activity = new Activity();
        $activity->setName('Activity 1');
        $otherUser = new User();

        $query->setCustomers([$customer]);
        $query->setProjects([$project]);
        $query->setActivities([$activity]);
        $query->setUsers([$otherUser]);

        self::assertSame([$customer], $query->getCustomers());
        self::assertSame([$project], $query->getProjects());
        self::assertSame([$activity], $query->getActivities());
        self::assertSame([$otherUser], $query->getUsers());
    }

    public function testHappinessReportFormConfigurationAndActivityQueryBuilder(): void
    {
        $formType = new HappinessReportForm();

        $resolver = new OptionsResolver();
        $formType->configureOptions($resolver);

        $user = new User();
        $options = $resolver->resolve(['user' => $user]);

        self::assertSame(HappinessReportQuery::class, $options['data_class']);
        self::assertFalse($options['csrf_protection']);
        self::assertSame('GET', $options['method']);
        self::assertSame($user, $options['user']);

        $fields = [];
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function ($child, $type, $options) use (&$fields, $builder) {
            $fields[$child] = [
                'type' => $type,
                'options' => $options,
            ];

            return $builder;
        });

        $formType->buildForm($builder, $options);

        self::assertArrayHasKey('dateRange', $fields);
        self::assertArrayHasKey('customers', $fields);
        self::assertArrayHasKey('projects', $fields);
        self::assertArrayHasKey('activities', $fields);
        self::assertArrayHasKey('users', $fields);

        self::assertSame(ActivityType::class, $fields['activities']['type']);
        self::assertSame(ProjectType::class, $fields['projects']['type']);
        self::assertTrue($fields['projects']['options']['join_customer']);

        $activityOptions = $fields['activities']['options'];
        self::assertTrue($activityOptions['multiple']);
        self::assertIsCallable($activityOptions['query_builder']);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getExpressionBuilder')->willReturn(new \Doctrine\ORM\Query\Expr());
        $qb = new QueryBuilder($em);
        $qb->select('a')->from(Activity::class, 'a');

        $activityRepo = $this->createMock(ActivityRepository::class);
        $activityRepo->expects(self::once())
            ->method('createQueryBuilder')
            ->with('a')
            ->willReturn($qb);

        $resultQb = ($activityOptions['query_builder'])($activityRepo);
        self::assertSame($qb, $resultQb);
        self::assertStringContainsString('a.project', (string) $qb->getDQL());
        self::assertStringContainsString('p.customer', (string) $qb->getDQL());
    }

    public function testHappinessReportRepositoryGrouping(): void
    {
        $user1 = new User();
        $user1->setAlias('User 1');
        $user1->setUserIdentifier('user1');
        $u1Prop = new \ReflectionProperty(User::class, 'id');
        $u1Prop->setValue($user1, 10);

        $user2 = new User();
        $user2->setAlias('User 2');
        $user2->setUserIdentifier('user2');
        $u2Prop = new \ReflectionProperty(User::class, 'id');
        $u2Prop->setValue($user2, 20);

        $activity1 = new Activity();
        $activity1->setName('Dev');
        $a1Prop = new \ReflectionProperty(Activity::class, 'id');
        $a1Prop->setValue($activity1, 100);

        $activity2 = new Activity();
        $activity2->setName('Design');
        $a2Prop = new \ReflectionProperty(Activity::class, 'id');
        $a2Prop->setValue($activity2, 200);

        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('findBy')->willReturn([$user1, $user2]);

        $activityRepo = $this->createMock(EntityRepository::class);
        $activityRepo->method('findBy')->willReturn([$activity1, $activity2]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getExpressionBuilder')->willReturn(new \Doctrine\ORM\Query\Expr());
        $em->method('getRepository')->willReturnCallback(function (string $class) use ($userRepo, $activityRepo) {
            if ($class === User::class) {
                return $userRepo;
            }
            if ($class === Activity::class) {
                return $activityRepo;
            }

            return null;
        });

        $queryResult = [
            [
                'user_id' => 10,
                'activity_id' => 100,
                'duration' => 3600,
                'rate' => 150.0,
                'internalRate' => 100.0,
                'total_records' => 2,
            ],
            [
                'user_id' => 10,
                'activity_id' => 200,
                'duration' => 1800,
                'rate' => 75.0,
                'internalRate' => 50.0,
                'total_records' => 1,
            ],
            [
                'user_id' => 20,
                'activity_id' => 100,
                'duration' => 7200,
                'rate' => 300.0,
                'internalRate' => 200.0,
                'total_records' => 4,
            ],
        ];

        $doctrineQuery = $this->createMock(AbstractQuery::class);
        $doctrineQuery->method('getArrayResult')->willReturn($queryResult);

        $qb = $this->getMockBuilder(QueryBuilder::class)
            ->setConstructorArgs([$em])
            ->onlyMethods(['getQuery'])
            ->getMock();
        $qb->method('getQuery')->willReturn($doctrineQuery);

        $em->method('createQueryBuilder')->willReturnCallback(function () use ($em, $doctrineQuery) {
            $qb = new class($em, $doctrineQuery) extends QueryBuilder {
                public function __construct(EntityManagerInterface $em, private readonly AbstractQuery $mockQuery)
                {
                    parent::__construct($em);
                }

                public function getQuery(): AbstractQuery
                {
                    return $this->mockQuery;
                }
            };

            return $qb;
        });

        $repo = new HappinessReportRepository($em);
        $query = new HappinessReportQuery($user1);
        $data = $repo->getGroupedByUserAndActivity($query);

        self::assertArrayHasKey('users', $data);
        self::assertArrayHasKey('totals', $data);

        self::assertCount(2, $data['users']);
        self::assertArrayHasKey(10, $data['users']);
        self::assertArrayHasKey(20, $data['users']);

        $u1Data = $data['users'][10];
        self::assertSame($user1, $u1Data['user']);
        self::assertEquals(5400, $u1Data['duration']);
        self::assertEquals(225.0, $u1Data['rate']);
        self::assertEquals(150.0, $u1Data['internalRate']);
        self::assertEquals(3, $u1Data['totalRecords']);
        self::assertCount(2, $u1Data['activities']);
        self::assertEquals(3600, $u1Data['activities'][100]['duration']);
        self::assertEquals(1800, $u1Data['activities'][200]['duration']);

        $totals = $data['totals'];
        self::assertEquals(12600, $totals['duration']);
        self::assertEquals(525.0, $totals['rate']);
        self::assertEquals(350.0, $totals['internalRate']);
        self::assertEquals(7, $totals['totalRecords']);
    }

    public function testReportingTemplateStructure(): void
    {
        $templateFile = $this->bundleDir . '/Resources/views/reporting/happiness.html.twig';
        self::assertFileExists($templateFile);

        $content = (string) file_get_contents($templateFile);
        self::assertStringContainsString("{% extends 'reporting/layout.html.twig' %}", $content);
        self::assertStringContainsString('report_happiness', $content);
        self::assertStringContainsString("'duration'|trans", $content);
        self::assertStringContainsString("'entryState'|trans", $content);
        self::assertStringContainsString('reportData.users', $content);
        self::assertStringContainsString('item.user.displayName', $content);
        self::assertStringContainsString('actData.activity.name', $content);
        self::assertStringContainsString('item.duration|duration', $content);
        self::assertStringContainsString('item.rate|money', $content);
        self::assertStringContainsString('item.internalRate|money', $content);
        self::assertStringContainsString('sum.total', $content);
        self::assertStringContainsString('reportData.totals.duration|duration', $content);
    }

    public function testRoutesConfiguration(): void
    {
        $routesFile = $this->bundleDir . '/Resources/config/routes.yaml';
        self::assertFileExists($routesFile);

        $content = (string) file_get_contents($routesFile);
        self::assertStringContainsString('@HappinessBundle/Controller/', $content);
        self::assertStringContainsString('prefix: /{_locale}', $content);
    }

    public function testTranslationConfiguration(): void
    {
        $transFile = $this->bundleDir . '/Resources/translations/happiness.en.xlf';
        self::assertFileExists($transFile);

        $content = (string) file_get_contents($transFile);
        self::assertStringContainsString('report_happiness', $content);
        self::assertStringContainsString('Happiness report', $content);
    }
}
