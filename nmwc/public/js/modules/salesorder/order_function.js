function hidealltab()
{
   $("#tab1").hide();
   $("#tab2").hide();
   $("#tab3").hide();
   $("#tab4").hide();
   $("#tab5").hide();
   $('a').removeClass('admin_active tablink');
}
function loadpromotiondata()
{
     var in_customer_code = $("#ddlcustomer").val();
     
     if($("#Enable_Order_Promotion").val() == 1)
     { 
         $.ajax
          ({
              type: "POST",
              url: salesorder_promotion_grid+"/customer_code/"+in_customer_code,
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
                  $("#total_amount_pro").val($("#customer_total_amount").val());
                  $("#txt_final_amount_sig").val($("#total_amount_pro").val());
              }
               
          });
     }
     else{
          hidealltab();
          $("#tab5").show();
          $("#t5").addClass("admin_active");
          $("#t5").attr("disable","1");
          getsignaturevalue(base_url);
          $("#txt_final_amount_sig").val($("#customer_total_amount").val());
          $("#txt_amount_due_sig").val($("#customer_total_amount").val());
          $("#txt_discount_amount").val('0');
     }
 
}

function saveitemdata()
{
   $.blockUI({ message: please_wait_msg });
   $.post(save_page_URL+"saveinvoiceitem/", $("#frmorderitem").serialize(), function(data) {
    
      loaddata(salesorder_item_grid+"/key/"+data,'item-grid');               
          clear_detail_2_screen_data1();            
                    
     });
}

function generate_invoicedocument_no()
{
   $.blockUI({ message: please_wait_msg });   
   $.ajax
   ({
       type: "POST",
       url: ajax_page_URL+"generateinvoicenumber",
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
        
        string  = "in_customercode/"+in_customercode+"/in_visitkey/"+in_visitkey+"/in_routekey/"+in_routekey+"/assignment_no/"+assignment_no+"/plan_no/"+plan_no+"/range/"+range+"/in_promotiontypecode/"+promotiontypecode+"/qualification_group_no/"+qaulification_group_no;;
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
                    $("#promotion_fixed_amt_"+chk_id).val("0");
                    $("#repeatingrange_val_"+chk_id).val("0");
                }
                else
                {
                    $("#"+chk_id).html("1");
                    $("#val_"+chk_id).val("1");
                    $("#promotion_sales_qty_"+chk_id).val(data.sales_qty);
                    $("#promotion_sales_amt_"+chk_id).val(data.sales_amt);
                    $("#promotion_fixed_amt_"+chk_id).val(data.promotionamount);
                    $("#repeatingrange_val_"+chk_id).val(data.repeatingrange);
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
                   $("#order_summery_data").html(data.responseText);
                 
                  $("#txt_discount_amount").val($("#summery_fianl_total_discount").val());
	        }
	    }); 
      //if($("#customer_payment_terms").val() == 2)
      //       {
      //          $("#btn_next_signature").val("Finish");
      //       }
}