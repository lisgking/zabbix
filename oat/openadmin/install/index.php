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

/****************************************************
 * Install
 ****************************************************/

class install {

	public  $template;
	private $oat_version = "3.11"; // OAT version, populated with version number at build time
	private $print;
	private $message;               // object to manage the localized messages
	private $conf_vars = array ();
	private $silent    = false;  // running in silent mode?
	private $baseurl   = "";     // cmd line argument if running in silent mode
	private $conndbdir = "";     // cmd line argument if running in silent mode
	private $conndb    = "";     // connection to the connections.db database.
	private $lang      = "en_US"; // UI language for install pages

	function __construct()
	{
		$informixcontime = 20;
		$informixconretry = 3;
		
		// Get UI language
		session_start();
 		if (isset($_GET['lang']))
		{
			$this->lang = $_GET['lang'];
		} 
		else if (isset($_SESSION['lang'])) 
		{
			$this->lang = $_SESSION['lang'];
		}
		$_SESSION['lang'] = $this->lang;
		
		// Load output object
		require_once("lib/output.php");
		$this->print = new output();
		$this->print->setLang($this->lang);
		
		// Load localized messages
		require_once("lib/message.php");
		$this->message = new message($this->lang);
		$this->print->setPageTitle($this->message->lang("install_page_title"));
		
		// Load template
		$this->load_template("install_template");
		
		// Set the config params
		$this->conf_vars = array (
		"LANG"          => array ("name" => $this->message->lang("LANG"), 			"desc" => $this->message->lang("LANG_desc"))
		,"BASEURL"      => array ("name" => $this->message->lang("BASEURL"), 		"desc" => $this->message->lang("BASEURL_desc"))
		,"HOMEDIR"      => array ("name" => $this->message->lang("HOMEDIR" ), 		"desc" => $this->message->lang("HOMEDIR_desc"))
		,"CONNDBDIR"    => array ("name" => $this->message->lang("CONNDBDIR"), 		"desc" => $this->message->lang("CONNDBDIR_desc"))
		,"HOMEPAGE"     => array ("name" => $this->message->lang("HOMEPAGE"), 		"desc" => $this->message->lang("HOMEPAGE_desc"))
		,"PINGINTERVAL" => array ("name" => $this->message->lang("PINGINTERVAL"), 	"desc" => $this->message->lang("PINGINTERVAL_desc"))
		,"ROWSPERPAGE"  => array ("name" => $this->message->lang("ROWSPERPAGE"), 	"desc" => $this->message->lang("ROWSPERPAGE_desc"))
		,"SECURESQL"	=> array ("name" => $this->message->lang("SECURESQL"),		"desc" => $this->message->lang("SECURESQL_desc"))
		,"INFORMIXCONTIME"	=> array ("name" => $this->message->lang("INFORMIXCONTIME"), "desc" => $this->message->lang("INFORMIXCONTIME_desc"))
		,"INFORMIXCONRETRY"	=> array ("name" => $this->message->lang("INFORMIXCONRETRY"),"desc" => $this->message->lang("INFORMIXCONRETRY_desc"))
		);
	}

	// set install to run in silent mode
	function setSilentMode($baseurl,$conndbdir)
	{
		$this->silent=true;
		$this->baseurl=$baseurl;
		$this->conndbdir=$conndbdir;
	}

	function run()
	{
		// Check for install.lock file
		if ( !$this->silent && file_exists("install.lock") )
		{
			$this->error($this->message->lang("install_lock_exists"),true);
		}
		$option = isset($_GET['do']) ? $_GET['do'] : "unknown";
			
		if ( strlen(stristr("unknown check", $option)) <= 0 ) {
			if ( !file_exists("99") ) {
				$this->error($this->message->lang("error_accept_license_first") ,true);
			}
		}
			
		if ($this->silent)
		{
			$option="silent";
		}

		switch ($option)
		{
			case "start":
				$this->start();
				break;
			case "check":
				$this->check();
				break;
			case "config":
				$this->config();
				break;
			case "saveconfig":
				$this->saveConfig();
				break;
			case "dbs":
				$this->dbs();
				break;
			case "dodbs":
				$this->dodbs();
				break;
			case "plugins":
			    $this->plugins();
			    break;
			case "installplugins":
			    $this->installplugins();
			    break;
			case "final":
				$this->fin();
				break;
			case "silent":
				$this->doSilentInstall();
				break;
			default:
				$this->start();
				break;
		}

		if ( ! $this->silent )
		{
			$this->print->render();
		}

	} // end run

	function start()
	{
		$this->print->add_to_output($this->template->start($this->oat_version));
		
		// TEMP: Hiding language drop-down for install pages until we have
		// translated messages for these pages!
		//$this->print->setPageFooter($this->template->lang_page_footer($this->lang));
	}// end start

	function error($str, $fatal=false)
	{
		if ( $this->silent )
		{
			// errors for silent install should be sent to the console
			if ($fatal)
			{
				echo "\nFATAL ERROR:\n" . $str . "\n";
				exit(1);
			} else {
				echo "\nERROR:\n" . $str . "\n";
			}
		} else {
			$this->print->add_to_output($this->template->error($str));
			if ( $fatal )
			{
				$this->print->render();
				die();
			}
		}
	}  // error

	function warning($str)
	{
		$this->print->add_to_output($this->template->warning($str));
	}  // warning
	
	function check()
	{
		// License Agreement first ...
		if( !file_exists("99") ) {
			if (isset($_POST['install_start']) && $_POST['Lic_CB']) {
				fopen("99","w");
			} else {
				$this->error($this->message->lang('error_accept_license_first'),true);
			}
		}

		$show_next = true;
		$show_warning = false;
		/* Perform some sanity checks */
		$needed = array("PDO" => "true"
		,"pdo_informix" => "true"
		,"pdo_sqlite" => "true"
		,"soap" => "true"
		,);
		$recommended = array("gd" => "true" 
		, "zip" => "true"
		,);

		foreach ($needed as $k => $v)
		{
			$needed[$k] = $this->check_module($k);
			if ( $needed[$k] === false || $needed[$k] == "")
			$show_next = false;
		}
		foreach ($recommended as $k => $v)
		{
			$recommended[$k] = $this->check_module($k);
			if ( $recommended[$k] === false || $recommended[$k] == "")
			$show_warning = true;
		}

		if ( $this->silent )
		{
			if ( ! $show_next )
			{
				$errmsg = $this->message->lang('missing_required_modules') . "\n";
				$errmsg .= $this->message->lang('missing_modules') . "\n";
				foreach ($needed as $module => $value)
				{
					if (!$value)
					{
						$errmsg .= " -  $module \n";
					}
				}
				$this->error($errmsg,true);
			}

			if ( $show_warning )
			{
				$warn_msg = $this->message->lang('silent_missing_recommended_modules') . "\n" 
					. $this->message->lang('silent_recommended_modules') . "\n" ;
				foreach ($recommended as $module => $value)
				{
					if (!$value)
					{
						$warn_msg .= " -  $module \n";
					}
				}
				echo ($warn_msg);
			}

		} else {

			$this->print->add_to_output($this->template->show_required($needed));

			$this->print->add_to_output($this->template->show_recommended($recommended));

			$this->print->add_to_output($this->template->show_next($show_next,"config"));
		}

	} // end check

	function check_module($module)
	{
		$extensions = get_loaded_extensions();
		if (in_array($module, $extensions ) )
		{
			return true;
		}
		return false; // return false returns a blank string ! ?
	} // end check_module

	function config($config = array(), $warn_dir = '')
	{
		if (isset($config['HOMEDIR']))
		{
			$homedir = $config['HOMEDIR'];
		} else {
			$homedir = getcwd() . '/';
			$homedir = substr($homedir, 0, -8);
		}
		$homedir = str_replace( '\\', '/', $homedir);
		
		if (empty($config))
		{
			// If the $config wasn't passed as a parameter, see if the config.php
			// file already exists.  Because if it does, it means we are doing an
			// upgrade and we want to preserve the user's current config.
			if (file_exists($homedir."/conf/config.php"))
			{
				$CONF=array();
				require($homedir."/conf/config.php");
				$config = $CONF;
				unset($CONF);
			}
		}

		if (isset($config['CONNDBDIR']))
		{
			$conndbdir = $config['CONNDBDIR'];
		}else{
          	$conndbdir = getcwd() . '/';
          	$conndbdir = substr($conndbdir, 0, -8) . 'conf/';
		}
		$conndbdir = str_replace( '\\', '/', $conndbdir);

		// If conf/config.php does not exist, copy the default
		if (!file_exists($homedir."/config.php") && file_exists($homedir."/config.default.php"))
		{
			copy($homedir."/config.default.php", $homedir."/config.php");
		}

		if (isset($config['BASEURL'])){
			$baseurl = $config['BASEURL'];
		}else{
			$baseurl = substr($_SERVER['HTTP_REFERER'],0, -27);
		}

		$lang = isset($config['LANG']) ? $config['LANG'] : "en_US";
		$homepage = isset($config['HOMEPAGE']) ? $config['HOMEPAGE'] : "welcome";
		$pinginterval = isset($config['PINGINTERVAL']) ? $config['PINGINTERVAL'] : "300";
		$rowsperpage = isset($config['ROWSPERPAGE']) ? $config['ROWSPERPAGE'] : "25";
		if (!empty($config)&&!isset($config['SECURESQL'])){
			$secureSQL = "";
		}else{
			$secureSQL = "on";
		}
		$informixcontime = isset($config['INFORMIXCONTIME']) ? $config['INFORMIXCONTIME']:20;
		$informixconretry = isset($config['INFORMIXCONRETRY']) ? $config['INFORMIXCONRETRY']:3;

		// Set the config params defaults
		$this->conf_vars["LANG"]["default"] = $lang;
		$this->conf_vars["BASEURL"]["default"] = $baseurl;
		$this->conf_vars["HOMEDIR"]["default"] = $homedir;
		$this->conf_vars["CONNDBDIR"]["default"] = $conndbdir;
		$this->conf_vars["HOMEPAGE"]["default"] = $homepage;
		$this->conf_vars["PINGINTERVAL"]["default"] = $pinginterval;
		$this->conf_vars["ROWSPERPAGE"]["default"] = $rowsperpage;
		$this->conf_vars["SECURESQL"]["default"] = $secureSQL;
		$this->conf_vars["INFORMIXCONTIME"]["default"] = $informixcontime;
		$this->conf_vars["INFORMIXCONRETRY"]["default"] = $informixconretry;

		$this->print->add_to_output($this->template->config_start());
		$this->print->add_to_output($this->template->config_row($this->conf_vars));
		$this->print->add_to_output($this->template->config_warning($warn_dir));
		$this->print->add_to_output($this->template->config_end());
	} // end config

	function saveConfig()
	{
		// Strip escaped slashes
		if ( get_magic_quotes_gpc() )
		{
			foreach ($_POST as $k => $v)
			{
				$_POST[$k] = stripslashes($v);
			}
		}
		
		// Replace backslashes with forward slashes
		foreach ($_POST as $k => $v)
		{
			$_POST[$k] = str_replace( '\\', '/', $v);
		}
			
		// Check for missing values
		if ( !isset($_POST['LANG']) || $_POST['LANG'] == "")
		{
			$this->error($this->message->lang('config_value_empty',array($this->message->lang('LANG'))));
			return $this->config($_POST);
		}

		if ( !isset($_POST['BASEURL']) || $_POST['BASEURL'] == "")
		{
			$this->error($this->message->lang('config_value_empty',array($this->message->lang('BASEURL'))));
			return $this->config($_POST);
		}

		if ( !isset($_POST['HOMEDIR']) || $_POST['HOMEDIR'] == "")
		{
			$this->error($this->message->lang('config_value_empty',array($this->message->lang('HOMEDIR'))));
			return $this->config($_POST);
		}
		
		if ( !isset($_POST['CONNDBDIR']) || $_POST['CONNDBDIR'] == "")
		{
			$this->error($this->message->lang('config_value_empty',array($this->message->lang('CONNDBDIR'))));
			return $this->config($_POST);
		}
		
		if ( !isset($_POST['HOMEDIR']) || $_POST['HOMEDIR'] == "")
		{
			$this->error($this->message->lang('config_value_empty',array($this->message->lang('HOMEDIR'))));
			return $this->config($_POST);
		}

		if ( !isset($_POST['PINGINTERVAL']) || $_POST['PINGINTERVAL'] == "")
		{
			$this->error($this->message->lang('config_value_empty',array($this->message->lang('PINGINTERVAL'))));
			return $this->config($_POST);
		}
		
		if ( !isset($_POST['ROWSPERPAGE']) || $_POST['ROWSPERPAGE'] == "")
		{
			$this->error($this->message->lang('config_value_empty',array($this->message->lang('ROWSPERPAGE'))));
			return $this->config($_POST);
		}
		
		if ( !isset($_POST['INFORMIXCONTIME']) || $_POST['INFORMIXCONTIME'] == "")
		{
			$this->error($this->message->lang('config_value_empty',array($this->message->lang('INFORMIXCONTIME'))));
			return $this->config($_POST);
		}
		
		if ( !isset($_POST['INFORMIXCONRETRY']) || $_POST['INFORMIXCONRETRY'] == "")
		{
			$this->error($this->message->lang('config_value_empty',array($this->message->lang('INFORMIXCONRETRY'))));
			return $this->config($_POST);
		}
		
		if ( ! is_dir($_POST['HOMEDIR']) )
		{
			$this->error($this->message->lang('HOMEDIR_not_a_directory',array($_POST['HOMEDIR'])));
			return $this->config($_POST);
		}

		if ( ! is_dir($_POST['CONNDBDIR']) || ( is_writable($_POST['CONNDBDIR']) === false ) )
		{
			$this->error($this->message->lang('CONNDBDIR_not_valid', array($_POST['CONNDBDIR'])));
			return $this->config($_POST);
		}

		$cwd = realpath(getcwd());
		$request_uri = dirname(getenv('REQUEST_URI'));
		$l = strlen($cwd) - strlen($request_uri);
		$doc_root = substr($cwd,0,$l);
		$conndbdir = realpath($_POST['CONNDBDIR']);

		if (!stristr($conndbdir,$doc_root)===false)
		{
			$warned_dir = realpath($_POST['warned_dir']);

			if ((strcasecmp('',$warned_dir)==0)||(strcasecmp($conndbdir,$warned_dir)!=0))
			{
				$this->warning($this->message->lang('CONNDBDIR_warning', array($conndbdir)));
				return $this->config($_POST,$conndbdir);
			}
		}

		$homedir = $_POST['HOMEDIR']."/conf";
		if ( ( is_dir($homedir) === false )
		|| ( is_writable($homedir) === false )  )
		{
			$this->error($this->message->lang('directory_not_accessible', array($homedir)));
			return $this->config($_POST);
		}

		$conffile=$homedir."/config.php";
		if ( file_exists($conffile) && is_writable($conffile) === false )
		{
			$this->error($this->message->lang('file_not_accessible', array($conffile)));
			return $this->config($_POST);
		}

		// write out the config file ..
		$fd = fopen($conffile,'w+');

		fputs($fd,"<?php \n");
		foreach ($this->conf_vars as $k => $v)
		{
			$out = "\$CONF['{$k}']=\"{$_POST[$k]}\";#{$v['desc']}\n";
			fputs($fd,$out);
		}
		fputs($fd,"?>\n");
		fclose($fd);

		// redirect to next stage which is dbs..
		header("Location: index.php?do=dbs");
		die();
	} // end SaveConfig

	/**
	 *
	 * For silent install: save default values in config file
	 */
	function saveDefaultConfig()
	{
		$homedir = str_replace( '\\', '/', getcwd() ) . '/';
		$homedir = substr($homedir, 0, -8);

		$conndbdir = $this->conndbdir;

		if(!is_dir($this->conndbdir))
		{
			$chk = exec('mkdir "'.$this->conndbdir.'"');
			if($chk!=0)
			{
				$this->error("Failed to create {$this->conndbdir}. Make sure location is writable.",true);
			}
		}

		// If conf/config.php does not exist, copy the default
		if (!file_exists($homedir."/conf/config.php") && file_exists($homedir."/conf/config.default.php"))
		{
			copy($homedir."/conf/config.default.php", $homedir."/conf/config.php");
		}

		// default config values
		$lang         = "en_US";
		$useredirect  = "0";
		$homepage = "welcome";
		$pinginterval = "300";
		$rowsperpage = 25;
		$secureSQL    = "on";
		$informixcontime = 20;
		$informixconretry = 3;

		// Set the config params defaults
		$this->conf_vars["LANG"]["default"] = $lang;
		$this->conf_vars["BASEURL"]["default"] = $this->baseurl;
		$this->conf_vars["HOMEDIR"]["default"] = $homedir;
		$this->conf_vars["CONNDBDIR"]["default"] = $conndbdir;
		$this->conf_vars["HOMEPAGE"]["default"] = $homepage;
		$this->conf_vars["PINGINTERVAL"]["default"] = $pinginterval;
		$this->conf_vars["ROWSPERPAGE"]["default"] = $rowsperpage;
		$this->conf_vars["SECURESQL"]["default"] = $secureSQL;
		$this->conf_vars["INFORMIXCONTIME"]["default"] = $informixcontime;
		$this->conf_vars["INFORMIXCONRETRY"]["default"] = $informixconretry;

		if ( ! is_dir($homedir) )
		{
			$this->error("HOMEDIR is not valid",true);
		}

		//TODO: Nice to warn user if the directory entered is still web accessible .. !!

		if ( ! is_dir($conndbdir) || ( is_writable($conndbdir) === false ) )
		{
			$this->error("CONNDBDIR - $conndbdir -  is not valid. Please check it exists and is writable.",true);
		}

		$homedir = $homedir . "/conf";
		if ( ( is_dir($homedir) === false )
		|| ( is_writable($homedir) === false )  )
		{
			$this->error("{$homedir} is not accessible. Check it exists and is writable.",true);
		}

		$conffile=$homedir."/config.php";
		if ( is_writable($homedir."/config.php") === false )
		{
			$this->error("{$homedir}/config.php is not accessible. Check it exists and is writable.",true);
		}

		// write out the config file ..
		$fd = fopen($conffile,'w+');

		# write out the conf
		fputs($fd,"<?php \n");
		foreach ($this->conf_vars as $k => $v)
		{
			$out = "\$CONF['{$k}']=\"{$v['default']}\";#{$v['desc']}\n";
			fputs($fd,$out);
		}
		fputs($fd,"?>\n");
		fclose($fd);

	} // end saveDefaultConfig


        /**
         * Language in config file must have format "en_US".
         * Earlier versions of OAT just used "en", so if we find that, we need to update it.
         */
        function checkConfigLang($config_file)
        {
		if ( ! file_exists($config_file))
                {
                    return;
                }

                $CONF=array();
                require($config_file);
			
                // If the config file's lang is "en", change it to "en_US"
                if (isset($CONF['LANG']) && $CONF['LANG'] == "en")
                {
                        $CONF['LANG'] = "en_US";

                        // Re-write config file
                        $fd = fopen($config_file,'w+');

                        fputs($fd,"<?php \n");
                        foreach ($this->conf_vars as $k => $v)
                        {
                            $out = "\$CONF['{$k}']=\"{$CONF[$k]}\";#{$v['desc']}\n";
                            fputs($fd,$out);
                        }
                        fputs($fd,"?>\n");
                        fclose($fd);
                }
	}

	function dbs()
	{
		$vernum = "0";

		require_once("../conf/config.php");

		if ( ! isset($CONF['CONNDBDIR']) )
		{
			$this->error($this->message->lang('conndb_not_set'));
			return false;
		}

		$file="{$CONF['CONNDBDIR']}/connections.db";
		if ( ! is_dir($CONF['CONNDBDIR']) )
		{
			$this->error($this->message->lang('CONNDBDIR_not_valid', array($CONF['CONNDBDIR'])));
			return false;
		}

		unset($CONF);

		$this->conndb = new PDO("sqlite:{$file}");

		$stmt = $this->conndb->query("SELECT * FROM version");
		if ( $this->conndb->errorCode() == "00000" )
		{
			$version  = $stmt->fetch();
			$stmt->closeCursor();
			/* we got a version so lets run an upgrade */
			$vernum = $version['version_num'];
		}

		if ( $vernum == "0")
		{
			$this->print->add_to_output($this->template->createDatabase());
			$this->print->add_to_output($this->template->show_next(true,"dodbs"));
		}
		else
		{
			$this->print->add_to_output($this->template->updateDatabase());
			$this->print->add_to_output($this->template->show_next(true,"dodbs&amp;version={$vernum}"));
		}
	} // end dbs


	/**
	 *  dodbs :  create / upgrade the connections.db database.
	 *
	 * @return boolean
	 *
	 * Returns false if there was an error.
	 * Returns true if creation of connections database succeeded.
	 */
	function dodbs()
	{

		$chk = $this->open_connectionsdb();
		if($chk === false){
			return false;
		}
        // if we are being called with a version then we need to upgrade the connections.db.
		if ( isset( $_GET['version'] ) )
		{
			return $this->dodbs_upgrade( $_GET['version'] );
		}

        //create the default 220 database tables.
		$this->conndb->query("DROP TABLE groups;");
		$this->conndb->query("CREATE TABLE groups ( group_num INTEGER PRIMARY KEY AUTOINCREMENT , group_name VARCHAR(40) , password VARCHAR(20) , readonly CHAR(1) )");
		if ( $this->conndb->errorCode() != "00000" )
		{
			$err = $this->conndb->errorInfo();
			$this->error($this->message->lang("conndb_error", array("groups")) . "<br/>" . $this->message->lang("error_message") . " " . $err[2]);
			return false;
		}

		$this->conndb->query("DROP TABLE clusters;");
		$this->conndb->query("CREATE TABLE clusters (cluster_id INTEGER PRIMARY KEY AUTOINCREMENT , cluster_name VARCHAR(50))");
		if ( $this->conndb->errorCode() != "00000" )
		{
			$err = $this->conndb->errorInfo();
			$this->error($this->message->lang("conndb_error", array("clusters")) . "<br/>" . $this->message->lang("error_message") . " " . $err[2]);
			return false;
		}

		$this->conndb->query("DROP TABLE connections;");

		$this->conndb->query("CREATE TABLE connections ( conn_num INTEGER PRIMARY KEY AUTOINCREMENT , group_num INTEGER, nickname VARCHAR(40), host VARCHAR(200) , port VARCHAR(30) , server VARCHAR(128) , lat float , lon float , username varchar(128), password varchar(20), lastpingtime int , laststatus int , laststatusmsg int, lastonline int , cluster_id int , last_type int , cwd varchar(256) );");
		if ( $this->conndb->errorCode() != "00000" )
		{
			$err = $this->conndb->errorInfo();
			$this->error($this->message->lang("conndb_error", array("connections")) . "<br/>" . $this->message->lang("error_message") . " " . $err[2]);
			return false;
		}

		/**
		 * Create conn_envvars table.
		 * Used to let users specified env variables on their connections.
		 */
		$this->conndb->query("DROP TABLE conn_envvars;");
		$this->conndb->query("CREATE TABLE conn_envvars ( conn_num INTEGER, envvar_name VARCHAR(128) , envvar_value VARCHAR(512) )");
		if ( $this->conndb->errorCode() != "00000" )
		{
			$err = $this->conndb->errorInfo();
			$this->error($this->message->lang("conndb_error", array("conn_envvars")) . "<br/>" . $this->message->lang("error_message") . " " . $err[2]);
			return false;
		}

		/**
		 * Create the Dashboard tables
		 */
		$this->conndb->query("DROP TABLE dashboards;");
		$this->conndb->query("CREATE TABLE dashboards ( dashboard_id INTEGER PRIMARY KEY AUTOINCREMENT , dashboard_refresh INT, dashboard_name VARCHAR(100), dashboard_description TEXT );");
		if ( $this->conndb->errorCode() != "00000" )
		{
			$err = $this->conndb->errorInfo();
			$this->error($this->message->lang("conndb_error", array("dashboards")) . "<br/>" . $this->message->lang("error_message") . " " . $err[2]);
			return false;
		}
		
		/**
		 * Create the panels tables.
		 */
		$this->conndb->query("DROP TABLE panels;");
		$this->conndb->query("CREATE TABLE panels ( panel_id INTEGER PRIMARY KEY AUTOINCREMENT ,  panel_short_name VARCHAR(20) , panel_title VARCHAR(100), panel_description TEXT  );");
		if ( $this->conndb->errorCode() != "00000" )
		{
			$err = $this->conndb->errorInfo();
			$this->error($this->message->lang("conndb_error", array("panels")) . "<br/>" . $this->message->lang("error_message") . " " . $err[2]);
			return false;
		}

		$this->conndb->query("INSERT INTO panels VALUES (1,'memory','panel_title_memory','panel_desc_memory')");
		$this->conndb->query("INSERT INTO panels VALUES (2,'transactions','panel_title_transactions','panel_desc_transactions')");
		$this->conndb->query("INSERT INTO panels VALUES (3,'space','panel_title_space','panel_desc_space')");
		$this->conndb->query("INSERT INTO panels VALUES (4,'locks','panel_title_locks','panel_desc_locks')");


		/**
		 * Create the DashPanel table - this table describes what panels are on a dashboard and at what position.
		 */
		$this->conndb->query("DROP TABLE dashpanels;");
		$this->conndb->query("CREATE TABLE dashpanels ( dashid INTEGER , panelid INTEGER , pos INTEGER );");
		if ( $this->conndb->errorCode() != "00000" )
		{
			$err = $this->conndb->errorInfo();
			$this->error($this->message->lang("conndb_error", array("dashpanels")) . "<br/>" . $this->message->lang("error_message") . " " . $err[2]);
			return false;
		}

		/**
		 * Create the pinger info table.
		 */
		$this->conndb->query("CREATE TABLE 'pingerinfo' ('lastrun' INTEGER, 'isrunning' INTEGER, 'result' TEXT)");
		$this->conndb->query("INSERT INTO pingerinfo VALUES (0,0,'')");


		/*
		 * Create the idsd table. It links a connection with
		 * the IDS Daemon (idsd) controlling it.
		 */

		$this->conndb->query ( "CREATE TABLE idsd ( cid INTEGER PRIMARY KEY, host VARCHAR, port INTEGER ) ");
		$this->conndb->query ( "CREATE TRIGGER idsd_del DELETE ON connections BEGIN DELETE FROM idsd WHERE cid = old.conn_num; END" );

		/*
		 * Create the env and env_link tables. They store the environment
		 * variables for a server.
		 */

		$this->conndb->query ( "CREATE TABLE env ( eid INTEGER, name VARCHAR, value VARCHAR )" );
		$this->conndb->query ( "CREATE TABLE env_link ( eid INTEGER PRIMARY KEY AUTOINCREMENT, cid INTEGER, stamp INTEGER )" );
		$this->conndb->query ( "CREATE UNIQUE INDEX env_link_idx ON env_link ( cid )" );
		$this->conndb->query ( "CREATE TRIGGER env_link_del DELETE ON connections BEGIN DELETE FROM env_link WHERE cid = old.conn_num; END" );
		$this->conndb->query ( "CREATE TRIGGER env_del DELETE on env_link WHERE eid = old.eid; END" );
			
		/*
		 * Insert some default values into the necessary tables..
		 */
		$this->conndb->query("INSERT INTO groups VALUES (NULL,'Default','','')");

		/*
		 * Create the necessary Triggers
		 */
		$this->conndb->query("CREATE TRIGGER group_del DELETE on groups BEGIN delete from connections where connections.group_num = old.group_num;END");
		$this->conndb->query("CREATE TRIGGER cluster_del DELETE on clusters BEGIN update connections set cluster_id=0 where connections.cluster_id = old.cluster_id;END");
		$this->conndb->query("CREATE UNIQUE index i_clust_name ON clusters(cluter_name)");
		$this->conndb->query("CREATE TRIGGER dashboard_del DELETE on dashboards BEGIN delete from dashpanels where dashid = old.dashboard_id;END");

		/**
		 * drop and recreate the version table
		 * the version table is used to perform upgrades
		 * to the connections db
		 */
		$this->conndb->query("DROP TABLE version");
		$this->conndb->query("CREATE TABLE version ( version_num INTEGER )");
		if ( $this->conndb->errorCode() != "00000" )
		{
			$err = $this->conndb->errorInfo();
			$this->error($this->message->lang("conndb_error", array("version")) . "<br/>" . $this->message->lang("error_message") . " " . $err[2]);
			return false;
		}

		/**
		 * Insert the current version .
		 */
		$this->conndb->query("INSERT INTO version  (version_num) VALUES (220)");

		/**
		 * we now have a connections.db @ version 2.20 - we must now run the necessary upgrade scripts.
		 */

		return $this->dodbs_upgrade("220");


		//TODO: move this to dbdbs_upgrade -
		/* move this to dodbs_upgrade
		 if (!$this->silent)
		 {
			$this->print->add_to_output($this->template->databaseDone());
			$this->print->add_to_output($this->template->show_next(true,"final"));
			}

			return true;  // to indicate success
			*/

	} // end dodbs

	/**
	 * dodbs_upgrade - run the upgrade scripts ..
	 *
	 */

	function open_connectionsdb()
	{
		require("../conf/config.php");

		if ( ! isset($CONF['CONNDBDIR']) )
		{
			$this->error($this->message->lang('conndb_not_set'));
			return false;
		}

		$file="{$CONF['CONNDBDIR']}/connections.db";
		if ( ! is_dir($CONF['CONNDBDIR']) )
		{
			$this->error($this->message->lang('CONNDBDIR_not_valid', array($CONF['CONNDBDIR'])));
			return false;
		}

		unset($CONF);

		$this->conndb = new PDO("sqlite:{$file}");
	}

	function dodbs_upgrade($version)
	{
		$updfilelist = array();   // list of 'upgrade files' that need to be processed
		$result_message = "";

		$path = "./upgrade/";

		$dir = @opendir($path);

		/* read the upgrade directory */
		while ( false !== ( $file = readdir($dir) ) )
		{
			/* lets see if this is a php file */
			$fname = preg_split("/\./",$file);
			if ( strtolower( $fname[1] ) == "php" )
			{
				/* do we need to add this file ? */
				if ( $fname[0] > $version)
				{
					/* add the file to our list */
					$updfilelist[] = $file;
				}
			}
		}
		closedir($dir);
		sort($updfilelist);
		foreach ($updfilelist as $pos => $name )
		{
			/* reset the sql array */
			$sql = array();
			/* load the upgrade file */
			include_once("upgrade/$name");
			/* process each $sql array element as a seperate statment */
			foreach ( $sql as $n => $qry )
			{
				if ( ! $this->silent )
				{
					$file = "upgrade/$name";
					// only print upgrade statements that have a name ..
					if ( ! is_numeric($n) )
					{
						$result_message .= $this->message->lang('conndb_processing',array($file,$n)) . "<br/>";
					}
				}
				
				$stmt = $this->conndb->query($qry);
				if ( $this->conndb->errorCode() != "00000" )
				{
					$err = $this->conndb->errorInfo();
					$this->error($this->message->lang('conndb_error_upgrade',array($file,$n)) . "<br/>" . $this->message->lang("error_message") . " " . $err[2]);
					return false;
				}

			}

			// Update the version number in the connections.db ..
			$ver = preg_split("/\./",$name);
		
			$this->conndb->query("UPDATE version  SET version_num = {$ver[0]} ; ");
			if ( $this->conndb->errorCode() != "00000" )
			{
				$err = $this->conndb->errorInfo();
				$this->error($this->message->lang('conndb_error_upgrade_version'));
				return false;
			}
			
			// Also update the version number for the IBM Example plugin which is 
			// automatically installed with OAT to keep it in sync with the OAT version.
			$plugin_version = number_format(intval($ver[0])/100, 2);  // Convert version from 275 to 2.75 before inserting into plugin table.
			$this->conndb->query("UPDATE plugins SET plugin_version = {$plugin_version} where plugin_name = 'IBM Example' and plugin_author='IBM'; ");
			if ( $this->conndb->errorCode() != "00000" )
			{
				$err = $this->conndb->errorInfo();
				$this->error($this->message->lang('conndb_error_upgrade_example_plugin_version') . var_export($err,true));
				return false;
			}

		} // end foreach of the list of files to process.

		if ( ! $this->silent )
		{
			$this->print->add_to_output($this->template->databaseDone($result_message));
			$this->print->add_to_output($this->template->show_next(true,"plugins"));
		}

		return true;  // to indicate success

	}
	
	function plugins()
	{	    
	    // Check for PHP zip extension
	    $php_ext = get_loaded_extensions();
		if (!in_array("zip", $php_ext ) )
		{
			unset($php_ext);
			$HTML .= "<table width='100%'><tr>"
            	   . "<td class='tblheader'>{$this->message->lang('choose_plugins')}</td>"
            	   . "</tr><tr><td><p>{$this->message->lang('zip_extension_required')}</p></td></table>";
            $this->print->add_to_output($HTML);
            $this->print->add_to_output($this->template->show_next(true,"final"));
            return;
		}
		unset($php_ext);
		
		// Check that tmp directory is writable
	    if ( is_writable( "../tmp" ) === false )
	    {
	        $HTML .= "<table width='100%'><tr>"
            	   . "<td class='tblheader'>{$this->message->lang('choose_plugins')}</td>"
            	   . "</tr><tr><td><p>{$this->message->lang('tmp_dir_not_writable')}</p></td></table>";
            $this->print->add_to_output($HTML);
            $this->print->add_to_output($this->template->show_next(true,"final"));
            return;
	    }

		// Display list of available plugins
	    $plugins = $this->getAvailablePlugins();
    	if (count($plugins) == 0)
        {
        	error_reporting(E_ALL ^ E_NOTICE);//suppressing wrong notice that PHP 5.3 displays
            $HTML .= "<table width='100%'><tr>"
            	   . "<td class='tblheader'>{$this->message->lang('choose_plugins')}</td>"
            	   . "</tr><tr><td><p>{$this->message->lang('no_plugins_to_install')}</p></td></table>";
            $this->print->add_to_output($HTML);
			error_reporting(E_ALL);
			
            $this->print->add_to_output($this->template->show_next(true,"final"));
            return;    		
	    }
		$this->print->add_to_output($this->template->showPlugins($plugins));
	}
	

	function installplugins()
	{
	    $this->print->add_to_output("<table width='100%'><tr><td class='tblheader'>{$this->message->lang('plugin_install')}</td></tr>");
	    
	    // Determine which plugins should be installed
	    $plugins_to_install = array();
	    foreach ($_POST as $key => $value)
	    {
	        if (substr($key,0,7) == "plugin_")
	        {
	            if ($value == "on")
    	        {
    	            $plugins_to_install[] = $_POST['file_name_' . $key];
    	        }
	        } else if (substr($key,0,12) == "license_file")
	        {
	            // remove license files saved in temp
	            if (file_exists($_POST[$key]))
	            {
	                unlink($_POST[$key]);
	            }
	        }
	    }
	    // If no plugins to install, print message and return
	    if (count($plugins_to_install) == 0)
	    {
	        $this->print->add_to_output("<tr><td><p>{$this->message->lang('no_plugins_selected')}</p></td></tr></table>");
	        $this->print->add_to_output($this->template->show_next(true,"final")); 
	        return;
	    }
	    
	    // Use the plugin manager to install each of the plugins selected
	    require_once("../admin/modules/pluginmgr.php");
	    $this->open_connectionsdb();
	    $pluginmgr = new pluginManager();
	    $pluginmgr->setConndb($this->conndb);
	    $result_msg = "";
	    foreach ($plugins_to_install as $plugin_to_install)
	    {
	    	$result_msg .= $pluginmgr->doInstallPlugin("","",$plugin_to_install) . "<br/>";
	    }
	    $this->print->add_to_output("<tr><td><p>" . $result_msg . "</p></td></tr>");
	    		    
	    $this->print->add_to_output("</table>");
	    $this->print->add_to_output($this->template->show_next(true,"final")); 
	}
	
	function getAvailablePlugins()
	{
	    $plugins = array();
	    
	    // Get OAT version
	    $this->open_connectionsdb();
	    $stmt = $this->conndb->query("SELECT version_num FROM version;");
		if ( $this->conndb->errorCode() == "00000" )
		{
			$version  = $stmt->fetch();
			$stmt->closeCursor();
			$oat_version = $version['version_num'];
			$oat_version = preg_replace("/[^0-9B]/","",$oat_version);
			$split_ver = explode("B",$oat_version);
			$oat_version = $split_ver[0];
			
		}
	    
		// Find plugins available for install
		$dir = opendir( "../plugin_install" );
		while ( ( $file = readdir( $dir ) ) !== false )
		{
			// only interested in zip files.
			$plugin = explode( '.',$file );
			if ( strtolower( $plugin[count($plugin) -1] ) == "zip" )
			{
				$zip = new ZipArchive;
				if ($zip->open("../plugin_install/{$file}") !== TRUE) {
					continue;
				}

				if ( $zip->locateName("plugin.xml") !== FALSE )
				{
					$zip->extractTo("../tmp/","plugin.xml");
					$pluginData = file_get_contents("../tmp/plugin.xml");
					$plugin = $this->pluginInfoFromXML($pluginData,$file);
					
					// If the plugin has a minimum OAT version, check that now.
					// On the install screen, we'll only show plugins that meet requirements 
					if ($plugin->plugin_min_oat_version != "" && $plugin->plugin_min_oat_version != "--")
					{
						$min_oat_version = $plugin->plugin_min_oat_version;
						$min_oat_version = preg_replace("/[^0-9B]/","",$min_oat_version);
						$split_ver = explode("B",$min_oat_version);
						$min_oat_version = $split_ver[0];
						
						if ($min_oat_version > $oat_version)
						{
							// Plugin requires a higher OAT version, so don't show this plugin during install
							continue;
						}
					}
					
					if ($plugin->plugin_license)
					{
					    $zip->extractTo("../tmp/",$plugin->plugin_license);
					}
					
					$plugins[] = $plugin;
					unlink("../tmp/plugin.xml");
				}
				$zip->close();
			}
		}
		closedir($dir);
		
		return $plugins;
	}
	
	/**
	 * get the plugin info from the plugin plugin.xml file
	 *
	 * @param  $xml
	 * @return plugin object.
	 */
	function pluginInfoFromXML(&$xml,$file)
	{
		$sxml = new SimpleXMLElement($xml,NULL,false);
		require_once("../admin/lib/plugin.php");

		$plugin = new plugin($this->idsadmin);
		$pluginInfo = $sxml->plugin_info;
		$plugin->init(0,$file,$pluginInfo->plugin_name,$pluginInfo->plugin_desc
		,$pluginInfo->plugin_author,$pluginInfo->plugin_version
		,$pluginInfo->plugin_server_version , $pluginInfo->plugin_minimum_oat_version,
		$pluginInfo->plugin_upgrade_url
		,false,$pluginInfo->plugin_enabled , "" , $pluginInfo->plugin_license
		);

		return $plugin;
	}
	
	function fin()
	{
		$this->print->add_to_output($this->template->fin());
		$fd = fopen("install.lock","w");
		fclose($fd);
	} // end fin

	function load_template($template)
	{
		require_once("templates/{$template}.php");
		$this->template = new $template($this->print, $this->message);
	} // end load_template
	
	/* Silent Install */
	function doSilentInstall()
	{
		// For silent install, the user has already accepted the license
		// agreement, as indicated by the -accept_license command line
		// argument.  Therefore, we will automatically create the '99' file
		// that represent license agreement acceptance.
		fopen("99","w");

		// check modules
		$this->check();

		// in silent mode, if conf/config.php already exist, use and settings in the existing config.php
		// else create default config.php file
		$homedir = str_replace( '\\', '/', getcwd() ) . '/';
		$homedir = substr($homedir, 0, -8);
		$confdir = $homedir.'/conf/';
		if (!file_exists($confdir."/config.php"))
		{
			$this->saveDefaultConfig();
		} else {
			$this->checkConfigLang($confdir."/config.php");
		} 
		
		// in silent, if connections.db does not exist, run do dbs() to create it.
		// else, use existing connections.db
		require("../conf/config.php");
                if(!is_dir($CONF['CONNDBDIR']))
                {
                        $chk = exec('mkdir "'.$CONF['CONNDBDIR'].'"');
                        if($chk!=0)
                        {
                                $this->error("Failed to create {$CONF['CONNDBDIR']}. Make sure location is writable.",true);
                        }
                }
		
		$file="{$CONF['CONNDBDIR']}/connections.db";

		if(!file_exists($file)){
			// create connections database
			if (! ($this->dodbs()) )
			{
				// in silent install, failure in creation of connections database is a fatal error
				$this->error("Creation / Upgrade of connections database failed.", true);
			}			
		}else{
			$chk = $this->open_connectionsdb();
			if($chk === false){
				$this->error("Unable to preserve existing connections.db ({$file}). Delete existing connections.db and run the OAT automated installer again",true);
			}
			
			$stmt = $this->conndb->query("SELECT version_num FROM version;");
			if ( $this->conndb->errorCode() != "00000" )
			{
				$err = $this->conndb->errorInfo();
				$this->error("Error Occurred: {$err[2]}. Unable to preserve existing connections.db ({$file}). Delete existing connections.db and run the OAT automated installer again",true);
			}
			
			$res = $stmt->fetch();
			$chk = $this->dodbs_upgrade($res["version_num"]);
			if($chk === false){
				$this->error("Unable to preserve existing connections.db ({$file}). Delete existing connections.db and run the OAT automated installer again",true);
			}
		}
		
		// If we got to this point in the silent install without error,
		// the install succeeded.  Write "install.lock" file.
		fopen("install.lock","w");
		echo "\nOpenAdmin Tool installed successfully!\n";
	}// end doSilentInstall

} // end class


/**
 * setup and run the install
 */
$install = new install();

if ( version_compare(PHP_VERSION, "5.2.4", "<") )
{
	$install->error("Minimum PHP version required is 5.2.4.  PHP version found: ".PHP_VERSION , true);
}


/**
 * Check if running the install in silent mode off the command line.
 * If so, it will have been called with three arguments:
 * index.php -accept_license -baseurl="<base URL for OpenAdmin Tool>"
 *            -conndbdir="<the directory for connections database>";
 */
if ( @$_SERVER['argc'] == 4
&& strcasecmp(@$_SERVER['argv'][1], "-accept_license") == 0
&& strcasecmp(substr(@$_SERVER['argv'][2],0,9), "-baseurl=") == 0
&& strcasecmp(substr(@$_SERVER['argv'][3],0,11), "-conndbdir=") == 0 )
{
	echo "Starting silent install of OpenAdmin Tool...\n";
	$baseurl = substr(@$_SERVER['argv'][2],9);
	$conndbdir = addslashes(substr(@$_SERVER['argv'][3],11));
	$install->setSilentMode($baseurl,$conndbdir);
} 
/**
 * Check if running the upgrade in silent mode off the command line.
 * If so, it will have been called with two arguments:
 * index.php -accept_license -upgrade;
 * If these two arguments do not exist, default to web install mode.
 */
else if ( @$_SERVER['argc'] == 3
&& strcasecmp(@$_SERVER['argv'][1], "-accept_license") == 0
&& strcasecmp(@$_SERVER['argv'][2], "-upgrade") == 0 )
{
	echo "Starting silent upgrade of OpenAdmin Tool...\n";
	$install->setSilentMode("","");
}
// start installation
$install->run();
?>
