<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2012.  All Rights Reserved
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
 
class iwaServer
{
    private $idsadmin;
  
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
     * Get Accelerators
     * 
     */
	public function getAccels($type)
	{
		$ret = array();
		
		$query = "select unique(svrgroup) as name, trim(hostname) as hostname, svcname from syssqlhosts where nettype = 'dwsoctcp'";
		$ret = $this->idsadmin->doDatabaseWork($query,'sysmaster');
		
		$ipPattern = '/[\d]+[.][\d]+[.][\d]+[.][\d]+/';
		
		foreach($ret as &$value) {
		  if (preg_match($ipPattern, $value['HOSTNAME']) == 1) {
		      if ($value['HOSTNAME'] == "127.0.0.1") {
		          // IP address of localhost needs to be interpreted as localhost from the perspective of the database server,
		          // not from perspective of OAT (so we can't use gethostbyaddr() as on Windows this will return the web server's
		          // localhost machine name).
		          $value['RES_HOSTNAME'] = $this->idsadmin->phpsession->instance->get_host();
		      } else {
		          $value['RES_HOSTNAME'] = gethostbyaddr($value['HOSTNAME']);
		      }
		  } else {
		      $value['RES_HOSTNAME'] = $value['HOSTNAME'];
		  }
		  $value['LOCAL_HOSTNAME'] = $this->idsadmin->phpsession->instance->get_host();	
		}		

		return $ret;
	}
	
	public function getIWAStats($iwaName, $cluster=false)
	{
		$ret = $this->getDB();
		if ( !empty($ret) ) {
				
			/* get the dbname */
			$dbname = $ret[0]['DBNAME'];
			
			/* get the locale of the database */
			$locale =  $ret[0]['LOCALE'];
			
		} else {
		
			$res[0]['ERROR'] = 'NO_USER_DB';
			return $res;
		}
		
		$query1 = "SELECT c.* FROM TABLE(ifx_getDwaMetrics('{$iwaName}')) (c) ";
		
		if ($cluster) {
		  $query1 .= " WHERE c.group='Cluster' ";
		}
        try {
            $stats = $this->idsadmin->doDatabaseWork($query1,$dbname,null,null,null,$locale); 
        } catch ( PDOException $e )  {
            $stats['RESULT_MESSAGE'] = "{$e->getCode()}\n" . $e->getMessage() . "\n";
            $stats['FAIL'] = true;
        }	

		if (empty($stats)) {
			$stats[0]['ERROR'] = 'NO_RESULT';
		}
		
		//error_log("stats is:" . var_export($stats,true));
		return $stats;
	}
	
	public function addDropAccel($type, $name, $ipAdd="", $port="", $pairCode="")
	{

		$result['ACTION_TYPE'] = $type;
		$result['IWA_NAME'] = $name;
			
		$ret = $this->getDB();
		if ( !empty($ret) ) {
	
			/* get the dbname */
			$dbname = $ret[0]['DBNAME'];
			
			/* get the locale of the database */
			$locale =  $ret[0]['LOCALE'];
		} else {
			$result['RESULT'] = 'NO_USER_DB';
            return $result;
		}
				
		if ($type == "ADD_ACCEL") {
		
			if ($ipAdd == "" || $port == "" || $pairCode == "") {
				return $res['INPUT_PARAM_ERROR'] = true;
			}
			
			$query1 = "execute function ifx_setupDwa('{$name}','{$ipAdd}','{$port}','{$pairCode}')";
			
			$res = $this->idsadmin->doDatabaseWork($query1,$dbname,null,null,null,$locale);
			
		} else if ($type == "DROP_ACCEL") {		
		
			$query1 = "execute function ifx_removeDwa('{$name}')";
			
			$res = $this->idsadmin->doDatabaseWork($query1,$dbname,null,null,null,$locale);
					
		}
		
		$result['RESULT'] = $res;
		
		return $result;
	}
	
    /**
     * Get the datamarts for the accelerator
     * 
     * @param accel_name: Accelerator name     
     * @param rows_per_page: -1 indicates all rows
     * @param page: current page
     * @param sort_col: column to sort by
     */
    public function getDataMarts($accel_name, $rows_per_page = -1, $page = 1, $sort_col = null)
    {
    	$result = array();
   	
    	$ret = $this->getDB();
		if ( !empty($ret) ) {
		
			/* get the dbname */
			$dbname = $ret[0]['DBNAME'];
			
			/* get the locale of the database */
			$locale =  $ret[0]['LOCALE'];
		} else {
			return ($result['RESULT'] = 'NO_USER_DB');
		}
		
    	$sql = "SELECT c.* FROM TABLE(ifx_listMarts('{$accel_name}')) (c)";
    	
    	// get the corresponding db names for the mart names from sysadmin
    	$sql1 = "SELECT c.m_name, c.m_dbname from iwa_datamarts c where c.m_accel_name = '{$accel_name}'";
    	
    	if ($sort_col == null)
    	{
    		// default sort order
    		$sort_col = "name";
    	}

        try {
            $result['DATA'] = $this->idsadmin->doDatabaseWork($this->idsadmin->transformQuery($sql,$rows_per_page,$page,$sort_col), $dbname, null, null, null, $locale);
            $result['DBNAMES'] = $this->idsadmin->doDatabaseWork($sql1, 'sysadmin');
        } catch ( PDOException $e )  {
            $result['RESULT_MESSAGE'] = "{$e->getCode()}\n" . $e->getMessage() . "\n";
            $result['FAIL'] = true;
        }
   	
    	$result['COUNT'] = 0;
    	$temp = $this->idsadmin->doDatabaseWork($this->idsadmin->createCountQuery($sql), $dbname, null, null, null, $locale);
    	
    	if (count($temp) > 0)
    	{
    		$result['COUNT'] = $temp[0]['COUNT'];
    	}
    	
    	return $result;
    }	
	
 /***************************************************************************
  *
  * Private functions
  *
  ***************************************************************************/

    /*
     * IWA stored procedures execute in the context of the non system database.
     * This function returns a non-system database name and it's locale.
     */
    private function getDB()
    {
    	$ret = array();
    	
		$query = "select first 1 trim(dbs_dbsname) as dbname, trim(dbs_collate) as locale "
		          . "from sysdbslocale where dbs_dbsname NOT IN ('sysmaster','sysadmin','sysutils','sysuser','syscdr','sysha')";
		$ret = $this->idsadmin->doDatabaseWork($query,'sysmaster');	
		
		return $ret;    
    }		
	
}
	
?>
