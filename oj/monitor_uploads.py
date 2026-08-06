#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# OJ 上传停滞监控 v2（连接级）
# 原理：nginx 默认先把请求体缓冲完才交给 php-fpm，所以停滞上传不占 PHP 线程，
#       停滞点在于"客户端不再发数据"。本监控检测：已收到 >64KB 且 lastrcv >10s
#       （10 秒没收到任何数据包）的 nginx 连接，判定为停滞上传，精确断开该 TCP 连接。
# 效果：浏览器立即显示"上传中断"，配合直传的断点续传可秒级重试，不再干等超时。
import subprocess, re, time

KILL_BYTES = 64 * 1024   # 收到的字节数超过此值才视为上传（避免误伤正常请求）
STALL_MS = 10000         # 连续多少毫秒没收数据判定为停滞
POLL = 5
LOG = '/tmp/oj_upload_monitor.log'

def log(msg):
    try:
        with open(LOG, 'a') as f:
            f.write('[%s] %s\n' % (time.strftime('%F %T'), msg))
    except Exception:
        pass

def get_conns():
    conns = []
    try:
        out = subprocess.run(['ss', '-tinp', 'sport = :80'],
                             capture_output=True, text=True, timeout=5).stdout
    except Exception:
        return conns
    lines = out.splitlines()
    i = 0
    while i < len(lines):
        if lines[i].startswith('ESTAB'):
            m = re.search(r'\S+:\d+\s+(\d+\.\d+\.\d+\.\d+):(\d+)', lines[i])
            if m and i + 1 < len(lines):
                info = lines[i + 1]
                mb = re.search(r'bytes_received:(\d+)', info)
                mr = re.search(r'lastrcv:(\d+)', info)
                conns.append({'peer': m.group(1), 'port': m.group(2),
                              'bytes': int(mb.group(1)) if mb else 0,
                              'lastrcv': int(mr.group(1)) if mr else 0})
            i += 2
        else:
            i += 1
    return conns

# ===== PHP-FPM worker 停滞检测（502 场景：上传/文件接口卡死占 worker）=====
STALL_URIS = ('fchunk_upload.php','file_upload.php','upload_package.php','chunk.php',
              'tool_upload.php','fchunk_merge.php','merge.php','check.php','fchunk_check.php')
PHP_STALL_SEC = 60   # 这些接口应秒级完成，超过 60s 视为卡死

def get_php_workers():
    workers = {}
    try:
        out = subprocess.run(['curl','-s','http://127.0.0.1:81/status?full'],
                             capture_output=True, text=True, timeout=5).stdout
    except Exception:
        return workers
    cur = None
    for line in out.splitlines():
        if line.startswith('pid:'):
            cur = {'pid': line.split(':',1)[1].strip()}
            workers[cur['pid']] = cur
        elif cur is not None and ':' in line:
            k, v = line.split(':', 1); k, v = k.strip(), v.strip()
            if k in ('state','request duration','request URI'): cur[k] = v
    return workers

def check_php_workers(killed_workers):
    workers = get_php_workers()
    now = time.time()
    for pid, w in workers.items():
        if w.get('state') != 'Running': continue
        uri = w.get('request URI','')
        if not any(u in uri for u in STALL_URIS): continue
        try: dur = float(w.get('request duration','0') or 0) / 1000000  # 微秒->秒
        except: continue
        if dur > PHP_STALL_SEC and now - killed_workers.get(pid, 0) > 120:
            log('KILL stuck php-fpm worker pid=%s uri=%s dur=%.0fs' % (pid, uri, dur))
            try:
                os.kill(int(pid), 9)
                log('  -> php worker killed (master 会自动拉起新 worker)')
                killed_workers[pid] = now
            except Exception as e:
                log('  php kill fail: %s' % e)

def main():
    log('== upload-stall monitor v2 started (bytes>%d, lastrcv>%dms) ==' % (KILL_BYTES, STALL_MS))
    killed_cache = {}   # (peer,port) -> kill 时间，30s 内不重复杀
    killed_workers = {} # pid -> kill 时间，120s 内不重复杀
    while True:
        try:
            now = time.time()
            check_php_workers(killed_workers)
            for c in get_conns():
                key = (c['peer'], c['port'])
                if c['bytes'] > KILL_BYTES and c['lastrcv'] > STALL_MS:
                    if now - killed_cache.get(key, 0) < 30:
                        continue
                    log('KILL stalled upload peer=%s:%s bytes=%d lastrcv=%dms'
                        % (c['peer'], c['port'], c['bytes'], c['lastrcv']))
                    subprocess.run(['ss', '-K', 'sport = :80', 'dst', c['peer'], 'dport = :%s' % c['port']],
                                   capture_output=True, timeout=5)
                    log('  -> connection killed')
                    killed_cache[key] = now
        except Exception as e:
            log('loop error: %s' % e)
        time.sleep(POLL)

if __name__ == '__main__':
    main()
