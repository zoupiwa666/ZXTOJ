# 造数据专用容器：DeepSeek 生成 + 运行生成器/std 产出测试数据
FROM python:3.11-slim

# 国内 apt 源（阿里云，覆盖 deb822）
RUN rm -f /etc/apt/sources.list.d/debian.sources && \
    echo "deb http://mirrors.aliyun.com/debian/ trixie main contrib non-free non-free-firmware" > /etc/apt/sources.list && \
    echo "deb http://mirrors.aliyun.com/debian/ trixie-updates main contrib non-free non-free-firmware" >> /etc/apt/sources.list && \
    echo "deb http://mirrors.aliyun.com/debian-security trixie-security main contrib non-free non-free-firmware" >> /etc/apt/sources.list
RUN apt-get update && apt-get install -y --no-install-recommends \
    gcc g++ python3 python3-pip curl coreutils \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY app/requirements.txt .
RUN pip config set global.index-url https://mirrors.aliyun.com/pypi/simple/ && \
    pip install --no-cache-dir -r requirements.txt
COPY app/ /app/

ENV DATAMAKER_PORT=8000 DATA_ROOT=/data/problems
EXPOSE 8000
CMD ["sh", "-c", "uvicorn main:app --host 0.0.0.0 --port $DATAMAKER_PORT"]
