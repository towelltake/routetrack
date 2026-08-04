<?php
/**
* @name       PdcController
* @since      03-10-2012
* @version    Release: 1
* @author     PT <pankil@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage report module.
*/
class Reports_RoutesyncstatusController extends Reports_Library_Controller_Action_Abstract
{
    /**
    * @name       init
    * @since      09-10-2012
    * @version    Release: 1
    * @author     PT <pankil@elantechnologies.com>
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
        
        $this->sec_lang 	  = $this->view->sec_lang;
        $this->decimalplaces  = $this->view->decimalplaces	= $this->SFA_Comman->getdecimalplaces();
        $this->view->sec_lang = $this->SFA_Comman->getsecondlanguage();
        
        $this->report_session = new Zend_Session_Namespace('Re_routesyncstatus');
        $this->session = $this->view->session = new Zend_Session_Namespace('SESSION');
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
	
    /**
    * @name       routesyncstatusAction
    * @since      09-10-2012
    * @version    Release: 1
    * @author     PT <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display daily sales sheet report
    *
    */
    public function routesyncstatusAction()
    {
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata  = $formdata = $this->_request->getPost();
		//$result_arr = $this->SFA_Comman->executequery('CALL sp_report_dailyreport_routesyncstatus_detail()','','');
        $this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/ajaxdata/useraccessgrid");
        //$this->view->route_list = $result_arr[0];
        $this->report_session->post = array();
	}

    /**
    * @name       indexAction
    * @since      9-10-2012
    * @version    Release: 1
    * @author     PT <pankil@elantechnologies.com>
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
        
        $this->view->ReportTitle = $this->translate->_("Route Sync Status");
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
                                            array("title"=> $this->filter_arr[$formdata['ddlfilterby']],
                                                  "value" => $title_val),
                                            array("title"=> "Route End Date",
                                                  "value" => ($formdata['txt_route_end_date'] != "" ) ? date("d M Y",strtotime($formdata['txt_route_end_date'])) : "")
                                            );
        $this->report_session->searchParams = $this->view->searchParams;
        //echo "<pre>";
        //print_r($this->view->searchParams);exit;
        $this->view->xlsexport_link = $this->view->baseUrl()."/reports/routesyncstatus/export";
        $this->view->cvsexport_link = $this->view->baseUrl()."/reports/routesyncstatus/exportcsv";
    }
    /**
      * @name       routesyncstatusdataAction
      * @since      9-10-2012
      * @version    Release: 1
      * @author     PT <pankil@elantechnologies.com>
      * @copyright  Elan Technologies
      * @param
      *
      * This action fetch customer pending request data
      *
      */
    public function routesyncstatusdataAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        $extra_where = "";
        
        if(isset($this->report_session->post['txt_route_end_date']) && $this->report_session->post['txt_route_end_date'] != "")
            $extra_where  .= ' AND DATE_FORMAT(sed.routeenddate,"%Y-%m-%d") = "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
        //if(isset($this->report_session->post['ddlroutecode']) && $this->report_session->post['ddlroutecode'] != "")
        //    $extra_where .= " AND sed.routecode IN (".$this->report_session->post['ddlroutecode'].")";
        if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
            $extra_where .= ' AND sed.routecode IN ('.$this->report_session->routecode_str.')';
        
      
        $page = $_GET['page']; // get the requested page
        $limit = $_GET['rows']; // get how many rows we want to have into the grid
        $sidx = $_GET['sidx']; // get index row - i.e. user click to sort
        $sord = $_GET['sord']; // get the direction
        if(!$sidx) $sidx =1;
        
        if(empty($sidx)) {  $sidx  = "syncdate";}
        if(empty($sord)) {  $sord  = "asc";}
    
        $param_array = array();
        $param_array[1] = $extra_where;
        $param_array[2] = $sidx;
        $param_array[3] = $sord;
        $param_array[4] = $limit;
        $param_array[5] = $page;
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_dailyreport_routesyncstatus(?,?,?,?,?)',$param_array,'');
        
        $count  = $result_arr[0][0]['counter'];
        if( $count >0 ) {
            $total_pages = ceil($count/$limit);
        } else {
            $total_pages = 0;
        }
        if ($page > $total_pages) $page = $total_pages;
        $start = $limit*$page - $limit; // do not put $limit*($page - 1)
    
        $responce->page = $page;
        $responce->total = $total_pages;
        $responce->records = $count;
        $i=0;
        
        if(!empty($result_arr[1])) {
            foreach($result_arr[1] as $row) {
                $routename = ($this->session->lang == "ar_AR") ? $row['arbroutename'] : $row['routename'];
                $salesmanname = ($this->session->lang == "ar_AR") ? $row['arbsalesmanname1'] : $row['salesmanname1'];
                $responce->rows[$i]['id'] = $i;
                $responce->rows[$i]['cell'] = array($row['routecode'].' - '.$routename,
                                                    $salesmanname,$row['syncdate1'],$row['synctime'],$row['synctype'],$row['routeclosed']);
                $i++;
            }
        }
        else
        {
          //  $responce->rows[$i]['id']=1;
          //  $responce->rows[$i]['cell']=array("","","","No Record Founds","", "");
        }
        
        echo json_encode($responce);
        exit;
    }
    
    
     /**
      * @name       exportAction
      * @since      19-10-2012
      * @version    Release: 1
      * @author     PT <pankil@elantechnologies.com>
      * @copyright  Elan Technologies
      * @param
      *
      * This action fetch customer pending request data
      *
      */
    public function exportAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        $extra_where = "";
        
        if(isset($this->report_session->post['txt_route_end_date']) && $this->report_session->post['txt_route_end_date'] != "")
            $extra_where  .= ' AND DATE_FORMAT(sed.routeenddate,"%Y-%m-%d") = "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
        //if(isset($this->report_session->post['ddlroutecode']) && $this->report_session->post['ddlroutecode'] != "")
        //    $extra_where .= " AND sed.routecode IN (".$this->report_session->post['ddlroutecode'].")";
        if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
            $extra_where .= ' AND sed.routecode IN ('.$this->report_session->routecode_str.')';
        
      
        $page = $_GET['page']; // get the requested page
        $limit = $_GET['rows']; // get how many rows we want to have into the grid
        $sidx = $_GET['sidx']; // get index row - i.e. user click to sort
        $sord = $_GET['sord']; // get the direction
        if(!$sidx) $sidx =1;
        
        if(empty($sidx)) {  $sidx  = "syncdate";}
        if(empty($sord)) {  $sord  = "asc";}
    
        $param_array = array();
        $param_array[1] = $extra_where;
        $param_array[2] = $sidx;
        $param_array[3] = $sord;
        $param_array[4] = $limit;
        $param_array[5] = $page;
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_dailyreport_routesyncstatus(?,?,?,?,?)',$param_array,'');
        
        $report_title_height = 15;
        for($i=0;$i<count($this->report_session->searchParams);$i++)
        {
            if($this->report_session->searchParams[$i]["value"] != "")
            {
                if($this->session->lang == "ar_AR") {
                    $report_title .= "\r ".$this->report_session->searchParams[$i]["value"]." : ".$this->translate->_($this->report_session->searchParams[$i]["title"]);
                } else {
                    $report_title .= "\r ".$this->translate->_($this->report_session->searchParams[$i]["title"]) . " : ".$this->report_session->searchParams[$i]["value"];
                }
            }
            $report_title_height += 10;
        }
        
        $data = $result_arr[0];
        $data_arr = array();
        
        $column_model_arr = array();
        $data_arr["columns"] = array($this->translate->_('Route'),$this->translate->_('Salesman'),$this->translate->_('Sync Date'),$this->translate->_('Sync Time'),$this->translate->_('Sync Type'),$this->translate->_('Route Status'));
        $data_arr["columns_config"] = array(array("width"=>15),
                                            array("width"=>15),
                                            array("width"=>15,"align"=>"center"),
                                            array("width"=>15,"align"=>"center"),
                                            array("width"=>15,"align"=>"center"),
                                            array("width"=>15,"align"=>"center")
                                        );
        
        for($i = 0; $i < count($result_arr[0]); $i++)
        {
            $routename = ($this->session->lang == "ar_AR") ? $result_arr[0][$i]['arbroutename'] : $result_arr[0][$i]['routename'];
            $salesmanname = ($this->session->lang == "ar_AR") ? $result_arr[0][$i]['arbsalesmanname1'] : $result_arr[0][$i]['salesmanname1'];
            $column_model_arr[$result_arr[0][$i]['routecode'].' - '.$routename][]
                = array($salesmanname,$result_arr[0][$i]['syncdate1'],$result_arr[0][$i]['synctime'],$result_arr[0][$i]['synctype'],$result_arr[0][$i]['routeclosed']);
        }
        
        $data_arr["columns_model"]          = $column_model_arr;
        $data_arr["config"]["report_title"] = $this->translate->_("Route Sync Status").$report_title;
        $data_arr["config"]["report_title_height"] = $report_title_height;
        $data_arr["config"]["file_name"]    = "SyncStatus";
        $data_arr["config"]["group_level"]  = 1;
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
		
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_account_pdc_detail(?)',$params['cust_type'],'');
        echo Zend_Json::encode($result_arr);
		exit;
	}
    
}
