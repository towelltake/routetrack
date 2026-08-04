<?php

class SFA_Ajaxpagingextra{
        /**
     * SFA_Paging configuration parameters array to define if need to display select box, edit link, delete link etc.
     *
     * @var configuration_params
     */
    protected $configuration_params = array();

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
	
                $current_controller = $this->zend_instance->getRequest()->getParam('controller');
        	$current_action     = $this->zend_instance->getRequest()->getParam('action');
		$current_module     = $this->zend_instance->getRequest()->getParam('module');
		$cols_array = $this->configuration_params["fetch_columns_inquery"];
        
		if(count($whereclause) > 0) {
		      //implode where clause value with condition 'OR' or 'AND' 
		      $whereclause = implode(" ".$groupOp_arr[0]." ",$whereclause);
		      $whereclause = " AND (".$whereclause.")";
		}
            
        /*
         * Receive ordering column name
         */
        $ordershow = $this->zend_instance->getRequest()->getParam('order');

        /*
         * Set default sorting option is ascending
         */
        $this->Link_URL['order_type'] = "asc";
        
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
                $orderby_type="asc";
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
        
	
	
	if(isset($this->configuration_params['additional_where']) && is_array($this->configuration_params['additional_where']) && count($this->configuration_params['additional_where']))
	{
		$addtional_query = implode(" AND ",$this->configuration_params['additional_where']);
		
		$whereclause = " AND ". $addtional_query.$whereclause;
		
		
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
	   
	    $final_data_arr[$s]['PRIMARYKEY'] = $data_row_display[$s]['edit_del_primary_id'];
	    if(isset($data_row_display[$s][$configuration_params['primaryid']]) && $data_row_display[$s][$configuration_params['primaryid']]!="" )
	    {
		$edit_delete_primary_id = $data_row_display[$s][$configuration_params['primaryid']];

	    }
	    else
	    {
		$edit_delete_primary_id = $data_row_display[$s]['edit_del_primary_id'];
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
		    $final_data_arr[$s] = array_slice($data_row_display[$s], 0, 0, true) +  array("selectbox" => '<input type="checkbox" class="checkbox sel_checkbox_all_ex" name="chk[]"  onclick="toggleUnChecked(this.checked,this.value);" value="'.$edit_delete_primary_id.'" ' .$tmp_checked. ' />') +   array_slice($data_row_display[$s], 0, count($data_row_display[$s]), true);
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
	        
		/*
		 * Prepare anchor tag with edit image
		 */
		
		$editlink = '<a href="javascript:void(0)" id="'.$editlink.'" class="ico edit xedit" >Edit</a>';
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
		//$deletelink = '<a href="Javascript:void(0)" id="del_'.$data_row_display[$s][$configuration_params['primaryid']].'" redirecturl="'.$baseurl.$deletelink.'" class="icodel" >Delete</a>';
		$deletelink = '<a href="Javascript:void(0)" id="'.$deletelink.'" class="icodel" >Delete</a>';

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
		    $final_data_arr[$s][$tmpfieldname] = ($data_row_display[$s][$tmpfieldname]==1) ? $statusArr[1] : $statusArr[0];
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
	    if(isset($final_data_arr[$s]['edit_del_primary_id']))
            {
                unset($final_data_arr[$s]['edit_del_primary_id']);
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
	
	// creating the data grid array
	$datagrid["paginator"] = array("currenet"=>$get_return_vals['current_page'],
				     "itemCountPerPage"=>$get_return_vals['show_records_per_page'],
				     "totalItemCount"=>$data_arr["count"],
				     "pagingLength"=>2);
	     
	$datagrid["datagrid"] = array
	(
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