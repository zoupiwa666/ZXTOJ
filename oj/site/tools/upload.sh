#!/bin/bash
# ====================================================
#  ZXT SUPER OJ - 数据包上传工具 (Linux / macOS)
#  用法:
#    ./upload.sh [-s http://IP:端口] [数据包路径]
#    ./upload.sh -h  查看帮助
#  也可直接运行后按提示输入
# ====================================================

SERVER="${ZXT_OJ_SERVER:-}"
FILE=""

usage() {
  cat <<'EOF'
用法: upload.sh [-s http://IP:端口] [数据包路径]
示例:
  ./upload.sh -s http://192.168.1.100:18001 ./P1000.zip
  ./upload.sh ./P1000.zip            # 默认服务器 http://localhost:18001
  ./upload.sh -h                     # 帮助
提示: 上传后把返回的 /tmp/... 路径填到 OJ 题目编辑页「路径导入」
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
  read -r -p "请输入OJ服务器地址(直接回车用 http://localhost:18001): " SERVER
  SERVER="${SERVER:-http://localhost:18001}"
fi

if [ -z "$FILE" ]; then
  read -r -p "请输入数据包路径: " FILE
fi

if [ ! -f "$FILE" ]; then
  echo "[错误] 文件不存在: $FILE" >&2
  exit 1
fi

echo "服务器: $SERVER"
echo "[上传中] $FILE ..."

RESP=$(curl -s -F "file=@$FILE" "$SERVER/api/tool_upload.php")
echo "$RESP"

case "$RESP" in
  *'"ok":true'*)
    echo
    echo "[完成] 复制上方返回的 /tmp/... 路径，到 OJ 题目编辑页「路径导入」粘贴"
    ;;
  *)
    echo
    echo "[失败] 请检查服务器地址/端口是否正确，以及 OJ 是否正常运行" >&2
    exit 1
    ;;
esac
