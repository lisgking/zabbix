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
 *  See the License for the specific language governing permissions andab
 *  limitations under the License.
 **************************************************************************
 */

/**
 * Permissions Manager
 */
class privileges {

    public $idsadmin;
    private $publicUser;
	private $openPorts;
	private $pamPorts;
    private $dbname;

    // Array of available table-level privileges
    private $tabprivileges = Array("select", "update", "insert", "delete",
    		"index", "alter", "references", "under");

	private $WINDOWS = "Windows";
    # the 'constructor' function

    function __construct(&$idsadmin)
    {
        $this->idsadmin = &$idsadmin;
        $this->idsadmin->load_template("template_privileges");
        $this->idsadmin->load_lang("privileges");
        $this->idsadmin->html->set_pagetitle($this->idsadmin->lang("UserPrivileges"));
    }

    /*
     * the run function
     * this is what index.php will call
     * the decision of what to actually do is based on
     * the value of 'act' which is either posted or getted
     */

    function run()
    {
    	$this->idsadmin->setCurrMenuItem("privileges");

    	// If no dbname, default to the one set in the SQL Toolbox (if any)
   		if (isset($this->idsadmin->in['dbname']))
		{
		    $this->dbname = $this->idsadmin->in['dbname'];
		} else {
			$this->dbname = $this->idsadmin->phpsession->get_sqldbname();
		}
		$dbselect = $this->createDatabaseSelect();
        $this->idsadmin->html->add_to_output($this->setuptabs($this->idsadmin->in['do']));

    	switch($this->idsadmin->in['do'])
        {
        	case 'database';
        		if (isset($this->idsadmin->in['grantuser']))
        		{
        			$this->execGrantUserPrivilege();
        		}
        		if (isset($this->idsadmin->in['save']))
        		{
        			$this->execModifyUserPrivilege();
        		}
     	        $this->showPrivilegesPageHeader($dbselect);
     	        $this->databasePrivileges();
                break;

            case 'table';
            	if (isset($this->idsadmin->in['granttable']))
        		{
        			$this->execGrantTablePrivilege();
        		}
                if (isset($this->idsadmin->in['save']))
        		{
        			$this->execModifyTablePrivilege();
        		}
				$this->showPrivilegesPageHeader($dbselect);
				$this->tablePrivileges();
        	    break;

        	case 'roles';
           		if (isset($this->idsadmin->in['createrole']))
        		{
        			$this->execCreateNewRole();
        		}
        		$this->showPrivilegesPageHeader($dbselect);
				$this->showRoles();
				break;

			case 'nonosusers';
				$this->showInternalUsers();
				break;
				
			case 'admin';
				$this->showAdminPrivileges();
				break;

           default:
                $this->idsadmin->error("{$this->idsadmin->lang('InvalidURL_do_param')}");
          		break;
        }

    } // end function run

    /**
	 *Creates the HTML for the tabs at the top of a page
	 *
	 * @param string $active		The current active tab
	 * @return HTML to create the tabs
	 */
	function setuptabs($active)
	{
		if (!isset($active) || $active == "")
		{
			$active = "database";
		}

		require_once ROOT_PATH."/lib/tabs.php";
		$t = new tabs($this->idsadmin);
		$t->addtab("index.php?act=privileges&amp;do=database",
			$this->idsadmin->lang("DBPrivileges"),
			($active == "database") ? 1 : 0 );
		$t->addtab("index.php?act=privileges&amp;do=table",
			$this->idsadmin->lang("TablePrivileges"),
			($active == "table") ? 1 : 0 );
		/*$t->addtab("index.php?act=privileges&amp;do=roles",
			$this->idsadmin->lang("Roles"),
			($active == "roles") ? 1 : 0 );*/

		if (  Feature::isAvailable ( Feature::CENTAURUS, $this->idsadmin->phpsession->serverInfo->getVersion()  )  )
		{
			// SQL Admin API user privileges available only for server versions 12.10 or higher.
			$t->addtab("index.php?act=privileges&amp;do=admin",
				$this->idsadmin->lang("AdminPrivileges"),
				($active == "admin") ? 1 : 0 );
		}
		
		if (  Feature::isAvailable ( Feature::PANTHER, $this->idsadmin->phpsession->serverInfo->getVersion()  )  )
		{
			//Here we make sure we are not including the non os users feature in case the server's OS is windows.
			//This check must be removed once Windows gets supported (non os users)
			if($this->getServerOs() != $this->WINDOWS)
			{
				$t->addtab("index.php?act=privileges&amp;do=nonosusers",
					$this->idsadmin->lang("NonOsUsers"),
					($active == "nonosusers") ? 1 : 0 );
			}
		}

		#set the 'active' tab.
		$html  = ($t->tohtml());
		$html .= "<div class='borderwrapwhite'>";
		return $html;
	} #end setuptabs

    /**
     * Displays the header of the privileges manager pages.  
     * Also displays the drop-down that allows the user to select a database.
     */
    function showPrivilegesPageHeader($dbselect)
    {
		$html.=<<<END
<script type="text/javascript">
function privilegesSwitch(page)
{
window.location= "index.php?act=privileges&do=" + page + "&dbname={$this->dbname}";
return;
}
</script>

<div class="tabpadding">
  <div class="borderwrap">
<table class='gentab_nolines'>
  <tr>
    <td class='tblheader' style='text-align:center'>{$this->idsadmin->lang("ManagePrivileges")}</td>
  </tr>
  <tr style='text-align:center'>
    <th>{$this->idsadmin->lang("Database")}: $dbselect</th>
  </tr>
  </table>
END;

    	$this->idsadmin->html->add_to_output($html);
    }

    /**
     * Database-Level Privileges view
     */
    function databasePrivileges ()
    {
	    require_once ROOT_PATH."lib/gentab.php";
		$tab = new gentab($this->idsadmin);
		
		if (!$this->idsadmin->isreadonly()) 
		{
			$this->showGrantUserPrivilege();
		}

		// Display table of user privileges for dbname
       	$db = $this->idsadmin->get_database($this->dbname);
  		$qry = "select username as name, " .
            "CASE " .
            " WHEN (usertype='C')" .
            "   THEN 'CONNECT' " .
            " WHEN (usertype='D')" .
            "   THEN 'DBA' " .
            " WHEN (usertype='R')" .
            "   THEN 'RESOURCE' " .
            "END  as usertype, " .
            "defrole, " .
            "'{$this->dbname}' as dbname " .
  		    "from {$this->dbname}:sysusers " .
  		    "where usertype<>'G'";

        $qrycnt = "select count(*) from {$this->dbname}:sysusers where usertype<>'G'";

        $tab->display_tab_by_page($this->idsadmin->lang("DBPrivileges"),
            array("1" => $this->idsadmin->lang("User"),
			      "2" => $this->idsadmin->lang("Privilege"),
			      "3" => $this->idsadmin->lang("DefRole"),),
			$qry, $qrycnt, NULL, "gentab_db_privileges.php",$db);
		
		$this->idsadmin->html->add_to_output("</div></div>");
    }

    /**
     * Table-Level Privileges view
     */
    function tablePrivileges ()
    {
		require_once ROOT_PATH."lib/gentab.php";
		$tab = new gentab($this->idsadmin);
		
		if (!$this->idsadmin->isreadonly()) 
		{
			$this->showGrantTablePrivilege();
		}
		
		// Display table-level privileges for dbname
		$db = $this->idsadmin->get_database($this->dbname);
		$qry = "select trim(b.owner)||'.'||trim(b.tabname) AS tabname, " .
			"a.grantee, a.grantor, a.tabauth, a.tabid, " .
			"'{$this->dbname}' as dbname " .
			"from {$this->dbname}:systabauth as a, ".
			"{$this->dbname}:systables as b " .
			"where a.tabid = b.tabid and b.tabname not like 'sys%' " .
			"order by tabname, a.grantee, a.grantor";

		$qrycnt = "select count(*) from {$this->dbname}:systabauth as a, " .
			"{$this->dbname}:systables as b " .
			"where a.tabid = b.tabid and b.tabname not like 'sys%' ";

		$tab->display_tab_by_page($this->idsadmin->lang("TablePrivileges"),
			array("1" => $this->idsadmin->lang("Tabname"),
				  "2" => $this->idsadmin->lang("User"),
				  "3" => $this->idsadmin->lang("Grantor"),
				  "4" => $this->idsadmin->lang("Privileges"),),
			$qry, $qrycnt, NULL, "gentab_tab_privileges.php",$db);

		$this->idsadmin->html->add_to_output("</div></div>");
    }

    function showRoles()
    {
		require_once ROOT_PATH."lib/gentab.php";
		$tab = new gentab($this->idsadmin);

    	// Display table of all roles
		$db = $this->idsadmin->get_database($this->dbname);
  		$qry = "select username as name " .
  		    "from {$this->dbname}:sysusers " .
  		    "where usertype='G'";

        $qrycnt = "select count(*) from {$this->dbname}:sysusers where usertype='G'";

        $tab->display_tab_by_page($this->idsadmin->lang("Roles"),
            array("1" => $this->idsadmin->lang("Name")),
			$qry, $qrycnt, 20, "gentab_role_privileges.php",$db);

		if (!$this->idsadmin->isreadonly()) {
    		$this->showCreateNewRole();
		}
    }
    
    function showInternalUsers()
    {
    	if (!Feature::isAvailable(Feature::PANTHER, $this->idsadmin->phpsession->serverInfo->getVersion()))
    	{
    		$this->idsadmin->fatal_error($this->idsadmin->lang('InternalUsers_min_server_version'));
    		return;
    	}

    	$this->getDefaultUser();
    	$this->getPAMPorts();
    	$this->idsadmin->template["template_privileges"]->publicUser = $this->publicUser;
    	$this->idsadmin->template["template_privileges"]->openPorts = $this->openPorts;
    	$this->idsadmin->template["template_privileges"]->pamPorts = $this->pamPorts;
    	
    	// If the server version is 12.10 or higher, we can check if the currently logged in user has 
    	// DBSA privileges and is therefore authorized to manage internal users.  The admin_check_auth 
    	// function will return 1 if the user is a DBSA.
    	$authorized = true;
    	if (Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin->phpsession->serverInfo->getVersion()))
    	{
    		$db = $this->idsadmin->get_database("sysadmin");
    		$username = $this->idsadmin->phpsession->instance->get_username();
    		$sql = "select admin_check_auth('{$username}') as auth from sysmaster:sysdual";
    		$stmt = $db->query($sql);
    		$res = $stmt->fetch();
    		$authorized = ($res['AUTH'] == 1);
    	}
    	
        $this->idsadmin->html->add_to_output($this->idsadmin->template["template_privileges"]->render_non_os_users($authorized, $this->idsadmin->phpsession->get_lang()));
    }
    
    function showAdminPrivileges() 
    {
    	if (!Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin->phpsession->serverInfo->getVersion()))
    	{
    		$this->idsadmin->fatal_error($this->idsadmin->lang('AdminPrivileges_min_server_version'));
    		return;
    	}
    	
    	// Check if the currently logged in user is authorized to add/remove SQL Admin API privileges
    	// First check if they are a DBSA using the admin_check_auth function.
    	$db = $this->idsadmin->get_database("sysadmin");
    	$username = $this->idsadmin->phpsession->instance->get_username();
    	$sql = "select admin_check_auth('{$username}') as auth from sysmaster:sysdual";
    	$stmt = $db->query($sql);
    	$res = $stmt->fetch();
    	$authorized = ($res['AUTH'] == 1);
    	
    	// If they are not a DBSA, they still may have authorization by having GRANT privileges in ph_allow.
    	if (!$authorized)
    	{
    		$sql = "select bitand(perms,'0x100000') > 0 as auth from ph_allow where name = '{$username}'";
    		$stmt = $db->query($sql);
    		while ($res = $stmt->fetch())
    		{
    			$authorized = ($res['AUTH'] == 1);
    		}
    	}
    	
    	$this->idsadmin->html->add_to_output($this->idsadmin->template["template_privileges"]->render_admin_privileges($authorized,$this->idsadmin->phpsession->get_lang()));
    }

	function getServerOs()
    {
    	$db = $this->idsadmin->get_database("sysmaster");
		$sql = "SELECT os_name from sysmaster:sysmachineinfo";
    	$stmt = $db->query($sql);
		$res = $stmt->fetch();
		return trim($res['OS_NAME']);
    }

    /**
     * Create a select drop-down of all databases
     */
    function createDatabaseSelect ()
    {
    	$do = (isset($this->idsadmin->in['do']))? $this->idsadmin->in['do']:"database";
    	$select =<<<EOF
<script type="text/javascript">
function privilegesChangeDB(select) {
var dbname = select.options[select.selectedIndex].value;
window.location= "index.php?act=privileges&do={$do}&dbname=" + dbname;
return;
}
</script>
<select onchange='privilegesChangeDB(this)' name='dbname' style='vertical-align: middle'>
EOF;
        $db = $this->idsadmin->get_database("sysmaster");
        $stmt = $db->query("select name from sysdatabases order by name");

        while ($res = $stmt->fetch() )
        {
        	$name = trim($res['NAME']);
        	if ($this->dbname == "")
        	{
        		// if no current dbname, use the first returned by the query as the default
				$this->dbname = $name;
        	}
        	$selected = (strcasecmp($this->dbname,$name)==0)? "selected='selected'":"";
        	$select .= "<option $selected value='{$name}'>{$name}</option>";
        }

        $select .= "</select>";

        return $select;
    }

    /**
     * Create a select drop-down of all non system tables in the selected database
     */
    function createTableSelect ()
    {
    	$tabname = (isset($this->idsadmin->in['tabname']))? $this->idsadmin->in['tabname']:"";
    	$db = $this->idsadmin->get_database($this->dbname);
        $stmt = $db->query("select trim(owner)||'.'||trim(tabname) as tabname from systables ".
            "where tabid >= 100 order by tabname;");
    	$select ="<select name='tabname' style='vertical-align: middle'>";

        while ($res = $stmt->fetch() )
        {
        	$name = trim($res['TABNAME']);
            if ($this->idsadmin->phpsession->instance->get_delimident() == "Y")
            {
                // need to enclose table name in quotes if DELIMIDENT=Y
                $name_r = explode(".",$name);
                $name_r[1] = "\"{$name_r[1]}";
                $name_r[count($name_r) -1 ] = "{$name_r[count($name_r) -1]}\"";
                $name = implode(".",$name_r);
            }
        	$selected = (strcasecmp($name,$tabname)==0)? "selected='selected'":"";
        	$select .= "<option $selected value='{$name}'>{$name}</option>";
        }

        $select .= "</select>";
        return $select;
    }


    /**
     * Show Grant User Privilege input form
     */
    function showGrantUserPrivilege ()
    {
    	$HTML = <<<EOF
<br/>
<form method="post" name="grantUserPriv" action="index.php?act=privileges&amp;do=database&amp;dbname={$this->dbname}&amp;grantuser">
<table>
<tr>
   <th colspan='5'>{$this->idsadmin->lang("GrantNewPriv")}:</th>
</tr>
<tr>
   <th>{$this->idsadmin->lang("Username")}</th>
   <td><input type='text' name="username" value="" /></td>
   <th>{$this->idsadmin->lang("Privilege")}</th>
   <td><select name='newpriv'>
       <option value="Connect">{$this->idsadmin->lang('Connect')}</option>
       <option value="Resource">{$this->idsadmin->lang('Resource')}</option>
       <option value="DBA">{$this->idsadmin->lang('DBA')}</option>
      </select></td>
   <td><input type='submit' class='button' name='grantuser' value='{$this->idsadmin->lang("Grant")}'
        onclick='grantUserPriv.submit()' /></td>
</tr>
</table>
</form>
EOF;
        $this->idsadmin->html->add_to_output($HTML);
    }

    /**
     * Show Grant Table Privilege input form
     */
    function showGrantTablePrivilege ()
    {
        if ($this->idsadmin->isreadonly())
    	{
    		$this->idsadmin->error($this->idsadmin->lang("NoPermission"));
    	}

    	$table_select = $this->createTableSelect();
    	$username = (isset($this->idsadmin->in['username']))? $this->idsadmin->in['username']:"";

    	$checked = Array();
    	foreach ($this->tabprivileges as $level)
		{
			$checked["$level"] = (isset($this->idsadmin->in["{$level}_box"]))? "CHECKED":"";
		}

    	$HTML = <<<EOF
<br/>
<form method="post" name="grantTablePriv" action="index.php?act=privileges&amp;do=table&amp;dbname={$this->dbname}&amp;granttable">
<table>
<tr>
   <th colspan='5'>{$this->idsadmin->lang("GrantNewPriv")}:</th>
</tr>
<tr>
   <th>{$this->idsadmin->lang("Username")}</th>
   <td><input type='text' name="username" value="$username" /></td>
   <th>{$this->idsadmin->lang("Tabname")}</th>
   <td>$table_select</td>
<tr>
</tr>
    <td colspan='4'>
<table class='gentab' >
<tr>
<td class='color_select'>S</td><td><input type='checkbox' name='select_box' {$checked['select']}/>{$this->idsadmin->lang('Select')}</td>
<td class='color_update'>U</td><td><input type='checkbox' name='update_box' {$checked['update']}/>{$this->idsadmin->lang('Update')}</td>
<td class='color_insert'>I</td><td><input type='checkbox' name='insert_box' {$checked['insert']}/>{$this->idsadmin->lang('Insert')}</td>
<td class='color_delete'>D</td><td><input type='checkbox' name='delete_box' {$checked['delete']}/>{$this->idsadmin->lang('Delete')}</td>
</tr>
<tr>
<td class='color_index'>X</td><td><input type='checkbox' name='index_box' {$checked['index']}/>{$this->idsadmin->lang('Index')}</td>
<td class='color_alter'>A</td><td><input type='checkbox' name='alter_box' {$checked['alter']}/>{$this->idsadmin->lang('Alter')}</td>
<td class='color_ref'>R</td><td><input type='checkbox' name='references_box' {$checked['references']}/>{$this->idsadmin->lang('References')}</td>
<td class='color_under'>N</td><td><input type='checkbox' name='under_box' {$checked['under']}/>{$this->idsadmin->lang('Under')}</td>
</tr>
</td></tr>
</table>
   </td>
   <td><input type='submit' class='button' name='granttable' value='{$this->idsadmin->lang("Grant")}'
        onclick='grantTablePriv.submit()' /></td>
</tr>
</table>
</form>
EOF;
        $this->idsadmin->html->add_to_output($HTML);
    }

    /**
     * Show create new role form
     */
    function showCreateNewRole ()
    {
    	$HTML = <<<EOF
<br/>
<form method="post" name="createNewRole" action="index.php?act=privileges&amp;do=roles&amp;dbname={$this->dbname}">
<table>
<tr>
   <th colspan='5'>{$this->idsadmin->lang("CreateNewRole")}:</th>
</tr>
<tr>
   <th>{$this->idsadmin->lang("RoleName")}</th>
   <td><input type='text' name="role" value="" /></td>
   <td><input type='submit' class='button' name='createrole' value='{$this->idsadmin->lang("Create")}'
        onclick='createNewRole.submit()' /></td>
</tr>
</table>
</form>
EOF;
        $this->idsadmin->html->add_to_output($HTML);
    }


    /**
     * Grant User Privilege
     */
    function execGrantUserPrivilege ()
    {
    	if ($this->idsadmin->isreadonly())
    	{
    		$this->idsadmin->error($this->idsadmin->lang("NoPermission"));
    		return;
    	}

    	$db = $this->idsadmin->get_database($this->dbname);

    	if (!isset($this->idsadmin->in['username']) || $this->idsadmin->in['username'] == "")
    	{
    		$this->idsadmin->error($this->idsadmin->lang("NoUserError"));
    		return;
    	}
    	if (!isset($this->idsadmin->in['newpriv']) || $this->idsadmin->in['newpriv'] == "")
    	{
    		$this->idsadmin->error($this->idsadmin->lang("NoPrivError"));
    		return;
    	}

    	$username = $this->idsadmin->in['username'];
    	$newpriv = $this->idsadmin->in['newpriv'];
    	$grant = "GRANT $newpriv to '$username'";

	    // If the user already has privileges on this database,
		// we need to revoke first, then grant;
		$sql = "select username, " .
		    " CASE WHEN (usertype='C') THEN 'Connect' " .
            " WHEN (usertype='D') THEN 'DBA' " .
            " WHEN (usertype='R') THEN 'Resource' " .
            "END  as usertype from sysusers where username='{$username}'";
		$stmt = $db->query($sql);
		$res = $stmt->fetch();
		if ($res) {
			$grant = "revoke {$res['USERTYPE']} from '$username' ; " . $grant;
		}

    	$db->exec($grant);
        $err_r = $db->errorInfo();
        if ($err_r[1] != 0) {
            $this->idsadmin->error("{$this->idsadmin->lang('GrantFailed')}. <br/> {$this->idsadmin->lang('ErrorF')} {$err_r[1]} {$err_r[2]} ");
        } else {
        	$this->idsadmin->status($this->idsadmin->lang('GrantForUserSuccess') . " {$username}.");
        }
    }


    /**
     * Modify User Privilege
     */
    function execModifyUserPrivilege ()
    {
        if ($this->idsadmin->isreadonly())
    	{
    		$this->idsadmin->error($this->idsadmin->lang("NoPermission"));
    		return;
    	}

    	$db = $this->idsadmin->get_database($this->dbname);

    	if (!isset($this->idsadmin->in['username']) || $this->idsadmin->in['username'] == "")
    	{
    		$this->idsadmin->error($this->idsadmin->lang("NoUserError"));
    		return;
    	}
    	if (!isset($this->idsadmin->in['newpriv']) || $this->idsadmin->in['newpriv'] == "")
    	{
    		$this->idsadmin->error($this->idsadmin->lang("NoPrivError"));
    		return;
    	}

    	$username = $this->idsadmin->in['username'];
    	$newpriv = $this->idsadmin->in['newpriv'];
    	$prevpriv = (isset($this->idsadmin->in['prevpriv']))? $this->idsadmin->in['prevpriv']:"";
    	if (strcasecmp($newpriv,$prevpriv)==0) {
    		// if new privilege is same as previous privilege, then there is nothing to do
			return;
    	}

    	if (strcasecmp($newpriv,"Revoke") ==0) {
			// Revoke all privileges (including lower level privileges)
			$grant_stmt = "REVOKE $prevpriv from '$username'; ";
			if ($prevpriv == "DBA")
			{
				$grant_stmt .= "REVOKE RESOURCE from '$username'; ";
			}
			if ($prevpriv == "DBA" || $prevpriv == "RESOURCE")
			{
				$grant_stmt .= "REVOKE CONNECT from '$username';";
			}
    	} else {
			// Run revoke of current privilege, then run grant on the new one
			$grant_stmt = "REVOKE $prevpriv from '$username'; ";
  			$grant_stmt .= "GRANT $newpriv to '$username'";
    	}
    	$db->exec($grant_stmt);
        $err_r = $db->errorInfo();
        if ($err_r[1] != 0) {
            $this->idsadmin->error("{$this->idsadmin->lang('GrantFailed')}. <br/> {$this->idsadmin->lang('ErrorF')} {$err_r[1]} {$err_r[2]} ");
        } else {
        	if (strcasecmp($newpriv,"Revoke") ==0) {
        		$this->idsadmin->status($this->idsadmin->lang('PrivilegeRevokedForUser') . " {$username}.");
        	} else {
        		$this->idsadmin->status($this->idsadmin->lang('PrivilegeSavedForUser') . " {$username}.");
        	}
        }
    }


    /**
     * Grant Table Privilege
     */
    function execGrantTablePrivilege ()
    {
        if ($this->idsadmin->isreadonly())
    	{
    		$this->idsadmin->error($this->idsadmin->lang("NoPermission"));
    		return;
    	}

    	$db = $this->idsadmin->get_database($this->dbname);

    	if (!isset($this->idsadmin->in['username']) || $this->idsadmin->in['username'] == "")
    	{
    		$this->idsadmin->error($this->idsadmin->lang("NoUserError"));
    		return;
    	}
    	if (!isset($this->idsadmin->in['tabname']) || $this->idsadmin->in['tabname'] == "")
    	{
    		$this->idsadmin->error($this->idsadmin->lang("NoTabError"));
    		return;
    	}

    	$username = $this->idsadmin->in['username'];
    	$tabname = $this->idsadmin->in['tabname'];

    	// enclose owner in single quotes
        $tabname_r = explode(".",$tabname);
        $tabname_r[0] = "'{$tabname_r[0]}'";
        $tabname = implode(".",$tabname_r);

    	$newprivlist = "";
		foreach ($this->tabprivileges as $level)
		{
			if (isset($this->idsadmin->in["{$level}_box"]))
			{
				$newprivlist .= "{$level},";
			}
		}

		if ($newprivlist == "")
		{
    		$this->idsadmin->error($this->idsadmin->lang("NoPrivError"));
    		return;
    	}

		// strip off last comma in $newprivlist
    	$newprivlist = substr($newprivlist,0,strlen($newprivlist)-1);

		$grant = "GRANT $newprivlist on $tabname to '$username'";
 		$db->exec($grant);
        $err_r = $db->errorInfo();
        if ($err_r[1] != 0) {
            $this->idsadmin->error("{$this->idsadmin->lang('GrantFailed')}. <br/> {$this->idsadmin->lang('ErrorF')} {$err_r[1]} {$err_r[2]} ");
        } else {
        	$this->idsadmin->status($this->idsadmin->lang('GrantSuccess1') . " {$tablename} " . $this->idsadmin->lang('GrantSuccess2') . " {$username}.");
        }
    }

    /**
     * Modify Table Privilege
     */
    function execModifyTablePrivilege ()
    {
        if ($this->idsadmin->isreadonly())
    	{
    		$this->idsadmin->error($this->idsadmin->lang("NoPermission"));
    		return;
    	}

    	$db = $this->idsadmin->get_database($this->dbname);

    	if (!isset($this->idsadmin->in['grantee']) || $this->idsadmin->in['grantee'] == "" ||
    	    !isset($this->idsadmin->in['grantor']) || $this->idsadmin->in['grantor'] == "")
    	{
    		// can only happen if user manually types save into the url
    		$this->idsadmin->error($this->idsadmin->lang("NoUserError"));
    		return;
    	}
    	if (!isset($this->idsadmin->in['tabname']) || $this->idsadmin->in['tabname'] == "")
    	{
    		$this->idsadmin->error($this->idsadmin->lang("NoTabError"));
    		return;
    	}

    	$username = $this->idsadmin->in['grantee'];
    	$grantor = $this->idsadmin->in['grantor'];
    	$tabname = $this->idsadmin->in['tabname'];

    	// enclose owner in single quotes
        $tabname_r = explode(".",$tabname);
        $tabname_r[0] = "'{$tabname_r[0]}'";
        $tabname = implode(".",$tabname_r);

		foreach ($this->tabprivileges as $level)
		{
			if (isset($this->idsadmin->in["{$level}box"]))
			{
				$newprivlist .= "{$level},";
			}
		}
		// strip off last comma in $newprivlist
    	$newprivlist = substr($newprivlist,0,strlen($newprivlist)-1);

		// Revoke all for the user on the table first,
		// then grant the new privileges
		$grant_stmt = "REVOKE ALL on $tabname from '$username' as '$grantor'; ";
		if ($newprivlist != "")
		{
			$grant_stmt .=  "GRANT $newprivlist on $tabname to '$username'  as '$grantor'";
		}

    	$db->exec($grant_stmt);
        $err_r = $db->errorInfo();
        if ($err_r[1] != 0) {
            $this->idsadmin->error("{$this->idsadmin->lang('GrantFailed')}. <br/> $grant_stmt <br/> {$this->idsadmin->lang('ErrorF')} {$err_r[1]} {$err_r[2]} ");
        } else {
        	if ($newprivlist == "") {
        		$this->idsadmin->status($this->idsadmin->lang('PrivilegeRevokedForUser') . " {$username} " . $this->idsadmin->lang('OnTable') . " {$tabname}.");
        	} else {
        		$this->idsadmin->status($this->idsadmin->lang('PrivilegeSavedForUser') . " {$username} " . $this->idsadmin->lang('OnTable') . " {$tabname}.");
        	}
        }
    }

    /**
     * Create New Role
     */
    function execCreateNewRole ()
    {
        if ($this->idsadmin->isreadonly())
    	{
    		$this->idsadmin->error($this->idsadmin->lang("NoPermission"));
    		return;
    	}

    	$db = $this->idsadmin->get_database($this->dbname);

    	if (!isset($this->idsadmin->in['role']) || $this->idsadmin->in['role'] == "")
    	{
    		$this->idsadmin->error($this->idsadmin->lang("NoRoleError"));
    		return;
    	}

    	$role = $this->idsadmin->in['role'];
    	$create = "CREATE ROLE $role";
    	$db->exec($create);
        $err_r = $db->errorInfo();
        if ($err_r[1] != 0) {
            $this->idsadmin->error("{$this->idsadmin->lang('CreateFailed')}. <br/> {$this->idsadmin->lang('ErrorF')} {$err_r[1]} {$err_r[2]} ");
        } else {
        	$this->idsadmin->status($this->idsadmin->lang('CreateSuccessForRole') . " {$role}.");
        }
    }

	function getDefaultUser ()
	{
		$db = $this->idsadmin->get_database("sysuser");
		$sel = "SELECT COUNT (*) AS COUNT FROM SYSUSERMAP WHERE USERNAME='public'";
		$temp = $db->query($sel, false, false)->fetch();
		$this->publicUser = $temp[COUNT];
	}

	function getPAMPorts ()
	{
		$db = $this->idsadmin->get_database("sysmaster");
		$sel = "select
			count( CASE WHEN pamauth = 0 THEN 1 ELSE NULL END  ) non_pam_ports,
			count( CASE WHEN pamauth > 0 THEN 1 ELSE NULL END  ) pam_ports
			from sysmaster:syssqlhosts
			where  sysmaster:ifx_get_hostaddr(dbinfo('dbhostname')) =
            sysmaster:ifx_get_hostaddr(hostname)";
		$res = $db->query($sel, false, false)->fetch();
		$this->openPorts = $res[NON_PAM_PORTS];
		$this->pamPorts = $res[PAM_PORTS];
	}

} // end class privileges
?>
