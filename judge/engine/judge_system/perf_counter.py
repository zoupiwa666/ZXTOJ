"""确定性计时：perf_event_open 指令计数模型

T_model = instructions×INS_NS + syscalls×SYSCALL_NS + page_faults×PF_NS

与机器负载/CPU 争用无关：同一程序同一输入，指令数恒定 → 模型时间恒定，
彻底消除评测波动。仅统计 exec 后的被评测程序本身（enable_on_exec + inherit）。

perf 不可用时（权限/内核限制）返回 None，由调用方回退墙钟计时。
"""
import ctypes

PERF_TYPE_HARDWARE = 0
PERF_TYPE_SOFTWARE = 1
PERF_COUNT_HW_INSTRUCTIONS = 1
PERF_COUNT_SW_PAGE_FAULTS = 2
PERF_COUNT_SW_SYSCALLS = 14
SYS_perf_event_open = 298   # x86_64

# 权重常数（纳秒）——跨机器固定，消除波动
INS_NS = 0.5          # 每指令 ~0.5ns（约 2GHz IPC≈1）
SYSCALL_NS = 1500.0   # 每次系统调用 ~1.5us
PF_NS = 2000.0        # 每次缺页 ~2us


class PerfEventAttr(ctypes.Structure):
    """struct perf_event_attr (x86_64, kernel >= 5.13, size=128)"""
    _fields_ = [
        ("type", ctypes.c_uint32),
        ("size", ctypes.c_uint32),
        ("config", ctypes.c_uint64),
        ("sample_period", ctypes.c_uint64),
        ("sample_type", ctypes.c_uint64),
        ("read_format", ctypes.c_uint64),
        ("flags", ctypes.c_uint64),
        ("wakeup_events", ctypes.c_uint32),
        ("bp_type", ctypes.c_uint32),
        ("config1", ctypes.c_uint64),
        ("config2", ctypes.c_uint64),
        ("branch_sample_type", ctypes.c_uint64),
        ("sample_regs_user", ctypes.c_uint64),
        ("sample_stack_user", ctypes.c_uint32),
        ("clockid", ctypes.c_int32),
        ("sample_regs_intr", ctypes.c_uint64),
        ("aux_watermark", ctypes.c_uint32),
        ("sample_max_stack", ctypes.c_uint16),
        ("__reserved_2", ctypes.c_uint16),
        ("aux_sample_size", ctypes.c_uint32),
        ("__reserved_3", ctypes.c_uint32),
        ("sig_data", ctypes.c_uint64),
    ]


_libc = ctypes.CDLL(None, use_errno=True)


def _open(ev_type, config, pid):
    attr = PerfEventAttr()
    attr.size = ctypes.sizeof(PerfEventAttr)
    attr.type = ev_type
    attr.config = config
    # flags: inherit(2) only——Popen 返回时子进程已 exec，
    # 用 enable_on_exec 会错过触发导致计数恒为 0；open 即计数，
    # 只统计已 exec 的被评测程序（exec 前的启动代码量可忽略）
    attr.flags = (1 << 1)
    return _libc.syscall(SYS_perf_event_open, ctypes.byref(attr), pid, -1, -1, 0)


def _read(fd):
    val = ctypes.c_uint64()
    if _libc.read(fd, ctypes.byref(val), 8) == 8:
        return val.value
    return 0


class PerfCounters:
    """attach 到已 fork 的子进程；enable_on_exec 保证计数从 exec 开始"""

    def __init__(self, pid):
        self.fds = {}
        self.ok = False
        try:
            fd = _open(PERF_TYPE_HARDWARE, PERF_COUNT_HW_INSTRUCTIONS, pid)
            if fd >= 0:
                self.fds['ins'] = fd
            fd = _open(PERF_TYPE_SOFTWARE, PERF_COUNT_SW_SYSCALLS, pid)
            if fd >= 0:
                self.fds['sys'] = fd
            fd = _open(PERF_TYPE_SOFTWARE, PERF_COUNT_SW_PAGE_FAULTS, pid)
            if fd >= 0:
                self.fds['pf'] = fd
            self.ok = len(self.fds) >= 1
        except Exception:
            self.ok = False

    def model_time_ns(self):
        """返回模型时间（ns）；perf 不可用返回 None"""
        if not self.ok:
            return None
        ins = _read(self.fds['ins']) if 'ins' in self.fds else 0
        sysc = _read(self.fds['sys']) if 'sys' in self.fds else 0
        pf = _read(self.fds['pf']) if 'pf' in self.fds else 0
        return ins * INS_NS + sysc * SYSCALL_NS + pf * PF_NS

    def counts(self):
        if not self.ok:
            return None
        return {
            'instructions': _read(self.fds['ins']) if 'ins' in self.fds else 0,
            'syscalls': _read(self.fds['sys']) if 'sys' in self.fds else 0,
            'page_faults': _read(self.fds['pf']) if 'pf' in self.fds else 0,
        }

    def close(self):
        for fd in self.fds.values():
            try:
                _libc.close(fd)
            except Exception:
                pass
        self.fds.clear()
