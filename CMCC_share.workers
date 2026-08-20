export default {
  async fetch(request, env) {
    try {
      const url = new URL(request.url);
      const auth = env.AUTH;
      const rootFid = env.ROOT_ID;
      const pagePassword = env.PAGE_PASSWORD;
      const kv = env.CMCC_KV;

      // ---------- 辅助函数 ----------
      function parseCookies(cookieHeader) {
        const cookies = {};
        if (!cookieHeader) return cookies;
        cookieHeader.split(';').forEach(pair => {
          const idx = pair.indexOf('=');
          if (idx > -1) {
            const key = pair.substring(0, idx).trim();
            const value = decodeURIComponent(pair.substring(idx + 1).trim());
            cookies[key] = value;
          }
        });
        return cookies;
      }

      function btoa_utf8(str) {
        return btoa(unescape(encodeURIComponent(str)));
      }
      function atob_utf8(base64) {
        return decodeURIComponent(escape(atob(base64)));
      }

      function htmlEscape(str) {
        return String(str)
          .replace(/&/g, '&amp;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#39;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;');
      }

      function formatSize(bytes) {
        if (bytes === null || bytes === undefined) return '-';
        if (bytes < 1024) return `${bytes} B`;
        if (bytes < 1048576) return `${(bytes / 1024).toFixed(2)} KB`;
        if (bytes < 1073741824) return `${(bytes / 1048576).toFixed(2)} MB`;
        return `${(bytes / 1073741824).toFixed(2)} GB`;
      }

      // ---------- 登录页面渲染 ----------
      function renderLoginPage(hasError) {
        const errorBlock = hasError
          ? '<div class="error">密码错误，请重新输入</div>'
          : '';
        return new Response(`<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CMCC云盘 - 访问密码</title>
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
  <h2>CMCC files 云盘</h2>
  <div class="login-desc">请输入页面访问密码进入云盘浏览</div>
  ${errorBlock}
  <form method="post" action="/admin">
    <input type="password" name="password" placeholder="请输入访问密码" required autocomplete="off">
    <button type="submit">确认登录</button>
  </form>
</div>
</body>
</html>`, {
          headers: { 'Content-Type': 'text/html;charset=UTF-8' }
        });
      }

      // ---------- 公共请求头 ----------
      const commonHeaders = {
        'User-Agent': 'Mozilla/5.0 (Linux; U; Android 10; zh-CN; 2014811 Build/QQ3A.200805.001) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/78.0.2564.116 Quark/3.8.2.126 Mobile Safari/537.36 T7/10.3 SearchCraft/2.6.3 (Baidu; P1 8.0.0)',
        'Accept': 'application/json, text/plain, */*',
        'Accept-Encoding': 'gzip, deflate, br, zstd',
        'sec-ch-ua-platform': '"Android"',
        'Authorization': auth,
        'x-yun-client-info': '||3|7.17.9|2014811|2014811|x7GCiQfBbjGPtehxHpoqFgxfM2cfSfin||Android 10|391X578|||',
        'CMS-CLIENT': '0010102',
        'mcloud-version': '7.17.9',
        'x-inner-ntwk': '2',
        'mcloud-client': '10601',
        'mcloud-channel': '1000101',
        'sec-ch-ua-mobile': '?1',
        'sec-ch-ua': '"Android WebView";v="143", "Chromium";v="143"',
        'x-yun-api-version': 'v1',
        'CMS-DEVICE': 'default',
        'Content-Type': 'application/json;charset=UTF-8',
        'x-yun-svc-type': '1',
        'x-huawei-channelSrc': '10000035',
        'x-m4c-src': '10001',
        'x-m4c-caller': 'h5',
        'Accept-Language': 'zh-CN',
        'x-DeviceInfo': '||3|7.17.9|2014811|2014811|x7GCiQfBbjGPtehxHpoqFgxfM2cfSfin||Android 10|391X578|||',
        'mcloud-route': '001',
        'Access-Control-Allow-Credentials': 'true',
        'INNER-HCY-ROUTER-HTTPS': '1',
        'x-Nationcode': '+86',
        'mcloud-sign': '20260809221731,JYIo08VM,6E54D111CCEBEE44E3011D8DA52EFD63',
        'x-yun-app-channel': '10000035',
        'x-SvcType': '1',
        'Origin': 'https://yun.139.com',
        'X-Requested-With': 'mark.via',
        'Sec-Fetch-Site': 'same-site',
        'Sec-Fetch-Mode': 'cors',
        'Sec-Fetch-Dest': 'empty',
        'Referer': 'https://yun.139.com/',
      };

      // ---------- API 调用封装 ----------
      async function api_list(parentId) {
        const body = JSON.stringify({
          pageInfo: { pageSize: 100, pageCursor: null },
          orderBy: 'updated_at',
          orderDirection: 'DESC',
          parentFileId: parentId,
          imageThumbnailStyleList: ['Small', 'Large']
        });
        const resp = await fetch('https://personal-kd-njs.yun.139.com/hcy/file/list', {
          method: 'POST',
          headers: commonHeaders,
          body: body
        });
        const json = await resp.json();
        return json.data?.items || [];
      }

      async function getDownloadUrl(fileId) {
        const body = JSON.stringify({ fileId });
        const resp = await fetch('https://personal-kd-njs.yun.139.com/hcy/file/getDownloadUrl', {
          method: 'POST',
          headers: commonHeaders,
          body: body
        });
        const json = await resp.json();
        return {
          url: json.data?.url || '',
          raw: JSON.stringify(json)
        };
      }

      async function findFileByPath(startFid, pathArr) {
        if (pathArr.length === 0) return null;
        const targetName = pathArr[0];
        const remaining = pathArr.slice(1);
        const items = await api_list(startFid);
        for (const item of items) {
          if (item.name !== targetName) continue;
          if (remaining.length === 0) {
            return item.type !== 'folder' ? item.fileId : null;
          }
          if (item.type === 'folder') {
            return findFileByPath(item.fileId, remaining);
          }
        }
        return null;
      }

      // ---------- 分享功能：读写 KV ----------
      async function getShareFid(token) {
        if (!kv) return null;
        const val = await kv.get(token);
        return val || null;
      }
      async function setShare(token, fid) {
        if (!kv) return;
        await kv.put(token, fid);
      }
      async function deleteShare(token) {
        if (!kv) return;
        await kv.delete(token);
      }

      // ---------- 路径访问控制 ----------
      const pathname = url.pathname;
      const isAdminPath = pathname === '/admin' || pathname === '/admin/';
      const shareToken = url.searchParams.get('share_token');
      const cookies = parseCookies(request.headers.get('Cookie') || '');
      const isLogin = cookies.page_login === '1';
      const isDownloadRequest = url.searchParams.has('download_fid') || url.searchParams.has('path');

      // 核心规则：只有 /admin、带 share_token、或已登录用户的下载请求 允许访问，否则 404
      if (!isAdminPath && !shareToken && !(isDownloadRequest && isLogin)) {
        return new Response('Not Found', { status: 404 });
      }

      // ---------- 1. 退出登录（仅 /admin）----------
      if (isAdminPath && url.searchParams.has('logout')) {
        return new Response(null, {
          status: 302,
          headers: {
            'Location': '/admin',
            'Set-Cookie': 'page_login=; Path=/; Max-Age=0; HttpOnly; SameSite=Lax'
          }
        });
      }

      // ---------- 2. 分享/取消分享 API（仅 /admin 且登录）----------
      if (isAdminPath) {
        const action = url.searchParams.get('action');
        const fidParam = url.searchParams.get('fid');
        if ((action === 'share' || action === 'unshare') && fidParam) {
          if (!isLogin) {
            return new Response(JSON.stringify({ success: false, error: '请先登录' }), {
              status: 403,
              headers: { 'Content-Type': 'application/json' }
            });
          }
          if (action === 'share') {
            const token = crypto.randomUUID().replace(/-/g, '');
            await setShare(token, fidParam);
            const shareLink = `${url.protocol}//${url.host}?share_token=${token}`;
            return new Response(JSON.stringify({ success: true, share_link: shareLink }), {
              headers: { 'Content-Type': 'application/json' }
            });
          } else { // unshare
            const list = await kv.list();
            let foundToken = null;
            for (const key of list.keys) {
              const val = await kv.get(key.name);
              if (val === fidParam) {
                foundToken = key.name;
                break;
              }
            }
            if (foundToken) {
              await kv.delete(foundToken);
              return new Response(JSON.stringify({ success: true }), {
                headers: { 'Content-Type': 'application/json' }
              });
            } else {
              return new Response(JSON.stringify({ success: false, error: '未找到分享' }), {
                status: 404,
                headers: { 'Content-Type': 'application/json' }
              });
            }
          }
        }
      }

      // ---------- 3. 分享模式检测 ----------
      let isShareMode = false;
      let shareRootId = null;
      if (shareToken) {
        const fid = await getShareFid(shareToken);
        if (fid) {
          isShareMode = true;
          shareRootId = fid;
        } else {
          return new Response('无效的分享链接', { status: 404 });
        }
      }

      // ---------- 4. 下载请求处理 ----------
      if (url.searchParams.has('download_fid')) {
        const downloadFid = url.searchParams.get('download_fid');
        // 分享模式下验证文件属于当前目录
        if (isShareMode) {
          const currentFid = url.searchParams.get('fid') || shareRootId;
          const items = await api_list(currentFid);
          const found = items.some(item => item.fileId === downloadFid && item.type !== 'folder');
          if (!found) {
            return new Response('文件不存在或无权下载', { status: 403 });
          }
        }
        const dl = await getDownloadUrl(downloadFid);
        if (!dl.url) {
          return new Response(`<pre style="background:#111;color:#f33;padding:16px">下载接口返回错误：\n${dl.raw}</pre>`, {
            headers: { 'Content-Type': 'text/html;charset=UTF-8' }
          });
        }
        return Response.redirect(dl.url, 302);
      }

      // ---------- 5. 路径下载（仅非分享模式且已登录）----------
      if (!isShareMode && isLogin && url.searchParams.has('path')) {
        let rawQuery = url.search.slice(1);
        let realPathStr = '';
        const pathMatch = rawQuery.match(/path=([^&]*)/);
        if (pathMatch) {
          realPathStr = decodeURIComponent(pathMatch[1]);
        }
        realPathStr = realPathStr.split(/[?#&]/)[0].replace(/\/+$/, '');
        const pathArr = realPathStr.split('/').filter(v => v !== '');
        if (pathArr.length > 0 && pathArr[0] === 'CMCC') {
          pathArr.shift();
        }
        const startFid = url.searchParams.get('fid') || rootFid;
        const targetFid = await findFileByPath(startFid, [...pathArr]);
        if (!targetFid) {
          return new Response(`路径不存在：${htmlEscape(realPathStr)}`, {
            headers: { 'Content-Type': 'text/html;charset=UTF-8' }
          });
        }
        const dl = await getDownloadUrl(targetFid);
        if (!dl.url) {
          return new Response(`<pre style="background:#111;color:#f33;padding:16px">下载接口返回错误：\n${dl.raw}</pre>`, {
            headers: { 'Content-Type': 'text/html;charset=UTF-8' }
          });
        }
        return Response.redirect(dl.url, 302);
      }

      // ---------- 6. 登录处理（仅 /admin）----------
      if (isAdminPath && request.method === 'POST') {
        const formData = await request.formData();
        const password = formData.get('password');
        if (password === pagePassword) {
          return new Response(null, {
            status: 302,
            headers: {
              'Location': '/admin',
              'Set-Cookie': 'page_login=1; Path=/; HttpOnly; SameSite=Lax'
            }
          });
        } else {
          return renderLoginPage(true);
        }
      }

      // ---------- 7. 未登录且是 /admin 路径，显示登录 ----------
      if (isAdminPath && !isLogin) {
        return renderLoginPage(false);
      }

      // ---------- 8. 渲染文件列表 ----------
      const effectiveRootId = isShareMode ? shareRootId : rootFid;
      const rootName = isShareMode ? '分享根目录' : 'CMCC';

      let currentFid;
      if (isShareMode) {
        currentFid = effectiveRootId;
      } else {
        currentFid = url.searchParams.get('fid') || effectiveRootId;
      }

      // 构建面包屑
      let breadcrumb = [];
      const breadcrumbParam = url.searchParams.get('breadcrumb');
      if (breadcrumbParam && !isShareMode) {
        try {
          breadcrumb = JSON.parse(atob_utf8(breadcrumbParam));
        } catch (e) { /* ignore */ }
      }

      if (breadcrumb.length === 0 || breadcrumb[0].fid !== effectiveRootId) {
        breadcrumb = [{ name: rootName, fid: effectiveRootId }];
      }

      if (currentFid !== effectiveRootId) {
        const last = breadcrumb[breadcrumb.length - 1];
        if (!last || last.fid !== currentFid) {
          let folderName = '未知文件夹';
          const parentFid = breadcrumb.length >= 2 ? breadcrumb[breadcrumb.length - 2].fid : effectiveRootId;
          const items = await api_list(parentFid);
          for (const item of items) {
            if (item.fileId === currentFid && item.type === 'folder') {
              folderName = item.name;
              break;
            }
          }
          breadcrumb.push({ name: folderName, fid: currentFid });
        }
      } else {
        breadcrumb = [{ name: rootName, fid: effectiveRootId }];
      }

      const items = await api_list(currentFid);

      // ---------- 链接构建辅助 ----------
      // 基础 URL（分享模式带 share_token，非分享模式为 /admin）
      const baseHref = isShareMode
        ? `?share_token=${encodeURIComponent(shareToken)}`
        : '/admin';

      // 构建带参数的链接（处理 ? 或 &）
      function buildHref(params) {
        const query = new URLSearchParams(params);
        const queryStr = query.toString();
        if (!queryStr) return baseHref;
        const connector = baseHref.includes('?') ? '&' : '?';
        return baseHref + connector + queryStr;
      }

      // ---------- 生成面包屑 HTML ----------
      let bcHtml = '';
      for (let i = 0; i < breadcrumb.length; i++) {
        if (i > 0) bcHtml += ' <span class="sep">/</span> ';
        const sliceBc = breadcrumb.slice(0, i + 1);
        const encoded = btoa_utf8(JSON.stringify(sliceBc));
        const href = buildHref({
          fid: breadcrumb[i].fid,
          breadcrumb: encoded
        });
        bcHtml += `<a href="${href}">${htmlEscape(breadcrumb[i].name)}</a>`;
      }

      // ---------- 生成文件列表行 ----------
      let rowsHtml = '';
      if (items.length === 0) {
        rowsHtml = '<tr><td colspan="3">目录无内容 / 凭证可能过期</td></tr>';
      } else {
        for (const item of items) {
          if (item.type === 'folder') {
            // 检查是否已分享（仅非分享模式）
            let isShared = false;
            if (!isShareMode && isLogin) {
              const list = await kv.list();
              for (const key of list.keys) {
                const val = await kv.get(key.name);
                if (val === item.fileId) {
                  isShared = true;
                  break;
                }
              }
            }
            let shareBtnHtml;
            if (isShareMode) {
              shareBtnHtml = '<span style="color:#9ca3af;">只读</span>';
            } else {
              shareBtnHtml = isShared
                ? `<button class="unshare-btn" data-fid="${item.fileId}" onclick="unshareFolder(this)">取消分享</button>`
                : `<button class="share-btn" data-fid="${item.fileId}" onclick="shareFolder(this)">分享</button>`;
            }

            const newBc = [...breadcrumb, { name: item.name, fid: item.fileId }];
            const bcEncoded = btoa_utf8(JSON.stringify(newBc));
            const href = buildHref({
              fid: item.fileId,
              breadcrumb: bcEncoded
            });
            rowsHtml += `<tr>
              <td class="folder"><a href="${href}">📁 ${htmlEscape(item.name)}</a></td>
              <td class="size">-</td>
              <td>${shareBtnHtml}</td>
            </tr>`;
          } else {
            // 文件
            let downloadLink;
            if (isShareMode) {
              const href = buildHref({
                download_fid: item.fileId,
                fid: currentFid
              });
              downloadLink = `<a href="${href}">下载</a>`;
            } else {
              const idHref = buildHref({ fid: currentFid, download_fid: item.fileId });
              const paramHref = buildHref({ fid: currentFid, path: item.name });
              // 路径下载（完整路径）
              const pathParts = ['CMCC', ...breadcrumb.slice(1).map(b => b.name), item.name];
              const pathStr = pathParts.join('/');
              const fullPathHref = buildHref({ path: pathStr });
              downloadLink = `<a href="${idHref}">ID下载</a>｜
                              <a href="${paramHref}">参数下载</a>｜
                              <a href="${fullPathHref}">路径下载</a>`;
            }
            rowsHtml += `<tr>
              <td class="file">${htmlEscape(item.name)}</td>
              <td class="size">${formatSize(item.size)}</td>
              <td>${downloadLink}</td>
            </tr>`;
          }
        }
      }

      // ---------- 返回上一级按钮 ----------
      let backBtnHtml = '';
      if (currentFid !== effectiveRootId && breadcrumb.length >= 2) {
        const prevItem = breadcrumb[breadcrumb.length - 2];
        const prevBc = btoa_utf8(JSON.stringify(breadcrumb.slice(0, -1)));
        const href = buildHref({
          fid: prevItem.fid,
          breadcrumb: prevBc
        });
        backBtnHtml = `<a class="back-btn" href="${href}">← 返回上一级</a>`;
      }

      // ---------- 页面 HTML ----------
      const pageHtml = `<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>${isShareMode ? '分享文件夹 - CMCC云盘' : 'CMCC云盘文件浏览'}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
body{max-width:1240px;margin:30px auto;padding:0 16px}
.header-wrap{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}
h1{font-size:20px;color:#1f2937}
.logout-btn{padding:7px 14px;background:#ef4444;color:#fff;border-radius:6px;text-decoration:none;font-size:14px}
.logout-btn:hover{background:#dc2626}
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
  padding:4px 12px;
  border-radius:4px;
  font-size:13px;
  border:none;
  color:#fff;
  background:#10b981;
  cursor:pointer;
}
.unshare-btn{background:#ef4444;}
.share-btn:hover{background:#059669;}
.unshare-btn:hover{background:#dc2626;}
/* 模态框 */
.modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background: rgba(0,0,0,0.5);
  align-items: center;
  justify-content: center;
}
.modal-content {
  background: #fff;
  padding: 30px 35px;
  border-radius: 12px;
  max-width: 500px;
  width: 90%;
  box-shadow: 0 20px 60px rgba(0,0,0,0.3);
  position: relative;
  text-align: center;
}
.modal-content h3 {
  margin-bottom: 15px;
  color: #1f2937;
}
.modal-content .link-box {
  background: #f3f4f6;
  padding: 12px 16px;
  border-radius: 6px;
  word-break: break-all;
  margin: 15px 0;
  font-size: 14px;
  color: #2563eb;
}
.modal-content .copy-btn {
  background: #4f46e5;
  color: #fff;
  border: none;
  padding: 8px 20px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
}
.modal-content .copy-btn:hover {
  background: #4338ca;
}
.modal-content .close-btn {
  position: absolute;
  top: 12px;
  right: 20px;
  font-size: 24px;
  cursor: pointer;
  color: #9ca3af;
}
@media(max-width:720px){
  table th:nth-child(2),table td:nth-child(2){display:none;}
  th,td{padding:10px 8px;font-size:14px}
}
</style>
</head>
<body>
<div class="header-wrap">
  <h1>${isShareMode ? '📂 分享文件夹内容' : '📂 CMCC云盘文件浏览'}</h1>
  ${!isShareMode ? `<a class="logout-btn" href="/admin?logout=1">🚪 退出登录</a>` : ''}
</div>
${backBtnHtml}
<div class="breadcrumb">📂 当前路径：${bcHtml}</div>
<table>
  <tr><th>文件名</th><th>大小</th><th>操作</th></tr>
  ${rowsHtml}
</table>

<!-- 模态框 -->
<div id="shareModal" class="modal">
  <div class="modal-content">
    <span class="close-btn" onclick="document.getElementById('shareModal').style.display='none'">&times;</span>
    <h3>🔗 分享链接已生成</h3>
    <p style="color:#6b7280;font-size:14px;">永久有效，复制链接发给朋友即可访问</p>
    <div class="link-box" id="shareLinkDisplay">加载中...</div>
    <button class="copy-btn" onclick="copyShareLink()">📋 复制链接</button>
  </div>
</div>

<script>
// 分享功能
async function shareFolder(btn) {
  const fid = btn.dataset.fid;
  try {
    const resp = await fetch('/admin?action=share&fid=' + encodeURIComponent(fid));
    const data = await resp.json();
    if (data.success) {
      document.getElementById('shareLinkDisplay').textContent = data.share_link;
      document.getElementById('shareModal').style.display = 'flex';
      window._shareLink = data.share_link;
    } else {
      alert('分享失败：' + (data.error || '未知错误'));
    }
  } catch (e) {
    alert('请求失败：' + e.message);
  }
}

async function unshareFolder(btn) {
  const fid = btn.dataset.fid;
  if (!confirm('确认取消分享此文件夹？')) return;
  try {
    const resp = await fetch('/admin?action=unshare&fid=' + encodeURIComponent(fid));
    const data = await resp.json();
    if (data.success) {
      location.reload();
    } else {
      alert('取消失败：' + (data.error || '未知错误'));
    }
  } catch (e) {
    alert('请求失败：' + e.message);
  }
}

function copyShareLink() {
  const link = window._shareLink;
  if (!link) return;
  if (navigator.clipboard) {
    navigator.clipboard.writeText(link).then(() => {
      alert('链接已复制到剪贴板');
    }).catch(() => {
      fallbackCopy(link);
    });
  } else {
    fallbackCopy(link);
  }
}

function fallbackCopy(text) {
  const input = document.createElement('input');
  input.value = text;
  document.body.appendChild(input);
  input.select();
  document.execCommand('copy');
  document.body.removeChild(input);
  alert('链接已复制到剪贴板');
}

window.onclick = function(event) {
  const modal = document.getElementById('shareModal');
  if (event.target === modal) {
    modal.style.display = 'none';
  }
}
</script>
</body>
</html>`;

      return new Response(pageHtml, {
        headers: { 'Content-Type': 'text/html;charset=UTF-8' }
      });

    } catch (err) {
      return new Response(`Worker Error:\n${err.stack}`, {
        status: 500,
        headers: { 'Content-Type': 'text/plain;charset=UTF-8' }
      });
    }
  }
};
