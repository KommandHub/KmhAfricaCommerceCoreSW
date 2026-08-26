<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\Geo;

/**
 * Supplies the reference dataset to reconcile. Published so a third party can
 * ship an alternative or extended dataset without patching the foundation.
 */
interface ReferenceDataProviderInterface
{
    public function load(): ReferenceDataSet;
}
