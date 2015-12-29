<?php
/***************************************************************************
 *  (c) Copyright IBM Corporation. 2009, 2011.  All Rights Reserved
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
 ***************************************************************************/


/* SQL Explorer */

class sqltrace {

    public $idsadmin;
    function __construct(&$idsadmin)
    {
        $this->idsadmin = $idsadmin;
        $this->idsadmin->load_lang("sqltrace");
        $this->idsadmin->load_template("template_sqltrace");
        $this->idsadmin->html->set_pagetitle($this->idsadmin->lang("SQLExplorer"));
        $this->idsadmin->setCurrMenuItem("sqlexplorer");
    }

    /**
     * The run function is what index.php will call.
     */
    function run()
    {
        $this->idsadmin->setCurrMenuItem("sqlexplorer");
        switch($this->idsadmin->in['do'])
        {
            case 'getXtree':
                $this->getXtree();
                break;
            default:
                $this->renderSqltrace();
                break;
        }
    } # end function run

    /**
     * Load the sqltrace swf using the template file
     */
    function renderSqltrace()
    {
        // let check if the procedure to capture data is in place
        // and that the saved tables have been created ..
        require_once ROOT_PATH."lib/feature.php";
        if ( Feature::isAvailable(Feature::CHEETAH2_UC3, $this->idsadmin) )
        {
        	$this->checkSavedDataSetup();
        }
        // render the flex stuff
        $this->idsadmin->html->add_to_output($this->idsadmin->template["template_sqltrace"]->renderSqltrace($this->idsadmin->phpsession->get_lang()));
    }

    function checkSavedDataSetup()
    {
    	// do not create the tables in read-only mode
    	if ($this->idsadmin->isreadonly())
    	{
    		return;
    	}
    	
    	// only try and create the tables if we are on a primary server ..
    	if ( $this->idsadmin->phpsession->serverInfo->isPrimary() === false )
    	{
    		return;
    	}

        $savedTable = preg_split("/:/",$this->saved_trace_table);

        $offset = count($savedTable) - 1;

        $sel = " SELECT procname FROM sysprocedures WHERE procname = 'sql_showsnap' ";
        $db  = $this->idsadmin->get_database("sysadmin",false);
        $stmt = $db->query($sel);
        $ret = $stmt->fetchAll();

        if ( count($ret) < 1 )
        {
            //error_log("not found");

			$sel = "SELECT tabid FROM systables WHERE tabname = 'mon_syssqltrace_info'";
			$stmt = $db->query($sel);
        	$ret = $stmt->fetchAll();
			if ( count($ret) < 1 )
        	{
        		$proc = " create raw table mon_syssqltrace_info (	"
				      . " serial_id         serial,					"
				      . " ID                integer,				"
				      . " task_id           integer,				"
				      . " orig_sql_id       int8,					"
				      . " starttime         integer)				";

				$db->query($proc);
			}


			$db->query("    insert into mon_syssqltrace_info values (0, 0,  0,  -1, 0); ");


			$sel = "SELECT tabid FROM systables WHERE tabname = 'mon_syssqltrace_hvar'";
			$stmt = $db->query($sel);
        	$ret = $stmt->fetchAll();
			if ( count($ret) < 1 )
        	{
				$proc =   "create raw table mon_syssqltrace_hvar("

				        . "ID                integer,		"
				        . "task_id           integer,		"
				        . "cur_date          date,			"
				        . "sql_id            int8,			"
				        . "orig_sql_id       int8,			"
				        . "sql_address       int8,			"
				        . "sql_hvar_id       int,			"
				        . "sql_hvar_flags    int,			"
				        . "sql_hvar_typeid   int,			"
				        . "sql_hvar_xtypeid  int,			"
				        . "sql_hvar_ind      int,			"
				        . "sql_hvar_type     varchar(128),	"
				        . "sql_hvar_data     lvarchar(8192));";

				$idx1 = "CREATE INDEX mon_syssqltrace_hvar_idx1 on mon_syssqltrace_hvar(ID, task_id, orig_sql_id);";
				$idx2 = "CREATE INDEX mon_syssqltrace_hvar_idx2 on mon_syssqltrace_hvar(cur_date);";
				$idx3 = "CREATE INDEX mon_syssqltrace_hvar_idx3 on mon_syssqltrace_hvar(sql_id);";
				
				$db->query($proc);
				$db->query($idx1);
				$db->query($idx2);
				$db->query($idx3);
			}


			$sel = "SELECT tabid FROM systables WHERE tabname = 'mon_syssqltrace_iter'";
			$stmt = $db->query($sel);
        	$ret = $stmt->fetchAll();
			if ( count($ret) < 1 )
        	{
				$proc =   "create raw table mon_syssqltrace_iter("

				        . "ID                integer,"
				        . "task_id           integer,"
				        . "cur_date          date,"
				        . "sql_id            int8,"
				        . "orig_sql_id       int8,"
				        . "sql_address       int8,"
				        . "sql_itr_address   int8,"
				        . "sql_itr_id        int,"
				        . "sql_itr_left      int,"
				        . "sql_itr_right     int,"
				        . "sql_itr_cost      int,"
				        . "sql_itr_estrows   int,"
				        . "sql_itr_numrows   int,"
				        . "sql_itr_type      int,"
				        . "sql_itr_misc      int,"
				        . "sql_itr_info      char(256),"
				        . "sql_itr_time      float,"
						. "sql_itr_sender    int default 0,"
    					. "sql_itr_nxtsender int default 0,"
				        . "sql_itr_partnum   int);";
				        
				$idx1 = "CREATE INDEX mon_syssqltrace_iter_idx1 on mon_syssqltrace_iter(ID, task_id, orig_sql_id);";
				$idx2 = "CREATE INDEX mon_syssqltrace_iter_idx2 on mon_syssqltrace_iter(cur_date);";
				$idx3 = "CREATE INDEX mon_syssqltrace_iter_idx3 on mon_syssqltrace_iter(sql_id);";
				
				$db->query($proc);
				$db->query($idx1);
				$db->query($idx2);
				$db->query($idx3);
			}


			$sel = "SELECT tabid FROM systables WHERE tabname = 'mon_syssqltrace'";
			$stmt = $db->query($sel);
        	$ret = $stmt->fetchAll();
			if ( count($ret) < 1 )
        	{
				$proc =   "create raw table mon_syssqltrace("
				        . "ID                integer,"
				        . "task_id           integer,"
				        . "cur_date          date,"
				        . "sql_id            int8,"
				        . "orig_sql_id       int8,"
				        . "sql_address       int8,"
				        . "sql_sid           int,"
				        . "sql_uid           int,"
				        . "sql_stmttype      int,"
				        . "sql_stmtname      varchar(40),"
				        . "sql_finishtime    int,"
				        . "sql_begintxtime   int,"
				        . "sql_runtime       float,"
				        . "sql_pgreads       int,"
				        . "sql_bfreads       int,"
				        . "sql_rdcache       float,"
				        . "sql_bfidxreads    int,"
				        . "sql_pgwrites      int,"
				        . "sql_bfwrites      int,"
				        . "sql_wrcache       float,"
				        . "sql_lockreq       int,"
				        . "sql_lockwaits     int,"
				        . "sql_lockwttime    float,"
				        . "sql_logspace      int,"
				        . "sql_sorttotal     int,"
				        . "sql_sortdisk      int,"
				        . "sql_sortmem       int,"
				        . "sql_executions    int,"
				        . "sql_totaltime     float,"
				        . "sql_avgtime       float,"
				        . "sql_maxtime       float,"
				        . "sql_numiowaits    int,"
				        . "sql_avgiowaits    float,"
				        . "sql_totaliowaits  float,"
				        . "sql_rowspersec    float,"
				        . "sql_estcost       int,"
				        . "sql_estrows       int,"
				        . "sql_actualrows    int,"
				        . "sql_sqlerror      int,"
				        . "sql_isamerror     int,"
				        . "sql_isollevel     int,"
				        . "sql_sqlmemory     int,"
				        . "sql_numiterators  int,"
				        . "sql_database      varchar(128),"
				        . "sql_numtables     int,"
				        . "sql_tablelist     lvarchar(4096),"
				        . "sql_statement     lvarchar(16000),"
				        . "sql_stmtlen       int,"
				        . "sql_stmthash      int8,"
				        . "sql_pdq           smallint,"
				        . "sql_num_hvars     smallint,"
				        . "sql_dbspartnum    int);";
						
				$idx1 = "CREATE INDEX mon_syssqltrace_idx1 on mon_syssqltrace(ID, task_id, orig_sql_id);";
			    $idx2 = "CREATE INDEX mon_syssqltrace_idx2 on mon_syssqltrace(sql_stmtlen,sql_stmttype);";
				$idx3 = "CREATE INDEX mon_syssqltrace_idx3 on mon_syssqltrace(cur_date);";
				$idx4 = "CREATE INDEX mon_syssqltrace_idx4 on mon_syssqltrace(sql_id);";
				
				$db->query($proc);
				$db->query($idx1);
				$db->query($idx2);
				$db->query($idx3);
				$db->query($idx4);
			}
			
			// For server versions below 11.70.xC2, need to work around server defect idsdb00218057
			// by forcing some of the queries not to use the index.
			$directive_iter = "";
			$directive_hvar = "";
			require_once ROOT_PATH."lib/feature.php";
			if ( ! Feature::isAvailable( Feature::PANTHER_UC2, $this->idsadmin) )
			{
				$directive_iter = "{+ FULL( syssqltrace_iter ) }";
				$directive_hvar = "{+ FULL( syssqltrace_hvar ) }";
			}



$proc = <<<EOS

CREATE FUNCTION sql_showsnap(in_task_id INTEGER, in_seq_id INTEGER)
   RETURNING INTEGER

DEFINE p_last_starttime    INTEGER; -- starttime from mon_syssqltrace_info
                                    -- for the last task run
DEFINE p_trace_starttime   INTEGER; -- starttime from sysmaster:syssqltrace_info

DEFINE p_last_sql_id       INT8;  -- biggest orig_sql_id from last task run

DEFINE p_new_sql_id        INT8;  -- new max(orig_sql_id) from mon_syssqltrace
                                  -- for this task run

DEFINE p_start_high4       INT8;  -- high value of sql_id in mon_syssqltrace*
                                  -- tables

DEFINE p_sql_itr_senders_exist         INTEGER;

DEFINE p_host_vars         INTEGER; -- Is SQLTRACE set to collect host vars ?
DEFINE sqltrace_row_cnt    INTEGER;
DEFINE sqltrace_iter_row_cnt INTEGER;
DEFINE sqltrace_hvar_row_cnt INTEGER;
DEFINE delete_cnt            INTEGER;


ON EXCEPTION IN (-206) -- If no table was found, create one

BEGIN
  ON EXCEPTION  -- Continue trying each of these statements within the outer exception
  END EXCEPTION WITH RESUME;

    create raw table mon_syssqltrace_info
        (
        serial_id         serial,
        ID                integer,
        task_id           integer,
        orig_sql_id       int8,
        starttime         integer
        );
    insert into mon_syssqltrace_info values (0, 0,  0,  -1, 0);

    create raw table mon_syssqltrace_hvar
        (
        ID                integer,
        task_id           integer,
        cur_date          date,
        sql_id            int8,
        orig_sql_id       int8,
        sql_address       int8,
        sql_hvar_id       int,
        sql_hvar_flags    int,
        sql_hvar_typeid   int,
        sql_hvar_xtypeid  int,
        sql_hvar_ind      int,
        sql_hvar_type     varchar(128),
        sql_hvar_data     lvarchar(8192)
        );

    create raw table mon_syssqltrace_iter
        (
        ID                integer,
        task_id           integer,
        cur_date          date,
        sql_id            int8,
        orig_sql_id       int8,
        sql_address       int8,
        sql_itr_address   int8,
        sql_itr_id        int,
        sql_itr_left      int,
        sql_itr_right     int,
        sql_itr_cost      int,
        sql_itr_estrows   int,
        sql_itr_numrows   int,
        sql_itr_type      int,
        sql_itr_misc      int,
        sql_itr_info      char(256),
        sql_itr_time      float,
        sql_itr_sender    int default 0,
        sql_itr_nxtsender int default 0,
        sql_itr_partnum   int
        );

    create raw table mon_syssqltrace
        (
        ID                integer,
        task_id           integer,
        cur_date          date,
        sql_id            int8,
        orig_sql_id       int8,
        sql_address       int8,
        sql_sid           int,
        sql_uid           int,
        sql_stmttype      int,
        sql_stmtname      varchar(40),
        sql_finishtime    int,
        sql_begintxtime   int,
        sql_runtime       float,
        sql_pgreads       int,
        sql_bfreads       int,
        sql_rdcache       float,
        sql_bfidxreads    int,
        sql_pgwrites      int,
        sql_bfwrites      int,
        sql_wrcache       float,
        sql_lockreq       int,
        sql_lockwaits     int,
        sql_lockwttime    float,
        sql_logspace      int,
        sql_sorttotal     int,
        sql_sortdisk      int,
        sql_sortmem       int,
        sql_executions    int,
        sql_totaltime     float,
        sql_avgtime       float,
        sql_maxtime       float,
        sql_numiowaits    int,
        sql_avgiowaits    float,
        sql_totaliowaits  float,
        sql_rowspersec    float,
        sql_estcost       int,
        sql_estrows       int,
        sql_actualrows    int,
        sql_sqlerror      int,
        sql_isamerror     int,
        sql_isollevel     int,
        sql_sqlmemory     int,
        sql_numiterators  int,
        sql_database      varchar(128),
        sql_numtables     int,
        sql_tablelist     lvarchar(4096),
        sql_statement     lvarchar(16000),
        -- sql_statement     char(16000),
        sql_stmtlen       int,
        sql_stmthash      int8,
        sql_pdq           smallint,
        sql_num_hvars     smallint,
        sql_dbspartnum    int
        );


     CREATE INDEX mon_syssqltrace_idx1 on
                                mon_syssqltrace(ID, task_id, orig_sql_id);
     CREATE INDEX mon_syssqltrace_idx2 on
                                mon_syssqltrace(sql_stmtlen,sql_stmttype);
     CREATE INDEX mon_syssqltrace_idx3 on mon_syssqltrace(cur_date);

     CREATE INDEX mon_syssqltrace_iter_idx1 on
                                mon_syssqltrace_iter(ID, task_id, orig_sql_id);
     CREATE INDEX mon_syssqltrace_iter_idx2 on mon_syssqltrace_iter(cur_date);

     CREATE INDEX mon_syssqltrace_hvar_idx1 on
                                mon_syssqltrace_hvar(ID, task_id, orig_sql_id);
     CREATE INDEX mon_syssqltrace_hvar_idx2 on mon_syssqltrace_hvar(cur_date);

END
   END EXCEPTION WITH RESUME;


--SET DEBUG FILE TO "/tmp/debug_sql_showsnap.log";
--TRACE ON;

SET ISOLATION TO DIRTY READ;

LET p_last_starttime = 0;
LET p_trace_starttime = 0;
LET p_last_sql_id = -1;
LET p_host_vars = 0;
LET sqltrace_iter_row_cnt = 0;
LET sqltrace_row_cnt = 0;
LET sqltrace_hvar_row_cnt = 0;

LET p_trace_starttime =
                 (SELECT NVL(starttime,0) FROM sysmaster:syssqltrace_info);

IF  ( (p_trace_starttime is NULL) or (p_trace_starttime == 0) ) THEN
INSERT into mon_syssqltrace_info
                 values (0, in_seq_id, in_task_id, -1, p_trace_starttime);
RETURN 0;
END IF;


LET p_host_vars =
       (SELECT bitand(flags,8192) FROM sysmaster:syssqltrace_info);

LET p_last_sql_id = (SELECT NVL(orig_sql_id,-1) FROM mon_syssqltrace_info
                     where serial_id  =
                       (select MAX(serial_id) from mon_syssqltrace_info));


LET p_last_starttime = (SELECT NVL(starttime,0) FROM mon_syssqltrace_info
                     where serial_id  =
                       (select MAX(serial_id) from mon_syssqltrace_info));


IF (p_last_sql_id is NULL) THEN
   LET p_last_sql_id = -1;
END IF;

IF (p_last_starttime is NULL) THEN
   LET p_last_starttime = 0;
END IF;

IF  (p_last_starttime != p_trace_starttime) THEN
    LET p_last_sql_id = -1 ;
END IF;

LET p_start_high4 = p_trace_starttime * 4294967296 ;

INSERT INTO mon_syssqltrace
        (
        ID                ,
        task_id           ,
        cur_date          ,
        sql_id            ,
        orig_sql_id       ,
        sql_address       ,
        sql_sid           ,
        sql_uid           ,
        sql_stmttype      ,
        sql_stmtname      ,
        sql_finishtime    ,
        sql_begintxtime   ,
        sql_runtime       ,
        sql_pgreads       ,
        sql_bfreads       ,
        sql_rdcache       ,
        sql_bfidxreads    ,
        sql_pgwrites      ,
        sql_bfwrites      ,
        sql_wrcache       ,
        sql_lockreq       ,
        sql_lockwaits     ,
        sql_lockwttime    ,
        sql_logspace      ,
        sql_sorttotal     ,
        sql_sortdisk      ,
        sql_sortmem       ,
        sql_executions    ,
        sql_totaltime     ,
        sql_avgtime       ,
        sql_maxtime       ,
        sql_numiowaits    ,
        sql_avgiowaits    ,
        sql_totaliowaits  ,
        sql_rowspersec    ,
        sql_estcost       ,
        sql_estrows       ,
        sql_actualrows    ,
        sql_sqlerror      ,
        sql_isamerror     ,
        sql_isollevel     ,
        sql_sqlmemory     ,
        sql_numiterators  ,
        sql_database      ,
        sql_numtables     ,
        sql_tablelist     ,
        sql_statement     ,
        sql_stmtlen       ,
        sql_stmthash      ,
        sql_pdq           ,
        sql_num_hvars     ,
        sql_dbspartnum
        )
    SELECT
        in_seq_id         ,
        in_task_id        ,
        today             ,
        p_start_high4 + sql_id  ,
        sql_id            ,
        sql_address       ,
        sql_sid           ,
        sql_uid           ,
        sql_stmttype      ,
        sql_stmtname      ,
        sql_finishtime    ,
        sql_begintxtime   ,
        sql_runtime       ,
        sql_pgreads       ,
        sql_bfreads       ,
        sql_rdcache       ,
        sql_bfidxreads    ,
        sql_pgwrites      ,
        sql_bfwrites      ,
        sql_wrcache       ,
        sql_lockreq       ,
        sql_lockwaits     ,
        sql_lockwttime    ,
        sql_logspace      ,
        sql_sorttotal     ,
        sql_sortdisk      ,
        sql_sortmem       ,
        sql_executions    ,
        sql_totaltime     ,
        sql_avgtime       ,
        sql_maxtime       ,
        sql_numiowaits    ,
        sql_avgiowaits    ,
        sql_totaliowaits  ,
        sql_rowspersec    ,
        sql_estcost       ,
        sql_estrows       ,
        sql_actualrows    ,
        sql_sqlerror      ,
        sql_isamerror     ,
        sql_isollevel     ,
        sql_sqlmemory     ,
        sql_numiterators  ,
        sql_database      ,
        sql_numtables     ,
        TRIM(sql_tablelist)     ,
        TRIM(sql_statement)     ,
        sql_stmtlen       ,
        sql_stmthash      ,
        sql_pdq           ,
        sql_num_hvars     ,
        sql_dbspartnum
    FROM sysmaster:syssqltrace
    WHERE sql_id >  p_last_sql_id;

LET  sqltrace_row_cnt = DBINFO('sqlca.sqlerrd2');

INSERT INTO mon_syssqltrace_iter
        (
        ID                ,
        task_id           ,
        cur_date          ,
        sql_id            ,
        orig_sql_id       ,
        sql_address       ,
        sql_itr_address   ,
        sql_itr_id        ,
        sql_itr_left      ,
        sql_itr_right     ,
        sql_itr_cost      ,
        sql_itr_estrows   ,
        sql_itr_numrows   ,
        sql_itr_type      ,
        sql_itr_misc      ,
        sql_itr_info      ,
        sql_itr_time      ,
        sql_itr_partnum
        )
 SELECT {$directive_iter}
        in_seq_id         ,
        in_task_id        ,
        today             ,
        p_start_high4 + sql_id ,
        sql_id            ,
        sql_address       ,
        sql_itr_address   ,
        sql_itr_id        ,
        sql_itr_left      ,
        sql_itr_right     ,
        sql_itr_cost      ,
        sql_itr_estrows   ,
        sql_itr_numrows   ,
        sql_itr_type      ,
        sql_itr_misc      ,
        sql_itr_info      ,
        sql_itr_time      ,
        sql_itr_partnum
    FROM sysmaster:syssqltrace_iter
    WHERE sql_id >  p_last_sql_id;
	
	
LET p_sql_itr_senders_exist = (SELECT COUNT(*) FROM sysmaster:syscolumns a, sysmaster:systables b 
    WHERE a.tabid = b.tabid AND a.colname = 'sql_itr_sender' AND b.tabname = 'syssqltrace_iter');
	
IF ( p_sql_itr_senders_exist > 0) THEN
INSERT INTO mon_syssqltrace_iter
        (
	    sql_itr_sender    ,
        sql_itr_nxtsender 
        )
 SELECT 
        sql_itr_sender    ,
        sql_itr_nxtsender 
    FROM sysmaster:syssqltrace_iter
    WHERE sql_id >  p_last_sql_id;
END IF; -- IF ( p_sql_itr_senders_exist > 0)
					   
LET  sqltrace_iter_row_cnt = DBINFO('sqlca.sqlerrd2');

IF ( p_host_vars > 0  )  THEN

INSERT INTO mon_syssqltrace_hvar
        (
        ID                ,
        task_id           ,
        cur_date          ,
        sql_id            ,
        orig_sql_id       ,
        sql_address       ,
        sql_hvar_id       ,
        sql_hvar_flags    ,
        sql_hvar_typeid   ,
        sql_hvar_xtypeid  ,
        sql_hvar_ind      ,
        sql_hvar_type     ,
        sql_hvar_data
        )
 SELECT {$directive_hvar} 
        in_seq_id         ,
        in_task_id        ,
        today             ,
        p_start_high4 + sql_id ,
        sql_id            ,
        sql_address       ,
        sql_hvar_id       ,
        sql_hvar_flags    ,
        sql_hvar_typeid   ,
        sql_hvar_xtypeid  ,
        sql_hvar_ind      ,
        sql_hvar_type      ,
        trim(sql_hvar_data)
    FROM sysmaster:syssqltrace_hvar
    WHERE sql_id >  p_last_sql_id;

LET  sqltrace_hvar_row_cnt = DBINFO('sqlca.sqlerrd2');


DELETE FROM mon_syssqltrace_hvar
    WHERE     ID = in_seq_id AND
              task_id = in_task_id AND
              orig_sql_id > p_last_sql_id AND
   orig_sql_id NOT IN ( SELECT orig_sql_id FROM mon_syssqltrace
                       where ID = in_seq_id AND task_id = in_task_id);

LET  delete_cnt = DBINFO('sqlca.sqlerrd2');
END IF;   -- IF ( p_host_vars > 0  )  THEN



DELETE FROM mon_syssqltrace_iter
    WHERE     ID = in_seq_id AND
              task_id = in_task_id AND
              orig_sql_id > p_last_sql_id AND
   orig_sql_id NOT IN ( SELECT orig_sql_id FROM mon_syssqltrace
                       where ID = in_seq_id AND task_id = in_task_id);
LET  delete_cnt = DBINFO('sqlca.sqlerrd2');

LET p_new_sql_id = -1;
LET p_new_sql_id = (SELECT NVL(MAX(orig_sql_id),-1) from mon_syssqltrace
                      where ID = in_seq_id AND task_id = in_task_id );

IF ( (p_new_sql_id is NULL) or (p_new_sql_id == -1) )
THEN
INSERT into mon_syssqltrace_info
         values (0, in_seq_id, in_task_id, p_last_sql_id, p_trace_starttime);
ELSE
INSERT into mon_syssqltrace_info
         values (0, in_seq_id, in_task_id, p_new_sql_id, p_trace_starttime);
END IF;

RETURN sqltrace_row_cnt;

END FUNCTION;

EOS;
$stmt = $db->query($proc);
$res = $stmt->fetchAll();
/*create the dbcron jobs also*/
$proc = <<< EOS
INSERT INTO ph_task
(
tk_name,
tk_type,
tk_group,
tk_description,
tk_execute,
tk_start_time,
tk_stop_time,
tk_frequency,
tk_delete,
tk_result_table,
tk_attributes,
tk_enable
)
VALUES
(
'Save SQL Trace',
'SENSOR',
'PERFORMANCE',
'Saves the current syssqltrace info to table',
'sql_showsnap',
DATETIME(06:00:00) HOUR TO SECOND,
DATETIME(18:00:00) HOUR TO SECOND,
INTERVAL ( 15 ) MINUTE TO MINUTE,
INTERVAL ( 1  ) DAY TO DAY,
'mon_syssqltrace,mon_syssqltrace_iter,mon_syssqltrace_hvar,mon_syssqltrace_info',
1,
'f'
);

EOS;
$stmt = $db->query($proc);
$res = $stmt->fetchAll();
        }
    }

    // GD is required for XTree;  So check if gd is enabled
    function getXtree()
    {
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


}
?>
