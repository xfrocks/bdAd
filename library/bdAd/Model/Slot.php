<?php

class bdAd_Model_Slot extends XenForo_Model
{
    public function getSlotClasses()
    {
        $classes = array();

        if (!!bdAd_Option::get('slotPost')) {
            $classes[] = 'bdAd_Slot_Post';
        }

        if (!!bdAd_Option::get('slotThread')) {
            $classes[] = 'bdAd_Slot_Thread';
        }

        return $classes;
    }

    public function getSlotClassTitles()
    {
        $classes = $this->getSlotClasses();
        $phrases = array();

        foreach ($classes as $class) {
            // new XenForo_Phrase('bdad_slot_post')
            // new XenForo_Phrase('bdad_slot_thread')
            $phrases[$class] = new XenForo_Phrase(strtolower($class));
        }

        $titles = array();
        foreach ($phrases as $key => $phrase) {
            $titles[$key] = strval($phrase);
        }
        asort($titles);

        return $titles;
    }

    /* Start auto-generated lines of code. Change made will be overwriten... */

    public function getList(array $conditions = array(), array $fetchOptions = array())
    {
        $slots = $this->getSlots($conditions, $fetchOptions);
        $list = array();

        foreach ($slots as $id => $slot) {
            $list[$id] = $slot['slot_name'];
        }

        return $list;
    }

    public function getSlotById($id, array $fetchOptions = array())
    {
        $slots = $this->getSlots(array('slot_id' => $id), $fetchOptions);

        return reset($slots);
    }

    public function getSlotIdsInRange($start, $limit)
    {
        $db = $this->_getDb();

        return $db->fetchCol($db->limit('
            SELECT slot_id
            FROM xf_bdad_slot
            WHERE slot_id > ?
            ORDER BY slot_id
        ', $limit), $start);
    }

    public function getSlots(array $conditions = array(), array $fetchOptions = array())
    {
        $whereConditions = $this->prepareSlotConditions($conditions, $fetchOptions);

        $orderClause = $this->prepareSlotOrderOptions($fetchOptions);
        $joinOptions = $this->prepareSlotFetchOptions($fetchOptions);
        $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

        $slots = $this->fetchAllKeyed($this->limitQueryResults("
            SELECT slot.*
                $joinOptions[selectFields]
            FROM `xf_bdad_slot` AS slot
                $joinOptions[joinTables]
            WHERE $whereConditions
                $orderClause
            ", $limitOptions['limit'], $limitOptions['offset']), 'slot_id');

        // parse all the options fields
        foreach ($slots as &$slot) {
            $slot['slot_options'] = @unserialize($slot['slot_options']);
            if (empty($slot['slot_options'])) {
                $slot['slot_options'] = array();
            }
            $slot['slot_config_options'] = @unserialize($slot['slot_config_options']);
            if (empty($slot['slot_config_options'])) {
                $slot['slot_config_options'] = array();
            }
        }

        $this->_getSlotsCustomized($slots, $fetchOptions);

        return $slots;
    }

    public function countSlots(array $conditions = array(), array $fetchOptions = array())
    {
        $whereConditions = $this->prepareSlotConditions($conditions, $fetchOptions);
        $joinOptions = $this->prepareSlotFetchOptions($fetchOptions);

        return $this->_getDb()->fetchOne("
            SELECT COUNT(*)
            FROM `xf_bdad_slot` AS slot
                $joinOptions[joinTables]
            WHERE $whereConditions
        ");
    }

    public function prepareSlotConditions(array $conditions = array(), array $fetchOptions = array())
    {
        $sqlConditions = array();
        $db = $this->_getDb();

        if (isset($conditions['slot_id'])) {
            if (is_array($conditions['slot_id'])) {
                if (!empty($conditions['slot_id'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "slot.slot_id IN (" . $db->quote($conditions['slot_id']) . ")";
                }
            } else {
                $sqlConditions[] = "slot.slot_id = " . $db->quote($conditions['slot_id']);
            }
        }

        if (isset($conditions['slot_name'])) {
            if (is_array($conditions['slot_name'])) {
                if (!empty($conditions['slot_name'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "slot.slot_name IN (" . $db->quote($conditions['slot_name']) . ")";
                }
            } else {
                $sqlConditions[] = "slot.slot_name = " . $db->quote($conditions['slot_name']);
            }
        }

        if (isset($conditions['slot_class'])) {
            if (is_array($conditions['slot_class'])) {
                if (!empty($conditions['slot_class'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "slot.slot_class IN (" . $db->quote($conditions['slot_class']) . ")";
                }
            } else {
                $sqlConditions[] = "slot.slot_class = " . $db->quote($conditions['slot_class']);
            }
        }

        if (isset($conditions['active'])) {
            if (is_array($conditions['active'])) {
                if (!empty($conditions['active'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "slot.active IN (" . $db->quote($conditions['active']) . ")";
                }
            } else {
                $sqlConditions[] = "slot.active = " . $db->quote($conditions['active']);
            }
        }

        $this->_prepareSlotConditionsCustomized($sqlConditions, $conditions, $fetchOptions);

        return $this->getConditionsForClause($sqlConditions);
    }

    public function prepareSlotFetchOptions(array $fetchOptions = array())
    {
        $selectFields = '';
        $joinTables = '';

        $this->_prepareSlotFetchOptionsCustomized($selectFields, $joinTables, $fetchOptions);

        return array(
            'selectFields' => $selectFields,
            'joinTables' => $joinTables
        );
    }

    public function prepareSlotOrderOptions(array $fetchOptions = array(), $defaultOrderSql = '')
    {
        $choices = array();

        $this->_prepareSlotOrderOptionsCustomized($choices, $fetchOptions);

        return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
    }

    /* End auto-generated lines of code. Feel free to make changes below */

    protected function _getSlotsCustomized(array &$data, array $fetchOptions)
    {
        // customized code goes here
    }

    protected function _prepareSlotConditionsCustomized(array &$sqlConditions, array $conditions, array $fetchOptions)
    {
        // customized code goes here
    }

    protected function _prepareSlotFetchOptionsCustomized(&$selectFields, &$joinTables, array $fetchOptions)
    {
        // customized code goes here
    }

    protected function _prepareSlotOrderOptionsCustomized(array &$choices, array &$fetchOptions)
    {
        // customized code goes here
    }
}
