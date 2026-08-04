function clear_detail_1_screen_data()
{
    $("#txt_order_no").val("");
    $("#txt_doc_no").val("");
    $("#txt_salesman").val();
    $("#txt_customer_address").val("");
    $("#customer_payment_terms_text").val("");
    $("#txt_customer_creditlimit").val("");
    $("#txt_customer_credit_day").val("");
    $("#txt_lpo_number").val("");
}

function clear_detail_2_screen_data()
{
    $("#txt_upc").val('');
    $("#txt_sale_price").val('');
    $("#txt_return_price").val('');
    $("#txt_sales_pcs_price").val('');
    $("#txt_return_pcs_price").val('');
    $("#txt_defaultcostprice").val('');
    $("#txt_costcaseprice").val('');
    $("#txt_freegood_price").val("");
    $("#txt_freegood_pcs_price").val("");
}
function clear_detail_2_screen_data1()
{
    $("#ddlitem").val("");
    $("#ddlitem").trigger("liszt:updated");
    $("#txt_sales_case").val("");
    $("#txt_sales_pieces").val("");
    $("#txt_upc").val("");
    
    $("#txt_damage_cases").val("");
    $("#txt_damage_pieces").val("");
    
    $("#txt_freegood_case").val("");
    $("#txt_freegood_pieces").val("");
    $("#txt_expirey_case").val("");
    $("#txt_expirey_pieces").val("");
    
    $("#txt_sale_price").val("");
    $("#txt_return_price").val("");
    $("#txt_return_price").val("");
    
    $("#txt_sales_pcs_price").val("");
    $("#txt_return_pcs_price").val("");
    
    $("#txt_return_cases").val("");
    $("#txt_return_pieces").val("");
    
    $("#txt_defaultcostprice").val('');
    $("#txt_costcaseprice").val('');
    
    $("#txt_freegood_price").val("");
    $("#txt_freegood_pcs_price").val("");
    
   
}