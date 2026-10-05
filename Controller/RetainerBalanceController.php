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
use App\Entity\Project;
use KimaiPlugin\HappinessBundle\Configuration\RetainerBalanceFields;
use KimaiPlugin\HappinessBundle\Repository\RetainerAdjustmentRepository;
use KimaiPlugin\HappinessBundle\Service\RetainerBalanceService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: '/admin/retainer-balance')]
#[IsGranted('ROLE_ADMIN')]
final class RetainerBalanceController extends AbstractController
{
    private const CSRF_ID = 'happiness_retainer_adjustment';

    public function __construct(private readonly RetainerBalanceService $service, private readonly TranslatorInterface $translator)
    {
    }

    #[Route(path: '', name: 'happiness_retainer_balance', methods: ['GET'])]
    public function index(): Response
    {
        $now = $this->getDateTimeFactory()->createDateTime();

        $rows = [];
        foreach ($this->service->findProjects() as $project) {
            $months = $this->service->getMonths($project, $now);
            $rows[] = [
                'project' => $project,
                'hours' => $this->service->getHoursPerMonth($project),
                'start' => $this->service->getStartMonth($project),
                'current' => $months === [] ? null : $months[array_key_last($months)],
            ];
        }

        return $this->render('@Happiness/retainer-balance/index.html.twig', ['rows' => $rows]);
    }

    #[Route(path: '/{id}', name: 'happiness_retainer_balance_project', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function project(Project $project): Response
    {
        $this->assertRetainer($project);

        $months = $this->service->getMonths($project, $this->getDateTimeFactory()->createDateTime());

        return $this->render('@Happiness/retainer-balance/project.html.twig', [
            'project' => $project,
            'hours' => $this->service->getHoursPerMonth($project),
            'start' => $this->service->getStartMonth($project),
            'months' => array_reverse($months),
            'csrf_id' => self::CSRF_ID,
        ]);
    }

    #[Route(path: '/{id}/adjust', name: 'happiness_retainer_balance_adjust', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function adjust(Project $project, Request $request, RetainerAdjustmentRepository $adjustments): Response
    {
        $this->assertRetainer($project);

        if (!$this->isCsrfTokenValid(self::CSRF_ID, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        $month = (string) $request->request->get('month');
        $hours = str_replace(',', '.', trim((string) $request->request->get('hours')));
        $start = $this->service->getStartMonth($project);

        if (preg_match(RetainerBalanceFields::START_PATTERN, $month) !== 1 || $start === null || $month < $start || !is_numeric($hours)) {
            $this->addFlash('error', $this->translator->trans('happiness.retainer_balance.adjust_invalid', [], 'happiness'));
        } else {
            $adjustments->setAdjustment($project, $month, round((float) $hours, 2));
            $this->flashSuccess('action.update.success');
        }

        return $this->redirectToRoute('happiness_retainer_balance_project', ['id' => $project->getId()]);
    }

    private function assertRetainer(Project $project): void
    {
        if (!$this->service->isRetainer($project)) {
            throw $this->createNotFoundException();
        }
    }
}
