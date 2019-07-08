<?php
// Return if no visible fields
global $thisclient;
if (!$form->hasAnyVisibleFields($thisclient))
    return;

$isCreate = (isset($options['mode']) && $options['mode'] == 'create');
?>
    <tr class="form-header-row"><td colspan="<?php echo MAX_FORM_DISPLAY_COLUMNS ?>">
    <div class="form-header" style="margin-bottom:0.5em">
    <h3><?php echo Format::htmlchars($form->getTitle()); ?></h3>
    <div><?php echo Format::display($form->getInstructions()); ?></div>
    </div>
    </td></tr>
    <?php
    // Form fields, each with corresponding errors follows. Fields marked
    // 'private' are not included in the output for clients
    $columnsInCurrentRow = 0;
    $maxColumns = MAX_FORM_DISPLAY_COLUMNS;
    $startNewRow = true;

    foreach ($form->getFields() as $field) {
        try {
            if (!$field->isEnabled())
                continue;
        }
        catch (Exception $e) {
            // Not connected to a DynamicFormField
        }

        if ($isCreate) {
            if (!$field->isVisibleToUsers() && !$field->isRequiredForUsers())
                continue;
        } elseif (!$field->isVisibleToUsers()) {
            continue;
        }

        $columnsToUse = $field->getDisplayColumns();

        if ($columnsToUse > $maxColumns)
            $columnsToUse = $maxColumns;

        if ($startNewRow || $columnsInCurrentRow + $columnsToUse > $maxColumns)
        { ?>
        <tr>
        <?php
            $startNewRow = false; 
        } ?>
            <td colspan="<?php echo $columnsToUse ?>" style="padding-top:10px;<?php if($field->isInitiallyHidden()) echo 'display:none;'; ?>">
            <?php if (!$field->isBlockLevel()) { ?>
                <label for="<?php echo $field->getFormName(); ?>"><span class="<?php
                    if ($field->isRequiredForUsers()) echo 'required'; ?>">
                <?php echo Format::htmlchars($field->getLocal('label')); ?>
            <?php if ($field->isRequiredForUsers() &&
                    ($field->isEditableToUsers() || $isCreate)) { ?>
                <span class="error">*</span>
            <?php 
            }
            if (!$field->isRequiredForUsers() && $field->isRequiredSometimes() 
                       && ($field->isEditableToUsers() || $isCreate)) { ?>
                    <span class="error" style="display:none;">*</span>
                <?php }
            ?></span><?php
                if ($field->get('hint')) { ?>
                    <br /><em style="color:gray;display:inline-block"><?php
                        echo Format::viewableImages($field->getLocal('hint')); ?></em>
                <?php
                } ?>
            <br/>
            <?php
            }
            if ($field->isEditableToUsers() || $isCreate) {
                $field->render(array('client'=>true));
                ?></label><?php
                if (!($field instanceof TableField)) {
                    foreach ($field->errors() as $e) { ?>
                        <div class="error"><?php echo $e; ?></div>
                    <?php }
                }
                $field->renderExtras(array('client'=>true));
            } else {
                $val = '';
                if ($field->value)
                    $val = $field->display($field->value);
                elseif (($a=$field->getAnswer()))
                    $val = $a->display();

                echo sprintf('%s </label>', $val);
            }
            ?>
            </td>
        <?php if ($columnsInCurrentRow + $columnsToUse >= $maxColumns) { ?>
        </tr>
        <?php
            $columnsInCurrentRow = 0;
            $startNewRow = true;
        } else {
            $columnsInCurrentRow += $columnsToUse;
        }   
    }
?>
