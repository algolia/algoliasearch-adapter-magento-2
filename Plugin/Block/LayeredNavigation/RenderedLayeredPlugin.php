<?php

namespace Algolia\SearchAdapter\Plugin\Block\LayeredNavigation;

use Algolia\SearchAdapter\Helper\ConfigHelper;
use Algolia\SearchAdapter\Plugin\AbstractFilterPlugin;
use Algolia\SearchAdapter\Service\FacetValueConverter;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Swatches\Block\LayeredNavigation\RenderLayered;
use Magento\Theme\Block\Html\Pager;

class RenderedLayeredPlugin extends AbstractFilterPlugin
{

    public function __construct(
        protected ConfigHelper          $configHelper,
        protected StoreManagerInterface $storeManager,
        protected FacetValueConverter   $facetValueConverter,
        UrlInterface                    $urlBuilder,
        Pager                           $pager,
    ) {
        parent::__construct($urlBuilder, $pager);
    }

    public function afterBuildUrl(
        RenderLayered $subject,
        string $result,
        string $attributeCode,
        int $optionId): string
    {
        if (!$this->configHelper->areSeoFiltersEnabled($this->storeManager->getStore()->getId())) {
            return $result;
        }

        return $this->buildUrl($attributeCode, $this->facetValueConverter->convertOptionIdToLabel($attributeCode, $optionId));
    }
}
