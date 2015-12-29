/***************************************************************************
 *  (c) Copyright IBM Corporation, 2006, 2012.  All Rights Reserved
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
 ***************************************************************************/

function pop(act,hdo)
{
	var w;
	w = window.open( 'index.php?act=help&helpact='+act+'&helpdo='+hdo,'Help','width=600,height=555,resizable=yes,scrollbars=yes');
        w.focus();
}

function changeLink(_linkid,_href)
{
	var x = document.getElementById(_linkid);
	if (x != null)
	{
		x.href = _href;
	}
	
	var y = document.getElementsByName("helpbtn");
	var f = function onclick(event) { eval(_href) };
	y[0].onclick = f;

}

function getHelpLink()
{
	var x = document.getElementById('helpLink');
	return x.href;
}

function pop_rss(title)
{
	var w;
        w = window.open('index.php?act=rss&do='+title,+title+' RSS Feed',
            'width=400,height=500,resizable=yes,scrollbars=yes');
        w.focus();
}

function switchPage( newpage ) 
{
window.location = newpage.options[newpage.selectedIndex].value;
}

function expandcollapse( target )
{
var state = 0;
var sectionname = "section" + target;
var sectionimage = "sectionimage" + target;

if ( document.getElementById ) {
   target = document.getElementById( sectionname );
   if ( target.style.display == "none" ) {
   		if ( target.id == "sectionMenu")
   		{
   			/* IE doesn't handle all W3C-compliant display values */
      		if (navigator.appName == "Microsoft Internet Explorer")
      			target.style.display = "";
      		else       		
      			target.style.display = "table-cell";      		
   		}
   		else
   		{
   			target.style.display = "block";
   		}
      state = 1;
   }
   else {
      target.style.display = "none";
      state = 0;
   }

   document.getElementById( sectionimage ).src =  (state ? "images/collapse_arrow.jpg" : "images/expand_arrow.jpg");
   if ( target.id == "sectionMenu")
   {
   		document.getElementById( sectionimage ).title = ( state ? "Collapse menu" : "Expand Menu");
   }
}

}

function showDocuments( URL, windowName )
{	
	var w = window.open('index.php?act=help&do=showDocument&document='+URL,windowName);
	w.focus();
}

function openURL ( URL, windowName )
{	
	var w = window.open(URL,windowName);
	w.focus();
}

function openOATURL (URL)
{	
	window.location = URL;
}

function goToHomePage ()
{	
	openOATURL("index.php?act=home");
}

/**
 * Toggle for enabling/disabling an HTML element
 **/
function enable_disable(id) {
    if (document.getElementById(id).disabled==true) {
                document.getElementById(id).disabled = false;
        } else {
                document.getElementById(id).disabled = true;
                document.getElementById(id).value = 0;
        }
}

function switchLang()
{
	var box = document.forms['language_form'].langauge_select;
	var choice = box.options[box.selectedIndex].value;
	window.location= 'index.php?lang='+choice;
}

/**
 * Auto expand menu items to ensure the currently selected menu item is visible.
 */
function autoExpandMenuToShowSelection(is_admin_page)
{
	// Get the menu
	var menucontents = document.getElementById("menucontents");
	if (menucontents == null)
	{
		return;
	}
	
	// Get the highlighted item on the menu
	var highlightedItem;
	var divs = menucontents.getElementsByTagName("div");
	for (i=0; i<divs.length; i++) {
		if (divs[i].className == "menuitem_highlight") {
			highlightedItem = divs[i];
			break;
		}
	}

	if (highlightedItem == null) return;
	
	// Find the highlighted menu item's parent
	var parentIdNumber = highlightedItem.parentNode.id.split("_")[1];
	var linkId = "link_"+parentIdNumber;
	
	var link = document.getElementById(linkId);
	if (link == null) return;
	
	// Change the style
	highlightedItem.parentNode.style.display = "";

	// Change the link image
	if (is_admin_page) {
		link.firstChild.src="../images/menu/twisty_expand.png";
	} else {
		link.firstChild.src="images/menu/twisty_expand.png";
	}
	
}