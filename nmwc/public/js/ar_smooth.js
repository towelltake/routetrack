/* path to the stylesheets for the color picker */
var style_path = base_url + "/public/css/colors";

$(document).ready(function () {
    /* messages fade away when dismiss is clicked */
    $(".message > .dismiss > a").live("click", function (event) {
	var value = $(this).attr("href");
	var id = value.substring(value.indexOf('#') + 1);

	$("#" + id).fadeOut('slow', function () { });

	return false;
    });

    /* color picker */
    $("#colors-switcher > a").click(function () {
	var style = $("#color");
	var cookieOptions = {expires: 7, path: '/'};
	var cookieValue = $(this).attr("title").toLowerCase() + ".css";	
	var cookieName = 'css_color';
	
	style.attr("href", "" + style_path + "/" + css + $(this).attr("title").toLowerCase() + ".css");
	setCookie(cookieName,cookieValue,7);
	return false;
    });

    $("#menu h6 a").click(function () {
	var link = $(this);
	var value = link.attr("href");
	var id = value.substring(value.indexOf('#') + 1);

	var heading = $("#h-menu-" + id);
	var list = $("#menu-" + id);

	if (list.attr("class") == "closed") {
		heading.attr("class", "selected");
		list.attr("class", "opened");
	} else {
		heading.attr("class", "");
		list.attr("class", "closed");
	}
    });

    $("#menu li a[class~=collapsible]").click(function () {
	var element = $(this);

	if (element.attr("class") == "collapsible plus") {
		element.attr("class", "collapsible minus");
	} else {
		element.attr("class", "collapsible plus");
	}

	var list = element.next();

	if (list.attr("class") == "collapsed") {
		list.attr("class", "expanded");
	} else {
		list.attr("class", "collapsed");
	}
    });
    
    
    function setCookie(name,value,days)
    {
	//	From http://www.quirksmode.org/js/cookies.html
	if (days) {
		var date = new Date();
		date.setTime(date.getTime()+(days*24*60*60*1000));
		var expires = "; expires="+date.toGMTString();
	}
	else var expires = "";
	document.cookie = name+"="+value+expires+"; path=/";
    }
    function readCookie(name) {
	var nameEQ = name + "=";
	var ca = document.cookie.split(';');
	for(var i=0;i < ca.length;i++) {
		var c = ca[i];
		while (c.charAt(0)==' ') c = c.substring(1,c.length);
		if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
	}
	return null;
    }
    
    function eraseCookie(name) {
	createCookie(name,"",-1);
    }
});