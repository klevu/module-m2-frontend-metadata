<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\FrontendMetadata\Service\Provider;

use Klevu\FrontendMetadataApi\Service\Provider\Catalog\Category\PathProviderInterface;
use Klevu\FrontendMetadataApi\Service\Provider\PageMetaProviderInterface;
use Klevu\Registry\Api\CategoryRegistryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\CatalogUrlRewrite\Model\CategoryUrlPathGenerator;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class CategoryMetaProvider implements PageMetaProviderInterface
{
    /**
     * @var CategoryRegistryInterface
     */
    private readonly CategoryRegistryInterface $categoryRegistry;
    /**
     * @var PathProviderInterface
     */
    private readonly PathProviderInterface $categoryPathProvider;
    /**
     * @var StoreManagerInterface
     */
    private readonly StoreManagerInterface $storeManager;
    /**
     * @var string
     */
    private readonly string $urlSuffix;

    /**
     * @param CategoryRegistryInterface $categoryRegistry
     * @param StoreManagerInterface $storeManager
     * @param PathProviderInterface $categoryPathProvider
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        CategoryRegistryInterface $categoryRegistry,
        StoreManagerInterface $storeManager,
        PathProviderInterface $categoryPathProvider,
        ScopeConfigInterface $scopeConfig,
    ) {
        $this->categoryRegistry = $categoryRegistry;
        $this->storeManager = $storeManager;
        $this->categoryPathProvider = $categoryPathProvider;
        $this->urlSuffix = $scopeConfig->getValue(
            CategoryUrlPathGenerator::XML_PATH_CATEGORY_URL_SUFFIX,
            ScopeInterface::SCOPE_STORE,
        );
    }

    /**
     * @return mixed[]
     * @throws LocalizedException
     */
    public function get(): array
    {
        $category = $this->categoryRegistry->getCurrentCategory();
        if (!$category instanceof CategoryInterface) {
            return [];
        }

        return [
            'categoryUrl' => $this->getCategoryUrl($category),
            'categoryAbsolutePath' => $this->getUrlPath($category),
            'categoryPath' => $this->categoryPathProvider->get($category),
            'categoryName' => $category->getName(),
        ];
    }

    /**
     * @param CategoryInterface $category
     *
     * @return string
     * @throws NoSuchEntityException
     */
    private function getCategoryUrl(CategoryInterface $category): string
    {
        /** @var CategoryInterface $category */
        $urlKey = method_exists($category, 'getDataUsingMethod')
            ? $category->getDataUsingMethod('url_key')
            : '';

        return $this->prepareUrl($urlKey);
    }

    /**
     * @param string $urlKey
     *
     * @return string
     * @throws NoSuchEntityException
     */
    private function prepareUrl(string $urlKey): string
    {
        $store = $this->storeManager->getStore();
        $storeBaseUrl = method_exists($store, 'getBaseUrl')
            ? $store->getBaseUrl()
            : '';

        return rtrim($storeBaseUrl, '/')
            . '/' . trim($urlKey, '/')
            . $this->urlSuffix;
    }

    /**
     * @param CategoryInterface $category
     *
     * @return mixed|string
     */
    private function getUrlPath(CategoryInterface $category): mixed
    {
        return method_exists($category, 'getDataUsingMethod')
            ? $category->getDataUsingMethod('url_path')
            : '';
    }
}
