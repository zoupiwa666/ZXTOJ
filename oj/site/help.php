<?php
require __DIR__.'/inc/config.php';
require __DIR__.'/inc/auth.php';
requireLogin();
$pageTitle = '帮助中心 - Zxt Super OJ';
require __DIR__.'/inc/header.php';
?>
<style>
.help-wrap{max-width:960px;margin:0 auto}
.help-hero{text-align:center;padding:36px 20px 28px;margin-bottom:24px;border:1px solid #222;background:#16161c}
.help-hero h1{font-size:22px;color:#fff;font-weight:400;letter-spacing:3px;margin:0 0 8px}
.help-hero p{color:#888;font-size:13px;margin:0}
.help-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(430px,1fr));gap:16px}
.help-card{background:#1e1e1e;border:1px solid #2a2a2a;padding:20px 22px;transition:border-color .2s}
.help-card:hover{border-color:#5af}
.help-card h2{font-size:14px;color:#fff;font-weight:400;margin:0 0 14px;letter-spacing:1px;display:flex;align-items:center;gap:8px}
.help-card h2 i{color:#5af;width:20px;text-align:center}
.help-card .item{display:flex;gap:10px;padding:8px 0;border-bottom:1px dashed #242424;font-size:12px;color:#aaa;line-height:1.7}
.help-card .item:last-child{border-bottom:none}
.help-card .item i{color:#888;margin-top:3px;width:14px;text-align:center;flex-shrink:0}
.help-card .item b{color:#ddd;font-weight:500}
.help-card .item .tag{color:#5af;font-size:11px;margin-left:6px;flex-shrink:0}
.help-card .item .tag.green{color:#2ecc71}
.help-card .item .tag.orange{color:#ffab00}
.help-note{margin-top:24px;text-align:center;font-size:11px;color:#555}
</style>
<div class="help-wrap">
  <div class="help-hero">
    <h1>HELP · 帮助中心</h1>
    <p>常见功能使用指南 —— 账号 / 评测 / AI 造数据 / 题目管理</p>
  </div>

  <div class="help-grid">

    <div class="help-card"><h2><i class="fa-solid fa-user"></i> 账号与登录</h2>
      <div class="item"><i class="fa-solid fa-right-to-bracket"></i><div><b>登录与注册</b>：右上角注册/登录；注册需要邀请码（联系管理员获取）。</div></div>
      <div class="item"><i class="fa-solid fa-key"></i><div><b>OJCID 长效登录</b>：登录后自动发放 48 位登录凭证（Cookie），<b>一周内免登录</b>；改密码后凭证自动更新，旧凭证立即失效。</div></div>
      <div class="item"><i class="fa-solid fa-user-pen"></i><div><b>个人设置</b>：<a href="profile.php" style="color:#5af">编辑资料</a>可改头像、格言、密码；个人主页显示提交统计。</div></div>
    </div>

    <div class="help-card"><h2><i class="fa-solid fa-book-open"></i> 题库与题目</h2>
      <div class="item"><i class="fa-solid fa-magnifying-glass"></i><div><b>浏览与搜索</b>：<a href="problems.php" style="color:#5af">题库</a>支持按名称/编号搜索，关联度排序。</div></div>
      <div class="item"><i class="fa-solid fa-eye"></i><div><b>题目详情</b>：题面支持 Markdown + LaTeX；含样例、时间/内存限制、提交入口。</div></div>
      <div class="item"><i class="fa-solid fa-list-ul"></i><div><b>题单</b>：<a href="lists.php" style="color:#5af">题单</a>按专题整理题目，方便系统练习。</div></div>
      <div class="item"><i class="fa-solid fa-lock"></i><div><b>权限</b>：公开题人人可做；隐藏题需管理员授权（用户名或组）。</div></div>
    </div>

    <div class="help-card"><h2><i class="fa-solid fa-microchip"></i> 提交与评测</h2>
      <div class="item"><i class="fa-solid fa-code"></i><div><b>支持语言</b>：C / C++14 / C++17 / C++20 / Python3。</div></div>
      <div class="item"><i class="fa-solid fa-gauge-high"></i><div><b>评测机制</b>：指令计数模型计时（与机器负载无关），判定：
        <span style="color:#2ecc71">AC</span> <span style="color:#ff4f4f">WA</span> <span style="color:#ffab00">TLE</span> <span style="color:#d500f9">MLE</span> <span style="color:#f8603a">RE</span> <span style="color:#0091ea">OLE</span> <span style="color:#ff9100">CE</span> <span style="color:#999">SE</span></div></div>
      <div class="item"><i class="fa-solid fa-rotate-right"></i><div><b>重测</b>：管理员可在提交详情页重测，结果异步覆盖原提交。</div></div>
      <div class="item"><i class="fa-solid fa-list-check"></i><div><b>提交记录</b>：<a href="submissions.php" style="color:#5af">记录页</a>实时刷新各测试点状态与耗时。</div></div>
    </div>

    <div class="help-card"><h2><i class="fa-solid fa-wand-magic-sparkles"></i> AI 造数据</h2>
      <div class="item"><i class="fa-solid fa-comments"></i><div><b>聊天式 AI 助手</b>：像聊天一样让 AI 生成/修改测试数据，AI 掌握文件读写与专用工具（生成器 / 标准解法 / checker 自检）。</div></div>
      <div class="item"><i class="fa-solid fa-wrench"></i><div><b>工具链</b>：AI 自动 写文件 → 生成数据 → checker 自检 → 失败自动修复；你点「应用数据」落盘。</div></div>
      <div class="item"><i class="fa-solid fa-file-shield"></i><div><b>checker 支持</b>：Python check 函数 或 C++ testlib.h 两种模式。</div></div>
      <div class="item"><i class="fa-solid fa-save"></i><div><b>会话持久化</b>：对话与工具记录全部保存，刷新页面自动恢复，可多轮修改。</div></div>
    </div>

    <div class="help-card"><h2><i class="fa-solid fa-user-gear"></i> 题目管理（管理员）</h2>
      <div class="item"><i class="fa-solid fa-table-columns"></i><div><b>四栏目配置页</b>：题面 / 数据+config.yaml / 权限 / AI 功能，点击 Tab 切换。</div></div>
      <div class="item"><i class="fa-solid fa-file-lines"></i><div><b>config.yaml 可视化</b>：表单改配置自动同步文件与数据库；上传数据包无 config 自动补全。</div></div>
      <div class="item"><i class="fa-solid fa-file-import"></i><div><b>数据导入</b>：标准上传 / 服务器路径导入；自动扫描 checker 文件（Python 优先）。</div></div>
      <div class="item"><i class="fa-solid fa-clipboard-check"></i><div><b>checker 编辑器</b>：页面直接编写 Python / testlib C++ checker，保存即生效。</div></div>
    </div>

    <div class="help-card"><h2><i class="fa-solid fa-boxes-stacked"></i> 其它功能</h2>
      <div class="item"><i class="fa-solid fa-trophy"></i><div><b>榜单统计</b>：用户主页查看通过率 / 提交分布 / 最近提交。</div></div>
      <div class="item"><i class="fa-solid fa-file-arrow-down"></i><div><b>我的文件</b>：个人文件空间，支持直链下载（<span class="tag">/files/用户名/文件名</span>）。</div></div>
      <div class="item"><i class="fa-solid fa-users"></i><div><b>群组</b>：管理员创建用户组，题目可对组授权。</div></div>
      <div class="item"><i class="fa-solid fa-envelope-open-text"></i><div><b>文章</b>：题解/公告发布（管理员），支持 Markdown 渲染。</div></div>
    </div>

  </div>
  <div class="help-note">ZXT Super OJ · 遇到问题请联系管理员</div>
</div>
<?php require __DIR__.'/inc/footer.php'; ?>
