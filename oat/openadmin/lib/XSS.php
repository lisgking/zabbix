<?php

/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2011.  All Rights Reserved
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


class XSS
{
	public static function is_htmlspecialchars_and_stripslashes_supported()
	{
		return version_compare(PHP_VERSION, '4.0.0') >= 0;
	}
	
	/**
	 * This funtion reads input and tests whether it is safe. It looks for html characters that are used to inject scritps.
	 * If it finds any unsafe characters, it replaces the input with NULL. However, the user can choose to sanitize the 
	 * input and display it as sanitized by setting prohibit_bad_data to false. 
	 * @return 
	 * @param object $input_data the data that is to be sanitized
	 * @param object $prohibit_bad_data[optional] if we are suspecious about the content we can choose to remove them completely instead of encoding them
	 */
	public static function sanitize_data($input_data,$prohibit_bad_data=true) 
	{
		$sanitized_value = $input_data;
		if (!is_null($input_data ) && XSS::is_htmlspecialchars_and_stripslashes_supported())
		{
  			$sanitized_value = htmlspecialchars(stripslashes($input_data), ENT_QUOTES, "UTF-8");
			if($prohibit_bad_data && strcmp($sanitized_value, $input_data) != 0)
			{
				$sanitized_value = null;
			}
		}

		return $sanitized_value;
	}

	
}

?>