<?php
if(!defined('OSTCLIENTINC')) die('Access Denied!');
$info=array();
if($thisclient && $thisclient->isValid()) {
    $info=array('name'=>$thisclient->getName(),
                'email'=>$thisclient->getEmail(),
                'phone'=>$thisclient->getPhoneNumber());
}

$info=($_POST && $errors)?Format::htmlchars($_POST):$info;

$form = null;
if (!$info['topicId']) {
    if (array_key_exists('topicId',$_GET) && preg_match('/^\d+$/',$_GET['topicId']) && Topic::lookup($_GET['topicId']))
        $info['topicId'] = intval($_GET['topicId']);
    else
        $info['topicId'] = $cfg->getDefaultTopicId();
}

$forms = array();
if ($info['topicId'] && ($topic=Topic::lookup($info['topicId']))) {
    foreach ($topic->getForms() as $F) {
        if (!$F->hasAnyVisibleFields())
            continue;
        if ($_POST) {
            $F = $F->instanciate();
            $F->isValidForClient();
        }
        $forms[] = $F->getForm();
    }
}

?>
<h1><?php echo __('Make a New Request');?></h1>
<form id="ticketForm" method="post" action="open.php" enctype="multipart/form-data">
  <?php csrf_token(); ?>
  <input type="hidden" name="a" value="open">
  <?php if (!$thisclient) { ?>
  <table width="50%" cellpadding="1" cellspacing="0" border="0">
    <tbody>
        <?php      
            $uform = UserForm::getUserForm()->getForm($_POST);
            if ($_POST) $uform->isValid();
            $uform->render(array('staff' => false, 'mode' => 'create'));
        ?>  
    </tbody>
  </table>
  <?php } ?>
    <div class="topic-select-wrapper">
        <span class="bold"><?php echo __('I need help with:'); ?></span>
        <select id="topicId" required name="topicId" style="float:none;display:inline-block" onchange="javascript:
                var data = $(':input[name]', '#dynamic-form').serialize();
                $.ajax(
                    'ajax.php/form/help-topic/' + this.value,
                    {
                    data: data,
                    dataType: 'json',
                    success: function(json) {
                        $('#dynamic-form').empty().append(json.html);
                        $(document.head).append(json.media);
                        $(document).trigger('formLoaded');
                    }
                    });">
            <option value="" disabled selected><?php echo __('Select...');?></option>
            <?php
            if($topics=Topic::getPublicHelpTopics()) {
                foreach($topics as $id =>$name) {
                    echo sprintf('<option value="%d" %s>%s</option>',
                            $id, ($info['topicId']==$id)?'selected="selected"':'', $name);
                }
            } else { ?>
                <option value="0" ><?php echo __('General Inquiry');?></option>
            <?php
            } ?>
        </select>
    </div>
  <table width="100%" cellpadding="1" cellspacing="0" border="0">
    <tbody id="dynamic-form">
        <?php
        $options = array('mode' => 'create');
        foreach ($forms as $form) {
            include(CLIENTINC_DIR . 'templates/dynamic-form.tmpl.php');
        } ?>
    </tbody>
    <tbody>
    <?php
    if($cfg && $cfg->isCaptchaEnabled() && (!$thisclient || !$thisclient->isValid())) {
        if($_POST && $errors && !$errors['captcha'])
            $errors['captcha']=__('Please re-enter the text again');
        ?>
    <tr class="captchaRow">
        <td class="required"><?php echo __('CAPTCHA Text');?>:</td>
        <td>
            <span class="captcha"><img src="captcha.php" border="0" align="left"></span>
            &nbsp;&nbsp;
            <input id="captcha" type="text" name="captcha" size="6" autocomplete="off">
            <em><?php echo __('Enter the text shown on the image.');?></em>
            <font class="error">*&nbsp;<?php echo $errors['captcha']; ?></font>
        </td>
    </tr>
    <?php
    } ?>
    <tr><td colspan="<?php echo MAX_FORM_DISPLAY_COLUMNS ?>">&nbsp;</td></tr>
    </tbody>
  </table>
  <p class="buttons" style="text-align:center;">
        <input type="submit" class="primary button" value="<?php echo __('Create Ticket');?>">
        <input type="reset" class="secondary button" name="reset" value="<?php echo __('Reset');?>">
        <input type="button" class="secondary button" name="cancel" value="<?php echo __('Cancel'); ?>" onclick="javascript:
            $('.richtext').each(function() {
                var redactor = $(this).data('redactor');
                if (redactor && redactor.opts.draftDelete)
                    redactor.draft.deleteDraft();
            });
            window.location.href='index.php';">
  </p>
</form>
