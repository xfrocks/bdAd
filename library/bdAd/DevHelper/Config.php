<?php

class bdAd_DevHelper_Config extends DevHelper_Config_Base
{
    protected $_dataClasses = array(
        'slot' => array(
            'name' => 'slot',
            'camelCase' => 'Slot',
            'camelCasePlural' => 'Slots',
            'camelCaseWSpace' => 'Slot',
            'camelCasePluralWSpace' => 'Slots',
            'fields' => array(
                'slot_id' => array('name' => 'slot_id', 'type' => 'uint', 'autoIncrement' => true),
                'slot_name' => array('name' => 'slot_name', 'type' => 'string', 'length' => 50, 'required' => true),
                'slot_class' => array('name' => 'slot_class', 'type' => 'string', 'length' => 50, 'required' => true),
                'slot_options' => array('name' => 'slot_options', 'type' => 'serialized', 'required' => true, 'default' => 'a:0:{}'),
                'active' => array('name' => 'active', 'type' => 'boolean', 'required' => true, 'default' => 1),
                'slot_config_options' => array('name' => 'slot_config_options', 'type' => 'serialized', 'required' => true, 'default' => 'a:0:{}'),
            ),
            'phrases' => array(),
            'title_field' => 'slot_name',
            'primaryKey' => array('slot_id'),
            'indeces' => array(),
            'files' => array(
                'data_writer' => array('className' => 'bdAd_DataWriter_Slot', 'hash' => '155c29741b511acdad7dad00a5617397'),
                'model' => array('className' => 'bdAd_Model_Slot', 'hash' => '9f6f8d05c1c0985d959e2814570e47e6'),
                'route_prefix_admin' => array('className' => 'bdAd_Route_PrefixAdmin_Slot', 'hash' => '408c5f05b534888ca3c42ccbd940b8b5'),
                'controller_admin' => array('className' => 'bdAd_ControllerAdmin_Slot', 'hash' => '96d35b57c63ac3168c806771dbc0dc86'),
            ),
        ),
        'ad' => array(
            'name' => 'ad',
            'camelCase' => 'Ad',
            'camelCasePlural' => 'Ads',
            'camelCaseWSpace' => 'Ad',
            'camelCasePluralWSpace' => 'Ads',
            'fields' => array(
                'ad_id' => array('name' => 'ad_id', 'type' => 'uint', 'autoIncrement' => true),
                'ad_name' => array('name' => 'ad_name', 'type' => 'string', 'length' => 50, 'required' => true),
                'user_id' => array('name' => 'user_id', 'type' => 'uint', 'required' => true),
                'slot_id' => array('name' => 'slot_id', 'type' => 'uint', 'required' => true),
                'ad_options' => array('name' => 'ad_options', 'type' => 'serialized', 'required' => true, 'default' => 'a:0:{}'),
                'active' => array('name' => 'active', 'type' => 'boolean', 'required' => true, 'default' => 1),
                'attach_count' => array('name' => 'attach_count', 'type' => 'uint', 'required' => true, 'default' => 0),
                'view_count' => array('name' => 'view_count', 'type' => 'uint', 'required' => true, 'default' => 0),
                'click_count' => array('name' => 'click_count', 'type' => 'uint', 'required' => true, 'default' => 0),
                'ad_config_options' => array('name' => 'ad_config_options', 'type' => 'serialized', 'required' => true, 'default' => 'a:0:{}'),
            ),
            'phrases' => array('title', 'description'),
            'title_field' => 'ad_name',
            'primaryKey' => array('ad_id'),
            'indeces' => array(),
            'files' => array(
                'data_writer' => array('className' => 'bdAd_DataWriter_Ad', 'hash' => 'x63fc8e26039ccddc42ff2376863982a1'),
                'model' => array('className' => 'bdAd_Model_Ad', 'hash' => '579a907053d0f54bb18cb201dd4e7c39'),
                'route_prefix_admin' => array('className' => 'bdAd_Route_PrefixAdmin_Ad', 'hash' => '8793f07123e7fd29f0b2d3526914c271'),
                'controller_admin' => array('className' => 'bdAd_ControllerAdmin_Ad', 'hash' => '4b2867a65111c16b876aa84fcbb34f49'),
            ),
        ),
    );
    protected $_dataPatches = array();
    protected $_exportPath = '/Users/sondh/XenForo/_FlyingSolo/bdAd';
    protected $_exportIncludes = array();
    protected $_exportExcludes = array();
    protected $_exportAddOns = array();
    protected $_exportStyles = array();
    protected $_options = array();

    /**
     * Return false to trigger the upgrade!
     **/
    protected function _upgrade()
    {
        return true; // remove this line to trigger update

        /*
        $this->addDataClass(
            'name_here',
            array( // fields
                'field_here' => array(
                    'type' => 'type_here',
                    // 'length' => 'length_here',
                    // 'required' => true,
                    // 'allowedValues' => array('value_1', 'value_2'),
                    // 'default' => 0,
                    // 'autoIncrement' => true,
                ),
                // other fields go here
            ),
            array('primary_key_1', 'primary_key_2'), // or 'primary_key', both are okie
            array( // indeces
                array(
                    'fields' => array('field_1', 'field_2'),
                    'type' => 'NORMAL', // UNIQUE or FULLTEXT
                ),
            ),
        );
        */
    }
}