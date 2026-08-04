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
class Admin_SecurityController extends Admin_Library_Controller_Action_Abstract
{
    /**
    * @name       init
    * @since      30-11-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is the default function for all Actions.
    *
    */
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
        $this->view->required	= $this->translate->_('Required');
        $this->css 				= $this->translate->_('CSS');
		$this->view->css 		= $this->css;
        $this->view->overview	= $this->translate->_('Overview');
        $this->view->details	= $this->translate->_('Details');
        $this->view->colan	    = $this->translate->_('Colan');
        $this->common 		    = new SFA_Model_Index();            
        $this->SFA_Comman 		= new SFA_Comman();
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
    
    /**
    * @name       usertype
    * @since      03 Aug, 2012
    * @version    Release: 5
    * @author     MB <mayur.b@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is for display user type
    */
    public function usertypeAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        
        if($formdata["hdDelete"]==1)
        {
            $ids = implode(',',$formdata['chk']);
            $param_array 	= array();
            $param_array[1]	= $ids;
            $param_array[2]	= $this->currentUser->username;
            
            $result = $this->SFA_Comman->executequery('CALL sp_delete_admin_security_addusertype(?,?)',$param_array,'');
            
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
                
                if(count($ids) != count($deleted_id)) {
                    SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
                }
                SFA_Message::setMsg($this->translate->_('Delete Record'));
            }
            
            $this->_helper->redirector('usertype', 'security', 'admin');
        }
        
        $this->view->title	= $this->translate->_('User Type');
        $code			= $this->translate->_('Code');
        $name			= $this->translate->_('Name');
        $status			= $this->translate->_('Status');
        
        $parm_val 		= $this->getRequest()->getParams();
        
        if($parm_val['succ'] == '1' && $parm_val['succ'] != ''){
            $this->view->success	= $this->translate->_('Record Success');
        }
        
        $cols_array 	= array('id','user_type','status');
        $columns_show 	=  array($this->translate->_('Code'),$this->translate->_('Name'),$this->translate->_('Status'));
        
        // prepare the configuration for grid
        $pagingparams = array(
                "show_grid_heading" => true,
                "grid_heading_message" => $this->translate->_('Overview'),
                "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
                "show_searchbox" => true,
                "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
                "pagename" => $this->translate->_('User Type'),
                "show_selectbox" => true,
                "selected_list" => $checked,
                "show_editlink" => true,
                "show_deletelink" => false,
                "show_deleteall" => false,
                "status_cols" => array	(
                                array(
                                "cols_name" => "status",
                                "status_change" => array("0"=>"Inactive","1"=>"Active")
                                )
                            ),
                "primaryid" => "id",
                "editlink" => array("/admin/security/addusertype/id/#pattern#/edit/yes/","#pattern#"),
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
        $result = $this->SFA_Comman->executequery('CALL sp_get_admin_security_usertype(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
    
        $data_arr["count"]	= $result[0][0]['counter'];
        $data_arr["data"][0]	= $result[1];
        
        // pass the data in summary_showdatagrid() function & create a final variable for view
        $this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
        
        $this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");        
    }    
    
    /**
    * @name       addusertypeAction
    * @since      03 Aug, 2012
    * @version    Release: 5
    * @author     MB <mayur.b@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is for add usertype
    */
    public function addusertypeAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();


        // IF EXTRA PARAMS ARE REQUIRED
        $ex_param = "";
        if(isset($params["id"]) && $params["id"]>0)
		$ex_param = "/key/".$params["id"];
        
        $this->view->css 		 = $this->translate->_('CSS');        
        $this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/addusertypegrid".$ex_param);
    
        if(count($formdata) > 0) {
        
            if($formdata['hdnid'] > 0)
            {	    
                $param_array = array();
                $param_array[1] = trim($formdata['txtcode']); 		//Code
                $param_array[2] = trim($formdata['txtname']);	    //Name
                $param_array[3] = $this->currentUser->userid;		//userid
                $param_array[4] = trim($formdata['ddlstatus']);		//status
                $param_array[5]	= $this->currentUser->username;
                
                $last_id = $this->SFA_Comman->executequery('CALL sp_edit_admin_security_addusertype(?,?,?,?,?)',$param_array,'');
                
                SFA_Message::setMsg($this->translate->_('Update Record'));
            }
            else
            {
                $param_array = array();
                $param_array[1] = trim($formdata['txtcode']); 		//Code
                $param_array[2] = trim($formdata['txtname']);	    //Name
                $param_array[3] = $this->currentUser->userid;		//userid
                $param_array[4] = trim($formdata['ddlstatus']);		//status
                $param_array[5]	= $this->currentUser->username;
                
                $last_id = $this->SFA_Comman->executequery('CALL sp_add_admin_security_addusertype(?,?,?,?,?)',$param_array,'');
                
                SFA_Message::setMsg($this->translate->_('New Record'));
            }
            
            $this->_helper->redirector('usertype', 'security', 'admin');
        }
        elseif($params['id'] > 0)
        {
            $result = $this->SFA_Comman->executequery('CALL sp_get_admin_security_addusertype(?)',$params['id'],'');
            
            $res['txtcode']		= $result[0][0]['id'];
            $res['user_type']	= $result[0][0]['user_type'];
            $res['ddlstatus']	= $result[0][0]['status'];
            
            $this->view->formdata 	= $res;
        }
        else
        {
            $result = $this->SFA_Comman->executequery('CALL sp_get_admin_security_addusertype(?)','0','');
            $this->view->formdata['txtcode']	= ($result[0][0]['Auto_increment'] == '') ? '1' : $result[0][0]['Auto_increment'];
        }
    }
    
    /**
    * @name       user
    * @since      28-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is for display user inforamtion
    */
    public function userAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        
        if($formdata["hdDelete"]==1)
        {
            $ids = implode(',',$formdata['chk']);
            $param_array 	= array();
            $param_array[1]	= $ids;
            $param_array[2]	= $this->currentUser->username;
            $param_array[3]	= $this->currentUser->userid;
            
            $result = $this->SFA_Comman->executequery('CALL sp_delete_admin_security_adduser(?,?,?)',$param_array,'');
            
            if($result[0][0]['deleted_id'] =='')
            {
                $ids		= explode(',',$ids);
                $checked 	= $ids;
                
                //SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
                SFA_Message::setErrorMsg($this->translate->_("Can't Delete Log In OR Administrator User."));
            }
            else
            {
                $deleted_id 	= explode(',',$result[0][0]['deleted_id']);
                $ids		= explode(',',$ids);
                $checked 	= array_diff($ids,$deleted_id);
                
                if(count($ids) != count($deleted_id)) {
                    //SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
                    SFA_Message::setErrorMsg($this->translate->_("Can't Delete Log In OR Administrator User."));
                }
                SFA_Message::setMsg($this->translate->_('Delete Record'));
            }
            
            $this->_helper->redirector('user', 'security', 'admin');
        }
        
        $this->view->title	= $this->translate->_('User Master');
        
        $parm_val 		= $this->getRequest()->getParams();
        
        if($parm_val['succ'] == '1' && $parm_val['succ'] != ''){
            $this->view->success	= $this->translate->_('Record Success');
        }
        
        $cols_array 	= array('um.userid','um.username','ut.user_type','um.accesstypeid');
        $columns_show 	=  array($this->translate->_('Code'),$this->translate->_('Username'),$this->translate->_('User Type'),$this->translate->_('Access Type'));
        
        // prepare the configuration for grid
        $pagingparams = array(
                "show_grid_heading" => true,
                "grid_heading_message" => $this->translate->_('Overview'),
                "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
                "show_searchbox" => true,
                "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
                "pagename" => $this->translate->_('User'),
                "show_selectbox" => true,
                "selected_list" => $checked,
                "show_editlink" => true,
                "show_deletelink" => false,
                "show_deleteall" => false,
                "status_cols" => array	(
                                array(
                                "cols_name" => "accesstypeid",
                                "status_change" => array(
                                                1	=> 'Company',
                                                2	=> 'Country',
                                                3	=> 'Region',
                                                4	=> 'Depot',
                                                5	=> 'Area',
                                                6	=> 'SubArea'                                    
                                                )
                                            )
                            ),
                "primaryid" => "userid",
                "editlink" => array("/admin/security/adduser/id/#pattern#/edit/yes/","#pattern#"),
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
        $result = $this->SFA_Comman->executequery('CALL sp_get_admin_security_user(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
    
        $data_arr["count"]	= $result[0][0]['counter'];
        $data_arr["data"][0]	= $result[1];
        
        // pass the data in summary_showdatagrid() function & create a final variable for view
        $this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
        
        $this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    
    /**
    * @name       addusertypeAction
    * @since      28-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is for add user inforamtion
    */
    public function adduserAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
    
        $this->view->css 		    = $this->translate->_('CSS');
        
        $ex_param = "";
        if($params['id'] > 0)
            $ex_param = "/key1/".$params["id"];
            
        $this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/useraccessgrid".$ex_param);
        
        // called stored procedure for counter
        $result = $this->SFA_Comman->executequery('CALL sp_combo_usertype()','','');        
        $this->view->user_type = $result[0];
        
        $user_access = array();
        $user_access[0]['val'] 	= 'Company';
        $user_access[1]['val'] 	= 'Country';
        $user_access[2]['val'] 	= 'Region';
        $user_access[3]['val'] 	= 'Depot';
        $user_access[4]['val'] 	= 'Area';
        $user_access[5]['val'] 	= 'SubArea';
        $user_access[0]['id'] 	= '1';
        $user_access[1]['id'] 	= '2';
        $user_access[2]['id'] 	= '3';
        $user_access[3]['id'] 	= '4';
        $user_access[4]['id'] 	= '5';
        $user_access[5]['id'] 	= '6';
        $this->view->user_access	= $user_access;
        
        if(count($formdata) > 0) {
        
            $formdata['hdnselectedids'] = substr_replace($formdata['hdnselectedids'],"",-1);
            if($formdata['hdnid'] > 0)
            {
                $param_array = array();
                $param_array[1] = trim($formdata['txtcode']); 		//Code
                $param_array[2] = trim($formdata['txtusername']);	//Username
                $param_array[3] = trim($formdata['txtpassword']);	//PAssword
                $param_array[4] = trim($formdata['ddlusertype']);	//User type
                $param_array[5] = trim($formdata['ddluser_access']);//user access
                $param_array[6] = trim($formdata['hdnselectedids']);
                $param_array[7]	= $this->currentUser->username;     // Loggged in user
                
                $result = $this->SFA_Comman->executequery('CALL sp_edit_admin_security_adduser(?,?,?,?,?,?)',$param_array,'');
                if($result[0][0]['lastid'] > 0)
                {
                    SFA_Message::setMsg($this->translate->_('Update Record'));
                    $this->_helper->redirector('user', 'security', 'admin');
                }
                else
                {
                    $res['txtcode']		= $result[1][0]['userid'];
                    $res['txtusername']	= $result[1][0]['username'];
                    $res['password']	= $result[1][0]['password'];
                    $res['ddlusertype']	= $result[1][0]['usertypeid'];
                    $res['ddluser_access']= $result[1][0]['accesstypeid'];
                    $this->view->formdata 	= $res;
                    
                    SFA_Message::setErrorMsg($this->translate->_('This Username Has Already Taken.'));
                }
            }
            else
            {
                $param_array = array();
                $param_array[1] = trim($formdata['txtcode']); 		//Code
                $param_array[2] = trim($formdata['txtusername']);	//Username
                $param_array[3] = trim($formdata['txtpassword']);	//PAssword
                $param_array[4] = trim($formdata['ddlusertype']);	//User type
                $param_array[5] = trim($formdata['ddluser_access']);//user access
                $param_array[6] = trim($formdata['hdnselectedids']);
                $param_array[7]	= $this->currentUser->username;     // Loggged in user
                
                
                $last_id = $this->SFA_Comman->executequery('CALL sp_add_admin_security_adduser(?,?,?,?,?,?)',$param_array,'');
                
                if($last_id[0][0]['lastid'] > 0)
                {
                    SFA_Message::setMsg($this->translate->_('New Record'));
                    $this->_helper->redirector('user', 'security', 'admin');
                }
                else
                {
                    SFA_Message::setErrorMsg($this->translate->_('This Username Has Already Taken.'));
                }
            }
        }
        elseif($params['id'] > 0)
        {
            $result = $this->SFA_Comman->executequery('CALL sp_get_admin_security_adduser(?)',$params['id'],'');
            
            $res['txtcode']		= $result[0][0]['userid'];
            $res['txtusername']	= $result[0][0]['username'];
            $res['password']	= $result[0][0]['password'];
            $res['ddlusertype']	= $result[0][0]['usertypeid'];
            $res['ddluser_access']= $result[0][0]['accesstypeid'];
            
            $this->view->formdata 	= $res;
        }
        else
        {
            $result = $this->SFA_Comman->executequery('CALL sp_get_admin_security_adduser(?)','0','');
            $this->view->formdata['txtcode']	= ($result[0][0]['Auto_increment'] == '') ? '1' : $result[0][0]['Auto_increment'];
        }        
    }
    /**
    * @name       useraccessgridAction
    * @since      03-08-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param   	
    *
    * This is the function for load value in useraccess grid.
    */
	public function useraccessgridAction()
	{
		$params = $this->getRequest()->getParams();
		
		
		
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
        $additional_where_condition = array();
		if(isset($params["key"]) && $params["key"]>0) {
			$ex_param = "/key/".$params["key"];
            if($params['key'] == 1)
            {
                $table_name = 'company';
                $additional_where_condition[] = "activestatus = 1";
                $columns_array 	= array('cmpycode AS id','CONCAT(cmpycode," -- ",`name`) AS val','cmpycode as edit_del_primary_id');
            }
            elseif($params['key'] == 2)
            {
                $table_name = 'country';                
                $columns_array 	= array('countrycode AS id','CONCAT(countrycode," -- ",countryname) AS val','countrycode as edit_del_primary_id');
                $result = $this->SFA_Comman->executequery('CALL sp_combo_country()','','');
            }
            elseif($params['key'] == 3)
            {
                $table_name = 'regionmaster';                
                $columns_array 	= array('regionmstcode AS id','CONCAT(regionmstcode," -- ",regionmstname) AS val','regionmstcode as edit_del_primary_id');                
            }
            elseif($params['key'] == 4)
            {
                $table_name = 'depotmaster';
                $additional_where_condition[] = "activestatus = 1";
                $columns_array 	= array('depotcode AS id','CONCAT(depotcode," -- ",depotname) AS val','depotcode as edit_del_primary_id');                
            }
            elseif($params['key'] == 5)
            {
                $table_name = 'areamaster';
                $additional_where_condition[] = "activestatus = 1";
                $columns_array 	= array('areacode AS id','CONCAT(areacode," -- ",areaname) AS val','areacode as edit_del_primary_id');                
            }
            elseif($params['key'] == 6)
            {
                $table_name = 'subareamaster';
                $additional_where_condition[] = "activestatus = 1";
                $columns_array 	= array('subareacode AS id','CONCAT(subareacode," -- ",subareaname) AS val','subareacode as edit_del_primary_id');
            }
		}
        // for selected record in grid
        if($params["key1"] > 0) {
            
            $param_array    = array();
            $param_array[1] = $params['key1'];
            $param_array[2] = $params['key'];
            
            $result         = $this->SFA_Comman->executequery('CALL sp_get_admin_security_selecteduseraccesscode(?,?)',$param_array,'');
            $checked        = explode(',',$result[0][0]['selected']);
        }
        
		$columns_show  	= array($this->translate->_('Code'),$this->translate->_('Name'));
		
        
		// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
					 "show_grid_heading" => false,
					 "grid_heading_message" => $this->translate->_('Overview'),
					 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10000000000,
					 "show_searchbox" => false,
					 "show_selectbox" => true,
					 "show_editlink" => false,
					 "show_deletelink" => false,
					 "show_deleteall" => false,
                     "selected_list" => $checked,
					 "primaryid" => "id",
					 "currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),
					 "nodata_message" => $this->translate->_('No Record(s) Found'),
					 "fetch_columns_inquery" => $columns_array,
					 "show_columns" => $columns_show,
					 "additional_where" => $additional_where_condition,
					 );
        
		$pagingshow = new SFA_Ajaxpaging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		// call the stored procedure for fetch the data
		$param_array 	= array();
		$param_array[1] = '1';
		$param_array[2] = $get_return_vals['order_columns_name'];
		$param_array[3] = $get_return_vals['order_type'];
		$param_array[4] = $get_return_vals['offset'];
		$param_array[5] = (int)$get_return_vals['show_records_per_page'];
		$param_array[6] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[7] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
        $param_array[8] = $table_name;
	
		
		// called stored procedure for counter		
        $result = $this->SFA_Comman->executequery('CALL sp_get_admin_security_adduseraccesscode(?,?,?,?,?,?,?,?)',$param_array,'');
		$data_arr["count"] 	    = $result[0][0]['counter'];	
		$data_arr["data"][0] 	= $result[1];
		
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
		$this->render("ajaxgrid");
	}
    
    /**
    * @name       pwdgenerateAction
    * @since      28-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is for password generation
    */
    public function pwdgenerateAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        
        
        if(count($formdata) > 0) {            
            $this->view->enterkey   = $formdata['txtkey'];
            $this->view->output     = $this->toAlpha($formdata['txtkey']);            
        }
    }
    //function number
	function toNumber($data1)
	{
		$str = $data1; 
		$out = '';
		$pos = '';
			for($i = 0;$i<strlen($str);$i++) 
			{
    
				switch($str[$i]) 
				{
					case '0': $pos .="0";break;
					case '1': $pos .="1";break;
					case '2': $pos .="2";break;
					case '3': $pos .="3";break;
					case '4': $pos .="4";break;
					case '5': $pos .="5";break;
					case '6': $pos .="6";break;
					case '7': $pos .="7";break;
					case '8': $pos .="8";break;
					case '9': $pos .="9";break;
					case '-': $pos .="-";break;
					case  'a': case 'b': case 'c': $pos .="2";break;
					case  'd': case 'e': case 'f': $pos .="3";break;
					case  'g': case 'h': case 'i': $pos .="4";break;
					case  'j': case 'k': case 'l': $pos .="5";break;
					case  'm': case 'n': case 'o': $pos .="6";break;
					case  'p': case 'q': case 'r': case 's': $pos .="7";break;
					case  't': case 'u': case 'v': $pos .="8";break;
					case  'w': case 'x': case 'y': case 'z': $pos .="9";break;
				}
			}
	return $pos;
	}

    // Below function is for creating to password generate.
    function toAlpha($data){
        
        $numlen = (int)strlen($data);        
        if($numlen <8) {            
            $data = (int)($data.'123');
        }
        
        $alphabet =   array('a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z');
        $alpha_flip = array_flip($alphabet);
        if($data <= 25){
          return $alphabet[$data];
        }
        elseif($data > 25){
          $dividend = ($data + 1);
          $alpha = '';
          $modulo;
          while ($dividend > 0){
            $modulo = ($dividend - 1) % 26;
            $alpha = $alphabet[$modulo] . $alpha;
            $dividend = floor((($dividend - $modulo) / 26));
          } 
          return $this->toNumber($alpha);
        }    
    }
    /**
    * @name       userpermissionAction
    * @since      27-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @author     MB <mayur.b@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is for give permission to User
    */
    
    /*
    * Modification Ticket Number: 0000032500 BAAZ(KRC) ISSUE
    * Modified by: Alvin P. Namuag
    */
    
    public function userpermissionAction()
    {
        try {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        
        $permission_by 			= array();
        $permission_by[0]['id']		= 1;
        $permission_by[1]['id']		= 2;
        $permission_by[0]['val']	= 'User';
        $permission_by[1]['val']	= 'User Type';	
        $this->view->permissionby	= $permission_by;
        
        // called stored procedure for get module header name
        $result = $this->SFA_Comman->executequery('CALL sp_combo_moduleheader()','','');        
        $this->view->allmodules = $result[0];
        
        //$this->view->allmodules		= $this->common->getcomboboxdata('moduleheader','ModuleID as id' ,'ModuleName as val');
        
        // called stored procedure for get user types
        //$result = $this->SFA_Comman->executequery('CALL sp_combo_usertype()','','');        
        //$this->view->user_type = $result[0];
        
        // called stored procedure for get user types
        $param_array = array();
        $param_array[1] = '';
        $result = $this->SFA_Comman->executequery('CALL sp_site_modules_forms_list(?)',$param_array,'');        
        $this->view->form_data = $result[0];
        
        //$this->view->user_type     	= $this->common->getcomboboxdata('usertype','UserTypeID as id' ,'UserTypeName as val');
        //$this->view->form_data		= $this->common->getformdata();
        
        if(count($formdata) > 0) {
            
            if((isset($formdata['ddlpermissionby_hid']) && $formdata['ddlpermissionby_hid'] != "") && (isset($formdata['ddlchoose_hid']) && $formdata['ddlchoose_hid'] != ""))
            {
                $param_array = array();
                $param_array[1] = $formdata['ddlpermissionby_hid'];
                $param_array[2] = $formdata['ddlchoose_hid'];
                $param_array[3] = $formdata['modulelist_detail'];
                
                $this->SFA_Comman->executequery('CALL sp_check_acl_entry(?,?,?)',$param_array,'');
            }
            
            //if(isset($formdata['btnsave']) && $formdata['btnsave'] != '')
            {
                //$modules = implode(',',$formdata['modules']);
                
                $param_array = array();
                $param_array[1] = $formdata['modulelist_detail'];
                
                $allmodulelist = $this->SFA_Comman->executequery('CALL sp_site_modules_forms_list(?)',$param_array,'');        
                
                //$this->view->form_data = $result[0];
                //echo $this->view->form_data;
                //exit;
                
                if(isset($formdata['ddlpermissionby_hid']) && $formdata['ddlpermissionby_hid'] != '')
                {
                    $param_array = array();                    
                    $insarray = array();
                    
                    for($i=0; $i<count($formdata['read_chk']);$i++)
                    {
                        $exp = explode("_",$formdata['read_chk'][$i]);
                        $moduleid = $exp[0];
                        $formid = $exp[1];
                        
                        $insarray[$formdata['read_chk'][$i]]['read'] = 1;
                        $insarray[$formdata['read_chk'][$i]]['moduleid'] = $moduleid;
                        $insarray[$formdata['read_chk'][$i]]['formid'] = $formid;
                    }
                    
                    for($i=0; $i<count($formdata['write_chk']);$i++)
                    {
                        $exp = explode("_",$formdata['write_chk'][$i]);
                        $moduleid = $exp[0];
                        $formid = $exp[1];
                        
                        $insarray[$formdata['write_chk'][$i]]['write'] = 1;
                        $insarray[$formdata['write_chk'][$i]]['moduleid'] = $moduleid;
                        $insarray[$formdata['write_chk'][$i]]['formid'] = $formid;
                    }
                    
                    for($i=0; $i<count($formdata['delete_chk']);$i++)
                    {
                        $exp = explode("_",$formdata['delete_chk'][$i]);
                        $moduleid = $exp[0];
                        $formid = $exp[1];
                        
                        $insarray[$formdata['delete_chk'][$i]]['delete'] = 1;
                        $insarray[$formdata['delete_chk'][$i]]['moduleid'] = $moduleid;
                        $insarray[$formdata['delete_chk'][$i]]['formid'] = $formid;
                    }
                    
                    for($i=0; $i<count($formdata['all_chk']);$i++)
                    {
                        $exp = explode("_",$formdata['all_chk'][$i]);
                        $moduleid = $exp[0];
                        $formid = $exp[1];
                        $insarray[$formdata['all_chk'][$i]]['all'] = 1;
                        $insarray[$formdata['all_chk'][$i]]['moduleid'] = $moduleid;
                        $insarray[$formdata['all_chk'][$i]]['formid'] = $formid;
                    }
                    
                    $updquery = array();
                    if(count($insarray) > 0)
                    {
                        foreach($insarray as $key => $val)
                        {
                            $table = ($formdata['ddlpermissionby_hid'] == '1') ? "userdetail" : "usertypedetail";
                            //#region 1 APN added get Field ID and User ID 01/04/2015
                            $var_fldID = ($formdata['ddlpermissionby_hid'] == '1') ? "userid" : "usertypeid";
                            $var_fldUserID = $formdata['ddlchoose_hid'];
                            //#endregion
                            $updatestr = "UPDATE ".$table." SET ";
                            $read = isset($val['read']) ? $val['read'] : 0;
                            $write = isset($val['write']) ? $val['write'] : 0;
                            $delete = isset($val['delete']) ? $val['delete'] : 0;
                            $all = isset($val['all']) ? $val['all'] : 0;
                            $updatestr .= " readdata = ".$read.",updatedata = ".$write.",insertdata = ".$write.",deletedata = ".$delete.",allpermissions = ".$all." WHERE formid = '".$val['formid']."'";
                            $updatestr .= " and " .$var_fldID. " = '" .$var_fldUserID."'"; // APN added 01/04/2015 additional filter Field ID and User ID
                            $updquery[] = $updatestr;
                            $formids_arr[] = $val['formid'];
                        }
                    }
                    
                    $query = "";
                    $query = (!empty($updquery)) ?implode(";",$updquery):"";
                    $query .= ($query != "")?";":"";
                    $redirect_logout = false;
                    
                    $filedisplayarr=array();
                    $filesarr = $this->SFA_Comman->show_dir_file_array(CACHE_PATH."/db/adaptor",$filedisplayarr);
                    
                    $remove_acl_arr = array();
                    
                    for($i=0; $i < count($allmodulelist[0]); $i++)
                    {
                        if(!in_array($allmodulelist[0][$i]['formid'],$formids_arr))
                        {
                            $remove_acl_arr[] = $allmodulelist[0][$i]['formid'];
                        }
                    }
                    
                    if(isset($remove_acl_arr) && !empty($remove_acl_arr))
                    {   
                        $table = ($formdata['ddlpermissionby_hid'] == '1') ? "userdetail" : "usertypedetail";
                        //#Region 2 APN added update depending on the table with userid and field id 
                        $var_userID = $formdata['ddlchoose_hid'];
                        if($table == 'userdetail')
                        {
                            $query .= " UPDATE $table SET readdata = 0,updatedata = 0,insertdata = 0,deletedata = 0,allpermissions = 0 WHERE userid = " .$var_userID. " and formid IN (".implode(",",$remove_acl_arr).");";
                        }
                        else
                        {
                            $query .= " UPDATE $table SET readdata = 0,updatedata = 0,insertdata = 0,deletedata = 0,allpermissions = 0 WHERE usertypeid = " .$var_userID. " and formid IN (".implode(",",$remove_acl_arr).");";
                        }
                        //#endregion 2
                    }
                    
                    if($formdata['ddlpermissionby_hid'] == '1') {
                        $userid = $formdata['ddlchoose_hid'];
                        $search_1 = $this->SFA_Comman->search_array($filesarr,"_u_".$userid."_");
                        $search_2 = $this->SFA_Comman->search_array($filesarr,"_user_".$userid);
                        if($userid == $this->currentUser->userid)
                        {
                            $redirect_logout = true;
                        }
                    } else {
                        $usertypeid = $formdata['ddlchoose_hid'];
                        $search_1 = $this->SFA_Comman->search_array($filesarr,"_r_".$usertypeid);
                        $search_2 = $this->SFA_Comman->search_array($filesarr,"_role_".$usertypeid);
                        if($usertypeid == $this->currentUser->usertypeid)
                        {
                            $redirect_logout = true;							
                        }
                    }
                    
                    for( $i = 0; $i < count($search_1); $i++ )
                    {
                        @unlink(CACHE_PATH."/db/adaptor/".$filesarr[$search_1[$i]]);
                    }
                    
                    for( $i = 0; $i < count($search_2); $i++ )
                    {
                        @unlink(CACHE_PATH."/db/adaptor/".$filesarr[$search_2[$i]]);
                    }
                    
                    if($query != '')
                    {
                        $SFA_Model_Core = new SFA_Model_Core();
                        $SFA_Model_Core->executeaclquery($query);
                        
                        SFA_Message::setMsg($this->translate->_('Update Record'));
                        if($redirect_logout)
                            $this->_helper->redirector('logout', 'index','home');
                        else
                            $this->_helper->redirector('userpermission', 'security', 'admin');
                    }
                }
            }
        }
        }
        catch(Zend_Exception $e)
        {
            echo $e->getMessage();exit;
        }
    }
    
    public function filtermoduleAction()
    {
        $formdata = $this->_request->getPost();
        
        $modules = implode(',',$formdata['modules']);
                
        $param_array = array();
        $param_array[1] = $modules;
        
        $result = $this->SFA_Comman->executequery('CALL sp_site_modules_forms_list(?)',$param_array,'');
        $this->view->form_data = $result[0];
    }
    
    public function getuserandusertypeAction()
    {
        $params = $this->getRequest()->getParams();
		$result = $this->SFA_Comman->executequery('CALL sp_get_user_and_usertype(?)',$params['id'],'');
		echo Zend_Json::encode($result[0]);
        exit;
    }
    
    
    public function searchfiltermoduleAction()
    {
        $alldata = $this->_request->getParams();
        
        $modules = implode(',',$alldata['modules']);
        $param_array = array();
        $param_array[1] = $alldata['type'];
        $param_array[2] = $alldata['searchval'];
        $param_array[3] = $modules;
        
        $result = $this->SFA_Comman->executequery('CALL sp_site_search_modules_forms_list(?,?,?)',$param_array,'');
        if($result[0][0]['cnt'] > 0)
        {
            $this->view->all_check = "0";
        }
        else
        {
            $this->view->all_check = "1";
        }
        $this->view->form_data = $result[1];
    }
	public function deviceregistrationAction()
    {
        
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		
		if($formdata["hdDelete"]==1)
        {
            $ids = implode(',',$formdata['chk']);
            $param_array 	= array();
            $param_array[1]	= $ids;
            $param_array[2]	= $this->currentUser->username;	
            $param_array[3]	= $this->currentUser->userid;
           
            $result = $this->SFA_Comman->executequery('CALL sp_delete_device(?,?,?)',$param_array,'');
            
            /*if($result[0][0]['deleted_id'] =='')
            {
                $ids		= explode(',',$ids);
                $checked 	= $ids;
                
                //SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
                SFA_Message::setErrorMsg($this->translate->_("Can't Delete Log In OR Administrator User."));
            }
            else*/
            {
               /* $deleted_id 	= explode(',',$result[0][0]['deleted_id']);
                $ids		= explode(',',$ids);
                $checked 	= array_diff($ids,$deleted_id);
                
                if(count($ids) != count($deleted_id)) {
                    //SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
                    SFA_Message::setErrorMsg($this->translate->_("Can't Delete Log In OR Administrator User."));
                }*/
                SFA_Message::setMsg($this->translate->_('Delete Record'));
            }
            
           // $this->_helper->redirector('user', 'security', 'admin');
        }
        
        $this->view->title	= $this->translate->_('Device Registation');
        $code			= $this->translate->_('DeviceId');
        $name			= $this->translate->_('CompanyId');
        
        $parm_val 		= $this->getRequest()->getParams();
        
        if($parm_val['succ'] == '1' && $parm_val['succ'] != ''){
            $this->view->success	= $this->translate->_('Record Success');
        }
        
        $cols_array 	= array('primary_key','device_id','company_id');
        $columns_show 	=  array($this->translate->_('Id'),$this->translate->_('Device Id'),$this->translate->_('Remarks'));
        
        // prepare the configuration for grid
        $pagingparams = array(
                "show_grid_heading" => true,
                "grid_heading_message" => $this->translate->_('Overview'),
                "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
                "show_searchbox" => true,
                "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
                "pagename" => $this->translate->_('Device Registration'),
                "show_selectbox" => true,
                "selected_list" => $checked,
                "show_editlink" => false,
                "show_deletelink" => false,
                "show_deleteall" => false,
                "status_cols" => array	(
                            ),
                "primaryid" => "primary_key",
                "nodata_message" => $this->translate->_('No Record(s) Found'),
                "fetch_columns_inquery" => $cols_array,
                "show_columns" => $columns_show
                );
        
        if(!$this->checkaccess("update"))
        {
            $pagingparams["show_editlink"] = false;
        }
        $downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];
    
        // Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
        $printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
        
        // called stored procedure for counter
        $result = $this->SFA_Comman->executequery('CALL sp_get_admin_security_device()',null);
    
        $data_arr["count"] = sizeof($result[0]);
        $data_arr["data"][0] = $result[0];
        
        // create grid class object
        $pagingshow = new SFA_Paging($pagingparams);
        // call common function of grid class
        $get_return_vals = $pagingshow->commnfunc();
        // pass the data in summary_showdatagrid() function & create a final variable for view
        $this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
        $this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    
    public function adddeviceAction()
    {
    	//echo str_repeat(" ",1024*1024*4) ."deviceregistrationAction!<br>";ob_flush();
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		 = $this->translate->_('CSS');

        if(count($formdata) > 0) {
        		$param_array = array();
                $param_array[1] = trim($formdata['deviceid']); 		//Device Id
                $param_array[2] = trim($formdata['companyid']); 		//Company Id
                $param_array[3] = $this->currentUser->username;	    //User Name
                $last_id = $this->SFA_Comman->executequery('CALL sp_add_admin_security_adddevice()',$param_array,'');				
				if($last_id[0][0]['lastid'] =='-1')
				{
					SFA_Message::setErrorMsg($this->translate->_('Registration exceeds the maximum number of purchased licenses.'));
				}else if($last_id[0][0]['lastid'] =='0')
				{
					SFA_Message::setErrorMsg($this->translate->_('Device Already Registered'));	
				}else
				{
					SFA_Message::setMsg($this->translate->_('New Record'));
					$this->_helper->redirector('deviceregistration', 'security', 'admin');	
				}					
				
               
        }
		
    }  
}