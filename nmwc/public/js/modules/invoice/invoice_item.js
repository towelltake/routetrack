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
                    jAlert("Item already exist.");
                    $("#ddlitem").val("");
                    $("#ddlitem").trigger("liszt:updated");
                    return false;
                } else {
                    var data_arr=final_data.split("$::$");
                    
                    clear_detail_2_screen_data2();
                    $("#txt_upc").val(data_arr[0]);
                   
                    $("#txt_sale_price").val(data_arr[1]);
                    $("#txt_sales_pcs_price").val(data_arr[2]);
                    $("#txt_freegood_price").val(data_arr[3]);
                    $("#txt_freegood_pcs_price").val(data_arr[4]);
                    $("#txt_return_price").val(data_arr[5]);
                    $("#txt_return_pcs_price").val(data_arr[6]);
                    $("#txt_costcaseprice").val(data_arr[7]);
                    $("#txt_defaultcostprice").val(data_arr[8]);
                    $("#txt_available_qty").val(parseInt(data_arr[9])+"/"+parseInt(data_arr[10]));
                    $("#hid_available_qty").val(parseInt(data_arr[11]));
                    $("#hid_check_allowbatchentry").val(data_arr[12]);
                }
            }
        });
      
    });
 
    $("#btn_previous_invoice_item").click(function(){
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

   $("#btn_next_invoice_item").click(function(){
      //var creditlimit =  $("#customer_creditlimit").val();
      //var customer_limit = $("#customer_total_amount").val();
      // creditlimit     = creditlimit.replace(",","");
      //customer_limit     = customer_limit.replace(",","");
      //if($("#customer_payment_terms").val() > 1 )
      //{
      //  //alert(parseFloat(creditlimit) + '>' + parseFloat(customer_limit));
      //   if( parseFloat(creditlimit) > parseFloat(customer_limit))
      //   {
      //      loadpromotiondata();
      //   }
      //   else
      //   {
      //       jAlert("Credit Limit Exceeded.");
      //   }
      //}
      //else
      //{
         loadpromotiondata();
      //}
    
   });
   
    $("#btn_save_invoice_item").click(function(){
     
        var sales_case_price = $("#txt_sale_price").val();
        var sales_pcs_price = $("#txt_sales_pcs_price").val();
        
        
        var return_case_price = $("#txt_return_price").val();
        var return_pcs_price = $("#txt_return_pcs_price").val();
        
        var good_return_case_price = $("#txt_freegood_price").val();
        var good_return_pcs_price = $("#txt_freegood_pcs_price").val();
        
        var costcaseprice = $("#txt_costcaseprice").val();
        var defaultcostprice = $("#txt_defaultcostprice").val();
     
     
        if(parseFloat(costcaseprice) > parseFloat(sales_case_price))
        {
           jAlert("Sales Case Price is Grater then "+costcaseprice);
           return false;
        }
         if(parseFloat(costcaseprice)  > parseFloat(good_return_case_price))
        {
           jAlert("Good Return Case Price is Grater then "+costcaseprice);
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
         if(parseFloat(defaultcostprice) > parseFloat(good_return_pcs_price) )
        {
           jAlert("Good Return PCS Price is Grater then "+defaultcostprice);
           return false;
        }
       
       
        if(parseFloat(defaultcostprice) > parseFloat(return_pcs_price))
        {
           jAlert("Return PCS Price is Grater then "+defaultcostprice);
           return false;
        }
        var free_good_case    = (isNaN($('#txt_freegood_case').val()) || $('#txt_freegood_case').val() == "") ? 0: $('#txt_freegood_case').val();
        var free_good_pcs     = (isNaN($('#txt_freegood_pieces').val()) || $('#txt_freegood_pieces').val() == "") ? 0: $('#txt_freegood_pieces').val();
        
        var free_buy_case     = (isNaN($('#txt_buyback_case').val()) || $('#txt_buyback_case').val() == "") ? 0: $('#txt_buyback_case').val();
        var free_buy_pcs      = (isNaN($('#txt_buyback_pieces').val()) || $('#txt_buyback_pieces').val() == "") ? 0: $('#txt_buyback_pieces').val();
        
        var free_damage_case  = (isNaN($('#txt_damage_case').val()) || $('#txt_damage_case').val() == "") ? 0: $('#txt_damage_case').val();
        var free_damage_pcs   = (isNaN($('#txt_damage_pieces').val()) || $('#txt_damage_pieces').val() == "") ? 0: $('#txt_damage_pieces').val();
        
        var free_expirey_case = (isNaN($('#txt_expirey_case').val()) || $('#txt_expirey_case').val() == "") ? 0: $('#txt_expirey_case').val();
        var free_expirey_pcs  = (isNaN($('#txt_expirey_pieces').val()) || $('#txt_expirey_pieces').val() == "") ? 0: $('#txt_expirey_pieces').val();
        
        var free_buyback_case = (isNaN($('#txt_return_case').val()) || $('#txt_return_case').val() == "") ? 0: $('#txt_return_case').val();
        var free_buyback_pcs = (isNaN($('#txt_return_pieces').val()) || $('#txt_return_pieces').val() == "") ? 0: $('#txt_return_pieces').val();
        
        var free_sales_case = (isNaN($('#txt_sales_case').val()) || $('#txt_sales_case').val() == "") ? 0: $('#txt_sales_case').val();
        var free_sales_pcs = (isNaN($('#txt_sales_pieces').val()) || $('#txt_sales_pieces').val() == "") ? 0: $('#txt_sales_pieces').val();
        
        var free_rental_case = (isNaN($('#txt_rental_case').val()) || $('#txt_rental_case').val() == "") ? 0: $('#txt_rental_case').val();
        var free_rental_pcs = (isNaN($('#txt_rental_pieces').val()) || $('#txt_rental_pieces').val() == "") ? 0: $('#txt_rental_pieces').val();
        
        //if((free_sales_case == "" && free_sales_pcs == "") && (free_good_case == "" && free_good_pcs == "") && (free_buyback_case == "" && free_buyback_pcs == "") && (free_buy_case == "" && free_buy_pcs == "") && (free_damage_case == "" && free_damage_pcs == "") && (free_expirey_case == "" && free_expirey_pcs == "") && (free_rental_case == "" && free_rental_pcs == ""))
        //{
        //    jAlert("Please Enter Value.");
        //    return false;
        //}
        var display_flag = true;
        $('input[type="text"][id^="txt"][id$="case"],input[type="text"][id^="txt"][id$="pieces"]').each(function() {
            
            if($(this).val() != "" && $(this).val() != 0)
            {
                display_flag = false;
            }
        });
        
        if(display_flag)
        {
            jAlert("Please Enter Value.");
            return false;
        }
        if((free_good_case != "" && free_good_case != 0) || (free_good_pcs != "" && free_good_pcs != 0))
        {
            $("#ddfreegoodreason").addClass('required');
            $("#ddfreegoodreason").trigger("liszt:updated");
        }
        else
        {
            $("#ddfreegoodreason").removeClass('required');
            $("#ddfreegoodreason").trigger("liszt:updated");
        }
        if((free_buy_case!= "" && free_buy_case != 0) || (free_buy_pcs != "" && free_buy_pcs != 0))
        {
           $("#ddbuybackreason").addClass('required');
           $("#ddbuybackreason").trigger("liszt:updated");
        }
        else
        {
           $("#ddbuybackreason").removeClass('required');
           $("#ddbuybackreason").trigger("liszt:updated");
        }
        if((free_damage_case != "" && free_damage_case != 0) || (free_damage_pcs != "" && free_damage_pcs != 0))
        {
           $("#dddamagereason").addClass('required');
           $("#dddamagereason").trigger("liszt:updated");
        }
        else{
           
           $("#dddamagereason").removeClass('required');
           $("#dddamagereason").trigger("liszt:updated");     
        }
        if((free_expirey_case!= "" && free_expirey_case != 0) || (free_expirey_pcs != "" && free_expirey_pcs != 0))
        {
           $("#ddexpiryreason").addClass('required');
           $("#ddexpiryreason").trigger("liszt:updated");
        }
        else
        {
           $("#ddexpiryreason").removeClass('required');
           $("#ddexpiryreason").trigger("liszt:updated");
        }
        if((free_buyback_case!= "" && free_buyback_case != 0) || (free_buyback_pcs != "" && free_buyback_pcs != 0))
        {
           $("#ddreturnreason").addClass('required');
           $("#ddreturnreason").trigger("liszt:updated");
        }
        else
        {
           $("#ddreturnreason").removeClass('required');
           $("#ddreturnreason").trigger("liszt:updated");
        }
        
            if($("#frminvoiceitem").valid())
            {
                var checkflag = false;
                var upc = parseInt($("#txt_upc").val());
                if((free_buyback_case != "" && free_buyback_case != 0) || (free_buyback_pcs != "" && free_buyback_pcs != 0))
                {
                    var return_new_qnt = parseInt(parseInt(free_buyback_case * upc) + parseInt(free_buyback_pcs));
                    var return_popup_qnt = parseInt($('#hid_return_qty_val').val());
                    if(isNaN(return_new_qnt)) { return_new_qnt = 0; }
                    //alert(return_new_qnt +"---"+ return_popup_qnt);
                    if(return_new_qnt != return_popup_qnt) {
                        checkflag = true;
                    }
                }
                
                if((free_buy_case != "" && free_buy_case != 0) || (free_buy_pcs != "" && free_buy_pcs != 0))
                {
                    var buyback_new_qnt = parseInt(parseInt(free_buy_case * upc) + parseInt(free_buy_pcs));
                    var buyback_popup_qnt = parseInt($('#hid_buyback_qty_val').val());
                    if(isNaN(buyback_new_qnt)) { buyback_new_qnt = 0; }
                    //alert(buyback_new_qnt +"---"+ buyback_popup_qnt);
                    if(buyback_new_qnt != buyback_popup_qnt) {
                        checkflag = true;
                    }
                }
                
                if((free_damage_case != "" && free_damage_case != 0) || (free_damage_pcs != "" && free_damage_pcs != 0))
                {
                    var damage_new_qnt = parseInt(parseInt(free_damage_case * upc) + parseInt(free_damage_pcs));
                    var damage_popup_qnt = parseInt($('#hid_damage_qty_val').val());
                    if(isNaN(damage_new_qnt)) { damage_new_qnt = 0; }
                    //alert(damage_new_qnt +"---"+ damage_popup_qnt);
                    if(damage_new_qnt != damage_popup_qnt) {
                        checkflag = true;
                    }
                }
                
                if((free_expirey_case != "" && free_expirey_case != 0) || (free_expirey_pcs != "" && free_expirey_pcs != 0))
                {
                    var expirey_new_qnt = parseInt(parseInt(free_expirey_case * upc) + parseInt(free_expirey_pcs));
                    var expirey_popup_qnt = parseInt($('#hid_expirey_qty_val').val());
                    if(isNaN(expirey_new_qnt)) { expirey_new_qnt = 0; }
                    //alert(expirey_new_qnt +"---"+ expirey_popup_qnt);
                    if(expirey_new_qnt != expirey_popup_qnt) {
                        checkflag = true;
                    }
                }
                var allowbatchentry = $("#hid_check_allowbatchentry").val();
                if(allowbatchentry == "0") {
                    checkflag = false;
                }
                if(checkflag) {
                    jAlert("Please Select Batch");
                    return false;
                } else {
                    saveitemdata();
                    return true;
                }
            }
            return false;
       });

    
//end off   
});