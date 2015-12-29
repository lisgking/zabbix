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

final class Version
{
	private $major = null;
	private $minor = null;
	private $os    = null;
	private $level = null;
	
	public function __construct ( $version )
	{
		if ( !is_string ( $version ) )
		{
			throw new Exception ( 'Argument #1 to __construct() must be of type String' );	
		}
			
		$this->parse ( $version );	
		
	}
		
	public function compareTo ( $that )
	{
		if ( !$that instanceof Version )
		{
			if ( is_string ( $that ) )
			{
				$that = new Version ( $that );	
			}
			else
			{
				throw new Exception ( 'Argument #1 to compareTo() must be of type Version or String' );	
			}
		}
		
		/*
		 * Note that the comparison does not evaluate the OS component
		 * of the version, i.e. version 11.10.UC1 and 11.10.FC1 are 
		 * considered equal. Also, any patch level is ignored, i.e.
		 * 11.10.UC1 and 11.10.UC1X1 are considered equal.
		 */
			
		$rc = $this->major - $that->major;
		if ( $rc == 0 )
		{
			$rc = $this->minor - $that->minor;
			if ( $rc == 0 )	
			{
				$rc = strcmp ( substr ( $this->level, 0, 2 )
			    	         , substr ( $that->level, 0, 2 ) );
			}
		}
			
		return $rc;
	}
		
	private function parse ( $version )
	{
		/*
		 * We can handle the entire string returned by DBINFO('version', 'full'), e.g.
		 * "IBM Informix Dynamic Server Version 11.10.FC1". First, we remove everything
		 * up to the version number.
		 * Next, we break down the version number into major, minor, os and level.
		 */
		$version = strpbrk ( trim ( $version ), "1234567890" );
		$pieces  = explode ( '.', $version );
		
		if ( isset ( $pieces [ 0 ] ) && preg_match ( "/^\b([0-9]{1,2})\b/", $pieces [ 0 ] ) )
		{
			$this->major = $pieces [ 0 ];
		}
		else
		{
			throw new Exception ( "Invalid major version number \"" . $pieces [ 0 ] . "\" in \"" . $version . "\"" );
		}

		if ( isset ( $pieces [ 1 ] ) && preg_match ( "/^\b([0-9]{2})\b/", $pieces [ 1 ] ) )
		{	
			$this->minor = $pieces [ 1 ];
		}
		else
		{
			throw new Exception ( "Invalid minor version number \"" . $pieces [ 1 ] . "\" in \"" . $version . "\"" );
		}
			
		/*
		 * Part 3 of the version string could be empty, a single 'U', 'F' or 'T',
		 * or a fully qualified 'UC1', 'FD2', or 'TE3X1'.
		 */
			
		if ( isset ( $pieces [ 2 ] ) )
		{
			$length = strlen ( $pieces [ 2 ] );
			
			switch ( $length )
			{
				/*
				 * We have the OS part ('U', 'F' or 'T')
				 * Default to 'C1' for the level
				 */
				case 1:
					$this->os    = substr ( $pieces [ 2 ], 0, 1 );
					$this->level = 'C1';
					break;
				/*
				 * We have the OS part and the first character of the level ('C', 'D', 'E', etc).
				 * Default to '1' as the level interim.
				 */
				case 2:					
					$this->os    = substr ( $pieces [ 2 ], 0, 1 );
					$this->level = substr ( $pieces [ 2 ], 1, 1 ) . '1';
					break;
				/*
				 * We have 3 or more characters ('UC2', 'UC3X4', etc).
				 */
				default:
					$this->os    = substr ( $pieces [ 2 ], 0, 1 );
					$this->level = substr ( $pieces [ 2 ], 1    );
					break;
			}
		}
		else
		{
			/*
			 * We got nothing for OS or level. Default to 'UC1'.
			 */
			$this->os    = 'U';
			$this->level = 'C1';
		}
		
	}
		
	public function toString ( )
	{
		return '' 
		     . $this->major 
		     . '.'	
		     . $this->minor
		     . '.'
		     . $this->os
		     . $this->level;
	}
}

?>
