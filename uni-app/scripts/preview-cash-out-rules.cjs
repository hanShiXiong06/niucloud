// Actual withdrawal-rule template + styles with read-only fixtures. No business API imports.
const fs = require('node:fs')
const path = require('node:path')
const { createRequire } = require('node:module')
const root = path.resolve(__dirname, '..')
const local = createRequire(path.join(root, 'package.json'))
const { createServer } = local('vite')
const vue = local('@vitejs/plugin-vue').default
const sfc = local('@vue/compiler-sfc')
const virtual = path.join(root, '__cash-out-rules-preview__.vue')
let server
function previewSource() {
    const source = fs.readFileSync(path.join(root, 'src/app/pages/member/apply_cash_out.vue'), 'utf8')
    const { descriptor, errors } = sfc.parse(source)
    if (errors.length) throw errors[0]
    const start = descriptor.template.content.indexOf('<view class="mt-[20rpx] card-template cash-out-rules">')
    const end = descriptor.template.content.indexOf('<view class="tab-bar-placeholder">', start)
    if (start < 0 || end < 0) throw Error('Actual cash-out rule section not found')
    const template = descriptor.template.content.slice(start, end)
    // A 375px phone preview uses the same rpx scale as a 750rpx design width.
    const styles = descriptor.styles.map(style => style.content.replace(/([\d.]+)rpx/g, (_, value) => `${Number(value) / 2}px`)).join('\n')
    return `<template><main>
      <aside class="preview-note">规则区隔离预览 · 不发起提现、不访问业务接口</aside>
      <div class="preview-tools"><button @click="config.min='0.30';config.rate='0';config.transfer_type=['wechatpay'];config.is_auto_verify=1">样本：0.30元 / 免手续费 / 微信</button><button @click="config.min='50';config.rate='2.5';config.transfer_type=['bank'];config.is_auto_verify=0">样本：50元 / 2.5% / 银行卡</button></div>
      <div class="phone-preview">${template}</div>
    </main></template><script setup>
    import {computed,reactive} from 'vue'
    import {buildCashOutRules} from '/src/app/utils/cash-out-rules.ts'
    const config=reactive({min:'0.30',rate:'0',is_auto_verify:1,transfer_type:['wechatpay']})
    const minimumCashOutText=computed(()=>Number(config.min)>0?'¥'+Number(config.min).toFixed(2):'未设置额外最低金额')
    const cashOutRules=computed(()=>buildCashOutRules({min:config.min,rate:config.rate,isAutoVerify:config.is_auto_verify,transferTypes:config.transfer_type,accountType:'money'}))
    const redirect=()=>{}
    </script><style lang="scss" scoped>${styles}</style>`
}
async function main() {
    server = await createServer({
        root, configFile: false, publicDir: false,
        cacheDir: path.join(root, 'node_modules/.vite-preview/cash-out-rules'),
        resolve: { alias: [{ find: '@/', replacement: path.join(root, 'src') + '/' }] },
        css: { postcss: { plugins: [] } },
        server: { host: '127.0.0.1', port: 5232, strictPort: true },
        plugins: [{name:'cash-out-rules-preview',enforce:'pre',
            resolveId(id) {
                if(id==='virtual:cash-out-rules.vue'||id==='/__cash-out-rules-preview__.vue') return virtual
                if(id.startsWith('/__cash-out-rules-preview__.vue?')) return virtual+id.slice('/__cash-out-rules-preview__.vue'.length)
            },
            load(id) { if(id===virtual) return previewSource() },
            configureServer(server) { server.middlewares.use(async(req,res,next)=>{
                if(req.url.split('?')[0]!=='/__cash-out-rules') return next()
                res.setHeader('Content-Type','text/html; charset=utf-8')
                res.end(await server.transformIndexHtml(req.url,`<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>提现规则 · 隔离预览</title><style>:root{--page-bg-color:#f6f7f9;--text-color-light6:#606266;--rounded-small:8px;--primary-color:#2656e8}*{box-sizing:border-box}body{margin:0;background:#e8ebf0;font-family:-apple-system,BlinkMacSystemFont,"PingFang SC",sans-serif;color:#333}view{display:block}text{display:inline}.preview-note{background:#fff9e8;color:#8b6500;padding:12px 16px;font-size:13px;text-align:center}.preview-tools{max-width:760px;margin:12px auto;display:flex;gap:10px;justify-content:center;padding:0 12px}.preview-tools button{border:1px solid #c8cdd6;border-radius:6px;background:white;padding:8px;font-size:12px}.phone-preview{width:375px;max-width:100%;margin:16px auto;background:#f6f7f9;padding:12px}.card-template{padding:18px;background:#fff;border-radius:12px}</style></head><body><div id="app"></div><script type="module">import{createApp,h}from'vue';import Preview from'virtual:cash-out-rules.vue';const app=createApp(Preview);app.component('nc-contact',{setup:(_,context)=>()=>h('div',context.slots.default?.())});app.mount('#app')</script></body></html>`))
            })}
        },vue({template:{compilerOptions:{isCustomElement:tag=>['view','text'].includes(tag)}}})],
        optimizeDeps: { entries: [], include: ['vue'] }
    })
    await server.listen()
    console.log('Ready: http://127.0.0.1:5232/__cash-out-rules (read-only fixture only)')
}
for(const signal of ['SIGINT','SIGTERM']) process.on(signal,async()=>{await server?.close();process.exit(0)})
main().catch(error=>{console.error(error);process.exitCode=1})
