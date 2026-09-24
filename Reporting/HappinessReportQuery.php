<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Reporting;

use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\User;
use App\Form\Model\DateRange;

final class HappinessReportQuery
{
    private DateRange $dateRange;
    /** @var Customer[] */
    private array $customers = [];
    /** @var Project[] */
    private array $projects = [];
    /** @var User[] */
    private array $users = [];
    /** @var Activity[] */
    private array $activities = [];

    public function __construct(private readonly User $currentUser)
    {
        $this->dateRange = new DateRange(true);
    }

    public function getCurrentUser(): User
    {
        return $this->currentUser;
    }

    public function getDateRange(): DateRange
    {
        return $this->dateRange;
    }

    public function setDateRange(DateRange $dateRange): self
    {
        $this->dateRange = $dateRange;

        return $this;
    }

    /**
     * @return Customer[]
     */
    public function getCustomers(): array
    {
        return $this->customers;
    }

    /**
     * @param Customer[] $customers
     */
    public function setCustomers(array $customers): self
    {
        $this->customers = $customers;

        return $this;
    }

    /**
     * @return Project[]
     */
    public function getProjects(): array
    {
        return $this->projects;
    }

    /**
     * @param Project[] $projects
     */
    public function setProjects(array $projects): self
    {
        $this->projects = $projects;

        return $this;
    }

    /**
     * @return User[]
     */
    public function getUsers(): array
    {
        return $this->users;
    }

    /**
     * @param User[] $users
     */
    public function setUsers(array $users): self
    {
        $this->users = $users;

        return $this;
    }

    /**
     * @return Activity[]
     */
    public function getActivities(): array
    {
        return $this->activities;
    }

    /**
     * @param Activity[] $activities
     */
    public function setActivities(array $activities): self
    {
        $this->activities = $activities;

        return $this;
    }
}
