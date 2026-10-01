<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Tests;

use App\Configuration\ConfigLoaderInterface;
use App\Configuration\SystemConfiguration;
use App\Entity\Project;
use App\Event\ProjectMetaDefinitionEvent;
use App\Event\SystemConfigurationEvent;
use KimaiPlugin\HappinessBundle\Configuration\RetainerConfiguration;
use KimaiPlugin\HappinessBundle\EventSubscriber\ProjectMetaSubscriber;
use KimaiPlugin\HappinessBundle\EventSubscriber\SystemConfigurationSubscriber;
use PHPUnit\Framework\TestCase;

class RetainerTest extends TestCase
{
    /**
     * @param array<string, mixed> $values
     */
    private function config(array $values): RetainerConfiguration
    {
        $loader = $this->createMock(ConfigLoaderInterface::class);
        $loader->method('getConfigurations')->willReturn([]);

        $settings = [];
        foreach ($values as $key => $value) {
            $settings['happiness.retainer.' . $key] = $value;
        }

        $system = new SystemConfiguration($loader, $settings);

        return new RetainerConfiguration($system);
    }

    public function testDefaults(): void
    {
        $sut = $this->config([]);

        self::assertTrue($sut->isEnabled());
        self::assertSame(1, $sut->getDayOfMonth());
        self::assertSame(1.0, $sut->getHours());
        self::assertSame('happiness', $sut->getUsername());
        self::assertSame(10, $sut->getActivityId());
        self::assertStringStartsWith('Projektledning:', $sut->getDescription());
    }

    public function testDayOfMonthIsCappedAt28(): void
    {
        self::assertSame(28, $this->config(['day_of_month' => 31])->getDayOfMonth());
        self::assertSame(1, $this->config(['day_of_month' => 0])->getDayOfMonth());
        self::assertSame(15, $this->config(['day_of_month' => 15])->getDayOfMonth());
    }

    public function testCustomValues(): void
    {
        $sut = $this->config(['hours' => '2.5', 'user' => ' bob ', 'activity_id' => '42', 'description' => 'foo', 'enabled' => false]);

        self::assertSame(2.5, $sut->getHours());
        self::assertSame('bob', $sut->getUsername());
        self::assertSame(42, $sut->getActivityId());
        self::assertSame('foo', $sut->getDescription());
        self::assertFalse($sut->isEnabled());
    }

    public function testProjectMetaSubscriberAddsCheckbox(): void
    {
        $project = new Project();
        (new ProjectMetaSubscriber($this->config(['hours' => 1.5])))->loadMeta(new ProjectMetaDefinitionEvent($project));

        $field = $project->getMetaField(RetainerConfiguration::META_FIELD);
        self::assertNotNull($field);
        self::assertTrue($field->isVisible());
        self::assertSame('happiness.monthly_planning', $field->getLabel());
        self::assertSame(['%hours%' => '1.5'], $field->getOptions()['label_translation_parameters']);
    }

    public function testSystemConfigurationSubscriberAddsSection(): void
    {
        $event = new SystemConfigurationEvent([]);
        (new SystemConfigurationSubscriber())->onSystemConfiguration($event);

        self::assertCount(1, $event->getConfigurations());
        $names = array_map(static fn ($c) => $c->getName(), $event->getConfigurations()[0]->getConfiguration());
        self::assertSame([
            'happiness.retainer.enabled',
            'happiness.retainer.day_of_month',
            'happiness.retainer.hours',
            'happiness.retainer.description',
            'happiness.retainer.user',
            'happiness.retainer.activity_id',
        ], $names);
    }
}
