<?php

/**
 * Migracja ustawień do wersji 2.1.0.
 *
 * @return bool
 */
function upgrade_module_2_1_0($module)
{
    if (!Configuration::hasKey('LOWESTSHIPPING_ENABLE_PRODUCT_PAGE')) {
        Configuration::updateValue('LOWESTSHIPPING_ENABLE_PRODUCT_PAGE', true);
    }

    if (!Configuration::hasKey('LOWESTSHIPPING_DESCRIPTION')) {
        Configuration::updateValue('LOWESTSHIPPING_DESCRIPTION', '');
    }

    Configuration::deleteByName('LOWESTSHIPPING_ENABLE_VISIBILITY_FILTER');
    Configuration::deleteByName('LOWESTSHIPPING_EXCLUDED_PRODUCT_IDS');
    Configuration::deleteByName('LOWESTSHIPPING_EXCLUDED_CATEGORY_IDS');

    return true;
}
