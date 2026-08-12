# -*- coding: utf-8 -*-
"""
ZXT OJ Windows 客户端（现代版）
功能：登录(OJCID) / 题库 / 查看题目 / 编辑题目 / 上传数据包 / 提交评测
风格：无边框窗口 + 自定义标题栏 + Win11 圆角 + 深色现代排版
依赖：Python 3.8+（标准库）   打包：pyinstaller -F -w oj_client.py
"""
import tkinter as tk
from tkinter import ttk, messagebox, filedialog
import json, os, threading, time, uuid, sys, urllib.request, urllib.parse, http.cookiejar

CFG_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), "oj_client.json")
DEFAULT_IP = "156.239.236.66:18001"

# 现代配色
C_BG      = "#0e0e12"   # 窗口背景
C_PANEL   = "#16161d"   # 面板
C_PANEL2  = "#1b1b23"   # 面板高亮
C_BORDER  = "#26262f"   # 边框
C_TEXT    = "#d5d5de"   # 主文字
C_DIM     = "#8a8a96"   # 次要文字
C_ACC     = "#4d9fff"   # 强调蓝
C_OK      = "#2ecc71"   # 成功绿
C_WARN    = "#ffab00"
C_ERR     = "#ff5f56"
FONT      = ("Segoe UI", 10)
FONT_B    = ("Segoe UI", 10, "bold")
FONT_S    = ("Segoe UI", 9)

def enable_round_corners(root):
    """Windows 11 原生窗口圆角（无边框下仍生效），失败自动忽略"""
    try:
        import ctypes
        hwnd = root.winfo_id()
        DWMWA_WINDOW_CORNER_PREFERENCE = 33
        DWMWCP_ROUND = 2
        ctypes.windll.dwmapi.DwmSetWindowAttribute(hwnd, DWMWA_WINDOW_CORNER_PREFERENCE,
            ctypes.byref(ctypes.c_int(DWMWCP_ROUND)), ctypes.sizeof(ctypes.c_int))
    except Exception:
        pass

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
        r = urllib.request.Request(self.url(path), data=b"".join(parts),
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
VC = {"AC": C_OK, "WA": C_ERR, "TLE": C_WARN, "MLE": "#d500f9", "RE": "#f8603a",
      "OLE": "#0091ea", "CE": "#ff9100", "SE": "#999", "judging": C_ACC, "waiting": "#999", "compiling": C_WARN}

class App(tk.Tk):
    def __init__(self):
        super().__init__()
        self.overrideredirect(True)                      # 无系统边框
        self.configure(bg=C_BG)
        self.geometry("1000x720")
        self.minsize(860, 600)
        self._offset = (0, 0)
        self.cfg = load_cfg()
        self.client = OJClient(self.cfg.get("server", DEFAULT_IP))
        self.problems = []
        self._build_ui()
        self.after(60, lambda: enable_round_corners(self))   # Win11 圆角
        self.after(120, self._auto_login)

    # ---------------- 现代控件辅助 ----------------
    def _btn(self, parent, text, cmd, fg=C_TEXT, bg=C_PANEL2, hover="#24242e"):
        """扁平圆角感按钮（hover 变色）"""
        b = tk.Button(parent, text=text, command=cmd, font=FONT, fg=fg, bg=bg, bd=0,
                      activebackground=hover, activeforeground="#fff", cursor="hand2",
                      padx=14, pady=5, relief="flat")
        b.bind("<Enter>", lambda e, w=b: w.config(bg=hover))
        b.bind("<Leave>", lambda e, w=b, c=bg: w.config(bg=c))
        return b

    def _entry(self, parent, width=None, show=None, ph=""):
        e = tk.Entry(parent, width=width, show=show, font=FONT, fg=C_TEXT, bg="#101016",
                     insertbackground=C_TEXT, relief="flat", highlightthickness=1,
                     highlightbackground=C_BORDER, highlightcolor=C_ACC)
        return e

    def _text(self, parent, h=4, wrap="word"):
        t = tk.Text(parent, bg="#101016", fg=C_TEXT, insertbackground=C_TEXT, font=FONT,
                    bd=0, wrap=wrap, highlightthickness=1, highlightbackground=C_BORDER,
                    highlightcolor=C_ACC, padx=10, pady=8)
        return t

    # ---------------- 布局 ----------------
    def _build_ui(self):
        # 自定义标题栏（可拖动）
        bar = tk.Frame(self, bg="#121218", height=38)
        bar.pack(fill="x"); bar.pack_propagate(False)
        logo = tk.Label(bar, text="◈  ZXT OJ", bg="#121218", fg="#fff", font=("Segoe UI", 11, "bold"))
        logo.pack(side="left", padx=14)
        self.lb_status = tk.Label(bar, text="未登录", bg="#121218", fg=C_DIM, font=FONT_S)
        self.lb_status.pack(side="left", padx=12)
        for txt, cmd in [("─", self.iconify), ("✕", self.destroy)]:
            b = tk.Label(bar, text=txt, bg="#121218", fg=C_DIM, font=("Segoe UI", 13),
                         padx=10, cursor="hand2")
            b.pack(side="right")
            b.bind("<Button-1>", lambda e, c=cmd: c())
            b.bind("<Enter>", lambda e, w=b: w.config(fg="#fff"))
            b.bind("<Leave>", lambda e, w=b: w.config(fg=C_DIM))
        bar.bind("<Button-1>", self._start_move)
        bar.bind("<B1-Motion>", self._on_move)
        logo.bind("<Button-1>", self._start_move)
        logo.bind("<B1-Motion>", self._on_move)

        # 登录工具条
        top = tk.Frame(self, bg=C_BG)
        top.pack(fill="x", padx=16, pady=(12, 8))
        self.e_server = self._entry(top, 24); self.e_server.pack(side="left")
        self.e_server.insert(0, self.cfg.get("server", DEFAULT_IP))
        self.e_user = self._entry(top, 12); self.e_user.pack(side="left", padx=(10, 0))
        self.e_user.insert(0, self.cfg.get("username", ""))
        self.e_pass = self._entry(top, 14, show="*"); self.e_pass.pack(side="left", padx=(10, 0))
        self.btn_login = self._btn(top, "登录", self.on_login, fg="#fff", bg="#1a3a5c", hover="#1f4a75")
        self.btn_login.pack(side="left", padx=(10, 6))
        self.btn_reload = self._btn(top, "↻ 刷新题库", self.on_refresh_problems)
        self.btn_reload.pack(side="left")

        # 主区
        mid = tk.Frame(self, bg=C_BG)
        mid.pack(fill="both", expand=True, padx=16, pady=(4, 12))

        # 左：题库（卡片式）
        side = tk.Frame(mid, bg=C_PANEL, highlightbackground=C_BORDER, highlightthickness=1)
        side.pack(side="left", fill="y")
        tk.Label(side, text="题库", bg=C_PANEL, fg=C_DIM, font=FONT_B, padx=14, pady=8).pack(anchor="w")
        self.listbox = tk.Listbox(side, width=38, bg=C_PANEL2, fg=C_TEXT, selectbackground="#1a3a5c",
                                  selectforeground="#fff", font=FONT, bd=0, highlightthickness=0,
                                  activestyle="none", relief="flat")
        self.listbox.pack(side="left", fill="y")
        self.listbox.bind("<<ListboxSelect>>", lambda e: self.on_select_problem())

        # 右：内容 Notebook
        right = tk.Frame(mid, bg=C_BG)
        right.pack(side="left", fill="both", expand=True, padx=(12, 0))
        self.nb = ttk.Notebook(right)
        self.nb.pack(fill="both", expand=True)
        self._tab_detail(); self._tab_edit(); self._tab_upload(); self._tab_submit()

        # 底部状态栏
        status = tk.Frame(self, bg="#121218", height=26)
        status.pack(fill="x", side="bottom"); status.pack_propagate(False)
        tk.Label(status, text="ZXT OJ 客户端 · OJCID 一周免登录 · 指令计数评测",
                 bg="#121218", fg="#555", font=FONT_S).pack(side="left", padx=12)

    def _tab_detail(self):
        f = tk.Frame(self.nb, bg=C_PANEL)
        self.nb.add(f, text="  题目详情  ")
        self.txt_detail = self._text(f, wrap="word")
        self.txt_detail.pack(fill="both", expand=True, padx=10, pady=10)
        self.txt_detail.config(state="disabled")

    def _tab_edit(self):
        f = tk.Frame(self.nb, bg=C_PANEL)
        self.nb.add(f, text="  编辑题目  ")
        box = tk.Frame(f, bg=C_PANEL); box.pack(fill="both", expand=True, padx=14, pady=10)
        self.e_title = self._entry(box); self.e_title.pack(fill="x")
        row = tk.Frame(box, bg=C_PANEL); row.pack(fill="x", pady=(8, 4))
        for lb, w, key in [("时限(s)", 8, "e_tl"), ("内存(MB)", 8, "e_ml")]:
            tk.Label(row, text=lb, bg=C_PANEL, fg=C_DIM, font=FONT_S).pack(side="left")
            setattr(self, key, self._entry(row, w)); getattr(self, key).pack(side="left", padx=(4, 12))
        tk.Label(row, text="可见性", bg=C_PANEL, fg=C_DIM, font=FONT_S).pack(side="left")
        self.cb_vis = ttk.Combobox(row, values=["public", "hidden"], width=8, state="readonly",
                                   font=FONT, foreground=C_TEXT)
        self.cb_vis.pack(side="left", padx=4); self.cb_vis.set("public")
        tk.Label(box, text="背景（Markdown / LaTeX）", bg=C_PANEL, fg=C_DIM, font=FONT_S).pack(anchor="w", pady=(6, 2))
        self.e_bg = self._text(box, 2); self.e_bg.pack(fill="x")
        tk.Label(box, text="题目描述", bg=C_PANEL, fg=C_DIM, font=FONT_S).pack(anchor="w", pady=(6, 2))
        self.e_desc = self._text(box, 5); self.e_desc.pack(fill="both", expand=True)
        self._btn(box, "保存题目", self.on_save_problem, fg="#fff", bg="#1a3a5c", hover="#1f4a75").pack(pady=8)

    def _tab_upload(self):
        f = tk.Frame(self.nb, bg=C_PANEL)
        self.nb.add(f, text="  上传数据包  ")
        box = tk.Frame(f, bg=C_PANEL); box.pack(fill="x", padx=14, pady=12)
        row = tk.Frame(box, bg=C_PANEL); row.pack(fill="x")
        self.e_zip = self._entry(row); self.e_zip.pack(side="left", fill="x", expand=True, padx=(0, 6))
        self._btn(row, "选择 zip", self.on_pick_zip).pack(side="left", padx=2)
        self._btn(row, "上传并导入", self.on_upload, fg="#fff", bg="#1a3a5c", hover="#1f4a75").pack(side="left", padx=2)
        tk.Label(f, text="支持 zip / tar.gz，自动补全 config.yaml 与 checker",
                 bg=C_PANEL, fg=C_DIM, font=FONT_S).pack(anchor="w", padx=14, pady=(0, 4))
        self.txt_up = self._text(f, 6, wrap="word"); self.txt_up.pack(fill="both", expand=True, padx=14, pady=(0, 12))
        self.txt_up.config(state="disabled")

    def _tab_submit(self):
        f = tk.Frame(self.nb, bg=C_PANEL)
        self.nb.add(f, text="  提交评测  ")
        row = tk.Frame(f, bg=C_PANEL); row.pack(fill="x", padx=14, pady=10)
        tk.Label(row, text="题目", bg=C_PANEL, fg=C_DIM, font=FONT_S).pack(side="left")
        self.e_pid = self._entry(row, 10); self.e_pid.pack(side="left", padx=4)
        tk.Label(row, text="语言", bg=C_PANEL, fg=C_DIM, font=FONT_S).pack(side="left", padx=(12, 2))
        self.cb_lang = ttk.Combobox(row, values=LANGS, width=9, state="readonly", font=FONT, foreground=C_TEXT)
        self.cb_lang.pack(side="left"); self.cb_lang.set("python3")
        self.btn_sub = self._btn(row, "提交评测", self.on_submit, fg="#fff", bg="#1a3a5c", hover="#1f4a75")
        self.btn_sub.pack(side="right")
        self.txt_code = self._text(f, wrap="none"); self.txt_code.pack(fill="both", expand=True, padx=14, pady=(0, 8))
        self.txt_res = self._text(f, 7, wrap="word"); self.txt_res.pack(fill="both", padx=14, pady=(0, 12))
        self.txt_res.config(state="disabled")

    # ---------------- 窗口拖动 / 状态 ----------------
    def _start_move(self, e): self._offset = (e.x, e.y)
    def _on_move(self, e):
        x = self.winfo_x() + e.x - self._offset[0]
        y = self.winfo_y() + e.y - self._offset[1]
        self.geometry(f"+{x}+{y}")

    def _log(self, w, s, color=None):
        w.config(state="normal"); w.insert("end", s + "\n")
        if color:
            w.tag_add("c", f"end-{len(s)+2}c", "end-1c"); w.tag_config("c", foreground=color)
        w.see("end"); w.config(state="disabled")

    def _set_login(self, ok, msg):
        self.lb_status.config(text=msg, fg=(C_OK if ok else C_ERR))

    def _auto_login(self):
        if self.cfg.get("ojcid"):
            host = self.cfg.get("server", DEFAULT_IP).split(":")[0]
            self.client.cj.set_cookie(http.cookiejar.Cookie(0, "OJCID", self.cfg["ojcid"], None, False,
                host, False, "/", True, False, None, True, None, None, {}))
            self._set_login(True, f"已登录 {self.cfg.get('username','')}")
            self.on_refresh_problems()

    # ---------------- 功能 ----------------
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
            self._set_login(False, "题库加载失败"); return
        self.problems = ps
        self.listbox.delete(0, "end")
        for p in ps:
            rate = f"{p['ac']}/{p['submissions']}" if p['submissions'] else "-"
            self.listbox.insert("end", f"  {p['problem_id']:<12} {p['title'][:18]:<18} {rate} AC")
        self._set_login(True, f"已登录 {self.cfg.get('username','')} · {len(ps)} 题")

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
            self._log(self.txt_detail, f"[错误] {d.get('message','') if d else '加载失败'}", C_ERR); return
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
        self._log(self.txt_up, f"[上传] {os.path.basename(zipf)} -> {pid}", C_ACC)
        def work():
            ok, msg = self.client.upload_zip(zipf, pid)
            self.after(0, lambda: self._log(self.txt_up, ("✓ " + msg) if ok else ("✗ " + msg),
                                            C_OK if ok else C_ERR))
        threading.Thread(target=work, daemon=True).start()

    def on_submit(self):
        pid = self.e_pid.get().strip(); code = self.txt_code.get("1.0", "end").strip()
        if not pid: messagebox.showwarning("提示", "请输入题目编号"); return
        if not code: messagebox.showwarning("提示", "请输入代码"); return
        self.btn_sub.config(state="disabled")
        self._log(self.txt_res, f"[提交] {pid} {self.cb_lang.get()}", C_ACC)
        def work():
            sid = self.client.submit(pid, self.cb_lang.get(), code)
            if not sid:
                self.after(0, lambda: (self._log(self.txt_res, "提交失败（检查登录/题目）", C_ERR),
                                       self.btn_sub.config(state="normal"))); return
            self.after(0, lambda: self._log(self.txt_res, f"[队列] 提交 #{sid}，等待评测...", C_WARN))
            for _ in range(120):
                time.sleep(2)
                s = self.client.status(sid)
                if not s: continue
                if s.get("status") in ("waiting", "judging", "compiling"): continue
                self.after(0, lambda s=s: self._show_result(s)); break
            self.after(0, lambda: self.btn_sub.config(state="normal"))
        threading.Thread(target=work, daemon=True).start()

    def _show_result(self, s):
        st = s.get("status", "?"); score = s.get("score", 0); mx = s.get("max_score", 0)
        color = VC.get(st, "#ccc")
        self._log(self.txt_res, "─" * 40, "#555")
        self._log(self.txt_res, f"状态: {st}    得分: {score}/{mx}    通过: {s.get('passed_tests',0)}/{s.get('total_tests',0)}", color)
        self._log(self.txt_res, f"总耗时: {s.get('total_time','-')}s  峰值内存: {s.get('peak_memory','-')}MB", C_DIM)
        try:
            det = json.loads(s.get("details") or "[]")
            rows = []
            for r in det:
                if not r: continue
                rows.append(f"  #{r.get('test_case_index',0)+1:<3} {r.get('verdict','?'):<5} "
                            f"{r.get('time_used',0):<8.3f}s {r.get('memory_used',0):<7.2f}MB"
                            + (f"  {r.get('error','')[:60]}" if r.get('error') else ""))
            if rows: self._log(self.txt_res, "\n".join(rows[:25]), C_TEXT)
        except Exception: pass
        self._log(self.txt_res, "─" * 40, "#555")

if __name__ == "__main__":
    App().mainloop()
