<?php

class bdAd_Model_Ad extends XenForo_Model
{
    const FETCH_AD_SLOTS = 0x01;
    const FETCH_AD_SLOT = 0x02;

    public function prepareAdPhrasesForCaching(array $ad)
    {
        /** @var XenForo_Model_Phrase $phraseModel */
        $phraseModel = XenForo_Model::create('XenForo_Model_Phrase');
        $prepared = array();

        $titles = array();
        foreach ($ad['phrases'] as $key => $phrase) {
            /** @var XenForo_Phrase $phrase */
            $phraseTitle = $phrase->getPhraseName();
            $titles[$key] = $phraseTitle;
        }

        $compiledPhrases = $phraseModel->getEffectivePhraseValuesInAllLanguages($titles);
        foreach ($compiledPhrases as $languageId => $languagePhrases) {
            foreach ($languagePhrases as $phraseTitle => $phraseText) {
                foreach ($titles as $key => $title) {
                    if ($title === $phraseTitle) {
                        $prepared[$key][$languageId] = $phraseText;
                    }
                }
            }
        }

        unset($ad['phrases']);
        $ad['safePhrases'] = $prepared;

        return $ad;
    }

    public function prepareAdUploads(array $ad)
    {
        if (empty($ad['attach_count'])
            || empty($ad['ad_options']['upload'])
        ) {
            return $ad;
        }

        /** @var XenForo_Model_Attachment $attachmentModel */
        $attachmentModel = $this->getModelFromCache('XenForo_Model_Attachment');
        $attachments = $attachmentModel->getAttachmentsByContentId('bdad_ad', $ad['ad_id']);

        $prepared = array();
        foreach ($ad['ad_options']['upload'] as $optionKey => $attachmentId) {
            if (isset($attachments[$attachmentId])) {
                $prepared[$optionKey] = $attachmentModel->prepareAttachment($attachments[$attachmentId]);
            }
        }

        $ad['attachments'] = $prepared;

        return $ad;
    }

    public function prepareAdsUploadsForCaching(array $ads)
    {
        $adIds = array();
        foreach ($ads as $ad) {
            if ($ad['attach_count'] > 0) {
                $adIds[] = $ad['ad_id'];
            }
        }
        if (empty($adIds)) {
            return $ads;
        }

        /** @var XenForo_Model_Attachment $attachmentModel */
        $attachmentModel = $this->getModelFromCache('XenForo_Model_Attachment');
        $attachments = $attachmentModel->getAttachmentsByContentIds('bdad_ad', $adIds);

        foreach ($ads as &$adRef) {
            if (empty($adRef['ad_options']['upload'])) {
                continue;
            }

            $prepared = array();

            foreach ($adRef['ad_options']['upload'] as $optionKey => $attachmentId) {
                if (isset($attachments[$attachmentId])) {
                    $attachment = $attachmentModel->prepareAttachment($attachments[$attachmentId]);

                    $prepared[$optionKey] = array(
                        'attachment_id' => $attachment['attachment_id'],
                        'thumbnailUrl' => $attachment['thumbnailUrl'],
                    );
                }
            }

            $adRef['safeAttachments'] = $prepared;
        }

        return $ads;
    }

    public function updateAdSlotIds($adId, array $slotIds)
    {
        $this->_getDb()->query('DELETE FROM xf_bdad_ad_slot WHERE ad_id = ?', $adId);

        $values = array();
        foreach ($slotIds as $slotId) {
            $values[] = sprintf('(%d, %d)', $adId, $slotId);
        }
        if (count($values) > 0) {
            $this->_getDb()->query('REPLACE INTO xf_bdad_ad_slot (ad_id, slot_id) VALUES '
                . implode(', ', $values));
        }
    }

    public function deleteAdSlotForSlot($slotId)
    {
        $this->_getDb()->query('DELETE FROM xf_bdad_ad_slot WHERE slot_id = ?', $slotId);
    }

    /* Start auto-generated lines of code. Change made will be overwriten... */

    public function getList(array $conditions = array(), array $fetchOptions = array())
    {
        $ads = $this->getAds($conditions, $fetchOptions);
        $list = array();

        foreach ($ads as $id => $ad) {
            $list[$id] = $ad['ad_name'];
        }

        return $list;
    }

    public function getAdById($id, array $fetchOptions = array())
    {
        $ads = $this->getAds(array('ad_id' => $id), $fetchOptions);

        return reset($ads);
    }

    public function getAdIdsInRange($start, $limit)
    {
        $db = $this->_getDb();

        return $db->fetchCol($db->limit('
            SELECT ad_id
            FROM xf_bdad_ad
            WHERE ad_id > ?
            ORDER BY ad_id
        ', $limit), $start);
    }

    public function getAds(array $conditions = array(), array $fetchOptions = array())
    {
        $whereConditions = $this->prepareAdConditions($conditions, $fetchOptions);

        $orderClause = $this->prepareAdOrderOptions($fetchOptions);
        $joinOptions = $this->prepareAdFetchOptions($fetchOptions);
        $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

        $ads = $this->fetchAllKeyed($this->limitQueryResults("
            SELECT ad.*
                $joinOptions[selectFields]
            FROM `xf_bdad_ad` AS ad
                $joinOptions[joinTables]
            WHERE $whereConditions
                $orderClause
            ", $limitOptions['limit'], $limitOptions['offset']
        ), 'ad_id');

        // prepare the phrases
        foreach ($ads as &$ad) {
            $ad['phrases'] = array(
                'title' => new XenForo_Phrase(self::getPhraseTitleForTitle($ad['ad_id'])),
                'description' => new XenForo_Phrase(self::getPhraseTitleForDescription($ad['ad_id'])),
            );
        }

        // parse all the options fields
        foreach ($ads as &$ad) {
            $ad['ad_options'] = @unserialize($ad['ad_options']);
            if (empty($ad['ad_options'])) {
                $ad['ad_options'] = array();
            }
            $ad['ad_config_options'] = @unserialize($ad['ad_config_options']);
            if (empty($ad['ad_config_options'])) {
                $ad['ad_config_options'] = array();
            }
        }

        $this->_getAdsCustomized($ads, $fetchOptions);

        return $ads;
    }

    public function countAds(array $conditions = array(), array $fetchOptions = array())
    {
        $whereConditions = $this->prepareAdConditions($conditions, $fetchOptions);

        $orderClause = $this->prepareAdOrderOptions($fetchOptions);
        $joinOptions = $this->prepareAdFetchOptions($fetchOptions);
        $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

        return $this->_getDb()->fetchOne("
            SELECT COUNT(*)
            FROM `xf_bdad_ad` AS ad
                $joinOptions[joinTables]
            WHERE $whereConditions
        ");
    }

    public function prepareAdConditions(array $conditions = array(), array $fetchOptions = array())
    {
        $sqlConditions = array();
        $db = $this->_getDb();

        if (isset($conditions['ad_id'])) {
            if (is_array($conditions['ad_id'])) {
                if (!empty($conditions['ad_id'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "ad.ad_id IN (" . $db->quote($conditions['ad_id']) . ")";
                }
            } else {
                $sqlConditions[] = "ad.ad_id = " . $db->quote($conditions['ad_id']);
            }
        }

        if (isset($conditions['ad_name'])) {
            if (is_array($conditions['ad_name'])) {
                if (!empty($conditions['ad_name'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "ad.ad_name IN (" . $db->quote($conditions['ad_name']) . ")";
                }
            } else {
                $sqlConditions[] = "ad.ad_name = " . $db->quote($conditions['ad_name']);
            }
        }

        if (isset($conditions['user_id'])) {
            if (is_array($conditions['user_id'])) {
                if (!empty($conditions['user_id'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "ad.user_id IN (" . $db->quote($conditions['user_id']) . ")";
                }
            } else {
                $sqlConditions[] = "ad.user_id = " . $db->quote($conditions['user_id']);
            }
        }

        if (isset($conditions['active'])) {
            if (is_array($conditions['active'])) {
                if (!empty($conditions['active'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "ad.active IN (" . $db->quote($conditions['active']) . ")";
                }
            } else {
                $sqlConditions[] = "ad.active = " . $db->quote($conditions['active']);
            }
        }

        if (isset($conditions['attach_count'])) {
            if (is_array($conditions['attach_count'])) {
                if (!empty($conditions['attach_count'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "ad.attach_count IN (" . $db->quote($conditions['attach_count']) . ")";
                }
            } else {
                $sqlConditions[] = "ad.attach_count = " . $db->quote($conditions['attach_count']);
            }
        }

        if (isset($conditions['view_count'])) {
            if (is_array($conditions['view_count'])) {
                if (!empty($conditions['view_count'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "ad.view_count IN (" . $db->quote($conditions['view_count']) . ")";
                }
            } else {
                $sqlConditions[] = "ad.view_count = " . $db->quote($conditions['view_count']);
            }
        }

        if (isset($conditions['click_count'])) {
            if (is_array($conditions['click_count'])) {
                if (!empty($conditions['click_count'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "ad.click_count IN (" . $db->quote($conditions['click_count']) . ")";
                }
            } else {
                $sqlConditions[] = "ad.click_count = " . $db->quote($conditions['click_count']);
            }
        }

        $this->_prepareAdConditionsCustomized($sqlConditions, $conditions, $fetchOptions);

        return $this->getConditionsForClause($sqlConditions);
    }

    public function prepareAdFetchOptions(array $fetchOptions = array())
    {
        $selectFields = '';
        $joinTables = '';

        $this->_prepareAdFetchOptionsCustomized($selectFields, $joinTables, $fetchOptions);

        return array(
            'selectFields' => $selectFields,
            'joinTables' => $joinTables
        );
    }

    public function prepareAdOrderOptions(array $fetchOptions = array(), $defaultOrderSql = '')
    {
        $choices = array();

        $this->_prepareAdOrderOptionsCustomized($choices, $fetchOptions);

        return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
    }

    public static function getPhraseTitleForTitle($id)
    {
        return "bdad_ad_{$id}_title";
    }

    public static function getPhraseTitleForDescription($id)
    {
        return "bdad_ad_{$id}_description";
    }

    /* End auto-generated lines of code. Feel free to make changes below */

    protected function _getAdsCustomized(array &$data, array $fetchOptions)
    {
        if (isset($fetchOptions['join'])) {
            if ($fetchOptions['join'] & self::FETCH_AD_SLOTS && count($data) > 0) {
                $adSlots = $this->_getDb()->fetchAll('
                    SELECT *
                    FROM xf_bdad_ad_slot
                    WHERE ad_id IN (' . $this->_getDb()->quote(array_keys($data)) . ')
                ');

                foreach ($data as &$adRef) {
                    $adRef['adSlots'] = array();
                    foreach ($adSlots as $adSlot) {
                        if ($adSlot['ad_id'] == $adRef['ad_id']) {
                            $adSlot['ad_slot_options'] = @unserialize($adSlot['ad_slot_options']);
                            if (empty($adSlot['ad_slot_options'])) {
                                $adSlot['ad_slot_options'] = array();
                            }
                            $adRef['adSlots'][$adSlot['slot_id']] = $adSlot;
                        }
                    }
                }
            }
        }
    }

    protected function _prepareAdConditionsCustomized(array &$sqlConditions, array $conditions, array $fetchOptions)
    {
        if (isset($conditions['slot_id'])) {
            if (is_array($conditions['slot_id'])) {
                if (!empty($conditions['slot_id'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "ad_slot.slot_id IN (" . $this->_getDb()->quote($conditions['slot_id']) . ")";
                }
            } else {
                $sqlConditions[] = "ad_slot.slot_id = " . $this->_getDb()->quote($conditions['slot_id']);
            }
        }
    }

    protected function _prepareAdFetchOptionsCustomized(&$selectFields, &$joinTables, array $fetchOptions)
    {
        if (isset($fetchOptions['join'])) {
            if ($fetchOptions['join'] & self::FETCH_AD_SLOT) {
                $selectFields .= ',
                    ad_slot.*';
                $joinTables .= '
                    INNER JOIN `xf_bdad_ad_slot` AS ad_slot
                    ON (ad_slot.ad_id = ad.ad_id)';
            }
        }
    }

    protected function _prepareAdOrderOptionsCustomized(array &$choices, array &$fetchOptions)
    {
        // customized code goes here
    }

}