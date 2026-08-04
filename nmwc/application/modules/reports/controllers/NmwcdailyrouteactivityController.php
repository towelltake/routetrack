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
class Reports_NmwcdailyrouteactivityController extends Reports_Library_Controller_Action_Abstract
{
     /**
    * @name       init
    * @since      15-02-2012
    * @version    Release: 1
    * @author     M@M <miral@elantechnologies.com>
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
        
        $this->report_session = new Zend_Session_Namespace('Re_dailyrouteactivity');
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
    * @name       dailyrouteactivityAction
    * @since      04-10-2012
    * @version    Release: 1
    * @author     PT <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display daily sales sheet report
    *
    */
    public function dailyrouteactivityAction()
    {
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata  = $formdata = $this->_request->getPost();
        
        //$result_arr = $this->SFA_Comman->executequery('CALL sp_report_dailyreport_dailyrouteactivity_detail()','','');
        
        //$this->view->route_list = $result_arr[0];
        $this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/ajaxdata/useraccessgrid");
        
        $this->report_session->post = array();
   }


    /**
    * @name       totalsalesbyhierarchyAction
    * @since      04-10-2012
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
        
        $this->view->ReportTitle = $this->translate->_("NMWC Daily Route Activity");
        $this->view->pageHeaderTitle  = $this->translate->_('Date');
        $this->view->pageHeadervalue  =  date("m/d/Y h:i:s");
        
        $this->view->xlsexport_link = $this->view->baseUrl()."/reports/nmwcdailyrouteactivity/export";
        $this->view->cvsexport_link = $this->view->baseUrl()."/reports/nmwcdailyrouteactivity/exportcsv";
        
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
    public function dailyrouteactivitydataAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        $extra_where = "";
        
        if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
        {
            $extra_where .= ' AND rm.routecode IN ('.$this->report_session->routecode_str.')';
        }  
        if($this->report_session->post['txt_route_end_date'] != "" )
        {
            $extra_where  .= ' AND  DATE_FORMAT(sed.routeenddate,"%Y-%m-%d")  = "'.date('Y-m-d',strtotime($this->report_session->post['txt_route_end_date'])).'"';
        }
      
        $page = $_GET['page']; // get the requested page
        $limit = $_GET['rows']; // get how many rows we want to have into the grid
        $sidx = $_GET['sidx']; // get index row - i.e. user click to sort
        $sord = $_GET['sord']; // get the direction
        if(!$sidx) $sidx =1;
        
        if(empty($sidx)) {  $sidx  = "routecode";}
        if(empty($sord)) {  $sord  = "asc";}
    
        $param_array = array();
        $param_array[1] = $extra_where;
        $param_array[2] = $sidx;
        $param_array[3] = $sord;
        $param_array[4] = $limit;
        $param_array[5] = $page;
        
        //if(isset($this->report_session->reportdata) && !empty($this->report_session->reportdata))
        //{
        //    $result_arr = $this->report_session->reportdata;
        //}
        //else
        //{
        //    $this->report_session->reportdata = array();
            $result_arr = $this->SFA_Comman->executequery('CALL sp_report_dailyreport_dailyrouteactivity_edit(?,?,?,?,?)',$param_array,'');
        //    $this->report_session->reportdata = $result_arr;
        //}
        
        $count  = !empty($result_arr[0]) ? count($result_arr[0]) : 0;
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
        $total_tranamount = $total_salePaid = $total_receiptPaid = 0;
        if(!empty($result_arr[0])) {
            for($j = $start; $j < count($result_arr[0]); $j++,$i++)
            {
                $address="";$customer="";
                $total_salePaid += $result_arr[0][$j]['tranamount'];
                $total_receiptPaid += $result_arr[0][$j]['receiptPaid'];
                
                $customer = ($result_arr[0][$j]['customername'] != "") ? $result_arr[0][$j]['customercode'].'-'.$result_arr[0][$j]['customername'] : $result_arr[0][$j]['customercode'];
                $address .= $result_arr[0][$j]['customeraddress1'];
                if($result_arr[0][$j]['customeraddress2'] != "") {
                    $address .= ($result_arr[0][$j]['customeraddress1'] != "") ? " - " : "";
                    $address .= $result_arr[0][$j]['customeraddress2'];
                } elseif($result_arr[0][$j]['customeraddress3'] != "") {
                    $address .= ($result_arr[0][$j]['customeraddress2'] != "") ? " - " : "";
                    $address .= " - ".$result_arr[0][$j]['customeraddress3'];
                }
                
                $routename = ($this->session->lang == "ar_AR") ? $result_arr[0][$j]['arbroutename'] : $result_arr[0][$j]['routename'];
                $salesmanname = ($this->session->lang == "ar_AR") ? $result_arr[0][$j]['arbsalesmanname1'] : $result_arr[0][$j]['salesmanname1'];
				if($j==0)
					$visitintrvl=0;
				else
              //  $visitintrvl=$this->gettimediff($result_arr[0][$j]['visitstarttime'],$result_arr[0][$j-1]['visitendtime']);
			 $visitintrvl=$this->gettimediff($result_arr[0][$j-1]['visitendtime'],$result_arr[0][$j]['visitstarttime']);
                $responce->rows[$i]['id'] = $i;
                $responce->rows[$i]['cell'] = array($result_arr[0][$j]['trandate'],$result_arr[0][$j]['routecode'].'-'.$routename,$customer,$address,$result_arr[0][$j]['standardgps'],$result_arr[0][$j]['actualgps'],$result_arr[0][$j]['distance'],$result_arr[0][$j]['visitstarttime'],$result_arr[0][$j]['visitendtime'], $this->gettimediff($result_arr[0][$j]['visitstarttime'] , $result_arr[0][$j]['visitendtime']) ,$visitintrvl,$result_arr[0][$j]['trantype'],$result_arr[0][$j]['tranamount'],$result_arr[0][$j]['receiptPaid']);
            }
        }
        $responce->userdata['trantype'] = $this->translate->_("Total");
        $responce->userdata['tranamount'] = $total_salePaid;
        $responce->userdata['receiptPaid'] = $total_receiptPaid;
        
        echo json_encode($responce);
        exit;
    }
    
    /**
     * @name       gettimediff
     * @since      
     * @version    Release: 1
     * @author     PT <pankil@elantechnologies.com>
     * @copyright  Elan Technologies
     * @param
     *
     * This function is for getting timedifference
     *
     */
    function gettimediff($dtime,$atime) {
        $nextday=$dtime>$atime?1:0;
        $dep=explode(":",$dtime);
        $arr=explode(":",$atime);
        $diff=abs(mktime($dep[0],$dep[1],0,date("n"),date("j"),date("y"))-mktime($arr[0],$arr[1],0,date("n"),date("j")+$nextday,date("y")));
        $hours=floor($diff/(60*60));
        $mins=floor(($diff-($hours*60*60))/(60));
        $secs=floor(($diff-(($hours*60*60)+($mins*60))));
        if(strlen($hours)<2){$hours="0".$hours;}
        if(strlen($mins)<2){$mins="0".$mins;}
        if(strlen($secs)<2){$secs="0".$secs;}
        return $hours.":".$mins;
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
        //pr($params,1);
        if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
        {
            $extra_where .= ' AND rm.routecode IN ('.$this->report_session->routecode_str.')';
        }  
        if($this->report_session->post['txt_route_end_date'] != "" )
        {
            $extra_where  .= ' AND  DATE_FORMAT(sed.routeenddate,"%Y-%m-%d")  = "'.date('Y-m-d',strtotime($this->report_session->post['txt_route_end_date'])).'"';
        }
      
        $page = $_GET['page']; // get the requested page
        $limit = $_GET['rows']; // get how many rows we want to have into the grid
        $sidx = $_GET['sidx']; // get index row - i.e. user click to sort
        $sord = $_GET['sord']; // get the direction
        if(!$sidx) $sidx =1;
        
        if(empty($sidx)) {  $sidx  = "customercode";}
        if(empty($sord)) {  $sord  = "asc";}
    
        $param_array = array();
        $param_array[1] = $extra_where;
        $param_array[2] = "routecode,trandate,documentno,".$sidx;
        $param_array[3] = $sord;
        /*$param_array[4] = $limit;
        $param_array[5] = $page;*/
		$param_array[4] = 10000000;
        $param_array[5] = 1;
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_dailyreport_dailyrouteactivity_edit(?,?,?,?,?)',$param_array,'');
         
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
        }
        
        $data = $result_arr[0];
        $data_arr = array();
        
        $column_model_arr = array();
		
        $data_arr["columns"] = array($this->translate->_('Route'),$this->translate->_('Transaction Date'),$this->translate->_('Customer'),$this->translate->_('Address'),$this->translate->_('Standard GPS'),$this->translate->_('Actual GPS'),$this->translate->_('Difference(Meter)'),$this->translate->_('Visit Start Time'),$this->translate->_('Visit End Time'),$this->translate->_('Duration hh:mm'),$this->translate->_('Visit Interval Time'),$this->translate->_('Transaction'),$this->translate->_('Order Collected'),$this->translate->_('Receipt Collected'));
        $data_arr["columns_config"] =   array(array("width"=>12),
                                            array("width"=>15),
                                            array("width"=>17),
                                            array("width"=>45),
                                            array("width"=>13),
                                            array("width"=>13),
                                            array("width"=>13),
                                            array("width"=>13),
											array("width"=>13),
                                            array("width"=>20,"toaltext"=>$this->translate->_('Total')),
                                            array("width"=>13),
                                            array("width"=>13,"total"=>"1"),
                                            array("width"=>13,"total"=>"1")
                                        );
       //echo '<pre>',print_r($result_arr[0][0],1),'</pre>';exit;
        for($i = 0; $i < count($result_arr[0]); $i++)
        {
            $address="";$customer="";
            $customer = ($result_arr[0][$i]['customername'] != "") ? $result_arr[0][$i]['customercode'].'-'.$result_arr[0][$i]['customername'] : $result_arr[0][$i]['customercode'];
                $address .= $result_arr[0][$i]['customeraddress1'];
                if($result_arr[0][$i]['customeraddress2'] != "") {
                    $address .= ($result_arr[0][$i]['customeraddress1'] != "") ? " - " : "";
                    $address .= $result_arr[0][$i]['customeraddress2'];
                } elseif($result_arr[0][$i]['customeraddress3'] != "") {
                    $address .= ($result_arr[0][$i]['customeraddress2'] != "") ? " - " : "";
                    $address .= " - ".$result_arr[0][$i]['customeraddress3'];
                }
            
            $routename = ($this->session->lang == "ar_AR") ? $result_arr[0][$i]['arbroutename'] : $result_arr[0][$i]['routename'];
            $salesmanname = ($this->session->lang == "ar_AR") ? $result_arr[0][$i]['arbsalesmanname1'] : $result_arr[0][$i]['salesmanname1'];
              if($i==0)
					$visitintrvl=0;
				else
               $visitintrvl=$this->gettimediff($result_arr[0][$i-1]['visitendtime'],$result_arr[0][$i]['visitstarttime']);
            $column_model_arr[$result_arr[0][$i]['routecode'].'-'.$routename][$result_arr[0][$i]['trandate']][] = array($customer,$address,$result_arr[0][$i]['standardgps'],$result_arr[0][$i]['actualgps'],$result_arr[0][$i]['distance'],$result_arr[0][$i]['visitstarttime'],$result_arr[0][$i]['visitendtime'], $this->gettimediff($result_arr[0][$i]['visitstarttime'] , $result_arr[0][$i]['visitendtime']) , $visitintrvl,$result_arr[0][$i]['trantype'],$result_arr[0][$i]['tranamount'],$result_arr[0][$i]['receiptPaid']);
			
			
			// $responce->rows[$i]['cell'] = array($result_arr[0][$j]['trandate'],$result_arr[0][$j]['routecode'].'-'.$routename,$customer,$address,$result_arr[0][$j]['standardgps'],$result_arr[0][$j]['actualgps'],$result_arr[0][$j]['distance'],$result_arr[0][$j]['visitstarttime'],$result_arr[0][$j]['visitendtime'], $this->gettimediff($result_arr[0][$j]['visitstarttime'] , $result_arr[0][$j]['visitendtime']) ,$visitintrvl,$result_arr[0][$j]['trantype'],$result_arr[0][$j]['tranamount'],$result_arr[0][$j]['receiptPaid']);
			
			
			
            //$column_model_arr[$result_arr[0][$i]['routecode'].'-'.$routename][$result_arr[0][$i]['trandate']][] = array($result_arr[0][$j]['trandate'],$result_arr[0][$j]['routecode'].'-'.$routename,$customer,$address,$result_arr[0][$j]['trandate'],$result_arr[0][$j]['standardgps'],$result_arr[0][$j]['actualgps'],$result_arr[0][$j]['distance'],$result_arr[0][$j]['visitstarttime'],$result_arr[0][$i]['visitendtime'], $this->gettimediff($result_arr[0][$i]['visitstarttime'] , $result_arr[0][$i]['visitendtime']) ,$visitintrvl,$result_arr[0][$i]['trantype'],$result_arr[0][$i]['salePaid'],$result_arr[0][$i]['receiptPaid']);
        }
        
		
		
        $data_arr["columns_model"]          = $column_model_arr;
        $data_arr["config"]["report_title"] = $this->translate->_("NMWC Daily Route Activity").$report_title;
        $data_arr["config"]["report_title_height"] = 30;
        $data_arr["config"]["file_name"]    = "NMWCDailyRouteActivity";
        $data_arr["config"]["group_level"]  = 2;
        $data_arr["config"]["total_columns"]= count($data_arr["columns"]);
        $data_arr["config"]["group_total"]  = "0";
        $data_arr["config"]["main_total"]   = "1";
        
        
        $SFA_Exportxls = new SFA_Exportxls($data_arr);
        $objPHPExcel = $SFA_Exportxls->exportxls();
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        exit;
    }
}
