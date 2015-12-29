<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2008, 2012.  All Rights Reserved
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
 * This is an example 'module' which shows example code of how to generate 
 * tables and graphs in OAT.
 */

class example {

    /**
     * Each module should have an 'idsadmin' member.  This gives access to the OAT API.
     */
    var $idsadmin;

    /**
     * Every class needs to have a constructor method that takes &$idsadmin as its argument.
     * We are also going to load our 'language' file too.
     * 
     * @param Class $idsadmin
     */
    function __construct(&$idsadmin)
    {
        $this->idsadmin = $idsadmin;

        // Load our language file.
        $this->idsadmin->load_lang("example");
    } // end of function __construct

    /**
     * Every class needs a 'run' method.  This is the 'entry' point of your module.
     */
    function run()
    {
    	// Find out what the user wanted to do.
	    $do = $this->idsadmin->in['do'];

	    // Set the page title - this is the title that is shown in the browser window.
        $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('ibm_example_plugin'));
	    
	    // Set the current menu item
	    $this->idsadmin->setCurrMenuItem("ibmexample");
	    
	    // Show the page header that allows the user to choose which example they want to see.
    	$this->showExamplePageHeader($do);
	    
	    // Map our 'do' action to a function
	    switch ($do)
	    {
	    	case "piechart":
	    		$this->doPieChartExample();
	    		break;
	    	case "linechart":
	    		$this->doLineChartExample();
	    		break;
	    	case "barchart":
	    		$this->doBarChartExample();
	    		break;
	    	case "table":
	    	case "demo":
	    	default:
	    		$this->doTableExample();
	    }
		
    } // end of function run
    
    /**
     * Add a drop-down to the top of the page that allows the user to select which
     * example demo that would like to see.
     */
    function showExamplePageHeader ($selected_option) 
    {
    	// Figure out which option should be selected.
    	$options = array('table' => "", 'piechart' => "");
    	$options[$selected_option] = "selected";
    	    	
    	// Create the HTML for a form that includes one select (drop-down) box.
    	$HTML =<<< EOF
<div width="100%" align="center">
<form name="exampleForm" method="post" action="index.php?act=ibm/example/example">
<strong>{$this->idsadmin->lang('example')}</strong>
<select name="do" onchange="exampleForm.submit()">
	<option value="table" {$options['table']}>{$this->idsadmin->lang('table')}</option>
	<option value="piechart" {$options['piechart']}>{$this->idsadmin->lang('piechart')}</option>
	<option value="linechart" {$options['linechart']}>{$this->idsadmin->lang('linechart')}</option>
	<option value="barchart" {$options['barchart']}>{$this->idsadmin->lang('barchart')}</option>
</select>
</form>
</div>
EOF;

    	// Add this HTML to the idsadmin object's html property
    	$this->idsadmin->html->add_to_output($HTML);
    }

    /**
	 * Table Example.
	 * 
	 * This example function generates a table showing usernames and the number 
	 * of sequential scans by that user.
	 */
    function doTableExample()
    {
        // Let's select some information from the sysmaster:sysuserthreads table.
        // How about we display a list of threads and the number of seqscans run by each thread.

        // We first need a 'connection' to the database.
        $db = $this->idsadmin->get_database("sysmaster");

        // Now we write our query
        $qry = " SELECT us_name AS user_name , us_seqscans AS sequential_scans from sysuserthreads ";

        // We need another query which would be the 'count' of the # of rows returned from the 
        // previous query.
        $qrycnt = " SELECT count(*) as count FROM sysuserthreads ";
        
        // We can use the 'gentab' api in OAT to create the output for us.
        // First, we load the gentab class.
        require_once("lib/gentab.php");

        // Create a new instance of the gentab class
        $tab = new gentab($this->idsadmin);

        /**
         * Call the display_tab_by_page function of the gentab class and pass the required arguments.
         *   arg1:  Title of the table.
         *   arg2:  Array of 'column' headings.
         *          We use the idsadmin lang function to get our strings to use as the headings.
         *   arg3:  The query.
         *   arg4:  The count query.
         *   arg5:  How many rows to display per page.
         */
        $tab->display_tab_by_page($this->idsadmin->lang("users_and_seqscans"),
        array(
                  "1" => $this->idsadmin->lang("username"),
                  "2" => $this->idsadmin->lang("numseqscans"),
        ),
        $qry,$qrycnt,10);

        /**
         * We are done.
         * Test this in your browser using the url
         *      http://HOSTNAME/OATINSTALL/index.php?act=/ibm/example/example
         */
    
    } // end function doTableExample
    
    /**
     * Pie Chart Example
     * 
     * This example function shows how to generate a pie chart of
     * the total IO per chunk.
     **/
    function doPieChartExample() 
    {
        // We'll get the chunk IO information from the sysmaster:syschktab.
        
        // We first need a 'connection' to the database.
        $db = $this->idsadmin->get_database("sysmaster");

        // Now we write our query
        $qry = "SELECT FIRST 20 chknum AS chunknum, reads + writes AS totalio FROM syschktab ORDER BY totalio DESC";
        
        // Now run the query
        $stmt = $db->query($qry);
        
        // And iterate over the result to put the data in array form
        $data = array();
        while ($res = $stmt->fetch(PDO::FETCH_ASSOC))
        {
        	$label = $this->idsadmin->lang("Chunk_num", array($res['CHUNKNUM']));
        	$data[$label] = (int)$res['TOTALIO'];
        }

        // We'll use the 'Charts' api in OAT to create the graph for us.
        // First, we load the Charts class.
        require_once("lib/Charts.php");

        // Create a new instance of the Charts class
        $this->idsadmin->Charts = new Charts($this->idsadmin);
        
        // Now set all of the properties of our chart
        $this->idsadmin->Charts->setType("PIE");
        $this->idsadmin->Charts->setData($data);
        $this->idsadmin->Charts->setTitle($this->idsadmin->lang("Pie_Chart_Title"));
        $this->idsadmin->Charts->setDataTitles(array($this->idsadmin->lang("TotalIO"),$this->idsadmin->lang("Chunk")));
        $this->idsadmin->Charts->setLegendDir("vertical");
        $this->idsadmin->Charts->setWidth("100%");
        $this->idsadmin->Charts->setHeight("400");
        
        // Lastly, call the render function to draw the chart
        $this->idsadmin->Charts->Render();
    	
        /** 
         * Alternatively, you can send the query directly to the Charts library.
         * Uncomment the follow section to graph the same data by sending the query instead of the data itself.
         */
        /*
        // Write the query for the data to graph.
        // Note the query must follow the format used below.  It must have a column labeled series1 to be graphed.
        $qry = "SELECT FIRST 20 reads + writes AS series1, 'Chunk '||chknum AS series1_label FROM syschktab ORDER BY series1 DESC";

        // We'll use the 'Charts' api in OAT to create the graph for us.
        // First, we load the Charts class.
        require_once("lib/Charts.php");

        // Create a new instance of the Charts class
        $this->idsadmin->Charts = new Charts($this->idsadmin);
        
        // Now set all of the properties of our chart, 
        // this time using the Select anbd Dbname properties, instead of sending a data array. 
        $this->idsadmin->Charts->setType("PIE");
        $this->idsadmin->Charts->setSelect(urlencode($qry));
        $this->idsadmin->Charts->setDbname("sysmaster");
        $this->idsadmin->Charts->setTitle($this->idsadmin->lang("Pie_Chart_Title"));
        $this->idsadmin->Charts->setDataTitles(array($this->idsadmin->lang("TotalIO"),$this->idsadmin->lang("Chunk")));
        $this->idsadmin->Charts->setLegendDir("vertical");
        $this->idsadmin->Charts->setWidth("100%");
        $this->idsadmin->Charts->setHeight("400");
        
        // Lastly, call the render function to draw the chart
        $this->idsadmin->Charts->Render();
        */
        
    }  // end function doPieChartExample
    
    /**
     * Line Chart example
     * 
     * Graph the # dirty buffers and # waits for the checkpoints over time.
     */
    function doLineChartExample()
    {
        // Write the query for the data to graph.
        // For line charts, the query must have a 'category' column which represents the date/time column.
        // Then there must be one or more seriesX column, number sequentially (e.g. series1, series2, series3).
        // Optionally, add seriesX_label column to add a column label.
        $qry = "SELECT DBINFO ('utc_to_datetime', clock_time) AS category, "
        	 . "n_dirty_buffs AS series1, '# Dirty Buffers' as series1_label, "
    		 . "n_crit_waits AS series2, '# Waits' as series2_label "
             . "FROM syscheckpoint ORDER BY intvl DESC";
       
        // We'll use the 'Charts' api in OAT to create the graph for us.
        // First, we load the Charts class.
        require_once("lib/Charts.php");

        // Create a new instance of the Charts class
        $this->idsadmin->Charts = new Charts($this->idsadmin);
        
        // Now set all of the properties of our chart. 
        $this->idsadmin->Charts->setType("LINE");
        $this->idsadmin->Charts->setSelect(urlencode($qry));
        $this->idsadmin->Charts->setDbname("sysmaster");
        $this->idsadmin->Charts->setTitle($this->idsadmin->lang("Line_Chart_Title"));
        $this->idsadmin->Charts->setShowZoom(true);
        $this->idsadmin->Charts->setLegendDir("vertical");
        $this->idsadmin->Charts->setWidth("100%");
        $this->idsadmin->Charts->setHeight("400");

        // Lastly, call the render function to draw the chart
        $this->idsadmin->Charts->Render();
    }
    
	/**
     * Bar Chart example
     * 
     * Graph the used and free space for all of the dbspaces on the database server.
     */
    function doBarChartExample()
    {
    	// Here's our query for used and free space in each dbspace.
    	$defaultPageSize = $this->idsadmin->phpsession->serverInfo->getDefaultPagesize();
    	$qry = "select trim(name) as category "
        .",sum((chksize - decode(mdsize,-1,nfree,udfree)) * {$defaultPageSize}) as series1 "
        .",'Used' as series1_label "
        .",sum(decode(mdsize,-1,nfree,udfree) * {$defaultPageSize}) as series2 "
        .",'Free' as series2_label "
        ."from syschunks , sysdbspaces "
        ."where syschunks.dbsnum = sysdbspaces.dbsnum "
        ."group by 1 "
        ."order by 1 asc" ;
        
        // We'll use the 'Charts' api in OAT to create the graph for us.
        // First, we load the Charts class.
        require_once("lib/Charts.php");

        // Create a new instance of the Charts class
        $this->idsadmin->Charts = new Charts($this->idsadmin);
        
        // Now set all of the properties of our chart. 
        $this->idsadmin->Charts->setType("BAR");
        $this->idsadmin->Charts->setBarType('clustered');  // Bar type can be 'clustered', 'stacked', 'overlaid' or '100%'.
        $this->idsadmin->Charts->setSelect(urlencode($qry));
        $this->idsadmin->Charts->setDbname("sysmaster");
        $this->idsadmin->Charts->setTitle($this->idsadmin->lang("Bar_Chart_Title"));
        $this->idsadmin->Charts->setLegendDir("vertical");
        $this->idsadmin->Charts->setWidth("100%");
        $this->idsadmin->Charts->setHeight("400");
        
        // Lastly, call the render function to draw the chart
        $this->idsadmin->Charts->Render();
    }
    
} // end of class example
?>
