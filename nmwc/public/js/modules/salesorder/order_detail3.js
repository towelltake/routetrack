$(function()
{//start Route process

  $("#btn_previous_promotion").click(function(){
      
        hidealltab();
        $("#tab2").show();
        $("#t2").addClass("admin_active");
   });
  
   $("#btn_next_promotion").click(function(){
      
        $.post(save_page_URL+"promotionsave/", $("#frmpromotion").serialize(), function(data) {
      
           
            var string ='';
             hidealltab();
            $("#tab4").show();
            $("#t4").addClass("admin_active");
            $("#t4").attr("disable","1"); 
            $("#review_promotion").html("<img src='"+base_url+"/public/images/ajax-loader-bar.gif' alt='Loading..' />"); 
             $.ajax
	    ({
		type: "POST",
		url: ajax_page_URL+"checkpromotionreview/",
		data:string,
		cache: false,
		complete: function(data)
		{
                  if(data.responseText != "$::$")
                  {
                        
		   $("#review_promotion").html(data.responseText);
                   $("#txt_amount_due_sig").val($("#final_total_amount_last").val());
                  }
                  else
                  {     $("#review_promotion").html("<input type='hidden' name='no_promotion_found' id='no_promotion_found' value='0'/>");
                         hidealltab();
                         $("#tab5").show();
                         $("#t5").addClass("admin_active");
                         $("#t5").attr("disable","1");
                         getsignaturevalue(base_url);
                         $("#txt_amount_due_sig").val($("#txt_final_amount_sig").val());
                        
                  }
	        }
	    }); 
      });
        
  });
   
    /**************** Promotion Overviwe tab JS  - START **************************/
    
      $("#btn_prev_promotion_overview").click(function()
      {
      
          hidealltab();
          $("#tab3").show();
          $("#t3").addClass("admin_active");
      });
      $("#btn_next_promotion_overview").click(function(){
      
      
       $.post(save_page_URL+"promotionreviewsave/", $("#frmpromotionreview").serialize(), function(data)
       {
          
            var str           = data;
            var substr        = str.split('$::$');
            var tc_amm        = $("#customer_tc_amount").val();
            tc_amm            = tc_amm.replace(",","");
            var oringimm      = substr[1];
            oringimm          = oringimm.replace(",","");
                                     
            $("#txt_order_no").val($("#txt_invoice_no").val());
            $("#txt_minimum_due_cash").val(parseFloat(oringimm) - parseFloat(tc_amm));
           
            hidealltab();
              $("#tab5").show();
              $("#t5").addClass("admin_active");
              $("#t5").attr("disable","1");
              getsignaturevalue(base_url);
           
                       
           
        
       });
       
    
  });
    
     /**************** Promotion Overviwe tab JS  - End **************************/

//end off   
});