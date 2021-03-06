<?php
/**
 * Package object
 *
 * @author Gouverneur Thomas <tgo@espix.net>
 * @copyright Copyright (c) 2007-2012, Gouverneur Thomas
 * @version 1.0
 * @package objects
 * @category classes
 * @subpackage backend
 * @filesource
 * @license https://raw.githubusercontent.com/tgouverneur/SPXOps/master/LICENSE.md Revised BSD License
 */
class Pkg extends MySqlObj
{
    public $id = -1;
    public $name = '';
    public $lname = '';
    public $arch = '';
    public $version = '';
    public $basedir = '';
    public $vendor = '';
    public $desc = '';
    public $fmri = '';
    public $status = '';
    public $fk_server = -1;
    public $fk_vm = -1;
    public $t_add = -1;
    public $t_upd = -1;

    public $o_server = null;
    public $o_vm = null;

    public function log($str)
    {
        Logger::log($str, $this);
    }

    public function equals($z)
    {
        if (!strcmp($this->name, $z->name) &&
    $this->fk_server && $z->fk_server) {
            return true;
        }

        return false;
    }

    public function fetchAll($all = 1)
    {
        try {
            if (!$this->o_server && $this->fk_server > 0) {
                $this->fetchFK('fk_server');
            }
        } catch (Exception $e) {
            throw($e);
        }
    }

    public function __toString()
    {
        return $this->name;
    }

    public static function printCols($cfs = array())
    {
        return array('Name' => 'name',
                 'Version' => 'version',
                 'Details' => 'details',
                 'Added on' => 't_add',
                );
    }

    public function toArray($cfs = array())
    {
        return array(
                 'name' => $this->name,
                 'version' => $this->version,
                 'details' => '<a href="/view/w/pkg/i/'.$this->id.'">View</a>',
                 't_add' => date('d-m-Y', $this->t_add),
                );
    }

  /**
   * ctor
   */
  public function __construct($id = -1)
  {
      $this->id = $id;
      $this->_table = 'list_pkg';
      $this->_nfotable = null;
      $this->_my = array(
                        'id' => SQL_INDEX,
                        'name' => SQL_PROPE|SQL_EXIST,
                        'lname' => SQL_PROPE,
                        'arch' => SQL_PROPE,
                        'version' => SQL_PROPE,
                        'basedir' => SQL_PROPE,
                        'vendor' => SQL_PROPE,
                        'desc' => SQL_PROPE,
                        'fmri' => SQL_PROPE,
                        'status' => SQL_PROPE,
                        'fk_server' => SQL_PROPE,
                        'fk_vm' => SQL_PROPE,
                        't_add' => SQL_PROPE,
                        't_upd' => SQL_PROPE,
                 );
      $this->_myc = array( /* mysql => class */
                        'id' => 'id',
                        'name' => 'name',
                        'lname' => 'lname',
                        'arch' => 'arch',
                        'version' => 'version',
                        'basedir' => 'basedir',
                        'vendor' => 'vendor',
                        'desc' => 'desc',
                        'fmri' => 'fmri',
                        'status' => 'status',
                        'fk_server' => 'fk_server',
                        'fk_vm' => 'fk_vm',
                        't_add' => 't_add',
                        't_upd' => 't_upd',
                 );

      $this->_addFK("fk_server", "o_server", "Server");
      $this->_addFK("fk_vm", "o_vm", "VM");
  }
}
