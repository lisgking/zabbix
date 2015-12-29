<?php
/*********************************************************************
 *  Licensed Materials - Property of IBM
 *
 *  "Restricted Materials of IBM"
 *
 *  OpenAdmin Tool for Informix
 *  Copyright IBM Corporation 2011, 2012.  All rights reserved.
 **********************************************************************/


/**
 * This is a simple example 'module' which generates a table showing usernames and the number of
 * sequential scans by that user.
 */

class runhadv{

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
        $this->idsadmin->load_lang("act");
        $this->idsadmin->load_lang("dsc");

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
	     * find out what the user wanted todo ..
	     */
	    $do = $this->idsadmin->in['do'];
	    
	    /**
	     * map our action to a function.
	     */
	    switch ($do)
	    {

	      default:
                $this->idsadmin->setCurrMenuItem("hadv_menu");
                //$this->ajax(true);
                $this->execHADV();
                break;
	    }
		
    } // end of function run



    function execHADV()
    {
   	 /**
         * Set the Page Title - this is the title that is shown in the browser.
         */
    $html = "";


END;
        $this->idsadmin->html->set_pagetitle(
             $this->idsadmin->lang("prod_name"));

        require_once("lib/gentab.php");

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


        $prof_stmt = $db_sysadmin->query(" SELECT prof_id,name from hadv_profiles t1 where t1.status='A' " );
        $prof_res = $prof_stmt->fetch();
        $prof_id = $prof_res['PROF_ID'];
        $prof_name = $prof_res['NAME'];


        /**
         * Execute the Health Monitor 
         */


         $db_sysadmin->exec("execute procedure hadv_gen_check(${prof_id})");

$html.= <<<EOF
<html lang="{$this->idsadmin->html->get_html_lang()}" xml:lang="{$this->idsadmin->html->get_html_lang()}">
<head></head>
<body style="height:100% ; width:98%;" >
<br/>
<table width="75%">
<tr>
<td><center><h2>
{$this->idsadmin->lang('prod_name')}</h2>
</center></td>
</tr>
<tr>
<td><center><h5>
{$this->idsadmin->lang('profile')} {$prof_name}
</center></td>

</table>
<br/><br/>

EOF;
        $this->idsadmin->html->add_to_output($html); 

        /**
         * Handle Red Alarms
         */ 

         $qrycnt= $db_sysadmin->query("select count(*) as count  from hadv_temp_res where alarm='R'"); 
         $res = $qrycnt->fetch();
         $count = $res['COUNT']; 

         $totcnt=$count;

         $qry= $db_sysadmin->query("select alarm_id,alarm,desc,action," .
                "temp_tab,lparam1,param1,param3 ".
                " from hadv_temp_res where alarm='R'"); 
         for($i=1; $i <= $count; $i++)
         {       
         $res = $qry->fetch();

         $alarm_id = $res['ALARM_ID'];
         $alarm = $res['ALARM'];
         $desc = $res['DESC'];
         $action = $res['ACTION'];
         $lparam1 = $res['LPARAM1'];
         $param1 = $res['PARAM1'];
         $param3 = $res['PARAM3'];
         $temp_tab = "t_red_".trim($res['TEMP_TAB']);
         $stlen = strlen($temp_tab);

        /**
         * Setup action using lang msg files 
         */ 

        $msg_desc = preg_replace('/Alarm Check/','',$desc,-1);
        $msg_desc = preg_replace('/ /','_',
          trim(preg_replace ('/%/','',trim($msg_desc),-1) ), -1);

        $msg_action_name = "act_r_" . $msg_desc; 

        $msg_action = $this->idsadmin->lang($msg_action_name);
        $loc_desc = $this->idsadmin->lang("dsc_" . $msg_desc);

        $msg_action = preg_replace('/%lparam1%/',$lparam1,$msg_action,-1);
        $msg_action = preg_replace('/%param1%/',$param1,$msg_action,-1);
        $msg_action = preg_replace('/%param3%/',$param3,$msg_action,-1);

$html=<<<EOF
<br/>
<table width="80%">
<tr>
<td width="7%"> <img src='images/status_red.png' border='0' alt='{$this->idsadmin->lang("RedAlarm")}'/> </td>
<th align="left">$loc_desc <th> </tr>
<tr> <td></td>
<tr> <td></td>
<td> $msg_action </td></tr>

</table>
<br/><br/>

EOF;
        $this->idsadmin->html->add_to_output($html); 

        /**
         * Handle Multiple Results for Each Red Alarm
         */ 
         if(strcmp($temp_tab,'t_red_') <> 0)
         {
$html=<<<EOF
<br/>
<table width="80%">
<tr>
<td width="7%">  </td>
<td>
EOF;
            $this->idsadmin->html->add_to_output($html); 

            $res_qry = "select * from ${temp_tab}";
            $res_qrycnt = "select count(*) from ${temp_tab}";

            $tab = new gentab($this->idsadmin);

            $tab->display_tab(
               $this->idsadmin->lang(""),
               NULL,
               $res_qry,"hadv_temp_results.php",$db_sysadmin,15);
$html=<<<EOF
</td></tr>
</table>
<br/><br/>
EOF;
        $this->idsadmin->html->add_to_output($html); 
         }
      
         }

        /**
         * Handle Yellow Alarms
         */ 

         $qrycnt= $db_sysadmin->query("select count(*) as count  from hadv_temp_res where alarm='Y'"); 
         $res = $qrycnt->fetch();
         $count = $res['COUNT']; 

         $totcnt=$totcnt + $count;

         $qry= $db_sysadmin->query("select alarm_id,alarm,desc,action," .
                "temp_tab,lparam1,param1,param3 ".
                " from hadv_temp_res where alarm='Y'"); 
         for($i=1; $i <= $count; $i++)
         {       
         $res = $qry->fetch();


         $alarm_id = $res['ALARM_ID'];
         $alarm = $res['ALARM'];
         $desc = $res['DESC'];
         $action = $res['ACTION'];
         $lparam1 = $res['LPARAM1'];
         $param1 = $res['PARAM1'];
         $param3 = $res['PARAM3'];
         $temp_tab = "t_yel_".trim($res['TEMP_TAB']);
         $stlen = strlen($temp_tab);

        /**
         * Setup action using lang msg files 
         */ 

        $msg_desc = preg_replace('/Alarm Check/','',$desc,-1);
        $msg_desc = preg_replace('/ /','_',
          trim(preg_replace ('/%/','',trim($msg_desc),-1) ), -1);

        $msg_action_name = "act_y_" . $msg_desc; 

        $msg_action = $this->idsadmin->lang($msg_action_name);
        $loc_desc = $this->idsadmin->lang("dsc_" . $msg_desc);

        $msg_action = preg_replace('/%lparam1%/',$lparam1,$msg_action,-1);
        $msg_action = preg_replace('/%param1%/',$param1,$msg_action,-1);
        $msg_action = preg_replace('/%param3%/',$param3,$msg_action,-1);


$html=<<<EOF
<br/>
<table width="80%">
<tr>
<td width="7%"> <img src='images/status_yellow.png' border='0' alt='{$this->idsadmin->lang("YelAlarm")}'/> </td>
<th align="left">$loc_desc <th> </tr>
<tr> <td></td>
<tr> <td></td>
<td> $msg_action </td></tr>
</table>
<br/><br/>

EOF;
     
        $this->idsadmin->html->add_to_output($html); 

        /**
         * Handle Multiple Results for Each Yellow Alarm
         */ 

         if(strcmp($temp_tab,'t_yel_') != 0)
         {
$html=<<<EOF
<br/>
<table width="80%">
<tr>
<td width="7%">  </td>
<td>
EOF;
            $this->idsadmin->html->add_to_output($html); 

            $res_qry = "select * from ${temp_tab}";
            $res_qrycnt = "select count(*) from ${temp_tab}";

            $tab = new gentab($this->idsadmin);

            $tab->display_tab(
              $this->idsadmin->lang(""),
              NULL,
              $res_qry,"hadv_temp_results.php",$db_sysadmin,15);

$html=<<<EOF
</td></tr>
</table>
<br/><br/>
EOF;

        if($totcnt = 0)
        {

$html.=<<<EOF
{$this->idsadmin->lang('noalarms')}
EOF;

        }
        $this->idsadmin->html->add_to_output($html); 
        }

 
        }




    } // end function modifyAlarm 



        function ajax( $display )
        {
                $HTML = <<< EOF
        <script type="text/javascript">

        function loadit()
        {
          l=document.getElementById('output');
                   l.innerHTML="<center><img src='images/spinner.gif' border='0' alt=''/>{$this->idsadmin->lang('Executing')}</center>";

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
/*
  request.open("POST", "index.php?act=updstats&do=show&refreshEval=on",true);
*/
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
                        $HTML .= $this->execHADV();
                }
                $HTML .= <<< EOF
        </div>
EOF;
                $this->idsadmin->html->add_to_output($HTML);

        }








    
} // end of class threshold 


?>
