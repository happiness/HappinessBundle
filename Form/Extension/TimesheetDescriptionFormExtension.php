<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Form\Extension;

use App\Form\TimesheetEditForm;
use App\Form\Type\DescriptionType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Makes the timesheet description mandatory, as customers complain about invoices with empty descriptions.
 */
final class TimesheetDescriptionFormExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [TimesheetEditForm::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!$builder->has('description')) {
            return;
        }

        $attr = $builder->get('description')->getOption('attr') ?? [];

        $builder->add('description', DescriptionType::class, [
            'required' => true,
            'attr' => $attr,
            'constraints' => [new NotBlank()],
        ]);
    }
}
