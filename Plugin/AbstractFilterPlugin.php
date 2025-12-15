<?php
namespace Algolia\SearchAdapter\Plugin;

use Magento\Framework\UrlInterface;
use Magento\Theme\Block\Html\Pager;

class AbstractFilterPlugin
{
    public function __construct(
        protected UrlInterface $urlBuilder,
        protected Pager        $pager,
    ) {}

    protected function buildUrl(string $attributeCode, string $value): string
    {
        $query = [
            $attributeCode => $value,
            // reset pagination with filter selection
            $this->pager->getPageVarName() => null
        ];

        return $this->urlBuilder->getUrl(
            '*/*/*',
            [
                '_current' => true,
                '_use_rewrite' => true,
                '_query' => $query
            ]
        );
    }
}
