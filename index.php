<?php
session_start();
// =========配置区========
$defaultAuth = "token";
$defaultRootId = "文件夹ID";
$DEBUG = false;
// 网页浏览页面登录密码（自行修改）
define('PAGE_PASSWORD', '123456');
// =======================

// 退出登录处理
if(isset($_GET['logout'])){
    unset($_SESSION['page_login']);
    session_destroy();
    header("Location: ?");
    exit;
}

$auth = $_GET['auth'] ?? $defaultAuth;
$rootFid = $defaultRootId;

$commonHeaders = [
    'User-Agent: Mozilla/5.0 (Linux; U; Android 10; zh-CN; 2014811 Build/QQ3A.200805.001) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/78.0.2564.116 Quark/3.8.2.126 Mobile Safari/537.36 T7/10.3 SearchCraft/2.6.3 (Baidu; P1 8.0.0)',
    'Accept: application/json, text/plain, */*',
    'Accept-Encoding: gzip, deflate, br, zstd',
    'sec-ch-ua-platform: "Android"',
    "Authorization: {$auth}",
    'x-yun-client-info: ||3|7.17.9|2014811|2014811|x7GCiQfBbjGPtehxHpoqFgxfM2cfSfin||Android 10|391X578|||',
    'CMS-CLIENT: 0010102',
    'mcloud-version: 7.17.9',
    'x-inner-ntwk: 2',
    'mcloud-client: 10601',
    'mcloud-channel: 1000101',
    'sec-ch-ua-mobile: ?1',
    'sec-ch-ua: "Android WebView";v="143", "Chromium";v="143"',
    'x-yun-api-version: v1',
    'CMS-DEVICE: default',
    'Content-Type: application/json;charset=UTF-8',
    'x-yun-svc-type: 1',
    'x-huawei-channelSrc: 10000035',
    'x-m4c-src: 10001',
    'x-m4c-caller: h5',
    'Accept-Language: zh-CN',
    'x-DeviceInfo: ||3|7.17.9|2014811|2014811|x7GCiQfBbjGPtehxHpoqFgxfM2cfSfin||Android 10|391X578|||',
    'mcloud-route: 001',
    'Access-Control-Allow-Credentials: true',
    'INNER-HCY-ROUTER-HTTPS: 1',
    'x-Nationcode: +86',
    'mcloud-sign: 20260809221731,JYIo08VM,6E54D111CCEBEE44E3011D8DA52EFD63',
    'x-yun-app-channel: 10000035',
    'x-SvcType: 1',
    'Origin: https://yun.139.com',
    'X-Requested-With: mark.via',
    'Sec-Fetch-Site: same-site',
    'Sec-Fetch-Mode: cors',
    'Sec-Fetch-Dest: empty',
    'Referer: https://yun.139.com/',
];


/**
 * 获取目录文件列表
 */
function api_list(string $parentId, array $headers): array
{
    $curl = curl_init();
    $postBody = json_encode([
        "pageInfo" => ["pageSize" => 100, "pageCursor" => null],
        "orderBy" => "updated_at",
        "orderDirection" => "DESC",
        "parentFileId" => $parentId,
        "imageThumbnailStyleList" => ["Small","Large"]
    ]);
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://personal-kd-njs.yun.139.com/hcy/file/list',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postBody,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $resp = curl_exec($curl);
    curl_close($curl);
    $json = json_decode($resp, true);
    return $json['data']['items'] ?? [];
}

/**
 * 递归按名称逐层查找文件ID
 */
function findFileByPath(string $startFid, array $pathArr, array $headers): ?string
{
    if (empty($pathArr)) return null;
    $targetName = array_shift($pathArr);
    $items = api_list($startFid, $headers);
    foreach ($items as $item) {
        if ($item['name'] !== $targetName) continue;
        if (empty($pathArr)) {
            return $item['type'] !== 'folder' ? $item['fileId'] : null;
        }
        if ($item['type'] === 'folder') {
            return findFileByPath($item['fileId'], $pathArr, $headers);
        }
    }
    return null;
}

/**
 * 获取CDN下载直链
 */
function getDownloadUrl(string $fileId, array $headers): array
{
    $curl = curl_init();
    $postBody = json_encode(["fileId" => $fileId]);
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://personal-kd-njs.yun.139.com/hcy/file/getDownloadUrl",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postBody,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER=>false
    ]);
    $resp = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    $j = json_decode($resp,true);
    return [
        'url' => $j['data']['url'] ?? '',
        'raw' => $resp,
        'error' => $err
    ];
}

// ==========【下载逻辑完全不做密码校验】只要参数正确直接下载 ==========
if (!empty($_GET['path'])) {
    $pathRaw = urldecode(trim($_GET['path'], '/'));
    $pathArr = array_filter(explode('/', $pathRaw), fn($v)=>$v!=='');
    $pathArr = array_values($pathArr);
    // 去掉虚拟CMCC第一层，不传给网盘API
    if(!empty($pathArr) && $pathArr[0]==='CMCC'){
        array_shift($pathArr);
    }
    $startFid = !empty($_GET['fid']) ? $_GET['fid'] : $rootFid;
    $targetFid = findFileByPath($startFid, $pathArr, $commonHeaders);
    if ($targetFid === null) die("路径不存在，请核对名称：".htmlspecialchars($pathRaw));
    $dlRes = getDownloadUrl($targetFid, $commonHeaders);
    $realUrl = $dlRes['url'];
    if (empty($realUrl)) {
        echo "<pre style='background:#111;color:#f33;padding:16px'>下载接口返回错误：\n".$dlRes['raw']."</pre>";
        exit;
    }
    header("Location: ".$realUrl);
    exit;
}

if(!empty($_GET['download_fid'])){
    $fid = $_GET['download_fid'];
    $dlRes = getDownloadUrl($fid, $commonHeaders);
    $dlUrl = $dlRes['url'];
    if($dlUrl === ""){
        echo "<pre style='background:#111;color:#f33;padding:16px'>下载接口返回原始数据：\n".$dlRes['raw']."</pre>";
        exit;
    }
    header("Location: ".$dlUrl);
    exit;
}

// ====================== 网页浏览页面：密码登录校验开始 ======================
$isLogin = isset($_SESSION['page_login']) && $_SESSION['page_login'] === true;
$passwordError = '';

// 提交密码登录
if(isset($_POST['submit_password'])){
    if($_POST['password'] === PAGE_PASSWORD){
        $_SESSION['page_login'] = true;
        $isLogin = true;
    }else{
        $passwordError = "密码错误，请重新输入";
    }
}

// 未登录输出密码表单，终止程序，不渲染文件列表
if(!$isLogin){
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>139云盘 - 访问密码</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{
    font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
    display:flex;
    align-items:center;
    justify-content:center;
    min-height:100vh;
    margin:0;
    background: linear-gradient(135deg,#667eea 0%,#764ba2 100%);
}
.login-card{
    width:100%;
    max-width:400px;
    padding:42px 36px;
    background:#ffffff;
    border-radius:14px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.22);
}
.login-card h2{
    text-align:center;
    color:#1f2937;
    margin-bottom:8px;
    font-size:22px;
}
.login-desc{
    text-align:center;
    color:#6b7280;
    font-size:14px;
    margin-bottom:28px;
}
.error{
    background:#fee2e2;
    color:#dc2626;
    padding:10px 14px;
    border-radius:6px;
    margin-bottom:18px;
    font-size:14px;
}
input{
    width:100%;
    padding:13px 16px;
    font-size:16px;
    border:1px solid #d1d5db;
    border-radius:8px;
    margin-bottom:20px;
    outline:none;
    transition:0.2s border;
}
input:focus{
    border-color:#6366f1;
    box-shadow:0 0 0 3px rgba(99,102,241,0.12);
}
button{
    width:100%;
    padding:13px;
    border:none;
    background:#4f46e5;
    color:#fff;
    border-radius:8px;
    font-size:16px;
    cursor:pointer;
    transition:0.2s background;
}
button:hover{
    background:#4338ca;
}
</style>
</head>
<body>
<div class="login-card">
    <h2>🔐 访问验证</h2>
    <div class="login-desc">请输入页面访问密码进入云盘浏览</div>
    <?php if($passwordError):?>
        <div class="error"><?=htmlspecialchars($passwordError) ?></div>
    <?php endif;?>
    <form method="post">
        <input type="password" name="password" placeholder="请输入访问密码" required autocomplete="off">
        <button type="submit" name="submit_password">确认登录</button>
    </form>
</div>
</body>
</html>
<?php
exit;
}
// ====================== 登录成功，继续渲染文件列表页面 ======================

// --------面包屑导航处理 --------
$breadcrumb = [];
$parentFileId = $_GET['fid'] ?? $rootFid;
if(!empty($_GET['breadcrumb'])){
    $rawBc = json_decode(base64_decode($_GET['breadcrumb']),true);
    if(is_array($rawBc)) $breadcrumb = $rawBc;
}
if($parentFileId === $rootFid){
    $breadcrumb = [ ["name"=>"根目录","fid"=>$rootFid] ];
}else{
    $last = end($breadcrumb);
    if(!$last || $last['fid'] !== $parentFileId){
        $folderName = "未知文件夹";
        $parentOfCurrent = count($breadcrumb)>=1 ? $breadcrumb[count($breadcrumb)-1]['fid'] : null;
        if($parentOfCurrent){
            $list = api_list($parentOfCurrent,$commonHeaders);
            foreach ($list as $li){
                if($li['fileId'] === $parentFileId && $li['type']==='folder'){
                    $folderName = $li['name'];
                    break;
                }
            }
        }
        $breadcrumb[] = ["name"=>$folderName,"fid"=>$parentFileId];
    }
}
$bcEncoded = base64_encode(json_encode($breadcrumb,JSON_UNESCAPED_UNICODE));
$respItems = api_list($parentFileId, $commonHeaders);

// 字节格式化
function formatSize(?int $bytes): string
{
    if($bytes === null) return "-";
    if ($bytes < 1024) return "{$bytes} B";
    if ($bytes < 1048576) return round($bytes/1024,2)." KB";
    if ($bytes < 1073741824) return round($bytes/1048576,2)." MB";
    return round($bytes/1073741824,2)." GB";
}

/**
 * 生成路径下载链接，最前面固定带上 CMCC
 */
function getParamUrl($breadcrumbArr, $fileName): string
{
    $realPathArr = array_slice($breadcrumbArr,1);
    $nameParts = [];
    $nameParts[] = "CMCC";
    foreach ($realPathArr as $item){
        $nameParts[] = $item['name'];
    }
    $nameParts[] = $fileName;
    $pathStr = implode('/',$nameParts);
    return 'index.php?path='.rawurlencode($pathStr);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>139云盘文件浏览下载</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
body{max-width:1240px;margin:30px auto;padding:0 16px}
.header-wrap{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}
h1{font-size:20px;color:#1f2937}
.logout-btn{
    padding:7px 14px;
    background:#ef4444;
    color:#fff;
    border-radius:6px;
    text-decoration:none;
    font-size:14px;
}
.logout-btn:hover{background:#dc2626}
.info{background:#f5f7fa;padding:14px;border-radius:8px;margin-bottom:14px;color:#444;font-size:14px;line-height:1.6}
.breadcrumb{background:#eef2f7;padding:14px;border-radius:8px;margin-bottom:16px;font-size:14px}
.breadcrumb a{color:#2563eb;text-decoration:none}
.breadcrumb a:hover{text-decoration:underline}
.breadcrumb span.sep{margin:0 8px;color:#9ca3af}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08)}
th,td{padding:14px 16px;border-bottom:1px solid #eee}
th{background:#f3f4f6;text-align:left;color:#374151}
a{color:#2563eb;text-decoration:none}
a:hover{text-decoration:underline}
.folder{color:#d97706;font-weight:bold}
.file{color:#1f2937}
.size{color:#6b7280;font-size:13px}
.back-btn{display:inline-block;padding:7px 14px;background:#e5e7eb;border-radius:6px;margin-bottom:12px;color:#1f2937;text-decoration:none;font-size:14px}
.back-btn:hover{background:#dde0e4}
@media(max-width:720px){
    table th:nth-child(2),table td:nth-child(2){display:none;}
    th,td{padding:10px 8px;font-size:14px}
}
</style>
</head>
<body>
<div class="header-wrap">
    <h1>📂 139云盘文件浏览</h1>
    <a class="logout-btn" href="?logout=1">🚪 退出登录</a>
</div>

<?php /*
<div class="info">
<h3>访问说明（虚拟顶层CMCC）</h3>
1.路径下载完整示例：index.php?path=CMCC/电脑/app/TSC/BarTend/企业版/官方版本/BT2022_R6_206587_Full_x64.exe<br>
2.参数下载：?fid=目录ID&path=文件名<br>
3.ID下载（稳定推荐）：?fid=目录ID&download_fid=文件ID<br>
4.目录浏览：?fid=文件夹ID
</div>
*/ ?>

<?php
// 返回上一级按钮
if($parentFileId !== $rootFid && count($breadcrumb)>=2){
    $prevItem = $breadcrumb[count($breadcrumb)-2];
    $prevBc = base64_encode(json_encode(array_slice($breadcrumb,0,-1),JSON_UNESCAPED_UNICODE));
?>
<a class="back-btn" href="?fid=<?=urlencode($prevItem['fid'])?>&breadcrumb=<?=urlencode($prevBc)?>">← 返回上一级</a>
<?php } ?>

<div class="breadcrumb">
📂 当前路径：
<a href="?fid=<?=urlencode($rootFid)?>">CMCC</a>
<span class="sep">/</span>
<?php foreach ($breadcrumb as $idx=>$bcItem): ?>
<?php if($idx>0):?><span class="sep">/</span><?php endif; ?>
<?php
$sliceBc = array_slice($breadcrumb,0,$idx+1);
$sliceEncode = base64_encode(json_encode($sliceBc,JSON_UNESCAPED_UNICODE));
?>
<a href="?fid=<?=urlencode($bcItem['fid'])?>&breadcrumb=<?=urlencode($sliceEncode)?>"><?=htmlspecialchars($bcItem['name'])?></a>
<?php endforeach; ?>
</div>

<table>
    <tr>
        <th>文件名</th>
        <th>大小</th>
        <th>操作</th>
    </tr>
<?php if(empty($respItems)): ?>
    <tr><td colspan="3">目录无内容 / Authorization/mcloud‑sign 凭证过期，请重新抓包替换头部</td></tr>
<?php else: foreach($respItems as $item): ?>
    <tr>
    <?php if($item['type'] === 'folder'): ?>
        <td class="folder">
            <a href="?fid=<?=urlencode($item['fileId'])?>&breadcrumb=<?=urlencode($bcEncoded)?>">📁 <?=htmlspecialchars($item['name'])?></a>
        </td>
        <td class="size">-</td>
        <td>-</td>
    <?php else: ?>
        <td class="file"><?=htmlspecialchars($item['name'])?></td>
        <td class="size"><?=formatSize($item['size'])?></td>
        <td>
            <a href="?fid=<?=urlencode($parentFileId)?>&download_fid=<?=urlencode($item['fileId'])?>">ID下载</a>｜
            <a href="?fid=<?=urlencode($parentFileId)?>&path=<?=urlencode($item['name'])?>">参数下载</a>｜
            <a href="<?=htmlspecialchars(getParamUrl($breadcrumb, $item['name']))?>">路径下载</a>
        </td>
    <?php endif; ?>
    </tr>
<?php endforeach; endif; ?>
</table>
</body>
</html>
