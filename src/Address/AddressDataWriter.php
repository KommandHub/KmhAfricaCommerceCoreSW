<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Address;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

/**
 * Writes the additive African address fields from a submitted address databag
 * into the kmh_af_customer_address_data 1:1 aggregate.
 *
 * Shared by every route that saves an address (account upsert, registration), so
 * the mapping lives in one place. Idempotent per address (unique customer_address_id):
 * an existing row is updated, a blank submission with no existing row is skipped.
 * The submitted division *code* is resolved to the division FK.
 */
final class AddressDataWriter
{
    public function __construct(
        private readonly EntityRepository $addressDataRepository,
        private readonly EntityRepository $administrativeDivisionRepository,
    ) {
    }

    public function write(string $addressId, RequestDataBag $addressBag, Context $context): void
    {
        $divisionCode = $this->clean($addressBag->get('kmhAfDivisionCode'));

        $fields = [
            'landmark' => $this->clean($addressBag->get('kmhAfLandmark')),
            'area' => $this->clean($addressBag->get('kmhAfArea')),
            'directions' => $this->clean($addressBag->get('kmhAfDirections')),
            'digitalAddressCode' => $this->clean($addressBag->get('kmhAfDigitalAddressCode')),
            'divisionId' => $divisionCode !== null ? $this->resolveDivisionId($divisionCode, $context) : null,
        ];

        $existingId = $this->existingId($addressId, $context);

        // Nothing entered and no row to update — do not create an empty aggregate.
        if ($existingId === null && array_filter($fields, static fn ($v): bool => $v !== null) === []) {
            return;
        }

        $this->addressDataRepository->upsert([
            ['id' => $existingId ?? Uuid::randomHex(), 'customerAddressId' => $addressId] + $fields,
        ], $context);
    }

    private function resolveDivisionId(string $code, Context $context): ?string
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('code', strtoupper($code)))->setLimit(1);

        return $this->administrativeDivisionRepository->searchIds($criteria, $context)->firstId();
    }

    private function existingId(string $addressId, Context $context): ?string
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('customerAddressId', $addressId))->setLimit(1);

        return $this->addressDataRepository->searchIds($criteria, $context)->firstId();
    }

    private function clean(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
