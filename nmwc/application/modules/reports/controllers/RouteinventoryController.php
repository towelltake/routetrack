<?php
/**
* @name       RouteinventoryController
* @since      05-10-2012
* @version    Release: 1
* @author     PT <pankil@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage report module.
*/
class Reports_RouteinventoryController extends Reports_Library_Controller_Action_Abstract
{
    /**
    * @name       init
    * @since      05-10-2012
    * @version    Release: 1
    * @author     PT <Pankil@elantechnologies.com>
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
        
        $this->report_session = new Zend_Session_Namespace('Re_routeinventory');
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
    * @name       routeinventoryAction
    * @since      05-10-2012
    * @version    Release: 1
    * @author     PT <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display discount summary
    *
    */
    public function routeinventoryAction()
    {
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata  = $formdata = $this->_request->getPost();
        
        //$result_arr = $this->SFA_Comman->executequery('CALL sp_report_dailyreport_routeinventory_detail()','','');
        //
        //$this->view->route_list = $result_arr[0];
        
        $this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/ajaxdata/useraccessgrid");
        
        $this->report_session->post = array();
   }


    /**
    * @name       indexAction
    * @since      05-10-2012
    * @version    Release: 1
    * @author     PT <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for 
    *
    */
    public function indexAction()
    {
        $this->_helper->layout->setLayout('jqreport');
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata  = $formdata = $this->_request->getPost();
	 
        $this->report_session->post = $formdata;
        
        $this->view->ReportTitle = $this->translate->_("Route Inventory");
        $this->view->pageHeaderTitle  = $this->translate->_('Date');
        $this->view->pageHeadervalue  =  date("m/d/Y h:i:s");
        
        $this->view->xlsexport_link = $this->view->baseUrl()."/reports/routeinventory/export";
        $this->view->cvsexport_link = $this->view->baseUrl()."/reports/routeinventory/exportcsv";
        
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
                    
                    for($i=0;$i<count($result_arr[1]);$i++)
                    {
                        $routecode_arr[] = $result_arr[1][$i]["routecode"];
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
                                                  "value" => ($formdata['txt_route_start_date'] != "" ) ? date("d M Y",strtotime($formdata['txt_route_start_date'])) : "")
                                            );
        $this->report_session->searchParams = $this->view->searchParams;
    }
    /**
      * @name       routeinventorydataAction
      * @since      05-10-2012
      * @version    Release: 1
      * @author     PT <pankil@elantechnologies.com>
      * @copyright  Elan Technologies
      * @param
      *
      * This action fetch customer pending request data
      *
      */
    public function routeinventorydataAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        $extra_where = "";
        
        if($this->report_session->post['txt_route_start_date'] != "" )
        {
            $extra_where  .= ' AND  DATE_FORMAT(sed.routeenddate,"%Y-%m-%d")  = "'.date('Y-m-d',strtotime($this->report_session->post['txt_route_start_date'])).'"';
        }
        if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
            $extra_where .= ' AND sed.routecode IN ('.$this->report_session->routecode_str.')';
                
      
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
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_dailyreport_routeinventory(?,?,?,?,?)',$param_array,'');
        
     
	$count  = $result_arr[0][0]['in_counter'];
	
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
        //$totalsalesamount = $totalreturnamount = $totaldamagedamount = $totalexpiryamount = $totalfreesampleamount = 0;
       /* $total_beginqtycase = $total_beginqtypcs = $total_loadqtycase = $total_loadqtypcs = $total_loadaddqtycase = $total_loadaddqtypcs
        = $total_loadcutqtycase = $total_loadcutqtypcs = $total_salesqtycase = $total_salesqtypcs = $total_returnqtycase = $total_returnqtypcs
        = $total_damagedqtycase = $total_damagedqtypcs = $total_expiryqtycase = $total_expiryqtypcs = $total_freesampleqtycase = $total_freesampleqtypcs
        = $total_manualfreeqtycase = $total_manualfreeqtypcs = $total_endqtycase = $total_endqtypcs = $total_truckstockvalue = $total_endstockvalue = 0;*/
        
        if(!empty($result_arr[2])){
            foreach($result_arr[2] as $row) {
                $total_beginqtycase = $row['beginstock'];
              //  $total_beginqtypcs += $row['beginstock'];
              //  $total_loadqtycase += $row['loadqty'];
                $total_loadqtypcs = $row['loadstock'];
              //  $total_loadaddqtycase += $row['loadaddqty'];
                $total_loadaddqtypcs = $row['loadaddstock'];
              //  $total_loadcutqtycase += $row['loadcutqty'];
                $total_loadcutqtypcs = $row['loadcutstock'];
                //$total_salesqtycase += $row['salesqty'];
                $total_salesqtypcs = $row['salesstock'];
               // $total_returnqtycase += $row['returnqty'];
                $total_returnqtypcs = $row['returnqstock'];
              //  $total_damagedqtycase += $row['damagedqty'];
                $total_damagedqtypcs = $row['damagedstock'];
             //   $total_expiryqtycase += $row['expiryqtycase'];
              //  $total_expiryqtypcs += $row['expiryqtypcs'];
               // $total_freesampleqtycase += $row['freeqty'];
                $total_freesampleqtypcs = $row['freestock'];
                /*$total_manualfreeqtycase += $row['manualfreeqtycase'];//Need to Remove
                $total_manualfreeqtypcs += $row['manualfreeqtypcs'];//Need to Remove*/
				 $total_damagevarqty = $row['damagevariancestock'];
                $total_damagevarval += $row['damagevariancevalue'];
              //  $total_endqtycase += $row['endqty'];
                $total_endqtypcs = $row['endstock'];
                $total_truckstockvalue += $row['truckstockvalue'];
                $total_endstockvalue += $row['endstockvalue'];
                
                $routename = ($this->session->lang == "ar_AR") ? $row['arbroutename'] : $row['routename'];
                $salesmanname = ($this->session->lang == "ar_AR") ? $row['arbsalesmanname1'] : $row['salesman'];
                $majorcategory = ($this->session->lang == "ar_AR") ? $row['arbmajorcatdes'] : $row['majorcategroy'];
                $itemdescription = ($this->session->lang == "ar_AR") ? $row['arbitemdescription'] : $row['itemdescription'];
                
                $responce->rows[$i]['id'] = $i;
                $responce->rows[$i]['cell'] = array($row['routecode']." - ".$routename,$row['majorcategorycode']." - ".$majorcategory,$row['actualitemcode'],$itemdescription,
                                                    /*$row['beginstockqty'],*/$row['beginstock'],/*$row['loadqty'],*/$row['loadstock'],/*$row['loadaddqty'],*/$row['loadaddstock'],
                                                    /*$row['loadcutqty'],*/$row['loadcutstock'],/*$row['salesqty'],*/$row['salesstock'],/*$row['returnqty'],*/$row['returnqstock'],
                                                   /* $row['damagedqty'],*/$row['damagedstock'],/*$row['expiryqtycase'],$row['expiryqtypcs'],*//*$row['freeqty'],*/$row['freestock'],
                                                    $row['damagevariancestock'],$row['damagevariancevalue'],/*$row['endqty'],*/$row['endstock'],$row['truckstockvalue'],$row['endstockvalue']
                                                );
                $i++;
            }
        }
        else
        {
          //  $responce->rows[$i]['id']=1;
          //  $responce->rows[$i]['cell']=array("","","","No Record Founds","", "");
        }
        $responce->userdata['itemdescription'] = $this->translate->_("Total");
        $responce->userdata['beginqtycase'] = "";//$total_beginqtycase;
     
        $responce->userdata['loadqtypcs'] = "";//$total_loadqtypcs;
     
        $responce->userdata['loadaddqtypcs'] = "";//$total_loadaddqtypcs;
     
        $responce->userdata['loadcutqtypcs'] = "";//$total_loadcutqtypcs;
      //  $responce->userdata['salesqtycase'] = $total_salesqtycase;
        $responce->userdata['salesqtypcs'] = "";//$total_salesqtypcs;
		
       // $responce->userdata['returnqtycase'] = $total_returnqtycase;
        $responce->userdata['returnqtypcs'] = "";//$total_returnqtypcs;
      //  $responce->userdata['damagedqtycase'] = $total_damagedqtycase;
	  
        $responce->userdata['damagedqtypcs'] = "";//$total_damagedqtypcs;
     //   $responce->userdata['freesampleqtycase'] = $total_freesampleqtycase;
        $responce->userdata['freesampleqtypcs'] = "";//$total_freesampleqtypcs;
        $responce->userdata['damagevarianceqty'] = "";//$total_damagevarqty;
        $responce->userdata['damagevariance'] = $total_damagevarval;
     //   $responce->userdata['endqtycase'] = $total_endqtycase;
        $responce->userdata['endqtypcs'] = "";//$total_endqtypcs;
        $responce->userdata['truckstockvalue'] = $total_truckstockvalue;
        $responce->userdata['endstockvalue'] = $total_endstockvalue;
        
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
        $report_title = "";
        if($this->report_session->post['txt_route_start_date'] != "")
        {
            $extra_where .= ' AND DATE_FORMAT(sed.routeenddate,"%Y-%m-%d") = "'.date('Y-m-d',strtotime($this->report_session->post['txt_route_start_date'])).'"';
            //$report_title .= "Route Start Date : ".date('d M Y',strtotime($this->report_session->post['txt_route_start_date']));
        }
        if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
            $extra_where .= ' AND sed.routecode IN ('.$this->report_session->routecode_str.')';
        
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
        
        $page = $_GET['page']; // get the requested page
        $limit = $_GET['rows']; // get how many rows we want to have into the grid
        $sidx = $_GET['sidx']; // get index row - i.e. user click to sort
        $sord = $_GET['sord']; // get the direction
		
        if(!$sidx) $sidx =1;
        
        if(empty($sidx)) {  $sidx  = "routecode";}
        if(empty($sord)) {  $sord  = "asc";}
    
        $param_array = array();
        $param_array[1] = $extra_where;
        $param_array[2] = "routecode,majorcategorycode,".$sidx;
        $param_array[3] = $sord;
        $param_array[4] = $limit;
        $param_array[5] = $page;
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_dailyreport_routeinventory(?,?,?,?,?)',$param_array,'');
        
        $data = $result_arr[1];
		
        $data_arr = array();
        
        $column_model_arr = array();
        $data_arr["columns"] = array($this->translate->_('Route'),$this->translate->_('Group'),$this->translate->_('Item Code'),$this->translate->_('Item Description'),$this->translate->_('Opening Case/Pcs'),$this->translate->_('Load Case/Pcs'),$this->translate->_('Transfer IN Case/Pcs'),$this->translate->_('Transfer OUT Case/Pcs'),$this->translate->_('Sales Case/Pcs'),$this->translate->_('Good Return Case/Pcs'),$this->translate->_('Bad Return Case/Pcs'),$this->translate->_('Free Case/Pcs'),$this->translate->_('Damage Varience Qty./Pcs'),$this->translate->_('Damage Varience Value'),$this->translate->_('Closing Case/Pcs'),$this->translate->_('Load Value'),$this->translate->_('Closing Value'));
        $data_arr["columns_config"] = array(array("width"=>13),
                                            array("width"=>13),
                                            array("width"=>15),
                                            array("width"=>35,"toaltext"=>$this->translate->_('Total'),"group_total_text"=>$this->translate->_('Group Total')),
                                         //   array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10),
                                          //  array("width"=>9,"total"=>"1","group_total"=>"1"),
                                            array("width"=>8),
                                           // array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10),
                                           // array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10),
                                            //array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>9),
                                           // array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10),
                                           // array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10),
                                           // array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10),
                                            array("width"=>10),
                                            array("width"=>10,"total"=>"1","group_total"=>"1"),
                                           // array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10),
                                            array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10,"total"=>"1","group_total"=>"1")
                                          /* array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10,"total"=>"1","group_total"=>"1")*/
                                        );
        
        for($i = 0; $i < count($result_arr[1]); $i++)
        {
            $routename = ($this->session->lang == "ar_AR") ? $result_arr[1][$i]['arbroutename'] : $result_arr[1][$i]['routename'];
            $salesmanname = ($this->session->lang == "ar_AR") ? $result_arr[1][$i]['arbsalesmanname1'] : $result_arr[1][$i]['salesman'];
            $majorcategory = ($this->session->lang == "ar_AR") ? $result_arr[1][$i]['arbmajorcatdes'] : $result_arr[1][$i]['majorcategroy'];
            $itemdescription = ($this->session->lang == "ar_AR") ? $result_arr[1][$i]['arbitemdescription'] : $result_arr[1][$i]['itemdescription'];
            
            $column_model_arr[$result_arr[1][$i]['routecode']." - ".$routename][$result_arr[1][$i]['majorcategorycode']." - ".$majorcategory][]
                = array($result_arr[1][$i]['actualitemcode'],$itemdescription,$result_arr[1][$i]['beginstock']
                        ,$result_arr[1][$i]['loadstock'],$result_arr[1][$i]['loadaddstock'],
                                                    $result_arr[1][$i]['loadcutstock'],$result_arr[1][$i]['salesstock'],$result_arr[1][$i]['returnqstock'],
                                                    $result_arr[1][$i]['damagedstock'],$result_arr[1][$i]['freestock'],
                                                    $result_arr[1][$i]['damagevariancestock'],$result_arr[1][$i]['damagevariancevalue'],$result_arr[1][$i]['endstock'],$result_arr[1][$i]['truckstockvalue'],$result_arr[1][$i]['endstockvalue']
                        );
        }
        
        $data_arr["columns_model"]          = $column_model_arr;
        $data_arr["config"]["report_title"] = $this->translate->_("Route Inventory").$report_title;
        $data_arr["config"]["report_title_height"] =30;
        $data_arr["config"]["file_name"]    = "RouteInventorySummary";
        $data_arr["config"]["group_level"]  = 2;
        $data_arr["config"]["total_columns"]= count($data_arr["columns"]);
        $data_arr["config"]["group_total"]  = "1";
        $data_arr["config"]["main_total"]   = "1";
        
        //pr($data_arr,1);
        
        $SFA_Exportxls = new SFA_Exportxls($data_arr);
        $objPHPExcel = $SFA_Exportxls->exportxls();
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        exit;
    }
}
