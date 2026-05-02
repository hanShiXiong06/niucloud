# 回收系统 2.0 权限控制流程图

> 目标：补齐 Niucloud 插件权限的完整闭环。当前插件里已经有菜单/按钮权限配置，但缺少稳定的“获取权限、前端使用权限、后端强制校验、操作审计”流程。

核心闭环：

```text
set + get + use + enforce + audit
```

即：

```text
定义权限 -> 写入菜单表 -> 角色授权 -> 登录获取 -> 前端使用 -> 后端校验 -> 操作审计
```

---

## 1. 权限控制总览

```mermaid
flowchart TD
    A[插件菜单权限定义<br/>app/dict/menu/site.php] --> B[插件安装/菜单同步]
    B --> C[写入 sys_menu]
    C --> D[后台角色管理读取权限树]
    D --> E[管理员给角色勾选菜单/按钮权限]
    E --> F[写入角色权限关系<br/>role -> menu_key 列表]

    F --> G[用户登录后台]
    G --> H[根据用户角色读取 menu_key 权限]
    H --> I[后端返回菜单树 + 按钮权限 auth]
    I --> J[前端生成路由菜单]
    I --> K[前端保存按钮权限集合]

    J --> L[页面菜单显示控制]
    K --> M[按钮显示/禁用控制]

    M --> N[用户点击按钮]
    N --> O[调用后台 API]
    O --> P[AdminCheckRole 中间件校验接口权限]
    P --> Q{是否有权限}
    Q -- 有权限 --> R[执行业务逻辑]
    Q -- 无权限 --> S[返回无权限错误]
```

---

## 2. 菜单 / 按钮权限注册流程

插件里的 `app/dict/menu/site.php` 是权限声明源，最终必须同步到 `sys_menu` 后才能在角色管理里授权。

```mermaid
flowchart TD
    A[开发者在插件中定义权限] --> B[目录权限 menu_type = 0]
    A --> C[页面菜单 menu_type = 1]
    A --> D[按钮权限 menu_type = 2]

    B --> E[字段: menu_key]
    B --> F[字段: menu_name]
    B --> G[字段: parent_key]
    B --> H[字段: is_show]

    C --> I[字段: api_url]
    C --> J[字段: methods]
    C --> K[字段: router_path]
    C --> L[字段: view_path]

    D --> M[字段: api_url]
    D --> N[字段: methods]
    D --> O[字段: is_show = 0]

    E --> P[插件安装/菜单同步]
    I --> P
    M --> P

    P --> Q[CoreMenuService 解析菜单树]
    Q --> R[扁平化菜单树]
    R --> S[写入 sys_menu]

    S --> T[后台权限配置可见]
```

---

## 3. 权限数据结构关系

```mermaid
erDiagram
    SYS_MENU {
        string menu_key PK
        string parent_key
        string menu_name
        int menu_type
        string api_url
        string methods
        string router_path
        string view_path
        int is_show
        int status
        string app_type
        string addon
    }

    SYS_ROLE {
        int role_id PK
        string role_name
        int site_id
        int status
    }

    SYS_ROLE_MENU {
        int id PK
        int role_id
        string menu_key
        int site_id
    }

    SYS_USER {
        int uid PK
        string username
    }

    SYS_USER_ROLE {
        int id PK
        int uid
        int role_id
        int site_id
    }

    SYS_MENU ||--o{ SYS_ROLE_MENU : "menu_key"
    SYS_ROLE ||--o{ SYS_ROLE_MENU : "role_id"
    SYS_USER ||--o{ SYS_USER_ROLE : "uid"
    SYS_ROLE ||--o{ SYS_USER_ROLE : "role_id"
```

---

## 4. 角色授权流程

```mermaid
flowchart TD
    A[管理员进入角色管理] --> B[读取 sys_menu 权限树]
    B --> C[展示目录/菜单/按钮权限]

    C --> D[管理员勾选权限]
    D --> E[勾选页面菜单权限]
    D --> F[勾选按钮权限]
    D --> G[勾选接口权限]

    E --> H[保存角色权限]
    F --> H
    G --> H

    H --> I[删除旧角色权限]
    I --> J[写入新的 role -> menu_key 列表]

    J --> K[清理权限缓存]
    K --> L[角色授权完成]
```

---

## 5. 登录后获取菜单和按钮权限流程

这里是当前系统需要重点补齐的位置。权限已经能配置和保存，但前端页面必须能拿到按钮权限集合，否则按钮级权限无法统一实现。

```mermaid
sequenceDiagram
    participant U as 后台用户
    participant FE as Admin 前端
    participant API as 后端接口
    participant Auth as AuthService/RoleService
    participant Menu as MenuService
    participant DB as 数据库

    U->>FE: 登录后台
    FE->>API: 请求用户信息/菜单权限
    API->>Auth: 获取当前用户角色
    Auth->>DB: 查询 user -> role
    DB-->>Auth: 返回角色列表

    Auth->>DB: 查询 role -> menu_key
    DB-->>Auth: 返回用户拥有的 menu_key

    API->>Menu: 根据 menu_key 查询 sys_menu
    Menu->>DB: 查询菜单和按钮权限
    DB-->>Menu: 返回菜单/按钮节点

    Menu-->>API: 返回菜单树
    API-->>FE: 返回 routes + auth/button 权限

    FE->>FE: 生成左侧菜单
    FE->>FE: 缓存按钮权限集合
    FE->>U: 渲染有权限的页面和按钮
```

---

## 6. 前端按钮权限控制流程

```mermaid
flowchart TD
    A[后端返回菜单树] --> B[前端解析路由]
    B --> C[提取 menu_type = 1 页面菜单]
    B --> D[提取 menu_type = 2 按钮权限]

    C --> E[生成可访问页面路由]
    D --> F[生成按钮权限集合 authRules]

    F --> G[存入前端状态管理]
    G --> H[页面组件渲染]

    H --> I{按钮是否需要权限}
    I -- 不需要 --> J[直接显示按钮]
    I -- 需要 --> K[读取按钮权限 key]

    K --> L{authRules 是否包含该 key}
    L -- 包含 --> M[显示按钮]
    L -- 不包含 --> N[隐藏按钮或禁用按钮]

    M --> O[用户可点击]
    N --> P[用户不可见或不可操作]
```

---

## 7. 按钮点击后的后端接口权限校验流程

前端隐藏按钮只解决用户体验，不能作为安全边界。后端 `AdminCheckRole` 必须再次校验当前请求对应的 `api_url + methods`。

```mermaid
sequenceDiagram
    participant U as 用户
    participant FE as 前端按钮
    participant API as 后端接口
    participant MW as AdminCheckRole
    participant Auth as AuthService
    participant Biz as 业务服务

    U->>FE: 点击按钮
    FE->>FE: 前端按钮权限已通过
    FE->>API: 发起请求

    API->>MW: 进入 AdminCheckRole 中间件
    MW->>MW: 获取当前请求 method + path
    MW->>Auth: 校验当前用户是否拥有该 API 权限

    Auth->>Auth: 根据 sys_menu.api_url + methods 匹配 menu_key
    Auth->>Auth: 判断用户角色是否拥有 menu_key

    alt 有权限
        Auth-->>MW: 校验通过
        MW->>Biz: 执行业务逻辑
        Biz-->>API: 返回业务结果
        API-->>FE: 返回成功
    else 无权限
        Auth-->>MW: 校验失败
        MW-->>API: 返回无权限
        API-->>FE: 返回 403/无权限提示
    end
```

---

## 8. 当前问题：set 有了，但 get 没闭环

```mermaid
flowchart TD
    A[当前插件已定义按钮权限] --> B[menu_type = 2]
    B --> C[api_url + methods 已配置]
    C --> D[权限已写入 sys_menu]

    D --> E[角色管理可以勾选]
    E --> F[角色权限可以保存]
    F --> G[后端接口可能已能校验]

    G --> H{前端是否获取按钮权限}
    H -- 没有 --> I[页面不知道用户有哪些按钮权限]
    I --> J[按钮只能全部显示或手写判断]
    J --> K[用户体验和权限控制不统一]

    H -- 有 --> L[前端拿到 auth/button 权限集合]
    L --> M[页面按钮按权限显示]
    M --> N[按钮点击后后端再次校验]
    N --> O[形成完整权限闭环]
```

---

## 9. 补齐后的完整权限闭环

```mermaid
flowchart TD
    A[插件定义权限节点] --> B[安装/同步到 sys_menu]
    B --> C[角色管理展示权限树]
    C --> D[管理员给角色授权]
    D --> E[保存角色 menu_key 权限]

    E --> F[用户登录]
    F --> G[后端计算用户权限]
    G --> H[返回菜单树]
    G --> I[返回按钮权限 authRules]

    H --> J[前端生成页面菜单]
    I --> K[前端保存按钮权限集合]

    J --> L[页面路由访问控制]
    K --> M[按钮显示控制]

    L --> N{是否有页面权限}
    N -- 无 --> O[跳转无权限页]
    N -- 有 --> P[进入页面]

    P --> Q{是否有按钮权限}
    Q -- 无 --> R[隐藏按钮/禁用按钮]
    Q -- 有 --> S[显示按钮]

    S --> T[用户点击按钮]
    T --> U[请求后端 API]
    U --> V[AdminCheckRole 校验 API 权限]

    V --> W{API 权限是否通过}
    W -- 否 --> X[返回无权限]
    W -- 是 --> Y[执行业务逻辑]

    Y --> Z[记录操作日志]
```

---

## 10. 回收插件按钮权限设计流程

```mermaid
flowchart TD
    A[回收插件业务模块] --> B[订单管理]
    A --> C[质检管理]
    A --> D[报价中心]
    A --> E[物流管理]
    A --> F[财务管理]
    A --> G[服务能力中心]
    A --> H[打印管理]

    B --> B1[订单查看]
    B --> B2[订单创建]
    B --> B3[订单编辑]
    B --> B4[订单取消]
    B --> B5[订单删除]
    B --> B6[订单导出]
    B --> B7[订单备注]
    B --> B8[代用户确认价格]

    C --> C1[开始质检]
    C --> C2[提交质检]
    C --> C3[修改质检]
    C --> C4[复核质检]
    C --> C5[上传质检图片]

    D --> D1[查看报价]
    D --> D2[生成最终报价]
    D --> D3[调整最终报价]
    D --> D4[管理报价规则]
    D --> D5[同步报价源]
    D --> D6[处理规格映射]

    E --> E1[创建平台快递]
    E --> E2[取消快递]
    E --> E3[同步物流]
    E --> E4[创建退回物流]
    E --> E5[修改物流单号]

    F --> F1[查看待打款]
    F --> F2[确认打款]
    F --> F3[上传凭证]
    F --> F4[打款失败处理]
    F --> F5[导出财务记录]

    G --> G1[查看服务能力]
    G --> G2[保存服务配置]
    G --> G3[测试服务连接]
    G --> G4[启用/禁用服务]
    G --> G5[查看调用日志]

    H --> H1[打印设备标签]
    H --> H2[管理打印模板]
    H --> H3[测试打印]
    H --> H4[查看打印记录]
```

---

## 11. 回收系统角色权限矩阵

```mermaid
flowchart LR
    A[管理员] --> A1[全部权限]

    B[客服/运营] --> B1[订单查看]
    B --> B2[订单创建]
    B --> B3[订单备注]
    B --> B4[联系用户]
    B --> B5[代用户确认]
    B --> B6[创建退回]

    C[仓库] --> C1[订单签收]
    C --> C2[设备录入]
    C --> C3[打印标签]
    C --> C4[查看物流]

    D[质检员] --> D1[查看待质检]
    D --> D2[开始质检]
    D --> D3[提交质检]
    D --> D4[设备查询]
    D --> D5[上传图片]

    E[报价员] --> E1[查看质检结果]
    E --> E2[生成最终报价]
    E --> E3[调整报价]
    E --> E4[报价规则查看]

    F[财务] --> F1[查看待打款]
    F --> F2[确认打款]
    F --> F3[上传凭证]
    F --> F4[导出财务记录]

    G[交付人员] --> G1[服务能力配置]
    G --> G2[测试第三方服务]
    G --> G3[打印配置]
    G --> G4[报价基础配置]
```

---

## 12. 前端权限组件建议流程

建议前端统一封装 `AuthButton`、`v-auth` 或 `usePermission()`，不要在每个页面里手写判断。

```mermaid
flowchart TD
    A[页面中声明按钮权限 key] --> B[AuthButton / v-auth / usePermission]
    B --> C[读取前端 authRules]

    C --> D{是否拥有权限 key}
    D -- 是 --> E[渲染按钮]
    D -- 否 --> F{配置策略}
    F -- hide --> G[隐藏按钮]
    F -- disabled --> H[禁用按钮并显示无权限提示]

    E --> I[点击按钮]
    I --> J[调用 API]
    J --> K[后端再次校验]
```

---

## 13. 推荐的按钮权限命名规范

```mermaid
flowchart TD
    A[按钮权限命名] --> B[模块名]
    B --> C[资源名]
    C --> D[动作名]

    D --> E[示例: recycle_order_create]
    D --> F[示例: recycle_order_cancel]
    D --> G[示例: recycle_device_inspect]
    D --> H[示例: recycle_price_confirm]
    D --> I[示例: recycle_payment_confirm]
    D --> J[示例: recycle_integration_config_save]
    D --> K[示例: recycle_integration_test]
```

推荐格式：

```text
{addon}_{resource}_{action}
```

示例：

| 权限 key | 说明 |
| --- | --- |
| `recycle_order_list` | 查看订单列表 |
| `recycle_order_create` | 创建订单 |
| `recycle_order_cancel` | 取消订单 |
| `recycle_order_export` | 导出订单 |
| `recycle_device_inspect` | 设备质检 |
| `recycle_device_query` | 设备查询 |
| `recycle_price_confirm` | 确认最终报价 |
| `recycle_payment_confirm` | 确认打款 |
| `recycle_integration_config_save` | 保存服务能力配置 |
| `recycle_integration_test` | 测试服务能力 |
| `recycle_printer_print_label` | 打印设备标签 |

---

## 14. 最终目标流程

```mermaid
flowchart TD
    A[权限节点标准化] --> B[菜单/按钮/API 统一配置]
    B --> C[角色可视化授权]
    C --> D[登录后获取菜单权限]
    D --> E[登录后获取按钮权限]
    E --> F[前端页面和按钮自动控制]
    F --> G[后端 API 强制校验]
    G --> H[操作日志记录]
    H --> I[形成产品级权限体系]

    I --> J[支持不同岗位]
    J --> K[客服]
    J --> L[仓库]
    J --> M[质检]
    J --> N[报价]
    J --> O[财务]
    J --> P[交付]
    J --> Q[管理员]
```

---

## 15. 一句话总结

```mermaid
flowchart LR
    A[权限定义 set] --> B[角色授权 set]
    B --> C[登录获取 get]
    C --> D[前端按钮控制 use]
    D --> E[后端接口校验 enforce]
    E --> F[操作日志 audit]

    F --> G[完整权限闭环]
```

---

## 16. 实现落地清单

| 阶段 | 要做的事 | 说明 |
| --- | --- | --- |
| 1 | 梳理插件所有页面和按钮 | 订单、质检、报价、物流、财务、服务能力、打印 |
| 2 | 标准化 `menu_key` | 避免命名混乱和重复 |
| 3 | 补齐 `menu_type = 2` 按钮权限 | 每个按钮都要对应 `api_url + methods` |
| 4 | 确认插件菜单同步到 `sys_menu` | 安装/升级/同步时写入 |
| 5 | 确认角色管理能勾选按钮权限 | 按钮权限 `is_show = 0`，但授权树可见 |
| 6 | 后端返回按钮权限集合 | 登录或菜单接口返回 `authRules` |
| 7 | 前端统一封装按钮权限组件 | `AuthButton` / `v-auth` / `usePermission` |
| 8 | 页面替换手写按钮判断 | 所有按钮统一走权限组件 |
| 9 | 后端 `AdminCheckRole` 强制校验 | 防止绕过前端直接调接口 |
| 10 | 关键操作写日志 | 订单、质检、报价、打款、配置修改 |

---

## 17. 当前插件要重点检查的位置

| 位置 | 要检查什么 |
| --- | --- |
| `niucloud/addon/recycle/app/dict/menu/site.php` | 是否所有按钮权限都定义了 `menu_type = 2` |
| `niucloud/addon/recycle/app/adminapi/route/route.php` | 路由是否都挂了 `AdminCheckRole` |
| `niucloud/app/service/admin/auth/AuthService.php` | 是否能根据 API 路径和 method 匹配权限 |
| `admin/src/router/routers.ts` | 是否已经提取 `route.auth` |
| `admin/src/addon/recycle/views/**` | 页面按钮是否使用统一权限判断 |
| 角色管理页面 | 是否能勾选插件按钮权限 |

---

## 18. 建议后续文档

后续可以再单独拆两份文档：

| 文档 | 内容 |
| --- | --- |
| `recycle-2.0-permission-keys.md` | 回收插件所有页面和按钮权限 key 清单 |
| `recycle-2.0-role-matrix.md` | 客服、仓库、质检、报价、财务、交付、管理员权限矩阵 |
