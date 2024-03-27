<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\FrontendMetadata\Test\Integration\ViewModel;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\FrontendMetadata\ViewModel\PageMeta;
use Klevu\FrontendMetadataApi\Service\Provider\PageMetaProviderInterface;
use Klevu\FrontendMetadataApi\ViewModel\PageMetaInterface;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers \Klevu\FrontendMetadata\ViewModel\PageMeta
 * @method PageMetaInterface instantiateTestObject(?array $arguments = null)
 * @method PageMetaInterface instantiateTestObjectFromInterface(?array $arguments = null)
 * @magentoAppArea frontend
 */
class PageMetaTest extends TestCase
{
    use ObjectInstantiationTrait;
    use SetAuthKeysTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null;
    /**
     * @var ScopeProviderInterface|null
     */
    private ?ScopeProviderInterface $scopeProvider = null;
    /**
     * @var SerializerInterface|null
     */
    private ?SerializerInterface $serializer = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = PageMeta::class;
        $this->interfaceFqcn = PageMetaInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $this->scopeProvider->unsetCurrentScope();
        $this->serializer = $this->objectManager->get(SerializerInterface::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->scopeProvider->unsetCurrentScope();
        $this->storeFixturesPool->rollback();
    }

    public function testIsEnabled_ReturnsFalse_WhenNotIntegrated(): void
    {
        /** @var PageMetaInterface $viewModel */
        $viewModel = $this->instantiateTestObject();

        $this->assertFalse($viewModel->isEnabled());
    }

    public function testIsEnabled_ReturnsTrue_WhenIntegrated_MetadataEnabled(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $this->scopeProvider->setCurrentScope($storeFixture->get());

        $this->setAuthKeys(
            scopeProvider: $this->scopeProvider,
            jsApiKey: 'klevu-js-key',
            restAuthKey: 'klevu-rest-key',
        );
        ConfigFixture::setForStore(
            path: 'klevu_frontend/metadata/enabled',
            value: 1,
            storeCode: $storeFixture->getCode(),
        );

        /** @var PageMetaInterface $viewModel */
        $viewModel = $this->instantiateTestObject();

        $this->assertTrue($viewModel->isEnabled());
    }

    public function testIsEnabled_ReturnsFalse_WhenIntegrated_MetadataDisabled(): void
    {
        $mockProvider = $this->getMockBuilder(PageMetaProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockProvider->expects($this->never())
            ->method('get');

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $this->scopeProvider->setCurrentScope($storeFixture->get());

        $this->setAuthKeys(
            scopeProvider: $this->scopeProvider,
            jsApiKey: 'klevu-js-key',
            restAuthKey: 'klevu-rest-key',
        );
        ConfigFixture::setForStore(
            path: 'klevu_frontend/metadata/enabled',
            value: 0,
            storeCode: $storeFixture->getCode(),
        );

        /** @var PageMetaInterface $viewModel */
        $viewModel = $this->instantiateTestObject([
            'pageMetaProviders' => [
                'section' => $mockProvider,
            ],
        ]);

        $this->assertFalse($viewModel->isEnabled());
    }

    public function testGetMeta_ReturnsBasicMetaData(): void
    {
        $viewModel = $this->instantiateTestObject();
        $viewModelPageMeta = $viewModel->getMeta();
        $pageMeta = $this->serializer->unserialize($viewModelPageMeta);

        $this->assertArrayHasKey('system', $pageMeta);
        $this->assertArrayHasKey('platform', $pageMeta['system']);
        $this->assertSame(expected: 'Magento', actual: $pageMeta['system']['platform']);

        $this->assertArrayHasKey('page', $pageMeta);

        $this->assertArrayHasKey(key: 'cart', array: $pageMeta['page']);
        $this->assertArrayHasKey(key: 'products', array: $pageMeta['page']['cart']);
        $this->assertIsArray(actual: $pageMeta['page']['cart']['products']);
    }
}
