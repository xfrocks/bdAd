<?php

class bdAd_Helper_Template
{
    public static function renderNoAds()
    {
        bdAd_Listener::$noAd = true;
    }
}
