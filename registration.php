<?php
/**
 * Copyright © Algolia, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Algolia_SearchAdapter',
    __DIR__
);
