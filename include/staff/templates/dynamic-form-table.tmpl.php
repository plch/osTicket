<?php
// Return if no visible fields
global $thisclient;
//if (!$form->hasAnyVisibleFields($thisclient))
//    return;

if (empty($data)) {
    ?><div>Data is empty</div><?php
    $data[] = array();

    foreach ($form->getFields() as $field) {
        $data[0][] = null;
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
        </tr>
        </thead>
        <tbody>
        <tr>
    <?php
        foreach ($data as $row) {
            $index = 0;
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
                
                if ($row[$index]) {
                    ?> <td> Set field value </td> <?php
                    $field->value = $row[$index];
                }

                ?>
                    <td>
                    <?php if ($field->isEditableToUsers() || $isCreate) {
                        $field->render(array('client'=>true, 'in_table'=>true));
                        ?></label><?php
                        foreach ($field->errors() as $e) { ?>
                            <div class="error"><?php echo $e; ?></div>
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
                $index++;
            } 
        }?>
        </tr>
        </tbody>
    <table>
    <a href="#" class="add-form-row"><i class="icon-large icon-plus"></i>Add</a>
    <script type="text/javascript">
        $(function() {
            $(".add-form-row").click(function(e){
                e.preventDefault();

                var template = $(this).closest(".table-form-container").find(".row-template").html();
                var table = $(this).closest(".table-form-container").find(".table-form");

                table.append(template);
            });
        });
    </script>
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
                ?>
                    <td>
                    <?php if ($field->isEditableToUsers() || $isCreate) {
                        $field->render(array('client'=>true, 'in_table'=>true));
                        ?></label><?php
                        foreach ($field->errors() as $e) { ?>
                            <div class="error"><?php echo $e; ?></div>
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
        </tr>
    </script>
</div>
