<?php

namespace Algolia\SearchAdapter\Plugin\View;

use Algolia\SearchAdapter\Helper\ConfigHelper;
use Magento\Catalog\Block\Product\ProductList\Toolbar;
use Magento\Catalog\Model\Product\ProductList\Toolbar as ToolbarModel;
use Magento\Catalog\Model\Product\ProductList\ToolbarMemorizer;
use Magento\Framework\App\Http\Context;
use Magento\Framework\View\Element\Template;

class ToolbarPlugin
{
    protected const CURRENT_SORT_ORDER = '_algolia_current_order';

    public function __construct(
        protected ToolbarMemorizer $toolbarMemorizer,
        protected Context $httpContext,
        protected ConfigHelper $configHelper,
    ) {}

    public function beforeGetTemplateFile(Toolbar $subject, ?string $template = null): array
    {
        if (
            // Intercept only sorter.phtml
            $template === 'Magento_Catalog::product/list/toolbar/sorter.phtml'
            &&
            $this->configHelper->isAlgoliaEngineSelected()
        ) {
            $template = 'Algolia_SearchAdapter::product/list/toolbar/sorter.phtml';
        }

        return [$template];
    }

    /** Handles the Algolia sort param independently of Magento core sort params  */
    public function aroundGetCurrentOrder(Toolbar $subject, callable $proceed): ?string
    {
        if (!$this->configHelper->isAlgoliaEngineSelected()) {
            return $proceed();
        }

        $order = $subject->getData(self::CURRENT_SORT_ORDER);
        if ($order) {
            return $order;
        }

        $order = $this->toolbarMemorizer->getOrder();

        if ($this->toolbarMemorizer->isMemorizingAllowed()) {
            // Algolia uses a composite sort param (field~direction)
            $this->httpContext->setValue(ToolbarModel::ORDER_PARAM_NAME, $order, null);
        }

        $subject->setData(self::CURRENT_SORT_ORDER, $order);
        return $order;
    }
}
