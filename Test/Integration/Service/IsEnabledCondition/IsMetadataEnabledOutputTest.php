<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\FrontendMetadata\Test\Integration\Service\IsEnabledCondition;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Website\WebsiteFixturesPool;
use Klevu\TestFixtures\Website\WebsiteTrait;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Response as TestFrameworkResponse;
use Magento\TestFramework\TestCase\AbstractController;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @magentoAppArea frontend
 */
class IsMetadataEnabledOutputTest extends AbstractController
{
    use SetAuthKeysTrait;
    use StoreTrait;
    use WebsiteTrait;

    /**
     * @var string|null
     */
    private ?string $uri = 'cms/index/index'; // home page
    /**
     * @var string|null
     */
    private ?string $pattern = '#<script type="text.*javascript"\s*id="klevu_meta".*>'
        . '\s*window\._klvReady\s*=\s*window._klvReady\s*\|\|\s*\[\];'
        . '\s*window\._klvReady\.push\(function\(\)\s*\{'
        . '\s*window\.klevu_meta = .*'
        . '\s*klevu\(\{powerUp: \{pageMeta: true\}\}\);'
        . '\s*\}\);'
        . '\s*</script>#';
    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->objectManager = $this->_objectManager;
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->websiteFixturesPool = $this->objectManager->get(WebsiteFixturesPool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->storeFixturesPool->rollback();
        $this->websiteFixturesPool->rollback();
    }

    /**
     * @magentoConfigFixture default/klevu_frontend/metadata/enabled 1
     * @magentoConfigFixture default_store klevu_frontend/metadata/enabled 1
     */
    public function test_MetadataJs_IsNotIncluded_WhenStoreNotIntegrated_MetaEnabled(): void
    {
        $this->dispatch($this->uri);

        /** @var TestFrameworkResponse $response */
        $response = $this->getResponse();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $responseBody = $response->getBody();

        $matches = [];
        preg_match(
            pattern: $this->pattern,
            subject: $responseBody,
            matches: $matches,
        );
        $this->assertCount(
            expectedCount: 0,
            haystack: $matches,
            message: 'Klevu Frontend Metadata Script Not Added When Store Not Integrated Meta Enabled',
        );
    }

    /**
     * @magentoConfigFixture default/klevu_frontend/metadata/enabled 0
     * @testWith ["klevu_frontend/quick_search/enabled", 1]
     *           ["klevu_frontend/srlp/theme", 1]
     *           ["klevu_frontend/recommendations/enabled", 1]
     *           ["klevu_frontend/category_navigation/theme", 1]
     *           ["klevu_frontend/metadata/enabled", 1]
     */
    public function test_MetadataJs_IsIncluded_WhenStoreIntegrated_ForAnyNeededFeature(
        string $enabledConfigPath,
        int $enabledConfigValue,
    ): void {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());

        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: 'klevu-js-key',
            restAuthKey: 'klevu-rest-key',
        );

        $array = [
            'klevu_frontend/quick_search/enabled' => 0,
            'klevu_frontend/srlp/theme' => 0,
            'klevu_frontend/recommendations/enabled' => 0,
            'klevu_frontend/category_navigation/theme' => 0,
            'klevu_frontend/metadata/enabled' => 0,
        ];
        $array[$enabledConfigPath] = $enabledConfigValue;

        foreach ($array as $path => $value) {
            ConfigFixture::setForStore(
                path: $path,
                value: $value,
                storeCode: $storeFixture->getCode(), // @phpstan-ignore-line incorrect type hint in TddFixtures
            );
        }

        $this->dispatch(uri: $this->uri);

        /** @var TestFrameworkResponse $response */
        $response = $this->getResponse();

        $this->assertInstanceOf(expected: ResponseInterface::class, actual: $response);
        $responseBody = $response->getBody();

        $matches = [];
        preg_match(
            pattern: $this->pattern,
            subject: $responseBody,
            matches: $matches,
        );
        $this->assertCount(
            expectedCount: 1,
            haystack: $matches,
            message: 'Klevu Frontend Metadata Script Added Meta Enabled',
        );
    }

    /**
     * @magentoConfigFixture default/klevu_frontend/metadata/enabled 1
     * @magentoConfigFixture klevu_test_store_1_store klevu_frontend/metadata/enabled 0
     * @magentoConfigFixture klevu_test_store_1_store klevu_frontend/recommendations/enabled 0
     * @magentoConfigFixture klevu_test_store_1_store klevu_frontend/quick_search/enabled 0
     * @magentoConfigFixture klevu_test_store_1_store klevu_frontend/category_navigation/theme 0
     * @magentoConfigFixture klevu_test_store_1_store klevu_frontend/srlp/theme 0
     */
    public function test_MetadataJs_IsIncluded_WhenStoreIntegrated_MetadataDisabled(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());

        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: 'klevu-js-key',
            restAuthKey: 'klevu-rest-key',
        );

        $this->dispatch($this->uri);

        /** @var TestFrameworkResponse $response */
        $response = $this->getResponse();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $responseBody = $response->getBody();

        $matches = [];
        preg_match(
            pattern: $this->pattern,
            subject: $responseBody,
            matches: $matches,
        );

        $this->assertCount(
            expectedCount: 0,
            haystack: $matches,
            message: 'Klevu Frontend Metadata Script Not Added Meta Disabled',
        );
    }

    public function test_MetadataJs_IsIncluded_WhenWebsiteIntegrated_MetadataEnabled(): void
    {
        $this->markTestSkipped('Re implement when website integration is released.');
        $this->createWebsite();
        $websiteFixture = $this->websiteFixturesPool->get('test_website');
        $this->createStore([
            'website_id' => $websiteFixture->getId(),
        ]);
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($websiteFixture->get());

        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: 'klevu-js-key',
            restAuthKey: 'klevu-rest-key',
        );

        ConfigFixture::setForStore(
            path: 'klevu_frontend/metadata/enabled',
            value: 1,
            storeCode: $storeFixture->getCode(),
        );

        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->dispatch($this->uri);

        /** @var TestFrameworkResponse $response */
        $response = $this->getResponse();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $responseBody = $response->getBody();
        $matches = [];
        preg_match(
            pattern: $this->pattern,
            subject: $responseBody,
            matches: $matches,
        );
        $this->assertCount(
            expectedCount: 1,
            haystack: $matches,
            message: 'Klevu Frontend Metadata Script Added When Website Integrated Meta Enabled',
        );
    }

    /**
     * @magentoConfigFixture default/klevu_frontend/metadata/enabled 1
     * @magentoConfigFixture default_store klevu_frontend/metadata/enabled 0
     */
    public function test_MetadataSearchJs_IsNotIncluded_WhenStoreNotIntegrated_MetadataDisabled(): void
    {
        $this->dispatch('cms/index/index');

        /** @var TestFrameworkResponse $response */
        $response = $this->getResponse();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $responseBody = $response->getBody();

        $matches = [];
        preg_match(
            pattern: $this->pattern,
            subject: $responseBody,
            matches: $matches,
        );

        $this->assertCount(
            expectedCount: 0,
            haystack: $matches,
            message: 'Klevu Frontend Metadata Script Not Added',
        );
    }
}
