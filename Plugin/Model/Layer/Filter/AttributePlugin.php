<?php

namespace Algolia\SearchAdapter\Plugin\Model\Layer\Filter;

use Algolia\SearchAdapter\Helper\ConfigHelper;
use Algolia\SearchAdapter\Service\FacetValueConverter;
use Magento\CatalogSearch\Model\Layer\Filter\Attribute;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * All text based labels must be normalized to option IDs to retain compatability with Magento Layered Navigation
 * (including swatches with backend type of 'int' and current refinements).
 * This plugin is used to convert the text based label to the option ID as needed.
 *
 * @see \Magento\CatalogSearch\Model\Layer\Filter\Attribute::convertAttributeValue
 */
class AttributePlugin extends AbstractSeoFilterPlugin
{
    public function __construct(
        protected StoreManagerInterface $storeManager,
        protected FacetValueConverter   $facetValueConverter,
        ConfigHelper                    $configHelper,
    ) {
        parent::__construct($configHelper);
    }

    public function beforeApply(Attribute $subject, RequestInterface $request): array
    {
        $attributeCode = $subject->getRequestVar();
        $attributeValue = $request->getParam($attributeCode);
        $storeId = $this->storeManager->getStore()->getId();

        if (!$this->checkFilterConfig($attributeValue, $storeId)) {
            return [$request];
        }

        $optionId = $this->facetValueConverter->convertLabelToOptionId($attributeCode, $attributeValue);

        if ($optionId) {
            $request->setParam($attributeCode, $optionId);
        }

        return [$request];
    }
}
