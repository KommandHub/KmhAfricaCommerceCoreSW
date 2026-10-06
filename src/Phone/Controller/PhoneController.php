<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Phone\Controller;

use Kommandhub\AfricaCommerceCore\Domain\Feature\Feature;
use Kommandhub\AfricaCommerceCore\Domain\Phone\PhoneNormalizerInterface;
use Kommandhub\AfricaCommerceCore\Feature\FeatureGate;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Store-API endpoint that normalizes a phone number to E.164 with display forms —
 * a published extension point so a storefront can validate a number as the
 * shopper types, before the address is submitted.
 *
 * Warn, don't block: an invalid number still returns 200 with `valid: false` and
 * a warning; the caller decides what to do. Sales-channel aware; 404 when the
 * phone feature is off for the channel.
 */
#[Route(defaults: ['_routeScope' => ['store-api']])]
class PhoneController extends AbstractController
{
    public function __construct(
        private readonly PhoneNormalizerInterface $phoneNormalizer,
        private readonly FeatureGate $featureGate,
    ) {
    }

    #[Route(
        path: '/store-api/kmh-af/phone/normalize',
        name: 'store-api.kmh-af.phone.normalize',
        methods: ['POST'],
    )]
    public function normalize(Request $request, SalesChannelContext $context): JsonResponse
    {
        if (!$this->featureGate->isEnabled(Feature::PhoneNormalization, $context->getSalesChannelId())) {
            return new JsonResponse(['enabled' => false], Response::HTTP_NOT_FOUND);
        }

        $number = (string)$request->request->get('number', '');
        $countryIso = (string)$request->request->get('countryIso', '');

        if ($number === '' || $countryIso === '') {
            return new JsonResponse(
                ['error' => 'number and countryIso are required'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $result = $this->phoneNormalizer->normalize($number, $countryIso);

        return new JsonResponse([
            'e164' => $result->e164,
            'national' => $result->national,
            'international' => $result->international,
            'valid' => $result->valid,
            'warning' => $result->warning,
        ]);
    }
}
