<?php
// Return if no visible fields
global $thisclient;
//if (!$form->hasAnyVisibleFields($thisclient))
//    return;
if (empty($formData)) {   
    $formData[] = array();
    foreach ($form->getFields() as $field) {
        $formData[0][] = null;
    }
}

//$isCreate = (isset($options['mode']) && $options['mode'] == 'create');
?>
<div class="table-form-container">
    <div class="form-header" style="margin-bottom:0.5em">
    <div><?php echo Format::display($form->getInstructions()); ?></div>
    </div>
    <table class="table-form">
        <thead>
        <tr>
        <?php
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
        ?>
            <td>
            <?php if (!$field->isBlockLevel()) { ?>
                <label for="<?php echo $field->getFormName(); ?>"><span class="<?php
                    if ($field->isRequiredForUsers()) echo 'required'; ?>">
                <?php echo Format::htmlchars($field->getLocal('label')); ?>
            <?php if ($field->isRequiredForUsers() &&
                    ($field->isEditableToUsers() || $isCreate)) { ?>
                <span class="error">*</span>
            <?php }
            ?></span><?php
                if ($field->get('hint')) { ?>
                    <br /><em style="color:gray;display:inline-block"><?php
                        echo Format::viewableImages($field->getLocal('hint')); ?></em>
                <?php
                } ?>
            <br/>
            <?php
            } ?>
            </td>
        <?php
    } ?>
        <td></td>
        </tr>
        </thead>
        <tbody>
    <?php
        foreach ($formData as $rowIndex => $row) {
            ?> <tr> <?php
            $fieldIndex = 0;
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
                
                if ($row[$fieldIndex]) {
                    $field->setValue($row[$fieldIndex]);
                }  else {
                    $field->setValue(null);
                }

                if (!empty($formErrors)) {
                    if ($formErrors[$rowIndex][$fieldIndex]) {
                        $field->setErrors($formErrors[$rowIndex][$fieldIndex]);
                    }
                } else {
                    $field->setErrors(array());
                }

                ?>
                    <td>
                    <?php if ($field->isEditableToUsers() || $isCreate) {
                        $field->render(array('client'=>true, 'in_table'=>true));
                        ?></label><?php
 
                        foreach ($field->errors() as $e) { ?>
                            <div class="error" style="clear: both;"><?php echo $e; ?></div>
                        <?php }
                        $field->renderExtras(array('client'=>true));
                        } else {
                        $val = '';
                        if ($field->value)
                            $val = $field->display($field->value);
                        elseif (($a=$field->getAnswer()))
                            $val = $a->display();

                        echo sprintf('%s </label>', $val);
                    } ?>
                    </td>
                <?php
                $fieldIndex++;
            } 
            ?>
        <td><button type="button" class="remove-form-row" name="DeleteRow"><i class="icon-large icon-trash"></i></button></td>
        </tr><?php
        }?>
         </tbody>
    <table>
    <button type="button" class="add-form-row" name="AddRow"><i class="icon-large icon-plus"></i>Add</button>
    <script type="template/html" class="row-template">
    <tr>
        <?php
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

                $field->setValue(null);
                $field->setErrors(array());

                ?>
                    <td>
                    <?php if ($field->isEditableToUsers() || $isCreate) {
                        $field->render(array('client'=>true, 'in_table'=>true));
                        ?></label><?php
                        foreach ($field->errors() as $e) { ?>
                            <div class="error" style="clear:both;"><?php echo $e; ?></div>
                        <?php }
                        $field->renderExtras(array('client'=>true));
                        } else {
                        $val = '';
                        if ($field->value)
                            $val = $field->display($field->value);
                        elseif (($a=$field->getAnswer()))
                            $val = $a->display();

                        echo sprintf('%s </label>', $val);
                    } ?>
                    </td>
                <?php
            } ?>
            <td><button type="button" class="remove-form-row" name="DeleteRow"><i class="icon-large icon-trash"></i></button></td>
        </tr>
    </script>
</div>
