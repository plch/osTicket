<?php
$BUTTONS = isset($BUTTONS) ? $BUTTONS : true;
?>
<div class="sidebar-container">
        <div class="buttons-container">
            <?php if ($BUTTONS) { ?>
            <div class="buttons-wrapper">
                <div class="individual-button-container">
                <?php
                    if ($cfg->getClientRegistrationMode() != 'disabled'
                        || !$cfg->isClientLoginRequired()) { ?>
                            <a href="open.php" style="display:block" class="primary button"><?php
                                echo __('Make a New Request');?></a>
                            <div>
                                Please provide as much detail as possible so we can best assist you. To update a previously submitted ticket, please login.
                            </div>
                </div>
                <?php } ?>
                <?php if ($cfg->isKnowledgebaseEnabled()) { ?>
                    <div class="individual-button-container">
                    <a href="kb/index.php" style="display:block" class="secondary button"><?php
                        echo __('Knowledgebase');?></a>
                </div>
                <?php } ?>
            </div>
            <?php } ?>
        </div>
        <?php if ($thisclient->getNumTickets($thisclient->canSeeOrgTickets())) {?>
            <div>
                <?php include(CLIENTINC_DIR.'recenttickets.inc.php'); ?>
                <div>
                    <a href="tickets.php" style="display:block;margin:5px auto;" class="secondary button">
                        <?php echo __('View All');?>
                    </a>
                </div>
            </div>
        <?php } ?>
</div>

