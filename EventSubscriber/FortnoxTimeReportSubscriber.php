<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\EventSubscriber;

use KimaiPlugin\FortnoxBundle\Event\TimeReportSummaryEvent;
use KimaiPlugin\HappinessBundle\Service\RetainerBalanceService;
use KimaiPlugin\HappinessBundle\Service\RetainerSummaryFormatter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds the retainer balance as the first row of the Fortnox time report, if the FortnoxBundle is installed.
 *
 * The balance is monthly, so the row is only added when the report covers a single calendar month.
 */
final class FortnoxTimeReportSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RetainerBalanceService $balances,
        private readonly RetainerSummaryFormatter $formatter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TimeReportSummaryEvent::class => 'onSummary',
        ];
    }

    public function onSummary(TimeReportSummaryEvent $event): void
    {
        $project = $event->getProject();
        $month = $event->getBegin()->format('Y-m');

        if ($month !== $event->getEnd()->format('Y-m') || !$this->balances->isRetainer($project)) {
            return;
        }

        $months = $this->balances->getMonths($project, $event->getBegin());
        foreach ($this->formatter->lines($months, $month, $this->balances->getText($project)) as $line) {
            $event->addLine($line);
        }
    }
}
