<?php
class SFA_Corepaging{ 

   /* These are defaults */ 

   var $CurrentPage=1;
   var $ResultsPerPage; 
   var $startOffset;
   var $QueryStr;
   var $Fields;
   var $Columns;
   var $Align;
   var $TotalCols;
   
   var $TotalRecords;
   var $TotalPages;
   var $PagingLength;
   
   var $FirstPage;
   var $PreviousPage;
   var $NextPage;
   var $LastPage;
   
   var $PagingStart;
   var $PagingEnd;
   
   var $showSearch;
   
   var $_select_query;
   var $db_paging;
   var $zend_instance;
   
    var $Common_NameSpace;
    
        public function __construct($QueryStr,$Fields,$Columns,$Align,$ResultsPerPage,$PagingLength,$CurrentPage,$showSearch=false,$totalCols=array())
        {
            $this->Common_NameSpace = new Zend_Session_Namespace('Common');
            $this->db_paging = Zend_Db_Table::getDefaultAdapter();
        
            if($CurrentPage>0)
               $this->CurrentPage = $CurrentPage;
            
            $this->ResultsPerPage = $ResultsPerPage;
            $this->PagingLength = $PagingLength;
            
            $this->startOffset = (($CurrentPage-1) * $ResultsPerPage);  
            $this->QueryStr = $this->getSearchQuery($QueryStr,$Fields);
            $this->Fields = $Fields;
            $this->Columns = $Columns;
            $this->Align    = $Align;
            $this->TotalCols = $totalCols;
            $this->showSearch = $showSearch;
          
            $tmp_result = $this->db_paging->fetchAll($this->QueryStr);
            $this->TotalRecords = count($tmp_result);
            
            $this->TotalPages = ceil($this->TotalRecords/$ResultsPerPage);
            $this->PagingLimit = 3;
            
            
            
            if($this->CurrentPage>=$this->TotalPages){
                $this->NextPage = "";
                $this->LastPage = "";
            }
            else
            {
                $this->NextPage = $this->CurrentPage+1;
                $this->LastPage = $this->TotalPages;
            }
            
            if($this->CurrentPage<=1){
                $this->PreviousPage = "";
                $this->FirstPage = "";
            }
            else
            {
                $this->PreviousPage = $this->CurrentPage-1;
                $this->FirstPage = "1";
            }
            
            $this->PagingStart = $this->CurrentPage-$PagingLength;
            $this->PagingEnd  = $this->CurrentPage+$PagingLength;
            
            if($this->PagingStart<1)
                $this->PagingStart = 1;
                
            if($this->PagingEnd>$this->TotalPages)
                $this->PagingEnd = $this->TotalPages;
        }
       
         
        function QueryOutput()
        {
                $query = $this->QueryStr." LIMIT ".$this->startOffset.",".$this->ResultsPerPage;
                return $this->db_paging->fetchAll($query);
        }
        
        function getSearchQuery($Query,$fields){
           
           
           
           if($this->Common_NameSpace->pageSearch!="")
           {
            
               $Query .= " AND ( 0 ";
               
               foreach($fields as $field)
               {
                   $Query .= " OR ". $field." LIKE '%".$this->Common_NameSpace->pageSearch."%'";
               }
               
               
               $Query .= ")";
               
              
               return $Query;
           }
           else
           {
               return $Query;
           }
       }
    }

    ?>