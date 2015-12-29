function onlyalnum(event)
{
	var key;
	var keychar;

	if ( window.event )
	{
   		key = window.event.keyCode;
	}
	else
	{
 		if (event)
 		{
			key = event.which;
		}
		else
		{
			return true;
   		}
	}
keychar = String.fromCharCode(key);
keychar = keychar.toLowerCase();

// control keys
if (  (key===null) || (key===0) || (key==8) || 
    (key==9) || (key==13) || (key==27) )
{
   return true;
}
// alphas and numbers
else
{
 if ((("abcdefghijklmnopqrstuvwxyz0123456789").indexOf(keychar) > -1))
	{
   return true;
   }
	else
	{
   		return false;
   		}
}
}

function onlydigit(event)
{
var key;
var keychar;

if (window.event)
{
   key = window.event.keyCode;
}
else 
{
	if (event)
	{
   key = event.which;
   }
	else
	{
   	return true;
   	}
}
keychar = String.fromCharCode(key);

// control keys
if ((key===null) || (key===0) || (key==8) || 
    (key==9) || (key==13) || (key==27) )
    {
   return true;
   }

// numbers
else
   {
    if ((("0123456789").indexOf(keychar) > -1))
   	{
   	return true;
   	}
	else
	{
   		return false;
	}
  }
}
