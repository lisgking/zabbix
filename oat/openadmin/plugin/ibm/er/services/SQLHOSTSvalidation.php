<?php
/*
 ************************************************************************
 *  Licensed Materials - Property of IBM
 *
 *  "Restricted Materials of IBM"
 *
 *  OpenAdmin Tool for IDS
 *  Copyright IBM Corporation, 2008, 2012.  All rights reserved.
 ************************************************************************
 */

/*
 * This function performs minimal validation on the SQLHOSTS file(s) of a 
 * new ER node and its sync server (if one is involved)
 * 
 * It only validates the SQLHOSTS file of the new node and the sync node. It does not validate SQLHOSTS file in the 
 * entire ER domain.
 * 
 * $conn = connection handle to the server to validate (i.e. the server to be defined as a new ER node)
 * $case = NewDomain/ExistingDomainLeaf/ExistingDomainRootNonRoot (If case is NULL, it will be regarded as NewDomain)
 * $newnodeserver = dbservername of new node
 * $newnodegroup  = group name of new node
 * $syncgroup     = group name of sync node
 */
function CheckSQLHOSTS($idsadmin,$conn,$case,$newnodeserver,$newnodegroup,$syncgroup)
 {

 		/**
 		 * Define constants for retcodes
 		 * 1 = SQLHOSTS file fails the test
 		 * 2 = Error in running the test
 		 **/
 		define ("FAIL", 1);
 		define ("ERROR", 2);
 		
 		/*
		 * $retcode = 1 (SQLHOSTS file fails the test), 2 (error in running the test)
		 * $retmsg  - return message
		 * 
		 * If all test are successful, the return array will be empty 
		 * */
		$retArray = array();
		
		if(empty($conn))
		{
			$ret = array('retcode'=>ERROR
						,'retmsg'=>"CheckSQLHOSTS() connection handle is empty"
					);
			array_push($retArray,$ret);
			return FormatRetArray($retArray);
		}
		
		// If newnodegroup is not defined in newnode's SQLHOSTS, break and return fail
		if(!isGroupExist($idsadmin,$conn,$retArray,array($newnodegroup),$newnodegroup))
		{
			return FormatRetArray($retArray);
		}
		// isGroupsUnique will test if all server groups in newnode's SQLHOSTS have unique id
		isGroupsUnique($idsadmin,$conn,$retArray,$newnodegroup);
		// isStandaloneNode will test if newnodegroup is a standalone group in newnode's SQLHOSTS file.
		// if newnodegroup is not standalone, break and return fail.
		if(isStandaloneNode($idsadmin,$conn,$retArray,$newnodegroup,$newnodegroup,$newnodeserver))
		{
			//isNETTYPECompat will test if NETTYPE is compatible, plus check that there is another available
			//DBSERVERALIAS for ER to use if csm is enabled.
			isNETTYPECompat($idsadmin,$conn,$retArray,array($newnodegroup),$newnodegroup);
		} else {
			return FormatRetArray($retArray);
		}
		
		switch($case)
		{
			case 'NewDomain':
			//All tests are completed above, outside the switch statement.
			break;
			
			case 'ExistingDomainLeaf':
			// Test if we could establish trusted connection with syncgroup.
			// If not, all the test on the sync server won't run and cdr define server won't run.
			// break and return fail.	
			if(!isTrustedConnection($idsadmin,$conn,$retArray,$syncgroup))
			{
				break;
			}
			
			// Test if the sync server is Root/NonRoot. Test if syscdr databse is defined on sync server. Else return fail.
			if(!isERActive($idsadmin,$conn,$retArray,$syncgroup)||!isRootOrNonRoot($idsadmin,$conn,$retArray,$syncgroup))
			{
				break;
			}
			
			// Test if SyncNode's group is defined on NewNode's SQLHOSTS file.
			if(isGroupExist($idsadmin,$conn,$retArray,array($syncgroup),$newnodegroup))
			{
				//Test if the SyncNode's group svrid matches in the NewNode's and SyncNode's SQLHOSTS file
				isGroupIDMatch($idsadmin,$conn,$retArray,array($syncgroup),$newnodegroup,$syncgroup);
				
				//Test if the syncgroup has compatible network settings in NewNode's SQLHOSTS file.
				isNETTYPECompat($idsadmin,$conn,$retArray,array($syncgroup),$newnodegroup);
			}
			// Test if NewNode's group is defined on SyncNode's SQLHOSTS file
			if(isGroupExist($idsadmin,$conn,$retArray,array($newnodegroup),$syncgroup))
			{
				// Test if the svrid is unique for all groups in SyncNode's SQLHOSTS file
				isGroupsUnique($idsadmin,$conn,$retArray,$syncgroup);
				// Test if the NewNode's group svrid matches in the NewNode's and SyncNode's SQLHOSTS file
				isGroupIDMatch($idsadmin,$conn,$retArray,array($newnodegroup),$syncgroup,$newnodegroup);
				
				// Test if the NewNode's group is standalone on the SyncNode's SQLHOSTS file
				if(isStandaloneNode($idsadmin,$conn,$retArray,$newnodegroup,$syncgroup,$newnodeserver))
				{
					isNETTYPECompat($idsadmin,$conn,$retArray,array($newnodegroup),$syncgroup);
				}
			}
			break;
			
			case 'ExistingDomainRootNonRoot':			
			if(!isTrustedConnection($idsadmin,$conn,$retArray,$syncgroup))
			{
				break;
			}
			
			if(!isERActive($idsadmin,$conn,$retArray,$syncgroup)||!isRootOrNonRoot($idsadmin,$conn,$retArray,$syncgroup))
			{
				break;
			}
			
			// Get all ER nodes from syncserver's sysmaster:syscdrhost table.
			$syncgroup_ernodes = getERNodes($conn,$retArray,$syncgroup);
			
			// Test if all ER nodes' group is defined in NewNode's SQLHOSTS file
			if(isGroupExist($idsadmin,$conn,$retArray,$syncgroup_ernodes,$newnodegroup))
			{
				// Test if svrid of all ER nodes' group matches in NewNode's and SyncNode's SQLHOSTS file
				isGroupIDMatch($idsadmin,$conn,$retArray,$syncgroup_ernodes,$newnodegroup,$syncgroup);
				
				//Test if all ER nodes have compatible NETTYPE and csm settings in NewNode's SQLHOSTS file
				isNETTYPECompat($idsadmin,$conn,$retArray,$syncgroup_ernodes,$newnodegroup);
			}
			
			// Test if NewNode's group is defined in SyncNode's SQLHOSTS file		
			if(isGroupExist($idsadmin,$conn,$retArray,array($newnodegroup),$syncgroup))
			{
				// Test if the group svrid unique on SyncNode's SQLHOSTS file.
				isGroupsUnique($idsadmin,$conn,$retArray,$syncgroup);
				
				// Test if the NewNode's group svrid matches in SyncNode's and NewNode's SQLHOSTS file.
				isGroupIDMatch($idsadmin,$conn,$retArray,array($newnodegroup),$syncgroup,$newnodegroup);
				
				// Test if the NewNode's group is standalone in SyncGroup's SQLHOSTS file
				if(isStandaloneNode($idsadmin,$conn,$retArray,$newnodegroup,$syncgroup,$newnodeserver))
				{
					// Test if NewNode have compatible NETTYPE and csm settings in SyncNode's SQLHOSTS file.
					isNETTYPECompat($idsadmin,$conn,$retArray,array($newnodegroup),$syncgroup);
				}
			}
			break;
			
			default:
			break;
		}
		
		$ret = FormatRetArray($retArray);
		
		return $ret;
 }

 /*
  * Checks in $Node's SQLHOSTS file
  * Returns true if groups in $Node's SQLHOSTS file has unique group ids, returns false and populates the 
  * &$retArray with error messages if it doesn't.
  * 
  * Parameters:
  * $conn		: PDO connection object.
  * &$retArray	: An array to populate error messages and return codes.
  * $Node		: We check the SQLHOSTS file on $Node. Could be a servername or a groupname
  * */
 function isGroupsUnique($idsadmin,$conn,&$retArray,$Node)
 {
 	if (empty($conn)||empty($Node))
 	{
		$ret = array('retcode'=>ERROR
					,'retmsg'=>"CheckSQLHOSTS::isGroupsUnique() Some required parameters are empty"
					);
		array_push($retArray,$ret);
 		return false;
 	}
 	
 	$qry = "SELECT svrid AS total_count " .
 			"FROM sysmaster@{$Node}:syssqlhosts WHERE nettype='group' and options like '%i=%'";
 	$qry_stmt = dodatabasework($conn,$retArray,$qry);
 	if(empty($qry_stmt)) return false;
    
    $total_count = array();
    while($res = $qry_stmt->fetch(PDO::FETCH_ASSOC))
    {
    	array_push($total_count,trim($res['TOTAL_COUNT']));
    }
    
    $unique_count = array_unique($total_count);   
    $dup_ids = array_values(array_diff_assoc($total_count,$unique_count));
 	
 	if (!empty($dup_ids))
 	{
 		$dup_ids_str = trim(implode(",",$dup_ids));
 		$ret = array('retcode'=>FAIL
					,'retmsg'=>$idsadmin->lang('ER_Groups_Not_Unique', array($Node,$dup_ids_str))
					);
		array_push($retArray,$ret);
 		return false;
 	}else{
 		return true;
 	}
 }
 
 /*
  * Returns true if the groups in the $grouparray exist in $Node's SQLHOSTS file
  * returns false and populates the &$retArray with error messages if it doesn't.
  * 
  * Parameters:
  * $conn		: PDO connection object.
  * &$retArray	: An array to populate error messages and return codes.
  * $grouparray	: An array, populated with groupnames. Function will check if these groups exist in 
  * 				$Node's SQLHOST file. Groupnames should be in lower case letters.
  * $Node		: We check the SQLHOSTS file on $Node. Could be a servername or a groupname
  * */
 function isGroupExist($idsadmin,$conn,&$retArray,$grouparray,$Node)
 {
 	if (empty($conn)||empty($Node))
 	{
 		$ret = array('retcode'=>ERROR
					,'retmsg'=>"CheckSQLHOSTS::isGroupExist() Some required parameters are empty"
					);
		array_push($retArray,$ret);
 		return false;
 	}
 	
 	$group_str = trim(implode("','",$grouparray));
 	
 	$qry = "SELECT dbsvrnm FROM sysmaster@{$Node}:syssqlhosts " .
 			"WHERE nettype='group' AND dbsvrnm IN ('{$group_str}')";
 	$qry_stmt = dodatabasework($conn,$retArray,$qry);
 	if(empty($qry_stmt))
 	{
 		$existgroups = array();
 	} else {
 		$existgroups = array();
 		while($res = $qry_stmt->fetch(PDO::FETCH_ASSOC))
 		{
 			array_push($existgroups,trim($res['DBSVRNM']));
 		}
 	}
 	
 	$missinggroups = array_values(array_diff($grouparray,$existgroups));
 	
 	if(!empty($missinggroups))
 	{
 		$str = trim(implode(",",$missinggroups));
 		$ret = array('retcode'=>FAIL
					,'retmsg'=>$idsadmin->lang('ER_Group_Does_Not_Exist', array($Node,$str))
					);
		array_push($retArray,$ret);
 		return false;
 	}else{
 		return true;
 	}
 }
 /*
  * Returns true if all members of the groups in $grouparray have ER compatible NETTYPES.
  * 
  * Parameters:
  * $conn		: PDO connection object.
  * &$retArray	: An array to populate error messages and return codes.
  * $grouparray	: An array, populated with groupnames. Function will check if members of these groups 
  * 				have compatible NETTYPES
  * $Node		: We check the SQLHOSTS file on $Node. Could be a servername or a groupname
  * */
 function isNETTYPECompat($idsadmin,$conn,&$retArray,$grouparray,$Node)
 {
 	if (empty($conn)||empty($Node))
 	{
 		$ret = array('retcode'=>ERROR
					,'retmsg'=>"CheckSQLHOSTS::isNETTYPECompat() Some required parameters are empty"
					);
		array_push($retArray,$ret);
 		return false;
 	}
 	
 	$group_str = trim(implode("','",$grouparray));
 	
 	$qry ="SELECT UNIQUE svrgroup FROM sysmaster@{$Node}:syssqlhosts " .
 			"WHERE svrgroup IN ('{$group_str}') " .
 			"AND netprot IN ('soctcp','tlitcp','socssl','tlispx','socimc','tliimc','sqlmux') " .
 			"AND svrtype IN ('on','ol') " .
 			"AND hostname IS NOT NULL " .
 			"AND svcname IS NOT NULL " .
 			"AND options NOT LIKE '%csm=%'";
 	$qry_stmt = dodatabasework($conn,$retArray,$qry);
 	if(empty($qry_stmt)) return false;
 	
 	$compatible_groups = array();
 	while($res = $qry_stmt->fetch(PDO::FETCH_ASSOC)){
 		array_push($compatible_groups,trim($res['SVRGROUP']));
 	}
 	$incompatible_groups = array_values(array_diff($grouparray,$compatible_groups));
 	
 	if (!empty($incompatible_groups)){
 		$str = trim(implode(",",$incompatible_groups));
 		$ret = array('retcode'=>FAIL
					,'retmsg'=>$idsadmin->lang('Invalid_NETTYPE', array($Node))
					);
		array_push($retArray,$ret);
 		return false;
 	}else{
 		return true;
 	}
 }
 
 /*
  * Returns true is all members of $group are the DBSERVERNAME or DBSERVERALIASES 
  * of the same standalone server
  * I.e. members can only be one server and it's aliases. It cannot be multiple servers
  * 
  * Parameters:
  * $conn		: PDO connection object.
  * &$retArray	: An array to populate error messages and return codes.
  * $group		: The group to be checked to be a standalone server. We also call getAliases($conn,&$retArray,$group)
  * 			  to get its aliases.
  * $Node		: We check the SQLHOSTS file on $Node. Could be a servername or a groupname
  * $servername : server name
  * */
 function isStandaloneNode($idsadmin,$conn,&$retArray,$group,$Node,$servername)
{
 	if (empty($conn)||empty($group)||empty($Node))
 	{
 		$ret = array('retcode'=>ERROR
					,'retmsg'=>"CheckSQLHOSTS::isStandaloneNode() some required parameters are empty"
					);
		array_push($retArray,$ret);
 		return false;
 	}
 	
 	// For server versions 12.10 and above, OAT does support defining an ER server on a primary in a cluster (but not secondaries).
 	// For server versions below 12.10, OAT not suport defining ER server on any server that is part of a cluster.
 	
 	// So start by getting the server version and server type.
 	$qry = "SELECT DBINFO('version','full') AS version, ha_type FROM sysha_type";
 	$qry_stmt = dodatabasework($conn,$retArray,$qry);
 	$row = $qry_stmt->fetch(PDO::FETCH_ASSOC);
 	$server_version = $row['VERSION'];
 	$ha_type = $row['HA_TYPE'];
 	
 	// If version >= 12.10, then make sure the server is either a primary or stand-alone and return.
 	require_once(ROOT_PATH."lib/feature.php");
 	if (Feature::isAvailable(Feature::CENTAURUS, $server_version))
 	{
 		if ($ha_type == 0 || $ha_type == 1)
 		{
 			// 12.10 stand-alone server or 12.10 primary server, so it is ok to use OAT to define this server for ER. 
 			return true;
 		} else {
 			// 12.10 secondary server.  Can't use OAT to define this server for ER.  User should define ER on the primary.
 			$ret = array('retcode'=>FAIL
					,'retmsg'=>$idsadmin->lang('Server_Not_Primary', array($servername))
					);
			array_push($retArray,$ret);
			return false;
 		}
 	} 
 	
 	// For versions < 12.10, make sure the server is a stand-alone and has no other group members defined in the SQLHOSTS.
 	$alias_str = trim(implode("','",getAliases($conn,$retArray,$group)));
 	
 	$qry = "SELECT dbsvrnm FROM sysmaster@{$Node}:syssqlhosts " .
 			"WHERE svrgroup = '{$group}' " .
 			"AND dbsvrnm NOT IN ('{$alias_str}')";
 			
 	$qry_stmt = dodatabasework($conn,$retArray,$qry);
 	if(empty($qry_stmt)) return false;
    
    $NotStandaloneNodes = array();
    while($res = $qry_stmt->fetch(PDO::FETCH_ASSOC)){
    	array_push($NotStandaloneNodes,trim($res['DBSVRNM']));
    }
    
    if(!empty($NotStandaloneNodes))
    {
    	$str = trim(implode(",",$NotStandaloneNodes));
    	$ret = array('retcode'=>FAIL
					,'retmsg'=>$idsadmin->lang('Server_Not_StandAlone', array($Node, $group, $str))
					);
		array_push($retArray,$ret);
 		return false;
    }
 	return true;
}


/*
 * Returns true if there is trusted connection from $conn to $Node
 * */ 
 function isTrustedConnection($idsadmin,$conn,&$retArray,$Node)
 {
 	if (empty($conn)||empty($Node))
 	{
 		$ret = array('retcode'=>ERROR
					,'retmsg'=>"CheckSQLHOSTS::isTrustedConnection() some required parameters are empty"
					);
		array_push($retArray,$ret);
 		return false;
 	}
 	
 	$qry = "SELECT * FROM sysmaster@{$Node}:syssqlhosts";
 	$stmt = $conn->query($qry);
 	$err  = $conn->errorInfo();
 	
 	if (isset($err[1]) && $err[1] != 0)
    {
        $error_str = "{$idsadmin->lang("Error")}: {$err[2]} - {$err[1]}";
        $ret = array('retcode'=>FAIL
					,'retmsg'=>$idsadmin->lang('No_Trusted_Connection', array($Node,$error_str))
					);
		array_push($retArray,$ret);
 		return false;
    }else{
    	return true;
    }
 }
 
 function isGroupIDMatch($idsadmin,$conn,&$retArray,$grouparray,$NodeToCheckFrom,$NodeToCheckAgainst)
 {
 	if (empty($conn)||empty($grouparray)||empty($NodeToCheckFrom)||empty($NodeToCheckAgainst))
 	{
  		$ret = array('retcode'=>ERROR
					,'retmsg'=>"CheckSQLHOSTS::isGroupIDMatch()) some required parameters are empty"
					);
		array_push($retArray,$ret);
 		return false;
 	}

	$group_str = trim(implode("','",$grouparray));

 	$qry = "SELECT A.dbsvrnm AS UNMATCH_SERVERS " .
 			"FROM sysmaster@{$NodeToCheckFrom}:syssqlhosts AS A, " .
 			"sysmaster@{$NodeToCheckAgainst}:syssqlhosts AS B " .
 			"WHERE A.nettype='group' AND B.nettype='group' " .
 			"AND A.svrid != B.svrid " .
 			"AND A.dbsvrnm = B.dbsvrnm " .
 			"AND A.dbsvrnm IN ('{$group_str}') " .
 			"AND B.dbsvrnm IN ('{$group_str}') ";
 	
 	$qry_stmt = dodatabasework($conn,$retArray,$qry);
 	if(empty($qry_stmt)) return false;
 
 	$Unmatch_servers = array();
    while($res = $qry_stmt->fetch(PDO::FETCH_ASSOC)){
    	array_push($Unmatch_servers,trim($res['UNMATCH_SERVERS']));
    }
    
    if(!empty($Unmatch_servers))
    {
    	$str = trim(implode(",",$Unmatch_servers));
    	$ret = array('retcode'=>FAIL
					,'retmsg'=>$idsadmin->lang('No_Group_ID_Match', array($str, $NodeToCheckFrom, $NodeToCheckAgainst))
					);
		array_push($retArray,$ret);
 		return false;
    }
 	return true;
 }
 
 function isERActive($idsadmin,$conn,&$retArray,$server)
 {
 	if (empty($conn)||empty($server))
 	{
 		$ret = array('retcode'=>ERROR
					,'retmsg'=>"CheckSQLHOSTS::isERActive() some required parameters are empty"
					);
		array_push($retArray,$ret);
 		return false;
 	}
 	
 	$qry = "SELECT count(*) FROM sysmaster@{$server}:sysdatabases WHERE name='syscdr'";
 	$qry_stmt = dodatabasework($conn,$retArray,$qry);
 	if(empty($qry_stmt)) return false;
 	
 	$res = $qry_stmt->fetch(PDO::FETCH_ASSOC);
 	if($res['']==0)
 	{
 		$ret = array('retcode'=>FAIL
					,'retmsg'=>$idsadmin->lang('ER_Not_Active', array($server))
					);
		array_push($retArray,$ret);
 		return false;
 	}
 	return true;
 }
 
 function isRootOrNonRoot($idsadmin,$conn,&$retArray,$server)
 {
 	if (empty($conn)||empty($server))
 	{
 		$ret = array('retcode'=>ERROR
					,'retmsg'=>"CheckSQLHOSTS::isRootOrNonRoot() some required parameters are empty"
					);
		array_push($retArray,$ret);
 		return false;
 	}
 	
 	$qry = "SELECT count(*) FROM sysmaster@{$server}:syscdrs " .
 			"WHERE servname = '{$server}' " .
 			"AND isleaf = 'N'";
 	$qry_stmt = dodatabasework($conn,$retArray,$qry);
 	if(empty($qry_stmt)) return false;
    
    $res = $qry_stmt->fetch(PDO::FETCH_ASSOC);
 	if($res['']==0)
 	{
 		$ret = array('retcode'=>FAIL
					,'retmsg'=>$idsadmin->lang('Server_is_Leaf', array($server))
					);
		array_push($retArray,$ret);
 		return false;
 	}
 	return true;
 }
  
 function getAliases($conn,&$retArray,$server)
 {
 	if (empty($conn)||empty($server))
 	{
 		$ret = array('retcode'=>ERROR
					,'retmsg'=>"CheckSQLHOSTS::getAliases() some required parameters are empty"
					);
		array_push($retArray,$ret);
 		return false;
 	}
 	
 	$qry ="SELECT cf_original AS aliases FROM sysmaster@{$server}:sysconfig " .
 			"WHERE cf_name='DBSERVERALIASES'";
 	$qry_stmt = dodatabasework($conn,$retArray,$qry);
 	if(empty($qry_stmt)) return false;
 	
 	$res = $qry_stmt->fetch(PDO::FETCH_ASSOC);
 	$aliases = explode(",",$res['ALIASES']);
 	
 	$qry ="SELECT cf_original AS DBSERVERNAME FROM sysmaster@{$server}:sysconfig " .
 			"WHERE cf_name='DBSERVERNAME'";
 	$qry_stmt = dodatabasework($conn,$retArray,$qry);
 	if(empty($qry_stmt)) return false;
 	
 	$res = $qry_stmt->fetch(PDO::FETCH_ASSOC);
 	$DBSERVERNAME = trim($res['DBSERVERNAME']);
 	array_push($aliases,$DBSERVERNAME);
 	
 	return $aliases;
 }
/*
 * Returns an array of ER Nodes associated with the $server. The return array includes $server itself
 * */
function getERNodes($conn,&$retArray,$server)
{
	if (empty($conn)||empty($server))
 	{
 		$ret = array('retcode'=>ERROR
					,'retmsg'=>"CheckSQLHOSTS::getERNodes() some required parameters are empty"
					);
		array_push($retArray,$ret);
 		return false;
 	}
 			
 	$qry = "SELECT servname AS ER_GROUPS " .
 			"FROM sysmaster@{$server}:syscdrs ";
 	
 	$qry_stmt = dodatabasework($conn,$retArray,$qry);
 	if(empty($qry_stmt)) return false;
 	
 	$er_nodes = array();
 	while($res = $qry_stmt->fetch(PDO::FETCH_ASSOC))
 	{
 		array_push($er_nodes,trim($res['ER_GROUPS']));
 	}
 	
 	return $er_nodes;
}

function dodatabasework($conn,&$retArray,$qry)
{
	$stmt = $conn->query($qry);
 	$err=$conn->errorInfo();
 	if (isset($err[1]) && $err[1] != 0)
    {
        $error_str = "Statement Error: {$err[2]} - {$err[1]}";
        $ret = array('retcode'=>ERROR
					,'retmsg'=>"Failed to issue query: {$qry} -- ".$error_str
					);
		array_push($retArray,$ret);
 		return;
    }
    return $stmt;
}

/*
 * FormatRetArray
 * 
 * Formats the $retArray object so that it only displays fail test messages, not error messages like 
 * empty function parameters and stuff.
 * 
 * retArray@param - The $retArray object to format.
 * 
 * Return Value:
 * $ret = array ("VALID"=> true/false
 * 				"ERROR" => array(1=>"message 1...", 2=>"message 2....", 3=>"message 3....")
 * 				)
 * */
function FormatRetArray($retArray)
{
	$VALID = (empty($retArray))?true:false;
	$ERROR = ($VALID)?"":array();
	
	if(!$VALID)
	{
		foreach($retArray as $i=>$v)
		{
			if($v['retcode']==FAIL) 
			{
				// Return 'FAIL' messages to Flex
				array_push($ERROR,$v['retmsg']);
			} else {
				// Error log 'ERROR' messages
				error_log("ERROR: {$v['retmsg']}");				
			}
		}
	}
	
	$ret = array("VALID"=>$VALID,
				"ERROR"=>$ERROR);
	
	return $ret;
}


?>
