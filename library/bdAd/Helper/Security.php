<?php

class bdAd_Helper_Security
{
    public static function getClickTrackingUrl($adId, $link)
    {
        $data = array(
            'ad_id' => $adId,
            'sid' => XenForo_Application::getSession()->getSessionId(),
        );

        bdAd_Listener::$clickTrackingAdIds[] = $adId;

        return XenForo_Link::buildPublicLink('full:misc/ads/click', null, array(
            'redirect' => $link,
            'ad_id' => $adId,
            'data' => self::packData($data),
        ));
    }

    public static function verifyClickTrackingData(XenForo_Input $input)
    {
        $data = self::unpackData($input->filterSingle('data', XenForo_Input::STRING));

        if (empty($data['ad_id'])
            || empty($data['sid'])
            || $data['sid'] !== XenForo_Application::getSession()->getSessionId()
        ) {
            return 0;
        }

        return $data['ad_id'];
    }

    public static function packData(array $data)
    {
        $serialized = json_encode($data);
        $hash = self::_generateHash($serialized);
        $json = json_encode(array($serialized, $hash));

        return base64_encode($json);
    }

    public static function unpackData($str)
    {
        $decoded = base64_decode($str);
        $json = @json_decode($decoded, true);
        if (!is_array($json)
            || count($json) !== 2
        ) {
            return null;
        }

        $hash = self::_generateHash($json[0]);
        if ($hash !== $json[1]) {
            return null;
        }

        return json_decode($json[0], true);
    }

    protected static function _generateHash()
    {
        $args = func_get_args();

        $data = array();
        foreach ($args as $arg) {
            $data[] = strval($arg);
        }

        return md5(implode('', $data) . XenForo_Application::getConfig()->get('globalSalt'));
    }
}