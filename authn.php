<?php
defined( '_VALID_SXR' ) or die( 'Direct Access to this location is not allowed!' );
 
	
if ($cfg["user"] )
{	if ($_SESSION['PASSED_OK']=='' || !isset($_SESSION['PASSED_OK']))   
	{    
		//check login 
		$login_username=$_REQUEST['login_username'];
		$login_password=$_REQUEST['login_password'];
		
		if (isset($login_username)) {
			$me=$login_username;
			if (strcmp($login_username,$cfg["user"])!=0 || strcmp(md5($login_password),$cfg["pass"])!=0) {
				unset($me);
				$errmsg="Invalid User Name or Password ";
			}
			else
			{		
				 $_SESSION['PASSED_OK']='admin';	
				 
			   $me='admin';
			}
		}
	}
}
else
{
	$_SESSION['PASSED_OK']='admin';
	$me='admin';
}


if ($_REQUEST["action"]=="logout"  ) {
		// destroy the authentication cookie
		if (isset($_COOKIE["login_username"])) setcookie("login_username", '', time()-42000);
		if (isset($_COOKIE["login_password"])) setcookie("login_password", '', time()-42000);
		$_SESSION['PASSED_OK']='';
		session_unset($_SESSION['PASSED_OK']);
		unset($login_username);
		unset($login_password);
		unset($me);
		 
}

 

if ($_SESSION['PASSED_OK']=='' || !isset($_SESSION['PASSED_OK']))
{   unset($me);
	}
else
{  $me='admin';}
  
  
  
  
if (!$me) {
	 
	$_SESSION['PASSED_OK']="";
	// login screen
	?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
	<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>SiteXplorer Login</title>
	<link rel="shortcut icon" href="img/favicon.ico" type="image/x-icon" />
	<link type="text/css" rel="StyleSheet" href="style.css.php">
	<script type="text/javascript" src="javascript.js"></script>
	
	<LINK href="dialog/dialog.css" type=text/css rel=stylesheet> 
  <SCRIPT src="dialog/jquery.min.js" type=text/javascript></SCRIPT>
  <SCRIPT src="dialog/dialog.js" type=text/javascript></SCRIPT>	
	<script language=javascript>
	<?	 echo 'var last_errmsg ="' . $errmsg .'";' ;
	     echo 'var last_msg ="' . $msg . '";' ;	 
	?>
	function onloadshow()
	{ 
	  if (last_errmsg) shownError(last_errmsg );
	  if (last_msg) showNotify(last_msg );
	 //  document.getElementById('uname').focus();	  
	}
	</script>



</head>
<body onLoad="onloadshow();"  >
<br><center>
	
<table id=f_login border=0 cellpadding=0 cellspacing=0><tr><td><img src=img/win_tl.gif></td><td bgcolor=#BDBDBD style='color:#4F4F4F' align=left><img src=img/img_logo16.gif style='margin-right:6px' align=absmiddle>SiteXplorer - Login</td><td><img src=img/win_tr.gif></td></tr><tr><td bgcolor=#BDBDBD></td><td style='border:1px solid white'>
	<div id=login style="width:320px">
	<table style="width:320px;height:242px;background:#ECE9D8;" cellpadding=0 cellspacing=0 border=0><tr><td><img src="img/authn.jpg"></td></tr><tr><td align=left>
	<form action="index.php" method="POST" name="login">
	<input type="hidden" name="action" value="login">
	<table border=0 cellpadding=2 cellspacing=0 style="margin:10px;height:162px;width:300px;">
	<tr><td colspan=2>Welcome to siteXplorer! Please log in.<br>&nbsp;</td></tr>
	<tr><td>User name:</td><td align=center valign=middle><input id=uname type=text name="login_username" style="width:148px;height:15px;border:1px solid #7F9DB9;background:#ffffff url(img/user.gif) no-repeat 2px 2px;padding-left:22px;padding-top:3px"></td></tr>
	<tr><td>Password:</td><td align=center><input type=password name="login_password" style="width:165px;height:15px;border:1px solid #7F9DB9;padding-top:3px;padding-left:5px;"></td></tr>
	<tr><td colspan=2><input type="checkbox" name="staylogged" value="yes"> Stay logged in</td></tr>
	<tr><td colspan=2 align=right valign=bottom><input type=submit value="OK" class=but></td></tr>
	</table></form></td></tr></table></div>
</td><td bgcolor=#BDBDBD></td></tr><tr><td><img src=img/win_bl.gif></td><td bgcolor=#BDBDBD></td><td><img src=img/win_br.gif></td></tr></table>	
	</center>
	</body>
	</html>
<?php
	 
	exit();

} 


 
	

?>