<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Phone\Controller;

use Kommandhub\AfricaCommerceCore\Domain\Feature\Feature;
use Kommandhub\AfricaCommerceCore\Domain\Phone\PhoneNormalizerInterface;
use Kommandhub\AfricaCommerceCore\Feature\FeatureGate;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Storefront endpoint for live phone feedback as the shopper types: normalizes
 * to E.164 and returns display forms + validity. Warn, don't block.
 *
 * Takes the form's countryId and resolves the ISO alpha-2 server-side, so the
 * JS needs nothing but the selected country value. Session-scoped.
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
class PhoneStorefrontController extends StorefrontController
{
    public function __construct(
        private readonly PhoneNormalizerInterface $phoneNormalizer,
        private readonly EntityRepository $countryRepository,
        private readonly FeatureGate $featureGate,
    ) {
    }

    #[Route(
        path: '/kmh-af/phone/normalize',
        name: 'frontend.kmh-af.phone.normalize',
        defaults: ['XmlHttpRequest' => true],
        methods: ['POST'],
    )]
    public function normalize(Request $request, SalesChannelContext $context): JsonResponse
    {
        if (!$this->featureGate->isEnabled(Feature::PhoneNormalization, $context->getSalesChannelId())) {
            return new JsonResponse(['enabled' => false]);
        }

        $number = (string) $request->request->get('number', '');
        $countryId = (string) $request->request->get('countryId', '');

        $iso = $countryId !== '' ? $this->resolveIso($countryId, $context->getContext()) : null;

        if ($number === '' || $iso === null) {
            return new JsonResponse(['valid' => false, 'warning' => null, 'e164' => '', 'national' => '', 'international' => '']);
        }

        $result = $this->phoneNormalizer->normalize($number, $iso);

        return new JsonResponse([
            'e164' => $result->e164,
            'national' => $result->national,
            'international' => $result->international,
            'valid' => $result->valid,
            'warning' => $result->warning,
        ]);
    }

    private function resolveIso(string $countryId, Context $context): ?string
    {
        $country = $this->countryRepository->search(new Criteria([$countryId]), $context)->first();

        return $country instanceof CountryEntity ? $country->getIso() : null;
    }
}
