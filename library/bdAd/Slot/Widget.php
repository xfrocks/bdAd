<?php

class bdAd_Slot_Widget extends bdAd_Slot_Abstract
{
    public function verifySlotOptions(bdAd_DataWriter_Slot $dw, array $slotOptions)
    {
        if (empty($slotOptions['widgetId'])) {
            $dw->error(new XenForo_Phrase('bdad_slot_options_error_widget_id_required'), 'slot_options');
        }

        switch ($slotOptions['adLayout']) {
            case 'gpt':
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
            case 'image':
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
            case 'image':
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
            case 'image':
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
            case 'image':
                $this->_verifyAdOptions_helperLink($dw, $adOptions);
                break;
            case 'html':
                break;
            case 'gpt':
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
            case 'gpt':
                $mapping['{googletag.display}'] = $this->_prepareAdHtml_gpt_render($ad, $slot);
                break;
        }

        return str_replace(array_keys($mapping), array_values($mapping), $htmlWithPlaceholders);
    }

    protected function _prepareAdHtml_gpt_render(array $ad, array $slot)
    {
        if (!empty($slot['slot_options']['hideNonSidebar'])) {
            return $this->_prepareAdHtml_gpt_getHideNonSidebarDisplayCode($ad, $slot);
        }

        if (empty($slot['slot_options']['responsiveAds'])) {
            return $this->_prepareAdHtml_gpt_getDisplayCode($ad, $slot);
        }

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
            return $this->_prepareAdHtml_gpt_getDisplayCode($ad, $slot);
        } elseif (count($adsByWidth) < 2) {
            // only one ad, render it directly
            return $this->_prepareAdHtml_gpt_getDisplayCode(reset($adsByWidth), $slot);
        }

        return $this->_prepareAdHtml_gpt_getResponsiveDisplayCode($adsByWidth, $slot);
    }

    protected function _prepareAdHtml_gpt_getDisplayCode(array $ad, array $slot)
    {
        $divId = bdAd_Engine::gpt_getContainerElementId($ad);
        $style = '';
        if (!empty($ad['ad_options']['sizeWidth']) && !empty($ad['ad_options']['sizeHeight'])) {
            $style = sprintf(' style="width: %dpx; height: %dpx;"',
                $ad['ad_options']['sizeWidth'], $ad['ad_options']['sizeHeight']);
        }

        if (!bdAd_Option::get('gptStaticJs')) {
            bdAd_Engine::gpt_generateBootstrapJs($ad, bdAd_Listener::$headerScripts);
        }

        bdAd_Engine::getInstance()->markServed($slot['slot_id'], $ad['ad_id']);

        return sprintf('<' . 'div id="%1$s" class="adContainer"%2$s>'
            . '<script>googletag.cmd.push(function(){'
            . 'googletag.display("%1$s");});</script></div>', $divId, $style);
    }

    protected function _prepareAdHtml_gpt_getHideNonSidebarDisplayCode(array $ad, array $slot)
    {
        $wideWidth = intval(XenForo_Template_Helper_Core::styleProperty('maxResponsiveWideWidth'));

        $displayCodeOriginal = $this->_prepareAdHtml_gpt_getDisplayCode($ad, $slot);
        $displayCodeNoScript = str_replace('script', 'scr_ipt', $displayCodeOriginal);

        return sprintf('<' . 'script>(function() {'
            . 'if (document.documentElement.clientWidth > %1$d) {'
            . 'document.write(%2$s.replace(/scr_ipt/g, "script"));'
            . '}'
            . '})();</script>', $wideWidth, json_encode($displayCodeNoScript));
    }

    protected function _prepareAdHtml_gpt_getResponsiveDisplayCode(array $adsByWidth, array $slot)
    {
        krsort($adsByWidth);
        $wideWidth = intval(XenForo_Template_Helper_Core::styleProperty('maxResponsiveWideWidth'));
        $mediumWidth = intval(XenForo_Template_Helper_Core::styleProperty('maxResponsiveMediumWidth'));
        $narrowWidth = intval(XenForo_Template_Helper_Core::styleProperty('maxResponsiveNarrowWidth'));
        $sidebarWidth = intval(XenForo_Template_Helper_Core::styleProperty('sidebar.width'));
        $contentPadding = intval(XenForo_Template_Helper_Core::styleProperty('content.padding-left'))
            + intval(XenForo_Template_Helper_Core::styleProperty('content.padding-right'));

        $widthMapping = array();
        $widthMapping[$wideWidth] = $wideWidth - $sidebarWidth - $contentPadding;
        $widthMapping[$mediumWidth] = $mediumWidth - $contentPadding / 2;
        $widthMapping[$narrowWidth] = $narrowWidth;

        foreach (array_keys($adsByWidth) as $adWidth) {
            if ($adWidth > $widthMapping[$wideWidth]) {
                $adRequiredClientWidth = $adWidth + $sidebarWidth + $contentPadding;
                $widthMapping[$adRequiredClientWidth] = $adWidth;
            }

            if ($adWidth < $widthMapping[$narrowWidth]) {
                $adRequiredClientWidth = $adWidth;
                $widthMapping[$adRequiredClientWidth] = $adWidth;
            }
        }
        krsort($widthMapping);

        $widthToAd = array();
        foreach ($widthMapping as $clientWidth => $usableWidth) {
            $ad = null;
            foreach (array_keys($adsByWidth) as $adWidth) {
                if ($adWidth <= $usableWidth) {
                    $ad = $adsByWidth[$adWidth];
                    break;
                }
            }
            if (empty($ad)) {
                continue;
            }

            $widthToAd[$clientWidth] = $ad;
        }

        $prevClientWidth = -1;
        $prevAdId = -1;
        foreach (array_keys($widthToAd) as $clientWidth) {
            if ($prevClientWidth === -1
                || $prevAdId === -1
                || $prevAdId == $widthToAd[$clientWidth]['ad_id']
            ) {
                if ($prevClientWidth !== -1) {
                    unset($widthToAd[$prevClientWidth]);
                }
            }

            $prevAdId = $widthToAd[$clientWidth]['ad_id'];
            $prevClientWidth = $clientWidth;
        }

        $displayCode = '';
        foreach ($widthToAd as $clientWidth => $ad) {
            if (!empty($displayCode)) {
                $displayCode .= ' else ';
            }

            $displayCodeOriginal = $this->_prepareAdHtml_gpt_getDisplayCode($ad, $slot);
            $displayCodeNoScript = str_replace('script', 'scr_ipt', $displayCodeOriginal);

            $displayCode .= sprintf('if (clientWidth > %1$d) {'
                . 'document.write(%2$s.replace(/scr_ipt/g, "script"));'
                . '}', $clientWidth,
                json_encode($displayCodeNoScript));
        }

        return sprintf('<' . 'script>(function() {'
            . 'var clientWidth = document.documentElement.clientWidth;'
            . '%s})();</script>', $displayCode);
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