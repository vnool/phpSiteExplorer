<!-- File listing ---- vvvvvv -->
<table id="main"><tr><td>
<?php
	$files=array();
	$fd=$fn=$ft=$fs=$fm=array();
	$count=0;
	$tsize=0;
	$dir=array();
	if($handle = opendir(".")) while(false !== ($file = readdir($handle))) $dir[]=$file;
	foreach ($dir as $file) {
		if (is_dir($file)) {
			if ($file==".") continue;
			if ($file==".." && $curdir=="" && count($dir)>2) continue;
			$fd[]=$files[$count]['d']=1;
			$ft[]=$files[$count]['t']="File Folder";
			$files[$count]['i']=$folder_icon;
			$fs[]='';
			//$fs[]=$files[$count]['s']='';
		} else {
			$fd[]=$files[$count]['d']=0;
			$m=$files[$count]['mi']=file2mime($file);
			foreach ($icons as $k => $v) if (preg_match("'^".$k."$'i",$m)) break;
			if ($v[1]) $ft[]=($files[$count]['t']=$v[1])."_".strtoupper(preg_replace("/^.*\./","",$file));
			else $ft[]=$files[$count]['t']=strstr($file,".")===false?"File":(strtoupper(preg_replace("/^.*\./","",$file))." File");
			$files[$count]['i']=$v[0];
			$fs[]=$files[$count]['s']=filesize($file);
			$tsize+=$files[$count]['s'];
		}
		$fa[]=$files[$count]['p']=fileperms($file);
		$fn[]=strtolower($file);$files[$count]['n']=$file;
		$fm[]=$files[$count]['m']=$file==".."?"":date("Y-m-d H:i",filemtime($file));
		$count++;
	}
	closedir($handle);
	# sort
	$s1=substr($order,0,1);$s2=substr($order,2,1);$s3=substr($order,4,1);
	array_multisort(
		$fd,
		substr($order,1,1)=='a'?SORT_DESC:SORT_ASC,
		$s1=='n'?$fn:($s1=='s'?$fs:($s1=='t'?$ft:$fm)),
		substr($order,1,1)=='a'?SORT_ASC:SORT_DESC,
		$s2=='n'?$fn:($s2=='s'?$fs:($s2=='t'?$ft:$fm)),
		substr($order,3,1)=='a'?SORT_ASC:SORT_DESC,
		$s3=='n'?$fn:($s3=='s'?$fs:($s3=='t'?$ft:$fm)),
		substr($order,5,1)=='a'?SORT_ASC:SORT_DESC,
		$files);

	$cutfiles=($cb_action==2 && $cb_path==$curdir && $cb_files)?explode(':',$cb_files):array();
	if ($view=="d")	printdetail($files);
	elseif ($view=="s")	printfilm($files);
	elseif ($view=="t")	printthumb($files);
	elseif ($view=="i")	printicon($files);

	function printfilm($f) {
		global $curdir,$cfg,$cutfiles,$image_ext,$key;
		
		echo "<table id=film><tr><td id=filmprevtd valign=middle align=center onClick='toggle_zoom()'><div id=fpdiv><img id=filmld src='img/spinner.gif' alt='Loading image'><img id=filmimg src='' alt='No preview available'><div id=filmno>No preview available.</div></div></td></tr>";
		echo "<tr><td id=filmnav><img class=fibut src='img/tool_prev.gif' title='Previous Image (Left Arrow)' onclick='doSelection(curpos-1<1?items.length:curpos-1,0,0,0);update_tb();'><img class=fibut src='img/tool_next.gif' title='Next Image (Right Arrow)' onclick='doSelection(curpos+1>items.length?1:curpos+1,0,0,0);update_tb();'> <img src='img/tool_sep.gif' height=19 width=1> <img class=fibut id='icrtr' src='img/tool_rrotate_bw.gif' title='Rotate Clockwise ($key[rrot])' onclick='ac_imgrotate(1)'><img class=fibut id='icrtl' src='img/tool_lrotate_bw.gif' title='Rotate Counterclockwise ($key[lrot])' onclick='ac_imgrotate(0)'></td></tr>";
		echo "<tr><td id=filmtd><div id=filmdiv><table id=filmtable><tr>";
		for($i=0;$i<count($f);$i++) {
			$img=htmlspecialchars($f[$i]['d'] || !preg_match("'^".join("|",$image_ext[$cfg["tnmethod"]])."$'",$f[$i]['mi'])?
				"img/icon_".$f[$i]['i']."48.gif":sxr_tnurl("$curdir/".$f[$i]['n']));
			$url=$f[$i]['d']?"goTo(&quot;".htmlspecialchars(shortenpath("$curdir/".$f[$i]['n']))."&quot;)":
				"location.href=&quot;dl.php?p=".urlenc(shortenpath("$curdir/".$f[$i]['n']))."&quot;";
			echo "<td class=fi1><div p=\"".$f[$i]['p']."\" title=\"".htmlspecialchars($f[$i]['n'])."\" class=tn onDblClick=\"$url\"><table class=t1><tr><td><img src=\"$img\"".(in_array($f[$i]['n'],$cutfiles)?" class=cut":"")."></td></tr></table><div class=t2>".htmlspecialchars($f[$i]['n'])."</div></div></td>\n";
		}
		echo "</tr></table></div></td></tr></table>";
	}

	function printdetail($f) {
		global $curdir,$cfg,$cutfiles,$order;
		$o1=substr($order,0,1);$o2=substr($order,1,1);
		echo "<table class=det><tr id=dettr><th width=100%>Name ".($o1=="n"?"<img src='img/img_arrow".($o2=="a"?"up":"down").".gif'>":"").
			"</th><th></th><th align=right>Size ".($o1=="s"?"<img src='img/img_arrow".($o2=="a"?"up":"down").".gif'>":"").
			"</th><th>Type ".($o1=="t"?"<img src='img/img_arrow".($o2=="a"?"up":"down").".gif'>":"").
			"</th><th>Date Modified ".($o1=="d"?"<img src='img/img_arrow".($o2=="a"?"up":"down").".gif'>":"").
			"</th><th align=right class=nosort>Permissions</th></tr>\n";
		for($i=0;$i<count($f);$i++) {
			$url=$f[$i]['d']?"goTo(&quot;".htmlspecialchars(shortenpath("$curdir/".$f[$i]['n']))."&quot;)":
				"location.href=&quot;dl.php?p=".urlenc(shortenpath("$curdir/".$f[$i]['n']))."&quot;";
			echo "<tr>
				<td><div p=\"".($f[$i]['p']&511)."\" title=\"".htmlspecialchars($f[$i]['n'])."\" class=de onDblClick=\"$url\"><img src=\"img/icon_".$f[$i]['i']."16.gif\"".
				(in_array($f[$i]['n'],$cutfiles)?" class=cut":"")."><span>".$f[$i]['n']."</span></div></td>
				<td><a href='dl.php?p=".urlenc(shortenpath("$curdir/".$f[$i]['n']))."' >下载</a></td>
				<td align=right>".(isset($f[$i]['s'])?number_format($f[$i]['s']):"")."</td>
				<td>".$f[$i]['t']."</td>
				<td>".$f[$i]['m']."</td>
				<td align=right class=perms>".sxr_parseperms($f[$i]['p'])."</tr>\n";
		}
		echo '</table>';
	}

	function printthumb($f) {
		global $curdir,$cfg,$cutfiles,$image_ext;
		for($i=0;$i<count($f);$i++) {
			$img=htmlspecialchars($f[$i]['d'] || !preg_match("'^".join("|",$image_ext[$cfg["tnmethod"]])."$'",$f[$i]['mi'])?
				"img/icon_".$f[$i]['i']."48.gif":sxr_tnurl("$curdir/".$f[$i]['n']));
			$url=$f[$i]['d']?"goTo(&quot;".htmlspecialchars(shortenpath("$curdir/".$f[$i]['n']))."&quot;)":
				"location.href=&quot;dl.php?p=".urlenc(shortenpath("$curdir/".$f[$i]['n']))."&quot;";
			echo "<div p=\"".$f[$i]['p']."\" title=\"".htmlspecialchars($f[$i]['n'])."\" class=tn onDblClick=\"$url\"><table class=t1><tr><td><img src=\"$img\"".(in_array($f[$i]['n'],$cutfiles)?" class=cut":"")."></td></tr></table><div class=t2>".htmlspecialchars($f[$i]['n'])."</div></div>\n";
		}
	}

	function printicon($f) {
		global $curdir,$cfg,$cutfiles;
		for($i=0;$i<count($f);$i++) {
			$url=$f[$i]['d']?"goTo(&quot;".htmlspecialchars(shortenpath("$curdir/".$f[$i]['n']))."&quot;)":
				"location.href=&quot;dl.php?p=".urlenc(shortenpath("$curdir/".$f[$i]['n']))."&quot;";
			echo "<div p=\"".$f[$i]['p']."\" title=\"".htmlspecialchars($f[$i]['n'])."\" class=ic onDblClick=\"$url\"><div class=i1><img src=\"img/icon_".$f[$i]['i']."32.gif\"".(in_array($f[$i]['n'],$cutfiles)?" class=cut":"")."></div><div class=i2>".htmlspecialchars($f[$i]['n'])."</div></div>\n";
		}
	}
?>
</td></tr>
</table>
<!-- File listing ---- ^^^^^ -->

	<table id=foot><tr>
	<?php
		echo "<td width=100%>".(count($files)-($curdir?1:0))." objects (Disk free space: ".sxr_format_fsize(disk_free_space(".")).")</td>
		<td align=right>".sxr_format_fsize($tsize)."</td>
		<td align=right>".php_uname()."</td>";
	?>
	</tr></table>
	</form>