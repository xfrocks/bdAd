<?php

class bdAd_XenForo_ViewPublic_Forum_View extends XFCP_bdAd_XenForo_ViewPublic_Forum_View
{
    public function prepareParams()
    {
        parent::prepareParams();

        if (isset($this->_params['threads'])) {
            $i = 0;
            foreach ($this->_params['threads'] as &$threadRef) {
                $threadRef['_bdAd_indexInThreads'] = $i++;
            }
        }
    }

}