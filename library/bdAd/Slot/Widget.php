<?php

class bdAd_Slot_Widget extends bdAd_Slot_Abstract
{
    public function verifySlotOptions(bdAd_DataWriter_Slot $dw, array $slotOptions)
    {
        if (empty($slotOptions['widgetId'])) {
            $dw->error(new XenForo_Phrase('bdad_slot_options_error_widget_id_required'), 'slot_options');
        }

        return parent::verifySlotOptions($dw, $slotOptions);
    }

    public function allowUpload($optionKey)
    {
        switch ($optionKey) {
            case 'image':
                return true;
        }

        return parent::allowUpload($optionKey);
    }

    public function verifyAdOptions(bdAd_DataWriter_Ad $dw, array $slot, array $adOptions)
    {
        switch ($slot['slot_options']['adLayout']) {
            case 'image':
                $this->_verifyAdOptions_helperLink($dw, $adOptions);
                break;
            case 'html':
                break;
            default:
                $this->_verifyAdOptions_helperLink($dw, $adOptions);
        }

        return parent::verifyAdOptions($dw, $slot, $adOptions);
    }

    public function adIdsShouldBeServed()
    {
        $args = func_get_args();
        if (count($args) < 1) {
            return 0;
        }

        $slotId = array_shift($args);
        if (empty($slotId)) {
            return 0;
        }

        $engine = bdAd_Engine::getInstance();
        $slots = $engine->getSlotsByClass(__CLASS__);
        if (empty($slots)
            || !isset($slots[$slotId])
        ) {
            return 0;
        }

        $ad = $engine->getRandomAdBySlotId($slotId);
        if ($ad === null) {
            return 0;
        }

        $engine->markServed($slotId, $ad['ad_id']);

        return $ad['ad_id'];
    }

    protected function _prepareAdHtml(array $ad, array $slot, $htmlWithPlaceholders)
    {
        $mapping = array(
            '{title}' => $this->_prepareAdHtml_helperAdPhrase($ad, 'title'),
            '{description}' => $this->_prepareAdHtml_helperAdPhrase($ad, 'description'),
            '{link}' => $this->_prepareAdHtml_helperLink($ad),
        );

        switch ($slot['slot_options']['adLayout']) {
            case 'image':
                // image url
                $imageWidth = XenForo_Template_Helper_Core::styleProperty('sidebar.width')
                    - XenForo_Template_Helper_Core::styleProperty('secondaryContent.padding-left')
                    - XenForo_Template_Helper_Core::styleProperty('secondaryContent.padding-right');
                $imageHeight = $imageWidth;
                $imageUrl = $this->_prepareAdHtml_helperUploadUrl($ad, 'image');
                if (empty($imageUrl)) {
                    $imageUrl = sprintf('http://placehold.it/%dx%d', $imageWidth, $imageHeight);
                }
                $mapping['{imageUrl}'] = $imageUrl;
                $mapping['{imageWidth}'] = $imageWidth;
                $mapping['{imageHeight}'] = $imageHeight;
                break;
            case 'html':
                $mapping['{html}'] = $ad['ad_options']['html'];
                break;
        }

        return str_replace(array_keys($mapping), array_values($mapping), $htmlWithPlaceholders);
    }

    protected function _getSlotOptionsTemplate()
    {
        return 'bdad_slot_options_widget';
    }

    protected function _getAdOptionsTemplate()
    {
        return 'bdad_ad_options_widget';
    }

}