<?php

class bdAd_bdCache_Model_Cache extends XFCP_bdAd_bdCache_Model_Cache
{
    const EXTRA_DATA_KEY_CLICK_TRACKING_AD_IDS = 'bdAd_clickTracking';
    const EXTRA_DATA_KEY_LOGGED_VIEW_AD_IDS = 'bdAd_loggedView';

    public function prepareBeforeCaching(&$output, array &$extraData)
    {
        if (bdAd_Listener::$adHasBeenServed) {
            if (count(bdAd_Listener::$clickTrackingAdIds) > 0) {
                $extraData[self::EXTRA_DATA_KEY_CLICK_TRACKING_AD_IDS] = bdAd_Listener::$clickTrackingAdIds;
            }

            if (count(bdAd_Listener::$loggedViewAdIds) > 0) {
                $extraData[self::EXTRA_DATA_KEY_LOGGED_VIEW_AD_IDS] = bdAd_Listener::$loggedViewAdIds;
            }
        }

        parent::prepareBeforeCaching($output, $extraData);
    }

    public function prepareCached(array &$cached)
    {
        $this->_bdAd_fixClickTrackingUrls($cached);
        $this->_bdAd_logAdViews($cached);
        parent::prepareCached($cached);
    }

    protected function _bdAd_fixClickTrackingUrls(array &$cached)
    {
        if (!isset($cached[bdCache_Model_Cache::DATA_EXTRA_DATA][self::EXTRA_DATA_KEY_CLICK_TRACKING_AD_IDS])) {
            return;
        }

        $adIds = $cached[bdCache_Model_Cache::DATA_EXTRA_DATA][self::EXTRA_DATA_KEY_CLICK_TRACKING_AD_IDS];
        if (!is_array($adIds)) {
            return;
        }

        $htmlRef =& $cached[bdCache_Model_Cache::DATA_OUTPUT];
        $pattern = '#href="(?<u' . 'rl>[^"]+misc/ads/click[^"]+)"#';
        $offset = 0;
        while (true) {
            if (!preg_match($pattern, $htmlRef, $matches, PREG_OFFSET_CAPTURE, $offset)) {
                break;
            }

            $url = $matches['url'][0];
            $offset = $matches[0][1] + strlen($matches[0][0]);

            $parsedUrl = parse_url($url);
            if (empty($parsedUrl['query'])) {
                continue;
            }

            $urlQuery = array();
            parse_str($parsedUrl['query'], $urlQuery);
            if (empty($urlQuery['ad_id'])
                || !in_array($urlQuery['ad_id'], $adIds)
                || empty($urlQuery['redirect'])
            ) {
                continue;
            }

            $clickTrackingUrl = bdAd_Helper_Security::getClickTrackingUrl($urlQuery['ad_id'], $urlQuery['redirect']);
            $clickTrackingUrl .= '&cached=1';
            $htmlRef = substr_replace($htmlRef, $clickTrackingUrl, $matches['url'][1], strlen($matches['url'][0]));
        }
    }

    protected function _bdAd_logAdViews(array &$cached)
    {
        if (!isset($cached[bdCache_Model_Cache::DATA_EXTRA_DATA][self::EXTRA_DATA_KEY_LOGGED_VIEW_AD_IDS])) {
            return;
        }

        $adIds = $cached[bdCache_Model_Cache::DATA_EXTRA_DATA][self::EXTRA_DATA_KEY_LOGGED_VIEW_AD_IDS];
        if (!is_array($adIds)) {
            return;
        }

        /** @var bdAd_Model_Log $logModel */
        $logModel = $this->getModelFromCache('bdAd_Model_Log');
        $logModel->logAdViews($adIds);
    }
}
