<?php
/**
* @name       IndexController
* @since
* @version    Release: 1
* @author     M@M <miral@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage hhctransaction module.
*/


class Hhctransaction_InvoiceprintController extends Hhctransaction_Library_Controller_Action_Abstract
{
      /**
    * @name       init
    * @since      01-02-2012
    * @version    Release: 1
    * @author     M@M <miral@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is the default function for all Actions.
    *
    */
    public function init()
    {
      
	$this->translate 	= Zend_Registry::get('Zend_Translate');
	$this->SFA_Comman	= new SFA_Comman();
	
	$this->currentUser = SFA_Loginauth::getIdentity();	
	if(!isset($this->currentUser) || empty($this->currentUser))
	{
	    SFA_Message::setMsg($this->translate->_('Do Login'));
	    //$this->_helper->redirector("index", "index", "home");
			$url = $this->view->baseUrl();
			echo '<script type="text/javascript">window.location="'.$url.'";</script>';
			exit;
	}
	$this->view->overview		= $this->translate->_('Overview');
	$this->view->details		= $this->translate->_('Details');
	$this->view->required		= $this->translate->_('Required');
	$this->view->colan		= $this->translate->_('Colan');
	
	
	$this->decimalplaces 		= $this->SFA_Comman->getdecimalplaces();
	$this->view->decimalplaces 	= $this->SFA_Comman->getdecimalplaces();
	$this->view->sec_lang		= $this->SFA_Comman->getsecondlanguage();
	$this->sec_lang 		= $this->view->sec_lang;
	$this->view->header = $this->translate->_('Header');
	$this->view->detail = $this->translate->_('Detail');
	
	
	$this->cacespcslabel		= $this->SFA_Comman->getcasepcslabel();
	$this->lblcase				= $this->cacespcslabel['0'];
	$this->lblpcs				= $this->cacespcslabel['1'];
	$this->view->lblcase		= $this->lblcase;				
	$this->view->lblpcs  		= $this->lblpcs;
    }
    
    
    /**
    * @name       preDispatch
    * @since      26- sep-2012
    * @version    Release: 1
    * @author     PT <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is the default function for all Actions.
    *
    */
    
    public function preDispatch()
    {
        parent::preDispatch();
        
        /**
         *      Acl Code start
         */
        $getparams_init = $this->getRequest()->getParams();
        $getpost_init = $this->_request->getPost();
        
        if(in_array($getparams_init['action'],$this->current_read_delete_arr))
        {
            if($getpost_init["hdDelete"]==1 && !$this->checkaccess("delete")) {
            
                $this->_forward('noaccess','aclaccess','home', array("actiontype"=>"delete","modulename"=>$this->currentmodulename));
            
            } elseif(!$this->checkaccess("read")) {
                
                $this->_forward('noaccess','aclaccess','home', array("actiontype"=>"read","modulename"=>$this->currentmodulename));
                
            } else {
                
            }
        }
        elseif(in_array($getparams_init['action'],$this->current_insert_update_arr))
        {
            if($params['id'] > 0 && !$this->checkaccess("update")) {
            
                $this->_forward('noaccess','aclaccess','home', array("actiontype"=>"update","modulename"=>$this->currentmodulename));
                
            } elseif(!$this->checkaccess("insert")) {
                
                $this->_forward('noaccess','aclaccess','home', array("actiontype"=>"insert","modulename"=>$this->currentmodulename));
                
            } else {
                
            }
        }
        /**
         *      Acl Code end
         */
    }
   
	public function exportpdfAction()
	{
	$params = $this->getRequest()->getParams();
	
	$result = $this->SFA_Comman->executequery('CALL sp_get_transaction_index_addinvoice(?)',$params['id'],'');			
	$invoice_header 	= $result[1][0];
	//var_dump($invoice_header);
	//die;

	$html ="";
	$html .= "<head><link href='../../../../public/images/favicon.ico' type='image/x-icon' rel='shortcut icon'>";
	$html .= "<title>Invoice Detail</title>";
	$html .= "<meta charset='utf-8'></head>";
	$html .="<body style='font-family: DejaVuSansCondensed, sans-serif;'>";
	$html .="<h3 style='text-align:center'>Invoice Detail</h3>";
	$html .='<table >';
	  $html .='<tr >';	 
	  $html .=' <td >';
	  $html .= "<b>Invoice Date. : </b>";
	  $html .=' </td>';
	  $html .=' <td style="width:120px;">';
	  $html .= date('d-m-Y',strtotime($invoice_header['transactiondate']));
	  $html .=' </td>';	  
	  $html .=' <td >';
	  $html .= "<b>Invoice Time. : </b>";
	  $html .=' </td>';
	  $html .=' <td >';
	  $html .= $invoice_header['transactiontime'];
	  $html .=' </td>';	  
	  $html .='</tr>';
	  $html .='<tr>';
	  $html .=' <td >';
	  $html .= "<b>Route : </b>";
	  $html .=' </td>';
	   $html .=' <td >';
	  $html .= $invoice_header['routecode']." - ".$invoice_header['routename'];
	  $html .=' </td>';
	  $html .=' <td>';
	  $html .= "<b>Salesman : </b>";
	  $html .=' </td>';	
	   $html .=' <td>';
	  $html .= $invoice_header['salesmancode']." - ".$invoice_header['salesmanname'];
	  $html .=' </td>';
	  $html .='</tr>';
	  $html .='<tr>';
	  $html .=' <td>';
	  $html .= "<b>Customer : </b>";
	  $html .=' </td>';
	  $html .=' <td colspan="3">';
	  $html .= $invoice_header['customercode']." - ".$invoice_header['customername'];
	  $html .=' </td>';
	  $html .='</tr>';
	  $html .='<tr>'; 
	  $html .=' <td>';
	  $html .= "<b>Address: </b>";
	  $html .=' </td>';
	   $html .=' <td colspan="3">';
	  $html .= $invoice_header['customeraddress1'];	 
	  $html .= ", ".$invoice_header['customeraddress2'];	  
	  $html .= ", ".$invoice_header['customeraddress3'];
	  $html .='</td>';	
	  $html .='</tr>'; 
	  $html .='<tr>';
	  $html .=' <td>';
	  $html .= "<b>Mode Of Payment : </b>";
	  $html .=' </td>';
	  $html .=' <td colspan="3">';
	  $html .= $invoice_header['paymentterm'];
	  $html .=' </td>';
	  $html .='</tr>'; 		   
	  $html .='</table>';
	  $html .="<h3 style='text-align:center'>Sales Invoice ".$invoice_header['invoicenumber']."</h3>";
	  /**/
		
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
		
		$columns_array		= array('im.actualitemcode','im.itemshortdescription','im.unitspercase',				
				'FLOOR((salesqty/im.unitspercase)) AS case_sales_qty','(salesqty%im.unitspercase) AS pcs_sales_qty',
				'FORMAT(id.salescaseprice,'.$this->decimalplaces.') AS salescaseprice','FORMAT(id.salesprice,'.$this->decimalplaces.') AS salesprice ',
				'FORMAT(((id.salescaseprice)*FLOOR(id.salesqty/im.unitspercase))+(id.salesprice*(id.salesqty%im.unitspercase)),'.$this->decimalplaces.') AS total_amt',
				'im.itemtaxkey1 as itemtaxkey1',
				 
				'FORMAT(id.salesitemexcisetax,'.$this->decimalplaces.') AS sales_excise',
				'FORMAT(id.salesitemgsttax,'.$this->decimalplaces.') AS sales_vat',	

				'FORMAT(id.returnitemexcisetax,'.$this->decimalplaces.') AS return_excise',
				'FORMAT(id.returnitemgsttax,'.$this->decimalplaces.') AS return_vat',	
				
				'FORMAT(id.damageditemexcisetax,'.$this->decimalplaces.') AS bad_return_excise',
				'FORMAT(id.damageditemgsttax,'.$this->decimalplaces.') AS bad_return_vat',			
				
				'FORMAT(id.fgitemexcisetax + id.promoitemexcisetax,'.$this->decimalplaces.') AS free_excise',
				'FORMAT(id.fgitemgsttax + id.promoitemgsttax,'.$this->decimalplaces.') AS free_vat',	

				
				
				'FLOOR((returnqty/im.unitspercase)) AS case_return_qty','(returnqty%im.unitspercase) AS pcs_return_qty',
				'FORMAT(id.returncaseprice,'.$this->decimalplaces.') AS returncaseprice','FORMAT(id.returnprice,'.$this->decimalplaces.') AS returnprice',
				'FORMAT(((id.returncaseprice)*FLOOR(returnqty/im.unitspercase))+(id.returnprice*(returnqty%im.unitspercase)),'.$this->decimalplaces.') AS ret_total_amt',
				'FLOOR((damagedqty/im.unitspercase)) AS case_damage_qty','(damagedqty%im.unitspercase) AS pcs_damage_qty',
				'FORMAT(((id.returncaseprice)*FLOOR(damagedqty/im.unitspercase))+(id.returnprice*(damagedqty%im.unitspercase)),'.$this->decimalplaces.') AS dam_total_amt',
				'FLOOR((manualfreeqty/im.unitspercase)) AS case_manualfreeqty_qty','(manualfreeqty%im.unitspercase) AS pcs_manualfreeqty_qty',
				'FLOOR((freesampleqty/im.unitspercase)) AS casediscount','(freesampleqty%im.unitspercase) AS pcsdiscount',
				'FORMAT(promoamount,'.$this->decimalplaces.') AS discount','FORMAT(discountamount,'.$this->decimalplaces.') AS discountamount','FORMAT((((FLOOR(salesqty/im.unitspercase)*id.salescaseprice)+((salesqty%im.unitspercase)*id.salesprice))-((FLOOR(returnqty/im.unitspercase)*id.returncaseprice)+((returnqty%im.unitspercase)*id.returnprice))
				-((FLOOR(damagedqty/im.unitspercase)*id.returncaseprice)+((damagedqty%im.unitspercase)*id.returnprice))-promoamount),'.$this->decimalplaces.') AS total_amount','1 AS sett_tran_type','id.transactionkey as edit_del_primary_id'
				);
				$item_code = 'actualitemcode';
		if($altcode_status) {
			$columns_array[0]	= 'im.alternatecode';
			$columns_show[0]	= $alt_code;
			$item_code = 'alternatecode';
		}
	$param_array[1] = "";
	$param_array[2] = "desc";
	$param_array[3] = 0;
	$param_array[4] = 100;
	$param_array[5] = implode(", ",$columns_array);
	$param_array[6] = "";
	$param_array[7] = $params['id'];	
	// called stored procedure for counter
	$result_grid = $this->SFA_Comman->executequery('CALL sp_get_transaction_index_invoicedetailgrid_print(?,?,?,?,?,?,?)',$param_array,'');   
	$column_model_arr = $result_grid[0];
	$salescount = $result_grid[1][0]['salescount'];
	$returncount = $result_grid[1][0]['returncount'];
	$damagecount = $result_grid[1][0]['damagecount'];
	$freecount = $result_grid[1][0]['freecount'];
	$promocount = $result_grid[1][0]['promocount'];
	/*For Sales*/
	if($salescount != "0")
	{
	  $html .='<br>';
	  $html .='<strong>Sales</strong><br>';
	  $html .='<table style="border-collapse: collapse;">';
	  $html .='<tr style=" border: dotted; border-width: 1px 0;">';		
      $html .=' <th style="height:20px;text-align:left;">';
      $html .=$this->translate->_('Item Code');
      $html .='  </th>';	
	  $html .=' <th style="height:20px;width:200px;text-align:left;">';
      $html .=$this->translate->_('Description');
      $html .='  </th>';
	  $html .=' <th style="height:20px;width:50px;">';
      $html .=$this->translate->_('UOM');
      $html .='  </th>';
	   $html .=' <th style="height:20px;width:70px;">';
      $html .=$this->translate->_('Quantity')." ".$this->translate->_("$this->lblcase")."/".$this->translate->_("$this->lblpcs");     
	  $html .=' <th style="height:20px;text-align:right;">';	  
      $html .=$this->translate->_("$this->lblcase Price");
      $html .='  </th>';
	  $html .=' <th style="height:20px;width:50px;text-align:right;">';
      $html .=$this->translate->_("$this->lblpcs Price");
      $html .='  </th>';
	  if($column_model_arr[0]['itemtaxkey1'] > 0){
	  $html .=' <th style="height:20px;width:70px;text-align:right;">';
      $html .=$this->translate->_("Excise");
      $html .='  </th>';
	  }
	   $html .=' <th style="height:20px;width:70px;text-align:right;">';
      $html .=$this->translate->_("VAT");
      $html .='  </th>';
	 
	  $html .='  <th style="height:20px;width:50px;text-align:right;">';
      $html .=$this->translate->_('Total');
      $html .='  </th>';
	  $html .='</tr>';	
	 /**/
	/**/
	
	for($j=0;$j<count($column_model_arr);$j++){
		if($column_model_arr[$j]['case_sales_qty'] != "0" || $column_model_arr[$j]['pcs_sales_qty'] != "0"){		
			$html .='<tr>';	
			$html .=' <td style="text-align:center;text-align:left;">';			
			$html .=  $column_model_arr[$j][$item_code];			
			$html .='  </td>';
			$html .=' <td style="text-align:center;text-align:left;">';			
			$html .=  $column_model_arr[$j]['itemshortdescription'];			
			$html .='  </td>';
			$html .=' <td style="text-align:center">';			
			$html .=  $column_model_arr[$j]['unitspercase'];			
			$html .='  </td>';
			$html .=' <td style="text-align:center">';			
			$html .=  $column_model_arr[$j]['case_sales_qty']."/".$column_model_arr[$j]['pcs_sales_qty'];
			$tot_cases +=$column_model_arr[$j]['case_sales_qty'];
			$tot_pieces +=$column_model_arr[$j]['pcs_sales_qty'];			
			$html .='  </td>';
			$html .=' <td style="text-align:right">';			
			$html .=  $column_model_arr[$j]['salescaseprice'];			
			$html .='  </td>';
			$html .=' <td style="text-align:right">';			
			$html .=  $column_model_arr[$j]['salesprice'];			
			$html .='  </td>';
			if($column_model_arr[0]['itemtaxkey1'] > 0){	 
			
			$html .=' <td style="text-align:right">';			
			$html .=  $column_model_arr[$j]['sales_excise'];			
			$html .='  </td>';
			}
			$html .=' <td style="text-align:right">';			
			$html .=  $column_model_arr[$j]['sales_vat'];			
			$html .='  </td>';
			
			$html .=' <td style="text-align:right">';			
			//$html .=  $column_model_arr[$j]['total_amt'];			 
            $html .=$numberString = str_replace(',','',$column_model_arr[$j]['total_amt']);			
			$tot_amt += $numberString;			
			$html .='  </td>';
			$html .='</tr>';	
			}			
		}
	/**/
	/**/
			$html .='<tr style=" border: dotted; border-width: 1px 0;">';	
			$html .=' <td colspan="2" style="text-align:center">';			
			$html .= ' <b>Total</b>';			
			$html .='  </td>';
			$html .=' <td  style="text-align:center">';			
			$html .=  "";			
			$html .='  </td>';
			$html .=' <td  style="text-align:center">';			
			$html .=  $tot_cases."/".$tot_pieces;
			$html .='  </td>';	
			$html .=' <td  style="text-align:center">';			
			$html .=  "";			
			$html .='  </td>';			
			$html .=' <td  style="text-align:center">';			
			$html .=  "";			
			$html .='  </td>';
			if($column_model_arr[0]['itemtaxkey1'] > 0){	 
			
			$html .=' <td  style="text-align:center">';			
			$html .=  "";			
			$html .='  </td>';
			}
			$html .=' <td  style="text-align:center">';			
			$html .=  "";			
			$html .='  </td>';
			
			$html .=' <td  style="text-align:right">';			
			$html .= str_replace(',','',number_format($tot_amt, $this->decimalplaces));			
			$html .='  </td>';				
			$html .='</tr>';
		/**/
	/**/
	 $html .='</table>';
	} 
	  /**/
	 /*For Return*/
	 if($returncount !="0")
	 {
	 	$html .='<br>';
		$html .='<strong>Return</strong><br>';
	  $html .='<table style="border-collapse: collapse;">';
	  $html .='<tr style=" border: dotted; border-width: 1px 0;">';		
      $html .=' <th style="height:20px;text-align:left;">';
      $html .=$this->translate->_('Item Code');
      $html .='  </th>';	
	  $html .=' <th style="height:20px;width:200px;text-align:left;">';
      $html .=$this->translate->_('Description');
      $html .='  </th>';
	  $html .=' <th style="height:20px;width:50px;">';
      $html .=$this->translate->_('UOM');
      $html .='  </th>';
	   $html .=' <th style="height:20px;width:70px;">';
      $html .=$this->translate->_('Quantity')." ".$this->translate->_("$this->lblcase")."/".$this->translate->_("$this->lblpcs");     
	  $html .=' <th style="height:20px;text-align:right;">';	  
      $html .=$this->translate->_("$this->lblcase Price");
      $html .='  </th>';
	  $html .=' <th style="height:20px;width:50px;text-align:right;">';
      $html .=$this->translate->_("$this->lblpcs Price");
      $html .='  </th>';
	  
	    if($column_model_arr[0]['itemtaxkey1'] > 0){
	  $html .=' <th style="height:20px;width:70px;text-align:right;">';
      $html .=$this->translate->_("Excise");
      $html .='  </th>';
	  }
	  
	   $html .=' <th style="height:20px;width:70px;text-align:right;">';
      $html .=$this->translate->_("VAT");
      $html .='  </th>';
	  
	  $html .='  <th style="height:20px;width:50px;text-align:right;">';
      $html .=$this->translate->_('Total');
      $html .='  </th>';
	  $html .='</tr>';	
	 /**/
	/**/
	
	for($j=0;$j<count($column_model_arr);$j++){
		if($column_model_arr[$j]['case_return_qty'] != "0" || $column_model_arr[$j]['pcs_return_qty'] != "0"){
		
			$html .='<tr>';	
			$html .=' <td style="text-align:center;text-align:left;">';			
			$html .=  $column_model_arr[$j][$item_code];			
			$html .='  </td>';
			$html .=' <td style="text-align:center;text-align:left;">';			
			$html .=  $column_model_arr[$j]['itemshortdescription'];			
			$html .='  </td>';
			$html .=' <td style="text-align:center">';			
			$html .=  $column_model_arr[$j]['unitspercase'];			
			$html .='  </td>';
			$html .=' <td style="text-align:center">';			
			$html .=  $column_model_arr[$j]['case_return_qty']."/".$column_model_arr[$j]['pcs_return_qty'];
			$tot_ret_cases +=$column_model_arr[$j]['case_return_qty'];
			$tot_ret_pieces +=$column_model_arr[$j]['pcs_return_qty'];			
			$html .='  </td>';
			$html .=' <td style="text-align:right">';			
			$html .=  $column_model_arr[$j]['returncaseprice'];			
			$html .='  </td>';
			$html .=' <td style="text-align:right">';			
			$html .=  $column_model_arr[$j]['returnprice'];			
			$html .='  </td>';
			if($column_model_arr[0]['itemtaxkey1'] > 0){	 
			
			$html .=' <td style="text-align:right">';			
			$html .=  $column_model_arr[$j]['return_excise'];			
			$html .='  </td>';
			}
			$html .=' <td style="text-align:right">';			
			$html .=  $column_model_arr[$j]['return_vat'];			
			$html .='  </td>';
			
			$html .=' <td style="text-align:right">';					 
            $html .=$numberString = str_replace(',','',$column_model_arr[$j]['ret_total_amt']);			
			$ret_tot_amt += $numberString;			
			$html .='  </td>';
			$html .='</tr>';	
			}
		}
	/**/
	/**/
			$html .='<tr style=" border: dotted; border-width: 1px 0;">';	
			$html .=' <td colspan="2" style="text-align:center">';			
			$html .= ' <b>Total</b>';			
			$html .='  </td>';
			$html .=' <td  style="text-align:center">';			
			$html .=  "";			
			$html .='  </td>';
			$html .=' <td  style="text-align:center">';			
			$html .=  $tot_ret_cases."/".$tot_ret_pieces;
			$html .='  </td>';	
			$html .=' <td  style="text-align:center">';			
			$html .=  "";			
			$html .='  </td>';			
			$html .=' <td  style="text-align:center">';			
			$html .=  "";			
			$html .='  </td>';
			if($column_model_arr[0]['itemtaxkey1'] > 0){	 
			
			$html .=' <td  style="text-align:center">';			
			$html .=  "";			
			$html .='  </td>';	
			}			
			$html .=' <td  style="text-align:center">';			
			$html .=  "";			
			$html .='  </td>';
			
			$html .=' <td  style="text-align:right">';			
			$html .= str_replace(',','',number_format($ret_tot_amt, $this->decimalplaces));			
			$html .='  </td>';				
			$html .='</tr>';
		/**/
	/**/
	 $html .='</table>';
	 /**/
	}
	 /*For Damage*/
	if($damagecount !="0")
	{
	 	$html .='<br>';
	$html .='<strong>Damage</strong><br>';
	  $html .='<table style="border-collapse: collapse;">';
	  $html .='<tr style=" border: dotted; border-width: 1px 0;">';		
      $html .=' <th style="height:20px;text-align:left;">';
      $html .=$this->translate->_('Item Code');
      $html .='  </th>';	
	  $html .=' <th style="height:20px;width:200px;text-align:left;">';
      $html .=$this->translate->_('Description');
      $html .='  </th>';
	  $html .=' <th style="height:20px;width:50px;">';
      $html .=$this->translate->_('UOM');
      $html .='  </th>';
	   $html .=' <th style="height:20px;width:70px;">';
      $html .=$this->translate->_('Quantity')." ".$this->translate->_("$this->lblcase")."/".$this->translate->_("$this->lblpcs"); 
      $html .='  </th>';	
	  $html .=' <th style="height:20px;text-align:right;">';	  
      $html .=$this->translate->_("$this->lblcase Price");
      $html .='  </th>';
	  $html .=' <th style="height:20px;width:50px;text-align:right;">';
      $html .=$this->translate->_("$this->lblpcs Price");
      $html .='  </th>';

	    if($column_model_arr[0]['itemtaxkey1'] > 0){
	  $html .=' <th style="height:20px;width:70px;text-align:right;">';
      $html .=$this->translate->_("Excise");
      $html .='  </th>';
	  }
	  
	   $html .=' <th style="height:20px;width:70px;text-align:right;">';
      $html .=$this->translate->_("VAT");
      $html .='  </th>';
	  
	  $html .=' <th style="height:20px;width:50px;text-align:right;">';
      $html .=$this->translate->_('Total');
      $html .='</th>';
	  $html .='</tr>';	
	 /**/
	/**/
	
	for($j=0;$j<count($column_model_arr);$j++){
		if($column_model_arr[$j]['case_damage_qty'] != "0" || $column_model_arr[$j]['pcs_damage_qty'] != "0"){			
		
			$html .='<tr>';	
			$html .=' <td style="text-align:center;text-align:left;">';			
			$html .=  $column_model_arr[$j][$item_code];			
			$html .='  </td>';
			$html .=' <td style="text-align:center;text-align:left;">';			
			$html .=  $column_model_arr[$j]['itemshortdescription'];			
			$html .='  </td>';
			$html .=' <td style="text-align:center">';			
			$html .=  $column_model_arr[$j]['unitspercase'];			
			$html .='  </td>';
			$html .=' <td style="text-align:center">';			
			$html .=  $column_model_arr[$j]['case_damage_qty']."/".$column_model_arr[$j]['pcs_damage_qty'];
			$tot_dam_cases +=$column_model_arr[$j]['case_damage_qty'];
			$tot_dam_pieces +=$column_model_arr[$j]['pcs_damage_qty'];			
			$html .='  </td>';
			$html .=' <td style="text-align:right">';			
			$html .=  $column_model_arr[$j]['returncaseprice'];			
			$html .='  </td>';
			$html .=' <td style="text-align:right">';			
			$html .=  $column_model_arr[$j]['returnprice'];			
			$html .='  </td>';
			if($column_model_arr[0]['itemtaxkey1'] > 0){	 
			
			$html .=' <td style="text-align:right">';			
			$html .=  $column_model_arr[$j]['bad_return_excise'];			
			$html .='  </td>';
			}
			$html .=' <td style="text-align:right">';			
			$html .=  $column_model_arr[$j]['bad_return_vat'];			
			$html .='  </td>';
			
			$html .=' <td style="text-align:right">';
			$html .=$numberString = str_replace(',','',$column_model_arr[$j]['dam_total_amt']);	
			
			$dam_total_amt += $numberString;				
			$html .=' </td>';		
			$html .='</tr>';	
			}
		}
	/**/
	/**/
			$html .='<tr style=" border: dotted; border-width: 1px 0;">';	
			$html .=' <td colspan="2" style="text-align:center">';			
			$html .= ' <b>Total</b>';			
			$html .='  </td>';
			$html .=' <td  style="text-align:center">';			
			$html .=  "";			
			$html .='  </td>';
			$html .=' <td  style="text-align:center">';			
			$html .=  $tot_dam_cases."/".$tot_dam_pieces;
			$html .='  </td>';	
			$html .=' <td  style="text-align:center">';			
			$html .=  "";			
			$html .='  </td>';			
			$html .=' <td  style="text-align:center">';			
			$html .=  "";			
			$html .='  </td>';	
			if($column_model_arr[0]['itemtaxkey1'] > 0){	 
			
			$html .=' <td  style="text-align:center">';			
			$html .=  "";			
			$html .='  </td>';	
			}			
			$html .=' <td  style="text-align:center">';			
			$html .=  "";			
			$html .='  </td>';
			
			$html .=' <td  style="text-align:right;">';	
			$html .= str_replace(',','',number_format($dam_total_amt, $this->decimalplaces));		
			$html .='  </td>';			
			$html .='</tr>';
		/**/
	/**/
	 $html .='</table>';
	 /**/
	 }
	  /*For Free*/
	if($freecount != "0" || $promocount != "0")
	{
	  $html .='<br>';
	  $html .='<strong>Free</strong><br>';
	  $html .='<table style="border-collapse: collapse;">';
	  $html .='<tr style=" border: dotted; border-width: 1px 0;">';		
      $html .=' <th style="height:20px;text-align:left;">';
      $html .=$this->translate->_('Item Code');
      $html .='  </th>';	
	  $html .=' <th style="height:20px;width:200px;text-align:left;">';
      $html .=$this->translate->_('Description');
      $html .='  </th>';
	  $html .=' <th style="height:20px;width:50px;">';
      $html .=$this->translate->_('UOM');
      $html .='  </th>';
	   $html .=' <th style="height:20px;width:70px;">';
      $html .=$this->translate->_('Quantity')." ".$this->translate->_("$this->lblcase")."/".$this->translate->_("$this->lblpcs"); 
      $html .='  </th>';
	   $html .=' <th style="height:20px;text-align:right;">';	  
      $html .=$this->translate->_("$this->lblcase Price");
      $html .='  </th>';
	  $html .=' <th style="height:20px;width:50px;text-align:right;">';
      $html .=$this->translate->_("$this->lblpcs Price");
      $html .='  </th>';
	  
	   if($column_model_arr[0]['itemtaxkey1'] > 0){
	  $html .=' <th style="height:20px;width:70px;text-align:right;">';
      $html .=$this->translate->_("Excise");
      $html .='  </th>';
	  }
	  
	   $html .=' <th style="height:20px;width:70px;text-align:right;">';
      $html .=$this->translate->_("VAT");
      $html .='  </th>';
	  
	   $html .=' <th style="height:20px;width:50px;text-align:right;">';
      $html .=$this->translate->_('Total');
      $html .='</th>';
	  $html .='</tr>';	
	 /**/
	/**/
	
	for($j=0;$j<count($column_model_arr);$j++){
		if(($column_model_arr[$j]['case_manualfreeqty_qty']+ $column_model_arr[$j]['casediscount']) != "0" || ($column_model_arr[$j]['pcs_manualfreeqty_qty']+ $column_model_arr[$j]['pcsdiscount']) != "0"){		
		
			$html .='<tr>';	
			$html .=' <td style="text-align:center;text-align:left;">';			
			$html .=  $column_model_arr[$j][$item_code];			
			$html .='  </td>';
			$html .=' <td style="text-align:center;text-align:left;">';			
			$html .=  $column_model_arr[$j]['itemshortdescription'];			
			$html .='  </td>';
			$html .=' <td style="text-align:center">';			
			$html .=  $column_model_arr[$j]['unitspercase'];			
			$html .='  </td>';
			$html .=' <td style="text-align:center">';			
			$html .=  $column_model_arr[$j]['case_manualfreeqty_qty']+$column_model_arr[$j]['casediscount']."/".$column_model_arr[$j]['pcs_manualfreeqty_qty'] + $column_model_arr[$j]['pcsdiscount'];
			$tot_free_cases +=$column_model_arr[$j]['case_manualfreeqty_qty'] + $column_model_arr[$j]['casediscount'];
			$tot_free_pieces +=$column_model_arr[$j]['pcs_manualfreeqty_qty'] + $column_model_arr[$j]['pcsdiscount'];
			
					
			$html .='  </td>';
			$html .=' <td  style="text-align:right">';			
			$html .= str_replace(',','',number_format("0", $this->decimalplaces));	
			$html .='  </td>';			
			$html .=' <td  style="text-align:right">';			
			$html .= str_replace(',','',number_format("0", $this->decimalplaces));		
			$html .='  </td>';
			
			if($column_model_arr[0]['itemtaxkey1'] > 0){	 
			$html .=' <td style="text-align:right">';			
			$html .=  $column_model_arr[$j]['free_excise'];			
			$html .='  </td>';
			}
			  
			$html .=' <td style="text-align:right">';			
			$html .=  $column_model_arr[$j]['free_vat'];			
			$html .='  </td>';
			
			$html .=' <td  style="text-align:right">';			
			$html .= str_replace(',','',number_format("0", $this->decimalplaces));		
			$html .='  </td>';			
			$html .='</tr>';
			}			
		}
	/**/
	/**/
			$html .='<tr style=" border: dotted; border-width: 1px 0;">';	
			$html .=' <td colspan="2" style="text-align:center">';			
			$html .= ' <b>Total</b>';			
			$html .='  </td>';
			$html .=' <td  style="text-align:center">';			
			$html .=  "";			
			$html .='  </td>';
			$html .=' <td  style="text-align:center">';			
			$html .=  $tot_free_cases."/".$tot_free_pieces;
			$html .='  </td>';	
			$html .=' <td  style="text-align:center">';			
			$html .=  "";			
			$html .='  </td>';			
			$html .=' <td  style="text-align:center">';			
			$html .=  "";			
			$html .='  </td>';
			if($column_model_arr[0]['itemtaxkey1'] > 0){	 
			
			$html .=' <td  style="text-align:center">';			
			$html .=  "";			
			$html .='  </td>';	
			}			
			$html .=' <td  style="text-align:center">';			
			$html .=  "";			
			$html .='  </td>';
			
			$html .=' <td  style="text-align:right">';			
			$html .= str_replace(',','',number_format("0", $this->decimalplaces));		
			$html .='  </td>';				
			$html .='</tr>';
		/**/
	/**/
	 $html .='</table>';
	 
	} 
	
	$html .='<table>';
	if($invoice_header['totalsalesamount'] > 0){
	$html .='<tr>'; 
	$html .='<td style="width:220px;">';
	$html .= "<b> </b>";
	$html .='</td>';
	$html .='<td style="width:120px;">';
	$html .='</td>';
	$html .='<td >';
	$html .= "<b>Total Sales Amount : </b>";
	$html .='</td>';
	$html .='<td >';
	$html .= number_format($invoice_header['totalsalesamount'],$this->decimalplaces);
	$html .='</td>';
	$html .='</tr>'; 
	$html .='<tr>'; 
	}
	
	if($invoice_header['totalreturnamount'] > 0){
	$html .='<tr>'; 
	$html .='<td >';
	$html .= "<b> </b>";
	$html .='</td>';
	$html .='<td style="width:120px;">';
	$html .='</td>';
	$html .='<td >';
	$html .= "<b>Total Returned Amount : </b>";
	$html .='</td>';
	$html .='<td >';
	$html .= number_format($invoice_header['totalreturnamount'],$this->decimalplaces);
	$html .='</td>';
	$html .='</tr>'; 
	$html .='<tr>'; 
	}
	
	
	if($invoice_header['totaldamagedamount'] > 0){
	$html .='<tr>'; 
	$html .='<td >';
	$html .= "<b> </b>";
	$html .='</td>';
	$html .='<td style="width:120px;">';
	$html .='</td>';
	$html .='<td >';
	$html .= "<b>Total Damaged Amount : </b>";
	$html .='</td>';
	$html .='<td >';
	$html .= number_format($invoice_header['totaldamagedamount'],$this->decimalplaces);
	$html .='</td>';
	$html .='</tr>'; 
	$html .='<tr>'; 
	}
	
	if($invoice_header['totalpromoamount'] > 0){
	$html .='<tr>'; 
	$html .='<td >';
	$html .= "<b> </b>";
	$html .='</td>';
	$html .='<td style="width:120px;">';
	$html .='</td>';
	$html .='<td >';
	$html .= "<b>Total Invoice Discount : </b>";
	$html .='</td>';
	$html .='<td >';
	$html .= number_format($invoice_header['totalpromoamount'],$this->decimalplaces);
	$html .='</td>';
	$html .='</tr>'; 
	$html .='<tr>'; 
	}
	
	if($invoice_header['itemlinetaxamount'] != 0){
	$html .='<tr>'; 
	$html .='<td >';
	$html .= "<b> </b>";
	$html .='</td>';
	$html .='<td style="width:120px;">';
	$html .='</td>';
	$html .='<td >';
	$html .= "<b>Total Tax : </b>";
	$html .='</td>';
	$html .='<td >';
	$html .= number_format($invoice_header['itemlinetaxamount'],$this->decimalplaces);
	$html .='</td>';
	$html .='</tr>'; 
	$html .='<tr>'; 
	}
	
	$html .='<tr>'; 
	$html .='<td >';
	$html .= "<b> </b>";
	$html .='</td>';
	$html .='<td style="width:120px;">';
	$html .='</td>';
	$html .='<td >';
	$html .= "<b>Total Invoice Amount : </b>";
	$html .='</td>';
	$html .='<td >';
	$html .= number_format($invoice_header['totalinvoiceamount'],$this->decimalplaces);
	$html .='</td>';
	$html .='</tr>'; 
	$html .='<tr>'; 	
	
	
	
	if($invoice_header['amountpaid'] > 0){
	$html .='<tr>'; 
	$html .='<td >';
	$html .= "<b> </b>";
	$html .='</td>';
	$html .='<td style="width:120px;">';
	$html .='</td>';
	$html .='<td >';
	$html .= "<b>Amount Paid : </b>";
	$html .='</td>';
	$html .='<td >';
	$html .= number_format($invoice_header['amountpaid'],$this->decimalplaces);
	$html .='</td>';
	$html .='</tr>'; 
	$html .='<tr>'; 
	}
	
	
	if($invoice_header['invoicebalance'] > 0){
	$html .='<tr>'; 
	$html .='<td >';
	$html .= "<b> </b>";
	$html .='</td>';
	$html .='<td style="width:120px;">';
	$html .='</td>';
	$html .='<td >';
	$html .= "<b>Invoice Balance : </b>";
	$html .='</td>';
	$html .='<td >';
	$html .= number_format($invoice_header['invoicebalance'],$this->decimalplaces);
	$html .='</td>';
	$html .='</tr>'; 
	$html .='<tr>'; 
	}
	
	if($invoice_header['comments'] != null ){
	$html .='<tr>'; 
	$html .='<td>';
	$html .= "<b>Comments : </b>";
	$html .='</td>';
	$html .='<td colspan="3">';
	$html .= $invoice_header['comments'];
	$html .='</td>';	
	$html .='</tr>';
	}
	if($invoice_header['signaturedata']!=null)
	{
	$html .='<tr>'; 
	$html .='<td Style="height:50px;vertical-align:top;">';
	$html .= "<b>Customer Signature : </b>";
	$html .='</td>';
	$html .='<td colspan="3">';
	if($invoice_header['signaturedata']!=null)
	{
	$html .= '<img width="100" alt=" " height="100" src="'.$invoice_header['signaturedata'].'">';
	}
	$html .='</td>';	
	$html .='</tr>';	
	}
	
	$html .='</table>';

	  $html .="</body>";
	require_once('mpdf/mpdf.php');
    $mpdf=new mPDF('c','A4',9,'',32,25,27,25,16,13);	
	
    $mpdf->setFooter('{PAGENO}');
    $mpdf->WriteHTML($html);
    $mpdf->Output('invoice.pdf','I');
    exit;   
	}
}