<?php
/**
 * lowestshipping module — Maxsoft
 *
 * @copyright 2026 Maxsoft
 * @license   https://opensource.org/licenses/MIT MIT License
 */
$sql = array();

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'lowestshipping` (
    `id_lowestshipping` int(11) NOT NULL AUTO_INCREMENT,
    PRIMARY KEY  (`id_lowestshipping`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
