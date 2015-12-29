<?php
/*
 **************************************************************************   
 *  (c) Copyright IBM Corporation. 2007, 2010.  All Rights Reserved
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


require_once "Color.php";
require_once "IllegalArgumentException.php";

class Diagram
    {
    protected $xmlParser;

    /**
     * Class constructor
     */

    public function __construct ( )
        {
        }

    /**
     * Class destructor
     */

    public function __destruct ( )
        {
        }

    public function render ( $xml, $file )
        {
        $tree = $this->parse ( $xml );
        $root = $this->compile ( $tree[0]['child'][0] );
        $root->render ( $file );
        }

    protected function parse ( $xml )
        {
        $this->xmlParser = new XMLParser ( );
        return $this->xmlParser->parse ( $xml );
        }

    protected function compile ( &$node )
        {
        return new Node ( $node );
        }
    }

class Node
    {
    /*
     * Static attribute defaults
     */

    const JUSTIFY_LEFT   = 1;
    const JUSTIFY_RIGHT  = 2;
    const JUSTIFY_CENTER = 3;

    protected static $defaults = array 
        (
          'BORDER_BACKGROUND' => array ( 0x000000, 0xffffff )
        , 'BORDER_FOREGROUND' => 0x000000
        , 'BORDER_THICKNESS'  => 1
        , 'DATA_BACKGROUND'   => 0xe4eaf2
        , 'DATA_FOREGROUND'   => 0x000000
        , 'DATA_FONT'         => 2
        , 'DATA_JUSTIFY'      => self::JUSTIFY_LEFT
        //, 'ICON'              => "../images/lightning.png"
        , 'ICON'              => null
        , 'LINK_BACKGROUND'   => array ( 0x000000, 0xffffff )
        , 'LINK_FOREGROUND'   => 0x000000
        , 'LINK_THICKNESS'    => 1
        , 'BOTTOM_MARGIN'     => 3 
        , 'LEFT_MARGIN'       => 3 
        , 'RIGHT_MARGIN'      => 3 
        , 'TOP_MARGIN'        => 3 
        , 'BOTTOM_PADDING'    => 10
        , 'LEFT_PADDING'      => 5
        , 'RIGHT_PADDING'     => 5
        , 'TOP_PADDING'       => 10
        , 'TITLE'             => "Node"
        , 'TITLE_BACKGROUND'  => array ( 0x5075b4, 0x00bfff )
        , 'TITLE_FOREGROUND'  => 0xffffff
        , 'TITLE_FONT'        => 2
        , 'TITLE_JUSTIFY'     => self::JUSTIFY_CENTER
        , 'MIN_NODE_WIDTH'    => 150
        , 'MIN_NODE_HEIGHT'   =>  50
        , 'MAX_NODE_WIDTH'    => 200
        , 'MAX_NODE_HEIGHT'   =>  50
        , 'BACKGROUND'        => 0xFFFFFF
        , 'FOREGROUND'        => 0x000000
        );
    
    protected $attributes = array ( );
    protected $children   = array ( );

    /**
     *
     * Constructor
     *
     */

    public function __construct ( &$node )
        {
        /*
         * Copy attributes and data
         */
        $this->attributes = $node['attr'];
        $this->setData ( $node['data'] );

        /*
         * Instantiate children
         */
        foreach ( $node['child'] as &$child )
            {
            $this->children[] = new Node ( $child );
            }
        }

    /**
     * 
     * Destructor
     *
     */

    public function __destruct ( )
        {
        }

    public function render ( $file )
        {
        /*
         * Get the dimensions for the tree, create an image,
         * and draw the background.
         */

        list ( $width, $height ) = $this->getTreeDimensions ( );
    
        $image = @imagecreatetruecolor ( $width , $height );
       
        if ( $image == null )
            {
                error_log("Image creation failed: width={$width} height={$height}");
                throw new Exception ( "Image creation failed" );
            }

        $this->renderBackground ( $image
                                , 0
                                , 0
                                , $width
                                , $height
                                , $this->getBackground ( ) );

        /*
         * Start rendering the tree from this node.
         */
		$horizontalLines = array();
		$verticalLines = array();
        $this->renderNode ( $image, 0, 0 , $this);
		$this->renderLinks ($image, $this,$horizontalLines, $verticalLines);

        /* 
         * Save the image
         */

        imagepng ( $image, $file );
        }
		
	protected function renderLinks (&$image, $root, &$horizontalLines, &$verticalLines)
        {

        $children = $this->getChildren ( );
		$children = array_reverse($children);
        foreach ( $children as &$child )
            {
            if($child->getLevel() >= $this->getLevel())
				{
				$child->renderLinks ( $image, $root, $horizontalLines, $verticalLines );
				}
            }

        $this->renderLink  ( $image, $root, $horizontalLines, $verticalLines );
        }

    protected function renderNode ( &$image, $x, $y)
        {
        /*
         * Get dimensions for the node, its subtree, and its zone 
         * (or envelope)
         */

        list ( $nodeWidth, $nodeHeight ) = $this->getNodeDimensions ( );
        list ( $treeWidth, $treeHeight ) = $this->getTreeDimensions ( );
        list ( $zoneWidth, $zoneHeight ) = $this->getZoneDimensions ( );

        /*
         * Calculate and store node coordinates.
         */

        $x1 = $x  + round ( ( $treeWidth - $nodeWidth ) / 2 );
        $x2 = $x1 + $nodeWidth;
        $y1 = $y  + $this->getTopPadding ( );
        $y2 = $y1 + $nodeHeight;

        $this->setCoordinates ( $x1, $y1, $x2, $y2 );
        
        /* 
         * Draw the node outline
         */

        $color = $this->createColor ( $image, $this->getBorderForeground ( ) );
        imagerectangle ( $image, $x1, $y1, $x2, $y2, $color );
        $this->destroyColor ( $image, $color );

        /*
         * Recurse and render next level in tree
         */

        $y += $zoneHeight;
        $children = $this->getChildren ( );
        foreach ( $children as &$child )
            {
            if($child->getLevel() >= $this->getLevel())
				{
				$child->renderNode ( $image, $x, $y );
	            list ( $treeWidth, $treeHeight ) = $child->getTreeDimensions ( );
	            $x += $treeWidth;
				}
            }
			
		$this->renderTitle ( $image );
        $this->renderData  ( $image );
        }

    protected function renderTitle ( &$image )
        {
        /*
         * Draw the title background
         */

        $border                     = $this->getBorderThickness ( );
        list ( $x1, $y1, $x2, $y2 ) = $this->getCoordinates ( );
        list ( $width, $height )    = $this->getTitleDimensions ( );

        $y2 = $y1 + $height + $border;

        $this->renderBackground ( $image
                                , $x1 + $border
                                , $y1 + $border
                                , $x2 - $border
                                , $y2
                                , $this->getTitleBackground ( ) );

        /*
         * Draw title / data separator
         */

        $color = $this->createColor ( $image
                                    , $this->getBorderForeground ( ) );
        imageline ( $image
                  , $x1
                  , $y2
                  , $x2
                  , $y2
                  , $color );

        $this->destroyColor ( $image, $color );

        $iconWidth  = 0;
        $iconHeight = 0;

        /*
         * Draw title icon. If the icon file cannot be located,
         * draw a 16x16 white rectangle instead.
         */

        $path = $this->getIcon ( );
        if ( isset ( $path ) )
            {
            $icon = @imagecreatefrompng ( $path );
            if ( ! $icon )
                {
                $icon  = imagecreatetruecolor ( 16, 16 );
                $color = $this->createColor ( $icon, 0xffffff );
                imagefilledrectangle ( $icon, 0, 0, 16, 16, $color );
                $this->destroyColor ( $icon, $color );
                }


            $iconWidth  = imagesx ( $icon );
            $iconHeight = imagesy ( $icon );

            imagecopy ( $image
                      , $icon
                      , $x1 + $this->getLeftMargin ( )
                      , $y1 + $this->getTopMargin ( )
                      , 0
                      , 0
                      , $iconWidth
                      , $iconHeight );

            $iconWidth += $this->getLeftMargin  ( )
                       +  $this->getRightMargin ( );
            }

        /*
         * Draw title text
         */

        $this->renderText ( $image
                          , $this->getTitleFont ( )
                          , $x1 + $this->getLeftMargin   ( )
                          , $y1 + $this->getTopMargin    ( )
                          , $x2 - $this->getRightMargin  ( )
                          //, $x1 - $this->getRightMargin  ( ) + $width
                          //, $y1 - $this->getBottomMargin ( ) + $height
                          , $y2 - $this->getBottomMargin ( )
                          , $this->getTitle ( )
                          , $this->getTitleForeground ( )
                          , $this->getTitleJustify ( ) );
        }

    protected function renderBackground ( &$image
                                        , $x1
                                        , $y1 
                                        , $x2
                                        , $y2
                                        , $color )
        {
        if ( is_array ( $color ) && count ( $color ) > 1 )
            {
            list ( $r, $g, $b ) = Color::toRGB ( $color[0] );
            $color1 = new Color ( $r, $g, $b );
            list ( $r, $g, $b ) = Color::toRGB ( $color[1] );
            $color2 = new Color ( $r, $g, $b );

            $colors = Color::fade ( $color1, $color2, $y2 - $y1 );
            for ( $i = 0; $i < ( $y2 - $y1 ); $i++ )
                {
                list ( $r, $g, $b ) = $colors[$i]->rgb ( );
                $color = imagecolorallocate ( $image, $r, $g, $b );

                imageline ( $image
                          , $x1
                          , $y1 + $i
                          , $x2
                          , $y1 + $i
                          , $color );
                }
            }
        else
            {
            $color = $this->createColor ( $image, $color );
            imagefilledrectangle ( $image, $x1, $y1, $x2, $y2, $color );
            }
        }

    protected function renderText ( &$image 
                                  , $font
                                  , $x1
                                  , $y1
                                  , $x2
                                  , $y2
                                  , &$text 
                                  , $color 
                                  , $justify = self::JUSTIFY_LEFT )
        {
        if ( isset ( $text ) )
            {
            $color = $this->createColor ( $image, $color );

            $lines = explode ( "\n", $text );

            foreach ( $lines as &$line )
                {
                $width  = imagefontwidth  ( $font ) * strlen ( $line );
                $height = imagefontheight ( $font );

                switch ( $justify )
                    {
                    case self::JUSTIFY_LEFT:
                        $x = $x1;
                        $y = $y1;
                        break;
                    case self::JUSTIFY_RIGHT:
                        $x = $x2 - $w;
                        $y = $y1;
                        break;
                    case self::JUSTIFY_CENTER:
                        $x = $x1 + round ( ( ( $x2 - $x1 ) - $width  ) / 2 );
                        $y = $y1 + round ( ( ( $y2 - $y1 ) - $height ) / 2 );
                        break;
                    default:
                        throw new IllegalArgumentException 
                            ( "Bad justification " );
                    }

                imagestring ( $image, $font, $x, $y, $line, $color );

                $y1 += $height;
                }

            $this->destroyColor ( $image, $color );
            }
        }

    protected function renderData ( &$image )
        {
        list ( $x1, $y1, $x2, $y2 )        = $this->getCoordinates     ( );
        list ( $titleWidth, $titleHeight ) = $this->getTitleDimensions ( );
       
        /*
         * Render data background. Adjust 'y1' with the height of the
         * title above the data
         */

        $border = $this->getBorderThickness ( );

        $y1 += $titleHeight + $border;

        $this->renderBackground ( $image
                                , $x1 + $border
                                , $y1 + $border
                                , $x2 - $border
                                , $y2 - $border
                                , $this->getDataBackground ( ) );

        $this->renderText ( $image
                          , $this->getDataFont ( )
                          , $x1 + $this->getLeftMargin   ( )
                          , $y1 + $this->getTopMargin    ( )
                          , $x2 - $this->getRightMargin  ( )
                          , $y2 - $this->getBottomMargin ( ) 
                          , $this->getData ( )
                          , $this->getDataForeground ( ) 
                          , $this->getDataJustify ( ) );
        }

    protected function renderLink ( &$image, $root, &$horizontalLines, &$verticalLines )
        {
        if ( $this->isParent ( ) )
            {
            list ( $x1, $y1, $x2, $y2 ) = $this->getCoordinates ( );
            $color = $this->createColor ( $image
                                        , $this->getLinkForeground ( ) );

            /*
             * Draw vertical line from parent
             */
			$line = array ($this->getCenterX ( ), $y2, $this->getCenterX ( ), $y2 + $this->getBottomPadding ( ));
			Node::drawAdjustedVerticalLine ($line, $horizontalLines, $verticalLines, $image, $color, false);
			
            /* 
             * Draw horizontal line spanning all children
             */
            $child = $this->getChildren ( );
            $count = count ( $child );
					  
			$line = array ($child[0]->getCenterX ( ), 
							$y2 + $this->getBottomPadding ( ) + $this->getBorderThickness ( ), 
							$child[$count - 1]->getCenterX ( ), 
							$y2 + $this->getBottomPadding ( ) + $this->getBorderThickness ( ));
			Node::drawAdjustedHorizontalLine ($line, $horizontalLines, $verticalLines, $image, $color, false);
  
            /*
             * Draw vertical line from each child
             */
            for ( $i = 0; $i < $count; $i++ )
                {
                //if the level of the child is higher, then draw normal line to it from the line that spans all children nodes
                if($child[$i]->getLevel() >= $this->getLevel())
					{						  
					$line = array($child[$i]->getCenterX ( ), $child[$i]->getY1 ( ) - $child[$i]->getTopPadding( ), $child[$i]->getCenterX ( ), $child[$i]->getY1 ( ));
					Node::drawAdjustedVerticalLine ($line, $horizontalLines, $verticalLines, $image, $color, false);
					}
				else //if the level of the child is lower, use a red line that goes up to the actual child
					{
					$actualChild = Node::searchNode($child[$i]->getID(), $root);//substitute of child[i]
					$color = 0x3333FF;//distinguish the pushdown lines with color blue.
					
					//draw horizontal line toward the actual child
					$line = array($this->getCenterX ( ), $this->getY2 ( ) + $this->getBottomPadding ( ), $actualChild->getX2() + 
										$actualChild->getRightPadding ( ), $this->getY2 ( ) + $this->getBottomPadding ( ));
					
					$hline = Node::drawAdjustedHorizontalLine ($line, $horizontalLines, $verticalLines, $image, $color);

					//draw a vertical line toward the actual node	  
					$line = array($actualChild->getX2() + $actualChild->getRightPadding ( ), $this->getY2 ( ) + $this->getBottomPadding ( ),
									$actualChild->getX2() + $actualChild->getRightPadding ( ), $actualChild->getCenterY() + $this->getBottomPadding ( ) );
									
					$vline = Node::drawAdjustedVerticalLine ($line, $horizontalLines, $verticalLines, $image, $color);
					
					$line = array($hline['X2'], $hline['Y2'], $vline['X2'], $hline['Y2']);
					Node::drawAdjustedVerticalLine ($line, $horizontalLines, $verticalLines, $image, $color, false);
					
					$line = array($vline['X2'], $hline['Y2'], $vline['X2'], $vline['Y2']);
					Node::drawAdjustedVerticalLine ($line, $horizontalLines, $verticalLines, $image, $color, false);
					
					//draw a small vertical line from the parent 
					$line = array ($this->getCenterX ( ), $y2, $this->getCenterX ( ), $hline['Y1']);
					Node::drawAdjustedVerticalLine ($line, $horizontalLines, $verticalLines, $image, $color, false);
					
					//draw a little horizontal line to the right of the actual right node
					$line = array($vline['X1'], $vline['Y1'], $actualChild->getX2(), $actualChild->getCenterY() + $actualChild->getBottomPadding ( ));
					Node::drawAdjustedHorizontalLine ($line, $horizontalLines, $verticalLines, $image, $color, false);
					}
                }

            $this->destroyColor ( $image, $color );
            }
        }

	private static function adjustVerticalLine($line, $verticalLines)
		{
		foreach ($verticalLines as $vline)
			{ 
			if(($line['X1'] < $vline['X1'] + 5) && ($line['X1'] > $vline['X1'] - 5) && (($line['Y1'] >= $vline['Y1'] && $line['Y1'] <= $vline['Y2']) || 
			($line['Y2'] <= $vline['Y2'] && $line['Y2'] >= $vline['Y1']) || ($line['Y1'] <= $vline['Y1'] && $line['Y2'] >= $vline['Y2'])))
				{
				$line['X1'] += 5;
				$line['X2'] += 5;
				$line = Node::adjustVerticalLine($line, $verticalLines);
				break;
				}
			}
			return $line;
		}
	
	private static function adjustHorizontalLine($line, $horizontalLines)
		{
		foreach ($horizontalLines as $hline)
			{
			if(($line['Y1'] < $hline['Y1'] + 5) && ($line['Y1'] > $hline['Y1'] - 5) && (($line['X1'] >= $hline['X1'] && $line['X1'] <= $hline['X2']) || 
			($line['X2'] <= $hline['X2'] && $line['X2'] >= $hline['X1']) || ($line['X1'] <= $hline['X1'] && $line['X2'] >= $hline['X2'])))
				{
				$line['Y1'] += 5;
				$line['Y2'] += 5;
				$line =  Node::adjustHorizontalLine($line, $horizontalLines);
				break;
				}
			}
			return $line;
		}
	
	private static function drawAdjustedHorizontalLine ($line, &$horizontalLines, &$verticalLines, &$image, $color, $adjust = true)
	{
		$line = Node::adjustLineCoordinates($line);
		$adjustedLine = ($adjust) ? Node::adjustHorizontalLine($line, $horizontalLines) : $line;
		imageline ($image, $adjustedLine['X1'], $adjustedLine['Y1'], $adjustedLine['X2'], $adjustedLine['Y2'], $color);
		$horizontalLines[] = $adjustedLine;
		return $adjustedLine;
		/*
		if($line['Y1'] != $adjustedLine['Y1'])
			{
			$adjustmentLine = array('X1' => $line['X1'], 'Y1' => $line['Y1'], 'X2' => $adjustedLine['X1'], 'Y2' => $adjustedLine['Y1']);
			imageline ($image, $adjustmentLine['X1'], $adjustmentLine['Y1'] , $adjustmentLine['X2'], $adjustmentLine['Y2'], $color );
			$verticalLines[] = $adjustmentLine;
			}*/
	}
	
	private static function drawAdjustedVerticalLine ($line, &$horizontalLines, &$verticalLines, &$image, $color, $adjust = true)
	{
		$line = Node::adjustLineCoordinates($line);
		$adjustedLine = ($adjust) ? Node::adjustVerticalLine($line, $verticalLines) : $line;
		imageline ($image, $adjustedLine['X1'], $adjustedLine['Y1'], $adjustedLine['X2'], $adjustedLine['Y2'], $color);
		$verticalLines[] = $adjustedLine;
		return $adjustedLine;
		/*
		if($line['X1'] != $adjustedLine['X1'])
			{
			$adjustmentLine = array('X1' => $line['X2'], 'Y1' => $line['Y2'], 'X2' => $adjustedLine['X2'], 'Y2' => $adjustedLine['Y2']);
			imageline ( $image, $adjustmentLine['X1'], $adjustmentLine['Y1'] , $adjustmentLine['X2'], $adjustmentLine['Y2'], $color );
			$horizontalLines[] = $adjustmentLine;
			}*/
	}
	
	/**
	 * We should follow a standard coordinate system. So say we have line (X1, Y1, X2, Y2), X1 must be <= X2, Y1 <= Y2.
	 * @return 
	 */
	private static function adjustLineCoordinates($line)
		{
		$line = array('X1' => $line[0], 'Y1' => $line[1], 'X2' => $line[2], 'Y2' => $line[3]);
		if($line['X1'] > $line['X2'] || $line['Y1'] > $line['Y2'])
			{
			$line = array('X1' => $line['X2'], 'Y1' => $line['Y2'], 'X2' => $line['X1'], 'Y2' => $line['Y1']);
			}
		return $line;
		}

    protected function createColor ( &$image, $color )
        {
        list ( $r, $g, $b ) = Color::toRGB ( $color );
        return imagecolorallocate ( $image, $r, $g, $b );
        }

    protected function destroyColor ( &$image, $color )
        {
        imagecolordeallocate ( $image, $color );
        }

    /**
     * Static functions to get/set defaults
     */

    public static function getDefaultBackground ( )
        {
        return self::$getDefault ( 'BACKGROUND' );
        }

    public static function setDefaultBackground ( $value )
        {
        self::$setDefault ( 'BACKGROUND', $value );
        }

    public static function getDefaultForeground ( )
        {
        return self::$getDefault ( 'FOREGROUND' );
        }

    public static function setDefaultForeground ( $value )
        {
        self::$setDefault ( 'FOREGROUND', $value );
        }

    public static function getDefaultBorderBackground ( )
        {
        return self::$getDefault ( 'BORDER_BACKGROUND' );
        }

    public static function setDefaultBorderBackground ( $value )
        {
        self::$setDefault ( 'BORDER_BACKGROUND', $value );
        }

    public static function getDefaultBorderForeground ( )
        {
        return self::$getDefault ( 'BORDER_FOREGROUND' );
        }

    public static function setDefaultBorderForeground ( $value )
        {
        self::$setDefault ( 'BORDER_FOREGROUND', $value );
        }

    public static function getDefaultBorderThickness ( )
        {
        return self::$getDefault ( 'BORDER_THICKNESS' );
        }

    public static function setDefaultBorderThickness ( $value )
        {
        self::$setDefault ( 'BORDER_THICKNESS', $value );
        }

    public static function getDefaultDataBackground ( )
        {
        return self::$getDefault ( 'DATA_BACKGROUND' );
        }

    public static function setDefaultDataBackground ( $value )
        {
        self::$setDefault ( 'DATA_BACKGROUND', $value );
        }

    public static function getDefaultDataForeground ( )
        {
        return self::$getDefault ( 'DATA_FOREGROUND' );
        }

    public static function setDefaultDataForeground ( $value )
        {
        self::$setDefault ( 'DATA_FOREGROUND', $value );
        }
   
    public static function getDefaultDataFont ( )
        {
        return self::$getDefault ( 'DATA_FONT' );
        }

    public static function setDefaultDataFont ( $font )
        {
        self::$setDefault ( 'DATA_FONT', $font );
        }

    public static function getDefaultDataJustify ( )
        {
        return self::$getDefault ( 'DATA_JUSTIFY' );
        }

    public static function setDefaultDataJustify ( $justify )
        {
        self::$setDefault ( 'DATA_JUSTIFY', $justify );
        }

    public static function getDefaultLinkBackground ( )
        {
        return self::$getDefault ( 'LINK_BACKGROUND' );
        }

    public static function setDefaultLinkBackground ( $value )
        {
        self::$setDefault ( 'LINK_BACKGROUND', $value );
        }

    public static function getDefaultLinkForeground ( )
        {
        return self::$getDefault ( 'LINK_FOREGROUND' );
        }

    public static function setDefaultLinkForeground ( $value )
        {
        self::$setDefault ( 'LINK_FOREGROUND', $value );
        }

    public static function getDefaultLinkThickness ( )
        {
        return self::$getDefault ( 'LINK_THICKNESS' );
        }

    public static function setDefaultLinkThickness ( $value )
        {
        self::$setDefault ( 'LINK_THICKNESS', $value );
        }

    public static function getDefaultTitleBackground ( )
        {
        return self::$getDefault ( 'TITLE_BACKGROUND' );
        }

    public static function setDefaultTitleBackground ( $value )
        {
        self::$setDefault ( 'TITLE_BACKGROUND', $value );
        }

    public static function getDefaultTitleForeground ( )
        {
        return self::$getDefault ( 'TITLE_FOREGROUND' );
        }

    public static function setDefaultTitleForeground ( $value )
        {
        self::$setDefault ( 'TITLE_FOREGROUND', $value );
        }

    public static function getDefaultTitleFont ( )
        {
        return self::$getDefault ( 'TITLE_FONT' );
        }

    public static function setDefaultTitleFont ( $font )
        {
        self::$setDefault ( 'TITLE_FONT', $font );
        }

    public static function getDefaultTitleJustify ( )
        {
        return self::$getDefault ( 'TITLE_JUSTIFY' );
        }

    public static function setDefaultTitleJustify ( $justify )
        {
        self::$setDefault ( 'TITLE_JUSTIFY', $justify );
        }

    public static function getDefaultMaximumNodeWidth ( )
        {
        return self::$getDefault ( 'MAX_NODE_WIDTH' );
        }

    public static function setDefaultMaximumNodeWidth ( $width )
        {
        self::$setDefault ( 'MAX_NODE_WIDTH', $width );
        }

    public static function getDefaultMinimumNodeWidth ( )
        {
        return self::$getDefault ( 'MIN_NODE_WIDTH' );
        }

    public static function setDefaultMinimumNodeWidth ( $width )
        {
        self::$setDefault ( 'MIN_NODE_WIDTH', $width );
        }

    public static function getDefaultTopPadding ( )
        {
        return self::$getDefault ( 'TOP_PADDING' );
        }

    public static function setDefaultTopPadding ( $value )
        {
        self::$setDefault ( 'TOP_PADDING', $value );
        }

    public static function getDefaultBottomPadding ( )
        {
        return self::$getDefault ( 'BOTTOM_PADDING' );
        }
       
    public static function setDefaultBottomPadding ( $value )
        {
        self::$setDefault ( 'BOTTOM_PADDING', $value );
        }

    public static function getDefaultLeftPadding ( )
        {
        return self::$getDefault ( 'LEFT_PADDING' );
        }
       
    public static function setDefaultLeftPadding ( $value )
        {
        self::$setDefault ( 'LEFT_PADDING', $value );
        }

    public static function getDefaultRightPadding ( )
        {
        return self::$getDefault ( 'RIGHT_PADDING' );
        }
       
    public static function setDefaultRightPadding ( $value )
        {
        self::$setDefault ( 'RIGHT_PADDING', $value );
        }

    public static function getDefaultTopMargin ( )
        {
        return self::$getDefault ( 'TOP_MARGIN' );
        }

    public static function setDefaultTopMargin ( $value )
        {
        self::$setDefault ( 'TOP_MARGIN', $value );
        }

    public static function getDefaultBottomMargin ( )
        {
        return self::$getDefault ( 'BOTTOM_MARGIN' );
        }
       
    public static function setDefaultBottomMargin ( $value )
        {
        self::$setDefault ( 'BOTTOM_MARGIN', $value );
        }

    public static function getDefaultLeftMargin ( )
        {
        return self::$getDefault ( 'LEFT_MARGIN' );
        }
       
    protected static function setDefaultLeftMargin ( $value )
        {
        self::$setDefault ( 'LEFT_MARGIN', $value );
        }

    public static function getDefaultRightMargin ( )
        {
        return self::$getDefault ( 'RIGHT_MARGIN' );
        }
       
    public static function setDefaultRightMargin ( $value )
        {
        self::$setDefault ( 'RIGHT_MARGIN', $value );
        }

    protected static function getDefault ( $key )
        {
        /*
        if ( ! isset ( self::$defaults [ $key ] ) )
            {
            throw new Exception ( "No such key [ " . $key . " ]" );
            }
        */
        
        return self::$defaults [ $key ];
        }

    protected static function setDefault ( $key, $value )
        {
        // TODO: Add basic error checking (null keys or values)
        if ( ! isset ( $key ) )
            {
            throw new Exception ( "Key is not set" );
            }

        if ( ! isset ( self::$defaults [ $key ] ) )
            {
            throw new Exception ( "Key has no associated value" );
            }
     
        self::$default [ $key ] = $value;
        }

    /*
     * Attribute getters & setters
     */

    public function getBackground ( )
        {
        return $this->getAttribute ( 'BACKGROUND' );
        }

    public function setBackground ( $color )
        {
        $this->attribute['BACKGROUND'] = $color;
        }

    public function getForeground ( )
        {
        return $this->getAttribute ( 'FOREGROUND' );
        }

    public function setForeground ( $color )
        {
        $this->attribute['FOREGROUND'] = $color;
        }

    public function getBorderBackground ( )
        {
        return $this->getAttribute ( 'BORDER_BACKGROUND' );
        }

    public function setBorderBackground ( $color )
        {
        $this->attribute['BORDER_BACKGROUND'] = $color;
        }

    public function getBorderForeground ( )
        {
        return $this->getAttribute ( 'BORDER_FOREGROUND' );
        }

    public function setBorderForeground ( $color )
        {
        $this->attribute['BORDER_FOREGROUND'] = $color;
        }

    public function getBorderThickness ( )
        {
        return $this->getAttribute ( 'BORDER_THICKNESS' );
        }

    public function setBorderThickness ( $thickness )
        {
        $this->attributes['BORDER_THICKNESS'] = $thickness;
        }

    public function getDataBackground ( )
        {
        return $this->getAttribute ( 'DATA_BACKGROUND' );
        }

    public function setDataBackground ( $color )
        {
        $this->attributes['DATA_BACKGROUND'] = $color;
        }

    public function getDataForeground ( )
        {
        return $this->getAttribute ( 'DATA_FOREGROUND' );
        }

    public function setDataForeground ( $color )
        {
        $this->attributes['DATA_FOREGROUND'] = $color;
        }

    public function getDataFont ( )
        {
        return $this->getAttribute ( 'DATA_FONT' );
        }

    public function setDataFont ( $font )
        {
        $this->attributes['DATA_FONT'] = $font;
        }

    public function getDataJustify ( )
        {
        return $this->getAttribute ( 'DATA_JUSTIFY' );
        }

    public function setDataJustify ( $justify )
        {
        $this->attributes['DATA_JUSTIFY'] = $justify;
        }

    public function getIcon ( )
        {
        return $this->getAttribute ( 'ICON' );
        }

    public function setIcon ( $icon )
        {
        $this->attributes['ICON'] = $icon;
        }

    public function getLinkBackground ( )
        {
        return $this->getAttribute ( 'LINK_BACKGROUND' );
        }

    public function setLinkBackground ( $color )
        {
        $this->attributes['LINK_BACKGROUND'] = $color;
        }

    public function getLinkForeground ( )
        {
        return $this->getAttribute ( 'LINK_FOREGROUND' );
        }

    public function setLinkForeground ( $color )
        {
        $this->attributes['LINK_BACKGROUND'] = $color;
        }

    public function getLinkThickness ( )
        {
        return $this->getAttribute ( 'LINK_THICKNESS' );
        }

    public function setLinkThickness ( $thickness )
        {
        $this->attributes['LINK_THICKNESS'] = $thickness;
        }

    public function getTitle ( )
        {
        return $this->getAttribute ( 'TITLE' );
        }
		
	public function setTitle ( $title )
        {
        return $this->attributes['TITLE'] = $title;
        }

	public function getLevel ( )
        {
        return $this->getAttribute ( 'LEVEL' );
        }
		
	public function setLevel ( $level )
        {
        return $this->attributes['LEVEL'] = $level;
        }

    public function getTitleBackground ( )
        {
        return $this->getAttribute ( 'TITLE_BACKGROUND' );
        }

    public function setTitleBackground ( $color )
        {
        return $this->attributes['TITLE_BACKGROUND'] = $color;
        }

    public function getTitleForeground ( )
        {
        return $this->getAttribute ( 'TITLE_FOREGROUND' );
        }

    public function setTitleForeground ( $color )
        {
        return $this->attributes['TITLE_FOREGROUND'] = $color;
        }

    public function getTitleFont ( )
        {
        return $this->getAttribute ( 'TITLE_FONT' );
        }

    public function setTitleFont ( $font )
        {
        $this->attributes['TITLE_FONT'] = $font;
        }

    public function getTitleJustify ( )
        {
        return $this->getAttribute ( 'TITLE_JUSTIFY' );
        }

    public function setTitleJustify ( $justify )
        {
        $this->attributes['TITLE_JUSTIFY'] = $justify;
        }

    public function getMaximumNodeWidth ( )
        {
        return $this->getAttribute ( 'MAX_NODE_WIDTH' );
        }

    public function setMaximumNodeWidth ( $width )
        {
        $this->attributes['MAX_NODE_WIDTH'] = $width;
        }

    public function getMinimumNodeWidth ( )
        {
        return $this->getAttribute ( 'MIN_NODE_WIDTH' );
        }

    public function setMinimumNodeWidth ( $width )
        {
        $this->attributes['MIN_NODE_WIDTH'] = $width;
        }

    public function getMaximumNodeHeight ( )
        {
        return $this->getAttribute ( 'MAX_NODE_HEIGHT' );
        }

    public function setMaximumNodeHeight ( $height )
        {
        $this->attributes['MAX_NODE_HEIGHT'] = $height;
        }

    public function getMinimumNodeHeight ( )
        {
        return $this->getAttribute ( 'MIN_NODE_HEIGHT' );
        }

    public function setMinimumNodeHeight ( $height )
        {
        $this->attributes['MIN_NODE_HEIGHT'] = $height;
        }

    public function getBottomMargin ( )
        {
        return $this->getAttribute ( 'BOTTOM_MARGIN' );
        }

    public function setBottomMargin ( $margin )
        {
        $this->attributes['BOTTOM_MARGIN'] = $margin;
        }

    public function getLeftMargin ( )
        {
        return $this->getAttribute ( 'LEFT_MARGIN' );
        }

    public function setLeftMargin ( $margin )
        {
        $this->attributes['LEFT_MARGIN'] = $margin;
        }

    public function getRightMargin ( )
        {
        return $this->getAttribute ( 'RIGHT_MARGIN' );
        }

    public function setRightMargin ( $margin )
        {
        $this->attributes['RIGHT_MARGIN'] = $margin;
        }

    public function getTopMargin ( )
        { 
        return $this->getAttribute ( 'TOP_MARGIN' );
        }

    public function setTopMargin ( $margin )
        {
        $this->attributes['TOP_MARGIN'] = $margin;
        }

    public function getBottomPadding ( )
        {
        return $this->getAttribute ( 'BOTTOM_PADDING' );
        }

    public function setBottomPadding ( $padding )
        {
        $this->attributes['BOTTOM_PADDING'] = $padding;
        }

    public function getLeftPadding ( )
        {
        return $this->getAttribute ( 'LEFT_PADDING' );
        }

    public function setLeftPadding ( $padding )
        {
        $this->attributes['LEFT_PADDING'] = $padding;
        }

    public function getRightPadding ( )
        {
        return $this->getAttribute ( 'RIGHT_PADDING' );
        }

    public function setRightPadding ( $padding )
        {
        $this->attributes['RIGHT_PADDING'] = $padding;
        }

    public function getTopPadding ( )
        { 
        return $this->getAttribute ( 'TOP_PADDING' );
        }

    public function setTopPadding ( $padding )
        {
        $this->attributes['TOP_PADDING'] = $padding;
        }

    protected function getX1 ( )
        {
        return $this->attributes['X1'];
        }

    protected function setX1 ( $x1 )
        {
        $this->attributes['X1'] = $x1;
        }

    protected function getX2 ( )
        {
        return $this->attributes['X2'];
        }

    protected function setX2 ( $x2 )
        {
        $this->attributes['X2'] = $x2;
        }

    protected function getY1 ( )
        {
        return $this->attributes['Y1'];
        }

    protected function setY1 ( $y1 )
        {
        $this->attributes['Y1'] = $y1;
        }

    protected function getY2 ( )
        {
        return $this->attributes['Y2'];
        }

    protected function setY2 ( $y2 )
        {
        $this->attributes['Y2'] = $y2;
        }

    protected function getCenterX ( )
        {
        list ( $x1, $y1, $x2, $y2 ) = $this->getCoordinates ( );
        return $x1 + round ( ( $x2 - $x1 ) / 2 );
        }

    protected function getCenterY ( )
        {
        list ( $x1, $y1, $x2, $y2 ) = $this->getCoordinates ( );
        return $y1 + round ( ( $y2 - $y1 ) / 2 );
        }

    protected function getCoordinates ( )
        {
        return array ( $this->getX1 ( )
                     , $this->getY1 ( )
                     , $this->getX2 ( )
                     , $this->getY2 ( ) );
        }

    protected function setCoordinates ( $x1, $y1, $x2, $y2 )
        {
        $this->setX1 ( $x1 );
        $this->setY1 ( $y1 );
        $this->setX2 ( $x2 );
        $this->setY2 ( $y2 );
        }

    protected function getAttribute ( $key )
        {
        if ( ! isset ( $this->attributes[$key] ) )
            {
            $this->attributes[$key] = $this->getDefault ( $key );
            }

        return $this->attributes[$key];
        }

    protected function getDataDimensions ( )
        {
        list ( $width, $height ) = 
            $this->getTextDimensions ( $this->getData     ( )
                                     , $this->getDataFont ( ) );

        $width  += $this->getLeftMargin ( ) + $this->getRightMargin  ( );
        $height += $this->getTopMargin  ( ) + $this->getBottomMargin ( );

        $width  = max ( $width , $this->getMinimumNodeWidth  ( ) );
        //$height = max ( $height, $this->getMinimumNodeHeight ( ) );

        return array ( $width, $height );
        }

    protected function getTitleDimensions ( )
        {
        /*
         * Calculate title text dimensions
         */
        list ( $width, $height ) = 
            $this->getTextDimensions ( $this->getTitle     ( )
                                     , $this->getTitleFont ( ) );

        /*
         * Include icon dimensions if necessary
         */
        $icon = $this->getIcon ( );
        if ( isset ( $icon ) && file_exists ( $icon ) )
            {
            list ( $iconWidth, $iconHeight ) = getimagesize ( $icon );
            $width += $iconWidth
                   +  $this->getLeftMargin  ( )
                   +  $this->getRightMargin ( );

            $height = max ( $height, $iconHeight );
            }

        /*
         * Allow for margins
         */
        $width  += $this->getLeftMargin ( ) 
                +  $this->getRightMargin  ( );

        $height += $this->getTopMargin  ( ) 
                +  $this->getBottomMargin ( )
                +  $this->getBorderThickness ( );

        $width  = max ( $width , $this->getMinimumNodeWidth  ( ) );
        //$height = max ( $height, $this->getMinimumNodeHeight ( ) );

        return array ( $width, $height );
        }

    protected function getNodeDimensions ( )
        {
        $width  = 0;
        $height = 0;

        list ( $titleWidth, $titleHeight ) = $this->getTitleDimensions ( );
        list ( $dataWidth , $dataHeight  ) = $this->getDataDimensions  ( );

        $width  = max ( $titleWidth, $dataWidth );
        $height = $titleHeight + $dataHeight;

        return array ( $width, $height );
        }

    protected function getTreeDimensions ( )
        {
        $width  = 0;
        $height = 0;

        list ( $zoneWidth, $zoneHeight ) = $this->getZoneDimensions ( );

        if ( $this->isParent ( ) )
            {
            $children = $this->getChildren ( );
            for ( $i = 0; $i < count ( $children ); $i++ )
                {
                $child = &$children[$i];
                list ( $childWidth, $childHeight ) = 
                    $child->getTreeDimensions ( );

                $width += $childWidth;
                $height = max ( $height, ( $zoneHeight + $childHeight ) );
                }

            return array ( $width, $height );
            }
        else
            {
            $width  = $zoneWidth;
            $height = $zoneHeight;
            }

        return array ( $width, $height );
        }

    protected function getZoneDimensions ( )
        {
        list ( $width, $height ) = $this->getNodeDimensions ( );

        $width = $width
               + $this->getLeftPadding ( )
               + $this->getRightPadding ( )
               + $this->getBorderThickness ( ) * 2;

        $height = $height
                + $this->getTopPadding ( )
                + $this->getBottomPadding ( )
                + $this->getBorderThickness ( ) * 2;

        return array ( $width, $height );
        }

    protected function getLineCount ( &$text )
        {
        return count ( explode ( "\n", $text ) );
        }

    protected function getFontDimensions ( $font )
        {
        return array ( imagefontwidth ( $font ), imagefontheight ( $font ) );
        }

    protected function getTextDimensions ( &$text, $font )
        {
        $width  = 0;
        $height = 0;

        if ( isset ( $text ) )
            {
            $lines = explode ( "\n", $text );
            foreach ( $lines as &$line )
                {
                $height++;
                $width = max ( $width, strlen ( $line ) );
                }

            $width  *= imagefontwidth  ( $font );
            $height *= imagefontheight ( $font );
            }

        return array ( $width, $height );
        }

    public function getData ( ) { return $this->data; }
    public function setData ( $d ) { $this->data = $d; }

    public function &getChildren( )    { return $this->children; }

    public function isParent ( )
        {
        return ( isset ( $this->children ) && count ( $this->children ) > 0 );
        }

	public function getID () 
	{
		$dummy = explode(".", $this->getTitle());
		return $dummy[0];
	}
	
	public function isEmpty () 
	{
		return $this->getAttribute ( 'EMPTY' );
	}
		
	public static function searchNode ($nodeID, $root)
		{
		if($root->getID() == $nodeID && !$root->isEmpty())//ignore empty nodes
			{
			return $root;
			}
		else if(!$root->isParent())
			{
			return null;
			}
		else
			{
			$children = $root->getChildren();
			foreach($children as $child)
				{
				$foundNode = Node::searchNode($nodeID, $child);
				if($foundNode != null)
					{
					return $foundNode;
					}
				}
			}
		return null;
		}
		
    }

class XMLParser 
    {
    protected $parser;
    protected $node;
    protected $tree;

    public function __construct ( )
        {
        $this->parser = xml_parser_create ( );
        xml_set_object ( $this->parser, $this );
        xml_set_element_handler ( $this->parser
                                , "startElementHandler"
                                , "endElementHandler" );
        xml_set_character_data_handler ( $this->parser 
                                       , "characterDataHandler" );

        $this->tree = array ( );
        $this->node = array ( &$this->tree );
        }

    public function parse ( $xml )
        {
        if ( ! xml_parse ( $this->parser, $xml ) )
            {
            $message = sprintf ( "Parse error at line %d: %s"
                               , $this->getLineNumber ( )
                               , $this->getErrorMessage ( ) );

            throw new XMLParserException ( $message );
            }

        xml_parser_free ( $this->parser );
        return $this->tree;
        }

    protected function startElementHandler ( $parser
                                           , $element
                                           , $attributes )
        {
        $i = count ( $this->node ) - 1;
        $this->node [$i][] = array ( 'elem'  => $element
                                   , 'attr'  => $attributes
                                   , 'data'  => ''
                                   , 'child' => array ( ) );

        $j = count ( $this->node [$i] ) - 1;
        $this->node [ ] = &$this->node [$i][$j]['child'];
        }

    protected function endElementHandler ( $parser
                                         , $element )
        {
        array_pop ( $this->node );
        }

    protected function characterDataHandler ( $parser
                                            , $data )
        {
        if ( strlen ( ltrim ( $data ) ) > 0 ) 
            {
            $i = count ( $this->node ) - 2;
            $j = count ( $this->node [ $i ] ) - 1;

            $this->node [ $i ][ $j ][ 'data' ] .= 
                str_replace ( '\n' , "\n" , trim ( $data ) );
            }
        }

    protected function getLineNumber ( )
        {
        return xml_get_current_line_number ( $this->parser );
        }

    protected function getErrorMessage ( )
        {
        return xml_error_string ( xml_get_error_code ( $this->parser ) );
        }
    }

class XMLParserException
    extends Exception
    {
    public function __construct ( $message = null
                                , $code    = 0 )
        {
        parent::__construct ( $message
                            , $code );
        }
    }
?>
