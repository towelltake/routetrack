<?php
/**
* @name       IndexController
* @since
* @version    Release: 1
* @author     HD <hiren.d@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage user signup module.
*/
class Account_CustomerController extends Account_Library_Controller_Action_Abstract
{
    /**
    * @name       init
    * @since      15-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is the default function for all Actions.
    *
    */
    public $SFA_Model_Index 	= '';
    public $sec_lang 		= '';

    public function init()
    {
        $this->translate 	= Zend_Registry::get('Zend_Translate');
        
        $this->currentUser = SFA_Loginauth::getIdentity();	
        if(!isset($this->currentUser) || empty($this->currentUser))
        {
            SFA_Message::setMsg($this->translate->_('Do Login'));
            //$this->_helper->redirector("index", "index", "home");
			$url = $this->view->baseUrl();
			echo '<script type="text/javascript">window.location="'.$url.'";</script>';
			exit;
        }
        
        $this->css 				= $this->translate->_('CSS');
		$this->view->css 		= $this->css;
		$this->view->overview	= $this->translate->_('Overview');
        $this->view->details	= $this->translate->_('Details');
        $this->view->required	= $this->translate->_('Required');
        $this->view->colan	    = $this->translate->_('Colan');
        
        $this->SFA_Comman		    = new SFA_Comman();
        $this->view->decimalplaces 	= $this->SFA_Comman->getdecimalplaces();
        $this->view->sec_lang		= $this->SFA_Comman->getsecondlanguage();
        $this->sec_lang 		    = $this->view->sec_lang;
        $this->decimalplaces 		= $this->view->decimalplaces;
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
                
            }
        }
        /**
         *      Acl Code end
         */
    }
    
    
    /**
    * @name       custcatAction
    * @since      15-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is for display customer category
    */
    public function custcatAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

        if($formdata["hdDelete"]==1)
        {
            $ids = implode(',',$formdata['chk']);
            $param_array 	= array();
            $param_array[1]	= $ids;
            $param_array[2]	= $this->currentUser->username;
            
            $result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_customer_custcat(?)',$param_array,'');
            
            if($result[0][0]['deleted_id'] =='')
            {
            $ids		= explode(',',$ids);
            $checked 	= $ids;
            
            SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
            }
            else
            {
            $deleted_id 	= explode(',',$result[0][0]['deleted_id']);
            $ids		= explode(',',$ids);
            $checked 	= array_diff($ids,$deleted_id);
            
            if(count($ids) != count($deleted_id)){
                SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
            }
            
            SFA_Message::setMsg($this->translate->_('Delete Record'));
            }
        }
    
        $this->view->title	= $this->translate->_('Customer Category');	
        
        $cols_array 	= array('categoryid','categoryname','activestatus');
        $columns_show 	=  array($this->translate->_('Code'),$this->translate->_('Customer Category').' '.$this->translate->_('Name'),$this->translate->_('Status'));
        
        if($this->css == 'ar_') {
			$cols_array[1]	= 'arbcategoryname';
		}
            
        // prepare the configuration for grid
        $pagingparams = array(
                "show_grid_heading" => true,
                "grid_heading_message" => $this->translate->_('Overview'),
                "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
                "show_searchbox" => true,
                "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
                "pagename" => $this->translate->_('Customer Category'),
                "show_selectbox" => true,
                "show_editlink" => true,
                "selected_list" => $checked,
                "show_deletelink" => false,			
                "show_deleteall" => false,
                "primaryid" => "categoryid",
                "status_cols" => array(
                               array(
                               "cols_name" => "activestatus",
                               "status_change" => array("0"=>"Inactive","1"=>"Active")
                               )
                               ),
                "editlink" => array("/account/customer/addcustcat/id/#pattern#/edit/yes/","#pattern#"),
                "nodata_message" => $this->translate->_('No Record(s) Found'),
                "fetch_columns_inquery" => $cols_array,
                "show_columns" => $columns_show
                );
        
        if(!$this->checkaccess("update"))
        {
            $pagingparams["show_editlink"] = false;
        }
        
        // create grid class object
        $pagingshow = new SFA_Paging($pagingparams);
        
        // call common function of grid class
        $get_return_vals = $pagingshow->commnfunc();
        
        //print_r($get_return_vals['where_condition']);
        
        // call the stored procedure for fetch the data
        $param_array    = array();
        $param_array[1] = '1';
        $param_array[2] = '';
        $param_array[3] = $get_return_vals['order_columns_name'];
        $param_array[4] = $get_return_vals['order_type'];
        $param_array[5] = $get_return_vals['offset'];
        $param_array[6] = (int)$get_return_vals['show_records_per_page'];
        $param_array[7] = implode(", ",$pagingparams["fetch_columns_inquery"]);
        $param_array[8] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
        
        $downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];
        
        // Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
        $printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
        // called stored procedure for counter
        $result 	= $this->SFA_Comman->executequery('CALL sp_get_account_customer_custcat(?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
    
        $data_arr["count"] 		= $result[0][0]['counter'];	
        $data_arr["data"][0] 	= $result[1];
        
        // pass the data in summary_showdatagrid() function & create a final variable for view
        $this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
        
        $this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addcustcatAction
    * @since      15-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is for add customer category
    */
    public function addcustcatAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

		$this->view->css 		= $this->translate->_('CSS');
		$this->view->select 	= $this->translate->_('Select');
		$this->view->missonefld	= $this->translate->_('Missed One Field');
		$this->view->youmissed	= $this->translate->_('You Missed');
		$this->view->highlated	= $this->translate->_('Fields. They have been highlighted.');
		
		if($formdata['txtcode'] && $formdata['txtname'])
		{
			if($formdata['hdnid'] > 0)
			{	    
				$param_array = array();
				$param_array[1] = trim($formdata['txtname']); 		//categoryname
				$param_array[2] = trim($formdata['txtname_arb']);	//arbcategoryname
				$param_array[3] = trim($formdata['ddlstatus']);		//activestatus
				$param_array[4] = $this->currentUser->username;		//Modified
				$param_array[5] = $formdata['hdnid'];				//categoryid
				$param_array[6] = $formdata['txtaltcode'];			//alternatecode
				
				$last_id = $this->SFA_Comman->executequery('CALL sp_edit_account_customer_addcustcat(?)',$param_array,'');
				
				SFA_Message::setMsg($this->translate->_('Update Record'));
			}
			else
			{
				$param_array = array();
				$param_array[1] = trim($formdata['txtname']); 		//categoryname
				$param_array[2] = trim($formdata['txtname_arb']);	//arbcategoryname
				$param_array[3] = trim($formdata['ddlstatus']);		//activestatus
				$param_array[4] = $this->currentUser->username;		//created		
				$param_array[5] = $formdata['txtaltcode'];			//alternatecode
				
				$last_id = $this->SFA_Comman->executequery('CALL sp_add_account_customer_addcustcat(?)',$param_array,'');
				
				SFA_Message::setMsg($this->translate->_('New Record'));
			}
		   $this->_helper->redirector('custcat', 'customer', 'account');
		}
		elseif($params['id'] > 0)
		{
			$result  		= $this->SFA_Comman->executequery('CALL sp_get_account_customer_addcustcat(?)',$params['id'],'');
			$res['txtcode'] 	= $result[0][0]['categoryid'];
			$res['txtname'] 	= $result[0][0]['categoryname'];
			$res['txtname_arb'] = $result[0][0]['arbcategoryname'];
			$res['ddlstatus'] 	= $result[0][0]['activestatus'];
			$res['createddate']	= date('d-m-Y',strtotime($result[0][0]['cdat']));
			$res['txtaltcode'] 	= $result[0][0]['alternatecode'];
			$this->view->formdata = $res;
		}	
		else
		{
			$table_name = 'categorymaster';
			$code = $this->SFA_Comman->executequery('CALL sp_get_table_last_id(?)',$table_name,'');	    
			$this->view->formdata['txtcode'] = ($code[0][0]['Auto_increment'] == '') ? '1' : $code[0][0]['Auto_increment'];
			
		}
    }
     /**
    * @name       customerAction
    * @since      16-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for display custoemr details
    */
    public function customerAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
	
		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_customer_customer(?)',$param_array,'');
			
			if($result[0][0]['deleted_id'] =='')
			{
			$ids		= explode(',',$ids);
			$checked 	= $ids;
			
			SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			}
			else
			{
			$deleted_id 	= explode(',',$result[0][0]['deleted_id']);
			$ids		= explode(',',$ids);
			$checked 	= array_diff($ids,$deleted_id);
			
			if(count($ids) != count($deleted_id)){
				SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			}
			
			SFA_Message::setMsg($this->translate->_('Delete Record'));
			}
		}
	
		$Settings_NameSpace = new Zend_Session_Namespace('Settings');		
		$this->view->show_contact_tab 	= $Settings_NameSpace->cpanel['Show Additional Customer Details']['status'];
		$this->view->title				= $this->translate->_('Customer');
		$this->view->general			= $this->translate->_('General');
		$this->view->setting1			= $this->translate->_('Settings 1');
		$this->view->setting2			= $this->translate->_('Settings 2');
		$this->view->contact			= $this->translate->_('Contact');
		
		
		$code				= $this->translate->_('Code');
		$alt_code			= $this->translate->_('Alternate Code');
		$cust_name 			= $this->translate->_('Customer Name');
		$headoffice_code	= $this->translate->_('Balance');
		$route_code			= $this->translate->_('Route Code');
		$route_name			= $this->translate->_('Route Name');
		$payment_mode		= $this->translate->_('Payment Mode');
		$status				= $this->translate->_('Status');
		
		
		$cols_array 	= array('customercode','alternatecode','customername','FORMAT(balance,'.$this->decimalplaces.') AS balance','route.routecode','routename','payment_mode(invoicepaymentterms) AS invoicepaymentterms','activecustomer');
		$columns_show 	=  array($code,$alt_code,$cust_name,$headoffice_code,$route_code,$route_name,$payment_mode,$status);
		
		if($this->css == 'ar_') {
			$cols_array[2]	= 'arbcustomername';
            $cols_array[5]	= 'arbroutename';
		}
		
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
                "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
                "pagename" => $this->translate->_('Customer'),
				"show_selectbox" => true,
				"show_editlink" => true,
				"selected_list" => $checked,
				"show_deletelink" => false,			
				"show_deleteall" => false,
				"primaryid" => "customercode",
				"status_cols" => array(
							   array(
							   "cols_name" => "activecustomer",
							   "status_change" => array("0"=>"Inactive","1"=>"Active")
							   )
							   ),
				"editlink" => array("/account/customer/addcustomer/id/#pattern#/edit/yes/","#pattern#"),
				"nodata_message" => $this->translate->_('No Record(s) Found'),
				"fetch_columns_inquery" => $cols_array,
				"show_columns" => $columns_show,
				"show_columns_right_side"=>array('balance'),
				"show_header_right_side"=>array($this->translate->_('Balance')),
				);
		
        if(!$this->checkaccess("update"))
        {
            $pagingparams["show_editlink"] = false;
        }
        
		// create grid class object
		$pagingshow = new SFA_Paging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		//print_r($get_return_vals['where_condition']);
		
		
		if(strpos($get_return_vals['where_condition'],'payment_mode(invoicepaymentterms)')){			
			$get_return_vals['where_condition'] = str_replace('payment_mode(invoicepaymentterms)','invoicepaymentterms',$get_return_vals['where_condition']);			
		}
		
		// call the stored procedure for fetch the data
		$param_array 	= array();
		$param_array[1] = '1';
		$param_array[2] = '';
		$param_array[3] = $get_return_vals['order_columns_name'];
		$param_array[4] = $get_return_vals['order_type'];
		$param_array[5] = $get_return_vals['offset'];
		$param_array[6] = (int)$get_return_vals['show_records_per_page'];
		$param_array[7] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[8] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
		
		$downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];    
        
        // Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
        $printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
    
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_account_customer_customer(?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addcustomerAction
    * @since      16-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add custoemr details
    */
    public function addcustomerAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		$Settings_NameSpace = new Zend_Session_Namespace('Settings');		
		$this->view->show_contact_tab 	= $Settings_NameSpace->cpanel['Show Additional Customer Details']['status'];	
		
		$this->view->select 	= $this->translate->_('Select');
		$this->view->missonefld	= $this->translate->_('Missed One Field');
		$this->view->youmissed	= $this->translate->_('You Missed');
		$this->view->highlated	= $this->translate->_('Fields. They have been highlighted.');
		
		$this->setting1values();
		$this->setting2values();
	
		$invo_pay_term = array();
		$invo_pay_term[0]['id']  = '0';
		$invo_pay_term[0]['val'] = 'CASH Only';
		$invo_pay_term[1]['id']  = '1';
		$invo_pay_term[1]['val'] = 'CASH or CHEQUE';
		$invo_pay_term[2]['id']  = '2';
		$invo_pay_term[2]['val'] = 'CHARGE Only (GC)';
		$invo_pay_term[3]['id']  = '3';
		$invo_pay_term[3]['val'] = 'TC (CASH or CHEQUE)';
		$invo_pay_term[4]['id']  = '4';
		$invo_pay_term[4]['val'] = 'TC (CASH Only)';
		$this->view->invo_pay_term	= $invo_pay_term;
	
	
		if($formdata['txtcode'] && $formdata['txtname'])
		{
			$headoffice_code = ($formdata['rdofftype'] > '1') ? $formdata['ddlhead_off'] : 0;
			
			$param_array    = array();
			$param_array[1] = $formdata['txtcode'];				//customercode
			$param_array[2] = $formdata['ddlstatus'];				//activecustomer
			$param_array[3] = $formdata['txtname'];				//customername
			$param_array[4] = $formdata['txtaddress'];				//customeraddress1	
			$param_array[5] = $formdata['ddlroutename'];			//routecode	
			$param_array[6] = $formdata['txtaddress2'];				//customeraddress2	
			$param_array[7] = $formdata['txtalt_code'];				//alternatecode	
			$param_array[8] = $formdata['txtaddress3'];				//customeraddress3
			$param_array[9] = $formdata['txttel_num'];				//customerphone
			$param_array[10] = $headoffice_code;				//headofficecode
			$param_array[11] = $formdata['txtpobox'];				//pobox
			$param_array[12] = $formdata['ddlinvo_pay'];			//invoicepaymentterms
			$param_array[13] = str_replace(',','',$formdata['txtcash_bal']);			//balance
			$param_array[14] = ($formdata['ddlprice_key']<>""?$formdata['ddlprice_key']:0);			//pricingkey
			$param_array[15] = str_replace(',','',$formdata['txtcr_limit']);			//creditlimit
			$param_array[16] = ($formdata['ddlpromo_key'] > 0) ? $formdata['ddlpromo_key'] : 'NULL';			//promotionkey
			$param_array[17] = ($formdata['txtcr_days'] > 0) ? $formdata['txtcr_days'] : 'NULL';			//$formdata['txtcr_days'];				//creditlimitdays
			$param_array[18] = ($formdata['ddlauthorise_grp'] > 0) ? $formdata['ddlauthorise_grp'] : 'NULL';			//$formdata['ddlauthorise_grp'];			//authorizeditemgrpkey
			$param_array[19] = ($formdata['ddldis_key'] > 0) ? $formdata['ddldis_key'] : 'NULL';			//$formdata['ddldis_key'];				//discountkey
			$param_array[20] = ($formdata['ddldistri_key'] > 0) ? $formdata['ddldistri_key'] : 'NULL';			//$formdata['ddldistri_key'];			//distributionkey
			$param_array[21] = ($formdata['ddloutlet_pro'] > 0) ? $formdata['ddloutlet_pro'] : 'NULL';			//$formdata['ddloutlet_pro'];			//outletsubtype
			$param_array[22] = ($formdata['ddlsurvey_key'] > 0) ? $formdata['ddlsurvey_key'] : 'NULL';			//$formdata['ddlsurvey_key'];			//surveykey
			$param_array[23] = ($formdata['ddlcust_cat'] > 0) ? $formdata['ddlcust_cat'] : 'NULL';			//customercategory
			$param_array[24] = ($formdata['ddl_ar_cust'] > 0) ? $formdata['ddl_ar_cust'] : 'NULL';			//$formdata['ddl_ar_cust'];			//arcustomertype
			$param_array[25] = ($formdata['txtspot_limit'] > 0) ? $formdata['txtspot_limit'] : 'NULL';			//$formdata['txtspot_limit'];			//expirylimit
			$param_array[26] = ($formdata['txtexp_run_val'] > 0) ? $formdata['txtexp_run_val'] : 'NULL';			//$formdata['txtexp_run_val'];			//exprunningvalue
			$param_array[27] = ($formdata['txtcont_name'] > 0) ? $formdata['txtcont_name'] : 'NULL';			//$formdata['txtcont_name'];			//ArbContactName
			$param_array[28] = ($formdata['txtalpha_code'] > 0) ? $formdata['txtalpha_code'] : 'NULL';			//$formdata['txtalpha_code'];			//ancustomercode
			$param_array[29] = $formdata['rdofftype'];				//type		
			$param_array[30] = $this->currentUser->username;			//created
			$param_array[31] = $formdata['txtlangname'];			//ARBCustomerName
			$param_array[32] = $formdata['txtcustomer_state'];			//CustomerState	
			$param_array[33] = $formdata['txtcustoemr_city'];			//ArabicCustomerCity
			$param_array[34] = $formdata['txtlangaddress'];			//ArbCustomerAddress1		
			$param_array[35] = $formdata['txtlangaddress2'];			//ArbCustomerAddress2
			$param_array[36] = $formdata['txtremark'];				//ArbCustomerAddress3
			$param_array[37] = $formdata['txt_bill_to_bill']?$formdata['txt_bill_to_bill']:0;			//numoutstandinginv
            $param_array[38] = ($formdata['ddldis_msg_key1'] > 0) ? $formdata['ddldis_msg_key1'] : 'NULL';            		//messagekey1
			/*$param_array[49] was there added 39 by nilesh on 14Mar2016 */
            $param_array[39] = ($formdata['ddldis_msg_key2'] > 0) ? $formdata['ddldis_msg_key2'] : 'NULL';            		//messagekey2
            $param_array[40] = ($formdata['ddlena_promo_edit_ord'] > 0) ? $formdata['ddlena_promo_edit_ord'] : 'NULL';        //enablepromoeditords
            $param_array[41] = ($formdata['ddlcust_tax_key1'] > 0) ? $formdata['ddlcust_tax_key1'] : 'NULL';                  //custtaxkey1
            $param_array[42] = ($formdata['ddlcust_tax_key2'] > 0) ? $formdata['ddlcust_tax_key2'] : 'NULL';                  //custtaxkey2
            $param_array[43] = ($formdata['ddlcust_tax_key3'] > 0) ? $formdata['ddlcust_tax_key3'] : 'NULL';                  //custtaxkey3
			$param_array[44] = 0; 
			$param_array[45] = ($formdata['ddlcust_visual'] > 0) ? $formdata['ddlcust_visual'] : 'NULL';
			$param_array[46] = ($formdata['ddlcust_adver'] > 0) ? $formdata['ddlcust_adver'] : 'NULL';
			$param_array[47] = ($formdata['ddlloyality_key'] > 0) ? $formdata['ddlloyality_key'] : 'NULL';
			$param_array[48] = $formdata['ddlapplytax_option']; 
			$param_array[49] = $formdata['txttaxregistraion']; 
			$param_array[50] = $formdata['txttraname']; 
			$param_array[51] = $formdata['txttranamearabic']; 
			//print_r($param_array);exit;
			//SFA_Comman::pre($param_array);
			if($formdata['hdnid'] > 0) {
			/*Following param commented by nilesh on 14March2016*/
			
				
                $param_array[52] = $formdata['hdnchangeroutestatus'];		//it's for update route id in route sequence.
                /*instead of 46 param, 47 param added by nilesh on 14Mar2016*/
                $last_id = $this->SFA_Comman->executequery('CALL sp_edit_account_customer_addcustomer(?)',$param_array,'');
                //update setting1 values in database
                $last_id = $this->addsetting1values($formdata);
                //update setting2 values in database
                $last_id = $this->addsetting2values($formdata);
                //update Contact values in database
                //$last_id = $this->addcontactvalues($formdata);
        
                SFA_Message::setMsg($this->translate->_('Update Record'));
			}
			else
			{
				/*instead of 45 param, 46 param added by nilesh on 14Mar2016*/
                $result = $this->SFA_Comman->executequery('CALL sp_add_account_customer_addcustomer(?)',$param_array,'');
               $last_id = $result[0][0]['last_id'];
               
                $formdata['hdnid'] = $last_id;
                //update setting1 values in database
                $last_id = $this->addsetting1values($formdata);
                //update setting2 values in database
                $last_id = $this->addsetting2values($formdata);
                //update Contact values in database
                //$last_id = $this->addcontactvalues($formdata);
                
                SFA_Message::setMsg($this->translate->_('New Record'));
			}
		   $this->_helper->redirector('customer', 'customer', 'account');
		}
		elseif($params['id'] > 0)
		{
			$result  		= $this->SFA_Comman->executequery('CALL sp_get_account_customer_addcustomer(?)',$params['id'],'');
			//SFA_Comman::pre($result);
			
			$res['txtcode'] 	= $result[4][0]['customercode'];
			$res['ddlstatus'] 	= $result[4][0]['activecustomer'];
			$res['txtname']		= $result[4][0]['customername'];
			$res['txtaddress'] 	= $result[4][0]['customeraddress1'];
			$res['ddlroutename'] 	= $result[4][0]['routecode'];	    
			$res['txtaddress2'] 	= $result[4][0]['customeraddress2'];	    
			$res['txtalt_code'] 	= $result[4][0]['alternatecode'];
			$res['txtaddress3'] 	= $result[4][0]['customeraddress3'];	    
			$res['txttel_num'] 	= $result[4][0]['customerphone'];
			$res['ddlhead_off'] 	= $result[4][0]['headofficecode'];
			$res['txtpobox'] 	= $result[4][0]['pobox'];
			$res['ddlinvo_pay'] 	= $result[4][0]['invoicepaymentterms'];
			$res['txtcash_bal'] 	= $result[4][0]['balance'];
			$res['ddlprice_key'] 	= $result[4][0]['pricingkey'];
			$res['txtcr_limit'] 	= $result[4][0]['creditlimit'];
			$res['ddlpromo_key'] 	= $result[4][0]['promotionkey'];
			$res['txtcr_days'] 	= $result[4][0]['creditlimitdays'];
			$res['ddlauthorise_grp']= $result[4][0]['authorizeditemgrpkey'];	    
			$res['ddldis_key'] 	= $result[4][0]['discountkey'];
			$res['ddldistri_key'] 	= $result[4][0]['distributionkey'];
			$res['ddloutlet_pro'] 	= $result[4][0]['outletsubtype'];
			$res['ddlsurvey_key'] 	= $result[4][0]['surveykey'];
			$res['ddlcust_cat'] 	= $result[4][0]['customercategory'];
			$res['ddl_ar_cust'] 	= $result[4][0]['arcustomertype'];	    
			$res['txtspot_limit'] 	= $result[4][0]['threshholdlimit'];
			$res['txtexp_run_val'] 	= $result[4][0]['exprunningvalue'];
			$res['txtcont_name'] 	= $result[4][0]['contactname'];
			$res['txtalpha_code'] 	= $result[4][0]['ancustomercode'];
			$res['rdofftype'] 	= $result[4][0]['type'];
			$res['ddlhead_off'] 	= $result[4][0]['headofficecode'];
			$res['createddate'] 	= date("d-m-Y",strtotime($result[4][0]['cdat']));
			$res['txtlangname']		= $result[4][0]['arbcustomername'];	//ARBCustomerName
			$res['txtcustomer_state']	= $result[4][0]['customerstate'];	//CustomerState	
			$res['txtcustoemr_city']	= $result[4][0]['customercity'];	//ArabicCustomerCity
			$res['txtlangaddress']		= $result[4][0]['arbcustomeraddress1'];	//ArbCustomerAddress1		
			$res['txtlangaddress2']		= $result[4][0]['arbcustomeraddress2'];	//ArbCustomerAddress2
			$res['txtremark']		= $result[4][0]['arbcustomeraddress3'];	//ArbCustomerAddress3                
			$res['txtcont_name']		= $result[4][0]['arbcontactname']; 	//ArbContactName
			$res['txt_bill_to_bill']		= $result[4][0]['numoutstandinginv']; //txt_bill_to_bill
			$res['ddlloyality_key']		= $result[4][0]['loyalitykey']; //txt_bill_to_bill
			
			//print_r($result[4][0]);exit;
			//setting1 Values
			$res['ddldis_msg_key1'] 	= $result[4][0]['messagekey1'];
			$res['ddldis_msg_key2'] 	= $result[4][0]['messagekey2'];
			$res['ddlena_sugg_sales']	= $result[4][0]['enablesuggestsales'];
			$res['ddlena_auto_fill_ret'] 	= $result[4][0]['enableautofillreturns'];
			$res['ddlena_auto_fill_dmg'] 	= $result[4][0]['enableautofilldamaged'];
			$res['ddlena_auto_fill_cap'] 	= $result[4][0]['enablesigcapture'];
			$res['ddlenable_ret_trxn'] 	= $result[4][0]['enablereturnstrxn'];	    
			$res['ddlena_ar_coll'] 		= $result[4][0]['enablearcollection'];
			$res['ddlena_promo_trxn'] 	= $result[4][0]['enablepromotrxn'];
			$res['ddlena_sales_trxn'] 	= $result[4][0]['enablesalestrxn'];
			$res['ddlena_invo_copy'] 	= $result[4][0]['enableinvoicecopy'];
			$res['ddlinvo_price_prnt'] 	= $result[4][0]['invoicepriceprint'];
			$res['ddlprnt_seq'] 		= $result[4][0]['printsequence'];
			$res['ddlena_edit_price_invs'] 	= $result[4][0]['enablepriceeditinvs'];
			$res['ddlena_sell_prev'] 	= $result[4][0]['enablesellprevious'];
			$res['ddlena_survey_audit'] 	= $result[4][0]['enablesurveyaudit'];
			$res['ddlena_del_instru'] 	= $result[4][0]['enabledelivinstruct'];
			$res['ddlena_invo_comment'] 	= $result[4][0]['enableinvoicecomment'];
			$res['ddlinvo_dtl_entry'] 	= $result[4][0]['invoicedetailentry'];
			$res['ddlord_dtl_entry'] 	= $result[4][0]['orderdetailentry'];	    
			$res['ddlforce_stock_capture'] 	= $result[4][0]['forcestockcapture'];
			$res['ddlauto_set_coll'] 	= $result[4][0]['autosettlecollection'];
			$res['ddlorder_format'] 	= $result[4][0]['orderformat'];
			$res['ddlena_delay_prnt'] 	= $result[4][0]['enabledelayprint'];
			$res['ddlena_damage_ret'] 	= $result[4][0]['enabledamagedreturns'];
			$res['ddlautho_itm_list'] 	= $result[4][0]['authorizeditemlistctl'];	    
			$res['ddlprnt_msg_key1'] 	= $result[4][0]['messagekey3'];
			$res['ddlprnt_msg_key2'] 	= $result[4][0]['messagekey4'];
			$res['ddlinvo_header_msg_key'] 	= $result[4][0]['messagekey5'];
			$res['ddlinvo_trailor_key'] 	= $result[4][0]['messagekey6'];
			$res['ddlena_itm_barcode'] 	= $result[4][0]['enableupcprint'];
			$res['chkenablepos'] 		= $result[4][0]['enableposequipment'];
			$res['chkenableadvpay'] 	= $result[4][0]['enableadvancepayment'];
			$res['chkenablebuybackfree']	= $result[4][0]['enablebuybackfree'];
			$res['chkenablerental']		= $result[4][0]['enablerental'];
			$res['chkenableautofillsales']	= $result[4][0]['enableautofillsales'];
			$res['chkenableadvbatchsel']	= $result[4][0]['enablebatchselection'];
			$res['ddlchannel']				= $result[4][0]['channelcode'];
			$res['txtstartdate']			= $result[4][0]['startdate'];
			$res['txtenddate']				= $result[4][0]['enddate'];
			$res['chkenableexchangetrxn']  = $result[4][0]['enableexchangetrxn'];
			$res['chkenablepassrtntranxn']  = $result[4][0]['enablereturnpassword'];
			
			//setting2 Values
			$res['ddlprnt_lang'] 		= $result[4][0]['printlanguageflag'];
			$res['txthis_max_del'] 		= $result[4][0]['histmaxdeliveries'];	    
			$res['ddlcust_tax_key1'] 	= $result[4][0]['custtaxkey1'];
			$res['ddlcust_tax_key2'] 	= $result[4][0]['custtaxkey2'];
			$res['ddlcust_tax_key3'] 	= $result[4][0]['custtaxkey3'];
			$res['ddlcust_visual'] 	= $result[4][0]['visualcode'];
			$res['ddlcust_adver'] 	= $result[4][0]['advertisecode'];
			$res['txtlost_placement'] 	= $result[4][0]['lostplacementdelivs'];
			$res['txtnew_plac_deli'] 	= $result[4][0]['newplacementdelivs'];	    
			$res['txtinvo_copies'] 		= $result[4][0]['invoicecopies'];
			$res['txttax_id'] 		        = $result[4][0]['customertaxid'];
			$res['ddlena_promo_edit_ord'] 	= $result[4][0]['enablepromoeditords'];	    
			$res['ddlena_promo_edit_invs'] 	= $result[4][0]['enablepromoeditinvs'];
			$res['txtzip'] 			        = $result[4][0]['customerzip'];
			$res['ddladd_addl_invo'] 	= $result[4][0]['enableaddlpromoords'];
			$res['ddlena_promo_invoice'] 	= $result[4][0]['enableaddlpromoinvoices'];
			$res['chkroundnetamt'] 		= $result[4][0]['roundnetamount'];
			$res['chkenforce_promo'] 	= $result[4][0]['enforcepromotion'];
			$res['chkdraftcpy'] 		= $result[4][0]['enabledraftcopy'];
			$res['txtforward_factor'] 	= $result[4][0]['forwardcoverfactor'];			
			$res['txtcustoemr_city'] 	= $result[4][0]['customercity'];
			$res['txtcustomer_state'] 	= $result[4][0]['customerstate'];
			$res['txtmemo1'] 		= $result[4][0]['memo1'];
			$res['txtmemo2'] 		= $result[4][0]['memo2'];
			$res['chkallow_cash'] 		= $result[4][0]['allowcashoncreditexceed'];
			$res['chkprint_outlet'] 	= $result[4][0]['printoutletitemcode'];	    
			$res['ddlsales'] 		= $result[4][0]['invoiceformatoption'];
			$res['ddltax_option'] 		= $result[4][0]['customertaxidoptions'];
			$res['txtfixlati']		= $result[4][0]['fixedlatitude'];
			$res['txtfixlong']		= $result[4][0]['fixedlongitude'];
			$res['chkgraceperiod']		= $result[4][0]['graceperiod'];
		    // $res['txtroundingoff']		= $result[4][0]['roundingoffvalue'];
			$res['ddlroundingtype']		= $result[4][0]['roundingoffvalue'];
		    $res['txtbarcode']		    = $result[4][0]['barcode'];
			$res['ddlitemmustkey']		= $result[4][0]['itemmustkey'];
			$res['ddldistibutioncheck']		= $result[4][0]['distribution_check_id'];
			$res['ddlpricesurvey']		= $result[4][0]['price_survey_id'];
			
			$res['ddlapplytax_option']		= $result[4][0]['applytax'];
			$res['txttaxregistraion']		= $result[4][0]['taxregistrationnumber'];
			$res['txttraname']		= $result[4][0]['traname'];
			$res['txttranamearabic']		= $result[4][0]['tranamearabic'];
	
			//contact values
			$res['txtshop_tel'] 		= $result[4][0]['shoptelephonenumber'];
			$res['txtshop_fax'] 		= $result[4][0]['shopfaxnumber'];
			$res['txtowner_name'] 		= $result[4][0]['ownername'];
			$res['txtowner_land_line'] 	= $result[4][0]['ownerlandlinenumber'];
			$res['txtowner_mob'] 		= $result[4][0]['ownermobilenumber'];
			$res['txtcont_person_name'] 	= $result[4][0]['contactname'];
			$res['txtconta_land_line'] 	= $result[4][0]['contactpersonlandlinenumber'];
			$res['txtcontact_email'] 	= $result[4][0]['contactpersonemail'];
			$res['txtcontact_mobile'] 	= $result[4][0]['contactpersonmobilenumber'];	    
			$res['txtmanager_name'] 	= $result[4][0]['purchasemanagername'];	    
			$res['txtmanager_land_line'] 	= $result[4][0]['purchasemanagerlandlinenumber'];
			$res['txtmanager_mobile'] 	= $result[4][0]['purchasemanagermobilenumber'];
			$res['txtmanager_email'] 	= $result[4][0]['purchasemanageremail'];
			$res['txtware_maneger_name'] 	= $result[4][0]['warehousemanagername'];	    
			$res['txtware_manager_land_line'] 	= $result[4][0]['warehousemanagerlandlinenumber'];
			$res['txtware_manager_mobile'] 	= $result[4][0]['warehousemanagermobilenumber'];
			$res['txtware_manager_email'] 	= $result[4][0]['warehousemanageremail'];
	
			//$res['txtlangname'] 	= $result[4][0]['']; ddlcust_visual
			//$res['txt_bill_to_bill']= $result[4][0][''];
			//$res['txtlangaddress'] 	= $result[4][0][''];
			//$res['txtlangaddress2'] 	= $result[4][0][''];
			//$res['txtremark'] 	= $result[4][0][''];
			
			$this->view->formdata 		        = $res;
			$this->view->price_key_data	    	= $result[0];
			$this->view->route_data		        = $result[1];
			$this->view->promo_key_data	    	= $result[2];
			$this->view->customer_category	    = $result[3];
			$this->view->survey_key_data	    = $result[5];
			$this->view->discount_key_data	    = $result[6];
			$this->view->distribution_key_data	= $result[7];
			$this->view->authorise_grp_data	    = $result[8];
			$this->view->headoffice_data	    = $result[9];
			$this->view->outletproduct_data	    = $result[10];
			$this->view->cust_templ		        = $result[11];			
			$this->view->status				    = $result[12];
            $this->view->customer_tax_key	    = $result[13];
			$this->view->itemmust_key_data	    = $result[14];
			$this->view->channel_data	    	= $result[15];			
			$this->view->customer_visual	    	= $result[16];
			$this->view->distribution_check	    	= $result[17];
			$this->view->price_survey	    	= $result[18];
			$this->view->advertising_data	    	= $result[19];
			$this->view->loyalitydata	    	= $result[20];
			
			if($params['edit'] !='yes')
			{
			$result = $this->SFA_Comman->executequery('CALL sp_get_table_last_id("?")','customermaster','');
			$this->view->formdata['txtcode'] = ($result[0][0]['Auto_increment'] == '') ? '1' : $result[0][0]['Auto_increment'];
			$this->view->cust_tmplval = $params['id'];		
			}
		}	
		else
		{
			$result 	= $this->SFA_Comman->executequery('CALL sp_getcombobox_account_customer_addcustomer()','','');	   
			$this->view->price_key_data	    	= $result[0];
			$this->view->route_data		        = $result[1];
			$this->view->promo_key_data		    = $result[2];
			$this->view->customer_category	    = $result[3];
			$this->view->survey_key_data	    = $result[4];
			$this->view->discount_key_data	    = $result[5];
			$this->view->distribution_key_data	= $result[6];
			$this->view->authorise_grp_data	    = $result[7];
			$this->view->headoffice_data	    = $result[8];
			$this->view->outletproduct_data	    = $result[9];			
			$this->view->cust_templ			    = $result[11];
			$this->view->status				    = $result[12];
            $this->view->customer_tax_key	    = $result[13];
			$this->view->itemmust_key_data	    = $result[14];
			$this->view->channel_data	    	= $result[15];
			$this->view->customer_visual	    	= $result[16];
			$this->view->distribution_check	    	= $result[17];
			$this->view->price_survey	    	= $result[18];
			$this->view->advertising_data	    	= $result[19];
			$this->view->loyalitydata	    	= $result[20];
		}
    }
    /**
    * @name       addsetting1values
    * @since      17-04-2012
    * @version    Release: 1
    * @author     Jinal <Jinal@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This function is used to edit values of setting1
    */   
    public function addsetting1values($formdata){	
	$param_setting1_array  	 = array();
	$param_setting1_array[1]  = ($formdata['ddldis_msg_key1']<>""?$formdata['ddldis_msg_key1']:0);		//messagekey1
	$param_setting1_array[2]  = ($formdata['ddldis_msg_key2']<>""?$formdata['ddldis_msg_key2']:0); //$formdata['ddldis_msg_key2'];		//messagekey2
	$param_setting1_array[3]  = ($formdata['ddlena_sugg_sales']<>""?$formdata['ddlena_sugg_sales']:0); //$formdata['ddlena_sugg_sales'];		//enablesuggestsales
	$param_setting1_array[4]  = ($formdata['ddlena_auto_fill_ret']<>""?$formdata['ddlena_auto_fill_ret']:0); //$formdata['ddlena_auto_fill_ret'];		//enableautofillreturns
	$param_setting1_array[5]  = ($formdata['ddlena_auto_fill_dmg']<>""?$formdata['ddlena_auto_fill_dmg']:0); //$formdata['ddlena_auto_fill_dmg'];		//enableautofilldamaged
	$param_setting1_array[6]  = ($formdata['ddlena_auto_fill_cap']<>""?$formdata['ddlena_auto_fill_cap']:0); //$formdata['ddlena_auto_fill_cap'];		//enablesigcapture
	$param_setting1_array[7]  = ($formdata['ddlenable_ret_trxn']<>""?$formdata['ddlenable_ret_trxn']:0); //$formdata['ddlenable_ret_trxn'];		//enablereturnstrxn
	$param_setting1_array[8]  = ($formdata['ddlena_ar_coll']<>""?$formdata['ddlena_ar_coll']:0); //$formdata['ddlena_ar_coll'];		//enablearcollection
	$param_setting1_array[9]  = ($formdata['ddlena_promo_trxn']<>""?$formdata['ddlena_promo_trxn']:0); //$formdata['ddlena_promo_trxn'];		//enablepromotrxn
	$param_setting1_array[10] = ($formdata['ddlena_sales_trxn']<>""?$formdata['ddlena_sales_trxn']:0); //$formdata['ddlena_sales_trxn'];		//enablesalestrxn
	$param_setting1_array[11] = ($formdata['ddlena_invo_copy']<>""?$formdata['ddlena_invo_copy']:0); //$formdata['ddlena_invo_copy'];		//enableinvoicecopy
	$param_setting1_array[12] = ($formdata['ddlinvo_price_prnt']<>""?$formdata['ddlinvo_price_prnt']:0); //$formdata['ddlinvo_price_prnt'];		//invoicepriceprint
	$param_setting1_array[13] = ($formdata['ddlprnt_seq']<>""?$formdata['ddlprnt_seq']:0); //$formdata['ddlprnt_seq'];			//printsequence
	$param_setting1_array[14] = ($formdata['ddlena_edit_price_invs']<>""?$formdata['ddlena_edit_price_invs']:0); //$formdata['ddlena_edit_price_invs'];	//enablepriceeditinvs
	$param_setting1_array[15] = ($formdata['ddlena_sell_prev']<>""?$formdata['ddlena_sell_prev']:0); //$formdata['ddlena_sell_prev'];		//enablesellprevious
	$param_setting1_array[16] = ($formdata['ddlena_survey_audit']<>""?$formdata['ddlena_survey_audit']:0); //$formdata['ddlena_survey_audit'];		//enablesurveyaudit
	$param_setting1_array[17] = ($formdata['ddlena_del_instru']<>""?$formdata['ddlena_del_instru']:0); //$formdata['ddlena_del_instru'];		//enabledelivinstruct
	$param_setting1_array[18] = ($formdata['ddlena_invo_comment']<>""?$formdata['ddlena_invo_comment']:0); //$formdata['ddlena_invo_comment'];		//enableinvoicecomment
	$param_setting1_array[19] = ($formdata['ddlinvo_dtl_entry']<>""?$formdata['ddlinvo_dtl_entry']:0); //$formdata['ddlinvo_dtl_entry'];		//invoicedetailentry
	$param_setting1_array[20] = ($formdata['ddlord_dtl_entry']<>""?$formdata['ddlord_dtl_entry']:0); //$formdata['ddlord_dtl_entry'];		//orderdetailentry
	$param_setting1_array[21] = ($formdata['ddlforce_stock_capture']<>""?$formdata['ddlforce_stock_capture']:0); //$formdata['ddlforce_stock_capture'];	//forcestockcapture
	$param_setting1_array[22] = ($formdata['ddlauto_set_coll']<>""?$formdata['ddlauto_set_coll']:0); //$formdata['ddlauto_set_coll'];		//autosettlecollection
	$param_setting1_array[23] = ($formdata['ddlorder_format']<>""?$formdata['ddlorder_format']:0); //$formdata['ddlorder_format'];		//orderformat
	$param_setting1_array[24] = ($formdata['ddlena_delay_prnt']<>""?$formdata['ddlena_delay_prnt']:0); //$formdata['ddlena_delay_prnt'];		//enabledelayprint
	$param_setting1_array[25] = ($formdata['ddlena_damage_ret']<>""?$formdata['ddlena_damage_ret']:0); //$formdata['ddlena_damage_ret'];		//enabledamagedreturns
	$param_setting1_array[26] = ($formdata['ddlautho_itm_list']<>""?$formdata['ddlautho_itm_list']:0); //$formdata['ddlautho_itm_list'];		//authorizeditemlistctl
	$param_setting1_array[27] = $formdata['hdnid'];				//customercode
	$param_setting1_array[28] = ($formdata['ddlprnt_msg_key1']<>""?$formdata['ddlprnt_msg_key1']:0); //$formdata['ddlprnt_msg_key1'];		//messagekey3
	$param_setting1_array[29] = ($formdata['ddlprnt_msg_key2']<>""?$formdata['ddlprnt_msg_key2']:0); //$formdata['ddlprnt_msg_key2'];		//messagekey4
	$param_setting1_array[30] = ($formdata['ddlinvo_header_msg_key']<>""?$formdata['ddlinvo_header_msg_key']:0); //$formdata['ddlinvo_header_msg_key'];	//messagekey5
	$param_setting1_array[31] = ($formdata['ddlinvo_trailor_key']<>""?$formdata['ddlinvo_trailor_key']:0); //$formdata['ddlinvo_trailor_key'];		//messagekey6
	$param_setting1_array[32] = ($formdata['ddlena_itm_barcode']<>""?$formdata['ddlena_itm_barcode']:0); //$formdata['ddlena_itm_barcode'];		//enableupcprint
	$param_setting1_array[33] = ($formdata['chkenablepos']<>""?$formdata['chkenablepos']:0); //$formdata['chkenablepos'];			//enableposequipment
	$param_setting1_array[34] = ($formdata['chkenableadvpay']<>""?$formdata['chkenableadvpay']:0); //$formdata['chkenableadvpay'];		//enableadvancepayment
	$param_setting1_array[35] = ($formdata['chkenablebuybackfree']<>""?$formdata['chkenablebuybackfree']:0); //$formdata['chkenablebuybackfree'];		//enablebuybackfree
	$param_setting1_array[36] = ($formdata['chkenablerental']<>""?$formdata['chkenablerental']:0); //$formdata['chkenablerental'];		//enablerental	
	$param_setting1_array[37] = ($formdata['chkenableautofillsales']<>""?$formdata['chkenableautofillsales']:0); //$formdata['chkenableautofillsales'];	//EnableAutoFillSales 
	$param_setting1_array[38] = ($formdata['chkenableadvbatchsel']<>""?$formdata['chkenableadvbatchsel']:0); //$formdata['chkenableadvbatchsel'];		//enablebatchselection
	$param_setting1_array[39] = ($formdata['ddlchannel'] > 0) ? $formdata['ddlchannel'] : 'NULL';	//channelcode
	$param_setting1_array[40] = $formdata['txtstartdate'];				//startdate
	$param_setting1_array[41] = $formdata['txtenddate'];				//enddate
	$param_setting1_array[42] = ($formdata['chkenableexchangetrxn']<>""?$formdata['chkenableexchangetrxn']:0); //$formdata['chkenableexchangetrxn'];
	$param_setting1_array[43] = ($formdata['chkenablepassrtntranxn']<>""?$formdata['chkenablepassrtntranxn']:0); //$formdata['chkenablepassrtntranxn'];
	
	$last_id = $this->SFA_Comman->executequery('CALL sp_edit_account_customer_setting1(?)',$param_setting1_array,'');
	return $last_id;
    }

    /**
    * @name       addsetting2values
    * @since      17-04-2012
    * @version    Release: 1
    * @author     Jinal <Jinal@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *($formdata['']<>""?$formdata['']:0); //
    * This function is used to edit values of setting2
    */   
    public function addsetting2values($formdata){
	$param_setting2_array 	   = array();		
	$param_setting2_array[1]   = $formdata['ddlprnt_lang']; 		//printlanguageflag
	$param_setting2_array[2]   = $formdata['txthis_max_del']; 	//histmaxdeliveries
	$param_setting2_array[3]   = ($formdata['ddlcust_tax_key1']<>""?$formdata['ddlcust_tax_key1']:0); //$formdata['ddlcust_tax_key1']; 	//custtaxkey1
	$param_setting2_array[4]   = ($formdata['ddlcust_tax_key2']<>""?$formdata['ddlcust_tax_key2']:0); //$formdata['ddlcust_tax_key2']; 	//custtaxkey2
	$param_setting2_array[5]   = ($formdata['ddlcust_tax_key3']<>""?$formdata['ddlcust_tax_key3']:0); //$formdata['ddlcust_tax_key3']; 	//custtaxkey3
	$param_setting2_array[6]   = ($formdata['txtlost_placement']<>""?$formdata['txtlost_placement']:0); //$formdata['txtlost_placement']; 	//lostplacementdelivs
	$param_setting2_array[7]   = ($formdata['txtnew_plac_deli']<>""?$formdata['txtnew_plac_deli']:0); //$formdata['txtnew_plac_deli']; 	//newplacementdelivs
	$param_setting2_array[8]   = ($formdata['txtinvo_copies']<>""?$formdata['txtinvo_copies']:0); //$formdata['txtinvo_copies']; 	//invoicecopies
	$param_setting2_array[9]   = $formdata['txttax_id']; 		//customertaxid
	$param_setting2_array[10]  = ($formdata['ddlena_promo_edit_ord']<>""?$formdata['ddlena_promo_edit_ord']:0); //$formdata['ddlena_promo_edit_ord']; //enablepromoeditords
	$param_setting2_array[11]  = ($formdata['ddlena_promo_edit_invs']<>""?$formdata['ddlena_promo_edit_invs']:0); //$formdata['ddlena_promo_edit_invs']; //enablepromoeditinvs
	$param_setting2_array[12]  = $formdata['txtzip']; 		//customerzip
	$param_setting2_array[13]  = ($formdata['ddladd_addl_invo']<>""?$formdata['ddladd_addl_invo']:0); //$formdata['ddladd_addl_invo']; 	//enableaddlpromoords
	$param_setting2_array[14]  = ($formdata['ddlena_promo_invoice']<>""?$formdata['ddlena_promo_invoice']:0); //$formdata['ddlena_promo_invoice'];	//enableaddlpromoinvoices
	$param_setting2_array[15]  = ($formdata['chkroundnetamt']<>""?$formdata['chkroundnetamt']:0); //$formdata['chkroundnetamt']; 	//roundnetamount
	$param_setting2_array[16]  = ($formdata['chkenforce_promo']<>""?$formdata['chkenforce_promo']:0); //$formdata['chkenforce_promo']; 	//enforcepromotion
	$param_setting2_array[17]  = ($formdata['chkdraftcpy']<>""?$formdata['chkdraftcpy']:0); //$formdata['chkdraftcpy']; 		//enabledraftcopy
	$param_setting2_array[18]  = ($formdata['txtforward_factor']<>""?$formdata['txtforward_factor']:0); //$formdata['txtforward_factor']; 	//forwardcoverfactor	
	$param_setting2_array[19]  = $formdata['txtcustoemr_city']; 	//customercity
	$param_setting2_array[20]  = $formdata['txtcustomer_state']; 	//customerstate
	$param_setting2_array[21]  = $formdata['txtmemo1']; 		//memo1
	$param_setting2_array[22]  = $formdata['txtmemo2']; 		//memo2
	$param_setting2_array[23]  = ($formdata['chkallow_cash']<>""?$formdata['chkallow_cash']:0); //$formdata['chkallow_cash']; 	//allowcashoncreditexceed
	$param_setting2_array[24]  = ($formdata['chkprint_outlet']<>""?$formdata['chkprint_outlet']:0); //$formdata['chkprint_outlet']; 	//printoutletitemcode
	$param_setting2_array[25]  = $formdata['hdnid'];		//customercode		
	$param_setting2_array[26]  = ($formdata['ddlsales']<>""?$formdata['ddlsales']:0); //$formdata['ddlsales'];		//invoiceformatoption
	$param_setting2_array[27]  = ($formdata['ddltax_option']<>""?$formdata['ddltax_option']:0); //$formdata['ddltax_option'];	//customertaxidoptions
	$param_setting2_array[28]  = ($formdata['txtfixlati']<>""?$formdata['txtfixlati']:0); //$formdata['txtfixlati'];		//fixedlatitude
	$param_setting2_array[29]  = ($formdata['txtfixlong']<>""?$formdata['txtfixlong']:0); //$formdata['txtfixlong'];		//fixedlongitude	
	$param_setting2_array[30]  = ($formdata['chkgraceperiod']<>""?$formdata['chkgraceperiod']:0); //$formdata['chkgraceperiod'];	//graceperiod
    $param_setting2_array[31]  = ($formdata['ddlroundingtype']<>""?$formdata['ddlroundingtype']:0); //$formdata['ddlroundingtype'];//$formdata['txtroundingoff'];	//roundingoffvalue 
    $param_setting2_array[32]  = $formdata['txtbarcode'];		//barcode;
	$param_setting2_array[33]  = ($formdata['ddlitemmustkey'] > 0) ? $formdata['ddlitemmustkey'] : 'NULL';		//itemmustkey;
	$param_setting2_array[34]   = ($formdata['ddlcust_visual']<>""?$formdata['ddlcust_visual']:0); //$formdata['ddlcust_visual'];
	$param_setting2_array[35]  = ($formdata['ddldistibutioncheck'] > 0) ? $formdata['ddldistibutioncheck'] : 'NULL';
	$param_setting2_array[36]  = ($formdata['ddlpricesurvey'] > 0) ? $formdata['ddlpricesurvey'] : 'NULL';
	$param_setting2_array[37]  = ($formdata['ddlcust_adver'] > 0) ? $formdata['ddlcust_adver'] : 'NULL';
	$param_setting2_array[38]  = ($formdata['ddlapplytax_option']<>""?$formdata['ddlapplytax_option']:0); //$formdata['ddlapplytax_option']; 		
	$param_setting2_array[39]  = $formdata['txttaxregistraion']; 		
	$param_setting2_array[40]  = $formdata['txttraname']; 		
	$param_setting2_array[41]  = $formdata['txttranamearabic']; 		
	$last_id = $this->SFA_Comman->executequery('CALL sp_edit_account_customer_setting2(?)',$param_setting2_array,'');
	return $last_id;
    }


    /**
    * @name       addcontactvalues
    * @since      17-04-2012
    * @version    Release: 1
    * @author     Jinal <Jinal@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This function is used to edit values of contact
    */   
    public function addcontactvalues($formdata){
	$param_contact_array      = array();		
	$param_contact_array[1]   = $formdata['txtshop_tel']; 			//shoptelephonenumber
	$param_contact_array[2]   = $formdata['txtshop_fax']; 			//shopfaxnumber
	$param_contact_array[3]   = $formdata['txtowner_name']; 		//ownername
	$param_contact_array[4]   = $formdata['txtowner_land_line']; 		//ownerlandlinenumber
	$param_contact_array[5]   = $formdata['txtowner_mob']; 			//ownermobilenumber
	$param_contact_array[6]   = $formdata['txtcont_person_name']; 		//contactname
	$param_contact_array[7]   = $formdata['txtconta_land_line']; 		//contactpersonlandlinenumber
	$param_contact_array[8]   = $formdata['txtcontact_mobile']; 		//contactpersonmobilenumber
	$param_contact_array[9]   = $formdata['txtcontact_email']; 		//contactpersonemail	    
	$param_contact_array[10]  = $formdata['txtmanager_name']; 		//purchasemanagername	    
	$param_contact_array[11]  = $formdata['txtmanager_land_line']; 		//purchasemanagerlandlinenumber
	$param_contact_array[12]  = $formdata['txtmanager_mobile']; 		//purchasemanagermobilenumber
	$param_contact_array[13]  = $formdata['txtmanager_email']; 		//purchasemanageremail
	$param_contact_array[14]  = $formdata['txtware_maneger_name']; 		//warehousemanagername	    
	$param_contact_array[15]  = $formdata['txtware_manager_land_line']; 	//warehousemanagerlandlinenumber
	$param_contact_array[16]  = $formdata['txtware_manager_mobile'];	//warehousemanagermobilenumber
	$param_contact_array[17]  = $formdata['txtware_manager_email']; 	//warehousemanageremail
	$param_contact_array[18]  = $formdata['hdnid'];				//customercode
	
 	$last_id = $this->SFA_Comman->executequery('CALL sp_edit_account_customer_contact(?)',$param_contact_array,'');
	return $last_id;
    }



    
    /**
    * @name       setting1values
    * @since      17-04-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This function is used to define values for combo
    */    
    public function setting1values(){
		
	$result = $this->SFA_Comman->executequery('CALL sp_getcombobox_account_customer_setting1()',"",'');
	$this->view->customer_messages	= $result[0];
	
	$priceprint = array();
	$priceprint[0]['id'] 		= '0';
	$priceprint[1]['id'] 		= '1';
	$priceprint[0]['val'] 		= 'Do not Print';
	$priceprint[1]['val'] 		= 'Summary Pricing and Amount Print';
	$this->view->priceprint_data	= $priceprint;
	
	
	$stockcapture_data = array();
	$stockcapture_data[0]['id'] 	= '0';
	$stockcapture_data[1]['id'] 	= '1';
	$stockcapture_data[2]['id'] 	= '2';
	$stockcapture_data[0]['val'] 	= 'Disable';
	$stockcapture_data[1]['val'] 	= 'Enable';
	$stockcapture_data[2]['val'] 	= 'Force Capture';
	$this->view->stockcapture_data 	= $stockcapture_data;

	$prntseq = array();
	$prntseq[0]['id'] 		= '1';
	$prntseq[1]['id'] 		= '2';
	$prntseq[2]['id'] 		= '3';
	$prntseq[3]['id'] 		= '4';
	$prntseq[0]['val'] 		= 'Itemmaster.ActualItemCode';
	$prntseq[1]['val'] 		= 'Itemmaster.AlternateCode';
	$prntseq[2]['val'] 		= 'Itemmaster.PrintSequenceCustomer';
	$prntseq[3]['val'] 		= 'OutLetCode';	
	$this->view->print_seq_data = $prntseq;


	$editprice = array();
	$editprice[0]['id'] 		= '1';
	$editprice[1]['id'] 		= '2';
	$editprice[2]['id'] 		= '3';
	$editprice[3]['id'] 		= '4';
	$editprice[4]['id'] 		= '5';
	$editprice[5]['id'] 		= '6';
	$editprice[6]['id'] 		= '7';
	$editprice[7]['id'] 		= '8';	
	$editprice[0]['val'] 		= 'Disable';
	$editprice[1]['val'] 		= 'Allow Chgs to sale and Rtn Prices';
	$editprice[2]['val'] 		= 'Allow Chgs to Return Prices Only';
	$editprice[3]['val'] 		= 'Allow Chgs to Sales and Good Rtrn Only';
	$editprice[4]['val'] 		= 'Allow Chgs to  Sales and Bad Rtrn Only';
	$editprice[5]['val'] 		= 'Allow Chgs to Sales Only';
	$editprice[6]['val'] 		= 'Allow Chgs to Good Rtrn Only';
	$editprice[7]['val'] 		= 'Allow Chgs to Bad Rtrn Only';
	$this->view->edit_price_data 	= $editprice;

	$suggestsale = array();
	$suggestsale[0]['id'] 		= '0';
	$suggestsale[1]['id'] 		= '1';	
	$suggestsale[0]['val'] 		= 'Disable';
	$suggestsale[1]['val'] 		= 'Enabled';	
	$this->view->sugg_sale_data 	= $suggestsale;

	$autofilldamage = array();
	$autofilldamage[0]['id'] 	= '1';
	$autofilldamage[1]['id'] 	= '2';
	$autofilldamage[2]['id'] 	= '3';
	$autofilldamage[3]['id'] 	= '4';
	$autofilldamage[0]['val'] 	= 'Disable';
	$autofilldamage[1]['val'] 	= 'Fill All Items';
	$autofilldamage[2]['val'] 	= 'Fill Items From Sales With Qty.';
	$autofilldamage[3]['val'] 	= 'Fill Items From Sales Without Qty.';
	
	$this->view->autofill_dmg_data 	= $autofilldamage;

	$salestrxn = array();
	$salestrxn[0]['id']    		= '1';
	$salestrxn[0]['val'] 		= 'Disable';
	$salestrxn[1]['id']    		= '2';
	$salestrxn[1]['val'] 		= 'Allow on Order Transaction';
	$salestrxn[2]['id']    		= '3';
	$salestrxn[2]['val'] 		= 'Allow on Invoice Transaction';
	$salestrxn[3]['id']    		= '4';
	$salestrxn[3]['val'] 		= 'Both';
	$this->view->sales_trxn_data 	= $salestrxn;

	$signcapture = array();
	$signcapture[0]['id'] 		= '1';
	$signcapture[1]['id'] 		= '2';
	$signcapture[2]['id'] 		= '3';	
	$signcapture[0]['val'] 		= 'Disable';
	$signcapture[1]['val'] 		= 'Enable Signature Capture';
	$signcapture[2]['val'] 		= 'Enable and Print on Order/Invoice';
	$this->view->sign_capt_data 	= $signcapture;

	$sellprev = array();
	$sellprev[0]['id'] 		= '1';
	$sellprev[1]['id'] 		= '2';
	$sellprev[0]['val'] 		= 'Disable';
	$sellprev[1]['val'] 		= 'Allow Resale of Previous Trxns';
	$this->view->sell_prev_data 	= $sellprev;
    }    
    
    /**
    * @name       setting2values
    * @since      03-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This function is used to define values for combo
    */
    
    public function setting2values()
    {
		
		$rounding_type = array();
		$rounding_type[0]['id'] 	= '0';
		$rounding_type[1]['id'] 	= '1';	
		$rounding_type[2]['id'] 	= '2';	
		$rounding_type[0]['val'] 	= 'Normal';
		$rounding_type[1]['val'] 	= 'Round Down';
		$rounding_type[2]['val'] 	= 'Round Up';
		$this->view->round_type	= $rounding_type;
		
		$enable_rounding = array();
		$enable_rounding[0]['id'] 	= '0';
		$enable_rounding[1]['id'] 	= '1';	
		$enable_rounding[2]['id'] 	= '2';	
		$enable_rounding[0]['val'] 	= 'Disable';
		$enable_rounding[1]['val'] 	= 'Allow Line Level Rounding';
		$enable_rounding[2]['val'] 	= 'Allow Invoice Level Rounding';
		$this->view->enable_round	= $enable_rounding;
		
		$sales_res = array();
		$sales_res[0]['id'] 	= '1';
		$sales_res[1]['id'] 	= '2';	
		$sales_res[0]['val'] 	= 'Net Sales and Return';
		$sales_res[1]['val'] 	= 'Split Sales and Return';
		$this->view->sales_res	= $sales_res;
	
		$lang_res = array();
		$lang_res[0]['id'] 	= '1';
		$lang_res[1]['id'] 	= '2';
		$lang_res[0]['val'] 	= 'English/Arabic';
		$lang_res[1]['val'] 	= 'Arabic';
		$this->view->lang_res	= $lang_res;
	
		$tax_data = array();
		$tax_data[0]['id']    	= '0';
		$tax_data[1]['id']    	= '1';
		$tax_data[2]['id']    	= '2';		
		$tax_data[0]['val']    	= $this->translate->_('Do not Print');
		$tax_data[1]['val']    	= $this->translate->_('Print Tax In Amount');
		$tax_data[2]['val']    	= $this->translate->_('Print Tax In Percent (%)');		
		$this->view->tax_data	= $tax_data;	
		
		
		$applytax_data = array();
		$applytax_data[0]['id']    	= '0';
		$applytax_data[1]['id']    	= '1';
		$applytax_data[2]['id']    	= '2';		
		$applytax_data[0]['val']    	= $this->translate->_('Disabled');
		$applytax_data[1]['val']    	= $this->translate->_('Apply Tax for Sales & Returns Only');
		$applytax_data[2]['val']    	= $this->translate->_('Apply Tax for All');		
		$this->view->applytax_data	= $applytax_data;
	
		$promo_edit = array();
		$promo_edit[0]['id']	= '0';
		$promo_edit[1]['id']	= '1';
		$promo_edit[2]['id']	= '2';
		$promo_edit[3]['id']	= '3';
		$promo_edit[0]['val']	= 'Disable';
		$promo_edit[1]['val']	= 'Sales Only';
		$promo_edit[2]['val']	= 'Return Only';
		$promo_edit[3]['val']	= 'Both';	
		$this->view->promo_edit	= $promo_edit;		
    }   

    /**
    * @name       messagesAction
    * @since      25-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use display customer messages
    */
    public function messagesAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	if($formdata["hdDelete"]==1)
	{
	    $ids = implode(',',$formdata['chk']);
	    $param_array 	= array();
	    $param_array[1]	= $ids;
	    $param_array[2]	= $this->currentUser->username;
	    
	    $result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_customer_customermsg(?)',$param_array,'');
	    
	    if($result[0][0]['deleted_id'] =='')
	    {
		$ids		= explode(',',$ids);
		$checked 	= $ids;
		
		SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
	    }
	    else
	    {
		$deleted_id 	= explode(',',$result[0][0]['deleted_id']);
		$ids		= explode(',',$ids);
		$checked 	= array_diff($ids,$deleted_id);
		
		if(count($ids) != count($deleted_id)){
		    SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
		}
		
		SFA_Message::setMsg($this->translate->_('Delete Record'));
	    }
	}

	$this->view->title	= $this->translate->_('Customer Message');
	$cols_array 	= array('messagekey','messagedescription','activestatus');
	$columns_show 	=  array($this->translate->_('Message Key'),$this->translate->_('Customer Message'),$this->translate->_('Status'));
	
	
	// prepare the configuration for grid
	$pagingparams = array(
			"show_grid_heading" => true,
			"grid_heading_message" => $this->translate->_('Overview'),
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			"show_searchbox" => true,
            "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
            "pagename" => $this->translate->_('Customer Message'),
			"show_selectbox" => true,
			"show_editlink" => true,
			"selected_list" => $checked,
			"show_deletelink" => false,			
			"show_deleteall" => false,
			"primaryid" => "messagekey",
			"status_cols" => array(
						   array(
						   "cols_name" => "activestatus",
						   "status_change" => array("0"=>"Inactive","1"=>"Active")
						   )
						   ),
			 "editlink" => array("/account/customer/addmessages/id/#pattern#/edit/yes/","#pattern#"),
			"nodata_message" => $this->translate->_('No Record(s) Found'),
			"fetch_columns_inquery" => $cols_array,
			"show_columns" => $columns_show
			);
    
    if(!$this->checkaccess("update"))
    {
        $pagingparams["show_editlink"] = false;
    }
    
	// create grid class object
	$pagingshow = new SFA_Paging($pagingparams);
	
	// call common function of grid class
	$get_return_vals = $pagingshow->commnfunc();
	
	//print_r($get_return_vals['where_condition']);
	
	// call the stored procedure for fetch the data  
	$param_array[1] = '1';
	$param_array[2] = '';
	$param_array[3] = $get_return_vals['order_columns_name'];
	$param_array[4] = $get_return_vals['order_type'];
	$param_array[5] = $get_return_vals['offset'];
	$param_array[6] = (int)$get_return_vals['show_records_per_page'];
	$param_array[7] = implode(", ",$pagingparams["fetch_columns_inquery"]);
	$param_array[8] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
    
    $downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];
    
    // Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
	$printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
    
	// called stored procedure for counter
	$result = $this->SFA_Comman->executequery('CALL sp_get_account_customer_customermsg(?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	$data_arr["count"]	= $result[0][0]['counter'];	
	$data_arr["data"][0] 	= $result[1];
	
	// pass the data in summary_showdatagrid() function & create a final variable for view
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");	
    }
     /**
    * @name       addmessagesAction
    * @since      25-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add customer messages
    */
    public function addmessagesAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	$this->view->title	= $this->translate->_('Customer Messages');
	
	$this->view->returnUrl = $_SERVER["HTTP_REFERER"];

	if(count($formdata) > 0 && $formdata['txtcode'] !='' && $formdata['txtmsg_desc'] !='' )
	{
	    $param_array 	= array();
	    $param_array[1]	= $formdata['txtmsg_desc'];
	    $param_array[2]	= $formdata['txtmesg1'];
	    $param_array[3]	= $formdata['txtmesg2'];
	    $param_array[4]	= $formdata['txtmesg3'];
	    $param_array[5]	= $formdata['txtmesg4'];
	    $param_array[6]	= $formdata['txtmesg_arb1'];
	    $param_array[7]	= $formdata['txtmesg_arb2'];
	    $param_array[8]	= $formdata['txtmesg_arb3'];
	    $param_array[9]	= $formdata['txtmesg_arb4'];
	    $param_array[10]	= $formdata['ddlstatus'];
	    $param_array[11]	= $this->currentUser->username;
	    $param_array[12]	= $formdata['txtaltcode'];
	    
	    if($formdata['hdnid'] > 0)
	    {
		$param_array[13] = $formdata['hdnid'];		
		$last_id = $this->SFA_Comman->executequery('CALL sp_edit_account_customer_addcustomermsg(?)',$param_array,'');		
		SFA_Message::setMsg($this->translate->_('Update Record'));		
	    }
	    else
	    {
		$last_id = $this->SFA_Comman->executequery('CALL sp_add_account_customer_addcustomermsg(?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('New Record'));
	    }
	    if($formdata["returnUrl"]!="")
		$this->_redirect($formdata["returnUrl"]);
	    else
		$this->_helper->redirector('messages', 'customer', 'account');
	}
	elseif($params['id'] > 0)
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_account_customer_addcustomermsg(?)',$params['id'],'');
	    $this->view->formdata = $result[0][0];
	    $this->view->formdata['createddate'] = date("d-m-Y",strtotime($result[0][0]['cdat']));	    
	}
	else
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_account_customer_addcustomermsg(?)','0','');	    
	    $this->view->formdata['messagekey'] = $result[0][0]['Auto_increment'];
	}
    }
     /**
    * @name       templateAction
    * @since      16-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for display template details
    */
    public function templateAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
	
	if($formdata["hdDelete"]==1)
	{
	    $ids = implode(',',$formdata['chk']);
	    $param_array 	= array();
	    $param_array[1]	= $ids;
	    $param_array[2]	= $this->currentUser->username;
	    
	    $result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_customer_customer(?)',$param_array,'');
	    
	    if($result[0][0]['deleted_id'] =='')
	    {
		$ids		= explode(',',$ids);
		$checked 	= $ids;
		
		SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
	    }
	    else
	    {
		$deleted_id 	= explode(',',$result[0][0]['deleted_id']);
		$ids		= explode(',',$ids);
		$checked 	= array_diff($ids,$deleted_id);
		
		if(count($ids) != count($deleted_id)){
		    SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
		}
		
		SFA_Message::setMsg($this->translate->_('Delete Record'));
	    }
	}


	$this->view->title	= $this->translate->_('Customer Template');
	$this->view->general	= $this->translate->_('General');
	$this->view->setting1	= $this->translate->_('Settings 1');
	$this->view->setting2	= $this->translate->_('Settings 2');
	
	
	
	$code				= $this->translate->_('Code');
	$cust_name 			= $this->translate->_('Template Name');	
	$payment_mode		= $this->translate->_('Payment Mode');
	$status				= $this->translate->_('Status');
	
	$cols_array 	= array('customercode','customer.templatename','payment_mode(invoicepaymentterms) AS invoicepaymentterms','activecustomer');
	$columns_show 	=  array($code,$cust_name,$payment_mode,$status);
	
	// prepare the configuration for grid
	$pagingparams = array(
			"show_grid_heading" => true,
			"grid_heading_message" => $this->translate->_('Overview'),
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			"show_searchbox" => true,
            "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
            "pagename" => $this->translate->_('Customer Template'),
			"show_selectbox" => true,
			"show_editlink" => true,
			"selected_list" => $checked,
			"show_deletelink" => false,			
			"show_deleteall" => false,
			"primaryid" => "customercode",
			"status_cols" => array(
						   array(
						   "cols_name" => "activecustomer",
						   "status_change" => array("0"=>"Inactive","1"=>"Active")
						   )
						   ),
			"editlink" => array("/account/customer/addtemplate/id/#pattern#/edit/yes/","#pattern#"),
			"nodata_message" => $this->translate->_('No Record(s) Found'),
			"fetch_columns_inquery" => $cols_array,
			"show_columns" => $columns_show
			);
	
    if(!$this->checkaccess("update"))
    {
        $pagingparams["show_editlink"] = false;
    }
    
	// create grid class object
	$pagingshow = new SFA_Paging($pagingparams);
	
	// call common function of grid class
	$get_return_vals = $pagingshow->commnfunc();
	
	//print_r($get_return_vals['where_condition']);
	
	// call the stored procedure for fetch the data  
	$param_array[1] = '1';
	$param_array[2] = '';
	$param_array[3] = $get_return_vals['order_columns_name'];
	$param_array[4] = $get_return_vals['order_type'];
	$param_array[5] = $get_return_vals['offset'];
	$param_array[6] = (int)$get_return_vals['show_records_per_page'];
	$param_array[7] = implode(", ",$pagingparams["fetch_columns_inquery"]);
	$param_array[8] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
	
    $downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];
    
    // Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
	$printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
	// called stored procedure for counter
	$result = $this->SFA_Comman->executequery('CALL sp_get_account_customer_customertmpl(?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	$data_arr["count"] 	= $result[0][0]['counter'];
	$data_arr["data"][0] 	= $result[1];
	
	// pass the data in summary_showdatagrid() function & create a final variable for view
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addtemplateAction
    * @since      21-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add settings of customer template.
    */
    public function addtemplateAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

        $this->view->setting1	= $this->translate->_('Settings 1');
        $this->view->setting2	= $this->translate->_('Settings 2');
        
        
        $this->setting1values();
        $this->setting2values();
    
        $invo_pay_term = array();
        $invo_pay_term[0]['id']  = '0';
        $invo_pay_term[0]['val'] = 'CASH Only';
        $invo_pay_term[1]['id']  = '1';
        $invo_pay_term[1]['val'] = 'CASH or CHEQUE';
        $invo_pay_term[2]['id']  = '2';
        $invo_pay_term[2]['val'] = 'CHARGE Only (GC)';
        $invo_pay_term[3]['id']  = '3';
        $invo_pay_term[3]['val'] = 'TC (CASH or CHEQUE)';
        $invo_pay_term[4]['id']  = '4';
        $invo_pay_term[4]['val'] = 'TC (CASH Only)';
        $this->view->invo_pay_term	= $invo_pay_term;
        
        
            if(count($formdata) > 0)
            {
				//SFA_Comman::pre($formdata);
                $param_array    = array();
                $param_array[1] = $formdata['hdnid'];				//customercode
                $param_array[2] = $formdata['ddlstatus'];			//activecustomer
                $param_array[3] = $formdata['txtname'];				//customername
                $param_array[4] = $formdata['txtaddress'];			//customeraddress1	
                $param_array[5] = $formdata['ddlroutename'];			//routecode	
                $param_array[6] = $formdata['txtaddress2'];			//customeraddress2	
                $param_array[7] = $formdata['txtalt_code'];			//alternatecode	
                $param_array[8] = $formdata['txtaddress3'];			//customeraddress3
                $param_array[9] = $formdata['txttel_num'];			//customerphone
                $param_array[10] = $formdata['ddlhead_off'];			//headofficecode
                $param_array[11] = $formdata['txtpobox'];			//pobox
                $param_array[12] = $formdata['ddlinvo_pay'];			//invoicepaymentterms
                $param_array[13] = str_replace(',','',$formdata['txtcash_bal']);			//balance
                $param_array[14] = $formdata['ddlprice_key'];			//pricingkey
                $param_array[15] = str_replace(',','',$formdata['txtcr_limit']);			//creditlimit
                $param_array[16] = ($formdata['ddlpromo_key'] > 0) ? $formdata['ddlpromo_key'] : 'NULL';			//promotionkey
                $param_array[17] = $formdata['txtcr_days'];			//creditlimitdays
                $param_array[18] = $formdata['ddlauthorise_grp'];		//authorizeditemgrpkey
                $param_array[19] = $formdata['ddldis_key'];			//discountkey
                $param_array[20] = $formdata['ddldistri_key'];			//distributionkey
                $param_array[21] = $formdata['ddloutlet_pro'];			//outletsubtype
                $param_array[22] = $formdata['ddlsurvey_key'];			//surveykey
                $param_array[23] = ($formdata['ddlcust_cat'] > 0) ? $formdata['ddlcust_cat'] : 'NULL';			//customercategory
                $param_array[24] = $formdata['ddl_ar_cust'];			//arcustomertype
                $param_array[25] = $formdata['txtspot_limit'];			//expirylimit
                $param_array[26] = $formdata['txtexp_run_val'];			//exprunningvalue
                $param_array[27] = $formdata['txtcont_name'];			//ArbContactName
                $param_array[28] = $formdata['txtalpha_code'];			//ancustomercode
                $param_array[29] = '1';						//type		
                $param_array[30] = $this->currentUser->username;		//created
                $param_array[31] = $formdata['txtlangname'];			//ARBCustomerName
                $param_array[32] = $formdata['txtcustomer_state'];		//CustomerState	
                $param_array[33] = $formdata['txtcustoemr_city'];		//ArabicCustomerCity
                $param_array[34] = $formdata['txtlangaddress'];			//ArbCustomerAddress1		
                $param_array[35] = $formdata['txtlangaddress2'];		//ArbCustomerAddress2
                $param_array[36] = $formdata['txtremark'];			//ArbCustomerAddress3
                $param_array[37] = $formdata['txt_bill_to_bill']; 		//numoutstandinginv
                $param_array[38] = $formdata['txt_templatename']; 		//templatename
                $param_array[39] = ($formdata['ddldis_msg_key1'] > 0) ? $formdata['ddldis_msg_key1'] : 'NULL';            		//messagekey1
                $param_array[40] = ($formdata['ddldis_msg_key2'] > 0) ? $formdata['ddldis_msg_key2'] : 'NULL';            		//messagekey2
                $param_array[41] = ($formdata['ddlena_promo_edit_ord'] > 0) ? $formdata['ddlena_promo_edit_ord'] : 'NULL';        //enablepromoeditords
                $param_array[42] = ($formdata['ddlcust_tax_key1'] > 0) ? $formdata['ddlcust_tax_key1'] : 'NULL';                  //custtaxkey1
                $param_array[43] = ($formdata['ddlcust_tax_key2'] > 0) ? $formdata['ddlcust_tax_key2'] : 'NULL';                  //custtaxkey2
                $param_array[44] = ($formdata['ddlcust_tax_key3'] > 0) ? $formdata['ddlcust_tax_key3'] : 'NULL';                  //custtaxkey3
				/*Following param commented by nilesh on 15Mar2016*/
				//$param_array[45] = ($formdata['ddlcust_visual'] > 0) ? $formdata['ddlcust_visual'] : 'NULL';
				//$param_array[46] = ($formdata['ddlcust_adver'] > 0) ? $formdata['ddlcust_adver'] : 'NULL';
				//$param_array[47] = ($formdata['ddlloyality_key'] > 0) ? $formdata['ddlloyality_key'] : 'NULL';
                 
                if($formdata['hdnid'] > 0) {
				
                
                $param_array[45] = $formdata['hdnchangeroutestatus'];		//it's for update route id in route sequence.
                /*Edit customer template parameter list added by nilesh on 15Mar2016*/
                $last_id = $this->SFA_Comman->executequery('CALL sp_edit_account_customer_addcustomertmpl(?)',$param_array,'');
                //update setting1 values in database
                $last_id = $this->addsetting1values($formdata);
                //update setting2 values in database
                $last_id = $this->addsetting2values($formdata);
                //update Contact values in database
               // $last_id = $this->addcontactvalues($formdata);
        
                SFA_Message::setMsg($this->translate->_('Update Record'));
                }
                else
                {
				/*ADD customer template parameter list added by nilesh on 15Mar2016*/
                    $result = $this->SFA_Comman->executequery('CALL sp_add_account_customer_addcustomertmpl(?)',$param_array,'');
                    $last_id = $result[0][0]['last_id'];
                    
                    $formdata['hdnid'] = $last_id;
                    //update setting1 values in database
                    $last_id = $this->addsetting1values($formdata);
                    //update setting2 values in database
                    $last_id = $this->addsetting2values($formdata);
                    //update Contact values in database
                    //$last_id = $this->addcontactvalues($formdata);
                    
                    SFA_Message::setMsg($this->translate->_('New Record'));
                }
                $this->_helper->redirector('template', 'customer', 'account');
        }
        elseif($params['id'] > 0)
        {
            $result  		= $this->SFA_Comman->executequery('CALL sp_get_account_customer_addcustomertmpl(?)',$params['id'],'');
            //SFA_Comman::pre($result);
            
            $res['ddlstatus'] 	= $result[4][0]['activecustomer'];	    
            $res['ddlroutename'] 	= $result[4][0]['routecode'];	    
            $res['ddlhead_off'] 	= $result[4][0]['headofficecode'];
            $res['ddlinvo_pay'] 	= $result[4][0]['invoicepaymentterms'];
            $res['txtcash_bal'] 	= $result[4][0]['balance'];
            $res['ddlprice_key'] 	= $result[4][0]['pricingkey'];
            $res['txtcr_limit'] 	= $result[4][0]['creditlimit'];
            $res['ddlpromo_key'] 	= $result[4][0]['promotionkey'];
            $res['txtcr_days'] 		= $result[4][0]['creditlimitdays'];
            $res['ddlauthorise_grp']	= $result[4][0]['authorizeditemgrpkey'];	    
            $res['ddldis_key'] 		= $result[4][0]['discountkey'];
            $res['ddldistri_key'] 	= $result[4][0]['distributionkey'];
            $res['ddloutlet_pro'] 	= $result[4][0]['outletsubtype'];
            $res['ddlsurvey_key'] 	= $result[4][0]['surveykey'];
            $res['ddlcust_cat'] 	= $result[4][0]['customercategory'];
            $res['ddl_ar_cust'] 	= $result[4][0]['arcustomertype'];	    
            $res['txtspot_limit'] 	= $result[4][0]['threshholdlimit'];
            $res['txtexp_run_val'] 	= $result[4][0]['exprunningvalue'];
            $res['txtcont_name'] 	= $result[4][0]['contactname'];
            $res['txtalpha_code'] 	= $result[4][0]['ancustomercode'];
            $res['rdofftype'] 		= $result[4][0]['type'];
            $res['ddlhead_off'] 	= $result[4][0]['headofficecode'];
            $res['createddate'] 	= date("d-m-Y",strtotime($result[4][0]['cdat']));
            $res['txtlangname']		= $result[4][0]['arbcustomername'];	//ARBCustomerName
            $res['txtcustomer_state']	= $result[4][0]['customerstate'];	//CustomerState	
            $res['txtcustoemr_city']	= $result[4][0]['customercity'];	//ArabicCustomerCity
            $res['txtlangaddress']	= $result[4][0]['arbcustomeraddress1'];	//ArbCustomerAddress1		
            $res['txtlangaddress2']	= $result[4][0]['arbcustomeraddress2'];	//ArbCustomerAddress2
            $res['txtremark']		= $result[4][0]['arbcustomeraddress3'];	//ArbCustomerAddress3                
            $res['txtcont_name']	= $result[4][0]['arbcontactname']; 	//ArbContactName
            $res['txt_bill_to_bill']	= $result[4][0]['numoutstandinginv']; 	//numoutstandinginv
            $res['txt_templatename']	= $result[4][0]['templatename']; 	//templatename
            $res['ddlloyality_key']		= $result[4][0]['loyalitykey'];
            //setting1 Values
            $res['ddldis_msg_key1'] 	= $result[4][0]['messagekey1'];
            $res['ddldis_msg_key2'] 	= $result[4][0]['messagekey2'];
            $res['ddlena_sugg_sales']	= $result[4][0]['enablesuggestsales'];
            $res['ddlena_auto_fill_ret'] 	= $result[4][0]['enableautofillreturns'];
            $res['ddlena_auto_fill_dmg'] 	= $result[4][0]['enableautofilldamaged'];
            $res['ddlena_auto_fill_cap'] 	= $result[4][0]['enablesigcapture'];
            $res['ddlenable_ret_trxn'] 	= $result[4][0]['enablereturnstrxn'];	    
            $res['ddlena_ar_coll'] 		= $result[4][0]['enablearcollection'];
            $res['ddlena_promo_trxn'] 	= $result[4][0]['enablepromotrxn'];
            $res['ddlena_sales_trxn'] 	= $result[4][0]['enablesalestrxn'];
            $res['ddlena_invo_copy'] 	= $result[4][0]['enableinvoicecopy'];
            $res['ddlinvo_price_prnt'] 	= $result[4][0]['invoicepriceprint'];
            $res['ddlprnt_seq'] 		= $result[4][0]['printsequence'];
            $res['ddlena_edit_price_invs'] 	= $result[4][0]['enablepriceeditinvs'];
            $res['ddlena_sell_prev'] 	= $result[4][0]['enablesellprevious'];
            $res['ddlena_survey_audit'] 	= $result[4][0]['enablesurveyaudit'];
            $res['ddlena_del_instru'] 	= $result[4][0]['enabledelivinstruct'];
            $res['ddlena_invo_comment'] 	= $result[4][0]['enableinvoicecomment'];
            $res['ddlinvo_dtl_entry'] 	= $result[4][0]['invoicedetailentry'];
            $res['ddlord_dtl_entry'] 	= $result[4][0]['orderdetailentry'];	    
            $res['ddlforce_stock_capture'] 	= $result[4][0]['forcestockcapture'];
            $res['ddlauto_set_coll'] 	= $result[4][0]['autosettlecollection'];
            $res['ddlorder_format'] 	= $result[4][0]['orderformat'];
            $res['ddlena_delay_prnt'] 	= $result[4][0]['enabledelayprint'];
            $res['ddlena_damage_ret'] 	= $result[4][0]['enabledamagedreturns'];
            $res['ddlautho_itm_list'] 	= $result[4][0]['authorizeditemlistctl'];	    
            $res['ddlprnt_msg_key1'] 	= $result[4][0]['messagekey3'];
            $res['ddlprnt_msg_key2'] 	= $result[4][0]['messagekey4'];
            $res['ddlinvo_header_msg_key'] 	= $result[4][0]['messagekey5'];
            $res['ddlinvo_trailor_key'] 	= $result[4][0]['messagekey6'];
            $res['ddlena_itm_barcode'] 		= $result[4][0]['enableupcprint'];
            $res['chkenablepos'] 		= $result[4][0]['enableposequipment'];
            $res['chkenableadvpay'] 		= $result[4][0]['enableadvancepayment'];
            $res['chkenablebuybackfree']	= $result[4][0]['enablebuybackfree'];
            $res['chkenablerental']		= $result[4][0]['enablerental'];
            $res['chkenableautofillsales']	= $result[4][0]['enableautofillsales'];
            $res['chkenableadvbatchsel']	= $result[4][0]['enablebatchselection'];
            $res['ddlchannel']				= $result[4][0]['channelcode'];	
			$res['chkenableexchangetrxn']   = $result[4][0]['enableexchangetrxn'];
			$res['chkenablepassrtntranxn']  = $result[4][0]['enablereturnpassword'];	
			
            //setting2 Values
            $res['ddlprnt_lang'] 	    = $result[4][0]['printlanguageflag'];
            $res['txthis_max_del'] 	    = $result[4][0]['histmaxdeliveries'];	    
            $res['ddlcust_tax_key1'] 	= $result[4][0]['custtaxkey1'];
            $res['ddlcust_tax_key2'] 	= $result[4][0]['custtaxkey2'];
            $res['ddlcust_tax_key3'] 	= $result[4][0]['custtaxkey3'];
	     $res['ddlcust_visual'] 	= $result[4][0]['visualcode'];
	    $res['ddlcust_adver'] 	= $result[4][0]['advertisecode'];	
            $res['txtlost_placement'] 	= $result[4][0]['lostplacementdelivs'];
            $res['txtnew_plac_deli'] 	= $result[4][0]['newplacementdelivs'];	    
            $res['txtinvo_copies'] 	    = $result[4][0]['invoicecopies'];
            $res['txttax_id'] 		    = $result[4][0]['customertaxid'];
            $res['ddlena_promo_edit_ord'] 	= $result[4][0]['enablepromoeditords'];	    
            $res['ddlena_promo_edit_invs'] 	= $result[4][0]['enablepromoeditinvs'];
            $res['txtzip'] 			    = $result[4][0]['customerzip'];
            $res['ddladd_addl_invo'] 	= $result[4][0]['enableaddlpromoords'];
            $res['ddlena_promo_invoice']= $result[4][0]['enableaddlpromoinvoices'];
            $res['chkroundnetamt'] 	    = $result[4][0]['roundnetamount'];
            $res['chkenforce_promo'] 	= $result[4][0]['enforcepromotion'];
            $res['chkdraftcpy'] 	    = $result[4][0]['enabledraftcopy'];
            $res['txtforward_factor'] 	= $result[4][0]['forwardcoverfactor'];			
            $res['txtcustoemr_city'] 	= $result[4][0]['customercity'];
            $res['txtcustomer_state'] 	= $result[4][0]['customerstate'];
            $res['txtmemo1'] 		    = $result[4][0]['memo1'];
            $res['txtmemo2'] 		    = $result[4][0]['memo2'];
            $res['chkallow_cash'] 	    = $result[4][0]['allowcashoncreditexceed'];
            $res['chkprint_outlet'] 	= $result[4][0]['printoutletitemcode'];	    
            $res['ddlsales'] 		    = $result[4][0]['invoiceformatoption'];
            $res['ddltax_option'] 	    = $result[4][0]['customertaxidoptions'];
            $res['txtfixlati']		    = $result[4][0]['fixedlatitude'];
            $res['txtfixlong']		    = $result[4][0]['fixedlongitude'];
           // $res['txtroundingoff']		= $result[4][0]['roundingoffvalue'];
			$res['ddlroundingtype']		= $result[4][0]['roundingoffvalue'];
            $res['txtbarcode']		    = $result[4][0]['barcode'];
            
    
            //contact values
            $res['txtshop_tel'] 		= $result[4][0]['shoptelephonenumber'];
            $res['txtshop_fax'] 		= $result[4][0]['shopfaxnumber'];
            $res['txtowner_name'] 		= $result[4][0]['ownername'];
            $res['txtowner_land_line'] 		= $result[4][0]['ownerlandlinenumber'];
            $res['txtowner_mob'] 		= $result[4][0]['ownermobilenumber'];
            $res['txtcont_person_name'] 	= $result[4][0]['contactname'];
            $res['txtconta_land_line'] 		= $result[4][0]['contactpersonlandlinenumber'];
            $res['txtcontact_email'] 		= $result[4][0]['contactpersonemail'];
            $res['txtcontact_mobile'] 		= $result[4][0]['contactpersonmobilenumber'];	    
            $res['txtmanager_name'] 		= $result[4][0]['purchasemanagername'];	    
            $res['txtmanager_land_line'] 	= $result[4][0]['purchasemanagerlandlinenumber'];
            $res['txtmanager_mobile'] 		= $result[4][0]['purchasemanagermobilenumber'];
            $res['txtmanager_email'] 		= $result[4][0]['purchasemanageremail'];
            $res['txtware_maneger_name'] 	= $result[4][0]['warehousemanagername'];	    
            $res['txtware_manager_land_line'] 	= $result[4][0]['warehousemanagerlandlinenumber'];
            $res['txtware_manager_mobile'] 	= $result[4][0]['warehousemanagermobilenumber'];
            $res['txtware_manager_email'] 	= $result[4][0]['warehousemanageremail'];
            
            $this->view->formdata 		        = $res;
            $this->view->price_key_data	    	= $result[0];
            $this->view->route_data		        = $result[1];
            $this->view->promo_key_data		    = $result[2];
            $this->view->customer_category	    = $result[3];
            $this->view->survey_key_data	    = $result[5];
            $this->view->discount_key_data	    = $result[6];
            $this->view->distribution_key_data	= $result[7];
            $this->view->authorise_grp_data	    = $result[8];
            $this->view->headoffice_data	    = $result[9];
            $this->view->outletproduct_data	    = $result[10];
            $this->view->cust_templ		        = $result[11];	    
            $this->view->tmpl_id 		        = $params['id'];
            $this->view->customer_tax_key	    = $result[12];
			$this->view->itemmust_key_data	    = $result[13];
			$this->view->channel_data	    	= $result[14];
			$this->view->customer_visual	    	= $result[15];
			$this->view->distribution_check	    	= $result[16];
			$this->view->advertising_data	    	= $result[17];
			$this->view->loyalitydata	    	= $result[18];
        }	
        else
        {
            $result 	= $this->SFA_Comman->executequery('CALL sp_getcombobox_account_customer_addcustomertmpl()','','');	   
            $this->view->price_key_data		    = $result[0];
            $this->view->route_data		        = $result[1];
            $this->view->promo_key_data		    = $result[2];
            $this->view->customer_category	    = $result[3];
            $this->view->survey_key_data	    = $result[4];
            $this->view->discount_key_data	    = $result[5];
            $this->view->distribution_key_data	= $result[6];
            $this->view->authorise_grp_data	    = $result[7];
            $this->view->headoffice_data	    = $result[8];
            $this->view->outletproduct_data	    = $result[9];
            $this->view->cust_templ		        = $result[10];
            $this->view->status				    = $result[11];
            $this->view->customer_tax_key	    = $result[12];
			$this->view->itemmust_key_data	    = $result[13];
			$this->view->channel_data	    	= $result[14];
	    $this->view->customer_visual	    	= $result[15];
	    $this->view->distribution_check	    	= $result[16];
	    $this->view->advertising_data	    	= $result[17];
		$this->view->loyalitydata	    	= $result[18];
        }
    }    
    /**
    * @name       authorizegroup
    * @since      7-02-2012
    * @version    Release: 1
    * @author     HD <Hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for display authorize group
    */
    public function authorizegroupAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

        if($formdata["hdDelete"]==1)
        {
            $ids = implode(',',$formdata['chk']);
            $param_array 	= array();
            $param_array[1]	= $ids;
            $param_array[2]	= $this->currentUser->username;
            
            $result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_customer_authorizegroup(?)',$param_array,'');
            
            
            if($result[0][0]['deleted_id'] =='')
            {
                $ids		= explode(',',$ids);
                $checked 	= $ids;
                
                SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
            }
            else
            {
                $deleted_id 	= explode(',',$result[0][0]['deleted_id']);
                $ids		= explode(',',$ids);
                $checked 	= array_diff($ids,$deleted_id);
                
                if(count($ids) != count($deleted_id)){
                    SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
                }
            
                SFA_Message::setMsg($this->translate->_('Delete Record'));
            }
        }
    
        $this->view->title = $this->translate->_('Customer Authorize Group');
    
        $cols_array     = array('groupnumber','groupdescription');
        $columns_show   = array($this->translate->_('Group Number'),$this->translate->_('Group Description'));
        
        if($this->css == 'ar_') {
			$cols_array[1]	= 'arbgroupdescription';
		}
    
        $pagingparams = array(
                    "show_grid_heading" => true,
                    "grid_heading_message" => $this->translate->_('Overview'),
                    "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
                    "show_searchbox" => true,
                    "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
                    "pagename" => $this->translate->_('Customer Authorize Group'),
                    "show_selectbox" => true,
                    "show_editlink" => true,
                    "show_deletelink" => false,
                    "selected_list" => $checked,
                    "deletelink" => array("/account/index/authorizegroup/id/#pattern#/delete/yes/","#pattern#"),
                    "show_deleteall" => false,
                    "primaryid" => "groupnumber",
                    "editlink" => array("/account/customer/addauthorizegroup/id/#pattern#","#pattern#"),
                    "fetch_columns_inquery" => $cols_array,
                    "show_columns" => $columns_show,
                    "nodata_message" => $this->translate->_('No Record(s) Found')
                    );
        
        if(!$this->checkaccess("update"))
        {
            $pagingparams["show_editlink"] = false;
        }
        
        $pagingshow = new SFA_Paging($pagingparams);
        // call common function of grid class
        $get_return_vals = $pagingshow->commnfunc();
    
        $get_return_vals['where_condition'] .= " AND grouptype = 3 ";
        $param_array    = array();
        $param_array[1] = '';
        $param_array[2] = $get_return_vals['order_columns_name'];
        $param_array[3] = $get_return_vals['order_type'];
        $param_array[4] = $get_return_vals['offset'];
        $param_array[5] = (int)$get_return_vals['show_records_per_page'];
        $param_array[6] = implode(", ",$pagingparams["fetch_columns_inquery"]);
        $param_array[7] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
        
        $downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];
        
        // Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
        $printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];	
        // called stored procedure for counter
        $result = $this->SFA_Comman->executequery('CALL sp_get_promotions_customer_authorizegroup(?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
        
        $data_arr["count"] = $result[0][0]['counter'];
        $data_arr["data"][0] = $result[1];
        
        // pass the data in summary_showdatagrid() function & create a final variable for view
        $this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
    
        $this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");

    }
    /**
    * @name       addauthorizegroupAction
    * @since      07-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for add authorize group
    */
    public function addauthorizegroupAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
	

        if(isset($formdata["hdnid"]) && $formdata["hdnid"]>0)
            $ex_param = "/key/".$params["hdnid"];
            
        if(isset($params["id"]) && $params["id"]>0)
            $ex_param = "/key/".$params["id"];
        
        $this->view->itemgrid  = $this->view->BaseUrl("/account/customer/authorizegroupitemgrid".$ex_param);
        
        if($params['id'] > 0){
            //Following code is for Edit
            if(!empty($formdata['txtgrp_num']) && !empty($formdata['txtgrp_desc'])){
                $param_array = array();
                //$formdata['txtgrp_num'];
                $param_array[1] = trim($formdata["hdnid"]); 		//groupnumber
                $param_array[2] = trim($formdata['txtgrp_desc']);	//groupdescription
                $param_array[3] = trim($formdata['txtgrp_arb']);	//arbgroupdescription
                $param_array[4] = $this->currentUser->username;		//modified
                
                $r_update = $this->SFA_Comman->executequery('CALL sp_edit_promotions_customer_addauthorizegroup(?)',$param_array,'');
                SFA_Message::setMsg($this->translate->_('Update Record'));
                $this->_helper->redirector('authorizegroup', 'customer', 'account');
            }
            $result  		= $this->SFA_Comman->executequery('CALL sp_get_account_customer_addauthorizegroup(?)',$params['id'],'');
            $res['txtgrp_num'] 	= $result[1][0]['groupnumber'];
            $res['txtgrp_desc'] = $result[1][0]['groupdescription'];
            $res['txtgrp_arb'] 	= $result[1][0]['arbgroupdescription'];
    
            $this->view->formdata = $res;
            $this->view->item_group = $result[0];    
	    } else {
		//Following code is for Add
		if(!empty($formdata['txtgrp_num']) && !empty($formdata['txtgrp_desc'])){
		    $param_array = array();
			$param_array[1] = trim($formdata['txtgrp_num']); 	//groupnumber
			$param_array[2] = trim($formdata['txtgrp_desc']);	//groupdescription
			$param_array[3] = trim($formdata['txtgrp_arb']);	//arbgroupdescription
			$param_array[4] = implode(',',$formdata['ddlitem_code']);	//itemcode - productgroupdetail
			$param_array[5] = 3;					            //grouptype
			$param_array[6] = $this->currentUser->username;		//created
			$param_array[7] = '0';					            //promopcprice
			$param_array[8] = '0';					            //promocaseprice
			$param_array[9] = count($formdata['ddlitem_code']);	//itemcount
		    
		    $r_update = $this->SFA_Comman->executequery('CALL sp_get_account_customer_addauthorizegroup(?)',$param_array,'');
		    SFA_Message::setMsg($this->translate->_('New Record'));
		    $this->_helper->redirector('authorizegroup', 'customer', 'account');
		}
		$table_name = 'productgroupheader';
		$result = $this->SFA_Comman->executequery('CALL sp_get_account_customer_addauthorizegroup(?)',$table_name,'');
		$this->view->item_group = $result[0];
		$this->view->formdata['txtgrp_num']= ($result[1][0]['Auto_increment'] == '') ? '1' : $result[1][0]['Auto_increment'];
	    }	
    }
    
    /**
    * @name       authorizegroupitemgridAction
    * @since      22-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for discount item grid
    */
    public function authorizegroupitemgridAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		$this->view->formdata = $formdata = $this->_request->getPost();
	
		$item_code	= $this->translate->_('Item Code');
		$desc		= $this->translate->_('Description');
		
		// For Alternate Code Status.
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
		
    
        $columns_array = array('im.actualitemcode','im.itemshortdescription AS itemshortdescription','CONCAT(primary_key,"_",pgh.groupnumber) AS edit_del_primary_id');        
        $columns_show =  array($this->translate->_('Item Code'),$this->translate->_('Item Description'));
        
        if($this->css == 'ar_') {
			$columns_array[1]	= 'im.arbitemshortdescription AS itemshortdescription';
		}
		
		if($altcode_status)
        {
            $columns_array[0] = 'im.alternatecode';
        }
		
		
		//To get value of group number and listed out inner grid.
		$ex_param = "";
		if(isset($params["key"]) && $params["key"]>0){
			$ex_param = "/key/".$params["key"];
		}
		
		if(isset($formdata["hdnid"]) && $formdata["hdnid"]>0){
			$ex_param = "/key/".$formdata["hdnid"];
			$params['key'] = $formdata["hdnid"];
		}
	
		// DELETE THE RECORD
		if($params["delete"]=="yes") {
            
			$paramarry = array();
            $ids = explode('_',$params['id']);
			$paramarry[1] = $ids[0];
            
			$r_delete = $this->SFA_Comman->executequery('CALL sp_delete_promotions_customer_authorizegroupitemgrid(?)',$paramarry,'');
            
            if(!$params['key'])
            {
                $params['key'] = $ids[1];
            }
            
			SFA_Message::setMsg($this->translate->_('Delete Record'));
		}
        // DELETE THE RECORD
		if($params["deleteall"]=="yes") {
            
			$paramarry = array();            
			#$paramarry[1] = substr_replace($params['hdndeleteall'],'',-1);
			$paramarry[1] = $params['hdngroupno'].'_$';
            
			$r_delete = $this->SFA_Comman->executequery('CALL sp_delete_promotions_customer_authorizegroupitemgrid(?)',$paramarry,'');
            
            if(!$params['key']) {
                $params['key'] = $params['hdngroupno'];
            }            
			SFA_Message::setMsg($this->translate->_('Delete Record'));
		}
		if($formdata["add"]=="yes") {
            
			if(!empty($formdata['txtgrp_num']) && !empty($formdata['txtgrp_desc']) && !empty($formdata['ddlitem_code'])){				
				$param_array = array();
				$param_array[1] = trim($formdata['txtgrp_num']); 	//groupnumber
				$param_array[2] = trim($formdata['txtgrp_desc']);	//groupdescription
				$param_array[3] = trim($formdata['txtgrp_arb']);	//arbgroupdescription
				$param_array[4] = implode(',',$formdata['ddlitem_code']);	//itemcode - productgroupdetail
				$param_array[5] = 3;					            //grouptype
				$param_array[6] = $this->currentUser->username;		//created
				$param_array[7] = '0';					            //promopcprice
				$param_array[8] = '0';					            //promocaseprice
				$param_array[9] = count($formdata['ddlitem_code']);	//itemcount
                
				$returnval = $this->SFA_Comman->executequery('CALL sp_add_account_customer_addauthorizegroup(?)',$param_array,'');
				$last_id = $returnval[0][0]['var_groupnumber'];
                
				if($last_id['duplicate'] == 'duplicate'){
					SFA_Message::setErrorMsg($this->translate->_('Item Code is already Exist.'));		
				}elseif(isset($last_id) && $last_id>0) {
					SFA_Message::setMsg($this->translate->_('New Record'));
                    echo '<script type="text/javascript">  $("#hdngroupno").val('.$last_id.');
                                    $("#hdnid").val('.$last_id.');
                          </script>';
                          
                    $params['key'] = $last_id;
				}
			}
		}	
		
		$pagingparams = array(
					 "show_grid_heading" => false,
					 "grid_heading_message" => $this->translate->_('Overview'),
					 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
					 "show_searchbox" => false,
					 "show_selectbox" => false,
					 "show_editlink" => false,
					 "show_deletelink" => true,
					 "deletelink" => array("/account/index/deletejourneyplan/id/#pattern#/delete/yes".$ex_param,"#pattern#"),
					 "currentlink" => array("/account/customer/authorizegroupitemgrid".$ex_param),
					 "deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
					 "show_deleteall" => false,
					 "primaryid" => "primary_key",
					 "fetch_columns_inquery" => $columns_array,
					 "show_columns" => $columns_show,			     
					 "nodata_message" => $this->translate->_('No Record(s) Found')
					 );
        
        if(!$this->checkaccess("delete"))
        {
            $pagingparams["show_deletelink"] = false;
        }
		// WHEN GRID IS IN EDIT MODE
		//if($params["edit"]=="yes"){
		//
		//    $pagingparams["editmode"] = true;
		//    $pagingparams["editmodeid"] = $params["id"];
		//    $pagingparams["editmodevalue"] = "id";  // put table's prymary key here
		//}
	
		$pagingshow = new SFA_Ajaxpaging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		$param_array = array();
		$param_array[1] = '';
		$param_array[2] = $get_return_vals['order_columns_name'];
		$param_array[3] = $get_return_vals['order_type'];
		$param_array[4] = $get_return_vals['offset'];
		$param_array[5] = (int)$get_return_vals['show_records_per_page'];
		$param_array[6] = implode(", ",$columns_array);
		$param_array[7] = ' AND pgh.grouptype = 3 AND pgh.groupnumber ='.$params['key'].' ';
	
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_account_customer_addauthorizegroupgrid(?)',$param_array,'');
		$data_arr["count"] = $result[0][0]['counter'];
		$data_arr["data"][0] = $result[1];
	
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
		$this->render("ajaxgrid");
    }
	/**
    * @name       getitemlistfromitemgroup
    * @since      22-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for getitemlist from item group code
    */
    public function getitemlistfromitemgroupAction()
    {
		$params = $this->getRequest()->getParams();
		
		$result = $this->SFA_Comman->executequery('CALL sp_combo_itemmaster_itemgroup(?)',$params['grpcode'],'');	
		echo Zend_Json_Encoder::encode($result);
		exit;
	}
  /**
    * @name       getitemlistfromitemgroupbyitemtype
    * @since      22-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for getitemlist from item group code
    */
    public function getitemlistfromitemgroupbyitemtypeAction()
    {
		$params = $this->getRequest()->getParams();
		$argument = array();
		$argument[1] = $params['grpcode'];
		$argument[2] = '4'; // for item type 'Competitor Item'
		//var_dump($argument);die;
		$result = $this->SFA_Comman->executequery('CALL sp_combo_itemmaster_itemgroup_bytype(?)',$argument,'');
		echo Zend_Json::encode($result);
		exit;
    }
	/**
    * @name       getcustomercode
    * @since      22-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    * This action is use for getcustomercode from item group code
    */
    public function getcustomercodeAction()
    {
		$params = $this->getRequest()->getParams();
		$result = $this->SFA_Comman->executequery('CALL sp_get_customercode(?)',$params['route_code'],'');
		echo $result[0][0]['custcode'];
		exit;
	}
	/**
    * @name       itemmust
    * @since      17-10-2013
    * @version    Release: 1
    * @author     HD <Hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for display item must list
    */
    public function itemmustAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

        if($formdata["hdDelete"]==1)
        {
            $ids = implode(',',$formdata['chk']);
            $param_array 	= array();
            $param_array[1]	= $ids;
            $param_array[2]	= $this->currentUser->username;
            
            $result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_customer_itemmust(?)',$param_array,'');
            
            
            if($result[0][0]['deleted_id'] =='')
            {
                $ids		= explode(',',$ids);
                $checked 	= $ids;
                
                SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
            }
            else
            {
                $deleted_id 	= explode(',',$result[0][0]['deleted_id']);
                $ids		= explode(',',$ids);
                $checked 	= array_diff($ids,$deleted_id);
                
                if(count($ids) != count($deleted_id)){
                    SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
                }
            
                SFA_Message::setMsg($this->translate->_('Delete Record'));
            }
        }
    
        $this->view->title = $this->translate->_('Item Must List');
    
        $cols_array     = array('itemmustcode','itemmustdescription');
        $columns_show   = array($this->translate->_('Itemmust Code'),$this->translate->_('Item Must Description'));
        
        if($this->css == 'ar_') {
			$cols_array[1]	= 'arbitemmustdescription';
		}
    
        $pagingparams = array(
                    "show_grid_heading" => true,
                    "grid_heading_message" => $this->translate->_('Overview'),
                    "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
                    "show_searchbox" => true,
                    "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
                    "pagename" => $this->translate->_('Item Must List'),
                    "show_selectbox" => true,
                    "show_editlink" => true,
                    "show_deletelink" => false,
                    "selected_list" => $checked,
                    "deletelink" => array("/account/customer/itemmust/id/#pattern#/delete/yes/","#pattern#"),
                    "show_deleteall" => false,
                    "primaryid" => "itemmustcode",
                    "editlink" => array("/account/customer/additemmust/id/#pattern#","#pattern#"),
                    "fetch_columns_inquery" => $cols_array,
                    "show_columns" => $columns_show,
                    "nodata_message" => $this->translate->_('No Record(s) Found')
                    );
        
        if(!$this->checkaccess("update"))
        {
            $pagingparams["show_editlink"] = false;
        }
        
        $pagingshow = new SFA_Paging($pagingparams);
        // call common function of grid class
        $get_return_vals = $pagingshow->commnfunc();
		
        $param_array    = array();
        $param_array[1] = '';
        $param_array[2] = $get_return_vals['order_columns_name'];
        $param_array[3] = $get_return_vals['order_type'];
        $param_array[4] = $get_return_vals['offset'];
        $param_array[5] = (int)$get_return_vals['show_records_per_page'];
        $param_array[6] = implode(", ",$pagingparams["fetch_columns_inquery"]);
        $param_array[7] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';        
        $downloadCSV 	= (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];
        
        // Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
        $printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];	
        // called stored procedure for counter
        $result = $this->SFA_Comman->executequery('CALL sp_get_account_customer_itemmust(?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
        
        $data_arr["count"] = $result[0][0]['counter'];
        $data_arr["data"][0] = $result[1];
        
        // pass the data in summary_showdatagrid() function & create a final variable for view
        $this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
    
        $this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");

    }
	/**
    * @name       additemmustAction
    * @since      07-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for add Item Must list
    */
    public function additemmustAction()
    {
        $this->view->params 	= $params 	= $this->getRequest()->getParams();
        $this->view->formdata 	= $formdata = $this->_request->getPost();
	

        if(isset($formdata["hdnid"]) && $formdata["hdnid"]>0)
            $ex_param = "/key/".$params["hdnid"];
            
        if(isset($params["id"]) && $params["id"]>0)
            $ex_param = "/key/".$params["id"];
        //echo $ex_param;exit;
        $this->view->itemgrid  = $this->view->BaseUrl("/account/customer/itemmustitemgrid".$ex_param);
        
        if($params['id'] > 0 || $formdata['hdnid']){
			
            //Following code is for Edit
            if(!empty($formdata['txt_desc'])){
                $param_array = array();
                $param_array[1] = trim($formdata["hdnid"]); 		//groupnumber
                $param_array[2] = trim($formdata['txt_desc']);	//groupdescription
                $param_array[3] = trim($formdata['txtdesc_arb']);	//arbgroupdescription
                $param_array[4] = $this->currentUser->username;		//modified
                
                $r_update = $this->SFA_Comman->executequery('CALL sp_edit_account_customer_additemmust(?)',$param_array,'');
                SFA_Message::setMsg($this->translate->_('Update Record'));
                $this->_helper->redirector('itemmust', 'customer', 'account');
            }
            $result  		= $this->SFA_Comman->executequery('CALL sp_get_account_customer_additemmust(?)',$params['id'],'');
            $res['txtitemmust_id'] 	= $result[1][0]['itemmustcode'];
            $res['txt_desc'] 		= $result[1][0]['itemmustdescription'];
            $res['txtdesc_arb'] 	= $result[1][0]['arbitemmustdescription'];
    
            $this->view->formdata = $res;
            $this->view->item_group = $result[0];    
	    } else {		
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_customer_additemmust(?)',"0",'');			
			$this->view->item_group = $result[0];
			$this->view->formdata['txtitemmust_id']= ($result[1][0]['Auto_increment'] == '') ? '1' : $result[1][0]['Auto_increment'];
	    }
    }
	/**
    * @name       itemmustitemgridAction
    * @since      22-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for discount item grid
    */
    public function itemmustitemgridAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		$this->view->formdata = $formdata = $this->_request->getPost();
	
		$item_code	= $this->translate->_('Item Code');
		$desc		= $this->translate->_('Description');
		$status		= $this->translate->_('Show');
		
		// For Alternate Code Status.
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
		
    
        $columns_array = array('im.actualitemcode','im.itemshortdescription AS itemshortdescription','CONCAT(primary_key,"_",ith.itemmustcode) AS edit_del_primary_id','itd.active');        
        $columns_show =  array($this->translate->_('Item Code'),$this->translate->_('Item Description'),$status);
        
        if($this->css == 'ar_') {
			$columns_array[1]	= 'im.arbitemshortdescription AS itemshortdescription';
		}
		
		if($altcode_status)
        {
            $columns_array[0] = 'im.alternatecode';
        }
		
		$outlet_dd[0] = '0 - No';
		$outlet_dd[1] = '1 - Yes';
		$status_key = 'active';
		
		$mastervalues = array($status_key=>$outlet_dd);
		//To get value of group number and listed out inner grid.
		$ex_param = "";
		if(isset($params["key"]) && $params["key"]>0){
			$ex_param = "/key/".$params["key"];
		}
		
		if(isset($formdata["hdnid"]) && $formdata["hdnid"]>0){
			$ex_param = "/key/".$formdata["hdnid"];
			$params['key'] = $formdata["hdnid"];
		}
	
		// DELETE THE RECORD
		if($params["delete"]=="yes") {
            
			$paramarry = array();
            $ids = explode('_',$params['id']);
			$paramarry[1] = $ids[0];
            
			$r_delete = $this->SFA_Comman->executequery('CALL sp_delete_account_customer_itemmustgrid(?)',$paramarry,'');
            
            if(!$params['key'])
            {
                $params['key'] = $ids[1];
            }
            
			SFA_Message::setMsg($this->translate->_('Delete Record'));
		}
        // DELETE THE RECORD
		if($params["deleteall"]=="yes") {
            
			$paramarry = array();
			$paramarry[1] = $params['hdnitemmustno'].'_$';
            
			$r_delete = $this->SFA_Comman->executequery('CALL sp_delete_account_customer_itemmustgrid(?)',$paramarry,'');
            
            if(!$params['key']) {
                $params['key'] = $params['hdnitemmustno'];
            }            
			SFA_Message::setMsg($this->translate->_('Delete Record'));
		}
		// UPDATE THE RECORD
			if($params["update"]=="yes")
			{		
				$par_id = explode('_',$params['id']);
				$updateData["1"] =$par_id[0];
				$updateData["2"] =$params['active'];				
				$r_edit = $this->SFA_Comman->executequery('CALL sp_edit_account_customer_additemmust_grid(?)',$updateData,'');
				SFA_Message::setMsg($this->translate->_('Update Record'));
				
			}
		
		
		if($formdata["add"]=="yes") {
            
			if(!empty($formdata['txtitemmust_id']) && !empty($formdata['txt_desc']) && !empty($formdata['ddlitem_code'])){
				$param_array = array();				
				$param_array[1] = trim($formdata['txtitemmust_id']); 		//groupnumber
				$param_array[2] = trim($formdata['txt_desc']);				//groupdescription
				$param_array[3] = trim($formdata['txtdesc_arb']);			//arbgroupdescription
				$param_array[4] = implode(',',$formdata['ddlitem_code']);	//itemcode - productgroupdetail
				$param_array[5] = $this->currentUser->username;				//created
				$param_array[6] = count($formdata['ddlitem_code']);			//itemcount
                
				$returnval = $this->SFA_Comman->executequery('CALL sp_add_account_customer_additemmust(?)',$param_array,'');
				$last_id = $returnval[0][0]['var_itemmustnumber'];
                
				if($last_id['duplicate'] == 'duplicate'){
					SFA_Message::setErrorMsg($this->translate->_('Item Code is already Exist.'));		
				}elseif(isset($last_id) && $last_id>0) {
					SFA_Message::setMsg($this->translate->_('New Record'));
                    echo '<script type="text/javascript">  $("#hdnitemmustno").val('.$last_id.');
                                    $("#hdnid").val('.$last_id.');
                          </script>';
                          
                    $params['key'] = $last_id;
				}
			}
		}		
		$noeditfield = array('alternatecode','actualitemcode','itemshortdescription');
		$pagingparams = array(
					 "show_grid_heading" => false,
					 "grid_heading_message" => $this->translate->_('Overview'),
					 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
					 "show_searchbox" => false,
					 "show_selectbox" => false,
					 "show_editlink" => true,
					 "mastervalues" => $mastervalues,
					 "show_deletelink" => true,
					 "deletelink" => array("/account/index/deletejourneyplan/id/#pattern#/delete/yes".$ex_param,"#pattern#"),
					 "currentlink" => array("/account/customer/itemmustitemgrid".$ex_param),
					 "deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
					 "editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
					 "noeditfields" => $noeditfield,
					 "show_deleteall" => false,
					 "primaryid" => "primary_key",
					 "fetch_columns_inquery" => $columns_array,
					 "show_columns" => $columns_show,			     
					 "nodata_message" => $this->translate->_('No Record(s) Found')
					 );
        if($params["edit"]=="yes"){
	
			$pagingparams["editmode"] = true;
			$pagingparams["editmodeid"] = $params["id"];
			$pagingparams["editmodevalue"] = "primary_key";  // put table's prymary key here
		}
        if(!$this->checkaccess("delete"))
        {
            $pagingparams["show_deletelink"] = false;
        }		
	
		$pagingshow = new SFA_Ajaxpaging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		$param_array = array();
		$param_array[1] = '';
		$param_array[2] = $get_return_vals['order_columns_name'];
		$param_array[3] = $get_return_vals['order_type'];
		$param_array[4] = $get_return_vals['offset'];
		$param_array[5] = (int)$get_return_vals['show_records_per_page'];
		$param_array[6] = implode(", ",$columns_array);
		$param_array[7] = 'AND itd.itemmustcode ='.$params['key'].' ';

	
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_account_customer_additemmustgrid(?)',$param_array,'');
		$data_arr["count"] = $result[0][0]['counter'];
		$data_arr["data"][0] = $result[1];
	
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
		$this->render("ajaxgrid");
    }
	/**
    * @name       channelAction
    * @since      06-11-2013
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is for display channel
    */
    public function channelAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

        if($formdata["hdDelete"]==1)
        {
            $ids = implode(',',$formdata['chk']);
            $param_array 	= array();
            $param_array[1]	= $ids;
            $param_array[2]	= $this->currentUser->username;
            
            $result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_customer_channel(?)',$param_array,'');
            
            if($result[0][0]['deleted_id'] =='')
            {
            $ids		= explode(',',$ids);
            $checked 	= $ids;
            
            SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
            }
            else
            {
            $deleted_id 	= explode(',',$result[0][0]['deleted_id']);
            $ids		= explode(',',$ids);
            $checked 	= array_diff($ids,$deleted_id);
            
            if(count($ids) != count($deleted_id)){
                SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
            }
            
            SFA_Message::setMsg($this->translate->_('Delete Record'));
            }
        }
    
        $this->view->title	= $this->translate->_('Customer Channel');	
        
        $cols_array 	= array('channelcode','channelname','activestatus');
        $columns_show 	=  array($this->translate->_('Code'),$this->translate->_('Channel Name'),$this->translate->_('Status'));
        
        if($this->css == 'ar_') {
			$cols_array[1]	= 'arbchannelname';
		}
            
        // prepare the configuration for grid
        $pagingparams = array(
                "show_grid_heading" => true,
                "grid_heading_message" => $this->translate->_('Overview'),
                "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
                "show_searchbox" => true,
                "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
                "pagename" => $this->translate->_('Channel'),
                "show_selectbox" => true,
                "show_editlink" => true,
                "selected_list" => $checked,
                "show_deletelink" => false,			
                "show_deleteall" => false,
                "primaryid" => "channelcode",
                "status_cols" => array(
                               array(
                               "cols_name" => "activestatus",
                               "status_change" => array("0"=>"Inactive","1"=>"Active")
                               )
                               ),
                "editlink" => array("/account/customer/addchannel/id/#pattern#/edit/yes/","#pattern#"),
                "nodata_message" => $this->translate->_('No Record(s) Found'),
                "fetch_columns_inquery" => $cols_array,
                "show_columns" => $columns_show
                );
        
        if(!$this->checkaccess("update"))
        {
            $pagingparams["show_editlink"] = false;
        }
        
        // create grid class object
        $pagingshow = new SFA_Paging($pagingparams);
        
        // call common function of grid class
        $get_return_vals = $pagingshow->commnfunc();
        
        //print_r($get_return_vals['where_condition']);
        
        // call the stored procedure for fetch the data
        $param_array    = array();
        $param_array[1] = '1';
        $param_array[2] = '';
        $param_array[3] = $get_return_vals['order_columns_name'];
        $param_array[4] = $get_return_vals['order_type'];
        $param_array[5] = $get_return_vals['offset'];
        $param_array[6] = (int)$get_return_vals['show_records_per_page'];
        $param_array[7] = implode(", ",$pagingparams["fetch_columns_inquery"]);
        $param_array[8] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
        
        $downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];
        
        // Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
        $printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
        // called stored procedure for counter
        $result 	= $this->SFA_Comman->executequery('CALL sp_get_account_customer_channel(?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
    
        $data_arr["count"] 		= $result[0][0]['counter'];	
        $data_arr["data"][0] 	= $result[1];
        
        // pass the data in summary_showdatagrid() function & create a final variable for view
        $this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
        
        $this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addchannelAction
    * @since      06-11-2013
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is for add channel
    */
    public function addchannelAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

		$this->view->css 		= $this->translate->_('CSS');
		$this->view->select 	= $this->translate->_('Select');
		$this->view->missonefld	= $this->translate->_('Missed One Field');
		$this->view->youmissed	= $this->translate->_('You Missed');
		$this->view->highlated	= $this->translate->_('Fields. They have been highlighted.');
		
		if($formdata['txtcode'] && $formdata['txtname'])
		{
			if($formdata['hdnid'] > 0)
			{	    
				$param_array = array();
				$param_array[1] = trim($formdata['txtname']); 		//categoryname
				$param_array[2] = trim($formdata['txtname_arb']);	//arbcategoryname
				$param_array[3] = trim($formdata['ddlstatus']);		//activestatus
				$param_array[4] = $this->currentUser->username;		//Modified
				$param_array[5] = $formdata['hdnid'];				//categoryid
				$param_array[6] = $formdata['txtaltcode'];			//alternatecode
				
				$last_id = $this->SFA_Comman->executequery('CALL sp_edit_account_customer_addchannel(?)',$param_array,'');
				
				SFA_Message::setMsg($this->translate->_('Update Record'));
			}
			else
			{
				$param_array = array();
				$param_array[1] = trim($formdata['txtname']); 		//categoryname
				$param_array[2] = trim($formdata['txtname_arb']);	//arbcategoryname
				$param_array[3] = trim($formdata['ddlstatus']);		//activestatus
				$param_array[4] = $this->currentUser->username;		//created		
				$param_array[5] = $formdata['txtaltcode'];			//alternatecode
				
				$last_id = $this->SFA_Comman->executequery('CALL sp_add_account_customer_addchannel(?)',$param_array,'');
				
				SFA_Message::setMsg($this->translate->_('New Record'));
			}
		   $this->_helper->redirector('channel', 'customer', 'account');
		}
		elseif($params['id'] > 0)
		{
			$result  		= $this->SFA_Comman->executequery('CALL sp_get_account_customer_addchannel(?)',$params['id'],'');
			$res['txtcode'] 	= $result[0][0]['channelcode'];
			$res['txtname'] 	= $result[0][0]['channelname'];
			$res['txtname_arb'] = $result[0][0]['arbchannelname'];
			$res['ddlstatus'] 	= $result[0][0]['activestatus'];
			$res['createddate']	= date('d-m-Y',strtotime($result[0][0]['cdat']));
			$res['txtaltcode'] 	= $result[0][0]['alternatecode'];
			$this->view->formdata = $res;
		}	
		else
		{
			$table_name = 'channelmaster';
			$code = $this->SFA_Comman->executequery('CALL sp_get_table_last_id(?)',$table_name,'');	    
			$this->view->formdata['txtcode'] = ($code[0][0]['Auto_increment'] == '') ? '1' : $code[0][0]['Auto_increment'];
			
		}
    }
     public function distributioncheckAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

        if($formdata["hdDelete"]==1)
        {
            $ids = implode(',',$formdata['chk']);
            $param_array 	= array();
            $param_array[1]	= $ids;
            $param_array[2]	= $this->currentUser->username;
            
            $result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_customer_distributioncheck(?)',$param_array,'');
            
            
            if($result[0][0]['deleted_id'] =='')
            {
                $ids		= explode(',',$ids);
                $checked 	= $ids;
                
                SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
            }
            else
            {
                $deleted_id 	= explode(',',$result[0][0]['deleted_id']);
                $ids		= explode(',',$ids);
                $checked 	= array_diff($ids,$deleted_id);
                
                if(count($ids) != count($deleted_id)){
                    SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
                }
            
                SFA_Message::setMsg($this->translate->_('Delete Record'));
            }
        }
    
        $this->view->title = $this->translate->_('Distribution Check List');
    
        $cols_array     = array('distributioncode','distributiondescription');
        $columns_show   = array($this->translate->_('Distribution Check Code'),$this->translate->_('Distribution Check Description'));
        
        if($this->css == 'ar_') {
			$cols_array[1]	= 'arbdistributiondescription';
		}
    
        $pagingparams = array(
                    "show_grid_heading" => true,
                    "grid_heading_message" => $this->translate->_('Overview'),
                    "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
                    "show_searchbox" => true,
                    "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
                    "pagename" => $this->translate->_('Distribution Check List'),
                    "show_selectbox" => true,
                    "show_editlink" => true,
                    "show_deletelink" => false,
                    "selected_list" => $checked,
                    "deletelink" => array("/account/customer/distributioncheck/id/#pattern#/delete/yes/","#pattern#"),
                    "show_deleteall" => false,
                    "primaryid" => "distributioncode",
                    "editlink" => array("/account/customer/adddistributioncheck/id/#pattern#","#pattern#"),
                    "fetch_columns_inquery" => $cols_array,
                    "show_columns" => $columns_show,
                    "nodata_message" => $this->translate->_('No Record(s) Found')
                    );
        
        if(!$this->checkaccess("update"))
        {
            $pagingparams["show_editlink"] = false;
        }
        
        $pagingshow = new SFA_Paging($pagingparams);
        // call common function of grid class
        $get_return_vals = $pagingshow->commnfunc();
		
        $param_array    = array();
        $param_array[1] = '';
        $param_array[2] = $get_return_vals['order_columns_name'];
        $param_array[3] = $get_return_vals['order_type'];
        $param_array[4] = $get_return_vals['offset'];
        $param_array[5] = (int)$get_return_vals['show_records_per_page'];
        $param_array[6] = implode(", ",$pagingparams["fetch_columns_inquery"]);
        $param_array[7] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';        
        $downloadCSV 	= (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];
        
        // Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
        $printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];	
        // called stored procedure for counter
        $result = $this->SFA_Comman->executequery('CALL sp_get_account_customer_distributioncheck(?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
        
        $data_arr["count"] = $result[0][0]['counter'];
        $data_arr["data"][0] = $result[1];
        
        // pass the data in summary_showdatagrid() function & create a final variable for view
        $this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
    
        $this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");

    }
     public function adddistributioncheckAction()
    {
        $this->view->params 	= $params 	= $this->getRequest()->getParams();
        $this->view->formdata 	= $formdata = $this->_request->getPost();
	

        if(isset($formdata["hdnid"]) && $formdata["hdnid"]>0)
            $ex_param = "/key/".$params["hdnid"];
            
        if(isset($params["id"]) && $params["id"]>0)
            $ex_param = "/key/".$params["id"];
        //echo $ex_param;exit;
        $this->view->itemgrid  = $this->view->BaseUrl("/account/customer/distributioncheckgrid".$ex_param);
        
        if($params['id'] > 0 || $formdata['hdnid']){
			
            //Following code is for Edit
            if(!empty($formdata['txt_desc'])){
                $param_array = array();
                $param_array[1] = trim($formdata["hdnid"]); 		//groupnumber
                $param_array[2] = trim($formdata['txt_desc']);	//groupdescription
                $param_array[3] = trim($formdata['txtdesc_arb']);	//arbgroupdescription
                $param_array[4] = $this->currentUser->username;		//modified
                
                $r_update = $this->SFA_Comman->executequery('CALL sp_edit_account_customer_adddistributioncheck(?)',$param_array,'');
                SFA_Message::setMsg($this->translate->_('Update Record'));
                $this->_helper->redirector('distributioncheck', 'customer', 'account');
            }
            $result  		= $this->SFA_Comman->executequery('CALL sp_get_account_customer_adddistributioncheck(?)',$params['id'],'');
         
	    $res['txtdistributioncheck_id'] 	= $result[1][0]['distributioncode'];
            $res['txt_desc'] 		= $result[1][0]['distributiondescription'];
            $res['txtdesc_arb'] 	= $result[1][0]['arbdistributiondescription'];
    
            $this->view->formdata = $res;
            $this->view->item_group = $result[0];    
	    } else {		
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_customer_adddistributioncheck(?)',"0",'');			
			$this->view->item_group = $result[0];
			$this->view->formdata['txtdistributioncheck_id']= ($result[1][0]['Auto_increment'] == '') ? '1' : $result[1][0]['Auto_increment'];
	    }
    }
    
    public function distributioncheckgridAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
	$this->view->formdata = $formdata = $this->_request->getPost();
	
	$item_code	= $this->translate->_('Item Code');
	$desc		= $this->translate->_('Description');
	
	// For Alternate Code Status.
	$cpanel				= $this->SFA_Comman->getaltcodestatus();
	$altcode_status		= $cpanel["Use Alternate Code"]['status'];
	
	
	$columns_array = array('im.actualitemcode','im.itemshortdescription AS itemshortdescription','im.unitspercase','cutoff_qty','CONCAT(primary_key,"_",ith.distributioncode) AS edit_del_primary_id','itd.expirycapture','itd.stockcapture');        
	$columns_show =  array($this->translate->_('Item Code'),$this->translate->_('Item Description'),$this->translate->_('UPC'),$this->translate->_('Cut-off Qty (in PCS)'),$this->translate->_('Expiry capture'),$this->translate->_('stock capture'));
	
	if($this->css == 'ar_')
	{
	    $columns_array[1]	= 'im.arbitemshortdescription AS itemshortdescription';
	}
	
	if($altcode_status)
	{
	    $columns_array[0] = 'im.alternatecode';
	}
	
	//
	$outlet_dd[0] = '0 - No';
	$outlet_dd[1] = '1 - Yes';
	
	$expiry_key_ms = "expirycapture";
	$stock_key_ms = "stockcapture";
	$mastervalues = array($expiry_key_ms=>$outlet_dd, $stock_key_ms=>$outlet_dd);
	
	
	//To get value of group number and listed out inner grid.
	$ex_param = "";
	if(isset($params["key"]) && $params["key"]>0)
	{
	    $ex_param = "/key/".$params["key"];
	}
	
	if(isset($formdata["hdnid"]) && $formdata["hdnid"]>0)
	{
	    $ex_param = "/key/".$formdata["hdnid"];
	    $params['key'] = $formdata["hdnid"];
	}
	
	// DELETE THE RECORD
	if($params["delete"]=="yes")
	{
	    $paramarry = array();
	    $ids = explode('_',$params['id']);
	    $paramarry[1] = $ids[0];
	    
	    $r_delete = $this->SFA_Comman->executequery('CALL sp_delete_account_customer_distributioncheckgrid(?)',$paramarry,'');
	    
	    if(!$params['key'])
	    {
		$params['key'] = $ids[1];
	    }
	    
	    SFA_Message::setMsg($this->translate->_('Delete Record'));
	}
	
	// UPDATE THE RECORD
	if($params["update"]=="yes")
	{
	    $updateData["1"] = $params["cutoff_qty"];
	    $updateData["2"] =$params['id'];
	    $updateData["3"] =$params['expirycapture'];
	    $updateData["4"] =$params['stockcapture'];
	    
	    //SFA_Comman::pre($updateData);
	    // call sp for edit currencydetail
	    $r_edit = $this->SFA_Comman->executequery('CALL sp_edit_account_customer_distributioncheckgrid(?)',$updateData,'');
	    
	    
	    echo '<script type="text/javascript">$("#hdnrange_high").val(1);</script>';
	    SFA_Message::setMsg($this->translate->_('Update Record'));
	}
	
	// DELETE THE RECORD
	if($params["deleteall"]=="yes")
	{
	    $paramarry = array();
	    $paramarry[1] = $params['hdnitemmustno'].'_$';
	    
	    $r_delete = $this->SFA_Comman->executequery('CALL sp_delete_account_customer_distributioncheckgrid(?)',$paramarry,'');
	    
	    if(!$params['key'])
	    {
		$params['key'] = $params['hdnitemmustno'];
	    }            
	    SFA_Message::setMsg($this->translate->_('Delete Record'));
	}
	if($formdata["add"]=="yes")
	{
	    if(!empty($formdata['txtdistributioncheck_id']) && !empty($formdata['txt_desc']) && !empty($formdata['ddlitem_code']))
	    {
		$param_array = array();				
		$param_array[1] = trim($formdata['txtdistributioncheck_id']); 		//groupnumber
		$param_array[2] = trim($formdata['txt_desc']);				//groupdescription
		$param_array[3] = trim($formdata['txtdesc_arb']);			//arbgroupdescription
		$param_array[4] = implode(',',$formdata['ddlitem_code']);	//itemcode - productgroupdetail
		$param_array[5] = $this->currentUser->username;				//created
		$param_array[6] = count($formdata['ddlitem_code']);			//itemcount
		
		$returnval = $this->SFA_Comman->executequery('CALL sp_add_account_customer_adddistributioncheck(?)',$param_array,'');
		
		$last_id = $returnval[0][0]['var_itemmustnumber'];
		
		if($last_id['duplicate'] == 'duplicate')
		{
		    SFA_Message::setErrorMsg($this->translate->_('Item Code is already Exist.'));		
		}
		elseif(isset($last_id) && $last_id>0)
		{
		    SFA_Message::setMsg($this->translate->_('New Record'));
		    echo '<script type="text/javascript">  $("#hdnitemmustno").val('.$last_id.');
		    $("#hdnid").val('.$last_id.');
		    </script>';
		    
		    $params['key'] = $last_id;
		}
	    }
	}
	
	
	$pagingparams = array(
		"show_grid_heading" => false,
		"grid_heading_message" => $this->translate->_('Overview'),
		"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
		"show_searchbox" => false,
		"show_selectbox" => false,
		"show_editlink" => true,
		"show_deletelink" => true,
		"mastervalues" => $mastervalues,
		"deletelink" => array("/account/index/deletejourneyplan/id/#pattern#/delete/yes".$ex_param,"#pattern#"),
		"currentlink" => array("/account/customer/distributioncheckgrid".$ex_param),
		"deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
		"editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
		"noeditfields" => array('actualitemcode','alternatecode','itemshortdescription'),
		"show_deleteall" => false,
		"primaryid" => "primary_key",
		"fetch_columns_inquery" => $columns_array,
		"show_columns" => $columns_show,			     
		"nodata_message" => $this->translate->_('No Record(s) Found')
		);
	
	if(!$this->checkaccess("delete"))
	{
	    $pagingparams["show_deletelink"] = false;
	}
	
	// WHEN GRID IS IN EDIT MODE
	if($params["edit"]=="yes")
	{
	    $pagingparams["editmode"] = true;
	    $pagingparams["editmodeid"] = $params["id"];
	    $pagingparams["editmodevalue"] = "primary_key";  // put table's prymary key here
	}
	$pagingshow = new SFA_Ajaxpaging($pagingparams);
	
	// call common function of grid class
	$get_return_vals = $pagingshow->commnfunc();
	
	$param_array = array();
	$param_array[1] = '';
	$param_array[2] = $get_return_vals['order_columns_name'];
	$param_array[3] = $get_return_vals['order_type'];
	$param_array[4] = $get_return_vals['offset'];
	$param_array[5] = (int)$get_return_vals['show_records_per_page'];
	$param_array[6] = implode(", ",$columns_array);
	$param_array[7] = 'AND itd.distributioncode ='.$params['key'].' ';
	
	
	// called stored procedure for counter
	$result = $this->SFA_Comman->executequery('CALL sp_get_account_customer_adddistributioncheckgrid(?)',$param_array,'');
	$data_arr["count"] = $result[0][0]['counter'];
	$data_arr["data"][0] = $result[1];
	
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
	$this->render("ajaxgrid");
    }
    
    public function pricesurveyAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

        if($formdata["hdDelete"]==1)
        {
            $ids = implode(',',$formdata['chk']);
            $param_array 	= array();
            $param_array[1]	= $ids;
            $param_array[2]	= $this->currentUser->username;
            
            $result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_customer_pricesurvey(?)',$param_array,'');
            
            
            if($result[0][0]['deleted_id'] =='')
            {
                $ids		= explode(',',$ids);
                $checked 	= $ids;
                
                SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
            }
            else
            {
                $deleted_id 	= explode(',',$result[0][0]['deleted_id']);
                $ids		= explode(',',$ids);
                $checked 	= array_diff($ids,$deleted_id);
                
                if(count($ids) != count($deleted_id)){
                    SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
                }
            
                SFA_Message::setMsg($this->translate->_('Delete Record'));
            }
        }
    
        $this->view->title = $this->translate->_('Price Survey List');
    
        $cols_array     = array('pricesurveycode','pricesurveydescription');
        $columns_show   = array($this->translate->_('Price Survey Code'),$this->translate->_('Price Survey Description'));
        
        if($this->css == 'ar_') {
			$cols_array[1]	= 'arbpricesurveydescription';
		}
    
        $pagingparams = array(
                    "show_grid_heading" => true,
                    "grid_heading_message" => $this->translate->_('Overview'),
                    "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
                    "show_searchbox" => true,
                    "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
                    "pagename" => $this->translate->_('Price Survey List'),
                    "show_selectbox" => true,
                    "show_editlink" => true,
                    "show_deletelink" => false,
                    "selected_list" => $checked,
                    "deletelink" => array("/account/customer/pricesurvey/id/#pattern#/delete/yes/","#pattern#"),
                    "show_deleteall" => false,
                    "primaryid" => "pricesurveycode",
                    "editlink" => array("/account/customer/addpricesurvey/id/#pattern#","#pattern#"),
                    "fetch_columns_inquery" => $cols_array,
                    "show_columns" => $columns_show,
                    "nodata_message" => $this->translate->_('No Record(s) Found')
                    );
        
        if(!$this->checkaccess("update"))
        {
            $pagingparams["show_editlink"] = false;
        }
        
        $pagingshow = new SFA_Paging($pagingparams);
        // call common function of grid class
        $get_return_vals = $pagingshow->commnfunc();
		
        $param_array    = array();
        $param_array[1] = '';
        $param_array[2] = $get_return_vals['order_columns_name'];
        $param_array[3] = $get_return_vals['order_type'];
        $param_array[4] = $get_return_vals['offset'];
        $param_array[5] = (int)$get_return_vals['show_records_per_page'];
        $param_array[6] = implode(", ",$pagingparams["fetch_columns_inquery"]);
        $param_array[7] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';        
        $downloadCSV 	= (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];
        
        // Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
        $printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];	
        // called stored procedure for counter
        $result = $this->SFA_Comman->executequery('CALL sp_get_account_customer_pricesurvey(?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
        
        $data_arr["count"] = $result[0][0]['counter'];
        $data_arr["data"][0] = $result[1];
        
        // pass the data in summary_showdatagrid() function & create a final variable for view
        $this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
    
        $this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");

    }
    
    public function addpricesurveyAction()
    {
	$this->view->params 	= $params 	= $this->getRequest()->getParams();
	$this->view->formdata 	= $formdata = $this->_request->getPost();
	
	
	if(isset($formdata["hdnid"]) && $formdata["hdnid"]>0)
	    $ex_param = "/key/".$params["hdnid"];
	
	if(isset($params["id"]) && $params["id"]>0)
	    $ex_param = "/key/".$params["id"];
	//echo $ex_param;exit;
	$this->view->itemgrid  = $this->view->BaseUrl("/account/customer/pricesurveygrid".$ex_param);
	
	if($params['id'] > 0 || $formdata['hdnid'])
	{
	    //Following code is for Edit
	    if(!empty($formdata['txt_desc']))
	    {
		$param_array = array();
		$param_array[1] = trim($formdata["hdnid"]); 		//groupnumber
		$param_array[2] = trim($formdata['txt_desc']);	//groupdescription
		$param_array[3] = trim($formdata['txtdesc_arb']);	//arbgroupdescription
		$param_array[4] = $this->currentUser->username;		//modified
		
		$r_update = $this->SFA_Comman->executequery('CALL sp_edit_account_customer_addpricesurvey(?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('Update Record'));
		$this->_helper->redirector('pricesurvey', 'customer', 'account');
	    }
	    $result  		= $this->SFA_Comman->executequery('CALL sp_get_account_customer_addpricesurvey(?)',$params['id'],'');
	    
	    $res['txtpricesurvey_id'] 	= $result[1][0]['pricesurveycode'];
	    $res['txt_desc'] 		= $result[1][0]['pricesurveydescription'];
	    $res['txtdesc_arb'] 	= $result[1][0]['arbpricesurveydescription'];
	    
	    $this->view->formdata = $res;
	    $this->view->item_group = $result[0];
	}
	else
	{		
	    $result = $this->SFA_Comman->executequery('CALL sp_get_account_customer_addpricesurvey(?)',"0",'');			
	    $this->view->item_group = $result[0];
	    $this->view->formdata['txtpricesurvey_id']= ($result[1][0]['Auto_increment'] == '') ? '1' : $result[1][0]['Auto_increment'];
	}
    }

    public function pricesurveygridAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
	$this->view->formdata = $formdata = $this->_request->getPost();
	
	$item_code	= $this->translate->_('Item Code');
	$desc		= $this->translate->_('Description');
	
	// For Alternate Code Status.
	$cpanel				= $this->SFA_Comman->getaltcodestatus();
	$altcode_status		= $cpanel["Use Alternate Code"]['status'];
	
	
	//$columns_array = array('im.actualitemcode','im.itemshortdescription AS itemshortdescription','im.unitspercase','cutoff_qty','CONCAT(primary_key,"_",ith.pricesurveycode) AS edit_del_primary_id','itd.expirycapture','itd.stockcapture');        
	//$columns_show =  array($this->translate->_('Item Code'),$this->translate->_('Item Description'),$this->translate->_('UPC'),$this->translate->_('Cut-off Qty (in Cases)'),$this->translate->_('Expiry capture'),$this->translate->_('stock capture'));
	
	$columns_array = array('im.actualitemcode','im.itemshortdescription AS itemshortdescription','im.unitspercase','CONCAT(primary_key,"_",ith.pricesurveycode) AS edit_del_primary_id','itd.stockcapture');        
	$columns_show =  array($this->translate->_('Item Code'),$this->translate->_('Item Description'),$this->translate->_('UPC'),$this->translate->_('Price capture'));
	
	if($this->css == 'ar_')
	{
	    $columns_array[1]	= 'im.arbitemshortdescription AS itemshortdescription';
	}
	
	if($altcode_status)
	{
	    $columns_array[0] = 'im.alternatecode';
	}
	
	//
	$outlet_dd[0] = '0 - No';
	$outlet_dd[1] = '1 - Yes';
	
	$expiry_key_ms = "expirycapture";
	$stock_key_ms = "stockcapture";
	$mastervalues = array($expiry_key_ms=>$outlet_dd, $stock_key_ms=>$outlet_dd);
	
	
	//To get value of group number and listed out inner grid.
	$ex_param = "";
	if(isset($params["key"]) && $params["key"]>0)
	{
	    $ex_param = "/key/".$params["key"];
	}
	
	if(isset($formdata["hdnid"]) && $formdata["hdnid"]>0)
	{
	    $ex_param = "/key/".$formdata["hdnid"];
	    $params['key'] = $formdata["hdnid"];
	}
	
	// DELETE THE RECORD
	if($params["delete"]=="yes")
	{
	    $paramarry = array();
	    $ids = explode('_',$params['id']);
	    $paramarry[1] = $ids[0];
	    
	    $r_delete = $this->SFA_Comman->executequery('CALL sp_delete_account_customer_pricesurveygrid(?)',$paramarry,'');
	    
	    if(!$params['key'])
	    {
		$params['key'] = $ids[1];
	    }
	    
	    SFA_Message::setMsg($this->translate->_('Delete Record'));
	}
	
	// UPDATE THE RECORD
	if($params["update"]=="yes")
	{
	    $updateData["1"] = $params["cutoff_qty"];
	    $updateData["2"] =$params['id'];
	    $updateData["3"] =$params['expirycapture'];
	    $updateData["4"] =$params['stockcapture'];
	    
	    //SFA_Comman::pre($updateData);
	    // call sp for edit currencydetail
	    $r_edit = $this->SFA_Comman->executequery('CALL sp_edit_account_customer_pricesurveygrid(?)',$updateData,'');
	    
	    
	    echo '<script type="text/javascript">$("#hdnrange_high").val(1);</script>';
	    SFA_Message::setMsg($this->translate->_('Update Record'));
	}
	
	// DELETE THE RECORD
	if($params["deleteall"]=="yes")
	{
	    $paramarry = array();
	    $paramarry[1] = $params['hdnitemmustno'].'_$';
	    
	    $r_delete = $this->SFA_Comman->executequery('CALL sp_delete_account_customer_pricesurveygrid(?)',$paramarry,'');
	    
	    if(!$params['key'])
	    {
		$params['key'] = $params['hdnitemmustno'];
	    }            
	    SFA_Message::setMsg($this->translate->_('Delete Record'));
	}
	if($formdata["add"]=="yes")
	{
	    if(!empty($formdata['txtpricesurvey_id']) && !empty($formdata['txt_desc']) && !empty($formdata['ddlitem_code']))
	    {
		$param_array = array();				
		$param_array[1] = trim($formdata['txtpricesurvey_id']); 		//groupnumber
		$param_array[2] = trim($formdata['txt_desc']);				//groupdescription
		$param_array[3] = trim($formdata['txtdesc_arb']);			//arbgroupdescription
		$param_array[4] = implode(',',$formdata['ddlitem_code']);	//itemcode - productgroupdetail
		$param_array[5] = $this->currentUser->username;				//created
		$param_array[6] = count($formdata['ddlitem_code']);			//itemcount
		
		$returnval = $this->SFA_Comman->executequery('CALL sp_add_account_customer_addpricesurvey(?)',$param_array,'');
		
		$last_id = $returnval[0][0]['var_itemmustnumber'];
		
		if($last_id['duplicate'] == 'duplicate')
		{
		    SFA_Message::setErrorMsg($this->translate->_('Item Code is already Exist.'));		
		}
		elseif(isset($last_id) && $last_id>0)
		{
		    SFA_Message::setMsg($this->translate->_('New Record'));
		    echo '<script type="text/javascript">  $("#hdnitemmustno").val('.$last_id.');
		    $("#hdnid").val('.$last_id.');
		    </script>';
		    
		    $params['key'] = $last_id;
		}
	    }
	}
	
	
	$pagingparams = array(
		"show_grid_heading" => false,
		"grid_heading_message" => $this->translate->_('Overview'),
		"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
		"show_searchbox" => false,
		"show_selectbox" => false,
		"show_editlink" => true,
		"show_deletelink" => true,
		"mastervalues" => $mastervalues,
		"deletelink" => array("/account/index/deletejourneyplan/id/#pattern#/delete/yes".$ex_param,"#pattern#"),
		"currentlink" => array("/account/customer/pricesurveygrid".$ex_param),
		"deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
		"editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
		"noeditfields" => array('actualitemcode','alternatecode','itemshortdescription'),
		"show_deleteall" => false,
		"primaryid" => "primary_key",
		"fetch_columns_inquery" => $columns_array,
		"show_columns" => $columns_show,			     
		"nodata_message" => $this->translate->_('No Record(s) Found')
		);
	
	if(!$this->checkaccess("delete"))
	{
	    $pagingparams["show_deletelink"] = false;
	}
	
	// WHEN GRID IS IN EDIT MODE
	if($params["edit"]=="yes")
	{
	    $pagingparams["editmode"] = true;
	    $pagingparams["editmodeid"] = $params["id"];
	    $pagingparams["editmodevalue"] = "primary_key";  // put table's prymary key here
	}
	$pagingshow = new SFA_Ajaxpaging($pagingparams);
	
	// call common function of grid class
	$get_return_vals = $pagingshow->commnfunc();
	
	$param_array = array();
	$param_array[1] = '';
	$param_array[2] = $get_return_vals['order_columns_name'];
	$param_array[3] = $get_return_vals['order_type'];
	$param_array[4] = $get_return_vals['offset'];
	$param_array[5] = (int)$get_return_vals['show_records_per_page'];
	$param_array[6] = implode(", ",$columns_array);
	$param_array[7] = 'AND itd.pricesurveycode ='.$params['key'].' ';
	
	
	// called stored procedure for counter
	$result = $this->SFA_Comman->executequery('CALL sp_get_account_customer_addpricesurveygrid(?)',$param_array,'');
	$data_arr["count"] = $result[0][0]['counter'];
	$data_arr["data"][0] = $result[1];
	
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
	$this->render("ajaxgrid");
    }
}