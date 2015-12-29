<?php
/*********************************************************************
 *  Licensed Materials - Property of IBM
 *
 *  "Restricted Materials of IBM"
 *
 *  OpenAdmin Tool for IDS
 *  Copyright IBM Corporation 2009.  All rights reserved.
 **********************************************************************/

/**
 * The module for DBAccess Schema Management In OAT
 *
 */
class schemamgr {

    public $idsadmin;

    # the 'constructor' function
    # called when the class "new'd"

    function __construct(&$idsadmin)
    {
        $this->idsadmin = &$idsadmin;
        $this->idsadmin->load_template("template_schemamgr");
        $this->idsadmin->load_lang("schemamgr");
        $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('schemamgr'));
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
                $this->idsadmin->setCurrMenuItem("schemamgr");
                $this->def();
                break;
        }
    } # end function run

    function def()
    {
            $this->idsadmin->html->add_to_output($this->idsadmin->template["template_schemamgr"]->render($this->idsadmin->phpsession->get_lang()) );
    } // end def

} // end class home
?>
