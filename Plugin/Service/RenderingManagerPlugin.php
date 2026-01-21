<?php

namespace Algolia\SearchAdapter\Plugin\Service;

use Algolia\AlgoliaSearch\Service\RenderingManager;
use Algolia\SearchAdapter\Service\BackendRenderingResolver;

class RenderingManagerPlugin
{
    public function __construct(
        protected BackendRenderingResolver $backendRenderingResolver,
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

        return $this->backendRenderingResolver->shouldPreventRendering($storeId);
    }
}
