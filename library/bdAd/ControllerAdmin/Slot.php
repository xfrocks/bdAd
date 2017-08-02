<?php

class bdAd_ControllerAdmin_Slot extends XenForo_ControllerAdmin_Abstract
{
    public function actionIndex()
    {
        $conditions = array();
        $fetchOptions = array();

        $slotModel = $this->_getSlotModel();
        $slots = $slotModel->getSlots($conditions, $fetchOptions);

        $viewParams = array(
            'slots' => $slots,
            'slotClassTitles' => $slotModel->getSlotClassTitles(),
        );

        return $this->responseView('bdAd_ViewAdmin_Slot_List', 'bdad_slot_list', $viewParams);
    }

    public function actionAdd()
    {
        $slotClasses = $this->_getSlotModel()->getSlotClasses();

        $slot = array(
            'slot_class' => reset($slotClasses),
            'active' => 1,
        );

        return $this->_actionAddOrEdit($slot);
    }

    public function actionEdit()
    {
        $id = $this->_input->filterSingle('slot_id', XenForo_Input::UINT);
        $slot = $this->_getSlotOrError($id);

        return $this->_actionAddOrEdit($slot);
    }

    public function actionOptions()
    {
        $this->_assertPostOnly();

        $slot = $this->_input->filter(array(
            'slot_class' => XenForo_Input::STRING,
            'slot_options' => XenForo_Input::ARRAY_SIMPLE,
        ));

        $response = $this->_actionAddOrEdit($slot);
        $response->templateName = 'bdad_slot_options';

        return $response;
    }

    public function actionSave()
    {
        $this->_assertPostOnly();

        $id = $this->_input->filterSingle('slot_id', XenForo_Input::UINT);

        /** @var bdAd_DataWriter_Slot $dw */
        $dw = XenForo_DataWriter::create('bdAd_DataWriter_Slot');
        if ($id) {
            $dw->setExistingData($id);
        }

        // get regular fields from input data
        $dwInput = $this->_input->filter(array(
            'slot_name' => XenForo_Input::STRING,
            'slot_class' => XenForo_Input::STRING,
            'active' => XenForo_Input::BOOLEAN,
        ));
        $dw->bulkSet($dwInput);

        // get options (only if the correct ones have been rendered)
        $optionsInput = $this->_input->filter(array(
            'slot_options_class' => XenForo_Input::STRING,
            'slot_options' => XenForo_Input::ARRAY_SIMPLE,
        ));
        if ($optionsInput['slot_options_class'] == $dw->get('slot_class')) {
            $dw->set('slot_options', $optionsInput['slot_options']);
        }

        $dw->save();

        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildAdminLink('ad-slots') . $this->getLastHash($dw->get('slot_id'))
        );
    }

    public function actionDelete()
    {
        $id = $this->_input->filterSingle('slot_id', XenForo_Input::UINT);
        $slot = $this->_getSlotOrError($id);

        if ($this->isConfirmedPost()) {
            /** @var bdAd_DataWriter_Slot $dw */
            $dw = XenForo_DataWriter::create('bdAd_DataWriter_Slot');
            $dw->setExistingData($id);
            $dw->delete();

            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::SUCCESS,
                XenForo_Link::buildAdminLink('ad-slots')
            );
        } else {
            $viewParams = array(
                'slot' => $slot,
            );

            return $this->responseView('bdAd_ViewAdmin_Slot_Delete', 'bdad_slot_delete', $viewParams);
        }
    }

    public function actionToggle()
    {
        return $this->_getToggleResponse(
            $this->_getSlotModel()->getSlots(),
            'bdAd_DataWriter_Slot',
            'ad-slots'
        );
    }

    public function actionEnable()
    {
        // can be requested over GET, so check for the token manually
        $this->_checkCsrfFromToken($this->_input->filterSingle('_xfToken', XenForo_Input::STRING));

        $id = $this->_input->filterSingle('slot_id', XenForo_Input::STRING);
        return $this->_switchSlotActiveStateAndGetResponse($id, 1);
    }

    public function actionDisable()
    {
        // can be requested over GET, so check for the token manually
        $this->_checkCsrfFromToken($this->_input->filterSingle('_xfToken', XenForo_Input::STRING));

        $id = $this->_input->filterSingle('slot_id', XenForo_Input::STRING);
        return $this->_switchSlotActiveStateAndGetResponse($id, 0);
    }

    protected function _preDispatch($action)
    {
        $this->assertAdminPermission('bdAd');
    }

    protected function _getSlotOrError($id, array $fetchOptions = array())
    {
        $slot = $this->_getSlotModel()->getSlotById($id, $fetchOptions);

        if (empty($slot)) {
            throw $this->responseException($this->responseError(new XenForo_Phrase('bdad_slot_not_found'), 404));
        }

        return $slot;
    }

    protected function _actionAddOrEdit(array $slot)
    {
        $viewParams = array(
            'slot' => $slot,
            'slotClasses' => $this->_getSlotModel()->getSlotClassTitles(),
        );

        if (isset($slot['slot_class'])) {
            $viewParams['slotObj'] = bdAd_Slot_Abstract::create($slot['slot_class'], false);
        }

        if (!empty($viewParams['slotObj'])
            && !isset($viewParams['slotClasses'][$slot['slot_class']])
        ) {
            $viewParams['slotClassNotListed'] = true;
        }

        return $this->responseView('bdAd_ViewAdmin_Slot_Edit', 'bdad_slot_edit', $viewParams);
    }

    protected function _switchSlotActiveStateAndGetResponse($slotId, $activeState)
    {
        $dw = XenForo_DataWriter::create('bdAd_DataWriter_Slot');
        $dw->setExistingData($slotId);
        $dw->set('active', $activeState);
        $dw->save();

        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildAdminLink('ad-slots') . $this->getLastHash($slotId)
        );
    }

    /**
     * @return bdAd_Model_Slot
     */
    protected function _getSlotModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('bdAd_Model_Slot');
    }
}
