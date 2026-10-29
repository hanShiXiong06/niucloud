// Isolated real DeviceEntryCard + Element Plus menu preview. No API, DB or business writes.
// node niucloud/addon/hsx_recycle/scripts/preview_device_entry_menu.cjs
const fs = require('node:fs')
const path = require('node:path')
const assert = require('node:assert/strict')
const { createRequire } = require('node:module')
const root = path.resolve(__dirname, '../../../../admin')
const local = createRequire(path.join(root, 'package.json'))
const { createServer } = local('vite')
const vue = local('@vitejs/plugin-vue').default
const filename = path.join(root, '__device-menu-preview__.vue')
const relative = 'components/device-entry/DeviceEntryCard.vue'
assert.equal(fs.readFileSync(path.join(root, 'src/addon/hsx_recycle', relative), 'utf8'),
    fs.readFileSync(path.join(root, '../niucloud/addon/hsx_recycle/admin', relative), 'utf8'))
const component = `<template><main>
  <h1>设备更多菜单 · 隔离交互验证</h1><p>仅使用虚拟设备，点击删除只记事件，不调用任何业务接口。</p>
  <div class="test-result" role="status">删除事件：{{ removes }} 次</div>
  <section v-for="(item,index) in samples" :key="index">
    <h2>{{ item.title }}</h2>
    <DeviceEntryCard :device="item.device" :index="index" :can-remove="item.canRemove" :quote-users="[]" @remove="removes++">
      <template #model><el-input model-value="苹果 iPhone 17 Pro Max" readonly size="small" /></template>
    </DeviceEntryCard>
  </section>
  <el-button class="outside-button">菜单外部区域</el-button>
  <p>预期：第一行鼠标移入删除菜单后可以停留并点击；第二、三行删除禁用。也可以聚焦更多按钮后按 Enter/↓ 打开，再用键盘操作。</p>
</main></template><script setup>
import {ref,reactive} from 'vue'
import DeviceEntryCard from '/src/addon/hsx_recycle/components/device-entry/DeviceEntryCard.vue'
const removes=ref(0)
const make=(imei,saving=false)=>({imei,model:'苹果 iPhone 17 Pro Max',initial_price:6000,saved:true,saving,summary_fields:[],summary_values:{}})
const samples=reactive([
 {title:'可删除设备',canRemove:true,device:make('353938205192374')},
 {title:'仅剩一台（禁止删除）',canRemove:false,device:make('353938205192375')},
 {title:'保存中（禁止删除）',canRemove:true,device:make('353938205192376',true)}
])
</script>`
let server
async function main() {
    server = await createServer({
        root, configFile: false, publicDir: false,
        cacheDir: path.join(root, 'node_modules/.vite-preview/device-menu'),
        css: { postcss: { plugins: [] } },
        server: { host: '127.0.0.1', port: 5223, strictPort: true },
        resolve: { alias: [{ find: '@/', replacement: path.join(root, 'src') + '/' }] },
        plugins: [{name:'device-menu-preview',enforce:'pre',
            resolveId(id) {
                if(id==='virtual:device-menu.vue'||id==='/__device-menu-preview__.vue')return filename
                if(id.startsWith('/__device-menu-preview__.vue?'))return filename+id.slice('/__device-menu-preview__.vue'.length)
            },
            load(id){if(id===filename)return component},
            configureServer(server){server.middlewares.use(async(req,res,next)=>{
                if(req.url.split('?')[0]!=='/__device-menu')return next()
                res.setHeader('Content-Type','text/html; charset=utf-8')
                res.end(await server.transformIndexHtml(req.url,`<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Device menu test</title><style>body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"PingFang SC",sans-serif;background:#f5f6f8;color:#303133}*{box-sizing:border-box}main{max-width:1120px;margin:24px auto;padding:24px;background:white}h1{font-size:20px}h2{font-size:14px;margin:24px 0 10px}p{font-size:13px;line-height:1.7;color:#606266}section{margin-bottom:20px}.test-result{padding:12px;background:#ecf5ff;color:#409eff}.outside-button{margin-top:20px}</style></head><body><div id="app"></div><script type="module">import {createApp} from 'vue';import ElementPlus from 'element-plus';import 'element-plus/dist/index.css';import Preview from 'virtual:device-menu.vue';const app=createApp(Preview);app.component('upload-image',{render:()=>null});app.use(ElementPlus).mount('#app')</script></body></html>`))
            })}
        },vue()],
        optimizeDeps:{entries:[],include:['vue','element-plus','@element-plus/icons-vue']}
    })
    await server.listen()
    console.log('Ready: http://127.0.0.1:5223/__device-menu (fixture only)')
}
for(const signal of ['SIGINT','SIGTERM'])process.on(signal,async()=>{await server?.close();process.exit(0)})
main().catch(error=>{console.error(error);process.exitCode=1})
