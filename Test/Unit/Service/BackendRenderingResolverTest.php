<?php

namespace Algolia\SearchAdapter\Test\Unit\Service;

use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Model\Config\Source\BackendRenderMode;
use Algolia\SearchAdapter\Service\BackendRenderingResolver;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;

class BackendRenderingResolverTest extends TestCase
{
    private ?BackendRenderingResolver $resolver = null;
    private null|(ScopeConfigInterface&MockObject) $scopeConfig = null;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->resolver = new BackendRenderingResolver($this->scopeConfig);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_USER_AGENT']);
    }

    /**
     * @dataProvider isEnabledDataProvider
     */
    public function testIsEnabled(int $configValue, bool $expected): void
    {
        $storeId = 1;

        $this->scopeConfig
            ->expects($this->once())
            ->method('getValue')
            ->with(
                BackendRenderingResolver::BACKEND_RENDER_MODE,
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
            ->willReturn($configValue);

        $result = $this->resolver->isEnabled($storeId);

        $this->assertSame($expected, $result);
    }

    public static function isEnabledDataProvider(): array
    {
        return [
            'disabled' => [
                'configValue' => BackendRenderMode::BACKEND_RENDER_OFF,
                'expected' => false,
            ],
            'enabled always' => [
                'configValue' => BackendRenderMode::BACKEND_RENDER_ON,
                'expected' => true,
            ],
            'enabled for user agents' => [
                'configValue' => BackendRenderMode::BACKEND_RENDER_USER_AGENTS,
                'expected' => true,
            ],
        ];
    }

    public function testShouldPreventRenderingReturnsTrueWhenDisabled(): void
    {
        $storeId = 1;

        $this->scopeConfig
            ->method('getValue')
            ->with(
                BackendRenderingResolver::BACKEND_RENDER_MODE,
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
            ->willReturn(BackendRenderMode::BACKEND_RENDER_OFF);

        $result = $this->resolver->shouldPreventRendering($storeId);

        $this->assertTrue($result);
    }

    public function testShouldPreventRenderingReturnsFalseWhenAlwaysOn(): void
    {
        $storeId = 1;

        $this->scopeConfig
            ->method('getValue')
            ->with(
                BackendRenderingResolver::BACKEND_RENDER_MODE,
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
            ->willReturn(BackendRenderMode::BACKEND_RENDER_ON);

        $result = $this->resolver->shouldPreventRendering($storeId);

        $this->assertFalse($result);
    }

    /**
     * @dataProvider userAgentMatchDataProvider
     */
    public function testShouldPreventRenderingWithUserAgentMode(
        string $userAgent,
        string $allowedAgents,
        bool $expectedPrevent
    ): void {
        $storeId = 1;
        if ($userAgent) {
            $_SERVER['HTTP_USER_AGENT'] = $userAgent;
        }

        $this->scopeConfig
            ->method('getValue')
            ->willReturnMap([
                [
                    BackendRenderingResolver::BACKEND_RENDER_MODE,
                    ScopeInterface::SCOPE_STORE,
                    $storeId,
                    BackendRenderMode::BACKEND_RENDER_USER_AGENTS,
                ],
                [
                    BackendRenderingResolver::BACKEND_RENDER_USER_AGENTS,
                    ScopeInterface::SCOPE_STORE,
                    $storeId,
                    $allowedAgents,
                ],
            ]);

        $result = $this->resolver->shouldPreventRendering($storeId);

        $this->assertSame($expectedPrevent, $result);
    }

    public static function userAgentMatchDataProvider(): array
    {
        return [
            'googlebot matches' => [
                'userAgent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
                'allowedAgents' => "googlebot\nbingbot",
                'expectedPrevent' => false,
            ],
            'bingbot matches' => [
                'userAgent' => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
                'allowedAgents' => "googlebot\nbingbot",
                'expectedPrevent' => false,
            ],
            'regular browser does not match' => [
                'userAgent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                'allowedAgents' => "googlebot\nbingbot",
                'expectedPrevent' => true,
            ],
            'empty allowed list prevents rendering' => [
                'userAgent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)',
                'allowedAgents' => '',
                'expectedPrevent' => true,
            ],
            'case insensitive match' => [
                'userAgent' => 'Mozilla/5.0 (compatible; GOOGLEBOT/2.1)',
                'allowedAgents' => 'googlebot',
                'expectedPrevent' => false,
            ],
            'no user agent prevents rendering' => [
                'userAgent' => '',
                'allowedAgents' => 'googlebot\nbingbot',
                'expectedPrevent' => true,
            ],
            'user agent match with multiple line formats' => [
                'userAgent' => 'Mozilla/5.0 (compatible; yandexbot/3.0)',
                'allowedAgents' => "googlebot\r\nbingbot\ryandexbot", // Alt line endings e.g. Windows
                'expectedPrevent' => false,
            ],
            'user agent match with whitespace in config' => [
                'userAgent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)',
                'allowedAgents' => "  \n\ngooglebot\n\n  ",
                'expectedPrevent' => false,
            ],
        ];
    }

    public function testShouldPreventRenderingWithNullStoreId(): void
    {
        $this->scopeConfig
            ->method('getValue')
            ->with(
                BackendRenderingResolver::BACKEND_RENDER_MODE,
                ScopeInterface::SCOPE_STORE,
                null
            )
            ->willReturn(BackendRenderMode::BACKEND_RENDER_ON);

        $result = $this->resolver->shouldPreventRendering(null);

        $this->assertFalse($result);
    }

}

