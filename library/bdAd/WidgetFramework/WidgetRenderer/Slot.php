<?php

class bdAd_WidgetFramework_WidgetRenderer_Slot extends WidgetFramework_WidgetRenderer
{
    protected function _getConfiguration()
    {
        return array(
            'name' => new XenForo_Phrase('bdad_widget_slot_name'),
            'options' => array(
                'slotId' => XenForo_Input::UINT,
                'slotOptions' => XenForo_Input::ARRAY_SIMPLE,
            ),
            'canAjaxLoad' => true,
            'useWrapper' => false,
        );
    }

    protected function _getOptionsTemplate()
    {
        return 'bdad_widget_options_slot';
    }

    protected function _getRenderTemplate(array $widget, $positionCode, array $params)
    {
        return 'bdad_widget_slot';
    }

    protected function _render(
        array $widget,
        $positionCode,
        array $params,
        XenForo_Template_Abstract $renderTemplateObject
    ) {
        return $renderTemplateObject;
    }
}
