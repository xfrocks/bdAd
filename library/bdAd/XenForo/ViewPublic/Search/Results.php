<?php

class bdAd_XenForo_ViewPublic_Search_Results extends XFCP_bdAd_XenForo_ViewPublic_Search_Results
{
    public function renderHtml()
    {
        $templates = array();
        $templateTitles = array(
            'bdAd_Slot_Post' => 'bdad_search_result_post',
            'bdAd_Slot_Thread' => 'bdad_search_result_thread',
        );
        foreach ($templateTitles as $slotClass => $templateTitle) {
            $this->preLoadTemplate($templateTitle);
        }

        parent::renderHtml();

        if (isset($this->_params['results'])) {
            $i = 0;
            foreach ($this->_params['results'] as &$resultRef) {
                foreach (array_keys($templateTitles) as $slotClass) {
                    $adIds = bdAd_Engine::adIdsShouldBeServed($slotClass, '_search_result', $i);
                    if (!empty($adIds)) {
                        if (!isset($templates[$slotClass])) {
                            $templates[$slotClass] = $this->createTemplateObject($templateTitles[$slotClass]);
                        }

                        /** @var XenForo_Template_Abstract $_templateRef */
                        $_templateRef =& $templates[$slotClass];
                        $_html = $_templateRef->render();

                        bdAd_Engine::onTemplateHook('bdad_hook', $_html, array(
                            'slotClass' => $slotClass,
                            'adIds' => $adIds
                        ));
                        if (!empty($_html)) {
                            $resultRef .= $_html;
                        }
                    }
                }

                $i++;
            }
        }
    }

}