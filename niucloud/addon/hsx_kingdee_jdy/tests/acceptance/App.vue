<template>
    <div class="acceptance-app">
        <header class="environment-bar"><div><el-icon><Monitor /></el-icon><strong>精斗云本地验收台</strong></div><el-tag effect="plain" type="warning">外部服务模拟 · 禁止正式业务</el-tag></header>
        <nav><el-radio-group v-model="view" @change="refresh"><el-radio-button label="cases">ERP业务演练</el-radio-button><el-radio-button label="workbench">精斗云工作台</el-radio-button><el-radio-button label="notices">模拟提醒</el-radio-button><el-radio-button label="guide">验收说明</el-radio-button></el-radio-group><el-button :icon="Refresh" circle aria-label="刷新验收数据" :loading="busy" @click="refresh" /></nav>
        <el-alert v-if="error" :title="error" type="error" show-icon :closable="false" />
        <section v-if="view === 'cases'" class="cases-view" v-loading="busy">
            <div class="section-heading"><h1>ERP业务演练</h1><el-button :icon="Plus" type="primary" :disabled="busy" @click="newCase">新建验收轮次</el-button></div>
            <div class="case-selector"><el-select v-model="selected" placeholder="选择验收轮次"><el-option v-for="item in summary.cases" :key="item.id" :label="`#${item.id} ${item.label}`" :value="Number(item.id)" /></el-select><el-tag type="info">独立数据库，刷新后保留</el-tag></div>
            <template v-if="current">
                <div class="metrics"><div><span>本轮资金余额</span><strong>¥{{ money(current.balance) }}</strong></div><div><span>已完成步骤</span><strong>{{ current.step }} / 8</strong></div><div><span>设备状态</span><strong class="asset-state">{{ assetStates[current.asset_status] || current.asset_status || '尚未采购' }}</strong></div></div>
                <div class="progress"><el-steps :active="Number(current.step)" finish-status="success" simple><el-step title="采购" /><el-step title="付款" /><el-step title="销售" /><el-step title="收款" /><el-step title="销退" /><el-step title="退款" /><el-step title="采退" /><el-step title="收退款" /></el-steps></div>
                <div class="actions"><el-button v-if="current.step < 8" type="primary" :icon="VideoPlay" :loading="busy" @click="advance(Number(current.step))">{{ summary.steps?.[current.step] }}</el-button><el-tag v-else type="success" size="large">本轮内部业务闭环完成</el-tag><el-button v-if="current.step > 0" :icon="Refresh" :disabled="busy" @click="advance(Number(current.step) - 1)">重复提交上一步</el-button><el-button :icon="Connection" @click="view = 'workbench'">查看精斗云任务</el-button></div>
                <el-descriptions :column="1" border><el-descriptions-item label="采购单号">{{ current.purchase_no || '尚未生成' }}</el-descriptions-item><el-descriptions-item label="销售单号">{{ current.sale_no || '尚未生成' }}</el-descriptions-item><el-descriptions-item label="设备ERP记录ID">{{ current.data?.asset || '尚未生成' }}</el-descriptions-item><el-descriptions-item label="远端单据">未写入精斗云</el-descriptions-item></el-descriptions>
                <div v-if="current.data?.asset" class="mapping-line"><span>商品映射</span><code>设备商品 / {{ current.data.asset }} / LOCAL-PHONE-{{ current.data.asset }} / 单位ID 1</code><el-button :icon="Link" :disabled="busy" @click="mapAsset">补齐本轮商品映射</el-button></div>
                <h2>本轮业务记录</h2><el-table :data="current.history" empty-text="点击采购入库开始本轮验收"><el-table-column label="步骤" width="70"><template #default="{ row }">{{ row.step + 1 }}</template></el-table-column><el-table-column prop="name" label="业务动作" min-width="160" /><el-table-column label="内部资金余额" min-width="150"><template #default="{ row }">¥{{ money(row.balance) }}</template></el-table-column><el-table-column label="时间" min-width="180"><template #default="{ row }">{{ date(row.at) }}</template></el-table-column></el-table>
            </template>
        </section>
        <div v-else-if="view === 'workbench'" class="workbench-wrap"><div class="fault-bar"><span>授权响应演练</span><el-select :model-value="summary.fault" :disabled="busy" @change="setFault"><el-option label="正常模拟响应" value="none" /><el-option label="授权失效" value="auth" /><el-option label="网络超时" value="timeout" /></el-select><el-tag type="info" effect="plain">仅影响本地模拟授权检查</el-tag></div><Workbench /></div>
        <section v-else-if="view === 'notices'" class="cases-view"><h1>模拟提醒记录</h1><el-alert title="以下记录来自本地事件接收器，没有发送企业微信，也不代表员工已读。" type="info" show-icon :closable="false" /><el-table :data="notices" empty-text="在精斗云待处理任务中点击提醒责任人"><el-table-column prop="task_id" label="任务ID" width="100" /><el-table-column prop="title" label="提醒标题" min-width="170" /><el-table-column prop="business_no" label="业务单号" min-width="240" /><el-table-column label="时间" min-width="180"><template #default="{ row }">{{ date(row.create_at) }}</template></el-table-column></el-table></section>
        <section v-else class="cases-view guide"><h1>本地验收说明</h1><p>本环境使用真实ERP业务服务、精斗云插件控制器与隔离MySQL数据库。外部金蝶响应和企业微信接收器采用模拟，不访问真实账套。</p><ol><li>选择“我的首次验收”，按顺序点击八个业务动作。</li><li>采购价4500元、售价5000元，初始资金20000元。完成两类退货退款后，余额应回到20000元。</li><li>点击“重复提交上一步”，应提示已完成，资金和业务记录不应重复。</li><li>补齐本轮商品映射，进入精斗云工作台，找到采购或销售任务并重新预检，应变为“已预检，未写单”。</li><li>退货和资金任务仍需人工核对。可测试提醒责任人、人工结案和操作日志；结案不会改变真实精斗云数据。</li><li>在工作台选择授权失效或网络超时，再到授权配置检查已保存授权，查看错误提示与日志。切回正常模拟响应后可恢复测试。</li></ol><h2>环境边界</h2><p>账号与模拟密钥已经配置，不需要登录，不要输入真实密钥。本地访问自动使用隔离站点的“本地验收员”，不验证正式后台登录或角色分配流程。</p><p>安装入口已经通过安装、重复升级及卸载回归。正式写单、自动授权更新、远端退货核销、真实企业微信送达均不在本次通过范围。</p><p>数据库：<code>{{ summary.database }}</code></p><p>计划任务已注册：{{ summary.schedule_count }} 项。本环境每次业务操作后主动处理事件；未启用现有业务环境的全局定时器。</p></section>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Connection, Link, Monitor, Plus, Refresh, VideoPlay } from '@element-plus/icons-vue'
import Workbench from '../../admin/views/workbench/index.vue'
import request from './request'
import * as api from '../../admin/api'
const view = ref('cases'), busy = ref(false), error = ref(''), selected = ref(0), summary = ref<any>({ cases: [] }), notices = ref<any[]>([])
const current = computed(() => summary.value.cases.find((item: any) => Number(item.id) === selected.value))
const assetStates: Record<string, string> = { in_stock: '在库', sold: '已售出', returned: '已退供应商' }
const money = (n: any) => Number(n || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const date = (n: number) => new Date(n * 1000).toLocaleString('zh-CN', { hour12: false })
async function load() { summary.value = (await request.get('acceptance/summary')).data; if (!selected.value) selected.value = Number(summary.value.cases[0]?.id || 0); if (view.value === 'notices') notices.value = (await request.get('acceptance/notices')).data }
async function action(run: () => Promise<void>) { if (busy.value) return; busy.value = true; error.value = ''; try { await run(); await load() } catch (e) { error.value = e instanceof Error ? e.message : '操作未完成' } finally { busy.value = false } }
const refresh = () => action(async () => {})
const newCase = () => action(async () => { selected.value = Number((await request.post('acceptance/cases')).data.id) })
const advance = (step: number) => action(async () => { const r = await request.post(`acceptance/cases/${selected.value}/step`, { step }); ElMessage.success(r.data.message) })
const setFault = (fault: string) => action(async () => { await request.post('acceptance/fault', { fault }); ElMessage.success('模拟响应已切换') })
const mapAsset = () => action(async () => { const id = Number(current.value.data.asset); await api.saveMapping({ kind: 'asset_material', local_id: id, external_number: `LOCAL-PHONE-${id}`, unit_id: '1', remark: '本地模拟商品，未远端验证' }); ElMessage.success('映射已保存，请到工作台重新预检相关任务') })
onMounted(refresh)
</script>

<style>
* { box-sizing: border-box; } body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'PingFang SC', sans-serif; color: #20252d; background: #f4f6f8; letter-spacing: 0; }
.acceptance-app { max-width: 1440px; margin: 0 auto; } .environment-bar { padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; background: #fff; border-bottom: 1px solid #e4e7ed; }
.environment-bar div { display: flex; gap: 10px; align-items: center; } nav { display: flex; gap: 12px; justify-content: space-between; padding: 18px 24px; flex-wrap: wrap; }
.cases-view { background: #fff; padding: 24px; min-height: 600px; } .section-heading { display: flex; gap: 16px; justify-content: space-between; align-items: center; margin-bottom: 24px; } h1 { margin: 0; font-size: 22px; } h2 { margin: 28px 0 16px; font-size: 16px; }
.case-selector, .actions, .mapping-line, .fault-bar { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; } .case-selector .el-select { width: 320px; } .cases-view .metrics { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); padding: 28px 0; gap: 20px; } .cases-view .metrics div { display: flex; flex-direction: column; gap: 12px; } .cases-view .metrics span { color: #737d89; font-size: 14px; } .cases-view .metrics strong { font-size: 28px; font-weight: 600; } .cases-view .metrics .asset-state { font-size: 22px; }
.progress { overflow-x: auto; } .progress .el-steps { min-width: 860px; } .actions { padding: 24px 0; } .mapping-line { padding: 20px 0; border-bottom: 1px solid #ebeef5; } code { overflow-wrap: anywhere; font-size: 13px; color: #596579; } .fault-bar { padding: 16px 24px; background: #edf5ff; } .fault-bar .el-select { width: 180px; } .guide { max-width: 900px; } .guide p, .guide li { line-height: 1.9; } .guide li { margin: 10px 0; } .guide ol { padding-left: 22px; }
@media(max-width:720px) { .environment-bar { align-items: flex-start; flex-direction: column; } .cases-view { padding: 16px; } nav { padding: 14px 16px; } nav .el-radio-group { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; } .cases-view .metrics { grid-template-columns: 1fr 1fr; } .section-heading { align-items: flex-start; flex-wrap: wrap; } .case-selector .el-select { width: 100%; } .el-descriptions__label { white-space: nowrap; } }
</style>
