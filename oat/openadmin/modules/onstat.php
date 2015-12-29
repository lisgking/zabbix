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
 * This class is used to track each onstat option
 *
 */
class onstatoption {


    /**
     *  The HTML tag name.
     * Always the same as cmd, as long as
     * cmd is unique accross all classes.  If
     * cmd is not unqiue then you must make this
     * different.
     *
     * @var string
     */
    public $option;
    /**
     * The class name that contains this reports function.
     *
     * @var string
     */
    public $cname;
    /**
     * The option used to access this report inside the
     * run() function. (i.e. the value used by
     * $this->idsadmin->in['do'] )
     *
     * @var string
     */
    public $cmd;
    /**
     * The group of functionality to associate with
     * this onstat option.
     *
     * @var string
     */
    public $group;

    /** 
     * NOTE: So that report title and descriptions are localized,
     * the TITLE of the onstatoption should be retrieved using $this->idsadmin->lang($option)
     * and the DESCRIPTION of the onstatoption should be retrieved using $this->idsadmin->lang($option . "_tooltip") 
     **/
    
    /**
     * Used to create different onstat options that will
     * be displayed in the reprot builder.
     *
     * @param string $option
     * @param string $cmd
     * @param string $cname
     * @param string $group
     * @return onstatoption
     */
    function __construct($option, $cmd, $cname="onstat",$group="NONE")
    {
        $this->option  = $option;
        $this->cmd     = $cmd;
        $this->cname   = $cname;
        $this->group   = $group;
    }

    /**
     * Get the Option
     *
     * @return string
     */
    function getOption() {
        return $this->option;
    }
    /**
     * Get the command name
     *
     * @return string
     */
    function getCmd() {
        return $this->cmd;
    }
    /**
     * Return the class name
     *
     * @return string
     */
    function getCname() {
        return $this->cname;
    }

    /**
     * Return the group
     *
     * @return string
     */
    function getGroup() {
        return $this->group;
    }
    
    /**
     * This function will return if the currently set
     * global onstat option "onstatgoups::cur_group"
     * is set to this option's group.  If so return
     * the string which is passed in else return
     * nothing.
     *
     * @param string $ret
     * @return string
     */
    function getGrpStatus($ret="CHECKED ") {
        if (strcasecmp( onstatgroups::$cur_group, $this->group) == 0 ){
            return  $ret;
        } else if (strcasecmp( onstatgroups::$cur_group, "ALL") == 0 ){
            return  $ret;
        }
        return  "";
    }

}

/**
 *  This class is used to group a group
 */
/**
 * This is a list of groups that collects
 * similar onstat function together
 *
 */
class onstatgroups {


    /**
     * The name of the group
     *
     * @var string
     */
    public $gname;
    /**
     * The long description for the group
     *
     * @var string
     */
    public $desc;

    /**
     * The name of the current group, default
     * if the performance group
     *
     * @var string
     */
    public static $cur_group = "perf";

    /**
     * Used to create a new group
     *
     * @param string $gname
     * @param string $desc
     * @return onstatgroups
     */
    function onstatgroups($gname, $desc)
    {
        $this->gname   = $gname;
        if (empty($desc))
        $this->desc    = $this->gname;
        else
        $this->desc    = $desc;
    }

    /**
     * Get the group name
     *
     * @return string
     */
    function getGname() {
        return $this->gname;
    }
    /**
     * Get the long description
     *
     * @return string
     */
    function getDesc() {
        return $this->desc;
    }
    /**
     * Use to see if the current group matches
     * the global group.  If so return the
     * string passed in as "ret" else return
     * nothing.
     *
     * @param string $ret
     * @return string
     */
    function getGrpStatus($ret="CHECKED ") {
        if (strcasecmp( self::$cur_group, $this->getGname()) == 0 ) {
            return  $ret;
        } else if (strcasecmp( self::$cur_group, "ALL") == 0 ){
            return  $ret;
        }
        return  "";
    }
    /**
     * Use to see if the $name matches
     * the global group or the "ALL".  If so return the
     * string passed in as "ret" else return
     * nothing.
     *
     * @param string $name
     * @param string $ret
     * @return string
     */
    static function getMatchGroup($name, $ret="CHECKED ") {
        if (strcasecmp( self::$cur_group, $name) == 0 ) {
            return  $ret;
        } else if (strcasecmp( self::$cur_group, "ALL") == 0 ) {
            return  $ret;
        }

        return  "";
    }
    /**
     * Use to see if the group $name matches
     * the global group.  If so return the
     * string passed in as "ret" else return
     * nothing.
     *
     * @param string $name
     * @param string $ret
     * @return string
     */
    static function equalGroup($name, $ret="CHECKED ") {
        if (strcasecmp( self::$cur_group, $name) == 0 )
        {
            return  $ret;
        }

        return  "";
    }

}

/**
 * System specific report list
 */
global $olist;

$olist = array();
$olist[] = new onstatoption("diskspace","show","chunk","Disk");
$olist[] = new onstatoption("diskio","chunkio","chunk","Disk");
$olist[] = new onstatoption("showllogs","showllogs", "rlogs","Disk");
$olist[] = new onstatoption("showplogs","showplogs", "rlogs","Disk");
$olist[] = new onstatoption("backup","backup", "onstat","Disk");
$olist[] = new onstatoption("checkpoints","checkpoints", "rlogs","Disk");
$olist[] = new onstatoption("glo","glo", "onstat","Perf");
$olist[] = new onstatoption("config","config","onstat","Perf");
$olist[] = new onstatoption("sqlcache","sqlcache", "onstat","Perf");
$olist[] = new onstatoption("seg","seg", "onstat","Mem");
$olist[] = new onstatoption("mem","mem", "onstat","Mem");
$olist[] = new onstatoption("net","net", "onstat","Net");
$olist[] = new onstatoption("showOnlineLogTail","showOnlineLogTail", "show","Server");
$olist[] = new onstatoption("showCommands","showCommands", "show","Server");
$olist[] = new onstatoption("showComputerResource","rusage","onstat","Server");
$olist[] = new onstatoption("showComputerInfo","compinfo","onstat","Server");
$olist[] = new onstatoption("showByType","showByType", "sqltraceforreports","Sql");
$olist[] = new onstatoption("showslowSQL","showslowSQL","sqltraceforreports","SQL");
$olist[] = new onstatoption("showmostIO","showmostIO","sqltraceforreports","SQL");
$olist[] = new onstatoption("showmostBuff","showmostBuff","sqltraceforreports","SQL");
$olist[] = new onstatoption("showdbtab","showdbtab", "sqlwin","Tables");
$olist[] = new onstatoption("ppf","ppf", "onstat","Tables");
$olist[] = new onstatoption("ses","ses","onstat","User");
$olist[] = new onstatoption("showWaitingSessions","waitses","onstat","User");
$olist[] = new onstatoption("locklist","locklist","onstat","Locks");
$olist[] = new onstatoption("locksPerTab","locksPerTab","onstat","Locks");
$olist[] = new onstatoption("locksPerSes","locksPerSes","onstat","Locks");
$olist[] = new onstatoption("locksWaiters","locksWaiters","onstat","Locks");

/**
 * User specific report list
 */
/*global $oulist;
$oulist = array( );
$oulist[] = new onstatoption("ses","ses","onstat","Basic Session Information",
"List detials of user session.",
"Basic");
$oulist[] = new onstatoption("showSumByUser","showSumByUser","sqltraceforreports",
"SQL Summary",
"Summary of recent SQL run by user.",
"SQL");
$oulist[] = new onstatoption("showslowSQL","showslowSQL","sqltraceforreports",
"Slowest SQL Statements",
"Show the slowest SQL statements by this User.",
"SQL");
$oulist[] = new onstatoption("showmostIO","showmostIO","sqltraceforreports",
"SQL with the most IO time",
"Show the SQL statements consuming the most IO time.",
"SQL");
$oulist[] = new onstatoption("showmostBuff","showmostBuff","sqltraceforreports",
"SQL with the most Buffer Activity",
"Show the SQL statements with the most bufer pool activity.",
"SQL");
*/

/* Historical System Reports */

global $ohlist;

$ohlist = array();


$ohlist[] = new onstatoption("showByType","showByType", "sqltraceforreports","Sql");
$ohlist[] = new onstatoption("showslowSQL","showslowSQL","sqltraceforreports","SQL");
$ohlist[] = new onstatoption("showmostIO","showmostIO","sqltraceforreports","SQL");
$ohlist[] = new onstatoption("showmostBuff","showmostBuff","sqltraceforreports","SQL");


class onstat {

    public $idsadmin;
    
    const REPORT_MAX_ROWS = 1000;

    /**
     * the 'constructor' function
     * called when the class "new'd"
     */
    function __construct(&$idsadmin)
    {
        $this->idsadmin = &$idsadmin;
        $this->idsadmin->load_lang("onstat");
        $this->idsadmin->load_lang("onconfig");
    }


    /**
     * the run function
     * this is what index.php will call
     * the decision of what to actually do is based on
     * the value of 'do' which is either posted or getted
     */
    function run()
    {
    	
        $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('mainTitle'));

        switch($this->idsadmin->in['do'])
        {
            case 'glo':
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang("VPReport"));
                $this->idsadmin->setCurrMenuItem("Reports");
                $this->showVPList( );
                break;
            case 'net':
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang("NetworkUsage"));
                $this->idsadmin->setCurrMenuItem("Reports");
                $this->showNetUser( );
                break;
            case 'config':
                $this->idsadmin->setCurrMenuItem("onconfigparam");
                $this->showOnconfig( );
                break;
            case 'config_details':
                $this->idsadmin->set_redirect("config");
                $this->idsadmin->setCurrMenuItem("onconfigparam");
                $this->onconfigDetails( $this->idsadmin->in['param_id'] );
                break;
            case 'save_config':
                $this->idsadmin->set_redirect("config");
                $this->idsadmin->setCurrMenuItem("onconfigparam");
                if (isset($this->idsadmin->in['save']))
                {
                    $this->saveOnconfigParam();
                }
                else
                {  // for cancel
                    $this->showOnconfig();
                }
                break;
            case 'mempool':
            case 'mem':
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang("MemoryPools"));
                $this->idsadmin->setCurrMenuItem("Reports");
                $this->showpoollst();
                break;
            case 'seg':
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang("MemoryUsage"));
                $this->idsadmin->setCurrMenuItem("Reports");
                $this->showseglist();
                break;
            case 'backup':
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang("SysBackups"));
                $this->idsadmin->setCurrMenuItem("Reports");
                $this->showbackups();
                break;
            case 'ses':
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang("Sessions"));
                $this->idsadmin->setCurrMenuItem("Reports");
                if (empty($this->idsadmin->in['sid']) )
                {
                    $this->showSessions();
                }
                else
                {
                    $this->showUserSessions($this->idsadmin->in['sid']);
                }
                break;
            case 'sqlcache':
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang("SQLCache"));
                $this->idsadmin->setCurrMenuItem("Reports");
                $this->showsqlcache();
                break;
            case 'ppf':
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang("TableProf"));
                $this->idsadmin->setCurrMenuItem("Reports");
                $this->showTableProfile();
                break;
        	case 'hist':
                $this->idsadmin->setCurrMenuItem("Reports");
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang("SystemReports"));
                $this->idsadmin->html->add_to_output( $this->setupTabs() );
                $this->HistoricalReportList();
                break;
            case 'reports':
                $this->idsadmin->setCurrMenuItem("Reports");
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang("SystemReports"));
                $this->idsadmin->html->add_to_output( $this->setupTabs() );
                $this->ReportList();
                break;
            case 'runreports':
                $this->ReportRun();
                break;
              case 'runhreports':
                $this->ReportHRun($this->idsadmin->in['fromDay'],$this->idsadmin->in['fromMonth'],$this->idsadmin->in['fromYear'],$this->idsadmin->in['toDay'],$this->idsadmin->in['toMonth'],$this->idsadmin->in['toYear']);
                break;  
  /*          case 'userreports':
                $this->idsadmin->setCurrMenuItem("Reports");
                $this->ReportListUser();
                break;
            case 'runuserreports':
                $this->idsadmin->setCurrMenuItem("Reports");
                $this->ReportRunUser();
                break;*/
            case 'compinfo':
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang("CompInfo"));
                $this->idsadmin->setCurrMenuItem("Reports");
                $this->showComputerInfo();
                break;
            case 'rusage':
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang("ResourceUsage"));
                $this->idsadmin->setCurrMenuItem("Reports");
                $this->showResourceHistory();
                break;
            case 'waitses':
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang("WaitSesRpt"));
                $this->idsadmin->setCurrMenuItem("Reports");
                $this->showWaitingSessions();
                break;
            case 'locklist':
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang("locklist"));
                $this->idsadmin->setCurrMenuItem("Reports");
                $this->locklist();
                break;
            case 'locksWaiters':
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang("locksWaiters"));
                $this->idsadmin->setCurrMenuItem("Reports");
                $this->locksWaiters();
                break;
            case 'locksPerTab':
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang("locksPerTab"));
                $this->idsadmin->setCurrMenuItem("Reports");
                $this->locksPerTab();
                break;
            case 'locksPerSes':
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang("locksPerSes"));
                $this->idsadmin->setCurrMenuItem("Reports");
                $this->locksPerSes();
                break;
            case 'graphs':
                $this->idsadmin->setCurrMenuItem("Reports");
            	$this->idsadmin->html->add_to_output( $this->setupTabs() );
                $this->heatmaps();
                break;
            default:
                $this->idsadmin->error($this->idsadmin->lang('InvalidURL_do_param'));
                break;
        }
    }

    
    function setupTabs()
    {
        // don't setup tabs if in report mode
        if (isset($this->idsadmin->in["runReports"]))
        {
        	return;
        }
		
        require_once ROOT_PATH."/lib/tabs.php";
        $url="index.php?act=onstat&amp;do=";
        $active = $this->idsadmin->in['do'];
        $t = new tabs();
        
        // Current system reports tab
        $t->addtab($url."reports", $this->idsadmin->lang('mCurrent'),
        		($active == "reports") ? 1 : 0 );
        
        // Historical system reports tab:
        // Show the historical tab only if the historical sqltrace tables exist on the database server
        $db = $this->idsadmin->get_database("sysadmin");
        $stmt = $db->query("SELECT count(*) as count from systables where tabname='mon_syssqltrace'");
        $res = $stmt->fetch() ;
        $qrycnt = $res['COUNT'];
        if ( $qrycnt != 0) 
        {
        	$t->addtab($url."hist", $this->idsadmin->lang('mHistorical'),
        		($active == "hist") ? 1 : 0 );
        }
        
        // Heat Charts tab
        $t->addtab($url."graphs", $this->idsadmin->lang('HeatMaps'),
        		($active == "graphs") ? 1 : 0 );
        
        $html  = ($t->tohtml());
        $html .= "<div class='borderwrapwhite'>";
        return $html;
    } #end setuptabs
    
    
    /**
     * Display all the system reports and let the user build their
     * own report.
     * 1. Setup a set of report groups
     * 2. Display a list of system reports from the
     *    array $olistgroup
     * To add another report just add the report to the olistgroup
     * and everything else is done for you.
     *
     */ 
    function ReportList()
    {
        global $olist;
        if ( isset($this->idsadmin->in['reporttype']) )
        {
        	onstatgroups::$cur_group = $this->idsadmin->in['reporttype'];
        } else {
        	onstatgroups::$cur_group = "Clear All";
        }

        $olistgroup = array();
        $olistgroup[] = new onstatgroups("Clear All","{$this->idsadmin->lang('ClearAll')}");
        $olistgroup[] = new onstatgroups("All","{$this->idsadmin->lang('AllRept')}");
        $olistgroup[] = new onstatgroups("Disk","{$this->idsadmin->lang('Disk')}");
        $olistgroup[] = new onstatgroups("Perf","{$this->idsadmin->lang('Performance')}");
        $olistgroup[] = new onstatgroups("Mem","{$this->idsadmin->lang('Memory')}");
        $olistgroup[] = new onstatgroups("Net","{$this->idsadmin->lang('Network')}");
        $olistgroup[] = new onstatgroups("Server","{$this->idsadmin->lang('Server')}");
        $olistgroup[] = new onstatgroups("SQL","{$this->idsadmin->lang('SQL')}");
        $olistgroup[] = new onstatgroups("Tables","{$this->idsadmin->lang('Tables')}");
        $olistgroup[] = new onstatgroups("User","{$this->idsadmin->lang('Users')}");
        $olistgroup[] = new onstatgroups("Locks","{$this->idsadmin->lang('Locks')}");

        $html=<<<END
<div class="tabpadding">
<div class="borderwrap">      
<table class="gentab_nolines" >
<tr>
<td class="tblheader" colspan="4" align="center">{$this->idsadmin->lang('SystemReports')}</td>
</tr>
<tr>
  <td align="left" colspan="4">
  <form method="get" action="index.php" name="groupname">
  &nbsp;&nbsp;{$this->idsadmin->lang('SelectReportType')} 
  <input type="hidden" name="act" value="onstat" />
  <input type="hidden" name="do"  value="reports" />
  <select name="reporttype" onchange="groupname.submit()">
END;
        foreach( $olistgroup as $val )
        {
            $html.=<<<END
            <option value="{$val->getGname()}" {$val->equalGroup($val->getGname(),"SELECTED")} >{$val->getDesc()}</option>

END;
        }
        $html.=<<<END
  </select>
  </form>
 </td>
 </tr>
 </table>

END;

        $html.='<form method="post" action="index.php" name="reporter" target="_blank"><table class="gentab_nolines">';
        
        // We want to render the report list to read vertically, 
        // so switch up the array order to allow us to easily do this.
        $myolist = array();
        $midpoint = ceil(count($olist)/2);
        for ($i = 0; $i < $midpoint; $i++)
        {
        	$myolist[] = $olist[$i];
        	if (isset($olist[$midpoint + $i]))
        	{
        		$myolist[] = $olist[$midpoint + $i];
        	}
        }

        $start=true;
        foreach( $myolist as $val )
        {
            if ($start)
            {
            	$html.="<tr>";
            }

            $html.=<<<END

            <td align="right" >
            <input type="checkbox" name="{$val->getOption()}" {$val->getGrpStatus()} />
            </td>
            <td align="left">
            <a href="index.php?act={$val->getCname()}&amp;do={$val->getCmd()}&amp;reportMode" title="{$this->idsadmin->lang($val->getOption() . "_tooltip")}">{$this->idsadmin->lang($val->getOption())}</a>
    </td>
END;
            if ($start)
            {
            	$start=false;
            } else {
                $start=true;
                $html.="</tr>";
            }
        }
        if ($start)
        {
        	$html.="</tr>";
        }

        $html.=<<<END


        <tr>
        <td align="center" colspan="4">
        <input type="hidden" name="act" value="onstat"/>
        <input type="hidden" name="do"  value="runreports"/>
        <input type="hidden" name="fullrpt"  value="true"/>
        <input type="submit" class="button" name="runReports" value="{$this->idsadmin->lang('CreateReport')}"/>
		</td>
		</tr>
</table>
</form>
</div>
</div>
END;
        $this->idsadmin->html->add_to_output( $html );

    } # end function ReportList


    function HistoricalReportList()
    {
        global $ohlist;
        
        $db = $this->idsadmin->get_database("sysadmin");

		$qry = $db->query(" SELECT " .
                   " dbinfo('UTC_TO_DATETIME',NVL(min(sql_finishtime),dbinfo('UTC_CURRENT')))" .
                   " :: DATETIME YEAR TO YEAR as minyear," .
                   " CURRENT " .
                   " :: DATETIME YEAR TO YEAR as maxyear" .
                   " from mon_syssqltrace ");
        
 		$res = $qry->fetch() ;
 
        $minyear = $res['MINYEAR'];
        $maxyear = $res['MAXYEAR']; 
  
        if ( isset($this->idsadmin->in['reporttype']) )
        {
        	onstatgroups::$cur_group = $this->idsadmin->in['reporttype'];
        } else {
        	onstatgroups::$cur_group = "Clear All";
        }

        $olistgroup = array();
        $olistgroup[] = new onstatgroups("Clear All","{$this->idsadmin->lang('ClearAll')}");
        $olistgroup[] = new onstatgroups("All","{$this->idsadmin->lang('AllRept')}");

        
$html=<<<END
<div class="tabpadding">
  <div class="borderwrap">      
     <table class="gentab_nolines">
       
      
<tr>
<td class="tblheader" colspan="4" align="center">{$this->idsadmin->lang('SystemReports')}</td>
</tr> 
       
<tr>

  <td align="left" colspan="2">
  
  <script type="text/javascript">
  	function dateRangeChange(name,select)
  	{
  		document.getElementById(name + "Hidden").value = select.options[select.selectedIndex].value;
  	}
  </script>
  
  <form method="get" action="index.php" name="groupname">
  &nbsp;&nbsp;{$this->idsadmin->lang('SelectReportType')} 
  <input type="hidden" name="act" value="onstat" />
  <input type="hidden" name="do"  value="hist" />
  <input type="hidden" id="fromDayHidden" name="fromDayHidden" value="{$this->idsadmin->in['fromDayHidden']}" />
  <input type="hidden" id="fromMonthHidden" name="fromMonthHidden" value="{$this->idsadmin->in['fromMonthHidden']}" />
  <input type="hidden" id="fromYearHidden" name="fromYearHidden" value="{$this->idsadmin->in['fromYearHidden']}" />
  <input type="hidden" id="toDayHidden" name="toDayHidden" value="{$this->idsadmin->in['toDayHidden']}" />
  <input type="hidden" id="toMonthHidden" name="toMonthHidden" value="{$this->idsadmin->in['toMonthHidden']}" />
  <input type="hidden" id="toYearHidden" name="toYearHidden" value="{$this->idsadmin->in['toYearHidden']}" />
  <select name="reporttype" onchange="groupname.submit()">
  
END;
        foreach( $olistgroup as $val )
        {
            $html.=<<<END
            <option value="{$val->getGname()}" {$val->equalGroup($val->getGname(),"SELECTED")} >{$val->getDesc()}</option>
END;
        }
        
        $html.=<<<END
  </select>
  </form>
 </td>

END;

$html.='<form method="post" action="index.php" name="reporter" target="_blank">';
$html.=<<<END

        <td align="left">{$this->idsadmin->lang('Daterange')}
</td>
       <td><table><tr><td>{$this->idsadmin->lang("from")}
	
</td>
<td>
   			    <select name="fromYear" onchange='dateRangeChange("fromYear",this)'>
END;
			
   			    for ($val=$minyear; $val<=$maxyear; $val++)
   			    {
                	$selected = (isset($this->idsadmin->in['fromYearHidden']) && $this->idsadmin->in['fromYearHidden'] == $val)? "SELECTED":"";
   			    	$html.="<option value='$val' $selected>$val</option>";
   			    }
				$html.=<<<END
   			    
   			   </select>
				</td>		
<td>         
	          <select name="fromMonth" onchange='dateRangeChange("fromMonth",this)'>
END;
   			    for ($val=1; $val<=12; $val++)
   			    {
   			    	$selected = (isset($this->idsadmin->in['fromMonthHidden']) && $this->idsadmin->in['fromMonthHidden'] == $val)? "SELECTED":"";
   			    	$html.="<option value='$val' $selected>{$this->idsadmin->lang('Month' . $val)}</option>";
   			    }

		$html.=<<<END
	          </select>
</td>
<td>         
	          <select name="fromDay" onchange='dateRangeChange("fromDay",this)'>
END;
   			    for ($val=1; $val<=31; $val++)
   			    {
   			    	$val_text = $val;
   			    	if ($val < 10)
   			    	{
   			    		$val_text = "0" . $val;
   			    	}
   			    	$selected = (isset($this->idsadmin->in['fromDayHidden']) && $this->idsadmin->in['fromDayHidden'] == $val_text)? "SELECTED":"";
   			    	$html.="<option value='$val_text' $selected>$val</option>";
   			    }

		$html.=<<<END
	          </select>
</td>
				</tr>
 	
	<tr><td>{$this->idsadmin->lang("to")}
</td>	

<td>
                <select name="toYear" onchange='dateRangeChange("toYear",this)'>
END;
   			    for ($val2=$minyear; $val2<=$maxyear; $val2++)
   			    {
                	$selected = (isset($this->idsadmin->in['toYearHidden']) && $this->idsadmin->in['toYearHidden'] == $val2)? "SELECTED":"";
   			    	$html.="<option value='$val2' $selected>$val2</option>";
   			    }
				$html.=<<<END
   			   </select>
</td>
<td>
	          <select name="toMonth" onchange='dateRangeChange("toMonth",this)'>
END;
   			    for ($val=1; $val<=12; $val++)
   			    {
   			    	$selected = (isset($this->idsadmin->in['toMonthHidden']) && $this->idsadmin->in['toMonthHidden'] == $val)? "SELECTED":"";
   			    	$html.="<option value='$val' $selected>{$this->idsadmin->lang('Month' . $val)}</option>";
   			    }

   			    $html.=<<<END
	          </select>
</td>
<td>         
		          <select name="toDay" onchange='dateRangeChange("toDay",this)'>
END;
        for ($val=1; $val<=31; $val++)
        {
        	$val_text = $val;
        	if ($val < 10)
        	{
        		$val_text = "0" . $val;
        	}
        	$selected = (isset($this->idsadmin->in['toDayHidden']) && $this->idsadmin->in['toDayHidden'] == $val_text)? "SELECTED":"";
        	$html.="<option value='$val_text' $selected>$val</option>";
        }

		$html.=<<<END
	              </select>
</td>
</tr>
</table>
</td>

</tr>

END;

        // We want to render the report list to read vertically, 
        // so switch up the array order to allow us to easily do this.
        $myohlist = array();
        $midpoint = ceil(count($ohlist)/2);
        for ($i = 0; $i < $midpoint; $i++)
        {
        	$myohlist[] = $ohlist[$i];
        	if (isset($ohlist[$midpoint + $i]))
        	{
        		$myohlist[] = $ohlist[$midpoint + $i];
        	}
        }
        
        $start=true;
        foreach( $myohlist as $val )
        {
            if ($start)
            $html.="<tr>";

            $html.=<<<END

            <td align="right">
            <input type="checkbox" name="{$val->getOption()}" {$val->getGrpStatus()} />
            </td>
            <td align="left">
            {$this->idsadmin->lang($val->getOption())}
            </td>
END;
            if ($start)
            $start=false;
            else
            {
                $start=true;
                $html.="</tr>";
            }
        }
        if ($start)
        $html.="</tr>";

        $html.=<<<END

        <tr>
        <td align="center" colspan="4">
        <input type="hidden" name="act" value="onstat"/>
        <input type="hidden" name="do"  value="runhreports"/>
        <input type="hidden" name="fullrpt"  value="true"/>
        <input type="submit" class="button" name="runReports" value="{$this->idsadmin->lang('CreateReport')}"/>
		</td>
		</tr>

</form>
</table>
</div>
</div>
END;

        $this->idsadmin->html->add_to_output( $html );

    } # end function HistoricalReportList
    

    /**
     * Run all the system reports that we requested
     * The list of reports are stored under app->in
     *
     */
    function ReportRun()
    {
        global $olist;
        $db = $this->idsadmin->get_database("sysmaster");
        $html="";
        /*
         * Do the list of reports
         */
        $stmt = $db->query(
        " select " .
        " dbinfo('UTC_TO_DATETIME', sh_boottime) as boottime, " .
        " dbinfo('UTC_TO_DATETIME', sh_curtime) as curtime, " .
        " (sh_curtime - sh_boottime) as uptime " .
        " from sysshmvals"
        );
       
        $res = $stmt->fetch();
        $res['UPTIME']=$this->idsadmin->timedays($res['UPTIME']);

        $html.=<<<END
        <html lang="{$this->idsadmin->html->get_html_lang()}" xml:lang="{$this->idsadmin->html->get_html_lang()}">
        <head>
        <title>{$this->idsadmin->lang("SystemReports")}</title>
        </head>
        <body style="height:100% ; width:98%;" >
        <table width="98%" border="0">
        <tr>
        <td>
        <table border="0">
        <tr>
        <th> <a name='ReportTop'>{$this->idsadmin->lang('ReportRun')}</a> </th>
        <td> {$res['CURTIME']} </td>
        </tr>
        </table>
        <td align="right">
        <table border="0">
        <tr>
        <th>{$this->idsadmin->lang('ServerUptime')}</th>
        <td> {$res['UPTIME']} </td>
           </tr>
          </table>
        </td>
      </tr>
      </table>

END;
        $html.="<ol>";
        foreach( $olist as $val )
        {
            if ( isset( $this->idsadmin->in[ $val->getOption() ] ) )
            {
                $tname = $this->idsadmin->lang($val->getOption());
                $html.="<li><a href=\"#{$tname}\">{$tname}</a></li>";
            }
        }
        $html.="</ol>";
        $this->idsadmin->html->add_to_output( $html );

        /*
         * Run each report requested
         */
        foreach( $olist as $val )
        {
            if ( isset( $this->idsadmin->in[ $val->getOption() ] ) )
            {
                $cname = $val->getCname();
                if ( strcasecmp( $cname, "onstat") == 0 )
                $obj=$this;
                else
                {
                    require_once ROOT_PATH."modules/". $val->getCname().".php";
                    $this->idsadmin->html->debug($cname);
                    $obj = new $cname($this->idsadmin);
                }
                $html="<h2><a name='{$this->idsadmin->lang($val->getOption())}'>{$this->idsadmin->lang($val->getOption())}</a></h2><br/>";
                $this->idsadmin->html->add_to_output( $html );
                $this->idsadmin->in['do'] = $val->getCmd();
                $obj->run();
                $html="<h3 align='right'><a href=\"#ReportTop\">{$this->idsadmin->lang('Top')}</a></h3>";
                $this->idsadmin->html->add_to_output( $html );
            }
        }
        $this->idsadmin->html->add_to_output("</body></html>");
        return;
    }

    function ReportHRun($fromDay=null, $fromMonth=null, $fromYear=null, $toDay=null, $toMonth=null, $toYear=null)
    {
    	global $ohlist;
    	
    	if ($fromMonth < 10)
    	{
    		$fromMonth = "0" . $fromMonth;
    	}
    	if ($toMonth < 10)
    	{
    		$toMonth = "0" . $toMonth;
    	}
    	
        $db = $this->idsadmin->get_database("sysadmin");
        $html="";

        // Print report header
        $html.=<<<END
        <html lang="{$this->idsadmin->html->get_html_lang()}" xml:lang="{$this->idsadmin->html->get_html_lang()}">
        <head>
        <title>{$this->idsadmin->lang("SystemReports")}</title>
        </head>
        <body style="height:100% ; width:98%;" >
        <table width="98%" border="0">
        <tr>
        <td>
        <table border="0">
        <tr>
        <th> <a name='ReportTop'>{$this->idsadmin->lang('ReportRun')}</a> </th>
        </tr>
        </table>
        <td>
        <table border="0">
        <tr>
        
        <th>{$this->idsadmin->lang('StartDate')}</th>
        <td> $fromYear-$fromMonth-$fromDay </td>
        <td>&nbsp;</td>
        <th>{$this->idsadmin->lang('EndDate')}</th>
        <td> $toYear-$toMonth-$toDay </td>
        
        </tr>
        </table>
        
        </table>

END;
        $html.="<ol>";        
        $this->idsadmin->in['start_date'] = "$fromYear-$fromMonth-$fromDay";
        $this->idsadmin->in['end_date'] = "$toYear-$toMonth-$toDay";
            
        // Check that start data is before end date
        if ($this->idsadmin->in['start_date'] > $this->idsadmin->in['end_date'] )
        {
            $html.="<h3 align='left'><font color='red'>{$this->idsadmin->lang('InvalidDateRangeError')}</font></h3>";
            $this->idsadmin->html->add_to_output( $html );
            $this->idsadmin->html->add_to_output("</body></html>");
        	return;
        }  
        
      
        foreach( $ohlist as $val )
        {
            if ( isset( $this->idsadmin->in[ $val->getOption() ] ) )
            {
                $tname = $this->idsadmin->lang($val->getOption());
                $html.="<li><a href=\"#{$tname}\">{$tname}</a></li>";
            }
        }
        $html.="</ol>";
        $this->idsadmin->html->add_to_output( $html );

        /*
         * Run each report requested
         */
        foreach( $ohlist as $val )
        {
            if ( isset( $this->idsadmin->in[ $val->getOption() ] ) )
            {
                $cname = $val->getCname();
                if ( strcasecmp( $cname, "onstat") == 0 )
                $obj=$this;
                else
                {
                    require_once ROOT_PATH."modules/". $val->getCname().".php";
                    $this->idsadmin->html->debug($cname);
                    $obj = new $cname($this->idsadmin);
                }
                $html="<h2><a name='{$this->idsadmin->lang($val->getOption())}'>{$this->idsadmin->lang($val->getOption())}</a></h2><br/>";
                $this->idsadmin->html->add_to_output( $html );
                $this->idsadmin->in['do'] = $val->getCmd();
   
                $obj->run();
                
                $html="<h3 align='right'><a href=\"#ReportTop\">{$this->idsadmin->lang('Top')}</a></h3>";
             	$this->idsadmin->html->add_to_output( $html );
             }    
        }
        $this->idsadmin->html->add_to_output("</body></html>");
        return;
    }
    
     /**
     * Display a list of users and a list of user reports
     * Let the users build their own reports
     *
     * To add a user defined report just add the report
     * to the $oulist array.  The user's sid will be passed
     * in $this->idsadmin->in['sid'].  reports must validate that the user
     * has not logged off since being asked to run the
     * report
     *
     */
    function ReportListUser()
    {
        global $oulist;
        $db = $this->idsadmin->get_database("sysmaster");
        /* get the current SID */
        if ( isset($this->idsadmin->in['sid']) )
        {
            $sid = $this->idsadmin->in['sid'];
        }
        else
        {
            $stmt = $db->query(
            "select first 1 sid as sid, " .
            " trim(username)||'('||sid||')' as name " .
            " from sysscblst " .
            " where sid != dbinfo('sessionid') and connected > 0 order by 2, 1 DESC" );
            $res = $stmt->fetch();
            $sid = $res['SID'];
        }

        if ( isset($this->idsadmin->in['reporttype']) )
        onstatgroups::$cur_group = $this->idsadmin->in['reporttype'];
        else
        onstatgroups::$cur_group = "Clear All";

        $olistgroup = array();
        $olistgroup[] = new onstatgroups("Clear All","{$this->idsadmin->lang('ClearAll')}");
        $olistgroup[] = new onstatgroups("All"," {$this->idsadmin->lang('AllRept')}");
        $olistgroup[] = new onstatgroups("Basic"," {$this->idsadmin->lang('Basic')}");
        $olistgroup[] = new onstatgroups("SQL"," {$this->idsadmin->lang('SQL')}");


        $html=<<<END
<table class="gentab_nolines">
<tr>
<td class="tblheader" colspan="4" align="center"> User Reports </td>
</tr>
<tr>
  <td align="center" colspan=2>
  <form method="get" action="index.php" name="uinfo">
   Select Report Type
  <input type="hidden" name="act" value="onstat"/>
  <input type="hidden" name="do"  value="userreports"/>
  <select name="reporttype" onchange="uinfo.submit()">
END;


        foreach( $olistgroup as $index => $val )
        {
            $html.=<<<END
            <option value="{$val->getGname()}"
END;
            $html.=onstatgroups::equalGroup($val->getGname(),"SELECTED");
            $html.=<<<END
            >{$val->getDesc()}</option>

END;
        }
        $html.=<<<END
  </select>
  </td>
  <td colspan=2 align="center">
  User
END;


        $html.=$this->idsadmin->html->autoSelectList(
        "uinfo",
        "sid",
        "select sid as sid, trim(username)||'('||sid||')' as name " .
        "from sysscblst where sid != dbinfo('sessionid') and connected > 0 order by 2, 1 DESC",
        "sysmaster",
        $sid
        );
        $html.=<<<END
</form>
 </td>
</tr>
END;

        $html.='<form method="post" action="index.php">';
        $start=true;
        foreach( $oulist as $val )
        {
            if ($start)
            $html.="<tr>";
            $html.=<<<END

            <td align="right" >
            <input type="checkbox" name="{$val->getOption()}"
END;
            $html.=onstatgroups::getMatchGroup($val->getGroup());
            $html.=<<<END
            />

            </td>
            <td align="Left">
            <a href="index.php?act={$val->getCname()}&amp;do={$val->getCmd()}&amp;sid={$sid}"  title="{$val->getDesc()}">{$val->getTitle()}</a>
    </td>
END;
            if ($start)
            $start=false;
            else
            {
                $start=true;
                $html.="</tr>";
            }
        }
        if ($start)
        $html.="</tr>";
        $html.=<<<END


        <tr>
        <td align="center" colspan="4">
        <input type="hidden" name="act" value="onstat"/>
        <input type="hidden" name="do"  value="runuserreports"/>
        <input type="hidden" name="sid"  value="{$sid}"/>
        <input type="hidden" name="fullrpt"  value="true"/>
        <input type="submit" class=button name=runReports value="{$this->idsadmin->lang('CreateReport')}"/>
   </form>
</td>
</tr>
</table>
END;

        $this->idsadmin->html->add_to_output( $html );

    } # end function

    /**
     * Run the list of user defined reports
     *
     */
    function ReportRunUser()
    {
        global $oulist;
        $db = $this->idsadmin->get_database("sysmaster");
        $html="";
        /*
         * Do the list of reports
         */
        $stmt = $db->query(
        " select " .
        " dbinfo('UTC_TO_DATETIME', sh_boottime) as boottime, " .
        " dbinfo('UTC_TO_DATETIME', sh_curtime) as curtime, " .
        " (sh_curtime - sh_boottime) as uptime " .
        " from sysshmvals"
        );
        $res = $stmt->fetch();
        $res['UPTIME']=$this->idsadmin->timedays($res['UPTIME']);

        $html.=<<<END
        <table width="100%" border="0">
        <tr>
        <td>
        <table border="0">
        <th> <a name='ReportTop'>{$this->idsadmin->lang('ReportRun')}</a> </th>
        <td> {$res['CURTIME']} </td>
        </table>
        <td align="right">
        <table border="0">
        <th>{$this->idsadmin->lang('ServerUptime')}</tj>
        <td>{$res['UPTIME']}</td>
          </table>
        </td>
      </tr>
      </table>

END;
        $html.="<ol>";


        /*
         * Run each report requested
         */
        foreach( $oulist as $index => $val )
        {
            if ( isset( $this->idsadmin->in[ $val->getOption() ] ) )
            {
                $tname = $val->getTitle();
                $html.="<li><a href=\"#{$tname}\">{$tname}</a></li>";
            }
        }
        $html.="</ol>";
        $this->idsadmin->html->add_to_output( $html );


        foreach( $oulist as $val )
        {
            if ( isset( $this->idsadmin->in[ $val->getOption() ] ) )
            {
                $cname = $val->getCname();
                if ( strcasecmp( $cname, "onstat") == 0 )
                $obj=$this;
                else
                {
                    require_once ROOT_PATH."modules/". $val->getCname().".php";
                    $obj = new $cname($this->idsadmin);
                }
                $html="<h2 align='left'>
                <a name='{$val->getTitle()}'>{$val->getTitle()}</a></h2><br/>";
                $this->idsadmin->html->add_to_output( $html );
                $this->idsadmin->in['do'] = $val->getCmd();
                $obj->run();
                $html="<h3 align='right'><a href=\"#ReportTop\">{$this->idsadmin->lang('Top')}</a></h3>";
                $this->idsadmin->html->add_to_output( $html );
            }
        }
        return;
    }

    /**
     * Main report for the Virtual Processor report.
     * 1.  Displays a graph of the Virtual Processors
     * 2.  Display a table list of the VP information
     *
     */
    function showVPList( )
    {
        $this->idsadmin->html->add_to_output( "<table  style='width:100%;height:100%'><tr   style='width:100%;height:50%'><td align='center'>" );
        $this->showVPGraph();
        $this->idsadmin->html->add_to_output( "</td></tr><tr><td>" );
        $this->showVPTable();
        $this->idsadmin->html->add_to_output( "</td></tr></table>" );

    } #end default

    /**
     * Show a tabular format of the Virtual Processor Information
     *
     */
    function showVPTable( )
    {
        $this->idsadmin->load_lang("vps");
    	
    	require_once ROOT_PATH."lib/gentab.php";

        $tab = new gentab($this->idsadmin);

        /* Individual user information for the table */
        $qry = "SELECT ".
        "A.vpid, " .
        "A.pid, " .
        "B.txt, " .
        "A.usecs_user, ".
        "A.usecs_sys, ".
        "A.num_ready ".
        "from sysvplst A, flags_text B   " .
        "WHERE  B.tabname = 'sysvplst' " .
        " AND  a.class = B.flags " .
        " order by vpid" ;

        $qrycnt = "SELECT count(*) from sysvplst";

        $tab->display_tab_by_page($this->idsadmin->lang('NetworkUsage'),
        array(
        "1" => $this->idsadmin->lang('vpid'),
        "2" => $this->idsadmin->lang('PPID'),
        "3" => $this->idsadmin->lang('Class'),
        "4" => $this->idsadmin->lang('usercpu'),
        "5" => $this->idsadmin->lang('syscpu'),
        "6" => $this->idsadmin->lang('ReadyQ'),
        ),
        $qry, $qrycnt, NULL, "template_gentab_order.php");

    } #end

    /**
     * Show a Graph of virtual process by class
     *
     */
    function showVPGraph( )
    {

    	$this->idsadmin->load_lang("vps");
    	
        $db = $this->idsadmin->get_database("sysmaster");

        require_once ROOT_PATH."lib/Charts.php";

        $sql ="SELECT sum(usecs_sys + usecs_user) as time , " .
        " B.txt  " .
        " FROM sysvplst A, flags_text B   " .
        " WHERE  B.tabname = 'sysvplst' " .
        " AND  a.class = B.flags " .
        " GROUP by B.txt " ;

        $stmt = $db->query($sql);
        $udata = array( );
        while ($res = $stmt->fetch())
        {
            $udata[  $res['TXT']  ] = $res[ 'TIME' ];
        }

        $this->idsadmin->Charts = new Charts($this->idsadmin);
        $this->idsadmin->Charts->setType("PIE");
        $this->idsadmin->Charts->setData($udata);
        $this->idsadmin->Charts->setTitle($this->idsadmin->lang('VP'));
        $this->idsadmin->Charts->setDataTitles(array($this->idsadmin->lang('totalcpu'),$this->idsadmin->lang('class')));
        $this->idsadmin->Charts->setUnits($this->idsadmin->lang('seconds'));
        $this->idsadmin->Charts->setLegendDir("vertical");
        $this->idsadmin->Charts->setWidth("100%");
        $this->idsadmin->Charts->setHeight("250");
        $this->idsadmin->Charts->Render();

        //$this->idsadmin->html->add_to_output( $mygraph->pieGraph( $udata,
        //    $this->idsadmin->lang('VP'),300,300,true) );
    }

    /**
     * Display the network user list in both a Graphical
     * view and a tabular view.
     *
     */
    function showNetUser( )
    {
        $this->idsadmin->html->add_to_output( "<table style='width:100%;height:100%'><tr   style='width:100%;height:50%'><td align='center' valign='top'>" );
        $this->showNetUserGraph( );
        $this->idsadmin->html->add_to_output( "</td></tr><tr><td>" );
        $this->showNetUserTable( );
        $this->idsadmin->html->add_to_output( "</td></table>" );
    } #end default

    /**
     * Display the network user list in a tabular view.
     *
     */
    function showNetUserTable( )
    {

        require_once ROOT_PATH."lib/gentab.php";
        $tab = new gentab($this->idsadmin);

        /* Individual user information for the table */
        $qry = "SELECT ".
        "net_id, " .
        "sid, " .
        "net_client_name, ".
        "net_protocol, ".
        "dbinfo('UTC_TO_DATETIME', net_open_time) as connect, " .
        "net_read_cnt as reads, ".
        "net_write_cnt as writes, ".
        "format_units(net_read_bytes,'b') as readbytes, ".
        "format_units(net_write_bytes,'b') as writebytes, ".
        "net_write_cnt + net_read_cnt as netio ".
        "from sysnetworkio  " .
        " order by netio DESC" ;

        $qrycnt = "SELECT count(*) from sysnetworkio";

        $tab->display_tab_by_page($this->idsadmin->lang('NetUserRep'),
        array(
        "1" => $this->idsadmin->lang('NetID'),
        "2" => $this->idsadmin->lang('SID'),
        "3" => $this->idsadmin->lang('ClientName'),
        "4" => $this->idsadmin->lang('Protocol'),
        "5" => $this->idsadmin->lang('ConTime'),
        "6" => $this->idsadmin->lang('ReadCount'),
        "7" => $this->idsadmin->lang('WriteCount'),
        "8" => $this->idsadmin->lang('ReadBytes'),
        "9" => $this->idsadmin->lang('WriteBytes'),
        ),
        $qry, $qrycnt, NULL, "template_gentab_order.php");

    } #end


    /**
     * Display a network system graph
     *
     */
    function showNetUserGraph( )
    {
        $db = $this->idsadmin->get_database("sysmaster");

        require_once ROOT_PATH."lib/Charts.php";

        $sql ="SELECT ng_writes as writes, ng_reads as reads, " .
        "ng_his_writes_bytes as write_data, " .
        "ng_his_reads_bytes as read_data, " .
        " ng_connects AS CONNECT " .
        " FROM sysnetglobal" ;

        $stmt = $db->query($sql);
        $res = $stmt->fetch();
        $stmt->closeCursor();

        $udata = array(
        "Sends" => (int)$res['WRITES'],
        "Receives" => (int)$res['READS'],
        "Connects" => (int)$res['CONNECT'],
        );
        $udata1 = array(
        "Sends" => (int)$res['WRITE_DATA'],
        "Receives"  => (int)$res['READ_DATA'],
        );

        $sql ="SELECT "  .
        " sum(net_write_bytes) as writes, " .
        " sum(net_read_bytes) as reads " .
        " FROM sysnetworkio" ;

        $stmt = $db->query($sql);
        $res = $stmt->fetch();
        $stmt->closeCursor();

        $udata1["Sends"] += (int)$res['WRITES'];
        $udata1["Receives"] += (int)$res['READS'];

        $this->idsadmin->Charts = new Charts($this->idsadmin);
        $this->idsadmin->Charts->setType("PIE");
        $this->idsadmin->Charts->setData($udata);
        $this->idsadmin->Charts->setTitle($this->idsadmin->lang('SysNetIO'));
        $this->idsadmin->Charts->setDataTitles(array($this->idsadmin->lang('Count'),$this->idsadmin->lang('Type')));
        $this->idsadmin->Charts->setLegendDir("vertical");
        $this->idsadmin->Charts->setWidth("100%");
        $this->idsadmin->Charts->setHeight("150");
        $this->idsadmin->Charts->setId("networkIOGraph");

        $this->idsadmin->Charts->Render();


        //$this->idsadmin->html->add_to_output("</br>HERE</br>");
        //$this->idsadmin->html->add_to_output( $mygraph->pieGraph( $udata,
        //$this->idsadmin->lang('SysNetIO'),290,220,true) );

        /*
         *
         */
        $this->idsadmin->Charts = new Charts($this->idsadmin);
        $this->idsadmin->Charts->setType("PIE");
        $this->idsadmin->Charts->setData($udata1);
        $this->idsadmin->Charts->setTitle($this->idsadmin->lang('SysNetTXF'));
        $this->idsadmin->Charts->setDataTitles(array($this->idsadmin->lang('Count'),$this->idsadmin->lang('Type')));
        $this->idsadmin->Charts->setLegendDir("vertical");
        $this->idsadmin->Charts->setWidth("100%");
        $this->idsadmin->Charts->setHeight("150");
        $this->idsadmin->Charts->Render();


        //$this->idsadmin->html->add_to_output( $mygraph->pieGraph( $udata1,
        // $this->idsadmin->lang('SysNetTXF'),290,220,true) );

    } #end

    /**
     * This function display the oncofig file (i.e. centeral
     * configuration file.
     *
     */
    function showOnconfig()
    {
        require_once ROOT_PATH."lib/onconfig.php";
        require_once ROOT_PATH.'lib/feature.php';

        require_once ROOT_PATH."lib/gentab.php";
        $tab = new gentab($this->idsadmin);

        $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('ServerConfig'));

        $dynamic_list = "'" . implode("','", onconfig::get_dynamic_onconfig_params($this->idsadmin)) . "'";
        
        $report_mode = (isset($this->idsadmin->in["runReports"]) || isset($this->idsadmin->in["reportMode"]));

        $allOption = "";
        $basicOption = "";
        $dynamicOption = "";
        $recommendOption = "";
        
        if ((isset($this->idsadmin->in['show']) && strcmp($this->idsadmin->in['show'],'ShowAll')==0) || $report_mode)
        {
            // "ShowAll"
            $allOption = "selected='selected'";

            // Use cf_flags column to filter out unsupported ('0x1'), undocumented ('0x1000'), 
            // and discontinued ('0x4000') onconfig parameters.  For the onconfig parameters 
            // that are unsupported, undocumented, or discontinued, we will display
            // them only if they do not follow the OAT recommendation.  
            $where_clause = " where bitand(cf_flags,'0x1') = 0 "
            	. "and bitand(cf_flags,'0x1000') = 0 "
            	. "and bitand(cf_flags,'0x4000') = 0 ";
            $recommend_list = $this->getOnconfigUnsupportedList();
            if (count($recommend_list) != 0) {
                $recommend_list = implode(",",$recommend_list);
                $where_clause .= " OR cf_id in ( $recommend_list )";
            }
        }
        elseif (isset($this->idsadmin->in['show']) && strcmp($this->idsadmin->in['show'],'ShowDynamic')==0)
        {
            // "ShowDynamic"
            $dynamicOption = "selected='selected'";
            
            // For Informix versions >= 12.10, we can use the cf_flags column to determine
            // which onconfig parameters are dynamic ('0x8000').  For Informix verisons
            // < 12.10, this information is not stored in a system table, and therefore we
            // have this info stored in OAT (here, in the $dynamic_list variable).
            if (Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin))
            {
            	$where_clause = "where bitand(cf_flags,'0x8000') > 0";
            } else {
            	$where_clause = " where cf_name in ( $dynamic_list )";
            }            
            
            // Also remove unsupported, undocumented and discontinued onconfig parameters from the query.
            $where_clause .= " and bitand(cf_flags,'0x1') = 0 "
            		. "and bitand(cf_flags,'0x1000') = 0 "
            		. "and bitand(cf_flags,'0x4000') = 0 ";
        }
        elseif (isset($this->idsadmin->in['show']) && strcmp($this->idsadmin->in['show'],'ShowRecommend')==0)
        {
            // "ShowRecommend"
            $recommendOption = "selected='selected'";
            // If querying for only onconfig parameters recommendations,
            // which are not being followed, append that onto the query's where clause.
            $recommend_list = $this->getOnconfigRecommendNonComplianceList();
            if (count($recommend_list) == 0) {
                // no non-compliant recommendations, so make query return no rows
                $recommend_list = null;
                $where_clause = " where cf_id in ( -1 )";
            } else {
                $recommend_list = implode(",",$recommend_list);
                $where_clause = " where cf_id in ( $recommend_list )";
            }
        } else {
            // "ShowBasic"
            $basicOption = "selected='selected'";
            $basic_list = "'" . implode("','", onconfig::get_basic_onconfig_params($this->idsadmin)) . "'";
            $where_clause = " where cf_name in ( $basic_list )";
        }

        // If not in report mode, give user a drop-down box with
        // options to view all onconfig parameters or only dynamic ones
        if (!$report_mode)
        {
            $HTML=<<<END
            <table width="100%" border="0">
            <tr>
            <th align="center" colspan="4">
            <form method="get" action="index.php" name="onconfigoption">
            {$this->idsadmin->lang("OnconfigOption")}
            <input type="hidden" name="act" value="onstat" />
            <input type="hidden" name="do"  value="config" />
            <select name="show" onchange="onconfigoption.submit()">
            <option value="ShowAll" $allOption>{$this->idsadmin->lang("ShowAll")}</option>
            <option value="ShowBasic" $basicOption>{$this->idsadmin->lang("ShowBasic")}</option>
            <option value="ShowDynamic" $dynamicOption>{$this->idsadmin->lang("ShowDynamic")}</option>
            <option value="ShowRecommend" $recommendOption>{$this->idsadmin->lang("ShowRecommend")}</option>
            </select>
            </form>
            </th>
            </tr>
            <tr>
            <td align="center" colspan="4">
            {$this->idsadmin->lang("RecommendationMessage")}
            </td>
            </tr>
            </table>
END;
            $this->idsadmin->html->add_to_output($HTML);
        } else {
        	$this->idsadmin->setCurrMenuItem("Reports");
        }
        
        // For Informix 12.10 and above, we can use the cf_flags column to determine
        // which onconfig parameters are dynamic ('0x8000').  For Informix verisons  
        // < 12.10, this information is not stored in a system table, and therefore we 
        // have this info stored in the $dynamic_list variable.
        require_once 'lib/feature.php';
        if (Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin))
        {
        	$sql_dynamic_case = "bitand(cf_flags,'0x8000') > 0";
        } else {
        	$sql_dynamic_case = "cf_name in ($dynamic_list)";
        }
        
        $qry =  "select " .
        	" trim(cf_name) as name, " .
        	" trim(cf_effective) as effective, " .
        	" case " .
        	"   when ($sql_dynamic_case)" .
        	"     then 'Dynamic' ".
        	"   else " .
        	"     'Not Dynamic' " .
        	" end as configurable, " .
        	" cf_id as id, " .
        	" cf_flags as flags " .
        	" from syscfgtab $where_clause" .
        	" order by name";

        $qrycnt = "SELECT count(*) from syscfgtab $where_clause";
        
        $tab->display_tab_by_page($this->idsadmin->lang("OnconfigFile"),
        array(
        "name" => $this->idsadmin->lang("ParamName"),
        "effective" => $this->idsadmin->lang("Value"),
        "configurable" => $this->idsadmin->lang("Configurable")
        ),
        $qry,
        $qrycnt,
        NULL,
        "template_gentab_onconfig.php");

    } #end

    /**
     * This function returns a list of parameter ids for those
     * onconfig parameters that do not comply with OAT's recommendations.
     */
    function getOnconfigRecommendNonComplianceList()
    {
        require_once ROOT_PATH."lib/onconfig_param.php";
        $recommend_list = Array();
        $dbsysmaster= $this->idsadmin->get_database("sysmaster");
        $qry = "SELECT cf_id as id, "
        ." cf_name as name, "
        ." cf_effective as value, "
        ." cf_flags as flags "
        ." FROM syscfgtab";
        $stmt = $dbsysmaster->query( $qry );
        while ($res = $stmt->fetch(PDO::FETCH_ASSOC) )
        {
            $onconfig_param = new onconfig_param($res['ID'],$res['NAME'],$res['VALUE'],$res['FLAGS'],$this->idsadmin);
            $compliance = $onconfig_param->checkRecommendation();
            if (!is_null($compliance) && !$compliance) {
                $recommend_list[] = $res['ID'];
            }
        }
        return $recommend_list;
    }

    /**
     * For unsupported onconfig parameters (cf_flags=1), we will only display
     * them if they do not comply with OAT's recommendations.
     *
     * This function returns a list of parameter ids for those onconfig
     * parameters with cf_flags=1 AND that do not comply with OAT's recommendations.
     */
    function getOnconfigUnsupportedList()
    {
        require_once ROOT_PATH."lib/onconfig_param.php";
        $recommend_list = Array();
        $dbsysmaster= $this->idsadmin->get_database("sysmaster");
        $qry = "SELECT cf_id as id, "
        ." cf_name as name, "
        ." cf_effective as value, "
        ." cf_flags as flags "
        ." FROM syscfgtab"
        ." where bitand(cf_flags,'0x1') = 0 "
        ."and bitand(cf_flags,'0x1000') = 0 "
        ."and bitand(cf_flags,'0x4000') = 0 ";
        
        $stmt = $dbsysmaster->query( $qry );
        while ($res = $stmt->fetch(PDO::FETCH_ASSOC) )
        {
            $onconfig_param = new onconfig_param($res['ID'],$res['NAME'],$res['VALUE'],$res['FLAGS'],$this->idsadmin);
            $compliance = $onconfig_param->checkRecommendation();
            if (!is_null($compliance) && !$compliance) {
                $recommend_list[] = $res['ID'];
            }
        }
        return $recommend_list;
    }


    /**
     * This function displays the details of an onconfig parameter.
     * If it is a dyanamic onconfig parameter, this page will also
     * allow the user to edit the value.
     */
    function onconfigDetails($param_id)
    {
        require_once ROOT_PATH."lib/onconfig_param.php";

        $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('OnconfigParamDetails'));

        // if $param_id is not set, redirect to show onconfig
        // NOTE: this will not occur if user manually types "act=onstat&do=config_details"
        // in URL without the param_id=$param_id
        if (! isset($param_id))
        $this->idsadmin->html->add_to_output($this->idsadmin->template['template_global']->
        global_redirect("Onconfig","index.php?act=onstat&do=config"));

        $readonly = "";
        $disabled = "";
        $dynamic_buttons = "<input type='submit' class='button' name='save' value='{$this->idsadmin->lang("Save")}'/>" .
           "<input type='button' class='button' name='cancel' value='{$this->idsadmin->lang("Cancel")}' onclick='history.back()'/>";

        if ( $this->idsadmin->isreadonly() )
        {
            $readonly="READONLY";
            $disabled="DISABLED";
            $dynamic_buttons="<input type='button' class='button' name='cancel' value='{$this->idsadmin->lang("Back")}' onclick='history.back()'/>";
        }

        $dbsysmaster= $this->idsadmin->get_database("sysmaster");

        $qry = "SELECT cf_id as id, "
        ." cf_name as name, "
        ." cf_effective as effective, "
        ." cf_flags as flags "
        ." FROM syscfgtab"
        ." WHERE cf_id='$param_id'";

        $stmt = $dbsysmaster->query( $qry );
        if (($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
        {
            $this->idsadmin->error("{$this->idsadmin->lang('OnconfigParamError')} {$param_id}");
            return;
        }

        // create config_param object to represent this parameter
        $param_name = trim($res['NAME']);
        $onconfigParam = new onconfig_param(
        $param_id,
        $param_name,
        $res['EFFECTIVE'], 
        $res['FLAGS'],
        $this->idsadmin);

        // If save of onconfig parameter value has failed because
        // the user entered an invalid value, redisplay the value
        // entered by user, instead of the one in the database
        if (isset($this->idsadmin->in['param_value']))
        {
            $onconfigParam->setValue($this->idsadmin->in['param_value']);
            if (isset($this->idsadmin->in['save_option']) &&
            strcmp($this->idsadmin->in['save_option'], 'wf') == 0)
            {
                $wf="SELECTED";
            }
        }

        // Retreive and pass along any URL options that indicate the table position in the onconfig
        // table before drilling down, so that we can return the user to the same position.
        $url_options = "";
        if(isset($this->idsadmin->in['show']))
        {
            $url_options .= "&amp;show={$this->idsadmin->in['show']}";
        }
        if(isset($this->idsadmin->in['pos']))
        {
            $url_options .= "&amp;pos={$this->idsadmin->in['pos']}";
        }
        if(isset($this->idsadmin->in['orderby']))
        {
            $url_options .= "&amp;orderby={$this->idsadmin->in['orderby']}";
        }
        if(isset($this->idsadmin->in['orderway']))
        {
            $url_options .= "&amp;orderway={$this->idsadmin->in['orderway']}";
        }
        if(isset($this->idsadmin->in['perpage']))
        {
            $url_options .= "&amp;perpage={$this->idsadmin->in['perpage']}";
        }


        if ($onconfigParam->isDynamic() && !$this->idsadmin->isreadonly())
        {
            $HTML="<form method=\"post\" action=\"index.php?act=onstat&amp;do=save_config{$url_options}\">"
            . "<input type='hidden'  name=\"param_id\" value=\"$param_id\"/>"
            . "<input type='hidden'  name=\"param_name\" value=\"$param_name\"/>"
            . "<input type='hidden'  name=\"param_flags\" value=\"{$res['FLAGS']}\"/>";
        } else {
            $HTML="<form method=\"post\" action=\"index.php?act=onstat&do=config{$url_options}\">";
        }
        $HTML.=<<<END

        <table class="onconfig" align="center" cellpadding="2" cellspacing="5">
        <tr>
        <td class="tblheader" colspan="2" align="center">{$this->idsadmin->lang("OnconfigParamDetails")}</td>
        </tr>
        <tr>
        <th>{$this->idsadmin->lang("ParamName")}</th>
        <td>$param_name</td>
        </tr>
        <tr>
        <th>{$this->idsadmin->lang("Description")}</th>
        <td>{$onconfigParam->getDescription()}</td>
</tr>
END;
        if (!is_null($onconfigParam->getType()))
        {
            $HTML.="<tr><th>{$this->idsadmin->lang("ParamType")}</th>".
    	    "<td>{$onconfigParam->getType()}</td></tr>";
        }
        if (!is_null($onconfigParam->getMin()))
        {
            $HTML.="<tr><th>{$this->idsadmin->lang("MinValue")}</th>".
    	    "<td>{$onconfigParam->getMin()}</td></tr>";
        }
        if (!is_null($onconfigParam->getMax()))
        {
            $HTML.="<tr><th>{$this->idsadmin->lang("MaxValue")}</th>".
    	    "<td>{$onconfigParam->getMax()}</td></tr>";
        }
        if (!is_null($onconfigParam->getValueSet()))
        {
            $val_list = implode(', ', $onconfigParam->getValueSet());
            $HTML.="<tr><th>{$this->idsadmin->lang("PossibleValues")}</th>".
    	    "<td>$val_list</td></tr>";
        }
        $recommend_check = $onconfigParam->checkRecommendation();
        if (!is_null($recommend_check))
        {
            $HTML .= "<tr><th>{$this->idsadmin->lang("Recommendation")}</th>";
            $HTML .= "<td>";
            $HTML .= "{$onconfigParam->getRecommendation()}<br/>";
            $HTML.="</td></tr>";
        }

        if ($onconfigParam->isDynamic())
        {
            // For Dynamic Onconfig Params

            $HTML .= <<<END
            <tr>
            <th>{$this->idsadmin->lang("Value")}</th>
<td>
END;
            if ($onconfigParam->getType() == onconfig::BOOLEAN)
            {
                $off = ($onconfigParam->getValue()==0)? "SELECTED":"";
                $on = ($onconfigParam->getValue()==1)? "SELECTED":"";

                $HTML.=<<<END
                <select $disabled name="param_value">
                <option value="0" $off>0 (OFF)</option>
                <option value="1" $on>1 (ON)</option>
</select>
END;
            }
            else
            {
                $HTML.="<input $readonly type='text' name=\"param_value\" value=\"{$onconfigParam->getValue()}\"/>";
            }
            if (!is_null($recommend_check) && !$recommend_check)
            {
                $HTML .= "<br/><em><strong><font color='red'>" . $this->idsadmin->lang("Warning") . ":</font> " .
                $this->idsadmin->lang("Recommendation_NO") . "</strong></em>";
            }
            $HTML.="</td></tr>";

            if (!$this->idsadmin->isreadonly() &&
            !onconfig::is_ER_config_parameter($onconfigParam->getName()))
            {
                $HTML.=<<<END

                <tr>
                <th>{$this->idsadmin->lang("SaveOption")}</th>
                <td>
                <select name="save_option">
                <option value="wm">{$this->idsadmin->lang("SaveMem")}</option>
                <option value="wf" $wf>{$this->idsadmin->lang("SaveMemFile")}</option>
</select>
</td>
</tr>
END;
            }
            $HTML.=<<<END
            <tr></tr>
            <tr align="center">
            <td colspan="2">
            $dynamic_buttons
</td>
</tr>
END;
        }
        else
        {
            // For non-dynamic onconfig params
            $HTML.=<<<END
            <tr>
            <th>{$this->idsadmin->lang("Value")}</th>
            <td>{$onconfigParam->getValue()}
END;
            if (!is_null($recommend_check) && !$recommend_check)
            {
                $HTML .= "<br/><em><strong><font color='red'>{$this->idsadmin->lang("Warning")}:</font> " .
                          "{$this->idsadmin->lang("Recommendation_NO")}</strong></em>";
            }
            $HTML.=<<<END
            </td></tr>
            <tr>
            <th colspan="2">{$this->idsadmin->lang("ParamNotDynamic")}</th>
            </tr>
            <tr align="center">
            <td colspan="2">
            <input type="button" class="button" name="back" value='{$this->idsadmin->lang("Back")}' onclick='history.back()'/></td>
</tr>
END;
        }
        $HTML .= <<<END
</table>
</form>

END;
        $this->idsadmin->html->add_to_output($HTML);

    } #end onconfigDetails

    /*
     * This functions saves user modifications of dynamic
     * onconfig parameters
     */
    function saveOnconfigParam()
    {
        if ($this->idsadmin->isreadonly())
        {
            $this->idsadmin->fatal_error($this->idsadmin->lang('nopermission'));
        }

        require_once ROOT_PATH."lib/onconfig_param.php";

        // create onconfig_parameter object with the new value
        $onconfigParam = new onconfig_param(
        $this->idsadmin->in['param_id'],
        $this->idsadmin->in['param_name'],
        $this->idsadmin->in['param_value'],
        $this->idsadmin->in['param_flags'],
        $this->idsadmin);

        // Check constraints to make sure the value entered by the user
        // if valid for that onconfig param
        $check = $onconfigParam->checkValue();
        if ($check != "VALID")
        {
            // if check failed, print error and return user to onconfigDetails page
            $this->idsadmin->error($check);
            $this->onconfigDetails($onconfigParam->getId());
            return;
        }

        // Update the value on the server
        $status_msg = "";
        $sql = "";
        $dbadmin= $this->idsadmin->get_database("sysadmin");
        if (onconfig::is_ER_config_parameter($onconfigParam->getName()))
        {
            // For ER onconfig parameters, use the 'cdr change onconfig' command
            $sql = "execute function task('cdr change onconfig', "
            . "'\"{$onconfigParam->getName()} {$onconfigParam->getValue()}\"')";
            $status_msg = "Update {$onconfigParam->getName()}... ";
        } else {
            // For all others, use the 'onmode -wm/wf' command
            $sql = "execute function task('onmode', '{$this->idsadmin->in['save_option']}', " .
    	    	"'{$onconfigParam->getName()}={$onconfigParam->getValue()}')";
        }

        /**
         * Special case: DS_NONPDQ_QUERY_MEM
         *
         * It is a server requirement that DS_TOTAL_MEMORY must be greater than or equal to
         * 4 times the size of DS_NONPDQ_QUERY_MEM.  Therefore, if a user is trying to
         * increase DS_NONPDQ_QUERY_MEM to a value that is more that 1/4 the size of
         * DS_TOTAL_MEMORY, then automatically also increase DS_TOTAL_MEMORY to the
         * necessary value.  The goal is to keep it simple for the user.
         */
        if (strcasecmp($onconfigParam->getName(),"DS_NONPDQ_QUERY_MEM") == 0)
        {
            // Query sysmaster to find the current value of DS_TOTAL_MEMORY
            $dbsysmaster = $this->idsadmin->get_database("sysmaster");
            $qry = "SELECT cf_effective FROM syscfgtab" .
                " WHERE cf_name like 'DS_TOTAL_MEMORY'";
            $stmt = $dbsysmaster->query( $qry );
            while ($res = $stmt->fetch(PDO::FETCH_ASSOC))
            {
                $ds_total_memory = $res['CF_EFFECTIVE'];
            }

            // If DS_TOTAL_MEMORY is less than 4 times the new value for DS_NONPDQ_QUERY_MEM,
            // increase the value of DS_TOTAL_MEMORY first.
            if ($ds_total_memory < (4 * $onconfigParam->getValue()) )
            {
                $ds_total_memory_sql = "execute function task('onmode', " .
                    "'{$this->idsadmin->in['save_option']}', " .
                    "'DS_TOTAL_MEMORY=" . (4 * $onconfigParam->getValue()) . "')";
                $stmt = $dbadmin->query($ds_total_memory_sql);
                $res = $stmt->fetch(PDO::FETCH_ASSOC);
                $status_msg .= $res[''] . "  ";
            }
        }

        $stmt = $dbadmin->query($sql);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        $status_msg .= $res[''];
        $this->idsadmin->status($status_msg);
        $stmt->closeCursor();

        // if save successul, return to onconfig list page
        $this->showOnconfig();
    }

    /**
     * Show the memory pools
     *
     */
    function showpoollst()
    {
        require_once ROOT_PATH."lib/gentab.php";
        $tab = new gentab($this->idsadmin);

        $qrycnt = "SELECT count(*) from syspoollst where po_id>0";
        $tab->display_tab_by_page("{$this->idsadmin->lang('MemPoolList')}",
        array(
        "1" => "{$this->idsadmin->lang('ID')}",
        "2" => "{$this->idsadmin->lang('Name')}",
        "3" => "{$this->idsadmin->lang('Class')}",
        "8" => "{$this->idsadmin->lang('Total')}",
        "9" => "{$this->idsadmin->lang('Free')}",
        "10" => "{$this->idsadmin->lang('Used')}",
        "7" => "{$this->idsadmin->lang('FLAGS')}",
        ),
        "select po_id, " .
        " po_name, ".
        " decode(po_class,1, 'Resident', "  .
        "2, 'Virtual' , "  .
        "3, 'Message' , "  .
        "   'Other' ) as class, ".
        " format_units(po_usedamt+po_freeamt, 'b') as total, ".
        " format_units(po_freeamt, 'b') as free, ".
        " format_units(po_usedamt,'b') as used, ".
        " po_flags, ".
        " po_usedamt + po_freeamt, ".
        " po_freeamt, ".
        " po_usedamt ".
        " from syspoollst " .
        " order by po_name",
        $qrycnt,
        NULL);

    } #end

    /**
     * Show the memory segment overview in both a graphical
     * view and a tabular view.
     *
     */
    function showseglist( )
    {
        $this->idsadmin->html->add_to_output( "<table style='width:100%;height:100%'><tr  style='width:50%;height:50%'>" );
        $this->idsadmin->html->add_to_output( "<td align='center'>" );
        $this->showSegListGraph();

        if ( Feature::isAvailable ( Feature::CHEETAH2, $this->idsadmin )  )
        {
            $this->idsadmin->html->add_to_output( "</td><td style='width:50%; height:100%'>" );
            $this->showOSMemGraph();
            $this->idsadmin->html->add_to_output( "</td></tr><tr><td colspan='2'>" );
        } else {
            $this->idsadmin->html->add_to_output( "</td></tr><tr><td>" );
        }
        $this->showSegTable();
        $this->idsadmin->html->add_to_output( "</td></tr></table>" );

    } #end default

    /**
     * Show a graphical view of the memory segments
     *
     */
    function showSegListGraph( )
    {
        $db = $this->idsadmin->get_database("sysmaster");
        require_once ROOT_PATH."lib/Charts.php";

        $sql ="SELECT sum(seg_blkused) as mUsed , " .
        " sum(seg_blkfree) as mFree, " .
        " decode(seg_class,1, 'Resident', "  .
        "2, 'Virtual' , "  .
        "3, 'Message' , "  .
        "   'Other' ) as class ".
        " FROM sysseglst   " .
        " GROUP by 3" ;

        $stmt = $db->query($sql);
        $udata = array( );
        while ($res = $stmt->fetch())
        {
            $udata[ $res['CLASS'] . " " . $this->idsadmin->lang('Allocated') ] = $res['MUSED'];
            /*
             * Only consider the free portion if we have more than two blocks free
             *   some of the segments like the resident will always have 1 or 2 blocks
             *   free, but we do not need to waste space showing these in the graph.
             */
            if ( $res['MFREE'] > 2 )
            $udata[ $res['CLASS'] . " " . $this->idsadmin->lang('Free') ] = $res['MFREE'];
        }

        $this->idsadmin->Charts = new Charts($this->idsadmin);
        $this->idsadmin->Charts->setType("PIE");
        $this->idsadmin->Charts->setData($udata);
        $this->idsadmin->Charts->setTitle($this->idsadmin->lang('MemSegUsage'));
        $this->idsadmin->Charts->setDataTitles(array($this->idsadmin->lang('Blocks'),$this->idsadmin->lang('SegmentType')));
        $this->idsadmin->Charts->setUnits($this->idsadmin->lang('blocks'));
        $this->idsadmin->Charts->setLegendDir("vertical");
        $this->idsadmin->Charts->setWidth("100%");
        $this->idsadmin->Charts->setHeight("200");
        $this->idsadmin->Charts->Render();


        //$this->idsadmin->html->add_to_output( $mygraph->pieGraph( $udata,
        // $this->idsadmin->lang('MemSegUsage'),600,300,true) );
    }

    function showOSMemGraph()
    {

        $db = $this->idsadmin->get_database("sysmaster");
        require_once ROOT_PATH."lib/Charts.php";

        $sql = "SELECT os_mem_total - os_mem_free as mUsed , "
        . " os_mem_free as mFree "
        . " FROM sysmachineinfo   " ;

        $stmt = $db->query($sql);
        $udata = array( );
        while ($res = $stmt->fetch())
        {
            $udata[ $this->idsadmin->lang('Allocated') ] = $res['MUSED'];
            $udata[  $this->idsadmin->lang('Free') ] = $res['MFREE'];
        }

        $this->idsadmin->Charts = new Charts($this->idsadmin);
        $this->idsadmin->Charts->setType("PIE");
        $this->idsadmin->Charts->setData($udata);
        $this->idsadmin->Charts->setTitle($this->idsadmin->lang('MemOSUsage'));
        $this->idsadmin->Charts->setDataTitles(array($this->idsadmin->lang('Bytes'),$this->idsadmin->lang('Usage')));
        $this->idsadmin->Charts->setUnits($this->idsadmin->lang('bytes'));
        $this->idsadmin->Charts->setLegendDir("vertical");
        $this->idsadmin->Charts->setWidth("100%");
        $this->idsadmin->Charts->setHeight("200");
        $this->idsadmin->Charts->Render();
    }

    /**
     * Show a tabular view of the shared memory segmetns
     *
     */
    function showSegTable()
    {
        require_once ROOT_PATH."lib/gentab.php";
        $tab = new gentab($this->idsadmin);

        $qrycnt = "SELECT count(*) from sysseglst";
        $tab->display_tab_by_page("{$this->idsadmin->lang('SharedMemorySegList')}",
        array(
        "1" => "{$this->idsadmin->lang('SegmentType')}",
        "7" => "{$this->idsadmin->lang('Size')}",
        "8" => "{$this->idsadmin->lang('Used')}",
        "9" => "{$this->idsadmin->lang('Free')}",
        "5" => "{$this->idsadmin->lang('Address')}",
        "6" => "{$this->idsadmin->lang('Key')}",
        ),
        "select " .
        " decode(seg_class,1, 'Resident', "  .
        "2, 'Virtual' , "  .
        "3, 'Message' , "  .
        "   'Other' ) as class, ".
        " format_units(seg_size, 'b') as size, ".
        " format_units(seg_blkused,'4') as used, ".
        " format_units(seg_blkfree,'4') as free, ".
        " seg_shmaddr, ".
        " seg_osshmkey, ".
        " seg_size, ".
        " seg_blkused, ".
        " seg_blkfree ".
        " from sysseglst " .
        " order by seg_class",
        $qrycnt,
        NULL);
    } #end


    /**
     * Show the usage of of the various sql Caches
     *
     */
    function showsqlcache()
    {
        require_once ROOT_PATH."lib/gentab.php";
        $tab = new gentab($this->idsadmin);

        $qrycnt = "SELECT count(*) from syssqlcacheprof";
        $tab->display_tab_by_page("{$this->idsadmin->lang('SQLCachesList')}",
        array(
        "1" => "{$this->idsadmin->lang('CacheName')}",
        "2" => "{$this->idsadmin->lang('Ratio')}",
        "3" => "{$this->idsadmin->lang('Hits')}",
        "4" => "{$this->idsadmin->lang('Misses')}",
        "5" => "{$this->idsadmin->lang('Removed')}",
        "10" => "{$this->idsadmin->lang('UsedMemory')}",
        "11" => "{$this->idsadmin->lang('FreeMemory')}",
        "8" => "{$this->idsadmin->lang('CacheEntries')}",
        "9" => "{$this->idsadmin->lang('InUse')}",
        ),
        "select " .
        " name, " .
        " TRUNC(decode(hits,-1,0,0,0,100*hits/(hits+misses)),2) " .
        "       ||'%' as rat, " .
        " decode(hits,-1,'0',hits::char(20)) as hits, " .
        " decode(misses,-1,'0',misses::char(20)) as misses, " .
        " decode(removed,-1,'0',removed::char(20)) as removed, " .
        " format_units(mem_used,'b') as used, " .
        " format_units(mem_free,'b') as free, " .
        " total_entries, " .
        " inuse_entries, " .
        " mem_used, " .
        " mem_free " .
        " from syssqlcacheprof " .
        " order by 1",
        $qrycnt,
        NULL);

    } #end

    /**
     * Show all database user session and basic user inforamtion
     *
     */
    function showSessions(  )
    {
        require_once ROOT_PATH."lib/gentab.php";
        $tab = new gentab($this->idsadmin);

        $qrycnt = "SELECT count(*) from sysscblst ";

        $qry = "SELECT " .
        "sid, " .
        "trim(username)||'@'||decode(length(hostname),0,'localhost',hostname)::lvarchar as usr, " .
        "pid, " .
        "dbinfo('UTC_TO_DATETIME',connected)::DATETIME MONTH TO SECOND as con, " .
        "format_units(memtotal,'b') as mtotal, " .
        "format_units(memused,'b') as mused, " .
        "memtotal, " .
        "memused " .
        "FROM sysscblst " .
        "ORDER BY 2,4" ;

        $tab->display_tab_by_page("{$this->idsadmin->lang('SessionList')}",
        array(
        "1" => "{$this->idsadmin->lang('SIDShort')}",
        "2" => "{$this->idsadmin->lang('User')}",
        "3" => "{$this->idsadmin->lang('PID')}",
        "4" => "{$this->idsadmin->lang('ConnectTime')}",
        "7" => "{$this->idsadmin->lang('TotalMemory')}",
        "8" => "{$this->idsadmin->lang('UsedMemory')}",
        ),
        $qry,
        $qrycnt,
        NULL);

    } #end


    /**
     * Show detial user information when give a database seission id
     *
     * @param integer $sid
     */
    function showUserSessions( $sid )
    {
        $db = $this->idsadmin->get_database("sysmaster");
        $qry = "SELECT " .
        "sysscblst.sid, " .
        "trim(sysscblst.username) as user, " .
        "decode(length(hostname),0,'localhost',hostname) as hostname, " .
        "sysscblst.uid, " .
        "sysscblst.gid, " .
        "sysscblst.pid, " .
        "dbinfo('UTC_TO_DATETIME',connected)::DATETIME MONTH TO SECOND as con, " .
        "format_units(memtotal,'b') as mtotal, " .
        "format_units(memused,'b') as mused, " .
        "nreads, " .
        "nwrites, " .
        "nfiles, " .
        "upf_rqlock, " .
        "upf_wtlock, " .
        "upf_deadlk, " .
        "upf_lktouts, " .
        "upf_lgrecs, " .
        "upf_isread, " .
        "upf_iswrite, " .
        "upf_isrwrite, " .
        "upf_isdelete, " .
        "upf_iscommit, " .
        "upf_isrollback, " .
        "upf_longtxs, " .
        "upf_bufreads, " .
        "upf_bufwrites, " .
        "format_units(upf_logspuse,'b') as logspuse, " .
        "format_units(upf_logspmax,'b') as logspmax, " .
        "upf_seqscans, " .
        "upf_totsorts, " .
        "upf_dsksorts, " .
        "upf_totsorts - upf_dsksorts as memsorts, " .
        "format_units(upf_srtspmax,'b') as srtspmax, " .
        "nlocks " .
        "FROM sysscblst , sysrstcb " .
        "WHERE sysscblst.address = sysrstcb.scb " .
        " AND bitval(sysrstcb.flags,'0x80000')>0 " .
        " AND sysscblst.sid = {$sid} ";

        $stmt = $db->query( $qry );
        $res = $stmt->fetch();
        if ( count( $res ) == 0 )
        {
            $this->idsadmin->html->add_to_output( "<H2> No Data </H2>" );
            return;
        }


        $res['HOSTNAME']= substr( $res['HOSTNAME'],0,strrpos($res['HOSTNAME'],'.') );

        $html=<<<END
        <table class="gentab">
        <tr>
        <td class="tblheader" align="center" colspan=8>{$res['USER']}@{$res['HOSTNAME']}</td>
        </tr>

        <tr>
        <th>{$this->idsadmin->lang('SessionID')}</th>
        <th>{$this->idsadmin->lang('UserID')}</th>
        <th>{$this->idsadmin->lang('GroupID')}</th>
        <th>{$this->idsadmin->lang('PID')}</th>
        <th>{$this->idsadmin->lang('Connected')}</th>
        <th>{$this->idsadmin->lang('OpenTables')}</th>
        <th>{$this->idsadmin->lang('TotalMemory')}</th>
        <th>{$this->idsadmin->lang('UsedMemory')}</th>
        </tr>
        <tr>
        <td>{$res['SID']}</td>
        <td>{$res['UID']}</td>
        <td>{$res['GID']}</td>
        <td>{$res['PID']}</td>
        <td>{$res['CON']}</td>
        <td>{$res['NFILES']}</td>
        <td>{$res['MTOTAL']}</td>
        <td>{$res['MUSED']}</td>
        </tr>

        <tr>
        <th>{$this->idsadmin->lang('Locks')}</th>
        <th>{$this->idsadmin->lang('LockRequests')}</th>
        <th>{$this->idsadmin->lang('LockWaits')}</th>
        <th>{$this->idsadmin->lang('DeadLocks')}</th>
        <th>{$this->idsadmin->lang('LockTimeouts')}</th>
        <th>{$this->idsadmin->lang('LogRecords')}</th>
        <th>{$this->idsadmin->lang('LogSpace')}</th>
        <th>{$this->idsadmin->lang('MaxLogSpace')}</th>
        </tr>
        <tr>
        <td>{$res['NLOCKS']}</td>
        <td>{$res['UPF_RQLOCK']}</td>
        <td>{$res['UPF_WTLOCK']}</td>
        <td>{$res['UPF_DEADLK']}</td>
        <td>{$res['UPF_LKTOUTS']}</td>
        <td>{$res['UPF_LGRECS']}</td>
        <td>{$res['LOGSPUSE']}</td>
        <td>{$res['LOGSPMAX']}</td>
        </tr>

        <tr>
        <th>{$this->idsadmin->lang('RowsProcessed')}</th>
        <th>{$this->idsadmin->lang('RowsInserted')}</th>
        <th>{$this->idsadmin->lang('RowsUpdated')}</th>
        <th>{$this->idsadmin->lang('RowsDeleted')}</th>
        <th>{$this->idsadmin->lang('Commits')}</th>
        <th>{$this->idsadmin->lang('Rollbacks')}</th>
        <th>{$this->idsadmin->lang('LongTXs')}</th>
        <th>{$this->idsadmin->lang('SequentialScans')}</th>
        </tr>
        <tr>
        <td>{$res['NREADS']}</td>
        <td>{$res['NWRITES']}</td>
        <td>{$res['UPF_RQLOCK']}</td>
        <td>{$res['UPF_WTLOCK']}</td>
        <td>{$res['UPF_DEADLK']}</td>
        <td>{$res['UPF_LKTOUTS']}</td>
        <td>{$res['UPF_LONGTXS']}</td>
        <td>{$res['UPF_SEQSCANS']}</td>
        </tr>

        <tr>
        <th>{$this->idsadmin->lang('FGReads')}</th>
        <th>{$this->idsadmin->lang('FGWrites')}</th>
        <th>{$this->idsadmin->lang('BufferReads')}</th>
        <th>{$this->idsadmin->lang('BufferWrites')}</th>
        <th>{$this->idsadmin->lang('Sorts')}</th>
        <th>{$this->idsadmin->lang('MemorySorts')}</th>
        <th>{$this->idsadmin->lang('DiskSorts')}</th>
        <th>{$this->idsadmin->lang('LargestSorts')}</th>
        </tr>
        <tr>
        <td>{$res['NREADS']}</td>
        <td>{$res['NWRITES']}</td>
        <td>{$res['UPF_BUFREADS']}</td>
        <td>{$res['UPF_BUFWRITES']}</td>
        <td>{$res['UPF_TOTSORTS']}</td>
        <td>{$res['MEMSORTS']}</td>
        <td>{$res['UPF_DSKSORTS']}</td>
        <td>{$res['SRTSPMAX']}</td>
</tr>

</table>

END;
        $this->idsadmin->html->add_to_output( $html );

    } #end


    /**
     * Show the table profile information.
     *
     */
    function showTableProfile(  )
    {
        require_once ROOT_PATH."lib/gentab.php";
        $tab = new gentab($this->idsadmin);

        $qrycnt = "SELECT count(unique tablock) as cnt " .
        " from sysptntab " .
        " WHERE partnum > 0" ;

        $qry = "SELECT " .
        "trim(dbsname)||'.'||trim(tabname) as name, " .
        "sum(pf_rqlock) as lockreq, " .
        "sum(pf_wtlock + pf_deadlk+pf_lktouts) as lockwaits, " .
        "sum(pf_bfcread) as rdcache, " .
        "sum(pf_bfcwrite) as wrcache, " .
        "sum(pf_dskreads) as dskreads, " .
        "sum(pf_dskwrites) as dskwrites, " .
        "sum(pf_isread)  as isread, " .
        "sum(pf_iswrite) as iswrite, " .
        "sum(pf_isrwrite) as isrwrite, " .
        "sum(pf_isdelete) as isdelete, " .
        "sum(pf_seqscans) as seqscans " .
        "FROM sysptntab, systabnames  " .
        "WHERE sysptntab.tablock = systabnames.partnum " .
        "GROUP BY 1 " .
        "ORDER BY 1" ;

        $tab->display_tab_by_page("{$this->idsadmin->lang('TableProfileInfo')}",
        array(
        "1" => "{$this->idsadmin->lang('TableName')}",
        "2" => "{$this->idsadmin->lang('Locks')}",
        "3" => "{$this->idsadmin->lang('LockWaits')}",
        "4" => "{$this->idsadmin->lang('ReadCache')}",
        "5" => "{$this->idsadmin->lang('WriteCache')}",
        "6" => "{$this->idsadmin->lang('DiskReads')}",
        "7" => "{$this->idsadmin->lang('DiskWrites')}",
        "8" => "{$this->idsadmin->lang('RowsProcessed')}",
        "9" => "{$this->idsadmin->lang('RowsInserted')}",
        "10" => "{$this->idsadmin->lang('RowsUpdated')}",
        "11" => "{$this->idsadmin->lang('RowsDeleted')}",
        "12" => "{$this->idsadmin->lang('SequentialScans')}",
        ),
        $qry,
        $qrycnt,
        NULL);

    } #end

    /**
     * Show when the last backups have occurred.
     *
     */
    function showbackups()
    {

        require_once ROOT_PATH."lib/gentab.php";
        $tab = new gentab($this->idsadmin);

        $qrycnt = "SELECT count(*) from sysdbstab " .
        "WHERE bitval(flags,'0x2000')==0 " ;

        $sql = "SELECT " .
        "dbsnum," .
        "name," .
        "decode(level0,0,'NEVER',dbinfo('UTC_TO_DATETIME',level0)::char(40)) as level0,".
        "decode(logid0,0,'NEVER',logid0||':'||(logpos0)) as logpos0, " .
        "decode(level1,0,'NEVER',dbinfo('UTC_TO_DATETIME',level1)::char(40)) as level1,".
        "decode(logid1,0,'NEVER', logid1||':'||(logpos1)) as logpos1, " .
        "decode(level2,0,'NEVER',dbinfo('UTC_TO_DATETIME',level2)::char(40)) as level2,".
        "decode(logid2,0,'NEVER', logid2||':'||(logpos2)) as logpos2 " .
        "FROM sysdbstab " .
        "WHERE bitval(flags,'0x2000')==0 " .
        "ORDER BY 1";

        $tab->display_tab_by_page("{$this->idsadmin->lang('BackupReport')}",
        array(
        "1" => "{$this->idsadmin->lang('Number')}",
        "2" => "{$this->idsadmin->lang('Name')}",
        "3" => "{$this->idsadmin->lang('Level0')}",
        "4" => "{$this->idsadmin->lang('Position')}",
        "5" => "{$this->idsadmin->lang('Level1')}",
        "6" => "{$this->idsadmin->lang('Position')}",
        "7" => "{$this->idsadmin->lang('Level2')}",
        "8" => "{$this->idsadmin->lang('Position')}",
        ),
        $sql,
        $qrycnt,
        NULL);

    } #end

    function showComputerInfo()
    {

        if ( !Feature::isAvailable ( Feature::CHEETAH2, $this->idsadmin )  )
        {
            $this->idsadmin->load_lang("global");
            $this->idsadmin->error($this->idsadmin->lang('featureNotSupported', array("11.50.xC1")));
            return;
        }


        $db = $this->idsadmin->get_database("sysmaster");

        $sql = "SELECT  "
        . " format_units(os_mem_total/1024,'k') as mem_total,  "
        . " format_units(os_mem_free/1024,'k') as mem_free,  "
        . " *  "
        . " FROM sysmaster:sysmachineinfo   "
        ;
        $HTML="<table>";
        $stmt = $db->query($sql);
        if ($res = $stmt->fetch())
        {
            $HTML.="<tr><th colspan='2' align='center'>{$this->idsadmin->lang('ComputerInfo')}</th></tr>"	;

            if ( isset($res['OS_NODENAME']) )
            $HTML.="<tr><td>{$this->idsadmin->lang('HostName')} </td><td>{$res['OS_NODENAME']} </td></tr> ";
            if ( isset($res['OS_NAME'])  )
            $HTML.="<tr><td>{$this->idsadmin->lang('OSName')} </td><td>{$res['OS_NAME']} </td></tr> ";
            if ( isset($res['OS_RELEASE'])  )
            $HTML.="<tr><td>{$this->idsadmin->lang('OSRel')} </td><td>{$res['OS_RELEASE']}  </td></tr> ";
            if (  isset($res['OS_VERSION']) )
            $HTML.="<tr><td>{$this->idsadmin->lang('OSVer')} </td><td> {$res['OS_VERSION']} </td></tr> ";
            if ( isset($res['OS_MACHINE']) )
            $HTML.="<tr><td>{$this->idsadmin->lang('CPUType')}</td><td> {$res['OS_MACHINE']}  </td></tr> ";
            if ( isset($res['MEM_TOTAL']) )
            $HTML.="<tr><td>{$this->idsadmin->lang('TotalMem')}</td><td> {$res['MEM_TOTAL']}  </td></tr> ";
            if (  isset($res['MEM_FREE']) )
            $HTML.="<tr><td>{$this->idsadmin->lang('TotalFreeMem')}</td><td>{$res['MEM_FREE']} </td></tr> ";
            if (  isset($res['OS_NUM_OLPROCS']) && isset($res['OS_NUM_OLPROCS']) )
            if ( $res['OS_NUM_OLPROCS'] == $res['OS_NUM_OLPROCS'] )
            $HTML.="<tr><td>{$this->idsadmin->lang('CPUs')}</td><td>{$res['OS_NUM_PROCS']} </td></tr> ";
            else
            {
                $HTML.="<tr><td>{$this->idsadmin->lang('OnlineCPUs')}</td><td>{$res['OS_NUM_OLPROCS']} </td></tr> ";
                $HTML.="<tr><td>{$this->idsadmin->lang('TotalCPUs')}</td><td>{$res['OS_NUM_PROCS']} </td></tr> ";
            }
            if ( isset($res['OS_PAGESIZE']) )
            $HTML.="<tr><td>{$this->idsadmin->lang('OSPageSize')}</td><td> {$res['OS_PAGESIZE']}  </td></tr> ";
            if ( isset($res['OS_OPEN_FILE_LIM']) )
            $HTML.="<tr><td>{$this->idsadmin->lang('MaxFilesPerProc')}</td><td> {$res['OS_OPEN_FILE_LIM']}  </td></tr> ";

            $HTML.="<tr><th colspan='2' align='center'>{$this->idsadmin->lang('SharedMemInfo')}</th></tr>"	;
            foreach( $res as $index => $val )
            {
                if ( strncmp( $index, "OS_SHM",6)!=0 )
                continue;
                $HTML.="<tr><td> " . ucfirst(strtolower(substr($index,3))) ."</td><td>{$val}</td></tr> ";
            }

            $HTML.="<tr><th colspan='2' align='center'>{$this->idsadmin->lang('SemaphoreInfo')}</th></tr>"	;
            foreach( $res as $index => $val )
            {
                if ( strncmp( $index, "OS_SEM",6)!=0 )
                continue;
                $HTML.="<tr><td>" . ucfirst(strtolower(substr($index,3))) ."</td><td>{$val}</td></tr> ";
            }
            /*
             foreach( $res as $index => $val )
             {
             $HTML.="<tr><td> {$index}</td><td>{$val}</td></tr> ";
             }
             */
        }
        $HTML.="</table>";

        $this->idsadmin->html->add_to_output( $HTML );



    }

    function showResourceHistory()
    {

        if ( !Feature::isAvailable ( Feature::CHEETAH2, $this->idsadmin )  )
        {
            $this->idsadmin->load_lang("global");
            $this->idsadmin->error($this->idsadmin->lang('featureNotSupported', array("11.50.xC1")));
            return;
        }

        require_once ROOT_PATH."lib/gentab.php";
        $tab = new gentab($this->idsadmin);

        $sql = "SELECT 'Maximum', " .
        "MAX(max_conns) as max_conns,".
        "MAX(max_cpu_vps) as max_cpu_vps,".
        "MAX(max_vps)as max_vps, " .
        "format_units(MAX(total_size),'M') as t_size, " .
        "format_units(MAX(total_size_used),'M') as tu_size,".
        "format_units(MAX(max_memory),'M') as m_memory, " .
        "format_units(MAX(max_memory_used),'M') as m_memory_used, " .
        "MAX(total_size ) as total_size_used, " .
        "MAX(total_size_used) as total_size_used, " .
        "MAX(max_memory) as max_memory, " .
        "MAX(max_memory_used) max_memory_used " .
        "FROM sysmaster:syslicenseinfo " .
        " UNION " .
        "SELECT 'Minimum', " .
        "MIN(max_conns) as max_conns,".
        "MIN(max_cpu_vps) as max_cpu_vps,".
        "MIN(max_vps)as max_vps, " .
        "format_units(MIN(total_size),'M') as t_size, " .
        "format_units(MIN(total_size_used),'M') as tu_size,".
        "format_units(MIN(max_memory),'M') as m_memory, " .
        "format_units(MIN(max_memory_used),'M') as m_memory_used, " .
        "MIN(total_size ) as total_size_used, " .
        "MIN(total_size_used) as total_size_used, " .
        "MIN(max_memory) as max_memory, " .
        "MIN(max_memory_used) max_memory_used " .
        "FROM sysmaster:syslicenseinfo " .
        " UNION " .
        "SELECT 'Average', " .
        "ROUND(AVG(max_conns),0) as max_conns,".
        "ROUND(AVG(max_cpu_vps),0) as max_cpu_vps,".
        "ROUND(AVG(max_vps),0)as max_vps, " .
        "format_units(TRUNC( AVG(total_size),4),'M') as t_size, " .
        "format_units(TRUNC (AVG(total_size_used),4),'M') as tu_size,".
        "format_units(TRUNC (AVG(max_memory),4),'M') as m_memory, " .
        "format_units(TRUNC (AVG(max_memory_used),4),'M') as m_memory_used, " .
        "TRUNC(AVG(total_size ),4) as total_size_used, " .
        "TRUNC(AVG(total_size_used),4) as total_size_used, " .
        "TRUNC(AVG(max_memory),4) as max_memory, " .
        "TRUNC(AVG(max_memory_used),4) max_memory_used " .
        "FROM sysmaster:syslicenseinfo " .
        "ORDER BY 1 "

        ;

        $this->idsadmin->in['fullrpt']=on;
        $tab->display_tab_by_page("{$this->idsadmin->lang('ResourceSummaryReport')}",
        array(
        "1" => "",
        "2" => "{$this->idsadmin->lang('MaxConns')}",
        "3" => "{$this->idsadmin->lang('MaxCPUVPS')}",
        "4" => "{$this->idsadmin->lang('MAXVPS')}",
        "9" => "{$this->idsadmin->lang('DiskSize')}",
        "10" => "{$this->idsadmin->lang('DiskUsed')}",
        "11" => "{$this->idsadmin->lang('Memory')}",
        "12" => "{$this->idsadmin->lang('MemoryUsed')}
        "
        ),
        $sql,
        3,
        3);

        $this->idsadmin->html->add_to_output( "<BR>" );

        $qrycnt = "SELECT count(*) from sysmaster:syslicenseinfo " .
        			"WHERE 1=1" ;

        $sql = "SELECT " .
        "(MDY(1,1,year) + (week*7) UNITS DAY)::DATE as edate," .
        "max_conns,".
        "max_cpu_vps,".
        "max_vps, " .
        "format_units(total_size,'M') as t_size, " .
        "format_units(total_size_used,'M') as tu_size,".
        "format_units(max_memory,'M') as m_memory, " .
        "format_units(max_memory_used,'M') as m_memory_used, " .
        "total_size, " .
        "total_size_used, " .
        "max_memory, " .
        "max_memory_used " .
        "FROM sysmaster:syslicenseinfo " .
        "ORDER BY 1 DESC";

        if (!isset($this->idsadmin->in["runReports"])) {
            unset($this->idsadmin->in['fullrpt']);
        }

        $tab->display_tab_by_page("{$this->idsadmin->lang('ResourceUsageReport')}",
        array(
        "1" => "{$this->idsadmin->lang('ForWeekEnding')}",
        "2" => "{$this->idsadmin->lang('MaxConns')}",
        "3" => "{$this->idsadmin->lang('MaxCPUVPS')}",
        "4" => "{$this->idsadmin->lang('MAXVPS')}",
        "9" => "{$this->idsadmin->lang('DiskSize')}",
        "10" => "{$this->idsadmin->lang('DiskUsed')}",
        "11" => "{$this->idsadmin->lang('Memory')}",
        "12" => "{$this->idsadmin->lang('MemoryUsed')}
        "
        ),
        $sql,
        $qrycnt,
        NULL);

        $this->idsadmin->html->add_to_output( $HTML );

    }

    function showWaitingSessions()
    {
        require_once ROOT_PATH."lib/gentab.php";
        $tab = new gentab($this->idsadmin);

        $this->idsadmin->html->add_to_output( "<BR>" );

        $qrycnt = "SELECT count(*) from sysmaster:sysrstcb R "
        . "WHERE r.lkwait <> 0 "
        . "OR r.bfwait <> 0 "
        . "OR bitand(R.flags, '0x5010')>0"
        ;

        $sql =     "SELECT "
        . "R.username wait_user,R.sid  wait_sid,  "
        . "O.username owner_user,O.sid owner_sid,  "
        . " 'LOCK-'||tx.txt OWNER_LOCK_TYPE, "
        . "TRIM(T.dbsname)||'.'||TRIM(T.owner)||'.'||TRIM(T.tabname) wtable, "
        . "'ROWID '||decode(L.rowidr,0,L.rowidn,L.rowidr)  wrowid, "
        . "(CURRENT - dbinfo('UTC_TO_DATETIME', grtime))::char(40) as LOCK_WAIT_TIME "
        . "FROM sysmaster:sysrstcb R, sysmaster:syslcktab L, sysmaster:systabnames T, "
        . "sysmaster:sysrstcb O, sysmaster:flags_text TX "
        . "WHERE R.lkwait  <> 0 "
        . "AND   R.lkwait  =  L.address "
        . "AND   T.partnum =  L.partnum "
        . "AND   O.txp     =  L.owner "
        . "and   TX.tabname = 'syslcktab' "
        . "AND   TX.flags   = R.lkwttype "
        /*	. "UNION  "
         . "SELECT "
         . "R.username wait_user,R.sid  wait_sid,  "
         . "O.username owner_user,O.sid owner_sid,  "
         . "'BUFFER-'||decode( R.bfwtflag, 16, 'SHARE', 'EXCLUSIVE' ), "
         . "TRIM(T.dbsname)||'.'||TRIM(T.owner)||'.'||TRIM(T.tabname) Table, "
         . "'PAGE '||PG.pg_pagenum, "
         . "'Unknown' "
         . "FROM sysmaster:sysrstcb R, sysmaster:sysbufhdr B, "
         . "sysmaster:syspaghdr PG, sysmaster:systabnames T, outer sysmaster:sysrstcb O "
         . "WHERE R.bfwait  <> 0 "
         . "AND R.bfwait      = B.address "
         . "AND PG.pg_chunk   = B.chunk "
         . "AND PG.pg_offset  = B.offset "
         . "AND PG.pg_partnum = T.partnum "
         . "AND B.owner       = O.address "*/
        . "UNION "
        . "SELECT "
        . "R.username wait_user,R.sid  wait_sid,  "
        . " ' ' , 0,  "
        . "decode(  bitand(R.flags, '0x5010'), '0x10','Checkpoint', '0x1000', 'Log Buffer','0x4000','Transaction','Unknown' ), "
        . "'None', "
        . "'None', "
        . "'Unknown' "
        . "FROM sysmaster:sysrstcb R "
        . "where bitand(R.flags, '0x5010')>0 "
        . "UNION "
        . "SELECT  NVL(R.username,'thread('||trim(T.name)||')') wait_user, "
        . " NVL(R.sid, T.tid), "
        . " ' ', "
        . " 0, "
        ." wait_reason, "
        ." '',"
        ." 'none',";
        // statedetail column only available in 11.50 or higher
        $sql .= ( Feature::isAvailable ( Feature::CHEETAH2, $this->idsadmin ) )? " statedetail" : " ''";
        $sql .= " from systcblst T, outer sysrstcb R"
        ." where wreason not in ( 21, 28 )"
        ." and R.mttcb = T.address "
        ." and wreason > 0 ";

        $tab->display_tab_by_page($this->idsadmin->lang('WaitSesRpt'),
        array(
        "1" => $this->idsadmin->lang('WaitSes'),
        "2" => $this->idsadmin->lang('ResourceOwner'),
        "3" => $this->idsadmin->lang('ResourceType'),
        "4" => $this->idsadmin->lang('WaitObject'),
        "5" => $this->idsadmin->lang('WaitItem'),
        "6" => $this->idsadmin->lang('WaitInfo')
        ),
        $sql,
        $qrycnt,
        NULL,
        "template_gentab_session_waiters.php");

        $this->idsadmin->html->add_to_output( $HTML );

    }
    
    /**
     * Lock List report
     */
    function locklist()
    {
		$db = $this->idsadmin->get_database("sysmaster");
		require_once ROOT_PATH."lib/gentab.php";
		$tab = new gentab($this->idsadmin);
		
		$report_title = $this->idsadmin->lang('locklist');

		$report = (isset($this->idsadmin->in["runReports"]));
		if ($report)
		{
			$this->idsadmin->html->add_to_output("<h4>". $this->idsadmin->lang("ReportMaxRows",array(self::REPORT_MAX_ROWS)) . "</h4>");
		}
		
		// Check if dbname and tabname are set.  If so, just show locklist for that  
		// particular table.
		$tabname_filter_clause = "";
		if (isset($this->idsadmin->in['dbname']) && isset($this->idsadmin->in['tabname']))
		{
			$tabname_filter_clause = " AND TRIM(t2.dbsname) = '" . $this->idsadmin->in['dbname']
				. "' AND TRIM(t2.tabname) = '" . $this->idsadmin->in['tabname'] . "' ";
			$report_title = $this->idsadmin->lang('locklistfortab', array($this->idsadmin->in['dbname'] . ":" . $this->idsadmin->in['tabname'])); 
		}
		
		// Check if sid is set.  If so, just show the locklit for that particular session.
		$sid_filter_clause = "";
		if (isset($this->idsadmin->in['sid']))
		{
			$sid_filter_clause = " AND r.sid = " . $this->idsadmin->in['sid'] . " ";
			$report_title = $this->idsadmin->lang('locklistforses', array($this->idsadmin->in['sid'])); 
		}
		
		$qry =  "SELECT " .
		(($report)? " FIRST " . self::REPORT_MAX_ROWS . " ": "") .
		"TRIM(r.username) owner_user, " .
		"r.sid owner_sid, " .
		"TRIM(r1.username) wait_user , " .
		"r1.sid wait_sid, " .
		"CASE " .
		"        WHEN (MOD(lk_flags,2*2) >= 2) THEN " .
		"                'HDR+'||f.txt " .
		"        ELSE " .
		"                f.txt " .
		"END lock_type, " .
		"CASE " .
		"        WHEN lk_keynum = 0 THEN " .
		"                TRIM(t2.dbsname)||':'||TRIM(t2.tabname) " .
		"        ELSE " .
		"                TRIM(t2.dbsname)||':'||TRIM(t2.tabname)||'#'||TRIM(t1.tabname) " .
		"        END locked_object, " .
		"lk_rowid, " .
		"lk_partnum, " .
		"DBINFO('utc_to_datetime', lk_grtime) " .
		"FROM syslocktab l, flags_text f, systabnames t1, sysptnhdr p, systabnames t2, systxptab tx, sysrstcb r, outer sysrstcb r1 " .
		"WHERE " .
		"p.partnum = l.lk_partnum AND " .
		"t2.partnum = p.lockid AND " .
		"t1.partnum = l.lk_partnum AND " .
		"l.lk_type =  f.flags AND " .
		"f.tabname = 'syslcktab' AND " .
		"tx.address = lk_owner AND " .
		"r.address = tx.owner AND " .
		"r1.address = lk_wtlist AND " .
		"r.sid != DBINFO('sessionid') "
		. $tabname_filter_clause
		. $sid_filter_clause;
		
		$qrycnt = "SELECT count(*)" .
		"FROM syslocktab l, flags_text f, systabnames t1, sysptnhdr p, systabnames t2, systxptab tx, sysrstcb r, outer sysrstcb r1 " .
		"WHERE " .
		"p.partnum = l.lk_partnum AND " .
		"t2.partnum = p.lockid AND " .
		"t1.partnum = l.lk_partnum AND " .
		"l.lk_type =  f.flags AND " .
		"f.tabname = 'syslcktab' AND " .
		"tx.address = lk_owner AND " .
		"r.address = tx.owner AND " .
		"r1.address = lk_wtlist AND " .
		"r.sid != DBINFO('sessionid') "
		. $tabname_filter_clause
		. $sid_filter_clause;
		
		$tab->display_tab_by_page($report_title,
		array(
			"1" => "{$this->idsadmin->lang('Owner')}",
			"2" => "{$this->idsadmin->lang('OwnerSID')}",
			"3" => "{$this->idsadmin->lang('Waiter')}",
			"4" => "{$this->idsadmin->lang('WaitSID')}",
			"5" => "{$this->idsadmin->lang('LkType')}",
			"6" => "{$this->idsadmin->lang('LkObject')}",
			"7" => "{$this->idsadmin->lang('RowID')}",
			"8" => "{$this->idsadmin->lang('Partnum')}",
			"9" => "{$this->idsadmin->lang('LockEstablished')}",
		),
		$qry, 
		$qrycnt,
		NULL,
		"template_gentab_locks.php");
    }
    
    /**
     * Locks per table report
     */
    function locksPerTab()
    {
		$db = $this->idsadmin->get_database("sysmaster");
		require_once ROOT_PATH."lib/gentab.php";
		$tab = new gentab($this->idsadmin);
    	
		$report = (isset($this->idsadmin->in["runReports"]));
		if ($report)
		{
			$this->idsadmin->html->add_to_output("<h4>". $this->idsadmin->lang("ReportMaxRows",array(self::REPORT_MAX_ROWS)) . "</h4>");
		}
		
		$qry =  "SELECT " .
		(($report)? " FIRST " . self::REPORT_MAX_ROWS . " ": "") .
		"t.dbsname , " .
		"t.tabname, " .
        "NVL(l.lk_addr,0) AS lockcnt, " .
		"SUM(p.pf_rqlock) AS lockreq, " .
		"SUM(p.pf_wtlock) AS lockwaits, " .
		"SUM(p.pf_deadlk) AS deadlocks, " .
		"SUM(p.pf_lktouts) AS locktimeouts " .
		"FROM sysptntab p, systabnames t, OUTER ( select count(l1.lk_addr) lk_addr, lk_partnum, " .
		"CASE WHEN lk_kvobj = 0 THEN 0 ELSE 1 END lk_kvobj FROM syslocktab l1 GROUP BY 2, 3 ) l " .
		"WHERE p.tablock = t.partnum AND " .
		"( (l.lk_partnum = p.tablock AND l.lk_kvobj != 0) OR (l.lk_partnum = p.partnum AND l.lk_kvobj = 0) ) " .
		"GROUP BY 1,2,3 " .
		"HAVING SUM(p.pf_rqlock) > 0 " .
		"ORDER BY lockcnt DESC, lockreq DESC";
    	
		$qrycnt =  "SELECT count(*) FROM ($qry)";

		$tab->display_tab_by_page("{$this->idsadmin->lang('locksPerTab')}",
		array(
			"1" => "{$this->idsadmin->lang('Database')}",
			"2" => "{$this->idsadmin->lang('TableName')}",
			"3" => "{$this->idsadmin->lang('ActiveLocks')}",
			"4" => "{$this->idsadmin->lang('LockRequests')}",
			"5" => "{$this->idsadmin->lang('LockWaits')}",
			"6" => "{$this->idsadmin->lang('DeadLocks')}",
			"7" => "{$this->idsadmin->lang('LockTimeouts')}",
		),
		$qry, $qrycnt, NULL,
		"template_gentab_locks.php");
    }
    
    /**
     * Locks per session report
     */
    function locksPerSes()
    {
		$db = $this->idsadmin->get_database("sysmaster");
		require_once ROOT_PATH."lib/gentab.php";
		$tab = new gentab($this->idsadmin);
    	
		$report = (isset($this->idsadmin->in["runReports"]));
		if ($report)
		{
			$this->idsadmin->html->add_to_output("<h4>". $this->idsadmin->lang("ReportMaxRows",array(self::REPORT_MAX_ROWS)) . "</h4>");
		}
		
		$qry =  "SELECT " .
		(($report)? " FIRST " . self::REPORT_MAX_ROWS . " ": "") .
		"t.sid, " .
		"trim(t.username)||' @ '||decode(length(s.hostname),0,'localhost',s.hostname)::lvarchar as user, " .
		"SUM(nlocks) ses_num_locks, " .
		"SUM(upf_rqlock) ses_req_locks, " .
		"SUM(upf_wtlock) ses_wai_locks, " .
		"SUM(upf_deadlk) ses_dead_locks, " .
		"SUM(upf_lktouts) ses_lock_tout, " .
		"d.odb_dbname, " .
		"f.txt, " .
		"DBINFO('utc_to_datetime', s.connected) ses_connected " .
		"FROM sysrstcb t, sysscblst s, sysopendb d, flags_text f " .
		"WHERE t.sid = s.sid AND " .
		"d.odb_sessionid = t.sid AND " .
		"odb_iscurrent = 'Y' AND " .
		"f.tabname = 'sysopendb' AND " .
		"f.flags = d.odb_isolation AND " .
		"t.sid != DBINFO('sessionid') " .
		"GROUP BY 1,2,8,9,10" . 
		"ORDER BY ses_num_locks DESC, ses_req_locks DESC";
    	
		$qrycnt =  "SELECT count(*) " .
		"FROM sysrstcb t, sysscblst s, sysopendb d, flags_text f " .
		"WHERE t.sid = s.sid AND " .
		"d.odb_sessionid = t.sid AND " .
		"odb_iscurrent = 'Y' AND " .
		"f.tabname = 'sysopendb' AND " .
		"f.flags = d.odb_isolation AND " .
		"t.sid != DBINFO('sessionid') " .
		"GROUP BY t.sid" ;

		$tab->display_tab_by_page("{$this->idsadmin->lang('locksPerSes')}",
		array(
			"1" => "{$this->idsadmin->lang('SID')}",
			"2" => "{$this->idsadmin->lang('User')}",
			"3" => "{$this->idsadmin->lang('Locks')}",
			"4" => "{$this->idsadmin->lang('LockRequests')}",
			"5" => "{$this->idsadmin->lang('LockWaits')}",
			"6" => "{$this->idsadmin->lang('DeadLocks')}",
			"7" => "{$this->idsadmin->lang('LockTimeouts')}",
			"8" => "{$this->idsadmin->lang('Database')}",
			"9" => "{$this->idsadmin->lang('IsolationLevel')}",
			"10" => "{$this->idsadmin->lang('Connected')}"
		),
		$qry,$qrycnt,NULL,
		"template_gentab_locks.php");
    }
    
    /**
     * Locks with waiters report
     */
    function locksWaiters()
    {
		$db = $this->idsadmin->get_database("sysmaster");
		require_once ROOT_PATH."lib/gentab.php";
		$tab = new gentab($this->idsadmin);
		
		$report = (isset($this->idsadmin->in["runReports"]));
		if ($report)
		{
			$this->idsadmin->html->add_to_output("<h4>". $this->idsadmin->lang("ReportMaxRows",array(self::REPORT_MAX_ROWS)) . "</h4>");
		}
		
		$qry =  "SELECT " .
			(($report)? " FIRST " . self::REPORT_MAX_ROWS . " ": "") .
			"a.dbsname dbsname, " .
			"a.tabname tabname, " .
			"t2.sid owner_sid, " .
			"trim(t2.username)||' @ '||decode(length(h.hostname),0,'localhost',h.hostname)::lvarchar as owner_user, " .
			"t.sid wait_sid, " .
			"trim(t.username)||' @ '||decode(length(h2.hostname),0,'localhost',h2.hostname)::lvarchar as wait_user, " .
			"CURRENT YEAR TO SECOND - DBINFO('utc_to_datetime',g.start_wait) lock_wait, " .
			"l.indx lock_id, " .
			"f.txt[1,4] lock_type, " .
			"l.rowidr rowid, " .
			"l.keynum keynum, " .
			"EXTEND(DBINFO('utc_to_datetime', grtime), YEAR TO SECOND) lock_establish, " .
			"CURRENT YEAR TO SECOND - DBINFO('utc_to_datetime', grtime) lock_duration " .
			"FROM " .
			"sysrstcb t, syslcktab l, sysrstcb t2,  systxptab c, systabnames a, systcblst g, sysscblst h, sysscblst h2, flags_text f " .
			"WHERE " .
			"t.lkwait = l.address AND " .
			"l.owner = c.address AND " .
			"c.owner = t2.address AND " .
			"l.partnum = a.partnum AND " .
			"g.tid = t.tid AND " .
			"h2.sid = t.sid AND " .
			"h.sid = t2.sid AND " .
			"f.tabname = 'syslcktab' AND f.flags = l.type " .
			"ORDER BY lock_duration desc, lock_id;";
		
		$qrycnt =  "SELECT COUNT(*) " .
			"FROM " .
			"sysrstcb t, syslcktab l, sysrstcb t2,  systxptab c, systabnames a, systcblst g, sysscblst h, sysscblst h2, flags_text f " .
			"WHERE " .
			"t.lkwait = l.address AND " .
			"l.owner = c.address AND " .
			"c.owner = t2.address AND " .
			"l.partnum = a.partnum AND " .
			"g.tid = t.tid AND " .
			"h2.sid = t.sid AND " .
			"h.sid = t2.sid AND " .
			"f.tabname = 'syslcktab' AND f.flags = l.type ";
			
		$tab->display_tab_by_page( $this->idsadmin->lang("locksWaiters"),
		array(
			"1" => $this->idsadmin->lang("Database"),
			"2" => $this->idsadmin->lang("TableName"),
			"3" => $this->idsadmin->lang("OwnerSID"),
			"4" => $this->idsadmin->lang("Owner"),
			"5" => $this->idsadmin->lang("WaitSID"),
            "6" => $this->idsadmin->lang("Waiter"),
			"7" => $this->idsadmin->lang("WaitTime"),
			"8" => $this->idsadmin->lang("lockid"),
			"9" => $this->idsadmin->lang("LkType"),
			"10" => $this->idsadmin->lang("RowID"),
			"11" => $this->idsadmin->lang("Key"),
			"12" => $this->idsadmin->lang("LockEstablished"),
			"13" => $this->idsadmin->lang("LockDuration"),
		),
		$qry,$qrycnt,NULL,
		"template_gentab_locks.php");
	}


    /**
     * Future Developement
     *
     * Fina a column which has a unique or primmary constraint or index
     * on it so we can use it to update by
     *
     * @param string $tabname
     * @param string $dbsname
     * @return array
     */
    function findUniqueColumn( $tabname, $dbsname="sysmaster" )
    {

        $db = $this->idsadmin->get_database($dbsname);

        ################################
        # See if rowid will work
        ################################
        $sql ="SELECT tabid, partnum " .
        " FROM systables " .
        " WHERE tabname ='" . $tabname . "'";


        $stmt = $db->query($sql);
        if ($res = $stmt->fetch() )
        {
            $tabid = $res['TABID'];
            if ( $res['PARTNUM'] != 0 )
            {
                return array ( '1' => "ROWID") ;
            }
        }
        else
        return false;

        ################################
        # See if we have any unqiue indexes
        #  i.e. primary key, unique constraints
        #       or unique indexes
        ################################

        # Find an index with the fewest key parts
        # search up to 16 indexes key parts
        for( $i=2 ; $i < 17 ; $i++ )
        {
            $sql = "SELECT * " .
            "FROM sysindexes " .
            "WHERE " .
            " (  ( idxname in  ( " .
            "        select idxname from sysconstraints " .
            "        where constrtype in ('U','P') " .
            "    ))  " .
            "  OR  " .
            "    (   idxtype='U'  ) " .
            " ) " .
            " AND " .
            "    part".$i. "=0 " .
            " AND " .
            "   tabid = " . $tabid  ;

            $stmt = $db->query($sql);
            if ($res = $stmt->fetch() )
            {
                $col_where="";
                foreach( $res as $index => $val )
                if ( (strncmp("PART",$index,4) == 0 ) && $val>0 )
                {
                    if ( $col_where == "" )
                    $col_where = " colno IN ( " . abs($val) ;
                    else
                    $col_where .= " , " . abs($val) ;
                }
                $col_where .= " ) ";
                $sql = "SELECT colname " .
                " FROM syscolumns " .
                " WHERE tabid = " . $tabid .
                " AND " . $col_where;
                $results = array();
                $stmt = $db->query($sql);
                while ($res = $stmt->fetch())
                {
                    $results[] = $res['COLNAME'];
                }
                return $results;
            }

        }
        return false;
    }


    /**
     * Get meta data for a specific table
     *
     * @param string $tabname
     * @param string $dbsname
     * @return array
     */
    function showTableColumns( $tabname , $dbsname="sysmaster" )
    {
        $db = $this->idsadmin->get_database($dbsname);

        ################################
        # See if rowid will work
        ################################
        $sql ="SELECT tabid " .
        " FROM systables " .
        " WHERE tabname ='" . $tabname . "'";


        $stmt = $db->query($sql);
        if ($res = $stmt->fetch() )
        {
            $tabid = $res['TABID'];
        }
        else
        return false;


        $sql = "SELECT " .
        "colname, " .
        "colno, " .
        "coltype, " .
        "collength, " .
        "colmin, " .
        "colmax, " .
        "extended_id " .
        " FROM syscolumns " .
        " WHERE tabid = " . $tabid .
        " ORDER BY 2" ;

        $stmt = $db->query($sql);
        if ($res = $stmt->fetchALL() )
        return $res;
        return false;
    }
    
    function heatmaps() 
    {
    	require_once ROOT_PATH . "modules/heatmaps.php";
    	$heatmaps = new heatmaps($this->idsadmin);
    	
    	$HTML = <<< EOF
<div class="tabpadding">
<div class="borderwrap">      
<table class="gentab_nolines">
<tr>
<td class="tblheader" colspan="4" align="center">{$this->idsadmin->lang('HeatMaps')}</td>
</tr>
<tr>
<td>
EOF;
    	
    	$this->idsadmin->html->add_to_output($HTML);
    	$heatmaps->run();

    	$HTML = <<< EOF
</td>
</tr>
</table>
</div>
</div>
EOF;
		$this->idsadmin->html->add_to_output($HTML);
    }

}
?>
