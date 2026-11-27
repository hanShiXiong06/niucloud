<template>
    <DeskShell title="对接工作台" subtitle="业务在本站办理，博远接收已选单据。暂停同步不影响收货、付款或销售。">
        <template #extra><el-button v-if="mayRead" :loading="loading" @click="reload">刷新状态</el-button></template>
        <div class="desk-split">
            <section class="desk-card overview">
                <div class="kicker">本站连接</div>
                <template v-if="!mayRead"><h2>连接由管理员维护</h2><p class="desk-caption">日常工作请从下方原单入口进入。</p></template>
                <template v-else-if="loading"><h2 role="status">正在读取账套…</h2><el-skeleton :rows="1" animated /></template>
                <template v-else-if="error"><h2>暂时读不到连接状态</h2><p role="alert" class="desk-caption">{{ error }}</p><el-button class="desk-retry" @click="reload">重新读取</el-button></template>
                <template v-else-if="state">
                    <div class="headline"><h2>{{ headline }}</h2><HsxTag v-if="step !== 'setup'" :tone="step === 'verified' ? 'success' : 'warning'" dot>{{ step === 'verified' ? '核验通过' : step === 'legacy' ? '兼容网关' : '待核验' }}</HsxTag></div>
                    <p class="desk-caption">{{ advice }}</p>
                    <el-button v-if="step === 'verified' && has(routes.catalog)" type="primary" @click="go(routes.catalog)">查看基础资料</el-button>
                    <el-button v-if="step === 'verified' && has(routes.opening)" link type="primary" @click="go(routes.opening)">去期初建账</el-button>
                    <el-button v-else-if="step !== 'verified'" type="primary" @click="go(routes.connection)">{{ step === 'setup' && state.can_edit ? '填写连接信息' : step === 'verify' && state.can_probe ? '去核验账套' : '查看连接设置' }}</el-button>
                    <el-button v-if="step === 'verified'" link type="primary" @click="go(routes.connection)">查看连接设置</el-button>
                </template>
            </section>
            <aside v-if="mayRead && step !== 'verified' && state?.can_edit" class="desk-card"><h2>首次接入</h2>
                <ol class="steps"><li><b>1</b><span>填写连接信息<small>服务地址、账套代码和应用凭据</small></span></li><li><b>2</b><span>核验账套<small>确认博远返回的是本站账套</small></span></li><li><b>3</b><span>准备基础资料<small>按业务选择仓库、往来单位和商品等</small></span></li></ol>
            </aside>
            <aside v-else-if="step === 'verified'" class="desk-card"><h2>当前账套</h2><dl class="facts"><dt>连接环境</dt><dd>{{ envName(state?.mode || 'mock') }}</dd><dt>公司代码</dt><dd>{{ profile?.account_id }}</dd><dt>单据同步</dt><dd>{{ state?.dispatching ? '已开启' : '未投递' }}</dd></dl></aside>
        </div>
        <ExchangeCenter v-if="state?.exchange_views?.length && (!has('hsx_boyuan_logs') || route.query.purchase_no)" :key="state.active_key + String(route.query.purchase_no || '')" :views="state.exchange_views" :initial="String(route.query.purchase_no || '')" />
        <section class="desk-card daily"><h2>日常业务</h2><p class="desk-caption">按岗位进入。设备只录一次，财务按台结算。</p>
            <div v-if="entries.length" class="entries">
                <router-link v-for="item in entries" :key="item.name" :to="{name:item.name}" class="entry">
                    <el-icon :size="20" aria-hidden="true"><component :is="item.icon" /></el-icon>
                    <div><strong>{{ item.label }}</strong><small>{{ item.help }}</small></div><span aria-hidden="true">›</span>
                </router-link>
            </div><p v-else class="desk-caption">本账号没有单据菜单权限。如需处理入出库，请联系站点管理员授权。</p>
            <nav v-if="records.length" class="record-links" aria-label="核对原单"><small>查原单与回执</small><router-link v-for="item in records" :key="item.name" :to="{name:item.name}">{{ item.label }}</router-link></nav>
        </section>
    </DeskShell>
</template>
<script setup lang="ts">
import {computed, onMounted, onActivated, onDeactivated, onBeforeUnmount, ref} from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ExchangeCenter from '../components/ExchangeCenter.vue'
import { Box, SoldOut, Tickets } from '@element-plus/icons-vue'
import DeskShell from '../components/DeskShell.vue'
import HsxTag from '@/addon/hsx_components/components/HsxTag/index.vue'
import { readConnection, type ConnectionState } from '../api/connection'
import { routes, connectionStep, decodeState, envName, reason } from '../utils/connection'
const router=useRouter(), has=(name:string)=>router.hasRoute(name), go=(name:string)=>{if(has(name)) void router.push({name})}
const route=useRoute()
const mayRead=computed(()=>has(routes.connection)), state=ref<ConnectionState|null>(null), error=ref(''), loading=ref(false)
const step=computed(()=>state.value ? connectionStep(state.value) : '')
const profile=computed(()=>state.value && state.value.mode !== 'mock' ? state.value.profiles[state.value.mode] : null)
const headline=computed(()=>step.value === 'setup' ? '连接你的博远账套' : step.value === 'legacy' ? '本站使用兼容网关' : '账套 '+profile.value?.account_id)
const advice=computed(()=>step.value === 'setup' ? '准备好博远提供的公司代码、AppID 和 AppSecret，就可以开始。' : step.value === 'legacy' ? '当前连接保持不变，原单和历史记录仍按原流程查看。' : envName(state.value!.mode)+' · '+(step.value === 'verify' ? '资料已保存，还需要核验账套身份。' : state.value!.dispatching ? '单据同步已开启。' : '单据同步未投递，可继续准备资料。'))
const entries=computed(()=>[
    {name:'hsx_boyuan_logs',label:'业务往来',help:'查采购、付款凭证和每次接口的入参、出参',icon:Tickets},
    {name:'recycle_order_list',label:'回收录单',help:'录单员 · 核对型号、串号和报价',icon:Box},
    {name:'hsx_erp_payable',label:'按设备付款',help:'财务 · 按原采购应付登记打款',icon:Tickets},
    {name:'shop_cashier',label:'商城交付',help:'销售 · 选买家和设备，现结或挂账',icon:SoldOut},
    {name:'hsx_erp_receivable',label:'收回挂账',help:'财务 · 按销售应收登记实际收款',icon:Tickets}
].filter(i=>has(i.name)))
const records=computed(()=>[
    {name:routes.purchase,label:'采购入库单',help:'核对入库串号，查看博远单号',icon:Box},
    {name:routes.sale,label:'销售出库单',help:'查看实际经办人、设备和结算',icon:SoldOut},
    {name:routes.records,label:'出库核对记录',help:'只查博远回执，不重复出库',icon:Tickets}
].filter(i=>has(i.name)))
let seq=0, inactive=false
async function reload(){if(!mayRead.value) return; const request=++seq; loading.value=true; error.value=''; state.value=null
    try {const s=decodeState(await readConnection());if(request===seq) state.value=s}
    catch(e){if(request===seq) error.value=reason(e,'请检查网络或连接查看权限。')}
    finally{if(request===seq) loading.value=false}}
onMounted(reload); onDeactivated(()=>{inactive=true;seq++}); onActivated(()=>{if(inactive){inactive=false;void reload()}}); onBeforeUnmount(()=>{seq++})
</script>
<style scoped>
.record-links{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:16px}.record-links small{color:var(--el-text-color-secondary)}.record-links a{color:var(--el-color-primary);font-size:13px}
.overview{border-top:3px solid var(--el-color-primary)!important;align-self:stretch;display:flex;align-items:flex-start;justify-content:center;flex-direction:column}
.kicker{font-size:12px;letter-spacing:.08em;color:var(--el-color-primary);font-weight:650;margin-bottom:10px}.overview .headline{display:flex;align-items:center;gap:12px;flex-wrap:wrap}.overview h2{font-size:24px!important}.overview .el-button{margin:16px 0 0}
.steps{list-style:none;margin:15px 0 0;padding:0}.steps li{display:flex;gap:10px;align-items:flex-start;font-size:14px;margin:0 0 12px}.steps li:last-child{margin:0}.steps b{border-radius:50%;background:var(--el-color-primary-light-9);color:var(--el-color-primary);font-size:12px;width:22px;height:22px;line-height:22px;text-align:center}
.steps small,.entry small{display:block;font-size:12px;color:var(--el-text-color-secondary);margin-top:3px;line-height:1.5}.daily{margin-top:16px}.entries{display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:12px;margin-top:16px}.entry{display:flex;align-items:center;gap:12px;padding:16px;border:1px solid var(--el-border-color-light);border-radius:7px;text-decoration:none;color:var(--el-text-color-primary)}.entry:hover,.entry:focus-visible{border-color:var(--el-color-primary);background:var(--el-color-primary-light-9)}.entry .el-icon{color:var(--el-color-primary)}.entry strong{font-size:15px}.entry>span{margin-left:auto;color:var(--el-text-color-secondary);font-size:20px}
.overview:only-child{grid-column:1/-1}.facts{display:grid;grid-template-columns:auto 1fr;gap:9px 14px;margin:16px 0 0;font-size:13px}.facts dt{color:var(--el-text-color-secondary)}.facts dd{margin:0}
</style>
