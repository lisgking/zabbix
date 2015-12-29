<?php
/*
 **************************************************************************   
 *  (c) Copyright IBM Corporation. 2008, 2012.  All Rights Reserved
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


class template_switchuser{
    public $idsadmin;

    function error($err_string="")
    {
        $HTML = "";
        $HTML .= $this->idsadmin->template["template_global"]->global_error($err_string);
        return $HTML;
    }

    function showForm()
    {

        $HTML = "";
        $HTML .= <<<EOF
<form name="login" action="index.php?act=switchuser&amp;do=dologin" method="post">
<table>
<tr>
	<td colspan="2" align="center">
	{$this->idsadmin->lang('loginprompt')}
	</td>
</tr>
<tr>
   <td>{$this->idsadmin->lang('username')}</td>
   <td>
      <input type="text" name="username"/>
   </td>
</tr>

<tr>
   <td>{$this->idsadmin->lang('password')}</td>
   <td>
      <input type="password" name="passwd" autocomplete="off"/>
   </td>
</tr>
<tr>
   <td colspan="2" align='center'>
      <input type="submit" class="button" name="Submit" value="{$this->idsadmin->lang("login")}"/>
   </td>
</tr>
</table>
</form>
EOF;

	return $HTML;
	}
	
	function sqltoolbox_logout_jscript()
	{
	    $this->idsadmin->load_lang("global");
	    $HTML = <<< EOF
<script type="text/javascript">
function sqltoolbox_logout()
{
	c = confirm("{$this->idsadmin->lang("confirmSqltoolboxLogout")}");
	
	if (c)
	{
		document.form_logoutbutton.submit();
	}
}
</script>
EOF;
        return $HTML;
	}
	
}

?>
