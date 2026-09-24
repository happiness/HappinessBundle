<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\EventSubscriber;

use App\Event\ReportingEvent;
use App\Reporting\Report;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ReportingSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly AuthorizationCheckerInterface $security)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ReportingEvent::class => ['onReporting', 100],
        ];
    }

    public function onReporting(ReportingEvent $event): void
    {
        if (!$this->security->isGranted('view_reporting')) {
            return;
        }

        $event->addReport(new Report(
            'happiness_report',
            'happiness_report',
            'report_happiness',
            'fas fa-smile',
            'happiness'
        ));
    }
}
