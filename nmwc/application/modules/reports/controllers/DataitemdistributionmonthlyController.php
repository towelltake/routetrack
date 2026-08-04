<?php
/**
* @name       AccountController
* @since      20-02-2012
* @version    Release: 1
* @author     PT <pankil@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage report module.
*/
class Reports_DataitemdistributionmonthlyController extends Reports_Library_Controller_Action_Abstract
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
        
        $this->report_session		= new Zend_Session_Namespace('Re_itemdistributionmonthly');
        $this->css 				= $this->translate->_('CSS');
		$this->view->css 		= $this->css;
    }

    /**
    * @name       itemdistributionAction
    * @since      15-02-2012
    * @version    Release: 1
    * @author     PT <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display daily sales sheet report
    *
    */
    public function itemdistributionAction()
    {
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata  = $formdata = $this->_request->getPost();
	 
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_data_item_distributionmonthly_detail()','','');
        
        $this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/ajaxdata/useraccessgrid");
        
        $this->view->item_list = $result_arr[1];
        $this->view->majorcat_list = $result_arr[2];
        $this->report_session->post = array();
   }

    /**
    * @name       totalsalesbyhierarchyAction
    * @since      15-02-2012
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
	 
        $this->view->ReportTitle = $this->translate->_("Item Distribution Monthly Comparison");
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
                                            array("title"=> "Route End Date - From",
                                                  "value" => ($formdata['txt_route_start_date'] != "" ) ? date("d M Y",strtotime($formdata['txt_route_start_date'])) : ""),
                                            array("title"=> "Route End Date - To",
                                                  "value" => ($formdata['txt_route_end_date'] != "" ) ? date("d M Y",strtotime($formdata['txt_route_end_date'])) : "")
                                            );
        $this->report_session->searchParams = $this->view->searchParams;
        
        $this->view->xlsexport_link = $this->view->baseUrl()."/reports/dataitemdistributionmonthly/export";
        $this->view->cvsexport_link = $this->view->baseUrl()."/reports/dataitemdistributionmonthly/exportcsv";
        
        
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
        
        $param_array = array();
        $param_array[1] = $extra_where;
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_data_item_distributionmonthly_columndata(?)',$param_array,'');
        
        $column = array($this->translate->_('Route (Salesman)'),$this->translate->_('Customer Category'),$this->translate->_('Group'),$this->translate->_('Customer'),$this->translate->_('Item Code'),$this->translate->_('Item Description'));
        $column_config = array('routecode','customercategory','majorcategorycode','customercode','itemcode','itemdescription');
        $header = array();
        
        for($i=0;$i<count($result_arr[0]);$i++)
        {
            $column = array_merge($column , array($this->translate->_('Sales Qty'),$this->translate->_('Return Qty'),$this->translate->_('Damage Qty'),$this->translate->_('Expiry Qty'),$this->translate->_('Free Qty'),$this->translate->_('Sales Value'),$this->translate->_('Return Value'),$this->translate->_('Damage Value'),$this->translate->_('Expired Value'),$this->translate->_('Free Value'),$this->translate->_('Discounts'),$this->translate->_('Net Value')));
            
            $postfix = '_'.$result_arr[0][$i]["monthno"].'_'.$result_arr[0][$i]["year"];
            
            $column_config = array_merge($column_config , array('salesqty'.$postfix,'returnqty'.$postfix,'damagedqty'.$postfix,'expiryqty'.$postfix,'freesampleqty'.$postfix,'salesvalue'.$postfix,'returnvalue'.$postfix,'damagevalue'.$postfix,'expiredvalue'.$postfix,'freevalue'.$postfix,'itempromodiscount'.$postfix,'netvalue'.$postfix));
            
            $header[] = array("title"=>$result_arr[0][$i]["monthtxt"]."-".$result_arr[0][$i]["year"],"column"=>'salesqty'.$postfix);
            
            $headerval[] =  array("month"=>$result_arr[0][$i]["monthno"],"year" =>$result_arr[0][$i]["year"]);
        }
        $this->report_session->count_header = count($header);
        $this->view->column = $column;
        $this->view->header = $header;
        $this->report_session->header = $headerval;
        $this->view->column_config = $column_config;
        $this->report_session->column_val = $column;
        $this->report_session->header_txt = $header;
    }
    /**
      * @name       itemdisAction
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
        $param_array[2] = " year asc,monthno asc, ".$sidx;
        $param_array[3] = $sord;
        $param_array[4] = $limit;
        $param_array[5] = $page;
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_data_item_distributionmonthly(?,?,?,?,?)',$param_array,'');
        
        $count  = $result_arr[0][0]['counter'];
        
        if( $count > 0 ) {
            $total_pages = ceil($count/$limit);
        } else {
            $total_pages = 0;
        }
        if ($page > $total_pages) $page=$total_pages;
            $start = $limit*$page - $limit; // do not put $limit*($page - 1)
        
        $responce->page = $page;
        $responce->total = $total_pages;
        $responce->records = $count;
        
        $i=0;$j=0;
        $rowindex = array();
        $total_salesqty = $total_returnqty = $total_damagedqty = $total_expiryqty = $total_freesampleqty = $total_salesvalue = $total_returnvalue = $total_damagevalue = $total_expiredvalue = $total_freevalue = $total_itempromodiscount = $total_netamount = array();
        if(!empty($result_arr[1])){
            foreach($result_arr[1] as $row) {
                
                $routename = ($this->css == 'ar_') ? $row['arbroutename'] : $row['routename'];
                $customername = ($this->css == 'ar_') ? $row['arbcustomername'] : $row['customername'];
                $salesman = ($this->css == 'ar_') ? $row['arbsalesmanname1'] : $row['salesmanname1'];
                $categoryname = ($this->css == 'ar_') ? $row['arbcategoryname'] : $row['categoryname'];
                $itemdescription = ($this->css == 'ar_') ? $row['arbitemdescription'] : $row['itemdescription'];
                $magorcategory = ($this->css == 'ar_') ? $row['arbdescription'] : $row['majorcategory'];
                
                if(isset($rowindex[$row['routecode']][$row['salesmancode']][$row['customercategory']][$row['majorcategorycode']][$row['customercode']][$row['itemcode']])) {
                    $index = $rowindex[$row['routecode']][$row['salesmancode']][$row['customercategory']][$row['majorcategorycode']][$row['customercode']][$row['itemcode']];
                    $netamount = $row['salesvalue']-($row['returnvalue']+$row['damagevalue']+$row['expiredvalue']+$row['itempromodiscount']);
                    $newrow[$index][$row['year']][$row['monthno']] = array("salesqty" => $row['salesqty'],"returnqty" => $row['returnqty'],"damagedqty" => $row['damagedqty'],"expiryqty" => $row['expiryqty'],"freesampleqty" => $row['freesampleqty'],"salesvalue" => $row['salesvalue'],"returnvalue" => $row['returnvalue'],"damagevalue" => $row['damagevalue'],"expiredvalue" => $row['expiredvalue'],"freevalue" => $row['freevalue'],"itempromodiscount" =>$row['itempromodiscount'],"netamount"=>$netamount);
                } else {
                    $rowindex[$row['routecode']][$row['salesmancode']][$row['customercategory']][$row['majorcategorycode']][$row['customercode']][$row['itemcode']] = $j;
                    //$newrow[$j] = array($row['routecode']." - ".$row['routename'] ." [ ".$row['salesmanname1']." ]",$row['customercategory']." - ".$row['categoryname'],$row['majorcategorycode']." - ".$row['majorcategory'],$row['customercode']." - ".$row['customername'],$row['itemcode'],$row['itemdescription']);
                    $newrow[$j] = array("routecode"=>$row['routecode']." - ".$routename ." ( ".$salesman." )","customercategory" => $row['customercategory']." - ".$categoryname,"majorcategorycode" => $row['majorcategorycode']." - ".$magorcategory,"customercode" => $row['customercode']." - ".$customername,"itemcode" => $row['itemcode'],"itemdescription" => $itemdescription);
                    $netamount = $row['salesvalue']-($row['returnvalue']+$row['damagevalue']+$row['expiredvalue']+$row['itempromodiscount']);
                    //$newrow[$j][$row['year']][$row['monthno']] = array($row['salesqty'],$row['returnqty'],$row['damagedqty'],$row['expiryqty'],$row['freesampleqty'],$row['salesvalue'],$row['returnvalue'],$row['damagevalue'],$row['expiredvalue'],$row['freevalue'],$row['itempromodiscount'],$netamount);
                    $newrow[$j][$row['year']][$row['monthno']] = array("salesqty" => $row['salesqty'],"returnqty" => $row['returnqty'],"damagedqty" => $row['damagedqty'],"expiryqty" => $row['expiryqty'],"freesampleqty" => $row['freesampleqty'],"salesvalue" => $row['salesvalue'],"returnvalue" => $row['returnvalue'],"damagevalue" => $row['damagevalue'],"expiredvalue" => $row['expiredvalue'],"freevalue" => $row['freevalue'],"itempromodiscount" =>$row['itempromodiscount'],"netamount"=>$netamount);
                    $j++;
                }
                $i++;
                //echo $row['monthno']."_".$row['year']."_".$row['salesqty'];exit;
                $total_salesqty[$row['monthno']."_".$row['year']] = (isset($total_salesqty[$row['monthno']."_".$row['year']]) && $total_salesqty[$row['monthno']."_".$row['year']]!= "") ? ($total_salesqty[$row['monthno']."_".$row['year']] + $row['salesqty']): $row['salesqty'];
                $total_returnqty[$row['monthno']."_".$row['year']] = (isset($total_returnqty[$row['monthno']."_".$row['year']]) && $total_returnqty[$row['monthno']."_".$row['year']]!= "") ? ($total_returnqty[$row['monthno']."_".$row['year']] + $row['returnqty']): $row['returnqty'];
                $total_damagedqty[$row['monthno']."_".$row['year']] = (isset($total_damagedqty[$row['monthno']."_".$row['year']]) && $total_damagedqty[$row['monthno']."_".$row['year']]!= "") ? ($total_damagedqty[$row['monthno']."_".$row['year']] + $row['damagedqty']): $row['damagedqty'];
                $total_expiryqty[$row['monthno']."_".$row['year']] = (isset($total_expiryqty[$row['monthno']."_".$row['year']]) && $total_expiryqty[$row['monthno']."_".$row['year']]!= "") ? ($total_expiryqty[$row['monthno']."_".$row['year']] + $row['expiryqty']): $row['expiryqty'];
                $total_freesampleqty[$row['monthno']."_".$row['year']] = (isset($total_freesampleqty[$row['monthno']."_".$row['year']]) && $total_freesampleqty[$row['monthno']."_".$row['year']]!= "") ? ($total_freesampleqty[$row['monthno']."_".$row['year']] + $row['freesampleqty']): $row['freesampleqty'];
                $total_salesvalue[$row['monthno']."_".$row['year']] = (isset($total_salesvalue[$row['monthno']."_".$row['year']]) && $total_salesvalue[$row['monthno']."_".$row['year']]!= "") ? ($total_salesvalue[$row['monthno']."_".$row['year']] + $row['salesvalue']): $row['salesvalue'];
                $total_returnvalue[$row['monthno']."_".$row['year']] = (isset($total_returnvalue[$row['monthno']."_".$row['year']]) && $total_returnvalue[$row['monthno']."_".$row['year']]!= "") ? ($total_returnvalue[$row['monthno']."_".$row['year']] + $row['returnvalue']): $row['returnvalue'];
                $total_damagevalue[$row['monthno']."_".$row['year']] = (isset($total_damagevalue[$row['monthno']."_".$row['year']]) && $total_damagevalue[$row['monthno']."_".$row['year']]!= "") ? ($total_damagevalue[$row['monthno']."_".$row['year']] + $row['damagevalue']): $row['damagevalue'];
                $total_expiredvalue[$row['monthno']."_".$row['year']] = (isset($total_expiredvalue[$row['monthno']."_".$row['year']]) && $total_expiredvalue[$row['monthno']."_".$row['year']]!= "") ? ($total_expiredvalue[$row['monthno']."_".$row['year']] + $row['expiredvalue']): $row['expiredvalue'];
                $total_freevalue[$row['monthno']."_".$row['year']] = (isset($total_salesqty[$row['monthno']."_".$row['year']]) && $total_salesqty[$row['monthno']."_".$row['year']]!= "") ? ($total_salesqty[$row['monthno']."_".$row['year']] + $row['freevalue']): $row['freevalue'];
                $total_itempromodiscount[$row['monthno']."_".$row['year']] = (isset($total_itempromodiscount[$row['monthno']."_".$row['year']]) && $total_itempromodiscount[$row['monthno']."_".$row['year']]!= "") ? ($total_itempromodiscount[$row['monthno']."_".$row['year']] + $row['itempromodiscount']): $row['itempromodiscount'];
                $total_netamount[$row['monthno']."_".$row['year']] = (isset($total_netamount[$row['monthno']."_".$row['year']]) && $total_netamount[$row['monthno']."_".$row['year']]!= "") ? ($total_netamount[$row['monthno']."_".$row['year']] + $netamount): $netamount;
                
            }
            
            for( $i = 0 ;$i < count($newrow);$i++)
            {
                $dataarr = array();
                $dataarr = array($newrow[$i]['routecode'],$newrow[$i]['customercategory'],$newrow[$i]['majorcategorycode'],$newrow[$i]['customercode'],$newrow[$i]['itemcode'],$newrow[$i]['itemdescription']);
                for($j=0;$j<count($this->report_session->header);$j++)
                {
                    if(isset($newrow[$i][$this->report_session->header[$j]['year']][$this->report_session->header[$j]['month']]) && !empty($newrow[$i][$this->report_session->header[$j]['year']][$this->report_session->header[$j]['month']]))
                    {
                        $dataarr1 = $newrow[$i][$this->report_session->header[$j]['year']][$this->report_session->header[$j]['month']];
                    }
                    else
                    {
                        $dataarr1 = array('salesqty'=>0,'returnqty'=>0,'damagedqty'=>0,'expiryqty'=>0,'freesampleqty'=>0,'salesvalue'=>0,'returnvalue'=>0,'damagevalue'=>0,'expiredvalue'=>0,'freevalue'=>0,'itempromodiscount'=>0,'netamount'=>0);
                    }
                    $partialarr = array($dataarr1['salesqty'],$dataarr1['returnqty'],$dataarr1['damagedqty'],$dataarr1['expiryqty'],$dataarr1['freesampleqty'],round($dataarr1['salesvalue'],2),round($dataarr1['returnvalue'],2),round($dataarr1['damagevalue'],2),round($dataarr1['expiredvalue'],2),round($dataarr1['freevalue'],2),round($dataarr1['itempromodiscount'],2),round($dataarr1['netamount'],2));
                    $dataarr = array_merge($dataarr,$partialarr);
                }
                $responce->rows[$i]['id']=$i;
                $responce->rows[$i]['cell']=$dataarr;
            }
            
        }
        else
        {
          //  $responce->rows[$i]['id']=1;
          //  $responce->rows[$i]['cell']=array("","","","No Record Founds","", "");
        }
        for($j=0;$j<count($this->report_session->header);$j++)
        {
            $responce->userdata['salesqty_'.$this->report_session->header[$j]['month']."_".$this->report_session->header[$j]['year']] = round($total_salesqty[$this->report_session->header[$j]['month']."_".$this->report_session->header[$j]['year']],2);
            $responce->userdata['returnqty_'.$this->report_session->header[$j]['month']."_".$this->report_session->header[$j]['year']] = round($total_returnqty[$this->report_session->header[$j]['month']."_".$this->report_session->header[$j]['year']],2);
            $responce->userdata['damagedqty_'.$this->report_session->header[$j]['month']."_".$this->report_session->header[$j]['year']] = round($total_damagedqty[$this->report_session->header[$j]['month']."_".$this->report_session->header[$j]['year']],2);
            $responce->userdata['expiryqty_'.$this->report_session->header[$j]['month']."_".$this->report_session->header[$j]['year']] = round($total_expiryqty[$this->report_session->header[$j]['month']."_".$this->report_session->header[$j]['year']],2);
            $responce->userdata['freesampleqty_'.$this->report_session->header[$j]['month']."_".$this->report_session->header[$j]['year']] = round($total_freesampleqty[$this->report_session->header[$j]['month']."_".$this->report_session->header[$j]['year']],2);
            $responce->userdata['salesvalue_'.$this->report_session->header[$j]['month']."_".$this->report_session->header[$j]['year']] = round($total_salesvalue[$this->report_session->header[$j]['month']."_".$this->report_session->header[$j]['year']],2);
            $responce->userdata['returnvalue_'.$this->report_session->header[$j]['month']."_".$this->report_session->header[$j]['year']] = round($total_returnvalue[$this->report_session->header[$j]['month']."_".$this->report_session->header[$j]['year']],2);
            $responce->userdata['damagevalue_'.$this->report_session->header[$j]['month']."_".$this->report_session->header[$j]['year']] = round($total_damagevalue[$this->report_session->header[$j]['month']."_".$this->report_session->header[$j]['year']],2);
            $responce->userdata['expiredvalue_'.$this->report_session->header[$j]['month']."_".$this->report_session->header[$j]['year']] = round($total_expiredvalue[$this->report_session->header[$j]['month']."_".$this->report_session->header[$j]['year']],2);
            $responce->userdata['freevalue_'.$this->report_session->header[$j]['month']."_".$this->report_session->header[$j]['year']] = round($total_freevalue[$this->report_session->header[$j]['month']."_".$this->report_session->header[$j]['year']],2);
            $responce->userdata['itempromodiscount_'.$this->report_session->header[$j]['month']."_".$this->report_session->header[$j]['year']] = round($total_itempromodiscount[$this->report_session->header[$j]['month']."_".$this->report_session->header[$j]['year']],2);
            $responce->userdata['netamount_'.$this->report_session->header[$j]['month']."_".$this->report_session->header[$j]['year']] = round($total_netamount[$this->report_session->header[$j]['month']."_".$this->report_session->header[$j]['year']],2);
        }
        $responce->userdata['itemdescription'] = $this->translate->_("Total");
        
        echo json_encode($responce);
        exit;
    }
    
    /**
      * @name       exportAction
      * @since      27-11-2012
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
        $param_array[2] = " year asc,monthno asc,routecode,customercategory,majorcategorycode,customercode, ".$sidx;
        $param_array[3] = $sord;
        $param_array[4] = $limit;
        $param_array[5] = $page;
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_data_item_distributionmonthly(?,?,?,?,?)',$param_array,'');
        
        $report_title_height = 25;
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
        $column_model_arr = array();
        $rowindex = array();
        $j = 0;
        if(!empty($result_arr[1])){
            foreach($result_arr[1] as $row) {
                
                $routename = ($this->css == 'ar_') ? $row['arbroutename'] : $row['routename'];
                $customername = ($this->css == 'ar_') ? $row['arbcustomername'] : $row['customername'];
                $salesman = ($this->css == 'ar_') ? $row['arbsalesmanname1'] : $row['salesmanname1'];
                $categoryname = ($this->css == 'ar_') ? $row['arbcategoryname'] : $row['categoryname'];
                $itemdescription = ($this->css == 'ar_') ? $row['arbitemdescription'] : $row['itemdescription'];
                $magorcategory = ($this->css == 'ar_') ? $row['arbdescription'] : $row['majorcategory'];
                
                if(isset($rowindex[$row['routecode']][$row['salesmancode']][$row['customercategory']][$row['majorcategorycode']][$row['customercode']][$row['itemcode']])) {
                    $index = $rowindex[$row['routecode']][$row['salesmancode']][$row['customercategory']][$row['majorcategorycode']][$row['customercode']][$row['itemcode']];
                    $netamount = $row['salesvalue']-($row['returnvalue']+$row['damagevalue']+$row['expiredvalue']+$row['itempromodiscount']);
                    $newrow[$index][$row['year']][$row['monthno']] = array("salesqty" => $row['salesqty'],"returnqty" => $row['returnqty'],"damagedqty" => $row['damagedqty'],"expiryqty" => $row['expiryqty'],"freesampleqty" => $row['freesampleqty'],"salesvalue" => $row['salesvalue'],"returnvalue" => $row['returnvalue'],"damagevalue" => $row['damagevalue'],"expiredvalue" => $row['expiredvalue'],"freevalue" => $row['freevalue'],"itempromodiscount" =>$row['itempromodiscount'],"netamount"=>$netamount);
                } else {
                    $rowindex[$row['routecode']][$row['salesmancode']][$row['customercategory']][$row['majorcategorycode']][$row['customercode']][$row['itemcode']] = $j;
                    $newrow[$j] = array("routecode"=>$row['routecode']." - ".$routename ." ( ".$salesman." )","customercategory" => $row['customercategory']." - ".$categoryname,"majorcategorycode" => $row['majorcategorycode']." - ".$magorcategory,"customercode" => $row['customercode']." - ".$customername,"itemcode" => $row['itemcode'],"itemdescription" => $itemdescription);
                    $netamount = $row['salesvalue']-($row['returnvalue']+$row['damagevalue']+$row['expiredvalue']+$row['itempromodiscount']);
                    $newrow[$j][$row['year']][$row['monthno']] = array("salesqty" => $row['salesqty'],"returnqty" => $row['returnqty'],"damagedqty" => $row['damagedqty'],"expiryqty" => $row['expiryqty'],"freesampleqty" => $row['freesampleqty'],"salesvalue" => $row['salesvalue'],"returnvalue" => $row['returnvalue'],"damagevalue" => $row['damagevalue'],"expiredvalue" => $row['expiredvalue'],"freevalue" => $row['freevalue'],"itempromodiscount" =>$row['itempromodiscount'],"netamount"=>$netamount);
                    $j++;
                }
                $i++;
            }
            
            $dataarr = array();
            for( $i = 0 ;$i < count($newrow);$i++)
            {
                $temp_arr = array();
                $temp_arr = array($newrow[$i]['itemcode'],$newrow[$i]['itemdescription']);
                for($j=0;$j<count($this->report_session->header);$j++)
                {
                    if(isset($newrow[$i][$this->report_session->header[$j]['year']][$this->report_session->header[$j]['month']]) && !empty($newrow[$i][$this->report_session->header[$j]['year']][$this->report_session->header[$j]['month']]))
                    {
                        $dataarr1 = $newrow[$i][$this->report_session->header[$j]['year']][$this->report_session->header[$j]['month']];
                    }
                    else
                    {
                        $dataarr1 = array('salesqty'=>0,'returnqty'=>0,'damagedqty'=>0,'expiryqty'=>0,'freesampleqty'=>0,'salesvalue'=>0,'returnvalue'=>0,'damagevalue'=>0,'expiredvalue'=>0,'freevalue'=>0,'itempromodiscount'=>0,'netamount'=>0);
                    }
                    $partialarr = array($dataarr1['salesqty'],$dataarr1['returnqty'],$dataarr1['damagedqty'],$dataarr1['expiryqty'],$dataarr1['freesampleqty'],$dataarr1['salesvalue'],$dataarr1['returnvalue'],$dataarr1['damagevalue'],$dataarr1['expiredvalue'],$dataarr1['freevalue'],$dataarr1['itempromodiscount'],$dataarr1['netamount']);
                    $temp_arr = array_merge($temp_arr,$partialarr);
                }
                $dataarr[$newrow[$i]['routecode']][$newrow[$i]['customercategory']][$newrow[$i]['majorcategorycode']][$newrow[$i]['customercode']][] = $temp_arr;
            }
            $column_model_arr = $dataarr;
        }
        
        $data_arr = array();
        
        $data_arr["columns"] = $this->report_session->column_val;
        $data_arr["columns_config"] =   array(
                                            array("width"=>15),
                                            array("width"=>15),
                                            array("width"=>15),
                                            array("width"=>15),
                                            array("width"=>15),
                                            array("width"=>35,"toaltext"=>$this->translate->_('Total'),"group_total_text"=>$this->translate->_('Group Total'))
                                        );
        for($j=6;$j<count($this->report_session->column_val);$j++)
        {
            $data_arr["columns_config"][] = array("width"=>15,"total"=>"1","group_total"=>"1");
        }
        
        $data_arr["columns_model"]          = $column_model_arr;
        $data_arr["config"]["report_title"] = $this->translate->_("Item Distribution Monthly Comparison").$report_title;
        $data_arr["config"]["report_title_height"] = $report_title_height;
        $data_arr["config"]["file_name"]    = "Item_Distribution_Monthly_Comparison";
        $data_arr["config"]["group_level"]  = 4;
        $data_arr["config"]["total_columns"]= count($data_arr["columns"]);
        $data_arr["config"]["group_total"]  = "1";
        $data_arr["config"]["main_total"]   = "1";
        
        /* ONLY for extra Heading */
        $data_arr["config"]["main_heading"] = "1";
        
        for($i=0;$i<count($this->report_session->header_txt);$i++) {
            $startindex = ($i==0) ? 6 : $lastindex+1;
            $lastindex = $startindex+11;
            $newheading_arr[] = array("title"=>$this->report_session->header_txt[$i]["title"],"start_index"=>$startindex,"last_index"=>$lastindex);
        }
        $data_arr["main_heading_arr"] = $newheading_arr;
        
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
