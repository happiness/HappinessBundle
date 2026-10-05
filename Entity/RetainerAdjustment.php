<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Entity;

use App\Entity\Project;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\HappinessBundle\Repository\RetainerAdjustmentRepository;

/**
 * A manual change (in hours, positive or negative) of the retainer balance in one month.
 */
#[ORM\Table(name: 'kimai2_happiness_retainer_adjustments')]
#[ORM\UniqueConstraint(name: 'UNIQ_HAPPINESS_RETAINER_ADJ', columns: ['project_id', 'month'])]
#[ORM\Entity(repositoryClass: RetainerAdjustmentRepository::class)]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class RetainerAdjustment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Project::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Project $project,
        /** Month in the format YYYY-MM */
        #[ORM\Column(name: 'month', type: Types::STRING, length: 7, nullable: false)]
        private string $month,
        #[ORM\Column(name: 'hours', type: Types::FLOAT, nullable: false)]
        private float $hours = 0.0,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getMonth(): string
    {
        return $this->month;
    }

    public function getHours(): float
    {
        return $this->hours;
    }

    public function setHours(float $hours): void
    {
        $this->hours = $hours;
    }
}
