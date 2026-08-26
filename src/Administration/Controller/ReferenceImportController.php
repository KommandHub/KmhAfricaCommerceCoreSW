<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Administration\Controller;

use Kommandhub\AfricaCommerceCore\Geo\ReferenceImporter;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin API action behind the "Run import" button in the plugin config, so a
 * non-technical merchant can seed/correct reference data without the CLI.
 *
 * Runs the same {@see ReferenceImporter} as the console command and returns the
 * per-dataset reconcile counts (created / updated / up to date / overridden) for
 * the component to display. Admin-scoped: requires an authenticated admin.
 */
#[Route(defaults: ['_routeScope' => ['api']])]
class ReferenceImportController extends AbstractController
{
    public function __construct(private readonly ReferenceImporter $importer)
    {
    }

    #[Route(
        path: '/api/_action/kmh-af/reference-import',
        name: 'api.action.kmh-af.reference-import',
        methods: ['POST'],
    )]
    public function import(Context $context): JsonResponse
    {
        $sections = [];
        foreach ($this->importer->import($context) as $section => $report) {
            $sections[$section] = $report->toArray();
        }

        return new JsonResponse(['sections' => $sections]);
    }
}
