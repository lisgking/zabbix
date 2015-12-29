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



class Charts {

    public  $idsadmin;
    private $select;
    private $dbname;
    private $type;
    private $legendDir;
    private $title;
    private $showZoom = false;
    private $dataTitles;
    private $units = "";
    private $width="100%";
    private $height="100%";
    private $data = array();
    private $id = "gengraph";
	private $barType;

    /**
     * constructor
     *
     * @param idsadmin object
     * @return Charts object
     */
    function __construct(&$idsadmin)
    {
        $this->idsadmin = $idsadmin;
        $this->setId("genGraph");
    }

    /**
     * Set database name
     *
     * @param database name
     */
    function setDbname($dbname="")
    {
        $this->dbname=$dbname;
    }

    /**
     * Set the select statement
     *
     * @param sql
     */
    function setSelect($sql="")
    {
        $this->select = $sql;
    }

    /**
     * Set the type of the graph.
     *
     * @param type - valid values are "PIE", "LINE", or "BAR"
     */
    function setType($type="")
    {
        $this->type=$type;
    }

    /**
     * Set the direction of legend.
     *
     * @param direction - valid values are "horizontal" or "vertical"
     */
    function setLegendDir($dir="horizontal")
    {
        $this->legendDir = $dir ;
    }

    /**
     * Set chart title
     *
     * @param title
     */
    function setTitle($title="")
    {
        $this->title=$title;
    }

	/**
     * Set whether the zoom is shown.
     * Only applicable to LINE graphs.
     *
     * @param boolean show
     */
    function setShowZoom($show=true)
    {
        $this->showZoom=$show;
    }

    /**
     * Set data titles
     *
     * @param array of titles
     */
    function setDataTitles($title=array())
    {
        $this->dataTitles=$title;
    }

    /**
     * Set units label
     *
     * @param units
     */
    function setUnits($label="")
    {
        $this->units=$label;
    }

    /**
     * Set the width of the chart
     *
     * @param width - can be percentage or number of pixels
     */
    function setWidth($width="100%")
    {
        $this->width=$width;
    }

    /**
     * Set the height of the chart
     *
     * @param width - can be percentage or number of pixels
     */
    function setHeight($height="100%")
    {
        $this->height = $height;
    }

    /**
     * Set the id of object for the HTML tag.
     *
     * @param id
     */
    function setId($id="genGraph")
    {
        $this->id = $id;
    }

    /**
     * Set the data array - only for pie charts
     *
     * @param data array
     */
    function setData($arr=array())
    {
        $this->data = $arr;
    }

    /**
     * Set the bar graph type.
     * Available options are "clustered", "overlaid", "stacked", or "100%".
     * 
     * @param String type
     */
    function setBarType($type)
    {
        if ($type == "100%")
        {
            $this->barType = "100 percent";
        }
        else
        {
            $this->barType = $type;
        }
    }

    /**
     * Get the database name to run the select against.
     *
     * @return database name
     */
    function getDbname()
    {
        return $this->dbname;
    }

    /**
     * Get the select statement.
     *
     * @return select sql
     */
    function getSelect()
    {
        return $this->select;
    }

    /**
     * Get the type of graph - 'PIE' or 'LINE'.
     *
     * @return type string
     */
    function getType()
    {
        return $this->type;
    }

    /**
     * Get legend direction - 'horizontal' or 'vertical'.
     *
     * @return direction string
     */
    function getLegendDir()
    {
        return $this->legendDir;
    }

    /**
     * Get the chart title
     *
     * @return title
     */
    function getTitle()
    {
        return $this->title;
    }

	/**
     * Get whether the zoom is shown
     *
     * @return boolean showZoom
     */
    function getShowZoom()
    {
        return $this->showZoom;
    }

    /**
     * Get the width
     *
     * @return width
     */
    function getWidth()
    {
        if ( !$this->width )
        {
            $this->setWidth("100%");
        }

        return $this->width;
    }

    /**
     * Get the height
     *
     * @return height
     */
    function getHeight()
    {
        if ( ! $this->height )
        {
            $this->setHeight("100%");
        }

        return $this->height;
    }

    /**
     * Get the id of the chart
     *
     * @return id
     */
    function getId()
    {
        return $this->id;
    }

    /**
     * Get the data titles
     *
     * @return array of data titles
     */
    function getDataTitles()
    {
        $ret = "";

        if ( is_array($this->dataTitles) )
        {
            foreach ( $this->dataTitles as $k => $v)
            {
                $ret .= "title_{$k}={$v};";
            }
        }
        return $ret;
    }

    /**
     * Get the label for the units
     *
     * @return units
     */
    function getUnits()
    {
        return $this->units;
    }

    /**
     * Get the data array
     *
     * @return data array
     */
    function getData()
    {
        $ret = "";

        foreach ( $this->data as $k => $v)
        {
            $ret .= "{$k}={$v};";
        }

        return $ret;
    }

	/**
	 * get the barGraph type
	 *
	 * @return String barType
	 */
	function getBarType()
	{
		return $this->barType;
	}

    /**
     * Get the SWF file needed to render the Chart.
     *
     * @return string
     */
    function getSWF()
    {
        if ( $this->type == "PIE")
        {
            return "genPieGraph";
        }
		elseif ($this->type == "LINE")
		{
			return "genGraph";
		}
		elseif ($this->type == "BAR")
		{
			return "genBarGraph";
		}
	}

    /**
     * Get the FlashVars data
     * i.e. the options we are passing to the SWF file.
     *
     * @return string
     */
    function getFlashVars()
    {
    	// Setup flashvars for the graph
        $flashvars = "select={$this->getSelect()}&dbname={$this->getDbname()}&graphTitle={$this->getTitle()}"
                   . "&dataTitles={$this->getDataTitles()}&graphData={$this->getData()}&units={$this->getUnits()}" 
                   . "&legendDirection={$this->getLegendDir()}&showZoom={$this->getShowZoom()}&barType={$this->getBarType()}";

        // Setup flashvars for the resource bundles
        $lang = $this->idsadmin->phpsession->get_lang();
        if ($lang == "en_US")
        {
             $flashvars .= "&localeChain=en_US";
        }
        else 
        {
             $flashvars .= "&localeChain={$lang},en_US";
        }
        
        $swf_name = "";
        if ($this->type == "PIE")
        {
        	$swf_name = "genPieGraph";
        } 
        else if ($this->type == "BAR")
        {
        	$swf_name = "genBarGraph";
        }
        else 
        {
        	$swf_name = "genGraph";
        }
        $flashvars .= "&resourceModuleURLs=swfs/Charts/{$swf_name}_en_US.swf,swfs/lib/oat_en_US.swf,swfs/lib/rdfwidgets_en_US.swf";
        if ($lang != "en_US")
        {
             $flashvars .= ",swfs/Charts/{$swf_name}_{$lang}.swf,swfs/lib/oat_{$lang}.swf,swfs/lib/rdfwidgets_{$lang}.swf";
        }

        return $flashvars;
    }

    /**
     * Get the params that options we are passing to the SWF file for IE.
     *
     * @return string
     */
    function getParams()
    {
        return <<< EOF
        <param name="select" value="{$this->getSelect()}"/>
        <param name="dbname" value="{$this->getDbname()}"/>
        <param name="graphTitle" value="{$this->getTitle()}"/>
        <param name="legendDirection" value="{$this->getLegendDir()}"/>
        <param name="graphData" value="{$this->getData()}"/>
EOF;
    }

    /**
     * Render the chart in the HTML page
     */
    function Render()
    {
        $this->idsadmin->load_template("template_Charts");
        $this->idsadmin->html->add_to_output($this->idsadmin->template['template_Charts']->renderChart($id));
    }

    function __destruct()
    {

    }
}
?>
