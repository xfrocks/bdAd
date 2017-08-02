<?php

class bdAd_Slot_Thread extends bdAd_Slot_Abstract
{
    public function prepareSlotOptionsTemplate(XenForo_View $view, array $slot)
    {
        $template = parent::prepareSlotOptionsTemplate($view, $slot);

        $this->_prepareSlotOptions_helperForumIds($template, $slot);

        return $template;
    }

    public function prepareAdOptionsTemplate(XenForo_View $view, array $ad, array $slot)
    {
        $template = parent::prepareAdOptionsTemplate($view, $ad, $slot);

        $this->_prepareAdOptions_helperSlotOptionsForumIds($template, $slot);

        return $template;
    }

    public function allowUpload(array $slot, $optionKey)
    {
        switch ($optionKey) {
            case 'avatar':
                return true;
        }

        return parent::allowUpload($slot, $optionKey);
    }

    public function verifyAdOptions(bdAd_DataWriter_Ad $dw, array $slot, array $adOptions)
    {
        $this->_verifyAdOptions_helperLink($dw, $adOptions);

        return parent::verifyAdOptions($dw, $slot, $adOptions);
    }

    public function adIdsShouldBeServed($position)
    {
        $args = func_get_args();
        array_shift($args);

        $forum = null;
        $thread = null;
        $searchResultIndex = null;
        switch ($position) {
            case 'thread_list_item':
                if (count($args) < 2) {
                    return 0;
                }

                $forum = array_shift($args);
                if (!is_array($forum)
                    || !isset($forum['node_id'])
                ) {
                    return 0;
                }

                $thread = array_shift($args);
                if (!is_array($thread)
                    || !isset($thread['thread_id'])
                    || !isset($thread['_bdAd_indexInThreads'])
                ) {
                    return 0;
                }
                break;
            case '_search_result':
                if (count($args) < 1) {
                    return 0;
                }

                $searchResultIndex = array_shift($args);
                if (!is_int($searchResultIndex)) {
                    return 0;
                }
                break;
            default:
                return 0;
        }

        $engine = bdAd_Engine::getInstance();
        $slots = $engine->getSlotsByClass(__CLASS__);
        if (empty($slots)) {
            return 0;
        }

        $slot = null;
        foreach ($slots as $_slot) {
            if ($forum !== null) {
                // forum view
                if (!$this->_adIdsShouldBeServed_helperForumIds($_slot, $forum)) {
                    continue;
                }

                $threadIndex = 0;
                if (isset($_slot['slot_options']['threadIndex'])) {
                    $threadIndex = intval($_slot['slot_options']['threadIndex']);
                }
                if ($threadIndex != $thread['_bdAd_indexInThreads']) {
                    continue;
                }
            } elseif ($searchResultIndex !== null) {
                // search results view
                $optionIndex = 0;
                if (isset($_slot['slot_options']['threadIndex'])) {
                    $optionIndex = intval($_slot['slot_options']['threadIndex']);
                }
                if ($optionIndex != $searchResultIndex) {
                    continue;
                }
            } else {
                // unknown view?!
            }

            $slot = $_slot;
            break;
        }
        if ($slot === null) {
            return 0;
        }

        $ad = $engine->getRandomAdBySlotId($slot['slot_id']);
        if ($ad === null) {
            return 0;
        }

        return $this->_adIdsShouldBeServed_helperMarkServed($slot['slot_id'], $ad);
    }

    protected function _prepareAdHtmlMapping(array $ad, array $slot, array $mapping)
    {
        $mapping['{title}'] = $this->_prepareAdHtml_helperAdPhrase($ad, 'title');
        $mapping['{description}'] = $this->_prepareAdHtml_helperAdPhrase($ad, 'description');
        $mapping['{link}'] = $this->_prepareAdHtml_helperLink($ad);

        // avatar url
        $avatarUrl = $this->_prepareAdHtml_helperUploadUrl($ad, 'avatar');
        if (empty($avatarUrl)) {
            $avatarUrl = XenForo_Template_Helper_Core::getAvatarUrl(array(), 's', 'default');
        }
        $mapping['{avatarUrl}'] = $avatarUrl;

        // sponsored text
        if (!empty($slot['slot_options']['sponsoredText'])) {
            $sponsoredText = $slot['slot_options']['sponsoredText'];
        } else {
            $sponsoredText = new XenForo_Phrase('bdad_sponsored_text');
        }
        $mapping['{sponsoredText}'] = $sponsoredText;

        return $mapping;
    }

    protected function _getSlotOptionsTemplate()
    {
        return 'bdad_slot_options_thread';
    }

    protected function _getAdOptionsTemplate()
    {
        return 'bdad_ad_options_thread';
    }
}