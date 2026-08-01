# 评测机引擎容器
FROM python:3.11-slim

# 国内 apt 源（阿里云）
RUN sed -i 's|deb.debian.org|mirrors.aliyun.com|g; s|security.debian.org|mirrors.aliyun.com|g' /etc/apt/sources.list.d/debian.sources 2>/dev/null || \
    sed -i 's|deb.debian.org|mirrors.aliyun.com|g; s|security.debian.org|mirrors.aliyun.com|g' /etc/apt/sources.list
RUN apt-get update && apt-get install -y --no-install-recommends docker.io curl     && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY engine/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt pymysql pyyaml

COPY engine/ /app/

# 评测机自身配置（通过环境变量覆盖）
ENV JUDGE_HOST=0.0.0.0 \
    JUDGE_PORT=8000 \
    JUDGE_CPU_LIMIT=0.5 \
    JUDGE_MEM_LIMIT=512m \
    JUDGE_POOL_SIZE=3

EXPOSE 8000

CMD ["sh", "-c", "python /app/config_gen.py && python /app/main.py"]
