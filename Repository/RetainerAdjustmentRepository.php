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
     * @return array<string, float> balance carried in, indexed by month (YYYY-MM)
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
     * Replaces the balance carried in to a month, null removes the override (zero is a value like any other).
     */
    public function setAdjustment(Project $project, string $month, ?float $hours): void
    {
        $entityManager = $this->getEntityManager();
        $adjustment = $this->findOneBy(['project' => $project, 'month' => $month]);

        if ($hours === null) {
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
