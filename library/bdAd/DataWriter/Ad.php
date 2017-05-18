<?php

class bdAd_DataWriter_Ad extends XenForo_DataWriter
{
    const DATA_PHRASE_TITLE = 'phraseTitle';
    const DATA_PHRASE_DESCRIPTION = 'phraseDescription';
    const DATA_SLOT_IDS = 'slotIds';

    const OPTION_REFRESH_ACTIVE_ADS = 'refreshActiveAds';
    const OPTION_UPDATE_AD_SLOT_IDS = 'updateAdSlotIds';

    public function setAdOptions(array $slotIds, $adOptionsSlotId, array $adOptions)
    {
        $this->setExtraData(self::DATA_SLOT_IDS, array());
        $this->set('ad_options', array());
        if (!in_array($adOptionsSlotId, $slotIds)) {
            return false;
        }

        $adOptions['_time'] = XenForo_Application::$time;
        $adOptions['_visitorUserId'] = XenForo_Visitor::getUserId();
        $adOptions['_slotIds'] = $slotIds;
        $adOptions['_adOptionsSlotId'] = $adOptionsSlotId;
        $this->setExtraData(self::DATA_SLOT_IDS, $slotIds);
        $this->set('ad_options', $adOptions);
        return true;
    }

    protected function _getFields()
    {
        return array(
            'xf_bdad_ad' => array(
                'ad_id' => array('type' => XenForo_DataWriter::TYPE_UINT, 'autoIncrement' => true),
                'ad_name' => array('type' => XenForo_DataWriter::TYPE_STRING, 'required' => true, 'maxLength' => 50),
                'user_id' => array('type' => XenForo_DataWriter::TYPE_UINT, 'required' => true),
                'ad_options' => array(
                    'type' => XenForo_DataWriter::TYPE_SERIALIZED,
                    'required' => true,
                    'default' => 'a:0:{}'
                ),
                'active' => array('type' => XenForo_DataWriter::TYPE_BOOLEAN, 'required' => true, 'default' => 1),
                'attach_count' => array('type' => XenForo_DataWriter::TYPE_UINT, 'required' => true, 'default' => 0),
                'view_count' => array('type' => XenForo_DataWriter::TYPE_UINT, 'required' => true, 'default' => 0),
                'click_count' => array('type' => XenForo_DataWriter::TYPE_UINT, 'required' => true, 'default' => 0),
                'ad_config_options' => array(
                    'type' => XenForo_DataWriter::TYPE_SERIALIZED,
                    'required' => true,
                    'default' => 'a:0:{}'
                ),
            )
        );
    }

    protected function _getDefaultOptions()
    {
        $options = parent::_getDefaultOptions();

        $options[self::OPTION_REFRESH_ACTIVE_ADS] = true;
        $options[self::OPTION_UPDATE_AD_SLOT_IDS] = true;

        return $options;
    }


    protected function _getExistingData($data)
    {
        if (!$id = $this->_getExistingPrimaryKey($data, 'ad_id')) {
            return false;
        }

        return array('xf_bdad_ad' => $this->_getAdModel()->getAdById($id));
    }

    protected function _getUpdateCondition($tableName)
    {
        $conditions = array();

        foreach (array('ad_id') as $field) {
            $conditions[] = $field . ' = ' . $this->_db->quote($this->getExisting($field));
        }

        return implode(' AND ', $conditions);
    }

    protected function _preSave()
    {
        parent::_preSave();

        if ($this->isInsert()
            && !$this->get('ad_name')
        ) {
            $maxAdId = intval($this->_db->fetchOne('SELECT MAX(ad_id) FROM xf_bdad_ad'));
            $this->set('ad_name', strval(new XenForo_Phrase('bdad_ad_x', array('number' => $maxAdId + 1))));
        }

        if ($this->isChanged('ad_options')) {
            $this->_verifyAdOptions();
        }
    }

    protected function _postSave()
    {
        $phraseTitle = $this->getExtraData(self::DATA_PHRASE_TITLE);
        if ($phraseTitle !== null) {
            $this->_insertOrUpdateMasterPhrase(bdAd_Model_Ad::getPhraseTitleForTitle($this->get('ad_id')), $phraseTitle);
        }

        $phraseDescription = $this->getExtraData(self::DATA_PHRASE_DESCRIPTION);
        if ($phraseDescription !== null) {
            $this->_insertOrUpdateMasterPhrase(bdAd_Model_Ad::getPhraseTitleForDescription($this->get('ad_id')), $phraseDescription);
        }

        $slotIds = $this->getExtraData(self::DATA_SLOT_IDS);
        if (is_array($slotIds)
            && $this->getOption(self::OPTION_UPDATE_AD_SLOT_IDS)
        ) {
            $this->_getAdModel()->updateAdSlotIds($this->get('ad_id'), $slotIds);
        }

        if ($this->getOption(self::OPTION_REFRESH_ACTIVE_ADS)) {
            bdAd_Engine::refreshActiveAds($this->_getSlotModel());
        }
    }

    protected function _postDelete()
    {
        $this->_deleteMasterPhrase(bdAd_Model_Ad::getPhraseTitleForTitle($this->get('ad_id')));

        $this->_deleteMasterPhrase(bdAd_Model_Ad::getPhraseTitleForDescription($this->get('ad_id')));

        if ($this->get('attach_count')) {
            /** @var XenForo_Model_Attachment $attachmentModel */
            $attachmentModel = $this->getModelFromCache('XenForo_Model_Attachment');
            $attachmentModel->deleteAttachmentsFromContentIds(
                'bdad_ad', array($this->get('ad_id'))
            );
        }

        if ($this->getOption(self::OPTION_UPDATE_AD_SLOT_IDS)) {
            $this->_getAdModel()->updateAdSlotIds($this->getExisting('ad_id'), array());
        }

        if ($this->getOption(self::OPTION_REFRESH_ACTIVE_ADS)) {
            bdAd_Engine::refreshActiveAds($this->_getSlotModel());
        }
    }

    protected function _verifyAdOptions()
    {
        $adOptions = unserialize($this->get('ad_options'));
        if (empty($adOptions)) {
            return;
        }

        if (!isset($adOptions['_slotIds'])
            || !isset($adOptions['_adOptionsSlotId'])
        ) {
            $this->error(new XenForo_Phrase('bdad_ad_options_cannot_verified'), 'ad_options');
        }

        foreach ($adOptions['_slotIds'] as $slotId) {
            $slot = $this->_getSlotModel()->getSlotById($slotId);
            if (empty($slot)) {
                $this->error(new XenForo_Phrase('bdad_slot_not_found'), 'ad_options');
            } else {
                $slotObj = bdAd_Slot_Abstract::create($slot['slot_class']);
                if (!$slotObj->verifyAdOptions($this, $slot, $adOptions)) {
                    $this->error(new XenForo_Phrase('bdad_ad_options_cannot_verified'), 'ad_options');
                }
            }
        }
    }

    /**
     * @return bdAd_Model_Ad
     */
    protected function _getAdModel()
    {
        return $this->getModelFromCache('bdAd_Model_Ad');
    }

    /**
     * @return bdAd_Model_Slot
     */
    protected function _getSlotModel()
    {
        return $this->getModelFromCache('bdAd_Model_Slot');
    }


}