<?php

namespace Algolia\SearchAdapter\Plugin;

use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\SearchAdapter\Service\BackendRenderingResolver;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;

/**
 * The purpose of this class is to render different cached versions of the pages according to the user agent.
 * If the "prevent backend rendering" configuration is turned on, we don't want to render the results on the backend
 * side only for humans, but we want to do it for the robots (configured in the "advanced" section of the extension).
 * So with this plugin, two versions of the pages are cached : one for humans, and one for robots.
 */
class RenderingCacheContextPlugin
{
    public const RENDERING_CONTEXT = 'algolia_rendering_context';
    public const RENDERING_WITH_BACKEND = 'with_backend';
    public const RENDERING_WITHOUT_BACKEND = 'without_backend';

    public function __construct(
        protected BackendRenderingResolver $backendRenderingResolver,
        protected InstantSearchHelper      $isConfig,
        protected StoreManagerInterface    $storeManager
    ) {}

    /**
     * Add rendering context to the vary string data to distinguish which versions of the category PLP should be cached
     * (If the "prevent backend rendering" configuration is enabled and the user agent is not whitelisted to display it,
     * we set a different page variation, and the FPC stores a different cached page)
     *
     * IMPORTANT:
     * Magento\Framework\App\Http\Context::getData can be called multiple times over the course of the request lifecycle
     * it is important that this plugin return the data consistently - or the cache will be invalidated unexpectedly!
     *
     * @throws NoSuchEntityException
     */
    public function afterGetData(HttpContext $_subject, array $data): array
    {
        if (!$this->shouldApplyCacheContext()) {
            return $data;
        }

        $context = $this->backendRenderingResolver->shouldPreventRendering() ?
            self::RENDERING_WITHOUT_BACKEND :
            self::RENDERING_WITH_BACKEND;

        $data[self::RENDERING_CONTEXT] = $context;

        return $data;
    }

    /**
     * @throws NoSuchEntityException
     */
    protected function shouldApplyCacheContext(): bool
    {
        $storeId = $this->storeManager->getStore()->getId();
        return $this->isConfig->shouldReplaceCategories($storeId);
    }
}
