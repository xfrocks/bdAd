<?php

class bdAd_Engine
{
    const VERSION = 2016112201;
    const DATA_REGISTRY_ACTIVE_ADS = 'bdAd_activeAds';
    const SIMPLE_CACHE_ACTIVE_SLOT_CLASSES = 'bdAd_activeSlotClasses';

    public static function refreshActiveAds(bdAd_Model_Slot $slotModel, array $options = array())
    {
        $slots = $slotModel->getSlots(array('active' => 1));

        /** @var bdAd_Model_Ad $adModel */
        $adModel = $slotModel->getModelFromCache('bdAd_Model_Ad');
        $ads = $adModel->getAds(array('active' => 1), array('join' => bdAd_Model_Ad::FETCH_AD_SLOTS));
        $ads = $adModel->prepareAdsUploadsForCaching($ads);

        $data = array(
            'version' => self::VERSION,
            'slots' => array(),
            'adsGrouped' => array(),
        );
        $adSlotIdBase = count($ads) > 0 ? max(array_keys($ads)) : 0;

        $activeSlotClasses = array();
        foreach ($ads as $adId => $ad) {
            if (empty($ad['adSlots'])) {
                continue;
            }
            $ad = $adModel->prepareAdPhrasesForCaching($ad);

            foreach ($ad['adSlots'] as $adAdSlot) {
                $slotId = $adAdSlot['slot_id'];
                if (!isset($slots[$slotId])) {
                    continue;
                }

                $slotRef =& $slots[$slotId];

                $data['slots'][$slotId] = $slotRef;
                $activeSlotClasses[] = $slotRef['slot_class'];

                $adSlotId = ++$adSlotIdBase;
                $data['adsGrouped'][$slotId][$adId] = $ad;
                $data['adsGrouped'][$slotId][$adId]['adSlotId'] = $adSlotId;
                unset($data['adsGrouped'][$slotId][$adId]['adSlots']);
                $data['adsGrouped'][$slotId][$adId]['adSlotOptions'] = $adAdSlot['ad_slot_options'];
            }
        }
        $activeSlotClasses = array_unique($activeSlotClasses);

        /** @var XenForo_Model_DataRegistry $dataRegistryModel */
        $dataRegistryModel = $slotModel->getModelFromCache('XenForo_Model_DataRegistry');
        $dataRegistryModel->set(self::DATA_REGISTRY_ACTIVE_ADS, $data);

        XenForo_Application::setSimpleCacheData(self::SIMPLE_CACHE_ACTIVE_SLOT_CLASSES, $activeSlotClasses);

        return $data;
    }

    public static function isSlotClassActive($slotClass)
    {
        if (self::$_activeSlotClasses === null) {
            $activeSlotClasses = XenForo_Application::getSimpleCacheData(self::SIMPLE_CACHE_ACTIVE_SLOT_CLASSES);
        } else {
            $activeSlotClasses =& self::$_activeSlotClasses;
        }

        if (empty($activeSlotClasses)) {
            return false;
        }

        return in_array($slotClass, $activeSlotClasses, true);
    }

    public static function getAdIdsShouldBeServed($slotClass, array $params)
    {
        if (!self::isSlotClassActive($slotClass)) {
            return false;
        }

        if (bdAd_Listener::$noAd) {
            return false;
        }

        $slotObj = bdAd_Slot_Abstract::create($slotClass);

        $function = array($slotObj, 'adIdsShouldBeServed');
        if (!is_callable($function)) {
            return false;
        }

        return call_user_func_array($function, $params);
    }

    public static function onTemplateHook($hookName, &$contents, array $hookParams)
    {
        if ($hookName !== 'bdad_hook'
            || !isset($hookParams['slotClass'])
            || !isset($hookParams['adIds'])
        ) {
            return;
        }

        $adIds = array_map('intval', explode(',', $hookParams['adIds']));
        $validAdIds = array();
        foreach ($adIds as $adId) {
            if ($adId > 0) {
                $validAdIds[] = $adId;
            }
        }

        $slotObj = bdAd_Slot_Abstract::create($hookParams['slotClass']);
        $adHtmls = array();
        foreach ($validAdIds as $adId) {
            $adHtmls[$adId] = $slotObj->prepareAdHtml($adId, $contents);
        }

        $contents = implode('', $adHtmls);
    }

    /**
     * @return bdAd_Engine
     */
    public static function getInstance()
    {
        static $instance = null;

        if ($instance === null) {
            $data = array();

            /** @var XenForo_Model_DataRegistry $dataRegistryModel */
            $dataRegistryModel = XenForo_Model::create('XenForo_Model_DataRegistry');

            if (!XenForo_Visitor::getInstance()->hasPermission('general', 'bdAd_noAd')) {
                $data = $dataRegistryModel->get(self::DATA_REGISTRY_ACTIVE_ADS);

                if (!is_array($data)
                    || empty($data['version'])
                    || $data['version'] < self::VERSION
                ) {
                    /** @var bdAd_Model_Slot $slotModel */
                    $slotModel = $dataRegistryModel->getModelFromCache('bdAd_Model_Slot');
                    $data = self::refreshActiveAds($slotModel);
                }
            }

            $instance = new bdAd_Engine($dataRegistryModel, $data);
        }

        return $instance;
    }

    /** @var XenForo_Model_DataRegistry $_dataRegistryModel */
    private $_dataRegistryModel;
    private $_slots = array();
    private $_adsGrouped = array();
    private $_servedSlots = array();
    private $_servedAds = array();

    private static $_activeSlotClasses = null;

    private function __construct(XenForo_Model_DataRegistry $dataRegistryModel, array $data)
    {
        $this->_dataRegistryModel = $dataRegistryModel;

        if (!empty($data['slots'])) {
            $this->_slots = $data['slots'];
        }

        if (!empty($data['adsGrouped'])) {
            $this->_adsGrouped = $data['adsGrouped'];
        }

        $this->_updateActiveSlotClasses();
    }

    public function getSlotsByClass($slotClass)
    {
        $slots = array();

        foreach ($this->_slots as $slotId => $slot) {
            if ($slot['slot_class'] === $slotClass) {
                $slots[$slotId] = $slot;
            }
        }

        return $slots;
    }

    public function getAdsBySlotId($slotId)
    {
        if (isset($this->_adsGrouped[$slotId])) {
            $ads = array();

            $visitor = XenForo_Visitor::getInstance()->toArray();
            foreach ($this->_adsGrouped[$slotId] as $adId => $ad) {
                if (!empty($ad['ad_config_options']['user_criteria'])
                    && !XenForo_Helper_Criteria::userMatchesCriteria(
                        $ad['ad_config_options']['user_criteria'], true, $visitor)
                ) {
                    continue;
                }

                $ads[$adId] = $ad;
            }

            return $ads;
        }

        return array();
    }

    public function getRandomAdBySlotId($slotId)
    {
        if (isset($this->_adsGrouped[$slotId])) {
            $keys = array_keys($this->_adsGrouped[$slotId]);
            if (empty($keys)) {
                return null;
            }
            shuffle($keys);
            $visitor = XenForo_Visitor::getInstance()->toArray();

            foreach ($keys as $key) {
                $ad = $this->_adsGrouped[$slotId][$key];

                if (!empty($ad['ad_config_options']['user_criteria'])
                    && !XenForo_Helper_Criteria::userMatchesCriteria(
                        $ad['ad_config_options']['user_criteria'], true, $visitor)
                ) {
                    continue;
                }

                return $ad;
            }
        }

        return null;
    }

    public function markServed($slotId, $adId)
    {
        if (isset($this->_slots[$slotId])) {
            $this->_servedSlots[$slotId] = $this->_slots[$slotId];
            unset($this->_slots[$slotId]);
        }

        if (isset($this->_adsGrouped[$slotId][$adId])) {
            $ad = $this->_adsGrouped[$slotId][$adId];
            unset($this->_adsGrouped[$slotId][$adId]);

            $adSlotId = $ad['adSlotId'];
            $this->_servedAds[$adSlotId] = array($slotId, $ad);
        }

        $this->_updateActiveSlotClasses();
        bdAd_Listener::$adHasBeenServed = true;

        return false;
    }

    public function getServedSlotAndAd($adSlotId)
    {
        $slot = null;
        $ad = null;

        if (isset($this->_servedAds[$adSlotId])) {
            list($slotId, $ad) = $this->_servedAds[$adSlotId];

            if (isset($this->_servedSlots[$slotId])) {
                $slot = $this->_servedSlots[$slotId];
            }
        }

        return array($slot, $ad);
    }

    public function getServedAdIds()
    {
        return array_keys($this->_servedAds);
    }

    private function _updateActiveSlotClasses()
    {
        $slotClasses = array();

        foreach ($this->_slots as $slot) {
            $slotClasses[$slot['slot_class']] = 1;
        }

        self::$_activeSlotClasses = array_keys($slotClasses);
    }

}
