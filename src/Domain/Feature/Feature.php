<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\Feature;

/**
 * The plugin's toggleable capabilities. Each maps to a bool in config.xml
 * (Features card) and is resolved per sales channel through {@see FeatureGate}.
 *
 * Pure and framework-agnostic: the enum names the feature and its config key;
 * the adapter reads the value.
 */
enum Feature: string
{
    case ReferenceData = 'referenceDataEnabled';
    case AdministrativeHierarchy = 'administrativeHierarchyEnabled';
    case AddressExtensions = 'addressExtensionsEnabled';
    case PhoneNormalization = 'phoneNormalizationEnabled';

    /** SystemConfig key suffix (appended after the plugin config domain). */
    public function configKey(): string
    {
        return $this->value;
    }
}
