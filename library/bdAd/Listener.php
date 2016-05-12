<?php

class bdAd_Listener
{
    public static $adHasBeenServed = false;
    public static $headerScripts = array();
    protected static $_noAd = true;

    public static function load_class($class, array &$extend)
    {
        static $classes = array(
            'bdCache_Model_Cache',

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

        bdAd_ShippableHelper_Updater::onInitDependencies($dependencies, bdAd_Option::UPDATER_URL);
    }

    public static function template_post_render_PAGE_CONTAINER(
        /** @noinspection PhpUnusedParameterInspection */
        $templateName,
        &$content,
        array &$containerData,
        XenForo_Template_Abstract $template
    )
    {
        if (!count(self::$headerScripts)) {
            return;
        }

        ksort(self::$headerScripts);

        /** @noinspection BadExpressionStatementJS */
        $headerScripts = sprintf('<script>%s</script>', implode('', self::$headerScripts));
        $search = '<!--XenForo_Require:JS-->';
        $content = str_replace($search, $search . $headerScripts, $content);
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

    public static function front_controller_pre_dispatch(XenForo_FrontController $fc, XenForo_RouteMatch &$routeMatch)
    {
        $routeMatch->getMajorSection();
        $majorSection = $routeMatch->getMajorSection();

        $listValuesOption = XenForo_Application::getOptions('bdAd_customizeDisplayAd')->getOptions('bdAd_customizeDisplayAd');
        $checkedValues = $listValuesOption['bdAd_customizeDisplayAd'];

        foreach ($checkedValues as $key => $value) {
            if ($majorSection == $key) {
                self::$_noAd = false;
                self::bdAd_customizeDisplayAdCheck();
            }
        }

    }

    public static function bdAd_customizeDisplayAdCheck()
    {
        return self::$_noAd;
    }

}
