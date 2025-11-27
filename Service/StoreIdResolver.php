<?php

namespace Algolia\SearchAdapter\Service;

use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Search\RequestInterface;

/**
 * Service to resolve the store ID from a search request
 */
class StoreIdResolver
{
    public function __construct(
        protected ScopeResolverInterface $scopeResolver,
    ) {}

    /**
     * Get the store ID from the search request
     */
    public function getStoreId(RequestInterface $request): int
    {
        $dimension = current($request->getDimensions());
        return $this->scopeResolver->getScope($dimension->getValue())->getId();
    }
}

