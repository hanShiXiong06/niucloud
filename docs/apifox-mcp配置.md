# 云雾 API 文档 — Apifox MCP 配置

让 Claude 桌面端直接读取这套云雾接口文档(project-id `5443236`),后续写 `AiChannelService` 时可让 AI 按最新文档生成/核对代码。

## 1. 先拿 Apifox 个人访问令牌

1. 浏览器登录 Apifox → 右上角头像 → **账号设置** → **API 访问令牌**(Personal Access Tokens)。
2. 点**新建令牌**,复制生成的字符串(只显示一次,务必存好)。
3. 把下面配置里的 `<access-token>` 整段替换成它。

## 2. 配置内容(填好 project-id,待填 token)

```json
{
  "mcpServers": {
    "yunwu-api-docs": {
      "command": "npx",
      "args": [
        "-y",
        "apifox-mcp-server@latest",
        "--project-id=5443236"
      ],
      "env": {
        "APIFOX_ACCESS_TOKEN": "<把这里换成你的 Apifox 访问令牌>"
      }
    }
  }
}
```

> 说明:把服务名从中文 `云雾API 接口对接 - API 文档` 改成了英文 `yunwu-api-docs`,避免个别客户端对中文/空格服务名解析异常。功能不变。

## 3. 加到 Claude 桌面端

把上面 `mcpServers` 里的 `yunwu-api-docs` 这一项,合并进 Claude 桌面端的 MCP 配置文件:

- **macOS** 配置文件路径:
  `~/Library/Application Support/Claude/claude_desktop_config.json`

操作:
1. 打开该文件(没有就新建)。
2. 如果已有 `mcpServers`,把 `yunwu-api-docs` 这一项加进去;如果没有,整段粘贴。
3. 确认 `APIFOX_ACCESS_TOKEN` 已替换为真实令牌。
4. **完全退出并重启** Claude 桌面端(让它重新拉起 MCP 进程)。

## 4. 前置环境检查

终端执行,确认 Node ≥ 18:

```bash
node -v        # 需 >= v18, 推荐最新 LTS
npx -v
```

若没装,用 nvm 或官网安装 Node LTS 即可。npx 首次会联网从 npm 拉 `apifox-mcp-server`,确保能访问 `www.npmjs.com`。

## 5. 验证可用

重启后,在对话里说一句:

> 通过 MCP 获取云雾 API 文档,列出 chat/completions 接口的请求和响应字段

能列出来就说明通了。文档有更新时,让 AI **刷新接口文档数据**即可(它默认本地缓存)。

---

### 安全提醒
- 访问令牌等同账号凭证,**不要提交进 git**。该文件放在 `docs/` 仅作说明,真实令牌请只写进本机 `claude_desktop_config.json`,别填回本文件再提交。
- 建议把本文件加进 `.gitignore`,或替换 token 后再提交。
