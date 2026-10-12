<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Controller;

use App\Controller\AbstractController;
use KimaiPlugin\HappinessBundle\Repository\ActiveProjectsRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/reporting/active-projects')]
#[IsGranted('view_reporting')]
final class ActiveProjectsController extends AbstractController
{
    #[Route(path: '', name: 'happiness_active_projects', methods: ['GET'])]
    public function __invoke(Request $request, ActiveProjectsRepository $repository): Response
    {
        $weekStart = $this->parseWeek((string) $request->query->get('week', ''));

        return $this->render('@Happiness/reporting/active-projects.html.twig', [
            'report_title' => 'report_active_projects',
            'week_start' => $weekStart,
            'week' => $weekStart->format('o-\\WW'),
            'previous_week' => $weekStart->modify('-7 days')->format('o-\\WW'),
            'next_week' => $weekStart->modify('+7 days')->format('o-\\WW'),
            'is_current_week' => $weekStart == $this->parseWeek(''),
            'sections' => $repository->getRows($weekStart),
            'participant_days' => ActiveProjectsRepository::PARTICIPANT_DAYS,
        ]);
    }

    /**
     * Monday of the ISO week (format 2026-W42) or of the current week if the value is empty or invalid.
     */
    private function parseWeek(string $value): \DateTimeImmutable
    {
        $date = new \DateTimeImmutable();
        if (preg_match('/^(\d{4})-W(0[1-9]|[1-4]\d|5[0-3])$/', $value, $m) === 1) {
            $date = $date->setISODate((int) $m[1], (int) $m[2]);
        }

        return $date->modify('monday this week')->setTime(0, 0);
    }
}
