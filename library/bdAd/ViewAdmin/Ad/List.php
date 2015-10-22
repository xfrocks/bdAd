<?php

class bdAd_ViewAdmin_Ad_List extends XenForo_ViewAdmin_Base
{
    public function prepareParams()
    {
        parent::prepareParams();

        if (isset($this->_params['ads'])
            && isset($this->_params['slots'])
        ) {
            $adsGrouped = array();
            $adsRef =& $this->_params['ads'];

            foreach ($this->_params['slots'] as $slotId => $slot) {
                $adsGrouped[$slotId] = array();

                foreach (array_keys($adsRef) as $adId) {
                    if ($adsRef[$adId]['slot_id'] == $slotId) {
                        $adsGrouped[$slotId][$adId] = $adsRef[$adId];
                        unset($adsRef[$adId]);
                    }
                }
            }

            if (!empty($adsRef)) {
                $adsGrouped[0] = $adsRef;
            }

            $this->_params['adsGrouped'] = $adsGrouped;
            unset($this->_params['ads']);
        }
    }

}