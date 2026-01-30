<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Indexing;

use Algolia\AlgoliaSearch\Service\Product\SortingTransformer;
use Algolia\AlgoliaSearch\Test\Integration\TestCase;
use Algolia\SearchAdapter\Test\Integration\BackendSearchTestCase;
use Algolia\AlgoliaSearch\Service\Product\ReplicaManager;
use Algolia\SearchAdapter\Helper\ConfigHelper as AdapterConfigHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;

class ReplicaManagerTest extends BackendSearchTestCase
{
    protected ?ReplicaManager $replicaManager = null;
    protected ?AdapterConfigHelper $adapterConfigHelper = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->replicaManager = $this->objectManager->get(ReplicaManager::class);
        $this->adapterConfigHelper = $this->objectManager->get(AdapterConfigHelper::class);
    }

    /**
     * Test that indexing products with backend search creates replicas
     *
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default/catalog/search/engine algolia
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 0
     * @magentoConfigFixture current_store algoliasearch_indexing_manager/algolia_indexing/enable_indexing 1
     */
    public function testIndexingCreatesReplicasWithBackendSearchEnabled(): void
    {
        $storeId = 1;

        $this->assertTrue(
            $this->adapterConfigHelper->isAlgoliaEngineSelected(),
            'Algolia should be selected as the search engine'
        );

        $this->assertTrue(
            $this->replicaManager->isReplicaSyncEnabled($storeId),
            'Replica sync should be enabled for this test'
        );

        // Index products - this should trigger replica creation
        $this->indexAllProducts($storeId);

        // Get the primary index settings to verify replicas are configured
        $indexOptions = $this->getIndexOptions('products');

        $settings = $this->algoliaConnector->getSettings($indexOptions);

        $sorting = $this->objectManager->get(SortingTransformer::class)->getSortingIndices($storeId, null, null, true);

        $this->assertArrayHasKey(
            'replicas',
            $settings,
            'Primary index should have replicas setting after indexing with backend search enabled'
        );

        $this->assertCount(
            count($sorting),
            $settings['replicas'],
            'Primary index should have the same number of replicas as sorts'
        );
    }

    /**
     * Test plugin correctly extends isReplicaSyncEnabled logic
     * The ReplicaManagerPlugin::afterIsReplicaSyncEnabled should return true
     * when Algolia backend search is enabled, even if the original result was false
     * (because InstantSearch is disabled)
     *
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default/catalog/search/engine algolia
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 0
     * @magentoConfigFixture current_store algoliasearch_indexing_manager/algolia_indexing/enable_indexing 1
     */
    public function testPluginExtendsReplicaSyncLogic(): void
    {
        $storeId = 1;

        $result = $this->replicaManager->isReplicaSyncEnabled($storeId);

        $this->assertTrue(
            $result,
            'Plugin should enable replica sync when Algolia backend search is selected'
        );
    }

    /**
     * Test that replica sync requires indexing to be enabled
     * Even with Algolia backend search, replica sync should be disabled if indexing is not enabled
     *
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default/catalog/search/engine algolia
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 0
     * @magentoConfigFixture current_store algoliasearch_indexing_manager/algolia_indexing/enable_indexing 0
     */
    public function testReplicaSyncRequiresIndexingEnabled(): void
    {
        $isReplicaSyncEnabled = $this->replicaManager->isReplicaSyncEnabled(1);

        $this->assertFalse(
            $isReplicaSyncEnabled,
            'Replica sync should be disabled when indexing is not enabled'
        );
    }

    /**
     * Test that both conditions must be met for backend search replica sync
     *
     * @magentoDbIsolation disabled
     * @dataProvider backendSearchReplicaSyncConditionsProvider
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 0
     */
    public function testBackendSearchReplicaSyncConditions(
        string $searchEngine,
        bool $indexingEnabled,
        bool $expectedResult,
        string $message
    ): void {
        // Set the search engine
        $this->setConfig('catalog/search/engine', $searchEngine, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null);
        $this->setConfig('algoliasearch_indexing_manager/algolia_indexing/enable_indexing', $indexingEnabled ? '1' : '0');

        $isReplicaSyncEnabled = $this->replicaManager->isReplicaSyncEnabled(1);

        $this->assertEquals($expectedResult, $isReplicaSyncEnabled, $message);
    }

    /**
     * Data provider for backend search replica sync conditions
     */
    public static function backendSearchReplicaSyncConditionsProvider(): array
    {
        return [
            'Algolia engine + indexing enabled' => [
                'algolia',
                true,
                true,
                'Should enable replica sync with Algolia engine and indexing enabled'
            ],
            'Algolia engine + indexing disabled' => [
                'algolia',
                false,
                false,
                'Should disable replica sync when indexing is disabled'
            ],
            'OpenSearch engine + indexing enabled' => [
                'opensearch',
                true,
                false,
                'Should disable replica sync with non-Algolia engine and no InstantSearch'
            ],
            'OpenSearch engine + indexing disabled' => [
                'opensearch',
                false,
                false,
                'Should disable replica sync with non-Algolia engine and indexing disabled'
            ],
        ];
    }
}
