<?php

declare(strict_types=1);

namespace Kommandhub\AfricaCommerceCore\Tests;

$loader = (new TestBootstrapper())
    ->setPlatformEmbedded(true)
    ->addCallingPlugin()
    ->setForceInstallPlugins(true)
    ->addActivePlugins(
        'KmhAfricaCommerceCoreSW',
    )
    ->bootstrap()
    ->getClassLoader();

$loader->addPsr4('Kommandhub\AfricaCommerceCore\\Tests\\', __DIR__);
