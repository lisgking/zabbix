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
 * This class is used to show information
 * about the Recovery Logs
 *
 */
class rlogs {

    public  $idsadmin;

    /**
     * This class constructor sets
     * the default title and the
     * language files.
     *
     * @return rlogs
     */
    function __construct(&$idsadmin)
    {
        $this->idsadmin = &$idsadmin;
        $this->idsadmin->load_template("template_login");
        $this->idsadmin->load_lang("rlogs");
        $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('mainTitle'));
    }


    /**
     * The run function is what index.php will call.
     * The decission of what to actually do is based
     * on the value of the $this->idsadmin->in['do']
     *
     */
    function run()
    {
        /*
         * Execute any action which need to be taken
         */
        if ( isset($this->idsadmin->in['addllog']) )
        {
            if ( $this->actLLogAdd() === false )
            {
                // Adding a log failed so lets go back to the admin screen..
                $this->idsadmin->in['do'] = "admin";
            }
        }
        else if (isset($this->idsadmin->in['switchlog']) )
        {
            if ( $this->actSwitchLog() === false )
            {
                $this->idsadmin->in['do'] = "admin";
            }	
        }
        else if ( isset($this->idsadmin->in['dropllog']) )
        {
            if ( $this->actLLogDrop() === false )
            {
                // Dropping  a log failed so lets go back to the admin screen..
                $this->idsadmin->in['do'] = "admin";
            }
        }
        else if ( isset($this->idsadmin->in['moveplog']) )
        {
            if ( $this->actMovePLog() === false )
            {
                // moving the physical log failed so lets go back to the admin screen..
                $this->idsadmin->in['do'] = "admin";
            }
        }
        else if ( isset($this->idsadmin->in['updatepolicy']) )
        {
            if ( ( $this->actAutoLRUUpdate() === false ) ||
                 ( $this->actAutoCheckpointUpdate() == false) ||
                 ( $this->actRTOUpdate() == false) )
            {
                $this->idsadmin->in['do'] = "policy";
            }
            
            $this->idsadmin->in['do'] = "policy";
        }
        else if ( isset($this->idsadmin->in['dockpt']) )
        {
            if ( $this->actDoCkpt() === false )
            {
                // performing checkpoint failed so lets go back to the admin screen..
                $this->idsadmin->in['do'] = "admin";
            } else {
                // if checkpoint succeeded, ajax function will redisplay the page with 
                // result message
                return;
            }
        }
        
        
        $this->idsadmin->setCurrMenuItem("RecoveryLogs");
        
        if (isset($this->idsadmin->in['reportMode']))
        {
        	$this->idsadmin->setCurrMenuItem("Reports");
        }
        
        switch($this->idsadmin->in['do'])
        {
        	case 'addllogs':
        		$this->actLLogAdd();
        		break;
        	case 'llogs':
                $this->idsadmin->html->add_to_output( $this->setupRLogTabs() );
            case 'showllogs':
                $this->showLogicalLogs();
                break;
            case 'plog':
                $this->idsadmin->html->add_to_output( $this->setupRLogTabs() );
            case 'showplogs':
                $this->showPhysicalLog();
                break;
            case 'admin':
                $this->idsadmin->html->add_to_output("<div id='rlogadminpage'>");
            	$this->idsadmin->html->add_to_output( $this->setupRLogTabs() );
                $this->showAdmin();
                $this->idsadmin->html->add_to_output("</div>");
                break;
            case 'checkpoints';
            	$this->idsadmin->html->add_to_output( $this->setupRLogTabs() );
            	$this->showCheckpoints();
            	break;
            case 'policy':
                $this->idsadmin->html->add_to_output( $this->setupRLogTabs() );
                $this->showRecoveryPolicy();
                break;
            case 'demo':
                $this->showdemo();
                break;
            default:
            	$this->idsadmin->error("{$this->idsadmin->lang('InvalidURL_do_param')}");
                break;
        }
    }


    /**
     * Setup the Recovery Log detail tab we want
     * to use.
     *
     * @return HTML
     */
    function setupRLogTabs()
    {

        // don't setup tabs if in report mode
        if (isset($this->idsadmin->in["runReports"]) || isset($this->idsadmin->in['reportMode'])) return;

        require_once ROOT_PATH."/lib/tabs.php";
        $url="index.php?act=rlogs&amp;do=";
        $active = $this->idsadmin->in['do'];

        $t = new tabs();
        $t->addtab($url."llogs", $this->idsadmin->lang('mLogicalLogs'),
        ($active == "llogs") ? 1 : 0 );
        $t->addtab($url."plog", $this->idsadmin->lang('mPhysicalLog'),
        ($active == "plog") ? 1 : 0 );
        $t-$t->addtab($url."checkpoints",  $this->idsadmin->lang('mCheckpoints'),
        ($active == "checkpoints") ? 1 : 0 );
        
        if ( ! $this->idsadmin->isreadonly() ) {
        
        	$t->addtab($url."admin",  $this->idsadmin->lang('mAdmin'),
          		  ($active == "admin") ? 1 : 0 );
            
        	$t->addtab($url."policy",  $this->idsadmin->lang('mRPolicy'),
       			 ($active == "policy") ? 1 : 0 );
        
        }

        $html  = ($t->tohtml());
        $html .= "<div class='borderwrapwhite'>";
        return $html;
    } #end setuptabs

    /*
     *************************************************************
     * showdemo
     *
     * Purpose:   show all the pieces
     *
     *************************************************************
     */
    function showdemo()
    {
        $this->showPhysicalLog();
        $this->showLogicalLogs();
    }

    /**
     * Display information about the phsyical log
     *
     */
    function showPhysicalLog()
    {
        $this->idsadmin->html->add_to_output("<div class='tabpadding'>");
        $this->idsadmin->html->add_to_output( "<table style='width:100%;' role='presentation'><tr><td valign='top'>" );
        $this->showPlog();
        $this->idsadmin->html->add_to_output( "</td><td valign='top' style='width:50%'>" );
        $this->showPlogGraph();
        $this->idsadmin->html->add_to_output( "</td></tr>" );
        $this->idsadmin->html->add_to_output( "</table>" );
        $this->idsadmin->html->add_to_output("</div>");
    }


    /**
     * Show the Physical log table information
     *
     */
    function showPlog()
    {
        $db = $this->idsadmin->get_database("sysmaster");

        $sql = "SELECT " .
        " pl_chunk||'_'||pl_offset as location, " .
        " format_units(pl_physize,'P') as size, " .
        " format_units(pl_phyused,'P') as used, " .
        " format_units(pl_bufsize,'P') as bufsize, " .
        " format_units(pl_phypos,'P') as start " .
        " FROM sysplog ";

        $stmt = $db->query($sql);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $html=<<<END
<table class="gentab_padded" >
<tr>
<td class="tblheader" colspan="2" align="center">{$this->idsadmin->lang('PLogInfo')}</td>
</tr>
<tr>
<th align="left" > {$this->idsadmin->lang('PLogSize')} </th>
<td> {$res['SIZE']} </td>
</tr>

<tr>
<th align="left" > {$this->idsadmin->lang('PLogUsed')}</th>
<td> {$res['USED']} </td>
</tr>

<tr>
<th align="left"> {$this->idsadmin->lang('PlogLocation')}</th>
<td> {$res['LOCATION']} </td>
</tr>

<tr>
<th align="left"> {$this->idsadmin->lang('PlogStartPos')}</th>
<td> {$res['START']} </td>
</tr>

<tr>
<th align="left"> {$this->idsadmin->lang('PlogBuffSize')}</th>
<td> {$res['BUFSIZE']} </td>
</tr>

</table>
END;

        $this->idsadmin->html->add_to_output( $html );
    }


    /**
     * Show the Graph of the phsyical log
     *
     */
    function showPLogGraph()
    {

        // require_once ROOT_PATH."lib/idsgraphs.php";
        require_once ROOT_PATH."lib/Charts.php";
        $db = $this->idsadmin->get_database("sysmaster");

        $sql = "SELECT pl_physize-pl_phyused as free, pl_phyused as used " .
        "FROM sysplog";
        $stmt = $db->query($sql);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        $gdata = array(
        "{$this->idsadmin->lang('Used')}"        => $res['USED'],
        "{$this->idsadmin->lang('Free')}"        => $res['FREE'],
        );

        $this->idsadmin->Charts = new Charts($this->idsadmin);
        $this->idsadmin->Charts->setType("PIE");
        $this->idsadmin->Charts->setData($gdata);
        $this->idsadmin->Charts->setTitle($this->idsadmin->lang('PLogUsage'));
        $this->idsadmin->Charts->setDataTitles(array($this->idsadmin->lang('Pages'),$this->idsadmin->lang('Usage')));
        $this->idsadmin->Charts->setUnits($this->idsadmin->lang('pages'));
        $this->idsadmin->Charts->setLegendDir("vertical");
        $this->idsadmin->Charts->setWidth("100%");
        $this->idsadmin->Charts->setHeight("250");
        $this->idsadmin->Charts->Render();
         
    }


    /**
     * Man displayer of the logical log information
     *
     */
    function showLogicalLogs()
    {
        $this->idsadmin->html->add_to_output("<div class='tabpadding'>");
        $this->idsadmin->html->add_to_output( "<table style='width:100%;height:100%;' role='presentation'><tr style='height:50%'><td align='center'>" );
        $this->showLogicalLogGraph();
        $this->idsadmin->html->add_to_output( "</td></tr><tr><td>" );
        $this->showLLogs();
        $this->idsadmin->html->add_to_output( "</td></tr></table>" );
        $this->idsadmin->html->add_to_output("</div>");
    }

    /**
     * Show the Graph of the logical logs
     *
     */
    function showLogicalLogGraph()
    {
        require_once ROOT_PATH."lib/Charts.php";

        $db = $this->idsadmin->get_database("sysmaster");

        $sql = "select " .
        "sum ( " .
        "CASE " .
        "WHEN bitval(flags,'0x4')>0 THEN size " .
        "ELSE 0 " .
        "END " .
        ") as backedup, " .
        "sum ( " .
        "CASE " .
        "WHEN bitval(flags,'0x4')>0 " .
        "THEN 0 " .
        "WHEN bitval(flags,'0x1')>0 AND bitval(flags,'0x2')>0 " .
        "THEN used " .
        "WHEN bitval(flags,'0x1')>0 " .
        "THEN size " .
        "ELSE 0 " .
        "END " .
        ") as used, " .
        "sum ( " .
        "CASE " .
        "WHEN bitval(flags,'0x4')>0 " .
        "THEN 0 " .
        "WHEN bitval(flags,'0x1')>0 AND bitval(flags,'0x2')>0 " .
        "THEN size-used " .
        "WHEN bitval(flags,'0x1')>0 " .
        "THEN 0 " .
        "ELSE size " .
        "END " .
        ") as free " .
        "FROM syslogfil";

        $stmt = $db->query($sql);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $gdata = array(
        "{$this->idsadmin->lang('Used')}"       => $res['USED'],
        "{$this->idsadmin->lang('Free')}"       => $res['FREE'],
        "{$this->idsadmin->lang('BackedUp')}"    => $res['BACKEDUP'],
        );

        $this->idsadmin->Charts = new Charts($this->idsadmin);
        $this->idsadmin->Charts->setType("PIE");
        $this->idsadmin->Charts->setData($gdata);
        $this->idsadmin->Charts->setTitle($this->idsadmin->lang('LLogUsage'));
        $this->idsadmin->Charts->setDataTitles(array($this->idsadmin->lang('Pages'),$this->idsadmin->lang('Usage')));
        $this->idsadmin->Charts->setUnits($this->idsadmin->lang('pages'));
        $this->idsadmin->Charts->setLegendDir("vertical");
        $this->idsadmin->Charts->setWidth("100%");
        $this->idsadmin->Charts->setHeight("250");
        $this->idsadmin->Charts->Render();

    }


    /**
     * Show the table of logical logs
     *
     */
    function showLLogs()
    {
        require_once ROOT_PATH."lib/gentab.php";
        $tab = new gentab($this->idsadmin);

        $qry = "SELECT " .
        " A.number, " .
        " A.uniqid, " .
        " format_units(A.size,'P') as size, " .
        " TRIM( TRUNC(A.used*100/A.size,0)||'%') as used, " .
        " TRIM( A.chunk||'_'||A.offset ) as location, " .
        " decode(A.filltime,0,'NotFull', " .
        "  dbinfo('UTC_TO_DATETIME', A.filltime)::varchar(50)) as filltime, " .
        " CASE " .
        " WHEN bitval(A.flags,'0x1') > 0 AND bitval(A.flags,'0x4')>0 " .
        "      THEN 'UsedBackedUp'  " .
        " WHEN bitval(A.flags,'0x1') > 0 AND bitval(A.flags,'0x2')>0 " .
        "      THEN 'UsedCurrent' " .
        " WHEN bitval(A.flags,'0x1') > 0 " .
        "      THEN 'Used' " .
        " ELSE  " .
        "      hex(A.flags)::varchar(50) " .
        " END as flags, " .
        " CASE " .
        "   WHEN A.filltime-B.filltime > 0 THEN " .
        "     format_units(CAST(TRUNC(A.size/(A.filltime-B.filltime),4) " .
        "         as varchar(20)) ,'P')||'/SEC'  " .
        " ELSE  " .
        "      ' N/A ' " .
        " END as pps " .
         " , TRUNC(A.used*100/A.size,0) as used_size ".
        " FROM syslogfil A, syslogfil B" .
        " WHERE " .
        "       A.uniqid-1 = B.uniqid " .
        "       AND B.uniqid != 0 " .
        " UNION " .
        "SELECT " .
        " A.number, " .
        " A.uniqid, " .
        " format_units(A.size,'P') as size, " .
        " TRIM( TRUNC(A.used*100/A.size,0)||'%') as used, " .
        " TRIM( A.chunk||'_'||A.offset ) as location, " .
        " decode(A.filltime,0,'NotFull', " .
        "  dbinfo('UTC_TO_DATETIME', A.filltime)::varchar(50)) as filltime, " .
        " CASE " .
        " WHEN bitval(A.flags,'0x1') > 0 AND bitval(A.flags,'0x4')>0 " .
        "      THEN 'UsedBackedUp'  " .
        " WHEN bitval(A.flags,'0x1') > 0 AND bitval(A.flags,'0x2')>0 " .
        "      THEN 'UsedCurrent' " .
        " WHEN bitval(A.flags,'0x1') > 0 " .
        "      THEN 'Used' " .
        " WHEN bitval(A.flags,'0x8') > 0 " .
        "      THEN 'NewAdd' " .
        " ELSE  " .
        "      hex(A.flags)::varchar(50) " .
        " END as flags, " .
        "  'N/A' as pps " .
        " , TRUNC(A.used*100/A.size,0) as used_size ".
        " FROM syslogfil A " .
        " WHERE A.uniqid = (SELECT min(uniqid) FROM syslogfil WHERE uniqid > 0) ".
        " OR A.uniqid = 0  " .
        " ORDER BY A.uniqid ";

        $qrycnt = "SELECT count(*) as cnt FROM syslogfil";

        $tab->display_tab_by_page($this->idsadmin->lang('ltitle'),
        array(
        "1" => $this->idsadmin->lang('lognum'),
        "2" => $this->idsadmin->lang('uniqid'),
        "3" => $this->idsadmin->lang('size'),
        "9" => $this->idsadmin->lang('used'),
        "5" => $this->idsadmin->lang('location'),
        "6" => $this->idsadmin->lang('lastfilled'),
        "7" => $this->idsadmin->lang('notes'),
        "8" => $this->idsadmin->lang('PagesPerSec'),
        ),
        $qry, $qrycnt, NULL, "template_llogs.php");

    }


    /*
     *************************************************************
     * showAdmin
     *
     * Purpose:   Show the admin tab
     *
     *************************************************************
     */
    /**
     * Show the Admin Page for all the Recovery information
     *
     */
    function showAdmin()
    {
        if ( $this->idsadmin->isreadonly() )
        {
            $this->idsadmin->fatal_error($this->idsadmin->lang('NoPermission'));
        }

        $this->idsadmin->html->add_to_output( "<div id='adminform' class='tabpadding'>" );
        $this->idsadmin->html->add_to_output( "<table class='gentab_nolines_70' role='presentation'><tr><td>" );
        $this->showCheckpointForm();
        $this->idsadmin->html->add_to_output( "</td></tr><tr><td>" );
        $this->showLLogAdd();
        $this->idsadmin->html->add_to_output( "</td></tr><tr><td>" );
        $this->showSwitchLog();
        $this->idsadmin->html->add_to_output( "</td></tr><tr><td>" );
        $this->showLLogDrop();
        $this->idsadmin->html->add_to_output( "</td></tr><tr><td>" );
        $this->showMovePLog();
        $this->idsadmin->html->add_to_output( "</td></tr></table>" );
        $this->idsadmin->html->add_to_output( "</div>" );

    }

   /**
     * Show the Checkpoints Page (using checkpoints.php module)
     *
     */
    function showCheckpoints()
    {
        include_once("modules/checkpoints.php");
        $checkpoints = new checkpoints($this->idsadmin);
        $checkpoints->def();
    }

    /**
     * Show the Checkpoint Form
     *
     */
    function showCheckpointForm()
    {
        $html=<<<END
<script type="text/javascript" src='jscripts/ajax.js'></script>
<form method="post" action="index.php?act=rlogs&amp;do=checkpoints">
<table class="borderwrap" width="100%">
<tr>
<td class="tblheader" colspan="2" align="center"> {$this->idsadmin->lang('Checkpoint')}</td>
</tr>
<tr>
  <td>
  <select name="ckpttype">
  <option value="normal" selected="selected"> {$this->idsadmin->lang('normckpt')} </option>
  <option value="sync">{$this->idsadmin->lang('syncckpt')}</option>
  </select>
  </td>
  <td align="right">
  <input type=submit class=button name="dockpt" value="{$this->idsadmin->lang('dockpt')}"
  onClick="loadAJAX('adminform','index.php?act=rlogs&amp;do=admin','dockpt=dockpt&amp;ckpttype=' + ckpttype.value)"/>
  </td>
</tr>
</table>
</form>
END;
        $this->idsadmin->html->add_to_output( $html );
    }

    /**
     * Main function for the recovery tab
     **/
  	function showRecoveryPolicy() 
  	{
  		if ( $this->idsadmin->isreadonly() )
        {
            $this->idsadmin->fatal_error($this->idsadmin->lang('NoPermission'));
        }
        $HTML=<<<EOF
        <form method="post" action="index.php?act=rlogs&amp;do=policy">
         <input type="hidden" name="updatepolicy" value="on"/>
        
EOF;
        $this->idsadmin->html->add_to_output( $HTML );
        $this->idsadmin->html->add_to_output( "<div class='tabpadding'>" );
        $this->idsadmin->html->add_to_output( "<table class='gentab_nolines_70' role='presentation'><tr><td>" );
        $this->showRTOForm();
        $this->idsadmin->html->add_to_output( "</td></tr><tr><td>" );
        $this->showAutoCheckpoinForm();
        $this->idsadmin->html->add_to_output( "</td></tr><tr><td>" );
        $this->showAutoLRUTuningForm();
        
        $HTML=<<<EOF
        </td>
        <tr>
        <td align="center">
        <input type="submit" class="button" name="rto_upd" value="{$this->idsadmin->lang('save')}"/>
	<input type="button" class="button" name="cancel" value="{$this->idsadmin->lang('cancel')}" onclick='history.back()'/>
        </td>
        </tr>
        </table>
        </div>
        </form>
EOF;
        $this->idsadmin->html->add_to_output( $HTML );
        
        
  	}
  	
  	function selectListTime( $name, $time_array )
    {

        $dbadmin = $this->idsadmin->get_database("sysmaster");

        $stmt = $dbadmin->query("SELECT TRIM(cf_effective) as cf_value"
                                . " FROM syscfgtab "
                                . " WHERE cf_name='RTO_SERVER_RESTART'");
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		
		

		$info=$row['CF_VALUE'];


        $html=<<<END
<INPUT TYPE="hidden" NAME="rto_orig" VALUE="${info}"/>
        
<SELECT NAME="{$name}" SIZE=1>

END;

        $found=0;
        foreach ( $time_array as $val => $key )
        {

			if ( $info < $val && $found==0)
				{
				$found=1;		
                
            $html.=<<<END
        	
        		<OPTION VALUE="{$info}"  SELECTED  >{$info} {$this->idsadmin->lang('seconds')}</OPTION>
END;
				continue;
				}
			else if (  $val == $info  )
				{ 
                $sel=" SELECTED ";
                $found=1;
        		}
            else
                $sel=" ";
                
            $html.=<<<END
 
        	<OPTION VALUE="{$val}" {$sel} >{$key}</OPTION>
END;
        }
   
        return $html;
    }
  	
  	function showRTOForm() {
  		$minutes = $this->idsadmin->lang('minutes');
  		$time_array =  array(
	        "0" => "{$this->idsadmin->lang('OFF')}",
	        "60" => "1 {$this->idsadmin->lang('minute')}",
	        "120" => "2 $minutes",
	        "180" => "3 $minutes",
	        "240" => "4 $minutes",
	        "300" => "5 $minutes",
	        "360" => "6 $minutes",
	        "420" => "7 $minutes",
	        "480" => "8 $minutes",
	        "540" => "9 $minutes",
	        "600" => "10 $minutes",
	        "900" => "15 $minutes",
	        "1200" => "20 $minutes",
	        "1500" => "25 $minutes",
	        "1800" => "30 $minutes"
        );
  		
  		$selList = $this->selectListTime("rto_value", $time_array);
        $html=<<<EOF
<table class="borderwrap" width="100%">
<tr>
<td class="tblheader" colspan="5" align="center">{$this->idsadmin->lang('rto_title')}</td>
</tr>
<tr>
  <th align="center" width="50%">{$this->idsadmin->lang('rto')}</th>
  <td align="left">
  {$selList}
  </select>
  </td>
</tr>
</table>

EOF;

    $this->idsadmin->html->add_to_output( $html );
  	}
  	
  	function actRTOUpdate() {
  		if ( (isset($this->idsadmin->in['rto_orig'])==0) ||
  		   ( $this->idsadmin->in['rto_orig'] == $this->idsadmin->in['rto_value']) )
  		   {
  		   	return true;
  		   }
  		   
  		$cmd2     = 'RTO_SERVER_RESTART='.$this->idsadmin->in[ 'rto_value' ];
        $cmd1     = "wf";
        $cmd      = "onmode";
        $sql      = "SELECT " .
        	        " task( '$cmd', '$cmd1', '$cmd2' ) AS SINFO" .
        	        " FROM sysadmin:systables WHERE tabid=1";
        
        $dbadmin = $this->idsadmin->get_database("sysadmin");

        $stmt = $dbadmin->query($sql);
        $res = $stmt->fetch();

//error_log(var_export($dbadmin->errorInfo(),true));       
//error_log(var_export($stmt,true));

        $stmt->closeCursor();
        if ($res == null )
        {
        	$this->idsadmin->error($this->idsadmin->lang('rto_error'));
        }else {
        	$this->idsadmin->status( str_ireplace( "\\n"," ", $res['SINFO'] ) );
        }

        return true;  
  	}
  	
  	function showAutoCheckpoinForm() {
  	    $dbadmin = $this->idsadmin->get_database("sysmaster");
        $stmt = $dbadmin->query("SELECT TRIM(cf_effective) as cf_effective"
                                . " FROM syscfgtab "
                                . " WHERE cf_name='AUTO_CKPTS'");
                             
		$info=$stmt->fetch(PDO::FETCH_ASSOC);
		
		if ($info['CF_EFFECTIVE']=='1')
		{
			$val_string="<option value=\"1\" selected='selected'> {$this->idsadmin->lang('ON')} </option>  <option value=\"0\"> {$this->idsadmin->lang('OFF')} </option>";
			$value=1;
		} else
		{
			$val_string="<option value=\"0\" selected='selected'> {$this->idsadmin->lang('OFF')} </option>  <option value=\"1\"> {$this->idsadmin->lang('ON')} </option>";			
			$value=0;			
		}
  		 		
  		 $html=<<<END
  		 <input type="hidden" name="autockpt_orig" value="${value}"/>
  		 
<table class="borderwrap" width="100%">
<tr>
<td class="tblheader" colspan="4" align="center">{$this->idsadmin->lang('autoChkpts')} </td>
</tr>
<tr>
  <th align="center" width="50%">{$this->idsadmin->lang('autoChkpts')} </th>
   <td>
  <select name="autockpt_value">
  ${val_string}
  </select>
  </td>
</tr>
</table>

END;

    $this->idsadmin->html->add_to_output( $html ); 		
  	}
  	
  	function actAutoCheckpointUpdate() {
  		if ( (isset($this->idsadmin->in['autockpt_orig'])==0) ||
  		   ( $this->idsadmin->in['autockpt_orig'] == $this->idsadmin->in['autockpt_value']) )
  		   {
  		   	return true;
  		   }
  		   
  		$cmd2     = 'AUTO_CKPTS='.$this->idsadmin->in[ 'autockpt_value' ];
        $cmd1     = "wf";
        $cmd      = "onmode";
        $sql      = "SELECT " .
        	        " task( '$cmd', '$cmd1', '$cmd2' ) AS SINFO" .
        	        " FROM sysadmin:systables WHERE tabid=1";
        
        $dbadmin = $this->idsadmin->get_database("sysadmin");

        $stmt = $dbadmin->query($sql);
        $res = $stmt->fetch();

//error_log(var_export($dbadmin->errorInfo(),true));       
//error_log(var_export($stmt,true));

        $stmt->closeCursor();
        if ($res == null )
        {
        	$this->idsadmin->error($this->idsadmin->lang('autoChkpts_error'));
        }else {
        	$this->idsadmin->status( str_ireplace( "\\n"," ", $res['SINFO'] ) );
        }

        return true;   
  	}
  	
  	function showAutoLRUTuningForm() {
  
        $dbadmin = $this->idsadmin->get_database("sysmaster");
        $stmt = $dbadmin->query("SELECT TRIM(cf_effective) as cf_effective"
                                . " FROM syscfgtab "
                                . " WHERE cf_name='AUTO_LRU_TUNING'");
                             
		$info=$stmt->fetch(PDO::FETCH_ASSOC);
		//error_log(var_export($info,true));
		$stmt->closeCursor();
		
		if ($info['CF_EFFECTIVE']=='1')
		{
			$val_string="<option value=\"1\" selected='selected'> {$this->idsadmin->lang('ON')} </option>  <option value=\"0\"> {$this->idsadmin->lang('OFF')} </option>";
			$value=1;
		} else
		{
			$val_string="<option value=\"0\" selected='selected'> {$this->idsadmin->lang('OFF')} </option>  <option value=\"1\"> {$this->idsadmin->lang('ON')} </option>";			
			$value=0;			
		}
  		 $html=<<<END
<input type="hidden" name="autolru_orig" value="${value}"/>
<table class="borderwrap" width="100%">
<tr>
<td class="tblheader" colspan="4" align="center">{$this->idsadmin->lang('autoLRU')} </td>
</tr>
<tr>
  <th align="center" width="50%">{$this->idsadmin->lang('autoLRU')} </th>
   <td>
  <select name="autolru_value">
  ${val_string}
  </select>
  </td>
</tr>
</table>

END;

    $this->idsadmin->html->add_to_output( $html );
  		
  	}
  	
  	function actAutoLRUUpdate() {
  		if ( (isset($this->idsadmin->in['autolru_orig'])==0) ||
  		   ( $this->idsadmin->in['autolru_orig'] == $this->idsadmin->in['autolru_value']) )
  		   {
  		   	return true;
  		   }
  		$cmd2     = 'AUTO_LRU_TUNING='.$this->idsadmin->in[ 'autolru_value' ];
        $cmd1     = "wf";
        $cmd      = "onmode";
        $sql      = "SELECT " .
        	        " task( '$cmd', '$cmd1', '$cmd2' ) AS SINFO" .
        	        " FROM sysadmin:systables WHERE tabid=1";
        
        $dbadmin = $this->idsadmin->get_database("sysadmin");

        $stmt = $dbadmin->query($sql);
        $res = $stmt->fetch();

//error_log(var_export($dbadmin->errorInfo(),true));       
//error_log(var_export($stmt,true));

        $stmt->closeCursor();
        if ($res == null )
        {
        	$this->idsadmin->error($this->idsadmin->lang('autoLRU_error'));
        }else {
        	$this->idsadmin->status( str_ireplace( "\\n"," ", $res['SINFO'] ) );
        }

        return true;   
  	}
  	
    /**
     * Show the move physical log form
     *
     */
    function showMovePLog()
    {

        $html=<<<END
<form method="post" action="index.php?act=rlogs&amp;do=plog">
<table class="borderwrap" width="100%">
<tr>
<td class="tblheader" colspan="4" align="center"> {$this->idsadmin->lang('MPhysicalLog')}</td>
</tr>
<tr>
  <th align="center">{$this->idsadmin->lang('DBSpaceName')}</th>
  <th align="center">{$this->idsadmin->lang('size')}</th>
  <th align="center">{$this->idsadmin->lang('Confirm')}</th>
</tr>
<tr>
  <td align="center">
  {$this->idsadmin->html->selectList("movplogdbsname",
        "SELECT name as dbsname, name from sysdbspaces "
        . " where is_blobspace=0 and is_temp=0 and is_sbspace=0"
        ." AND pagesize = (select pagesize from sysdbspaces WHERE dbsnum = 1) ")}</select>
  </td>
  <td align="center">
  <input type="text" name="plogsize" size="13"/>
  <select id="plogSizeUnits" name="plogSizeUnits">
    <option value="K">{$this->idsadmin->lang('KB')}</option>
    <option value="M" selected="selected" >{$this->idsadmin->lang('MB')}</option>
    <option value="G">{$this->idsadmin->lang('GB')}</option>
  </select>
  </td>
  <td align="center">
  <select name="confirm">
  <option value="1"> {$this->idsadmin->lang('MPhysicalLog')} </option>
  <option value="0" selected="selected" >{$this->idsadmin->lang('NoMove')}</option>
  </select>
  </td>
  <td align="right">
  <input type=submit class=button name="moveplog" value="{$this->idsadmin->lang('Move')}"/>
  </td>
</tr>
</table>
</form>
END;

  $this->idsadmin->html->add_to_output( $html );

    }

    /**
     * Execute the move physical log command
     *
     */
    function actMovePLog() {
    	
        $check =  array(
	        "1" => "plogsize",
	        "2" => "plogSizeUnits",
	        "3" => "movplogdbsname",
	        "4" => "confirm",
        );
        foreach ( $check as $val )
        {
            if ( empty($this->idsadmin->in[ $val ]) &&
            strlen($this->idsadmin->in[ $val ])<1 )
            {
                $this->idsadmin->error( $this->idsadmin->lang('InvalidValue')  .
                ": " . $val );
                return false;
            }
        }
        if ( $this->idsadmin->in[ 'confirm' ] == 0 )
        {
            $this->idsadmin->error( $this->idsadmin->lang('LogMoveNotConfirmed') );
            return false;
        }

        $size     = $this->idsadmin->in[ 'plogsize' ] . $this->idsadmin->in[ 'plogSizeUnits' ];
        $dbspace  = $this->idsadmin->in[ 'movplogdbsname' ];
        $cmd      = "ALTER PLOG";
        $sql      = "SELECT " .
        	        " task( '$cmd', '$dbspace', '$size' ) AS SINFO" .
        	        " FROM sysadmin:systables WHERE tabid=1";
        
        $dbadmin = $this->idsadmin->get_database("sysadmin");

        $stmt = $dbadmin->query($sql);
        $res = $stmt->fetch();

        $stmt->closeCursor();
        if ($res == null )
        {
        	$this->idsadmin->error("{$this->idsadmin->lang('ErrorWithPhysLog')}");
        }else {
        	$this->idsadmin->status( str_ireplace( "\\n"," ", $res['SINFO'] ) );
        }

        return true;
    }

 
       function showSwitchLog()
    {

        $html=<<<END
<form method="post" action="index.php?act=rlogs&amp;do=admin">
<table class="borderwrap" width="100%">
<tr>
<td class="tblheader" colspan="4" align="center"> {$this->idsadmin->lang('SwitchLog')}</td>
</tr>
<tr>
  <th align="center">{$this->idsadmin->lang('SwitchComment')}</th>
  
  <td align="right">
  <input type="submit" class="button" name="switchlog" value="{$this->idsadmin->lang('Switch')}"/>
  </td>
</tr>
</table>
</form>
END;

        $this->idsadmin->html->add_to_output( $html );

    }
    
    function actSwitchLog() 
    {
        $cmd      = "onmode -l";
        $sql      = "EXECUTE FUNCTION task ('onmode','l')";
         
        $dbadmin = $this->idsadmin->get_database("sysadmin");

        $stmt = $dbadmin->query($sql);
        $res = $stmt->fetch();
        $stmt->closeCursor();
        
        if ($res == null )
        {
        	$this->idsadmin->error("{$this->idsadmin->lang('ErrorWithSwitchLog')}");
        } else {
        	$this->idsadmin->status( str_ireplace( "\\n"," ", $res[''] ) );
        }

        return true;
    }
        
    
    /**
     * Show the drop logical log form
     *
     */
    function showLLogDrop()
    {
        $html=<<<END
        <script type="text/javascript">
        function doDrop()
        {
        	var sel = document.getElementsByName("droplog");
        	
        	var lltodrop = sel[0].options[sel[0].selectedIndex].text;
        	var response = confirm("{$this->idsadmin->lang('DropLLog')} "+lltodrop+" ?");
        	if ( response )
        	{
    	    	document.getElementsByName("dropconfirm")[0].value="1";
    			return true;
    		}
        	return false;
        }
        </script>
        
	<form name="dropLogForm" method="post" action="index.php?act=rlogs&amp;do=admin">
<table class="borderwrap" width="100%">
<tr>
<td class="tblheader" colspan="4" align="center">{$this->idsadmin->lang('DropLLog')}</td>
</tr>
<tr>
  <th align="left">{$this->idsadmin->lang('LogicalLogNum')}</th>
  <th align="right">{$this->idsadmin->lang('Confirm')}</th>
</tr>
<tr>
  <td>
  {$this->idsadmin->html->selectList("droplog",
        "select A.number, A.number||' @ '||chunk||'_'||offset||' ' from syslogfil A order by A.number",
        "sysmaster","1")}
  </SELECT>
  </td>

  <td align="right">
	<input type="hidden" name="dropconfirm" value="0"/>
  	<input type=submit class=button name="dropllog" value="{$this->idsadmin->lang('Drop')}" onClick="return doDrop()"/>
  </td>
</tr>
</table>
  	</form>

END;

        $this->idsadmin->html->add_to_output( $html );

    }


    /**
     * Show the logical log add from
     *
     */
    function showLLogAdd()
    {
        $selList = $this->idsadmin->html->selectList("addlogdbsname","select name as dbsname,name as lstname from sysdbspaces where is_blobspace=0 and is_temp=0 and is_sbspace=0","sysmaster","","1","addlogdbsname");
        $html=<<<EOF

<script type="text/javascript" src='jscripts/ajax.js'></script>
<script type="text/javascript">
function setaddllogdata(ajaxform)
{
        var ajaxdata = "size=" + ajaxform.size.value + "&"
                     + "sizeUnits=" + ajaxform.sizeUnits.value + "&"
                     + "addlogcnt=" + ajaxform.addlogcnt.value + "&"
                     + "addlogattr=" + ajaxform.addlogattr.value + "&"
                     + "addlogdbsname=" + ajaxform.addlogdbsname.value;
        return ajaxdata;
}
</script>
<form>
<table class="borderwrap" width="100%">
<tr>
<td class="tblheader" colspan="5" align="center">{$this->idsadmin->lang('AddLogicalLog')}</td>
</tr>
<tr>
  <th align="center">{$this->idsadmin->lang('DBSpaceName')}</th>
  <th align="center">{$this->idsadmin->lang('size')}</th>
  <th align="center">{$this->idsadmin->lang('lognum')}</th>
  <th align="center">{$this->idsadmin->lang('attributes')}</th>
</tr>
<tr>
  <td align="center">
  {$selList}</select>
  </td>
  <td align="center">
  <input id="size" type="text" name="size" size="8"/>
  <select id="sizeUnits" name="sizeUnits">
    <option value="K">{$this->idsadmin->lang('KB')}</option>
    <option value="M" selected="selected" >{$this->idsadmin->lang('MB')}</option>
    <option value="G">{$this->idsadmin->lang('GB')}</option>
  </select>
  </td>
  <td align="center">
  <input id="addlogcnt" type="text" name="addlogcnt" size="8"/>
  </td>
  <td align="center">
  <select id="addlogattr" name="addlogattr">
  <option value="1">{$this->idsadmin->lang('AfterCurrentLog')}</option>
  <option value="0" selected="selected" >{$this->idsadmin->lang('AtEndOfLogs')}</option>
  </select>
  </td>
  <td align="right">
  <input type=button class=button name="addllog" 
  onClick="loadAJAX('rlogadminpage','index.php?act=rlogs&amp;do=addllogs',setaddllogdata(this.form),addLloghandler)"
  value="{$this->idsadmin->lang('Add')}"/>
  </td>
</tr>
</table>
</form>
EOF;

  $this->idsadmin->html->add_to_output( $html );
    }

    function actLLogAdd()
    {
        $this->idsadmin->render = false;
    	
    	$check =  array(
        "1" => "addlogdbsname",
        "2" => "size",
        "3" => "sizeUnits",
        "4" => "addlogattr",
        "5" => "addlogcnt",
        );

        foreach ( $check as $val )
        {
            if ( empty($this->idsadmin->in[ $val ]) &&
            strlen($this->idsadmin->in[ $val ])<1 )
            {
                echo "{$this->idsadmin->lang('ErrorF')} ".$this->idsadmin->lang('InvalidValue')  .
                ": " . $val ;
                return false;
            }
        }
        $name   = $this->idsadmin->in[ 'addlogdbsname' ];
        $size   = $this->idsadmin->in[ 'size' ] . $this->idsadmin->in[ 'sizeUnits' ];
        $cnt    = $this->idsadmin->in[ 'addlogcnt' ];
        $attr   = ($this->idsadmin->in['addlogattr']==1?",'true'": " ");
        $cmd    = "ADD LOG";
        $dbadmin = $this->idsadmin->get_database("sysadmin");

        $sql ="select " .
        " admin( '$cmd', '$name', '$size', '$cnt' $attr) as cmd_number" .
        " FROM systables where tabid=1";
        $stmt = $dbadmin->query($sql);
        $res = $stmt->fetch();
        $cmd_no = $res['CMD_NUMBER'];
        
        if($cmd_no < 0){
                //command failed
                $cmd_no = abs($cmd_no);
                $sql = "SELECT cmd_ret_msg AS retinfo".
                " FROM command_history WHERE cmd_number={$cmd_no}";
                $stmt = $dbadmin->query($sql);
                $res = $stmt->fetch(PDO::FETCH_ASSOC);
                echo $res['RETINFO'];
                return false;
        }else{
                //command suceeded
                echo $this->idsadmin->lang('LogicalLogsAdded');
                return true;
        }

    }


    /**
     * Execute the Drop logical log command
     *
     */
    function actLLogDrop()
    {
        $check =  array(
        "1" => "droplog",
        "2" => "dropconfirm",
        );
        foreach ( $check as $val )
        {
            if ( empty($this->idsadmin->in[ $val ]) &&
            strlen($this->idsadmin->in[ $val ])<1 )
            {
                $this->idsadmin->error( $this->idsadmin->lang('InvalidValue')  .
                ": " . $val);
                return false;
            }
        }
       
        if ( $this->idsadmin->in[ 'dropconfirm' ] == 0 )
        {
            $this->idsadmin->error( $this->idsadmin->lang('LogDropNotConfirmed') );
            return false;
        }

        $num       = $this->idsadmin->in[ 'droplog' ];
        $cmd    = "DROP LOG";
        $dbadmin = $this->idsadmin->get_database("sysadmin");

        $sql ="select " .
        " task( '$cmd', '$num' ) as info" .
        " FROM systables where tabid=1";
        $stmt = $dbadmin->query($sql);
        $res = $stmt->fetch();
        $this->idsadmin->status( $res['INFO'] );
        $stmt->closeCursor();
        return true;
    }


    /**
     * Perform a checkpoint
     *
     */
    function actDoCkpt()
    {
        if (empty($this->idsadmin->in['ckpttype']))
        {
            $this->idsadmin->error( $this->idsadmin->lang('InvalidValue')  .
                ': ckpttype');
            return false;
        }

        $cmd1 = "ONMODE";
        $cmd2 = "c";
        $cmd3 = ($this->idsadmin->in['ckpttype'] == "normal")? "norm":"hard";
        $dbadmin = $this->idsadmin->get_database("sysadmin");

        $sql ="select " .
        " task( '$cmd1', '$cmd2', '$cmd3' ) as info" .
        " FROM systables where tabid=1";
        $stmt = $dbadmin->query($sql);
        $res = $stmt->fetch();
        //$this->idsadmin->status( $res['INFO'] );
        
        // Using ajax function to show result instead of re-rendering the page 
        // through the idsadmin object
        $this->idsadmin->render = false;
        echo($res['INFO']);
        $stmt->closeCursor();
        
        return true;
    }
    


} // end class
?>
