<?php
$result = array('error'=>1, 'msg'=>'参数错误');

$DB_HOST = 'api.edisonx.cn';
$DB_NAME = 'taihe';

// if (isset($_GET['action'])) {
//     $action = $_GET['action'];
//     if ($action == "cha_info" && isset($_GET['cid'])) {
//         $cha_id = $_GET['cid'];
//         $cha_point_list = array('23,45', '55,65', '87,11');
//         $cha_pic_url = 'https://miniapp.edisonx.cn/data/files/cha/pics/' . $cha_id .'.jpg';
        
//         $info = array('pos'=>$cha_point_list, 'url'=>$cha_pic_url);
        
//         $result = array('error'=>0, 'info'=>$info);
//     } 
//     // else if ($action == "remove") {
//     //     $result = onRemoveHandler($_GET['id']);
//     // }
// }

// echo json_encode($result);

$result = array('error'=>101);
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action == "upload") {
        $result = onUploadHandler();
    }
} else if (isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action == "query") {
        $result['error'] = 0;
        $result['list'] = onQueryHandler();
    } else if ($action == "remove") {
        $result = onRemoveHandler($_GET['id']);
    }
}
echo json_encode($result);

/** Query */
function onQueryHandler () {
    $last_ver_pkg_map = array();
    global $DB_HOST, $DB_NAME;

    $db_connection = mysql_connect($DB_HOST,"root","e5cda60c7e");

    mysql_query("set names 'utf8'"); //数据库输出编码

    mysql_select_db($DB_NAME); //打开数据库

    $sql = "select * from find_action";

    $all_actions = mysql_query($sql);

    if ($all_actions !== false) { // 空
        $curTimeStamp = curSystime();

        while ($item = mysql_fetch_array($all_actions)) {

            print_r($item);

            $start_time = $item['start_time']; 
            $end_time = $item['end_time'];

            echo "start time = $start_time end time = $end_time";

            if (!empty($start_time) && $curTimeStamp < $start_time) {
                echo ('start time not continue');
                continue;
            }
            if (!empty($end_time) && $curTimeStamp > $end_time) {
                echo ('end time ok continue');
                continue;
            }
            $enable = $item['enable']; 

            if (!$enable) {
                continue;
            }

            $aid = $item['aid'];
            $name = $item['name'];
            $packages = $item['packages'];

            echo "handling $name : $packages";
            
        }
    }

    return;

    $sql = "select * from find_pkg";

    // echo $sql;

    $all_info = mysql_query($sql);

    if ($all_info !== false) { // 空
        while ($item = mysql_fetch_array($all_info)) {
            $key = $item['id'];
            $value = array('version'=>$item['version'], 
                'pkg_name'=>$item['pkg_name'], 
                'time'=>$item['upload_time'], 
                'dur'=>$item['duration'], 
                'pos'=>$item['point_info'],
                'w'=>$item['width'],
                'h'=>$item['height'],
                // 'url'=>str_ireplace('/alidata/www/default', 'http://h5.edisonx.cn', $item['file_path']));
                'url'=>$item['file_path']);
            if((!array_key_exists($key,$last_ver_pkg_map)) || 
               ($last_ver_pkg_map[$key]['version'] < $value['version'])) {
                $last_ver_pkg_map[$key] = $value;
            }
        }
    }
    mysql_close(); 
    return $last_ver_pkg_map;
}

/** Upload */
function onUploadHandler() {
    $result = array('error'=>$_FILES['upImgA']['error']);
    if ($result['error'] === 0) {
        $pkgName = $_POST['name'];
        $duration = $_POST['dur'];
        $version = $_POST['ver'];
        $PosList = $_POST['p'];

        if (isset($pkgName) && isset($duration) && isset($version) && isset($PosList)) {
            $target_path_A = dirname(dirname(__FILE__)) . "/pkg/A.jpg";
            $target_path_B = dirname(dirname(__FILE__)) . "/pkg/B.jpg";
            $target_path_P = dirname(dirname(__FILE__)) . "/pkg/p.txt";
            posList2File($target_path_P, $PosList);

            if (!move_uploaded_file($_FILES['upImgA']['tmp_name'], $target_path_A)) {
                $result['error'] = 104;
                echo json_encode($result);
                return $result;
            }
            if (!move_uploaded_file($_FILES['upImgB']['tmp_name'], $target_path_B)) {
                $result['error'] = 104;
                echo json_encode($result);
                return $result;
            }

            $zip_path = dirname($target_path_A) . "/$pkgName-$version.zip";
            zipFile($target_path_A, $target_path_B, $target_path_P, $zip_path);
            $result['error'] = save2Db($pkgName, $version, $zip_path, $PosList);
        } else {
            $result['error'] = 103;
        }
    }
    return $result;
}

function posList2File($path, $txt) {
    $pfile = fopen($path, "w") or die("Unable to open file!");
    fwrite($pfile, $txt);
    fclose($pfile);
}

function zipFile($file_path_A, $file_path_B, $file_path_P, $zip_file_path) {
    $zip = new ZipArchive();
    $zip->open($zip_file_path,ZipArchive::CREATE);   //打开压缩包
    $zip->addFile($file_path_A,basename($file_path_A));   //向压缩包中添加文件
    $zip->addFile($file_path_B,basename($file_path_B));   
    $zip->addFile($file_path_P,basename($file_path_P));   
    $zip->close();  //关闭压缩包
}

function save2Db ($pkg_name, $version, $file_path, $pos_list) {
    $error = 0;
    global $DB_HOST, $DB_NAME;
    $db_connection = mysql_connect($DB_HOST,"root","e5cda60c7e");

    mysql_query("set names 'utf8'"); //数据库输出编码

    mysql_select_db($DB_NAME); //打开数据库

    $curtime = toDTS(curSystime());

    $sql = "select * from find_pkg_pub where pkg_name = '$pkg_name' ORDER BY version DESC LIMIT 1";

//    echo $sql;

    $result = mysql_query($sql);

    if ($result !== false) { //已经有该app的版本了

        $msg = mysql_fetch_array($result);

        $cur_version = $msg['version'];

        if ($cur_version >= $version) { // 不比当前版本高
            $error = 105;
//            echo $error;
        }
    }

    if ($error === 0) {
        $sql = "insert into find_pkg_pub (pkg_name,version,upload_time,file_path,point_info) 
        values ('$pkg_name','$version','$curtime','$file_path','$pos_list')";
        mysql_query($sql);
//        echo $sql;
    }

    mysql_close();
    return $error;
}

/* Remove */
function onRemoveHandler ($delID) {
    $result = array();
    global $DB_HOST, $DB_NAME;
    $db_connection = mysql_connect($DB_HOST,"root","e5cda60c7e");

    mysql_query("set names 'utf8'"); //数据库输出编码

    mysql_select_db($DB_NAME); //打开数据库

    $sql = "delete from find_pkg_pub where id=$delID";

    // echo $sql;

    $all_info = mysql_query($sql);

    // var_dump($all_info);

    if ($all_info !== false) { // 空
        $result['error'] = 0;
    } else {
        $result['error'] = 107;
    }
    mysql_close(); 
    return $result;
}

function curSystime() {
    list($t1, $t2) = explode(' ', microtime());
    return (float)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);
}

function toDTS($value) {
    if ($value === 0) {
        return '0';
    } else {
        return date("Y-m-d@H:i:s" , substr($value,0,10));
    }
}

?>