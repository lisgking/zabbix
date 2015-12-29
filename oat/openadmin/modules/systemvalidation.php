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
 * The main class for the systemvalidation
 */

class systemvalidation {

    /**
     * This class constructor sets
     * the default title and the
     * language files.
     *
     * @return systemvalidation
     */
    function __construct(&$idsadmin)
    {
        $this->idsadmin = &$idsadmin;
        $this->idsadmin->load_lang("systemvalidation");
        $this->idsadmin->load_lang("misc_template");
        $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('mainTitle'));
    }

    /**
     * To select a list of databases using select tag
     */
    function selectListDbs($name, $sql, $dbsname="sysmaster",
    $default="", $size="1" )
    {
        $disabled = "";
        if (!$this->idsadmin->phpsession->serverInfo->isPrimary())
        {
        	$disabled = "disabled='disabled'";
        }
    	
    	$locale = $this->uniqueNonEnglishLocale();
        // $dbadmin = $this->idsadmin->get_database($dbsname);
        $dbadmin = $this->localizeDbConn($dbsname,$locale);

        $stmt = $dbadmin->query($sql);

        $html=<<<END
<script type="text/javascript">
function switchValDatabase(dbsname)
{
      var prev = 0;

      for ( prev=0 ; prev < dbsname.options.length ; prev++ )
      {
        if ( dbsname.options[prev].defaultSelected == true )
        {
            break
        }
      }

      if ( true )
      {
        document.valdbswitch.submit();
      }
      else
      {
        dbsname.selectedIndex = prev;
      }
}      
</script>

        <select name="{$name}" size="{$size}" {$disabled} onchange="switchValDatabase(this)" >

END;
        while ($res = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            $value = current( $res );
            $ptext = next( $res );
            if (  ( strcasecmp( $value, $default) == 0 ) ||
               ( strcasecmp( $ptext, $default) == 0 )  )
            {
            	$sel=" selected='selected' ";
            } else {
            	$sel=" ";
            }
            if ($value == "ALL")
            {
                $value = $this->idsadmin->lang('ALL');
            }
            $html .= "<option value=\"{$value}\" {$sel}>{$value}</option>";
        }

        return $html;
    }

    /**
     * Used to select list of Extents
     */
    function selectListExt($name, $sql, $dbsname="sysmaster",
    $default="", $size="1" )
    {
        $disabled = "";
        if (!$this->idsadmin->phpsession->serverInfo->isPrimary())
        {
        	$disabled = "disabled='disabled'";
        }
    	
        $dbadmin = $this->idsadmin->get_database($dbsname);
        $stmt = $dbadmin->query($sql);
        $html = "<select name=\"{$name}\" size=\"{$size}\" {$disabled}>";

        while ($res = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            $value = current( $res );
            $ptext = next( $res );
            if (  ( strcasecmp( $value, $default) == 0 ) ||
            ( strcasecmp( $ptext, $default) == 0 )  )
            {
            	$sel=" selected='selected' ";
            } else {
            	$sel=" ";
            }
            if ($value == "ALL")
            {
                $value = $this->idsadmin->lang('ALL');
            }

            $html .= "<option value=\"{$value}\" {$sel} > {$value} </option>";
        }
        return $html;
    }

    function selectListTbs($name, $sql, $dbsname="sysmaster",
    $default="", $size="1" )
    {

        $disabled = "";
        if (!$this->idsadmin->phpsession->serverInfo->isPrimary())
        {
        	$disabled = "disabled='disabled'";
        }
        
    	$locale = $this->uniqueNonEnglishLocale();
        // $dbadmin = $this->idsadmin->get_database($dbsname);
        $dbadmin = $this->localizeDbConn($dbsname,$locale);

        $stmt = $dbadmin->query($sql);
        $html = "<select name=\"{$name}\" size=\"{$size}\" {$disabled}>";
        while ($res = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            $value = current( $res );
            $ptext = next( $res );
            if (  ( strcasecmp( $value, $default) == 0 ) ||
            ( strcasecmp( $ptext, $default) == 0 )  )
            {
            	$sel=" selected='selected' ";
            } else {
            	$sel=" ";
            }
            if ($value == "ALL")
            {
                $value = $this->idsadmin->lang('ALL');
            }

            $html .= "<option value=\"{$value}\" {$sel}> {$value} </option>";
        }
        return $html;
    }

    /**
     * The run function is what index.php will call.
     * The decission of what to actually do is based
     * on the value of the $this->idsadmin->in['do']
     *
     */
    function run()
    {

        $this->idsadmin->setCurrMenuItem("SystemValidation");
        if ($this->idsadmin->isreadonly()) 
        {
             $this->idsadmin->fatal_error("<center>{$this->idsadmin->lang('ErrorNoPermission')}</center>");
        }

        switch($this->idsadmin->in['do'])
        {
            case 'show':
                $this->showDb_TabList();
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('mainTitle'));
                break;

            default:
                $this->idsadmin->error($this->idsadmin->lang('InvalidURL_do_param'));
                break;
        }
    } # end function run
     
    /**
     Based on the Database and Table name execTableValidation
     does the oncheck and returns the value.
     *
     */

    function execTableValidation($dbsname,$tabname)
    {
        require_once ROOT_PATH."lib/gentab.php";
        $tab = new gentab($this->idsadmin);
        $dbadmin = $this->idsadmin->get_database("sysadmin");
         

        if(strcmp($tabname,"")==0 ||strcmp($tabname,$this->idsadmin->lang("ALL"))==0 )
        $tabname = "%";
         
        if(strcmp($dbsname,$this->idsadmin->lang("ALL"))==0)
        $dbsname = "%";

    	$locale = $this->uniqueNonEnglishLocale();
        // $dbadmin = $this->idsadmin->get_database("sysadmin");
        $dbadmin = $this->localizeDbConn("sysadmin",$locale);

        $sql ="SELECT admin('check data', t.partnum) AS id FROM
                sysmaster:systabnames t, sysmaster:sysptnhdr h WHERE t.partnum = h.partnum AND dbsname  like '$dbsname' AND 
                tabname like '$tabname' and npdata != 0 and rowsize > 1 INTO TEMP  tmp_results;";

        $dbadmin->query($sql);

        $sql ="SELECT cmd_ret_msg FROM command_history, tmp_results WHERE ABS(tmp_results.id) = cmd_number ORDER BY cmd_number ;";
        $tmp = $this->idsadmin->lang('oncheckSystemValidation');
        $tab->display_tab(
        	"$tmp", 
        array(
                "CMD_RET_MSG" => $this->idsadmin->lang('ValidationResultsTo'),
        ),
        $sql,"gentab_systemvalidation.php",$dbadmin);
        $sql ="drop table tmp_results ;";
        $dbadmin->query($sql);
    }

    /**
     Based on the chunk selected execExtentVerification verifies the specified chunk
     if we select ALL it veries all the chunks available
     *
     */

    function execExtentVerification($dbspacename)
    {
        require_once ROOT_PATH."lib/gentab.php";
        $tab = new gentab($this->idsadmin);
        $dbadmin = $this->idsadmin->get_database("sysadmin");
         
        if(strcmp($dbspacename, $this->idsadmin->lang('ALL'))!=0)
        $sql ="select admin('check extents', dbsnum) as id from 
              sysmaster:sysdbstab where name= '$dbspacename' into TEMP tmp_results";
        else
        $sql ="SELECT  admin('check extents', dbsnum) as id FROM
                sysmaster:sysdbstab into TEMP tmp_results;";        

        $dbadmin->query($sql);

        $sql ="SELECT cmd_ret_msg FROM command_history, tmp_results
		 WHERE ABS(tmp_results.id) = cmd_number ORDER BY cmd_number ;";

        $tmp = $this->idsadmin->lang('oncheckSystemValidation');
        $tab->display_tab( "$tmp",
        array(
           	"EXT" => $this->idsadmin->lang('ValidationResults'),
        ),
        $sql,"gentab_systemvalidation.php",$dbadmin);
    }

    # This function displays a list of databases and tables in the form of list
    function showDb_TabList( )
    {
        require_once ROOT_PATH."lib/gentab.php";
        
        $disabled = "";
        if (!$this->idsadmin->phpsession->serverInfo->isPrimary())
        {
        	$disabled = "disabled";
        	$this->idsadmin->error($this->idsadmin->lang('SysValNotValid'));
        }
        
        $group_default=$this->idsadmin->lang("ALL");
        if ( isset( $this->idsadmin->in['Dbs_name'] ) &&
        strcasecmp($this->idsadmin->lang('ALL'),$this->idsadmin->in['Dbs_name']) !=0 )
        {
            $dbname1= $this->idsadmin->in['Dbs_name'];
            $group_default=$this->idsadmin->in['Dbs_name'];
        }


        if(strcmp($this->idsadmin->in['Dbs_name'],$this->idsadmin->lang("ALL"))==0)
        $dbname1 = '%';

        $html=<<<END
<script type="text/javascript">
function confirmCheckData()
{
    c = confirm("{$this->idsadmin->lang("confirmCheckData")}");

    if ( c )
    {
        document.valdbswitch.checkTable.value = "true";
        document.valdbswitch.submit();
    }
}
</script>
        
<table width="100%" align="center">
<tr>
<td align="center" colspan="4">
       <form method="post" name="valdbswitch" action="index.php?act=systemvalidation&amp;do=show">
 	   <table class="systemvalidation">
 	   <tr>
			<td class="tblheader" colspan="5" align="center">
		        {$this->idsadmin->lang('checkDataFormat')}
    		</td>
       </tr>
	   <tr>
		<th align="center" colspan="4">
			{$this->idsadmin->lang('databaseName')}:
END;
		$html .= $this->selectListDbs("Dbs_name",
                "SELECT trim(name) as dbsname FROM sysdatabases " .
                " UNION SELECT 'ALL' as dbname FROM sysdatabases " ,
                "sysmaster",
		$group_default);

		$html.=<<<END
		 </select>
    	</th>
		</tr>
		<tr>
			<th align="center" colspan="4"> {$this->idsadmin->lang('tableName')}: 
END;

		$html .= $this->selectListTbs("dbstext",
                 "select trim(tabname) as tabname1 from " .
                "sysmaster:systabnames t , sysmaster:sysptnhdr h where t.partnum = h.partnum ".
		        " AND dbsname like '$dbname1' AND tabname not like ' %'" .
                " AND rowsize > 1 AND npdata != 0 ".
		        " UNION SELECT 'ALL' as tabname1 FROM sysmaster:systabnames ",
                "sysmaster", $this->idsadmin->lang("ALL"));
		$html.=<<<END
		</select>
			</th>
		</tr>
		<tr>
			<td align="center" colspan="4" >
    			<input type="button" {$disabled} class="{$disabled}button" name="checkTableButton" value="{$this->idsadmin->lang('chkTable')}" onclick="confirmCheckData()" />
    			<input type="hidden" {$disabled} name="checkTable" value="false" />
    		</td>
 		</tr>
	</table>
 </form>
</td>
<td align="left" colspan="4" width="50%" valign="top">
END;
		$this->idsadmin->html->add_to_output($html);
		 
		$html=<<<END
<form method="post" action="index.php?act=systemvalidation&amp;do=show">
<table class="systemvalidation">
<tr>
	<td class="tblheader" colspan="4" align="center">
    {$this->idsadmin->lang('chkExtFormat')}
    </td>
</tr>    
<tr>
	<th align="center" colspan="4">
	{$this->idsadmin->lang('extName')}:
END;
$html .= $this->selectListExt("dbspace_name",
                "SELECT trim(name) as dbs_name FROM sysdbspaces".
                " UNION SELECT 'ALL' as dbs_name  FROM sysdbspaces;" ,
                "sysmaster",
$group_default);
$html.=<<<END
	  </select>
</th>
</tr>
<tr>
<td align="center">
      <input type="submit" {$disabled} class="{$disabled}button" name="checkExtent" value="{$this->idsadmin->lang('chkExtent')}" />
</td>
</tr>
</table>
      </form>
</td>
</tr>
</table>
END;
$this->idsadmin->html->add_to_output( $html );

if (isset($this->idsadmin->in['checkTable']) && $this->idsadmin->in['checkTable'] == "true")
{
    $this->execTableValidation($this->idsadmin->in['Dbs_name'],
    $this->idsadmin->in['dbstext']);
}

else if (isset($this->idsadmin->in["checkExtent"]))
{
    $this->execExtentVerification($this->idsadmin->in['dbspace_name']);
}
    }
    
	/* Tables is system databases like sysmaster, sysadmin could potentially have data in multiple locales.
	 * An example scenario is db A having Japanese char table names and db B having Chinese char table names.
	 * SQL Tracing would create data with these names in the above mentioned system db tables. Hence a table would
	 * have data in multiple locales. The assumption below is that a typical customer scenario would be to have
	 * databases in only one non-English locale.
	 *
	 * return - unique non-English locales
	 */
	private function uniqueNonEnglishLocale()
	{
		$locale = NULL;
		$unique_locale = "select unique(dbs_collate) from sysdbslocale where dbs_collate NOT LIKE 'en_%'";
		//$unique_locale = "select unique(dbs_collate) from sysdbslocale ";

		$locale_res = $this->doDatabaseWork($unique_locale,"sysmaster");
		
		if ( count($locale_res) > 1 )
		{
			$this->idsadmin->fatal_error($this->idsadmin->lang('NotYetImpl'));
		} 
		else if ( count($locale_res) == 1 )
		{
			$locale = $locale_res[0]['DBS_COLLATE'];
		}
		
		return $locale;
	}
	
	/* Get database connection with appropriate LOCALE settings
	 */
	private function localizeDbConn($dbname,$locale=NULL)
	{
		if (is_null($locale)) {
			$db = $this->idsadmin->get_database($dbname);			
		} else {
			require_once(ROOT_PATH."lib/database.php");
			$db = new database($this->idsadmin,$dbname,$locale,"","");
		}
		
		return $db;
	}
	
    /**
     * do the database work.
     *
     */
    function doDatabaseWork($sel,$dbname="sysmaster",$locale=NULL)
    {
        $ret = array();

		if (is_null($locale)) {
			$db = $this->idsadmin->get_database($dbname);			
		} else {
			require_once(ROOT_PATH."lib/database.php");
			$db = new database($this->idsadmin,$dbname,$locale,"","");
		}
		
        while (1 == 1)
        {
            $stmt = $db->query($sel);
            while ($row = $stmt->fetch() )
            {
                $ret[] = $row;
            }
            $err = $db->errorInfo();

            if ( $err[2] == 0 )
            {
                $stmt->closeCursor();
                break;
            }
            else
            {
                $err = "{$this->idsadmin->lang('ErrorF')} {$err[2]} - {$err[1]}";
                $stmt->closeCursor();
                trigger_error($err,E_USER_ERROR);
                continue;
            }
        }

        return $ret;
    }

}
?>
