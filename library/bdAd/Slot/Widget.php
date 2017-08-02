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
                    $dw->error(
                        new XenForo_Phrase('bdad_slot_options_error_cannot_both_responsive_non_sidebar'),
                        'slot_options'
                    );
                }
                break;
        }

        if (!empty($slotOptions['hideSidebar'])
            && !empty($slotOptions['hideNonSidebar'])
        ) {
            $dw->error(new XenForo_Phrase('bdad_slot_options_error_cannot_both_hide'), 'slot_options');
        }

        return parent::verifySlotOptions($dw, $slotOptions);
    }

    public function checkSlotsOptionsCompatibility(array $thisSlot, array $otherSlot)
    {
        if (!parent::checkSlotsOptionsCompatibility($thisSlot, $otherSlot)) {
            return false;
        }

        return $thisSlot['slot_options']['adLayout'] === $otherSlot['slot_options']['adLayout'];
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
                            throw new XenForo_Exception(new XenForo_Phrase(
                                'bdad_please_upload_image_dimension_x_y',
                                array(
                                    'width' => $width,
                                    'height' => $height,
                                )
                            ));
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
                    $dw->error(new XenForo_Phrase(
                        'bdad_ad_options_error_option_x_required',
                        array('option' => new XenForo_Phrase('bdad_ad_adsense_publisher_id'))
                    ), 'ad_options');
                }

                if (empty($adOptions['slotId'])) {
                    $dw->error(new XenForo_Phrase(
                        'bdad_ad_options_error_option_x_required',
                        array('option' => new XenForo_Phrase('bdad_ad_adsense_slot_id'))
                    ), 'ad_options');
                }
                break;
            case self::AD_LAYOUT_GPT:
                if (empty($adOptions['adUnitPath'])) {
                    $dw->error(new XenForo_Phrase(
                        'bdad_ad_options_error_option_x_required',
                        array('option' => new XenForo_Phrase('bdad_ad_unit_path'))
                    ), 'ad_options');
                }

                if (empty($adOptions['sizeWidth'])) {
                    $dw->error(new XenForo_Phrase(
                        'bdad_ad_options_error_option_x_required',
                        array('option' => new XenForo_Phrase('width'))
                    ), 'ad_options');
                }

                if (empty($adOptions['sizeHeight'])) {
                    $dw->error(new XenForo_Phrase(
                        'bdad_ad_options_error_option_x_required',
                        array('option' => new XenForo_Phrase('height'))
                    ), 'ad_options');
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

        return $this->_adIdsShouldBeServed_helperMarkServed($slotId, $ad);
    }

    protected function _adIdsShouldBeServed_helperLogAdView(array $ad)
    {
        if (!empty($ad['ad_options']['publisherId'])) {
            // adsense, bypass logging
            return;
        } elseif (!empty($ad['ad_options']['adUnitPath'])) {
            // gpt ad, bypass logging
            return;
        }

        parent::_adIdsShouldBeServed_helperLogAdView($ad);
    }

    protected function _prepareAdHtmlMapping(array $ad, array $slot, array $mapping)
    {
        $mapping['{_adStyles}'] = 'display: none';

        $adAttributes = array(
            ' data-loader-version="2017080202"',
            sprintf(' data-ad-layout="%s"', htmlentities($slot['slot_options']['adLayout'])),
        );
        if (XenForo_Application::debugMode()) {
            $adAttributes[] = ' data-debug="yes"';
        }
        if (!empty($slot['slot_options']['hideSidebar'])) {
            $adAttributes[] = sprintf(
                ' data-max-client-width="%d"',
                XenForo_Template_Helper_Core::styleProperty('maxResponsiveWideWidth')
            );
        } elseif (!empty($slot['slot_options']['hideNonSidebar'])) {
            $adAttributes[] = sprintf(
                ' data-min-client-width="%d"',
                XenForo_Template_Helper_Core::styleProperty('maxResponsiveWideWidth')
            );
        }

        switch ($slot['slot_options']['adLayout']) {
            case self::AD_LAYOUT_TEXT:
                $mapping['{title}'] = $this->_prepareAdHtml_helperAdPhrase($ad, 'title');
                $mapping['{description}'] = $this->_prepareAdHtml_helperAdPhrase($ad, 'description');
                $mapping['{link}'] = $this->_prepareAdHtml_helperLink($ad);
                break;
            case self::AD_LAYOUT_IMAGE:
                $imageUrl = $this->_prepareAdHtml_helperUploadUrl($ad, 'image');
                $imageWidth = intval($slot['slot_options']['width']);
                $imageHeight = intval($slot['slot_options']['height']);
                if (empty($imageUrl)
                    && $imageWidth > 0
                    && $imageHeight > 0
                ) {
                    $imageUrl = sprintf('http://placehold.it/%dx%d', $imageWidth, $imageHeight);
                }
                if (empty($imageUrl)) {
                    return null;
                }

                $renderedAdStyles = array();
                if ($imageWidth > 0 && $imageHeight > 0
                ) {
                    $renderedAdStyles[] = sprintf('width:%dpx;height:%dpx', $imageWidth, $imageHeight);
                }

                $mapping['{imageUrl}'] = $imageUrl;
                $mapping['{_renderedAdStyles}'] = implode('', $renderedAdStyles);
                $mapping['{link}'] = $this->_prepareAdHtml_helperLink($ad);
                break;
            case self::AD_LAYOUT_HTML:
                $mapping['{html}'] = $ad['ad_options']['html'];
                break;
            case self::AD_LAYOUT_ADSENSE:
                $adAttributes = $this->_prepareAdHtmlMapping_adsenseAttributes($ad, $slot, $adAttributes);
                break;
            case self::AD_LAYOUT_GPT:
                if (empty($slot['slot_options']['responsiveAds'])) {
                    $gptRendered = $this->_prepareAdHtmlMapping_gptAds(array($ad), $slot);
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
                        $gptRendered = $this->_prepareAdHtmlMapping_gptAds(array($ad), $slot);
                    } else {
                        $adAttributes[] = ' data-gpt-responsive="yes"';
                        $gptRendered = $this->_prepareAdHtmlMapping_gptAds($adsByWidth, $slot);
                    }
                }

                $mapping['{ads}'] = $gptRendered;
                break;
        }

        $mapping['{_adAttributes}'] .= implode('', $adAttributes);

        return $mapping;
    }

    protected function _prepareAdHtmlMapping_adsenseAttributes(array $ad, array $slot, array $adAttributes)
    {
        $adAttributes[] = sprintf(
            ' data-adsense-client="%s" data-adsense-slot="%s" data-adsense-format="%s"',
            htmlentities($ad['ad_options']['publisherId']),
            htmlentities($ad['ad_options']['slotId']),
            !empty($ad['ad_options']['format']) ? htmlentities($ad['ad_options']['format']) : 'auto'
        );

        return $adAttributes;
    }

    protected function _prepareAdHtmlMapping_gptAds(array $ads, array $slot)
    {
        krsort($ads);

        $adsRendered = array();
        foreach ($ads as $ad) {
            $adsRendered[] = sprintf(
                '<ins data-gpt-unit-path="%s" data-gpt-size-width="%d" data-gpt-size-height="%d"></ins>',
                htmlentities($ad['ad_options']['adUnitPath']),
                $ad['ad_options']['sizeWidth'],
                $ad['ad_options']['sizeHeight']
            );
        }

        return implode('', $adsRendered);
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
