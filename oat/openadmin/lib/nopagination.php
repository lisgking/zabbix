<?php
/*
 **************************************************************************   
 *  (c) Copyright IBM Corporation. 2007.  All Rights Reserved
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
 * Class used when pagination is not required
 *
 */
class nopagination {

    private $sql = "";
    private $totalrows ="";
    private $perpage = "";
    private $url = "";
    private $num_to_show = 2;
    public $skip = "";
    public $first = "";
    public $template = "";

    function nopagination($idsadmin,$sql,$perpage,$url="",$conn="")
    {
        $this->idsadmin = &$idsadmin;
        $CURRENT=0;
        if ( ! $conn instanceOf database ) {
            $conn = $this->idsadmin->get_database("sysmaster");
        }

        if (isset($this->idsadmin->in['perpage']))
        $perpage=$this->idsadmin->in['perpage']; 

        if ($perpage < 0)
        $perpage=10;

        $this->idsadmin->load_template("template_pagination");
        $this->set_template(new template_pagination);
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
   }

    function get_pag()
    {
        return "";
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
    }

    function set_skip($skip)
    {
       $this->skip = $skip;
    }

    function set_first($first) 
    {
       $this->first = $first;
    }
    function set_template($template)
    {
       $this->template = $template;
    }

    function get_totalrows()
    {
      return "";
    }

}
?>
