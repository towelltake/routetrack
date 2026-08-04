$(function()
{//start Route process
   
 $("#ddlitem").change(function()
   {
       $.blockUI({ message: load_price_value });
       var customer_code  =$("#ddlcustomer").val();
       var item_id = $("#ddlitem").val();
          
       $.ajax
      ({
          type: "POST",
          url: ajax_page_URL+"itempriceinfo/item_id/"+item_id+"/customer_code/"+customer_code,
          data:'',
          cache: false,
          complete: function(data)
          {
            var final_data =data.responseText;
            if(final_data == "exist") {
                jAlert("Item Already Added To The List.");
                $("#ddlitem").val("");
                $("#ddlitem").trigger("liszt:updated");
                return false;
            } else {
                var data_arr=final_data.split("$::$");
               
                clear_detail_2_screen_data();
                $("#txt_upc").val(data_arr[0]);
                $("#txt_sale_price").val(data_arr[1]);
                $("#txt_sales_pcs_price").val(data_arr[2]);
                $("#txt_freegood_price").val(data_arr[3]);
                $("#txt_freegood_pcs_price").val(data_arr[4]);
                $("#txt_return_price").val(data_arr[5]);
                $("#txt_return_pcs_price").val(data_arr[6]);
                $("#txt_costcaseprice").val(data_arr[7]);
                $("#txt_defaultcostprice").val(data_arr[8]);
            }
            /*  
             
              $("#txt_sale_price").val(data_arr[1]);
              $("#txt_return_price").val(data_arr[2]);
              $("#txt_sales_pcs_price").val(data_arr[3]);
              $("#txt_return_pcs_price").val(data_arr[4]);
              $("#txt_costcaseprice").val(data_arr[5]);
              $("#txt_defaultcostprice").val(data_arr[6]);
              $("#txt_freegood_price").val(data_arr[7]);
              $("#txt_freegood_pcs_price").val(data_arr[8]);
            */
          }
      });
      
   });
 
    $("#btn_previous_order_item").click(function(){
           //hidealltab();
           //$("#tab1").show();
           //$("#t1").addClass("admin_active");
           jConfirm('Do you want delete your all data?', '', function (ans) {
            if(ans){
                $.ajax
                ({
                    type: "POST",
                    url: ajax_page_URL+"deletetempdata",
                    data:'',
                    cache: false,
                    complete: function(data)
                    {
                        hidealltab();
                        $("#tab1").show();
                        $("#t1").addClass("admin_active");
                        $("#item-grid").html('');
                        $("#divid").hide();
                    }
                });
            }
        });
   });

    $("#btn_next_order_item").click(function(){
        if($('#totalitem_list').length <= 0) {
            jAlert("Please Add Item.");
            return false;
        } else if($('#totalitem_list').length > 0 && $('#totalitem_list').val() == 0){
            jAlert("Please Add Item.");
            return false;
        }
        var creditlimit    =  $("#customer_creditlimit").val();
        var customer_limit =  $("#customer_total_amount").val();
        
        creditlimit     = creditlimit.replace(",","");
        customer_limit     = customer_limit.replace(",","");
        
        if($("#customer_payment_terms").val() > 1 )
        {
            if( parseFloat(creditlimit) > parseFloat(customer_limit))
            {
                loadpromotiondata();
            }
            else
            {
                jAlert("Credit Limit Exceeded.");
            }
        }
        else
        {
            loadpromotiondata();
        }
    
   });
   
    $("#btn_save_invoice_item").click(function(){
     
     var sales_case_price = $("#txt_sale_price").val();
     var sales_pcs_price = $("#txt_sales_pcs_price").val();
     
     
     var return_case_price = $("#txt_return_price").val();
     var return_pcs_price = $("#txt_return_pcs_price").val();
     
     
     var costcaseprice = $("#txt_costcaseprice").val();
     var defaultcostprice = $("#txt_defaultcostprice").val();
   
     
      if(parseFloat(costcaseprice) > parseFloat(sales_case_price))
      {
         jAlert("Sales Case Price is Grater then "+costcaseprice);
         return false;
      }
      
      if(parseFloat(costcaseprice)  > parseFloat(return_case_price))
      {
         jAlert("Return Case Price is Grater then "+costcaseprice);
         return false;
      }
       
      if(parseFloat(defaultcostprice) > parseFloat(sales_pcs_price) )
      {
         jAlert("Sales PCS Price is Grater then "+defaultcostprice);
         return false;
      }
     
      if(parseFloat(defaultcostprice) > parseFloat(return_pcs_price))
      {
         jAlert("Return PCS Price is Grater then "+defaultcostprice);
         return false;
      }
      
      
         if($("#frmorderitem").valid())
         {            
           //nothing
            saveitemdata();
            $('#divid').show();
            return true;
         }
            return false;
     });

//end off   
});