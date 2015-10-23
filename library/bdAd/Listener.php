<?php

class bdAd_Listener
{
    const UPDATER_URL = 'https://xfrocks.com/api/index.php?updater';

    public static function load_class($class, array &$extend)
    {
        static $classes = array(
            'XenForo_ControllerPublic_Misc',

            'XenForo_ViewPublic_Forum_View',
            'XenForo_ViewPublic_Search_Results',
            'XenForo_ViewPublic_Thread_View',

            'WidgetFramework_DataWriter_Widget',
        );

        if (in_array($class, $classes, true)) {
            $extend[] = 'bdAd_' . $class;
        }
    }

    public static function init_dependencies(XenForo_Dependencies_Abstract $dependencies, array $data)
    {
        XenForo_Template_Helper_Core::$helperCallbacks[strtolower('bdAd_Engine')] = array(
            'bdAd_Listener',
            'helperEngine',
        );

        bdAd_ShippableHelper_Updater::onInitDependencies($dependencies, self::UPDATER_URL);
    }

    public static function widget_framework_ready(array &$renderers)
    {
        $renderers[] = 'bdAd_WidgetFramework_WidgetRenderer_Slot';
    }

    public static function file_health_check(XenForo_ControllerAdmin_Abstract $controller, array &$hashes)
    {
        $hashes += bdAd_FileSums::getHashes();
    }

    public static function helperEngine()
    {
        $args = func_get_args();
        if (count($args) < 1) {
            return null;
        }

        $method = array('bdAd_Engine', array_shift($args));
        if (!is_callable($method)) {
            return null;
        }

        return call_user_func_array($method, $args);
    }
}