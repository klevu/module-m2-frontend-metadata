<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\FrontendMetadata\Test\Integration\Service\Provider;

use Klevu\FrontendMetadata\Service\Provider\PageTypeMetaProvider;
use Klevu\FrontendMetadataApi\Service\Provider\PageMetaProviderInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers PageTypeMetaProvider
 * @method PageMetaProviderInterface instantiateTestObject(?array $arguments = null)
 * @method PageMetaProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 * @magentoAppArea frontend
 */
class PageTypeMetaProviderTest extends TestCase
{
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;

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

        $this->implementationFqcn = PageTypeMetaProvider::class;
        $this->interfaceFqcn = PageMetaProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testGet_ReturnsArray_IncludingHomepage_ForHomepagePage(): void
    {
        $this->setRequest(
            module: 'cms',
            controller: 'index',
            action: 'index',
        );

        $provider = $this->instantiateTestObject();
        $result = $provider->get();

        $this->assertSame(expected: 'page', actual: $result);
    }

    public function testGet_ReturnsArray_IncludingPage_ForCmsPage(): void
    {
        $this->setRequest(
            module: 'cms',
            controller: 'page',
            action: 'view',
            params: ['page_id' => 1],
        );

        $provider = $this->instantiateTestObject();
        $result = $provider->get();

        $this->assertSame(expected: 'page', actual: $result);
    }

    public function testGet_ReturnsArray_IncludingPdp_ForProductPage(): void
    {
        $this->setRequest(
            module: 'catalog',
            controller: 'product',
            action: 'view',
            params: ['id' => 1],
        );

        $provider = $this->instantiateTestObject();
        $result = $provider->get();

        $this->assertSame(expected: 'pdp', actual: $result);
    }

    public function testGet_ReturnsArray_IncludingCategory_ForCategoryPage(): void
    {
        $this->setRequest(
            module: 'catalog',
            controller: 'category',
            action: 'view',
            params: ['id' => 1],
        );

        $provider = $this->instantiateTestObject();
        $result = $provider->get();

        $this->assertSame(expected: 'category', actual: $result);
    }

    public function testGet_ReturnsArray_IncludingCart_ForCartPage(): void
    {
        $this->setRequest(
            module: 'checkout',
            controller: 'cart',
            action: 'index',
        );

        $provider = $this->instantiateTestObject();
        $result = $provider->get();

        $this->assertSame(expected: 'cart', actual: $result);
    }

    public function testGet_ReturnsArray_IncludingCart_ForCheckoutPage(): void
    {
        $this->setRequest(
            module: 'checkout',
            controller: 'index',
            action: 'index',
        );

        $provider = $this->instantiateTestObject();
        $result = $provider->get();

        $this->assertSame(expected: 'cart', actual: $result);
    }

    public function testGet_ReturnsArray_IncludingCheckout_ForCheckoutSuccessPage(): void
    {
        $this->setRequest(
            module: 'checkout',
            controller: 'onepage',
            action: 'success',
        );

        $provider = $this->instantiateTestObject();
        $result = $provider->get();

        $this->assertSame(expected: 'checkout', actual: $result);
    }

    /**
     * @param string $module
     * @param string $controller
     * @param string $action
     * @param mixed[]|null $params
     *
     * @return void
     */
    private function setRequest(
        string $module,
        string $controller,
        string $action,
        ?array $params = [],
    ): void {
        $request = $this->objectManager->get(RequestInterface::class);
        $request->setModuleName($module);
        $request->setControllerName($controller);
        $request->setActionName($action);
        foreach ($params as $param => $value) {
            $request->setParam($param, $value);
        }
    }
}
