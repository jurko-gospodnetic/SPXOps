<?php
/**
 * Login object
 *
 * @author Gouverneur Thomas <tgo@espix.net>
 * @copyright Copyright (c) 2007-2012, Gouverneur Thomas
 * @version 1.0
 * @package objects
 * @category classes
 * @subpackage backend
 * @filesource
 */


class Login extends mysqlObj
{
  public $id = -1;
  public $username = '';
  public $password = '';
  public $password_c = ''; /* only for form-based validation */
  public $fullname = '';
  public $email = '';
  public $f_admin = 0;
  public $f_ldap = 0;
  public $t_last = -1;
  public $t_add = -1;
  public $t_upd = -1;

  public $a_ugroup = array();
  public $a_right = array();

  public function link() {
    return '<a href="/view/w/login/i/'.$this->id.'">'.$this.'</a>';
  }

  public function equals($z) {
    if (!strcmp($this->username, $z->username)) {
      return true;
    }
    return false;
  }


  public function __toString() {
    return $this->username;
  }

  public function valid($new = true) { /* validate form-based fields */
    global $config;
    $ret = array();

    if (empty($this->username)) {
      $ret[] = 'Missing Username';
    } else {
      if ($new) { /* check for already-exist */
        $check = new Login();
	$check->username = $this->username;
        if (!$check->fetchFromField('username')) {
	  $this->username = '';
	  $ret[] = 'Username already exist';
	  $check = null;
	}
      }
    }

    $lm = LoginCM::getInstance();
    if ($this->f_admin && !$lm->o_login->f_admin) {
      $ret[] = 'You cannot add an admin user as you aren\'t administrator yourself.';
      $this->f_admin = false;
    }

    if (empty($this->email)) {
      $ret[] = 'Missing E-Mail';
    } else {
      if (!HTTP::checkEmail($this->email)) {
	$this->email = '';
	$ret[] = 'Wrong E-mail address';
      }
    }

    if (empty($this->password) && $new) {
      $ret[] = 'Missing Password';
      $this->password = $this->password_c = '';
    }

    if (empty($this->password_c) && (!empty($this->password) && $new)) {
      $ret[] = 'Missing Password confirmation';
      $this->password = $this->password_c = '';
    }

    if (strlen($this->password) < $config['minpassword'] && !empty($this->password_c)) {
      $ret[] = 'Password is too short, should be '.$config['minpassword'].' length minimum';
      $this->password = $this->password_c = '';
    }

    if (strcmp($this->password, $this->password_c) && $new && !empty($this->password_c)) {
      $ret[] = 'Password and its confirmation doesn\'t match';
      $this->password = $this->password_c = '';
    }

    if (empty($this->fullname)) {
      $ret[] = 'Missing Full Name';
    }
    

    if (count($ret)) {
      return $ret;
    } else {
      return null;
    }
  }

  public function fetchRights() {
    $this->a_right = array();
    $this->fetchJT('a_ugroup');
    foreach($this->a_ugroup as $ug) {
      $ug->fetchJT('a_right');
      foreach($ug->a_right as $r) {
        if (!isset($this->a_right[$r->short])) {
	  $this->a_right[$r->short] = $ug->level[''.$r];
	} else {
	  $ra = array(R_VIEW, R_ADD, R_EDIT, R_DEL);
	  foreach($ra as $rr) {
	    if (!($this->a_right[$r->short] & $rr) &&
	         ($ug->level[''.$r])) {
	      $this->a_right[$r->short] |= $rr;
	    }
	  }
	}
      }
    }
  }

  public function cRight($short, $right) {
    if (isset($this->a_right[$short]) &&
	$this->a_right[$short] & $right) {
      return true;
    }
    return false;
  }

  public function bcrypt($input, $rounds = 7)
  {
    $salt = "";
    $salt_chars = array_merge(range('A','Z'), range('a','z'), range(0,9));
    for($i=0; $i < 22; $i++) {
      $salt .= $salt_chars[array_rand($salt_chars)];
    }
    $this->password = crypt($input, sprintf('$2y$%02d$', $rounds) . $salt);
  }

  public function auth($pwd) {
    if (crypt($pwd, $this->password) == $this->password) {
      return TRUE;
    } else {
      return FALSE;
    }
  }

  public static function printCols() {
    return array('Username' => 'username',
                 'Fullname' => 'fullname',
                 'E-Mail' => 'email',
                 'Admin' => 'f_admin',
                 'LDAP' => 'f_ldap',
                 'Added' => 't_add',
                );
  }

  public function toArray() {

    return array(
                 'username' => $this->username,
                 'fullname' => $this->fullname,
                 'email' => $this->email,
                 'f_admin' => $this->f_admin,
                 'f_ldap' => $this->f_ldap,
                 't_add' => date('d-m-Y', $this->t_add),
                );
  }

  public function htmlDump() {
    return array(
        'Username' => $this->username,
        'Full Name' => $this->fullname,
        'E-Mail' => $this->email,
        'Admin?' => ($this->f_admin)?'<i class="icon-ok-sign"></i>':'<i class="icon-remove-sign"></i>',
        'LDAP?' => ($this->f_ldap)?'<i class="icon-ok-sign"></i>':'<i class="icon-remove-sign"></i>',
        'Last seen' => date('d-m-Y', $this->t_last),
        'Updated on' => date('d-m-Y', $this->t_upd),
        'Added on' => date('d-m-Y', $this->t_add),
    );
  }

  public function delete() {

    parent::_delAllJT();
    parent::delete();
  }

 /**
  * ctor
  */
  public function __construct($id=-1)
  {
    $this->id = $id;
    $this->_table = 'list_login';
    $this->_nfotable = NULL;
    $this->_my = array(
                        'id' => SQL_INDEX,
                        'username' => SQL_PROPE|SQL_EXIST,
                        'password' => SQL_PROPE,
                        'fullname' => SQL_PROPE,
                        'email' => SQL_PROPE,
                        'f_admin' => SQL_PROPE,
                        'f_ldap' => SQL_PROPE,
                        't_last' => SQL_PROPE,
                        't_add' => SQL_PROPE,
                        't_upd' => SQL_PROPE
                 );


    $this->_myc = array( /* mysql => class */
                        'id' => 'id',
                        'username' => 'username',
                        'password' => 'password',
                        'fullname' => 'fullname',
                        'email' => 'email',
                        'f_admin' => 'f_admin',
                        'f_ldap' => 'f_ldap',
                        't_last' => 't_last',
                        't_add' => 't_add',
                        't_upd' => 't_upd'
                 );
                /* array(),  Object, jt table,     source mapping, dest mapping, attribuytes */
    $this->_addJT('a_ugroup', 'UGroup', 'jt_login_ugroup', array('id' => 'fk_login'), array('id' => 'fk_ugroup'), array());

  }

}
?>
