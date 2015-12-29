<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2007, 2013.  All Rights Reserved
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *       http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 **************************************************************************
 */

class template_global {

	public $idsadmin;

	function __construct()
	{
	}

	function httpheader()
	{
		@header("HTTP/1.0 200 OK");
		@header("HTTP/1.1 200 OK");
		@header("Content-type: text/html");
		@header("Cache-control: no-cache, must-revalidate, max-age=0");
		@header("Expires: Wed, 01 Mar 2006 00:00:00 GMT");
		@header("Pragma: no-cache");
	}

	function css($media="all")
	{
		$css_file = "templates/style.css";
		if ( isset( $this->idsadmin->in['runReports'] ) )
		{
			$css_file = "templates/reportstyle.css";
		}
		if ($this->idsadmin->in['act'] == "admin") {
			$css_file = "../templates/style.css";
		}

		$HTML = "";
		$HTML .= $this->includeDojoCSS();
		$HTML .= <<<EOF
<style type="text/css" media="{$media}">
	@import url('{$css_file}');
</style>
EOF;
		return $HTML;
	}
	function includeDojoCSS()
	{
		$HTML = "";
		if ( $this->idsadmin->includeDojo === TRUE )
		{
			$HTML = <<<EOF
			<style type="text/css">
			@import url("jscripts/dojo/dojo/resources/dojo.css");
			@import url("jscripts/dojo/dijit/themes/claro/claro.css");
			</style>
EOF;
		}
		return $HTML;
	}
	function pageheader()
	{
		$this->idsadmin->load_lang("misc_template");
		/*$pinger_img = "<img src='lib/pinger.php' border='0' alt='' />";
		if ($this->idsadmin->in['act'] == "admin") {
			$pinger_img = "";
		}*/

		$back_img = ($this->idsadmin->in['act'] == "admin")? "../images/back.gif":"images/back.gif";
		$help_img = ($this->idsadmin->in['act'] == "admin")? "../images/help.gif":"images/help.gif";
		$header_buttons = "";
		if ($this->idsadmin->in['act'] != "help")
		{
			if ($this->idsadmin->in['act'] != "login")
			{
				$header_buttons = <<<EOF
<td width='1%'>
<input type='image' name='backbtn' src='$back_img' class='button_image' onclick='history.back()' title='{$this->idsadmin->lang("Back")}' alt='{$this->idsadmin->lang("Back")}' />
</td>
EOF;
			}
			if ($this->idsadmin->in['act'] != "login" && $this->idsadmin->in['act'] != "admin")
			{
				$header_buttons .= <<<EOF
<td width='1%'>
<input type='image' name='welcomebtn' src='images/welcome.gif' class='button_image' title='{$this->idsadmin->lang("Welcome")}' alt='{$this->idsadmin->lang("Welcome")}'
	onclick="javascript:openOATURL('index.php?act=home&do=welcome')"/>
</td>
EOF;
			}
			$header_buttons .= <<<EOF
<td width='1%'>
<input type='image' name='helpbtn' src='$help_img' class='button_image' title='{$this->idsadmin->lang("Help")}' alt='{$this->idsadmin->lang("Help")}'
       onclick="javascript:pop('{$this->idsadmin->in['act']}','{$this->idsadmin->in['do']}')" />
</td>
EOF;
		}

		$HTML = "";

		$HTML .= <<<EOF
<div id="logo">
<table width="100%" height="54">
<tr>
<td width="60%">
<a href='{$this->idsadmin->get_config('BASEURL')}'>
</a>
</td>
<td>
<!--INFO-->
</td>
		$header_buttons
</tr>
</table>
</div>
EOF;

        if ($this->idsadmin->in['act'] == "admin")
        {
        	require_once ROOT_PATH."/lib/tabs.php";
    		$t = new tabs($this->idsadmin);
    		$t->addtab("../index.php", $this->idsadmin->lang("Login"), 0);
    		$t->addtab("index.php", $this->idsadmin->lang("Admin"), 1);    		
    		$HTML .= $t->tohtml(false);
        }
		
		return $HTML;
	}

	function collapse_menu()
	{
		$HTML = "";
		if ( $this->idsadmin->iserror )
		{
			return $HTML;
		}
		 
		$HTML .= <<<EOF
    	<td style="background-image: url(images/bufferbar.png);vertical-align: middle" onclick="expandcollapse('Menu')">
						<a onclick="expandcollapse('Menu')"><img
						id="sectionimageMenu"
						onclick="expandcollapse('Menu')"
						src="images/collapse_arrow.jpg"
						alt="{$this->idsadmin->lang('CollapseMenu')}" title="{$this->idsadmin->lang('CollapseMenu')}"/></a>
					</td>
EOF;
		return $HTML;
	}

	function pagefooter()
	{
		if ($this->idsadmin->in['act'] == "help")
		{
			return;
		}
		
		$HTML = "";	
		$HTML .= <<<EOF
<div class="borderwrapfooter">
<table cellpadding='0' cellspacing='0' border='0' width='100%'>
<tr><td align="left">{$this->global_pick_lang()}</td></tr>
<!--
<tr>
<td colspan="2" align="center"><small>OpenAdmin Tool - Version: {$this->idsadmin->get_version()}</small></td>
</tr>
<tr>
<td colspan="2" align="center"><small>Build Timestamp: {$this->idsadmin->get_buildtime()}</small></td>
</tr>
!-->
<tr>
<td colspan="2" align="center"><!--STATS--></td>
</tr>
</table>
</div>
EOF;

		return $HTML;
	}

	function global_pick_lang()
	{
		$HTML = "";

		$this->idsadmin->load_lang("global");
		$langdir = $this->idsadmin->get_config('HOMEDIR')."/lang/";
		if ( ! file_exists($langdir) )
		{
			return $HTML;
		}

		$dir = opendir($langdir);
		while (false !== ($file = readdir($dir)))
		{
			if (substr($file,0,1) == "." || $file == ".." )
			{
				continue;
			}
			if  ( is_dir($langdir.$file) 
			      && file_exists ( $langdir.$file."/language.php" ) )
			{
				include($langdir.$file."/language.php");
				$data[$file]["lang"]=$language;
			}
		}
		asort($data);  // Sort languages alphabetically

		$HTML .= "<form name='language_form'> {$this->idsadmin->lang("language")}:<select name='langauge_select' onchange=\"javascript:switchLang()\">";
		foreach ($data as $k => $v)
		{
			$selected = (strcasecmp($k,$this->idsadmin->phpsession->get_lang())==0)?"selected='selected'":"";
			$HTML .= "<option {$selected} value='{$k}'>{$v['lang']}</option>";
		}
		$HTML .= "</select></form>";

		return $HTML;
	}

	function stats()
	{
		$HTML = "";
		$HTML .= <<<EOF
EOF;
		return $HTML;
	}

	function help()
	{
		$HTML = "";
		$HTML .= <<<EOF
<a href="javascript:pop('{$this->idsadmin->in['act']}','{$this->idsadmin->in['do']}');" title="{$this->idsadmin->lang('Help')}">{$this->idsadmin->lang('Help')}</a>
EOF;
		return $HTML;
	}

	function breadCrumb()
	{
		$HTML = "";
		 
		$num = count($this->idsadmin->crumb) - 1;
		foreach ( $this->idsadmin->crumb as $k => $v )
		{

			if ( $k < $num )
			{
				if ( $v['link'] == "")
				{
					$HTML .= $v['title']." -> ";
				}
				else
				{
					$HTML .= "<a href='{$v['link']}'>{$v['title']}</a> -> ";
				}
			}
			else
			{
				$HTML .= $v['title'];
			}
		}
		return $HTML;
	}

	function javascript()
	{
		$prefix = "";
		
		if ($this->idsadmin->in['act'] == "admin") 
		{
			$prefix = "../";
		}

		$js_file = "{$prefix}jscripts/global.js";
		$HTML = "";
		$HTML .= <<<EOF
            <script type="text/javascript" src='{$js_file}'></script>
EOF;
		$HTML .= $this->includeDojoJS($prefix);
		return $HTML;
	}
	
	
	function includeDojoJS($prefix)
	{
		$HTML = "";
		if ( $this->idsadmin->includeDojo === TRUE )
		{
		$dojo_file = "{$prefix}jscripts/dojo/dojo/dojo.js";
		$dijit_file = "{$prefix}jscripts/dojo/dijit/dijit.js";
		# dojox not needed right now.
		#$dojox_file = "${prefix}jscripts/dojox/dojox/dojox.js";
		$HTML = <<<EOF
            <script type="text/javascript" src='{$dojo_file}' djConfig="parseOnLoad: true"></script>
            <script type="text/javascript" src='{$dijit_file}'"></script>
            <!--
            <script type="text/javascript" src='{$dojox_file}'"></script>
			-->
EOF;
		}
		return $HTML;
	}
	function serverInfoBlock()
	{
		// If the serverInfo object was not properly created, do not try to display the serverInfoBlock
		if ($this->idsadmin->phpsession->serverInfo == "") return;
		$HTML="";

		$versionStr = str_word_count($this->idsadmin->phpsession->serverInfo->getVersion(),1,'.1234567890');
		$versionStr = $versionStr[sizeof($versionStr)-1];
		$HTML .= <<<EOF
<br/>
<br/>

<table class='borderwrapmenu' cellpadding='0' cellspacing='0'> 
<tr>
   <th colspan="2" class='tblheader' align='center'>{$this->idsadmin->lang('ServerInfo')}</th>
</tr>
<tr>
	<td>{$this->idsadmin->lang('ServerType')}:</td>
	<td>{$this->idsadmin->lang($this->idsadmin->phpsession->serverInfo->getServerType(true))}</td>
</tr>

<tr>
	<td>{$this->idsadmin->lang('Version')}:</td>
	<td>{$versionStr}</td>
</tr>

<tr>
	<td>{$this->idsadmin->lang('ServerTime')}:</td>
	<td>{$this->idsadmin->phpsession->serverInfo->getServerTime()}</td>
</tr>

<tr>
	<td>{$this->idsadmin->lang('BootTime')}:</td>
	<td>{$this->idsadmin->phpsession->serverInfo->getBootTime()}</td>
</tr>

<tr>
	<td>{$this->idsadmin->lang('UpTime')}:</td>
	<td>{$this->idsadmin->phpsession->serverInfo->getServerUpTime($this->idsadmin)}</td>
</tr>

<tr>
	<td>{$this->idsadmin->lang('Sessions')}:</td>
	<td>{$this->idsadmin->phpsession->serverInfo->getNumSessions()}</td>
</tr>

<tr>
	<td>{$this->idsadmin->lang('MaxUsers')}:</td>
	<td>{$this->idsadmin->phpsession->serverInfo->getMaxUsers()}</td>
</tr>


EOF;

		if ( Feature::isAvailable ( Feature::CHEETAH2, $this->idsadmin->phpsession->serverInfo->getVersion() )  )
		{
			$HTML.=<<<END
			<tr> <th colspan="2" align="center"> {$this->idsadmin->lang('OperatingSys')} </th></tr>
    		<tr>
    		<td>{$this->idsadmin->lang('TotalMem')}:</td>
    		<td> {$this->idsadmin->phpsession->serverInfo->getMemTotal()} </td>
    		</tr>
    		<tr>
    		<td>{$this->idsadmin->lang('FreeMem')}:</td>
    		<td> {$this->idsadmin->phpsession->serverInfo->getMemFree()} </td>
    		</tr>
    		 <tr>
    		<td>{$this->idsadmin->lang('NumCPU')}:</td>
    		<td> {$this->idsadmin->phpsession->serverInfo->getNumCpu()} 
    		</td>
    		</tr>
END;

		}

		$HTML.="</table>";


		return $HTML;

	}

	function connectedinfo()
	{
		$HTML="";
		$HTML .= <<<EOF
        
<table align="right" style="text-align: right;" cellspacing="3" cellpadding="0" width="600" border="0" >

<tr>
	<th nowrap="nowrap">{$this->idsadmin->lang('Server')}:</th>
	<td colspan="3" style="width:100%; text-align:left">{$this->idsadmin->getServers()}</td>
</tr>
EOF;

		// Add database drop-down to SQL Toolbox
		if ($this->idsadmin->in['act'] == "sqlwin"
		&&  ( $this->idsadmin->in['do'] != "dbtab"  || $this->idsadmin->phpsession->get_sqldbname() != ""))
		{
			$HTML .= <<<EOF
<tr>
    <th nowrap="nowrap">{$this->idsadmin->lang('Database')}:</th>
    <td colspan='3' style="width:100%">{$this->idsadmin->getDatabaseSelect()}</td>
</tr>
EOF;
		}
		
		// Add locale drop-down to Replication plugin 
		if (!$this->idsadmin->iserror // no fatal error
			&& $this->idsadmin->phpsession->serverInfo != "" // have server info 
			&& Feature::isAvailable ( Feature::PANTHER_UC4, $this->idsadmin->phpsession->serverInfo->getVersion() ) // correct version
			&& ( $this->idsadmin->in['act'] == "ibm/er/er" || $this->idsadmin->in['act'] == "ibm/er/ucm" ) ) // in er or ucm
		{
			$HTML .= <<<EOF
<tr>
	<form method='post' action='index.php?act={$this->idsadmin->in['act']}&amp;do={$this->idsadmin->in['do']}' name='localeswitch' >
	<th nowrap="nowrap">{$this->idsadmin->lang('MB_Locale')}:</th>
	<td title='{$this->idsadmin->lang('MB_Locale_Encoding_tooltip')}' style='width:45%'>
      {$this->switchLocaleJscript()}
	  {$this->idsadmin->getLocaleLanguageSelect()}
	</td>
	</form>
	<form method='post' action='index.php?act={$this->idsadmin->in['act']}&amp;do={$this->idsadmin->in['do']}' name='encodingswitch' >
	<th nowrap="nowrap">
	  {$this->idsadmin->lang('MB_Encoding')}:
	</th>
	<td title='{$this->idsadmin->lang('MB_Locale_Encoding_tooltip')}' style='width:45%'>
	  {$this->idsadmin->getLocaleSelect()}
	</td>
	</form>
</tr>
EOF;
		}
		
		$HTML .= <<<EOF
</table>
EOF;
		return $HTML;

	}

	function global_status($msg="")
	{
		$this->idsadmin->load_lang("misc_template");
		$HTML = "";
		$HTML .= <<<EOF
<div class="tabpadding">
<fieldset>
<legend>{$this->idsadmin->lang('Info')}</legend>
<table width="100%" style="background-color: #FFFFFF;">
  <tr>
  <td>{$msg}</td>
  </tr>
</table>
</fieldset>
</div>
EOF;
		return $HTML;
	}

	function global_error($msg="", $title="")
	{
		$error_icon_location = ROOT_PATH . "/images/error_msg.png";
		$HTML = "";

		if($title != "")
		{
			$HTML .= <<<EOF
<div class="borderwraperror">
<table width="100%">
	<tr>
	<td><p class="errortitle"><img src="{$error_icon_location}" align="middle"/> {$title} </p></td>
	</tr>
	<tr>
	<td>{$msg}</td>
	</tr>
</table>
</div>
EOF;
		}
		else
		{
			$HTML .= <<<EOF
<div class="borderwraperror2">
<table width="100%">
  <tr>
  <td><p><img src="{$error_icon_location}" align="middle"/> {$msg} </p></td>
  </tr>
</table>
</div>
EOF;
		}

		return $HTML;
	}

	function __destruct()
	{
	}

	/**
	 * Redirect the user to a different URL .
	 */
	function global_redirect($txt="",$url="",$force=0)
	{
		header("Location: {$url}");
		exit();
	} // end global_redirect
	
	function switchServerJscript()
	{
	    $this->idsadmin->load_lang("global");
	    $HTML = <<< EOF
<script type="text/javascript">
function switchServers(sel)
{
      // save off what was previously selected so we can reset it.
      var prev = 0;

      for ( prev=0 ; prev < sel.options.length ; prev++ )
      {
        if ( sel.options[prev].defaultSelected == true )
        {
            break;
        }
      }

      c = confirm('{$this->idsadmin->lang("switchServer")} ' + sel.options[sel.selectedIndex].text + '\\n{$this->idsadmin->lang("confirmContinue")}');

      if ( c )
      {
        document.serverswitch.submit();
      }
      else
      {
        sel.selectedIndex = prev;
      }
}
</script>
EOF;
        return $HTML;
	}

	function switchLocaleJscript()
	{
	    $this->idsadmin->load_lang("global");
	    $HTML = <<< EOF
<script type="text/javascript">
function switchLocales(sel)
{
      document.localeswitch.submit();
}
function switchEncoding(sel)
{
      document.encodingswitch.submit();
}
</script>
EOF;
        return $HTML;
	}
	
	function switchDatabaseJscript()
	{
	    $this->idsadmin->load_lang("global");
	    $HTML = <<< EOF
<script type="text/javascript">
function switchDatabase(sel)
{
      // save off what was previously selected so we can reset it.
      var prev = 0;

      for ( prev=0 ; prev < sel.options.length ; prev++ )
      {
        if ( sel.options[prev].defaultSelected == true )
        {
            break;
        }
      }

      c = confirm("{$this->idsadmin->lang("switchDatabase")} " + sel.options[sel.selectedIndex].value + ".  {$this->idsadmin->lang("confirmContinue")}");

      if ( c )
      {
        document.dbswitch.submit();
      }
      else
      {
        sel.selectedIndex = prev;
      }
}

</script>
EOF;
        return $HTML;
	}
}
?>
