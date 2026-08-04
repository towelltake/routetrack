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
class Reports_TabletreportController extends Reports_Library_Controller_Action_Abstract
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
        
        $this->sec_lang 	                        	= $this->view->sec_lang;
        $this->decimalplaces  = $this->view->decimalplaces	= $this->SFA_Comman->getdecimalplaces();
        $this->view->sec_lang	                        	= $this->SFA_Comman->getsecondlanguage();
        
        $this->report_session		= new Zend_Session_Namespace('Re_salessummary');
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
    public function tabletreportAction()
    {
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata  = $formdata = $this->_request->getPost();
        
        //$result_arr = $this->SFA_Comman->executequery('CALL sp_report_tablet_routeinventory_detail()','','');
        //
        //$this->view->route_list = $result_arr[0];
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
	 
        $this->view->ReportTitle = $this->translate->_("Route Inventory");
        $this->view->pageHeaderTitle  = $this->translate->_('Date');
        $this->view->pageHeadervalue  =  date("d-m-Y h:i:s");
        
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
                                                  "value" => $title_val)
                                            );
        $this->report_session->searchParams = $this->view->searchParams;
        
        $this->view->xlsexport_link = $this->view->baseUrl()."/reports/tabletreport/export";
        $this->view->cvsexport_link = $this->view->baseUrl()."/reports/tabletreport/exportcsv";
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
    public function tabletreportdataAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        $extra_where = "";
        
        if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
        {
            $extra_where .= ' AND sed.routecode IN ('.$this->report_session->routecode_str.')';
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
      
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_tablet_routeinventory(?,?,?,?,?)',$param_array,'');
      
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
        $total_beginqtycase = $total_beginqtypcs = $total_loadqtycase = $total_loadqtypcs = $total_loadaddqtycase = $total_loadaddqtypcs
        = $total_loadcutqtycase = $total_loadcutqtypcs = $total_salesqtycase = $total_salesqtypcs = $total_returnqtycase = $total_returnqtypcs
        = $total_damagedqtycase = $total_damagedqtypcs = $total_expiryqtycase = $total_expiryqtypcs = $total_freesampleqtycase = $total_freesampleqtypcs
        = $total_manualfreeqtycase = $total_manualfreeqtypcs = $total_endqtycase = $total_endqtypcs = $total_truckstockvalue = $total_endstockvalue = 0;
        
        $i=0;
        if(!empty($result_arr[1])){
            foreach($result_arr[1] as $row) {
                $total_beginqtycase += (int)$row['beginqtycase'];
                $total_beginqtypcs += (int)$row['beginqtypcs'];
                $total_loadqtycase += (int)$row['loadqtycase'];
                $total_loadqtypcs += (int)$row['loadqtypcs'];
                $total_loadaddqtycase += (int)$row['loadaddqtycase'];
                $total_loadaddqtypcs += (int)$row['loadaddqtypcs'];
                $total_loadcutqtycase += (int)$row['loadcutqtycase'];
                $total_loadcutqtypcs += (int)$row['loadcutqtypcs'];
                $total_salesqtycase += (int)$row['salesqtycase'];
                $total_salesqtypcs += (int)$row['salesqtypcs'];
                $total_returnqtycase += (int)$row['returnqtycase'];
                $total_returnqtypcs += (int)$row['returnqtypcs'];
                $total_damagedqtycase += (int)$row['damagedqtycase'];
                $total_damagedqtypcs += (int)$row['damagedqtypcs'];
                $total_expiryqtycase += (int)$row['expiryqtycase'];
                $total_expiryqtypcs += (int)$row['expiryqtypcs'];
                $total_freesampleqtycase += (int)$row['freesampleqtycase'];
                $total_freesampleqtypcs += (int)$row['freesampleqtypcs'];
                $total_manualfreeqtycase += (int)$row['manualfreeqtycase'];
                $total_manualfreeqtypcs += (int)$row['manualfreeqtypcs'];
                $total_endqtycase += (int)$row['endqtycase'];
                $total_endqtypcs += (int)$row['endqtypcs'];
                $total_truckstockvalue += $row['truckstockvalue'];
                $total_endstockvalue += $row['endstockvalue'];
                
                $responce->rows[$i]['id']=$i;
				
				$routename 			= ($this->css == 'ar_') ? $row['arbroutename'] 	    	: $row['routename'];
                $itemdescription	= ($this->css == 'ar_') ? $row['arbitemdescription'] 	: $row['itemdescription'];
				
                $responce->rows[$i]['cell']=array($row['routecode']." - ".$routename,$row['actualitemcode'],$itemdescription,
                                                  (int)$row['beginqtycase'],(int)$row['beginqtypcs'],(int)$row['loadqtycase'],(int)$row['loadqtypcs'],(int)$row['loadaddqtycase'],
                                                  (int)$row['loadaddqtypcs'],(int)$row['loadcutqtycase'],(int)$row['loadcutqtypcs'],(int)$row['salesqtycase'],(int)$row['salesqtypcs'],
                                                  (int)$row['returnqtycase'],(int)$row['returnqtypcs'],(int)$row['damagedqtycase'],(int)$row['damagedqtypcs'],(int)$row['expiryqtycase'],
                                                  (int)$row['expiryqtypcs'],(int)$row['freesampleqtycase'],(int)$row['freesampleqtypcs'],(int)$row['manualfreeqtycase'],
                                                  (int)$row['manualfreeqtypcs'],(int)$row['endqtycase'],(int)$row['endqtypcs'],$row['truckstockvalue'],$row['endstockvalue']);
                $i++;
              
            }
        }
        else
        {
        //  $responce->rows[$i]['id']=1;
          //  $responce->rows[$i]['cell']=array("","","","No Record Founds","", "");
        }
        $responce->userdata['itemdescription'] = $this->translate->_("Total");
        $responce->userdata['beginqtycase'] = $total_beginqtycase;
        $responce->userdata['beginqtypcs'] = $total_beginqtypcs;
        $responce->userdata['loadqtycase'] = $total_loadqtycase;
        $responce->userdata['loadqtypcs'] = $total_loadqtypcs;
        $responce->userdata['loadaddqtycase'] = $total_loadaddqtycase;
        $responce->userdata['loadaddqtypcs'] = $total_loadaddqtypcs;
        $responce->userdata['loadcutqtycase'] = $total_loadcutqtycase;
        $responce->userdata['loadcutqtypcs'] = $total_loadcutqtypcs;
        $responce->userdata['salesqtycase'] = $total_salesqtycase;
        $responce->userdata['salesqtypcs'] = $total_salesqtypcs;
        $responce->userdata['returnqtycase'] = $total_returnqtycase;
        $responce->userdata['returnqtypcs'] = $total_returnqtypcs;
        $responce->userdata['damagedqtycase'] = $total_damagedqtycase;
        $responce->userdata['damagedqtypcs'] = $total_damagedqtypcs;
        $responce->userdata['expiryqtycase'] = $total_expiryqtycase;
        $responce->userdata['expiryqtypcs'] = $total_expiryqtypcs;
        $responce->userdata['freesampleqtycase'] = $total_freesampleqtycase;
        $responce->userdata['freesampleqtypcs'] = $total_freesampleqtypcs;
        $responce->userdata['manualfreeqtycase'] = $total_manualfreeqtycase;
        $responce->userdata['manualfreeqtypcs'] = $total_manualfreeqtypcs;
        $responce->userdata['endqtycase'] = $total_endqtycase;
        $responce->userdata['endqtypcs'] = $total_endqtypcs;
        $responce->userdata['truckstockvalue'] = $total_truckstockvalue;
        $responce->userdata['endstockvalue'] = $total_endstockvalue;
        
        echo json_encode($responce);
        exit;
    }
      
       /**
      * @name       exportAction
      * @since      15-02-2012
      * @version    Release: 1
      * @author     GP <gayatri@elantechnologies.com>
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
        
        if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
        {
            $extra_where .= ' AND sed.routecode IN ('.$this->report_session->routecode_str.')';
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
        $param_array[2] = ($sidx == "actualitemcode") ? "routecode,".$sidx : "routecode,actualitemcode,".$sidx;
        $param_array[3] = $sord;
        $param_array[4] = $limit;
        $param_array[5] = $page;
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_tablet_routeinventory(?,?,?,?,?)',$param_array,'');
        
        $report_title_height = 15;
        FOR($i=0;$i<COUNT($this->report_session->searchParams);$i++)
        {
            IF($this->report_session->searchParams[$i]["value"] != "")
            {
                IF($this->css == "ar_") {
                    $report_title .= "\r ".$this->report_session->searchParams[$i]["value"]." : ".$this->translate->_($this->report_session->searchParams[$i]["title"]);
                } ELSE {
                    $report_title .= "\r ".$this->translate->_($this->report_session->searchParams[$i]["title"]) . " : ".$this->report_session->searchParams[$i]["value"];
                }
                $report_title_height += 10;
            }
        }
        
        $data = $result_arr[0];
        $data_arr = array();
        
        $column_model_arr = array();
        $data_arr["columns"] = array($this->translate->_('Route'),$this->translate->_('Item'),$this->translate->_('Description'),$this->translate->_('Opening Case'),$this->translate->_('Opening Pcs'),$this->translate->_('Load Case'),$this->translate->_('Load Pcs'),$this->translate->_('Tran.IN Case'),$this->translate->_('Tran.IN Pcs'),$this->translate->_('Tran.OUT Case'),$this->translate->_('Tran.OUT Pcs'),$this->translate->_('Sales Case'),$this->translate->_('Sales Pcs'),$this->translate->_('G.Return Case'),$this->translate->_('G.Return Pcs'),$this->translate->_('Damaged Case'),$this->translate->_('Damaged Pcs'),$this->translate->_('Expiry Case'),$this->translate->_('Expiry Pcs'),$this->translate->_('Promo Case'),$this->translate->_('Promo Pcs'),$this->translate->_('Free Case'),$this->translate->_('Free Pcs'),$this->translate->_('Closing Case'),$this->translate->_('Closing Pcs'),$this->translate->_('Load Value'),$this->translate->_('Closing Value'));
        $data_arr["columns_config"] =   array(
                                            array("width"=>12),
                                            array("width"=>12),
                                            array("width"=>35,"toaltext"=>$this->translate->_('Total'),"group_total_text"=>$this->translate->_('Group Total')),
                                            array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>10,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1")
                                        );
        
        for($i = 0; $i < count($result_arr[0]); $i++)
        {
			$routename 			= ($this->css == 'ar_') ? $result_arr[0][$i]['arbroutename'] 	    : $result_arr[0][$i]['routename'];
            $itemdescription	= ($this->css == 'ar_') ? $result_arr[0][$i]['arbitemdescription'] 	: $result_arr[0][$i]['itemdescription'];
			
            $column_model_arr[$result_arr[0][$i]['routecode']." - ".$routename][] = array($result_arr[0][$i]['actualitemcode'],$itemdescription,$result_arr[0][$i]['beginqtycase'],$result_arr[0][$i]['beginqtypcs'],$result_arr[0][$i]['loadqtycase'],$result_arr[0][$i]['loadqtypcs'],$result_arr[0][$i]['loadaddqtycase'],$result_arr[0][$i]['loadaddqtypcs'],$result_arr[0][$i]['loadcutqtycase'],$result_arr[0][$i]['loadcutqtypcs'],$result_arr[0][$i]['salesqtycase'],$result_arr[0][$i]['salesqtypcs'],$result_arr[0][$i]['returnqtycase'],$result_arr[0][$i]['returnqtypcs'],$result_arr[0][$i]['damagedqtycase'],$result_arr[0][$i]['damagedqtypcs'],$result_arr[0][$i]['expiryqtycase'],$result_arr[0][$i]['expiryqtypcs'],$result_arr[0][$i]['freesampleqtycase'],$result_arr[0][$i]['freesampleqtypcs'],$result_arr[0][$i]['manualfreeqtycase'],$result_arr[0][$i]['manualfreeqtypcs'],$result_arr[0][$i]['endqtycase'],$result_arr[0][$i]['endqtypcs'],$result_arr[0][$i]['truckstockvalue'],$result_arr[0][$i]['endstockvalue']);
        }
        
        $data_arr["columns_model"]          = $column_model_arr;
        $data_arr["config"]["report_title"] = $this->translate->_('Route Inventory').$report_title;
        $data_arr["config"]["report_title_height"] = $report_title_height;
        $data_arr["config"]["file_name"]    = "RouteInventory";
        $data_arr["config"]["group_level"]  = 1;
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
