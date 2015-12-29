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
 * Class used when pagination is required
 *
 */
class pagination {

    private $sql = "";
    private $totalrows ="";
    private $perpage = "";
    private $url = "";
    private $num_to_show = 2;
    public $skip = "";
    public $first = "";


    public $idsadmin;

    function __construct(&$idsadmin,$sql,$perpage,$url="",$conn="")
    {
        $this->idsadmin = &$idsadmin;
        $firstpg = "";
        $lastpg = "";
        $total_pages = "";

        $CURRENT=0;
        if ( ! $conn instanceOf database ) {
            $conn = $this->idsadmin->get_database("sysmaster");
        }

        if (isset($this->idsadmin->in['perpage']))
        $perpage=$this->idsadmin->in['perpage'];

        if ($perpage < 0)
        $perpage=10;

	$this->idsadmin->load_template("template_pagination");
        $this->idsadmin->load_lang("misc_template");
         
        $this->set_sql($sql);
        $this->set_perpage($perpage);
        $this->set_url($url);


        if ( is_int( $this->sql) ) {
            $TOTAL=$this->sql;
        } else {
            $stmt=$conn->query($this->sql);
            $res = $stmt->fetch();
             
            # we dont what the column is called so
            foreach($res as $k => $v)
            $TOTAL=$v;
        }

        if ( $this->perpage == 0 )
        {
            $total_pages = 1;
            $this->set_perpage( $TOTAL );
        }
        if ( $TOTAL > 0 )
        {
            $total_pages = ceil( $TOTAL / $this->perpage );
        }

        if ( !(isset($TOTAL)) || $total_pages <= 0)
        $total_pages = 1;

        $this->set_totalrows($TOTAL);


        if ( isset($this->idsadmin->in['pos']) )
        {
            if ( ($this->idsadmin->in['pos'] < 0 ) )
            $this->idsadmin->in['pos']=0;

            if ( ($this->idsadmin->in['pos']*$this->perpage) >= $TOTAL ) {
                $this->idsadmin->in['pos']=($total_pages-1);
            }

            $this->set_skip("SKIP ".$this->idsadmin->in['pos']*$this->perpage);
            $CURRENT=$this->idsadmin->in['pos'];
        }


        if ($TOTAL != -1 )
        $this->set_first ( "FIRST ".$this->perpage );
        else
        $this->set_first ( "FIRST ".($this->perpage+1) );



        if ($CURRENT < 0) {
            $CURRENT = 0;
        }

        $prev="";
        $next="";
        $pages="";

        if ($CURRENT >= 1)
        {
            $pos  = $CURRENT - 1; # $data['PER_PAGE'];
            $prev  = $this->idsadmin->template["template_pagination"]->pag_prevlink("{$this->url}&amp;pos={$pos}");
            //$firstpg=$this->idsadmin->template["template_pagination"]->pag_first("{$this->url}&amp;pos=0",$total_pages);
        }
         
        if (($CURRENT < ($total_pages -1)) )
        {
            $pos = $CURRENT + 1 ; #+ $data['PER_PAGE'];
            $next = $this->idsadmin->template["template_pagination"]->pag_nextlink("{$this->url}&amp;pos={$pos}");
            //$lastpg=$this->idsadmin->template["template_pagination"]->pag_last("{$this->url}&amp;pos=".($total_pages-1),$total_pages);
        }

        if ($total_pages >= 1)
        {        	
            $pages .= "<td><select onchange='switchPage(this)'>";
            for ($cnt = 0 ; $cnt <= ($total_pages -1); ++$cnt)
            {
                $pgnum  = $cnt+1;
                #$pos    = $cnt*$data['PER_PAGE'];
                $link = ($pgnum == $CURRENT + 1)? "":"{$this->url}&amp;pos={$cnt}";
                $pages .= $this->idsadmin->template["template_pagination"]->pag_addpage($link,$pgnum);
                
                
                /*if ( $pgnum == $CURRENT + 1 ) {
                    $pages .= $this->idsadmin->template["template_pagination"]->pag_addpage("",$pgnum);
                }
                else {
                    if ( $pgnum < ( $CURRENT - $this->num_to_show ) +1   ) {
                        continue;
                    }
                    if ( $pgnum > ( $CURRENT + $this->num_to_show )   ) {
                        $pages .= $this->idsadmin->template["template_pagination"]->pag_addpage("{$this->url}&amp;pos={$cnt}",$pgnum);
                        break;
                    }
                    $pages .= $this->idsadmin->template["template_pagination"]->pag_addpage("{$this->url}&amp;pos={$cnt}",$pgnum);
                }*/

            }
            $pages .= "</select></td>";


            //$info = $this->idsadmin->template["template_pagination"]->pag_info($CURRENT + 1,$total_pages);
        }

        $perpages = array();
        $vals = array(10,25,50,100,500);
        foreach($vals as $k => $v) {
            if ($v != 500 || $TOTAL > $v)
            {
            	$perpages[$v]=$v;
            }
        }
        if ($TOTAL <= 500)
        {
            // Only show 'ALL' if total number of rows is less than 500
            $perpages[$TOTAL]=$this->idsadmin->lang("ALL");
        }

        $ppage = $this->idsadmin->template["template_pagination"]->pag_perpage($TOTAL,$this->perpage,$perpages);
        $this->pag = $this->idsadmin->template["template_pagination"]->pag($info,$firstpg,$next,$pages,$prev,$lastpg,$ppage);

    }

    function get_pag()
    {
        return $this->pag;
    }

    function set_sql($sql)
    {
        $this->sql=$sql;
    }

    function set_perpage($perpage)
    {
        $this->perpage=$perpage;
    }

    function set_totalrows($total=0)
    {
        $this->totalrows=$total;
    }

    function set_url($url)
    {
        $this->url=$this->idsadmin->removefromurl("pos");
        $this->url=htmlentities($this->url);
    }

    function set_skip($skip)
    {
        $this->skip = $skip;
    }

    function set_first($first)
    {
        $this->first = $first;
    }

    function get_totalrows()
    {
        if ($this->totalrows < 0 )
        $this->set_totalrows(0);
        return $this->totalrows;
    }

}
?>
