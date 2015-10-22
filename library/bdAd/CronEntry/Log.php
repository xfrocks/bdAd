<?php

class bdAd_CronEntry_Log
{
    public static function runHourly()
    {
        /** @var bdAd_Model_Log $logModel */
        $logModel = XenForo_Model::create('bdAd_Model_Log');
        $logModel->aggregateAdClicks();
        $logModel->aggregateAdViews();
    }
}