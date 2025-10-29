<?php

namespace Algolia\SearchAdapter\Block\Adminhtml\System\Config;

use Algolia\SearchAdapter\Helper\ConfigHelper;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;

class Engine extends \Magento\Config\Block\System\Config\Form\Field
{
    public function __construct(
        protected ConfigHelper $configHelper,
        Context                $context,
        array                  $data = [],
        ?SecureHtmlRenderer    $secureRenderer = null
    ) {
        parent::__construct($context, $data, $secureRenderer);
    }

    /** Hide any engine other than Algolia if not in default scope  */
    protected function _decorateRowHtml(AbstractElement $element, $html): string
    {
        $hiddenStyleAttr = '';
        if (!$this->configHelper->isEngineSelectVisible($this->getRequest())) {
            $hiddenStyleAttr = ' style="display:none;"';
        }
        return '<tr id="row_' . $element->getHtmlId() . '"'. $hiddenStyleAttr . '>' . $html . '</tr>';
    }

    /** Disable engine selection for anything other than default scope */
    protected function _getElementHtml(AbstractElement $element): string
    {
        if (!$this->configHelper->isEngineSelectEnabled($this->getRequest())) {
            $element->setReadonly(true);
            $element->setData('disabled', true);
        }
        return parent::_getElementHtml($element);
    }

    /** Preserve engine scope limitation despite overriding scope restrictions  */
    protected function _isInheritCheckboxRequired(AbstractElement $element): bool
    {
        return false;
    }
}
