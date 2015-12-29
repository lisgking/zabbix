<?php
/*
 **************************************************************************   
 *  (c) Copyright IBM Corporation. 2007, 2011.  All Rights Reserved
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
 * This class is for the popup windows used in the SQL Toolbox.
 *
 */

class sqlwin_pop {

    /**
     * Instance of class sqlwin_pop_info, the current
     * template for this class/function.
     *
     * @var string
     */
   
    public $idsadmin;

    /**
     * This constructor loads the language files and the
     * template.
     *
     * @return sqlwin_pop
     */
    function __construct(&$idsadmin)
    {
        $this->idsadmin = $idsadmin;
        $this->idsadmin->load_lang("sqlwin_pop");
        $this->idsadmin->load_template("sqlwin_pop_info");
    }


    /**
     * This is what index.php will call.  The decision
     * of what to do is based on the value of
     * $this->idsadmin->in['do'].
     *
     */
    function run()
    {
        switch($this->idsadmin->in['do'])
        {
            case 'show_partnum';
            $this->get_partnum_info();
            break;
            default:
                $this->def();
                break;
        }
        
    } # end function run



    /**
     * This function displays partition info from
     * sysmaster:sysptnhdr where partnum = the partnum
     * passed in via $this->idsadmin->in['val'].
     *
     * val must be in hex format, starting with "0x".
     *
     * The database connection used for the query is
     * the global $db (sysmaster connection).
     *
     * $this->pop_template->show_partnum is called to
     * display the output.
     *
     */
    function get_partnum_info()
    {

        $db = $this->idsadmin->get_database("sysmaster");

        $this->idsadmin->html->set_pagetitle ($this->idsadmin->lang('PartTitle'));
        $partnum = $this->idsadmin->in['val'];
        if ($partnum == "")
        {
            $errorstr = $this->idsadmin->lang('errNoPartnum');
            $this->idsadmin->error($errorstr);
            return;
        }
        else if (strncmp($partnum, "0x", 2)  != 0 )
        {
            $errorstr = $this->idsadmin->lang('errNotHex');
            $this->idsadmin->error($errorstr);
            return;
        }

        $sql =  " select partnum || ' ($partnum)' , pagesize, ".
        " nextns, nptotal, npused, npdata, nrows, lockid, ".
        " case when nkeys > 0 THEN ".
        "          'Yes'   ".
        "      else 'No'  ".
        "      end as index_pages  ".
        " from sysptnhdr where partnum = '$partnum' ";

        $stmt = $db->query($sql);
        $res = $stmt->fetch();
        $disp_title = $this->idsadmin->lang('PartInfoFor',array($partnum));

        $hdr = array(
        "1" => $this->idsadmin->lang('Partition'),
        "2" => $this->idsadmin->lang('Pgsize'),
        "3" => $this->idsadmin->lang('NumExtAlloc'),
        "4" => $this->idsadmin->lang('TPgsAlloc'),
        "5" => $this->idsadmin->lang('PagesUsed'),
        "6" => $this->idsadmin->lang('NDataPgs'),
        "7" => $this->idsadmin->lang('NRows'),
        "8" => $this->idsadmin->lang('Lockid'),
        "9" => $this->idsadmin->lang('HasIPgs')
        );

        $this->idsadmin->html->add_to_output(
        $this->idsadmin->template['sqlwin_pop_info']->show_partnum($disp_title, $partnum, $hdr, $res));

        return;
    } #get_partnum_info

     
    /**
     * This function is called from
     * "default:" in the run() function switch.
     *
     * Prints a message saying URL is invalid.
     *
     */
    function def()
    {
         
        $errorstr = $this->idsadmin->lang('InvalidURL_do_param');
        $this->idsadmin->error($errorstr);
    } #end default

} #end class sqlwin_pop


?>
