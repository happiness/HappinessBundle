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
use KimaiPlugin\HappinessBundle\Form\HappinessReportForm;
use KimaiPlugin\HappinessBundle\Reporting\HappinessReportQuery;
use KimaiPlugin\HappinessBundle\Repository\HappinessReportRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/reporting/happiness')]
#[IsGranted('view_reporting')]
final class HappinessReportController extends AbstractController
{
    #[Route(path: '', name: 'happiness_report', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        HappinessReportRepository $reportRepository
    ): Response {
        $user = $this->getUser();
        $query = new HappinessReportQuery($user);

        $dateFactory = $this->getDateTimeFactory();
        $query->getDateRange()->setBegin($dateFactory->getStartOfMonth());
        $query->getDateRange()->setEnd($dateFactory->getEndOfMonth($query->getDateRange()->getBegin()));

        $form = $this->createFormForGetRequest(HappinessReportForm::class, $query, [
            'timezone' => $user->getTimezone(),
            'user' => $user,
        ]);
        $form->submit($request->query->all(), false);

        $reportData = $reportRepository->getGroupedByUserAndActivity($query);

        return $this->render('@Happiness/reporting/happiness.html.twig', [
            'report_title' => 'report_happiness',
            'form' => $form->createView(),
            'query' => $query,
            'reportData' => $reportData,
        ]);
    }
}
