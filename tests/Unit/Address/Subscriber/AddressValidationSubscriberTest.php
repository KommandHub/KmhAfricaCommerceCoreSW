<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests\Unit\Address\Subscriber;

use Kommandhub\AfricaCommerceCore\Address\Subscriber\AddressValidationSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Symfony\Component\Validator\Validation;

#[CoversClass(AddressValidationSubscriber::class)]
class AddressValidationSubscriberTest extends TestCase
{
    public function testHooksCoreAddressValidation(): void
    {
        static::assertSame(
            ['framework.validation.address.create', 'framework.validation.address.update'],
            array_keys(AddressValidationSubscriber::getSubscribedEvents()),
        );
    }

    /**
     * Runs before core saves the address, so an over-long value is rejected up
     * front instead of failing after the address is already persisted.
     */
    public function testRejectsOverLongAndNonStringValues(): void
    {
        $definition = new DataValidationDefinition('address.create');
        (new AddressValidationSubscriber())->addConstraints(
            new BuildValidationEvent($definition, new DataBag(), Context::createDefaultContext()),
        );

        $constraints = $definition->getProperties();
        $validator = Validation::createValidator();

        static::assertCount(0, $validator->validate('Near the big tree', $constraints['kmhAfLandmark']));
        static::assertCount(0, $validator->validate(null, $constraints['kmhAfLandmark']));
        static::assertCount(1, $validator->validate(str_repeat('a', 256), $constraints['kmhAfLandmark']));
        static::assertCount(1, $validator->validate(str_repeat('a', 65), $constraints['kmhAfDivisionCode']));
        // An array must be a violation, not an UnexpectedValueException (500).
        static::assertCount(1, $validator->validate(['x'], $constraints['kmhAfArea']));
    }
}
