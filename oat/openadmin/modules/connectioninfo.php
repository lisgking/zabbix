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


/* Connection Info class */
class connectioninfo {

    public $idsadmin;
    function __construct(&$idsadmin)
    {
        $this->idsadmin = $idsadmin;
    }

    /**
     * The run function is what index.php will call.
     * The decission of what to actually do is based
     * on the value of the $this->idsadmin->in['do']
     *
     */
    function run()
    {
         
        switch($this->idsadmin->in['do'])
        {
            case "getconnections":
                $this->getconnections();
                break;
            default:
                $this->def();
                break;
        }
    } # end function run

    function def()
    {
        $this->idsadmin->error($this->idsadmin->lang('InvalidURL_do_param'));
    }

    function getconnections()
    {
        if ( ! isset($this->idsadmin->in['group_num']) )
        {
            $grp = 1;
        }
        else
        {
            $grp = intval($this->idsadmin->in['group_num']);
        }
        
        require_once("lib/connections.php");
        $conndb = new connections($this->idsadmin);
        $conndb->getconnectionsforGroup($grp);
        
    }
    
}
?>
