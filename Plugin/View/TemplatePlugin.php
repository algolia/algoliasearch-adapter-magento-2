<?php

namespace Algolia\SearchAdapter\Plugin\View;

use Algolia\SearchAdapter\Helper\ConfigHelper;
use Magento\Framework\View\Element\Template;

class TemplatePlugin
{
    public function __construct(
        protected ConfigHelper $configHelper,
    ) {}

    public function beforeGetTemplateFile(Template $subject, ?string $template = null)
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
}
