<?php
/**
 * SQL uninstall script — no statements by default (merchant data may be preserved).
 *
 * @author    Maxsoft
 * @copyright 2007-2026 Maxsoft
 * @license   https://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/*
 * In some cases you should not drop the tables.
 * Maybe the merchant will just try to reset the module
 * but does not want to loose all of the data associated to the module.
 *
 * When you must remove DB objects, add a non-empty list and loop, for example:
 *
 *     $queries = [
 *         'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'lowestshipping`',
 *     ];
 *     foreach ($queries as $query) {
 *         if (\Db::getInstance()->execute($query) == false) {
 *             return false;
 *         }
 *     }
 */
return true;
