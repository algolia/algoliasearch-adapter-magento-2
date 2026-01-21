<?php

namespace Algolia\SearchAdapter\Service;

use Algolia\SearchAdapter\Model\Config\Source\EnableBackendRendering;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class BackendRenderingResolver
{
    public const BACKEND_RENDER_MODE = 'algoliasearch_instant/backend/enable_backend_render';
    public const BACKEND_RENDER_USER_AGENTS = 'algoliasearch_instant/backend/backend_render_allowed_user_agents';

    public function __construct(
        protected ScopeConfigInterface $configInterface,
    ) {}

    public function isEnabled(?int $storeId = null): bool
    {
        return (int) $this->configInterface->getValue(
                self::BACKEND_RENDER_MODE,
                ScopeInterface::SCOPE_STORE,
                $storeId
            ) !== EnableBackendRendering::BACKEND_RENDER_OFF;
    }

    public function shouldPreventRendering(?int $storeId = null): bool
    {
        $backendRenderMode = (int) $this->configInterface->getValue(
            self::BACKEND_RENDER_MODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($backendRenderMode === EnableBackendRendering::BACKEND_RENDER_OFF) { // Not enabled
            return true;
        }

        if ($backendRenderMode === EnableBackendRendering::BACKEND_RENDER_ON) { // Always on
            return false;
        }

        return !$this->isUserAgentMatch($storeId);
    }

    protected function isUserAgentMatch(?int $storeId): bool
    {
        $userAgent = mb_strtolower(
            (string) filter_var(
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                FILTER_SANITIZE_SPECIAL_CHARS
            ),
            'utf-8'
        );

        if (!$userAgent) {
            return false;
        }

        $allowedUserAgents = $this->configInterface->getValue(
            self::BACKEND_RENDER_USER_AGENTS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $allowedUserAgents = trim((string) $allowedUserAgents);
        if ($allowedUserAgents === '') {
            return false;
        }
        $allowedUserAgents = preg_split('/\n|\r\n?/', $allowedUserAgents);
        $allowedUserAgents = array_filter($allowedUserAgents);
        foreach ($allowedUserAgents as $allowedUserAgent) {
            $allowedUserAgent = mb_strtolower($allowedUserAgent, 'utf-8');
            if (mb_strpos($userAgent, $allowedUserAgent) !== false) {
                return true;
            }
        }
        return false;
    }
}
