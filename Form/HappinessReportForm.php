<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Form;

use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\Team;
use App\Entity\User;
use App\Form\Type\ActivityType;
use App\Form\Type\CustomerType;
use App\Form\Type\DateRangeType;
use App\Form\Type\ProjectType;
use App\Form\Type\UserType;
use App\Repository\ActivityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\QueryBuilder;
use KimaiPlugin\HappinessBundle\Reporting\HappinessReportQuery;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<HappinessReportQuery>
 */
final class HappinessReportForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var User $user */
        $user = $options['user'];

        $builder->add('dateRange', DateRangeType::class, [
            'required' => false,
            'timezone' => $options['timezone'],
            'user' => $user,
        ]);

        $builder->add('customers', CustomerType::class, [
            'required' => false,
            'multiple' => true,
            'user' => $user,
            'project_enabled' => 'customers[]',
            'project_select' => 'projects',
            'ignore_date' => true,
        ]);

        $this->addProjectField($builder, [], [], $user);
        $this->addActivityField($builder, [], [], $user);

        $builder->add('users', UserType::class, [
            'required' => false,
            'multiple' => true,
            'user' => $user,
        ]);

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($user): void {
                $query = $event->getData();
                if (!$query instanceof HappinessReportQuery) {
                    return;
                }

                $customers = $query->getCustomers();
                $projects = $query->getProjects();
                $activities = $query->getActivities();

                $this->addProjectField($event->getForm(), $customers, $projects, $user);
                $this->addActivityField($event->getForm(), $projects, $activities, $user);
            }
        );

        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) use ($user): void {
                $data = $event->getData();
                if (!\is_array($data)) {
                    return;
                }

                $customers = [];
                if (\array_key_exists('customers', $data) && $data['customers'] !== null && $data['customers'] !== '') {
                    $customers = \is_array($data['customers']) ? $data['customers'] : [$data['customers']];
                    $customers = array_values(array_filter(array_map(static fn ($c) => is_numeric($c) ? (int) $c : $c, $customers)));
                }

                $projects = [];
                if (\array_key_exists('projects', $data) && $data['projects'] !== null && $data['projects'] !== '') {
                    $projects = \is_array($data['projects']) ? $data['projects'] : [$data['projects']];
                    $projects = array_values(array_filter(array_map(static fn ($p) => is_numeric($p) ? (int) $p : $p, $projects)));
                }

                $activities = [];
                if (\array_key_exists('activities', $data) && $data['activities'] !== null && $data['activities'] !== '') {
                    $activities = \is_array($data['activities']) ? $data['activities'] : [$data['activities']];
                    $activities = array_values(array_filter(array_map(static fn ($a) => is_numeric($a) ? (int) $a : $a, $activities)));
                }

                $this->addProjectField($event->getForm(), $customers, $projects, $user);
                $this->addActivityField($event->getForm(), $projects, $activities, $user);
            }
        );
    }

    /**
     * @param FormInterface|FormBuilderInterface $form
     * @param array<Customer|int|string> $customers
     * @param array<Project|int|string> $projects
     */
    private function addProjectField(FormInterface|FormBuilderInterface $form, array $customers, array $projects, User $user): void
    {
        $projectOptions = [
            'required' => false,
            'multiple' => true,
            'join_customer' => true,
            'user' => $user,
            'activity_enabled' => 'projects[]',
            'activity_select' => 'activities',
            'ignore_date' => true,
            'api_data' => [
                'select' => 'activities',
                'route' => 'get_activities',
                'route_params' => ['projects[]' => '%projects[]%', 'visible' => 1],
                'empty_route_params' => ['visible' => 1],
            ],
        ];

        if (!empty($customers)) {
            $projectOptions['customers'] = $customers;
        }

        if (!empty($projects)) {
            $projectOptions['projects'] = $projects;
        }

        $form->add('projects', ProjectType::class, $projectOptions);
    }

    /**
     * @param FormInterface|FormBuilderInterface $form
     * @param array<Project|int|string> $projects
     * @param array<Activity|int|string> $activities
     */
    private function addActivityField(FormInterface|FormBuilderInterface $form, array $projects, array $activities, User $user): void
    {
        $activityOptions = [
            'required' => false,
            'multiple' => true,
            'user' => $user,
        ];

        if (!empty($projects)) {
            $activityOptions['projects'] = $projects;
            if (!empty($activities)) {
                $activityOptions['activities'] = $activities;
            }
        } else {
            $activityOptions['query_builder'] = function (ActivityRepository $repo) use ($user): QueryBuilder {
                $qb = $repo->createQueryBuilder('a');
                $qb
                    ->addSelect('p')
                    ->addSelect('c')
                    ->leftJoin('a.project', 'p')
                    ->leftJoin('p.customer', 'c')
                    ->where($qb->expr()->eq('a.visible', ':visible'))
                    ->andWhere(
                        $qb->expr()->orX(
                            $qb->expr()->isNull('a.project'),
                            $qb->expr()->andX(
                                $qb->expr()->eq('p.visible', ':is_visible'),
                                $qb->expr()->eq('c.visible', ':is_visible')
                            )
                        )
                    )
                    ->setParameter('visible', true, ParameterType::BOOLEAN)
                    ->setParameter('is_visible', true, ParameterType::BOOLEAN)
                    ->addOrderBy('a.project', 'DESC')
                    ->addOrderBy('a.name', 'ASC');

                if (!$user->canSeeAllData()) {
                    $teams = $user->getTeams();
                    if (empty($teams)) {
                        $qb->andWhere('SIZE(a.teams) = 0')
                            ->andWhere(
                                $qb->expr()->orX(
                                    $qb->expr()->isNull('a.project'),
                                    $qb->expr()->andX('SIZE(p.teams) = 0', 'SIZE(c.teams) = 0')
                                )
                            );
                    } else {
                        $teamIds = array_values(array_unique(array_map(static fn (Team $team) => $team->getId(), $teams)));
                        $qb->andWhere(
                            $qb->expr()->orX(
                                'SIZE(a.teams) = 0',
                                $qb->expr()->isMemberOf(':teams', 'a.teams')
                            )
                        );
                        $qb->andWhere(
                            $qb->expr()->orX(
                                $qb->expr()->isNull('a.project'),
                                $qb->expr()->andX(
                                    $qb->expr()->orX(
                                        'SIZE(p.teams) = 0',
                                        $qb->expr()->isMemberOf(':teams', 'p.teams')
                                    ),
                                    $qb->expr()->orX(
                                        'SIZE(c.teams) = 0',
                                        $qb->expr()->isMemberOf(':teams', 'c.teams')
                                    )
                                )
                            )
                        );
                        $qb->setParameter('teams', $teamIds);
                    }
                }

                return $qb;
            };
        }

        $form->add('activities', ActivityType::class, $activityOptions);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => HappinessReportQuery::class,
            'timezone' => date_default_timezone_get(),
            'user' => null,
            'csrf_protection' => false,
            'method' => 'GET',
        ]);

        $resolver->setRequired(['user']);
        $resolver->setAllowedTypes('user', [User::class]);
    }
}
