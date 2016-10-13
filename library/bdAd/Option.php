<?php

class bdAd_Option
{
    public static function get($key, $subKey = null)
    {
        $options = XenForo_Application::getOptions();

        return $options->get('bdAd_' . $key, is_string($subKey) ? $subKey : null);
    }
}