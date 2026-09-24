<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Tests\Form\Extension;

use App\Configuration\SystemConfiguration;
use App\Entity\Timesheet;
use App\Form\TimesheetEditForm;
use App\Form\Type\DatePickerType;
use App\Tests\Mocks\SystemConfigurationFactory;
use DateTime;
use KimaiPlugin\HappinessBundle\Form\Extension\TimesheetEditFormExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class TimesheetEditFormExtensionTest extends TestCase
{
    private function createConfiguration(string $modeId): SystemConfiguration
    {
        return SystemConfigurationFactory::createStub(['timesheet' => ['mode' => $modeId]]);
    }

    public function testGetExtendedTypes(): void
    {
        $types = TimesheetEditFormExtension::getExtendedTypes();
        self::assertEquals([TimesheetEditForm::class], iterator_to_array($types));
    }

    public function testBuildFormDoesNothingWhenAllowBeginDatetimeIsTrue(): void
    {
        $configuration = $this->createConfiguration('duration_fixed_begin');

        $extension = new TimesheetEditFormExtension($configuration);
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects(self::never())->method('add');

        $extension->buildForm($builder, [
            'allow_begin_datetime' => true,
            'allow_duration' => true,
        ]);
    }

    public function testBuildFormDoesNothingWhenAllowDurationIsFalse(): void
    {
        $configuration = $this->createConfiguration('duration_fixed_begin');

        $extension = new TimesheetEditFormExtension($configuration);
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects(self::never())->method('add');

        $extension->buildForm($builder, [
            'allow_begin_datetime' => false,
            'allow_duration' => false,
        ]);
    }

    public function testBuildFormDoesNothingWhenTrackingModeIsNotDurationFixedBegin(): void
    {
        $configuration = $this->createConfiguration('punch');

        $extension = new TimesheetEditFormExtension($configuration);
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects(self::never())->method('add');

        $extension->buildForm($builder, [
            'allow_begin_datetime' => false,
            'allow_duration' => true,
        ]);
    }

    public function testBuildFormDoesNothingWhenBeginDateAlreadyExists(): void
    {
        $configuration = $this->createConfiguration('duration_fixed_begin');

        $extension = new TimesheetEditFormExtension($configuration);
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->method('has')->with('begin_date')->willReturn(true);
        $builder->expects(self::never())->method('add');

        $extension->buildForm($builder, [
            'allow_begin_datetime' => false,
            'allow_duration' => true,
        ]);
    }

    public function testBuildFormAddsBeginDateFieldAndListeners(): void
    {
        $configuration = $this->createConfiguration('duration_fixed_begin');

        $extension = new TimesheetEditFormExtension($configuration);

        $timesheet = new Timesheet();
        $begin = new DateTime('2026-09-24 09:15:30');
        $timesheet->setBegin($begin);

        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->method('has')->with('begin_date')->willReturn(false);

        $builder->expects(self::once())
            ->method('add')
            ->with(
                'begin_date',
                DatePickerType::class,
                self::callback(function (array $options) use ($begin) {
                    return $options['label'] === 'date'
                        && $options['mapped'] === false
                        && $options['model_timezone'] === $begin->getTimezone()->getName()
                        && isset($options['constraints'][0])
                        && $options['constraints'][0] instanceof NotBlank;
                })
            )
            ->willReturnSelf();

        $eventListeners = [];
        $builder->expects(self::exactly(2))
            ->method('addEventListener')
            ->willReturnCallback(function (string $eventName, callable $listener, int $priority = 0) use (&$eventListeners, $builder) {
                $eventListeners[$eventName] = [
                    'listener' => $listener,
                    'priority' => $priority,
                ];

                return $builder;
            });

        $extension->buildForm($builder, [
            'allow_begin_datetime' => false,
            'allow_duration' => true,
            'data' => $timesheet,
            'timezone' => 'UTC',
        ]);

        self::assertArrayHasKey(FormEvents::POST_SET_DATA, $eventListeners);
        self::assertArrayHasKey(FormEvents::SUBMIT, $eventListeners);
        self::assertEquals(10, $eventListeners[FormEvents::SUBMIT]['priority']);

        // Test POST_SET_DATA
        $beginDateField = $this->createMock(FormInterface::class);
        $beginDateField->expects(self::once())->method('setData')->with($begin);

        $form = $this->createMock(FormInterface::class);
        $form->method('has')->with('begin_date')->willReturn(true);
        $form->method('get')->with('begin_date')->willReturn($beginDateField);

        $postSetDataEvent = new FormEvent($form, $timesheet);
        $eventListeners[FormEvents::POST_SET_DATA]['listener']($postSetDataEvent);

        // Test SUBMIT with changed date
        $submittedDate = new DateTime('2026-09-20 00:00:00');
        $beginDateFieldSubmit = $this->createMock(FormInterface::class);
        $beginDateFieldSubmit->method('getData')->willReturn($submittedDate);

        $submitForm = $this->createMock(FormInterface::class);
        $submitForm->method('has')->with('begin_date')->willReturn(true);
        $submitForm->method('get')->with('begin_date')->willReturn($beginDateFieldSubmit);

        $timesheetToSubmit = new Timesheet();
        $timesheetToSubmit->setBegin(new DateTime('2026-09-24 09:15:30'));
        $timesheetToSubmit->setEnd(new DateTime('2026-09-24 10:15:30'));
        $timesheetToSubmit->setDuration(3600);

        $submitEvent = new FormEvent($submitForm, $timesheetToSubmit);
        $eventListeners[FormEvents::SUBMIT]['listener']($submitEvent);

        self::assertNotNull($timesheetToSubmit->getBegin());
        self::assertEquals('2026-09-20 09:15:30', $timesheetToSubmit->getBegin()->format('Y-m-d H:i:s'));
    }
}
