# -*- coding: utf-8 -*-
"""
ZXT OJ Windows 客户端
功能：登录(OJCID) / 题库 / 查看题目 / 编辑题目 / 上传数据包 / 提交评测
依赖：Python 3.8+（标准库）   打包：pyinstaller -F -w oj_client.py
"""
import tkinter as tk
from tkinter import ttk, messagebox, filedialog
import json, os, threading, time, uuid, urllib.request, urllib.parse, http.cookiejar

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

    def url(self, path): return f"http://{self.server}{path}"

    def req(self, path, data=None, json_body=False, timeout=30):
        headers = {"User-Agent": "Mozilla/5.0 (Windows NT 10.0)"}
        body = None
        if data is not None:
            if json_body:
                headers["Content-Type"] = "application/json"; body = json.dumps(data).encode("utf-8")
            else:
                headers["Content-Type"] = "application/x-www-form-urlencoded"
                body = urllib.parse.urlencode(data).encode("utf-8")
        r = urllib.request.Request(self.url(path), data=body, headers=headers)
        try:
            resp = self.opener.open(r, timeout=timeout); return resp.status, resp.read().decode("utf-8", errors="replace")
        except urllib.error.HTTPError as e: return e.code, e.read().decode("utf-8", errors="replace")
        except Exception as e: return -1, str(e)

    def multipart(self, path, fields, file_field, filename, file_bytes):
        boundary = "----ZXTOJ" + uuid.uuid4().hex
        parts = []
        for k, v in fields.items():
            parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode("utf-8"))
        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{file_field}"; filename="{filename}"\r\nContent-Type: application/zip\r\n\r\n'.encode("utf-8"))
        parts.append(file_bytes); parts.append(f'\r\n--{boundary}--\r\n'.encode("utf-8"))
        body = b"".join(parts)
        r = urllib.request.Request(self.url(path), data=body,
            headers={"Content-Type": f"multipart/form-data; boundary={boundary}", "User-Agent": "Mozilla/5.0"})
        try:
            resp = self.opener.open(r, timeout=120); return resp.status, resp.read().decode("utf-8", errors="replace")
        except urllib.error.HTTPError as e: return e.code, e.read().decode("utf-8", errors="replace")
        except Exception as e: return -1, str(e)

    def login(self, user, pw):
        st, txt = self.req("/login.php", {"username": user, "password": pw})
        cid = next((c.value for c in self.cj if c.name == "OJCID"), None)
        return (True, cid) if cid else (False, f"HTTP {st}" if st > 0 else txt)

    def problems(self):
        st, txt = self.req("/api/problems_json.php")
        return json.loads(txt) if st == 200 else None

    def problem(self, pid):
        st, txt = self.req(f"/api/problem_json.php?id={pid}")
        return json.loads(txt) if st == 200 else None

    def save_problem(self, data):
        st, txt = self.req("/api/problem_save.php", data, json_body=True)
        try: return json.loads(txt)
        except Exception: return {"ok": False, "message": txt[:120]}

    def upload_zip(self, filepath, pid):
        try:
            with open(filepath, "rb") as f: data = f.read()
        except Exception as e: return False, f"读取文件失败: {e}"
        st, txt = self.multipart("/api/upload_package.php", {"problem_id": pid},
                                 "package", os.path.basename(filepath), data)
        try: d = json.loads(txt)
        except Exception: return False, txt[:120]
        if st == 200 and d.get("ok") and d.get("path"):
            st2, txt2 = self.req("/api/import_by_path.php", {"server_path": d["path"], "problem_id": pid})
            try: d2 = json.loads(txt2)
            except Exception: return False, txt2[:120]
            return d2.get("ok", False), d2.get("message", "")
        return False, d.get("message", "上传失败")

    def submit(self, pid, lang, code):
        st, txt = self.req("/api/submit.php",
            {"problem_id": pid, "language": lang, "code": code, "time_limit": 2.0, "memory_limit": 128},
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
VC = {"AC": "#2ecc71", "WA": "#ff4f4f", "TLE": "#ffab00", "MLE": "#d500f9", "RE": "#f8603a",
      "OLE": "#0091ea", "CE": "#ff9100", "SE": "#999", "judging": "#5af", "waiting": "#999", "compiling": "#ffab00"}

class App(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title("ZXT OJ 客户端")
        self.geometry("980x720")
        self.configure(bg="#111")
        self.cfg = load_cfg()
        self.client = OJClient(self.cfg.get("server", DEFAULT_IP))
        self.problems = []
        self._build_ui()
        self.after(100, self._auto_login)

    # ---------------- UI ----------------
    def _build_ui(self):
        style = ttk.Style(self); style.theme_use("clam")
        style.configure("TFrame", background="#111")
        style.configure("TLabel", background="#111", foreground="#ccc", font=("Consolas", 10))
        style.configure("TLabelframe", background="#16161c", foreground="#999")
        style.configure("TLabelframe.Label", background="#16161c", foreground="#5af", font=("Consolas", 10))
        style.configure("TButton", background="#2a2a2a", foreground="#ddd", font=("Consolas", 10))
        style.map("TButton", background=[("active", "#3a3a3a")])
        style.configure("TNotebook", background="#111", borderwidth=0)
        style.configure("TNotebook.Tab", background="#1e1e1e", foreground="#999", padding=(14, 6), font=("Consolas", 10))
        style.map("TNotebook.Tab", background=[("selected", "#16161c")], foreground=[("selected", "#5af")])

        top = ttk.Frame(self); top.pack(fill="x", padx=8, pady=6)
        ttk.Label(top, text="服务器:").pack(side="left")
        self.e_server = ttk.Entry(top, width=22); self.e_server.pack(side="left", padx=4)
        self.e_server.insert(0, self.cfg.get("server", DEFAULT_IP))
        ttk.Label(top, text="用户:").pack(side="left", padx=(14, 2))
        self.e_user = ttk.Entry(top, width=12); self.e_user.pack(side="left")
        self.e_user.insert(0, self.cfg.get("username", ""))
        ttk.Label(top, text="密码:").pack(side="left", padx=(8, 2))
        self.e_pass = ttk.Entry(top, width=14, show="*"); self.e_pass.pack(side="left")
        self.btn_login = ttk.Button(top, text="登录", command=self.on_login); self.btn_login.pack(side="left", padx=6)
        self.btn_reload = ttk.Button(top, text="刷新题库", command=self.on_refresh_problems); self.btn_reload.pack(side="left")
        self.lb_login = ttk.Label(top, text="未登录"); self.lb_login.pack(side="left", padx=6)

        mid = ttk.Frame(self); mid.pack(fill="both", expand=True, padx=8, pady=4)
        lf = ttk.Labelframe(mid, text="题库"); lf.pack(side="left", fill="y", padx=(0, 6))
        self.listbox = tk.Listbox(lf, width=36, bg="#0d0d12", fg="#ccc", selectbackground="#1a3a5c",
                                  font=("Consolas", 10), bd=0, highlightthickness=0)
        self.listbox.pack(side="left", fill="y")
        self.listbox.bind("<<ListboxSelect>>", lambda e: self.on_select_problem())

        right = ttk.Frame(mid); right.pack(side="left", fill="both", expand=True)
        self.nb = ttk.Notebook(right); self.nb.pack(fill="both", expand=True)
        self._tab_detail(); self._tab_edit(); self._tab_upload(); self._tab_submit()

    def _tab_detail(self):
        f = ttk.Frame(self.nb); self.nb.add(f, text="题目详情")
        self.txt_detail = tk.Text(f, bg="#0d0d12", fg="#ccc", font=("Consolas", 11), bd=0, wrap="word",
                                  padx=10, pady=8, state="disabled")
        self.txt_detail.pack(fill="both", expand=True)

    def _tab_edit(self):
        f = ttk.Frame(self.nb); self.nb.add(f, text="编辑题目")
        box = ttk.Frame(f); box.pack(fill="both", expand=True, padx=8, pady=6)
        self.e_title = ttk.Entry(box); self.e_title.pack(fill="x", pady=2)
        row = ttk.Frame(box); row.pack(fill="x", pady=2)
        ttk.Label(row, text="时限(s)").pack(side="left"); self.e_tl = ttk.Entry(row, width=8); self.e_tl.pack(side="left", padx=4)
        ttk.Label(row, text="内存(MB)").pack(side="left"); self.e_ml = ttk.Entry(row, width=8); self.e_ml.pack(side="left", padx=4)
        ttk.Label(row, text="可见性").pack(side="left"); self.cb_vis = ttk.Combobox(row, values=["public", "hidden"], width=8, state="readonly"); self.cb_vis.pack(side="left", padx=4); self.cb_vis.set("public")
        self.e_bg = tk.Text(box, height=2, bg="#0d0d12", fg="#ccc", insertbackground="#ccc", font=("Consolas", 10), bd=0); self.e_bg.pack(fill="x", pady=2)
        self.e_desc = tk.Text(box, height=4, bg="#0d0d12", fg="#ccc", insertbackground="#ccc", font=("Consolas", 10), bd=0); self.e_desc.pack(fill="both", expand=True, pady=2)
        ttk.Button(box, text="保存题目", command=self.on_save_problem).pack(pady=4)
        for w, ph in [(self.e_bg, "背景 (Markdown/LaTeX)"), (self.e_desc, "题目描述")]:
            w.insert("1.0", "")

    def _tab_upload(self):
        f = ttk.Frame(self.nb); self.nb.add(f, text="上传数据包")
        box = ttk.Frame(f); box.pack(fill="x", padx=8, pady=8)
        row = ttk.Frame(box); row.pack(fill="x")
        self.e_zip = ttk.Entry(row); self.e_zip.pack(side="left", fill="x", expand=True, padx=(0, 4))
        ttk.Button(row, text="选择 zip", command=self.on_pick_zip).pack(side="left", padx=2)
        ttk.Button(row, text="上传并导入", command=self.on_upload).pack(side="left", padx=2)
        self.txt_up = tk.Text(f, bg="#0a0e14", fg="#bbb", font=("Consolas", 10), height=8, bd=0, state="disabled")
        self.txt_up.pack(fill="both", expand=True, padx=8, pady=(0, 8))

    def _tab_submit(self):
        f = ttk.Frame(self.nb); self.nb.add(f, text="提交评测")
        crow = ttk.Frame(f); crow.pack(fill="x", padx=8, pady=6)
        ttk.Label(crow, text="题目:").pack(side="left")
        self.e_pid = ttk.Entry(crow, width=10); self.e_pid.pack(side="left", padx=3)
        ttk.Label(crow, text="语言:").pack(side="left", padx=(10, 2))
        self.cb_lang = ttk.Combobox(crow, values=LANGS, width=9, state="readonly"); self.cb_lang.pack(side="left"); self.cb_lang.set("python3")
        self.btn_sub = ttk.Button(crow, text="提交评测", command=self.on_submit); self.btn_sub.pack(side="right", padx=4)
        self.txt_code = tk.Text(f, bg="#0d0d12", fg="#ccc", insertbackground="#ccc", font=("Consolas", 11), bd=0, wrap="none")
        self.txt_code.pack(fill="both", expand=True, padx=8, pady=(0, 4))
        self.txt_res = tk.Text(f, bg="#0a0e14", fg="#bbb", font=("Consolas", 10), height=8, bd=0, state="disabled")
        self.txt_res.pack(fill="both", padx=8, pady=(0, 8))

    # ---------------- 工具 ----------------
    def _log(self, w, s, color=None):
        w.config(state="normal"); w.insert("end", s + "\n")
        if color:
            w.tag_add("c", f"end-{len(s)+2}c", "end-1c"); w.tag_config("c", foreground=color)
        w.see("end"); w.config(state="disabled")

    def _set_login(self, ok, msg):
        self.lb_login.config(text=msg, foreground=("#2ecc71" if ok else "#ff6b6b"))

    def _auto_login(self):
        if self.cfg.get("ojcid"):
            host = self.cfg.get("server", DEFAULT_IP).split(":")[0]
            self.client.cj.set_cookie(http.cookiejar.Cookie(0, "OJCID", self.cfg["ojcid"], None, False,
                host, False, "/", True, False, None, True, None, None, {}))
            self._set_login(True, f"已登录 {self.cfg.get('username','')}")
            self.on_refresh_problems()

    def on_login(self):
        self.btn_login.config(state="disabled"); self._set_login(False, "登录中...")
        def work():
            server = self.e_server.get().strip(); self.cfg["server"] = server; save_cfg(self.cfg)
            self.client = OJClient(server)
            ok, msg = self.client.login(self.e_user.get().strip(), self.e_pass.get())
            self.after(0, lambda: self._login_done(ok, msg))
        threading.Thread(target=work, daemon=True).start()

    def _login_done(self, ok, msg):
        self.btn_login.config(state="normal")
        if ok:
            self.cfg["ojcid"] = msg; self.cfg["username"] = self.e_user.get().strip(); save_cfg(self.cfg)
            self._set_login(True, f"已登录 {self.cfg['username']}")
            self.on_refresh_problems()
        else:
            self._set_login(False, f"登录失败: {msg}")

    def on_refresh_problems(self):
        def work():
            ps = self.client.problems()
            self.after(0, lambda: self._fill_problems(ps))
        threading.Thread(target=work, daemon=True).start()

    def _fill_problems(self, ps):
        if ps is None:
            self._set_login(False, "题库加载失败（请检查服务器/OJCID）"); return
        self.problems = ps
        self.listbox.delete(0, "end")
        for p in ps:
            rate = f"{p['ac']}/{p['submissions']}" if p['submissions'] else "-"
            self.listbox.insert("end", f"  {p['problem_id']:<12} {p['title'][:18]:<18} {rate} AC")
        self._set_login(True, f"已登录 {self.cfg.get('username','')}（{len(ps)} 题）")

    def _cur_pid(self):
        sel = self.listbox.curselection()
        if sel:
            p = self.problems[sel[0]]
            return p["problem_id"], p
        return self.e_pid.get().strip(), None

    def on_select_problem(self):
        pid, p = self._cur_pid()
        if not pid: return
        self.e_pid.delete(0, "end"); self.e_pid.insert(0, pid)
        if p: self._load_detail(pid)

    def _load_detail(self, pid):
        def work():
            d = self.client.problem(pid)
            self.after(0, lambda: self._show_detail(d))
        threading.Thread(target=work, daemon=True).start()

    def _show_detail(self, d):
        if not d or not d.get("ok"):
            self._log(self.txt_detail, f"[错误] {d.get('message','') if d else '加载失败'}", "#ff6b6b"); return
        self.txt_detail.config(state="normal"); self.txt_detail.delete("1.0", "end")
        s = f"【{d['problem_id']}】{d['title']}\n限制: {d['time_limit']}s / {d['memory_limit']}MB  可见性: {d['visibility']}\n"
        for k, lb in [("background", "背景"), ("description", "描述"), ("input_format", "输入格式"),
                      ("output_format", "输出格式"), ("hints", "提示")]:
            if d.get(k): s += f"\n===== {lb} =====\n{d[k]}\n"
        if d.get("samples"):
            s += "\n===== 样例 =====\n"
            for sm in d["samples"]:
                s += f"样例{sm['sort_order']}:\n输入:\n{sm['input_text']}\n输出:\n{sm['output_text']}\n"
        self.txt_detail.insert("1.0", s); self.txt_detail.config(state="disabled")

    def on_save_problem(self):
        pid, p = self._cur_pid()
        if not pid: messagebox.showwarning("提示", "请先在题库选择题目（或输入编号）"); return
        data = {"problem_id": pid, "title": self.e_title.get().strip(),
                "background": self.e_bg.get("1.0", "end").strip(), "description": self.e_desc.get("1.0", "end").strip(),
                "input_format": "", "output_format": "", "hints": "",
                "time_limit": float(self.e_tl.get() or 2.0), "memory_limit": int(self.e_ml.get() or 128),
                "visibility": self.cb_vis.get()}
        def work():
            d = self.client.save_problem(data)
            self.after(0, lambda: (messagebox.showinfo("保存", d.get("message", "已保存") if d.get("ok") else d.get("message", "失败")),
                                   self.on_refresh_problems() if d.get("ok") else None))
        threading.Thread(target=work, daemon=True).start()

    def on_pick_zip(self):
        f = filedialog.askopenfilename(filetypes=[("数据包", "*.zip *.tar.gz *.tgz *.tar")])
        if f: self.e_zip.delete(0, "end"); self.e_zip.insert(0, f)

    def on_upload(self):
        zipf = self.e_zip.get().strip(); pid, _ = self._cur_pid()
        if not zipf: messagebox.showwarning("提示", "请选择 zip 数据包"); return
        if not pid: messagebox.showwarning("提示", "请选择题目"); return
        self._log(self.txt_up, f"[上传] {os.path.basename(zipf)} -> {pid}", "#5af")
        def work():
            ok, msg = self.client.upload_zip(zipf, pid)
            self.after(0, lambda: self._log(self.txt_up, ("✅ " + msg) if ok else ("❌ " + msg),
                                            "#2ecc71" if ok else "#ff6b6b"))
        threading.Thread(target=work, daemon=True).start()

    def on_submit(self):
        pid = self.e_pid.get().strip(); code = self.txt_code.get("1.0", "end").strip()
        if not pid: messagebox.showwarning("提示", "请输入题目编号"); return
        if not code: messagebox.showwarning("提示", "请输入代码"); return
        self.btn_sub.config(state="disabled")
        self._log(self.txt_res, f"[提交] {pid} {self.cb_lang.get()}", "#5af")
        def work():
            sid = self.client.submit(pid, self.cb_lang.get(), code)
            if not sid:
                self.after(0, lambda: (self._log(self.txt_res, "提交失败（检查登录/题目）", "#ff6b6b"),
                                       self.btn_sub.config(state="normal"))); return
            self.after(0, lambda: self._log(self.txt_res, f"[队列] 提交 #{sid}，等待评测...", "#ffab00"))
            for _ in range(120):
                time.sleep(2)
                s = self.client.status(sid)
                if not s: continue
                st = s.get("status", "")
                if st in ("waiting", "judging", "compiling"):
                    continue
                self.after(0, lambda s=s: self._show_result(s)); break
            self.after(0, lambda: self.btn_sub.config(state="normal"))
        threading.Thread(target=work, daemon=True).start()

    def _show_result(self, s):
        st = s.get("status", "?"); score = s.get("score", 0); mx = s.get("max_score", 0)
        color = VC.get(st, "#ccc")
        self._log(self.txt_res, "========================", "#666")
        self._log(self.txt_res, f"状态: {st}  得分: {score}/{mx}  通过: {s.get('passed_tests',0)}/{s.get('total_tests',0)}", color)
        self._log(self.txt_res, f"总耗时: {s.get('total_time','-')}s  峰值内存: {s.get('peak_memory','-')}MB", "#aaa")
        try:
            det = json.loads(s.get("details") or "[]")
            rows = []
            for r in det:
                if not r: continue
                rows.append(f"  #{r.get('test_case_index',0)+1:<3} {r.get('verdict','?'):<5} "
                            f"{r.get('time_used',0):<8.3f}s {r.get('memory_used',0):<7.2f}MB"
                            + (f"  {r.get('error','')[:60]}" if r.get('error') else ""))
            if rows: self._log(self.txt_res, "\n".join(rows[:25]), "#bbb")
        except Exception: pass
        self._log(self.txt_res, "========================", "#666")

if __name__ == "__main__":
    App().mainloop()
