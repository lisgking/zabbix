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
 * The main class for the health center
 *
 */
class health {

    /**
     * This class constructor sets
     * the default title and the
     * language files.
     *
     * @return health
     */
    function __construct(&$idsadmin)
    {
        $this->idsadmin = &$idsadmin;
        //$this->idsadmin->load_template("template_health");
        $this->idsadmin->load_lang("global");
        $this->idsadmin->load_lang("health");
        //$this->idsadmin->html->set_pagetitle($this->idsadmin->lang('health'));
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
            case 'tasklist':
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang("taskdetails"));
                $this->idsadmin->setCurrMenuItem("taskdetails");
                $this->showTaskList( );
                break;
            case 'runtimes':
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang("taskruntimes"));
                $this->idsadmin->setCurrMenuItem("runtimes");
                $this->showRunTimes( );
                break;
            case 'sched':
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang("scheduler"));
                $this->idsadmin->setCurrMenuItem("scheduler");
                $this->showSched( );
                break;
            case 'taskdetails':
                if( isset($this->idsadmin->in['caller']) )
                {
                	switch ( $this->idsadmin->in['caller'] )
                	{
                		case "aus":
                			/*Menu item, title and the redirect page when switching servers,
                			 will be set to AUS when scheduler is called from updstats.php instead of by health.php
                			 This is when user clicked on the AUS tasks in the AUS info tab.
                			 The caller value will be passed here through the in array.*/
                			$this->idsadmin->set_redirect("admin","updstats");
                			$this->idsadmin->setCurrMenuItem("aus");
                			$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("aus"));
                			$this->showTaskDetails( $this->idsadmin->in['id'] , "aus");
                			break;
                		case "olmsg":
	            			$this->idsadmin->set_redirect("show","onlineLogAdmin");
	            			$this->idsadmin->setCurrMenuItem("onlinelog");
	            			$this->idsadmin->in['do']="onlineLogAdmin";
                			$this->showTaskDetails( $this->idsadmin->in['id'] , "olmsg");
                			break;
                		case "healthadv":
	            			$this->idsadmin->set_redirect("ibm/hadv/healthadv","showModifySchedule");
	            			$this->idsadmin->setCurrMenuItem("hadv_menu");
                			$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("prod_name"));
	            			$this->idsadmin->in['do']="schedule";
                			$this->showTaskDetails( $this->idsadmin->in['id'] , "healthadv");
                			break;
                		case "baractlog":
	            			$this->idsadmin->set_redirect("show","barActLogAdmin");
	            			$this->idsadmin->setCurrMenuItem("baractlog");
	            			$this->idsadmin->in['do']="barActLogAdmin";
                			$this->showTaskDetails( $this->idsadmin->in['id'] , "baractlog");
                			break;
                		default:
                			$this->idsadmin->set_redirect("tasklist");
                			$this->idsadmin->setCurrMenuItem("taskdetails");
                			$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("taskdetails"));
                			$this->showTaskDetails( $this->idsadmin->in['id'] );
                			break;
                	}
                }
                break;
            case 'edittaskparam':
            	$caller = "";
            	 
            	if(isset($this->idsadmin->in['caller']))
            	{
            		$caller = $this->idsadmin->in['caller'];
            	}
            	switch ($caller)
            	{
            		case "aus":
            			/*Menu item, title and the redirect page when switching servers,
            			 will be set to AUS when scheduler is called from updstats.php instead of by health.php
            			 This is when user clicked on the AUS tasks in the AUS info tab.
            			 The caller value will be passed here through the in array.*/
            			$this->idsadmin->set_redirect("admin","updstats");
            			$this->idsadmin->setCurrMenuItem("aus");
            			$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("aus"));
            			$this->editTaskParam( $this->idsadmin->in['param_id'], $this->idsadmin->in['tk_name'], "aus");
            			break;
            		case "olmsg":
            			$this->idsadmin->set_redirect("show","onlineLogAdmin");
            			$this->idsadmin->setCurrMenuItem("onlinelog");
            			$this->editTaskParam( $this->idsadmin->in['param_id'], $this->idsadmin->in['tk_name'], $caller);
            			break;
            		case "healthadv":
            			$this->idsadmin->set_redirect("ibm/hadv/healthadv","showModifySchedule");
            			$this->idsadmin->setCurrMenuItem("hadv_menu");
                		$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("prod_name"));
            			$this->editTaskParam( $this->idsadmin->in['param_id'], $this->idsadmin->in['tk_name'], $caller);
            			break;
            		case "baractlog":
            			$this->idsadmin->set_redirect("show","barActLogAdmin");
            			$this->idsadmin->setCurrMenuItem("baractlog");
            			$this->editTaskParam( $this->idsadmin->in['param_id'], $this->idsadmin->in['tk_name'], $caller);
            			break;
            		default:
            			$this->idsadmin->set_redirect("tasklist");
            			$this->idsadmin->setCurrMenuItem("taskdetails");
            			$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("edittaskparam"));
            			$this->editTaskParam( $this->idsadmin->in['param_id'], $this->idsadmin->in['tk_name'] , $caller );
            			break;
            	}
                break;
            case 'showAlerts':
                $this->idsadmin->html->set_pagetitle( $this->idsadmin->lang('alerts') );
                $this->idsadmin->setCurrMenuItem("alerts");
                $this->showAlerts( );
                break;
			case 'AddNewTask'; 
				//When users click the "Add a New Task" button, act is set the "health" and do is set to "AddNewTask"
				//Also when users click on Next,Back,Cancel or Finish during the new task setup process,
				//act is set the "health" and do is set to "AddNewTask"
				$this->idsadmin->html->set_pagetitle($this->idsadmin->lang('addtask'));
				$this->idsadmin->setCurrMenuItem("scheduler");
				$this->AddNewTaskPageSelect();
				break;
			case 'showDeleteTask';
				$this->idsadmin->html->set_pagetitle($this->idsadmin->lang('deletetask'));
				$this->idsadmin->setCurrMenuItem("scheduler");
				$this->showDeleteTask();
				break;
			case 'doDeleteTask';
				$this->idsadmin->setCurrMenuItem("scheduler");
				$this->doDeleteTask();
				break;
            default:
                $this->idsadmin->error($this->idsadmin->lang('InvalidURL_do_param'));
                break;
        }
    } # end function run

    /**
     * Show all the different alerts from the IDS
     * database server
     *
     */
    function showAlerts( )
    {

        $alertoptions=$this->idsadmin->phpsession->get_alertoptions();

        require_once ROOT_PATH."lib/gentab.php";

        if ( isset($this->idsadmin->in['command']) )
        {
            $this->execTaskAction();
        }

        // If user clicked 'viewAlerts' button,
        // reset $alertoptions based on checkboxes from the html form
        if (isset($this->idsadmin->in['viewAlerts'])) {

            $checkboxes = array ("RED", "YELLOW", "GREEN", "ERROR",
        		"INFO", "WARNING", "NEW", "ADDRESSED", "ACKNOWLEDGED", "IGNORED");

            foreach ($checkboxes as $alertparam) {
                if (isset($this->idsadmin->in[$alertparam])) {
                    $alertoptions[$alertparam] = "checked='checked'";
                } else {
                    $alertoptions[$alertparam] = "";
                }
            }
             
            // reset the phpsession's alertoptions
            $this->idsadmin->phpsession->set_alertoptions($alertoptions);
        }

        $dbadmin = $this->idsadmin->get_database("sysadmin");

        $html=<<<END
<form method="post" action="index.php?act=health&amp;do=showAlerts">
<table class="alerts">
<tr>
	<th align="center">{$this->idsadmin->lang('severity')}</th>
	<th align="center">{$this->idsadmin->lang('alerttype')}</th>
	<th align="center">{$this->idsadmin->lang('state')}</th>
    <td rowspan="2">
        <input type="submit" class="button" name="viewAlerts" value="{$this->idsadmin->lang('view')}"/>
    </td>
</tr>

<tr>
    <td>
		<input type="checkbox" class="checkbox" name="RED"     {$alertoptions['RED']} />{$this->idsadmin->lang('COLORRED')}
        <input type="checkbox" class="checkbox" name="YELLOW"  {$alertoptions['YELLOW']} />{$this->idsadmin->lang('COLORYELLOW')}
        <input type="checkbox" class="checkbox" name="GREEN"   {$alertoptions['GREEN']} />{$this->idsadmin->lang('COLORGREEN')}
    </td>
    <td>
		<input type="checkbox" class="checkbox" name="ERROR"   {$alertoptions['ERROR']} />{$this->idsadmin->lang('ERROR')}
        <input type="checkbox" class="checkbox" name="WARNING" {$alertoptions['WARNING']} />{$this->idsadmin->lang('WARNING')}
        <input type="checkbox" class="checkbox" name="INFO"    {$alertoptions['INFO']} />{$this->idsadmin->lang('INFO')}
    </td>
    <td>
		<input type="checkbox" class="checkbox" name="NEW"          {$alertoptions['NEW']} />{$this->idsadmin->lang('NEW')}
        <input type="checkbox" class="checkbox" name="ADDRESSED"    {$alertoptions['ADDRESSED']} />{$this->idsadmin->lang('ADDRESSED')}
        <input type="checkbox" class="checkbox" name="ACKNOWLEDGED" {$alertoptions['ACKNOWLEDGED']} />{$this->idsadmin->lang('ACKNOWLEDGED')}
        <input type="checkbox" class="checkbox" name="IGNORED"      {$alertoptions['IGNORED']} />{$this->idsadmin->lang('IGNORED')}
    </td>
</tr>
</table>
</form>
END;
        $this->idsadmin->html->add_to_output( $html );

        $where = " WHERE " .
      "   UPPER(alert_color) IN " .
      "   ( " .
        ( strpos($alertoptions['RED'], 'checked') !== false ? " 'RED', " : "' ', " ) .
        ( strpos($alertoptions['YELLOW'], 'checked') !== false ? " 'YELLOW', " : "' ', " ) .
        ( strpos($alertoptions['GREEN'], 'checked') !== false ? " 'GREEN' " : "' ' " ) .
      "   ) " .
      "   AND " .
      "   UPPER(alert_type) IN " .
      "   ( " .
        ( strpos($alertoptions['ERROR'], 'checked') !== false ? " 'ERROR', " : "' ', " ) .
        ( strpos($alertoptions['WARNING'], 'checked') !== false ? " 'WARNING', " : "' ', " ) .
        ( strpos($alertoptions['INFO'], 'checked') !== false ? " 'INFO' " : "' ' " ) .
      "   ) " .
      "   AND " .
      "   UPPER(alert_state) IN " .
      "   ( " .
        ( strpos($alertoptions['NEW'], 'checked') !== false ? " 'NEW', " : "' ', " ) .
        ( strpos($alertoptions['ADDRESSED'], 'checked') !== false ? " 'ADDRESSED', " : "' ', " ) .
        ( strpos($alertoptions['ACKNOWLEDGED'], 'checked') !== false ? " 'ACKNOWLEDGED', " : "' ', " ) .
        ( strpos($alertoptions['IGNORED'], 'checked') !== false ? " 'IGNORED' " : "' ' " ) .
      "   ) " ;

        $tab = new gentab($this->idsadmin);
        $qry = "SELECT " .
              " trim(lower(alert_color)) as alert_color, " .
              " alert_type, " .
              " alert_id, " .  
              " alert_message, " .
              " alert_time, "  .
              " trim(alert_state) as alert_state, ".
              " alert_action," ; 
        if ( Feature::isAvailable ( Feature::PANTHER, $this->idsadmin ) )
        {
              $qry .= "trim(alert_object_type) as alert_object_type, alert_object_info, ";
        }
        $qry .= " trim(task_name) as task_name " .
              " FROM ph_alerts " .
        $where .
              " ORDER BY alert_time DESC";

        $qrycnt = "SELECT count( * ) as cnt from ph_alerts " . $where;

        $tab->display_tab_by_page($this->idsadmin->lang("AlertList"),
        array(
                  "1" => "{$this->idsadmin->lang('severity')}",
                  "2" => "{$this->idsadmin->lang('alerttype')}",
                  "3" => "{$this->idsadmin->lang('id')}",
                  "4" => "{$this->idsadmin->lang('message')}",
                  "5" => "{$this->idsadmin->lang('time')}",
                  "6" => "{$this->idsadmin->lang('alertstate')}",
        ),
        $qry, $qrycnt, NULL, "gentab_order_alert.php",$dbadmin);

    }

    function execTaskAction()
    {

        $task_name= $this->idsadmin->in['task_name'];
        $alert_id = $this->idsadmin->in['alert_id'];

        $dbadmin = $this->idsadmin->get_database("sysadmin");
        if (isset($this->idsadmin->in['button_correct']))
        {
            $sql ="select alert_exec_recommend($alert_id) as info" .
             " FROM systables where tabid=1";
            $stmt = $dbadmin->query($sql);
            $res = $stmt->fetch();
            $this->idsadmin->status( $res['INFO'] );
            $stmt->closeCursor();

        }
        elseif (isset($this->idsadmin->in['button_recheck']))
        {

            $sql ="select exectask('{$task_name}') as info" .
             " FROM systables where tabid=1";
            $stmt = $dbadmin->query($sql);
            $dbadmin->exec("UPDATE ph_alert SET ".
                  " alert_state = 'ADDRESSED' where id = " . $alert_id );
        }
        elseif (isset($this->idsadmin->in['button_ignore']))
        {
            $dbadmin->exec("UPDATE ph_alert SET ".
                  " alert_state = 'IGNORED' where id = " . $alert_id );
        }


    }


    # This function display a list of chunks
    #
    # Params:
    #     dbsnum	If no specified all chunks are show
    #                   else just the chunks for the specified
    #                        dbspace are shown
    function showTaskList( )
    {
        if ( isset( $this->idsadmin->in['id'] ) )
        {
            return $this->showTaskDetails( $this->idsadmin->in['id'] );
        }
        require_once ROOT_PATH."lib/gentab.php";

        $group_where=" ";
        $group_default="ALL";
        if ( isset( $this->idsadmin->in['groupname'] ) &&
        strcasecmp('ALL',$this->idsadmin->in['groupname']) !=0 )
        {
            $group_where="AND tk_group = '{$this->idsadmin->in['groupname']}' ";
            $group_default=$this->idsadmin->in['groupname'];
        }

        $html=<<<END
      <form method="get" name="taskGroup" action="index.php">
      <input type="hidden" name="act" value="health" />
      <input type="hidden" name="do"  value="tasklist" />
<table align="center">
<tr>
    <th>{$this->idsadmin->lang('grouptoview')}</th>
    <td>
END;
        $html .= $this->idsadmin->html->autoSelectList("taskGroup","groupname",
                "SELECT 'ALL' as group_id, 'ALL_GROUP_NAME' as group_name " .
                     " FROM systables where tabid=1 UNION " .
                "SELECT group_name, group_name FROM ph_group order by 2",
                "sysadmin",
        $group_default);
        
        // Replace ALL_GROUP_NAME with the localized string 'ALL'
        $this->idsadmin->load_lang("misc_template");
        $html = preg_replace('/ALL_GROUP_NAME/',$this->idsadmin->lang('ALL'),$html);
        
        $html.=<<<END
      </select>
    </td>
    </tr>
</table>
      </form>
END;


        $this->idsadmin->html->add_to_output( $html );

        $tab = new gentab( $this->idsadmin );
        $dbadmin= $this->idsadmin->get_database("sysadmin");

        $burl="'<a href=\"index.php?act=health&amp;do=tasklist&amp;id='";
        $murl="'\">'";
        $eurl="'</a>'";
        $qry = "SELECT " .
             " $burl||tk_id||$murl||tk_name||{$eurl} as name, " .
             " lower(tk_group) as group, " .
             " tk_description, " .
             " tk_next_execution::datetime year to minute as tk_exec, " .
             " tk_frequency, tk_id , tk_name " .
             "FROM ph_task " .
             "WHERE 1=1 " .
        $group_where .
             "ORDER BY tk_name"
             ;

             //$this->idsadmin->html->add_to_output( $qry . "<BR>" );
             $qrycnt = "SELECT count(*) from ph_task where 1=1  $group_where";

              
             $tab->display_tab_by_page("{$this->idsadmin->lang('CronTaskList')}",
             array(
                  "7" => "{$this->idsadmin->lang('name')}",
                  "2" => "{$this->idsadmin->lang('group')}",
                  "3" => "{$this->idsadmin->lang('desc')}",
                  "4" => "{$this->idsadmin->lang('execution')}",
                  "5" => "{$this->idsadmin->lang('frequency')}",

             ),
             $qry, $qrycnt, NULL, "template_gentab_order.php",$dbadmin);

    } #end default


	# This function is for displaying a list of tasks for deletion
	# Most of the contents are the same as the function showTaskList() in this php file
	function showDeleteTask()
	{

	require_once ROOT_PATH."lib/feature.php";

	if ( !Feature::isAvailable ( Feature::CHEETAH2, $this->idsadmin )  )
        {
            $this->idsadmin->html->add_to_output(
		 	$this->idsadmin->lang('FeatureUnavailable'));
            return;
        }

     	if ( $this->idsadmin->isreadonly() )
        {
            $this->idsadmin->fatal_error("<center>{$this->idsadmin->lang("NoPermission")}</center>");
            return;
        }


        require_once ROOT_PATH."lib/gentab.php";

        $group_where=" ";
        $group_default="ALL";
        if ( isset( $this->idsadmin->in['groupname'] ) &&
        strcasecmp('ALL',$this->idsadmin->in['groupname']) !=0 )
        {
            $group_where="WHERE tk_group = '{$this->idsadmin->in['groupname']}' ";
            $group_default=$this->idsadmin->in['groupname'];
        }

        $html=<<<END
      <form method="get" name="deletetaskGroup" action="index.php">
      <input type="hidden" name="act" value="health" />
      <input type="hidden" name="do"  value="showDeleteTask" />
<table align="center">
<tr>
    <th>{$this->idsadmin->lang('grouptoview')}</th>
    <td>
END;
        $html .= $this->idsadmin->html->autoSelectList("deletetaskGroup","groupname",
                "SELECT 'ALL' as group_id, 'ALL_GROUP_NAME' as group_name " .
                     " FROM systables where tabid=1 UNION " .
                "SELECT group_name, group_name FROM ph_group order by 2",
                "sysadmin",
        $group_default);
        
        // Replace ALL_GROUP_NAME with the localized string 'ALL'
        $this->idsadmin->load_lang("misc_template");
        $html = preg_replace('/ALL_GROUP_NAME/',$this->idsadmin->lang('ALL'),$html);
        
        $html.=<<<END
      </select>
    </td>
    </tr>
</table>
      </form>
END;


        $this->idsadmin->html->add_to_output( $html );

        $tab = new gentab( $this->idsadmin );
        $dbadmin= $this->idsadmin->get_database("sysadmin");

        $burl="'<a href=\"index.php?act=health&amp;do=tasklist&amp;id='";
        $murl="'\">'";
        $eurl="'</a>'";
        $qry = "SELECT " .
             " $burl||tk_id||$murl||tk_name||{$eurl} as name, " .
             " lower(tk_group) as group, " .
             " tk_description, " .
             " tk_name, tk_type, tk_result_table, " .
             " bitand(tk_attributes,4)>0 as server_builtin_task, " .
             " tk_id " .
             "FROM ph_task " .
             $group_where .
             "ORDER BY tk_name";

             $qrycnt = "SELECT count(*) from ph_task {$group_where}";

              
             $tab->display_tab_by_page("{$this->idsadmin->lang('CronTaskList')}",
             array(
                  "4" => "{$this->idsadmin->lang('name')}",
                  "2" => "{$this->idsadmin->lang('group')}",
                  "3" => "{$this->idsadmin->lang('desc')}",
                  "6" => "{$this->idsadmin->lang('deltask')}",
             ),
             $qry, $qrycnt, 10, "template_gentab_deletetask.php",$dbadmin);	
	} // end showDeleteTask()
	
	function doDeleteTask()
	{
	
	require_once ROOT_PATH."lib/feature.php";

	if ( !Feature::isAvailable ( Feature::CHEETAH2, $this->idsadmin )  )
        {
            $this->idsadmin->html->add_to_output(
		 	$this->idsadmin->lang('FeatureUnavailable') );
            return;
        }

        if ( $this->idsadmin->isreadonly() )
        {
            $this->idsadmin->fatal_error("<center>{$this->idsadmin->lang("NoPermission")}</center>");
            return;
        }

	$dbadmin= $this->idsadmin->get_database("sysadmin");

        if ( ! isset( $this->idsadmin->in['id'] ) )
        {
            return $this->showDeleteTask();
        }

        $id = $this->idsadmin->in['id'];

        $qry =  "SELECT tk_id, tk_name, tk_type, tk_result_table ".
                        "FROM ph_task ".
                        "WHERE tk_id={$id} ";

        $stmt = $dbadmin->query($qry);
        $error = $dbadmin->errorInfo();

        if(($res=$stmt->fetch(PDO::FETCH_ASSOC))==false){
                $this->idsadmin->error($this->idsadmin->lang("TaskDeleteFailed")."{$this->idsadmin->lang('ErrorF')} {$error[1]} - {$error[2]}");
                return $this->showDeleteTask();
        }else{
                $taskname = trim($res['TK_NAME']);
                $tasktype = trim($res['TK_TYPE']);
                $resulttab = trim($res['TK_RESULT_TABLE']);
        }
        
         $resulttab_array = preg_split("/,/",$resulttab);

        $DeleteResultsTab = $this->idsadmin->in['DeleteResultsTab'];
        
        $PH_TASK_delete = "DELETE FROM ph_task WHERE tk_id={$id}";
        $PH_RUN_delete  = "DELETE FROM ph_run WHERE run_task_id={$id}";
        $PH_THRESHOLD_delete = "DELETE FROM ph_threshold WHERE task_name=:taskname";

       	$dbadmin->exec($PH_TASK_delete);
       	$error1 = $dbadmin->errorInfo();
	    if ($error1[1] != 0) {
	    	$html = "<script language='JavaScript'>\n";
	    	$msg = $this->idsadmin->lang("TaskDeleteFailed")." {$this->idsadmin->lang('ErrorF')} {$error1[1]} - {$error1[2]}";
	    	$html.= "alert('{$msg}');\n";
	    	$html.= "window.location='index.php?act=health&do=showDeleteTask';\n";
	    	$html.= "</script>\n";
	    	
	    	return $this->idsadmin->html->add_to_output($html);
	    	
	    }
	    
	    $dbadmin->exec($PH_RUN_delete);
       	$error2 = $dbadmin->errorInfo();
	    if ($error2[1] != 0) {
	    	$html = "<script language='JavaScript'>\n";
	    	$msg = $this->idsadmin->lang("TaskDeleteFailed")." {$this->idsadmin->lang('ErrorF')} {$error2[1]} - {$error2[2]}";
	    	$html.= "alert('{$msg}');\n";
	    	$html.= "window.location='index.php?act=health&do=showDeleteTask';\n";
	    	$html.= "</script>\n";
	    	
	    	return $this->idsadmin->html->add_to_output($html);
	    }
	    
	    $stmt = $dbadmin->prepare($PH_THRESHOLD_delete);
	    $stmt->bindParam(':taskname',$taskname);
	    $stmt->execute();
       	$error3 = $dbadmin->errorInfo();
	    if ($error3[1] != 0) {
	    	$html = "<script language='JavaScript'>\n";
	    	$msg = $this->idsadmin->lang("TaskDeleteFailed")." {$this->idsadmin->lang('ErrorF')} {$error3[1]} - {$error3[2]}";
	    	$html.= "alert('{$msg}');\n";
	    	$html.= "window.location='index.php?act=health&do=showDeleteTask';\n";
	    	$html.= "</script>\n";
	    	
	    	return $this->idsadmin->html->add_to_output($html);
	    }
	    
	    if(strcasecmp($DeleteResultsTab,'true')==0){
	    	foreach($resulttab_array as $i=>$v)
	    	{
	    		$DROP_RESULT_TAB = "DROP TABLE {$v}";
		    	$dbadmin->exec($DROP_RESULT_TAB);
	       		$error4 = $dbadmin->errorInfo();
		    	if ($error4[1] != 0) {
		    		$html = "<script language='JavaScript'>\n";
		    		$msg = $this->idsadmin->lang("ResultTableDeleteFailed")." {$this->idsadmin->lang('ErrorF')} {$error4[1]} - {$error4[2]}";
		    		$html.= "alert('{$msg}');\n";
		    		$html.= "window.location='index.php?act=health&do=showDeleteTask';\n";
		    		$html.= "</script>\n";
		    		
		    		return $this->idsadmin->html->add_to_output($html);
		    	}
	    	}
	    }
	    
	    $html = "<script language='JavaScript'>\n";
	    $html.= "alert('{$this->idsadmin->lang('TaskDeleteSuccessful')}');\n";
	    $html.= "window.location='index.php?act=health&do=showDeleteTask';\n";
	    $html.= "</script>\n";
	    
	    return $this->idsadmin->html->add_to_output($html);
	} // end doDeleteTask()
	
    # This function display the runtime of all the differnt tasks
    #
    # Params:
    #             NONE
    #
    function showRunTimes( )
    {

        if ( isset( $this->idsadmin->in['id'] ) )
        {
            return $this->showTaskDetails( $this->idsadmin->in['id'] );
        }

        require_once ROOT_PATH."lib/gentab.php";


        $group_where=" ";
        $group_default="ALL";
        if ( isset( $this->idsadmin->in['groupname'] ) &&
        strcasecmp('ALL',$this->idsadmin->in['groupname']) !=0 )
        {
            $group_where="WHERE tk_group = '{$this->idsadmin->in['groupname']}'";
            $group_default=$this->idsadmin->in['groupname'];
        }

        $html=<<<END
<table align="center">
<tr>
    <th>{$this->idsadmin->lang('grouptoview')}</th>
    <td>
      <form method="get" name="groupTaskRun" action="index.php?act=health&amp;do=runtimes">
      <input type="hidden" name="act" value="health" />
      <input type="hidden" name="do"  value="runtimes" />
END;
        $html .= $this->idsadmin->html->autoSelectList("groupTaskRun","groupname",
                "SELECT 'ALL' as group_id, 'ALL_GROUP_NAME' as group_name " .
                     " FROM systables where tabid=99 UNION " .
                "SELECT group_name, group_name FROM ph_group order by 2",
                "sysadmin",
        $group_default);
        
        // Replace ALL_GROUP_NAME with the localized string 'ALL'
        $this->idsadmin->load_lang("misc_template");
        $html = preg_replace('/ALL_GROUP_NAME/',$this->idsadmin->lang('ALL'),$html);
        
        $html.=<<<END
      </select>
      </form>
    </td>

    </tr>
</table>
END;

        $this->idsadmin->html->add_to_output( $html );

        $tab = new gentab($this->idsadmin);

        $dbadmin= $this->idsadmin->get_database("sysadmin");

        $burl="'<a href=\"index.php?act=health&amp;do=runtimes&amp;id='";
        $murl="'\">'";
        $eurl="'</a>'";

        if ($this->idsadmin->phpsession->serverInfo->isPrimary() ||
        $this->idsadmin->phpsession->serverInfo->isSDS())
        {
            // If server is a primary or SDS, then run a join query that includes
            // each task's last run time in the query results.
            $qry = " SELECT " .
                       " $burl||tk_id||$murl||tk_name||{$eurl} as name, " .
                       " tk_total_executions num_execs, " .
                       "trunc(tk_total_time/ " .
                       "(decode(tk_total_executions,0,1,tk_total_executions)) ,2)  AVG," .
                       " trunc(tk_total_time,2) TOTAL , last_run_time, last_run_retcode, tk_name  " .
                       "FROM ph_task LEFT OUTER JOIN " .
                       "(SELECT ph_run.run_task_id , ph_run.run_retcode as last_run_retcode, ph_run.run_time as last_run_time ".
                       "FROM (SELECT run_task_id, max(run_time) as max_run_time ".
                                "FROM ph_run GROUP BY run_task_id) as temp ".
                       "INNER JOIN ph_run ".
                       "ON ph_run.run_task_id=temp.run_task_id ".
                       "AND ph_run.run_time=temp.max_run_time) AS run_times ".
                       "ON ph_task.tk_id = run_times.run_task_id " .

            $group_where .
                       "order by tk_name";
            $columns = array(
                       "7" => $this->idsadmin->lang("name"),
                       "2" => $this->idsadmin->lang("num_executions"),
                       "3" => $this->idsadmin->lang("avg_time"),
                       "4" => $this->idsadmin->lang("total_time"),
                       "5" => $this->idsadmin->lang("last_run_time"),
                       "6" => $this->idsadmin->lang("last_execution_status")
                       );
        } else {
            // For HDR and RSS secondaries, the above query will not work (error -229).
            // So run the query without the join to get the last run time.
            $qry = " select " .
                        " $burl||tk_id||$murl||tk_name||{$eurl} as name, " .
                        " tk_total_executions num_execs, " .
                        "trunc(tk_total_time/ " .
                        "(decode(tk_total_executions,0,1,tk_total_executions)) ,2)  AVG," .
                        " trunc(tk_total_time,2) TOTAL , tk_name " .
                        "from ph_task " .
            $group_where .
                        "order by tk_name";
            $columns = array(
                       "5" => $this->idsadmin->lang("name"),
                       "2" => $this->idsadmin->lang("num_executions"),
                       "3" => $this->idsadmin->lang("avg_time"),
                       "4" => $this->idsadmin->lang("total_time"),
            );
        }

        $qrycnt = "SELECT count(*) from ph_task $group_where ";

        $tab->display_tab_by_page($this->idsadmin->lang("task_run_list"), $columns,
        $qry, $qrycnt, NULL, "gentab_show_task_runtimes.php",$dbadmin);

    } #end function


    # This function display a list of chunks
    #
    # Params:
    #     dbsnum	If no specified all chunks are show
    #                   else just the chunks for the specified
    #                        dbspace are shown
    function showSched( )
    {
         
        if ( isset($this->idsadmin->in['id']) )
        {
            return $this->showTaskDetails($this->idsadmin->in['id']);
        }

        require_once ROOT_PATH."lib/gentab.php";

        $tab = new gentab( $this->idsadmin );
        $dbadmin= $this->idsadmin->get_database("sysadmin");

        $burl="'<a href=\"index.php?act=health&amp;do=taskdetails&amp;id='";
        $murl="'\">'";
        $eurl="'</a>'";
        $qry = "SELECT " .
          " tk_name, " .
          " tk_start_time, " .
          " NVL(tk_stop_time,'NEVER') as stop_time, " .
          " tk_frequency, " .
          " tk_Monday, tk_Tuesday, tk_Wednesday, " .
          " tk_Thursday, tk_Friday," .
          " tk_Saturday, tk_Sunday, tk_enable, " .
          " tk_id " .
          " FROM  ph_task " .
          " WHERE tk_id > 0 " .
          " AND tk_type <> 'QUEUEDJOB' " .
          " ORDER BY tk_name " ;

        $qrycnt = "SELECT count(*) from ph_task " .
              " WHERE tk_id > 0 " .
              " AND tk_type <> 'QUEUEDJOB' ";

        $tab->display_tab_by_page($this->idsadmin->lang('TaskSchedTabHdr'),
        array(
                  "1" => "{$this->idsadmin->lang('name')}",
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
                  "12" => "{$this->idsadmin->lang('Enabled')}",
        ),
        $qry, $qrycnt, NULL, "gentab_show_sched.php",$dbadmin);

	if ( !$this->idsadmin->isreadonly() )
	{
	
		require_once ROOT_PATH."lib/feature.php";

		if ( Feature::isAvailable ( Feature::CHEETAH2, $this->idsadmin )  )
    		{	
    		$html=<<<END
<table>
<tr>
	<td>
	<form method="post" action="index.php?act=health&amp;do=AddNewTask">
	<input type="submit" class="button" name="AddNewTask" value="{$this->idsadmin->lang('AddNewTask')}" />
	</form>
	</td>
	
	<td>
	<form method="post" action="index.php?act=health&amp;do=showDeleteTask">
	<input type="submit" class="button" name="DeleteTask" value="{$this->idsadmin->lang('deletetask')}" />
	</form>
	</td>
</tr>
</table>
END;
    		} else {
    		$html=<<<END
<table>
<tr>
	<td>
	<form method="post" action="index.php?act=health&amp;do=AddNewTask">
	<input type="submit" class="button" name="AddNewTask" value={$this->idsadmin->lang('AddNewTask')} />
	</form>
	</td>
</tr>
</table>
END;
    		}

		$this->idsadmin->html->add_to_output($html);       
	}
    } #end function
    
    function showTaskDetails( $tk_id = "" , $caller = "")
    {

        $save = "<input type=\"submit\" class='button' name=\"saveTaskDetails\" value=\"{$this->idsadmin->lang('Save')}\" />";
        $cancel = "<input type=\"submit\" class='button' name=\"cancelSaveTaskDetails\" value=\"{$this->idsadmin->lang('Cancel')}\" />";
        $saveEditParamButton = " <input type='submit' class='button' name='edittaskdetails' value=\"{$this->idsadmin->lang('EditParam')}\"/>";
        $readonly = "";
        $disabled = "";

        if ( $this->idsadmin->isreadonly() )
        {
            $save = $saveEditParamButton = "&nbsp;";
            $readonly="READONLY";
            $disabled="DISABLED";
        }

        require_once ROOT_PATH."lib/gentab.php";
        
         /* setup $caller if not passed in 
          * via function call and is set via idsadmin->in 
          */
         if ( isset ( $this->idsadmin->in['caller'] ) && $caller == "" )
         {
         	$caller = $this->idsadmin->in['caller'];
         }
         /* if we are in online log / bar activity log admin 
          * then no need to display the cancel button.
          */
         if ( $caller == "olmsg" || $caller == "baractlog" || $caller == "healthadv" ) 
         {
         	$cancel = "";
         }

        if ( isset($this->idsadmin->in['saveTaskDetails']) ) 
        {
            $this->saveTaskDetails( $tk_id,$caller );
        } 
        elseif ( isset($this->idsadmin->in['cancelSaveTaskDetails']) ) 
        {
            if(isset($this->idsadmin->in['caller'])&&($this->idsadmin->in['caller']=="aus"))
            {
            	/* redirect to AUS info tab (act=updstat, do=admin)*/
            	/* when scheduler is called from updstats.php, */
            	/* that is when user clicked on the AUS tasks in AUS info tab */
            	/* , this will pass the caller value in the in array */
            	$rurl = "index.php?act=updstats&do=admin";
            	$rtitle = $this->idsadmin->lang("aus");
            	$this->idsadmin->html->add_to_output($this->idsadmin->template['template_global']->global_redirect($rtitle,$rurl));
            }
            else
            {
            	/* redirect to tasklist if user cancels modifications */
            	/* do not use &amp; for & in the argument to global_redirect */
            	$this->idsadmin->html->add_to_output($this->idsadmin->template['template_global']->
            	global_redirect("Health Center -> Task List", "index.php?act=health&do=tasklist"));	
            }
        } elseif ( isset($this->idsadmin->in['saveTaskParameter']) ) {
        	$this->saveTaskParameter($caller);
        }

        $dbadmin= $this->idsadmin->get_database("sysadmin");

        $qry = "SELECT tk_name, " .
          " tk_id, " .
          " tk_start_time, " .
          " tk_description, " .
          " tk_create, ".
          " tk_result_table, ".
          " tk_execute, ".
          " NVL(tk_stop_time,'NEVER') as stop_time, " .
          " NVL(tk_frequency,'NULL') as tk_frequency, " .
          " tk_Monday, tk_Tuesday, tk_Wednesday, " .
          " tk_Thursday, tk_Friday," .
          " tk_Saturday, tk_Sunday, " .
          " tk_delete, " .
          " tk_type, " .
          " tk_enable " .
          " FROM  ph_task " .
          " WHERE tk_id = " . $tk_id .
          " AND tk_type <> 'QUEUEDJOB' " ;

        $stmt = $dbadmin->query( $qry );
        if (($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
        {
            $this->idsadmin->error( $this->idsadmin->lang('TaskNotFound') );
            return;
        }

        $qry1 = "SELECT " .
          "id, name, task_name, " .
          " value, value_type, " .
          " tk_id, tk_name, tk_description, " .
          " description " .
          " from " .
          " ph_task, ph_threshold " .
          " WHERE tk_name = '{$res['TK_NAME']}'" .
          " AND task_name = '{$res['TK_NAME']}'" .
          " AND task_name = tk_name";

        $HTML=<<<END
<form method="post" action="index.php?act=health&amp;do=taskdetails&amp;id={$res['TK_ID']}">
<input type="hidden"  name="act" value="health"/>
<input type="hidden"  name="do" value="taskdetails"/>
<input type="hidden"  name="id" value="{$res['TK_ID']}"/>
<input type="hidden"  name="tk_name" value="{$res['TK_NAME']}"/>
<input type="hidden"  name="caller" value="{$caller}"/>
<table class='tasks' align='center' cellpadding='2' cellspacing='5'>
<tr>
<td class="tblheader" colspan="2" align="center">{$this->idsadmin->lang('taskdetails')}</td>
</tr>
<tr>
    <th> {$this->idsadmin->lang('taskname')} </th>
    <td> {$res['TK_NAME']} </td>
</tr>
<tr>
    <th> {$this->idsadmin->lang('id')} </th>
    <td> {$res['TK_ID']} </td>
</tr>
<tr>
    <th> {$this->idsadmin->lang('description')} </th>
    <td> 
     <textarea {$readonly} name="description" cols="40" rows="4" >{$res['TK_DESCRIPTION']}</textarea>
     </td>
</tr>

<tr>
    <th>{$this->idsadmin->lang('executionstmt')}</th>
	<td>
	 <textarea {$readonly} name="execute" cols="40" rows="4" wrap="soft">{$res['TK_EXECUTE']}</textarea>
	</td>
</tr>

END;
        // Show tk_result_table field only for SENSOR tasks
        if (strcasecmp(trim($res['TK_TYPE']),"SENSOR") == 0 ||
        strcasecmp(trim($res['TK_TYPE']),"STARTUP SENSOR") == 0)
        {  
			$HTML.=<<<END
<tr>
	<th> {$this->idsadmin->lang('resulttable')} </th>
	<td>{$res['TK_RESULT_TABLE']}</td>
</tr>
END;
        }
        
        $HTML.=<<<END
<tr>
    <th> {$this->idsadmin->lang('starttime')} </th>
    <td> 
END;
        $HTML.=$this->create_time_select( "START", $res['TK_START_TIME'] , $disabled );
        $HTML.=<<<END
    </td>
</tr>
<tr>
    <th> {$this->idsadmin->lang('stopttime')} </th>
    <td>
END;
        $never_checked = (strcasecmp(trim($res['STOP_TIME']),"NEVER") == 0)? "CHECKED":"";
        $stop_disabled = ($disabled == "" && $never_checked == "")? "":"DISABLED";
        $HTML.=$this->create_time_select( "STOP", $res['STOP_TIME'] , $stop_disabled );
        $HTML.=<<<END
        <input name='STOPTIME_NEVER' type='checkbox' class='checkbox' {$disabled} {$never_checked} 
               onchange='enable_disable("STOP_HOUR");enable_disable("STOP_MINUTE");enable_disable("STOP_SECOND");'>
              {$this->idsadmin->lang("NEVER")}</input>
    </td>
</tr>
<tr>
    <th> {$this->idsadmin->lang('frequency')}</th>
    <td> 
END;
        $null_checked = (strcasecmp(trim($res['TK_FREQUENCY']),"NULL") == 0)? "CHECKED":"";
        $frequency_disabled = ($disabled == "" && $null_checked == "")? "":"DISABLED";
        $HTML.=$this->create_interval_select( "INTERVAL", $res['TK_FREQUENCY'] , $frequency_disabled );
        $HTML.=<<<END
     	<input name='FREQUENCY_NULL' type=checkbox class=checkbox {$disabled} {$null_checked} 
               onchange='enable_disable("INTERVAL_DAYS");enable_disable("INTERVAL_HOUR");enable_disable("INTERVAL_MINUTE");enable_disable("INTERVAL_SECOND");'>
              {$this->idsadmin->lang("NULL")}</input>
     </td>
</tr>
END;
        // Show tk_delete field only for SENSOR tasks
        if (strcasecmp(trim($res['TK_TYPE']),"SENSOR") == 0 ||
        strcasecmp(trim($res['TK_TYPE']),"STARTUP SENSOR") == 0)
        {
            $HTML.=<<<END
<tr>
    <th> {$this->idsadmin->lang('datadelete')} </th>
    <td> 
END;
            $HTML.=$this->create_interval_select( "DELETE", $res['TK_DELETE'] , $disabled );
            $HTML.=<<<END
     </td>
</tr>
END;
        }
           $HTML.=<<<END
<tr>
<td colspan="2">
<table id="daysofweek" width="100%" border="0">
    <tr>
    <th> {$this->idsadmin->lang('WeekDay1')}</th>
    <td> 
END;
        $HTML.=$this->create_check_select( "MONDAY", $res['TK_MONDAY'] , $disabled );
        $HTML.=<<<END
</td>
    <th> {$this->idsadmin->lang('WeekDay2')}</th>
    <td> 
END;
        $HTML.=$this->create_check_select( "TUESDAY", $res['TK_TUESDAY'] , $disabled );
        $HTML.=<<<END
    </td>
    </tr>
    <tr>
    <th> {$this->idsadmin->lang('WeekDay3')}</th>
    <td>
END;
        $HTML.=$this->create_check_select( "WEDNESDAY", $res['TK_WEDNESDAY'] , $disabled );
        $HTML.=<<<END
    </td>
    <th> {$this->idsadmin->lang('WeekDay4')}</th>
    <td>
END;
        $HTML.=$this->create_check_select( "THURSDAY", $res['TK_THURSDAY'] , $disabled );
        $HTML.=<<<END
    </td>
    <tr>
  
    <th> {$this->idsadmin->lang('WeekDay5')}</th>
    <td>
END;
        $HTML.=$this->create_check_select( "FRIDAY", $res['TK_FRIDAY'] , $disabled );
        $HTML.=<<<END
    </td>
    </tr>
    <tr>
    <th> {$this->idsadmin->lang('WeekDay6')}</th>
    <td>
END;
        $HTML.=$this->create_check_select( "SATURDAY", $res['TK_SATURDAY'] , $disabled );
        $HTML.=<<<END
    </td>
    <th> {$this->idsadmin->lang('WeekDay7')}</th>
    <td>
END;
        $HTML.=$this->create_check_select( "SUNDAY", $res['TK_SUNDAY'] , $disabled );
        $HTML.=<<<END
    </td>
    </tr>
</table><!-- daysofweek -->
</td>
</tr>
END;
	if($res['TK_ENABLE']){
		$HTML.="<tr><th colspan='2'><input {$disabled} type='checkbox' class='checkbox' name=\"tk_enable\" checked='checked'>{$this->idsadmin->lang('enabletask')}</th></tr>";
	}else{
		$HTML.="<tr><th colspan='2'><input {$disabled} type='checkbox' class='checkbox' name=\"tk_enable\" >{$this->idsadmin->lang('enabletask')}</th></tr>";
	}
	
	$HTML.=<<<END
<tr>
<td colspan="2" align="center">
        {$save} {$cancel}
</td>
</tr>
</form>

END;

        $print_header=0;
        $stmt1 = $dbadmin->query( $qry1 );
        while ($res1 = $stmt1->fetch())
        {
            if ( $print_header==0 )
            {
                $HTML.=<<<END
 <tr>
<td class="tblheader" colspan="2" align="center">{$this->idsadmin->lang('taskparams')}</td>
</tr>
END;
            }
            $print_header++;

            $HTML.=<<<END
<tr>
    <th> {$this->idsadmin->lang('paramname')} </th>
    <td> {$res1['NAME']} </td>
</tr>
<tr>
    <th > {$this->idsadmin->lang('description')} </th>
    <td> {$res1['DESCRIPTION']} </td>
</tr>
<tr>
    <th> {$this->idsadmin->lang('value')} </th>
    <td> {$res1['VALUE']} </td>
</tr>
<tr>
    <th> {$this->idsadmin->lang('valuetype')} </th>
    <td> {$res1['VALUE_TYPE']} </td>
</tr>
<tr align="center">
   <td colspan="2">
<form method="post" action="index.php?act=health&amp;do=edittaskparam">
   <input type="hidden" name="param_id" value="{$res1['ID']}"/>
   <input type="hidden" name="tk_name" value="{$res1['TK_NAME']}"/>
   <input type="hidden" name="tk_type" value="{$res['TK_TYPE']}">
   <input type="hidden"  name="caller" value="{$caller}"/>
   {$saveEditParamButton}
</form>
   </td>
</tr>

END;
        }

        $HTML.=<<<END
</table>

END;
        $this->idsadmin->html->add_to_output( $HTML );

    } #end function

    # This function allows users to edit task parameters
    #
    # Params:
    #     param_id         param id
    #     tk_name	 task name
    function editTaskParam( $param_id=1, $tk_name="",$caller="" )
    {
        $this->checkAccess();
        require_once ROOT_PATH."lib/gentab.php";
        $dbadmin= $this->idsadmin->get_database("sysadmin");

        $qry = "SELECT " .
          " id, name, task_name, " .
          " value, value_type, " .
          " tk_id, tk_name, tk_description, " .
          " description " .
          " from " .
          " ph_task, ph_threshold " .
          " WHERE tk_name = '$tk_name'" .
          " AND task_name = '$tk_name'" .
          " AND task_name = tk_name " .
          " AND id = $param_id";

        $stmt = $dbadmin->query( $qry );
        if (($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
        {
            $errmsg=$this->idsadmin->lang('TaskParamNotFound');
            $this->idsadmin->error( $errmsg );
            return;
        }

        $HTML.=<<<END
<form method="post" action="index.php?act=health&amp;do=taskdetails&amp;id={$res['TK_ID']}">
<table class="tasks" align="center" cellpadding="2" cellspacing="5">
<tr>
<td class="tblheader" colspan="2" align="center">{$this->idsadmin->lang('edittaskparam')}</td>
</tr>
<tr>
    <th> {$this->idsadmin->lang('taskname')} </th>
    <td> {$res['TK_NAME']} </td>
</tr>
<tr>
    <th> {$this->idsadmin->lang('paramname')} </th>
    <td> {$res['NAME']} </td>
</tr>
<tr>
    <th > {$this->idsadmin->lang('description')} </th>
    <td> <textarea name="description" cols="40" rows="4" >{$res['DESCRIPTION']}</textarea></td>
</tr>
<tr>
    <th> {$this->idsadmin->lang('value')} </th>
    <td> <input type='text' name='param_value' value='{$res['VALUE']}' /> </td>
</tr>
<tr>
    <th> {$this->idsadmin->lang('valuetype')} </th>
    <td> {$res['VALUE_TYPE']} </td>
</tr>
<tr align="center">
   <td colspan="2">
   <input type="hidden" name="param_id" value="{$res['ID']}"/> 
   <input type="hidden" name="tk_id" value="{$res['TK_ID']}"/>
   <input type="hidden" name="tk_name" value="{$res['TK_NAME']}"/>
   <input type="hidden" name="param_name" value="{$res['NAME']}"/>
   <input type="hidden" name="caller" value="{$caller}"/>
   <input type="submit" class="button" name="saveTaskParameter" value="{$this->idsadmin->lang('Save')}"/>
   <input type="button" class="button" name="cancel" value="{$this->idsadmin->lang('Cancel')}" onclick="history.back()"/>
   </td>
</tr>
</table>
</form>

END;
        $this->idsadmin->html->add_to_output( $HTML );

    } #end function editTaskParam
     
    # This function saves changes to task details
    #
    # Params:
    #     tk_id         task id
    function saveTaskDetails( $tk_id=1,$caller="" )
    {
        $this->checkAccess();
         
        if (strcasecmp($this->idsadmin->in['execute'],"")==0)
        {
            $this->idsadmin->error($this->idsadmin->lang('NoExecutionStmt'));
            return;
        }
        
        $dbadmin= $this->idsadmin->get_database("sysadmin");

        $start_time = "'" . ($this->idsadmin->in['START_HOUR'] < 10 ? "0":"") .
        $this->idsadmin->in['START_HOUR'] . ":" .
        ($this->idsadmin->in['START_MINUTE'] < 10 ? "0":"") .
        $this->idsadmin->in['START_MINUTE'] . ":" .
        ($this->idsadmin->in['START_SECOND'] < 10 ? "0":"") .
        $this->idsadmin->in['START_SECOND'] . "'";
        $stop_time = "'" . ($this->idsadmin->in['STOP_HOUR'] < 10 ? "0":"") .
        $this->idsadmin->in['STOP_HOUR'] . ":" .
        ($this->idsadmin->in['STOP_MINUTE'] < 10 ? "0":"") .
        $this->idsadmin->in['STOP_MINUTE'] . ":" .
        ($this->idsadmin->in['STOP_SECOND'] < 10 ? "0":"") .
        $this->idsadmin->in['STOP_SECOND'] . "'";
        if (isset($this->idsadmin->in['STOPTIME_NEVER']))
        {
            $stop_time = "NULL";
        }
        $frequency = "'" . $this->idsadmin->in['INTERVAL_DAYS'] . " " .
        ($this->idsadmin->in['INTERVAL_HOUR'] < 10 ? "0":"") .
        $this->idsadmin->in['INTERVAL_HOUR'] . ":" .
        ($this->idsadmin->in['INTERVAL_MINUTE'] < 10 ? "0":"") .
        $this->idsadmin->in['INTERVAL_MINUTE'] . ":" .
        ($this->idsadmin->in['INTERVAL_SECOND'] < 10 ? "0":"") .
        $this->idsadmin->in['INTERVAL_SECOND'] . "'";
        if (isset($this->idsadmin->in['FREQUENCY_NULL']))
        {
            $frequency = "NULL";
        }
        if (isset($this->idsadmin->in['DELETE_DAYS']))
        {
            $delete = "'" . $this->idsadmin->in['DELETE_DAYS'] . " " .
            ($this->idsadmin->in['DELETE_HOUR'] < 10 ? "0":"") .
            $this->idsadmin->in['DELETE_HOUR'] . ":" .
            ($this->idsadmin->in['DELETE_MINUTE'] < 10 ? "0":"") .
            $this->idsadmin->in['DELETE_MINUTE'] . ":" .
            ($this->idsadmin->in['DELETE_SECOND'] < 10 ? "0":"") .
            $this->idsadmin->in['DELETE_SECOND'] . "'";
        }
        $enable = (strcasecmp($this->idsadmin->in['tk_enable'],"on")==0)?"T":"F";

        $update = "UPDATE ph_task " .
                " SET tk_description= :description, " .
                " tk_start_time = $start_time, " .
                " tk_stop_time = $stop_time, " .
                " tk_frequency = $frequency, " .
                " tk_execute = :execute_stmt, ".
                " tk_monday = '{$this->idsadmin->in['MONDAY']}', " .
                " tk_tuesday = '{$this->idsadmin->in['TUESDAY']}', " .
                " tk_wednesday = '{$this->idsadmin->in['WEDNESDAY']}', " .
                " tk_thursday = '{$this->idsadmin->in['THURSDAY']}', " .
                " tk_friday = '{$this->idsadmin->in['FRIDAY']}', " .
                " tk_saturday = '{$this->idsadmin->in['SATURDAY']}', " .
                " tk_sunday = '{$this->idsadmin->in['SUNDAY']}', ".
                " tk_enable = '{$enable}'";
        if (isset($this->idsadmin->in['DELETE_DAYS']))
        {
            $update .=  ", tk_delete = $delete";
        }
        
        if (isset($caller) && ($caller == "aus"))
        {
        	// When calling save task from the AUS page and updating the Refresh task,
        	// we want to save the same changes to all AUS Refresh threads.
        	$tk_name = $this->idsadmin->in['tk_name'];
        	if (strstr($tk_name, "Auto Update Statistics Refresh") != false)
        	{
        		$update .= " WHERE tk_name like 'Auto Update Statistics Refresh%'";
        	} else {
        		$update .= " WHERE tk_id = " . $tk_id;
        	}
        } else {
        	$update .= " WHERE tk_id = " . $tk_id;
        } 
        $update .= " AND tk_type <> 'QUEUEDJOB' ";

        $stmt = $dbadmin->prepare($update);
        $stmt->bindParam(':description', $this->idsadmin->in['description']);
        $stmt->bindParam(':execute_stmt', $this->idsadmin->in['execute']);
        $stmt->execute();
        $err_r = $dbadmin->errorInfo();
        if ($err_r[1] != 0) {
            $this->idsadmin->error("{$this->idsadmin->lang('save_failed')}<br> {$this->idsadmin->lang('ErrorF')} {$err_r[1]} {$err_r[2]} ");
        } else {
            $this->idsadmin->status($this->idsadmin->lang('taskdetailssaved'));

            if( isset($caller) && $caller != "" ) 
            {
            	switch ($caller)
            	{
            	   case "aus":
            	   /*if savetaskdetails is called from aus, i.e. when user clicked on
            	       an AUS task in the AUS info tab.
            	       Then when the save is completed, it should redirect user back to AUS page.*/
            	       $rurl = "index.php?act=updstats&do=admin&saveTask=ok";
            	       $rtitle = $this->idsadmin->lang("aus");
            	       break;
            	   
            	   /* if called from online log admin redirect
            	    * back to there.
            	    */
                    case "olmsg":
                        $rurl = "index.php?act=show&do=onlineLogAdmin&saveTask=ok";
                        $rtitle = "";
                        break;
                    case "healthadv":
                        $rurl = "index.php?act=ibm/hadv/healthadv&do=showModifySchedule&saveTask=ok";
                        $rtitle = "";
                        break;
                    case "baractlog":
                        $rurl = "index.php?act=show&do=barActLogAdmin&saveTask=ok";
                        $rtitle = "";
                        break;
                }            
            	$this->idsadmin->html->add_to_output($this->idsadmin->template['template_global']->global_redirect($rtitle,$rurl));
            }
        }
        
    } # end function saveTaskDetails


    # This function saves changes to a task parameter
    function saveTaskParameter($caller="")
    {
        $dbadmin= $this->idsadmin->get_database("sysadmin");

        $id = $this->idsadmin->in['param_id'];
        $tk_name = $this->idsadmin->in['tk_name'];
        $param_name = $this->idsadmin->in['param_name'];
        $desc = $this->idsadmin->in['description'];
        $value = $this->idsadmin->in['param_value'];
         
        $update = "UPDATE ph_threshold " .
                " SET value= '{$value}', " .
                " description= :description " .
                " WHERE id = " . $id .
                " AND task_name = '{$tk_name}' ";
                " AND name = '{$param_name}' ";

        $stmt = $dbadmin->prepare($update);
        $stmt->bindParam(':description', $desc);
        $stmt->execute();
        $err_r = $dbadmin->errorInfo();
        if ($err_r[1] != 0) 
        {
            $this->idsadmin->error("{$this->idsadmin->lang('savetaskparamfailed')} <br> {$this->idsadmin->lang('ErrorF')} {$err_r[1]} {$err_r[2]} ");
        } 
        else 
        {
            $this->idsadmin->status($this->idsadmin->lang('TaskParamSaved'));
            if( isset($caller) && $caller != "" )
            {
            	switch($caller)
            	{
            		case "aus":
            			/*if savetaskdetails is called from aus, i.e. when user clicked on
            			 an AUS task in the AUS info tab.
            			 Then when the save is completed, it should redirect user back to AUS page.*/
            			$rurl = "index.php?act=updstats&do=admin&saveTask=ok";
            			$rtitle = $this->idsadmin->lang("aus");
            			break;
            		case "olmsg":
            			/* if called from online log admin
            			 * then redirect to there.
            			 */
            			$rurl = "index.php?act=show&do=onlineLogAdmin&saveTask=ok";
            			$rtitle = "";
            			break;
            		case "healthadv":
            			/* if called from online health advisor 
            			 * then redirect to there.
            			 */
            			$rurl = "index.php?act=ibm/hadv/healthadv&do=showModifySchedule&saveTask=ok";
            			$rtitle = "";
            			break;
            		case "baractlog":
            			/* if called from online log admin
            			 * then redirect to there.
            			 */
            			$rurl = "index.php?act=show&do=barActLogAdmin&saveTask=ok";
            			$rtitle = "";
            			break;
            	}
            	$this->idsadmin->html->add_to_output($this->idsadmin->template['template_global']->global_redirect($rtitle,$rurl));
            }
        }
    } # end function saveTaskParameter
     

    function get_time_info($value, $timestr, $item)
    {
        $pieces = explode(":",$timestr);

        if( isset($pieces[$item]) && $pieces[$item] == $value )
        return "\"$value\" SELECTED";
        return "\"$value\"";
    }
    
    function create_time_select( $name, $time_str , $disabled="")
    {
        
        $html="<select id='{$name}_HOUR' {$disabled} name='{$name}_HOUR' szie='1'>
          ";

        for($i=0; $i <= 23; $i++)
        {
            $html .= "<option value=" .
            $this->get_time_info($i,$time_str,0) .
                 ">" .
            $i .
                  "</option>
                   ";
        }

        $html.="</select>";
        $html.=":";
        $html.="<select id='{$name}_MINUTE' {$disabled} name='{$name}_MINUTE' size='1'>
           ";

        for($i=0; $i <= 59; $i++)
        {
            $html .= "<option value=" .
            $this->get_time_info($i,$time_str,1) .
                 ">" .
            $i .
                  "</option>
                  ";
        }
        $html.="</select>";
        $html.=":";
        $html.="<select id='{$name}_SECOND' {$disabled} name='{$name}_SECOND' size='1'>";

        for($i=0; $i <= 59; $i++)
        {
            $html .= "<option value=" .
            $this->get_time_info($i,$time_str,2) .
                 ">" .
            $i .
                  "</option>
                  ";
        }
        $html.="</select>";
        
        return $html;
    }

    function create_interval_select( $name, $interval_str , $disabled = "" )
    {
        $html = "";

        $time_str=str_replace(" ",":",ltrim($interval_str));

        $html.="<select id='{$name}_DAYS' {$disabled} name='{$name}_DAYS' size='1'>
          ";

        for($i=0; $i <= 99; $i++)
        {
            $html .= "<option value=" .
            $this->get_time_info($i,$time_str,0) .
                 ">" .
            $i .
                  "</option>
                   ";
        }

        $html.="</select>";
        $html.="{$this->idsadmin->lang('Days')}  ";

        $html.="<select id='{$name}_HOUR' {$disabled} name='{$name}_HOUR' size='1'>
          ";

        for($i=0; $i <= 23; $i++)
        {
            $html .= "<option value=" .
            $this->get_time_info($i,$time_str,1) .
                 ">" .
            $i .
                  "</option>
                   ";
        }

        $html.="</select>";
        $html.="{$this->idsadmin->lang('Hours')}  ";
        $html.="<select id='{$name}_MINUTE' {$disabled} name='{$name}_MINUTE' size=1'>
           ";

        for($i=0; $i <= 59; $i++)
        {
            $html .= "<option value=" .
            $this->get_time_info($i,$time_str,2) .
                 ">" .
            $i .
                  "</option>
                  ";
        }
        $html.="</select>";
        $html.="{$this->idsadmin->lang('Minutes')}  ";
		$html.="<select id='{$name}_SECOND' {$disabled} name='{$name}_SECOND' size='1'>
           ";

        for($i=0; $i <= 59; $i++)
        {
            $html .= "<option value=" .
            $this->get_time_info($i,$time_str,3) .
                 ">" .
            $i .
                  "</option>
                  ";
        }
        $html.="</select>";
        $html.="{$this->idsadmin->lang('Seconds')}  ";
        
        return $html;
    }

    function create_check_select($name, $value , $disabled="")
    {
        $html="<select {$disabled} name='{$name}' size='1'>";

        $html .= "<option value='T' " ;
        if ($value != 0 )
        {
        	$html .= " selected='selected'" ;
        }
        $html .= " > {$this->idsadmin->lang('Enabled')}";
        $html .= "<option value='F' " ;
        if ($value == 0 )
        {
        	$html .= " selected='selected'" ;
        }
        $html .= " > {$this->idsadmin->lang('Disabled')}";

        $html.="</select>";

        return $html;

    }

    function checkAccess()
    {
        if ( $this->idsadmin->isreadonly() )
        {
            $this->idsadmin->fatal_error("<center>{$this->idsadmin->lang('NoPermission')}</center>");
        }
    }// end function checkAccess()
    
  function AddNewTaskPageSelect()
  {
     $this->idsadmin->set_redirect("sched");
     
     if ( $this->idsadmin->isreadonly() )
        {
            $this->idsadmin->fatal_error("<center>{$this->idsadmin->lang("NoPermission")}</center>");
            break;
        }

    /*When user clicks "Cancel" in the new task setup process, $this->idsadmin->in['Cancel'] is set
    OAT will redirect to task scheduler page and no changes in the server will be made.*/
    if(isset($this->idsadmin->in['Cancel'])){
    	$this->idsadmin->html->add_to_output($this->idsadmin->template['template_global']->
        global_redirect($this->idsadmin->lang("Scheduler_pagetitle"), "index.php?act=health&do=sched"));
        break;
    }
    
    /*When user just clicked "Add a New Task" button and started the new task setup process, the code
    below will run.
    Or when user clicked "Next", "Back" or "Finish" button during the setup process, the code below
    will run.
    The code below determines which installation step(i.e. $pageno) should the user go to based on
    whether they click "Next","Back" or "Finish". The code below also does some error checking on the
    user input fields.*/  
    $pageno = (isset($this->idsadmin->in['PageNo']))?($this->idsadmin->in['PageNo']):null;
    switch ($pageno){
    	
    	default; //When users first click the "Add a New Task" button
    		$this->idsadmin->in['PageNo']=0;
    		//The new task setup process has multiple steps and each step is submitting an html
    		//form. All form data is resubmitted and is stored in the in[] array.
    		//The in['Next'] and in['Back'] stores info about whether users clicked "Next" or "Back"
    		//The in['Next'] and in['Back'] is unset and refreshed at each step.
    		unset($this->idsadmin->in['Next']);
    		unset($this->idsadmin->in['Back']);
    		$this->AddNewTask($this->idsadmin->in['PageNo']);
    		break;
    	
    	case 0; //User selects task type on this page
    		$this->idsadmin->in['PageNo']=(isset($this->idsadmin->in['Next']))?1:null;
    		//$this->idsadmin->in['NewTask_StartUp']=(isset($this->idsadmin->in['checkerbox']))?"on":"off";
    		unset($this->idsadmin->in['Next']);
    		unset($this->idsadmin->in['Back']);
    		$this->AddNewTask($this->idsadmin->in['PageNo']);
    		break;
    		
    	case 1; //User selects name, group and description of task on this page
    		if(isset($this->idsadmin->in['Next'])){
    			/*Error checking on the task name field is performed. The following code checks
    			whether the name field is empty and whether the task name already exist in the database.
    			An error message will be printed. Users cannot click "Next" and go to the next page.
    			Users are allowed to go back to the previous page.*/
    			if(strcasecmp($this->idsadmin->in['NewTask_Name'],"")==0){
	    			$this->idsadmin->error($this->idsadmin->lang('name_field_empty'));
	    			$this->idsadmin->in['PageNo']=1;
	    		}elseif($this->check_duplicate_tk_name($this->idsadmin->in['NewTask_Name'])!=0){
	    			$this->idsadmin->error($this->idsadmin->lang('name_field_duplicate'));
	    			$this->idsadmin->in['PageNo']=1;
	    		}else{
	    			$this->idsadmin->in['PageNo']=2;
	    		}
    		}else{
    			$this->idsadmin->in['PageNo']=0;
    		}
    		unset($this->idsadmin->in['Next']);
    		unset($this->idsadmin->in['Back']);
    		$this->AddNewTask($this->idsadmin->in['PageNo']);
    		break;
    	
    	case 2; //User selects schedule on this page
			/*The format of the form data is not compatible with the ph_task table's time fields.    	
			The following code changes the format of the form data.*/  	
    		
    		//STARTUP TASK/SENSORS have NULL value for start time and stop time
    		//Input formatting for start time and stop time is only performed when
    		//the user unchecked the STARTUP ONLY option.
    		if (strcasecmp($this->idsadmin->in['NewTask_Startup'],"on")!=0){
    			
    			$start_hour = $this->idsadmin->in['START_HOUR'];
    			$start_minute = $this->idsadmin->in['START_MINUTE'];
    			$start_second = $this->idsadmin->in['START_SECOND'];
    			$stop_hour = $this->idsadmin->in['STOP_HOUR'];
    			$stop_minute = $this->idsadmin->in['STOP_MINUTE'];
    			$stop_second = $this->idsadmin->in['STOP_SECOND'];
    			
    			$time_err=false;
    			if (! isset($this->idsadmin->in['STOPTIME_NEVER']))
    			{
    			    if ( ((integer)$stop_hour < (integer)$start_hour) ||
    			       (((integer)$stop_hour == (integer)$start_hour) && 
    			       ((integer)$stop_minute < (integer)$start_minute)) )
    			    {
    				     //Stop time cannot be earlier than the start time.
    				     $time_err=true;
    			    }
    			}
    			
    			$start_time = ($start_hour < 10 ? "0":"") .
                $start_hour . ":" .
                ($start_minute < 10 ? "0":"") .
                $start_minute . ":" .
                ($start_second < 10 ? "0":"") .
                $start_second;
                
                $this->idsadmin->in['NewTask_StartTime']=$start_time;
                
                if (isset($this->idsadmin->in['STOPTIME_NEVER']))
                {
                    $stop_time = "00:00:00";
                } else {
                    $stop_time = ($stop_hour < 10 ? "0":"") .
                        $stop_hour . ":" .
                        ($stop_minute < 10 ? "0":"") .
                        $stop_minute . ":" .
                        ($stop_second < 10 ? "0":"") .
                        $stop_second;
                }
                
                $this->idsadmin->in['NewTask_StopTime']=$stop_time;
    		}	
       			
       			$interval_day = $this->idsadmin->in['INTERVAL_DAYS'];
       			$interval_hour = $this->idsadmin->in['INTERVAL_HOUR'];
       			$interval_minute = $this->idsadmin->in['INTERVAL_MINUTE'];
       			$interval_second = $this->idsadmin->in['INTERVAL_SECOND'];
       			
       			$freq_err = false;
       			if(((integer)$interval_day==0) and
       			((integer)$interval_hour==0) and
       			((integer)$interval_minute==0) and
       			((integer)$interval_second==0)){
       				//Frequency cannot be 0 days 0 hours 0 minutes 0 seconds
       				$freq_err = true;
       			}
       			$frequency = $interval_day . " " .
                ($interval_hour < 10 ? "0":"") .
               	$interval_hour . ":" .
                ($interval_minute < 10 ? "0":"") .
                $interval_minute . ":" .
                ($interval_second < 10 ? "0":"") .
                $interval_second;
                
                $this->idsadmin->in['NewTask_Frequency']=$frequency;
                
       			if (isset($this->idsadmin->in['NewTask_Type'])&&(strcasecmp($this->idsadmin->in['NewTask_Type'], "SENSOR")==0)) 
       			{
           			//Tasks of type "TASK" does not have delete time.
           			//Input formatting for delete time is only performed when task type is "SENSOR" 
           			$delete = $this->idsadmin->in['DELETE_DAYS'] . " " .
	                ($this->idsadmin->in['DELETE_HOUR'] < 10 ? "0":"") .
	                $this->idsadmin->in['DELETE_HOUR'] . ":" .
	                ($this->idsadmin->in['DELETE_MINUTE'] < 10 ? "0":"") .
	                $this->idsadmin->in['DELETE_MINUTE'] . ":" .
	                ($this->idsadmin->in['DELETE_SECOND'] < 10 ? "0":"") .
	                $this->idsadmin->in['DELETE_SECOND'];
	                
	                $this->idsadmin->in['NewTask_DeleteTime']=$delete;
       			}
       			
       			if(isset($this->idsadmin->in['Next'])){ 
       				/*If the task type is "TASK", there is no need to request user input for tk_create and tk_result
       				tk_create and tk_result are just for sensors. Users will be redirected to page 5 (input tk_execute)*/
       				if($time_err){
       					$this->idsadmin->error($this->idsadmin->lang('time_error'));
       					$this->idsadmin->in['PageNo']=2;
       				}elseif($freq_err){
       					$this->idsadmin->error($this->idsadmin->lang('freq_error'));
       					$this->idsadmin->in['PageNo']=2;
       				}else{
       					$this->idsadmin->in['PageNo']=(strcasecmp($this->idsadmin->in['NewTask_Type'],"TASK")==0)?5:3;
       				}
       			}else{
       				$this->idsadmin->in['PageNo']=1;
       			}
       			unset($this->idsadmin->in['Next']);
    			unset($this->idsadmin->in['Back']);
       			$this->AddNewTask($this->idsadmin->in['PageNo']);
       			break;
       		
       	case 3; //User specify result table name on this page
       		if(isset($this->idsadmin->in['Next'])){
       			/**
       			 * Verify table name if DELIMIDENT != "Y"
       			 * If DELIMIDENT != "Y", the result table name always starts with "_" or alphabets. 
       			 * The result table name must only contain alphabets, numbers or "_".
       			 * The following code checks whether the user's result table name is valid. 
       			 * It also checks whether the result table name has already been used by other tables 
       			 * in the sysadmin database.  The result table is stored in the sysadmin database.
       			**/
       			if($this->idsadmin->phpsession->instance->get_delimident() != "Y" 
                    && ((preg_match("/^[a-zA-Z0-9_]+$/",$this->idsadmin->in['NewTask_ResultTable'])==0)
    				|| (preg_match("/^[a-zA-Z_]/",$this->idsadmin->in['NewTask_ResultTable'])==0)))
    			{
	    			$this->idsadmin->error($this->idsadmin->lang('invalid_table_name'));
	    			$this->idsadmin->in['PageNo']=3;
	    		} elseif($this->check_duplicate_table_name($this->idsadmin->in['NewTask_ResultTable'])!=0) {
	    			$this->idsadmin->error($this->idsadmin->lang('table_name_duplicate'));
	    			$this->idsadmin->in['PageNo']=3;
	    		} elseif(isset($this->idsadmin->in['NewTask_Create'])) {
	    			/*The user selected a result table name, clicked on "Next". The next step is to write the
	    			SQL statement to create the result table. The user finished the SQL create statement but decided
	    			to go back and change the result table name.
	    			In this case we would want to replace the old result table name with the new result table name in
	    			the SQL create statement. The following line of code performs this operation.*/
	    			$this->idsadmin->in['NewTask_Create']=str_replace($this->idsadmin->in['NewTask_ResultTable_Old'],$this->idsadmin->in['NewTask_ResultTable'],$this->idsadmin->in['NewTask_Create']);
	    			$this->idsadmin->in['PageNo']=4;
	    		} else {
	    			$this->idsadmin->in['PageNo']=4;
	    		}
       		} else {
       			$this->idsadmin->in['PageNo']=2;
       		}
       		unset($this->idsadmin->in['Next']);
    		unset($this->idsadmin->in['Back']);
       		$this->AddNewTask($this->idsadmin->in['PageNo']);
    		break;
    	
    	case 4; //User specify result table create statement on this page
	  		if(isset($this->idsadmin->in['Next'])){
    			if(strcasecmp($this->idsadmin->in['NewTask_Create'],"")==0){
	    			$this->idsadmin->error("{$this->idsadmin->lang('create_stmt_empty')}");
	    			$this->idsadmin->in['PageNo']=4;
    			}else{
    				$this->idsadmin->in['PageNo']=5;
    			}
    		}else{
    			$this->idsadmin->in['PageNo']=3;
    		}
    		
    		unset($this->idsadmin->in['Next']);
    		unset($this->idsadmin->in['Back']);
    		$this->AddNewTask($this->idsadmin->in['PageNo']);
    		break;
    		
    	case 5; //User specify execute statement on this page
    		if(isset($this->idsadmin->in['Next'])){
    			if(strcasecmp($this->idsadmin->in['NewTask_Execute'],"")==0){
	    			$this->idsadmin->error("{$this->idsadmin->lang('execute_stmt_empty')}");
	    			$this->idsadmin->in['PageNo']=5;
    			}else{
    				$this->idsadmin->in['PageNo']=6;
    			}
    		}else{
    			$this->idsadmin->in['PageNo']=(strcasecmp($this->idsadmin->in['NewTask_Type'],"TASK")==0)?2:4;
    		}
    		
    		unset($this->idsadmin->in['Next']);
    		unset($this->idsadmin->in['Back']);
    		$this->AddNewTask($this->idsadmin->in['PageNo']);
    		break;
    		
    	case 6; //User verifies setup on this page
    		$this->idsadmin->in['PageNo']=5;
    		if(isset($this->idsadmin->in['Back'])){
    			$this->AddNewTask($this->idsadmin->in['PageNo']);
    		}else{
    			$this->SaveNewTask();
    		}
    		break;
    		
    }
  }// end function AddNewTaskPageSelect()

    function SaveNewTask()
    {
       $dbadmin= $this->idsadmin->get_database("sysadmin");
       
       $str_name = $this->idsadmin->in['NewTask_Name'];
       $str_startup = $this->idsadmin->in['NewTask_Startup'];
       if(strcasecmp($str_startup,"on")==0){
       		$str_type = "STARTUP ".$this->idsadmin->in['NewTask_Type'];
       }else{
       		$str_type = $this->idsadmin->in['NewTask_Type'];
       }
       $str_group = $this->idsadmin->in['NewTask_Group'];
       $str_desc = $this->idsadmin->in['NewTask_Desc'];
       $str_start = (strcasecmp($str_startup,"on")==0)?null:$this->idsadmin->in['NewTask_StartTime'];
       if (isset($this->idsadmin->in['STOPTIME_NEVER']) || strcasecmp($str_startup,"on")==0)
       {
           $str_stop = null;
       } else {
           $str_stop = $this->idsadmin->in['NewTask_StopTime'];
       }
       $str_freq = $this->idsadmin->in['NewTask_Frequency'];
       $str_execute = $this->idsadmin->in['NewTask_Execute'];
       if((strcasecmp($str_type,"SENSOR")==0)||(strcasecmp($str_type,"STARTUP SENSOR")==0)){
			$str_result_table = $this->idsadmin->in['NewTask_ResultTable'];
			$str_create = $this->idsadmin->in['NewTask_Create'];
			$str_delete = $this->idsadmin->in['NewTask_DeleteTime'];	
       }
       
       
       $insert = "INSERT INTO ph_task" .
       			 "(" . 
       			 "tk_name," .
       			 "tk_description," .
       			 "tk_type,";
       if((strcasecmp($str_type,"SENSOR")==0)||(strcasecmp($str_type,"STARTUP SENSOR")==0)){
       //Only sensors, not tasks, have values for tk_result_table, tk_create and tk_delete
       $insert .=   "tk_result_table, " .
       				"tk_create, " .
       				"tk_delete,";
       }
       $insert .="tk_execute," .
       			 "tk_start_time," .
       			 "tk_stop_time," .
       			 "tk_frequency," .
       			 "tk_group" .
       			 ") VALUES" . 
       			 "(" . 
       			 "'$str_name', " .
       			 " :str_desc, " . 
       			 "'$str_type', ";
       if((strcasecmp($str_type,"SENSOR")==0)||(strcasecmp($str_type,"STARTUP SENSOR")==0)){
       $insert .="'$str_result_table', " .
       			" :str_create, " .
       			" '$str_delete', ";
       }
       
       $insert .=" :str_execute,";
       
       $insert .=
       			"'$str_start'," . 
       			"'$str_stop'," . 
       			"'$str_freq'," .
       			"'$str_group'" .
       			");";

       $stmt = $dbadmin->prepare($insert);
       $stmt->bindParam(':str_desc', $str_desc);
       $stmt->bindParam(':str_execute', $str_execute);
       if((strcasecmp($str_type,"SENSOR")==0)||(strcasecmp($str_type,"STARTUP SENSOR")==0))
       {
       	   $stmt->bindParam(':str_create', $str_create);
       }
       $stmt->execute();
       $error = $dbadmin->errorInfo();
       
       /*When the scheduled task's tk_execute statement is performed, it's return code 
       is stored in the ph_run table. The following query will obtain the return code
       from the ph_run table and report to the user if there are any problems executing
       the scheduled task's tk_execute statement.*/
		
		$check = $this->check_NewTask_Execution($str_name);
		
		$taskid = $check['taskid'];
		$errorcode = $check['errorcode'];
		
		$html="<table border='0' align='center' cellpadding='2' cellspacing='5'>";

		$html.=((strcasecmp($str_type,"SENSOR")==0)||(strcasecmp($str_type,"STARTUP SENSOR")==0))?"<tr><td class=\"tblheader\" align='center'>{$this->idsadmin->lang('new_sensor_setup')}</td></tr>":"<tr><td class=\"tblheader\" align='center'>{$this->idsadmin->lang('new_task_setup')}</td></tr>";
		if ($error[1] != 0) {
           	$html.="<tr></tr>";
           	$html.="<tr><td align='center'><font size=\"2\" color=\"red\">{$this->idsadmin->lang('setup_fail')}</font></td></tr>";
           	$html.="<tr></tr>";
           	$html.=((strcasecmp($str_type,"SENSOR")==0)||(strcasecmp($str_type,"STARTUP SENSOR")==0))?"<tr><td>{$this->idsadmin->lang('sensor_check_failed')}</td></tr>":"<tr><td>{$this->idsadmin->lang('task_check_failed')}</td></tr>";
           	$html.="<tr><td>{$this->idsadmin->lang('save_failed')} {$this->idsadmin->lang('ErrorF')} {$error[1]} {$error[2]}</td></tr>";
       	}elseif($errorcode!=0){
       		$html.="<tr></tr>";
           	$html.="<tr><td align='center'><font size=\"2\" color=\"dark orange\">{$this->idsadmin->lang('setup_success_with_problems')}</font></td></tr>";
           	$html.="<tr></tr>";
          	$html.=((strcasecmp($str_type,"SENSOR")==0)||(strcasecmp($str_type,"STARTUP SENSOR")==0))?"<tr><td>{$this->idsadmin->lang('sensor_check_bad_exec')}</td></tr>":"<tr><td>{$this->idsadmin->lang('task_check_bad_exec')}</td></tr>";
          	$html.="<tr><td>{$this->idsadmin->lang('debug_exec_stmt')}</td></tr>";
          	$html.="<tr><td>{$this->idsadmin->lang('return_code')}{$errorcode}</td></tr>";
          	$html.="<tr><td><a href=\"index.php?act=health&amp;do=taskdetails&amp;id='{$taskid}'\">{$this->idsadmin->lang('debug_command')}</a>";
       	}else{
       		$html.="<tr></tr>";
           	$html.="<tr><td align='center'><font size=\"2\" color=\"green\">{$this->idsadmin->lang('setup_success')}</font></td></tr>";
           	$html.="<tr></tr>";
           	$html.=((strcasecmp($str_type,"SENSOR")==0)||(strcasecmp($str_type,"STARTUP SENSOR")==0))?"<tr><td>{$this->idsadmin->lang('sensor_check_ok')}</td></tr>":"<tr><td>{$this->idsadmin->lang('task_check_ok')}</td></tr>";
       	}
       	
        $html.="</table>";
        $this->idsadmin->html->add_to_output($html);
    }//end function SaveNewTask()

    function check_NewTask_Execution($tk_name)
    {
		$return = array();
		$dbadmin=$this->idsadmin->get_database("sysadmin");
    	$qry = "SELECT run_retcode,run_task_id FROM ph_run,ph_task ".
				"WHERE tk_id=run_task_id ".
				"AND tk_name='{$tk_name}' AND run_task_seq=1";
    			
    	$stmt = $dbadmin->query($qry);
    	$res = $stmt->fetch();
    	$return['taskid']=$res['RUN_TASK_ID'];
    	$return['errorcode']=$res['RUN_RETCODE'];
    	
    	return $return;
    }

    function AddNewTask($PageNo)
    {
	   	switch ($PageNo)
    	{
    		case 0;
    		$html=<<<END
    		<form method="post" action="index.php?act=health&amp;do=AddNewTask">
END;
        	if (!empty($this->idsadmin->in))
        	{
        		/*The following code re-submit all form data. In each step of the new task setup process
        		The " is replaced with &quot; so that the html code will work.
        		*/
        		foreach($this->idsadmin->in as $i=>$v)
        		{
        			$index=str_replace("\"","&quot;",$i);
        			$value=str_replace("\"","&quot;",$v);
        			if(strcasecmp($i,"NewTask_Startup")!=0){
        				//we do not resubmit the previous value for NewTask_Startup here. Because this value is set using 
        				//a checkbox. Checkboxes will not submit if it is unchecked.
        				//If we have previously selected the Startup option and we do not want to select Startup option 
        				//in later steps, we hit "Back" to this page and uncheck the checkbox. But the checkbox will not 
        				//submit. Previous values for NewTask_Startup will stay there if we resubmit previous values.  
        				$html.="<input type='hidden' name=\"" . $index . "\" value=\"" . $value . "\"/>";
        			}
        			//$html.="{$i}=>{$v}<br>";
        		}
        	}
			$html.=<<<END
				<table border="0" align="center" cellpadding="2" cellspacing="5">
				<tr>
					<td class="tblheader" colspan="2" align="center">{$this->idsadmin->lang('new_task_setup')}</td>
				</tr>
				<tr>
					<th colspan="2">{$this->idsadmin->lang('what_task_type')}</th>
				</tr>
END;
					if(isset($this->idsadmin->in['NewTask_Type'])&&(strcasecmp($this->idsadmin->in['NewTask_Type'],"TASK")==0))
					{
						$html.="<tr><td colspan='2'><input type='radio' class='radiobutton' name=\"NewTask_Type\" value=\"TASK\" checked='checked' >{$this->idsadmin->lang('task')}</td></tr>";
					}elseif(!isset($this->idsadmin->in['NewTask_Type'])){
						$html.="<tr><td colspan='2'><input type='radio' class='radiobutton' name=\"NewTask_Type\" value=\"TASK\" checked='checked' >{$this->idsadmin->lang('task')}</td></tr>";
					}else{
						$html.="<tr><td colspan='2'><input type='radio' class='radiobutton' name=\"NewTask_Type\" value=\"TASK\">{$this->idsadmin->lang('task')}</td></tr>";
					}
					
					if(isset($this->idsadmin->in['NewTask_Type'])&&(strcasecmp($this->idsadmin->in['NewTask_Type'],"SENSOR")==0))
					{
						$html.="<tr><td colspan='2'><input type='radio' class='radiobutton' name=\"NewTask_Type\" value=\"SENSOR\" checked='checked' >{$this->idsadmin->lang('sensor')}</td></tr>";
					}elseif(!isset($this->idsadmin->in['NewTask_Type'])){
						$html.="<tr><td colspan='2'><input type='radio' class='radiobutton' name=\"NewTask_Type\" value=\"SENSOR\">{$this->idsadmin->lang('sensor')}</td></tr>";
					}else{
						$html.="<tr><td colspan='2'><input type='radio' class='radiobutton' name=\"NewTask_Type\" value=\"SENSOR\">{$this->idsadmin->lang('sensor')}</td></tr>";
					}
					$html.="<tr></tr><tr></tr>";
					if(strcasecmp($this->idsadmin->in['NewTask_Startup'],"on")==0){
						$html.="<tr><td colspan='2'><input type='checkbox' class='checkbox' name=\"NewTask_Startup\" CHECKED>{$this->idsadmin->lang('startup_only')}</td></tr>";
					}else{
						$html.="<tr><td colspan='2'><input type='checkbox' class='checkbox' name=\"NewTask_Startup\">{$this->idsadmin->lang('startup_only')}</td></tr>";
					}
					
				$html.=<<<END
				<tr><td></td><td align="right">
				<input type="submit" class="button" name="Next" value="{$this->idsadmin->lang('next')}" />
				<input type="submit" class="button" name="Cancel" value="{$this->idsadmin->lang('cancel')}" />
				</td></tr>
				</table>
				</form>
END;
    			$this->idsadmin->html->add_to_output($html);
    			break;
    		
    			case 1;
    			$html=<<<END
    			<form method="post" action="index.php?act=health&amp;do=AddNewTask">
END;
				if (!empty($this->idsadmin->in))
        		{
        			foreach($this->idsadmin->in as $i=>$v)
        			{
        				/*The following code re-submit all form data. In each step of the new task setup process
        				The " is replaced with &quot; so that the html code will work.
        				*/
        				$index=str_replace("\"","&quot;",$i);
        				$value=str_replace("\"","&quot;",$v);
        				$html.="<input type='hidden' name=\"" . $index . "\" value=\"" . $value . "\"/>";
        				//$html.="{$i}=>{$v}<br>";
        			}
        		}
        		$html.="<table border='0' align=\"center\" cellpadding='2' cellspacing='5'>";
				
				if (isset($this->idsadmin->in['NewTask_Type'])&&(strcasecmp($this->idsadmin->in['NewTask_Type'],"SENSOR")==0)){
					$html.="<tr><td class=\"tblheader\" colspan=2 align=\"center\">{$this->idsadmin->lang('new_sensor_setup')}</td></tr>";
					$html.="<tr><th colspan='2'>{$this->idsadmin->lang('what_sensor_name_and_group')}</th></tr>";
					$html.="<tr><td>{$this->idsadmin->lang('sensor_name')}</td><td><input name='NewTask_Name' value=\"{$this->idsadmin->in['NewTask_Name']}\"></td></tr>";
					$html.="<tr><td>{$this->idsadmin->lang('sensor_group')}</td><td><select name='NewTask_Group' size='1'>";
				} elseif(isset($this->idsadmin->in['NewTask_Type'])&&(strcasecmp($this->idsadmin->in['NewTask_Type'],"TASK")==0)) {
					$html.="<tr><td class=\"tblheader\" colspan=2 align=\"center\">{$this->idsadmin->lang('new_task_setup')}</td></tr>";
					$html.="<tr><th colspan='2'>{$this->idsadmin->lang('what_task_name_and_group')}</th></tr>";
					$html.="<tr><td>{$this->idsadmin->lang('task_name')}</td><td><input name='NewTask_Name' value=\"{$this->idsadmin->in['NewTask_Name']}\"></td></tr>";
					$html.="<tr><td>{$this->idsadmin->lang('task_group')}</td><td><select name='NewTask_Group' size='1'>";
				} else {
					$this->idsadmin->fatal_error("{$this->idsadmin->lang('type_error')}");
				}
					$task_groups = $this->get_task_group();
					if(!empty($task_groups)) {
						foreach($task_groups as $index=>$value) {
						if (isset($this->idsadmin->in['NewTask_Group'])&&(strcasecmp($this->idsadmin->in['NewTask_Group'],$value)==0)) {
								$html.="<option selected='selected'>".$value."</option>";
							} else {
								$html.='<option>'.$value.'</option>';
							}
						}
					} else {
						$this->idsadmin->fatal_error("{$this->idsadmin->lang('ph_group_error')}");
					}
					
					
					$html.="</select></td></tr>";
					
					if (isset($this->idsadmin->in['NewTask_Type'])&&(strcasecmp($this->idsadmin->in['NewTask_Type'],"SENSOR")==0)){
						$html.="<tr><th colspan='2'>{$this->idsadmin->lang('sensor_desc')}</th></tr>";
					}else{
						$html.="<tr><th colspan='2'>{$this->idsadmin->lang('task_desc')}</th></tr>";
					}
					$html.=<<<END
					<tr>
						<td colspan=2><textarea name='NewTask_Desc' cols='70' rows='4'>{$this->idsadmin->in['NewTask_Desc']}</textarea></td>
					</tr>
					<tr><td></td><td align='right'>
						<input type='submit' class='button' name="Back" value="{$this->idsadmin->lang('back')}"/>
						<input type='submit' class='button' name="Next" value="{$this->idsadmin->lang('next')}" />
						<input type='submit' class='button' name="Cancel" value="{$this->idsadmin->lang('cancel')}" />
						</td>
					</tr>
				</table>
				</form>
END;
    			$this->idsadmin->html->add_to_output($html);
    			break;
    			
    			case 2;				
    			$html=<<<END
    			<form method="post" action="index.php?act=health&amp;do=AddNewTask">
END;
    			if (!empty($this->idsadmin->in))
        		{
        			foreach($this->idsadmin->in as $i=>$v)
        			{
        				/*The following code re-submit all form data. In each step of the new task setup process
        				The " is replaced with &quot; so that the html code will work.
        				*/
        				$index=str_replace("\"","&quot;",$i);
        				$value=str_replace("\"","&quot;",$v);
                        if(strcasecmp($i,"STOPTIME_NEVER")!=0){
                            // We do not resubmit the previous value for STOPTIME_NEVER here, 
                            // because this value is set using a checkbox. Checkboxes will not 
                            // submit if it is unchecked. 
                            $html.="<input type='hidden' name=\"" . $index . "\" value=\"" . $value . "\"/>";
                        }
        				//$html.="{$i}=>{$v}<br>";
        			}
        		}
    			$html.="<table border='0' align='center' cellpadding='2' cellspacing='5'>";
				
				if (isset($this->idsadmin->in['NewTask_Type'])&&(strcasecmp($this->idsadmin->in['NewTask_Type'],"SENSOR")==0)){
					$html.="<tr><td class=\"tblheader\" colspan='2' align='center'>{$this->idsadmin->lang('new_sensor_setup')}</td></tr>";
					$html.="<tr><th colspan='2' width='500'>{$this->idsadmin->lang('what_sensor_schedule')}</th></tr>";
				}elseif(isset($this->idsadmin->in['NewTask_Type'])&&(strcasecmp($this->idsadmin->in['NewTask_Type'],"TASK")==0)){
					$html.="<tr><td class=\"tblheader\" colspan='2' align='center'>{$this->idsadmin->lang('new_task_setup')}</td></tr>";
					$html.="<tr><th colspan='2' width='500'>{$this->idsadmin->lang('what_task_schedule')}</th></tr>";
				}else{
					$this->idsadmin->fatal_error("{$this->idsadmin->lang('type_error')}");
				}
					
				if(strcasecmp($this->idsadmin->in['NewTask_Startup'],"on")!=0){
					$html.=<<<END
		    		<tr><td>{$this->idsadmin->lang('start_time')}</td>
		    		<td>
END;
					if(isset($this->idsadmin->in['NewTask_StartTime'])){
						$html.=$this->create_time_select("START", $this->idsadmin->in['NewTask_StartTime']);
					}else{
						$html.=$this->create_time_select("START", "0:0:0");
					}
					$html.="</td></tr>";
					$html.="<tr><td>{$this->idsadmin->lang('stop_time')}</td><td>";
					if(isset($this->idsadmin->in['NewTask_StopTime'])){
					    $never_checked = "";
					    if (isset($this->idsadmin->in['STOPTIME_NEVER']))
					    {
					       $never_checked = "CHECKED";
					       unset($this->idsadmin->in['STOPTIME_NEVER']);
					    }
                                            $stop_disabled = ($never_checked == "")? "":"DISABLED";
					    $html.=$this->create_time_select("STOP", $this->idsadmin->in['NewTask_StopTime'], $stop_disabled);
						$html.="<input name='STOPTIME_NEVER' type=checkbox class=checkbox {$never_checked} " . 
                                                       "onchange='enable_disable(\"STOP_HOUR\");enable_disable(\"STOP_MINUTE\");enable_disable(\"STOP_SECOND\");'>
                                                {$this->idsadmin->lang("NEVER")}</input>";
					}else{
						$html.=$this->create_time_select("STOP", "0:0:0");
						$html.="<input name='STOPTIME_NEVER' type=checkbox class=checkbox " . 
                                                       "onchange='enable_disable(\"STOP_HOUR\");enable_disable(\"STOP_MINUTE\");enable_disable(\"STOP_SECOND\");'>
                                                       {$this->idsadmin->lang("NEVER")}</input>";
					}
					$html.="</td></tr>";
				}

				$html.="<tr><td>{$this->idsadmin->lang('frequency')}</td><td>";
				if(isset($this->idsadmin->in['NewTask_Frequency'])){
					$html.=$this->create_interval_select("INTERVAL", $this->idsadmin->in['NewTask_Frequency']);
				}else{
					$html.=$this->create_interval_select("INTERVAL", "0 0 0");
				}
				$html.="</td></tr>";
				//Show Data Delete Time only when the task type is SENSOR
				if (isset($this->idsadmin->in['NewTask_Type'])&&(strcasecmp($this->idsadmin->in['NewTask_Type'], "SENSOR")==0))
				{
					$html.="<tr><td>{$this->idsadmin->lang('delete_time')}</td><td>";
					if(isset($this->idsadmin->in['NewTask_DeleteTime'])){
						$html.=$this->create_interval_select("DELETE", $this->idsadmin->in['NewTask_DeleteTime']);
					}else{
						$html.=$this->create_interval_select("DELETE", "0 0 0");
					}
					$html.="</td></tr>";
				}				
				$html.=<<<END
    			<tr><td></td><td align='right'>
    				<input type='submit' class='button' name="Back" value="{$this->idsadmin->lang('back')}"/>
    				<input type='submit' class='button' name="Next" value="{$this->idsadmin->lang('next')}"/>
    				<input type='submit' class='button' name="Cancel" value="{$this->idsadmin->lang('cancel')}"/>
    				</td>
    			</tr>
    			</table>
    			</form>
END;
				$this->idsadmin->html->add_to_output($html);
				break;
				
				case 3;
				$html=<<<END
				<form method="post" action="index.php?act=health&amp;do=AddNewTask">
END;
    			if (!empty($this->idsadmin->in))
        		{
        			foreach($this->idsadmin->in as $i=>$v)
        			{
        				/*The following code re-submit all form data. In each step of the new task setup process
        				The " is replaced with &quot; so that the html code will work.
        				*/
        				$index=str_replace("\"","&quot;",$i);
        				$value=str_replace("\"","&quot;",$v);
        				$html.="<input type='hidden' name=\"" . $index . "\" value=\"" . $value . "\"/>";
        				//$html.="{$i}=>{$v}<br>";
        			}
        		}
    			$html.="<table border='0' align='center' cellpadding='2' cellspacing='5'>";
    			
				$html.="<tr><td class=\"tblheader\" colspan=2 align=center>{$this->idsadmin->lang('new_sensor_setup')}</td></tr>";
				$html.="<tr><th colspan='2' width='500'>{$this->idsadmin->lang('what_result_table')}</th></tr>";
 			
    			$html.=<<<END
    			<tr><td>{$this->idsadmin->lang('result_table_name')}</td><td><input name='NewTask_ResultTable' value="{$this->idsadmin->in['NewTask_ResultTable']}"></td>
    			</tr>
    			<tr><td></td><td align='right'>
    				<input type='submit' class='button' name="Back" value="{$this->idsadmin->lang('back')}"/>
    				<input type='submit' class='button' name="Next" value="{$this->idsadmin->lang('next')}"/>
    				<input type='submit' class='button' name="Cancel" value="{$this->idsadmin->lang('cancel')}"/>
    				</td>
    			</tr>
    			</table>
    			</form>
END;
				$this->idsadmin->html->add_to_output($html);
				break;
				
				case 4; 
				$html=<<<END
				<form method="post" action="index.php?act=health&amp;do=AddNewTask">
END;
    			if (!empty($this->idsadmin->in))
        		{
        			foreach($this->idsadmin->in as $i=>$v)
        			{
        				/*The following code re-submit all form data. In each step of the new task setup process
        				The " is replaced with &quot; so that the html code will work.
        				*/
        				$index=str_replace("\"","&quot;",$i);
        				$value=str_replace("\"","&quot;",$v);
        				$html.="<input type='hidden' name=\"" . $index . "\" value=\"" . $value . "\"/>";
        				//$html.="{$i}=>{$v}<br>";
        			}
        		}
    			$html.=<<<END
    			<table border='0' align='center' cellpadding='2' cellspacing='5'>
    			<tr><td class="tblheader" colspan="2" align="center">{$this->idsadmin->lang('new_sensor_setup')}</td>
    			</tr>
    			<tr><th colspan="2" width="500">{$this->idsadmin->lang('what_tk_create')}</th>
    			</tr>
    			<tr><td colspan="2">
END;
				if(isset($this->idsadmin->in['NewTask_ResultTable'])){
    				$html.="<input type='hidden' name='NewTask_ResultTable_Old' value=\"{$this->idsadmin->in['NewTask_ResultTable']}\"/>";
    			}
				
				if(isset($this->idsadmin->in['NewTask_Create']))
				{
					$html.="<textarea name=\"NewTask_Create\" cols='70' rows='4' wrap='soft'>{$this->idsadmin->in['NewTask_Create']}</textarea>";
				}else{
					$html.="<textarea name=\"NewTask_Create\" cols='70' rows='4' wrap='soft'>create table {$this->idsadmin->in['NewTask_ResultTable']}(ID integer)</textarea>";
				}
    			$html.=<<<END
    			</td>
    			</tr>
    			<tr><td></td><td align='right'>
    				<input type='submit' class='button' name="Back" value="{$this->idsadmin->lang('back')}"/>
    				<input type='submit' class='button' name="Next" value="{$this->idsadmin->lang('next')}"/>
    				<input type='submit' class='button' name="Cancel" value="{$this->idsadmin->lang('cancel')}"/>
    				</td>
    			</tr>
    			</table>
    			</form>
END;
				$this->idsadmin->html->add_to_output($html);
				break;
				
				case 5;
				$html=<<<END
				<form method="post" action="index.php?act=health&amp;do=AddNewTask">
END;
    			if (!empty($this->idsadmin->in))
        		{
        			foreach($this->idsadmin->in as $i=>$v)
        			{
        				/*The following code re-submit all form data. In each step of the new task setup process
        				The " is replaced with &quot; so that the html code will work.
        				*/
        				$index=str_replace("\"","&quot;",$i);
        				$value=str_replace("\"","&quot;",$v);
        				$html.="<input type='hidden' name=\"" . $index . "\" value=\"" . $value . "\"/>";
        				//$html.="{$i}=>{$v}<br>";
        			}
        		}
    			$html.=<<<END
    			<table border='0' align='center' cellpadding='2' cellspacing='5'>
END;
				if (isset($this->idsadmin->in['NewTask_Type'])&&(strcasecmp($this->idsadmin->in['NewTask_Type'],"SENSOR")==0)){
					$html.="<tr><td class=\"tblheader\" colspan='2' align='center'>{$this->idsadmin->lang('new_sensor_setup')}</td></tr>";
					$html.="<tr><th colspan='2' width='500'>{$this->idsadmin->lang('what_sensor_tk_execute')}</th></tr>";
				}else{
					$html.="<tr><td class=\"tblheader\" colspan='2' align='center'>{$this->idsadmin->lang('new_task_setup')}</td></tr>";
					$html.="<tr><th colspan='2' width='500'>{$this->idsadmin->lang('what_task_tk_execute')}</th></tr>";
				}
				
				$html.=<<<END
    			<tr><td colspan="2">
    			<textarea name="NewTask_Execute" cols='70' rows='4' wrap='soft'>{$this->idsadmin->in['NewTask_Execute']}</textarea> 			
       			</td>
    			</tr>

    			<tr><td></td><td align='right'>
    				<input type='submit' class='button' name="Back" value="{$this->idsadmin->lang('back')}"/>
    				<input type='submit' class='button' name="Next" value="{$this->idsadmin->lang('next')}"/>
    				<input type='submit' class='button' name="Cancel" value="{$this->idsadmin->lang('cancel')}"/>
    				</td>
    			</tr>
    			</table>
    			</form>
END;
				$this->idsadmin->html->add_to_output($html);
				break;
				
				case 6;
				$html=<<<END
				<form method="post" action="index.php?act=health&amp;do=AddNewTask">
END;
    			if (!empty($this->idsadmin->in))
        		{
        			foreach($this->idsadmin->in as $i=>$v)
        			{
        				/*The following code re-submit all form data. In each step of the new task setup process
        				The " is replaced with &quot; so that the html code will work.
        				*/
        				$index=str_replace("\"","&quot;",$i);
        				$value=str_replace("\"","&quot;",$v);
        				$html.="<input type='hidden' name=\"" . $index . "\" value=\"" . $value . "\"/>";
        				//$html.="{$i}=>{$v}<br>";
        			}
        		}
    			$html.="<table border='0' align='center' cellpadding='2' cellspacing='5'>";
    			if (isset($this->idsadmin->in['NewTask_Type'])&&(strcasecmp($this->idsadmin->in['NewTask_Type'],"SENSOR")==0)){
					$html.="<tr><td class=\"tblheader\" colspan=2 align=center>{$this->idsadmin->lang('new_sensor_setup')}</td></tr>";
					$html.="<tr><th colspan='2' width='500'>{$this->idsadmin->lang('verify_specs_sensor')}</th></tr>";
				}else{
					$html.="<tr><td class=\"tblheader\" colspan='2' align='center'>{$this->idsadmin->lang('new_task_setup')}</td></tr>";
					$html.="<tr><th colspan='2' width='500'>{$this->idsadmin->lang('verify_specs_task')}</th></tr>";
				}
    			$html.=<<<END
    			<tr><td>{$this->idsadmin->lang('name')}</td><td>{$this->idsadmin->in['NewTask_Name']}</td>
    			</tr>
    			<tr><td>{$this->idsadmin->lang('type')}</td><td>{$this->idsadmin->in['NewTask_Type']}</td>
    			</tr>
    			<tr><td>{$this->idsadmin->lang('group')}</td><td>{$this->idsadmin->in['NewTask_Group']}</td>
    			</tr>
    			<tr><td>{$this->idsadmin->lang('desc')}</td><td>{$this->idsadmin->in['NewTask_Desc']}</td>
    			</tr>
END;
				if(strcasecmp($this->idsadmin->in['NewTask_Startup'],"on")!=0){
				    if (isset($this->idsadmin->in['STOPTIME_NEVER']))
				    { 
				        $stop_time_str = $this->idsadmin->lang("NEVER");
				    } else {
				        $stop_time_str = $this->idsadmin->in['NewTask_StopTime'];
				    }
					$html.=<<<END
							
			    		<tr><td>{$this->idsadmin->lang('start_time')}</td><td>{$this->idsadmin->in['NewTask_StartTime']}</td>
			    		</tr>
			    		<tr><td>{$this->idsadmin->lang('stop_time')}</td><td>{$stop_time_str}</td>
			    		</tr>
END;
				}
				
    			$html.=<<<END
    			<tr><td>{$this->idsadmin->lang('frequency')}</td><td>{$this->idsadmin->in['NewTask_Frequency']}</td>
    			</tr>
END;
    			if (isset($this->idsadmin->in['NewTask_Type'])&&(strcasecmp($this->idsadmin->in['NewTask_Type'], "SENSOR")==0)) 
       			{
           			$html.="<tr><td>{$this->idsadmin->lang('delete_time')}</td><td>".$this->idsadmin->in['NewTask_DeleteTime']."</td></tr>";
           			$html.="<tr><td>{$this->idsadmin->lang('result_table_name')}</td><td>".$this->idsadmin->in['NewTask_ResultTable']."</td></tr>";
           			$html.="<tr><td>{$this->idsadmin->lang('result_table_create')}</td><td>".$this->idsadmin->in['NewTask_Create']."</td></tr>";
       			}
       			
       			$html.="<tr><td>{$this->idsadmin->lang('execute_command')}</td><td>{$this->idsadmin->in['NewTask_Execute']}</td></tr>";
       			
       			$html.=<<<END
    			</tr>
    			<tr><td></td><td align='right'>
    				<input type='submit' class='button' name="Back" value="{$this->idsadmin->lang('back')}"/>
    				<input type='submit' class='button' name="Finish" value="{$this->idsadmin->lang('finish')}"/>
    				<input type='submit' class='button' name="Cancel" value="{$this->idsadmin->lang('cancel')}"/>
    				</td>
    			</tr>
    			</table>
    			</form>
END;
				$this->idsadmin->html->add_to_output($html);
				break;
    	}		
    } // end AddNewTask()

    function get_task_group(){
    	$return = array();
    	$dbadmin= $this->idsadmin->get_database("sysadmin");
    	
    	$qry =  "SELECT group_name ".
    			"FROM ph_group order by group_name ";
    			
    	$stmt = $dbadmin->query($qry);
    	
    	
    	$i=0;
    	while ($res = $stmt->fetch())
    	{
    		$return[$i]=$res['GROUP_NAME'];
    		$i++;
    	}
    	    	    	
    	return $return;	
    } // end get_task_group()
    
    function check_duplicate_tk_name($name){
    	$dbadmin= $this->idsadmin->get_database("sysadmin");
    	
    	$qry =  "SELECT count(*) ".
    			"FROM ph_task " .
    			"WHERE tk_name='$name'";
    			
    	$stmt = $dbadmin->query($qry);
    	if(($res=$stmt->fetch(PDO::FETCH_ASSOC))==false){
    		$this->idsadmin->error("{$this->idsadmin->lang('task_name_check_error')}");
    	}else{
    		return trim($res['']);
    	}
    } // end check_duplicate_tk_name($name)
    
    function check_duplicate_table_name($name){
    	$dbadmin=$this->idsadmin->get_database("sysadmin");
    	$qry =  "SELECT count(*) ".
    			"FROM systables ".
    			"WHERE tabname='$name'";
    			
    	$stmt = $dbadmin->query($qry);
    	if(($res=$stmt->fetch(PDO::FETCH_ASSOC))==false){
    		$this->idsadmin->error("{$this->idsadmin->lang('table_name_check_error')}");
    	}else{
    		return trim($res['']);
    	}
    }
    
    } // end class
?>
