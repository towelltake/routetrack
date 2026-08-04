<?php
/**
* @name       TaxController
*
* This controller is added by nilesh gotre on 07Aug2015.
*/
class Account_TaxController extends Account_Library_Controller_Action_Abstract
{

    public $common_model 	= '';
    public $sec_lang 		= '';

  
    public function init()
    {
		$this->translate 	= Zend_Registry::get('Zend_Translate');
		
		$this->currentUser = SFA_Loginauth::getIdentity();
		
		if(!isset($this->currentUser) || empty($this->currentUser))
		{
			SFA_Message::setMsg($this->translate->_('Do Login'));			
			$url = $this->view->baseUrl();
			echo '<script type="text/javascript">window.location="'.$url.'";</script>';
			exit;
		}
        $this->css 				= $this->translate->_('CSS');
		$this->view->css 		= $this->css;
		$this->view->overview	= $this->translate->_('Overview');
		$this->view->details	= $this->translate->_('Details');
		$this->view->required	= $this->translate->_('Required');
		$this->view->colan 		= $this->translate->_('Colan');
	
		$this->common_model			= new SFA_Model_Index();
		$this->SFA_Comman			= new SFA_Comman();		
		$this->view->sec_lang		= $this->SFA_Comman->getsecondlanguage();
		$this->sec_lang 			= $this->view->sec_lang;
		 $this->view->decimalplaces 	= $this->SFA_Comman->getdecimalplaces();
		  $this->decimalplaces 		= $this->view->decimalplaces;
    }
    
    
  
    
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
    
  
    public function indexAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		$string_del   = 'del_';
		$del_status = strpos($formdata["hdDelete"], $string_del);	
		if($formdata["hdDelete"] ==1 || $del_status !==false )
		{
			$param_array 	= array();
			$str = $formdata["hdDelete"];
			$del_id = explode('_', $str);
			if($del_id[1] == "")
			{
				$param_array[1] = implode(',',$formdata['chk']);
			}
		   else{
				$param_array[1] = $del_id[1]; 
		   }
				$param_array[2]	= $this->currentUser->username;	
		
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_tax(?,?)',$param_array,'');
			
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
	
		$this->view->title	= $this->translate->_('Tax');
		
		
		$cols_array 	= array('taxcode','taxdescription');
		$columns_show 	= array($this->translate->_('Code'),$this->translate->_('Description'));
		
		if($this->css == 'ar_') {
			$cols_array[1]	= 'arbtaxdescription';
		}
		
		
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"pagename" => $this->translate->_('Tax'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,				
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"show_selectbox" => true,
				"show_editlink" => true,
				"show_deletelink" => false,
				"selected_list" => $checked,
				"show_deleteall" => false,
				"primaryid" => "taxcode",				
				"editlink" => array("/account/tax/addtax/id/#pattern#/edit/yes/","#pattern#"),
				"nodata_message" => $this->translate->_('No Record(s) Found'),
				"fetch_columns_inquery" => $cols_array,
				"show_columns" => $columns_show
				);

        if(!$this->checkaccess("update"))
        {
            $pagingparams["show_editlink"] = false;
        }
		
 if($this->checkaccess("delete"))
    {
       	//$pagingparams["show_deletelink"] 	= true;
    }
		// create grid class object
		$pagingshow = new SFA_Paging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		
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
		
		$printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
		
		$result = $this->SFA_Comman->executequery('CALL sp_get_account_tax(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
   
   
    public function addtaxAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();


	$tax_type = array();
	$tax_type[0]['id']  = 1;
	$tax_type[0]['val'] = $this->translate->_('Customer');
	$tax_type[1]['id']  = 2;
	$tax_type[1]['val'] = $this->translate->_('Item');
	
	$this->view->tax_type	= $tax_type;
	
	$tax_base = array();
	$tax_base[0]['id']  = 1;
	$tax_base[0]['val'] = $this->translate->_('Price');
	$tax_base[1]['id']  = 2;
	$tax_base[1]['val'] = $this->translate->_('Quantity');
	
	$this->view->tax_base	= $tax_base;
	

	if($formdata['txtcode'] && $formdata['txtdesc'])
	{
			$param_array = array();
		    $param_array[1] = $formdata['txtdesc'];	//description
		    $param_array[2] = $formdata['txtdesc_arb'];	//arbdescription
		    $param_array[3] = $formdata['ddltax_type']; 	//type
		    $param_array[4] = $formdata['taxpercentage'];	//percentage		   
		    $param_array[5] = $formdata['ddltax_base'];	
		    $param_array[6] = $this->currentUser->username;	//created
		
	    if($formdata['hdnid'] > 0)
	    {
		    $param_array[7] = $formdata['hdnid'];			//salesmancode
		    
		    $last_id = $this->SFA_Comman->executequery('CALL sp_edit_account_tax_addtax(?,?,?,?,?,?,?)',$param_array,'');
		    
		    SFA_Message::setMsg($this->translate->_('Update Record'));
		    $this->_helper->redirector('index', 'tax', 'account');
	    }
	    else
	    {
			
			
		    $last_id = $this->SFA_Comman->executequery('CALL sp_add_account_tax(?,?,?,?,?,?,?)',$param_array,'');
		   
		    SFA_Message::setMsg($this->translate->_('New Record'));
		    $this->_helper->redirector('index', 'tax', 'account');
		}
	   
	}
	elseif($params['id'] > 0)
	{
	    $result  				= $this->SFA_Comman->executequery('CALL sp_get_account_tax_addtax(?)',$params['id'],'');
	    $res['txtcode'] 		= $result[0][0]['taxcode'];
	    $res['txtdesc'] 		= $result[0][0]['taxdescription'];
	    $res['txtdesc_arb'] 	= $result[0][0]['arbtaxdescription'];
	    $res['ddltax_type'] 	= $result[0][0]['taxtype'];
	    $res['taxpercentage'] 	= $result[0][0]['taxpercentage'];
	    $res['ddltax_base'] 	= $result[0][0]['taxbase'];	   
	    $res['createddate']		= date('d-m-Y',strtotime($result[0][0]['cdat']));
	    $this->view->formdata	= $res;
	}
	else
	{
		$param_array = array();
		$param_array[1] = $formdata['0'];	//description
	    $result  		= $this->SFA_Comman->executequery('CALL sp_get_account_tax_addtax(?)',$param_array,'');	  
	    $this->view->formdata['txtcode'] 	= ($result[0][0]['Auto_increment'] == '') ? '1' : $result[0][0]['Auto_increment'];
	}
    }
   
   
}