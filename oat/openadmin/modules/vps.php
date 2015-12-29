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
 * This module is to view, add or remove VPs
 */

class vps {

	public $idsadmin;

	function __construct(&$idsadmin)
	{
		$this->idsadmin = &$idsadmin;
		$this->idsadmin->load_lang('vps');
		$this->idsadmin->html->set_pagetitle($this->idsadmin->lang('virtualprocessors'));
	}

	function run()
	{
		$this->idsadmin->setCurrMenuItem("VP");
		switch ($this->idsadmin->in['do'])
		{
			case "global":
				if (isset ($this->idsadmin->in['vpaction']))
				{
					$this->action_vp();
				}
				$this->global_info();
				break;
			case "classdetails":
				if (isset ($this->idsadmin->in['vpaction']))
				{
				    $this->action_vp();
				}
				$this->classdetails();
			    break;
			default:
			    $this->idsadmin->error($this->idsadmin->lang('InvalidURL_do_param'));
				break;
		}
	}

	function global_info()
	{
		$html = "";
		$html .= <<< EOF
<h3>{$this->idsadmin->lang('VPSGlobalInfo')}</h3><br/>
EOF;

		require_once "modules/onstat.php";
		$this->idsadmin->html->add_to_output( "<table width='100%'><tr><td>" );
		$onstat = new onstat($this->idsadmin);
		$onstat->showVPGraph();
		$this->idsadmin->html->add_to_output( "</td></tr></table>" );

		$this->idsadmin->html->add_to_output($html);

		$db = $this->idsadmin->get_database("sysmaster");
		require_once ROOT_PATH."lib/gentab.php";
		$tab = new gentab($this->idsadmin);

		$qry = "select trim(class) as class ," .
		"count(class) as count ," .
		"TRUNC(sum(usercpu),2) as usercpu ," .
		"TRUNC(sum(syscpu),2) as syscpu ," .
		"TRUNC(sum(usercpu+syscpu),2) as total " .
		"from sysvpprof " .
		"group by class" ;

		$qrycnt = "Select Count(*) as cnt from sysvpprof where 1=1";

		$tab->display_tab(
                $this->idsadmin->lang('vpclasses'),
		array(
		    "1" => $this->idsadmin->lang('class'),
		    "2" => $this->idsadmin->lang('cnt'),
		    "3" => $this->idsadmin->lang('usercpu'),
		    "4" => $this->idsadmin->lang('syscpu'),
		    "5" => $this->idsadmin->lang('totalcpu'),
		),
		$qry,
                "template_gentab_vps.php",
		$db,25);

		/**
		 * Adding the "total" row to the table
		 */
		$qry = "select count(class) as cnt, " .
		"sum(usercpu) as sumusercpu, " .
		"sum(syscpu) as sumsyscpu, " .
		"sum(usercpu+syscpu) as sumtot " .
		"from sysvpprof ";

		$stmt = $db->query($qry);
		$res = $stmt->fetch(PDO::FETCH_NUM);

		$html = <<< EOF
		<tr>
		<th align='center'>{$this->idsadmin->lang('total')}</th>
		<th align='center'>$res[0]</th>
		<th align='center'>$res[1]</th>
		<th align='center'>$res[2]</th>
		<th align='center'>$res[3]</th>
		</tr>
		</table>
		</div>
EOF;

		$this->idsadmin->html->add_to_output($html);


		$stmt = $db->query("select trim(class) as class from sysvpprof " .
                           " where class in ('cpu','jvp','encrypt','idsxmlvp') " .
                           " group by class " .
                           " having count(*) > 1 " );
		$this->show_add_drop_vp($stmt);

	}

	function show_add_drop_vp($stmt)
	{
		if ( $this->idsadmin->isreadonly() )
		{
			return "";
		}

		$html = "";

		$html .= <<<EOF
<h4>{$this->idsadmin->lang('AddDropVP')}</h4>
<form method="post" action="index.php?act=vps&amp;do=global">
<input type="hidden" name="vpaction" value="true"/>
<table>
<tr><td>
<table border="0">
<tr>
<th>{$this->idsadmin->lang('Count')}</th><th>{$this->idsadmin->lang('VPType')}</th><th></th>
</tr>
<tr>
<td><input type="text" name="count_add" size="10" value="1" /></td>
<td><select name="type_add"><option value="cpu">cpu</option>
						<option value="aio" >aio</option>
						<option value="encrypt">encrypt</option>
						<option value="idsxmlvp">idsxmlvp</option>
						<option value="jvp">jvp</option>
						<option value="lio">lio</option>
						<option value="msc">msc</option>
						<option value="pio">pio</option>
						<option value="str">str</option>
</select></td>
<td><input type="submit" class="button" name="vpaction_add" value="{$this->idsadmin->lang('add')}"/></td>
</tr>
</table></td>
<td>
EOF;

		$res = $stmt->fetch();
		if ( $res == null )
		{
			$html .= "</td></tr></table>";
			$this->idsadmin->html->add_to_output($html);
			return;
		}

		$html .= <<<EOF
<table border="0"><tr>
<th>{$this->idsadmin->lang('Count')}</th><th>{$this->idsadmin->lang('VPType')}</th><th></th>
</tr>
<tr>
<td><input type="text" name="count_drop" size="10" value="1" /></td>
<td><select name="type_drop">
EOF;

		while($res)
		{
			foreach ($res as $val)
			{
				$test = trim($val);
				$html .= "<option value='$test'>$test</option>";
			}
			$res = $stmt->fetch();
		}

		$html .= <<<EOF
</select></td>
<td><input type="submit" class="button" name="vpaction_drop" value="{$this->idsadmin->lang('drop')}"/></td>
</table></td>

</td></tr></table>

EOF;
		$this->idsadmin->html->add_to_output($html);

	}

	function action_vp()
	{
		if ( $this->idsadmin->isreadonly() )
		{
			return;
		}

		if (isset($this->idsadmin->in['vpaction_add']))
		{
			$do = "+";
			$check = array (
					"1" => "count_add",
					"2" => "type_add",
			);

			foreach ($check as $val)
			{
				if ( empty($this->idsadmin->in[ $val ]) &&
				strlen($this->idsadmin->in[ $val ])<1 )
				{
					$this->idsadmin->error(
			                $this->idsadmin->lang('VPValNotSet') . " $val" );
					return;
				}
			}

			$count = $this->idsadmin->in['count_add'];
			$type = $this->idsadmin->in['type_add'];
		}
		else if (isset($this->idsadmin->in['vpaction_drop']))
		{
			$do = "-";
			$check = array (
					"1" => "count_drop",
					"2" => "type_drop",
			);

			foreach ($check as $val)
			{
				if ( empty($this->idsadmin->in[ $val ]) &&
				strlen($this->idsadmin->in[ $val ])<1 )
				{
					$this->idsadmin->error(
			                $this->idsadmin->lang('VPValNotSet') . " $val" );
					return;
				}
			}
			$count = $this->idsadmin->in['count_drop'];
			$type = $this->idsadmin->in['type_drop'];
		} else {
			$this->idsadmin->error("{$this->idsadmin->lang('OptionNotAvail')}");
		}


		$db = $this->idsadmin->get_database('sysadmin');
		$sql = "select task ('ONMODE', 'p', '$do$count', '$type') as info " .
		"from systables where tabid=1";
		$stmt = $db->query($sql);
		$res = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($res['INFO'] == "OK")
		{
			$this->idsadmin->status($this->idsadmin->lang('success'));
		}
		else
		{
			$this->idsadmin->status($res['INFO']);
		}
		$stmt->closeCursor();


	}

	function classdetails()
	{
		$this->idsadmin->html->set_pagetitle($this->idsadmin->lang('classdetails'));
		$classname = $this->idsadmin->in['classname'];

		$db = $this->idsadmin->get_database("sysmaster");
		require_once ROOT_PATH."lib/gentab.php";
		$tab = new gentab($this->idsadmin);

		$sql = "select vpid, class, usercpu, syscpu, " .
		"sum (usercpu+syscpu) as total from sysvpprof " .
		"where class ='$classname' " . 
		"group by vpid,class,usercpu,syscpu";

		$sqlcnt = "select count(class) as cnt from sysvpprof where class='$classname'";

		$tab->display_tab_by_page($this->idsadmin->lang('VPClassTitle',array(strtoupper($classname))),
		array(
		"1" => $this->idsadmin->lang('vpid'),
		"2" => $this->idsadmin->lang('class'),
		"3" => $this->idsadmin->lang('usercpu'),
		"4" => $this->idsadmin->lang('syscpu'),
		"5" => $this->idsadmin->lang('totalcpu'),
		),
		$sql,$sqlcnt,NULL,"template_gentab_vps_details.php");

		/**
		 * Adding the "total" row to the table
		 */
		$qry = "select sum(usercpu) as sumusercpu, " .
		"sum(syscpu) as sumsyscpu, " .
		"sum(usercpu+syscpu) as sumtot " .
		"from sysvpprof " .
		"where class='$classname'";

		$stmt = $db->query($qry);
		$res = $stmt->fetch(PDO::FETCH_NUM);

		$html = <<< EOF
		<tr><td>&nbsp;</td>
		<th>{$this->idsadmin->lang('total')}</th>
		<th>$res[0]</th>
		<th>$res[1]</th>
		<th>$res[2]</th>
</tr>
</table></div>
EOF;

		if ( $this->idsadmin->isreadonly() )
		{
			$this->idsadmin->html->add_to_output($html);
			return;
		}

		$html .= "<h4>{$this->idsadmin->lang('modifyingClass')}: &nbsp;&nbsp;" . strtoupper($classname) . "</h4>";
		$html .= <<< EOF
		<form method="post" action="index.php?act=vps&amp;do=classdetails&amp;classname=$classname">
		<input type="hidden" name="vpaction" value="true"/>
		<table border="0">
		<tr>
		<th></th><th>{$this->idsadmin->lang('Count')}</th>
		</tr>
		<tr>
		<td width="45%">{$this->idsadmin->lang('addVP')} ($classname) :</td>
		<td><input type="text" name="count_add" size="10" value="1" /></td>
		<td><input type="hidden" name="type_add" value="$classname"/></td>
<td><input type="submit" class="button" name="vpaction_add" value="{$this->idsadmin->lang('add')}"/></td>
</tr>

EOF;

		if ($classname == "cpu" || $classname == "encrypt" || $classname == "jvp")
		{
			$stmt = $db->query("select count(class) as cnt from sysvpprof where class='$classname'");
			$res = $stmt->fetch(PDO::FETCH_NUM);

			if ($res[0] > 1 || $classname == "jvp")
			{
				$html .= <<<EOF
				<tr>
				<td width="45%">{$this->idsadmin->lang('dropVP')} ($classname) :</td>
				<td><input type="text" name="count_drop" size="10" value="1"/></td>
				<td><input type="hidden" name="type_drop" value="$classname"/></td>
<td><input type="submit" class="button" name="vpaction_drop" value="{$this->idsadmin->lang('drop')}"/></td>
</tr>
EOF;
			}

		}

		$html .= "</table></form>";

		$this->idsadmin->html->add_to_output($html);

	}


} //end class

?>
