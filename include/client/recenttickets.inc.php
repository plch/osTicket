<?php
if(!defined('OSTCLIENTINC') || !is_object($thisclient) || !$thisclient->isValid()) die('Access Denied');

$org_tickets = $thisclient->canSeeOrgTickets();

$tickets = Ticket::objects();

$status=null;

$sortOptions=array('id'=>'number', 'subject'=>'cdata__subject',
                    'status'=>'status__name', 'dept'=>'dept__name','date'=>'created');
$orderWays=array('DESC'=>'-','ASC'=>'');

$order_by= $sortOptions['date'];
$order = $orderWays['DESC'];

$x=$sort.'_sort';
$$x=' class="'.strtolower($_REQUEST['order'] ?: 'desc').'" ';

$basic_filter = Ticket::objects();

// Add visibility constraints — use a union query to use multiple indexes,
// use UNION without "ALL" (false as second parameter to union()) to imply
// unique values
$visibility = $basic_filter->copy()
    ->values_flat('ticket_id')
    ->filter(array('user_id' => $thisclient->getId()));

// Add visibility of Tickets where the User is a Collaborator if enabled
if ($cfg->collaboratorTicketsVisibility())
    $visibility = $visibility
    ->union($basic_filter->copy()
        ->values_flat('ticket_id')
        ->filter(array('thread__collaborators__user_id' => $thisclient->getId()))
    , false);

if ($thisclient->canSeeOrgTickets()) {
    $visibility = $visibility->union(
        $basic_filter->copy()->values_flat('ticket_id')
            ->filter(array('user__org_id' => $thisclient->getOrgId()))
    , false);
}

$tickets->distinct('ticket_id')->limit(5);

TicketForm::ensureDynamicDataView();

$total=$visibility->count();
$tickets->filter(array('ticket_id__in' => $visibility));

$negorder=$order=='-'?'ASC':'DESC'; //Negate the sorting

$tickets->order_by($order.$order_by);
$tickets->values(
    'ticket_id', 'number', 'created', 'isanswered', 'source', 'status_id',
    'status__state', 'status__name', 'cdata__subject', 'dept_id',
    'dept__name', 'dept__ispublic', 'user__default_email__address', 'user_id'
);

?>
<table id="ticketTable" width="800" border="0" cellspacing="0" cellpadding="0">
    <caption><?php echo __('Recent Requests');?></caption>
    <thead>
        <tr>
            <th nowrap>
                <?php echo __('Ticket #');?>
            </th>
            <th width="120">
                <?php echo __('Create Date');?>
            </th>
            <th width="100">
                <?php echo __('Status');?>
            </th>
            <th width="320">
                <?php echo __('Subject');?>
            </th>
            <th width="120">
                <?php echo __('Department');?>
            </th>
        </tr>
    </thead>
    <tbody>
    <?php
     $subject_field = TicketForm::objects()->one()->getField('subject');
     $defaultDept=Dept::getDefaultDeptName(); //Default public dept.
     if ($tickets->exists(true)) {
         foreach ($tickets as $T) {
            $dept = $T['dept__ispublic']
                ? Dept::getLocalById($T['dept_id'], 'name', $T['dept__name'])
                : $defaultDept;
            $subject = $subject_field->display(
                $subject_field->to_php($T['cdata__subject']) ?: $T['cdata__subject']
            );
            $status = TicketStatus::getLocalById($T['status_id'], 'value', $T['status__name']);
            if (false) // XXX: Reimplement attachment count support
                $subject.='  &nbsp;&nbsp;<span class="Icon file"></span>';

            $ticketNumber=$T['number'];
            if($T['isanswered'] && !strcasecmp($T['status__state'], 'open')) {
                $subject="<b>$subject</b>";
                $ticketNumber="<b>$ticketNumber</b>";
            }
            $thisclient->getId() != $T['user_id'] ? $isCollab = true : $isCollab = false;
            ?>
            <tr id="<?php echo $T['ticket_id']; ?>">
                <td>
                <a class="Icon <?php echo strtolower($T['source']); ?>Ticket" title="<?php echo $T['user__default_email__address']; ?>"
                    href="tickets.php?id=<?php echo $T['ticket_id']; ?>"><?php echo $ticketNumber; ?></a>
                </td>
                <td><?php echo Format::date($T['created']); ?></td>
                <td><?php echo $status; ?></td>
                <td>
                  <?php if ($isCollab) {?>
                    <div style="max-height: 1.2em; max-width: 320px;" class="link truncate" href="tickets.php?id=<?php echo $T['ticket_id']; ?>"><i class="icon-group"></i> <?php echo $subject; ?></div>
                  <?php } else {?>
                    <div style="max-height: 1.2em; max-width: 320px;" class="link truncate" href="tickets.php?id=<?php echo $T['ticket_id']; ?>"><?php echo $subject; ?></div>
                    <?php } ?>
                </td>
                <td><span class="truncate"><?php echo $dept; ?></span></td>
            </tr>
        <?php
        }
     } else {
         echo '<tr><td colspan="5">'.__('Your query did not match any records').'</td></tr>';
     }
    ?>
    </tbody>
</table>

