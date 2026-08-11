<?php
require __DIR__.'/inc/config.php';
require __DIR__.'/inc/auth.php';
requireLogin();
$pageTitle = '帮助中心 - Zxt Super OJ';
require __DIR__.'/inc/header.php';
?>
<style>
.tut-wrap{display:flex;gap:24px;align-items:flex-start;max-width:1100px;margin:0 auto}
.tut-nav{flex:0 0 230px;position:sticky;top:20px;background:#1e1e1e;border:1px solid #2a2a2a;padding:14px 0}
.tut-nav .tn-title{font-size:11px;color:#666;letter-spacing:2px;padding:0 18px 10px;text-transform:uppercase;border-bottom:1px solid #222;margin-bottom:6px}
.tut-nav a{display:flex;align-items:center;gap:8px;padding:9px 18px;font-size:12px;color:#aaa;cursor:pointer;border-left:2px solid transparent;transition:all .15s}
.tut-nav a i{color:#5a7;width:14px;text-align:center;font-size:11px}
.tut-nav a:hover{color:#fff;background:#161616}
.tut-nav a.active{color:#fff;border-left-color:#5af;background:#16161c}
.tut-content{flex:1;min-width:0}
.tut-section{display:none}
.tut-section.active{display:block}
.tut-card{background:#1e1e1e;border:1px solid #2a2a2a;padding:26px 30px;margin-bottom:18px}
.tut-card h1{font-size:19px;color:#fff;font-weight:400;margin:0 0 6px;letter-spacing:1px}
.tut-card .desc{color:#888;font-size:12px;margin-bottom:18px}
.tut-card h2{font-size:13px;color:#5af;font-weight:400;margin:22px 0 10px;letter-spacing:.5px;display:flex;align-items:center;gap:7px}
.tut-card h2 i{font-size:11px}
.tut-card ol,.tut-card ul{margin:0 0 12px;padding-left:22px}
.tut-card li{font-size:12.5px;color:#bbb;line-height:1.9}
.tut-card li b{color:#eee;font-weight:500}
.tut-card code{background:#0d0d12;border:1px solid #222;padding:1px 6px;font-size:11px;color:#b8e;border-radius:3px}
.tut-card .codeblock{background:#0d0d12;border:1px solid #222;padding:12px 14px;font-size:11.5px;color:#aed;line-height:1.7;margin:8px 0 14px;overflow-x:auto;white-space:pre-wrap;word-break:break-all}
.tut-card .warn{background:#2a2210;border:1px solid #4a3a10;color:#e8c86a;font-size:12px;padding:10px 14px;margin:10px 0;line-height:1.7}
.tut-card .ok{background:#0e2a1a;border:1px solid #1e4a2e;color:#7ee0a0;font-size:12px;padding:10px 14px;margin:10px 0;line-height:1.7}
.tut-table{width:100%;border-collapse:collapse;font-size:12px;margin:8px 0 14px}
.tut-table th{color:#999;font-weight:400;text-align:left;padding:7px 10px;border-bottom:1px solid #333;font-size:11px;letter-spacing:1px}
.tut-table td{padding:7px 10px;border-bottom:1px solid #1d1d1d;color:#bbb}
.tut-table td b{color:#eee}
.tut-table .v-ac{color:#2ecc71}.tut-table .v-wa{color:#ff4f4f}.tut-table .v-tle{color:#ffab00}
.tut-table .v-mle{color:#d500f9}.tut-table .v-re{color:#f8603a}.tut-table .v-ole{color:#0091ea}
.tut-table .v-ce{color:#ff9100}.tut-table .v-se{color:#999}
.subtab{display:flex;gap:0;border-bottom:1px solid #222;margin:0 0 16px;flex-wrap:wrap}
.subtab .st{padding:7px 16px;color:#888;cursor:pointer;font-size:12px;border-bottom:2px solid transparent;transition:.15s;user-select:none;letter-spacing:.5px}
.subtab .st:hover{color:#fff}
.subtab .st.active{color:#fff;border-bottom-color:#5af}
.subtab.l2{margin-top:4px}
.subtab.l2 .st{font-size:11.5px;padding:6px 13px}
.subtab.l3{border-bottom-color:#1a1a1a}
.subtab.l3 .st{font-size:11px;padding:5px 11px;border-bottom-color:#1a1a1a}
.subtab.l3 .st.active{color:#7ee0a0;border-bottom-color:#2ecc71}
.subpanel{display:none}
.subpanel.active{display:block}
.subpanel .tut-h{font-size:13px;color:#5af;font-weight:400;margin:16px 0 10px;letter-spacing:.5px}
.subpanel .tut-h:first-child{margin-top:0}
.subpanel ol,.subpanel ul{margin:0 0 12px;padding-left:22px}
.subpanel li{font-size:12.5px;color:#bbb;line-height:1.9}
.subpanel li b{color:#eee;font-weight:500}
@media(max-width:820px){.tut-wrap{flex-direction:column}.tut-nav{position:static;width:100%;display:flex;flex-wrap:wrap;gap:0}.tut-nav a{flex:0 0 50%}}
</style>
<div class="tut-wrap">
  <nav class="tut-nav">
    <div class="tn-title">教程目录</div>
    <a class="active" data-t="1"><i class="fa-solid fa-right-to-bracket"></i> 注册与登录</a>
    <a data-t="2"><i class="fa-solid fa-user-pen"></i> 个人资料</a>
    <a data-t="3"><i class="fa-solid fa-book-open"></i> 浏览题目</a>
    <a data-t="4"><i class="fa-solid fa-microchip"></i> 提交与评测</a>
    <a data-t="5"><i class="fa-solid fa-file-arrow-down"></i> 我的文件</a>
    <a data-t="6"><i class="fa-solid fa-wand-magic-sparkles"></i> AI 造数据</a>
    <a data-t="7"><i class="fa-solid fa-user-gear"></i> 题目管理</a>
    <a data-t="8"><i class="fa-solid fa-users"></i> 群组与权限</a>
    <a data-t="9"><i class="fa-solid fa-circle-question"></i> 常见问题</a>
  </nav>

  <div class="tut-content">

    <!-- ======== 1 注册与登录 ======== -->
    <section class="tut-section active" id="tut-1">
      <div class="tut-card">
        <h1>注册与登录</h1>
        <div class="desc">开始使用 OJ 的第一步：注册账号并登录。</div>
        <h2><i class="fa-solid fa-circle-1"></i> 注册账号</h2>
        <ol>
          <li>点击右上角 <b>Register</b>，填写用户名与密码（至少 6 位）。</li>
          <li>若提示需要<b>邀请码</b>，请联系管理员获取（邀请码由超级管理员在「邀请码」页生成）。</li>
        </ol>
        <h2><i class="fa-solid fa-circle-2"></i> 登录</h2>
        <ol>
          <li>点击右上角 <b>登录</b>，输入用户名与密码。</li>
          <li>登录成功后，系统会为你发放一个 <b>OJCID 登录凭证</b>（写入浏览器 Cookie）。</li>
        </ol>
        <h2><i class="fa-solid fa-circle-3"></i> OJCID 长效登录（一周免登录）</h2>
        <div class="ok"><i class="fa-solid fa-circle-check"></i> 只要浏览器保留 OJCID Cookie，<b>一周内打开 OJ 都会自动登录</b>，无需重复输入密码。</div>
        <ul>
          <li>OJCID 是 48 位随机凭证，绑定你的账号，有效期 <b>7 天</b>（每次访问自动续期）。</li>
          <li><b>修改密码后</b> OJCID 会自动重新生成，旧凭证立即失效。</li>
          <li><b>登出</b>会清除本机 OJCID（其他设备不受影响）。</li>
        </ul>
        <h2><i class="fa-solid fa-circle-4"></i> 登出</h2>
        <p style="font-size:12px;color:#bbb;line-height:1.8">点击右上角「退出」即可登出。登出后本机 Cookie 中的 OJCID 被清除，下次需重新登录。</p>
      </div>
    </section>

    <!-- ======== 2 个人资料 ======== -->
    <section class="tut-section" id="tut-2">
      <div class="tut-card">
        <h1>个人资料设置</h1>
        <div class="desc">修改头像、格言、密码，查看个人统计。</div>
        <h2><i class="fa-solid fa-circle-1"></i> 进入编辑资料</h2>
        <ol><li>在页面右上角点击自己的用户名，进入<b>个人主页</b>。</li><li>点击「编辑」进入 <b>profile.php</b> 资料编辑页。</li></ol>
        <h2><i class="fa-solid fa-circle-2"></i> 修改头像与格言</h2>
        <ul><li><b>头像</b>：上传 jpg/png 图片，保存后全局显示。</li><li><b>格言</b>：一行文字，显示在个人主页。</li></ul>
        <h2><i class="fa-solid fa-circle-3"></i> 修改密码</h2>
        <ol>
          <li>填写「原密码」「新密码（至少 6 位）」「确认新密码」。</li>
          <li>保存后密码立即生效，同时 <b>OJCID 自动更新</b>（其他设备需重新登录）。</li>
        </ol>
        <h2><i class="fa-solid fa-circle-4"></i> 查看个人统计</h2>
        <p style="font-size:12px;color:#bbb;line-height:1.8">个人主页展示：AC 数量、提交总数、通过率、最近提交、题解文章等。</p>
      </div>
    </section>

    <!-- ======== 3 浏览题目 ======== -->
    <section class="tut-section" id="tut-3">
      <div class="tut-card">
        <h1>浏览与搜索题目</h1>
        <div class="desc">在题库中找题、看题面、按专题刷题。</div>
        <h2><i class="fa-solid fa-circle-1"></i> 题库与搜索</h2>
        <ol>
          <li>顶部导航进入 <b>题库</b>。</li>
          <li>搜索框输入<b>编号</b>或<b>标题</b>，结果按相关度排序（编号完全匹配 &gt; 前缀 &gt; 标题包含）。</li>
        </ol>
        <h2><i class="fa-solid fa-circle-2"></i> 阅读题面</h2>
        <ul>
          <li>题目详情页展示：标题、时间/内存限制、题面（支持 Markdown 与 LaTeX 公式）、样例、提示。</li>
          <li>点击「提交」直接进入提交页面。</li>
        </ul>
        <h2><i class="fa-solid fa-circle-3"></i> 题单</h2>
        <ol><li>进入 <b>题单</b>，按专题选择（如入门、数据结构、图论）。</li><li>题单内按顺序刷题，进度自动记录。</li></ol>
        <div class="warn"><i class="fa-solid fa-triangle-exclamation"></i> 隐藏题目需要管理员授权（用户或用户组）才能查看与提交。</div>
      </div>
    </section>

    <!-- ======== 4 提交与评测 ======== -->
    <section class="tut-section" id="tut-4">
      <div class="tut-card">
        <h1>提交代码与评测</h1>
        <div class="desc">提交代码、理解评测状态、查看详细结果。</div>
        <h2><i class="fa-solid fa-circle-1"></i> 提交代码</h2>
        <ol>
          <li>进入题目详情页，点击「提交」。</li>
          <li>选择<b>语言</b>（C / C++14 / C++17 / C++20 / Python3），粘贴代码，提交。</li>
        </ol>
        <h2><i class="fa-solid fa-circle-2"></i> 评测机制</h2>
        <p style="font-size:12px;color:#bbb;line-height:1.8">OJ 使用<b>指令计数模型</b>计时：统计程序执行的指令数换算时间，与机器负载无关，评测结果稳定可复现。内存统计使用峰值内存（VmHWM）。</p>
        <h2><i class="fa-solid fa-circle-3"></i> 评测状态说明</h2>
        <table class="tut-table">
          <tr><th>状态</th><th>含义</th><th>常见原因</th></tr>
          <tr><td class="v-ac">AC</td><td>Accepted 通过</td><td>所有测试点正确</td></tr>
          <tr><td class="v-wa">WA</td><td>Wrong Answer 答案错误</td><td>算法/输出格式不对</td></tr>
          <tr><td class="v-tle">TLE</td><td>Time Limit Exceeded 超时</td><td>算法复杂度过高、死循环</td></tr>
          <tr><td class="v-mle">MLE</td><td>Memory Limit Exceeded 超内存</td><td>数组过大、内存泄漏</td></tr>
          <tr><td class="v-re">RE</td><td>Runtime Error 运行错误</td><td>数组越界、除零、段错误</td></tr>
          <tr><td class="v-ole">OLE</td><td>Output Limit Exceeded 输出超限</td><td>输出过多（有 checker 时由 checker 判定）</td></tr>
          <tr><td class="v-ce">CE</td><td>Compile Error 编译错误</td><td>语法错误（点开看编译器输出）</td></tr>
          <tr><td class="v-se">SE</td><td>System Error 系统错误</td><td>评测机异常，联系管理员</td></tr>
        </table>
        <h2><i class="fa-solid fa-circle-4"></i> 查看提交详情</h2>
        <ul>
          <li><b>提交记录</b>页可看到每次提交的状态、分数、总耗时、峰值内存。</li>
          <li>点击进入<b>提交详情</b>：逐测试点查看判定、耗时、内存。</li>
        </ul>
        <h2><i class="fa-solid fa-circle-5"></i> 重测（管理员）</h2>
        <p style="font-size:12px;color:#bbb;line-height:1.8">管理员在提交详情页点击「重测」即可重新评测（结果异步覆盖原提交）。</p>
      </div>
    </section>

    <!-- ======== 5 我的文件 ======== -->
    <section class="tut-section" id="tut-5">
      <div class="tut-card">
        <h1>我的文件</h1>
        <div class="desc">个人文件空间：上传、直链下载、分享。</div>
        <h2><i class="fa-solid fa-circle-1"></i> 上传文件</h2>
        <ol><li>进入个人主页 →「我的文件」标签。</li><li>选择文件 → 点「上传」（每人总空间 256MB）。</li></ol>
        <h2><i class="fa-solid fa-circle-2"></i> 下载与直链</h2>
        <ul>
          <li><b>下载</b>：点「下载」以原始文件名保存。</li>
          <li><b>复制直链</b>：生成可直接访问的链接，任何人打开即可下载：</li>
        </ul>
        <div class="codeblock">http://服务器IP:端口/files/用户名/存储文件名</div>
        <h2><i class="fa-solid fa-circle-3"></i> 分享</h2>
        <p style="font-size:12px;color:#bbb;line-height:1.8">把「复制直链」得到的链接发给别人即可分享文件（链接含随机前缀，无法枚举他人文件）。</p>
      </div>
    </section>

    <!-- ======== 6 AI 造数据 ======== -->
    <section class="tut-section" id="tut-6">
      <div class="tut-card">
        <h1>AI 造数据（出题人）</h1>
        <div class="desc">像聊天一样让 AI 生成测试数据，支持多轮修改与自动校验。</div>
        <h2><i class="fa-solid fa-circle-1"></i> 打开 AI 助手</h2>
        <ol><li>进入题目「编辑」页 → 「AI 功能」栏目 → 点「打开 AI 助手」。</li><li>可选填 DeepSeek API Key（保存过可留空）与题目正解 std，点「开始对话」。</li></ol>
        <h2><i class="fa-solid fa-circle-2"></i> 用自然语言生成数据</h2>
        <p style="font-size:12px;color:#bbb;line-height:1.8">直接打字提出要求，例如：</p>
        <div class="codeblock">帮我生成 5 组测试数据，覆盖边界和随机数据
第2组改成大边界输入
加一个忽略行末空格的 checker</div>
        <h2><i class="fa-solid fa-circle-3"></i> AI 的工作方式</h2>
        <ul>
          <li>AI 拥有<b>工具</b>：写文件（gen.py / sol.py / checker.py）、运行生成器、自检 checker。</li>
          <li>生成过程<b>实时展示</b>：AI 思考 → 工具调用卡片（可展开参数与结果）→ 完成。</li>
          <li>checker 自检失败时 AI 会<b>自动修复重试</b>。</li>
        </ul>
        <h2><i class="fa-solid fa-circle-4"></i> 应用数据</h2>
        <ol><li>AI 生成数据后，点「<b>应用数据</b>」。</li><li>数据写入题目数据目录并同步数据库，评测立即可用。</li></ol>
        <h2><i class="fa-solid fa-circle-5"></i> 会话恢复</h2>
        <div class="ok"><i class="fa-solid fa-circle-check"></i> 对话与工具记录全部保存，<b>刷新页面自动恢复</b>，可继续多轮修改。</div>
      </div>
    </section>

    <!-- ======== 7 题目管理（三级子栏目） ======== -->
    <section class="tut-section" id="tut-7">
      <div class="tut-card">
        <h1>题目管理（管理员）</h1>
        <div class="desc">创建题目、配置数据包与 checker、AI 造数据。编辑页分 4 栏目，此处按功能组织讲解。</div>

        <div class="subtab">
          <div class="st active" data-target="tm-statement">题面配置</div>
          <div class="st" data-target="tm-data">数据包配置</div>
          <div class="st" data-target="tm-ai">AI 功能</div>
        </div>

        <!-- 题面配置 -->
        <div class="subpanel active" id="tm-statement">
          <div class="tut-h">创建题目</div>
          <ol>
            <li>题库页点「新建题目」（管理员）。</li>
            <li>填写<b>编号</b>（如 P1000）与<b>标题</b>，保存后进入编辑页。</li>
          </ol>
          <div class="tut-h">题面字段</div>
          <ul>
            <li><b>背景 / 描述 / 输入格式 / 输出格式 / 提示</b>：支持 Markdown 与 LaTeX 公式。</li>
            <li><b>可见性</b>：公开（所有人）或隐藏（需授权）。</li>
            <li><b>样例</b>：可添加多组，显示在题面中。</li>
          </ul>
          <div class="tut-h">编辑页四栏目</div>
          <table class="tut-table">
            <tr><th>栏目</th><th>用途</th></tr>
            <tr><td>题面配置</td><td>标题、背景、描述、输入输出格式、提示、样例</td></tr>
            <tr><td>数据 + config.yaml</td><td>config 可视化、数据包导入、checker 编辑器</td></tr>
            <tr><td>权限配置</td><td>授权用户/组查看隐藏题目</td></tr>
            <tr><td>AI 功能</td><td>AI 助手入口与数据状态</td></tr>
          </table>
        </div>

        <!-- 数据包配置 -->
        <div class="subpanel" id="tm-data">
          <div class="subtab l2">
            <div class="st active" data-target="pk-checker">checker 配置</div>
            <div class="st" data-target="pk-format">数据包格式</div>
            <div class="st" data-target="pk-config">config.yaml</div>
          </div>

          <!-- checker 配置 -->
          <div class="subpanel active" id="pk-checker">
            <div class="tut-h">checker 是什么</div>
            <p style="font-size:12px;color:#bbb;line-height:1.8">当答案不唯一、需要浮点误差判断或验证构造合法性时，用 checker 代替标准比对。编辑页「数据 + config.yaml」栏目底部有<b>checker 编辑器</b>，两种模式：</p>
            <div class="subtab l3">
              <div class="st active" data-target="ck-py">Python checker</div>
              <div class="st" data-target="ck-cpp">C++ testlib checker</div>
            </div>

            <div class="subpanel active" id="ck-py">
              <div class="tut-h">Python checker · 传参方式</div>
              <p style="font-size:12px;color:#bbb;line-height:1.8">评测端加载代码后直接调用函数 <code>check(input, output, expected)</code>，<b>三个参数均为字符串</b>：</p>
              <table class="tut-table">
                <tr><th>参数</th><th>含义</th></tr>
                <tr><td><code>input</code></td><td>该测试点的输入内容（.in）</td></tr>
                <tr><td><code>output</code></td><td>选手程序的输出内容</td></tr>
                <tr><td><code>expected</code></td><td>标准答案（.out）</td></tr>
              </table>
              <div class="tut-h">返回值</div>
              <div class="codeblock">True / False
或 (是否通过:bool, 提示:str, 得分占比:float)</div>
              <div class="tut-h">示例：忽略行末空格</div>
              <div class="codeblock">def check(input, output, expected):
    def norm(s):
        return '\n'.join(line.rstrip() for line in s.strip().splitlines())
    return norm(output) == norm(expected)</div>
              <div class="tut-h">示例：浮点误差 1e-6</div>
              <div class="codeblock">def check(input, output, expected):
    a, b = float(output.strip()), float(expected.strip())
    if abs(a - b) <= 1e-6:
        return True, 'OK', 1.0
    return False, f'expected {b}, got {a}', 0.0</div>
              <div class="tut-h">示例：构造题校验（利用 input）</div>
              <div class="codeblock">def check(input, output, expected):
    nums = list(map(int, output.split()))
    n = int(input.split()[0])
    if sorted(nums) == list(range(1, n + 1)):
        return True, 'valid', 1.0
    return False, 'not a permutation', 0.0</div>
              <div class="warn"><i class="fa-solid fa-triangle-exclamation"></i> checker 里<b>不要读文件、不要 print</b>；标准答案（expected）必须返回 True。</div>
            </div>

            <div class="subpanel" id="ck-cpp">
              <div class="tut-h">C++ testlib checker · 传参方式</div>
              <p style="font-size:12px;color:#bbb;line-height:1.8">评测端自动编译（<code>g++</code> + 预编译缓存），运行：</p>
              <div class="codeblock">checker 输入文件 选手输出文件 标准答案文件
# argv[1] -> inf    argv[2] -> ouf    argv[3] -> ans</div>
              <div class="tut-h">基本框架</div>
              <div class="codeblock">#include "testlib.h"
int main(int argc, char* argv[]) {
    registerTestlibCmd(argc, argv);   // 必须第一行
    int ja = ans.readInt();           // 标准答案
    int pa = ouf.readInt();           // 选手输出
    if (ja != pa)
        quitf(_wa, "expected %d, found %d", ja, pa);
    quitf(_ok, "OK");
}</div>
              <div class="tut-h">常用读取函数</div>
              <table class="tut-table">
                <tr><th>函数</th><th>含义</th></tr>
                <tr><td><code>readInt() / readLong()</code></td><td>读整数 / 64 位整数</td></tr>
                <tr><td><code>readDouble()</code></td><td>读浮点数</td></tr>
                <tr><td><code>readString()</code></td><td>读一整行</td></tr>
                <tr><td><code>readToken() / readWord()</code></td><td>读一个不含空格的词</td></tr>
                <tr><td><code>readEoln() / readEof()</code></td><td>读换行 / 读到末尾</td></tr>
              </table>
              <div class="tut-h">判定函数</div>
              <table class="tut-table">
                <tr><th>函数</th><th>结果</th><th>退出码</th></tr>
                <tr><td><code>quitf(_ok, ...)</code></td><td class="v-ac">AC</td><td>0</td></tr>
                <tr><td><code>quitf(_wa, ...)</code></td><td class="v-wa">WA</td><td>1</td></tr>
                <tr><td><code>quitf(_pe, ...)</code></td><td>PE（按 WA）</td><td>2</td></tr>
                <tr><td><code>quitp(points, ...)</code></td><td>部分得分 0~1</td><td>0</td></tr>
              </table>
              <div class="tut-h">示例：浮点误差 1e-6</div>
              <div class="codeblock">#include "testlib.h"
int main(int argc, char* argv[]) {
    registerTestlibCmd(argc, argv);
    double ja = ans.readDouble();
    double pa = ouf.readDouble();
    if (doubleCompare(pa, ja) > 1e-6)
        quitf(_wa, "expected %.6f, found %.6f", ja, pa);
    quitf(_ok, "OK");
}</div>
              <div class="tut-h">示例：读输入辅助判断</div>
              <div class="codeblock">#include "testlib.h"
int main(int argc, char* argv[]) {
    registerTestlibCmd(argc, argv);
    int n = inf.readInt();
    std::string ja = ans.readString();
    std::string pa = ouf.readString();
    if (ja != pa) quitf(_wa, "mismatch");
    quitf(_ok, "n=%d ok", n);
}</div>
              <div class="warn"><i class="fa-solid fa-triangle-exclamation"></i> 必须先 <code>registerTestlibCmd</code> 再读流；标准答案必须返回 <code>_ok</code>，否则自检失败。</div>
            </div>

            <div class="tut-h">两种模式对照</div>
            <table class="tut-table">
              <tr><th></th><th>Python</th><th>C++ testlib</th></tr>
              <tr><td><b>传参</b></td><td><code>check(input, output, expected)</code> 三字符串</td><td><code>checker in out ans</code> 三文件（inf/ouf/ans 流）</td></tr>
              <tr><td><b>判定</b></td><td><code>return True/False</code> 或 <code>(bool,msg,score)</code></td><td><code>quitf(_ok/_wa)</code>；退出码 0=AC 非0=WA</td></tr>
              <tr><td><b>部分得分</b></td><td>三元组第三项 0~1</td><td><code>quitp(points)</code></td></tr>
              <tr><td><b>适用</b></td><td>简单比对、误差、小规模</td><td>大数据、复杂格式、构造题</td></tr>
            </table>
          </div>

          <!-- 数据包格式 -->
          <div class="subpanel" id="pk-format">
            <div class="tut-h">数据包结构</div>
            <div class="codeblock">题目数据目录: /data/problems/P1000/
├── 1.in / 1.out / 1.score    测试点（按编号）
├── config.yaml               题目配置（可缺省，自动补全）
├── checker.py 或 checker.cpp 特殊判题（可缺省）
└── 2.in / 2.out ...</div>
            <div class="tut-h">支持格式</div>
            <ul>
              <li>上传 <b>zip / tar.gz</b>，或填服务器路径 / URL 导入。</li>
              <li>测试点命名 <code>1.in</code>、<code>01.in</code>、<code>test1.in</code> 均可。</li>
            </ul>
            <div class="tut-h">自动补全</div>
            <ul>
              <li><b>无 config.yaml</b>：自动扫描测试文件统计组数，生成默认配置（2s / 128MB / default 评分）。</li>
              <li><b>checker 文件</b>：自动扫描 <code>checker.py</code> / <code>checker.cpp</code> 并落盘（都有时优先 Python）。</li>
            </ul>
            <div class="tut-h">导入方式</div>
            <ol>
              <li>编辑页「数据 + config.yaml」栏目 → 上传文件（标准上传）或填路径（路径导入）。</li>
              <li>导入成功自动写入 config.yaml 并同步数据库。</li>
            </ol>
          </div>

          <!-- config.yaml -->
          <div class="subpanel" id="pk-config">
            <div class="tut-h">config.yaml 字段</div>
            <table class="tut-table">
              <tr><th>字段</th><th>含义</th></tr>
              <tr><td><code>name</code></td><td>题目名称（改题面标题自动同步）</td></tr>
              <tr><td><code>time_limit</code></td><td>时间限制（秒）</td></tr>
              <tr><td><code>memory_limit</code></td><td>内存限制（MB）</td></tr>
              <tr><td><code>test_cases</code></td><td>测试点数量（按实际文件自动统计）</td></tr>
              <tr><td><code>scoring_mode</code></td><td>评分模式（default = 默认平分）</td></tr>
            </table>
            <div class="tut-h">可视化配置</div>
            <ul>
              <li>编辑页「数据 + config.yaml」栏目表单直接改，保存后<b>同步文件与数据库</b>。</li>
              <li>评测以 config.yaml 为准。</li>
            </ul>
            <div class="tut-h">双向同步</div>
            <ul>
              <li>保存题面 → name 自动更新。</li>
              <li>导入数据 / AI 应用数据 → 自动写 config.yaml。</li>
            </ul>
          </div>
        </div>

        <!-- AI 功能 -->
        <div class="subpanel" id="tm-ai">
          <div class="tut-h">打开 AI 助手</div>
          <ol>
            <li>编辑页「AI 功能」栏目 → 「打开 AI 助手」。</li>
            <li>可选填 DeepSeek API Key 与题目正解 std → 「开始对话」。</li>
          </ol>
          <div class="tut-h">聊天生成数据</div>
          <div class="codeblock">帮我生成 5 组测试数据，覆盖边界和随机数据
第2组改成大边界输入
加一个忽略行末空格的 checker</div>
          <div class="tut-h">AI 的工作方式</div>
          <ul>
            <li>AI 拥有<b>工具</b>：写文件（gen.py / sol.py / checker.py）、运行生成器、自检 checker。</li>
            <li>过程实时展示：AI 思考 → 工具调用卡片（可展开参数/结果）→ 完成；自检失败自动修复。</li>
          </ul>
          <div class="tut-h">应用数据与会话</div>
          <ol>
            <li>生成后点「<b>应用数据</b>」→ 写入题目数据目录并同步数据库。</li>
            <li>对话与工具记录全部保存，<b>刷新自动恢复</b>，可多轮修改。</li>
          </ol>
        </div>
      </div>
    </section>

    <!-- ======== 8 群组与权限 ======== -->
    <section class="tut-section" id="tut-9">
      <div class="tut-card">
        <h1>群组与权限</h1>
        <div class="desc">通过用户组批量管理题目访问权限。</div>
        <h2><i class="fa-solid fa-circle-1"></i> 创建用户组（管理员）</h2>
        <ol><li>顶部导航「用户组」→ 创建组。</li><li>添加成员（输入用户名）。</li></ol>
        <h2><i class="fa-solid fa-circle-2"></i> 题目授权</h2>
        <ol>
          <li>进入题目「权限配置」栏目。</li>
          <li>输入 <b>用户名</b> 或 <b>team->组名</b> 授权。</li>
          <li>被授权的用户/组成员可查看与提交该题（即使题目为隐藏）。</li>
        </ol>
        <h2><i class="fa-solid fa-circle-3"></i> 撤销授权</h2>
        <p style="font-size:12px;color:#bbb;line-height:1.8">权限列表中点「撤销」即可移除单个用户/组的访问权限。</p>
      </div>
    </section>

    <!-- ======== 9 FAQ ======== -->
    <section class="tut-section" id="tut-9">
      <div class="tut-card">
        <h1>常见问题（FAQ）</h1>
        <div class="desc">遇到问题先看这里。</div>
        <h2><i class="fa-solid fa-question"></i> 登录总是失效？</h2>
        <div class="ok">已加入 <b>OJCID 长效登录</b>：登录后一周内免登录。若仍频繁掉线，检查浏览器是否禁用了 Cookie。</div>
        <h2><i class="fa-solid fa-question"></i> 提交一直显示等待/评测中？</h2>
        <ul><li>稍等片刻（评测需要数秒）；长时间无响应请刷新页面。</li><li>若仍是 waiting，可能是评测 Worker 未运行，联系管理员。</li></ul>
        <h2><i class="fa-solid fa-question"></i> 同一份代码两次提交耗时不同？</h2>
        <div class="ok">OJ 使用<b>指令计数模型</b>计时，与机器负载无关；结果差异通常来自算法本身的输入数据差异。</div>
        <h2><i class="fa-solid fa-question"></i> checker 怎么用 testlib？</h2>
        <div class="codeblock">#include "testlib.h"
int main(int argc, char* argv[]) {
    registerTestlibCmd(argc, argv);
    if (ans.readInt() != ouf.readInt())
        quitf(_wa, "mismatch");
    quitf(_ok, "ok");
}</div>
        <p style="font-size:12px;color:#bbb;line-height:1.8">在题目「checker 编辑器」选择 C++ 模式粘贴即可，保存后自动预编译，评测直接调用。</p>
        <h2><i class="fa-solid fa-question"></i> 数据包怎么准备？</h2>
        <ul>
          <li>最少只需 <code>1.in / 1.out</code> 等测试文件；config.yaml 与 checker 可缺省（自动补全）。</li>
          <li>支持 zip / tar.gz，测试点命名 <code>1.in</code>、<code>01.in</code>、<code>test1.in</code> 均可。</li>
        </ul>
        <h2><i class="fa-solid fa-question"></i> 显示 SE（系统错误）？</h2>
        <p style="font-size:12px;color:#bbb;line-height:1.8">评测机或数据异常，联系管理员查看日志。</p>
      </div>
    </section>

  </div>
</div>
<script>
function showTut(t){
  document.querySelectorAll('.tut-nav a').forEach(a=>a.classList.toggle('active', a.dataset.t===String(t)));
  document.querySelectorAll('.tut-section').forEach(s=>s.classList.toggle('active', s.id==='tut-'+t));
  window.scrollTo({top:0, behavior:'smooth'});
}
document.querySelectorAll('.subtab .st').forEach(st=>{
  st.addEventListener('click', ()=>{
    const g = st.closest('.subtab');
    g.querySelectorAll('.st').forEach(x=>x.classList.remove('active'));
    st.classList.add('active');
    let el = g.nextElementSibling;
    while(el && el.classList.contains('subpanel')){
      el.classList.toggle('active', el.id === st.dataset.target);
      el = el.nextElementSibling;
    }
  });
});
document.querySelectorAll('.tut-nav a').forEach(a=>{
  a.addEventListener('click', ()=>showTut(a.dataset.t));
});
</script>
<?php require __DIR__.'/inc/footer.php'; ?>
