<?php
/*
 ************************************************************************
 *  Licensed Materials - Property of IBM
 *
 *  "Restricted Materials of IBM"
 *
 *  OpenAdmin Tool for IDS
 *  Copyright IBM Corporation 2009, 2012.  All rights reserved.
 ************************************************************************
 */
 
 /***********************************************************************
  * Public and Private functions:
  * The public functions are in the top half of this file. The private
  * functions (functions not directly accessible via SOAP) are in the
  * bottom half of this file.
  ***********************************************************************/
 
/**
 * this function compares two sync/check jobs. We define identical jobs as jobs that have the same
 * name AND the same type (thus, two jobs can have the same name and still not identical).
 * In order to be able to sort jobs and organize them in data structure we provided a way so 
 * we can compare them and obtain 3 possible outcomes: -1 ($job1 < $job2), 1 ($job1 > $ job2) and 0
 * ($job1 == $job2). 
 * NB: this function is being used as a call-back function (or function pointer) by built-in PHP
 * functions. SOAP does not allow this mechanism if the call-back function is member of a class. For this
 * reason we defined this function outside the ERServer class.
 */
function compareCheckSyncJobs($job1, $job2)
	{
		if($job1['NAME'] == $job2['NAME'] && $job1['TYPE'] == $job2['TYPE'])
			$result = 0;
		else 
			$result = strcmp($job1['TYPE'].$job1['NAME'], $job2['TYPE'].$job2['NAME']);// -1 or 1
		
		return $result;
	}
/**
 * This function determine whether a job is a failed job (case 4 and 5)
 * @return true if it is not a failed job; false otherwise.
 * @param object $job
 */	
function detectNonFailedJob($job)
{
	return ($job['STATUS'] == "D" || 
			$job['STATUS'] == "R" || 
			$job['STATUS'] == "C" || 
			$job['STATUS'] == "F");
}

class ERServer
{
    private $idsadmin;
    private $servername = null;
    private $connectionDb;

    const ERROR = -1;
    const LVARCHAR_LENGTH = 2048;
    const REAL_LVARCHAR_LENGTH = 32000;
    const MAX_NUM_ADMIN_PARAMS = 6;
    const MAX_ADMIN_ASYNC_LENGTH = 4096;
	const OUT_OF_SYNC_REPL_ERR = 178;
	const OUT_OF_SYNC_REPLSET_ERR = 213;

    /* database and table names */
    const REPLCHECK_STAT = "replcheck_stat";
    const REPLCHECK_STAT_NODE = "replcheck_stat_node";
	const JOB_STATUS = "job_status";
	const COMMAND_HISTORY = "command_history";
    const SYSCDR = "syscdr";
	const SYSADMIN = "sysadmin";
	const PH_TASK = "ph_task";
	const SYSMASTER = "sysmaster";
	
    /* the status of a cdr check/sync job */
    const STATUS_ABORTED = "A";						//case 5
    const STATUS_DEFINED = "D";
    const STATUS_RUNNING = "R";
    const STATUS_COMPLETED_INSYNC = "C";
    const STATUS_COMPLETED_OUTOFSYNC = "F";
    const STATUS_ABORTED_WITHOUT_SYSCDR = "X";		//case 4
	const STATUS_SOME_REP_ABORTED = "W";			//case 5
	const STATUS_SCHEDULED_NEVER_BEEN_RUN = "S";			//case 1
    const ERROR_MESSAGE_FOR_REPLICATESET_NAME_NOT_FOUND = "Set name unknown";
	
	const MAX_NUMB_OF_ER_TASKS_TO_DELETE = 5000;

	/* the type flag of check/sync jobs */
	const SYNC_TYPE = "S";
	const SYNC_TYPE_WORD = "sync";
	const CHECK_TYPE = "C";
	const CHECK_TYPE_WORD = "check";
	
	/* ats/ris directory constants */
	const WINDOWS_OS = 'Windows';
	const WINDOWS_FILE_SEPARATOR = '\\';
	const FILE_SEPARATOR = '/';
	const TMP_DIR_NAME = 'tmp';
	const ATS = 'ats';
	const RIS = 'ris';
    
    /* ER group name constant */
    const ER_GROUP_NAME = 'ER';
    
	/* Constant for partdef.flags */
    const A_USETABOWNER = 0x04;
    const A_USEINFORMIX = 0x08;
    
    function __construct()
    {
        define ("ROOT_PATH","../../../../");
        define( 'IDSADMIN',  "1" );
        define( 'DEBUG', false);
        define( 'SQLMAXFETNUM' , 100 );
        include_once(ROOT_PATH."/services/serviceErrorHandler.php");
        set_error_handler("serviceErrorHandler");
        
        require_once(ROOT_PATH."/services/idsadmin/clusterdb.php");
        $this->connectionsDb = new clusterdb(true);

        // This is where the connection.db password hooks are, so we need to include this
        // file so we can call the decode/encode functions.
        require_once(ROOT_PATH."lib/connections.php");
        
        require_once(ROOT_PATH."lib/idsadmin.php");
        $this->idsadmin = new idsadmin(true);
        $this->idsadmin->load_lang("er");

        require_once(ROOT_PATH."lib/feature.php");
        $this->idsadmin->phpsession->set_serverInfo($this->idsadmin);
        
        // Multi-byte support for 11.70.xC4.  For lower versions, reset phpsession's
        // DBLOCALE variables to the defaults (en_US.8895-1).
        if ( ! Feature::isAvailable( Feature::PANTHER_UC4, $this->idsadmin) ) 
        {
        	$this->idsadmin->phpsession->reset_dblocales_to_default();
        }

    }

    /**************************************************************************
     * SOAP service functions
     * - The functions in this section are explosed to as a service via SOAP.
     *************************************************************************/
	
	function testConnection($hostName, $portNum, $serverName, $username, $password, $protocol)
	{
		$state = 1;
		$statemessage="Online";

		require_once (ROOT_PATH."lib/PDO_OAT.php");
		try 
		{
			$tdb = new PDO_OAT($this->idsadmin,$serverName,$hostName,$portNum,$protocol,"sysmaster","",null,$username,$password);
		}
		catch(PDOException $e) 
		{
			$message=preg_split("/:/", $e->getMessage());
			$statemessage = $message[sizeof($message)-1];
			$statemessage = "{$this->idsadmin->lang("ConnectionFailed")}: {$statemessage}";
			$state = 3;
		}
		$tdb = null;
		return $statemessage;
	}
	
	/*function updateReviewStatus($errorseqnum, $errorserv, $reviewed)
    {
    	$query1 = "SELECT servid FROM syscdrs WHERE servname = '{$errorserv}'";
    	$server_id = $this->doDatabaseWork($query1);

    	$query2 = "UPDATE cdr_errors SET errreviewed = '{$reviewed}' where source_id = {$server_id[0]['SERVID']} AND remote_seqnum = $errorseqnum";
    	$this->doDatabaseWork($query2, 'syscdr');
    	
    	return true;
    }*/
	
	function getCurrentOATGroup()
	{
		return $this->idsadmin->phpsession->get_group();
	}
	
	function getConnectionsdbServers ()
	{
	 	$grpnum = $this->getCurrentOATGroup();
	 	 
	    $query = "SELECT conn_num      "
               . "     , host          "
               . "     , server        "
               . "  FROM connections   "
               . " WHERE group_num = {$grpnum} " 
               . " ORDER BY server";

        $result = $this->doConnectionsDatabaseWork ( $query ,true );
		return $result;
	}
	
	
	function getServerFromCache ( $server, $hostname )
	{
	    // If hostname is fully qualified hostname, strip it down to simplified hostname
	    $array = explode(".", $hostname);
        if (!is_numeric($array[0]))
        {
        	$hostname = $array[0];
        }
        // If hostname has an initial '*' character, remove it
        if (strlen($hostname) > 1 && substr($hostname, 0, 1) == "*")
        {
        	$hostname = substr($hostname,1);
        }
        
	
        $query = "SELECT conn_num      "
               . "     , group_num     "
               . "     , nickname      "
               . "     , host          "
               . "     , port          "
               . "     , server        "
               . "     , idsprotocol   "
               . "     , lat           "
               . "     , lon           "
               . "     , username      "
               . "     , password      "
               . "     , lastpingtime  "
               . "     , laststatus    "
               . "     , laststatusmsg "
               . "     , lastonline    "
               . "     , cluster_id    "
               . "     , last_type     "
               . "  FROM connections   "
               . " WHERE server = '{$server}' "
               . " AND host like '{$hostname}%'"
               . " AND group_num={$this->getCurrentOATGroup()}";

        $result = $this->doConnectionsDatabaseWork ( $query ,true );
		return $result;
	}
	
	function addERServerToCache($group_num
							  , $host
                              , $port
                              , $server 
                              , $idsprotocol
                              , $lat
                              , $lon
                              , $username
                              , $password
                              , $cluster_id
                              , $last_type)
	{
		$password = connections::encode_password($password);
		
		$query = "INSERT INTO connections   "
               . "        ( group_num       "
               . "        , host            "
               . "        , port            "
               . "        , server          "
               . "        , idsprotocol     "
               . "        , lat             "
               . "        , lon             "
               . "        , username        "
               . "        , password        "
               . "        , cluster_id      "
               . "        , last_type )     "
               . " VALUES (  {$group_num}   	"
               . "        , '{$host}'       "
               . "        , '{$port}'       "
               . "        , '{$server}'     "
               . "        , '{$idsprotocol}'"
               . "        ,  {$lat}         "
               . "        ,  {$lon}         "
               . "        , '{$username}'   "
               . "        , '{$password}'   "
               . "        ,  {$cluster_id}  "
               . "        ,  {$last_type} ) ";

        $this->doConnectionsDatabaseWork($query);
		$query = "select conn_num, server, host from connections where server = '$server' and host = '$host'";
		return $this->doConnectionsDatabaseWork($query);		
	}
	
    function getERTopologyInfo($conn = null)
	{
		$result = array();
		$qry = "select syscdrs.servid as serverid,  syssqlhosts.dbsvrnm, "
			. "ishub, isleaf, servstate, rootserverid from syscdrs, "
			. "syssqlhosts where syscdrs.servid = syssqlhosts.svrid";
		$result = $this->doDatabaseWork($qry, "sysmaster", $conn);
		
		foreach ($result as $index => $data)
		{
			$result[$index]['NODE_MEMBERS'] = $this->getERNodeMembers($data['DBSVRNM']);
		}

		return $result;		
	}
	
	function getServerNameOfGroup($group)
	{
		$qry = "select name from hostdef where groupname = '{$group}'";
		$result = $this->doDatabaseWork ( $qry, 'syscdr' );
		return $result[0]['NAME'];
	}
	
	function getQODStatus ($group = null) 
	{
		$qry = "SET ISOLATION COMMITTED READ; ";
		$this->doDatabaseWork ( $qry, 'syscdr' );
		$db = null;
		$result = array();

		$server = "";

		$db = $this->idsadmin->get_database("syscdr");
		$sql = "";
		
		if($group != null)
		{
			$server = $this->getServerNameOfGroup($group);
			$sql = "select (state = 1) as qod_is_on , (count(state) > 0) as qod_is_defined from syscdr@{$server}:qod_control_tab group by 1";
		}
		else
		{
			$sql = "select (state = 1) as qod_is_on , (count(state) > 0) as qod_is_defined from qod_control_tab group by 1";
		}
		
		$stmt = $db->query($sql);
		$err = $db->errorInfo();
		
		if (isset($err[1]) && $err[1] != 0)
        {
        	$err = "{$this->idsadmin->lang("Error")}: {$err[2]} - {$err[1]}";
            $stmt->closeCursor();
            trigger_error($err,E_USER_ERROR);
        }
        else
        {
        	$res = $stmt->fetch(PDO::FETCH_ASSOC);
			if($res == null || count($res) == 0)
			{
				$result = array('QOD_IS_ON' => '0', 'QOD_IS_DEFINED' => '0');
			}
			else
			{
				$result = $res;
			}
            $stmt->closeCursor();
        }
		
		return $result;
	}
	
    /**
     * Obtain the summary information about the current ER node.
     * This function is explosed as a service via SOAP.
     * Maps to the ER Server Summary Screen.
     */
   	function getNodeSummaryInfo()
   	{
        $result = array();
         
        /* 1. Get the server's current time */
        $current_time = $this->getNodeCurrentTime();


        /* 2. Get overall ER state information */
        $er_component_states = $this->getNodeERState();

        if (trim($er_component_states['ERSTATE']) == "Uninitialized" ||
        trim($er_component_states['ERSTATE']) == "Shut Down") {
            $result['CURRENTTIME'] = $current_time;
            $result['STATES'] = $er_component_states;
        }
        else {

            /* 3. Get Capture information */
            $capture_result = $this->getNodeCaptureSummary();
             
            /* 4. Get the send queue information */
            $sendq_result = $this->getNodeSendQueueSummary();
             
            /* 5. Get the network info */
            $network_result = $this->getNodeNetworkSummary();

            /* 6. Get spool disk info */
            $disk_result = $this->getDiskSummary();

            /* 7. Get the receive queue information */
            $recvq_result = $this->getNodeReceiveQueueSummary();
             
            /* 8. Apply */
            $apply_result = $this->getNodeApplySummary();
             
            /* 9. Number of unreviewed ER errors */
            $errors = $this->getNodeErrorCount();
             
            /* 10. Replicate information */
            $replicate_results = $this->getNodeReplicateSummary();
             
            /* 11. Package the results */
            $result['CURRENTTIME'] = $current_time;
            $result['STATES'] = $er_component_states;
            $result['CAPTURE'] = $capture_result;
            $result['SENDQ'] = $sendq_result;
            $result['NETWORK'] = $network_result;
            $result['DISK'] = $disk_result;
            $result['RECVQ'] = $recvq_result;
            $result['APPLY'] = $apply_result;
            $result['ERRORS'] = $errors;
            $result['REPLICATES'] = $replicate_results;
        }

        return $result;
    }
    
    /**
     * Obtain the detailed Apply information about the current ER node.
     * This function is explosed as a service via SOAP.
     * Maps to the Node Summary -> Apply Details page
     */
    function getNodeApplyDetails()
    {
        $result = array();
        
        // Get the server's current time 
        $current_time = $this->getNodeCurrentTime();
        
        // Get overall ER state information 
        $er_component_states = $this->getNodeERState();

        $result['CURRENTTIME'] = $current_time;
        $result['STATES'] = $er_component_states;
        
        // The rest of the apply details data is only applicable 
        // if the ER state is not 'Uninitialized' or 'Shut Down'
        if (! (trim($er_component_states['ERSTATE']) == "Uninitialized" ||
            trim($er_component_states['ERSTATE']) == "Shut Down")) 
        {
            // Get the Apply summary data
            $apply_result = $this->mergeArrays($this->getNodeApplySummary(),
                 $this->getNodeApplyGlobalDetails());
            
            // Get the Apply stats per node
            $apply_per_node = $this->getApplyStatsPerNode();
        
            // Package the results 
            $result['APPLY'] = $apply_result;
            $result['APPLY_PER_NODE'] = $apply_per_node;
        }
        
        return $result;
    }
    
    /**
     * Obtain the detailed Send Queue information about the current ER node.
     * This function is exposed as a service via SOAP.
     * Maps to the Node Summary -> Send Queue Details page
     */
    function getNodeSendQueueDetails()
    {
        $result = array();
        
        // Get the server's current time 
        $current_time = $this->getNodeCurrentTime();
        
        // Get overall ER state information 
        $er_component_states = $this->getNodeERState();

        $result['CURRENTTIME'] = $current_time;
        $result['STATES'] = $er_component_states;
        
        // The rest of the apply details data is only applicable 
        // if the ER state is not 'Uninitialized' or 'Shut Down'
        if (! (trim($er_component_states['ERSTATE']) == "Uninitialized" ||
            trim($er_component_states['ERSTATE']) == "Shut Down")) 
        {

            // Get the Send Queue summary data
            $result['SENDQ'] = $this->mergeArrays($this->getNodeSendQueueSummary(),
                $this->getNodeSendQueueDetailInfo()); 
                
            // Get Capture summary data (to show the current capture log position 
            // on the send queue summary page)
            $result['CAPTURE'] = $this->getNodeCaptureSummary();
            
            // Get the Send Queue data per target node
            $result['SENDQ_PER_NODE'] = $this->getSendQueuePerTargetNode();
            
            // Get the Send Queue data per replicate
            $result['SENDQ_PER_REPL'] = $this->getSendQueuePerReplicate();
        }
        
        return $result;
    }

    
	/*
	 * Obtain information about all ATS files on the current ER node.
	 * This function is exposed as a SOAP service.
	 */
	public function getATSFilesInfo(){
		$result = array();
		
		$er_component_states = $this->getNodeERState();
        $result['STATES'] = $er_component_states;
        
        // The rest of the apply details data is only applicable 
        // if the ER state is not 'Uninitialized' or 'Shut Down'
        if (! (trim($er_component_states['ERSTATE']) == "Uninitialized" ||
            trim($er_component_states['ERSTATE']) == "Shut Down")) 
        {
    		/* Get total number of ATS files */
    		$result = $this->mergeArrays($result, $this->getTotalNumberOfATSFiles());
        	
			/*
			 * Hot fix for defect 195834. The if statement below prevents an error message that is display when opening the node details page.
			 */
			$nodeInfo = $this->getNodeInfo();
			$atsFilesKeyed = array(); // key is the filename
			
			if($nodeInfo['ATSDIR'] != "/dev/null" && $nodeInfo['ATSDIR'] != "NUL" &&
				$nodeInfo['RISDIR'] != "/dev/null" && $nodeInfo['RISDIR'] != "NUL")
			{
	        	/* Get info about each ATS file */
	        	$qry = "select atsd_rid as id, trim(atsd_file) as filename, atsd_ctime as creationTime, "
	    			   . "atsd_size as size from syscdr_atsdir order by atsd_rid";
	    		$tmp = $this->doDatabaseWork($qry);
	    		
	    		foreach ($tmp as $row) {
	    			$r = array();
	    			$r['ID'] = $row['ID'];
	    			$r['FILENAME'] = $row['FILENAME'];
	    			$r['CREATIONTIME'] = $row['CREATIONTIME'];
	    			$r['SIZE'] = $row['SIZE'];
	    			$r['NUMRISFILES'] = 0;
	    			$atsFilesKeyed[$row['FILENAME']] = $r;
	    		}
			
    		
	    		/* Get the number of corresponding RIS files */
	    		$qry = "select filename, count(*) as numRISFiles from ("
	    			   . "    select trim(A.atsd_file) as filename, atsd_rid, ris_rid, ris_file from syscdr_atsdir A, syscdr_ris R "
	    			   . "    where R.ris_atsfile = A.atsd_file order by atsd_rid"
	    			   . ") group by filename";
	    		$tmp = $this->doDatabaseWork($qry);
	    		foreach ($tmp as $row) {
	    			if (array_key_exists($row['FILENAME'], $atsFilesKeyed)){
	    				$atsFilesKeyed[$row['FILENAME']]['NUMRISFILES'] = $row['NUMRISFILES'];
	    			}
	    		}	
			}
    		$result['ATSFILES'] = array_values($atsFilesKeyed);
        }
		
		return $result;
	}	

	
	/*
	 * Obtain information about a given ATS filename on the current ER node.
	 * This function is exposed as a SOAP service.
	 */	
	public function getATSFileDetailInfo($ats_filename){
		$result = array();		
			
		/*
		 * Hot fix for defect 195834. The if statement below prevents an error message that is display when opening the node details page.
		 */
		$nodeInfo = $this->getNodeInfo();
		if($nodeInfo['ATSDIR'] != "/dev/null" && $nodeInfo['ATSDIR'] != "NUL" &&
		$nodeInfo['RISDIR'] != "/dev/null" && $nodeInfo['RISDIR'] != "NUL")
		{
			/* Get information about the ATS file */		
			$qry = "select ats_rid, ats_file, ats_source, ats_committime, ats_target, ats_receivetime, ats_line1, "
				   . "ats_line2, ats_line3, ats_line4, ats_line5, ats_line6, ats_line7, ats_line8, ats_line9, "
				   . "ats_line10, atsd_ctime as creationTime, atsd_size as size from syscdr_ats a, syscdr_atsdir b "
				   . "where b.atsd_file = a.ats_file AND ats_file = '". $ats_filename . "'";
			$result = $this->mergeArrays($result, $this->doDatabaseWork($qry));
					
			/* Get information about related RIS files */		
			$qry = "select ris_file from syscdr_ris where ris_atsfile = '" . $ats_filename . "'";
			$tmp = $this->doDatabaseWork($qry);
			$ris_files = array();
			foreach ($tmp as $row) {
				$ris_files[] = $row['RIS_FILE'];
			}				
			$result['RIS_FILES'] = implode(", ", $ris_files);
		}
		return $result;
	}
 
	
	/*
	 * Obtain information about a given RIS filename on the current ER node.
	 * This function is exposed as a SOAP service.
	 */		
	public function getRISFilesInfo(){
		$result = array();
		
		$er_component_states = $this->getNodeERState();
        $result['STATES'] = $er_component_states;
        
        // The rest of the apply details data is only applicable 
        // if the ER state is not 'Uninitialized' or 'Shut Down'
        if (! (trim($er_component_states['ERSTATE']) == "Uninitialized" ||
            trim($er_component_states['ERSTATE']) == "Shut Down")) 
        {
    		/* Get total number of RIS files */
    		$result = $this->mergeArrays($result, $this->getTotalNumberOfRISFiles());
        	
        	/* Get info about each RIS file */
    		$qry = "select risd_rid as id, trim(risd_file) as filename, risd_ctime as creationTime, "
    			   . "risd_size as size from syscdr_risdir order by risd_rid";
    		$tmp = $this->doDatabaseWork($qry);
    		$risFilesKeyed = array(); // key is the filename
    		foreach ($tmp as $row) {
    			$r = array();
    			$r['ID'] = $row['ID'];
    			$r['FILENAME'] = $row['FILENAME'];
    			$r['CREATIONTIME'] = $row['CREATIONTIME'];
    			$r['SIZE'] = $row['SIZE'];
    			$r['NUMATSFILES'] = 0;
    			$r['ATSFILE'] = "";
    			$risFilesKeyed[$row['FILENAME']] = $r;			
    		}		
    		
    		/* Get the number of corresponding RIS files - there should be at most one */
    		$qry = "select filename, count(*) as numATSFiles, ats_file from ("
    			   . "    select trim(risd_file) as filename, ats_file from syscdr_ats A, syscdr_risdir R "
    			   . "    where R.risd_file = A.ats_risfile order by R.risd_file"
    			   . ") group by filename, ats_file";
    		$tmp = $this->doDatabaseWork($qry);
    		foreach ($tmp as $row) {
    			if (array_key_exists($row['FILENAME'], $risFilesKeyed)){
    				$risFilesKeyed[$row['FILENAME']]['NUMATSFILES'] = $row['NUMATSFILES'];
    				
    				if ($row['NUMATSFILES'] == 1)
    					$risFilesKeyed[$row['FILENAME']]['ATSFILE'] = $row['ATS_FILE'];
    			}
    		}			
    		$result['RISFILES'] = array_values($risFilesKeyed);
        }
		
		return $result;		
	}
	
	
	/*
	 * Obtain information about a given RIS filename on the current ER node.
	 * This function is exposed as a SOAP service.
	 */		
	public function getRISFileDetailInfo($ris_filename){
		$result = array();
				
		/* Get information about the RIS file */		
		$qry = "select ris_rid, ris_file, ris_source, ris_committime, ris_target, ris_receivetime, ris_line1, "
			   . "ris_line2, ris_line3, ris_line4, ris_line5, ris_line6, ris_line7, ris_line8, ris_line9, "
			   . "ris_line10, risd_ctime as creationTime, risd_size as size from syscdr_ris a, syscdr_risdir b "
			   . "where b.risd_file = a.ris_file AND ris_file = ?";
		$result = $this->mergeArrays($result, $this->doPreparedDatabaseWork($qry, array($ris_filename)));
		
		/* Get information about related ATS files - there should be at most one */		
		$qry = "select first 1 ats_file as ats_files from syscdr_ats where ats_risfile = ?";
		$tmp = $this->doPreparedDatabaseWork($qry, array($ris_filename));
		if (count($tmp) == 0)
			$result['ATS_FILES'] = "";
		else
			$result = $this->mergeArrays($result, $tmp);
		return $result;
	}

	
	/*
	 * Obtain information about the disk usage of an ER node.
	 * This function is exposed as a SOAP service.
	 */
    public function getDiskUsageInfo(){
    	$result = array();
    	
        $result['STATES'] = $this->getNodeERState();
    	
    	/* Get information about all the queue data spaces */
        $qDataSpaces = $this->getSpaceNames('CDR_QDATA_SBSPACE');
    	$result['QDATA']['SPACES'] = $this->getSpaceInfo($qDataSpaces);  	
    	$tmp = $this->summateSpaceInfo($result['QDATA']['SPACES']);    	
    	$result['QDATA'] = array_merge($result['QDATA'], $tmp);    	
    	
    	
    	/* Get information about all the queue header spaces */
    	$qhdrSpaces = $this->getSpaceNames('ROOTNAME');  // always exists
    	$tmp = $this->getSpaceNames('CDR_QHDR_DBSPACE'); // may be empty
    	if (strlen($tmp) > 0) {
    		$qhdrSpaces .= "," . $tmp;
    	}
    	$result['QHDR']['SPACES'] = $this->getSpaceInfo($qhdrSpaces);
		$tmp = $this->summateSpaceInfo($result['QHDR']['SPACES']);
		$result['QHDR'] = array_merge($result['QHDR'], $tmp);  
    	
		
		/* Get information about the grouper's paging spaces */
		$grouperSpaces = "";
		$sbspaceTemp = $this->getSpaceNames('SBSPACETEMP'); // may be empty
		if (strlen($sbspaceTemp) > 0) {
			$grouperSpaces = $sbspaceTemp;
		}
		
		$sbspaceName = $this->getSpaceNames('SBSPACENAME'); // may be empty
		if (strlen($sbspaceName) > 0) {
			if (strlen($grouperSpaces) > 0)
				$grouperSpaces .= "," . $sbspaceName;
			else
				$grouperSpaces = $sbspaceName;
		}
		
		// as a third option the grouper will spool to the queue data disk space
		$qdata = array();
		$qdata['NAME'] = 'Row Data Sbspace';			
		$qdata['SIZE'] = $result['QDATA']['TOTALSIZE'];
		$qdata['NCHUNKS'] = $result['QDATA']['TOTALNCHUNKS'];
		$qdata['STATUS'] = '-';
		$qdata['FREE_SIZE'] = $result['QDATA']['TOTALFREE'];
		$qdata['USED'] = $result['QDATA']['TOTALUSED'];
		$qdata['LOGGING'] = '-';
		
		$result['GROUPER']['SPACES'] = $this->getSpaceInfo($grouperSpaces);
		$result['GROUPER']['SPACES'][] = $qdata;
		$tmp = $this->summateSpaceInfo($result['GROUPER']['SPACES']);			
		$result['GROUPER'] = array_merge($result['GROUPER'], $tmp);			
		
    	return $result;
    }
	
    /**
     * Obtain the detailed Capture information about the current ER node.
     * This function is explosed as a service via SOAP.
     * Maps to the Node Summary -> Capture Details page
     */
    function getCaptureDetails ( ) 
        {
        $result = array ( );
        
        $result [ 'TIMESTAMP' ] = $this->getTimestamp ( );
        
        $er_component_states = $this->getNodeERState();
        $result['STATES'] = $er_component_states;
        
        // The rest of the apply details data is only applicable 
        // if the ER state is not 'Uninitialized' or 'Shut Down'
        if (! (trim($er_component_states['ERSTATE']) == "Uninitialized" ||
            trim($er_component_states['ERSTATE']) == "Shut Down")) 
        {
            $query = "SELECT TRIM ( ddr_state ) as ddr_state "
               . "     , ddr_snoopy_loguniq  "
               . "     , ddr_snoopy_logpos   "
               . "     , ddr_replay_loguniq  "
               . "     , ddr_replay_logpos   "
               . "     , ddr_curr_loguniq    "
               . "     , ddr_curr_logpos     "
               . "     , ddr_logsnoop_cached "
               . "     , ddr_logsnoop_disk   "
               . "     , ddr_logs_tossed     "
               . "     , ddr_logs_ignored    "
               . "     , ddr_dlog_requests   "
               . "     , ddr_total_logspace  "
               . "     , ddr_logpage2wrap    "
               . "     , ddr_logpage2block   "
               . "     , ddr_logneeds        "
               . "     , ddr_logcatchup      "
               . "  FROM syscdr_ddr ";
               
            $result [ 'CAPTURE' ] = $this->doDatabaseWork ( $query, 'sysmaster' );
        
            $query = "SELECT TRIM ( cf_name ) as cf_name "
		           . "     , TRIM ( cf_effective ) as cf_effective "
		           . "  FROM sysconfig "
		           . " WHERE cf_name IN ( 'CDR_MAX_DYNAMIC_LOGS', 'DYNAMIC_LOGS' ) ";
  
			$result [ 'CONFIG' ] = $this->doDatabaseWork ( $query, 'sysmaster' );
        }
	
        return $result;
        }

	/**
	 * Get ER Configuration parameter information
	 **/
	public function getERConfigParams($serverGroup = "")
	{
		$result = array();
		$tempResult = array();
		
		$er_state = $this->getNodeERState();
        $result['STATES'] = $er_state;
        
        $result['CLUSTER'] = $this->isNodeInCluster();
        
        // Get Config parameter values
        if(!(trim($er_state['ERSTATE']) == "Uninitialized" || trim($er_state['ERSTATE']) == "Shut Down"))
        { 
	        $qry = "select cf_name, cf_effective from sysconfig "
	        		. "where cf_name in ("
	        		. "'CDR_QDATA_SBSPACE', 'SBSPACETEMP', 'SBSPACENAME', "
	        		. "'LTXEHWM', 'LTXHWM', 'DYNAMIC_LOGS', 'CDR_MAX_DYNAMIC_LOGS', "
	        		. "'CDR_QUEUEMEM', 'CDR_DBSPACE', 'CDR_SERIAL', 'CDR_EVALTHREADS', 'CDR_NIFCOMPRESS', "
	        		. "'CDR_SUPPRESS_ATSRISWARN', 'CDR_DSLOCKWAIT', 'ENCRYPT_CDR', 'ENCRYPT_CIPHERS', " 
	        		. "'ENCRYPT_MAC', 'ENCRYPT_MACFILE', 'ENCRYPT_SWITCH', 'CDR_APPLY', 'CDR_DELAY_PURGE_DTC', "
	        		. "'CDR_LOG_LAG_ACTION','CDR_LOG_STAGING_DIR')";
			$result['CONFIG'] = $this->doDatabaseWork($qry, "sysmaster");
		}

		// Now get the ATS and RIS dirs
		$tempResult = $this->getNodeInfo();
        
        return (array_merge($result, $tempResult));
	}
	
	/**
	 *  Split this from getERConfigParams() to minimize connections to db server, since this info 
	 *  is needed atleast at 2 different places.
	 **/
	public function getATSRISdir()
	{
		$result = array();
		$tempResult = array();
    	$res = array();
    	
    	// Determine OS of IDS server
		$qry = "SELECT os_name osname from sysmachineinfo";
    	$res = $this->doDatabaseWork($qry);
    	
    	if ($res[0]['OSNAME'] == self::WINDOWS_OS){
    		$isWindows = true;
    		$fileSeperator = self::WINDOWS_FILE_SEPARATOR;
	    } else {
	    	$isWindows = false;
	    	$fileSeperator = self::FILE_SEPARATOR;
    	}
		
		$qry = "select atsdir, risdir from syscdrserver where connstate = 'L'";
		$tempResult = $this->doDatabaseWork($qry, "sysmaster");
		
		if (empty($tempResult[0]['ATSDIR']))
			$result['ATS_DIR'] = $this->defaultAtsRisDirs($isWindows);
		else
			$result['ATS_DIR'] = $tempResult[0]['ATSDIR'] . $fileSeperator;
			
		if (empty($tempResult[0]['RISDIR']))
			$result['RIS_DIR'] = $this->defaultAtsRisDirs($isWindows);
		else
			$result['RIS_DIR'] = $tempResult[0]['RISDIR'] . $fileSeperator;
        
        return $result;
	}
	
	/**
	 * Update ER configuration parameters
	 *
	 * @param updateTasks a list of task with the information for updating
	 **/
	public function updateERConfigParams($updateTasks)
	{
		$updateTasks = unserialize($updateTasks);
		
		for ($i = 0; $i < count($updateTasks); $i++)
		{
			$updateTasks[$i] = $this->executeAdminTask($updateTasks[$i]);
		}
		
		return $updateTasks;
	}
	
    public function getNodeErrorDetails()
	{
		$result = array();
		
		$er_state = $this->getNodeERState();
        
        if(!(trim($er_state['ERSTATE']) == "Uninitialized" || trim($er_state['ERSTATE']) == "Shut Down"))
        { 
	        $qry = "SELECT errornum, errorserv, errorseqnum, errortime, "
	        	 . "CASE WHEN (sendserv is NULL) THEN ' ' ELSE trim(sendserv) END as sendserv, "
	        	 . "reviewed, errorstmnt from syscdrerror";
			$result = $this->doDatabaseWork($qry);
        }
		$result['STATES'] = $er_state;
		return $result;
	}
    /**
     * Obtain the detailed Network information about the current ER node.
     * This function is explosed as a service via SOAP.
     * Maps to the Node Summary -> Network Details page
     */
    function getNetworkDetails ( )
        {
        $result = array ( );
        
        $result [ 'TIMESTAMP' ] = $this->getTimestamp ( );
        $er_component_states = $this->getNodeERState();
        $result['STATES'] = $er_component_states;
        
        // The rest of the apply details data is only applicable 
        // if the ER state is not 'Uninitialized' or 'Shut Down'
        if (! (trim($er_component_states['ERSTATE']) == "Uninitialized" ||
            trim($er_component_states['ERSTATE']) == "Shut Down")) 
        {
        
			if (  Feature::isAvailable( Feature::PANTHER, $this->idsadmin) ) {
        		 $stampCols =  "     , nif_trgsend_stamp   "
               				 . "     , nif_acksend_stamp   "
               				 . "     , nif_ctrlsend_stamp  "
               				 . "     , nif_syncsend_stamp  ";
            } else {  				 
				 $stampCols =   "     , nif_trgsend_stamp1  "
               				  . "     , nif_trgsend_stamp2  "
               				  . "     , nif_acksend_stamp1  "
               				  . "     , nif_acksend_stamp2  "
					          . "     , nif_ctrlsend_stamp1 "
					          . "     , nif_ctrlsend_stamp2 "
					          . "     , nif_syncsend_stamp1 "
					          . "     , nif_syncsend_stamp2 ";				
        	}
            $query = "SELECT nif_connid " 
               . "     , TRIM ( nif_connname  ) AS nif_conname "
               . "     , TRIM ( nif_state     ) AS nif_state "
               . "     , TRIM ( nif_connstate ) AS nif_connstate "
               . "     , nif_version         "
               . "     , nif_msgsent         "
               . "     , nif_bytessent       "
               . "     , nif_msgrcv          "
               . "     , nif_bytesrcv        "
               . "     , nif_compress        "
               . "     , nif_sentblockcnt    "
               . "     , nif_rcvblockcnt     "
               . $stampCols
               . "     , nif_starttime       "
               . "     , nif_lastsend        "
               . "  FROM syscdr_nif          "
               . " ORDER BY nif_conname      ";
        
            $result [ 'NIF' ] = $this->doDatabaseWork ( $query, 'sysmaster' );
        }
               
        return $result;
    }   
    
    /**
     * Get the current node's servername
     */ 
    public function getCurrentERNodeName($conn=null)
    {
        $qry = "select servername from sysmaster:syscdrserver where connstate = 'L'";
        $tmp = $this->doDatabaseWork($qry,"sysmaster",$conn);
        if(isset($tmp[0]))
        {
            return trim($tmp[0]['SERVERNAME']);
        } 
        else 
        {
            return "";
        }
    }
    
    /**
     * Get the OAT ER thresholds from the ph_threshold table
     */
    public function getThresholds ( $returnDefaults = false )
    {
        $result = array();
        
        $query = "select id, name, task_name, value, value_type, description " .
                 "from ph_threshold where name like 'OAT_ER%' " .
                 "order by id";
        $thresholds = $this->doDatabaseWork ($query, self::SYSADMIN);
        
        if ( ( !isset ( $thresholds ) || count ( $thresholds ) == 0 ) && $returnDefaults == true )
	        {
            require_once ("defaultThresholds.php");
	        $thresholds = $defaultThresholds;
	        }

        // For the UI, we want to return the translated description, not the English
        // one inserted into sysadmin:ph_threshold
        for($i = 0; $i < count($thresholds); $i++)
        {
            $thresholds[$i]['DESCRIPTION'] = $this->idsadmin->lang($thresholds[$i]['NAME']);
        }
	        
        $result['THRESHOLDS'] = $thresholds;
        return $result;
    }

    /**
     * Insert the default OAT ER thresholds into the ph_threshold table.
     * Returns a result set containing the new thresholds added.
     */
    function insertDefaultThresholds ()
    {
        require_once ("defaultThresholds.php");
        
        $insert = "INSERT into ph_threshold " .
                " (name, task_name, value, value_type, description) " .
                " VALUES " .
                " (:name, :task_name, :value, :value_type, :desc)";
                
        //$db = $this->idsadmin->get_database(self::SYSADMIN);
        require_once(ROOT_PATH."lib/database.php");
	$db = new database($this->idsadmin,self::SYSADMIN,$this->idsadmin->phpsession->get_dblcname());

        // For each threshold, insert the default value
        foreach ($defaultThresholds as $threshold )
        {
            // Prepare the insert statement
            $stmt = $db->prepare($insert);
            $err = $db->errorInfo();
            if (isset($err[1]) && $err[1] != 0)
            {
                $err = "{$this->idsadmin->lang("Error")}: {$err[2]} - {$err[1]}";
                trigger_error($err_str,E_USER_ERROR);    
            }
            
            // Bind parameters & execute insert
            $stmt->bindParam(':name', $threshold['NAME']);
            $stmt->bindParam(':task_name', $threshold['TASK_NAME']);
            $stmt->bindParam(':value', $threshold['VALUE']);
            $stmt->bindParam(':value_type', $threshold['VALUE_TYPE']);
            $stmt->bindParam(':desc', $threshold['DESCRIPTION']);
            $stmt->execute();
            
            // Check for errors
            $err = $db->errorInfo();
            if (isset($err[1]) && $err[1] != 0)
            {
                $err_str = "{$this->idsadmin->lang("Error")}: {$err[2]} - {$err[1]}";
                trigger_error($err_str,E_USER_ERROR); 
            }
        }

        // Return the thresholds
        return $this->getThresholds(false);
    }
    
    /**
     * Update the OAT ER threshold values for all servers in the ER domain
     * 
     * @param $newThreshols_s serailized array collection that represents the 
     *        new threshold values
     **/
    function updateThresholds ($newThresholds_s)
    {
        $newThresholds = unserialize($newThresholds_s);
        $success = array();
        $fail = array();    
    
        // Update thresholds on current server
        //$db = $this->idsadmin->get_database(self::SYSADMIN);
        require_once(ROOT_PATH."lib/database.php");
	$db = new database($this->idsadmin,self::SYSADMIN,$this->idsadmin->phpsession->get_dblcname());
	$ret = $this->doThresholdUpdateDatabaseWork ($newThresholds, $db);
        if ($ret[0] == 0)
        {
            $success[] = array("SERVERNAME" => $this->idsadmin->phpsession->instance->get_servername() . 
                               " (" . $this->idsadmin->phpsession->instance->get_host() . ")",
                               "MSG" => $ret[1]);
        } else {
            $fail[] = array("SERVERNAME" => $this->idsadmin->phpsession->instance->get_servername(). 
                            " (" . $this->idsadmin->phpsession->instance->get_host() . ")",
                            "MSG" => $ret[1]);
        }
      
        // Now we need to update thresholds on other server in the ER domain

        // First get the list of other ER servers in the ER domain

        // The following query will return all of the other servers in the ER domain,
        // as well as the group name for each and the total number of servers in its group.
        // If the number of servers in its group (svrgroupcnt) is > 1, we need
        // to check if the server is a secondary or not before updating threholds
        $query = "select trim(A.dbsvrnm) as servername, A.svrgroup, " 
               . "trim(A.hostname) as hostname, C.svrgroupcnt "
               . "from syssqlhosts A, syscdrserver B, " 
               . "(select svrgroup, count(svrgroup) as svrgroupcnt " 
               . "from syssqlhosts where svrgroup != '' group by svrgroup) C " 
               . "where A.svrgroup = B.servername " 
               . "and A.svrgroup  = C.svrgroup " 
               . "and B.connstate != 'L'";

        $serverlist = $this->doDatabaseWork($query);
        
        foreach ($serverlist as $server)
        {
            try {
                $hostname = $server['HOSTNAME'];
                $servername = $server['SERVERNAME'];
                $db = $this->getPartnerServerConnection($servername, $hostname, self::SYSADMIN);
                if ($db == null)
                {
                    $fail[] = array("SERVERNAME" => "$servername ($hostname)",
                              "MSG" => "{$this->idsadmin->lang("NoConnectionInfo")}");
                    continue;
                }
            } catch(PDOException $e) {
                $fail[] = array("SERVERNAME" => "$servername ($hostname)",
                                "MSG" => "{$this->idsadmin->lang("ConnectionFailed")}:{$e->getMessage()}");
				
				//error_log("OAT ER Updating Thresholds Connection Failed:{$e->getMessage()}");
                continue;
            }
            
            $checkNeeded = ($server['SVRGROUPCNT'] > 1)? true:false;
            $ret = $this->doThresholdUpdateDatabaseWork ($newThresholds, $db, $checkNeeded);
            if ($ret == null)
            {
                // The server was a secondary, so do nothing
            } elseif ($ret[0] == 0) {
                $success[] = array("SERVERNAME" => "$servername ($hostname)", 
                                   "MSG" => $ret[1]);
            } else {
                $fail[] = array("SERVERNAME" => "$servername ($hostname)", 
                                "MSG" => $ret[1]);
            }
        }
        
        // Return results
        $result = array();
        $result['SUCCESS'] = $success;
        $result['FAIL'] = $fail;
        return $result;
    }

	function getWorldView ( $deployUDRs = false, $conn = null )
	{
		/* 
		 * For 12.10 and above, use the SQL Admin API command 'cdr view profile xml' to get the 
		 * ER world view info.  For server versions prior to 12.10, we depend on the 'runcdr'
		 * stored procedure deployed by OAT to get this information.
		 */
		$useAdminAPI = (Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin));
		
		/*
		 * Check if we need to deploy the runcdr stored procedure.
		 */
		if ( !$useAdminAPI && $deployUDRs )
		{
			$this->testAndDeployUDRs ( $conn );
		}
			
		if ($useAdminAPI)
		{
			$query = "EXECUTE FUNCTION admin('cdr view profile xml')";
		} else {
			$query = "EXECUTE PROCEDURE runcdr ( 'view profile --saveResults' )";
		}
		
		// The runcdr procedure should be run with en_US.819, not the user specified locale.
		// The runcdr does not behave well when run with other locales, but since it does
		// not return locale sensitive data, it is ok to always run with en_US.819.
		$rows = $this->doDatabaseWork ( $query, self::SYSADMIN, $conn, null, "en_US.819" );

		// For the runcdr procedure, the results are returned directly by the procedure.
		// For the Admin API, the results will be in the command_history table, so we'll 
		// have to query for the reqsults.
		$result = array();
		if ($useAdminAPI)
		{
			$cmd_num = $rows[0][''];
			$query = "SELECT cmd_ret_msg FROM command_history WHERE cmd_number > {$cmd_num} and cmd_user = {$cmd_num} ORDER BY cmd_number";
			
			$rows = $this->doDatabaseWork ( $query, self::SYSADMIN, $conn, null, "en_US.819" );
		} 
		
		/*
		 * The result set returned by the 'runcdr' procedure is a bit different from
		 * what you'd expect. Instead of an array of (XML) strings, PDO returns
		 * an array of associative arrays:
		 * 
		 * array ( 0 => array ( '' => XML string )
	 	 *       , 1 => array ( '' => XML string ) )
		 * 
		 * The code below repackages the result set into something more readily 
		 * consumed by Flex - the XML data is extracted and placed in the result
		 * array with an 'XML' label. Thus, OAT can treat it pretty much like other
		 * services - iterate over ResultEvent.result, cast the current item to 
		 * ObjectProxy, and retrieve the XML data by referencing the XML field.
		 */
		foreach ( $rows as $key => $value )
		{
			foreach ( $value as $xml )
			{
				$result [ ] = array ( 'XML' => $xml );
			}
		}
		
		return $result;
	}
		
    function getTimestamp ( )
    {
        $query = "SELECT CURRENT YEAR TO SECOND AS timestamp FROM sysdual";
        $result = $this->doDatabaseWork ( $query, 'sysmaster' );
        return $result [ 0 ][ 'TIMESTAMP' ];
    }

	/**************************************************************************
     * Define Server Wizard soap functions
     *************************************************************************/
        
    /**
     * Define Server Wizard, Page 1 Validation service
     * 1. Validate that we can connect to the selected server.
     * 2. Validate that the selected server is not already particpating in ER.
     * 3. Validate that the selected server is not part of a cluster (if server version is < 12.10)
     * 
     * @param $conn_num of the selected server in the connections.db
     * @param $servername of the selected server
     * @return $result['VALID'] a boolean representing whether input is valid for the wizard
     *         $result['ERROR'] error message if not valid
     *         $result['VERSION'] server version of the selected server
     *         $result['GROUPNAME'] group name from the server's sqlhosts
     *         $result['SYNCSERVERS'] array of potential sync servers from the selected
     *                server's sqlhosts
     */
    function defineServerPage1Validation ($conn_num, $servername)
    {
    	// Validate that we can connect to the server
    	try {
    		$db = $this->getServerConnection($conn_num);
        } catch(PDOException $e) {
            return array("VALID" => false,
            			 "ERROR" => "Connection Failed: {$e->getMessage()}");
        }
        
        // Validate that the server does not already participate in ER
        $qry = "select count(*) as count from sysdatabases where name='syscdr'";
        $res = $this->doDatabaseWork($qry, "sysmaster", $db);
        if ($res[0]['COUNT'] >= 1 )
        {
        	// This server already participates in ER
        	return array("VALID" => false, 
                         "ERROR" => "{$this->idsadmin->lang('ER_Already_Active')}");
        }
   
        // If the server version < 12.10, validate that the server is not part of a cluster
        if (!Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin) && $this->isNodeInCluster())
        {
        	// This server is part of a cluster
        	return array("VALID" => false, 
                         "ERROR" => "{$this->idsadmin->lang('Server_in_Cluster')}");
        }
   
        $result = array();
        $result["VALID"] = true;

		// Retrieve the server version of this server
		$qry = "select DBINFO('version','full') as version from systables WHERE tabid = 1";
		$res = $this->doDatabaseWork($qry, "sysmaster", $db);
        $result["VERSION"] = $res[0]['VERSION'];
        
        // Retreive the group name of this server from the syssqlhosts table...
        // We'll need it for Define Server Wizard Page 2
        $qry = "select svrgroup from syssqlhosts where dbsvrnm='$servername'";
        $res = $this->doDatabaseWork($qry, "sysmaster", $db);
        $groupname = trim($res[0]['SVRGROUP']);
               
        if (!empty($groupname))
        {
        	// Validate the group name
        	$qry = "select count(*) as count from syssqlhosts where nettype='group' and dbsvrnm='$groupname'";
        	$res = $this->doDatabaseWork($qry, "sysmaster", $db);
        	if ($res[0]['COUNT'] < 1 )
        	{
        		// The group name is not valid
        		return array("VALID" => false, 
                        	 "ERROR" => $this->idsadmin->lang('Invalid_Group_Name', array($groupname,$servername)));
        	}
        }
        
        $result["GROUPNAME"] = $groupname;
        
        // While we're here, we might as well also get information about
        // available sync servers since, we'll need that right away on page 2.
        $qry = "select unique dbsvrnm as group from syssqlhosts where nettype='group' and options like '%i=%' and dbsvrnm != '$groupname'";
        $res = $this->doDatabaseWork($qry, "sysmaster", $db);
        $result["SYNCSERVERS"] = $res;
            
    	return $result;
    }
    
    /**
     * Define Server Wizard, Page 2 Validation service
     * 1. Validate SQLHOSTS on the selected server 
     * 2. Validate SQLHOSTS on the sync server
     * 
     * @param $conn_num of the selected server in the connections.db
     * @param $servername of the selected server
     * @param $grpname of the selected server
     * @param $syncserver the group name of the sync server, will be null if the user is
     *        creating a new domain 
     * @param $nodeType type for new node ("root", "nonroot", or "leaf")
     * @return $result['VALID'] a boolean representing whether SQLHOSTS setup is valid
     *         $result['ERROR'] error message if not valid
     */
    function defineServerPage2Validation ($conn_num, $servername, $grpname, $syncserver, $nodeType)
    {
		// Get connection to the server
		try {
			$db = $this->getServerConnection($conn_num);
		} catch(PDOException $e) {
			return array("VALID" => false,
						 "ERROR" => "{$this->idsadmin->lang("ConnectionFailed")}: {$e->getMessage()}");
		}

		if(empty($syncserver))
		{
			$case = 'NewDomain';
		} else {
			$case = ($nodeType == "leaf")?'ExistingDomainLeaf':'ExistingDomainRootNonRoot';
		}

		require_once "SQLHOSTSvalidation.php";
		$ret = CheckSQLHOSTS($this->idsadmin,$db,$case,$servername,$grpname,$syncserver);

		/*
		 * Return Value of CheckSQLHOSTS function:
		 * $ret = array ("VALID" => true/false
		 *		"ERROR" => array(1=>"message 1....",2=>"message 2...",3=>"message 3..." etc...)
		 *		)
		 */

		$result = array();
		$result["VALID"] = $ret['VALID'];
		$result["ERROR"] = $ret['ERROR'];
	
		// If page 2 validation succeeded, let's get the information about ER space
		// configuration parameters and available spaces since we'll need that right
		// away on page 3.
		if ($result["VALID"])
		{
			$qry = "select cf_effective from syscfgtab "
				 . "where cf_name='CDR_QDATA_SBSPACE'";
			$tmpRes = $this->doDatabaseWork($qry, "sysmaster", $db);
			if (count($tmpRes) == 1)
			{
    			$result["CDR_QDATA_SBSPACE"] = $tmpRes[0]['CF_EFFECTIVE'];
			}   		
   	     	
			$qry = "select cf_effective from syscfgtab "
				 . "where cf_name='CDR_DBSPACE'";
			$tmpRes = $this->doDatabaseWork($qry, "sysmaster", $db);
			if (count($tmpRes) == 1)
			{
    			$result["CDR_DBSPACE"] = $tmpRes[0]['CF_EFFECTIVE'];
			}

			$result['SBSPACES'] = $this->getSbspaceInfo($db);			
			$result['DBSPACES'] = $this->getDbspaceInfo($db);
		}

		return $result;
    } 

	/**
     * Execute the create space commands for the Define Server wizard
     * 
     * @param conn_num  indicates which server to connect to 
     * @param $spaceTasks a list of tasks with the info for creating the new spaces
     */
    public function defineServerWizard_createSpace($conn_num, $spaceTasks) 
    {
    	$spaceTasks = unserialize($spaceTasks);
		
		for ($i = 0; $i < count($spaceTasks); $i++)
		{
			$spaceTasks[$i] = $this->executeAdminTask($spaceTasks[$i], $conn_num);
		}
		
		return $spaceTasks;
	}
    
	/**
     * Execute the modify onconfig commands for the Define Server wizard
     * 
     * @param conn_num  indicates which server to connect to  
     * @param $configTasks
     */
    public function defineServerWizard_modifyOnconfig($conn_num, $configTasks) 
    {
   		$configTasks = unserialize($configTasks);
		
		for ($i = 0; $i < count($configTasks); $i++)
		{
			$configTasks[$i] = $this->executeAdminTask($configTasks[$i], $conn_num);
		}
		
		return $configTasks;
    }
    
	/**
     * Execute the 'cdr define server' command for the Define Server wizard
     * 
     * @param $conn_num  indicates which server to connect to 
     * @param $group_name ER group name of the new server
     * @param $sync_server group name of the sync server 
     *        (null if there is no sync server)
     * @param $node_type 'root', 'nonroot', or 'leaf'
     * @param $ats_dir ATS directory (empty string if there is no ATS dir)
     * @param $ats_dir ATS directory (empty string if there is no ATS dir)
     * @param $idle_timeout timeout (0 if idle timeout should be turned off)
     */
    public function defineServerWizard_defineServer($conn_num, $group_name, 
    	$sync_server = null, $node_type = 'root', 
    	$ats_dir = "", $ris_dir = "", $ats_ris_format, $idle_timeout = 0)
    {
    	$command = "'cdr define server', ";
    	$parameters = "";
    	
    	if ($ats_dir != "")
    	{
    		$parameters .= "'--ats={$ats_dir}', ";
    	}
    	if ($ris_dir != "")
    	{
    		$parameters .= "'--ris={$ris_dir}', ";
    	}
		if ($ats_ris_format != "")
    	{
    		$parameters .= "'--atsrisformat={$ats_ris_format}', ";
    	}
    	if ($idle_timeout != 0)
    	{
    		$parameters .= "'--idle={$idle_timeout}', ";
    	}
    	if (strcasecmp($node_type, "root") != 0)
    	{
    		$parameters .= "'--{$node_type}', ";
    	}
    	
    	$syncStr = ($sync_server == null)? "":"--sync={$sync_server}";
    	$parameters .= "'{$syncStr} --init {$group_name}'";
    	
    	$task = array("COMMAND" => $command,
    				  "PARAMETERS" => $parameters);

    	$result = array();
    	$result['TASK'] = $this->executeSQLAdminAPICommand($task, $conn_num);
    	
    	return $result;
	}
	
	/**
	 * This function modifies a node using the "cdr modify server" command.
	 * @return command results
	 * @param object $idletimeout[optional] 
	 * @param object $mode[optional]
	 * @param object $atsdir[optional]
	 * @param object $risdir[optional]
	 * @param object $atsrisformat[optional]
	 * @param object $server_group[optional] the server we are modifying
	 */
	public function modifyServer($idletimeout = "", $mode = "", $atsdir = "", $risdir = "", $atsrisformat= "text", $server_group = "")
	{
		$command = "'cdr modify server', ";
    	$parameters = "";
    	$groupname = "";
		
		if ($idletimeout != null && $idletimeout != "")
    	{
    		$parameters .= "'--idle={$idletimeout}', ";
    	}
		if(!empty($mode))
		{
			$parameters .= "'--mode={$mode}', ";
		}
    	if ($atsdir != null && $atsdir != "")
    	{
    		$parameters .= "'--ats={$atsdir}', ";
    	}
		if ($risdir != null && $risdir != "")
    	{
    		$parameters .= "'--ris={$risdir}', ";
    	}
		if (!empty($atsrisformat))
    	{
    		$parameters .= "'--atsrisformat={$atsrisformat}', ";
    	}
		
		$parameters .= "'{$server_group}'";	
		
		
    	$task = array("COMMAND" => $command,
    				  "PARAMETERS" => $parameters);
    
		return $this->executeSQLAdminAPICommand($task);
	}
	
	/**
	 * This function is used by the modify server wizard. It obtains the current values of the attributes
	 * of the specified node
	 * @return returns an array containing teh attributes values
	 * @param object $groupname the node that we want to obtain its attributes
	 */
	public function getNodeInfo($groupname = null)
	{
		$sql = "SELECT idletimeout, atsdir, risdir "
			. ",CASE WHEN bitval(flags,'0x00000880') = 1 THEN 'both' "
			. "      WHEN bitval(flags,'0x00000080') = 1 THEN 'xml' "
			. "      WHEN bitval(flags,'0x00000800') = 1 THEN 'text' "
			. "      ELSE 'text' "
			. " END as atsrisformat "
			. " FROM syscdr:servdef s,  syscdr:hostdef h"
 			. " WHERE s.servid = h.servid ";
			
		//If the the node is not specified, use the current node we are connected to.
		if($groupname == null)
		{
			$sql .= " AND h.groupname in ( select servername from sysmaster:syscdrserver where connstate = 'L' )";
		}
		else
		{
		    $sql .= " AND h.groupname = '" . $groupname . "'";
		}
			
		$result = $this->doDatabaseWork ( $sql, 'syscdr' );
		return $result[0];
	}
	
	public function getOSInfo() 
	{
		$sql = "SELECT os_name as osname, env_value as informixdir from sysmachineinfo, sysenv WHERE"
				. " env_name = 'INFORMIXDIR'";
    	$res = $this->doDatabaseWork($sql);
		return $res[0];
	}
	
	/**
	 * performReplicateAction --
	 * This function executes the replicate/replicate set start, stop, suspend and resume commands
	 * 
	 * @param action = 'start', 'stop', 'suspend', or 'resume' (required)
	 * @param object = 'replicate' or 'replicateset' (required)
	 * @param name = replicate or replicate set name (required)
	 * @param server_group_list = list of ER groups, 
	 *        empty string if the action should be perform on all participants
	 * @param sync_data_source = ER group that should be used as the sync data source, 
	 *        null if there is no sync data source.  This parameter is optional and 
	 *        applies to 'start' action only.
	 * @param sync_option = 'delete', 'keep', or 'merge', this specifies how to handle 
	 *        the extra rows found on the target servers (command line --extratargetrows option).
	 *        This parameter is optional and only applies to 'start' action when there is 
	 *        a sync_data_source specified. 
	 * @return the result of the command will be stored in the $result array as follows:
	 * 		$result['SUCCESS'] --> true/false, based on result of Admin API command execution 
	 * 		$result['RESULT_MESSAGE'] --> success or failure message
	 **/
	public function performReplicateAction($action,$object,$name,$server_group_list="",$sync_data_source=null,$sync_option=null)
	{
		// Make sure we have a valid $action and $object parameters and non-empty $name
		$result = array();
		if ($name == "" || !($action == "start" || $action == "stop" || $action == "suspend" || $action == "resume") 
		    || !($object == "replicate" || $object=="replicateset"))
		{
			$result['SUCCESS'] = false;
			$result['RESULT_MESSAGE'] = "Invalid cdr replicate or replicateset action.";
			return $result;
		}
		
		// Setup up SQL Admin API command and parameter values
		$command = "'cdr {$action} ${object}',";
		$parameters = "$name";
		if ($server_group_list != "")
		{
			$parameters .= " {$server_group_list}";
		}
		
		// If it is a cdr start action, add the sync options if specified
		if ($action == "start" && !is_null($sync_data_source))
		{
			$parameters .= " --syncdatasource={$sync_data_source}";
			if (!is_null($sync_option))
			{
				if (!($sync_option == "delete" || $sync_option=="keep" || $sync_option=="merge"))
				{
					$result['SUCCESS'] = false;
					$result['RESULT_MESSAGE'] = "Invalid --extratargetrows option for cdr start replicate action.";
					return $task;
				}
				$parameters .= " --extratargetrows={$sync_option}";
			}
		}
		
		/* 
		 * The ADMIN function takes up to 6 LVARCHAR arguments (in addition to the "COMMAND") 
		 * each of 2048 bytes max. If our parameters string has exceeded 2048, split it up into
		 * up to 6 different arguments. 
		 */
		if (strlen($parameters) > self::LVARCHAR_LENGTH)
		{
			// Our $parameters string exceeded the length allowed for a single parameter,
			// so we need to split it up.
			
			// Split parameters into roughly 2048-length pieces
			$split_parameters = "";
			$unsplit_parameters = trim($parameters);
			$param_count = 0;
			while (strlen($unsplit_parameters) > self::LVARCHAR_LENGTH)
			{
				$split_position = strrpos(substr($unsplit_parameters,0,self::LVARCHAR_LENGTH),' ');
				if ($split_position <= 0)
				{
					// If we can't split, the next parameter is too large to fit in a single argument.
					// Set parameter count to be too big, to trigger the error after we break.
					$param_count = self::MAX_NUM_ADMIN_PARAMS + 1;
					break;
				}
				$split_parameters .= "'" . substr($unsplit_parameters,0,$split_position) . "', "; 
				$unsplit_parameters = trim(substr($unsplit_parameters,$split_position));
				$param_count++;
			}
			
			// Add the remaining parameters to the $split_paramters string
			$split_parameters .= "'" . $unsplit_parameters . "' ";
			$param_count++;
			
			// Check to make sure we have not exceeded the max number of parameters
			if ($param_count > self::MAX_NUM_ADMIN_PARAMS)
			{
				$task["SUCCESS"] = false;
				$taks["RETURN_CODE"] = self::ERROR;
				$task["RESULT_MESSAGE"] = $this->idsadmin->lang("ExceededAdminAPI");
				return $task;
			}
			
			// Set our $parameters variable to our newly create $split_parameters string
			$parameters = $split_parameters;
			
		} else {
			// Even if we don't need to split the parameters across multiple arguments, 
			// we still need to make sure that our single paramater to the SQL Admin API 
			// command is deliminated by single quotes
			$parameters = "'" . $parameters . "'";
		}

		
		// Execute the SQL Admin API Command
		$task = array("COMMAND" => $command,
    				  "PARAMETERS" => $parameters);
    	$result = $this->executeSQLAdminAPICommand($task);

    	
    	if (!$result['SUCCESS'] && !isset($result['RETURN_CODE']))
    	{
    		$result['RETURN_CODE'] = self::ERROR;
    	}
    	
    	return $result;
	}
	
	/**
	 * This function resets the failed transaction count for replication servers that are using a Connection Manager SLA with the FAILURE or LATENCY policies.
	 * @param object $replicate[optional] replicate name
	 * @param object $replicateSet[optional] replicate set name
	 */
	public function resetQOD ($replName = null, $replType = 'replicate')
	{
		$task = array ( );
		$command = "'cdr reset qod',";
		$parameters = "";

		if($replType == 'replicate')
		{
			$parameters .= "'--repl={$replName}'";
		}
		else  // if replset
		{
			$parameters .= "'--replset={$replName}'";
		}
		
		$task = array("COMMAND" => $command,
    				  "PARAMETERS" => $parameters);
    
		$task = $this->executeSQLAdminAPICommand($task);

		return $task;
	}
	
	/**
	 * This functions starts QOD. It defines QOD in case QOD wasn't defined.
	 */
	public function startStopQOD ($define, $start = true, $group)
	{
		$task = array ( );
		$server = $this->getServerNameOfGroup($group);
		if($define)//if not defined define it
		{
			if($start)//define and start
			{
				$command = "'cdr define qod',";
				$parameters .= " '--connect={$server}'";
				$parameters = " '--start'";
				$task = array("COMMAND" => $command,
	    				  "PARAMETERS" => $parameters);
				$task = $this->executeSQLAdminAPICommand($task);
			}
			else //define without starting
			{
				$command = "'cdr define qod',";
				$parameters = " '--connect={$server}'";
				$task = array("COMMAND" => $command,
	    				  "PARAMETERS" => $parameters);
				$task = $this->executeSQLAdminAPICommand($task);
			}
		}
		else //if defined then just start or stop QOD
		{
			$command = ($start) ? "'cdr start qod'," : "'cdr stop qod',";
			$parameters = " '--connect={$server}'";
			$task = array("COMMAND" => $command,
	    				  "PARAMETERS" => $parameters);
			$task = $this->executeSQLAdminAPICommand($task);
		}
		
		return $task;
	}

	/**
	 * performCheckSyncAction --
	 * This function executes the replicate/replicate set check and sync commands
	 * 
	 * @param action = 'check' or 'sync' (required)
	 * @param object = 'replicate' or 'replicateset' (required)
	 * @param name = replicate or replicate set name (required)
	 * @param job_name = job name for the check/sync job (required)
	 * @param ref_data_source = ER group that should be used as the reference or 
	 *        master data source 
	 * @param target_servers_list = list of ER groups, 
	 *        empty string if the action should be perform on all targets
	 * @param repair = true/false.  Applies to check only.  Specifies whether a repair 
	 *        should be done as part of the check.
	 * @param extra_target_rows = 'delete', 'keep', or 'merge', this specifies how to handle 
	 *        the extra rows found on the target servers (command line --extratargetrows option).
	 * @param fire_trigger = 'off', 'on', or 'follow', this specifies how to handle 
	 *        handle triggers at the target servers while synchronizing the data 
	 *        (command line --firetrigger option).
	 * @param mem_adjust = Size of the send queue during sync 
	 *        (command line --memadjust option).  This param applies to sync commands only
	 *        and is optional.  It will be null if it is not used or does not apply. 
	 * @param start_time = Start time for check/sync job.  Null if the job should be 
	 *        run immediately.
	 * @param frequency = Frequency to repeat the job.  Null if the job should not be repeated
	 * @param days_of_week = Optional.  Array mapping each day of week to boolean
	 *        indicating whether the job should be run on that day.  
	 *        Eg.  $days['MONDAY'] = true, $dasy['TUESDAY'] = false, etc...
	 * @param sinceOption = Optional. Limits the check to rows updated in the specified interval
	 *        or since the specified timestamp
	 * @param whereOption = Optional. Limits the check to rows matching this 'where' clause
	 * @param deployUDR = Defaults to true.  Does the function need to test and deploy
	 *        the admin_async UDR?
	 * @param use_timestamp = Optional. Uses timestamp repair with CDR check repair when true. 
	 *  
	 * 
	 * @return the result of the command will be stored in the $result array as follows:
	 * 		$result['SUCCESS'] --> true/false, based on result of Admin API command execution 
	 * 		$result['RESULT_MESSAGE'] --> success or failure message
	 **/
	public function performCheckSyncAction($action, $object, $name, $job_name, $ref_data_source, 
		$target_servers_list="", $repair=false, $extra_target_rows=null, $fire_trigger=null, 
		$mem_adjust=null, $start_time=null, $frequency=null, $days_of_week=null, $sinceOption=null, 
		$whereOption=null, $deployUDR = true, $virtualProcesses=null, $use_timestamp = false,
		$delete_wins = false, $enableservers = false)
	{
		$result = array();
		
		/* Validate parameters */
		$invalid_params = false;
		// Check for valid action and object
		if (!($action == self::CHECK_TYPE_WORD || $action == self::SYNC_TYPE_WORD)) $invalid_params = true; 
		if (!($object == "replicate" || $object=="replicateset")) $invalid_params = true;
		// Object name, job name, ref_data_source cannot be empty
		if ($name == "" || $job_name == "" || $ref_data_source == "") $invalid_params = true;
		// If any of the parameter checks failed, return with error
		if ($invalid_params)
		{
			$result['SUCCESS'] = false;
			$result['RESULT_MESSAGE'] = "Invalid cdr replicate or replicateset action.";
			return $result;
		}
		
		if ($deployUDR)
		{
			/* Test and deploy admin_async procedure if necessary */
			$this->idsadmin->testAndDeployAdminAsync();
			/* Test and create ER group in sysadmin ph_group */
			$this->testAndDeploySysAdminERGroup(self::SYSADMIN);
		}
		
		/* Setup up async SQL Admin API command and parameter values */
		$command = " \"\"cdr {$action} {$object}\"\" ";

		/* Parameter string start */
    	$parameters = "\"\"";

		/* Don't specify --master if using timestamp conflict resolution option */   
		if ($use_timestamp != true)
		{
		  $parameters .= "--master={$ref_data_source} ";
		}
		if ($object == "replicate")
		{
			$parameters .= "--repl=$name";
		} else {
			$parameters .= "--replset=$name";
		}
		if ($target_servers_list == "")
		{
			$parameters .= " --all";
		} else {
			$parameters .= " {$target_servers_list}";
		}
		if ($repair == true && $action != self::SYNC_TYPE_WORD)
		{
			$parameters .= " --repair";
	  		if ($use_timestamp == true)
	  		{
	  			$parameters .= " --timestamp";
	  		}
	  		if ($delete_wins == true)
	  		{
	  			$parameters .= " --deletewins";
	  		}
	  		if ($enableservers == true)
	  		{
	  			$parameters .= " --enable";
	  		}
		}
		if (!is_null($extra_target_rows) && $use_timestamp != true)
		{
			$parameters .= " --extratargetrows={$extra_target_rows}";
		}
		if (!is_null($fire_trigger))
		{
				$parameters .= " --firetrigger={$fire_trigger}";
		}
		if ($action == self::SYNC_TYPE_WORD && !is_null($mem_adjust))
		{
			$parameters .= " --memadjust={$mem_adjust}";
		}

		if ($action == self::CHECK_TYPE_WORD && !empty($sinceOption))
		{
			$parameters .= " --since=\"\"\"\"{$sinceOption}\"\"\"\" ";
		}
		if ($action == self::CHECK_TYPE_WORD && $object == 'replicate' && !empty($whereOption))
		{
			$parameters .= " --where=\"\"\"\"{$whereOption}\"\"\"\" ";
		}

		if(!is_null($virtualProcesses) && $virtualProcesses != "")
		{
			$parameters .= " --process={$virtualProcesses}";
		}
		$parameters .= " --name={$job_name}";
		
		/* Parameter string end */
		$parameters .= "\"\"";

		// Set the arguments to pass to async SQL Admin API prodcure
		$task = array();
		$task["COMMAND"] = $command;
		$task["PARAMETERS"] = $parameters;
		$task["COMMENTS"] = $this->formJobDescription($action,$job_name);
		if (!is_null($start_time))
		{
			$task['START_TIME'] = $start_time;
			$task['END_TIME'] = "null";
			$task['FREQUENCY'] = (is_null($frequency))? "null":$frequency;
		}
		if (!is_null($days_of_week))
		{
			$days_array = unserialize($days_of_week);
			foreach ($days_array as $day => $enabled)
			{
				$task[$day] = $enabled;
			}
		}
		
		// Execute the Async SQL Admin API Command
		$result = $this->executeAsyncSQLAdminAPICommand($task);
		//error_log ( 'task: ' . var_export ( $task, true ) );
    	if (!$result['SUCCESS'] && !isset($result['RETURN_CODE']))
    	{
    		$result['RETURN_CODE'] = self::ERROR;
    	}
		
    	return $result;
	}
	
	/**
	 * Get number of available virtual processors
	 * @return 
	 */
	public function getNumbOfAvailableVirtualProcessors()
	{
		$result = Array();
		$qry = "SELECT count(*) as NUMCPUVPS from sysvplst WHERE sysvplst.class = 0";
		$res = $this->doDatabaseWork($qry,self::SYSMASTER);
		$result['NUMCPUVPS'] = $res[0]['NUMCPUVPS']; 
    	return $result;
	}
	
	
	/**
	 * Get input validation information that we need for the cd check/sync actions.
	 * 
	 * For check/sync input validation, we need to know:
	 *   (1) current list of job names from replcheck_stat table
	 *   (2) current setting of CDR_QUEUEMEM
	 */
	public function getCheckSyncValidationInfo() 
	{
		$result = array();
        
		if ($this->doCheckSyncProgressTablesExist())
		{
			// Get list of existing cdr check/sync job names
			$query = "select replcheck_name, replcheck_type, replcheck_status from replcheck_stat";
			$res = $this->doDatabaseWork ($query, self::SYSCDR);
			$xml = "<jobs>";
			foreach ( $res as $row )
			{
				$xml .= "<job name=\"{$row['REPLCHECK_NAME']}\" type=\"{$row['REPLCHECK_TYPE']}\" status=\"{$row['REPLCHECK_STATUS']}\"/>";
			}
			$xml .= "</jobs>";
			$result['JOB_NAMES'] = $xml;
		} else {
			// If replcheck_stat table does not exist yet, there are no
			// existing jobs.
			$result['JOB_NAMES'] = "<jobs></jobs>";
		}
        
		// Get current CDR_QUEUEMEM setting
		$query = "select cf_effective from syscfgtab where cf_name = 'CDR_QUEUEMEM'";
		$res = $this->doDatabaseWork ($query, "sysmaster");
		if (count($res) == 1)
		{
			$result['CDR_QUEUEMEM'] = $res[0]['CF_EFFECTIVE'];
		} else {
			$result['CDR_QUEUEMEM'] = 4096;
		}
        
		return $result;
	}
	
    public function getReplicates ( )
	{
	    $query = "SELECT r.repid"
	           . "     , r.primaryrepid"
	           . "     , r.replsetid"
	           . "     , r.repstate"
	           . "     , r.flags"
	           . "     , TRIM ( r.repname ) AS repname"
	           . "     , r.cr_primary"
	           . "     , r.cr_secondary"
	           . "     , r.cr_spopt"
	           . "     , TRIM ( r.cr_spname ) AS cr_spname"
	           . "     , r.freqtype"
	           . "     , r.create_time"
	           . "     , r.modify_time"
	           . "     , r.susp_time"
	           . "     , p.repid"
	           . "     , p.servid"
	           . "     , p.partnum"
	           . "     , p.partstate"
	           . "     , p.partmode"
	           . "     , p.flags AS partflags"
	           . "     , p.start_time"
	           . "     , p.stop_time"
	           . "     , TRIM ( p.db ) AS db"
	           . "     , TRIM ( p.owner ) AS owner"
	           . "     , TRIM ( p.table ) AS table"
	           . "     , TRIM ( p.selecstmt ) AS selecstmt"
	           . "     , TRIM ( h.groupname ) AS groupname"
	           . "     , exclusive"
	           . "     , m.flags AS master_flags"
	           . "     , TRIM ( m.tabserver ) AS master_server"
	           . "     , TRIM ( m.tabdb ) AS master_database"
	           . "     , TRIM ( m.tabowner ) AS master_owner"
	           . "     , TRIM ( m.tabname ) AS master_table"
	           . "     , f.objtype"
	           . "     , f.hour"
	           . "     , f.min"
	           . "     , f.day"
	           . "     , f.lastexec";
	    if (Feature::isAvailable( Feature::PANTHER_UC3, $this->idsadmin))
	    {
	    	$query .= ", BITAND(a.xtd_attr1,'0x0000001') as utf8_transport ";
	    }
	    $query .= "  FROM repdef r, OUTER ( partdef p, OUTER ( servdef s, OUTER hostdef h ) )"
	           . "     , OUTER (select replsetpartdef.repid, 'E' as exclusive from replsetdef,"
	           . "              replsetpartdef where replsetdef.replsetid = replsetpartdef.replsetid"
	           . "              and bitval(replsetdef.replsetattr,'0x00000080')>0) e"
	           . "     , OUTER mastered_replicates m"
	           . "     , OUTER freqdef f ";
	    if (Feature::isAvailable( Feature::PANTHER_UC3, $this->idsadmin))
	    {
	    	$query .= ", OUTER repxtdattr a ";
	    }
	    $query .= " WHERE r.repid = p.repid"
	           . "   AND p.servid = s.servid"
	           . "   AND s.servid = h.servid"
	           . "   AND r.repid = e.repid"
			   . "   AND r.repid = m.replid"
	           . "   AND r.repid = f.repid";
	    if (Feature::isAvailable( Feature::PANTHER_UC3, $this->idsadmin))
	    {
	    	$query .= " AND r.repid = a.repid ";
	    }
	    $query .= "   AND r.repname NOT LIKE '_ifx_%' " // filter out all internal replicates
			   . "   AND r.repid NOT IN" //filter out replicates belonging to (and only to) unrealized template
			   . "	 ( "
			   . "		SELECT replid FROM templatetables"
			   . " 		WHERE"
			   . "			replid NOT IN"	// make sure the replicates' template is unrealized
			   . " 				(SELECT repid FROM partdef)" 
			   . "			AND replid NOT IN" //make sure this replicate does not belong to a replicate set
			   . "				(SELECT r1.repid FROM replsetpartdef r1, replsetdef r2 WHERE r1.replsetid = r2.replsetid AND bitval ( r2.replsetattr, 2097152 ) != 1) "
		   	   . "   ) "
	           . " ORDER BY r.repname, db, groupname, owner, table";
	             
	    $rows = $this->doDatabaseWork ( $query, 'syscdr' );
	    
	    $xml = "<rows>";
	    $replicate = "";
	    $participantCount = 0;
	    $activeParticipants = 0;
	    foreach ( $rows as $row )
		{
		    if ( $row['REPNAME'] != $replicate )
		    {
			    if ( $replicate != "" )
			    {
			    	$splitPos = strrpos($xml,"<details>") - 1;
			    	$xml = substr($xml,0,$splitPos) . 
			    		" participantCount=\"{$participantCount}\"" . 
			    		" activeParticipants=\"{$activeParticipants}\"" .
			    		substr($xml,$splitPos);
			    		
			    	$xml .= "</participants></details></row>";
			    }
			    	
			    $replicate = $row['REPNAME'];
			    
			    $xml .= "<row repid=\""           . $row['REPID'           ] . "\""
			         .  " primaryrepid=\""        . $row['PRIMARYREPID'    ] . "\""
			         .  " replsetid=\""           . $row['REPLSETID'       ] . "\""
			         .  " repstate=\""            . $row['REPSTATE'        ] . "\""
			         .  " flags=\""               . $row['FLAGS'           ] . "\""
			         .  " repname=\""             . $row['REPNAME'         ] . "\""
			         .  " cr_primary=\""          . $row['CR_PRIMARY'      ] . "\""
			         .  " cr_secondary=\""        . $row['CR_SECONDARY'    ] . "\""
			         .  " cr_spopt=\""            . $row['CR_SPOPT'        ] . "\""
			         .  " cr_spname=\""           . $row['CR_SPNAME'       ] . "\""
			         .  " exclusive=\""           . $row['EXCLUSIVE'       ] . "\""
			         .  " freqtype=\""            . $row['FREQTYPE'        ] . "\""
			         .  " create_time=\""         . $row['CREATE_TIME'     ] . "\""
			         .  " modify_time=\""         . $row['MODIFY_TIME'     ] . "\""
			         .  " susp_time=\""           . $row['SUSP_TIME'       ] . "\""
			         .  " master_flags=\""        . $row['MASTER_FLAGS'    ] . "\""
			         .  " master_server=\""       . $row['MASTER_SERVER'   ] . "\""
			         .  " master_database=\""     . $row['MASTER_DATABASE' ] . "\""
			         .  " master_owner=\""        . $row['MASTER_OWNER'    ] . "\""
			         .  " master_table=\""        . $row['MASTER_TABLE'    ] . "\""
			         .  " freq_object_type=\""    . $row['OBJTYPE'         ] . "\""
			         .  " freq_day=\""            . $row['DAY'             ] . "\""
			         .  " freq_hour=\""           . $row['HOUR'            ] . "\""
			         .  " freq_minute=\""         . $row['MIN'             ] . "\""
			         .  " freq_last_execution=\"" . $row['LASTEXEC'        ] . "\"";
			     if (Feature::isAvailable( Feature::PANTHER_UC3, $this->idsadmin))
			     { 
			         $xml .= " utf8_transport=\""      . $row['UTF8_TRANSPORT'  ] . "\"";
			     } else {
			         $xml .= " utf8_transport=\"0\"";
			     }
			     $xml .= "><details><participants>";
			    
				$participantCount = 0;
			    $activeParticipants = 0;

			}
			    
			if ( isset ( $row['GROUPNAME'] ) && isset ( $row['DB'] ) && isset ( $row['OWNER'] ) && isset ( $row['TABLE'] ) )
			{
				$participantCount++;
				if ($row['PARTSTATE'] & 0x00000004)
				{
					$activeParticipants++;
				}
				$xml .= "<row repid=\""  . $row['REPID'     ] . "\""
				     .  " servid=\""     . $row['SERVID'    ] . "\""
				     .  " partnum=\""    . $row['PARTNUM'   ] . "\""
				     .  " partstate=\""  . $row['PARTSTATE' ] . "\""
				     .  " partmode=\""   . $row['PARTMODE'  ] . "\""
				     .  " partflags=\""  . $row['PARTFLAGS' ] . "\""
				     .  " start_time=\"" . $row['START_TIME'] . "\""
				     .  " stop_time=\""  . $row['STOP_TIME' ] . "\""
				     .  " groupname=\""  . $row['GROUPNAME' ] . "\""
				     .  " db=\""         . $row['DB'        ] . "\""
					 .  " dbname=\""     . $row['DB'        ] . "\""
				     .  " owner=\""      . $row['OWNER'     ] . "\""
					 .  " tabowner=\""   . $row['OWNER'     ] . "\""
				     .  " table=\""      . $row['TABLE'     ] . "\""
					 .  " tabname=\""    . $row['TABLE'     ] . "\""
				     .  " selecstmt=\""  . htmlentities ( $row['SELECSTMT'],ENT_COMPAT,"UTF-8" ) . "\""
				     .  " participant_type=\"" . $row['PARTMODE' ] . "\""
				     .  " apply_as_informix=\"" . ( ( ( $row['PARTFLAGS' ] & self::A_USETABOWNER ) == self::A_USETABOWNER ) ? "false" : "true" ) . "\""
				     .  "></row>";
			}
		}
		    
		if ( ! empty ( $rows ) )
		{
			$splitPos = strrpos($xml,"<details>") - 1;
			    	$xml = substr($xml,0,$splitPos) . 
			    		" participantCount=\"{$participantCount}\"" . 
			    		" activeParticipants=\"{$activeParticipants}\"" .
			    		substr($xml,$splitPos);
			$xml .= "</participants></details></row>";
		}
		    
		$xml .= "</rows>";
		//error_log ( "xml: " . $xml );
	    return $xml;
	}
	    
	public function getReplicateSets ( )
		{
		$query = "SELECT s.replsetid"
               . "     , s.replsetattr"
               . "     , s.replsetstate"
               . "     , TRIM ( s.replsetname ) AS replsetname"
               . "     , s.freqtype"
               . "     , s.susp_type"
               . "     , s.create_time"
               . "     , s.modify_time"
               . "     , r.repid"
               . "     , r.primaryrepid"
               . "     , r.replsetid"
               . "     , r.repstate"
               . "     , r.flags"
               . "     , TRIM ( r.repname ) AS repname"
               . "     , r.cr_primary"
               . "     , r.cr_secondary"
               . "     , r.cr_spopt"
               . "     , TRIM ( r.cr_spname ) AS cr_spname"
               . "     , r.freqtype AS repfreqtype"
               . "     , r.susp_time AS repsusp_time"
               . "     , r.create_time AS repcreate_time"
               . "     , r.modify_time AS repmodify_time"
               . "  FROM replsetdef s, OUTER ( replsetpartdef p, OUTER repdef r )"
               . " WHERE s.replsetid = p.replsetid"
               . "   AND r.repid = p.repid"
               . "   AND s.replsetname != 'ifx_internal_set'"   // filter out internal replicate set
               . " ORDER BY s.replsetname, r.repname";     
		
        $rows = $this->doDatabaseWork ( $query, 'syscdr' );
        
       	$xml = "<rows>";
       	$replicateSetName = "";
       	foreach ( $rows as $row )
	       	{
	       	if ( $row['REPLSETNAME'] != $replicateSetName )
			    {			    	
			    if ( ! empty ( $replicateSetName ) )
			    	{
			    	$xml .= "</replicates></details></row>";
			    	}
			    		
				$replicateSetName = $row['REPLSETNAME'];

				$xml .= "<row replsetid=\"" . $row['REPLSETID'   ] . "\""
			         .  " replsetattr=\""   . $row['REPLSETATTR' ] . "\""
			         .  " replsetstate=\""  . $row['REPLSETSTATE'] . "\""
			         .  " replsetname=\""   . $row['REPLSETNAME' ] . "\""
			         .  " freqtype=\""      . $row['FREQTYPE'    ] . "\""
			         .  " susp_time=\""     . $row['SUSP_TYPE'   ] . "\""
			         .  " create_time=\""   . $row['CREATE_TIME' ] . "\""
			         .  " modify_time=\""   . $row['MODIFY_TIME' ] . "\""
			         .  "><details><replicates>";
			    }
			    	
			if ( isset ( $row['REPNAME'] ) )
				{		
				$xml .= "<row repid=\""      . $row['REPID'         ] . "\""
				     .  " primaryrepid=\""   . $row['PRIMARYREPID'  ] . "\""
				     .  " replsetid=\""      . $row['REPLSETID'     ] . "\""
				     .  " repstate=\""       . $row['REPSTATE'      ] . "\""
				     .  " flags=\""          . $row['FLAGS'         ] . "\""
				     .  " repname=\""        . $row['REPNAME'       ] . "\""
				     .  " cr_primary=\""     . $row['CR_PRIMARY'    ] . "\""
				     .  " cr_secondary=\""   . $row['CR_SECONDARY'  ] . "\""
				     .  " cr_spopt=\""       . $row['CR_SPOPT'      ] . "\""
				     .  " cr_spname=\""      . $row['CR_SPNAME'     ] . "\""
				     .  " repfreqtype=\""    . $row['REPFREQTYPE'   ] . "\""
				     .  " repsusp_time=\""   . $row['REPSUSP_TIME'  ] . "\""
				     .  " repcreate_time=\"" . $row['REPCREATE_TIME'] . "\""
				     .  " repmodify_time=\"" . $row['REPMODIFY_TIME'] . "\""
				     .  "></row>";
			    }		      
	       	}
	       	
		if ( ! empty ( $rows ) )
			{	
			$xml .= "</replicates></details></row>";
			}

		$xml .= "</rows>";
	    return $xml;
		}

	/**
     * Returns whether CDR check repair with timestamp is valid for a given replicate set
	 * @param objName = Required. A replicate set name to check timestamp option validity
	 * 
	 * @return Boolean indicating whether a replicate set has homogeneous conflict
	 * 		   resolution options compatible with CDR check repair with timestamp option.
	 * 		
     */
	public function isTimestampOptionValid ($objName) {
		$query = "SELECT cr_primary FROM"
               . " (SELECT * FROM replsetdef s, OUTER(replsetpartdef p, OUTER repdef r)"
               . " WHERE s.replsetid = p.replsetid"
               . " AND r.repid = p.repid"
               . " AND s.replsetname = '{$objName}')"  
               . " WHERE cr_primary <> 'T'" 
               . " AND cr_primary <> 'D'"; 
                              		
        $rows = $this->doDatabaseWork ( $query, 'syscdr' );
        $return = !(count($rows) > 0);
        return $return;
	}

	/**
	 * Defines a new replicate set.
	 * All arguments are required.
	 * @frequency:     frequency option string
	 * @exclusivity:   exclusivity option string (may be the empty string)
	 * @name:	       name of the replicate set
	 * @replicates:    serialized array of strings of the member replicates' names
	 * 
	 * @return an array of:
	 *     "SUCCESS"        => true or false
	 *     "RESULT_MESSAGE" => a message string
	 */
	public function defineReplicateSet($frequency, $exclusivity, $name, $replicates) 
	{
		$replicates = unserialize($replicates);
				
		$task = array("COMMAND" => "'cdr define replicateset', ");
		/* 
		 * The ADMIN function takes up to 6 LVARCHAR arguments (in addition to the "COMMAND") 
		 * each of 2048 bytes max. Use the first for the frequency, eclusivity, and name.
		 * Use the rest for the member replicates, ensuring we stay within the
		 * proper length.
		 */			
		$parameters = "'{$frequency} ${exclusivity} ${name}'";
		$numParamsUsed = 1;
		
		$param = "";
		$numParamsUsed++;
		$i = 0;		
		while ($i < count($replicates)) {
			if (strlen($param) > 0)
				$param .= " ";
			$param .= $replicates[$i];
							
			if (($i+1) < count($replicates)){
				/* There are more replicates to add.  Check the parameter's length. */				
				if (strlen($param . $replicates[$i+1]) > self::LVARCHAR_LENGTH){
					$parameters .= ", '" . $param . "'";				
					$param = "";
					$numParamsUsed++;					
					
					if ($numParamsUsed > self::MAX_NUM_ADMIN_PARAMS){
						/* The length of the replicate names is too long for the Admin API. */
						$task["SUCCESS"] = false;
						$task["RESULT_MESSAGE"] = $this->idsadmin->lang('ExceededAdminAPI');
						return $task;
					}
				}
			}
			else {
				/* All the replicates have been added. */
				$parameters .= ", '" . $param . "'";				
			}					
			$i++;
		}
		$task['PARAMETERS'] = $parameters;	
		
		return $this->executeSQLAdminAPICommand($task);
	}
	
	/**
	 * Get replicate participant candidate groups
	 * 
	 * @param boolean include_databases - include candidate databases for the groups
	 */
	public function getReplicateParticipantCandidateGroups ($include_databases = false)
	{
		// Get groups in the ER domain.
		$query = "SELECT TRIM ( servname ) AS groupname"
		       . "  FROM syscdrs";
        $groups = $this->doDatabaseWork ( $query, 'sysmaster' );
		       
        $xml = "";
        foreach ( $groups as $group )
	    {
	        $xml .= "<group label=\"" . $group['GROUPNAME'] . "\"" 
	             .  " groupname=\""   . $group['GROUPNAME'] . "\""
	             .  " selectable=\"true\""
	             .  ">";
	        if ($include_databases)
	        {
	             $xml .= $this->getReplicateParticipantCandidateDatabases ( $group['GROUPNAME'] );
	        }
	        $xml .= "</group>";
	    }
        return $xml;
	}
		
	/**
	 * Get replicate participant candidate database for a particular database server group.
	 * 
	 * @param group name
	 * @param boolean include_tables - include candidate tables for the database
	 */
	public function getReplicateParticipantCandidateDatabases ( $group, $include_tables=false )
	{
		/*
		 * Use a distributed query against '$group' to find all databases
		 * that are logged and do not have the DB_READONLY (0x08) or 
		 * DB_BYPASSCHECK (0x20) bit set. Only system databases (sysadmin,
		 * syscdr, sysmaster, sysusers, sysutils) can have those bits set.
		 */
		$query = "SELECT TRIM ( d.name ) AS dbname"
		       . "     , d.partnum"
               . "     , TRIM ( d.owner ) AS dbowner"
               . "     , d.created"
               . "     , d.is_logging"
               . "     , d.is_buff_log"
               . "     , d.is_ansi"
               . "     , d.is_nls"
               . "     , d.flags"
               . "     , z.dbs_collate"
               . "  FROM sysmaster@{$group}:sysdatabases d, sysmaster@{$group}:sysdbslocale z"
               . " WHERE d.is_logging = 1"   // Server restriction, ER requires logged databases.
               . "   AND d.is_ansi = 0"      // OAT-only restriction, ANSI databases not currently supported in OAT UI.
               . "   AND d.name = z.dbs_dbsname"
               . "   AND DECODE ( BITAND ( d.flags, 40 ), 0 , 0 , 1 ) = 0"
               . " ORDER BY dbname";
               
       
        $databases = $this->doDatabaseWork ( $query );
          
        /*
         * For each database, find eligible tables
         */
        
        $xml = "";
        foreach ( $databases as $database )
        {
	        $xml .= "<database label=\""  . $database['DBNAME'     ] . "\""
	             .  " dbname=\""          . $database['DBNAME'     ] . "\""
				 .  " partnum=\""         . $database['PARTNUM'    ] . "\""
				 .  " dbowner=\""         . $database['DBOWNER'    ] . "\""
				 .  " created=\""         . $database['CREATED'    ] . "\""
				 .  " is_logging=\""      . $database['IS_LOGGING' ] . "\""
				 .  " is_buff_log=\""     . $database['IS_BUFF_LOG'] . "\""
				 .  " is_ansi=\""         . $database['IS_ANSI'    ] . "\""
				 .  " is_nls=\""          . $database['IS_NLS'     ] . "\""
				 .  " flags=\""           . $database['FLAGS'      ] . "\""
				 .  " locale=\""	  . $database['DBS_COLLATE'] . "\""
				 .  " selectable=\"true\""
				 .  ">";
	        if ($include_tables)
	        {
				$xml .= $this->getReplicateParticipantCandidateTables ( $group, $database['DBNAME'], $database['DBS_COLLATE'] );
	        }
	        $xml .= "</database>";
        }
	       
	    return $xml;
	}
	
	/**
	 * Get replicate participant candidate tables for a particular server group and database.
	 * 
	 * @param $group - group name
	 * @param $database - database name
	 * @param $locale - locale of the database
	 * @param $tabname - table name if looking for single table, null otherwise
	 * @param $tabowner - table owner if looking for single table, null otherwise
	 * @param $tabname_search_pattern - table pattern name to search for
	 */
	public function getReplicateParticipantCandidateTables ( $group, $database, $locale, 
															 $tabname = null, $tabowner = null, $tabname_search_pattern = null )
	{
		/*
		 * Use a distributed query to select all tables that:
		 * 1. are not part of the system catalogs (tabid >= 100)
		 * 2. are not external, views, sequences, public or private synonyms (tabtype = 'T')
		 * 3. are not CDR deletion tables (tabname[1,11] != 'cdr_deltab_')
		 * 4. have a primary key (constrtype = 'P') OR have erkey shadow columns (having one of the shadow columns is enough for checking. In this query we look for ifx_erkey_1.
		 */
		$sql_for_erkey_tabs = "SELECT tabid FROM {$database}@{$group}:syscolumns WHERE colname = 'ifx_erkey_1'";
		
		$query = "SELECT UNIQUE TRIM ( t.tabname ) AS tabname"
               . "     , TRIM ( t.owner ) AS tabowner"
               . "     , t.rowsize"
               . "     , t.ncols"
               . "     , t.flags"
			   . "	   , CASE " 
		       . "         WHEN c.constrtype = 'P' " 
		       . "            THEN 'yes' " 
		       . " 		   ELSE " 
		       . "            'no' " 
		       . " 		 END as hasprimarykey " 
			   . "	   , CASE " 
		       . "         WHEN t.tabid in ($sql_for_erkey_tabs) " 
		       . "            THEN 'yes' " 
		       . " 		   ELSE " 
		       . "            'no' " 
		       . " 		 END as haserkey " 
               . "     , TRIM ( DECODE ( ( SELECT COUNT ( * )"
               . "                           FROM syscdr:partdef p"
               . "                              , syscdr:hostdef h"
               . "                              , syscdr:servdef s"
               . "                          WHERE p.table     = t.tabname"
               . "                            AND p.db        = '{$database}'"
               . "                            AND h.groupname = '{$group}'"
               . "                            AND s.servid = p.servid"
               . "                            AND h.servid = s.servid )"
               . "                     , 0, 'false', 'true' ) ) AS replicated"
               . "     , ( SELECT SUM ( mod(o.coltype,256) ) + SUM ( o.collength )"
               . "           FROM {$database}@{$group}:syscolumns o"
               . "          WHERE o.tabid = t.tabid"
               . "            AND (o.colattr = 0 OR o.colattr = 128)) AS checksum"
               . "  FROM {$database}@{$group}:systables t"
               . "     , {$database}@{$group}:sysconstraints c"
               . " WHERE t.tabid >= 100"
               . "   AND t.tabtype = 'T'"
               . "   AND t.tabname [ 1, 11 ] != 'cdr_deltab_'"
               . "   AND t.tabid = c.tabid"
               . "   AND (c.constrtype = 'P' OR t.tabid in ($sql_for_erkey_tabs)) "
               . (($tabname == null)?  "":"    AND t.tabname = '{$tabname}' ")
               . (($tabowner == null)? "":"    AND t.owner = '{$tabowner}' ")
               . (($tabname_search_pattern == null)? "":"    AND t.tabname like '%{$tabname_search_pattern}%' ")
               . " ORDER BY tabname";
        $tables = $this->doDatabaseWork ( $query, self::SYSMASTER, null, array(), $locale );
        $existing_tables = array(); //let's use this array to filter out possible duplicates
        
        $xml = "";
        foreach ( $tables as $table )
	    {
        	if(isset($existing_tables[$table['TABNAME']] ))
        	{
					continue;
        	}
        	$existing_tables[$table['TABNAME']] = true;
			
        	$xml .= "<table label=\""     . $table['TABNAME'   		] . "\""
	             .  " tabname=\""         . $table['TABNAME'   		] . "\""
	             .  " tabowner=\""        . $table['TABOWNER'  		] . "\""
	             .  " rowsize=\""         . $table['ROWSIZE'   		] . "\""
	             .  " ncols=\""           . $table['NCOLS'     		] . "\""
	             .  " flags=\""           . $table['FLAGS'     		] . "\""
	             .  " replicated=\""      . $table['REPLICATED'		] . "\""
	             .  " checksum=\""        . $table['CHECKSUM'  		] . "\""
				 .  " has_primary_key=\"" . $table['HASPRIMARYKEY'  ] . "\""
				 .  " has_erkey=\""       . $table['HASERKEY'  		] . "\""
	             .  " selected=\"false\""
	             .  " mismatch=\"false\""
	             .  " participant_type=\"P\""
	             .  " apply_as_informix=\"true\""
	             .  " selectable=\"true\""
	             .  " autocreate=\"false\""
	             .  " master_participant=\"false\""
	             .  " groupname=\"{$group}\""
	             .  " dbname=\"{$database}\""
	             .  " locale=\"{$locale}\""
	             .  "></table>";
	    }
	        
	    return $xml;
	}
	
	/**
	 * This function is called by the Replicate Wizard when the user chooses to  
	 * add a participant to all servers that have a matching database name.
	 * 
	 * This function therefore needs to check the list of server groups and 
	 * return those that have a database of the given name.
	 * 
	 * @param $group_list - list of server groups (serialized array)
	 * @param $dbname - database name
	 */
	public function checkAddParticipantToAllServers($group_list, $dbname, $participant)
	{
		$serverParticipantsToAdd = array();
		$group_list = unserialize($group_list);
		
		foreach ($group_list as $server_group)
		{
			$sql = "select count(*) as count "
				 . "from sysmaster@{$server_group}:sysdatabases d "
				 . "where d.is_logging = 1 "   // Server restriction, ER requires logged databases.
				 . "and d.is_ansi = 0 "      // OAT-only restriction, ANSI databases not currently supported in OAT UI.
				 . "and DECODE ( BITAND ( d.flags, 40 ), 0 , 0 , 1 ) = 0 "
				 . "and name = '{$dbname}'";
            
			try {
				$res = $this->doDatabaseWork($sql, 'sysmaster', null, array(), "", true); 
			} catch (PDOException $e) {
				
				// Catch exceptions to ignore down servers.
				
				// If one of the participant servers in a replicate is down,
				// we'll ignore it and thereby not add a participant to that server.
				$err_code = $e->getCode();
				$err_msg = $e->getMessage();
				if ($err_code == -908)
				{
					continue;
				} else {
					// Any other errors, we'll send as a fault.
					$err = "{$this->idsadmin->lang("Error")}: {$err_code} - {$err_msg}";
					trigger_error($err,E_USER_ERROR);
				}
			}
			
			if (count($res) > 0 && $res[0]['COUNT'] > 0)
			{
				// If database was found, add this server to the list
				$serverParticipantsToAdd[] = $server_group;
			}
		}
		
		$res['SERVERS'] = $serverParticipantsToAdd;
		$res['PARTICIPANT'] = $participant;
		return $res;
	}
		
	/**
	 * Get information about a replicate's participants for the 
	 * Modify Replicate wizard.
	 * 
	 * @param repid replicate id
	 */
	public function getModifyReplicateParticipantInfo($repid)
	{
		$sql = "SELECT r.repid, p.servid, db, owner, table, selecstmt, groupname " 
			 . "FROM repdef r, OUTER ( partdef p, OUTER ( servdef s, OUTER hostdef h ) ) "
			 . "WHERE r.repid = p.repid "
			 . "AND p.servid = s.servid "
			 . "AND s.servid = h.servid "
			 . "AND r.repid={$repid}";
			 
		$participants = $this->doDatabaseWork($sql, 'syscdr');
		
		$partInfoXML = "";
		foreach ($participants as $part)
		{
			$sql = "SELECT dbs_collate "
				 . "FROM sysmaster@{$part['GROUPNAME']}:sysdbslocale "
				 . "WHERE dbs_dbsname='{$part['DB']}'";
			try {
				$locale = $this->doDatabaseWork($sql, 'sysmaster', null, array(), "", true); 
			} catch (PDOException $e) {
				
				// Catch exceptions to ignore down servers.
				
				// If one of the participant servers in a replicate is down,
				// we won't be able to get info about it.  But to allow users
				// to still modify the replicate, we'll just ignore any
				// -908 'Attempt to connect to database server failed' errors
				// for down servers in this web service.
				$err_code = $e->getCode();
				$err_msg = $e->getMessage();
				if ($err_code == -908)
				{
					continue;
				} else {
					// Any other errors, we'll send as a fault.
					$err = "{$this->idsadmin->lang("Error")}: {$err_code} - {$err_msg}";
					trigger_error($err,E_USER_ERROR);
				}
			}

			$partInfoXML .= $this->getReplicateParticipantCandidateTables($part['GROUPNAME'], $part['DB'], 
				$locale[0]['DBS_COLLATE'], $part['TABLE'], $part['OWNER']);
		}
		return $partInfoXML;
	}
	
	/**
	 * Do replicate action.
	 * 
	 * @param $command - SQL Admin API command
	 * @param $parameters - SQL Admin API parameters
	 * @param $action_type - 'define', 'change', 'modify', or 'remaster'
	 **/
	public function doReplicateAction ( $command, $parameters, $action_type )
	{
		$task = array ( );
		$task [ 'COMMAND' ] = $command;
		$task [ 'PARAMETERS' ] = $parameters;
		$task [ 'ACTION_TYPE' ] = $action_type;
		$task = $this->executeSQLAdminAPICommand ( $task );
		return $task;
	}
        								  
	public function deleteReplicate ( $name 
	                                , $connectOption )
    {
        $task = array ( );
        $task [ 'COMMAND' ] = "'cdr delete replicate',";
        $parameters = "'{$connectOption} {$name}'";
        $task [ 'PARAMETERS' ] = $parameters;
        
        $task = $this->executeSQLAdminAPICommand ( $task );
		//error_log ( 'deleteReplicate: ' . var_export ( $task, true ) );
        return $task;
    }

 	/** 
	 * Obtain summary information about all check and sync jobs on replicates.
	 * @return XML of <jobs></jobs> that contains a <job> node for each job.
	 *   If the appropriate tables in syscdr do not exist there will be no <job> nodes.
	 */
	public function getCheckSyncReplicateJobs() {
		$result = "<jobs>";
		
		/* Do the appropriate syscdr tables exist? */
		$doTablesExist = $this->doCheckSyncProgressTablesExist();

		/* Obtain the jobs' info */
		if ($doTablesExist) {
			/*
			 * Obtain information about jobs that did not fail. 
			 */
			$tmp1 = $this->getCheckSyncReplicateJobInfo();//case 2,3,5 jobs

			/* 
			 * get information about sync/check tasks that failed.
			 * those tasks are jobs that failed. They are categorized as case 4 and case 5.
			 * Case 4 jobs are jobs that failed and did not write to syscdr. Case 5 jobs are jobs 
			 * that failed but written to syscdr. In general, case 4 jobs' information are 
			 * obtained from sysadmin while case 5 jobs information are obtained from both 
			 * databases sysadmin and syscdr.
			 */ 
			$tmp2 = $this->getCheckSyncFailedJobsNotWrittenToSYSCDR();//cases 4, 5 jobs
			/*	
			 * We added case 5 jobs to $tmp2 because we want to get their information from sysadmin. we
			 * did so because syscdr seems not to contain all the needed information for case 5 while those
			 * information could be found in sysadmin. 
			 * Now, since $tmp1 and $tmp2 overlap (both have case 5), we should combine them carefully.
			 */
			//$tmp = $this->combineJobs($tmp1, $tmp2);  //here we take the info of case 5 jobs from both sysadmin and syscdr
			$tmp = $this->combineJobs2($tmp1, $tmp2);//here we take the info of case 5 jobs from sysadmin only

			/* 
			 * Build a <job> node for each row.
			 * Depending on the job's status certain fields may be empty (e.g. rowsProcessed).
			 */
			foreach ($tmp as $row) {
				$percentComplete = 0;
				if ($row['STATUS'] == self::STATUS_COMPLETED_INSYNC || $row['STATUS'] == self::STATUS_COMPLETED_OUTOFSYNC)
					$percentComplete = 100;
				else if ($row['STATUS'] == self::STATUS_RUNNING && $row['NUMROWS'] > 0)
					$percentComplete = intval(($row['ROWS_PROCESSED'] / $row['NUMROWS']) * 100);
				else 
					$percentComplete = 0;
				
				if($row['MASTER'] == null || $row['MASTER'] == "")
					$row['MASTER'] = "";				

				$job = "<job name=\""            . $row['NAME']                                . "\""
						. " type=\""		     . $this->convertTypeToWord($row['TYPE']) 	   . "\""
						. " replicate=\""        . $row['REPLICATE']                           . "\""
						. " time_started=\""     . $row['TIME_STARTED']                        . "\""
						. " time_completed=\""   . $row['TIME_COMPLETED']                      . "\""
						. " status=\""           . $row['STATUS']                              . "\""
						. " totalRows=\""        . $row['NUMROWS']                             . "\""
						. " rowsProcessed=\""    . $row['ROWS_PROCESSED']                      . "\""
						. " time_remaining=\""   . $row['ESTIMATED_DURATION']                  . "\""
						. " duration=\""         . $row['DURATION']                            . "\""						
						. " percent_complete=\"" . strval($percentComplete)                    . "\""
					    . " current_time=\""     . $row['CURRENT_TIME']                        . "\""
						. " master=\""     		 . $row['MASTER']             				   . "\""	
						. " js_id=\""     		 . $row['JS_ID']             				   . "\""		
						. " cmd_number=\""     	 . $row['CMD_NUMBER']             			   . "\""
						. " replcheck_id=\""	 . $row['REPLCHECK_ID']						   . "\""	
						. " tk_id=\""         	 				               				   . "\""	
						. " tk_next_execution=\"" 											   . "\""
						. " />"
						;
				$result .= $job;				
			}
		}
		$result .= $this->getNeverRunScheduledCheckSyncJobs();
		$result .= "</jobs>";
		return $result;
	}
	
	/**
	 * Obtain details about a check/sync replicate job.
	 */
	public function getCheckSyncReplicateJobDetail($jobName, $jobType){
		$result = "";

		/*  
		 * Do the appropriate syscdr tables exist?
		 * At this point in OAT they should always exist, but just to be sure...
		 */
		$doTablesExist = $this->doCheckSyncProgressTablesExist();
		
		if ($doTablesExist) {
			$jobID = array($jobName, $this->convertTypeToLetter($jobType));
			$result = $this->buildReplJobNode($jobID); 
		}
		else {
			$result = "</job>";
		}
		
		return $result;
	}

	/** 
	 * Obtain information about all check/sync jobs on replicate sets
	 * Returns:
	 *   <jobs>
	 *      <job...
	 *      ...
	 *   </jobs> 
	 */
	public function getCheckSyncReplicateSetJobs() {
		$result = "<jobs>";

		/* Do the appropriate syscdr tables exist? */
		$doTablesExist = $this->doCheckSyncProgressTablesExist();

		/* Obtain the jobs' info */
		if ($doTablesExist) {
			$result .= $this->buildReplSetJobNodes();	
		}
		
		$result .= "</jobs>";
		return $result;
	}

	/**
	 * Obtain details about a check/sync replicate set job.
	 */
	public function getCheckSyncReplicateSetJobDetail($jobName, $jobType){
		$result = "";
		$jobType = $this->convertTypeToLetter($jobType);
		
		/*  
		 * Do the appropriate syscdr tables exist?
		 * At this point in OAT they should always exist, but just to be sure...
		 */
		$doTablesExist = $this->doCheckSyncProgressTablesExist();
		
		if ($doTablesExist){
			/* Query about the job - returns the <job> */
			$result = $this->buildReplSetJobNodes(array($jobName, $jobType));			
		
			/* 
			 * remove </job> so we ad more stuff to it
			 */
			$result = substr($result, 0, -6);
			
			/* Query about the jobs on all the replicates in this set */
			$qry = "SELECT TRIM(replcheck_replname) as replicate"
				. ",CASE WHEN replcheck_status IN ('C', 'F') THEN 1"
				. "      WHEN replcheck_status IN ('R')      THEN 2"
				. "      WHEN replcheck_status IN ('D')      THEN 3"
				. "      ELSE 4"
				. " END as myStatusOrdering"		
				. " , replcheck_start_time" 
				. " FROM " . self::REPLCHECK_STAT
				. " WHERE replcheck_name = ?"
				. " AND replcheck_type = ?"
				. " ORDER BY myStatusOrdering"
				. " , replcheck_start_time"
				. " , 1" 
				;
			$tmp = $this->doPreparedDatabaseWork($qry, array($jobName, $jobType), self::SYSCDR);

			foreach ($tmp as $row){
			$result .= $this->buildReplJobNode(array($jobName, $jobType), $row['REPLICATE']);	
			}
			/* Close the <job> element */
			$result .= "</job>";
		}
		return $result;
	}
	
	/* Submit an ATS/RIS repair job */
    public function submitATSRISRepairBatch($fileNames, $jobType)
    {
    	$result = array();
    	$fileNames = unserialize($fileNames);
    	// get the repair directory name
    	$atsrisDir = $this->getATSRISdir();   	
    	$repairDir = ($jobType == self::ATS) ? $atsrisDir['ATS_DIR'] : $atsrisDir['RIS_DIR'];    	
    	
    	$jobname = $this->createTabLoad($repairDir, $fileNames, $jobType);
    	
    	// Test and deploy perform_atsris_repair procedure if necessary		
		$this->testAndDeployPerformAtsRisRepair(self::SYSADMIN);
        // Test and create ER group in sysadmin ph_group 
        $this->testAndDeploySysAdminERGroup("sysadmin");
		
		// schedule the execution of perform_atsris_repair()
		$qry = "INSERT INTO ph_task ".
        							"( tk_name,".
        							"tk_description,".
        							"tk_type,".
        							"tk_group,".
        							"tk_execute,".
        							"tk_start_time,".
        							"tk_stop_time,".
        							"tk_attributes".
        							") ".
        						"VALUES ".
        							"(".
        							"\"OAT ER repair job ({$jobname})\",".
        							"\"OAT ER repair batch job\",".
        							"\"TASK\",".
        							"\"ER\",".
        							"\"execute function perform_atsris_repair(".
        							"'{$jobname}', \$DATA_TASK_ID, \$DATA_SEQ_ID );\",".
        							"CURRENT hour to second,".
        							"CURRENT hour to second,".
        							"8)";
		$res = $this->doDatabaseWork($qry, self::SYSADMIN);
    	$result['JOBNAME'] = $jobname;
    	return $result; 	
    }
	
	public function getCheckSyncJobFailureMessage($cmd_number)
	{		
		$message = "";
			
		$sql="SELECT cmd_ret_msg 
		FROM command_history WHERE "
		. " cmd_number = '" . $cmd_number . "'" 
		;
		
		$result = $this->doDatabaseWork($sql, self::SYSADMIN);
		if(count($result) <= 0)
			$message = "";
		else
			$message = $result[0]['CMD_RET_MSG'];

		return $message;
	}
    
    /* Get the status of the already submitted repair job */        
    public function getBatchStatus($jobname)
    {
    	$qry = "select jobname, execution_order, job_type, status, ret_code, cmd_ret_status, cmd_ret_msg ".
    				"from oat_atsris_repair_jobs as status, command_history as cmdhis ".
    				"where status.jobname=? and ABS(status.ret_code) = cmdhis.cmd_number";
    	$result = $this->doPreparedDatabaseWork($qry,array($jobname),self::SYSADMIN);
    	return $result;   
    }
    
    /* - Get (active) servers in the domain.
     * - Also get the template(replset) details, especially the
     *   participants (servers, where template has been realized).
     */        
    public function getServersInDomain($templateName)
    {
    	// get servers
		$query = "SELECT TRIM ( servname ) AS name"
		       . "  FROM syscdrs";
        $servers = $this->doDatabaseWork ( $query, 'sysmaster' );
		
        // get template(replset) details
        $query1 = "SELECT A.replsetname, A.replsetid, B.repid, C.repname, D.servid, E.groupname, E.name"
				. " FROM replsetdef A, replsetpartdef B, repdef C, partdef D, hostdef E"
				. " WHERE A.replsetname = ? AND"
       			. " A.replsetid = B.replsetid AND"
       			. " B.repid = C.repid AND"
       			. " C.repid = D.repid AND"
       			. " D.servid = E.servid";
		$rows = $this->doPreparedDatabaseWork($query1, array($templateName), "syscdr");

		if (empty($rows))
			{
				$xml = array('TEMPLATE' => '<template></template>');
				$templateDetails = array($xml);
				$result = array_merge($servers,$templateDetails);
				return $result;
			}
			
		// every row contains some common info, so grab it from the first row.
		$xml = '<template replsetname="' . $rows[0]['REPLSETNAME'] . '"'
			 . ' replsetid="' 			 . $rows[0]['REPLSETID'] 	 . '">';
			 
		foreach ($rows as $row)
	    	{
	    		$xml .= '<participant repid="' . $row['REPID'] 	 . '"'
	    			  . ' servid="'			   . $row['SERVID'] 	 . '"'
	    			  . ' groupname="'		   . $row['GROUPNAME'] . '"'
	    			  . ' name="'			   . $row['NAME']		 . '"/>';
	    	}
	    // close the xml tag
	    $xml .= '</template>';
	    $xmlarr = array('TEMPLATE' => $xml);
	    $templateDetails = array($xmlarr);
	    $result = array_merge($servers, $templateDetails);
		return $result;
    }
    
	/**
	 * submitRealizeTemplate --
	 * This function executes the realize template command
	 * 
	 * @param command = 'cdr realize template'
	 * @param parameters = parameters to the realize template command
	 * @param type = P (primary), S (send-only), or R (receive-only)
	 *  
	 * @return the result of the command will be stored in the $result array as follows:
	 * 		$result['SUCCESS'] --> true/false, based on result of Admin API command execution 
	 * 		$result['RETURN_CODE'] --> task id in ph_task
	 **/
	public function submitRealizeTemplate($command, $parameters, $type)
	{
		$result = array();
		
		// Set the arguments to pass to async SQL Admin API prodcure
		$task = array();
		$task["COMMAND"] = $command;
		$task["PARAMETERS"] = $parameters;
		$task["TYPE"] = $type;
		
		// Execute the Admin API Command
		$result = $this->executeSQLAdminAPICommand($task);
		
    	if (!$result['SUCCESS'] && !isset($result['RETURN_CODE']))
    	{
    		$result['RETURN_CODE'] = self::ERROR;
    	}
    	
    	return $result;
	}
	
	/* getDBSpaces --
	 * 
	 * This function gets the common dbspaces across a given list of server groups.
	 * 
	 * @param serverGroupNames = one or more server group names
	 *  
	 * @return the result (array of dbspaces)
	 * 		$result['DBSPACES'] 
	 * 		$result['FAIL']
	 **/
	public function getDBSpaces($serverGroupNames)
	{
	   $result = array();
	   $serverInfo = array();
	   $serverGroupNames = unserialize($serverGroupNames);
       		
   	   /* Construct a distributed query like:
    	*		SELECT A.name,
   		*				A.flags,B.flags,C.flags,D.flags
		*		FROM sysmaster@g_one:sysdbstab A, sysmaster@g_two:sysdbstab B,
	    *        	  sysmaster@g_three:sysdbstab C, sysmaster@g_four:sysdbstab D
    	*		WHERE A.name = B.name and A.name = C.name and A.name = D.name
		*			AND bitval(A.flags,'0x10')<=0
    	*				AND bitval(A.flags,'0x2000')<=0
    	*				AND bitval(A.flags,'0x8000')<=0
		*			AND bitval(B.flags,'0x10')<=0
    	*				AND bitval(B.flags,'0x2000')<=0
    	*				AND bitval(B.flags,'0x8000')<=0
		*			AND bitval(C.flags,'0x10')<=0
    	*				AND bitval(C.flags,'0x2000')<=0
    	*				AND bitval(C.flags,'0x8000')<=0
		*			AND bitval(D.flags,'0x10')<=0
    	*				AND bitval(D.flags,'0x2000')<=0
    	*				AND bitval(D.flags,'0x8000')<=0
    	*				;
		*/
	   $select_stmt = "SELECT a0.name, ";
	   $from_stmt = "FROM ";
	   $where_stmt = "WHERE ";
	   for ($i=0; $i<count($serverGroupNames) ; $i++) {
			$select_stmt .= "a{$i}.flags AS {$serverGroupNames[$i]}";
			$from_stmt   .= "sysmaster@{$serverGroupNames[$i]}:sysdbstab a{$i}";
			if ($i != 0) {
			   $where_stmt .= "a0.name = a{$i}.name AND ";
			}
			$where_stmt  .= "bitval(a{$i}.flags,'0x10')<=0 AND bitval(a{$i}.flags,'0x2000')<=0 AND bitval(a{$i}.flags,'0x8000')<=0";
			
			if ($i != count($serverGroupNames)-1) {
				$select_stmt .= ", ";
				$from_stmt   .= ", ";
				$where_stmt  .= " AND ";
			} else {
				$select_stmt .= " ";
				$from_stmt   .= " ";
				$where_stmt  .= ";";
			}		
	   }
	   $query = $select_stmt . $from_stmt . $where_stmt;
		
	   $serverInfo = $this->doDatabaseWork ( $query, "sysmaster");

	   return $serverInfo;
	}      
	
	public function getTemplates ( ) 
	{
		/* 
         * Templates are replicate sets with attribute 0x20000 (2097152) set 
         */
		$query = "SELECT replsetid"
		       . "     , replsetattr"
		       . "     , replsetstate"
		       . "     , TRIM ( replsetname ) AS replsetname"
		       . "     , freqtype"
		       . "     , susp_type"
		       . "     , create_time"
		       . "     , modify_time"
		       . "  FROM replsetdef"
		       . " WHERE bitval ( replsetattr, 2097152 ) = 1"
		       . " ORDER BY replsetname";
		       
		$rows = $this->doDatabaseWork ( $query, self::SYSCDR );
		
		$xml = '';
		foreach ( $rows as $row )
		{
			$xml .= "<template"
			     .  " replsetid=\""    . $row['REPLSETID'   ] . "\""
			     .  " replsetattr=\""  . $row['REPLSETATTR' ] . "\""
			     .  " replsetstate=\"" . $row['REPLSETSTATE'] . "\""
			     .  " replsetname=\""  . $row['REPLSETNAME' ] . "\""
			     .  " freqtype=\""     . $row['FREQTYPE'    ] . "\""
			     .  " susp_time=\""    . $row['SUSP_TYPE'   ] . "\""
			     .  " create_time=\""  . $row['CREATE_TIME' ] . "\""
			     .  " modify_time=\""  . $row['MODIFY_TIME' ] . "\""
			     .  ">"
			     .  $this->getTemplateReplicates ( $row ['REPLSETID'] )
			     .  "</template>";
		}
		return $xml;
	}
      
	public function defineTemplate ( $command, $parameters )
    {
	    $task = array ( );
    	$task [ 'COMMAND' ] = $command;
    	$task [ 'PARAMETERS' ] = $parameters;
    	$task = $this->executeSQLAdminAPICommand( $task );
    	return $task;
    }
	    
	/**
     * Delete the template 'templateName'
     */
	public function deleteTemplate ( $name 
	                               , $connectOption = '' )
		{
		$task = array ( );
		$task [ 'COMMAND'    ] = "'cdr delete template',";
		$task [ 'PARAMETERS' ] = "'{$connectOption} {$name}'";
		
		$task = $this->executeSQLAdminAPICommand ( $task );
		//error_log ( 'deleteTemplate: ' . var_export ( $task, true ) );
    	return $task;
		}
		
	public function deleteReplicateSet ( $name 
	                                   , $connectOption = '' )
        {
        $task = array ( );
        $task [ 'COMMAND'    ] = "'cdr delete replicateset',";
		$task [ 'PARAMETERS' ] = "'{$connectOption} {$name}'";
        
        $task = $this->executeSQLAdminAPICommand ( $task );
		//error_log ( 'deleteReplicateSet: ' . var_export ( $task, true ) );
        return $task;
        }
	/**
     * Returns all replicate and replicate set names. Used by Define Template wizard
     * to validate template names
     */
	    
	public function getAllReplicateNames ( ) 
		{
		$query = " SELECT TRIM( replsetname ) AS name"
		       . "   FROM replsetdef"
		       . "  UNION ALL"
		       . " SELECT TRIM( repname ) AS name"
		       . "   FROM repdef"
		       . "  ORDER BY name";
		       
		return $this->doDatabaseWork( $query, self::SYSCDR );  
		}
	
	private function deleteSerializedERTasks($tasks)
	{
		$qry = "";
		$qry1 = "DELETE FROM " . self::SYSADMIN 	. ":job_status 		WHERE js_id 		IN (";
		$qry2 = "DELETE FROM " . self::SYSADMIN 	. ":ph_task 		WHERE tk_id			IN (";
		$qry3 = "DELETE FROM replcheck_stat WHERE replcheck_name || replcheck_type IN ".
				"(SELECT replcheck_name || replcheck_type FROM replcheck_stat WHERE replcheck_id IN (";
		$qry1_updated = false;
		$qry2_updated = false;
		$qry3_updated = false;
	
		foreach ($tasks as $task)//each task is equivalent to this array: {task_id, task_status}
		{
			switch ($task[1])//task status
			{
				case self::STATUS_ABORTED:
				case self::STATUS_ABORTED_WITHOUT_SYSCDR:
				case self::STATUS_SOME_REP_ABORTED:
					$qry1 .= $task[0] . ",";
					$qry1_updated = true;
					break;
				case self::STATUS_SCHEDULED_NEVER_BEEN_RUN:
					$qry2 .= $task[0] . ",";
					$qry2_updated = true;
					break;
				default:
					$qry3 .= $task[0] . ",";
					$qry3_updated = true;
					break;
			}
		}	

		//remove the last commas
		if($qry1_updated)
		{
			$qry .= substr($qry1, 0, -1) . ");";
		}
		if($qry2_updated)
		{
			$qry .= substr($qry2, 0, -1) . ");";
		}
		if($qry3_updated)
		{
			$qry .= substr($qry3, 0, -1) . "));";
		}
		
		$this->doDatabaseWork($qry, self::SYSCDR);
	}	
	
	public function deleteERTasks ($tasks)
	{
		$tasks = unserialize($tasks);
		
		/**
		 * The query cannot afford a certain amount of tasks. To prevent the query from failing
		 * we set the maximum number of tasks to delete (per query) to MAX_NUMB_OF_ER_TASKS_TO_DELETE.
		 */
		$groupsOfTasks = array_chunk($tasks, self::MAX_NUMB_OF_ER_TASKS_TO_DELETE);
		
		foreach($groupsOfTasks as $groupOfTasks)
		{
			$this->deleteSerializedERTasks($groupOfTasks);
		}
	}
	              
	/**
	 * Changes an existing replicate set.
	 * All arguments are required.
	 * @command:	  cdr command (e.g. 'cdr change replicate')
	 * @parameters:   parameters (e.g. '--add myreplSet')
	 * @replicates:   serialized array of strings of the replicates' names
	 * 
	 * @return an array of:
	 *     "SUCCESS"        => true or false
	 *     "RESULT_MESSAGE" => a message string
	 */
	public function changeModifyReplicateSet($command, $parameters, $replicates) {
		$replicates = unserialize($replicates);		
		
		$result = array();
		
		// comma between command and parameters argument
		$command .= ',';
		
		// Set the arguments to pass to async SQL Admin API prodcure
		$task = array();
		$task["COMMAND"] = $command;

		if ($replicates != null)
			{
			/* 
			 * The ADMIN function takes up to 6 LVARCHAR arguments (in addition to the "COMMAND") 
			 * each of 2048 bytes max. Use the first for the frequency, eclusivity, and name.
			 * Use the rest for the member replicates, ensuring we stay within the
			 * proper length.
			 */			
			$numParamsUsed = 1;
			
			$param = "";
			$numParamsUsed++;
			$i = 0;		
			while ($i < count($replicates)) {
				if (strlen($param) > 0)
					$param .= " ";
				$param .= $replicates[$i];
								
				if (($i+1) < count($replicates)){
					/* There are more replicates to add.  Check the parameter's length. */				
					if (strlen($param . $replicates[$i+1]) > self::LVARCHAR_LENGTH){
						$parameters .= ", '" . $param . "'";				
						$param = "";
						$numParamsUsed++;					
						
						if ($numParamsUsed > self::MAX_NUM_ADMIN_PARAMS){
							/* The length of the replicate names is too long for the Admin API. */
							$task["SUCCESS"] = false;
							$task["RESULT_MESSAGE"] = $this->idsadmin->lang('ExceededAdminAPI');
							return $task;
						}
					}
				}
				else {
					/* All the replicates have been added. */
					$parameters .= ", '" . $param . "'";				
				}					
				$i++;
			}// while
			}
		$task['PARAMETERS'] = $parameters;
		$task["COMMENTS"] = "OAT ER modify replicate set job";

		$result = $this->executeSQLAdminAPICommand($task);
		
		/* the following is used in the flex result handler */
		if ( (strncasecmp($parameters, '\'--add', 6)) == 0) {
			$result['COMMAND_TYPE'] = 'ADD';
		} else if ( (strncasecmp($parameters, '\'--del', 6)) == 0) {
			$result['COMMAND_TYPE'] = 'DELETE';
		} else {
			$result['COMMAND_TYPE'] = 'FREQUENCY';
		}
		
		return $result;
	}

	/* Given a serialized list of servers find the list of eligible servers
	 * to select from depending on the connection/replication status.
	 *
	 * - $servList[0] is the selected server in the Flex UI
	 */
    public function getServControlServers($servList, $flag) {

    	$servList = unserialize($servList);
    	
    	if ($flag == "suspend" || $flag == "resume" || $flag == "enable") {
	    	
	    	if (count($servList) <= 1)
	    	{
	    		// If there is only one server in the domain (the one selected in the Flex UI),
	    		// don't bother to query, there are no other available servers in the domain
	    		$available_servers = array();
	    		return $available_servers;
	    	}

	    	/* Construct a distributed query like:
	    	 *	SELECT a1.servstate as g_two, a2.servstate as g_three, a3.servstate as g_four
			 *	FROM sysmaster@g_two:syscdrs a1, sysmaster@g_three:syscdrs a2, sysmaster@g_four:syscdrs a3
			 *	WHERE a1.servname = 'g_one' and a2.servname = 'g_one' and a3.servname = 'g_one';
			 */
			$select_stmt = "SELECT ";
			$from_stmt = "FROM ";
			$where_stmt = "WHERE ";
			for ($i=1; $i<count($servList) ; $i++) {
				$select_stmt .= "a{$i}.servstate AS {$servList[$i]}";
				$from_stmt   .= "sysmaster@{$servList[$i]}:syscdrs a{$i}";
				$where_stmt  .= "a{$i}.servname = '$servList[0]'";
				
				if ($i != count($servList)-1) {
					$select_stmt .= ", ";
					$from_stmt   .= ", ";
					$where_stmt  .= " AND ";
				} else {
					$select_stmt .= " ";
					$from_stmt   .= " ";
					$where_stmt  .= ";";
				}		
			}
			
			$query = $select_stmt . $from_stmt . $where_stmt;
			
			$serverInfo = $this->doDatabaseWork ( $query, "sysmaster");
			
		} else if ($flag == "connect" || $flag == "disconnect") {
		
			$serverInfo = $this->whatNodesOughtIBeConnectedTo($servList[0]);
			//error_log("serverInfo is:". var_export($serverInfo,true));
			$available_servers = array();	
		}
		
		/* Since the keys are the server names send the relevant ones back
		 */
		switch ($flag)
        	{
        		case "connect":
        			for ($i=0; $i<count($serverInfo) ; $i++) {
        				if ($serverInfo[$i]['CNNSTATE'] == 'X') {
        					array_push($available_servers, trim($serverInfo[$i]['SERVNAME']));
        				}
        			}
        			break;
        		case "disconnect":
        			for ($i=0; $i<count($serverInfo) ; $i++) {
        				if ($serverInfo[$i]['CNNSTATE'] == 'C') {
        					array_push($available_servers, trim($serverInfo[$i]['SERVNAME']));
        				}
        			}
        			break;
        		case "suspend":
        			$available_servers = array_keys(array_change_key_case($serverInfo[0]), "A");
        			break;
        		case "resume":
        			$available_servers = array_keys(array_change_key_case($serverInfo[0]), "S");
        			break;
        		case "enable":
        			$available_servers = array_keys(array_change_key_case($serverInfo[0]), "U");
        			break;        			
        	} 
		
		return $available_servers;
    }
    
    /* Execute:
     *		- suspend/resume/connect/disconnect/start/stop cdr commands
     */
    public function execServControlCmd($command, $parameters) {
    
    	$result = array();
		
		// comma between command and parameters argument
		$command .= ',';
		
		// Set the arguments to pass to SQL Admin API procedure
		$task = array();
		$task["COMMAND"]    = $command;
		$task['PARAMETERS'] = $parameters;
		$task["COMMENTS"]   = "OAT ER {$command} job";

		$result = $this->executeSQLAdminAPICommand($task);
		
		return $result;  
    }
    
	public function deleteServer ( $server 
	                             , $anotherServer = null
	                             , $options       = null )
    	{
    	/*
    	 * First, issue the command against the deleted server 
    	 */
    	$task = array ( );
    	$task [ 'COMMAND' ]    = "'cdr delete server',";
    	$task [ 'PARAMETERS' ] = "'--connect={$server} {$server}'";
    	
    	$task = $this->executeSQLAdminAPICommand ( $task );
    	//error_log ( 'deleteServer 1: ' . var_export ( $task, true ) );
    	
    	/*
    	 * Second, issue the same command against another server
    	 */
    	if ( $task [ 'SUCCESS' ] == true && $anotherServer != null )
	    	{
	    	$task = array ( );
    		$task [ 'COMMAND' ]    = "'cdr delete server',";
	    	$task [ 'PARAMETERS' ] = "'--connect={$anotherServer} {$server}'";
	    	
    		$task = $this->executeSQLAdminAPICommand ( $task );
    		//error_log ( 'deleteServer 2: ' . var_export ( $task, true ) );
	    	}
    	
    	return $task;
        }
        
   /**************************************************************************
    * Private functions
    * - The functions in this section are all private and not-directly 
    *    accessible via SOAP services.
    *************************************************************************/
		
	private function getTemplateReplicates ( $replicateSetID )
	{
		$query = "SELECT m.replid"
		       . "     , m.flags"
		       . "     , TRIM ( m.tabserver ) AS tabserver"
		       . "     , TRIM ( m.tabdb ) AS tabdb"
		       . "     , TRIM ( m.tabowner ) AS tabowner"
		       . "     , TRIM ( m.tabname ) AS tabname"
		       . "  FROM replsetpartdef p"
		       . "     , OUTER ( mastered_replicates m, OUTER repdef r )"
		       . " WHERE p.replsetid = {$replicateSetID}"
               . "   AND p.repid     = m.replid"
               . "   AND m.replid    = r.repid";
               
        $rows = $this->doDatabaseWork ( $query, self::SYSCDR );
        
        $xml = '';
        foreach ( $rows as $row )
        	{
       		$xml .= "<participant"
       		     .  " replid=\""    . $row['REPLID'   ] . "\""
       		     .  " flags=\""     . $row['FLAGS'    ] . "\""
       		     .  " tabserver=\"" . $row['TABSERVER'] . "\""
       		     .  " tabdb=\""     . $row['TABDB'    ] . "\""
       		     .  " tabowner=\""  . $row['TABOWNER' ] . "\""
       		     .  " tabname=\""   . $row['TABNAME'  ] . "\""
       		     .  "></participant>";
        	}
        return $xml;
	}
		
	/** 
	 * Build an XML node that contain the details a replicate check/sync.
	 * If no replicateName is provided queries are performed as if
	 * it was a check/sync of a replicate and this is returned:
	 *    <job...>
	 *       <participant.../>
	 *       ...
	 *     </job>
	 *     
	 * If a replicateName is provided queries are performed as if 
	 * this was a check/sync of a replicate set, in which replicateName 
	 * was a member.  This is returned: 
	 *    <repljob...>
	 *       <participant.../>
	 *       ...
	 *     </repljob>
	 * 
	 * Parameters:
	 *    $job : an array of <job name>, <job type>
	 */
	private function buildReplJobNode($jobID, $replicateName=null) {
		$jobName = $jobID[0];
		$jobType = $jobID[1];

		$result = "";
		if ($replicateName == null)
			$result = "<job"; 			
		else 
			$result = "<repljob";			

		/* Query about the job */
		$tmp = $this->getCheckSyncReplicateJobInfo($jobID, $replicateName);
	
	
		/* Who is the master? The first row in the replnode_node_master table for this job */
		$tmp2 = $this->getCheckSyncJobMaster($jobID);
		
		/* Query about the replicate's participants */
		$qry = "SELECT TRIM(n.replnode_node_name) as group,"
			. " TRIM(p.db) as table_db,"
			. " TRIM(n.replnode_table_owner) as table_owner,"
			. " TRIM(n.replnode_table_name) as table_name,"
			. " n.replnode_row_count as row_count,"
			. " n.replnode_extra_rows as extra_rows,"
			. " n.replnode_missing_rows as missing_rows,"
			. " n.replnode_mismatched_rows as mismatched_rows,"
			. " n.replnode_extra_child_rows as extra_child_rows,"
			. " n.replnode_processed_rows as rows_processed,"
			. " n.replnode_order as order"
			. " FROM replcheck_stat_node n, replcheck_stat s, partdef p, repdef r"
			. " WHERE n.replnode_replcheck_id = s.replcheck_id" 
			. "   AND p.repid = r.repid"
			. "   AND r.repname = s.replcheck_replname"
			. "   AND p.servid = n.replnode_id"
			. "   AND s.replcheck_name = ?"
			. "   AND s.replcheck_type = ?"
			;
		$tmp3;
		if ($replicateName == null) {
			$qry .= " ORDER by order";
			$tmp3 = $this->doPreparedDatabaseWork($qry, array($jobName, $jobType), self::SYSCDR);	
		}
		else {
			$qry .= " AND s.replcheck_replname = ?"
				  . " ORDER by order";
			$tmp3 = $this->doPreparedDatabaseWork($qry, array($jobName, $jobType, $replicateName), self::SYSCDR);
		}
					
		
		if($tmp2 == null || $tmp2['MASTER'] == null)
		{
			$tmp2 = Array();
			$tmp2['MASTER'] = "unknown";
		}
		
		/* Build the XML string */
		$tmp = $tmp[0];
		$tmp['PENDING_ROWS'] = $tmp['NUMROWS'] - $tmp['ROWS_PROCESSED'];
		$percentComplete = $this->calculatePercentComplete($tmp['STATUS'], $tmp['NUMROWS'], $tmp['ROWS_PROCESSED']);			
		$result .= " name=\""             . $tmp['NAME']                                . "\""
				 . " type=\""		      . $this->convertTypeToWord($tmp['TYPE']) . "\""
				 . " replicate=\""        . $tmp['REPLICATE']                           . "\""
				 . " time_started=\""     . $tmp['TIME_STARTED']                        . "\""
				 . " time_completed=\""   . $tmp['TIME_COMPLETED']                      . "\""
				 . " status=\""           . $tmp['STATUS']                              . "\""
				 . " totalRows=\""        . $tmp['NUMROWS']                             . "\""
				 . " rowsProcessed=\""    . $tmp['ROWS_PROCESSED']                      . "\""
				 . " pendingRows=\""	  . $tmp['PENDING_ROWS']						. "\""
				 . " time_remaining=\""   . $tmp['ESTIMATED_DURATION']                  . "\""
				 . " duration=\""         . $tmp['DURATION']                            . "\""						
				 . " percent_complete=\"" . strval($percentComplete)                    . "\""
				 . " master=\""           . $tmp2['MASTER']                             . "\""
				 . " current_time=\""     . $tmp['CURRENT_TIME']                        . "\""
				 . ">";
		
		foreach ($tmp3 as $row) {
			/*
			 * E.g. <participant group="grp10" table="db2:informix.t1" rows_to_scan="1" extra_rows="2" 
			 *       missing_rows="3" mismatched_rows="4" extra_child_rows="5" rows_processed="6"/>
			 */
			$participant = "<participant"
						. " group=\""            . $row['GROUP']            . "\""
						. " table=\""            . $row['TABLE_DB'] . ":" . $row['TABLE_OWNER'] . "." . $row['TABLE_NAME'] . "\""
						. " rows_to_scan=\""     . $row['ROW_COUNT']        . "\""
						. " extra_rows=\""       . $row['EXTRA_ROWS']       . "\""
						. " missing_rows=\""     . $row['MISSING_ROWS']     . "\""
						. " mismatched_rows=\""  . $row['MISMATCHED_ROWS']  . "\""
						. " extra_child_rows=\"" . $row['EXTRA_CHILD_ROWS'] . "\""
						. " rows_processed=\""   . $row['ROWS_PROCESSED']   . "\""
				        . "/>";
			$result .= $participant;				
		}
		
		if ($replicateName && strlen($replicateName) > 0) 
			$result .= "</repljob>";
		else 
			$result .= "</job>";
		return $result;
	}

	/**
	 * Determine the master node of a check/sync job.
	 * This is for both replicate and replicate set jobs.
	 */
	private function getCheckSyncJobMaster($jobID){		
		$jobName = $jobID[0];
		$jobType = $jobID[1];

		/* Who is the master? The first row in the replnode_node_master table for this job */
		$qry = "SELECT n.replnode_node_name as master"
			. " FROM replcheck_stat_node n, replcheck_stat s" 
		    . " WHERE n.replnode_replcheck_id = s.replcheck_id" 
		    . "   AND s.replcheck_name = ?"
			. "   AND s.replcheck_type = ?"
			. "   AND n.replnode_order = 1";
		$tmp = $this->doPreparedDatabaseWork($qry, array($jobName, $jobType), self::SYSCDR);
		
		if($tmp != null && $tmp[0] != null)
			$result = $tmp[0];
		else 
		{
			$result = array();
			$result['MASTER'] = "";
		}
		return $result;
	}

	private function getNeverRunScheduledCheckSyncSetJobs()
	{
		//SOAP requires variables to be declared.
		$results = ""; //this string will contain the jobs in XML format
		$job = "";  //We must declare $job outside the loop (SOAP)		

		$sql = "SELECT tk_id, tk_description, tk_execute, tk_start_time, tk_next_execution, CURRENT YEAR TO SECOND as current_time  
		FROM " . self::PH_TASK . " WHERE "
		. " tk_total_executions = 0" 
		;

		$rows = $this->doDatabaseWork($sql, self::SYSADMIN);
		
		foreach($rows as $row)
      	{
      		$tmp = $this->retrieveNameAndType($row['TK_DESCRIPTION']);
			if($tmp == null)
				continue;
			else
			{
				$row['NAME'] =  $tmp['NAME'];
				$row['TYPE'] = $tmp['TYPE'];
			}
			 
			$tmp = $this->retrieveReplsetName($row['TK_EXECUTE']);
			if($tmp == null)
				continue;
			else
				$row['REPLICATESET'] = $tmp;
			
			$tmp = $this->retrieveMaster($row['TK_EXECUTE']);
			if($tmp == null)
				$row['MASTER'] =  "";
			else
				$row['MASTER'] = $tmp;		
      		
      		$job = "<job"
				  . " name=\""         				. $row['NAME']          					. "\""
				  . " type=\""         				. $this->convertTypeToWord($row['TYPE'])	. "\""
				  . " replicateset=\"" 				. $row['REPLICATESET'] 						. "\""
				  . " current_time=\"" 				. $row['CURRENT_TIME'] 						. "\""
				  . " master=\""       				. $row['MASTER']							. "\""
		  		  . " time_started=\""    			. $row['TK_NEXT_EXECUTION']					. "\""
				  . " time_completed=\""  									 					. "\""
				  . " status=\""          			. self::STATUS_SCHEDULED_NEVER_BEEN_RUN     . "\""				  
				  . " repls_in_set=\""            	     										. "\""
				  . " time_remaining=\""          	                 							. "\""
				  . " duration=\""         							                      		. "\""
				  . " js_id=\""         		                       							. "\""
				  . " cmd_number=\""         						                      		. "\""
				  . " tk_id=\""         			. $row['TK_ID']				                . "\""
				  . " tk_next_execution=\""     	. $row['TK_NEXT_EXECUTION']					. "\""
				  . " completed_replicates_numb=\""  		 									. "\""
				  . " pending_replicates_numb=\""												. "\""
				  . " running_replicates_numb=\""     											. "\""
				;
		   $job .=  "></job>";
		   $results .= $job;
      	}
		return $results;
	}
	
	private function getNeverRunScheduledCheckSyncJobs()
	{
		//SOAP requires variables to be declared.
		$results = ""; //this string will contain the jobs in XML format
		$job = "";  //We must declare $job outside the loop (SOAP)
				
		$sql = "SELECT tk_id, tk_description, tk_execute, tk_start_time, tk_next_execution, CURRENT YEAR TO SECOND as current_time  
		FROM " . self::PH_TASK . " WHERE "
		. " tk_total_executions = 0" 
		;

		$rows = $this->doDatabaseWork($sql, self::SYSADMIN);

		foreach($rows as $row)
      	{
      		$tmp = $this->retrieveNameAndType($row['TK_DESCRIPTION']);
			if($tmp == null)
				continue;
			else
			{
				$row['NAME'] =  $tmp['NAME'];
				$row['TYPE'] = $tmp['TYPE'];
			}
			 
			$tmp = $this->retrieveReplName($row['TK_EXECUTE']);
			if($tmp == null)
				continue;
			else
				$row['REPLICATE'] = $tmp;
			
			$tmp = $this->retrieveMaster($row['TK_EXECUTE']);
			if($tmp == null)
				$row['MASTER'] =  "";
			else
				$row['MASTER'] = $tmp;		
      		
      		$job = "<job"
				  . " name=\""         			. $row['NAME']          						. "\""
				  . " type=\""         			. $this->convertTypeToWord($row['TYPE'])		. "\""
				  . " replicate=\"" 			. $row['REPLICATE'] 							. "\""
				  . " time_started=\""    														. "\""
				  . " time_completed=\""  									 					. "\""
				  . " status=\""          		. self::STATUS_SCHEDULED_NEVER_BEEN_RUN         . "\""
				  . " time_remaining=\""          	                 							. "\""
				  . " duration=\""         							                      		. "\""
				  . " percent_complete=\"" 		 	 											. "\""  
				  . " current_time=\"" 			. $row['CURRENT_TIME'] 							. "\""
				  . " master=\""       			. $row['MASTER']								. "\""
				  . " js_id=\""         		                       							. "\""
				  . " cmd_number=\""         						                      		. "\""
				  . " tk_id=\""         		. $row['TK_ID']				                    . "\""
				  . " tk_next_execution=\""     . $row['TK_NEXT_EXECUTION']						. "\""
				;
		   $job .=  "/>";
		   $results .= $job;
      	}
		return $results;
	}
	
	
	/**
	 * 
	 * get all case 4 replicate set jobs.
	 * Get information of replicate set jobs that failed and never written to syscdr.
	 * This function returns an xml string containing info about jobs.
	 * @return 
	 */
	private function getCheckSyncFailedSetJobsNotWrittenToSYSCDR()
	{
		//SOAP requires variables to be declared.
		$results = ""; //this string will contain the jobs in XML format
		$job = "";  //We must declare $job outside the loop (SOAP)
		
		$needed_tables_exist = $this->doJobStatusHistoryTablesExist();
		if(!$needed_tables_exist)	//if the tables we need do not exist
			return "";			//return an empty string. This is important to do in case job_status is not created yet.

		// get info about all tasks that ran, exited with non-zero, and didn't insert syscdr
		$sql = "SELECT js_id, cmd_ret_status, cmd_number, js_comment, js_command, js_start, js_done, js_done - js_start AS duration, CURRENT YEAR TO SECOND as current_time  
		FROM job_status j, command_history c WHERE"
		. " ABS(js_result) = cmd_number " // join
		. " AND cmd_ret_status <> 0" // exited with non-zero 
		;

		$rows = $this->doDatabaseWork($sql, self::SYSADMIN);
		
		foreach($rows as $row)
      	{
      		$tmp = $this->retrieveNameAndType($row['JS_COMMENT']);
			if($tmp == null)
				continue;
			else
			{
				$row['NAME'] =  $tmp['NAME'];
				$row['TYPE'] = $tmp['TYPE'];
			}
			
			/**
			 * out of sync check jobs with w/o repair option should not count as failed job. 
			 */
			if($this->isCheck($row['TYPE']) && !$this->hasRepairOption($row['JS_COMMENT']))
			{
				if($row['CMD_RET_STATUS'] == self::OUT_OF_SYNC_REPLSET_ERR) //if out of sync error, then don't count it as a failed job
					continue;
			}
			 
			$tmp = $this->retrieveReplsetName($row['JS_COMMAND']);
			if($tmp == null)
				continue;
			else
				$row['REPLICATESET'] = $tmp;
			
			$tmp = $this->retrieveMaster($row['JS_COMMAND']);
			if($tmp == null)
				$row['MASTER'] =  "";
			else
				$row['MASTER'] = $tmp;		
      		
      		$job = "<job"
				  . " name=\""         				. $row['NAME']          						. "\""
				  . " type=\""         				. $this->convertTypeToWord($row['TYPE'])		. "\""
				  . " replicateset=\"" 				. $row['REPLICATESET'] 							. "\""
				  . " current_time=\"" 				. $row['CURRENT_TIME'] 							. "\""
				  . " master=\""       				. $row['MASTER']								. "\""
		  		  . " time_started=\""    			. $row['JS_START']          					. "\""
				  . " time_completed=\""  			. $row['JS_DONE']			 					. "\""
				  . " status=\""          			. self::STATUS_ABORTED_WITHOUT_SYSCDR        	. "\""					  
				  . " repls_in_set=\""            	     											. "\""
				  . " time_remaining=\""          	                 								. "\""
				  . " duration=\""         			. $row['DURATION']                       		. "\""
				  . " percent_complete=\"" 		 	 												. "\"" 
				  . " js_id=\""         			. $row['JS_ID']                       			. "\""
				  . " cmd_number=\""         		. $row['CMD_NUMBER']                       		. "\""	  
				  . " tk_id=\""         									                    	. "\""
				  . " tk_next_execution=\""     							  						. "\""	
				  . " completed_replicates_numb=\"" 		 										. "\""
				  . " pending_replicates_numb=\""   												. "\""
                  . " running_replicates_numb=\""   												. "\""  
				;
		   $job .=  "></job>";
		   $results .= $job;
      	}
		return $results;	  
	}

	/**
	 * Retrive name and type of a job from a comment in either ph_task or job_status. 
	 * @return an array containing the name and type of a job.
	 * @param object $js_comment
	 */
	private function retrieveNameAndType($js_comment)
	{
		$row = Array();
		preg_match( "/OAT ER(.*)job -(.*)/", $js_comment, $matches);
		if($matches == null || $matches[1] == null || $matches[2] == null) //filter out irrelevant data
			$row = null;
		else{
			$row['NAME'] =  trim($matches[2]);
			$row['TYPE'] = $this->convertTypeToLetter($matches[1]);
		}
		return $row;
	}
	
	/**
	 * Retrive name of replicate set from command
	 * @return name of replicate set
	 * @param object $js_command
	 */
	private function retrieveReplsetName($js_command)
	{
		$result = "";
		preg_match( "/.*--replset=(.*?) .*/", $js_command, $matches);
		if($matches == null || $matches[1] == null) //only get replicateset jobs info
			$result = null;
		else
  			$result =  $matches[1];
		return $result;
	}
	
	/**
	 * Tell whether the check/sync job included the repair option
	 * @return true if it has repair optionl; false otherwise.
	 * @param object $js_command
	 */
	private function hasRepairOption($js_command)
	{
		$result = "";
		preg_match( "/.*--repair .*/", $js_command, $matches);
		if($matches == null || $matches[1] == null)
			$result = false;
		else
  			$result =  true;
		return $result;
	}
		
	/**
	 * Rtrieve name of master node from command
	 * @return name of master node
	 * @param object $js_command
	 */	 
	private function retrieveMaster($js_command)
	{		
			$result = "";
			preg_match( "/.*--master=(.*?) .*/", $js_command, $matches);
			if($matches == null || $matches[1] == null) 
				$result =  null;
			else
      			$result =  $matches[1];
			return $result;
	} 
	
	/**
	 * Retrive the replicate name from a string representing some command
	 * @return name of replicate
	 * @param object $js_command
	 */
	private function retrieveReplName($js_command)
	{
		$result = "";
		preg_match( "/.*--repl=(.*?) .*/", $js_command, $matches); 
			if($matches == null || $matches[1] == null) //only get replicate jobs info
				$result == null;
			else
      			$result =  $matches[1];

		return $result;
	}

	/** 
	 * Build the <job> element for the given replicate set job.
	 * If no job name is given, build nodes for all replicate set jobs.
	 * Returns:
	 *   <job.../>
	 *   ...
	 */
	private function buildReplSetJobNodes($jobID=null){
		$result = "";
	
		$tmp = $this->getCheckSyncReplicateSetJobInfo($jobID);	

		/*
		 * Build a <job> node for each set of rows about a job.
		 * Jobs of replicate sets are comprised of a row for each replicate.
		 */
		$job = "";
		$numRepls = 0;                   // # of repls in job
		$timeStarted = "";               // from the first row for the job
		$status = self::STATUS_DEFINED;  // status of entire job, a composite of replicate sub-job statuses			
		$totalRows = 0;
		$rowNum = 0;
		$completedRepls = 0;
		$runningRepls = 0;
		
		foreach ($tmp as $row){	
		
			/* do this for the first repl in job */
			if ($numRepls == 0) {				
				$tmp2 = $this->getReplicateSetNameForJob(array($row['NAME'], $row['TYPE']));
				
				$job .= "<job"
					  . " name=\""         . $row['NAME']          					. "\""
					  . " type=\""         . $this->convertTypeToWord($row['TYPE']) . "\""
					  . " replicateset=\"" . $tmp2['REPLICATESET'] 					. "\""
					  . " current_time=\"" . $tmp2['CURRENT_TIME'] 					. "\""
					  . " replcheck_id=\"" . $row['REPLCHECK_ID']           		. "\""
					  . " js_id=\""                                 				. "\""
					  . " cmd_number=\""                                 			. "\""
					  . " tk_id=\""         					                    . "\""
					  . " tk_next_execution=\""         		                    . "\""
					  ;
					  
				if ($jobID != null) {
					$tmp3 = $this->getCheckSyncJobMaster($jobID);
					$job .= " master=\""   . $tmp3['MASTER']       . "\"";
				}

				$timeStarted = $row['TIME_STARTED'];			
				$status = $row['STATUS'];
			}				
			
			if($row['STATUS'] == self::STATUS_COMPLETED_INSYNC || $row['STATUS'] == self::STATUS_COMPLETED_OUTOFSYNC)
				$completedRepls++;
		
			switch($status)
			{
				case self::STATUS_DEFINED:
				case self::STATUS_COMPLETED_INSYNC:
					if ($row['STATUS'] == self::STATUS_RUNNING || $row['STATUS'] == self::STATUS_COMPLETED_OUTOFSYNC)
					{
						$status = $row['STATUS'];
						
					}
					break;	
				case self::STATUS_COMPLETED_OUTOFSYNC:
				case self::STATUS_RUNNING:
					if ($row['STATUS'] == self::STATUS_RUNNING)
					{
						$status = $row['STATUS'];
					}
					break;
				default: //failure cases dominates because if a job on one replicate fails, the entire replicate set job fails
					$status = $row['STATUS'];
					break;
			}
				
			if ($row['STATUS'] == self::STATUS_RUNNING)
			{		
				$runningRepls++;
			}
			
			$numRepls++;

			/* do this after handling the last repl in job */
			if ((sizeof($tmp) == ($rowNum+1))                     // there are no more rows to process
				 || ($tmp[$rowNum+1]['NAME'] != $row['NAME'])     // the next row is for a new job name
				 || ($tmp[$rowNum+1]['TYPE'] != $row['TYPE'])     // the next row is for a new job type
				)
			{
				$pendingRepls = $numRepls - ($completedRepls + $runningRepls);
					
				$job .= " time_started=\""    			. $timeStarted           	. "\""
					  . " time_completed=\""  			. $row['TIME_COMPLETED'] 	. "\""
					  . " status=\""          			. $status                	. "\""
					  . " repls_in_set=\""    			. $numRepls              	. "\""
					  . " time_remaining=\""                           			 	. "\""
					  . " duration=\""                       		   			 	. "\""
					  . " completed_replicates_numb=\"" . $completedRepls 		 	. "\""
					  . " pending_replicates_numb=\""	. $pendingRepls				. "\""
					  . " running_replicates_numb=\""	. $runningRepls 			. "\""
					  . " percent_complete=\"" 			. $this->calculatePercentComplete($status, $numRepls, $completedRepls) . "\"" 
					;
				
				// if job is a failed or scheduled job, don't get it from syscdr (we should get it from sysadmin)
				
				if ($status == self::STATUS_RUNNING 			||
					$status == self::STATUS_DEFINED 			||  
					$status == self::STATUS_COMPLETED_INSYNC 	|| 
					$status == self::STATUS_COMPLETED_OUTOFSYNC	)
				{
					$result .= $job . "></job>";	
				}
				
				// prepare for next job
				$job = "";
				$numRepls = 0;
				$timeStarted = "";
				$status = self::STATUS_DEFINED;
				$totalRows = 0;
				$completedRepls = 0;
				$runningRepls = 0;
			}
			
			$rowNum++;
		}
		
		if($jobID == null)
		{
			$result .= $this->getCheckSyncFailedSetJobsNotWrittenToSYSCDR();
			$result .= $this->getNeverRunScheduledCheckSyncSetJobs();
		}
		
		return $result;
	}
	
	/**
	 * Obtain the name of the replicate set on which the job was run.
	 */
	private function getReplicateSetNameForJob($jobID){
		$jobName = $jobID[0];
		$jobType = $jobID[1];
	
		// What is the name of one replset that contains only and all the repls in a given job?		
		        // select all replsets that contain at least the repls in the job
		$qry = "SELECT FIRST 1" 
			. " TRIM(a.replsetname) as replicateset"
			. ",CURRENT YEAR TO SECOND as current_time"
			. " FROM replsetdef a, replsetpartdef b"
			. " WHERE a.replsetid = b.replsetid"
			. " AND b.repid IN ("
				    // select the ids of all the repls in the job
				. " SELECT b.repid"
				. " FROM replcheck_stat a, repdef b" 
				. " WHERE a.replcheck_name = ?"
				. " AND a.replcheck_type =?"
				. " AND a.replcheck_replname = b.repname"
			. " )"
			. " AND a.replsetname IN ("
				    // select all replsets that have exactly <x> many repls
				. " SELECT a.replsetname"
				. " FROM replsetdef a, replsetpartdef b, repdef c"				
				. " WHERE a.replsetid = b.replsetid"
				. "  AND b.repid = c.repid"
				. "  AND BITAND(c.flags, '0x2000000') = 0"  // do not include shadow replicates
				. " GROUP BY 1"
				. " HAVING COUNT(*) = ("
					. " SELECT COUNT(*) FROM replcheck_stat WHERE replcheck_name = ? AND replcheck_type = ?"
				. " )"
			. " )" 
			. " GROUP BY 1"
			. " HAVING COUNT(*) = ("
				. " SELECT COUNT(*) FROM replcheck_stat WHERE replcheck_name = ? AND replcheck_type = ?"
			. " )"
			. " ORDER BY 1" 
			;
		
		$tmp = $this->doPreparedDatabasework($qry, array($jobName, $jobType, $jobName, $jobType, $jobName, $jobType), self::SYSCDR);

		if (count($tmp) == 1){
			$result = $tmp[0];	
		}
		else {
			/* the replicate set was deleted, so the name is unknown */
			$qry = "SELECT FIRST 1" 
				. " CURRENT YEAR TO SECOND as current_time"
				. " FROM servdef"
				;
			$tmp = $this->doDatabasework($qry, self::SYSCDR);			
			$result['REPLICATESET'] = self::ERROR_MESSAGE_FOR_REPLICATESET_NAME_NOT_FOUND;
			$result['CURRENT_TIME'] = $tmp[0]['CURRENT_TIME'];
		}

		return $result;
	}

	/**
	 * Query for check/sync jobs of replicatesets.
	 * If a jobName is provided info about only that job will be returned.  
	 * Otherwise info about all jobs is returned.   
	 */
	private function getCheckSyncReplicateSetJobInfo($jobID=null){
		
		$qry = "SELECT"
			. " TRIM(replcheck_name) as name"
			. ",TRIM(replcheck_replname) as replicate"
			. ",replcheck_type as type"
			. ",replcheck_numrows as numrows"
			. ",replcheck_rows_processed as rows_processed"
			. ",replcheck_status as status"
			. ",replcheck_start_time as time_started"
			. ",replcheck_end_time as time_completed"
			. ",'' as master"
			. ",'' as replicateset"
			. ",'' current_time"
			. ", replcheck_id"
			. ",CASE WHEN replcheck_status IN ('C', 'F') THEN 1"
			. "      WHEN replcheck_status IN ('R')      THEN 2"
			. "      WHEN replcheck_status IN ('D')      THEN 3"
			. "      ELSE 4"
			. " END as myStatusOrdering"
			. " FROM " . self::REPLCHECK_STAT
			;

		if ($jobID == null) {
			/* query for all jobs */
			$qry .= " WHERE replcheck_name IN ("
					       // select the names of all the jobs on replsets
						. " SELECT TRIM(replcheck_name)"
						. " FROM " . self::REPLCHECK_STAT
						. " WHERE replcheck_type = 'C'"
						. " AND replcheck_scope = 'S'"	//make sure this is a replicateset job
						. " GROUP BY replcheck_name"
						. " UNION"
						. " SELECT TRIM(replcheck_name)"
						. " FROM " . self::REPLCHECK_STAT
						. " WHERE replcheck_type = 'S'"
						. " AND replcheck_scope = 'S'"	//make sure this is a replicateset job
						. " GROUP BY replcheck_name"
				  . " )"
				  . " ORDER BY" 
				  . "  replcheck_name"       // job name
				  . ", replcheck_type"       // job type
				  . ", myStatusOrdering"     // status, completed to not started
				  . ", replcheck_start_time" // oldest to youngest
				  . ", replcheck_replname"   // name
				  ;
			$result = $this->doDatabaseWork($qry, self::SYSCDR);
		}
		else {
			$jobName = $jobID[0];
			$jobType = $this->convertTypeToLetter($jobID[1]);
			/* query for a single job */
			$qry .= " WHERE replcheck_name = ?"
				  . " AND replcheck_type = ?"
				  . " AND replcheck_scope = 'S'"	//make sure this is a replicateset job
				  . " ORDER BY" 
				  . "  replcheck_name"       // job name
				  . ", myStatusOrdering"     // status, completed to not started
				  . ", replcheck_start_time" // oldest to youngest
				  . ", replcheck_replname"   // name
				  ;
			$result = $this->doPreparedDatabaseWork($qry, array($jobName, $jobType), self::SYSCDR);
		}

		return $result;		
	}

	/**
	 * Calculate how complete a check/sync job is
	 */
	private function calculatePercentComplete($status, $numRows, $rowsProcessed){
		$percentComplete = 0;
		if ($status == self::STATUS_COMPLETED_INSYNC || $status == self::STATUS_COMPLETED_OUTOFSYNC)
			$percentComplete = 100;
		else if ($status == self::STATUS_RUNNING && $numRows > 0)
			$percentComplete = intval(($rowsProcessed / $numRows) * 100);
		return $percentComplete;				
	}
	
	/**
	 * Query for check/sync jobs of replicates.  
	 * If a jobName is provided info about only that job will be returned.  
	 * Otherwise info about all jobs is returned.  
	 */
	private function getCheckSyncReplicateJobInfo($jobID=null, $replicateName=null){		
		$qry = "SELECT TRIM(replcheck_name) as name, TRIM(replcheck_replname) as replicate,"
			. " replcheck_type as type, replcheck_status as status, '' as master," 
			. " replcheck_start_time as time_started, replcheck_end_time as time_completed,"
			. " replcheck_numrows as numrows, replcheck_rows_processed as rows_processed, replcheck_id,"
			. " CASE WHEN replcheck_status in ('D', 'C', 'F')"       // calculate the estimated duration on the server
			. "          THEN NULL"
			. "      WHEN (replcheck_numrows <= 0 OR replcheck_rows_processed <= 0)"
			. "          THEN NULL"
			. "      ELSE CAST( ((CURRENT - replcheck_start_time) / (replcheck_rows_processed / replcheck_numrows)) AS INTERVAL DAY TO SECOND)"
			. " END as estimated_duration,"
			. " CASE WHEN replcheck_status in ('C', 'F')"
			. "          THEN replcheck_end_time - replcheck_start_time"
			. "     ELSE NULL"
			. " END as duration,"
			. " CURRENT YEAR TO SECOND as current_time"
			. " FROM " . self::REPLCHECK_STAT ;
		
		if ($jobID == null) {
			/* query for all jobs */
			$qry .= " WHERE replcheck_name IN ("  // only report on jobs that check/sync'ed a single replicate
			      . "   SELECT replcheck_name FROM " . self::REPLCHECK_STAT
				  . "   WHERE replcheck_type = 'C'"
				  . "	AND replcheck_scope = 'R'"	//make sure this is a replicate (not a replicate set) job
  				  . "   GROUP BY replcheck_name"
				  . "   UNION"
			      . "   SELECT replcheck_name FROM " . self::REPLCHECK_STAT				  
				  . "   WHERE replcheck_type = 'S'"
				  . "	AND replcheck_scope = 'R'"	//make sure this is a replicate (not a replicate set) job
  				  . "   GROUP BY replcheck_name"
				  . " )"
				  . "	ORDER BY name";
			$result = $this->doDatabaseWork($qry, self::SYSCDR);
			
		}
		else {
			/* query for a single job */
			$jobName = $jobID[0];
			$jobType = $this->convertTypeToLetter($jobID[1]);						
			if ($replicateName == null) {
				/* query for a single replicate job */
				$qry .= " WHERE replcheck_name = ?"
			 		  . " AND replcheck_type = ?"
					  . " AND replcheck_scope = 'R'"	//make sure this is a replicate job
					;
				$result = $this->doPreparedDatabaseWork($qry, array($jobName, $jobType), self::SYSCDR);
			}
			else {
				/* query for a single replicate in a replicateset job */
				$qry .= " WHERE replcheck_name = ?"
					  . " AND replcheck_type = ?"
					  . " AND replcheck_scope = 'S'"	//make sure this is a replicate set job
					  . " AND replcheck_replname = ?";	
				
				$result = $this->doPreparedDatabaseWork($qry, array($jobName, $jobType, $replicateName), self::SYSCDR);	
			}			
		}
		
		return $result;		
	}
	
	/**
	 * @return array containing ALL the check/sync jobs
	 * @param object $tmp1: case 1, 2, 3, and 5 jobs (obtained from syscdr
	 * @param object $tmp2: case 4 and 5 jobs (obtained from sysadmin)
	 * IMPORTANT: the order of arguments matter. 
	 */
	private function combineJobs($cases_1_2_3_5, $case4and5)
	{
		
		/*
		 * first combine all jobs that exist in either syscdr or sysadmin, but NOT in both (i.e. exclude their intersection).
		 * Those failed jobs will contain all case 1, case 2, case 3, and case 4 (because they don't exist in syscdr, but only in sysadmin) jobs and some 
		 * case 5 jobs that do not exist in sysadmin (such as case 5 jobs that were performed in the command line).
		 */
		$result = array_merge(array_udiff($cases_1_2_3_5, $case4and5, "compareCheckSyncJobs"), array_udiff($case4and5, $cases_1_2_3_5, "compareCheckSyncJobs"));

		/*
		 * Now we still have to take care of case 5 jobs that exist in both syscdr and sysadmin.
		 * $case5_intersect1 will contain the case 5 jobs of syscdr (and that exist in sysadmin as well).
		 * $case5_intersect2 will contain the case 5 jobs of sysadmin (and that exist in syscdr as well).
		 * Thus, $case5_intersect1 and $case5_intersect2 must contain the same jobs and thus they have
		 * a one-to-one rrelationship. 
		 * We will sort $case5_intersect1 and $case5_intersect2 so each job with index X in $case5_intersect1
		 * correspond to its equivalent job in $case5_intersect2 with index X.
		 */
		$case5_intersect1 = array_uintersect($cases_1_2_3_5, $case4and5, "compareCheckSyncJobs");
		usort($case5_intersect1,"compareCheckSyncJobs");
		$case5_intersect2 = array_uintersect($case4and5, $cases_1_2_3_5, "compareCheckSyncJobs");
		usort($case5_intersect2,"compareCheckSyncJobs");

		// now case5_intersect1 and case5_intersect2 must have a 1-1 relationship and they should contain the same (case 5) jobs
		$numb_of_case5_jobs = count($case5_intersect1);
		for($i = 0; $i < $numb_of_case5_jobs ; $i++) 
		{
		 	/* Replace the date information of each case 5 job of syscdr with the date information of its corresponding job in sysadmin
			 * because sysadmin is more likely to have these values. of course, for each job, if syscdr has the dates information,
			 * then sysadmin has those dates as well. Also, since the master replicate could not be found in syscdr and it is
			 * garenteed to be found in this particular case, we copy the value of the master as well.
			 */
			$case5_intersect1[$i]['TIME_STARTED'] = $case5_intersect2[$i]['TIME_STARTED'];
			$case5_intersect1[$i]['TIME_COMPLETED'] = $case5_intersect2[$i]['TIME_COMPLETED'];
			$case5_intersect1[$i]['DURATION'] = $case5_intersect2[$i]['DURATION'];
			$case5_intersect1[$i]['MASTER'] = $case5_intersect2[$i]['MASTER'];
		}
		// Since the case 5 jobs of syscdr contain more useful information, they will replace case 5 jobs of sysadmin.
		// merge the intersected case 5 jobs with all the other jobs.
		$result = array_merge($result, $case5_intersect1);		
		return $result;
	}
	
	/**
	 * @return array containing ALL the check/sync jobs. Case 5 jobs are taken only from sysadmin.
	 * @param object $syscdr_cases_1_2_3_5: case 1, 2, 3, and 5 jobs (obtained from syscdr
	 * @param object $sysadmin_cases_4_5: case 4 and 5 jobs (obtained from sysadmin)
	 * IMPORTANT: the order of arguments matter. 
	 */
	private function combineJobs2($syscdr_cases_1_2_3_5, $sysadmin_cases_4_5)
	{
		/*
		 * the failed jobs in syscdr are only case 5 jobs. This function filters out all failed jobs, so case 5 jobs will be filtered out.
		 */
		$syscdr_cases_1_2_3 = array_filter($syscdr_cases_1_2_3_5, "detectNonFailedJob") ;
																		
		/* array members must be defined otherwise SOP complains about it later. 
		 * jobs obtained from syscdr do not need any information from sysadmin, so we can skip cmd_number and js_id.
		 */	
		 $syscdr_cases_1_2_3_clean = Array();
		foreach($syscdr_cases_1_2_3 as $job)
		{
			$syscdr_cases_1_2_3_clean[] = array_merge($job, array("CMD_NUMBER" => "", "JS_ID" => ""));
		}
		$result = array_merge($syscdr_cases_1_2_3_clean, $sysadmin_cases_4_5);	//cases 1 2 3 4 5 combined	
		return $result;
	}
	
	/**
     * Convert job type from job type name to its symbol character. If the user provides the type as one character
     * symbol, the fnction keeps it one character (e.g. "check" --> "C" , "C" --> "C").
     * @return 
     * @param object $type
     */
	private function convertTypeToLetter($type)
	{
		$type = trim($type);
		if($type == self::CHECK_TYPE_WORD || $type ==  self::CHECK_TYPE)
      		$type = self::CHECK_TYPE;
		else if($type == self::SYNC_TYPE_WORD || $type ==  self::SYNC_TYPE)
      		$type = self::SYNC_TYPE;	
		else 
			$type = "";
		
		return $type;
	}
	
	private function isCheck($jobType)
	{
		return ($jobType == self::CHECK_TYPE || $jobType == self::CHECK_TYPE_WORD);
	}
	
	private function isSync($jobType)
	{
		return ($jobType == self::SYNC_TYPE || $jobType == self::SYNC_TYPE_WORD);
	}
	
	/**
	 * Convert job type from one character symbol to a word. If the user provides the type as a word,
	 * the function keeps it one character (e.g. "S" --> "sync", "sync" --> "sync").
	 * @return 
	 * @param object $type
	 */
	private function convertTypeToWord($type)
	{
		$type = trim($type);
		if($type == self::CHECK_TYPE_WORD || $type ==  self::CHECK_TYPE)
      		$type = self::CHECK_TYPE_WORD;
		else if($type == self::SYNC_TYPE_WORD || $type ==  self::SYNC_TYPE)
      		$type = self::SYNC_TYPE_WORD;	
		else 
			$type = "unknown";
		
		return $type;
	}
	
	/**
	 * get all case 4 replicate jobs
	 * Get information of jobs that failed and never written to syscdr. 
	 * return array of jobs that failed. 
	 */
	private function getCheckSyncFailedJobsNotWrittenToSYSCDR()
	{
		$needed_tables_exist = $this->doJobStatusHistoryTablesExist();
		if(!$needed_tables_exist)	//if the tables we need do not exist
			return Array();			//return an empty array. This is important to do in case job_status is not created yet.
		
		// get info about all tasks that ran, exited with non-zero, and didn't insert syscdr
		$sql = "SELECT js_id, cmd_number, cmd_ret_status, js_comment, js_command, js_start, js_done, js_done - js_start AS duration, CURRENT YEAR TO SECOND as current_time, '' AS replcheck_id   
		FROM job_status, command_history WHERE"
		. " ABS(js_result) = cmd_number " // join
		. " AND cmd_ret_status <> 0" // exited with non-zero
		;

		$rows = $this->doDatabaseWork($sql, self::SYSADMIN);

		/* those declarations are not important, but SOAP requires them */
		$result = Array(); 
	
		foreach($rows as $row)
      	{
      		$tmp = $this->retrieveNameAndType($row['JS_COMMENT']);
			if($tmp == null)
				continue;
			else
			{
				$row['NAME'] =  $tmp['NAME'];
				$row['TYPE'] = $tmp['TYPE'];
			}

			/**
			 * out of sync check jobs that have no repair option should not count as failed job. 
			 */
			if($this->isCheck($row['TYPE']) && !$this->hasRepairOption($row['JS_COMMENT']))
			{
				if($row['CMD_RET_STATUS'] == self::OUT_OF_SYNC_REPL_ERR) //if out of sync error, then don't count it as a failed job
					continue;
			}

			$tmp = $this->retrieveReplName($row['JS_COMMAND']);
			if($tmp == null)
				continue;
			else
				$row['REPLICATE'] = $tmp;
 	
			$tmp = $this->retrieveMaster($row['JS_COMMAND']);
			if($tmp == null)
				$row['MASTER'] =  "";
			else
				$row['MASTER'] = $tmp;
	
			$row['TIME_STARTED'] = $row['JS_START'];
			$row['TIME_COMPLETED'] = $row['JS_DONE'];
			$row['STATUS'] = self::STATUS_ABORTED_WITHOUT_SYSCDR;
			$row['NUMROWS'] = "";
			$row['ROWS_PROCESSED'] = "";
			$row['ESTIMATED_DURATION'] = "";
			$row['DURATION'] = $row['DURATION'];
			$row['PERCENT_COMPLETE'] = ""; //leave it empty (don't remove it); otherwise soap will complain about it later
			$row['JS_ID'] = $row['JS_ID'];
			$row["CMD_NUMBER"] = $row["CMD_NUMBER"];
      		$result[] = $row;
      	}

		return $result;

	}

	/**
	 * Test if the syscdr replcheck_stat and replcheck_stat_node tables exist.
	 * @return 
	 */
	private function doCheckSyncProgressTablesExist(){
		$result = false;
		
		$qry = "SELECT COUNT(*) as cnt FROM systables WHERE TABNAME IN ("
				. "'" . self::REPLCHECK_STAT . "', '" .self::REPLCHECK_STAT_NODE ."')";
		$tmp = $this->doDatabaseWork($qry, self::SYSCDR);
		if (count($tmp) == 1 && $tmp[0]["CNT"] == 2) {
			$result = true;
		}
		
		return $result;
	}
	
	 /**
	 * Test if the sysadmin job_status and command_history tables exist.
	 * @return 
	 */
	private function doJobStatusHistoryTablesExist(){
		$result = false;
		
		$qry = "SELECT COUNT(*) as cnt FROM systables WHERE TABNAME IN ("
				. "'" . self::JOB_STATUS . "', '" .self::COMMAND_HISTORY ."')";
		$tmp = $this->doDatabaseWork($qry, self::SYSADMIN);
		if (count($tmp) == 1 && $tmp[0]["CNT"] == 2) {
			$result = true;
		}
		
		return $result;
	}
	
	/**
	 * Get the list of servers that make up a particular ER group
	 * @param $groupName
	 */
	private function getERNodeMembers($groupName, $conn = null)
	{
		$qry = "select svrgroup, dbsvrnm, hostname from syssqlhosts where svrgroup = '$groupName'";
		return $this->doDatabaseWork($qry, "sysmaster", $conn);
	}
	
	/*
     * Determine what nodes the current ER node ought to be connected to.
     * Returns an array of Array([SERVID] => <id>).
     */
    private function whatNodesOughtIBeConnectedTo($groupName=null){
    	$result = array();

		if ($groupName != null) {
			$dist_qual = "sysmaster@{$groupName}:";
		} else {
			$dist_qual = "";
		}
		
    	// Determine the current server type: root or non-root (includes non-root non-leaf and leaf)
    	$isroot = false;
    	$qry = "select rootserverid from {$dist_qual}syscdrs where cnnstate = 'L'";
    	$tmp = $this->doDatabaseWork($qry);
    	if (count($tmp) == 1){
    		$isroot = $tmp[0]['ROOTSERVERID'] == 0 ? true : false;
    	}
    	
    	// Get the server id of all the nodes the current node ought to be connected to    	
    	if ($isroot){
    		$qry = "select servid, servname, cnnstate from {$dist_qual}syscdrs where cnnstate != 'L' and rootserverid = 0"  // all other root nodes
				  ."union " 
				  ."select servid, servname, cnnstate from {$dist_qual}syscdrs where rootserverid = (select servid from {$dist_qual}syscdrs where cnnstate ='L')"; // child nodes    		
    	}
    	else {
    		$qry = "select servid, servname, cnnstate from {$dist_qual}syscdrs where servid = (select rootserverid from {$dist_qual}syscdrs where cnnstate='L')" // parent
				  ."union "
				  ."select servid, servname, cnnstate from {$dist_qual}syscdrs where rootserverid = (select servid from {$dist_qual}syscdrs where cnnstate ='L')"; // child nodes
    	}
    	$result = $this->doDatabaseWork($qry);    
    	
    	return $result;
    }
    
    
    /*
     * Format a given float to have a precision of 2.
     */
    private function formatFloat($float){
    	return sprintf("%0.2f", $float);
    }
    
    /*
     * Get the server's current time
     */
    private function getNodeCurrentTime()
    {
        $qry = "select first 1 CURRENT year to second as currentTime from syscdr_state";
        return ($this->mergeArrays(array(), $this->doDatabaseWork($qry)));
    }

    /**
     * Get the node's overall ER state information
     */
    private function getNodeERState()
    {
        $qry = "select trim(er_state) as ERState, er_capture_state as captureState, "  
             . "er_network_state as networkState, er_apply_state as applyState "
             . "from syscdr_state";
        return ($this->mergeArrays(array(), $this->doDatabaseWork($qry)));
    }
    
    /**
     * Determine whether this node is part of a cluster
     */
    private function isNodeInCluster() 
    {
    	$qry = "select ha_type from sysha_type";
        $res = $this->doDatabaseWork($qry);
        if ($res[0]['HA_TYPE'] != 0 )
        {
			return true;
        } else {
        	return false;
        }
    }
    
    /*
     * Given an array of space data (from getSpaceInfo) summate it.
     */    
    private function summateSpaceInfo($allSpacesInfo) {
        $result = array();
        
    	$free = 0;
    	$size = 0;
    	$nchunks = 0;
    	foreach ($allSpacesInfo as $spacedata) {
    		$free += $spacedata['FREE_SIZE'];
    		$size += $spacedata['SIZE'];
    		$nchunks += $spacedata['NCHUNKS'];
    	}
    	$free = $this->formatFloat($free);
    	$size = $this->formatFloat($size);
    	$result['GRAPH'] = array(array("type" => $this->idsadmin->lang("Free_MB"), "amount" => $free), 
										 array("type" => $this->idsadmin->lang("Used_MB"), "amount" => ($size - $free))); 	
    	$result['TOTALFREE'] = $free;
    	if ($size > 0)
    		$result['TOTALUSED'] = $this->formatFloat((($size - $free) / $size) * 100);
    	else
    		$result['TOTALUSED'] = 0;
    	$result['TOTALSIZE'] = $size;
    	$result['TOTALNCHUNKS'] = $nchunks;
										 
    	return $result; 	
    }
    
    
    /*
     * Get information about the disk usage of a given set of spaces.
     * $spaces must be in the following format: '<space1>','<space2>,...'            
     */
    private function getSpaceInfo($spaces) {
		$result = array();
    	
    	$qry = "select " .    			
    			"TRIM(B.name) as name, ".
    			"A.dbsnum, " .
    			"TRUNC(sum((A.chksize * A.pagesize)/(1024*1024)),2) as size, " .
    			"MAX(B.nchunks) as nchunks, " .
        		"CASE " .
		        " WHEN bitval(B.flags,'0x4')>0 THEN 'Disabled' " .
		        " WHEN bitand(B.flags,3584)>0 THEN 'Recovering' " .
		        " ELSE 'Operational' END  as status, " .
				"TRUNC(sum( (decode(A.mdsize,-1,A.nfree,A.udfree)*A.pagesize)/(1024*1024) ),2) as free_size, " .
    			"TRUNC(100-sum(decode(A.mdsize,-1,A.nfree,A.udfree))*100 / sum(A.chksize),2) as used, ".
    			"MAX(A.pagesize) as pgsize, " .
    			"CASE ".
    			" WHEN bitval(B.flags,'0x8000') <= 0 THEN 'NA'" .  // it's not a sbspace or mirrored sbspace 
    			" WHEN B.sbcflag IN (1,5,9,17) THEN 'ON' ".
    			" ELSE 'OFF' END as logging " .	
    			"FROM syschktab A, sysdbstab B " .   	
        		"WHERE A.dbsnum = B.dbsnum " .
    			"AND B.name IN (" . $spaces .") ".
        		"GROUP BY name, A.dbsnum, 5, 9 ";
    	$result = $this->doDatabaseWork($qry);
    	
    	return $result;
    }    
    
    /**
     * Get space info about all dbspaces on the server
     */ 
    private function getDbspaceInfo($conn = null) 
    {
		$result = array();
    	
    	$qry = "select " .    			
    			"TRIM(B.name) as name, ".
    			"A.dbsnum, " .
    			"TRUNC(sum((A.chksize * A.pagesize)/(1024*1024)),2) as size, " .
    			"MAX(B.nchunks) as nchunks, " .
        		"CASE " .
		        " WHEN bitval(B.flags,'0x4')>0 THEN 'Disabled' " .
		        " WHEN bitand(B.flags,3584)>0 THEN 'Recovering' " .
		        " ELSE 'Operational' END  as status, " .
				"TRUNC(sum( (decode(A.mdsize,-1,A.nfree,A.udfree)*A.pagesize)/(1024*1024) ),2) as free_size, " .
    			"TRUNC(100-sum(decode(A.mdsize,-1,A.nfree,A.udfree))*100 / sum(A.chksize),2) as used, ".
    			"MAX(A.pagesize) as pgsize, " .
    			"CASE ".
    			" WHEN bitval(B.flags,'0x8000') <= 0 THEN 'NA'" .  // it's not a sbspace or mirrored sbspace 
    			" WHEN B.sbcflag IN (1,5,9,17) THEN 'ON' ".
    			" ELSE 'OFF' END as logging " .	
    			"FROM syschktab A, sysdbstab B " .   	
        		"WHERE A.dbsnum = B.dbsnum " .
        		"AND bitval(B.flags,'0x10')<=0 " .
        		"AND bitval(B.flags,'0x2000')<=0 " .
        		"AND bitval(B.flags,'0x8000')<=0 " .
        		"GROUP BY name, A.dbsnum, 5, 9 " .
        		"ORDER BY name";
    	$result = $this->doDatabaseWork($qry, "sysmaster", $conn);
    	
    	return $result;
    }
    
    /**
     * Get space info about all sbspaces on the server
     */ 
    private function getSbspaceInfo($conn = null) 
    {
		$result = array();
    	
    	$qry = "select " .    			
    			"TRIM(B.name) as name, ".
    			"A.dbsnum, " .
    			"TRUNC(sum((A.chksize * A.pagesize)/(1024*1024)),2) as size, " .
    			"MAX(B.nchunks) as nchunks, " .
        		"CASE " .
		        " WHEN bitval(B.flags,'0x4')>0 THEN 'Disabled' " .
		        " WHEN bitand(B.flags,3584)>0 THEN 'Recovering' " .
		        " ELSE 'Operational' END  as status, " .
				"TRUNC(sum( (decode(A.mdsize,-1,A.nfree,A.udfree)*A.pagesize)/(1024*1024) ),2) as free_size, " .
    			"TRUNC(100-sum(decode(A.mdsize,-1,A.nfree,A.udfree))*100 / sum(A.chksize),2) as used, ".
    			"MAX(A.pagesize) as pgsize, " .
    			"CASE ".
    			" WHEN bitval(B.flags,'0x8000') <= 0 THEN 'NA'" .  // it's not a sbspace or mirrored sbspace 
    			" WHEN B.sbcflag IN (1,5,9,17) THEN 'ON' ".
    			" ELSE 'OFF' END as logging " .	
    			"FROM syschktab A, sysdbstab B " .   	
        		"WHERE A.dbsnum = B.dbsnum " .
        		"AND bitval(B.flags,'0x8000')>0 " .
    	        "AND bitval(B.flags,'0x2000') = 0 " .
        		"GROUP BY name, A.dbsnum, 5, 9 " .
        		"ORDER BY name";
    	$result = $this->doDatabaseWork($qry, "sysmaster", $conn);
    	
    	return $result;
    }
    
    /*
     * For a given onconfig parameter return all space names in a list of the following format:
     *   "<space1>","<space2>,..."
     */
    private function getSpaceNames($onconfigParameter) {
        $spaces = "";
        $qry = "select cf_effective from sysconfig where cf_name = ?";
        $tmp = $this->doPreparedDatabaseWork($qry, array($onconfigParameter));
        if (count($tmp) == 1) {
            $space_name_array = explode(",", $tmp[0]["CF_EFFECTIVE"]);
            $spaces = "'" . trim(implode("','", $space_name_array)) . "'";
        }
        
        return $spaces;
    }
    	
	
    /*
     * Obtain the total number of ATS files.
     */
    private function getTotalNumberOfATSFiles(){
    	$result = 0;
		
		/*
		 * Hot fix for defect 195834. The if statement below prevents an error message that is display when opening the node details page.
		 */
		$nodeInfo = $this->getNodeInfo();
		
		if($nodeInfo['ATSDIR'] != "/dev/null" && $nodeInfo['ATSDIR'] != "NUL" &&
		   $nodeInfo['RISDIR'] != "/dev/null" && $nodeInfo['RISDIR'] != "NUL")
		{		
			$qry = "select count(*) as atsFileCount from syscdr_atsdir";
			$result = $this->doDatabaseWork($qry);
		}
		return $result;
    }

    
    /* 
	 * Obtain the total number of RIS files 
	 */
	private function getTotalNumberOfRISFiles() {
		
		$result = 0;
		/*
		 * Hot fix for defect 195834. The if statement below prevents an error message that is display when opening the node details page.
		 */
		$nodeInfo = $this->getNodeInfo();
		if($nodeInfo['ATSDIR'] != "/dev/null" && $nodeInfo['ATSDIR'] != "NUL" &&
		   $nodeInfo['RISDIR'] != "/dev/null" && $nodeInfo['RISDIR'] != "NUL")
		{
			$qry = "select count(*) as risFileCount from syscdr_risdir";
			$result = $this->doDatabaseWork($qry);
		}
		return $result;
	}


    /**
     * Get the node's capture summary information
     */
    private function getNodeCaptureSummary()
    {
        $qry = "select ddr_state as ddrState,"
             . "ddr_curr_loguniq as currentLlId, ddr_curr_logpos as currentLlPos,"
             . "ddr_snoopy_loguniq as snoopyLlId, ddr_snoopy_logpos as snoopyLlPos,"             
             . "ddr_replay_loguniq as replayLlId, ddr_replay_logpos as replayLlPos,"
             . "ddr_logpage2block as proximityToDDRBLOCKInPages "
             . "from syscdr_ddr";
        return ($this->mergeArrays(array(), $this->doDatabaseWork($qry)));
    }

    /**
     * Get the node's send queue summary information
     */
    private function getNodeSendQueueSummary()
    {
        $qry = "select A.rqm_txn as txnInQueue, "
             . "A.rqm_txn_in_memory as txnInMem, "
             . "A.rqm_txn_spooled as txnSpooled, "
             . "A.rqm_data_in_queue as dataInQueue, "
             . "A.rqm_inuse_mem as memUsed, "
             . "A.rqm_tottxn as totalTxnsQueued, "
             . "A.rqm_totqueued as totalDataQueued, "
             . "A.rqm_totspooled as totalTxnsSpooled, " 
             . "A.rqm_maxmemdata as maxMemData, "
             . "A.rqm_maxmemhdr as maxMemHdr, "
             . "CASE WHEN (B.rm_pending_acks IS NULL) THEN 0 "
             . "ELSE B.rm_pending_acks END as acksPending, "
             . "C.numSuspended as numSuspended "
             . "from syscdr_rqm A left outer join syscdr_rcv B on 1=1"
             . ", (select COUNT(*) as numSuspended from syscdrserver "
             . "where servstate = 'Suspend' and servername IN "
             . "( select nif_connname from syscdr_nif )) as C "
             . "where A.rqm_name = 'trg_send'";
        $sendq_result = $this->mergeArrays(array(), $this->doDatabaseWork($qry));
        return $sendq_result;
    }
    
    /**
     * Get more detailed information about Send Queue 
     * (additional info not returned by getNodeSendQueueSummary method
     */
    private function getNodeSendQueueDetailInfo()
    {
        $qry = "select first 1 key_acked_lgid as lastdataack_llid, "
            . "key_acked_lgpos as lastdataack_llpos "
            . "from (select key_acked_lgid, max(key_acked_lgpos) as key_acked_lgpos "
            . "from syscdrprog group by key_acked_lgid) order by key_acked_lgid desc";
		$result = $this->mergeArrays(array(), $this->doDatabaseWork($qry));
		
		/* 
		 * Obtain the most recent logical log ID and position of data 
		 * that has been sent to any other node. 
		 */
		if (  Feature::isAvailable( Feature::PANTHER, $this->idsadmin) ) {
			$stampPred = " AND a.nif_trgsend_stamp = b.rqmh_stamp";
		} else {
			$stampPred = " AND a.nif_trgsend_stamp1 = b.rqmh_stamp1 AND a.nif_trgsend_stamp2 = b.rqmh_stamp2";			
		}
		$qry = "SELECT FIRST 1 MAX(rqmh_logid) AS lastdatasent_llid, rqmh_logpos as lastdatasent_llpos FROM ("
			. "    SELECT b.rqmh_logid, b.rqmh_logpos FROM syscdr_nif as a, syscdr_rqmhandle as b"
			. "    WHERE b.rqmh_qidx = 0"   /* Send queue */
			. $stampPred
			. "    GROUP BY 1,2"
			. " )"
			. " GROUP BY rqmh_logpos"
			. " ORDER BY rqmh_logpos DESC";
		$result = array($this->mergeArrays($result, $this->doDatabaseWork($qry)));
		 			
        return $result;        
    }
    
    
    /**
     * Get the send queue details per target node
     */
    private function getSendQueuePerTargetNode()
    {
        // This is a complicated query with a number of joins and nested queries,
        // so let's build it up piece by piece

        // Query to get last data acked per target node
        $last_data_ack_qry = "select dest_id, "
            . "max(key_acked_lgid)||':'||max(key_acked_lgpos) as lastdataack "
            . "from (select dest_id, key_acked_lgid, max(key_acked_lgpos) as key_acked_lgpos "
            . "from syscdrprog group by dest_id, key_acked_lgid) group by dest_id";
        
        // Query to get data in queue per target node
        $data_in_queue_qry = "select servid, syscdrqueued.servername, "
            . "sum(bytesqueued) as datainqueue " 
            . "from syscdrqueued, syscdrserver "
            . "where syscdrqueued.servername=syscdrserver.servername " 
            . "group by servid, syscdrqueued.servername";

		// Obtain the last data sent to each node
		if (  Feature::isAvailable( Feature::PANTHER, $this->idsadmin) ) {
			$stampPred = " AND a.nif_trgsend_stamp = b.rqmh_stamp";
		} else {
			$stampPred = " AND a.nif_trgsend_stamp1 = b.rqmh_stamp1 AND a.nif_trgsend_stamp2 = b.rqmh_stamp2";			
		}
		$last_data_sent_qry = "SELECT a.nif_connname, b.rqmh_logid, b.rqmh_logpos "
		    . " FROM syscdr_nif as a, syscdr_rqmhandle as b"
			. " WHERE b.rqmh_qidx = 0"   /* Send queue */
			. $stampPred
			. " GROUP BY 1,2,3";
		
        // Join the above queries with the syscdr_nif table to get connstate and nifstate
        // info about each target node
		if (  Feature::isAvailable( Feature::PANTHER, $this->idsadmin) ) {
        	$stampProj = "nif_trgsend_stamp, lastdataack ";
        } else {
        	$stampProj = "nif_trgsend_stamp1, nif_trgsend_stamp2, lastdataack ";
        }
        
        $qry = "select servername as node, NVL(nif_state, 'Disconnected') as nifstate, "
            . "NVL(nif_connstate, '-') as connstate, datainqueue, "
            . $stampProj
			. ", rqmh_logid || ':' || rqmh_logpos as lastdatasent "
            . "from syscdr_nif right outer join ($data_in_queue_qry) on servername=nif_connname "
            . "left outer join ($last_data_ack_qry) on servid=dest_id "
			. ", ($last_data_sent_qry) as t4 where servername = t4.nif_connname "			
			. "order by servername";
        return $this->doDatabaseWork($qry);
    }
    
    /**
     * Get the send queue details per replicate 
     */
    private function getSendQueuePerReplicate()
    {
        $qry = "select q.replname, q.srvname as rcvnode, "
             . "q.bytesqued as datainqueue, " 
             . "p.key_acked_lgid||':'||p.key_acked_lgpos as lastdataack "
             . "from syscdrprog p, syscdrq q "
             . "where p.dest_id = q.srvid and p.group_id = q.repid "
             . "order by q.replname";
        return $this->doDatabaseWork($qry);
    }
    
    /**
     * Get the node's apply summary information
     */
    private function getNodeApplySummary()
    {
        $apply_result = array();
        $qry = "select NVL(SUM(txncnt),0) as txnProcessed, NVL(SUM(pending),0) as txnPending, "
               . "TRUNC(NVL(AVG(cmtrate),0),2) as commitRate, "
               . "TRUNC(NVL(AVG(avg_active),0),2) as avgActApply from syscdrrecv_stats";
        $apply_result = $this->mergeArrays($apply_result, $this->doDatabaseWork($qry));
        $qry = "select TRUNC(rm_ds_failrate,2) as failRate, rm_ds_num_locktout as lockTimeouts, " 
               . " rm_ds_num_lockrb as lockRollbacks, rm_ds_num_deadlocks as deadlocks "
               . " from syscdr_rcv";
        $apply_result = $this->mergeArrays($apply_result, $this->doDatabaseWork($qry));
        $qry = "select SUM(txabrtd) as totalFailures from syscdrtx";
        $apply_result = $this->mergeArrays($apply_result, $this->doDatabaseWork($qry));
        $apply_result = array_merge($apply_result, $this->getLatencySummary());
        $apply_result = $this->mergeArrays($apply_result, $this->getTotalNumberOfATSFiles());
        $apply_result = $this->mergeArrays($apply_result, $this->getTotalNumberOfRISFiles());
        return $apply_result;
    }
    
    /**
     * Get the node's detailed global apply information,
     *  i.e. the additional data not returned by the getNodeApplySummary method
     */
    private function getNodeApplyGlobalDetails()
    {     
        $qry = "select rqm_data_in_queue as rcvqueuesize " 
               . "from syscdr_rqm where rqm_name = 'trg_receive'";
        return $this->doDatabaseWork($qry);
    }
    
    
    /**
     * Get the apply statistics for each partner node
     */
    private function getApplyStatsPerNode()
    {
         $qry = "select sourceid, srvname as node, NVL(nifstate, 'Disconnected') as nifstate, " 
               . "NVL(connstate, '-') as connstate, activetxns, "
               . "pendingtxns, txprocssd as txnsprocessed, "
               . "txcmmtd as txnscommitted, txabrtd as txnsaborted "
               . "from syscdrtx right outer join "
               . "(select source::int as sourceid,nif_state as nifstate, "
               . "nif_connstate as connstate, active as activetxns, pending as pendingtxns "
               . "from syscdrrecv_stats left outer join syscdr_nif on nif_connid=source::int ) "
               . "on srvid=sourceid;";
        $apply_per_node = $this->doDatabaseWork($qry);
        
        // For each source node returned by the above query, 
        // get the avg and max latency
        $i = 0;
        foreach ($apply_per_node as $row)
        {
            $sourceid = $row['SOURCEID'];
            $latency_result = $this->getLatencySummary($sourceid);
            $apply_per_node[$i]['AVGLATENCY'] = $latency_result['AVGLATENCY'];
            $apply_per_node[$i]['MAXLATENCY'] = $latency_result['MAXLATENCY'];
            $i++;
        }
        
        return $apply_per_node;
    }

    
    /**
     * Get the node's summary network info
     */
private function getNodeNetworkSummary()
    {
        $network_result = array();
        
        // Determine the number of nodes this server ought to be connected to
        $network_result['TOTALNIFS'] = count($this->whatNodesOughtIBeConnectedTo());  // i.e. total number of NIFs that ought to exist        
        $qry = "select count(*) as numNifsConnected from syscdr_nif where nif_state = 'Connected'";
        $network_result = $this->mergeArrays($network_result, $this->doDatabaseWork($qry));
        $qry = "select SUM(nif_msgsent) as msgSent, SUM(nif_msgrcv) as msgRcv from syscdr_nif";
        $network_result = $this->mergeArrays($network_result, $this->doDatabaseWork($qry));
         
        // Get # of bytes sent and received
        $qry = "select count(*) as numberOfNifs from syscdr_nif";
        $tmp = $this->doDatabaseWork($qry);        
        $numNifs = $tmp[0]['NUMBEROFNIFS'];  // the actual number of NIFs that currently exist              
        $qry = "select SUM(nif_bytessent + nif_bytesrcv) as totalsent, MIN(nif_starttime) as start, CURRENT YEAR TO SECOND as current from syscdr_nif";
        $tmp = $this->doDatabaseWork($qry);                                
        $throughput = 0;
        if (count($tmp) == 1 && $numNifs > 0) {
            $bytesSent = $tmp[0]['TOTALSENT'];

            // avoid any timezone errors from strtotime() by explicitly setting timezone
            date_default_timezone_set("Europe/London");

            $secondsElapsed = strtotime($tmp[0]['CURRENT']) - strtotime($tmp[0]['START']);
            if ($secondsElapsed == 0)
            $throughput = 0;
            else
            $throughput = ($bytesSent / $secondsElapsed) / $numNifs;
            $throughput = $this->formatFloat($throughput);            
        }
        $network_result['THROUGHPUT'] = $throughput;

        /* Get # of messages not sent.
         * (schema change in sysmaster:(syscdr_nif, syscdr_rqmstamp, syscdr_rqmhandle) tables
         * in IDS version 11.7(panther) replacing 2 int columns with 1 bigint column - (OAT CQ idsdb00194178))
		 */	
		if (  Feature::isAvailable( Feature::PANTHER, $this->idsadmin) ) {
         $qry = "select rqms_stamp as stampOfNextTxnToQueue1 from syscdr_rqmstamp where rqms_qidx = 0";
        } else {
         $qry = "select rqms_stamp1 as stampOfNextTxnToQueue1, rqms_stamp2 as stampOfNextTxnToQueue2 from syscdr_rqmstamp where rqms_qidx = 0";
        }
        $tmp = $this->doDatabaseWork($qry);
        $total_msgs_not_sent = 0;
		if  (count($tmp) == 1) {
			if (  Feature::isAvailable( Feature::PANTHER, $this->idsadmin) ) {
         		$qry = "select nif_trgsend_stamp as stampOfLastTxnSent1 from syscdr_nif";
         		$nextToQueue1 = $tmp[0]['STAMPOFNEXTTXNTOQUEUE1'];
        	} else {
         		$qry = "select nif_trgsend_stamp1 as stampOfLastTxnSent1, nif_trgsend_stamp2 as stampOfLastTxnSent2 from syscdr_nif";
         		$nextToQueue1 = $tmp[0]['STAMPOFNEXTTXNTOQUEUE1'];
            	$nextToQueue2 = $tmp[0]['STAMPOFNEXTTXNTOQUEUE2'];
        	}           
            
            $tmp = $this->doDatabaseWork($qry);
            for ($i = 0; $i < count($tmp); $i++) {
            	// Being by using stamp 1
                $msgs_not_sent_to_site = $nextToQueue1 - $tmp[$i]['STAMPOFLASTTXNSENT1'];
                
				if ( ! Feature::isAvailable( Feature::PANTHER, $this->idsadmin) ) {
	                // If the stamp 2's differ then use their difference               
	                if ($nextToQueue2 != $tmp[$i]['STAMPOFLASTTXNSENT2']) {
	                	$msgs_not_sent_to_site = $nextToQueue2 - $tmp[$i]['STAMPOFLASTTXNSENT2'];
	                }
                }
                
                // Guard against negative values
                if ($msgs_not_sent_to_site < 0) {
                	$msgs_not_sent_to_site = 0;
                }
                
                $total_msgs_not_sent += $msgs_not_sent_to_site;
            }
        }
        $network_result['MSGNOTSENT'] = $total_msgs_not_sent;

        return $network_result;
    }

    /**
     * Get the node's spool disk summary information
     */
    private function getDiskSummary()
    {
    	$result = array();
    	
        // Get names of all sbspaces used in CDR_QDATA_SBSPACE in the format: "<space1>","<space2>,..."
        $sbspaces = $this->getSpaceNames('CDR_QDATA_SBSPACE');
    	$tmp = $this->summateSpaceInfo($this->getSpaceInfo($sbspaces));
    	$result['SIZE'] = $tmp['TOTALSIZE'];
    	$result['FREE'] = $tmp['TOTALFREE'];
    	$result['USED'] = $result['SIZE'] - $result['FREE'];
	$result['PERCENTFREE'] = $this->formatFloat(($result['FREE'] / $result['SIZE']) * 100);
    	$result['PERCENTUSED'] = $this->formatFloat(($result['USED'] / $result['SIZE']) * 100);
   
		return $result;
    }

    /**
     * Get the node's receive queue summary info
     */
    private function getNodeReceiveQueueSummary()
    {
        $recvq_result = array();
        $qry = "select rqm_txn as txnInQueue from syscdr_rqm where rqm_name = 'trg_receive'";
        $recvq_result = $this->mergeArrays($recvq_result, $this->doDatabaseWork($qry));
        $qry = "select NVL(SUM(pending),0) as txnInPendingList from syscdrrecv_stats";
        $recvq_result = $this->mergeArrays($recvq_result, $this->doDatabaseWork($qry));
        return $recvq_result;
    }

    /*
     * Get the node's replicate summary info
     */
    private function getNodeReplicateSummary()
    {
        if (is_null($this->servername))
        {
            $this->servername = $this->getCurrentERNodeName();
        }

        $replicate_results = array();
        $qry = "select count(*) as numRepls from syscdrpart where servername = '" . $this->servername . "'";
        $replicate_results = $this->mergeArrays($replicate_results, $this->doDatabaseWork($qry));
        $qry = "select count(*) as numActiveRepls from syscdrpart where partstate = 'Active' "
             . "AND servername = '" .  $this->servername . "'";
        $replicate_results = $this->mergeArrays($replicate_results, $this->doDatabaseWork($qry));
        return $replicate_results;
    }

    /**
     * Get the node's error information
     */
    private function getNodeErrorCount()
    {
        if (is_null($this->servername))
        {
            $this->servername = $this->getCurrentERNodeName();
        }
        $qry = "select count(*) as numUnreviewedErrors from syscdrerror where errorserv = '"
             . $this->servername . "' and reviewed = 'N';";
        return ($this->mergeArrays(array(), $this->doDatabaseWork($qry)));
    }

    /*
     * Calculate the average and max latencies
     *
     * @param sourceid -- id of the source node
     * If sourceid = 0, the function will return the avg and max latencies
     * for all source nodes. If sourceid != 0, the function will return the
     * avg and max latencies for that particular source node.
     */
    private function getLatencySummary($sourceid=0)
    {
        $result = array();
        $totalSourceNodes = 0;        
        $avgLatency = 0; // the average latency of the most recently received txn from all source nodes
        $maxLatency = 0; // the maximum latency of the most recently received txn from all source nodes
        $lastSourceID = 0;               
        $mostRecentSrcApplyTime = 0;
        $mostRecentLatencyForSrcNode = 0;

        $tmp = array();
        if ($sourceid == 0) {
        	$qry = "select source, replid, last_tgt_apply, last_src_apply from syscdrlatency order by source, replid";
        	$tmp = $this->doDatabaseWork($qry);
        }
        else {
        	$qry = "select source, replid, last_tgt_apply, last_src_apply from syscdrlatency where source = ? order by source, replid";
        	$tmp = $this->doPreparedDatabaseWork($qry, array($sourceid));        	
        }
        
        foreach ($tmp as $row)
        {    
			$thisSourceID = $row['SOURCE'];
			$thisApplyTime = $row['LAST_SRC_APPLY'];
			$thisLatency = $row['LAST_TGT_APPLY'] - $thisApplyTime;
			
			if ($thisSourceID != $lastSourceID) {
				$lastSourceID = $thisSourceID; 
				$totalSourceNodes++;
				$mostRecentSrcApplyTime = 0;
				$avgLatency += $mostRecentLatencyForSrcNode;
			}
						
			if ($thisApplyTime >= $mostRecentSrcApplyTime){
				$mostRecentSrcApplyTime = $thisApplyTime;
				$mostRecentLatencyForSrcNode = $thisLatency;				
				if ($thisLatency > $maxLatency) {
					$maxLatency = $thisLatency;					
				}				
			}        	
        }
        $avgLatency += $mostRecentLatencyForSrcNode;
        if ($avgLatency < 0)
        	$avgLatency = 0;        
        
        $result['MAXLATENCY'] = $maxLatency;        
        $result['AVGLATENCY'] = $totalSourceNodes > 0 ? $avgLatency / $totalSourceNodes : 0;
			
        return $result;
	}
    
    /*
     * Test for the existence of the UDR(s) needed for the OAT ER plugin.
     * If they don't exist, deploy them.
     *   
     * Upon failure a PDO error will be thrown (from doDatabaseWork()).
     * Upon success the function simply returns.
     * 
     */
     private function testAndDeployUDRs( $conn = null){
    							
		$UDRs = array("RUNCDR", "ONEXEC"); /* Note: Changing these requires changing the related PHP functions. */
		$database = self::SYSADMIN;
				
    	/* Test if each function exists.  If they don't exist, create them. */
		foreach ($UDRs as $UDRName) {
	    	$qry = "select COUNT(*) as UDREXISTS from sysprocedures where procname = ?";	    	
	    	$tmp = $this->doPreparedDatabaseWork($qry, array(strtolower($UDRName)), $database, $conn); /* Testing reveals that the UDR name must be in lowercase */	    		    		    	    	
	    	if (count($tmp) == 1){
	    		
	    		/* If the UDR doesn't exist, create it */
	    		if ($tmp[0]['UDREXISTS'] != 1){	
	    			$funcName = "deploy" . $UDRName;
	    			$this->$funcName($database, $conn);  // throws an error if there is a problem    			
	    		}
	    	}
		}
    }
    
    /*
     * Creates the ONEXEC UDR on the given database.
     * If the routine already exists it throws a -673 error (via doDatabaseWork).
     */        
    private function deployONEXEC($database, $conn = null){
    	$qry = <<<EOS
/*
 * Execute the given program with the given argument string.
 *
 * Returns 0 on success, the ISAM error upon failure.
 */
CREATE FUNCTION ONEXEC(prog char(128), args lvarchar) RETURNING integer;
    DEFINE tProg CHAR(128);
    DEFINE command LVARCHAR(2048);
    DEFINE ifx VARCHAR(128);
    DEFINE os CHAR(2);
    DEFINE ctime INTEGER;
    DEFINE sqlerr INTEGER;
    DEFINE isamerr INTEGER;
    DEFINE result INTEGER;

    LET isamerr = 0;
    LET sqlerr = 0;
    LET result = 0;
    LET tProg = TRIM(prog);

    -- Security: Only allow the following programs to be run
    IF NOT (tProg IN ("cdr", "onstat", "ontape", "onbar", 
                      "archecker", "oncheck", "onpladm", 
                      "ipload", "dbexport", "dbimport") ) THEN
        RAISE EXCEPTION -746, -746, "Invalid argument";
    END IF

    LET os,ctime = (SELECT DBINFO("version", "os"), sh_curtime FROM sysmaster:sysshmvals);
    LET ifx = (SELECT TRIM(env_value) FROM sysmaster:sysenv WHERE env_name ='INFORMIXDIR');

    IF (os = 'T' ) THEN
        -- WINDOWS
        LET command = 'cmd /c '||ifx||'\bin\\'|| tProg ||' '|| TRIM(args);
    ELSE
        LET command = TRIM(ifx)||'/bin/'|| tProg ||' '|| TRIM(args);
    END IF

    BEGIN
        ON EXCEPTION SET sqlerr, isamerr
            LET result = isamerr;
        END EXCEPTION WITH RESUME;
        SYSTEM( command );
    END

    return result;
END FUNCTION;
    	    	
EOS
;
		$result = $this->doDatabaseWork($qry, $database, $conn);		
		return $result;    	
    }
    
    
    /*
     * Creates the RUNCDR UDR on the given database.
     * If the routine already exists it throws a -673 error (via doDatabaseWork).
     */    
    private function deployRUNCDR($database, $conn = null){
    	$qry = <<<EOS
/*
 * Parameters:
 *   - subCommand: the string representing the cdr subcommand
 *     (e.g. "view" in "cdr view")
 *
 * Requirements:  
 *   - Only call this with supported cdr sub-commands
 *   - Call this at most once per (IDS) session
 *
 * Returns one result per ER node in the current domain.
 * Upon failure an error is raised.
 */
CREATE FUNCTION RUNCDR(subCommand LVARCHAR) RETURNS LVARCHAR(30000);
    DEFINE cmd CHAR(128);
    DEFINE now datetime year to second;
    DEFINE result LVARCHAR(30000);
    DEFINE numLastCmdRun INTEGER;
    DEFINE commandID VARCHAR(100);
    DEFINE onexecResult INTEGER;
    DEFINE errsql INTEGER;
    DEFINE errisam INTEGER;
    DEFINE errtext VARCHAR(255);


    /* Set up exception handling */
    ON EXCEPTION SET errsql, errisam, errtext
        LET now = CURRENT YEAR TO SECOND;
        LET result = "Error: " || errtext || "  -  " || errisam || "  -  " || now;

        -- insert into sysadmin:command_history
        INSERT INTO sysadmin:command_history VALUES (0, now, 'informix', DBINFO('dbhostname'), TRIM(cmd) || " " || subCommand, errisam, result);
        
        -- and return the same, completing execution of the UDR
        RETURN result;
    END EXCEPTION;

    
    LET cmd = "cdr";


    /* 
     * 1. Determine a unique ID for the cdr command
     * This links the results cdr inserts into sysadmin:command_history to
     * a single invocation of this UDR.  Every command run through cdr must
     * accept this command id.
     * 
     * Use the session ID, this is unique for OAT.
     */
    LET commandID = (SELECT DBINFO("sessionid") AS VARCHAR FROM sysmaster:sysshmvals);


    /* 
     * 2. Modify the command to include the command ID
     * This must be done for each cdr subcommand that supports being called
     * from this UDR.
     */
    LET subCommand = TRIM(subCommand);
    -- add the command ID to "cdr view profile --saveResults" if it exists
    LET subCommand = REPLACE(subCommand, "--saveResults", "--saveResults=" || commandID);

    /* 
     * 3. Find the number of the most recent command run
     * This helps us return the results faster.
     */
    SELECT NVL ( MAX(cmd_number), 0 ) INTO numLastCmdRun FROM sysadmin:command_history;


    /* 4. Call the cdr utility via sysadmin:ONEXEC */
    EXECUTE FUNCTION ONEXEC(cmd, subCommand) INTO onexecResult;
    IF (onexecResult != 0) THEN
        RAISE EXCEPTION -746, onexecResult, "ONEXEC returned an error";
    END IF;


    /* 5. Return the results */
    PREPARE stmt FROM 'SELECT cmd_ret_msg FROM sysadmin:command_history WHERE cmd_number > ? AND cmd_user = ? ORDER BY cmd_number';
    DECLARE cur1 CURSOR FOR stmt;
    OPEN cur1 USING numLastCmdRun, commandID;
    FETCH cur1 INTO result;

    WHILE (SQLCODE == 0)
        RETURN result WITH RESUME;
        FETCH cur1 INTO result;    
    END WHILE;

    CLOSE cur1;
    FREE cur1;
    FREE stmt;

END FUNCTION;
    	
EOS
;    	
    	$result = $this->doDatabaseWork($qry, $database, $conn);
		return $result;    			
    } 
    
    
    /**
     * Tests if the 'ER' ph_group exists.  If not, this function creates the group.
     **/    
    private function testAndDeploySysAdminERGroup()
    {
    	// Check if 'ER' exists in ph_group
    	$qry = "select COUNT(*) as GROUPEXISTS from ph_group where group_name = '" . self::ER_GROUP_NAME . "'";
	    $res = $this->doDatabaseWork($qry, self::SYSADMIN); 	    		    		    	    	
	    if (count($res) == 1){
	    	if ($res[0]['GROUPEXISTS'] == 1){	
	    		// 'ER' group already exists, so return
	    		return;    			
	    	}
	    }
	    
	    // If not, create the group
    	$qry = "insert into ph_group(group_name,group_description) "
    		 . "values ('"  . self::ER_GROUP_NAME . "','Enterprise Replication')";
    	$result = $this->doDatabaseWork($qry, self::SYSADMIN);
		return $result;    			
    }     

    /**
     * Execute the prepare statements to update (or insert if the 
     * row does not yet exist) the OAT ER thresholds in the 
     * sysadmin:ph_threshold table.
     * 
     * For servers that are part of a cluster, we will not update the 
     * thresholds on any of the secondary servers.  The $checkNeeded
     * parameter is used to indicate whether we need to check whether 
     * the server is a secondary.
     * 
     * @param $newThresholds
     * @param $db connection to the server database
     * @param $check_needed (boolean) do we need check check if server 
     *        is a primary or secondary before doing the update?
     * @return an array where the first element indicates whether the
     *        the threshold update succeeded or failed (0=success, -1=fail),
     *        and the second element is the corresponding success/fail message
     */
    private function doThresholdUpdateDatabaseWork($newThresholds, $db, $checkNeeded=false)
    {
        if ($checkNeeded)
        {
            // This server is part of a cluster, so we need to check
            // whether is the primary or a secondary.  We will only
            // update the thresholds on the primary.
            $qry= "select ha_type from sysmaster:sysha_type";
            $stmt = $db->query($qry);
            $err = $db->errorInfo();
            if (isset($err[1]) && $err[1] != 0)
            {
                $error_str = "{$this->idsadmin->lang("Error")}: {$err[2]} - {$err[1]}";
                return array(-1, $error_str);
            }
            
            $res = $stmt->fetch();
            if ($res['HA_TYPE'] > 1 )
            {
                // This server is a secondary, so do not perform the update
                return null;
            }
        }
    
        $error_str = "";
        
        $update = "UPDATE ph_threshold " .
                " SET value= :val, " .
                " task_name = :enabled ".
                " WHERE name = :name " ;
                
        $insert = "INSERT into ph_threshold " .
                " (name, task_name, value, value_type, description) " .
                " VALUES " .
                " (:name, :task_name, :value, :value_type, :desc)";
                
        foreach ($newThresholds as $threshold )
        {
            // Prepare update statement
            $stmt = $db->prepare($update);
            $err = $db->errorInfo();
            if (isset($err[1]) && $err[1] != 0)
            {
                $error_str = "{$this->idsadmin->lang("Error")}: {$err[2]} - {$err[1]}";
                return array(-1, $error_str);
             }
            
            // Bind parameters & execute update
            $stmt->bindParam(':val', $threshold['VALUE']);
            // NOTE: task_name field is where we store whether threshold is enabled
            $stmt->bindParam(':enabled', $threshold['TASK_NAME']);
            $stmt->bindParam(':name', $threshold['NAME']);
            $stmt->execute();
            
            // Check for errors
            $err = $db->errorInfo();
            if (isset($err[1]) && $err[1] != 0)
            {
                $error_str = "{$this->idsadmin->lang("Error")}: {$err[2]} - {$err[1]}";
                return array(-1, $error_str);
            }
            
            // Check number of rows affected by the update statement.
            // If no rows affected, that parameter did not exist in the ph_threshold 
            // table, so run an insert statement for that value.
            if ($stmt->rowCount() != 0)
            {
                continue;
            }
            
            // Prepare the insert statement
            $stmt = $db->prepare($insert);
            $err = $db->errorInfo();
            if (isset($err[1]) && $err[1] != 0)
            {
                $error_str = "{$this->idsadmin->lang("Error")}: {$err[2]} - {$err[1]}";
                return array(-1, $error_str);
            }
            
            // Bind parameters & execute insert
            $stmt->bindParam(':name', $threshold['NAME']);
            $stmt->bindParam(':task_name', $threshold['TASK_NAME']);
            $stmt->bindParam(':value', $threshold['VALUE']);
            $stmt->bindParam(':value_type', $threshold['VALUE_TYPE']);
            $stmt->bindParam(':desc', $threshold['DESC']);
            $stmt->execute();
            
            // Check for errors
            $err = $db->errorInfo();
            if (isset($err[1]) && $err[1] != 0)
            {
                $error_str = "{$this->idsadmin->lang("Error")}: {$err[2]} - {$err[1]}";
                return array(-1, $error_str);
            }
        }

        return array(0, "update successful");
    }
    
    /**
     * Execute AdminTask of either type CONFIG or SPACE.
     * (object derived from flex/ER/utils/AdminTask.as)
     * 
     * This is a helper function to build up the correct SQL
     * Admin API command/parameters strings for CONFIG and SPACE tasks.
     * This function builds up the command and parameters strings
     * and then calls the more generic executeSQLAdminAPICommand() to
     * execute the task.
     *
	 * Return values:
	 * The result of the command will be stored in the $task array as follows:
	 * 		$task['SUCCESS'] --> true/false, based on result of Admin API command execution 
	 * 		$task['RESULT_MESSAGE'] --> success or failure message
     */
	private function executeAdminTask($task, $conn_num = -1)
	{
	    if (!isset($task['TYPE']) || 
	    	(strcasecmp($task['TYPE'],"CONFIG") != 0 && 
	    	strcasecmp($task['TYPE'],"SPACE") != 0))
    	{
    		$task['SUCCESS'] = false;
    		$task['RESULT_MESSAGE'] = "ERROR: Unknown task type.";
    		return $task;
    	}
    	
		// Determine SQL Admin API command and parameters
        $command = "";
        $parameters = "";
        if (strcasecmp($task['TYPE'],"CONFIG") == 0)
        {
        	switch ($task['CONFIG_NAME'])
        	{
        		case "LTXEHWM":
        		case "LTXHWM":
        		case "DYNAMIC_LOGS":
        		case "CDR_DELAY_PURGE_DTC":
        		case "CDR_LOG_LAG_ACTION":
        		case "CDR_LOG_STAGING_MAXSIZE":
        			$command = "'onmode'";
        			$parameters = ", 'wf'";
        			$parameters .= ", '{$task['CONFIG_NAME']}={$task['CONFIG_VALUE']}'";
        			break;
        		case "ATS_DIR":
        		case "RIS_DIR":
				case "ATS_RIS_FORMAT":
			    	// Get database connection.
    				// If conn_num != -1, use that server instead of the one OAT is connected to
					$db = null;
					if ($conn_num != -1)
					{
    					try {
    						$db = $this->getServerConnection($conn_num, self::SYSADMIN);
        				} catch(PDOException $e) {
        					$err = $e->errorInfo();
            				$err = "{$this->idsadmin->lang("Error")}: {$err[2]} - {$err[1]}";
            				trigger_error($err_str,E_USER_ERROR); 
        				}
        			} else {
	        			//$db = $this->idsadmin->get_database(self::SYSADMIN);
					require_once(ROOT_PATH."lib/database.php");
					$db = new database($this->idsadmin,self::SYSADMIN,$this->idsadmin->phpsession->get_dblcname());
        			}

        			if ($task['CONFIG_VALUE'] == "")
        			{
        				$task['SUCCESS'] = false;
    					if (strcasecmp($task['CONFIG_NAME'], "ATS_DIR") == 0)
    					{
    						$task['RESULT_MESSAGE'] = "Failed: ATS_DIR cannot be empty.";
    					} 
						else if (strcasecmp($task['CONFIG_NAME'], "RIS_DIR") == 0)
						{
    						$task['RESULT_MESSAGE'] = "Failed: RIS_DIR cannot be empty.";
    					}
						else if (strcasecmp($task['CONFIG_NAME'], "ATS_RIS_FORMAT") == 0)
						{
							$task['RESULT_MESSAGE'] = "Failed: RIS_DIR_FORMAT cannot be empty.";
						}
    					return $task;
        			}
        			$command = "'cdr modify server'";
   					if (strcasecmp($task['CONFIG_NAME'], "ATS_DIR") == 0)
   					{
	        			$parameters = ",'--ats={$task['CONFIG_VALUE']}'";
   					} 
					else if (strcasecmp($task['CONFIG_NAME'], "RIS_DIR") == 0)
					{
	        			$parameters = ",'--ris={$task['CONFIG_VALUE']}'";
   					}
					else if (strcasecmp($task['CONFIG_NAME'], "ATS_RIS_FORMAT") == 0)
					{
						$parameters .= ",'--atsrisformat={$task['CONFIG_VALUE']}'";
					}
        			$parameters .= ", '{$this->getCurrentERNodeName($db)}'";
        			break;
        		default:
        			$command = "'cdr change onconfig'";
        			$parameters = ", '\"{$task['CONFIG_NAME']} {$task['CONFIG_VALUE']}\"'";
        			break;
        	}
        } 
        elseif (strcasecmp($task['TYPE'],"SPACE") == 0) 
        {
        	if ($task['SPACE_TYPE'] != "dbspace" && $task['SPACE_TYPE'] != "sbspace")
			{
				$task['SUCCESS'] = false;
    			$task['RESULT_MESSAGE'] = "Unsupported space type: {$task['SPACE_TYPE']}. " .
    				"Only dbspaces and sbspaces can be created.";
    			return $task;
			}
			$logging_option = "";
			if ($task['SPACE_TYPE'] == "sbspace" && Feature::isAvailable(Feature::PANTHER_UC3, $this->idsadmin))
			{
				// ER spaces should have logging turned on by default, but the "with log" version of the
				// admin API command was introduced in 11.70.xC3 
				$logging_option = "with log";
			}
        	$command = "'create {$task['SPACE_TYPE']} $logging_option',";
        	$parameters = "'{$task['NAME']}', '{$task['PATH']}', " .
        			      "'{$task['SIZE']}M', '{$task['OFFSET']}'";
        }
        
        $task['COMMAND'] = $command;
        $task['PARAMETERS'] = $parameters;

        return $this->executeSQLAdminAPICommand($task, $conn_num);
	}
	
	/**
	 * 
	 * @return a string that describes the job exactly the way it appears in job_status.js_comment and ph_task.tk_description.
	 * @param object $action: $action is the job type (e.g. check)
	 * @param object $job_name: $job_name is the job name.
	 */
	private function formJobDescription($action, $job_name)
	{
		return "OAT ER {$action} job - {$job_name}";
	}
	
    /**
     * Execute SQL Admin API Command
     *
     * Execute SQL Admin API Command specified in $task
     * $task will be an array of the following format:
     * 		$task['COMMAND'] --> SQL Admin API command
     * 		$task['PARAMETERS'] --> Parameters for the SQL Admin API Command
     *
     * When $conn_num is -1, use OAT's current server connection.  
	 * When $conn_num is not -1, use this connection from the connenctions.db
	 *
	 * Return values:
	 * The result of the command will be stored in the $task array as follows:
	 * 		$task['SUCCESS'] --> true/false, based on result of Admin API command execution 
	 * 		$task['RESULT_MESSAGE'] --> success or failure message
	 *      $task['RETURN_CODE'] --> return code of the command
     */
    private function executeSQLAdminAPICommand($task, $conn_num = -1)
    {
    	// Get database connection.
    	// If conn_num != -1, use that server instead of the one OAT is connected to
		$db = null;
		if ($conn_num != -1)
		{
    		try {
    			$db = $this->getServerConnection($conn_num, self::SYSADMIN);
        	} catch(PDOException $e) {
        		$err = $e->errorInfo();
            	$err = "{$this->idsadmin->lang("Error")}: {$err[2]} - {$err[1]}";
            	trigger_error($err_str,E_USER_ERROR); 
        	}
        } else {
	        //$db = $this->idsadmin->get_database(self::SYSADMIN);
	        require_once(ROOT_PATH."lib/database.php");
	        $db = new database($this->idsadmin,self::SYSADMIN,$this->idsadmin->phpsession->get_dblcname(),"","",true);
	        
        }

        if (!isset($task['COMMAND']))
        {
        	$task['SUCCESS'] = false;
            $task['RESULT_MESSAGE'] = "Missing SQL Admin API command name.";
            return $task;
        }
    	if (!isset($task['PARAMETERS']))
        {
        	$task['SUCCESS'] = false;
            $task['RESULT_MESSAGE'] = "Missing SQL Admin API command parameters.";
            return $task;
        }
        
        $command = $task['COMMAND'];
        $parameters = $task['PARAMETERS'];
		
        // Build up SQL statement
        $sql = "execute function admin ( {$command} {$parameters} )";
        // Execute SQL Admin API command
       	$stmt = $db->query($sql);
        
        // Check for success or errors
        $err = $db->errorInfo();
        if (isset($err[1]) && $err[1] != 0)
        {
            $task['SUCCESS'] = false;
            $task['RESULT_MESSAGE'] = "{$this->idsadmin->lang("Error")}: {$err[2]} - {$err[1]}";
            trigger_error($task['RESULT_MESSAGE']);
            return $task;
        }
        
        // Retreive id from command_history table 
        $row = $stmt->fetch();
        $cmd_num = $row[''];
        
        // Retrieve cmd_ret_status and cmd_ret_msg for SQL Admin API command
		$qry = "select cmd_ret_status, cmd_ret_msg from command_history "
			 . "where cmd_number=" . abs($cmd_num);
		$stmt = $db->query($qry);
        $err = $db->errorInfo();
        if (isset($err[1]) && $err[1] != 0)
        {
            $task['SUCCESS'] = false;
            $task['RESULT_MESSAGE'] = "{$this->idsadmin->lang("Error")}: {$err[2]} - {$err[1]}";
            return $task;
        }
        
        // Retreive cmd_ret_status and cmd_ret_msg 
       	$task['SUCCESS'] = false;
		$task['RESULT_MESSAGE'] = "Could not determine result. $cmd_num not found in command_history table" ;	
		while($row = $stmt->fetch(PDO::FETCH_ASSOC))
       	{
        	$task['SUCCESS'] = ($row['CMD_RET_STATUS'] == 0);
        	$task['RETURN_CODE'] = $row['CMD_RET_STATUS'];
			$task['RESULT_MESSAGE'] = $row['CMD_RET_MSG'];
		}
		  			
		return $task;	
    }

    /**
     * Execute asynchronous SQL Admin API Command via the admin_async procedure.
     *
     * Execute asynchronous SQL Admin API Command specified in $task
     * $task will be an array of the following format:
     * 		$task['COMMAND'] --> SQL Admin API command
     * 		$task['PARAMETERS'] --> Parameters for the SQL Admin API Command
     * 		$task['COMMENTS'] --> Optional, name for task
     *      $task['START_TIME'] --> Optional, start time for task
     *      $task['END_TIME'] --> Optional, end time for task
     *      $task['FREQUENCY'] --> Optional, frequency of task
     *      $task['MONDAY'] --> Optional, true/false, is task enabled on Monday?
     *      $task['TUESDAY'] --> Optional, true/false, is task enabled on Tuesday?
     *      $task['WEDNESDAY'] --> Optional, true/false, is task enabled on Wednesday?
     *      $task['THURSDAY'] --> Optional, true/false, is task enabled on Thursday?
     *      $task['FRIDAY'] --> Optional, true/false, is task enabled on Friday?
     *      $task['SATURDAY'] --> Optional, true/false, is task enabled on Saturday?
     *      $task['SUNDAY'] --> Optional, true/false, is task enabled on Sunnday?
     *
     * When $conn_num is -1, use OAT's current server connection.  
	 * When $conn_num is not -1, use this connection from the connenctions.db
	 *
	 * Return values:
	 * The result of the command will be stored in the $task array as follows:
	 * 		$task['SUCCESS'] --> true/false, based on result of Admin API command execution 
	 * 		$task['RESULT_MESSAGE'] --> success or failure message   
	 * 		$task['RETURN_CODE'] --> return code from admin_async procedure, which 
	 *            is the job_status_id  
     */
    private function executeAsyncSQLAdminAPICommand($task, $conn_num = -1)
    {
    	// Get database connection.
    	// If conn_num != -1, use that server instead of the one OAT is connected to
		$db = null;
		if ($conn_num != -1)
		{
    		try {
    			$db = $this->getServerConnection($conn_num, self::SYSADMIN);
        	} catch(PDOException $e) {
        		$err = $e->errorInfo();
            	$err = "{$this->idsadmin->lang("Error")}: {$err[2]} - {$err[1]}";
            	trigger_error($err_str,E_USER_ERROR); 
        	}
        } else {
	        //$db = $this->idsadmin->get_database(self::SYSADMIN);
		require_once(ROOT_PATH."lib/database.php");
		$db = new database($this->idsadmin,self::SYSADMIN,$this->idsadmin->phpsession->get_dblcname());
        }

        if (!isset($task['COMMAND']))
        {
        	$task['SUCCESS'] = false;
            $task['RESULT_MESSAGE'] = "Missing SQL Admin API command name.";
            return $task;
        }
    	if (!isset($task['PARAMETERS']))
        {
        	$task['SUCCESS'] = false;
            $task['RESULT_MESSAGE'] = "Missing SQL Admin API command parameters.";
            return $task;
        }
        
        $command = $task['COMMAND'];
        $parameters = $task['PARAMETERS'];
        
		/* 
		 * Make sure the the command + parameters does not exceed MAX_ADMIN_ASYNC_LENGTH.
		 * If it does, return an error. 
		 */
		$arg = $command . "," . $parameters;
		if (strlen($arg) > self::MAX_ADMIN_ASYNC_LENGTH)
		{
			$task["SUCCESS"] = false;
			$taks["RETURN_CODE"] = self::ERROR;
			$task["RESULT_MESSAGE"] = "The length of the command exceeds the limits for " .
				"background tasks in the Admin API.";
			return $task;
		}
		
        // Are any of the option async scheduling parameters passed?
        $async_options_list = array('COMMENTS','START_TIME','END_TIME','FREQUENCY',
        	'MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY','SUNDAY');
        $async_options = ", 'ER' "; // ph_group for all tasks is 'ER'
        foreach ($async_options_list as $option)
        {
        	if (isset($task[$option]))
        	{
        		if ($task[$option] == "null")
        		{
        			$async_options .= ", NULL";
        		} else {
        			$async_options .= ", \"{$task[$option]}\"";
        		}
        	} else {
        		break;
        	}
        }
         
        // Build up SQL statement
		$sql = "execute function admin_async ( \"{$command},{$parameters}\" {$async_options})";
		
		// Execute SQL Admin API command
		$stmt = $db->query($sql);
        
        // Check for success or errors
        $err = $db->errorInfo();
        if (isset($err[1]) && $err[1] != 0)
        {
            $task['SUCCESS'] = false;
            $task['RESULT_MESSAGE'] = "{$this->idsadmin->lang("Error")}: {$err[2]} - {$err[1]}";
            return $task;
        }
        
        // Retreive job_status_id 
        $row = $stmt->fetch();
        $job_status_id = $row[''];
        
        // If the procedure did not throw an error, the job was submitted succesfully
        $task['SUCCESS'] = true;
       	$task['RESULT_MESSAGE'] = $this->idsadmin->lang('AdminAsyncTaskSumbitted') ;
       	$task['RETURN_CODE'] = $job_status_id;
		  			
		return $task;	
    }
    
    /**
     * Get connection to a partner node in the ER domain.
     *  
     * Search the connections.db for the specified ER partner servername 
     * (only search connections.db for servers within the current group)
     * and establish a connection to that server.   
     * server.
     * 
     * @param servername   
     * @param dbname
     * @return connection to that database or NULL 
     *         if the connection parameters do not exist 
     */
    private function getPartnerServerConnection($servername, $hostname, $dbname="sysmaster")
    {
        // If hostname is the fully qualified hostname, we'll only match the part of the 
        // hostname before the first period with what is in our connections.db.
        // This ensures that if the full hostname is specified in the syssqlhosts 
        // table but only the short hostname in OAT's connections.db (or vice versa)
        // that we still find the proper connection.  If the hostname is an IP address, 
        // compare the entire hostname with what is in connections.db
        $array = explode(".", $hostname);
        if (!is_numeric($array[0]))
        {
        	$hostname = $array[0];
        }
        // If hostname has an initial '*' character, remove it
        if (strlen($hostname) > 1 && substr($hostname, 0, 1) == "*")
        {
        	$hostname = substr($hostname,1);
        }
        
        $query = "select host, port, server, username, password, idsprotocol " .
                 "from connections where " .
                 "group_num={$this->getCurrentOATGroup()} " . 
                 "and server='{$servername}' " .
                 "and host like '{$hostname}%'";
        $res = $this->doConnectionsDatabaseWork($query); 
      
        if (count($res) == 0)
        {
            // return null if we do not have connection info in the connections.db
            return null;
        }
        
        $row = $res[0];
       
        $protocol = ($row['idsprotocol'] == "")? "onsoctcp":$row['idsprotocol'];
        $host = $row['host'];
        $port = $row['port'];
        $server = $row['server']; 
        $locale = $this->idsadmin->phpsession->get_dblcname();
        $username = $row['username'];
        $password = connections::decode_password( $row['password'] );

        require_once (ROOT_PATH."lib/PDO_OAT.php");
        $db = new PDO_OAT($this->idsadmin,$server,$host,$port,$protocol,$dbname,"",null,$username,$password);
        return $db;
    }
    
    /**
     * Get connection to another server from the connections.db
     *
     * @param conn_num of that connection in the connections.db   
     * @param dbname
     * @return connection to that database or NULL 
     *         if the connection parameters do not exist 
     */
    function getServerConnection($conn_num, $dbname="sysmaster")
    {
        $query = "select host, port, server, username, password, idsprotocol " .
                 "from connections where " .
                 "group_num={$this->getCurrentOATGroup()} " . 
                 "and conn_num='{$conn_num}' ";
        $res = $this->doConnectionsDatabaseWork($query); 
      
        if (count($res) == 0)
        {
            // return null if we do not have connection info in the connections.db
            return null;
        }
        
        $row = $res[0];
       
        $protocol = ($row['idsprotocol'] == "")? "onsoctcp":$row['idsprotocol'];
        $host = $row['host'];
        $port = $row['port'];
        $server = $row['server']; 
        $locale = $this->idsadmin->phpsession->get_dblcname();
        $username = $row['username'];
        $password = connections::decode_password( $row['password'] );
        
        require_once (ROOT_PATH."lib/PDO_OAT.php");
        $db = new PDO_OAT($this->idsadmin,$server,$host,$port,$protocol,$dbname,"",null,$username,$password);
        return $db;
    }
    
	/**
     * Merge two arrays where $array2 represents a single row returned from a database. That row should be
     * an associative array.  The result is that $array2 is "flattened" and added to $array1.
     */
    private function mergeArrays($array1, $array2)
    {
        $result = $array1;
        if (count($array2) == 1 && is_array($array2[0]))
        {
            $result = array_merge($array1, $array2[0]);
        }
        return $result;
    }
		
	/*
	 * Use this function to prepare then execute a given SQL query.
	 * For queries that involve dynamic parameters it is safer to use prepared statements
	 * than to put the parameters directly into the query string. 
	 */
	private function doPreparedDatabaseWork($sel, $params, $dbname = 'sysmaster', $conn = null ){
		return $this->doDatabaseWork($sel, $dbname, $conn, $params);
	}
	
	/**
	 * Use this function to execute statements on the IDS server
	 */
    public function doDatabaseWork($sel,$dbname="sysmaster",$conn=null,$params=array(),$locale="",$exceptions=false)
    {
        $ret = array();
        
        if (! is_null($conn))
        {
        	$db = $conn;
        }
        else if (!empty($locale))
        {
        	require_once(ROOT_PATH."lib/database.php");
        	$db = new database($this->idsadmin,$dbname,$locale);
        } else {
        	require_once(ROOT_PATH."lib/database.php");
        	$db = new database($this->idsadmin,$dbname,$this->idsadmin->phpsession->get_dblcname());
        }

        while (1 == 1)
        {        	
        	/* If we have parameters, we'll prepare then execute the query. */
			if (count($params) == 0)
        		$stmt = $db->query($sel, false, $exceptions);
			else {
				$stmt = $db->prepare($sel);
				$stmt->execute($params);
			}

            $colcount = $stmt->columnCount();
            $colname = array();
            // Following loop identifies the columns with type = "TEXT"
            // Later we need to convert this type of data before sending it back to the flex side
            // Otherwise flex web service will not be able to handle it correctly
            for($cnt = 0; $cnt < $colcount; $cnt++)
            {
                $meta = $stmt->getColumnMeta($cnt);
                if("TEXT" == $meta["native_type"])
                	$colname[] = $meta["name"];
            }
            
            while($row = $stmt->fetch(PDO::FETCH_ASSOC))
            {
				// Following loop actually converts the data into stream,
				// for the columns, identified in the previous loop
            	foreach($colname as $name)
            		$row[$name] = stream_get_contents($row[$name]);
            	$ret[] = $row;
            }
            
            $err = $db->errorInfo();
            if ( $err[2] == 0 )
            {
                $stmt->closeCursor();
                break;
            }
            else
            {
                $err = "{$this->idsadmin->lang("Error")}: {$err[2]} - {$err[1]}";
                $stmt->closeCursor();
                trigger_error($err,E_USER_ERROR);
                continue;
            }
        }
        return $ret;
    }
    
    /**
     * Use this function to execute statements on the OAT connections database
     */
    private function doConnectionsDatabaseWork($qry , $ispasswd = false)
    {
    	$ret = array();

        $stmt = $this->connectionsDb->query($qry);
        if (! $stmt)
        	{
            $err = $this->connectionsDb->errorInfo();
            return array(-1,"statement error:{$err[1]} {$err[2]}");
        	}

        while ($row = $stmt->fetch() )
        	{
            $err = $stmt->errorInfo();
            
            // "isset($err[1])" added into the following condition to avoid strange crash
            if (isset($err[1]) && $err[1] != 0)
            	{
                $ret = array(-1,"fetch error: {$err[2]}");
                break;
            	}
			if ( $ispasswd == true && isset( $row['password'] ) )
			{
				$row['password'] = connections::decode_password($row['password']);
			}
            $ret[] = $row;
        	}

        return $ret;
    }
        
    private function createTabLoad($atsrisDir, $fileNames, $jobType)
    {
        $result = array();
    	// Check if the oat_atsris_repair_jobs table already exists
    	$qry = "select count(*) as TABEXISTS from systables where tabname=?";	    	
	    $res = $this->doPreparedDatabaseWork($qry, array('oat_atsris_repair_jobs'), self::SYSADMIN);
	    	    		    		    	    	
	    if (count($res) == 1){
	    	if ($res[0]['TABEXISTS'] == 0){	
	    		// create table
	    		 $qry = "create table oat_atsris_repair_jobs (".
        								        "job_id serial  primary key,".
       											"task_id  integer,".
       											"task_seq integer,".
        										"jobname char(20) NOT NULL,".
        										"execution_order int,".
        										"repair_dir lvarchar,".
        										"filename lvarchar,".
        										"job_type  char(3)  CHECK ( job_type IN  (\"ats\", \"ris\")),".
        										"status  char(1) DEFAULT 'W'  CHECK ( status IN (\"W\",\"C\")),".
        										"ret_code int  DEFAULT NULL,".
        										"start_time DATETIME year to fraction(3),".
    											"end_time DATETIME year to fraction(3)".
        										")";

        		 $result = $this->doDatabaseWork($qry, self::SYSADMIN);		    			
	    	}
	    } else {
	    	$result['SUCCESS'] = true;
	    }
	    // generate a unique name for this job since multiple jobs can be submitted to the same table
	    $jobname = uniqid($jobType);
	    
	    //insert ats/ris files to be repaired
	    for ($i = 0; $i < count($fileNames); $i++)
	    {
	    	$qry = "insert into oat_atsris_repair_jobs (jobname, execution_order, repair_dir, filename, job_type) ".
	     										"values ( '{$jobname}', $i+1, '{$atsrisDir}', '{$fileNames[$i]}', '{$jobType}')";
	     	$result = $this->doDatabaseWork($qry, self::SYSADMIN);
	    }
	    
    	return $jobname;
    }

   	/**
     * return default ats/ris dirs based on OS
     **/    
    private function defaultAtsRisDirs($isWindows)
    {

	    if ($isWindows == true)
	    {
	    	$defaultDir = self::WINDOWS_FILE_SEPARATOR . self::TMP_DIR_NAME . self::WINDOWS_FILE_SEPARATOR;
	    } else {
	    	$defaultDir = self::FILE_SEPARATOR . self::TMP_DIR_NAME . self::FILE_SEPARATOR;
	    }
	    return $defaultDir;
    }
    
   	/**
     * Tests if the 'perform_atsris_repair' UDR exists on sysadmin.
     * If not, this function creates the 'perform_atsris_repair' procedure.
     **/    
    private function testAndDeployPerformAtsRisRepair()
    {
    	// Check if perform_atsris_repair procedure already exists
    	$qry = "select COUNT(*) as UDREXISTS from sysprocedures where procname = ?";	    	
	    $res = $this->doPreparedDatabaseWork($qry, array('perform_atsris_repair'), self::SYSADMIN); 	    		    		    	    	
	    if (count($res) == 1){
	    	if ($res[0]['UDREXISTS'] == 1){	
	    		// procedure already exists, so return
	    		return;    			
	    	}
	    }
	    
	    // If not, create the procedure
    	$qry = <<<EOM
CREATE FUNCTION perform_atsris_repair(jname char(20), task_id integer,task_seq integer)
   RETURNING INTEGER

   DEFINE res_code INTEGER;
   DEFINE fname lvarchar;
   DEFINE repfile lvarchar;
   DEFINE jtype char(3);
   DEFINE ex_order INTEGER;
   DEFINE id INTEGER;
   DEFINE cnt INTEGER;
   DEFINE err_cnt INTEGER;
   DEFINE del_code INTEGER;
   DEFINE del_cmd INTEGER;

  LET cnt = 0;
  LET err_cnt = 0;
  LET res_code=0;
  LET del_code=0;
  LET del_cmd=0;


  FOREACH WITH HOLD SELECT job_id, concat(repair_dir,filename), filename, job_type
    INTO id, repfile, fname, jtype
            FROM oat_atsris_repair_jobs AS tb
            WHERE tb.jobname = jname
            ORDER BY tb.execution_order

   LET cnt = cnt + 1;

   update oat_atsris_repair_jobs set (status, ret_code, task_id, task_seq, start_time) = ('C', admin('cdr repair', jtype, repfile), task_id, task_seq, CURRENT)
           WHERE job_id = id;
   update oat_atsris_repair_jobs set (end_time) = (CURRENT)
           WHERE job_id = id;
           
   LET res_code = (SELECT ret_code FROM oat_atsris_repair_jobs WHERE job_id = id);
   
   IF jtype == 'ats' THEN
   		LET del_cmd = 85;
   ELSE
   		LET del_cmd = 86;
   END IF
   
   IF res_code > 0 THEN
   		BEGIN WORK;
   		CALL informix.cdrcmd(del_cmd,fname) returning del_code;
   		COMMIT WORK;
   END IF

  END FOREACH

   LET err_cnt = (SELECT count(*) FROM oat_atsris_repair_jobs WHERE jobname = jname and ret_code < 0);

   IF err_cnt > 0 THEN
       INSERT INTO ph_alert
          (ID, alert_task_id,alert_task_seq,alert_type,
           alert_color, alert_object_type,
           alert_object_name, alert_message)
            VALUES
          (0,task_id, task_seq, "WARNING", "YELLOW", "SERVER",
    jtype, "Enterprise Replication repair of "|| cnt || " " ||jtype|| " files had "|| err_cnt || " errors.");
   ELSE
       INSERT INTO ph_alert
          (ID, alert_task_id,alert_task_seq,alert_type, alert_color, alert_object_type,
           alert_object_name, alert_message)
            VALUES
          (0,task_id, task_seq, "INFO", "GREEN", "SERVER",
    jtype, "Enterprise Replication repaired "|| cnt || " " || jtype|| " files." );
   END IF

   RETURN  del_code;
END FUNCTION;
   
EOM
;    	
    	$result = $this->doDatabaseWork($qry, self::SYSADMIN);
    	return $result;    			
    }
	
}
	
?>
