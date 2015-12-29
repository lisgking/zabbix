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

require_once ( 'version.php' );

final class Feature
{
    const CHEETAH       =  'CHEETAH';
    const CHEETAH_UC2   =  'CHEETAH_UC2';
    const CHEETAH2      =  'CHEETAH2';
    const CHEETAH2_UC3  =  'CHEETAH2_UC3';
    const CHEETAH2_UC4  =  'CHEETAH2_UC4';
    const CHEETAH2_UC5  =  'CHEETAH2_UC5';
    const CHEETAH2_UC6  =  'CHEETAH2_UC6';
    const CHEETAH2_UC7  =  'CHEETAH2_UC7';
    const CHEETAH2_UC8  =  'CHEETAH2_UC8';
    const CHEETAH2_UC9  =  'CHEETAH2_UC9';
    const PANTHER       =  'PANTHER';
    const PANTHER_UC2   =  'PANTHER_UC2';
    const PANTHER_UC3   =  'PANTHER_UC3';
    const PANTHER_UC4   =  'PANTHER_UC4';
    const CENTAURUS     =  'CENTAURUS';

    private static $featureSet = array (
                                         self::CHEETAH      =>  '11.10.UC1'
                                       , self::CHEETAH_UC2  =>  '11.10.UC2'
                                       , self::CHEETAH2     =>  '11.50.UA1'
                                       , self::CHEETAH2_UC3 =>  '11.50.UC3'
                                       , self::CHEETAH2_UC4 =>  '11.50.UC4'
                                       , self::CHEETAH2_UC5 =>  '11.50.UC5'
                                       , self::CHEETAH2_UC6 =>  '11.50.UC6'
                                       , self::CHEETAH2_UC7 =>  '11.50.UC7'
                                       , self::CHEETAH2_UC8 =>  '11.50.UC8'
                                       , self::CHEETAH2_UC9 =>  '11.50.UC9'
                                       , self::PANTHER      =>  '11.70.UC1'
                                       , self::PANTHER_UC2  =>  '11.70.UC2'
                                       , self::PANTHER_UC3  =>  '11.70.UC3'
                                       , self::PANTHER_UC4  =>  '11.70.UC4'
                                       , self::CENTAURUS    =>  '12.10.UC1'
	                                   );

    public static function getVersion ( $feature )
    {
        $version = self::$featureSet [ $feature ];
        return ( isset ( $version ) ? $version : null );
    }

    public static function isAvailable ( $feature, $version )
    {
        if ( $version instanceof IDSAdmin ) 
        {
        	$version = $version->phpsession->serverInfo->getVersion();
        }
        
    	if ( ! $version instanceof Version )
        {
            if ( is_string ( $version ) )
            {
                $version = new Version ( $version );
            }
            else
            {
                throw new Exception ( 'Argument #2 must be of type Version or String' );
            }
        }
        	
        $v = self::$featureSet [ $feature ];
        return ( isset ( $v ) && $version->compareTo ( $v ) >= 0 );
    }
    
    /*
     * We are going to tuse isAvailable in its place, because
     *  the function isAvailable can handle overloading
     
    public static function exists( $feature, $idsadmin ) {
    	return Feature::isAvailable ( $feature, 
    			$idsadmin->phpsession->serverInfo->getVersion()  );
    	
    }
    *****/
}
?>
