<?php
/*
===============
= siteXplorer =
===============
copyright 2008 by Sebastian Weber <webersebastian@yahoo.de>
This software is licensed under the GNU general public license http://www.gnu.org/copyleft/gpl.html
http://sitexplorer.sourceforge.net

RELEASE NOTES:
v1.0
	Initial Release

v1.1
	Most Javascript moved into a static file
	Filmstrip view & Zoom - already viewed images are cached for performance
	Zoom available for all views
	Prefs: New Button to clear thumbnail cache and all stored settings
	Small changes:
		Toolbar button onmouseup works now
		Files without "." should be Type "File"
		Command textbox more pretty

TODOs
	Store settings per direcory (view,order)
	Framework support
		php functions with prefix
		javascript functions with prefix
	more views (tiles, list)
	search (needs own page for results etc.)
*/
	// autoconfig
	
error_reporting(E_ALL^E_NOTICE);
date_default_timezone_set('PRC');


	session_start() ;
  header('Content-Type:text/html;charset=utf-8');
	define( '_VALID_SXR', 1 );
	if (file_exists("cfg.php")) {
		include "cfg.php";
	} else {
		if ($_REQUEST['action']!="saveprefs") $firststart=true;
		include "cfg-dist.php";
	}
	include "authn.php";
	include "lib.php";
	
	if ($_REQUEST['action']=="saveprefs" && sxr_chkdemo("Modify Preferences")) {
		if (!$_REQUEST['cfg']['user']) $_REQUEST['cfg']['pass']=$cfg['pass']=""; // clear password if uname is empty
		
		if ($_REQUEST['cfg']['pass'] || !$cfg['pass']) { // set new password
			//setcookie("login_password", $_REQUEST['cfg']['pass']);
			$_REQUEST['cfg']['pass']=md5($_REQUEST['cfg']['pass']);
		} else $_REQUEST['cfg']['pass']=$cfg['pass'];
		
		 
		
		$f=fopen("cfg.php","w");
		fwrite($f,"<?php\n");
		foreach ($_REQUEST['cfg'] as $k => $v) fwrite($f,"\$cfg[\"$k\"]=\"$v\";\n");
		foreach ($_REQUEST['key'] as $k => $v) fwrite($f,"\$key[\"$k\"]=\"$v\";\n");
		fwrite($f,"?".">\n");
		fclose($f);
		$_REQUEST['action']=="";
		unset ($cfg,$key);
		include "cfg.php";
		$msg='System Settings Saved!';
	//	setcookie("login_username", $cfg['user']);
	}
	if ($_REQUEST['action']=="clearcache") {
		sxr_delete("thumbs");
		echo "OK";
		exit;
	}
	# functions
	function sxr_chkdemo($what) {
		global $demo_mode,$errmsg;
		if ($demo_mode) $errmsg="$what: Action prohibted in demo mode";
		return !$demo_mode;
	}
	
	function sxr_myerror($errno, $errstr, $errfile, $errline) {
		global $lasterr;
		switch ($errno) {
		case E_ERROR:
			echo "ERROR in line $errline of file $errfile: [$errno] $errstr\n";
			exit;
		case E_WARNING:
		//	echo "WARNING in line $errline of file $errfile: [$errno] $errstr<br>\n";
			$lasterr=$errstr;
		default:
		//	echo "NOTICE in line $errline of file $errfile: [$errno] $errstr<br>\n";
		}
	}	
	function sxr_delete($file) {
		if (file_exists($file)) {
			chmod($file,0777);
			if (is_dir($file)) {
				$handle = opendir($file);
				if (!$handle) return "Cannot open folder $file: $lasterr";
				while($filename = readdir($handle)) {
					if ($filename != "." && $filename != "..") {
						if ($msg=sxr_delete($file."/".$filename)) return $msg;
					}
				}
				closedir($handle);
				if (!rmdir($file)) return "Cannot delete folder $file: $lasterr";
			} else {
				if (!unlink($file)) return "Cannot delete file $file: $lasterr";
			}
		}
		return "";
	}
	function sxr_tnurl($f) {
		global $cfg,$cwd;
		$tp = "$cwd/thumbs$f";
		if ($cfg["enablecache"] && file_exists($tp) && filemtime($tp)==filemtime("$cfg[root_path]/$f"))
			return "thumbs".urlenc($f);
		return "tn.php?p=".urlenc($f);
	}
	function sxr_cleancache() { // clean cache all 10 minutes
		global $cfg,$curdir,$cwd;
		if (!file_exists("$cwd/thumbs$curdir")) return;
		if (!file_exists("$cwd/thumbs$curdir/.cacheage")) $fp=fopen("$cwd/thumbs$curdir/.cacheage", "w+");
		else $fp = fopen("$cwd/thumbs$curdir/.cacheage", "r+");
		flock($fp, LOCK_SH);
		$t=fread($fp,256);
		if ($t && $t+600>time()) {fclose($fp);return;}
		flock($fp, LOCK_EX);fseek($fp,0);fwrite($fp,time());fclose($fp);
		$fp = opendir("$cwd/thumbs$curdir");
		while(false !== ($file = readdir($fp))){
			if ($file==".." || $file=="." || $file==".cacheage" ||
				(is_dir("$cwd/thumbs$curdir/$file") && is_dir($file)) ||
				(file_exists($file) && filemtime($file)==filemtime("$cwd/thumbs$curdir/$file"))) continue;
			sxr_delete("$cwd/thumbs$curdir/$file");
		}
	}

	function sxr_xcopy($source, $dest, $move) {
		if (!file_exists($source)) return "";
		if ($source==$dest)	{
			if ($move) return "";
			$di=pathinfo($dest);$i=0;
			while (file_exists($dest))
				$dest=($di['dirname']?"$di[dirname]/":"")."Copy ".(++$i>1?"($i) ":"")."of $di[basename]";
		}
		if ($move) {
			if (file_exists($dest)) unlink($dest);
			if (rename($source,$dest)) return "";
			else return "Cannot move $source to $dest: $lasterr";}
		if (!is_dir($source)) {
			if (copy($source,$dest) || filesize($source)==0) return "";
			else return "Cannot copy $source to $dest: $lasterr";}
		if (substr($dest,0,strlen($source)+1)==$source."/") return "Cannot copy $source: The destination folder is the same as the source folder";
		if (!file_exists($dest) && !mkdir($dest)) return "Cannot create folder $dest: $lasterr";
		if (!($d = opendir($source))) return "Cannot open folder $source: $lasterr";
		while(false !== ($f = readdir($d))){
			if ($f=="." || $f=="..") continue;
			if($msg=sxr_xcopy("$source/$f","$dest/$f",$move)) return $msg;
		}
		closedir($d);
		return "";
	}
	function sxr_execprog($s) {
		$a=array();
		exec ($s." 2>&1",$a,$i);
		$out=join("\n",$a);
		$trans = get_html_translation_table(HTML_ENTITIES);
		$trans{"\n"}="<br>";
		$trans{"\r"}="";
		$out = strtr($out, $trans);
		return array($i,$out);
	}
	function sxr_zip($zipfile,$files) {
		global $cfg,$zipcom,$curdir,$errmsg;
		if ($cfg["zmethod"]==2) {
			$f=""; foreach($files as $fi) $f.=escapeshellarg($fi)." ";
			$c=preg_replace("/@FILES@/",$f,preg_replace("/@ZIPFILE@/",escapeshellarg($zipfile),$zipcom));
			$a=sxr_execprog($c);
			if ($a[0]) $errmsg.="Zipping files failed:\n$cfg[root_path]$curdir> $c\n$a[1]\n$cfg[root_path]$curdir><br>";
		} elseif ($cfg["zmethod"]==1) {
			require_once('pclzip.lib.php');
			$z = new PclZip($zipfile);
			if ($z->create($files)==0) $errmsg="Zipping files failed: ".$z->errorInfo(true);
		} else $errmsg.="Zipping files failed: no method to zip files!<br>";
		if ($errmsg) return false;
		return true;
	}

	function sxr_unzip($src_file,$out_path) {
		global $unzipcom,$cfg,$curdir;
		if ($cfg["uzmethod"]==2) {
			$c=preg_replace("/@ZIPFILE@/",escapeshellarg($src_file),$unzipcom);
			$a=sxr_execprog($c);
			return $a[0]?"Unzip failed:<br>$cfg[root_path]$curdir> $c<br>$a[1]<br>$cfg[root_path]$curdir>":"";
		} 
		elseif ($cfg["uzmethod"]==1) {
			require_once('pclzip.lib.php');
		  $out_path =str_replace('//','/',$out_path);		  
			$z = new PclZip($src_file);
			return $z->extract($out_path)==0?"Unzipping files failed: ".$z->errorInfo(true):"";
		} else return "Unzipping files failed: no method to unzip files!";
	}

	function sxr_format_fsize($i) {
		$x=0;
		$a=array("bytes","KB","MB","GB","TB","PB");
		$i=round($i);
		while ($i>1000) {$i/=1024;$x++;}
		$i=round($i,min(2,2-floor(log($i,10))));
		return "$i $a[$x]";	
	}
	function sxr_parseperms($p) {
		if (($p & 0xC000) == 0xC000) $i = 's';		// Socket
		elseif (($p & 0xA000) == 0xA000) $i = 'l';	// Symbolic Link
		elseif (($p & 0x8000) == 0x8000) $i = '-';	// Regular
		elseif (($p & 0x6000) == 0x6000) $i = 'b';	// Block special
		elseif (($p & 0x4000) == 0x4000) $i = 'd';	// Directory
		elseif (($p & 0x2000) == 0x2000) $i = 'c';	// Character special
		elseif (($p & 0x1000) == 0x1000) $i = 'p';	// FIFO pipe
		else $i = 'u';	// Unknown
		// Owner
		$i .= (($p & 0x0100) ? 'r' : '-');
		$i .= (($p & 0x0080) ? 'w' : '-');
		$i .= (($p & 0x0040) ? (($p & 0x0800) ? 's' : 'x' ) : (($p & 0x0800) ? 'S' : '-'));
		// Group
		$i .= (($p & 0x0020) ? 'r' : '-');
		$i .= (($p & 0x0010) ? 'w' : '-');
		$i .= (($p & 0x0008) ? (($p & 0x0400) ? 's' : 'x' ) : (($p & 0x0400) ? 'S' : '-'));
		// World
		$i .= (($p & 0x0004) ? 'r' : '-');
		$i .= (($p & 0x0002) ? 'w' : '-');
		$i .= (($p & 0x0001) ? (($p & 0x0200) ? 't' : 'x' ) : (($p & 0x0200) ? 'T' : '-'));
		return $i;
	}
	
	#####################################
	# Start of script
	#
	session_start();	// need a session to store the clipboard - cookies are not large enough
	set_error_handler ( sxr_myerror );

	// set my global variables
	$view=($_POST['view']?$_POST['view']:($_GET['view']?$_GET['view']:($_COOKIE['view']?$_COOKIE['view']:"d")));
	$curdir=shortenpath(isset($_POST['curdir'])?$_POST['curdir']:(isset($_GET['curdir'])?$_GET['curdir']:(isset($_COOKIE['curdir'])?$_COOKIE['curdir']:"")));
	$order=($_POST['order']?$_POST['order']:($_GET['order']?$_GET['order']:($_COOKIE['order']?$_COOKIE['order']:"nasata")));
	$action=$_REQUEST['action'];
	$arg=$_REQUEST['arg'];
	$curpos=$_REQUEST['curpos'];
	$curitem=$_REQUEST['curitem'];
	$cwd=getcwd();
	if ($cfg["root_path"]=="/" && $curdir) $cfg["root_path"]="";

	// store curdir, view & order in a cookie
	if ($view!=$_COOKIE["view"]) setcookie("view",$view,time()+51840000);
	if ($order!=$_COOKIE["order"]) setcookie("order",$order,time()+51840000);
	if ($curdir!=$_COOKIE["curdir"]) setcookie("curdir",$curdir,time()+51840000);

	chdir($cfg["root_path"]);
	if (!chdir(".".$curdir)) $curdir="";
	if (!isset($_SESSION['cb_action'])) $_SESSION['cb_action'] = 0;
	if ($_REQUEST['cb_action']) {
		$_SESSION['cb_action'] = $_REQUEST['cb_action'];
		$_SESSION['cb_path'] = $_REQUEST['cb_path'];
		$_SESSION['cb_files'] = $_REQUEST['cb_files'];
	}
	$cb_action=$_SESSION['cb_action'];
	$cb_path=$_SESSION['cb_path'];
	$cb_files=$_SESSION['cb_files'];
	
	if ($cfg["enablecache"]) sxr_cleancache();
	
	$a=array();
	$m="'^(".join("|",$image_ext[$cfg["tnmethod"]]).")$'";
	foreach ($mimes as $k => $v) if (preg_match($m,$v)) $a[]=$k;
	$img_can_rotate='\.'.join('$|\.',$a).'$';	// image formats: imglib_can_read() AND imglib_can_write()

	foreach ($browserimg as $k) $a=array_merge($a,array_keys($mimes,$k));
	$img_can_show='\.'.join('$|\.',array_unique($a)).'$';	// image formats: imglib_can_read() OR browser_can_read()

	###########
	# ACTIONS
	if ($action=="mkdir" && $arg && sxr_chkdemo("New Folder")) {
		if (!mkdir($arg)) $errmsg="Cannot create folder $arg: $lasterr";
		else $curitem=$arg;
	}
	if ($action=="delete" && $arg && sxr_chkdemo("Delete Files"))
	 foreach (explode(':',$arg) as $what) 
	 {  if (!preg_match("/^\\.\\.?$/",$what) && ($errmsg=sxr_delete($what)))	
	 	    break;
	 	  else
	 	     $msg='Delete done!';
	 } 
	if ($action=="command" && $arg && sxr_chkdemo("Execute command")) {
		$a=sxr_execprog($arg);
		if ($a[0]) $errmsg="<b>Command could not be executed:</b><br>$cfg[root_path]$curdir> $arg<br>$a[1]<br>$cfg[root_path]$curdir>";
		else $msg="$cfg[root_path]$curdir> $arg<br>$a[1]<br>$cfg[root_path]$curdir>";
	}
	if ($action=="paste" && $_SESSION['cb_files'] && sxr_chkdemo("Paste Files")) {
		foreach (explode(':',$_SESSION['cb_files']) as $f)
			if ($errmsg=sxr_xcopy("$cfg[root_path]$_SESSION[cb_path]/$f","$cfg[root_path]$curdir/$f",$cb_action==2?1:0)) break;
		if ($cb_action==2) {$cb_action=0;$cb_path=$cb_files="";}
		$msg = $errmsg=='' ? 'Paste done!' : ''; 
	}
	if ($action=="rename" && sxr_chkdemo("Rename File")) {
		$a=explode(":",$arg);
		if (!rename($a[0],$a[1])) $errmsg="Cannot rename $a[0]: $lasterr";
		else $msg = "Rename done!"; 
		$curitem=$a[1];
	}
	if ($action=="edit" && sxr_chkdemo("Edit File")) {
		$a=explode(":",$arg);
		//if (!rename($a[0],$a[1])) $errmsg="Cannot rename $a[0]: $lasterr";
		//echo "<h1>edit " .$a[0] ."</h1>";
		$file2edit=$a[0];
	}
	if ($action=="savefile")
	{ 
		$file2edit = $_REQUEST['fname'];		
		$filepath=$cfg[root_path] .$curdir. '/' . $_REQUEST['fname'];
		$filepath= str_replace("//","/",$filepath);		 
		$Xcontent= $_REQUEST['code'];
		
		file_put_contents($filepath,html_entity_decode($Xcontent));
		//echo 		 
		$msg = $_REQUEST['fname'] .": Saved!" ;
		 
	}
	if ($action=="savefileonly")
	{
		$filepath=$cfg[root_path] .$curdir. '/' . $_REQUEST['fname'];
		$filepath= str_replace("//","/",$filepath);
		
		//$Xcontent= iconv('utf-8','gb2312',$_REQUEST['code']);
		$Xcontent= $_REQUEST['code'];
		file_put_contents($filepath,html_entity_decode($Xcontent));
		echo "File  '". $_REQUEST['fname'] ."'   Saved!" ;
		exit();
  }
	if ($action=="newfile" && sxr_chkdemo("New File")) {
		$a=explode(":",$arg);
		//if (!rename($a[0],$a[1])) $errmsg="Cannot rename $a[0]: $lasterr";
		//echo "<h1>newfile " .$a[0] ."</h1>";
		$file2edit=$a[0];
	}
	if ($action=="upload" && sxr_chkdemo("Upload Files")) {
		$count=1;$errmsg="";
		foreach ($_FILES as $n => $f) {
			if (!$f['name']) continue;
			$n=preg_replace("/^file/","",$n);
			if (preg_match("/\\.zip$/",$f['name']) && $_REQUEST["uz$n"]) $errmsg=sxr_unzip($f['tmp_name']);
			else {	// copy
				if (file_exists($f['name']) && $_REQUEST["ow"] && !unlink($f['name'])) $errmsg.="Cannot delete $f[name]: $lasterr<br>";
				else if (!rename($f['tmp_name'],$f['name'])) $errmsg.="Cannot store $f[name]: $lasterr<br>";
			}
		}	
		$msg = $errmsg ? "" : "Upload OK!"; 
	}
	if ($action=="download" && $arg) {
		$b=explode(":",$arg);
		// zip content
		if (!($zipfile=tempnam("/tmp","SX"))) $zipfile=($_ENV['TMP']?$_ENV['TMP']:$_ENV['TEMP']?$_ENV['TEMP']:$cfg["root_path"])."/__".md5(time().$_SERVER["REQUEST_URI"]).".zip";
		unlink($zipfile);
		if (sxr_zip($zipfile,$b)) {
			// calculate zip filename
			if (count($b)==1) $fn=preg_replace("/\\.[^\\.]*\$/","",$b[0]);
			else $fn=$curdir?preg_replace('|^.*/([^/]+)$|',"$1",$curdir):"root";
			header("Content-type: application/zip");
			header("Content-disposition: attachment; filename=\"$fn.zip\"");
			readfile($zipfile);
			unlink($zipfile);
			exit;
		}
	}
	if ($action=="zip" && $arg) {
		$b=explode(":",$arg);
		// zip content 
		
		$zipfile = $cfg[root_path] .$curdir."/" .$_REQUEST['zip_filename'].  '.zip' ;
		$zipfile = str_replace("//","/",$zipfile);		 
		  
		if (sxr_zip($zipfile,$b)) {			
			// calculate zip filename
		 	if (count($b)==1) $fn=preg_replace("/\\.[^\\.]*\$/","",$b[0]);
		 	else $fn=$curdir?preg_replace('|^.*/([^/]+)$|',"$1",$curdir):"root";
		 	$msg = "Zip: " . $_REQUEST['zip_filename'] . ".zip  ";		 
		}
		else
		  $errmsg = "Zip file failed!"; 
	}
	if ($action=="chmod" && $arg && sxr_chkdemo("Change File Permissions")) {
		$b=explode(":",$arg);
		$m=octdec("0".array_shift($b));
		foreach($b as $f) if (!preg_match("/^\\.\\.?$/",$f) && !chmod($f,$m)) $errmsg.="Cannot chmod $f: $lasterr<br>";
		$curitem=$b[0];
		$msg = $errmsg ? "" : "Chmod ". $m ." Done!"; 
	}
	if ($action=="extract" && $arg && sxr_chkdemo("Extract Archive")) 
	{ $zip_filename = $_REQUEST['zip_filename'];
		$errmsg=sxr_unzip("$cfg[root_path]$curdir/$zip_filename", "$cfg[root_path]$arg"   );
	  $msg = $errmsg ? "" : "Extract Done!"; 
	}
	if ($action=="phpinfo")
	{
	 phpinfo();
	 exit();	
	}
	
	
	if (preg_match("/^rotate(.*)\$/",$action,$m) && $arg && sxr_chkdemo("Rotate Image")) {
		$mi=file2mime($arg);
		if ($cfg["jpegrot"]==2 && $mi=="image/jpeg") {
			sxr_execprog("jpegtran -rotate ".($m[1]?90:270)." \"$arg\" >\"$arg.__rot__\"");
			unlink($arg);
			rename("$arg.__rot__",$arg);
		} elseif (!preg_match("'^(".join("|",$image_ext[$cfg["tnmethod"]]).")$'",$mi)) {$errmsg.="Image type $mi not supported by image processing library<br>";
		} else {
			if ($cfg["tnmethod"]==1) {
				sxr_execprog("mogrify -rotate ".($m[1]?90:270)." \"$arg\"");}
			elseif ($cfg["tnmethod"]==2) {
				sxr_execprog($pbm_commands[$mi][0]." \"$arg\" | pnmrotate ".($m[1]?90:270)." | ".$pbm_commands[$mi][1]." >\"$arg.__rot__\"");
				unlink($arg);
				rename("$arg.__rot__",$arg);
			}
			elseif ($cfg["tnmethod"]==3) {
				if (!$img=imagecreatefromstring(file_get_contents("$cfg[root_path]$curdir/$arg"))) $errmsg.="Cannot read image<br>";
				else {
					$img=imagerotate($img, $m[1]?270:90, 0);
					$ext=preg_replace('|^.*[/\.]|',"",$mi);
					eval("image$ext(\$img,\"$arg\");");
				}
			}
		}
		$curitem=$arg;
	}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo $curdir?$curdir:"/" ?> - SiteXplorer</title>	
<link rel="shortcut icon" href="img/favicon.ico" type="image/x-icon" />
<link type="text/css" rel="StyleSheet" href="style.css.php">
<script type="text/javascript">
	// some global variables which are configurable
	var curdir="<?php echo preg_replace("/\"/","\\\"",$curdir);?>";
	var cb_path="<?php echo $cb_path?>";// clipboard dir
	var cb_files="<?php echo $cb_files?>";// clipboard files
	var cb_action=<?php echo $cb_action?>;// clipboard action (2=cut,1=copy)
	var view="<?php echo $view?>"; // whats out current view? d=details, i=icons, t=thumnails, s=filmstrip
	var imgCanShowRe=/<?php echo $img_can_show;?>/i; // regular expression to check files if they can be shown
	var imgCanRotateRe=/<?php echo $img_can_rotate;?>/i; // regular expression to check files if they can be shown
	var curdirenc="<?php echo urlenc(shortenpath($curdir))?>"; // escaped and shortened current path
	var cpos=<?php echo $curpos>0?$curpos+0:1;?>; // predefined cursor position - numeric
	var curitem="<?php echo $curitem?>"; // predefined cursor position - name (name overrides number)
	var firststart=<?php echo $firststart?1:0?>; // are we starting up for the first time?
	var order="<?php echo $order?>"; // what are we sorting for?
	// whats the name for each keycode? code -> name
	var ks=new Array();<?php foreach ($ks as $k => $v) echo "ks[$v]=\"$k\";";?>

	// my configured keys
	var k_action=new Array();k_code=new Array();k_ctrl=new Array();k_alt=new Array();k_shift=new Array();
<?php 
	$i=0;
	foreach ($key as $ac => $k) {
		if (preg_match('/^(Ctrl-)?(Alt-)?(Shift-)?(.+)$/',$k,$m)) {
			echo "\tk_action[$i]=\"$ac\";".
				"k_code[$i]=".$ks[$m[4]].";".
				"k_ctrl[$i]=".($m[1]?"true":"false").";".
				"k_alt[$i]=".($m[2]?"true":"false").";".
				"k_shift[$i]=".($m[3]?"true":"false").";\n";
			$i++;
		}
	}
	?>
</script>
<script type="text/javascript" src="javascript.js"></script> 

	<LINK href="dialog/dialog.css" type=text/css rel=stylesheet> 
  <SCRIPT src="dialog/jquery.min.js" type=text/javascript></SCRIPT>
  <SCRIPT src="dialog/dialog.js" type=text/javascript></SCRIPT>	
<!--[if lte IE 8]>
<SCRIPT src="dialog/html5.js" type=text/javascript></SCRIPT>
<![endif]-->
 
	<script language=javascript>
	<?	 echo 'var last_errmsg ="' . $errmsg .'";' ;
	     echo 'var last_msg ="' . $msg . '";' ;	 
	?>
	function onloadshow()
	{ 
	  if (last_errmsg) shownError(last_errmsg );
	  if (last_msg) showNotify(last_msg );	  
	}
	</script>
	
</head>
<body id=body onLoad="onloadshow();">
 
  

	
	
<!-- Popup Windows and utility layers -->
<div id=back style='width:1px;height:1px'>&nbsp;</div>
<table id=f_zoom border=0 cellpadding=0 cellspacing=0><tr><td><img src=img/win_tl.gif></td><td style='background:#BDBDBD url(img/img_close.gif) no-repeat scroll top right;color:#4F4F4F;cursor:pointer' onclick="toggle_zoom()" align=left><img src=img/img_zoom16.gif style='margin-right:6px' align=left><div id=zoomtitle>SiteXplorer - Zoom</div></td><td><img src=img/win_tr.gif></td></tr><tr><td bgcolor=#BDBDBD></td><td style='border:0px solid red' id=zoomtd align=center valign=middle onclick="toggle_zoom()"><div id=zoomdiv>
<img id=zoomld src='img/spinner.gif' alt='Loading image'><img id=zoomimg src='' alt='No preview available'><div id=zoomno>No preview available.</div></div>
</td><td bgcolor=#BDBDBD></td></tr><tr><td><img src=img/win_bl.gif></td><td bgcolor=#BDBDBD></td><td><img src=img/win_br.gif></td></tr></table>
<!-- about -->
<table id=f_about border=0 cellpadding=0 cellspacing=0><tr><td><img src=img/win_tl.gif></td><td style='background:#BDBDBD url(img/img_close.gif) no-repeat scroll top right;color:#4F4F4F;cursor:pointer' onclick="ca_about()" align=left><img src=img/img_logo16.gif style='margin-right:6px' align=absmiddle>SiteXplorer - About</td><td><img src=img/win_tr.gif></td></tr><tr><td bgcolor=#BDBDBD></td><td style='border:1px solid white'>
<div id=about><img onClick="ca_about()" src=img/img_logo.gif>
<div>siteXplorer v1.2</div>
&copy;2008 by Sebastian Weber &lt;<a href="mailto:websersebastian@yahoo.de">webersebastian@yahoo.de</a>><br>
This software is licensed under the <a href="http://www.gnu.org/copyleft/gpl.html" target="_new">GNU general public license</a><br>
Website <a target="_new" href="http://sitexplorer.sourceforge.net">sitexplorer.sourceforge.net</a>
<br><a href=# onclick='ac_phpinfo();'>  PHP info </a>  &nbsp;&nbsp;&nbsp;&nbsp;Ding,chengliang<<a href="mailto:goodook@163.com">goodook@163.com</a>> last modified</div>
</td><td bgcolor=#BDBDBD></td></tr><tr><td><img src=img/win_bl.gif></td><td bgcolor=#BDBDBD></td><td><img src=img/win_br.gif></td></tr></table>
 
 <!-- config -->
<table id=f_prefs border=0 cellpadding=0 cellspacing=0 ><tr><td><img src=img/win_tl.gif></td><td style='background:#BDBDBD url(img/img_close.gif) no-repeat scroll top right;color:#4F4F4F;cursor:pointer' onclick="ca_prefs()" align=left><img src=img/img_prefs16.gif style='margin-right:6px' align=absmiddle>SiteXplorer - Preferences</td><td><img src=img/win_tr.gif></td></tr><tr><td bgcolor=#BDBDBD></td><td style='border:1px solid white'>
<div id=prefs><table border=0 cellpadding=0 cellspacing=0 width="100%" height="100%"><tr><td valign=middle align=center><form><img src="img/spinner.gif"><br><br><input type="button" value="Cancel" onClick="ca_prefs()"></form></td></tr></table></div>
</td><td bgcolor=#BDBDBD></td></tr><tr><td><img src=img/win_bl.gif></td><td bgcolor=#BDBDBD></td><td><img src=img/win_br.gif></td></tr></table>	
<!-- upload -->
<form name=u method=post enctype="multipart/form-data" action=index.php>
<table id=f_upload border=0 cellpadding=0 cellspacing=0><tr><td><img src=img/win_tl.gif></td><td style='background:#BDBDBD url(img/img_close.gif) no-repeat scroll top right;color:#4F4F4F;cursor:pointer' onclick="ca_upload()" align=left><img src=img/img_upload16.gif style='margin-right:6px' align=absmiddle>SiteXplorer - Upload Files</td><td><img src=img/win_tr.gif></td></tr><tr><td bgcolor=#BDBDBD></td><td style='border:1px solid white'>
<div id=upload>
<div id=upspin><table border=0 cellpadding=0 cellspacing=0 width="100%" height="100%"><tr><td valign=middle align=center><img src=img/spinner.gif align=middle></td></tr></table></div>
<input type=hidden name=curdir value="<?php echo htmlspecialchars($curdir);?>">
<input type=hidden name=order value="<?php echo $order;?>">
<input type=hidden name=action value=upload>
<input type=hidden name=view value="<?php echo $view ?>">
<a href="#" onmouseup="addUpload()"><img src="img/img_addfile.gif"> add file ...</a><br>
<div id=ulfiles><div class=ulfile><input type=file name=file1 size=50 onchange="this.nextSibling.style.display=this.value.match(/\.zip$/)?'inline':'none'"><span><input type=checkbox name=uz1 value=1>Unzip File</span></div></div>
<input type=checkbox name=ow value=1 checked> Overwrite existing files<br> 
<div align=right><input type=button class=but value=Upload onmouseup="do_upload()"> <input class=but type=button value=Cancel onmouseup="ca_upload()"></div>
</div>
</td><td bgcolor=#BDBDBD></td></tr><tr><td><img src=img/win_bl.gif></td><td bgcolor=#BDBDBD></td><td><img src=img/win_br.gif></td></tr></table>	
</form>

 

<form method=get name=f action=index.php>
<input type=hidden name=curpos value="">
<input type=hidden name=curdir value="<?php echo htmlspecialchars($curdir);?>">
<input type=hidden name=order value="<?php echo $order;?>">
<input type=hidden name=action value="">
<input name=arg value="" style="display:none;position:absolute;z-index:999" onblur="ca_rename()">
<input type=hidden name=cb_action value="">
<input type=hidden name=cb_path value="">
<input type=hidden name=cb_files value="">
<input type=hidden name=zip_filename value="">
<!-- Toolbar -->
<table id=head>

<tr><td colspan=2 class=toolbar><table width=100% cellpadding=0 cellspacing=0 border=0><tr><td><img class=tbbut
src="img/tool_dirup<?php echo $curdir?"":"_bw"?>.gif" title="Up<?php echo $key["dirup"]?" ($key[dirup])":"";?>"<?php if ($curdir) 
	echo " onclick=\"goTo(&quot;".htmlspecialchars(shortenpath($curdir."/.."))."&quot;)\""?>><img
src="img/tool_sep.gif"><img class=tbbut
src="img/tool_upload.gif" title="Upload Files<?php echo $key["upload"]?" ($key[upload])":"";?>" id="icul"
	onclick="ac_upload()"><img class=tbbut
src="img/tool_download_bw.gif" title="Download Files as ZIP<?php echo $key["download"]?" ($key[download])":"";?>" id="icdl"
	onclick="ac_download()"><img class=tbbut
src="img/tool_zip16.gif" title="Zip Files" id="iczip"
	onclick="ac_zip()"><img class=tbbut
src="img/tool_newfolder.gif" title="New Folder<?php echo $key["mkdir"]?" ($key[mkdir])":"";?>"
	onclick="ac_mkdir()"><img class=tbbut 
src="img/tool_newfile.gif" title="Create New file" id="icnewfile"   
onclick="ac_newfile()"><img class=tbbut
src="img/tool_extract_bw.gif" title="Extract Zipfile<?php echo $key["extract"]?" ($key[extract])":"";?>" id="icext"
	onclick="ac_extract()"><img
src="img/tool_sep.gif"><img class=tbbut
src="img/tool_selectall.gif" title="Select All<?php echo $key["selall"]?" ($key[selall])":"";?>" id="icsa"
	onclick="ac_sall()"><img class=tbbut
src="img/tool_invsel.gif" title="Invert Selection<?php echo $key["dselall"]?" ($key[dselall])":"";?>"
	onclick="ac_dsall()"><img class=tbbut
src="img/tool_rename.gif" title="Rename<?php echo $key["rename"]?" ($key[rename])":"";?>" id="icren"
	onclick="ac_rename()"><img class=tbbut
src="img/tool_chmod_bw.gif" title="Change Permissions<?php echo $key["perms"]?" ($key[perms])":"";?>" id="icchm"
	onclick="ac_chmod()"><img class=tbbut 
src="img/tool_edit.gif" title="Edit" id="icedit"   
onclick="ac_edit()"><img class=tbbut
src="img/tool_delete_bw.gif" title="Delete<?php echo $key["del"]?" ($key[del])":"";?>" id="icdel"
	onclick="ac_delete()"><img class=tbbut
src="img/tool_cut_bw.gif"  id="iccut" title="Cut<?php echo $key["cut"]?" ($key[cut])":"";?>"
	onclick="ac_copy(1)"><img class=tbbut
src="img/tool_copy_bw.gif"  id="iccpy" title="Copy<?php echo $key["copy"]?" ($key[copy])":"";?>"
	onclick="ac_copy(0)"><img class=tbbut
src="img/tool_paste<?php echo $_REQUEST['cb_files']?"":"_bw"?>.gif"  
id="icpst" title="Paste: &#10;---------<?php
 $b=explode(":",$cb_files); foreach ($b as $v) echo '&#10;'.$v;
?>" 	onclick="ac_paste()"><img
src="img/tool_sep.gif"><img
src="img/tool_view.gif" title="Views"><select name=view onChange="document.f.curpos.value=window.curpos;document.f.action.value='';subfrm()" title="Views">
<option value="s"<?php echo $view=="s"?" selected":"" ?>>Filmstrip</option>
<option value="t"<?php echo $view=="t"?" selected":"" ?>>Thumbnails </option>
<option value="i"<?php echo $view=="i"?" selected":"" ?>>Icons</option>
<option value="d"<?php echo $view=="d"?" selected":"" ?>>Details </option>
</select> <img class=tbbut
src="img/tool_run.gif" title="Run Command<?php echo $key["command"]?" ($key[command])":"";?>"
	onclick="ac_command()"> <input type="text" name="command" style="color:#AAAAAA" value="type command here"
	onfocus="if (this.value=='type command here') {this.value='';this.style.color='#000000';}"
	onblur="if (this.value=='') {this.style.color='#AAAAAA';this.value='type command here';}"><img
src="img/tool_sep.gif"><img class=tbbut
src="img/tool_prefs.gif"  id="icpre" title="Preferences<?php echo $key["prefs"]?" ($key[prefs])":"";?>"
	onclick="ac_prefs()">
</td><td><img class=tbbut src="img/tool_logout.gif" id="lclog" title="Logout<?php echo $key["logout"]?" ($key[logout])":"";?>" onclick="ac_logout()"></td></tr></table></td>
<td rowspan=2 valign=top id=logo><img src="img/img_logo.gif" alt="SiteXplorer - About" onClick="ac_about()"></td></tr>
<!-- Address Bar -->

<tr><td class=a1>Address</td><td class=a2><div class=a2 onclick="">
<?php
	echo "<img class=icon16 src=\"img/icon_".$folder_icon."16.gif\"> <a id=a href='#' onmouseup=\"goTo('')\">root</a>";
	$p="";
	foreach (explode('/',$curdir) as $i) {
		if ($i=="") continue;
		$p.="/$i";
		echo " / <a href='#' onmouseup=\"goTo(&quot;".htmlspecialchars($p)."&quot;)\">$i</a>";
	}
?></div>
</td></tr></table>
 
<?   
    if($action!='edit' && $action!='newfile' )
      require('filelist.php');
    else
      require('edit.htm');
?>
 


<script type="text/javascript">
init();
</script>
</body>
</html>
