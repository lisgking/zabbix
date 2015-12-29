<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2007, 2012.  All Rights Reserved
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

class install_template {

	private $print;
	private $message;

	function __construct(&$print, &$message)
	{
		$this->print = &$print;
		$this->message = &$message;
	}

	function start($version)
	{
		$readme_link = "<a href='../README.html' target='_blank'>{$this->message->lang('readme')}</a>";
		
		$HTML = "";
		$HTML .= <<< EOF
<form method="post" action="index.php?do=check">
<table width='100%' role='presentation'>
	<tr>
		<td class="tblheader">
			{$this->message->lang('welcome')}
		</td>
	</tr>
	<tr>
		<td>
			<p>{$this->message->lang('welcome_message', array($version))} {$this->message->lang('prerequisite_message', array($readme_link))}</p>
		</td>
	</tr>
	<tr>
		<td>
			{$this->message->lang('license_message')}
		</td>
	</tr>
	<tr>
		<td>
			<p><a href='../license/LICENSE-2.0.txt'>{$this->message->lang('license_agreement')}</a></p><br/>
			<input type='checkbox' name='Lic_CB'>{$this->message->lang('i_accept')}</input>
		</td>
	</tr>
	<tr>
		<td colspan="3" class="formsubtitle" align="right">
			<input name="install_start" class="button" type="submit" value="{$this->message->lang('next')}"/>
		</td>
	</tr>
</table>
</form>
EOF;
		return $HTML;
	} // end start.


	function show_required($required)
	{
		$HTML = "";
		 
		$HTML .= "<table width='100%'>";

		$HTML .= <<< EOF
		<tr>
			<td class='tblheader' colspan="2">{$this->message->lang('required_PHP_modules')}</td>
		</tr>
		<tr>
			<td colspan="2" class="formsubtitle">
				<p>{$this->message->lang('required_modules_for_OAT')}</p> 
                <p>{$this->message->lang('missing_module_instructions')}</p>
			</td>
		</tr>
EOF;
		foreach ($required as $k => $v)
		{
			$img = "<img src='../images/check.png' border='0' alt='" . $this->message->lang('OK') . "' />";
			$HTML .= "<tr><td class='formleft'>{$k}</td>";

			if ($v == 0)
			{
				$show_next = false;
				$img = "<img src='../images/cross.png' border='0' alt='X' />";
			}
			$HTML .= "<td class='formright'>{$img}</td></tr>";
		}
		$HTML .= "<tr><td>&nbsp;</td></tr></table>";
		return $HTML;
	} // end show_required


	function show_recommended($recommended)
	{
		$HTML = "";

		$HTML .= "<table width='100%'>";

		$HTML .= <<< EOF
                <tr>
                        <td class='tblheader' colspan="2">{$this->message->lang('recommended_PHP_modules')}</td>
                </tr>
                <tr>
                        <td colspan="2" class="formsubtitle">
                                <p>{$this->message->lang('recommended_modules_for_OAT')}</p>
                                <p>{$this->message->lang('recommend_module_instructions')}</p>
                        </td>
                </tr>
EOF;
		foreach ($recommended as $k => $v)
		{
			$img = "<img src='../images/check.png' border='0' alt='OK' />";
			$HTML .= "<tr><td class='formleft'>{$k}</td>";

			if ($v == 0)
			{
				$img = "<img src='../images/cross.png' border='0' alt='X' />";
			}
			$HTML .= "<td class='formright'>{$img}</td></tr>";
		}
		$HTML .= "</table>";
		return $HTML;
	} // end show_recommended

	function config_start()
	{
		$HTML="";
		$HTML .= <<< EOF
		<form method="post" action="index.php?do=saveconfig">
		<table width="100%">
			<tr>
				<td colspan="3" class="tblheader">{$this->message->lang("oat_config")}</td>
			</tr>
			<tr class="formsubtitle">
				<th>{$this->message->lang('parameter')}</th>
				<th>{$this->message->lang('description')}</th>
				<th>{$this->message->lang('value')}</th>
			</tr>
EOF;
		return $HTML;
	}

	function config_row($what)
	{
		$HTML = "";
		foreach ($what as $k => $v)
		{
			$HTML.=<<<EOF
			<tr>
			<td class="formleft">{$v['name']}</td>
			<td class="formright">{$v['desc']}</td>
EOF;
			if ($k == "HOMEPAGE")
			{
				$home_page_options = "<option value='welcome' " . (($v['default'] == "welcome")? " selected='selected' ": "") 
					. ">{$this->message->lang('home_page_option_welcome')}</option>"
					. "<option value='dashboard_group' " . (($v['default'] == "dashboard_group")? " selected='selected' ": "") 
					. ">{$this->message->lang('home_page_option_dashboard_group')}</option>"
					. "<option value='dashboard_server' " . (($v['default'] == "dashboard_server")? " selected='selected' ": "") 
					. ">{$this->message->lang('home_page_option_dashboard_server')}</option>";
				if (substr($v['default'],0,6) == "custom")
				{
					$home_page_options .= "<option value='{$v['default']}' selected='selected' >{$this->message->lang('home_page_option_custom')}</option>";
				}
				$HTML .= <<<EOF
				<td class="formright">
					<select name="HOMEPAGE">
						{$home_page_options}
					</select>
				</td>
EOF;
			}
			else if ($k == "ROWSPERPAGE")
			{       	
				$rpp_options = array(10,25,50,100);
				$HTML .= "<td class='formright'><select name='ROWSPERPAGE'>";
				foreach ($rpp_options as $val)
				{
					$selected = ($val == $v['default'])? "selected='selected'":"";
					$HTML .= "<option value='{$val}' {$selected}>$val</option>";
				}
				$HTML .= "</select></td>";
			}
			else if ($k == "SECURESQL")
			{
				$SECURESQL_SELECTED = (strcasecmp($v['default'],"on")==0) ? "CHECKED":"";
				$HTML .= <<<EOF
				<td class="formright"><input type=checkbox name="{$k}" {$SECURESQL_SELECTED}/></td>
EOF;
			} 
			else if ($k == "LANG")
			{
			    $HTML .= <<<EOF
				<td class="formright"><select name="{$k}">{$this->option_lang($v['default'])}</select></td>
EOF;
			}
			else
			{
				$HTML .= <<<EOF
				<td class="formright"><input size="60" name="{$k}" value="{$v['default']}"/></td>
EOF;
			}
			$HTML .= <<<EOF
			</tr>
EOF;
		}
		return $HTML;
	}

	function config_warning($warned_dir)
	{
		$HTML ="";
		$HTML .= "<input type='hidden' name='warned_dir' value='{$warned_dir}'/>";
		return $HTML;
	}

	function config_end()
	{
		$HTML = "";

		$HTML .= <<< EOF
			<tr>
				<td colspan="3" class="formsubtitle" align="right">
					<input type="submit" class="button" value="{$this->message->lang('save')}"/>
				</td>
			</tr>
		</table>
		</form>
EOF;
		return $HTML;
	}
	
    /**
     * Determines all languages supported by OAT and creates the drop-down options 
     * to include each language.
     * 
     * @param $defLang default language that should be selected
     * @return $HTML options for drop-down control
    */
    function option_lang($defLang="en_US")
    {
        // Load the English lang_language file for all language names
        $lang = array();
        $fname = "../lang/en_US/lang_language.xml";
        if ( file_exists($fname) )
        {
            $xml = simplexml_load_file( $fname );
        }
        if (! is_null($xml))
        {
            foreach ( $xml as $k )
            {
                $name = (string)$k->getName();
                $lang[$name]=(string)$xml->$name;
            }
        } else {
            // If xml is null (or we couldn't find the file), just add English
            // (This is sanity check.  We should never get here.)
            $lang['en_US'] = "English";
        }

        // Find all of the languages OAT supports
        $HTML = "";
        $data = array();
        $dir = opendir("../lang/");
        while (false !== ($file = readdir($dir)))
        {
            if (substr($file,0,1) == "." || $file == ".." )
            {
                continue;
            }
            if (file_exists("../lang/".$file."/language.php"))
            {
                $data[$file]['lang']=$lang[$file];   
            }
        }
        asort($data);  // Sort languages alphabetically

        // Create options for the drop-down of each language OAT supports
        foreach ( $data as $k => $v )
        {
            if ( $k == $defLang)
            {
                $HTML .= "<option value='{$k}' SELECTED>{$v['lang']}</option>";
            }
            else
            {
                $HTML .= "<option value='{$k}'>{$v['lang']}</option>";
            }
        }
        return $HTML;
    }

	function show_next($show_next,$what)
	{
		$HTML = "";
		if ( $show_next == true )
		{
			$HTML .= <<< EOF
			<form method="post" action="index.php?do={$what}">
            <table width="100%">
                <tr>
                   <td>&nbsp;</td>
                </tr>
                <tr>
                   <td class="formsubtitle" align="right">
                   <input name="next" class="button" type="submit" value="{$this->message->lang("next")}"/>
                   </td>
                </tr>
            </table>
            </form>
EOF;
		}
		return $HTML;
	} // end show_next

	function createDatabase()
	{
		$HTML = "";
		$HTML .= <<< EOF
		<table width="100%" role="presentation">
			<tr>
				<th class="tblheader">
					{$this->message->lang('conndb')}
				</th>
			</tr>
			<tr>
				<td>
					<p>{$this->message->lang('conndb_desc')}</p>
					<p>{$this->message->lang('create_conndb_instructions')}</p>
				</td>
			</tr>
		</table>
EOF;
		return $HTML;
	} // end createDatabase

	
	function updateDatabase()
	{
		$HTML = "";
		$HTML .= <<< EOF
		<table width="100%">
			<tr>
				<td class="tblheader">
					{$this->message->lang('conndb')}
				</td>
			</tr>
			<tr>
				<td>
					<p>{$this->message->lang('upgrade_conndb_instructions')}</p>
				</td>
			</tr>
		</table>
EOF;
		return $HTML;
	} // end updateDatabase

	function databaseDone($result_message)
	{
		$HTML = "";
		$HTML .= <<< EOF
		<table width="100%">
			<tr>
				<td class="tblheader">
					{$this->message->lang('conndb')}
				</td>
			</tr>
			<tr>
				<td>
					<p>$result_message</p>
				</td>
			</tr>
			<tr>
				<td>
					<p>{$this->message->lang('conndb_complete')}</p>
				</td>
			</tr>
		</table>
EOF;
		return $HTML;
	} // end databasesDone
	
	
	function showPlugins($plugins)
	{
	    $HTML = "";
	    $HTML .= <<< EOF
		<script type="text/javascript">
			function plugin_click(plugin_name, checkbox)
			{
				// This function only applies if the checkbox was checked
				// (not if it was unchecked).
				if (checkbox.checked == false)
				{
					return;
				}
				
				c = confirm(plugin_name + ": " + "{$this->message->lang('plugin_license_confirmation')}");
				if (c)
				{
					checkbox.checked = true;
				} else {
					checkbox.checked = false;
				}
			}
		</script>
		<form method="post" action="index.php?do=installplugins">
		<table width="100%">
			<tr>
				<td class="tblheader">{$this->message->lang('choose_plugins')}</td>
			</tr>
		</table>
		<table width="100%" border="0" cellpadding="5">
			<tr class="formsubtitle">
				<td>{$this->message->lang('plugin_name')}</td>
				<td>{$this->message->lang('description')}</td>
				<td>{$this->message->lang('plugin_author')}</td>
				<td>{$this->message->lang('plugin_version')}</td>
				<td>{$this->message->lang('plugin_server_version')}</td>
				<td>{$this->message->lang('plugin_license')}</td>
				<td>{$this->message->lang('plugin_install_checkbox')}</td>
			</tr>		
EOF;

		$index = 0;
		foreach ($plugins as $p)
		{
		    $HTML .= "<tr><td>" . $p->plugin_name . "</td>" 
		          .  "<td>" . $p->plugin_desc . "</td>"
		          .  "<td>" . $p->plugin_author . "</td>"
		          .  "<td>" . $p->plugin_version . "</td>"
		          .  "<td>" . $p->plugin_server_version . "</td>"
		          .  "<td> <a href='../tmp/{$p->plugin_license}'>{$this->message->lang('license_agreement')}</a> </td>"
		          .  "<td><input name='plugin_{$index}' type='checkbox' onclick='plugin_click(\"{$p->plugin_name}\", this)'/></td></tr>"
		          .  "<input type='hidden' name='file_name_plugin_{$index}' value='{$p->plugin_file_name}'/>"
		          .  "<input type='hidden' name='license_file_plugin_{$index}' value='../tmp/{$p->plugin_license}'/>";
		    $index++;
		}
		
		$HTML .= <<< EOF
        </table>
        <table width="100%">
                <tr>
                   <td>&nbsp;</td>
                </tr>
                <tr>
                   <td class="formsubtitle" align="right">
                   <input name="next" class="button" type="submit" value="{$this->message->lang('next')}"/>
                   </td>
                </tr>
            </table>
            </form>
EOF;
		return $HTML;
	}
	
	
	function fin()
	{
		$HTML = "";
		$HTML .= <<< EOF
		<table width="100%">
			<tr>
				<td class="tblheader">{$this->message->lang('install_complete')}</td>
			</tr>
			<tr>
				<td>
					<p>{$this->message->lang('install_complete_details')}</p>
				</td>
			</td>
			<tr>
				<td class="formsubtitle" align="right">
					<form method="post" action="../index.php">
						<input name="next" class="button" type="submit" value="{$this->message->lang('finish')}"/>
					</form>
			    </td>
			</tr>
		</table>
EOF;
		return $HTML;
	} // end fin

	function error($err_string="")
	{
		$HTML = "";
		$HTML .= <<<EOF
		<fieldset>
		<legend>{$this->message->lang('error_occurred')}</legend>
		<div class="borderwrapred">
		<table width="100%" style="background: #E3C0C0;" >
		<tr>
		<td>{$err_string}</td>
  </tr>
</table>
</div>
</fieldset>
EOF;
		return $HTML;
	}

	function warning($warning_string="")
	{
		$HTML = "";
		$HTML .= <<<EOF
		<fieldset>
		<legend>{$this->message->lang('warning')}</legend>
		<div class="borderwrapred">
		<table width="100%" style="background: #E3C0C0;" >
		<tr>
		<td>{$warning_string}</td>
  </tr>
</table>
</div>
</fieldset>
EOF;
		return $HTML;
	}
	
	function lang_page_footer($lang)
	{
		$HTML = "";

		$langdir = "../lang/";
		if ( ! file_exists($langdir) )
		{
			return $HTML;
		}

		$data = array();
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
		
		$HTML .= <<< EOF
<script type="text/javascript" src='../jscripts/global.js'></script>
<div class="borderwrapfooter">
<table cellpadding='0' cellspacing='0' border='0' width='100%'>
<tr><td align="left">
EOF;
		
		$HTML .= "<form name='language_form'> {$this->message->lang("language")}<select name='langauge_select' onchange=\"javascript:switchLang()\">";
		foreach ($data as $k => $v)
		{
			$selected = (strcasecmp($k,$lang)==0)?"selected='selected'":"";
			$HTML .= "<option {$selected} value='{$k}'>{$v['lang']}</option>";
		}
		$HTML .= "</select></form></tr></table></div>";
		
		return $HTML;
	}

} // end class
?>
