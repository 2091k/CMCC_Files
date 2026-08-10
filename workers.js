export default {
  async fetch(request, env) {
    try {
      const url = new URL(request.url);
      const auth = env.AUTH;
      const rootFid = env.ROOT_ID;
      const pagePassword = env.PAGE_PASSWORD;

      // ---------- 安全 Base64 编/解码（支持中文）----------
      function btoa_utf8(str) {
        return btoa(unescape(encodeURIComponent(str)));
      }
      function atob_utf8(base64) {
        return decodeURIComponent(escape(atob(base64)));
      }

      // ---------- 登录页面渲染函数 ----------
      function renderLoginPage(hasError) {
        const errorBlock = hasError
          ? '<div class="error">密码错误，请重新输入</div>'
          : '';
        const html = `<!DOCTYPE html>
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
  <h2>CMCC files 云盘</h2>
  <div class="login-desc">请输入页面访问密码进入云盘浏览</div>
  ${errorBlock}
  <form method="post">
    <input type="password" name="password" placeholder="请输入访问密码" required autocomplete="off">
    <button type="submit">确认登录</button>
  </form>
</div>
</body>
</html>`;
        return new Response(html, {
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

      function getPathUrl(breadcrumb, fileName) {
        const parts = ['CMCC'];
        for (let i = 1; i < breadcrumb.length; i++) {
          parts.push(breadcrumb[i].name);
        }
        parts.push(fileName);
        return '?path=' + encodeURIComponent(parts.join('/'));
      }

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

      // ---------- 1. 退出登录 ----------
      if (url.searchParams.has('logout')) {
        return new Response(null, {
          status: 302,
          headers: {
            'Location': url.pathname,
            'Set-Cookie': 'page_login=; Path=/; Max-Age=0; HttpOnly; SameSite=Lax'
          }
        });
      }

      // ---------- 2. 下载请求（无需密码）----------
      if (url.searchParams.has('path') || url.searchParams.has('download_fid')) {
        if (url.searchParams.has('download_fid')) {
          const fid = url.searchParams.get('download_fid');
          const dl = await getDownloadUrl(fid);
          if (!dl.url) {
            return new Response(`<pre style="background:#111;color:#f33;padding:16px">下载接口返回错误：\n${dl.raw}</pre>`, {
              headers: { 'Content-Type': 'text/html;charset=UTF-8' }
            });
          }
          return Response.redirect(dl.url, 302);
        }
        if (url.searchParams.has('path')) {
          const pathRaw = decodeURIComponent(url.searchParams.get('path')).replace(/\/+$/, '');
          const pathArr = pathRaw.split('/').filter(v => v !== '');
          if (pathArr.length > 0 && pathArr[0] === 'CMCC') {
            pathArr.shift();
          }
          const startFid = url.searchParams.get('fid') || rootFid;
          const targetFid = await findFileByPath(startFid, [...pathArr]);
          if (!targetFid) {
            return new Response(`路径不存在，请核对名称：${htmlEscape(pathRaw)}`, {
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
      }

      // ---------- 3. 登录处理 ----------
      const cookies = parseCookies(request.headers.get('Cookie') || '');
      const isLogin = cookies.page_login === '1';

      if (request.method === 'POST') {
        const formData = await request.formData();
        const password = formData.get('password');
        if (password === pagePassword) {
          const redirectUrl = url.pathname + url.search;
          return new Response(null, {
            status: 302,
            headers: {
              'Location': redirectUrl || '/',
              'Set-Cookie': 'page_login=1; Path=/; HttpOnly; SameSite=Lax'
            }
          });
        } else {
          return renderLoginPage(true);
        }
      }

      // ---------- 4. 未登录 ----------
      if (!isLogin) {
        return renderLoginPage(false);
      }

      // ---------- 5. 已登录，渲染文件浏览 ----------
      const browsePath = url.searchParams.get('browse');
      let fid, breadcrumb = [];

      if (browsePath) {
        // === 路径浏览模式 ===
        const rawPath = decodeURIComponent(browsePath).replace(/^\/+|\/+$/g, '');
        const pathArr = rawPath.split('/').filter(v => v !== '');
        if (pathArr.length > 0 && pathArr[0] === 'CMCC') pathArr.shift();

        let currentFid = rootFid;
        breadcrumb = [{ name: '根目录', fid: rootFid }];
        let foundFid = rootFid;

        for (const folderName of pathArr) {
          const items = await api_list(currentFid);
          const found = items.find(item => item.name === folderName && item.type === 'folder');
          if (!found) {
            return new Response(`路径不存在：${htmlEscape(folderName)}`, {
              headers: { 'Content-Type': 'text/html;charset=UTF-8' }
            });
          }
          breadcrumb.push({ name: found.name, fid: found.fileId });
          currentFid = found.fileId;
          foundFid = found.fileId;
        }
        fid = foundFid;
      } else {
        // === 原有 fid / breadcrumb 模式 ===
        fid = url.searchParams.get('fid') || rootFid;
        const breadcrumbParam = url.searchParams.get('breadcrumb');
        if (breadcrumbParam) {
          try {
            breadcrumb = JSON.parse(atob_utf8(breadcrumbParam));
          } catch (e) { /* 忽略解析错误 */ }
        }

        if (fid === rootFid) {
          breadcrumb = [{ name: '根目录', fid: rootFid }];
        } else {
          if (breadcrumb.length === 0) {
            breadcrumb = [{ name: '未知文件夹', fid: fid }];
          } else {
            const last = breadcrumb[breadcrumb.length - 1];
            if (!last || last.fid !== fid) {
              let folderName = '未知文件夹';
              const parentFid = breadcrumb.length >= 1 ? breadcrumb[breadcrumb.length - 1].fid : null;
              if (parentFid) {
                const items = await api_list(parentFid);
                for (const item of items) {
                  if (item.fileId === fid && item.type === 'folder') {
                    folderName = item.name;
                    break;
                  }
                }
              }
              breadcrumb.push({ name: folderName, fid: fid });
            }
          }
        }
      }

      const items = await api_list(fid);

      // 上一级按钮
      let backBtnHtml = '';
      if (fid !== rootFid && breadcrumb.length >= 2) {
        const prevItem = breadcrumb[breadcrumb.length - 2];
        const prevBc = btoa_utf8(JSON.stringify(breadcrumb.slice(0, -1)));
        backBtnHtml = `<a class="back-btn" href="?fid=${encodeURIComponent(prevItem.fid)}&breadcrumb=${encodeURIComponent(prevBc)}">← 返回上一级</a>`;
      }

      // 面包屑导航
      let bcHtml = `<a href="?fid=${encodeURIComponent(rootFid)}">CMCC</a>`;
      for (let i = 0; i < breadcrumb.length; i++) {
        bcHtml += ' <span class="sep">/</span> ';
        const sliceBc = breadcrumb.slice(0, i + 1);
        const encoded = btoa_utf8(JSON.stringify(sliceBc));
        bcHtml += `<a href="?fid=${encodeURIComponent(breadcrumb[i].fid)}&breadcrumb=${encodeURIComponent(encoded)}">${htmlEscape(breadcrumb[i].name)}</a>`;
      }

      // 文件列表
      let rowsHtml = '';
      if (items.length === 0) {
        rowsHtml = '<tr><td colspan="3">目录无内容 / Authorization 或 mcloud-sign 可能过期，请重新抓包替换头部</td></tr>';
      } else {
        for (const item of items) {
          if (item.type === 'folder') {
            const newBc = [...breadcrumb, { name: item.name, fid: item.fileId }];
            const bcEncoded = btoa_utf8(JSON.stringify(newBc));
            rowsHtml += `<tr>
              <td class="folder"><a href="?fid=${encodeURIComponent(item.fileId)}&breadcrumb=${encodeURIComponent(bcEncoded)}">📁 ${htmlEscape(item.name)}</a></td>
              <td class="size">-</td>
              <td>-</td>
            </tr>`;
          } else {
            const pathUrl = getPathUrl(breadcrumb, item.name);
            rowsHtml += `<tr>
              <td class="file">${htmlEscape(item.name)}</td>
              <td class="size">${formatSize(item.size)}</td>
              <td>
                <a href="?fid=${encodeURIComponent(fid)}&download_fid=${encodeURIComponent(item.fileId)}">ID下载</a>｜
                <a href="?fid=${encodeURIComponent(fid)}&path=${encodeURIComponent(item.name)}">参数下载</a>｜
                <a href="${htmlEscape(pathUrl)}">路径下载</a>
              </td>
            </tr>`;
          }
        }
      }

      const pageHtml = `<!DOCTYPE html>
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
${backBtnHtml}
<div class="breadcrumb">📂 当前路径：${bcHtml}</div>
<table>
  <tr><th>文件名</th><th>大小</th><th>操作</th></tr>
  ${rowsHtml}
</table>
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
