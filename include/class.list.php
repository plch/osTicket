<?php
/*********************************************************************
    class.list.php

    Custom List utils

    Jared Hancock <jared@osticket.com>
    Peter Rotich <peter@osticket.com>
    Copyright (c)  2014 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

require_once(INCLUDE_DIR .'class.dynamic_forms.php');

/**
 * Interface for Custom Lists
 *
 * Custom lists are used to represent list of arbitrary data that can be
 * used as dropdown or typeahead selections in dynamic forms. This model
 * defines a list. The individual items are stored in the "Item" model.
 *
 */

interface CustomList {

    function getId();
    function getName();
    function getPluralName();

    function getNumItems();
    function getAllItems();
    function getItems($criteria);

    function getItem($id);
    function addItem($vars, &$errors);

    function getForm(); // Config form
    function hasProperties();

    function getSortModes();
    function getSortMode();
    function getListOrderBy();

    function allowAdd();
    function hasAbbrev();

    function update($vars, &$errors);
    function delete();

    static function create($vars, &$errors);
    static function lookup($id);
}

/*
 * Custom list item interface
 */
interface CustomListItem {
    function getId();
    function getValue();
    function getAbbrev();
    function getSortOrder();

    function getConfiguration();
    function getConfigurationForm();


    function isEnabled();
    function isDeletable();
    function isEnableable();
    function isInternal();

    function enable();
    function disable();

    function update($vars, &$errors);
    function delete();
}


/*
 * Base class for Custom List handlers
 *
 * Custom list handler extends custom list and might store data outside the
 * typical dynamic list store.
 *
 */

abstract class CustomListHandler {

    var $_list;

    function __construct($list) {
        $this->_list = $list;
    }

    function __call($name, $args) {

        $rv = null;
        if ($this->_list && is_callable(array($this->_list, $name)))
            $rv = $args
                ? call_user_func_array(array($this->_list, $name), $args)
                : call_user_func(array($this->_list, $name));

        return $rv;
    }

    function update($vars, &$errors) {
        return $this->_list->update($vars, $errors);
    }

    abstract function getNumItems();
    abstract function getAllItems();
    abstract function getItems($criteria);
    abstract function getItem($id);
    abstract function addItem($vars, &$errors);
}

/**
 * Dynamic lists are Custom Lists solely defined by the user.
 *
 */
class DynamicList extends VerySimpleModel implements CustomList {

    static $meta = array(
        'table' => LIST_TABLE,
        'ordering' => array('name'),
        'pk' => array('id'),
    );

    static $sort_modes = array(
            'Alpha'     => 'Alphabetical',
            '-Alpha'    => 'Alphabetical (Reversed)',
            'SortCol'   => 'Manually Sorted'
            );

    // Required fields
    static $fields = array('name', 'name_plural', 'sort_mode', 'notes');

    // Supported masks
    const MASK_EDIT     = 0x0001;
    const MASK_ADD      = 0x0002;
    const MASK_DELETE   = 0x0004;
    const MASK_ABBREV   = 0x0008;

    var $_items;
    var $_form;
    var $_config;

    function __construct() {
        call_user_func_array(array('parent', '__construct'), func_get_args());
        $this->_config = new Config('list.'.$this->getId());
    }

    function getId() {
        return $this->get('id');
    }

    function getInfo() {
        return $this->ht;
    }

    function hasProperties() {
        return ($this->getForm() && $this->getForm()->getFields());
    }

    function getSortModes() {
       return static::$sort_modes;
    }

    function getSortMode() {
        return $this->sort_mode;
    }

    function getListOrderBy() {
        switch ($this->getSortMode()) {
            case 'Alpha':   return 'value';
            case '-Alpha':  return '-value';
            case 'SortCol': return 'sort';
        }
    }

    function getName() {
        return $this->get('name');
    }

    function getPluralName() {
        if ($name = $this->get('name_plural'))
            return $name;
        else
            return $this->get('name') . 's';
    }

    function getItemCount() {
        return DynamicListItem::objects()->filter(array('list_id'=>$this->id))
            ->count();
    }

    function getNumItems() {
        return $this->getItemCount();
    }

    function getAllItems() {
         return DynamicListItem::objects()->filter(
                array('list_id'=>$this->get('id')))
                ->order_by($this->getListOrderBy());
    }

    function getItems($limit=false, $offset=false) {
        if (!$this->_items) {
            $this->_items = DynamicListItem::objects()->filter(
                array('list_id'=>$this->get('id'),
                      'status__hasbit'=>DynamicListItem::ENABLED))
                ->order_by($this->getListOrderBy());
            if ($limit)
                $this->_items->limit($limit);
            if ($offset)
                $this->_items->offset($offset);
        }
        return $this->_items;
    }



    function getItem($val) {

        $criteria = array('list_id' => $this->getId());
        if (is_int($val))
            $criteria['id'] = $val;
        else
            $criteria['value'] = $val;

         return DynamicListItem::lookup($criteria);
    }

    function addItem($vars, &$errors) {

        $item = DynamicListItem::create(array(
            'status' => 1,
            'list_id' => $this->getId(),
            'sort'  => $vars['sort'],
            'value' => $vars['value'],
            'extra' => $vars['abbrev']
        ));

        $item->save();

        $this->_items = false;

        return $item;
    }

    function getConfigurationForm($autocreate=false) {
        if (!$this->_form) {
            $this->_form = DynamicForm::lookup(array('type'=>'L'.$this->getId()));
            if (!$this->_form
                    && $autocreate
                    && $this->createConfigurationForm())
                return $this->getConfigurationForm(false);
        }

        return $this->_form;
    }

    function isDeleteable() {
        return !$this->hasMask(static::MASK_DELETE);
    }

    function isEditable() {
        return !$this->hasMask(static::MASK_EDIT);
    }

    function allowAdd() {
        return !$this->hasMask(static::MASK_ADD);
    }

    function hasAbbrev() {
        return !$this->hasMask(static::MASK_ABBREV);
    }

    protected function hasMask($mask) {
        return 0 !== ($this->get('masks') & $mask);
    }

    protected function clearMask($mask) {
        return $this->set('masks', $this->get('masks') & ~$mask);
    }

    protected function setFlag($mask) {
        return $this->set('mask', $this->get('mask') | $mask);
    }

    private function createConfigurationForm() {

        $form = DynamicForm::create(array(
                    'type' => 'L'.$this->getId(),
                    'title' => $this->getName() . ' Properties'
        ));

        return $form->save(true);
    }

    function getForm($autocreate=true) {
        return $this->getConfigurationForm($autocreate);
    }

    function getConfiguration() {
        return JsonDataParser::parse($this->_config->get('configuration'));
    }

    function update($vars, &$errors) {

        $required = array();
        if ($this->isEditable())
            $required = array('name');

        foreach (static::$fields as $f) {
            if (in_array($f, $required) && !$vars[$f])
                $errors[$f] = sprintf('%s is required', mb_convert_case($f, MB_CASE_TITLE));
            elseif (isset($vars[$f]))
                $this->set($f, $vars[$f]);
        }

        if ($errors)
            return false;

        return $this->save(true);
    }

    function save($refetch=false) {
        if (count($this->dirty))
            $this->set('updated', new SqlFunction('NOW'));
        if (isset($this->dirty['notes']))
            $this->notes = Format::sanitize($this->notes);
        return parent::save($refetch);
    }

    function delete() {
        $fields = DynamicFormField::objects()->filter(array(
            'type'=>'list-'.$this->id))->count();
        if ($fields == 0)
            return parent::delete();
        else
            // Refuse to delete lists that are in use by fields
            return false;
    }

    private function createForm() {

        $form = DynamicForm::create(array(
                    'type' => 'L'.$this->getId(),
                    'title' => $this->getName() . ' Properties'
        ));

        return $form->save(true);
    }

    static function add($vars, &$errors) {

        $required = array('name');
        $ht = array();
        foreach (static::$fields as $f) {
            if (in_array($f, $required) && !$vars[$f])
                $errors[$f] = sprintf('%s is required', mb_convert_case($f, MB_CASE_TITLE));
            elseif(isset($vars[$f]))
                $ht[$f] = $vars[$f];
        }

        if (!$ht || $errors)
            return false;

        // Create the list && form
        if (!($list = self::create($ht))
                || !$list->save(true)
                || !$list->createConfigurationForm())
            return false;

        return $list;
    }

    static function create($ht=false, &$errors=array()) {
        $inst = parent::create($ht);
        $inst->set('created', new SqlFunction('NOW'));

        if (isset($ht['properties'])) {
            $inst->save();
            $ht['properties']['type'] = 'L'.$inst->getId();
            $form = DynamicForm::create($ht['properties']);
            $form->save();
        }

        if (isset($ht['configuration'])) {
            $inst->save();
            $c = new Config('list.'.$inst->getId());
            $c->set('configuration', JsonDataEncoder::encode($ht['configuration']));
        }

        if (isset($ht['items'])) {
            $inst->save();
            foreach ($ht['items'] as $i) {
                $i['list_id'] = $inst->getId();
                $item = DynamicListItem::create($i);
                $item->save();
            }
        }

        return $inst;
    }

    static function lookup($id) {

        if (!($list = parent::lookup($id)))
            return null;

        if (($config = $list->getConfiguration())) {
            if (($lh=$config['handler']) && class_exists($lh))
                $list = new $lh($list);
        }

        return $list;
    }

    static function getSelections() {
        $selections = array();
        foreach (DynamicList::objects() as $list) {
            $selections['list-'.$list->id] =
                array($list->getPluralName(),
                    SelectionField, $list->get('id'));
        }
        return $selections;
    }

}
FormField::addFieldTypes('Custom Lists', array('DynamicList', 'getSelections'));

/**
 * Represents a single item in a dynamic list
 *
 * Fields:
 * value - (char * 255) Actual list item content
 * extra - (char * 255) Other values that represent the same item in the
 *      list, such as an abbreviation. In practice, should be a
 *      space-separated list of tokens which should hit this list item in a
 *      search
 * sort - (int) If sorting by this field, represents the numeric sort order
 *      that this item should come in the dropdown list
 */
class DynamicListItem extends VerySimpleModel implements CustomListItem {

    static $meta = array(
        'table' => LIST_ITEM_TABLE,
        'pk' => array('id'),
        'joins' => array(
            'list' => array(
                'null' => true,
                'constraint' => array('list_id' => 'DynamicList.id'),
            ),
        ),
    );

    var $_config;
    var $_form;

    const ENABLED   = 0x0001;
    const INTERNAL  = 0x0002;

    protected function hasStatus($flag) {
        return 0 !== ($this->get('status') & $flag);
    }

    protected function clearStatus($flag) {
        return $this->set('status', $this->get('status') & ~$flag);
    }

    protected function setStatus($flag) {
        return $this->set('status', $this->get('status') | $flag);
    }

    function isInternal() {
        return  $this->hasStatus(self::INTERNAL);
    }

    function isEnableable() {
        return true;
    }

    function isDeletable() {
        return !$this->isInternal();
    }

    function isEnabled() {
        return $this->hasStatus(self::ENABLED);
    }

    function enable() {
        $this->setStatus(self::ENABLED);
    }
    function disable() {
        $this->clearStatus(self::ENABLED);
    }

    function getId() {
        return $this->get('id');
    }

    function getListId() {
        return $this->get('list_id');
    }

    function getValue() {
        return $this->get('value');
    }

    function getAbbrev() {
        return $this->get('extra');
    }

    function getSortOrder() {
        return $this->get('sort');
    }

    function getConfiguration() {
        if (!$this->_config) {
            $this->_config = $this->get('properties');
            if (is_string($this->_config))
                $this->_config = JsonDataParser::parse($this->_config);
            elseif (!$this->_config)
                $this->_config = array();
        }
        return $this->_config;
    }

    function setConfiguration(&$errors=array()) {
        $config = array();
        foreach ($this->getConfigurationForm()->getFields() as $field) {
            $val = $field->to_database($field->getClean());
            $config[$field->get('id')] = is_array($val) ? $val[1] : $val;
            $errors = array_merge($errors, $field->errors());
        }
        if (count($errors) === 0)
            $this->set('properties', JsonDataEncoder::encode($config));

        return count($errors) === 0;
    }

    function getConfigurationForm() {
        if (!$this->_form) {
            $this->_form = DynamicForm::lookup(
                array('type'=>'L'.$this->get('list_id')));
        }
        return $this->_form;
    }

    function getForm() {
        return $this->getConfigurationForm();
    }

    function getVar($name) {
        $config = $this->getConfiguration();
        $name = mb_strtolower($name);
        foreach ($this->getConfigurationForm()->getFields() as $field) {
            if (mb_strtolower($field->get('name')) == $name)
                return $config[$field->get('id')];
        }
    }

    function toString() {
        return $this->get('value');
    }

    function __toString() {
        return $this->toString();
    }

    function update($vars, &$errors=array()) {

        if (!$vars['value']) {
            $errors['value-'.$this->getId()] = 'Value required';
            return false;
        }

        foreach (array(
                    'sort' => 'sort',
                    'value' => 'value',
                    'abbrev' => 'extra') as $k => $v) {
            if (isset($vars[$k]))
                $this->set($v, $vars[$k]);
        }

        return $this->save();
    }

    function delete() {
        # Don't really delete, just unset the list_id to un-associate it with
        # the list
        $this->set('list_id', null);
        return $this->save();
    }

    static function create($ht=false, &$errors=array()) {

        if (isset($ht['properties']) && is_array($ht['properties']))
            $ht['properties'] = JsonDataEncoder::encode($ht['properties']);

        $inst = parent::create($ht);
        $inst->save(true);

        // Auto-config properties if any
        if ($ht['configuration'] && is_array($ht['configuration'])) {
            $config = $inst->getConfiguration();
            if (($form = $inst->getConfigurationForm())) {
                foreach ($form->getFields() as $f) {
                    if (!isset($ht['configuration'][$f->get('name')]))
                        continue;

                    if (is_array($ht['configuration'][$f->get('name')]))
                        $val = JsonDataEncoder::encode(
                                $ht['configuration'][$f->get('name')]);
                    else
                        $val = $ht['configuration'][$f->get('name')];

                    $config[$f->get('id')] = $val;
                }
            }

            $inst->set('properties', JsonDataEncoder::encode($config));
        }

        return $inst;
    }
}


/*
 * Ticket status List
 *
 */

class TicketStatusList extends CustomListHandler {

    // Fields of interest we need to store
    static $config_fields = array('sort_mode', 'notes');

    var $_items;
    var $_form;

    function getNumItems() {
        return TicketStatus::objects()->count();
    }

    function getAllItems() {
         return TicketStatus::objects()->order_by($this->getListOrderBy());
    }

    function getItems($criteria) {

        if (!$this->_items) {
            $this->_items = TicketStatus::objects()->filter(
                array('flags__hasbit' => TicketStatus::ENABLED))
                ->order_by($this->getListOrderBy());
            if ($criteria['limit'])
                $this->_items->limit($criteria['limit']);
            if ($criteria['offset'])
                $this->_items->offset($criteria['offset']);
        }

        return $this->_items;
    }

    function getItem($val) {

        if (!is_int($val))
            $val = array('name' => $val);

         return TicketStatus::lookup($val, $this);
    }

    function addItem($vars, &$errors) {

        $item = TicketStatus::create(array(
            'flags' => 0,
            'sort'  => $vars['sort'],
            'name' => $vars['value'],
        ));
        $item->save();

        $this->_items = false;

        return $item;
    }

    static function __load() {
        require_once(INCLUDE_DIR.'class.i18n.php');

        $i18n = new Internationalization();
        $tpl = $i18n->getTemplate('list.yaml');
        foreach ($tpl->getData() as $f) {
            if ($f['type'] == 'ticket-status') {
                $list = DynamicList::create($f);
                $list->save();
                break;
            }
        }

        if (!$list || !($o=DynamicForm::objects()->filter(
                        array('type'=>'L'.$list->getId()))))
            return false;

        // Create default statuses
        if (($statuses = $i18n->getTemplate('ticket_status.yaml')->getData()))
            foreach ($statuses as $status)
                TicketStatus::__create($status);

        return $o[0];
    }
}

class TicketStatus  extends VerySimpleModel implements CustomListItem {

    static $meta = array(
        'table' => TICKET_STATUS_TABLE,
        'ordering' => array('name'),
        'pk' => array('id'),
    );

    var $_list;
    var $_form;
    var $_config;
    var $_settings;

    const ENABLED   = 0x0001;
    const INTERNAL  = 0x0002; // Forbid deletion or name and status change.



    function __construct() {
        call_user_func_array(array('parent', '__construct'), func_get_args());
        $this->_config = new Config('TS.'.$this->getId());
    }

    protected function hasFlag($field, $flag) {
        return 0 !== ($this->get($field) & $flag);
    }

    protected function clearFlag($field, $flag) {
        return $this->set($field, $this->get($field) & ~$flag);
    }

    protected function setFlag($field, $flag) {
        return $this->set($field, $this->get($field) | $flag);
    }

    protected function hasProperties() {
        return ($this->_config->get('properties'));
    }

    function getForm() {
        return $this->getConfigurationForm();
    }

    function getConfigurationForm() {

        if (!$this->_form && $this->_list) {
            $this->_form = DynamicForm::lookup(
                    array('type'=>'L'.$this->_list->getId()));
        }

        return $this->_form;
    }

    function isEnabled() {
        return $this->hasFlag('mode', self::ENABLED);
    }

    function enable() {

        // Ticket status without properties cannot be enabled!
        if (!$this->isEnableable())
            return false;

        return $this->setFlag('mode', self::ENABLED);
    }

    function disable() {
        return (!$this->isInternal()
                && $this->clearFlag('mode', self::ENABLED));
    }

    function isEnableable() {
        return $this->hasProperties();
    }

    function isDeletable() {
        return !$this->isInternal();
    }

    function isInternal() {
        return ($this->hasFlag('mode', self::INTERNAL));
    }

    function getId() {
        return $this->get('id');
    }

    function getName() {
        return $this->get('name');
    }

    function getValue() {
        return $this->getName();
    }

    function getAbbrev() {
        return '';
    }

    function getSortOrder() {
        return $this->get('sort');
    }

    function getConfiguration() {

        if (!$this->_settings) {
             $this->_settings = $this->_config->get('properties');
             if (is_string($this->_settings))
                 $this->_settings = JsonDataParser::parse($this->_settings);
             elseif (!$this->_settings)
                 $this->_settings = array();

            if ($this->getConfigurationForm()) {
                foreach ($this->getConfigurationForm()->getFields() as $f)  {
                    $name = mb_strtolower($f->get('name'));
                    $id = $f->get('id');
                    switch($name) {
                        case 'flags':
                            foreach (TicketFlagField::$_flags as $k => $v)
                                if ($this->hasFlag('flags', $v['flag']))
                                    $this->_settings[$id][$k] = $v['name'];
                            break;
                        case 'state':
                            $this->_settings[$id][$this->get('state')] = $this->get('state');
                            break;
                        default:
                            if (!$this->_settings[$id] && $this->_settings[$name])
                                $this->_settings[$id] = $this->_settings[$name];
                    }
                }
            }
        }

        return $this->_settings;
    }

    function setConfiguration(&$errors=array()) {
        $properties = array();
        foreach ($this->getConfigurationForm()->getFields() as $f) {
            if ($this->isInternal() //Item is internal.
                    && !$f->isEditable())
                continue;
            $val = $f->getClean();
            $errors = array_merge($errors, $f->errors());
            if ($f->errors()) continue;
            $name = mb_strtolower($f->get('name'));
            switch ($name) {
                case 'flags':
                    if ($val && is_array($val)) {
                        $flags = 0;
                        foreach ($val as $k => $v) {
                            if (isset(TicketFlagField::$_flags[$k]))
                                $flags += TicketFlagField::$_flags[$k]['flag'];
                            elseif (!$f->errors())
                                $f->addError('Unknown or invalid flag', $name);
                        }
                        $this->set('flags', $flags);
                    } elseif ($val && !$f->errors()) {
                        $f->addError('Unknown or invalid flag format', $name);
                    }
                    break;
                case 'state':
                    if ($val && is_array($val))
                        $this->set('state', key($val));
                    else
                        $f->addError('Unknown or invalid state', $name);
                    break;
                default: //Custom properties the user might add.
                    $properties[$f->get('id')] = $f->to_php($val);
            }
            // Add field specific validation errors (warnings)
            $errors = array_merge($errors, $f->errors());
        }

        if (count($errors) === 0) {
            $this->save(true);
            $this->setProperties($properties);
        }

        return count($errors) === 0;
    }

    function setProperties($properties) {
        if ($properties && is_array($properties))
            $properties = JsonDataEncoder::encode($properties);

        $this->_config->set('properties', $properties);
    }

    function update($vars, &$errors) {

        $fields = array('value' => 'name', 'sort' => 'sort');
        foreach($fields as $k => $v) {
            if (isset($vars[$k]))
                $this->set($v, $vars[$k]);
        }

        return $this->save(true);
    }

    function delete() {

        if (!$this->isDeletable())
            return false;

        // TODO: Delete and do house cleaning (move tickets..etc)

    }

    function toString() {
        return $this->getValue();
    }

    function __toString() {
        return $this->toString();
    }


    static function lookup($var, $list= false) {

        if (!($item = parent::lookup($var)))
            return null;

        $item->_list = $list;

        return $item;
    }


    static function __create($ht, &$error=false) {
        global $ost;

        $properties = JsonDataEncoder::encode($ht['properties']);
        unset($ht['properties']);
        $ht['created'] = new SqlFunction('NOW');
        if ($status = TicketStatus::create($ht)) {
            $status->save(true);
            $status->_config = new Config('TS.'.$status->getId());
            $status->_config->set('properties', $properties);
        }

        return $status;
    }
}




?>
