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


class template_map {
    
    public $idsadmin;
    
    function header($data,$from=0)
    {
	$this->idsadmin->load_lang("misc_template");
        $style = "";
        if ( $from != 0 )
        $style = "style='display: none;'";

        $HTML = "";
        $HTML .= <<<EOF
<form name="mapgroups" onsubmit="return false;" action="">
<table>
<tr>
<td>
<div id="mmap" style="float:left;width:700px;height:500px;border:1px solid black;"></div>
</td>

<td valign="top">
<div {$style}>
   <select id="group_num" name="groupnum" onChange='changegrp(this)'>
EOF;
        foreach ($data as $k => $v )
        {
            $HTML .= "<option value='{$v['GROUP_NUM']}'>{$v['GROUP_NAME']}</option>";
        }
        $HTML .= <<<EOF
   </select>
</div>

<div id="xrefresh" align="center">
   <a href="javascript:refresh();" title="{$this->idsadmin->lang('ClickToReload')}" class="button">{$this->idsadmin->lang('Reload')}</a> <br/>
</div>
<div id="loadinfo" style="overflow:auto;height=600px;"></div>


<div id="list"></div>
</td>
</tr>
</table>
</form>

<script src="http://maps.google.com/maps?file=api&amp;v=2.x&amp;key={$this->idsadmin->get_config('GOOGLEMAPKEY')}" type="text/javascript"> 
</script> 


<script defer="defer" type="text/javascript">

function loadon(server)
{
 var l;
 document.mapgroups.group_num.disabled=true;
 l=document.getElementById('xrefresh');
 l.innerHTML="<center>{$this->idsadmin->lang('LoadingMsg')}</center>";
 l.style.backgroundColor="red";
}

function loadoff()
{
x = document.getElementById('xrefresh');
x.style.backgroundColor="";
x.innerHTML="<a href='javascript:refresh();' title=\"{$this->idsadmin->lang('ClickToReload')}\" class='button'>{$this->idsadmin->lang('Reload')}</a><br/>"
document.mapgroups.group_num.disabled=false;
}

function clearMarkers()
{
  for ( i=0 ; i < markers.length ; i++ )
  {
     map.removeOverlay(markers[i]);
  }
  markers.splice(0,markers.length); 
}

function refresh()
{
clearMarkers();
group_num = document.mapgroups.group_num.selectedIndex;
group_num = document.mapgroups.group_num[group_num].value;
getGroup(group_num);
}

function changegrp(t)
{
   var group_num = t[t.selectedIndex].value;
   getGroup(group_num);
} 

function linkclick(which)
{
    markers[which].openInfoWindowHtml(html[which]);
}

function createMarker(point, icon,html) {
  var marker = new GMarker(point,icon);
  GEvent.addListener(marker, "mouseover", function() {
    marker.openInfoWindowHtml(html);
  });
  return marker;
}

function getGroup(group_num)
{
clearTimeout(tout);
var timeOut = {$this->idsadmin->get_config("PINGINTERVAL",300)} * 10000;
tout = setTimeout('refresh()',timeOut);
var request = GXmlHttp.create();
var listhtml = "<b>{$this->idsadmin->lang('GroupServers')}</b><br/>";
request.open("POST", "index.php?act=connectioninfo&do=getconnections&group_num="+group_num, true);
loadon("");

bounds = new GLatLngBounds();

request.onreadystatechange = function() {
if (request.readyState == 4) 
{
 clearMarkers();
 var xmlDoc = request.responseXML.documentElement;
 var conn =  xmlDoc.getElementsByTagName("connection");
 var firsttime=true;
 for (var i = 0; i < conn.length; i++) 
 {

 var asof    = "";
if ( xmlDoc.getElementsByTagName("asof").item(i) != null &&
     xmlDoc.getElementsByTagName("asof").item(i).firstChild != null
)
{
asof=xmlDoc.getElementsByTagName("asof").item(i).firstChild.data;
}
 var conn_num = xmlDoc.getElementsByTagName("conn_num").item(i).firstChild.data;
 var nickname = xmlDoc.getElementsByTagName("server").item(i).firstChild.data;
 var server = xmlDoc.getElementsByTagName("server").item(i).firstChild.data;
 var host = xmlDoc.getElementsByTagName("host").item(i).firstChild.data;
 var port = xmlDoc.getElementsByTagName("port").item(i).firstChild.data;
 var lat = xmlDoc.getElementsByTagName("lat").item(i).firstChild.data;
 var lon = xmlDoc.getElementsByTagName("lon").item(i).firstChild.data;
 var state = parseInt(xmlDoc.getElementsByTagName("state").item(i).firstChild.data);
 var statemessage = xmlDoc.getElementsByTagName("message").item(i).firstChild.data;

 var lpt = "";
if ( xmlDoc.getElementsByTagName("lpt").item(i) != null &&
xmlDoc.getElementsByTagName("lpt").item(i).firstChild != null
)
{
lpt = xmlDoc.getElementsByTagName("lpt").item(i).firstChild.data;
}
color="green";
 switch ( state ) {
  case ONLINE:
  color = 'green';
  break;
  case ISSUE:
  color = 'yellow';
  break;
  case OFFLINE:
  color = 'red';
  break;
  case CURRENT:
  color = 'blue';
  break;
  default:
  break;
 }
 if ( firsttime == true )
 {
 listhtml += "Last Ping "+asof+"<br/>";
 firsttime=false;
 }
 listhtml += "<a href='javascript:linkclick("+i+");' title='"+statemessage+"'>"
            +"<span style='color:"+color+";'>"+nickname+"</span></a><br/>";
 var point = new  GLatLng(parseFloat(lat),parseFloat(lon));
 bounds.extend(point);
 
 html[i] = "Status:"+statemessage 
          +"<br/>Host: "+host
          +"<br/>Informix Server: "+server
          +"<br/>"
          +"<br/>Info is current as of:"+lpt;
          //+"<br/><input type=button onclick='alert(\"click\")' value='connect' />"
          //+"<br/>";
 markers[i] = createMarker(point,icons[state],html[i]); 
 map.addOverlay(markers[i]);
 } //end for

 if( first == true)
 {
 map.setZoom(map.getBoundsZoomLevel(bounds)-1);
 var clat = (bounds.getNorthEast().lat() + bounds.getSouthWest().lat()) /2;
 var clng = (bounds.getNorthEast().lng() + bounds.getSouthWest().lng()) /2;
 map.setCenter(new GLatLng(clat,clng));

 }
 document.getElementById("list").innerHTML = listhtml;
 loadoff();
}
} /* end onreadystatechange */

request.send(null);
} /* end getGroup */


if (G_INCOMPAT != true)
{

map = new GMap2(document.getElementById('mmap'));
map.addControl(new GLargeMapControl());
map.addControl(new GMapTypeControl());
//map.addControl(new GOverviewMapControl(new GSize(200,200)));
map.addMapType(G_SATELLITE_3D_MAP);
map.setCenter(new GLatLng(0, 0), 0);


GEvent.addListener(map, "zoomend", function(oldLevel, newLevel) {
first=oldLevel;
});



// create our icons
icons = new Array();

ONLINE=1;
ISSUE=2;
OFFLINE=3;
CURRENT=4;
first = true;
icons[ONLINE]=new GIcon();
icons[ONLINE].image= "images/map/green.png";
icons[ONLINE].shadow= "images/map/shadow.png";
icons[ONLINE].iconSize = new GSize(20, 34);
icons[ONLINE].shadowSize = new GSize(37, 34);
icons[ONLINE].iconAnchor = new GPoint(6, 20);
icons[ONLINE].infoWindowAnchor = new GPoint(5, 1);

icons[OFFLINE]=new GIcon();
icons[OFFLINE].image= "images/map/red.png";
icons[OFFLINE].shadow= "images/map/shadow.png";
icons[OFFLINE].iconSize = new GSize(20, 34);
icons[OFFLINE].shadowSize = new GSize(37, 34);
icons[OFFLINE].iconAnchor = new GPoint(6, 20);
icons[OFFLINE].infoWindowAnchor = new GPoint(5, 1);

icons[ISSUE]=new GIcon();
icons[ISSUE].image= "images/map/yellow.png";
icons[ISSUE].shadow= "images/map/shadow.png";
icons[ISSUE].iconSize = new GSize(20, 34);
icons[ISSUE].shadowSize = new GSize(37, 34);
icons[ISSUE].iconAnchor = new GPoint(6, 20);
icons[ISSUE].infoWindowAnchor = new GPoint(5, 1);

icons[CURRENT]=new GIcon();
icons[CURRENT].image= "images/map/greendot.png";
icons[CURRENT].shadow= "images/map/shadow.png";
icons[CURRENT].iconSize = new GSize(20, 34);
icons[CURRENT].shadowSize = new GSize(37, 34);
icons[CURRENT].iconAnchor = new GPoint(6, 20);
icons[CURRENT].infoWindowAnchor = new GPoint(5, 1);

tout=0;
markers = new Array();
html = new Array();
}

function mapit() 
{
if (G_INCOMPAT == true)
{
alert("{$this->idsadmin->lang('MapNotAvail')}");
window.location = "index.php?act=home&do=dashboard";
return;
} 
getGroup({$data[0]['GROUP_NUM']});

EOF;
        return $HTML;
    }

    function end()
    {
        $HTML = "";
        $HTML .= <<<EOF
//map.setZoom(map.getBoundsZoomLevel(bounds)-1);
//var clat = (bounds.getNorthEast().lat() + bounds.getSouthWest().lat()) /2;
//var clng = (bounds.getNorthEast().lng() + bounds.getSouthWest().lng()) /2;
//map.setCenter(new GLatLng(clat,clng));
// >
}
</script>
<body onload="mapit()">
</body>
EOF;
        return $HTML;
    }

}

?>
