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


class template_login {
    public $idsadmin;

    function error($err_string="")
    {
        $HTML = "";
		if($err_string != "")
        	$HTML .= $this->idsadmin->template["template_global"]->global_error($err_string);
        return $HTML;
    }

    function showForm()
    {
        $this->idsadmin->load_lang("misc_template");
        $HTML = $this->setupLoginTabs("login");
        $HTML .= <<<EOF
<form name="login" action="index.php?act=login&amp;do=dologin" method="post">
<table border="0" role="presentation" cellpadding="10" class="borderwrapwhite" width="800">
<tr>
  <td valign="top">{$this->idsadmin->lang("login_instructions")}</td>
</tr>
<tr>
   <td valign="top">
   {$this->showQuickLoginForm()}
   </td>
</tr>
<tr>
   <td valign="top">
   {$this->showLoginForm()}
   </td>
</tr>
<tr>
   <td><div id="connectioninfo" style="text-align:center" /></td>
</tr>
<tr>
   <td style="text-align: center; font-weight: bold;">{$this->showHelpLinks()}</td>
</tr>
</table>
</form>

  


EOF;
return $HTML;
    }
    
    function setupLoginTabs ($active="login") 
    {
    	require_once ROOT_PATH."/lib/tabs.php";
    	$t = new tabs($this->idsadmin);
    	$t->addtab("index.php",	$this->idsadmin->lang("Login"), 1);
    	$t->addtab("admin/index.php", $this->idsadmin->lang("Admin"), 0);
    	return $t->tohtml();
    }

    function showgroups($grps)
    {
        $HTML = "";
        $selected="";
        foreach ($grps as $k => $v )
        {
            if ($v['GROUP_NUM']==1)
            {
                $selected="selected='selected'";
            } else {
                $selected="";
            }
            if ($v['GROUP_NAME']=="Default")
            {
                // Localize "Default" to the user's language
                $grpname = $this->idsadmin->lang("Default");
            } else {
                // If it's a user entered group name, display as is
                $grpname = $v['GROUP_NAME'];
            }

            $HTML .=  "<option {$selected} value='{$v['GROUP_NUM']}'>{$grpname}</option>";
        }
        return $HTML;
    }#end showgroups

    function showconns($conns)
    {

        $HTML = "";
        $HTML .=  "<option value='0' selected='selected'>{$this->idsadmin->lang("selectaconnection")}</option>";
        foreach ($conns as $k => $v )
        {
            $HTML .=  "<option {$selected} value='{$v['CONN_NUM']}'>{$v['SERVER']}@{$v['HOST']}</option>";
        }
        return $HTML;
    } // end showconns

    function showQuickLoginForm()
    {
        $HTML = "";
        require_once ROOT_PATH."/lib/connections.php";
        $db = new connections($this->idsadmin);
        $stmt = $db->db->query("select * from groups order by group_name");

        $grps = $stmt->fetchAll();

        $HTML .= <<<EOF
<script type ="text/javascript">

function resetLoginForm()
{
document.login.informixserver.value = '';
document.login.host.value = '';
document.login.port.value = '';
document.login.username.value = '';
document.login.userpass.value = '';
document.login.idsprotocol.value = 'onsoctcp';
}

function connectgroup()
{
  var group_num = 
    document.login.groups.options[document.login.groups.selectedIndex].value;
  var group_pass = document.login.grouppass.value;
  var params = "group_num=" + group_num + "&group_pass=" + escape(group_pass);

  if (window.XMLHttpRequest) { 
     request = new XMLHttpRequest();
     if (request.overrideMimeType) {
        request.overrideMimeType('text/html');
     }
  } else if (window.ActiveXObject) { // IE
     try {
        request = new ActiveXObject("Msxml2.XMLHTTP");
      } catch (e) {
        try {
           request = new ActiveXObject("Microsoft.XMLHTTP");
        } catch (e) {}
     }
  }
  request.open("POST", "index.php?act=login&do=connectgroup");
  request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
  request.setRequestHeader("Content-length", params.length);
  request.setRequestHeader("Connection", "close");
  
  request.onreadystatechange = function() {
    if (request.readyState == 4) {
       if (request.status == 200) {
          result = request.responseText;
          document.getElementById('connectionlist').innerHTML = result;
       }
       else {
          document.getElementById('connectionlist').innerHTML = "Error:"+request.status+" occurred";
       }
    }
  }
  request.send(params);
  return true;
}

// given a specific connection id, populate the server connection details
function populateconnection(con)
{
    var conn_num = con.options[con.selectedIndex].value;
    var group_pass = document.login.grouppass.value;
    var params = "conn_num=" + conn_num + "&group_pass=" + escape(group_pass); 

  if (window.XMLHttpRequest) { 
     request = new XMLHttpRequest();
     if (request.overrideMimeType) {
        request.overrideMimeType('text/xml');
     }
  } else if (window.ActiveXObject) { // IE
     try {
        request = new ActiveXObject("Msxml2.XMLHTTP");
      } catch (e) {
        try {
           request = new ActiveXObject("Microsoft.XMLHTTP");
        } catch (e) {}
     }
  }
  
  request.open("POST", "index.php?act=login&do=popserver");
  request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
  request.setRequestHeader("Content-length", params.length);
  request.setRequestHeader("Connection", "close");

  request.onreadystatechange = function() {
    if (request.readyState == 4) {
       if (request.status == 200) {
         resetLoginForm();
         var xmlDoc = request.responseXML.documentElement;
         var conn =  xmlDoc.getElementsByTagName("connection");

         if ( xmlDoc.getElementsByTagName("server").item(0).firstChild == null ) 
		 {
		     return;
		 }
         
         document.login.informixserver.value = 
            xmlDoc.getElementsByTagName("server").item(0).firstChild.data;
         document.login.host.value = 
            xmlDoc.getElementsByTagName("host").item(0).firstChild.data;
         document.login.port.value = 
            xmlDoc.getElementsByTagName("port").item(0).firstChild.data;
         document.login.username.value = 
            xmlDoc.getElementsByTagName("username").item(0).firstChild.data;
         if (xmlDoc.getElementsByTagName("password").item(0).firstChild != null)
         {
            document.login.userpass.value = 
          	  xmlDoc.getElementsByTagName("password").item(0).firstChild.data;
         }
         if (xmlDoc.getElementsByTagName("idsprotocol").item(0).firstChild != null)
         {	
         	document.login.idsprotocol.value = 
            	xmlDoc.getElementsByTagName("idsprotocol").item(0).firstChild.data;
         }
         document.login.conn_num.value = 
            xmlDoc.getElementsByTagName("conn_num").item(0).firstChild.data;
       }
       else {
          document.getElementById('connectionlist').innerHTML = "Error:"+request.status+" occurred";
       }
    }
  }
  request.send(params);
  return true;
}

function getConns(what,idx)
{
	document.getElementById('connectionlist').innerHTML = '<select style="width:138px;" disabled="disabled" />';
	document.login.grouppass.value='';
resetLoginForm();
}

</script>

<table class="login" cellspacing="0" cellpadding="0">
<tr>
<th class="formheader" colspan="2">{$this->idsadmin->lang('quicklogin')}</th>
</tr>
<tr>
    <td class="formright_nl" colspan="2" height="30">{$this->idsadmin->lang('quicklogin_instructions')}</td>
</tr>
<tr>
    <td class="formleft_nl">{$this->idsadmin->lang('group')}</td>
    <td class="formright_nl">
        <select name="groups" onchange="getConns('groups',document.login.groups.selectedIndex)">
        {$this->showgroups($grps)}
        </select>
    </td>
</tr>
<tr>
   <td class="formleft_nl">{$this->idsadmin->lang('password')}</td>
   <td class="formright_nl">
       <input type="password" name="grouppass" autocomplete="off" value="{$this->idsadmin->phpsession->instance->get_passwd()}"/>
             <input type="submit"
          onclick="connectgroup();return false;" class="button" 
          value="{$this->idsadmin->lang("getservers")}" />
   </td>
</tr>
<tr>
   <td class="formleft_nl">{$this->idsadmin->lang('server')}</td>
   <td class="formright_nl">
   		<div id="connectionlist">
   			<select style="width:138px;" disabled="disabled" />
        </div>
    </td>
</tr>
</table>
EOF;
return $HTML;
    }

    function showLoginForm()
    {
        $protocol = $this->idsadmin->phpsession->instance->get_idsprotocol();
        switch ( $protocol )
        {
            case "onsoctcp":
                $onsocSELECTED="selected='selected'";
                break;
            case "ontlitcp":
                $ontliSELECTED="selected='selected'";
                break;
            case "onsocssl":
             	$onsslSELECTED="selected='selected'";
              	break;
        }
        
        $HTML = "";
        $HTML .= <<<EOF

<script type='text/javascript'>
function doLogin()
{
	// First test if cookies are supported.  If yes, proceed with login.  
	// If not, we won't be able to login so give the user an error.
	document.cookie = "cookies=true";
	if (document.cookie)
	{
		document.login.submit();
    } else {
    	alert ("{$this->idsadmin->lang('cookies_required')}");
    }
}

/**
 * Test connection
 *
 * @param login - If true, test connection and then automatically login if the
 *                connection is successful.  If false, just test connection.
 */
function testconnection(login)
{
	var username=document.login.username.value;
	var password=document.login.userpass.value;
	var server=document.login.informixserver.value;
	var host=document.login.host.value;
	var port=document.login.port.value;
	var idsprotocol=document.login.idsprotocol.value;
	
	if ( server == "" )
	{
		document.login.informixserver.focus();
		alert("{$this->idsadmin->lang('missinginformixserver')}");
		return false;
	}
	
	if ( host == "" )
	{
		document.login.host.focus();
		alert("{$this->idsadmin->lang('missinghost')}");
		return false;
	}
	
	if ( port == "" )
	{
		document.login.port.focus();
		alert("{$this->idsadmin->lang('missingport')}");
		return false;
	}
	
	if ( username == "" )
	{
		document.login.username.focus();
		alert("{$this->idsadmin->lang('no_user')}");
		return false;
	}
	
	if (login)
	{
		document.getElementById('connectioninfo').innerHTML = " <center>{$this->idsadmin->lang('logging_in')}</center>";
	} else {
		document.getElementById('connectioninfo').innerHTML = " <center>{$this->idsadmin->lang('testing_connection')}</center>";
    }

	var parameters = "USERNAME="+encodeURI(username);
	parameters += "&PASSWORD="+encodeURIComponent(password);
	parameters += "&SERVER="+encodeURI(server);
	parameters += "&HOST="+encodeURI(host);
	parameters += "&PORT="+encodeURI(port);
	parameters += "&IDSPROTOCOL="+encodeURI(idsprotocol);

	if ( window.XMLHttpRequest) 
	{ 
		http_request = new XMLHttpRequest();
		if (http_request.overrideMimeType) 
		{
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
	
	if (!http_request) 
	{
		alert('Cannot create XMLHTTP instance');
		return false;
	}

	if (login)
	{
		http_request.onreadystatechange = function() {
			if (http_request.readyState == 4) {
				if (http_request.status == 200) {
					result = http_request.responseText;
					if (result == "Online")
					{
						doLogin();
					} else {
						document.getElementById('connectioninfo').innerHTML = "<div class='borderwrapred'><center>"+result+"<center></div>";
					}            
				} else {
					document.getElementById('connectioninfo').innerHTML = "<div class='borderwrapred'><center>{$this->idsadmin->lang('ErrorF')} "+http_request.status+"<center></div>";            
				}
			}
		};
	} else {
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
					document.getElementById('connectioninfo').innerHTML = "<div class='borderwrapred'><center>{$this->idsadmin->lang('ErrorF')} "+http_request.status+"<center></div>";            
				}
			}
		};
	}

	http_request.open('POST', "index.php?act=login&do=testconn", true);
	http_request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
	http_request.setRequestHeader("Content-length", parameters.length);
	http_request.setRequestHeader("Connection", "close");
	http_request.send(parameters);
}
</script>
<table class="login" cellspacing="0" cellpadding="0">
<tr>
<th class="formheader" colspan="6">{$this->idsadmin->lang('serverdetails')}</th>
</tr>
<tr>
	<td class="formright_nl" colspan="6" height="30">{$this->idsadmin->lang('serverdetails_instructions')}</td>
</tr>

<tr>
   <td class="formleft_nl">{$this->idsadmin->lang("informixserver")}</td>
   <td class="formright_nl">
      <input type="text" name="informixserver" value="{$this->idsadmin->phpsession->instance->get_servername()}"/>
   </td>
   <td width="5%" class="formleft_nl"/>
   <td class="formleft_nl">{$this->idsadmin->lang('hostname')}</td>
   <td class="formright_nl">
      <input type="text" name="host" value="{$this->idsadmin->phpsession->instance->get_host()}"/>
   </td>
   <td width="5%" class="formleft_nl"/>
</tr>

<tr>
   <td class="formleft_nl">{$this->idsadmin->lang('port')}</td>
   <td class="formright_nl">
      <input type="text" name="port" value="{$this->idsadmin->phpsession->instance->get_port()}"/>
   </td>
   <td width="5%" class="formleft_nl"/>
   <td class="formleft_nl">{$this->idsadmin->lang("idsprotocol")}</td>
    <td class="formright_nl">
        <select name="idsprotocol">
            <option value="onsoctcp" $onsocSELECTED>onsoctcp</option>
            <option value="ontlitcp" $ontliSELECTED>ontlitcp</option>
            <option value="onsocssl" $onsslSELECTED>onsocssl</option>
        </select>
    </td>
   <td width="5%" class="formleft_nl"/>
</tr>

<tr>
   <td class="formleft_nl">{$this->idsadmin->lang('username')}</td>
   <td class="formright_nl">
      <input type="text" name="username" value="{$this->idsadmin->phpsession->instance->get_username()}"/>
   </td>
   <td width="5%" class="formleft_nl"/>
   <td class="formleft_nl">{$this->idsadmin->lang('password')}</td>
   <td class="formright_nl">
       <input type="password" name="userpass" autocomplete="off" value="{$this->idsadmin->phpsession->instance->get_passwd()}"/>
   </td>
    <td width="5%" class="formleft_nl"/>
</tr>

<tr sytle="display:none">
   <td sytle="display:none"></td>
   <td sytle="display:none"><input type="hidden" name="conn_num" value="{$this->idsadmin->phpsession->instance->get_conn_num()}" />
   </td>
</tr>
<tr>
   <td colspan="6" align='right'>
      <input type="button" class="button" name="Submit" value="{$this->idsadmin->lang("test_connection")}" onclick="testconnection()"  />
      <input type="button" class="button" name="Submit" value="{$this->idsadmin->lang("log_in")}"  onclick="testconnection(true)" />
   </td>
</tr>
</table>

EOF;
        return $HTML;
    }
    
    function showHelpLinks()
    {
    	$HTML = <<<EOF
<script type="text/javascript">
function popAboutOAT()
{
	w = window.open( 'index.php?act=help&do=aboutOAT','AboutOAT','width=500,height=350,resizable=yes,scrollbars=yes');
        w.focus();
}
</script>
&middot;
<a href="javascript:popAboutOAT()">{$this->idsadmin->lang('aboutOAT')}</a>
&middot;
<a href="javascript:showDocuments('HOWTO.html','HowDoI'); "  title="{$this->idsadmin->lang('HowDoI')}">{$this->idsadmin->lang('HowDoI')}</a>
&middot;
EOF;
		return $HTML;
    }

}

?>
