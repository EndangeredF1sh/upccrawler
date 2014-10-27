<?php
header("Content-type:image/jpeg");

$id=$_GET['id'];
$img=file_get_contents('http://jwxt.upc.edu.cn/jwxt/uploadfile/studentphoto/pic/'.$id.'.JPG');

if ($img === false) {
    // 200x300
} else {
    echo $img;
}
