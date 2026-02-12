<?php
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\App\Config\MutableScopeConfigInterface;

$objectManager = Bootstrap::getObjectManager();
$scopeConfig = $objectManager->get(MutableScopeConfigInterface::class);

// Set complex configuration value
$scopeConfig->setValue(
    'algoliasearch_instant/backend/backend_render_allowed_user_agents',
    implode("\n", ["Googlebot", "Bingbot", "Foobot"]),
    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
);
