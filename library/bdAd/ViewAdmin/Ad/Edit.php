<?php

class bdAd_ViewAdmin_Ad_Edit extends XenForo_ViewAdmin_Base
{
    public function prepareParams()
    {
        parent::prepareParams();

        if (!empty($this->_params['ad'])
            && !empty($this->_params['slot'])
            && !empty($this->_params['slotObj'])
        ) {
            /** @var bdAd_Slot_Abstract $slotObj */
            $slotObj = $this->_params['slotObj'];
            $adOptionsTemplate = $slotObj->prepareAdOptionsTemplate($this,
                $this->_params['ad'], $this->_params['slot']);
            if (!empty($adOptionsTemplate)) {
                $this->_params['adOptions'] = $adOptionsTemplate;
            }
        }
    }

}