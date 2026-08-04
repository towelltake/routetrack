$(function()
{//start Route process

  /**************** Signature  tab JS  - START **************************/
  
  $("#btn_prev_signature").click(function(){
      
     if($("#Enable_Order_Promotion").val() == 1)
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
    
    // $.post(save_page_URL+"signaturedata/", $("#frmdet").serialize(), function(data) {
            generate_invoicedocument_no();
    // });
      
  });

//end off   
});