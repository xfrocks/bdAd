<?php

class bdAd_ViewPublic_Ads_Click extends XenForo_ViewPublic_Base
{
    public function renderRaw()
    {
        return sprintf('<meta http-equiv="refresh" content="0;url=%s" />',
            htmlentities($this->_params['target']));
    }
}