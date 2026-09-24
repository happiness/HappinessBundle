<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Form;

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
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<HappinessReportQuery>
 */
final class HappinessReportForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('dateRange', DateRangeType::class, [
            'required' => false,
            'timezone' => $options['timezone'],
            'user' => $options['user'],
        ]);

        $builder->add('customers', CustomerType::class, [
            'required' => false,
            'multiple' => true,
            'user' => $options['user'],
        ]);

        $builder->add('projects', ProjectType::class, [
            'required' => false,
            'multiple' => true,
            'join_customer' => true,
            'user' => $options['user'],
        ]);

        $builder->add('activities', ActivityType::class, [
            'required' => false,
            'multiple' => true,
            'user' => $options['user'],
            'query_builder' => function (ActivityRepository $repo) use ($options): QueryBuilder {
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

                /** @var User|null $user */
                $user = $options['user'] ?? null;
                if ($user !== null && !$user->canSeeAllData()) {
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
            },
        ]);

        $builder->add('users', UserType::class, [
            'required' => false,
            'multiple' => true,
            'user' => $options['user'],
        ]);
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
