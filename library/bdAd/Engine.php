<?php

class bdAd_Engine
{
    const VERSION = 2016033001;
    const DATA_REGISTRY_ACTIVE_ADS = 'bdAd_activeAds';
    const SIMPLE_CACHE_ACTIVE_SLOT_CLASSES = 'bdAd_activeSlotClasses';

    public static function refreshActiveAds(bdAd_Model_Slot $slotModel, array $options = array())
    {
        $slots = $slotModel->getSlots(array('active' => 1));

        /** @var bdAd_Model_Ad $adModel */
        $adModel = $slotModel->getModelFromCache('bdAd_Model_Ad');
        $ads = $adModel->getAds(array('active' => 1));
        $ads = $adModel->prepareAdsUploadsForCaching($ads);

        $data = array(
            'version' => self::VERSION,
            'slots' => array(),
            'adsGrouped' => array(),
            'gptStaticJs' => false,
        );
        $gptAds = array();

        $activeSlotClasses = array();
        foreach ($ads as $adId => $ad) {
            if (!isset($slots[$ad['slot_id']])) {
                continue;
            }

            $ad = $adModel->prepareAdPhrasesForCaching($ad);
            $slotRef =& $slots[$ad['slot_id']];

            $data['slots'][$slotRef['slot_id']] = $slotRef;
            $data['adsGrouped'][$slotRef['slot_id']][$adId] = $ad;
            $activeSlotClasses[] = $slots[$ad['slot_id']]['slot_class'];

            if ($slotRef['slot_class'] == 'bdAd_Slot_Widget'
                && !empty($slotRef['slot_options']['adLayout'])
                && $slotRef['slot_options']['adLayout'] === 'gpt'
            ) {
                $gptAds[$adId] = $ad;
            }

        }
        $activeSlotClasses = array_unique($activeSlotClasses);


        if (!empty($options['gptStaticJs'])
            || bdAd_Option::get('gptStaticJs')
        ) {
            $data['gptStaticJs'] = self::gpt_generateStaticJs($gptAds);
        }

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

    public static function adIdsShouldBeServed($slotClass)
    {
        if (!self::isSlotClassActive($slotClass)) {
            return false;
        }

        if (bdAd_Listener::isNoAd() == false) {
            return false;
        }

        $slotObj = bdAd_Slot_Abstract::create($slotClass);
        $args = func_get_args();
        array_shift($args);

        return call_user_func_array(array($slotObj, 'adIdsShouldBeServed'), $args);
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

    public static function gpt_getContainerElementId(array $ad)
    {
        return sprintf('bdAd-gpt-slot-%d-ad-%d', $ad['slot_id'], $ad['ad_id']);
    }

    public static function gpt_generateBootstrapJs(array $ad, array &$scripts)
    {
        if (!isset($scripts['gpt_0'])) {
            $scripts['gpt_0'] = 'var googletag = googletag || {};'
                . 'googletag.cmd = googletag.cmd || [];'
                . '(function() {'
                . 'var gads = document.createElement("script");'
                . 'gads.async = true;'
                . 'gads.type = "text/javascript";'
                . 'var useSSL = "https:" == document.location.protocol;'
                . 'gads.src = (useSSL ? "https:" : "http:") + "//www.googletagservices.com/tag/js/gpt.js";'
                . 'var node =document.getElementsByTagName("script")[0];'
                . 'node.parentNode.insertBefore(gads, node);'
                . ' })();';

            $scripts['gpt_1'] = 'googletag.cmd.push(function() {';

            $scripts['gpt_9'] = 'googletag.pubads().enableSingleRequest();'
                . 'googletag.enableServices();'
                . '});';
        }

        $containerElementId = self::gpt_getContainerElementId($ad);
        $adSize = 'null';
        if (!empty($ad['ad_options']['sizeWidth'])
            && !empty($ad['ad_options']['sizeHeight'])
        ) {
            $adSize = sprintf('[%d, %d]', $ad['ad_options']['sizeWidth'], $ad['ad_options']['sizeHeight']);
        }
        $scripts['gpt_5_' . $containerElementId] = sprintf('googletag.defineSlot(%1$s, %2$s, %3$s)'
            . '.addService(googletag.pubads());',
            json_encode($ad['ad_options']['adUnitPath']),
            $adSize,
            json_encode($containerElementId));
    }

    public static function gpt_generateStaticJs(array $ads)
    {
        $scripts = array();
        foreach ($ads as $ad) {
            self::gpt_generateBootstrapJs($ad, $scripts);
        }
        ksort($scripts);

        $path = self::gpt_getStaticJsPath();
        XenForo_Helper_File::createDirectory(dirname($path));
        if (!file_put_contents($path, implode('', $scripts))) {
            return 0;
        }

        XenForo_Helper_File::makeWritableByFtpUser($path);
        return time();
    }

    public static function gpt_getStaticJsPath($prefix = null)
    {
        if ($prefix === null) {
            $prefix = XenForo_Helper_File::getExternalDataPath();
        }
        return $prefix . '/ads/gpt.js';
    }

    public static function gpt_getStaticJsUrl()
    {
        return self::getInstance()->getGptStaticJsUrl();
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
    private $_gptStaticJs = false;
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

        if (isset($data['gptStaticJs'])) {
            $this->_gptStaticJs = $data['gptStaticJs'];
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
            $this->_servedAds[$adId] = $this->_adsGrouped[$slotId][$adId];
            unset($this->_adsGrouped[$slotId][$adId]);

            /** @var bdAd_Model_Log $logModel */
            $logModel = $this->_dataRegistryModel->getModelFromCache('bdAd_Model_Log');
            $logModel->logAdView($adId);
        }

        $this->_updateActiveSlotClasses();
        bdAd_Listener::$adHasBeenServed = true;

        return false;
    }

    public function getServedSlotAndAd($adId)
    {
        $slot = null;
        $ad = null;

        if (isset($this->_servedAds[$adId])) {
            $ad = $this->_servedAds[$adId];
        }

        if (!empty($ad['slot_id'])
            && isset($this->_servedSlots[$ad['slot_id']])
        ) {
            $slot = $this->_servedSlots[$ad['slot_id']];
        }

        return array($slot, $ad);
    }

    public function getServedAdIds()
    {
        return array_keys($this->_servedAds);
    }

    public function getGptStaticJsUrl()
    {
        return sprintf('%s?%d', self::gpt_getStaticJsPath(XenForo_Application::$externalDataUrl),
            $this->_gptStaticJs);
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
