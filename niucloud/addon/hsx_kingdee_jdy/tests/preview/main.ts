import { createApp, h } from 'vue'
import { createRouter, createWebHashHistory } from 'vue-router'
import ElementPlus from 'element-plus'
import 'element-plus/dist/index.css'
import Workbench from '../../admin/views/workbench/index.vue'
import './preview.css'

const router = createRouter({ history: createWebHashHistory(), routes: [{ path: '/:pathMatch(.*)*', component: Workbench }] })
const app = createApp({ render: () => h('div', [h('div', { class: 'fixture-banner' }, '本地模拟验收 · 仅展示虚构任务，不连接精斗云，不保存任何真实授权'), h(Workbench)]) })
app.use(router).use(ElementPlus).mount('#app')
