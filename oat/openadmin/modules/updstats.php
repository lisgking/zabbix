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
 * This class is for the auto update statistics functionality
 * 1.  entire system view
 * 2.  Transactional view
 * 3.  By user (i.e. session)
 */
class updstats {

	public $idsadmin;

	private $task_name_aus_eval    = "Auto Update Statistics Evaluation";
	private $task_name_aus_refresh = "Auto Update Statistics Refresh";
	private $number_of_threads = 1;

	/***********************************
	 * This class constructor sets
	 * the default title and the
	 * language files.
	 *
	 * @return sqltrace
	 */

	function __construct(&$idsadmin)
	{
		$this->idsadmin = &$idsadmin;
		$this->idsadmin->load_lang("updstats");
	}

	/***********************************************
	 * The run function is what index.php will call.
	 * The decission of what to actually do is based
	 * on the value of the $this->idsadmin->in['do']
	 */
	function run()
	{
		$this->idsadmin->html->set_pagetitle($this->idsadmin->lang('mainTitle'));
		$this->idsadmin->setCurrMenuItem("aus");

		// AUS only available for Cheetah 2
		require_once ROOT_PATH."lib/feature.php";
		if ( !Feature::isAvailable ( Feature::CHEETAH2, $this->idsadmin )  )
		{
			$this->idsadmin->fatal_error($this->idsadmin->lang("FeatureUnavailable"));
		}
		else if ( !isset($this->idsadmin->in['refreshEval']) &&
		$this->isAUSOperational() === false )
		{
			$this->idsadmin->in['refreshEval'] = "on";
			$this->ajax( false );
			$this->idsadmin->html->add_to_output("<SCRIPT TYPE='text/javascript'>loadit()</SCRIPT>" );
			return;
		}

		$this->idsadmin->html->add_to_output($this->setuptabs($this->idsadmin->in['do']));
		$run=$this->idsadmin->in['do'];

		if ( isset( $this->idsadmin->in['refreshEval']) ) {
			if ( $this->execRefreshEval() === true )
			{
				print $this->showAUSDatabaseSummary();
				die();
			}
			else
			{
				$html = <<<EOF
<form method="get" action="index.php">
<input type="hidden" name="act" value="updstats">
<input type="hidden" name="do" value="show">
<table>
	<tr><td>
	{$this->idsadmin->lang('fix')}
	</td></tr>
	<tr><td>
	<input type="submit" class="button" name="cleanup" style="width:90%"
	value="{$this->idsadmin->lang("Cleanup")}"/>
    </td></tr>
</table>
</form>
EOF;
				print $html;
				die();
			}
		} else if ( isset( $this->idsadmin->in['cleanup'])) {
			$this->execCleanup();
		} else if ( isset( $this->idsadmin->in['saveAUSConfig'])) {
			$this->execConfigSave();
		}

		switch($run)
		{
			case 'admin':
				if (isset($this->idsadmin->in['saveTask'])&&($this->idsadmin->in['saveTask']=="ok")){
					$this->idsadmin->status($this->idsadmin->lang('saveTaskOK'));
				}elseif (isset($this->idsadmin->in['saveTask'])&&($this->idsadmin->in['saveTask']=="bad")){
					$this->idsadmin->error($this->idsadmin->lang('saveTaskBAD'));
				}
				$this->statsAdmin();
				break;
			case 'alert':
				$this->statShowAlerts();
				break;
			case 'list':
				$this->statsList();
				break;
			case 'config':
				$this->statsConfig();
				break;
			case 'show':
			default:
				$this->ajax( true );
				//$this->statsShow();
				break;
		}
	} # end function run

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
			$active = "show";
		}

		require_once ROOT_PATH."/lib/tabs.php";
		$t = new tabs($this->idsadmin);
		$t->addtab("index.php?act=updstats",
		$this->idsadmin->lang("generalTab"),
		($active == "show") ? 1 : 0 );
		$t->addtab("index.php?act=updstats&amp;do=admin",
		$this->idsadmin->lang("infoTab"),
		($active == "admin") ? 1 : 0 );
		$t->addtab("index.php?act=updstats&amp;do=alert",
		$this->idsadmin->lang("alerts"),
		($active == "alert") ? 1 : 0 );
		$t->addtab("index.php?act=updstats&amp;do=list",
		$this->idsadmin->lang("listTab"),
		($active == "list") ? 1 : 0 );
		$t->addtab("index.php?act=updstats&amp;do=config",
		$this->idsadmin->lang("configTab"),
		($active == "config") ? 1 : 0 );

		#set the 'active' tab.
		$html  = ($t->tohtml());
		$html .= "<div class='borderwrapwhite'>";
		return $html;
	} #end setuptabs

	/*
	 * Return is AUS operational.
	 * Determine if we have the AUS procedures installed
	 *
	 * @return Boolean
	 */
	function isAUSinstalled(  )
	{
		$db = $this->idsadmin->get_database("sysmaster");

		/* See if the two main AUS function are installed */
		$qry = "SELECT count(*) as cnt"
		. " FROM sysadmin:sysprocedures "
		. " WHERE procname MATCHES 'aus_evaluator*' "
		;

		$stmt = $db->query( $qry );

		if (($res = $stmt->fetch())==true)
		{


			return ($res['CNT']>1?true:false);
		}

		return false;
	}

	/*
	 * Return is AUS operational.
	 * If we have just installed IDS the AUS tasks
	 * might not have run yet, we need to find out.
	 *
	 * We look in systables for the table which are
	 * created by AUS tasks.
	 *
	 * @return Boolean
	 */
	function isAUSOperational()
	{
		$db = $this->idsadmin->get_database("sysmaster");

		/* See if the two main AUS function are installed */
		$qry = "SELECT count(*) as cnt"
		. " FROM sysadmin:systables "
		. " WHERE tabname IN "
		. " ( 'aus_cmd_info' , 'aus_cmd_list', 'aus_cmd_comp' ) "
		;

		$stmt = $db->query( $qry );

		if (($res = $stmt->fetch())==true)
		{
			return ($res['CNT']==3?true:false);
		}

		return false;
	}

	/**
	 * Get the number of threads OAT uses to to run AUS refresh (auto update statistics refresh).
	 * Only applicable for Panther.
	 **/
	private function determineNumberOfAUSRefreshThreads()
	{
		if (!Feature::isAvailable ( Feature::PANTHER, $this->idsadmin ))
		{
			return;
		}
		$dbadmin= $this->idsadmin->get_database("sysadmin");
		$aqry = "SELECT count(*) as cnt"
					. " FROM sysadmin:ph_task"
					. " WHERE tk_execute = 'aus_refresh_stats'";
		$astmt = $dbadmin->query( $aqry, "sysadmin");
		$threadsNumb = $astmt->fetch();
		$this->number_of_threads = $threadsNumb['CNT'];
	}


	function execConfigSave()
	{
		if ($this->idsadmin->isreadonly())
		{
			$this->idsadmin->fatal_error("<center>{$this->idsadmin->lang('NoPermission')}</center>");
		}

		$db = $this->idsadmin->get_database("sysadmin");
		$param = array ( "AUS_AGE", "AUS_AUTO_RULES","AUS_SMALL_TABLES", "AUS_PDQ");
		if (isset($this->idsadmin->in["AUS_CHANGE"]))
		{
			// Only add AUS_CHANGE to the list if it existed on the database server
			// as AUS_CHANGE was removed after Fragment Level Statistics was introduced in IDS.
			$param[] = "AUS_CHANGE";
		}
		// Only add AUS_THREADS to the list for Panther server versions
		if (Feature::isAvailable ( Feature::PANTHER, $this->idsadmin))
		{
			$param[] = "AUS_THREADS";
		}

		// Variable to store AUS_THREADS specified by user
		$desiredthreadcount = 1;
		// Validate input before saving
		$err = 0;
		$err_msg = "{$this->idsadmin->lang("configSaveErr")} <br/>";
		foreach ($param as $config)
		{
			$val = trim($this->idsadmin->in[$config]);
			// Validate all parameters are non-empty
			if (! isset($this->idsadmin->in[$config]) || $val == "")
			{
				$err = 1;
				$err_msg .= "- $config: {$this->idsadmin->lang('noValue')}<br/>";
			} else {

				// Validate parameters are integers within the correct range
				switch ($config)
				{
					case "AUS_AGE":
					case "AUS_SMALL_TABLES":
						// must be a positive integer
						if (!(is_numeric($val) && intval($val) == $val)  || $val < 0)
						{
							$err = 1;
							$err_msg .= "- $config: {$this->idsadmin->lang('notPositiveInt')}<br/>";
						}
						break;
					case "AUS_CHANGE":
						// must be integer between 0 and 100
						if (!(is_numeric($val) && intval($val) == $val) || $val < 0 || $val > 100)
						{
							$err = 1;
							$err_msg .= "- $config: {$this->idsadmin->lang('notInt_0_100')}<br/>";
						}
						break;
					case "AUS_AUTO_RULES":
						// must be zero or 1
						if (!(is_numeric($val) && intval($val) == $val) || $val < 0 || $val > 1)
						{
							$err = 1;
							$err_msg .= "- $config: {$this->idsadmin->lang('notInt_0_1')}<br/>";
						}
						break;
					case "AUS_PDQ":
						// must be an integer value between -1 and  100
						if (!(is_numeric($val) && intval($val) == $val) || $val < -1 || $val > 100)
						{
							$err = 1;
							$err_msg .= "- $config: {$this->idsadmin->lang('notInt_-1_100')}<br/>";
						}
						break;
					case "AUS_THREADS":
						// must be integer between 1 and 10
						if (!(is_numeric($val) && intval($val) == $val) || $val < 1 || $val > 10)
						{
							$err = 1;
							$err_msg .= "- $config: {$this->idsadmin->lang('notInt_1_10')}<br/>";
						} else {
							$desiredthreadcount = $val;
						}
						break;
				}
			}
		}

		if ($err == 1)
		{
			$this->idsadmin->error($err_msg);
			return;
		}

		if (Feature::isAvailable ( Feature::PANTHER, $this->idsadmin ))
		{
			// Handle saving AUS thread count first.  But we only need to do something
			// if the user changed the number of threads.
			$this->determineNumberOfAUSRefreshThreads();
			if ($this->number_of_threads != $desiredthreadcount)
			{
				// Delete all threads except the first one
				$dqry = "DELETE"
					. " FROM sysadmin:ph_task"
					. " WHERE tk_name <> \"Auto Update Statistics Refresh\""
					. " AND tk_execute = \"aus_refresh_stats\"";
				$dstmt = $db->query( $dqry );
				$dstmt->execute();

				if ($desiredthreadcount > 1)
				{
					// Get info about AUS Refresh task, since all additional threads will
					// be a copy of the original task.
					$tqry = "SELECT *"
						. " FROM sysadmin:ph_task"
						. " WHERE tk_name = \"Auto Update Statistics Refresh\"";
					$tstmt = $db->query ( $tqry );
					$tres = $tstmt->fetch();
					$threads = 1;
					$tres['TK_ENABLE'] = ($tres['TK_ENABLE'] == "1") ? "T" : "F";
					$tres['TK_MONDAY'] = ($tres['TK_MONDAY'] == "1") ? "T" : "F";
					$tres['TK_TUESDAY'] = ($tres['TK_TUESDAY'] == "1") ? "T" : "F";
					$tres['TK_WEDNESDAY'] = ($tres['TK_WEDNESDAY'] == "1") ? "T" : "F";
					$tres['TK_THURSDAY'] = ($tres['TK_THURSDAY'] == "1") ? "T" : "F";
					$tres['TK_FRIDAY'] = ($tres['TK_FRIDAY'] == "1") ? "T" : "F";
					$tres['TK_SATURDAY'] = ($tres['TK_SATURDAY'] == "1") ? "T" : "F";
					$tres['TK_SUNDAY'] = ($tres['TK_SUNDAY'] == "1") ? "T" : "F";
					$tres['TK_ATTRIBUTES'] = "770";

					while ( $threads < $desiredthreadcount )
					{
						$tres['TK_NAME'] = "Auto Update Statistics Refresh " . ( $threads + 1 );
						unset($tres['TK_ID']);
						$rqry = "INSERT INTO sysadmin:ph_task";
						$colpart = " (";
						$valuepart = " VALUES (";
						foreach ($tres as $k => $v) {
							$colpart .= $k . ", ";
							$valuepart .= "'" . $v . "', ";
						}
						$colpart = substr($colpart, 0, -2);
						$colpart .= ")";
						$valuepart = substr($valuepart, 0, -2);
						$valuepart .= ")";
						$rqry .= $colpart . $valuepart;
						$istmt = $db->query( $rqry );
						$istmt->execute();
						$threads = $threads + 1;
					}
				}
			}

			// Unset AUS_THREADS, so we don't process it below since it doesn't exist in ph_threshold table.
			unset($param['AUS_THREADS']);
		}

		// Save new config values
		foreach ($param as $val)
		{
			$cmd = "UPDATE sysadmin:ph_threshold SET ( value ) ="
			.  "( '{$this->idsadmin->in[$val]}' ) "
			. "WHERE name = '{$val}'"
			;

			$db->query($cmd);
		}

		// Show save successful message
                $this->idsadmin->status($this->idsadmin->lang('saveConfigSuccessful'));
	}


	function execRefreshEval()
	{
		$db = $this->idsadmin->get_database("sysadmin");
		//$qry = "EXECUTE FUNCTION aus_evaluator('t')";
		$qry = "EXECUTE FUNCTION exectask('Auto Update Statistics Evaluation')";
		$stmt = $db->query( $qry );
		$res = $stmt->fetchall();
		$err = $stmt->errorInfo();

		if ( $err[1] != 0 )
		{
			print $this->idsadmin->template["template_global"]->global_error($this->idsadmin->lang('ProblemWithEval') . " {$err[2]}.");
			return false;
		}

		$retcode = 0;
		foreach($res as $row)
		{
			$retcode = $row[''];
			if ( $retcode < 0)
			{
				break;
			}
		}

		if ( $retcode < 0)
		{
			print $this->idsadmin->template["template_global"]->global_error($this->idsadmin->lang('ProblemWithEval') . " " .
                            $this->idsadmin->lang('ReturnCode',array($retcode)));
			return false;
		}

		return true;
	}

	function execCleanup()
	{
		$db = $this->idsadmin->get_database("sysadmin");
		$qry = "EXECUTE FUNCTION aus_rel_exclusive_access()";

		$stmt = $db->query( $qry );

		if (($res = $stmt->fetch())==true)
		{
			$this->idsadmin->status($this->idsadmin->lang('CleanupOK'));
		} else
		{
			$this->idsadmin->error($this->idsadmin->lang('CleanupBAD'));
		}
	}


	function statsShow()
	{
		$this->showAUSDatabaseSummary();
	}

	function statsAdmin()
	{
		$this->determineNumberOfAUSRefreshThreads();
		$this->idsadmin->html->add_to_output( "<div class='tabpadding'>" );
		$this->showSched();
		$this->idsadmin->html->add_to_output( "<br/>" );
		$this->showConfig();
		$this->idsadmin->html->add_to_output( "</div>" );
	}

	function statsConfig()
	{
		$this->determineNumberOfAUSRefreshThreads();
		$this->idsadmin->html->add_to_output( "<div class='tabpadding'>" );
		$this->showConfigEdit();
		//$this->statsShowTasks();
		$this->idsadmin->html->add_to_output( "</div>" );
	}

	function statsList()
	{
		$this->idsadmin->html->add_to_output( "<div class='tabpadding'>" );
		$this->showList();
		$this->idsadmin->html->add_to_output( "</div>" );
	}

	function showAdminCommands()
	{

		if ( $this->idsadmin->isreadonly() )
		{
			$HTML=<<<END
			<table>
			<tr><td>
			<input type="button" class="disabledbutton" name="readonly"  disabled="disabled"
			title="{$this->idsadmin->lang('RequestEval')}"
			value="{$this->idsadmin->lang('RefreshEvaluation')}"/>
			</td></tr>
			<tr><td>
			<input type="submit" class="disabledbutton" name="readonly" disabled="disabled"
			style="width:90%"
			title="{$this->idsadmin->lang('Cleanup_tooltip')}"
			value="{$this->idsadmin->lang('Cleanup')}"/>
    	</td></tr>
    	<tr><td>
    	</table>

END;
		}
		else
		{
			$HTML=<<<END
			<form method="get" action="index.php">
			<table>
			<tr><td>

			<input type="hidden"  name="act" value="updstats">
			<input type="hidden"  name="do" value="show">
			<input type="button" onClick="loadit()" class=button name="refreshEval"
			title="{$this->idsadmin->lang('RequestEval')}"
			value="{$this->idsadmin->lang("RefreshEvaluation")}"/>
			</td></tr>
			<tr><td>
			<input type="submit" class="button" name="cleanup" style="width:90%"
			title="{$this->idsadmin->lang('Cleanup_tooltip')}"
			value="{$this->idsadmin->lang('Cleanup')}"/>
    	</td></tr>
    	<tr><td>
END;
			/**  Future code
			<input type="submit" class="button" name="exportCmds"  style="width:90%"
			value="{$this->idsadmin->lang("ExportCmds")}"/>
			</td></tr>
			**/

			$HTML.=<<<END
    	</table>
    	</form>
END;
		}
		return $HTML;

	}

	function showAUSDatabaseSummary()
	{
		$db = $this->idsadmin->get_database("sysmaster");


		$sql= "SELECT FIRST 1 "
		. " decode(tk_enable,'t', LTRIM(decode( "
		. " ((tk_next_execution - CURRENT)::INTERVAL HOUR(5) TO HOUR)::char(20)::integer, "
		. " 0, ' ', (tk_next_execution - CURRENT)::INTERVAL HOUR(5) TO HOUR || ' hours ') || "
		. " TRIM( ((tk_next_execution - CURRENT) - (tk_next_execution - CURRENT)::INTERVAL HOUR(5) TO HOUR)::INTERVAL MINUTE(9) TO MINUTE  || ' minutes ' )), "
		. " 'DISABLED' ) as data "
		. " FROM sysadmin:ph_task "
		. " WHERE tk_name MATCHES 'Auto Update Statistics Refresh*' "
		. " ORDER BY tk_next_execution"
		;

		$stmt = $db->query($sql);
		$res1 = $stmt->fetch(PDO::FETCH_ASSOC);
		$stmt->closeCursor();

		if ( $res1['DATA'] == "DISABLED" )
		{
			$data="<strong>" . $this->idsadmin->lang("ausDisabled") . "</strong>";
		}
		else
		{
			$data=preg_replace('/hours/',$this->idsadmin->lang('hours'),$res1['DATA']);
			$data=preg_replace('/minutes/',$this->idsadmin->lang('minutes'),$data);
			$data=$this->idsadmin->lang("runIn", array($data));
		}

		$sql = "SELECT SUM(aus_ci_missed_tables) as missed, "
		. " sum(aus_ci_need_tables)- SUM(aus_ci_done_tables) as need, "
		. " SUM(aus_ci_done_tables) as done, "
		. " MIN(aus_ci_stime)::DATETIME YEAR TO MINUTE as start "
		. " FROM sysadmin:aus_cmd_info"
		. " WHERE aus_ci_database is NOT NULL";

		$stmt = $db->query($sql);
		$res = $stmt->fetch(PDO::FETCH_ASSOC);
		$stmt->closeCursor();

		$sql =   "select count(unique aus_cmd_partnum), aus_cmd_dbs_partnum "
				."from sysadmin:aus_cmd_list "
				."where aus_cmd_priority > 100000 "
				."group by aus_cmd_dbs_partnum";

		$stmt2 = $db->query($sql);
		$total_smalltables = 0;
		while ($res2 = $stmt2->fetch()){
			foreach($res2 as $i=>$v){
				if ($i == ""){
					$total_smalltables = $total_smalltables + $v;
				}
			}
		}
		$stmt2->closeCursor();

		$userdata = array(
        "{$this->idsadmin->lang("Missed")}" => (int)$res['MISSED'],
        "{$this->idsadmin->lang("Needed_Large")}" => (int)$res['NEED'] - $total_smalltables,
        "{$this->idsadmin->lang("Needed_Small")}" => $total_smalltables,
        "{$this->idsadmin->lang("Done")}"   => (int)$res['DONE'],
		);

		require_once("lib/Charts.php");
		$this->idsadmin->Charts = new Charts($this->idsadmin);
		$this->idsadmin->Charts->setType("PIE");
		$this->idsadmin->Charts->setDbname("sysmaster");
		$this->idsadmin->Charts->setTitle( $this->idsadmin->lang("ChartSummary") );
		$this->idsadmin->Charts->setDataTitles( array($this->idsadmin->lang("Tables"),$this->idsadmin->lang("Actions")) );
		$this->idsadmin->Charts->setLegendDir("vertical");
		$this->idsadmin->Charts->setWidth("100%");
		$this->idsadmin->Charts->setHeight("200");
		$this->idsadmin->Charts->setData($userdata);

		$STATUS_LINE=<<<END
		<table style='width:100%'>
		<tr><td>{$this->idsadmin->lang("lastEval")} {$res['START']}  </td>
		<td align='right'> {$data} </td>
	</tr>
	</table>

END;


		$db = $this->idsadmin->get_database("sysadmin");

		$qry = "SELECT aus_ci_stime, aus_ci_database, "
		. " aus_ci_missed_tables, "
		. " aus_ci_need_tables - aus_ci_done_tables - decode(aus_ci_need_tables,0,0,"
		. "									(select count(unique aus_cmd_partnum)"
		. "									from sysadmin:aus_cmd_list"
		. "                                 where aus_cmd_priority > 100000"
		. "                                 and aus_cmd_dbs_partnum = aus_ci_dbs_partnum)"
		. "        ) as large_tables,"
		. " decode(aus_ci_need_tables,0,0,"
		. "          (select count(unique aus_cmd_partnum)"
		. "           from sysadmin:aus_cmd_list"
		. "           where aus_cmd_priority > 100000"
		. "           and aus_cmd_dbs_partnum = aus_ci_dbs_partnum)"
		. "        ) as small_tables,"
		. " aus_ci_done_tables "
		. " FROM sysadmin:aus_cmd_info "
		. " WHERE aus_ci_database IS NOT NULL";

		$qrycnt = "SELECT COUNT(*) "
		. " FROM sysadmin:aus_cmd_info "
		. "WHERE aus_ci_database IS NOT NULL";

		require_once ROOT_PATH."lib/gentab.php";
		$tab=new gentab($this->idsadmin);

		$this->idsadmin->html->add_to_output( "<div class='tabpadding'>" );
		$this->idsadmin->html->add_to_output( "<table style='width:100%; height:50%'><tr><td valign='top'>" );

		$HTML=$this->showAdminCommands();

		$this->idsadmin->html->add_to_output($HTML . "</td><td style='width:90%;height:50%'  valign='top'>");

		$this->idsadmin->Charts->Render();

		$this->idsadmin->html->add_to_output( "</td></tr><tr><td colspan='2'>" );

		$this->idsadmin->html->add_to_output( $STATUS_LINE );

		$this->idsadmin->html->add_to_output( "</td></tr><tr><td colspan='2'>" );

		$tab->display_tab_by_page( $this->idsadmin->lang("ListDBSsummary"),
		array(
        		"1" => $this->idsadmin->lang("ListDBSTime"),
        		"2" => $this->idsadmin->lang("ListDBSName"),
        		"3" => $this->idsadmin->lang("ListDBSMissed"),
        		"4" => $this->idsadmin->lang("ListDBSLargeTables"),
        		"5" => $this->idsadmin->lang("ListDBSSmallTables"),
        		"6" => $this->idsadmin->lang("ListDBSDone"),
		),
		$qry,
		$qrycnt,
		NULL
		);
		$this->idsadmin->html->add_to_output( "</td></tr></table></div>" );
		$HTML = $this->idsadmin->html->to_render;
		$this->idsadmin->html->to_render="";

		return $HTML;

	}

	function statShowAlerts()
	{
		require_once ROOT_PATH."lib/gentab.php";
		$tab=new gentab($this->idsadmin);
		$db = $this->idsadmin->get_database("sysmaster");

		$sel="SELECT alert_time::DATETIME MONTH TO MINUTE, alert_type,"
		. " trim(lower(alert_color)) as alert_color, alert_message"
		. " FROM sysadmin:ph_alert "
		. " WHERE alert_object_name ='Auto Update Statistics'"
		. " ORDER BY id DESC"
		;

		$selcnt="SELECT count(*) "
		. " FROM sysadmin:ph_alert "
		. " WHERE alert_object_name ='Auto Update Statistics'"
		;

		$this->idsadmin->html->add_to_output( "<div class='tabpadding'>" );
		
		$tab->display_tab_by_page("{$this->idsadmin->lang('AUSAlerts')}",
		array(
                  "1" => "{$this->idsadmin->lang('Time')}",
                  "2" => "{$this->idsadmin->lang('Type')}",
                  "3" => "{$this->idsadmin->lang('Color')}",
                  "4" => "{$this->idsadmin->lang('Message')}",
		),
		$sel, $selcnt, NULL, "gentab_aus_alert.php");
		
		$this->idsadmin->html->add_to_output( "</div>" );
	}

	function statsShowTasks()
	{
		require_once (ROOT_PATH."modules/health.php");


		$db = $this->idsadmin->get_database("sysmaster");
		$qry = "SELECT tk_id "
		. " FROM sysadmin:ph_task "
		. " WHERE tk_name IN "
		. " ( 'Auto Update Statistics Evaluation', "
		. "   'Auto Update Statistics Refresh' ) ";

		$stmt = $db->query( $qry );

		$tasks = new health( $this->idsadmin );

		while (($res = $stmt->fetch())==true)
		{
			$tasks->showTaskDetails( $res['TK_ID'] );
		}

	}

	function statsShowTaskEval()
	{
		require_once (ROOT_PATH."modules/health.php");

		$db = $this->idsadmin->get_database("sysmaster");
		$qry = "SELECT tk_id "
		. " FROM sysadmin:ph_task "
		. " WHERE tk_name = "
		. " 'Auto Update Statistics Evaluation' ";


		$this->idsadmin->html->add_to_output( $qry );

		$stmt = $db->query( $qry );

		$tasks = new health( $this->idsadmin );

		if (($res = $stmt->fetch())==true)
		{
			$tasks->showTaskDetails( $res['TK_ID'] );
		}
		else
		{
			$this->idsadmin->html->add_to_output( "AUS Evaluation task not Found" );
		}

	}

	function statsShowTaskRefresh()
	{
		require_once (ROOT_PATH."modules/health.php");

		$db = $this->idsadmin->get_database("sysmaster");
		$qry = "SELECT tk_id "
		. " FROM sysadmin:ph_task "
		. " WHERE tk_name = '$this->task_name_aus_refresh' ";

		$this->idsadmin->html->add_to_output( $qry );

		$stmt = $db->query( $qry );

		$tasks = new health( $this->idsadmin );

		if (($res = $stmt->fetch())==true)
		{
			$tasks->showTaskDetails( $res['TK_ID'] );
		}
		else
		{
			$this->idsadmin->html->add_to_output( "AUS Refresh task not Found" );
		}

	}

	/*
	 * This function is used to display all AUS
	 * parameters or just a single parameter.
	 */
	function showConfig($param="")
	{

		$db = $this->idsadmin->get_database("sysadmin");
		$qry = "SELECT name, value, description "
		. " FROM sysadmin:ph_threshold "
		. "WHERE task_name IN "
		. "('$this->task_name_aus_eval', "
		. " '$this->task_name_aus_refresh' ) "
		. " ORDER BY name "
		;

		$qrycnt = "SELECT count(*) "
		. " FROM sysadmin:ph_threshold "
		. "WHERE task_name IN "
		. "('$this->task_name_aus_eval', "
		. " '$this->task_name_aus_refresh' ) "
		;

		if ( sizeof($param) > 1 )
		{
			$qry .= " AND name = '" . $param . "'";
			$qrycnt .= " AND name = '" . $param . "'";
		}

		// Add to the config table, the number of AUS refresh threads.
		$additional_rows = null;
		if (Feature::isAvailable ( Feature::PANTHER, $this->idsadmin ))
		{
			$additional_rows = array($row1 = array(
				"NAME" => $this->idsadmin->lang('Threads'),
				"VALUE" => $this->number_of_threads,
				"DESCRIPTION" => $this->idsadmin->lang('specifiedNumbOfThread')));
		}

		require_once ROOT_PATH."lib/gentab.php";
		$tab=new gentab($this->idsadmin);
		$this->idsadmin->in['fullrpt']=true;
		$tab->display_tab_by_page( $this->idsadmin->lang("ListConfig"),
		array(
        		"1" => $this->idsadmin->lang("ConfParam"),
        		"2" => $this->idsadmin->lang("ConfValue"),
        		"3" => $this->idsadmin->lang("ConfDesc"),
		),
		$qry,
		$qrycnt,
		NULL,
		null,
		null,
		null,
		null,
		$additional_rows
		);

	}

	function showConfigEdit($tk_name="Auto Update Statistics Evaluation")
	{
		require_once ROOT_PATH."lib/gentab.php";

		$disabled = ($this->idsadmin->isreadonly())? "DISABLED":"";

		$HTML="";

		$dbadmin= $this->idsadmin->get_database("sysadmin");

		$qry = "SELECT " .
          " id, name, task_name, " .
          " value, value_type, " .
          " tk_id, tk_name, tk_description, " .
          " description " .
          " from " .
          " ph_task, ph_threshold " .
          " WHERE (tk_name = '$this->task_name_aus_eval'" .
          "     OR tk_name ='$this->task_name_aus_refresh')" .
          " AND task_name = tk_name " .
          " AND name NOT MATCHES '*DEBUG' " .
          " ORDER BY name ";

		$stmt = $dbadmin->query( $qry );

		$HTML.=<<<END
<form method="post" action="index.php?act=updstats&amp;do=config">
<table style='width:100%;' class='borderwrap' align='center' cellpadding='2' cellspacing='5'>
<tr>
<td class="tblheader" colspan="6" align="center">{$this->idsadmin->lang('ConfigAutoUpdStatParams')}</td>
</tr>
<tr>
<th>{$this->idsadmin->lang('ConfDesc')}</th>
<th>{$this->idsadmin->lang('ConfValue')}</th>
<th>{$this->idsadmin->lang('ConfParam')}</th>
</tr>
END;
		while (($res = $stmt->fetch(PDO::FETCH_ASSOC))==true)
		{
			if ( $res['NAME'] == 'AUS_AGE')
			{
				$HTML.=<<<END
				<tr>
				<td width="45%"> {$this->idsadmin->lang('AUS_AGE_Description')} </td>
				<td><input type='text' name="{$res['NAME']}" value="{$res['VALUE']}" $disabled size='5' style="text-align: right"/> {$this->idsadmin->lang('days')} </td>
				<td>{$res['NAME']}</td>
				</tr>
END;

			}elseif ( $res['NAME'] == 'AUS_CHANGE')
			{
				$HTML.=<<<END
				<tr>
				<td width="45%"> {$this->idsadmin->lang('AUS_CHANGE_Description')} </td>
				<td><input type='text' name="{$res['NAME']}" value="{$res['VALUE']}" $disabled size='5' style="text-align: right"/>% </td>
				<td>{$res['NAME']}</td>
				</tr>
END;

			}elseif ( $res['NAME'] == 'AUS_AUTO_RULES')
			{
				$AUS_AUTO_RULES_ON  = (isset($res['VALUE'])&&($res['VALUE']==1))?"SELECTED":"";
				$AUS_AUTO_RULES_OFF = (isset($res['VALUE'])&&($res['VALUE']==0))?"SELECTED":"";
				$HTML.=<<<END
				<tr>
				<td width="45%"> {$this->idsadmin->lang('AUS_AUTO_RULES_Description')} </td>
				<td>
				<select name="{$res['NAME']}" $disabled>
				<option value='1' {$AUS_AUTO_RULES_ON} >{$this->idsadmin->lang('on')}</option>
				<option value='0' {$AUS_AUTO_RULES_OFF}>{$this->idsadmin->lang('off')}</option>
				</select>
				</td>
				<td>{$res['NAME']}</td>
				</tr>
END;

			}elseif ( $res['NAME'] == 'AUS_SMALL_TABLES')
			{
				$HTML.=<<<END
				<tr>
				<td width="45%"> {$this->idsadmin->lang('AUS_SMALL_TABLES_Description')} </td>
				<td><input type='text' name="{$res['NAME']}" value="{$res['VALUE']}" $disabled size='5' style="text-align: right"/> {$this->idsadmin->lang('Rows')} </td>
				<td>{$res['NAME']}</td>
				</tr>
END;

			}elseif ( $res['NAME'] == 'AUS_PDQ')
			{
				$HTML.=<<<END
				<tr>
				<td width="45%"> {$this->idsadmin->lang('AUS_PDQ_Description')} </td>
				<td><input type='text' name="{$res['NAME']}" value="{$res['VALUE']}" $disabled size='5' style="text-align: right"/> {$this->idsadmin->lang('Priority')} </td>
				<td>{$res['NAME']}</td>
				</tr>
END;


			}else
			{
				$HTML.=<<<END
				<tr>
				<td width="45%"> {$res['NAME']} -  {$res['DESCRIPTION']} </td>
				<td> <input type='text' name="{$res['NAME']}" value="{$res['VALUE']}" $disabled size='5' style="text-align: right"/> </td>
				<td>{$res['NAME']}</td>
				</tr>
END;

			}

		}


		if (Feature::isAvailable ( Feature::PANTHER, $this->idsadmin ))
		{
			$HTML.=<<<END
			<tr>
			<td width="45%"> {$this->idsadmin->lang('AUS_THREADS_Description')}</td>
			<td><input type='text' name="AUS_THREADS" value="{$this->number_of_threads}" $disabled size='5' style="text-align: right"/> {$this->idsadmin->lang('AUS_THREADS')} </td>
			<td>{$res['NAME']}</td>
			</tr>
END;
		}

		if (! $this->idsadmin->isreadonly())
		{
			$HTML.=<<<END
<tr align='center'>
   <td colspan='2'>
   <input type='submit' class='button' name='saveAUSConfig' value="{$this->idsadmin->lang('Save')}"/>
   <input type='submit' class='button' name='cancel' value="{$this->idsadmin->lang('Cancel')}" onClick="index.php?act=updstats&amp;do=config"/>
   </td>
</tr>
END;
		}

		$HTML .= "</table></form>";


		$this->idsadmin->html->add_to_output( $HTML );
	}


	function showList()
	{
		if ( isset($this->idsadmin->in['listCmdType']) )
		$cmd=$this->idsadmin->in['listCmdType'];
		else
		$cmd="list";

		if ($cmd == "list") {
			$opt1 = "<option selected=\"selected\" value=\"list\">{$this->idsadmin->lang('PendingCmds')}</option>";
			$opt2 = "<option value=\"comp\">{$this->idsadmin->lang('CompletedCmds')}</option>";
		} else {
			$opt1 = "<option value=\"list\">{$this->idsadmin->lang('PendingCmds')}</option>";
			$opt2 = "<option selected=\"selected\" value=\"comp\">{$this->idsadmin->lang('CompletedCmds')}</option>";
		}

		$HTML=<<<END
		<center>
		<table>
		<tr><td>
		<form name="showList" method="get" action="index.php">
		<input type="hidden" name="act" value="updstats">
		<input type="hidden" name="do" value="list">
		<select name="listCmdType" onChange="showList.submit()">
		{$opt1}
		{$opt2}
    	</select>
    	</td></tr>
    	</form>
    	</table>
    	</center>

END;
		$this->idsadmin->html->add_to_output( $HTML );

		$db = $this->idsadmin->get_database("sysadmin");
		if ($cmd=="list") {
			$qry = "SELECT "
			. " aus_cmd_exe, "
			. " aus_cmd_id, aus_cmd_partnum "
			. " FROM sysadmin:aus_cmd_{$cmd} "
			. " ORDER BY aus_cmd_priority DESC, aus_cmd_partnum, aus_cmd_type DESC "
			;
			$cols =  array(
        		"1" => $this->idsadmin->lang("ListExec"),
			);
		}else {
			$qry = "SELECT "
			. " aus_cmd_time::DATETIME YEAR TO SECOND, aus_cmd_exe, "
			. " aus_cmd_id, aus_cmd_partnum "
			. " FROM sysadmin:aus_cmd_{$cmd} "
			. " ORDER BY aus_cmd_priority DESC, aus_cmd_partnum, aus_cmd_type DESC "
			;
			$cols =  array(
        		"1" => $this->idsadmin->lang("ListTime"),
        		"2" => $this->idsadmin->lang("ListExec"),
			);
		}
		$qrycnt = "SELECT count(*) "
		. " FROM sysadmin:aus_cmd_{$cmd}"
		;

		require_once ROOT_PATH."lib/gentab.php";
		$tab=new gentab($this->idsadmin);

		$tab->display_tab_by_page( $this->idsadmin->lang("ListCommands"),
		$cols,
		$qry,
		$qrycnt,
		NULL
		);
	}
	function showSched( )
	{

		require_once ROOT_PATH."lib/gentab.php";

		$tab = new gentab( $this->idsadmin );
		$dbadmin= $this->idsadmin->get_database("sysadmin");

		$burl="'<a href=\"index.php?act=health&amp;do=taskdetails&amp;id='";
		$murl="'\">'";
		$eurl="'</a>'";
		$qry = "SELECT "
		. " tk_name, "
		. " tk_start_time, "
		. " NVL(tk_stop_time,'NEVER') as start_time, "
		. " tk_frequency, "
		. " tk_Monday, tk_Tuesday, tk_Wednesday, "
		. " tk_Thursday, tk_Friday,"
		. " tk_Saturday, tk_Sunday, "
		. " tk_id "
		. " FROM  ph_task "
		. " WHERE tk_name ='$this->task_name_aus_eval'"
		. " UNION "
		. "SELECT "
		. " tk_name, "
		. " tk_start_time, "
		. " NVL(tk_stop_time,'NEVER') as start_time, "
		. " tk_frequency, "
		. " tk_Monday, tk_Tuesday, tk_Wednesday, "
		. " tk_Thursday, tk_Friday,"
		. " tk_Saturday, tk_Sunday, "
		. " tk_id "
		. " FROM  ph_task "
		. " WHERE tk_name ='$this->task_name_aus_refresh'"
		;


		//$this->idsadmin->in['fullrpt']=true;
		$tab->display_tab_by_page("{$this->idsadmin->lang('AutoUpdStatsSched')}",
		array(
                  "1" => "{$this->idsadmin->lang('Name')}",
                  "2" => "{$this->idsadmin->lang('starttime')}",
                  "3" => "{$this->idsadmin->lang('stopttime')}",
                  "4" => "{$this->idsadmin->lang('runfrequency')}",
                  "5" => "{$this->idsadmin->lang('WeekDay1Letter')}",
                  "6" => "{$this->idsadmin->lang('WeekDay2Letter')}",
                  "7" => "{$this->idsadmin->lang('WeekDay3Letter')}",
                  "8" => "{$this->idsadmin->lang('WeekDay4Letter')}",
                  "9" => "{$this->idsadmin->lang('WeekDay5Letter')}",
                  "10" => "{$this->idsadmin->lang('WeekDay6Letter')}",
                  "11" => "{$this->idsadmin->lang('WeekDay7Letter')}",
		),
		$qry, 2, NULL, "gentab_show_aus_sched.php",$dbadmin);


	} #end function

	function getData()
	{
		$this->idsadmin->html->render();
		die();
	}
	function ajax( $display )
	{
		$HTML = <<< EOF
        <script type="text/javascript">

        function loadit()
        {
          l=document.getElementById('output');
 		   l.innerHTML="<center><img src='images/spinner.gif' border='0' alt=''/>{$this->idsadmin->lang('Evaluating')}</center>";
if (window.XMLHttpRequest)
{
     request = new XMLHttpRequest();
     if (request.overrideMimeType)
     {
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
  request.open("POST", "index.php?act=updstats&do=show&refreshEval=on",true);
  request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

  request.onreadystatechange = function() {
    if (request.readyState == 4) {
       if (request.status == 200) {
          result = request.responseText;
          document.getElementById('output').innerHTML = result;
       }
       else {
          document.getElementById('output').innerHTML = "Error:"+request.status+" occurred";
       }
    }
  }
  request.send(null);
}
        </script>

        <div id="output">
EOF;

		if ( $display === true )
		{
			$HTML .= $this->showAUSDatabaseSummary();
		}
		$HTML .= <<< EOF
        </div>
EOF;
		$this->idsadmin->html->add_to_output($HTML);

	}

}// end class

?>
