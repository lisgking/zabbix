var httprequest;
var id;
var beforeImage;
var requestURL;
var data;
var resulthandler;

function setid(param_id)
{
	id = param_id;
}

function setbeforeImage(param_id)
{
	beforeImage = document.getElementById(param_id).innerHTML;
}

function setrequestURL(param_requestURL)
{
	requestURL = param_requestURL;
}

function setdata(param_data)
{
	data = param_data;
}

function setresulthandler(param_resulthandler)
{
	resulthandler = param_resulthandler;
}

function sethttprequest()
{
	if (window.XMLHttpRequest) // Case for Firefox, Safari ....
	{
		httprequest = new XMLHttpRequest(); 
		if (httprequest.overrideMimeType){
			httprequest.overrideMimeType('text/html');
		}
	}else if (window.ActiveXObject) // Case for internet explorer.
	{
		try{
			httprequest = new ActiveXObject("Msxml2.XMLHTTP");
		}catch(e){
			try{
				httprequest = new ActiveXObject("Microsoft.XMLHTTP");
			}catch(e){}
		}
	}
}

/*loadAJAX function to load the "processing..." indicator and make XMLHTTP request call to webserver.
params:
	
	id : The id of the div section in the HTML, that you want to turn it into the "processing..." indicator.
	
	requestURL: The URL to the php script that you want to process. 
	Should be something like "index.php?act=space&amp;do=createdbspace"
	
	data(optional): The extra data that you want to pass to the php script. 
	Should be something like "name=value&anothername=othervalue&so=on"
	
	resulthandler(optional): The javascript function name that you want to call when the requestURL php script returns.
	Resulthandler should be a function that looks like this..
	function functionname(){
        if (httpRequest.readyState == 4) {
            if (httpRequest.status == 200) {
                //do something when script success...
            } else {
                //do something to process script error
            }
        }
    }
	
	If resulthandler is not set, it will use default result handler. It will dump a message box at 
	the head of the HTML div specified in the id parameter.
	
	In the server script, you probably have to set $this->idsadmin->render = false,
	and use echo function to write to httprequest.responseText.
	Or else the responseText will also contain the OAT menus, headers and stuff, which you dont want.
	*/
function loadAJAX(param_id,param_requestURL,param_data,param_resulthandler)
{
	if ( param_id === undefined || param_requestURL === undefined ){
		alert('ERROR: Specify ID and RequestURL when calling loadAJAX javascript function.');
		return false;
	}
	
	var ajax_msgbox = document.getElementById('AJAX_msgbox');
	if (ajax_msgbox != null){
		ajax_msgbox.innerHTML = "";
	}
	
	setid(param_id);
	setrequestURL(param_requestURL);
	setdata(param_data);
	setresulthandler(param_resulthandler);
	setbeforeImage(param_id);
	sethttprequest();
	
	var htmlobj = document.getElementById(id);
	htmlobj.innerHTML = "<center><img src='images/spinner.gif' border='0' alt=''/>Processing ...</center>";
	
	if(!httprequest){
		Alert('ERROR: Failed to create XMLHTTP Object.');
		return false;
	}
	
	if (resulthandler === undefined){
		httprequest.onreadystatechange = function() {
			if(httprequest.readyState == 4){
				if(httprequest.status == 200){
					document.getElementById(id).innerHTML = 
						"<div id='AJAX_msgbox'>" +
						"<fieldset>" +
						"<legend>Info</legend>" +
						"<table width='100%' style='background: #FFFFFF;' >" +
						"<tr><td>" +
						httprequest.responseText +
						"</td></tr>" +
						"</table>" +
						"</div>";
					document.getElementById(id).innerHTML+= beforeImage;
				}else{
					document.getElementById(id).innerHTML = 
						"<div id='AJAX_msgbox'>" +
						"<fieldset>" +
						"<legend>An error has occurred</legend>" +
						"<table width='100%' style='background: #E3C0C0;' >" +
						"<tr>" +
						"<td>AJAX XMLHTTP Request Failed</td>" +
						"</tr>" +
						"</table>" +
						"</div>";
					document.getElementById(id).innerHTML+= beforeImage;
				}
			}
		}
	}else{
		httprequest.onreadystatechange = resulthandler;
	}
		
	httprequest.open('POST',requestURL,true);
	httprequest.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
	
	if(data === undefined){
		httprequest.send(null);
	}else{
		httprequest.send(data);
	}
}
	
function createDBhandler()
{
	if(httprequest.readyState == 4){
		if(httprequest.status == 200){
			switch(httprequest.responseText)
			{
			case "DBSpace creation success":
				window.location = 'index.php?act=space&do=dbspaces';
				break;
			default:
				document.getElementById(id).innerHTML = 
					"<div id='AJAX_msgbox'>" +
					"<fieldset>" +
					"<legend>An error has occured</legend>" +
					"<table width='100%' style='background: #E3C0C0;' >" +
					"<tr><td>" +
					httprequest.responseText +
					"</td></tr>" +
					"</table>" +
					"</div>";
				document.getElementById(id).innerHTML+= beforeImage;
				break;
			}
		}else{
			document.getElementById(id).innerHTML = 
				"<div id='AJAX_msgbox'>" +
				"<fieldset>" +
				"<legend>An error has occurred</legend>" +
				"<table width='100%' style='background: #E3C0C0;' >" +
				"<tr>" +
				"<td>AJAX XMLHTTP Request Failed</td>" +
				"</tr>" +
				"</table>" +
				"</div>";
			document.getElementById(id).innerHTML+= beforeImage;
		}
	}
}

function createChkhandler()
{
	var dbsnum = data.substr(data.indexOf('dbsnum=')+7,1);
	var dbsname = data.substr(data.indexOf('dbsname=')+8);
	
	if(httprequest.readyState == 4){
		if(httprequest.status == 200){
			switch(httprequest.responseText)
			{
			case "Chunk added successfully":
				window.location = 'index.php?act=space&do=dbsadmin&dbsnum='+dbsnum+'&dbsname='+dbsname;
				break;
			case "Mirrored chunk added successfully":
				window.location = 'index.php?act=space&do=dbsadmin&dbsnum='+dbsnum+'&dbsname='+dbsname;
				break;
			case "Mirror added successfully":
				window.location = 'index.php?act=space&do=dbsadmin&dbsnum='+dbsnum+'&dbsname='+dbsname;
				break;
			default:
				document.getElementById(id).innerHTML = 
					"<div id='AJAX_msgbox'>" +
					"<fieldset>" +
					"<legend>An error has occured</legend>" +
					"<table width='100%' style='background: #E3C0C0;' >" +
					"<tr><td>" +
					httprequest.responseText +
					"</td></tr>" +
					"</table>" +
					"</div>";
				document.getElementById(id).innerHTML+= beforeImage;
				break;
			}
		}else{
			document.getElementById(id).innerHTML = 
				"<div id='AJAX_msgbox'>" +
				"<fieldset>" +
				"<legend>An error has occurred</legend>" +
				"<table width='100%' style='background: #E3C0C0;' >" +
				"<tr>" +
				"<td>AJAX XMLHTTP Request Failed</td>" +
				"</tr>" +
				"</table>" +
				"</div>";
			document.getElementById(id).innerHTML+= beforeImage;
		}
	}
}

function addLloghandler()
{
	if(httprequest.readyState == 4){
		if(httprequest.status == 200){
			switch(httprequest.responseText)
			{
			case "Logical logs added":
				window.location = 'index.php?act=rlogs&do=llogs';
				break;
			default:
				document.getElementById(id).innerHTML = 
					"<div id='AJAX_msgbox'>" +
					"<fieldset>" +
					"<legend>An error has occured</legend>" +
					"<table width='100%' style='background: #E3C0C0;' >" +
					"<tr><td>" +
					httprequest.responseText +
					"</td></tr>" +
					"</table>" +
					"</div>";
				document.getElementById(id).innerHTML+= beforeImage;
				break;
			}
		}else{
			document.getElementById(id).innerHTML = 
				"<div id='AJAX_msgbox'>" +
				"<fieldset>" +
				"<legend>An error has occurred</legend>" +
				"<table width='100%' style='background: #E3C0C0;' >" +
				"<tr>" +
				"<td>AJAX XMLHTTP Request Failed</td>" +
				"</tr>" +
				"</table>" +
				"</div>";
			document.getElementById(id).innerHTML+= beforeImage;
		}
	}
}
