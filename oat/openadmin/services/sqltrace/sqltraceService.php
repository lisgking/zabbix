<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2008, 2012.  All Rights Reserved
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
 * sqltrace service class , used by the flex sqltrace part of OAT
 * to get data from the database.
 */
class sqltrace {

    private $idsadmin = null;

    private $trace_table = "";
    private $hvar_table  = "";
    private $iter_table  = "";

    private $saved_trace_table = "sysadmin:mon_syssqltrace";
    private $saved_hvar_table = "sysadmin:mon_syssqltrace_hvar";
    private $saved_iter_table  = "sysadmin:mon_syssqltrace_iter";

    private $live_trace_table  = "sysmaster:syssqltrace";
    private $live_hvars_table  = "sysmaster:syssqltrace_hvar";
    private $live_iter_table   = "sysmaster:syssqltrace_iter";

    private $use_live = true;
    private $start_date;
    private $start_time;
    private $end_date;
    private $end_time;


    /**
     * constructor
     *
     */
    function __construct()
    {
        define ("ROOT_PATH","../../");
        define( 'IDSADMIN',  "1" );
        define( 'DEBUG', false);
        define( 'SQLMAXFETNUM' , 100 );

        define('STANDARD',0);
        define('PRIMARY',1);
        define('SECONDARY',2);
        define('SDS',3);
        define('RSS',4);

        set_error_handler("serviceErrorHandler");

        require_once(ROOT_PATH."lib/idsadmin.php");
        $this->idsadmin = new idsadmin(true);

        require_once ROOT_PATH."lib/feature.php";
        $this->idsadmin->phpsession->set_serverInfo($this->idsadmin);

        $this->idsadmin->render = false;

        $this->trace_table = $this->live_trace_table;
        $this->hvar_table  = $this->live_hvars_table;
        $this->iter_table  = $this->live_iter_table;

        if ( isset($_GET['mode']) && ($_GET['mode'] != 1 ) )
        {
            $this->use_live = false;
            $this->trace_table = $this->saved_trace_table;
            $this->iter_table  = $this->saved_iter_table;
            $this->hvar_table  = $this->saved_hvar_table;


            /* setup the start and end params */
            $this->start_date  = isset ( $_GET['sdate'] ) ? $_GET['sdate'] : "00:00:00";
            $this->start_time  = isset ( $_GET['stime'] ) ? $_GET['stime'] : "00:00:00";
            $this->end_date    = isset ( $_GET['edate'] )   ? $_GET['edate'] : "00:00:00";
            $this->end_time    = isset ( $_GET['etime'] )   ? $_GET['etime'] : "00:00:00";
        }
        $this->idsadmin->load_lang("sqltrace");
    }

    function def()
    {
        $this->idsadmin->load_lang("sqltrace");
        trigger_error("{$this->idsadmin->lang('InvalidArgument')}",E_USER_ERROR);
    }

    function sqltrace_directiveCheck($sqlstmtid)
    {
        $retval = "<act>directiveCheck</act>";
    	
        $sql = "SELECT cf_effective FROM sysconfig WHERE cf_name='EXT_DIRECTIVES'";
        $res = $this->doDatabaseWork($sql,"sysmaster",false);
        if (($res[0]['CF_EFFECTIVE'] != 2)&&($res[0]['CF_EFFECTIVE'] != 1)){
            $retval .= "<retcode><![CDATA[1]]></retcode><retstring>{$this->idsadmin->lang('EXTDIRECTIVE')}</retstring>";
            return $retval;
        }

        $sql = "SELECT";
        $sql.= " a.sql_statement AS sqlstmt";
        $sql.= " ,b.name AS databasename";
        $sql.= " FROM {$this->trace_table} a, sysdatabases b";
        $sql.= " WHERE a.sql_id = {$sqlstmtid}";
        $sql.= " AND a.sql_dbspartnum = b.partnum";
        $res = $this->doDatabaseWork($sql,"sysmaster",false);
        $database = trim($res[0]['DATABASENAME']);
        $sqlstmt = trim($res[0]['SQLSTMT']);

        $sql = "SELECT";
        $sql.= " id, active, directive, query";
        $sql.= " FROM sysdirectives";
        $blobcol = array('DIRECTIVE','QUERY');
        $res = $this->doDatabaseWork($sql,$database,false,false,NULL,$blobcol);

        foreach($res as $i=>$v){
        	$sqlstmt = str_replace(array("'",";","\\"), "", $sqlstmt);
			$v['QUERY'] = str_replace(array("'",";","\\"), "", $v['QUERY']);
            if(strcasecmp(trim($v['QUERY']),$sqlstmt)==0){
                $directiveStr = trim($v['DIRECTIVE']);
                $retval .= "<retcode><![CDATA[2]]></retcode>";
                $retval .= "<retstring>{$this->idsadmin->lang('SavedDir')}</retstring>";
                $retval .= "<status><![CDATA[{$v['ACTIVE']}]]></status>";
                $retval .= "<id><![CDATA[{$v['ID']}]]></id>";
                $parseout = array();
                $regex = "/ORDERED|FIRST_ROWS|ALL_ROWS|AVOID_STAR_JOIN|STAR_JOIN|AVOID_FACT\([^\(\)]*\)|FACT\([^\(\)]*\)|AVOID_INDEX_SJ\([^\(\)]*\)|INDEX_SJ\([^\(\)]*\)|AVOID_MULTI_INDEX\([^\(\)]*\)|MULTI_INDEX\([^\(\)]*\)|INDEX\([^\(\)]*\)|AVOID_INDEX\([^\(\)]*\)|AVOID_FULL\([^\(\)]*\)|FULL\([^\(\)]*\)|USE_NL\([^\(\)]*\)|AVOID_NL\([^\(\)]*\)|USE_HASH\([^\(\)]*\)|AVOID_HASH\([^\(\)]*\)/i";
               
			    $chk = preg_match_all($regex,$directiveStr,$parseout);
                if($chk == 0||$chk==false){
                    $retval .= "<list><directive><![CDATA[{$directiveStr}]]></directive><desc></desc></list>";
                    return $retval;
                    break;
                }
                foreach($parseout[0] as $i=>$v){
                    $desc = $this->descriptionFinder($v);
                    $retval .= "<list><directive><![CDATA[{$v}]]></directive><desc><![CDATA[{$desc}]]></desc></list>";
                }
                return $retval;
                break;
            }
        }

        $retval .= "<retcode><![CDATA[0]]></retcode>";
        $retval .= "<retstring>{$this->idsadmin->lang('NoneSavedYet')}</retstring>";
        return $retval;
    }

    function descriptionFinder($directiveStr)
    {
        if(preg_match("/ORDERED/i",$directiveStr) == 1){
            return $this->idsadmin->lang("ORDERED_Description");
        }

        if(preg_match("/FIRST_ROWS/i",$directiveStr) == 1){
            return $this->idsadmin->lang("FIRST_ROWS_Description");
        }

        if(preg_match("/ALL_ROWS/i",$directiveStr) == 1){
            return $this->idsadmin->lang("ALL_ROWS_Description"); 
        }
		
		if(preg_match("/AVOID_INDEX_SJ\([^\(\)]*\)/i",$directiveStr) == 1){
            return $this->idsadmin->lang("AVOID_INDEX_SJ_Description");
        }
		
		if(preg_match("/INDEX_SJ\([^\(\)]*\)/i",$directiveStr) == 1){
            return $this->idsadmin->lang("INDEX_SJ_Description");
        }
		
		if(preg_match("/AVOID_STAR_JOIN/i",$directiveStr) == 1){
            return $this->idsadmin->lang("AVOID_STAR_JOIN_Description");
        }
		
		if(preg_match("/STAR_JOIN/i",$directiveStr) == 1){
            return $this->idsadmin->lang("STAR_JOIN_Description");
        }
		
		if(preg_match("/AVOID_FACT\([^\(\)]*\)/i",$directiveStr) == 1){
            return $this->idsadmin->lang("AVOID_FACT_Description");
        }
		
		if(preg_match("/FACT\([^\(\)]*\)/i",$directiveStr) == 1){
            return $this->idsadmin->lang("FACT_Description");
        }

        if(preg_match("/AVOID_MULTI_INDEX\([^\(\)]*\)/i",$directiveStr) == 1){
            return $this->idsadmin->lang("AVOID_MULTI_INDEX_Description");
        }
		
        if(preg_match("/MULTI_INDEX\([^\(\)]*\)/i",$directiveStr) == 1){
            return $this->idsadmin->lang("MULTI_INDEX_Description");
        }

        if(preg_match("/AVOID_INDEX\([^\(\)]*\)/i",$directiveStr) == 1){
            return $this->idsadmin->lang("AVOID_INDEX_Description");
        }

        if(preg_match("/INDEX\([^\(\)]*\)/i",$directiveStr) == 1){
            return $this->idsadmin->lang("INDEX_Description");
        }
		
        if(preg_match("/AVOID_FULL\([^\(\)]*\)/i",$directiveStr) == 1){
            return $this->idsadmin->lang("AVOID_FULL_Description");
        }

        if(preg_match("/FULL\([^\(\)]*\)/i",$directiveStr) == 1){
            return $this->idsadmin->lang("FULL_Description");
        }

        if(preg_match("/USE_NL\([^\(\)]*\)/i",$directiveStr) == 1){
            return $this->idsadmin->lang("USE_NL_Description");
        }

        if(preg_match("/AVOID_NL\([^\(\)]*\)/i",$directiveStr) == 1){
            return $this->idsadmin->lang("AVOID_NL_Description");
        }

        if(preg_match("/USE_HASH\([^\(\)]*\)/i",$directiveStr) == 1){
            return $this->idsadmin->lang("USE_HASH_Description");
        }

        if(preg_match("/AVOID_HASH\([^\(\)]*\)/i",$directiveStr) == 1){
            return $this->idsadmin->lang("AVOID_HASH_Description");
        }

        return "";
    }

    function sqltrace_applyDirectives($directiveStr,$sqlstmtid,$applymode)
    {
        putenv("IFX_EXTDIRECTIVES=1");
        $retval = "<sqlstmt><![CDATA[{$sql}]]></sqlstmt><act>applyDirectives</act>";

        $sql = "SELECT";
        $sql.= " b.name AS databasename";
        $sql.= " ,a.sql_statement AS sqlstatement";
        $sql.= " FROM {$this->trace_table} a, sysdatabases b";
	   	$sql.= " WHERE a.sql_id = {$sqlstmtid}";
        $sql.= " AND a.sql_dbspartnum = b.partnum";

		$locale = $this->uniqueNonEnglishLocale();		
		if (strncmp($locale,"<faultcode>",11) == 0)
		{
			return $locale;
		}
		$res = $this->doDatabaseWork($sql,"sysmaster",false,false,$locale);

        $database = $res[0]['DATABASENAME'];
        $sqlstatement = trim($res[0]['SQLSTATEMENT']);

        $sel = "SAVE EXTERNAL DIRECTIVES /*+{$directiveStr}*/ {$applymode} FOR {$sqlstatement}";
        $res = $this->doDatabaseWork($sel,$database,false,true,$locale);

        if($res[0]!='0'){
            $retval.= "<retcode><![CDATA[1]]></retcode>";
            $retval.= "<retstring><![CDATA[{$res}]]></retstring>";
        }else{
            $retval.= "<retcode><![CDATA[0]]></retcode>";
            $retval.= "<retstring>{$this->idsadmin->lang('DirectivesSaved')}</retstring>";
        }

        return $retval;
    }

    function sqltrace_updateDirectives($directiveID,$directiveStr,$sqlstmtid,$applymode)
    {
        putenv("IFX_EXTDIRECTIVES=1");

        $sql = "SELECT";
        $sql.= " b.name AS databasename";
        $sql.= " ,a.sql_statement AS sqlstatement";
        $sql.= " FROM {$this->trace_table} a, sysdatabases b";
        $sql.= " WHERE a.sql_id = {$sqlstmtid}";
        $sql.= " AND a.sql_dbspartnum = b.partnum";

		$locale = $this->uniqueNonEnglishLocale();		
		if (strncmp($locale,"<faultcode>",11) == 0)
		{
			return $locale;
		}
		$res = $this->doDatabaseWork($sql,"sysmaster",false,false,$locale);
        $database = $res[0]['DATABASENAME'];
        $sqlstatement = trim($res[0]['SQLSTATEMENT']);

        $retval = "<sqlstmt><![CDATA[{$sql}]]></sqlstmt><act>updateDirectives</act>";
        $sql = "DELETE FROM sysdirectives WHERE id={$directiveID}";
        $res = $this->doDatabaseWork($sql,$database,false,true);
        if($res[0]!='0'){
            $retval.= "<retcode><![CDATA[1]]></retcode>";
            $retval.= "<retstring><![CDATA[{$res}]]></retstring>";
            return $retval;
        }

        if($directiveStr==""){
            $retval.= "<retcode><![CDATA[0]]></retcode>";
            $retval.= "<retstring>{$this->idsadmin->lang('DirectivesDeleted')}</retstring>";
            return $retval;
        }

        $sel = "SAVE EXTERNAL DIRECTIVES /*+{$directiveStr}*/ {$applymode} FOR {$sqlstatement}";
        $res = $this->doDatabaseWork($sel,$database,false,true,$locale);

        if($res[0]!='0'){
            $retval.= "<retcode><![CDATA[1]]></retcode>";
            $retval.= "<retstring><![CDATA[{$res}]]></retstring>";
        }else{
            $retval.= "<retcode><![CDATA[0]]></retcode>";
            $retval.= "<retstring>{$this->idsadmin->lang('DirectivesSaved')}</retstring>";
        }

        return $retval;
    }

    function sqltrace_getTables($sqlstmtid)
    {    	
        $sql = "SELECT"
        ." a.sql_tablelist AS tablelist"
        ." ,b.name AS databasename"
        ." FROM {$this->trace_table} a, sysmaster:sysdatabases b"
        ." WHERE a.sql_id = {$sqlstmtid}"
        ." AND a.sql_dbspartnum=b.partnum";

        $retarray = $this->doDatabaseWork($sql,"sysmaster",false);

        if(trim($retarray[0]['TABLELIST'])=="None"){
            return "<act>getTables</act><faultcode><![CDATA[1]]></faultcode><faultstring>{$this->idsadmin->lang('NoTraceInfo')}</faultstring>";
        }else{
            $database = $retarray[0]['DATABASENAME'];
            $tables = array();
            $tables = preg_split("/ /",trim($retarray[0]['TABLELIST']));
        }

        $retval = "<act>getTables</act><faultcode><![CDATA[0]]></faultcode><faultstring></faultstring>";
        foreach($tables as $i=>$v){
            $retval.= "<tab><name><![CDATA[{$v}]]></name></tab>";
        }
        return $retval;
    }

    function sqltrace_getIdxInfo($sqlstmtid,$idxname)
    {
        $sql = "SELECT"
        ." b.name AS databasename"
        ." FROM {$this->trace_table} a, sysmaster:sysdatabases b"
        ." WHERE a.sql_id = {$sqlstmtid}"
        ." AND a.sql_dbspartnum=b.partnum";

        $retarray = $this->doDatabaseWork($sql,"sysmaster",false);
        $database = $retarray[0]['DATABASENAME'];

        $sql = "SELECT"
        ." C.colname AS COLNAME"
        ." ,ikeyextractcolno(indexkeys,colno-1) AS COLORDER"
        ." FROM syscolumns C, sysindices I"
        ." WHERE C.tabid = I.tabid"
        ." AND I.idxname = '{$idxname}'"
        ." AND C.colno IN"
        ." (ABS(ikeyextractcolno(indexkeys,0)),"
        ." ABS(ikeyextractcolno(indexkeys,1)),"
        ." ABS(ikeyextractcolno(indexkeys,2)),"
        ." ABS(ikeyextractcolno(indexkeys,3)),"
        ." ABS(ikeyextractcolno(indexkeys,4)),"
        ." ABS(ikeyextractcolno(indexkeys,5)),"
        ." ABS(ikeyextractcolno(indexkeys,6)),"
        ." ABS(ikeyextractcolno(indexkeys,7)),"
        ." ABS(ikeyextractcolno(indexkeys,8)),"
        ." ABS(ikeyextractcolno(indexkeys,9)),"
        ." ABS(ikeyextractcolno(indexkeys,10)),"
        ." ABS(ikeyextractcolno(indexkeys,11)),"
        ." ABS(ikeyextractcolno(indexkeys,12)),"
        ." ABS(ikeyextractcolno(indexkeys,13)),"
        ." ABS(ikeyextractcolno(indexkeys,14)),"
        ." ABS(ikeyextractcolno(indexkeys,15)))";

        $retval = "<act>getIdxInfo</act>";
        $res = $this->doDatabaseWork($sql,$database,false,false);
        foreach ($res as $i=>$v){
            $order = ($v['COLORDER']>=0)?"ascending":"descending";
            $retval.= "<row>";
            $retval.= "<colname><![CDATA[{$v['COLNAME']}]]></colname>";
            $retval.= "<colorder><![CDATA[{$order}]]></colorder>";
            $retval.= "</row>";
        }

        return $retval;
    }

    function sqltrace_getIndexes($sqlstmtid)
    {
        $sql = "SELECT"
        ." a.sql_tablelist AS tablelist"
        ." ,b.name AS databasename"
        ." FROM {$this->trace_table} a, sysmaster:sysdatabases b"
        ." WHERE a.sql_id = {$sqlstmtid}"
        ." AND a.sql_dbspartnum=b.partnum";

        $retarray = $this->doDatabaseWork($sql,"sysmaster",false);

        if(trim($retarray[0]['TABLELIST'])=="None"){
            return "<act>getIndexes</act><faultcode><![CDATA[1]]></faultcode><faultstring>{$this->idsadmin->lang('NoTraceInfo')}</faultstring>";
        }else{
            $database = $retarray[0]['DATABASENAME'];
            $tables = array();
            $tables = preg_split("/ /",trim($retarray[0]['TABLELIST']));
        }
        $numOfIndexes = 0;
        $retval = "<act>getIndexes</act>";
        foreach($tables as $i=>$v){
            $sql = "SELECT"
            ." b.idxname AS indexname"
            ." FROM systables a, sysindexes b"
            ." WHERE a.tabid = b.tabid"
            ." AND a.tabname = '{$v}'";
            $res = $this->doDatabaseWork($sql,$database,false);
            if(count($res)!=0){
                $numOfIndexes++;
                $retval.="<tab>";
                $retval.="<name><![CDATA[{$v}]]></name>";
                foreach($res as $i1=>$v1){
                    $retval.="<children><tablename><![CDATA[{$v}]]></tablename><name><![CDATA[{$v1['INDEXNAME']}]]></name></children>";
                }
                $retval.="</tab>";
            }
        }

        $retval.=($numOfIndexes==0)?"<faultcode><![CDATA[2]]></faultcode><faultstring>{$this->idsadmin->lang('NoIndexesFound')}</faultstring>":"<faultcode><![CDATA[0]]></faultcode>";

        return $retval;
    }

    function getWhereClause()
    {

        $where = "  AND  CAST(dbinfo('UTC_TO_DATETIME',(sql_finishtime)) as DATETIME YEAR TO SECOND) >= '{$this->start_date} {$this->start_time}'";
        $where .= " AND  CAST(dbinfo('UTC_TO_DATETIME',(sql_finishtime)) as DATETIME YEAR TO SECOND) <= '{$this->end_date} {$this->end_time}'";
        return $where;
    }

    function sqltrace_summary()
    {
        // SQL Statement Summary Query
        $sql = "SELECT"
        ."  sql_stmttype AS stmttype"
        ." ,TRIM(sql_stmtname) AS stmtname"
        ." ,COUNT(*) AS cnt"
        ." ,TRUNC(AVG(sql_runtime),4) AS runtime"
        ." ,TRUNC(MAX(sql_runtime),4) AS maxruntime"
        ." ,AVG(sql_sqlmemory) AS avgmem"
        ." ,SUM(sql_actualrows) AS rows"
        ." ,SUM(sql_runtime) AS totalruntime"
        ." FROM {$this->trace_table}";

        if ( $this->use_live === false )
        {
            $sql .= " WHERE 1=1 ";
            $sql .= $this->getWhereClause();
        }

        //TODO: add saved date / time
        $sql .= " GROUP BY sql_stmttype, sql_stmtname"
        ." ORDER BY 1";

        $res = "<stmtsummary>{$this->doDatabaseWork($sql)}</stmtsummary>";

        // Txn Summary Query
        $sql = "SELECT " .
        " max(stmtname) as stmtname, " .
        " count(*) AS cnt , " .
        " TRUNC(avg(txsql),2) AS avgsql , " .
        " TRUNC(sum(runtime),2) AS runtime  , " .
        " TRUNC(max(maxruntime),2) AS maxruntime  , " .
        " TRUNC(avg(mem),2) AS avgmem , " .
        " TRUNC(avg(avgrows),2)  as avgrows, " .
        " sum(rows) as rows " .
        " FROM ( " .
        " SELECT " .
        " 'TXNS' AS stmtname, " .
        " count(*) as txsql, " .
        " sql_sid*4294967295 + sql_begintxtime AS cnt , " .
        " TRUNC(avg(sql_runtime),4) AS runtime, " .
        " TRUNC(MAX(sql_runtime),4) AS maxruntime, " .
        " AVG(sql_sqlmemory) AS mem, " .
        " AVG(sql_actualrows) AS AVGROWS , " .
        " SUM(sql_actualrows) AS ROWS " .
        " from {$this->trace_table} " .
        " where sql_stmttype >0 ";

        if ( $this->use_live === false )
        {
            $sql .= $this->getWhereClause();
        }

        $sql .= " group by 3 ) ";

        $res .= "<txnsummary>{$this->doDatabaseWork($sql)}</txnsummary>";

        return $res;
    }

    /**
	 * Get sqltrace data by statement type
	 * 
	 * @param $sqltype - statement type, -1 indicates all types
	 * @param $rows_per_page - null or -1 indicates all rows
	 * @param $page - current page number
	 * @param $sort_col - sort columns for order by clause 
	 * @param $search_pattern - string to search for in sql statement
     **/
    function sqltrace_sqltype($sqltype = -1, $rows_per_page = null, $page = 1, $sort_col = null, $search_pattern = null)
    {
    	// Get the list of available statements (for the drop-down to switch types)
        $sql = "SELECT -1 as sql_stmttype, " .
        "'ALL' as sql_stmtname " .
        "FROM systables where tabid=1 " .
        "UNION " .
        "SELECT unique sql_stmttype, " .
        "trim(sql_stmtname) as sql_stmtname " .
        "FROM {$this->trace_table} " ;
        if ( $this->use_live === false )
        {

            $sql .= " WHERE 1=1 ";
            $sql .= $this->getWhereClause();
        }

        $sql .= "ORDER BY sql_stmtname";

        $res = "<stmttypes>{$this->doDatabaseWork($sql)}</stmttypes>";

        $condition = ($sqltype == -1)? " > 0 ": "= $sqltype ";

        // Get the trace data for the specified type
        $sql = "SELECT " .
            " '0'||MAX(sql_id) as sql_id," .
            " count(*) as cnt, " .
            " TRUNC(sum(sql_runtime)/count(*),4) as runtime, " .
            " TRUNC(sum(sql_lockwttime),4) as lockwait,  " .
            " TRUNC(sum(sql_totaliowaits),4) as iowait, " .
            " cast( dbinfo('UTC_TO_DATETIME',MAX(sql_finishtime)) as DATETIME HOUR TO SECOND) as finishtime, " .
            " TRIM(SUBSTR(sql_statement,0,500)) as sql_statement " .
            " FROM " . $this->trace_table .
            " WHERE sql_stmttype " . $condition ;

        if ( $this->use_live === false )
        {
            $sql .= $this->getWhereClause();
        }
        
        if ($search_pattern != null)
        {
        	$sql .= " AND sql_statement like '%{$search_pattern}%'";
        }
        
        /** Cheetah2 has the new stmtlen and the stmthash info */
        if (  Feature::isAvailable( Feature::CHEETAH2, $this->idsadmin) ) 
        {
            $sql .= " GROUP BY sql_stmtlen, sql_stmthash, sql_statement ";
        } else {
            $sql .= " GROUP BY sql_statement ";
        }
        
        if ($sort_col == null)
        {
        	// default sort order
        	$sort_col = "cnt desc, runtime desc";
        }

		$locale = $this->uniqueNonEnglishLocale();
		if (strncmp($locale,"<faultcode>",11) == 0)
		{
			return $locale;
		}
		$res .= "<sqltype>{$this->doDatabaseWork($this->idsadmin->transformQuery($sql,$rows_per_page,$page,$sort_col),"sysmaster",true,false,$locale)}</sqltype>";
		
		// Get count for sql statements
		$res .= "<sqltype_count>{$this->doDatabaseWork($this->idsadmin->createCountQuery($sql), "sysmaster")}</sqltype_count>";

        return $res;
    }

    /**
	 * Get a list all executions of a particular sql statement
	 * 
	 * @param $sql_id - id of the sql statement
	 * @param $rows_per_page - null or -1 indicates all rows
	 * @param $page - current page number
	 * @param $sort_col - sort columns for order by clause 
     **/
    function sqltrace_sqllist($sql_id = 0, $rows_per_page = null, $page = 1, $sort_col = null)
    {

        $sql = "select TRIM(sql_statement) as sql_statement " .
        " FROM {$this->trace_table} " .
        " WHERE sql_id = " . $sql_id;

        $res = "<sqlstmt>{$this->doDatabaseWork($sql)}</sqlstmt>";

        $sql = "select " .
        " '0'||B.sql_id as ID, " .
        " B.sql_sid as SID, " .
        " B.sql_uid as UID, " .
        " TRUNC(B.sql_runtime,5)  as runtime, " .
        " TRUNC(B.sql_rowspersec,5) as rowspersec, " .
        " B.sql_actualrows as rows, " .
        " TRUNC(B.sql_lockwttime,5) as lockwait, " .
        " TRUNC(B.sql_totaliowaits,5) as iowait  " .
        " FROM {$this->trace_table} A, " .
        "{$this->trace_table} B " .
        " WHERE A.sql_statement = B.sql_statement " .
        " AND A.sql_id = " . $sql_id;

        $res .= "<sqllist>{$this->doDatabaseWork($this->idsadmin->transformQuery($sql,$rows_per_page,$page,$sort_col))}</sqllist>";
        
        $res .= "<sqllist_count>{$this->doDatabaseWork($this->idsadmin->createCountQuery($sql))}</sqllist_count>";

        return $res;
    }

   /**
	 * Get a list all recent transactions
	 * 
	 * @param $rows_per_page - null or -1 indicates all rows
	 * @param $page - current page number
	 * @param $sort_col - sort columns for order by clause 
     **/
    function sqltrace_txns ($rows_per_page = null, $page = 1, $sort_col = null)
    {
        // Transactions Query
        $sql = "SELECT " .
        "sql_begintxtime AS begintime, " .
        "sql_begintxtime AS txtime , " .
        "sql_sid, " .
        "count(*) AS cnt , " .
        "TRUNC(AVG(sql_runtime),4) AS runtime, " .
        "TRUNC(MAX(sql_runtime),4) AS maxruntime, " .
        "TRUNC(AVG(sql_sqlmemory),4) AS avgmem, " .
        "sum(sql_actualrows) AS ROWS " .
        "from {$this->trace_table} " .
        " where sql_stmttype >0 " ;

        if ( $this->use_live === false )
        {

            $sql .= $this->getWhereClause();
        }
        $sql .= "group by sql_begintxtime, sql_sid ";
        
        if ($sort_col == null)
        {
        	// default sort order
        	$sort_col = "begintime DESC ";
        }

        $res = "<txn>{$this->doDatabaseWork($this->idsadmin->transformQuery($sql,$rows_per_page,$page,$sort_col))}</txn>";
        
        // Get count of transactions
        $res .= "<txn_count>{$this->doDatabaseWork($this->idsadmin->createCountQuery($sql))}</txn_count>";

        return $res;
    }

    /**
	 * Get information about a particular transaction group
	 * 
	 * @param $sql_sid - session id of the transaction group
	 * @param $begintime - begin time of the transaction group
	 * @param $rows_per_page - null or -1 indicates all rows
	 * @param $page - current page number
	 * @param $sort_col - sort columns for order by clause 
     **/
    function sqltrace_txngrp ($sql_sid=0, $begintime=0, $rows_per_page = null, $page = 1, $sort_col = null)
    {

        // Get summary information about a transaction group
    	$sql = "SELECT  " .
        " COUNT(*)               AS sql_numstmt, " .
        " SUM( sql_runtime )     AS sql_runtime, " .
        " SUM( sql_pgreads )     AS sql_pgreads, " .
        " SUM( sql_bfreads )     AS sql_bfreads, " .
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
        " format_units(SUM(sql_sqlmemory),'b') AS sqlmemory, " .
        " TRUNC( SUM( sql_runtime ) ,5) AS sql_runtime, " .
        " CASE WHEN (SUM( sql_bfreads ) == 0 OR " .
        " SUM( sql_bfreads ) < SUM( sql_pgreads )) THEN '0.0%' " .
        " ELSE TRUNC(((SUM(sql_bfreads) - SUM(sql_pgreads)) * 100.0 / " .
        " SUM(sql_bfreads)),2)||'%' end AS rdcache, " .
        " CASE WHEN (SUM( sql_bfwrites ) == 0 OR " .
        " SUM( sql_bfwrites ) < SUM( sql_pgwrites )) THEN '0.0%' " .
        " ELSE TRUNC(((SUM(sql_bfwrites) - SUM(sql_pgwrites)) * 100.0 / " .
        " SUM(sql_bfwrites)),2)||'%' end AS wrcache " .
        " FROM " . $this->trace_table .
        " WHERE sql_sid = " . $sql_sid .
        " AND sql_logspace >= 0 " .
        " AND sql_begintxtime = " . $begintime ;

        $res = "<txngrp_summary>{$this->doDatabaseWork($sql)}</txngrp_summary>";

        // Get the list of sql statements associated with this transaction group
        $sql = "select " ;
        if ( $_GET['mode'] == 2)
        {
            # $sql .= " sql_id / 4294967296 as id ,"   ;
            $sql .= " '0'||sql_id as id, " ;
        }
        else
        {
            $sql .= " sql_id as id, " ;
        }
        $sql .= " TRUNC(sql_runtime,5)  as runtime, " .
        " TRUNC(sql_rowspersec,5) as rowspersec, " .
        " sql_actualrows as rows, " .
        " TRUNC(sql_lockwttime,5) as lockwait, " .
        " TRUNC(sql_totaliowaits,5) as iowait,  " .
        " TRIM(sql_statement) as sql_statement, " .
        " sql_begintxtime " .
        " FROM " . $this->trace_table .
        " WHERE sql_begintxtime = " . $begintime .
        " AND sql_sid = " . $sql_sid ;

        $locale = $this->uniqueNonEnglishLocale();
        if (strncmp($locale,"<faultcode>",11) == 0)
        {
        	return $locale;
        }
        $res .= "<txngrp_list>{$this->doDatabaseWork($this->idsadmin->transformQuery($sql,$rows_per_page,$page,$sort_col),"sysmaster",true,false,$locale)}</txngrp_list>";
        
        // Get count of sql statements in the transaction group 
        $res .= "<txngrp_count>{$this->doDatabaseWork($this->idsadmin->createCountQuery($sql),"sysmaster",true,false,$locale)}</txngrp_count>";

        return $res;
    }

    function sqltrace_settings()
    {
        // SQL Trace Settings Query
        $sql = "SELECT " .
        "CASE ".
        "WHEN bitval(flags,'0x0004') == 1 THEN 'Suspended' " .
        "WHEN bitval(flags,'0x0001') == 1 THEN 'On' " .
        "WHEN bitval(flags,'0x0002') == 1 THEN 'Off' " .
        "END AS state, " .
        "CASE WHEN  bitval (flags, '0x7F00') == 1 THEN 'High' " .
        "WHEN bitval(flags, '0x5F00') == 1 THEN 'Med' " .
        "WHEN bitval(flags, '0x1600') == 1 THEN 'Low' END AS level, " ;

         /* if where are using 11.50.UC3+ then determine which mode
         * else just use 'Global' as the mode.
         */
         if ( Feature::isAvailable( Feature::CHEETAH2_UC3, $this->idsadmin) )
         {
              $sql .= "CASE WHEN bitval(flags,'0x0010')>0 THEN 'Global' ";
              $sql .= "ELSE 'User' END AS mode, " ;
         }
         else
         {
              $sql .= " 'Global' as mode , ";
         }

         $sql .= " bitval(flags,'0x0800') as trc_procedures , " .
        "bitval(flags,'0x4000') as trc_tabnames, " .
        "bitval(flags,'0x0100') as trc_dbsname, " .
        "bitval(flags,'0x2000') as trc_hostvars, " .
        "ntraces, tracesize, " .
        "(CURRENT - duration UNITS second)::DATETIME YEAR TO SECOND AS starttrace " .
        "from syssqltrace_info;";

        $res = "<tracesettings>{$this->doDatabaseWork($sql)}</tracesettings>";

        return $res;
    }

    /**
     * Get info on database level trace settings
     */
    function sqltrace_db_settings()
    {

        // if we are not using a 11.50.UC3+ version of the server
        // just return;
        if ( ! Feature::isAvailable( Feature::CHEETAH2_UC3, $this->idsadmin) )
        {
            return "";
        }


        // Get list of all databases
        $sql = "SELECT trim(name) as name from sysdatabases";
        $alldbs = $this->doDatabaseWork($sql,"sysmaster",false);

        // Get list of traced databases
        $sql = "SELECT sysadmin:task('set sql tracing database list') as name from sysmaster:sysdual";
        $traceddbs = $this->doDatabaseWork($sql,"sysmaster",false);

        if ( count($traceddbs) == 1 )
        {
            if ( $traceddbs[0]['NAME'] == "SQLTrace is tracing all databases.")
            {
                $traceddbs = $alldbs;
                $alldbs = array();
            }
            else if ($traceddbs[0]['NAME'] == "Not authorized to run command (set sql tracing database list).")
            {
            	$traceddbs = array();
            }
            else
            {
                //the list command returns a 'space' delimited list - here we put it into
                // an array.
                $names = $traceddbs[0]['NAME'];
                $traceddbs = array();
                $traceddbs = preg_split("/ /" , $names );
            }
        }

        $res = "<untracedDBs>";
        foreach ( $alldbs as $k => $v )
        {

            if ( in_array($v['NAME'] , $traceddbs , true ) )
            {
                continue;
            }
            $res .= "<row><NAME><![CDATA[{$v['NAME']}]]></NAME></row>";
        }
        $res .= "</untracedDBs>";

        $res .= "<tracedDBs>";
        foreach ( $traceddbs as $k => $v )
        {
            if ( is_array($v) && isset( $v['NAME'] ) === true )
            {
                $res .= "<row><NAME><![CDATA[{$v['NAME']}]]></NAME></row>";
            }
            else
            {
                $res .= "<row><NAME><![CDATA[{$v}]]></NAME></row>";
            }


        }

        $res .= "</tracedDBs>";

        $res .= $this->get_traced_users();
        return $res;
    }

    /*
     * check if the saved data procedure has been created
     * and that the tables exist .
     */


    /*
     * get info ( max / min dates ) from the saved data table.
     */
    function getSavedDataInfo()
    {
        /* dont get the saved dates in live mode */
        if ( isset( $_GET['mode']) && $_GET['mode'] == 1 )
        {
            return;
        }

        $sql  = "SELECT ";
        $sql .= " dbinfo('UTC_TO_DATETIME',MIN(sql_finishtime)) as min ";
        $sql .= ",dbinfo('UTC_TO_DATETIME',MAX(sql_finishtime)) as max ";
        $sql .= " FROM {$this->saved_trace_table}";
        $ret = $this->doDatabaseWork($sql,"sysadmin",false);

        $res = "";
        if ( isset ( $ret[0]['MIN'] ) )
        {
            $split      = preg_split("/ /",$ret[0]['MIN']);
            $start_date = $split[0];
            $start_time = $split[1];
            $split      = preg_split("/ /",$ret[0]['MAX']);
            $end_date   = $split[0];
            $end_time   = $split[1];
            $res = "<savedInfo>";
            $res .= "<start_date>{$start_date}</start_date>";
            $res .= "<start_time>{$start_time}</start_time>";
            $res .= "<end_date>{$end_date}</end_date>";
            $res .= "<end_time>{$end_time}</end_time>";
            $res .= "</savedInfo>";

        }
        return $res;
    }

    function sqltrace_info()
    {
        $sql = "SELECT flags, " .
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

        $res = "<traceinfo>{$this->doDatabaseWork($sql)}</traceinfo>";

        return $res;
    }

    function set_sqltrace_settings($status="off", $ntraces=0, $tracesize=0)
    {
        define ('TRC_DBSNAME'    , 0x0100 );
        define ('TRC_STATEMENT'  , 0x0200 );
        define ('TRC_RSAM_STATS' , 0x0400 );
        define ('TRC_PROCEDURES' , 0x0800 );
        define ('TRC_ITERATORS'  , 0x1000 );
        define ('TRC_HOST_VARS'  , 0x2000 );
        define ('TRC_TABNAMES'   , 0x4000 );

        $tracetables   = isset( $_GET['tracetables']  ) ? $_GET['tracetables']  : false;
        $tracedbnames  = isset( $_GET['tracedbnames'] ) ? $_GET['tracedbnames'] : false;
        $traceprocs    = isset( $_GET['traceprocs']   ) ? $_GET['traceprocs']   : false;
        $tracehostvar  = isset( $_GET['tracehostvar'] ) ? $_GET['tracehostvar'] : false;
        $traceddbs     = isset( $_GET['dbs']          ) ? $_GET['dbs']          : false;
        $tracedusers   = isset( $_GET['users']        ) ? $_GET['users']        : false;
        $clearbuffer   = isset( $_GET['clearbuffer']  ) ? $_GET['clearbuffer']  : false;

        // what mode are we tracing - global / user . default to global.
        $mode = strtolower( isset($_GET['tracemode']) ? $_GET['tracemode'] : 'global' );

        // setup our tracing level ..  By default we'll use a 'low' setting .
        $level = ( TRC_RSAM_STATS | TRC_STATEMENT | TRC_ITERATORS );

        if ( $tracetables === 'true' )
        {
            $level = ( $level | TRC_TABNAMES );
        }
        
        if ( $tracedbnames === 'true' )
        {
            $level = ( $level | TRC_DBSNAME );
        }

        if ( $traceprocs === 'true' )
        {
            $level = ( $level | TRC_PROCEDURES );
        }

        if ( $tracehostvar === 'true' )
        {
            $level = ( $level | TRC_HOST_VARS );
        }


        switch ( strtolower($status) )
        {
            case "off":
                $sql ="SELECT task( 'set sql tracing off' ) AS info FROM systables WHERE tabid=1";
                break;
            case "on":
                $sql = "SELECT task( 'set sql tracing on' , " .
                   " {$ntraces}, '{$tracesize}b', '{$level}' , '{$mode}' ) AS info" .
                   " FROM systables WHERE tabid=1";
                break;
            case "suspend":
                $sql ="SELECT task( 'set sql tracing suspend' ) AS info FROM systables WHERE tabid=1";
                break;
            case "resume":
                $sql ="SELECT task( 'set sql tracing resume' ) AS info FROM systables WHERE tabid=1";
                break;
        }

        // clear out the previous trace info by first turning off tracing ..
        if ( $clearbuffer == "true" )
        {
            $sql2 ="SELECT task( 'set sql tracing off' ) AS info FROM systables WHERE tabid=1";
            $res = "<msg>{$this->doDatabaseWork($sql2, "sysadmin")}</msg>";
        }

        $res = "<msg>{$this->doDatabaseWork($sql, "sysadmin")}</msg>";

        // only need this is we are using 11.50.UC3+
        if ( Feature::isAvailable( Feature::CHEETAH2_UC3, $this->idsadmin) )
        {

            $dbs = preg_split("/ /",$traceddbs);
            if ( count($dbs) > 0 )
            {
                // lets clear out any databases we currently have.
                $sql = "SELECT task('set sql tracing database clear') AS info FROM systables WHERE tabid=1";
                $this->doDatabaseWork($sql, "sysadmin");

                foreach ( $dbs as $k => $v)
                {
                    if ( $v != "" )
                    {
                        $sql = " select task('set sql tracing database add','{$v}') from systables where tabid = 1 ";
                        $this->doDatabaseWork($sql, "sysadmin");
                    }
                }

            }

            $users =  preg_split("/ /",$tracedusers);
            if ( count($users) > 0 )
            {
                $adduser = "";

                // lets clear out any users we currently have.
                // there is a bug in the server where clear doesnt remove the flag from current users
                // so we'll clear then re-add users then remove them. This will ensure that our tracelist is in sync with
                // the server bits.
                $sql = "SELECT task( 'set sql tracing user clear' ) AS info FROM systables WHERE tabid=1";
                $this->doDatabaseWork($sql, "sysadmin");
                // re-add the users that have the bit set. - this puts them back on the sql trace list
                $sql = "select  task('set sql tracing user add ' , name ) from  ( select unique(trim(username)) as name from sysmaster:sysscblst
where bitand(flags,'0x00010000') > 0 );";
                $this->doDatabaseWork($sql, "sysadmin");
                // now remove them all.
                $sql = "select  task('set sql tracing user remove ' , name ) from  ( select unique(trim(username)) as name from sysmaster:sysscblst
where bitand(flags,'0x00010000') > 0 );";
                $this->doDatabaseWork($sql, "sysadmin");

                foreach ( $users as $k => $v )
                {
                    if ( $v == "" )
                    {
                        continue;
                    }

                    $sql = " select task('set sql tracing user add','{$v}') from systables where tabid = 1 ";
                    $this->doDatabaseWork($sql, "sysadmin");
                }
                $res .= $this->get_traced_users();
            }
        } // end check for server version.

        return $res;
    }

    /**
     * get the users that currently have tracing ..
     */

    function get_traced_users()
    {
        $sql = "select task('set sql tracing user list') as list from sysmaster:sysdual ";
        $users = $this->doDatabaseWork($sql,"sysadmin",false);
        
        if ( count($users) == 1 )
        {
            if ( $users[0]['LIST'] == "NO USERS"  || $users[0]['LIST'] == "ALL USERS" || $users[0]['LIST'] == "NO TRACING" 
            	 || $users[0]['LIST'] == "Not authorized to run command (set sql tracing user list).")
            {
                $users = array();
            }
        }
        $ret = "<users>";
        if ( isset ( $users[0]['LIST'] ) )
        {
            $u = preg_split("/ /",$users[0]['LIST']);
            foreach ( $u as $k => $v )
            {
                if ( $v == "" )
                {
                    continue;
                }
                $ret .= "<name>{$v}</name>";

            }
        }
        $ret .= "</users>";

        return $ret;
    }
    /**
     * get the profile info for the sql id.
     */
    function sqltrace_profile()
    {
        ( isset ( $_GET['sqlid'] ) ) ? $sqlid = $_GET['sqlid'] : $sqlid = "";

        if ( $sqlid == "" )
        {
            return;
            $this->idsadmin->load_lang("sqltrace");
            trigger_error("{$this->idsadmin->lang('NoSQLID')}",E_USER_ERROR);
        }

        //$sqlid = intval($sqlid);

        $qry = "SELECT  " .
        " '0'||sql_id as sql_id,"   .
        " sql_sid, " .
        " sql_uid,  " .
        " sql_stmttype, " .
        " TRIM(sql_stmtname) AS sql_stmtname, " .
        " dbinfo('UTC_TO_DATETIME',sql_finishtime) AS sql_finishtime, " .
        " TRUNC(sql_runtime,7) AS sql_runtime, " .
        " sql_pgreads, " .
        " sql_bfreads, " .
        " sql_bfidxreads, " .
        " sql_bfwrites, " .
        " sql_pgwrites, " .
        " TRUNC(sql_rdcache,2) AS sql_rdcache, " .
        " TRUNC(sql_wrcache,2) AS sql_wrcache, " .
        " sql_bfreads-sql_bfidxreads AS sql_databufreads, " .
        " sql_lockreq, " .
        " sql_lockwaits,  " .
        " sql_lockwttime, " .
        " format_units(sql_logspace,'b') AS sql_logspace, " .
        " sql_sorttotal, " .
        " sql_sortdisk, " .
        " sql_sortmem, " .
        " sql_numtables, " .
        " sql_numiterators, " .
        " sql_executions, " .
        " TRUNC(sql_totaltime,5) AS sql_totaltime, " .
        " TRUNC(sql_avgtime,5) AS sql_avgtime, " .
        " TRUNC(sql_maxtime,5) AS sql_maxtime,  " .
        " sql_numiowaits,   " .
        " TRUNC(sql_totaliowaits,5) AS sql_totaliowaits, " .
        " TRUNC(sql_avgiowaits,5)   AS sql_avgiowaits, " .
        " TRUNC(sql_rowspersec,5)   AS sql_rowspersec, " .
        " sql_estcost, " .
        " sql_estrows, " .
        " sql_actualrows, " .
        " sql_sqlerror, " .
        " sql_isamerror, " .
        " sql_isollevel, " .
        " TRIM(sql_statement) AS sql_statement, " .
        " TRIM(sql_database) AS DBNAME, " .
        " format_units(sql_sqlmemory,'b') AS sql_sqlmemory ";


        if (  Feature::isAvailable( Feature::CHEETAH2, $this->idsadmin) )
        {
            $qry .= " ,sql_pdq, sql_num_hvars ";
            $has_hvars = true;
        }
        else
        {
            $qry .= " ,'NA' AS sql_pdq, 0 AS sql_num_hvars ";
            $has_hvars = false;
        }


        $qry .=
	        " FROM {$this->trace_table} " .
	        " WHERE sql_id = " . $sqlid;


		$locale = $this->uniqueNonEnglishLocale();	
		if (strncmp($locale,"<faultcode>",11) == 0)
		{
			return $locale;
		}


		$ret = $this->doDatabaseWork($qry,"sysmaster",true,false,$locale);


        if ( $ret == "" )
        {
            $ret = "<faultcode>-1</faultcode><faultstring>{$this->idsadmin->lang('NoRowsFound')}</faultstring>";
        }

        if ( $has_hvars === true )
        {
            if (  Feature::isAvailable( Feature::PANTHER_UC2, $this->idsadmin) )
            {
                $qry = "SELECT * FROM {$this->hvar_table} WHERE sql_id = {$sqlid} ";
            } else {
                // For server versions below 11.70.xC2, need to work around server defect idsdb00218057
                // by forcing the query not to use the index.
                $qry = "SELECT {+ FULL( $this->hvar_table ) } * FROM {$this->hvar_table} WHERE sql_id = {$sqlid} ";
            } 

            $hvars = $this->doDatabaseWork($qry);
            $ret .= "<hvars>{$hvars}</hvars>";
        }

        return $ret;
    }

    function mode($m , $s="" , $ed="")
    {
        if ( $m == 1 )
        {
            $m = "LIVE";
        }
        else
        {
            $m = "SAVED";
        }

        $mode = array("MODE" => $m , "START_DATE" => $s , "END_DATE" => $ed);
        //$this->idsadmin->phpsession->set_sqltracemode($mode);
        return $this->getSavedDataInfo();
    }
    
    /* 
     * Figure out if the db locale is non-English or not.
     *
     * We do this to set CLIENT_LOCALE and DB_LOCALE when querying for data about non-English databases
     * in system databases like sysadmin, sysmaster etc. This is because the data in these system
     * databases can be non-English.
     *
     * parameter - $dbname => name of database being queried about in system databases.
     */

     private function processLocale($dbname)
     {
     	$loc = $this->idsadmin->get_locale($dbname);
        if (strncmp($loc,"en_",3) == 0)
        	{ return NULL; }
        else
        	{ return $loc; }
     }

	/* Tables is system databases like sysmaster, sysadmin could potentially have data in multiple locales.
	 * An example scenario is db A having Japanese char table names and db B having Chinese char table names.
	 * SQL Tracing would create data with these names in the above mentioned system db tables. Hence a table would
	 * have data in multiple locales. The assumption below is that a typical customer scenario would be to have
	 * databases in only one non-English locale.
	 *
	 * return - unique non-English locales
	 */

	private function uniqueNonEnglishLocale()
	{
		$locale = NULL;
		$unique_locale = "select unique(dbs_collate) from sysdbslocale where dbs_collate NOT LIKE 'en_%'";
		//$unique_locale = "select unique(dbs_collate) from sysdbslocale ";

		$locale_res = $this->doDatabaseWork($unique_locale,"sysmaster",false);

		if ( count($locale_res) > 1 )
		{
			$ret = "<faultcode>-1</faultcode><faultstring>{$this->idsadmin->lang('NotYetImpl')}</faultstring>";
			return $ret;

		} else if ( count($locale_res) == 1 )
		{
			$locale = $locale_res[0]['DBS_COLLATE'];
		}

		return $locale;
	}

    function doDatabaseWork($sel,$dbname="sysmaster",$xml=true, $useExecute=false,$locale=NULL,$blobcol=array())
    {

        if ( $xml === true )
        {
            $ret = "";
        }
        else
        {
            $ret = array();
        }

		if (is_null($locale)) {
			$db = $this->idsadmin->get_database($dbname);
		} else {
			require_once(ROOT_PATH."lib/database.php");
			$db = new database($this->idsadmin,$dbname,$locale,"","");
		}

        if($useExecute){
            $stmt = $db->prepare($sel);
            $errInfo = $db->errorInfo();

            if($errInfo[1]!=0){
                return $errInfo[1]." - ".$errInfo[2];
            }

            $stmt->execute();
            //For executeOnly, we return error code to report execution status, but not returning rows.
            $errInfo = $db->errorInfo();
            $stmt->closeCursor();
            return $errInfo[1]." - ".$errInfo[2];
        }


        while (1 == 1)
        {
           	$stmt = $db->query($sel,$xml);

            while ($row = $stmt->fetch() )
            {
                if ( $xml === true )
                {
                    $ret .= $row;
                }
                else
                {
                    $newRow = array();
                    foreach($row as $i=>$v){
                        if(in_array($i,$blobcol,true)){
                            $content = stream_get_contents($v);
                            $newRow[$i] = $content;
                        }else{
                            $newRow[$i] = $v;
                        }
                    }
                    $ret[] = $newRow;
                }
            }
            $err = $db->errorInfo();
            if ( $err[2] == 0 )
            {
                $stmt->closeCursor();
                break;
            }
            else
            {
                $stmt->closeCursor();
                continue;
            }
        }

        return $ret;
    }
} // end class


/**
 * handle errors.
 *
 */
function serviceErrorHandler($errno, $errstr, $errfile, $errline)
{

    if ( $errno != E_USER_WARNING )
    {
        $faultString = "<faultstring>{$errstr} - {$errfile} - {$errline}</faultstring>";
    }
    else
    {
        $faultString = "<faultstring>{$errstr}</faultstring>";
    }
    echo <<< EOF
    <faultcode>$errno</faultcode>
    {$faultString}
EOF;
}

// set the timeout to not timeout ..
set_time_limit(0);


/**
 * the 'runner' part of the service.
 */
$sqlT = new sqltrace();

if ( empty($_GET['act']) )
{
    $call = "def";
}
else
{
    $call = $_GET['act'];
}


$rows_per_page = isset($_GET['rows_per_page'])? $_GET['rows_per_page']:-1;
$page = isset($_GET['page'])? $_GET['page']:-1;
$sort_col = isset($_GET['sort_col'])? $_GET['sort_col']:null;
$search_pattern = isset($_GET['search_pattern'])? $_GET['search_pattern']:null;

switch ( $call )
{
    case "getSavedDataInfo":
        echo $sqlT->getSavedDataInfo();
        break;
    case "tracesettings":
        echo $sqlT->sqltrace_settings();
        echo $sqlT->getSavedDataInfo();
        break;
    case "summary":
        echo $sqlT->sqltrace_summary();
        echo $sqlT->sqltrace_settings();
        echo $sqlT->getSavedDataInfo();
        break;
    case "sqltype":
        $sqltype = isset($_GET['sqltype'])? $_GET['sqltype']:-1;
        echo $sqlT->sqltrace_sqltype($sqltype, $rows_per_page, $page, $sort_col, $search_pattern);
        echo $sqlT->sqltrace_settings();
        echo $sqlT->getSavedDataInfo();
        break;
    case "sqllist":
        echo $sqlT->sqltrace_sqllist($_GET['sql_id'], $rows_per_page, $page, $sort_col);
        echo $sqlT->sqltrace_settings();
        echo $sqlT->getSavedDataInfo();
        break;
    case "txns":
        echo $sqlT->sqltrace_txns($rows_per_page, $page, $sort_col, $search_pattern);
        echo $sqlT->sqltrace_settings();
        echo $sqlT->getSavedDataInfo();
        break;
    case "txngrp":
        echo $sqlT->sqltrace_txngrp($_GET['sql_sid'], $_GET['begintime'], $rows_per_page, $page, $sort_col, $search_pattern);
        echo $sqlT->sqltrace_settings();
        echo $sqlT->getSavedDataInfo();
        break;
    case "profile":
        echo $sqlT->sqltrace_profile();
        break;
    case "admin":
        echo $sqlT->sqltrace_info();
        echo $sqlT->sqltrace_settings();
        echo $sqlT->getSavedDataInfo();
        echo $sqlT->sqltrace_db_settings();
        break;
    case "settrace":
        echo $sqlT->set_sqltrace_settings($_GET['state'], $_GET['ntraces'],$_GET['tracesize']);
        echo $sqlT->sqltrace_info();
        echo $sqlT->sqltrace_settings();
        echo $sqlT->getSavedDataInfo();
        break;
    case "getIndexes":
        echo $sqlT->sqltrace_getIndexes($_GET['sqlstmtid']);
        break;
    case "getTables":
        echo $sqlT->sqltrace_getTables($_GET['sqlstmtid']);
        break;
    case "getIdxInfo":
        echo $sqlT->sqltrace_getIdxInfo($_GET['sqlstmtid'],$_GET['idxname']);
        break;
    case "directiveCheck":
        echo $sqlT->sqltrace_directiveCheck($_GET['sqlstmtid']);
        break;
    case "applyDirectives":
        echo $sqlT->sqltrace_applyDirectives($_GET['directiveStr'],$_GET['stmtid'],$_GET['applymode']);
        break;
    case "updateDirectives":
        echo $sqlT->sqltrace_updateDirectives($_GET['directiveID'],$_GET['directiveStr'],$_GET['stmtid'],$_GET['applymode']);
        break;
    default:
        echo $sqlT->def();
        break;
}

die();
?>
