<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Command;

use Nowo\PageLayoutKitBundle\Service\PageBlockMigrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command: migrate legacy PageContent JSON into typed page blocks.
 */
#[AsCommand(
    name: 'nowo:page-layout:migrate',
    description: 'Migrate legacy PageContent JSON into typed page blocks',
)]
final class MigratePageBlocksCommand extends Command
{
    public function __construct(
        private readonly PageBlockMigrator $pageBlockMigrator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Replace existing page block layout');
        $this->addOption('if-empty', null, InputOption::VALUE_NONE, 'Skip when layout already exists');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $ifEmpty = (bool) $input->getOption('if-empty');

        if ($ifEmpty && !$force && !$this->pageBlockMigrator->isEmpty()) {
            $symfonyStyle->note('Page blocks layout already exists.');

            return Command::SUCCESS;
        }

        if (!$this->pageBlockMigrator->migrate($force)) {
            $symfonyStyle->warning('Page blocks layout already exists. Use --force to replace.');

            return Command::SUCCESS;
        }

        $symfonyStyle->success('Page blocks migrated from legacy content.');

        return Command::SUCCESS;
    }
}
