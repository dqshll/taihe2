<?php
$result = array('error'=>1, 'msg'=>'参数错误');

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action == "cha_info" && isset($_GET['cid'])) {
        $cha_id = $_GET['cid'];
        $cha_point_list = array('23,45', '55,65', '87,11');
        $cha_pic_url = 'https://miniapp.edisonx.cn/data/files/cha/pics/' . $cha_id .'.jpg';
        
        $info = array('pos'=>$cha_point_list, 'url'=>$cha_pic_url);
        
        $result = array('error'=>0, 'info'=>$info);
    } 
    // else if ($action == "remove") {
    //     $result = onRemoveHandler($_GET['id']);
    // }
}

echo json_encode($result);

// $ver_info_list = array();

// foreach($app_list as $app_name) {
//     $result = null;
//     if (isset($app_name)) {
//         $result = checkDb($app_name);
//     }
//     if ($result['app_version'] !== null) {
//         array_push($ver_info_list, $result);
//     }
// }

// echo json_encode($ver_info_list);

function checkDb ($app_name) {
    $result = array('error'=>0);
    $db_connection = mysql_connect("localhost","root","e5cda60c7e");

    mysql_query("set names 'utf8'"); //数据库输出编码

    mysql_select_db("game"); //打开数据库

    $sql = "select * from app_pub where app_name = '$app_name' ORDER BY version DESC LIMIT 1";

//    echo $sql;

    $ver_info = mysql_query($sql);

//    var_dump($ver_info);

    if ($ver_info === false) { // 没有该app的版本
    } else {
        $msg = mysql_fetch_array($ver_info);

//        var_dump($msg);
        $cur_version = $msg['version'];
        $cur_app_url = $msg['file_path'];
        $result['app_name'] = $app_name;
        $result['app_version'] = $cur_version;
        $result['file_type'] = $msg['file_type'];
        $result['dl_url'] = str_ireplace('/alidata/www/default', 'http://h5.edisonx.cn', $cur_app_url);
//        var_dump($result);
    }
    mysql_close(); 
    return $result;
}

//function curSystime() {
//    list($t1, $t2) = explode(' ', microtime());
//    return (float)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);
//}
//
//function toDTS($value) {
//    if ($value === 0) {
//        return '0';
//    } else {
//        return date("Y-m-d@H:i:s" , substr($value,0,10));
//    }
//}

?>