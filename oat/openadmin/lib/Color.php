<?php
/*
 **************************************************************************   
 *  (c) Copyright IBM Corporation. 2007.  All Rights Reserved
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

require_once ( "IllegalArgumentException.php" );

class Color
    {
    protected $rgb = array ( 'R' => 0 , 'G' => 0 , 'B' => 0 );

    public function __construct ( $r = 0
                                , $g = 0
                                , $b = 0 )
        {
        $this->rgb['R'] = $r;
        $this->rgb['G'] = $g;
        $this->rgb['B'] = $b;
        }

    public function rgb ( )
        {
        return array ( $this->rgb['R'], $this->rgb['G'], $this->rgb['B'] );
        }

    public function getRed ( )
        {
        return $this->rgb['R'];
        }

    public function getGreen ( )
        {
        return $this->rgb['G'];
        }

    public function getBlue ( )
        {
        return $this->rgb['B'];
        }
/*
    public static function toRGB ( $string )
        {
        $c = self::decode ( $string );
        return array ( $c->getRed(), $c->getGreen(), $c->getBlue() );
        }
*/

    public static function toRGB ( $value )
        {
        return array ( ( $value >> 16 ) & 0xff
                     , ( $value >>  8 ) & 0xff
                     , ( $value       ) & 0xff );
        }

    public static function toRGBA ( $value )
        {
        return array ( ( $value >> 24 ) & 0xff
                     , ( $value >> 16 ) & 0xff
                     , ( $value >>  8 ) & 0xff
                     , ( $value       ) & 0xff );
        }

    public static function decode ( $string )
        {
        $string = trim ( $string );
        if ( substr ( $string, 0, 1 ) == "#" )
            {
            $string = substr ( $string, 1 );
            switch ( strlen ( $string ) )
                {
                case 6:
                    $color = new Color ( hexdec ( substr ( $string, 0, 2 ) )
                                       , hexdec ( substr ( $string, 2, 2 ) )
                                       , hexdec ( substr ( $string, 4, 2 ) ) );
                    break; 

                default:
                    throw new IllegalArgumentException ( );
                }
            }
        else
            {
            throw new IllegalArgumentException ( );
            }

        return $color;
        }

    public static function encode ( Color $color ) 
        {
        return sprintf ( "%s%02x%02x%02x"
                       , "#" 
                       , $color->rgb['R'] 
                       , $color->rgb['G']
                       , $color->rgb['B'] );
        }

    public static function fade ( $color1
                                , $color2
                                , $steps )
        {
        if ( $steps <= 0 )
            {
            throw new IllegalArgumentException ( ) ;
            }

        $gradient = array 
                ( 
                'R' => ( $color1->rgb['R'] - $color2->rgb['R'] ) / ( $steps - 1 ),
                'G' => ( $color1->rgb['G'] - $color2->rgb['G'] ) / ( $steps - 1 ),
                'B' => ( $color1->rgb['B'] - $color2->rgb['B'] ) / ( $steps - 1 )
                );

        $colors = array();
        for ( $i = 0; $i < $steps; $i++ )
            {
            $colors[$i] = 
                new Color ( round ( $color1->rgb['R'] - ( $gradient['R'] * $i ) ) 
                          , round ( $color1->rgb['G'] - ( $gradient['G'] * $i ) ) 
                          , round ( $color1->rgb['B'] - ( $gradient['B'] * $i ) ) );
            }

        return $colors;
        }
    }

?>
