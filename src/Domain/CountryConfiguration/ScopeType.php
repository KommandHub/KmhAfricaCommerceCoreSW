<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Domain\CountryConfiguration;

enum ScopeType: string
{
    case Global = 'global';
    case Country = 'country';
    case SalesChannel = 'sales_channel';
}
