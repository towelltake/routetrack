<?php
/** 
 * @since      Class available since Release 1.0
 * @version    Release: @1.0@
 * @author     HC <harsh@elantechnologies.com>
 * @author     MB <mayur.b@elantechnologies.com>
 * @copyright  2011 - Elan Technologies
 *
 * Short description : Dynamic pagignation with dynamic column headings, searching, sorting functionality
 *
 */

class SFA_Pagingquery
{
    const DISTINCT       = 'distinct';
    const COLUMNS        = 'columns';
    const FROM           = 'from';
    const UNION          = 'union';
    const WHERE          = 'where';
    const GROUP          = 'group';
    const HAVING         = 'having';
    const ORDER          = 'order';
    const LIMIT_COUNT    = 'limitcount';
    const LIMIT_OFFSET   = 'limitoffset';
    const FOR_UPDATE     = 'forupdate';

    const INNER_JOIN     = 'inner join';
    const LEFT_JOIN      = 'left join';
    const RIGHT_JOIN     = 'right join';
    const FULL_JOIN      = 'full join';
    const CROSS_JOIN     = 'cross join';
    const NATURAL_JOIN   = 'natural join';

    const SQL_WILDCARD   = '*';
    const SQL_SELECT     = 'SELECT';
    const SQL_UNION      = 'UNION';
    const SQL_UNION_ALL  = 'UNION ALL';
    const SQL_FROM       = 'FROM';
    const SQL_WHERE      = 'WHERE';
    const SQL_DISTINCT   = 'DISTINCT';
    const SQL_GROUP_BY   = 'GROUP BY';
    const SQL_ORDER_BY   = 'ORDER BY';
    const SQL_HAVING     = 'HAVING';
    const SQL_FOR_UPDATE = 'FOR UPDATE';
    const SQL_AND        = 'AND';
    const SQL_AS         = 'AS';
    const SQL_OR         = 'OR';
    const SQL_ON         = 'ON';
    const SQL_ASC        = 'ASC';
    const SQL_DESC       = 'DESC';
    
    /**
     * Select query variable.
     *
     * @var _select_query 
     */
    public $_select_query;
    
    /**
     * Columns array which needs to select in select query - Array.
     *
     * @var _select_cols
     */
    protected $_select_cols = array();
    
     /**
     * Zend_Db_Adapter_Abstract object.
     *
     * @var Zend_Db_Adapter_Abstract
     */
    protected $db_paging;
        
    /**
     * Zend_Db_Adapter_Abstract object.
     *
     * @var Zend_Db_Adapter_Abstract
     */
    protected $zend_instance;

    /**
     * SFA_Paging var to fetch records.
     *
     * @var SFA_Paging
     */
    protected $data_row_display;

   /**
     * View instance used for self rendering
     *
     * @var Zend_View_Interface
     */
    protected $_view = null;
    
    protected $showselectbox;
    
    /**
     * SFA_Paging var to save columns names which need to show on grid.
     *
     * @var column_nameshow
     */
    protected $column_nameshow;
    
    /**
     * SFA_Paging array to save grid data.
     *
     * @var column_nameshow
     */
    protected $Link_URL = array();
    
    
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
                                      "bw" => array("like '#pattern#%'",1),
                                      "bn" => array("not like '#pattern#%'",1),
                                      "in" => array("in (#pattern#)",1),
                                      "ni" => array("not in (#pattern#)",1),
                                      "ew" => array("like '%#pattern#'",1),
                                      "en" => array("not like '%#pattern#'",1),
                                      "cn" => array("like '%#pattern#%'",1),
                                      "nc" => array("not like '%#pattern#%'",1)     
                                      );
    
    /**
     * Class constructor
     *
     * @param showselectbox
     */
    public function __construct($configuration_params)
    {
        $this->configuration_params = $configuration_params;
	$this->db_paging = Zend_Db_Table::getDefaultAdapter();
	$this->_select_query = $this->db_paging->select();
	$this->zend_instance = Zend_Controller_Front::getInstance();
    }
    
    /************************************************************************
    * @name       paginationshow
    * @since      28 Sep, 2011
    * @version    Release: 1
    * @author     HC & MB
    * @copyright  Elan Technologies
    * 
    * This will display pagignation
    *
    ************************************************************************/
    public function paginationshow($columns_name = array())
    {
        /*
         * @var select_cols_number_assign array
         * 
         * Define array to store selected columns
         */
        $select_cols_number_assign = array();
        
        //Cnt variable to increment array keys
        $cnt = 0;
        
        // Fill array with DB field names
        foreach($this->_select_cols as $key => $val)
        {
            if(strpos(strtolower($val)," as ")!==false)
            {
                $val = explode("as", $val);
                $val = $val[0];
            }
        
            $select_cols_number_assign[$cnt] = $val;
            $cnt++;
        }
       
        $current_controller = $this->zend_instance->getRequest()->getParam('controller');
    	$current_action     = $this->zend_instance->getRequest()->getParam('action');
        $current_module     = $this->zend_instance->getRequest()->getParam('module');
        
       if(strtolower($current_action)=="index")
       {
         $current_action = "";
       }
        
        /*
        * Add seaching criteria - Starts
        */
        $field_arr = $seachvalue_arr = $op_arr = $groupOp_arr = array();
        
        
        $Paginator_SearchDetails = new Zend_Session_Namespace('Paginator_SearchDetails');
        
        $Paginator_Paging = new Zend_Session_Namespace('Paginator_Paging');
     
        $gridupper_showsearchbox = false;
        if(isset($Paginator_SearchDetails->field_arr))
        {
            $field_arr = unserialize($Paginator_SearchDetails->field_arr);
            if(count($field_arr) > 0)
            {
                $gridupper_showsearchbox = true;
            }
        }
        
        //HC
        //For the short search
        $shortsearch_value = "";
        
        if($this->zend_instance->getRequest()->getPost())
        {

            
            $Paginator_Paging->show_records_per_page = $this->zend_instance->getRequest()->getPost('show_records_per_page');
            
            $shortsearch = $this->zend_instance->getRequest()->getPost('grid_search_text');
            
            $seachvalue_arr = $this->zend_instance->getRequest()->getPost('seachvalue');
            
            $reset_val = $this->zend_instance->getRequest()->getPost('reset_val');
            
            if($reset_val == 1)
               {
                   Zend_Session:: namespaceUnset('Paginator_SearchDetails');
                   $Paginator_SearchDetails->field_arr = "";
                   $Paginator_SearchDetails->seachvalue_arr = "";
                   $Paginator_SearchDetails->op_arr = "";
                   $Paginator_SearchDetails->groupOp_arr = "";
               
                   $Paginator_SearchDetails = array();
               }
                
            if($seachvalue_arr[0]!="")
            {
            
                
                $field_arr      = $this->zend_instance->getRequest()->getPost('field');
                $seachvalue_arr = $this->zend_instance->getRequest()->getPost('seachvalue');
                $op_arr         = $this->zend_instance->getRequest()->getPost('op');
                $groupOp_arr    = $this->zend_instance->getRequest()->getPost('groupOp');
                
                $Paginator_SearchDetails->field_arr = serialize($field_arr);
                $Paginator_SearchDetails->seachvalue_arr = serialize($seachvalue_arr);
                $Paginator_SearchDetails->op_arr = serialize($op_arr);
                $Paginator_SearchDetails->groupOp_arr = serialize($groupOp_arr);
                
                
                $Paginator_SearchDetails->shortsearch = false;
                $Paginator_SearchDetails->shortsearch_value = "";
                
            }
            elseif(isset($shortsearch) && $shortsearch!="" )
            {
                $Paginator_SearchDetails->shortsearch = true;
                $shortsearch_value = $Paginator_SearchDetails->shortsearch_value = $this->zend_instance->getRequest()->getPost('grid_search_text');
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
        else if(strpos($_SERVER['HTTP_REFERER'],($current_module."/".$current_controller."/".$current_action))!==false)
        {
            if($Paginator_SearchDetails->shortsearch == true)
            {
                $shortsearch_value = $Paginator_SearchDetails->shortsearch_value;
            }
            else
            {
            
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
        
        $query_str = '';
        if(isset($field_arr)) {
            $query_str = implode("_",$field_arr);
        }
        
        $this->Link_URL['where_values'] = array("field" => $query_str);
        


        if(count($field_arr) > 0)
        {
            $whereclause = array();
            for($s=0; $s<count($field_arr); $s++)
            {
                
                $tmpwhere ="";
                if($this->condtional_show[$op_arr[$s]][1]==1)
                {
                    /*
                     * Replace pattern keyword with search values
                     */                    
                    $tmpwhere = str_replace("#pattern#",$seachvalue_arr[$s],$this->condtional_show[$op_arr[$s]][0]);                    
                    
                    /*
                    * Prepare where clause
                    */
                    $whereclause[] = $select_cols_number_assign[$field_arr[$s]] . ' ' .$tmpwhere;                    
                }
                elseif(isset($seachvalue_arr[$s]) && $seachvalue_arr[$s]!='')
                {
                    // Above condition is added by hiren dave on 8th Feb because add form for a page.
                    /*
                    * Prepare where clause
                    */
                    $whereclause[] = $select_cols_number_assign[$field_arr[$s]] . ' ' . $this->condtional_show[$op_arr[$s]][0] .' "'. $seachvalue_arr[$s].'"';    
                }
            }
            
            /*
             * Add seaching criteria to query (where clause)
             */
            if(count($whereclause) > 0) {
                $whereclause = implode(" ".$groupOp_arr[0]." ",$whereclause);
                $this->where($whereclause);
            }
           
            /*
            * Add search criteria values to link url array to display requested values
            */
            $this->Link_URL['search_values'] = array( 0 => $field_arr,
                                                      1 => $seachvalue_arr,
                                                      2 => $op_arr,
                                                      3 => $groupOp_arr );
        }
        /*
        * Add seaching criteria - Ends
        */
        
        
        //For the short search applied
        
        if($shortsearch_value != "")
        {
            
            $whereclause = array();
            for($s=0; $s<count($select_cols_number_assign); $s++)
            {                
                /*
                * Replace pattern keyword with search values
                */                    
               $tmpwhere = str_replace("#pattern#",$seachvalue_arr[$s],$this->condtional_show[$op_arr[$s]][0]);                    
               
               /*
               * Prepare where clause
               */
               $whereclause[] = $select_cols_number_assign[$s] . ' like "%'.$shortsearch_value.'%"';               
                
            }
            
            /*
             * Add seaching criteria to query (where clause)
             */
            if(count($whereclause) > 0) {
                $whereclause = implode(" OR ",$whereclause);
                $this->where($whereclause);
            }
        }
        /*
        * Add seaching criteria - Ends
        */
        
        
	$request= new Zend_Controller_Request_Http();
	$sqlqry = $this->_select_query;
	//die();
	$countqryshow = "SELECT count(".substr(strstr($sqlqry,"`"),0,strpos(strstr($sqlqry,"`"),",")).") as cntshow ".strstr($sqlqry,"FROM");
    
    //$countqryshow = "SELECT count(*) as cntshow ".strstr($sqlqry,"FROM").""; 
	
	$countdatashow = $this->db_paging->fetchAll($countqryshow);
        
	
	$total_rowset_count = 0;
	if($countdatashow)
	{
        $total_rowset_count =(int) $countdatashow[0]['cntshow'];
	}
        // echo $countqryshow; die();
        /*
        * Save number of available data in ilnk_url array
        */
        $this->Link_URL['total_data'] = $total_rowset_count;
          
        
        /*
        * Save configuration parameters in ilnk_url array for conditional use in pthml file
        */
        $this->Link_URL['configuration_params'] = $this->configuration_params;
       
       /*
        * For the showing the searchbox if the data is there or search displayed
        */
          
        if($total_rowset_count > 0)
        {
            $gridupper_showsearchbox = true;
        }
        
        // if show search box is true and no record are there then not to show the search box
        ## commented because a problem occurs, if there is no data after search then search box was disappeared
        //if($gridupper_showsearchbox == false)
        //{
        //    $this->Link_URL['configuration_params']['show_searchbox'] = $gridupper_showsearchbox;    
        //}
       
       
       
        $total_row_data = array();
        
        /*
         * Fill array with 1
         */
        if($total_rowset_count!=0)
        {
            $total_row_data = array_fill(0,$total_rowset_count,'1');
        }
        /*
        * Request page values
        */
        $page = $this->zend_instance->getRequest()->getParam('page',1);
        
        /*
        * Number of records displayed per page
        */
	$total_row_show = isset($Paginator_Paging->show_records_per_page)?$Paginator_Paging->show_records_per_page:$this->configuration_params['show_records_per_page'];
        
        $this->Link_URL['total_row_per_page']=$total_row_show = $this->zend_instance->getRequest()->getParam('prow',$total_row_show);
        
	$start_row_page = ($page - 1)* $total_row_show;
	
	$end_row_page = (($page - 1)* $total_row_show ) + $total_row_show;
                
	$paginator = Zend_Paginator::factory($total_row_data);
	$paginator->setItemCountPerPage($total_row_show);
	$paginator->setCurrentPageNumber($page);
	
        
        
        // Limit query with total rows and starting of rows       
	$this->limit($total_row_show,$start_row_page);        
        
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
            
            
            
            $orderby_columns = $select_cols_number_assign[$int_ordershow];
           
            $this->Link_URL['order_columns_name']   = $orderby_columns;
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
            if($orderby_type=="asc") {
                $this->Link_URL['order_type'] = "desc";
            } else {
                $this->Link_URL['order_type'] = "asc";
            }
            
            //echo $orderby_columns." ".$orderby_type." "; die();
            // Set order by column names
            $this->order($orderby_columns." ".$orderby_type." ");
        }
        
        /*
         * Fetch query data from database
         */
        
	$this->data_row_display = $this->db_paging->fetchAll($this->_select_query);
    
    //Added by MB - Check if columns name is defined
    if(isset($columns_name) && count($columns_name) > 0)
    {    
        foreach($columns_name as $key => $val)
        {
            $this->column_nameshow[trim($key)] = trim($val);
        }
    }
    else
    {
        if(count($this->data_row_display) > 0)
        {        
            foreach($this->data_row_display[0] as $key => $val)
            {
                $this->column_nameshow[trim($key)] = trim($key);
            }        
        }
    }
    //echo $this->_select_query; die();
    
        return $paginator;
    }    
    
    /************************************************************************
    * @name       summary_showdatagrid
    * @since      28 Sep, 2011
    * @version    Release: 1
    * @author     HC & MB
    * @copyright  Elan Technologies
    * 
    * This function is used to put summary in grid
    *
    ************************************************************************/
    public function summary_showdatagrid($columns_name = array(),$columns_show  = array(),$result = array())
    {
        $send_array_show = array();	
        
        //$send_array_show['paginator'] = $this->paginationshow();
        //Changed by MB - Pass columns name
        $send_array_show['paginator'] = $this->paginationshow($columns_name);        
        
        $send_array_show['datagrid']['columns'] = $this->columns_name_replace($columns_name);
       
        $this->data_row_display = $this->create_data_grid($this->data_row_display);
        
        $send_array_show['datagrid']['data'] = $this->data_row_display;
        
        /* Hiren dave*/
        //$send_array_show['datagrid']['data'] = $result;
        
        $send_array_show['datagrid']['settings'] = $this->Link_URL;
        
        return $send_array_show;
    }
    
    public function create_data_grid($data_row_display)
    {
        $final_data_arr = array();
        if(count($data_row_display) > 0)
        {
        
        $final_data_arr = $data_row_display;
        
        for($s=0; $s<count($data_row_display); $s++)
        {
            /*
             * Add dynamic check box if its set to true
             */
            if($this->configuration_params['show_selectbox'])
            {
                $final_data_arr[$s] = array_slice($data_row_display[$s], 0, 0, true) +  array("selectbox" => '<input type="checkbox" class="checkbox" name="chk[]"  onclick="toggleUnChecked(this.checked,this.value);" value="'.$data_row_display[$s]['edit_del_primary_id'].'" />') +   array_slice($data_row_display[$s], 0, count($data_row_display[$s]) - 1, true);
            }
            
            /*
             * Set base url in var
             */
            $baseurl = Zend_Controller_Front::getInstance()->getBaseUrl();
            
            /*
             * Add dynamic edit link if its set to true
             */ 
            if($this->configuration_params['show_editlink'])
            {
                /*
                 * Replace pattern with dynamic primary id value
                 */ 
                $editlink = str_replace($this->configuration_params['editlink'][1],$data_row_display[$s]['edit_del_primary_id'], $this->configuration_params['editlink'][0]);
               
                
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
            if($this->configuration_params['show_deletelink'])
            {
                /*
                 * Replace pattern with dynamic primary id value
                 */ 
                $deletelink = str_replace($this->configuration_params['deletelink'][1],$data_row_display[$s]['edit_del_primary_id'], $this->configuration_params['deletelink'][0]);
                
                /*
                 * Prepare anchor tag with delete image
                 */
                //$deletelink = '<a href="'.$baseurl.$deletelink.'" class="ask" onclick="return confirm(\'Are you sure you want to delete?\')"><img src="'.$baseurl.'/public/pagegrid_images/trash.png" alt="Delete" title="Delete" border="0" /></a>';
                $deletelink = '<a href="Javascript:void(0)" id="del_'.$data_row_display[$s]['edit_del_primary_id'].'" redirecturl="'.$baseurl.$deletelink.'" class="icodel" >Delete</a>';
                /*
                 * Fill anchor tag in predefined data array
                 */
                $final_data_arr[$s]['delete_link'] = $deletelink;
            }
            
            //Replace the image            
            if($this->configuration_params['image_cols'] && count($this->configuration_params['image_cols']) > 0)
            {
                /*
                 * Get image's database field name - Which is passed from controller
                 */
                $cols_name_show = $this->configuration_params['image_cols'][0][1];
                
                /*
                 * Get image's path - Which is passed from controller
                 */
                $img_path_show = $this->configuration_params['image_cols'][0][0]."/".$data_row_display[$s][$cols_name_show];
                
                /*
                 * Get image width - Which is passed from controller
                 */
                $imgwidth = $this->configuration_params['image_cols'][0][2];
                
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
            if($this->configuration_params['status_cols'] && count($this->configuration_params['status_cols']) > 0)
            {
                for($i=0; $i<count($this->configuration_params['status_cols']); $i++)
                {
                    /*
                     * Get column name
                     */
                    $tmpfieldname = $this->configuration_params['status_cols'][$i]['cols_name'];
                    
                    /*
                     * Get user defined values ie Active / In-active
                     */
                    $statusArr = $this->configuration_params['status_cols'][$i]['status_change'];
                    
                    /*
                    * Fill status values in predefined data array
                    */
                    $final_data_arr[$s][$tmpfieldname] = ($data_row_display[$s][$tmpfieldname]==1) ? $statusArr[1] : $statusArr[0];
                }
            }
            
            if($this->configuration_params['show_extralink'])
            {
                /*
                 * Replace pattern with dynamic primary id value
                 */
                
                $extral_link_array = $this->configuration_params['extralink'];
                
                $ex_counter = 0;
                
                foreach($extral_link_array as $_el)
                {
                    
                    $ex_counter++;
                    
                    $extralink = str_replace($_el[2],$data_row_display[$s]['edit_del_primary_id'], $_el[1]);
                
                    /*
                     * Prepare anchor tag with delete image
                     */
                    //$deletelink = '<a href="'.$baseurl.$deletelink.'" class="ask" onclick="return confirm(\'Are you sure you want to delete?\')"><img src="'.$baseurl.'/public/pagegrid_images/trash.png" alt="Delete" title="Delete" border="0" /></a>';
                    $f_extralink = '<a id="extra_'.$ex_counter."_".$data_row_display[$s]['edit_del_primary_id'].'" href="'.$baseurl.$extralink.'" class="icoextra" >'.$_el[0].'</a>';
                    /*
                     * Fill anchor tag in predefined data array
                     */
                    $final_data_arr[$s]['extra_link'.$ex_counter] = $f_extralink;
                    
                }
                
            }
            
            /*
             * Remove additinal defined primary key id for edit and delete
             */
            if(isset($final_data_arr[$s]['edit_del_primary_id']))
            {
                unset($final_data_arr[$s]['edit_del_primary_id']);
            }
        }
        }
        
        //print_r($final_data_arr);
        
        
        return $final_data_arr;
    }
    
    
    /**
     * Set column name which needs to show on grid heading
     *
     */
    public function columns_name_replace($column_nameshow)
    {
        /*
         * Check total number of columns and seleted columns and pass column names to var
         */
        
        if(isset($column_nameshow) && count($column_nameshow) > 0)
        {
            foreach($column_nameshow as $key => $val)
            {                
                if(!isset($this->column_nameshow[$key]))
                {
                    $this->column_nameshow[$key] = $val;
                }
            }
        }
        
        if(isset($this->column_nameshow['edit_del_primary_id']))
        {
            unset($this->column_nameshow['edit_del_primary_id']);
        }
        
        //echo "<pre>";
        //print_r($this->column_nameshow);
        return $this->column_nameshow;
    
    }

    /**
     * Makes the query SELECT DISTINCT.
     *
     * @param bool $flag Whether or not the SELECT is DISTINCT (default true).
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function distinct($flag = true)
    {
        $this->_select_query->distinct($flag);
    }

    
    /************************************************************************
    * @name       from
    * @since      28 Sep, 2011
    * @version    Release: 1
    * @author     HC & MB
    * @copyright  Elan Technologies
    * 
    * This function is used to set table names, column names and schemas
    *
    ************************************************************************/
    public function from($name, $cols = '*', $schema = null)
    {
        /*
         * table alias
         */
        $show_table_alias = array_keys($name);
        $show_table_alias = $show_table_alias[0];
        
        foreach($cols as $key => $val)
        {
            if(strpos($val,".")===false && strpos($val,"(")===false ) 
            {
                $cols[$key] = $show_table_alias.".".$val;
            }
        }
        
        /*
         * Check if primary key is set then fill extra edit_del_primary_id
         */         
        if( $this->configuration_params['primaryid']!="")
        {
            $cols[$this->configuration_params['primaryid']] = $this->configuration_params['primaryid']." as edit_del_primary_id";
        }
        
	$this->_select_cols = $cols;
	$this->_select_query->from($name,$cols,$schema);
    }
    
    
    /**
     * Adds a WHERE condition to the query by AND.
     *
     * If a value is passed as the second param, it will be quoted
     * and replaced into the condition wherever a question-mark
     * appears. Array values are quoted and comma-separated.
     *
     * <code>
     * // simplest but non-secure
     * $select->where("id = $id");
     *
     * // secure (ID is quoted but matched anyway)
     * $select->where('id = ?', $id);
     *
     * // alternatively, with named binding
     * $select->where('id = :id');
     * </code>
     *
     * Note that it is more correct to use named bindings in your
     * queries for values other than strings. When you use named
     * bindings, don't forget to pass the values when actually
     * making a query:
     *
     * <code>
     * $db->fetchAll($select, array('id' => 5));
     * </code>
     *
     * @param string   $cond  The WHERE condition.
     * @param mixed    $value OPTIONAL The value to quote into the condition.
     * @param constant $type  OPTIONAL The type of the given value
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function where($cond, $value = null, $type = null)
    {
	$this->_select_query->where($cond, $value = null, $type = null);
    }

    
    /**
     * Adds a UNION clause to the query.
     *
     * The first parameter has to be an array of Zend_Db_Select or
     * sql query strings.
     *
     * <code>
     * $sql1 = $db->select();
     * $sql2 = "SELECT ...";
     * $select = $db->select()
     *      ->union(array($sql1, $sql2))
     *      ->order("id");
     * </code>
     *
     * @param  array $select Array of select clauses for the union.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function union($select = array(), $type = self::SQL_UNION)
    {
	$this->_select_query->union($select,$type);
    }

    /**
     * Adds a JOIN table and columns to the query.
     *
     * The $name and $cols parameters follow the same logic
     * as described in the from() method.
     *
     * @param  array|string|Zend_Db_Expr $name The table name.
     * @param  string $cond Join on this condition.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The database name to specify, if any.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function join($name, $cond, $cols = self::SQL_WILDCARD, $schema = null)
    {
        $this->_select_query->join($name, $cond, $cols, $schema);
    }

    /**
     * Add an INNER JOIN table and colums to the query
     * Rows in both tables are matched according to the expression
     * in the $cond argument.  The result set is comprised
     * of all cases where rows from the left table match
     * rows from the right table.
     *
     * The $name and $cols parameters follow the same logic
     * as described in the from() method.
     *
     * @param  array|string|Zend_Db_Expr $name The table name.
     * @param  string $cond Join on this condition.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The database name to specify, if any.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function joinInner($name, $cond, $cols = self::SQL_WILDCARD, $schema = null)
    {
        $this->_select_query->joinInner($name, $cond, $cols, $schema);
    }

    /**
     * Add a LEFT OUTER JOIN table and colums to the query
     * All rows from the left operand table are included,
     * matching rows from the right operand table included,
     * and the columns from the right operand table are filled
     * with NULLs if no row exists matching the left table.
     *
     * The $name and $cols parameters follow the same logic
     * as described in the from() method.
     *
     * @param  array|string|Zend_Db_Expr $name The table name.
     * @param  string $cond Join on this condition.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The database name to specify, if any.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function joinLeft($name, $cond, $cols = self::SQL_WILDCARD, $schema = null)
    {
        $this->_select_query->joinLeft($name, $cond, $cols, $schema);
    }

    /**
     * Add a RIGHT OUTER JOIN table and colums to the query.
     * Right outer join is the complement of left outer join.
     * All rows from the right operand table are included,
     * matching rows from the left operand table included,
     * and the columns from the left operand table are filled
     * with NULLs if no row exists matching the right table.
     *
     * The $name and $cols parameters follow the same logic
     * as described in the from() method.
     *
     * @param  array|string|Zend_Db_Expr $name The table name.
     * @param  string $cond Join on this condition.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The database name to specify, if any.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function joinRight($name, $cond, $cols = self::SQL_WILDCARD, $schema = null)
    {
        $this->_select_query->joinRight($name, $cond, $cols , $schema);
    }

    /**
     * Add a FULL OUTER JOIN table and colums to the query.
     * A full outer join is like combining a left outer join
     * and a right outer join.  All rows from both tables are
     * included, paired with each other on the same row of the
     * result set if they satisfy the join condition, and otherwise
     * paired with NULLs in place of columns from the other table.
     *
     * The $name and $cols parameters follow the same logic
     * as described in the from() method.
     *
     * @param  array|string|Zend_Db_Expr $name The table name.
     * @param  string $cond Join on this condition.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The database name to specify, if any.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function joinFull($name, $cond, $cols = self::SQL_WILDCARD, $schema = null)
    {
        $this->_select_query->joinFull($name, $cond, $cols, $schema);
    }

    /**
     * Add a CROSS JOIN table and colums to the query.
     * A cross join is a cartesian product; there is no join condition.
     *
     * The $name and $cols parameters follow the same logic
     * as described in the from() method.
     *
     * @param  array|string|Zend_Db_Expr $name The table name.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The database name to specify, if any.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function joinCross($name, $cols = self::SQL_WILDCARD, $schema = null)
    {
        $this->_select_query->joinCross($name, $cols, $schema );
    }

    /**
     * Add a NATURAL JOIN table and colums to the query.
     * A natural join assumes an equi-join across any column(s)
     * that appear with the same name in both tables.
     * Only natural inner joins are supported by this API,
     * even though SQL permits natural outer joins as well.
     *
     * The $name and $cols parameters follow the same logic
     * as described in the from() method.
     *
     * @param  array|string|Zend_Db_Expr $name The table name.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The database name to specify, if any.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function joinNatural($name, $cols = self::SQL_WILDCARD, $schema = null)
    {
        $this->_select_query->joinNatural($name, $cols, $schema);
    }

   

    /**
     * Adds a WHERE condition to the query by OR.
     *
     * Otherwise identical to where().
     *
     * @param string   $cond  The WHERE condition.
     * @param mixed    $value OPTIONAL The value to quote into the condition.
     * @param constant $type  OPTIONAL The type of the given value
     * @return Zend_Db_Select This Zend_Db_Select object.
     *
     * @see where()
     */
    public function orWhere($cond, $value = null, $type = null)
    {
	$this->_select_query->orWhere($cond, $value, $type);
    }

    /**
     * Adds grouping to the query.
     *
     * @param  array|string $spec The column(s) to group by.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function group($spec)
    {
	$this->_select_query->group($spec);
    }

    /**
     * Adds a HAVING condition to the query by AND.
     *
     * If a value is passed as the second param, it will be quoted
     * and replaced into the condition wherever a question-mark
     * appears. See {@link where()} for an example
     *
     * @param string $cond The HAVING condition.
     * @param string|Zend_Db_Expr $val The value to quote into the condition.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function having($cond)
    {
	$this->_select_query->having($cond);
    }

    /**
     * Adds a HAVING condition to the query by OR.
     *
     * Otherwise identical to orHaving().
     *
     * @param string $cond The HAVING condition.
     * @param mixed  $val  The value to quote into the condition.
     * @return Zend_Db_Select This Zend_Db_Select object.
     *
     * @see having()
     */
    public function orHaving($cond)
    {
	$this->_select_query->orHaving($cond);
    }

    /**
     * Adds a row order to the query.
     *
     * @param mixed $spec The column(s) and direction to order by.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function order($spec)
    {
	$this->_select_query->order($spec);
    }

    /**
     * Sets a limit count and offset to the query.
     *
     * @param int $count OPTIONAL The number of rows to return.
     * @param int $offset OPTIONAL Start returning after this many rows.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function limit($count = null, $offset = null)
    {
	$this->_select_query->limit($count, $offset);
    }

    /**
     * Sets the limit and count by page number.
     *
     * @param int $page Limit results to this page number.
     * @param int $rowCount Use this many rows per page.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function limitPage($page, $rowCount)
    {
	$this->_select_query->limitPage($page, $rowCount);
    }

    /**
     * Makes the query SELECT FOR UPDATE.
     *
     * @param bool $flag Whether or not the SELECT is FOR UPDATE (default true).
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function forUpdate($flag = true)
    {
	$this->_select_query->forUpdate($flag);        
    }

    /**
     * Get part of the structured information for the currect query.
     *
     * @param string $part
     * @return mixed
     * @throws Zend_Db_Select_Exception
     */
    public function getPart($part)
    {
	$this->_select_query->getPart($part);
    }

    /**
     * Executes the current select object and returns the result
     *
     * @param integer $fetchMode OPTIONAL
     * @param  mixed  $bind An array of data to bind to the placeholders.
     * @return PDO_Statement|Zend_Db_Statement
     */
    public function query($fetchMode = null, $bind = array())
    {
	$this->_select_query->query($fetchMode, $bind);
    }

    /**
     * Converts this object to an SQL SELECT string.
     *
     * @return string|null This object as a SELECT string. (or null if a string cannot be produced.)
     */
    public function assemble()
    {
        $sql = self::SQL_SELECT;
        foreach (array_keys(self::$_partsInit) as $part) {
            $method = '_render' . ucfirst($part);
            if (method_exists($this, $method)) {
                $sql = $this->$method($sql);
            }
        }
        return $sql;
    }

    /**
     * Clear parts of the Select object, or an individual part.
     *
     * @param string $part OPTIONAL
     * @return Zend_Db_Select
     */
    public function reset($part = null)
    {
        if ($part == null) {
            $this->_parts = self::$_partsInit;
        } else if (array_key_exists($part, self::$_partsInit)) {
            $this->_parts[$part] = self::$_partsInit[$part];
        }
        return $this;
    }

    /**
     * Gets the Zend_Db_Adapter_Abstract for this
     * particular Zend_Db_Select object.
     *
     * @return Zend_Db_Adapter_Abstract
     */
    public function getAdapter()
    {
        return $this->_adapter;
    }

    /**
     * Populate the {@link $_parts} 'join' key
     *
     * Does the dirty work of populating the join key.
     *
     * The $name and $cols parameters follow the same logic
     * as described in the from() method.
     *
     * @param  null|string $type Type of join; inner, left, and null are currently supported
     * @param  array|string|Zend_Db_Expr $name Table name
     * @param  string $cond Join on this condition
     * @param  array|string $cols The columns to select from the joined table
     * @param  string $schema The database name to specify, if any.
     * @return Zend_Db_Select This Zend_Db_Select object
     * @throws Zend_Db_Select_Exception
     */
    protected function _join($type, $name, $cond, $cols, $schema = null)
    {
        if (!in_array($type, self::$_joinTypes) && $type != self::FROM) {
            /**
             * @see Zend_Db_Select_Exception
             */
            require_once 'Zend/Db/Select/Exception.php';
            throw new Zend_Db_Select_Exception("Invalid join type '$type'");
        }

        if (count($this->_parts[self::UNION])) {
            require_once 'Zend/Db/Select/Exception.php';
            throw new Zend_Db_Select_Exception("Invalid use of table with " . self::SQL_UNION);
        }

        if (empty($name)) {
            $correlationName = $tableName = '';
        } else if (is_array($name)) {
            // Must be array($correlationName => $tableName) or array($ident, ...)
            foreach ($name as $_correlationName => $_tableName) {
                if (is_string($_correlationName)) {
                    // We assume the key is the correlation name and value is the table name
                    $tableName = $_tableName;
                    $correlationName = $_correlationName;
                } else {
                    // We assume just an array of identifiers, with no correlation name
                    $tableName = $_tableName;
                    $correlationName = $this->_uniqueCorrelation($tableName);
                }
                break;
            }
        } else if ($name instanceof Zend_Db_Expr|| $name instanceof Zend_Db_Select) {
            $tableName = $name;
            $correlationName = $this->_uniqueCorrelation('t');
        } else if (preg_match('/^(.+)\s+AS\s+(.+)$/i', $name, $m)) {
            $tableName = $m[1];
            $correlationName = $m[2];
        } else {
            $tableName = $name;
            $correlationName = $this->_uniqueCorrelation($tableName);
        }

        // Schema from table name overrides schema argument
        if (!is_object($tableName) && false !== strpos($tableName, '.')) {
            list($schema, $tableName) = explode('.', $tableName);
        }

        $lastFromCorrelationName = null;
        if (!empty($correlationName)) {
            if (array_key_exists($correlationName, $this->_parts[self::FROM])) {
                /**
                 * @see Zend_Db_Select_Exception
                 */
                require_once 'Zend/Db/Select/Exception.php';
                throw new Zend_Db_Select_Exception("You cannot define a correlation name '$correlationName' more than once");
            }

            if ($type == self::FROM) {
                // append this from after the last from joinType
                $tmpFromParts = $this->_parts[self::FROM];
                $this->_parts[self::FROM] = array();
                // move all the froms onto the stack
                while ($tmpFromParts) {
                    $currentCorrelationName = key($tmpFromParts);
                    if ($tmpFromParts[$currentCorrelationName]['joinType'] != self::FROM) {
                        break;
                    }
                    $lastFromCorrelationName = $currentCorrelationName;
                    $this->_parts[self::FROM][$currentCorrelationName] = array_shift($tmpFromParts);
                }
            } else {
                $tmpFromParts = array();
            }
            $this->_parts[self::FROM][$correlationName] = array(
                'joinType'      => $type,
                'schema'        => $schema,
                'tableName'     => $tableName,
                'joinCondition' => $cond
                );
            while ($tmpFromParts) {
                $currentCorrelationName = key($tmpFromParts);
                $this->_parts[self::FROM][$currentCorrelationName] = array_shift($tmpFromParts);
            }
        }

        // add to the columns from this joined table
        if ($type == self::FROM && $lastFromCorrelationName == null) {
            $lastFromCorrelationName = true;
        }
        $this->_tableCols($correlationName, $cols, $lastFromCorrelationName);

        return $this;
    }

    /**
     * Handle JOIN... USING... syntax
     *
     * This is functionality identical to the existing JOIN methods, however
     * the join condition can be passed as a single column name. This method
     * then completes the ON condition by using the same field for the FROM
     * table and the JOIN table.
     *
     * <code>
     * $select = $db->select()->from('table1')
     *                        ->joinUsing('table2', 'column1');
     *
     * // SELECT * FROM table1 JOIN table2 ON table1.column1 = table2.column2
     * </code>
     *
     * These joins are called by the developer simply by adding 'Using' to the
     * method name. E.g.
     * * joinUsing
     * * joinInnerUsing
     * * joinFullUsing
     * * joinRightUsing
     * * joinLeftUsing
     *
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function _joinUsing($type, $name, $cond, $cols = '*', $schema = null)
    {
        if (empty($this->_parts[self::FROM])) {
            require_once 'Zend/Db/Select/Exception.php';
            throw new Zend_Db_Select_Exception("You can only perform a joinUsing after specifying a FROM table");
        }

        $join  = $this->_adapter->quoteIdentifier(key($this->_parts[self::FROM]), true);
        $from  = $this->_adapter->quoteIdentifier($this->_uniqueCorrelation($name), true);

        $cond1 = $from . '.' . $cond;
        $cond2 = $join . '.' . $cond;
        $cond  = $cond1 . ' = ' . $cond2;

        return $this->_join($type, $name, $cond, $cols, $schema);
    }

    /**
     * Generate a unique correlation name
     *
     * @param string|array $name A qualified identifier.
     * @return string A unique correlation name.
     */
    private function _uniqueCorrelation($name)
    {
        if (is_array($name)) {
            $c = end($name);
        } else {
            // Extract just the last name of a qualified table name
            $dot = strrpos($name,'.');
            $c = ($dot === false) ? $name : substr($name, $dot+1);
        }
        for ($i = 2; array_key_exists($c, $this->_parts[self::FROM]); ++$i) {
            $c = $name . '_' . (string) $i;
        }
        return $c;
    }

    /**
     * Adds to the internal table-to-column mapping array.
     *
     * @param  string $tbl The table/join the columns come from.
     * @param  array|string $cols The list of columns; preferably as
     * an array, but possibly as a string containing one column.
     * @param  bool|string True if it should be prepended, a correlation name if it should be inserted
     * @return void
     */
    protected function _tableCols($correlationName, $cols, $afterCorrelationName = null)
    {
        if (!is_array($cols)) {
            $cols = array($cols);
        }

        if ($correlationName == null) {
            $correlationName = '';
        }

        $columnValues = array();

        foreach (array_filter($cols) as $alias => $col) {
            $currentCorrelationName = $correlationName;
            if (is_string($col)) {
                // Check for a column matching "<column> AS <alias>" and extract the alias name
                if (preg_match('/^(.+)\s+' . self::SQL_AS . '\s+(.+)$/i', $col, $m)) {
                    $col = $m[1];
                    $alias = $m[2];
                }
                // Check for columns that look like functions and convert to Zend_Db_Expr
                if (preg_match('/\(.*\)/', $col)) {
                    $col = new Zend_Db_Expr($col);
                } elseif (preg_match('/(.+)\.(.+)/', $col, $m)) {
                    $currentCorrelationName = $m[1];
                    $col = $m[2];
                }
            }
            $columnValues[] = array($currentCorrelationName, $col, is_string($alias) ? $alias : null);
        }

        if ($columnValues) {

            // should we attempt to prepend or insert these values?
            if ($afterCorrelationName === true || is_string($afterCorrelationName)) {
                $tmpColumns = $this->_parts[self::COLUMNS];
                $this->_parts[self::COLUMNS] = array();
            } else {
                $tmpColumns = array();
            }

            // find the correlation name to insert after
            if (is_string($afterCorrelationName)) {
                while ($tmpColumns) {
                    $this->_parts[self::COLUMNS][] = $currentColumn = array_shift($tmpColumns);
                    if ($currentColumn[0] == $afterCorrelationName) {
                        break;
                    }
                }
            }

            // apply current values to current stack
            foreach ($columnValues as $columnValue) {
                array_push($this->_parts[self::COLUMNS], $columnValue);
            }

            // finish ensuring that all previous values are applied (if they exist)
            while ($tmpColumns) {
                array_push($this->_parts[self::COLUMNS], array_shift($tmpColumns));
            }
        }
    }

    /**
     * Internal function for creating the where clause
     *
     * @param string   $condition
     * @param mixed    $value  optional
     * @param string   $type   optional
     * @param boolean  $bool  true = AND, false = OR
     * @return string  clause
     */
    protected function _where($condition, $value = null, $type = null, $bool = true)
    {
        if (count($this->_parts[self::UNION])) {
            require_once 'Zend/Db/Select/Exception.php';
            throw new Zend_Db_Select_Exception("Invalid use of where clause with " . self::SQL_UNION);
        }

        if ($value !== null) {
            $condition = $this->_adapter->quoteInto($condition, $value, $type);
        }

        $cond = "";
        if ($this->_parts[self::WHERE]) {
            if ($bool === true) {
                $cond = self::SQL_AND . ' ';
            } else {
                $cond = self::SQL_OR . ' ';
            }
        }

        return $cond . "($condition)";
    }

    /**
     * @return array
     */
    protected function _getDummyTable()
    {
        return array();
    }

    /**
     * Return a quoted schema name
     *
     * @param string   $schema  The schema name OPTIONAL
     * @return string|null
     */
    protected function _getQuotedSchema($schema = null)
    {
        if ($schema === null) {
            return null;
        }
        return $this->_adapter->quoteIdentifier($schema, true) . '.';
    }

    /**
     * Return a quoted table name
     *
     * @param string   $tableName        The table name
     * @param string   $correlationName  The correlation name OPTIONAL
     * @return string
     */
    protected function _getQuotedTable($tableName, $correlationName = null)
    {
        return $this->_adapter->quoteTableAs($tableName, $correlationName, true);
    }

    /**
     * Render DISTINCT clause
     *
     * @param string   $sql SQL query
     * @return string
     */
    protected function _renderDistinct($sql)
    {
        if ($this->_parts[self::DISTINCT]) {
            $sql .= ' ' . self::SQL_DISTINCT;
        }

        return $sql;
    }

    /**
     * Render DISTINCT clause
     *
     * @param string   $sql SQL query
     * @return string|null
     */
    protected function _renderColumns($sql)
    {
        if (!count($this->_parts[self::COLUMNS])) {
            return null;
        }

        $columns = array();
        foreach ($this->_parts[self::COLUMNS] as $columnEntry) {
            list($correlationName, $column, $alias) = $columnEntry;
            if ($column instanceof Zend_Db_Expr) {
                $columns[] = $this->_adapter->quoteColumnAs($column, $alias, true);
            } else {
                if ($column == self::SQL_WILDCARD) {
                    $column = new Zend_Db_Expr(self::SQL_WILDCARD);
                    $alias = null;
                }
                if (empty($correlationName)) {
                    $columns[] = $this->_adapter->quoteColumnAs($column, $alias, true);
                } else {
                    $columns[] = $this->_adapter->quoteColumnAs(array($correlationName, $column), $alias, true);
                }
            }
        }

        return $sql .= ' ' . implode(', ', $columns);
    }

    /**
     * Render FROM clause
     *
     * @param string   $sql SQL query
     * @return string
     */
    protected function _renderFrom($sql)
    {
        /*
         * If no table specified, use RDBMS-dependent solution
         * for table-less query.  e.g. DUAL in Oracle.
         */
        if (empty($this->_parts[self::FROM])) {
            $this->_parts[self::FROM] = $this->_getDummyTable();
        }

        $from = array();

        foreach ($this->_parts[self::FROM] as $correlationName => $table) {
            $tmp = '';

            $joinType = ($table['joinType'] == self::FROM) ? self::INNER_JOIN : $table['joinType'];

            // Add join clause (if applicable)
            if (! empty($from)) {
                $tmp .= ' ' . strtoupper($joinType) . ' ';
            }

            $tmp .= $this->_getQuotedSchema($table['schema']);
            $tmp .= $this->_getQuotedTable($table['tableName'], $correlationName);

            // Add join conditions (if applicable)
            if (!empty($from) && ! empty($table['joinCondition'])) {
                $tmp .= ' ' . self::SQL_ON . ' ' . $table['joinCondition'];
            }

            // Add the table name and condition add to the list
            $from[] = $tmp;
        }

        // Add the list of all joins
        if (!empty($from)) {
            $sql .= ' ' . self::SQL_FROM . ' ' . implode("\n", $from);
        }

        return $sql;
    }

    /**
     * Render UNION query
     *
     * @param string   $sql SQL query
     * @return string
     */
    protected function _renderUnion($sql)
    {
        if ($this->_parts[self::UNION]) {
            $parts = count($this->_parts[self::UNION]);
            foreach ($this->_parts[self::UNION] as $cnt => $union) {
                list($target, $type) = $union;
                if ($target instanceof Zend_Db_Select) {
                    $target = $target->assemble();
                }
                $sql .= $target;
                if ($cnt < $parts - 1) {
                    $sql .= ' ' . $type . ' ';
                }
            }
        }

        return $sql;
    }

    /**
     * Render WHERE clause
     *
     * @param string   $sql SQL query
     * @return string
     */
    protected function _renderWhere($sql)
    {
        if ($this->_parts[self::FROM] && $this->_parts[self::WHERE]) {
            $sql .= ' ' . self::SQL_WHERE . ' ' .  implode(' ', $this->_parts[self::WHERE]);
        }

        return $sql;
    }

    /**
     * Render GROUP clause
     *
     * @param string   $sql SQL query
     * @return string
     */
    protected function _renderGroup($sql)
    {
        if ($this->_parts[self::FROM] && $this->_parts[self::GROUP]) {
            $group = array();
            foreach ($this->_parts[self::GROUP] as $term) {
                $group[] = $this->_adapter->quoteIdentifier($term, true);
            }
            $sql .= ' ' . self::SQL_GROUP_BY . ' ' . implode(",\n\t", $group);
        }

        return $sql;
    }

    /**
     * Render HAVING clause
     *
     * @param string   $sql SQL query
     * @return string
     */
    protected function _renderHaving($sql)
    {
        if ($this->_parts[self::FROM] && $this->_parts[self::HAVING]) {
            $sql .= ' ' . self::SQL_HAVING . ' ' . implode(' ', $this->_parts[self::HAVING]);
        }

        return $sql;
    }

    /**
     * Render ORDER clause
     *
     * @param string   $sql SQL query
     * @return string
     */
    protected function _renderOrder($sql)
    {
        if ($this->_parts[self::ORDER]) {
            $order = array();
            foreach ($this->_parts[self::ORDER] as $term) {
                if (is_array($term)) {
                    if(is_numeric($term[0]) && strval(intval($term[0])) == $term[0]) {
                        $order[] = (int)trim($term[0]) . ' ' . $term[1];
                    } else {
                        $order[] = $this->_adapter->quoteIdentifier($term[0], true) . ' ' . $term[1];
                    }
                } else if (is_numeric($term) && strval(intval($term)) == $term) {
                    $order[] = (int)trim($term);
                } else {
                    $order[] = $this->_adapter->quoteIdentifier($term, true);
                }
            }
            $sql .= ' ' . self::SQL_ORDER_BY . ' ' . implode(', ', $order);
        }

        return $sql;
    }

    /**
     * Render LIMIT OFFSET clause
     *
     * @param string   $sql SQL query
     * @return string
     */
    protected function _renderLimitoffset($sql)
    {
        $count = 0;
        $offset = 0;

        if (!empty($this->_parts[self::LIMIT_OFFSET])) {
            $offset = (int) $this->_parts[self::LIMIT_OFFSET];
            $count = PHP_INT_MAX;
        }

        if (!empty($this->_parts[self::LIMIT_COUNT])) {
            $count = (int) $this->_parts[self::LIMIT_COUNT];
        }

        /*
         * Add limits clause
         */
        if ($count > 0) {
            $sql = trim($this->_adapter->limit($sql, $count, $offset));
        }

        return $sql;
    }

    /**
     * Render FOR UPDATE clause
     *
     * @param string   $sql SQL query
     * @return string
     */
    protected function _renderForupdate($sql)
    {
        if ($this->_parts[self::FOR_UPDATE]) {
            $sql .= ' ' . self::SQL_FOR_UPDATE;
        }

        return $sql;
    }

    /**
     * Turn magic function calls into non-magic function calls
     * for joinUsing syntax
     *
     * @param string $method
     * @param array $args OPTIONAL Zend_Db_Table_Select query modifier
     * @return Zend_Db_Select
     * @throws Zend_Db_Select_Exception If an invalid method is called.
     */
    public function __call($method, array $args)
    {
        $matches = array();

        /**
         * Recognize methods for Has-Many cases:
         * findParent<Class>()
         * findParent<Class>By<Rule>()
         * Use the non-greedy pattern repeat modifier e.g. \w+?
         */
        if (preg_match('/^join([a-zA-Z]*?)Using$/', $method, $matches)) {
            $type = strtolower($matches[1]);
            if ($type) {
                $type .= ' join';
                if (!in_array($type, self::$_joinTypes)) {
                    require_once 'Zend/Db/Select/Exception.php';
                    throw new Zend_Db_Select_Exception("Unrecognized method '$method()'");
                }
                if (in_array($type, array(self::CROSS_JOIN, self::NATURAL_JOIN))) {
                    require_once 'Zend/Db/Select/Exception.php';
                    throw new Zend_Db_Select_Exception("Cannot perform a joinUsing with method '$method()'");
                }
            } else {
                $type = self::INNER_JOIN;
            }
            array_unshift($args, $type);
            return call_user_func_array(array($this, '_joinUsing'), $args);
        }

        require_once 'Zend/Db/Select/Exception.php';
        throw new Zend_Db_Select_Exception("Unrecognized method '$method()'");
    }

    /**
     * Implements magic method.
     *
     * @return string This object as a SELECT string.
     */
    public function __toString()
    {
        try {
            $sql = $this->assemble();
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            $sql = '';
        }
        return (string)$sql;
    }

}
