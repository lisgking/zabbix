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


class  template_admin {

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

    function config_hdr($err="")
    {
        $this->idsadmin->load_lang("admin");
        $HTML = $this->error($err);
        $HTML .= <<<EOF
<div id="borderwrap">
<form name="config" method="post" action="index.php?act=admin&amp;do=checkconfig">
<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr>
<th class="formheader">{$this->idsadmin->lang('param')}</th>
<th class="formheader">{$this->idsadmin->lang('value')}</th>
</tr>
EOF;
return $HTML;
    }

    function option_lang($defLang="en_US")
    {
        $this->idsadmin->load_lang("language");
        $HTML = "";

        $dir = opendir(ROOT_PATH."/lang/");
        while (false !== ($file = readdir($dir)))
        {
            if (substr($file,0,1) == "." || $file == ".." )
            {
                continue;
            }
            if (file_exists(ROOT_PATH."/lang/".$file."/language.php"))
            {
                $data[$file][lang]=$this->idsadmin->lang($file);
            }
        }
        asort($data);

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
    
    function option_home_page($selectedHomePage="welcome")
    {
        $HTML  = "<option value='welcome' " . (($selectedHomePage == "welcome")? " selected='selected' ": "") 
        	   . ">{$this->idsadmin->lang('home_page_option_welcome')}</option>";
        $HTML .= "<option value='dashboard_group' " . (($selectedHomePage == "dashboard_group")? " selected='selected' ": "") 
        	   . ">{$this->idsadmin->lang('home_page_option_dashboard_group')}</option>";
        $HTML .= "<option value='dashboard_server' " . (($selectedHomePage == "dashboard_server")? " selected='selected' ": "") 
        	   . ">{$this->idsadmin->lang('home_page_option_dashboard_server')}</option>";
        if (substr($selectedHomePage,0,6) == "custom")
        {
        	$HTML .= "<option value='{$selectedHomePage}' selected='selected' >{$this->idsadmin->lang('home_page_option_custom')}</option>";
        }
        return $HTML;
    }
    
    function option_rows_per_page($default_rows_per_page=25)
    {    
    	if (is_null($default_rows_per_page))
    	{
    		$default_rows_per_page = 25;
    	}
    	
       	$rpp_options = array(10,25,50,100);
    
    	$HTML = "";
    	foreach ($rpp_options as $val)
    	{
    		$selected = ($val == $default_rows_per_page)? "selected='selected'":"";
    		$HTML .= "<option value='{$val}' {$selected}>$val</option>";
    	}
    	return $HTML;
	}
	
    function config_row($params)
    {
        $HTML .= <<<EOF
<tr>
    <td class="formleft">{$this->idsadmin->lang("defaultlang")}</td>
    <td class="formright">
        <span title="{$this->idsadmin->lang("LANG")}">
        <select name="LANG">
        {$this->option_lang($params['LANG'])}
        </select>
        </span>
    </td>
</tr>

<tr>
    <td class="formleft">{$this->idsadmin->lang("baseurl")}</td>
    <td class="formright">
        <span title="{$this->idsadmin->lang("BASEURL")}">
        <input type="text" name="BASEURL" value="{$params['BASEURL']}" size="80"/>
        </span>
    </td>
</tr>

<tr>
    <td class="formleft">{$this->idsadmin->lang("installdir")}</td>
    <td class="formright">
	    <span title="{$this->idsadmin->lang("HOMEDIR")}">
        <input type="text" name="HOMEDIR" value="{$params['HOMEDIR']}" size="80"/>
        </span>
    </td>
</tr>

<tr>
    <td class="formleft">{$this->idsadmin->lang("conndb_loc")}</td>
    <td class="formright">
        <span title="{$this->idsadmin->lang("CONNDBDIR")}">
		<input type="text" name="CONNDBDIR" value="{$params['CONNDBDIR']}" size="80"/>
        </span>
    </td>
</tr>

<tr>
    <td class="formleft">{$this->idsadmin->lang("HomePage")}</td>
    <td class="formright">
        <span title="{$this->idsadmin->lang("HOMEPAGE")}">
        <select name="HOMEPAGE">
        	{$this->option_home_page($params['HOMEPAGE'])}
        </select>
        </span>
    </td>
</tr>

<tr>
    <td class="formleft">{$this->idsadmin->lang("pingerinterval")}</td>
    <td class="formright">
        <span title="{$this->idsadmin->lang("PINGINTERVAL")}">
        <input type="text" name="PINGINTERVAL" value="{$params['PINGINTERVAL']}" size="40" />
        </span>
    </td>
</tr>

<tr>
    <td class="formleft">{$this->idsadmin->lang("rowsperpage")}</td>
    <td class="formright">
        <span title="{$this->idsadmin->lang("ROWSPERPAGE")}">
        <select name="ROWSPERPAGE">
        {$this->option_rows_per_page($params['ROWSPERPAGE'])}
        </select>
        </span>
    </td>
</tr>

EOF;
	$SECURESQL_SELECTED = (strcasecmp($params['SECURESQL'],"on")==0) ? "CHECKED":"";
	$HTML.=<<<EOF
<tr>
    <td class="formleft">{$this->idsadmin->lang("secureSQL")}</td>
    <td class="formright">
        <span title="{$this->idsadmin->lang("SECURESQL_tooltip")}">
        <input type="checkbox" name="SECURESQL" {$SECURESQL_SELECTED}/>{$this->idsadmin->lang("SECURESQL")}
        </span>
    </td>
</tr>
EOF;
	
	$HTML.=<<<EOF
<tr>
    <td class="formleft">{$this->idsadmin->lang("informixcontime")}</td>
    <td class="formright">
        <span title="{$this->idsadmin->lang("INFORMIXCONTIME")}">
        <input type="text" name="INFORMIXCONTIME" value="{$params['INFORMIXCONTIME']}" size="40" />
        </span>
    </td>
</tr>
<tr>
    <td class="formleft">{$this->idsadmin->lang("informixconretry")}</td>
    <td class="formright">
        <span title="{$this->idsadmin->lang("INFORMIXCONRETRY")}">
        <input type="text" name="INFORMIXCONRETRY" value="{$params['INFORMIXCONRETRY']}" size="40" />
        </span>
    </td>
</tr>
EOF;
return $HTML;
    }

    function config_footer()
    {
        $HTML = "";
        $HTML .= <<<EOF
<tr>
   <td colspan="2" align="center" class="formsubtitle">
         <input type="submit" class="button" value="{$this->idsadmin->lang('save')}" name="dosaveconf"/>
   </td>
</tr>
</table>
</form>
</div>
EOF;
return $HTML;
    }

    /*
     * connection form. serves dual purpose for both
     * add / edit.
     */
    function connform($data,$envvars,$do="doaddconn",$hdr="Add A Connection",$groups)
    {
    	$envvars_serial = htmlentities(serialize($envvars),ENT_COMPAT,"UTF-8");

        $onsocSELECTED = "";
        $ontliSELECTED = "";
        $onsslSELECTED = "";

        $HTML = "";
        $protocol = isset($data['IDSPROTOCOL'])? $data['IDSPROTOCOL']:"";
        switch ( $protocol )
        {
            case "onsoctcp":
                $onsocSELECTED="SELECTED";
                break;
            case "ontlitcp":
                $ontliSELECTED="SELECTED";
                break;
            case "onsocssl":
                $onsslSELECTED="SELECTED";
                break;
        }
    	
    	$js_file = "../jscripts/admin_connform.js";
        $HTML="";
        $HTML .= <<<EOF
<script type="text/javascript" src='{$js_file}'></script>
{$this->testConnectionJscript()}
{$this->connectionEnvVarsJscript()}

<form name="connform" method="post" action="index.php?act=admin&amp;do={$do}">
<table cellspacing="0" cellpadding="0" width="100%">
<tr>
<td class="tblheader" colspan="3">{$hdr}</td>
</tr>
<tr>
    <td class="formleft">{$this->idsadmin->lang("group")}</td>
    <td class="formright" colspan="2">
    <input type="hidden" name="CONN_NUM" value="{$data['CONN_NUM']}"/>
      <select name="GROUP_NUM">
      {$this->optiongroups($groups,$data['GROUP_NUM'])}
      </select>
    </td>
</tr>
<tr>
    <td class="formleft">{$this->idsadmin->lang("informixserver")}</td>
    <td class="formright" colspan="2"><input type="text" name="SERVER" value="{$data['SERVER']}"/></td>
</tr>
<tr>
    <td class="formleft">{$this->idsadmin->lang("hostname")}</td>
    <td class="formright" colspan="2">
          <input type="text" name="HOST" value="{$data['HOST']}"/>
    </td>
</tr>
<tr>
    <td class="formleft">{$this->idsadmin->lang("port")}</td>
    <td class="formright" colspan="2"><input type="text" name="PORT" value="{$data['PORT']}"/></td>
</tr>
<tr>
    <td class="formleft">{$this->idsadmin->lang("username")}</td>
    <td class="formright" colspan="2"><input type="text" name="USERNAME" value="{$data['USERNAME']}"/></td>
</tr>
<tr>
    <td class="formleft">{$this->idsadmin->lang("password")}</td>
    <td class="formright" colspan="2"><input type="password" name="PASSWORD" autocomplete="off" value="{$data['PASSWORD']}"/></td>
</tr>
<tr>
    <td class="formleft">{$this->idsadmin->lang("IDSProtocol")}</td>
    <td class="formright" colspan="2">
        <span title="{$this->idsadmin->lang("IDSProtocol_explain")}">
        <select name="IDSPROTOCOL">
                        <option value="onsoctcp" {$onsocSELECTED}>onsoctcp</option>
            <option value="ontlitcp" {$ontliSELECTED}>ontlitcp</option>
            <option value="onsocssl" {$onsslSELECTED}>onsocssl</option>
        </select>
        </span>
    </td>
</tr>
<!-- Disabling this functionality.  See idsdb00232788.
<tr>
    <td class="formleft">{$this->idsadmin->lang("latitude")}</td>
    <td class="formright" colspan="2"><input type="text" name="LAT" value="{$data['LAT']}"/></td>
</tr>
<tr>
    <td class="formleft">{$this->idsadmin->lang("longitude")}</td>
    <td class="formright" colspan="2"><input type="text" name="LON" value="{$data['LON']}"/></td>
</tr>
-->

<tr>
    <td class="formleft">{$this->idsadmin->lang("idsdport")}</td>
    <td class="formright" colspan="2"><input type="text" size=6 name="IDSD" value="{$data['IDSD']}"/></td>
</tr>

<tr>
    <td class="formleft">{$this->idsadmin->lang("envvars")}</td>
    <td class="formright" colspan="2">
        <input type="hidden" id="envvars" name="envvars" value="$envvars_serial" />
        <input type="hidden" id="envvar_modified" name="envvar_modified" value=0 />
        <input type="button" class="button" id="ModifyEnv" value="{$this->idsadmin->lang("viewmodify")}" onClick="expand_collapse('env_expanded');expand_collapse('ModifyEnv');expand_collapse('CollapseEnv');"/>
        <input type="button" class="button" id="CollapseEnv" value="{$this->idsadmin->lang("collapse")}" onClick="expand_collapse('env_expanded');expand_collapse('ModifyEnv');expand_collapse('CollapseEnv');" style="display:none"/>
        <div id="env_expanded" style="display:none">
            <table id='envtable' class='formenv'><tbody id='envtbody'>
                 <tr><th>{$this->idsadmin->lang("varname")}</th><th>{$this->idsadmin->lang("value")}</th><th></th></tr>
                 <tr>
                     <td><input type="text" id="envvarname" name="envvarname" /></td>
                     <td><input type="text" id="envvarvalue" name="envvarvalue" /></td>
                     <td style='text-align:center;'><input type='button' class='button' name='AddEnvVar' value='{$this->idsadmin->lang("add")}' onClick='addenvvar();' /></td>
                 </tr>
    
EOF;
	if (! empty($envvars))
	{
		$cnt = 0;
        foreach ($envvars as $name => $value)
	    {
	    	$rowid = "envvar_$cnt";
		    $HTML .= <<<EOF
		         <tr id='$rowid'>
		             <td id='{$rowid}_name'>$name</td>
		             <td id='{$rowid}_value'>$value</td>
		             <td style='text-align:center;'>
		                 <input type='button' class='button' name='DeleteEnvVar' value='{$this->idsadmin->lang("delete")}' onClick='deleteenvvar("$rowid");' />
		             </td>
		         </tr>
EOF;
		    $cnt++;
	    }
	}

    $HTML .= <<<EOF
	          </tbody></table>
        </div>
    </td>
</tr>

<tr>
    <td  class="formsubtitle" align='center'>
      <input type="submit" class="button" name="{$do}" value="{$this->idsadmin->lang("save")}"/>
    </td>

    <td  class="formsubtitle" align='center'>
      <input type="button" class="button" name="Cancel" value="{$this->idsadmin->lang("cancel")}" onClick="history.back()" />
    </td>
    
    <td colspan="1" class="formsubtitle" align='right'>
        <input type="button" class="button" onClick="testconnection();" value="{$this->idsadmin->lang("testconnection")}"/>
    </td>
    
    
</tr>
</table>
</form>
<div id="connectioninfo" style="text-align:center"></div>
EOF;
return $HTML;
    } #end connform

    function optiongroups($data,$select=1)
    {
        $this->idsadmin->load_lang("login");
        $HTML = "";
        $selected="";
        foreach ($data as $key => $vee)
        {
            if ($select == $vee['GROUP_NUM'])
            {
                $selected="SELECTED";
            }
            if ($vee['GROUP_NAME'] == "Default")
            {
                $grpname = $this->idsadmin->lang("Default");
            } else {
                $grpname = $vee['GROUP_NAME'];
            }
            $HTML .= "<option {$selected} value='{$vee['GROUP_NUM']}'>{$grpname}</option>";
            $selected = "";
        }
        return $HTML;
    }#end optiongroups

    function addconn($data,$envvars,$groups,$err="")
    {
        $HTML = $this->error($err);
        $HTML .= $this->connform($data,$envvars,"doaddconn",$this->idsadmin->lang("AddConn"),$groups);
        return $HTML;
    }#end addconn

    function editconn($data,$envvars,$groups,$err)
    {
        $HTML = $this->error($err);
        $HTML .= $this->connform($data,$envvars,"doeditconn",$this->idsadmin->lang("EditConn"),$groups);
        return $HTML;
    }#end editconn


    function editgroup($err="",$data="")
    {
        $checked = "";
        if ( isset( $data[0]['READONLY'] ) && $data[0]['READONLY'] == 1 )
        {
            $checked = "checked";
        }
        
        if ($data[0]['GROUP_NAME'] == "Default")
        {
            $this->idsadmin->load_lang("login");
            $grpname = $this->idsadmin->lang("Default");
        } else {
            $grpname = $data[0]['GROUP_NAME'];
        }
        
        $HTML = $this->error($err);
        $HTML .= <<<EOF
<form name="editgroup" method="POST" 
   action="index.php?act=admin&amp;do=doeditgroup">
<div class="borderwrap">
<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr>
  <td colspan="2" class="tblheader">{$this->idsadmin->lang('EditGroup')}</td>
</tr>

<tr>
  <td class="formleft">{$this->idsadmin->lang('groupname_sc')}:</td>
  <td align="left" class="formright">
   <input type="hidden" name="num" value="{$data[0]['GROUP_NUM']}"/>
   <input type="text" name="groupname" size="22" maxlength="20" value="{$grpname}" />
  </td>
</tr>
<tr>
  <td class="formleft">{$this->idsadmin->lang('password')}:</td>
  <td align="left" class="formright">
   <input type="password" name="password" size="22" maxlength="20" autocomplete="off" value="{$data[0]['PASSWORD']}" />
  </td>
</tr>

<tr>
        <td width="20%" class="formleft">{$this->idsadmin->lang('readonly_sc')}:</td>
        <td align="left" class="formright">
        	<input type="checkbox" name="readonly" $checked />
        </td>
</tr>

<tr>
  <td class="formsubtitle" colspan="2" align="center">
   <input type="submit" name="doeditgroup" class="button" value="{$this->idsadmin->lang('save')}" />
  </td>
</tr>
</table>
</div>
</form>
EOF;
return $HTML;
    } #end editgroup


    function addgroup($err="",$grps="")
    {
        $HTML = $this->error($err);
        ###output the form
        $HTML .= <<<EOF
<form name='addgroup' method='post' action='index.php?act=admin&amp;do=doaddgroup'>
<table width="100%" cellspacing="0" cellpadding="0">
    <tr>
        <th colspan="2" class="tblheader">{$this->idsadmin->lang("AddGroup")}</th>
    </tr>
    <tr>
        <td width="20%" class="formleft">{$this->idsadmin->lang("groupname_sc")}:</td>
        <td align="left" class="formright">
        	<input type="text" name="groupname" size="22" maxlength="20" />
        </td>
    </tr>
    <tr>
        <td width="20%" class="formleft">{$this->idsadmin->lang("password")}:</td>
        <td align="left" class="formright"><input type="password" name="password" size="22" maxlength="20" autocomplete="off" /></td>
    </tr>
      
    <tr>
        <td width="20%" class="formleft">{$this->idsadmin->lang("readonly_sc")}:</td>
        <td align="left" class="formright">
        	<input type="checkbox" name="readonly"/>
        </td>
    </tr>
      
    <tr>
        <td class="formsubtitle" colspan="2" align="center">
            <input class="button" type="submit" value="{$this->idsadmin->lang("add")}" />
        </td>
    </tr>
</table>
</form>
EOF;
return $HTML;
    }

    function groups_hdr($err="")
    {
        $HTML = $this->error($err);
        $HTML .= <<<EOF
<script type="text/javascript">
function testit()
{
var foundit=false;
for (var i=0; i < document.deletegroup.elements.length; i++)
   {
    if(document.deletegroup.elements[i].getAttribute("type") == "checkbox") { 
       if (document.deletegroup.elements[i].checked) {
           foundit = true;
           break;
       }
    }
   }
 if ( foundit ) 
 {
 return (confirm("{$this->idsadmin->lang('SureToDeleteGroup')}"));
 }
 return foundit;
}
</script>
<div class='borderwrap'>
    <form name='deletegroup' method='post' action='index.php?act=admin&amp;do=dodelgroup' onsubmit="return testit()">
    <table width="100%" border="0" cellspacing="0" cellpadding="1">
       <tr>
           <td class="tblheader" colspan='2'>{$this->idsadmin->lang("currgroups")}</td>
           <td class="tblheader">{$this->idsadmin->lang("deleteselected")}</td>
       </tr>
       <tr>
           <td class="formsubtitle" align='center'>{$this->idsadmin->lang("groupname")}</td>
           <td class="formsubtitle" align='center'>{$this->idsadmin->lang("numconn")}</td>
           <td class="formsubtitle">&nbsp;</td>
       </tr>
EOF;
return $HTML;
    }

    function groups_row($data,$cnt=0)
    {
        $this->idsadmin->load_lang("login");
        
        $HTML = "";
        $row = "row".($cnt%2);
        
        if ($data['GROUP_NAME'] == "Default")
        {
            $grpname = $this->idsadmin->lang("Default");
        } else {
            $grpname = $data['GROUP_NAME'];
        }
        
        $HTML .= <<<EOF
<tr>
   <td class='{$row}'>
      <a href='index.php?act=admin&amp;do=editgroup&amp;group_num={$data['GROUP_NUM']}' title='{$this->idsadmin->lang('EditGroup_',array($grpname))}'>{$grpname}</a>
   </td>
   <td class='{$row}' align='center'>
      <a href='index.php?act=admin&amp;do=showconn&amp;group={$data['GROUP_NUM']}' title='{$this->idsadmin->lang('ShowConnections')}'><img src="images/edit.gif" border="0" alt="{$this->idsadmin->lang('ShowConnections')}"/>{$data['CNT']}</a>
   </td>
EOF;

        if ($data['GROUP_NUM']==1)  {
            $checkbox = "&nbsp;";
        }
        else  {
            $checkbox = " <input type='checkbox' name='{$data['GROUP_NUM']}' /> ";
        }

        $HTML .= <<<EOF
   <td class='{$row}' align="center">
   {$checkbox}
   </td>
</tr>
EOF;
return $HTML;
    } #end groups_row

    function groups_footer()
    {
        $HTML = "";
        $HTML .= <<<EOF
<tr><td colspan='3' class='formsubtitle' align='right'><input class='button' type='submit' name='delgrp' value="{$this->idsadmin->lang('delete')}" /></td></tr>
    </table>
    </form>
EOF;
return $HTML;
    }
    
    function import_export()
    {
    	$HTML = "";
    	$HTML .= <<<EOF

<script type="text/javascript">
function overwriteWarning(importform)
{
	if(importform.overwrite.checked){
		var yes = confirm('{$this->idsadmin->lang("overwriteConfirm")}');
	}
	
	if(yes == false){
		importform.overwrite.checked = false;
	}
}
function securityWarning(exportform)
{
	if(exportform.exportpasswd.checked){
		var yes = confirm('{$this->idsadmin->lang("securityWarning")}');
	}
	
	if(yes == false){
		exportform.exportpasswd.checked = false;
	}
}
</script>


<div class="borderwrap">
<br/>
<br/>
<form name="importform" method="post" action='index.php?act=admin&amp;do=doimport' enctype="multipart/form-data">
<b>{$this->idsadmin->lang("import")}</b><br/>
<input type='file' name='importfile'/>
<input class='button' type='submit' name='import' value='{$this->idsadmin->lang("importbutton")}'/><br/>
<input type='checkbox' name='overwrite'
onclick='overwriteWarning(this.form)'/>{$this->idsadmin->lang("importcheckbox")}<br/>
</form>
<br/>
<br/>
<form name="exportform" method="post" action='index.php?act=admin&amp;do=doexport'>
<b>{$this->idsadmin->lang("export")}</b><br/>
<input class='button' type='submit' name='export' value='{$this->idsadmin->lang("exportbutton")}'/><br/>
<input type='checkbox' name='exportpasswd'
onclick='securityWarning(this.form)'/>{$this->idsadmin->lang("exportcheckbox")}<br/>
</form>
</div>
EOF;

    	return $HTML;
    }

    function connections_header($data="", $err="")
    {
        foreach ( $data as $k => $v ) 
        {
            $grpname  = $v['GROUP_NAME'];
            if ($grpname == "Default")
            {
                $this->idsadmin->load_lang("login");
                $this->idsadmin->load_lang("admin");
                $grpname = $this->idsadmin->lang("Default");
            }
            break;
        }
        $HTML = $this->error($err);
        $HTML .= <<<EOF
<script type="text/javascript">
function testitconn()
{
var foundit=false;
for (var i=0; i < document.deleteconn.elements.length; i++)
   {
    if(document.deleteconn.elements[i].getAttribute("type") == "checkbox") { 
       if (document.deleteconn.elements[i].checked) {
           foundit = true;
           break;
       }
    }
   }
 if ( foundit ) 
 {
 return (confirm("{$this->idsadmin->lang('SureToDeleteConn')} "));
 }
 return foundit;
}
</script>
    <form name='deleteconn' method='post' action='index.php?act=admin&amp;do=dodelconn&amp;group={$this->idsadmin->in['group']}' onsubmit='return testitconn()'>
<div class="borderwrap">
<table width="100%" cellspacing="0" cellpadding="0">
<tr>
<td class="tblheader" colspan="5">
{$this->idsadmin->lang('groupname')}: {$grpname}
</td>
</tr>
<tr>
<td class="formsubtitle">{$this->idsadmin->lang('informixserver')}</td>
<td class="formsubtitle">{$this->idsadmin->lang('hostname')}</td>
<td class="formsubtitle">{$this->idsadmin->lang('port')}</td>
<td class="formsubtitle" align="center">{$this->idsadmin->lang('deleteselected')}</td>
</tr>
EOF;
return $HTML;
    }# end connections_header

    function connections_row($data,$cnt=2)
    {
        $HTML = "";
        $cnt = ($cnt % 2);
        $HTML .= <<<EOF
<tr>
<td class="row{$cnt}">
    <a href="index.php?act=admin&amp;do=editconn&amp;conn={$data['CONN_NUM']}" title="{$this->idsadmin->lang('EditConn_',array($data['SERVER']))}"><img src="images/edit.gif" alt="{$this->idsadmin->lang('EditConn_',array($data['SERVER']))}" border="0"/>{$data['SERVER']}</a></td>
<td class="row{$cnt}">{$data['HOST']}</td>
<td class="row{$cnt}">{$data['PORT']}</td>
<td class="row{$cnt}" align="center"><input type="checkbox" name="{$data['CONN_NUM']}"/></td>
</tr>
EOF;
return $HTML;
    } #end connections_row

    function connections_footer()
    {
        $HTML = "";
        $HTML .= <<<EOF
<tr>
<td colspan='5' align='right' class="formsubtitle">
<span title="{$this->idsadmin->lang('deleteselected')}"><input class="button" type='submit' name='delconn' value="{$this->idsadmin->lang('delete')}"/></span> 
</td>
</tr>

</table>
</form>
</div>
EOF;
return $HTML;
    } // end connections_footer

    function addhelp($data,$helpdb=0,$helpdb_select,$err="")
    {
        $HTML = $this->error($err);
        $HTML .= $this->helpform($data,"doaddhelp",$helpdb,$this->idsadmin->lang("AddHelp"),$helpdb_select);
        return $HTML;
    } // end addhelp

    function edithelp($data,$helpdb=0,$err="")
    {
        $HTML = $this->error($err);
        $HTML .= $this->helpform($data,"doedithelp",$helpdb,$this->idsadmin->lang("EditHelp"));
        return $HTML;
    } // end addhelp

    function helpform($data,$do="doaddhelp",$helpdb=0,$hdr="",$helpdb_select="")
    {
        $HTML = "";
        $HTML .= <<<EOF
<form name="helpform" method="post" action="index.php?act=admin&amp;do={$do}&amp;helpdb={$helpdb}">
<table cellspacing="0" cellpadding="0" width="100%" border="0">
<tr>
<td class="tblheader" colspan="2">{$hdr}</td>
</tr>
EOF;
		if ($helpdb_select != "")
		{
			$HTML .= <<<EOF
<tr>
    <td class="formleft">{$this->idsadmin->lang("helpDB")}</td>
    <td class="formright">$helpdb_select</td>
</tr>
EOF;
		}

	$HTML .= <<<EOF
<tr>
    <td class="formleft">{$this->idsadmin->lang("act")}</td>
    <td class="formright">
       <input type="hidden" name="num" value="{$data['num']}"/>
       <input type="text" name="helpact" value="{$data['helpact']}"/>
    </td>
</tr>
<tr>
    <td class="formleft">{$this->idsadmin->lang("do")}</td>
    <td class="formright">
      <input type="text" name="helpdo" value="{$data['helpdo']}"/>
    </td>
</tr>
<tr>
    <td class="formleft">{$this->idsadmin->lang("description")}</td>
    <td class="formright">
          <textarea name="desc" cols="80" rows="5">{$data['desc']}</textarea>
    </td>
</tr>
<tr>
    <td colspan="2" class="formsubtitle" align='center'>
      <input type="submit" class="button" name="{$do}" value="{$this->idsadmin->lang('save')}"/>
    </td>
</tr>

</table>
</form>
EOF;
return $HTML;
    } #end helpform

    function help_header($helpdb=0)
    {
        $HTML = "";
        $HTML .= <<<EOF
<script type="text/javascript">
function testhelp()
{
var foundit=false;
for (var i=0; i < document.helpform.elements.length; i++)
   {
    if(document.helpform.elements[i].getAttribute("type") == "checkbox") {
       if (document.helpform.elements[i].checked) {
           foundit = true;
           break;
       }
    }
   }
 if ( foundit )
 {
 return (confirm("{$this->idsadmin->lang('AskDelete')}"));
 }
 return foundit;
}
</script>

<form name="helpform" method="post" action="index.php?act=admin&amp;do=delhelp&amp;helpdb={$helpdb}" onsubmit="return testhelp()">
<table width="100%" border="0" cellspacing="1" cellpadding="1">
<tr class="tblheader" align="center">
<td>{$this->idsadmin->lang("act")}</td>
<td>{$this->idsadmin->lang("do")}</td>
<td>{$this->idsadmin->lang("description")}</td>
<td>{$this->idsadmin->lang("delete")}</td>
</tr>

EOF;
return $HTML;
    }#end help_header

    function help_row($data,$cnt=0,$helpdb=0)
    {
        $HTML = "";
        $HTML .= <<<EOF
<tr>
<td class="row{$cnt}">
<a href="index.php?act=admin&amp;do=edithelp&amp;helpnum={$data['num']}&amp;helpdb={$helpdb}" title="{$this->idsadmin->lang('EditHelp')}">{$data['helpact']}</a>
</td>
<td class="row{$cnt}">{$data['helpdo']}</td>
<td class="row{$cnt}">{$data['desc']}</td>
<td class="row{$cnt}" align="center"><input type="checkbox" name="{$data['num']}"/></td>
</tr>
EOF;
return $HTML;
    }#end help_row

    function help_footer()
    {
        $HTML = "";
        $HTML  .=<<<EOF
<tr>
  <td colspan="5" class="formsubtitle" align="right">
    <input type="submit" class="button" value="{$this->idsadmin->lang("deleteselected")}" name="deletehelp"/>
  </td>
</tr>
</table>
</form>
EOF;
return $HTML;
    }#end help_footer
    
    /**
     * Javascript function for testing connections.
     */
    function testConnectionJscript()
    {
        $this->idsadmin->load_lang("misc_template");
	    $HTML = <<< EOF
<script type="text/javascript">
function testconnection()
{
var username=document.connform.USERNAME.value;
var password=document.connform.PASSWORD.value;
var server=document.connform.SERVER.value;
var host=document.connform.HOST.value;
var port=document.connform.PORT.value;
var idsprotocol=document.connform.IDSPROTOCOL.value;
var envvars=document.connform.envvars.value;
envvars = unserialize(envvars);

if ( username == "" )
{
document.connform.USERNAME.focus();
alert("{$this->idsadmin->lang('RequiresUser')}");
return false;
}

if ( server == "" )
{
document.connform.SERVER.focus();
alert("{$this->idsadmin->lang('RequiresInformixServer')}");
return false;
}


if ( host == "" )
{
document.connform.HOST.focus();
alert("{$this->idsadmin->lang('RequiresHost')}");
return false;
}

if ( port == "" )
{
document.connform.PORT.focus();
alert("{$this->idsadmin->lang('RequiresPort')}");
return false;
}

document.getElementById('connectioninfo').innerHTML = " <center>{$this->idsadmin->lang('TestingConnection')}</center>";

      parameters = "USERNAME="+encodeURI(username);
      parameters += "&PASSWORD="+encodeURIComponent(password);
      parameters += "&SERVER="+encodeURI(server);
      parameters += "&HOST="+encodeURI(host);
      parameters += "&PORT="+encodeURI(port);
      parameters += "&IDSPROTOCOL="+encodeURI(idsprotocol);
      parameters += "&ENVVARS="+encodeURI(envvars);

      if ( window.XMLHttpRequest) { 
         http_request = new XMLHttpRequest();
         if (http_request.overrideMimeType) {
            http_request.overrideMimeType('text/xml');
         }
      } else if (window.ActiveXObject) { // IE
         try {
            http_request = new ActiveXObject("Msxml2.XMLHTTP");
         } catch (e) {
            try {
               http_request = new ActiveXObject("Microsoft.XMLHTTP");
            } catch (e) {}
         }
      }
      if (!http_request) {
         alert('Cannot create XMLHTTP instance');
         return false;
      }

http_request.onreadystatechange = function() {
      if (http_request.readyState == 4) {
         if (http_request.status == 200) {
            result = http_request.responseText;
            if (result == "Online")
            {
                document.getElementById('connectioninfo').innerHTML = "<div class='borderwrapgreen'><center>"+result+"<center></div></div>";
            } else {
                document.getElementById('connectioninfo').innerHTML = "<div class='borderwrapred'><center>"+result+"<center></div>";
            }
         } else {
            document.getElementById('connectioninfo').innerHTML = "<div class='borderwrapred'>{$this->idsadmin->lang('ErrorF')} "+http_request.status+"</div>";            
         }
      }
};
      http_request.open('POST', "index.php?act=admin&do=testconn", true);
      http_request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
      http_request.setRequestHeader("Content-length", parameters.length);
      http_request.setRequestHeader("Connection", "close");
      http_request.send(parameters);
}
</script>
EOF;
        return $HTML;
    }
    
    /**
     * Javascript functions used for adding and deleting connection env variables.
     */
    function connectionEnvVarsJscript()
    {
	    $HTML = <<< EOF
<script type="text/javascript">
/**
 * Unserialize PHP associative array 
 * Example:
 * serialized associative array:  a:2:{s:4:"KEY1";s:4:"VAL1";s:4:"KEY2";s:4:"VAL2";}
 * unserialized format:           KEY1=VAL1;KEY2=VAL2;
 */
function unserialize(serialized_array)
{
   var unserialized = "";
   
   // get number of keys in array
   serialized_array = serialized_array.substring(2);
   var num = serialized_array.substring(0,serialized_array.indexOf(":"));
   serialized_array = serialized_array.substring(serialized_array.indexOf(":")+2,serialized_array.length -1);
   
   // extract key and value pairs and append to the unserialized string 
   var index=0;
   var tmp_serialized_array = serialized_array.split(";");    
   for (i=0; i<num; i++)
   {
      var key = tmp_serialized_array[index++];
      key = key.substring(2);
      key = key.substring(key.indexOf(":")+2,key.length-1);
      var value = tmp_serialized_array[index++];
      value = value.substring(2);
      value = value.substring(value.indexOf(":")+2,value.length-1);
      
      unserialized += key + "=" + value + ";";
   }

   return unserialized;
}

/*
 * addenvvar(): Add new connection env variable
 * - Does add env var processing on client side, so that the connection
 *   form does not have to be posted until the user is ready to save.
 * - Inserts the new env variable into the serialized variable "envvars".
 * - Adds the new row to the env vars table on the page.
 */
function addenvvar() {
    var name = document.getElementById("envvarname").value;
	var value = document.getElementById("envvarvalue").value;
    if (name == "" || value == "")
	{
		alert("{$this->idsadmin->lang('InvalidEnvVar')}");
		return;
	}    

    // Mark env variables as modified, so they are saved when form is submitted
    document.getElementById("envvar_modified").value = 1;

    // Insert new env variable into the serialized variable "envvars" 
    // which stores all env vars for the connection.  Need to parse the
    // "envvars" string to add the new env var in serialized form.
    var serialized_envvars = document.getElementById("envvars").value;
    var newenvvar = "s:" + name.length + ":\"" + name + "\";";
    newenvvar += "s:" + value.length + ":\"" + value + "\";";
    var serialized_array = serialized_envvars.split(":");
	var newcount = parseInt(serialized_array[1]) + 1;
    serialized_array[1] = newcount + '';
	serialized_envvars = serialized_array.join(":");
    serialized_envvars = serialized_envvars.substring(0, serialized_envvars.length - 1) + newenvvar + "}";
    document.getElementById("envvars").value = serialized_envvars;

    // Add new row in the env vars table
    var newrowid = "envvar_" + (new Date()).getTime();    
	if (browserIsIE()) {
    	var newRow = document.createElement("<tr id='" + newrowid + "'></tr>");
    	var newCell1 = document.createElement("<td id='" + newrowid + "_name'></td>");
    	newCell1.appendChild(document.createTextNode(name));
    	var newCell2 = document.createElement("<td id='" + newrowid + "_value'>" + value + "</td>");
    	newCell2.appendChild(document.createTextNode(value));
    	var newCell3 = document.createElement("<td style='text-align:center;'></td>");
    	newCell3.appendChild(document.createElement("<input type='button' "
	        + "class='button' name='DeleteEnvVar' value='{$this->idsadmin->lang('delete')}' "
    	    + "onClick='deleteenvvar(\"" + newrowid + "\");' />"));
    	newRow.appendChild(newCell1);
    	newRow.appendChild(newCell2);
    	newRow.appendChild(newCell3);
    	document.getElementById("envtbody").appendChild(newRow);
    	
    	// for IE, we need to also clear out the name and value from the input boxes
    	document.getElementById("envvarname").value = "";
		document.getElementById("envvarvalue").value = "";
	} else {
	    // works for Firefox and Safari but IE has problems with innerHTML 
	 	var newenvrow = "<tr id='" + newrowid + "'>";
    	newenvrow += "<td id='" + newrowid + "_name'>" + name + "</td>";
    	newenvrow += "<td id='" + newrowid + "_value'>" + value + "</td>"; 
    	newenvrow += "<td style='text-align:center;'>";
    	newenvrow += "<input type='button' class='button' name='DeleteEnvVar' "  
    	  + "value='{$this->idsadmin->lang('delete')}' onClick='deleteenvvar(\"" + newrowid + "\");' /></td></tr>";
	    
	    document.getElementById("envtbody").innerHTML += newenvrow;
    }
}

/*
 * deleteenvvar(): Delete connection env variable
 * - Delete the env variable from the serialized variable "envvars".
 * - Delete the row in the env vars table on the page
 */
function deleteenvvar(rowid) {
    // Mark env variables as modified, so they are saved when form is submitted
    document.getElementById("envvar_modified").value = 1;

    // Delete env variable from the serialized variable "envvars" 
    // which stores all env vars for the connection.  Need to parse the
    // "envvars" string to remove the new env var from serialized form.
    var name = document.getElementById(rowid + "_name").innerHTML;
    var value = document.getElementById(rowid + "_value").innerHTML;
    var serialized_envvars = document.getElementById("envvars").value;
    var serialized_array = serialized_envvars.split(":");
	var newcount = parseInt(serialized_array[1]) - 1;
    serialized_array[1] = newcount + '';
	serialized_envvars = serialized_array.join(":");
    var index = serialized_envvars.indexOf("\"" + name + "\"");    
    var start_offset =  -3 - ((name.length + '').length);
    var end_offset =  name.length + 9 + value.length + ((value.length + '').length);  
    serialized_envvars = serialized_envvars.substring(0, index + start_offset) +
	    serialized_envvars.substring(index + end_offset);
    document.getElementById("envvars").value = serialized_envvars;

    // Delete the row in the env vars table
	var nodeToBeRemoved = document.getElementById(rowid);
	nodeToBeRemoved.parentNode.removeChild(nodeToBeRemoved);
}

</script>
EOF;
        return $HTML;
    }    

} // end class template_admin
?>
