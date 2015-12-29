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


class template_rss {
    public $idsadmin;
    
    function rss_intro()
    {
        $html="";
        $html .= <<<EOF
<h3>RSS Feeds</h3>
<br/>Please select from the following RSS feeds:<br/>
<ul style="font-size: medium; list-style-type: none;">
EOF;
        return $html;
    }

    function rss_conclusion()
    {
        $html="";
$html .= <<<EOF
</ul>
EOF;
        return $html;
    }
}

?>
