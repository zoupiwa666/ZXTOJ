// ZXT OJ VSCode 插件：提交代码 + 编辑题目
const vscode = require('vscode');
const http = require('http');
const https = require('https');

const DEFAULT_SERVER = '156.239.236.66:18001';
const CFG = () => ({
  server: vscode.workspace.getConfiguration('zxt-oj').get('server') || DEFAULT_SERVER,
});

function rawRequest(server, method, path, body, headers, timeout) {
  return new Promise((resolve, reject) => {
    const mod = server.startsWith('https') ? https : http;
    const url = new URL((server.startsWith('http') ? '' : 'http://') + server + path);
    const req = mod.request(url, {
      method, headers: Object.assign({ 'User-Agent': 'ZXT-OJ-VSCode/1.0' }, headers || {})
    }, res => {
      let data = '';
      res.on('data', c => data += c);
      res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body: data }));
    });
    req.on('error', reject);
    req.setTimeout(timeout || 30000, () => req.destroy(new Error('请求超时')));
    if (body) req.write(body);
    req.end();
  });
}

// ---------------- OJ API ----------------
class OJApi {
  constructor(ctx) { this.ctx = ctx; }
  get cred() { return this.ctx.globalState.get('zxt-oj', {}); }
  set cred(v) { this.ctx.globalState.update('zxt-oj', v); }
  server() { return this.cred.server || CFG().server; }
  cookie() { return this.cred.ojcid ? `OJCID=${this.cred.ojcid}` : ''; }

  async login(username, password) {
    const body = new URLSearchParams({ username, password }).toString();
    const r = await rawRequest(this.server(), 'POST', '/login.php', body,
      { 'Content-Type': 'application/x-www-form-urlencoded' });
    // 302 + Set-Cookie: OJCID=...
    const sc = (r.headers['set-cookie'] || []).join(';');
    const m = sc.match(/OJCID=([a-f0-9]{48})/);
    if (m) {
      this.cred = Object.assign({}, this.cred, { server: this.server(), ojcid: m[1], username });
      return { ok: true, ojcid: m[1] };
    }
    return { ok: false, message: `登录失败（HTTP ${r.status}）` };
  }

  async get(path) {
    const r = await rawRequest(this.server(), 'GET', path, null, { Cookie: this.cookie() });
    try { return { ok: r.status === 200, status: r.status, data: JSON.parse(r.body), body: r.body }; }
    catch (e) { return { ok: false, status: r.status, message: '响应解析失败' }; }
  }
  async post(path, jsonBody) {
    const r = await rawRequest(this.server(), 'POST', path, JSON.stringify(jsonBody),
      { 'Content-Type': 'application/json', Cookie: this.cookie() });
    try { return { ok: r.status === 200, status: r.status, data: JSON.parse(r.body), body: r.body }; }
    catch (e) { return { ok: false, status: r.status, message: r.body.slice(0, 120) }; }
  }

  async problems() {
    const r = await this.get('/api/problems_json.php');
    return r.ok ? r.data : [];
  }
  async problem(pid) {
    const r = await this.get(`/api/problem_json.php?id=${encodeURIComponent(pid)}`);
    return r.ok ? r.data : null;
  }
  async saveProblem(d) {
    const r = await this.post('/api/problem_save.php', d);
    return r.ok ? r.data : { ok: false, message: r.message || '保存失败' };
  }
  async submit(pid, lang, code) {
    const r = await this.post('/api/submit.php',
      { problem_id: pid, language: lang, code, time_limit: 2.0, memory_limit: 128 });
    return r.ok ? r.data.submission_id : null;
  }
  async status(sid) {
    const r = await this.get(`/api/submission_status.php?id=${sid}`);
    return r.ok ? r.data : null;
  }
}

function langOfFile(fileName) {
  const map = { '.py': 'python3', '.cpp': 'cpp17', '.cc': 'cpp17', '.cxx': 'cpp17',
                '.c': 'c', '.py3': 'python3', '.java': 'cpp17' }; // java 暂按 cpp17 提示
  for (const k in map) if (fileName.endsWith(k)) return map[k];
  return null;
}

// ---------------- 命令实现 ----------------
async function cmdLogin(ctx, api) {
  const server = await vscode.window.showInputBox({ prompt: 'OJ 服务器地址（IP:端口）', value: api.cred.server || DEFAULT_SERVER });
  if (!server) return;
  const username = await vscode.window.showInputBox({ prompt: '用户名' });
  if (!username) return;
  const password = await vscode.window.showInputBox({ prompt: '密码', password: true });
  if (!password) return;
  api.cred = Object.assign({}, api.cred, { server });
  const r = await api.login(username, password);
  if (r.ok) {
    vscode.window.showInformationMessage(`✅ 已登录 ${username}（OJCID 一周有效）`);
  } else {
    vscode.window.showErrorMessage(`❌ ${r.message}`);
  }
}

async function cmdSubmit(ctx, api) {
  const ed = vscode.window.activeTextEditor;
  if (!ed) { vscode.window.showWarningMessage('请先打开要提交的代码文件'); return; }
  const fileName = ed.document.fileName;
  const code = ed.document.getText();
  let lang = langOfFile(fileName);
  if (!lang) {
    lang = await vscode.window.showQuickPick(['python3', 'cpp17', 'cpp14', 'cpp20', 'c'],
      { placeHolder: '无法识别语言，请选择' });
    if (!lang) return;
  }
  const probs = await api.problems();
  if (!probs.length) { vscode.window.showErrorMessage('题库加载失败（请先登录）'); return; }
  const pick = await vscode.window.showQuickPick(
    probs.map(p => ({ label: p.problem_id, description: p.title, detail: `${p.ac}/${p.submissions} AC`, p })),
    { placeHolder: '选择题目' });
  if (!pick) return;
  const pid = pick.p.label;
  vscode.window.showInformationMessage(`⏳ 提交 ${pid} (${lang}) ...`);
  const sid = await api.submit(pid, lang, code);
  if (!sid) { vscode.window.showErrorMessage('提交失败（检查登录/题目）'); return; }
  // 轮询结果
  for (let i = 0; i < 120; i++) {
    await new Promise(r => setTimeout(r, 2000));
    const s = await api.status(sid);
    if (!s) continue;
    const st = s.status || '';
    if (['waiting', 'judging', 'compiling'].includes(st)) continue;
    const color = st === 'AC' ? 'green' : (st === 'WA' ? 'red' : 'yellow');
    const msg = `[${pid}] ${st}  ${s.score}/${s.max_score}  通过 ${s.passed_tests}/${s.total_tests}  耗时 ${s.total_time}s`;
    vscode.window.showInformationMessage(msg, { modal: false });
    const out = vscode.window.createOutputChannel('ZXT OJ');
    out.appendLine(`提交 #${sid} ${pid} ${lang}`);
    out.appendLine('='.repeat(40));
    out.appendLine(`状态: ${st}   得分: ${s.score}/${s.max_score}   通过: ${s.passed_tests}/${s.total_tests}`);
    out.appendLine(`总耗时: ${s.total_time}s  峰值内存: ${s.peak_memory}MB`);
    try {
      const det = JSON.parse(s.details || '[]');
      det.forEach((r, i) => {
        if (!r) return;
        out.appendLine(`  #${i + 1}  ${r.verdict}  ${r.time_used}s  ${r.memory_used}MB` +
          (r.error ? `  ${r.error.slice(0, 60)}` : ''));
      });
    } catch (e) {}
    out.show(true);
    return;
  }
  vscode.window.showWarningMessage('评测超时，请到网页查看');
}

function webviewHtml(api, pid) {
  return `<!DOCTYPE html><html><head><meta charset="utf-8">
<style>
body{background:#0e0e12;color:#d5d5de;font-family:Segoe UI,sans-serif;padding:16px}
h1{font-size:18px;color:#fff;font-weight:400;margin:0 0 4px}
.desc{color:#8a8a96;font-size:12px;margin-bottom:14px}
label{display:block;color:#8a8a96;font-size:11px;margin:8px 0 3px}
input,textarea,select{width:100%;background:#101016;border:1px solid #26262f;color:#d5d5de;
  padding:7px 9px;font-size:13px;box-sizing:border-box;outline:none;font-family:inherit}
input:focus,textarea:focus,select:focus{border-color:#4d9fff}
.row{display:flex;gap:10px}
.row>div{flex:1}
.btn{margin-top:16px;padding:8px 26px;background:#1a3a5c;color:#fff;border:none;cursor:pointer;font-size:13px}
.btn:hover{background:#1f4a75}
#msg{margin-top:10px;font-size:12px}
.ok{color:#2ecc71}.err{color:#ff5f56}
</style></head><body>
<h1>✎ 编辑题目</h1>
<div class="desc">保存后同步 config.yaml 与数据库</div>
<label>题目编号</label>
<div class="row"><div style="flex:3"><input id="pid" placeholder="如 P1000"></div>
<div style="flex:1"><button class="btn" onclick="load()">载入</button></div></div>
<label>标题</label><input id="title">
<div class="row"><div><label>时间限制(s)</label><input id="tl" type="number" value="2" step="0.5"></div>
<div><label>内存限制(MB)</label><input id="ml" type="number" value="128"></div>
<div><label>可见性</label><select id="vis"><option value="public">public</option><option value="hidden">hidden</option></select></div></div>
<label>背景（Markdown / LaTeX）</label><textarea id="bg" rows="2"></textarea>
<label>题目描述</label><textarea id="desc" rows="8"></textarea>
<div id="msg"></div>
<button class="btn" onclick="save()">保存题目</button>
<script>
const vscode = acquireVsCodeApi();
function load(){ vscode.postMessage({cmd:'load', pid: document.getElementById('pid').value.trim()}); }
function save(){
  vscode.postMessage({cmd:'save', data:{
    problem_id: document.getElementById('pid').value.trim(),
    title: document.getElementById('title').value.trim(),
    time_limit: parseFloat(document.getElementById('tl').value)||2,
    memory_limit: parseInt(document.getElementById('ml').value)||128,
    visibility: document.getElementById('vis').value,
    background: document.getElementById('bg').value,
    description: document.getElementById('desc').value,
    input_format:'', output_format:'', hints:''
  }});
}
window.addEventListener('message', e => {
  const d = e.data;
  if(d.type==='loaded' && d.p){
    document.getElementById('title').value = d.p.title||'';
    document.getElementById('tl').value = d.p.time_limit||2;
    document.getElementById('ml').value = d.p.memory_limit||128;
    document.getElementById('vis').value = d.p.visibility||'public';
    document.getElementById('bg').value = d.p.background||'';
    document.getElementById('desc').value = d.p.description||'';
  }
  const m = document.getElementById('msg');
  if(d.type==='msg'){ m.textContent = d.text; m.className = d.ok?'ok':'err'; }
});
</script></body></html>`;
}

async function cmdEditProblem(ctx, api) {
  const panel = vscode.window.createWebviewPanel('zxt-oj-edit', 'ZXT OJ 编辑题目',
    vscode.ViewColumn.One, { enableScripts: true });
  panel.webview.html = webviewHtml(api);
  panel.webview.onDidReceiveMessage(async msg => {
    if (msg.cmd === 'load') {
      const d = await api.problem(msg.pid);
      panel.webview.postMessage(d ? { type: 'loaded', p: d } : { type: 'msg', text: '题目不存在或无权查看', ok: false });
    } else if (msg.cmd === 'save') {
      const d = await api.saveProblem(msg.data);
      panel.webview.postMessage({ type: 'msg', text: d.message || (d.ok ? '已保存' : '失败'), ok: !!d.ok });
      if (d.ok) vscode.window.showInformationMessage('✅ 题目已保存');
    }
  });
}

function activate(ctx) {
  const api = new OJApi(ctx);
  ctx.subscriptions.push(
    vscode.commands.registerCommand('zxt-oj.login', () => cmdLogin(ctx, api)),
    vscode.commands.registerCommand('zxt-oj.submit', () => cmdSubmit(ctx, api)),
    vscode.commands.registerCommand('zxt-oj.editProblem', () => cmdEditProblem(ctx, api))
  );
}
function deactivate() {}
module.exports = { activate, deactivate };
