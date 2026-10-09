<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Tests\Form\Extension;

use App\Form\TimesheetEditForm;
use App\Form\Type\DescriptionType;
use KimaiPlugin\HappinessBundle\Form\Extension\TimesheetDescriptionFormExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class TimesheetDescriptionFormExtensionTest extends TestCase
{
    public function testGetExtendedTypes(): void
    {
        self::assertEquals([TimesheetEditForm::class], iterator_to_array(TimesheetDescriptionFormExtension::getExtendedTypes()));
    }

    public function testBuildFormDoesNothingWithoutDescriptionField(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->method('has')->with('description')->willReturn(false);
        $builder->expects(self::never())->method('add');

        (new TimesheetDescriptionFormExtension())->buildForm($builder, []);
    }

    public function testBuildFormMakesDescriptionRequiredAndKeepsAttributes(): void
    {
        $field = $this->createMock(FormBuilderInterface::class);
        $field->method('getOption')->with('attr')->willReturn(['autofocus' => 'autofocus']);

        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->method('has')->with('description')->willReturn(true);
        $builder->method('get')->with('description')->willReturn($field);
        $builder->expects(self::once())
            ->method('add')
            ->with(
                'description',
                DescriptionType::class,
                self::callback(static fn (array $o): bool => $o['required'] === true
                    && $o['attr'] === ['autofocus' => 'autofocus']
                    && $o['constraints'][0] instanceof NotBlank)
            )
            ->willReturnSelf();

        (new TimesheetDescriptionFormExtension())->buildForm($builder, []);
    }
}
