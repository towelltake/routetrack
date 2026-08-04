<?php

class SFA_Paging{
        /**
     * SFA_Paging configuration parameters array to define if need to display select box, edit link, delete link etc.
     *
     * @var configuration_params
     */	
    protected $configuration_params = array(
			     "show_selectbox" => false,
			     "show_editlink" => false,
			     "show_deletelink" => false,
			     "show_deleteall" => false,
			     "primaryid" => ""
			     );
    
    /*
     * This array is used for conditions of combobox
     * 
     * @pattern will replace with search values
     */
    protected $condtional_show = array(
                                      "eq" => array("=",0),
                                      "ne" => array("!=",0),
                                      "lt" => array("<",0),
                                      "le" => array("<=",0),
                                      "gt" => array(">",0),
                                      "ge" => array(">=",0),
                                      "bw" => array('like "#pattern#%"',1),
                                      "bn" => array('not like "#pattern#%"',1),
                                      "in" => array('in (#pattern#)',1),
                                      "ni" => array('not in (#pattern#)',1),
                                      "ew" => array('like "%#pattern#"',1),
                                      "en" => array('not like "%#pattern#"',1),
                                      "cn" => array('like "%#pattern#%"',1),
                                      "nc" => array('not like "%#pattern#%"',1)     
                                      );

    /**
     * Class constructor
     *
     * @param showselectbox
     */
    public function __construct($configuration_params)
    {
        $this->configuration_params = $configuration_params;
	$this->zend_instance = Zend_Controller_Front::getInstance();
    }
    
    /**
    * @name       commnfunc
    * @since      20-03-2012
    * @version    Release: 1
    * @author     Jinal Dudhia <jinal@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This function is for getting the search field, pagingation etc
    *
    */
	public function commnfunc(){
		
		$params 			= $this->zend_instance->getRequest()->getParams();		
        $current_controller = $this->zend_instance->getRequest()->getParam('controller');
        $current_action     = $this->zend_instance->getRequest()->getParam('action');
		$current_module     = $this->zend_instance->getRequest()->getParam('module');
		$cols_array 		= $this->configuration_params["fetch_columns_inquery"];
        
	    $field_arr = $seachvalue_arr = $op_arr = $groupOp_arr = array();
	    
	    $Paginator_SearchDetails = new Zend_Session_Namespace('Paginator_SearchDetails');
	    
	    $Paginator_Paging = new Zend_Session_Namespace('Paginator_Paging');
	 
		$gridupper_showsearchbox = false;
		if(isset($Paginator_SearchDetails->field_arr))
		{
			$field_arr = unserialize($Paginator_SearchDetails->field_arr);
			if(count($field_arr) > 0)
			{
			      $this->view->gridupper_showsearchbox = true;
			}
		}
		if($this->zend_instance->getRequest()->getPost())
		{
			Zend_Session::namespaceUnset('Paginator_SearchDetails');
			
			$Paginator_Paging->show_records_per_page = $this->zend_instance->getRequest()->getPost('show_records_per_page');
			
			$this->Link_URL['show_records_per_page'] = $Paginator_Paging->show_records_per_page;
			
			$shortsearch = $this->zend_instance->getRequest()->getPost('grid_search_text');
      
			//Search Value entered by User.
			$seachvalue_arr = $this->zend_instance->getRequest()->getPost('seachvalue');
			
			
			//This is pattern eq, ne, lt, le, gt
			$op_arr         = $this->zend_instance->getRequest()->getPost('op');
			 
			//Rules to Find 
			$groupOp_arr    = $this->zend_instance->getRequest()->getPost('groupOp');
			
			
			$reset_val = $this->zend_instance->getRequest()->getPost('reset_val');
			
			if($reset_val == 1){
			      Zend_Session:: namespaceUnset('Paginator_SearchDetails');
			      $Paginator_SearchDetails->field_arr = "";
			      $Paginator_SearchDetails->seachvalue_arr = "";
			      $Paginator_SearchDetails->op_arr = "";
			      $Paginator_SearchDetails->groupOp_arr = "";
			      $Paginator_SearchDetails->shortsearch_value = "";
			      $Paginator_SearchDetails = array();
			      $this->searchvals = "";
			}
			if($seachvalue_arr[0]!="") {			
				//Search Field sected by User.
				$field_arr      = $this->zend_instance->getRequest()->getPost('field');
				//echo $field_arr;exit;
				
				//Search Value entered by User.
				$seachvalue_arr = $this->zend_instance->getRequest()->getPost('seachvalue');

				$Paginator_SearchDetails->field_arr = serialize($field_arr);
				$Paginator_SearchDetails->seachvalue_arr = serialize($seachvalue_arr);
				$Paginator_SearchDetails->op_arr = serialize($op_arr);
				$Paginator_SearchDetails->groupOp_arr = serialize($groupOp_arr);
			
			
				$Paginator_SearchDetails->shortsearch = false;
				$Paginator_SearchDetails->shortsearch_value = "";				

			}elseif(isset($shortsearch) && $shortsearch!="" ){				
				$Paginator_SearchDetails->shortsearch = true;
				$shortsearch_value = $Paginator_SearchDetails->shortsearch_value = $this->zend_instance->getRequest()->getPost('grid_search_text');
				
				foreach($cols_array as $key=>$value) {
				    if(!strstr($value,'edit_del_primary_id') || !strstr($value,'sett_tran_type') || (!in_array($value,$this->configuration_params['no_search_fields'])))
				    {
						if(!strstr($value,'DATE_FORMAT')){
							$value = strtolower($value);
							$qry = array();
							$qry = explode(' as ',$value);
							$whereclause[] = $qry[0] .' LIKE "%'.$shortsearch_value.'%"';
						}
				    }
					else{
						$whereclause[] = $value .' LIKE "%'.$shortsearch_value.'%"';
					}				    
				    $groupOp_arr[0] = 'OR';
				}
				$groupOp_arr[0] = 'OR';
			}
			else
			{
				if($Paginator_SearchDetails->shortsearch)
				{
					$Paginator_SearchDetails->shortsearch = false;
					$Paginator_SearchDetails->shortsearch_value = "";
				}
			}
		}
		else if(strpos($_SERVER['HTTP_REFERER'],($current_module."/".$current_controller."/".$current_action))!==false && strpos($_SERVER['REQUEST_URI'],'/page/') || $params['printData'] == 1)
		{
			$this->Link_URL['show_records_per_page']= $Paginator_Paging->show_records_per_page;
			
			if($Paginator_SearchDetails->shortsearch == true)
			{
				$shortsearch_value = $Paginator_SearchDetails->shortsearch_value;
				
				foreach($cols_array as $key=>$value) {				    
				    if(!strstr($value,'edit_del_primary_id') || !strstr($value,'sett_tran_type') || (!in_array($value,$this->configuration_params['no_search_fields'])))
				    {
						if(!strstr($value,'DATE_FORMAT')){
							$value = strtolower($value);
							$qry = array();
							$qry = explode(' as ',$value);					    
							$whereclause[] = $qry[0] .' LIKE "%'.$shortsearch_value.'%"';
						}
					}else{
						$whereclause[] = $value .' LIKE "%'.$shortsearch_value.'%"';
					}
				}
				$groupOp_arr[0] = 'OR';
			}
			else {
				
				if(isset($Paginator_SearchDetails->field_arr))
				{
				    $field_arr = unserialize($Paginator_SearchDetails->field_arr);
				}            
				if(isset($Paginator_SearchDetails->seachvalue_arr))
				{
				    $seachvalue_arr = unserialize($Paginator_SearchDetails->seachvalue_arr);
				}
				if(isset($Paginator_SearchDetails->op_arr))
				{
				    $op_arr = unserialize($Paginator_SearchDetails->op_arr);
				}			
				if(isset($Paginator_SearchDetails->groupOp_arr))
				{
				    $groupOp_arr = unserialize($Paginator_SearchDetails->groupOp_arr);
				}
			}
		}
		else if(strpos($_SERVER['HTTP_REFERER'],("/".$current_module."/".$current_controller."/".$current_action))===false)
		{
			Zend_Session:: namespaceUnset('Paginator_SearchDetails');
			Zend_Session:: namespaceUnset('Paginator_Paging');
		}
		elseif(!strpos($_SERVER['REQUEST_URI'],'/page/')) // By HD |||| session will be clear while click on overview tab.
		{
			Zend_Session:: namespaceUnset('Paginator_SearchDetails');
			Zend_Session:: namespaceUnset('Paginator_Paging');
		}
		$this->searchvals = array( 	0 => $field_arr,
									1 => $seachvalue_arr,
									2 => $op_arr,
									3 => $groupOp_arr );
	  
		//creating where clause
		for($i = 0; $i<count($field_arr); $i++){
		      
		    if($seachvalue_arr[$i]=="")
				continue;
		    
			if($this->condtional_show[$op_arr[$i]][1]==1)
			{
				/*
				 * Replace pattern keyword with search values
				 */
	
				$tmpwhere = str_replace('#pattern#',$seachvalue_arr[$i],$this->condtional_show[$op_arr[$i]][0]);                    
				
				/*
				* Prepare where clause
				*/
				if(strstr(strtolower($cols_array[$field_arr[$i]]), ' as ')){
					$qry = array();
					$qry = explode(' as ',strtolower($cols_array[$field_arr[$i]]));
					$whereclause[] = $qry[1] . ' ' .$tmpwhere;
				} else {
					$whereclause[] = $cols_array[$field_arr[$i]] . ' ' .$tmpwhere;
				}
		    }
		    elseif(isset($seachvalue_arr[$i]) && $seachvalue_arr[$i]!='')
		    {
				// Above condition is added by hiren dave on 8th Feb because add form for a page.
				/*
				* Prepare where clause
				*/
				
				if(strstr(strtolower($cols_array[$field_arr[$i]]), ' as ')){
					$qry = array();
					$qry = explode(' as ',strtolower($cols_array[$field_arr[$i]]));
					$checkas_on = $qry[0];					
				} else {
					$checkas_on = $cols_array[$field_arr[$i]];
				}
				
				$whereclause[] = $checkas_on . ' ' . $this->condtional_show[$op_arr[$i]][0] .' "'. $seachvalue_arr[$i].'"';
				
				#echo $cols_array[$field_arr[$i]];
				//exit;
				
		    }
		}
		
		if(count($whereclause) > 0) {				
			//implode where clause value with condition 'OR' or 'AND' 
			$whereclause 		= implode(" ".$groupOp_arr[0]." ",$whereclause);
			$whereclause 		= " AND (".$whereclause.")";
		}
        /*
         * Receive ordering column name
         */
        $ordershow = $this->zend_instance->getRequest()->getParam('order');
	
	/*	
	    Below Setting is change by Hiren Dave
	    Set default sorting option is Descding	
	*/
	
	$this->Link_URL['order_type'] = "desc";

        /*
         * Set default sorting option is ascending
         */
        //$this->Link_URL['order_type'] = "asc";
        
        /*
         * Define sorting columns names
         */
        $this->Link_URL['order_columns_name'] = null;
        
        /*
         * Define sorting column number
         */ 
        $this->Link_URL['order_columns_number'] = null;
        
        if($ordershow != "")
        {
            /*
             * Explode string in array by column number and sorting predefined
             */             
            $ordershow = explode("_",$ordershow);
            
            $int_ordershow = (int) $ordershow[0];
            
            $orderby_columns = $cols_array[$int_ordershow];
           
	    // if the last column as `code as id` then take only `code`, thus `order by id` - M@M
	    $order_columns_name = explode(" ",$cols_array[$int_ordershow]);
	    
            $this->Link_URL['order_columns_name']   = end($order_columns_name);
            $this->Link_URL['order_columns_number'] = (int) $ordershow[0];
            
            /*
             * Check and set default sort order as ascending.
             */
            $orderby_type = strtolower($ordershow[1]);	    
            if($orderby_type == "asc" || $orderby_type == "desc") {               
            } else {
                $orderby_type="desc";
            }
            
            /*
             * Check if order is ascending or descending
             */
            
            // whatever order is passed from URL, that will be set as current order (Meral)
            $this->Link_URL['order_type'] = $orderby_type;
            
        }else{
	    // if the last column as `code as id` then take only `code`, thus `order by id` - M@M
	    $order_columns_name = explode(" ",$cols_array[$int_ordershow]);
	    
            $this->Link_URL['order_columns_name']   = end($order_columns_name);
            $this->Link_URL['order_columns_number'] =  0;
	}
	
	
	if($this->Link_URL['show_records_per_page'] == ''){
	      $this->Link_URL['show_records_per_page'] = $this->configuration_params['show_records_per_page'];
	}
	
	$page_val = $this->zend_instance->getRequest()->getParam('page');
	if(!isset($page_val) && $page_val ==''){
	    $start_limit = 0;
	    $page_val = 1;
	    $this->Link_URL['show_records_per_page'] = $this->configuration_params['show_records_per_page'];
	}else{
	    $start_limit = ($page_val - 1) * $this->Link_URL['show_records_per_page'];
	}
	
	$this->Link_URL['current_page'] = $page_val;
	$this->Link_URL['offset'] = (int)$start_limit;
        $this->Link_URL['search_value'] = $this->searchvals;
        
	
	
	if(isset($this->configuration_params['additional_where']) && is_array($this->configuration_params['additional_where']))
	{
		
		$addtional_query = implode(" AND ",$this->configuration_params['additional_where']);
		
		$whereclause = " AND ". $addtional_query.$whereclause;
		
		//added by Jinal for advance search -date 18-April-2012
		$Paginator_SearchDetails->additional_where = $whereclause;
		/*$whereclause = explode(" OR ",$whereclause);
		if(is_array($whereclause) && count($whereclause) > 0)
		{
			$addtional_query = implode(" AND ",$this->configuration_params['additional_where']);
			for($k=0;$k<count($whereclause);$k++)
			{
				$whereclause[$k] = $whereclause[$k]." AND ".$addtional_query;
			}
			$whereclause = implode(" AND ",$whereclause);
		}*/
	}
	//added by Jinal for advance search -date 18-April-2012
        if(isset($Paginator_SearchDetails->additional_where)){
		$whereclause = $Paginator_SearchDetails->additional_where;
	}

	$this->Link_URL['where_condition'] = $whereclause;
	
        return $this->Link_URL;
        
    }
    
    /**
    * @name       create_data_grid
    * @since      20-03-2012
    * @version    Release: 1
    * @author     M@M <miral@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This function is used to create grid with edit,delete,selectbox columns
    *
    */
    
    function create_data_grid($data_row_display,$configuration_params)
    {
	$final_data_arr = array();
	if(count($data_row_display) > 0)
	{
	
	$final_data_arr = $data_row_display;
	
	for($s=0; $s<count($data_row_display); $s++)
	{
	    // if the primary id is fetched for the grid then edit id will be used from it , other wise explicitly need to set up edit_del_primary_id. Eg $cols_array 	= array('bankname','arbbankname','activestatus', 'bankcode as edit_del_primary_id' );
	    if(isset($data_row_display[$s][$configuration_params['primaryid']]) && $data_row_display[$s][$configuration_params['primaryid']]!="" )
	    {
		$edit_delete_primary_id = $data_row_display[$s][$configuration_params['primaryid']];

	    }		
	    else
	    {
			$edit_delete_primary_id = $data_row_display[$s]['edit_del_primary_id'];
	    }
		
		/*
	     * Check condition for add query string into url
	     */
		if(isset($data_row_display[$s]['sett_tran_type']))
	    {
			$sett_tran_type = $data_row_display[$s]['sett_tran_type'];
	    }
	    
	    /*
	     * Add dynamic check box if its set to true
	     */
	    if($configuration_params['show_selectbox'])
	    {
		if(isset($configuration_params["selected_list"]) && count($configuration_params["selected_list"]) && in_array($edit_delete_primary_id,$configuration_params["selected_list"]))
		    $tmp_checked = 'checked="checked"';
		else
			$tmp_checked = "";
			    
		$final_data_arr[$s] = array_slice($data_row_display[$s], 0, 0, true) +  array("selectbox" => '<input type="checkbox" class="checkbox sel_checkbox_all" name="chk[]"  onclick="toggleUnChecked(this.checked,this.value);" value="'.$edit_delete_primary_id.'" ' .$tmp_checked. ' />') +   array_slice($data_row_display[$s], 0, count($data_row_display[$s]), true);
	    }
	    
	    /*
	     * Set base url in var
	     */

            $fc = Zend_Controller_Front::getInstance();
            $baseurl = $this->_baseUrl = $fc->getBaseUrl();

	    /*
	     * Add dynamic edit link if its set to true
	     */ 
	    if($configuration_params['show_editlink'])
	    {
		    /*
		 * Replace pattern with dynamic primary id value
		 */
			
		$editlink = str_replace($configuration_params['editlink'][1],$edit_delete_primary_id, $configuration_params['editlink'][0]);
		
		if(isset($data_row_display[$s]['sett_tran_type']))
		{
			$editlink = str_replace('#trantype#',$sett_tran_type, $editlink);
		}
		
		/*
		 * Prepare anchor tag with edit image
		 */
		//$editlink = '<a href="'.$baseurl.$editlink.'"><img src="'.$baseurl.'/public/pagegrid_images/user_edit.png" alt="Edit" title="Edit" border="0" /></a>';
		$editlink = '<a href="'.$baseurl.$editlink.'" class="ico edit" >Edit</a>';
		
		/*
		 * Fill anchor tag in predefined data array
		 */
		$final_data_arr[$s]['edit_link'] = $editlink;
	    }
	    
	    /*
	     * Add dynamic delete link if its set to true
	     */
	    if($configuration_params['show_deletelink'])
	    {
		/*
		 * Replace pattern with dynamic primary id value
		 */ 
		$deletelink = str_replace($configuration_params['deletelink'][1],$edit_delete_primary_id, $configuration_params['deletelink'][0]);
		
		/*
		 * Prepare anchor tag with delete image
		 */
		//$deletelink = '<a href="'.$baseurl.$deletelink.'" class="ask" onclick="return confirm(\'Are you sure you want to delete?\')"><img src="'.$baseurl.'/public/pagegrid_images/trash.png" alt="Delete" title="Delete" border="0" /></a>';
		$deletelink = '<a href="Javascript:void(0)" id="del_'.$edit_delete_primary_id.'" redirecturl="'.$baseurl.$deletelink.'" class="icodel" >Delete</a>';
		/*
		 * Fill anchor tag in predefined data array
		 */
		$final_data_arr[$s]['delete_link'] = $deletelink;
	    }
	    
	    //Replace the image            
	    if($configuration_params['image_cols'] && count($configuration_params['image_cols']) > 0)
	    {
		/*
		 * Get image's database field name - Which is passed from controller
		 */
		$cols_name_show = $configuration_params['image_cols'][0][1];
		
		/*
		 * Get image's path - Which is passed from controller
		 */
		$img_path_show = $configuration_params['image_cols'][0][0]."/".$data_row_display[$s][$cols_name_show];
		
		/*
		 * Get image width - Which is passed from controller
		 */
		$imgwidth = $configuration_params['image_cols'][0][2];
		
		/*
		 * Prepare image tag
		 */
		$image = '<img src="'. $img_path_show .'" width="'.$imgwidth.'" alt="" />';
		
		/*
		 * Replace image database field with image tag with src and width
		 */
		$final_data_arr[$s][$cols_name_show] = $image;
	    }
	    
	    /*
	     * Replace status database field with user defined values ie active / in-active
	     */
	    if($configuration_params['status_cols'] && count($configuration_params['status_cols']) > 0)
	    {
            for($i=0; $i<count($configuration_params['status_cols']); $i++)
            {
                /*
                 * Get column name
                 */
                $tmpfieldname = $configuration_params['status_cols'][$i]['cols_name'];
                
                /*
                 * Get user defined values ie Active / In-active
                 */
                $statusArr = $configuration_params['status_cols'][$i]['status_change'];
                
                /*
                * Fill status values in predefined data array
                */
                // $final_data_arr[$s][$tmpfieldname] = ($data_row_display[$s][$tmpfieldname]==1) ? $statusArr[1] : $statusArr[0];
                // Inserted by MB [Elan] - This is added for more than 2 status array passed
                foreach( $statusArr as $k => $v )
                {
                    if($data_row_display[$s][$tmpfieldname]==$k) {
                        $final_data_arr[$s][$tmpfieldname] = $v;
                    }
                }
            }
	    }
	    
	    if($configuration_params['show_extralink'])
	    {
		/*
		 * Replace pattern with dynamic primary id value
		 */
		
		$extral_link_array = $configuration_params['extralink'];
		
		$ex_counter = 0;
		
		foreach($extral_link_array as $_el)
		{
		    
		    $ex_counter++;
		    
		    $extralink = str_replace($_el[2],$edit_delete_primary_id, $_el[1]);
		
		    /*
		     * Prepare anchor tag with delete image
		     */
		    //$deletelink = '<a href="'.$baseurl.$deletelink.'" class="ask" onclick="return confirm(\'Are you sure you want to delete?\')"><img src="'.$baseurl.'/public/pagegrid_images/trash.png" alt="Delete" title="Delete" border="0" /></a>';
		    $f_extralink = '<a id="extra_'.$ex_counter."_".$edit_delete_primary_id.'" href="'.$baseurl.$extralink.'" class="icoextra" >'.$_el[0].'</a>';
		    /*
		     * Fill anchor tag in predefined data array
		     */
		    $final_data_arr[$s]['extra_link'.$ex_counter] = $f_extralink;
		    
		}
		
	    }
	}
	}
	return $final_data_arr;
    }
    
    
    /**
    * @name       summary_showdatagrid
    * @since      20-03-2012
    * @version    Release: 1
    * @author     M@M <miral@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action returns the required data in array format used in datagrid.phtml file
    *
    */
    public function summary_showdatagrid($data_arr)
    {

	$get_return_vals = $this->commnfunc();
	
	//$comman = new SFA_Comman();
	//
	//$param_array[1] = '1';
	//$param_array[2] = '';
	//$param_array[3] = $get_return_vals['order_columns_name'];
	//$param_array[4] = $get_return_vals['order_type'];
	//$param_array[5] = $get_return_vals['offset'];
	//$param_array[6] = (int)$get_return_vals['show_records_per_page'];
	//$param_array[7] = '';
	//$param_array[8] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
	////Called stored procedure for counter
	//$rowcount = $comman->executequery('CALL sp_transactionmaster(?,?,?,?,?,?,?,?)',$param_array,'');
	//$data_arr["count"] = $rowcount[0][0]['counter'];
	//
	//$param_array[1] = '';
	//$param_array[7] = implode(", ",$this->configuration_params["fetch_columns_inquery"]);
	//
	////Called stored procedure to fetch data and display in grid
	//$data_arr["data"] = $comman->executequery('CALL sp_transactionmaster(?,?,?,?,?,?,?,?)',$param_array,'');
	//
	//
	//#echo "<pre>"; print_r($data_arr["data"]); echo "</pre>"; exit;
	//#return $data_arr;
	//   
	// creating the data grid array
	$datagrid["paginator"] = array("currenet"=>$get_return_vals['current_page'],
				     "itemCountPerPage"=>$get_return_vals['show_records_per_page'],
				     "totalItemCount"=>$data_arr["count"],
				     "pagingLength"=>2);
	     
	$datagrid["datagrid"] = array
	(
		"columns_top" =>$this->configuration_params["show_top_columns"],
	    "columns_top_value" =>$this->configuration_params["show_top_columns_value"],
	    "columns" =>$this->configuration_params["show_columns"],	 
	    "data" => $this->create_data_grid($data_arr["data"][0],$this->configuration_params),	 
	    "settings" => array (
			"total_data" => $data_arr["count"],
			"configuration_params" =>$this->configuration_params,
			"total_row_per_page" => $get_return_vals['show_records_per_page'],
			"order_type" => $get_return_vals['order_type'],
			"order_columns_name" => $get_return_vals['order_columns_name'],
			"order_columns_number" => $get_return_vals['order_columns_number'],
			"search_values"=>$get_return_vals['search_value']
	    )
	);
	
	return $datagrid;
	
    }
}

?>