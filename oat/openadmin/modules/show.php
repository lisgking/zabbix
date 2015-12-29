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
 * This is a class which shows basic functionality,
 * much of the advanced show functionality has been
 * moved to onstat.php
 *
 */
class show {

    public $idsadmin;
	/**
	 * The constructor for the class,
	 * Make sure the current language is set
	 * and a default title for the page.
	 *
	 * @return show
	 */
    function __construct(&$idsadmin)
    {
        $this->idsadmin = &$idsadmin;
        $this->idsadmin->load_lang("show");
        $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('Show'));
    }

	/**
	 * The run function is what index.php will call.
	 * The decission of what to actually do is based
	 * on the value of the $this->idsadmin->in['do']
	 *
	 */
    function run()
    {
        switch($this->idsadmin->in['do'])
        {
			case 'doOnlineLogAdmin':
				$this->doOnlineLogAdmin();
				break;
			case 'onlineLogAdmin':
				$this->idsadmin->setCurrMenuItem("onlinelog");
				$this->idsadmin->html->set_pagetitle($this->idsadmin->lang('OnlineMsgLog'));
				$this->idsadmin->html->add_to_output($this->setupTabsForOnlineLog($this->idsadmin->in['do']));
				$this->onlineLogAdmin();
				break;
			case 'doBarActLogAdmin':
				$this->doBarActLogAdmin();
				break;
			case 'barActLogAdmin':
				$this->idsadmin->setCurrMenuItem("baractlog");
				$this->idsadmin->html->set_pagetitle($this->idsadmin->lang('OnBarActLog'));
				$this->idsadmin->html->add_to_output($this->setupTabsForBarActLog($this->idsadmin->in['do']));
				$this->barActLogAdmin();
				break;
            case 'showCommands';
                $this->idsadmin->setCurrMenuItem("admincommands");
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('CmdHistory'));
                $this->showCommands();
                break;
            case 'showOnlinelog';
                $this->idsadmin->setCurrMenuItem("onlinelog");
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('OnlineMsgLog'));
                $this->showOnlinelog();
                break;
            case 'showOnlineLogTail';
                if (isset($this->idsadmin->in["runReports"]) || isset($this->idsadmin->in["reportMode"]))
                {
                	$this->idsadmin->setCurrMenuItem("Reports");
                } else {         
                    $this->idsadmin->setCurrMenuItem("onlinelog");
                }
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('OnlineMsgLog'));
				$this->idsadmin->html->add_to_output($this->setupTabsForOnlineLog($this->idsadmin->in['do']));
                $this->showOnlineLogTail();
                break;
            case 'showBarActLogTail';
                $this->idsadmin->setCurrMenuItem("baractlog");
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('OnBarActLog'));
				$this->idsadmin->html->add_to_output($this->setupTabsForBarActLog($this->idsadmin->in['do']));
                $this->showBarActLogTail();
                break;
            default:
                $this->idsadmin->error($this->idsadmin->lang('InvalidURL_do_param'));
                break;
        }
        return;
    } # end function run

	/**
	 * Display the Tail of the server log file
	 * (i.e. the online log file).
	 *
	*/
	function showOnlineLogTail()
	{
		$this->idsadmin->includeDojo = TRUE;
		
		require_once ROOT_PATH."lib/gentab.php";
		$tab = new gentab($this->idsadmin);
		
		$tab->display_tab($this->idsadmin->lang("OnlineMsgLog"),
			array(
				"1" => $this->idsadmin->lang('Messages'),
				  ),
			"select skip 1 trim(line) as line from sysonlinelog where offset > -10000",
			"gentab_show_onlinelog.php" );

	} #end showOnlineLogTail


	/**
	 * Display in a table the end of the
	 * BAR activity log.
	 *
	 */
	function showBarActLogTail()
	{
		$this->idsadmin->includeDojo = TRUE;
		
		require_once ROOT_PATH."lib/gentab.php";
		$tab = new gentab($this->idsadmin);

		$tab->display_tab($this->idsadmin->lang('OnBarActLog'),
			array(
				"1" => $this->idsadmin->lang('Messages'),
				  ),
			"select skip 1 line from sysbaract_log where offset > -10000",
			"gentab_show_baractlog.php" );

	} #end showBarActLogTail

    /**
     * This will show the entire online log.
     * The log file can be large that is why we
     * have a version to show the tail of the log
     * file so DBA can see the most recent work without
     * have to go through the entire file.
     *
     */
    function showOnlinelog()
    {

        require_once ROOT_PATH."lib/gentab.php";
		
        $cols = array(
                  "Message" => "Message",
                  );
                  $tab = new gentab($this->idsadmin);
                  $tab->display_tab_pag($this->idsadmin->lang("OnlineMsgLog"),
                  $cols,
             "select line from sysonlinelog " ,20);

    } #end showOnlinelog

	/**
	 * Show in a table the admin commands which
	 * have been executed.  This function must
	 * get it own database handle to the sysadmin
	 * database.
	 *
	 */
    function showCommands( )
    {

        require_once ROOT_PATH."lib/gentab.php";

        $dbadmin = $this->idsadmin->get_database("sysadmin");
        $tab = new gentab($this->idsadmin);
        $qry = "SELECT ".
              " trim(cmd_user)||' @ '|| cmd_hostname as user, " .
              " cmd_exec_time, " .
              " cmd_executed, " .
              " cmd_ret_msg " .
              " FROM command_history order by cmd_number desc";

              $qrycnt = "SELECT count( * ) as cnt from command_history ";

              $tab->display_tab_by_page($this->idsadmin->lang('CmdHistory'),
              array(
                  "1" => $this->idsadmin->lang('User'),
                  "2" => $this->idsadmin->lang('Time'),
                  "3" => $this->idsadmin->lang('CommandExecuted'),
                  "4" => $this->idsadmin->lang('ReturnMsg'),
                  ),
                  $qry, $qrycnt, NULL, "template_gentab_order_html.php",
                  $dbadmin);

    }

	/**
	 *Creates the HTML for the tabs at the top of a page
	 *
	 * @param string $active        The current active tab
	 * @return HTML to create the tabs
	 */
	function setupTabsForOnlineLog($active)
	{
		$report_mode = (isset($this->idsadmin->in["runReports"]) || isset($this->idsadmin->in["reportMode"]));
		if ($report_mode)
		{
			return "";
		}
		
		if ( ! Feature::isAvailable ( Feature::PANTHER_UC3, $this->idsadmin->phpsession->serverInfo->getVersion()  )  )
		{
			return "";
		}

		if (!isset($active) || $active == "")
		{
			$active = "showOnlineLogTail";
		}

		require_once ROOT_PATH."/lib/tabs.php";
		$t = new tabs($this->idsadmin);

		$t->addtab("index.php?act=show&amp;do=showOnlineLogTail",
		$this->idsadmin->lang("OnlineMsgLog"),
		($active == "showOnlineLogTail") ? 1 : 0 );

		$t->addtab("index.php?act=show&amp;do=onlineLogAdmin",
		$this->idsadmin->lang("OnlineMsgLogRotate"),
		($active == "onlineLogAdmin") ? 1 : 0 );

		#set the 'active' tab.
		$html  = ($t->tohtml());
		$html .= "<div class='borderwrapwhite'>";
		return $html;
	} #end setupTabsForOnlineLog

	/**
	 *Creates the HTML for the tabs at the top of a page
	 *
	 * @param string $active        The current active tab
	 * @return HTML to create the tabs
	 */
	function setupTabsForBarActLog($active)
	{
		if ( !  Feature::isAvailable ( Feature::PANTHER_UC3, $this->idsadmin->phpsession->serverInfo->getVersion()  )  )
		{
			return "";
		}

		if (!isset($active) || $active == "")
		{
			$active = "showBarActLogTail";
		}

		require_once ROOT_PATH."/lib/tabs.php";
		$t = new tabs($this->idsadmin);

		$t->addtab("index.php?act=show&amp;do=showBarActLogTail",
		$this->idsadmin->lang("BarActLog"),
		($active == "showBarActLogTail") ? 1 : 0 );

		$t->addtab("index.php?act=show&amp;do=barActLogAdmin",
		$this->idsadmin->lang("BarActLogRotate"),
		($active == "barActLogAdmin") ? 1 : 0 );

		#set the 'active' tab.
		$html  = ($t->tohtml());
		$html .= "<div class='borderwrapwhite'>";
		return $html;
	} #end setupTabsForBarActLog

	function onlineLogAdmin()
	{
	   $db = $this->idsadmin->get_database("sysadmin");
	   $sql = "SELECT tk_id FROM ph_task WHERE tk_name = 'online_log_rotate'";
	   $stmt = $db->query($sql);
	   $row = $stmt->fetchAll();
	   $stmt->closeCursor();
	   $id = $row[0]['TK_ID'];
	   
	   if ( $id == "" || $id == null )
	   {
	   	$error = $this->idsadmin->lang("TaskIdNotFoundOnlineRotate");
	   	$this->idsadmin->fatal_error($error);
	   	return;
	   }
	   require_once 'modules/health.php';
       /* lets us the existing task scheduler module to display/edit
        * the task.
        */
	   $h = new health($this->idsadmin);
	   
	   if ( isset ($this->idsadmin->in['saveTask'] )
	   && ( $this->idsadmin->in['saveTask'] == "ok") )
	   {
	   	$this->idsadmin->status($this->idsadmin->lang('taskdetailssaved'));
	   }
	   
	   /* setup the options to fake that we are calling this from the
	    * regular interface.
	    */
	   $this->idsadmin->in['do'] = "taskdetails";
	   $this->idsadmin->in['caller'] = "olmsg";
	   $this->idsadmin->in['id'] = $id;
	   
	   $this->idsadmin->html->add_to_output("<div class='tabpadding'>");

	   /* run the health module */
	   $h->run();
	   
	   $this->idsadmin->html->add_to_output("</div>");
	   
	}

	function barActLogAdmin()
	{
	   $db = $this->idsadmin->get_database("sysadmin");
	   $sql = "SELECT tk_id FROM ph_task WHERE tk_name = 'bar_act_log_rotate'";
	   $stmt = $db->query($sql);
	   $row = $stmt->fetchAll();
	   $stmt->closeCursor();
	   $id = $row[0]['TK_ID'];
	   
	   if ( $id == "" || $id == null )
	   {
	   	$error = $this->idsadmin->lang("TaskIdNotFoundBarActLogRotate");
	   	$this->idsadmin->fatal_error($error);
	   	return;
	   }
	   require_once 'modules/health.php';
       /* lets us the existing task scheduler module to display/edit
        * the task.
        */
	   $h = new health($this->idsadmin);
	   
	   if ( isset ($this->idsadmin->in['saveTask'] )
	   && ( $this->idsadmin->in['saveTask'] == "ok") )
	   {
	   	$this->idsadmin->status($this->idsadmin->lang('taskdetailssaved'));
	   }
	   
	   /* setup the options to fake that we are calling this from the
	    * regular interface.
	    */
	   $this->idsadmin->in['do'] = "taskdetails";
	   $this->idsadmin->in['caller'] = "baractlog";
	   $this->idsadmin->in['id'] = $id;

	   $this->idsadmin->html->add_to_output("<div class='tabpadding'>");

	   /* run the health module */
	   $h->run();
	   
	   $this->idsadmin->html->add_to_output("</div>");
	}
	
	/* Function to handle log administration.
	 * The 'action' is pulled from the idsadmin class.
	 * This is called via ajax in the browser and therefore
	 * does not need to display everything within the 'oat' ui
	 * framework.
	 */
	
	function doLogAdmin($whichLog="")
	{
          
		$this->idsadmin->render = false;
          $task = ( isset ( $this->idsadmin->in['action'] ) ) ? $this->idsadmin->in['action'] : "" ;
         
         if ( $task == "" )
         {
            die ( "unknown task" );
         }
         
         if ( $whichLog == "" )
         {
         	die("unknown log file");
         }
         $sql = "SELECT admin('file {$task}',trim(cf_effective))::int as result from sysmaster:sysconfig ";
         $sql .= " WHERE cf_name='{$whichLog}' ";
         
         $db = $this->idsadmin->get_database("sysadmin");
         $stmt = $db->query($sql);
         $rows = $stmt->fetchAll();
         $stmt->closeCursor();
         $retMessage = "";
         
         if ( $rows[0]['RESULT'] < 0 )
         {
            $rows[0]['RESULT'] = $rows[0]['RESULT'] * -1;
            $retMessage = "<img src='images/error_msg.png' class='dialogimg' alt='{$this->idsadmin->lang('Error')}'></img>";
         }
         else
         {
         	$retMessage = "<img src='images/check.png' class='dialogimg' alt='{$this->idsadmin->lang('Success')}'></img>";
         }
                  
         $sql = "SELECT cmd_ret_msg FROM command_history WHERE cmd_number = {$rows[0]['RESULT']}";
         $stmt = $db->query($sql);
         $rows = $stmt->fetchAll();
         $stmt->closeCursor();
         $retMessage .= $rows[0]['CMD_RET_MSG'];
         die ( $retMessage );
    } #end doLogAdmin
	
	function doOnlineLogAdmin()
	{
		$this->doLogAdmin("MSGPATH");
	}#end doOnlineLogAdmin
	
	function doBarActLogAdmin()
	{
		$this->doLogAdmin("BAR_ACT_LOG");
	} #end doBarActLogAdmin

} // end class
?>
