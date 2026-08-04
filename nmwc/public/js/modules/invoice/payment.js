$(function()
{//start Route process

  /**************** Signature  tab JS  - START **************************/
  
  $("#btn_prev_signature").click(function(){
      
      
       if($("#Enable_Sales_Promotion").val() != 0)
        {
            if($("#no_promotion_found") && $("#no_promotion_found").val() == 0)
            {
              hidealltab();
              $("#tab3").show();
              $("#t3").addClass("admin_active");
            }
            else
            {
              hidealltab();
              $("#tab4").show();
              $("#t4").addClass("admin_active");
            }
        }
        else
        {
            hidealltab();
            $("#tab2").show();
            $("#t2").addClass("admin_active");
        }
        
       
       
	
        
   });
 
   $("#btn_next_signature").click(function(){
    
     $.post(save_page_URL+"signaturedata/", $("#frmdet").serialize(), function(data) {
      
    
       if($("#customer_payment_terms").val() == 2)
       {
             generate_invoicedocument_no('');
       }
       else
       {
	  var amount_due    = $("#txt_amount_due_sig").val();
	  amount_due            = amount_due.replace(",","");
	    $(".only_cheque_detial").hide();
	  if($("#customer_payment_terms").val() == 0 || ($("#customer_payment_terms").val() == 4))
	  {
	    $(".only_cheque_detial").hide();
	     var html1 ='<option value="0">CASH</option>';
	     
	    $("#ddl_payment_mode").html(html1);
	    $("#ddl_payment_mode").trigger("liszt:updated");
	    
	  }
          else
          {
            $(".only_cheque_detial").hide();
	     var html1 ='<option value="0">CASH</option>';
              html1 = html1 + '<option value="1">CHEQUE</option>';
	     
	    $("#ddl_payment_mode").html(html1);
	    $("#ddl_payment_mode").trigger("liszt:updated");
          }
          $("#txt_amount_due_cash").val(formatNumber(amount_due,2));
            hidealltab();
            $("#tab6").show();
            $("#t6").addClass("admin_active");
            $("#t6").attr("disable","1"); 
		//$.ajax
		//({
		//    type: "POST",
		//    url: ajax_page_URL+"getamountnontcitem/total_amount/"+amount_due,
		//    data:'',
		//    cache: false,
		//    complete: function(data)
		//    {
		//      var tc_amm =data.responseText;
		//     
		//      $("#txt_amount_due_cash").val(amount_due);
		//      if(parseFloat(tc_amm) >  parseFloat(amount_due))
		//       $("#txt_min_amount_pay_cash").val(amount_due);
		//       else
		//       $("#txt_min_amount_pay_cash").val(tc_amm);
		//      
		//       hidealltab();
		//      $("#tab6").show();
		//      $("#t6").addClass("admin_active");
		//      $("#t6").attr("disable","1"); 
		//      
		//    }
		//});
      }
     });
      
  });
  
  /**************** End of Signature tab JS  - START **************************/ 
   $("#btn_prev_cashcollection").click(function(){
	 hidealltab();
        $("#tab5").show();
        $("#t5").addClass("admin_active");
      
     }); 
   
  $("#btn_finish_cashcollection").click(function(){
      generate_invoicedocument_no($("#payment_type_detail_7").val());
    
  });
  
  $("#btn_save_cash_collection_data").click(function(){
   
    if($("#frmcashcheck").valid())
    {            
    
        var amount_due_original = $("#txt_amount_due_cash").val();
        amount_due_original   = amount_due_original.replace(",","");
        var  amount_arr  	    = amount_due_original.split(".");
        
        var min_amount_pay_original = $("#txt_min_amount_pay_cash").val();
        min_amount_pay_original   = min_amount_pay_original.replace(",","");
        var  min_amount_arr         = min_amount_pay_original.split(".");
        
        var user_enter_amount 	= $("#txt_user_amount_due").val();
        user_enter_amount  	= user_enter_amount.replace(",","");
        var  user_amount_arr        = user_enter_amount.split(".");
        
        if(parseFloat(amount_due_original) < 0 && parseFloat(min_amount_pay_original) < 0) {
            if(parseFloat(amount_due_original) > parseFloat(min_amount_pay_original)) {
                if(parseFloat(user_enter_amount) <= parseFloat(amount_due_original) && parseFloat(user_enter_amount) >= parseFloat(min_amount_pay_original)) {
                    save_cash_data();
                } else {
                    jAlert("Enter Min Due Amount");
                }
            } else {
                if(parseFloat(user_enter_amount) >= parseFloat(amount_due_original) && parseFloat(user_enter_amount) <= parseFloat(min_amount_pay_original)) {
                    save_cash_data();
                } else {
                    jAlert("Enter Min Due Amount");
                }
            }
        } else if(parseFloat(amount_due_original) > 0 && parseFloat(min_amount_pay_original) > 0) {
            if(parseFloat(amount_due_original) > parseFloat(min_amount_pay_original)) {
                if(parseFloat(user_enter_amount) <= parseFloat(amount_due_original) && parseFloat(user_enter_amount) >= parseFloat(min_amount_pay_original)) {
                    save_cash_data();
                } else {
                    jAlert("Enter Min Due Amount");
                }
            } else {
                if(parseFloat(user_enter_amount) >= parseFloat(amount_due_original) && parseFloat(user_enter_amount) <= parseFloat(min_amount_pay_original)) {
                    save_cash_data();
                } else {
                    jAlert("Enter Min Due Amount");
                }
            }
        } else {
            if(parseFloat(amount_due_original) > parseFloat(min_amount_pay_original)) {
                if(parseFloat(user_enter_amount) <= parseFloat(amount_due_original) && parseFloat(user_enter_amount) >= parseFloat(min_amount_pay_original)) {
                    save_cash_data();
                } else {
                    jAlert("Enter Min Due Amount");
                }
            } else {
                if(parseFloat(user_enter_amount) >= parseFloat(amount_due_original) && parseFloat(user_enter_amount) <= parseFloat(min_amount_pay_original)) {
                    save_cash_data();
                } else {
                    jAlert("Enter Min Due Amount");
                }
            }
        }
        
    }
    
   
   
      
      
  });
  
  $("#ddl_payment_mode").change(function(){
      
	  if(this.value == 1)
	  {
		$(".only_cheque_detial").show();
	  }
	  else
	  {
		$(".only_cheque_detial").hide();
	  }
	  
     $("#txt_cheques").removeClass('required');
      $("#txt_date").removeClass('required');
      $("#ddl_bank").removeClass('required');
      $("#ddl_bank").trigger("liszt:updated");
    if($(this).val() == 1)
    {
       $("#txt_cheques").addClass('required');
       $("#txt_date").addClass('required');
       $("#ddl_bank").addClass('required');
       $("#ddl_bank").addClass("liszt:updated");
    }
    
  });
//end process
});