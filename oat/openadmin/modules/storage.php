<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2010, 2012.  All Rights Reserved
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
 * Storage 
 */
class storage {

    public  $idsadmin;

    /**
     * This class constructor sets
     * the default title and the
     * language files.
     *
     */
    function __construct(&$idsadmin)
    {
        $this->idsadmin = &$idsadmin;
        $this->idsadmin->load_template("template_storage");
        $this->idsadmin->load_lang("storage");
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
        switch($this->idsadmin->in['do'])
        {
        	default:
                $this->idsadmin->setCurrMenuItem("storage");
                $this->def();
                break;
        }
    }
	
	function def()
    {
    	$lang = $this->idsadmin->phpsession->get_lang();
    	
		// Deploy functions and tables needed for compression
		if (Feature::isAvailable ( Feature::CHEETAH2_UC4 , $this->idsadmin->phpsession->serverInfo->getVersion() )
			&& !$this->idsadmin->isreadonly() && $this->idsadmin->phpsession->serverInfo->isPrimary() )
		{
			$this->idsadmin->testAndDeployAdminAsync("COMPRESSION" , "Compression jobs");
			$this->verifyEstimatesTableExists ();
			$this->verifyEstimatesFunctionExists();
		}
		
		// Deploy task needed for page usage information
		if (!$this->idsadmin->isreadonly() && $this->idsadmin->phpsession->serverInfo->isPrimary())
		{
			$this->verifyPageUsageTaskExists();
		}
		
    	$mirrorEnabled = $this->isMirrorEnabled();
    	$this->idsadmin->html->add_to_output($this->idsadmin->template["template_storage"]->render_storage($lang,$mirrorEnabled));
			
    } // end def
    
    /**
     * Is mirroring enabled for this server.
     * 
     * Returns 1 if mirroring is enabled, 0 if it is disabled.
     */
    function isMirrorEnabled() {
        $conn = $this->idsadmin->get_database("sysmaster");

        $qry = "SELECT ".
            "cf_effective " .
            "FROM sysconfig " .
            "WHERE cf_name='MIRROR'";
        ;

        $stmt = $conn->query($qry);
        while ($res = $stmt->fetch(PDO::FETCH_ASSOC) )
        {
            $mirrorEnabled = trim($res['CF_EFFECTIVE']);
        }
        
        return $mirrorEnabled;
    }
	
	function verifyEstimatesFunctionExists () 
	{
    	// First check if DELIMIDENT is set as an env variable on the connection.
    	// Some of the following statements for deploying functions will not work
    	// with DELIMIDENT, so we'll need to ensure we are running this on a 
    	// connection without DELIMIDENT.
    	$connection_reset = false;
    	if (strcasecmp($this->idsadmin->phpsession->instance->get_delimident(), "Y") == 0)
    	{
    		// DELIMIDENT is set, so save off the env variable setting and reset the connection.
    		$connection_reset = true;
    		$saved_delimident_value = $this->idsadmin->phpsession->instance->get_delimident();
    		$this->idsadmin->phpsession->instance->set_delimident("");
    		$saved_envvars = $this->idsadmin->phpsession->instance->get_envvars();
    		$this->idsadmin->phpsession->instance->set_envvars("");
    		$this->idsadmin->unset_database("sysadmin");
    	}
		
		$db = $this->idsadmin->get_database("sysadmin");
		
		$name = "mon_compression_estimates";
        $sel = "select count(*) as cnt from ph_task WHERE tk_name = '{$name}' ";
        $stmt = $db->query($sel);
        $row = $stmt->fetch();
        $stmt->closeCursor();
		
		$qry = "select COUNT(*) as UDREXISTS from sysprocedures where procname = 'mon_estimate_compression'";
        $stmt = $db->query($qry);
        $row2 = $stmt->fetch();
        $stmt->closeCursor();
		
        /* we found it so just return */
        if ( isset($row['CNT']) && $row['CNT'] == 0 && isset($row2['UDREXISTS']) && $row2['UDREXISTS'] == 0)
        {

        /* let's create it */

        /* In sysmaster:sysptnhdr 
         *    nrows = number of rows; is set for tables, but always zero for indexes
         *    npdata = number of data pages; is set for tables, but always zero for indexes
         *    npused = number of used pages; when dealing with indexes, we need to use this column instead of npdata to find out how many pages are used by the index
         */

        if (Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin->phpsession->serverInfo->getVersion()))
        {
        	// Filter for server versions 12.10 and above: Run the mon_estimate_compression task on tables with > 2000 rows
        	// and on indexes with > 500 data pages (our approximation for indexes that will have enough unique keys to be compressed).
        	$table_idx_filter = " AND ((nrows >= 2000 AND npdata > 0)  OR (npdata = 0 AND npused >= 500)) ";
        } else {
        	// Filter for server versions less than 12.10: Only run the mon_estimate_compression task on tables 
        	// and only on tables with > 2000 rows
        	// Only run the mon_estimate_compression task on tables
        	$table_idx_filter = " AND nrows >= 2000 AND npdata > 0 ";
        }

        $sel = <<< EOF
         CREATE FUNCTION mon_estimate_compression(task_id integer, id integer)
         RETURNING integer;

DEFINE p INTEGER;
DEFINE d VARCHAR(128);
DEFINE o VARCHAR(32);
DEFINE t VARCHAR(128);
DEFINE e VARCHAR(255);

FOREACH
SELECT tab.partnum , TRIM(dbsname) AS dbsname , TRIM(owner) AS owner, TRIM(tabname) AS tabname
,( SUBSTR( sysadmin:task('fragment estimate_compression',tab.partnum) , 102 ) )

INTO p , d , o , t , e
FROM sysmaster:systabnames tab , sysmaster:sysptnhdr hdr
WHERE dbsname NOT IN ( 'sysmaster','sysutils','syscdr','sysuser','system'
, 'syscdcv1','syscdcv2','syscdcv3','syscdcv4','syscdcv5','syscdcv6','syscdcv7'
, 'syscdcv8','syscdcv9')
AND tabname != dbsname
{$table_idx_filter}
--(nvl((SELECT env_value FROM sysenv WHERE env_name = 'IFX_COMPRESSION_MIN_ROWS'),2000))
AND tab.partnum = hdr.partnum

AND tabname != 'TBLSpace'
AND bitand(flags,'0x0004') != 4
AND tabname NOT IN ( SELECT tabname FROM systables WHERE tabid < 100 ) 

INSERT INTO  mon_compression_estimates
(id, est_partnum , est_dbname , est_owner , est_tabname , est_estimate )
VALUES
(id, p,d,o,t,e);

END FOREACH

RETURN 0;
END FUNCTION

EOF;

        try {
        	$db->query($sel, false, true);
        } catch (PDOException $e) {
    		// Check for SQL errors related to out-of-space.  
    		// If so, suppress them, so the Storage page can keep loading.
    		// We'll try again to deploy the procedures the next time the 
    		// user comes to the page when there is enough space.
    		$err_code = $e->getCode();
    		$err_msg = $e->getMessage();
    		if ($err_code == -312 || $err_code == -261 || $err_code == -212)
    		{
    			error_log("Error in modules/storage.php deploying the mon_estimate_compression procedure due to space issues. Ignoring this error and proceeding to load the page.");
    			error_log($err_code . " " . $err_msg);
    			return;
    		} else {
    			$this->idsadmin->db_error("{$this->idsadmin->lang('ErrorF')} {$err_code} <br/> {$err_msg} <br/><br/>{$this->idsadmin->lang('QueryF')}<br/> {$sel} ");
    		}
    	}
        	

        $sel = <<<EOF
        INSERT INTO ph_task
(
tk_name,
tk_type,
tk_group,
tk_description,
tk_result_table,
tk_create,
tk_execute,
tk_stop_time,
tk_start_time,
tk_frequency,
tk_delete
)
VALUES
(
'{$name}',
'SENSOR',
'COMPRESSION',
'Get compression estimates',
'mon_compression_estimates',
'create table mon_compression_estimates(id  integer, est_partnum integer,est_dbname varchar(128),est_owner  varchar(32),est_tabname varchar(128),est_estimate lvarchar(32000),est_date datetime year to second default CURRENT year to second); create index mon_estimate_compression_idx1 on mon_compression_estimates ( est_partnum );',
'mon_estimate_compression',
NULL,
DATETIME(02:30:00) HOUR TO SECOND,
INTERVAL ( 7 ) DAY TO DAY,
INTERVAL ( 30 ) DAY TO DAY
);

EOF;
		$db->query($sel);
        
        }

        $name = "get_compression_estimate";
        $sel = "select count(*) as cnt from sysadmin:sysprocedures WHERE procname = '{$name}' ";
        $db = $this->idsadmin->get_database("sysadmin");
        $stmt = $db->query($sel);
        $row = $stmt->fetch();
        $stmt->closeCursor();

        /* we found it so just return */
        if ( isset($row['CNT']) && $row['CNT'] == 0 )
        {

        $sel = <<< EOF
        CREATE FUNCTION get_compression_estimate( pnum varchar(128) , comptype varchar(12)  , dbname varchar(128) , owner varchar(32) )
returning int;

define e LVARCHAR;
define cmd integer;

--SET DEBUG FILE TO "/tmp/debug2.out";
--TRACE ON;

IF comptype == 'fragment' THEN
select admin(comptype||' estimate_compression',pnum) INTO cmd from sysmaster:sysdual ;
ELSE
select admin(comptype||' estimate_compression',pnum , dbname , owner ) INTO cmd from sysmaster:sysdual ;
END IF

return cmd;
end function;

EOF;
        try {
        	$db->query($sel, false, true);
        } catch (PDOException $e) {
    		// Check for SQL errors related to out-of-space.  
    		// If so, suppress them, so the Storage page can keep loading.
    		// We'll try again to deploy the procedures the next time the 
    		// user comes to the page when there is enough space.
    		$err_code = $e->getCode();
    		$err_msg = $e->getMessage();
    		if ($err_code == -312 || $err_code == -261 || $err_code == -212)
    		{
    			error_log("Error in modules/storage.php deploying the get_compression_estimate procedure due to space issues. Ignoring this error and proceeding to load the page.");
    			error_log($err_code . " " . $err_msg);
    			return;
    		} else {
    			$this->idsadmin->db_error("{$this->idsadmin->lang('ErrorF')} {$err_code} <br/> {$err_msg} <br/><br/>{$this->idsadmin->lang('QueryF')}<br/> {$sel} ");
    		}
    	}
        
        }

        $name = "admin_async_estimates";
        $sel = "select count(*) as cnt from sysadmin:sysprocedures WHERE procname = '{$name}' ";
        $db = $this->idsadmin->get_database("sysadmin");
        $stmt = $db->query($sel);
        $row = $stmt->fetch();
        $stmt->closeCursor();

        /* we found it so just return */
        if ( isset($row['CNT']) && $row['CNT'] == 0 )
        {

        $sel = <<< EOF
CREATE FUNCTION admin_async_estimates(cmd varchar(128),
                          comptype varchar(12),
                          dbname varchar(128),
                          owner  varchar(32),
                          cur_group CHAR(129),
                          comments lvarchar(1024)
                               DEFAULT "Background admin API",
                          start_time DATETIME hour to second
                               DEFAULT CURRENT hour to second,
                          end_time   DATETIME hour to second
                               DEFAULT NULL,
                          frequency  INTERVAL day(2) to second
                               DEFAULT NULL,
                          monday    BOOLEAN DEFAULT 't',
                          tuesday   BOOLEAN DEFAULT 't',
                          wednesday BOOLEAN DEFAULT 't',
                          thursday  BOOLEAN DEFAULT 't',
                          friday    BOOLEAN DEFAULT 't',
                          saturday  BOOLEAN DEFAULT 't',
                          sunday    BOOLEAN DEFAULT 't'
                          )
   RETURNING INTEGER
   DEFINE ret_task_id  INTEGER;
   DEFINE del_time     INTERVAL DAY TO SECOND;
   DEFINE id           INTEGER;
   DEFINE task_id      INTEGER;
   DEFINE seq_id       INTEGER;
   DEFINE cmd_num      INTEGER;
   DEFINE boot_time    DATETIME YEAR TO SECOND;

   IF cur_group IS NULL THEN
       LET cur_group = 'MISC';
   END IF

   SELECT FIRST 1 value::INTERVAL DAY TO SECOND INTO del_time FROM ph_threshold
      WHERE name = 'BACKGROUND TASK HISTORY RETENTION';
   IF del_time IS NULL THEN
       LET del_time = 7 UNITS DAY;
   END IF

    BEGIN
        ON EXCEPTION IN ( -310, -316 )
        END EXCEPTION WITH RESUME

            CREATE TABLE job_status (
               js_id         SERIAL,
               js_task       INTEGER,
               js_seq        INTEGER,
               js_comment    LVARCHAR(512),
               js_command    LVARCHAR(4096),
               js_start      DATETIME year to second
                             DEFAULT CURRENT year to second,
               js_done       DATETIME year to second DEFAULT NULL,
               js_result     INTEGER
           );
            CREATE INDEX job_status_ix1 ON job_status(js_id);
            CREATE INDEX job_status_ix2 ON job_status(js_task);
            CREATE INDEX job_status_ix3 ON job_status(js_result);
     END

     BEGIN
        ON EXCEPTION IN ( -8301 )
        END EXCEPTION WITH RESUME

        CREATE SEQUENCE background_task START 1 NOMAXVALUE ;

     END
   IF comptype == 'fragment' THEN

    INSERT INTO ph_task
        ( tk_name,
        tk_description,
        tk_type,
        tk_group,
        tk_execute,
        tk_start_time,
        tk_stop_time,
        tk_frequency,
        tk_Monday,
        tk_Tuesday,
        tk_Wednesday,
        tk_Thursday,
        tk_Friday,
        tk_Saturday,
        tk_Sunday,
        tk_attributes
        )
        VALUES
        (
        'Background Task ('||  background_task.NEXTVAL ||')',
        TRIM(comments),
        'TASK',
        cur_group,
        "insert into job_status (js_task, js_seq , js_comment,js_command) VALUES(\$DATA_TASK_ID,\$DATA_SEQ_ID, '"||TRIM(comments)||"','"||"Estimate for "||comptype||" "||TRIM(REPLACE(REPLACE(cmd,"'"),"""") )||"' ); update job_status set js_result=get_compression_estimate("""||cmd||""" ,"""||comptype||""" , """" , """" )  WHERE js_task = \$DATA_TASK_ID AND js_seq = \$DATA_SEQ_ID; insert into mon_compression_estimates SELECT 0,tab.partnum , TRIM(dbsname) , TRIM(owner) , TRIM(tabname) , substr(trim(cmd_ret_msg),102) , current FROM sysmaster:systabnames tab , sysmaster:sysptnhdr hdr , command_history where hdr.partnum = tab.partnum and tab.partnum ="""||cmd||""" and cmd_number = ( select case when ( js_result < 0 ) then js_result *-1 else js_result end from job_status where  js_task = \$DATA_TASK_ID AND js_seq = \$DATA_SEQ_ID ) ;update job_status set (js_done)  = ( CURRENT ) WHERE js_task = \$DATA_TASK_ID AND js_seq = \$DATA_SEQ_ID ;" ,
                start_time,
        end_time,
        frequency,
        monday,
        tuesday,
        wednesday,
        thursday,
        friday,
        saturday,
        sunday,
        8);
        ELSE

    INSERT INTO ph_task
        ( tk_name,
        tk_description,
        tk_type,
        tk_group,
        tk_execute,
        tk_start_time,
        tk_stop_time,
        tk_frequency,
        tk_Monday,
        tk_Tuesday,
        tk_Wednesday,
        tk_Thursday,
        tk_Friday,
        tk_Saturday,
        tk_Sunday,
        tk_attributes
        )
        VALUES
        (
        'Background Task ('||  background_task.NEXTVAL ||')',
        TRIM(comments),
        'TASK',
        cur_group,

        "insert into job_status (js_task, js_seq , js_comment,js_command) VALUES(\$DATA_TASK_ID,\$DATA_SEQ_ID, '"||TRIM(comments)||"','"||"Estimate for "||comptype||" "||TRIM(REPLACE(REPLACE(cmd,"'"),"""") )||"' ); update job_status set js_result=get_compression_estimate("""||cmd||""" ,"""||comptype||""", """||dbname||""" , """||owner||""")  WHERE js_task = \$DATA_TASK_ID AND js_seq = \$DATA_SEQ_ID; insert into mon_compression_estimates SELECT 0,tab.partnum , TRIM(dbsname) , TRIM(owner) , TRIM(tabname) , substr(trim(cmd_ret_msg),102) , current FROM sysmaster:systabnames tab , sysmaster:sysptnhdr hdr , command_history where hdr.partnum = tab.partnum and tab.tabname ="""||cmd||"""  and dbsname = """||dbname||""" and owner = """||owner||""" and cmd_number = ( select case when ( js_result < 0 ) then js_result *-1 else js_result end from job_status where  js_task = \$DATA_TASK_ID AND js_seq = \$DATA_SEQ_ID ) ;update job_status set (js_done)  = ( CURRENT ) WHERE js_task = \$DATA_TASK_ID AND js_seq = \$DATA_SEQ_ID ;" ,

        start_time,
        end_time,
        frequency,
        monday,
        tuesday,
        wednesday,
        thursday,
        friday,
        saturday,
                sunday,
        8);


END IF

   LET ret_task_id = DBINFO('sqlca.sqlerrd1');

   /* Cleanup the job_status table */

   SELECT dbinfo('UTC_TO_DATETIME',sh_boottime)
          INTO boot_time
           FROM sysmaster:sysshmvals;

   FOREACH  SELECT js_id, js_task, js_seq, js_result
        INTO id, task_id,  seq_id, cmd_num
        FROM job_status J, OUTER ph_run, command_history
        WHERE  ( CURRENT - js_done > del_time OR
                (js_start < boot_time AND js_done IS NULL ) )
        AND    js_task = run_task_id
        AND    js_seq  = run_task_seq
        AND    js_result = ABS(cmd_number)

       DELETE FROM ph_run WHERE run_task_id = task_id
                          AND run_task_seq = seq_id;
       DELETE FROM command_history WHERE cmd_number = cmd_num;
       DELETE FROM job_status WHERE js_id = id;

       -- Cleanup the task table only if this is not a repeating task
       DELETE FROM ph_task WHERE tk_id = task_id AND tk_next_execution IS NULL;

   END FOREACH

   RETURN  ret_task_id;
END FUNCTION;

EOF;
		try {
			$db->query($sel, false, true);
		} catch (PDOException $e) {
    		// Check for SQL errors related to out-of-space.  
    		// If so, suppress them, so the Storage page can keep loading.
    		// We'll try again to deploy the procedures the next time the 
    		// user comes to the page when there is enough space.
    		$err_code = $e->getCode();
    		$err_msg = $e->getMessage();
    		if ($err_code == -312 || $err_code == -261 || $err_code == -212)
    		{
    			error_log("Error in modules/storage.php deploying the admin_async_estimates procedure due to space issues. Ignoring this error and proceeding to load the page.");
    			error_log($err_code . " " . $err_msg);
    			return;
    		} else {
    			$this->idsadmin->db_error("{$this->idsadmin->lang('ErrorF')} {$err_code} <br/> {$err_msg} <br/><br/>{$this->idsadmin->lang('QueryF')}<br/> {$sel} ");
    		}
    	}
	
		}
		
    	// If we had to reset the connection due to the delimident setting, restore it now.
    	if ($connection_reset)
    	{
    		$this->idsadmin->unset_database("sysadmin");
    		$this->idsadmin->phpsession->instance->set_delimident($saved_delimident_value);
    		$this->idsadmin->phpsession->instance->set_envvars($saved_envvars);
    	}
	}
	
	/**
	 * Check whether mon_compression_estimates exists in sysadmin.
	 * If it doesn't exist, create it.
	 */
	function verifyEstimatesTableExists () 
	{
		// first let check if the mon_compression_estimates table exists ,
		// this is normally created by the SENSOR , but if that has yet to run
		// then errors will ensue about the table not existing ..

		$sel = "SELECT count(*) as CNT FROM systables WHERE tabname = 'mon_compression_estimates'";
		$db  = $this->idsadmin->get_database("sysadmin");
		$stmt = $db->query($sel);
		$row  = $stmt->fetch();
		$stmt->closeCursor();
		if ( isset($row['CNT']) && $row['CNT'] == 0 )
		{
			try {
				$sel = "create table mon_compression_estimates(id  integer, est_partnum integer,est_dbname varchar(128),est_owner  varchar(32),est_tabname varchar(128),est_estimate lvarchar(32000),est_date datetime year to second default CURRENT year to second);";
				$db->query($sel, false, true);
				$sel = "create index mon_estimate_compression_idx1 on mon_compression_estimates ( est_partnum );";
				$db->query($sel, false, true);
			} catch (PDOException $e) {
	    		// Check for SQL errors related to out-of-space.  
    			// If so, suppress them, so the Storage page can keep loading.
	    		// We'll try again to deploy the procedures the next time the 
    			// user comes to the page when there is enough space.
	    		$err_code = $e->getCode();
	    		$err_msg = $e->getMessage();
	    		if ($err_code == -312 || $err_code == -261 || $err_code == -212)
	    		{
	    			error_log("Error in modules/storage.php deploying the mon_compression_estimates table due to space issues. Ignoring this error and proceeding to load the page.");
	    			error_log($err_code . " " . $err_msg);
	    			return;
	    		} else {
	    			$this->idsadmin->db_error("{$this->idsadmin->lang('ErrorF')} {$err_code} <br/> {$err_msg} <br/><br/>{$this->idsadmin->lang('QueryF')}<br/> {$sel} ");
	    		}
    		}
		}
	}
	
	/**
	 * Check whether the mon_page_usage task exists in sysadmin.
	 * If it doesn't exist, create it.
	 */
	function verifyPageUsageTaskExists()
	{
		$sel = "SELECT count(*) as CNT FROM ph_task WHERE tk_name = 'mon_page_usage'";
		$db  = $this->idsadmin->get_database("sysadmin");
		$stmt = $db->query($sel);
		$row  = $stmt->fetch();
		$stmt->closeCursor();
		if ( isset($row['CNT']) && $row['CNT'] == 0 )
		{
			$sel = <<< EOF
INSERT INTO ph_task
(
tk_name,
tk_type,
tk_group,
tk_description,
tk_result_table,
tk_create,
tk_execute,
tk_stop_time,
tk_start_time,
tk_frequency,
tk_delete
)

VALUES
(
'mon_page_usage',
'SENSOR',
'DISK',
'Get page usage estimate',
'mon_page_usage',
'CREATE TABLE mon_page_usage(ID integer, dbsnum smallint, type char(1), partnum integer, lockid integer, nextns smallint, nrows integer, nptotal integer, npused integer, free integer, partly_used integer, mostly_used integer, very_full integer,run_time datetime year to second );create index mon_page_usage_ix1 on mon_page_usage(partnum,id);create index mon_page_usage_ix2 on mon_page_usage(lockid,id);',
'INSERT INTO mon_page_usage select \$DATA_SEQ_ID, trunc(P.partnum / 1048577) as dbsnum, CASE WHEN P.nkeys = 1 AND P.npused > 1 AND P.npdata = 0 AND P.partnum <> P.lockid AND bitand(P.flags,4) = 0 THEN "I" ELSE "T" END as type, P.partnum, P.lockid, P.nextns, P.nrows, P.nptotal, P.npused, P.nptotal - ( BM.partly_used+BM.mostly_used+BM.very_full) AS free ,BM.partly_used ,BM.mostly_used , BM.very_full, CURRENT FROM sysmaster:sysptnhdr P, outer (select b.pb_partnum as partnum, (b.pb_partnum/1048577)::integer as dbsnum ,sum(decode(bitand(b.pb_bitmap, 12),4 ,1,0)) as partly_used ,sum(decode(bitand(b.pb_bitmap, 12),8 ,1,0)) as mostly_used ,sum(decode(bitand(b.pb_bitmap, 12),12,1,0)) as very_full from sysmaster:sysptnbit b where b.pb_bitmap > 0 group by b.pb_partnum ) as BM WHERE P.partnum = BM.partnum and bitand(p.flags,"0xE0") = 0 and sysmaster:partpagenum(P.partnum)>1;UPDATE STATISTICS HIGH FOR TABLE mon_page_usage(ID,dbsnum,partnum,lockid)',
NULL,
DATETIME(03:00:00) HOUR TO SECOND,
INTERVAL (1) DAY TO DAY,
INTERVAL (7) DAY TO DAY
);

EOF;
			$db->query($sel);
		}
	}
    
} // end class
?>
