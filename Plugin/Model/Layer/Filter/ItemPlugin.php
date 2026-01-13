<?php

namespace Algolia\SearchAdapter\Plugin\Model\Layer\Filter;

use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\AlgoliaSearch\Service\Category\CategoryPathProvider;
use Algolia\SearchAdapter\Helper\ConfigHelper;
use Algolia\SearchAdapter\Plugin\AbstractFilterPlugin;
use Magento\Catalog\Model\Layer\Filter\Item;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Theme\Block\Html\Pager;

/**
 * This plugin alters the URL used for standard text based filters to use labels instead of option IDs.
 * @see \Magento\Catalog\Model\Layer\Filter\Item::getUrl
 *
 * Swatches are handled differently
 * @see \Algolia\SearchAdapter\Plugin\Block\LayeredNavigation\RenderedLayeredPlugin
 */
class ItemPlugin extends AbstractFilterPlugin
{
    protected const PARAM_CATEGORY = 'cat';
    protected const PARAM_PRICE = 'price';
    protected const EXCLUDED_ATTRIBUTES = [];

    public function __construct(

        protected ConfigHelper          $configHelper,
        protected StoreManagerInterface $storeManager,
        protected CategoryPathProvider  $categoryPathProvider,
        UrlInterface                    $urlBuilder,
        Pager                           $pager,
    ) {
        parent::__construct($urlBuilder, $pager);
    }

    /**
     * @throws LocalizedException
     */
    public function afterGetUrl(Item $subject, string $result): string
    {
        $param = $subject->getFilter()->getRequestVar();
        $storeId = $this->storeManager->getStore()->getId();

        if (
            !$this->configHelper->areSeoFiltersEnabled($storeId) ||
            in_array($param, self::EXCLUDED_ATTRIBUTES)
        ) {
            return $result;
        }

        switch ($param) {
            case self::PARAM_CATEGORY:
                return $this->getCategorySlug($subject, $storeId);
            case self::PARAM_PRICE:
                return $result;
            default: // all other EAV attributes
                return $this->buildUrl($param, $this->getAttributeSlug($subject));
        }
    }

    protected function getAttributeSlug(Item $item): string
    {
        return $item->getData('label');
    }

    /**
     * @throws LocalizedException
     */
    protected function getCategorySlug(Item $item, int $storeId): string
    {
        return $this->buildUrl(
            $item->getFilter()->getRequestVar(),
            $this->categoryPathProvider->getCategoryPageId(
                $item->getValueString(),
                $storeId,
                InstantSearchHelper::CATEGORY_ROUTE_DELIMITER
            )
        );
    }
}
