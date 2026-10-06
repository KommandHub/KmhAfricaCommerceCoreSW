<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Address\Subscriber;

use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;

/**
 * Validates the additive address fields inside core's own address validation.
 *
 * The address decorators write the aggregate *after* core has saved the address,
 * so a value the DAL would reject (too long, not a string) must be caught before
 * that save — otherwise the shopper sees an error while the address (or, at
 * registration, the whole customer) is already persisted. Core dispatches these
 * events for account address create/update and for each registration address.
 */
class AddressValidationSubscriber implements EventSubscriberInterface
{
    /** Databag key => max length (matches the aggregate's column sizes). */
    private const MAX_LENGTH = [
        'kmhAfLandmark' => 255,
        'kmhAfArea' => 255,
        'kmhAfDigitalAddressCode' => 255,
        'kmhAfDivisionCode' => 64,
        'kmhAfDirections' => 65535,
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            'framework.validation.address.create' => 'addConstraints',
            'framework.validation.address.update' => 'addConstraints',
        ];
    }

    public function addConstraints(BuildValidationEvent $event): void
    {
        $definition = $event->getDefinition();

        foreach (self::MAX_LENGTH as $field => $max) {
            // Sequentially: a non-string must fail on Type, never reach Length
            // (which throws on non-scalars instead of reporting a violation).
            $definition->add($field, new Sequentially([new Type('string'), new Length(max: $max)]));
        }
    }
}
