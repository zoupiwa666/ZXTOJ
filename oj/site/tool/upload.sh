#!/bin/bash
# ====================================================
#  ZXT SUPER OJ - Data Package Uploader (Linux/macOS)
#  Usage:
#    ./upload.sh [-s http://IP:PORT] [data package path]
#    ./upload.sh -h   show help
#  You can also run it and follow the prompts.
# ====================================================

SERVER="${ZXT_OJ_SERVER:-}"
FILE=""

usage() {
  cat <<'EOF'
Usage: upload.sh [-s http://IP:PORT] [data package path]
Examples:
  ./upload.sh -s http://192.168.1.100:18001 ./P1000.zip
  ./upload.sh ./P1000.zip            # default server http://localhost:18001
  ./upload.sh -h                     # help
Tip: After upload, copy the returned /tmp/... path and paste it
     into the "Import by Path" field on the OJ problem edit page.
EOF
}

while [ $# -gt 0 ]; do
  case "$1" in
    -s|--server)
      SERVER="$2"; shift 2;;
    -h|--help)
      usage; exit 0;;
    *)
      FILE="$1"; shift;;
  esac
done

if [ -z "$SERVER" ]; then
  read -r -p "Enter OJ server URL (Enter for http://localhost:18001): " SERVER
  SERVER="${SERVER:-http://localhost:18001}"
fi

if [ -z "$FILE" ]; then
  read -r -p "Enter data package path: " FILE
fi

if [ ! -f "$FILE" ]; then
  echo "[ERROR] File not found: $FILE" >&2
  exit 1
fi

echo "Server: $SERVER"
echo "[Uploading] $FILE ..."

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
