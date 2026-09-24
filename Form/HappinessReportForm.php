<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Form;

use App\Entity\User;
use App\Form\Type\ActivityType;
use App\Form\Type\CustomerType;
use App\Form\Type\DateRangeType;
use App\Form\Type\ProjectType;
use App\Form\Type\UserType;
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
