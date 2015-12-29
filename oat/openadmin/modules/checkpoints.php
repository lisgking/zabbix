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
 * The home page for IDSAdmin
 *
 */
class checkpoints {

    public $idsadmin;

    # the 'constructor' function
    # called when the class "new'd"

    function __construct(&$idsadmin)
    {
        $this->idsadmin = &$idsadmin;
        $this->idsadmin->load_template("template_checkpoints");
        $this->idsadmin->load_lang("checkpoints");
        $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('checkpointInfo'));
    }


    /*
     * the run function
     * this is what index.php will call
     * the decision of what to actually do is based on
     * the value of 'act' which is either posted or getted
     */

    function run()
    {
        switch($this->idsadmin->in['do'])
        {
            default:
                $this->idsadmin->setCurrMenuItem("RecoveryLogs");
                $this->def();
                break;
        }
    } # end function run

     

    function def()
    {
        require_once ROOT_PATH."lib/gentab.php";
        $tab = new gentab($this->idsadmin);
        $this->idsadmin->html->add_to_output($this->idsadmin->template['template_checkpoints']->header());

        $qry = "SELECT intvl "
              ." ,type"
              ." ,ckpt_logid||':'||trim(hex(ckpt_logpos)) as lsn "
              ." ,caller"
              ." ,DBINFO ('utc_to_datetime', clock_time) AS chkp_time "
              ." ,TRUNC(block_time,1) AS block_time "
              ." ,TRUNC(crit_time,1) AS crit_time "
              ." ,TRUNC(flush_time,1) AS flush_time "
              ." ,TRUNC(cp_time,1) AS cp_time    "
              ." ,n_dirty_buffs "
              ." ,n_crit_waits "
              ." FROM syscheckpoint order by intvl desc";

        $qrycnt="SELECT count(*) as cnt FROM syscheckpoint WHERE 1=1";


        $tab->display_tab_by_page($this->idsadmin->lang("Checkpoints"),
        array(
        "1" => $this->idsadmin->lang("Interval"),
        "2" => $this->idsadmin->lang("Type"),
        "3" => $this->idsadmin->lang("LSN"),
        "4" => $this->idsadmin->lang("Caller"),
        "5" => $this->idsadmin->lang("Time"),
        "6" => $this->idsadmin->lang("BlockTime"),
        "7" => $this->idsadmin->lang("CritTime"),
        "8" => $this->idsadmin->lang("FlushTime"),
        "9" => $this->idsadmin->lang("CPTime"),
        "10" => $this->idsadmin->lang("NDirtyBuffers"),
        "11" => $this->idsadmin->lang("NCritWaits"),
   
         
        ),
        $qry,$qrycnt,NULL,"template_gentab_order.php");

        $this->idsadmin->html->add_to_output($this->idsadmin->template['template_checkpoints']->footer());



    } // end def

} // end class checkpoints
?>
