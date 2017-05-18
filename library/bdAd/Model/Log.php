<?php

class bdAd_Model_Log extends XenForo_Model
{
    public function logAdClick($adId)
    {
        $this->_getDb()->query('
        	INSERT ' . (XenForo_Application::getOptions()->get('enableInsertDelayed') ? 'DELAYED' : '')
            . ' INTO xf_bdad_click (ad_id)
			VALUES (?)
		', $adId);
    }

    public function aggregateAdClicks()
    {
        $db = $this->_getDb();

        $db->query('
			UPDATE xf_bdad_ad
			INNER JOIN (
				SELECT ad_id, COUNT(*) AS total
				FROM xf_bdad_click
				GROUP BY ad_id
			) AS xf_ac ON (xf_ac.ad_id = xf_bdad_ad.ad_id)
			SET xf_bdad_ad.click_count = xf_bdad_ad.click_count + xf_ac.total
		');

        $db->query('TRUNCATE TABLE xf_bdad_click');
    }

    public function logAdView($adId)
    {
        $this->_getDb()->query('
        	INSERT ' . (XenForo_Application::getOptions()->get('enableInsertDelayed') ? 'DELAYED' : '')
            . ' INTO xf_bdad_view (ad_id)
			VALUES (?)
		', $adId);

        bdAd_Listener::$loggedViewAdIds[] = $adId;
    }

    public function logAdViews(array $adIds)
    {
        if (empty($adIds)) {
            return;
        }

        $this->_getDb()->query(sprintf('
        	INSERT ' . (XenForo_Application::getOptions()->get('enableInsertDelayed') ? 'DELAYED' : '')
            . ' INTO xf_bdad_view (ad_id)
			VALUES (%s)
		', implode('), (', $adIds)));

        foreach ($adIds as $adId) {
            bdAd_Listener::$loggedViewAdIds[] = $adId;
        }
    }

    public function aggregateAdViews()
    {
        $db = $this->_getDb();

        $db->query('
			UPDATE xf_bdad_ad
			INNER JOIN (
				SELECT ad_id, COUNT(*) AS total
				FROM xf_bdad_view
				GROUP BY ad_id
			) AS xf_av ON (xf_av.ad_id = xf_bdad_ad.ad_id)
			SET xf_bdad_ad.view_count = xf_bdad_ad.view_count + xf_av.total
		');

        $db->query('TRUNCATE TABLE xf_bdad_view');
    }
}