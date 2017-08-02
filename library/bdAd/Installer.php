<?php

class bdAd_Installer
{
    /* Start auto-generated lines of code. Change made will be overwriten... */

    protected static $_tables = array(
        'slot' => array(
            'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_bdad_slot` (
                `slot_id` INT(10) UNSIGNED AUTO_INCREMENT
                ,`slot_name` VARCHAR(50) NOT NULL
                ,`slot_class` VARCHAR(50) NOT NULL
                ,`slot_options` MEDIUMBLOB NOT NULL
                ,`active` TINYINT(4) UNSIGNED NOT NULL DEFAULT \'1\'
                ,`slot_config_options` MEDIUMBLOB NOT NULL
                , PRIMARY KEY (`slot_id`)
                
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
            'dropQuery' => 'DROP TABLE IF EXISTS `xf_bdad_slot`',
        ),
        'ad' => array(
            'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_bdad_ad` (
                `ad_id` INT(10) UNSIGNED AUTO_INCREMENT
                ,`ad_name` VARCHAR(50) NOT NULL
                ,`user_id` INT(10) UNSIGNED NOT NULL
                ,`ad_options` MEDIUMBLOB NOT NULL
                ,`active` TINYINT(4) UNSIGNED NOT NULL DEFAULT \'1\'
                ,`attach_count` INT(10) UNSIGNED NOT NULL DEFAULT \'0\'
                ,`view_count` INT(10) UNSIGNED NOT NULL DEFAULT \'0\'
                ,`click_count` INT(10) UNSIGNED NOT NULL DEFAULT \'0\'
                ,`ad_config_options` MEDIUMBLOB NOT NULL
                , PRIMARY KEY (`ad_id`)
                
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
            'dropQuery' => 'DROP TABLE IF EXISTS `xf_bdad_ad`',
        ),
        'ad_slot' => array(
            'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_bdad_ad_slot` (
                `ad_id` INT(10) UNSIGNED NOT NULL
                ,`slot_id` INT(10) UNSIGNED NOT NULL
                ,`ad_slot_options` MEDIUMBLOB
                , PRIMARY KEY (`ad_id`,`slot_id`)
                
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
            'dropQuery' => 'DROP TABLE IF EXISTS `xf_bdad_ad_slot`',
        ),
    );
    protected static $_patches = array();

    public static function install($existingAddOn, $addOnData)
    {
        $db = XenForo_Application::get('db');

        foreach (self::$_tables as $table) {
            $db->query($table['createQuery']);
        }

        foreach (self::$_patches as $patch) {
            $tableExisted = $db->fetchOne($patch['tableCheckQuery']);
            if (empty($tableExisted)) {
                continue;
            }

            $existed = $db->fetchOne($patch['checkQuery']);
            if (empty($existed)) {
                $db->query($patch['addQuery']);
            } else {
                $db->query($patch['modifyQuery']);
            }
        }

        self::installCustomized($existingAddOn, $addOnData);
    }

    public static function uninstall()
    {
        $db = XenForo_Application::get('db');

        foreach (self::$_patches as $patch) {
            $tableExisted = $db->fetchOne($patch['tableCheckQuery']);
            if (empty($tableExisted)) {
                continue;
            }

            $existed = $db->fetchOne($patch['checkQuery']);
            if (!empty($existed)) {
                $db->query($patch['dropQuery']);
            }
        }

        foreach (self::$_tables as $table) {
            $db->query($table['dropQuery']);
        }

        self::uninstallCustomized();
    }

    /* End auto-generated lines of code. Feel free to make changes below */

    public static function installCustomized($existingAddOn, $addOnData)
    {
        if (XenForo_Application::$versionId < 1020000) {
            throw new XenForo_Exception('[bd] Advertisement requires XenForo 1.2.0+');
        }

        $db = XenForo_Application::getDb();

        $db->query('
            CREATE TABLE IF NOT EXISTS xf_bdad_click (
                ad_id int(10) unsigned NOT NULL,
                KEY ad_id (ad_id)
            ) ENGINE=MEMORY DEFAULT CHARSET=utf8;
        ');

        $db->query('
            CREATE TABLE IF NOT EXISTS xf_bdad_view (
                ad_id int(10) unsigned NOT NULL,
                KEY ad_id (ad_id)
            ) ENGINE=MEMORY DEFAULT CHARSET=utf8;
        ');

        $db->query('REPLACE INTO `xf_content_type` (content_type, addon_id, fields) VALUES ("bdad_ad", "bdAd", "")');
        $db->query('REPLACE INTO `xf_content_type_field` (content_type, field_name, field_value) VALUES ("bdad_ad", "attachment_handler_class", "bdAd_AttachmentHandler_Ad")');

        /** @var XenForo_Model_ContentType $contentTypeModel */
        $contentTypeModel = XenForo_Model::create('XenForo_Model_ContentType');
        $contentTypeModel->rebuildContentTypeCache();

        self::_upgradeAdSlotIds();
    }

    public static function uninstallCustomized()
    {
        $db = XenForo_Application::getDb();

        $db->query('DROP TABLE xf_bdad_click');
        $db->query('DROP TABLE xf_bdad_view');

        $db->query('DELETE FROM `xf_content_type` WHERE addon_id = "bdAd"');
        $db->query('DELETE FROM `xf_content_type_field` WHERE content_type = "bdad_ad"');

        /** @var XenForo_Model_ContentType $contentTypeModel */
        $contentTypeModel = XenForo_Model::create('XenForo_Model_ContentType');
        $contentTypeModel->rebuildContentTypeCache();

        /** @var XenForo_Model_DataRegistry $dataRegistryModel */
        $dataRegistryModel = XenForo_Model::create('XenForo_Model_DataRegistry');
        $dataRegistryModel->delete(bdAd_Engine::DATA_REGISTRY_ACTIVE_ADS);

        XenForo_Application::setSimpleCacheData(bdAd_Engine::SIMPLE_CACHE_ACTIVE_SLOT_CLASSES, false);

        bdAd_ShippableHelper_Updater::onUninstall();
    }

    protected static function _upgradeAdSlotIds()
    {
        $db = XenForo_Application::getDb();

        $slotIdColumnInAdTable = $db->fetchOne('SHOW COLUMNS FROM xf_bdad_ad LIKE \'slot_id\';');
        if (empty($slotIdColumnInAdTable)) {
            return;
        }

        $adSlots = $db->fetchAll('SELECT ad_id, slot_id FROM xf_bdad_ad');
        foreach ($adSlots as $adSlot) {
            $db->query(
                'REPLACE INTO xf_bdad_ad_slot (ad_id, slot_id) VALUES (?, ?)',
                array($adSlot['ad_id'], $adSlot['slot_id'])
            );
        }

        $db->query('ALTER TABLE xf_bdad_ad CHANGE slot_id slot_id_'
            . XenForo_Application::$time . ' INT(10) UNSIGNED');
    }
}
