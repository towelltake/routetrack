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
      $html .=$this->translate->_('UPC');
      $html .='  </th>';
	   $html .=' <th style="height:20px;width:70px;">';
      $html .=$this->translate->_('Quantity')." ".$this->translate->_('Case')."/".$this->translate->_('Pcs');     
	  $html .=' <th style="height:20px;text-align:right;">';	  
      $html .=$this->translate->_('Case Price');
      $html .='  </th>';
	  $html .=' <th style="height:20px;width:70px;text-align:right;">';
      $html .=$this->translate->_('Pcs Price');
      $html .='  </th>';
	  $html .='  <th style="height:20px;width:50px;text-align:right;">';
      $html .=$this->translate->_('Total');
      $html .='  </th>';
	  $html .='</tr>';	
	 /**/
	/**/
	
	for($j=0;$j<count($column_model_arr);$j++){
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
			
			$html .=' <td style="text-align:right">';			
			//$html .=  $column_model_arr[$j]['total_amt'];			 
            $html .=$numberString = str_replace(',','',$column_model_arr[$j]['total_amt']);			
			$tot_amt += $numberString;			
			$html .='  </td>';
			$html .='</tr>';			
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
      $html .=$this->translate->_('UPC');
      $html .='  </th>';
	   $html .=' <th style="height:20px;width:70px;">';
      $html .=$this->translate->_('Quantity')." ".$this->translate->_('Case')."/".$this->translate->_('Pcs');     
	  $html .=' <th style="height:20px;text-align:right;">';	  
      $html .=$this->translate->_('Case Price');
      $html .='  </th>';
	  $html .=' <th style="height:20px;width:70px;text-align:right;">';
      $html .=$this->translate->_('Pcs Price');
      $html .='  </th>';
	  $html .='  <th style="height:20px;width:50px;text-align:right;">';
      $html .=$this->translate->_('Total');
      $html .='  </th>';
	  $html .='</tr>';	
	 /**/
	/**/
	
	for($j=0;$j<count($column_model_arr);$j++){
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
			
			$html .=' <td style="text-align:right">';					 
            $html .=$numberString = str_replace(',','',$column_model_arr[$j]['ret_total_amt']);			
			$ret_tot_amt += $numberString;			
			$html .='  </td>';
			$html .='</tr>';			
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
      $html .=$this->translate->_('UPC');
      $html .='  </th>';
	   $html .=' <th style="height:20px;width:70px;">';
      $html .=$this->translate->_('Quantity')." ".$this->translate->_('Case')."/".$this->translate->_('Pcs'); 
      $html .='  </th>';	
	  $html .=' <th style="height:20px;text-align:right;">';	  
      $html .=$this->translate->_('Case Price');
      $html .='  </th>';
	  $html .=' <th style="height:20px;width:70px;text-align:right;">';
      $html .=$this->translate->_('Pcs Price');
      $html .='  </th>';	  
	  $html .=' <th style="height:20px;width:50px;text-align:right;">';
      $html .=$this->translate->_('Total');
      $html .='</th>';
	  $html .='</tr>';	
	 /**/
	/**/
	
	for($j=0;$j<count($column_model_arr);$j++){
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
			$html .=' <td style="text-align:right">';
			$html .=$numberString = str_replace(',','',$column_model_arr[$j]['dam_total_amt']);	
			
			$dam_total_amt += $numberString;				
			$html .=' </td>';		
			$html .='</tr>';			
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
      $html .=$this->translate->_('UPC');
      $html .='  </th>';
	   $html .=' <th style="height:20px;width:70px;">';
      $html .=$this->translate->_('Quantity')." ".$this->translate->_('Case')."/".$this->translate->_('Pcs'); 
      $html .='  </th>';
	   $html .=' <th style="height:20px;text-align:right;">';	  
      $html .=$this->translate->_('Case Price');
      $html .='  </th>';
	  $html .=' <th style="height:20px;width:70px;text-align:right;">';
      $html .=$this->translate->_('Pcs Price');
      $html .='  </th>';
	   $html .=' <th style="height:20px;width:50px;text-align:right;">';
      $html .=$this->translate->_('Total');
      $html .='</th>';
	  $html .='</tr>';	
	 /**/
	/**/
	
	for($j=0;$j<count($column_model_arr);$j++){
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
			$html .=' <td  style="text-align:right">';			
			$html .= str_replace(',','',number_format("0", $this->decimalplaces));		
			$html .='  </td>';			
			$html .='</tr>';			
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
			$html .=' <td  style="text-align:right">';			
			$html .= str_replace(',','',number_format("0", $this->decimalplaces));		
			$html .='  </td>';				
			$html .='</tr>';
		/**/
	/**/
	 $html .='</table>';
	 
	} 
	   /*For Promotion*/
	/*
	  $html .='<br>';
	  $html .='<strong>Promotion</strong><br>';
	  $html .='<table style="border-collapse: collapse;">';
	  $html .='<tr style=" border: dotted; border-width: 1px 0;">';		
      $html .=' <th style="height:20px;text-align:left;">';
      $html .=$this->translate->_('Item Code');
      $html .='  </th>';	
	  $html .=' <th style="height:20px;width:200px;text-align:left;">';
      $html .=$this->translate->_('Description');
      $html .='  </th>';
	  $html .=' <th style="height:20px;width:50px;">';
      $html .=$this->translate->_('UPC');
      $html .='  </th>';
	   $html .=' <th style="height:20px;width:70px;">';
      $html .=$this->translate->_('Quantity')." ".$this->translate->_('Case')."/".$this->translate->_('Pcs'); 
      $html .='  </th>';
	  $html .=' <th style="height:20px;width:70px;text-align:right;">';
      $html .=$this->translate->_('Discount'); 
      $html .='  </th>';
	   $html .=' <th style="height:20px;width:70px;text-align:right;">';
      $html .=$this->translate->_('Total'); 
      $html .='  </th>';	 
	  $html .='</tr>';	
	
	
	for($j=0;$j<count($column_model_arr);$j++){
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
			$html .=  $column_model_arr[$j]['casediscount']."/".$column_model_arr[$j]['pcsdiscount'];
			$tot_disc_cases +=$column_model_arr[$j]['casediscount'];
			$tot_disc_pieces +=$column_model_arr[$j]['pcsdiscount'];			
			$html .='  </td>';	
			$html .=' <td style="text-align:right">';			
			$html .=$numberString = str_replace(',','',$column_model_arr[$j]['discount']);
			$discount += $numberString;
			$html .='  </td>';
			$html .=' <td style="text-align:right">';			
			$html .=$numberString = str_replace(',','',$column_model_arr[$j]['discountamount']);
			$disc_total += $numberString;
			$html .='  </td>';
 			
			$html .='</tr>';			
		}
	
			$html .='<tr style=" border: dotted; border-width: 1px 0;">';	
			$html .=' <td colspan="2" style="text-align:center">';			
			$html .= ' <b>Total</b>';			
			$html .='  </td>';
			$html .=' <td  style="text-align:center">';			
			$html .=  "";			
			$html .='  </td>';
			$html .=' <td  style="text-align:center">';			
			$html .=  $tot_disc_cases."/".$tot_disc_pieces;
			$html .='  </td>';	
			$html .=' <td  style="text-align:right;">';	
			$html .= '';//str_replace(',','',number_format($discount, $this->decimalplaces));		
			$html .='  </td>';
			$html .=' <td  style="text-align:right;">';	
			$html .= str_replace(',','',number_format($disc_total, $this->decimalplaces));		
			$html .='  </td>';					
			$html .='</tr>';
	
	 $html .='</table>';
	 */
	 /*Following code for total total amount*/
	
	$html .='<table>';
	
	$html .='<tr>'; 
	$html .='<td >';
	$html .= "<b>Total Sales Amount : </b>";
	$html .='</td>';
	$html .='<td style="width:120px;">';
	$html .= number_format($invoice_header['totalsalesamount'],$this->decimalplaces);
	$html .='</td>';
	$html .='<td >';
	$html .= "<b>Total Invoice Amount : </b>";
	$html .='</td>';
	$html .='<td >';
	$html .= number_format($invoice_header['totalinvoiceamount'],$this->decimalplaces);
	$html .='</td>';
	$html .='</tr>'; 
	$html .='<tr>'; 
	
	$html .='<td>';
	$html .= "<b>Total Returned Amount : </b>";
	$html .='</td>';
	$html .='<td>';
	$html .= number_format($invoice_header['totalreturnamount'],$this->decimalplaces);
	$html .='</td>';
	$html .='<td>';
	$html .= "<b>Total Damaged Amount : </b>";
	$html .='</td>';
	$html .='<td>';
	$html .= number_format($invoice_header['totaldamagedamount'],$this->decimalplaces);
	$html .='</td>';
	$html .='</tr>'; 
	$html .='<tr>'; 
	$html .='<td>';
	$html .= "<b>Total Line Item Discount : </b>";
	$html .='</td>';
	$html .='<td>';
	$html .= number_format($result[3][0]['promoamount'],$this->decimalplaces);
	$html .='</td>'; 
	$total_invo_dis = ($invoice_header['totalpromoamount']-$result[3][0]['promoamount']);
	$html .='<td>';	
	$html .= "<b>Total Invoice Discount : </b>";
	$html .='</td>';
	$html .='<td>';	
	$html .= number_format($total_invo_dis,$this->decimalplaces);
	$html .='</td>';
	$html .='</tr>';
	$html .='<tr>'; 
	$html .='<td>';
	$html .= "<b>Amount Paid : </b>";
	$html .='</td>';
	$html .='<td>';
	$html .= number_format($invoice_header['amountpaid'],$this->decimalplaces);
	$html .='</td>';
	$html .='<td>';
	$html .= "<b>Invoice Balance : </b>";
	$html .='</td>';
	$html .='<td>';
	$html .= number_format($invoice_header['invoicebalance'],$this->decimalplaces);
	$html .='</td>';
	$html .='</tr>'; 
		$html .='<tr>'; 
	$html .='<td>';
	$html .= "<b>Comments : </b>";
	$html .='</td>';
	$html .='<td colspan="3">';
	$html .= $invoice_header['comments'];
	$html .='</td>';	
	$html .='</tr>';
	$html .='</tr>'; 
		$html .='<tr>'; 
	$html .='<td Style="height:50px;vertical-align:top;">';
	$html .= "<b>Customer Signature : </b>";
	$html .='</td>';
	$html .='<td colspan="3">';
	//$invoice_header['signaturedata']	=	"data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/2wBDAQMDAwQDBAgEBAgQCwkLEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBD/wAARCAEsAZADASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD9U6KrS6hZwSGKWbay9RtJ/pTP7W0//n4/8cb/AAoAuUVT/tbT/wDn4/8AHG/wo/tbT/8An4/8cb/CgC5RVP8AtbT/APn4/wDHG/wo/tbT/wDn4/8AHG/woAuUVT/tbT/+fj/xxv8ACj+1tP8A+fj/AMcb/CgC5RVP+1tP/wCfj/xxv8KP7W0//n4/8cb/AAoAuUVT/tbT/wDn4/8AHG/wo/tbT/8An4/8cb/CgC5RVP8AtbT/APn4/wDHG/wo/tbT/wDn4/8AHG/woAuUVT/tbT/+fj/xxv8ACj+1tP8A+fj/AMcb/CgC5RVP+1tP/wCfj/xxv8KP7W0//n4/8cb/AAoAuUVT/tbT/wDn4/8AHG/wo/tbT/8An4/8cb/CgC5RVP8AtbT/APn4/wDHG/wo/tbT/wDn4/8AHG/woAuUVT/tbT/+fj/xxv8ACj+1tP8A+fj/AMcb/CgC5RVP+1tP/wCfj/xxv8KP7W0//n4/8cb/AAoAuUVT/tbT/wDn4/8AHG/wo/tbT/8An4/8cb/CgC5RVP8AtbT/APn4/wDHG/wo/tbT/wDn4/8AHG/woAuUVT/tbT/+fj/xxv8ACj+1tP8A+fj/AMcb/CgC5RVP+1tP/wCfj/xxv8KP7W0//n4/8cb/AAoAuUVT/tbT/wDn4/8AHG/wo/tbT/8An4/8cb/CgC5RVP8AtbT/APn4/wDHG/wo/tbT/wDn4/8AHG/woAuUVT/tbT/+fj/xxv8ACj+1tP8A+fj/AMcb/CgC5RVP+1tP/wCfj/xxv8KP7W0//n4/8cb/AAoAuUVT/tbT/wDn4/8AHG/wo/tbT/8An4/8cb/CgC5RVP8AtbT/APn4/wDHG/wo/tbT/wDn4/8AHG/woAuUVT/tbT/+fj/xxv8ACj+1tP8A+fj/AMcb/CgC5RVP+1tP/wCfj/xxv8KfDqNnPIIoptzt0G0j+lAFmiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAorm/D3jAeJvEWu6bpmnb9K0ORLNtU84FLi/Bb7RbxpjkQjywz7seYzx43RPjpKqUHB2kTGSmrxCiiipKCiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKK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";
	if($invoice_header['signaturedata']!=null)
	{
	$html .= '<img width="100" alt=" " height="100" src="'.$invoice_header['signaturedata'].'">';
	}
	$html .='</td>';	
	$html .='</tr>';	
	
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