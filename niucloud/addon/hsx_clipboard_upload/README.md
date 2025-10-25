# 剪贴板图片上传插件

## 功能介绍

这是一个基于NiuCloud框架开发的剪贴板图片上传插件，解决了传统上传图片操作繁琐的痛点。提供了两种核心功能：**Ctrl+V粘贴上传** 和 **Ctrl+C复制检测上传**。

### 主要功能

- ✅ **全局剪贴板上传**：在系统任何页面都可以使用 Ctrl+V 快速上传
- 🆕 **Ctrl+C检测上传**：监听复制操作，自动检测并提示上传图片
- ✅ **智能检测**：自动检测剪贴板中的图片并提示上传
- ✅ **拖拽上传**：支持直接拖拽图片文件到上传区域
- ✅ **实时预览**：上传后立即显示图片预览
- ✅ **链接复制**：一键复制图片链接到剪贴板
- ✅ **上传历史**：记录本次会话的上传历史
- ✅ **进度显示**：实时显示上传进度
- ✅ **智能过滤**：自动识别输入框，避免干扰正常编辑

### 使用场景

- 快速上传截图
- 处理从其他应用复制的图片
- 批量图片管理
- 图片链接获取
- 任何页面的快速上传需求
- **智能检测复制的图片并主动提示上传**

## 安装说明

1. 将插件文件夹 `hsx_clipboard_upload` 放置到 `niucloud/addon/` 目录下
2. 在管理后台进入插件管理页面
3. 找到"剪贴板图片上传"插件并安装

## 使用方法

### 🎯 Ctrl+C 检测上传（新功能）

**创新的复制检测机制**，当用户复制图片后，自动检测并提示上传：

```html
<script src="/niucloud/addon/hsx_clipboard_upload/admin/static/ctrl-c-upload.js"></script>
```

**工作流程：**
1. 用户按 `Ctrl+C` 复制图片（截图、网页图片等）
2. 系统在500ms后自动检测剪贴板内容
3. 发现图片时弹出上传确认提示
4. 用户点击"立即上传"完成上传

**特点：**
- 🎯 **主动检测**：用户复制后自动提示，无需记住快捷键
- 🚀 **快速响应**：500ms延迟检测，体验流畅
- 💡 **智能提示**：只在检测到图片时才显示确认框
- 🔒 **安全可靠**：遵循浏览器安全策略

### 🌍 Ctrl+V 全局上传（经典功能）

在任何页面引入全局脚本，即可在该页面的任何位置使用剪贴板上传：

```html
<script src="/niucloud/addon/hsx_clipboard_upload/admin/static/global-clipboard.js"></script>
```

引入后即可：
- 在任何页面按 `Ctrl+V` 上传剪贴板图片
- 按 `Ctrl+Shift+V` 查看帮助
- 系统自动检测剪贴板内容并提示

### 📋 专用页面使用

访问插件专用页面进行上传管理：

#### 剪贴板上传

1. 打开插件页面
2. 点击上传区域使其获得焦点
3. 复制任意图片到剪贴板（如截图、从网页复制图片等）
4. 按下 `Ctrl+V` 即可自动上传

#### 拖拽上传

1. 直接将图片文件拖拽到上传区域
2. 松开鼠标即可自动上传

#### 功能操作

- **复制链接**：点击图片上的"复制链接"按钮
- **预览图片**：点击图片可在新窗口中预览
- **删除图片**：点击"删除"按钮移除上传记录
- **清空记录**：点击"清空上传记录"按钮清除所有历史

## 🔧 高级集成

### 两种模式选择

**Ctrl+C 检测模式**（主动提示）：
```html
<script src="/niucloud/addon/hsx_clipboard_upload/admin/static/ctrl-c-upload.js"></script>
```

**Ctrl+V 全局模式**（被动触发）：
```html
<script src="/niucloud/addon/hsx_clipboard_upload/admin/static/global-clipboard.js"></script>
```

**可以同时使用两种模式**，它们互不冲突！

### Vue 组件中使用

```vue
<template>
    <div class="my-component">
        <h1>我的组件</h1>
        <p>剪贴板上传功能已启用</p>
    </div>
</template>

<script>
export default {
    name: 'MyComponent',
    mounted() {
        this.loadClipboardUpload()
    },
    methods: {
        loadClipboardUpload() {
            // 加载 Ctrl+C 检测版本
            if (!window.ctrlCUploadDetector) {
                const scriptC = document.createElement('script')
                scriptC.src = '/niucloud/addon/hsx_clipboard_upload/admin/static/ctrl-c-upload.js'
                document.head.appendChild(scriptC)
            }
            
            // 可选：同时加载 Ctrl+V 全局版本
            if (!window.globalClipboardUpload) {
                const scriptV = document.createElement('script')
                scriptV.src = '/niucloud/addon/hsx_clipboard_upload/admin/static/global-clipboard.js'
                document.head.appendChild(scriptV)
            }
        }
    }
}
</script>
```

### JavaScript API

**Ctrl+C 检测 API：**
```javascript
// 全局对象
window.ctrlCUploadDetector

// 手动上传文件
window.ctrlCUploadDetector.uploadImage(file)

// 显示帮助
window.ctrlCUploadDetector.showHelp()

// 销毁实例
window.ctrlCUploadDetector.destroy()
```

**Ctrl+V 全局 API：**
```javascript
// 全局对象
window.globalClipboardUpload

// 手动上传文件
window.globalClipboardUpload.uploadImage(file)

// 复制到剪贴板
window.globalClipboardUpload.copyToClipboard('text')

// 销毁实例
window.globalClipboardUpload.destroy()
```

## 💡 插件特色

### 🌟 双模式支持

- **Ctrl+C 检测模式**：主动检测复制操作，智能提示上传
- **Ctrl+V 全局模式**：任意位置粘贴上传，传统体验
- **完美兼容**：两种模式可同时使用，互不干扰

### 🎨 用户体验

- **现代化界面**：美观的浮动提示和进度显示
- **多种快捷键**：
  - `Ctrl+C` - 复制后自动检测（新）
  - `Ctrl+V` - 粘贴上传（传统）
  - `Ctrl+Shift+V` - 显示帮助
  - `Ctrl+Shift+C` - 显示Ctrl+C帮助（新）
- **即时反馈**：上传成功后立即显示结果和操作按钮

### 🔒 技术安全

- **权限控制**：使用系统自带的上传接口和权限验证
- **智能过滤**：只在非输入环境中拦截快捷键事件
- **错误处理**：完善的错误提示和异常处理
- **浏览器兼容**：遵循现代浏览器安全策略

## 技术特点

- 纯前端实现，无需数据库
- 使用NiuCloud系统自带的上传接口
- 支持现代浏览器的剪贴板API
- 响应式设计，支持各种屏幕尺寸
- 全局监听，智能检测
- **创新的Ctrl+C检测机制**

## 兼容性

- **系统要求**：NiuCloud 1.1.0+
- **浏览器支持**：
  - Chrome 76+
  - Firefox 90+
  - Edge 79+
  - Safari 13.1+
  - 不支持 IE 浏览器
- **环境要求**：建议 HTTPS 环境（剪贴板API限制）

## 注意事项

1. **HTTPS 环境**：现代浏览器的剪贴板API需要HTTPS环境才能完全工作
2. **默认分组**：图片会上传到系统默认分组（cate_id=0）
3. **权限验证**：需要确保有上传权限
4. **会话存储**：上传历史仅在当前会话中保存
5. **智能过滤**：在输入框中不会拦截快捷键，保持正常功能
6. **延迟检测**：Ctrl+C检测有500ms延迟，确保剪贴板内容完整

## 快捷键说明

| 快捷键 | 功能 | 模式 |
|--------|------|------|
| `Ctrl+C` | 复制后自动检测并提示上传 | 检测模式 |
| `Ctrl+V` | 在非输入环境中上传剪贴板图片 | 全局模式 |
| `Ctrl+Shift+V` | 显示Ctrl+V帮助提示 | 全局模式 |
| `Ctrl+Shift+C` | 显示Ctrl+C帮助提示 | 检测模式 |

## 更新日志

### v1.0.0
- 初始版本发布
- 支持剪贴板上传
- 支持拖拽上传
- 基础的图片管理功能
- **新增全局剪贴板上传功能**
- **新增智能检测和提示**
- **新增JavaScript API**
- **新增多框架集成示例**
- 🆕 **新增Ctrl+C检测上传功能**
- 🆕 **新增主动检测和确认机制**
- 🆕 **新增双模式支持**

## 目录结构

```
hsx_clipboard_upload/
├── info.json                                    # 插件信息
├── Addon.php                                   # 插件主文件
├── README.md                                   # 说明文档
├── admin/
│   ├── lang/zh-cn/menu.php                    # 菜单语言
│   ├── static/
│   │   ├── global-clipboard.js                # 全局剪贴板脚本（Ctrl+V）
│   │   └── ctrl-c-upload.js                   # Ctrl+C检测脚本（新）
│   └── views/
│       ├── clipboard-upload.vue               # 基础上传页面
│       ├── enhanced-upload.vue                # 强化上传页面
│       ├── global-clipboard.vue               # 全局组件（Vue版）
│       ├── global-usage.vue                   # Ctrl+V使用说明页面
│       └── ctrl-c-usage.vue                   # Ctrl+C使用说明页面（新）
```

## 技术支持

如有问题请联系开发者 hsx

---

© 2024 HSX. All rights reserved. 