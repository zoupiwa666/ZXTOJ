# ZXT OJ - VSCode 插件

提交代码到 ZXT OJ 并编辑题目。

## 安装

1. 打开 VSCode → 扩展面板 → `...` → **从 VSIX 安装** 或 **Install from Location**（选择本文件夹）
2. 或 F5 调试运行（需装 @vscode/vsce / 直接用 Extension Development Host）

## 使用

| 命令 | 功能 |
|---|---|
| `ZXT OJ: 登录` | 输入服务器/用户名/密码 → 获取 OJCID（一周有效，存本地） |
| `ZXT OJ: 提交当前文件` | 自动识别语言（.py/.cpp/.c）→ 选题目 → 提交 → 弹窗+输出面板显示结果 |
| `ZXT OJ: 编辑题目` | Webview 表单：编号载入 → 改题面/时限/内存/可见性 → 保存 |

右键代码 → 「ZXT OJ: 提交当前文件」快捷提交。

## 配置

设置项 `zxt-oj.server`：OJ 服务器地址（默认 `156.239.236.66:18001`），也可在登录时输入。
