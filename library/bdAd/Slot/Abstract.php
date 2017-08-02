<?php

abstract class bdAd_Slot_Abstract extends XenForo_Model
{
    public function prepareSlotOptionsTemplate(XenForo_View $view, array $slot)
    {
        $template = $this->_getSlotOptionsTemplate();
        if (empty($template)) {
            return null;
        }

        return $slotOptionsTemplate = $view->createTemplateObject($template, array(
            'slot' => $slot,
        ));
    }

    public function verifySlotOptions(
        /** @noinspection PhpUnusedParameterInspection */
        bdAd_DataWriter_Slot $dw,
        array $slotOptions
    ) {
        return true;
    }

    public function prepareAdOptionsTemplate(XenForo_View $view, array $ad, array $slot)
    {
        $template = $this->_getAdOptionsTemplate();
        if (empty($template)) {
            return null;
        }

        return $slotOptionsTemplate = $view->createTemplateObject($template, array(
            'ad' => $ad,
            'slot' => $slot,
        ));
    }

    public function checkSlotsOptionsCompatibility(array $thisSlot, array $otherSlot)
    {
        return $thisSlot['slot_class'] === $otherSlot['slot_class'];
    }

    public function allowUpload(
        /** @noinspection PhpUnusedParameterInspection */
        array $slot,
        $optionKey
    ) {
        return false;
    }

    public function getUploadConstraints(array $slot, $optionKey)
    {
        if (empty($slot['slot_id'])
            || empty($optionKey)
        ) {
            return array();
        }

        return array(
            'extensions' => array('jpg', 'jpeg', 'jpe', 'png', 'gif'),
        );
    }

    public function assertUploadedFile(
        /** @noinspection PhpUnusedParameterInspection */
        array $slot,
        $optionKey,
        XenForo_Upload $file
    ) {
        return true;
    }

    public function verifyAdOptions(
        /** @noinspection PhpUnusedParameterInspection */
        bdAd_DataWriter_Ad $dw,
        array $slot,
        array $adOptions
    ) {
        return true;
    }

    final public function prepareAdHtml($adId, $htmlWithPlaceholders)
    {
        $engine = bdAd_Engine::getInstance();
        list($slot, $ad) = $engine->getServedSlotAndAd($adId);
        if (empty($ad)) {
            return '';
        }

        $mapping = $this->_prepareAdHtmlMapping($ad, $slot, array(
            '{_adClass}' => sprintf(' ad-%d slot-%d', $ad['ad_id'], $slot['slot_id']),
            '{_adAttributes}' => sprintf(' data-ad-id="%d" data-ad-slot="%d"', $ad['ad_id'], $slot['slot_id']),
        ));
        if (!is_array($mapping)) {
            return '';
        }

        $html = str_replace(array_keys($mapping), array_values($mapping), $htmlWithPlaceholders);
        return $html;
    }

    /**
     * @return string
     * @deprecated
     */
    final protected function _prepareAdHtml()
    {
        return '';
    }

    protected function _getSlotOptionsTemplate()
    {
        return '';
    }

    protected function _getAdOptionsTemplate()
    {
        return '';
    }

    protected function _prepareSlotOptions_helperForumIds(XenForo_Template_Abstract $template, array $slot)
    {
        $selectedForumIds = array();
        if (isset($slot['slot_options']['forumIds'])
            && is_array($slot['slot_options']['forumIds'])
        ) {
            $selectedForumIds = $slot['slot_options']['forumIds'];
        }

        /** @var XenForo_Model_Node $nodeModel */
        $nodeModel = $this->getModelFromCache('XenForo_Model_Node');
        $nodes = $nodeModel->getAllNodes();
        $forums = array();
        foreach ($nodes as $node) {
            if ($node['node_type_id'] === 'Forum') {
                $forums[] = array(
                    'value' => $node['node_id'],
                    'label' => $node['title'],
                    'depth' => $node['depth'],
                    'selected' => in_array($node['node_id'], $selectedForumIds),
                );
            }
        }

        $template->setParam('forums', $forums);
    }

    protected function _prepareAdOptions_helperSlotOptionsForumIds(XenForo_Template_Abstract $template, array $slot)
    {
        $forums = array();

        if (isset($slot['slot_options']['forumIds'])
            && is_array($slot['slot_options']['forumIds'])
        ) {
            /** @var XenForo_Model_Forum $nodeModel */
            $nodeModel = $this->getModelFromCache('XenForo_Model_Forum');
            $forums = $nodeModel->getForumsByIds($slot['slot_options']['forumIds']);
        }

        $template->setParam('slotOptionsForums', $forums);
    }

    protected function _prepareAdOptions_helperSlotOptionsUsername(
        XenForo_Template_Abstract $template,
        /** @noinspection PhpUnusedParameterInspection */
        array $slot
    ) {
        /** @var XenForo_Model_Phrase $phraseModel */
        $phraseModel = $this->getModelFromCache('XenForo_Model_Phrase');
        $phraseIds = $phraseModel->getPhraseIdInLanguagesByTitle('bdad_username');

        $languages = XenForo_Application::get('languages');

        $usernamePhrases = array();
        foreach (array_keys($languages) as $languageId) {
            if (isset($phraseIds[$languageId])) {
                $phraseLink = XenForo_Link::buildAdminLink('phrases/edit', array(
                    'phrase_id' => $phraseIds[$languageId],
                ));
            } else {
                $phraseLink = XenForo_Link::buildAdminLink('phrases/edit', array(
                    'phrase_id' => $phraseIds[0],
                ), array('language_id' => $languageId));
            }

            $usernamePhrases[] = array(
                'link' => $phraseLink,
                'title' => $languages[$languageId]['title'],
            );
        }

        $template->setParam('usernamePhrases', $usernamePhrases);
    }

    protected function _verifyAdOptions_helperLink(bdAd_DataWriter_Ad $dw, array $adOptions)
    {
        if (empty($adOptions['link'])
            || !Zend_Uri::check($adOptions['link'])
        ) {
            $dw->error(new XenForo_Phrase('bdad_ad_options_error_link_required'), 'ad_options');
        }
    }

    protected function _adIdsShouldBeServed_helperForumIds(array $slot, array $forum)
    {
        $forumIds = array();
        if (isset($slot['slot_options']['forumIds'])) {
            $forumIds = array_map('intval', $slot['slot_options']['forumIds']);
        }

        if (count($forumIds) === 0) {
            return true;
        }

        return in_array($forum['node_id'], $forumIds);
    }

    protected function _adIdsShouldBeServed_helperMarkServed($slotId, array $ad)
    {
        $ads = func_get_args();
        array_shift($ads);

        $engine = bdAd_Engine::getInstance();
        $adSlotIds = array();
        foreach ($ads as $ad) {
            $engine->markServed($slotId, $ad['ad_id']);
            $this->_adIdsShouldBeServed_helperLogAdView($ad);

            $adSlotIds[] = $ad['adSlotId'];
        }

        if (count($adSlotIds) > 0) {
            return implode(',', $adSlotIds);
        } else {
            return 0;
        }
    }

    protected function _adIdsShouldBeServed_helperLogAdView(array $ad)
    {
        /** @var bdAd_Model_Log $logModel */
        $logModel = $this->getModelFromCache('bdAd_Model_Log');
        $logModel->logAdView($ad['ad_id']);
    }

    abstract protected function _prepareAdHtmlMapping(array $ad, array $slot, array $mapping);

    protected function _prepareAdHtml_helperAdPhrase(array $ad, $type)
    {
        if (isset($ad['safePhrases'][$type])) {
            $languageId = XenForo_Phrase::getLanguageId();
            if (!empty($ad['safePhrases'][$type][$languageId])) {
                return $ad['safePhrases'][$type][$languageId];
            }
        }

        return '';
    }

    protected function _prepareAdHtml_helperLink(array $ad)
    {
        if (isset($ad['ad_options']['link'])) {
            return bdAd_Helper_Security::getClickTrackingUrl($ad['ad_id'], $ad['ad_options']['link']);
        }

        return '';
    }

    protected function _prepareAdHtml_helperUploadUrl(array $ad, $optionKey)
    {
        if (isset($ad['safeAttachments'][$optionKey])) {
            return XenForo_Link::buildPublicLink('attachments', $ad['safeAttachments'][$optionKey]);
        }

        return '';
    }

    /**
     * @param string $class
     * @param bool $throw
     *
     * @return bdAd_Slot_Abstract|null
     * @throws Exception
     * @throws XenForo_Exception
     */
    public static function create($class, $throw = true)
    {
        static $objs = array();

        if (!isset($objs[$class])) {
            try {
                $createClass = XenForo_Application::resolveDynamicClass($class, 'model');
                if (!$createClass) {
                    throw new XenForo_Exception("Invalid slot class '$class' specified");
                }

                $obj = new $createClass;
                if (!($obj instanceof bdAd_Slot_Abstract)) {
                    throw new XenForo_Exception("Illegal slot class '$class' specified");
                }

                $objs[$class] = $obj;
            } catch (XenForo_Exception $e) {
                if ($throw) {
                    throw $e;
                } elseif (XenForo_Application::debugMode()) {
                    XenForo_Error::logException($e, false, __METHOD__);
                }

                return null;
            }
        }

        return $objs[$class];
    }
}