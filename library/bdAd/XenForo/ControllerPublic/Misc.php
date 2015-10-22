<?php

class bdAd_XenForo_ControllerPublic_Misc extends XFCP_bdAd_XenForo_ControllerPublic_Misc
{
    public function actionAdsClick()
    {
        $redirect = $this->_input->filterSingle('redirect', XenForo_Input::STRING);

        $adId = bdAd_Helper_Security::verifyClickTrackingData($this->_input);
        if ($adId > 0) {
            /** @var bdAd_Model_Log $logModel */
            $logModel = $this->getModelFromCache('bdAd_Model_Log');
            $logModel->logAdClick($adId);
        }

        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
            $redirect
        );
    }
}