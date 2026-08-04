function hidealltab()
{
   $("#tab1").hide();
   $("#tab2").hide();
   $("#tab3").hide();
   $("#tab4").hide();
   $("#tab5").hide();
   $("#tab6").hide();
   $('a').removeClass('admin_active tablink');
}
function loadpromotiondata()
{
    if($('#totalitem_list').length <= 0) {
        jAlert("Please Add Item.");
        return false;
    } else if($('#totalitem_list').length > 0 && $('#totalitem_list').val() == 0){
        jAlert("Please Add Item.");
        return false;
    }
    var in_customer_code = $("#ddlcustomer").val();
    var customerbal = parseFloat($("#customer_total_amount").val().replace(/\,/g,''));
    var customer_payment_terms = $("#customer_payment_terms").val();
    if(customer_payment_terms > 1 && customerbal > 0.00) {
        $.ajax
        ({
            type: "POST",
            url: ajax_page_URL+"checkcreditlimit/customerbal/"+customerbal,
            data:'',
            cache: false,
            success: function(data)
            {
                if(data == "0") {
                    jAlert("Availble Credit Limit Exceed.");
                    return false;
                } else {
                    
                    if($("#Enable_Sales_Promotion").val() != 0)
                    {
                        $.ajax
                        ({
                            type: "POST",
                            url: Invoice_promotion_grid+"/customer_code/"+in_customer_code,
                            data:'',
                            cache: false,
                            complete: function(data)
                            {
                            
                                hidealltab();
                                $("#tab3").show();
                                $("#t3").addClass("admin_active");
                                $("#t3").attr("disable","1"); 
                                var final_data =data.responseText;
                                $("#promotion-grid").html(final_data);
                                $("#total_invoice_amount_pro").val($("#customer_total_amount").val());
                                $("#txt_final_amount_sig").val($("#total_invoice_amount_pro").val());
                                //$("#txt_tc_amount_sig").val($("#customer_tc_amount").val());
                                //var minamt = parseFloat(parseFloat($("#customer_total_amount").val()) - parseFloat($("#customer_tc_amount").val()))
                                //$("#txt_min_amount_due_sig").val(minamt);
                                //$("#txt_min_amount_pay_cash").val(minamt);
                            }
                             
                        });
                    }
                    else
                    {
                        hidealltab();
                        $("#tab5").show();
                        $("#t5").addClass("admin_active");
                        $("#t5").attr("disable","1");
                        getsignaturevalue(base_url);
                        $("#txt_final_amount_sig").val($("#customer_total_amount").val());
                        $("#txt_amount_due_sig").val($("#customer_total_amount").val());
                        $("#txt_discount_amount").val('0');
                        //$("#txt_tc_amount_sig").val($("#customer_tc_amount").val());
                        //var minamt = parseFloat(parseFloat($("#customer_total_amount").val()) - parseFloat($("#customer_tc_amount").val()))
                        //$("#txt_min_amount_due_sig").val(minamt);
                        //$("#txt_min_amount_pay_cash").val(minamt);
                    }
                }
            }
        });
    }
    else {
        
        if($("#Enable_Sales_Promotion").val() != 0)
        {
            $.ajax
            ({
                type: "POST",
                url: Invoice_promotion_grid+"/customer_code/"+in_customer_code,
                data:'',
                cache: false,
                complete: function(data)
                {
                
                    hidealltab();
                    $("#tab3").show();
                    $("#t3").addClass("admin_active");
                    $("#t3").attr("disable","1"); 
                    var final_data =data.responseText;
                    $("#promotion-grid").html(final_data);
                    $("#total_invoice_amount_pro").val($("#customer_total_amount").val());
                    $("#txt_final_amount_sig").val($("#total_invoice_amount_pro").val());
                    //$("#txt_tc_amount_sig").val($("#customer_tc_amount").val());
                    //var minamt = parseFloat(parseFloat($("#customer_total_amount").val()) - parseFloat($("#customer_tc_amount").val()))
                    //$("#txt_min_amount_due_sig").val(minamt);
                    //$("#txt_min_amount_pay_cash").val(minamt);
                }
                 
            });
        }
        else
        {
            hidealltab();
            $("#tab5").show();
            $("#t5").addClass("admin_active");
            $("#t5").attr("disable","1");
            getsignaturevalue(base_url);
            $("#txt_final_amount_sig").val($("#customer_total_amount").val());
            $("#txt_amount_due_sig").val($("#customer_total_amount").val());
            $("#txt_discount_amount").val('0');
            //$("#txt_tc_amount_sig").val($("#customer_tc_amount").val());
            //var minamt = parseFloat(parseFloat($("#customer_total_amount").val()) - parseFloat($("#customer_tc_amount").val()))
            //$("#txt_min_amount_due_sig").val(minamt);
            //$("#txt_min_amount_pay_cash").val(minamt);
        }
    }
    
    
}

function saveitemdata()
{
   $.blockUI({ message: please_wait_msg });
   var sales =0;
   var return1 =0;
   
   if($("#txt_sales_case").val() != "")
   sales = 1;
   
   if($("#txt_sales_pieces").val() != "")
   sales = 1;
  
   if($("#txt_freegood_case").val() != "")
   return1 = 1;
   
   if($("#txt_freegood_pieces").val() != "")
   return1 = 1; 
   
   
    //if(sales == 1 || return1 == 1)
    //{
        $.post(save_page_URL+"saveinvoiceitem/", $("#frminvoiceitem").serialize(), function(data) {
            $("#divid").show();
            loaddata(Invoice_item_grid+"/key/"+data,'item-grid');
            clear_detail_2_screen_data1();
        });
    //}
   
    //$.ajax
    //  ({
    //      type: "POST",
    //      url: ajax_page_URL+"checkbatchadded/sales/"+sales+"/return1/"+return1,
    //      data:'',
    //      cache: false,
    //      complete: function(data)
    //      {
    //        var final_data =data.responseText;
    //         var final_data =data.responseText;
    //        var data_arr=final_data.split("$::$");
    //          
    //        if(sales == 1 &&  return1 == 1)
    //        {
    //            if(data_arr[0] ==  1  && data_arr[1] == 1)
    //            {
    //                 $.post(save_page_URL+"saveinvoiceitem/", $("#frminvoiceitem").serialize(), function(data) {
    //                 $("#divid").show();
    //                 loaddata(Invoice_item_grid+"/key/"+data,'item-grid');               
    //                 clear_detail_2_screen_data1();            
    //                               
    //                });
    //            }
    //            else
    //            {
    //                if(data_arr[0] == 0  )
    //                {
    //                 jAlert("Please Check Available Qty");
    //                }
    //                else if(data_arr[1] == 0)
    //                {
    //                 jAlert("Please Add New Batch and Qty");
    //                }
    //                
    //            }
    //           
    //           
    //        }
    //        else
    //        {
    //           if(sales == 1)
    //           {
    //              if(data_arr[0] ==  1 )
    //                 {
    //                      $.post(save_page_URL+"saveinvoiceitem/", $("#frminvoiceitem").serialize(), function(data) {
    //                      $("#divid").show();
    //                      loaddata(Invoice_item_grid+"/key/"+data,'item-grid');               
    //                      clear_detail_2_screen_data1();            
    //                                    
    //                     });
    //                 }
    //                 else
    //                 {
    //                    
    //                      jAlert("Please Check Available Qty");
    //                   
    //                 }
    //           }
    //           else if( return1 == 1)
    //           {
    //              if(data_arr[1] ==  1 )
    //                 {
    //                      $.post(save_page_URL+"saveinvoiceitem/", $("#frminvoiceitem").serialize(), function(data) {
    //                      $("#divid").show();
    //                      loaddata(Invoice_item_grid+"/key/"+data,'item-grid');               
    //                      clear_detail_2_screen_data1();            
    //                                    
    //                     });
    //                 }
    //                 else
    //                 {
    //                    
    //                     jAlert("Please Add New Batch and Qty");
    //                   
    //                 }
    //           }
    //           
    //           
    //        }
    //        
    //        
    //        
    //      }
    //  });
      
}

function generate_invoicedocument_no(inpaymenttype)
{
       var  GCPaymentType  ='';
       var   paymenttype ='';
      if($("#customer_payment_terms").val() == 2)
         { GCPaymentType = 2
           paymenttype =1;
         }
      else
          {
         //change by hiren dave on 9th nov 2012
         //GCPaymentType =1;
            GCPaymentType =0;
            paymenttype =inpaymenttype;
          }
   $.ajax
   ({
       type: "POST",
       url: ajax_page_URL+"generateinvoicenumber/GCPaymentType/"+GCPaymentType+"/paymenttype/"+paymenttype,
       data:string='',
       cache: false,
       complete: function(data)
       {
         //alert(data.responseText);
         window.location.href= Overview_URL;
       }
   });
}

/*###assignment_no,plan_no,range,chk_id,promotiontypecode */
 function check_promotion(assignment_no,plan_no,range,chk_id,promotiontypecode,qaulification_group_no)
{
   // alert($("#"+chk_id).checked);
    
    if ($("#chk_"+chk_id).is(':checked'))
    {
        var string ="";
        var in_customercode= $("#in_customercode").val();
        var in_visitkey= $("#in_visitkey").val();
        var in_routekey= $("#in_routekey").val();
        
        string  = "in_customercode/"+in_customercode+"/in_visitkey/"+in_visitkey+"/in_routekey/"+in_routekey+"/assignment_no/"+assignment_no+"/plan_no/"+plan_no+"/range/"+range+"/in_promotiontypecode/"+promotiontypecode+"/qualification_group_no/"+qaulification_group_no;
        // string  = "in_customercode/"+in_customercode+"/in_visitkey/"+in_visitkey+"/in_routekey/"+in_routekey+"/qualification_group_no/"+qaulification_group_no;
        $.ajax
        ({
            type: "POST",
            url: ajax_page_URL+"checkpromotion/"+string,
            data:string,
            cache: false,
            dataType:"json",
            success: function(data)
            {
                if(data.range_status == 0)
                {
                    $("#chk_"+chk_id).attr('checked',false);
                    $("#val_"+chk_id).val("0");
                    $("#promotion_sales_qty_"+chk_id).val("0");
                    $("#promotion_sales_amt_"+chk_id).val("0");
                    $("#promotion_return_qty_"+chk_id).val("0");
                    $("#promotion_return_amt_"+chk_id).val("0");
                    $("#promotion_fixed_amt_"+chk_id).val("0");
                    $("#repeatingrange_val_"+chk_id).val("0");
                    $("#return_promotion_amt_"+chk_id).val("0");
                }
                else
                {
                    $("#"+chk_id).html("1");
                    $("#val_"+chk_id).val("1");
                    $("#promotion_sales_qty_"+chk_id).val(data.sales_qty);
                    $("#promotion_sales_amt_"+chk_id).val(data.sales_amt);
                    $("#promotion_return_qty_"+chk_id).val(data.return_qty);
                    $("#promotion_return_amt_"+chk_id).val(data.return_amt);
                    $("#promotion_fixed_amt_"+chk_id).val(data.promotionamount);
                    $("#repeatingrange_val_"+chk_id).val(data.repeatingrange);
                    $("#return_promotion_amt_"+chk_id).val(data.returnpromotionamt);
                }
            }
        });
    }
    else
    {
         $("#val_"+chk_id).val("0");
         $("#"+chk_id).html("0");
    }
}
    
function shownext (val)
{
  
  if( val == ""  || val == "_")
  {  }
  else
  {
        if(val != 0)
        
        {
              $(".promo").css("display", "none");
              $("#promotion"+val).show();
        }
  }
}

function getsignaturevalue(base_url)
{
    $("#invoice_summery_data").html("<img src='"+base_url+"/public/images/ajax-loader-bar.gif' alt='Loading..' />"); 
    $.ajax
	({
		type: "POST",
		url: ajax_page_URL+"invoicesummery/",
		data:string='',
		cache: false,
		complete: function(data)
		{
            if(!(($("#customer_payment_terms").val() == 3) || ($("#customer_payment_terms").val() == 4)))
            {
                $("#invoice_summery_data").html(data.responseText);
                $("#txt_discount_amount").val($("#summery_fianl_total_discount").val());
                var final_amt = parseFloat($('#txt_final_amount').val().replace(",","")).toFixed(2);
                $('#txt_amount_due_sig').val(formatNumber(final_amt,2));
                $("#txt_min_amount_due_sig").val(formatNumber(final_amt,2));
                $("#txt_min_amount_pay_cash").val(formatNumber(final_amt,2));
                
                tc_amt = 0;
                $("#txt_tc_amount_sig").val(formatNumber(tc_amt,2));
                $("#txt_allowed_tc_cc").val(formatNumber(tc_amt,2));
            }
            else
            {
                $("#invoice_summery_data").html(data.responseText);
                $("#txt_discount_amount").val($("#summery_fianl_total_discount").val());
                $('#txt_min_amount_due_sig').val($('#customer_final_non_tc_amount').val());
                var tc_amt = parseFloat(parseFloat($("#txt_amount_due_sig").val().replace(",","")) - parseFloat($('#txt_min_amount_due_sig').val().replace(",",""))).toFixed(2);
                if(!(($("#customer_payment_terms").val() == 3) || ($("#customer_payment_terms").val() == 4)))
                {
                    //$("#customer_final_tc_amount").val(0);
                    tc_amt = 0;
                }
                $("#txt_tc_amount_sig").val(formatNumber(tc_amt,2));
                $("#txt_allowed_tc_cc").val(formatNumber(tc_amt,2));
                //var minamt = parseFloat(parseFloat($("#txt_amount_due_sig").val().replace(",","")) - parseFloat($("#customer_final_tc_amount").val().replace(",",""))).toFixed(2);
                var minamt = parseFloat($('#customer_final_non_tc_amount').val().replace(",",""));
                $("#txt_min_amount_due_sig").val(formatNumber(minamt,2));
                $("#txt_min_amount_pay_cash").val(formatNumber(minamt,2));
            }
	    }
	}); 
    if($("#customer_payment_terms").val() == 2)
    {
        $("#btn_next_signature").val("Finish");
    }
}

function save_cash_data()
{
     $.blockUI({ message: please_wait_msg });
       $.post(save_page_URL+"cashcollectionsavecash/", $("#frmcashcheck").serialize(), function(data) {
            
           $.ajax
	    ({
		type: "POST",
		url: ajax_page_URL+"cashcollection/",
		data:string='',
		cache: false,
		complete: function(data)
		{
                  
                   $("#load_payment_data").html(data.responseText);
                   $("#cash_payment_grid").show();
                   clear_detail_6_screen_data();
	        }
	    });
       });
}

function addbatchdetail(thisobj,type)
{
    if($("#ddlitem").val() != "")
    {
        if($('#txt_'+type+'_case').val() != "" || $('#txt_'+type+'_pieces').val() != "")
        {
            var caseval = 0;
            var pieceval = 0;
            var qntadd = 0;
            if($('#txt_'+type+'_case').val() != "")
            {
                caseval = parseInt($("#txt_"+type+"_case").val() * $("#txt_upc").val());
            }
            if($('#txt_'+type+'_pieces').val() != "")
            {
                pieceval =  parseInt($("#txt_"+type+"_pieces").val());
            }
            var qnt = parseInt(caseval + pieceval);
            var itemcode = $("#ddlitem").val();
            if(type == "sales") {
                if($("#txt_freegood_case").val() != "")
                {
                    qntadd += parseInt($("#txt_freegood_case").val() * $("#txt_upc").val());
                }
                if($("#txt_freegood_pieces").val() != "")
                {
                    qntadd += parseInt($("#txt_freegood_pieces").val());
                }
                
                if($("#txt_rental_case").val() != "")
                {
                    qntadd += parseInt($("#txt_rental_case").val() * $("#txt_upc").val());
                }
                if($("#txt_rental_pieces").val() != "")
                {
                    qntadd += parseInt($("#txt_rental_pieces").val());
                }
                
            } else if(type == "freegood") {
                if($("#txt_sales_case").val() != "")
                {
                    qntadd += parseInt($("#txt_sales_case").val() * $("#txt_upc").val());
                }
                if($("#txt_sales_pieces").val() != "")
                {
                    qntadd += parseInt($("#txt_sales_pieces").val());
                }
                
                if($("#txt_rental_case").val() != "")
                {
                    qntadd += parseInt($("#txt_rental_case").val() * $("#txt_upc").val());
                }
                if($("#txt_rental_pieces").val() != "")
                {
                    qntadd += parseInt($("#txt_rental_pieces").val());
                }
                
            } else if(type == "rental") {
                if($("#txt_freegood_case").val() != "")
                {
                    qntadd += parseInt($("#txt_freegood_case").val() * $("#txt_upc").val());
                }
                if($("#txt_freegood_pieces").val() != "")
                {
                    qntadd += parseInt($("#txt_freegood_pieces").val());
                }
                
                if($("#txt_sales_case").val() != "")
                {
                    qntadd += parseInt($("#txt_sales_case").val() * $("#txt_upc").val());
                }
                if($("#txt_sales_pieces").val() != "")
                {
                    qntadd += parseInt($("#txt_sales_pieces").val());
                }
                
            } 
            var available_qty = 0;
            if(type != "return" && type != "expirey" && type != "damage" && type != "buyback") {
                available_qty = parseInt($("#hid_available_qty").val() - qntadd);
            }
            
            if((type != "return" && type != "expirey" && type != "damage" && type != "buyback") && (qnt > available_qty))
            {
                jAlert("Quantity not available.");
                $(thisobj).val('');
                return false;
            }
            else
            {
                if((type == "return" || type == "expirey" || type == "damage" || type == "buyback")) {
                    var allowbatchentry = $("#hid_check_allowbatchentry").val();
                    if(allowbatchentry == "0") {
                        $.ajax
                        ({
                            type: "POST",
                            url: ajax_page_URL+"onblurnewreturnavailableqty/",
                            data: {"qty":qnt,"itemcode":itemcode,"type":type},
                            success: function(data)
                            {
                                //console.log(data);
                            }
                        });
                    }
                } else {
                    $.ajax
                    ({
                        type: "POST",
                        url: ajax_page_URL+"onblurreturnavailableqty/",
                        data: {"qty":qnt,"itemcode":itemcode,"type":type},
                        success: function(data)
                        {
                            //console.log(data);
                        }
                    });
                }
            }
        }
    }
    else
    {
        jAlert("Please Select Item.");
    }
}