<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\Controller;

use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionNode;
use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionProviderInterface;
use Kommandhub\AfricaCommerceCore\Domain\Feature\Feature;
use Kommandhub\AfricaCommerceCore\Feature\FeatureGate;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Store-API endpoint returning the administrative-division tree for a country —
 * a published extension point for storefronts (cascading division dropdowns).
 *
 * Sales-channel aware: returns an empty tree when the administrative-hierarchy
 * feature is disabled for the channel. Reads through DivisionProviderInterface,
 * so swapping the provider changes the source without touching this route.
 */
#[Route(defaults: ['_routeScope' => ['store-api']])]
class DivisionController extends AbstractController
{
    public function __construct(
        private readonly DivisionProviderInterface $divisionProvider,
        private readonly FeatureGate $featureGate,
    ) {
    }

    #[Route(
        path: '/store-api/kmh-af/divisions/{countryIso}',
        name: 'store-api.kmh-af.divisions',
        methods: ['GET'],
    )]
    public function list(string $countryIso, SalesChannelContext $context): JsonResponse
    {
        if (!$this->featureGate->isEnabled(Feature::AdministrativeHierarchy, $context->getSalesChannelId())) {
            return new JsonResponse(['countryIso' => strtoupper($countryIso), 'maxDepth' => 0, 'divisions' => []]);
        }

        $tree = $this->divisionProvider->divisionsFor($countryIso);

        return new JsonResponse([
            'countryIso' => strtoupper($countryIso),
            // From the tree already built — no second provider query.
            'maxDepth' => DivisionNode::depthOf($tree),
            'divisions' => array_map([$this, 'nodeToArray'], $tree),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function nodeToArray(DivisionNode $node): array
    {
        return [
            'code' => $node->code,
            'name' => $node->name,
            'parentCode' => $node->parentCode,
            'children' => array_map([$this, 'nodeToArray'], $node->children),
        ];
    }
}
