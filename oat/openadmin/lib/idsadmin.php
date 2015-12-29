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

/*********************************************************************
 * Main OAT class
 **********************************************************************/

class IDSAdmin {

	public   $html;                      /* the output class   */
	public   $template=array();          /* Array of templates */
	private  $version="3.11";     /* the version string */
	private  $buildtime="2013-02-20 05:00:34"; /* the build time     */
	private  $config=array();            /* CONFIG info from conf/config.php */
	public   $phpsession;                /* the phpsession class */
	private  $language = array();        /* the language array */
	public   $in = array();              /* the values from post or get */
	private  $database = array();        /* array of databases we have connections to */
	private  $currdb;                    /* current database */
	private  $last_lang_file;            /* the last lang file that was loaded */
	private  $isreadonly=false;          /* is the group we are connected to read-only? */
	public   $render = true;             /* whether to render our output or not */
	public   $serverInfo;                /* Information about the server we are connected to */
	public   $iserror;                   /* has a fatal error occurred? */
	public   $fatal_error_cnt = 0;       /* fatal error count... used to prevent getting in infinite loop of fatal errors */
	public   $crumb  = array();          /* bread crumb array */
	private  $redirect = array();        /* where to redirect to if the server is switched */
	private  $currMenuItem = "";         /* current menu item */
	private  $is_Plugin = 0;             /* Is the current URL a plugin? */
	private  $url_fullpath = "";         /* Full path to module name */
	public   $includeDojo = FALSE;       /* Include DOJO requirements in the output */
	
	/******************************************
	 * Constructor:
	 *******************************************/
	function __construct($is_webservice=false)
	{

		/* load the 'global template' */
		$this->load_template("template_global");
		
		/* load the 'html' class */
		require_once(ROOT_PATH."lib/output.php");
		$this->html = new output();
		
		$this->html->idsadmin =& $this;

		/* load the configuration params */
		if ( sizeof($this->config) == 0 && file_exists(ROOT_PATH."/conf/config.php"))
		{
			$CONF=array();
			require(ROOT_PATH."/conf/config.php");
			$this->config = $CONF;
			unset($CONF);
		}

		/* render html output only when not running as a web service */
		if ( ! $is_webservice )
		{
			$this->render=true;
		}
		else
		{
			$this->render=false;
			// IN_ADMIN gets set in index.php, so if we are running as a webservice
			// that does not go through index.php, we need to set it here
			@define('IN_ADMIN',false); 
		}

		/* load the 'phpsession' class */
		require_once(ROOT_PATH."/lib/phpsession.php");
		$this->phpsession = new phpsession($this);

		/* If the language is set in the session, use that.
		 Otherwise, set the default lang */
		$lang = (isset($_SESSION['lang']))? $_SESSION['lang']:$this->get_config("LANG","en_US");
		$this->phpsession->set_lang($lang);

		/* Make sure config.php exists.  If not, the user needs to run the install */
		if ( ! file_exists(ROOT_PATH."/conf/config.php"))
		{
			$msg = "Cannot find config.php. Have you run the install ? <br/>" .
                   "<a href='" . ROOT_PATH . "install/index.php'>Start the install</a>";
			$this->fatal_error($msg,false);
			return;
		}

		/* check our version */
		if ( $this->checkForUpgrade() == 1)
		{
			$msg = "OAT has been upgraded. You need to re-run the install to upgrade.<br/>" .
                   "<a href='" . ROOT_PATH . "install/index.php'>Start the upgrade</a>";
			$this->fatal_error($msg,false);
			return;
		}

		/* Sanity check PHP modules */
		$this->check_modules();

		/* Setup the INFORMIXDIR as part of the config */
		$this->set_INFORMIXDIR();

		/* Set up the in array */
		$this->set_in();

		/* set readonly */
		$this->set_isreadonly( $this->isGroupReadOnly( $this->phpsession->group ) );

	} #end __construct

	/** 
	 * Set the full path name to the module code.
	 *
	 * @param String $name = full path name
	 **/
	function set_fullpath( $name )
	{
		$this->url_fullpath = $name;
			
		if ( strpos($actname,"/") === false)
		{
			// $this->set_isPlugin(0);
		}
		else
		{
			// $this->set_isPlugin(1);
		}
	}

	/**
	 * Get the full pathname to the module code.
	 * 
	 * @return full path name
	 **/
	function get_fullpath()
	{
		return $this->url_fullpath;
	}

	/** 
	 * Are we using a plug-in module?
	 * 
	 * @return 0 = false; 1 = true
	 **/
	function isPlugin()
	{
		return $this->is_Plugin;
	}

	/**
	 * Set whether we are currently running a plug-in module .
	 * 
	 * @param integer: 0 = false; 1 = true
	 **/
	function set_isPlugin($val=0)
	{
		$this->is_Plugin = $val;
	}

	/**
	 * Get the name of the class.
	 * 
	 * @return class name
	 **/
	function get_classname()
	{
		$cname = substr(strrchr($this->url_fullpath, "/"), 1);

		return substr($cname,0,-4);   // remove the .php extention
	}

	/**
	 * Get full class path.
	 * 
	 * @return class path
	 **/
	function get_classpath()
	{
		return substr($this->url_fullpath,0,strrpos($this->url_fullpath,"/"));
	}
	
	/** 
	 * Get the plugin directory
	 * based on the full class path of the module we are currently running.
	 */
	function get_plugindir()
	{
		if (!$this->is_Plugin)
		{ 
			return false;
		}
		
		$classpath = $this->get_classpath();
		return substr($classpath,strpos($classpath,"plugin") + 7);
	} 

	/**
	 * Set our redirect array.
	 *
	 * @param String $rdo = 'do' parameter of URL
	 * @param String $ract = 'act' parameter of URL
	 */
	function set_redirect($rdo,$ract="")
	{
		$this->redirect['rdo'] = $rdo;
		$this->redirect['ract'] = $ract;
	}

	/**
	 * Get redirect URL.
	 * 
	 * @return URL
	 **/
	function get_redirect()
	{
		return "ract={$this->redirect['ract']}&amp;rdo={$this->redirect['rdo']}";
	}

	/**
	 * Set the read-only flag.
	 *
	 * @param boolean $_isreadonly
	 */
	function set_isreadonly($_isreadonly=false)
	{
		$this->isreadonly = $_isreadonly;
	}
	
	/**
	 * Return whether the user is logged in with a read-only group.
	 * 
	 * @return true/false
	 */
	function isreadonly()
	{
		return $this->isreadonly;
	}

	/**
	 * Check if OAT needs to be upgraded; i.e. check whether the version in connections.db
	 * matches OAT version.
	 *
	 * @return true if upgrade is needed.
	 */
	function checkForUpgrade()
	{
		$version = $this->get_version();
		$version = preg_replace("/[^0-9B]/","",$version);
		$split_ver = explode("B",$version);
		$maj_version = $split_ver[0];
		//$beta_version = $split_ver[1];

		require_once(ROOT_PATH."lib/connections.php");
		$conndb = new connections($this);
			
		$qry = "SELECT * FROM VERSION";
		$stmt = $conndb->db->query($qry);
		$err = $conndb->db->errorInfo();
		if ( $conndb->db->errorCode() != "00000" )
		{
			return 1;
		}
		$row = $stmt->fetch();
		$dbVersion = $row['VERSION_NUM'];
		$stmt->closeCursor();

		if ( $dbVersion < $maj_version )
		{
			return 1;
		}

		return 0;
	}

	/**
	 * Determine if a particular group is read-only.
	 * 
	 *  @param $group = group number
	 *  @return true/false
	 */
	function isGroupReadOnly($group)
	{
		if ( !isset($group) || $group == "")
		{
			return false;
		}

		require_once(ROOT_PATH."lib/connections.php");
		$conndb = new connections($this);

		$qry = "select * from groups where group_num = {$group} ";
		$stmt = $conndb->db->query($qry);
		$row = $stmt->fetch();
		return $row['READONLY'];
	}

	/**
	 * Setup the in array and clean the parameters passed from GET/POST.
	 **/
	function set_in()
	{
		$return = array(); /* temp variable used to hold the values from the GET and POST */

		/*
		 * Default the 'act' and 'do' params to ""
		 */
		$return['do'] = "";
		$return['act'] = "";

		//It is very difficult to sanitize pages where users can enter almost anything. 
		//Only sanitize pages where user input can be controlled.
		//If the user is using an old version of PHP (older than PHP 4.0.0), the data will not be sanitized.
		require_once(ROOT_PATH."lib/XSS.php");
		$sanitizable_content = XSS::is_htmlspecialchars_and_stripslashes_supported() && $this->is_content_sanitizable($_GET);
		
		if( !empty($_GET) )
		{
			foreach( $_GET as $i=>$v )
			{
				if( is_array($_GET[$i]) )
				{
					foreach( $_GET[$i] as $i2=>$v2 )
					{
						$return[$i][$i2] = $this->clean_data($v2,$sanitizable_content);
					}
				}
				else
				{
					$return[$i] = $this->clean_data($v,$sanitizable_content && !$this->is_password($i));	
				}
			}
		}

		if( !empty($_POST) )
		{
			foreach( $_POST as $i=>$v )
			{
				if( is_array($_POST[$i]) )
				{
					foreach( $_POST[$i] as $i2=>$v2 )
					{
						$return[$i][$i2] = $this->clean_data($v2,$sanitizable_content);
					}
				}
				else
				{
					$return[$i] = $this->clean_data($v,$sanitizable_content && !$this->is_password($i));
				}
			}
		}

		$this->in=$return;
	}  //end set_in

	/**
	 * This function is used for cross scripting prevention. It is needed to avoid sanitizing passwords. Browsers cannot use
	 * password fields to run script also user might have bad characters in their passwords that has no impact on
	 * security. 
	 * @return 
	 * @param object $key
	 */
	function is_password($key)
	{
		return trim($key) == 'group_pass' || trim($key) == 'grouppass' || trim($key) == 'PASSWORD' || trim($key) == 'userpass' || trim($key) == 'password' ;
	}

	function is_content_sanitizable($content)
	{
		$sanitizable = true;
		if( !empty($content) )
		{
			//Only sanitize the pages that appear before logging in
			$sanitizable =  !array_key_exists('act',$content) || $content['act'] == "login";
		}
		return $sanitizable;
	}

	/**
	 * Clean data: trim and remove slashes from data. 
	 * This function is used when processing browser 'input'.
	 * 
	 * @param data to clean
	 **/
	function clean_data($data,$sanitize=true)
	{
		if ($data == "")
		{
			return "";
		}

		$data = trim($data);
		if ( get_magic_quotes_gpc() )
		{
			$data = stripslashes($data);
		}
		
		if($sanitize)
		{
			$data = XSS::sanitize_data($data);
		}
		
		return $data;
	} // end clean_data

	/**
	 * Get a config parameter
	 *
	 * @param $what - parameter to get
	 * @param $default - default value of the parameter
	 **/
	function get_config($what,$default=NULL)
	{
		if ($what == "*")
		{
			return $this->config;
		}
			
		return isset($this->config[$what]) ? $this->config[$what] : $default;
	} // end get_config

	/**	
	 * Switch to another user.
	 * Unset the database private variable.
	 * Changes username and password in this idsadmin's phpsession->instance.
	 * 
	 * @param $who - new user name
	 * @param $pwd - new user's password
	 */
	function switch_user($who,$pwd)
	{
		$_SESSION['SQLTOOLBOX_USERNAME'] = $who;
		$_SESSION['SQLTOOLBOX_PASSWORD'] = $pwd;
	}

	/**
	 * Get a connection to a database on the database server.
	 *
	 * @param String $dbname
	 * @param boolean $use_user - whether to use SQL Toolbox user
	 * @param boolean $throw_fatal_error - throw fatal error if database connection fails
	 * @return PDO database connection
	 */
	function get_database($dbname="sysmaster",$use_user=false,$throw_fatal_error=false)
	{
		if ( ( isset($this->in['act']) )
		   &&( $use_user === true )
		   &&( ($this->in['act'] == "sqlwin" && $this->in['do'] != "dbtab") || $this->in['act'] == "qbe" )
		   &&( $this->get_config('SECURESQL',"on") == "on" ) ) 
		 {
			$sql_dbname="SQLTOOLBOX_".$dbname;
			if (! isset( $this->database[$sql_dbname] ) )
			{
				$this->set_database($dbname , $use_user, $throw_fatal_error);
			}
			return $this->database[$sql_dbname];			
		 }else{
			if (! isset($this->database[$dbname]))
			$this->set_database($dbname,$use_user,$throw_fatal_error);
	
			return $this->database[$dbname];	
		}
	}

	/**
	 * Unset database connection from our database array.
	 * This function is used if we switch the context of the connection using a database statement.
	 * 
	 * @param $dbname database name
	 */
	function unset_database($dbname)
	{
		if ((isset($this->in['act']))&&($this->in['act'] == "sqlwin")&&($this->get_config('SECURESQL') == "on")){
			$sql_dbname="SQLTOOLBOX_".$dbname;
			if (isset($this->database[$sql_dbname]))
			{
				unset($this->database[$sql_dbname]);
			}
		}else{
			if (isset($this->database[$dbname]))
			{
				unset($this->database[$dbname]);
			}
		}
	}

	/**
	 * Create a new database connection and store it in our database array.
	 *
	 * @param String $dbname
	 * $param boolean $user_user - use the SQL Toolbox user?
	 * $param boolean $throw_fatal_error - throw fatal error if database connection fails
	 */
	function set_database($dbname="sysmaster",$use_user=true,$throw_fatal_error=false)
	{
		if ((isset($this->in['act']))
		   &&( $use_user === true )
		   &&(($this->in['act'] == "sqlwin" && $this->in['do'] != "dbtab") || $this->in['act'] == "qbe" )
		   &&($this->get_config('SECURESQL','on') == "on") ) 
		   {
			$sql_dbname="SQLTOOLBOX_".$dbname;
			if (isset($this->database[$sql_dbname]))
			{
				return;
			}
			
			$locale = $this->get_locale($dbname);
			require_once(ROOT_PATH."/lib/database.php");
			$this->database[$sql_dbname] = new database($this,$dbname,$locale,$_SESSION['SQLTOOLBOX_USERNAME'],$_SESSION['SQLTOOLBOX_PASSWORD'],$throw_fatal_error);
		}else{
			if (isset($this->database[$dbname]))
			{
				return;
			}
	
			$locale = $this->get_locale($dbname);
	
			require_once(ROOT_PATH."/lib/database.php");
			$this->database[$dbname] = new database($this,$dbname,$locale,"","",$throw_fatal_error);
		}	
	}
	/**
	 * Get the locale of a database.
	 *
	 * @param String $dbname
	 * @return String locale
	 */
	function get_locale($dbname)
	{

		if ( $dbname == "sysmaster"
		|| $dbname == "sysadmin"
		|| $dbname == "sysha"
		|| $dbname == "sysuser"
		|| $dbname == "sysutils" )
		{
			return "";
		}

		$smdb = $this->get_database("sysmaster");
		$stmt = $smdb->query("select trim(dbs_collate) as dbs_collate from sysdbslocale where dbs_dbsname='{$dbname}'");
		$row  = $stmt->fetch();

		if (is_null($row['DBS_COLLATE']) || $row['DBS_COLLATE'] == "")
		{
			return "";
		}

		return $row['DBS_COLLATE'];
	}

	/**
	 * Set the current database.
	 *
	 * @param String $dbname
	 */
	function set_currdb($dbname="sysmaster")
	{
		if (isset($this->database[$dbname]))
		{
			$this->currdb = $this->database[$dbname];
			return;
		}
		$locale = $this->get_locale($dbname);
		$this->database[$dbname] =  new database($this,$dbname,$locale,"","");
	}

	/**
	 * Set a config parameter.
	 * 
	 * @param $what - parameter name
	 * @param $value - parameter value
	 **/
	function set_config($what,$value)
	{
		$this->config[$what] = $value;
	} #end set_config

	/**
	 * Sanity check php modules: PDO, pdo_informix, pdo_sqlite, soap.
	 * 
	 * If any required php modules are missing, this function prints
	 * a fatal error message and then dies.
	 **/
	function check_modules()
	{
		$required = array("PDO" => "true",
        	"pdo_informix" => "true",
        	"pdo_sqlite" => "true",
        	"soap" => "true");
		$missing = false;

		$plugins = get_loaded_extensions();
		foreach ($required as $module => $value)
		{
			if (!in_array($module, $plugins ) )
			{
				$required[$module] = false;
				$missing = true;
			}
		}

		// If any of required modules are missing, print error and die
		if ($missing)
		{
			$errmsg = "Not all required PHP modules are loaded. <BR><BR>";
			$errmsg .= "Missing module(s) : <BR> ";
			foreach ($required as $module => $value)
			{
				if (!$value)
				{
					$errmsg .= " -  $module <BR>";
				}
			}

			$this->fatal_error($errmsg, false, E_USER_ERROR);
			die();
		}
	} #end check_modules

	/**
	 * Set the INFORMIXDIR config parameter.
	 **/
	function set_INFORMIXDIR()
	{
		$INFORMIXDIR=getenv("INFORMIXDIR");
		if ( $INFORMIXDIR == "" || $INFORMIXDIR == NULL)
		{
			$this->fatal_error("INFORMIXDIR is not set.", false, E_USER_ERROR);
			die();
		}
		$this->set_config("INFORMIXDIR",$INFORMIXDIR);
	} #end set_INFORMIXDIR

	/**
	 * Load a template and save it in the template array.
	 * 
	 * @param $template_name
	 **/
	function load_template($template_name)
	{
			
		if ( !isset($this->template[$template_name]) )
		{
			$template_fname = ROOT_PATH."templates/{$template_name}.php";

			// If current module is plugin, template could be located
			// in the default location or in the plugin's templates directory.
			if ($this->isPlugin() == 1 && !file_exists($template_fname))
			{
				$template_fname = $this->get_classpath() ."/templates/{$template_name}.php";
			}

			// Check if template file exists
			if (!file_exists($template_fname))
			{
				$this->fatal_error("Cannot find template $template_name");
			}

			require_once($template_fname);

			$this->template[$template_name] = new $template_name($this);
			$this->template[$template_name]->idsadmin = &$this;
		}
		return ;
	}# end load_template

	/**
	 * Returns the OAT version.
	 *
	 * @return version string
	 **/
	function get_version()
	{
		return $this->version;
	}

	/**
	 * Returns the OAT build time.
	 * 
	 * @return build timestamp
	 **/
	function get_buildtime()
	{
		return $this->buildtime;
	}

	/**
	 * Get language file.
	 * 
	 * @param $file - file name
	 * @param $lang - language code
	 */
	function get_lang_file( $file, $lang="en_US" )
	{
		if ( $file != "menu" )
		{
			$fname = $this->get_classpath();
			if ( $this->isPlugin() == 0 )
			{
				/* remove the module directory */
				$fname = substr($fname,0,-8);   // remove the '/modules'
			}
		}
		else
		{
			$fname = ".";
		}

		if ( IN_ADMIN == 1 )
		{
			$fname = "../";
		}

		/* If we couldn't find it still, then check if its a services PHP file
		 * and check path relative to that */     
		if (!file_exists($fname . "/lang/$lang/lang_$file.xml"))
		{
		    if (isset($this->render) && (!$this->render))
		    {
		        // It is a service
		        
                        // Now is it a plugin service trying to load a regular OAT lang file?
                        if ( file_exists("../../../../lang/$lang/lang_$file.xml"))
                        {
                            $fname = "../../../..";
                        } elseif ( file_exists("../lang/$lang/lang_$file.xml")) {
                                // Or is it a plugin trying to load its own lang file?
                                $fname = "..";
                        } else {
                                // Or is it a regular service?
                                $fname = "../..";
                        }

		    }
		}
		
		/* Last path to try if we haven't found the lang file yet*/
		if (!file_exists($fname . "/lang/$lang/lang_$file.xml"))
		{
		    $fname = ".";
        }
        
		$fname = $fname . "/lang/$lang/lang_$file.xml";

		return $fname;

	}

	/**
	 * Load the language file for the plugin installed in a specific directory.
	 *
	 * @param string $dir - directory
	 */
	function load_plugin_menu_lang($dir)
	{
		if ( $dir == "" )
		{
			return;
		}

		// get the current language.
		$l = $this->phpsession->get_lang();
		if ( $l == "" )
		{
			$l = "en_US";
		}
		
		$fname = "";
		if ( IN_ADMIN == true ) 
		{
			$fname = "../";
		} 
		else if ($this->render == false)
		{
			$fname = "../../";
		}
		
		// As a fallback for incomplete translations, load English strings first.
		$fname = "{$fname}plugin/{$dir}/lang/en_US/lang_menu.xml";
		
		if ( file_exists($fname) )
		{
			$xml = simplexml_load_file( $fname );
			if (! is_null($xml))
			{
				foreach ( $xml as $k )
				{
					$name = (string)$k->getName();
					$this->language[$name]=(string)$xml->$name;
				}
			}
			unset($xml);
		}

		// Now we'll load the correct language file, which will overwrite all English strings
		// that translations exist for.
		if ($l != "en_US")
		{
    		$fname = str_replace("en_US",$l,$fname);
    		if ( file_exists($fname) )
    		{
    			$xml = simplexml_load_file( $fname );
    			if (! is_null($xml))
    			{
    				foreach ( $xml as $k )
    				{
    					$name = (string)$k->getName();
    					$this->language[$name]=(string)$xml->$name;
    				}
    			}
    			unset($xml);
    		}
		}
	} // end load_plugin_menu_lang
	
	/**
	 * Load a module's language file into the idsadmin  lang array.
	 * 
	 * @param $keyword - module keyword that specifies which lang
	 *        file to load.  The file named lang_{$keyword}.xml 
	 *        will be loaded. 
	 **/
	function load_lang($keyword)
	{
		$l = $this->phpsession->get_lang();
		if ( $l == "" )
		{
			$l="en_US";
		}

		$lang = array();
		$fname = $this->get_lang_file($keyword,$l);

		if ( file_exists($fname) )
		{
			$xml = simplexml_load_file( $fname );
		}
		else
		{
			// let's see if it's in the plugins ..
			$fname =  ( $this->get_lang_file( $keyword ) ) ;
			if ( file_exists( $fname ) )
			{
				$xml = simplexml_load_file( $fname );
			}
		}

		if (! is_null($xml))
		{
			foreach ( $xml as $k )
			{
				$name = (string)$k->getName();
					
				$this->language[$name]=(string)$xml->$name;
			}
		}

		unset($xml);
		$this->set_last_lang_file($fname);

	}# end load_lang


	/**
	 * Remove a parameter from the url.
	 * 
	 * @param $toremove - parameter to remove
	 * @param $url - URL
	 *****************************************/
	function removefromurl($toremove="",$url="")
	{
		if ($url == "")
		$url = $_SERVER['REQUEST_URI'];
		return preg_replace('/&'.$toremove.'\=[^&]*/', '',$url);
	} // end removefromurl

	/**
	 * Set the last language file loaded.
	 * 
	 * @param $file
	 */
	function set_last_lang_file($file)
	{
		if (isset($this->last_lang_file))
		{
			array_push($this->last_lang_file, $file);
		}
		else
		{
			$this->last_lang_file = Array($file);
		}

	} // end set_last_lang_file

	/**
	 * Get the last language file that was loaded.
	 * 
	 * @param file name
	 */
	function get_last_lang_file()
	{
		return $this->last_lang_file;
	} // end get_last_lang_file

	/**
	 *  function lang( $item, $parameters=array() )
	 *
	 *  Find the specified message in the correct language.
	 *  
	 *  If the current language is not English and we cannot
	 *  find the specified message, then look it up in English.
	 *  If it is not available in English, print out an error.
	 *
	 *  Optional parameters will get substituted for '{0}',
	 *  '{1}', '{2}', etc.
	 **/
	function lang($item, $parameters=array())
	{
			
		$item=trim($item);

		if ( $item == "" )
		{
			return ;
		}

		if( isset($this->language["{$item}"]) && !empty( $this->language["{$item}"] ) )
		{
			return $this->lang_substitute_parameters($this->language["{$item}"], $parameters);
		}

		/*
		 * If the translation files are correct we will never
		 * get here, but if a foreign lang file is missing a
		 * string, then try and look it up in the English file.
		 */

		$lang_array = $this->get_last_lang_file();
		foreach ($lang_array as $langfile)
		{
			$langfile = str_replace($this->phpsession->get_lang(),"en_US",$langfile);
			if (file_exists($langfile))
			{
				$xml_lang = simplexml_load_file($langfile);
				if( isset( $xml_lang->{$item} ) && !empty(  $xml_lang->{$item}  ) )
				{
					return $this->lang_substitute_parameters((string)$xml_lang->{$item}, $parameters);
				}
			}
		}
		
		/* If we can not find what we want print out what we are missing. */
		return "MISSING LANG FILE ITEM $item";
	} // end lang

	/**
	 *  function lang_substitute_parameters( $string, $parameters=array() )
	 *
	 *  Substitute parameters into our localized string.
	 *  Optional parameters will get substituted for '{0}', '{1}', '{2}', etc.
	 **/
	private function lang_substitute_parameters($string, $parameters=array())
	{
	    for ($i = 0; $i < count($parameters); $i++)
	    {
	        $replace = "/\\{" . $i . "\\}/";
	        $string = preg_replace($replace,$parameters[$i],$string);
	    }
	    return $string;

	} // end lang_substitute_parameters

	/**
	 * Display an error that has occured in a red box.
	 * 
	 * @param string $msg
	 */ 
	function error($msg="")
	{
		$html = $this->template["template_global"]->global_error($msg);
		$this->html->add_to_output($html);
	} //end error

	/**
	 * Display an error that occurs when connecting to the database.
	 *
	 * @param string $msg
	 */
	function db_error($msg="",$title="")
	{
		/*
		 * Check if we are actually rendering output and this not part of a web service call.
		 * If we aren't rendering output, then use trigger_error and hopefully this will get caught by the web service.
		 */
		if ( $this->render == false )
		{
			$msg .= " ROOT_PATH=".ROOT_PATH;
			trigger_error(htmlentities($msg,ENT_COMPAT,"UTF-8"),E_USER_ERROR);
		}

		$html = $this->template["template_global"]->global_error($msg, $title);
		$this->html->add_to_output($html);
		$this->html->render();
		die();
	} //end db_error
	
	
	/**
	 * Display a fatal error message in a red box then die.
	 * 
	 * @param string $msg
	 * @param boolean $render_menu - indicates whether the OAT menu should be rendered along with the error.
	 **/
	function fatal_error($msg="",$render_menu=true)
	{
		$this->fatal_error_cnt++;
		
		/*
		 * Check if we are actually rendering output and this not part of a web service call.
		 * If we aren't rendering output, then use trigger_error and hopefully this will get caught by the web service.
		 */
		$html = $this->template["template_global"]->global_error($msg);
		if ( $this->render == false )
		{
			$msg .= " ROOT_PATH=".ROOT_PATH;
			trigger_error(htmlentities($msg,ENT_COMPAT,"UTF-8"),E_USER_ERROR);
		}
		
		if (! $render_menu || $this->fatal_error_cnt > 1)
		{
			$this->iserror=true;
		}
				
		$this->html->add_to_output($html);
		$this->html->render();
		die();
	} //end fatal_error

	/**
	 * Format an integer byte value into most appropriate human readable form (KB, MB, GB, etc).
	 * 
	 * @param integer $value in bytes
	 * @param integer $precision
	 * @return value with a suffix (e.g. "8 MB") 
	 */
	function format_units($value,$prec=2)
	{
		$suffix = array("B","KB","MB","GB","TB","PB","EB");
		//assume value is byte for now ..
		$cnt = 0;
		while ( $value >= 1024 )
		{
			$cnt++;
			$value /= 1024;
		}
			
		$value = round($value,$prec);
			
		return $value." ".$suffix[$cnt];
			
	} // end format_units

	/**
	 * Display a status message.
	 * 
	 * @param string $msg
	 **/
	function status($msg="")
	{
		$html = $this->template["template_global"]->global_status($msg);
		$this->html->add_to_output($html);
	} //end status

	/**
	 * Convert a time interval in seconds to a string representing "days hours:mins:sec"
	 * 
	 * @param $sec number of seconds
	 * @param string "days hours:min:sec"
	 */ 
	function timedays($sec)
	{
		$this->load_lang("updstats");
		$days = "";
		$hours = intval(intval($sec) / 3600);
		if ($hours > 24) {
			$days  = floor($hours / 24)." ".$this->lang("days");
			$hours = $hours % 24;
		}
		$hours = str_pad($hours, 2, "0", STR_PAD_LEFT).":";
		$minutes = intval(($sec / 60) % 60);
		$minutes = str_pad($minutes, 2, "0", STR_PAD_LEFT).":";
		$seconds = intval($sec % 60);
		$seconds = str_pad($seconds, 2, "0", STR_PAD_LEFT);
		return "{$days} {$hours}{$minutes}{$seconds}";
	} #end timedays

	/**
	 * Save the current url parameters.
	 */
	function saveurl()
	{
		$this->phpsession->set_lasturl($_SERVER['REQUEST_URI']);
	}

	/**
	 * Get the list of database on the database server and create a drop-down select control.
	 * 
	 * @return an HTML select control listing all database names
	 */
	function getDatabaseSelect()
	{
	    // If we are in the sql part of the Toolbox, we want to redirect 
	    // the user back to that part, so we add default to our url so that 
	    // we can check this later in sql_dbconnect.
		if ( $this->in['do'] == "sqltab" || $this->in['do'] == "sqlrestab" )
		{
			$default = "&amp;default=1";
		}
		$db = $this->get_database("sysmaster");
		$qry = "select name as dbname from sysmaster:sysdatabases order by dbname";
		$stmt = $db->query($qry);
		$dbselect = $this->template["template_global"]->switchDatabaseJscript();
		$dbselect .= "<form name='dbswitch' method='post' action=\"index.php?act=sqlwin&amp;do=connect{$default}\">"
		."<select name=\"val\" onchange='switchDatabase(this)' class='switchSelect'>";
		while ($res = $stmt->fetch())
		{
			$dbname = trim($res['DBNAME']);
			if (strcmp($dbname, $this->phpsession->get_sqldbname()) == 0)
			{
				$dbselect .= "<option class=\"switchSelectOption\" value=\"{$dbname}\" selected=\"yes\">{$dbname}</option>";
			} else {
				$dbselect .= "<option class=\"switchSelectOption\" value=\"{$dbname}\">{$dbname}</option>";
			}
		}
		$dbselect .= "</select></form>";
		$stmt->closeCursor();
		return $dbselect;
	}

	/**
	 * Get the list of database servers in my OAT current group
	 * and create a drop-down control listing all servers.
	 * 
	 * @return an HTML select control listing all servers in the current OAT group
	 */
	function getServers()
	{
	    if ( $this->iserror )
	    {
	    	return;
	    }
	    
		if ( $this->phpsession->get_group() == "" )
		{
			return  $this->phpsession->instance->get_servername();
		}

		require_once("lib/connections.php");
		$conndb = new connections($this);

		$sql = "select conn_num,server,host from connections where group_num='{$this->phpsession->get_group()}' order by server";
		$stmt = $conndb->db->query($sql);

		$html = "";
		$options = "";
		$found = false;
		while ( $row = $stmt->fetch() )
		{
			$selected = "";
			if ( $this->phpsession->instance->get_servername() == $row['SERVER'] &&
                             $this->phpsession->instance->get_conn_num() == $row['CONN_NUM'] )
			{
				$selected = "selected='selected'";
				$found=true;
			}

			$options .= "<option class='switchSelectOption' value='{$row['CONN_NUM']}' {$selected}>{$row['SERVER']}@{$row['HOST']}</option> ";
		}
		$stmt->closeCursor();

		// If our current server wasn't found, then add it to the list.
		if ( $found === false )
		{
			$options .= "<option class='switchSelectOption' value='' selected='selected'>{$this->phpsession->instance->get_servername()}@{$this->phpsession->instance->get_host()}</option>";
		}
		$html  = $this->template["template_global"]->switchServerJscript();
		$html .= "<form method='post' action='index.php?act=login&amp;do=loginnopass&amp;{$this->get_redirect()}' name='serverswitch' >";
		$html .= " <select onchange='switchServers(this)' name='conn_num' class='switchSelect' style='vertical-align: middle'>{$options}</select>";
		$html .= "</form>";
		return $html;
	}

	/**
	 * Get the list of locales language on server and create a drop-down control for user selection of them.
	 * 
	 * @return an HTML select control listing all DB_LOCALE languages on the current server
	 */
	function getLocaleLanguageSelect()
	{
		if ( $this->iserror )
		{
			return;
		}
		
		$this->load_lang("language");
		
		$options = "";

		$avail_lang_list = $this->phpsession->get_dblc_avail_lang_list();
		if (is_null($avail_lang_list))
		{
			// Get list of available DBLOCALE languages
			$avail_lang_list = $this->getDBLocaleLanguages(true);
			$this->phpsession->set_dblc_avail_lang_list($avail_lang_list);
		}	

		$dblclang = $this->phpsession->get_dblclang();

		$track = 0;
		$dataArr = array();
		while ($track < count($avail_lang_list) ) 
		{
			$lang = $avail_lang_list[$track]['LANGUAGE'];
			if ($lang == "unknown") 
			{
				$track++; 
				continue; 
			}

			$selected = "";
			if ($lang == $dblclang) 
			{ 
				$selected = "selected='yes'"; 
			}
			
			$lang_keyword = trim($lang);
			$lang_keyword = str_replace(" ", "_", $lang_keyword);
			$lang_localized = $this->lang($lang_keyword);
			if (strpos($lang_localized,"MISSING LANG FILE ITEM") !== false)
			{
				// If language not stored in the message file, use the language as stored in the database table
				$lang_localized = trim($lang);
			}
			$options .= "<option class='switchSelectOption' value='{$lang}' {$selected}>{$lang_localized}</option> ";
		
			$track++;
		}
		
		$html .= "<select onchange='switchLocales(this)' name='dblclang' class='switchSelect' style='vertical-align: middle'>{$options}</select>";
		return $html;
	}
	
	/**
	 * Get the list of locales on server for the current language and creat a drop-down control for user selection of them.
	 * 
	 * @return an HTML select control listing all DB_LOCALE languages on the current server
	 */
	function getLocaleSelect()
	{
		if ( $this->iserror )
		{
			return;
		}
		
		$options = "";
		
		$dblclang = $this->phpsession->get_dblclang();
		$dblcname = $this->phpsession->get_dblcname();
		
		$avail_locale_list = $this->phpsession->get_dblc_avail_locale_list();
		if (is_null($avail_locale_list))
		{
			// Get list of available DBLOCALE languages
			$avail_locale_list = $this->getDBLocales($dblclang, false);
			$this->phpsession->set_dblc_avail_locale_list($avail_locale_list);
		}	
		
		$dataArr = array();
		foreach ($avail_locale_list as $row) 
		{
			$locale = $row['NAME'];
			if (strpos($locale, ' ') != false) 
			{ 
				continue; 
			}
			
			$selected = "";
			if (is_null($dblcname))
			{
				// $dblcname is null if the user just changed the language drop-down
				// in this case, set $dblcname to the first option in the locale drop-down
				$selected = "selected='yes'";
				$this->phpsession->set_dblcname($locale);
				$dblcname = $locale;
			}
			else if ($locale == $dblcname) 
			{
                 $selected = "selected='yes'";
                 $this->phpsession->set_dblcname($locale);
            }
			
            $options .= "<option class='switchSelectOption' value='{$locale}' {$selected}>{$locale}</option> ";
		}
		
		$html .= "<select onchange='switchEncoding(this)' name='dblcname' class='switchSelect' style='vertical-align: middle'>{$options}</select>";
		return $html;
	}

	/**
	 * Experimental: bread crumbs.
	 *
	 * @param string $title
	 * @param string $link
	 */
	function addCrumb($title,$link="")
	{
		if ( $link == "" )
		{
			$link = $this->phpsession->get_lasturl();
		}

		if ( $link == "NONE" )
		{
			$link = "";
		}

		$this->crumb[] = array( "title" => $title , "link" => $link );
	}

	/**
	 * Get the current menu item,
	 * i.e. which menu item should be highlighted.
	 * 
	 * @return current menu item
	 */
	function getCurrMenuItem()
	{
		return $this->currMenuItem;
	}

	/**
	 * Sets the current menu item
	 * i.e. which menu item should be highlighted
	 * 
	 * @param current menu item
	 */
	function setCurrMenuItem($item="")
	{
		$this->currMenuItem = $item;
	}

	/**
	 * Transform the query based on the rows per page, current page number,
	 * and sort column to only return the desired subset of the data.
	 * 
	 * @param rows_per_page, null or -1 indicates return all data
	 * @param page, number of the current page (starts from page 1, not 0).
	 * @param sort_column, column or columns for the order by clause.  If sort 
	 *        direction matters, ASC or DESC should be appended to the column name, 
	 *        e.g. $sort_col = "col1 DESC".  A null value indicates there is 
	 *        no column to sort by.
	 * @return new version of the query
	 */
	function transformQuery($query, $rows_per_page = NULL, $page = 1, $sort_col = NULL)
	{
		// Trim off any beginning spaces from the query
		$query = ltrim($query);
		
		// Handling paging parameters
		if ($rows_per_page != NULL && $rows_per_page != -1)
		{
			$transform = " FIRST $rows_per_page ";
			if ($page > 1)
			{
				$transform = " SKIP " . ($rows_per_page * ($page - 1)) . " " . $transform; 
			}
			
			// First six characters will always be 'select'.  We need to insert the $transform string
			// after the 6th character.
			$query = substr($query,0,6) . $transform . substr($query,6);
		}
		
		// Handle the sort or order by clause
		if ($sort_col != NULL)
		{
			$query .= " ORDER BY " . $sort_col;
		}

		return $query;
	}
	
	/**
	 * Create a count query, by wrapping the given query inside a 'select count (*) from (....)"
	 * 
	 * @param $query - current query
	 * @param count query
	 */
	function createCountQuery($query)
	{
		$countQuery = "SELECT COUNT(*) as count FROM ($query)";
		return $countQuery;
	}	
	
	/** 
	 * Check and enforce the minimum server version for a plugin.
	 **/
	function checkPluginMinServerVersion()
	{
		if (!$this->is_Plugin)
		{
			return;
		}
		
		// Get plugin directory name
		$plugindir = $this->get_plugindir();
		
		// Find plugin minimum version from connections.db 
		require_once(ROOT_PATH."lib/connections.php");
		$conndb = new connections($this);
		$qry = "select plugin_name, plugin_server_version from plugins where plugin_dir='{$plugindir}'";
		$stmt = $conndb->db->query($qry);
		$err = $conndb->db->errorInfo();
		if ( $conndb->db->errorCode() != "00000" )
		{
			return;
		}
		$row = $stmt->fetch();
		$plugin_name = $row['PLUGIN_NAME'];
		$plugin_server_version_str = $row['PLUGIN_SERVER_VERSION'];
		$stmt->closeCursor();
		if ($plugin_server_version_str == "")
		{
			// Nothing to do if the plugin doesn't have a minimum server version
			return;
		}

		// Convert plugin server version string to a version object
		require_once(ROOT_PATH."lib/version.php");
		$plugin_server_version_obj = new Version($plugin_server_version_str);
		
		// Check and enforce the minimum server version for the plugin
		require_once(ROOT_PATH."lib/feature.php");
		if ($plugin_server_version_obj->compareTo($this->phpsession->serverInfo->getVersion()) > 0)
		{
			// Plugin minimum required version is higher than the current server version, so display a fatal error
			$this->fatal_error($this->lang('PluginRequiredVersionError', array($plugin_name, $plugin_server_version_str)));
		} 
	}
	
	/**
	 * Check if there is a certain group in the sysadmin:ph_group table.
	 * If not, add the group to the ph_group table.
	 * 
	 * @param $feature - group name
	 * @param $desc - group description
	 */ 
	function checkForFeature($feature , $desc="")
	{
	    
	    $db = $this->get_database("sysadmin");
	    
    	// Check if '{$feature}' exists in ph_group
    	$qry = "select COUNT(*) as GROUPEXISTS from ph_group where group_name = '{$feature}'";	    	
	    $stmt = $db->query($qry);
	    $res = $stmt->fetch();
	    $stmt->closeCursor(); 	    		    		    	    	
	    if (count($res) == 1)
	    {
	    	if ($res['GROUPEXISTS'] == 1)
	    	{	
	    		// '{$feature}' group already exists, so return
	    		return;    			
	    	}
	    }
	    
	    // If not, create the group
    	$qry = "insert into ph_group(group_name,group_description) "
    		 . "values ('{$feature}','{$desc}')";
    	$result = $db->query($qry);
		return $result;    			
    
	}
	
	/**
	 * This function checks whether the user that is currently logged into OAT is
	 * the user informix or a DBSA.
	 * 
	 * Note: We only have the ability to check for a DBSA user on Informix 12.10 or higher,
	 * so for earlier server versions this function just checks for the user informix.
	 */
	function checkForUserInformixOrDBSA() 
	{
    	$username = $this->phpsession->instance->get_username();
    	require_once 'lib/feature.php';
    	if (Feature::isAvailable(Feature::CENTAURUS, $this))
    	{
    		// For 12.10, we can use the admin_check_auth to check for informix or a DBSA users
    		$db = $this->get_database("sysadmin");
    		$sql = "select admin_check_auth('{$username}') as auth from sysmaster:sysdual";
    		$stmt = $db->query($sql);
    		$res = $stmt->fetch();
    		$valid_user = ($res['AUTH'] == 1);
    	} else {
    		// For older server versions, we just check if the user name is informix
    		$valid_user = (trim($username) == "informix");
    	}
    	
    	return $valid_user;
	}
	
    /**
     * Tests if the admin_async UDR exists in the sysadmin database.
     * If not, this function creates the admin_async procedure.
     * 
     * @param $feature - group name required in the sysdmin:ph_group table
     * @param $desc - group description
     **/    
    function testAndDeployAdminAsync($feature="",$desc="")
    {
    	// First check if DELIMIDENT is set as an env variable on the connection.
    	// The following statements for deploying the admin_async will not work
    	// with DELIMIDENT, so we'll need to ensure we are running this on a connection
    	// without DELIMIDENT.
    	$connection_reset = false;
    	if (strcasecmp($this->phpsession->instance->get_delimident(), "Y") == 0)
    	{
    		// DELIMIDENT is set, so save off the env variable setting and reset the connection.
    		$connection_reset = true;
    		$saved_delimident_value = $this->phpsession->instance->get_delimident();
    		$this->phpsession->instance->set_delimident("");
    		$saved_envvars = $this->phpsession->instance->get_envvars();
    		$this->phpsession->instance->set_envvars("");
    		$this->unset_database("sysadmin");
    	}
    	
    	// Check if the admin_async procedure already exists
    	$qry = "select COUNT(*) as UDREXISTS from sysprocedures where procname = 'admin_async'";
    	$db = $this->get_database("sysadmin");	
    	$stmt = $db->query($qry);
    	    	
	    $res = $stmt->fetch();
	    $stmt->closeCursor();
	    
	    if (count($res) == 1)
	    {
	    	if ($res['UDREXISTS'] == 1)
	    	{	
	    		// procedure already exists, so return
	    		
	    		// lets do check for feature in ph_group ..
	    		if ( $feature != "" )
	    		{ 
	    		    $this->checkForFeature($feature,$desc);
	    		}	    			
	    	}
	    }
	    	    
	    if ( $res['UDREXISTS'] != 1 )
	    {
	    // If not, create the procedure
    	$qry = <<<EOM
CREATE FUNCTION admin_async(cmd lvarchar(4096),
                          cur_group CHAR(129),
                          comments lvarchar(1024)
                               DEFAULT "Background admin API",
                          start_time DATETIME hour to second
                               DEFAULT CURRENT hour to second,
                          end_time   DATETIME hour to second
                               DEFAULT NULL,
                          frequency  INTERVAL day(2) to second
                               DEFAULT NULL,
                          monday    BOOLEAN DEFAULT 't',
                          tuesday   BOOLEAN DEFAULT 't',
                          wednesday BOOLEAN DEFAULT 't',
                          thursday  BOOLEAN DEFAULT 't',
                          friday    BOOLEAN DEFAULT 't',
                          saturday  BOOLEAN DEFAULT 't',
                          sunday    BOOLEAN DEFAULT 't'
                          )
   RETURNING INTEGER
   DEFINE ret_task_id  INTEGER;
   DEFINE del_time     INTERVAL DAY TO SECOND;
   DEFINE id           INTEGER;
   DEFINE task_id      INTEGER;
   DEFINE seq_id       INTEGER;
   DEFINE cmd_num      INTEGER;
   DEFINE boot_time    DATETIME YEAR TO SECOND;

--SET DEBUG FILE TO "/tmp/debug.out";
--TRACE ON;

   IF cur_group IS NULL THEN
       LET cur_group = "MISC";
   END IF

   SELECT FIRST 1 value::INTERVAL DAY TO SECOND INTO del_time FROM ph_threshold
      WHERE name = "BACKGROUND TASK HISTORY RETENTION";
   IF del_time IS NULL THEN
       LET del_time = 7 UNITS DAY;
   END IF

    BEGIN
        ON EXCEPTION IN ( -310, -316 )
        END EXCEPTION WITH RESUME

            CREATE TABLE job_status (
               js_id         SERIAL,
               js_task       INTEGER,
               js_seq        INTEGER,
               js_comment    LVARCHAR(512),
               js_command    LVARCHAR(4096),
               js_start      DATETIME year to second
                             DEFAULT CURRENT year to second,
               js_done       DATETIME year to second DEFAULT NULL,
               js_result     INTEGER
           );
            CREATE INDEX job_status_ix1 ON job_status(js_id);
            CREATE INDEX job_status_ix2 ON job_status(js_task);
            CREATE INDEX job_status_ix3 ON job_status(js_result);
     END

     BEGIN
        ON EXCEPTION IN ( -8301 )
        END EXCEPTION WITH RESUME

        CREATE SEQUENCE background_task START 1 NOMAXVALUE ;

     END

    INSERT INTO ph_task
        ( tk_name,
        tk_description,
        tk_type,
        tk_group,
        tk_execute,
        tk_start_time,
        tk_stop_time,
        tk_frequency,
        tk_Monday,
        tk_Tuesday,
        tk_Wednesday,
        tk_Thursday,
        tk_Friday,
        tk_Saturday,
        tk_Sunday,
        tk_attributes
        )
        VALUES
        (
        "Background Task ("||  background_task.NEXTVAL ||")",
        TRIM(comments),
        "TASK",
        cur_group,
        "insert into job_status (js_task, js_seq , js_comment,js_command) VALUES(\$DATA_TASK_ID,\$DATA_SEQ_ID, '"||TRIM(comments)||"','"||TRIM(REPLACE(REPLACE(cmd,"'"),"""") )||"' ); update job_status set (js_result)= ( admin("||TRIM(cmd)||") ) WHERE js_task = \$DATA_TASK_ID AND js_seq = \$DATA_SEQ_ID ;update job_status set (js_done)  = ( CURRENT ) WHERE js_task = \$DATA_TASK_ID AND js_seq = \$DATA_SEQ_ID ",
        start_time,
        end_time,
        frequency,
        monday,
        tuesday,
        wednesday,
        thursday,
        friday,
        saturday,
        sunday,
        8);

   LET ret_task_id = DBINFO("sqlca.sqlerrd1");

   /* Cleanup the job_status table */

   SELECT dbinfo('UTC_TO_DATETIME',sh_boottime)
          INTO boot_time
           FROM sysmaster:sysshmvals;

   FOREACH  SELECT js_id, js_task, js_seq, js_result
        INTO id, task_id,  seq_id, cmd_num
        FROM job_status J, OUTER ph_run, command_history
        WHERE  ( CURRENT - js_done > del_time OR
                (js_start < boot_time AND js_done IS NULL ) )
        AND    js_task = run_task_id
        AND    js_seq  = run_task_seq
        AND    js_result = ABS(cmd_number)

       DELETE FROM ph_run WHERE run_task_id = task_id
                          AND run_task_seq = seq_id;
       DELETE FROM command_history WHERE cmd_number = cmd_num;
       DELETE FROM job_status WHERE js_id = id;

       -- Cleanup the task table only if this is not a repeating task
       DELETE FROM ph_task WHERE tk_id = task_id AND tk_next_execution IS NULL;

   END FOREACH

   RETURN  ret_task_id;
END FUNCTION;
   
EOM
;    	
    	try {
			$stmt = $db->query($qry, false, true);
    	} catch (PDOException $e) {
    		// Check for SQL errors related to out-of-space.  
    		// If so, suppress them, so the Storage page can potentially keep loading.
    		// We'll try again to deploy the procedures the next time the user comes 
    		// to the page when there is enough space.
    		$err_code = $e->getCode();
    		$err_msg = $e->getMessage();
    		if ($err_code == -312 || $err_code == -261 || $err_code == -212)
    		{
    			error_log("Error in idsadmin.php deploying the admin_async procedure due to space issues. Ignoring this error and proceeding to load the page.");
    			error_log($err_code . " " . $err_msg);
    			return;
    		} else {
    			$this->db_error("{$this->lang('ErrorF')} {$err_code} <br/> {$err_msg} <br/><br/>{$this->lang('QueryF')}<br/> {$qry} ");
    		}
    	}
    	
    	// With the admin_async procedure, we also need to add into the ph_threshold
    	// table the 'BACKGROUND TASK HISTORY RETENTION' threshold.
    	$qry = <<<EOM
INSERT INTO ph_threshold
(name,task_name,value,value_type,description)
VALUES
("BACKGROUND TASK HISTORY RETENTION", "Cleanup background tasks","7 0:00:00","NUMERIC",
"Remove all dormant background tasks that are older than then the threshold.")
EOM
; 	
		$result = $db->query($qry);
	    }
	    
		// Now let's create the job_status table so that we can potentially use it before 
		// running the procedure. 
		
	    $qry = "select count(*) as cnt from systables where tabname = 'job_status'";
	    $stmt = $db->query($qry);
	    $row = $stmt->fetch();
	    $stmt->closeCursor();
	   
	    if ( $row['CNT'] == 0 )
	    {
	    
		$qry = <<<EOM
		            CREATE TABLE job_status (
               js_id         SERIAL,
               js_task       INTEGER,
               js_seq        INTEGER,
               js_comment    LVARCHAR(512),
               js_command    LVARCHAR(4096),
               js_start      DATETIME year to second
                             DEFAULT CURRENT year to second,
               js_done       DATETIME year to second DEFAULT NULL,
               js_result     INTEGER
           );
EOM;
		
		try {
			// Create the job_status table
			$db->query($qry, false, true);
			
			// Now create the indexes.
			$qry = " CREATE INDEX job_status_ix1 ON job_status(js_id); ";
	        $db->query($qry, false, true);
	        $qry = " CREATE INDEX job_status_ix2 ON job_status(js_task) ";
	        $db->query($qry, false, true);
	        $qry = " CREATE INDEX job_status_ix3 ON job_status(js_result) ";
	        $db->query($qry, false, true);
	    } catch (PDOException $e) {
    		// Check for SQL errors related to out-of-space.  
    		// If so, suppress them, so the Storage page can potentially keep loading.
    		// We'll try again to deploy the procedures the next time the user comes 
    		// to the page when there is enough space.
    		$err_code = $e->getCode();
    		$err_msg = $e->getMessage();
    		if ($err_code == -312 || $err_code == -261 || $err_code == -212)
    		{
    			error_log("Error in idsadmin.php deploying the job_status table due to space issues. Ignoring this error and proceeding to load the page.");
    			error_log($err_code . " " . $err_msg);
    			return;
    		} else {
    			$this->db_error("{$this->lang('ErrorF')} {$err_code} <br/> {$err_msg} <br/><br/>{$this->lang('QueryF')}<br/> {$qry} ");
    		}
    	}
	    }
	    
        $this->checkForFeature($feature,$desc);
        
    	// If we had to reset the connection due to the delimident setting, restore it now.
    	if ($connection_reset)
    	{
    		$this->unset_database("sysadmin");
    		$this->phpsession->instance->set_delimident($saved_delimident_value);
    		$this->phpsession->instance->set_envvars($saved_envvars);
    	}
        
    	return;    			
    } 
    
	/**
	 * Use this function to execute statements on the IDS server
	 */
    public function doDatabaseWork($sel,$dbname="sysmaster",$exceptions=false,$conn=null,$params=array(),$locale=null)
    {
        $ret = array();
		
        if ($conn != null)
        {
        	$db = $conn;
        } else if ($locale == null) {
        	$db = $this->get_database($dbname,false,true);
        } else {
        	require_once(ROOT_PATH."/lib/database.php");
        	$db = new database($this,$dbname,$locale,"","",true);
        }
        
        while (1 == 1)
        {        	
        	/* If we have parameters, we'll prepare then execute the query. */
			if (count($params) == 0)
        		$stmt = $db->query($sel, false, $exceptions);
			else {
				$stmt = $db->prepare($sel);
				$stmt->execute($params);
			}
        		
            $colcount = $stmt->columnCount();
            $colname = array();
            // Following loop identifies the columns with type = "TEXT"
            // Later we need to convert this type of data before sending it back to the flex side
            // Otherwise flex web service will not be able to handle it correctly
            for($cnt = 0; $cnt < $colcount; $cnt++)
            {
                $meta = $stmt->getColumnMeta($cnt);
                if("TEXT" == $meta["native_type"])
                	$colname[] = $meta["name"];
            }
            
            while($row = $stmt->fetch(PDO::FETCH_ASSOC))
            {
				// Following loop actually converts the data into stream,
				// for the columns, identified in the previous loop
            	foreach($colname as $name)
            	{
            		$row[$name] = stream_get_contents($row[$name]);
            	}
            	$ret[] = $row;
            }
            
            $err = $db->errorInfo();
            if ( $err[2] == 0 )
            {
                $stmt->closeCursor();
                break;
            }
            else
            {
                $err = "{$this->lang("Error")}: {$err[2]} - {$err[1]}";
                $stmt->closeCursor();
            	if ( $this->render == false )
                {
                	trigger_error($err,E_USER_ERROR);
                } else {
                	$this->fatal_error($err);
                }
                continue;
            }
        }
        return $ret;
    }

    /**
     * Execute SQL Admin API Command
     *
     * Execute SQL Admin API Command specified in $task
     * $task will be an array of the following format:
     * 		$task['COMMAND'] --> SQL Admin API command
     * 		$task['PARAMETERS'] --> Parameters for the SQL Admin API Command
     *
     * When $conn_num is -1, use OAT's current server connection.  
	 * When $conn_num is not -1, use this connection from the connenctions.db
	 *
	 * Return values:
	 * The result of the command will be stored in the $task array as follows:
	 * 		$task['SUCCESS'] --> true/false, based on result of Admin API command execution 
	 * 		$task['RESULT_MESSAGE'] --> success or failure message
	 *      $task['RETURN_CODE'] --> return code of the command
     */
    public function executeSQLAdminAPICommand($task, $conn_num = -1)
    {
    	// Get database connection.
    	// If conn_num != -1, use that server instead of the one OAT is connected to
		$db = null;
		if ($conn_num != -1)
		{
    		try {
    			// fix - getServerConnection() not in idsadmin.php, it's in ERServer.php
    			$db = $this->getServerConnection($conn_num, self::SYSADMIN);
        	} catch(PDOException $e) {
        		$err = $e->errorInfo();
            	$err = "{$this->lang("Error")}: {$err[2]} - {$err[1]}";
            	if ( $this->render == false )
            	{
            		trigger_error($err_str,E_USER_ERROR); 
            	} else {
            		$this->fatal_error($err);
            	}
        	}
        } else {
	        $db = $this->get_database("sysadmin");
        }

        if (!isset($task['COMMAND']))
        {
        	$task['SUCCESS'] = false;
            $task['RESULT_MESSAGE'] = "Missing SQL Admin API command name.";
            return $task;
        }
    	if (!isset($task['PARAMETERS']))
        {
        	$task['SUCCESS'] = false;
            $task['RESULT_MESSAGE'] = "Missing SQL Admin API command parameters.";
            return $task;
        }
        
        $command = $task['COMMAND'];
        $parameters = $task['PARAMETERS'];
		
        // Build up SQL statement
		$sql = "execute function admin ( {$command} {$parameters} )";
		// Execute SQL Admin API command
		try
		{
			$stmt = $db->query($sql, false, true);
		} 
		catch (PDOException $e)
		{
			$task['SUCCESS'] = false;
			$task['RETURN_CODE'] = $e->getCode(); 
			$task['RESULT_MESSAGE'] = "{$this->lang("Error")}: {$e->getCode()} - {$e->getMessage()}";
			return $task;
		}
		
        // Check for success or errors
        $err = $db->errorInfo();
        if (isset($err[1]) && $err[1] != 0)
        {
            $task['SUCCESS'] = false;
            $task['RESULT_MESSAGE'] = "{$this->lang("Error")}: {$err[2]} - {$err[1]}";
            return $task;
        }
        
        // Retreive id from command_history table 
        $row = $stmt->fetch();
        $cmd_num = $row[''];
        
        // Retrieve cmd_ret_status and cmd_ret_msg for SQL Admin API command
		$qry = "select cmd_ret_status, cmd_ret_msg from command_history "
			 . "where cmd_number=" . abs($cmd_num);
		$stmt = $db->query($qry);
        $err = $db->errorInfo();
        if (isset($err[1]) && $err[1] != 0)
        {
            $task['SUCCESS'] = false;
            $task['RESULT_MESSAGE'] = "{$this->lang("Error")}: {$err[2]} - {$err[1]}";
            return $task;
        }
        
        // Retreive cmd_ret_status and cmd_ret_msg 
       	$task['SUCCESS'] = false;
		$task['RESULT_MESSAGE'] = "Could not determine result. $cmd_num not found in command_history table" ;	
		while($row = $stmt->fetch(PDO::FETCH_ASSOC))
       	{
        	$task['SUCCESS'] = ($row['CMD_RET_STATUS'] == 0);
        	$task['RETURN_CODE'] = $row['CMD_RET_STATUS'];
			$task['RESULT_MESSAGE'] = $row['CMD_RET_MSG'];
		}
		  			
		return $task;	
    }

    /**
    * Execute SQL Admin API Command given in $sql
    *
    * Return values:
    * The result of the command will be stored in the $task array as follows:
    * 		$task['RESULT_MESSAGE'] --> success or failure message
    *      $task['RETURN_CODE'] --> return code of the command
    */
    public function executeSQLAdminTask($sql)
    {
		$res = array();
		$db = $this->get_database("sysadmin");
		$stmt = $db->query($sql);
        
		// Check for success or errors
		$err = $db->errorInfo();
		if (isset($err[1]) && $err[1] != 0)
		{
			$res['RETURN_CODE']  = -1;
			$res['RESULT_MESSAGE'] = "{$this->lang("Error")}: {$err[2]} - {$err[1]}";
			return $res;
		}
        
		// Retreive id from command_history table 
		$row = $stmt->fetch();
		$cmd_num = $row[''];
        
		// Retrieve cmd_ret_status and cmd_ret_msg for SQL Admin API command
		$qry = "select cmd_ret_status, cmd_ret_msg from command_history "
			 . "where cmd_number=" . abs($cmd_num);
		$stmt = $db->query($qry);
		$err = $db->errorInfo();
		if (isset($err[1]) && $err[1] != 0)
		{
			$res['RETURN_CODE']  = -1;
			$res['RESULT_MESSAGE'] = "{$this->lang("Error")}: {$err[2]} - {$err[1]}";
			return $task;
		}
        
		// Retreive cmd_ret_status and cmd_ret_msg 
		$res['RESULT_MESSAGE'] = "Could not determine result. $cmd_num not found in command_history table" ;	
		while($row = $stmt->fetch(PDO::FETCH_ASSOC))
		{
			$res['RETURN_CODE'] = $row['CMD_RET_STATUS'];
			$res['RESULT_MESSAGE'] = $row['CMD_RET_MSG'];
		}
		
		return $res;
    }

	/**
	 * Check if locales_ext table exists in sysadmin.
	 * If not create that table.
	 */
	private function testAndDeployLocalesExt() 
	{
		try {
			// First check if locales_ext table exists
			$sql = "select count(*) as count from systables where tabname = 'locales_ext'";
			$res = $this->doDatabaseWork($sql,"sysadmin",true);
			
			if ($res[0]['COUNT'] == 1)
			{
				// The locales_ext table already exists, so we can return
				return;
			}

			// If the table does not exist, run the 'CREATE GLFILES' command to create the glsinfo.csv file.
			$this->refreshDBLocalesInfo();

			// Get INFORMIXDIR
			$sql = "select env_value from sysenv where env_name = 'INFORMIXDIR'";
			$res = $this->doDatabaseWork($sql,"sysmaster",true);
			if (count($res) > 0)
			{
				$informixdir = trim($res[0]['ENV_VALUE']);
			} else {
				if ( $this->render == false )
				{
					trigger_error("Error deploying locales_ext table.  Cannot determine INFORMIXDIR.");
				} else {
					$this->fatal_error("Error deploying locales_ext table.  Cannot determine INFORMIXDIR.", false);
				}
			}
			
			// Get DBTEMP 
			$sql = "select TRIM(env_value) as env_value from sysenv where env_name = 'DBTEMP'";
			$res = $this->doDatabaseWork($sql,"sysmaster",true);
			if (count($res) > 0)
			{
				$dbtemp = trim($res[0]['ENV_VALUE']);
			} else {
				if ( $this->render == false )
				{
					trigger_error("Error deploying locales_ext table.  Cannot determine DBTEMP.");
				} else {
					$this->fatal_error("Error deploying locales_ext table.  Cannot determine DBTEMP.", false);
				}
			}
			
			// Determine the OS where the database server is installed.
			// We need this to properly setup path names.
			$sql = "select os_name from sysmachineinfo";
			$res = $this->doDatabaseWork($sql,"sysmaster",true);
			if (count($res) > 0)
			{
				$os_name = trim($res[0]['OS_NAME']);
			} else {
				if ( $this->render == false )
				{
					trigger_error("Error deploying locales_ext table.  Cannot determine database server OS name.");
				} else {
					$this->fatal_error("Error deploying locales_ext table.  Cannot determine database server OS name.");
				}
			}

			if (strcasecmp($os_name,"Windows") == 0)
			{
				$datafile = "{$informixdir}\gls\glsinfo.csv";
				$rejectfile = "{$dbtemp}\\glsinfo_bad.out";
			} else {
				$datafile = "{$informixdir}/gls/glsinfo.csv";
				$rejectfile = "{$dbtemp}/glsinfo_bad.out";
			}

			// And create the external table that will read the the glsinfo.csv file
			$sql = "CREATE EXTERNAL TABLE locales_ext "
				. "( "
				. "filename varchar(200), "
				. "language varchar(200), "
				. "territory varchar(200), "
				. "modifier varchar(200), "
				. "codeset varchar(200), "
				. "name varchar(200), "
				. "lc_source_version integer, "
				. "cm_source_version integer "
				. ") USING ( "
				. "DATAFILES ('DISK:$datafile'), "
				. "REJECTFILE '$rejectfile', "
				. "FORMAT 'delimited', "
				. "DELIMITER ',')";
			$this->doDatabaseWork($sql, "sysadmin", true);
		} catch (PDOException $e) {
			throw new PDOException($e->getMessage() . "   Query: " . $sql, $e->getCode());
		}
    }

	/**
	 * Refresh the db locales information by running the 
	 * EXECUTE FUNCTION TASK ('CREATE GLFILES') command.
	 * This task will update the glsinfo.csv file that the 
	 * external table locales_ext uses.
	 */
	private function refreshDBLocalesInfo()
	{
		$sql = "EXECUTE FUNCTION ADMIN ('CREATE GLFILES')";
		$res = $this->executeSQLAdminTask($sql);
		
		if ($res['RETURN_CODE'] < 0)
		{
			if ( $this->render == false )
			{
				trigger_error($res['RESULT_MESSAGE']);
			} else {
				$this->fatal_error($this->lang('couldNotLoadSwitchLocale') . " " . $res['RESULT_MESSAGE'], false);
			}
		}
	}

	/**
	 * Get available database locale languages
	 * 
	 * @param $refresh - boolean indicating whether we need to refresh the locales
	 *            information on the database server
	 */
	public function getDBLocaleLanguages ($refresh = false)
	{
		try {
			// Check for locales_ext table
			$this->testAndDeployLocalesExt();

			if ($refresh == true)
			{
				$this->refreshDBLocalesInfo();
			}

			$res = array();

			$qry = "select unique language from locales_ext order by language";
			$res = $this->doDatabaseWork($qry,"sysadmin", true);
		} catch (PDOException $e) {
			$this->load_lang("global");
			$this->fatal_error($this->lang('couldNotLoadSwitchLocale') . " " . $e->getMessage() . " " . $qry, false);
		}
	
		return $res;
	}
	
	/**
	 * Get available database locales for a given language
	 * 
	 * @param $lang - language name
	 * @param $refresh - boolean indicating whether we need to refresh the locales
	 *            information on the database server
	 */
	public function getDBLocales ($lang, $refresh = false)
	{
		try {
			// Check for locales_ext table
			$this->testAndDeployLocalesExt();

			if ($refresh == true)
			{
				$this->refreshDBLocalesInfo();
			}

			$res = array();

			$qry = "select name from locales_ext ";
			$qry .= "where name != 'en_US.unicode' and name != 'en_US.ucs4' ";  // en_US.unicode and en_US.ucs4 are not valid DB_LOCALEs
			$qry .= "and language = '$lang' ";
			$qry .= "order by name";

			$res = $this->doDatabaseWork($qry,"sysadmin",true);

		} catch (PDOException $e) {
			$this->load_lang("global");
			$this->fatal_error($this->lang('couldNotLoadSwitchLocale') . " " . $e->getMessage() . " " . $qry, false);
		}
		
		return $res;
	}

	/**
	 * TODO: Clean up temp directory
	 **/
	function cleanuptmp()
	{
		// TODO: Add functionality to cleanup tmp	
	}
	
	/******************************************
	 * Destruct:
	 *******************************************/
	function __destruct()
	{
			
	} #end __destruct

} #end IDSAdmin class
?>
