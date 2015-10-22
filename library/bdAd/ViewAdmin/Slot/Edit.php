<?php

class bdAd_ViewAdmin_Slot_Edit extends XenForo_ViewAdmin_Base
{
    public function prepareParams()
    {
        parent::prepareParams();

        if (!empty($this->_params['slot'])
            && !empty($this->_params['slotObj'])
        ) {
            /** @var bdAd_Slot_Abstract $slotObj */
            $slotObj = $this->_params['slotObj'];
            $slotOptionsTemplate = $slotObj->prepareSlotOptionsTemplate($this, $this->_params['slot']);
            if (!empty($slotOptionsTemplate)) {
                $this->_params['slotOptions'] = $slotOptionsTemplate;
            }
        }
    }

}