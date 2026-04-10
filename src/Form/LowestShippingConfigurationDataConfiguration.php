<?php

declare(strict_types=1);

namespace PrestaShop\Module\Lowestshipping\Form;

use PrestaShop\PrestaShop\Core\Configuration\DataConfigurationInterface;
use PrestaShop\PrestaShop\Core\ConfigurationInterface;

final class LowestShippingConfigurationDataConfiguration implements DataConfigurationInterface
{
    public const DEFAULT_COUNTRY = 'LOWESTSHIPPING_DEFAULT_COUNTRY';

    public const PRICE_WITH_TAX = 'LOWESTSHIPPING_PRICE_WITH_TAX';

    public const TEXT_PREFIX = 'LOWESTSHIPPING_TEXT_PREFIX';

    public const ENABLE_VISIBILITY_FILTER = 'LOWESTSHIPPING_ENABLE_VISIBILITY_FILTER';

    public const EXCLUDED_PRODUCT_IDS = 'LOWESTSHIPPING_EXCLUDED_PRODUCT_IDS';

    public const EXCLUDED_CATEGORY_IDS = 'LOWESTSHIPPING_EXCLUDED_CATEGORY_IDS';

    private ConfigurationInterface $configuration;

    public function __construct(ConfigurationInterface $configuration)
    {
        $this->configuration = $configuration;
    }

    public function getConfiguration(): array
    {
        return [
            'default_country' => (int) $this->configuration->get(static::DEFAULT_COUNTRY),
            'price_with_tax' => (bool) $this->configuration->get(static::PRICE_WITH_TAX),
            'text_prefix' => (string) $this->configuration->get(static::TEXT_PREFIX),
            'enable_visibility_filter' => (bool) $this->configuration->get(static::ENABLE_VISIBILITY_FILTER),
            'excluded_product_ids' => (string) $this->configuration->get(static::EXCLUDED_PRODUCT_IDS),
            'excluded_category_ids' => (string) $this->configuration->get(static::EXCLUDED_CATEGORY_IDS),
        ];
    }

    public function updateConfiguration(array $configuration): array
    {
        $errors = [];

        if (!$this->validateConfiguration($configuration)) {
            return ['Invalid configuration payload.'];
        }

        if ((int) $configuration['default_country'] <= 0) {
            $errors[] = 'Default country is required.';
        }

        if ($errors !== []) {
            return $errors;
        }

        $this->configuration->set(static::DEFAULT_COUNTRY, (int) $configuration['default_country']);
        $this->configuration->set(static::PRICE_WITH_TAX, (bool) $configuration['price_with_tax']);
        $this->configuration->set(static::TEXT_PREFIX, (string) $configuration['text_prefix']);
        $this->configuration->set(static::ENABLE_VISIBILITY_FILTER, (bool) $configuration['enable_visibility_filter']);
        $this->configuration->set(static::EXCLUDED_PRODUCT_IDS, (string) $configuration['excluded_product_ids']);
        $this->configuration->set(static::EXCLUDED_CATEGORY_IDS, (string) $configuration['excluded_category_ids']);

        return [];
    }

    public function validateConfiguration(array $configuration): bool
    {
        return isset(
            $configuration['default_country'],
            $configuration['price_with_tax'],
            $configuration['text_prefix'],
            $configuration['enable_visibility_filter'],
            $configuration['excluded_product_ids'],
            $configuration['excluded_category_ids']
        );
    }
}
