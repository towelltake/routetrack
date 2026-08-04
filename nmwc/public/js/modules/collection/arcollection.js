$(function()
{//start Route process
   $("#divbank").hide();
   $("#showbtn").hide();
   $( "#txtcheckdt" ).datepicker({
            dateFormat: 'dd-mm-yy'
    });
   $("#ddlroute").change(function() {
	
        
       
        var str = $(this).find("option:selected").text();
	var substr = str.split(' -- ');
	$("#txtsalesman").attr("value",substr[1]);
       
        // Load Customer Dropdown
        $.ajax
        ({
            type: "POST",
            url: ajax_page_URL+"customerlist/route_code/"+$(this).val(),
            data: dataString=$(this).val(),
            cache: false,
            complete: function(html)
            {
                $("#ddlcustomer").html(html.responseText);
                $("#ddlcustomer").trigger("liszt:updated");
            }
        });
        
        $.ajax
        ({
            type: "POST",
            url: ajax_page_URL+"getroutewisedata/route_code/"+$(this).val(),
            data: dataString=$(this).val(),
            cache: false,
            complete: function(html)
            {
                var final_data =html.responseText;
                var data_arr=final_data.split("$::$");
                $("#txtdoc_no").val(data_arr[0]);
                $("#txtcollection_no").val(data_arr[1]);
                $("#txtsalesman_code").val(data_arr[2]);
            }
        });
    });
   
   $("#ddlcustomer").change(function(){
    //nothing
     
      var check_val = 0;
       if($('#chkfirstout').attr('checked')) {
          var check_val = 1;
          //$("#showbtn").show();
      }
      load_griddata(check_val);
   });
   
   $('#chkfirstout').click(function(){
      var check_val = 1;
      
      if($('#chkfirstout').attr('checked')) {
          var check_val = 0;
          $('#txtamount').attr('readonly', 'readonly');
          
                
      }else
      {
         $('#txtamount').removeAttr('readonly');
      }
      
      load_griddata(check_val);
   });
   $("#btnloadtotal").click(function(){
      
       var add = 0;
       var val1 = 0;
        $(".textbox_in_grid").each(function() {
           val1 = $(this).val();
           val1 = val1.replace(",","");
            add += Number(val1);
         //   alert(add);
        });
      $("#txtamount").val(add.toFixed(2));
      // alert(add);
   
   });
   
   $("#ddlpaymode").change(function(){
          if(this.value == 1)
	  {
		$("#divbank").show();
	  }
	  else
	  {
		$("#divbank").hide();
	  }
      
   });
   
   $("#btnsave").click(function(){
    //  add_collection1
    
      if($("#add_collection1").valid())
         {            
           //nothing
           $.blockUI({ message: please_wait_msg });
            $.post(ajax_page_URL+"savearcollection/", $("#add_collection").serialize()+$("#add_collection1").serialize(), function(data) {
    
             //alert(data);
         
               window.location.href= Overview_URL;
                    
            });    
            return true;
         }
         return false;
    
    
      
   });
   
//end offf   
});

function load_griddata(check_val)
{
   if($("#add_collection").valid())
    {
        if(check_val == 0)
            $("#showbtn").show();
        else
            $("#showbtn").hide();
           var str = "key1/"+ $("#ddlroute").val()+"/key2/"+$("#ddlcustomer").val()+"/key3/"+$("#txtsalesman_code").val()+"/key4/"+check_val;
           //loaddata(collection_item_grid+str,'item-grid');
           
            $.ajax({
                url:collection_item_grid+str,
                beforeSend: function() {	   
                    $("#loader").html("<img src='"+base_url+"/public/images/ajax-loader-bar.gif' alt='Loading..' />");	    
                },
                success:function(data){
                    $("#item-grid").html(data);
                    $("#loader").html("");
                    
                    $("#table-example tr").each(function() {
                        $(this).find('td').eq(5).find('input').removeAttr('onblur');
                    })
                }
            })           
           
            return true;
         }
         return false;
}