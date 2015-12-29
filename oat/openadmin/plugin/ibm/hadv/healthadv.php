<?php
/*********************************************************************
 *  Licensed Materials - Property of IBM
 *
 *  "Restricted Materials of IBM"
 *
 *  OpenAdmin Tool for Informix
 *  Copyright IBM Corporation 2011, 2012.  All rights reserved.
 **********************************************************************/


class healthadv{

    /**
     * Each module should have an 'idsadmin' member , this gives access to the OAT API.
     */
    var $idsadmin;

    /**
     * Every class needs to have a constructor method that takes &$idsadmin as its argument
     * We are also going to load our 'language' file too.
     * @param Class $idsadmin
     */
    function __construct(&$idsadmin)
    {
        $this->idsadmin = $idsadmin;
        /**
         * load our language file.
         */
        $this->idsadmin->load_lang("hadv");
        $this->idsadmin->load_lang("dsc");
        $this->idsadmin->load_lang("msg");
        $this->idsadmin->load_lang("act");
        $this->idsadmin->load_lang("exc");
        
        /**
         * If it exists, load the custom language file for the user's custom alarms.
         **/
        $this->idsadmin->load_lang("custom");

    } // end of function __construct

    /**
     * Every class needs a 'run' method , this is the 'entry' point of your module.
     *
     */
    function run()
    {

	    /**
	     * find out what the user wanted to do ..
	     */
	    $do = $this->idsadmin->in['do'];
	    $action = $this->idsadmin->in['action'];

            if ($action != "LoadDeleteProfile" )
            {
               $this->showRunHealthMonitor();
               $this->idsadmin->html->add_to_output($this->setuptabs($do));
            } 



	    /*
	     * map our action to a function.
	     */

            $this->idsadmin->setCurrMenuItem("hadv_menu");
	    switch ($do)
	    {
              case 'profile': 
                if(strcmp($action,'AddProfile') == 0)
                {
                  $this->AddProfile();
                }else if(strcmp($action,'LoadDeleteProfile') == 0)
                {
                  $this->LoadDeleteProfile();
                }else
                {
                  $this->showProfileTab();
                }
                break;

              case 'notification': 
                if(strcmp($action,'SaveNotification') == 0)
                {
                  $this->SaveNotification();
                }else if(strcmp($action,'SaveEmail') == 0)
                {
                  $this->SaveEmail();
                }else
                {
                  $this->showNotificationTab();
                }
                break;

              case 'schedule': 
                $this->showModifySchedule();
                break;

              case 'alarms':
                if(strcmp($action,'updateAlarms') == 0)
                {
                  $this->updateAlarms();
                }else
                {
                  $this->showAlarmsTab();
                }
                break;


              case 'thresholds':
                $this->modifyThreshold();
                break;

              case 'updateThreshold':
                $this->updateThreshold();
                break;


	      default:
                $this->showProfileTab();
	    }
		
    } // end of function run



    function showRunHealthMonitor()
    {

        /**
         * Check Installation
        */
        require_once("plugin/ibm/hadv/lib/hadvlibs.php");
        $mylib = new hadvlibs($this->idsadmin);
        $mylib->check_installed();


        /**
         * we first need a 'connection' to the database.
         */
        $db_sysadmin = $this->idsadmin->get_database("sysadmin");

        /**
         * Get Current Profile Information 
         */

        $sql_stmt= " SELECT trim(name) prof_name from " .
                  "hadv_profiles where status='A' " ;
        $prof_stmt = $db_sysadmin->query($sql_stmt);
        $prof_res = $prof_stmt->fetch();

        $prof_name = $prof_res['PROF_NAME'];


        $html = <<<EOF
<TABLE cellspacing="3" cellpadding="0" width="100%">
  <tr>
    <td width="15%"></td>
    <td width="40%"></td>
    <td width="45%"></td>
  </tr>
  <tr>
    <form method="post" target="_blank" action="index.php" name="reporter">
    <td colspan=3>
      <input type="hidden" name="act" value="ibm/hadv/runhadv"/>
      <input type="hidden" name="do" value=""/>
      <input type="hidden" name="runReports" value="no menu"/>
       <input type="submit" class="button" name="runReports" value="{$this->idsadmin->lang('prod_name_run')}"/>

    </td>
    </form>
  </tr>
  <tr>
    <td class="tblheader" colspan=3>&nbsp;&nbsp;
        {$this->idsadmin->lang('prod_name')} - {$this->idsadmin->lang('CurrentProfile')}: {$prof_name}
</td>
  </tr>

</table>
EOF;

        $this->idsadmin->html->add_to_output($html); 


    }  // end of showRunHealthMonitor()



    function showAlarmSetup()
    {


        $html = <<<EOF
<table cellspacing="3" cellpadding="0" width="100%">
  <tr>
    <td width="5%"></td>
    <td width="10%"></td>
    <td width="40%"></td>
    <td width="45%"></td>
  </tr>
  <tr><td>&nbsp;</td></tr>
  <tr>
    <th colspan="3" >&nbsp;&nbsp;
        {$this->idsadmin->lang('alarm_setup')}
    </th>
  </tr>
  <tr>
    <td></td>
    <td colspan="3">
       {$this->idsadmin->lang('alarm_msg')}
    </td>
  </tr>
  <tr>
    <td></td>
    <td></td>

    <form method="post" target="_new" action="index.php?act=ibm/hadv/healthadv">
    <td colspan="2">
       <input type="submit" class="button" value="{$this->idsadmin->lang('alarm_mod')}"/>
    </td>
    </form>
  </tr>

</table>
EOF;

        $this->idsadmin->html->add_to_output($html); 


    }  // end of showAlarmSetup()


    function showProfileManagement()
    {

        /**
         * we first need a 'connection' to the database.
         */
        $db_sysadmin = $this->idsadmin->get_database("sysadmin");

        /**
         * Get Current Profile Information 
         */

        $sql_stmt= " SELECT prof_id,trim(name) prof_name from " .
                  "hadv_profiles where status='A' " ;
        $prof_stmt = $db_sysadmin->query($sql_stmt);
        $prof_res = $prof_stmt->fetch();

        $prof_id = $prof_res['PROF_ID'];
        $prof_name = $prof_res['PROF_NAME'];


        /**
         * Make List Box
         */

         $sql_stmt= "select name prof_name from hadv_profiles where " .
               "prof_id <> ${prof_id} order by name";
         $list_qry = $db_sysadmin->query($sql_stmt);
         $list_box = "<select name='list_box' size=6 style='width: 200px'> ";
         while($res = $list_qry->fetch())
         {
            $list_prof_name = $res['PROF_NAME'];
            $list_box .= "<option>${list_prof_name}</option>";
         }
         $list_box .= "</select>";
         
         $disabled = ($this->idsadmin->isreadonly()? "disabled":"");



        $html = <<<EOF
<table cellspacing="3" cellpadding="0" width="100%">
  <tr>
    <td width="5%"></td>
    <td width="10%"></td>
    <td width="20%"></td>
    <td width="60%"></td>
  </tr>
  <tr> 
    <td>&nbsp;</td> 
  </tr>
  <tr>
    <td colspan="4" >&nbsp;&nbsp; 
       {$this->idsadmin->lang('Currentprofile')}:  
    &nbsp;&nbsp;{$prof_name}</td>
  </tr>
  <tr> 
    <td>&nbsp;</td> 
  </tr>
  <tr>
    <th colspan="4" >&nbsp;&nbsp;
        {$this->idsadmin->lang('profmanagement')}
    </th>
  </tr> 
  <tr>
    <td></td>
    <td colspan="3">
       {$this->idsadmin->lang('profmsg')}
    </td>
  </tr>

  <tr>
    <form method="post" action="index.php?act=ibm/hadv/healthadv&amp;do=profile&amp;action=AddProfile">
    <td></td>
    <td></td>
    <td valign="top" align="right"> 
         <input type="text" name="prof_name" size="30"/> </td>
    <td>
       <input type="submit" class="{$disabled}button" value="{$this->idsadmin->lang('add')}" {$disabled} style="width: 70px"/>
    </td>
    </form>
  </tr>

  <tr>
    <td></td>
  </tr>

  <tr>
    <td></td>
    <td></td>
    <td align="right">
      <form method="post" action="index.php?act=ibm/hadv/healthadv&amp;do=profile&amp;action=LoadDeleteProfile">
      ${list_box} 
    </td> 
    <td valign="top">
      <input type="submit" name="btn" class="button" value="{$this->idsadmin->lang('load')}" style="width: 70px" /><br>
      <input type="submit" name="btn" class="{$disabled}button" value="{$this->idsadmin->lang('delete')}" {$disabled} style="width: 70px"/>
    </td>
    </form>
  </tr>

</table>



EOF;

        $this->idsadmin->html->add_to_output($html); 


    }  // end of showProfileManagement()

    function showScheduleInfo()
    {

        /**
         * we first need a 'connection' to the database.
         */
        $db_sysadmin = $this->idsadmin->get_database("sysadmin");

        /**
         * Get Current Profile Information 
         */

        $sql_stmt= " SELECT prof_id,trim(name) prof_name from " .
                  "hadv_profiles where status='A' " ;
        $prof_stmt = $db_sysadmin->query($sql_stmt);
        $prof_res = $prof_stmt->fetch();

        $prof_id = $prof_res['PROF_ID'];
        $prof_name = $prof_res['PROF_NAME'];
        $tk_name = "HADV ".${prof_name}." Profile"; 

        /**
         * Get Current TK_ID from ph_task
         * Get email program from ph_threshold
         */

         $sql_stmt2= "select tk_id tk_id from ph_task where ".
               "tk_name='${tk_name}'";
         $stmt2 = $db_sysadmin->query($sql_stmt2);
         $res2 = $stmt2->fetch();
         $tk_id = $res2['TK_ID'];

        /**
         * Get Current TK_ID from ph_task
         * Get email program from ph_threshold
         */

        $sql_stmt3 = "select trim(from_email) from_email, trim(to_email) ".
            "to_email,prof_id,trim(send_when) send_when from hadv_sched_prof " .
             "where prof_id = ${prof_id} ";
        $stmt3 = $db_sysadmin->query($sql_stmt3);

        $res3 = $stmt3->fetch();

        $to          = $res3['TO_EMAIL'];
        $from        = $res3['FROM_EMAIL'];
        $send_when   = $res3['SEND_WHEN'];

        if(strcmp($send_when,'Always') == 0)
        {
           $sel_always="selected";
           $sel_red="";
           $sel_any="";
        }
        if(strcmp($send_when,'Red') == 0)
        {
           $sel_red="selected";
           $sel_always="";
           $sel_any="";
        }
        if(strcmp($send_when,'Any') == 0)
        {
           $sel_any="selected";
           $sel_always="";
           $sel_red="";
        }



        $html = <<<EOF
<table cellspacing="3" cellpadding="0" width="100%">
  <tr>
    <td width="5%"></td>
    <td width="10%"></td>
    <td width="40%"></td>
    <td width="45%"></td>
  </tr>
  <tr> 
    <td>&nbsp;</td> 
  </tr>
  <tr>
    <th colspan="3" >&nbsp;&nbsp;
       {$this->idsadmin->lang('notification')} 
    </th>
  </tr>
  <tr> 
    <td>&nbsp;</td> 
  </tr>
  <tr>
    <td></td>
    <td colspan="3" >
        {$this->idsadmin->lang('send')}  
     </td>
  </tr>

  <tr>
    <form method="post" action="index.php?act=ibm/hadv/healthadv&amp;do=notification&amp;action=SaveNotification">
    <input type="hidden" name="prof_id" value=${prof_id} />
    <td></td>
    <td >
      {$this->idsadmin->lang("to")}:</td>
    <td colspan="2" align="left"><input type="text"
            name="to" value="${to}" size="50"/> </td>
  </tr>

  <tr>
    <td></td>
    <td >
     {$this->idsadmin->lang("from")}:</td>
    <td colspan="2" align="left"><input type="text"
          name="from" value="${from}" size="50"/> </td>

  </tr>

  <tr>
    <td></td>
    <td >
     {$this->idsadmin->lang("when")}:</td>
    <td colspan="2" align="left">
       <select name="send_when">
       <option value="Always" ${sel_always}>{$this->idsadmin->lang('Always')}
              </option>
       <option value="Red" ${sel_red}>{$this->idsadmin->lang('Redalarm')}
              </option>
       <option value="Any" ${sel_any}>{$this->idsadmin->lang('Anyalarm')}
              </option>
       </select>
    </td>
  </tr>
  <tr>
    <td></td>
    <td></td>

    <td colspan="2">
       <input type="submit" class="button" value="{$this->idsadmin->lang('save')}" style="width: 70px"/>
    </td>
    </form>
  </tr>





</table>



EOF;

        $this->idsadmin->html->add_to_output($html); 


    }  // end of showScheduleInfo()

    function showEmail()
    {

        /**
         * we first need a 'connection' to the database.
         */
        $db_sysadmin = $this->idsadmin->get_database("sysadmin");

        /**
         * Get Email Proram from ph_threshold 
         */

         $email_stmt= "select value from ph_threshold where " .
              "name='Email Program'";
         $email_qry= $db_sysadmin->query($email_stmt);
         $email_res= $email_qry->fetch();
         $email_prog= $email_res['VALUE'];




        $html = <<<EOF
<hr>
<table cellspacing="3" cellpadding="0" width="100%">
  <tr>
    <td width="15%"></td>
    <td width="30%"></td>
    <td width="55%"></td>
  </tr>
  <tr> 
    <td>&nbsp;</td> 
  </tr>
  <tr>
    <th colspan="3" >&nbsp;&nbsp;
       {$this->idsadmin->lang('emailprog')}
    </th>
  </tr>
  <tr>
    <form method="post" action="index.php?act=ibm/hadv/healthadv&amp;do=notification&amp;action=SaveEmail">
    <td></td>
    <td colspan="2">
      <input type="text"
            name="email_prog" value="${email_prog}" size="60"/> 
    </td>

  </tr>
  <tr>
    <td></td>

    <td colspan="2">
       <input type="submit" class="button" value="{$this->idsadmin->lang('save')}" style="width: 70px"/>
    </td>
    </form>
  </tr>


</table>



EOF;

        $this->idsadmin->html->add_to_output($html); 


    }  // end of showEmail()


    function showProfileTab()
    {
   	 /**
         * Set the Page Title - this is the title that is shown in the browser.
         */
        $this->idsadmin->html->set_pagetitle(
             $this->idsadmin->lang("prod_name"));

        /**
         * we first need a 'connection' to the database.
         */
        $db_sysadmin = $this->idsadmin->get_database("sysadmin");

        /**
         * Check Installation
        */
        require_once("plugin/ibm/hadv/lib/hadvlibs.php");
        $mylib = new hadvlibs($this->idsadmin);
        $mylib->check_installed();


        $this->showProfileManagement();


    }  // End of showProfileTab()


    function showNotificationTab()
    {
    	if ($this->idsadmin->isreadonly())
    	{
    		$this->idsadmin->fatal_error($this->idsadmin->lang('noauth'));
    		return;
    	}
    	
    	/**
         * Set the Page Title - this is the title that is shown in the browser.
         */
        $this->idsadmin->html->set_pagetitle(
             $this->idsadmin->lang("prod_name"));

        /**
         * we first need a 'connection' to the database.
         */
        $db_sysadmin = $this->idsadmin->get_database("sysadmin");

        $this->showScheduleInfo();
        $this->showEmail();


    }  // End of showNotificationTab()





    function showModifySchedule()
    {
    	if ($this->idsadmin->isreadonly())
    	{
    		$this->idsadmin->fatal_error($this->idsadmin->lang('noauth'));
    		return;
    	}
        /**
         * we first need a 'connection' to the database.
         */
        $db_sysadmin = $this->idsadmin->get_database("sysadmin");

        /**
         * Get Current Profile Information
         */

        $sql_stmt= " SELECT prof_id,trim(name) prof_name from " .
                  "hadv_profiles where status='A' " ;
        $prof_stmt = $db_sysadmin->query($sql_stmt);
        $prof_res = $prof_stmt->fetch();

        $prof_id = $prof_res['PROF_ID'];
        $prof_name = $prof_res['PROF_NAME'];
        $tk_name = "HADV ".${prof_name}." Profile";

        /**
         * Get Current TK_ID from ph_task
         * Get email program from ph_threshold
         */

         $sql_stmt2= "select tk_id tk_id from ph_task where ".
               "tk_name='${tk_name}'";
         $stmt2 = $db_sysadmin->query($sql_stmt2);
         $res2 = $stmt2->fetch();
         $tk_id = $res2['TK_ID'];

        require_once 'modules/health.php';
        $h = new health($this->idsadmin);

        if ( isset ($this->idsadmin->in['saveTask'] )
        && ( $this->idsadmin->in['saveTask'] == "ok") )
        {
          $this->idsadmin->status($this->idsadmin->lang('taskdetailssaved'));
        }


        $this->idsadmin->in['do']="taskdetails";
        $this->idsadmin->in['caller']="healthadv";
        $this->idsadmin->in['id'] = $tk_id;

        $h->run();


    }  // End of showModifySchedule()


    function AddProfile()
    {
    	if ($this->idsadmin->isreadonly())
    	{
    		$this->showProfileTab();
    		return;
    	}

        /**
         * we first need a 'connection' to the database.
         */
        $db_sysadmin = $this->idsadmin->get_database("sysadmin");

        $prof_name = $this->idsadmin->in['prof_name'];

        $html.="prof_name = " . $prof_name;

        $prof_name = preg_replace("/'/", "''",$prof_name,-1);
 
        if( trim($prof_name) == "")
        {
           $this->idsadmin->status($this->idsadmin->lang('empty_prof_add'));
        }
        else {
      
        /**
         * Create New Profile and Get prof_id 
         */
        $sql_stmt="execute procedure hadv_create_profile('${prof_name}')";
        $db_sysadmin->exec($sql_stmt);

        $sql_stmt = "select prof_id from hadv_profiles where " .
              "name='${prof_name}' " ;
        $stmt = $db_sysadmin->query($sql_stmt);
        $res  = $stmt->fetch();
        $prof_id = $res['PROF_ID'];


        /**
         * Insert Rows, Update rows per message files 
        */
        require_once("plugin/ibm/hadv/lib/hadvlibs.php");
        $mylib = new hadvlibs($this->idsadmin);
        $mylib->load_ins_files();


        $sql_stmt="update hadv_gen_prof set prof_id = ${prof_id} " .
            "where prof_id=-1";
        $db_sysadmin->exec($sql_stmt);

        //$mylib->update_tempdir($prof_id);
        $mylib->update_profile_per_msg_files($prof_id);

        $sql_stmt="execute procedure hadv_update_profile_os_info(${prof_id})";
        $db_sysadmin->exec($sql_stmt);

        }

        $this->showProfileTab();


    }  // End of AddProfile()


    function SaveEmail()
    {
    	if ($this->idsadmin->isreadonly())
    	{
    		$this->idsadmin->fatal_error($this->idsadmin->lang('noauth'));
    		return;
    	}

        /**
         * we first need a 'connection' to the database.
         */
        $db_sysadmin = $this->idsadmin->get_database("sysadmin");

        $email_prog = $this->idsadmin->in['email_prog'];
        $email_prog = preg_replace("/'/", "''",$email_prog,-1);

        /**
         * Save Email Proram into ph_threshold 
         */

         $email_stmt= "update ph_threshold set value = '${email_prog}' where " .
              "name='Email Program'";

        $db_sysadmin->query($email_stmt);
      
        $err_r = $db_sysadmin->errorInfo();
        if ($err_r[1] !=0)
        {
           $this->idsadmin->error("{$this->idsadmin->lang('save_failed')}<br> {$this->idsadmin->lang('ErrorF')} {$err_r[1]} {$err_r[2]} ");
        } else 
        {
          $this->idsadmin->status($this->idsadmin->lang('saved_email'));
        }

     
        $this->idsadmin->html->add_to_output($html);
        $this->showNotificationTab();

    }  // End of SaveEmail()


    function SaveNotification()
    {
    	if ($this->idsadmin->isreadonly())
    	{
    		$this->idsadmin->fatal_error($this->idsadmin->lang('noauth'));
    		return;
    	}

        /**
         * we first need a 'connection' to the database.
         */
        $db_sysadmin = $this->idsadmin->get_database("sysadmin");

        $send_when = $this->idsadmin->in['send_when'];
        $to        = $this->idsadmin->in['to'];
        $from      = $this->idsadmin->in['from'];
        $prof_id   = $this->idsadmin->in['prof_id'];

        $to = preg_replace("/'/", "''",$to,-1);
        $from = preg_replace("/'/", "''",$from,-1);

        /**
         * Save Schedule Information to hadv_sched_prof 
         */


        $upd_stmt="update hadv_sched_prof set (from_email,to_email," .
               "send_when) = (trim('${from}'),trim('${to}'), ".
               "trim('${send_when}')) where  prof_id = ${prof_id} ;";
        $db_sysadmin->exec($upd_stmt);
        
        $err_r =$db_sysadmin->errorInfo();
        if ($err_r[1] !=0)
        {
           $this->idsadmin->error("{$this->idsadmin->lang('save_failed')}<br> {$this->idsadmin->lang('ErrorF')} {$err_r[1]} {$err_r[2]} ");
        } else 
        {
          $this->idsadmin->status($this->idsadmin->lang('saved_schedule'));
        }

     
        $this->idsadmin->html->add_to_output($html);
        $this->showNotificationTab();

    }  // End of SaveNotification()


    function LoadDeleteProfile()
    {

        /**
         * we first need a 'connection' to the database.
         */
        $db_sysadmin = $this->idsadmin->get_database("sysadmin");

        $prof_name = $this->idsadmin->in['list_box'];
        $btn = $this->idsadmin->in['btn'];

        $html.="prof_name = " . $prof_name;
        $prof_name = preg_replace("/'/", "''",$prof_name,-1);

        if( trim($prof_name) == "")
        {
           $this->idsadmin->status($this->idsadmin->lang('empty_prof_load'));
        }else if( trim($prof_name) == "Default" &&
                  strcmp($btn,$this->idsadmin->lang("delete")) == 0)
        {
           $this->idsadmin->status($this->idsadmin->lang('del_def_na'));
        }else{



        if(strcmp($btn,$this->idsadmin->lang("delete")) == 0 && !$this->idsadmin->isreadonly())
        {
           $sql_stmt="execute procedure hadv_delete_profile('${prof_name}')";
           $db_sysadmin->exec($sql_stmt);
        }
        if(strcmp($btn,$this->idsadmin->lang("load")) == 0)
        {
           $sql_stmt="execute procedure hadv_load_profile('${prof_name}')";
           $db_sysadmin->exec($sql_stmt);
        }


        }


        $this->idsadmin->in['do']="profile";
        $this->idsadmin->in['action']="";
        $this->run(); 


    } // End LoadDeleteProfile() 



    /**
         *Creates the HTML for the tabs at the top of a page
         *
         * @param string $active                The current active tab
         * @return HTML to create the tabs
         */
        function setuptabs($active)
        {
             if (!isset($active) || $active == "" ||
                $active == "AddProfile" ||
                $active == "LoadDeleteProfile") 
             {
                     $active = "profile";
             }

             if (!isset($active) || $active == "thresholds" 
             || $active == "updateThreshold"   
             || $active == "updateAlarms"   )
             {
                     $active = "alarms";
             }

             if (!isset($active) || $active == "SaveNotification" 
             || $active == "SaveEmail"   )
             {
                     $active = "notification";
             }



             require_once ROOT_PATH."/lib/tabs.php";

             $t = new tabs($this->idsadmin);
             $t->addtab("index.php?act=ibm/hadv/healthadv",
                      $this->idsadmin->lang("profile"),
                      ($active == "profile") ? 1 : 0 );

             $t->addtab("index.php?act=ibm/hadv/healthadv&amp;do=alarms",
                      $this->idsadmin->lang("alarms"),
                      ($active == "alarms") ? 1 : 0 );
                      
             if (!$this->idsadmin->isreadonly())
             {
	             $t->addtab("index.php?act=ibm/hadv/healthadv&amp;do=schedule",
	                      $this->idsadmin->lang("schedule"),
	                      ($active == "schedule") ? 1 : 0 );
	
	             $t->addtab("index.php?act=ibm/hadv/healthadv&amp;do=notification",
	                      $this->idsadmin->lang("notification"),
	                      ($active == "notification") ? 1 : 0 );
             }

             #set the 'active' tab.
             $html  = ($t->tohtml());
             $html .= "<div class='borderwrapwhite'><br>";
             return $html;
        } #end setuptabs



    /**
     * showAlarmsTab function
     */
    function showAlarmsTab ()
    {
   	 /**
         * Set the Page Title - this is the title that is shown in the browser.
         */
        $this->idsadmin->html->set_pagetitle(
             $this->idsadmin->lang("prod_name"));

        /**
         * we first need a 'connection' to the database.
         */
        $db_sysadmin = $this->idsadmin->get_database("sysadmin");

        /**
        * Check Installation
        */
        require_once("plugin/ibm/hadv/lib/hadvlibs.php");
        $mylib = new hadvlibs($this->idsadmin);
        $mylib->check_installed();
        
        $prof_stmt = $db_sysadmin->query(" SELECT prof_id from hadv_profiles t1 where t1.status='A' " );
        $prof_res = $prof_stmt->fetch();
        $prof_id = $prof_res['PROF_ID']; 


        /**
         * Set where_clause for query 
         */
         $url_options="";
         $show = $this->idsadmin->in['show'];
         if (!isset($show)) {
            $show=$this->idsadmin->lang('all');
         }
         if(strcmp($show,$this->idsadmin->lang('all')) == 0) {
            $where_clause = " where prof_id=${prof_id}";
         } else {
            $where_clause = " where prof_id=${prof_id} and group = '${show}' ";
         }
         $url_options="&amp;show=${show}";

        /**
         * now we write our query
         */
        $qry = " SELECT id,prof_id,group,replace(desc,'Alarm Check','') as desc,ldesc,"
        	 . "(select count(*) from hadv_gen_prof t2 where t2.prof_id=${prof_id} and t2.id=t1.id "
        	 . "and (red_threshold != '' or yel_threshold != '')) modify,enable "
        	 . "from hadv_gen_prof t1 ${where_clause} order by id";


        /**
         *  we need another query which would be the 'count' 
         *   of the # of rows returned from the previous query.
         */
        $qrycnt = " SELECT count(*) as count FROM hadv_gen_prof ${where_clause}";


        /**
         *  Write Query to populate show drop down 
         */
       
         $show_qry= $db_sysadmin->query("select unique trim(group) group from hadv_gen_prof where prof_id=${prof_id} order by 1"); 
         
         $drop_down = "<select name='show' onchange='showalarms.submit()' ";
         $drop_down.= " style='width: 150px'>";
         $drop_down.= "<option value='All'";
         if(strcmp($show ,$this->idsadmin->lang('all'))==0) {
            $drop_down.= " selected ";
         }
         $drop_down.= ">{$this->idsadmin->lang('all')}</option>";
         while($res = $show_qry->fetch())
         {
            $group = $res['GROUP'];
            $drop_down.= "<option value='{$res['GROUP']}'";
            if(strcmp($show ,$group)==0) {
               $drop_down.= " selected ";
            }
            $drop_down.= ">{$this->idsadmin->lang($group)}</option>";
         }
         $drop_down .= "</select>";


        /**
         * We can use the 'gentab' api in OAT to create the output for us.
         *
         * 1. first we load the gentab class.
         */

        require_once("lib/gentab.php");

$HTML= <<<EOF
<form method="get" name="showalarms" action="index.php">
<input type="hidden" name="prof_id" value="${prof_id}" />
<input type="hidden" name="act" value="ibm/hadv/healthadv" />
<input type="hidden" name="do" value="alarms" />
<input type="hidden" name="action" value="updateAlarms" />
EOF;

$HTML.= <<<EOF
<center><b>{$this->idsadmin->lang('show')}: </b>${drop_down}</center>
EOF;

        $this->idsadmin->html->add_to_output($HTML); 


        /**
         * Get Current Profile Information
         */

         $stmt = $db_sysadmin->query("select trim(name) name from hadv_profiles t1 where t1.status='A'" );
         $res  = $stmt->fetch();
         $name = $res['NAME'];


        /**
         * create a new instance of the gentab class
         */
        $tab = new gentab($this->idsadmin);

        /**
         * call the display_tab_by_page function of the gentab class and pass the required arguments.
         *   arg1:  Title .
         *   arg2:  Array of 'column' headings.
         *          We use the idsadmin lang function to get our string to use as a heading.
         *   arg3:  the query.
         *   arg4:  the count query.
         *   arg5:  how many to display per page
         */
        $tab->display_tab_by_page($this->idsadmin->lang("alarm_setup") .
                    '  [' . $this->idsadmin->lang("profile") . ' ' .  
                    ${name}. ']',
        array(
                  "1" => $this->idsadmin->lang("id"),
                  "3" => $this->idsadmin->lang("Category"),
                  "4" => $this->idsadmin->lang("desc"),
                  "5" => $this->idsadmin->lang("ldesc"),
                  "6" => $this->idsadmin->lang("modify"),
                  "7" => $this->idsadmin->lang("enable"),
        ),
        $qry,$qrycnt,NULL,"hadv_alarmconfig.php",$db_sysadmin);
        //$qry,$qrycnt,15,"hadv_admin_threshold.php",$db_sysadmin);

    } // end function showAlarmsTab

    function updateAlarms()
    {
    	/**
         * Set the Page Title - this is the title that is shown in the browser.
         */
        $this->idsadmin->html->set_pagetitle(
             $this->idsadmin->lang("prod_name"));

        /**
         * we first need a 'connection' to the database.
         */
        $db_sysadmin = $this->idsadmin->get_database("sysadmin");

        $totbox = $_POST['totbox'];
        $box = $this->idsadmin->in['box']; 
        $prof_id= $this->idsadmin->in['prof_id'];
        $totbox = $this->idsadmin->in['totbox']; 
        $UpdateTask = $this->idsadmin->in['UpdateTask']; 
        //$html .="btn = {$btn}<br>";

        /**
         * Check If Profile Still Exists 
         */
        $sql_stmt= " SELECT trim(name) prof_name from " .
                  "hadv_profiles where prof_id={$prof_id}" ;
        $prof_stmt = $db_sysadmin->query($sql_stmt);
        $prof_res = $prof_stmt->fetch();

        $prof_name = $prof_res['PROF_NAME'];
        if( trim($prof_name) == "")
        {
           $this->idsadmin->status($this->idsadmin->lang('deleted_prof'));
        }else {




        if(strcmp($UpdateTask,$this->idsadmin->lang("save")) == 0 && !$this->idsadmin->isreadonly())
        {
        /**
         * Loop through all the data and update enable field accordingly
         */


        for($i=0; $i < count($totbox); $i++)
        { 
           $updstmt="update hadv_gen_prof set enable = 'N' where prof_id=${prof_id} and id =$totbox[$i];";
           $html .=$updstmt."<br>";
           $db_sysadmin->exec($updstmt);
        }
        for($x=0; $x < count($box); $x++)
        {
          $updstmt="update hadv_gen_prof set enable = 'Y' where prof_id=${prof_id} and id = $box[$x];";
          $html .=$updstmt."<br>";
          $db_sysadmin->exec($updstmt);
        }

        $this->idsadmin->status($this->idsadmin->lang('saved_alarms'));

        //$this->idsadmin->html->add_to_output($html); 
        } // end if UpdateTask ==sSave

        $this->showAlarmsTab();
        }


    } // end function updateAlarms
   


    function modifyThreshold()
    {
    	if ($this->idsadmin->isreadonly())
    	{
    		$this->idsadmin->fatal_error($this->idsadmin->lang('noauth'));
    		return;
    	}
   	 /**
         * Set the Page Title - this is the title that is shown in the browser.
         */
        $this->idsadmin->html->set_pagetitle(
             $this->idsadmin->lang("prod_name"));

        /**
         * we first need a 'connection' to the database.
         */
        $db_sysadmin = $this->idsadmin->get_database("sysadmin");


        /**
         * Loop through all the data and update enable field accordingly
         */

        $id = $this->idsadmin->in['id'];
        $prof_id= $this->idsadmin->in['prof_id'];
        //$id = $_POST['id'];
        //$html .="Modify  = ".$id."<br>";


         $stmt = $db_sysadmin->query("select replace(desc,'Alarm Check','') ".
                "desc,ldesc,".
                "trim(yel_threshold) yel_threshold,".
                "trim(red_threshold) red_threshold," .
                "trim(red_lvalue_param1) red_lvalue_param1," .
                "trim(red_rvalue) red_rvalue," .
                "trim(yel_lvalue_param1) yel_lvalue_param1," .
                "trim(yel_rvalue) yel_rvalue," .
                "trim(temp_tab) temp_tab," .
                "exc_desc exc_desc" .
                " from hadv_gen_prof where prof_id=${prof_id} and id =".$id);

         $res = $stmt->fetch();
         $desc = $res['DESC'];
         $ldesc = $res['LDESC'];
         $red_threshold = $res['RED_THRESHOLD'];
         $yel_threshold = $res['YEL_THRESHOLD'];
         $red_lvalue_param1 = $res['RED_LVALUE_PARAM1'];
         $yel_lvalue_param1 = $res['YEL_LVALUE_PARAM1'];
         $red_rvalue = $res['RED_RVALUE'];
         $yel_rvalue = $res['YEL_RVALUE'];
         $temp_tab= $res['TEMP_TAB'];
         $temp_red = "t_red_".$temp_tab;
         $exc_desc = $res['EXC_DESC'];

/*
        $html .="red_threshold  = ".$red_threshold."<br>";
        $html .="yel_threshold  = ".$yel_threshold. "<br>";
        $html .="red_threshold  = ".strlen($red_threshold)."<br>";
        $html .="yel_threshold  = ".strlen($yel_threshold). "<br>";
        $html .="temp_tab = ".$temp_tab. "<br>";
*/

        $stmtcnt = $db_sysadmin->query(" SELECT count(*) as count FROM hadv_gen_prof where prof_id=${prof_id} and " .
                              "id = ${id} and (yel_lvalue like '%exception%' " .
                              "or red_lvalue like '%exception%') " );
        $res = $stmtcnt->fetch();

        $exception = $res['COUNT'];  
        //$html .="Count (exception) = ".$exception. "<br>";

        // Create Exception list 
        $exc_list="";

        // Only Have to pull red values, only support 1 exception list, 
        // applies to both red/yel

      



        $selstmt = $db_sysadmin->query(" SELECT t1.value as val FROM  ".
                 " hadv_exception_prof t1 where t1.prof_id= ${prof_id}".
                 " and t1.tabname = '${temp_tab}';" );
        $res = $selstmt->fetch();

        while($res)
        {
           $exc_list .= $res['VAL']; 
           $res = $selstmt->fetch();
           if($res)
           {
              $exc_list .= ","; 
           }
        }

        //$html .="exc_list  = ".$exc_list. "<br>";



      $msg_desc = "msg_". preg_replace('/ /','_',
          trim(preg_replace ('/%/','',trim($desc),-1) ), -1);
      $dsc_name = preg_replace("/msg_/","dsc_",$msg_desc,-1);

      $full_desc = $this->idsadmin->lang($msg_desc);

      $full_desc = preg_replace("/{$this->idsadmin->lang('Redalarm_')}/",
               "{$this->idsadmin->lang('Redalarm_')}</td>" .
               "<td >",
                $full_desc,-1);

      $full_desc = preg_replace("/{$this->idsadmin->lang('Yelalarm_')}/",
                "</td></tr> <tr> <td></td><td valign='top'><br>" .
                "{$this->idsadmin->lang('Yelalarm_')}</td>" .
                "<td><br/> " ,
                $full_desc,-1);



$html.= <<<EOF
<table  cellspacing="3" cellpadding="0">
<form method="post" action="index.php?act=ibm/hadv/healthadv&amp;do=updateThreshold">
<tr>
   <td width="2%"></td>
   <td width="18%"></td>
   <td width="60%"></td>
   <td width="14%"></td>
   <td width="1%"></td>
</tr>
<tr>
   <th colspan="4">{$this->idsadmin->lang('modify_threshold')}</th>
</tr>
<tr> <td colspan="4">&nbsp</td></tr>
<tr>
  <td> </td>
  <td>{$this->idsadmin->lang('alarm_name')}</td>
  <td colspan="2"> {$this->idsadmin->lang($dsc_name)}</td>
</tr>
<tr><td></td></tr>
<tr> <td colspan="4"></td></tr>
<tr>
  <td> </td>
  <td valign="top">{$full_desc}</td>
</tr>
<tr> <td colspan="4"></td> </tr>
<tr> <td colspan="4"></td></tr>
<tr>
   <th colspan="4">{$this->idsadmin->lang('thresholds')}</th>
</tr>
<tr> <td colspan="4"></td></tr>
<tr>
   <td colspan="5">{$this->idsadmin->lang('threshold_msg')}</td>
</tr>
<tr> <td colspan="4"></td></tr>


EOF;

if(strlen($red_threshold) > 0 && 
    strcmp($red_threshold,'red_lvalue_param1')==0 )
{         
$html.= <<<EOF
<tr>
       <td></td>
       <td>{$this->idsadmin->lang('Redalarmthreshold')}:</td>
       <td><input type="text" name="red_value" 
           value="${red_lvalue_param1}" size="20"/> 
       </td>
</tr>
EOF;
}

if(strlen($red_threshold) > 0 && 
    strcmp($red_threshold,'red_rvalue')==0 )
{         
$html.= <<<EOF
<tr>
       <td></td>
       <td>{$this->idsadmin->lang('Redalarmthreshold')}:</td>
       <td><input type="text" name="red_value" 
           value="${red_rvalue}" size="20"/> 
       </td>
</tr>
EOF;
}



if(strlen($yel_threshold) > 0 &&
    strcmp($yel_threshold,'yel_lvalue_param1')==0 )
{         
$html.= <<<EOF
<tr>
       <td></td>
       <td>{$this->idsadmin->lang('Yellowalarmthreshold')}:</td>
       <td><input type="text" name="yel_value" 
           value="${yel_lvalue_param1}" size="20"/></td>
</tr>
EOF;
}

if(strlen($yel_threshold) > 0 &&
    strcmp($yel_threshold,'yel_rvalue')==0 )
{         
$html.= <<<EOF
<tr>
       <td></td>
       <td>{$this->idsadmin->lang('Yellowalarmthreshold')}:</td>
       <td><input type="text" name="yel_value" 
           value="${yel_rvalue}" size="20"/></td>
</tr>
EOF;
}


if($exception > 0 )
{        
$exc_desc = preg_replace('/msg_/','exc_',$msg_desc,-1);
$html.= <<<EOF
<tr> <td>&nbsp</td></tr>
<tr>
   <th colspan="2">{$this->idsadmin->lang('exceptions')}</th>
</tr>
<tr> <td ></td></tr>
<tr>
   <td colspan="5"> {$this->idsadmin->lang($exc_desc)}</td>
</tr>
<tr> <td colspan="4"></td></tr>
<tr>
   <td></td>
   <td>{$this->idsadmin->lang('exceptions')}:</td>
   <td><input type="text" 
        name="exc_list" value="${exc_list}" size="60"/> </td>
</tr>

EOF;
}

$html.= <<<EOF

<tr>
        <td colspan="4">
            <input type="hidden" name="id" value="${id}" />
            <input type="hidden" name="red_threshold" value="${red_threshold}" />
            <input type="hidden" name="yel_threshold" value="${yel_threshold}" />
            <input type="hidden" name="temp_tab" value="${temp_tab}" />
            <input type="hidden" name="prof_id" value="${prof_id}" />
            <input type="submit" name ="btn" class="button" value="{$this->idsadmin->lang('save')}"/>
            <input type="submit" name="btn" class="button" value="{$this->idsadmin->lang('cancel')}" />

        </td>
</tr>
</form>

</table>
EOF;



        $this->idsadmin->html->add_to_output($html); 

    } // end function modifyThreshold


    function updateThreshold()
    {
    	if ($this->idsadmin->isreadonly())
    	{
    		$this->idsadmin->fatal_error($this->idsadmin->lang('noauth'));
    		return;
    	}

       $rurl="index.php?act=ibm/hadv/healthadv&do=alarms";
       $rtitle=$this->idsadmin->lang('prod_name');

         /**
         * Set the Page Title - this is the title that is shown in the browser.
         */
        $this->idsadmin->html->set_pagetitle(
             $this->idsadmin->lang("prod_name"));

        /**
         * we first need a 'connection' to the database.
         */
        $db_sysadmin = $this->idsadmin->get_database("sysadmin");


        $red_value = $this->idsadmin->in['red_value']; 
        $yel_value = $this->idsadmin->in['yel_value']; 
        $id = $this->idsadmin->in['id']; 
        $red_threshold = $this->idsadmin->in['red_threshold']; 
        $yel_threshold = $this->idsadmin->in['yel_threshold']; 
        $exc_list= $this->idsadmin->in['exc_list']; 
        $temp_tab= $this->idsadmin->in['temp_tab'];
        $prof_id= $this->idsadmin->in['prof_id'];
        $temp_red = "t_red_".$temp_tab;
        $temp_yel = "t_yel_".$temp_tab;

        $btn = $this->idsadmin->in['btn']; 

        $html="ut prof_id = {$prof_id}<br>";
        $this->idsadmin->html->add_to_output($html);


        if(strcmp($btn,$this->idsadmin->lang("cancel")) == 0)
        {
          $this->idsadmin->html->add_to_output(
          $this->idsadmin->template['template_global']->global_redirect(
              $rtitle,$rurl));
          $this->idsadmin->html->render();
           return;
        }

        /**
         * Check If Profile Still Exists 
         */
        $sql_stmt= " SELECT trim(name) prof_name from " .
                  "hadv_profiles where prof_id={$prof_id}" ;
        $prof_stmt = $db_sysadmin->query($sql_stmt);
        $prof_res = $prof_stmt->fetch();

        $prof_name = $prof_res['PROF_NAME'];
        if( trim($prof_name) == "")
        {
           $this->idsadmin->status($this->idsadmin->lang('deleted_prof'));
        }else {




        /**
         * Loop through all the data and update enable field accordingly
         */
/*
*/
        $html .="red_value = " .$red_value."<br>";
        $html .="yel_value = " .$yel_value."<br>";
        $html .="id = ".$id."<br>";
        $html .="prof_id = ".$prof_id."<br>";
        $html .="yel_threshold = ".$yel_threshold."<br>";
        $html .="red_threshold = ".$red_threshold."<br>";
        $html .="exc_list = ".$exc_list."<br>";
        if (isset($exc_list)) {
        $html .="exc_list isset <br>";
        }
        $html .="temp_tab = ".$temp_tab."<br>";
        $html .="t_red_ = ".$temp_red."<br>";
        $html .="t_yel_ = ".$temp_yel."<br>";

        $prof_stmt = $db_sysadmin->query(" SELECT prof_id from hadv_profiles t1 where t1.status='A' " );
        $prof_res = $prof_stmt->fetch();
        $prof_id = $prof_res['PROF_ID']; 


        if( strcmp($red_threshold,'red_lvalue_param1')==0 )
        {
           $updstmt="update hadv_gen_prof set red_lvalue_param1 = " . 
                    "'${red_value}' where prof_id=${prof_id} and id =$id;";
           //$html .=$updstmt."<br>";
           $db_sysadmin->exec($updstmt);
        }
        if( strcmp($red_threshold,'red_rvalue')==0 )
        {
           $updstmt="update hadv_gen_prof set red_rvalue = " . 
                    "'${red_value}' where prof_id=${prof_id} and id =$id;";
           //$html .=$updstmt."<br>";
           $db_sysadmin->exec($updstmt);
        }

        if( strcmp($yel_threshold,'yel_lvalue_param1')==0 )
        {
           $updstmt="update hadv_gen_prof set yel_lvalue_param1 = " . 
                    "'${yel_value}' where prof_id=${prof_id} and id =$id;";
           //$html .=$updstmt."<br>";
           $db_sysadmin->exec($updstmt);
        }
        if( strcmp($yel_threshold,'yel_rvalue')==0 )
        {
           $updstmt="update hadv_gen_prof set yel_rvalue = " . 
                    "'${yel_value}' where prof_id=${prof_id} and id =$id;";
           //$html .=$updstmt."<br>";
           $db_sysadmin->exec($updstmt);
        }

           $delstmt="delete from hadv_exception_prof where prof_id = ${prof_id} and ".
                    " tabname ='${temp_tab}'  ";
           $db_sysadmin->exec($delstmt);
              $html .=$delstmt."<br>";
        if(isset($exc_list)  && strlen(trim($exc_list)) > 0 )
        {

           $exc_data = preg_split('/,/',$exc_list); 
           foreach ($exc_data as $val) 
           {
              $insstmt="insert into hadv_exception_prof values( ${prof_id}," . 
                       "'${val}','${temp_tab}');";
              $db_sysadmin->exec($insstmt);

              $html .=$insstmt."<br>";
              $html .= "exc_val = ".$val."<br>";
           }
        }
/*
        $this->idsadmin->in['do']="alarms";
        $this->showAlarmsTab();
*/


       $this->idsadmin->html->add_to_output(
          $this->idsadmin->template['template_global']->global_redirect(
              $rtitle,$rurl));
       $this->idsadmin->html->render();


        }

    } // end function updateThreshold









}

?>
