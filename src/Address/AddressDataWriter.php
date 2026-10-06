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
 * an existing row is updated, a blank submission with no existing row is skipped,
 * and a key missing from the databag leaves its stored value untouched.
 * The submitted division *code* is resolved to the division FK.
 */
final class AddressDataWriter
{
    public function __construct(
        private readonly EntityRepository $addressDataRepository,
        private readonly EntityRepository $administrativeDivisionRepository,
    ) {
    }

    /** Databag key => aggregate field. */
    private const FIELDS = [
        'kmhAfLandmark' => 'landmark',
        'kmhAfArea' => 'area',
        'kmhAfDirections' => 'directions',
        'kmhAfDigitalAddressCode' => 'digitalAddressCode',
        'kmhAfDivisionCode' => 'divisionId',
    ];

    public function write(string $addressId, RequestDataBag $addressBag, Context $context): void
    {
        // Only keys actually submitted are written. An absent key means "not
        // part of this form/client" (a headless PATCH, a disabled division
        // select) and must keep the stored value; an empty one clears it.
        $fields = [];

        foreach (self::FIELDS as $key => $field) {
            if (!$addressBag->has($key)) {
                continue;
            }

            $value = $this->clean($addressBag->get($key));
            $fields[$field] = $field === 'divisionId' && $value !== null
                ? $this->resolveDivisionId($value, $context)
                : $value;
        }

        if ($fields === []) {
            return;
        }

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
