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


class gentab_pag_sqlwin_info {

    private $textopt=0;
    private $byteopt=0;
    private $sessionid="";

    public $idsadmin;

    function gentab_pag_sqlwin_info(&$idsadmin)
    {

    	$this->idsadmin = &$idsadmin;
        $this->idsadmin->load_lang("misc_template");
        $text_opt_ar = $this->idsadmin->phpsession->get_sqltextoptions();
        $byte_opt_ar = $this->idsadmin->phpsession->get_sqlbyteoptions();

        $this->sessionid=session_id();

        foreach ($text_opt_ar as $key => $val)
        {
            if ( $val == "selected" )
            {
                $this->textopt = $key;
                break;
            }
        }
        foreach ($byte_opt_ar as $key => $val)
        {
            if ( $val == "selected" )
            {
                $this->byteopt = $key;
                break;
            }
        }
    }


    function sysgentab_start_output( $title, $column_titles,
    $pag="", $url="", $col_types="")
    {

        $sz=sizeof($column_titles);

	$this->idsadmin->load_lang("misc_template");
        $url=$this->idsadmin->removefromurl("orderby");
        $url=preg_replace('/&'."orderway".'\=[^&]*/', '', $url);
        // $url = htmlentities($url);
        if ( $col_types )
        {
            $coltype = reset($col_types);
        }

        $HTML = <<<EOF
$pag
<div class="borderwrap">
<table class="gentab" >
<tr>
<td class="tblheader" align="center" colspan="{$sz}">{$title}</td>
</tr>
EOF;

        $HTML .= "<TR>";
        foreach ($column_titles as $index => $val)
        {
            $img="";
            $HTML .= "<td class='formsubtitle' align='center'>";

            if ( $col_types &&
            ($coltype == "TEXT" || $coltype == "CLOB" ||
            $coltype == "BYTE" || $coltype == "BLOB" ) )
            {
                /* cannot order by these column types */
                $HTML .= $val;
            }

            else
            {

                if(isset($this->idsadmin->in['orderby']) && $this->idsadmin->in['orderby']==$index)
                {
                    $img="<img src='images/arrow-up.gif' border='0' alt='{$this->idsadmin->lang('Ascending')}'/>";
                    if (isset($this->idsadmin->in['orderway']))
                    $img="<img src='images/arrow-down.gif' border='0' alt='{$this->idsadmin->lang('Descending')}'/>";
                }

                if( (isset($this->idsadmin->in['orderby']) && $this->idsadmin->in['orderby']==$index) && !(isset($this->idsadmin->in['orderway'])) )
                {
                    $HTML .= "<a href='{$url}&amp;orderby=$index&amp;orderway=DESC' title='{$this->idsadmin->lang("OrderDesc", array($val))}'>{$val}{$img}</a>";
                }
                else
                {
                    $HTML .= "<a href='{$url}&amp;orderby=$index' title='{$this->idsadmin->lang("OrderAsc", array($val))}'>{$val}{$img}</a>";
                }
            } /* else of if a blob type */

            $HTML .= "</td>";
            if ( $col_types )
            $coltype = next($col_types);
        }   /* end foreach */


        $HTML .= "</TR>";
        return $HTML;
    }

    function sysgentab_row_output($data, $col_types="")
    {

        $HTML = "<TR>";

	
        $rcnt=1;
        if ( $col_types )
        $coltype=reset($col_types);

        foreach ($data as $index => $val)
        {
            $HTML .= "<TD> ";
            if ( $index == "EXPRTEXT" )
            {
                $str=stream_get_contents($val);
                $HTML .= $str;
            }
            else if ( $index == "_SQLWIN_TABPARTNUM" )
            {
                if ( strncmp($val, '0x', 2) == 0)
                {
                    $HTML .= "<a href=\"javascript:void(0);\" onClick=\"var w=window.open('index.php?act=sqlwin_pop&amp;do=show_partnum&amp;val={$val}', 'PartnumInfo', 'width=400, height=350, resizable=yes, scrollbars=yes'); w.focus();\">";
                    $HTML .= $val;
                    $HTML .= "</a>";
                }
                else
                $HTML .= $val;
            }

            else if ( $col_types && ($coltype == "TEXT" ||  $coltype == "CLOB"))
            {
                $HTML .= $this->do_text($val, $coltype, $rcnt);
            }
            else if ( $col_types && ($coltype == "BYTE" || $coltype == "BLOB"))
            {
                $HTML .= $this->do_byte($val, $coltype, $rcnt);
            }
            else
            $HTML .= $val;

            $HTML .= "</TD>";
            $rcnt++;
            $coltype=next($col_types);
        }
        $HTML .= "</TR>";
        return $HTML;
    }

    function sysgentab_end_output($pag="")
    {
        $HTML = <<<EOF
</table>
</div>
$pag
EOF;
return $HTML;
    }


    function do_text($val, $type, $rcnt)
    {
	
        $ltype = strtolower($type);

        switch($this->textopt)
        {
            case 0 : /* show_all_text */
                $str=stream_get_contents($val);
                /* $HTML .= "<pre>$str</pre>"; */
                $str2 = str_replace("\n", "<br/>", $str);
                $str2 = htmlentities($str2,ENT_COMPAT,"UTF-8");
                return $str2;
                break;

            case 1 : /* show_some_text */
                //$str=stream_get_contents($val, 255);
                // The above line hangs waiting on end of stream for text < 255 characters;
                // workaround used is to read the entire stream and cut off after 255 characters.
                $str= stream_get_contents($val);
                $str = substr($str, 0, 255);
                $str2 = str_replace("\n", "<br/>", $str);
                $str2 = htmlentities($str2,ENT_COMPAT,"UTF-8");
                return $str2;
                break;

            case 2 : /* show_in_file  */
                if ($this->sessionid == "")
                return "[<strong>{$this->idsadmin->lang('ERROR')}</strong>]";

                $dir = ROOT_PATH."tmp/$this->sessionid";
                $mode = 0777;

                if ( ! is_dir($dir)  )
                {
                    if ( !mkdir($dir, $mode) )
                    return "[<strong>{$this->idsadmin->lang('ERROR')}</strong>]";
                }
                 
                $refnum = substr_replace($val, "", 0,13);
                $filename ="$dir"."/"."$ltype"."_"."$rcnt"."_"."$refnum" ;
                #printf ("filename=$filename\n");
                if ( ($dest = fopen($filename, 'w')) <= 0 )
                $str="[<strong>{$this->idsadmin->lang('ERROR')}</strong>]";
                else
                {
                    $num_bytes=stream_copy_to_stream($val, $dest);
                    if ( $num_bytes == 0 )
		            {
		                $str="[{$type}, {$this->idsadmin->lang('filesize',array(0))}]";
		            } else {
		                $str = "<a href=\"{$filename}\">{$this->idsadmin->lang('filetype',array($type))}</a>, {$this->idsadmin->lang('filesize',array($num_bytes))}" ;
		            }
                }
                return $str;
                break;

            case 3 : /* show_size_only */
                $tempfile = tmpfile();
                $num_bytes = stream_copy_to_stream($val, $tempfile);
                $str = "[$type, {$this->idsadmin->lang('filesize',array($num_bytes))}]" ;
                fclose($tempfile);
                return $str;
                break;

            case 4 : /* ignore_text */
                $str = "[<strong>{$this->idsadmin->lang('Ignore')} $type</strong>]";
                return $str;
                break;

            default: /* show_all_text */
                $str=stream_get_contents($val);
                $str2 = str_replace("\n", "<br/>", $str);
                $str2 = htmlentities($str2,ENT_COMPAT,"UTF-8");
                return $str2;
                break;
        }
    } #do_text()

    function do_byte($val, $type, $rcnt)
    {
	
        $ltype = strtolower($type);

        switch($this->byteopt)
        {
            case 0 : /* ignore_byte */
                $str = "[<strong>{$this->idsadmin->lang('Ignore')} $type</strong>]";
                return $str;
                break;

            case 1 : /* save_in_file  */
            case 3 : /* show_as_image */
                if ($this->sessionid == "")
                return "[<strong>{$this->idsadmin->lang('ERROR')}</strong>]";

                $dir = ROOT_PATH."tmp/$this->sessionid";
                $mode = 0777;

                if ( ! is_dir($dir)  )
                {
                    if ( !mkdir($dir, $mode) )
                    return "[<strong>{$this->idsadmin->lang('ERROR')}</strong>]";
                }
                 
                $refnum = substr_replace($val, "", 0,13);
                $filename ="$dir"."/"."$ltype"."_"."$rcnt"."_"."$refnum" ;
                #printf ("filename=$filename\n");
                if ( ($dest = fopen($filename, 'w')) <= 0 )
                $str="[<strong>{$this->idsadmin->lang('ERROR')}</strong>]";
                else
                {
                    $num_bytes=stream_copy_to_stream($val, $dest);
                    if ($this->byteopt == 1 )
                    {
                        if ( $num_bytes == 0 )
		                {
		                    $str="[{$type}, {$this->idsadmin->lang('filesize',array(0))}]";
		                } else {
		                    $str = "<a href=\"{$filename}\">{$this->idsadmin->lang('filetype',array($type))}</a>, {$this->idsadmin->lang('filesize',array($num_bytes))}" ;
		                }
                    }
                    else
                    {
                        if ( $num_bytes == 0 )
		                {
		                    $str="[{$type}, {$this->idsadmin->lang('filesize',array(0))}]";
		                } else {
		                    $str = "<img src=\"{$filename}\" alt=\"{$type} {$this->idsadmin->lang('file')}: {$filename}\" height=\"100\" width=\"100\">" ;
		                }
                    }
                }

                return $str;
                break;

            case 2 : /* show_size_only */
            	$tempfile = tmpfile();
                $num_bytes = stream_copy_to_stream($val, $tempfile);
                $str = "[$type, {$this->idsadmin->lang('filesize',array($num_bytes))}]" ;
                fclose($tempfile);
                return $str;
                break;


            default: /* ignore_byte */
                $str = "[<strong>{$this->idsadmin->lang('Ignore')} $type</strong>]";
                return $str;
                break;
        }
    } #do_byte()


}

?>
