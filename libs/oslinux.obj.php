<?php

class OSLinux extends OSType
{
  public static $binPaths = array(
    "/bin",
    "/usr/bin",
    "/usr/local/bin",
    "/sbin",
    "/usr/sbin",
    "/usr/local/sbin",
  );

  protected static $_update = array(
    "update_uname",
    "update_release",
    "update_dmidecode",
    "update_network",
    "update_hostid",
    "update_packages",
    "update_proc",
    "update_nfs_shares",
    "update_nfs_mount",
    "update_lvm",
    "update_cdp",
//    "update_swap",
  );

  /* Updates function for Linux */

  /**
   * nfs_shares
   */
  public static function update_nfs_shares(&$s) {

    $cat = $s->findBin('cat');
    $cmd_cat = "$cat /etc/exports";
    $out_cat = $s->exec($cmd_cat);

    $lines = explode(PHP_EOL, $out_cat);
    $found_n = array();

    foreach($lines as $line) {
      $line = trim($line);
      if (empty($line) || preg_match('/^#/', $line)) {
        continue;
      }

      $f = preg_split("/\s+/", $line);
      if (count($f) < 2) {
        continue; // Malformed line
      }

      $no = new NFS();
      $no->type = 'share';
      $no->fk_server = $s->id;
      $no->share = $f[0];
      $changed = false;
      if ($no->fetchFromFields(array('type', 'fk_server', 'share'))) {
        $no->insert();
        $s->log("Added $no", LLOG_INFO);
        $s->a_nfss[] = $no;
      }

      if (strcmp($no->acl, $f[1])) {
        $no->acl = $f[1];
        $s->log("Changed acl of $no to be ".$no->acl, LLOG_DEBUG);
        $changed = true;
      }
      $df = $s->findBin('df');
      $cmd_df = "$df -k ".$no->share;
      $out_df = $s->exec($cmd_df);

      $lines_df = explode(PHP_EOL, $out_df);
      if (count($lines_df) == 2) {
        $line_df = $lines_df[1];
        $f_df = preg_split("/\s+/", $line_df);
        if ($no->size != $f_df[1]) {
          $no->size = $f_df[1];
          $changed = true;
          $s->log("Changed size of $no to be ".$no->size, LLOG_DEBUG);
        }
        if ($no->used != $f_df[2]) {
          $no->used = $f_df[2];
          $changed = true;
          $s->log("Changed used of $no to be ".$no->size, LLOG_DEBUG);
        }
      }
      if ($changed) $no->update();
      $found_n[''.$no] = $no;
    }

    foreach($s->a_nfss as $ns) {
      if (isset($found_n[''.$ns])) {
        continue;
      }
      $s->log("Removing NFS $ns", LLOG_INFO);
      $ns->delete();
    }

    return 0;
  }

  /**
   * nfs_mount
   */
  public static function update_nfs_mount(&$s) {

    $cat = $s->findBin('cat');
    $cmd_cat = "$cat /proc/mounts";
    $out_cat = $s->exec($cmd_cat);

    $lines = explode(PHP_EOL, $out_cat);
    $found_n = array();

    foreach($lines as $line) {
      $line = trim($line);
      if (empty($line)) {
        continue;
      }

      $f = preg_split("/\s+/", $line);
      if (count($f) < 5) {
        continue; // Malformed line
      }

      if (strncmp($f[2], 'nfs', 3)) {
        continue; // not nfs
      }

      $no = new NFS();
      $no->type = 'mount';
      $no->fk_server = $s->id;
      $no->path = $f[1];
      $changed = false;
      if ($no->fetchFromFields(array('type', 'fk_server', 'path'))) {
        $no->insert();
        $s->log("Added $no", LLOG_INFO);
        $s->a_nfsm[] = $no;
      }
      $remote_f = explode(':', $f[0]);
      if (strcmp($no->share, $remote_f[1])) {
        $no->share = $remote_f[1];
        $s->log("Changed share of $no to be ".$no->share, LLOG_INFO);
        $changed = true;
      }
      if (strcmp($no->dest, $remote_f[0])) {
        $no->dest = $remote_f[0];
        $s->log("Changed dest of $no to be ".$no->dest, LLOG_INFO);
        $changed = true;
      }
      $df = $s->findBin('df');
      $cmd_df = "$df -P -k ".$no->path;
      $out_df = $s->exec($cmd_df);

      $lines_df = explode(PHP_EOL, $out_df);
      if (count($lines_df) == 2) {
        $line_df = $lines_df[1];
        $f_df = preg_split("/\s+/", $line_df);
        if ($no->size != $f_df[1]) {
          $no->size = $f_df[1];
          $changed = true;
          $s->log("Changed size of $no to be ".$no->size, LLOG_INFO);
        }
        if ($no->used != $f_df[2]) {
          $no->used = $f_df[2];
          $changed = true;
          $s->log("Changed used of $no to be ".$no->size, LLOG_INFO);
        }
      }
      if ($changed) $no->update();
      $found_n[''.$no] = $no;
    }

    foreach($s->a_nfsm as $ns) {
      if (isset($found_n[''.$ns])) {
        continue;
      }
      $s->log("Removing NFS $ns", LLOG_INFO);
      $ns->delete();
    }

    return 0;
  }

  /**
   * packages
   */
  public static function update_packages_deb(&$s) {
    //dpkg-qu ery -W -f '${Package};${Version};${Architecture};${Status};${binary:Summary}\n' '*'
    $dpkg = $s->findBin('dpkg-query');
    $cmd_dpkg = "$dpkg -W -f '\${Package};\${Version};\${Architecture};\${Status};\${binary:Summary}\\n' '*'";
    $out_dpkg = $s->exec($cmd_dpkg);

    $lines = explode(PHP_EOL, $out_dpkg);
    $found_p = array();

    $pkg = null;
    foreach($lines as $line) {
      $line = trim($line);
      if (empty($line)) {
        continue;
      }
      $f = explode(';', $line);
      if (preg_match('/not-installed/', $f[3])) {
        continue;
      }
      $pkg = array();
      $pkg['name'] = $f[0];
      $pkg['version'] = $f[1];
      $pkg['arch'] = $f[2];
      $pkg['status'] = $f[3];
      $pkg['desc'] = $f[4];
      $found_p[$f[0]] = $pkg;
    }

    return $found_p;
  }

  public static function update_packages_rpm(&$s) {
    $rpm = $s->findBin('rpm');
    $cmd_rpm = "$rpm -qa --qf '%{NAME};%{VERSION};%{ARCH};;%{SUMMARY}\n'";
    $out_rpm = $s->exec($cmd_rpm);

    $lines = explode(PHP_EOL, $out_rpm);
    $found_p = array();

    $pkg = null;
    foreach($lines as $line) {
      $line = trim($line);
      if (empty($line)) {
        continue;
      }
      $f = explode(';', $line);
      if (preg_match('/not-installed/', $f[3])) {
        continue;
      }
      $pkg = array();
      $pkg['name'] = $f[0];
      $pkg['version'] = $f[1];
      $pkg['arch'] = $f[2];
      $pkg['status'] = $f[3];
      $pkg['desc'] = $f[4];
      $found_p[$f[0]] = $pkg;
    }

    return $found_p;
  }

  public static function update_packages_ebd(&$s) {
    $equery = $s->findBin('equery');
    $cmd_equery = "$equery -C l -F '\$name;\$fullversion;;;\$category' '*'";
    $out_equery = $s->exec($cmd_equery);

    $lines = explode(PHP_EOL, $out_equery);
    $found_p = array();

    $pkg = null;
    foreach($lines as $line) {
      $line = trim($line);
      if (empty($line)) {
        continue;
      }
      $f = explode(';', $line);
      $pkg = array();
      $pkg['name'] = $f[0];
      $pkg['version'] = $f[1];
      $pkg['arch'] = $f[2];
      $pkg['status'] = $f[3];
      $pkg['desc'] = $f[4];
      $found_p[$f[0]] = $pkg;
    }

    return $found_p;

  }


  public static function update_packages(&$s) {

    $distrib = $s->data('linux:name');
    if (empty($distrib)) {
      return 0;
    }
    switch($s->data('linux:name')) {
      case 'Debian':
        $found_p = OSLinux::update_packages_deb($s);
      break;
      case 'RHEL':
      case 'SLES':
        $found_p = OSLinux::update_packages_rpm($s);
      break;
      case 'Gentoo':
        $found_p = OSLinux::update_packages_ebd($s);
      break;
      default:
        return 0;
      break;
    }

    foreach ($found_p as $pkg) {
 
      $po = new Pkg();
      $po->name = $pkg['name'];
      $po->fk_server = $s->id;

      if ($po->fetchFromFields(array('name', 'fk_server'))) {
        $s->log('new package found: '.$po, LLOG_INFO);
        $po->insert();
        array_push($s->a_pkg, $po);
      }

      $f = array('lname', 'arch', 'version', 'basedir', 'vendor', 'desc', 'fmri', 'status');
      foreach($f as $field) {
        if (isset($pkg[$field]) && $pkg[$field] != $po->{$field}) {
          $po->{$field} = $pkg[$field];
          $s->log("$po:$field => ".$pkg[$field], LLOG_DEBUG);
        }
      }
      $po->update();
    }

    foreach($s->a_pkg as $po) {
      if (isset($found_p[$po->name])) {
        continue;
      }
      $s->log("Removing package $po", LLOG_INFO);
      $po->delete();
    }
    return 0;


    return 0;
  }

  /**
   * hostid
   */
  public static function update_hostid(&$s) {

    /* get hostid */
    $hostid = $s->findBin('hostid');

    $cmd_hostid = "$hostid";
    $out_hostid = $s->exec($cmd_hostid);

    if ($s->data('os:hostid') != $out_hostid) {
      $s->setData('os:hostid', $out_hostid);
      $s->log('os:hostid => '.$out_hostid, LLOG_INFO);
    }

    return 0;
  }

  /**
   * network
   */
  public static function update_network(&$s) {

    $ip = $s->findBin('ip');
    $cmd_ip = "$ip addr";
    $out_ip = $s->exec($cmd_ip);

    $lines = explode(PHP_EOL, $out_ip);

    $found_if = array();
    $c_if = null;

    foreach($lines as $line) {
      $vnet = null;
      $pnet = null;
      $line = trim($line);
      if (empty($line))
	continue;
      
      if (preg_match('/^[0-9]*: ([a-z0-9]*): ([A-Z,_<>]*)/', $line, $m)) {
        $pnet = new Net();
	$pnet->fk_server = $s->id;
	$pnet->layer = 2; // ether
        $pnet->ifname = $m[1];
	if ($pnet->fetchFromFields(array('layer', 'ifname', 'fk_server'))) {
          $pnet->insert();
	  $s->log("Added $pnet to server", LLOG_INFO);
	  $s->a_net[] = $pnet;
	}
        if (strcmp($pnet->flags, $m[2])) {
          $pnet->flags = $m[2];
	  $s->log("Updated flags for $pnet to be ".$pnet->flags, LLOG_DEBUG);
	  $pnet->update();
	}
        $c_if = $pnet;
	$found_if[''.$c_if] = $c_if;
      } else if (preg_match('/^link\/ether/', $line)) {
        $f_eth = explode(' ', $line);
	if (strcmp($c_if->address, $f_eth[1])) {
	  $c_if->address = $f_eth[1];
	  $s->log("Updated layer 2 address for $c_if to be ".$c_if->address, LLOG_DEBUG);
	  $c_if->update();
	  $found_if[''.$c_if] = $c_if;
	}

      } else if (preg_match('/^inet ([0-9\.\/]*) /', $line, $m)) {
        $f_eth = explode(' ', $line);
        $vnet = new Net();
        $vnet->ifname = $c_if->ifname;
	$vnet->fk_server = $s->id;
	$vnet->layer = 3; /* IP */
	$ipaddr = explode('/', $m[1]);
        $vnet->address = $ipaddr[0];
        $vnet->netmask = $ipaddr[1];
	if ($vnet->fetchFromFields(array('ifname', 'version', 'fk_server', 'layer', 'address', 'netmask'))) {
	  $vnet->insert();
	  $s->log("Added alias $vnet to server", LLOG_INFO);
	  $s->a_net[] = $vnet;
	}
	if ($f_eth[count($f_eth) - 2] == 'secondary') {
          $alias = explode(':', $f_eth[count($f_eth) - 1], 2);
	  if (count($alias) == 2) {
	    if (strcmp($vnet->alias, $alias[1])) {
	      $vnet->alias = $alias[1];
              $s->log("Updated alias for $vnet to be ".$vnet->alias, LLOG_DEBUG);
	      $vnet->update();
	    }
	  }
	}
      
      } else if (preg_match('/^inet6 ([0-9a-z:\/]*) /', $line, $m)) {
        $f_eth = explode(' ', $line);
        $vnet = new Net();
        $vnet->ifname = $c_if->ifname;
        $vnet->fk_server = $s->id;
        $vnet->layer = 3; /* IP */
        $vnet->version = 6; /* v6 */
        $ipaddr = explode('/', $m[1]);
        $vnet->address = $ipaddr[0]; 
        $vnet->netmask = $ipaddr[1];
        if ($vnet->fetchFromFields(array('ifname', 'version', 'fk_server', 'layer', 'address', 'netmask'))) {
          $vnet->insert();
          $s->log("Added alias6 $vnet to server", LLOG_INFO);
	  $s->a_net[] = $vnet;
        }
        if ($f_eth[count($f_eth) - 2] == 'secondary') {
          $alias = explode(':', $f_eth[count($f_eth) - 1], 2);
          if (count($alias) == 2) {
            if (strcmp($vnet->alias, $alias[1])) {
              $vnet->alias = $alias[1];
              $s->log("Updated alias6 for $vnet to be ".$vnet->alias, LLOG_DEBUG);
              $vnet->update();
            }
          }
        }
      }
      $found_if[''.$vnet] = $vnet;
    }

    foreach($s->a_net as $n) {
      if (isset($found_if[''.$n])) {
        continue;
      }
      $s->log("Removing net $n", LLOG_INFO);
      $n->delete();
    }

    /* default router */
    
    $cmd_ip = "$ip ro";
    $out_ip = $s->exec($cmd_ip);

    $lines = explode(PHP_EOL, $out_ip);
    $defrouter = null;

    foreach($lines as $line) {
      $line = trim($line);
      if (empty($line))
        continue;

      $f = preg_split("/\s+/", $line);

      if (!strcmp($f[0], 'default')) {
	if (!strcmp($f[1], 'via')) {
          $defrouter = $f[2];
	}
	break;
      }
    }

    if ($defrouter &&
	strcmp($s->data('net:defrouter'), $defrouter)) {
      $s->setData('net:defrouter', $defrouter);
      $s->log("Change defrouter => $defrouter", LLOG_INFO);
    }

    return 0;
  }

  /**
   * dmidecode
   */
  public static function update_dmidecode(&$s) {

    $dmidecode = $s->findBin('dmidecode');
    $sudo = $s->findBin('sudo');
    $cmd_dmidecode = "$sudo $dmidecode -t 1 -q";
    $out_dmidecode = $s->exec($cmd_dmidecode);

    $lines = preg_split('/\r\n|\r|\n/', $out_dmidecode);
    $vendor = $pname = $serial = 'Unknown';

    foreach($lines as $line) {
      $line = trim($line);
      if (empty($line)) {
        continue;
      }

      $f = explode(':', $line, 2);

      if (count($f) != 2) {
        continue;
      }

      $f[0] = trim($f[0]);
      $f[1] = trim($f[1]);

      switch($f[0]) {
        case 'Manufacturer':
	  $vendor = $f[1];
	break;
	case 'Product Name':
	  $pname = $f[1];
	break;
	case 'Serial Number':
	  $serial = $f[1];
	break;
      }
    }

    if (!empty($serial)) {
      if ($s->o_pserver) {
        if ($s->o_pserver->serial != $serial) {
          $s->o_pserver->serial = $serial;
          $s->log("Updated serial number: $serial", LLOG_INFO);
          $s->o_pserver->update();
        }
      }
    }

    $mo = new Model();
    $mo->name = $pname;
    $mo->vendor = $vendor;
    if ($mo->fetchFromFields(array('name', 'vendor'))) {
      $mo->insert();
    }
    if ($s->o_pserver) {
      if ($mo->id != $s->o_pserver->fk_model) {
        $s->log('Updating HW Model to be: '.$mo, LLOG_INFO);
        $s->o_pserver->fk_model = $mo->id;
        $s->o_pserver->update();
      }
    }

    return 0;
  }

  /**
   * cat /etc/release
   */
  public static function update_release(&$s) {

    global $config;
    @include_once($config['rootpath'].'/libs/functions.lib.php');

    /* get cat */
    $cat = $s->findBin('cat');

    $distrib = '';
    $known = array(
      '/etc/redhat-release' => 'RHEL',
      '/etc/lsb-release'=> 'LSB',
      '/etc/debian_version' => 'debian',
      '/etc/SuSE-release' => 'SLES',
      '/etc/gentoo-release'=> 'Gentoo',
    );

    foreach($known as $file => $dis) {
      try {
        if ($s->isFile($file)) {
          $s->log("[debug] detected $file - $dis", LLOG_DEBUG);
          $distrib = $dis;
          break;
        }
      } catch (Exception $e) {
        throw($e);
      }
    }

    $d['name'] = '';
    $d['version'] = '';
    $d['ver_name'] = '';

    $a_os = null;
    $a_lsb = null;

    if ($s->isFile('/etc/os-release')) {
      $cmd = "$cat /etc/os-release";
      $os_file = $s->exec($cmd);
      $a_os = parseVars($os_file);
    }

    if ($s->isFile('/etc/lsb-release')) {
      $cmd = "$cat /etc/lsb-release";
      $lsb_file = $s->exec($cmd);
      $a_lsb = parseVars($lsb_file);
    }

    switch($distrib) {
      case 'RHEL':
        $d['name'] = 'RHEL';
        
        break;
      case 'LSB': 
        if (isset($a_lsb['DISTRIB_ID'])) {
          $d['name'] = $a_lsb['DISTRIB_ID'];
	}
        if (isset($a_lsb['DISTRIB_RELEASE'])) {
          $d['version'] = $a_lsb['DISTRIB_RELEASE'];
	}
        if (isset($a_lsb['DISTRIB_CODENAME'])) {
          $d['ver_name'] = $a_lsb['DISTRIB_CODENAME'];
	}
        break;
      case 'debian': 
        $d['name'] = 'Debian';
        $cmd = "$cat /etc/debian_version";
        $o_cmd = $s->exec($cmd);
        if (!empty($o_cmd)) {
          if (preg_match('/^[0-9]/', $o_cmd)) {
            $d['version'] = $o_cmd;
          } else {
            $d['ver_name'] = $o_cmd;
	  }
        }
        if ($a_os) {
          if (isset($a_os['PRETTY_NAME'])) {
            $pn = $a_os['PRETTY_NAME'];
            $pn = preg_replace('/Debian GNU\/Linux /', '', $pn);
            $d['ver_name'] = $pn;
          }
        }
        break;
      case 'SLES': 
        $d['name'] = 'SLES';
        $cmd = "$cat /etc/SuSE-release";
        $o_cmd = $s->exec($cmd);
        if (!empty($o_cmd)) {
          $v = '';
          $vars = parseVars($o_cmd);
          if (isset($vars['VERSION'])) {
            $v = $vars['VERSION'];
          }
          if (isset($vars['PATCHLEVEL'])) {
            $v .= '.'.$vars['PATCHLEVEL'];
          }
          $d['version'] = $v;
        }
        break;
      case 'Gentoo':
        $d['name'] = 'Gentoo';
        $cmd = "$cat /etc/gentoo-release";
        $o_cmd = $s->exec($cmd);
        if (!empty($o_cmd)) {
          $v = explode(' ', $o_cmd);
          $v = $v[count($v) - 1];
          $d['version'] = $v;
        }
        break;
      default:
        $s->log('Unknown Linux distribution', LLOG_INFO);
        break;
    }

    if ($s->data('linux:name') != $d['name']) {
      $s->log('linux:name => '.$d['name'], LLOG_INFO);
      $s->setData('linux:name', $d['name']);
    }

    if ($s->data('linux:version') != $d['version']) {
      $s->log('linux:version => '.$d['version'], LLOG_INFO);
      $s->setData('linux:version', $d['version']);
    }

    if ($s->data('linux:ver_name') != $d['ver_name']) {
      $s->log('linux:ver_name => '.$d['ver_name'], LLOG_INFO);
      $s->setData('linux:ver_name', $d['ver_name']);
    }

    return 0;
  }

  /**
   * /proc parsing
   */
  public static function update_proc(&$s) {

    /* get cat */
    $cat = $s->findBin('cat');
    $cmd_cat = "$cat /proc/cpuinfo";
    $out_cat = $s->exec($cmd_cat);

    $thread = 0;
    $physical = 0;
    $cores = 0;
    $cputype = '';

    $lines = explode(PHP_EOL, $out_cat);

    foreach($lines as $line) {
      $line = trim($line);
      if (empty($line)) {
	continue;
      }
      $f = explode(':', $line, 2);
      $name = trim($f[0]);
      $value = trim($f[1]);
      switch($name) {
        case 'processor':
	  $thread++;
	break;
        case 'physical id':
	  if ($value == $physical) {
	    $physical++;
	  }
	break;
        case 'cpu cores':
	  if (!$cores) {
	    $cores = $value;
	  }
	break;
        case 'model name':
          if (empty($cputype)) {
	    $cputype = $value;
	  }
	break;
      }
    }
    if ($cores)
      $cores *= $physical;

    if ($s->data('hw:cpu') != $cputype) {
      $s->setData('hw:cpu', $cputype);
      $s->log('hw:cpu => '.$cputype, LLOG_INFO);
    }
    if ($s->data('hw:nrcpu') != $physical) {
      $s->setData('hw:nrcpu', $physical);
      $s->log('Updated hw:nrcpu => '.$physical, LLOG_INFO);
    }
    if ($s->data('hw:nrcore') != $cores) {
      $s->setData('hw:nrcore', $cores);
      $s->log('Updated hw:nrcore => '.$cores, LLOG_INFO);
    }
    if ($s->data('hw:nrstrand') != $thread) {
      $s->setData('hw:nrstrand', $thread);
      $s->log('Updated hw:nrstrand => '.$thread, LLOG_INFO);
    }

    return 0;
  }


  /**
   * uname
   */
  public static function update_uname(&$s) {

    /* get uname -a */
    $uname = $s->findBin('uname');

    $cmd_uname = "$uname -r";
    $out_uname = $s->exec($cmd_uname);
    $kr_version = $out_uname;
    
    $cmd_uname = "$uname -i";
    $out_uname = $s->exec($cmd_uname);
    $hw_class = $out_uname;

    $cmd_uname = "$uname -p";
    $out_uname = $s->exec($cmd_uname);
    $platform = $out_uname;

    $cmd_uname = "$uname -m";
    $out_uname = $s->exec($cmd_uname);
    $cputype = $out_uname;

    if ($s->data('os:kernel') != $kr_version) {
      $s->setData('os:kernel', $kr_version);
      $s->log('os:kernel => '.$kr_version, LLOG_INFO);
    }
    if ($s->data('hw:class') != $hw_class) {
      $s->setData('hw:class', $hw_class);
      $s->log('hw:class => '.$hw_class, LLOG_INFO);
    }
    if ($s->data('hw:platform') != $platform) {
      $s->setData('hw:platform', $platform);
      $s->log('hw:platform => '.$platform, LLOG_INFO);
    }

    return 0;
  }

  /**
   * LVM
   */
  public static function update_lvm(&$s) {

    $sudo = $s->findBin('sudo');
    $vgs = $s->findBin('vgs');

    $cmd_vgs = "$sudo $vgs --noheadings --separator ';'";
    $out_vgs = $s->exec($cmd_vgs);

    $lines = explode(PHP_EOL, $out_vgs);
    $found_v = array();
    $upd = false;
    foreach($lines as $line) {
      $line = trim($line);
      if (empty($line))
	continue;

      $f = preg_split('/;/', $line);

      $name = $f[0];
      $size = Pool::formatSize($f[5]);
      $free = Pool::formatSize($f[6]);
      $used = $size - $free;

      $vg = new Pool();
      $vg->name = $name;
      $vg->fk_server = $s->id;
      $upd = false;
      if ($vg->fetchFromFields(array('fk_server', 'name'))) {
        $s->log("Adding pool $vg", LLOG_INFO);
        $vg->insert();
        $s->a_pool[] = $vg;
      }
      if ($size != $vg->size) {
        $vg->size = $size;
        $upd = true;
        $s->log("Changed pool $vg size => $size", LLOG_DEBUG);
      }
      if ($used != $vg->used) {
        $vg->used = $used;
        $upd = true;
        $s->log("Changed pool $vg used => $used", LLOG_DEBUG);
      }
      if ($upd) $vg->update();
      $found_v[$vg->name] = $vg;
    }

    foreach($s->a_pool as $p) {
      if (isset($found_v[$p->name])) {
        continue;
      }
      $s->log("Removing pool $p", LLOG_INFO);
      $p->delete();
    }

    $lvs = $s->findBin('lvs');
    $cmd_lvs = "$sudo $lvs --noheadings --separator ';' %s";

    foreach($s->a_pool as $p) {

      $p->fetchJT('a_disk');
      $p->fetchRL('a_dataset');
  
      $cmd_l = sprintf($cmd_lvs, $p->name);
      $out_lvs = $s->exec($cmd_l);

      $lines = explode(PHP_EOL, $out_lvs);
      $found_v = array();
      $upd = false;
    
      foreach($lines as $line) {
        $line = trim($line);
        if (empty($line))
  	  continue;
      
        $f = preg_split("/;/", $line);
        $do = new Dataset();
	$do->fk_pool = $p->id;
	$do->name = $f[0];
	$upd = false;
	if ($do->fetchFromFields(array('fk_pool', 'name'))) {
          $do->insert();
	  $s->log("added dataset $do in $p", LLOG_INFO);
	  $p->a_dataset[] = $do;
	}
	$size = Pool::formatSize($f[3]);
	if ($size && $do->size != $size) {
	  $s->log("changed $do size => $size", LLOG_DEBUG);
	  $do->size = $size;
	  $upd = true;
	}
        if ($upd) $do->update();
	$found_v[$do->name] = $do;

      }
      foreach($p->a_dataset as $d) {
        if (isset($found_v[$d->name])) {
          continue;
        }
        $s->log("Removing dataset $d from pool $p", LLOG_INFO);
        $d->delete();
      }
    }

    return 0;
  }

  /**
   * CDP
   */
  public static function update_cdp(&$s) {

    $sudo = $s->findBin('sudo');
    $tcpdump = $s->findBin('tcpdump');
    $cmd_snoop = "$sudo $tcpdump -xx -c 1 -s1600 -n -i %s ether dst 01:00:0c:cc:cc:cc and greater 150";

    $s->fetchRL('a_net');

    foreach($s->a_net as $net) {
      if ($net->layer != 2) {
        continue;
      }
      if (!strncmp($net->ifname, 'lo', 2)) {
        continue;
      }
      if (!preg_match('/UP/i', $net->flags)) {
        continue;
      }
      $s->log("checking for CDP packet on $net", LLOG_INFO);
      try {
        $out_snoop = $s->exec($cmd_snoop, array($net->ifname), 100);
      } catch (Exception $e) {
        $s->log("Error checking CDP for $net: $e", LLOG_WARN);
        continue;
      }
      if (!empty($out_snoop)) {
        $cdpp = new CDPPacket('tcpdump', $out_snoop);
        $cdpp->treat();
        $ns = null;
        /* check switch */
        if (isset($cdpp->ent['deviceid']) && !empty($cdpp->ent['deviceid'])) {
          $ns = new NSwitch();
          $ns->did = $cdpp->ent['deviceid'];
          $upd = false;
          if ($ns->fetchFromField('did')) {
            $s->log("Added new switch $ns", LLOG_INFO);
            $ns->insert();
          }
          if (isset($cdpp->ent['sfversion']) &&
              !empty($cdpp->ent['sfversion']) &&
              strcmp($cdpp->ent['sfversion'], $ns->sfver)) {
            $upd = true;
            $ns->sfver = $cdpp->ent['sfversion'];
            $s->log("updated sfver of $ns", LLOG_DEBUG);
          }
          if (isset($cdpp->ent['platform']) &&
              !empty($cdpp->ent['platform']) &&
              strcmp($cdpp->ent['platform'], $ns->platform)) {
            $upd = true;
            $ns->platform = $cdpp->ent['platform'];
            $s->log("updated platform of $ns -> ".$ns->platform, LLOG_DEBUG);
          }
          if (isset($cdpp->ent['name']) &&
              !empty($cdpp->ent['name']) &&
              strcmp($cdpp->ent['name'], $ns->name)) {
            $upd = true;
            $ns->name = $cdpp->ent['name'];
            $s->log("updated name of $ns -> ".$ns->name, LLOG_DEBUG);
          }
          if (isset($cdpp->ent['location']) && 
              !empty($cdpp->ent['location']) &&
              strcmp($cdpp->ent['location'], $ns->location)) {
            $upd = true;
            $ns->location = $cdpp->ent['location'];
            $s->log("updated location of $ns -> ".$ns->location, LLOG_DEBUG);
          }
          if ($upd) $ns->update();
        }
        /* Check interface */
        if (isset($cdpp->ent['port']) && !empty($cdpp->ent['port'])) {
          if (!$ns) continue; // no switch...
          $ns->fetchRL('a_net');
          $sif = new Net();
          $sif->fk_switch = $ns->id;
          $sif->ifname = $cdpp->ent['port'];
          $upd = false;
          if ($sif->fetchFromFields(array('fk_switch', 'ifname'))) {
            $sif->insert();
            $s->log("added $sif to $ns", LLOG_INFO);
          }
          if ($sif->fk_net <= 0 || $sif->fk_net != $net->id) {
            $s->log("changed link for $ns/$sif => $net", LLOG_DEBUG);
            $sif->fk_net = $net->id;
            $net->fk_net = $sif->id;
            $upd = true;
          }
          if ($net->fk_net <= 0 || $net->fk_net != $sif->id) {
            $s->log("changed link for $net => $ns/$sif", LLOG_DEBUG);
            $sif->fk_net = $net->id;
            $net->fk_net = $sif->id;
            $upd = true;
          } 
          /**
           * @TODO: Add details to switch interfaces like mtu, duplex, link,vlan, etc..
           */
          if ($upd) {
            $sif->update();
            $net->update();
          }
        }
      }
    }

  }

  /* Screening */
  public static function htmlDump($s) {

    $version = $s->data('linux:version'); 
    $ver_name = $s->data('linux:ver_name');
    $version = "$version ($ver_name)";

    return array(
                'Distribution' => $s->data('linux:name'),
                'Version' => $version,
		'Kernel' => $s->data('os:kernel'),
           );
  }

  public static function dump($s) {

    $distro = $s->data('linux:name');
    $version = $s->data('linux:version');
    $ver_name = $s->data('linux:ver_name');
    $ker_ver = $s->data('os:kernel');
    if (empty($distro)) $distro = null;
    if (empty($version)) $version = null;
    if (empty($ver_name)) $ver_name = null;
    if (empty($ker_ver)) $ker_ver = null;
    $txt = '';
    $txt .= $s->o_os->name.' ';
    $txt .= ($ker_ver)?('- '.$ker_ver.' '):'';
    $txt .= ($distro)?('/ '.$distro.' '):'';
    $txt .= ($version)?('/ '.$version.' '):'';
    $txt .= ($ver_name)?('( '.$ver_name.') '):'';

    $s->log(sprintf("%15s: %s", 'OS', $txt), LLOG_INFO);
  }

}

?>
