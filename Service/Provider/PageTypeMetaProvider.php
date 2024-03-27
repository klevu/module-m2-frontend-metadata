<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\FrontendMetadata\Service\Provider;

use Klevu\FrontendMetadataApi\Service\Provider\PageMetaProviderInterface;
use Magento\Framework\App\RequestInterface;

class PageTypeMetaProvider implements PageMetaProviderInterface
{
    /**
     * @var RequestInterface
     */
    private readonly RequestInterface $request;
    /**
     * @var string[][]
     */
    private array $requestPaths;

    /**
     * @param RequestInterface $request
     * @param string[] $requestPaths
     */
    public function __construct(
        RequestInterface $request,
        array $requestPaths = [],
    ) {
        $this->request = $request;
        array_walk($requestPaths, [$this, 'setRequestPaths']);
    }

    /**
     * @return string
     */
    public function get(): string
    {
        $return = '';
        foreach ($this->requestPaths as $requestPath) {
            if (!$this->requestMatchesPath(requestPath: $requestPath['path'] ?? [])) {
                continue;
            }
            $return = $requestPath['pageType'];
            break;
        }

        return $return;
    }

    /**
     * @param string[] $requestPath
     *
     * @return void
     * @throws \LogicException
     */
    private function setRequestPaths(array $requestPath): void
    {
        if (!($requestPath['path'] ?? null) || !is_string($requestPath['path'])) {
            throw new \LogicException(
                sprintf(
                    'Invalid requestPath path provided. Expected string, received %s',
                    get_debug_type($requestPath['path'] ?? null),
                ),
            );
        }
        if (!($requestPath['pageType'] ?? null) || !is_string($requestPath['pageType'])) {
            throw new \LogicException(
                sprintf(
                    'Invalid requestPath pageType provided. Expected string, received %s',
                    get_debug_type($requestPath['pageType'] ?? null),
                ),
            );
        }
        $this->requestPaths[] = $requestPath;
    }

    /**
     * @param string $requestPath
     *
     * @return bool
     */
    private function requestMatchesPath(string $requestPath): bool
    {
        $path = explode('/', $requestPath);
        if (!($path[0] ?? null)) {
            return false;
        }
        if ($path[0] !== $this->request->getModuleName()) {
            return false;
        }
        if (method_exists($this->request, 'getControllerName')) {
            if (($path[1] ?? null) && $path[1] !== $this->request->getControllerName()) {
                return false;
            }
        }

        return !(($path[2] ?? null) && $path[2] !== $this->request->getActionName());
    }
}
