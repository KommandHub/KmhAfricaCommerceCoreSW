<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\AdministrativeHierarchy\Controller;

use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionNode;
use Kommandhub\AfricaCommerceCore\Domain\AdministrativeHierarchy\DivisionProviderInterface;
use Kommandhub\AfricaCommerceCore\Domain\Feature\Feature;
use Kommandhub\AfricaCommerceCore\Feature\FeatureGate;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Storefront endpoint feeding the cascading division dropdown: the divisions
 * under the country_state the shopper picked, flattened (with depth) for a
 * <select>. Session-scoped so the storefront JS calls it same-origin.
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
class DivisionStorefrontController extends StorefrontController
{
    public function __construct(
        private readonly DivisionProviderInterface $divisionProvider,
        private readonly FeatureGate $featureGate,
    ) {
    }

    #[Route(
        path: '/kmh-af/divisions/state/{countryStateId}',
        name: 'frontend.kmh-af.divisions.state',
        defaults: ['XmlHttpRequest' => true],
        methods: ['GET'],
    )]
    public function byState(string $countryStateId, SalesChannelContext $context): JsonResponse
    {
        if (!$this->featureGate->isEnabled(Feature::AdministrativeHierarchy, $context->getSalesChannelId())) {
            return new JsonResponse(['divisions' => []]);
        }

        return new JsonResponse(['divisions' => $this->flatten($this->divisionProvider->divisionsForState($countryStateId))]);
    }

    /**
     * @param list<DivisionNode> $nodes
     *
     * @return list<array{code: string, name: string, depth: int}>
     */
    private function flatten(array $nodes, int $depth = 0): array
    {
        $out = [];

        foreach ($nodes as $node) {
            $out[] = ['code' => $node->code, 'name' => str_repeat('— ', $depth) . $node->name, 'depth' => $depth];

            foreach ($this->flatten($node->children, $depth + 1) as $child) {
                $out[] = $child;
            }
        }

        return $out;
    }
}
