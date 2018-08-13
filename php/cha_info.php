<?php
$result = array('error'=>1, 'msg'=>'参数错误');

$DB_HOST = 'api.edisonx.cn';
$DB_NAME = 'taihe';
$QR_FOLDER = '/alidata/www/ecmall/data/files/cha';
$BUZZ_URL = 'https://miniapp.edisonx.cn/h5/taihe2';

$result = array('error'=>101);
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action == "upload") {
        $result = onUploadHandler();
    }
} else if (isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action == "query" && !empty($_GET['sid'])) {
        $result['error'] = 0;
        $result['actions'] = onQueryHandler($_GET['sid']);
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
    } else if($action == "qrv" && !empty($_GET['sid']) && !empty($_GET['dur'])) {
        createQRCodeVideo($_GET['sid'], $_GET['dur']);
    }
}
echo json_encode($result);



/** Query */
function onQueryHandler ($sid) {
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
            $to = $item['redirect'];

            $packages = $item['packages'];

            if (containSid($sid, $packages)) {
                $actionPkg =  parseActionPackages ($packages);
                if (!empty($actionPkg) && count($actionPkg) > 0) {
                    $actions[$aid] = array();
                    $actions[$aid]['name'] =  $name ;
                    $actions[$aid]['to'] =  $to ;
                    $actions[$aid]['pkg'] = $actionPkg;
                    break;
                }
            }
        }
    }

    mysql_close(); 

    return $actions;
}

function containSid ($sid, $packages) {
    $sid_list = explode(",",$packages);
    for ($i=0; $i<count($sid_list); $i++) {
        if ($sid_list[$i] == $sid) {
            return true;
        }
    }
    return false;
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
                'dur'=>$item['duration'], 
                'fdur'=>$item['follow_duration'], 
                'pos'=>$item['point_info'],
                'w'=>$item['width'],
                'h'=>$item['height'],
                'xls'=>$item['xls'],
                'qr_png_zip'=>$item['qr_png_zip'],
                'qr_video'=>$item['qr_video'],
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
                'desc'=>$item['description'],
                'ct'=>$item['create_time'],
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
            'desc'=>$item['description'],
            'ct'=>$item['create_time'],
            'to'=>$item['redirect'],
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
    $adesc = $_GET['desc'];

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
        $xls = $stage['xls'];

        if (empty($w) || empty($h) || empty($img_url) || empty($pos) || empty($dur) || empty($pkg_name)) {
            $result['error'] = 105;
            return;
        }

        $sql = "INSERT INTO find_pkg (pkg_name, point_info, description, img_url, duration, follow_duration, width, height, xls) VALUES ('$pkg_name','$pos,$desc','$desc','$img_url','$dur','$fdur','$w','$h','$xls')";
        // echo $sql;
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

        triggerQRCodeVideoAsync($lastId, $dur);
    }

    $result['error'] = 0;

    $sql = "INSERT INTO find_action (name, packages, start_time, end_time, enable, redirect, description) VALUES ('$name','$pkg_ids','$start_time','$end_time','$enable','$to','$adesc')";
     
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
    $adesc = $_GET['desc'];

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
        $xls = $stage['xls'];

        if (empty($w) || empty($h) || empty($img_url) || empty($pos) || empty($dur) || empty($pkg_name)) {
            $result['error'] = 105;
            return;
        }

        $sid = $stage['sid'];
        $lastId = $sid;

        $result['error'] = 0;

        if (empty($sid)) { // new pkg shoud insert
            $sql = "INSERT INTO find_pkg (pkg_name, point_info, description, img_url, duration, follow_duration, width, height, xls) VALUES ('$pkg_name','$pos','$desc','$img_url','$dur','$fdur','$w','$h','$xls')";
        } else {// old pkg should update
            $sql = "UPDATE find_pkg SET pkg_name='$pkg_name', point_info='$pos', description='$desc', img_url='$img_url', duration='$dur', follow_duration='$fdur', width='$w', height='$h', xls='$xls' WHERE id='$sid'";
        }
        // echo $sql;

        $db_result = mysql_query($sql);
        if (!$db_result) {
            $result['error'] = 106;
            return;
        }

        if (empty($sid)) {
            $lastId = mysql_insert_id($db_connection);
            $sid = $lastId;
        }
        
        if ($i == 0) {
            $pkg_ids = $lastId;
        } else {
            $pkg_ids = $pkg_ids . ',' . $lastId;
        }

        triggerQRCodeVideoAsync($sid, $dur);
    }

    $sql = "UPDATE find_action SET name='$name', packages='$pkg_ids', start_time='$start_time', end_time='$end_time', enable='$enable', redirect='$to', description='$adesc' WHERE aid=$aid";
     
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

    // echo $sql;

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

function zipQRPics($qr_folder, $zip_file_path) {
    $zip = new ZipArchive();
    $zip->open($zip_file_path,ZipArchive::CREATE);   //打开压缩包

    if(@$handle = opendir($qr_folder)) { //注意这里要加一个@，不然会有warning错误提示：）
        while(($file = readdir($handle)) !== false) {
            if($file != ".." && $file != ".") { //排除根目录；
                $tmp = $qr_folder."/".$file;
                if(!is_dir($tmp)) { //忽略子文件夹
                    // echo "$tmp";
                    $zip->addFile($tmp, basename($tmp)); 
                }
            }
        }
        closedir($handle);
    }
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

function triggerQRCodeVideoAsync ($sid, $dur) {
    $url = "https://miniapp.edisonx.cn/h5/taihe2/php/cha_info.php?action=qrv&sid=$sid&dur=$dur";
    // echo "trigger url= " . $url;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
 
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
     
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
 
    curl_exec($ch);
    curl_close($ch);
}

function createQRCodeVideo($sid, $dur) {
    global $QR_FOLDER, $result;
    exec("rm -rf $QR_FOLDER/qr/$sid");
    exec("mkdir $QR_FOLDER/qr/$sid");
    $dur += 2; // 加两秒buffer
    for($i=0,$t=0.0; $t <= $dur; $t+= 0.5, $i++) {
        handleOneQRCodes($sid, $t, $i);
    }
    
    $cmd = "ffmpeg -r 2 -i $QR_FOLDER/qr/$sid/$sid-%03d.png -c:v libx264 -pix_fmt yuv420p -y $QR_FOLDER/video/$sid.mp4";
    // echo $cmd;
    exec($cmd);
    $result['error'] = 0;

    // udpate viedo url in db
    global $DB_HOST, $DB_NAME;
    $db_connection = mysql_connect($DB_HOST,"root","e5cda60c7e");

    mysql_query("set names 'utf8'"); //数据库输出编码

    mysql_select_db($DB_NAME); //打开数据库

    $qr_video = "https://miniapp.edisonx.cn/data/files/cha/video/$sid.mp4";
    $qr_png_zip = "https://miniapp.edisonx.cn/data/files/cha/qr/$sid.zip";
    $sql = "UPDATE find_pkg SET qr_video='$qr_video',qr_png_zip='$qr_png_zip' WHERE id='$sid'";

    // echo $sql;

    $db_result = mysql_query($sql);

    // var_dump($all_info);

    if ($db_result !== false) { // 空
        $result['error'] = 0;
    } else {
        $result['error'] = 116;
    }
    mysql_close();

    zipQRPics("$QR_FOLDER/qr/$sid", "$QR_FOLDER/qr/$sid.zip");
}

function handleOneQRCodes($sid, $t, $i) {
     // $data = input('post.');
    global $BUZZ_URL, $QR_FOLDER;
    $index = sprintf("%03d", $i);
    $filename = "$QR_FOLDER/qr/$sid/$sid-$index.png";
    $longUrlString = "http://www.91qzb.com/thinkphp/public/index.php/api/index/weixin?type=h5&t=$t&cid=$sid&url=$BUZZ_URL";     //二维码内容  

    $shorten = "http://91qzb.com/api.php?format=json&domain=g&del_status=1&url=$longUrlString";

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $shorten);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $data = curl_exec($curl);
    $json = json_decode($data);
    $shortUrl = $json->url;
    curl_close($curl);

    generateQRPng($shortUrl, $filename);
}

function generateQRPng($url, $filename) {
    require_once 'QRcode.php';

    $errorCorrectionLevel = 'H'; //容错级别  
    $matrixPointSize = 6;   //生成图片大小  
    //生成二维码图片
    // $filename = microtime().'.png';
    // echo 'path = ' . $filename;
    QRcode::png($url,$filename , $errorCorrectionLevel, $matrixPointSize, 2);  
    
    $logo = 'edisonx_logo.png';  //准备好的logo图片   
    $QR = $filename;   //已经生成的原始二维码图  
    
    if (file_exists($logo)) {  
        // echo 'logo = ' . $logo;
        $QR = imagecreatefromstring(file_get_contents($QR));     //目标图象连接资源。
        $logo = imagecreatefromstring(file_get_contents($logo));    //源图象连接资源。
        $QR_width = imagesx($QR);   //二维码图片宽度   
        $QR_height = imagesy($QR);   //二维码图片高度   
        $logo_width = imagesx($logo);  //logo图片宽度   
        $logo_height = imagesy($logo);  //logo图片高度   
        $logo_qr_width = $QR_width / 4;    //组合之后logo的宽度(占二维码的1/5)
        $scale = $logo_width/$logo_qr_width;    //logo的宽度缩放比(本身宽度/组合后的宽度)
        $logo_qr_height = $logo_height/$scale;  //组合之后logo的高度
        $from_width = ($QR_width - $logo_qr_width) / 2;   //组合之后logo左上角所在坐标点
        
        //重新组合图片并调整大小
        /*
        * imagecopyresampled() 将一幅图像(源图象)中的一块正方形区域拷贝到另一个图像中
        */
        imagecopyresampled($QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width,$logo_qr_height, $logo_width, $logo_height); 
    }   
    
    //输出图片  

    imagepng($QR, $filename);  
    imagedestroy($QR);
    imagedestroy($logo);
}
?>