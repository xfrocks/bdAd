<?php

class bdAd_Slot_Widget extends bdAd_Slot_Abstract
{
    const AD_LAYOUT_TEXT = 'text';
    const AD_LAYOUT_IMAGE = 'image';
    const AD_LAYOUT_HTML = 'html';
    const AD_LAYOUT_ADSENSE = 'adsense';
    const AD_LAYOUT_GPT = 'gpt';

    public function verifySlotOptions(bdAd_DataWriter_Slot $dw, array $slotOptions)
    {
        if (empty($slotOptions['widgetId'])) {
            $dw->error(new XenForo_Phrase('bdad_slot_options_error_widget_id_required'), 'slot_options');
        }

        switch ($slotOptions['adLayout']) {
            case self::AD_LAYOUT_GPT:
                if (!empty($slotOptions['responsiveAds'])
                    && !empty($slotOptions['hideNonSidebar'])
                ) {
                    $dw->error(new XenForo_Phrase('bdad_slot_options_error_cannot_both_responsive_non_sidebar'),
                        'slot_options');
                }
                break;
        }

        return parent::verifySlotOptions($dw, $slotOptions);
    }

    public function allowUpload(array $slot, $optionKey)
    {
        switch ($optionKey) {
            case self::AD_LAYOUT_IMAGE:
                if (!empty($slot['slot_options']['adLayout'])
                    && $slot['slot_options']['adLayout'] === 'image'
                ) {
                    return true;
                }
                break;
        }

        return parent::allowUpload($slot, $optionKey);
    }

    public function getUploadConstraints(array $slot, $optionKey)
    {
        $constraints = parent::getUploadConstraints($slot, $optionKey);

        switch ($optionKey) {
            case self::AD_LAYOUT_IMAGE:
                if (!empty($slot['slot_options']['adLayout'])
                    && $slot['slot_options']['adLayout'] === 'image'
                ) {
                    if (!empty($slot['slot_options']['width'])) {
                        $constraints['width'] = intval($slot['slot_options']['width']);
                    }
                    if (!empty($slot['slot_options']['height'])) {
                        $constraints['height'] = intval($slot['slot_options']['height']);
                    }
                }
                break;
        }

        return $constraints;
    }

    public function assertUploadedFile(array $slot, $optionKey, XenForo_Upload $file)
    {
        switch ($optionKey) {
            case self::AD_LAYOUT_IMAGE:
                if (!empty($slot['slot_options']['adLayout'])
                    && $slot['slot_options']['adLayout'] === 'image'
                ) {
                    $width = 0;
                    $height = 0;
                    if (!empty($slot['slot_options']['width'])) {
                        $width = intval($slot['slot_options']['width']);
                    }
                    if (!empty($slot['slot_options']['height'])) {
                        $height = intval($slot['slot_options']['height']);
                    }

                    $fileWidth = intval($file->getImageInfoField('width'));
                    $fileHeight = intval($file->getImageInfoField('height'));
                    if ($width > 0 && $height > 0) {
                        if ($fileWidth < $width || $fileHeight < $height) {
                            throw new XenForo_Exception(new XenForo_Phrase('bdad_please_upload_image_dimension_x_y',
                                array(
                                    'width' => $width,
                                    'height' => $height,
                                )));
                        }
                    } elseif ($width > 0) {
                        if ($fileWidth < $width) {
                            throw new XenForo_Exception(new XenForo_Phrase('bdad_please_upload_image_width_x', array(
                                'width' => $width,
                            )));
                        }
                    } elseif ($height > 0) {
                        if ($fileHeight < $height) {
                            throw new XenForo_Exception(new XenForo_Phrase('bdad_please_upload_image_height_x', array(
                                'height' => $height,
                            )));
                        }
                    }
                }
                break;
        }

        return parent::assertUploadedFile($slot, $optionKey, $file);
    }


    public function verifyAdOptions(bdAd_DataWriter_Ad $dw, array $slot, array $adOptions)
    {
        switch ($slot['slot_options']['adLayout']) {
            case self::AD_LAYOUT_IMAGE:
                $this->_verifyAdOptions_helperLink($dw, $adOptions);
                break;
            case self::AD_LAYOUT_HTML:
                break;
            case self::AD_LAYOUT_ADSENSE:
                if (empty($adOptions['publisherId'])) {
                    $dw->error(new XenForo_Phrase('bdad_ad_options_error_option_x_required',
                        array('option' => new XenForo_Phrase('bdad_ad_adsense_publisher_id'))), 'ad_options');
                }

                if (empty($adOptions['slotId'])) {
                    $dw->error(new XenForo_Phrase('bdad_ad_options_error_option_x_required',
                        array('option' => new XenForo_Phrase('bdad_ad_adsense_slot_id'))), 'ad_options');
                }
                break;
            case self::AD_LAYOUT_GPT:
                if (empty($adOptions['adUnitPath'])) {
                    $dw->error(new XenForo_Phrase('bdad_ad_options_error_option_x_required',
                        array('option' => new XenForo_Phrase('bdad_ad_unit_path'))), 'ad_options');
                }

                if (empty($adOptions['sizeWidth'])) {
                    $dw->error(new XenForo_Phrase('bdad_ad_options_error_option_x_required',
                        array('option' => new XenForo_Phrase('width'))), 'ad_options');
                }

                if (empty($adOptions['sizeHeight'])) {
                    $dw->error(new XenForo_Phrase('bdad_ad_options_error_option_x_required',
                        array('option' => new XenForo_Phrase('height'))), 'ad_options');
                }
                break;
            default:
                $this->_verifyAdOptions_helperLink($dw, $adOptions);
        }

        return parent::verifyAdOptions($dw, $slot, $adOptions);
    }

    public function adIdsShouldBeServed($slotId)
    {
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
            case self::AD_LAYOUT_IMAGE:
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
            case self::AD_LAYOUT_HTML:
                $mapping['{html}'] = $ad['ad_options']['html'];
                break;
            case self::AD_LAYOUT_ADSENSE:
                $mapping['{adsbygoogle}'] = $this->_prepareAdHtml_adsense_render($ad, $slot);
                break;
            case self::AD_LAYOUT_GPT:
                if (empty($slot['slot_options']['responsiveAds'])) {
                    $gptRendered = $this->_prepareAdHtml_gpt_render($ad, $slot);
                } else {
                    // responsive ads: get all ads for this slot
                    $engine = bdAd_Engine::getInstance();
                    $ads = $engine->getAdsBySlotId($slot['slot_id']);
                    $ads[$ad['ad_id']] = $ad;

                    $adsByWidth = array();
                    foreach ($ads as $_ad) {
                        if (empty($_ad['ad_options']['sizeWidth'])) {
                            continue;
                        }
                        $_sizeWidth = intval($_ad['ad_options']['sizeWidth']);
                        if (isset($adsByWidth[$_sizeWidth])) {
                            continue;
                        }

                        $adsByWidth[$_sizeWidth] = $_ad;
                    }

                    if (empty($adsByWidth)) {
                        // no ads have width configured?!
                        // render the randomly picked one
                        $gptRendered = $this->_prepareAdHtml_gpt_render($ad, $slot);
                    } elseif (count($adsByWidth) < 2) {
                        // only one ad, render it directly
                        $gptRendered = $this->_prepareAdHtml_gpt_render($ad, $slot);
                    } else {
                        $gptRendered = $this->_prepareAdHtml_gpt_renderAdSet($adsByWidth, $slot);
                    }
                }
                $mapping['{googletag.display}'] = $gptRendered;
                break;
        }

        return str_replace(array_keys($mapping), array_values($mapping), $htmlWithPlaceholders);
    }

    protected function _prepareAdHtml_adsense_render(array $ad, array $slot)
    {
        return sprintf('<ins class="adsbygoogle AdSenseLoader" style="display:block;" '
            . (XenForo_Application::debugMode() ? 'data-debug="yes"' : '')
            . 'data-ad-client="%s" data-ad-slot="%s" data-ad-format="%s"></ins>',
            $ad['ad_options']['publisherId'], $ad['ad_options']['slotId'],
            !empty($ad['ad_options']['format']) ? $ad['ad_options']['format'] : 'auto');
    }

    protected function _prepareAdHtml_gpt_render(array $ad, array $slot)
    {
        return sprintf('<ins class="GptLoader" style="display:block;" '
            . (XenForo_Application::debugMode() ? 'data-debug="yes"' : '')
            . (!empty($slot['slot_options']['hideNonSidebar']) ? sprintf(' data-min-client-width="%d"',
                XenForo_Template_Helper_Core::styleProperty('maxResponsiveWideWidth')) : '')
            . 'data-ad-unit-path="%s" data-ad-size-width="%d" data-ad-size-height="%d"></ins>',
            htmlentities($ad['ad_options']['adUnitPath']),
            $ad['ad_options']['sizeWidth'], $ad['ad_options']['sizeHeight']);
    }

    protected function _prepareAdHtml_gpt_renderAdSet(array $adsByWidth, array $slot)
    {
        krsort($adsByWidth);

        $adsRendered = array();
        foreach ($adsByWidth as $adWidth => $ad) {
            $adsRendered[] = $this->_prepareAdHtml_gpt_render($ad, $slot);
        }

        return sprintf('<div class="ad-set">%s</div>', implode('', $adsRendered));
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