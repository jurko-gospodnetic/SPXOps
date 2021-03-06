<?php
/**
 * Pool object
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
class Pool extends MySqlObj
{
    public $id = -1;
    public $name = '';
    public $type = '';
    public $size = -1;
    public $used = -1;
    public $status = "";
    public $f_cluster = 0;
    public $fk_server = -1;
    public $t_add = -1;
    public $t_upd = -1;

    public $o_server = null;
    public $a_dataset = array();
    public $a_disk = array();

    /* JT attrs */
    public $slice = array();
    public $role = array();

    public function log($str)
    {
        Logger::log($str, $this);
    }

    public function isSlog()
    {
        if (!count($this->role)) {
            $this->fetchJT('a_disk');
        }

        foreach ($this->role as $role) {
            if (!strcmp($role, 'logs')) {
                return true;
            }
        }

        return false;
    }

    public function link()
    {
        return '<a href="/view/w/pool/i/'.$this->id.'">'.$this.'</a>';
    }

    public function getTypeStats()
    {
        $ret = array();
        foreach ($this->a_dataset as $ds) {
            if (!isset($ret[$ds->type])) {
                $ret[$ds->type] = $ds->used;
            } else {
                $ret[$ds->type] += $ds->used;
            }
        }

        return $ret;
    }

    public function equals($z)
    {
        if (!strcmp($this->name, $z->name) && $this->fk_server && $z->fk_server) {
            return true;
        }

        return false;
    }

    public function fetchAll($all = 1)
    {
        global $roles;
        try {
            if (!$this->o_server && $this->fk_server > 0) {
                $this->fetchFK('fk_server');
            }

            if ($all) {
                $this->fetchRL('a_dataset');
                $this->fetchJT('a_disk');
                /* sort drives per role */
                $roles = array();
                foreach($this->a_disk as $d) {
                    $roles[] = array_values($d->role)[0];
                }
                natsort($roles);
                if(!function_exists('cmp_disk_role')) {
                    function cmp_disk_role($a, $b) {
                        global $roles;
                        $i = $v_a = $v_b = 0;
                        foreach($roles as $r) {
                            $i++;
                            if (!$v_a && !strcmp($r, array_values($a->role)[0])) $v_a = $i;
                            if (!$v_b && !strcmp($r, array_values($b->role)[0])) $v_b = $i;
                            if ($v_a && $v_b) break;
                        }
                        if ($v_a == $v_b) return 0;
                        return ($v_a < $v_b) ? -1 : 1;
                    }
                }
                usort($this->a_disk, 'cmp_disk_role');
            }
        } catch (Exception $e) {
            throw($e);
        }
    }

    public function __toString()
    {
        return $this->name;
    }

    public function dump(&$s)
    {
        $this->log(sprintf("%15s: %s", 'ZPool', $this->name), LLOG_INFO);
    }

    public static function printCols($cfs = array())
    {
        return array('Name' => 'name',
             'Size' => 'size',
             'Used' => 'used',
             'Free' => 'free',
             'Status' => 'status',
             'Server' => 'server',
             'Added' => 't_add',
             'Updated' => 't_upd',
        );
    }

    public function toArray($cfs = array())
    {
        if (!$this->o_server && $this->fk_server > 0) {
            $this->fetchFK('fk_server');
        }

        return array(
        'name' => $this->name,
        'size' => $this->size,
        'used' => $this->used,
        'free' => $this->size - $this->used,
        'status' => $this->status,
        'server' => ($this->o_server) ? $this->o_server->name : 'Unknown',
        't_add' => $this->size,
        't_upd' => $this->size,
        );
    }

    public function htmlDump()
    {
        if (!$this->o_server && $this->fk_server > 0) {
            $this->fetchFK('fk_server');
        }

        return array(
        'Name' => $this->name,
        'Size' => Pool::formatBytes($this->size),
        'Used' => Pool::formatBytes($this->used),
        'Free' => Pool::formatBytes($this->size - $this->used),
        'Status' => $this->status,
        'Server' => ($this->o_server) ? $this->o_server->link() : 'Unknown',
        'Added on' => date('d-m-Y', $this->t_add),
        'Last Updated' => date('d-m-Y', $this->t_upd),
        );
    }

    public static function formatGBytes($k)
    {
        if (abs($k) < 1024) {
            return round($k, 2)." GB";
        }
        $k = $k / 1024;
        if (abs($k) < 1024) {
            return round($k, 2)." TB";
        }
        $k = $k / 1024;

        return round($k, 2)." PB";
    }


    public static function formatBytes($k)
    {
        $k /= 1024;
        if ($k < 1024) {
            return round($k, 2)." KB";
        }
        $k = $k / 1024;
        if ($k < 1024) {
            return round($k, 2)." MB";
        }
        $k = $k / 1024;
        if ($k < 1024) {
            return round($k, 2)." GB";
        }
        $k = $k / 1024;
        if ($k < 1024) {
            return round($k, 2)." TB";
        }
        $k = $k / 1024;

        return round($k, 2)." PB";
    }

    public static function formatSize($size)
    {
        if (!strcmp($size, 'none')) {
            return 0;
        }
        $unit = strtoupper($size[strlen($size) - 1]);
        if (is_numeric($unit)) {
            return $size;
        }
        $size[strlen($size) - 1] = ' ';
        switch ($unit) {
          case "K":
            return round($size * 1024);
          break;
          case "M":
            return round($size * 1024 * 1024);
          break;
          case "G":
            return round($size * 1024 * 1024 * 1024);
          break;
          case "T":
            return round($size * 1024 * 1024 * 1024 * 1024);
          break;
          case "P":
            return round($size * 1024 * 1024 * 1024 * 1024 * 1024);
          break;
          default:
            return -1;
          break;
        }
    }

    public function delete()
    {
        $this->fetchAll(1);
        foreach ($this->_rel as $r) {
            if ($this->{$r->ar} && count($this->{$r->ar})) {
                foreach ($this->{$r->ar} as $e) {
                    $e->delete();
                }
            }
        }

        parent::_delAllJT();
        parent::delete();
    }

  /**
   * ctor
   */
  public function __construct($id = -1)
  {
      $this->id = $id;
      $this->_table = 'list_pool';
      $this->_nfotable = null;
      $this->_my = array(
                        'id' => SQL_INDEX,
                        'name' => SQL_PROPE|SQL_EXIST,
                        'type' => SQL_PROPE,
                        'size' => SQL_PROPE,
                        'used' => SQL_PROPE,
                        'status' => SQL_PROPE,
                        'f_cluster' => SQL_PROPE,
                        'fk_server' => SQL_PROPE,
                        't_add' => SQL_PROPE,
                        't_upd' => SQL_PROPE,
                 );
      $this->_myc = array( /* mysql => class */
                        'id' => 'id',
                        'name' => 'name',
                        'type' => 'type',
                        'size' => 'size',
                        'used' => 'used',
                        'status' => 'status',
                        'f_cluster' => 'f_cluster',
                        'fk_server' => 'fk_server',
                        't_add' => 't_add',
                        't_upd' => 't_upd',
                 );

      $this->_addFK("fk_server", "o_server", "Server");

      $this->_addRL("a_dataset", "Dataset", array('id' => 'fk_pool'));

            /* array(),  Object, jt table,     source mapping, dest mapping, attribuytes */
    $this->_addJT('a_disk', 'Disk', 'jt_disk_pool', array('id' => 'fk_pool'), array('id' => 'fk_disk'), array('slice', 'role'));
  }
}
