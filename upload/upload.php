<?php
if($_REQUEST['id']==1)
{
//$file_path="sfaind/".date("Ymd")."/";
$file_path="F:sfaind/".date("Ymd")."/";
}elseif($_REQUEST['id']==2)
{
$file_path="tab_db_safat/".date("Ymd")."/";
}elseif($_REQUEST['id']==3)
{
$file_path="tab_db_gtrc/".date("Ymd")."/";

}else
{
$file_path="tab_db_other/".date("Ymd")."/";

}

if(file_exists($file_path))
{

}else
{
	mkdir($file_path,0777,true);
}



$file_path = $file_path.basename($_FILES['uploaded_file']['name']);

if(move_uploaded_file($_FILES['uploaded_file']['tmp_name'],$file_path))
{
  echo "sucess";
}else 
{
  echo "fail";
}

?>
