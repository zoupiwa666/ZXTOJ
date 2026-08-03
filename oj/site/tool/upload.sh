#!/bin/bash
# ====================================================
#  ZXT SUPER OJ - Data Package Uploader (Linux/macOS)
#  两种模式:
#    HTTP (默认) : 走 OJ 网页接口上传，带进度条
#    SCP         : 走 scp(SSH) 上传，稳定不断线，速度快
# ====================================================

SERVER="${ZXT_OJ_SERVER:-}"
FILE=""
MODE=http
USERHOST=""
DATADIR="${ZXT_OJ_DATADIR:-/opt/oj-deploy/data}"

usage() {
  cat <<'EOF'
Usage: upload.sh [OPTIONS] [data package path]

Modes:
  HTTP (default): upload via OJ web API, shows progress bar
  SCP:            upload via scp (SSH), reliable, never drops

Options:
  -s, --server URL    OJ server URL for HTTP mode (default http://localhost:18001)
  -u, --userhost U@H  SSH user@host for SCP mode (e.g. root@192.168.1.100)
  -d, --datadir DIR   server data directory for SCP mode (default /opt/oj-deploy/data)
      --scp           use SCP mode
  -h, --help          show this help

Examples:
  ./upload.sh -s http://192.168.1.100:18001 ./P1000.zip
  ./upload.sh --scp -u root@192.168.1.100 -d /opt/oj-deploy/data ./P1000.zip
EOF
}

while [ $# -gt 0 ]; do
  case "$1" in
    -s|--server)   SERVER="$2"; shift 2;;
    -u|--userhost) USERHOST="$2"; shift 2;;
    -d|--datadir)  DATADIR="$2"; shift 2;;
    --scp)         MODE=scp; shift;;
    -h|--help)     usage; exit 0;;
    *)             FILE="$1"; shift;;
  esac
done

if [ -z "$FILE" ]; then
  read -r -p "Enter data package path: " FILE
fi
if [ ! -f "$FILE" ]; then
  echo "[ERROR] File not found: $FILE" >&2
  exit 1
fi

if [ "$MODE" = "scp" ]; then
  if [ -z "$USERHOST" ]; then
    read -r -p "Enter SSH user@host (e.g. root@IP): " USERHOST
  fi
  if [ -z "$USERHOST" ]; then echo "[ERROR] user@host required for scp mode" >&2; exit 1; fi
  echo "Server data dir: $DATADIR"
  echo "[SCP] Uploading $(basename "$FILE") to ${USERHOST}:${DATADIR}/packages/ ..."
  scp "$FILE" "${USERHOST}:${DATADIR}/packages/" || { echo "[FAILED] scp failed" >&2; exit 1; }
  echo
  echo "[DONE] In OJ edit page, use 'Import by Path':"
  echo "  /data/packages/$(basename "$FILE")"
  exit 0
fi

# ===== HTTP mode =====
if [ -z "$SERVER" ]; then
  read -r -p "Enter OJ server URL (Enter for http://localhost:18001): " SERVER
  SERVER="${SERVER:-http://localhost:18001}"
fi
echo "Server: $SERVER"
echo "[Uploading] $FILE ..."
echo "  (progress bar below, please wait)"

RESP=$(curl --progress-bar -F "file=@$FILE" "$SERVER/api/tool_upload.php")
echo "$RESP"

case "$RESP" in
  *'"ok":true'*)
    echo
    echo "[DONE] Copy the returned /tmp/... path and paste it into"
    echo "       the \"Import by Path\" field on the OJ problem edit page."
    ;;
  *)
    echo
    echo "[FAILED] Please check the server URL/port and make sure the OJ is running." >&2
    exit 1
    ;;
esac
