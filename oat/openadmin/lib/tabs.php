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
 * Class to provide Tab displays
 *
 */
class tabs {

    private $tabs = array();
    
    function addtab($url,$title,$iscurrent)
    {
      array_push($this->tabs , array($url,$title,$iscurrent));
    }    


    function tohtml($subtabs=true)
    {
     
        $html = "<div id='tabs'> <ul>";
        foreach ($this->tabs as $k => $v)
        {
             if ( $v[2] == 0 )
             {
                 $html .= "<li><a href='$v[0]'>$v[1]</a></li>";
             } else {
                 $html .= "<li><span>$v[1]</span></li>";
             }
       }
       if ($subtabs)
       {
           $html .= "</ul></div><div id='subtabs'>&nbsp;</div>";
       } else {
           $html .= "</ul></div>&nbsp;";
       }
       return $html;
    }

   function current($active)
   {
     $this->tabs[$active-1][2]=1;
   }

} // end class
?>
