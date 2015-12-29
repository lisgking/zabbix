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


/**
 * Contains the 'admin' function for connections
 * Contains the 'admin' functions for help
 * Contains the 'stub'  for the plugin manager.
 */

class admin {

	public $idsadmin;
	public $silent = false;

	function __construct(&$idsadmin)
	{
		define('IN_ADMIN',true);
		$this->idsadmin = &$idsadmin;
		$this->idsadmin->load_template("template_admin");
		$this->idsadmin->load_lang("admin");
		$this->idsadmin->html->set_pagetitle($this->idsadmin->lang('admin'));
	}

	/**
	 * The run function is what index.php will call.
	 * The decission of what to actually do is based
	 * on the value of the $this->idsadmin->in['do']
	 *
	 */
	function run()
	{

		if ( isset ( $this->idsadmin->in['helpact'] )
		&& $this->idsadmin->in['do'] != "doedithelp"
		&& $this->idsadmin->in['do'] != "doaddhelp" )
		{
			header("Location: {$this->idsadmin->get_config("BASEURL")}/index.php?act=help&helpact={$this->idsadmin->in['helpact']}&helpdo={$this->idsadmin->in['helpdo']}");
			die();
		}

		if ( isset($this->idsadmin->in['lang']) )
		{
		    // If the user has changed the language, set the new language now.
		    $this->idsadmin->phpsession->set_lang($this->idsadmin->in['lang']);
		}

		switch( $this->idsadmin->in['do'] )
		{
			case "getconnections":
				if ( ! isset($this->idsadmin->in['group_num']) )
				{
					$grpnum = 1;
				}
				else
				{
					$grpnum = $this->idsadmin->in['group_num'];
				}
				$this->getconnections($grpnum);
				break;
			case "config":
				$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("OATconfig"));
				$this->idsadmin->setCurrMenuItem("OATconfig");
				$this->config();
				break;
			case "checkconfig":
				$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("OATconfig"));
				$this->check_config();
				break;
			case "doconfig":
				$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("OATconfig"));
				$this->doconfig();
				break;
			case "pluginmgr":
				$this->idsadmin->setCurrMenuItem("pluginmgr");
				$this->pluginmgr();
				break;
			case "menumgr":
				$this->idsadmin->setCurrMenuItem("menumgr");
				$this->menumgr();
				break;
			case "help":
				$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("HelpAdmin"));
				$this->idsadmin->setCurrMenuItem("helpadmin");
				$this->help();
				break;
			case "doedithelp":
				$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("EditHelp"));
				$this->doedithelp();
				break;
			case "edithelp":
				$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("EditHelp"));
				$this->idsadmin->setCurrMenuItem("helpadmin");
				$this->edithelp();
				break;
			case "delhelp":
				$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("DeleteHelp"));
				$this->idsadmin->setCurrMenuItem("helpadmin");
				$this->delhelp();
				break;
			case "addhelp":
				$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("AddHelp"));
				$this->idsadmin->setCurrMenuItem("addhelp");
				$this->addhelp();
				break;
			case "doaddhelp":
				$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("AddHelp"));
				$this->doaddhelp();
				break;
			case "addconn":
				$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("AddConn"));
				$this->idsadmin->setCurrMenuItem("addconn");
				$this->addconn();
				break;
			case "dodelconn":
				$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("DeleteConn"));
				$this->dodelconn();
				break;
			case "dodelgroup":
				$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("DeleteGroup"));
				$this->dodelgroup();
				break;
			case "doaddconn":
				$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("AddConn"));
				$this->doaddconn();
				break;
			case "editconn":
				$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("EditConn"));
				$this->idsadmin->setCurrMenuItem("connadmin");
				$this->editconn();
				break;
			case "doeditconn":
				$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("EditConn"));
				$this->doeditconn();
				break;
			case "addGroup":
				$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("AddGroup"));
				$this->idsadmin->setCurrMenuItem("addgroup");
				$this->addgroup();
				break;
			case "doaddgroup":
				$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("AddGroup"));
				$this->doaddgroup();
				break;
			case "editgroup":
				$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("EditGroup"));
				$this->idsadmin->setCurrMenuItem("connadmin");
				$this->editgroup();
				break;
			case "doeditgroup":
				$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("EditGroup"));
				$this->doeditgroup();
				break;
			case "showconn":
				$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("ConnList"));
				$this->idsadmin->setCurrMenuItem("connadmin");
				$this->showconn();
				break;
			case "showgroups":
				$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("ConnAdmin"));
				$this->idsadmin->setCurrMenuItem("connadmin");
				$this->showgroups();
				break;
			/* Disabling the pinger functionality.  See idsdb00232788.
			case "pingerinfo":
				$this->idsadmin->html->set_pagetitle ($this->idsadmin->lang("PingerInfo"));
				$this->pingerinfo();
				break;
			*/
			case "testconn":
				$this->testconn();
				break;
			case "picker":
				$this->idsadmin->html->set_pagetitle ($this->idsadmin->lang("DashboardMgr"));
				$this->idsadmin->setCurrMenuItem("DashboardMgr");
				$this->doPicker();
				break;
			case "doimport":
				$this->doimport();
				break;
			case "doexport":
				$this->doexport();
				break;
			case "showDocument":
				$this->idsadmin->html->add_to_output($this->idsadmin->template["template_global"]->global_redirect("","../index.php?act=help&do=showDocument&document=" . $this->idsadmin->in['document']));
				break;
			default:
				$this->idsadmin->setCurrMenuItem("AdminHome");
				$this->doWelcome();
				break;
		}

	}

	/**
	 * Show the Admin Welcome page
	 */
	function doWelcome()
	{
		
		$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("AdminWelcome"));
        $this->idsadmin->load_template("template_welcome");
        $this->idsadmin->html->add_to_output($this->idsadmin->template["template_welcome"]->render_welcome("", true, $this->idsadmin->phpsession->get_lang()));
	} #end doWelcome

	/**
	 * Perform the oat application configuration
	 */
	function config($err="")
	{
		$this->idsadmin->html->add_to_output($this->idsadmin->template['template_admin']->config_hdr($err));
		$this->idsadmin->html->add_to_output($this->idsadmin->template['template_admin']->config_row($this->idsadmin->get_config("*")));
		$this->idsadmin->html->add_to_output($this->idsadmin->template['template_admin']->config_footer());
	} //end config

	function check_config()
	{
		$cwd = realpath(getcwd());
		$request_uri = dirname(getenv('REQUEST_URI'));
		$l = strlen($cwd) - strlen($request_uri);
		$doc_root = substr($cwd,0,$l);
		$conndbdir = realpath($this->idsadmin->in['CONNDBDIR']);

		if (!stristr($conndbdir,$doc_root)===false)
		{
			$html = "<form name='ok' method='post' action='index.php?act=admin&amp;do=doconfig'>";
			foreach ($this->idsadmin->in as $i => $v)
			{
				$html .= (strcasecmp($i,'do')==0)?"":"<input type=hidden name='{$i}' value='{$v}'/>";
			}
			$html .= "</form>\n";
			$html .= "<script language='JavaScript'>\n";
			$html .= "var answer = confirm(\"{$this->idsadmin->lang('insecureCONNDBDIR')}\");\n";
			$html .= "if (answer)\n";
			$html .= "document.ok.submit();\n";
			$html .= "else\n";
			$html .= "window.location='index.php?act=admin&do=config';\n";
			$html .= "</script>";
			$this->idsadmin->html->add_to_output($html);
		}else{
			$this->doconfig();
		}
	} //end check_config

	/**
	 * Write the oat application config
	 *
	 */
	function doconfig()
	{
		$conf_vars = array (
        "LANG"         => $this->idsadmin->lang("LANG")
        ,"CONNDBDIR"    => $this->idsadmin->lang("CONNDBDIR")
        ,"BASEURL"      => $this->idsadmin->lang("BASEURL")
        ,"HOMEDIR"      => $this->idsadmin->lang("HOMEDIR")
        ,"HOMEPAGE"     => $this->idsadmin->lang("HOMEPAGE")
        ,"PINGINTERVAL" => $this->idsadmin->lang("PINGINTERVAL")
        ,"ROWSPERPAGE" => $this->idsadmin->lang("ROWSPERPAGE")
        ,"SECURESQL"	=> $this->idsadmin->lang("SECURESQL")
        ,"INFORMIXCONTIME"	=> $this->idsadmin->lang("INFORMIXCONTIME")
        ,"INFORMIXCONRETRY"	=> $this->idsadmin->lang("INFORMIXCONRETRY")
        );

        # create backup of file
        $src=$this->idsadmin->get_config('HOMEDIR')."/conf/config.php";
        $dest=$this->idsadmin->in['HOMEDIR']."/conf/BAKconfig.php";
        copy($src,$dest);
        # open the file
        if (! is_writable($src))
        {
        	$this->config($this->idsadmin->lang("SaveCfgFailure"). " $src");
        	return;
        }
        $fd = fopen($src,'w+');
        # write out the conf
        fputs($fd,"<?php \n");
        foreach ($conf_vars as $k => $v)
        {
        	if ($k == "CONNDBDIR" || $k == "HOMEDIR") 
            {
            	// Replace backslashes in paths with forward slashes
                $this->idsadmin->in[$k] = str_replace('\\', '/', $this->idsadmin->in[$k]); 
            }
        	$out = "\$CONF['{$k}']=\"{$this->idsadmin->in[$k]}\";  #{$v}\n";
        	fputs($fd,$out);
        }
        fputs($fd,"?>\n");
        fclose($fd);

        $this->idsadmin->html->add_to_output($this->idsadmin->template["template_global"]->global_redirect($this->idsadmin->lang("SaveCfgSuccess"),"index.php?act=admin"));

	} #end config

	/**
	 * Test the connection before the connection info is saved.
	 */
	function testconn()
	{
		require_once (ROOT_PATH."modules/login.php");
		$login_module = new login($this->idsadmin);
		$login_module->testconn();
	}

	/**
	 * Get a list of connections for a specific group
	 *
	 * @param integer $grpnum
	 */
	function getconnections( $grpnum = 1)
	{

		require_once ROOT_PATH."/lib/connections.php";
		$grp = new connections($this->idsadmin);

		if ( isset($this->idsadmin->in['group_num']) )
		{
			$grpnum = $this->idsadmin->in['group_num'];
		}

		$sql = "select * from connections where group_num = {$grpnum} ";
		$stmt = $grp->db->query($sql);
		header("Content-Type: text/xml");
		print ("<connections>");

		$rows = $stmt->fetchAll();

		foreach ($rows as $k=>$row)
		{
			if (! $row['LASTSTATUS'] )
			{
				$row['LASTSTATUS'] = 5;
			}

			if (! $row['LASTSTATUSMSG'])
			{
				$row['LASTSTATUSMSG'] = "Unknown";
			}

			if ( $row['LASTSTATUS'] == 1 )
			{
				$row['LASTSTATUSMSG'] = "Online";
			}

			if ( $this->idsadmin->phpsession->instance->get_servername() == $row['SERVER'] )
			{
				$row['LASTSTATUS']= 4 ;
				$row['LASTSTATUSMSG']=$this->idsadmin->lang("OnlineStatusCurrent");
			}

			print ("<connection>\n");
			print ("<conn_num>{$row['CONN_NUM']}</conn_num>\n");
			print ("<host>{$row['HOST']}</host>\n");
			print ("<port>{$row['PORT']}</port>\n");
			print ("<server>{$row['SERVER']}</server>\n");
                        print ("<idsprotocol>{$row['IDSPROTOCOL']}</idsprotocol>\n");
			print ("<state>{$row['LASTSTATUS']}</state>\n");
			print ("<message>{$row['LASTSTATUSMSG']}</message>\n");
			print ("</connection>\n");
		}

		print ("</connections>");
		die();
	} #end getconnections

	/**
	 * Show a list of groups
	 *
	 */
	function showgroups($err="")
	{

		require_once ROOT_PATH."/lib/connections.php";
		$grp = new connections($this->idsadmin);
		$this->idsadmin->html->add_to_output($this->idsadmin->template['template_admin']->groups_hdr($err));
		$stmt = $grp->db->query("select * from groups ");
		$cnt=0;
		while ( $row  = $stmt->fetch() )
		{
			$cntstmt = $grp->db->query("select count(*) as cnt from connections where group_num = {$row['GROUP_NUM']}");
			$c =  $cntstmt->fetch();
			$row['CNT'] = $c['CNT'];
			$cntstmt->closeCursor();
			$this->idsadmin->html->add_to_output($this->idsadmin->template['template_admin']->groups_row($row,$cnt++));
		}
		$stmt->closeCursor();
		$this->idsadmin->html->add_to_output($this->idsadmin->template['template_admin']->groups_footer());
		$this->idsadmin->html->add_to_output($this->idsadmin->template['template_admin']->import_export());

	}

	/**
	 * Add a connection
	 *
	 * @param unknown_type $data
	 * @param unknown_type $err
	 */
	function addconn($data="",$envvars=Array(),$err="")
	{

		$grps = $this->getGroups();
		$this->idsadmin->html->add_to_output($this->idsadmin->template['template_admin']->addconn($data,$envvars,$grps,$err));

	} #end addconn

	function doaddconn()
	{

		$data = $this->idsadmin->in;
		$envvars = unserialize(html_entity_decode($this->idsadmin->in['envvars']));

		if ( $this->idsadmin->in['SERVER']=="" )
		{
			$this->addconn($data, $envvars, $this->idsadmin->lang("RequiresInformixServer"));
			return;
		}

		if ( $this->idsadmin->in['HOST']=="" )
		{
			$this->addconn($data, $envvars, $this->idsadmin->lang("RequiresHost"));
			return;
		}

		if ( $this->idsadmin->in['PORT']=="" )
		{
			$this->addconn($data, $envvars, $this->idsadmin->lang("RequiresPort"));
			return;
		}
		$conn = array();
		$conn['GROUP_NUM'] = $this->idsadmin->in['GROUP_NUM'];
		$conn['HOST'] = $this->idsadmin->in['HOST'];
		$conn['PORT'] = $this->idsadmin->in['PORT'];
		$conn['SERVER'] = $this->idsadmin->in['SERVER'];
                $conn['IDSPROTOCOL'] = $this->idsadmin->in['IDSPROTOCOL'];
		$conn['USERNAME'] = $this->idsadmin->in['USERNAME'];
		$conn['PASSWORD'] = $this->idsadmin->in['PASSWORD'];
		$conn['IDSD'] = $this->idsadmin->in['IDSD'];

		require_once ROOT_PATH."/lib/connections.php";
		$grp = new connections($this->idsadmin);
		$grp->db->beginTransaction();


		$conn['cid'] = $grp->add_conn($conn);

		if ( $conn['cid'] == -1 )
		{
			$x = $grp->db->errorInfo();
			$grp->db->rollback();
			$this->addconn($data, $envvars, $this->idsadmin->lang("AddNewConnFailWithError") . " {$x[1]} - {$x[2]} " );
			return;
		}

		//$conn['cid'] = $grp->db->lastInsertId();

		$grp->db->query("insert into idsd (cid,host,port) values ( {$conn['cid']}, '{$conn['HOST']}', '{$conn['IDSD']}')");
		$x = $grp->db->errorInfo();
		if ( $x[1] != 0 )
		{
			$grp->db->rollback();
			$this->addconn($data, $envvars, $this->idsadmin->lang("AddNewConnIDSDFailWithError") . " {$x[1]} - {$x[2]} " );
			return;
		}

		// If env variables were added, insert into conn_envvars
		foreach ($envvars as $name => $value)
		{
			$grp->db->query("insert into conn_envvars (conn_num, envvar_name, envvar_value) values " .
          		"( {$conn['cid']}, \"$name\", \"$value\" )");
			if ( $x[1] != 0 )
			{
				$grp->db->rollback();
				$this->editconn($data,$envvars,$this->idsadmin->lang("InsertCEnvVarsFailedWithError") . " {$x[1]} - {$x[2]} " );
				return;
			}
		}

		$grp->db->commit();
		$this->idsadmin->html->add_to_output($this->idsadmin->template["template_global"]->global_redirect("{$this->idsadmin->lang('AddingConn')} {$conn['SERVER']}","index.php?act=admin&do=showgroups"));

	}#end doaddconn

	/**
	 * Edit a server connection entry
	 *
	 */
	function doeditconn()
	{

		$data = $this->idsadmin->in;
		$envvars = unserialize(html_entity_decode($this->idsadmin->in['envvars']));

		if ( $this->idsadmin->in['SERVER']=="" )
		{
			$this->editconn($data,$envvars,$this->idsadmin->lang("RequiresInformixServer"));
			return;
		}

		if ( $this->idsadmin->in['HOST']=="" )
		{
			$this->editconn($data,$envvars,$this->idsadmin->lang("RequiresHost"));
			return;
		}

		if ( $this->idsadmin->in['PORT']=="" )
		{
			$this->editconn($data,$envvars,$this->idsadmin->lang("RequiresPort"));
			return;
		}

		$conn = array();
		$conn['CONN_NUM'] = intval($this->idsadmin->in['CONN_NUM']);
		$conn['GROUP_NUM'] = $this->idsadmin->in['GROUP_NUM'];
		$conn['HOST'] = $this->idsadmin->in['HOST'];
		$conn['PORT'] = $this->idsadmin->in['PORT'];
		$conn['SERVER'] = $this->idsadmin->in['SERVER'];
                $conn['IDSPROTOCOL'] = $this->idsadmin->in['IDSPROTOCOL'];
		$conn['USERNAME'] = $this->idsadmin->in['USERNAME'];
		$conn['PASSWORD'] = $this->idsadmin->in['PASSWORD'];
		$conn['IDSD'] = $this->idsadmin->in['IDSD'];

		require_once ROOT_PATH."/lib/connections.php";
		$grp = new connections($this->idsadmin);

		$grp->db->beginTransaction();

		$res = $grp->update_conn($conn);
		if ( $res != 0 )
		{
			$x = $grp->db->errorInfo();
			$grp->db->rollback();
			$this->editconn($data,$envvars,$this->idsadmin->lang("UpdConnFailedWithError") . " {$x[1]} - {$x[2]} " );
			return;
		}

		$grp->db->query("replace into idsd (cid,host,port) values ( {$conn['CONN_NUM']}, '{$conn['HOST']}', '{$conn['IDSD']}')");
		$x = $grp->db->errorInfo();
		if ( $x[1] != 0 )
		{
			$grp->db->rollback();
			$this->editconn($data,$envvars,$this->idsadmin->lang("ReplaceIDSDFailedWithError") . " {$x[1]} - {$x[2]} " );
			return;
		}

		// If env variables were modified, remove the old ones from the connections db and reinsert the new ones
		if ($this->idsadmin->in['envvar_modified'] == 1)
		{
			$grp->db->query("delete from conn_envvars where conn_num=" . " {$conn['CONN_NUM']} " );
			$x = $grp->db->errorInfo();
			if ( $x[1] != 0 )
			{
				$grp->db->rollback();
				$this->editconn($data,$envvars,$this->idsadmin->lang("DeleteCEnvVarsFailedWithError") . " {$x[1]} - {$x[2]} " );
				return;
			}
			foreach ($envvars as $name => $value)
			{
				$grp->db->query("insert into conn_envvars (conn_num, envvar_name, envvar_value) values " .
            		"( {$conn['CONN_NUM']}, \"$name\", \"$value\" )");
				if ( $x[1] != 0 )
				{
					$grp->db->rollback();
					$this->editconn($data,$envvars,$this->idsadmin->lang("UpdCEnvVarsFailedWithError") . " {$x[1]} - {$x[2]} " );
					return;
				}
			}
		}

		$grp->db->commit();
		$this->idsadmin->html->add_to_output($this->idsadmin->template['template_global']->global_redirect("{$this->idsadmin->lang("EditingConnection")} {$conn['nickname']}","index.php?act=admin&do=showconn&group={$conn['GROUP_NUM']}"));

	}#end doeditconn

	function editconn($data = "",$envvars=Array(),$err="")
	{
		if ( !isset ($data['CONN_NUM']) )
		{
			$data['CONN_NUM'] = $this->idsadmin->in['conn'];
		}

		$groups = $this->getGroups();
		require_once ROOT_PATH."/lib/connections.php";
		$grp =new connections($this->idsadmin);;

		// get connection info
		$sql = "select connections.*,idsd.port as idsd from connections LEFT OUTER JOIN idsd on conn_num = cid  where conn_num={$data['CONN_NUM']}";
		$stmt = $grp->db->query($sql);
		$data = $stmt->fetch();

		// get env var info
		$sql = "select envvar_name, envvar_value from conn_envvars where conn_num={$data['CONN_NUM']}";
		$stmt = $grp->db->query($sql);
		$envvars = Array();
		while ($res = $stmt->fetch() )
		{
			$envvars["{$res['ENVVAR_NAME']}"] = $res['ENVVAR_VALUE'];
		}

		$this->idsadmin->html->add_to_output($this->idsadmin->template['template_admin']->editconn($data,$envvars,$groups,$err));
		$stmt->closeCursor();
	} #end editconn

	function editgroup($err="",$num=0)
	{

		if ( $num == 0 )
		$num = $this->idsadmin->in['group_num'];
		$grps=$this->getGroups( $num );
		$this->idsadmin->html->add_to_output($this->idsadmin->template['template_admin']->editgroup($err,$grps));
	} #end editgroup

	function doeditgroup()
	{

		$grpname = $this->idsadmin->in["groupname"];
		$password = $this->idsadmin->in["password"];
		$readonly = ( isset($this->idsadmin->in['readonly']) ? 1 : 0 );

		$num = $this->idsadmin->in['num'];

		if ($num == "")
		{
			$this->idsadmin->error($this->idsadmin->lang("ErrorOccurredGroupNumRequired").__FILE__.__LINE__);
			return;
		}

		if ($grpname == "")
		{
			$this->editgroup($this->idsadmin->lang("RequiresGroupName"),$num);
			return;
		}

		require_once ROOT_PATH."/lib/connections.php";
		$grp =new connections($this->idsadmin);;
		$grp->db->query("REPLACE INTO groups VALUES ({$num},'{$grpname}','{$password}','{$readonly}')");

		$x = $grp->db->errorInfo();
		if ( $x[1] != 0 )
		{
			$this->editgroup($this->idsadmin->lang("EditGroupFailedWithError") . " {$x[1]} - {$x[2]} " );
			return;
		}

		$this->idsadmin->html->add_to_output($this->idsadmin->template['template_global']->global_redirect("{$this->idsadmin->lang("EditingGroup")} {$grpname}","index.php?act=admin&do=showgroups"));

	}#end doeditgroup

	function addgroup($err="")
	{
		$this->idsadmin->html->add_to_output($this->idsadmin->template['template_admin']->addgroup($err));
	} #end addgroup

	function doaddgroup()
	{

		$grpname = $this->idsadmin->in["groupname"];
		if ($grpname == "")
		{
			$this->addgroup($this->idsadmin->lang("RequiresGroupName"));
			return;
		}
		$password = $this->idsadmin->in["password"];

		$readonly = ( isset($this->idsadmin->in['readonly']) ? 1 : 0 );

		require_once ROOT_PATH."/lib/connections.php";
		$grp =new connections($this->idsadmin);;
		$sql = "INSERT INTO groups VALUES (NULL,\"{$grpname}\",\"{$password}\",'{$readonly}')";
		$stmt = $grp->db->prepare($sql);

		$stmt->execute();

		$x = $grp->db->errorInfo();
		if ( $x[1] != 0 )
		{
			$this->addgroup($this->idsadmin->lang("AddGroupFailedWithError") . " {$x[1]} - {$x[2]} " );
			return;
		}

		$this->idsadmin->html->add_to_output($this->idsadmin->template["template_global"]->global_redirect("{$this->idsadmin->lang("AddingGroup")} {$grpname}","index.php?act=admin&do=showgroups"));

	} #end doaddgroup

	function getGroups($whichgrp="")
	{
		$newgrp = array();
		$whichgrp = intval($whichgrp);

		require_once ROOT_PATH."/lib/connections.php";
		$grp =new connections($this->idsadmin);;

		if ($whichgrp=="")
		{
			$where="";
		}
		else
		{
			$where="WHERE group_num={$whichgrp}";
		}

		$stmt = $grp->db->query("select * from groups {$where}");
		while ( $row = $stmt->fetch() )
		{
			$newgrp[] = $row;

		}
		$stmt->closeCursor();
		return $newgrp;

	}

	function dodelconn()
	{

		$todelete = array();

		if (!isset($this->idsadmin->in['delconn']))
		{
			$this->idsadmin->error($this->idsadmin->lang("ErrorDeletingConn"));
			return;
		}

		#find the connections to delete.
		foreach ( $this->idsadmin->in as $k => $v )
		{
			if ($v == "on")
			{
				$todelete[] = $k;
			}
		}

		if (sizeof($todelete) > 0)
		{
			require_once ROOT_PATH."/lib/connections.php";
			$grp =new connections($this->idsadmin);;
			$sql = "delete from connections where conn_num in ( ";
			$cnt = 0;
			foreach($todelete as $k => $v)
			{
				if ($cnt > 0)
				{
					$sql .= ",";
				}
				$sql .= "{$v}";
				$cnt++;
			}
			$sql .= ")";
			$grp->db->query($sql);

			$x = $grp->db->errorInfo();
			if ( $x[1] != 0 )
			{
				$this->showconn($this->idsadmin->lang("DeleteConnFailedWithError") . " {$x[1]} - {$x[2]} " );
				return;
			}
		}
		$this->idsadmin->html->add_to_output($this->idsadmin->template["template_global"]->global_redirect($this->idsadmin->lang("DeleteConnComplete"),"index.php?act=admin&do=showgroups"));

	}#end dodelconn

	function dodelgroup()
	{

		$todelete = array();
		#find the groups to delete.
		foreach ( $this->idsadmin->in as $k => $v )
		{
			if ($v == "on" && $k != 1) {
				$todelete[] = $k;
			}
		}

		if (sizeof($todelete) > 0)
		{
			require_once ROOT_PATH."/lib/connections.php";
			$grp =new connections($this->idsadmin);;
			$sql = "delete from groups where group_num in ( ";
			$cnt = 0;
			foreach($todelete as $k => $v)
			{
				if ($cnt > 0)
				{
					$sql .= ",";
				}
				$sql .= "{$v}";
				$cnt++;
			}
			$sql .= ")";
			$grp->db->query($sql);

			$x = $grp->db->errorInfo();
			if ( $x[1] != 0 )
			{
				$this->showgroups($this->idsadmin->lang("DeleteGroupsFailedWithError") . " {$x[1]} - {$x[2]} " );
				return;
			}

		}
		$this->idsadmin->html->add_to_output($this->idsadmin->template["template_global"]->global_redirect($this->idsadmin->lang("DeleteGroupsComplete"),"index.php?act=admin&do=showgroups"));

	}#end dodelgroup

	function showconn($err="")
	{

		$grp_num = intval($this->idsadmin->in['group']);
		require_once ROOT_PATH."/lib/connections.php";
		$grp =new connections($this->idsadmin);;
		$stmt = $grp->db->query("select * from connections where group_num = {$grp_num} order by server");

		$grps = $this->getGroups($grp_num);
		$this->idsadmin->html->add_to_output($this->idsadmin->template['template_admin']->connections_header($grps,$err));
		$cnt=0;
		while ( $row = $stmt->fetch() )
		{
			$this->idsadmin->html->add_to_output($this->idsadmin->template['template_admin']->connections_row($row,$cnt++));
		}
		$stmt->closeCursor();
		$this->idsadmin->html->add_to_output($this->idsadmin->template['template_admin']->connections_footer());
	} #end showconn

	/**
	 * Display the form to add a new help
	 *
	 * @param unknown_type $data
	 * @param unknown_type $err
	 */
	function addhelp($data="",$err="")
	{
		$helpdb_id = 0;
		if (isset($this->idsadmin->in['helpdb']))
		{
			$helpdb_id = $this->idsadmin->in['helpdb'];
		}

		// Create a drop-down select control to allow the user choose the helpdb to add to
		require_once ROOT_PATH."/lib/connections.php";
		$grp = new connections($this->idsadmin);
		$sql = "select plugin_id, plugin_name, plugin_dir from plugins where plugin_enabled = 1";
		$stmt = $grp->db->query($sql);
		$rows = $stmt->fetchAll();
		$helpdb_select = "<select name='helpdb'><option value='0'>{$this->idsadmin->lang('OATForIDSText')}</option>";
		$i = 0;
		$helpdb_path = "";
		foreach ($rows as $k=>$row)
		{
			$i++;
			$helpdb_path = ROOT_PATH . "/plugin/" . $row['PLUGIN_DIR'] . "/lang/" . $this->idsadmin->phpsession->get_lang() . "/idsadminHelp.db";
			if (file_exists($helpdb_path))
			{
				if ($helpdb_id == $row['PLUGIN_ID'])
				{
					$helpdb_select .= "<option value='{$row['PLUGIN_ID']}' selected='selected'>{$this->idsadmin->lang('x_Plugin',array($row['PLUGIN_NAME']))}</option>";
				} else {
					$helpdb_select .= "<option value='{$row['PLUGIN_ID']}'>{$this->idsadmin->lang('x_Plugin',array($row['PLUGIN_NAME']))}</option>";
				}
			}
		}
		$helpdb_select .= "</select>";

		// But we'll only display the drop-down if there are some plugin help databases
		if ($i <= 0)
		{
			$helpdb_select = "";
		}

		$this->idsadmin->html->add_to_output($this->idsadmin->template['template_admin']->addhelp($data,$helpdb_id,$helpdb_select,$err));
	}#end addhelp

	/**
	 * Add a 'help' entry to the database
	 *
	 */
	function doaddhelp()
	{

		$data = $this->idsadmin->in;

		if ( $this->idsadmin->in['helpact']=="" )
		{
			$this->addhelp($data,$this->idsadmin->lang("RequiresAct"));
			return;
		}

		if ( $this->idsadmin->in['desc']=="" )
		{
			$this->addhelp($data,$this->idsadmin->lang("RequiresDescription"));
			return;
		}

		$helpdb_id = 0;
		if (isset($this->idsadmin->in['helpdb']))
		{
			$helpdb_id = $this->idsadmin->in['helpdb'];
		}
		$helpdb =  $this->openhelpdb($helpdb_id);
		$sql = "insert into help (num, helpact , helpdo , desc ) values (NULL,:helpact,:helpdo,:helpdesc)";
		$stmt = $helpdb->prepare($sql);
		$stmt->bindValue(":helpact",$data['helpact']);
		$stmt->bindValue(":helpdo",$data['helpdo']);
		$stmt->bindValue(":helpdesc",$data['desc']);
		$stmt->execute();

		$this->idsadmin->html->add_to_output($this->idsadmin->template['template_global']->global_redirect("{$this->idsadmin->lang('AddingHelpFor')} {$data['act']}","index.php?act=admin&do=help&helpdb={$helpdb_id}"));

	}#end doaddhelp

	/**
	 * Display all the 'help' from the database.
	 *
	 */
	function help()
	{
		// Determine which helpdb to display.
		// $helpdb_id = 0 indicates that the OAT helpdb should be displayed.
		// Otherwise $helpdb_id is the id of the plugin whose helpdb should be displayed.
		$helpdb_id = 0;
		if (isset($this->idsadmin->in['helpdb']))
		{
			$helpdb_id = $this->idsadmin->in['helpdb'];
		}

		// Get list of available helpdbs
		require_once ROOT_PATH."/lib/connections.php";
		$grp = new connections($this->idsadmin);
		$sql = "select plugin_id, plugin_name, plugin_dir from plugins where plugin_enabled = 1";
		$stmt = $grp->db->query($sql);
		$rows = $stmt->fetchAll();

		// Display a drop-down to switch between OAT's helpdb and plugin helpdbs
		$html = <<<EOF
<form name="helpSelect" method="get">
<input type="hidden" name="act" value="admin"/>
<input type="hidden" name="do" value="help"/>
{$this->idsadmin->lang("selectHelpDB")}:
<select name='helpdb' onchange="helpSelect.submit()">
<option value='0'>{$this->idsadmin->lang('OATForIDSText')}</option>
EOF;

		$i = 0;
		$helpdb_path = "";
		foreach ($rows as $k=>$row)
		{
			$i++;
			$helpdb_path = ROOT_PATH . "/plugin/" . $row['PLUGIN_DIR'] . "/lang/". $this->idsadmin->phpsession->get_lang() ."/idsadminHelp.db";
			if (file_exists($helpdb_path))
			{
				if ($helpdb_id == $row['PLUGIN_ID'])
				{
					$html .= "<option value='{$row['PLUGIN_ID']}' selected='selected'>{$this->idsadmin->lang('x_Plugin',array($row['PLUGIN_NAME']))}</option>";
				} else {
					$html .= "<option value='{$row['PLUGIN_ID']}'>{$this->idsadmin->lang('x_Plugin',array($row['PLUGIN_NAME']))}</option>";
				}
			}
		}
		$html .= "</select></form>";

		// Only display the drop-down if there are some plugin help databases
		if ($i > 0)
		{
			$this->idsadmin->html->add_to_output($html);
		}

		$helpdb =  $this->openhelpdb($helpdb_id);
		$sql = "select * from help order by helpact,helpdo";
		$stmt = $helpdb->query($sql);
		$this->idsadmin->html->add_to_output($this->idsadmin->template['template_admin']->help_header($helpdb_id));
		$cnt=0;
		while ($res = $stmt->fetch())
		{
			$this->idsadmin->html->add_to_output($this->idsadmin->template['template_admin']->help_row($res,($cnt%2),$helpdb_id));
			$cnt++;
		}
		$this->idsadmin->html->add_to_output($this->idsadmin->template['template_admin']->help_footer());
	}#end help

	/**
	 * Delete help entries from the database
	 *
	 */
	function delhelp()
	{
		$helpdb_id = 0;
		if (isset($this->idsadmin->in['helpdb']))
		{
			$helpdb_id = $this->idsadmin->in['helpdb'];
		}

		$helpdb =  $this->openhelpdb($helpdb_id);
		foreach ($this->idsadmin->in as $k => $v )
		{
			if ( $v == "on" )  {
				$sql = "delete from help where num = {$k};";
				$helpdb->query($sql);
			}
		}

		$this->idsadmin->html->add_to_output($this->idsadmin->template['template_global']->global_redirect($this->idsadmin->lang("DeletingHelp"),"index.php?act=admin&do=help&helpdb={$helpdb_id}"));

	}#end delhelp

	/**
	 * Display the edit help form
	 *
	 * @param unknown_type $data
	 * @param unknown_type $err
	 */
	function edithelp($data="",$err="")
	{
		$helpdb_id = 0;
		if (isset($this->idsadmin->in['helpdb']))
		{
			$helpdb_id = $this->idsadmin->in['helpdb'];
		}

		if ( ! is_array($data) )
		{
			$helpdb =  $this->openhelpdb($helpdb_id);
			$helpnum = intval($this->idsadmin->in['helpnum']);
			$sql = "select * from help where num={$helpnum}";
			$stmt = $helpdb->query($sql);
			$data = $stmt->fetch();
		}

		$this->idsadmin->html->add_to_output($this->idsadmin->template['template_admin']->edithelp($data,$helpdb_id,$err));
	} #end edithelp

	/**
	 * Edit help entries from the database
	 *
	 */
	function doedithelp()
	{

		$data = $this->idsadmin->in;

		if ( $this->idsadmin->in['helpact']=="" )
		{
			$this->edithelp($data,$this->idsadmin->lang("RequiresAct"));
			return;
		}

		if ( $this->idsadmin->in['desc']=="" )
		{
			$this->edithelp($data,$this->idsadmin->lang("RequiresDescription"));
			return;
		}

		$helpdb_id = 0;
		if (isset($this->idsadmin->in['helpdb']))
		{
			$helpdb_id = $this->idsadmin->in['helpdb'];
		}
		$helpdb = $this->openhelpdb($helpdb_id);

		$sql = "update help set helpact=:helpact,helpdo=:helpdo,desc=:helpdesc where num = {$data['num']} ";
		$stmt = $helpdb->prepare($sql);
		$stmt->bindValue(":helpact",$data['helpact']);
		$stmt->bindValue(":helpdo",$data['helpdo']);
		$stmt->bindValue(":helpdesc",$data['desc']);
		$stmt->execute();
		$this->idsadmin->html->add_to_output($this->idsadmin->template['template_global']->global_redirect("{$this->idsadmin->lang('EditingHelpFor')} {$data['act']}","index.php?act=admin&do=help&helpdb={$helpdb_id}"));

	}#end doedithelp

	/**
	 * Open the help database
	 *
	 * @param $helpdb_id = 0 to open the OAT helpdb
	 * 		  otherwise $helpdb_id indicates the plugin_id whose helpdb should be opened
	 * @return PDO
	 */
	function openhelpdb($helpdb_id = 0)
	{
		if ($helpdb_id == 0)
		{
			$file = $this->idsadmin->get_config('HOMEDIR')."/lang/" . $this->idsadmin->phpsession->get_lang() . "/idsadminHelp.db";
		} else {
			// Find the path for the plugin's helpdb
			require_once ROOT_PATH."/lib/connections.php";
			$grp = new connections($this->idsadmin);
			$sql = "select plugin_dir from plugins where plugin_enabled = 1 and plugin_id = {$helpdb_id}";
			$stmt = $grp->db->query($sql);
			$rows = $stmt->fetchAll();
			$file = "";
			foreach ($rows as $k=>$row)
			{
				$file = ROOT_PATH . "/plugin/" . $row['PLUGIN_DIR'] . "/lang/" . $this->idsadmin->phpsession->get_lang() . "/idsadminHelp.db";
			}
			if ($file == "")
			{
				$this->idsadmin->fatal_error($this->idsadmin->lang("NoHelpDB") . " {$helpdb_id} " );
			}
		}

		# test if the file exists ..
		if (! is_writable($file))
		{
			die($this->idsadmin->lang("IDSAdminHelpProblem") . " {$file} " . " <br/>");
		}

		$helpdb =  new PDO("sqlite:{$file}");

		if ( ! $helpdb instanceOf PDO )  {
			die($this->idsadmin->lang("ErrorOpeningHelpDB") . " {$file} " . "<br/>");
		}

		return $helpdb;
	}

	function doimport()
	{
		$ferror=$_FILES['importfile']['error'];

		if ( $ferror == UPLOAD_ERR_NO_FILE )
        {
            $this->showgroups($this->idsadmin->lang('nofile'));
            return;
        }

		$text = file_get_contents($_FILES['importfile']['tmp_name']);
		try{
			$xml = new SimpleXMLElement($text);
		}catch(Exception $e){
			$this->showgroups($e->getMessage());
			return;
		}

        require_once ROOT_PATH."/lib/connections.php";
		$conn = new connections($this->idsadmin);

		if($this->idsadmin->in['overwrite'] == "on"){
			$sql = "DELETE FROM groups WHERE groups.group_name != 'Default'";
			$stmt = $conn->db->prepare($sql);
			$stmt->execute();
			$sql = "DELETE FROM connections";
			$stmt = $conn->db->prepare($sql);
			$stmt->execute();
			$sql = "DELETE FROM conn_envvars";
			$stmt = $conn->db->prepare($sql);
			$stmt->execute();
			$sql = "DELETE FROM idsd";
			$stmt = $conn->db->prepare($sql);
			$stmt->execute();
		}

		$importerr = "";

        //Add the groups to connections.db groups table
        foreach($xml->group as $grp)
        {
        	$grpname = (string)$grp['name'];
        	$readonly = ((string)$grp['readonly'] == "1")?1:0;
        	$grppasswd = (string)$grp['password'];

        	$sql = "SELECT count(*) AS grp_exist FROM groups WHERE group_name='{$grpname}' AND readonly={$readonly} AND password='{$grppasswd}'";
        	$stmt = $conn->db->query($sql);

        	$res = $stmt->fetch();

        	if($grpname == "Default"){
        		$sql = "UPDATE groups SET readonly={$readonly}, password='{$grppasswd}' WHERE group_name='Default'";
	        	$stmt = $conn->db->prepare($sql);
	        	$stmt->execute();
	        	$err = $conn->db->errorInfo();
	        	if($err[0] != "00000"){
	        		$importerr.= $this->idsadmin->lang("FailedToImportGroup") . " {$grpname} " . ": ".$err[2]."<br>";
	        	}
        	}elseif($res['GRP_EXIST'] === "0"){
	        	$sql = "INSERT INTO groups (group_name,readonly,password) VALUES ('{$grpname}',{$readonly},'{$grppasswd}')";
	        	$stmt = $conn->db->prepare($sql);
	        	$stmt->execute();
	        	$err = $conn->db->errorInfo();
	        	if($err[0] != "00000"){
	        		$importerr.= $this->idsadmin->lang("FailedToImportGroup") . " {$grpname} " . ": ".$err[2]."<br>";
	        	}
        	}

        	//Add the connections to the connections.db connections table, according to their group.
        	foreach($grp->row as $row)
        	{
        		$host = (String)$row['hostname'];
        		$port = (String)$row['port'];
        		$server = (String)$row['dbsvrnm'];
        		$username = (String)$row['username'];
                        $idsprotocol = (String)$row['idsprotocol'];
        		$passwd = (String)$row['password'];

        		$sql = "INSERT INTO connections";
        		$sql.= "(group_num,host,port,server,idsprotocol,username,password";
        		$sql.= ")";
        		$sql.= " VALUES (";
        		$sql.= " (SELECT group_num FROM groups WHERE group_name='{$grpname}' AND password='{$grppasswd}' AND readonly='{$readonly}')";
        		$sql.= " ,'{$host}'";
        		$sql.= " ,'{$port}'";
        		$sql.= " ,'{$server}'";
                        $sql.= " ,'{$idsprotocol}'";
        		$sql.= " ,'{$username}'";
        		$sql.= " ,'{$passwd}'";
        		$sql.= ")";

        		$stmt = $conn->db->prepare($sql);
	        	$stmt->execute();
	        	$err = $conn->db->errorInfo();
	        	if($err[0] != "00000"){
	        		$importerr.= $this->idsadmin->lang("FailedToImport") . " {$server} " . " at " . " {$host} " . ": ".$err[2]."<br>";
	        	}
        	}
        }

        $this->showgroups($importerr);
	}

	function doexport()
	{
		require_once ROOT_PATH."/lib/connections.php";
		$conn = new connections($this->idsadmin);

		$sql = "SELECT";
		$sql.= " conn.host AS hostname,";
		$sql.= " conn.port AS port,";
		$sql.= " conn.server AS dbsvrnm,";
                $sql.= " conn.idsprotocol AS idsprotocol,";
		$sql.= " conn.username AS username,";
		$sql.= " conn.password AS password,";
		$sql.= " grps.group_name AS groupname,";
		$sql.= " grps.password AS group_password,";
		$sql.= " grps.readonly AS group_readonly";
		$sql.= " FROM connections AS conn, groups AS grps";
		$sql.= " WHERE conn.group_num = grps.group_num";

		$stmt = $conn->db->query($sql);
		$err = $conn->db->errorInfo();
	    if($err[0] != "00000"){
	       	$this->showgroups($err[2]);
	       	return;
	    }

		$text = "<connections></connections>";
		$xml = new SimpleXMLElement($text);;

		while($res = $stmt->fetch()){
			//$grp = ($res['GROUPNAME']=="Default")?$xml:$this->findGroup($xml,$res['GROUPNAME'],$res['GROUP_PASSWORD'],$res['GROUP_READONLY']);
			$grp = $this->findGroup($xml,$res['GROUPNAME'],$res['GROUP_PASSWORD'],$res['GROUP_READONLY']);
			$row = $grp->addChild('row');
			$row->addAttribute('dbsvrnm',$res['DBSVRNM']);
			$row->addAttribute('hostname',$res['HOSTNAME']);
			$row->addAttribute('port',$res['PORT']);
                        $row->addAttribute('idsprotocol',$res['IDSPROTOCOL']);
			$row->addAttribute('username',$res['USERNAME']);
			if($this->idsadmin->in['exportpasswd']=="on"){
				$row->addAttribute('password',$res['PASSWORD']);
			}
		}

		$filename = "export.xml";
        $hdrstr = "Content-Disposition: attachment; filename=$filename";
        header($hdrstr);
        echo $xml->asXML();
        exit;
	}

	/*A SimpleXMLElement object will be passed in the parameter.
	This function will search the xml object and return the group with name attribute equal to $grpname
	if such group does not exist, this function will create a group with such $grpname,
	and then return it.*/
	function findGroup($xml,$grpname,$grp_passwd,$grp_readonly)
	{
		foreach($xml->group as $group)
		{
			if((String)$group['name'] == $grpname)
			{
				return $group;
			}
		}

		$grp_readonly = ($grp_readonly == "")?0:$grp_readonly;

		//if the foreach loop exits without finding a group, that means the group
		//with $grpname does not exist. Creating a group with $grpname now...
		$group = $xml->addChild('group');
		$group->addAttribute('name', $grpname);
		if($this->idsadmin->in['exportpasswd']=="on"){
			$group->addAttribute('password', $grp_passwd);
		}
		$group->addAttribute('readonly', $grp_readonly);
		return $group;
	}

	/***
	 * Dashboard Manager
	 */
	function doPicker()
	{
		$this->idsadmin->load_template("template_dashboard");
		$this->idsadmin->html->add_to_output($this->idsadmin->template["template_dashboard"]->picker($this->idsadmin->phpsession->get_lang()));
	} // end picker

	/**
	* pingerinfo - display info about the pinger
	*/
	/** Disabling the pinger functionality.  See idsdb00232788.
	function pingerinfo()
	{
		require_once ROOT_PATH."/lib/connections.php";
		$grp =new connections($this->idsadmin);
		$sel = "select * from pingerinfo";
		$stmt = $grp->db->query($sel);
		$row = $stmt->fetch();
		$stmt->closeCursor();
		$grp->db = null;

		$this->idsadmin->html->add_to_output( $row['LASTRUN']."<br/>" );
		$this->idsadmin->html->add_to_output( $row['ISRUNNING']."<br/>" );
		$this->idsadmin->html->add_to_output( nl2br( $row['RESULT'] ) );
	} */

	/**
	 * pluginmgr:  stub function (ie. entry point ) for the 'Plugin Manger'
	 */
	function pluginmgr()
	{
		require_once("modules/pluginmgr.php");
		$pluginmgr = new pluginManager($this->idsadmin);
		$pluginmgr->run($this->silent);
	}

	/**
	 * menumgr:  stub function (ie. entry point ) for the 'Menu Manger'
	 */
	function menumgr()
	{
		require_once("modules/menumgr.php");
		$menumgr = new menumgr($this->idsadmin);
		$menumgr->run();
	}

} // end of class admin

// Suppress erroneous PHP Notice. This is a PHP bug (Bug #30208: http://bugs.php.net/bug.php?id=30208)
error_reporting(E_ALL ^ E_NOTICE);
define('ROOT_PATH',"../");
error_reporting(E_ALL);// make sure we report all bugs after this line.

require_once("../lib/initialize.php");
require_once("../lib/idsadmin.php");

$idsadmin = new IDSAdmin();
$idsadmin->in["act"] = "admin";
$idsadmin->html->set_maintemplate("main_admin.html");
$admin = new admin($idsadmin);

/**
 * Check if running off the command line.
 * this is so the automated installer can install plugins..
 */

if ( $argc == 5
&& strcasecmp($argv[1], "--installext") == 0
&& strcasecmp($argv[3], "--extname") == 0)
{

	echo $idsadmin->lang("PluginInstallStarted") . "\n";
	$idsadmin->in['act']  = "admin";
	$idsadmin->in['do']   = "pluginmgr";
	$idsadmin->in['run']  = "installplugin";
	$idsadmin->in['file'] = $argv[2];

	require_once("../lib/connections.php");
	$conndb = new connections($idsadmin);
	$qry = "SELECT plugin_id FROM PLUGINS WHERE PLUGIN_NAME=\"{$argv[4]}\"";
	$stmt = $conndb->db->query($qry);
	$err = $conndb->db->errorInfo();
	if ( $conndb->db->errorCode() != "00000" )
	{
		echo $idsadmin->lang("ErrorLoadingConnDB");
		return false;
	}

	$row = $stmt->fetch();
	$pluginID = ($row['PLUGIN_ID'] > 0)?$row['PLUGIN_ID']:0;
	$stmt->closeCursor();

	$idsadmin->in['pluginid'] = $pluginID;
	$admin->silent = true;
	return $admin->run();
}

$admin->run();

$idsadmin->html->render();
?>
