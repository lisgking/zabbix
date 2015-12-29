/******
 * Javascript functions for connform on Admin pages
 *******/

/**
 * Toggle for expanding or collapsing an HTML element
 **/
function expand_collapse(id) {
    if (document.getElementById(id).style.display=="none") {
		document.getElementById(id).style.display = "";
	} else {
		document.getElementById(id).style.display = "none";
	}
}

/**
 * browserIsIE(): 
 * Returns true if running in IE; false otherwise
 */
function browserIsIE() {
	var browser=navigator.appName;
	var b_version=navigator.appVersion;
	var version=parseFloat(b_version);
	if (browser=="Microsoft Internet Explorer"){
		return true;
	} 
	return false;
}
