<?php

/**
 * @name       SFA_Exportxls
 * @since      19-10-2012
 * @version    Release: 1
 * @author     PT
 * @copyright  Elan Technologies
 * @param
 * This Class contains all the General functions For export Xls
 */

class SFA_Exportxls
{
    public $currente_column_alpha = "A";
    public $currente_column_num = 1;
    public $color = array(  "black" => array('fill' 	=> array('type'	=> PHPExcel_Style_Fill::FILL_SOLID,'color'=> array('argb' => '#55555555')),'font'=> array('bold' => true,'color'=> array('argb' => '#FFFFFFFF'))),
                            "gray" => array('fill' 	=> array('type'	=> PHPExcel_Style_Fill::FILL_SOLID,'color'=> array('argb' => '#CCCCCCCC')),'font'=> array('bold' => true,'color'=> array('argb' => '#00000000'))),
                            "darkgray" => array('fill' 	=> array('type'	=> PHPExcel_Style_Fill::FILL_SOLID,'color'=> array('argb' => '#99999999'))),
                            "green" => array('font'=> array('bold' => true,'color'=> array('argb' => '#99CC0000'))),
                            "lightgray" => array('fill' => array('type'	=> PHPExcel_Style_Fill::FILL_SOLID,'color'=> array('argb' => '#AFAFAFAF')))
                         );
    public $objPHPExcel;
    public $session;
    public $data_arr;
    public $default_arr = array(
                                "column_width" => 17,
                                "heading_colour" => 'black',
                                "column_colour" => 'gray',
                                "group_colour" => 'lightgray',
                                "heading_height" => 25
                                );
    public $grouplevel_arr = array();
	/**
	* @name       __construct
	* @since      19-10-2012
	* @version    Release: 1
	* @author     PT
	* @copyright  Elan Technologies
	* This is used to define all the settings from the setting table.
	*/
	public function __construct($data_arr = array())
	{
        $this->objPHPExcel = new PHPExcel();
        $this->data_arr = $data_arr;
        
	}

	/**
	* @name       exportxls
	* @since      19-10-2011
	* @version    Release: 1
	* @author     PT
	* @copyright  Elan Technologies
	* This is used to export xls
	*/
	public function exportxls()
	{
        /**
         *  For the heading of the report
         */
        $this->session = new Zend_Session_Namespace('SESSION');
        $this->objPHPExcel->getActiveSheet()->getDefaultStyle()->getFont()->setSize(11)
                                                                          ->setName('Times New Roman');
        $this->setheading();
        //pr($this->data_arr,1);
        $this->objPHPExcel->getActiveSheet()->getDefaultStyle()->getFont()->setSize(8)
                                                                          ->setName('Tahoma');
        $this->setColumn();
        //$this->objPHPExcel->getActiveSheet()->getDefaultRowDimension()->setRowHeight(12);
        if(!empty($this->data_arr["columns_model"]))
            $this->setColumnModel();
        $this->setheader();
       //exit;
        return $this->objPHPExcel;
    }
    
    /**
	* @name       setheading
	* @since      19-10-2011
	* @version    Release: 1
	* @author     PT
	* @copyright  Elan Technologies
	* This is used to set heading of the report
	*/
	public function setheading()
	{
        
        /**
         *  For the heading of the report
         */
        $this->objPHPExcel->getProperties()->setTitle($this->data_arr["config"]["report_title"])
                                            ->setCategory($this->data_arr["config"]["report_title"]);
        
        $fc = $this->currente_column_alpha.$this->currente_column_num;
        $this->currente_column_num+=1;
        //$last_char = ($this->data_arr["config"]['total_columns'] > 25) ? 24 : $this->data_arr["config"]['total_columns'];
        $last_char =  ($this->data_arr["config"]['total_columns']-1);
        $sc = changepos($this->currente_column_alpha,'+',$last_char).($this->currente_column_num);
        //echo $this->data_arr["config"]['total_columns']."---".$last_char."--".$fc."--".$sc;exit;
        $this->objPHPExcel->getActiveSheet()->mergeCells($fc.":".$sc);
        $this->objPHPExcel->getActiveSheet()->getStyle($fc.":".$sc)->applyFromArray($this->color[$this->default_arr["heading_colour"]]);
        $this->objPHPExcel->getActiveSheet()->setCellValue($fc, $this->data_arr["config"]["report_title"]);
        
        if($this->session->lang == "ar_AR") {
            $this->objPHPExcel->getActiveSheet()->getStyle($fc)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
        }
        
        $report_title_height = (isset($this->data_arr["config"]["report_title_height"]) && $this->data_arr["config"]["report_title_height"]!= "") ? $this->data_arr["config"]["report_title_height"] : $this->default_arr["heading_height"];
        $this->objPHPExcel->getActiveSheet()->getRowDimension($this->currente_column_num)->setRowHeight($report_title_height);
    }
    
    /**
	* @name       exportheader
	* @since      19-10-2011
	* @version    Release: 1
	* @author     PT
	* @copyright  Elan Technologies
	* This is used to set heading of the report
	*/
	public function setheader()
	{
        $activesheet = (isset($this->data_arr["config"]["active_sheet"]) && $this->data_arr["config"]["active_sheet"] != "") ? $this->data_arr["config"]["active_sheet"] : 0;
        $this->objPHPExcel->setActiveSheetIndex($activesheet);
        if($this->session->lang == "ar_AR") {
            $this->objPHPExcel->getActiveSheet()->setRightToLeft(true);
            header('Content-type: text/plain; charset=UTF-8');
        }
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="'.$this->data_arr["config"]["file_name"].'.xls"');
        header('Cache-Control: max-age=0');
    }
    
    /**
	* @name       setColumn
	* @since      22-10-2011
	* @version    Release: 1
	* @author     PT
	* @copyright  Elan Technologies
	* This is used to set heading of the report
	*/
	public function setColumn()
	{
        $this->currente_column_num+=1;
        
        /*
        If there is and extra heading like November-2012, December-2012 in the heading
        */
        if(isset($this->data_arr["config"]["main_heading"]) && $this->data_arr["config"]["main_heading"] == "1")
        {
            for($i=0;$i<count($this->data_arr["main_heading_arr"]);$i++)
            {
                $fc = changepos($this->currente_column_alpha,'+',$this->data_arr["main_heading_arr"][$i]["start_index"]).$this->currente_column_num;
                $sc = changepos($this->currente_column_alpha,'+',$this->data_arr["main_heading_arr"][$i]["last_index"]).$this->currente_column_num;
                $this->objPHPExcel->getActiveSheet()->getStyle($fc)->getFont()->setBold(true);
                $this->objPHPExcel->getActiveSheet()->mergeCells($fc.":".$sc);
                $this->objPHPExcel->getActiveSheet()->setCellValue($fc, $this->data_arr["main_heading_arr"][$i]["title"]);
                $this->objPHPExcel->getActiveSheet()->getStyle($fc)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                $this->objPHPExcel->getActiveSheet()->getStyle($fc.":".$sc)->getBorders()->getBottom()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);
            }
            
            $fc = changepos($this->currente_column_alpha,'+',0).$this->currente_column_num;
            $sc = changepos($this->currente_column_alpha,'+',count($this->data_arr["columns"])-1).$this->currente_column_num;
            $this->objPHPExcel->getActiveSheet()->getStyle($fc.":".$sc)->applyFromArray($this->color[$this->default_arr["column_colour"]]);
            $this->currente_column_num +=1;
        }
        /*
          End Extra Heading
        */
        for( $i = 0; $i < count($this->data_arr["columns"]); $i++)
        {
            $fc = changepos($this->currente_column_alpha,'+',$i).$this->currente_column_num;
            $this->objPHPExcel->getActiveSheet()->getStyle($fc)->getAlignment()->setWrapText(true);
            
            //if(isset($this->data_arr["columns_config"][$i]["align"]) && $this->data_arr["columns_config"][$i]["align"] != "") {
            //    //$this->objPHPExcel->getActiveSheet()->getStyle(changepos($this->currente_column_alpha,'+',$i))->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
            //    $this->objPHPExcel->getActiveSheet()->getStyle('E6')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
            //}
            $this->objPHPExcel->getActiveSheet()->getStyle($fc)->getFont()->setBold(true);
            $this->objPHPExcel->getActiveSheet()->setCellValue($fc, $this->data_arr["columns"][$i]);
            $width = (isset($this->data_arr["columns_config"][$i]["width"]) && $this->data_arr["columns_config"][$i]["width"]!= "") ? $this->data_arr["columns_config"][$i]["width"] : $this->default_arr["column_width"];
            $this->objPHPExcel->getActiveSheet()->getColumnDimension(changepos($this->currente_column_alpha,'+',$i))->setWidth($width);
            if($this->session->lang == "ar_AR") {
                $this->objPHPExcel->getActiveSheet()->getStyle($fc)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
            }
        }
        $fc = changepos($this->currente_column_alpha,'+',0).$this->currente_column_num;
        $sc = changepos($this->currente_column_alpha,'+',count($this->data_arr["columns"])-1).$this->currente_column_num;
        $this->objPHPExcel->getActiveSheet()->getStyle($fc.":".$sc)->getBorders()->getBottom()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);
        $this->objPHPExcel->getActiveSheet()->getStyle($fc.":".$sc)->applyFromArray($this->color[$this->default_arr["column_colour"]]);
    }
    
    /**
	* @name       setColumnModel()
	* @since      22-10-2011
	* @version    Release: 1
	* @author     PT
	* @copyright  Elan Technologies
	* This is used to set heading of the report
	*/
	public function setColumnModel()
	{
        //pr($this->data_arr,1);
        if(!empty($this->data_arr["columns_model"]))
        {
            $this->applyrow($this->data_arr["columns_model"],0,$this->currente_column_num);
        }
        //pr($this->grouplevel_arr,1);
        if(isset($this->data_arr["config"]["main_total"]) && $this->data_arr["config"]["main_total"] == "1")
        {
            $row = $this->objPHPExcel->setActiveSheetIndex(0)->getHighestRow();
            $k=0;
            for($i=0;$i<count($this->data_arr["columns_config"]);$i++)
            {
                if(isset($this->data_arr["config"]["main_heading"]) && $this->data_arr["config"]["main_heading"]!= "") {
                    $start_row = 5;
                } else {
                    $start_row = 4;
                }
                $fc = changepos($this->currente_column_alpha,'+',($i)).$start_row;
                $sc = changepos($this->currente_column_alpha,'+',($i)).$row;
                
                if(isset($this->data_arr["columns_config"][$i]["total"]) && $this->data_arr["columns_config"][$i]["total"] == "1")
                {
                    $tc = changepos($this->currente_column_alpha,'+',($i)).($row+1);
                    $minus_str = "";
                    if((isset($this->data_arr["config"]["group_total"]) && $this->data_arr["config"]["group_total"] == "1") && (isset($this->grouplevel_arr) && !empty($this->grouplevel_arr)))
                    {
                        $minus_str = $this->grouplevel_arr[$k];
                        $k++;
                    }
                    //{
                    //    for($k=0;$k<count($this->grouplevel_arr);$k++) {
                    //        $minus_str .= "-".changepos($this->currente_column_alpha,'+',($i)).$this->grouplevel_arr[$k];
                    //    }
                    //}
                    $this->objPHPExcel->getActiveSheet()->setCellValue("$tc", '=SUM('.$fc.':'.$sc.')'.$minus_str);
                    if($this->session->lang == "ar_AR") {
                        $this->objPHPExcel->getActiveSheet()->getStyle($tc)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
                    }
                }
                elseif(isset($this->data_arr["columns_config"][$i]["toaltext"]) && $this->data_arr["columns_config"][$i]["toaltext"] != "")
                {
                    $tc = changepos($this->currente_column_alpha,'+',($i)).($row+1);
                    $this->objPHPExcel->getActiveSheet()->setCellValue("$tc", $this->data_arr["columns_config"][$i]["toaltext"]);
                    if($this->session->lang == "ar_AR") {
                        $this->objPHPExcel->getActiveSheet()->getStyle($tc)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
                    }
                }
            }
            $fc = changepos($this->currente_column_alpha,'+',0).($row+1);
            $sc = changepos($this->currente_column_alpha,'+',count($this->data_arr["columns"])-1).($row+1);
            $this->objPHPExcel->getActiveSheet()->getStyle($fc.":".$sc)->getBorders()->getBottom()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);
            $this->objPHPExcel->getActiveSheet()->getStyle($fc.":".$sc)->applyFromArray($this->color[$this->default_arr["column_colour"]]);
        }
        
    }
    
    /**
	* @name       applyrow()
	* @since      22-10-2011
	* @version    Release: 1
	* @author     PT
	* @copyright  Elan Technologies
	* This is used to set heading of the report
	*/
    public function applyrow($data = array(), $level = 0,$new_row = 1)
	{
        $this->currente_column_num+=1;
        //$new_row = $this->currente_column_num;
        //echo $this->currente_column_alpha."---".$level."<br />";
        //pr($data,1);
        if($level < $this->data_arr["config"]["group_level"])
        {
            //pr($data);
            foreach($data as $key => $val)
            {
                //echo $key."---".$level."<br />";
                //pr($val);
                $fc = changepos($this->currente_column_alpha,'+',($level)).$this->currente_column_num;
                $sc = changepos($this->currente_column_alpha,'+',($level+2)).$this->currente_column_num;
                $this->objPHPExcel->getActiveSheet()->mergeCells($fc.":".$sc);
                $this->objPHPExcel->getActiveSheet()->getStyle($fc)->getAlignment()->setWrapText(true);
                $this->objPHPExcel->getActiveSheet()->getStyle($fc)->getFont()->setBold(true);
                $this->objPHPExcel->getActiveSheet()->getStyle($fc)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                $this->objPHPExcel->getActiveSheet()->getStyle($fc.":".$sc)->applyFromArray($this->color[$this->default_arr["group_colour"]]);
                if($level != 0) {
                    $this->objPHPExcel->getActiveSheet()->getRowDimension($this->currente_column_num)->setOutlineLevel($level);
                    $this->objPHPExcel->getActiveSheet()->getRowDimension($this->currente_column_num)->setCollapsed(false);
                    $this->objPHPExcel->getActiveSheet()->getRowDimension($this->currente_column_num)->setVisible(false);
                }
                $this->objPHPExcel->getActiveSheet()->setCellValue($fc, $key);
                if($this->session->lang == "ar_AR") {
                    $this->objPHPExcel->getActiveSheet()->getStyle($fc)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
                }
                $this->applyrow($val,$level+1,$this->currente_column_num);
            }
        }
        else
        {
            if(isset($this->data_arr["config"]["group_total"]) && $this->data_arr["config"]["group_total"] == "1")
            {
                $start_index = $this->currente_column_num;
            }
            for($i=0;$i< count($data) ;$i++) {
                $j=0;
                foreach($data[$i] as $key => $val)
                {
                    $fc = changepos($this->currente_column_alpha,'+',($level+$j)).$this->currente_column_num;
                    $this->objPHPExcel->getActiveSheet()->getStyle($fc)->getAlignment()->setWrapText(true);
                    if($level != 0)
                    {
                        $this->objPHPExcel->getActiveSheet()->getRowDimension($this->currente_column_num)->setOutlineLevel($level);
                        $this->objPHPExcel->getActiveSheet()->getRowDimension($this->currente_column_num)->setCollapsed(false);
                        $this->objPHPExcel->getActiveSheet()->getRowDimension($this->currente_column_num)->setVisible(false);
                    }
                    $this->objPHPExcel->getActiveSheet()->setCellValue($fc, $val);
                    if($this->session->lang == "ar_AR") {
                        $this->objPHPExcel->getActiveSheet()->getStyle($fc)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
                    }
                    if(isset($this->data_arr["config"]["row_height"]) && $this->data_arr["config"]["row_height"] != "") {
                        $this->objPHPExcel->getActiveSheet()->getRowDimension($this->currente_column_num)->setRowHeight($this->data_arr["config"]["row_height"]);
                    }
                    $this->objPHPExcel->getActiveSheet()->getStyle($fc)->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
                    $j++;
                }
                $this->currente_column_num+=1;
            }
            //if(isset($this->data_arr["config"]["group_total"]) && $this->data_arr["config"]["group_total"] == "1")
            if(isset($this->data_arr["config"]["group_total"]) && $this->data_arr["config"]["group_total"] == "1")
            {
                $last_index = $this->currente_column_num-1;
                $k =0;
                for($i=0;$i<count($this->data_arr["columns_config"]);$i++)
                {
                    $fc = changepos($this->currente_column_alpha,'+',($i)).$start_index;
                    $sc = changepos($this->currente_column_alpha,'+',($i)).$last_index;
                    
                    if(isset($this->data_arr["columns_config"][$i]["group_total"]) && $this->data_arr["columns_config"][$i]["group_total"] == "1")
                    {
                        $tc = changepos($this->currente_column_alpha,'+',($i)).($last_index+1);
                        $this->objPHPExcel->getActiveSheet()->setCellValue("$tc", '=SUM('.$fc.':'.$sc.')');
                        if($this->session->lang == "ar_AR") {
                            $this->objPHPExcel->getActiveSheet()->getStyle($tc)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
                        }
                        $this->objPHPExcel->getActiveSheet()->getStyle($tc)->applyFromArray($this->color[$this->default_arr["group_colour"]]);
                        //if(isset($this->grouplevel_arr) && !empty($this->grouplevel_arr))
                        //{
                            $this->grouplevel_arr[$k] = $this->grouplevel_arr[$k]."-".$tc;
                            $k++;
                        //}
                        //else
                        //{
                        //    $this->grouplevel_arr[$k] = $tc;
                        //    $k++;
                        //}
                    }
                    elseif(isset($this->data_arr["columns_config"][$i]["group_total_text"]) && $this->data_arr["columns_config"][$i]["group_total_text"] != "")
                    {
                        $tc = changepos($this->currente_column_alpha,'+',($i)).($last_index+1);
                        $this->objPHPExcel->getActiveSheet()->setCellValue("$tc", $this->data_arr["columns_config"][$i]["group_total_text"]);
                        if($this->session->lang == "ar_AR") {
                            $this->objPHPExcel->getActiveSheet()->getStyle($tc)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
                        }
                        $this->objPHPExcel->getActiveSheet()->getStyle($tc)->applyFromArray($this->color[$this->default_arr["group_colour"]]);
                        $this->objPHPExcel->getActiveSheet()->getStyle($tc)->getFont()->setBold(true);
                    }
                    $this->objPHPExcel->getActiveSheet()->getRowDimension($this->currente_column_num)->setOutlineLevel($level);
                    $this->objPHPExcel->getActiveSheet()->getRowDimension($this->currente_column_num)->setCollapsed(false);
                    $this->objPHPExcel->getActiveSheet()->getRowDimension($this->currente_column_num)->setVisible(false);
                }
                //$this->grouplevel_arr[] = $last_index+1;
                $this->currente_column_num+=1;
            }
        }
    }
}
?>