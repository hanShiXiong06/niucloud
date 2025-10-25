import { createApp } from 'vue'
import App from './App.vue'
import roter from './router'
import ElementPlus from 'element-plus'
import pinia from './stores'
import lang from './lang'
import directives from './utils/directives'
import '@/styles/index.scss'
import { useElementIcon } from './utils/common'
import 'highlight.js/styles/stackoverflow-light.css';
import hljs from 'highlight.js/lib/common'
import hljsVuePlugin from '@highlightjs/vue-plugin'
import VueUeditorWrap from 'vue-ueditor-wrap'
import '@/addon/hsx_clipboard_upload/static/global-clipboard.js'
import '@/addon/hsx_clipboard_upload/static/global-drag-upload.js'

window.hl = hljs

async function run() {
    const app = createApp(App)
    app.use(pinia)
    app.use(lang)
    app.use(roter)
    app.use(directives)
    app.use(ElementPlus)
    app.use(hljsVuePlugin)
    app.use(VueUeditorWrap)
    useElementIcon(app)
    app.mount('#app')
}

run()
