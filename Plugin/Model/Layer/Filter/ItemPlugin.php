<?php

namespace Algolia\SearchAdapter\Plugin\Model\Layer\Filter;

use Magento\Catalog\Model\Layer\Filter\Item;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Theme\Block\Html\Pager;

class ItemPlugin
{
    protected const EXCLUDED_ATTRIBUTES = [
        'cat',
        'price'
    ];

    public function __construct(
        protected UrlInterface $url,
        protected Pager $htmlPagerBlock,
    ) {}

    /**
     * @throws LocalizedException
     */
    public function afterGetUrl(Item $subject, $result): string
    {
        if (in_array($subject->getFilter()->getRequestVar(), self::EXCLUDED_ATTRIBUTES)) {
            return $result;
        }

        $query = [
            $subject->getFilter()->getRequestVar() => $this->getSlug($subject),
            // reset pagination with filter selection
            $this->htmlPagerBlock->getPageVarName() => null,
        ];
        return $this->url->getUrl('*/*/*', ['_current' => true, '_use_rewrite' => true, '_query' => $query]);
    }

    protected function getSlug(Item $item): string
    {
        return $item->getData('label');
    }
}
