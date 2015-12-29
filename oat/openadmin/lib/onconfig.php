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


/** Class to store onconfig parameter information **/
class onconfig {

	const INT = 'INTEGER';
	const STRING = 'STRING';
	const BOOLEAN = 'BOOLEAN';

 	/** List of basic onconfig parameters **/
	private static $basic_onconfig_params =
	 	array("ROOTNAME",
                        "ROOTPATH",
                        "DBSERVERNAME",
                        "PHYSDBS",
                        "MSGPATH",
                        "CONSOLE",
                        "TAPEDEV",
                        "LTAPEDEV",
                        "ROOTOFFSET",
                        "ROOTSIZE",
                        "MIRROR",
                        "PHYSFILE",
                        "LOGFILES",
                        "LOGSIZE",
                        "TAPEBLK",
                        "TAPESIZE",
                        "LTAPEBLK",
                        "LTAPESIZE",
                        "SERVERNUM",
                        "RESIDENT",
                        "LOCKS",
                        "PHYSBUFF",
                        "LOGBUFF",
                        "DYNAMIC_LOGS",
                        "CLEANERS",
                        "LRUS",
                        "SHMVIRTSIZE",
                        "USEOSTIME",
                        "RA_PAGES",
                        "RA_THRESHOLD",
                        "NUMAIOVPS",
                        "NETTYPE",
                        "DBSERVERALIASES",
                        "MULTIPROCESSOR",
                        "DBSPACETEMP",
                        "DS_MAX_QUERIES",
                        "DS_TOTAL_MEMORY",
                        "SHMADD",
                        "SHMTOTAL",
                        "VPCLASS",
                        "SBSPACENAME",
                        "SYSALARMPROGRAM",
                        "SBSPACETEMP",
                        "DEF_TABLE_LOCKMODE",
                        "BUFFERPOOL",
                        "BUFFERS_DEFAULT",
                        "DS_NONPDQ_QUERY_MEM",
                        "RTO_SERVER_RESTART",
                        "AUTO_LRU_TUNING",
                        "AUTO_CKPTS",
                        "AUTO_AIOVPS",
                        "SQLTRACE",
                        "TEMPTAB_NOLOG",
                        "MSG_DATE",
                        "SP_AUTOEXPAND",
                        "SP_THRESHOLD",
                        "SP_WAITTIME",
                        "AUTO_STAT_MODE",
                        "STATCHANGE"
		);

 	/** List of dynamically editable onconfig parameters **/
	private static $dynamic_onconfig_params =
	 	array("ADMIN_MODE_USERS",
			"AUTO_AIOVPS",
			"AUTO_CKPTS",
			"AUTO_LRU_TUNING",
			"DISTR_QUERY_FLAGS",
			"DS_MAX_QUERIES",
			"DS_MAX_SCANS",
			"DS_NONPDQ_QUERY_MEM",
			"DS_TOTAL_MEMORY",
			"DUMPCNT",
			"DUMPSHMEM",
			"EXPLAIN_STAT",
			"IFX_EXTEND_ROLE",
			"INDEX_SELFJOIN",
			"LISTEN_TIMEOUT",
			"LOG_INDEX_BUILDS",
			"MAX_INCOMPLETE_CONNECTIONS",
			"MAX_PDQPRIORITY",
			"ONLIDX_MAXMEM",
			"RAS_LLOG_SPEED",
			"RAS_PLOG_SPEED",
			"RESIDENT",
			"RTO_SERVER_RESTART",
			"SDS_TIMEOUT",
			"SORT_MERGE_SIZE",
			"TEMPTAB_NOLOG",
			"USE_BATCHEDREAD",
			"USE_KOBATCHEDREAD",
			"USELASTCOMMITTED",
			"VP_MEMORY_CACHE_KB"
	 	);

	/** List of dynamically editable onconfig parameters for server versions >= 11.50.xC1 **/
	private static $dynamic_onconfig_params_1150xC1 =
	 	array("HA_ALIAS",
	 		"LIMITNUMSESSIONS",
	 		"MSG_DATE"
	 	);

	/** List of dynamically editable onconfig parameters for server versions >= 11.50.xC3 **/
	private static $dynamic_onconfig_params_1150xC3 =
	 	array("DYNAMIC_LOGS",
	 		"LTXEHWM",
	 		"LTXHWM",
			"SBSPACENAME",
			"SBSPACETEMP"
	 	);

	/** List of dynamically editable ER onconfig parameters for server versions >= 11.50.xC3 **/
	/** These ER parameteters are edited through 'cdr change onconfig' instead of 'onmode -wf/wm' **/
	private static $dynamic_ER_onconfig_params_1150xC3 =
	 	array("CDR_DSLOCKWAIT",
			"CDR_DBSPACE",
			"CDR_EVALTHREADS",
			"CDR_MAX_DYNAMIC_LOGS",
			"CDR_NIFCOMPRESS",
			"CDR_QDATA_SBSPACE",
			"CDR_QHDR_DBSPACE",
			"CDR_QUEUEMEM",
			"CDR_SERIAL",
			"CDR_SUPPRESS_ATSRISWARN",
			"ENCRYPT_CDR",
			"ENCRYPT_CIPHERS",
			"ENCRYPT_MAC",
			"ENCRYPT_MACFILE",
			"ENCRYPT_SWITCH"
		);

	/** List of dynamically editable onconfig parameters for server versions >= 11.50.xC5 **/
	private static $dynamic_onconfig_params_1150xC5 =
	 	array("DELAY_APPLY",
			"LOG_STAGING_DIR",
			"STOP_APPLY",
			"SQL_LOGICAL_CHAR"
	 	);

	/** List of dynamically editable onconfig parameters for server versions >= 11.50.xC6 **/
	private static $dynamic_onconfig_params_1150xC6 =
	 	array("BATCHEDREAD_TABLE",
	 		"BATCHEDREAD_KEYONLY"
	 	);
	 	
	 /** List of dynamically editable onconfig parameters for server versions >= 11.50.xC8 **/
	 private static $dynamic_onconfig_params_1150xC8 =
	 	array("NET_IO_TIMEOUT_ALARM",
	 		"RSS_FLOW_CONTROL"
	 	);

	/** List of dynamically editable onconfig parameters for server versions >= 11.70.xC1 (Panther) **/
	private static $dynamic_onconfig_params_1170xC1 =
	 	array("AUTO_STAT_MODE",
	 		"BAR_CKPTSEC_TIMEOUT",
	 		"BATCHEDREAD_INDEX",
	 		"CDR_DELAY_PURGE_DTC",
	 		"CDR_KEYNAME",
	 		"CDR_LOG_LAG_ACTION",
	 		"CDR_LOG_STAGING_MAXSIZE",
	 		"ENABLE_SNAPSHOT_COPY",
	 		"EXPLAIN_CTRL",
	 		"FAILOVER_TX_TIMEOUT",
	 		"INTERRUPT_FREQ_PAGES",
	 		"MULTI_INDEX_SCAN",
	 		"NS_CACHE",
	 		"SB_SPECIAL_FLAGS",
	 		"SMX_COMPRESS",
	 		"SP_AUTOEXPAND",
	 		"SP_THRESHOLD",
	 		"SP_WAITTIME",
	 		"STAR_JOIN",
	 		"STATCHANGE",
	 		"USERMAPPING"
	 	);

	/** List of dynamically editable onconfig parameters for server versions >= 11.70.xC2 **/
	private static $dynamic_onconfig_params_1170xC2 =
	 	array("REMOTE_SERVER_CFG",
	 		"REMOTE_USERS_CFG"
	 	);
	 
	/** List of dynamically editable onconfig parameters for server versions >= 11.70.xC3 **/
	private static $dynamic_onconfig_params_1170xC3 =
	 	array("AUTO_READAHEAD",
	 		"LOW_MEMORY_MGR",
	 		"LOW_MEMORY_RESERVE"
	 	);
	 	
	/** List of dynamically editable onconfig parameters for server versions >= 11.70.xC4 **/
	private static $dynamic_onconfig_params_1170xC4 =
	 	array("AFCRASH",
	 		"AFFAIL",
	 		"AFWARN",
	 		"ALARMPROGRAM",
	 		"AUTO_REPREPARE",
	 		"BLOCKTIMEOUT",
	 		"CKPTINTVL",
	 		"DBSPACETEMP",
	 		"DEADLOCK_TIMEOUT",
	 		"DEF_TABLE_LOCKMODE",
	 		"DIRECTIVES",
	 		"DRINTERVAL",
	 		"DRTIMEOUT",
	 		"FILLFACTOR",
	 		"IFX_FOLDVIEW",
	 		"LOGSIZE",
	 		"LTAPEBLK",
	 		"LTAPEDEV",
	 		"LTAPESIZE",
	 		"MSGPATH",
	 		"ONDBSPACEDOWN",
	 		"OPTCOMPIND",
	 		"RA_PAGES",
	 		"S6_USE_REMOTE_SERVER_CFG",
	 		"SHMADD",
	 		"STACKSIZE",
	 		"SYSALARMPROGRAM",
	 		"SYSSBSPACENAME",
	 		"TAPEBLK",
	 		"TAPEDEV",
	 		"TAPESIZE",
	 		"TBLTBLFIRST",
	 		"TBLTBLNEXT",
	 		"TXTIMEOUT",
	 		"USTLOW_SAMPLE",
	 		"WSTATS"
	 	);

	/** Array to store type and value range info about the onconfig parameters **/
	public static $onconfig_info = Array(
		'ADMIN_MODE_USERS' => Array('type'=>onconfig::STRING),
		'ALARMPROGRAM' => Array('type'=>onconfig::STRING),
		'AUTO_AIOVPS' => Array('type'=>onconfig::BOOLEAN),
		'AUTO_CKPTS' => Array('type'=>onconfig::BOOLEAN),
 		'AUTO_LRU_TUNING' => Array('type'=>onconfig::BOOLEAN),
		'AUTO_READAHEAD' => Array('type'=>onconfig::INT, 'min'=>0, 'max'=>2),
 		'AUTO_REPREPARE' => Array('type'=>onconfig::BOOLEAN),
		'AUTO_STAT_MODE' => Array('type'=>onconfig::BOOLEAN),
		'BAR_CKPTSEC_TIMEOUT' => Array('type'=>onconfig::INT, 'min'=>0),
		'BATCHEDREAD_INDEX' => Array('type'=>onconfig::BOOLEAN),
		'BATCHEDREAD_KEYONLY' => Array('type'=>onconfig::BOOLEAN),
		'BATCHEDREAD_TABLE' => Array('type'=>onconfig::BOOLEAN),
		'BLOCKTIMEOUT' => Array('type'=>onconfig::INT, 'min'=>0),
		'CDR_DELAY_PURGE_DTC' => Array('type'=>onconfig::STRING),
		'CDR_DSLOCKWAIT' => Array('type'=>onconfig::INT, 'min'=>0),
		'CDR_DBSPACE' => Array('type'=>onconfig::STRING),
		'CDR_EVALTHREADS' => Array('type'=>onconfig::STRING),
		'CDR_KEYNAME' => Array('type'=>onconfig::STRING),
		'CDR_LOG_LAG_ACTION' => Array('type'=>onconfig::STRING),
		'CDR_LOG_STAGING_MAXSIZE' => Array('type'=>onconfig::STRING),
		'CDR_MAX_DYNAMIC_LOGS' => Array('type'=>onconfig::INT, 'min'=>-1),
		'CDR_NIFCOMPRESS' => Array('type'=>onconfig::INT, 'min'=>-1, 'max'=>9),
		'CDR_QDATA_SBSPACE' => Array('type'=>onconfig::STRING),
		'CDR_QHDR_DBSPACE' => Array('type'=>onconfig::STRING),
		'CDR_QUEUEMEM' => Array('type'=>onconfig::INT, 'min'=> 500),
		'CDR_SERIAL' => Array('type'=>onconfig::STRING),
		'CDR_SUPPRESS_ATSRISWARN' => Array('type'=>onconfig::STRING),
		'CKPTINTVL' => Array('type'=>onconfig::INT, 'min'=>0),
		'DBSPACETEMP' => Array('type'=>onconfig::STRING),
		'DEADLOCK_TIMEOUT' => Array('type'=>onconfig::INT, 'min'=>0),
		'DEF_TABLE_LOCKMODE' => Array('type'=>onconfig::STRING, 'values'=>Array('PAGE', 'ROW', 'page', 'row')),
		'DELAY_APPLY' => Array('type'=>onconfig::STRING),
		'DIRECTIVES' => Array('type'=>onconfig::BOOLEAN),
		'DISTR_QUERY_FLAGS' => Array(),
		'DRINTERVAL' => Array('type'=>onconfig::INT, 'min'=>-1),
		'DRTIMEOUT' => Array('type'=>onconfig::INT, 'min'=>0),
		'DS_MAX_QUERIES' => Array('type'=>onconfig::INT, 'min'=>1, 'max'=>8388608),
		'DS_MAX_SCANS' => Array('type'=>onconfig::INT, 'min'=>10, 'max'=>1048576),
		'DS_NONPDQ_QUERY_MEM' => Array('type'=>onconfig::INT, 'min'=>128),
		'DS_TOTAL_MEMORY' => Array('type'=>onconfig::INT),
		'DUMPCNT' => Array('type'=>onconfig::INT, 'min'=>1),
		'DUMPSHMEM' => Array('type'=>onconfig::BOOLEAN),
		'DYNAMIC_LOGS' => Array('type'=>onconfig::INT, 'min'=>0, 'max'=>2),
		'ENABLE_SNAPSHOT_COPY' => Array('type'=>onconfig::BOOLEAN),
		'ENCRYPT_CDR' => Array('type'=>onconfig::INT, 'min'=>0, 'max'=>2),
		'ENCRYPT_CIPHERS' => Array('type'=>onconfig::STRING),
		'ENCRYPT_MAC' => Array('type'=>onconfig::STRING),
		'ENCRYPT_MACFILE' => Array('type'=>onconfig::STRING),
		'ENCRYPT_SWITCH' => Array('type'=>onconfig::STRING),
		'EXPLAIN_CTRL' => Array('type'=>onconfig::STRING),
		'EXPLAIN_STAT' => Array('type'=>onconfig::BOOLEAN),
		'FAILOVER_TX_TIMEOUT' => Array('type'=>onconfig::INT, 'min'=>0, 'max'=>2147483647),
		'FILLFACTOR' => Array('type'=>onconfig::INT, 'min'=>1, 'max'=>100),
		'HA_ALIAS' => Array('type'=>onconfig::STRING),
		'IFX_EXTEND_ROLE' => Array('type'=>onconfig::BOOLEAN),
		'IFX_FOLDVIEW' => Array('type'=>onconfig::BOOLEAN),
		'INDEX_SELFJOIN' => Array(),
		'INTERRUPT_FREQ_PAGES' => Array('type'=>onconfig::INT, 'min'=>0, 'max'=>1000),
		'LISTEN_TIMEOUT' => Array('type'=>onconfig::INT, 'min'=>0),
		'LOGSIZE' => Array('type'=>onconfig::INT, 'min'=>200),
		'LOG_INDEX_BUILDS' => Array('type'=>onconfig::BOOLEAN),
		'LOG_STAGING_DIR' => Array('type'=>onconfig::STRING),
		'LOW_MEMORY_MGR' => Array('type'=>onconfig::BOOLEAN),
		'LOW_MEMORY_RESERVE' => Array('type'=>onconfig::INT),
		'LTAPEBLK' => Array('type'=>onconfig::INT),
		'LTAPEDEV' => Array('type'=>onconfig::STRING),
		'LTAPESIZE' => Array('type'=>onconfig::INT, 'min'=>0),
		'LTXHWM' => Array('type'=>onconfig::INT, 'min'=>0, 'max'=>100),
		'LTXEHWM' => Array('type'=>onconfig::INT, 'min'=>0, 'max'=>100),
		'MAX_INCOMPLETE_CONNECTIONS' => Array('type'=>onconfig::INT, 'min'=>0),
		'MAX_PDQPRIORITY' => Array('type'=>onconfig::INT, 'min'=>1, 'max'=>100),
		'MSGPATH' => Array('type'=>onconfig::STRING),
		'MSG_DATE' => Array('type'=>onconfig::BOOLEAN),
		'MULTI_INDEX_SCAN' => Array('type'=>onconfig::BOOLEAN),
		'NET_IO_TIMEOUT_ALARM' => Array('type'=>onconfig::INT),
		'NS_CACHE' => Array('type'=>onconfig::STRING),
		'ONDBSPACEDOWN' => Array('type'=>onconfig::INT, 'min'=>0, 'max'=>2),
		'ONLIDX_MAXMEM' => Array('type'=>onconfig::INT, 'min'=>16, 'max'=>4294967295),
		'OPTCOMPIND' => Array('type'=>onconfig::INT, 'min'=>0, 'max'=>2),
		'RA_PAGES' => Array('type'=>onconfig::INT),
		'RAS_LLOG_SPEED' => Array(),
		'RAS_PLOG_SPEED' => Array(),
		'RESIDENT' => Array('type'=>onconfig::INT, 'min'=>-1, 'max'=>99),
		'REMOTE_SERVER_CFG' => Array('type'=>onconfig::STRING),
		'REMOTE_USERS_CFG' => Array('type'=>onconfig::STRING),
		'RSS_FLOW_CONTROL' => Array(),
		'RTO_SERVER_RESTART' => Array('type'=>onconfig::INT, 'min'=>60, 'max'=>1800),
		'S6_USE_REMOTE_SERVER_CFG' => Array('type'=>onconfig::BOOLEAN),
		'SBSPACENAME' => Array('type'=>onconfig::STRING),
		'SBSPACETEMP' => Array('type'=>onconfig::STRING),
		'SDS_TIMEOUT' => Array('type'=>onconfig::INT, 'min'=>2, 'max'=>2147483647),
		'SMX_COMPRESS' => Array('type'=>onconfig::INT, 'min'=>-1, 'max'=>9),
		'SORT_MERGE_SIZE' => Array(),
		'SB_SPECIAL_FLAGS' => Array('type'=>onconfig::STRING),
		'SP_AUTOEXPAND' => Array('type'=>onconfig::BOOLEAN),
		'SP_THRESHOLD' => Array('type'=>onconfig::INT, 'min'=>0, 'max'=>4294967294),
		'SP_WAITTIME' => Array('type'=>onconfig::INT, 'min'=>0, 'max'=>2147483647),
		'STACKSIZE' => Array('type'=>onconfig::INT, 'min'=>32),
		'STAR_JOIN' => Array('type'=>onconfig::BOOLEAN),
		'STATCHANGE' => Array('type'=>onconfig::INT, 'min'=>0, 'max'=>100),
		'STOP_APPLY' => Array('type'=>onconfig::STRING),
		'SQL_LOGICAL_CHAR' => Array('type'=>onconfig::STRING),
		'SYSALARMPROGRAM' => Array('type'=>onconfig::STRING),
		'SYSSBSPACENAME' => Array('type'=>onconfig::STRING),
		'TAPEBLK' => Array('type'=>onconfig::INT),
		'TAPEDEV' => Array('type'=>onconfig::STRING),
		'TAPESIZE' => Array('type'=>onconfig::INT, 'min'=>0),
		'TBLTBLFIRST' => Array('type'=>onconfig::INT),
		'TBLTBLNEXT' => Array('type'=>onconfig::INT),
		'TEMPTAB_NOLOG' => Array('type'=>onconfig::BOOLEAN),
		'TXTIMEOUT' => Array('type'=>onconfig::INT, 'min'=>0),
		'USERMAPPING' => Array('type'=>onconfig::STRING, 'values'=>Array('OFF', 'BASIC', 'ADMIN',)),
		'USE_BATCHEDREAD' => Array(),
		'USE_KOBATCHEDREAD' => Array(),
		'USELASTCOMMITTED' => Array('type'=>onconfig::STRING, 'values'=>Array('None', 'Committed Read', 'Dirty Read', 'All')),
		'USTLOW_SAMPLE' => Array('type'=>onconfig::BOOLEAN),
		'VP_MEMORY_CACHE_KB' => Array('type'=>onconfig::INT, 'min'=>0),
		'WSTATS' => Array('type'=>onconfig::BOOLEAN)
	);

	/**
	 * Get the list of basic onconfig parameters
	 */
	public static function get_basic_onconfig_params($idsadmin)
	{
		return onconfig::$basic_onconfig_params;
	}

	/**
	 * Get the list of dynamic onconfig parameters
	 * for the current server version.
	 */
	public static function get_dynamic_onconfig_params($idsadmin)
	{
		require_once ROOT_PATH."lib/feature.php";
		
		// For Informix 12.10 and above, we can use the cf_flags column to determine
		// which onconfig parameters are dynamic ('0x8000').  For Informix verisons
		// < 12.10, this information is not stored in a system table, and therefore we
		// have this info stored in various dynamic_onconfig_params lists in OAT.
		if (Feature::isAvailable(Feature::CENTAURUS, $idsadmin))
		{
			$sql = "select trim(cf_name) as name from syscfgtab where bitand(cf_flags,'0x8000') > 0";
			$db = $idsadmin->get_database("sysmaster");
			$stmt = $db->query($sql);
			
			$dynamic_list = array();
			while ($res = $stmt->fetch(PDO::FETCH_ASSOC) )
			{
				$dynamic_list[] = $res['NAME'];
			}
			return $dynamic_list;
		} 

		// Server versions < 12.10, manually put together the dynamic onconfig param
		// list using the info stored in OAT.
		$dynamic_list = onconfig::$dynamic_onconfig_params;
		if (Feature::isAvailable(Feature::CHEETAH2, $idsadmin))
		{
			$dynamic_list = array_merge($dynamic_list,
				onconfig::$dynamic_onconfig_params_1150xC1);
		}
		if (Feature::isAvailable(Feature::CHEETAH2_UC3, $idsadmin))
		{
			$dynamic_list = array_merge($dynamic_list,
				onconfig::$dynamic_onconfig_params_1150xC3);
			$dynamic_list = array_merge($dynamic_list,
				onconfig::$dynamic_ER_onconfig_params_1150xC3);
		}
		if (Feature::isAvailable(Feature::CHEETAH2_UC5, $idsadmin))
		{
			$dynamic_list = array_merge($dynamic_list,
				onconfig::$dynamic_onconfig_params_1150xC5);
		}
		if (Feature::isAvailable(Feature::CHEETAH2_UC6, $idsadmin))
		{
			$dynamic_list = array_merge($dynamic_list,
				onconfig::$dynamic_onconfig_params_1150xC6);
		}
		if (Feature::isAvailable(Feature::CHEETAH2_UC8, $idsadmin))
		{
			$dynamic_list = array_merge($dynamic_list,
				onconfig::$dynamic_onconfig_params_1150xC8);
		}
		if (Feature::isAvailable(Feature::PANTHER, $idsadmin))
		{
			$dynamic_list = array_merge($dynamic_list,
				onconfig::$dynamic_onconfig_params_1170xC1);
		}
		if (Feature::isAvailable(Feature::PANTHER_UC2, $idsadmin))
		{
			$dynamic_list = array_merge($dynamic_list,
				onconfig::$dynamic_onconfig_params_1170xC2);
		}
		if (Feature::isAvailable(Feature::PANTHER_UC3, $idsadmin))
		{
			$dynamic_list = array_merge($dynamic_list,
				onconfig::$dynamic_onconfig_params_1170xC3);
		}
		if (Feature::isAvailable(Feature::PANTHER_UC4, $idsadmin))
		{
			$dynamic_list = array_merge($dynamic_list,
				onconfig::$dynamic_onconfig_params_1170xC4);
		}
		
		return $dynamic_list;
	}

	/**
	 * Is certain onconfig parameter an ER configuration parameter?
	 *
	 * @param $onconfigParam name of onconfig parameter
	 * @return true/false
	 */
	public static function is_ER_config_parameter($onconfigParam)
	{
		return in_array($onconfigParam, onconfig::$dynamic_ER_onconfig_params_1150xC3);
	}

	/**
	 * ONCONFIG PARAMETER recommendation methods
	 *
	 * For each onconfig parameter (or set of parameters) that
	 * OAT has a recommendation for, there will be a corresponding
	 * recommendation function.  These functions will return an
	 * array of two values:
	 * 	 Array('recommendation'=>"<text description of recommendation>",
	 *         'compliance'=>{true|false})
	 * which will return both the recommendation and whether the
	 * current value of the parameter complies with the recommendation.
	 */

	/**
	 * Zero Recommendation method
	 *
	 * Recommend these always be set to 0.
	 */
	public function zero_recommendation($paramname, $value, $idsadmin)
	{
		$recommendation = $idsadmin->lang("{$paramname}_RECOMMEND");
		$compliance = ($value == 0)? true:false;
		return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
	}

	/**
	 * "On" Recommendation method
	 *
	 * For parameters that should be set to 1 or "turned on"
	 * */
	public function on_recommendation($value, $idsadmin)
	{
		$recommendation = $idsadmin->lang("ON_RECOMMEND");
		$compliance = ($value == 1)? true:false;
		return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
	}

	/**
	 * Non-Zero Recommendation method
	 *
	 * Recommend these NOT be set to 0.
	 */
	public function nonzero_recommendation($paramname, $value, $idsadmin)
	{
		$recommendation = $idsadmin->lang("{$paramname}_RECOMMEND");
		$compliance = ($value != 0)? true:false;
		return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
	}

	/**
	 * "OFF" Recommendation method
	 *
	 * Recommend these be set to "OFF"
	 */
	public function off_recommendation($paramname, $value, $idsadmin)
	{
		$recommendation = $idsadmin->lang("{$paramname}_RECOMMEND");
		$compliance = (strcasecmp(trim($value),"OFF") == 0)? true:false;
		return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
	}

	/**
	 * MIN/MAX Recommendation method
	 * This a generic recommendation method used when a parameter is
	 * recommended to between a certain min and max (inclusive).
	 *
	 * Used for FILLFACTOR, BAR_NB_XPORT_COUNT
	 */
	public function minmax_recommendation($paramname, $value, $idsadmin)
	{
		switch ($paramname)
		{
			case "FILLFACTOR";
			    $min = 40;
			    $max = 98;
			    break;
			case "BAR_NB_XPORT_COUNT";
			    $min = 5;
			    $max = 99;
			    break;
			default;
			    // don't know this parameter
			    return Array('recommendation'=>"",'compliance'=>null);
		}

		$recommendation = $idsadmin->lang("{$paramname}_RECOMMEND");
		$compliance = ($value >= $min && $value <= $max)? true:false;
		return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
	}

	/**
	 * Path not in temp directory method
	 * for BAR_ACT_LOG, BAR_DEBUG_LOG, DUMPDIR
	 *
	 * Recommend these NOT be null and not be located in the server's
	 * temp directory (C:\temp or /tmp)
	 */
	public function nontemp_recommendation($value, $idsadmin)
	{
		$recommendation = $idsadmin->lang("NONTEMP_RECOMMEND");
		$compliance = true;
		if (is_null($value) || $value == "" || strcasecmp($value,"null") == 0)
		{
			$compliance = false;
		}
		else if (strlen($value) >= 4 && strcasecmp(substr($value,0,4), "/tmp") == 0)
		{
			$compliance = false;
		}
		else if (strlen($value) >= 7 && strcasecmp(substr($value,0,7), "C:\\temp") == 0)
		{
			$compliance = false;
		}
		return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
	}

	/**
	 * AFF_NPROCS/AFF_SPROC Recommendation method
	 *
	 * AFF_SPROC - AFF_NPROCS should be greater than the num of physical processors
	 * Not Valid for CHEETAH2.
	 */
	public function AFF_PROC_recommendation($paramname, $value, $idsadmin)
	{
		//defect: idsdb00195874 - AFF_SPROC / AFF_NPROC are not applicable to 11.50 servers
		// however to provide a recommendation we need to lookup in sysmacheinfo which was introduced
		// in 11.50 , therefore we are unable to make recommendations for any version.

			return Array('recommendation'=>"",'compliance'=>null);
/*
		if (!Feature::isAvailable(Feature::CHEETAH2, $idsadmin->phpsession->serverInfo->getVersion()))
		{
			// no recommendation for server versions prior to Cheetah2
			return Array('recommendation'=>"",'compliance'=>null);
		}
		$dbsysmaster = $idsadmin->get_database("sysmaster");
		if (strcasecmp($paramname, "AFF_NPROCS") == 0)
		{
			$aff_nprocs = $value;
			$qry = "select cf_effective from syscfgtab where cf_name='AFF_SPROC'";
			$stmt = $dbsysmaster->query( $qry );
			if (($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
		    {
		        // error running sysmaster query?  then return no recommendation
		        return Array('recommendation'=>"",'compliance'=>null);
		    }
		    $aff_sproc = trim($res['CF_EFFECTIVE']);
		} else {
			$aff_sproc = $value;
			$qry = "select cf_effective from syscfgtab where cf_name='AFF_NPROCS'";
			$stmt = $dbsysmaster->query( $qry );
			if (($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
		    {
		        // error running sysmaster query?  then return no recommendation
		        return Array('recommendation'=>"",'compliance'=>null);
		    }
		    $aff_nprocs = trim($res['CF_EFFECTIVE']);
		}

		// get number of physical processors
		$qry = "select os_num_procs from sysmaster:sysmachineinfo";
		$stmt = $dbsysmaster->query( $qry );
    	if (($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
    	{
    	    // error running sysmaster query?  then return no recommendation
    	    return Array('recommendation'=>"",'compliance'=>null);
    	}
    	$processors = $res['OS_NUM_PROCS'];

    	// (AFF_SPROC - AFF_NPROC) should be greater than the number of physical processors
    	$compliance = (($aff_sproc - $aff_nprocs) > $processors)? true:false;
    	$recommendation = $idsadmin->lang("AFF_NPROCS_SPROC_RECOMMEND", array($processors));
		return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
	*/
	}

	/**
	 * ALARMPROGRAM Recommendation method
	 * for ALARMPROGRAM and SYSALARMPROGRAM
	 *
	 * If ALARMPROGRAM/SYSALARMPROGRAM starts with /usr/informix (or c:\informix)
	 * and INFORMIXDIR is not /usr/informix (or c:\informix), then recommend
	 * it is set to "$INFORMIXDIR/etc/alarmprogram.sh" (ALARMPROGRAM) or
	 * "$INFORMIXDIR/etc/evidence.sh" (SYSALARMPROGRAM)
	 */
	public function ALARMPROGRAM_recommendation($paramname, $value, $idsadmin)
	{
		$recommendation = $idsadmin->lang("{$paramname}_RECOMMEND");
		$compliance = true;
		if (is_null($value) || $value == "" || strcasecmp($value,"null") == 0)
		{
			$compliance = false;
		}
		else
		{
			$dbsysmaster = $idsadmin->get_database("sysmaster");
			$qry = "select env_value from sysenv where env_name='INFORMIXDIR'";
			$stmt = $dbsysmaster->query( $qry );
			if (($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
			{
			    // error running sysmaster query?  then return no recommendation
			    return Array('recommendation'=>"",'compliance'=>null);
			}
			$informixdir=trim($res['ENV_VALUE']);
			if ((strlen($value) >= 13 && strcasecmp(substr($value,0,13), "/usr/informix") == 0)
				|| (strlen($value) >= 11 && strcasecmp(substr($value,0,11), "C:\\informix") == 0))
			{
				$compliance = (strpos("X$value",$informixdir) == 1)? true:false;
			}
		}
		if (!$compliance) $recommendation = $idsadmin->lang("{$paramname}_RECOMMEND_NO") .
			"  " .	$recommendation;
		return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
	}

	/**
	 * BAR_XFER_BUF_SIZE Recommendation method
	 *
	 * If page size is 2K, BAR_XFER_BUF_SIZE should be 15.
	 * If page size is 4K, BAR_XFER_BUF_SIZE should be 31.
	 * select decode(sh_pagesize,2048,31,15) from sysshmvals
	*/
	public function BAR_XFER_BUF_SIZE_recommendation($value, $idsadmin)
	{
		$dbsysmaster = $idsadmin->get_database("sysmaster");
		$qry = "select decode(sh_pagesize,2048,31,15) from sysshmvals";
		$stmt = $dbsysmaster->query( $qry );
    	if (($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
    	{
    	    // error running sysmaster query?  then return no recommendation
    	    return Array('recommendation'=>"",'compliance'=>null);
    	}
		$rcmd_value = trim($res['']);
		$compliance = ($value ==$rcmd_value)? true:false;
		$recommendation = $idsadmin->lang("BAR_XFER_BUF_SIZE_RECOMMEND_" . $rcmd_value);
		return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
	}

    /**
	 * BTSCANNER Recommendation method
	 *
	 * Should include "alice=X" in the value, where X is a non-zero number
	 */
	public function BTSCANNER_recommendation($value, $idsadmin)
	{
		$recommendation = $idsadmin->lang("BTSCANNER_RECOMMEND");
		$compliance = true;

		// For server versions < 11.10xC2W2, BTSCANNER is always empty in syscfgtab.
		// As a workaround, we will return compliance=true if BTSCANNER value is
                // empty for these server versions only.
		require_once(ROOT_PATH."/lib/feature.php");
		if (!Feature::isAvailable(Feature::CHEETAH_UC2, $idsadmin->phpsession->serverInfo->getVersion())
			&& $value == "")
		{
			return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
		}

		$pos = strpos($value,"alice=");
		if (!$pos)  // if string "alice=" does not exist in $value
		{
			$compliance = false;
		}
		else
		{
			// check the value of alice is non-zero
			$value = substr($value,($pos + 6));
    		if ( strpos( $value,"," ) )
            {
	             $value = preg_split( "/,/",$value );
	             $value = $value[0];
            }

			if ($value == 0)
			{
				$compliance = false;
			}
		}
		return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
	}

	/**
	 * CKPTINTVL Recommendation method
	 *
	 * Recommend >= 30 seconds
	 */
	function CKPTINTVL_recommendation ($value, $idsadmin)
	{
		$recommendation = $idsadmin->lang("CKPTINTVL_RECOMMEND");
		$compliance = ($value >= 30)? true:false;
		return Array('recommendation' => $recommendation, 'compliance'=>$compliance);
	}

	/**
	 * CLEANERS Recommendation method
	 *
	 * Recommend it be set to the min(# of VPs, # of chunks)
	 */
	function CLEANERS_recommendation ($value, $idsadmin)
	{
		$dbsysmaster = $idsadmin->get_database("sysmaster");

		// Get number of VPS
		$qry="select count(*) from sysvpprof";
		$stmt = $dbsysmaster->query( $qry );
    	if (($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
    	{
    	   	// error running sysmaster query?  then return no recommendation
    	   	return Array('recommendation'=>"",'compliance'=>null);
    	}
    	$vps = trim($res['']);

    	// Get number of chunks
		$qry="select count(*) from syschunks";
		$stmt = $dbsysmaster->query( $qry );
    	if (($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
    	{
    	   	// error running sysmaster query?  then return no recommendation
    	   	return Array('recommendation'=>"",'compliance'=>null);
    	}
    	$chunks = trim($res['']);

		$rcmd_value = min($vps,$chunks);
		$recommendation = $idsadmin->lang("CLEANERS_RECOMMEND") . "<strong> $rcmd_value </strong>";
		$compliance = ($value == $rcmd_value)? true:false;
		return Array('recommendation' => $recommendation, 'compliance'=>$compliance);
	}

	/**
	 * DB_LIBRARY_PATH Recommendation metod
	 *
	 * All components in DB_LIBRARY_PATH should start with $INFORMIXDIR.
	 */
	function DB_LIBRARY_PATH_recommendation ($value,$idsadmin)
	{
		$path_list = explode(",", $value);
		$compliance = true;
		$recommendation = $idsadmin->lang("DB_LIBRARY_PATH_RECOMMEND");
		$dbsysmaster = $idsadmin->get_database("sysmaster");
		$qry = "select env_value from sysenv where env_name='INFORMIXDIR'";
		$stmt = $dbsysmaster->query( $qry );
		if (($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
		{
		    // error running sysmaster query?  then return no recommendation
		    return Array('recommendation'=>"",'compliance'=>null);
		}
		$informixdir=trim($res['ENV_VALUE']);
		foreach ($path_list as $path)
		{
			if ($path != "" &&
			!(strpos("X$path",$informixdir) == 1 || strpos("X$path", "\$INFORMIXDIR") == 1))
			{
				$compliance = false;
			}
		}
		if (!$compliance) $recommendation .= "  " . $idsadmin->lang("DB_LIBRARY_PATH_RECOMMEND_NO");
		return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
	}

	/**
	 * DBSPACETEMP Recommendation method
	 *
	 * Should not be null, and each space should exist and be
	 * a temp dbspace
	 */
	public function DBSPACETEMP_recommendation($value, $idsadmin)
	{
		$value = trim($value);
		// DBSPACETEMP values can be separated by commas or colons.
		if (count(explode(':',$value)) > 1)
		{
			$dbs_list = explode(':',$value);
		} else {
			$dbs_list = explode(',',$value);
		}
		$recommendation = $idsadmin->lang("DBSPACETEMP_RECOMMEND");
		$compliance = true;
		if (is_null($value) || $value == "" || $value == "null")
		{
			$compliance = false;
		} else {
			$dbsysmaster = $idsadmin->get_database("sysmaster");
			$qry = "select name from sysdbspaces where is_temp > 0 and name=";
			foreach ($dbs_list as $dbs)
			{
				$stmt = $dbsysmaster->query( $qry . "'$dbs'");
				if ($dbs != "" && ($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
			    {
			        // if there are no rows for this dbs name, compliance=false
			        $compliance = false;
			    }
			}
		}
		return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
	}

	/**
	 * DEF_TABLE_LOCKMODE Recommendation method
	 *
	 * General recommendation is that it is set to 'row'.
	 * However, if it is currently set to 'page', we will only
	 * recommend it is changed to 'row' if lock waits exist on
	 * the server.
	 **/
	function DEF_TABLE_LOCKMODE_recommendation ($value, $idsadmin)
	{
		if (strcasecmp($value, "row") == 0)
		{
			$recommendation = $idsadmin->lang("DEF_TABLE_LOCKMODE_RECOMMEND");
			$compliance = true;
		} else { // value="page"
			// check if lock waits exist on the server
			$dbsysmaster = $idsadmin->get_database("sysmaster");
			$qry="select sum(value) from sysprofile where name in ('lockwts', 'deadlks', 'lktouts')";
			$stmt = $dbsysmaster->query( $qry );
    		if (($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
    		{
    	    	// error running sysmaster query?  then return no recommendation
    	    	return Array('recommendation'=>"",'compliance'=>null);
    		}

    		if ($res[''] >= 1)
    		{
    			// if lock waits exist, recommend change parameter to 'row'
				$recommendation = $idsadmin->lang("DEF_TABLE_LOCKMODE_RECOMMEND_ROW");
				$compliance = false;
    		} else {
    			// else 'page' is ok, show general recommendation
				$recommendation = $idsadmin->lang("DEF_TABLE_LOCKMODE_RECOMMEND");
				$compliance = true;
    		}
		}
		return Array('recommendation' => $recommendation, 'compliance'=>$compliance);
	}

    /**
	 * DS_NONPDQ_QUERY_MEM Recommendation method
	 *
	 * Should be set to a value >= 512
	 */
	public function DS_NONPDQ_QUERY_MEM_recommendation($value, $idsadmin)
	{
		$recommendation = $idsadmin->lang("DS_NONPDQ_QUERY_MEM_RECOMMEND");
		$compliance = ($value >= 512)? true:false;
		return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
	}

	/**
	 * DS_TOTAL_MEMORY Recommendation method
	 *
	 * DS_TOTAL_MEMORY should be at least 2048 KB and must be > 4 times
         * size of DS_NONPDQ_QUERY_MEM
	 */
	public function DS_TOTAL_MEMORY_recommendation($value, $idsadmin)
	{
		$recommendation = $idsadmin->lang("DS_TOTAL_MEMORY_RECOMMEND");

                // check that DS_TOTAL_MEMORY is at least 2048
                $compliance = ($value >= 2048)? true:false;

                // check that DS_TOTAL_MEMORY is > 4 times size of DS_NONPDQ_QUERY_MEM
		$dbsysmaster = $idsadmin->get_database("sysmaster");
		$qry = "select cf_effective from syscfgtab where cf_name='DS_NONPDQ_QUERY_MEM'";
		$stmt = $dbsysmaster->query( $qry );
    	        if (($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
    	        {
    	            // error running sysmaster query?  then return no recommendation
    	            return Array('recommendation'=>"",'compliance'=>null);
    	        }
		$ds_nonpdq_query_mem = trim($res['CF_EFFECTIVE']);
		$compliance = ($value >= ($ds_nonpdq_query_mem * 4))? $compliance: false;
		return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
	}

	/**
	 * EXTSHMADD Recommendation method
	 *
	 * select sum(seg_size)*1.1 from syssegments where seg_class in (4);
	 * If this sum returns 0, current EXTSHMADD value is ok.
	 * If it's non-zero, recommend EXTSHMADD be set to this value or above.
	 */
	public function EXTSHMADD_recommendation ($value, $idsadmin)
	{
		$dbsysmaster = $idsadmin->get_database("sysmaster");
		$qry = "select sum(seg_size) * 1.1 as sum from syssegments where seg_class in (4)";
		$stmt = $dbsysmaster->query( $qry );
		if (($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
		{
		    // error running sysmaster query?  then return no recommendation
		    return Array('recommendation'=>"",'compliance'=>null);
		}
		$seg_size_sum = trim($res['SUM']);
		if ($seg_size_sum == 0)
		{
			$compliance = true;
		} else {
			$compliance = ($value >= $seg_size_sum)? true:false;
		}
		if ($compliance)
		{
			$recommendation = $idsadmin->lang("EXTSHMADD_RECOMMEND_OK");
		} else {
			$recommendation = $idsadmin->lang("EXTSHMADD_RECOMMEND_NO") . "$seg_size_sum.";
		}
		return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
	}

	/**
	 * LISTEN_TIMEOUT Recommendation method
	 *
	 * Should between 2 seconds and 3 minutes (not inclusive)
	 */
	public function LISTEN_TIMEOUT_recommendation($value, $idsadmin)
	{
		$recommendation = $idsadmin->lang("LISTEN_TIMEOUT_RECOMMEND");
		$compliance = ($value > 2 && $value < 180)? true:false;
		return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
	}

	/**
	 * LOCKS Recommendation method
	 *
	 * If current size of the lock table (sh_maxlocks) is greater that LOCKS,
	 * recommend user increases LOCKS by 10%
	 */
	public function LOCKS_recommendation ($value, $idsadmin)
	{
		$dbsysmaster = $idsadmin->get_database("sysmaster");
		$qry = "select sh_maxlocks from sysshmvals;";
		$stmt = $dbsysmaster->query( $qry );
		if (($res = $stmt->fetch(PDO::FETCH_ASSOC)) == false)
		{
    	   // error running sysmaster query?  then return no recommendation
    	    return Array('recommendation'=>"",'compliance'=>null);
    	}
    	$maxlocks = $res['SH_MAXLOCKS'];
    	if ($maxlocks > $value)
    	{
    		$recommendation = $idsadmin->lang("LOCKS_RECOMMEND_NO");
    		$compliance = false;
    	} else {
    		$recommendation = $idsadmin->lang("LOCKS_RECOMMEND_OK");
    		$compliance = true;
    	}
    	return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
	}

	/**
	 * LOGBUFF Recommendation method
	 *
	 * If (pf_llgpagewrites/pf_llgwrites) > 80% of LOGBUFF,
	 * recommend user increases LOGBUFF by 10%
	 */
	public function LOGBUFF_recommendation ($value, $idsadmin)
	{
		$dbsysmaster = $idsadmin->get_database("sysmaster");
		$qry = "select name, value from sysshmhdr where number in (76,77) order by number";
		$stmt = $dbsysmaster->query( $qry );
		$sysshmhdr = Array();
		while (($res = $stmt->fetch(PDO::FETCH_ASSOC)) == true)
		{
			$sysshmhdr[trim($res['NAME'])] = trim($res['VALUE']);
    	}
    	if (!array_key_exists("pf_llgpagewrites", $sysshmhdr) ||
    		!array_key_exists("pf_llgwrites", $sysshmhdr))
    	{
    	   // error running sysmaster query?  then return no recommendation
    	    return Array('recommendation'=>"",'compliance'=>null);
    	}
    	if ($sysshmhdr['pf_llgwrites'] != 0 &&
    	    ($sysshmhdr['pf_llgpagewrites']/$sysshmhdr['pf_llgwrites']) > (.8 * $value) )
    	{
    		$recommendation = $idsadmin->lang("LOGBUFF_RECOMMEND_NO");
    		$compliance = false;
    	} else {
    		$recommendation = $idsadmin->lang("LOGBUFF_RECOMMEND_OK");
    		$compliance = true;
    	}
    	return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
   	}

	/**
	 * LTXHWM/LTXEHWM Recommendation method
	 *
	 * LTXHWM must be less than LTXEHWM.
	 * LTXHWM: If DYNAMICLOG=off, value should be < 80% and > 25%.
	 * LTXEHWM: If DYNAMICLOG=off, value should be < 90% and > 25%.
	 */
	public function LTXHWM_recommendation($paramname, $value, $idsadmin)
	{
		$recommendation = $idsadmin->lang("{$paramname}_RECOMMEND");
		$compliance = true;
		$dbsysmaster = $idsadmin->get_database("sysmaster");
		if (strcasecmp($paramname, "LTXHWM") == 0)
		{
			$ltxhwm = $value;
			$qry = "select cf_effective from syscfgtab where cf_name='LTXEHWM'";
			$stmt = $dbsysmaster->query( $qry );
			if (($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
		    {
		        // error running sysmaster query?  then return no recommendation
		        return Array('recommendation'=>"",'compliance'=>null);
		    }
		    $ltxehwm = trim($res['CF_EFFECTIVE']);
		} else {
			$ltxehwm = $value;
			$qry = "select cf_effective from syscfgtab where cf_name='LTXHWM'";
			$stmt = $dbsysmaster->query( $qry );
			if (($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
		    {
		        // error running sysmaster query?  then return no recommendation
		        return Array('recommendation'=>"",'compliance'=>null);
		    }
		    $ltxhwm = trim($res['CF_EFFECTIVE']);
		}

		// check that LTXHWM < LTXEHWM
		if ($ltxhwm >= $ltxehwm)
		{
			return Array('recommendation'=>$recommendation,'compliance'=>false);
		}

		// If DYNAMIC_LOGS is off, check the value is < 80% and > 25%.
		$qry = "select cf_effective from syscfgtab where cf_name='DYNAMIC_LOGS'";
		$stmt = $dbsysmaster->query( $qry );
		if (($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
	    {
	        // error running sysmaster query?  then return no recommendation
	        return Array('recommendation'=>"",'compliance'=>null);
	    }
	    $min = 20;
	    $max = (strcasecmp($paramname, "LTXHWM") == 0)?80:90;
	    if ($res['CF_EFFECTIVE'] == 0 && ($value < $min || $value > $max))
	    {
	    	$compliance=false;
	    }
		return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
	}

    /**
	 * MULTIPROCESSOR Recommendation method
	 *
	 * If (select count(*) from sysvplst where class=0) >1,
	 * then recommend MULTIPROCESSOR be ON.  Else recommend
	 * MULTIPROCESSOR be off.
	 */
	public function MULTIPROCESSOR_recommendation($value, $idsadmin)
	{
		$dbsysmaster = $idsadmin->get_database("sysmaster");
		$qry = "select count(*) from sysvplst where class=0";
		$stmt = $dbsysmaster->query( $qry );
    	if (($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
    	{
    	    // error running sysmaster query?  then return no recommendation
    	    return Array('recommendation'=>"",'compliance'=>null);
    	}

		if ($res[''] <= 1) // <=1, MULITPROCESSOR should be off (=0)
		{
			$compliance = ($value == 0)? true:false;
			$recommendation = $idsadmin->lang("MULTIPROCESSOR_RECOMMEND_OFF");
		} else { // if > 1 , MULITPROCESSOR should be on (=1)
			$compliance = ($value == 1)? true:false;
			$recommendation = $idsadmin->lang("MULTIPROCESSOR_RECOMMEND_ON");
		}
		return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
	}

	/**
	 * NUMCPUVPS Recommendation method
	 *
	 * Maximum for NUMCPUVPS is the number of physical processors.
	 * Select avg(num_ready) from sysadmin:mon_vps where class = 0.
	 * If this average > 2 * NUMCPUVPS, recommend to increase it.
	 * However, if the recommendation is greater than max, recommend more hardware.
	 * Valid for CHEETAH2 only.
	 */
	public function NUMCPUVPS_recommendation($value, $idsadmin)
	{
		if (!Feature::isAvailable(Feature::CHEETAH2, $idsadmin->phpsession->serverInfo->getVersion()))
		{
			// no recommendation for server versions prior to Cheetah2
			return Array('recommendation'=>"",'compliance'=>null);
		}

		// get the number of physical processors
		$dbsysmaster = $idsadmin->get_database("sysmaster");
		$qry = "select os_num_procs from sysmachineinfo";
		$stmt = $dbsysmaster->query( $qry );
		if (($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
		{
			// error running sysmaster query?  then return no recommendation
		    return Array('recommendation'=>"",'compliance'=>null);
		}
		$max = $res['OS_NUM_PROCS'];

		$qry = "Select avg(num_ready) as avg from sysadmin:mon_vps where class = 0";
		$stmt = $dbsysmaster->query( $qry );
		if (($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
		{
			// error running sysmaster query?  then return no recommendation
		    return Array('recommendation'=>"",'compliance'=>null);
		}

		if ($res['AVG'] > (2 * $value)) {
			if ($value >= $max)
			{
				$compliance = false;
				$recommendation = $idsadmin->lang("NUMCPUVPS_RECOMMEND_NO");
			} else {
				$compliance = false;
				$recommendation = $idsadmin->lang("NUMCPUVPS_RECOMMEND_ATMAX");
			}
		} else {
			$compliance = true;
			$recommendation = $idsadmin->lang("NUMCPUVPS_RECOMMEND_OK");
		}
		return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
	}

	/**
	 * PHYSBUFF Recommendation method
	 *
	 * If (pf_plgpagewrites/pf_plgwrites) > 80% of PHYSBUFF,
	 * recommend user increases PHYSBUFF by 10%
	 */
	public function PHYSBUFF_recommendation ($value, $idsadmin)
	{
		$dbsysmaster = $idsadmin->get_database("sysmaster");
		$qry = "select name, value from sysshmhdr where number in (73,74) order by number";
		$stmt = $dbsysmaster->query( $qry );
		$sysshmhdr = Array();
		while (($res = $stmt->fetch(PDO::FETCH_ASSOC)) == true)
		{
			$sysshmhdr[trim($res['NAME'])] = trim($res['VALUE']);
    	}
    	if (!array_key_exists("pf_plgpagewrites", $sysshmhdr) ||
    		!array_key_exists("pf_plgwrites", $sysshmhdr))
    	{
    	   // error running sysmaster query?  then return no recommendation
    	    return Array('recommendation'=>"",'compliance'=>null);
    	}
    	if ( $sysshmhdr['pf_plgwrites'] != 0 &&
    	    ($sysshmhdr['pf_plgpagewrites']/$sysshmhdr['pf_plgwrites']) > (.8 * $value) )
    	{
    		$recommendation = $idsadmin->lang("PHYSBUFF_RECOMMEND_NO");
    		$compliance = false;
    	} else {
    		$recommendation = $idsadmin->lang("PHYSBUFF_RECOMMEND_OK");
    		$compliance = true;
    	}
    	return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
   	}

   	/**
	 * SINGLE_CPU_VP Recommendation method
	 *
	 * If there is only one physical processor, recommend this be turned on.
	 * Else, if select count(*) from sysvplst where class = 0 is 1,
	 *    recommend that user read the manual to understand any limitations and consider turning it on.
	 * Valid for CHEETAH2 only.
	 */
	public function SINGLE_CPU_VP_recommendation($value, $idsadmin)
	{
		if (!Feature::isAvailable(Feature::CHEETAH2, $idsadmin->phpsession->serverInfo->getVersion()))
		{
			// no recommendation for server versions prior to Cheetah2
			return Array('recommendation'=>"",'compliance'=>null);
		}

		$recommendation="";
		$compliance=null;

		// get the number of physical processors
		$dbsysmaster = $idsadmin->get_database("sysmaster");
		$qry = "select os_num_procs from sysmachineinfo";
		$stmt = $dbsysmaster->query( $qry );
		if (($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
		{
			// error running sysmaster query?  then return no recommendation
		    return Array('recommendation'=>"",'compliance'=>null);
		}

		// If there is only one physical processor, recommend SINGLE_CPU_VP be turned on
		if ($res['OS_NUM_PROCS'] == 1)
		{
			$compliance = ($value == 1)? true:false;
			$recommendation = $idsadmin->lang("SINGLE_CPU_VP_RECOMMEND_ON");
		} else {
		// Else check  select count(*) from sysvplst where class = 0
			$qry = "select count(*) from sysvplst where class = 0";
			$stmt = $dbsysmaster->query( $qry );
			if (($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
			{
				// error running sysmaster query?  then return no recommendation
			    return Array('recommendation'=>"",'compliance'=>null);
			}
			if ($res[''] == 1)
			{
				$compliance = ($value == 1)? true:false;
				$recommendation = $idsadmin->lang("SINGLE_CPU_VP_RECOMMEND_ON_CHECK");
			}
		}
		return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
	}

    /**
	 * TAPEBLK/LTAPEBLK Recommendation method
	 *
	 * If <= 32K, recommend an increase.
	 * If >= 8M, recommend a decrease.
	 **/
	public function tapeblk_recommnedation($paramname, $value, $idsadmin)
	{
		$compliance = null;
		$recommnedation = "";
		if ($value <= 32)
		{
			$recommendation = $idsadmin->lang("{$paramname}_RECOMMEND_LOW");
			$compliance = false;
		} elseif ($value >= 8192) {
			$recommendation = $idsadmin->lang("{$paramname}_RECOMMEND_HIGH");
			$compliance = false;
		}
		return Array('recommendation' => $recommendation, 'compliance'=>$compliance);
	}

	/**
	 * SBSPACETEMP Recommendation method
	 *
	 * Should not be null if sbspaces exist on the server,
	 * if not null, each space should actually exist and be
	 * a temp sbspace
	 */
	public function SBSPACETEMP_recommendation($value, $idsadmin)
	{
		$sbsp_list = explode(',',trim($value));
		$recommendation = $idsadmin->lang("SBSPACETEMP_RECOMMEND");
		$compliance = true;

		// find out if sbspaces exist on the server
		$dbsysmaster = $idsadmin->get_database("sysmaster");
		$qry = "select count(*) from sysdbspaces where is_sbspace > 0";
		$stmt = $dbsysmaster->query( $qry );
		if (($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
    	{
    	    // error running sysmaster query?  then return no recommendation
    	    return Array('recommendation'=>"",'compliance'=>null);
    	}
    	$sbspaces = trim($res['']);

    	if ($sbspaces == 0) {
    		// if user has no sbspaces, there is no recommendation for this parameter
    		return Array('recommendation'=>"",'compliance'=>null);
    	} else {
			// Else the user has sbspaces
			// so SBSPACETEMP should not be null and those spaces should
			// exist as temp sbspaces
	    	if (is_null($value) || $value == "" || $value == "null")
			{
				$compliance = false;
			} else {
				$bad_sbsp = Array();
				$qry = "select name from sysdbspaces where is_temp > 0 and is_sbspace > 0 and name=";
				foreach ($sbsp_list as $sbsp)
				{
					$stmt = $dbsysmaster->query( $qry . "'$sbsp'");
					if ($sbsp != "" && ($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
				    {
				        // if there are no rows for this sbspace name, compliance=false
				        $compliance = false;
				    }
				}
			}
    	}
		return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
	}

	/**
	 * SHMADD/SHMVIRTSIZE Recommendation method
	 *
	 * select count(*) as count, sum(seg_size) as sum from sysseglst where seg_class = 2
	 * SHMADD: if count > 1, recommend increase
	 * SHMVIRTSIZE: if count > 1, recommend increase to something <= sum(seg_size)
	 */
	public function SHM_recommendation ($paramname, $value, $idsadmin)
	{
		$dbsysmaster = $idsadmin->get_database("sysmaster");
		$qry = "select count(*) as count, hex(sum(seg_size)) as sum from sysseglst where seg_class = 2";
		$stmt = $dbsysmaster->query( $qry );
		if (($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
		{
    	    // error running sysmaster query?  then return no recommendation
    	    return Array('recommendation'=>"",'compliance'=>null);
    	}
    	$count = $res['COUNT'];
    	$sum = $res['SUM'];
    	if ($count > 1)
    	{
    		$compliance = false;
    		$recommendation = $idsadmin->lang("{$paramname}_RECOMMEND_NO");
    		if (strcasecmp("SHMVIRTSIZE",$paramname) == 0)
    			$recommendation .= "$sum.";
    	} else {
    		$compliance = true;
    		$recommendation = $idsadmin->lang("{$paramname}_RECOMMEND_OK");
    	}
		return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
	}

	/**
	 * SHMTOTAL Recommendation method
	 *
	 * If set to 0 (unlimited), it ok.
	 * Otherwise, select sum(seg_size)*1.1 from syssegments,
	 * if SHMTOTAL is < query sum, recommend SHMTOTAL is increased to
	 * that sum or higher.
	 */
	public function SHMTOTAL_recommendation ($value, $idsadmin)
	{
		if ($value == 0)
		{
			$recommendation = $idsadmin->lang("SHMTOTAL_RECOMMEND_OK");
			return Array('recommendation'=>$recommendation,'compliance'=>true);
		}

		$dbsysmaster = $idsadmin->get_database("sysmaster");
		$qry = "select sum(seg_size) * 1.1 as sum from syssegments";
		$stmt = $dbsysmaster->query( $qry );
		if (($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
		{
		    // error running sysmaster query?  then return no recommendation
		    return Array('recommendation'=>"",'compliance'=>null);
		}
		$seg_size_sum = trim($res['SUM']);
		$compliance = ($value >= $seg_size_sum)? true:false;
		if ($compliance)
		{
			$recommendation = $idsadmin->lang("SHMTOTAL_RECOMMEND_OK");
		} else {
			$recommendation = $idsadmin->lang("SHMTOTAL_RECOMMEND_NO") . "$seg_size_sum.";
		}
		return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
	}


	/**
	 * TBLTBL Recommendation methods
	 * Used for TBLTBLFIRST and TBLTBLNEXT
	 *
	 * select max(npused), avg(npused), min(npused) from sysactptnhdr
	 * where mod(partnum,1048576) = 1
	 *
	 * Recommend TBLTBLFIRST be at or above the average.
	 * Recommend TBLTBLNEXT be at or above the 25% of the average.
	 * Display average, min, and max for informational purposes.
	 */
	public function TBLTBL_recommendation ($paramname, $value, $idsadmin)
	{
		$dbsysmaster = $idsadmin->get_database("sysmaster");
		$qry = "select max(npused) as max, avg(npused) as avg, min(npused) as min " .
			"from sysactptnhdr where mod(partnum,1048576) = 1";
		$stmt = $dbsysmaster->query( $qry );
		if (($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
		{
		    // error running sysmaster query?  then return no recommendation
		    return Array('recommendation'=>"",'compliance'=>null);
		}
		$max = $res['MAX'];
		$avg = $res['AVG'];
		$min = $res['MIN'];
		if ($paramname == "TBLTBLFIRST") {
			$compliance = ($value >= $avg)? true:false;
		} else { // $paramaname = "TBLTBLNEXT"
			$compliance = ($value >= (.25 *$avg))? true:false;
		}
		$recommendation = $idsadmin->lang("{$paramname}_RECOMMEND") .
			"  avg=$avg; max=$max; min=$min";
		return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
	}

	/**
	 * TEMPTAB_NOLOG Recommendation method
	 *
	 * If in an HDR or MACH11 environment, recommend TEMPTAB_NOLOG is on
	 * if the applications support it.
	 */
	public function TEMPTAB_NOLOG_recommendation ($value, $idsadmin)
	{
		$dbsysmaster = $idsadmin->get_database("sysmaster");
		$qry = "select ha_type FROM sysha_type";
		$stmt = $dbsysmaster->query( $qry );
		if (($res = $stmt->fetch(PDO::FETCH_ASSOC))==false)
		{
	        // error running sysmaster query?  then return no recommendation
	        return Array('recommendation'=>"",'compliance'=>null);
	    }
    	if ($res['HA_TYPE'] != 0) {
			// in HDR/Mach11 env, recommend on
    		$compliance = ($value == 1)? true:false;
    		$recommendation = $idsadmin->lang("TEMPTAB_NOLOG_RECOMMEND");
    	}
		return Array('recommendation'=>$recommendation,'compliance'=>$compliance);
	}
}
?>
