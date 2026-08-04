<?php
/**
* @name       StrikeratecallrateavgtimeController
* @since      16-10-2012
* @version    Release: 1
* @author     PT <pankil@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage report module.
*/
class Reports_StrikeratecallrateavgtimeController extends Reports_Library_Controller_Action_Abstract
{
    /**
    * @name       init
    * @since      16-10-2012
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
		$this->css 			= $this->translate->_('CSS');
		$this->view->css	= $this->css;
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
        
        $this->report_session = new Zend_Session_Namespace('Re_strikeratecallrateavgtime');
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
    
    /**
    * @name       strikeratecallrateavgtimeAction
    * @since      16-10-2012
    * @version    Release: 1
    * @author     PT <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display daily sales sheet report
    *
    */
    public function strikeratecallrateavgtimeAction()
    {
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata  = $formdata = $this->_request->getPost();
		
        $this->report_session->post = array();
	}

    /**
    * @name       indexAction
    * @since      16-10-2012
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
        
        $this->view->ReportTitle = $this->translate->_("StrikeRate CallRate Discipline Length AvgTime");
        $this->view->pageHeaderTitle  = $this->translate->_('Date');
        $this->view->pageHeadervalue  =  date("m/d/Y h:i:s");
        $this->view->searchParams  =  array(
                                           array("title"=> "Route Start Date - Month",
                                                 "value" => $formdata['month_selected']),
                                           array("title"=> "Route Start Date - Year",
                                                 "value" => $formdata['year_selected'])
                                           );
        $this->report_session->searchParams = $this->view->searchParams;
        
        $this->view->xlsexport_link = $this->view->baseUrl()."/reports/strikeratecallrateavgtime/export";
        $this->view->cvsexport_link = $this->view->baseUrl()."/reports/strikeratecallrateavgtime/exportcsv";
    }
    /**
      * @name       strikeratecallrateavgtimedataAction
      * @since      16-10-2012
      * @version    Release: 1
      * @author     PT <pankil@elantechnologies.com>
      * @copyright  Elan Technologies
      * @param
      *
      * This action fetch customer pending request data
      *
      */
    public function strikeratecallrateavgtimedataAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        $extra_where = "";
        $customertype = "";
        $customercode = "";
        
        if(isset($this->report_session->post['year']) && $this->report_session->post['year'] != "")
        {
            $extra_where  .= " AND YEAR(routestartdate) = ".$this->report_session->post['year'];
        }
        if(isset($this->report_session->post['month']) && $this->report_session->post['month'] != "")
        {
            $extra_where  .= " AND MONTH(routestartdate) = ".$this->report_session->post['month'];
        }
        
        $page = $_GET['page']; // get the requested page
        $limit = $_GET['rows']; // get how many rows we want to have into the grid
        $sidx = $_GET['sidx']; // get index row - i.e. user click to sort
        $sord = $_GET['sord']; // get the direction
        if(!$sidx) $sidx =1;
        
        if(empty($sidx)) {  $sidx  = "routecode";}
        if(empty($sord)) {  $sord  = "asc";}
    
        $param_array = array();
        $param_array[1] = ' where 1=1 '.$extra_where;
        $param_array[2] = $sidx;
        $param_array[3] = $sord;
        $param_array[4] = $limit;
        $param_array[5] = $page;
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_dataanalysis_kpi_strikerate_callrate_avgtime(?,?,?,?,?)',$param_array,'');
        
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
        
        if(!empty($result_arr[1])){
            foreach($result_arr[1] as $row) {
                
                $routename = ($this->css == 'ar_') ? $row["arbroutename"] : $row["routename"];
                $salesman = ($this->css == 'ar_') ? $row["arbsalesmanname"] : $row["salesman"];
                
                $responce->rows[$i]['id'] = $i;
                $responce->rows[$i]['cell'] = array($row['routecode'].' - '.$routename.' ( '.$row['salesmancode'].' - '.$salesman.' ) ',$row['targetto_visit'],$row['targetvisits'],$row['callexceptions'],$row['non_targetvisits'],
                                                    $row['totalvisits'], $row['schedulesale'],$row['unsched_sale'],$row['schedulenosale'],$row['unschedulenosale'],$row['effectivevisit'],
                                                    $row['strikerate'],$row['callcomp'],$row['discipline'],$row['startkms'],$row['endkms'],$row['kms_covered'],$row['averagein_out_time']
                                                    );
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
      * @since      16-10-2012
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
        $customertype = "";
        $customercode = "";
        
        if(isset($this->report_session->post['year']) && $this->report_session->post['year'] != "")
        {
            $extra_where  .= " AND YEAR(routestartdate) = ".$this->report_session->post['year'];
        }
        if(isset($this->report_session->post['month']) && $this->report_session->post['month'] != "")
        {
            $extra_where  .= " AND MONTH(routestartdate) = ".$this->report_session->post['month'];
        }
        
        $page = $_GET['page']; // get the requested page
        $limit = $_GET['rows']; // get how many rows we want to have into the grid
        $sidx = $_GET['sidx']; // get index row - i.e. user click to sort
        $sord = $_GET['sord']; // get the direction
        if(!$sidx) $sidx =1;
        
        if(empty($sidx)) {  $sidx  = "routecode";}
        if(empty($sord)) {  $sord  = "asc";}
    
        $param_array = array();
        $param_array[1] = ' where 1=1 '.$extra_where;
        $param_array[2] = $sidx;
        $param_array[3] = $sord;
        $param_array[4] = $limit;
        $param_array[5] = $page;
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_dataanalysis_kpi_strikerate_callrate_avgtime(?,?,?,?,?)',$param_array,'');
        
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
                $report_title_height += 10;
            }
        }
        
        $data = $result_arr[0];
        $data_arr = array();
        
        $column_model_arr = array();
        $data_arr["columns"] = array($this->translate->_('Route / Salesman'),$this->translate->_('Target To Visit'),$this->translate->_('Targets Visited'),$this->translate->_('Targets Not Visited'),$this->translate->_('Non Targeted Visits'),$this->translate->_('Total Visits'),$this->translate->_('Scheduled Sale'),$this->translate->_('Un Scheduled Sale'),$this->translate->_('Scheduled No Sale'),$this->translate->_('Un Scheduled No Sale'),$this->translate->_('Effective Visit'),$this->translate->_('Strike Rate'),$this->translate->_('Call Complete Rate'),$this->translate->_('Route Discipline'),$this->translate->_('Starting Kms'),$this->translate->_('Ending Kms'),$this->translate->_('Kms Covered'),$this->translate->_('Average In Out Time'));
        $data_arr["columns_config"] =   array(
                                            array("width"=>35),
                                            array("width"=>13),
                                            array("width"=>13),
                                            array("width"=>13),
                                            array("width"=>13),
                                            array("width"=>13),
                                            array("width"=>13),
                                            array("width"=>13),
                                            array("width"=>13),
                                            array("width"=>13),
                                            array("width"=>13),
                                            array("width"=>13),
                                            array("width"=>13),
                                            array("width"=>13),
                                            array("width"=>13),
                                            array("width"=>15),
                                            array("width"=>15),
                                            array("width"=>15),
                                            array("width"=>15)
                                        );
        for($i = 0; $i < count($result_arr[0]); $i++)
        {
            $routename = ($this->css == 'ar_') ? $result_arr[0][$i]["arbroutename"] : $result_arr[0][$i]["routename"];
            $salesman = ($this->css == 'ar_') ? $result_arr[0][$i]["arbsalesmanname"] : $result_arr[0][$i]["salesman"];
            
            $column_model_arr[] = array($result_arr[0][$i]['routecode'].' - '.$routename.' ( '.$result_arr[0][$i]['salesmancode'].' - '.$salesman.' ) ',$result_arr[0][$i]['targetto_visit'],$result_arr[0][$i]['targetvisits'],$result_arr[0][$i]['callexceptions'],$result_arr[0][$i]['non_targetvisits'],
                                        $result_arr[0][$i]['totalvisits'], $result_arr[0][$i]['schedulesale'],$result_arr[0][$i]['unsched_sale'],$result_arr[0][$i]['schedulenosale'],$result_arr[0][$i]['unschedulenosale'],$result_arr[0][$i]['effectivevisit'],
                                        $result_arr[0][$i]['strikerate'],$result_arr[0][$i]['callcomp'],$result_arr[0][$i]['discipline'],$result_arr[0][$i]['startkms'],$result_arr[0][$i]['endkms'],$result_arr[0][$i]['kms_covered'],$result_arr[0][$i]['averagein_out_time']
                                        );
        }
        
        $data_arr["columns_model"]          = $column_model_arr;
        $data_arr["config"]["report_title"] = $this->translate->_("StrikeRate CallRate Discipline Length AvgTime").$report_title;
        $data_arr["config"]["report_title_height"] = $report_title_height;
        $data_arr["config"]["file_name"]    = "Strike_CallRate_Discipline_Length_AvgTime";
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
}
