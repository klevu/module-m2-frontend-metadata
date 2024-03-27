<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\FrontendMetadata\ViewModel;

use Klevu\Frontend\Exception\InvalidIsEnabledDeterminerException;
use Klevu\Frontend\Exception\OutputDisabledException;
use Klevu\FrontendApi\Service\IsEnabledCondition\IsEnabledConditionInterface;
use Klevu\FrontendApi\Service\IsEnabledDeterminerInterface;
use Klevu\FrontendMetadata\Exception\InvalidIsEnabledConditionException;
use Klevu\FrontendMetadataApi\Service\Provider\PageMetaProviderInterface;
use Klevu\FrontendMetadataApi\ViewModel\PageMetaInterface;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

class PageMeta implements PageMetaInterface
{
    /**
     * @var AppState
     */
    private readonly AppState $appState;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var IsEnabledDeterminerInterface
     */
    private readonly IsEnabledDeterminerInterface $isEnabledDeterminer;
    /**
     * @var SerializerInterface
     */
    private readonly SerializerInterface $serializer;
    /**
     * @var array<IsEnabledConditionInterface|IsEnabledConditionInterface[]>
     */
    private array $isEnabledConditions;
    /**
     * @var PageMetaProviderInterface[]
     */
    private array $pageMetaProviders;

    /**
     * @param AppState $appState
     * @param LoggerInterface $logger
     * @param IsEnabledDeterminerInterface $isEnabledDeterminer
     * @param SerializerInterface $serializer
     * @param array<IsEnabledConditionInterface|IsEnabledConditionInterface[]> $isEnabledConditions
     * @param PageMetaProviderInterface[] $pageMetaProviders
     *
     */
    public function __construct(
        AppState $appState,
        LoggerInterface $logger,
        IsEnabledDeterminerInterface $isEnabledDeterminer,
        SerializerInterface $serializer,
        array $isEnabledConditions = [],
        array $pageMetaProviders = [],
    ) {
        $this->appState = $appState;
        $this->logger = $logger;
        $this->isEnabledDeterminer = $isEnabledDeterminer;
        $this->serializer = $serializer;
        array_walk($isEnabledConditions, [$this, 'setEnabledCondition']);
        array_walk($pageMetaProviders, [$this, 'setPageMetaProvider']);
    }

    /**
     * @return bool
     * @throws InvalidIsEnabledDeterminerException
     */
    public function isEnabled(): bool
    {
        $return = false;
        try {
            $this->isEnabledDeterminer->executeAnd($this->isEnabledConditions);
            $return = true;
        } catch (InvalidIsEnabledDeterminerException $exception) {
            if ($this->appState->getMode() !== AppState::MODE_PRODUCTION) {
                throw $exception;
            }
            $this->logger->error(
                message: 'Method: {method}, Error: {error}',
                context: [
                    'method' => __METHOD__,
                    'error' => $exception->getMessage(),
                ],
            );
        } catch (OutputDisabledException) { //phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
            // Setting is disabled. This is fine, move onto next setting.
        }

        return $return;
    }

    /**
     * @return string
     */
    public function getMeta(): string
    {
        $pageMeta = [
            'system' => [
                'platform' => 'Magento',
            ],
            'page' => [],
        ];
        foreach ($this->pageMetaProviders as $section => $pageMetaProvider) {
            $pageMeta['page'][$section] = $pageMetaProvider->get();
        }

        return $this->serializer->serialize($pageMeta);
    }

    /**
     * @param IsEnabledConditionInterface|IsEnabledConditionInterface[] $isEnabledCondition
     *
     * @return void
     * @throws InvalidIsEnabledConditionException
     */
    private function setEnabledCondition(mixed $isEnabledCondition): void
    {
        $this->validateIsEnabledCondition($isEnabledCondition);

        $this->isEnabledConditions[] = $isEnabledCondition;
    }

    /**
     * @param mixed $isEnabledCondition
     *
     * @return void
     * @throws InvalidIsEnabledConditionException
     */
    private function validateIsEnabledCondition(mixed $isEnabledCondition): void
    {
        if (is_array($isEnabledCondition)) {
            foreach ($isEnabledCondition as $condition) {
                $this->validateIsEnabledCondition($condition);
            }

            return;
        }
        if (!$isEnabledCondition instanceof IsEnabledConditionInterface) {
            throw new InvalidIsEnabledConditionException(
                __(
                    'Invalid isEnabledCondition provided, expected (%1) received (%2).',
                    IsEnabledConditionInterface::class,
                    get_debug_type($isEnabledCondition),
                ),
            );
        }
    }

    /**
     * @param PageMetaProviderInterface $pageMetaProvider
     * @param string $key
     *
     * @return void
     */
    private function setPageMetaProvider(
        PageMetaProviderInterface $pageMetaProvider,
        string $key,
    ): void {
        $this->pageMetaProviders[$key] = $pageMetaProvider;
    }
}
