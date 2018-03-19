<?php
$parent_id = $_REQUEST['parent_id'] ?: $search->parent_id;
if ($parent_id
    && (!($parent = CustomQueue::lookup($parent_id)))
) {
    $parent_id = 0;
}

$queues = array();
foreach (CustomQueue::queues() as  $q)
    $queues[$q->id] = $q->getFullName();
asort($queues);
$queues = array(0 => ('—'.__("My Searches").'—')) + $queues;
$queue = $search;
$qname = $search->getName() ?:  __('Advanced Ticket Search');
?>
<div id="advanced-search" class="advanced-search">
<h3 class="drag-handle"><?php echo Format::htmlchars($qname); ?></h3>
<a class="close" href=""><i class="icon-remove-circle"></i></a>
<hr/>

<form action="#tickets/search" method="post" name="search">

  <div class="flex row">
    <div class="span12">
      <select name="parent_id">
          <?php
foreach ($queues as $id => $name) {
    ?>
          <option value="<?php echo $id; ?>"
              <?php if ($parent_id == $id) echo 'selected="selected"'; ?>
              ><?php echo $name; ?></option>
<?php       } ?>
      </select>
    </div>
   </div>
<ul class="clean tabs">
    <li class="active"><a href="#criteria"><i class="icon-search"></i> <?php echo __('Criteria'); ?></a></li>
    <li><a href="#columns"><i class="icon-columns"></i> <?php echo __('Columns'); ?></a></li>
    <li><a href="#fields"><i class="icon-download"></i> <?php echo __('Export'); ?></a></li>
</ul>

<div class="tab_content" id="criteria">
  <div class="flex row">
    <div class="span12" style="overflow-y: scroll; height:100%;">
<?php if ($parent) { ?>
      <div class="faded" style="margin-bottom: 1em">
      <div>
        <strong><?php echo __('Inherited Criteria'); ?></strong>
      </div>
      <div>
        <?php echo nl2br(Format::htmlchars($parent->describeCriteria())); ?>
      </div>
      </div>
<?php } ?>
      <input type="hidden" name="a" value="search">
      <?php include STAFFINC_DIR . 'templates/advanced-search-criteria.tmpl.php'; ?>
    </div>
  </div>

</div>

<div class="tab_content hidden" id="columns" style="overflow-y: scroll;
height:100%;">
    <?php
    include STAFFINC_DIR . "templates/queue-columns.tmpl.php";
    ?>
</div>
<div class="tab_content hidden" id="fields" style="overflow-y: scroll;
height:auto;">
    <?php
    include STAFFINC_DIR . "templates/queue-fields.tmpl.php";  ?>
</div>
  <hr/>
  <div>
    <div class="buttons pull-right">
      <button class="button" type="submit" name="submit" value="search"
        id="do_search"><i class="icon-search"></i>
        <?php echo __('Search'); ?></button>
    </div>
  </div>

</form>

<script>
+function() {
   // Return a helper with preserved width of cells
   var fixHelper = function(e, ui) {
      ui.children().each(function() {
          $(this).width($(this).width());
      });
      return ui;
   };
   // Sortable tables for dynamic forms objects
   $('.sortable-rows').sortable({
       'helper': fixHelper,
       'cursor': 'move',
       'stop': function(e, ui) {
           var attr = ui.item.parent('tbody').data('sort'),
               offset = parseInt($('#sort-offset').val(), 10) || 0;
           warnOnLeave(ui.item);
           $('input[name^='+attr+']', ui.item.parent('tbody')).each(function(i, el) {
               $(el).val(i + 1 + offset);
           });
       }
   });
}();
</script>
