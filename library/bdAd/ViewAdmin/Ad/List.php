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
            $ads = $this->_params['ads'];

            foreach ($this->_params['slots'] as $slotId => $slot) {
                $adsGrouped[$slotId] = array();

                foreach ($ads as $adId => $ad) {
                    if (isset($ad['adSlots'][$slotId])) {
                        $adsGrouped[$slotId][$adId] = $ad;
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
