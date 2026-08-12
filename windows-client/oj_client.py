# -*- coding: utf-8 -*-
"""
ZXT OJ Windows 客户端（左侧 Tab 栏导航）
栏目：题库 / 题目详情 / 编辑题目 / 上传数据包 / 提交评测 / 登录 / 注册 / 登出
无边框 + 自定义标题栏 + Win11 圆角 + 深色现代
依赖：Python 3.8+（标准库）   打包：pyinstaller -F -w oj_client.py
"""
import tkinter as tk
from tkinter import ttk, messagebox, filedialog
import json, os, threading, time, uuid, urllib.request, urllib.parse, http.cookiejar

CFG_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), "oj_client.json")
DEFAULT_IP = "156.239.236.66:18001"

C_BG, C_PANEL, C_PANEL2 = "#0e0e12", "#16161d", "#1b1b23"
C_BORDER, C_TEXT, C_DIM = "#26262f", "#d5d5de", "#8a8a96"
C_ACC, C_OK, C_WARN, C_ERR = "#4d9fff", "#2ecc71", "#ffab00", "#ff5f56"
FONT, FONT_B, FONT_S = ("Segoe UI", 10), ("Segoe UI", 10, "bold"), ("Segoe UI", 9)

def enable_round_corners(root):
    try:
        import ctypes
        hwnd = root.winfo_id()
        ctypes.windll.dwmapi.DwmSetWindowAttribute(hwnd, 33, ctypes.byref(ctypes.c_int(2)), 4)
    except Exception: pass

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
    def url(self, p): return f"http://{self.server}{p}"
    def req(self, path, data=None, json_body=False, timeout=30):
        headers = {"User-Agent": "Mozilla/5.0 (Windows NT 10.0)"}
        body = None
        if data is not None:
            if json_body:
                headers["Content-Type"] = "application/json"; body = json.dumps(data).encode()
            else:
                headers["Content-Type"] = "application/x-www-form-urlencoded"
                body = urllib.parse.urlencode(data).encode()
        r = urllib.request.Request(self.url(path), data=body, headers=headers)
        try:
            resp = self.opener.open(r, timeout=timeout); return resp.status, resp.read().decode("utf-8", errors="replace")
        except urllib.error.HTTPError as e: return e.code, e.read().decode("utf-8", errors="replace")
        except Exception as e: return -1, str(e)
    def multipart(self, path, fields, ff, fn, fb):
        bd = "----ZXTOJ" + uuid.uuid4().hex
        parts = [f'--{bd}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode() for k, v in fields.items()]
        parts.append(f'--{bd}\r\nContent-Disposition: form-data; name="{ff}"; filename="{fn}"\r\nContent-Type: application/zip\r\n\r\n'.encode())
        parts += [fb, f'\r\n--{bd}--\r\n'.encode()]
        r = urllib.request.Request(self.url(path), data=b"".join(parts),
            headers={"Content-Type": f"multipart/form-data; boundary={bd}", "User-Agent": "Mozilla/5.0"})
        try:
            resp = self.opener.open(r, timeout=120); return resp.status, resp.read().decode("utf-8", errors="replace")
        except urllib.error.HTTPError as e: return e.code, e.read().decode("utf-8", errors="replace")
        except Exception as e: return -1, str(e)
    def login(self, u, p):
        st, txt = self.req("/login.php", {"username": u, "password": p})
        cid = next((c.value for c in self.cj if c.name == "OJCID"), None)
        return (True, cid) if cid else (False, f"HTTP {st}" if st > 0 else txt)
    def register(self, inv, u, p, cf):
        st, txt = self.req("/register.php", {"invite": inv, "username": u, "password": p, "confirm": cf})
        if "注册成功" in txt or "注册成功" in txt.replace("&#x",""):
            return True, "注册成功"
        if "邀请码" in txt and "错误" in txt: return False, "邀请码错误"
        return False, (f"HTTP {st}" if st > 0 else "注册失败")
    def logout(self):
        try: self.req("/logout.php")
        except Exception: pass
    def problems(self):
        st, txt = self.req("/api/problems_json.php")
        return json.loads(txt) if st == 200 else None
    def problem(self, pid):
        st, txt = self.req(f"/api/problem_json.php?id={pid}")
        return json.loads(txt) if st == 200 else None
    def save_problem(self, d):
        st, txt = self.req("/api/problem_save.php", d, json_body=True)
        try: return json.loads(txt)
        except Exception: return {"ok": False, "message": txt[:120]}
    def upload_zip(self, fp, pid):
        try:
            with open(fp, "rb") as f: data = f.read()
        except Exception as e: return False, f"读取失败: {e}"
        st, txt = self.multipart("/api/upload_package.php", {"problem_id": pid}, "package", os.path.basename(fp), data)
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
            {"problem_id": pid, "language": lang, "code": code, "time_limit": 2.0, "memory_limit": 128}, json_body=True)
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
NAV = [("problems", "题库", "▦"), ("detail", "题目详情", "▤"), ("edit", "编辑题目", "✎"),
       ("upload", "上传数据包", "⇪"), ("submit", "提交评测", "▶")]

class App(tk.Tk):
    def __init__(self):
        super().__init__()
        self.overrideredirect(True)
        self.configure(bg=C_BG)
        self.geometry("1040x720")
        self.minsize(880, 600)
        self._off = (0, 0)
        self.cfg = load_cfg()
        self.client = OJClient(self.cfg.get("server", DEFAULT_IP))
        self.problems = []
        self.logged = bool(self.cfg.get("ojcid"))
        self._build_ui()
        self.after(60, lambda: enable_round_corners(self))
        self.after(120, self._auto_login)

    # ---------- 控件 ----------
    def _btn(self, p, t, cmd, fg=C_TEXT, bg=C_PANEL2, hov="#24242e"):
        b = tk.Button(p, text=t, command=cmd, font=FONT, fg=fg, bg=bg, bd=0,
                      activebackground=hov, activeforeground="#fff", cursor="hand2",
                      padx=14, pady=5, relief="flat")
        b.bind("<Enter>", lambda e, w=b: w.config(bg=hov))
        b.bind("<Leave>", lambda e, w=b, c=bg: w.config(bg=c))
        return b
    def _entry(self, p, w=None, show=None):
        return tk.Entry(p, width=w, show=show, font=FONT, fg=C_TEXT, bg="#101016",
                        insertbackground=C_TEXT, relief="flat", highlightthickness=1,
                        highlightbackground=C_BORDER, highlightcolor=C_ACC)
    def _text(self, p, h=4, wrap="word"):
        return tk.Text(p, bg="#101016", fg=C_TEXT, insertbackground=C_TEXT, font=FONT,
                       bd=0, wrap=wrap, highlightthickness=1, highlightbackground=C_BORDER,
                       highlightcolor=C_ACC, padx=10, pady=8)

    # ---------- 布局 ----------
    def _build_ui(self):
        bar = tk.Frame(self, bg="#121218", height=38); bar.pack(fill="x"); bar.pack_propagate(False)
        tk.Label(bar, text="◈ ZXT OJ", bg="#121218", fg="#fff", font=("Segoe UI", 11, "bold")).pack(side="left", padx=14)
        self.lb_status = tk.Label(bar, text="未登录", bg="#121218", fg=C_DIM, font=FONT_S); self.lb_status.pack(side="left", padx=12)
        for txt, cmd in [("─", self.iconify), ("✕", self.destroy)]:
            lb = tk.Label(bar, text=txt, bg="#121218", fg=C_DIM, font=("Segoe UI", 13), padx=10, cursor="hand2")
            lb.pack(side="right")
            lb.bind("<Button-1>", lambda e, c=cmd: c())
        bar.bind("<Button-1>", self._sm); bar.bind("<B1-Motion>", self._mm)
        for w in bar.winfo_children(): w.bind("<Button-1>", self._sm); w.bind("<B1-Motion>", self._mm)

        # 服务器条
        srv = tk.Frame(self, bg=C_BG); srv.pack(fill="x", padx=14, pady=(10, 8))
        self.e_server = self._entry(srv, 24); self.e_server.pack(side="left")
        self.e_server.insert(0, self.cfg.get("server", DEFAULT_IP))

        main = tk.Frame(self, bg=C_BG); main.pack(fill="both", expand=True, padx=14, pady=(0, 10))

        # 左侧 Tab 栏
        nav = tk.Frame(main, bg=C_PANEL, width=176, highlightbackground=C_BORDER, highlightthickness=1)
        nav.pack(side="left", fill="y"); nav.pack_propagate(False)
        tk.Label(nav, text="导航", bg=C_PANEL, fg=C_DIM, font=FONT_S, padx=16, pady=8).pack(anchor="w")
        self.nav_btns = {}
        for key, txt, icon in NAV:
            b = tk.Label(nav, text=f"  {icon}  {txt}", bg=C_PANEL, fg=C_DIM, font=FONT,
                         anchor="w", padx=14, pady=9, cursor="hand2")
            b.pack(fill="x")
            b.bind("<Button-1>", lambda e, k=key: self.show_panel(k))
            b.bind("<Enter>", lambda e, w=b: w.config(bg=C_PANEL2) if w["bg"] != "#1a2a3a" else None)
            b.bind("<Leave>", lambda e, w=b: w.config(bg=C_PANEL) if w["bg"] != "#1a2a3a" else None)
            self.nav_btns[key] = b
        tk.Frame(nav, bg=C_BORDER, height=1).pack(fill="x", pady=8)
        self.nav_login = tk.Label(nav, text="  🔑 登录", bg=C_PANEL, fg=C_DIM, font=FONT, anchor="w", padx=14, pady=9, cursor="hand2")
        self.nav_reg = tk.Label(nav, text="  📝 注册", bg=C_PANEL, fg=C_DIM, font=FONT, anchor="w", padx=14, pady=9, cursor="hand2")
        self.nav_logout = tk.Label(nav, text="  🚪 登出", bg=C_PANEL, fg=C_ERR, font=FONT, anchor="w", padx=14, pady=9, cursor="hand2")
        self.nav_login.bind("<Button-1>", lambda e: self.show_panel("login"))
        self.nav_reg.bind("<Button-1>", lambda e: self.show_panel("register"))
        self.nav_logout.bind("<Button-1>", lambda e: self.on_logout())

        # 右侧内容区（面板切换）
        self.content = tk.Frame(main, bg=C_BG)
        self.content.pack(side="left", fill="both", expand=True, padx=(12, 0))
        self.panels = {}
        self._p_problems(); self._p_detail(); self._p_edit(); self._p_upload(); self._p_submit(); self._p_login(); self._p_register()

        st = tk.Frame(self, bg="#121218", height=26); st.pack(fill="x", side="bottom"); st.pack_propagate(False)
        tk.Label(st, text="ZXT OJ 客户端 · 左侧导航 · OJCID 一周免登录",
                 bg="#121218", fg="#555", font=FONT_S).pack(side="left", padx=12)

        self._refresh_nav()

    def _panel(self, key):
        p = tk.Frame(self.content, bg=C_BG)
        self.panels[key] = p
        return p

    # ---------- 面板：题库 ----------
    def _p_problems(self):
        p = self._panel("problems")
        self.listbox = tk.Listbox(p, bg="#101016", fg=C_TEXT, selectbackground="#1a3a5c",
                                  selectforeground="#fff", font=FONT, bd=0, highlightthickness=0,
                                  activestyle="none", relief="flat")
        self.listbox.pack(fill="both", expand=True)
        self.listbox.bind("<Double-Button-1>", lambda e: self.show_panel("detail"))
        self.listbox.bind("<<ListboxSelect>>", lambda e: self._on_pick_problem())

    # ---------- 面板：题目详情 ----------
    def _p_detail(self):
        p = self._panel("detail")
        self.txt_detail = self._text(p, wrap="word")
        self.txt_detail.pack(fill="both", expand=True, padx=12, pady=10)
        self.txt_detail.config(state="disabled")

    # ---------- 面板：编辑题目 ----------
    def _p_edit(self):
        p = self._panel("edit")
        box = tk.Frame(p, bg=C_BG); box.pack(fill="both", expand=True, padx=14, pady=10)
        self.e_title = self._entry(box); self.e_title.pack(fill="x")
        row = tk.Frame(box, bg=C_BG); row.pack(fill="x", pady=(8, 4))
        for lb, w, k in [("时限(s)", 8, "e_tl"), ("内存(MB)", 8, "e_ml")]:
            tk.Label(row, text=lb, bg=C_BG, fg=C_DIM, font=FONT_S).pack(side="left")
            setattr(self, k, self._entry(row, w)); getattr(self, k).pack(side="left", padx=(4, 12))
        tk.Label(row, text="可见性", bg=C_BG, fg=C_DIM, font=FONT_S).pack(side="left")
        self.cb_vis = ttk.Combobox(row, values=["public", "hidden"], width=8, state="readonly", font=FONT, foreground=C_TEXT)
        self.cb_vis.pack(side="left", padx=4); self.cb_vis.set("public")
        tk.Label(box, text="背景（Markdown / LaTeX）", bg=C_BG, fg=C_DIM, font=FONT_S).pack(anchor="w", pady=(6, 2))
        self.e_bg = self._text(box, 2); self.e_bg.pack(fill="x")
        tk.Label(box, text="题目描述", bg=C_BG, fg=C_DIM, font=FONT_S).pack(anchor="w", pady=(6, 2))
        self.e_desc = self._text(box, 5); self.e_desc.pack(fill="both", expand=True)
        self._btn(box, "保存题目", self.on_save_problem, fg="#fff", bg="#1a3a5c", hov="#1f4a75").pack(pady=8)

    # ---------- 面板：上传 ----------
    def _p_upload(self):
        p = self._panel("upload")
        box = tk.Frame(p, bg=C_BG); box.pack(fill="x", padx=14, pady=12)
        row = tk.Frame(box, bg=C_BG); row.pack(fill="x")
        self.e_zip = self._entry(row); self.e_zip.pack(side="left", fill="x", expand=True, padx=(0, 6))
        self._btn(row, "选择 zip", self.on_pick_zip).pack(side="left", padx=2)
        self._btn(row, "上传并导入", self.on_upload, fg="#fff", bg="#1a3a5c", hov="#1f4a75").pack(side="left", padx=2)
        tk.Label(p, text="支持 zip / tar.gz，自动补全 config.yaml 与 checker",
                 bg=C_BG, fg=C_DIM, font=FONT_S).pack(anchor="w", padx=14, pady=(0, 4))
        self.txt_up = self._text(p, 6); self.txt_up.pack(fill="both", expand=True, padx=14, pady=(0, 12))
        self.txt_up.config(state="disabled")

    # ---------- 面板：提交 ----------
    def _p_submit(self):
        p = self._panel("submit")
        row = tk.Frame(p, bg=C_BG); row.pack(fill="x", padx=14, pady=10)
        tk.Label(row, text="题目", bg=C_BG, fg=C_DIM, font=FONT_S).pack(side="left")
        self.e_pid = self._entry(row, 10); self.e_pid.pack(side="left", padx=4)
        tk.Label(row, text="语言", bg=C_BG, fg=C_DIM, font=FONT_S).pack(side="left", padx=(12, 2))
        self.cb_lang = ttk.Combobox(row, values=LANGS, width=9, state="readonly", font=FONT, foreground=C_TEXT)
        self.cb_lang.pack(side="left"); self.cb_lang.set("python3")
        self.btn_sub = self._btn(row, "提交评测", self.on_submit, fg="#fff", bg="#1a3a5c", hov="#1f4a75")
        self.btn_sub.pack(side="right")
        self.txt_code = self._text(p, wrap="none"); self.txt_code.pack(fill="both", expand=True, padx=14, pady=(0, 8))
        self.txt_res = self._text(p, 7); self.txt_res.pack(fill="both", padx=14, pady=(0, 12))
        self.txt_res.config(state="disabled")

    # ---------- 面板：登录 ----------
    def _p_login(self):
        p = self._panel("login")
        box = tk.Frame(p, bg=C_BG); box.pack(padx=60, pady=50)
        tk.Label(box, text="🔑 登录", bg=C_BG, fg="#fff", font=("Segoe UI", 15, "bold")).pack(pady=(0, 18))
        tk.Label(box, text="用户名", bg=C_BG, fg=C_DIM, font=FONT_S).pack(anchor="w")
        self.e_lu = self._entry(box, 30); self.e_lu.pack(pady=(2, 10))
        tk.Label(box, text="密码", bg=C_BG, fg=C_DIM, font=FONT_S).pack(anchor="w")
        self.e_lp = self._entry(box, 30, show="*"); self.e_lp.pack(pady=(2, 14))
        self.btn_login = self._btn(box, "登 录", self.on_login, fg="#fff", bg="#1a3a5c", hov="#1f4a75")
        self.btn_login.pack(pady=4)
        self.lb_login = tk.Label(box, text="", bg=C_BG, fg=C_ERR, font=FONT_S); self.lb_login.pack(pady=6)

    # ---------- 面板：注册 ----------
    def _p_register(self):
        p = self._panel("register")
        box = tk.Frame(p, bg=C_BG); box.pack(padx=60, pady=50)
        tk.Label(box, text="📝 注册", bg=C_BG, fg="#fff", font=("Segoe UI", 15, "bold")).pack(pady=(0, 18))
        for lb, k in [("邀请码", "e_ri"), ("用户名", "e_ru"), ("密码", "e_rp"), ("确认密码", "e_rc")]:
            tk.Label(box, text=lb, bg=C_BG, fg=C_DIM, font=FONT_S).pack(anchor="w")
            setattr(self, k, self._entry(box, 30, show="*" if "p" in k or k == "e_rc" else None))
            getattr(self, k).pack(pady=(2, 10))
        self.btn_reg = self._btn(box, "注 册", self.on_register, fg="#fff", bg="#1a3a5c", hov="#1f4a75")
        self.btn_reg.pack(pady=4)
        self.lb_reg = tk.Label(box, text="", bg=C_BG, fg=C_OK, font=FONT_S); self.lb_reg.pack(pady=6)

    # ---------- 导航 ----------
    def _refresh_nav(self):
        # 功能栏目（顺序）
        for key, txt, icon in NAV:
            self.nav_btns[key].pack(fill="x")
        # 分隔线（固定一个，不重复创建）
        if not hasattr(self, "nav_sep"):
            self.nav_sep = tk.Frame(self.nav_btns["submit"].master, bg=C_BORDER, height=1)
        self.nav_sep.pack(fill="x", pady=8)
        # 登录 / 注册 / 登出
        if self.logged:
            self.nav_login.pack_forget(); self.nav_reg.pack_forget()
            self.nav_logout.pack(fill="x")
        else:
            self.nav_login.pack(fill="x"); self.nav_reg.pack(fill="x")
            self.nav_logout.pack_forget()

    def show_panel(self, key):
        for b in self.nav_btns.values():
            b.config(bg=C_PANEL, fg=C_DIM)
        if key in self.nav_btns:
            self.nav_btns[key].config(bg="#1a2a3a", fg="#fff")
        for k, p in self.panels.items():
            p.pack_forget()
        self.panels[key].pack(fill="both", expand=True)

    # ---------- 逻辑 ----------
    def _log(self, w, s, color=None):
        w.config(state="normal"); w.insert("end", s + "\n")
        if color:
            w.tag_add("c", f"end-{len(s)+2}c", "end-1c"); w.tag_config("c", foreground=color)
        w.see("end"); w.config(state="disabled")

    def _set_status(self, ok, msg):
        self.lb_status.config(text=msg, fg=(C_OK if ok else C_ERR))

    def _sm(self, e): self._off = (e.x, e.y)
    def _mm(self, e):
        self.geometry(f"+{self.winfo_x()+e.x-self._off[0]}+{self.winfo_y()+e.y-self._off[1]}")

    def _auto_login(self):
        if self.cfg.get("ojcid"):
            host = self.cfg.get("server", DEFAULT_IP).split(":")[0]
            self.client.cj.set_cookie(http.cookiejar.Cookie(0, "OJCID", self.cfg["ojcid"], None, False,
                host, False, "/", True, False, None, True, None, None, {}))
            self.logged = True; self._refresh_nav()
            self._set_status(True, f"已登录 {self.cfg.get('username','')}")
            self.show_panel("problems"); self.on_refresh_problems()

    def on_login(self):
        self.btn_login.config(state="disabled"); self.lb_login.config(text="登录中...", fg=C_DIM)
        def work():
            server = self.e_server.get().strip(); self.cfg["server"] = server; save_cfg(self.cfg)
            self.client = OJClient(server)
            ok, msg = self.client.login(self.e_lu.get().strip(), self.e_lp.get())
            self.after(0, lambda: self._login_done(ok, msg))
        threading.Thread(target=work, daemon=True).start()

    def _login_done(self, ok, msg):
        self.btn_login.config(state="normal")
        if ok:
            self.cfg["ojcid"] = msg; self.cfg["username"] = self.e_lu.get().strip(); save_cfg(self.cfg)
            self.logged = True; self._refresh_nav()
            self._set_status(True, f"已登录 {self.cfg['username']}")
            self.lb_login.config(text="登录成功", fg=C_OK)
            self.show_panel("problems"); self.on_refresh_problems()
        else:
            self.lb_login.config(text=f"登录失败: {msg}", fg=C_ERR)

    def on_register(self):
        self.btn_reg.config(state="disabled"); self.lb_reg.config(text="注册中...", fg=C_DIM)
        def work():
            ok, msg = self.client.register(self.e_ri.get().strip(), self.e_ru.get().strip(),
                                           self.e_rp.get(), self.e_rc.get())
            self.after(0, lambda: (self.btn_reg.config(state="normal"),
                                   self.lb_reg.config(text=msg, fg=(C_OK if ok else C_ERR)),
                                   self.show_panel("login") if ok else None))
        threading.Thread(target=work, daemon=True).start()

    def on_logout(self):
        self.client.logout()
        self.cfg["ojcid"] = ""; save_cfg(self.cfg)
        self.client.cj.clear()
        self.logged = False; self._refresh_nav()
        self._set_status(False, "已登出")
        self.show_panel("login")

    def on_refresh_problems(self):
        def work():
            ps = self.client.problems()
            self.after(0, lambda: self._fill(ps))
        threading.Thread(target=work, daemon=True).start()

    def _fill(self, ps):
        if ps is None: self._set_status(False, "题库加载失败"); return
        self.problems = ps
        self.listbox.delete(0, "end")
        for p in ps:
            rate = f"{p['ac']}/{p['submissions']}" if p['submissions'] else "-"
            self.listbox.insert("end", f"  {p['problem_id']:<12} {p['title'][:18]:<18} {rate} AC")
        self._set_status(True, f"已登录 {self.cfg.get('username','')} · {len(ps)} 题")

    def _cur_pid(self):
        sel = self.listbox.curselection()
        if sel:
            p = self.problems[sel[0]]
            return p["problem_id"], p
        return self.e_pid.get().strip(), None

    def _on_pick_problem(self):
        pid, p = self._cur_pid()
        if not pid: return
        self.e_pid.delete(0, "end"); self.e_pid.insert(0, pid)
        self._load_detail(pid)

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
        pid, _ = self._cur_pid()
        if not pid: messagebox.showwarning("提示", "请先在题库选择题目"); return
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
            self.after(0, lambda: self._log(self.txt_up, ("✓ " + msg) if ok else ("✗ " + msg), C_OK if ok else C_ERR))
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
