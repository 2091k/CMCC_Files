<?php
session_start();
// =========配置区========
$defaultAuth = "TOKEN";
$defaultRootId = "文件夹ID";
$DEBUG = false;
define('PAGE_PASSWORD', '123456@');
define('SHARE_FILE', __DIR__.'/share_data.json');
// =======================

// 退出登录（非分享模式使用）
if(isset($_GET['logout'])){
    unset($_SESSION['page_login']);
    session_destroy();
    header("Location: ?");
    exit;
}

$auth = $_GET['auth'] ?? $defaultAuth;
$rootFid = $defaultRootId;

// ---------- 分享数据读写 ----------
function loadShares() {
    if (file_exists(SHARE_FILE)) {
        $data = json_decode(file_get_contents(SHARE_FILE), true);
        return is_array($data) ? $data : [];
    }
    return [];
}
function saveShares($shares) {
    file_put_contents(SHARE_FILE, json_encode($shares, JSON_PRETTY_PRINT));
}

// ---------- 处理分享/取消分享 ----------
if (isset($_GET['action']) && in_array($_GET['action'], ['share','unshare']) && isset($_GET['fid'])) {
    $action = $_GET['action'];
    $fid = $_GET['fid'];
    $shares = loadShares();
    if ($action === 'share') {
        foreach ($shares as $token => $fid_existing) {
            if ($fid_existing === $fid) {
                unset($shares[$token]);
                break;
            }
        }
        $token = bin2hex(random_bytes(16));
        $shares[$token] = $fid;
        saveShares($shares);
        $shareLink = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . '?share_token=' . $token;
        header("Location: ?share_success=1&share_link=" . urlencode($shareLink));
        exit;
    } elseif ($action === 'unshare') {
        foreach ($shares as $token => $fid_existing) {
            if ($fid_existing === $fid) {
                unset($shares[$token]);
                saveShares($shares);
                break;
            }
        }
        header("Location: ?unshare_success=1");
        exit;
    }
}

// ---------- 分享模式检测 ----------
$shareToken = $_GET['share_token'] ?? '';
$shareFid = null;
$isShareMode = false;
if ($shareToken) {
    $shares = loadShares();
    if (isset($shares[$shareToken])) {
        $shareFid = $shares[$shareToken];
        $isShareMode = true;
        $rootFid = $shareFid;
        // 不设置登录会话，保持未登录状态
    } else {
        die("无效的分享链接");
    }
}
define('SHARE_MODE', $isShareMode);

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

// ========== 下载逻辑 ==========
// 1. 路径下载（非分享模式可用）
if (!empty($_GET['path']) && !SHARE_MODE) {
    $pathRawInput = urldecode(trim($_GET['path'], '/'));
    if(strpos($pathRawInput,'?') !== false){
        $pathRaw = explode('?',$pathRawInput,2)[0];
    }else{
        $pathRaw = $pathRawInput;
    }
    $pathArr = array_filter(explode('/', $pathRaw), fn($v)=>$v!=='');
    $pathArr = array_values($pathArr);
    if(!empty($pathArr) && $pathArr[0]==='CMCC'){
        array_shift($pathArr);
    }
    $startFid = (!empty($_GET['fid'])) ? $_GET['fid'] : $rootFid;
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

// 2. 文件ID下载（非分享模式直接可用；分享模式需验证）
if(!empty($_GET['download_fid'])){
    $fid = $_GET['download_fid'];
    if (SHARE_MODE) {
        // 分享模式：验证该文件是否属于当前目录
        $currentFid = $_GET['fid'] ?? $rootFid; // 当前目录ID
        $items = api_list($currentFid, $commonHeaders);
        $found = false;
        foreach ($items as $item) {
            if ($item['fileId'] === $fid && $item['type'] !== 'folder') {
                $found = true;
                break;
            }
        }
        if (!$found) {
            die("文件不存在或无权下载");
        }
    }
    $dlRes = getDownloadUrl($fid, $commonHeaders);
    $dlUrl = $dlRes['url'];
    if($dlUrl === ""){
        echo "<pre style='background:#111;color:#f33;padding:16px'>下载接口返回原始数据：\n".$dlRes['raw']."</pre>";
        exit;
    }
    header("Location: ".$dlUrl);
    exit;
}

// ========== 密码验证（分享模式自动跳过）==========
$isLogin = false;
$passwordError = '';
if (!SHARE_MODE) {
    $isLogin = isset($_SESSION['page_login']) && $_SESSION['page_login'] === true;
    if(isset($_POST['submit_password'])){
        if($_POST['password'] === PAGE_PASSWORD){
            $_SESSION['page_login'] = true;
            $isLogin = true;
        }else{
            $passwordError = "密码错误，请重新输入";
        }
    }
    if(!$isLogin){
        // 显示密码表单（省略，同之前）
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
} else {
    // 分享模式：视为已登录（仅用于渲染列表）
    $isLogin = true;
}

// ====================== 构建面包屑 ======================
$rootId = SHARE_MODE ? $shareFid : $defaultRootId;
$rootName = SHARE_MODE ? "分享根目录" : "CMCC";

if (SHARE_MODE) {
    $parentFileId = $rootId; // 强制从根开始
} else {
    $parentFileId = (!empty($_GET['fid'])) ? $_GET['fid'] : $rootId;
}

$breadcrumb = [];
if (!empty($_GET['breadcrumb'])) {
    $rawBc = json_decode(base64_decode($_GET['breadcrumb']), true);
    if (is_array($rawBc)) $breadcrumb = $rawBc;
}
if (empty($breadcrumb) || $breadcrumb[0]['fid'] !== $rootId) {
    array_unshift($breadcrumb, ["name"=>$rootName, "fid"=>$rootId]);
}

if ($parentFileId !== $rootId) {
    $last = end($breadcrumb);
    if (!$last || $last['fid'] !== $parentFileId) {
        $folderName = $_GET['folder_name'] ?? null;
        if ($folderName === null) {
            $folderName = "未知文件夹";
            $parentFid = (count($breadcrumb) >= 2) ? $breadcrumb[count($breadcrumb)-2]['fid'] : $rootId;
            $list = api_list($parentFid, $commonHeaders);
            foreach ($list as $li) {
                if ($li['fileId'] === $parentFileId && $li['type'] === 'folder') {
                    $folderName = $li['name'];
                    break;
                }
            }
        }
        $breadcrumb[] = ["name"=>$folderName, "fid"=>$parentFileId];
    }
} else {
    $breadcrumb = [ ["name"=>$rootName, "fid"=>$rootId] ];
}

$bcEncoded = base64_encode(json_encode($breadcrumb, JSON_UNESCAPED_UNICODE));

// 获取文件列表
$respItems = api_list($parentFileId, $commonHeaders);

// 格式化大小
function formatSize(?int $bytes): string
{
    if($bytes === null) return "-";
    if ($bytes < 1024) return "{$bytes} B";
    if ($bytes < 1048576) return round($bytes/1024,2)." KB";
    if ($bytes < 1073741824) return round($bytes/1048576,2)." MB";
    return round($bytes/1073741824,2)." GB";
}

// 生成路径下载链接（仅非分享模式使用）
function getParamUrl($breadcrumbArr, $fileName): string
{
    $realPathArr = array_slice($breadcrumbArr, 1);
    $nameParts = ["CMCC"];
    foreach ($realPathArr as $item) {
        $nameParts[] = $item['name'];
    }
    $nameParts[] = $fileName;
    $pathStr = implode('/', $nameParts);
    return 'index.php?path=' . rawurlencode($pathStr);
}

// 检查当前文件夹是否已分享（仅非分享模式需要）
$shares = loadShares();
$isShared = false;
foreach ($shares as $token => $fid) {
    if ($fid === $parentFileId) {
        $isShared = true;
        break;
    }
}

// 消息提示
$shareSuccessMsg = '';
if (isset($_GET['share_success']) && isset($_GET['share_link'])) {
    $shareSuccessMsg = '分享链接已生成：<a href="'.htmlspecialchars($_GET['share_link']).'" target="_blank">'.htmlspecialchars($_GET['share_link']).'</a>（永久有效）';
}
if (isset($_GET['unshare_success'])) {
    $shareSuccessMsg = '已取消分享。';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=SHARE_MODE?'分享文件夹 - 139云盘':'139云盘文件浏览下载'?></title>
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
.share-btn, .unshare-btn {
    display:inline-block;
    padding:4px 12px;
    border-radius:4px;
    font-size:13px;
    text-decoration:none;
    color:#fff;
    background:#10b981;
}
.unshare-btn{background:#ef4444;}
.share-btn:hover{background:#059669;}
.unshare-btn:hover{background:#dc2626;}
.msg-box {
    background:#d1fae5;
    border:1px solid #a7f3d0;
    padding:12px 16px;
    border-radius:6px;
    margin-bottom:16px;
    color:#065f46;
}
.msg-box a{color:#065f46;text-decoration:underline;}
@media(max-width:720px){
    table th:nth-child(2),table td:nth-child(2){display:none;}
    th,td{padding:10px 8px;font-size:14px}
}
</style>
</head>
<body>
<div class="header-wrap">
    <h1><?=SHARE_MODE?'📂 分享文件夹内容':'📂 139云盘文件浏览'?></h1>
    <?php if(!SHARE_MODE): ?>
        <a class="logout-btn" href="?logout=1">🚪 退出登录</a>
    <?php endif; ?>
</div>

<?php if(!empty($shareSuccessMsg)): ?>
    <div class="msg-box"><?=$shareSuccessMsg?></div>
<?php endif; ?>

<?php
// 返回上一级按钮
if (count($breadcrumb) >= 2 && $parentFileId !== $rootId) {
    $prevItem = $breadcrumb[count($breadcrumb)-2];
    $prevBc = base64_encode(json_encode(array_slice($breadcrumb, 0, -1), JSON_UNESCAPED_UNICODE));
?>
<a class="back-btn" href="?fid=<?=urlencode($prevItem['fid'])?>&breadcrumb=<?=urlencode($prevBc)?>&folder_name=<?=urlencode($prevItem['name'])?>">← 返回上一级</a>
<?php } ?>

<div class="breadcrumb">
📂 当前路径：
<?php foreach ($breadcrumb as $idx => $bcItem): ?>
    <?php if($idx>0):?><span class="sep">/</span><?php endif; ?>
    <?php
    $sliceBc = array_slice($breadcrumb, 0, $idx+1);
    $sliceEncode = base64_encode(json_encode($sliceBc, JSON_UNESCAPED_UNICODE));
    ?>
    <a href="?fid=<?=urlencode($bcItem['fid'])?>&breadcrumb=<?=urlencode($sliceEncode)?>&folder_name=<?=urlencode($bcItem['name'])?>"><?=htmlspecialchars($bcItem['name'])?></a>
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
            <a href="?fid=<?=urlencode($item['fileId'])?>&breadcrumb=<?=urlencode($bcEncoded)?>&folder_name=<?=urlencode($item['name'])?>">📁 <?=htmlspecialchars($item['name'])?></a>
        </td>
        <td class="size">-</td>
        <td>
            <?php if(!SHARE_MODE): ?>
                <?php
                $sharedNow = false;
                foreach ($shares as $token => $fid) {
                    if ($fid === $item['fileId']) {
                        $sharedNow = true;
                        break;
                    }
                }
                if ($sharedNow): ?>
                    <a class="unshare-btn" href="?action=unshare&fid=<?=urlencode($item['fileId'])?>">取消分享</a>
                <?php else: ?>
                    <a class="share-btn" href="?action=share&fid=<?=urlencode($item['fileId'])?>">分享</a>
                <?php endif; ?>
            <?php else: ?>
                <span style="color:#9ca3af;">只读</span>
            <?php endif; ?>
        </td>
    <?php else: ?>
        <td class="file"><?=htmlspecialchars($item['name'])?></td>
        <td class="size"><?=formatSize($item['size'])?></td>
        <td>
            <?php if(SHARE_MODE): ?>
                <!-- 分享模式：使用ID下载，并携带当前目录fid用于验证 -->
                <a href="?download_fid=<?=urlencode($item['fileId'])?>&fid=<?=urlencode($parentFileId)?>">下载</a>
            <?php else: ?>
                <a href="?fid=<?=urlencode($parentFileId)?>&download_fid=<?=urlencode($item['fileId'])?>">ID下载</a>｜
                <a href="?fid=<?=urlencode($parentFileId)?>&path=<?=urlencode($item['name'])?>">参数下载</a>｜
                <a href="<?=htmlspecialchars(getParamUrl($breadcrumb, $item['name']))?>">路径下载</a>
            <?php endif; ?>
        </td>
    <?php endif; ?>
    </tr>
<?php endforeach; endif; ?>
</table>
</body>
</html>
