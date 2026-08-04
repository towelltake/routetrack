<?php
/**
* @name       AccountController
* @since      20-02-2012
* @version    Release: 1
* @author     PM <pankit@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage report module.
*/
class Reports_TargetandcommissionController extends Reports_Library_Controller_Action_Abstract
{
     /**
    * @name       init
    * @since      01-10-2012
    * @version    Release: 6
    * @author     Nidhi
    * @copyright  Elan Technologies
    * @param
    *
    * This is the default function for all Actions.
    *
    */
    protected $report_session ;
    public function init()
    {
        $this->translate 	= Zend_Registry::get('Zend_Translate');
        $this->view->colan	= $this->translate->_('Colan');
        $this->SFA_Comman	= new SFA_Comman();
        
        $this->currentUser = SFA_Loginauth::getIdentity();	
        if(!isset($this->currentUser) || empty($this->currentUser))
        {
           // SFA_Message::setMsg($this->translate->_('Do Login'));
           // //$this->_helper->redirector("index", "index", "home");
			$url = $this->view->baseUrl();
			echo '<script type="text/javascript">window.location="'.$url.'";</script>';
			exit;
        }
        
        $this->sec_lang 	                        	= $this->view->sec_lang;
        $this->decimalplaces  = $this->view->decimalplaces	= $this->SFA_Comman->getdecimalplaces();
        $this->view->sec_lang	                        	= $this->SFA_Comman->getsecondlanguage();
        
        $this->report_session		= new Zend_Session_Namespace('Re_merchandizingpos');
        $this->css 				= $this->translate->_('CSS');
		$this->view->css 		= $this->css;
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
            if(!$this->checkaccess("read")) {
                
                $this->_forward('noaccess','aclaccess','home', array("actiontype"=>"read","modulename"=>$this->currentmodulename));
                
            }
        }
        
        /**
         *      Acl Code end
         */
    }
	
    public function posmasterAction()
    {
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata  = $formdata = $this->_request->getPost();
        
        $this->report_session->post = array();
    }

    /**
    * @name       totalsalesbyhierarchyAction
    * @since      15-02-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display daily sales sheet report
    *
    */
    public function indexAction()
    {
        $this->_helper->layout->setLayout('jqreport');
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata  = $formdata = $this->_request->getPost();
        
        $this->report_session->post = $formdata;
        
        $this->view->ReportTitle = $this->translate->_("Target & Commission");
        $this->view->pageHeaderTitle  = $this->translate->_('Date');
        $this->view->pageHeadervalue  =  date("m/d/Y h:i:s");
        
        $this->report_session->routecode_str = "";
        if($formdata['ddlfilterby'] != "") {
            if(isset($formdata['chkall']) && $formdata['chkall'] == "on") {
                $this->report_session->routecode_str = "";
            } else {
                $all_search = $formdata["chk"];
                for($i=0;$i<count($all_search);$i++)
                {
                    $search_arr = explode("$$",$all_search[$i]);
                    $chk_arr[] = $search_arr[0];
                    $name_arr[] = $search_arr[1];
                }
                
                $routecode_str = "";
                if($formdata['ddlfilterby'] != 6) {
                    $param_array = array();
                    $param_array[1] = $formdata['ddlfilterby'];
                    $param_array[2] = implode(",",$chk_arr);
                    
                    $result_arr = $this->SFA_Comman->executequery('CALL sp_report_common_get_report(?,?)',$param_array,'');
                    
                    $routecode_arr = array();
                    
                    for($i=0;$i<count($result_arr[0]);$i++)
                    {
                        $routecode_arr[] = $result_arr[0][$i]["routecode"];
                    }
                    if(!empty($routecode_arr)) {
                        $routecode_str = implode(",",$routecode_arr);
                    }
                } else {
                    $routecode_str = implode(",",$chk_arr);
                }
                
                $this->report_session->routecode_str = $routecode_str;
            }
        }
        if($formdata['chkall'] == "on") {
            $title_val = "All ".$this->filter_arr[$formdata['ddlfilterby']];
        } elseif(isset($name_arr) && !empty($name_arr)) {
            $title_val = implode(", ",$name_arr);
        } else {
            $title_val = "";
        }
        
        $this->view->searchParams  =  array(
                                            array("title"=> "Start Date",
                                                "value" => ($formdata['txt_route_start_date'] != "" ) ? date("d M Y",strtotime($formdata['txt_route_start_date'])) : ""),
                                            array("title"=> "End Date",
                                                "value" => ($formdata['txt_route_end_date'] != "" ) ? date("d M Y",strtotime($formdata['txt_route_end_date'])) : "")
                                        );
        $this->report_session->searchParams = $this->view->searchParams;
        
        $this->view->xlsexport_link = $this->view->baseUrl()."/reports/targetandcommission/export";
      
        
    }
    /**
      * @name       custpendingbalAction
      * @since      15-02-2012
      * @version    Release: 1
      * @author     GP <gayatri@elantechnologies.com>
      * @copyright  Elan Technologies
      * @param
      *
      * This action fetch customer pending request data
      *
      */
	  
	    public function sessionvalAction()
    {
		//view variable declaration
		session_start();
		$_SESSION['tgtype'] = $_POST['tgtype'];
		print json_encode(array('message' => $_SESSION['SESS_LANGUAGE']));
		die();
		echo Zend_Json::encode($report_list);
		exit;
    }  
    public function targetdataAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');       
		
		 $page = $_GET['page']; // get the requested page
        $limit = $_GET['rows']; // get how many rows we want to have into the grid
        $sidx = $_GET['sidx']; // get index row - i.e. user click to sort
        $sord = $_GET['sord']; // get the direction
        if(!$sidx) $sidx =1;
        
        if(empty($sidx)) {  $sidx  = "routecode";}
        if(empty($sord)) {  $sord  = "asc";}
        
        $param_array = array();
        $param_array[1] = $limit;
        $param_array[2] = $page;
		$param_array[3] = date("Y-m-d", strtotime($_SESSION['tgtype']));
		
        $result_arr = $this->SFA_Comman->executequery('CALL sp_get_inventory_target_commission_report(?,?)',$param_array,'');       
		$count = $result_arr[0][0]['counter'];
		
        if( $count >0 ) {
            $total_pages = ceil($count/$limit);
        } else {
            $total_pages = 0;
        }
        if ($page > $total_pages) $page=$total_pages;
        $start = $limit*$page - $limit; // do not put $limit*($page - 1)
        
        $responce->page = $page;
        $responce->total = $total_pages;
        $responce->records = $count;
        $i=0;
        if(!empty($result_arr[1])){
            foreach($result_arr[1] as $row) {
                $responce->rows[$i]['id']= $i;
                $responce->rows[$i]['cell']=array($row['routecode']."-".$row['routename'],$row['salesmancode']."-".$row['salesmanname1'],$row['fromdate'].'-'.$row['todate'],$row['targettype'],$row['packagedescription'],$row['quantity'],$row['commision'],$row['insentivepercent'],$row['insentive'],$row['achieveamount'],$row['targetstatus']);
                $i++;
            }
        }
        else
        {
        //  $responce->rows[$i]['id']=1;
        //  $responce->rows[$i]['cell']=array("","","","No Record Founds","", "");
        }
        
        //$responce->userdata['EmployeeID'] = "2000";
        //$responce->userdata['CustomerID'] = 'Total:';
        echo json_encode($responce);
        exit;
    }
      
      
       /**
    * @name       exportpdfAction
    * @since      15-02-2012
    * @version    Release: 1
    * @author     HC <harsh@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This export the pdf in HTML format
    *
    */
    public function exportAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
     
        
        $page = $_GET['page']; // get the requested page
        $limit = $_GET['rows']; // get how many rows we want to have into the grid
        $sidx = $_GET['sidx']; // get index row - i.e. user click to sort
        $sord = $_GET['sord']; // get the direction
        if(!$sidx) $sidx =1;
        
        if(empty($sidx)) {  $sidx  = "routecode";}
        if(empty($sord)) {  $sord  = "asc";}
        
           $param_array = array();
        $param_array[1] = $limit;
        $param_array[2] = $page;
		$param_array[3] = date("Y-m-d", strtotime($_SESSION['tgtype']));
        $result_arr = $this->SFA_Comman->executequery('CALL sp_get_inventory_target_commission_report(?,?,?)',$param_array,'');  
        $report_title_height = 15;
        
        $data = $result_arr[0];
        $data_arr = array();
       
        $column_model_arr = array();
        $data_arr["columns"] = array($this->translate->_('Route Name'),$this->translate->_('Salesman Name'),$this->translate->_('From Date & To Date'),$this->translate->_('Target On'),$this->translate->_('Target Group'),$this->translate->_('Value'),$this->translate->_('Commission'),$this->translate->_('Incentive %'),$this->translate->_('Incentive'),$this->translate->_('Achieved'),$this->translate->_('Status'));
        $data_arr["columns_config"] =   array(
                                            array("width"=>25),
                                            array("width"=>25),
                                            array("width"=>29),
                                            array("width"=>13),
                                            array("width"=>13),  
                                            array("width"=>13),
											array("width"=>13),  
                                            array("width"=>13),
											array("width"=>13),  
                                            array("width"=>13),
											array("width"=>13)
											
                                        );
        for($i = 0; $i < count($result_arr[0]); $i++)
        {  
			
            $column_model_arr[] = array($result_arr[0][$i]['routecode'].' - '.$result_arr[0][$i]['routename'],$result_arr[0][$i]['salesmancode']."-".$result_arr[0][$i]['salesmanname1'],$result_arr[0][$i]['fromdate'].' - '.$result_arr[0][$i]['todate'],$result_arr[0][$i]['targettype'],$result_arr[0][$i]['packagedescription'],$result_arr[0][$i]['quantity'],$result_arr[0][$i]['commision'],$result_arr[0][$i]['insentivepercent'],$result_arr[0][$i]['insentive'],$result_arr[0][$i]['achieveamount'],$result_arr[0][$i]['targetstatus']);
        }
        
        $data_arr["columns_model"]          = $column_model_arr;
        $data_arr["config"]["report_title"] = $this->translate->_("Target & Commission");
        $data_arr["config"]["report_title_height"] = $report_title_height;
        $data_arr["config"]["file_name"]    = "TargetCommission";
        $data_arr["config"]["group_level"]  = 0;
        $data_arr["config"]["total_columns"]= count($data_arr["columns"]);
        $data_arr["config"]["group_total"]  = "0";
        $data_arr["config"]["main_total"]   = "0";
        
        
        $SFA_Exportxls = new SFA_Exportxls($data_arr);
        $objPHPExcel = $SFA_Exportxls->exportxls();
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        exit;
    }
     /**
    * @name       getcustomersAction
    * @since      05-10-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for getting  customer info on the basis of the customer type.
    *
    */
    public function getcustomersAction()
    {
        $params = $this->getRequest()->getParams();
		
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_account_customerpendingbalance_detail(?)',$params['cust_type'],'');
        echo Zend_Json::encode($result_arr);
		exit;
	}
}
