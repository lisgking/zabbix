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
 * This class show all the sql tracing functionality
 * The attemp is to SQL statements show by
 * 1.  entire system view
 * 2.  Transactional view
 * 3.  By user (i.e. session)
 */
class sqltraceforreports {

    /*
     * The name of the database and the base table
     *   name to be use for sqltrace information.
     *   The table extention will be added
     *
     * @var		trace_table
     */
    private $trace_table = "syssqltrace";
    private $trace_htable = "mon_syssqltrace";

    /************************************
     * This class constructor sets
     * the default title and the
     * language files.
     *
     * @return sqltrace
     */
    function __construct(&$idsadmin)
    {
        $this->idsadmin = &$idsadmin;
        //$this->idsadmin->load_template("template_login");
        $this->idsadmin->load_lang("sqltrace");
        $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('SQLExplorer'));
    }

    /***********************************************
     * The run function is what index.php will call.
     * The decission of what to actually do is based
     * on the value of the $this->idsadmin->in['do']
     */
    function run()
    {
        $this->idsadmin->setCurrMenuItem("sqlexplorer");
        
        if (strncasecmp($this->idsadmin->in['do'],"show",4)==0)
        {
            // Running as a System Report
            $run=substr($this->idsadmin->in['do'],4);
            $this->idsadmin->setCurrMenuItem("Reports");
        }
        elseif ( $this->isSQLTraceOn()== false )
        {
            $run="admin";
        }
        else
        {
            $this->idsadmin->html->add_to_output($this->setuptabs($this->idsadmin->in['do']));
            $run=$this->idsadmin->in['do'];
        }

        switch($run)
        {
            case 'getXtree':
                $this->getXtree();
                break;
            case 'ByType':
            	if (isset($this->idsadmin->in['start_date']) && isset($this->idsadmin->in['end_date']))
            	{
            		$this->ByType(true,$this->idsadmin->in['start_date'],$this->idsadmin->in['end_date']);
            	} else {
                	$this->ByType();
            	}
                break;
            case 'ByTX':
                $this->ByTX();
                break;
            case 'listtxgroup':
                $this->TXGroupSummary($this->idsadmin->in['sid'], $this->idsadmin->in['begintime']);
                $this->listTXGroup($this->idsadmin->in['sid'], $this->idsadmin->in['begintime']);
                break;
            case 'ByFreq':
                $this->ByFreq();
                break;
            case 'type':
                $this->ByStmtType();
                break;
            case 'queryid':
            	$this->idsadmin->set_redirect("ByType");
                $this->byQueryID( $this->idsadmin->in['id']);
            	break;
            case 'sqllist':
                $this->listSQLStatement( $this->idsadmin->in['id'] );
                break;
             case 'admin':
                $this->idsadmin->set_redirect("admin","sqltrace");
                $this->sqlTraceAdmin( );
                break;
            case 'SumByUser':
                if ( isset( $this->idsadmin->in['sid'] ) )
                {
                    $this->SumByUser($this->idsadmin->in['sid']);
                }
                else
                {
                    $this->SumByUser(0);
                }
                break;
            case 'slowSQL':
            	if (isset($this->idsadmin->in['start_date']) && isset($this->idsadmin->in['end_date']))
            	{
            		$this->SlowestSQLbyUser(0,true,$this->idsadmin->in['start_date'],$this->idsadmin->in['end_date']);
            	}  
                elseif (isset( $this->idsadmin->in['sid'] ) )
                {
                    $this->SlowestSQLbyUser($this->idsadmin->in['sid']);
                }
                else
                {
                    $this->SlowestSQLbyUser(0);
                }
                break;
            case 'mostIO':
            	if (isset($this->idsadmin->in['start_date']) && isset($this->idsadmin->in['end_date']))
            	{
            		$this->SlowestSQLbyUser(0,true,$this->idsadmin->in['start_date'],$this->idsadmin->in['end_date'],"sql_totaliowaits");

            	} 
                elseif ( isset( $this->idsadmin->in['sid'] ) )
                {
                    $this->SlowestSQLbyUser($this->idsadmin->in['sid'],false,null,null,"sql_totaliowaits");
                }
                else
                {
                    $this->SlowestSQLbyUser(0,false,null,null,"sql_totaliowaits");
                    
                }
                break;
            case 'mostBuff':
            	if (isset($this->idsadmin->in['start_date']) && isset($this->idsadmin->in['end_date']))
            	{
            		$this->SlowestSQLbyUser(0,true,$this->idsadmin->in['start_date'],$this->idsadmin->in['end_date'],"sql_bfreads+sql_bfwrites");
            	} 
                elseif ( isset( $this->idsadmin->in['sid'] ) )
                {
                    $this->SlowestSQLbyUser($this->idsadmin->in['sid'],false,null,null,"sql_bfreads+sql_bfwrites");
                }
                else
                {
                    $this->SlowestSQLbyUser(0,false,null,null,"sql_bfreads+sql_bfwrites");
                }
                break;
            default:
                $this->ByType();
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
        require_once ROOT_PATH."/lib/tabs.php";
        $t = new tabs($this->idsadmin);
        $t->addtab("index.php?act=sqltraceforreports&amp;do=ByType",$this->idsadmin->lang("StmtType"),
        ($active == "ByType") ? 1 : 0 );
        $t->addtab("index.php?act=sqltraceforreports&amp;do=ByTX",$this->idsadmin->lang("TXTime"),
        ($active == "ByTX") ? 1 : 0 );
        $t->addtab("index.php?act=sqltraceforreports&amp;do=ByFreq",$this->idsadmin->lang("Freq"),
        ($active == "ByFreq") ? 1 : 0 );
        $t->addtab("index.php?act=sqltraceforreports&amp;do=admin",$this->idsadmin->lang("SQLAdmin"),
        ($active == "admin") ? 1 : 0 );
        
        // dynamic tabs that are only displayed if the user is on that page
        if ( $active == "type" )
        {
            $t->addtab("index.php?act=sqltraceforreports&amp;do=type",$this->idsadmin->lang("SQLType"),
            ($active == "type") ? 1 : 0 );
        }
        if ( $active == "listtxgroup" )
        {
            $t->addtab("index.php?act=sqltraceforreports&amp;do=listtxgroup",$this->idsadmin->lang("TXGroup"),
            ($active == "listtxgroup") ? 1 : 0 );
        }
        if ( $active == "queryid" )
        {
            $t->addtab("index.php?act=sqltraceforreports&amp;do=queryid",$this->idsadmin->lang("SQLProfile"),
            ($active == "queryid") ? 1 : 0 );
        }
        if ( $active == "sqllist" )
        {
            $t->addtab("index.php?act=sqltraceforreports&amp;do=sqllist",$this->idsadmin->lang("SQLList"),
            ($active == "sqllist") ? 1 : 0 );
        }
        
        #set the 'active' tab.
        $html  = ($t->tohtml());
        $html .= "<div class='borderwrapwhite'><br/>";
        return $html;
    } #end setuptabs


    /**
     * Show the SQL for the entire system broken down by
     * SQL operation.
     *
     */
    function ByType($historical=false, $start_date=null, $end_date=null)
    {
    	if ($historical == false)
        {
	        if ( $this->isSQLTraceOn() === false )
	        {
	            $this->idsadmin->html->add_to_output("<center>{$this->idsadmin->lang('SQLTracingOff')}</center>");
	            return;
	        }
        }

        require_once ROOT_PATH."lib/gentab.php";
        require_once ROOT_PATH."lib/Charts.php";

        $tab = new gentab($this->idsadmin);
        $data = array();

        $this->idsadmin->html->add_to_output( "<table><tr><td width='70%' valign='top'>" );
        
        if ($historical == true)
        {
        	$db = $this->idsadmin->get_database('sysadmin');
        	$tabname = $this->trace_htable;
        	$stmttype_syntax = "trim(sql_stmtname)";
        	$where_clause = "where '{$start_date}'<= CAST(dbinfo('UTC_TO_DATETIME',(sql_finishtime)) as DATETIME YEAR TO DAY) and '{$end_date}'>=CAST(dbinfo('UTC_TO_DATETIME',(sql_finishtime)) as DATETIME YEAR TO DAY) ";
        } else {
        	$db = $this->idsadmin->get_database('sysmaster');
        	$tabname = $this->trace_table;
        	if (strncasecmp($this->idsadmin->in['do'],"show",4)==0)
        	{
        		// if we are running this as a System Report... do not include links with stmt type
        		$stmttype_syntax = "trim(sql_stmtname)";
        	} else {
        		$stmttype_syntax = "'<a href=index.php?act=sqltraceforreports&amp;do=type&amp;sqltype='||sql_stmttype||'>'||trim(sql_stmtname)||'</a>'";
        	}
        	$where_clause = "";
        }
        
        $sql = "SELECT " .
		    "{$stmttype_syntax} as stmttype, " .
		    "count(*) as cnt , " .
		    "TRUNC(avg(sql_runtime),4) as runtime, " .
		    "TRUNC(MAX(sql_runtime),4) as maxruntime, " .
		    "format_units(avg(sql_sqlmemory),'b') as mem, " .
		    "sum(sql_actualrows) as ROWS, " .
		    "trim(sql_stmtname) as stname, " .
		    "sum(sql_runtime) as totalruntime " .
		    "from " . $tabname . " " .
		    $where_clause .        
		    "group by sql_stmttype, sql_stmtname " .
		    "order by 2 DESC,1 ";
        
        $data = $tab->display_tab("{$this->idsadmin->lang('SQLStmtSummary')}",
        array(
        "1" => $this->idsadmin->lang("StmtType"),
        "2" => $this->idsadmin->lang("Count"),
        "3" => $this->idsadmin->lang("AvgRT"),
        "4" => $this->idsadmin->lang("MaxRT"),
        "5" => $this->idsadmin->lang("AvgMem"),
        "6" => $this->idsadmin->lang("RowProc"),
        ),
        $sql, "template_gentab.php", $db);
         
        $this->idsadmin->html->add_to_output( "</td><td valign='top'> " );


        if ( sizeof( $data ) == 0 )
        {
            $this->idsadmin->html->add_to_output("</td></tr><tr><td> " .
            $this->idsadmin->lang("NoData") . "</td></tr></table>" );
            $this->idsadmin->html->add_to_output("</div>");
            return;
        }
        $gdata = array();
        $row = array();

        if ( ! is_array($data) )
        {
            $data = array();
        }
         
        foreach ($data as $row) {
            $gdata[ $row['STNAME'] ] = $row['CNT'];
        }

        if ( sizeof($gdata) > 0 )
        {
            $this->idsadmin->Charts = new Charts($this->idsadmin);
            $this->idsadmin->Charts->setType("PIE");
            $this->idsadmin->Charts->setData($gdata);
            $this->idsadmin->Charts->setTitle($this->idsadmin->lang('SQLStmtSum'));
            $this->idsadmin->Charts->setDataTitles(array($this->idsadmin->lang('Count'),$this->idsadmin->lang('StmtType')));
            $this->idsadmin->Charts->setLegendDir("vertical");
            $this->idsadmin->Charts->setWidth("450");
            $this->idsadmin->Charts->setHeight("300");
            $this->idsadmin->Charts->Render();
        }
        // $this->idsadmin->html->add_to_output( $mygraph->pieGraph( $gdata,
        // $this->idsadmin->lang("SQLStmtSum"),300,450) );

        $this->idsadmin->html->add_to_output( "</tr></tr></table>" );

        $this->idsadmin->html->add_to_output("</div>");

    } #end default



    /**
     * Display a summary of the SQL by transaction.
     *
     */
    function ByTX()
    {
         
        require_once ROOT_PATH."lib/gentab.php";
        //require_once 'Image/Graph.php';
        $tab = new gentab($this->idsadmin);


        // $this->idsadmin->html->add_to_output( "<TABLE><TR><TD width='70%'>" );
        /***
        display_tab_by_page
        */
        $tab->display_tab_by_page($this->idsadmin->lang("NRecentTX"),
        array(
        "1" => $this->idsadmin->lang("SQLDrill"),
        "2" => $this->idsadmin->lang("TXTime"),
        "3" => $this->idsadmin->lang("SID"),
        "4" => $this->idsadmin->lang("Count"),
        "5" => $this->idsadmin->lang("AvgRT"),
        "6" => $this->idsadmin->lang("MaxRT"),
        "7" => $this->idsadmin->lang("AvgMem"),
        "8" => $this->idsadmin->lang("RowProc"),
        ),
        "SELECT " .
        "sql_begintxtime as begintime, " .
        "dbinfo('UTC_TO_DATETIME',sql_begintxtime) as txtime , " .
        "sql_sid, " .
        "count(*) as cnt , " .
        "TRUNC(avg(sql_runtime),4) as runtime, " .
        "TRUNC(MAX(sql_runtime),4) as maxruntime, " .
        "format_units(avg(sql_sqlmemory),'b') as mem, " .
        "sum(sql_actualrows) as ROWS " .
        "from " . $this->trace_table ." " .
        " where sql_stmttype >0 " .
        "group by sql_begintxtime, sql_sid " .
        "order by begintime DESC ",
        100,
        10, "gentab_sqltrace_tx.php" );

        //$this->idsadmin->html->add_to_output( "</TD></TR></TABLE>" );

        //$this->idsadmin->html->add_to_output("</div>");

    }


    /**
     * This function displays a summary of all SQL
     * statements by statement type. (i.e. Select, insert,
     * delete, update,...).
     *
     */
    function ByStmtType()
    {
        $db = $this->idsadmin->get_database("sysmaster");

        require_once ROOT_PATH."lib/gentab.php";
        // require_once 'Image/Graph.php';
        $tab = new gentab($this->idsadmin );
        $work = isset($this->idsadmin->in['sqltype']) ?
       			 " = " . $this->idsadmin->in['sqltype'] : " > 0 ";

        $db->exec( "execute procedure IFX_ALLOW_NEWLINE('T')" );

        /* NOTE Should improve this something to get better performance */
        $qrycnt="SELECT " .
        " count( unique sql_statement[1,1000] ) " .
        "FROM " . $this->trace_table ." " .
        " WHERE sql_stmttype " . $work;

        /*
         * The following select will only show a portion of the full
         * sql statement.  This is so we do not fill the entire screen
         * with a single large sql statement.  The details screen will
         * show the full statement.
         */
        /* Cheetah2 has the new stmtlen and the stmthash info */
        if (  Feature::isAvailable( Feature::CHEETAH2, $this->idsadmin) ) {

            $qry = "SELECT " .
       		" MAX(sql_id) as url," .
        	" count(*) as cnt, " .
        	" TRUNC(sum(sql_runtime)/count(*),4) as avgrun, " .
        	" TRUNC(sum(sql_lockwttime),4) as lkwttime,  " .
        	" TRUNC(sum(sql_totaliowaits),4) as waitio, " .
	        " cast( dbinfo('UTC_TO_DATETIME',MAX(sql_finishtime)) as DATETIME HOUR TO SECOND) as finishtime, " .
	        " sql_statement[1,500] as sql_statement " .
	        " FROM " . $this->trace_table .
	        " WHERE sql_stmttype " . $work .
	        " GROUP BY sql_stmtlen, sql_stmthash, sql_statement " .
	        " ORDER BY 2 desc,3 desc";
        } else {

            $qry = "SELECT " .
       		" MAX(sql_id) as url," .
        	" count(*) as cnt, " .
        	" TRUNC(sum(sql_runtime)/count(*),4) as avgrun, " .
        	" TRUNC(sum(sql_lockwttime),4) as lkwttime,  " .
        	" TRUNC(sum(sql_totaliowaits),4) as waitio, " .
	        " cast( dbinfo('UTC_TO_DATETIME',MAX(sql_finishtime)) as DATETIME HOUR TO SECOND) as finishtime, " .
	        " sql_statement[1,500] as sql_statement " .
	        " FROM " . $this->trace_table .
	        " WHERE sql_stmttype " . $work .
	        " GROUP BY sql_statement " .
	        " ORDER BY 2 desc,3 desc";
        }



        $tab->display_tab_by_page("{$this->idsadmin->lang('SQLFreqSummary')}",
        array(
        "1" => "{$this->idsadmin->lang('SQLDrillDown')}",
        "2" => "{$this->idsadmin->lang('Count')}",
        "3" => "{$this->idsadmin->lang('AvgRunTime')}",
        "4" => "{$this->idsadmin->lang('LockWaitTime')}",
        "5" => "{$this->idsadmin->lang('WaitIOTime')}",
        "6" => "{$this->idsadmin->lang('CompleteTime')}",
        "7" => "{$this->idsadmin->lang('SQLStmt')}",
        ),
        $qry,
        $qrycnt, 20, "template_sqltracetype_order.php"  );

        $this->idsadmin->html->add_to_output("</div>");

    }


    /**
     * This function display SQL statements based on the
     * frequency upon which they are seen.  This allows
     * a DBA to ensure the most frequent statements are
     * optimized.
     *
     */
    function ByFreq()
    {
        $db = $this->idsadmin->get_database("sysmaster");
        require_once ROOT_PATH."lib/gentab.php";
        // require_once 'Image/Graph.php';
        $tab = new gentab($this->idsadmin);

        $surl=<<<END
<form method="get" action="index.php">
<input type=submit class=button name="view" value="{$this->idsadmin->lang('DrillDown')}">
<input type=hidden  name="act" value="sqltraceforreports">
<input type=hidden  name="do" value="sqllist">
<input type=hidden  name="id" value="
END;
        $eurl=<<<END
        "/>
</form>
END;

        $db->exec( "execute procedure IFX_ALLOW_NEWLINE('T')" );

        $qry = "SELECT ".
        " MAX(sql_id) as url, " .
        " count(*) as cnt, " .
        " TRUNC(sum(sql_runtime)/count(*),4) as avgrun, " .
        " TRUNC(sum(sql_lockwttime),4) as lkwttime,  " .
        " TRUNC(sum(sql_totaliowaits),4) as waitio, " .
        " sql_statement[1,500] as sql_statement " .
        "FROM " . $this->trace_table .
        " WHERE sql_stmttype >0 " .
        " GROUP BY sql_statement[1,500] " .
        " ORDER BY 2 desc, 3 desc";

        $qrycnt = "SELECT count( unique  sql_statement[1,500] ) " .
        " FROM " . $this->trace_table .
        " WHERE sql_stmttype >0 ";

        $tab->display_tab_by_page($this->idsadmin->lang("SQLFreq"),
        array(
        "1" => $this->idsadmin->lang("SQLDrill"),
        "2" => $this->idsadmin->lang("Count"),
        "3" => $this->idsadmin->lang("AvgRT"),
        "4" => $this->idsadmin->lang("LockWait"),
        "5" => $this->idsadmin->lang("WaitIOTime"),
        "6" => $this->idsadmin->lang("SQLStmt"),
        ),
        $qry,
        $qrycnt,
        20, "template_sqltracetype_order.php" );
        $this->idsadmin->html->add_to_output("</div>");

    }

    function printQueryHostVariables( $sql_id=0 )
    {
        $db = $this->idsadmin->get_database("sysmaster");
         
        $qryhost =  "SELECT "
        . " sql_hvar_id, "
        . "sql_hvar_typeid, "
        . "sql_hvar_xtypeid, "
        . "sql_hvar_ind, "
        . "sql_hvar_type, "
        . "sql_hvar_data "
        . " FROM " . $this->trace_table . "_hvar "
        . " WHERE sql_id = " . $sql_id
        . " ORDER BY sql_hvar_id"
        ;
         
        $stmthost = $db->query( $qryhost );

        /* Add Table Header */
        $html=<<<END
					 
<TABLE BORDER=1 WIDTH="100%">

<TR>
<td class="tblheader" align="center" colspan=3>{$this->idsadmin->lang("StmtHVars")}</td>
</TR>

<TR>
<TH> {$this->idsadmin->lang("Pos")} </TH>
<TH> {$this->idsadmin->lang("Type")} </TH>
<TH> {$this->idsadmin->lang("Value")} </TH>
</TR>
END;

        while ( ($hvar = $stmthost->fetch())==true) {
            if ( $hvar['SQL_HVAR_IND'] != 0 )
            $hvar['SQL_HVAR_DATA'] = "**NULL**";
            else
            $hvar['SQL_HVAR_DATA'] = htmlentities($hvar['SQL_HVAR_DATA'],ENT_COMPAT,"UTF-8");

            $html.=<<<END
			<TR>
			<TD> {$hvar['SQL_HVAR_ID']} </TD>
			<TD> {$hvar['SQL_HVAR_TYPE']} </TD>
			<TD> {$hvar['SQL_HVAR_DATA']}</TD>
			</TR>
END;
        }
        $html .= "</TABLE>";

        return $html;
    }

    /**
     * This function takes a given statement id and print all the
     * statistics.   This include a graphical reprsentation of the
     * query tree showing each iterator.
     *
     * @param integer $sql_id		The statement ID
     */
    function byQueryID( $sql_id=0, $historical=false )
    {
        $html = "";
        
        if ($historical == true)
        {
        	$db = $this->idsadmin->get_database("sysadmin");
        	// Mode=2 tells XTree.php to use the historical tables
        	$this->idsadmin->in['mode']=2;
        }
        else 
        {
        	$db = $this->idsadmin->get_database("sysmaster");	
        }
        
        // require_once ROOT_PATH."lib/gentab.php";
        //require_once 'Image/Graph.php';
        $qry = "SELECT  " .
        " sql_sid, " .
        " sql_uid,  " .
        " sql_stmttype, " .
        " sql_stmtname, " .
        " dbinfo('UTC_TO_DATETIME',sql_finishtime) as finishtime, " .
        " TRUNC(sql_runtime,7) || ' Sec' AS sql_runtime, " .
        " sql_pgreads, " .
        " sql_bfreads, " .
        " sql_bfidxreads, " .
        " sql_bfwrites, " .
        " sql_pgwrites, " .
        " TRUNC(sql_rdcache,2) as rdcache, " .
        " TRUNC(sql_wrcache,2) as wrcache, " .
        " sql_bfreads-sql_bfidxreads as databufreads, " .
        " sql_lockreq, " .
        " sql_lockwaits,  " .
        " sql_lockwttime, " .
        " format_units(sql_logspace,'b') as logspace, " .
        " sql_sorttotal, " .
        " sql_sortdisk, " .
        " sql_sortmem, " .
        " sql_numtables, " .
        " sql_numiterators, " .
        " sql_executions, " .
        " sql_totaltime, "  .
        " sql_avgtime," .
        " sql_executions, " .
        " TRUNC(sql_totaltime,5) as totaltime, " .
        " TRUNC(sql_avgtime,5) as avgtime, " .
        " TRUNC(sql_maxtime,5) maxtime,  " .
        " sql_numiowaits,   " .
        " TRUNC(sql_totaliowaits,5) totaliowaits,  " .
        " TRUNC(sql_avgiowaits,5) avgiowaits, " .
        " TRUNC(sql_rowspersec,5) as rowspersec, " .
        " sql_estcost, " .
        " sql_estrows, " .
        " sql_actualrows, " .
        " sql_sqlerror, " .
        " sql_isamerror, " .
        " sql_isollevel, " .
        " sql_statement, " .
        " sql_database, " .
        " format_units(sql_sqlmemory,'b') as sqlmemory, ";

        if (  Feature::isAvailable( Feature::CHEETAH2, $this->idsadmin) ) {
            $qry .= " sql_pdq, sql_num_hvars ";
        } else {
            $qry .= " 'NA' as sql_pdq, 0 AS sql_num_hvars ";
        }
        if ($historical == true)
        {
        	$qry .= " FROM " . $this->trace_htable;
	        
        } else {   
        	$qry .= " FROM " . $this->trace_table;
        }
        $qry .= " WHERE sql_id = " . $sql_id;
        
        
        $stmt = $db->query( $qry );

        $qry = "SELECT " .
        " sql_itr_id, sql_itr_left, sql_itr_right, ";
		
		if (  Feature::isAvailable ( Feature::PANTHER, $this->idsadmin->phpsession->serverInfo->getVersion()  )  )
		{
			 $qry .= " sql_itr_sender, sql_itr_nxtsender, ";
		}
		
        $qry .= " trim(sql_itr_info) as itr_info, " .
        " sql_itr_cost, sql_itr_estrows," .
        " sql_itr_numrows " ;
		
        if ($historical == true)
        { 
        	$qry .=  " FROM " . $this->trace_htable . "_iter ";
        } else {
        	$qry .= " FROM " . $this->trace_table . "_iter ";
        }
		
        $qry .= " WHERE sql_id = " . $sql_id .
        	" ORDER BY sql_itr_id";
    	
        // GD is required for XTree;  So check if gd is enabled
        $extensions = get_loaded_extensions();
        $gd_enabled = in_array("gd", $extensions);
        if (!$gd_enabled)
        {
            $this->idsadmin->error($this->idsadmin->lang("Error_GD_NotEnabled"));
        }

        while ( ($res = $stmt->fetch()) ==true)
        {
            /* If we do not have an iterator tree skip
             * printing one out.
             $xtreeimg="";
             if ( $res['SQL_NUMITERATORS'] > 0 )
             */
            if ( $gd_enabled && (( $res['SQL_STMTTYPE']  > 1  && $res['SQL_STMTTYPE']  < 7 )
            || $res['SQL_STMTTYPE'] == 32  || $res['SQL_STMTTYPE'] == 33 ) && $res['SQL_NUMITERATORS'] > 0)
            {
                $xtreePath = tempnam(realpath(ROOT_PATH . "/tmp"), "xtree_") . ".png";
                 
                require_once(ROOT_PATH . "lib/XTree.php");
                new XTree($this->idsadmin,$sql_id, $xtreePath);
                $xtreePath="tmp/".basename($xtreePath);
                 
                $xtreeimg=<<<END
<tr>
<td colspan='6' align='center'>
<img src="{$xtreePath}" border='0' alt='{$this->idsadmin->lang('QueryTree')}'>
</td>
</tr>
END;

            } else {
            	$xtreeimg=<<<END
<tr>
<td colspan='6' align='center'>
<em>{$this->idsadmin->lang('QueryTreeNA')}</em>
</td>
</tr>
END;
            }
             
            $res['SQL_STATEMENT'] = htmlentities($res['SQL_STATEMENT'],ENT_COMPAT,"UTF-8");
            $html=<<<END
<table class="gentab">
<tr>
<td class="tblheader" align="center" colspan="8">{$this->idsadmin->lang("SQLProfile")}</td>
</tr>
<tr>
<td width="50%">

<table class="gentab">
{$xtreeimg}
<tr>
<th> {$this->idsadmin->lang("SID")} </th>
<th> {$this->idsadmin->lang("UID")} </th>
<th> {$this->idsadmin->lang("StmtType")} </th>
<th> {$this->idsadmin->lang("PDQ")} </th>
<th> {$this->idsadmin->lang("CompTime")} </th>
<th> {$this->idsadmin->lang("RespTime")} </th>
</tr>
<tr>
<td> {$res['SQL_SID']} </td>
<td> {$res['SQL_UID']} </td>
<td> {$res['SQL_STMTNAME']} </td>
<td> {$res['SQL_PDQ']} </td>
<td> {$res['FINISHTIME']} </td>
<td> {$res['SQL_RUNTIME']} </td>
</tr>
<tr>
  <th> {$this->idsadmin->lang("Database")} </th>
  <td colspan='5'> {$res['SQL_DATABASE']} </td>
</tr>
<tr>
  <th> {$this->idsadmin->lang("Statement")} </th>
  <td colspan='5'>"{$res['SQL_STATEMENT']}"</td>
</tr>
</table>

</td>
</tr>


</table>
END;

$this->idsadmin->html->add_to_output( $html );
/***************************************************************
 * This will print a table version of the iterator tree.
 * Current we display the graphical view not the text version.
 * $tab->display_tab("Query Tree",
 array(
 "1" => "ID",
 "2" => "Left ID",
 "3" => "Right ID",
 "4" => "Operation Type",
 "5" => "Cost",
 "6" => "Estimated Rows",
 "7" => "Number of Rows",
 ),
 $qry);
 **************************************************************/

$html = "";

/* If we have host variables print them
 *
 * Test before un-commenting
 *
 */
 if ( $res['SQL_NUM_HVARS'] > 0 ) {
 $html .= $this->printQueryHostVariables( $sql_id );
 }


$html .= <<<END


<TABLE class="gentab">

<TR>
<td class="tblheader" align="center" colspan=8>{$this->idsadmin->lang("StmtStats")}</td>
</TR>

<TR>
<TH> {$this->idsadmin->lang("pgReads")} </TH>
<TH> {$this->idsadmin->lang("BufReads")} </TH>
<TH> {$this->idsadmin->lang("ReadCache")} </TH>
<TH> {$this->idsadmin->lang("DataBufReads")} </TH>
<TH> {$this->idsadmin->lang("IndexBuffReads")} </TH>
<TH> {$this->idsadmin->lang("PgWrites")} </TH>
<TH> {$this->idsadmin->lang("BufWrites")} </TH>
<TH> {$this->idsadmin->lang("WriteCache")} </TH>
</TR>
<TR>
<TD> {$res['SQL_PGREADS']} </TD>
<TD> {$res['SQL_BFREADS']} </TD>
<TD> {$res['RDCACHE']}% </TD>
<TD> {$res['DATABUFREADS']} </TD>
<TD> {$res['SQL_BFIDXREADS']} </TD>
<TD> {$res['SQL_PGWRITES']} </TD>
<TD> {$res['SQL_BFWRITES']} </TD>
<TD> {$res['WRCACHE']}% </TD>
</TR>



<TR>
<TH> {$this->idsadmin->lang("LockRequests")}</TH>
<TH> {$this->idsadmin->lang("NumLockWait")} </TH>
<TH> {$this->idsadmin->lang("LockWaitTime")}</TH>
<TH> {$this->idsadmin->lang("LogSpace")} </TH>
<TH> {$this->idsadmin->lang("DiskSorts")} </TH>
<TH> {$this->idsadmin->lang("MemSorts")} </TH>
<TH> {$this->idsadmin->lang("NumTables")} </TH>
<TH> {$this->idsadmin->lang("NumIter")} </TH>
</TR>
<TR>
<TD> {$res['SQL_LOCKREQ']} </TD>
<TD> {$res['SQL_LOCKWAITS']} </TD>
<TD> {$res['SQL_LOCKWTTIME']} </TD>
<TD> {$res['LOGSPACE']} </TD>
<TD> {$res['SQL_SORTTOTAL']} </TD>
<TD> {$res['SQL_SORTDISK']} </TD>
<TD> {$res['SQL_NUMTABLES']} </TD>
<TD> {$res['SQL_NUMITERATORS']} </TD>
</TR>


<TR>
<TH> {$this->idsadmin->lang("TExecs")} </TH>
<TH> {$this->idsadmin->lang("TExecTime")} </TH>
<TH> {$this->idsadmin->lang("AvgExecTime")} </TH>
<TH> {$this->idsadmin->lang("MaxExecTime")} </TH>
<TH> {$this->idsadmin->lang("TNumIOWait")} </TH>
<TH> {$this->idsadmin->lang("IOWaitTime")} </TH>
<TH> {$this->idsadmin->lang("AvgIOWait")} ) </TH>
<TH> {$this->idsadmin->lang("RowsPerSec")} </TH>
</TR>
<TR>
<TD> {$res['SQL_EXECUTIONS']} </TD>
<TD> {$res['TOTALTIME']} </TD>
<TD> {$res['AVGTIME']} </TD>
<TD> {$res['MAXTIME']} </TD>
<TD> {$res['SQL_NUMIOWAITS']} </TD>
<TD> {$res['TOTALIOWAITS']} </TD>
<TD> {$res['AVGIOWAITS']} </TD>
<TD> {$res['ROWSPERSEC']} </TD>
</TR>


<TR>
<TH> {$this->idsadmin->lang("EstCost")}</TH>
<TH> {$this->idsadmin->lang("EstRows")} </TH>
<TH> {$this->idsadmin->lang("ActRows")} </TH>
<TH> {$this->idsadmin->lang("SQLErr")} </TH>
<TH> {$this->idsadmin->lang("ISAMErr")} </TH>
<TH> {$this->idsadmin->lang("IsoLevel")} </TH>
<TH> {$this->idsadmin->lang("SQLMem")} </TH>
<TH> </TH>
</TR>
<TR>
<TD> {$res['SQL_ESTCOST']} </TD>
<TD> {$res['SQL_ESTROWS']} </TD>
<TD> {$res['SQL_ACTUALROWS']} </TD>
<TD> {$res['SQL_SQLERROR']} </TD>
<TD> {$res['SQL_ISAMERROR']} </TD>
<TD> {$res['SQL_ISOLLEVEL']} </TD>
<TD> {$res['SQLMEMORY']} </TD>
<TD> </TD>
</TR>



</TABLE> 
END;

        }
        $this->idsadmin->html->add_to_output( $html );


    }


    /**
     * For the given statement ID find all other statements
     * who are equal to this statement sql text.  List all the
     * similar statements so you can compare the statements
     *
     * @param integer $sql_id
     */
    function listSQLStatement( $sql_id )
    {
         
        require_once ROOT_PATH."lib/gentab.php";
        // require_once 'Image/Graph.php';
        $tab=new gentab($this->idsadmin);

        $surl=<<<END
<form method="get" action="index.php?act=sqltraceforreports&amp;do=queryid">
<input type=submit class=button name="view" value="Drill Down">
<input type=hidden  name="act" value="sqltraceforreports">
<input type=hidden  name="do" value="queryid">
<input type=hidden  name="id" value=
END;
        $eurl=<<<END
>
</form>
END;
        $db = $this->idsadmin->get_database("sysmaster");
        $db->exec( "execute procedure IFX_ALLOW_NEWLINE('T')" );
        $qry = "SELECT sql_statement FROM " .
        $this->trace_table .
        		 " WHERE sql_id =" . $sql_id;
        $stmt = $db->query( $qry );
        if (($res = $stmt->fetch()) != false )
        {
            $statement=htmlentities($res['SQL_STATEMENT'],ENT_COMPAT,"UTF-8");
            $this->idsadmin->html->add_to_output( "<CENTER><TABLE width='50%'><TR><TD>" );
            $this->idsadmin->html->add_to_output( $statement );
            $this->idsadmin->html->add_to_output( "</TD></TR></TABLE></CENTER>" );
        }


        /* Note because we can not match the query directly
         * because of special character processing is to hard.
         */
        $qry = "select " .
        " '$surl'||B.sql_id|| '$eurl' as url," .
        " B.sql_sid as SID, " .
        " B.sql_uid as UID, " .
        " TRUNC(B.sql_runtime,5)  as runtime, " .
        " TRUNC(B.sql_rowspersec,5) as rowspersec, " .
        " B.sql_actualrows, " .
        " TRUNC(B.sql_lockwttime,5) as lockwait, " .
        " TRUNC(B.sql_totaliowaits,5) as iowait  " .
        " FROM " . $this->trace_table .  " A, " .
        $this->trace_table . " B " .
        " WHERE A.sql_statement = B.sql_statement " .
        " AND A.sql_id = " . $sql_id;

        $qrycnt = "SELECT " .
        " count(*) " .
        " FROM " . $this->trace_table .  " A, " .
        $this->trace_table . " B " .
        " WHERE A.sql_statement = B.sql_statement " .
        " AND A.sql_id = " . $sql_id;

        $tab->display_tab_by_page("{$this->idsadmin->lang('ListSQL')}",
        array(
        "1" => $this->idsadmin->lang("SQLDrill"),
        "2" => $this->idsadmin->lang("SID"),
        "3" => $this->idsadmin->lang("UID"),
        "4" => $this->idsadmin->lang("RespTime"),
        "5" => $this->idsadmin->lang("RowPerSec"),
        "6" => $this->idsadmin->lang("RowProc"),
        "7" => $this->idsadmin->lang("LockWait"),
        "8" => $this->idsadmin->lang("WaitIOTime"),
        ),
        $qry,
        $qrycnt,
        10,
        "template_gentab_order.php");


    }

       
    
    /**
     * List a all SQL statement in a transaction.  A transaction is
     * defined by a sepcific users session id "sql_sid" and the time
     * the transaction started.
     *
     * @param integer $sql_sid
     * @param integer $begintime
     */
    function listTXGroup( $sql_sid, $begintime )
    {
         
        require_once ROOT_PATH."lib/gentab.php";
        $tab=new gentab($this->idsadmin);

        /* Note because we can not match the query directly
         * because of special character processing is to hard.
         */
        $qry = "select " .
        " sql_id as id, " .
        " TRUNC(sql_runtime,5)  as runtime, " .
        " TRUNC(sql_rowspersec,5) as rowspersec, " .
        " sql_actualrows, " .
        " TRUNC(sql_lockwttime,5) as lockwait, " .
        " TRUNC(sql_totaliowaits,5) as iowait,  " .
        " sql_statement as sql_statement, " .
        " sql_begintxtime " .
        " FROM " . $this->trace_table .
        " WHERE sql_begintxtime = " . $begintime .
        " AND sql_sid = " . $sql_sid;
        " ORDER BY runtime  DESC";

        $qrycnt = "SELECT count(*) as cnt ".
        " FROM " . $this->trace_table .
        " WHERE sql_begintxtime = " . $begintime .
        " AND sql_sid = " . $sql_sid;

        $tab->display_tab_by_page( $this->idsadmin->lang("ListSQL"),
        array(
        "1" => $this->idsadmin->lang("SQLDrill"),
        "2" => $this->idsadmin->lang("RespTime"),
        "3" => $this->idsadmin->lang("RowPerSec"),
        "4" => $this->idsadmin->lang("RowProc"),
        "5" => $this->idsadmin->lang("LockWait"),
        "6" => $this->idsadmin->lang("WaitIOTime"),
        "7" => $this->idsadmin->lang("SQLStmt"),
        ),
        $qry,
        $qrycnt,
        10,
        "gentab_sqltrace_list_id.php");


    } #end default

    /**
     *  Calculate the percentage between the two values
     *
     * @param integer  $x
     * @param integer  $y
     */
    function percent_cached( $x, $y ) {
        if ( $x == 0 || $x < $y )
        return "0.0";
        return sprintf( "%.2f", ($x-$y)*100.0/$x );
    }

    /**
     * This function takes a given session id and transaction
     * begin time and displays a summary of all SQL
     * statements in a transaction.
     *
     * @param integer $sql_sid		The session ID
     * @param integer $sql_txbgtime	The transaction begin time
     */
    function TXGroupSummary( $sql_sid=0, $sql_txbgtime=0 )
    {
        $html = "";
        $db = $this->idsadmin->get_database("sysmaster");

        $qry = "SELECT  " .
        " COUNT(*)               AS sql_numstmt, " .
        " SUM( sql_runtime )     AS sql_runtime, " .
        " SUM( sql_pgreads )     AS sql_pgreads, " .
        " SUM( sql_bfreads )     AS sql_bfreads, " .
        " SUM( sql_bfidxreads )  AS sql_bfidxreads, " .
        " SUM( sql_bfwrites )    AS sql_bfwrites, " .
        " SUM( sql_pgwrites )    AS sql_pgwrites, " .
        " SUM( sql_lockreq )     AS sql_lockreq, " .
        " SUM( sql_lockwaits )   AS sql_lockwaits,  " .
        " SUM( sql_lockwttime )  AS sql_lockwttime, " .
        " format_units( SUM(sql_logspace),'b') AS logspace, " .
        " SUM( sql_sorttotal )   AS sql_sorttotal, " .
        " SUM( sql_sortdisk )    AS sql_sortdisk, " .
        " SUM( sql_sortmem )     AS sql_sortmem, " .
        " SUM( sql_numiowaits )  AS sql_numiowaits,  " .
        " TRUNC(SUM(sql_totaliowaits),5) AS totaliowaits,  " .
        " TRUNC(AVG(sql_avgiowaits),5) AS avgiowaits, " .
        " SUM( sql_estcost )     AS sql_estcost, " .
        " SUM( sql_estrows )     AS sql_estrows, " .
        " SUM( sql_actualrows )  AS sql_actualrows, " .
        " format_units(SUM(sql_sqlmemory),'b') as sqlmemory, " .
        " TRUNC( SUM( sql_runtime ) ,5) AS sql_runtime " .
        " FROM " . $this->trace_table .
        " WHERE sql_sid = " . $sql_sid .
        " AND sql_begintxtime = " . $sql_txbgtime ;

        $stmt = $db->query( $qry );


        while ( ($res = $stmt->fetch()) ==true)
        {
            $res['RDCACHE'] = $this->percent_cached( $res['SQL_BFREADS'] ,
            $res['SQL_PGREADS'] );
            $res['WRCACHE'] = $this->percent_cached( $res['SQL_BFWRITES'] ,
            $res['SQL_PGWRITES'] );

            $html=<<<END

<TABLE class="gentab">

<TR>
<td class="tblheader" align="center" colspan=6>{$this->idsadmin->lang("TxStatsSummary")}</td>
</TR>


<TR>
<TH> {$this->idsadmin->lang("RespTime")} </TH>
<TH> {$this->idsadmin->lang("NumSQL")}</TH>
<TH> {$this->idsadmin->lang("EstCost")}</TH>
<TH> {$this->idsadmin->lang("EstRows")} </TH>
<TH> {$this->idsadmin->lang("ActRows")} </TH>
<TH> {$this->idsadmin->lang("SQLMem")} </TH>
</TR>
<TR>
<TD> {$res['SQL_RUNTIME']} </TD>
<TD> {$res['SQL_NUMSTMT']} </TD>
<TD> {$res['SQL_ESTCOST']} </TD>
<TD> {$res['SQL_ESTROWS']} </TD>
<TD> {$res['SQL_ACTUALROWS']} </TD>
<TD> {$res['SQLMEMORY']} </TD>
</TR>


<TR>
<TH> {$this->idsadmin->lang("pgReads")} </TH>
<TH> {$this->idsadmin->lang("BufReads")} </TH>
<TH> {$this->idsadmin->lang("ReadCache")} </TH>
<TH> {$this->idsadmin->lang("PgWrites")} </TH>
<TH> {$this->idsadmin->lang("BufWrites")} </TH>
<TH> {$this->idsadmin->lang("WriteCache")} </TH>
</TR>
<TR>
<TD> {$res['SQL_PGREADS']} </TD>
<TD> {$res['SQL_BFREADS']} </TD>
<TD> {$res['RDCACHE']}% </TD>
<TD> {$res['SQL_PGWRITES']} </TD>
<TD> {$res['SQL_BFWRITES']} </TD>
<TD> {$res['WRCACHE']}% </TD>
</TR>


<TR>
<TH> {$this->idsadmin->lang("LockRequests")}</TH>
<TH> {$this->idsadmin->lang("NumLockWait")} </TH>
<TH> {$this->idsadmin->lang("LockWaitTime")}</TH>
<TH> {$this->idsadmin->lang("LogSpace")} </TH>
<TH> {$this->idsadmin->lang("DiskSorts")} </TH>
<TH> {$this->idsadmin->lang("MemSorts")} </TH>
</TR>
<TR>
<TD> {$res['SQL_LOCKREQ']} </TD>
<TD> {$res['SQL_LOCKWAITS']} </TD>
<TD> {$res['SQL_LOCKWTTIME']} </TD>
<TD> {$res['LOGSPACE']} </TD>
<TD> {$res['SQL_SORTTOTAL']} </TD>
<TD> {$res['SQL_SORTDISK']} </TD>
</TR>

<TR>
<TH> {$this->idsadmin->lang("TNumIOWait")} </TH>
<TH> {$this->idsadmin->lang("IOWaitTime")} </TH>
<TH> {$this->idsadmin->lang("AvgIOWait")} ) </TH>
<TH> {$this->idsadmin->lang("IndexBuffReads")} </TH>
<TH style='border:none'> </TH> 
</TR>
<TR>
<TD> {$res['SQL_NUMIOWAITS']} </TD>
<TD> {$res['TOTALIOWAITS']} </TD>
<TD> {$res['AVGIOWAITS']} </TD>
<TD> {$res['SQL_BFIDXREADS']} </TD>
<TD style='border:none'> </TD> 
</TR>

</TABLE> 
END;

        }
        $this->idsadmin->html->add_to_output( $html );
    }



    /*
     * Return is SQL Tracing is on
     *
     * @return Boolean
     */
    function isSQLTraceOn(  )
    {
        $db = $this->idsadmin->get_database("sysmaster");
        $qry = "SELECT  ntraces FROM syssqltrace_info " .
        " WHERE ntraces > 0";

        $stmt = $db->query( $qry );

        if (($res = $stmt->fetch())==true)
        {
            return true;
        }
        else
        {
            return false;
        }
    }


    /**
     * Show the SQL Trace admin screen.  This screen shows
     * the status of the trace along with controling
     * the SQL trace options.
     * 1.  On/Off
     * 2.  Size of the trace
     * 3.  Trace Level
     *
     *
     */
    function sqlTraceAdmin()
    {
        $this->execSQLTraceControls( );
        $this->idsadmin->html->add_to_output( $this->sqlTraceInfo() );
        if ( $this->idsadmin->isreadonly() )
        {
            return;
        }

        $this->idsadmin->html->add_to_output( $this->displaySQLTraceControls() );

    }
    /**
     * Display the SQL trace status information
     *
     * @return HTML
     */
    function sqlTraceInfo(  )
    {
        $db = $this->idsadmin->get_database("sysmaster");
        $qry = "SELECT flags, " .
        " ntraces, " .
        " format_units(tracesize/1024,'kb') as tracesize,  " .
        " duration , " .
        " sqlseen, " .
        " dbinfo('UTC_TO_DATETIME',starttime) as starttime, " .
        " CASE " .
        "   WHEN sqlseen >= ntraces  THEN " .
        "       TRUNC(ntraces/decode(duration,0,1,duration),5) " .
        "  ELSE " .
        "       TRUNC(sqlseen/decode(duration,0,1,duration),5) " .
        " END  as sqlpersec, " .
        " format_units(memoryused,'b') as mem, " .
        " (CURRENT - duration UNITS second)::DATETIME YEAR TO SECOND as starttrace " .
        " FROM syssqltrace_info " .
        " WHERE ntraces > 0";

        $stmt = $db->query( $qry );


        if ( ( $res = $stmt->fetch() )!= NULL )
        {
            $html=<<<END
<TABLE class="gentab_nowidth" >
<tr>
<td class="tblheader" colspan=2 align="center">{$this->idsadmin->lang("SQLTraceInfo")}</td>
</tr>
<TR>
<TH align=left > {$this->idsadmin->lang("NumSQLTraced")}    </TH>
<TD> {$res['NTRACES']} </TD>
</TR>

<TR>
<TH align=left > {$this->idsadmin->lang("TraceBufSize")}</TH>
<TD> {$res['TRACESIZE']} </TD>
</TR>

<TR>
<TH align=left> {$this->idsadmin->lang("OldTracedStmt")}</TH>
<TD> {$res['STARTTRACE']} </TD>
</TR>

<TR>
<TH align=left> {$this->idsadmin->lang("TraceBufDuration")}</TH>
<TD> {$this->idsadmin->timedays($res['DURATION'])} </TD>
</TR>

<TR>
<TH align=left> {$this->idsadmin->lang("SQLSeen")}</TH>
<TD> {$res['SQLSEEN']} </TD>
</TR>

<TR>
<TH align=left> {$this->idsadmin->lang("SQLTraceStart")}</TH>
<TD> {$res['STARTTIME']} </TD>
</TR>

<TR>
<TH align=left> {$this->idsadmin->lang("SQLTracePerSec")}</TH>
<TD> {$res['SQLPERSEC']} </TD>
</TR>

<TR>
<TH align=left> {$this->idsadmin->lang("TraceMemUsed")} </TH>
<TD> {$res['MEM']} </TD>
</TR>


</TABLE>
END;
        }
        else
        {
            $html=<<<END
<TABLE class="gentab_70">
<tr>
<td class="tblheader" align="center">{$this->idsadmin->lang("SQLProfile")}</td>
</tr>
<TR>
<TH> {$this->idsadmin->lang('SQLTRACINGDISABLED')} </TH>
</TR>
</TABLE>
END;
        }



        return $html;


    }



    /**
     * Displays the status and controls for the SQL trace
     * admin section
     *
     * @return HTML
     */
    function displaySQLTraceControls( )
    {
        $db = $this->idsadmin->get_database("sysmaster");
        /*
         *  Get the new settings
         */
        $qry = "SELECT flags, ntraces,  tracesize  " .
        " FROM syssqltrace_info " .
        " WHERE flags > 0";

        $stmt = $db->query( $qry );

        $ntraces=0;
        if (($res = $stmt->fetch())==true)
        {
            $mode = "on";
            $ntraces = $res['NTRACES'];
            $buffsize = $res['TRACESIZE'];
        }

        if ( $ntraces == 0 )
        {
            $mode     = "off";
            $ntraces  = 2000 ;
            $buffsize = 2000 ;
        }


        $html=<<<END
<br/>
<br/>
<form method="post" action="index.php">
<input type=hidden name="do" value="admin">
<input type=hidden name="act" value="sqltraceforreports">

<table class="gentab_70" >
<tr>
<td class="tblheader" colspan=5 align="center">{$this->idsadmin->lang("SQLTraceOpt")}</td>
</tr>
<tr>
   <th align=center>{$this->idsadmin->lang("Mode")}</th>
   <th align=center>{$this->idsadmin->lang("NumTrace")}</th>
   <th align=center>{$this->idsadmin->lang("TraceSize")}</th>
   <th align=center>{$this->idsadmin->lang("TraceLevel")}</th>
   <th align=center></th>
</tr>

<tr>
   <td>
      <span title="{$this->idsadmin->lang("TraceOnOff")}">
      <select name="mode"><option value="ON">{$this->idsadmin->lang("On")}</option>
                          <option value="OFF">{$this->idsadmin->lang("Off")} </option>
      </select></span> 
   </td>
   <td align=center>
        <span title="{$this->idsadmin->lang("NumSqlTraceStmt")}">
        <input type=text name="ntraces" size=9 value={$ntraces} >
        </span>
   </td>
   <td align=center>
        <span title="{$this->idsadmin->lang("MaxSQLTrace")}">
        <input type=text name="buffsize" size=9 value={$buffsize} >
        </span>
   </td>
   <td align=center>
        <span title="{$this->idsadmin->lang("TraceLevel")}">
        <select name="level"><option value="LOW">{$this->idsadmin->lang("Low")}</option>
        					 <option value="MED">{$this->idsadmin->lang("Medium")}</option>
                             <option value="HIGH">{$this->idsadmin->lang("High")}</option>
        </select>
        </span>
   </td>
   <td align=center>
         <input type=submit class=button name="modify" value="{$this->idsadmin->lang("Modify")}"/>
   </td>
</tr>
</table>
<br/>
</form>
END;

        return $html;
    }

    /**
     * Check to see if we have been ask to modify the controls
     * for the SQL admin section.  If so make any changes to
     * the database server.  The changes include
     * 1. mode		on/off
     * 2. ntraces	The number of sql statements to trace
     * 3. trace buffer size		how much of the sql statement to store
     * 4. level		high/med/low tracing.
     *
     */
    function execSQLTraceControls( )
    {
         
        $check =  array(
        "1" => "mode",
        "2" => "ntraces",
        "3" => "buffsize",
        "4" => "level",
        );

        if ( empty($this->idsadmin->in['modify']) )
        return;

        foreach ( $check as  $val )
        {
            if ( empty($this->idsadmin->in[ $val ]) &&
            strlen($this->idsadmin->in[ $val ])<1 )
            {
                $this->idsadmin->error( $this->idsadmin->lang("SQLTbadParam") );
                return;
            }
        }
        $mode     = $this->idsadmin->in['mode'];
        $ntraces  = $this->idsadmin->in['ntraces'];
        $buffsize = $this->idsadmin->in['buffsize'];
        $level    = $this->idsadmin->in['level'];


        $dbadmin = $this->idsadmin->get_database("sysadmin");
        if ( strcasecmp($mode,"off")==0 )
        $sql ="select task( 'set sql tracing off' ) as info " .
        " FROM systables where tabid=1";
        else
        $sql ="select task( 'set sql tracing on' , " .
        " {$ntraces}, '{$buffsize} b', '{$level}' )  as info" .
        " FROM systables where tabid=1";

        $stmt = $dbadmin->query($sql);
        $res = $stmt->fetch();
        $this->idsadmin->status( $res['INFO']  );
        $stmt->closeCursor();

    }


    /**
     * Show a summary of SQL statement run by a specific
     * users (session).  This will show both a table and
     * graphical representation of the information.
     *
     * @param integer $sid   	The users session ID to
     * 							be displayed
     *
     */
    function SumByUser($sid)
    {
         
        require_once ROOT_PATH."lib/gentab.php";
        require_once ROOT_PATH."lib/Charts.php";
         
        $tab = new gentab($this->idsadmin);
        $data = array();

        $this->idsadmin->html->add_to_output( "<TABLE><TR><TD width='70%'>" );

        $data = $tab->display_tab( $this->idsadmin->lang("SQLStmtSum"),
        array(
        "1" => $this->idsadmin->lang("StmtType"),
        "2" => $this->idsadmin->lang("Count"),
        "3" => $this->idsadmin->lang("AvgRT"),
        "4" => $this->idsadmin->lang("MaxRT"),
        "5" => $this->idsadmin->lang("AvgMem"),
        "6" => $this->idsadmin->lang("RowProc"),
        ),
        "SELECT " .
        "trim(sql_stmtname) as stmttype, " .
        "count(*) as cnt , " .
        "TRUNC(avg(sql_runtime),4) as runtime, " .
        "TRUNC(MAX(sql_runtime),4) as maxruntime, " .
        "format_units(avg(sql_sqlmemory),'b') as mem, " .
        "sum(sql_actualrows) as ROWS, " .
        "trim(sql_stmtname) as stname, " .
        "sum(sql_runtime) as totalruntime " .
        "from syssqltrace " .
        " where sql_sid = {$sid} " .
        "group by sql_stmttype, sql_stmtname " .
        "order by 2 DESC,1 ");

        $this->idsadmin->html->add_to_output( "</TD><TD> " );
        if ( sizeof( $data ) == 0 || ! is_array($data) )
        {
            $this->idsadmin->html->add_to_output( "</TD></TR><TR><TD>" .
            $this->idsadmin->lang("NoData") .
            "</TD></TR></TABLE>" );
            $this->idsadmin->html->add_to_output("</div>");
            return;
        }

        $gdata = array();
        $row = array();
        foreach ($data as $row) {
            $gdata[ $row['STNAME'] ] = $row['CNT'];
        }

        $this->idsadmin->Charts = new Charts($this->idsadmin);
        $this->idsadmin->Charts->setType("PIE");
        $this->idsadmin->Charts->setData($gdata);
        $this->idsadmin->Charts->setTitle($dbsname . " " . $this->idsadmin->lang('SQLStmtSum'));
        $this->idsadmin->Charts->setLegendDir("vertical");
        $this->idsadmin->Charts->setWidth("100%");
        $this->idsadmin->Charts->setHeight("200");
        $this->idsadmin->Charts->Render();

        //$this->idsadmin->html->add_to_output( $mygraph->pieGraph( $gdata,
        // $this->idsadmin->lang("SQLStmtSum"),300,450) );

        $this->idsadmin->html->add_to_output( "</TD></TR></TABLE>" );

        $this->idsadmin->html->add_to_output("</div>");

    } #end default

    /**
     * A generic function to display inforamtion for a specific
     * user (session id) and by default it will show the slowest
     * 5 queries.  The "byWhat" can be changed to look for any
     * metric in the syssqltrace table.
     *
     * @param integer $sid
     * @param integer $byWhat
     * @param integer $count
     */
    function SlowestSQLbyUser($sid, $historical=false, $start_date=null, $end_date=null,$byWhat="sql_runtime" ,$count=25)
    {
        
        $i=0;
        $qry = "SELECT FIRST " . $count .
        	" sql_id ";
        
        if ($historical == true)
        {
        	$db = $this->idsadmin->get_database("sysadmin");
        
        	$qry .= " FROM mon_syssqltrace " .
        	    	" WHERE '{$start_date}'<= CAST(dbinfo('UTC_TO_DATETIME',(sql_finishtime)) as DATETIME YEAR TO DAY) and '{$end_date}'>=CAST(dbinfo('UTC_TO_DATETIME',(sql_finishtime)) as DATETIME YEAR TO DAY)" .
        	    	" AND {$byWhat} > 0";
        } else {
    	    $db = $this->idsadmin->get_database("sysmaster");
    	    $qry .= " FROM syssqltrace " .
        	    	" WHERE " . $byWhat . " > 0";
        }
        
        $qry .= ($sid==0? " " : " AND sql_sid = " . $sid ) .
        	" ORDER BY " . $byWhat . " DESC";
        
        $stmt = $db->query( $qry );
        
        while (($res = $stmt->fetch())==true)
        {
            $i++;
           
            $this->byQueryID( $res['SQL_ID'], $historical);
        }
        if ( $i == 0 )
        $this->idsadmin->html->add_to_output( $this->idsadmin->lang("NoData") );

    }

    function getXtree()
    {
        // GD is required for XTree;  So check if gd is enabled
        $extensions = get_loaded_extensions();
        $gd_enabled = in_array("gd", $extensions);
        if (!$gd_enabled)
        {
            $this->idsadmin->error($this->idsadmin->lang("Error_GD_NotEnabled"));
            return;
        }

        require_once(ROOT_PATH . "lib/XTree.php");
        $sql_id = $this->idsadmin->in['sql_id'];

        header('Content-type: image/png');
        new XTree($this->idsadmin,$sql_id);

        die();
    }

}// end class
?>
