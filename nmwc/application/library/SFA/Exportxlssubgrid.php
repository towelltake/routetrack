<?php

/**
 * @name       SFA_Exportxlssubgrid
 * @since      30-11-2012
 * @version    Release: 1
 * @author     PT
 * @copyright  Elan Technologies
 * @param
 * This Class contains all the General functions For export Xls
 */

class SFA_Exportxlssubgrid
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
        
        for( $i = 0; $i < count($this->data_arr["columns"]["maingrid"]); $i++)
        {
            $fc = changepos($this->currente_column_alpha,'+',$i).$this->currente_column_num;
            $this->objPHPExcel->getActiveSheet()->getStyle($fc)->getAlignment()->setWrapText(true);
            
            $this->objPHPExcel->getActiveSheet()->getStyle($fc)->getFont()->setBold(true);
            $this->objPHPExcel->getActiveSheet()->setCellValue($fc, $this->data_arr["columns"]["maingrid"][$i]);
            $width = (isset($this->data_arr["columns_config"]["main_column"][$i]["width"]) && $this->data_arr["columns_config"]["main_column"][$i]["width"]!= "") ? $this->data_arr["columns_config"]["main_column"][$i]["width"] : $this->default_arr["column_width"];
            $this->objPHPExcel->getActiveSheet()->getColumnDimension(changepos($this->currente_column_alpha,'+',$i))->setWidth($width);
            if($this->session->lang == "ar_AR") {
                $this->objPHPExcel->getActiveSheet()->getStyle($fc)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
            }
        }
        $fc = changepos($this->currente_column_alpha,'+',0).$this->currente_column_num;
        $sc = changepos($this->currente_column_alpha,'+',count($this->data_arr["columns"]["maingrid"])-1).$this->currente_column_num;
        $this->objPHPExcel->getActiveSheet()->getStyle($fc.":".$sc)->getBorders()->getBottom()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);
        $this->objPHPExcel->getActiveSheet()->getStyle($fc.":".$sc)->applyFromArray($this->color[$this->default_arr["column_colour"]]);
    }
    
    
    /**
	* @name       setsubgridColumn
	* @since      30-11-2011
	* @version    Release: 1
	* @author     PT
	* @copyright  Elan Technologies
	* This is used to set heading of the report
	*/
	public function setsubgridColumn()
	{
        $this->currente_column_num+=1;
        
        for( $i = 0; $i < count($this->data_arr["columns"]["subgrid"]); $i++)
        {
            $fc = changepos($this->currente_column_alpha,'+',$i+1).$this->currente_column_num;
            $this->objPHPExcel->getActiveSheet()->getStyle($fc)->getAlignment()->setWrapText(true);
            
            $this->objPHPExcel->getActiveSheet()->getStyle($fc)->getFont()->setBold(true);
            $this->objPHPExcel->getActiveSheet()->setCellValue($fc, $this->data_arr["columns"]["subgrid"][$i]);
            $width = (isset($this->data_arr["columns_config"]["subgrid_column"][$i]["width"]) && $this->data_arr["columns_config"]["subgrid_column"][$i]["width"]!= "") ? $this->data_arr["columns_config"]["subgrid_column"][$i]["width"] : $this->default_arr["column_width"];
            $this->objPHPExcel->getActiveSheet()->getColumnDimension(changepos($this->currente_column_alpha,'+',$i))->setWidth($width);
            if($this->session->lang == "ar_AR") {
                $this->objPHPExcel->getActiveSheet()->getStyle($fc)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
            }
        }
        $fc = changepos($this->currente_column_alpha,'+',1).$this->currente_column_num;
        $sc = changepos($this->currente_column_alpha,'+',count($this->data_arr["columns"]["subgrid"])).$this->currente_column_num;
        $this->objPHPExcel->getActiveSheet()->getStyle($fc.":".$sc)->getBorders()->getBottom()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);
        //$this->objPHPExcel->getActiveSheet()->getStyle($fc.":".$sc)->applyFromArray($this->color[$this->default_arr["column_colour"]]);
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
        if(!empty($this->data_arr["columns_model"]["maingrid"]))
        {
            $this->applyrow($this->data_arr["columns_model"]["maingrid"],0,$this->currente_column_num);
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
        for($i=0;$i<count($data);$i++)
        {
            $this->currente_column_num+=1;
            for($j=0;$j<count($data[$i]);$j++)
            {
                $fc = changepos($this->currente_column_alpha,'+',$j).$this->currente_column_num;
                $this->objPHPExcel->getActiveSheet()->getStyle($fc)->getAlignment()->setWrapText(true);
                //$this->objPHPExcel->getActiveSheet()->getStyle($fc)->getFont()->setBold(true);
                $this->objPHPExcel->getActiveSheet()->getStyle($fc)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                if($this->session->lang == "ar_AR") {
                        $this->objPHPExcel->getActiveSheet()->getStyle($fc)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
                }
                $this->objPHPExcel->getActiveSheet()->setCellValue($fc, $data[$i][$j]);
            }
            $this->setsubgrid($data[$i]);
        }
    }
    
    /**
	* @name       setsubgrid()
	* @since      22-10-2011
	* @version    Release: 1
	* @author     PT
	* @copyright  Elan Technologies
	* This is used to set heading of the report
	*/
    public function setsubgrid($data = array())
    {
        if(isset($this->data_arr["columns_model"]["subgrid"][$data[0]][$data[1]][$data[2]]) && !empty($this->data_arr["columns_model"]["subgrid"][$data[0]][$data[1]][$data[2]]))
        {
            $this->setsubgridColumn();
            $this->currente_column_num+=1;
            $subgrid_arr = $this->data_arr["columns_model"]["subgrid"][$data[0]][$data[1]][$data[2]];
            //pr($subgrid_arr);
            $this->objPHPExcel->getActiveSheet()->getRowDimension($this->currente_column_num-1)->setOutlineLevel(1);
            $this->objPHPExcel->getActiveSheet()->getRowDimension($this->currente_column_num-1)->setCollapsed(false);
            $this->objPHPExcel->getActiveSheet()->getRowDimension($this->currente_column_num-1)->setVisible(false);
            for($i=0;$i< count($subgrid_arr) ;$i++) {
                $j=1;
                foreach($subgrid_arr[$i] as $key => $val)
                {
                    $fc = changepos($this->currente_column_alpha,'+',$j).$this->currente_column_num;
                    $this->objPHPExcel->getActiveSheet()->getStyle($fc)->getAlignment()->setWrapText(true);
                    
                    $this->objPHPExcel->getActiveSheet()->getRowDimension($this->currente_column_num)->setOutlineLevel(1);
                    $this->objPHPExcel->getActiveSheet()->getRowDimension($this->currente_column_num)->setCollapsed(false);
                    $this->objPHPExcel->getActiveSheet()->getRowDimension($this->currente_column_num)->setVisible(false);
                    
                    $this->objPHPExcel->getActiveSheet()->setCellValue($fc, $val);
                    if(isset($this->data_arr["config"]["row_height"]) && $this->data_arr["config"]["row_height"] != "") {
                        $this->objPHPExcel->getActiveSheet()->getRowDimension($this->currente_column_num)->setRowHeight($this->data_arr["config"]["row_height"]);
                    }
                    $this->objPHPExcel->getActiveSheet()->getStyle($fc)->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
                    if($this->session->lang == "ar_AR") {
                            $this->objPHPExcel->getActiveSheet()->getStyle($fc)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
                    }
                    $j++;
                }
                $this->currente_column_num+=1;
            }
            //for($i=0;$i < count();$i++)
            //{
            //    $this->data_arr["columns_model"]["subgrid"][$data[0]][$data[1]][$data[2]][$i];
            //}
        }
        
    }
}
?>