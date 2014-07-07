<?php

$info=array();
if ($list) {
    $title = 'Update list';
    $action = 'update';
    $submit_text='Save Changes';
    $info = $list->getInfo();
    $newcount=2;
} else {
    $title = 'Add new custom list';
    $action = 'add';
    $submit_text='Add List';
    $newcount=4;
}

$info=Format::htmlchars(($errors && $_POST) ? array_merge($info,$_POST) : $info);

?>
<form action="?" method="post" id="save">
    <?php csrf_token(); ?>
    <input type="hidden" name="do" value="<?php echo $action; ?>">
    <input type="hidden" name="a" value="<?php echo Format::htmlchars($_REQUEST['a']); ?>">
    <input type="hidden" name="id" value="<?php echo $info['id']; ?>">
    <h2>Custom List: <?php echo $list ? $list->getName() : 'Add new list'; ?></h2>

<ul class="tabs">
    <li><a href="#definition" class="active">
        <i class="icon-plus"></i> Definition</a></li>
    <li><a href="#items">
        <i class="icon-list"></i> Items</a></li>
    <li><a href="#properties">
        <i class="icon-asterisk"></i> Properties</a></li>
</ul>

<div id="definition" class="tab_content">
    <table class="form_table" width="940" border="0" cellspacing="0" cellpadding="2">
    <thead>
        <tr>
            <th colspan="2">
                <h4><?php echo $title; ?></h4>
                <em>Custom lists are used to provide drop-down lists for custom forms. &nbsp;<i class="help-tip icon-question-sign" href="#custom_lists"></i></em>
            </th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td width="180" class="required">Name:</td>
            <td>
                <?php
                if ($list && $list->isBuiltIn())
                    echo $list->getName();
                else {
                    echo sprintf('<input size="50" type="text" name="name"
                            value="%s"/> <span
                            class="error">*<br/>%s</span>',
                            $info['name'], $errors['name']);
                }
                ?>
            </td>
        </tr>
        <tr>
            <td width="180">Plural Name:</td>
            <td>
                <?php
                    if ($list && $list->isBuiltIn())
                        echo $list->getPluralName();
                    else
                        echo sprintf('<input size="50" type="text"
                                name="name_plural" value="%s"/>',
                                $info['name_plural']);
                ?>
            </td>
        </tr>
        <tr>
            <td width="180">Sort Order:</td>
            <td><select name="sort_mode">
                <?php
                $sortModes = $list ? $list->getSortModes() : DynamicList::getSortModes();
                foreach ($sortModes as $key=>$desc) { ?>
                <option value="<?php echo $key; ?>" <?php
                    if ($key == $info['sort_mode']) echo 'selected="selected"';
                    ?>><?php echo $desc; ?></option>
                <?php } ?>
                </select></td>
        </tr>
    </tbody>
    <tbody>
        <tr>
            <th colspan="7">
                <em><strong>Internal Notes:</strong> be liberal, they're internal</em>
            </th>
        </tr>
        <tr>
            <td colspan="7"><textarea name="notes" class="richtext no-bar"
                rows="6" cols="80"><?php
                echo $info['notes']; ?></textarea>
            </td>
        </tr>
    </tbody>
    </table>
</div>
<div id="properties" class="tab_content" style="display:none">
    <table class="form_table" width="940" border="0" cellspacing="0" cellpadding="2">
    <thead>
        <tr>
            <th colspan="7">
                <em><strong>Item Properties</strong> properties definable for each item</em>
            </th>
        </tr>
        <tr>
            <th nowrap>Sort</th>
            <th nowrap>Label</th>
            <th nowrap>Type</th>
            <th nowrap>Variable</th>
            <th nowrap>Delete</th>
        </tr>
    </thead>
    <tbody class="sortable-rows" data-sort="prop-sort-">
    <?php if ($list && $form=$list->getForm()) foreach ($form->getDynamicFields() as $f) {
        $id = $f->get('id');
        $deletable = !$f->isDeletable() ? 'disabled="disabled"' : '';
        $force_name = $f->isNameForced() ? 'disabled="disabled"' : '';
        $fi = $f->getImpl();
        $ferrors = $f->errors(); ?>
        <tr>
            <td><i class="icon-sort"></i></td>
            <td><input type="text" size="32" name="prop-label-<?php echo $id; ?>"
                value="<?php echo Format::htmlchars($f->get('label')); ?>"/>
                <font class="error"><?php
                    if ($ferrors['label']) echo '<br/>'; echo $ferrors['label']; ?>
            </td>
            <td nowrap><select name="type-<?php echo $id; ?>" <?php
                if (!$fi->isChangeable() || !$f->isChangeable()) echo 'disabled="disabled"'; ?>>
                <?php foreach (FormField::allTypes() as $group=>$types) {
                        ?><optgroup label="<?php echo Format::htmlchars($group); ?>"><?php
                        foreach ($types as $type=>$nfo) {
                            if ($f->get('type') != $type
                                    && isset($nfo[2]) && !$nfo[2]) continue; ?>
                <option value="<?php echo $type; ?>" <?php
                    if ($f->get('type') == $type) echo 'selected="selected"'; ?>>
                    <?php echo $nfo[0]; ?></option>
                    <?php } ?>
                </optgroup>
                <?php } ?>
            </select>
            <?php if ($f->isConfigurable()) { ?>
                <a class="action-button" style="float:none;overflow:inherit"
                    href="#ajax.php/form/field-config/<?php
                        echo $f->get('id'); ?>"
                    onclick="javascript:
                        $('#overlay').show();
                        $('#field-config .body').html('Loading...');
                        $('#field-config .body').load($(this).attr('href').substr(1));
                        $('#field-config').show();
                        return false;
                    "><i class="icon-edit"></i> Config</a>
            <?php } ?></td>
            <td>
                <input type="text" size="20" name="name-<?php echo $id; ?>"
                    value="<?php echo Format::htmlchars($f->get('name'));
                    ?>" <?php echo $force_name ?>/>
                <font class="error"><?php
                    if ($ferrors['name']) echo '<br/>'; echo $ferrors['name'];
                ?></font>
                </td>
            <td>
                <?php
                if (!$f->isDeletable())
                    echo '<i class="icon-ban-circle"></i>';
                else
                    echo sprintf('<input type="checkbox" name="delete-prop-%s">', $id);
                ?>
                <input type="hidden" name="prop-sort-<?php echo $id; ?>"
                    value="<?php echo $f->get('sort'); ?>"/>
                </td>
        </tr>
    <?php
    }
    for ($i=0; $i<$newcount; $i++) { ?>
            <td><em>+</em>
                <input type="hidden" name="prop-sort-new-<?php echo $i; ?>"
                    value="<?php echo $info["prop-sort-new-$i"]; ?>"/></td>
            <td><input type="text" size="32" name="prop-label-new-<?php echo $i; ?>"
                value="<?php echo $info["prop-label-new-$i"]; ?>"/></td>
            <td><select name="type-new-<?php echo $i; ?>">
                <?php foreach (FormField::allTypes() as $group=>$types) {
                    ?><optgroup label="<?php echo Format::htmlchars($group); ?>"><?php
                    foreach ($types as $type=>$nfo) {
                        if (isset($nfo[2]) && !$nfo[2]) continue; ?>
                <option value="<?php echo $type; ?>"
                    <?php if ($info["type-new-$i"] == $type) echo 'selected="selected"'; ?>>
                    <?php echo $nfo[0]; ?>
                </option>
                    <?php } ?>
                </optgroup>
                <?php } ?>
            </select></td>
            <td><input type="text" size="20" name="name-new-<?php echo $i; ?>"
                value="<?php echo $info["name-new-$i"]; ?>"/>
                <font class="error"><?php
                    if ($errors["new-$i"]['name']) echo '<br/>'; echo $errors["new-$i"]['name'];
                ?></font>
            <td></td>
        </tr>
    <?php } ?>
    </tbody>
</table>
</div>
<div id="items" class="tab_content" style="display:none">
    <table class="form_table" width="940" border="0" cellspacing="0" cellpadding="2">
    <thead>
    <?php if ($list) {
        $page = ($_GET['p'] && is_numeric($_GET['p'])) ? $_GET['p'] : 1;
        $count = $list->getNumItems();
        $pageNav = new Pagenate($count, $page, PAGE_LIMIT);
        $pageNav->setURL('list.php', 'id='.urlencode($list->getId()));
        $showing=$pageNav->showing().' list items';
        ?>
    <?php }
        else $showing = 'Add a few initial items to the list';
    ?>
        <tr>
            <th colspan="5">
                <em><?php echo $showing; ?></em>
            </th>
        </tr>
        <tr>
            <th></th>
            <th>Value</th>
            <?php
            if (!$list || !$list->isBuiltIn()) { ?>
            <th>Abbrev <em style="display:inline">&mdash; Abbreviations and such</em></th>
            <?php
            } ?>
            <th>Disabled</th>
            <th>Delete</th>
        </tr>
    </thead>

    <tbody <?php if ($info['sort_mode'] == 'SortCol') { ?>
            class="sortable-rows" data-sort="sort-"<?php } ?>>
        <?php
        if ($list) {
            $icon = ($info['sort_mode'] == 'SortCol')
                ? '<i class="icon-sort"></i>&nbsp;' : '';
        foreach ($list->getAllItems() as $i) {
            $id = $i->getId(); ?>
        <tr class="<?php if (!$i->isEnabled()) echo 'disabled'; ?>">
            <td><?php echo $icon; ?>
                <input type="hidden" name="sort-<?php echo $id; ?>"
                value="<?php echo $i->getSortOrder(); ?>"/></td>
            <td><input type="text" size="40" name="value-<?php echo $id; ?>"
                value="<?php echo $i->getValue(); ?>"/>
                <?php if ($list->hasProperties()) { ?>
                <a class="action-button" style="float:none;overflow:inherit"
                    href="#ajax.php/list/<?php
                        echo $list->getId(); ?>/item/<?php echo $id ?>/properties"
                    onclick="javascript:
                        $('#overlay').show();
                        $('#field-config .body').html('Loading...');
                        $('#field-config .body').load($(this).attr('href').substr(1));
                        $('#field-config').show();
                        return false;
                    "><i class="icon-edit"></i> Properties</a>
                <?php
                }

                if ($errors["value-$id"])
                    echo sprintf('<br><span class="error">%s</span>',
                            $errors["value-$id"]);
                ?>
            </td>
            <?php
            if (!$list->isBuiltIn()) { ?>
            <td><input type="text" size="30" name="abbrev-<?php echo $id; ?>"
                value="<?php echo $i->getAbbrev(); ?>"/></td>
            <?php
            } ?>
            <td>
                <?php
                if ($i->isInternal())
                     echo '<i class="icon-ban-circle"></i>';
                else
                    echo sprintf('<input type="checkbox" name="disable-%s"
                            %s %s />',
                            $id,
                            !$i->isEnabled() ? ' checked="checked" ' : '',
                            (!$i->isEnabled() && !$i->isEnableable()) ? ' disabled="disabled" ' : ''
                            );
                ?>
            </td>
            <td>
                <?php
                if (!$i->isDeletable())
                    echo '<i class="icon-ban-circle"></i>';
                else
                    echo sprintf('<input type="checkbox" name="delete-item-%s">', $id);

                ?>
            </td>
        </tr>
    <?php }
    }
    for ($i=0; $i<$newcount; $i++) { ?>
        <tr>
            <td><?php echo $icon; ?> <em>+</em>
                <input type="hidden" name="sort-new-<?php echo $i; ?>"/></td>
            <td><input type="text" size="40" name="value-new-<?php echo $i; ?>"/></td>
            <?php
            if (!$list || !$list->isBuiltIn()) { ?>
            <td><input type="text" size="30" name="abbrev-new-<?php echo $i; ?>"/></td>
            <?php
            } ?>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
        </tr>
    <?php } ?>
    </tbody>
    </table>
</div>
<p class="centered">
    <input type="submit" name="submit" value="<?php echo $submit_text; ?>">
    <input type="reset"  name="reset"  value="Reset">
    <input type="button" name="cancel" value="Cancel" onclick='window.location.href="?"'>
</p>
</form>

<div style="display:none;" class="dialog draggable" id="field-config">
    <div class="body"></div>
</div>
