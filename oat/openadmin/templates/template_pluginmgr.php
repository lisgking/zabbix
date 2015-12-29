<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2008, 2010.  All Rights Reserved
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

class  template_pluginmgr {

	public $idsadmin;

	function error($err="")
	{
		$HTML = "";

		if ($err)
		{
			$HTML .= $this->idsadmin->template["template_global"]->global_error($err);
		}
		return $HTML;
	}

	function plugin_javascript()
	{
		$this->idsadmin->load_lang("pluginmgr");
		$HTML = "";
		$HTML .= <<<EOF
		<script type="text/javascript">
		function toggleEnabled(pluginid,state)
		{
		document.getElementById('plugin_'+pluginid).innerHTML = "{$this->idsadmin->lang('updating')}";
		if (window.XMLHttpRequest)
		{
		request = new XMLHttpRequest();
		if (request.overrideMimeType)
		{
		request.overrideMimeType('text/html');
	}
	} else if (window.ActiveXObject)
	{
	try {
	request = new ActiveXObject("Msxml2.XMLHTTP");
	} catch (e) {
	try {
	request = new ActiveXObject("Microsoft.XMLHTTP");
	} catch (e) {}
	}
	}

	request.open("POST", "index.php?act=admin&do=pluginmgr&run=toggleEnabled&enabled="+state+"&plugin_id="+pluginid,true);
	request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

	request.onreadystatechange = function() {
	if (request.readyState == 4)
	{
	if (request.status == 200)
	{
	result = request.responseText;
	document.getElementById('plugin_'+pluginid).innerHTML = result;
	}
	else
	{
	document.getElementById('plugin_'+pluginid).innerHTML = "{$this->idsadmin->lang('ErrorOccurred')}:"+request.status;
	}
	}
	} // request.onreadystatechange
	request.send(null);

	}
	
	function confirmPluginUninstall(plugin_id, msg)
	{
		var answer = confirm(msg);
		if (answer)
		{
			window.location="index.php?act=admin&do=pluginmgr&run=uninstallplugin&pluginid=" + plugin_id;
		}
	}
	</script>
EOF;
		return $HTML;
	}

	function show_plugin_header($header)
	{
		$HTML = "";
		$HTML .= <<<EOF
		<table width="100%" border="0" cellspacing="0" cellpadding="1">
		<tr class="tblheader" align="center" >
		<td colspan="10">{$header}</td>
		</tr>
EOF;
		return $HTML;
	}

	function show_plugin_sub_installed()
	{
		$HTML = "";
		$HTML .= <<<EOF
	<tr class="formsubtitle" align='center'>
		<td>{$this->idsadmin->lang('Name')}</td>
		<td>{$this->idsadmin->lang('Author')}</td>
		<td>{$this->idsadmin->lang('Version')}</td>
		<td>{$this->idsadmin->lang('ServerVersion')}</td>
		<td>{$this->idsadmin->lang('LatestInstalled')}</td>
		<td>{$this->idsadmin->lang('Enabled')}</td>
		<td>{$this->idsadmin->lang('Uninstall')}</td>
	</tr>
	
EOF;
		return $HTML;
	}

	function show_plugin_row_installed($data)
	{
		$HTML = "";
		$HTML .= <<<EOF
		<tr align='center'>
		<td>{$data['PLUGIN_NAME']}</td>
		<td>{$data['PLUGIN_AUTHOR']}</td>
		<td>{$data['PLUGIN_VERSION']}</td>
		<td>{$data['PLUGIN_SERVER_VERSION']}</td>
		<td>{$data['PLUGIN_LATEST']}</td>
		<td><div id="plugin_{$data['PLUGIN_ID']}">{$data['ENABLED']}</div></td>
		<td><button class="button" onclick="confirmPluginUninstall('{$data['PLUGIN_ID']}', '{$this->idsadmin->lang('ConfirmUninstallPlugin',array($data['PLUGIN_NAME']))}')" >{$this->idsadmin->lang('Uninstall')}</button></td>
		</tr>
EOF;
		return $HTML;
	}

	function show_plugin_sub_notinstalled()
	{
		$HTML = "";
		$HTML .= <<<EOF
	<tr class="formsubtitle">
		<td>{$this->idsadmin->lang('Name')}</td>
		<td>{$this->idsadmin->lang('Description')}</td>
		<td>{$this->idsadmin->lang('Author')}</td>
		<td>{$this->idsadmin->lang('Version')}</td>
		<td>{$this->idsadmin->lang('ServerVersion')}</td>
		<td>{$this->idsadmin->lang('MinOATVersion')}</td>
		<td>{$this->idsadmin->lang('Install')}</td>
	</tr>
	
EOF;
		return $HTML;
	}

	function show_plugin_row_notinstalled( $plugin )
	{
		$HTML = "";
		$install = <<<EOI
		<input type="button" class="button" value="{$this->idsadmin->lang('Install')}" onClick="window.location='index.php?act=admin&amp;do=pluginmgr&amp;run=installplugin&amp;file={$plugin->plugin_file_name}'"/>
EOI;

		
		$HTML .= <<<EOF
		<tr>
		<td>{$plugin->plugin_name}</td>
		<td>{$plugin->plugin_desc}</td>
		<td>{$plugin->plugin_author}</td>
		<td>{$plugin->plugin_version}</td>
		<td>{$plugin->plugin_server_version}</td>
		<td>{$plugin->plugin_min_oat_version}</td>
EOF;

	if ( $plugin->plugin_installed != 0 )
	{
		$this->idsadmin->load_lang("misc_template");
		$install = <<<END
		<input type="button" class="button" value="{$this->idsadmin->lang('Upgrade')}" onClick="window.location='index.php?act=admin&amp;do=pluginmgr&amp;run=upgradeplugin&amp;file={$plugin->plugin_file_name}&pluginid={$plugin->plugin_installed}'"/>
END;

	}
	
		$HTML .= <<<EOF
		<td>{$install}</td>
		</tr>
EOF;
		return $HTML;
	}

	function show_plugin_footer()
	{
		$HTML = "";
		$HTML .= <<<EOF
</table>
EOF;
		return $HTML;
	}

	function show_license($data)
	{
		$HTML = "";
		$pluginid = "";
		if ( isset( $this->idsadmin->in['pluginid'] ) )
		{
			$pluginid="&pluginid={$this->idsadmin->in['pluginid']}";
		}
		$HTML .= <<<EOF
		<script type="text/javascript">
		function doLicense(accept)
		{
		var url = "";
		if ( accept )
		{
		url = "index.php?act=admin&do=pluginmgr&run=accept&file={$this->idsadmin->in['file']}{$pluginid}"
	}
	else
	{
	url = "index.php?act=admin&do=pluginmgr&run=reject"
	}

	if (window.XMLHttpRequest)
	{
	request = new XMLHttpRequest();
	if (request.overrideMimeType)
	{
	request.overrideMimeType('text/html');
	}
	} else if (window.ActiveXObject)
	{
	try {
	request = new ActiveXObject("Msxml2.XMLHTTP");
	} catch (e) {
	try {
	request = new ActiveXObject("Microsoft.XMLHTTP");
	} catch (e) {}
	}
	}

	request.open("POST", url);
	request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

	request.onreadystatechange = function() {
	if (request.readyState == 4)
	{
	if (request.status == 200)
	{
	result = request.responseText;
	document.getElementById('license').innerHTML = result;
	}
	else
	{
	document.getElementById('license').innerHTML = "Error:"+request.status+" occurred";
	}
	}
	} // request.onreadystatechange
	request.send(null);
	}
	</script>
	<div id="license">
	<table>
	<tr>
	<td colspan="2">$data</td>
		</tr>
		<tr>
			<td align='center'>
			<input type="button" class="button" value="{$this->idsadmin->lang('Agree')}" onClick="doLicense(true)" />
			</td>
			<td align='center'>
			<input type="button" class="button" value="{$this->idsadmin->lang('Disagree')}" onClick="doLicense(false)" />
			</td>
		</tr>
		</table>
		</div>
EOF;
	return $HTML;
	}
}
?>