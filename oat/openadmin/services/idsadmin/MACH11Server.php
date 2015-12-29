<?php
/*
 **************************************************************************   
 *  (c) Copyright IBM Corporation. 2007, 2011.  All Rights Reserved
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

require_once ( "IDSDaemonControl.php" );

class MACH11Server 
{
	const TEMPDIR = "../../tmp";
	
    private $db; // Connection to SQLite DB
    private $idsadmin;
    private $group_num;
    private $serverdbs = array();
    
	function __construct ( )
	{
		define ("ROOT_PATH","../../");
        define( 'IDSADMIN',  "1" );
        define( 'DEBUG', false);
        define( 'SQLMAXFETNUM' , 100 ); 

		include_once("../serviceErrorHandler.php");
		set_error_handler("serviceErrorHandler");

		require_once ( "clusterdb.php" );
		$this->db = new clusterdb ( );
		
		require_once(ROOT_PATH."lib/idsadmin.php");
		$this->idsadmin = new idsadmin(true);
		$this->group_num = $this->idsadmin->phpsession->get_group();
        
		$this->idsadmin->load_lang("ca");
		//pull in the lib/connections.php for the encode/decode password 
		// functions..
		
		require_once (ROOT_PATH."lib/connections.php");		
		//$this->db = new connections($this->idsadmin);
	}

    function test ( )
	{
        $sel = "SELECT count(*) from systables";
        $row = $this->doDatabaseWork ( $sel, "sysmaster" );
		return $row;
	}

	public function getConnectionManagerSLA ( $server
                                            , $sid )
	{
		$query = "SELECT address      "
               . "     , sid          "
               . "     , sla_id       "
               . "     , TRIM ( sla_name   ) AS sla_name   "
               . "     , TRIM ( sla_define ) AS sla_define " 
               . "     , connections  "
               . "  FROM syscmsmsla   "
               . " WHERE sid = {$sid} ";

		$result = $this->doIDSDatabaseWork ( $server 
                                           , "sysmaster"
                                           , $query );

		return $result;
	}

	public function getConnectionManagers ( $server )
	{
		$query = "SELECT address "
               . "     , sid     "
               . "     , TRIM ( name ) AS name "
               . "     , TRIM ( host ) AS host "
               . "     , TRIM ( foc  ) AS foc  "
               . "     , flag    "
               . "  FROM syscmsmtab ";

		$result = $this->doIDSDatabaseWork ( $server
                                           , "sysmaster"
                                           , $query );

		return $result;
	}

	public function updateConnectionManager ( $server 
                                            , $description )
	{
/*
		error_log ( "updateConnectionManager ( \"" 
                  . $server 
                  . "\", \""
	              . $description
                  . "\" )" );
*/

		$query = "INSERT INTO sysrepstats ( repstats_type     "
               . "                        , repstats_subtype  "
               . "                        , repstats_desc )   "
               . "                 VALUES ( 8                 "
               . "                        , 1                 "
               . "                        , '{$description}' )";

		$this->doIDSDatabaseWork ( $server
                                 , "sysmaster"
                                 , $query );

		return true;
	}
	
	function addClusterToCache ( $clusterName )
	{
		$query = "INSERT INTO clusters ( cluster_name, group_num ) "
               . "              VALUES ( '{$clusterName}', $this->group_num) ";
               
		$row = $this->doDatabaseWork ( $query ); 

		$query = "SELECT cluster_id "
               . "  FROM clusters   "
               . " WHERE cluster_name = '{$clusterName}' "
               . " AND group_num = $this->group_num";

		$row = $this->doDatabaseWork ( $query ); 

		$clusterId = $row [ 0 ] [ 'cluster_id' ];
	
		return $clusterId;
	}

	function addServerToCache ($group_num
                              , $host
                              , $port
                              , $server 
                              , $idsprotocol 
                              , $lat
                              , $lon
                              , $username
                              , $password
                              , $cluster_id
                              , $last_type )
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
               . " VALUES (  {$group_num}   "
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

        $this->doDatabaseWork ( $query );
	    return $this->db->lastInsertId ( );
        //return sqlite_last_insert_rowid ( $this->db );
	}

	function addServerToClusterCache ( $clusterName
                                     , $serverName 
                                     , $serverType )
	{
		$query = "SELECT cluster_id "
               . "  FROM clusters   "
               . " WHERE cluster_name = '{$clusterName}' "
               . " AND group_num = {$this->group_num}";

		$row = $this->doDatabaseWork ( $query );

		$clusterId = $row [0]['cluster_id'];

		$query = "UPDATE connections "
               . "   SET ( cluster_id, last_type )       "
               . "     = ( {$clusterId}, {$serverType} ) "
               . " WHERE server = '{$serverName}'        "
               . " AND group_num = {$this->group_num}";

		$row = $this->doDatabaseWork ( $query );
		return $row;
	}

	function removeAllClustersFromCache ( )
	{
		$query = "DELETE FROM clusters "
				." WHERE group_num = {$this->group_num}";
		$this->doDatabaseWork ( $query );
		return 0;
	}

	function removeClusterFromCache ( $clusterName )
	{
		$query = "UPDATE connections         "
			   . "   SET cluster_id = 0      "
               . "     , last_type  = 0      "
               . " WHERE cluster_id =        "
               . "       ( SELECT cluster_id "
               . "           FROM clusters   "
               . "          WHERE cluster_name = '{ $clusterName }' "
               . "          AND group_num = {$this->group_num} ) "
               . " AND group_num = {$this->group_num} ";

		$row = $this->doDatabaseWork ( $query );
		
		$query = "DELETE FROM clusters "
               . " WHERE cluster_name = '{$clusterName}' "
               . " AND group_num = {$this->group_num}";
		$row = $this->doDatabaseWork ( $query );
		return $row;
	}

	function updateServerInCache ( $server
                                 , $clusterId
                                 , $serverType )
	{
		$query = "UPDATE connections "
               . "   SET cluster_id = {$clusterId}  "
               . "     , last_type  = {$serverType} "
               . " WHERE server = '{$server}' "
               . " AND group_num = {$this->group_num}";

		$this->doDatabaseWork ( $query );

		return 0;
	}

	function getServerAliases ( $server )
	{
		$query = "SELECT TRIM ( cf_effective ) AS cf_effective "
               . "  FROM syscfgtab "
               . " WHERE cf_name = 'DBSERVERALIASES' ";

		$result = $this->doIDSDatabaseWork ( $server
                                           , "sysmaster"
                                           , $query );

        $aliases = array ( );

        foreach ( $result as $row )
		{
			$list = explode ( ',', $row [ 'CF_EFFECTIVE' ] );

            foreach ( $list as $alias )
			{
                $sqlhosts = $this->getSqlHostEntryForServer ( $server
                                                            , $alias );
                foreach ( $sqlhosts as $sqlhost ) 
               	{
                	$aliases [ ] = $sqlhost;
				}
			}
		}

		return $aliases;
	}

	function getServerConfiguration ( $server ) 
	{
		$query = "SELECT TRIM ( cf_name      ) AS cf_name      "
               . "     , TRIM ( cf_original  ) AS cf_original  "
               . "     , TRIM ( cf_effective ) AS cf_effective "
               . "     , TRIM ( cf_default   ) AS cf_default   "
               . "  FROM syscfgtab ";

		$result = $this->doIDSDatabaseWork ( $server
                                           , "sysmaster"
                                           , $query );

		return $result;
	}

    function getServerFromCache ( $server )
	{
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
               . " AND group_num = {$this->group_num}";

        $result = $this->doDatabaseWork ( $query , true );
		return $result;
	}

	/**
	 * Get servers from the connections.db
	 * 
	 * @param $conn_num - if null, get all servers in the current OAT group, 
	 *                    else only get the specified conn_num.
	 * @param $server_names array - get servers in the current OAT group
	 *                    with the specified names.
	 */
    function getServersFromCache ( $conn_num = null, $server_names = array() )
	{
		if (!empty($server_names))
		{
			$server_names_list = "'" . implode ("','", $server_names) . "'";
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
               . " FROM connections   "
               . " WHERE group_num = {$this->group_num} "
               . (($conn_num != null)? " AND conn_num = {$conn_num} ":"")
               . ((!empty($server_names))? " AND server in ($server_names_list) ":"")
               . " ORDER BY server     ";

        $row = $this->doDatabaseWork ( $query ,true );
		return $row;
	}
	
	function getServersInCluster ( $clusterName )
	{
        $query = "SELECT a.conn_num      "
               . "     , a.group_num     "
               . "     , a.nickname      "
               . "     , a.host          "
               . "     , a.port          "
               . "     , a.server        "
               . "     , a.idsprotocol   "
               . "     , a.lat           "
               . "     , a.lon           "
               . "     , a.username      "
               . "     , a.password      "
               . "     , a.lastpingtime  "
               . "     , a.laststatus    "
               . "     , a.laststatusmsg "
               . "     , a.lastonline    "
               . "     , a.cluster_id    "
               . "     , a.last_type     "
               . "  FROM connections a, clusters b         "
               . " WHERE b.cluster_name = '{$clusterName}' "
               . "   AND b.cluster_id   = a.cluster_id     "
               . "   AND b.group_num    = a.group_num      "
               . "   AND a.group_num    = {$this->group_num}";

        $row = $this->doDatabaseWork ( $query , true );
		return $row;
	}

   function getCluster ( $clusterName )
	{
		//error_log ( 'getCluster ( ' . $clusterName . ' )' );
		$updateCache = false;

		/*
         * Get the cluster from the IDSAdmin cache,
		 * and find its primary.
         */

		$cachedCluster = $this->getClusterFromCache ( $clusterName );

        foreach ( $cachedCluster as $server )
		{
			if ( $server [ 'last_type' ] == 1 )
			{
				$cachedPrimary = $server [ 'server' ];
				break;
			}
		}

		/*
         * Check if the cached primary is still active
         */

		if ( isset ( $cachedPrimary ) )
		{
            if ( !$this->isServerReachable ( $cachedPrimary ) )
			{
                /*
                 * The primary is not reachable - see if any secondaries
                 * are online and who they report as the primary
                 */

                $primaries = $this->getPrimaryForCluster ( $cachedCluster );
             	
                /*
                 * Throw an error if:
                 * 
                 * 1. the secondaries report no primary
                 * 2. the secondaries reported multiple primaries
                 * 3. the reported primary is the same as cachedPrimary
                 */

            	if ( count ( $primaries ) == 1 )
				{
					if ( $primaries [ 0 ] != $cachedPrimary )
					{
						$cachedPrimary = $primaries [ 0 ];
                        if ( !$this->isServerReachable ( $cachedPrimary ) )
						{
								$err_str = $this->idsadmin->lang("PrimaryNotReachable");// ( "getCluster: Primary is unreachable ");
								trigger_error( $err_str , E_USER_ERROR);							
						}
					}
					else
					{
								$err_str = $this->idsadmin->lang("ClusterNotReachable");//$err_str = "getCluster: Cluster is unreachable";
								trigger_error( $err_str , E_USER_ERROR);
					}
				}
				else
				{
						$err_str = $this->idsadmin->lang("ClusterBeyondRecognition");//"getCluster: Cluster is beyond recognition ";
						trigger_error( $err_str , E_USER_ERROR);
				}
			}


			$activeCluster = $this->getClusterForServer ( $cachedPrimary );

			if ( isset ( $activeCluster ) )
			{
 				$activePrimary = trim ( $activeCluster [ 0 ][ 'SERVERNAME' ] );
				return $activeCluster;
			}
			else
			{
				/*
                 * The cached primary is no longer clustered. Check if 
				 * another server in the cache became primary.
                 */
				$err_str = $this->idsadmin->lang("CachedPrimaryNotClustered");//"getCluster: Cached primary is not clustered";
				trigger_error( $err_str , E_USER_ERROR);
			}
		}
        else
		{
			/* 
			 * Cached cluster has no primary ? That's bad.
             */
			$err_str = "{$this->idsadmin->lang("NoPrimaryInCluster")}  {$clusterName}";// "getCluster: No primary in cluster {$clusterName}";
			trigger_error( $err_str , E_USER_ERROR);
		}
		
		return $cache;
	}

    /*
     * Determines if server 'server' is part of a cluster and,
     * if so, returns the list of servers in that cluster.
     */
    function getClusterForServer ( $server )
	{
        $primary = $this->getPrimaryForServer ( $server );

		/*
		 * The query below is needed for the fix of defect 194737. The latter defect is a result of a server defect. The server defect has symptoms that we are aware of.
		 * Having an empty syssqlhosts table is one of the symptoms. In the code below we make sure that syssqlhosts is not empty.
		 * Another symptom is when sysha_nodes hangs. The latter symptom is also appears with an empty syssqlhosts.
		 * If defect 194737 appears again after this fix, we must check it's symptoms here or fix the defect in the server side.
		 */
		$query = "SELECT * FROM syssqlhosts;";
	
		$rows = $this->doIDSDatabaseWork ( $primary 
			  							 , "sysmaster"
									     , $query );
		
		if($rows != null) //if can't access syssqlhosts, don't allow OAT to seek the cluster because this causes the query to hang
						  //and end up failing to find the cluster
		{
		
	        $query = "SELECT TRIM ( n.server ) AS servername "
	               . "     , TRIM ( NVL ( h.hostname, '<unknown>' ) ) AS host "
	               . "     , TRIM ( NVL ( h.svcname , '<unknown>' ) ) AS port "
	               . "     , 1 AS type "
	               . "     , 'Active' AS server_status "
	               . "     , 'Connected' AS connection_status "
	               . "  FROM sysha_nodes n "
	               . "     , OUTER syssqlhosts h "
	               . " WHERE n.type   = 'Primary' "
	               . "   AND n.server = h.dbsvrnm "
	               . "UNION "
	               . "SELECT TRIM ( n.server ) AS servername "
	               . "     , TRIM ( NVL ( h.hostname, '<unknown>' ) ) AS host "
	               . "     , TRIM ( NVL ( h.svcname , '<unknown>' ) ) AS port "
	               . "     , 2 AS type "
	               . "     , CASE ( i.state ) "
	               . "            WHEN 'On' THEN 'Active' "
	               . "            ELSE 'Inactive' "
	               . "       END AS server_status "
	               . "     , CASE ( i.state )  "
	               . "            WHEN 'On' THEN 'Connected' "
	               . "            ELSE 'Disconnected' "
	               . "       END AS connection_status "
	               . "  FROM sysha_nodes n "
	               . "     , OUTER syssqlhosts h "
	               . "     , sysdri      i "
	               . " WHERE n.server = i.name "
	               . "   AND n.server = h.dbsvrnm "
	               . "UNION "
	               . "SELECT TRIM ( n.server ) AS servername "
	               . "     , TRIM ( NVL ( h.hostname, '<unknown>' ) ) AS host "
	               . "     , TRIM ( NVL ( h.svcname , '<unknown>' ) ) AS port "
	               . "     , 3 AS type "
	               . "     , TRIM ( s.server_status ) AS server_status "
	               . "     , TRIM ( s.connection_status ) AS connection_status "
	               . "  FROM sysha_nodes n "
	               . "     , OUTER syssqlhosts h "
	               . "     , syssrcsds   s "
	               . " WHERE n.server = s.server_name "
	               . "   AND n.server = h.dbsvrnm "
	               . "UNION "
	               . "SELECT TRIM ( n.server ) AS servername "
	               . "     , TRIM ( NVL ( h.hostname, '<unknown>' ) ) AS host "
	               . "     , TRIM ( NVL ( h.svcname , '<unknown>' ) ) AS port "
	               . "     , 4 AS type "
	               . "     , TRIM ( r.server_status ) AS server_status "
	               . "     , TRIM ( r.connection_status ) AS connection_status "
	               . "  FROM sysha_nodes n "
	               . "     , OUTER syssqlhosts h "
	               . "     , syssrcrss   r "
	               . " WHERE n.server = r.server_name "
	               . "   AND n.server = h.dbsvrnm ";
	
			
	        $rows = $this->doIDSDatabaseWork ( $primary 
				  							 , "sysmaster"
										     , $query );
	
			$i = 0;
			foreach ( $rows as $row )
			{
				if (!is_numeric($row['PORT']))
				{
					$rows[$i]['PORT'] = $this->resolvePortNumber($server, $row['PORT']);
				}
				
				$this->updateServerEnvironmentInCache ( $row [ 'SERVERNAME' ] );
				$i++;
			}
		}

		return $rows;
	}
		
	
	/*
     * Searches the IDSAdmin cache for cluster 'clusterName',
     * and returns the list of servers if found.
     */

	function getClusterFromCache ( $clusterName )
	{
        $query = "SELECT a.conn_num      "
               . "     , a.group_num     "
               . "     , a.nickname      "
               . "     , a.host          "
               . "     , a.port          "
               . "     , a.server        "
               . "     , a.idsprotocol   "
               . "     , a.lat           "
               . "     , a.lon           "
               . "     , a.username      "
               . "     , a.password      "
               . "     , a.lastpingtime  "
               . "     , a.laststatus    "
               . "     , a.laststatusmsg "
               . "     , a.lastonline    "
               . "     , a.cluster_id    "
               . "     , a.last_type     "
               . "  FROM connections a, clusters b         "
               . " WHERE b.cluster_name = '{$clusterName}' "
               . "   AND b.cluster_id   = a.cluster_id     "
               . "   AND b.group_num    = a.group_num      "
               . "   AND a.group_num    = {$this->group_num} ";

        $row = $this->doDatabaseWork ( $query , true );
		return $row;
	}

	function getClustersFromCache ( ) 
	{
        $query = "SELECT cluster_id   "
               . "     , cluster_name "
               . "  FROM clusters     "
               . "  WHERE group_num = {$this->group_num}";
        $row = $this->doDatabaseWork ( $query );
		return $row;
	}

	function getClusterStatus ( $server )
	{
        $primary = $this->getPrimaryForServer ( $server );
 
		$query = " SELECT TRIM ( o.server ) AS servername  "
               . "      , TRIM ( o.type   ) AS servertype  "
               . "      , 'Active'          AS server_status     "
               . "      , 'Connected'       AS connection_status "
               . "      , 1                 AS ranking           "
               . "      , ( ( u.wl_workload_1  + s.wl_workload_1  ) /       "
               . "          ( t.wl_workload_1  * n.wl_workload_1  ) ) * 100 "
               . "        AS cpu_usage_01                                   "
               . "      , ( ( u.wl_workload_2  + s.wl_workload_2  ) /       "
               . "          ( t.wl_workload_2  * n.wl_workload_2  ) ) * 100 "
               . "        AS cpu_usage_02                                   "
               . "      , ( ( u.wl_workload_3  + s.wl_workload_3  ) /       "
               . "          ( t.wl_workload_3  * n.wl_workload_3  ) ) * 100 "
               . "        AS cpu_usage_03                                   "
               . "      , ( ( u.wl_workload_4  + s.wl_workload_4  ) /       "
               . "          ( t.wl_workload_4  * n.wl_workload_4  ) ) * 100 "
               . "        AS cpu_usage_04                                   "
               . "      , ( ( u.wl_workload_5  + s.wl_workload_5  ) /       "
               . "          ( t.wl_workload_5  * n.wl_workload_5  ) ) * 100 "
               . "        AS cpu_usage_05                                   "
               . "      , ( ( u.wl_workload_6  + s.wl_workload_6  ) /       "
               . "          ( t.wl_workload_6  * n.wl_workload_6  ) ) * 100 "
               . "        AS cpu_usage_06                                   "
               . "      , ( ( u.wl_workload_7  + s.wl_workload_7  ) /       "
               . "          ( t.wl_workload_7  * n.wl_workload_7  ) ) * 100 "
               . "        AS cpu_usage_07                                   "
               . "      , ( ( u.wl_workload_8  + s.wl_workload_8  ) /       "
               . "          ( t.wl_workload_8  * n.wl_workload_8  ) ) * 100 "
               . "        AS cpu_usage_08                                   "
               . "      , ( ( u.wl_workload_9  + s.wl_workload_9  ) /       "
               . "          ( t.wl_workload_9  * n.wl_workload_9  ) ) * 100 "
               . "        AS cpu_usage_09                                   "
               . "      , ( ( u.wl_workload_10 + s.wl_workload_10 ) /       "
               . "          ( t.wl_workload_10 * n.wl_workload_10 ) ) * 100 "
               . "        AS cpu_usage_10                                   "
               . "      , ( ( u.wl_workload_11 + s.wl_workload_11 ) /       "
               . "          ( t.wl_workload_11 * n.wl_workload_11 ) ) * 100 "
               . "        AS cpu_usage_11                                   "
               . "      , ( ( u.wl_workload_12 + s.wl_workload_12 ) /       "
               . "          ( t.wl_workload_12 * n.wl_workload_12 ) ) * 100 "
               . "        AS cpu_usage_12                                   "
               . "      , ( ( u.wl_workload_13 + s.wl_workload_13 ) /       "
               . "          ( t.wl_workload_13 * n.wl_workload_13 ) ) * 100 "
               . "        AS cpu_usage_13                                   "
               . "      , ( ( u.wl_workload_14 + s.wl_workload_14 ) /       "
               . "          ( t.wl_workload_14 * n.wl_workload_14 ) ) * 100 "
               . "        AS cpu_usage_14                                   "
               . "      , ( ( u.wl_workload_15 + s.wl_workload_15 ) /       "
               . "          ( t.wl_workload_15 * n.wl_workload_15 ) ) * 100 "
               . "        AS cpu_usage_15                                   "
               . "      , ( ( u.wl_workload_16 + s.wl_workload_16 ) /       "
               . "          ( t.wl_workload_16 * n.wl_workload_16 ) ) * 100 "
               . "        AS cpu_usage_16                                   "
               . "      , ( ( u.wl_workload_17 + s.wl_workload_17 ) /       "
               . "          ( t.wl_workload_17 * n.wl_workload_17 ) ) * 100 "
               . "        AS cpu_usage_17                                   "
               . "      , ( ( u.wl_workload_18 + s.wl_workload_18 ) /       "
               . "          ( t.wl_workload_18 * n.wl_workload_18 ) ) * 100 "
               . "        AS cpu_usage_18                                   "
               . "      , ( ( u.wl_workload_19 + s.wl_workload_19 ) /       "
               . "          ( t.wl_workload_19 * n.wl_workload_19 ) ) * 100 "
               . "        AS cpu_usage_19                                   "
               . "      , ( ( u.wl_workload_20 + s.wl_workload_20 ) /       "
               . "          ( t.wl_workload_20 * n.wl_workload_20 ) ) * 100 "
               . "        AS cpu_usage_20                                   "
               . "      , DBINFO('utc_current') as current_time "
               . "      , 0 AS lt_time_last_update "
               . "      , 0.0 AS lag_time_01 "
               . "      , 0.0 AS lag_time_02 "
               . "      , 0.0 AS lag_time_03 "
               . "      , 0.0 AS lag_time_04 "
               . "      , 0.0 AS lag_time_05 "
               . "      , 0.0 AS lag_time_06 "
               . "      , 0.0 AS lag_time_07 "
               . "      , 0.0 AS lag_time_08 "
               . "      , 0.0 AS lag_time_09 "
               . "      , 0.0 AS lag_time_10 "
               . "      , 0.0 AS lag_time_11 "
               . "      , 0.0 AS lag_time_12 "
               . "      , 0.0 AS lag_time_13 "
               . "      , 0.0 AS lag_time_14 "
               . "      , 0.0 AS lag_time_15 "
               . "      , 0.0 AS lag_time_16 "
               . "      , 0.0 AS lag_time_17 "
               . "      , 0.0 AS lag_time_18 "
               . "      , 0.0 AS lag_time_19 "
               . "      , 0.0 AS lag_time_20 "
               . "   FROM sysha_nodes o "
               . "      , OUTER sysha_workload t "
               . "      , OUTER sysha_workload n "
               . "      , OUTER sysha_workload u "
               . "      , OUTER sysha_workload s "
               . "  WHERE t.wl_ttype = 'TIME_SPAN' "
               . "    AND n.wl_ttype = 'NUM_CPUVP' "
               . "    AND u.wl_ttype = 'UCPU_TIME' "
               . "    AND s.wl_ttype = 'SCPU_TIME' "
               . "    AND t.wl_secondary = o.server "
               . "    AND n.wl_secondary = o.server "
               . "    AND u.wl_secondary = o.server "
               . "    AND s.wl_secondary = o.server "
               . "    AND o.type = 'Primary' "
               . " UNION "
               . " SELECT TRIM ( o.server            ) AS servername  "
               . "      , TRIM ( o.type              ) AS servertype  "
               . "      , CASE TRIM ( i.state )      "
               . "             WHEN 'On' THEN 'Active' "
               . "             ELSE 'Inactive'  "
               . "        END                          AS server_status "
               . "      , CASE TRIM ( i.state )      "
               . "             WHEN 'On' THEN 'Connected' "
               . "             ELSE 'Disconnected'  "
               . "        END                          AS connection_status "
               . "      , 2                            AS ranking "
               . "      , ( ( u.wl_workload_1  + s.wl_workload_1  ) /       "
               . "          ( t.wl_workload_1  * n.wl_workload_1  ) ) * 100 "
               . "        AS cpu_usage_01                                   "
               . "      , ( ( u.wl_workload_2  + s.wl_workload_2  ) /       "
               . "          ( t.wl_workload_2  * n.wl_workload_2  ) ) * 100 "
               . "        AS cpu_usage_02                                   "
               . "      , ( ( u.wl_workload_3  + s.wl_workload_3  ) /       "
               . "          ( t.wl_workload_3  * n.wl_workload_3  ) ) * 100 "
               . "        AS cpu_usage_03                                   "
               . "      , ( ( u.wl_workload_4  + s.wl_workload_4  ) /       "
               . "          ( t.wl_workload_4  * n.wl_workload_4  ) ) * 100 "
               . "        AS cpu_usage_04                                   "
               . "      , ( ( u.wl_workload_5  + s.wl_workload_5  ) /       "
               . "          ( t.wl_workload_5  * n.wl_workload_5  ) ) * 100 "
               . "        AS cpu_usage_05                                   "
               . "      , ( ( u.wl_workload_6  + s.wl_workload_6  ) /       "
               . "          ( t.wl_workload_6  * n.wl_workload_6  ) ) * 100 "
               . "        AS cpu_usage_06                                   "
               . "      , ( ( u.wl_workload_7  + s.wl_workload_7  ) /       "
               . "          ( t.wl_workload_7  * n.wl_workload_7  ) ) * 100 "
               . "        AS cpu_usage_07                                   "
               . "      , ( ( u.wl_workload_8  + s.wl_workload_8  ) /       "
               . "          ( t.wl_workload_8  * n.wl_workload_8  ) ) * 100 "
               . "        AS cpu_usage_08                                   "
               . "      , ( ( u.wl_workload_9  + s.wl_workload_9  ) /       "
               . "          ( t.wl_workload_9  * n.wl_workload_9  ) ) * 100 "
               . "        AS cpu_usage_09                                   "
               . "      , ( ( u.wl_workload_10 + s.wl_workload_10 ) /       "
               . "          ( t.wl_workload_10 * n.wl_workload_10 ) ) * 100 "
               . "        AS cpu_usage_10                                   "
               . "      , ( ( u.wl_workload_11 + s.wl_workload_11 ) /       "
               . "          ( t.wl_workload_11 * n.wl_workload_11 ) ) * 100 "
               . "        AS cpu_usage_11                                   "
               . "      , ( ( u.wl_workload_12 + s.wl_workload_12 ) /       "
               . "          ( t.wl_workload_12 * n.wl_workload_12 ) ) * 100 "
               . "        AS cpu_usage_12                                   "
               . "      , ( ( u.wl_workload_13 + s.wl_workload_13 ) /       "
               . "          ( t.wl_workload_13 * n.wl_workload_13 ) ) * 100 "
               . "        AS cpu_usage_13                                   "
               . "      , ( ( u.wl_workload_14 + s.wl_workload_14 ) /       "
               . "          ( t.wl_workload_14 * n.wl_workload_14 ) ) * 100 "
               . "        AS cpu_usage_14                                   "
               . "      , ( ( u.wl_workload_15 + s.wl_workload_15 ) /       "
               . "          ( t.wl_workload_15 * n.wl_workload_15 ) ) * 100 "
               . "        AS cpu_usage_15                                   "
               . "      , ( ( u.wl_workload_16 + s.wl_workload_16 ) /       "
               . "          ( t.wl_workload_16 * n.wl_workload_16 ) ) * 100 "
               . "        AS cpu_usage_16                                   "
               . "      , ( ( u.wl_workload_17 + s.wl_workload_17 ) /       "
               . "          ( t.wl_workload_17 * n.wl_workload_17 ) ) * 100 "
               . "        AS cpu_usage_17                                   "
               . "      , ( ( u.wl_workload_18 + s.wl_workload_18 ) /       "
               . "          ( t.wl_workload_18 * n.wl_workload_18 ) ) * 100 "
               . "        AS cpu_usage_18                                   "
               . "      , ( ( u.wl_workload_19 + s.wl_workload_19 ) /       "
               . "          ( t.wl_workload_19 * n.wl_workload_19 ) ) * 100 "
               . "        AS cpu_usage_19                                   "
               . "      , ( ( u.wl_workload_20 + s.wl_workload_20 ) /       "
               . "          ( t.wl_workload_20 * n.wl_workload_20 ) ) * 100 "
               . "        AS cpu_usage_20                                   "
               . "      , DBINFO('utc_current') as current_time "
               . "      , l.lt_time_last_update AS lt_time_last_update "
               . "      , ROUND ( l.lt_lagtime_1 , 5 ) AS lag_time_01 "
               . "      , ROUND ( l.lt_lagtime_2 , 5 ) AS lag_time_02 "
               . "      , ROUND ( l.lt_lagtime_3 , 5 ) AS lag_time_03 "
               . "      , ROUND ( l.lt_lagtime_4 , 5 ) AS lag_time_04 "
               . "      , ROUND ( l.lt_lagtime_5 , 5 ) AS lag_time_05 "
               . "      , ROUND ( l.lt_lagtime_6 , 5 ) AS lag_time_06 "
               . "      , ROUND ( l.lt_lagtime_7 , 5 ) AS lag_time_07 "
               . "      , ROUND ( l.lt_lagtime_8 , 5 ) AS lag_time_08 "
               . "      , ROUND ( l.lt_lagtime_9 , 5 ) AS lag_time_09 "
               . "      , ROUND ( l.lt_lagtime_10, 5 ) AS lag_time_10 "
               . "      , ROUND ( l.lt_lagtime_11, 5 ) AS lag_time_11 "
               . "      , ROUND ( l.lt_lagtime_12, 5 ) AS lag_time_12 "
               . "      , ROUND ( l.lt_lagtime_13, 5 ) AS lag_time_13 "
               . "      , ROUND ( l.lt_lagtime_14, 5 ) AS lag_time_14 "
               . "      , ROUND ( l.lt_lagtime_15, 5 ) AS lag_time_15 "
               . "      , ROUND ( l.lt_lagtime_16, 5 ) AS lag_time_16 "
               . "      , ROUND ( l.lt_lagtime_17, 5 ) AS lag_time_17 "
               . "      , ROUND ( l.lt_lagtime_18, 5 ) AS lag_time_18 "
               . "      , ROUND ( l.lt_lagtime_19, 5 ) AS lag_time_19 "
               . "      , ROUND ( l.lt_lagtime_20, 5 ) AS lag_time_20 "
               . "   FROM sysha_nodes    o "
               . "      , sysdri         i "
               . "      , OUTER sysha_workload t "
               . "      , OUTER sysha_workload n "
               . "      , OUTER sysha_workload u "
               . "      , OUTER sysha_workload s "
               . "      , OUTER sysha_lagtime  l "
               . "  WHERE t.wl_ttype = 'TIME_SPAN' "
               . "    AND n.wl_ttype = 'NUM_CPUVP' "
               . "    AND u.wl_ttype = 'UCPU_TIME' "
               . "    AND s.wl_ttype = 'SCPU_TIME' "
               . "    AND t.wl_secondary = o.server "
               . "    AND n.wl_secondary = o.server "
               . "    AND u.wl_secondary = o.server "
               . "    AND s.wl_secondary = o.server "
               . "    AND l.lt_secondary = o.server "
               . "    AND i.name = o.server "
               . " UNION "
               . " SELECT TRIM ( o.server            ) AS servername  "
               . "      , TRIM ( o.type              ) AS servertype  "
               . "      , TRIM ( d.server_status     ) AS server_status     "
               . "      , TRIM ( d.connection_status ) AS connection_status "
               . "      , 3                            AS ranking "
               . "      , ( ( u.wl_workload_1  + s.wl_workload_1  ) /       "
               . "          ( t.wl_workload_1  * n.wl_workload_1  ) ) * 100 "
               . "        AS cpu_usage_01                                   "
               . "      , ( ( u.wl_workload_2  + s.wl_workload_2  ) /       "
               . "          ( t.wl_workload_2  * n.wl_workload_2  ) ) * 100 "
               . "        AS cpu_usage_02                                   "
               . "      , ( ( u.wl_workload_3  + s.wl_workload_3  ) /       "
               . "          ( t.wl_workload_3  * n.wl_workload_3  ) ) * 100 "
               . "        AS cpu_usage_03                                   "
               . "      , ( ( u.wl_workload_4  + s.wl_workload_4  ) /       "
               . "          ( t.wl_workload_4  * n.wl_workload_4  ) ) * 100 "
               . "        AS cpu_usage_04                                   "
               . "      , ( ( u.wl_workload_5  + s.wl_workload_5  ) /       "
               . "          ( t.wl_workload_5  * n.wl_workload_5  ) ) * 100 "
               . "        AS cpu_usage_05                                   "
               . "      , ( ( u.wl_workload_6  + s.wl_workload_6  ) /       "
               . "          ( t.wl_workload_6  * n.wl_workload_6  ) ) * 100 "
               . "        AS cpu_usage_06                                   "
               . "      , ( ( u.wl_workload_7  + s.wl_workload_7  ) /       "
               . "          ( t.wl_workload_7  * n.wl_workload_7  ) ) * 100 "
               . "        AS cpu_usage_07                                   "
               . "      , ( ( u.wl_workload_8  + s.wl_workload_8  ) /       "
               . "          ( t.wl_workload_8  * n.wl_workload_8  ) ) * 100 "
               . "        AS cpu_usage_08                                   "
               . "      , ( ( u.wl_workload_9  + s.wl_workload_9  ) /       "
               . "          ( t.wl_workload_9  * n.wl_workload_9  ) ) * 100 "
               . "        AS cpu_usage_09                                   "
               . "      , ( ( u.wl_workload_10 + s.wl_workload_10 ) /       "
               . "          ( t.wl_workload_10 * n.wl_workload_10 ) ) * 100 "
               . "        AS cpu_usage_10                                   "
               . "      , ( ( u.wl_workload_11 + s.wl_workload_11 ) /       "
               . "          ( t.wl_workload_11 * n.wl_workload_11 ) ) * 100 "
               . "        AS cpu_usage_11                                   "
               . "      , ( ( u.wl_workload_12 + s.wl_workload_12 ) /       "
               . "          ( t.wl_workload_12 * n.wl_workload_12 ) ) * 100 "
               . "        AS cpu_usage_12                                   "
               . "      , ( ( u.wl_workload_13 + s.wl_workload_13 ) /       "
               . "          ( t.wl_workload_13 * n.wl_workload_13 ) ) * 100 "
               . "        AS cpu_usage_13                                   "
               . "      , ( ( u.wl_workload_14 + s.wl_workload_14 ) /       "
               . "          ( t.wl_workload_14 * n.wl_workload_14 ) ) * 100 "
               . "        AS cpu_usage_14                                   "
               . "      , ( ( u.wl_workload_15 + s.wl_workload_15 ) /       "
               . "          ( t.wl_workload_15 * n.wl_workload_15 ) ) * 100 "
               . "        AS cpu_usage_15                                   "
               . "      , ( ( u.wl_workload_16 + s.wl_workload_16 ) /       "
               . "          ( t.wl_workload_16 * n.wl_workload_16 ) ) * 100 "
               . "        AS cpu_usage_16                                   "
               . "      , ( ( u.wl_workload_17 + s.wl_workload_17 ) /       "
               . "          ( t.wl_workload_17 * n.wl_workload_17 ) ) * 100 "
               . "        AS cpu_usage_17                                   "
               . "      , ( ( u.wl_workload_18 + s.wl_workload_18 ) /       "
               . "          ( t.wl_workload_18 * n.wl_workload_18 ) ) * 100 "
               . "        AS cpu_usage_18                                   "
               . "      , ( ( u.wl_workload_19 + s.wl_workload_19 ) /       "
               . "          ( t.wl_workload_19 * n.wl_workload_19 ) ) * 100 "
               . "        AS cpu_usage_19                                   "
               . "      , ( ( u.wl_workload_20 + s.wl_workload_20 ) /       "
               . "          ( t.wl_workload_20 * n.wl_workload_20 ) ) * 100 "
               . "        AS cpu_usage_20                                   "
               . "      , DBINFO('utc_current') as current_time "
               . "      , l.lt_time_last_update AS lt_time_last_update "
               . "      , ROUND ( l.lt_lagtime_1 , 5 ) AS lag_time_01 "
               . "      , ROUND ( l.lt_lagtime_2 , 5 ) AS lag_time_02 "
               . "      , ROUND ( l.lt_lagtime_3 , 5 ) AS lag_time_03 "
               . "      , ROUND ( l.lt_lagtime_4 , 5 ) AS lag_time_04 "
               . "      , ROUND ( l.lt_lagtime_5 , 5 ) AS lag_time_05 "
               . "      , ROUND ( l.lt_lagtime_6 , 5 ) AS lag_time_06 "
               . "      , ROUND ( l.lt_lagtime_7 , 5 ) AS lag_time_07 "
               . "      , ROUND ( l.lt_lagtime_8 , 5 ) AS lag_time_08 "
               . "      , ROUND ( l.lt_lagtime_9 , 5 ) AS lag_time_09 "
               . "      , ROUND ( l.lt_lagtime_10, 5 ) AS lag_time_10 "
               . "      , ROUND ( l.lt_lagtime_11, 5 ) AS lag_time_11 "
               . "      , ROUND ( l.lt_lagtime_12, 5 ) AS lag_time_12 "
               . "      , ROUND ( l.lt_lagtime_13, 5 ) AS lag_time_13 "
               . "      , ROUND ( l.lt_lagtime_14, 5 ) AS lag_time_14 "
               . "      , ROUND ( l.lt_lagtime_15, 5 ) AS lag_time_15 "
               . "      , ROUND ( l.lt_lagtime_16, 5 ) AS lag_time_16 "
               . "      , ROUND ( l.lt_lagtime_17, 5 ) AS lag_time_17 "
               . "      , ROUND ( l.lt_lagtime_18, 5 ) AS lag_time_18 "
               . "      , ROUND ( l.lt_lagtime_19, 5 ) AS lag_time_19 "
               . "      , ROUND ( l.lt_lagtime_20, 5 ) AS lag_time_20 "
               . "   FROM sysha_nodes    o "
               . "      , syssrcsds      d "
               . "      , OUTER sysha_workload t "
               . "      , OUTER sysha_workload n "
               . "      , OUTER sysha_workload u "
               . "      , OUTER sysha_workload s "
               . "      , OUTER sysha_lagtime  l "
               . "  WHERE t.wl_ttype = 'TIME_SPAN' "
               . "    AND n.wl_ttype = 'NUM_CPUVP' "
               . "    AND u.wl_ttype = 'UCPU_TIME' "
               . "    AND s.wl_ttype = 'SCPU_TIME' "
               . "    AND t.wl_secondary = o.server "
               . "    AND n.wl_secondary = o.server "
               . "    AND u.wl_secondary = o.server "
               . "    AND s.wl_secondary = o.server "
               . "    AND l.lt_secondary = o.server "
               . "    AND d.server_name  = o.server "
               . " UNION "
               . " SELECT TRIM ( o.server            ) AS servername  "
               . "      , TRIM ( o.type              ) AS servertype  "
               . "      , TRIM ( r.server_status     ) AS server_status     "
               . "      , TRIM ( r.connection_status ) AS connection_status "
               . "      , 4                            AS ranking "
               . "      , ( ( u.wl_workload_1  + s.wl_workload_1  ) /       "
               . "          ( t.wl_workload_1  * n.wl_workload_1  ) ) * 100 "
               . "        AS cpu_usage_01                                   "
               . "      , ( ( u.wl_workload_2  + s.wl_workload_2  ) /       "
               . "          ( t.wl_workload_2  * n.wl_workload_2  ) ) * 100 "
               . "        AS cpu_usage_02                                   "
               . "      , ( ( u.wl_workload_3  + s.wl_workload_3  ) /       "
               . "          ( t.wl_workload_3  * n.wl_workload_3  ) ) * 100 "
               . "        AS cpu_usage_03                                   "
               . "      , ( ( u.wl_workload_4  + s.wl_workload_4  ) /       "
               . "          ( t.wl_workload_4  * n.wl_workload_4  ) ) * 100 "
               . "        AS cpu_usage_04                                   "
               . "      , ( ( u.wl_workload_5  + s.wl_workload_5  ) /       "
               . "          ( t.wl_workload_5  * n.wl_workload_5  ) ) * 100 "
               . "        AS cpu_usage_05                                   "
               . "      , ( ( u.wl_workload_6  + s.wl_workload_6  ) /       "
               . "          ( t.wl_workload_6  * n.wl_workload_6  ) ) * 100 "
               . "        AS cpu_usage_06                                   "
               . "      , ( ( u.wl_workload_7  + s.wl_workload_7  ) /       "
               . "          ( t.wl_workload_7  * n.wl_workload_7  ) ) * 100 "
               . "        AS cpu_usage_07                                   "
               . "      , ( ( u.wl_workload_8  + s.wl_workload_8  ) /       "
               . "          ( t.wl_workload_8  * n.wl_workload_8  ) ) * 100 "
               . "        AS cpu_usage_08                                   "
               . "      , ( ( u.wl_workload_9  + s.wl_workload_9  ) /       "
               . "          ( t.wl_workload_9  * n.wl_workload_9  ) ) * 100 "
               . "        AS cpu_usage_09                                   "
               . "      , ( ( u.wl_workload_10 + s.wl_workload_10 ) /       "
               . "          ( t.wl_workload_10 * n.wl_workload_10 ) ) * 100 "
               . "        AS cpu_usage_10                                   "
               . "      , ( ( u.wl_workload_11 + s.wl_workload_11 ) /       "
               . "          ( t.wl_workload_11 * n.wl_workload_11 ) ) * 100 "
               . "        AS cpu_usage_11                                   "
               . "      , ( ( u.wl_workload_12 + s.wl_workload_12 ) /       "
               . "          ( t.wl_workload_12 * n.wl_workload_12 ) ) * 100 "
               . "        AS cpu_usage_12                                   "
               . "      , ( ( u.wl_workload_13 + s.wl_workload_13 ) /       "
               . "          ( t.wl_workload_13 * n.wl_workload_13 ) ) * 100 "
               . "        AS cpu_usage_13                                   "
               . "      , ( ( u.wl_workload_14 + s.wl_workload_14 ) /       "
               . "          ( t.wl_workload_14 * n.wl_workload_14 ) ) * 100 "
               . "        AS cpu_usage_14                                   "
               . "      , ( ( u.wl_workload_15 + s.wl_workload_15 ) /       "
               . "          ( t.wl_workload_15 * n.wl_workload_15 ) ) * 100 "
               . "        AS cpu_usage_15                                   "
               . "      , ( ( u.wl_workload_16 + s.wl_workload_16 ) /       "
               . "          ( t.wl_workload_16 * n.wl_workload_16 ) ) * 100 "
               . "        AS cpu_usage_16                                   "
               . "      , ( ( u.wl_workload_17 + s.wl_workload_17 ) /       "
               . "          ( t.wl_workload_17 * n.wl_workload_17 ) ) * 100 "
               . "        AS cpu_usage_17                                   "
               . "      , ( ( u.wl_workload_18 + s.wl_workload_18 ) /       "
               . "          ( t.wl_workload_18 * n.wl_workload_18 ) ) * 100 "
               . "        AS cpu_usage_18                                   "
               . "      , ( ( u.wl_workload_19 + s.wl_workload_19 ) /       "
               . "          ( t.wl_workload_19 * n.wl_workload_19 ) ) * 100 "
               . "        AS cpu_usage_19                                   "
               . "      , ( ( u.wl_workload_20 + s.wl_workload_20 ) /       "
               . "          ( t.wl_workload_20 * n.wl_workload_20 ) ) * 100 "
               . "        AS cpu_usage_20                                   "
               . "      , DBINFO('utc_current') as current_time "
               . "      , l.lt_time_last_update AS lt_time_last_update "
               . "      , ROUND ( l.lt_lagtime_1 , 5 ) AS lag_time_01 "
               . "      , ROUND ( l.lt_lagtime_2 , 5 ) AS lag_time_02 "
               . "      , ROUND ( l.lt_lagtime_3 , 5 ) AS lag_time_03 "
               . "      , ROUND ( l.lt_lagtime_4 , 5 ) AS lag_time_04 "
               . "      , ROUND ( l.lt_lagtime_5 , 5 ) AS lag_time_05 "
               . "      , ROUND ( l.lt_lagtime_6 , 5 ) AS lag_time_06 "
               . "      , ROUND ( l.lt_lagtime_7 , 5 ) AS lag_time_07 "
               . "      , ROUND ( l.lt_lagtime_8 , 5 ) AS lag_time_08 "
               . "      , ROUND ( l.lt_lagtime_9 , 5 ) AS lag_time_09 "
               . "      , ROUND ( l.lt_lagtime_10, 5 ) AS lag_time_10 "
               . "      , ROUND ( l.lt_lagtime_11, 5 ) AS lag_time_11 "
               . "      , ROUND ( l.lt_lagtime_12, 5 ) AS lag_time_12 "
               . "      , ROUND ( l.lt_lagtime_13, 5 ) AS lag_time_13 "
               . "      , ROUND ( l.lt_lagtime_14, 5 ) AS lag_time_14 "
               . "      , ROUND ( l.lt_lagtime_15, 5 ) AS lag_time_15 "
               . "      , ROUND ( l.lt_lagtime_16, 5 ) AS lag_time_16 "
               . "      , ROUND ( l.lt_lagtime_17, 5 ) AS lag_time_17 "
               . "      , ROUND ( l.lt_lagtime_18, 5 ) AS lag_time_18 "
               . "      , ROUND ( l.lt_lagtime_19, 5 ) AS lag_time_19 "
               . "      , ROUND ( l.lt_lagtime_20, 5 ) AS lag_time_20 "
               . "   FROM sysha_nodes o "
               . "      , syssrcrss   r "
               . "      , OUTER sysha_workload t "
               . "      , OUTER sysha_workload n "
               . "      , OUTER sysha_workload u "
               . "      , OUTER sysha_workload s "
               . "      , OUTER sysha_lagtime  l "
               . "  WHERE t.wl_ttype = 'TIME_SPAN' "
               . "    AND n.wl_ttype = 'NUM_CPUVP' "
               . "    AND u.wl_ttype = 'UCPU_TIME' "
               . "    AND s.wl_ttype = 'SCPU_TIME' "
               . "    AND t.wl_secondary = o.server "
               . "    AND n.wl_secondary = o.server "
               . "    AND u.wl_secondary = o.server "
               . "    AND s.wl_secondary = o.server "
               . "    AND l.lt_secondary = o.server "
               . "    AND r.server_name  = o.server "
               . "  ORDER BY ranking; ";

        $row = $this->doIDSDatabaseWork ( $primary 
										, "sysmaster"
									    , $query );

		return $row;
	}


    /*
     * get the Connection details for a serverName
     */
    protected function getConnectionForServer ( $servername
                                              , $dbname = "sysmaster" )
   	{
        if ( ! $servername )
		{
            return null;
		}
			
        $cacheKey = trim($dbname)."@".trim($servername);
		if ( isset ( $this->serverdbs[$cacheKey]) )
        {
           // error_log("found in cache {$cacheKey}");
            return $this->serverdbs[$cacheKey];
        }
			
			
        $query = "SELECT * FROM connections WHERE server = '{$servername}' AND group_num = {$this->group_num}";

        $row = $this->doDatabaseWork($query , true);
        if ( ! $row )
       	{
            return null;
       	}

        $row = $row[0];
        require_once("idsdb.php");
        $db_connection = new idsdb ( $row['host']
                         , $row['port']
                         , $row['server']
                         , $row['idsprotocol'] 
                         , $dbname
                         , $row['username']
                         , $row['password'] , true );
                        // error_log("adding to cache {$cacheKey}");

                         if ( $db_connection == null )
                         {
                         	return null;
                         }
        $this->serverdbs[$cacheKey] = $db_connection;
        return $this->serverdbs[$cacheKey];
   	}

    /*
     * Close all open connections associated with server
     */

	protected function closeConnectionForServer ( $serverName )
	{
		foreach ( $this->serverdbs as $key => $value )
		{
			$elements = explode ( "@", $key );
			if ( strcmp ( $elements [ 1 ], $serverName ) == 0 )
			{
				unset ( $this->serverdbs [ $key ] );
			}
		}
	}

	protected function getPrimaryForCluster ( $cluster )
	{
		$primaries = array ( );

		foreach ( $cluster as $server )
		{
            $serverName = $server [ 'server' ];

			if ( !$this->isServerReachable ( $serverName ) )
			{
				continue;
			}

			$primaries [ ] = $this->getPrimaryForServer ( $serverName );
		}

		return array_unique ( $primaries );
	}

	function getPrimaryForServer ( $server )
	{
        $primary = null;

        if ( $this->isClusteredServer ( $server ) )
		{
			$query = "SELECT TRIM ( ha_primary ) AS ha_primary "
               	   . "  FROM sysha_type ";

        	$row = $this->doIDSDatabaseWork ( $server
                                            , "sysmaster"
                                            , $query );

			$primary = $row [ 0 ] [ 'HA_PRIMARY' ];
		}

        return $primary;
	}

	function getServerType ( $server )
	{
        $query = "SELECT ha_type    "
               . "     , ha_primary "
               . "  FROM sysha_type ";

        $row = $this->doIDSDatabaseWork ( $server
                                        , "sysmaster"
                                        , $query );
        return $row;
	}

	function getSqlHostEntryForServer ( $server
                                      , $lookup )
	{
		$query = "SELECT TRIM ( dbsvrnm  ) AS dbsvrnm  "
               . "     , TRIM ( nettype  ) AS nettype  "
               . "     , TRIM ( svrtype  ) AS svrtype  "
               . "     , TRIM ( netprot  ) AS netprot  "
               . "     , TRIM ( hostname ) AS hostname "
               . "     , TRIM ( svcname  ) AS svcname  "
               . "     , TRIM ( options  ) AS options  "
               . "     , svrsecurity    "
               . "     , clntsecurity   "
               . "     , netoptions     "
               . "     , netbuf_size    "
               . "     , connmux_option "
               . "     , TRIM ( svrgroup   ) AS svrgroup   "
               . "     , TRIM ( endofgroup ) AS endofgroup "
               . "     , redirector     "
               . "     , svrid          "
               . "     , pamauth        "
               . "  FROM syssqlhosts    "
               . " WHERE dbsvrnm = '{$lookup}' ";

        $row = $this->doIDSDatabaseWork ( $server
                                        , "sysmaster"
                                        , $query );

		return $row;
	}
                                  
	function getSqlHostsFromServer ( $server )
	{
		$query = "SELECT TRIM ( dbsvrnm  ) AS dbsvrnm  "
               . "     , TRIM ( nettype  ) AS nettype  "
               . "     , TRIM ( svrtype  ) AS svrtype  "
               . "     , TRIM ( netprot  ) AS netprot  "
               . "     , TRIM ( hostname ) AS hostname "
               . "     , TRIM ( svcname  ) AS svcname  "
               . "     , TRIM ( options  ) AS options  "
               . "     , svrsecurity    "
               . "     , clntsecurity   "
               . "     , netoptions     "
               . "     , netbuf_size    "
               . "     , connmux_option "
               . "     , TRIM ( svrgroup   ) AS svrgroup   "
               . "     , TRIM ( endofgroup ) AS endofgroup "
               . "     , redirector     "
               . "     , svrid          "
               . "     , pamauth        "
               . "  FROM syssqlhosts    ";

        $row = $this->doIDSDatabaseWork ( $server
                                        , "sysmaster"
                                        , $query );

		return $row;
	}

    function isClusteredServer ( $server )
	{
        $query = "SELECT count ( * ) AS is_clustered"
               . "  FROM sysha_type  "
               . " WHERE ha_type > 0 ";

		$row = $this->doIDSDatabaseWork ( $server
                                        , "sysmaster"
                                        , $query );

		$bool = false;

		if ( $row [ 0 ] [ 'IS_CLUSTERED' ] > 0 )
		{
			$bool = true;
		}

		return $bool;
	}

	function isCachedServer ( $server )
	{
		$query = "SELECT count ( * ) as is_cached "
               . "  FROM connections "
               . " WHERE server = '{$server}' "
               . " AND group_num = {$this->group_num} ";

		$row = $this->doDatabaseWork ( $query );

		$bool = false;

		if ( $row [ 0 ] [ 'is_cached' ] > 0 )
		{
			$bool = true;
		}

		return $bool;
	}

	function isPrimaryServer ( $server )
	{
		$query = "SELECT count ( * ) AS is_primary"
               . "  FROM sysha_type  "
               . " WHERE ha_type = 1 ";

		$row = $this->doIDSDatabaseWork ( $server
                                        , "sysmaster"
                                        , $query );
		return $row;
	}

	function isSecondaryServer ( $server )
	{
		$query = "SELECT count ( * ) AS is_secondary"
               . "  FROM sysha_type  "
               . " WHERE ha_type IN ( 2, 3, 4 ) ";

		$row = $this->doIDSDatabaseWork ( $server
                                        , "sysmaster"
                                        , $query );
		return $row;
	}

	function isStandardServer ( $server )
	{
		$query = "SELECT count ( * ) AS is_standard"
               . "  FROM sysha_type  "
               . " WHERE ha_type = 0 ";

		$row = $this->doIDSDatabaseWork ( $server
                                        , "sysmaster"
                                        , $query );
		return $row;
	}

    protected function onModeDriver ( $server 
                                    , $command ) 
	{
        //error_log ( "onModeDriver ( " . $server . ", " . $command . " )" );

		$query = "SELECT admin ( ${command} ) AS cmd_number"
		       . "  FROM systables "
               . " WHERE tabid = 1 ";

		$row = $this->doIDSDatabaseWork ( $server
                                        , "sysadmin"
                                        , $query );


		$cmd_number = $row [0]['CMD_NUMBER'];

        $query = "SELECT cmd_ret_status AS status   "
               . "     , cmd_ret_msg    AS message  "
               . "  FROM command_history            "
               . " WHERE cmd_number = {$cmd_number} ";

		$row = $this->doIDSDatabaseWork ( $server
                                        , "sysadmin"
                                        , $query );
        return $row;
	}

	function onModeMakeStandard ( $server )
	{
		$command = "'ha set standard', '{$server}'";
		return $this->onModeDriver ( $server, $command );
	}

	function onModeMakePrimary ( $server
                               , $force )
	{
        if ( $force )
		{
			$command = "'ha make primary force', '{$server}'";
		}
		else
		{
			$command = "'ha make primary', '{$server}'";
		}

        return $this->onModeDriver ( $server, $command );
	}

	function onModeMakeHDRSecondary ( $server )
	{
        $command = "'ha hdr', '{$server}'";
        return $this->onModeDriver ( $server, $command );
	}

	function onModeMakeRSSSecondary ( $server
                                    , $primary )
	{
		$command = "'ha add rss', '{$server}'";
		$this->onModeDriver ( $primary, $command );
        $command = "'ha rss', '{$server}'";
        return $this->onModeDriver ( $server, $command );
	}

	function onModeStub ( $server )
	{
		$command = "'checkpoint'";
		return $this->onModeDriver ( $server, $command );
	}

	function compareCluster ( $cluster1, $cluster2 )
	{
		return 0;
	}

    function compareServer ( $server1, $server2 )
	{
		return 0;
	}
	
	/**
     * Discovery clusters
     * 
     * @param primaries - serialized array of primary names that were found by the discoverPrimaries web service
     **/
	function discoverClusters ( $primaries )
	{
		error_log("Starting cluster discovery");
		$primaries = unserialize($primaries);
		if ($primaries == null || count($primaries) == 0)
		{
			error_log("No primaries, so no clusters to find.");
			return array ( 'CLUSTERS' => array() );
		}
		
		$clusters = array ( );
		
		$servers = $this->getServersFromCache ( null, $primaries );
		foreach ( $servers as $server )
		{
			$serverName  = $server [ 'server' ];
			error_log("Checking server $serverName");
            if ( !$this->isServerReachable ( $serverName ) )
			{
				error_log("    server $serverName is not reachable");
				continue;
			} else {
				error_log("    connected to server $serverName");
			}
			
			$clstr = $this->getClusterForServer ( $serverName );
			error_log("    cluster information retreived");
					
			if($clstr != null)//Don't include invalid clusters
			{
				$clusters [ ] = $clstr;
			}
		}
		
		error_log("Cluster discovery complete.");
		error_log("Number of clusters found: " . count($clusters));
		
		$ret = array ( 'CLUSTERS' => $clusters );

		return $ret;
	}

	function isServerReachable ( $server ) 
	{
		$rc = false;

		if ( $this->isCachedServer ( $server ) )
		{
			try
			{
				$query = 'select dummy from sysdual';
				$ret = $this->doIDSDatabaseWork ( $server
                                     	 , 'sysmaster'
                                     	 , $query 
                                     	 , true);
				if ( $ret != null )
				{
					$rc = true;
				}
				else
				{
					$rc = false;
				}
			}
			catch ( PDOException $s )
			{
				$rc = false;
			}
		}
		else
		{
			$rc = false;
		}

		return $rc;
	}

	/**
     * See if any servers known to OAT report a primary unknown to OAT
     * These unknown primaries will be returned.
     * 
     * @param searchGroup = true indicates to search the entire OAT group for clusters/primaries
     *                      false indicates to search only the current server 
     *                        
     **/
	function discoverPrimaries ($searchGroup = true)
	{
		error_log("Starting discover primaries");
		error_log("Searching for primary servers that do not have connection information saved");
		
		$primaries = array(); // all primaries found
		$missingInfoPrimaries = array(); // primaries that we are missing connection information for
		$dejaVu = array();
		
		if ($searchGroup)
		{
			$conn_num = null;
			error_log("Searching servers in the current OAT group");
		} else {
			$conn_num = $this->idsadmin->phpsession->instance->get_conn_num();
			error_log("Only searching on the current server (conn_num = {$conn_num})");
		}

		$servers = $this->getServersFromCache ( $conn_num );
		foreach ( $servers as $server )
		{
			$serverName  = $server [ 'server' ];
			error_log("Checking server $serverName");

			if ( !$this->isServerReachable ( $serverName ) )
			{
				error_log("   server not reachable");
				continue;
			}

			$primaryName = $this->getPrimaryForServer ( $serverName );

			if ($primaryName != null)
			{
				error_log("   server is in a cluster");
				error_log("   primary server for this cluster is $primaryName");
				
				// we only need to take action if we have not seen the primary before
				if (!in_array($primaryName, $dejaVu))
				{
					// add primary to the list of all primaries
					$primaries[] = $primaryName;
					// add primary to the list of servers we have already seen
					$dejaVu[] = $primaryName;					
					
					if ($primaryName != $serverName)
					{
						// check if we have connection information for this primary
						$cache = $this->getServerFromCache($primaryName);
						if (count($cache) == 0) 
						{
							// we do not have connection information
							$missingInfoPrimaries[] = 
									$this->getSqlHostEntryForServer ( $serverName
		                                                            , $primaryName );
						}
					}
				}
			}
			else
			{
				error_log("   server is not in a cluster");	
			}
		}
		
		// for debug purposes print this information to the error log
		if (count($primaries) == 0)
		{
			error_log('No clusters found');
		}
		else if (count($missingInfoPrimaries) > 0)
		{
			error_log('Missing primary information for the following servers:');
			foreach ($missingInfoPrimaries as $pserver)
			{
				error_log("   " . $pserver[0]['DBSVRNM'] . '@' . $pserver[0]['HOSTNAME']);	
			}
		} else {
			error_log('No missing primary information');
		}
		
		$ret = array('PRIMARIES' => $primaries, 'MISSING_INFO_PRIMARIES' => $missingInfoPrimaries);
		
		return $ret;
	}

	public function filterKnownServers ( $servers )
	{
		/*
         * Flex 3 Beta 1 no longer passes simple arrays
         * like it did in earlier version. As a workaround,
         * we pass the list of server names as comma delimited
         * string. TODO: Clean this up.
         */

		$unknowns = array ( );

		$a = explode ( ",", $servers );
		foreach ( $a as $server )
		{
			//error_log ( 'filterKnownServers: ' . $server );

			if ( ! $this->isCachedServer ( $server ) )
			{
				$unknowns [ ] = $server;
			}
		}

/*
		error_log ( 'filterKnownServers: ' . $servers );

		if ( is_array ( $servers ) )
			{
			foreach ( $servers as $server )
				{
				error_log ( 'filterKnownServers: ' . $server );
				if ( ! $this->isCachedServer ( $server ) )
					{
					$unknowns [ ] = $server;
					}
				}
			}
		else
			error_log ( 'servers is not an array' );
*/

		return array_unique ( $unknowns );
	}

	public function updateServerEnvironmentInCache ( $server )
	{
		/*
         * Server must be cached for this operation to succeed
         */

		if ( ! $this->isCachedServer ( $server ) )
		{
			error_log ( "updateServerEnvironmentInCache: "
                      . "Server [" . $server . "] is not cached" );
			return false;
		}

		/*
         * Get the connection ID for this server
         */

		$rows = $this->getServerFromCache ( $server );
		$cid  = $rows [ 0 ] [ 'conn_num' ];
		
		/*
         * Check if the server is reachable
         */

		if ( ! $this->isServerReachable ( $server ) )
		{
			error_log ( "updateServerEnvironmentInCache: "
                      . "Server [" . $server . "] is not reachable" );
			return false;
		}

		/*
         * Get the server's boot time
         */

		$query = "SELECT sh_boottime FROM sysshmvals";
		$rows  = $this->doIDSDatabaseWork ( $server, "sysmaster", $query );
		$boot  = $rows [ 0 ] [ 'SH_BOOTTIME' ];

	    /*
         * Get the timestamp for the cached environment
         */

		$query = "SELECT e.eid   "
		       . "     , e.stamp "
               . "  FROM connections c "
               . "     , env_link    e "
               . " WHERE c.conn_num = e.cid  "
               . "   AND c.conn_num = {$cid} "
               . "   AND c.group_num = {$this->group_num} ";

		$eid   = 0;
		$stamp = 0;
		$rows  = $this->doDatabaseWork ( $query );
		foreach ( $rows as $row )
		{
			$eid   = $row [ 'eid'  ];
			$stamp = $row [ 'stamp' ];
		}

		/*
         * If the server was rebooted since we last cached
         * its environment settings, delete what we have and
         * get the current values
         */

		if ( $boot > $stamp )
		{
			if ( $stamp != 0 )
			{
                /*
                 * A delete from environment_link triggers a
                 * delete from environment with the same id
                 */
                
				$query = "DELETE FROM env_link WHERE eid = {$eid} ";
				$this->doDatabaseWork ( $query );
			}

			/*
             * Insert an entry for this connection,
             * and retrieve the value for the eid
             * serial (autoincrement) column.
             */

			$query = "INSERT INTO env_link "
                   . " ( cid, stamp ) VALUES ( {$cid}, {$boot} )";
			$this->doDatabaseWork ( $query );
			
			$query = "SELECT last_insert_rowid ( ) as eid";
			$rows  = $this->doDatabaseWork ( $query );
			$eid   = $rows [ 0 ] [ 'eid' ];

			/*
             * Get the environment variables from the IDS
             * server and store them in our cache
             */

            $query = "SELECT trim ( env_name  ) AS name  "
                   . "     , trim ( env_value ) AS value "
                   . "  FROM sysenv ";
			$rows  = $this->doIDSDatabaseWork ( $server, 'sysmaster', $query );

			foreach ( $rows as $row )
			{
				$name  = $row [ 'NAME'  ];
                $value = $row [ 'VALUE' ];

				$query = "INSERT INTO env ( eid, name, value ) "
                       . "VALUES ( $eid, '{$name}', '{$value}' ) ";

				$this->doDatabaseWork ( $query );
			}

			/*
             * Get the server's current working directory and save 
             * it in the connections table. This is useful if a server
             * uses relative paths for chunks, and needs to be started
             * in a specific directory.
			 * We take the value from the oldest session, which is 
             * internal and not likely to change directories after boot.
             */

			$query = "SELECT FIRST 1 cwd "
                   . "  FROM sysscblst   "
                   . " ORDER BY sid      ";


			$rows = $this->doIDSDatabaseWork ( $server, 'sysmaster', $query );
			$cwd  = $rows [ 0 ] [ 'CWD' ];

			$query = "UPDATE connections SET cwd = '{$cwd}' "
                   . " WHERE conn_num = $cid "
                   . " AND group_num = {$this->group_num} ";

			$this->doDatabaseWork ( $query );
		}
		
		return true;
	}

	public function getServerEnvironmentFromCache ( $server
                                                  , $format = false )
	{
		$query = "SELECT e.name  "
               . "     , e.value "
               . "  FROM connections c "
               . "     , env_link    l "
               . "     , env         e "
               . " WHERE l.cid    = c.conn_num  "
               . "   AND l.eid    = e.eid       "
               . "   AND c.server = '{$server}' "
               . "   AND c.group_num = {$this->group_num} ";

		$rows = $this->doDatabaseWork ( $query );

		if ( ! $format )
		{
			return $rows;
		}
		else
		{
			$env = array ( );
			foreach ( $rows as $row )
			{
				$env [ ] = $row [ 'name' ] . "=" . $row [ 'value' ];
			}

			return $env;
		}
	}
	
	/**
	 * Resolve a service name to a numeric port number
	 */
	private function resolvePortNumber ($server, $portServiceName) 
	{
		require_once(ROOT_PATH."lib/feature.php");
		if ($this->idsadmin->phpsession->serverInfo == "")
		{
			$this->idsadmin->phpsession->set_serverInfo($this->idsadmin);
		}
		if (!Feature::isAvailable(Feature::PANTHER, $this->idsadmin->phpsession->serverInfo->getVersion()))
		{
			// The ifx_get_service function to resolve a service name to a port number is available on 11.70 and above.
			// For any older server version return an empty port number, requiring the user to enter it themselves.
			return "";
		}
		
		$query = "EXECUTE FUNCTION ifx_get_service('" . $portServiceName ."')";
		$result = $this->doIDSDatabaseWork($server, "sysmaster", $query);
		
		$port = "";
		if (count($result) > 0)
		{
			$port = $result[0][''];
			if ($port == -1)
			{
				// IDS could not resovle the port number for the given service name.
				$port = "";
			}
		}
		return $port;
	}

	protected function getServerInstallDirectory ( $server ) 
	{
		$query = "SELECT e.value "
               . "  FROM connections c "
               . "     , env_link    l "
               . "     , env         e "
               . " WHERE l.cid    = c.conn_num    "
               . "   AND l.eid    = e.eid         "
               . "   AND e.name   = 'INFORMIXDIR' "
               . "   AND c.server = '{$server}'   "
               . "   AND c.group_num = {$this->group_num} ";

		$rows = $this->doDatabaseWork ( $query );
		return $rows [ 0 ] [ 'value' ];
	}

	public function setupSDSServer ( $primary 
                                   , $serverName 
								   , $serverNumber
                                   , $host
                                   , $port
                                   , $idsprotocol
								   , $user
								   , $password
								   , $idsdPort 
								   , $informixDirectory )
		{
/*
		error_log ( 'setupSDSServer ( ' 
                  . 'primary = '           . $primary           . ', '
                  . 'serverName = '        . $serverName        . ', '
                  . 'serverNumber = '      . $serverNumber      . ', '
                  . 'host = '              . $host              . ', '
                  . 'port = '              . $port              . ', '
                  . 'idsprotocol = '       . $idsprotocol       . ', '
                  . 'user = '              . $user              . ', '
                  . 'password = '          . $password          . ', '
                  . 'idsdPort = '          . $idsdPort          . ', '
				  . 'informixDirectory = ' . $informixDirectory . ', '
                  . ' )' );
*/

		/*
         * The values for $ONCONFIG and $INFORMIXSQLHOSTS
         * are determined here
         */

		$onConfigFile = "onconfig." . $serverName;
		$sqlHostsFile = "sqlhosts." . $serverName;

		/*
		 * Get the primary's configuration and replace 
         * some pertinent parameter values with our own
         */

		$configuration = $this->getServerConfiguration ( $primary );
		foreach ( $configuration as &$row )
		{
			/* Replace DBSERVERNAME */
			if ( $row [ 'CF_NAME' ] == 'DBSERVERNAME' ) 
			{
				$row [ 'CF_EFFECTIVE' ] = $serverName;
			}
			/* Convert EXTSHMADD/SHMADD back to kilobytes */
			else if ( $row [ 'CF_NAME' ] == 'EXTSHMADD' 
                   || $row [ 'CF_NAME' ] == 'SHMADD' )
			{
				$value = $row [ 'CF_EFFECTIVE' ] / 1024;
				$row [ 'CF_EFFECTIVE' ] = $value;
			}
			/* Replace MSGPATH */
			if ( $row [ 'CF_NAME' ] == 'MSGPATH' )
			{
				$row [ 'CF_EFFECTIVE' ] = $informixDirectory
                                        . "/"
                                        . $serverName
                                        . ".log";
			}
			/* Replace SDS_PAGING */
			else if ( $row [ 'CF_NAME' ] == 'SDS_PAGING' )
			{
				$swap  = $informixDirectory . "/" . $serverName . "_swap.";
				$swap1 = $swap . "1";
				$swap2 = $swap . "2";

				$row [ 'CF_EFFECTIVE' ] = $swap1 . "," . $swap2;
			}
			/* Replace SDS_TEMPDBS */
			else if ( $row [ 'CF_NAME' ] == 'SDS_TEMPDBS' )
			{
				$rubble   = explode ( ",", $row [ 'CF_EFFECTIVE' ] );
				$pageSize = $rubble [ 2 ];
				$offset   = $rubble [ 3 ];
				$size     = $rubble [ 4 ];

				$name = $serverName . "_temp" . $pageSize . "k";
				$path = $informixDirectory . "/" . $name;

				$row [ 'CF_EFFECTIVE' ] = $name
                                        . ","
                                        . $path
                                        . ","
                                        . $pageSize
                                        . ","
                                        . $offset
                                        . ","
                                        . $size;
			}
			/* Replace SERVERNUM */
			else if ( $row [ 'CF_NAME' ] == 'SERVERNUM' )
			{
				$row [ 'CF_EFFECTIVE' ] = $serverNumber;
			}
            /* 
             * Handle SHMBASE alterations. We use cf_original, because
             * that's what the primary's config file specified, and must
             * have worked since we just read its configuration from
             * sysmasters.
			 */
			else if ( $row [ 'CF_NAME' ] == 'SHMBASE' )
			{
				$row [ 'CF_EFFECTIVE' ] = $row [ 'CF_ORIGINAL' ];
			}
		}

		/*
         * For debugging, write the modified configuration
         * to the PHP error log
         */

/*
		foreach ( $configuration as $parameter )
		{
			error_log ( "Parameter [" 
                      . $parameter [ 'CF_NAME' ] 
                      . "]["
					  . $parameter [ 'CF_EFFECTIVE' ]
					  . "]" );
		}
*/

		/*
         * Instantiate an IDSD controller to deliver 
         * $ONCONFIG and $INFORMIXSQLHOSTS files and 
         * start the new SDS server.
         */

		$idsd = new IDSDaemonControl ( );
		$status = $idsd->connect ( $host, $idsdPort, $user, $password );

		if ( $status == false )
			return $status;

		/*
         * Assemble the $ONCONFIG file and 
         * transfer it to the target server.
         */

		$source      = tempnam ( self::TEMPDIR, $onConfigFile );
		$destination = $informixDirectory . "/etc/" . $onConfigFile;

		if ( ( $handle = fopen ( $source, "wb" ) ) === FALSE )
		{
			throw new Exception ( "Error opening file [" 
                                . $source 
                                . "]" );
		}

		foreach ( $configuration as $parameter )
		{
			$string = $parameter [ 'CF_NAME' ]
                    . " "
                    . $parameter [ 'CF_EFFECTIVE' ]
                    . "\n";

			if ( fwrite ( $handle, $string ) === FALSE )
			{
			    throw new Exception ( "Error writing file [" . $source . "]" );
			}
		}

		fclose ( $handle );
		//error_log ( "Source: " . $source );
		$idsd->putFile ( $source, $destination );
		unlink ( $source );

		/*
         * Copy the $INFORMIXSQLHOSTS file from
         * the primary, add an entry for the new SDS 
         * and transfer it to the host.
         */

		$source      = tempnam ( self::TEMPDIR, $sqlHostsFile );
		$destination = $informixDirectory . "/etc/" . $sqlHostsFile;

		if ( ( $handle = fopen ( $source, "wb" ) ) == FALSE )
		{
			throw new Exception ( "Error opening file ["
                                . $source
                                . "]" );
		}

		$rows = $this->getSqlHostsFromServer ( $primary );

		foreach ( $rows as $row )
		{
			$string = $row [ 'DBSVRNM'  ]
                    . " "
                    . $row [ 'NETTYPE'  ]
					. " "
                    . $row [ 'HOSTNAME' ]
                    . " "
                    . $row [ 'SVCNAME'  ]
                    . " " 
                    . $row [ 'OPTIONS'  ]
                    . "\n";

			if ( fwrite ( $handle, $string ) === FALSE )
			{
				throw new Exception ( "Error writing file [" . $source . "]" );
			}
		}

		/* 
         * Append an entry for the new server to sqlhosts
         */
 
		$string = $serverName
                . " "
                . $row [ 'NETTYPE' ]
                . " "
                . $host
                . " "
                . $port
                . " "
                . $row [ 'OPTIONS' ]
                . "\n";
                   
		if ( fwrite ( $handle, $string ) === FALSE )
		{
			throw new Exception ( "Error writing file [" . $source . "]" );
		}

		fclose ( $handle );
		$idsd->putFile ( $source, $destination );
		unlink ( $source );

		/*
         * Construct an array of arguments
         */

		$arguments = array ( "-w" );

		/*
         * Derive the SDS environment from the primary,
         * replacing some values with our own.
         */

		$rows = $this->getServerEnvironmentFromCache ( $primary );
		foreach ( $rows as &$row )
		{
			if ( $row [ 'name' ] == 'INFORMIXDIR' )
			{
				$row [ 'value' ] = $informixDirectory;
			}
			else if ( $row [ 'name' ] == 'INFORMIXSERVER' )
			{
				$row [ 'value' ] = $serverName;
			}
			else if ( $row [ 'name' ] == 'INFORMIXSQLHOSTS' )
			{
				$row [ 'value' ] = $informixDirectory
                                 . "/etc/"
                                 . $sqlHostsFile;
			}
			else if ( $row [ 'name' ] == 'ONCONFIG' )
			{
				$row [ 'value' ] = $onConfigFile;
			}
			else if ( $row [ 'name' ] == 'PATH' )
			{
				$row [ 'value' ] = $informixDirectory
                                 . "/bin:"
                                 . $row [ 'value' ];
			}
		}

		$environment = array ( );
		foreach ( $rows as $row )
		{
			$environment [ ] = $row [ 'name' ] . "=" . $row [ 'value' ];
		}

		/*
         * For debugging, write the environment to the PHP error log
         */

/*
		foreach ( $environment as $row )
		{
			error_log ( "Environment [" . $row . "]" );
		}
*/

		/*
         * Start the server and disconnect from IDSD.
         */

		//error_log ( "Start Server - begin " );

		$status = $idsd->startServer ( $arguments, $environment );
        /*
         * TODO: move this to startServer 
         */
		$idsd->disconnect ( );

		//error_log ( "Start Server - status " . $status );

		/*
         * If the SDS server started successfully,
         * update the OAT connections database.
         */

		if ( $status )
		{
			$query = "DELETE FROM connections "
                   . " WHERE server = '{$serverName}' "
                   . "   AND host   = '{$host}'   "
                   . "   AND port   = '{$port}'   "
                   . " AND group_num = {$this->group_num} ";

			$this->doDatabaseWork ( $query );

			$query = "SELECT cluster_id            "
                   . "  FROM connections           "
                   . " WHERE server = '{$primary}' "
                   . " AND group_num = {$this->group_num} ";

			$rows = $this->doDatabaseWork ( $query );
			$clusterID = $rows [ 0 ] [ 'cluster_id' ];

			//error_log ( 'Cluster ID: ' . $clusterID );

			/* Add connection info */
			$connectionID = $this->addServerToCache ( $this->group_num
                                                    , $host
                                                    , $port
                                                    , $serverName
                                                    , $idsprotocol
                                                    , 0
                                                    , 0
                                                    , $user
                                                    , $password
                                                    , $clusterID
                                                    , 3 );

			//error_log ( 'Connection ID: ' . $connectionID );

			$this->updateServerEnvironmentInCache ( $serverName );

			$query = "INSERT INTO idsd ( cid, host, port ) "
                   . "          VALUES ( '{$connectionID}' "
                   . "                 , '{$host}'         "
                   . "                 , '{$idsdPort}' )   ";

			$this->doDatabaseWork ( $query );
		}

		return $status;
	}

	public function startServer ( $server )
	{
		//error_log ( "startServer: " . $server );

		/* Server must be known */
		if ( ! $this->isCachedServer ( $server ) )
		{
			error_log ( "Server [" . $server . "] not found in cache" );
			return false;
		}

		/* Server must not be up and running */
		if ( $this->isServerReachable ( $server ) )
		{
			error_log ( "Server [" . $server . "] is already running" );
			return false;
		}

		/* Get the details for the IDS daemon */
		$query = "SELECT i.host "
               . "     , i.port "
               . "     , c.username "
               . "     , c.password "
               . "     , TRIM ( c.cwd ) AS cwd "
               . "  FROM idsd i "
               . "     , connections c "
               . " WHERE c.conn_num = i.cid "
               . "   AND c.server   = '{$server}' "
               . "   AND c.group_num = {$this->group_num} ";

		$rows = $this->doDatabaseWork ( $query , true );
		if ( empty ( $rows ) )
		{
			error_log ( "Server [" . $server . "] has no daemon registered" );
			return false;
		}
 
		$host      = $rows [ 0 ] [ 'host' ];
        $port      = $rows [ 0 ] [ 'port' ];
        $username  = $rows [ 0 ] [ 'username' ];
		$password  = $rows [ 0 ] [ 'password' ];
        $directory = $rows [ 0 ] [ 'cwd' ];

		/* Arguments, if any */
		$arguments = array ( "-w" );

		/* Get the server's environment */
		$environment = $this->getServerEnvironmentFromCache ( $server, true );
		if ( empty ( $environment ) )
		{
			error_log ( "No environment found for server [" . $server . "]" );
			return false;
		}

		$control = new IDSDaemonControl ( );
		$control->connect ( $host, $port, $username, $password );
		$control->startServer ( $arguments, $environment, $directory );
		$control->disconnect ( );

		return true;
	}

	public function stopServer ( $server )
	{
		$rc = false;

		if ( $this->isServerReachable ( $server ) )
		{
			$query = "EXECUTE FUNCTION task ( 'shutdown immediate' )";
			$this->doIDSDatabaseWork ( $server, 'sysadmin', $query );
			$this->closeConnectionForServer ( $server );
			$rc = true;
		}

		return $rc;
	}
    
	protected function doIDSDatabaseWork( $servername, $dbname, $query , $handleException = false )
   	{
        $db = $this->getConnectionForServer ( $servername, $dbname );
        if ( $db == null )
        {
        	return array();
        }
        
        $stmt = $db->query ( $query , false , $handleException );

        while ( $row = $stmt->fetch())
        {
            $ret[] = $row;
        }

   		$err = $db->errorInfo();

		if ( $err[2] == 0 )
		{
			$stmt->closeCursor();
		}
		else
		{
			$err = "Error: {$err[2]} - {$err[1]}";
			$stmt->closeCursor();
			trigger_error($err,E_USER_ERROR);
		}

		return $ret;
   	}

    protected function doDatabaseWork ($qry , $ispassword=false)
   	{
        $ret = array();

        $stmt = $this->db->query($qry);
        if (! $stmt)
       	{
            $err = $this->db->errorInfo();
            $err = "Error: {$err[2]} - {$err[1]}";
			trigger_error($err,E_USER_ERROR);
       	}

        while ($row = $stmt->fetch() )
       	{
            $err = $stmt->errorInfo();
            if ($err[1] != 0)
           	{
                $err = "Error: {$err[2]} - {$err[1]}";
				$stmt->closeCursor();
				trigger_error($err,E_USER_ERROR);
           	}
			if ( $ispassword === true && ( isset( $row['password'] ) ) )
			{
				$row['password'] = connections::decode_password( $row['password'] );
			}
            $ret[] = $row;
       	}

        return $ret;
   	} 
}

?>
