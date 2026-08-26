<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\Geo;

/**
 * Where a reference dataset came from and which version it is.
 *
 * Every seed/import carries provenance so a merchant (and an audit) can see that
 * a record was set from a tracked standard at a known version, not invented.
 */
final class Provenance
{
    public function __construct(
        public readonly string $source,
        public readonly string $standard,
        public readonly string $version,
        public readonly string $retrievedAt,
    ) {
    }
}
