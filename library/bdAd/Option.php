<?php

class bdAd_Option
{
    public static function get($key, $subKey = null)
    {
        $options = XenForo_Application::getOptions();

        return $options->get('bdAd_' . $key, is_string($subKey) ? $subKey : null);
    }

    public static function verifyGptStaticJs($enabled, XenForo_DataWriter $dw)
    {
        if ($enabled) {
            /** @var bdAd_Model_Slot $slotModel */
            $slotModel = $dw->getModelFromCache('bdAd_Model_Slot');
            bdAd_Engine::refreshActiveAds($slotModel, array('gptStaticJs' => true));
        }

        return true;
    }
}