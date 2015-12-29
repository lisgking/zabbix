/***************************************************************************
 *  (c) Copyright IBM Corporation, 2011, 2012.  All Rights Reserved
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

/**
 * Javascript functions For Dashboard
 **/

/**
 * Drill down to another page in OAT for the given server's connection number
 */
function dashboardDrillDown(connNum, actParameter, doParameter)
{
	// Set the drop-down list's value to the desired connection number
	document.serverswitch.conn_num.value = connNum;

	// Redirect to the page specified by the act and do parameters
	var action = 'index.php?act=login&do=loginnopass&ract=' + actParameter;
	if (doParameter != "")
	{
		action += '&rdo=' + doParameter;
	}
	document.serverswitch.action = action;
	

	// Refresh the page 
	document.serverswitch.submit();
}

/**
 * Function to expand a menu item.
 * 
 * If the menu item is already expanded, leave it open.  
 * If the menu item is closed, expand it.
 */
/*function dashboardMenuExpand(id)
{
	var elem = document.getElementById("menudiv_"+id);
	var val = null;

	if ( elem == null || elem.style.display == "none" )
	{
		val = getCookie("menu_expand");
		if ( val == null )
		{
			val = id;
		}
		else
		{
			val = val + ":" + id;
		}
		setCookie("menu_expand",val,365);
	}
}*/
