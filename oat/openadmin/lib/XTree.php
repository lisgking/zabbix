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


require_once ( "Diagram.php" );

/**
 * Renders the Statement interator tree
 *
 */
class XTree
{
    public $idsadmin;
    private $pantherFeaturesSupported = false;
	const MINIMUM_NODE_WIDTH = 25;

    /**
     * Class constructor
     */

    function __construct ( &$idsadmin, $sid = 0, $path )
    {
        $this->idsadmin = $idsadmin;
		$this->idsadmin->load_lang("sqltrace");

		if (  Feature::isAvailable ( Feature::PANTHER, $this->idsadmin->phpsession->serverInfo->getVersion()  )  )
		{
			$this->pantherFeaturesSupported = true;
		}
        $this->initialize ( $sid, $path );
    }

    protected function initialize ( $sid, $path )
    {
        /*
         * Retrieve the iterators associated
         * with the given statement ID
         */

        $iterators = $this->getIterators($sid);
        //$iterators = $this->produceFakeIterators(); //for testing only

        /*
         * Reconstruct the iterator tree in XML
         */

        $xml = $this->generateXML($iterators);

        /*
         * Render the iterator tree
         */

        $diagram = new Diagram ( );

        $diagram->render ( $xml, $path );
    }
	
	private static function printSpaces ($numOfSpaces) 
	{
		$spaces = "";
		for ($i = 0; $i<$numOfSpaces; $i++)
		{
			$spaces .= " ";
		}
		return $spaces;
	}

    protected function generateXML($iterators)
    {
        $xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
        $xml .= "<diagram>";
        $xml .= $this->generateXMLAllIterators($iterators);
        $xml .= "</diagram>";
        return $xml;
    }
	/**
	 * This function uses a BFS algorithm to go through all the iterators and save them in an XML object.
	 * Each iterator will be assigned a level. The level of the root node is zero. 
	 * After running BFS, we run DFS on the tree of nodes to create the XML object. 
	 * @return XML object containing the information of all the iterators
	 * @param object $iterators
	 */
	protected function generateXMLAllIterators(&$iterators)
	{
		
		$iterators_stack = array();
		
		//setup the root node
		reset($iterators);
		$root = &current($iterators);
		if (!$root)
		{
			// $root will be false if there are no iterators for this statement
			return;
		}
		$root->setlevel(0); //the level of the root node is 0
		$iterators_stack[] = &$root;
		
		while (count($iterators_stack) > 0)
		{
			$iterator = array_shift($iterators_stack);
			
			if ($iterator->hasRight())
			{
				$right = $iterators[$iterator->getRight()];
				
				$r = $right->getLevel();
				$i = $iterator->getLevel();
				
				if($right->getLevel() == -1) //if not visited
				{
					$iterators[$iterator->getRight()]->setLevel($iterator->getLevel() + 1);
					$iterators_stack[] = $right;
				}
			}

			if ($iterator->hasLeft())
			{
				$left = $iterators[$iterator->getLeft()];

				if($left->getLevel() == -1) //if not visited
				{
					$iterators[$iterator->getLeft()]->setLevel($iterator->getLevel() + 1);
					$iterators_stack[] = $left;
				}
			}

			if ($iterator->hasSender())
	        {
	            $sender = $iterators[$iterator->getSender()];
				if($sender->getLevel () == -1) //if not visited
				{
					$iterators[$iterator->getSender()]->setLevel($iterator->getLevel() + 1);
					$iterators_stack[] = $sender;
					
					//all the next senders must be at the same level
					while($sender->hasNextSender())
		            {
		                $sender = $iterators[$sender->getNextSender()];
						$iterators[$sender->getID()]->setLevel($iterator->getLevel() + 1);
		                $iterators_stack[] = $sender;
		            }
				}
	        }
		}
		
		return $this->generateXMLForIterator($iterators, $root);
	}

	/**
	 * Generate the XML object containing the information of all the nodes. 
	 * This function goes through the iterators using the DFS algorithm.
	 * Every child node must be have a higher level in the tree than its parent. 
	 * If a child iterator appears to be at lower level in the tree, 
	 * then this child iterator is a duplicate of the actual iterator at lower level in the tree. 
	 * The latter duplicate iterator does not need all its information to be retrieved, so only 
	 * its basic attributes will be taken. This iterator will be ignored by the Diagram and its parent
	 * will point to the actual iterator that it was duplicating.
	 * @return 
	 * @param object $iterator
	 * @param object $iterators
	 */
    protected function generateXMLForIterator($iterators, $iterator, $pushdown = false)
    {
        /*
         * Generate a node tag for this iterator
         */
        $xml = "<node "
        . "title=\""
        . $iterator->getID ( )
        . "."
        . trim ( $iterator->getInfo ( ) )
        . "\" "
		. "level=\"" 
		. $iterator->getLevel ( ) 
		. "\" "
		. (($pushdown) ? "empty=\"1\" " : "empty=\"0\" ")
        . ">" ;
		
        /*
         * Generate node data for this iterator
         */
		
		/* If this node is a push down node, then it exists at a higher level in the tree. thus, we
		 * should not draw it or query for any information about it, but we still need to get it's basic information
		 */
		if (!$pushdown)
		{
	        //get table info
			$tableInfo = $iterator->getTableName (); 
			
			list($table, $index) = preg_split("/ /", $tableInfo);
			
			//Calculate the minimum width of the node (which is the max entries width). It should be at least 25
			$tableLineLen = strlen($this->idsadmin->lang('Table') . $table);
			$indexLineLen = strlen($this->idsadmin->lang('Index') . $index);
			$costLineLen  = strlen($this->idsadmin->lang('Cost') . $iterator->getCost ());
			$rowsEstimatedLineLen = strlen($this->idsadmin->lang('RowsEstimated') . $iterator->getRowsEstimated ());
			$rowsProcessedLineLen = strlen($this->idsadmin->lang('RowsProcessed') . $iterator->getRowsProcessed ());
			$elapsedTimeLineLen = strlen($this->idsadmin->lang('ElapsedTime'). sprintf("%.4f", $iterator->getTime ()));
			
			$maxEntriesWidth = max(
					$tableLineLen, 
					$indexLineLen,
					$costLineLen,
					$rowsEstimatedLineLen,
					$rowsProcessedLineLen,
					$elapsedTimeLineLen,
					self::MINIMUM_NODE_WIDTH);
					
			$maxEntriesWidth += 1; //add a space so the entries and values can never collide
			
			if ( $table != null && $table != "")
			{
				$spaceFormat = $maxEntriesWidth - $tableLineLen;
				$xml .= $this->idsadmin->lang('Table') . XTree::printSpaces($spaceFormat) . $table . "\n";
			}
			
			if ( $index != null && $index != "")
			{
				$spaceFormat = $maxEntriesWidth - $indexLineLen;
				$xml .= $this->idsadmin->lang('Index') . XTree::printSpaces($spaceFormat) . $index . "\n";
			}	
			
			$spaceFormat = $maxEntriesWidth - $costLineLen;
	        $xml .= $this->idsadmin->lang('Cost') . XTree::printSpaces($spaceFormat) . $iterator->getCost () . "\n";	
			$spaceFormat = $maxEntriesWidth - $rowsEstimatedLineLen;	
			$xml .= $this->idsadmin->lang('RowsEstimated'). XTree::printSpaces($spaceFormat) . $iterator->getRowsEstimated () . "\n" ;
			$spaceFormat = $maxEntriesWidth - $rowsProcessedLineLen;	
			$xml .= $this->idsadmin->lang('RowsProcessed') . XTree::printSpaces($spaceFormat) . $iterator->getRowsProcessed () . "\n";
			$spaceFormat = $maxEntriesWidth - $elapsedTimeLineLen;
			$xml .= $this->idsadmin->lang('ElapsedTime') . XTree::printSpaces($spaceFormat) . sprintf("%.4f", $iterator->getTime ()) . "\n";
	
	        if ($iterator->hasLeft())
	        {
	            $left = $iterators[$iterator->getLeft()];
				$pushdown = $left->getLevel() < $iterator->getLevel();
	            $xml .= $this->generateXMLForIterator($iterators, $left, $pushdown);
	        }
			
	        if ($iterator->hasRight())
	        {
	            $right = $iterators[$iterator->getRight()];
				$pushdown = $right->getLevel() < $iterator->getLevel();
	            $xml .= $this->generateXMLForIterator($iterators, $right, $pushdown);
	        }
			
			/*NB: there's no need to test if PANTHER features are supported because if they aren't the values of senders and
			      next senders will be 0. 
			*/
	        if ($iterator->hasSender())
	        {
	            $sender = $iterators[$iterator->getSender()];
				$pushdown = $sender->getLevel() < $iterator->getLevel();
	            $xml .= $this->generateXMLForIterator($iterators, $sender, $pushdown);
	            
				while($sender->hasNextSender())
	            {
	                $sender = $iterators[$sender->getNextSender()];
					$pushdown = $sender->getLevel() < $iterator->getLevel();
	                $xml .= $this->generateXMLForIterator($iterators, $sender, $pushdown);
	            }	
	        }
		}

        $xml .= "</node>";
        return $xml;
    }

    protected function getIterators($sid)
    {
        $iter_table = "sysmaster:syssqltrace_iter";
        if ( $this->idsadmin->in['act'] == 'sqltrace' || $this->idsadmin->in['act'] == 'onstat' )
        {
            $mode = isset( $this->idsadmin->in['mode'] ) ? $this->idsadmin->in['mode'] : 1;
            if ( $mode == 2 )
            {
                $iter_table = "sysadmin:mon_syssqltrace_iter";
            }
        }
        $db = $this->idsadmin->get_database("sysmaster");

        $query = " SELECT ";
        require_once ROOT_PATH."lib/feature.php";
        if (  ! Feature::isAvailable(Feature::PANTHER_UC2, $this->idsadmin->phpsession->serverInfo->getVersion()))
        {
        	// For server versions below 11.70.xC2, need to work around server defect idsdb00218057
        	// by forcing the query not to use the index.
        	$query .= "{+ FULL( syssqltrace_iter )}  ";
        }
        $query .= " sql_id            ,       					"
        . " sql_itr_id        ,       					"
        . " sql_itr_left      ,       					"
        . " sql_itr_right     ,       					";
		
		if ($this->pantherFeaturesSupported)
		{
			$query 
			.= " sql_itr_sender    ,  "
        	.  " sql_itr_nxtsender ,  ";
		}
		else
		{
			$query 
			.= " 0 AS sql_itr_sender    ,  "
        	.  " 0 AS sql_itr_nxtsender ,  ";
		}
        
        $query 
		.=" sql_itr_cost      ,       "
        . " sql_itr_estrows   ,       "
        . " sql_itr_numrows   ,       "
        . " sql_itr_type      ,       "
        . " sql_itr_misc      ,       "
        . " sql_itr_info      ,       ";

        if (  Feature::isAvailable ( Feature::CHEETAH2, $this->idsadmin->phpsession->serverInfo->getVersion()  )  )
        {
            $query .= "sql_itr_time ,           " .
        	   			 "sql_itr_partnum          " ;

        } else {
        	   $query .= "0 AS sql_itr_time ,      " .
        	   		 	 "0 AS sql_itr_partnum     " ;
        }
         
        $query .= " FROM  {$iter_table} " .
              	  " WHERE sql_id =         " . $sid .
        		  " ORDER BY sql_itr_id    ";

        $statement = $db->query($query);

        $iterators = array();
        while ($result = $statement->fetch())
        {

            $iterators[$result['SQL_ITR_ID']] =
            new XTreeIterator($this->idsadmin,
            $result['SQL_ID'             ],
            $result['SQL_ITR_ID'         ],
            $result['SQL_ITR_LEFT'       ],
            $result['SQL_ITR_RIGHT'      ],
            $result['SQL_ITR_SENDER'     ],
            $result['SQL_ITR_NXTSENDER'  ],
            $result['SQL_ITR_COST'       ],
            $result['SQL_ITR_ESTROWS'    ],
            $result['SQL_ITR_NUMROWS'    ],
            $result['SQL_ITR_TYPE'       ],
            $result['SQL_ITR_MISC'       ],
            $result['SQL_ITR_INFO'       ],
            $result['SQL_ITR_TIME'       ],
            $result['SQL_ITR_PARTNUM'    ]);
        }

        return $iterators;
    }
	
	/**
	 * This function produces fake iterators so we can test the query tree.
	 * Every element in the array table[] represents an iterator.
	 * $table[][0] = left node
	 * $table[][1] = right node
	 * $table[][2] = sender node //PANTHER ONLY!!
	 * $table[][3] = next sender node ////PANTHER ONLY!!
	 * @return 
	 */
	private function produceFakeIterators() //for testing only!
	{
		$itrs = array();
		$table = array();
		$table[1] = array(0, 2, 3, 0);//1
		$table[2] = array(0, 0, 0, 0);//2
		$table[3] = array(4, 5, 8, 6);//3
		$table[4] = array(0, 0, 0, 0);//4
		$table[5] = array(0, 0, 0, 0);//5
		$table[6] = array(0, 0, 0, 7);//6
		$table[7] = array(0, 0, 0, 0);//7
		$table[8] = array(0, 0, 0, 9);//8
		$table[9] = array(0, 0, 0, 0);//9
		for($i = 1; $i <= count($table); $i++)
        {
            $itrs[$i] =
            new XTreeIterator($this->idsadmin,
            123,
            $i,
            $table[$i][0],
            $table[$i][1],
			$table[$i][2],
            $table[$i][3],
            5,
            40000,
            50000,
            1,
            12344,
            "Seq Scan",
            0.0003,
            123123);
        }
        return $itrs;
     }
     
} # End of class XTree

/**
 * Keeps track of each iterator in the tree
 *
 */
class XTreeIterator
{
    protected $idsadmin;
    protected $sid;
    protected $id;
    protected $left;
    protected $right;
    protected $sender;
    protected $nxtsender;
    protected $cost;
    protected $estrows;
    protected $numrows;
    protected $type;
    protected $misc;
    protected $info;
    protected $time;
    protected $partnum;
	protected $level;

    const IT_NOP      =  0; // exists only for tutorial purposes!
    const IT_SCAN     =  1; // sequential or indexed table/file scan
    const IT_CLSCAN   =  2; // scan of a collection variable
    const IT_ITERSCAN =  3; // scan of a iterator udr
    const IT_SORT     =  4; // produce sorted output from random input
    const IT_RDUPS    =  5; // remove duplicates
    const IT_MERGE    =  6; // two-way (for now) merge iterator
    const IT_JOIN     =  7; // Nested Loop Join (incl. index and cart. prod.)
    const IT_MJOIN    =  8; // sort/merge join
    const IT_HJOIN    =  9; // dynamic hash join
    const IT_GROUP    = 10; // provides group-by and aggrgation
    const IT_XCHG     = 11; // data exchange
    const IT_REMOTE   = 12; // Remote [Scan] iterator
    const IT_STREAM   = 13; // Stream iterator
    const IT_SOW      = 14; // Set Oriented Write iterator
    const IT_ULSTRM   = 15; // Unload stream iterator
    const IT_INSERT   = 16; // Insert iterator
    const IT_CLINSERT = 17; // insert in a collection variable
    const IT_LAST     = 18; // one too many!

    const SEQ_IDX_SCAN_ITERATORS = 1; //the Seq Scan and Index Scan iterators types are designated by the number 1 (yes both of them)

    function __construct($idsadmin,
    $sid         = 0,
    $id          = 0,
    $left        = 0,
    $right       = 0,
    $sender      = 0,
    $nxtsender   = 0,
    $cost        = 0,
    $estrows     = 0,
    $numrows     = 0,
    $type        = 0,
    $misc        = 0,
    $info        = "",
    $time        = 0,
    $partnum     = 0 )
    {
        $this->idsadmin  = $idsadmin;
        $this->sid       = $sid;
        $this->id        = $id;
        $this->left      = $left;
        $this->right     = $right;
        $this->sender    = $sender;
        $this->nxtsender = $nxtsender;
        $this->cost      = $cost;
        $this->estrows   = $estrows;
        $this->numrows   = $numrows;
        $this->type      = $type;
        $this->misc      = $misc;
        $this->info      = $info;
        $this->time      = $time;
        $this->partnum   = $partnum;
		$this->level	 = -1;
    }

    public function getCost         () { return $this->cost     ; }
    public function getInfo         () { return $this->info     ; }
    public function getID           () { return $this->id       ; }
    public function getLeft         () 
	{ 
		return $this->left     ; 
	}
    public function getMiscellaneous() { return $this->misc     ; }
    public function getRight        () { return $this->right    ; }
	/*NB: there's no need to test if PANTHER features are supported because if they aren't the values of senders and
			      next senders will be 0. 
	*/
    public function getSender       () { return $this->sender   ; }
    public function getNextSender   () { return $this->nxtsender; }
    public function getRowsEstimated() { return $this->estrows  ; }
    public function getRowsProcessed() { return $this->numrows  ; }
    public function getStatementID  () { return $this->sid      ; }
    public function getType         () { return $this->type     ; }
    public function getTime         () { return $this->time     ; }
    public function getPartnum      () { return $this->partnum  ; }
	
    /**
	 * Get the table name (with the index name in case the iterator is an Index Scan iterator). If no information could be found return the iterator number
	 * @return 
	 */
	public function getTableName    () 
	{
		$tabInfo = "";
		
        if ($this->getPartnum() == 0)
		{
        	$tabInfo = null;
		}
		//if the partnum is less then 0x00100000 (i.e. 1048576), then the table is a pseudo table
		else if($this->getPartnum() < 1048576)
		{
			//For pseudo tables, we display the partnum only (i.e. no table name or index name)
			$tabInfo = sprintf("0x%08X",$this->getPartnum());
		}
		else
		{
	        $db = $this->idsadmin->get_database("sysmaster");
	        
			$stmt = "";
			//If the server is 11.50.XC6 or newer and the iterator is Seq Scan or Index Scan we need a special logic 
			//I noticed that Index Scans and Seq Scan map their partnum to either indexes or tables. The logic below considers both options. 
			if (  Feature::isAvailable ( Feature::CHEETAH2_UC6, $this->idsadmin->phpsession->serverInfo->getVersion()) && $this->type == self::SEQ_IDX_SCAN_ITERATORS)
	        {
	        	//First get the name of the table/index that the partnum of the iterator belongs to
				$stmt = $db->query(	"SELECT lower(tabname) as name, lower(trim(dbsname)) as database " .
	        					" FROM systabnames WHERE partnum = " . $this->getPartnum()	);
				
				$indexOrTableName = "";
				//Try to find the table name that the index that we *probably* just found belongs to
				
			    if ($res = $stmt->fetch())
	        	{
	        		$db = $this->idsadmin->get_database($res['DATABASE']);
	        		$indexOrTableName = $res['NAME'];
	        		$stmt = $db->query(	"SELECT lower(t.tabname) as tabname from sysindices i, systables t " .
	        					" WHERE i.tabid = t.tabid AND i.idxname = '{$res['NAME']}' ");
				}
				
				//If we found a table that the probable index belongs to, then we know we have the info of the table and the index 
				if ($res = $stmt->fetch())
	        	{
	            	$tabInfo = $res[ 'TABNAME' ] . " " . trim($indexOrTableName);
	        	}
				//If no table name was found then we know that the probable index was NOT an index but a table
				else
				{
					$tabInfo = $indexOrTableName;
				}
	        } 
			else 
			{
				$stmt = $db->query(	"SELECT lower(trim(tabname)) as tabname " .
	        					" FROM systabnames WHERE partnum = " . $this->getPartnum()	);
				if ($res = $stmt->fetch())
	        	{
	            	$tabInfo = $res[ 'TABNAME' ];
	        	}
	        }
			//if we couldn't find the table or the index, we display the partnum
			$tabInfo = ($tabInfo == "") ? sprintf("0x%08X",$this->getPartnum()) : $tabInfo;
		}
        
        return $tabInfo;
    }

    public function hasLeft      ()     
	{ 
		return(self::isValidID($this->left     )); 
	}
    public function hasRight     ()     { return(self::isValidID($this->right    )); }
	/*NB: there's no need to test if PANTHER features are supported because if they aren't the values of senders and
			      next senders will be 0. 
	*/
    public function hasSender    ()     { return(self::isValidID($this->sender   )); }
    public function hasNextSender()     { return(self::isValidID($this->nxtsender)); }
	
	public function setLevel		($lev) { $this->level = $lev ; }
	public function getLevel		() { return $this->level	 ; }

    public static function isValidID($id) { return ($id > 0); }
} // End of class XTreeIterator
?>
