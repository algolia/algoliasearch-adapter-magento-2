<?php

namespace Algolia\SearchAdapter\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class QueryStringParamMode implements OptionSourceInterface
{
    public const PARAM_MODE_ALGOLIA = 'legacy'; // The way InstantSearch params used to work
    public const PARAM_MODE_MAGENTO = 'magento'; // The way Magento uses params

    public const SORT_PARAM_ALGOLIA = 'sortBy';
    public const SORT_PARAM_MAGENTO = 'product_list_order';

    public const PAGE_PARAM_ALGOLIA = 'page';
    public const PAGE_PARAM_MAGENTO = 'p';

    public const CATEGORY_PARAM_ALGOLIA = 'categories';
    public const CATEGORY_PARAM_MAGENTO = 'cat';

    public const PRICE_PARAM_MAGENTO = 'price';

    public const PRICE_SEPARATOR_MAGENTO = '-';

    public function toOptionArray()
    {
        return [
            [
                'value' => self::PARAM_MODE_ALGOLIA,
                'label' => __('Algolia default'),
            ],
            [
                'value' => self::PARAM_MODE_MAGENTO,
                'label' => __('Magento compatibility mode'),
            ],
        ];
    }
}
