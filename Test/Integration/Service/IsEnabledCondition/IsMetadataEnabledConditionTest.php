<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\FrontendMetadata\Test\Integration\Service\IsEnabledCondition;

use Klevu\FrontendApi\Service\IsEnabledCondition\IsEnabledConditionInterface;
use Klevu\FrontendMetadata\Service\IsEnabledCondition\IsMetadataEnabledCondition;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\FrontendMetadata\Service\IsEnabledCondition\IsMetadataEnabledCondition
 * @method IsEnabledConditionInterface instantiateTestObject(?array $arguments = null)
 * @method IsEnabledConditionInterface instantiateTestObjectFromInterface(?array $arguments = null)
 * @magentoAppArea frontend
 */
class IsMetadataEnabledConditionTest extends TestCase
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

        $this->implementationFqcn = IsMetadataEnabledCondition::class;
        $this->interfaceFqcn = IsEnabledConditionInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * @magentoConfigFixture default/klevu_frontend/metadata/enabled 1
     * @magentoConfigFixture default_store klevu_frontend/metadata/enabled 0
     */
    public function testExecute_ReturnsFalse_WhenDisabled(): void
    {
        $service = $this->instantiateTestObject();
        $this->assertFalse(condition: $service->execute());
    }

    /**
     * @magentoConfigFixture default/klevu_frontend/metadata/enabled 0
     * @magentoConfigFixture default_store klevu_frontend/metadata/enabled 1
     */
    public function testExecute_ReturnsTrue_WhenEnabled(): void
    {
        $service = $this->instantiateTestObject();
        $this->assertTrue(condition: $service->execute());
    }
}
