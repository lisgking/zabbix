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

/**
 * Manages localized messages for the web install process.
 */
class message 
{
	private $language;
	private $language_en = null;  // Fall back English messages
	
	function __construct($lang = "en_US")
	{
		$this->load_lang($lang);
	}
	
	/**
	 * Load the localized messages from the lang_install.xml file.
	 * 
	 * @param $l language
	 * @param $fallback - true/false, are we loading the English fall back messages?
	 */
	function load_lang($l, $fallback = false)
	{
		$lang = array();
		$fname = "../lang/$l/lang_install.xml";

		$xml = null;
		if ( file_exists($fname) )
		{
			$xml = simplexml_load_file( $fname );
		}

		if (! is_null($xml))
		{
			foreach ( $xml as $k )
			{
				$name = (string)$k->getName();
				
				if ($fallback)
				{
					$this->language_en[$name]=(string)$xml->$name;
				}
				else 
				{
					$this->language[$name]=(string)$xml->$name;
				}
			}
		}

		unset($xml);
	}
	
	/**
	 *  function get_message( $item, $parameters=array() )
	 *
	 *  Find the specified message in the correct language.
	 *  
	 *  If the current language is not English and we cannot
	 *  find the specified message, then look it up in English.
	 *  If it is not available in English, print out an error.
	 *
	 *  Optional parameters will get substituted for '{0}',
	 *  '{1}', '{2}', etc.
	 **/
	function lang($item, $parameters=array())
	{
		$item=trim($item);

		if ( $item == "" )
		{
			return ;
		}

		if( isset($this->language["{$item}"]) && !empty( $this->language["{$item}"] ) )
		{
			return $this->lang_substitute_parameters($this->language["{$item}"], $parameters);
		}

		// Default to English if no translated message exists!
		if (is_null($this->language_en))
		{
			$this->load_lang("en_US", true);
		}
		if( isset($this->language_en["{$item}"]) && !empty( $this->language_en["{$item}"] ) )
		{
			return $this->lang_substitute_parameters($this->language_en["{$item}"], $parameters);
		}
		
		/* If we can not find what we want print out what we are missing. */
		return "MISSING LANG FILE ITEM $item";
	} // end lang

	/**
	 *  function lang_substitute_parameters( $string, $parameters=array() )
	 *
	 *  Substitute parameters into our localized string.
	 *  Optional parameters will get substituted for '{0}', '{1}', '{2}', etc.
	 **/
	private function lang_substitute_parameters($string, $parameters=array())
	{
	    for ($i = 0; $i < count($parameters); $i++)
	    {
	        $replace = "/\\{" . $i . "\\}/";
	        $string = preg_replace($replace,$parameters[$i],$string);
	    }
	    return $string;

	} // end lang_substitute_parameters

	
}
?>
