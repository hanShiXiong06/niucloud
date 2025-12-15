<template>
  <div class="clipboard-upload-container">
    <h1 class="text-4xl">剪贴板上传插件使用说明</h1>
    
    <div class="usage-docs">
      <h2>📋 插件功能说明</h2>
      <p>此插件提供以下功能：</p>
      <ul>
        <li>✅ 剪贴板图片上传（Ctrl+V 或右键粘贴）</li>
        <li>✅ 拖拽文件上传</li>
        <li>✅ 多文件同时上传</li>
        <li>✅ 实时上传进度显示</li>
      </ul>

      <h2>🎯 使用方式</h2>
      
      <h3>1. 剪贴板上传</h3>
      <ol>
        <li>复制图片到剪贴板（截图或复制图片文件）</li>
        <li>在编辑器区域按 <kbd>Ctrl+V</kbd> 或右键选择"粘贴"</li>
        <li>图片将自动上传到服务器并插入到编辑器中</li>
      </ol>

      <h3>2. 拖拽上传</h3>
      <ol>
        <li>从本地文件夹选择要上传的文件</li>
        <li>拖拽文件到编辑器区域</li>
        <li>文件将自动上传并插入到编辑器中</li>
      </ol>

      <h2>⚙️ 配置说明</h2>
      <p>插件会自动加载以下配置文件：</p>
      <p>前提是: 进入系统后需要先访问这个插件</p>
      <ul>
        <li><code>global-clipboard.js</code> - 剪贴板上传核心功能</li>
        <li><code>global-drag-upload.js</code> - 拖拽上传核心功能</li>
      </ul>

      <h2>🔧 技术实现</h2>
      <p>如果想引入到框架 请自己添加到 main.ts文件中 可以解决每次刷新后访问插件才能生效问题 代码如下 ：</p>
      <pre><code>// 剪贴板上传功能
import "@/addon/hsx_clipboard_upload/static/global-clipboard.js";

// 拖拽上传功能  
import "@/addon/hsx_clipboard_upload/static/global-drag-upload.js";</code></pre>

      <h2>📝 注意事项</h2>
      <ul>
        <li>支持常见图片格式：JPG、PNG、GIF、WebP</li>
        <li>单文件大小限制：10MB</li>
        <li>支持批量上传，最多5个文件同时上传</li>
        <li>上传过程中请勿关闭页面</li>
        <li>上传失败会自动重试3次</li>
      </ul>

      <h2>🐛 常见问题</h2>
      <details>
        <summary><strong>上传失败怎么办？</strong></summary>
        <p>检查网络连接，确认文件格式和大小是否符合要求，刷新页面重试。</p>
      </details>
      
      <details>
        <summary><strong>如何查看上传进度？</strong></summary>
        <p>上传过程中会显示实时进度条，包括上传百分比和剩余时间。</p>
      </details>
    </div>
  </div>
</template>

<script setup lang="ts">
// 导入剪贴板上传插件核心文件
import "@/addon/hsx_clipboard_upload/static/global-clipboard.js";
import "@/addon/hsx_clipboard_upload/static/global-drag-upload.js";
</script>

<style scoped lang="scss">
.clipboard-upload-container {
  //max-width: 800px;
  margin: 0 auto;
  padding: 20px;
  
  h1 {
    color: #303133;
    margin-bottom: 30px;
    text-align: center;
  }
  
  .usage-docs {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 30px;
    
    h2 {
      color: #409eff;
      margin: 30px 0 15px 0;
      font-size: 20px;
      
      &:first-child {
        margin-top: 0;
      }
    }
    
    h3 {
      color: #606266;
      margin: 20px 0 10px 0;
      font-size: 16px;
    }
    
    ul, ol {
      margin: 10px 0;
      padding-left: 25px;
      
      li {
        margin: 8px 0;
        color: #606266;
        line-height: 1.6;
      }
    }
    
    kbd {
      background: #e9ecef;
      border: 1px solid #adb5bd;
      border-radius: 3px;
      padding: 2px 6px;
      font-family: monospace;
      font-size: 0.9em;
    }
    
    code {
      background: #f1f3f4;
      padding: 2px 6px;
      border-radius: 3px;
      font-family: 'Monaco', 'Consolas', monospace;
      font-size: 0.9em;
    }
    
    pre {
      background: #f1f3f4;
      border: 1px solid #e1e4e8;
      border-radius: 6px;
      padding: 15px;
      overflow-x: auto;
      
      code {
        background: none;
        padding: 0;
      }
    }
    
    details {
      margin: 15px 0;
      border: 1px solid #e1e4e8;
      border-radius: 6px;
      padding: 15px;
      
      summary {
        cursor: pointer;
        font-weight: bold;
        color: #409eff;
      }
      
      p {
        margin: 10px 0 0 0;
        color: #606266;
        line-height: 1.6;
      }
    }
  }
}
</style>