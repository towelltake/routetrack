<?php
/**
* @name       DistributionchecksummaryController
* @since      20-04-2015
* @version    Release: 1
* @author     CS <chetan@e2logy.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage report module.
*/
class Reports_DistributionchecksummaryController extends Reports_Library_Controller_Action_Abstract
{
     /**
    * @name       init
    * @since      20-04-2015
    * @version    Release: 6
    * @author     CS <chetan@e2logy.com>
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
            SFA_Message::setMsg($this->translate->_('Do Login'));
            //$this->_helper->redirector("index", "index", "home");
			$url = $this->view->baseUrl();
			echo '<script type="text/javascript">window.location="'.$url.'";</script>';
			exit;
        }
        
        $this->sec_lang 	    = $this->view->sec_lang;
        $this->decimalplaces    = $this->view->decimalplaces	= $this->SFA_Comman->getdecimalplaces();
        $this->view->sec_lang   = $this->SFA_Comman->getsecondlanguage();        
        $this->report_session   = new Zend_Session_Namespace('Re_distributionchecksummary');
    }
 
   /**
    * @name       preDispatch
    * @since      20-04-2015
    * @version    Release: 1
    * @author     CS <chetan@e2logy.com>
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
    public function distributionchecksummaryAction()
    {
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata  = $formdata = $this->_request->getPost();
        
        //$result_arr = $this->SFA_Comman->executequery('CALL sp_report_transaction_paymentsummary_detail()','','');
        //$this->view->route_list = $result_arr[0];
        $this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/ajaxdata/useraccessgrid");
        $this->report_session->post = array();
   }

    /**
    * @name       totalsalesbyhierarchyAction
    * @since      20-04-2015
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
        //var_dump($formdata);die;
        $this->report_session->post = $formdata;
        
        $this->view->ReportTitle = $this->translate->_("Merchandised Stock Summary");
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
                                            array("title"=> "Route Start Date",
                                                  "value" => ($formdata['txt_route_start_date'] != "" ) ? date("d M Y",strtotime($formdata['txt_route_start_date'])) : ""),
                                            array("title"=> "Cust off Qty",
                                                  "value" => ($formdata['chk_cut_of_qty'] == "on" ) ? $formdata['chk_cut_of_qty'] : "")
                                            );
        $this->report_session->searchParams = $this->view->searchParams;
        
        $this->view->xlsexport_link = $this->view->baseUrl()."/reports/distributionchecksummary/export";
        $this->view->cvsexport_link = $this->view->baseUrl()."/reports/distributionchecksummary/exportcsv";
    }
    /**
      * @name       custpendingbalAction
      * @since      20-04-2015
      * @version    Release: 1
      * @author     CS <chetan@e2logy.com>
      * @copyright  Elan Technologies
      * @param
      *
      * This action fetch customer pending request data
      *
      */
     public function distributioncheckdataAction()
     {
	  $this->view->params = $params = $this->getRequest()->getParams();
	  $this->view->formdata = $formdata = $this->_request->getPost();
	  $this->view->css 		= $this->translate->_('CSS');
	  $extra_where = "";
	  //print_r($this->report_session->routecode_str);die;
	  //echo '<pre>';print_r($this->report_session->post);die;
	  
	  if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
	  {
	      $extra_where .= ' AND coc.routecode IN ('.$this->report_session->routecode_str.')';
	  }
	  
	  if(isset($this->report_session->post['chk_cut_of_qty']) && $this->report_session->post['chk_cut_of_qty'] == "on" && $this->report_session->post['txt_cut_of_qty'] != '')
	  {
	       $extra_where .= ' AND cutoff_qty >= "'.$this->report_session->post['txt_cut_of_qty'].'"';
	  }
	  
	  if(isset($this->report_session->post['txt_route_start_date']) && $this->report_session->post['txt_route_start_date'] != "")
	  {
	      $extra_where  .= ' AND DATE_FORMAT(sed.routestartdate,"%Y-%m-%d") = "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'"';
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
	  //echo '<pre>';print_r($param_array);die;
	  
	  $result_arr = $this->SFA_Comman->executequery('CALL sp_report_distributioncheck_distributionchechsummary(?,?,?,?,?)',$param_array,'');
	  //echo '<pre>';print_r($result_arr);die;
	  $count  = $result_arr[0][0]['counter'];
	  //echo $count;die;
	  if( $count > 0 )
	  {
	      $total_pages = ceil($count/$limit);
	  }
	  else
	  {
	      $total_pages = 0;
	  }
	  if ($page > $total_pages) $page=$total_pages;
	  {
	      $start = $limit*$page - $limit; // do not put $limit*($page - 1)
	  }
	  
	  $responce->page = $page;
	  $responce->total = $total_pages;
	  $responce->records = $count;
	  $i=0;
	  $totalinvoiceamount = $total_immediatecash = $total_immediatecheck = $total_arcash = $total_archeck = $total_artotalpaid = 0;
	  if(!empty($result_arr[1])){
	       foreach($result_arr[1] as $row) {
		   
		    //$totalinvoiceamount += $row['totalinvoiceamount'];
		    //$total_immediatecash += $row['immediatecash'];
		    //$total_immediatecheck += $row['immediatecheck'];
		    //$total_arcash += $row['arcash'];
		    //$total_archeck += $row['archeck'];
		    //$total_artotalpaid += $row['artotalpaid'];
		    
		    $responce->rows[$i]['id']=$i;
		    
		    //$routename 		= ($this->css == 'ar_') ? $row['arbroutename'] 	    : $row['routename'];
		    //$customername	= ($this->css == 'ar_') ? $row['arbcustomername'] 	: $row['customername'];
		    //$honame     	= ($this->css == 'ar_') ? $row['arbhoname'] 	    : $row['honame'];
		    if($row['imagename'])
			$imageurl="<a href='".$this->view->baseUrl()."/public/customerimage/".$row['imagename']."' target='_blank'>Click Here</a>";
		else
			$imageurl="--";
		    //$responce->rows[$i]['cell'] = array($row['routecode']." - ".$routename,$row['hocode']." - ".$honame,$row['transactiondate'],$row['transactiontime'],$row['salesmancode'],$row['customercode'],$customername,$row['invoicenumber'],$row['mop'],$row['totalinvoiceamount'],$row['immediatecash'],$row['immediatecheck'],$row['arcash'],$row['archeck'],$row['artotalpaid']);
		    $responce->rows[$i]['cell'] = array($row['routecode'],$row['actualitemcode'],$row['itemdescription'],$row['customercode'],$row['customername'],$row['location1'],$imageurl);
		    $i++;
		 
	       }
	  }
	  else
	  {
	      //  $responce->rows[$i]['id']=1;
	      //  $responce->rows[$i]['cell']=array("","","","No Record Founds","", "");
	  }
	  
	  //$responce->userdata['mop'] = $this->translate->_('Total');
	  //$responce->userdata['totalinvoiceamount'] = $totalinvoiceamount;
	  //$responce->userdata['immediatecash'] = $total_immediatecash;
	  //$responce->userdata['immediatecheck'] = $total_immediatecheck;
	  //$responce->userdata['arcash'] = $total_arcash;
	  //$responce->userdata['archeck'] = $total_archeck;
	  //$responce->userdata['artotalpaid'] = $total_artotalpaid;
	  
	  echo json_encode($responce);
	  exit;
     }
      
      
     /**
     * @name       exportAction
     * @since      20-04-2015
     * @version    Release: 1
     * @author     CS <chetan@e2logy.com>
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
	      $extra_where .= ' AND coc.routecode IN ('.$this->report_session->routecode_str.')';
	  }
	  
	  if(isset($this->report_session->post['chk_cut_of_qty']) && $this->report_session->post['chk_cut_of_qty'] == "on" && $this->report_session->post['txt_cut_of_qty'] != '')
	  {
	       $extra_where .= ' AND cutoff_qty >= "'.$this->report_session->post['txt_cut_of_qty'].'"';
	  }
	  
	  if(isset($this->report_session->post['txt_route_start_date']) && $this->report_session->post['txt_route_start_date'] != "")
	  {
	      $extra_where  .= ' AND DATE_FORMAT(sed.routestartdate,"%Y-%m-%d") = "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'"';
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
	  
	  $result_arr = $this->SFA_Comman->executequery('CALL sp_report_distributioncheck_distributionchechsummary(?,?,?,?,?)',$param_array,'');
	  $report_title_height = 15;
	  FOR($i=0;$i<COUNT($this->report_session->searchParams);$i++)
	  {
	       IF($this->report_session->searchParams[$i]["value"] != "")
	       {
		    IF($this->css == "ar_")
		    {
			$report_title .= "\r ".$this->report_session->searchParams[$i]["value"]." : ".$this->translate->_($this->report_session->searchParams[$i]["title"]);
		    }
		    ELSE
		    {
			$report_title .= "\r ".$this->translate->_($this->report_session->searchParams[$i]["title"]) . " : ".$this->report_session->searchParams[$i]["value"];
		    }
		    $report_title_height += 10;
	       }
	  }
	  
	  $data = $result_arr[0];
	  //echo '<pre>';print_r($data);die;
	  $data_arr = array();
	  
	  $column_model_arr = array();
	  $data_arr["columns"] = array(
					$this->translate->_('Route'),
					$this->translate->_('Item Code'),
					$this->translate->_('Description'),
					$this->translate->_('Case Price'),
					$this->translate->_('Unit Price'),
					$this->translate->_('Cut-off'),
					$this->translate->_('Max Qty'),
					$this->translate->_('SHELF'),
					$this->translate->_('STORE'),
					$this->translate->_('Expiry Date')
				   );
	  $data_arr["columns_config"] =   array(
						  array("width"=>13),
						  array("width"=>13),
						  array("width"=>30),
						  array("width"=>15),
						  array("width"=>15),
						  array("width"=>15),
						  array("width"=>15),
						  array("width"=>15),
						  array("width"=>15),
					     );
	  
	  //echo '<pre>';print_r($result_arr[1]);die;
	  
	  for($i = 0; $i < count($result_arr[1]); $i++)
	  {
	       $column_model_arr[$result_arr[1][$i]['routecode']][] = array(//$result_arr[1][$i]['routecode'],
										$result_arr[1][$i]['actualitemcode'],
										$result_arr[1][$i]['itemdescription'],
										$result_arr[1][$i]['caseprice'],
										$result_arr[1][$i]['defaultsalesprice'],
										$result_arr[1][$i]['cutoff_qty'],
										$result_arr[1][$i]['max_qty'],
										$result_arr[1][$i]['location1'],
										$result_arr[1][$i]['location2'],
										$result_arr[1][$i]['expiry_date']
									   );
	  }
	  //var_dump($column_model_arr);die;
	  //pr($column_model_arr,1);
	  $data_arr["columns_model"]          = $column_model_arr;
	  $data_arr["config"]["report_title"] = $this->translate->_('Distribution Check Summary').$report_title;
	  $data_arr["config"]["report_title_height"] = $report_title_height;
	  $data_arr["config"]["file_name"]    = "DistributionCheckSummary";
	  $data_arr["config"]["group_level"]  = 1;
	  $data_arr["config"]["total_columns"]= count($data_arr["columns"]);
	  $data_arr["config"]["group_total"]  = "1";
	  $data_arr["config"]["main_total"]   = "1";
	  //echo '<pre>';print_r($data_arr);die;
	  
	  $SFA_Exportxls = new SFA_Exportxls($data_arr);
	  $objPHPExcel = $SFA_Exportxls->exportxls();
	  $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
	  $objWriter->save('php://output');
	  exit;
     }
}
