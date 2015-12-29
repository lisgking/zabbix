<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2011, 2012.  All Rights Reserved
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
 
 /***********************************************************************
  * Public and Private functions:
  * The public functions are in the top half of this file. The private
  * functions (functions not directly accessible via SOAP) are in the
  * bottom half of this file.
  ***********************************************************************/
 
class BackupServer
{
    private $idsadmin;
    private $servername = null;
    
    const SYSADMIN = "sysadmin";
    const ERROR = -1;
  
    function __construct()
    {
        define ("ROOT_PATH","../../");
        define( 'IDSADMIN',  "1" );
        define( 'DEBUG', false);
        define( 'SQLMAXFETNUM' , 100 );
        include_once(ROOT_PATH."/services/serviceErrorHandler.php");
        set_error_handler("serviceErrorHandler");
		
		require_once(ROOT_PATH."lib/idsadmin.php");
        $this->idsadmin = new idsadmin(true);
    }

    /**
     * Get configuation or status info:
     * 
     * type = 'ontapWizard' indicates to get configuration info for the ontape configuration wizard
     * type = 'ontapeConfigTabGET' indicates to get already scheduled ontape configuration
     * type = 'ontapWizard' indicates to get ontape backup status
     */
	public function getConfigParams($type)
	{
		switch ($type) 
		{
		    case "ontapeWizard":
		    		return $this->getOntapeWizardInfo();
			        break;
		        
		    case "ontapeConfigTabGET":
		    		return $this->getBackupConfiguration($type);
			        break;
			        
			case "ontapeBackupStatus":
					return $this->getOntapeBackupStatus();
			        break;
			        
			case "onbarWizardPSM":
					return $this->getOnbarWizardInfo('PSM');
			        break;
			        
			case "onbarWizardUnknownSM":
					return $this->getOnbarWizardInfo('Unknown');
			        break;
			        
			case "onbarConfigTabPSM":
					// get sysadmin settings
					$result['SYSADMIN'] = $this->getBackupConfiguration($type);
					// get onconfig settings and onbar/onpsm values
					$result['ONCONFIG'] = $this->getOnbarWizardInfo('PSM');
					return array_merge($result['SYSADMIN'], $result['ONCONFIG']);
					break;
			
			case "onbarConfigTabUnknownSM":
					// get sysadmin settings
					$result['SYSADMIN'] = $this->getBackupConfiguration($type);
					// get onconfig settings and onbar/onpsm values
					$result['ONCONFIG'] = $this->getOnbarWizardInfo('Unknown');
					return array_merge($result['SYSADMIN'], $result['ONCONFIG']);
					break;

     /*	This query was to show the individual space backup section on the Status Tab selectively - i.e only if
      * individual spaces were backed up separately (and not a whole system backup). Now we have decided
      * to show this section in all cases. One of the reasons being the Status tab is empty/bland without this.
      * Leaving this query here in case we decide the data in this section for a whole system backup is really not
      * useful and this section in the tab should be removed selectively.
      *       
	  *     case "ontapeStatusSpaceSection":
	  *				$query8 = "select decode( count(unique level0) , count(*), 1, 0) as show_sp_sec " 
	  *					  . "from sysmaster:sysdbstab where bitand(flags, '0x2000') = 0";
	  *					  
	  *				$showSp = $this->idsadmin->doDatabaseWork($query8, 'sysmaster');
	  *				
	  *				$result = $showSp[0];
	  *				return $result;
	  */						 		        
		}

	}
	
	/**
	 * Get information needed by the ontape configuration wizard.
	 */
	private function getOntapeWizardInfo()
	{
		$result = array();
		$result['ontapeWizard'] = 'true';			
		$query = "select trim(cf_name) as cf_name, trim(cf_effective) as cf_effective "
				 . "from sysmaster:syscfgtab "
				 . "where cf_name IN ( 'TAPEDEV', 'TAPEBLK')";
					
		$params = $this->idsadmin->doDatabaseWork($query, 'sysmaster');
					
		/* rehash the result to be of the form: $result['TAPEDEV'] = '/path/to/backup' */
		foreach ($params as $row) 
		{
			$result[$row['CF_NAME']] = $row['CF_EFFECTIVE'];
		}
					
		return $result;
	}
	
	/**
	 * Get ontape configuration
	 */
	private function getBackupConfiguration($type) 
	{
		$result = array();
		$result['{$type}'] = 'true';
		$query = "select name, value from ph_threshold where task_name = 'OAT Backup task'";
		$thresholdParams = $this->idsadmin->doDatabaseWork($query, 'sysadmin');

		foreach ($thresholdParams as $row) 
		{
			$result[$row['NAME']] = $row['VALUE'];
		}					
	
		$query = "select trim(tk_name) as tk_name, tk_start_time, tk_monday, tk_tuesday, tk_wednesday, "
			  . "tk_thursday, tk_friday, tk_saturday,tk_sunday, tk_enable "
			  . "from ph_task where tk_name like 'OAT Backup Level%' order by tk_name";
		$taskParams = $this->idsadmin->doDatabaseWork($query, 'sysadmin');
					
		$idx = 0;
		foreach ($taskParams as $row) 
		{
			// 0 => Level 0; 1 => Level 1; 2 => Level 2
			$result[$idx++] = $row;
		}
										
		return $result;
	}
	
	/**
	 * Get the ontape backup status
	 */
	private function getOntapeBackupStatus() 
	{
		$result = array();
		$result['ontapeBackupStatus'] = 'true';	
		
		// Get the oldest backups
		$query = "SELECT "
				. "decode(level0,0, 'NEVER' ,trim((CURRENT - DBINFO('utc_to_datetime',level0))::char(40)) ) as oldest_Level0, "
				. "decode(level1,0, 'NEVER' ,trim((CURRENT - DBINFO('utc_to_datetime',level1))::char(40)) ) as oldest_Level1, "
				. "decode(level2,0, 'NEVER' ,trim((CURRENT - DBINFO('utc_to_datetime',level2))::char(40)) ) as oldest_Level2 "
				. "FROM "
				. "( "
				. "    SELECT "
				. "    min(level0) as level0, "
				. "    min(level1) as level1, "
				. "    min(level2) as level2 "
				. "    FROM sysdbstab "
				. "    WHERE BITAND(flags, '0x2000') = 0 "
				. ")";
		$oldestBackups = $this->idsadmin->doDatabaseWork($query, 'sysmaster');
		if (count($oldestBackups) > 0) 
		{
			$result['OLDEST_LEVEL0'] = $oldestBackups[0]['OLDEST_LEVEL0'];
			$result['OLDEST_LEVEL1'] = $oldestBackups[0]['OLDEST_LEVEL1'];
			$result['OLDEST_LEVEL2'] = $oldestBackups[0]['OLDEST_LEVEL2'];
		}
					
		// Find out if any backups are currently running on the database server
		$query = "SELECT count(*) as count FROM sysdbstab WHERE bitand(flags,'0x4000') > 0";
		$running = $this->idsadmin->doDatabaseWork($query, 'sysmaster');
		if (count($running) > 0) 
		{
			$result['BACKUP_RUNNING'] = (($running[0]['COUNT'] > 0)? 1:0);
		}
		
		// Get time of next scheduled backup
		$query =  "select trim(tk_name) as tk_name, (tk_next_execution - current) as next "
			   . "from ph_task where tk_name like '%OAT Backup Level%' and tk_enable = 't'";
		$nextExec = $this->idsadmin->doDatabaseWork($query, 'sysadmin');
		foreach ($nextExec as $row) 
		{
		 	switch ($row['TK_NAME']) 
		 	{
		 		case 'OAT Backup Level 0':
		 			$result['NEXT_LEVEL0'] = trim($row['NEXT']);
		 			break;
		 		case 'OAT Backup Level 1':
		 			$result['NEXT_LEVEL1'] = trim($row['NEXT']);
		 			break;
		 		case 'OAT Backup Level 2':
		 			$result['NEXT_LEVEL2'] = trim($row['NEXT']);
		 			break;
		 	}						
		}

		// Get threshold for how often backups should run
		$query = "select name, value " 
			  . "from ph_threshold "
			  . "where task_name = 'check_backup' and name like 'REQUIRED%'";
		$reqBackup = $this->idsadmin->doDatabaseWork($query, 'sysadmin');
		if (count($reqBackup) > 0) 
		{
			foreach ($reqBackup as $row) 
			{
			 	switch ($row['NAME']) 
			 	{
			 		case 'REQUIRED LEVEL 0 BACKUP':
			 			$result['MAX_INTERVAL_L0'] = trim($row['VALUE']);
			 			break;
			 		case 'REQUIRED LEVEL BACKUP':
			 			$result['MAX_INTERVAL_ANY'] = trim($row['VALUE']);
			 			break;
			 	}						
			}
		} else {
			$result['MAX_INTERVAL_BACKUP'] = "false";
		}
				
		return $result;
	}
	
	/**
	 * Insert backup parameters (or update them, or insert the backup task, etc.).
	 * This function is used generically to make changes to the backup configuration.
	 * 
	 * @param $stmts - SQL statements to run
	 * @param $deploy_procedure - indicates whether 'oatbackup' procedure potentially needs to be deployed
	 * @param $trigger_error - true indicates that trigger_error should be used to return errors;
	 * 	                       false indicates that errors should be caught and returned in the result array.
	 */
	function insertParams($stmts, $deploy_procedure = true, $trigger_error = true)
	{
		if ($deploy_procedure)
		{
			// Create the BACKUP group in sysadmin:ph_group if not already created.
			$this->idsadmin->checkForFeature('BACKUP','Backup and Restore Information');
			
	   		// Test and deploy oatBackup() procedure if necessary		
			$this->testAndDeployOatBackup();
		}
		
		$exceptions = !$trigger_error;
		try {
			$res1 = $this->idsadmin->doDatabaseWork($stmts, 'sysadmin', $exceptions);
		} catch (PDOException $e) {
			// We only come here if there is an exception and $trigger_error = false, 
			// meaning we want to catch errors and handle them in a wizard.
			$result['RETURN_CODE'] = $e->getCode();
			$result['RETURN_MESSAGE'] = $e->getMessage();
			$result['SQL'] = $stmts;
			return $result;
		}
		$result[0] = $res1;
		$result['RETURN_CODE'] = 0;
				
		return $result;
    }
    
    /**
     * Get the backup status for each dbspace
     * 
     * @param rows_per_page: -1 indicates all rows
     * @param page: current page
     * @param sort_col: column to sort by
     */
    public function getStatusSpaces($rows_per_page = -1, $page = 1, $sort_col = null)
    {
    	$result = array();
    	
    	$sql = "SELECT dbsnum, name, "
    		 . "decode(level0,0, 'NEVER' ,(CURRENT - DBINFO('utc_to_datetime',level0))::char(40) ) as OldestLevel0, "
    		 . "decode(level1,0, 'NEVER' ,(CURRENT - DBINFO('utc_to_datetime',level1))::char(40) ) as OldestLevel1, "
    		 . "decode(level2,0, 'NEVER' ,(CURRENT - DBINFO('utc_to_datetime',level2))::char(40) ) as OldestLevel2, "
    		 . "CASE WHEN bitval(flags,'0x10')>0 THEN 'blobspace' "
     		 . " WHEN bitval(flags,'0x8000')>0 THEN 'sbspace' "
    		 . " ELSE 'dbspace' "
			 . " END as dbstype "
    		 . "FROM sysmaster:sysdbstab "
    		 . "WHERE BITAND(flags, '0x2000') = 0";
    	if ($sort_col == null)
    	{
    		// default sort order
    		$sort_col = "level0";
    	}

    	$result['DATA'] = $this->idsadmin->doDatabaseWork($this->idsadmin->transformQuery($sql,$rows_per_page,$page,$sort_col), "sysmaster");
    	
    	$result['COUNT'] = 0;
    	$temp = $this->idsadmin->doDatabaseWork($this->idsadmin->createCountQuery($sql), "sysmaster");
    	if (count($temp) > 0)
    	{
    		$result['COUNT'] = $temp[0]['COUNT'];
    	}
    	return $result;
    }
    
	/**
     * Get the backup command history
     * 
     * @param rows_per_page: -1 indicates all rows
     * @param page: current page
     * @param sort_col: column to sort by
     */
    public function getBackupCommandHistory($rows_per_page = -1, $page = 1, $sort_col = null)
    {
    	$result = array();
    	
    	$sql = "SELECT ".
    		" trim(cmd_user)||' @ '|| cmd_hostname as user, " .
    		" cmd_exec_time, " .
    		" cmd_executed, " .
    		" cmd_ret_status, " .
    		" cmd_ret_msg " .
    		" FROM command_history " .
    		" WHERE cmd_executed like '%ontape%' or cmd_executed like '%onbar%' or cmd_executed like '%onsmsync%'";
    	if ($sort_col == null)
    	{
    		// default sort order
    		$sort_col = "cmd_number desc";
    	}
    	
    	$result['DATA'] = $this->idsadmin->doDatabaseWork($this->idsadmin->transformQuery($sql,$rows_per_page,$page,$sort_col), "sysadmin");
    	
    	$result['COUNT'] = 0;
    	$temp = $this->idsadmin->doDatabaseWork($this->idsadmin->createCountQuery($sql), "sysadmin");
    	if (count($temp) > 0)
    	{
    		$result['COUNT'] = $temp[0]['COUNT'];
    	}
    	
    	return $result;
    }
    
	/**
     * Get onbar activity log
     * 
     */
    public function getOnBarActLog()
    {
    	$sql = "SELECT SKIP 1 trim(line) as msg FROM sysbaract_log WHERE offset > -10000";
    	$result = $this->idsadmin->doDatabaseWork($sql, "sysmaster");
    	return $result;
    }
    
    /**
	 * Get information needed by the onbar configuration wizard.
	 */
	private function getOnbarWizardInfo($type)
	{
		$result = array();
		$result['onbarWizard'] = 'true';			
		$query = "select trim(cf_name) as cf_name, trim(cf_effective) as cf_effective "
				 . "from sysmaster:syscfgtab "
				 . "where cf_name IN ('BAR_MAX_BACKUP', 'BAR_NB_XPORT_COUNT', 'BAR_XFER_BUF_SIZE') "
				 . "UNION "
				 . "select trim(env_name) as cf_name, trim(env_value) as cf_effective "
				 . "from sysmaster:sysenv "
				 . "where env_name IN('INFORMIXDIR')";
					
		$params = $this->idsadmin->doDatabaseWork($query, 'sysmaster');
					
		/* rehash the result to be of the form: $result['BAR_MAX_BACKUP'] = '10' */
		foreach ($params as $row) 
		{
			$result[$row['CF_NAME']] = $row['CF_EFFECTIVE'];
		}
		
		$query2 = "select t2.name "
				  . "from sysmaster:syschunks t1, sysmaster:sysdbspaces t2, sysmaster:syslogfil t3, sysmaster:sysplog t4 "
				  . "where t1.chknum!=t3.chunk and t1.chknum!=t4.pl_chunk and t1.dbsnum!=1 and t2.is_temp!=1 and t1.dbsnum = t2.dbsnum group by name";
		$nonCritDbSpaces = $this->idsadmin->doDatabaseWork($query2, 'sysmaster');
		$result['NUM_NON_CRIT_DBSPACES'] = count($nonCritDbSpaces);
		
		$query3 = "select os_name osname from sysmachineinfo";
		$serverOS = $this->idsadmin->doDatabaseWork($query3, 'sysmaster');
		$result['SERVER_OS'] = trim($serverOS[0]['OSNAME']);
		$result['PSM_DEVICE_LIST'] = 'false';
		$result['PSM_CATALOG_PATH'] = 'false';
		
		if ($type == 'PSM')
		{
			$task['COMMAND'] = "'onpsm',";
			$task['PARAMETERS'] = "'-D list -xml'";
		
			$psmDeviceList = $this->idsadmin->executeSQLAdminAPICommand($task);
			$result['PSM_DEVICE_LIST'] = $psmDeviceList;
			
			$task['COMMAND'] = "'onpsm',";
			$task['PARAMETERS'] = "'-version all'";
		
			$psmCatalogPath = $this->idsadmin->executeSQLAdminAPICommand($task);
			$result['PSM_CATALOG_PATH'] = $this->parseOnpsmV($psmCatalogPath['RESULT_MESSAGE']);
		}
		
		//error_log("result is:" . var_export($result,true));
					
		return $result;
	}
	
	function parseOnpsmV($onpsmVersion)
	{
		$this->idsadmin->load_lang("backup");
		$inpPattern = '/\nPSM Catalog:.*\n/';
		
	    if (preg_match($inpPattern, $onpsmVersion, $matches) == 1) {
		    $psmCat = preg_split('/:/',$matches[0]);
		    $catPath = trim($psmCat[1]);
		    if (strlen($catPath) <= 0) {
		    	$catPath = $this->idsadmin->lang("notAvail");
		    }
		} else {
			$catPath = $this->idsadmin->lang("notAvail");
		}
	
		return $catPath;
	}

	/**
	 * Insert Onbar backup parameters (or update them, or insert the backup task, etc.).
	 * This function is used generically to make changes to the backup configuration.
	 * 
	 * @param $stmts - SQL statements to update params in sysadmin
	 * @param $barMaxBackup, $barXportCount, $barXferBufsizePages - onbar backup parameters updated using the sql admin api
	 * @param $deploy_procedure - 'oatOnbarBackup' procedure is now designed to be deployed at server start-up time by running sch_oat.sql and/or conversion scripts, upgrade_1210.sql
	 * @param $trigger_error - true indicates that trigger_error should be used to return errors;
	 * 	                       false indicates that errors should be caught and returned in the result array.
	 */	
	public function updateOnbarParams($stmts, $barMaxBackup, $barXportCount, $barXferBufsizePages, $deploy_procedure=true, $trigger_error=true)
	{
		if ($deploy_procedure)
		{
			// Create the BACKUP group in sysadmin:ph_group if not already created.
			$this->idsadmin->checkForFeature('BACKUP','Backup and Restore Information');
			
	   		// Test if oatOnbarBackup() procedure has been created in the server.		
			$testRes = $this->testOatOnbarBackup();
			if ($testRes == -1) {
				$result['RETURN_CODE'] = -1;
				$this->idsadmin->load_lang("backup");	    	
				$result['RETURN_MESSAGE'] = $this->idsadmin->lang("onbarBackProcMissing");
				return $result;
			}
		}
		
		$exceptions = !$trigger_error;
		if (strlen($stmts) != 0) {			
			try {
				$res1 = $this->idsadmin->doDatabaseWork($stmts, 'sysadmin', $exceptions);
			} catch (PDOException $e) {
				// We only come here if there is an exception and $trigger_error = false, 
				// meaning we want to catch errors and handle them in a wizard.
				$result['RETURN_CODE'] = $e->getCode();
				$result['RETURN_MESSAGE'] = $e->getMessage();
				$result['SQL'] = $stmts;
				return $result;
			}
			$result[0] = $res1;
		}
		$result['RETURN_CODE'] = 0;
		
		$task['COMMAND'] = "'modify config persistent',";
		
		if ($barMaxBackup != -1) {
			$task['PARAMETERS'] = "'BAR_MAX_BACKUP','{$barMaxBackup}'";
			$barMBkp = $this->idsadmin->executeSQLAdminAPICommand($task);
			$result['BAR_MAX_BACKUP'] = $barMBkp;
		}
	
		if ($barXportCount != -1) {
			$task['PARAMETERS'] = "'BAR_NB_XPORT_COUNT','{$barXportCount}'";
			$barNumBuf = $this->idsadmin->executeSQLAdminAPICommand($task);
			$result['BAR_NB_XPORT_COUNT'] = $barNumBuf;
		}
		if ($barXferBufsizePages != -1) {
			$task['PARAMETERS'] = "'BAR_XFER_BUF_SIZE','{$barXferBufsizePages}'";
			$barBufSize = $this->idsadmin->executeSQLAdminAPICommand($task);
			$result['BAR_XFER_BUF_SIZE'] = $barBufSize;
		}
				
		return $result;	
	}

   	/**
     * Tests if the 'oatBackup' stored procedure exists on sysadmin.
     * If not, this function creates the 'oatBackup' procedure.
     **/    
    private function testAndDeployOatBackup()
    {
    	// Check if oatBackup procedure already exists
    	$qry = "select COUNT(*) as UDREXISTS from sysprocedures where procname = 'oatbackup'";	    	
	    $res = $this->idsadmin->doDatabaseWork($qry, 'sysadmin'); 	    		    		    	    	
	    if (count($res) == 1){
	    	if ($res[0]['UDREXISTS'] == 1){	
	    		// procedure already exists, so return
	    		return;    			
	    	}
	    }
	    
	    // If not, create the procedure
    	$qry = <<<EOM
CREATE FUNCTION oatBackup(backupLevel INT, task_id INT, task_seq INT)
RETURNING INT

  DEFINE tapedevice   CHAR(257);
  DEFINE deviceType   CHAR(10);
  DEFINE tapeblock    INTEGER;
  DEFINE args1        CHAR(40);
  DEFINE args2        CHAR(1000);
  DEFINE args3        CHAR(5);
  DEFINE errsql       INTEGER;
  DEFINE errisam      INTEGER;
  DEFINE errtext      VARCHAR(255);
  DEFINE rc           INTEGER;

 ON EXCEPTION SET errsql, errisam, errtext
   INSERT INTO ph_alert (
       ID, alert_task_id,alert_task_seq, alert_type, alert_color,
       alert_state, alert_object_type, alert_object_name,
       alert_message, alert_action
      ) VALUES (
       0,task_id, task_seq, 'ERROR', 'RED',
       'NEW', 'SERVER','BACKUP LEVEL '||backupLevel,
       'Level '||backupLevel||' backup of server FAILED with error('
           ||errsql||','||errisam||' - '||errtext||').',
      NULL
      );
 END EXCEPTION;

-- SET DEBUG FILE TO '/tmp/debug.oatBackup.out';
-- TRACE ON;

   LET tapedevice = NULL;
   {*** Get the ontape tape device ***}
   SELECT value::CHAR(257) INTO tapedevice FROM ph_threshold
      WHERE name = 'ONTAPE_TAPEDEV';
   IF tapedevice IS NULL THEN
        INSERT INTO ph_alert (
             ID, alert_task_id,alert_task_seq, alert_type, alert_color,
             alert_state, alert_object_type, alert_object_name,
             alert_message, alert_action
            ) VALUES (
             0,task_id, task_seq, 'ERROR', 'RED',
             'NEW', 'SERVER','oatBackup stored procedure',
             'Error executing procedure oatBackup. sysadmin:ph_threshold does not have an entry for ONTAPE_TAPEDEV which contains the TAPEDEV value for backup',
            NULL
            );
       RETURN -1;
   END IF

   {*** Get the device type: file, directory or tape ***}
   SELECT value::CHAR(10) INTO deviceType FROM ph_threshold
      WHERE name = 'ONTAPE_DEVICE_TYPE';

   LET tapeblock = 512;
   {*** Get the ontape block size ***}
   SELECT value::integer INTO tapeblock FROM ph_threshold
      WHERE name = 'ONTAPE_TAPEBLK';

   {*** Build the command ***}
   LET args1 = 'ontape archive ' || TRIM(deviceType) ||' level ' || backupLevel;

   IF deviceType = 'file'  THEN
      LET args2 = TRIM(tapedevice) ||'_L'||backupLevel;
   ELSE
      LET args2 = tapedevice;
   END IF

   LET args3 = tapeblock;

   LET rc = -1;
   BEGIN
       ON EXCEPTION SET errsql, errisam, errtext
          INSERT INTO ph_alert (
               ID, alert_task_id,alert_task_seq, alert_type, alert_color,
               alert_state, alert_object_type, alert_object_name,
               alert_message, alert_action
              ) VALUES (
               0,task_id, task_seq, 'ERROR', 'RED',
               'NEW', 'SERVER','BACKUP LEVEL '||backupLevel,
               'Level '|| backupLevel ||' backup of server FAILED starting ontape with error('
                   ||errsql||','||errisam||' - '||errtext||').',
              NULL
              );
       END EXCEPTION;

       {*** Run the command ***}
       SELECT admin(TRIM(args1),TRIM(args2),TRIM(args3)) INTO rc FROM systables WHERE tabid=1;

       IF rc > 0 THEN
         INSERT INTO ph_alert (
            ID, alert_task_id,alert_task_seq, alert_type, alert_color,
            alert_state, alert_object_type, alert_object_name,
            alert_message, alert_action
          ) VALUES (
            0,task_id, task_seq, 'INFO', 'GREEN',
            'NEW', 'SERVER','BACKUP LEVEL ' || backupLevel,
            'Level ' || backupLevel || ' backup of server completed successfully.',
            NULL
          );
       ELSE
          INSERT INTO ph_alert (
               ID, alert_task_id,alert_task_seq, alert_type, alert_color,
               alert_state, alert_object_type, alert_object_name,
               alert_message, alert_action
              ) VALUES (
               0,task_id, task_seq, 'ERROR', 'RED',
               'NEW', 'SERVER','BACKUP LEVEL '||backupLevel,
               'Level '|| backupLevel ||' backup of server FAILED starting ontape with error(see sysadmin:command_history.cmd_number=' ||ABS(rc),
              NULL
              );
       END IF

   END

RETURN rc;

END FUNCTION;
   
EOM
;    	
    	$result = $this->idsadmin->doDatabaseWork($qry, 'sysadmin');
    	return $result;    			
    }
    
   	/**
     * Tests if the 'oatOnbarBackup' stored procedure exists on sysadmin.
     * If not, reports that it is missing. The procedure is deployed at server startup time.
     **/    
    private function testOatOnbarBackup()
    {
    	// Check if oatBackup procedure already exists
    	$qry = "select COUNT(*) as UDREXISTS from sysprocedures where procname = 'oatonbarbackup'";	    	
	    $res = $this->idsadmin->doDatabaseWork($qry, 'sysadmin'); 	    		    		    	    	
	    if (count($res) == 1){
	    	if ($res[0]['UDREXISTS'] == 1){	
	    		// procedure already exists, so return
	    		return;    			
	    	} else {
	    	    return -1;
	    	}
	    }
	    
    	return -1;    			
    }
    
}
	
?>
