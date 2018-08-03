<?php
$result = array('error'=>1, 'msg'=>'参数错误');

$DB_HOST = 'api.edisonx.cn';
$DB_NAME = 'taihe';

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
        $result['actions'] = onQueryHandler();
    } else if ($action == "action_list") {
        $result['error'] = 0;
        $result['actions'] = onActionList();
    } else if ($action == "action_detail" && isset($_GET['aid'])) {
        $result['error'] = 0;
        $result['actions'] = onActionDetail($_GET['aid']);
    } else if ($action == "action_add") {
        onActionAdd();
    } else if ($action == "action_update") {
        onActionUpdate();
    } else if ($action == "action_del") {
        $result = onActionDel();
    }
}
echo json_encode($result);

/** Query */
function onQueryHandler () {
    $actions = array();

    global $DB_HOST, $DB_NAME;

    $db_connection = mysql_connect($DB_HOST,"root","e5cda60c7e");

    mysql_query("set names 'utf8'"); //数据库输出编码

    mysql_select_db($DB_NAME); //打开数据库

    $sql = "select * from find_action";

    $all_actions = mysql_query($sql);

    if ($all_actions !== false) { // 空
        $curTimeStamp = curSystime();

        while ($item = mysql_fetch_array($all_actions)) {

            $start_time = strtotime($item['start_time']) * 1000; // s -> ms
            $end_time = strtotime($item['end_time']) * 1000; // s -> ms

            // echo "cur time = $curTimeStamp start time = $start_time end time = $end_time";

            if (!empty($start_time) && $curTimeStamp < $start_time) {
                // echo ('start time check failed');
                continue;
            }

            if (!empty($end_time) && $curTimeStamp > $end_time) {
                // echo ('end time check failed');
                continue;
            }

            $enable = $item['enable']; 

            if (!$enable) {
                continue;
            }

            $aid = $item['aid'];
            $name = $item['name'];
            $packages = $item['packages'];

            $actionPkg =  parseActionPackages ($packages);
            if (!empty($actionPkg) && count($actionPkg) > 0) {
                $actions[$aid] = $actionPkg;
            }
        }
    }

    mysql_close(); 

    return $actions;
}

function parseActionPackages($packages) {
    $last_ver_pkg_map = array();

    $sql = "select * from find_pkg where id in($packages) ORDER BY FIND_IN_SET(id,'$packages')";

    // echo $sql;

    $all_info = mysql_query($sql);

    if ($all_info !== false) { // 空
        while ($item = mysql_fetch_array($all_info)) {
            $value = array('sid'=>$item['id'], 
                'pkg_name'=>$item['pkg_name'], 
                'to'=>$item['redirect'], 
                'dur'=>$item['duration'], 
                'fdur'=>$item['follow_duration'], 
                'pos'=>$item['point_info'],
                'w'=>$item['width'],
                'h'=>$item['height'],
                // 'url'=>str_ireplace('/alidata/www/default', 'http://h5.edisonx.cn', $item['file_path']));
                'url'=>$item['img_url']);
           
            array_push($last_ver_pkg_map, $value);
        }
    }
    return $last_ver_pkg_map;
}


function onActionList () {
    $actions = array();

    global $DB_HOST, $DB_NAME;

    $db_connection = mysql_connect($DB_HOST,"root","e5cda60c7e");

    mysql_query("set names 'utf8'"); //数据库输出编码

    mysql_select_db($DB_NAME); //打开数据库

    $sql = "select * from find_action";

    $all_actions = mysql_query($sql);

    if ($all_actions !== false) { // 空
        // $curTimeStamp = curSystime();

        while ($item = mysql_fetch_array($all_actions)) {
            $enable = $item['enable']; 
            $aid = $item['aid'];
            $name = $item['name'];
            $data = array('aid'=>$aid,
                'name'=>$name, 
                'st'=>$item['start_time'], 
                'ed'=>$item['end_time'],
                'qr_video_url'=>"http://xxx/xxx/$aid.zip",
                'qr_pics_url'=>"http://yyy/yyy/$aid.zip",
                'enable'=>$enable);
            array_push($actions, $data);
        }
    }

    mysql_close(); 

    return $actions;
}

/** Query */
function onActionDetail ($actionId) {
    global $DB_HOST, $DB_NAME;

    $db_connection = mysql_connect($DB_HOST,"root","e5cda60c7e");

    mysql_query("set names 'utf8'"); //数据库输出编码

    mysql_select_db($DB_NAME); //打开数据库

    $sql = "select * from find_action where aid=" . $actionId;

    // echo $sql;
     
    $action_result = mysql_query($sql);

    // var_dump($action_result);

    $action = null;

    if ($action_result !== false) { // 空
        $curTimeStamp = curSystime();

        $item = mysql_fetch_array($action_result);

        $enable = $item['enable']; 
        $aid = $item['aid'];
        $name = $item['name'];
        $action = array('aid'=>$aid,
            'name'=>$name, 
            'st'=>$item['start_time'], 
            'ed'=>$item['end_time'],
            'qr_video_url'=>"http://xxx/xxx/$aid.zip",
            'qr_pics_url'=>"http://yyy/yyy/$aid.zip",
            'enable'=>$enable);

        $packages = $item['packages'];
        $actionPkg =  parseActionPackages ($packages);
        if (!empty($actionPkg) && count($actionPkg) > 0) {
            $action['stage'] = $actionPkg;
        }
    }

    mysql_close(); 

    return $action;
}

function onActionAdd () {
    global $result;

    $name = $_GET['name'];
    if(empty($name)) {
        $result['error'] = 102;
        return;
    }

    $stages_json = $_GET['stage'];
    if ( empty($stages_json) || strlen($stages_json) <= 0) {
        $result['error'] = 103;
        return;
    }

    $stages = json_decode($stages_json,true);
    if (count($stages) <= 0) {
        $result['error'] = 104;
        return;
    }

    $start_time = $_GET['st'];
    $end_time = $_GET['ed'];
    $enable = $_GET['enable'];
    $to = $_GET['to'];

    global $DB_HOST, $DB_NAME;

    $db_connection = mysql_connect($DB_HOST,"root","e5cda60c7e");

    mysql_query("set names 'utf8'"); //数据库输出编码

    mysql_select_db($DB_NAME); //打开数据库

    $pkg_ids = '';
    for ($i=0; $i< count($stages); $i++) {
        $stage = $stages[$i];

        $pkg_name = $stage['pkg_name'];
        $dur = $stage['dur'];
        $fdur = $stage['fdur'];
        $pos = $stage['pos'];
        $w = $stage['w'];
        $h = $stage['h'];
        $desc = $stage['desc'];
        $img_url = $stage['url'];
        if (empty($w) || empty($h) || empty($img_url) || empty($pos) || empty($dur) || empty($fdur) || empty($pkg_name)) {
            $result['error'] = 105;
            return;
        }

        $sql = "INSERT INTO find_pkg (pkg_name, point_info, description, img_url, duration, follow_duration, width, height) VALUES ('$pkg_name','$pos,$desc','$desc','$img_url','$dur','$fdur','$w','$h')";

        $insert_result = mysql_query($sql);
        if (!$insert_result) {
            $result['error'] = 106;
            return;
        }

        $lastId = mysql_insert_id($db_connection);

        if ($i == 0) {
            $pkg_ids = $lastId;
        } else {
            $pkg_ids = $pkg_ids . ',' . $lastId;
        }
    }

    $result['error'] = 0;

    $sql = "INSERT INTO find_action (name, packages, start_time, end_time, enable) VALUES ('$name','$pkg_ids','$start_time','$end_time','$enable')";
     
    $action_result = mysql_query($sql);

    // var_dump($action_result);

    if (!$action_result) { // 空
        $result['error'] = 112;
    }

    mysql_close(); 

    return;
}

function onActionUpdate () {
    global $result;
    
    $aid = $_GET['aid'];
    if(empty($aid)) {
        $result['error'] = 111;
        return;
    }

    $name = $_GET['name'];
    if(empty($name)) {
        $result['error'] = 102;
        return;
    }

    $stages_json = $_GET['stage'];
    if ( empty($stages_json) || strlen($stages_json) <= 0) {
        $result['error'] = 103;
        return;
    }

    $stages = json_decode($stages_json,true);
    if (count($stages) <= 0) {
        $result['error'] = 104;
        return;
    }

    $start_time = $_GET['st'];
    $end_time = $_GET['ed'];
    $enable = $_GET['enable'];
    $to = $_GET['to'];

    global $DB_HOST, $DB_NAME;

    $db_connection = mysql_connect($DB_HOST,"root","e5cda60c7e");

    mysql_query("set names 'utf8'"); //数据库输出编码

    mysql_select_db($DB_NAME); //打开数据库

    $pkg_ids = '';
    for ($i=0; $i< count($stages); $i++) {
        $stage = $stages[$i];

        $pkg_name = $stage['pkg_name'];
        $dur = $stage['dur'];
        $fdur = $stage['fdur'];
        $pos = $stage['pos'];
        $w = $stage['w'];
        $h = $stage['h'];
        $desc = $stage['desc'];
        $img_url = $stage['url'];
        if (empty($w) || empty($h) || empty($img_url) || empty($pos) || empty($dur) || empty($fdur) || empty($pkg_name)) {
            $result['error'] = 105;
            return;
        }

        $sid = $stage['sid'];

        $lastId = $sid;
        if (empty($sid)) { // new pkg shoud insert
            $sql = "INSERT INTO find_pkg (pkg_name, point_info, description, img_url, duration, follow_duration, width, height) VALUES ('$pkg_name','$pos','$desc','$img_url','$dur','$fdur','$w','$h')";
            
        } else {// old pkg should update
            $sql = "UPDATE find_pkg SET pkg_name='$pkg_name', point_info='$pos', description='$desc', img_url='$img_url', duration='$dur', follow_duration='$fdur', width='$w', height='$h' WHERE id='$sid'";
        }
        // echo $sql;

        $db_result = mysql_query($sql);
        if (!$db_result) {
            $result['error'] = 106;
            return;
        }

        if (empty($sid)) {
            $lastId = mysql_insert_id($db_connection);
        }
        
        if ($i == 0) {
            $pkg_ids = $lastId;
        } else {
            $pkg_ids = $pkg_ids . ',' . $lastId;
        }
    }

    $result['error'] = 0;

    $sql = "UPDATE find_action SET name='$name', packages='$pkg_ids', start_time='$start_time', end_time='$end_time', enable='$enable' WHERE aid=$aid";
     
    $action_result = mysql_query($sql);

    // var_dump($action_result);

    if (!$action_result) { // 空
        $result['error'] = 112;
    }

    mysql_close(); 

    return;
}

/* Remove */
function onActionDel ($delID) {

    global $result;
    
    $aid = $_GET['aid'];
    if(empty($aid)) {
        $result['error'] = 111;
        return;
    }
    
    global $DB_HOST, $DB_NAME;
    $db_connection = mysql_connect($DB_HOST,"root","e5cda60c7e");

    mysql_query("set names 'utf8'"); //数据库输出编码

    mysql_select_db($DB_NAME); //打开数据库

    $sql = "delete from find_action where aid=$aid";

    echo $sql;

    $db_result = mysql_query($sql);

    // var_dump($all_info);

    if ($db_result !== false) { // 空
        $result['error'] = 0;
    } else {
        $result['error'] = 113;
    }
    mysql_close(); 
    return $result;
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