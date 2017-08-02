<?php

class bdAd_XenForo_ViewPublic_Thread_View extends XFCP_bdAd_XenForo_ViewPublic_Thread_View
{
    public function prepareParams()
    {
        parent::prepareParams();

        if (isset($this->_params['posts'])) {
            $i = 0;
            foreach ($this->_params['posts'] as &$postRef) {
                $postRef['_bdAd_indexInPosts'] = $i++;
            }
        }
    }
}
