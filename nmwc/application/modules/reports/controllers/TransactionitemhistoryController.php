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
class Reports_TransactionitemhistoryController extends Reports_Library_Controller_Action_Abstract
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
		$this->SFA_Comman	= new SFA_Comman();
		$this->view->colan	= $this->translate->_('Colan');
		$this->css 			= $this->translate->_('CSS');
		$this->view->css	= $this->css;
		
		$this->currentUser = SFA_Loginauth::getIdentity();	
		if(!isset($this->currentUser) || empty($this->currentUser))
		{
			SFA_Message::setMsg($this->translate->_('Do Login'));
			//$this->_helper->redirector("index", "index", "home");
			$url = $this->view->baseUrl();
			echo '<script type="text/javascript">window.location="'.$url.'";</script>';
			exit;
		}
		
		$this->sec_lang 		= $this->view->sec_lang;
		$this->decimalplaces  	= $this->view->decimalplaces	= $this->SFA_Comman->getdecimalplaces();
		$this->view->sec_lang	= $this->SFA_Comman->getsecondlanguage();
		
		$this->report_session	= new Zend_Session_Namespace('Re_itemhistory');
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
    public function itemhistoryAction()
    {
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata  = $formdata = $this->_request->getPost();
	 
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_transaction_itemhistory_detail()','','');
        //$this->view->route_list = $result_arr[0];
        $this->view->item_list = $result_arr[1];
        $this->view->majorcat_list = $result_arr[2];
	
        $this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/ajaxdata/useraccessgrid");
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
        
        $this->view->ReportTitle = $this->translate->_("Item History Summary");
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
                                           array("title"=> "Items",
                                                 "value" => $formdata['ddlitem_selected']),
                                           array("title"=> "Major Category",
                                                 "value" => $formdata['ddlcategory_selected']),
                                           array("title"=> "Start Date",
                                                 "value" => ($formdata['txt_route_start_date'] != "" ) ? date("d M Y",strtotime($formdata['txt_route_start_date'])) : ""),
                                           array("title"=> "End Date",
                                                 "value" => ($formdata['txt_route_end_date'] != "" ) ? date("d M Y",strtotime($formdata['txt_route_end_date'])) : "")
                                           );
        $this->report_session->searchParams = $this->view->searchParams;
        
        $this->view->xlsexport_link = $this->view->baseUrl()."/reports/transactionitemhistory/export";
        $this->view->cvsexport_link = $this->view->baseUrl()."/reports/transactionitemhistory/exportcsv";
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
    public function itemhisAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        $extra_where = "";
        
        
        if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
        {
            $extra_where .= ' AND sed.routecode IN ('.$this->report_session->routecode_str.')';
        }
        if($this->report_session->post['ddlcategory'] != "" )
        {
            $extra_where  .= " and smc.majorcategorycode = ".$this->report_session->post['ddlcategory'];	    
        }
        if($this->report_session->post['ddlitem'] != "" )
        {
            $extra_where  .= " and im.actualitemcode = ".$this->report_session->post['ddlitem'];	    
        }
        if($this->report_session->post['txt_route_start_date'] != "" && $this->report_session->post['txt_route_end_date'] != "")
        {
            $extra_where  .= ' AND sed.routeenddate BETWEEN "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'" AND "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
        }
        elseif($this->report_session->post['txt_route_start_date'] != "" && $this->report_session->post['txt_route_end_date'] == "")
        {
            $extra_where  .= ' AND sed.routeenddate >= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'"';
        }
        elseif($this->report_session->post['txt_route_start_date'] == "" && $this->report_session->post['txt_route_end_date'] != "")
        {
            $extra_where  .= ' AND sed.routeenddate <= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
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
       
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_transaction_itemhistory(?,?,?,?,?)',$param_array,'');
        
		$count  = $result_arr[0][0]['in_counter'];
	 
	    //$count  = count($result_arr[2]);
        // $count  = !empty($result_arr[0]) ? count($result_arr[0]) : 0;
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
       
        if(!empty($result_arr[2])){
            foreach($result_arr[2] as $row) {
			
                $total_beginqtypcs += (int)$row['openingqty'];
             
                $total_loadqtypcs += (int)$row['loadqty'];
             
                $total_loadaddqtypcs += (int)$row['transferinqty'];
             
                $total_loadcutqtypcs += (int)$row['transferoutqty'];
           
                $total_salesqtypcs += (int)$row['saleqty'];
            
                $total_returnqtypcs += (int)$row['retqty'];
             
                $total_damagedqtypcs += (int)$row['dmgqty'];
            
                $total_freesampleqtypcs += (int)$row['freeqty'];
               $total_damagevarqty += (int)$row['damagevariancestock'];
                $total_damagevarval += (int)$row['damagevariancevalue'];
              
                $total_endqtypcs += (int)$row['vanstockqty'];
                $total_beginstockvalue += $row['beginstockvalue'];
                $total_loadvalue += $row['loadvalue'];
                $total_truckstockvalue += $row['vanstockvalue'];
                $total_endstockvalue += $row['vanstockvalue'];
                
				
				$routename 			= ($this->css == 'ar_') ? $row['arbroutename'] 	    	: $row['routename'];
                $itemdescription	= ($this->css == 'ar_') ? $row['arbitemdescription'] 	: $row['itemdescription'];
				
				
                $responce->rows[$i]['id'] = $i;
                $responce->rows[$i]['cell'] = array($row['routestartdate']." - ".$row['routeenddate'],$row['routecode']." - ".$routename,$row['majorcategorycode']." - ".$row['majorcategroy'],$row['itemcode']." - ".$itemdescription,$row['openingqty'],$row['loadqty'],$row['transferinqty'],
				$row['transferoutqty'],$row['saleqty'],$row['retqty'],$row['dmgqty'],
				$row['freeqty'],$row['damagevariancestock'],$row['damagevariancevalue'],$row['vanstockqty'],$row['openingvalue'],$row['loadvalue'],$row['vanstockvalue'],$row['vanstockvalue']);
                $i++;
              
            }
			
        }
        else
        {
      
        }
        $responce->userdata['routeenddate'] = $this->translate->_("Total");
    
        $responce->userdata['beginqtypcs'] = "";//$total_beginqtypcs;
       
        $responce->userdata['loadqtypcs'] = "";//$total_loadqtypcs;
     
        $responce->userdata['loadaddqtypcs'] = "";//$total_loadaddqtypcs;
     
        $responce->userdata['loadcutqtypcs'] = "";//$total_loadcutqtypcs;
    
        $responce->userdata['salesqtypcs'] = "";//$total_salesqtypcs;
     
        $responce->userdata['returnqtypcs'] = "";//$total_returnqtypcs;
    
        $responce->userdata['damagedqtypcs'] = "";//$total_damagedqtypcs;      
    
        $responce->userdata['freesampleqtypcs'] = "";//$total_freesampleqtypcs;
        $responce->userdata['damagevarqty'] = "";// $total_damagevarqty;
        $responce->userdata['damagevarval'] = $total_damagevarval;
    
        $responce->userdata['endqtypcs'] = "";//$total_endqtypcs;
        $responce->userdata['beginstockvalue'] = $total_beginstockvalue;
        $responce->userdata['loadvalue'] = $total_loadvalue;
        $responce->userdata['truckstockvalue'] = $total_truckstockvalue;
        $responce->userdata['endstockvalue'] = $total_endstockvalue;
        
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
        $extra_where = "";
        
        
        if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
        {
            $extra_where .= ' AND sed.routecode IN ('.$this->report_session->routecode_str.')';
        }
        if($this->report_session->post['ddlcategory'] != "" )
        {
            $extra_where  .= " and smc.majorcategorycode = ".$this->report_session->post['ddlcategory'];	    
        }
        if($this->report_session->post['ddlitem'] != "" )
        {
            $extra_where  .= " and im.actualitemcode = ".$this->report_session->post['ddlitem'];	    
        }
        if($this->report_session->post['txt_route_start_date'] != "" && $this->report_session->post['txt_route_end_date'] != "")
        {
            $extra_where  .= ' AND sed.routeenddate BETWEEN "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'" AND "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
        }
        elseif($this->report_session->post['txt_route_start_date'] != "" && $this->report_session->post['txt_route_end_date'] == "")
        {
            $extra_where  .= ' AND sed.routeenddate >= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'"';
        }
        elseif($this->report_session->post['txt_route_start_date'] == "" && $this->report_session->post['txt_route_end_date'] != "")
        {
            $extra_where  .= ' AND sed.routeenddate <= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
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
        $param_array[2] = "routecode,majorcategorycode,itemcode,".$sidx;
        $param_array[3] = $sord;
        $param_array[4] = $limit;
        $param_array[5] = $page;
       
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_transaction_itemhistory(?,?,?,?,?)',$param_array,'');
        
        $report_title_height = 15;
        for($i=0;$i<COUNT($this->report_session->searchParams);$i++)
        {
            if($this->report_session->searchParams[$i]["value"] != "")
            {
                if($this->css == "ar_") {
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
        $data_arr["columns"] = array($this->translate->_('Trip Start Date - Trip End Date '),$this->translate->_('Route'),$this->translate->_('Group'),$this->translate->_('Item Code'),$this->translate->_('Opening Case/Unit'),$this->translate->_('Load Case/Unit'),$this->translate->_('Transfer IN Case/Unit'),$this->translate->_('Transfer OUT Case/Unit'),$this->translate->_('Sales Case/Unit'),$this->translate->_('Good Return Case/Unit'),$this->translate->_('Bad Return Case/Unit'),$this->translate->_('Free Case/Unit'),$this->translate->_('Damage Varience Case/Unit'),$this->translate->_('Damage Varience Value'),$this->translate->_('Closing Case/Unit'),$this->translate->_('Opening Stock Value'),$this->translate->_('Daily Loaded Value'),$this->translate->_('Truck Stock Value'),$this->translate->_('Closing Value'));
        $data_arr["columns_config"] =   array(
                                            array("width"=>30),
                                            array("width"=>18),
                                            array("width"=>18),
                                            array("width"=>30,"toaltext"=>$this->translate->_('Total'),"group_total_text"=>$this->translate->_('Group Total')),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>13,"total"=>"1","group_total"=>"1"),
                                            array("width"=>12,"total"=>"1","group_total"=>"1"),
                                            array("width"=>12,"total"=>"1","group_total"=>"1"),
                                            array("width"=>12,"total"=>"1","group_total"=>"1"),
                                            array("width"=>12,"total"=>"1","group_total"=>"1"),
                                            array("width"=>12,"total"=>"1","group_total"=>"1"),
                                            array("width"=>12,"total"=>"1","group_total"=>"1"),
                                            array("width"=>12,"total"=>"1","group_total"=>"1"),
                                            array("width"=>12,"total"=>"1","group_total"=>"1"),
                                            array("width"=>13,"total"=>"1","group_total"=>"1"),
                                            array("width"=>12,"total"=>"1","group_total"=>"1"),
                                            array("width"=>12,"total"=>"1","group_total"=>"1"),
                                            array("width"=>12,"total"=>"1","group_total"=>"1")
                                          
                                        );
        
        for($i = 0; $i < count($result_arr[1]); $i++)
        {
			$routename 			= ($this->css == 'ar_') ? $result_arr[1][$i]['arbroutename'] 	    : $result_arr[1][$i]['routename'];
            $itemdescription	= ($this->css == 'ar_') ? $result_arr[1][$i]['arbitemdescription'] 	: $result_arr[1][$i]['itemdescription'];
			
            $column_model_arr[$result_arr[1][$i]['routestartdate']." - ".$result_arr[1][$i]['routeenddate']][$result_arr[1][$i]['routecode']." - ".$routename][$result_arr[1][$i]['majorcategorycode']." - ".$result_arr[1][$i]['majorcategroy']][$result_arr[1][$i]['itemcode']." - ".$itemdescription][] = 
			array($result_arr[1][$i]['openingqty'],$result_arr[1][$i]['loadqty'],$result_arr[1][$i]['transferinqty'],
			$result_arr[1][$i]['transferoutqty'],$result_arr[1][$i]['saleqty'],$result_arr[1][$i]['retqty'],$result_arr[1][$i]['dmgqty'],
			$result_arr[1][$i]['freeqty'],$result_arr[1][$i]['damagevariancestock'],$result_arr[1][$i]['damagevariancevalue'],$result_arr[1][$i]['vanstockqty'],$result_arr[1][$i]['openingvalue'],$result_arr[1][$i]['loadvalue'],$result_arr[1][$i]['vanstockvalue'],$result_arr[1][$i]['vanstockvalue']);
        }
        //pr($column_model_arr,1);
        $data_arr["columns_model"]          = $column_model_arr;
        $data_arr["config"]["report_title"] = $this->translate->_("Item History Summary").$report_title;
        $data_arr["config"]["report_title_height"] = $report_title_height;
        $data_arr["config"]["file_name"]    = "ItemHistory";
        $data_arr["config"]["group_level"]  = 4;
        $data_arr["config"]["total_columns"]= count($data_arr["columns"]);
        $data_arr["config"]["group_total"]  = "1";
        $data_arr["config"]["main_total"]   = "1";
        
        
        $SFA_Exportxls = new SFA_Exportxls($data_arr);
        $objPHPExcel = $SFA_Exportxls->exportxls();
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        exit;
    }
}
