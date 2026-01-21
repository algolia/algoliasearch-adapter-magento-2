<?php

namespace Algolia\SearchAdapter\Plugin\Service;

use Algolia\AlgoliaSearch\Service\RenderingManager;
use Algolia\SearchAdapter\Helper\ConfigHelper;

class RenderingManagerPlugin
{
    public function __construct(
        protected ConfigHelper $configHelper,
    ) {}

    public function afterShouldPreventBackendRendering(
        RenderingManager $subject,
        bool $result,
        string $actionName,
        int $storeId): bool
    {
        // If core already says "don't prevent", respect that
        if (!$result) {
            return false;
        }

        return $this->configHelper->shouldPreventBackendRendering($storeId);
    }
}
