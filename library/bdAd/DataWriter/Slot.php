<?php

class bdAd_DataWriter_Slot extends XenForo_DataWriter
{

    protected function _getFields()
    {
        return array(
            'xf_bdad_slot' => array(
                'slot_id' => array('type' => XenForo_DataWriter::TYPE_UINT, 'autoIncrement' => true),
                'slot_name' => array('type' => XenForo_DataWriter::TYPE_STRING, 'required' => true, 'maxLength' => 50),
                'slot_class' => array('type' => XenForo_DataWriter::TYPE_STRING, 'required' => true, 'maxLength' => 50),
                'slot_options' => array(
                    'type' => XenForo_DataWriter::TYPE_SERIALIZED,
                    'required' => true,
                    'default' => 'a:0:{}',
                ),
                'active' => array('type' => XenForo_DataWriter::TYPE_BOOLEAN, 'required' => true, 'default' => 1),
                'slot_config_options' => array(
                    'type' => XenForo_DataWriter::TYPE_SERIALIZED,
                    'required' => true,
                    'default' => 'a:0:{}'
                ),
            )
        );
    }

    protected function _getExistingData($data)
    {
        if (!$id = $this->_getExistingPrimaryKey($data, 'slot_id')) {
            return false;
        }

        return array('xf_bdad_slot' => $this->_getSlotModel()->getSlotById($id));
    }

    protected function _getUpdateCondition($tableName)
    {
        $conditions = array();

        foreach (array('slot_id') as $field) {
            $conditions[] = $field . ' = ' . $this->_db->quote($this->getExisting($field));
        }

        return implode(' AND ', $conditions);
    }

    protected function _preSave()
    {
        parent::_preSave();

        if ($this->isInsert()
            && !$this->get('slot_name')
        ) {
            $maxSlotId = intval($this->_db->fetchOne('SELECT MAX(slot_id) FROM xf_bdad_slot'));
            $this->set('slot_name', strval(new XenForo_Phrase('bdad_slot_x', array('number' => $maxSlotId + 1))));
        }

        if ($this->isChanged('slot_class')
            || $this->isChanged('slot_options')
        ) {
            $slotOptions = unserialize($this->get('slot_options'));
            $slotObj = bdAd_Slot_Abstract::create($this->get('slot_class'));
            if (!$slotObj->verifySlotOptions($this, $slotOptions)) {
                $this->error(new XenForo_Phrase('bdad_slot_options_cannot_verified'), 'slot_options');
            }
        }
    }

    protected function _postSave()
    {
        parent::_postSave();

        bdAd_Engine::refreshActiveAds($this->_getSlotModel());
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        $this->_deleteSlotAds();

        bdAd_Engine::refreshActiveAds($this->_getSlotModel());
    }

    protected function _deleteSlotAds()
    {
        /** @var bdAd_Model_Ad $adModel */
        $adModel = $this->getModelFromCache('bdAd_Model_Ad');
        $ads = $adModel->getAds(array('slot_id' => $this->get('slot_id')));

        foreach ($ads as $ad) {
            /** @var bdAd_DataWriter_Ad $adDw */
            $adDw = XenForo_DataWriter::create('bdAd_DataWriter_Ad');
            $adDw->setExistingData($ad, true);
            $adDw->setOption(bdAd_DataWriter_Ad::OPTION_REFRESH_ACTIVE_ADS, false);
            $adDw->delete();
        }
    }

    /**
     * @return bdAd_Model_Slot
     */
    protected function _getSlotModel()
    {
        return $this->getModelFromCache('bdAd_Model_Slot');
    }

}