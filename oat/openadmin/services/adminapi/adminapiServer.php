<?php
/*
 **************************************************************************   
 *  (c) Copyright IBM Corporation. 2007 , 2008.  All Rights Reserved
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

/**********************************************************************
 *
 *  Functions for the AdminAPI webservices
 *
 **********************************************************************/

    /**
     * A define for which Admin api function to use. 
     * task = return a string of the result
     * admin = returns a numeric value indicating the result which can
     *         be looked up in the command_history table
     */
    define('ADMIN_API_FUNCTION','task');

class adminapiServer {

    /**
     * Add a chunk and create the path 
     */
    function addChunk($connectionObj,$dbspace,$path,$size,$offset) 
    {
         return $this->doAddChunk($connectionObj,$dbspace,$path,$size,$offset,"add chunk");
    } // end addChunk

    /**
     * Add a chunk but check that the path exists before . ie. do not create
     * the file
     */
    function addChunkWithCheck($connectionObj,$dbspace,$path,$size,$offset) 
    {
         return $this->doAddChunk($connectionObj,$dbspace,$path,$size,$offset,"add with_check chunk");
    } // end addChunkWithCheck

    /**
     * The workhorse for adding a chunk.
     */
    function doAddChunk($connectionObj,$dbspace,$path,$size,$offset,$cmd="add chunk") 
    {
         if ( ! $dbspace )
         {
             throw new SoapFault("{$cmd}","missing param dbspace");
         }
    
         if ( ! $path )
         {
             throw new SoapFault("{$cmd}","missing param path");
         }

         if ( ! $size )
         {
             throw new SoapFault("{$cmd}","missing param size");
         }
    
         if ( ! $offset )
         {
             throw new SoapFault("{$cmd}","missing param offset");
         }
    
         // set the execution time limit to 0 - it may take a while
         // to create a chunk
         set_time_limit(0);

         $qry=" execute function ".ADMIN_API_FUNCTION." ('{$cmd}' "
             .",'{$dbspace}','{$path}','{$size}','{$offset}' )";

         return $this->doDatabaseWork($connectionObj,$qry);
    } // end doAddChunk

    /**
     * drop a chunk
     */
    function dropChunk($connectionObj,$dbspace,$path,$offset) 
    {

     if ( ! $dbspace )
         {
         throw new SoapFault("dropChunk","missing param dbspace");
     }

     if ( ! $path )
         {
         throw new SoapFault("dropChunk","missing param path");
     }

     if ( ! $offset )
         {
         throw new SoapFault("dropChunk","missing param offset");
     }

         $qry=" execute function ".ADMIN_API_FUNCTION." ('drop chunk' "
             .",'{$dbspace}','{$path}','{$offset}' )";

         return $this->doDatabaseWork($connectionObj,$qry);
    } // end dropChunk

    /**
     * Add a logical log
     */
    function addLog($connectionObj,$dbspace,$size)
    {
     if ( ! $dbspace )
         {
         throw new SoapFault("addLog","missing param dbspace");
     }
     if ( ! $size )
         {
         throw new SoapFault("addLog","missing param size");
     }

         $qry=" execute function ".ADMIN_API_FUNCTION." ('add log' "
             .",'{$dbspace}','{$size}' )";

         return $this->doDatabaseWork($connectionObj,$qry);

    } // end addLog

    /**
     * Drop a Logical log
     */
    function dropLog($connectionObj,$lognum)
    {
     if ( ! $lognum )
         {
         throw new SoapFault("dropLog","missing param lognum");
     }

         $qry=" execute function ".ADMIN_API_FUNCTION." ('drop log' "
             .",'{$lognum}' )";

         return $this->doDatabaseWork($connectionObj,$qry);

    } // end dropLog

    /**
     * Add a bufferpool for a pagesize
     */
    function addBufferPool($connectionObj,$pagesize,$numbuffers="1000"
            ,$numlrus="8",$maxdirty="60",$mindirty="50")
    {

        if ( !$pagesize )
        {
            throw new SoapFault("addBufferPool","missing param pagesize");
        }

        if ( !$numbuffers )
        {
            $numbuffers="1000";
        }

        if ( !$numlrus )
        {
            $numlrus="8";
        }

        if ( !$maxdirty )
        {
            $maxdirty="60";
        }

        if ( !$mindirty )
        {
            $mindirty="50";
        }

         $qry=" execute function ".ADMIN_API_FUNCTION." ('add bufferpool' "
                 .",'{$pagesize}' "
                 .",'{$numbuffers}' "
                 .",'{$numlrus}' "
                 .",'{$maxdirty}' "
                 .",'{$mindirty}' "
                 ." )";

         return $this->doDatabaseWork($connectionObj,$qry);

    } // end addBufferPool

    /**
     * Add some more virtual memory
     */
    function addMemory($connectionObj,$size)
    {
        if ( ! $size )
        {
            throw new SoapFault("addMemory","missing param lognum");
        }

         $qry=" execute function ".ADMIN_API_FUNCTION." ('add memory' "
             .",'{$size}' )";

         return $this->doDatabaseWork($connectionObj,$qry);

    } // end addMemory

    /**
     * Add a mirror chunk
     */
    function addMirror($connectionObj , $dbspace 
                 , $path ,$offset ,$mpath ,$moffset)
    {
        if ( ! $dbspace )
        {
            throw new SoapFault("addMirror","missing param dbspace");
        }

        if ( ! $path )
        {
            throw new SoapFault("addMirror","missing param path");
        }

        if ( ! $offset )
        {
            throw new SoapFault("addMirror","missing param offset");
        }

        if ( ! $mpath )
        {
            throw new SoapFault("addMirror","missing param mirror path");
        }

        if ( ! $moffset )
        {
            throw new SoapFault("addMirror","missing param mirror offset");
        }

        $qry = "execute function ".ADMIN_API_FUNCTION." ('add mirror' "
                .",'{$dbspace}','{$path}','{$offset}','{$mpath}','{$moffset}')";

        return $this->doDatabaseWork($connectionObj,$qry);
    } //end addMirror

    /**
     * Alter Chunk Offline
     *   Change the status of a chunk to offline mode
     */
    function alterChunkOffline($connectionObj, $dbspace, $path, $offset)
    {
        return $this->doAlterChunk($connectionObj,$dbspace,$path,$offset,"alter chunk offline");
    } //end alterChunkOffline

    /**
     * Alter Chunk Online
     *   Change the status of a chunk to online mode
     */
    function alterChunkOnline($connectionObj, $dbspace, $path, $offset)
    {
        return $this->doAlterChunk($connectionObj,$dbspace,$path,$offset,"alter chunk online");
    }//end alterChunkOnline

    /**
     * workhorse for the alterChunk[Offline|Online]
     */
    function doAlterChunk($connectionObj, $dbspace, $path, $offset ,$cmd)
    {
        if ( ! $dbspace )
        {
            throw new SoapFault("{$cmd}","missing param dbspace");
        }

        if ( ! $path )
        {
            throw new SoapFault("{$cmd}","missing param path");
        }

        if ( ! $offset )
        {
            throw new SoapFault("{$cmd}","missing param offset");
        }

        $qry = "execute function ".ADMIN_API_FUNCTION." ('{$cmd}' "
                .",'{$dbspace}','{$path}','{$offset}')";
        return $this->doDatabaseWork($connectionObj,$qry);
    } // end doAlterChunk

    /**
     * Alter the logging mode of a database
     * newmode must be buffered or unbuffered or ansi or no logging
     */
    function alterLogMode($connectionObj,$dbname,$newmode)
    {
        if ( !$dbname ) 
        {
            throw new SoapFault("alterLogMode","missing param dbname");
        }

        if ( !$newmode ) 
        {
            throw new SoapFault("alterLogMode","missing param newmode");
        }

        $newmode = trim(strtolower($newmode));
        if ( $newmode != "buffered" || $newmode != "unbuffered" 
            || $newmode !="ansi" || $newmode != "no logging" ) 
        {
            throw new SoapFault("alterLogMode","invalid value {$newmode} ");
        }

        $qry = "execute function ".ADMIN_API_FUNCTION." ('alter logmode' "
                .",'{$dbname}','{$newmode}')";
        return $this->doDatabaseWork($connectionObj,$qry);

    } // end alterLogmode

    /**
     * Alter the physical log size.
     */
    function alterPlog($connectionObj,$dbspace,$size)
    {
        if ( !$dbspace ) 
        {
            throw new SoapFault("alterPlog","missing param dbspace");
        }

        if ( !$size ) 
        {
            throw new SoapFault("alterLogMode","missing param size");
        }

        $qry = "execute function ".ADMIN_API_FUNCTION." ('alter plog' "
                .",'{$dbspace}','{$size}')";

        return $this->doDatabaseWork($connectionObj,$qry);
    }// end alterPlog

    /**
     * Perform a fake level0 archive
     */
    function archiveFake($connectionObj)
    {
        $qry = "execute function ".ADMIN_API_FUNCTION." ('archive fake')";
        return $this->doDatabaseWork($connectionObj,$qry);
    } //end archiveFake

    /**
     * Check data
     */
    function checkData($connectionObj,$partnum)
    {
        if ( !$partnum ) 
        {
            throw new SoapFault("checkData","missing param partnum");
        }

        $qry = "execute function ".ADMIN_API_FUNCTION." ('check data' "
                .",'{$partnum}')";

        return $this->doDatabaseWork($connectionObj,$qry);
    } // end checkData

    /**
     * Check extents for a dbspace
     */
    function checkExtents($connectionObj,$dbsnum="")
    {
        $qry = "execute function ".ADMIN_API_FUNCTION." ('check extents' ";
         if ( $dbsnum ) 
         {
            $qry .= ",'{$dbsnum}')";
         } 
         else 
         {
            $qry .= ")";
         }

        return $this->doDatabaseWork($connectionObj,$qry);
    } // checkExtents

    /**
     * Check the data portion of a partition
     */
    function checkPartition($connectionObj,$partnum="")
    {
         if ( ! $partnum ) 
         {
             throw new SoapFault("checkPartition","missing param partnum");
         } 

        $qry = "execute function ".ADMIN_API_FUNCTION." ('check partition' "
             . ",'{$partnum}')";

        return $this->doDatabaseWork($connectionObj,$qry);
    } // end checkPartition

    /**
     * Perform a checkpoint
     */
    function doCheckpoint($connectionObj)
    {
        $qry = "execute function ".ADMIN_API_FUNCTION." ('checkpoint') ";
        return $this->doDatabaseWork($connectionObj,$qry);
    }// end doCheckPoint

    /**
     * Clean stray LO's from an SBspace
     */
    function cleanSBSpace($connectionObj, $sbspace)
    {
        if ( ! $sbspace )
        {
            throw new SoapFault("cleanSBSpace","missing param sbspace");
        }

        $qry = "execute function ".ADMIN_API_FUNCTION." ('clean sbspace' "
             . ",'{$sbspace}')";

        return $this->doDatabaseWork($connectionObj,$qry);

    } // end cleanSBSpace

    /**
     * Create a DBSpace
     *  dbsname = name of dbspace to create
     *  path = path to chunk
     *  size = size of chunk
     *  offset = offset into chunk
     *  pgsize = PageSize for BlobSpace
     *  fext = First extentsize of the TBLSpace TBLSpace
     *  next = Next extentsize of the TBLSpace TBLSpace
     *  mpath = Mirror Path 
     *  moffset = Mirror Path Offset
     */
    function createDBSpace( $connectionObj,$dbsname,$path,$size,$offset
                             ,$pgsize="2",$fext="50",$next="50"
                             ,$mpath="",$moffset="" )
    {

        if (!dbsname)
        {
            throw new SoapFault("createDBSpace","missing param dbsname");
        }

        if (!path)
        {
            throw new SoapFault("createDBSpace","missing param path");
        }

        if (!size)
        {
            throw new SoapFault("createDBSpace","missing param size");
        }

        if (!offset)
        {
            throw new SoapFault("createDBSpace","missing param offset");
        }

        $qry = "execute function ".ADMIN_API_FUNCTION." ('create dbspace' ";
        $qry .= ",'{$dbsname}'";
        $qry .= ",'{$path}'";
        $qry .= ",'{$size}'";
        $qry .= ",'{$offset}'";
        $qry .= ",'{$pgsize}'";
        $qry .= ",'{$fext}'";
        $qry .= ",'{$next}'";

        if ( $mpath )
        {
            $qry .= ",'{$mpath}'";

            if ( $moffset )
            {
                $qry .= ",'{$moffset}'";
            }
        }

        $qry .= ")";

        return $this->doDatabaseWork($connectionObj,$qry);
    } // end createDBSpace

    /**
     * Create a BlobSpace
     *  dbsname = name of blobspace
     *  path = path to chunk
     *  size = size of chunk
     *  offset = offset into chunk
     *  pgsize = PageSize for BlobSpace
     *  mpath = Mirror Path 
     *  moffset = Mirror Path Offset
     */
    function createBlobSpace( $connectionObj,$dbsname,$path,$size,$offset
                             ,$pgsize="2" ,$mpath="",$moffset="" )
    {

        if (!dbsname)
        {
            throw new SoapFault("createBlobSpace","missing param dbsname");
        }

        if (!path)
        {
            throw new SoapFault("createBlobSpace","missing param path");
        }

        if (!size)
        {
            throw new SoapFault("createBlobSpace","missing param size");
        }

        if (!offset)
        {
            throw new SoapFault("createBlobSpace","missing param offset");
        }

        $qry = "execute function ".ADMIN_API_FUNCTION." ('create blobspace' ";
        $qry .= ",'{$dbsname}'";
        $qry .= ",'{$path}'";
        $qry .= ",'{$size}'";
        $qry .= ",'{$offset}'";
        $qry .= ",'{$pgsize}'";

        if ( $mpath )
        {
            $qry .= ",'{$mpath}'";

            if ( $moffset )
            {
                $qry .= ",'{$moffset}'";
            }
        }

        $qry .= ")";

        return $this->doDatabaseWork($connectionObj,$qry);
    } // end createBlobSpace


    /**
     * Drop BlobSpace
     *   dbsname = "BlobSpace Name to drop"
     */
    function dropBlobSpace($connectionObj,$dbsname)
    {
        if (! $dbsname )
        {
            throw new SoapFault("dropBlobSpace","missing param dbsname");
        }
        return $this->doDropSpace($connectionObj,$dbsname,"drop blobspace");
    } // end dropBlobSpace

    /**
     * Create a Temporary DBSpace
     *  dbsname = name of temporary space
     *  path = path to chunk
     *  size = size of chunk
     *  offset = offset into chunk
     *  pgsize = PageSize for BlobSpace
     *  mpath = Mirror Path 
     *  moffset = Mirror Path Offset
     */
    function createTempDBS( $connectionObj,$dbsname,$path,$size,$offset
                             ,$pgsize="2" ,$mpath="",$moffset="" )
    {

        if (!dbsname)
        {
            throw new SoapFault("createTempDBS","missing param dbsname");
        }

        if (!path)
        {
            throw new SoapFault("createTempDBS","missing param path");
        }

        if (!size)
        {
            throw new SoapFault("createTempDBS","missing param size");
        }

        if (!offset)
        {
            throw new SoapFault("createTempDBS","missing param offset");
        }

        $qry = "execute function ".ADMIN_API_FUNCTION." ('create tempdbspace' ";
        $qry .= ",'{$dbsname}'";
        $qry .= ",'{$path}'";
        $qry .= ",'{$size}'";
        $qry .= ",'{$offset}'";
        $qry .= ",'{$pgsize}'";

        if ( $mpath )
        {
            $qry .= ",'{$mpath}'";

            if ( $moffset )
            {
                $qry .= ",'{$moffset}'";
            }
        }

        $qry .= ")";

        return $this->doDatabaseWork($connectionObj,$qry);
    } // end createTempDBS

    /**
     * Drop A tempDBS
     *   dbsname = "tempDBS Name to drop"
     */
    function dropTempDBS($connectionObj,$dbsname)
    {
        if (! $dbsname )
        {
            throw new SoapFault("dropTempDBS","missing param dbsname");
        }
        return $this->doDropSpace($connectionObj,$dbsname,"drop tempdbs");
    } // end dropTempDBS

    /**
     * Create a SBSpace
     *  dbsname = name of dbspace to create
     *  path = path to chunk
     *  size = size of chunk
     *  offset = offset into chunk
     *  pgsize = PageSize for BlobSpace
     *  mpath = Mirror Path 
     *  moffset = Mirror Path Offset
     */
    function createSBSpace( $connectionObj,$dbsname,$path,$size,$offset
                             ,$mpath="",$moffset="" )
    {

        if (!dbsname)
        {
            throw new SoapFault("createSBSpace","missing param dbsname");
        }

        if (!path)
        {
            throw new SoapFault("createSBSpace","missing param path");
        }

        if (!size)
        {
            throw new SoapFault("createSBSpace","missing param size");
        }

        if (!offset)
        {
            throw new SoapFault("createSBSpace","missing param offset");
        }

        $qry = "execute function ".ADMIN_API_FUNCTION." ('create sbspace' ";
        $qry .= ",'{$dbsname}'";
        $qry .= ",'{$path}'";
        $qry .= ",'{$size}'";
        $qry .= ",'{$offset}'";

        if ( $mpath )
        {
            $qry .= ",'{$mpath}'";

            if ( $moffset )
            {
                $qry .= ",'{$moffset}'";
            }
        }

        $qry .= ")";

        return $this->doDatabaseWork($connectionObj,$qry);
    } // end createSBSpace

    /**
     * Drop A SBSpace
     *   dbsname = "SBSpace Name to drop"
     */
    function dropSBSpace($connectionObj,$dbsname)
    {
        if (! $dbsname )
        {
            throw new SoapFault("dropSBSpace","missing param dbsname");
        }
        return $this->doDropSpace($connectionObj,$dbsname,"drop sbspace");
    } // end dropSBSpace

    /**
     * Drop A DBSpace
     *   dbsname = "DBSpace Name to drop"
     */
    function dropDBSpace($connectionObj,$dbsname)
    {
        if (! $dbsname )
        {
            throw new SoapFault("dropDBSpace","missing param dbsname");
        }
        return $this->doDropSpace($connectionObj,$dbsname,"drop dbspace");
    } // end dropDBSpace

    /**
     * doDropSpace - workhorse function for dropping space.
     *   dbsname = the name of the dbspace to drop
     *   cmd = the SQLADmin API command to run.(eg: drop dbspace)
     */
    function doDropSpace($connectionObj,$dbsname,$cmd)
    {

        if ( ! $dbsname )
        {
            throw new SoapFault("doDropSpace:{$cmd}","missing param dbsname");
        }

        if ( ! $cmd )
        {
            throw new SoapFault("doDropSpace","missing param cmd");
        }

        $qry = "execute function ".ADMIN_API_FUNCTION." ('{$cmd}' "
             . ",'{$dbsname}')";
        return $this->doDatabaseWork($connectionObj,$qry);
    } // end doDropSpace

	/**
	 * killSession - tries to kill a session
	 * @param int - sessionId
	 */
    function killSession($connectionObj,$sessiondId=0)
    {
    	if ( intval($sessionId) > 0 )
    	{
    	 $qry = "execute function ".ADMIN_API_FUNCTION." ('onmode' , 'z' "
             . ",'{$sessionId}')";
        return $this->doDatabaseWork($connectionObj,$qry);
    	}
    	throw new SoapFault("killSession","invalid session id");
    }
    /**
     * doDatabaseWork
     *  connectionObj = the connection details.
     *  qry = the query to execute
     */
    function doDatabaseWork($connectionObj,$qry)
    {
        require_once("soapdb.php");

        $host       = $connectionObj->host;
        $port       = $connectionObj->port;
        $servername = $connectionObj->servername;
        $user       = $connectionObj->user;
        $pass       = $connectionObj->password;
        $protocol   = $connectionObj->protocol;
        $dbname     = "sysadmin";
        
        $db = new soapdb($host,$port,$servername,$protocol,$dbname,$user,$pass);
        $stmt = $db->query($qry);

        while ($row = $stmt->fetch() )
        {
            $ret = implode("|",$row);
        }
        return $ret;
    } // end doDatabaseWork

} //end class IDSAdminServer

?>
