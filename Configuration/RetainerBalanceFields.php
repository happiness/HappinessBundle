<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Configuration;

/**
 * Project meta fields for retainer projects with an hour balance.
 *
 * Independent of the monthly planning hour, see {@see RetainerConfiguration::META_FIELD}.
 */
final class RetainerBalanceFields
{
    public const ENABLED = 'happiness_retainer';
    public const HOURS = 'happiness_retainer_hours';
    public const START = 'happiness_retainer_start';
    public const TEXT = 'happiness_retainer_text';

    public const START_PATTERN = '/^\d{4}-(0[1-9]|1[0-2])$/';
}
