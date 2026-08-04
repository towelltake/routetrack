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
class Reports_DataitemdistributionController extends Reports_Library_Controller_Action_Abstract
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
        
        $this->report_session		= new Zend_Session_Namespace('Re_itemdistribution');
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

    public function itemdistributionAction()
    {
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata  = $formdata = $this->_request->getPost();
	 
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_data_item_distribution_detail()','','');
	   
        $this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/ajaxdata/useraccessgrid");
        $this->view->item_list = $result_arr[1];
        $this->view->majorcat_list = $result_arr[2];
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
        
        $this->view->ReportTitle = $this->translate->_("Item Distribution");
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
                                            array("title"=> "Customer Type",
                                                  "value" => $formdata['ddlcustomertype_selected']),
                                            array("title"=> "Customer",
                                                  "value" => $formdata['ddlcustomer_selected']),
                                            array("title"=> "Start Date",
                                                  "value" => ($formdata['txt_route_start_date'] != "" ) ? date("d M Y",strtotime($formdata['txt_route_start_date'])) : ""),
                                            array("title"=> "End Date",
                                                  "value" => ($formdata['txt_route_end_date'] != "" ) ? date("d M Y",strtotime($formdata['txt_route_end_date'])) : "")
                                            );
        $this->report_session->searchParams = $this->view->searchParams;
        
        $this->view->xlsexport_link = $this->view->baseUrl()."/reports/dataitemdistribution/export";
        $this->view->cvsexport_link = $this->view->baseUrl()."/reports/dataitemdistribution/exportcsv";
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
    public function itemdisAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        $extra_where = "";
        
        if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
        {
            $extra_where .= ' AND ih.routecode IN ('.$this->report_session->routecode_str.')';
        }
        
        if($this->report_session->post['ddlcategory'] != "" )
        {
            $extra_where  .= " and mc.majorcategorycode = ".$this->report_session->post['ddlcategory'];	    
        }
        if($this->report_session->post['ddlitem'] != "" )
        {
            $extra_where  .= " and im.actualitemcode = ".$this->report_session->post['ddlitem'];	    
        }
        if($this->report_session->post['ddlcustomertype'] == 3 )
        {
            if($this->report_session->post['ddlcustomer'] !='')
                $extra_where  .= " and cm.headofficecode = ".$this->report_session->post['ddlcustomer']." and cm.type in (2,3)";
            else
                $extra_where  .= " and cm.type in (2,3)";
        }
        else
        {
            if($this->report_session->post['ddlcustomer'] !='')
                $extra_where  .= " and cm.customercode=".$this->report_session->post['ddlcustomer']."";
            else
                $extra_where  .= " and cm.customercode >0";
            if($this->report_session->post['ddlcustomertype'] !='')
                $extra_where  .= " and cm.type in (".$this->report_session->post['ddlcustomertype'].")";
        }
        
        if($this->report_session->post['txt_route_start_date'] != "" && $this->report_session->post['txt_route_end_date'] != "")
        {
            $extra_where  .= ' AND ih.actualtransactiondate BETWEEN "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'" AND "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
        }
        elseif($this->report_session->post['txt_route_start_date'] != "" && $this->report_session->post['txt_route_end_date'] == "")
        {
            $extra_where  .= ' AND ih.actualtransactiondate >= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'"';
        }
        elseif($this->report_session->post['txt_route_start_date'] == "" && $this->report_session->post['txt_route_end_date'] != "")
        {
            $extra_where  .= ' AND ih.actualtransactiondate <= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
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
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_data_item_distribution(?,?,?,?,?)',$param_array,'');
        //pr($result_arr,1);
        $count  = $result_arr[0][0]['counter'];
        
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
        $salesqty = $freesampleqty = $damagedqty = $expiryqty = $returnqty = $salesvalue = $returnvalue =
        $expiredvalue = $freevalue = $itempromodiscount = $invdiscountbreakup = $final_netamount = 0;
        $i=0;
        if(!empty($result_arr[1])){
            foreach($result_arr[1] as $row) {
                $netamount=$row['salesvalue']+$row['returnvalue']+$row['damagevalue']+$row['expiredvalue']+$row['freevalue']+$row['itempromodiscount']+$row['invdiscountbreakup'];
                
                $salesqty += $row['salesqty'];
                $freesampleqty += $row['freesampleqty'];
                $damagedqty += $row['damagedqty'];
                $expiryqty += $row['expiryqty'];
                $returnqty += $row['returnqty'];
                $salesvalue += $row['salesvalue'];
                $returnvalue += $row['returnvalue'];
                $expiredvalue += $row['expiredvalue'];
                $freevalue += $row['freevalue'];
                $itempromodiscount += $row['itempromodiscount'];
                $invdiscountbreakup += $row['invdiscountbreakup'];
                $final_netamount += $netamount;
                
                $routename = ($this->css == 'ar_') ? $row['arbroutename'] : $row['routename'];
                $customername = ($this->css == 'ar_') ? $row['arbcustomername'] : $row['customername'];
                $itemdescription = ($this->css == 'ar_') ? $row['arbitemdescription'] : $row['itemdescription'];
                $majorcategory = ($this->css == 'ar_') ? $row['arbdescription'] : $row['majorcategory'];
                $categoryname = ($this->css == 'ar_') ? $row['arbcategoryname'] : $row['categoryname'];
                
                $responce->rows[$i]['id']=$i;
                $responce->rows[$i]['cell']=array($row['routecode']." - ".$routename,$row['customercategory']." - ".$categoryname,$row['majorcategorycode']." - ".$majorcategory,
                                                  $row['customercode']." - ".$customername,$row['itemcode'],$itemdescription,$row['salesqty'],$row['returnqty'],$row['damagedqty'],
                                                  $row['expiryqty'],$row['freesampleqty'],$row['salesvalue'],$row['returnvalue'],$row['damagevalue'],$row['expiredvalue'],$row['freevalue'],
                                                  $row['itempromodiscount'],$row['invdiscountbreakup'],$netamount);
                $i++;
            }
        }
        else
        {
        //  $responce->rows[$i]['id']=1;
	    //  $responce->rows[$i]['cell']=array("","","","No Record Founds","", "");
        }
        
        $responce->userdata['itemdescription'] = $this->translate->_("Total");
        $responce->userdata['salesqty'] = $salesqty;
        $responce->userdata['freesampleqty'] = $freesampleqty;
        $responce->userdata['damagedqty'] = $damagedqty;
        $responce->userdata['expiryqty'] = $expiryqty;
        $responce->userdata['returnqty'] = $returnqty;
        $responce->userdata['salesvalue'] = $salesvalue;
        $responce->userdata['returnvalue'] = $returnvalue;
        $responce->userdata['expiredvalue'] = $expiredvalue;
        $responce->userdata['freevalue'] = $freevalue;
        $responce->userdata['itempromodiscount'] = $itempromodiscount;
        $responce->userdata['invdiscountbreakup'] = $invdiscountbreakup;
        $responce->userdata['netvalue'] = $final_netamount;
        
        echo json_encode($responce);
        exit;
    }
      
      
       /**
    * @name       exportAction
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
        /*This code commented and new code added by nilesh on 04Apr2016 for reports filter*/
        /*if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
        {
            $extra_where .= ' AND ih.routecode IN ('.$this->report_session->routecode_str.')';
        }
        
        if($this->report_session->post['ddlcategory'] != "" )
        {
            $extra_where  .= " and mc.majorcategorycode = ".$this->report_session->post['ddlcategory'];	    
        }
        if($this->report_session->post['ddlitem'] != "" )
        {
            $extra_where  .= " and im.actualitemcode = ".$this->report_session->post['ddlitem'];	    
        }
        if($this->report_session->post['ddlcustomertype'] == 3 )
        {
            if($this->report_session->post['ddlcustomer'] !='')
                $extra_where  = " and cm.headofficecode = ".$this->report_session->post['ddlcustomer']." and cm.type in (2,3)";
            else
                $extra_where  = " and cm.type in (2,3)";
        }
        else
        {
            if($this->report_session->post['ddlcustomer'] !='')
                $extra_where  = " and cm.customercode=".$this->report_session->post['ddlcustomer']."";
            else
                $extra_where  = " and cm.customercode >0";
            if($this->report_session->post['ddlcustomertype'] !='')
                $extra_where  .= " and cm.type in (".$this->report_session->post['ddlcustomertype'].")";
        }
        
        if($this->report_session->post['txt_route_start_date'] != "" && $this->report_session->post['txt_route_end_date'] != "")
        {
            $extra_where  .= ' AND ih.actualtransactiondate BETWEEN "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'" AND "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
        }
        elseif($this->report_session->post['txt_route_start_date'] != "" && $this->report_session->post['txt_route_end_date'] == "")
        {
            $extra_where  .= ' AND ih.actualtransactiondate >= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'"';
        }
        elseif($this->report_session->post['txt_route_start_date'] == "" && $this->report_session->post['txt_route_end_date'] != "")
        {
            $extra_where  .= ' AND ih.actualtransactiondate <= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
        }
        */
		if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
        {
            $extra_where .= ' AND ih.routecode IN ('.$this->report_session->routecode_str.')';
        }
        
        if($this->report_session->post['ddlcategory'] != "" )
        {
            $extra_where  .= " and mc.majorcategorycode = ".$this->report_session->post['ddlcategory'];	    
        }
        if($this->report_session->post['ddlitem'] != "" )
        {
            $extra_where  .= " and im.actualitemcode = ".$this->report_session->post['ddlitem'];	    
        }
        if($this->report_session->post['ddlcustomertype'] == 3 )
        {
            if($this->report_session->post['ddlcustomer'] !='')
                $extra_where  .= " and cm.headofficecode = ".$this->report_session->post['ddlcustomer']." and cm.type in (2,3)";
            else
                $extra_where  .= " and cm.type in (2,3)";
        }
        else
        {
            if($this->report_session->post['ddlcustomer'] !='')
                $extra_where  .= " and cm.customercode=".$this->report_session->post['ddlcustomer']."";
            else
                $extra_where  .= " and cm.customercode >0";
            if($this->report_session->post['ddlcustomertype'] !='')
                $extra_where  .= " and cm.type in (".$this->report_session->post['ddlcustomertype'].")";
        }
        
        if($this->report_session->post['txt_route_start_date'] != "" && $this->report_session->post['txt_route_end_date'] != "")
        {
            $extra_where  .= ' AND ih.actualtransactiondate BETWEEN "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'" AND "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
        }
        elseif($this->report_session->post['txt_route_start_date'] != "" && $this->report_session->post['txt_route_end_date'] == "")
        {
            $extra_where  .= ' AND ih.actualtransactiondate >= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'"';
        }
        elseif($this->report_session->post['txt_route_start_date'] == "" && $this->report_session->post['txt_route_end_date'] != "")
        {
            $extra_where  .= ' AND ih.actualtransactiondate <= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
        }
		/*end code added by nilesh*/
		
		
		
        $page = $_GET['page']; // get the requested page
        $limit = $_GET['rows']; // get how many rows we want to have into the grid
        $sidx = $_GET['sidx']; // get index row - i.e. user click to sort
        $sord = $_GET['sord']; // get the direction
        if(!$sidx) $sidx =1;
        
        if(empty($sidx)) {  $sidx  = "routecode";}
        if(empty($sord)) {  $sord  = "asc";}
        
        $param_array = array();
        $param_array[1] = $extra_where;
        $param_array[2] = "routecode,customercategory,majorcategorycode,customercode,".$sidx;
        $param_array[3] = $sord;
        $param_array[4] = $limit;
        $param_array[5] = $page;
	  
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_data_item_distribution(?,?,?,?,?)',$param_array,'');
        
        $report_title_height = 20;
        for($i=0;$i<count($this->report_session->searchParams);$i++)
        {
            if($this->report_session->searchParams[$i]["value"] != "")
            {
                if($this->css == 'ar_') {
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
        $data_arr["columns"] = array($this->translate->_('Route'),$this->translate->_('Customer Category'),$this->translate->_('Major Category'),$this->translate->_('Customer'),$this->translate->_('Item Code'),$this->translate->_('Item Description'),$this->translate->_('Sales Qty'),$this->translate->_('Return Qty'),$this->translate->_('Damaged Qty'),$this->translate->_('Expired Qty'),$this->translate->_('Free Qty'),$this->translate->_('Sales Value'),$this->translate->_('Return Value'),$this->translate->_('Damage Value'),$this->translate->_('Expired Value'),$this->translate->_('Free Value'),$this->translate->_('Item Discount'),$this->translate->_('Invoice Discounts'),$this->translate->_('Net Value'));
        $data_arr["columns_config"] =   array(
                                            array("width"=>12),
                                            array("width"=>12),
                                            array("width"=>12),
                                            array("width"=>12),
                                            array("width"=>15),
                                            array("width"=>35,"toaltext"=>$this->translate->_('Total'),"group_total_text"=>$this->translate->_('Group Total')),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1")
                                        );
        for($i = 0; $i < count($result_arr[0]); $i++)
        {
            $routename = ($this->css == 'ar_') ? $result_arr[0][$i]['arbroutename'] : $result_arr[0][$i]['routename'];
            $customername = ($this->css == 'ar_') ? $result_arr[0][$i]['arbcustomername'] : $result_arr[0][$i]['customername'];
            $itemdescription = ($this->css == 'ar_') ? $result_arr[0][$i]['arbitemdescription'] : $result_arr[0][$i]['itemdescription'];
            $majorcategory = ($this->css == 'ar_') ? $result_arr[0][$i]['arbdescription'] : $result_arr[0][$i]['majorcategory'];
            $categoryname = ($this->css == 'ar_') ? $result_arr[0][$i]['arbcategoryname'] : $result_arr[0][$i]['categoryname'];
            
            
            $netamount=$result_arr[0][$i]['salesvalue']+$result_arr[0][$i]['returnvalue']+$result_arr[0][$i]['damagevalue']+$result_arr[0][$i]['expiredvalue']+$result_arr[0][$i]['freevalue']+$result_arr[0][$i]['itempromodiscount']+$result_arr[0][$i]['invdiscountbreakup'];
            
            $column_model_arr[$result_arr[0][$i]['routecode']." - ".$routename][$result_arr[0][$i]['customercategory']." - ".$categoryname][$result_arr[0][$i]['majorcategorycode']." - ".$majorcategory][$result_arr[0][$i]['customercode']." - ".$customername][]
                    = array($result_arr[0][$i]['itemcode'],$itemdescription,$result_arr[0][$i]['salesqty'],$result_arr[0][$i]['returnqty'],$result_arr[0][$i]['damagedqty'],
                            $result_arr[0][$i]['expiryqty'],$result_arr[0][$i]['freesampleqty'],$result_arr[0][$i]['salesvalue'],$result_arr[0][$i]['returnvalue'],$result_arr[0][$i]['damagevalue'],
                            $result_arr[0][$i]['expiredvalue'],$result_arr[0][$i]['freevalue'],$result_arr[0][$i]['itempromodiscount'],$result_arr[0][$i]['invdiscountbreakup'],$netamount);
        }
        
        $data_arr["columns_model"]          = $column_model_arr;
        $data_arr["config"]["report_title"] = $this->translate->_("Item Distribution").$report_title;
        $data_arr["config"]["report_title_height"] = $report_title_height;
        $data_arr["config"]["file_name"]    = "Item_Distribution_Report";
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
