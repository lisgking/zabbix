<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2007, 2013.  All Rights Reserved
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
 * Class to provide Information about the Server currently connected too.
 *
 */

class serverInfo {
	 
	private $serverType=0;
	private $hasCDR=false;
	private $version;
	private $lastUpdate = 0;
	private $serverUpTime;
	private $maxUsers=0;
	private $numSessions=0;
	private $defaultPagesize;
	private $serverTime=0;
	private $bootTime=0;
	private $mem_free=-1;
	private $mem_total=-1;
	private $num_cpu=-1;
	private $hasGrid=null;

	function __construct(&$idsadmin)
	{
		$this->init($idsadmin);
	}

	function init(&$idsadmin)
	{
		/* Lets get the version info and server type */

		$db   = $idsadmin->get_database("sysmaster");
		$sql = "SELECT DBINFO('version','full') AS vers, " .
        	"DBINFO('version','major') AS vers_major FROM systables WHERE tabid = 1";
		$stmt = $db->query($sql , false);
		$row = $stmt->fetch();
		if ( $row['VERS_MAJOR'] < 11 )
		{
			// if version < 11, sysha_type table will not exist, so return
			$this->setVersion($row['VERS']);
			return;
		}
		$stmt->closeCursor();

		$sql   = "SELECT DBINFO('version','full') AS vers , ha_type FROM sysha_type ";
		$stmt = $db->query($sql);
		$row  = $stmt->fetch();

		$this->setServerType($row['HA_TYPE']);
		$this->setVersion($row['VERS']);
		$stmt->closeCursor();

		require_once("feature.php");
		/*
		 * this needs to be called after the setVersion call above
		 */
		if ( Feature::isAvailable ( Feature::CHEETAH2, $this->getVersion() )  )
		{
			/* get the other stats */
			$sql = " SELECT  "
			. " format_units(os_mem_total/1024,'k') as mem_total, "
			. " format_units(os_mem_free/1024,'k')  as mem_free, "
			. " os_num_procs as num_procs"
			. " from sysmaster:sysmachineinfo "
			;

			$stmt = $db->query($sql);
			$row = $stmt->fetch();
			$stmt->closeCursor();
				
			$this->setNumCpu($row['NUM_PROCS']);
			$this->setMemFree($row['MEM_FREE']);
			$this->setMemTotal($row['MEM_TOTAL']);
		}


		/* get the other stats */
		$sql = " SELECT (select count(*) from syssessions where  syssessions.sid != DBINFO('sessionid')) as numusers "
		." ,dbinfo('UTC_TO_DATETIME', sh_boottime)::datetime year to minute as boottime "
		." ,dbinfo('UTC_TO_DATETIME', sh_curtime)::datetime hour to second as curtime "
		." ,(sh_curtime - sh_boottime) as uptime "
		." ,sh_ovlmaxcons "
		." ,sh_pagesize "
		." from sysshmvals ";

		$stmt = $db->query($sql);
		$row = $stmt->fetch();
		$stmt->closeCursor();


		$this->setBootTime($row['BOOTTIME']);
		$this->setServerTime($row['CURTIME']);
		$this->setServerUpTime($row['UPTIME']);
		$this->setMaxUsers($row['SH_OVLMAXCONS']);
		$this->setNumSessions($row['NUMUSERS']);
		$this->setDefaultPagesize($row['SH_PAGESIZE']);

		$sql = "select count(*) as cnt from sysdatabases where name = 'syscdr' ";
		$stmt = $db->query ($sql);
		$row  = $stmt->fetch();
		$stmt->closeCursor();
		$this->setHasCDR($row['CNT']);

		/* update the lastupdate time */
		$this->setLastUpdate();

		return;
	}

	function setServerType($val)
	{
		/* some versions of 11.10 seem to return 0 */
		if ( $val == 0 )
		{
			$this->serverType = STANDARD;
		} else {
			$this->serverType = $val;
		}
	}

	function getServerType($x=false)
	{

		/* some versions of 11.10 seem to return 0 */
		if ( $this->serverType == 0 )
		{
			$this->setServerType(STANDARD);
		}


		if ( $x === true )
		{
			switch ($this->serverType)
			{
				case STANDARD:
					return "Standard";
					break;
				case PRIMARY:
					return "Primary";
					break;
				case SECONDARY:
					return "Secondary";
					break;
				case SDS:
					return "SDS";
					break;
				case RSS:
					return "RSS";
					break;
			}
		}

		return $this->serverType;
	}

	function setHasCDR($val)
	{
		$this->hasCDR = $val;
	}

	function getHasCDR()
	{
		return $this->hasCDR;
	}

	function setLastUpdate()
	{
		$this->lastUpdate = time();
	}

	function getLastUpdate()
	{
		return $this->lastUpdate;
	}

	function setVersion($val="")
	{
		$this->version = $val;
	}

	function getVersion()
	{
		return $this->version;
	}

	function isStandard()
	{
		return ( $this->serverType == STANDARD ) ;
	}

	function isPrimary()
	{
		if ( $this->isStandard() === true 
		|| $this->serverType == PRIMARY )
		{
			return true;
		}
		return false;
	}

	function isSecondary()
	{
		return ( $this->serverType == SECONDARY );
	}
	 
	function isRSS()
	{
		return ( $this->serverType == RSS );
	}

	function isSDS()
	{
		return ( $this->serverType == SDS );
	}

	function setServerTime($val=0)
	{
		$this->serverTime = $val;
	}

	function getServerTime()
	{
		return $this->serverTime;
	}

	function setBootTime($val=0)
	{
		$this->bootTime = $val;
	}

	function getBootTime()
	{
		return $this->bootTime;
	}

	function setServerUpTime($val=0)
	{
		$this->serverUpTime = $val;
	}

	function getServerUpTime($idsadmin)
	{
		$uptime = $idsadmin->timedays($this->serverUpTime);  // Covert to readable format
		$uptime = substr($uptime,0,strlen($uptime) - 3); // Not necessary to show seconds as part of uptime
		return $uptime;
	}

	function setMaxUsers($val=0)
	{
		$this->maxUsers = $val;
	}

	function getMaxUsers()
	{
		return $this->maxUsers;
	}

	function setNumSessions($val=0)
	{
		$this->numSessions = $val;
	}

	function getNumSessions()
	{
		return $this->numSessions;
	}

	function setDefaultPagesize($val=2048)
	{
		$this->defaultPagesize = $val;
	}

	function getDefaultPagesize()
	{
		return $this->defaultPagesize;
	}

	function setMemFree($val=-1)
	{
		$this->mem_free = $val;
	}
	
	function getMemFree() 
	{
		return $this->mem_free;
	}
	
	function setMemTotal($val=-1) 
	{
		$this->mem_total = $val;
	}
	
	function getMemTotal() 
	{
		return $this->mem_total;
	}
	
	function setNumCpu($val=-1) 
	{
		$this->num_cpu = $val;
	}
	
	function getNumCpu() 
	{
		return $this->num_cpu;
	}
	
	/**
	 * Returns true/false indicating if the current server participants in a grid.
	 **/
	function isServerInGrid(&$idsadmin) 
	{
		// If we've already figured out if this server is in a grid, return the answer.
		if ($this->hasGrid != null)
		{
			return $this->hasGrid;
		}
		
		// If the server version < 11.70, grid is not supported.
		include_once 'lib/feature.php';
		if (!Feature::isAvailable(Feature::PANTHER, $idsadmin))
		{
			$this->hasGrid = false;
			return $this->hasGrid;
		}
		
		// Otherwise, figure out if this server is in a grid now.
		// It can't be in a grid if it isn't defined for ER.
		if (!$this->hasCDR)
		{
			$this->hasGrid = false;
			return $this->hasGrid;
		}
		
		// Now check fo the existence of a grid that the current server participates in.
		$db = $idsadmin->get_database("syscdr");
		$sql = "select count(*) as grid_count from grid_def, grid_part, hostdef "
		 . "where grid_def.gd_id = grid_part.gp_id and hostdef.servid = grid_part.gp_servid "
			 . "and groupname = (select servername from sysmaster:syscdrserver where connstate = 'L')";
		$stmt = $db->query($sql);
		$row = $stmt->fetch();
		$stmt->closeCursor();
		$this->hasGrid = ($row['GRID_COUNT'] > 0);
		return $this->hasGrid;
	}
	
	/**
	 * Return the list of grids this server participants in.
	 * 
	 * @param $source_server 
	 *        - true indicates return the list of grids that this server both participates
	 *          in and is a source server on. This is used to supply the option for the 
	 *          user to run OAT actions/commands through the grid.  For this purpose, you'd
	 *          want to return only those grids that the current server is a source server on.
	 *        - false indicates return all grids that the server participates in, regardless
	 *          of whether the server is a source server or not.
	 */
	function getGridsForServer(&$idsadmin, $source_server=true) 
	{
		if (!$this->isServerInGrid($idsadmin))
		{
			return array();
		}
		
		// Now check fo the existence of a grid that the current server participates in.
		$grids = array();
		$db = $idsadmin->get_database("syscdr");
		$sql = "select trim(gd_name) as gd_name from grid_def, grid_part, hostdef "
			 . "where grid_def.gd_id = grid_part.gp_id and hostdef.servid = grid_part.gp_servid "
			 . "and groupname = (select servername from sysmaster:syscdrserver where connstate = 'L') ";
		if ($source_server)
		{
			$sql .= "and gp_enable = 'y'";
		}
		$stmt = $db->query($sql);
		$rows = $stmt->fetchAll();
		$stmt->closeCursor();
		foreach ($rows as $row)
		{
			$grids[] = $row['GD_NAME'];
		}
		return $grids;
	}
	
} // end class
?>
