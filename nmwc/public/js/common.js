function winloc(url)
{
    window.location=url;
}
function popup(url,width,height)
{
    var promourl = url+'?q=prettyphoto&iframe=true&width='+width+'&height='+height;
    $.prettyPhoto.open(promourl);
   
}
function closepopup(){
     parent.$.prettyPhoto.close();
}

function loaddata(xurl,container)
{
    $.ajax({
		url:xurl,
		beforeSend: function() {	   
			$("#loader").html("<img src='"+base_url+"/public/images/ajax-loader-bar.gif' alt='Loading..' />");	    
		},
		success:function(data){
			$("#"+container).html(data);
			$("#loader").html("");
			if(container == 'tran_invoice') {
				$("a[rel^='prettyPhoto']").prettyPhoto({animation_speed:'fast',theme:'light_rounded', allow_resize: false});				
			}
		}
    })
}
//For Depot Inventory Only
function loaddepotdatadata(xurl,container,data)
{
    $.ajax({
		type : "POST",
		url:xurl,
		data:data,
		beforeSend: function() {	   
			$("#loader").html("<img src='"+base_url+"/public/images/ajax-loader-bar.gif' alt='Loading..' />");	    
		},
		success:function(data){
			$("#"+container).html(data);
			$("#loader").html("");			
			fnload();
		}
    })
}
function loaddata_popup(xurl,container)
{
    $.ajax({
	url:xurl,
	beforeSend: function() {	   
	    $("#loader").html("<img src='"+base_url+"/public/images/ajax-loader-bar.gif' alt='Loading..' />");	    
	},
	success:function(data){
	    $("#"+container).html(data);
	    $("#loader").html("");
		$("a[rel^='prettyPhoto']").prettyPhoto({animation_speed:'fast',theme:'light_rounded', allow_resize: false});
		}
    })
}
// Added By Hiren Dave On 5th Sept. for number formating.

function addCommas(pNumber,decimals)
{
	pNumber = pNumber.toFixed(decimals);
	pNumber += '';
	x = pNumber.split('.');
	x1 = x[0];
	x2 = x.length > 1 ? '.' + x[1] : '';
	var rgx = /(\d+)(\d{3})/;
	while (rgx.test(x1)) {
	  x1 = x1.replace(rgx, '$1' + ',' + '$2');
	}
	
	return x1 + x2;
}
$(function() {
	
	$("#txtaltcode").attr('maxlength','50');
	$("#txtalt_code").attr('maxlength','50');
    
	$('#sidebar_content h6').click(function() {

	    $('h6').removeClass('selected');
	    $('ul').removeClass('opened');
	    $('ul').addClass('closed');
    
	    var menu_id = $(this).attr('id');
	    var str = menu_id;
	    var substr = str.split('-');
	    var mid = 'menu-' + substr[2];
    
	    //alert(menu_id);
	    //alert(mid);
	    $('#'+menu_id).addClass('selected');
	    $('#'+mid).removeClass('closed');
	    $('ul.collapsed').removeClass('closed');
	});
    
    /*
    Hiren Dave on 30th Jan 2012
    
    Below function is checked for any record is selected or not for deleting record.
    */
    $('#btndelete').click(function(){
        
        var str = '';
        $('#table-example input[type="checkbox"]:checked').each(function() {
            str  += ','+$(this).val();			
        });
        
        if(!str){
            jAlert('Please Select Record To Delete.');
        }
        else
        {
            jConfirm('Are You Sure To Delete This Record(s)?', 'Message', function(resp)
            {
                if(resp) {
                    $('#hdDelete').val(1);		    
                    $("form").submit();
                }
            })
        }
        
    });
	
	    /*
    Hiren Dave on 30th Jan 2012
    
    Below function is checked for any record is selected or not for deleting record in cloud transaction.
    */
    $('#btndeletetran').click(function(){
        
		jConfirm('Are You Sure To Delete This Record(s)?', 'Message', function(resp)
		{
			if(resp) {
				window.location = $('#btndeletetran').attr('alt');
			}
		})
    });
    
    /*
    Veena Nair on 13th Jan 2015
    
    Below function is checked for any record is selected or not for Syncing record in cloud transaction.
    */
    $('#btnsync').click(function(){
    	 var str = '';
         $('#table-example input[type="checkbox"]:checked').each(function() {
             str  += ','+$(this).val();			
         });
         
         if(!str){
             jAlert('Please Select Record To Sync.', 'Alert Dialog');
         } else {
        	 jConfirm('Are You Sure To sync This Record(s)?', 'Message', function(resp)
             {
                 if(resp) {
                	 //$("#Loadingcontainer").show();
                     $('#hdDelete').val(1);		    
                     $("form").submit();
                 }
             })
         }
    });
    
    // veena.nair down syncing data from SAP
    $('#btndwnsync').click(function(){
       	 jConfirm('Are You Sure To Downsync Record(s) From SAP', 'Message', function(resp)
            {
                if(resp) {
                	//$("#Loadingcontainer").show();
                	$('#hdDelete').val(1);	
                    $("form").submit();
                }
            })
   });

    $('#importsync').click(function(){
       	jConfirm('Are You Sure To sync?', 'Message', function(resp)
        {
            if(resp) {
               // $("#Loadingcontainer").show();
                $('#hdDelete').val(1);
                $("form").submit();
            }
        })
   });
    
    $(".icodel").click(function(){
       
       var tid = this.id;
       
       if(!tid){
            jAlert('Please Select Record To Delete.');
        }
        else
        {
			jConfirm('Are You Sure To Delete This Record(s)?', 'Message', function(resp)
			{
                $("#frmoverview").removeClass('validate');
                
                if(resp) {
                    window.location.href = $("#"+tid).attr('redirecturl');
                }
            })
		}        
    });
    
    /*
    Hiren Dave on 30th Jan 2012
    
    Below function is checked for any record is selected or not for editing record.    
    */
    $('#btnedit').click(function(){
        
		var len		= $("#table-example input[type='checkbox'][name='chk[]']:checked").length;
     
        if(len > 1)
        {
            jAlert('Please Select Only One Record.');
        }
        else if(!len)
        {
            jAlert('Please Select Record To Edit.');
        }
        else
        {
            var redURL  = $("#table-example a[class^='ico edit']").attr('href');
			if(redURL !='')
            {
				var edit_id = (/id\/\d+(?:\.\d+)?/.exec(redURL));
				var did     = $("input[name='chk[]']:checked").val();				
				//redLink     = redURL.replace("id/"+edit_id, 'id/'+did);
				redLink     = redURL.replace(edit_id, 'id/'+did);
				window.location = redLink;
            }
        }
    });   
    
    /*
    Mayur Bhayani on 23 July, 2012
    
    Below function is used to download csv file when click on download csv button.
    */
    $("#btnexport").click(function() {
        
        var currentPageUrl = $(location).attr('href');
        //currentPageUrl = currentPageUrl + '/downloadcsv/1';
        
        dataString = $("#frmoverview").serialize();
        
        $("#frmoverview").attr('action', currentPageUrl);        
        $('#frmoverview').append('<input type="hidden" name="downloadcsv" id="downloadcsv" value="1" />');
        $("#frmoverview").submit();        
        $('#downloadcsv').remove();
        
        return false;
        
        /*
        $.ajax
        ({
            type: "POST",
            url: currentPageUrl,
            data: dataString,
            cache: false,
            complete: function(html)
            {
                document.location.href = currentPageUrl;
                $(document).ajaxStop($.unblockUI);
            }
        });
        */
    });
	
	/*
    Hiren Dave on 19 Nov, 2012
    
    Below function is used to print data when click on print button.
    */
    $("#btnprint").click(function() {
        
        var currentPageUrl = $(location).attr('href');
		currentPageUrl = currentPageUrl+'/printData/1';
		
		window.open(currentPageUrl,'','width=900,height=400,scrollbars=yes');
        $('#printData').remove();
        return false;
    });
});
function callpagescroll(datecntrl)
{
 var dposition = datecntrl.offset().top;
 $(window).scrollTop(dposition);
}
// For clear all the controls of form
function clear_form_elements(frmname) {    
$("#"+frmname).find(':input').each(function() {
    switch(this.type) {
	case 'password':
	case 'select-multiple':
	case 'select-one':
	case 'text':	
	case 'textarea':
	    $(this).val('');
	    break;
	case 'checkbox':
	case 'radio':
	    this.checked = false;
	
	$('select').trigger('liszt:updated');
    }
});
}

//function toggleChecked(status) {
//    $(".checkbox").each( function() {
//    $(this).attr("checked",status);
//    })
//}

/*
for adding hidden field before report form submit
*/
function addhidden()
{
    if($("#ddlfilterby").length > 0 && $("#ddlfilterby").val() != "")
    {
        if($("input[name='chk[]']:checked").length <= 0)
        {
            jAlert("Please Select "+$("#ddlfilterby option:selected").text());
            return false;
        }
    }
    
    $("#thisform select").each(function(){
        var id = $(this).attr("id");
        if($("#"+id+" option:selected").val() != "" && id != undefined) {
            $('#thisform').append("<input type='hidden' name='"+id+"_selected' id='"+id+"_selected' value='"+$("#"+id+" option:selected").text()+"'>");
        } else {
            if($("#"+id+"_selected").length > 0) {
                $("#"+id+"_selected").remove();
            }
        }
    });
    return true;
}
function formatNumber(n, decPlaces) {
    var thouSeparator = ",";
    var decSeparator = ".";
    decPlaces = isNaN(decPlaces = Math.abs(decPlaces)) ? 2 : decPlaces,
    decSeparator = decSeparator == undefined ? "." : decSeparator,
    thouSeparator = thouSeparator == undefined ? "," : thouSeparator,
    sign = n < 0 ? "-" : "",
    i = parseInt(n = Math.abs(+n || 0).toFixed(decPlaces)) + "",
    j = (j = i.length) > 3 ? j % 3 : 0;
    return sign + (j ? i.substr(0, j) + thouSeparator : "") + i.substr(j).replace(/(\d{3})(?=\d)/g, "$1" + thouSeparator) + (decPlaces ? decSeparator + Math.abs(n - i).toFixed(decPlaces).slice(2) : "");
};