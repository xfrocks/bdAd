<?php

class bdAd_ControllerAdmin_Ad extends XenForo_ControllerAdmin_Abstract
{
    public function actionIndex()
    {
        $conditions = array();
        $fetchOptions = array();

        $adModel = $this->_getAdModel();
        $ads = $adModel->getAds($conditions, $fetchOptions);

        $viewParams = array(
            'ads' => $ads,
            'slots' => $this->_getSlotModel()->getSlots(),
        );

        return $this->responseView('bdAd_ViewAdmin_Ad_List', 'bdad_ad_list', $viewParams);
    }

    public function actionAdd()
    {
        $slotId = $this->_input->filterSingle('slot_id', XenForo_Input::UINT);

        if ($slotId == 0) {
            $slotIds = array_keys($this->_getSlotModel()->getList());
            if (empty($slotIds)) {
                return $this->responseRedirect(
                    XenForo_ControllerResponse_Redirect::SUCCESS,
                    XenForo_Link::buildAdminLink('ad-slots/add')
                );
            }
            $slotId = reset($slotIds);
        }

        $ad = array(
            'slot_id' => $slotId,
            'active' => 1,
        );

        return $this->_actionAddOrEdit($ad);
    }

    public function actionEdit()
    {
        $id = $this->_input->filterSingle('ad_id', XenForo_Input::UINT);
        $ad = $this->_getAdOrError($id);

        return $this->_actionAddOrEdit($ad);
    }

    public function actionOptions()
    {
        $this->_assertPostOnly();

        $id = $this->_input->filterSingle('ad_id', XenForo_Input::UINT);
        if (!empty($id)) {
            $ad = $this->_getAdOrError($id);
        } else {
            $ad = array();
        }

        $adInput = $this->_input->filter(array(
            'slot_id' => XenForo_Input::UINT,
            'ad_options' => XenForo_Input::ARRAY_SIMPLE,
        ));

        $adMerged = array_merge($ad, $adInput);

        $response = $this->_actionAddOrEdit($adMerged);
        $response->templateName = 'bdad_ad_options';

        return $response;
    }

    public function actionSave()
    {
        $this->_assertPostOnly();

        $id = $this->_input->filterSingle('ad_id', XenForo_Input::UINT);
        $dw = XenForo_DataWriter::create('bdAd_DataWriter_Ad');
        if ($id) {
            $dw->setExistingData($id);
        } else {
            $dw->set('user_id', XenForo_Visitor::getUserId());
        }

        // get phrases from input data
        $phrases = $this->_input->filterSingle('_phrases', XenForo_Input::ARRAY_SIMPLE);
        if (isset($phrases['title'])) {
            $dw->setExtraData(bdAd_DataWriter_Ad::DATA_PHRASE_TITLE, $phrases['title']);
        }
        if (isset($phrases['description'])) {
            $dw->setExtraData(bdAd_DataWriter_Ad::DATA_PHRASE_DESCRIPTION, $phrases['description']);
        }

        // get regular fields from input data
        $dwInput = $this->_input->filter(array(
            'ad_name' => XenForo_Input::STRING,
            'slot_id' => XenForo_Input::UINT,
            'active' => XenForo_Input::BOOLEAN,
        ));
        $dw->bulkSet($dwInput);

        // get options (only if the correct ones have been rendered)
        $optionsInput = $this->_input->filter(array(
            'ad_options_slot_id' => XenForo_Input::UINT,
            'ad_options' => XenForo_Input::ARRAY_SIMPLE,
        ));
        if ($optionsInput['ad_options_slot_id'] == $dw->get('slot_id')) {
            $dw->set('ad_options', $optionsInput['ad_options']);
        }

        // get configuration options
        $configOptions = $this->_input->filter(array(
            'user_criteria' => XenForo_Input::ARRAY_SIMPLE,
        ));
        $dw->set('ad_config_options', $configOptions);

        $dw->save();

        // delete uploads
        // see template "bdad_ad_options__upload" for more information
        $deleteUpload = $this->_input->filterSingle('delete_upload', XenForo_Input::UINT, array('array' => true));
        foreach ($deleteUpload as $optionKey => $attachmentId) {
            /** @var XenForo_DataWriter_Attachment $attachmentDw */
            $attachmentDw = XenForo_DataWriter::create('XenForo_DataWriter_Attachment');
            $attachmentDw->setExistingData($attachmentId);
            $attachmentDw->delete();
        }

        $redirectTarget = XenForo_Link::buildAdminLink('ads') . $this->getLastHash($dw->get('ad_id'));

        $redirectUpload = $this->_input->filterSingle('redirect_upload', XenForo_Input::STRING);
        if (!empty($redirectUpload)) {
            $redirectTarget = XenForo_Link::buildAdminLink('ads/upload', $dw->getMergedData(), array(
                'slot_id' => $dw->get('slot_id'),
                'option_key' => $redirectUpload,
            ));
        }

        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            $redirectTarget
        );
    }

    public function actionDelete()
    {
        $id = $this->_input->filterSingle('ad_id', XenForo_Input::UINT);
        $ad = $this->_getAdOrError($id);

        if ($this->isConfirmedPost()) {
            $dw = XenForo_DataWriter::create('bdAd_DataWriter_Ad');
            $dw->setExistingData($id);
            $dw->delete();

            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::SUCCESS,
                XenForo_Link::buildAdminLink('ads')
            );
        } else {
            $viewParams = array(
                'ad' => $ad,
            );

            return $this->responseView('bdAd_ViewAdmin_Ad_Delete', 'bdad_ad_delete', $viewParams);
        }
    }

    public function actionToggle()
    {
        return $this->_getToggleResponse(
            $this->_getAdModel()->getAds(),
            'bdAd_DataWriter_Ad',
            'ads'
        );
    }

    public function actionEnable()
    {
        // can be requested over GET, so check for the token manually
        $this->_checkCsrfFromToken($this->_input->filterSingle('_xfToken', XenForo_Input::STRING));

        $id = $this->_input->filterSingle('ad_id', XenForo_Input::STRING);
        return $this->_switchAdActiveStateAndGetResponse($id, 1);
    }

    public function actionDisable()
    {
        // can be requested over GET, so check for the token manually
        $this->_checkCsrfFromToken($this->_input->filterSingle('_xfToken', XenForo_Input::STRING));

        $id = $this->_input->filterSingle('ad_id', XenForo_Input::STRING);
        return $this->_switchAdActiveStateAndGetResponse($id, 0);
    }

    public function actionUpload()
    {
        $id = $this->_input->filterSingle('ad_id', XenForo_Input::UINT);
        $ad = $this->_getAdOrError($id);

        $slotId = $this->_input->filterSingle('slot_id', XenForo_Input::UINT);
        $slot = $this->_getSlotModel()->getSlotById($slotId);
        if (empty($slot)) {
            return $this->responseNoPermission();
        }

        $optionKey = $this->_input->filterSingle('option_key', XenForo_Input::STRING);
        $slotObj = bdAd_Slot_Abstract::create($slot['slot_class']);
        if (!$slotObj->allowUpload($slot, $optionKey)) {
            return $this->responseNoPermission();
        }

        if ($this->isConfirmedPost()) {
            $file = XenForo_Upload::getUploadedFile('file');
            if (empty($file)) {
                return $this->responseRedirect(
                    XenForo_ControllerResponse_Redirect::SUCCESS,
                    XenForo_Link::buildAdminLink('ads/edit', $ad)
                );
            }

            $file->setConstraints($slotObj->getUploadConstraints($slot, $optionKey));
            if (!$file->isValid()) {
                return $this->responseError($file->getErrors());
            }

            try {
                $slotObj->assertUploadedFile($slot, $optionKey, $file);
            } catch (XenForo_Exception $e) {
                return $this->responseError($e->getMessage());
            }

            /** @var XenForo_Model_Attachment $attachmentModel */
            $attachmentModel = $this->getModelFromCache('XenForo_Model_Attachment');
            $dataId = $attachmentModel->insertUploadedAttachmentData($file, $ad['user_id']);

            /** @var XenForo_DataWriter_Attachment $attachmentDw */
            $attachmentDw = XenForo_DataWriter::create('XenForo_DataWriter_Attachment');
            $attachmentDw->bulkSet(array(
                'data_id' => $dataId,
                'content_type' => 'bdad_ad',
                'content_id' => $ad['ad_id'],
                'unassociated' => 0,
            ));
            $attachmentDw->save();
            $attachmentId = $attachmentDw->get('attachment_id');

            $adDw = XenForo_DataWriter::create('bdAd_DataWriter_Ad');
            $adDw->setExistingData($ad, true);
            $adDw->set('attach_count', $ad['attach_count'] + 1);

            $newAdOptions = $ad['ad_options'];
            $newAdOptions['upload'][$optionKey] = $attachmentId;
            $adDw->set('ad_options', $newAdOptions);

            $adDw->save();

            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::SUCCESS,
                XenForo_Link::buildAdminLink('ads/edit', $ad)
            );
        } else {
            $viewParams = array(
                'ad' => $ad,
                'slot' => $slot,
                'slotObj' => $slotObj,
                'optionKey' => $optionKey,
            );

            return $this->responseView('bdAd_ViewAdmin_Ad_Upload', 'bdad_ad_upload', $viewParams);
        }
    }

    protected function _preDispatch($action)
    {
        $this->assertAdminPermission('bdAd');
    }

    protected function _getAdOrError($id, array $fetchOptions = array())
    {
        $ad = $this->_getAdModel()->getAdById($id, $fetchOptions);

        if (empty($ad)) {
            throw $this->responseException($this->responseError(new XenForo_Phrase('bdad_ad_not_found'), 404));
        }

        return $ad;
    }

    protected function _actionAddOrEdit(array $ad)
    {
        $ad = $this->_getAdModel()->prepareAdUploads($ad);

        $adUserCriteria = array();
        if (!empty($ad['ad_config_options']['user_criteria'])) {
            $adUserCriteria = $ad['ad_config_options']['user_criteria'];
        }

        $viewParams = array(
            'ad' => $ad,
            'userCriteria' => XenForo_Helper_Criteria::prepareCriteriaForSelection($adUserCriteria),
            'userCriteriaData' => XenForo_Helper_Criteria::getDataForUserCriteriaSelection()
        );

        $slots = $this->_getSlotModel()->getSlots();

        if (isset($ad['slot_id'])) {
            foreach ($slots as $slot) {
                if ($slot['slot_id'] == $ad['slot_id']) {
                    $viewParams['slot'] = $slot;
                    $viewParams['slotObj'] = bdAd_Slot_Abstract::create($slot['slot_class'], false);
                }
            }
        }

        $slotClasses = $this->_getSlotModel()->getSlotClassTitles();
        $listSlot = array();
        foreach ($slotClasses as $slotClass => $slotClassTitle) {
            $optGroup = sprintf('[%s]', $slotClassTitle);

            foreach (array_keys($slots) as $slotId) {
                if ($slots[$slotId]['slot_class'] === $slotClass) {
                    if (!isset($listSlot[$optGroup])) {
                        $listSlot[$optGroup] = array();
                    }

                    $listSlot[$optGroup][$slotId] = $slots[$slotId]['slot_name'];
                    unset($slots[$slotId]);
                }
            }
        }
        if (!empty($slots)) {
            $listSlotNew = array();
            foreach ($slots as $slotId => $slot) {
                $listSlotNew[$slotId] = $slot['slot_name'];
            }
            foreach ($listSlot as $key => $value) {
                $listSlotNew[$key] = $value;
            }
            $listSlot = $listSlotNew;
        }
        $viewParams['listSlot'] = $listSlot;

        return $this->responseView('bdAd_ViewAdmin_Ad_Edit', 'bdad_ad_edit', $viewParams);
    }

    protected function _switchAdActiveStateAndGetResponse($adId, $activeState)
    {
        $dw = XenForo_DataWriter::create('bdAd_DataWriter_Ad');
        $dw->setExistingData($adId);
        $dw->set('active', $activeState);
        $dw->save();

        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildAdminLink('ads') . $this->getLastHash($adId)
        );
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