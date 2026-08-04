$(function()
{//start Route process
   
   $("#ddlroute").change(function() {
	clear_detail_1_screen_data();
        
        $.blockUI({ message: please_wait_msg });
        
        var str = $(this).find("option:selected").text();
	var substr = str.split(' -- ');
	$("#txt_salesman").attr("value",substr[1]);
       
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
             url: ajax_page_URL+"generateinvoiceno/route_code/"+$(this).val(),
             data:string=$(this).val(),
             cache: false,
             complete: function(data)
             {
                var data1 =data.responseText;
                var substr = data1.split(' -- ');
                $("#txt_invoice_no").val(substr[0]);
                $("#txt_doc_no").val(substr[1]);
             }
         });
       
       
    });
   
    $("#ddlcustomer").change(function() {
	
        var str = $(this).find("option:selected").text();
        var substr = str.split(' -- ');
        $("#txt_customer_name").attr("value",substr[1]);
        $("#detail_2_customer").html(substr[1]);
        $("#detail_3_customer").html(substr[1]);
        $("#detail_6_customer").html(substr[1]);
       
        
	$("#txt_customer_no").val(substr[0]);
         $.ajax
         ({
             type: "POST",
             url: ajax_page_URL+"getvalidation/customer_code/"+ $(this).val(),
             data:string=$(this).val(),
             cache: false,
             complete: function(data)
             {
               $("#settings_value").html(data.responseText);
               $("#customer_payment_terms_text").val($("#addinvoice_customer_payment_terms_text").val());
               $("#txt_customer_credit_day").val($("#addinvoice_txt_customer_credit_day").val());
               $("#txt_customer_creditlimit").val($("#customer_creditlimit").val());
               $("#txt_customer_address").val($("#addinvoice_txt_customer_address").val());
             }
         });
       
   
    });
   
   $("#btn_next_add_invoice").click(function(){
        
        if($("#add_invoice").valid())
        {
            $.ajax
            ({
               type: "POST",
               url: ajax_page_URL+"checkprocessload/routeid/"+$("#ddlroute").val(),
               data:$("#add_invoice").serialize(),
               success:function(data) {            
                    if(data == 0)
                    {
                       jAlert('Please Create Process Load First.');
                       return false;
                    }
                    else
                    {
                       saveheaderdata();
                       return true;
                    }
                }
            })
        }
    });
   
   
    function saveheaderdata()
   {
            $.post(SaveURL, $("#add_invoice").serialize(), function(data)
            {
             
               hidealltab();
               $("#tab2").show();
               $("#t2").addClass("admin_active");
               $("#t2").attr("disable","1"); 
               $.blockUI({ message: please_wait_msg });
               $.ajax
               ({
                   type: "POST",
                   url: ajax_page_URL+"itemlist/route_code/"+$("#ddlroute").val(),
                   data: dataString='',
                   cache: false,
                   complete: function(html)
                   {
                      clear_detail_2_screen_data1();
                       $("#ddlitem").html(html.responseText);
                       $("#ddlitem").trigger("liszt:updated");
                      
                       if($("#Enable_Good_Returns").val() == 3  || $("#Enable_Good_Returns").val() == 4)
                        {
                           $('#txt_return_cases').removeAttr('readonly');
                           $('#txt_return_pieces').removeAttr('readonly');
                        }
                        else
                        {
                           $('#txt_return_cases').attr('readonly', 'readonly');
                           $('#txt_return_pieces').attr('readonly', 'readonly');
                        }
                         if($("#Enable_Free").val() == 3  || $("#Enable_Free").val() == 4)
                        {
                           $('#txt_freegood_case').removeAttr('readonly');
                           $('#txt_freegood_pieces').removeAttr('readonly');
                        }
                        else
                        {
                           $('#txt_freegood_case').attr('readonly', 'readonly');
                           $('#txt_freegood_pieces').attr('readonly', 'readonly');
                        }
                        
                        if($("#Enable_Sales").val() == 1 )
                        {
                            $('#txt_sales_case').removeAttr('readonly');
                            $('#txt_sales_pieces').removeAttr('readonly');
                        }
                        else
                        {
                           $('#txt_sales_case').attr('readonly', 'readonly');
                           $('#txt_sales_pieces').attr('readonly', 'readonly');
                        }
                        if($("#Enable_Rental").val() == 1 )
                        {
                            $('#txt_rental_case').removeAttr('readonly');
                            $('#txt_rental_pieces').removeAttr('readonly');
                        }
                        else
                        {
                           $('#txt_rental_case').attr('readonly', 'readonly');
                           $('#txt_rental_pieces').attr('readonly', 'readonly');
                        }
                        if($("#Enable_Buy_Back_Free").val() == 1 )
                        {
                            $('#txt_buyback_case').removeAttr('readonly');
                            $('#txt_buyback_pieces').removeAttr('readonly');
                        }
                        else
                        {
                           $('#txt_buyback_case').attr('readonly', 'readonly');
                           $('#txt_buyback_pieces').attr('readonly', 'readonly');
                        }
                        
                        if($("#Enable_Damage_Returns").val() == 3  || $("#Enable_Damage_Returns").val() == 4)
                        {
                           $('#txt_damage_case').removeAttr('readonly');
                           $('#txt_damage_pieces').removeAttr('readonly');
                           
                           $('#txt_expirey_case').removeAttr('readonly');
                           $('#txt_expirey_pieces').removeAttr('readonly');
                        }
                        else
                        {
                           $('#txt_damage_case').attr('readonly', 'readonly');
                           $('#txt_damage_pieces').attr('readonly', 'readonly');
                           
                           $('#txt_expirey_case').attr('readonly', 'readonly');
                           $('#txt_expirey_pieces').attr('readonly', 'readonly');
                        }
                        if($("#Enable_Edit_Price_Invs").val() != "")
                        {
                           //1 =Disable
                           //   SalesPrice   Good Retrun Price   Bad Return price
                           // 2  Y           Y                    Y
                           // 3  X           Y                    Y
                           // 4  Y           Y                    X
                           // 5  Y           X                    Y
                           // 6  Y           X                    X
                           // 7  X           Y                    X
                           // 8  X           X                    Y
                           $('#txt_sale_price').attr('readonly', 'readonly');
                           $('#txt_sales_pcs_price').attr('readonly', 'readonly');
                           $('#txt_return_price').attr('readonly', 'readonly');
                           $('#txt_return_pcs_price').attr('readonly', 'readonly');
                            $('#txt_freegood_price').attr('readonly', 'readonly');
                           $('#txt_freegood_pcs_price').attr('readonly', 'readonly');
                           
                           if($("#Enable_Edit_Price_Invs").val() == 2 || $("#Enable_Edit_Price_Invs").val() == 4 || $("#Enable_Edit_Price_Invs").val() == 5 || $("#Enable_Edit_Price_Invs").val() == 6)
                           {
                               $('#txt_sale_price').removeAttr('readonly');
                               $('#txt_sales_pcs_price').removeAttr('readonly');
                           }
                           if($("#Enable_Edit_Price_Invs").val() == 2 || $("#Enable_Edit_Price_Invs").val() == 3 || $("#Enable_Edit_Price_Invs").val() == 4 || $("#Enable_Edit_Price_Invs").val() == 7)
                           {
                               $('#txt_freegood_price').removeAttr('readonly');
                               $('#txt_freegood_pcs_price').removeAttr('readonly');
                           }
                           if($("#Enable_Edit_Price_Invs").val() == 2 || $("#Enable_Edit_Price_Invs").val() == 3 || $("#Enable_Edit_Price_Invs").val() == 5 || $("#Enable_Edit_Price_Invs").val() == 8)
                           {
                               $('#txt_return_price').removeAttr('readonly');
                               $('#txt_return_pcs_price').removeAttr('readonly');
                           }
                           
                           
                        }
                        
                      
                   }
               });
         });
   }
      
   
//end offf   
});