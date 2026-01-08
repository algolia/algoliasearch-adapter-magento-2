<?php

namespace Algolia\SearchAdapter\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class SortParam implements OptionSourceInterface
{
    public const SORT_PARAM_ALGOLIA = 'sortBy';
    public const SORT_PARAM_MAGENTO = 'product_list_order';

    /*
     * TODO: Refactor this into a query string parameter "mode" - see MAGE-1452
     * It doesn't make sense to aggregate the following parameters under the same "sort" configuration
     */
    public const PAGE_PARAM_ALGOLIA = 'page';
    public const PAGE_PARAM_MAGENTO = 'p';

    public const CATEGORY_PARAM_ALGOLIA = 'categories';
    public const CATEGORY_PARAM_MAGENTO = 'cat';

    public const PRICE_PARAM_MAGENTO = 'price';

    public const PRICE_DELIMITER_MAGENTO = '-';

    public function toOptionArray()
    {
        return [
            [
                'value' => self::SORT_PARAM_ALGOLIA,
                'label' => __('Algolia default'),
            ],
            [
                'value' => self::SORT_PARAM_MAGENTO,
                'label' => __('Magento compatibility mode'),
            ],
        ];
    }
}
