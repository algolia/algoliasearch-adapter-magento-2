<?php

namespace Algolia\SearchAdapter\Block\Adminhtml\System\Config;

use Algolia\SearchAdapter\Helper\ConfigHelper;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;

class ApplicationId extends \Magento\Config\Block\System\Config\Form\Field
{
    public function __construct(
        protected ConfigHelper $configHelper,
        Context                $context,
        array                  $data = [],
        ?SecureHtmlRenderer    $secureRenderer = null
    ) {
        parent::__construct($context, $data, $secureRenderer);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        $element->setReadonly(true);

        $request = $this->getRequest();
        $websiteId = $request->getParam('website') ?? null;
        $storeId = $request->getParam('store') ?? null;

        return '<strong>' . $this->configHelper->getApplicationId($websiteId, $storeId) . '</strong>';
    }

    protected function _isInheritCheckboxRequired(AbstractElement $element): bool
    {
        return false;
    }
}
