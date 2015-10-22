<?php

class bdAd_AttachmentHandler_Ad extends XenForo_AttachmentHandler_Abstract
{
    protected $_contentIdKey = 'ad_id';
    protected $_contentRoute = 'ads';

    // new XenForo_Phrase('bdad_ad')
    protected $_contentTypePhraseKey = 'bdad_ad';

    protected function _canUploadAndManageAttachments(array $contentData, array $viewingUser)
    {
        return false;
    }

    protected function _canViewAttachment(array $attachment, array $viewingUser)
    {
        return true;
    }

    public function attachmentPostDelete(array $attachment, Zend_Db_Adapter_Abstract $db)
    {
        /** @var bdAd_Model_Ad $adModel */
        $adModel = XenForo_Model::create('bdAd_Model_Ad');
        $ad = $adModel->getAdById($attachment['content_id']);
        if (empty($ad)) {
            // huh?!
            return;
        }

        $hasChanges = false;

        $newAdOptions = $ad['ad_options'];
        if (!empty($newAdOptions['upload'])) {
            foreach ($newAdOptions['upload'] as &$attachmentId) {
                if ($attachmentId == $attachment['attachment_id']) {
                    $attachmentId = 0;
                    $hasChanges = true;
                }
            }
        }

        $newAttachCount = $ad['attach_count'];
        if ($newAttachCount > 0) {
            $newAttachCount--;
            $hasChanges = true;
        }

        if ($hasChanges) {
            $adDw = XenForo_DataWriter::create('bdAd_DataWriter_Ad');
            $adDw->setExistingData($ad, true);
            $adDw->bulkSet(array(
                'ad_options' => $newAdOptions,
                'attach_count' => $newAttachCount,
            ));
            $adDw->save();
        }
    }
}