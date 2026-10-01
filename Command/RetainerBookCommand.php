<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Command;

use KimaiPlugin\HappinessBundle\Configuration\RetainerConfiguration;
use KimaiPlugin\HappinessBundle\Service\RetainerBookingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Intended to run daily from cron: it only books when today is the configured day of the month.
 */
#[AsCommand(name: 'happiness:retainer:book', description: 'Book the monthly retainer hour on all projects with the retainer checkbox enabled')]
final class RetainerBookCommand extends Command
{
    public function __construct(
        private readonly RetainerBookingService $service,
        private readonly RetainerConfiguration $configuration,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('month', null, InputOption::VALUE_REQUIRED, 'Month to book (YYYY-MM), defaults to the current month')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only show what would be booked')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Ignore the enabled flag and the configured day of month')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');

        if (!$force) {
            if (!$this->configuration->isEnabled()) {
                $io->note('Retainer booking is disabled.');

                return Command::SUCCESS;
            }

            if ((int) (new \DateTimeImmutable())->format('j') !== $this->configuration->getDayOfMonth()) {
                $io->note(\sprintf('Today is not the configured booking day (%d), nothing to do.', $this->configuration->getDayOfMonth()));

                return Command::SUCCESS;
            }
        }

        $monthOption = $input->getOption('month');
        if ($monthOption === null) {
            $month = new \DateTimeImmutable('first day of this month');
        } else {
            $month = \DateTimeImmutable::createFromFormat('!Y-m', (string) $monthOption);
            if ($month === false) {
                $io->error('Invalid --month, expected format YYYY-MM.');

                return Command::INVALID;
            }
        }

        try {
            $results = $this->service->book($month, (bool) $input->getOption('dry-run'));
        } catch (\RuntimeException $ex) {
            $io->error($ex->getMessage());

            return Command::FAILURE;
        }

        $failed = false;
        $rows = [];
        foreach ($results as $result) {
            $failed = $failed || $result['status'] === RetainerBookingService::ERROR;
            $rows[] = [$result['project'], $result['status'], $result['message']];
        }
        if ($rows !== []) {
            $io->table(['Project', 'Status', 'Message'], $rows);
        } else {
            $io->note('No projects have the retainer checkbox enabled.');
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
