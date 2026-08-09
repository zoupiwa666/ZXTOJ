/* ictime: 确定性时间度量工具 —— 用硬件指令计数器替代墙钟
 * 用法: ictime [--out=INSFILE] [--pidfile=PIDFILE] -- CMD [ARGS...]
 * 功能: 运行 CMD，统计其（含子进程）执行的用户态指令数，写入 INSFILE；
 *       将 CMD 进程 pid 写入 PIDFILE（供内存监控）；退出码与 CMD 一致。
 */
#include <linux/perf_event.h>
#include <sys/syscall.h>
#include <sys/ioctl.h>
#include <sys/wait.h>
#include <unistd.h>
#include <string.h>
#include <stdio.h>
#include <stdlib.h>

int main(int argc, char** argv) {
    const char* outfile = NULL;
    const char* pidfile = NULL;
    int i = 1;
    for (; i < argc; i++) {
        if (!strncmp(argv[i], "--out=", 6)) { outfile = argv[i] + 6; continue; }
        if (!strncmp(argv[i], "--pidfile=", 10)) { pidfile = argv[i] + 10; continue; }
        if (!strcmp(argv[i], "--")) { i++; break; }
    }
    if (i >= argc) { fprintf(stderr, "usage: ictime [--out=F] [--pidfile=F] -- CMD [ARGS...]\n"); return 126; }

    struct perf_event_attr pe; memset(&pe, 0, sizeof(pe));
    pe.type = PERF_TYPE_HARDWARE;
    pe.size = sizeof(pe);
    pe.config = PERF_COUNT_HW_INSTRUCTIONS;
    pe.disabled = 1;          /* 先禁用，exec 时开启 */
    pe.exclude_kernel = 1;    /* 只计用户态指令 */
    pe.exclude_hv = 1;
    pe.enable_on_exec = 1;    /* exec 后开始计数 */
    pe.inherit = 1;           /* fork 子进程继承计数 */
    int fd = syscall(__NR_perf_event_open, &pe, 0, -1, -1, 0);
    /* 软件事件：缺页计数（对内存密集型程序加权） */
    struct perf_event_attr pe2; memset(&pe2, 0, sizeof(pe2));
    pe2.type = 1;             /* PERF_TYPE_SOFTWARE */
    pe2.size = sizeof(pe2);
    pe2.config = 2;           /* PERF_COUNT_SW_PAGE_FAULTS */
    pe2.disabled = 1; pe2.exclude_kernel = 1; pe2.enable_on_exec = 1; pe2.inherit = 1;
    int fd2 = syscall(__NR_perf_event_open, &pe2, 0, -1, -1, 0);
    if (fd < 0 && fd2 < 0) { perror("perf_event_open"); return 126; }

    pid_t pid = fork();
    if (pid == 0) { execvp(argv[i], &argv[i]); perror("execvp"); _exit(127); }

    if (pidfile) { FILE* f = fopen(pidfile, "w"); if (f) { fprintf(f, "%d", pid); fclose(f); } }

    int st; waitpid(pid, &st, 0);
    long long c = 0; if (fd >= 0) { read(fd, &c, 8); close(fd); }
    long long c2 = 0; if (fd2 >= 0) { read(fd2, &c2, 8); close(fd2); }
    if (outfile) { FILE* f = fopen(outfile, "w"); if (f) { fprintf(f, "INS=%lld\nPF=%lld\n", c, c2); fclose(f); } }
    else fprintf(stderr, "INS=%lld PF=%lld\n", c, c2);
    if (WIFEXITED(st)) return WEXITSTATUS(st);
    if (WIFSIGNALED(st)) { kill(getpid(), WTERMSIG(st)); }
    return 1;
}
