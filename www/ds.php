<?php
 require_once("../libs/utils.obj.php");

try {

 $m = MySqlCM::getInstance();
 if ($m->connect()) {
   throw new ExitException('An error has occurred with the SQL Server and we were unable to process your request...');
 }
 $lm = LoginCM::getInstance();
 $lm->startSession();

 $h = HTTP::getInstance();
 $h->parseUrl();

 $index = new Template("../tpl/index.tpl");
 $head = new Template("../tpl/head.tpl");
 $foot = new Template("../tpl/foot.tpl");
 $page = array();
 $page['title'] = 'Display Settings: ';
 $page['action'] = 'Set';
 if ($lm->o_login) {
   $page['login'] = &$lm->o_login;
 } else {
   throw new ExitException('You must be logged-in to access this page');
 }

 if (isset($_GET['w']) && !empty($_GET['w'])) {
   switch($_GET['w']) {
     case 'server':
       $what = 'Server';
       $obj = new Server();
       $page['title'] .= $what;
       $content = new Template('../tpl/form_ds.tpl');
       $content->set('obj', $obj);
       $content->set('what', $what);
       $content->set('page', $page);
       $cfs = $lm->o_login->getListPref('server');
       if (!$cfs) {
         $cfs = $obj->printCols();
       }
       $content->set('cfs', $cfs);
       if (isset($_POST['submit'])) { /* clicked on the Edit button */
         if (isset($_POST['v']) && is_array($_POST['v'])) {
           $cfs = array();
           foreach($_POST['v'] as $v => $on) {
	     $cfs[] = $v;
           }
           $lm->o_login->setListPref('server', $cfs);
           $content = new Template('../tpl/message.tpl');
           $content->set('msg', "Display Settings for server have been updated");
           goto screen;
         } else {
	   $errors = array('You can\'t have a list with no fields selected');
           $content->set('error', $errors);
           $content->set('obj', $obj);
           goto screen;
         }
       }
     break;
     default:
       $content = new Template('../tpl/error.tpl');
       $content->set('error', 'Unknown option or not yet implemented');
     break;
   }
 } else {
   $content = new Template('../tpl/error.tpl');
   $content->set('error', "I don't know what to list...");
 }

screen:
 $head->set('page', $page);
 if (isset($a_link)) $foot->set('a_link', $a_link);
 $index->set('head', $head);
 $index->set('content', $content);
 $index->set('foot', $foot);

 echo $index->fetch();

} catch (ExitException $e) {
     
    if ($e->type == 2) { 
        echo Utils::getJSONError($e->getMessage());
    } else {
        $h = Utils::getHTTPError($e->getMessage());
        echo $h->fetch();
    }    
     
} catch (Exception $e) {
    /* @TODO: LOG EXCEPTION */
    $h = Utils::getHTTPError('Unexpected Exception');
    echo $h->fetch();
}

?>
