<?php

namespace Algolia\SearchAdapter\Plugin\Model\Layer\Filter;

use Algolia\SearchAdapter\Helper\ConfigHelper;
use Algolia\SearchAdapter\Plugin\AbstractFilterPlugin;
use Magento\Catalog\Model\Layer\Filter\Item;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Theme\Block\Html\Pager;

class ItemPlugin extends AbstractFilterPlugin
{
    protected const EXCLUDED_ATTRIBUTES = [
        'cat',
        'price'
    ];

    public function __construct(
        protected ConfigHelper          $configHelper,
        protected StoreManagerInterface $storeManager,
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
        if (
            !$this->configHelper->areSeoFiltersEnabled($this->storeManager->getStore()->getId())
            ||
            in_array($subject->getFilter()->getRequestVar(), self::EXCLUDED_ATTRIBUTES)
        ) {
            return $result;
        }

        return $this->buildUrl($subject->getFilter()->getRequestVar(), $this->getSlug($subject));
    }

    protected function getSlug(Item $item): string
    {
        return $item->getData('label');
    }
}
