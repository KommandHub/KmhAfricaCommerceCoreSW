<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Geo;

use Kommandhub\AfricaCommerceCore\Domain\Feature\Feature;
use Kommandhub\AfricaCommerceCore\Domain\Geo\ReconciliationReport;
use Kommandhub\AfricaCommerceCore\Feature\FeatureGate;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Reconciles the versioned African reference dataset into Shopware.
 *
 * Idempotent by construction: run it twice and the second run reports zero
 * created and zero updated. Records a merchant marked as overridden are counted
 * under "overridden" and left untouched.
 */
#[AsCommand(
    name: 'africa:reference:import',
    description: 'Seeds/corrects African countries, ISO 3166-2 subdivisions and ISO 4217 currency precision.',
)]
class ReferenceImportCommand extends Command
{
    public function __construct(
        private readonly ReferenceImporter $importer,
        private readonly FeatureGate $featureGate,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->featureGate->isEnabled(Feature::ReferenceData)) {
            $io->warning('Reference data feature is disabled (Features card). Nothing imported.');

            return Command::SUCCESS;
        }

        $reports = $this->importer->import(Context::createDefaultContext());

        $rows = [];
        foreach ($reports as $section => $report) {
            $rows[] = $this->row($section, $report);
        }

        $io->table(['Dataset', 'Created', 'Updated', 'Up to date', 'Overridden'], $rows);

        $converged = array_reduce(
            $reports,
            static fn (bool $carry, ReconciliationReport $r): bool => $carry && $r->converged(),
            true,
        );

        $io->success($converged
            ? 'Reference data already converged — nothing to change.'
            : 'Reference data reconciled.');

        return Command::SUCCESS;
    }

    /**
     * @return array{0: string, 1: int, 2: int, 3: int, 4: int}
     */
    private function row(string $section, ReconciliationReport $report): array
    {
        return [
            $section,
            $report->created(),
            $report->updated(),
            $report->upToDate(),
            $report->overridden(),
        ];
    }
}
