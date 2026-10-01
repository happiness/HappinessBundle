<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\EventSubscriber;

use App\Entity\ProjectMeta;
use App\Event\ProjectMetaDefinitionEvent;
use KimaiPlugin\HappinessBundle\Configuration\RetainerConfiguration;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

final class ProjectMetaSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly RetainerConfiguration $configuration)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProjectMetaDefinitionEvent::class => ['loadMeta', 200],
        ];
    }

    public function loadMeta(ProjectMetaDefinitionEvent $event): void
    {
        $definition = new ProjectMeta();
        $definition
            ->setName(RetainerConfiguration::META_FIELD)
            ->setLabel('happiness.monthly_planning')
            ->setType(CheckboxType::class)
            ->setOptions([
                'required' => false,
                'translation_domain' => 'happiness',
                'label_translation_parameters' => ['%hours%' => $this->formatHours($this->configuration->getHours())],
            ])
            ->setIsVisible(true);

        $event->getEntity()->setMetaField($definition);
    }

    private function formatHours(float $hours): string
    {
        return rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.');
    }
}
