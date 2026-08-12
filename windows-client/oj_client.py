# -*- coding: utf-8 -*-
"""
ZXT OJ Windows 客户端
依赖：Python 3.8+（标准库，无第三方包）
运行：python oj_client.py    打包：pyinstaller -F -w oj_client.py
功能：登录(OJCID) / 题库列表 / 提交评测 / 结果查询
"""
import tkinter as tk
from tkinter import ttk, messagebox
import json, os, threading, time, urllib.request, urllib.parse, http.cookiejar

CFG_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), "oj_client.json")
DEFAULT_IP = "156.239.236.66:18001"

def load_cfg():
    try:
        with open(CFG_PATH, encoding="utf-8") as f: return json.load(f)
    except Exception: return {"server": DEFAULT_IP, "ojcid": "", "username": ""}

def save_cfg(cfg):
    with open(CFG_PATH, "w", encoding="utf-8") as f: json.dump(cfg, f, ensure_ascii=False)

class OJClient:
    def __init__(self, server):
        self.server = server or DEFAULT_IP
        self.cj = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cj))

    def url(self, path):
        return f"http://{self.server}{path}"

    def req(self, path, data=None, json_body=False, timeout=30):
        """返回 (http_status, text)；自动带 cookie（OJCID）"""
        headers = {"User-Agent": "ZXT-OJ-Client/1.0"}
        body = None
        if data is not None:
            if json_body:
                headers["Content-Type"] = "application/json"
                body = json.dumps(data).encode("utf-8")
            else:
                headers["Content-Type"] = "application/x-www-form-urlencoded"
                body = urllib.parse.urlencode(data).encode("utf-8")
        r = urllib.request.Request(self.url(path), data=body, headers=headers)
        try:
            resp = self.opener.open(r, timeout=timeout)
            return resp.status, resp.read().decode("utf-8", errors="replace")
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode("utf-8", errors="replace")
        except Exception as e:
            return -1, str(e)

    def login(self, user, pw):
        """登录拿 OJCID；成功返回 (ok, msg)"""
        st, txt = self.req("/login.php", {"username": user, "password": pw})
        cid = None
        for c in self.cj:
            if c.name == "OJCID": cid = c.value
        if cid:
            return True, cid
        return False, (f"HTTP {st}" if st > 0 else txt)

    def problems(self):
        st, txt = self.req("/api/problems_json.php")
        if st == 200:
            return json.loads(txt)
        return None

    def submit(self, pid, lang, code):
        st, txt = self.req("/api/submit.php",
                           {"problem_id": pid, "language": lang, "code": code,
                            "time_limit": 2.0, "memory_limit": 128},
                           json_body=True)
        if st == 200:
            try: return json.loads(txt)["submission_id"]
            except Exception: pass
        return None

    def status(self, sid):
        st, txt = self.req(f"/api/submission_status.php?id={sid}")
        if st == 200 and txt.strip():
            try: return json.loads(txt)
            except Exception: return None
        return None

LANGS = ["python3", "cpp17", "cpp14", "cpp20", "c"]
VERDICT_COLOR = {"AC":"#2ecc71","WA":"#ff4f4f","TLE":"#ffab00","MLE":"#d500f9",
                 "RE":"#f8603a","OLE":"#0091ea","CE":"#ff9100","SE":"#999",
                 "judging":"#5af","waiting":"#999","compiling":"#ffab00"}

class App(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title("ZXT OJ 客户端")
        self.geometry("900x640")
        self.configure(bg="#111")
        self.cfg = load_cfg()
        self.client = OJClient(self.cfg.get("server", DEFAULT_IP))
        self.problems = []
        self._build_ui()
        self.after(100, self._auto_login)

    # ---------- UI ----------
    def _build_ui(self):
        style = ttk.Style(self)
        style.theme_use("clam")
        style.configure("TFrame", background="#111")
        style.configure("TLabel", background="#111", foreground="#ccc", font=("Consolas", 10))
        style.configure("TLabelframe", background="#16161c", foreground="#999")
        style.configure("TLabelframe.Label", background="#16161c", foreground="#5af", font=("Consolas", 10))
        style.configure("TButton", background="#2a2a2a", foreground="#ddd", font=("Consolas", 10))
        style.map("TButton", background=[("active", "#3a3a3a")])

        # 顶部：服务器 + 登录
        top = ttk.Frame(self); top.pack(fill="x", padx=8, pady=6)
        ttk.Label(top, text="服务器:").pack(side="left")
        self.e_server = ttk.Entry(top, width=22); self.e_server.pack(side="left", padx=4)
        self.e_server.insert(0, self.cfg.get("server", DEFAULT_IP))
        ttk.Label(top, text="用户:").pack(side="left", padx=(14,2))
        self.e_user = ttk.Entry(top, width=12); self.e_user.pack(side="left")
        self.e_user.insert(0, self.cfg.get("username", ""))
        ttk.Label(top, text="密码:").pack(side="left", padx=(8,2))
        self.e_pass = ttk.Entry(top, width=14, show="*"); self.e_pass.pack(side="left")
        self.btn_login = ttk.Button(top, text="登录", command=self.on_login); self.btn_login.pack(side="left", padx=6)
        self.lb_login = ttk.Label(top, text="未登录"); self.lb_login.pack(side="left", padx=6)

        mid = ttk.Frame(self); mid.pack(fill="both", expand=True, padx=8, pady=4)

        # 左：题库
        lf = ttk.Labelframe(mid, text="题库（双击刷新）"); lf.pack(side="left", fill="y", padx=(0,6))
        self.listbox = tk.Listbox(lf, width=34, bg="#0d0d12", fg="#ccc", selectbackground="#1a3a5c",
                                  font=("Consolas", 10), bd=0, highlightthickness=0)
        self.listbox.pack(side="left", fill="y")
        self.listbox.bind("<Double-Button-1>", lambda e: self.on_refresh_problems())

        # 右：代码 + 结果
        right = ttk.Frame(mid); right.pack(side="left", fill="both", expand=True)

        cframe = ttk.Labelframe(right, text="提交代码"); cframe.pack(fill="both", expand=True)
        crow = ttk.Frame(cframe); crow.pack(fill="x", padx=4, pady=3)
        ttk.Label(crow, text="题目:").pack(side="left")
        self.e_pid = ttk.Entry(crow, width=10); self.e_pid.pack(side="left", padx=3)
        ttk.Label(crow, text="语言:").pack(side="left", padx=(10,2))
        self.cb_lang = ttk.Combobox(crow, values=LANGS, width=9, state="readonly"); self.cb_lang.pack(side="left")
        self.cb_lang.set("python3")
        self.btn_sub = ttk.Button(crow, text="提交评测", command=self.on_submit); self.btn_sub.pack(side="right", padx=4)
        self.txt_code = tk.Text(cframe, bg="#0d0d12", fg="#ccc", insertbackground="#ccc",
                                font=("Consolas", 11), bd=0, wrap="none")
        self.txt_code.pack(fill="both", expand=True, padx=4, pady=(0,4))

        rframe = ttk.Labelframe(right, text="评测结果"); rframe.pack(fill="x", pady=(6,0))
        self.txt_res = tk.Text(rframe, bg="#0a0e14", fg="#bbb", font=("Consolas", 10),
                               height=8, bd=0, state="disabled")
        self.txt_res.pack(fill="both", padx=4, pady=4)

    # ---------- 逻辑 ----------
    def _log(self, s, color=None):
        self.txt_res.config(state="normal")
        self.txt_res.insert("end", s + "\n")
        if color:
            self.txt_res.tag_add("c", f"end-{len(s)+2}c", "end-1c")
            self.txt_res.tag_config("c", foreground=color)
        self.txt_res.see("end"); self.txt_res.config(state="disabled")

    def _set_login(self, ok, msg):
        self.lb_login.config(text=msg, foreground=("#2ecc71" if ok else "#ff6b6b"))

    def _auto_login(self):
        if self.cfg.get("ojcid"):
            self.client.cj.set_cookie(http.cookiejar.Cookie(0, "OJCID", self.cfg["ojcid"],
                None, False, self.cfg.get("server", "").split(":")[0], False, "/", True, False,
                None, True, None, None, {}))
            self._set_login(True, f"已登录 {self.cfg.get('username','')}")
            self.on_refresh_problems()

    def on_login(self):
        self.btn_login.config(state="disabled")
        self._set_login(False, "登录中...")
        def work():
            server = self.e_server.get().strip()
            self.cfg["server"] = server; save_cfg(self.cfg)
            self.client = OJClient(server)
            ok, msg = self.client.login(self.e_user.get().strip(), self.e_pass.get())
            self.after(0, lambda: self._login_done(ok, msg))
        threading.Thread(target=work, daemon=True).start()

    def _login_done(self, ok, msg):
        self.btn_login.config(state="normal")
        if ok:
            self.cfg["ojcid"] = msg; self.cfg["username"] = self.e_user.get().strip(); save_cfg(self.cfg)
            self._set_login(True, f"已登录 {self.cfg['username']}（OJCID 一周有效）")
            self.on_refresh_problems()
        else:
            self._set_login(False, f"登录失败: {msg}")

    def on_refresh_problems(self):
        self.lb_login.config(text="加载题库...", foreground="#ffab00")
        def work():
            ps = self.client.problems()
            self.after(0, lambda: self._fill_problems(ps))
        threading.Thread(target=work, daemon=True).start()

    def _fill_problems(self, ps):
        if ps is None:
            self._set_login(False, "题库加载失败（请检查服务器/OJCID）")
            return
        self.problems = ps
        self.listbox.delete(0, "end")
        for p in ps:
            rate = f"{p['ac']}/{p['submissions']}" if p['submissions'] else "-"
            self.listbox.insert("end", f"  {p['problem_id']:<12} {p['title'][:16]:<16} {rate} AC")
        self._set_login(True, f"已登录 {self.cfg.get('username','')}（{len(ps)} 题）")
        if ps: self.e_pid.delete(0, "end"); self.e_pid.insert(0, ps[0]["problem_id"])

    def on_submit(self):
        pid = self.e_pid.get().strip(); code = self.txt_code.get("1.0", "end").strip()
        if not pid: messagebox.showwarning("提示", "请输入题目编号"); return
        if not code: messagebox.showwarning("提示", "请输入代码"); return
        self.btn_sub.config(state="disabled")
        self._log(f"[提交] {pid} {self.cb_lang.get()}", "#5af")
        def work():
            sid = self.client.submit(pid, self.cb_lang.get(), code)
            if not sid:
                self.after(0, lambda: (self._log("提交失败（检查登录/题目是否存在）", "#ff6b6b"),
                                       self.btn_sub.config(state="normal")))
                return
            self.after(0, lambda: self._log(f"[队列] 提交 #{sid}，等待评测...", "#ffab00"))
            for _ in range(120):
                time.sleep(2)
                s = self.client.status(sid)
                if not s: continue
                st = s.get("status", "")
                if st in ("waiting", "judging", "compiling"):
                    self.after(0, lambda st=st: self._log(f"[{st}] ...", "#888"))
                else:
                    self.after(0, lambda s=s: self._show_result(s))
                    break
            self.after(0, lambda: self.btn_sub.config(state="normal"))
        threading.Thread(target=work, daemon=True).start()

    def _show_result(self, s):
        st = s.get("status", "?"); score = s.get("score", 0); mx = s.get("max_score", 0)
        color = VERDICT_COLOR.get(st, "#ccc")
        self._log(f"========================", "#666")
        self._log(f"状态: {st}    得分: {score}/{mx}    通过: {s.get('passed_tests',0)}/{s.get('total_tests',0)}",
                  color)
        self._log(f"总耗时: {s.get('total_time','-')}s  峰值内存: {s.get('peak_memory','-')}MB", "#aaa")
        try:
            det = json.loads(s.get("details") or "[]")
            if det:
                rows = []
                for r in det:
                    if not r: continue
                    v = r.get("verdict", "?")
                    rows.append(f"  #{r.get('test_case_index',0)+1:<3} {v:<5} {r.get('time_used',0):<8.3f}s {r.get('memory_used',0):<7.2f}MB"
                                + (f"  {r.get('error','')[:60]}" if r.get('error') else ""))
                self._log("\n".join(rows[:25]), "#bbb")
        except Exception:
            pass
        self._log("========================", "#666")

if __name__ == "__main__":
    App().mainloop()
