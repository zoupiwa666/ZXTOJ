# 评测机引擎容器
FROM python:3.11-slim

# 国内 apt 源（阿里云，直接覆盖 deb822 源文件）
RUN rm -f /etc/apt/sources.list.d/debian.sources && \
    echo "deb http://mirrors.aliyun.com/debian/ trixie main contrib non-free non-free-firmware" > /etc/apt/sources.list && \
    echo "deb http://mirrors.aliyun.com/debian/ trixie-updates main contrib non-free non-free-firmware" >> /etc/apt/sources.list && \
    echo "deb http://mirrors.aliyun.com/debian-security trixie-security main contrib non-free non-free-firmware" >> /etc/apt/sources.list
RUN apt-get update && apt-get install -y --no-install-recommends docker.io curl     && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY engine/requirements.txt .
# pip 使用国内阿里云镜像源
RUN pip config set global.index-url https://mirrors.aliyun.com/pypi/simple/ && \
    pip install --no-cache-dir -r requirements.txt pymysql pyyaml

COPY engine/ /app/

# 评测机自身配置（通过环境变量覆盖）
ENV JUDGE_HOST=0.0.0.0 \
    JUDGE_PORT=8000 \
    JUDGE_CPU_LIMIT=0.5 \
    JUDGE_MEM_LIMIT=512m \
    JUDGE_POOL_SIZE=3

EXPOSE 8000

CMD ["sh", "-c", "python /app/config_gen.py && python /app/main.py"]
