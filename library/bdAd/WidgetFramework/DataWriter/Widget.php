<?php

class bdAd_WidgetFramework_DataWriter_Widget extends XFCP_bdAd_WidgetFramework_DataWriter_Widget
{
    protected function _preSave()
    {
        parent::_preSave();

        if ($this->get('class') === 'bdAd_WidgetFramework_WidgetRenderer_Slot') {
            $this->_bdAd_makeSureSlotExists();
        } elseif ($this->getExisting('class') === 'bdAd_WidgetFramework_WidgetRenderer_Slot') {
            $this->_bdAd_deleteExistingSlot();
        }
    }

    protected function _postSave()
    {
        parent::_postSave();

        if ($this->get('class') === 'bdAd_WidgetFramework_WidgetRenderer_Slot') {
            $this->_bdAd_syncSlot();
        }
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        if ($this->getExisting('class') === 'bdAd_WidgetFramework_WidgetRenderer_Slot') {
            $this->_bdAd_deleteExistingSlot();
        }
    }

    protected function _bdAd_makeSureSlotExists()
    {
        $slot = null;

        $slotId = $this->getWidgetOption('slotId');
        if ($slotId > 0) {
            $slot = $this->_bdAd_getSlotModel()->getSlotById($slotId);
        }

        if (empty($slot)) {
            $slotName = strip_tags($this->get('title'));
            if (empty($slotName)) {
                $this->error(new XenForo_Phrase('bdad_widget_slot_title_cannot_empty'), 'options');
                return;
            }

            /** @var bdAd_DataWriter_Slot $slotDw */
            $slotDw = XenForo_DataWriter::create('bdAd_DataWriter_Slot');
            $slotDw->setOption(bdAd_DataWriter_Slot::OPTION_VERIFY_SLOT_OPTIONS, false);
            $slotDw->bulkSet(array(
                'slot_name' => $slotName,
                'slot_class' => 'bdAd_Slot_Widget',
            ));
            $slotDw->save();

            $this->setWidgetOption('slotId', $slotDw->get('slot_id'));
        }
    }

    protected function _bdAd_syncSlot()
    {
        $slotOptions = $this->getWidgetOption('slotOptions');
        if (!empty($slotOptions['_syncTime'])
            && $slotOptions['_syncTime'] >= XenForo_Application::$time
        ) {
            return;
        }

        $slotOptions['widgetId'] = $this->get('widget_id');
        $slotOptions['_syncTime'] = XenForo_Application::$time;
        $slotOptions['_syncMethod'] = __METHOD__;

        /** @var bdAd_DataWriter_Slot $slotDw */
        $slotDw = XenForo_DataWriter::create('bdAd_DataWriter_Slot');
        $slotDw->setExistingData($this->getWidgetOption('slotId'));
        $slotDw->bulkSet(array(
            'slot_name' => strip_tags($this->get('title')),
            'slot_options' => $slotOptions,
        ));
        $slotDw->save();
    }

    protected function _bdAd_deleteExistingSlot()
    {
        $widgetOptions = $this->getWidgetOptions(true);
        if (!empty($widgetOptions['slotId'])) {
            /** @var bdAd_DataWriter_Slot $slotDw */
            $slotDw = XenForo_DataWriter::create('bdAd_DataWriter_Slot', XenForo_DataWriter::ERROR_SILENT);
            if ($slotDw->setExistingData($widgetOptions['slotId'])) {
                $slotDw->set('slot_options', array());
                $slotDw->delete();
            }
        }
    }

    /**
     * @return bdAd_Model_Slot
     */
    protected function _bdAd_getSlotModel()
    {
        return $this->getModelFromCache('bdAd_Model_Slot');
    }
}