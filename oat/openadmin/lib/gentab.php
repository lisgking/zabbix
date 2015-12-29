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
 * This class has functions for displaying rows of data
 * returned from a query in a table format or another format
 * specified by a template having the following functions
 * defined -- sysgentab_start_output, sysgentab_row_output, and
 * sysgentab_end_output.
 *
 */
class gentab {

    public $idsadmin;

    /**
     * constructor.
     *
     * @param idsadmin object
     * @return gentab object
     */
    function __construct(&$idsadmin)
    {
        $this->idsadmin = &$idsadmin;
	$this->idsadmin->load_lang("gentab");
    }



    /**
     * display_tab()
     *
     * Displays rows of data returned from a query in
     * a table format specified by the template.
     *
     * The template should have the following functions defined:
     * sysgentab_start_output, sysgentab_row_output, and
     * sysgentab_end_output.
     *
     * hdr -- table title row
     * col_titles -- column headers
     * sql -- sql query to run
     * template -- template filename in templates directory
     * conn -- the database connection
     * num_rows -- number of rows to display per page.
     *
     * If $col_titles is empty/NULL, then the column titles will
     * be the column name in the meta data returned from the query --
     * meta data from PDOStatement::getColumnMeta .
     *
     * @param string $hdr
     * @param string array $col_titles
     * @param string $sql
     * @param string $template
     * @param db $conn
     * @param integer $num_rows
     * @return array
     */
    function display_tab($hdr, $col_titles, $sql,
    $template="template_gentab.php",$conn="",
    $num_rows=5)
    {
        $out = "";
        if (! $conn instanceOf database)
        $conn = $this->idsadmin->get_database("sysmaster");

        if ($template == "")
        $template="template_gentab.php";

        $tname = substr($template,0,strpos( $template, ".php"));
        $this->idsadmin->load_template($tname);
        
        if ( isset($this->idsadmin->in['orderway']) )
        {
            $desc="DESC";
        }

        if ( isset($this->idsadmin->in['orderby']) )
        {
            $ordby = " ORDER BY {$this->idsadmin->in['orderby']} {$desc}";
            $sql = preg_replace("/order[ \t]+by(.*)/i","",$sql);
            $sql .= $ordby;
        }

        $stmt = $conn->query($sql);

        if ( $col_titles == "" )
        {
            $colcount = $stmt->columnCount();
            for ($cnt=0; $cnt < $colcount; $cnt++)
            {
                $meta = $stmt->getColumnMeta($cnt);
                $col_titles[$cnt] = $meta["name"];
            }
        }

        $this->idsadmin->html->add_to_output(
        $this->idsadmin->template[$tname]->sysgentab_start_output($hdr, $col_titles)
        );

        while ($res = $stmt->fetch() )
        {
            $this->idsadmin->html->add_to_output($this->idsadmin->template[$tname]->sysgentab_row_output($res));
            if ( $num_rows-- > 0 )
            $out[] = $res;
        }
        $this->idsadmin->html->add_to_output($this->idsadmin->template[$tname]->sysgentab_end_output());
        $stmt->closeCursor();
        return $out;
    } /* display_tab */

    /**
     * display_tab_max()
     *
     * Displays rows of data returned from a query in
     * a table format specified by the template.
     *
     * The template should have the following functions defined:
     * sysgentab_start_output, sysgentab_row_output, and
     * sysgentab_end_output.
     *
     * hdr -- table title row
     * col_titles -- column headers
     * sql -- sql query to run
     * template -- template filename in templates directory
     * conn -- the database connection
     * max_rows -- maximum number of rows to fetch; -1 means to fetch all.
     *             if 0, the default of 100 is used.
     * If $col_titles is empty/NULL, then the column titles will
     * be the column name in the meta data returned from the query --
     * meta data from PDOStatement::getColumnMeta .
     *
     *
     * The returned array has this info:
     *     array("num_fetched" => num_fetched, "all_fetched" => "yes" or "no")
     *
     * @param string $hdr
     * @param string array $col_titles
     * @param string $sql
     * @param string $template
     * @param db $conn
     * @param integer $max_rows
     * @return array
     */
    function display_tab_max($hdr, $col_titles, $sql,
    $template="template_gentab.php",$conn="",
    $max_rows=-1)
    {
        $need_coltype = 0;
        
        if ($conn == "")
        $conn = $this->idsadmin->get_database("sysmaster");

        if ($template == "")
        $template="template_gentab.php";

        $tname = substr($template,0,strpos( $template, ".php"));
        $this->idsadmin->load_template($tname);
        
        if ( $template == "gentab_disp_sqlwin_sql.php" ||
        $template == "gentab_pag_sqlwin_info.php" ||
        $template == "gentab_order_sqlwin_db_tab.php" )
        {
            $need_coltype = 1;
        }

        if ( $max_rows == 0 )
        $max_rows = 100;

        $stmt = $conn->query($sql);

        if ( $col_titles == "" || $need_coltype == 1 )
        {
            $col_type = array();
            $meta_col_titles = array();
            $colcount = $stmt->columnCount();
            for ($cnt=0; $cnt < $colcount; $cnt++)
            {
                $meta = $stmt->getColumnMeta($cnt);
                $meta_col_titles[$cnt] = $meta["name"];
                $col_type[$cnt] = $meta["native_type"];
            }

            if ( $col_titles == "" )
            $col_titles = $meta_col_titles ;
        }
        
                $this->idsadmin->html->add_to_output(
        $this->idsadmin->template[$tname]->sysgentab_start_output($hdr, $col_titles)
        );

        $row_cnt=0;
        while ($res = $stmt->fetch())
        {
            if ( ($max_rows) > 0 && ($row_cnt == $max_rows) )
            {
                break;
            }
            $row_cnt++;
            if ( $need_coltype == 1 )
            {
                $this->idsadmin->html->add_to_output
                ($this->idsadmin->template[$tname]->sysgentab_row_output($res, $col_type));
            } else {
            	$this->idsadmin->html->add_to_output(
            	$this->idsadmin->template[$tname]->sysgentab_row_output($res));
            }
        }

        if ( $res )
        $all_fetched = "no";
        else
        $all_fetched = "yes";

        $out = array("num_fetched" => $row_cnt,
        "all_fetched" => $all_fetched);

        $this->idsadmin->html->add_to_output($this->idsadmin->template[$tname]->sysgentab_end_output());
        $stmt->closeCursor();
        return $out;
    } /* display_tab_max */


    /**
     * display_graph_tab()
     * Displays rows of data returned from a query in
     * a format specified by the template.
     *
     * The template should have the following functions defined:
     * sysgentab_start_output, sysgentab_row_output, and
     * sysgentab_end_output.
     *
     * hdr -- table title row
     * col_titles -- column headers
     * sql -- sql query to run
     * template -- template filename in templates directory
     * conn -- the database connection
     * num_rows -- number of rows to display.
     *
     * @param string $hdr
     * @param string array $col_titles
     * @param string $sql
     * @param string $template
     * @param db $conn
     * @param integer $num_rows
     * @return array
     *
     */
    function display_graph_tab($hdr, $col_titles, $sql,
    $template="template_gentab.php",$conn="",
    $num_rows=5)
    {
         
        if ($conn == "")
        $conn = $this->idsadmin->get_database("sysmaster");

        $tname = substr($template,0,strpos( $template, ".php"));
        $this->idsadmin->load_template($tname);


        $stmt = $conn->query($sql);

        $this->idsadmin->html->add_to_output(
        $this->idsadmin->template[$tname]->sysgentab_start_output($hdr, $col_titles)
        );

        while ($res = $stmt->fetch() )
        {
            $this->idsadmin->html->add_to_output($this->idsadmin->template[$tname]->sysgentab_row_output($res));
            if ( $num_rows-- > 0 )
            $out[] = $res;
        }
        $this->idsadmin->html->add_to_output($this->idsadmin->template[$tname]->sysgentab_end_output());
        $stmt->closeCursor();
        return $out;
    }

    /**
     * Include "lib/pagination.php".
     *
     * display_tab_pag()
     * Displays rows of data returned from a query in
     * a paginated table format.  Display of data is
     * further customized by a template.
     *
     * The template should have the following functions defined:
     * sysgentab_start_output, sysgentab_row_output, and
     * sysgentab_end_output.
     *
     * hdr -- table title row
     * col_titles -- column headers
     * sql -- sql query to run
     * perpage -- number of rows per page to be displayed
     * template -- template filename in templates directory
     * conn -- the database connection
     * prt_total -- flag indicating whether the total number of
     *              rows returned from the query should be appended
     *              to $hdr.
     *
     * If $col_titles is empty/NULL, then the column titles will
     * be the column name in the meta data returned from the query --
     * meta data from PDOStatement::getColumnMeta .
     *
     * @param string $hdr
     * @param string array $col_titles
     * @param string $sql
     * @param mixed $perpage
     * @param string $template
     * @param db $conn
     * @param integer $prt_total
     *
     */
    function display_tab_pag($hdr, $col_titles, $sql,
    $perpage, $template="template_gentab.php", $conn="", $prt_total=0 )
    {
        $need_coltype=0;

        if ($conn == "" )
        $conn = $this->idsadmin->get_database("sysmaster");

        if ($template == "")
        $template="template_gentab.php";

        $tname = substr($template,0,strpos( $template, ".php"));
        $this->idsadmin->load_template($tname);

        if ( $template == "gentab_disp_sqlwin_sql.php" ||
        $template == "gentab_pag_sqlwin_info.php" ||
        $template == "gentab_order_sqlwin_db_tab.php" )
        {
            $need_coltype = 1;
        }

        require_once ROOT_PATH."/lib/pagination.php";

        $sqlcnt = preg_replace("/(.*)from/i","select count(*) as mycnt from",$sql);
        $sqlcnt = preg_replace("/order[ \t]+by(.*)/i","",$sqlcnt);


        $pag = new pagination($this->idsadmin,$sqlcnt,$perpage,"",$conn);

        if ($prt_total != 0)
        $hdr.= $pag->get_totalrows();

        $sql = preg_replace("/select/i","select {$pag->skip} {$pag->first} ",$sql,1);

        $stmt = $conn->query($sql);


        if ( $col_titles == "" || $need_coltype == 1 )
        {
            $col_type = array();
            $meta_col_titles = array();
            $colcount = $stmt->columnCount();
            for ($cnt=0; $cnt < $colcount; $cnt++)
            {
                $meta = $stmt->getColumnMeta($cnt);
                $meta_col_titles[$cnt] = $meta["name"];
                $col_type[$cnt] = $meta["native_type"];
            }
            #print_r($col_type);

            if ( $col_titles == "" )
            $col_titles = $meta_col_titles ;
        }

        if ( $need_coltype == 1 )
        {
            $this->idsadmin->html->add_to_output(
            $this->idsadmin->template[$tname]->sysgentab_start_output($hdr, $col_titles,
            $pag->get_pag(), "", $col_type) );
        }
        else
        {
            $this->idsadmin->html->add_to_output(
            $this->idsadmin->template[$tname]->sysgentab_start_output($hdr, $col_titles,
            $pag->get_pag(), "") );
        }

        while ($res = $stmt->fetch() )
        {
             
            if ( $need_coltype == 1 )
            {
                $this->idsadmin->html->add_to_output
                ($this->idsadmin->template[$tname]->sysgentab_row_output($res, $col_type));
            }
            else
            $this->idsadmin->html->add_to_output($this->idsadmin->template[$tname]->sysgentab_row_output($res));
        }
        $this->idsadmin->html->add_to_output(
        $this->idsadmin->template[$tname]->sysgentab_end_output("")

        );
        $stmt->closeCursor();

    } #display_tab_pag

    /**
     *
     * display_tab_by_page()
     *
     * If $this->idsadmin->in['fullrpt'] is set,
     * include "lib/nopagination.php";
     * if not set, include "lib/pagination.php".
     *
     * Displays rows of data returned from a query in
     * a paginated or a report table format.  Display of data is
     * further customized by a template.
     *
     * The template should have the following functions defined:
     * sysgentab_start_output, sysgentab_row_output, and
     * sysgentab_end_output.
     *
     * hdr -- table title row
     * col_titles -- column headers
     * sql -- sql query to run
     * sqlcnt -- sql query to determine the number of rows to be returned
     * perpage -- number of rows per page to be displayed
     * template -- template filename in templates directory (optional)
     * conn -- the database connection (optional)
     * num_rows -- limit output to a max number of rows (optional)
     * prt_total -- flag indicating whether the total number of
     *              rows returned from the query should be appended
     *              to $hdr (optional).
     * additional_rows -- additional rows to append to the bottom
     *              of the table beyond what is returned by the query (optional)
     *
     * If $col_titles is empty/NULL, then the column titles will
     * be the column name in the meta data returned from the query --
     * meta data from PDOStatement::getColumnMeta .
     *
     * @param string $hdr
     * @param string array $col_titles
     * @param string $sql
     * @param string $sqlcnt
     * @param mixed $perpage - set to NULL to use the default setting from OAT config file
     * @param string $template
     * @param db $conn
     * @param integer $num_rows
     * @param integer $prt_total
     * @param array $additional_rows
     * @return array
     */
    function display_tab_by_page($hdr, $col_titles, $sql, $sqlcnt,
    $perpage, $template="template_gentab_order.php",
    $conn="", $num_rows=0,$prt_total=0, $additional_rows = null )
    {
        $need_coltype=0;
        $out = "";
        $desc = "";
        
        if (is_null($perpage))
        {
        	$perpage = $this->idsadmin->get_config("ROWSPERPAGE",25);
        } 

        if ( isset( $this->idsadmin->in['fullrpt']) )
        {
            $template = "template_gentab.php";
        }

        if (!$conn instanceOf database)
        {
            $conn = $this->idsadmin->get_database("sysmaster");
        }

        $tname = substr($template,0,strpos( $template, ".php"));
        $this->idsadmin->load_template($tname);

        if ( $template == "gentab_disp_sqlwin_sql.php" ||
        $template == "gentab_pag_sqlwin_info.php" ||
        $template == "gentab_order_sqlwin_db_tab.php" )
        {
            $need_coltype = 1;
        }

        if ( isset($this->idsadmin->in['orderway']) )
        {
            $desc="DESC";
        }


        if ( isset($this->idsadmin->in['orderby']) )
        {
            $ordby = " ORDER BY {$this->idsadmin->in['orderby']} {$desc}";
            $sql = preg_replace("/order[ \t]+by(.*)/i","",$sql);
            $sql .= $ordby;
        }

        if ( isset( $this->idsadmin->in['fullrpt']) )
        {
            require_once ROOT_PATH."/lib/nopagination.php";
            $pag = new nopagination($this->idsadmin,$sqlcnt,$perpage,"",$conn);
        }
        else
        {
            require_once ROOT_PATH."/lib/pagination.php";
            $pag = new pagination($this->idsadmin,$sqlcnt,$perpage,"",$conn);
        }

        if ($prt_total != 0)
        {
            $hdr.= $pag->get_totalrows();
        }

        $sql = preg_replace("/select/i","select {$pag->skip} {$pag->first} ",$sql,1);
  
        $stmt = $conn->query($sql);

        if ( $col_titles == "" || $need_coltype == 1 )
        {
            $col_type = array();
            $meta_col_titles = array();
            $colcount = $stmt->columnCount();
            for ($cnt=0; $cnt < $colcount; $cnt++)
            {
                $pos = $cnt + 1;
                $meta = $stmt->getColumnMeta($cnt);
                $meta_col_titles[$pos] = $meta["name"];
                $col_type[$pos] = $meta["native_type"];
            }
            if ( $col_titles == "" )
            {
                $col_titles = $meta_col_titles;
            }
        }

        /* Need coltype for column titles since you cannot order by
         * by blob columns.   So, need this info if you are planning to
         * enable ordering by columns.
         */
        if ( $need_coltype == 1 )
        {
           
            $this->idsadmin->html->add_to_output(
            $this->idsadmin->template[$tname]->sysgentab_start_output($hdr, $col_titles,
            $pag->get_pag(), "", $col_type) );
        }
        else
        {
            $this->idsadmin->html->add_to_output(
            $this->idsadmin->template[$tname]->sysgentab_start_output($hdr, $col_titles,
            $pag->get_pag(), "") );
        }

        while ($res = $stmt->fetch() )
        {
            if ( $need_coltype == 1 )
            {
                $this->idsadmin->html->add_to_output
                ($this->idsadmin->template[$tname]->sysgentab_row_output($res, $col_type));
            }
            else
            {
                $this->idsadmin->html->add_to_output($this->idsadmin->template[$tname]->sysgentab_row_output($res));
            }

            if ( $num_rows-- > 0 )
            {
                $out[] = $res;
            }
        }
        
        if($additional_rows != null)
        {
	        foreach ($additional_rows as $additional_row)
	        {
	        	$this->idsadmin->html->add_to_output($this->idsadmin->template[$tname]->sysgentab_row_output($additional_row));
	        }
        }
        
        $this->idsadmin->html->add_to_output(
        $this->idsadmin->template[$tname]->sysgentab_end_output("")
        );
        $stmt->closeCursor();
        return $out;
    } #display_tab_by_page

    /**
     * display_tab_exe
     *
     * Executes an sql statement and displays success or error
     * based on return of the PDO::exec call.
     * The display can be further customized by a template.
     *
     * The template should have the following functions defined:
     * sysgentab_start_output, sysgentab_row_output, and
     * sysgentab_end_output.
     *
     * hdr -- table title row
     * col_titles -- column headers
     * sql -- sql statement to execute
     * template -- template filename in templates directory
     * conn -- the database connection
     * Returns the integer returned from the PDO::exec call or
     *  the Informix error code from the PDO::errorInfo array.
     *
     * @param string $hdr
     * @param string array $col_titles
     * @param string $sql
     * @param string $template
     * @param db $conn
     * @return integer
     */
    function display_tab_exe($hdr, $col_titles, $sql,
    $template="template_gentab.php",$conn="")
    {
	$this->idsadmin->load_lang("gentab");
        if (! $conn instanceOf database)
        {
            $conn = $this->idsadmin->get_database("sysmaster");
        }
        
        if ($template == "")
        {
            $template="template_gentab.php";
        }

        $tname = substr($template,0,strpos( $template, ".php"));
        $this->idsadmin->load_template($tname);

	if (preg_match("/(^( *)execute function)/i", $sql))
	{
		// If executing a function, use $conn->query so we get the return value
		$res =  $conn->query($sql);

		// get return value of function
		$rows = $res->fetchAll();
		if (isset ($rows[0]['']))
		{
			$res = $rows[0][''];
		}
	}
	else 
	{
		// Otherwise, use $conn->exec so we get the number of rows affected
        	$res = $conn->exec($sql);
	}

        $this->idsadmin->html->add_to_output(
            $this->idsadmin->template[$tname]->sysgentab_start_output($hdr, $col_titles)
        );

        $err_r = $conn->errorInfo();
        $err = $err_r[1];
        $err_msg = isset($err_r[2])? $err_r[2]:"";

        if ( $err != 0 )
        {
            if ( $err == "-201" )
            {
                $str = array("{$this->idsadmin->lang('Return')} $err  {$this->idsadmin->lang('SyntaxError')}");
            }
            else
            {
                $str = array("{$this->idsadmin->lang('Return')} $err  $err_msg");
            }

            $res = $err;
        }
        else if (preg_match("/(^( *)execute function)/i", $sql))
        {
            $str = array("{$this->idsadmin->lang('FunctionReturnValue')} {$res}");
        }
        else if (!preg_match("/(^( *)create)|(^( *)drop)/i", $sql))
        {
            $str = array("{$this->idsadmin->lang('NumRowsAffected')} $res");
        }
        else
        {
            $str = array("{$this->idsadmin->lang('CompletedSuccess')}");
        }
        $this->idsadmin->html->add_to_output($this->idsadmin->template[$tname]->sysgentab_row_output($str));
        $this->idsadmin->html->add_to_output($this->idsadmin->template[$tname]->sysgentab_end_output());

        return $res;
    }


}
?>
