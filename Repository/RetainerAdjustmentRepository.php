<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Repository;

use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\HappinessBundle\Entity\RetainerAdjustment;

/**
 * @extends ServiceEntityRepository<RetainerAdjustment>
 */
class RetainerAdjustmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RetainerAdjustment::class);
    }

    /**
     * @return array<string, float> adjustment hours indexed by month (YYYY-MM)
     */
    public function findByProject(Project $project): array
    {
        $result = [];
        foreach ($this->findBy(['project' => $project]) as $adjustment) {
            $result[$adjustment->getMonth()] = $adjustment->getHours();
        }

        return $result;
    }

    /**
     * Sets the adjustment of a month, a value of zero removes it.
     */
    public function setAdjustment(Project $project, string $month, float $hours): void
    {
        $entityManager = $this->getEntityManager();
        $adjustment = $this->findOneBy(['project' => $project, 'month' => $month]);

        if ($hours === 0.0) {
            if ($adjustment !== null) {
                $entityManager->remove($adjustment);
            }
        } elseif ($adjustment !== null) {
            $adjustment->setHours($hours);
        } else {
            $entityManager->persist(new RetainerAdjustment($project, $month, $hours));
        }

        $entityManager->flush();
    }
}
