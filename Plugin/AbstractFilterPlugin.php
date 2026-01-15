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

    /**
     * Build the URL for the filter
     *
     * @param string $attributeCode Code of the attribute to filter
     * @param string $value Value of the filter
     * @param string[] $clearParams List of parameters to clear
     * @return string
     */
    protected function buildUrl(
        string $attributeCode,
        string $value,
        array $clearParams = []): string
    {
        $query = [
            $attributeCode => $value,
            // reset pagination with filter selection
            $this->pager->getPageVarName() => null
        ];

        if ($clearParams) {
            $query = array_merge($query, array_fill_keys($clearParams, null));
        }

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
