<?php
global $cfg;

if (!$info['title'])
    $info['title'] = 'Change Tickets Status';

?>
<h3><?php echo $info['title']; ?></h3>
<b><a class="close" href="#"><i class="icon-remove-circle"></i></a></b>
<div class="clear"></div>
<hr/>
<?php
if ($info['error']) {
    echo sprintf('<p id="msg_error">%s</p>', $info['error']);
} elseif ($info['warn']) {
    echo sprintf('<p id="msg_warning">%s</p>', $info['warn']);
} elseif ($info['msg']) {
    echo sprintf('<p id="msg_notice">%s</p>', $info['msg']);
} elseif ($info['notice']) {
   echo sprintf('<p id="msg_info"><i class="icon-info-sign"></i> %s</p>',
           $info['notice']);
}


$action = $info['action'] ?: ('#tickets/status/'. $state);
?>
<div id="ticket-status" style="display:block; margin:5px;">
    <form method="post" name="status" id="status"
        action="<?php echo $action; ?>">
        <table width="100%">
            <?php
            if ($info['extra']) {
                ?>
            <tbody>
                <tr><td colspan="2"><strong><?php echo $info['extra'];
                ?></strong></td> </tr>
                <tr><td colspan="2">&nbsp;</td></tr>
            </tbody>
            <?php
            }
            if ($state)
                $statuses = TicketStatusList::getStatuses(array('states'=>array($state)))->all();

            if ($statuses) {
            ?>
            <tbody>
                <tr>
                    <td colspan=2>
                        <span>
                        <?php echo __('Status') ?>:&nbsp;
                        <?php
                        if (count($statuses) > 1) { ?>
                            <select name="status_id">
                            <?php
                            foreach ($statuses as $s) {
                                echo sprintf('<option value="%d" %s>%s</option>',
                                        $s->getId(),
                                        ($info['status_id'] == $s->getId())
                                         ? 'selected="selected"' : '',
                                        $s->getName()
                                        );
                            }
                            ?>
                            </select>
                            <font class="error">*&nbsp;<?php echo $errors['status_id']; ?></font>
                        <?php
                        } elseif ($statuses[0]) {
                            echo __($statuses[0]->getName());
                            echo  "<input type='hidden' name='status_id' value={$statuses[0]->getId()} />";
                        } ?>
                        </span>
                    </td>
                </tr>
            </tbody>
            <?php
            } ?>
            <tbody>
                <tr>
                    <td colspan="2">
                        <em>Reasons for status change (internal note): Optional but highly recommended.</em>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <textarea name="comments" id="comments"
                            cols="50" rows="3" wrap="soft" style="width:100%"
                            class="richtext ifhtml no-bar"><?php
                            echo $info['notes']; ?></textarea>
                    </td>
                </tr>
            </tbody>
        </table>
        <hr>
        <p class="full-width">
            <span class="buttons" style="float:left">
                <input type="reset" value="<?php echo __('Reset'); ?>">
                <input type="button" name="cancel" class="close"
                value="<?php echo __('Cancel'); ?>">
            </span>
            <span class="buttons" style="float:right">
                <input type="submit" value="<?php echo __('Submit'); ?>">
            </span>
         </p>
    </form>
</div>
<div class="clear"></div>
<script type="text/javascript">
$(function() {
    // Copy checked tickets to status form.
    $('form#tickets input[name="tids[]"]:checkbox:checked')
    .clone()
    .prop('type', 'hidden')
    .removeAttr('class')
    .appendTo('form#status');
 });
</script>
