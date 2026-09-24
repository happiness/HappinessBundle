<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Form\Extension;

use App\Configuration\SystemConfiguration;
use App\Entity\Timesheet;
use App\Form\TimesheetEditForm;
use App\Form\Type\DatePickerType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\NotBlank;

final class TimesheetEditFormExtension extends AbstractTypeExtension
{
    public function __construct(private readonly SystemConfiguration $configuration)
    {
    }

    public static function getExtendedTypes(): iterable
    {
        return [TimesheetEditForm::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['allow_begin_datetime']) {
            return;
        }

        if (!$options['allow_duration']) {
            return;
        }

        if ($this->configuration->getTimesheetTrackingMode() !== 'duration_fixed_begin') {
            return;
        }

        if ($builder->has('begin_date')) {
            return;
        }

        $timezone = $options['timezone'] ?? date_default_timezone_get();
        if (isset($options['data']) && $options['data'] instanceof Timesheet) {
            if (null !== ($begin = $options['data']->getBegin())) {
                $timezone = $begin->getTimezone()->getName();
            }
        }

        $dateTimeOptions = [
            'model_timezone' => $timezone,
            'view_timezone' => $timezone,
        ];

        if (isset($options['date_format'])) {
            $dateTimeOptions['format'] = $options['date_format'];
        }

        $builder->add('begin_date', DatePickerType::class, array_merge($dateTimeOptions, [
            'label' => 'date',
            'mapped' => false,
            'constraints' => [
                new NotBlank(),
            ],
        ]));

        $builder->addEventListener(
            FormEvents::POST_SET_DATA,
            function (FormEvent $event): void {
                /** @var Timesheet|null $timesheet */
                $timesheet = $event->getData();
                if ($timesheet === null) {
                    return;
                }

                $begin = $timesheet->getBegin();
                if (null !== $begin && $event->getForm()->has('begin_date')) {
                    $event->getForm()->get('begin_date')->setData($begin);
                }
            }
        );

        $builder->addEventListener(
            FormEvents::SUBMIT,
            function (FormEvent $event): void {
                /** @var Timesheet|null $data */
                $data = $event->getData();
                if ($data === null) {
                    return;
                }

                if (!$event->getForm()->has('begin_date')) {
                    return;
                }

                /** @var \DateTime|null $date */
                $date = $event->getForm()->get('begin_date')->getData();
                if ($date === null) {
                    return;
                }

                $begin = $data->getBegin();
                $newDate = clone $date;

                if ($begin !== null) {
                    $newDate->setTime((int) $begin->format('H'), (int) $begin->format('i'), (int) $begin->format('s'));
                } else {
                    $newDate->setTime(0, 0, 0);
                }

                if ($data->getEnd() !== null && ($data->getDuration() === null || $data->getDuration() === 0) && $begin !== null) {
                    $diff = $data->getEnd()->getTimestamp() - $begin->getTimestamp();
                    $newEnd = clone $newDate;
                    $newEnd->modify('+ ' . $diff . ' seconds');
                    $data->setEnd($newEnd);
                }

                if ($data->getBegin() === null || $data->getBegin()->getTimestamp() !== $newDate->getTimestamp()) {
                    $data->setBegin($newDate);
                }
            },
            10
        );
    }
}
