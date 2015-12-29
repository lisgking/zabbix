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

/** Class to store info about individual onconfig parameters **/
class onconfig_param {
	
    private $idsadmin;
	
    /* onconfig parameter id */
    private $id;
		
    /* name of onconfig parameter */
    private $name;
	
    /* onconfig parameter value */
    private $value;
    
    /* onconfig parameter flags */
    private $flags;
	
    /* onconfig parameter description */
    private $description;
	
    /* onconfig parameter type: INT|STRING|BOOLEAN */
    private $type = null;
	
    /* onconfig parameter min and max limits (for INT) */
    private $min_value = null;
    private $max_value = null;
	
    /* onconfig parameter set of allowable values */
    private $value_set = null;
    
    function __construct ($param_id, $param_name, $param_value, $flags, $idsadmin) {
		require_once ROOT_PATH."lib/onconfig.php";
		$this->id = trim($param_id);
		$this->idsadmin = $idsadmin;
		$this->name = trim($param_name);
		$this->value = trim($param_value);
		$this->flags = $flags;
		$this->initialize();
		$this->idsadmin->load_lang("onconfigparam");
    }
	
    /** intialize variables based on onconfig parameter name **/ 
    function initialize() 
    {
		// set description
		$desc = $this->idsadmin->lang("{$this->name}_DESC");
		if (strcmp("MISSING LANG FILE ITEM {$this->name}_DESC",$desc) == 0) 
		{
		    $desc = $this->idsadmin->lang("NO_DESC_FOUND");
		}
		$this->description = $desc;
			
		// Set parameter type, min, max, value_set if we can determine them.
		require_once ("lib/feature.php");
		if (array_key_exists($this->name,onconfig::$onconfig_info))
		{
		    $info = onconfig::$onconfig_info[$this->name];
		    $this->type = (array_key_exists('type', $info))? $info['type']:"UNKNOWN TYPE";
		    $this->min_value = (array_key_exists('min', $info))? $info['min']:null;
		    $this->max_value = (array_key_exists('max', $info))? $info['max']:null;
		    $this->value_set = (array_key_exists('values', $info))? $info['values']:null;
		} 
		else if (Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin)) 
		{
			// For server versions >= 12.10, we can use the flags column to automatically determine the type of the onconfig parameter.
			if ($this->flags & 0x20)
			{
				// 0x20 = Boolean
				$this->type = onconfig::BOOLEAN;
			} else if (($this->flags & 0x40) || ($this->flags & 0x80)) {
				// 0x40 = 4-byte integer
				// 0x80 = 8-byte integer
				$this->type = onconfig::INT;
			} else if ($this->flags & 0x200) {
				// 0x200 = string
				$this->type = onconfig::STRING;
			} 
		}
    }

    function getId() {
	return $this->id;
    }
	
    function getName() {
		return $this->name;
    }
	
    function getType() {
		return $this->type;
    }
	
    function getMin() {
		return $this->min_value;
    }
	
    function getMax() {
		return $this->max_value;
    }
	
    function getValueSet() {
		return $this->value_set;
    }
	
    function getDescription() {
		return $this->description;
    }
	
    function getValue() {
		return $this->value;
    }
	
    function setValue($newValue) {
		$this->value = $newValue;
    }
	
    /* Returns true if it is a dynamic onconfig parameter */ 
    function isDynamic() {
		return in_array($this->name, onconfig::get_dynamic_onconfig_params($this->idsadmin));
    }
    
    /*
     * This function checks whehter the value of the onconfig parameter is valid.
     * Mostly this will involve verifying the type and, if min and max values exist, 
     * whether the value falls within the specified range. 
     * A few onconfig parameter will have special constraints (e.g. being one of 
     * a subset of values) that will be checked in this method.
     * 
     * If the value is NOT valid, an error message will be returned.
     * If the value is valid, the string 'VALID' will be returned.
     */
    function checkValue() {
	// verify the value is of the correct type
	switch($this->type)
        {
            case onconfig::INT;
        	if (!(is_numeric($this->value) && intval($this->value) == $this->value)) 
        		return $this->idsadmin->lang('SaveFailed') . " ". onconfig::INT ." {$this->idsadmin->lang('ValueReqd')} {$this->name}";      
            break;
            case onconfig::BOOLEAN;
            	// should be either 0 or 1
        	if (!(is_numeric($this->value) && intval($this->value) == $this->value) ||
        		!($this->value == 0 || $this->value == 1))
        		return $this->idsadmin->lang('SaveFailed'). " ". onconfig::BOOLEAN ." {$this->idsadmin->lang('ValueReqd')} {$this->name}";      
            break;
            case onconfig::STRING;
        	if (!is_string($this->value)) 
        		return $this->idsadmin->lang('SaveFailed'). " " . onconfig::STRING ." {$this->idsadmin->lang('ValueReqd')} {$this->name}";      
            break;
        }
        
        // Now we'll use another switch statement so we can have special   
	// validation rules for certain onconfig parameters.
	// The 'default' case will check all other parameters by validating
	// that the value is within range if a min and/or max are specified 
	// and validating that it is within the a subset of possible values.
	switch($this->name) 
	{
	   case "RTO_SERVER_RESTART";
		// 0=disabled, otherwise must be between 60 and 1800
		if ( !($this->value == 0 || (60 <= $this->value && $this->value <= 1800)) )
		{
			return 	$this->idsadmin->lang('SaveFailed') . " {$this->name} " . $this->idsadmin->lang('MustBe');
		}
		break;
		
	   default;
		// check that value is within range if min/max values specified
		if ($this->min_value !== null && $this->max_value !== null) 
		{
		    if ( !($this->min_value <= $this->value &&
			$this->value <= $this->max_value) )
		    { 
			return 	$this->idsadmin->lang('SaveFailed') . " {$this->name} " . $this->idsadmin->lang('MustBeRange') . " {$this->min_value} - {$this->max_value}.";
		    }
		} 
		else if ($this->min_value !== null)
		{
		    if ( !($this->min_value <= $this->value) )
		    {
			return $this->idsadmin->lang('SaveFailed') . " {$this->name} ". $this->idsadmin->lang('MustBeGreaterOrEqual') . " {$this->min_value}";
		    }
		}
		else if ($this->max_value !== null)
		{
		    if ( !($this->value <= $this->max_value) )
		    {
			return $this->idsadmin->lang('SaveFailed') . " {$this->name} ". $this->idsadmin->lang('MustBeLessOrEqual') . " {$this->max_value}";
		    }
		 }
				
		// If there is only a subset of allowable values, 
		// check if the value is that set
		if ($this->value_set !== null)
		{
		    if (! (in_array($this->value, $this->value_set)))
		    {
			$str = implode(', ', $this->value_set);
			return 	$this->idsadmin->lang('SaveFailed') . " {$this->name} " . $this->idsadmin->lang('MustBeInSet') . " { {$str} }";
		    }
		}
		break;
	}
		
	// If none of the validation rules failed, return 'VALID'
	return "VALID";
    }

    /**
     * This function calls the onconfig parameter's recommendation method.  
     *
     * @return an associative array that includes the recommendation message
     *         and whether the current setting complies with the recommendation.
     * 		   Array ('recommendation'=>"<recommendation text>", 
     * 				  'compliance'=> <true/false>);
     */
    private function parameterRecommendation()
    {
	// based on the onconfig parameter name, call the correct recommendation method;
	$rcmd_array = Array();
	switch($this->name) 
	{
	    case "AUTO_AIOVPS";
	    case "AUTO_CKPTS";
	    case "AUTO_LRU_TUNING";
	    case "AUTO_REPREPARE";
	    case "EXPLAIN_STAT";
	    case "FASTPOLL";
	    case "NOAGE";
	    case "RESTARTABLE_RESTORE";
	    case "TBLSPACE_STATS";
	   	$rcmd_array = onconfig::on_recommendation($this->value, $this->idsadmin);
		break;
	    case "DATASKIP";
		$rcmd_array = onconfig::off_recommendation($this->name, $this->value, $this->idsadmin);
		break;
	    case "BAR_DEBUG";
	    case "CCFLAGS";
	    case "QSTATS";
	    case "TRACEFLAGS";
	    case "TRACES";
	    case "WSTATS";
	   	$rcmd_array = onconfig::zero_recommendation($this->name, $this->value, $this->idsadmin);
		break;			
	    case "CLOSEPARTP";
		$rcmd_array = onconfig::zero_recommendation("ZERO", $this->value, $this->idsadmin);
		break;
	    case "RTO_SERVER_RESTART";
	    case "RESIDENT";
		$rcmd_array = onconfig::nonzero_recommendation($this->name, $this->value, $this->idsadmin);
		break;
	    case "ALARMPROGRAM";
	    case "SYSALARMPROGRAM";
		$rcmd_array = onconfig::ALARMPROGRAM_recommendation($this->name, $this->value, $this->idsadmin);
		break;
	    case "BAR_ACT_LOG";
	    case "BAR_DEBUG_LOG";
	    case "DUMPDIR";
		$rcmd_array = onconfig::nontemp_recommendation($this->value, $this->idsadmin);
		break;
	    case "FILLFACTOR";
	    case "BAR_NB_XPORT_COUNT";
		$rcmd_array = onconfig::minmax_recommendation($this->name, $this->value, $this->idsadmin);
		break;
	    case "AFF_NPROCS";
	    case "AFF_SPROC";
		$rcmd_array = onconfig::AFF_PROC_recommendation($this->name, $this->value, $this->idsadmin);
		break;
	    case "BAR_XFER_BUF_SIZE";
		$rcmd_array = onconfig::BAR_XFER_BUF_SIZE_recommendation($this->value, $this->idsadmin);
		break;	
	    case "BTSCANNER";
		$rcmd_array = onconfig::BTSCANNER_recommendation($this->value, $this->idsadmin);
		break;	
	    case "CKPTINTVL";
		$rcmd_array = onconfig::CKPTINTVL_recommendation($this->value, $this->idsadmin);
		break;
	    case "CLEANERS";
		$rcmd_array = onconfig::CLEANERS_recommendation($this->value, $this->idsadmin);
		break;
	    case "DB_LIBRARY_PATH";
		$rcmd_array = onconfig::DB_LIBRARY_PATH_recommendation($this->value, $this->idsadmin);
		break;
	    case "DBSPACETEMP";
		$rcmd_array = onconfig::DBSPACETEMP_recommendation($this->value, $this->idsadmin);
		break;
	    case "DEF_TABLE_LOCKMODE";
		$rcmd_array = onconfig::DEF_TABLE_LOCKMODE_recommendation($this->value, $this->idsadmin);
		break;
	    case "DS_NONPDQ_QUERY_MEM";
		$rcmd_array = onconfig::DS_NONPDQ_QUERY_MEM_recommendation($this->value, $this->idsadmin);
		break;
	    case "DS_TOTAL_MEMORY";
		$rcmd_array = onconfig::DS_TOTAL_MEMORY_recommendation($this->value, $this->idsadmin);
		break;
	    case "EXTSHMADD";
		$rcmd_array = onconfig::EXTSHMADD_recommendation($this->value, $this->idsadmin);
		break;
	    case "LISTEN_TIMEOUT";
		$rcmd_array = onconfig::LISTEN_TIMEOUT_recommendation($this->value,$this->idsadmin);
		break;
	    case "LOCKS";
		$rcmd_array = onconfig::LOCKS_recommendation($this->value,$this->idsadmin);
		break;
	    case "LOGBUFF";
		$rcmd_array = onconfig::LOGBUFF_recommendation($this->value,$this->idsadmin);
		break;
	    case "LTAPEBLK";
	    case "TAPEBLK";
		$rcmd_array = onconfig::tapeblk_recommnedation($this->name, $this->value, $this->idsadmin);
		break;
	    case "LTXHWM";
	    case "LTXEHWM";
		$rcmd_array = onconfig::LTXHWM_recommendation($this->name, $this->value, $this->idsadmin);
		break;
	    case "MULTIPROCESSOR";
		$rcmd_array = onconfig::MULTIPROCESSOR_recommendation($this->value, $this->idsadmin);
		break;
	    case "NUMCPUVPS";
		$rcmd_array = onconfig::NUMCPUVPS_recommendation($this->value, $this->idsadmin);
		break;		
	    case "PHYSBUFF";
		$rcmd_array = onconfig::PHYSBUFF_recommendation($this->value, $this->idsadmin);
		break;
	    case "SBSPACETEMP";
		$rcmd_array = onconfig::SBSPACETEMP_recommendation($this->value, $this->idsadmin);
		break;
	    case "SINGLE_CPU_VP";
		$rcmd_array = onconfig::SINGLE_CPU_VP_recommendation($this->value, $this->idsadmin);
		break;
	    case "SHMADD";
	    case "SHMVIRTSIZE";
		$rcmd_array = onconfig::SHM_recommendation($this->name, $this->value, $this->idsadmin);
		break;
	    case "SHMTOTAL";
		$rcmd_array = onconfig::SHMTOTAL_recommendation($this->value, $this->idsadmin);
		break;
	    case "TBLTBLFIRST";
	    case "TBLTBLNEXT";
		$rcmd_array = onconfig::TBLTBL_recommendation($this->name, $this->value, $this->idsadmin);
		break;
	    case "TEMPTAB_NOLOG";
		$rcmd_array = onconfig::TEMPTAB_NOLOG_recommendation($this->value, $this->idsadmin);
		break;
	}
	return $rcmd_array;
    }
	
    /*
     * This function checks whether the value of the onconfig parameter satisfies
     * OpenAdmin Tool's recommendation for that parameter.
     *
     * For some onconfig parameters, OAT will provide a function to get a 
     * recommendation for the setting of the parameter.  This function returns 
     * true if the current value complies with the recommendation,false if it
     * does not, and null if there is no recommendation. 
     */
    function checkRecommendation() {
	$rcmd_array = $this->parameterRecommendation();
	if (array_key_exists('compliance', $rcmd_array))
	{
	    return $rcmd_array['compliance'];
	} else {
	    // default is no recommendation, so return null
	    return null;
	}
     }
	
    /*
     * This function checks whether the value of the onconfig parameter satisfies
     * OpenAdmin Tool's recommendation for that parameter.  For the onconfig 
     * parameters for which OAT has recommendations for, this method will return
     * a string containing the recommendation.
     */
    function getRecommendation() {
	$rcmd_array = $this->parameterRecommendation();
	if (array_key_exists('recommendation', $rcmd_array))
	{
	    return $rcmd_array['recommendation'];
	} else {
	    return "";
	}
     }
	
} // end class
?>
