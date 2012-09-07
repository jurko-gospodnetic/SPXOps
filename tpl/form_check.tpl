<?php
if (!isset($obj) || !$obj) { $obj = new Check(); }
if (!isset($action) || !$action) { 
  if (isset($page['action'])) {
    $action = $page['action'];
  } else {
    $action = 'Add'; 
  }
}
if (!isset($edit)) $edit = false;
if (!isset($susers)) $susers = array();
if (!isset($susers)) $edit = false;
if (!isset($pservers)) $pservers = array();
?>
      <div class="row">
        <div class="span8 offset2">
<?php if (isset($error)) { 
        if (!is_array($error)) {
          $error = array($error);
        }
        foreach($error as $e) {
?>
	<div class="alert alert-error">
	  <button type="button" class="close" data-dismiss="alert">×</button>
	  <strong>Error!</strong> <?php echo $e; ?>
	</div>
<?php   }
      }
?>
	<h2><?php echo $action; ?> a Check</h2>
       </div>
      </div>
      <div class="row">
        <form method="POST" action="/<?php echo strtolower($action); ?>/w/check<?php if ($edit) echo "/i/".$obj->id; ?>" class="form-horizontal">
        <div class="span5">
          <div class="control-group">
            <label class="control-label" for="inputName">Name</label>
            <div class="controls">
              <input type="text" <?php if ($edit) echo "disabled"; ?> name="name" value="<?php echo $obj->name; ?>" id="inputName" placeholder="Check Name">
            </div>
	  </div>
          <div class="control-group">
            <label class="control-label" for="inputDescription">Description</label>
            <div class="controls">
              <input type="text" name="description" value="<?php echo $obj->description; ?>" id="inputDescription" placeholder="Check Description">
            </div>
	  </div>
          <div class="control-group">
            <label class="control-label" for="inputMSGError">Error Message</label>
            <div class="controls">
              <input type="text" name="m_error" value="<?php echo $obj->m_error; ?>" id="inputMSGError" placeholder="Message in case of error">
            </div>
	  </div>
          <div class="control-group">
            <label class="control-label" for="inputMSGWarn">Warning Message</label>
            <div class="controls">
              <input type="text" name="m_warn" value="<?php echo $obj->m_warn; ?>" id="inputMSGWarn" placeholder="Message in case of warning">
            </div>
          </div>
	  <div class="control-group">
	    <label class="control-label" for="selectFrequency">Frequency</label>
	    <div class="controls">
	      <select name="frequency" id="selectFrequency">
		<option value="-1">Upon request</option>
<?php $f = array(3600, 7200, 14400, 21600, 28800, 43200, 57600, 86400, 172800, 604800, 2678400);
      foreach($f as $freq) { ?>
		<option value="<?php echo $freq; ?>" <?php if ($freq == $obj->frequency) echo "selected"; ?>><?php echo parseFrequency($freq); ?></option>
<?php } ?>
	      </select>
	    </div>
	  </div>
          <div class="control-group">
            <label class="control-label" for="inputOptions">Options</label>
            <div class="controls">
              <label class="checkbox">
                <input name="f_root" type="checkbox" <?php if ($obj->f_root) { echo "checked"; } ?>>
		<a href="#" rel="tooltip" title="This check need root access">Need root</a>
              </label>
            </div>
          </div>
	  <div class="control-group">
	    <div class="controls">
	      <button type="submit" name="submit" value="1" class="btn"><?php echo $action; ?></button>
	    </div>
	  </div>
        </div>
        <div class="span7">
         <textarea name="lua" rows="25" class="input-xxlarge">
<?php if (!empty($obj->lua)) echo $obj->lua; ?>
         </textarea>
        </div>
       </form>
      </div>
