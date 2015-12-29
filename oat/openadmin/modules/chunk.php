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
 * Display information about the database
 * object Chunks
 *
 */
class chunk {

	public $idsadmin;
	private $mirror = -1;

	function __construct(&$idsadmin)
	{
		$this->idsadmin = &$idsadmin;
		$this->idsadmin->load_lang("chunk");
		$this->idsadmin->load_template("template_gentab_chunk");
		$this->isMirrored();
	}


	/**
	 * The run function is what index.php will call.
	 * The decission of what to actually do is based
	 * on the value of the $this->idsadmin->in['do']
	 *
	 */
	function run()
	{
		$this->idsadmin->setCurrMenuItem("chunks");
		$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("Chunks"));
		
		if (isset($this->idsadmin->in['reportMode']))
		{
			$this->idsadmin->setCurrMenuItem("Reports");
		}

		switch($this->idsadmin->in['do'])
		{
			case 'show';
			$this->idsadmin->html->add_to_output( $this->setupChunkTabs() );
			$this->showChunks( "");
			break;
			case 'mirror';
			$this->idsadmin->html->add_to_output( $this->setupChunkTabs() );
			$this->showMirrors( "");
			break;
			case 'chunkio';
			$this->idsadmin->html->add_to_output( $this->setupChunkTabs() );
			$this->showChunkIO( "" );
			break;
			default:
				$this->idsadmin->error($this->idsadmin->lang('InvalidURL_do_param'));
				break;
		}
	} # end function run

	/**
	 * Function to display chunk tabs
	 */
	function setupChunkTabs()
	{
		// don't setup tabs if in report mode
		if (isset($this->idsadmin->in["runReports"]) || isset($this->idsadmin->in['reportMode'])) return;

		require_once ROOT_PATH."/lib/tabs.php";
		$url="index.php?act=chunk&amp;do=";
		$active = $this->idsadmin->in['do'];

		$t = new tabs();
		$t->addtab($url."show", $this->idsadmin->lang("Chunk"),
		($active == "show") ? 1 : 0 );
		if ($this->mirror == 1) {
			$t->addtab($url."mirror", $this->idsadmin->lang("MirrorChunk"),
			($active == "mirror") ? 1 : 0 );
		}
		$t->addtab($url."chunkio",  $this->idsadmin->lang("ChunkIO"),
		($active == "chunkio") ? 1 : 0 );

		$html  = ($t->tohtml());
		$html .= "<div class='borderwrapwhite'><br>";
		return $html;
	} #end setuptabs

	/**
	 * This function display a list of chunks
	 *
	 * @param integer $dbsnum
	 */
	function showChunks( $dbsnum="" )
	{
		require_once ROOT_PATH."lib/gentab.php";
		$tab = new gentab($this->idsadmin);

		$qrycnt = "SELECT count(*) from syschktab";

		$defPagesize = $this->idsadmin->phpsession->serverInfo->getDefaultPagesize();
		if ($this->idsadmin->phpsession->serverInfo->isPrimary())
		{
			// If server is a primary server or server not part of high-availability env,
			// use the more complex query to determine if chunks are online/offline/recovering
			$qry = "SELECT ".
	            "A.chknum, " .
                    "C.name as spacename, " .
	            "A.pagesize/1024 as pgsize, " .
	            "format_units(A.offset, {$defPagesize}) as off, ".
	            "format_units(A.chksize, {$defPagesize}) as size, ".
	            "format_units(decode(A.mdsize,-1,A.nfree,A.udfree),{$defPagesize}) as free, ".
	            "TRUNC(100 - decode(A.mdsize,-1,A.nfree,A.udfree)*100/A.chksize,2 )|| '%' as used, " .
	            "CASE " .
			"WHEN B.is_offline=1 THEN 'OFFLINE'	 " .
			"WHEN B.is_recovering=1 THEN 'RECOVERING' " .
			"ELSE 'ONLINE' " .
			"END as status, " .
	            "A.fname, ".
	            "A.offset as sortoffset, ".
	            "A.chksize as sortsize, ".
	            "decode(A.mdsize,-1,A.nfree,A.udfree) as sortfree, ".
	            "TRUNC(100 - decode(A.mdsize,-1,A.nfree,A.udfree)*100/A.chksize,2 ) as sortused ".
			"FROM syschktab A, syschunks B, sysdbstab C " .
		    "WHERE A.dbsnum = B.dbsnum " .
		    "AND A.chknum = B.chknum " .
		    "AND A.dbsnum = C.dbsnum " .
			(empty($dbsnum) ? "" : "AND A.dbsnum = ".$dbsnum )
			;
			 
			$conn = $this->idsadmin->get_database("sysmaster");
			try{
				$stmt = $conn->query($qry,false,true);
			}catch(Exception $e){
				if($e->getCode() == -229)//This is the case when rootdbs is full and ODBC failed to create temp space.
				{
					$this->showChunks_noTabJoin($dbsnum);
					return;
				}
			}
			 
			$tab->display_tab_by_page($this->idsadmin->lang("ChunkTable"),
			array(
                  "1" => $this->idsadmin->lang("ChunkNum"),
                  "2" => $this->idsadmin->lang("SpaceName"),
                  "3" => $this->idsadmin->lang("PageSize"),
                  "10" => $this->idsadmin->lang("Offset"),
                  "11" => $this->idsadmin->lang("Size"),
                  "12" => $this->idsadmin->lang("Free"),
                  "13" => $this->idsadmin->lang("Used"),
                  "8" => $this->idsadmin->lang("Status"),
                  "9" => $this->idsadmin->lang("Path"),
			),
			$qry, $qrycnt, NULL, "template_gentab_chunk.php");
			 
		} else {
			// If server is a secondary server, use simplified query
			// because above query will result in -229/ISAM 140 operation illegal on a DR Secondary
			$qry = "SELECT ".
	            "A.chknum, " .
                    "B.name as spacename, " .
	            "A.pagesize/1024 as pgsize, " .
	            "format_units(A.offset, {$defPagesize}) as off, ".
	            "format_units(A.chksize, {$defPagesize}) as size, ".
	            "format_units(decode(A.mdsize,-1,A.nfree,A.udfree),{$defPagesize}) as free, ".
	            "TRUNC(100 - decode(A.mdsize,-1,A.nfree,A.udfree)*100/A.chksize,2 )|| '%' as used, " .
	            "A.fname, ".
	            "A.offset as sortoffset, ".
	            "A.chksize as sortsize, ".
	            "decode(A.mdsize,-1,A.nfree,A.udfree) as sortfree, ".
	            "TRUNC(100 - decode(A.mdsize,-1,A.nfree,A.udfree)*100/A.chksize,2 ) as sortused ".
		    "FROM syschktab A, sysdbstab B " .
                    "WHERE A.dbsnum = B.dbsnum " .
			(empty($dbsnum) ? "" : "WHERE A.dbsnum = ".$dbsnum )
			;
			 
			$conn = $this->idsadmin->get_database("sysmaster");
			try{
				$stmt = $conn->query($qry,false,true);
			}catch(Exception $e){
				if($e->getCode() == -229)//This is the case when rootdbs is full and ODBC failed to create temp space.
				{
					$this->showChunks_noTabJoin($dbsnum);
					return;
				}
			}
			 
			$tab->display_tab_by_page($this->idsadmin->lang("ChunkTable"),
			array(
                  "1" => $this->idsadmin->lang("ChunkNum"),
                  "2" => $this->idsadmin->lang("SpaceName"),
                  "3" => $this->idsadmin->lang("PageSize"),
                  "9" => $this->idsadmin->lang("Offset"),
                  "10" => $this->idsadmin->lang("Size"),
                  "11" => $this->idsadmin->lang("Free"),
                  "12" => $this->idsadmin->lang("Used"),
                  "8" => $this->idsadmin->lang("Path"),
			),
			$qry, $qrycnt, NULL, "template_gentab_chunk.php");
		}
		 
	} #end showChunks

	/**
	 * This function displays a list of mirror chunks
	 *
	 * @param integer $dbsnum
	 */
	function showMirrors( $dbsnum="" )
	{
		require_once ROOT_PATH."lib/gentab.php";
		$tab = new gentab($this->idsadmin);


		$qry = "SELECT ".
            "A.chknum, " .
            "D.name as spacename, " .
            "A.pagesize/1024 as pgsize, " .
            "format_units(A.offset*C.pagesize/1024, 'k') as off, " .
            "format_units(A.chksize*C.pagesize/1024, 'k') as size, " .
            "CASE " .
			"WHEN B.is_offline=1 THEN 'OFFLINE'	 " .
			"WHEN B.is_recovering=1 THEN 'RECOVERING' " .
			"ELSE 'ONLINE' " .
			"END as status, " .
            "A.fname, ".
            "A.offset as sortoffset, ".
            "A.chksize as sortsize ".
            "from sysmchktab A, syschunks B, syschunks C, sysdbstab D " .
            "WHERE A.dbsnum = B.dbsnum " .
            "AND A.chknum = B.chknum " .
            "AND C.chknum = 1 " .
            "AND A.dbsnum = D.dbsnum " .
		(empty($dbsnum) ? "" : "AND A.dbsnum = ".$dbsnum )
		;

		$conn = $this->idsadmin->get_database("sysmaster");
		try{
			$stmt = $conn->query($qry,false,true);
		}catch(Exception $e){
			if($e->getCode() == -229)//This is the case when rootdbs is full and ODBC failed to create temp space.
			{
				$this->showMirrors_noTabJoin($dbsnum);
				return;
			}
		}

		$qrycnt = "SELECT count(*) from sysmchktab";

		$tab->display_tab_by_page($this->idsadmin->lang("MirrorTable"),
		array(
                  "1" => $this->idsadmin->lang("ChunkNum"),
                  "2" => $this->idsadmin->lang("SpaceName"),
                  "3" => $this->idsadmin->lang("PageSize"),
                  "8" => $this->idsadmin->lang("Offset"),
                  "9" => $this->idsadmin->lang("Size"),
                  "6" => $this->idsadmin->lang("Status"),
                  "7" => $this->idsadmin->lang("Path"),
		),
		$qry, $qrycnt, 17, "template_gentab_chunk.php");

	} #end showMirrors

	//Special showChunks function that does not use temp space.
	//This showChunks_noTabJoin function will work if rootdbs is low on space.
	//It will be called when the above showChunk fails, returning error that temp space cannot be created.
	//However, the table cannot be sorted by Chunk Status column using this showChunks_noTabJoin function.
	function showChunks_noTabJoin( $dbsnum="" )
	{
		require_once ROOT_PATH."lib/pagination.php";
		$qrycnt = "SELECT count(*) from syschktab";

		if ( isset( $this->idsadmin->in['fullrpt']) )
		{
			require_once ROOT_PATH."/lib/nopagination.php";
			$pag = new nopagination($this->idsadmin,$qrycnt,10);
		}
		else
		{
			require_once ROOT_PATH."/lib/pagination.php";
			$pag = new pagination($this->idsadmin,$qrycnt,10);
		}

		$data = array();

		$defPagesize = $this->idsadmin->phpsession->serverInfo->getDefaultPagesize();

		// If server is a primary server or server not part of high-availability env,
		// use the more complex query to determine if chunks are online/offline/recovering
		// If server is a secondary server, use simplified query
		// because above query will result in -229/ISAM 140 operation illegal on a DR Secondary
		$isPrimary = ($this->idsadmin->phpsession->serverInfo->isPrimary())?true:false;

		// Also avoid using table joins in running this query.
		// Because running a table join might require temp space. If rootdbs is full, there will not
		// be enough space for the temp table. User will end up not being able to add more space to rootdbs
		// idsdb00172223
		if($isPrimary){
			$col_titles = array(
                  "2" => $this->idsadmin->lang("ChunkNum"),
                  "no_order_1" => $this->idsadmin->lang("SpaceName"),
                  "3" => $this->idsadmin->lang("PageSize"),
                  "9" => $this->idsadmin->lang("Offset"),
                  "10" => $this->idsadmin->lang("Size"),
                  "11" => $this->idsadmin->lang("Free"),
                  "12" => $this->idsadmin->lang("Used"),
                  "no_order_2" => $this->idsadmin->lang("Status"),
                  "8" => $this->idsadmin->lang("Path"),
			);
		}else{
			$col_titles = array(
                  "2" => $this->idsadmin->lang("ChunkNum"),
                  "no_order_1" => $this->idsadmin->lang("SpaceName"),
                  "3" => $this->idsadmin->lang("PageSize"),
                  "9" => $this->idsadmin->lang("Offset"),
                  "10" => $this->idsadmin->lang("Size"),
                  "11" => $this->idsadmin->lang("Free"),
                  "12" => $this->idsadmin->lang("Used"),
                  "8" => $this->idsadmin->lang("Path"),
			);
		}
		 
		$sql = 	"SELECT {$pag->skip} {$pag->first} ";
		$sql.= 	"dbsnum,chknum,pagesize/1024 as pgsize, ".
		        "format_units(offset, {$defPagesize}) as off, ".
		        "format_units(chksize, {$defPagesize}) as size, ".
		        "format_units(decode(mdsize,-1,nfree,udfree),{$defPagesize}) as free, ".
		        "TRUNC(100 - decode(mdsize,-1,nfree,udfree)*100/chksize,2 )|| '%' as used, ".
	       		"fname, offset as sortoffset, chksize as sortsize, ".
	       		"decode(mdsize,-1,nfree,udfree) as sortfree, ".
		        "TRUNC(100 - decode(mdsize,-1,nfree,udfree)*100/chksize,2 ) as sortused ".
	       		"FROM syschktab ".
		(empty($dbsnum) ? "" : "WHERE dbsnum = ".$dbsnum );

		if ( isset($this->idsadmin->in['orderway']) )
		{
			$desc="DESC";
		}
		if ( isset($this->idsadmin->in['orderby']) )
		{
			$ordby = " ORDER BY {$this->idsadmin->in['orderby']} {$desc} ";
			$sql .= $ordby;
		}
		$conn = $this->idsadmin->get_database("sysmaster");
		$stmt = $conn->query($sql);
		$res = $stmt->fetchAll();
		foreach($res as $i=>$v)
		{
			$row = array();
			if($isPrimary){
				$sql2 = "SELECT ".
	           			"CASE " .
	           			"WHEN is_offline=1 THEN 'OFFLINE'	 " .
	           			"WHEN is_recovering=1 THEN 'RECOVERING' " .
	           			"ELSE 'ONLINE' " .
	           			"END as status " .
	           			"FROM syschunks WHERE chknum = ".trim($v['CHKNUM'])." ".
	           			"AND dbsnum = ".trim($v['DBSNUM']);
				 
				$conn = $this->idsadmin->get_database("sysmaster");
				$stmt = $conn->query($sql2);
				$res2 = $stmt->fetch();
			}

			$sql3 = "SELECT name AS spacename FROM sysdbstab WHERE dbsnum = ".trim($v['DBSNUM']);
			$conn = $this->idsadmin->get_database("sysmaster");
			$stmt = $conn->query($sql3);
			$res3 = $stmt->fetch();

			$row['CHKNUM'] = $v['CHKNUM'];
			$row['SPACENAME'] = $res3['SPACENAME'];
			$row['PGSIZE'] = $v['PGSIZE'];
			$row['OFF'] = $v['OFF'];
			$row['SIZE'] = $v['SIZE'];
			$row['FREE'] = $v['FREE'];
			$row['USED'] = $v['USED'];
			if($isPrimary){
				$row['STATUS'] = $res2['STATUS'];
			}
			$row['FNAME'] = $v['FNAME'];
			$row['SORTOFFSET'] = $v['SORTOFFSET'];
			$row['SORTSIZE'] = $v['SORTSIZE'];
			$row['SORTFREE'] = $v['SORTFREE'];
			$row['SORTUSED'] = $v['SORTUSED'];

			$data[] = $row;
		}
		 
		$this->idsadmin->html->add_to_output(
		$this->idsadmin->template['template_gentab_chunk']->sysgentab_start_output(
		$this->idsadmin->lang("ChunkTable"),$col_titles,$pag->get_pag())
		);

		foreach($data as $i=>$v){
			$this->idsadmin->html->add_to_output(
			$this->idsadmin->template['template_gentab_chunk']->sysgentab_row_output($v)
			);
		}
		 
		$this->idsadmin->html->add_to_output(
		$this->idsadmin->template['template_gentab_chunk']->sysgentab_end_output($pag->get_pag())
		);
		 
	} #end showChunks_noTabJoin

	//Special showMirrors function that does not use temp space.
	//This showMirrors_noTabJoin function will work if rootdbs is low on space.
	//It will be called when the above showMirrors fails, returning error that temp space cannot be created.
	//However, the table cannot be sorted by Chunk Status column using this showMirrors_noTabJoin function.
	function showMirrors_noTabJoin( $dbsnum="" )
	{
		require_once ROOT_PATH."lib/pagination.php";
		$qrycnt = "SELECT count(*) from sysmchktab";

		if ( isset( $this->idsadmin->in['fullrpt']) )
		{
			require_once ROOT_PATH."/lib/nopagination.php";
			$pag = new nopagination($this->idsadmin,$qrycnt,10);
		}
		else
		{
			require_once ROOT_PATH."/lib/pagination.php";
			$pag = new pagination($this->idsadmin,$qrycnt,10);
		}

		$data = array();
		 
		$col_titles = array(
                  "2" => $this->idsadmin->lang("ChunkNum"),
                  "no_order_1" => $this->idsadmin->lang("SpaceName"),
                  "3" => $this->idsadmin->lang("PageSize"),
                  "7" => $this->idsadmin->lang("Offset"),
                  "8" => $this->idsadmin->lang("Size"),
                  "no_order_2" => $this->idsadmin->lang("Status"),
                  "6" => $this->idsadmin->lang("Path"),
		);
		 
		$sql = "SELECT pagesize from syschunks where chknum = 1";
		$conn = $this->idsadmin->get_database("sysmaster");
		$stmt = $conn->query($sql);
		$res = $stmt->fetch();
		$pgsize = trim($res['PAGESIZE']);
	  
		$sql = 	"SELECT {$pag->skip} {$pag->first} ".
	    		"dbsnum,chknum,pagesize/1024 as pgsize, ".
	    		"format_units(offset*{$pgsize}/1024, 'k') as off, " .
            	"format_units(chksize*{$pgsize}/1024, 'k') as size, " .
    			"fname, ".
            	"offset as sortoffset, ".
            	"chksize as sortsize ".
	    		"FROM sysmchktab ".
		(empty($dbsnum) ? "" : "WHERE dbsnum = ".$dbsnum );
		 
		if ( isset($this->idsadmin->in['orderway']) )
		{
			$desc="DESC";
		}
		if ( isset($this->idsadmin->in['orderby']) )
		{
			$ordby = " ORDER BY {$this->idsadmin->in['orderby']} {$desc} ";
			$sql .= $ordby;
		}
		 
		$conn = $this->idsadmin->get_database("sysmaster");
		$stmt = $conn->query($sql);
		$res = $stmt->fetchAll();
	  
		foreach($res as $i=>$v){
			$row = array();

			$sql2 = "SELECT ".
	        		"CASE " .
	           		"WHEN is_offline=1 THEN 'OFFLINE'	 " .
	           		"WHEN is_recovering=1 THEN 'RECOVERING' " .
	           		"ELSE 'ONLINE' " .
	           		"END as status " .
	           		"FROM syschunks WHERE chknum = ".trim($v['CHKNUM'])." ".
	           		"AND dbsnum = ".trim($v['DBSNUM']);
			 
			$conn = $this->idsadmin->get_database("sysmaster");
			$stmt = $conn->query($sql2);
			$res2 = $stmt->fetch();

			$sql3 = "SELECT name AS spacename FROM sysdbstab WHERE dbsnum = ".trim($v['DBSNUM']);
			$conn = $this->idsadmin->get_database("sysmaster");
			$stmt = $conn->query($sql3);
			$res3 = $stmt->fetch();

			$row['CHKNUM'] = $v['CHKNUM'];
			$row['SPACENAME'] = $res3['SPACENAME'];
			$row['PGSIZE'] = $v['PGSIZE'];
			$row['OFF'] = $v['OFF'];
			$row['SIZE'] = $v['SIZE'];
			$row['STATUS'] = $res2['STATUS'];
			$row['FNAME'] = $v['FNAME'];
			$row['SORTOFFSET'] = $v['SORTOFFSET'];
			$row['SORTSIZE'] = $v['SORTSIZE'];

			$data[] = $row;
		}
	  
		$this->idsadmin->html->add_to_output(
		$this->idsadmin->template['template_gentab_chunk']->sysgentab_start_output(
		$this->idsadmin->lang("MirrorTable"),$col_titles,$pag->get_pag())
		);

		foreach($data as $i=>$v){
			$this->idsadmin->html->add_to_output(
			$this->idsadmin->template['template_gentab_chunk']->sysgentab_row_output($v)
			);
		}
		 
		$this->idsadmin->html->add_to_output(
		$this->idsadmin->template['template_gentab_chunk']->sysgentab_end_output($pag->get_pag())
		);
	} #end showMirrors_noTabJoin

	/**
	 * Display information about Chunk I/O
	 *
	 * @param unknown_type $dbsnum
	 */
	function showChunkIO( $dbsnum="" )
	{
		$db = $this->idsadmin->get_database("sysmaster");
		require_once ROOT_PATH."lib/gentab.php";
		require_once ROOT_PATH."lib/Charts.php";


		$tab = new gentab($this->idsadmin);


		$qry = "SELECT ".
            "chknum, " .
            "B.name as spacename, " .
            "fname, " .
            "reads, " .
            "writes, " .
            " reads + writes  as totalio " .
            "from syschktab A, sysdbstab B " .
            "WHERE A.dbsnum = B.dbsnum " .
		(empty($dbsnum) ? "" : "AND A.dbsnum = ".$dbsnum ) .
            " ORDER BY totalio DESC" 
            ;

            $qrygraph = "SELECT FIRST 20 ".
            "chknum as chunknum, " .
            " reads + writes  as totalio " .
            "from syschktab A " .
            (empty($dbsnum) ? "" : "WHERE dbsnum = ".$dbsnum ) .
            " ORDER BY totalio DESC" 
            ;
            // $num_rows=6;
            $stmt = $db->query($qrygraph);
            while ($res = $stmt->fetch(PDO::FETCH_ASSOC) )
            {
            	$out[ $this->idsadmin->lang("Chunk") . " ".$res['CHUNKNUM'] ] = (int)$res['TOTALIO'];
            }

            $this->idsadmin->html->add_to_output( "<table style='width:100%;height:100%' role='presentation'><tr style='width:100%;height:50%'><td align='center'>" );
            $this->idsadmin->Charts = new Charts($this->idsadmin);
            $this->idsadmin->Charts->setType("PIE");
            $this->idsadmin->Charts->setData($out);
            $this->idsadmin->Charts->setTitle($this->idsadmin->lang("IOGraphTitle"));
            $this->idsadmin->Charts->setDataTitles(array($this->idsadmin->lang("TotalIO"),$this->idsadmin->lang("Chunk")));
            $this->idsadmin->Charts->setLegendDir("vertical");
            $this->idsadmin->Charts->setWidth("100%");
            $this->idsadmin->Charts->setHeight("250");
            $this->idsadmin->Charts->Render();

            //$this->idsadmin->html->add_to_output(
            //$mygraph->pieGraph( $out, "IO By Chunk" ,300,200,true ) );
            $this->idsadmin->html->add_to_output( "</td></tr><tr><td>" );

            $qrycnt = "SELECT count(*) from syschktab";

            $tab->display_tab_by_page($this->idsadmin->lang("IOTableTitle"),
            array(
                  "1" => $this->idsadmin->lang("ChunkNum"),
                  "2" => $this->idsadmin->lang("SpaceName"),
                  "3" => $this->idsadmin->lang("ChunkPath"),
                  "4" => $this->idsadmin->lang("Reads"),
                  "5" => $this->idsadmin->lang("Writes"),
            ),
            $qry, $qrycnt, NULL, "template_gentab_order.php","",7);
            $this->idsadmin->html->add_to_output( "</td></tr></table>" );

	} #end showChunkIO

	/**
	 * Determine if server has mirroring enabled
	 */
	function isMirrored() {
		$conn = $this->idsadmin->get_database("sysmaster");

		$qry = "SELECT ".
            "cf_effective " .
            "FROM sysconfig A " .
            "WHERE cf_name='MIRROR'";
		;

		$stmt = $conn->query($qry);
		while ($res = $stmt->fetch(PDO::FETCH_ASSOC) )
		{
			$this->mirror = $res['CF_EFFECTIVE'];
		}
	} // end isMirrored

} // end class?>
