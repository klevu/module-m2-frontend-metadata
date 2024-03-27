<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\FrontendMetadata\Setup\Patch\Data;

use Klevu\Configuration\Setup\Traits\MigrateLegacyConfigurationSettingsTrait;
use Klevu\FrontendMetadata\Constants;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class MigrateLegacyConfigurationSettings implements DataPatchInterface
{
    use MigrateLegacyConfigurationSettingsTrait;

    public const XML_PATH_LEGACY_METADATA_ENABLED = 'klevu_search/metadata/enabled';

    /**
     * @param WriterInterface $configWriter
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        WriterInterface $configWriter,
        ResourceConnection $resourceConnection,
    ) {
        $this->configWriter = $configWriter;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @return $this
     */
    public function apply(): self
    {
        $this->migrateMetadataEnabled();

        return $this;
    }

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @return void
     * @throws \LogicException
     */
    private function migrateMetadataEnabled(): void
    {
        $this->renameConfigValue(
            fromPath: static::XML_PATH_LEGACY_METADATA_ENABLED,
            toPath: Constants::XML_PATH_METADATA_ENABLED,
        );
    }
}
