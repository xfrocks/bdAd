<?php

class bdAd_DataWriter_Slot extends XenForo_DataWriter
{
    const OPTION_VERIFY_SLOT_OPTIONS = 'verifySlotOptions';

    public function getSlotOptions($existing = false)
    {
        if ($existing) {
            $slotOptions = $this->getExisting('slot_options');
        } else {
            $slotOptions = $this->get('slot_options');
        }

        if (is_string($slotOptions)) {
            $slotOptions = unserialize($slotOptions);
        }
        if (!is_array($slotOptions)) {
            $slotOptions = array();
        }

        return $slotOptions;
    }

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

    protected function _getDefaultOptions()
    {
        $options = parent::_getDefaultOptions();

        $options[self::OPTION_VERIFY_SLOT_OPTIONS] = true;

        return $options;
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

        if ($this->getOption(self::OPTION_VERIFY_SLOT_OPTIONS)
            && ($this->isChanged('slot_class')
                || $this->isChanged('slot_options')
            )
        ) {
            $slotOptions = unserialize($this->get('slot_options'));
            if (empty($slotOptions)) {
                $slotOptions = array();
            }

            $slotObj = bdAd_Slot_Abstract::create($this->get('slot_class'));
            if (!$slotObj->verifySlotOptions($this, $slotOptions)) {
                $this->error(new XenForo_Phrase('bdad_slot_options_cannot_verified'), 'slot_options');
            }
        }

        if ($this->isChanged('slot_class')
            && $this->getExisting('class') === 'bdAd_Slot_Widget'
        ) {
            $this->error(new XenForo_Phrase('bdad_slot_class_must_not_change_from_widget'));
        }
    }

    protected function _postSave()
    {
        parent::_postSave();

        if ($this->get('slot_class') === 'bdAd_Slot_Widget') {
            $this->_SlotWidget_syncWidget();
        }

        bdAd_Engine::refreshActiveAds($this->_getSlotModel());
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        $this->_deleteSlotAds();

        if ($this->getExisting('slot_class') === 'bdAd_Slot_Widget') {
            $this->_SlotWidget_deleteExistingWidget();
        }

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

    protected function _SlotWidget_syncWidget()
    {
        $slotOptions = $this->getSlotOptions();
        if (empty($slotOptions['widgetId'])
            || (!empty($slotOptions['_syncTime'])
                && $slotOptions['_syncTime'] >= XenForo_Application::$time
            )
        ) {
            return;
        }

        $slotOptions['_syncTime'] = XenForo_Application::$time;
        $slotOptions['_syncMethod'] = __METHOD__;

        /** @var WidgetFramework_DataWriter_Widget $widgetDw */
        $widgetDw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget');
        $widgetDw->setExistingData($slotOptions['widgetId']);
        if (strip_tags($widgetDw->get('title')) != $this->get('slot_name')) {
            $widgetDw->set('title', $this->get('slot_name'));
        }
        $widgetDw->setWidgetOption('slotId', $this->get('slot_id'));
        $widgetDw->setWidgetOption('slotOptions', $slotOptions);
        $widgetDw->save();
    }

    protected function _SlotWidget_deleteExistingWidget()
    {
        $existingSlotOptions = $this->getSlotOptions(true);
        if (!empty($existingSlotOptions['widgetId'])) {
            /** @var WidgetFramework_DataWriter_Widget $widgetDw */
            $widgetDw = XenForo_DataWriter::create('WidgetFramework_DataWriter_Widget',
                XenForo_DataWriter::ERROR_SILENT);
            if ($widgetDw->setExistingData($existingSlotOptions['widgetId'])) {
                $widgetDw->delete();
            }
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