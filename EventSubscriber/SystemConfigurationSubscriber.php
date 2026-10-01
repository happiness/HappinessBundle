<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\EventSubscriber;

use App\Event\SystemConfigurationEvent;
use App\Form\Model\Configuration;
use App\Form\Model\SystemConfiguration;
use App\Form\Type\YesNoType;
use KimaiPlugin\HappinessBundle\Configuration\RetainerConfiguration;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

final class SystemConfigurationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            SystemConfigurationEvent::class => ['onSystemConfiguration', 100],
        ];
    }

    public function onSystemConfiguration(SystemConfigurationEvent $event): void
    {
        $event->addConfiguration(
            (new SystemConfiguration('happiness_retainer'))
                ->setTranslation('happiness.retainer.title')
                ->setTranslationDomain('happiness')
                ->setConfiguration([
                    $this->create('enabled', YesNoType::class),
                    $this->create('day_of_month', IntegerType::class, [
                        new Range(min: 1, max: RetainerConfiguration::MAX_DAY),
                    ]),
                    $this->create('hours', NumberType::class, [
                        new Range(min: 0.01, max: 24),
                    ]),
                    $this->create('description', TextType::class),
                    $this->create('user', TextType::class),
                    $this->create('activity_id', IntegerType::class, [
                        new GreaterThan(0),
                    ]),
                ])
        );
    }

    /**
     * @param array<\Symfony\Component\Validator\Constraint> $constraints
     */
    private function create(string $name, string $type, array $constraints = []): Configuration
    {
        return (new Configuration('happiness.retainer.' . $name))
            ->setLabel('happiness.retainer.' . $name)
            ->setTranslationDomain('happiness')
            ->setType($type)
            ->setConstraints(array_merge([new NotBlank()], $constraints));
    }
}
