<?php

class bdAd_XenForo_ControllerPublic_Misc extends XFCP_bdAd_XenForo_ControllerPublic_Misc
{
    public function actionAdsClick()
    {
        $adId = bdAd_Helper_Security::verifyClickTrackingData($this->_input);
        if ($adId > 0) {
            /** @var bdAd_Model_Log $logModel */
            $logModel = $this->getModelFromCache('bdAd_Model_Log');
            $logModel->logAdClick($adId);
        }

        $viewParams = array(
            'target' => $this->_input->filterSingle('redirect', XenForo_Input::STRING),
        );

        $this->_routeMatch->setResponseType('raw');
        return $this->responseView('bdAd_ViewPublic_Ads_Click', '', $viewParams);
    }
}
