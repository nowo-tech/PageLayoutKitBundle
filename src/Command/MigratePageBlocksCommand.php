<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Command;

use Nowo\PageLayoutKitBundle\Service\PageBlockMigrator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Console command: migrate legacy PageContent JSON into typed page blocks.
 */
#[AsCommand(
    name: 'nowo:page-layout:migrate',
    description: 'Migrate legacy PageContent JSON into typed page blocks',
)]
final class MigratePageBlocksCommand extends Command
{
    /**
     * @param LoggerInterface|null $logger Logs start / result / failure of this destructive migration (REQ-OBS-001)
     */
    public function __construct(
        private readonly PageBlockMigrator $pageBlockMigrator,
        private readonly ?LoggerInterface $logger = null,
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
        $force        = (bool) $input->getOption('force');
        $ifEmpty      = (bool) $input->getOption('if-empty');

        $context = ['bundle' => 'page_layout_kit', 'action' => 'migrate_page_blocks', 'force' => $force, 'if_empty' => $ifEmpty];
        $this->logger?->info('Page block migration started.', $context);

        if ($ifEmpty && !$force && !$this->pageBlockMigrator->isEmpty()) {
            $symfonyStyle->note('Page blocks layout already exists.');
            $this->logger?->info('Page block migration skipped: layout already exists.', $context);

            return Command::SUCCESS;
        }

        try {
            $migrated = $this->pageBlockMigrator->migrate($force);
        } catch (Throwable $e) {
            $this->logger?->error('Page block migration failed.', $context + ['exception' => $e::class]);

            throw $e;
        }

        if (!$migrated) {
            $symfonyStyle->warning('Page blocks layout already exists. Use --force to replace.');
            $this->logger?->warning('Page block migration not run: layout exists and --force was not given.', $context);

            return Command::SUCCESS;
        }

        $symfonyStyle->success('Page blocks migrated from legacy content.');
        $this->logger?->info('Page block migration finished.', $context);

        return Command::SUCCESS;
    }
}
