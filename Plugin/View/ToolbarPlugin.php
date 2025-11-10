<?php

namespace Algolia\SearchAdapter\Plugin\View;

use Algolia\SearchAdapter\Helper\ConfigHelper;
use Magento\Catalog\Block\Product\ProductList\Toolbar;
use Magento\Catalog\Model\Product\ProductList\Toolbar as ToolbarModel;
use Magento\Catalog\Model\Product\ProductList\ToolbarMemorizer;
use Magento\Framework\App\Http\Context;

class ToolbarPlugin
{
    public function __construct(
        protected ToolbarMemorizer $toolbarMemorizer,
        protected Context $httpContext,
        protected ConfigHelper $configHelper,
    ) {}

    public function aroundGetCurrentOrder(Toolbar $subject, callable $proceed): ?string
    {
        if (!$this->configHelper->isAlgoliaEngineSelected()) {
            return $proceed();
        }

        $order = $subject->getData('_algolia_current_order');
        if ($order) {
            return $order;
        }

        $order = $this->toolbarMemorizer->getOrder();

        if ($this->toolbarMemorizer->isMemorizingAllowed()) {
            $this->httpContext->setValue(ToolbarModel::ORDER_PARAM_NAME, $order, null);
        }

        $subject->setData('_algolia_current_order', $order);
        return $order;
    }
}
