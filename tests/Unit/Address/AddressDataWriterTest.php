<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit\Address;

use Kommandhub\AfricaCommerceCore\Address\AddressDataWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

#[CoversClass(AddressDataWriter::class)]
class AddressDataWriterTest extends TestCase
{
    private const ADDRESS_ID = '0190a0b0c0d0e0f00010203040506070';
    private const ROW_ID = '0190a0b0c0d0e0f00010203040506071';
    private const DIVISION_ID = '0190a0b0c0d0e0f00010203040506072';
    private const STATE_ID = '0190a0b0c0d0e0f00010203040506073';

    private EntityRepository&MockObject $addressData;
    private EntityRepository&MockObject $divisions;

    protected function setUp(): void
    {
        $this->addressData = $this->createMock(EntityRepository::class);
        $this->divisions = $this->createMock(EntityRepository::class);
    }

    /**
     * A client that does not know the additive fields (headless PATCH, a form
     * without the division select) must not wipe what is stored.
     */
    public function testAbsentKeysLeaveStoredDataUntouched(): void
    {
        $this->addressData->expects(static::never())->method('upsert');

        $this->write(new RequestDataBag(['street' => '2 Changed St']));
    }

    public function testOnlySubmittedKeysAreWritten(): void
    {
        $this->existingRow(self::ROW_ID);

        $this->addressData->expects(static::once())->method('upsert')->with([[
            'id' => self::ROW_ID,
            'customerAddressId' => self::ADDRESS_ID,
            'landmark' => 'Near the big tree',
        ]]);

        $this->write(new RequestDataBag(['kmhAfLandmark' => '  Near the big tree ']));
    }

    public function testEmptySubmittedValueClearsTheField(): void
    {
        $this->existingRow(self::ROW_ID);

        $this->addressData->expects(static::once())->method('upsert')->with([[
            'id' => self::ROW_ID,
            'customerAddressId' => self::ADDRESS_ID,
            'divisionId' => null,
        ]]);

        $this->write(new RequestDataBag(['kmhAfDivisionCode' => '']));
    }

    public function testBlankSubmissionWithoutRowCreatesNothing(): void
    {
        $this->existingRow(null);
        $this->addressData->expects(static::never())->method('upsert');

        $this->write(new RequestDataBag(['kmhAfLandmark' => ' ', 'kmhAfArea' => '']));
    }

    public function testDivisionCodeResolvesToId(): void
    {
        $this->existingRow(null);
        $this->divisions->method('searchIds')->willReturn($this->ids(self::DIVISION_ID));

        $this->addressData->expects(static::once())->method('upsert')->with(static::callback(
            static fn (array $payload): bool => $payload[0]['divisionId'] === self::DIVISION_ID
                && $payload[0]['customerAddressId'] === self::ADDRESS_ID,
        ));

        $this->write(new RequestDataBag(['kmhAfDivisionCode' => 'ng-la-ikeja', 'countryStateId' => self::STATE_ID]));
    }

    /**
     * The lookup is scoped to the address's state; with no state there is
     * nothing to scope to, so no division is attached and none is looked up.
     */
    public function testDivisionNeedsTheAddressState(): void
    {
        $this->existingRow(self::ROW_ID);
        $this->divisions->expects(static::never())->method('searchIds');

        $this->addressData->expects(static::once())->method('upsert')->with(static::callback(
            static fn (array $payload): bool => $payload[0]['divisionId'] === null,
        ));

        $this->write(new RequestDataBag(['kmhAfDivisionCode' => 'NG-LA-IKEJA']));
    }

    public function testDivisionLookupFiltersByState(): void
    {
        $this->existingRow(null);

        $this->divisions->expects(static::once())->method('searchIds')->with(static::callback(
            static function (Criteria $criteria): bool {
                $filters = array_map(
                    static fn ($f): array => [$f->getField(), $f->getValue()],
                    $criteria->getFilters(),
                );

                return \in_array(['countryStateId', self::STATE_ID], $filters, true)
                    && \in_array(['code', 'NG-LA-IKEJA'], $filters, true);
            },
        ))->willReturn($this->ids(null));

        // Unknown in that state -> no division, nothing else entered -> no row.
        $this->addressData->expects(static::never())->method('upsert');

        $this->write(new RequestDataBag(['kmhAfDivisionCode' => 'ng-la-ikeja', 'countryStateId' => self::STATE_ID]));
    }

    private function write(RequestDataBag $bag): void
    {
        (new AddressDataWriter($this->addressData, $this->divisions))
            ->write(self::ADDRESS_ID, $bag, Context::createDefaultContext());
    }

    private function existingRow(?string $id): void
    {
        $this->addressData->method('searchIds')->willReturn($this->ids($id));
    }

    private function ids(?string $id): IdSearchResult
    {
        $data = $id === null ? [] : [$id => ['primaryKey' => $id, 'data' => []]];

        return new IdSearchResult(\count($data), $data, new Criteria(), Context::createDefaultContext());
    }
}
