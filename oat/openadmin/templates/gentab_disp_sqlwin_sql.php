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


class gentab_disp_sqlwin_sql {

	private $textopt=0;
	private $byteopt=0;
	private $sessionid="";
	
	public $idsadmin;

	function gentab_disp_sqlwin_sql(&$idsadmin)
	{
		$this->idsadmin = $idsadmin;
		
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

		$HTML = <<<EOF
$pag
<div class="borderwrap">
<table class="gentab" >
EOF;


		if ( $column_titles != "" )
		{
			$sz=sizeof($column_titles);
			$HTML .= <<<EOF
<tr>
<td class="tblheader" align="center" colspan="{$sz}">{$title}</td>
</tr>
EOF;

			$HTML .= "<tr>";
			foreach ($column_titles as $index => $val)
			{
				$HTML .= "<th align='center'>";
				$HTML .= $val;
				$HTML .= "</th>";
			}
			$HTML .= "</tr>";
		}	
		return $HTML;
	}

	function sysgentab_row_output($data, $arg2="")
	{

		$HTML = "<tr>";

		if ( $arg2 == "" )
		{
			$type_r = array();
		} else {
			$type_r = $arg2;
		}

		$rcnt=1;
		$coltype=reset($type_r);
		foreach ($data as $index => $val)
		{
			$HTML .= "<td>";
			if ( $type_r && ($coltype == "TEXT" || $coltype == "CLOB"))
			{
				$HTML .= $this->do_text($val, $coltype, $rcnt);
			}
			else if ( $type_r && ($coltype == "BYTE" || $coltype == "BLOB"))
			{
				$HTML .= $this->do_byte($val, $coltype, $rcnt);
			}
			/* else if ( strncmp($coltype, "ROW", 3) == 0 ) */
			/*    print_r($val); */
			else {
				$HTML .= $val;
			}

			$HTML .= "</td>";
			$rcnt++;
			$coltype=next($type_r);
		}
		$HTML .= "</tr>";
		return $HTML;
	}

	function do_text($val, $type, $rcnt)
	{
		$this->idsadmin->load_lang("misc_template");
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



	function sysgentab_end_output($pag="")
	{
		$HTML = <<<EOF
</table>
</div>
$pag
EOF;
		return $HTML;
	}



}

?>
