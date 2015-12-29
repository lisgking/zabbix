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
 * This is the main class for the SQL Toolbox.
 *
 */
class sqlwin {

	/**
	 * Holds the current sql toolbox database connection --
	 * instance of class sqldb.
	 *
	 * @var sqldb
	 */
	private  $sqlconn="";

	/**
	 * Holds the php session id from session_id() call.
	 * @var string
	 */
	private  $sessionid="";

	/*
	 * These variables hold the html forms for the SQL tab.
	 */
	private static $ajax_java_scripts;
	private static $main_table_start;
	private static $form_textarea;
	private static $form_win_buttons;
	private static $main_table_cellend;
	private static $form_ddmenu_text;
	private static $form_ddmenu_byte;
	private static $form_file_input;
	private static $form_max_fetch;
	private static $main_table_end;

	public $idsadmin;

	/**
	 * The class constructor function called when the class
	 * is "new'd".  Sets the default title and the language files.
	 *
	 * @return sqlwin
	 */
	function __construct(&$idsadmin)
	{
		$this->idsadmin = &$idsadmin;

		$this->idsadmin->load_lang("sqlwin");
		$this->idsadmin->html->set_pagetitle($this->idsadmin->lang('mainTitle')); #"SQL Toolbox"

		define("DBTAB", 1);
		define("TABTAB", 2);
		define("SPLTAB", 3);
		define("COLTAB", 4);
		define("BROWSETAB", 5);
		define("FRAGTAB", 6);
		define("SQLTAB", 7);
		define("SQLRESTAB", 8);
		define("SQLTREETAB", 9);
		define("SQLTABS_ALL", 10);

	}

	/**
	 * The run() function is what index.php will call.
	 * It's basically a big switch on the value of 'do',
	 * $this->idsadmin->in['do'].
	 */
	function run()
	{

		// before we do anything lets make sure we are 'logged in'
		// if need be ..
		if ( ( $this->idsadmin->in['do'] != "dbtab" && $this->idsadmin->in['do'] != "showdbtab" )
		&&( $this->idsadmin->get_config("SECURESQL","on") == "on" ) )
		{
			if ( ! isset( $_SESSION['SQLTOOLBOX_USERNAME'] )
			|| ! isset( $_SESSION['SQLTOOLBOX_PASSWORD'] )
			)
			{
				$this->idsadmin->in['act']="switchuser";
				$this->idsadmin->in['do']="showlogin";
				$this->idsadmin->html->add_to_output($this->idsadmin->template['template_global']->global_redirect($this->idsadmin->lang('NeedToLogin'),"index.php?act=switchuser&do=showlogin"));
			}
		}

		$currentdb=$this->idsadmin->phpsession->get_sqldbname();
		if ($currentdb == "")
		{
			$this->idsadmin->phpsession->set_sqldbname("sysmaster");
			$currentdb = "sysmaster";
		}
		$this->idsadmin->html->debug($currentdb." - currentdb");
	  
		$do_opt = $this->idsadmin->in['do'];
		 
		switch($do_opt)
		{
			case 'dbtab':
				$this->idsadmin->setCurrMenuItem("databases");
				$this->idsadmin->html->add_to_output($this->setuptabs_db(DBTAB));
			case 'showdbtab': /* for displaying the dbtab info in a report */
				$this->dbtab();
				break;
			case 'schematab':
				$this->idsadmin->setCurrMenuItem("schemabrowser");
				$this->schematab();
				break;
			case 'tabletab':
				$this->idsadmin->setCurrMenuItem("schemabrowser");
				$this->tabletab();
				break;
			case 'spltab':
				$this->idsadmin->setCurrMenuItem("schemabrowser");
				$this->spltab();
				break;
			case 'sqltab':
				$this->checkAccess();
				$this->idsadmin->setCurrMenuItem("sql");
				$this->sqltab();
				break;
			case 'sqlrestab':
				$this->checkAccess();
				$this->idsadmin->setCurrMenuItem("sql");
				$qval = $this->idsadmin->phpsession->get_sqlqval();
				/* This is in case there's an error -- we will reset
				 * $this->idsadmin->phpsession->sqlqval if sqlres() is successful.
				 */
				$this->idsadmin->phpsession->set_sqlqval("");
				$this->sqlres($qval);
				break;
			case 'sqltreetab':
				$this->checkAccess();
				$this->idsadmin->setCurrMenuItem("sql");
				$this->sqltree();
				break;
			case 'connect':
				$this->sqlconnect_db();
				break;
			case 'tableinfo':
				$this->idsadmin->setCurrMenuItem("schemabrowser");
				$this->tableinfo();
				break;
			case 'tablefrag':
				$this->idsadmin->setCurrMenuItem("schemabrowser");
				$this->tablefrag_info();
				break;
			case 'tablesel':
				$this->idsadmin->setCurrMenuItem("schemabrowser");
				$this->checkAccess();
				$this->tablesel();
				break;
			case 'save':
				$this->checkAccess();
				$this->save_query();
				break;
			case 'set_session_var':
				$this->set_session_var();
				break;
			default:
				$this->def();
				break;
		}
	} # end function run


	/**
	 * Sets the session text/clob and byte/blob output options
	 * for the dropdown menus in the SQL tab.  The option being
	 * specified is passed in via $this->idsadmin->in['opt'], and the value
	 * of the option is passed in via $this->idsadmin->in['value'].
	 */
	function set_session_var()
	{

		$text_opt_ar = array("", "", "", "", "");
		$byte_opt_ar = array("", "", "", "");
		$opt = $this->idsadmin->in['opt'];
		$value = $this->idsadmin->in['value'];

		if ( $opt == "textopt" )
		{
			switch($value)
			{
				case 'show_all_text':
					$text_opt_ar[0]="selected";
					break;
				case 'show_some_text':
					$text_opt_ar[1]="selected";
					break;
				case 'show_in_file':
					$text_opt_ar[2]="selected";
					break;
				case 'show_size_only':
					$text_opt_ar[3]="selected";
					break;
				case 'ignore_text':
					$text_opt_ar[4]="selected";
					break;
				default:
					$text_opt_ar = $this->idsadmin->phpsession->get_sqltextoptions();
					break;
			}
			$this->idsadmin->phpsession->set_sqltextoptions($text_opt_ar);
		}
		else if ( $opt == "byteopt" )
		{
			switch($value)
			{
				case 'ignore_byte':
					$byte_opt_ar[0]="selected";
					break;
				case 'save_in_file':
					$byte_opt_ar[1]="selected";
					break;
				case 'show_size_only':
					$byte_opt_ar[2]="selected";
					break;
				case 'show_as_image':
					$byte_opt_ar[3]="selected";
					break;
				default:
					$byte_opt_ar = $this->idsadmin->phpsession->get_sqlbyteoptions();
					break;
			}
			$this->idsadmin->phpsession->set_sqlbyteoptions($byte_opt_ar);
		}
		else if ( $opt == "maxfetnum" )
		{
			$this->idsadmin->phpsession->set_sqlmaxfetnum((int) $value);
		}
		exit;

	} /* function set_session_var() */

	/**
	 * Causes the browser to bring up a file save as window --
	 * to save the text in $this->idsadmin->in['sql_query_editor'].
	 * The text is saved to filename "idsadmin_query<$cnt>.txt",
	 * where the $cnt is a session global.
	 *
	 * $cnt is incremented each time a file is saved using this
	 * function.
	 *
	 * Uses the php header() function and echo.
	 *
	 */
	function save_query()
	{

		$textval = $this->idsadmin->in['sql_query_editor'];
		if ($textval == "")
		{
			$this->idsadmin->error($this->idsadmin->lang("Save_NoQuery"));
			return;
		}
		$cnt = $this->idsadmin->phpsession->get_sqlcnt();
		$cnt++;
		$this->idsadmin->phpsession->set_sqlcnt($cnt);
		$filename = "idsadmin_query". "$cnt". ".txt";
		$hdrstr = "Content-Disposition: attachment; filename=$filename";
		header($hdrstr);
		echo $textval;
		exit;
	}

	/**
	 * Reads a file using file_get_contents() and
	 * returns the text read.
	 * File read is $_FILES['userfile'].
	 * File must be a text file and size must be
	 * less than 20000 bytes.
	 *
	 * @return string
	 */
	function read_file()
	{

		$bolderr="<strong>". $this->idsadmin->lang('Error') .": </strong>";
		$ferror=$_FILES['userfile']['error'];
		$size=$_FILES['userfile']['size'];
		$type=$_FILES['userfile']['type'];
		$origname=$_FILES['userfile']['name'];
		$tmpname=$_FILES['userfile']['tmp_name'];


		if ( $ferror == UPLOAD_ERR_NO_FILE  && $origname == "" )
		{
			return "";
		}

		if ( $ferror != 0 )
		{
			$errstr = $bolderr ;
			$errstr .= $this->idsadmin->lang('filename') .": " . $origname ."; "  ;
			$errstr .= $this->idsadmin->lang('error_num') . ": $ferror." ;
			$this->idsadmin->html->add_to_output($errstr);
			return "";
		}

		$text="";

		if ( $size > 20000 )
		{
			$errstr = $bolderr ;
			$errstr .= $this->idsadmin->lang('filename') .": " . $origname ."; "  ;
			$errstr .= $this->idsadmin->lang('size_too_big') . " ($size)." ;
			$this->idsadmin->html->add_to_output($errstr);
			return $text;
		}
		else if ( $size == 0 )
		{
			$errstr = $bolderr ;
			$errstr .= $this->idsadmin->lang('filename') .": " . $origname ."; "  ;
			$errstr .= $this->idsadmin->lang('size_is_0') . "." ;
			$this->idsadmin->html->add_to_output($errstr);
			return $text;
		}

		$path = pathinfo($origname);

		if ( (strlen($type)) >= 4 && (strncmp($type, "text", 4) != 0) )
		{
			if ( ( $path['extension'] != "sql" && $path['extentsion'] != "txt" ) )
			{
				$errstr = $bolderr ;
				$errstr .= $this->idsadmin->lang('filename') .": " . $origname ."; "  ;
				$errstr .= $this->idsadmin->lang('not_text_type') . " ($type)." ;
				$this->idsadmin->html->add_to_output($errstr);
				return $text;
			}
		}

		$text = file_get_contents($tmpname);
		if ( !$text )
		{
			$errstr = $bolderr ;
			$errstr .= $this->idsadmin->lang('filename') .": " . $origname ."; "  ;
			$errstr .= $this->idsadmin->lang('cannot_read_file') . "." ;
			$this->idsadmin->html->add_to_output($errstr);
			return "";
		}

		return $text;
	}

	/**
	 * Uses class sqldb to create a connection to the
	 * database name passed in via $this->idsadmin->in['val'].
	 * Also sets the session global variable sqldbname.
	 *
	 */
	function sqlconnect_db($default=0,$unsetdb=false)
	{

		$currentdb=$this->idsadmin->in['val'];

		if ( $currentdb == "" )
		{
			$currentdb = "sysmaster";
			/* do not use &amp; for & in the argument to global_redirect */
			$this->idsadmin->html->add_to_output($this->idsadmin->template['template_global']->global_redirect($this->idsadmin->lang('SelectDB'),"index.php?act=sqlwin&do=dbtab"));
			//return;
		}
		$this->idsadmin->phpsession->set_sqldbname($currentdb);
		$tabinfodb=$this->idsadmin->phpsession->get_sqltabinfo_db();

		/*  Clear some session variables if the database changes */
		if ($tabinfodb != "" && $currentdb != $tabinfodb)
		{
			$this->idsadmin->phpsession->set_sqltabinfo_tab("");
			$this->idsadmin->phpsession->set_sqltabsel_tab("");
			$this->idsadmin->phpsession->set_sqlquery1("");
		}

		if( $unsetdb
		&& ( isset( $_SESSION['SQLTOOLBOX_USERNAME'] )
		|| isset( $_SESSION['SQLTOOLBOX_PASSWORD'] ) ) )
		{
			unset($_SESSION['SQLTOOLBOX_USERNAME']);
			unset($_SESSION['SQLTOOLBOX_PASSWORD']);
		}
		//$this->sqlconn = $this->idsadmin->get_database($currentdb);
		if ( $this->idsadmin->in['default'] )
		{
			return $this->idsadmin->html->add_to_output($this->idsadmin->template['template_global']->global_redirect($this->idsadmin->lang('ConnectingDB') . " {$currentdb}","index.php?act=sqlwin&do=sqltab"));
		}
		/* do not use &amp; for & in the argument to global_redirect */
		if ( $default == 0 )
		{
			return $this->idsadmin->html->add_to_output($this->idsadmin->template['template_global']->global_redirect($this->idsadmin->lang('ConnectingDB') . " {$currentdb}","index.php?act=sqlwin&do=tabletab"));
		}

		return $this->idsadmin->html->add_to_output($this->idsadmin->template['template_global']->global_redirect($this->idsadmin->lang('ConnectingDB') . " {$currentdb}",$this->idsadmin->phpsession->get_lasturl() ));

	} #sqlconnect_db

	/**
	 * Adds the message to "Click to connect on a database"
	 * to the html page to be displayed if the session global
	 * sqldbname is not set.
	 *
	 */
	function print_dbconn()
	{

		$currentdb=$this->idsadmin->phpsession->get_sqldbname();
		if ( $currentdb == "" && $this->idsadmin->in['do'] != "showdbtab" )
		{
			$this->idsadmin->html->add_to_output($this->idsadmin->lang('ClickDbConn'));
		}
	} #print_dbconn

	/**
	 * For the 'Databases' tab.
	 *
	 * Include "lib/tabs.php".
	 * Sets up the html tabs to display.
	 *
	 * $active determines the current tab numer
	 * that should be active.
	 *
	 * Returns html output.
	 *
	 * @param integer $active
	 * @return string
	 */
	function setuptabs_db($active)
	{

		require_once ROOT_PATH."/lib/tabs.php";
		$t = new tabs();

		if ( $active != DBTAB )
		return "";

		$t->addtab("index.php?act=sqlwin&amp;do=dbtab",
        $this->idsadmin->lang("Databases"), 0);

		$t->current($active);
		$html = "";
		$html .= ($t->tohtml());
		$html .= "<div class='borderwrapwhite'>";
		//COMMENTED OUT FOR NOW..
		//$html .= $this->sqltoolbox_logout_button($_SESSION['SQLTOOLBOX_USERNAME']);

		return $html;
	} #end setuptabs_db

	/**
	 * For the "Schema Browser" tabs.
	 *
	 * Include "lib/tabs.php".
	 * Sets up the html tabs to display.
	 *
	 * $active determines the current tab numer
	 * that should be active.
	 *
	 * Returns html output.
	 *
	 * @param integer $active
	 * @return string
	 */
	function setuptabs_schema($active)
	{

		$currentdb=$this->idsadmin->phpsession->get_sqldbname();
		$tabinfotab=$this->idsadmin->phpsession->get_sqltabinfo_tab();
		$tabseltab=$this->idsadmin->phpsession->get_sqltabsel_tab();
		$tabinfofrag=$this->idsadmin->phpsession->get_sqltabinfo_frag();
		$skip = 0;

		require_once ROOT_PATH."/lib/tabs.php";
		$t = new tabs();

		$skip = 1;   /* since DBTAB=1 is not part of this group */

		if ( $currentdb == "" )
		{
			setuptabs_db(DBTAB);
			return "";
		}

		$t->addtab("index.php?act=sqlwin&amp;do=tabletab",
		$this->idsadmin->lang('Tables'),0);
		$t->addtab("index.php?act=sqlwin&amp;do=spltab",
		$this->idsadmin->lang('SPL_UDR'),0);

		/* Show table info tab only if it's asked for, if a previous
		 * info exists, or if table browse is being shown.
		 * tabs:
		 * 1 -- db, 2 --tables, 3--spl,  4--column,
		 * 5--browse, 6-frag,
		 *
		 */

		if ( $active >= COLTAB || $tabinfotab != "" )
		{
			$t->addtab("index.php?act=sqlwin&amp;do=tableinfo",
			$this->idsadmin->lang('Column_Info'),0);
			$t->addtab("index.php?act=sqlwin&amp;do=tablesel",
			$this->idsadmin->lang('Table_Browse'),0);
			if ( $tabinfofrag == 1 )
			{
				$t->addtab("index.php?act=sqlwin&amp;do=tablefrag",
				$this->idsadmin->lang('Fragments'),0);
			}
		}

		#set the 'active' tab.
		$t->current($active-$skip);
		$html = "";
		$html .= ($t->tohtml());
		$html .= "<div class='borderwrapwhite'>";
		 
		$html .= $this->sqltoolbox_logout_button($_SESSION['SQLTOOLBOX_USERNAME']);
		return $html;
	} #end setuptabs_schema

	/**
	 * For the "SQL" tabs.
	 *
	 * Include "lib/tabs.php".
	 * Sets up the html tabs to display.
	 *
	 * $active determines the current tab numer
	 * that should be active.
	 *
	 * Returns html output.
	 *
	 * @param integer $active
	 * @return string
	 */
	function setuptabs_sql($active)
	{

		$currentdb=$this->idsadmin->phpsession->get_sqldbname();
		$tabinfotab=$this->idsadmin->phpsession->get_sqltabinfo_tab();
		$tabseltab=$this->idsadmin->phpsession->get_sqltabsel_tab();
		$tabinfofrag=$this->idsadmin->phpsession->get_sqltabinfo_frag();
		$sqlqval=$this->idsadmin->phpsession->get_sqlqval();

		if ($active == SQLTAB )
		$active = 1;
		else if ($active == SQLRESTAB )
		$active = 2;
		else if ($active == SQLTREETAB )
		$active = 3;

		require_once ROOT_PATH."/lib/tabs.php";
		$t = new tabs();

		$t->addtab("index.php?act=sqlwin&amp;do=sqltab",
		$this->idsadmin->lang('SQL'),0);

		if ( $sqlqval != "" || $active > 1)
		{
			$t->addtab("index.php?act=sqlwin&amp;do=sqlrestab",
			$this->idsadmin->lang('SQLResult'),0);
			$t->addtab("index.php?act=sqlwin&amp;do=sqltreetab",
			$this->idsadmin->lang('SQLTree'),0);
		}

		/* SQLTABS_ALL means to show all the tabs but make the
		 * active one SQLTAB
		 */
		if ($active == SQLTABS_ALL)
		$active = 1;
		/*
		 * tabs
		 * 1 -- sql
		 * 2 -- sql result
		 * 3 -- sql query tree
		 */

		#set the 'active' tab.
		$t->current($active);
		$html = "";
		$html .= ($t->tohtml());
		$html .= "<div class='borderwrapwhite'>";
		$html .= $this->sqltoolbox_logout_button($_SESSION['SQLTOOLBOX_USERNAME']);
		return $html;
	} #end setuptabs_sql

	/**
	 * Include "lib/gentab.php".
	 *
	 * Displays a pie graph showing the percentage of
	 * total allocated space being taken up by each database.
	 *
	 * Displays the list of databases in the database server.
	 * Query is to sysmaster:sysdatabases.
	 *
	 * Template name passed in to gentab.php is
	 * "gentab_order_sqlwin_db_tab.php".
	 *
	 */
	function dbtab()
	{

		$this->idsadmin->html->set_pagetitle($this->idsadmin->lang('DatabasesTitle'));
		require_once ROOT_PATH."lib/gentab.php";
		$this->showDBExtAlloc();
		$this->print_dbconn();
		$qrycnt = "select count(*) from sysdatabases";
		$tab = new gentab($this->idsadmin);
		$hdr = $this->idsadmin->lang('Databases') ."&nbsp; &nbsp; &nbsp;" ;
		
		if (!isset($this->idsadmin->in['fullrpt']))
		{
			$hdr .= $this->idsadmin->lang('TotalRows');
		}
		
		// If in report mode, set appropriate page title.
		if (isset($this->idsadmin->in['reportMode']))
		{
			$this->idsadmin->setCurrMenuItem("Reports");
		}

		$tab->display_tab_by_page($hdr,
		array(
        "1" => $this->idsadmin->lang('Name'),
        "2" => $this->idsadmin->lang('Collation'),
        "3" => $this->idsadmin->lang('CreateDate'),
        "4" => $this->idsadmin->lang('Logging')
		),
        " select trim(name) as _SQLWIN_DBNAME, ".
        " case when partnum > 0 THEN ".
        " (select collate from systabnames where ".
        "   systabnames.partnum = sysdatabases.partnum) ".
        " else 'Unknown' ".
        " End as mycollation, ".
        " created,  ".
        " case when is_logging = 1 THEN " .
        "     case when is_buff_log = 1 THEN 'Buffered' " .
        "     when is_ansi = 1 THEN 'ANSI' " . 
        "     else 'Unbuffered' end " .
        " else 'Not Logged' end as mylogging " .
        " from sysdatabases order by 1",
		$qrycnt, NULL,"gentab_order_sqlwin_db_tab.php", "",0,1);

		$this->idsadmin->html->add_to_output("</div>");

	} #end dbtab

	/**
	 * Checks to see if current database is set.
	 * Re-directs to "do=dbtab" if database is not set.
	 * Re-directs to "do=tabletab" if database is set.
	 */
	function schematab()
	{

		$currentdb=$this->idsadmin->phpsession->get_sqldbname();
		if ( $currentdb == "" )
		{
			$this->idsadmin->phpsession->set_sqldbname("sysmaster");
			/* do not use &amp; for & in the argument to global_redirect */
			//$this->idsadmin->html->add_to_output($this->idsadmin->template['template_global']->global_redirect($this->idsadmin->lang('SelectDBForConnect'),"index.php?act=sqlwin&do=dbtab"));
			//return;
		}

		/* do not use &amp; for & in the argument to global_redirect */
		$this->idsadmin->html->add_to_output($this->idsadmin->template['template_global']->global_redirect($this->idsadmin->lang('ConnectingDB') . " {$currentdb}","index.php?act=sqlwin&do=tabletab"));
	}

	/**
	 * Calls $this->showSysTables() to display the list of
	 * tables in the database selected.
	 *
	 * Checks the value of the checkbox via $this->idsadmin->in['chk_dbcatalog']
	 * to see if the system catalog tables should be displayed.
	 * The checkbox option is saved in the session global array
	 * sqloptions.
	 *
	 */
	function tabletab()
	{

		$this->idsadmin->html->set_pagetitle($this->idsadmin->lang('TablesTitle'));
		$this->idsadmin->html->add_to_output($this->setuptabs_schema(TABTAB));

		$currentdb=$this->idsadmin->phpsession->get_sqldbname();
		$sqloptions = $this->idsadmin->phpsession->get_sqloptions();

		if ( ! isset($this->idsadmin->in['chk_hidden']) && ! isset($this->idsadmin->in['chk_dbcatalog']) )
		{
			/* use saved session option */
			if ( isset($sqloptions["chk_dbcatalog"]) && $sqloptions["chk_dbcatalog"] == 1 )
			{
				$sqloptions["chk_dbcatalog"] = 1;
				$chk_dbcatalog = "checked";
			}
		}
		else if ( $this->idsadmin->in['chk_hidden'] )
		{
			if (isset ($this->idsadmin->in['chk_dbcatalog'] ))
			{
				$sqloptions["chk_dbcatalog"] = 1;
				$chk_dbcatalog = "checked";
			}
			else
			{
				$sqloptions["chk_dbcatalog"] = 0;
				$chk_dbcatalog = "";
			}
		}
		$this->idsadmin->phpsession->set_sqloptions($sqloptions);

		$include_cat_q = $this->idsadmin->lang('IncCatalog');

		$button_label = $this->idsadmin->lang('Submit');

		$form_chkboxes = <<<EOF
<div class='tabpadding'>
<form name="chkboxes_sqltable" method="post"
action="index.php?act=sqlwin&amp;do=tabletab">
 <b>{$include_cat_q}</b>
&nbsp; <input name="chk_dbcatalog" type="checkbox" {$chk_dbcatalog}/>
&nbsp; <input name="chk_hidden" type="hidden" value="none"/>
&nbsp; <input type="submit" class="button" value="{$button_label}"/></form><br/>
EOF;
		$this->idsadmin->html->add_to_output($form_chkboxes);

		$text_opt_str = $this->get_opt_str("text");
		$byte_opt_str = $this->get_opt_str("byte");
		$show_display_options = <<<EOF
<table border="1" cellpadding="2" cellspacing="4">
<tr><td>
{$this->idsadmin->lang('DisplayOptions')}: &nbsp;
<b>{$this->idsadmin->lang('text_clob')}</b>: $text_opt_str &nbsp; <b>{$this->idsadmin->lang('byte_blob')}</b>: $byte_opt_str
</td></tr>
</table>
<br/>
EOF;

		$this->idsadmin->html->add_to_output($show_display_options);
		/* Call showSysTables() to display the list of tables in the database */
		if ($chk_dbcatalog != "")
		$this->showSysTables(1);
		else
		$this->showSysTables(0);
		
		$this->idsadmin->html->add_to_output("</div>");

	} #end tabletab

	/**
	 * Returns the string description of the current
	 * Text/Clob and Byte/Blob display options.
	 *
	 * @param $value string
	 * @return string
	 **/
	function get_opt_str($value)
	{
		 
		$str = "Unknown";

		if ( $value == "text" )
		{
			$text_opt_ar = array("", "", "", "", "");
			$text_opt_ar = $this->idsadmin->phpsession->get_sqltextoptions();
			 
			foreach ($text_opt_ar as $key => $val)
			{
				if ($val == "selected")
				break;
				else
				$key = -1;
			}

			switch($key)
			{
				case 0:
					$str=$this->idsadmin->lang('ShowAllText');
					break;
				case 1:
					$str=$this->idsadmin->lang('ShowSomeText');
					break;
				case 2:
					$str=$this->idsadmin->lang('ShowInFile');
					break;
				case 3:
					$str=$this->idsadmin->lang('ShowSize');
					break;
				case 4:
					$str=$this->idsadmin->lang('IgnoreText');
					break;
				default:
					break;
			}
		}
		else if ( $value == "byte")
		{
			$byte_opt_ar = array("", "", "", "");
			$byte_opt_ar = $this->idsadmin->phpsession->get_sqlbyteoptions();

			foreach ($byte_opt_ar as $key => $val)
			{
				if ($val == "selected")
				break;
				else
				$key = -1;
			}

			switch($key)
			{
				case 0:
					$str=$this->idsadmin->lang('IgnoreByte');
					break;
				case 1:
					$str=$this->idsadmin->lang('SaveInFile');
					break;
				case 2:
					$str=$this->idsadmin->lang('ShowSize');
					break;
				case 3:
					$str=$this->idsadmin->lang('ShowImage');
					break;
				default:
					break;
			}
		}
		return($str);

	} #function get_opt_str

	/**
	 * Include "lib/gentab.php".
	 * The template name passed into gentab is
	 * "gentab_order_sqlwin_db_tab.php".
	 *
	 * Displays the return from "select *" from a table.
	 * The table name is parsed from $this->idsadmin->in['val'].
	 *
	 * "val" is in format owner.table.tabid.
	 *
	 * Also sets the session global variables sqltabinfo_db,
	 *    sqltabsel_tab, and sqltabinfo_tab.
	 *
	 */
	function tablesel()
	{

		$this->idsadmin->html->set_pagetitle($this->idsadmin->lang('TabSelTitle'));
		$this->idsadmin->html->add_to_output($this->setuptabs_schema(BROWSETAB));

		/* seltab_id is of form owner.table.tabid */
		$seltab_id = isset($this->idsadmin->in['val']) ? $this->idsadmin->in['val'] : "";

		$currentdb=$this->idsadmin->phpsession->get_sqldbname();
		$tabinfodb=$this->idsadmin->phpsession->get_sqltabinfo_db();
		$tabseltab=$this->idsadmin->phpsession->get_sqltabsel_tab();

		if ($seltab_id == "")
		{
			/* Show the table select for the previous table */
			if ( $currentdb == $tabinfodb )
			{
				$seltab_id = $tabseltab;
			}
			else
			{
				/* database changed -- shouldn't get here */
				return;
			}
		}

		$this->idsadmin->phpsession->set_sqltabinfo_db($currentdb);
		$this->idsadmin->phpsession->set_sqltabsel_tab($seltab_id);
		/* Set the table for tabinfo tab to be the same as the browse table */
		$this->idsadmin->phpsession->set_sqltabinfo_tab($seltab_id);

		$seltab_ar = explode(".", $seltab_id);

		// should run table selects as the user we are logged in as , so lets
		//  setup a database ..
		$this->sqlconn = $this->idsadmin->get_database($currentdb , true);
		if (strcasecmp($this->idsadmin->phpsession->instance->get_delimident(), "Y") == 0)
		{
			// if DELIMIDENT=Y, we need to quote table names
			$seltab_ar[1] = "\"{$seltab_ar[1]}\"";
		}
		$seltab_only = "'" . $seltab_ar[0] . "'." . $seltab_ar[1] ;
		$qrycnt = "select count(*) from ". $seltab_only ;
		$hdr = "select * from ". $seltab_only ."; &nbsp; &nbsp; &nbsp;" ;
		$hdr .= $this->idsadmin->lang('TotalRows');

		$colNames = $this->getColNames($seltab_ar[2]);

		$this->idsadmin->html->add_to_output("<div class='tabpadding'>");
        
		require_once ROOT_PATH."lib/gentab.php";
		$tab = new gentab($this->idsadmin);
		$tab->display_tab_by_page($hdr,
        $colNames,
        "select * from ". $seltab_only,
		$qrycnt, NULL,"gentab_order_sqlwin_db_tab.php",
		$this->sqlconn,0,1);

		$this->idsadmin->html->add_to_output("</div></div>");
	} #end tablesel
	
    /* *** Multi-byte support ***
	 * Get the column names from syscolumns.
	 * getColumnMeta() used in gentab.php:display_tab_by_page()
	 * does not work with multi-byte characters.
	 */
	function getColNames($tabId)
	{
		$colNameStmt = $this->sqlconn->query("select colname from 'informix'.syscolumns where tabid = " . $tabId);
        
        $colNames = array();
        $postn = 1;
        while ($colNameRes = $colNameStmt->fetch())
            {              
                $colNames[$postn++] = $colNameRes['COLNAME'];
            }
        
        return $colNames;
	}

	/**
	 * Include "lib/gentab.php".
	 * The template name passed into gentab is
	 * gentab_order_sqlwin_db_tab.php .
	 *
	 * Displays the database sysprocedures table info.
	 *
	 * Checkboxes for which langid to display is shown.
	 * The options are saved in the session global array
	 * sqloptions.
	 *
	 * Note:
	 * langid : C=1, SPL=2, Java=3, Client language=4
	 *    mode: D, d, p, O, o, R, r
	 *
	 * Many of the UDR's shown are Informix system
	 * UDR's -- should these not be shown?
	 *
	 * If the customers want to see theirs all together,
	 * they can always order by the mode.
	 *
	 */
	function spltab()
	{
		$chk_c="";
		$chk_spl="";
		$chk_java="";
		$this->idsadmin->html->set_pagetitle($this->idsadmin->lang('SPLTitle'));
		$this->idsadmin->html->add_to_output($this->setuptabs_schema(SPLTAB));

		$currentdb=$this->idsadmin->phpsession->get_sqldbname();
		$sqloptions = $this->idsadmin->phpsession->get_sqloptions();

		/* if check boxes are set in the form */
		if ( isset($this->idsadmin->in['chk_c']) || isset($this->idsadmin->in['chk_spl'])
		|| isset($this->idsadmin->in['chk_java']) )
		{
			if (isset ($this->idsadmin->in['chk_c'] ))
			$sqloptions["chk_c"] = 1;
			else
			$sqloptions["chk_c"] = 0;

			if (isset ($this->idsadmin->in['chk_spl'] ))
			$sqloptions["chk_spl"] = 1;
			else
			$sqloptions["chk_spl"] = 0;

			if (isset ($this->idsadmin->in['chk_java'] ))
			$sqloptions["chk_java"] = 1;
			else
			$sqloptions["chk_java"] = 0;
		}

		/* Check the options -- use previous session options if this is
		 * a new display.
		 */
		if ( $sqloptions["chk_spl"] == 1 )
		$chk_spl = "checked";
		if ( $sqloptions["chk_c"]  == 1)
		$chk_c = "checked";
		if ( $sqloptions["chk_java"] == 1 )
		$chk_java = "checked";


		/* If no options selected, then select all */
		if ($sqloptions["chk_c"] == 0 && $sqloptions["chk_spl"] == 0 &&
		$sqloptions["chk_java"] == 0 )
		{
			# if no checkbox is selected, then select all.
			#print "no box selected";
			$chk_c = "checked";
			$chk_spl = "checked";
			$chk_java = "checked";
			$sqloptions["chk_c"] = 1;
			$sqloptions["chk_spl"] = 1;
			$sqloptions["chk_java"] = 1;
		}

		$this->idsadmin->phpsession->set_sqloptions($sqloptions);


		/* langid = 1 for C, 2 for SPL, 3 for Java, 4 for Client language */
		/* mode ==> D, d, p, O, o, R, r ==> pc_mode in the procedure struct  */

		$button_label = $this->idsadmin->lang('Submit');
		$chk_instr = $this->idsadmin->lang('ChkRtTypes');

		$form_chkboxes = <<<EOF
<div class="tabpadding">
<form name="chkboxes_sqlspl" method="post" 
action="index.php?act=sqlwin&amp;do=spltab"> 
 <b>{$chk_instr}</b>
&nbsp; <input name="chk_c" type="checkbox" {$chk_c}/>C [1]
&nbsp; <input name="chk_spl" type="checkbox" {$chk_spl}/>SPL [2]
&nbsp; <input name="chk_java" type="checkbox" {$chk_java}/>Java [3]
&nbsp; <input type="submit" class="button" value="{$button_label}"/></form><br/>
EOF;

		$this->idsadmin->html->add_to_output($form_chkboxes);


		if ($chk_spl && $chk_c && $chk_java )
		$where_clause = "";
		else
		{
			$cnt=0;
			if ( $chk_c )
			{
				$where_clause = "where langid = 1 ";
				$cnt++;
			}
			if ( $chk_spl )
			{
				if ($cnt ==  0)
				$where_clause = "where langid = 2 ";
				else
				$where_clause .= "or langid = 2 ";
				$cnt++;
			}
			if ( $chk_java )
			{
				if ($cnt ==  0)
				$where_clause = "where langid = 3 ";
				else
				$where_clause .= "or langid = 3 ";
				$cnt++;
			}
		}

		if ( $where_clause == "" )
		$desc = $this->idsadmin->lang("All");
		else
		$desc = $where_clause;

		$qrycnt = "select count(*) from 'informix'.sysprocedures ". $where_clause;
		$this->sqlconn = $this->idsadmin->get_database($currentdb,true);
		$hdr = $this->idsadmin->lang('ProceduresFunctions',array($desc)) . " &nbsp; &nbsp; &nbsp;";
		$hdr .= $this->idsadmin->lang('TotalRows');
		require_once ROOT_PATH."lib/gentab.php";
		$tab = new gentab($this->idsadmin);
		$tab->display_tab_by_page($hdr,
		array(
        "1" => $this->idsadmin->lang('Name'),
        "2" => $this->idsadmin->lang('Owner'),
        "3" => $this->idsadmin->lang('Procid'),
        "4" => $this->idsadmin->lang('Type'),
        "5" => $this->idsadmin->lang('Numargs'),
        "6" => $this->idsadmin->lang('ParamTypes'),
        "7" => $this->idsadmin->lang('Mode'),
        "8" => $this->idsadmin->lang('Langid')
		),
        " select trim(procname) as procname, ".
        " owner, ".
        " procid, ".
        " decode(isproc, 'f', 'Function', 'Procedure') as proctype, ".
        " numargs,  ".
        " paramtypes::lvarchar as paramtypes,  ".
        " mode, langid ".
        " from 'informix'.sysprocedures ".
        " $where_clause ".
        " order by langid, mode, procname",
		$qrycnt, NULL,"gentab_order_sqlwin_db_tab.php",$this->sqlconn,
		0, 1);

		$this->idsadmin->html->add_to_output("</div></div>");
	} #end spltab

	/**
	 * Include "lib/gentab.php".
	 * The template name passed into gentab.php is
	 * gentab_pag_sqlwin_info.php .
	 *
	 * Display the database sysfragments table info for
	 * a fragmented table.
	 *
	 * The table name is parsed from $this->idsadmin->in['val'].
	 * "val" is in format owner.table.tabid.
	 *
	 * Also sets the session global variables
	 *    sqltabinfo_frag, sqltabinfo_db, sqltabinfo_tab,
	 *    sqltabsel_tab.
	 *
	 */
	function tablefrag_info()
	{
		 
		$this->idsadmin->html->set_pagetitle($this->idsadmin->lang('FragInfoTitle'));

		/* seltab_id is of form owner.table.tabid */
		$seltab_id = $this->idsadmin->in['val'];
		$fragmented = 1;

		$currentdb=$this->idsadmin->phpsession->get_sqldbname();
		$tabinfodb=$this->idsadmin->phpsession->get_sqltabinfo_db();
		$tabinfotab=$this->idsadmin->phpsession->get_sqltabinfo_tab();

		if ($seltab_id == "")
		{
			/* Show the table info for the previous database/table */
			$seltab_id = $tabinfotab;
		}
		$this->idsadmin->phpsession->set_sqltabinfo_frag($fragmented);
		$this->idsadmin->phpsession->set_sqltabinfo_db($currentdb);
		$this->idsadmin->phpsession->set_sqltabinfo_tab($seltab_id);

		/* Set the table for table select tab to be the same */
		$this->idsadmin->phpsession->set_sqltabsel_tab($seltab_id);

		/* Need to set $this->idsadmin->phpsession->set_sqltabinfo_frag and other session variables
		 * before setuptabs_schema().
		 */
		$this->idsadmin->html->add_to_output($this->setuptabs_schema(FRAGTAB));


		$seltab_ar = explode(".", $seltab_id);
		$val = $seltab_ar[0] . "." . $seltab_ar[1] ;
		$tabid = $seltab_ar[2];

		$this->sqlconn = $this->idsadmin->get_database($currentdb);
		require_once ROOT_PATH."lib/gentab.php";
		$url="index.php?act=sqlwin&amp;do=tablefrag&amp;val=$val";

		$ftab = new gentab($this->idsadmin);

		$qrycnt = "select count(*) from sysfragments where tabid = $tabid";
		$tab_title = $this->idsadmin->lang('FragDispTitle') ." {$val}, tabid={$tabid}";
		$ftab->display_tab_by_page($tab_title,
		array(
        "1" => $this->idsadmin->lang('Partnum'),
        "2" => $this->idsadmin->lang('FragmentType'),
        "3" => $this->idsadmin->lang('Expression'),
        "4" => $this->idsadmin->lang('Dbspace'),
        "5" => $this->idsadmin->lang('Partition')
		),
        "select hex(partn) as _SQLWIN_TABPARTNUM, ".
        "case strategy ".
        "when 'E' THEN 'Expression' ".
        "when 'R' THEN 'Round Robin' ".
        "else strategy ".
        "end as myexpr, ".
        "exprtext, ".
        "dbspace, partition ".
        "from sysfragments ".
        "where tabid = $tabid",
		$qrycnt,
		10,"gentab_pag_sqlwin_info.php", $this->sqlconn,0,1);

		$this->idsadmin->html->add_to_output("</div>");
	} #function tablefrag_info()

	/**
	 * Include "lib/gentab.php".
	 * The template name passed into gentab is
	 * gentab_pag_sqlwin_info.php .
	 *
	 * Displays the table column info -- similar to
	 * dbacess command 'info columns'.
	 *
	 * The table name is parsed from $this->idsadmin->in['val'].
	 * "val" is in format owner.table.tabid.
	 *
	 * If table is fragmented, $this->idsadmin->in['fragmented'] should be 1,
	 * and the session global sqltabinfo_frag is set.
	 *
	 * These session globals are set:
	 *  sqltabinfo_db, sqltabinfo_tab, sqltabsel_tab.
	 *
	 */
	function tableinfo()
	{
		$fragmented=0;

		$this->idsadmin->html->set_pagetitle($this->idsadmin->lang('TabInfoTitle'));

		/* seltab_id is of form owner.table.tabid */
		$seltab_id = isset($this->idsadmin->in['val']) ? $this->idsadmin->in['val'] : "";
		 
		if (isset($this->idsadmin->in['fragmented']))
		{
			$fragmented = $this->idsadmin->in['fragmented'];
			$this->idsadmin->phpsession->set_sqltabinfo_frag($fragmented);
		}

		/* Need to set $this->idsadmin->phpsession->set_sqltabinfo_frag prior to calling
		 * setuptabs_schema().
		 */
		$this->idsadmin->html->add_to_output($this->setuptabs_schema(COLTAB));

		$currentdb=$this->idsadmin->phpsession->get_sqldbname();
		$tabinfodb=$this->idsadmin->phpsession->get_sqltabinfo_db();
		$tabinfotab=$this->idsadmin->phpsession->get_sqltabinfo_tab();

		if ($seltab_id == "")
		{
			/* Show the table info for the previous database/table */
			if ( $currentdb == $tabinfodb )
			{
				$seltab_id = $tabinfotab;
			}
			else
			{
				/* database changed -- shouldn't get here */
				return;
			}
		}

		$this->idsadmin->phpsession->set_sqltabinfo_db($currentdb);
		$this->idsadmin->phpsession->set_sqltabinfo_tab($seltab_id);

		/* Set the table for table select tab to be the same as the info table */
		$this->idsadmin->phpsession->set_sqltabsel_tab($seltab_id);

		$seltab_ar = explode(".", $seltab_id);
		$val = $seltab_ar[0] . "." . $seltab_ar[1] ;
		$tabid = $seltab_ar[2];

		$this->sqlconn = $this->idsadmin->get_database($currentdb,true);
		require_once ROOT_PATH."lib/gentab.php";
		if ( $fragmented && $fragmented == 1 )
		{
			$url="index.php?act=sqlwin&amp;do=tableinfo&amp;".
            "fragmented=$fragmented&amp;val=$val";
		}
		else
		{
			$url="index.php?act=sqlwin&amp;do=tableinfo&amp;".
            "val=$val";
		}

		$qrycnt = "select count(*) from 'informix'.syscolumns ".
        " where tabid =  '{$tabid}'";

		$tab = new gentab($this->idsadmin);

		$tab_title = $this->idsadmin->lang('ColDispTitle')
		. " $val; &nbsp; &nbsp; &nbsp;"
		. $this->idsadmin->lang('TotalRows');
		
		$owner = "'informix'.";
		if (strcasecmp($this->idsadmin->phpsession->instance->get_delimident(), "Y") == 0)
		{
			// There seems to be a PDO informix defect (?) where when DELIMIDENT is set, 
			// it does not like the owner name 'informix' appended to table names in the query
			// (it is giving us a 'general error'). So we'll not add this when DELIMIDENT is set.
			// Note that because of this, if DELIMIDENT=Y and the database is an ANSI database
			// and you are logged into the SQL Toolbox not as informix, you will get an error
			// since the owner of the system tables are not specified in the query (as required
			// for ANSI databases).  However the query does work if any of those three conditions
			// is not true, so we'll live with this limitation for now.
			$owner = "";
		}
		
		$this->idsadmin->html->add_to_output("<div class='tabpadding'>");
        
		$tab->display_tab_by_page($tab_title,
		array(
        "1" => $this->idsadmin->lang('Colno'),
        "2" => $this->idsadmin->lang('Colname'),
        "3" => $this->idsadmin->lang('Coltype'),
        "4" => $this->idsadmin->lang('Collength'),
        "5" => $this->idsadmin->lang('ExtendedType')
		),

        " select colno, colname, case mod(coltype,256) ".
        "  when 0 THEN 'CHAR' ".
        "  when 1 THEN 'SMALL INT' ".
        "  when 2 THEN 'INTEGER' ".
        "  when 3 THEN 'FLOAT' ".
        "  when 4 THEN 'SMALL FLOAT' ".
        "  when 5 THEN 'DECIMAL' ".
        "  when 6 THEN 'SERIAL' ".
        "  when 7 THEN 'DATE' ".
        "  when 8 THEN 'MONEY' ".
        "  when 9 THEN 'NULL' ".
        "  when 10 THEN 'DATETIME' ".
        "  when 11 THEN 'BYTE' ".
        "  when 12 THEN 'TEXT' ".
        "  when 13 THEN 'VARCHAR' ".
        "  when 14 THEN 'INTERVAL' ".
        "  when 15 THEN 'NCHAR' ".
        "  when 16 THEN 'NVARCHAR' ".
        "  when 17 THEN 'INT8' ".
        "  when 18 THEN 'SERIAL8' ".
        "  when 19 THEN 'SET' ".
        "  when 20 THEN 'MULTISET' ".
        "  when 21 THEN 'LIST' ".
        "  when 22 THEN 'ROW' ".
        "  when 23 THEN 'COLLECTION' ".
        "  when 24 THEN 'ROWREF' ".
        "  when 40 THEN 'UDTVAR' ".
        "  when 41 THEN  ".
        "        case {$owner}syscolumns.extended_id ".
        "          when 1 THEN 'LVARCHAR' ".
        "          when 5 THEN 'BOOLEAN' ".
        "          when 10 THEN 'BLOB' ".
        "          when 11 THEN 'CLOB' ".
        "          ELSE 'UDTFIXED' ".
        "          END  ".
        "  when 42 THEN 'REFSER8' ".
        "  when 52 THEN 'BIGINT' ".
        "  when 53 THEN 'BIGSERIAL' ".
        "  ELSE 'UNKNOWN '||mod(coltype,256) ".
        "  END as mytype , ".
		#####  Now, decide what needs to be printed for the length.
        "case MOD(coltype,256) ".
        "  when 5 THEN ".
        " '(' || TRUNC(collength/256) || ',' ".
        "                   || MOD(collength, 256) || ')' ".
        "  when 8 THEN ".
        " '(' || TRUNC(collength/256) || ',' ".
        "                   || MOD(collength, 256) || ')' ".
		#datetime
        "  when 10 THEN ".
        "    (select decode ( ".
        "     TRUNC(MOD({$owner}syscolumns.collength, 256)/17), ".
        "     0 ,  'YEAR',  ".
        "     2 ,  'MONTH', ".
        "     4 ,  'DAY',   ".
        "     6 ,  'HOUR',  ".
        "     8 ,  'MINUTE',".
        "     10,  'SECOND',".
        "     11,  'FRACTION(1)', ".
        "     12,  'FRACTION(2)', ".
        "     13,  'FRACTION(3)', ".
        "     14,  'FRACTION(4)', ".
        "     15,  'FRACTION(5)', 'UNKNOWN' ) ".
        "     || ' TO ' || decode( ".
        "     MOD(MOD({$owner}syscolumns.collength, 256),16), ".
        "     0 ,  'YEAR',  ".
        "     2 ,  'MONTH', ".
        "     4 ,  'DAY',   ".
        "     6 ,  'HOUR',  ".
        "     8 ,  'MINUTE',".
        "     10,  'SECOND',".
        "     11,  'FRACTION(1)', ".
        "     12,  'FRACTION(2)', ".
        "     13,  'FRACTION(3)', ".
        "     14,  'FRACTION(4)', ".
        "     15,  'FRACTION(5)', 'UNKNOWN' ) ".
        "     from {$owner}systables where {$owner}systables.tabid=1) ".
        "  when 11 THEN '' ".
        "  when 12 THEN '' ".
        "  when 13 THEN ".
        " '(' || MOD(collength,256) || ',' ".
        "                   || TRUNC(collength/256) || ')' ".
		#interval
        "  when 14 THEN ".
        "    (select decode ( ".
        "     TRUNC(MOD({$owner}syscolumns.collength, 256)/17), ".
        "     0 ,  'YEAR',  ".
        "     2 ,  'MONTH', ".
        "     4 ,  'DAY',   ".
        "     6 ,  'HOUR',  ".
        "     8 ,  'MINUTE',".
        "     10,  'SECOND',".
        "     11,  'FRACTION', ". // FRACTION(1)
        "     12,  'FRACTION', ". // FRACTION(2)
        "     13,  'FRACTION', ". // FRACTION(3)
        "     14,  'FRACTION', ". // FRACTION(4)
        "     15,  'FRACTION', 'UNKNOWN' ) ". // FRACTION(5)
        "     || '(' || ".
        "     ( TRUNC({$owner}syscolumns.collength/256) - ".
        "     (MOD(MOD({$owner}syscolumns.collength, 256),16) - ".
        "      TRUNC(MOD({$owner}syscolumns.collength, 256)/17) )) ".
        "     || ')' || ' TO ' || decode( ".
        "     MOD(MOD({$owner}syscolumns.collength, 256),16), ".
        "     0 ,  'YEAR',  ".
        "     2 ,  'MONTH', ".
        "     4 ,  'DAY',   ".
        "     6 ,  'HOUR',  ".
        "     8 ,  'MINUTE',".
        "     10,  'SECOND',".
        "     11,  'FRACTION(1)', ".
        "     12,  'FRACTION(2)', ".
        "     13,  'FRACTION(3)', ".
        "     14,  'FRACTION(4)', ".
        "     15,  'FRACTION(5)', 'UNKNOWN' ) ".
        "     from {$owner}systables where {$owner}systables.tabid=1) ".
        "  when 19 THEN '' ".
        "  when 20 THEN '' ".
        "  when 21 THEN '' ".
		#             "  when 22 THEN '' ".
		#             "  when 23 THEN '' ".
        "  when 40 THEN '' ".
        "  when 41 THEN  ".
        "        case {$owner}syscolumns.extended_id ".
        "          when 10 THEN '' ".
        "          when 11 THEN '' ".
        "          ELSE collength::lvarchar ".
        "          END  ".
        "  ELSE collength::lvarchar ".
        "END as mylength, ".
        " case {$owner}syscolumns.extended_id ".
        "   when 0 THEN 'NONE' ".
        "   ELSE ".
        "     case {$owner}sysxtdtypes.mode ".
        "       when 'C' THEN  ".
        "           (select case count(*) ".
        "                when  1 THEN ".
        "                  (select {$owner}sysxtddesc.description ".
        "                  from {$owner}sysxtddesc where ".
        "                  {$owner}sysxtdtypes.extended_id =  ".
        "                  {$owner}sysxtddesc.extended_id and ".
        "                  {$owner}sysxtddesc.seqno = 1 )  ".
        "                 ELSE '*description longer than 256 chars' ".
        "                 END ".
        "             from {$owner}sysxtddesc where ".
        "             {$owner}sysxtdtypes.extended_id =  ".
        "             {$owner}sysxtddesc.extended_id ".
        "             )  ".
        "       ELSE {$owner}sysxtdtypes.name ".
        "       END ".
        "END as myextdesc ".
        " from {$owner}syscolumns, {$owner}systables, ".
        " outer {$owner}sysxtdtypes ".
        " where {$owner}syscolumns.tabid = {$owner}systables.tabid ".
        " and {$owner}syscolumns.extended_id = ".
        " {$owner}sysxtdtypes.extended_id and ".
        " {$owner}systables.tabid = '{$tabid}' ",
		$qrycnt,
		NULL,"gentab_pag_sqlwin_info.php",$this->sqlconn,0,1);

		$this->idsadmin->html->add_to_output("</div></div>");
	} #end tableinfo

	/**
	 * Include "lib/gentab.php".
	 * The template name passed into gentab is
	 * gentab_disp_sqlwin_sql.php .
	 *
	 * Displays the SQL Query Editor text box so
	 * that a query can be entered.  Also displays the
	 * query result for select count(*) and non-select sql.
	 *
	 * The forms displayed include the buttons for
	 * saving a query to a file, importing a query from
	 * a file, and a menu for hoosing how to display text/clob and
	 * byte/blob data.
	 *
	 */
	function sqltab()
	{

		$this->idsadmin->html->set_pagetitle($this->idsadmin->lang('SQLTitle'));

		$do_not_run = "";
		$TBORDER=0;
		#print_r( $this->idsadmin->in );
		if (isset($this->idsadmin->in['save_query']))
		{
			$this->save_query();
			return;
		}

		if (!isset($this->idsadmin->in['run_query']))
		{
			$do_not_run = 1;
		}
		else
		{
			/* run_query has been chosen */
			$this->idsadmin->phpsession->set_sqlid("");
			$this->idsadmin->phpsession->set_sqlxtreepath("");
			$this->idsadmin->phpsession->set_sqlqval("");
		}

		$file_text="";
		if (isset($this->idsadmin->in['import_file']))
		{
			// first clear any previous query data
			$textval = "";
			$this->idsadmin->phpsession->set_sqlid("");
			$this->idsadmin->phpsession->set_sqlxtreepath("");
			$this->idsadmin->phpsession->set_sqlqval("");
			$this->idsadmin->phpsession->set_sqlquery1("");

			$file_text=$this->read_file();
		}

		$text_opt_ar = $this->idsadmin->phpsession->get_sqltextoptions();
		$byte_opt_ar = $this->idsadmin->phpsession->get_sqlbyteoptions();

		if (isset($this->idsadmin->in['clear_query']))
		{
			$textval = "";
			$this->idsadmin->phpsession->set_sqlid("");
			$this->idsadmin->phpsession->set_sqlxtreepath("");
			$this->idsadmin->phpsession->set_sqlqval("");
		}
		else
		{
			/* Check if the text is imported from a file. */
			if ( $file_text != "" )
			{
				$textval=$file_text;
				$do_not_run=1;
			}
			else
			{
				/* Check if a query has been submitted. */
				$textval = isset($this->idsadmin->in['sql_query_editor']) ? $this->idsadmin->in['sql_query_editor'] : "";

				/* If query wasn't submitted, then it could be the
				 * gentab output -- the url sets textval.
				 */
				if ( $textval == "" )
				{
					$textval = isset($this->idsadmin->in['textval']) ? $this->idsadmin->in['textval'] : "";
				}
				$query1=$this->idsadmin->phpsession->get_sqlquery1();

				/* If text box didn't send anything, and nothing in the URL,
				 * then put the previous query in the text area.
				 */
				if ( $textval == "")
				{
					$textval = $query1;
					$do_not_run = 1;
				}
			}

			/* Remove first ';' and everything after in the query */
			$sqlqwarn = "";
			$textval = rtrim($textval);
			/* if this is a create function / procedure statement then ignore the multi-statement
			 * test.
			 */
			if ( ( strncasecmp($textval,"CREATE PROCEDURE",16) != 0 )
		      && ( strncasecmp($textval,"CREATE FUNCTION" ,15) != 0 ) )
			{
				$pos = strpos($textval, ';');
				if ( $pos === false ) ;
				else
				{
					// Provide a warning to the user if we alter their sql statement
					// more than just removing an ending semi-colon.
					if (strlen($textval) > ($pos+1))
					{
						$sqlqwarn = $this->idsadmin->lang("SQLEditorWarn");
						$this->idsadmin->status($sqlqwarn);
					}
					$tmpstr = $textval;
					$textval = substr($tmpstr, 0, $pos);
				}
			}
			$currentdb=$this->idsadmin->phpsession->get_sqldbname();

			/* The actual query is qval -- take out the newlines since
			 * that messes up string matches and replaces in gentab and
			 * pagination functions.
			 */
			$qval = str_replace("\n", " ", $textval);
			$qvallen = strlen($qval);
			#print("qval=$qval\n");
		}

		/* Save the content of text box */
		$this->idsadmin->phpsession->set_sqlquery1($textval);

		/*
		 * **********************************************************************
		 *  Currently inside function sqltab().
		 *  This is start of form and script building.
		 * **********************************************************************
		 */

		#This script is for setting the text/clob and byte/blob display options.
		$lang_maxfetnum=$this->idsadmin->lang('MaxFetNum');
		$this->ajax_java_scripts = <<<EOF
<script type="text/javascript">
function submitOpt(where,index)
{
  var req = null;
  var select_value = null;

  if (where == "byteopt")
      {
       select_value = 
           document.form_data_opt.byteopt.options[index].value ;
       }
   else if (where == "textopt") 
       {
        select_value = 
           document.form_data_opt.textopt.options[index].value ;
       }

   else if (where == "maxfetnum_chg") 
       {
        where = "maxfetnum";
        select_value = 
           document.getElementById('text_maxfetnum').value ;
        if (select_value <= 0 )
            select_value = 100;
        alert("{$lang_maxfetnum}: " + select_value);
       }

   else if (where == "maxfetnum_reset") 
       {
        where = "maxfetnum";
        select_value = 100;
        document.getElementById('text_maxfetnum').value = select_value;
        alert("{$lang_maxfetnum}: " + select_value);
       }

  if(window.XMLHttpRequest)  
      req = new XMLHttpRequest();
  else
    if (window.ActiveXObject)  
        req = new ActiveXObject("Microsoft.XMLHTTP");

   req.onreadystatechange = function()
   {
   //alert(where + ":" + select_value);
   if(req.readyState == 4)
       {
       if (req.status != 200)
           alert("{$this->idsadmin->lang('ErrorUnableToChg')}");    

       else
           {    
             ;
           //alert(where + ":" + select_value);
           // alert("req.open");    
           //document.getElementById("zone").innerHTML=
           //                       "Received:"+req.responseText;
           }
        }
   } //function

   // Do not put &amp; for & in the arguments to req.open().
   req.open( "Get", 
   "index.php?act=sqlwin&do=set_session_var&opt=" + where + "&value=" + select_value,
       true);
   req.setRequestHeader("Content-Type", 
   "application/x-www-form-urlencoded");
   req.send(null);
}
</script>

<script type="text/javascript" src="jscripts/validate.js">
</script>

<script type="text/javascript">
function checkText(form)
{
if ( form.value <= 0 ) 
    form.value = 100;

return true;
}
</script>

EOF;


		#All displays have been moved to show_sqltab().


		/* Start of form */
		$this->main_table_start = <<<EOF
<table width="700" border="{$TBORDER}" >
<tr><td valign="top">
<form name="tarea_sqledit"  method="post" 
action="index.php?act=sqlwin&amp;do=sqltab"> 
EOF;
		#All displays have been moved to show_sqltab().


		$this->form_textarea = <<<EOF
<br/>
<table width="400" border="{$TBORDER}"><tr><td>
&nbsp; <b>{$this->idsadmin->lang('SQLQueryEditor')}:</b> ({$this->idsadmin->lang('OneStmt')})<br/>
&nbsp; <textarea name="sql_query_editor" cols="50" rows="12" spellcheck="false">
EOF;

		if ( $textval != "" )
		$this->form_textarea .= $textval;

		$this->form_textarea .= <<<EOF
</textarea><br/> 
</td>
EOF;


		$lang_run_query=$this->idsadmin->lang('butRunQuery');
		$lang_save_to_file = $this->idsadmin->lang('butSaveToFile');
		$lang_clear  = "    ". $this->idsadmin->lang('butClear') ."    ";
		$lang_span_rq = $this->idsadmin->lang('spanRunQuery');
		$lang_span_sv = $this->idsadmin->lang('spanSaveToFile');
		$lang_span_clr = $this->idsadmin->lang('spanClear');

		$this->form_win_buttons = <<<EOF
<tr><td>
<span title="{$lang_span_rq}">
&nbsp; <input type="submit" name="run_query" class="button" 
value="{$lang_run_query}"/>
</span>
&nbsp; &nbsp;
<span title="{$lang_span_sv}">
&nbsp; <input type="submit" name="save_query" class="button" value="{$lang_save_to_file}"/>
</span>
&nbsp; &nbsp;
<span title="{$lang_span_clr}">
&nbsp; <input type="submit" name="clear_query" class="button" value="{$lang_clear}"/>
</span>
</td>
</tr>
</table>
</form>
EOF;

		$this->main_table_cellend = "</td><td>";

		#All displays have been moved to show_sqltab().

		$lang_colopt=$this->idsadmin->lang('ColOpt');
		$lang_showall=$this->idsadmin->lang('ShowAll');
		$lang_show255=$this->idsadmin->lang('Show255');
		$lang_showfile=$this->idsadmin->lang('ShowInFile');
		$lang_showsize=$this->idsadmin->lang('ShowSize');
		$lang_ignore=$this->idsadmin->lang('IgnoreCol');
		$lang_showimage=$this->idsadmin->lang('ShowImage');



		$this->form_ddmenu_text = <<<EOF
<br/><table border="0"><tr><td>
<form name="form_data_opt" method="post" action="">
<table> 
<tr>
	<td align="left"><b>{$this->idsadmin->lang('text_clob')} <br/>{$lang_colopt}</b></td>
<td>
<select name="textopt" 
onChange="submitOpt('textopt',document.form_data_opt.textopt.selectedIndex)">
<option value="show_all_text" {$text_opt_ar[0]}>{$lang_showall}</option>
<option value="show_some_text" {$text_opt_ar[1]}>{$lang_show255}</option>
<option value="show_in_file" {$text_opt_ar[2]}>{$lang_showfile}</option>
<option value="show_size_only" {$text_opt_ar[3]}>{$lang_showsize}</option>
<option value="ignore_text" {$text_opt_ar[4]}>{$lang_ignore}</option>
</select>
</td>
</tr>
EOF;

		$this->form_ddmenu_byte = <<<EOF
<tr>
	<td align="left"><b>{$this->idsadmin->lang('byte_blob')} <br/>{$lang_colopt}</b></td>
	<td>
		<select name="byteopt" 
		onChange="submitOpt('byteopt',document.form_data_opt.byteopt.selectedIndex)">
		<option value="ignore_byte" {$byte_opt_ar[0]}>{$lang_ignore}</option>
		<option value="save_in_file" {$byte_opt_ar[1]}>{$lang_showfile}</option>
		<option value="show_size_only" {$byte_opt_ar[2]}>{$lang_showsize}</option>
		<option value="show_as_image" {$byte_opt_ar[3]}>{$lang_showimage}</option>
		</select>
	</td>
</tr>
</table>
</form>
EOF;

		$span_import = $this->idsadmin->lang('spanImport');
		$lang_import = $this->idsadmin->lang('Import');
		$lang_importfrom = $this->idsadmin->lang('ImportQFrom');

		$this->form_file_input = <<<EOF
<tr><td>
<br/>
<form name="file_import_text" method="post" enctype="multipart/form-data" 
action="index.php?act=sqlwin&amp;do=sqltab">
<b>{$lang_importfrom}</b><br/> 
<input type=file size=30 name="userfile"/><br/>
<span title="{$span_import}">
<input type=submit name="import_file" class="button" value="{$lang_import}"/></span>
</form>
</td></tr></table>
EOF;

		$lang_change = $this->idsadmin->lang('Change');
		$lang_reset = $this->idsadmin->lang('Reset');
		$this->form_max_fetch = <<<EOF
<form name="form_maxfetnum" method="post" action="" >
<table border="{$TBORDER}">
<tr>
<td>
{$this->idsadmin->lang('RowFetchLimit')}<br/>
 
<input type="text" value="{$this->idsadmin->phpsession->get_sqlmaxfetnum()}" 
size=5 name="text_maxfetnum" id="text_maxfetnum" onkeypress="return onlydigit(event)"  /> 
<input type="button" class="button" value="{$lang_change}" onClick="checkText(this);submitOpt('maxfetnum_chg')"/>
<input type="button" class="button" value="{$lang_reset}"  onClick="submitOpt('maxfetnum_reset')"/>
</td>
</tr>
</table>
</form>
EOF;

		$this->main_table_end = <<<EOF
</td>
</tr>
</table>
EOF;
		/*
		* **********************************************************************
		*  Currently inside function sqltab().
		*  This is end of form and script building.
		* **********************************************************************
		*/


		$lang_qoutput  = $this->idsadmin->lang('QOutput') ."&nbsp; &nbsp; &nbsp;";

		if (  $do_not_run == "" && $textval != "" && $qval != "" &&
				preg_match("/[a-zA-z]/",$qval) )
		{
			//here we need to get a specific connection for the user..
			$this->sqlconn = $this->idsadmin->get_database($currentdb , true);


			if (!preg_match("/^( *)(\(*)( *)select/i", $qval) )
			{
				/* not a select */
				$this->show_sqltab();

				if (preg_match("/^( *)database /i", $qval) )
				{
					/* database command */

					$query_words = preg_split("/[\s;]+/", $qval);
					require_once ROOT_PATH."lib/gentab.php";
					$tab = new gentab($this->idsadmin);
					$res = $tab->display_tab_exe($lang_qoutput, "",
					$qval,
                    "gentab_disp_sqlwin_sql.php",$this->sqlconn,1);

					if ( $res == 0 )
					{
						$this->idsadmin->unset_database($currentdb);
						$this->idsadmin->phpsession->set_sqldbname($query_words[1]);
						$tabinfodb=$this->idsadmin->phpsession->get_sqltabinfo_db();
						/*  Clear some session variables if the database changes */
						if ($tabinfodb != "" && $currentdb != $tabinfodb)
						{
							$this->idsadmin->phpsession->set_sqltabinfo_tab("");
							$this->idsadmin->phpsession->set_sqltabsel_tab("");
						}
					}
				}
				else
				{

					/* not a select, not a database command */
					require_once ROOT_PATH."lib/gentab.php";
					$tab = new gentab($this->idsadmin);
					$res = $tab->display_tab_exe($lang_qoutput, "",
					$qval,
                    "gentab_disp_sqlwin_sql.php",$this->sqlconn,1);
				}
			}

			else
			{
				/* just a select */

				$this->idsadmin->phpsession->set_sqlqval($qval);
				if ($sqlqwarn != "")
				{
					$this->idsadmin->phpsession->set_sqlqwarn($sqlqwarn);
				}
				/* do not use &amp; for & in the argument to global_redirect */
				$this->idsadmin->html->add_to_output($this->idsadmin->template['template_global']->global_redirect($this->idsadmin->lang('SQLResults'),"index.php?act=sqlwin&do=sqlrestab"));
				return;
			}
		}
		else
		$this->show_sqltab();

		$this->idsadmin->html->add_to_output("</div>");

	} #end function sqltab

	/**
	 * Show the SQL tab window.
	 * Called from function sqltab() when the RESULTS tab does not need
	 * to be displayed.
	 *
	 * The forms and scripts to be displayed were built in sqltab().
	 *
	 * @param integer $tabnnum
	 */
	function show_sqltab($tabnum=SQLTAB)
	{
		 
		$this->idsadmin->html->set_pagetitle($this->idsadmin->lang('SQLTitle'));

		$this->idsadmin->html->add_to_output($this->setuptabs_sql($tabnum));
		$this->idsadmin->html->add_to_output($this->ajax_java_scripts);
		$this->idsadmin->html->add_to_output($this->main_table_start);
		$this->idsadmin->html->add_to_output($this->form_textarea);
		$this->idsadmin->html->add_to_output($this->form_win_buttons);
		$this->idsadmin->html->add_to_output($this->main_table_cellend);
		$this->idsadmin->html->add_to_output($this->form_ddmenu_text);
		$this->idsadmin->html->add_to_output($this->form_ddmenu_byte);
		$this->idsadmin->html->add_to_output($this->form_file_input);
		$this->idsadmin->html->add_to_output($this->form_max_fetch);
		$this->idsadmin->html->add_to_output($this->main_table_end);

	}

	/**
	 * Include "lib/gentab.php".
	 * The template names passed into gentab are
	 * gentab_disp_sqlwin_sql.php (used for non-paginated displays)
	 * and gentab_order_sqlwin_db_tab.php (for paginated, order-enabled
	 * headers).
	 *
	 * Display the query result in the RESULTS tab.
	 *
	 * @param $qval string
	 *
	 */
	function sqlres($qval)
	{
		 
		$this->idsadmin->html->set_pagetitle($this->idsadmin->lang('SQLTitle'));

		$this->idsadmin->html->add_to_output($this->setuptabs_sql(SQLRESTAB));

		if ($qval == "")
		{
			$this->idsadmin->html->add_to_output("<br/><br/>");
			$this->idsadmin->html->add_to_output($this->idsadmin->lang('SQLErr_NoQuery'));
			return;
		}

		if ( $this->sqlconn == "" )
		{
			$currentdb=$this->idsadmin->phpsession->get_sqldbname();
			$this->sqlconn = $this->idsadmin->get_database($currentdb,true);
		}

		// If there is a warning, print it and then unset the warning
		if ($this->idsadmin->phpsession->get_sqlqwarn() != "")
		{
			$this->idsadmin->status($this->idsadmin->phpsession->get_sqlqwarn());
			$this->idsadmin->phpsession->set_sqlqwarn() == "";
		}

		$lang_qoutput  = $this->idsadmin->lang('QOutput') ."&nbsp; &nbsp; &nbsp;";
		
		$this->idsadmin->html->add_to_output("<div class='tabpadding'>");
		$this->idsadmin->html->add_to_output("<br/>$qval<br/><br/>");

		require_once ROOT_PATH."lib/gentab.php";
		$tab = new gentab($this->idsadmin);

		/* select */
		/* check $qval to see if we have a select that has a subquery,
		 * order by, group by, or a union.
		 */
		$ret_qchk = $this->check_qval($qval);
		
		 
		if ($ret_qchk == 0) /* no order by, group by, union, subqueries, etc */
		{
			$lang_qoutput .= $this->idsadmin->lang('TotalRows');
			/*
			 * hdr, col_titles, query, perpage, template, conn, prt_total
			 * $tab->display_tab_pag($lang_qoutput, "",
			 *   $qval,10,"gentab_disp_sqlwin_sql.php",$this->sqlconn, 1);
			 */
			/* hdr, col_titles, query, sqlcnt, perpage, template,
			 * conn, num_rows, prt_total
			 */
			$qrycnt = preg_replace("/(.*)from/i",
            "select count(*) as mycnt from",$qval);
			$tab->display_tab_by_page($lang_qoutput, "",
			$qval,$qrycnt,NULL,"gentab_order_sqlwin_db_tab.php",
			$this->sqlconn,0,1);
		}
		else  /* has sub_query, union, order by, or group by */
		{
			/* We do not want to display these in paginated format.
			 * Fetch of rows are limited to a configurable amount.
			 */
			$maxfetnum = $this->idsadmin->phpsession->get_sqlmaxfetnum();
			/* if $req_qchk == 2 ==> has order by, else 1  */
			$lang_info = $this->idsadmin->lang('MaxRowsFetched');
			$lang_info .= "<BR/>";
			$lang_info .= $this->idsadmin->lang('MaxFetNum');
			$lang_info .= ": $maxfetnum" ;
			$res = $tab->display_tab_max($lang_qoutput, "", $qval,
            "gentab_disp_sqlwin_sql.php",$this->sqlconn,$maxfetnum);

			$res_num_fet = $res['num_fetched'];
			$res_all_fet = $res['all_fetched'];
			$lang_num_fet = $this->idsadmin->lang('NumFet');
			$lang_all_fet = $this->idsadmin->lang('AllRowsFet');
			$this->idsadmin->html->add_to_output(
            "<br/><br/> $lang_num_fet: $res_num_fet <br/>");
			$this->idsadmin->html->add_to_output(
            "$lang_all_fet? $res_all_fet <br/>");
			if ( $res_all_fet != "yes" )
			$this->idsadmin->html->add_to_output("<br/><br/>$lang_info<br/><br/>");
		}

		/* This query has been run as written, so get the sql_id */
		//if ( $this->idsadmin->phpsession->get_sqlid() == "" )

		$this->find_sqlid($qval);

		 
		$this->idsadmin->phpsession->set_sqlqval($qval);
		$this->idsadmin->html->add_to_output("</div></div>");
	} # function sqlres

	/**
	 *
	 * Displays the SQL Query Tree for query in $this->idsadmin->phpsession->sqlqval.
	 *
	 * If you've created the Query Tree already, display that.
	 * If you're creating a new query tree, save the output file
	 * in $this->idsadmin->phpsession->sqlxtreepath when done.
	 *
	 */
	function sqltree()
	{

		$this->idsadmin->html->set_pagetitle($this->idsadmin->lang('SQLTitle'));
		$this->idsadmin->html->add_to_output($this->setuptabs_sql(SQLTREETAB));

		/* get $xtreePath, $sqlid, and $qval from session var */
		$xtreePath = $this->idsadmin->phpsession->get_sqlxtreepath();
		$qval = $this->idsadmin->phpsession->get_sqlqval();

		$sqlid = $this->idsadmin->phpsession->get_sqlid();

		if ( $xtreePath == "" )
		{
			if ( $qval == "" )
			{
				$this->idsadmin->error($this->idsadmin->lang('SQLErr_NoTree'));
				return;
			}
			else
			{
				$xtreePath = $this->mkTreePath($qval, $sqlid);
				/* mkTreePath sets $this->idsadmin->phpsession->sqlid if it wasn't set already. */
				$sqlid = $this->idsadmin->phpsession->get_sqlid();
			}
		}

		if ( $xtreePath == "" )
		{
			$this->idsadmin->error($this->idsadmin->lang('SQLErr_NoTree'));
			return;
		}

		$this->display_xtree($sqlid, $qval, $xtreePath);

	} # function sqltree

	/**
	 * Include "lib/XTree.php".
	 *
	 * Run the query and find the sql_id of the query from the
	 * sysmaster:syssqltrace table and create new XTree.
	 *
	 * XTree() stores the picture in the passed-in $xtreePath.
	 * We also save the path in $this->idsadmin->phpsession->sqlxtreepath.
	 *
	 * @param string $qval
	 * @param string $sqlid
	 * @return string
	 */
	function mkTreePath($qval, $sqlid)
	{

		if ( $sqlid == "" )
		{
			if ( ($sqlid = $this->mk_sqlid($qval)) == "" )
			return "";
		}

		if ( ! $this->sqlconn instanceOf database )
		{
			$currentdb=$this->idsadmin->phpsession->get_sqldbname();
			$this->sqlconn = $this->idsadmin->get_database($currentdb);
		}


		/* call the query tree */
		$dir = $this->retSessionDir();

		$xtreePath = tempnam(realpath($dir), "sqlwin_xtree_") . ".png";

		//$xtreePath = tempnam(realpath($dir), "sqlwin_xtree_"). ".png";
		$xtreePath="{$dir}/".basename($xtreePath);
		#print("xtreePath=$xtreePath\n");

		require_once(ROOT_PATH. "lib/XTree.php");
		$xtree = new XTree($this->idsadmin,$sqlid, $xtreePath);

		$this->idsadmin->phpsession->set_sqlxtreepath($xtreePath);

		return($xtreePath);
	} #function mkTreePath

	/**
	 *
	 * Check if the sql tracing is on by checking info in
	 * syssqltrace_info table in sysmaster database.
	 *
	 * Run the query and find the sql_id of the query from the
	 * sysmaster:syssqltrace table and set $this->idsadmin->phpsession->sqlid .
	 *
	 * @param string $qval
	 * @return string
	 */
	function mk_sqlid($qval)
	{

		if ( $this->sqlconn == "" )
		{
			$currentdb=$this->idsadmin->phpsession->get_sqldbname();
			$this->sqlconn = $this->idsadmin->get_database($currentdb);
		}

		$db = $this->sqlconn;

		/* check if sqltracing is on */
		$sql = "SELECT tracesize, starttime FROM sysmaster:syssqltrace_info";
		$stmt = $db->query($sql);
		$res_r = $stmt->fetchAll();
		$stmt->closeCursor();
		if ( $res_r[0]["TRACESIZE"] == 0 && $res_r[0]["STARTTIME"] == 0 )
		{
			/* tracing is not on */
			$this->idsadmin->phpsession->set_sqlxtreepath("");
			$this->idsadmin->phpsession->set_sqlid("");
			return "";
		}

		$sql = $qval;

		$stmt = $this->sqlconn->query($sql);
		$err_r = $this->sqlconn->errorInfo();
		$err = $err_r[1];
		$stmt->closeCursor();

		if ( $err != 0 )
		{
			$this->idsadmin->html->add_to_output($this->idsadmin->lang('SQLErr_NoTree'));
			$tmpstr  = $this->idsadmin->lang('Return');
			$tmpstr .= ": $err  ";

			if ( $err == "-201" )
			{
				$tmpstr .= $this->idsadmin->lang('Syntax');
				$str = array($tmpstr);
			}
			else
			$str = array($tmpstr);

			$this->display_rows($str);
			/* set session xtreePath and sqlid to empty string */
			$this->idsadmin->phpsession->set_sqlxtreepath("");
			$this->idsadmin->phpsession->set_sqlid("");
			return "";
		}
		/* get the sidd */

		return $this->find_sqlid($qval,true);

	} #function mk_sqlid

	/**
	 *
	 * The query has been run in the current session $this->sqlconn.
	 *
	 * Find the sql_id of the query from the
	 * sysmaster:syssqltrace table and set $this->idsadmin->phpsession->sqlid .
	 *
	 * Sets $this->idsadmin->phpsession->sqlid .
	 *
	 * @param string $qval
	 */
	function find_sqlid($qval,$from_mk_sqlid = false)
	{
		if ( $this->sqlconn == "" )
		{
			// ERROR -- connection must exist
			$this->idsadmin->html->add_to_output($this->idsadmin->lang('SQLErr_NoConn'));
			$this->idsadmin->phpsession->set_sqlxtreepath("");
			$this->idsadmin->phpsession->set_sqlid("");
			return;
		}

		// need to figure out what the session id is for this user ..
		$stmt = $this->sqlconn->query("SELECT DBINFO('sessionid') as ses_id FROM systables WHERE tabid=1");
		$row = $stmt->fetchall();
		$stmt->closeCursor();


		$sessid = $row[0]['SES_ID'];

		// use the sql connection so our session id is still good ..
		//$db = $this->sqlconn;

		$sql = "SELECT MAX(sql_id) AS sql_id FROM sysmaster:syssqltrace "
		." WHERE sql_sid = {$sessid} "; //DBINFO('sessionid') " ;
		// if we are NOT being called from make_sqlid then we need to find the last but 1 sql_id
		if ( ! $from_mk_sqlid )
		{
			$sql .= " AND sql_id < ( SELECT MAX(sql_id) FROM sysmaster:syssqltrace "
			."                WHERE sql_sid = {$sessid} )" ; //DBINFO('sessionid') )";
		}

		$db = $this->idsadmin->get_database("sysmaster",false);

		/* get the sql_id from sysmaster syssqltrace */
		$stmt = $db->query($sql);
		$sqlid_r = $stmt->fetch();
		$stmt->closeCursor();

		$sqlid = $sqlid_r["SQL_ID"];
		$this->idsadmin->phpsession->set_sqlid($sqlid);
		$this->idsadmin->phpsession->set_sqlqval($qval);


		return $sqlid;
		 
	} #function find_sqlid

	/**
	 *
	 *  The query string is passed in.
	 *  This function parses the query string and returns 1 if it has
	 *  sub queries, order by, group by, union.
	 *  If not, returns 0.
	 *
	 * @param string $qval
	 * @return integer
	 */
	function check_qval($qval)
	{
		$ret = 0;

		#$qval = preg_quote($qvalstr);

		$findstr = 'order by ';

		$pos = stripos($qval, $findstr);
		if ($pos !== false)
		return 2;

		if ( preg_match("/sum[ \(]|avg[ \(]|max[ \(]|min[ \(]|count[ \(]/i",
		$qval) )
		return 1;

		if ( preg_match("/[ \)]group by |[ \)]union[ \(]|distinct[ \(]/i",
		$qval) )
		return 1;


		$findstr = 'select';
		/* more than one select in the query? */
		if ( stripos($qval, $findstr) != strripos($qval, $findstr) )
		return 1;


		return $ret;
	} #function check_qval

	/**
	 * Returns the path to the session directory using the
	 * ROOT_PATH and session id returned from session_id().
	 *
	 * @return string
	 */
	function retSessionDir()
	{

		if ( $this->sessionid == "" )
		{
			$this->sessionid=session_id();
		}

		$sdir = ROOT_PATH."/tmp/{$this->sessionid}";
		$mode = 0777;
		if ( ! is_dir($sdir)  )
		{
			if ( !mkdir($sdir, $mode) )
			return "";
		}
		return $sdir;
	} #function retSessionDir

	/**
	 * Include "lib/idsgraphs.php".
	 *
	 * Displays a pie graph that shows the relative sizes of the
	 * databases based on extents allocated for each database.
	 *
	 * query: sysmaster database.
	 *     select dbsname, sum(size) total from sysextents
	 *     group by dbsname;
	 *
	 */
	function showDBExtAlloc()
	{

		require_once ROOT_PATH."lib/Charts.php";

		$sql = "SELECT FIRST 6 trim(dbsname) as NAME, ".
        " sum(size) as TOTAL ".
        " from sysextents, sysdatabases ".
        " where sysextents.dbsname = sysdatabases.name ".
        " group by dbsname ".
        " order by 2 DESC,1 ";

		/* sysmaster database */
		$db0 = $this->idsadmin->get_database("sysmaster");
		$stmt = $db0->query($sql);
		$extdata = array();
		while ($res = $stmt->fetch())
		{
			$extdata[ $res['NAME'] ] = $res['TOTAL'];
		}
		$stmt->closeCursor();
		#print_r($extdata);

		$this->idsadmin->html->add_to_output( "<BR/><TABLE style='width:100%; height:50%'><TR style='height:100%'><TD>" );

		$this->idsadmin->Charts = new Charts($this->idsadmin);
		$this->idsadmin->Charts->setType("PIE");
		$this->idsadmin->Charts->setData($extdata);
		$this->idsadmin->Charts->setTitle($this->idsadmin->lang('SizeSixDBs'));
		$this->idsadmin->Charts->setDataTitles(array($this->idsadmin->lang('Pages'),$this->idsadmin->lang('Name')));
		$this->idsadmin->Charts->setUnits($this->idsadmin->lang('pages'));
		$this->idsadmin->Charts->setLegendDir("vertical");
		$this->idsadmin->Charts->setWidth("100%");
		$this->idsadmin->Charts->setHeight("300");
		$this->idsadmin->Charts->Render();

		$this->idsadmin->html->add_to_output( "</TD></TR></TABLE>" );

		$this->idsadmin->html->add_to_output("</div>");


	} #end showDBExtAlloc

	/**
	 * Include "lib/gentab.php".
	 * The template name passed into gentab is
	 * gentab_order_sqlwin_db_tab.php .
	 *
	 * Displays the tables in the current database.
	 * The current database is from the session global sqldbname.
	 *
	 * The argument $catalog passed in is a flag indicating
	 * whether the database catalog tables should be displayed --
	 * 1 is yes, 0 is no.
	 *
	 * @param integer $catalog
	 *
	 */
	function showSysTables($catalog)
	{

		$currentdb=$this->idsadmin->phpsession->get_sqldbname();

		if ($catalog == 1)
		{
			$where="where tabtype IS NOT NULL and tabtype != ' ' ";
			$where_orderby="where tabtype IS NOT NULL "  .
                " and tabtype != ' ' order by tabname";
		}
		else
		{
			$where="where tabid > 99 and tabtype IS NOT NULL " .
                "and tabtype != ' ' ";
			$where_orderby="where tabid > 99 and tabtype IS NOT NULL " .
                "and tabtype != ' ' order by tabname";
		}


		$qrycnt="select count(*) from 'informix'.systables ". $where;
		$this->sqlconn = $this->idsadmin->get_database($currentdb,true);
		require_once ROOT_PATH."lib/gentab.php";
		$tab = new gentab($this->idsadmin);
		$hdr = $this->idsadmin->lang('Tables') ."&nbsp; &nbsp; &nbsp;" ;
		$hdr .= $this->idsadmin->lang('TotalRows');

		$sql =
        " select case when tabtype = ' ' THEN '' ".
        " else ".
        " trim(owner) || '.' || trim(tabname) || '.' || tabid ".
        " end as  _SQLWIN_HTML_SHOWTAB1 , ".
        " case partnum when 0 THEN ".
        "    case when tabtype = 'T' THEN ".
        " '<a href=\"index.php?act=sqlwin&amp;do=tableinfo".
        "&amp;fragmented=1&amp;val=' || trim(owner) || '.' || ".
        " trim(tabname) || '.' || tabid || '\">' || ".
        " trim(tabname) || '</a>' ".
        " else  ".
        " '<a href=\"index.php?act=sqlwin&amp;do=tableinfo".
        "&amp;fragmented=0&amp;val=' || trim(owner) || '.' || ".
        " trim(tabname) || '.' || tabid || '\">' || ".
        " trim(tabname) || '</a>' ".
        " end ".
        " else ".
        " '<a href=\"index.php?act=sqlwin&amp;do=tableinfo".
        "&amp;fragmented=0&amp;val=' || trim(owner) || '.' || ".
        " trim(tabname) || '.' || tabid || '\">' || ".
        " trim(tabname) || '</a>' ".
        " end as mytabname, ".
        " created, ".
        " tabid, ".
        " case partnum when 0 THEN ".
        "    case when tabtype != 'T' THEN 'NONE' ".
        " else '<a href=\"index.php?act=sqlwin&amp;do=tablefrag".
        "&amp;fragmented=1&amp;val=' || trim(owner) || '.' || ".
        "trim(tabname) || '.' || tabid || '\">' || ".
        " 'FRAGMENTED</a>' end ".
        " else hex(partnum) ".
        " end as _SQLWIN_TABPARTNUM, ".
        " rowsize, nrows, nindexes, trunc(ustlowts,'mi') as ustlowts, ".
        " case locklevel  when 'P' THEN 'Page' ".
        " when 'R' THEN 'Row' ".
        " when 'B' THEN 'Row' ".
        " else locklevel end as lcklevel, ".
        " fextsize, nextsize, npused,".
        " decode(tabtype, 'V', 'Yes', 'No') as viewtype ".
        " from 'informix'.systables ". $where_orderby ;


		$tab->display_tab_by_page( $hdr,
		 
		array(
        "1" => $this->idsadmin->lang('Browse'),
        "2" => $this->idsadmin->lang('Name'),
        "3" => $this->idsadmin->lang('CreateDate'),
        "4" => $this->idsadmin->lang('Tabid'),
        "5" => $this->idsadmin->lang('Partnum'),
        "6" => $this->idsadmin->lang('Rowsize'),
        "7" => $this->idsadmin->lang('NRows'),
        "8" => $this->idsadmin->lang('NIndexes'),
        "9" => $this->idsadmin->lang('LastStats'),
        "10" => $this->idsadmin->lang('Locklevel'),
        "11" => $this->idsadmin->lang('Fextsize'),
        "12" => $this->idsadmin->lang('Nextsize'),
        "13" => $this->idsadmin->lang('PagesUsed'),
        "14" => $this->idsadmin->lang('View')
		),$sql
		,
		$qrycnt, NULL,"gentab_order_sqlwin_db_tab.php",$this->sqlconn,
		0, 1);


	} #end showSysTables


	/**
	 * Outputs the html div section that prints username and logout button
	 * param: $username - current user using the sqltoolbox. Should point to $this->idsadmin->phpsession->instance->get_username()
	 */
	function sqltoolbox_logout_button($username)
	{
	    // Check if securesql config is set to on , if it's not set assume on by default.
	    // We only need to show sqltoolbox logout botton if securesql is on
		if($this->idsadmin->get_config('SECURESQL' , "on") != "on" )
	    {
	    	return "";
	    }
	    
	    $this->idsadmin->load_template("template_switchuser");
	    $html = $this->idsadmin->template["template_switchuser"]->sqltoolbox_logout_jscript();
	    $html .= <<<EOF
<div align=right>
<form name="form_logoutbutton" method="post" action="index.php?act=switchuser&amp;do=logout">
<table cellspacing="5">
<tr>
	<th>{$this->idsadmin->lang('LoggedInAs')}: {$username}</th>
	<td><input type="button" class="button" value="{$this->idsadmin->lang('LogoutSQLToolbox')}"  onClick='sqltoolbox_logout();'/></td>
</tr>
</table>
</form>
</div>    	
EOF;
        return $html;
	}

	/**
	 * Display strings in rows of a table (single column).
	 *
	 * @param array  $str_r
	 * @param string $title
	 *
	 */
	function display_rows($str_r, $title="")
	{

		$html = <<<EOF
<div class="borderwrap">
<table width="100%" border="1">
<tr><td class="tblheader" align="center" colspan="1">{$title}</td></tr>
EOF;
		foreach ($str_r as $index => $val)
		{
			$html .="<tr><td>";
			$html .= $val;
			$html .="</td></tr>";
		}

		$html .= "</table></div>";
		$this->idsadmin->html->add_to_output($html);

	} #display_rows

	/**
	 * Display xtree.
	 *
	 * @param string $xtreePath
	 *
	 */
	function display_xtree($sqlid, $qval, $xtreePath)
	{

		$html = <<<EOF
<div class="tabpadding">
<table width="100%" border="0">
<tr><td>
<center>
<b>{$this->idsadmin->lang('SQLTree')} &nbsp;&nbsp;sql_id=$sqlid</b><br/><br/>
<b>$qval</b>
</td></center></tr>
<tr><td>
<center><img src="{$xtreePath}" border="0" alt="{$this->idsadmin->lang('SQLTree')}"/> </center>
</td></tr>
</table></div>
EOF;


		$this->idsadmin->html->add_to_output($html);

	} #function display_xtree

	/**
	 * This is the default function called from $this->run().
	 * Displays an error message that the URL is invalid.
	 */
	function def()
	{
		$errstr = $this->idsadmin->lang('InvalidURL_do_param');
		$this->idsadmin->error($errstr);
	} #end default

	function checkAccess()
	{
		if ( $this->idsadmin->isreadonly() )
		{
			$this->idsadmin->fatal_error("<center>{$this->idsadmin->lang("NoPermission")}</center>");
		}
	}

}  #end sqlwin class
?>
