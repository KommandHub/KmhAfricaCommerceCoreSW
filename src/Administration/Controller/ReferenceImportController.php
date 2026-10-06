<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Administration\Controller;

use Kommandhub\AfricaCommerceCore\Domain\Feature\Feature;
use Kommandhub\AfricaCommerceCore\Feature\FeatureGate;
use Kommandhub\AfricaCommerceCore\Geo\ReferenceImporter;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin API action behind the "Run import" button in the plugin config, so a
 * non-technical merchant can seed/correct reference data without the CLI.
 *
 * Runs the same {@see ReferenceImporter} as the console command and returns the
 * per-dataset reconcile counts (created / updated / up to date / overridden) for
 * the component to display. It rewrites core reference data (countries, states,
 * currency precision), so it needs the same privilege as plugin configuration,
 * and honours the Reference-data toggle exactly like the CLI.
 */
#[Route(defaults: ['_routeScope' => ['api']])]
class ReferenceImportController extends AbstractController
{
    public const ERROR_DISABLED = 'KMH_AF__REFERENCE_DATA_DISABLED';

    public function __construct(
        private readonly ReferenceImporter $importer,
        private readonly FeatureGate $featureGate,
    ) {
    }

    #[Route(
        path: '/api/_action/kmh-af/reference-import',
        name: 'api.action.kmh-af.reference-import',
        defaults: ['_acl' => ['system.plugin_maintain']],
        methods: ['POST'],
    )]
    public function import(Context $context): JsonResponse
    {
        if (!$this->featureGate->isEnabled(Feature::ReferenceData)) {
            return new JsonResponse(['errors' => [[
                'code' => self::ERROR_DISABLED,
                'status' => (string)Response::HTTP_CONFLICT,
                'detail' => 'The reference-data feature is disabled.',
            ]]], Response::HTTP_CONFLICT);
        }

        $sections = [];

        foreach ($this->importer->import($context) as $section => $report) {
            $sections[$section] = $report->toArray();
        }

        return new JsonResponse(['sections' => $sections]);
    }
}
