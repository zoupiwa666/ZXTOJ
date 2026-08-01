FROM python:3.11-alpine

# 国内镜像源（阿里云）
RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.aliyun.com/g' /etc/apk/repositories
LABEL description="Code Judge Sandbox - C/C++/Python3"
RUN apk add --no-cache gcc g++ musl-dev libc-dev linux-headers && rm -rf /var/cache/apk/* /tmp/*
RUN adduser -D -u 1000 judge
COPY judge_system/ /judge_system/
WORKDIR /tmp/judge
USER judge
ENTRYPOINT ["python3", "/judge_system/judge_core.py"]
