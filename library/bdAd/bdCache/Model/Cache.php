<?php

class bdAd_bdCache_Model_Cache extends XFCP_bdAd_bdCache_Model_Cache
{
    const EXTRA_DATA_KEY_SERVED_AD_IDS = 'bdAd_servedAdIds';

    public function prepareBeforeCaching(&$output, array &$extraData)
    {
        if (bdAd_Listener::$adHasBeenServed) {
            $extraData[self::EXTRA_DATA_KEY_SERVED_AD_IDS] = bdAd_Engine::getInstance()->getServedAdIds();
        }

        parent::prepareBeforeCaching($output, $extraData);
    }

    public function prepareCached(array &$cached)
    {

        if (!empty($cached[bdCache_Model_Cache::DATA_EXTRA_DATA][self::EXTRA_DATA_KEY_SERVED_AD_IDS])) {
            $adIds = $cached[bdCache_Model_Cache::DATA_EXTRA_DATA][self::EXTRA_DATA_KEY_SERVED_AD_IDS];
            $htmlRef =& $cached[bdCache_Model_Cache::DATA_OUTPUT];

            /** @var bdAd_Model_Log $logModel */
            $logModel = $this->getModelFromCache('bdAd_Model_Log');
            $logModel->logAdViews($adIds);

            $offset = 0;
            while (true) {
                if (!preg_match('#href="(?<url>[^"]+misc/ads/click[^"]+)"#', $htmlRef, $matches, PREG_OFFSET_CAPTURE, $offset)) {
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
                    || empty($urlQuery['redirect'])
                ) {
                    continue;
                }

                if (in_array($urlQuery['ad_id'], $adIds)) {
                    $clickTrackingUrl = bdAd_Helper_Security::getClickTrackingUrl($urlQuery['ad_id'], $urlQuery['redirect']);
                    $clickTrackingUrl .= '&cached=1';
                    $htmlRef = substr_replace($htmlRef, $clickTrackingUrl, $matches['url'][1], strlen($matches['url'][0]));
                }
            }
        }

        parent::prepareCached($cached);
    }
}