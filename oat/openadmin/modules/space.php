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

/**
 * This class is used to show and administer
 * the different server storage options
 *
 */
class space {


    public $idsadmin;

    /**
     * Is mirroring enabled on the server
     */
    private $mirrorEnabled = -1;

    function __construct(&$idsadmin)
    {
        $this->idsadmin = &$idsadmin;
        $this->idsadmin->load_template("template_space");
        $this->idsadmin->load_lang("space");
        $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('mainTitle'));
        $this->isMirrorEnabled();
    }

    /**
     * The run function is what index.php will call.
     * The decission of what to actually do is based
     * on the value of the $this->idsadmin->in['do']
     *
     */
    function run()
    {
    	
        $this->idsadmin->setCurrMenuItem("dbspaces");
        switch($this->idsadmin->in['do'])
        {
        	case 'createdbspace':
        		$this->execCreateDBSpace();
        		break;
        	case 'adddbchunk':
        		$this->execAddChunk();
        		break;
        	case 'addmirrorchunk':
        		$this->execAddChunkWithMirror();
        		break;
        	case 'addmirror':
        		$this->execAddMirror();
        		break;
        	case 'dbspaces':
                if ( isset($this->idsadmin->in['dbspaceaction_drop']) )
                {
                    $this->execDropDBSpace();
                }
                $this->showDBSpaces();
                break;
            case 'dbsdetails':
                $this->idsadmin->set_redirect("dbspaces");
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('mainTitle')) . "  " .
                $this->idsadmin->lang('dbspace') .
            " (". $this->idsadmin->in['dbsnum'] . ")";
                $this->idsadmin->html->add_to_output(
                $this->setupDBSpaceTabs($this->idsadmin->in['do'], $this->idsadmin->in['dbsnum'],
                $this->idsadmin->in['dbsname'] ));
                $this->DBSDetailSum( $this->idsadmin->in['dbsnum'], $this->idsadmin->in['dbsname'] );
                break;
            case 'dbstables':
                $this->idsadmin->set_redirect("dbspaces");
                $this->idsadmin->title=$this->idsadmin->lang('mainTitle') . "  " .
                $this->idsadmin->lang('dbspace')." (". $this->idsadmin->in['dbsnum'] . ")";
                $this->idsadmin->html->add_to_output(
                $this->setupDBSpaceTabs($this->idsadmin->in['do'], $this->idsadmin->in['dbsnum'],
                $this->idsadmin->in['dbsname'] ));
                $this->DBSDetailTables( $this->idsadmin->in['dbsnum'], $this->idsadmin->in['dbsname']);
                break;
            case 'dbsextents':
                $this->idsadmin->set_redirect("dbspaces");
                $this->idsadmin->title=$this->idsadmin->lang('mainTitle') . "  " .
                $this->idsadmin->lang('dbspace') .
            " (". $this->idsadmin->in['dbsnum'] . ")";
                $this->idsadmin->html->add_to_output(
                $this->setupDBSpaceTabs($this->idsadmin->in['do'], $this->idsadmin->in['dbsnum'],
                $this->idsadmin->in['dbsname'] ));
                $this->DBSDetailExtents( $this->idsadmin->in['dbsnum'], $this->idsadmin->in['dbsname'] );
                break;
            case 'dbsadmin':
                $this->idsadmin->set_redirect("dbspaces");
                $this->idsadmin->title=$this->idsadmin->lang('mainTitle') . "  " .
                $this->idsadmin->lang('dbspace') .
            " (". $this->idsadmin->in['dbsnum'] . ")";
                if ( isset($this->idsadmin->in['startmirror']) )
                {
                    $this->execStartMirror();
                }
                elseif ( isset($this->idsadmin->in['stopmirror']) )
                {
                     $this->execStopMirror();
                }
                $this->idsadmin->html->add_to_output("<div id='dbsadminpage'>");
                $this->idsadmin->html->add_to_output(
                $this->setupDBSpaceTabs($this->idsadmin->in['do'],
                $this->idsadmin->in['dbsnum'],
                $this->idsadmin->in['dbsname'] ));
                $this->DBSDetailAdmin( $this->idsadmin->in['dbsnum'],
                $this->idsadmin->in['dbsname'] );
                $this->idsadmin->html->add_to_output("</div>");
                break;
            case 'validate':
                $this->idsadmin->set_redirect("dbspaces");
                $this->idsadmin->title=$this->idsadmin->lang('mainTitle') . "  " .
                $this->idsadmin->lang('dbspace') .
            " (". $this->idsadmin->in['dbsnum'] . ")";
                $this->idsadmin->html->add_to_output(
                $this->setupDBSpaceTabs($this->idsadmin->in['do'],
                $this->idsadmin->in['dbsnum'],
                $this->idsadmin->in['dbsname'] ));

                if(isset($this->idsadmin->in['extentVerification']))
                {
                    $this->idsadmin->title=$this->idsadmin->lang('System Verification') . "  " .
                    $this->idsadmin->lang('dbspace') .
            " (". $this->idsadmin->in['dbsnum'] . ")";

                    $this->execExtentVerification($this->idsadmin->in['dbsnum'],
                    $this->idsadmin->in['dbsname']);
                }
                else if(isset($this->idsadmin->in['tableVerification']))
                {
                    $this->idsadmin->title=$this->idsadmin->lang('System Verification') . "  " .
                    $this->idsadmin->lang('dbspace') .
            " (". $this->idsadmin->in['dbsnum'] . ")";

                    $this->execTableVerification($this->idsadmin->in['dbsnum'],
                    $this->idsadmin->in['dbsname']);
                }
                break;
            default:
                $this->idsadmin->error($this->idsadmin->lang('InvalidURL_do_param'));
                break;
        }
    } // end run

    /**
     * This function is what prints out an overview
     * of the space screen.  It shows the dbspace with
     * a graph and then a pagable table, followed by the
     * ability to add a dbspace
     *
     */
    function dbspaces()
    {
         
        $db = $this->idsadmin->get_database("sysmaster");
        $qry = "SELECT A.dbsnum, name, A.pagesize/1024 as pgsize, " .
        " B.nchunks, " .
        " ( sum(chksize,avg(A.pagesize))) DBS_SIZE, " .
        " ( sum(decode(mdsize,-1,nfree,udfree),
                                avg(A.pagesize))) FREE_SIZE, " .
        " B.flags " .
        "FROM syschktab A, sysdbstab B " .
        "WHERE A.dbsnum = B.dbsnum " .
        "GROUP BY A.dbsnum , name, B.nchunks, 3, B.flags " .
        "ORDER BY A.dbsnum";

        $stmt = $db->query( $qry );

        $this->idsadmin->html->add_to_output($this->template->sysdbspace_start_output());

        while ($res = $stmt->fetch())
        {
            $res['DBS_SIZE']=$this->idsadmin->format_size($res['DBS_SIZE']);
            $res['FREE_SIZE']=$this->idsadmin->format_size($res['FREE_SIZE']);
            $this->idsadmin->html->add_to_output($this->template->sysdbspace_row_output($res));
        }
        $this->idsadmin->html->add_to_output($this->template->sysdbspace_end_output());

    }


    /**
     * To display a list of dbspaces and the associated
     * admin tasks
     *
     */
    function showDBSpaces()
    {
        $db = $this->idsadmin->get_database("sysmaster");
        $defPagesize = $this->idsadmin->phpsession->serverInfo->getDefaultPagesize();
        require_once ROOT_PATH."lib/gentab.php";
        $tab = new gentab($this->idsadmin);

        $sql ="SELECT sum((chksize - decode(mdsize,-1,nfree,udfree)) * {$defPagesize}) as Used, ".
        " sum(decode(mdsize,-1,nfree,udfree) * {$defPagesize}) as Free FROM syschktab".
        " WHERE bitval(flags, '0x200')=0 AND bitval(flags, '0x4000')=0";
        $stmt = $db->query($sql);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        $userdata = array(
        $this->idsadmin->lang("Used") => $res['USED'],
        $this->idsadmin->lang("Free") => $res['FREE'],
        );

        $sql ="SELECT sum((chksize - decode(mdsize,-1,nfree,udfree)) * {$defPagesize}) as Used, ".
        " sum(decode(mdsize,-1,nfree,udfree) * {$defPagesize}) as Free FROM syschktab".
        " WHERE bitval(flags, '0x200')<>0 OR bitval(flags, '0x4000')<>0";
        $stmt = $db->query($sql);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        $blobdata = array(
        $this->idsadmin->lang("Used") => $res['USED'],
        $this->idsadmin->lang("Free") => $res['FREE'],
        );

        $sql ="SELECT sum((A.chksize - decode(A.mdsize,-1,A.nfree,A.udfree)) * {$defPagesize}) as Used, ".
        " sum(decode(A.mdsize,-1,A.nfree,A.udfree) * {$defPagesize}) as Free ".
        " FROM syschktab A, sysdbstab B ".
        " WHERE A.dbsnum = B.dbsnum AND bitval(B.flags, '0x2000')=1 ";
        $stmt = $db->query($sql);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        $tempdata = array(
        $this->idsadmin->lang("Used") => $res['USED'],
        $this->idsadmin->lang("Free") => $res['FREE'],
        );

        require_once("lib/Charts.php");


        $this->idsadmin->Charts = new Charts($this->idsadmin);
        $this->idsadmin->Charts->setType("PIE");
        $this->idsadmin->Charts->setDbname("sysmaster");
        $this->idsadmin->Charts->setTitle($this->idsadmin->lang('DATASpace'));
        $this->idsadmin->Charts->setLegendDir("vertical");
        $this->idsadmin->Charts->setWidth("100%");
        $this->idsadmin->Charts->setHeight("200");
        $this->idsadmin->Charts->setData($userdata);
        $this->idsadmin->html->add_to_output("<div id='dbspacepage'>"); 
        $this->idsadmin->html->add_to_output( "<TABLE style='width:100%; height:50%'><TR><TD style='width:25%; height:100%'>" );
        //$this->idsadmin->html->add_to_output( $mygraph->pieGraph( $userdata, $this->idsadmin->lang('DataSpaceL'),200,200  ) );
        $this->idsadmin->Charts->Render();

        if ( (int)$tempdata[$this->idsadmin->lang('Free')] > 0 || (int)$tempdata[$this->idsadmin->lang('Used')] > 0 )
        {
            $this->idsadmin->Charts = new Charts($this->idsadmin);
            $this->idsadmin->Charts->setType("PIE");
            //$this->idsadmin->Charts->setDbname("sysmaster");
            $this->idsadmin->Charts->setData($tempdata);
            $this->idsadmin->Charts->setTitle($this->idsadmin->lang('TEMPSpace'));
            $this->idsadmin->Charts->setLegendDir("vertical");
            $this->idsadmin->Charts->setWidth("100%");
            $this->idsadmin->Charts->setHeight("200");
            $this->idsadmin->html->add_to_output( "</TD><TD style='width:25%; height:100%'>" );
            $this->idsadmin->Charts->Render();

            //    $this->idsadmin->html->add_to_output( $mygraph->pieGraph( $tempdata, $this->idsadmin->lang('TempSpaceL') ,200,200 ) );

        }

        if ( (int)$blobdata[$this->idsadmin->lang('Free')] > 0 || (int)$blobdata[$this->idsadmin->lang('Used')] > 0 )
        {
            $this->idsadmin->Charts = new Charts($this->idsadmin);
            $this->idsadmin->Charts->setType("PIE");
            //$this->idsadmin->Charts->setDbname("sysmaster");
            $this->idsadmin->Charts->setData($blobdata);
            $this->idsadmin->Charts->setTitle($this->idsadmin->lang('BLOBSpace'));
            $this->idsadmin->Charts->setLegendDir("vertical");
            $this->idsadmin->Charts->setWidth("100%");
            $this->idsadmin->Charts->setHeight("200");
            $this->idsadmin->html->add_to_output( "</TD><TD style='width:25%; height:100%'>" );
            $this->idsadmin->Charts->Render();
             
            //$this->idsadmin->html->add_to_output($mygraph->pieGraph( $blobdata, $this->idsadmin->lang('BLOBSpace') ,200,200 ) );
        }

        $this->idsadmin->html->add_to_output( "</TD></TR><TR><TD colspan='3'>" );

        $qry = "SELECT A.dbsnum, " .
        " trim(B.name) as name, " .
        "CASE " .
        " WHEN (bitval(B.flags,'0x10')>0 AND bitval(B.flags,'0x2')>0)" .
        "   THEN 'MirroredBlobspace' " .
        " WHEN bitval(B.flags,'0x10')>0 " .
        "   THEN 'Blobspace' " .
        " WHEN bitval(B.flags,'0x2000')>0 AND bitval(B.flags,'0x8000')>0" .
        "   THEN 'TempSbspace' " .
        " WHEN bitval(B.flags,'0x2000')>0 " .
        "   THEN 'TempDbspace' " .
        " WHEN (bitval(B.flags,'0x8000')>0 AND bitval(B.flags,'0x2')>0)" .
        "   THEN 'MirroredSbspace' " .
        " WHEN bitval(B.flags,'0x8000')>0 " .
        "   THEN 'SmartBlobspace' " .
        " WHEN bitval(B.flags,'0x2')>0 " .
        "   THEN 'MirroredDbspace' " .
        " ELSE " .
        "   'Dbspace' " .
        " END  as dbstype, " .        
        "CASE " .
        " WHEN bitval(B.flags,'0x4')>0 " .
        "   THEN 'Disabled' " .
        " WHEN bitand(B.flags,3584)>0 " .
        "   THEN 'Recovering' " .
        " ELSE " .
        "   'Operational' " .
        " END  as dbsstatus, " .
        " sum(chksize*{$defPagesize}) as DBS_SIZE , " .
        " sum(decode(mdsize,-1,nfree,udfree) * {$defPagesize}) as free_size, " .
        " TRUNC(100-sum(decode(mdsize,-1,nfree,udfree))*100/ ".
        " sum(chksize),2) as used,".
        " MAX(B.nchunks) as nchunks, " .
        " MAX(A.pagesize) as pgsize, " .
        " sum(chksize) as sortchksize, " .
        " sum(decode(mdsize,-1,nfree,udfree)) as sortusedsize " .
        "FROM syschktab A, sysdbstab B " .
        "WHERE A.dbsnum = B.dbsnum " .
        "GROUP BY A.dbsnum , name, 3, 4 " .
        "ORDER BY A.dbsnum";

        $qrycnt="SELECT count(*) as cnt FROM sysdbstab WHERE 1=1";

        $tab->display_tab_by_page($this->idsadmin->lang("mainTitle"),
        array(
        "1" => "{$this->idsadmin->lang('Num')}",
        "2" => "{$this->idsadmin->lang('Name')}",
        "3" => "{$this->idsadmin->lang('Type')}",
        "4" => "{$this->idsadmin->lang('Status')}",
        "10" => "{$this->idsadmin->lang('size')}",
        "11" => "{$this->idsadmin->lang('Free')}",
        "7" => "{$this->idsadmin->lang('UsedPer')}",
        "8" => "{$this->idsadmin->lang('numChunks')}",
        "9" => "{$this->idsadmin->lang('pagesize')}",
        ),
        $qry,$qrycnt,20,"gentab_dbspace_show.php");

        $this->idsadmin->html->add_to_output( "</TD></TR><TR><TD colspan='3'>" );

        $this->showCreateDBSpace();

        $this->idsadmin->html->add_to_output( "</TD></TR></TABLE>" );

    }

    /**
     * To Create the dbspace and set the return message
     *
     */
    function showCreateDBSpace()
    {
        if ( $this->idsadmin->isreadonly() 
        || ! $this->idsadmin->phpsession->serverInfo->isPrimary() )
        {
            return "";
        }
        $db = $this->idsadmin->get_database("sysmaster");
        $html=<<<END
<br/><br/>
<b>{$this->idsadmin->lang("CreateSpace")}</b>
<br/>
<script type="text/javascript">
/**
 * The actionChange function is called whenever the "action" drop-down option is changed.
 * For only certain values of "action" should the "pagesize" drop-down be shown.
 * 
 * Configuring the pagesize is not relevant for sbspaces, so whenever 'Sbspace' is
 * the selected action, disable the pagesize drop-down.
 */
function actionChange(select) 
{
    if (select.options[select.selectedIndex].value == "Sbspace") 
    {
        document.getElementById("pagesize").value = "default";
        document.getElementById("pagesize").disabled = true;
    } else {
        document.getElementById("pagesize").disabled = false;
    }
}

function setdbdata(ajaxform)
{
	var ajaxdata = "name=" + ajaxform.newdbname.value + "&"
				 + "path=" + ajaxform.newdbpath.value + "&"
				 + "offset=" + ajaxform.newdboffset.value + "&"
				 + "size=" + ajaxform.newdbsize.value + "&"
				 + "action=" + ajaxform.action.value + "&"
				 + "pagesize=" + ajaxform.pagesize.value +"&";
	return ajaxdata;
}

</script>
<script type="text/javascript" src='jscripts/ajax.js'></script>
<table border=0><tr>
<form>
<th>{$this->idsadmin->lang("Name")}</th>
<th>{$this->idsadmin->lang("Path")}</th>
<th>{$this->idsadmin->lang("Offset")}</th>
<th>{$this->idsadmin->lang("size")}</th>
<th>{$this->idsadmin->lang("Type")}</th>
<th>{$this->idsadmin->lang("pagesize")}</th>
<th></th>
</tr><tr>
<td><input id="newdbname" type=text name="name" size=12/></td>
<td><input id="newdbpath" type=text name="path" size=38/></td>
<td><input id="newdboffset" type=text name="offset" size=4 value="0"/></td>
<td><input id="newdbsize" type=text name="size" size=6 value="10 M"/></td>
<td><select id="action" name="action" onchange="actionChange(this)">
                          <option value="Dbspace">{$this->idsadmin->lang('Dbspace')}</option>
                          <option value="Tempdbs">{$this->idsadmin->lang('TempDbspace')}</option>
                          <option value="Blobspace">{$this->idsadmin->lang('Blobspace')}</option>
                          <option value="Sbspace">{$this->idsadmin->lang('SmartBlobspace')}</option>
</select>
</td>
<td><select id="pagesize" name="pagesize">
    <option value='default'>{$this->idsadmin->lang("default")}</option>
END;
    $def_pgsize = $this->idsadmin->phpsession->serverInfo->getDefaultPagesize()/1024;
     
        
    for ($pgsize = $def_pgsize; $pgsize <= 16; $pgsize += $def_pgsize)
    {
        $html .= "<option value='$pgsize'>{$pgsize}K</option>";
    }
    
    $html.=<<<END
</select>
</td>
<td><input type=button class=button name="dbspaceaction" value="{$this->idsadmin->lang('Create')}" 
onClick="loadAJAX('dbspacepage','index.php?act=space&amp;do=createdbspace',setdbdata(this.form),createDBhandler)"/></td>
</tr>
</form>
</table>
<br/>
</div>
END;

        $this->idsadmin->html->add_to_output( $html );

    }
    
    
    /**
     * Check to ensure all valid paramaters and creates a dbspace
     *
     */
    function execCreateDBSpace()
    {
    	$this->idsadmin->render = false;
    	
    	if ( $this->idsadmin->isreadonly() )
        {
            echo "{$this->idsadmin->lang('Error')} ".$this->idsadmin->lang("NoPermission");
            return;
        }
        
        $check =  array(
        "1" => "name",
        "2" => "path",
        "3" => "offset",
        "4" => "size",
        "5" => "action",
        );
        foreach ( $check as $val )
        {
            if ( empty($this->idsadmin->in[ $val ]) &&
            strlen($this->idsadmin->in[ $val ])<1 )
            {
                echo $this->idsadmin->lang('DBSpaceCreateFailed') . " $val";
            	return;
            }
        }
        $dbname    = $this->idsadmin->in[ 'name' ];
        $dbspath   = $this->idsadmin->in[ 'path' ];
        $dbsoffset = $this->idsadmin->in[ 'offset' ];
        $dbssize   = $this->idsadmin->in[ 'size' ];
        $action    = $this->idsadmin->in[ 'action' ];
        
        if (!isset($this->idsadmin->in['pagesize']) || 
            strcasecmp($this->idsadmin->in['pagesize'],"default")==0 ) 
        {
            $pagesize = "";       
        } else {
            $pagesize = $this->idsadmin->in['pagesize'];    
        }

        if (strcasecmp($action,"Dbspace")==0){
            $cmd = "create dbspace";
        } else if (strcasecmp($action,"tempdbs")==0) {
            $cmd = "create tempdbspace";
        } else if (strcasecmp($action,"blobspace")==0){
            $cmd = "create blobspace";
        } else if (strcasecmp($action,"sbspace")==0){
            $cmd = "create sbspace";
        } else
        {
            echo $this->idsadmin->lang('DBSpaceCreateActFailed') . "  [$action]  " . $this->idsadmin->lang('IsNotValid');
            return;
        }

        $options = "'$dbname', '$dbspath', '$dbssize', '$dbsoffset'";
        if ($pagesize != "")
        {
            $options .= ", '$pagesize'";
        }
        
        $dbadmin = $this->idsadmin->get_database("sysadmin");
        $sql ="execute function task( '$cmd', $options)";
        $sql ="select admin( '$cmd', $options) as cmd_number" .
        " FROM systables where tabid=1";
        $stmt = $dbadmin->query($sql);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        $cmd_no = $res['CMD_NUMBER'];
        
    	if($cmd_no < 0){
    		//command failed
    		$cmd_no = abs($cmd_no);
    		$sql = "SELECT cmd_ret_msg AS retinfo".
	        " FROM command_history WHERE cmd_number={$cmd_no}";
	        $stmt = $dbadmin->query($sql);
	        $res = $stmt->fetch(PDO::FETCH_ASSOC);
	        echo $res['RETINFO'];
    	}else{
    		//command suceeded
    		echo $this->idsadmin->lang('DBSpaceCreationSuccess');
    	}
	}

    function sysver()
    {

        $check =  array(
        "1" => "name",
        "2" => "path",
        "3" => "offset",
        "4" => "size",
        "5" => "action",
        );
        foreach ( $check as $val )
        {
            if ( empty($this->idsadmin->in[ $val ]) &&
            strlen($this->idsadmin->in[ $val ])<1 )
            {
                $this->idsadmin->error(
                $this->idsadmin->lang('DBSpaceCreateFailed') . " $val");
                return;
            }
        }
        $dbname    = $this->idsadmin->in[ 'name' ];
        $dbspath   = $this->idsadmin->in[ 'path' ];
        $dbsoffset = $this->idsadmin->in[ 'offset' ];
        $dbssize   = $this->idsadmin->in[ 'size' ];
        $action    = $this->idsadmin->in[ 'action' ];

        if (strcasecmp($action,"Dbspacef")==0){
            $cmd = "create dbspace";
        } else if (strcasecmp($action,"tempdbs")==0) {
            $cmd = "create tempdbspace";
        } else if (strcasecmp($action,"blobspace")==0){
            $cmd = "create blobspace";
        } else if (strcasecmp($action,"sbspace")==0){
            $cmd = "create sbspace";
        } else
        {
            $this->idsadmin->error(
            $this->idsadmin->lang('DBSpaceCreateActError') . ": [$action]");
            return;
        }
        
        $dbadmin = $this->idsadmin->get_database("sysadmin");
        $sql ="execute function task( '$cmd', '$dbname', '$dbspath', '$dbssize', '$dbsoffset')";
        $sql ="select task( '$cmd', '$dbname', '$dbspath', '$dbssize', '$dbsoffset') as info" .
        " FROM systables where tabid=1";
        $stmt = $dbadmin->query($sql);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->idsadmin->status( $res['INFO'] );
        $stmt->closeCursor();


    }

    /**
     * setup the dbspace detail tabs we want to use.
     *            associated admin tasks.
     *
     * @param string $active
     * @param integer $dbsnum
     * @param string $dbsname
     * @return HTML
     */
    function setupDBSpaceTabs($active, $dbsnum, $dbsname)
    {
         
        require_once ROOT_PATH."/lib/tabs.php";
        $url="index.php?act=space&amp;do=";

        $t = new tabs();
        $t->addtab($url."dbsdetails&amp;dbsnum=$dbsnum&amp;dbsname=$dbsname",
        $this->idsadmin->lang('summary'), ($active == "dbsdetails") ? 1 : 0 );
        if ( ! $this->idsadmin->isreadonly()  )
        {
            if (  $this->idsadmin->phpsession->serverInfo->isPrimary() ) 
            {
                $t->addtab($url."dbsadmin&amp;dbsnum=$dbsnum&amp;dbsname=$dbsname",
        		$this->idsadmin->lang('admin'), ($active == "dbsadmin") ? 1 : 0 );
            }
        }
        $t->addtab($url."dbstables&amp;dbsnum=$dbsnum&amp;dbsname=$dbsname",
        $this->idsadmin->lang('tables'), ($active == "dbstables") ? 1 : 0 );
        $t->addtab($url."dbsextents&amp;dbsnum=$dbsnum&amp;dbsname=$dbsname",
        $this->idsadmin->lang('extents'), ($active == "dbsextents") ? 1 : 0 );
        if (isset($this->idsadmin->in['extentVerification']) ||
        isset($this->idsadmin->in['tableVerification']))
        {
            $t->addtab($url."validate&amp;dbsnum=$dbsnum&amp;dbsname=$dbsname", $this->idsadmin->lang('validation'), ($active == "validate") ? 1 : 0 );
        }

        $html  = ($t->tohtml());
        $html .= "<div class='borderwrapwhite'><br>";
        return $html;
    }


    /**
     * To display details about a specific dbspaces
     * and the assocaited admin tasks for this dbspaces
     *
     * @param integer $dbsnum
     * @param string $dbsname
     */
    function DBSDetailSum( $dbsnum, $dbsname )
    {
        $db = $this->idsadmin->get_database("sysmaster");
        require_once ROOT_PATH."lib/Charts.php";

        $qry = "SELECT A.dbsnum, " .
        " A.name, " .
        " A.owner, " .
        " A.pagesize as pgsize, " .
        " dbinfo('UTC_TO_DATETIME', A.created) as created, " .
        " A.flags " .
        "FROM sysdbstab A " .
        "WHERE A.dbsnum =  " . $dbsnum;

        $stmt = $db->query( $qry );

        while ($res = $stmt->fetch())
        {
            $html = <<<END
	
<TABLE style="width:100% ; height:100%">
<TR><TD valign=top>
<div class="borderwrap">
<table class="gentab_padded">
<tr>
<td class="tblheader" colspan="2" align="center">{$this->idsadmin->lang('dbspaceInfo')}</td>
</tr>
<TR><TH> {$this->idsadmin->lang('dbspaceName')}</TH> <TD> {$res[ 'NAME' ]} </TR>
<TR><TH> {$this->idsadmin->lang('owner')} </TH> <TD> {$res[ 'OWNER' ]} </TR>
<TR><TH> {$this->idsadmin->lang('pagesize')} </TH> <TD> {$this->idsadmin->format_units($res[ 'PGSIZE' ])} </TR>
<TR><TH> {$this->idsadmin->lang('createTime')} </TH> <TD> {$res[ 'CREATED' ]} </TR>

END;
            $dbsname=trim($res[ 'NAME' ]);
            $this->idsadmin->html->add_to_output( $html );
        }

        $qry = "SELECT nkeys, " .
        " sum(npused) as npused, " .
        " sum(npdata) as npdata " .
        " FROM sysptnhdr" .
        " WHERE TRUNC(partnum/1048575,0) = " . $dbsnum .
        " GROUP BY 1 ";

        $stmt = $db->query( $qry );

        $pginfo =  array(
        "Data"  => 0,
        "Index" => 0,
        "Other" => 0,
        "Free"  => 0,
        );
        while ($res = $stmt->fetch())
        {
            if ( $res['NKEYS'] == 0 )
            {
                $pginfo['Data']  += $res['NPDATA'];
                $pginfo['Other'] += $res['NPUSED'] - $res['NPDATA'];
            }
            else if ( $res['NKEYS'] == 1 && $res['NPDATA'] == 0)
            {
                $pginfo['Index']  += $res['NPUSED'];
            }
            else if ( $res['NKEYS'] > 0 )
            {
                $pginfo['Data']  += $res['NPDATA'];
                $pginfo['Index'] += $res['NPUSED'] - $res['NPDATA'];
            }
        }

        $qry = "SELECT " .
        " TRUNC( sum(A.nfree * (C.pagesize/ A.pagesize) )  ) as nfree, " .
        " TRUNC( sum(A.chksize* (C.pagesize/ A.pagesize) ) ) as totalsize " .
        " FROM syschktab A, sysdbstab B, syschktab C " .
        " WHERE A.dbsnum = B.dbsnum " .
        " AND A.dbsnum = " . $dbsnum .
        " AND C.chknum=1 " .
        " GROUP BY A.dbsnum ";

        $stmt = $db->query( $qry );
        while ($res = $stmt->fetch())
        {
            $pginfo['Free'] = $res['NFREE'];
            $tmp=$pginfo['Free']+$pginfo['Data']+$pginfo['Index'];
            if ( $tmp + $pginfo[ 'Other' ] < $res['TOTALSIZE'] )
            $pginfo[ 'Other' ] = $res['TOTALSIZE'] - $tmp;
        }


        $html = <<<END
			<TR>
				<TH> {$this->idsadmin->lang('DataPages')}  </TH> 
				<TD>   {$pginfo[ 'Data' ]} </TD>
			</TR>
			<TR>
				<TH> {$this->idsadmin->lang('IndexPages')} </TH>
				<TD> {$pginfo[ 'Index' ]} </TD>
			</TR>
			<TR>
				<TH> {$this->idsadmin->lang('OtherPages')} </TH> 
				<TD> {$pginfo[ 'Other' ]} </TD>
			</TR>
			<TR>
				<TH> {$this->idsadmin->lang('FreePages')}  </TH> 
				<TD>  {$pginfo['Free']} </TD>
			</TR>
		</TABLE>
	
	</TD>

	<TD valign="top" style="width:50% ; height:50%">
</div>
END;

        $this->idsadmin->html->add_to_output( $html );

        // Before we send the data to the Chart, we need to localize the keywords
        $pginfo_localized = array();
        foreach ($pginfo as $key => $data)
        {
            $pginfo_localized[$this->idsadmin->lang($key)] = $data;
        }
        $this->idsadmin->Charts = new Charts($this->idsadmin);
        $this->idsadmin->Charts->setType("PIE");
        $this->idsadmin->Charts->setData($pginfo_localized);
        $this->idsadmin->Charts->setTitle($this->idsadmin->lang('utilization',array($dbsname)));
        $this->idsadmin->Charts->setLegendDir("vertical");
        $this->idsadmin->Charts->setWidth("100%");
        $this->idsadmin->Charts->setHeight("225");
        $this->idsadmin->Charts->Render();

        $html = <<< EOF
      </TD>
      </TR>
      </TABLE>
	</div>
EOF;
        $this->idsadmin->html->add_to_output( $html );
        //$this->idsadmin->html->add_to_output( $mygraph->pieGraph( $pginfo,
        //$dbsname . " " . $this->idsadmin->lang('utilization') ) );

    }


    /**
     * Show a list of extents in a specific dbspace
     *
     * @param integer $dbsnum
     * @param string $dbsname
     */
    function DBSDetailExtents( $dbsnum, $dbsname )
    {
        require_once ROOT_PATH."lib/gentab.php";

        $tab = new gentab($this->idsadmin);

        $db = $this->idsadmin->get_database("sysmaster");

        $sql  = "SELECT count(*) as cnt FROM sysplog A, syschktab B " .
        " WHERe  dbsnum= " . $dbsnum .
        " AND A.pl_chunk = B.chknum";

        $stmt = $db->query($sql);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        $cnt = $res['CNT'];

        $sql  = "SELECT count(*) as cnt FROM syslogfil A, syschktab B " .
        " WHERe  dbsnum= " . $dbsnum .
        " AND A.chunk = B.chknum";
        $stmt = $db->query($sql);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $cnt = $cnt + $res['CNT'];

        $sql  = "SELECT count(*) as cnt " .
        " FROM sysptnext A, systabnames B, syschktab C " .
        " WHERE TRUNC(partnum/1048575,0) = " . $dbsnum .
        " AND A.pe_partnum = B.partnum "  .
        " AND A.pe_chunk = C.chknum "  ;

        $stmt = $db->query($sql);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $cnt = $cnt + $res['CNT'];


        $qry =
        " select 'physical log' AS name,"
        . " pl_chunk||':'||A.pl_offset as addr, "
        . " pl_chunk||':'||(A.pl_offset+pl_physize-1) as eaddr , "
        . " format_units(pl_physize*B.pagesize,'b') AS pe_size, "
        . " pl_physize AS size, "
        . " A.pl_chunk::int8 * 1048575 + A.pl_offset::int8 AS pe_addr, "
        . " A.pl_chunk::int8 * 1048575 + A.pl_offset::int8 AS pe_eaddr "
        . " FROM sysplog A, syschktab B "
        . " WHERE dbsnum={$dbsnum} AND A.pl_chunk = B.chknum "
        . " UNION "
        . "SELECT "
        . " 'logical log '||number AS name, "
        . " chunk||':'||A.offset AS addr,"
        . " chunk||':'||(A.offset+size-1) AS eaddr , "
        . " format_units(size*B.pagesize,'b') AS pe_size, "
        . " size, "
        . " A.chunk::int8 * 1048575 + A.offset::int8 AS pe_addr,  "
        . " A.chunk::int8 * 1048575 + A.offset::int8 AS pe_eaddr  "
        . " FROM syslogfil A, syschktab B "
        . " WHERE dbsnum= " . $dbsnum
        . " AND A.chunk = B.chknum"
        . " UNION"
        . " SELECT "
        . " trim(B.dbsname)||'.'||trim(B.owner)||'.'||trim(tabname) AS name, "
        . " A.pe_chunk ||':'||A.pe_offset AS addr, "
        . " A.pe_chunk ||':'||(A.pe_offset+A.pe_size-1) AS eaddr, "
        . " format_units(A.pe_size * C.pagesize,'b') AS pe_size, "
        . " A.pe_size AS size, "
        . " A.pe_chunk::int8 * 1048575 + A.pe_offset::int8 AS pe_addr, "
        . " A.pe_chunk::int8 * 1048575 + A.pe_offset::int8 AS pe_eaddr "
        . " FROM sysptnext A, systabnames B, syschktab C "
        . " WHERE TRUNC(partnum/1048575,0) = " . $dbsnum
        . " AND A.pe_partnum = B.partnum "
        . " AND A.pe_chunk = C.chknum "
        . " ORDER BY 6 ";

        $tab->display_tab_by_page(
        $this->idsadmin->lang('dbspace')." ($dbsname) ". $this->idsadmin->lang('ExtentList'),
        array(
        "1" =>  $this->idsadmin->lang('TableName'),
        "6" =>  $this->idsadmin->lang('StartAddr'),
        "7" =>  $this->idsadmin->lang('EndAddr'),
        "5" =>  $this->idsadmin->lang('size'),
        ),
        $qry, $cnt, 20);

    }


    /**
     * Show a list of user defined tables in the dbspace
     *
     * @param integer $dbsnum
     * @param string $dbsname
     */
    function DBSDetailTables( $dbsnum, $dbsname )
    {
        require_once ROOT_PATH."lib/gentab.php";

        $tab = new gentab($this->idsadmin);

        $qry = "SELECT ".
        "trim(dbsname) as dbsname , ".
        "trim(tabname) as table , ".
        "collate, ".
        "sum(nrows) as nrows, ".
        "avg(rowsize) as rowsize, ".
        "dbinfo('UTC_TO_DATETIME', B.created) as created, ".
        "sum(nptotal) as nptotal, ".
        "sum(npused) as npused, ".
        "sum(nextns) as nextents ".
        "from systabnames A,  sysptnhdr B ".
        "where ".
        "trunc(B.partnum/1048575,0) = ".  $dbsnum . " " .
        "AND A.partnum = B.lockid ".
        "AND ( (npused > 0 AND npdata > 0) OR nkeys=0) ".
        "group by B.lockid, A.partnum, dbsname, owner, tabname, B.created,collate ";

        $qrycnt = "SELECT ".
        "count( unique B.lockid ) " .
        "from systabnames A,  sysptnhdr B ".
        "where ".
        "trunc(B.partnum/1048575,0) = " .  $dbsnum . " " .
        "AND A.partnum = B.lockid ".
        "AND ( (npused > 0 AND npdata > 0) OR nkeys=0) ";

        $tab->display_tab_by_page(
        $this->idsadmin->lang('dbspace')." ($dbsname) ". $this->idsadmin->lang('TableList'),
        array(
        "1" => $this->idsadmin->lang('Database'),
        "2" => $this->idsadmin->lang('Table'),
        "3" => $this->idsadmin->lang('Collate'),
        "4" => $this->idsadmin->lang('Rows'),
        "5" => $this->idsadmin->lang('RowSize'),
        "6" => $this->idsadmin->lang('Created'),
        "7" => $this->idsadmin->lang('AllocPages'),
        "8" => $this->idsadmin->lang('UsedPages'),
        "9" => $this->idsadmin->lang('NumExtents'),
        ),
        $qry, $qrycnt, 17, "template_gentab_order.php");

    }


    /**
     * Show the main Dbspace Admin Page
     *
     * @param integer $dbsnum
     * @param string $dbsname
     */
    function DBSDetailAdmin( $dbsnum, $dbsname )
    {

        // require_once ROOT_PATH."lib/gentab.php";
        require_once ROOT_PATH."modules/chunk.php";

        $chunk = new chunk($this->idsadmin);

        $chunk->showChunks( $dbsnum );

        $this->idsadmin->html->add_to_output( "<TABLE> <TR> <TD> " );

        $this->showDropDBSpace( $dbsnum, $dbsname );

        $this->idsadmin->html->add_to_output( "</TR> <TR> <TD>" );

        $this->showAddChunk( $dbsnum, $dbsname );
        $this->idsadmin->html->add_to_output( "</TD> </TR> </TABLE>" );

        if ($this->mirrorEnabled == 1) {
             
            // Determine if current spaces is temp dbspace
            // Cannot do mirroring on temp dbspaces
            $db = $this->idsadmin->get_database("sysmaster");
            $qry = "SELECT CASE " .
        	" WHEN (bitval(A.flags,'0x10')>0 AND bitval(A.flags,'0x2')>0)" .
        	"   THEN 'Mirrored Blobspace' " .
        	" WHEN bitval(A.flags,'0x10')>0 " .
        	"   THEN 'Blobspace' " .
        	" WHEN bitval(A.flags,'0x2000')>0 " .
        	"   THEN 'Temp DBSpace' " .
        	" WHEN (bitval(A.flags,'0x8000')>0 AND bitval(A.flags,'0x2')>0)" .
        	"   THEN 'Mirrored SBSpace' " .
        	" WHEN bitval(A.flags,'0x8000')>0 " .
        	"   THEN 'SBSpace' " .
        	" WHEN bitval(A.flags,'0x2')>0 " .
        	"   THEN 'Mirrored DBSpace' " .
        	" ELSE " .
        	"   'DBSpace' " .
        	" END  as dbstype " .
        	"FROM sysdbstab A " .
        	"WHERE A.dbsnum = {$dbsnum}";
            $stmt = $db->query( $qry );
            $res = $stmt->fetch();
            $dbstype = trim( $res['DBSTYPE'] );
             
            // Only show mirroring options if not temp dbspace
            if (strcasecmp($dbstype,'Temp DBSpace') != 0)
            {
                $chunk->showMirrors( $dbsnum );

                $this->idsadmin->html->add_to_output( "<TABLE> <TR> <TD> " );
                $this->idsadmin->html->add_to_output( "</TR> <TR> <TD>" );
                $this->showAddMirror( $dbsnum, $dbsname );
                $this->idsadmin->html->add_to_output( "</TD> </TR> </TABLE>" );

                $this->showStartStopMirror ( $dbsnum, $dbsname );
            }            
        }
        
        // Integrity checks
        $this->idsadmin->html->add_to_output( "<TABLE> <TR> <TD>" );
        $this->showTableVerification($dbsnum,$dbsname);
        $this->showExtVerification($dbsnum,$dbsname);
        $this->idsadmin->html->add_to_output( "</TD> </TR> </TABLE>" );

    }


    /**
     * Show the drop dbapces form
     *
     * @param integer $dbsnum
     * @param string $dbsname
     */
    function showDropDBSpace( $dbsnum, $dbsname )
    {
        $this->idsadmin->load_lang("rlogs");  // Load rlogs lang file as this hold the "Drop" mesage
        
        $drop_disabled = "";
        if ($dbsnum <= 1) 
        {
        	$drop_disabled = "disabled";
        	$disabled_text = $this->idsadmin->lang("noDropDBSpace");
        }
    	
        if ( $this->idsadmin->isreadonly()
        || ! $this->idsadmin->phpsession->serverInfo->isPrimary() )
        {
            return "";
        }
        
        $html=<<<END
<br/><br/>
<b>{$this->idsadmin->lang('DropSpace',array($dbsname))}</b>
<br/>              
<form method="post"action="index.php?act=space&amp;do=dbspaces">
<table border=0>
<tr>
<td><input type=hidden name=dbsnum value="{$dbsnum}"/></td>
<td><input type=hidden name=dbsname value="{$dbsname}"/></td>
<td>
<select name="confirm" $drop_disabled>
<option value="NO">{$this->idsadmin->lang("No")}</option>
<option value="YES">{$this->idsadmin->lang("Yes")}</option>
</select>
</td>
<td>
<input $drop_disabled type=submit class={$drop_disabled}button name="dbspaceaction_drop" value="{$this->idsadmin->lang('Drop')}"/>
$disabled_text
</td>
</tr></table>
<br/>      
</form>    
END;

        $this->idsadmin->html->add_to_output( $html );

    }

    /**
     * To Execute a Drop of a DBSpace
     * 1. Sanity Check the paramaters
     * 2. Execute the drop command
     * 3. Post the return message
     *
     */
    function execDropDBSpace()
    {
        if ( $this->idsadmin->isreadonly() )
        {
            $this->idsadmin->fatal_error("<center>{$this->idsadmin->lang("NoPermission")}</center>");
        }
    	
        $check =  array(
        "1" => "confirm",
        "2" => "dbsnum",
        "3" => "dbsname",
        );
        foreach ( $check as $index => $val )
        {
            if ( empty($this->idsadmin->in[ $val ]) &&
            strlen($this->idsadmin->in[ $val ])<1 )
            {
                $this->idsadmin->error(
                $this->idsadmin->lang('DropDBSpaceFailed') . " $val");
                return;
            }
        }

        $dbsnum  = $this->idsadmin->in['dbsnum'];
        $dbsname = $this->idsadmin->in['dbsname'];
        $confirm = $this->idsadmin->in['confirm'];

        if (strcasecmp($confirm,"YES")!=0)
        {
            $this->idsadmin->error($this->idsadmin->lang('NoConfDBSpaceDrop') . ": {$dbsname} ");
            return;
        }

        /* Need to findout if we have a blobspace or sbspace */
        $dbstype = "";
        $dbsysmaster = $this->idsadmin->get_database("sysmaster");

        $qry = "SELECT CASE " .
        " WHEN bitval(A.flags,'0x10')>0 " .
        "   THEN 'BLOBSPACE' " .
        " WHEN bitval(A.flags,'0x2000')>0 " .
        "   THEN 'TEMP DBSPACE' " .
        " WHEN bitval(A.flags,'0x8000')>0 " .
        "   THEN 'SBSPACE' " .
        " WHEN bitval(A.flags,'0x2')>0 " .
        "   THEN 'MIRRORED DBSPACE' " .
        " ELSE " .
        "   'DBSPACE' " .
        " END  as dbstype " .
        "FROM sysdbstab A " .
        "WHERE A.dbsnum=$dbsnum";

       	$stmt = $dbsysmaster->query($qry);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        $dbstype = trim($res['DBSTYPE']);

        /* Now run task to drop the space */
        $dbadmin = $this->idsadmin->get_database("sysadmin");
        if (strcasecmp($dbstype,"BLOBSPACE")==0)
        {
            $sql ="select task( 'drop blobspace', '$dbsname') as info" .
            " FROM systables where tabid=1";
        }
        else if (strcasecmp($dbstype,"SBSPACE")==0)
        {
            // ** NOTE: Do not run sbspace drop on server versions < 11.10.UC2
            // because a server bug in 11.10.xC1 can cause the server to crash.
            if (Feature::isAvailable(Feature::CHEETAH_UC2, $this->idsadmin->phpsession->serverInfo->getVersion()))
            {
                $sql="select task( 'drop sbspace', '$dbsname') as info" .
                     " FROM systables where tabid=1";
            } else {
                $sql="select 'Sorry, dropping sbspaces is not supported on this server version.' as info" .
                     " FROM systables where tabid=1";
            }
        }
        else
        {
            $sql ="select task( 'drop dbspace', '$dbsname') as info" .
            " FROM systables where tabid=1";
        }

        $stmt = $dbadmin->query($sql);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->idsadmin->status( $res['INFO'] );
        $stmt->closeCursor();
    }


    /**
     * Show the add chunk command
     *
     * @param integer $dbsnum
     * @param string $dbsname
     */
    function showAddChunk( $dbsnum, $dbsname )
    {
        if ( $this->idsadmin->isreadonly()
        || ! $this->idsadmin->phpsession->serverInfo->isPrimary() )
        {
            return "";
        }
        $html=<<<END
<br/><br/>
<b>{$this->idsadmin->lang('AddSpaceto')} [$dbsname]</b>
<br/>
<script type="text/javascript" src='jscripts/ajax.js'></script>
<script type="text/javascript">
function setchunkdata(ajaxform)
{
	var ajaxdata = "path=" + ajaxform.chkpath.value + "&"
				 + "offset=" + ajaxform.chkoffset.value + "&"
				 + "size=" + ajaxform.chksize.value + "&"
				 + "withcheck=" + ajaxform.chkwithcheck.value + "&"
				 + "dbsnum=" + ajaxform.dbsnum.value + "&"
				 + "dbsname=" + ajaxform.dbsname.value;
	return ajaxdata;
}

function setmirrorchunkdata(ajaxform)
{
	var ajaxdata = "path=" + ajaxform.chkpath.value + "&"
				 + "offset=" + ajaxform.chkoffset.value + "&"
				 + "size=" + ajaxform.chksize.value + "&"
				 + "withcheck=" + ajaxform.chkwithcheck.value + "&"
				 + "mirrorpath=" + ajaxform.mirrorpath.value + "&"
				 + "mirroroffset=" + ajaxform.mirroroffset.value + "&"
				 + "dbsnum=" + ajaxform.dbsnum.value + "&"
				 + "dbsname=" + ajaxform.dbsname.value;
	return ajaxdata;
}
</script>
<form>
<table border=0>
<tr>
   <th>{$this->idsadmin->lang('Path')}</th>
   <th>{$this->idsadmin->lang('Offset')}</th>
   <th>{$this->idsadmin->lang('size')}</th>
   <th>{$this->idsadmin->lang('FileCreation')}</TH>
</tr>

<tr>
    <td><input id="chkpath" type=text name="path" size="30"/></td>
    <td><input id="chkoffset" type=text name="offset" size="6" value="0"/></td>
    <td><input id="chksize" type=text name="size" size="8" value="10 M"/>
        <input id="dbsname" type=hidden name="dbsname" value="{$dbsname}"/>
        <input id="dbsnum" type=hidden name="dbsnum" value="{$dbsnum}"/>
    </td>
    <td><select id="chkwithcheck" name="withcheck">
               <option value="create">{$this->idsadmin->lang('MayCreateFile')}</option>
               <option value="create with_check">{$this->idsadmin->lang('FileMustExist')}</option>
        </select>

    </td>
    <td><input type=button class="button" name="addchunk"
    onClick="loadAJAX('dbsadminpage','index.php?act=space&amp;do=adddbchunk',setchunkdata(this.form),createChkhandler)"
         value="{$this->idsadmin->lang("AddChunk")}"/></td>
</tr>
END;

        $this->idsadmin->html->add_to_output( $html );

        if ($this->mirrorEnabled == 1) {
             
            // Determine if current spaces is temp dbspace
            // Cannot do mirroring on temp dbspaces
            $db = $this->idsadmin->get_database("sysmaster");
            $qry = "SELECT CASE " .
        	" WHEN (bitval(A.flags,'0x10')>0 AND bitval(A.flags,'0x2')>0)" .
        	"   THEN 'Mirrored Blobspace' " .
        	" WHEN bitval(A.flags,'0x10')>0 " .
        	"   THEN 'Blobspace' " .
        	" WHEN bitval(A.flags,'0x2000')>0 " .
        	"   THEN 'Temp DBSpace' " .
        	" WHEN (bitval(A.flags,'0x8000')>0 AND bitval(A.flags,'0x2')>0)" .
        	"   THEN 'Mirrored SBSpace' " .
        	" WHEN bitval(A.flags,'0x8000')>0 " .
        	"   THEN 'SBSpace' " .
        	" WHEN bitval(A.flags,'0x2')>0 " .
        	"   THEN 'Mirrored DBSpace' " .
        	" ELSE " .
        	"   'DBSpace' " .
        	" END  as dbstype " .
        	"FROM sysdbstab A " .
        	"WHERE A.dbsnum = {$dbsnum}";
            $stmt = $db->query( $qry );
            $res = $stmt->fetch();
            $dbstype = trim( $res['DBSTYPE'] );
             
            // Only show AddChunkWithMirror option if not temp dbspace
            if (strcasecmp($dbstype,'Temp DBSpace') != 0)
            {
                $this->showAddChunkWithMirror();
            }
        }

        $html=<<<END
</table>
<br/>
</form>
END;
         
        $this->idsadmin->html->add_to_output( $html );

    }

    function showTableVerification( $dbsnum, $dbsname )
    {
        $this->idsadmin->load_lang("systemvalidation");
        if ( $this->idsadmin->isreadonly() || !$this->idsadmin->phpsession->serverInfo->isPrimary() )
        {
            return "";
        }

        $html=<<<END

<b>{$this->idsadmin->lang('SystemIntegrity')} [$dbsname]</b>
<script type="text/javascript">
function confirmCheckData()
{
    c = confirm("{$this->idsadmin->lang("confirmCheckData")}");

    if ( c )
    {
        document.tableVerificationForm.submit();
    }
}
</script>
<form method="post" name="tableVerificationForm" action="index.php?act=space&amp;do=validate&amp;dbsnum=$dbsnum&amp;dbsname=$dbsname&amp;tableVerification">
<table border=0>

<tr>
	    <td><input type=button class="button" name="tableVerification" 
         value="{$this->idsadmin->lang('butCheckDbsTabFormat')}" onclick="confirmCheckData()"/></td>
</tr>
</table>
</form>
END;

        $this->idsadmin->html->add_to_output( $html );
    }


    function showExtVerification( $dbsnum, $dbsname )
    {

        if ( $this->idsadmin->isreadonly() || !$this->idsadmin->phpsession->serverInfo->isPrimary() )
        {
            return "";
        }

        $html=<<<END

<form method="post" action="index.php?act=space&amp;do=validate&amp;dbsnum=$dbsnum&amp;dbsname=$dbsname">
<table border=0>

<tr>
	    <td><input type=submit class="button" name="extentVerification" 
         value="{$this->idsadmin->lang('butCheckDbsExt')}"/></td>
</tr>
</table>
</form>
END;
        $this->idsadmin->html->add_to_output( $html );

    }




    /**
     * Show the add chunk with mirror command
     *
     */
    function showAddChunkWithMirror()
    {
    	
        if ( $this->idsadmin->isreadonly())
        {
            return "";
        }
       	$html=<<<END
        	 
<tr>
   <th>{$this->idsadmin->lang('MirrorPath')}</th>
   <th>{$this->idsadmin->lang('MirrorOffset')}</th>
</tr>

<tr>
    <td><input id="mirrorpath" type=text name="mirrorpath" size="30"/></td>
    <td><input id="mirroroffset" type=text name="mirroroffset" size="6" value="0"/></td>
	<td><td>
	<td><input type=button class="button" name="addchunkmirror" 
	onClick="loadAJAX('dbsadminpage','index.php?act=space&amp;do=addmirrorchunk',setmirrorchunkdata(this.form),createChkhandler)"
         value="{$this->idsadmin->lang("AddChunkMirror")}"/></td>
</tr>
END;

       	$this->idsadmin->html->add_to_output( $html );
    }
     
     
    function execTableVerification($dbsnum,$dbsname)
    {
        if ( $this->idsadmin->isreadonly() )
        {
            $this->idsadmin->fatal_error("<center>{$this->idsadmin->lang("NoPermission")}</center>");
        }

        if (!$this->idsadmin->phpsession->serverInfo->isPrimary())
        {
            $this->idsadmin->fatal_error("{$this->idsadmin->lang("NotValidOnSecondary")}");
        }

        require_once ROOT_PATH."lib/gentab.php";
        $tab = new gentab($this->idsadmin);
        $dbadmin = $this->idsadmin->get_database("sysadmin");

        $sql ="SELECT admin('check data', partnum) AS id " .
              "FROM sysmaster:systables  WHERE trunc(partnum/1048575)=$dbsnum" .
              " INTO TEMP temp_list ;";
        $dbadmin->query($sql);

        $sql ="SELECT command_history.cmd_ret_msg FROM command_history, temp_list " .
	  "WHERE ABS(temp_list.id) = cmd_number " .
 	  "ORDER BY cmd_number ;";

        $tmp = $this->idsadmin->lang('oncheckSystemValidation');
        $tab->display_tab(
        "$tmp", 
        array(
        "CMD_RET_MSG" => $this->idsadmin->lang('ValidationResultsTo'),
        ),
        $sql,"gentab_systemvalidation.php",$dbadmin);

        $sql ="drop table temp_list ;";
        $dbadmin->query($sql);
         
    }

    function execExtentVerification($dbsnum,$dbsname)
    {
        if ( $this->idsadmin->isreadonly() )
        {
            $this->idsadmin->fatal_error("<center>{$this->idsadmin->lang("NoPermission")}</center>");
        }

        if (!$this->idsadmin->phpsession->serverInfo->isPrimary())
        {
            $this->idsadmin->fatal_error("{$this->idsadmin->lang("NotValidOnSecondary")}");
        }

        require_once ROOT_PATH."lib/gentab.php";
        $tab = new gentab($this->idsadmin);
        $dbadmin = $this->idsadmin->get_database("sysadmin");

        $sql ="SELECT  task('check extents',$dbsnum) " .
              "as ext FROM sysmaster:sysdual";

        $tmp = $this->idsadmin->lang('oncheckSystemValidation');
        $tab->display_tab( "$tmp",
        array(
           "EXT" => $this->idsadmin->lang('ValidationResults'),
        ),
        $sql,"gentab_systemvalidation.php",$dbadmin);
    }


    /**
     * 1. Sanity Check paramaters
     * 2. Execute the add chunk command
     * 3. Set the return status message
     *
     */
    function execAddChunk()
    {
        $this->idsadmin->render = false;
    	
    	if ( $this->idsadmin->isreadonly() )
        {
            echo "{$this->idsadmin->lang('Error')} ".$this->idsadmin->lang("NoPermission");
        	return;
        }
        
        $check =  array(
        "1" => "path",
        "2" => "offset",
        "3" => "size",
        "4" => "withcheck",
        "5" => "dbsname",
        );
        foreach ( $check as $index => $val )
        {
            if ( empty($this->idsadmin->in[ $val ]) &&
            strlen($this->idsadmin->in[ $val ])<1 )
            {
                echo "{$this->idsadmin->lang('Error')} ".$this->idsadmin->lang('AddChunkFailed') . " {$this->idsadmin->in['dbsname']}. {$this->idsadmin->lang('NotSetRightDBSpaceValue')} $val";
                return;
            }
        }

        $dbsnum  = $this->idsadmin->in['dbsnum'];
        $dbsname = $this->idsadmin->in['dbsname'];
        $path    = $this->idsadmin->in['path'];
        $offset  = $this->idsadmin->in['offset'];
        $size    = $this->idsadmin->in['size'];
        $withcheck = $this->idsadmin->in['withcheck'];

        /* Need to findout if we have a blobspace */

        $dbadmin = $this->idsadmin->get_database("sysadmin");
        $sql ="select admin( '$withcheck chunk', '$dbsname', '$path', " .
        " '$size', '$offset')  as cmd_number" .
        " FROM systables where tabid=1";

        $stmt = $dbadmin->query($sql);
        $res = $stmt->fetch();
    	$cmd_no = $res['CMD_NUMBER'];
        
    	if($cmd_no < 0){
    		//command failed
    		$cmd_no = abs($cmd_no);
    		$sql = "SELECT cmd_ret_msg AS retinfo".
	        " FROM command_history WHERE cmd_number={$cmd_no}";
	        $stmt = $dbadmin->query($sql);
	        $res = $stmt->fetch(PDO::FETCH_ASSOC);
	        echo $res['RETINFO'];
    	}else{
    		//command suceeded
    		echo $this->idsadmin->lang('ChunkAddSuccess');
    	}
    }

    /**
     * 1. Sanity Check paramaters
     * 2. Execute the add chunk with mirror command
     * 3. Set the return status message
     *
     */
    function execAddChunkWithMirror()
    {
        $this->idsadmin->render = false;
    	
    	if ( $this->idsadmin->isreadonly() )
        {
            echo "{$this->idsadmin->lang('Error')} ".$this->idsadmin->lang("NoPermission");
        	return;
        }
    	
        $check =  array(
        "1" => "path",
        "2" => "offset",
        "3" => "size",
        "4" => "withcheck",
        "5" => "mirrorpath",
        "6" => "mirroroffset",
        "7" => "dbsname",
        );
        foreach ( $check as $index => $val )
        {
            if ( empty($this->idsadmin->in[ $val ]) &&
            strlen($this->idsadmin->in[ $val ])<1 )
            {
                echo "{$this->idsadmin->lang('Error')} ".$this->idsadmin->lang('AddChunkFailed') . " {$this->idsadmin->in['dbsname']}. " .
                $this->idsadmin->lang('NotSetRightDBSpaceValue') . " $val";
                return;
            }
        }

        $dbsnum  = $this->idsadmin->in['dbsnum'];
        $dbsname = $this->idsadmin->in['dbsname'];
        $path    = $this->idsadmin->in['path'];
        $offset  = $this->idsadmin->in['offset'];
        $size    = $this->idsadmin->in['size'];
        $withcheck = $this->idsadmin->in['withcheck'];
        $mpath   = $this->idsadmin->in['mirrorpath'];
        $moffset = $this->idsadmin->in['mirroroffset'];

        /* Need to findout if we have a blobspace */

        $dbadmin = $this->idsadmin->get_database("sysadmin");
        $sql ="select admin( '$withcheck chunk', '$dbsname', '$path', " .
        " '$size', '$offset', '$mpath', '$moffset')  as cmd_number" .
        " FROM systables where tabid=1";

        $stmt = $dbadmin->query($sql);
        $res = $stmt->fetch();
    	$cmd_no = $res['CMD_NUMBER'];
    	
    	if($cmd_no < 0){
    		//command failed
    		$cmd_no = abs($cmd_no);
    		$sql = "SELECT cmd_ret_msg AS retinfo".
	        " FROM command_history WHERE cmd_number={$cmd_no}";
	        $stmt = $dbadmin->query($sql);
	        $res = $stmt->fetch(PDO::FETCH_ASSOC);
	        echo $res['RETINFO'];
    	}else{
    		//command suceeded
    		echo $this->idsadmin->lang('MirrorChunkAddSuccess');
    	}
    }

    /**
     * Show the add mirror command
     *
     * @param integer $dbsnum
     * @param string $dbsname
     */
    function showAddMirror( $dbsnum, $dbsname )
    {
        if ( $this->idsadmin->isreadonly())
        {
            return "";
        }
        $html.=<<<END
<br/><br/>
<b>{$this->idsadmin->lang('AddMirrorto')} [$dbsname]</b>
<br/>
<script type="text/javascript">
function setmirrordata(ajaxform)
{
	var ajaxdata = "path=" + ajaxform.path.value + "&"
				 + "offset=" + ajaxform.offset.value + "&"
				 + "mpath=" + ajaxform.mpath.value + "&"
				 + "moffset=" + ajaxform.moffset.value + "&"
				 + "dbsnum=" + ajaxform.dbsnum.value + "&"
				 + "dbsname=" + ajaxform.dbsname.value;
	return ajaxdata;
}
</script>
<form>
<table border=0>
<tr>
	<th>{$this->idsadmin->lang('Path')}</th>
   <th>{$this->idsadmin->lang('Offset')}</th>
   <th>{$this->idsadmin->lang('MirrorPath')}</th>
   <th>{$this->idsadmin->lang('MirrorOffset')}</th>
</tr>
<tr>
	<td><input id="path" type=text name="path" size="30"/></td>
    <td><input id="offset" type=text name="offset" size="6" value="0"/></td>
    <td><input id="mpath" type=text name="mpath" size="30"/></td>
    <td><input id="moffset" type=text name="moffset" size="6" value="0"/></td>
    <td><input id="dbsnum" type=hidden name="dbsnum" value="{$dbsnum}"/></td>
	<td><input id="dbsname" type=hidden name="dbsname" value="{$dbsname}"/></td>
    <td><input type=button class="button" name="addmirror" 
    	onClick="loadAJAX('dbsadminpage','index.php?act=space&amp;do=addmirror',setmirrordata(this.form),createChkhandler)"
         value="{$this->idsadmin->lang("AddMirror")}"/></td>
</tr>

</table>
<br/>
</form>
END;

        $this->idsadmin->html->add_to_output( $html );

    } // end showAddMirror

    /**
     * Determine if the mirroring is started or stopped on the current space
     * And display button to allow user to either start or stop mirroring.
     *
     * @param integer $dbsnum
     * @param string $dbsname
     */
    function showStartStopMirror( $dbsnum, $dbsname )
    {
        if ( $this->idsadmin->isreadonly())
        {
            return "";
        }
    	
        // Determine if mirroring is currently started or stopped on the space
        $dbadmin = $this->idsadmin->get_database("sysmaster");
        $sql ="select is_mirrored from sysdbspaces where dbsnum={$dbsnum}";
        $stmt = $dbadmin->query($sql);
        $res = $stmt->fetch();
        $is_mirrored = trim($res['IS_MIRRORED']);
         
        // Display start or stop mirroring button depending on the current status
        $this->idsadmin->html->add_to_output("<br/><br/>");
        if ($is_mirrored == 1)
        {
            $this->idsadmin->html->add_to_output("<b>{$this->idsadmin->lang('MirroringStarted')} [$dbsname]</b>");
            $this->idsadmin->html->add_to_output("<form method=\"post\" action=\"index.php?act=space&amp;do=dbsadmin&amp;dbsnum={$dbsnum}&amp;dbsname={$dbsname}\">");
        }
        else
        {
            $html=<<<END
<b>{$this->idsadmin->lang('MirroringStopped')} [$dbsname]</b>
<script type="text/javascript">
function confirmmirror() { 
return (confirm("Warning: The start mirror command can take a while, depending on the size of the space.  Are you sure you want to start mirroring now?"));
}
</script>
<form method="post" action="index.php?act=space&amp;do=dbsadmin&amp;dbsnum={$dbsnum}&amp;dbsname={$dbsname}" onsubmit="return confirmmirror()">
END;
            $this->idsadmin->html->add_to_output($html);
             
             
        }

        $html=<<<END

<table border=0>
<tr>
<td><input type=hidden name=dbsnum value="{$dbsnum}"/></td>
<td><input type=hidden name=dbsname value="{$dbsname}"/></td>
<td>
<select name="confirm">
<option value="NO">NO</option>
<option value="YES">YES</option>
</select>
END;
        $this->idsadmin->html->add_to_output( $html );

        if ($is_mirrored == 1)
        {
            $this->idsadmin->html->add_to_output("<td><input type=submit class=\"button\" ".
				"name=\"stopmirror\" value=\"{$this->idsadmin->lang("StopMirror")}\"/></td>");
        }
        else
        {
            $this->idsadmin->html->add_to_output("<td><input type=submit class=\"button\" ".
				"name=\"startmirror\" value=\"{$this->idsadmin->lang("StartMirror")}\"/></td>");
        }

        $html=<<<END
</tr>
</table>
<br/>
</form>
END;

        $this->idsadmin->html->add_to_output( $html );

    } // end showStartStopMirror

    /**
     * 1. Sanity Check paramaters
     * 2. Execute the add mirror command
     * 3. Set the return status message
     *
     */
    function execAddMirror()
    {
        $this->idsadmin->render = false;
    	
    	if ( $this->idsadmin->isreadonly() )
        {
            echo "{$this->idsadmin->lang("Error")} ".$this->idsadmin->lang("NoPermission");
            return;
        }
    	
        $check =  array(
        "1" => "path",
        "2" => "offset",
        "3" => "mpath",
        "4" => "moffset",
        );
        foreach ( $check as $index => $val )
        {
            if ( empty($this->idsadmin->in[ $val ]) &&
            strlen($this->idsadmin->in[ $val ])<1 )
            {
                echo "{$this->idsadmin->lang('Error')} ".$this->idsadmin->lang('AddingMirrorFailed') . " {$this->idsadmin->in['dbsname']}. " .
                $this->idsadmin->lang('MirrorValueNotSetRight') . " $val";
                return;
            }
        }

        $dbsnum  = $this->idsadmin->in['dbsnum'];
        $dbsname = $this->idsadmin->in['dbsname'];
        $path    = $this->idsadmin->in['path'];
        $offset  = $this->idsadmin->in['offset'];
        $mpath    = $this->idsadmin->in['mpath'];
        $moffset  = $this->idsadmin->in['moffset'];

        /* Need to findout if we have a blobspace */

        $dbadmin = $this->idsadmin->get_database("sysadmin");
        $sql ="select admin( 'add mirror', '$dbsname', '$path', " .
        " '$offset', '$mpath', '$moffset')  as cmd_number" .
        " FROM systables where tabid=1";

        $stmt = $dbadmin->query($sql);
        $res = $stmt->fetch();
    	$cmd_no = $res['CMD_NUMBER'];
        
    	if($cmd_no < 0){
    		//command failed
    		$cmd_no = abs($cmd_no);
    		$sql = "SELECT cmd_ret_msg AS retinfo".
	        " FROM command_history WHERE cmd_number={$cmd_no}";
	        $stmt = $dbadmin->query($sql);
	        $res = $stmt->fetch(PDO::FETCH_ASSOC);
	        echo $res['RETINFO'];
    	}else{
    		//command suceeded
    		echo $this->idsadmin->lang('MirrorAddSuccess');
    	}
    } // end execAddMirror

    /**
     * 1. Sanity check parameters
     * 2. Execute the start mirroring command
     * 3. Set the return status message
     *
     */
    function execStartMirror()
    {
        if ( $this->idsadmin->isreadonly() )
        {
            $this->idsadmin->fatal_error("<center>{$this->idsadmin->lang("NoPermission")}</center>");
        }
    	
        $check =  array(
        "1" => "confirm",
        "2" => "dbsnum",
        "3" => "dbsname",
        );
        foreach ( $check as $index => $val )
        {
            if ( empty($this->idsadmin->in[ $val ]) &&
            strlen($this->idsadmin->in[ $val ])<1 )
            {
                $this->idsadmin->error(
                $this->idsadmin->lang('StartMirrorFailed') . " {$this->idsadmin->in['dbsname']}. {$this->idsadmin->lang('NotSetRightDBSpaceValue')} {$val}");
                return;
            }
        }

        $dbsnum  = $this->idsadmin->in['dbsnum'];
        $dbsname = $this->idsadmin->in['dbsname'];
        $confirm = $this->idsadmin->in['confirm'];

        if (strcasecmp($confirm,"YES")!=0)
        {
            $this->idsadmin->error($this->idsadmin->lang('NoConfStartMirror') . " {$dbsname}" );
            return;
        }

        $dbadmin = $this->idsadmin->get_database("sysadmin");
        $sql ="select task( 'start mirroring', '$dbsname')  as info" .
        " FROM systables where tabid=1";

        $stmt = $dbadmin->query($sql);
        $res = $stmt->fetch();
        $this->idsadmin->status( $res['INFO']  );
        $stmt->closeCursor();
    } // end execStartMirror

    /**
     * 1. Sanity check parameters
     * 2. Execute the stop mirroring command
     * 3. Set the return status message
     *
     */
    function execStopMirror()
    {
        if ( $this->idsadmin->isreadonly() )
        {
            $this->idsadmin->fatal_error("<center>{$this->idsadmin->lang("NoPermission")}</center>");
        }
    	
        $check =  array(
        "1" => "confirm",
        "2" => "dbsnum",
        "3" => "dbsname",
        );
        foreach ( $check as $index => $val )
        {
            if ( empty($this->idsadmin->in[ $val ]) &&
            strlen($this->idsadmin->in[ $val ])<1 )
            {
                $this->idsadmin->error(
                $this->idsadmin->lang('StopMirrorFailed') . " {$this->idsadmin->in['dbsname']} {$this->idsadmin->lang('NotSetRightDBSpaceValue')} {$val}");
                return;
            }
        }

        $dbsnum  = $this->idsadmin->in['dbsnum'];
        $dbsname = $this->idsadmin->in['dbsname'];
        $confirm = $this->idsadmin->in['confirm'];

        if (strcasecmp($confirm,"YES")!=0)
        {
            $this->idsadmin->error($this->idsadmin->lang('NoConfStopMirror') . " {$dbsname}.");
            return;
        }

        $dbadmin = $this->idsadmin->get_database("sysadmin");
        $sql ="select task( 'stop mirroring', '$dbsname')  as info" .
        " FROM systables where tabid=1";

        $stmt = $dbadmin->query($sql);
        $res = $stmt->fetch();
        $this->idsadmin->status( $res['INFO']  );
        $stmt->closeCursor();
    } // end execStartMirror

    /**
     * Determine if server has mirroring enabled
     */
    function isMirrorEnabled() {
        $conn = $this->idsadmin->get_database("sysmaster");

        $qry = "SELECT ".
            "cf_effective " .
            "FROM sysconfig A " .
            "WHERE cf_name='MIRROR'";
        ;

        $stmt = $conn->query($qry);
        while ($res = $stmt->fetch(PDO::FETCH_ASSOC) )
        {
            $this->mirrorEnabled = $res['CF_EFFECTIVE'];
        }
    } // end isMirrored

}   // end class
?>
