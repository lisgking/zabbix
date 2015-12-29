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

class gentab_show_onlinelog {

	private $sz=0;

	function sysgentab_start_output( $title, $column_titles, $pag="")
	{
		$this->idsadmin->load_lang("misc_template");
		$this->idsadmin->load_lang("admin");
		$this->idsadmin->load_lang("rlogs");
		$this->idsadmin->load_lang("global");
		$this->sz=sizeof($column_titles);
		
		if (! (isset($this->idsadmin->in["runReports"]) || isset($this->idsadmin->in["reportMode"])) )
		{
			$HTML = <<<EOF

        <script type="text/javascript">
            dojo.require("dijit.Dialog");
            dojo.require("dijit.form.Button");   
            
            var dialogConfirm;
            
            function doMessage(what)
            {
               var msgStr;
               var titleStr;
               
               if ( what == "truncate" )
               {
                msgStr = "{$this->idsadmin->lang('AreYouSureTruncate')}";
                titleStr = "{$this->idsadmin->lang('LogTruncate')}";
	           }
               else
               {
                msgStr = "{$this->idsadmin->lang('AreYouSureDelete')}";
                titleStr = "{$this->idsadmin->lang('LogDelete')}";
               }
               
               dialogConfirm = new dijit.Dialog ({title:titleStr,style:"width:300px"});
               dialogConfirm.attr('class','claro');
               dialogConfirm.attr('id','confirmDialog');
               dialogConfirm.attr('onCancel', function() { closeDialog(0); } );
               dialogConfirm.attr('content',
               "<img class='dialogimg' src='images/warning.png' alt='{$this->idsadmin->lang('Warning')}' align='middle'></img>"+msgStr+"<br/><br/><br/><div align='right'><button type='dijit.form.Button' onClick='doDelete(\""+what+"\")'>{$this->idsadmin->lang('Yes')}</button>&nbsp;&nbsp;<button type='dijit.form.Button' onClick='closeDialog()'>{$this->idsadmin->lang('No')}</button></div></div>"
               );
               dialogConfirm.show();   
            }
            
            function closeDialog(refresh)
            {
               dialogConfirm.hide();
               if ( refresh == 1 )
               {
               window.location="index.php?act=show&do=showOnlineLogTail";
               }
            }
            
            function doDelete(what)
            {               
                executeTask(what);
            }
            
            function executeTask(what)
            {          
                var whatToDo = "doOnlineLogAdmin";
                
                var xhrArgs = {
                    url: "index.php?act=show&do="+whatToDo+"&action="+what,
                    handleAs: "text",
                    load: function(data) {
                        console.log("loaded");
                        dialogConfirm.attr('onCancel',function () { closeDialog(1); } );
                        dialogConfirm.attr('title','Result');
                        dialogConfirm.attr('content',"<div align='center' style='width:300px'>"+data+"<br/><br/><input type='button' class='button' value='{$this->idsadmin->lang('ok')}' onClick='closeDialog(1)'></input></div>");
    dialogConfirm.show();                
    },
                    error: function(error) {
                        dialogConfirm.attr('onCancel',function () { closeDialog(1); });
                        dialogConfirm.attr('content' ,"Error: "+error);
    dialogConfirm.show();                
                    }
                }
                dojo.xhrGet(xhrArgs);
            }
        </script>
EOF;

		}
		
		$HTML .= <<<EOF
<div class='tabpadding'>
  <div class='borderwrap'>		
        {$pag}
<div class="tblheader" align="center">{$title}</div>
EOF;

		if (!isset($this->idsadmin->in["runReports"]))
		{
			$HTML .= "<div dojoType=\"dijit.layout.ContentPane\" style=\"height:600px; overflow: auto; border-bottom: 1px solid #9BABC5;\">";
		}

		$HTML .= <<<EOF
<table class="gentab_log" cellpadding="0" cellspacing="0" >
EOF;

        $HTML .= "<tr>";
        foreach ($column_titles as $index => $val)
        {
        	$HTML .= "<th align='center'>";
        	$HTML .= $val;
        	$HTML .= "</th>";
        }
        $HTML .= "</tr>\n";
        return $HTML;
	}

	function sysgentab_row_output($data)
	{
		$HTML="";

		$HTML = "<tr>";
		foreach ($data as $index => $val)
		{
			$val = trim($val);
			
			$val = htmlentities($val,ENT_COMPAT,"UTF-8");
			 
			if ( empty($val) == true )
			{
				$val .= "<br/>";
			}

			if ( stripos( $val, "err") || stripos( $val, "assert") ||
			stripos( $val, "Exception ") || stripos( $val, "fail") ) {
				if (stripos( $val,"success"))
				{
					// If if string also has success in it, don't mark it as an error!
					// For example, the message 'R-tree error message conversion completed successfully'
					// should not be marked as an error in the online.log
					$HTML .= "<td class='rowlog'>" . $val;
				} else {
						
					$HTML .= "<td class='rowlogexception'> <strong> " . $val . "</strong>";
				}
			} elseif ( stripos( $val, "warn") ) {

				$HTML .= "<td class='rowlogwarn'><strong> " . $val . "</strong>";
			} elseif (stripos( $val, "Advisory:") ) {

				$HTML .= "<td class='rowlogadvise'><strong>{$val}</strong>";
			} else {
				$HTML .= "<td class='rowlog'>" . $val;
			}

			$HTML .= "</td>";
		}
		$HTML .= "</tr>";
		return $HTML;
	}

	function sysgentab_end_output($pag="")
	{
		$HTML = "</table>";

		$report_mode = (isset($this->idsadmin->in["runReports"]) || isset($this->idsadmin->in["reportMode"]));
		
		if (!$report_mode && ( !$this->idsadmin->isreadonly() )
			&& ( Feature::isAvailable ( Feature::PANTHER_UC3, $this->idsadmin->phpsession->serverInfo->getVersion() ) )
			)
		{
			 
			$HTML .= <<<EOF
</div>
    <div align="right" style="padding: 5px;">
        <input type="button" class="button" value="{$this->idsadmin->lang('LogDelete')}" onclick="doMessage('delete')"/>
        <input type="button" class="button" value="{$this->idsadmin->lang('LogTruncate')}" onclick="doMessage('truncate')"/>
    </div>
</div>
</div>
EOF;
		}

		$HTML .= <<<EOF
		{$pag}
  </div><!-- end borderwrap -->
</div><!-- end tabpadding -->
EOF;
		return $HTML;
	}

}
?>
