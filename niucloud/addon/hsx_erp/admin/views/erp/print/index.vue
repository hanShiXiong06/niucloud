<template>
    <div class="main-container print-center">
        <el-card class="!border-none" shadow="never">
            <div class="page-head">
                <div>
                    <div class="text-page-title">打印中心</div>
                    <div class="page-subtitle">统一管理销售小票、财务凭证和设备标签。业务失败不会被打印机故障阻断，失败任务可单独重试。</div>
                </div>
                <el-button :icon="Refresh" :loading="loading" @click="loadAll">刷新</el-button>
            </div>

            <div class="overview-grid">
                <div class="overview-card blue"><el-icon><Printer /></el-icon><div><b>{{ printers.length }}</b><span>打印设备</span></div></div>
                <div class="overview-card green"><el-icon><Connection /></el-icon><div><b>{{ enabledScenes }}</b><span>已启用场景</span></div></div>
                <div class="overview-card orange"><el-icon><Warning /></el-icon><div><b>{{ failedJobs }}</b><span>待处理失败</span></div></div>
            </div>

            <el-alert class="mt-5" type="info" :closable="false" show-icon>
                <template #title>云打印机适合无人值守，但字号通常只能按倍数调整；蓝牙打印机排版更灵活，需要在手机端连接后发送。模板会按设备能力自动降级。</template>
            </el-alert>

            <el-tabs v-model="activeTab" class="mt-4" @tab-change="onTabChange">
                <el-tab-pane label="打印设备" name="printers">
                    <div class="toolbar"><div class="section-hint">先配置设备，再到“触发场景”决定何时打印。</div><el-button type="primary" :icon="Plus" @click="openPrinter()">添加打印机</el-button></div>
                    <div v-loading="loading" class="device-grid">
                        <article v-for="row in printers" :key="row.id" class="device-card" :class="{ off: !row.status }">
                            <div class="device-icon" :class="row.connection_mode"><el-icon><Printer /></el-icon></div>
                            <div class="device-main">
                                <div class="device-title"><span>{{ row.printer_name }}</span><el-tag v-if="row.is_default" size="small" type="warning">默认</el-tag><el-tag v-if="!row.status" size="small" type="info">停用</el-tag></div>
                                <div class="device-meta">{{ providerName(row.driver) }} · {{ row.print_type === 'label' ? '标签' : '小票' }} · {{ row.paper_width }}mm</div>
                                <div class="device-desc">{{ row.connection_mode === 'bluetooth' ? '手机连接后执行打印' : '云端在线发送，无需保持页面打开' }}</div>
                            </div>
                            <div class="device-actions">
                                <el-button text type="primary" @click="onTest(row)">试打</el-button>
                                <el-button text @click="openPrinter(row)">编辑</el-button>
                                <el-button text type="danger" @click="onDeletePrinter(row)">删除</el-button>
                            </div>
                        </article>
                        <button class="device-add" @click="openPrinter()"><el-icon><Plus /></el-icon><span>添加打印机</span></button>
                    </div>
                </el-tab-pane>

                <el-tab-pane label="触发场景" name="scenes">
                    <div class="section-hint mb-4">按业务动作配置是否自动打印。默认全部关闭，避免刚配置设备就意外出纸。</div>
                    <div v-loading="loading" class="scene-list">
                        <article v-for="scene in scenes" :key="scene.id" class="scene-card">
                            <div class="scene-badge" :class="scene.template_type"><el-icon><Tickets v-if="scene.template_type === 'receipt'" /><Postcard v-else /></el-icon></div>
                            <div class="scene-info"><div class="scene-title">{{ scene.scene_name }} <el-tag size="small" effect="plain">{{ granularityText(scene.granularity) }}</el-tag></div><div class="scene-desc">{{ scene.description }}</div></div>
                            <div class="scene-selects">
                                <el-select v-model="scene.printer_id" placeholder="选择打印机" clearable @change="scene.enabled = 0">
                                    <el-option v-for="p in printerOptions(scene.template_type)" :key="p.id" :label="p.printer_name" :value="p.id" />
                                </el-select>
                                <el-select v-model="scene.template_id" placeholder="选择模板" clearable @change="scene.enabled = 0">
                                    <el-option v-for="t in templates.filter((x:any) => x.print_type === scene.template_type && x.status)" :key="t.id" :label="t.template_name" :value="t.id" />
                                </el-select>
                                <el-select v-model="scene.granularity" placeholder="打印粒度">
                                    <el-option v-for="item in granularityOptions(scene)" :key="item.value" :label="item.label" :value="item.value" />
                                </el-select>
                                <div class="copies-field"><span>份数</span><el-input-number v-model="scene.copies" :min="1" :max="9" controls-position="right" /></div>
                            </div>
                            <div class="scene-switches"><div><span>启用</span><el-switch v-model="scene.enabled" :active-value="1" :inactive-value="0" /></div><div><span>自动打印</span><el-switch v-model="scene.auto_print" :active-value="1" :inactive-value="0" :disabled="!scene.enabled" /></div></div>
                            <el-button type="primary" plain :loading="scene.saving" @click="onSaveScene(scene)">保存</el-button>
                        </article>
                    </div>
                </el-tab-pane>

                <el-tab-pane label="打印模板" name="templates">
                    <div class="toolbar"><div class="section-hint">支持变量替换。云设备建议使用原生排版，复杂字体与图形可后续切换精确图像模式。</div><el-button type="primary" :icon="Plus" @click="openTemplate()">新建模板</el-button></div>
                    <el-table :data="templates" v-loading="loading" size="large">
                        <el-table-column prop="template_name" label="模板名称" min-width="190"><template #default="{ row }"><span class="font-medium">{{ row.template_name }}</span><el-tag v-if="row.is_builtin" class="ml-2" size="small" type="info">内置</el-tag></template></el-table-column>
                        <el-table-column label="类型" width="100"><template #default="{ row }"><el-tag :type="row.print_type === 'label' ? 'warning' : 'success'" effect="plain">{{ row.print_type === 'label' ? '标签' : '小票' }}</el-tag></template></el-table-column>
                        <el-table-column prop="paper_width" label="纸宽" width="90"><template #default="{ row }">{{ row.paper_width }}mm</template></el-table-column>
                        <el-table-column label="排版模式" width="120"><template #default="{ row }">{{ row.layout_mode === 'raster' ? '精确图像' : '设备原生' }}</template></el-table-column>
                        <el-table-column label="状态" width="90"><template #default="{ row }"><el-tag :type="row.status ? 'success' : 'info'" size="small">{{ row.status ? '启用' : '停用' }}</el-tag></template></el-table-column>
                        <el-table-column label="操作" width="100" fixed="right"><template #default="{ row }"><el-button text type="primary" @click="openTemplate(row)">编辑</el-button></template></el-table-column>
                    </el-table>
                </el-tab-pane>

                <el-tab-pane label="任务日志" name="jobs">
                    <div class="job-filter"><el-input v-model.trim="jobQuery.keyword" clearable placeholder="任务号 / 业务单号 / 打印机 / 错误" @keyup.enter="loadJobs" /><el-select v-model="jobQuery.status" clearable placeholder="全部状态"><el-option v-for="(name,key) in meta.status_map" :key="key" :label="name" :value="key" /></el-select><el-button type="primary" @click="loadJobs">查询</el-button></div>
                    <el-table :data="jobs" v-loading="jobsLoading" size="large" empty-text="暂无打印任务">
                        <el-table-column prop="job_no" label="任务号" min-width="180" show-overflow-tooltip />
                        <el-table-column prop="scene_name" label="场景" min-width="120" />
                        <el-table-column prop="biz_no" label="业务单号" min-width="170" show-overflow-tooltip />
                        <el-table-column prop="printer_name" label="打印机" min-width="130" show-overflow-tooltip />
                        <el-table-column label="状态" width="110"><template #default="{ row }"><el-tag :type="statusType(row.status)" size="small">{{ erpEnumLabel(row.status, meta.status_map) }}</el-tag></template></el-table-column>
                        <el-table-column prop="error_message" label="结果说明" min-width="220" show-overflow-tooltip><template #default="{ row }">{{ row.error_message || (row.status === 'success' ? '打印成功' : '-') }}</template></el-table-column>
                        <el-table-column label="时间" width="170"><template #default="{ row }">{{ formatTime(row.create_at) }}</template></el-table-column>
                        <el-table-column label="操作" width="90" fixed="right"><template #default="{ row }"><el-button v-if="['failed','waiting_client'].includes(row.status)" text type="primary" @click="onRetry(row)">重试</el-button></template></el-table-column>
                    </el-table>
                    <div class="mt-4 flex justify-end"><el-pagination v-model:current-page="jobQuery.page" v-model:page-size="jobQuery.limit" layout="total, prev, pager, next" :total="jobTotal" @current-change="loadJobs" /></div>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <el-dialog v-model="printerVisible" :title="printerForm.id ? '编辑打印机' : '添加打印机'" width="620px" destroy-on-close>
            <el-form label-width="108px">
                <el-form-item label="打印机名称" required><el-input v-model.trim="printerForm.printer_name" placeholder="如：前台小票机 / 仓库标签机" /></el-form-item>
                <el-form-item label="连接方式" required><el-select v-model="printerForm.driver" class="w-full" placeholder="选择厂商或蓝牙协议" @change="onDriverChange"><el-option v-for="p in meta.providers" :key="p.key" :label="p.name" :value="p.key"><span>{{ p.name }}</span><span class="float-right text-xs text-gray-400">{{ p.modes?.[0] === 'cloud' ? '云打印' : '手机蓝牙' }}</span></el-option></el-select><div class="form-help">{{ currentProvider?.description }}</div></el-form-item>
                <el-form-item label="打印类型" required><el-radio-group v-model="printerForm.print_type"><el-radio-button v-for="type in currentProvider?.types || ['receipt']" :key="type" :label="type">{{ type === 'label' ? '标签' : '小票' }}</el-radio-button></el-radio-group></el-form-item>
                <el-form-item label="纸张宽度"><el-input-number v-model="printerForm.paper_width" :min="20" :max="110" /><span class="ml-2 text-gray-400">mm</span></el-form-item>
                <template v-for="(label,key) in currentProvider?.fields || {}" :key="key"><el-form-item :label="label" required><el-input v-model.trim="printerForm.config[key]" :type="String(key).includes('key') ? 'password' : 'text'" show-password autocomplete="new-password" /></el-form-item></template>
                <el-form-item label="默认设备"><el-switch v-model="printerForm.is_default" :active-value="1" :inactive-value="0" /></el-form-item>
                <el-form-item label="启用"><el-switch v-model="printerForm.status" :active-value="1" :inactive-value="0" /></el-form-item>
                <el-form-item label="备注"><el-input v-model.trim="printerForm.remark" type="textarea" :rows="2" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="printerVisible=false">取消</el-button><el-button type="primary" :loading="saving" @click="onSavePrinter">保存</el-button></template>
        </el-dialog>

        <el-drawer v-model="templateVisible" :title="templateForm.id ? '编辑打印模板' : '新建打印模板'" size="720px" destroy-on-close>
            <el-form label-width="90px">
                <div class="grid grid-cols-2 gap-x-4"><el-form-item label="模板名称" required><el-input v-model.trim="templateForm.template_name" /></el-form-item><el-form-item label="模板类型"><el-select v-model="templateForm.print_type" class="w-full"><el-option label="小票" value="receipt" /><el-option label="标签" value="label" /></el-select></el-form-item></div>
                <div class="grid grid-cols-2 gap-x-4"><el-form-item label="纸宽"><el-input-number v-model="templateForm.paper_width" :min="20" :max="110" /></el-form-item><el-form-item label="排版"><el-radio-group v-model="templateForm.layout_mode"><el-radio label="native">设备原生</el-radio><el-radio label="raster" disabled>精确图像（后续）</el-radio></el-radio-group></el-form-item></div>
                <el-form-item label="可用变量"><div class="variable-box"><el-tag v-for="item in meta.variables" :key="item.key" class="cursor-pointer" size="small" effect="plain" @click="insertVariable(item.key)">{{ item.name }}</el-tag></div></el-form-item>
                <el-form-item label="模板内容" required><el-input ref="contentInput" v-model="templateForm.content" type="textarea" :rows="18" resize="vertical" placeholder="点击变量可插入 {{变量}}" /></el-form-item>
                <el-form-item label="启用"><el-switch v-model="templateForm.status" :active-value="1" :inactive-value="0" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="templateVisible=false">取消</el-button><el-button type="primary" :loading="saving" @click="onSaveTemplate">保存模板</el-button></template>
        </el-drawer>
    </div>
</template>

<script lang="ts" setup>
import { erpEnumLabel, erpNamedLabel } from '@/addon/hsx_erp/utils/display'
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Connection, Plus, Postcard, Printer, Refresh, Tickets, Warning } from '@element-plus/icons-vue'
import { deleteErpPrinter, getErpPrinters, getErpPrintJobs, getErpPrintMeta, getErpPrintScenes, getErpPrintTemplates, retryErpPrintJob, saveErpPrinter, saveErpPrintScene, saveErpPrintTemplate, testErpPrinter } from '@/addon/hsx_erp/api/erp'

const activeTab = ref('printers'), loading = ref(false), saving = ref(false), jobsLoading = ref(false)
const meta = reactive<any>({ providers: [], variables: [], status_map: {} })
const printers = ref<any[]>([]), templates = ref<any[]>([]), scenes = ref<any[]>([]), jobs = ref<any[]>([])
const jobTotal = ref(0), failedJobs = ref(0), jobQuery = reactive({ keyword: '', status: '', page: 1, limit: 15 })
const enabledScenes = computed(() => scenes.value.filter((x:any) => Number(x.enabled) === 1).length)
const providerName = (key:string) => erpNamedLabel(meta.providers.find((x:any) => x.key === key)?.name, key, '其他打印设备')
const currentProvider = computed(() => meta.providers.find((x:any) => x.key === printerForm.driver))
const granularityText = (v:string) => erpEnumLabel(v, { order: '按订单', device: '按设备', settlement: '按结算' }, '按业务打印')
const granularityOptions = (scene:any) => scene.biz_type === 'sale'
    ? [{ label: '按订单打印', value: 'order' }, { label: '按设备打印', value: 'device' }]
    : scene.biz_type === 'asset'
        ? [{ label: '按设备打印', value: 'device' }]
        : [{ label: '按本次结算打印', value: 'settlement' }]
const statusType = (v:string) => ({ success: 'success', failed: 'danger', waiting_client: 'warning', sending: 'primary' } as any)[v] || 'info'
const formatTime = (v:number) => v ? new Date(v * 1000).toLocaleString() : '-'
const printerOptions = (type:string) => printers.value.filter((x:any) => x.status && x.print_type === type)

async function loadAll() {
    loading.value = true
    try {
        const [m,p,t,s]:any[] = await Promise.all([getErpPrintMeta(), getErpPrinters(), getErpPrintTemplates(), getErpPrintScenes()])
        Object.assign(meta, m?.data || {}); printers.value = p?.data || []; templates.value = t?.data || []; scenes.value = s?.data || []
        await Promise.all([loadJobs(true), loadFailedCount()])
    } finally { loading.value = false }
}
async function loadJobs(silent=false) {
    if (!silent) jobsLoading.value = true
    try { const res:any = await getErpPrintJobs(jobQuery); const data = res?.data || {}; jobs.value = data.data || data.list || []; jobTotal.value = Number(data.total || 0) }
    finally { jobsLoading.value = false }
}
async function loadFailedCount() { const res:any = await getErpPrintJobs({ page:1, limit:1, status:'failed' }); failedJobs.value = Number(res?.data?.total || 0) }
function onTabChange(name:any) { if (name === 'jobs') loadJobs() }

const printerVisible = ref(false), printerForm = reactive<any>({})
function openPrinter(row?:any) { Object.assign(printerForm, row ? { ...row, config: { ...(row.config || {}) } } : { id:0, printer_name:'', driver:'xpyun', print_type:'receipt', paper_width:58, config:{}, copies:1, is_default:0, status:1, sort:0, remark:'' }); printerVisible.value = true }
function onDriverChange() { const p:any=currentProvider.value; printerForm.print_type=p?.types?.[0] || 'receipt'; printerForm.paper_width=printerForm.print_type==='label'?50:58; printerForm.config={} }
async function onSavePrinter() { if (!printerForm.printer_name) return ElMessage.warning('请填写打印机名称'); saving.value=true; try { await saveErpPrinter(printerForm.id,{...printerForm,config:{...printerForm.config}}); printerVisible.value=false; await loadAll() } finally { saving.value=false } }
async function onDeletePrinter(row:any) { try { await ElMessageBox.confirm(`确认删除「${row.printer_name}」？`,'提示',{type:'warning'}) } catch { return }; await deleteErpPrinter(row.id); await loadAll() }
async function onTest(row:any) { const res:any=await testErpPrinter(row.id); const status=res?.data?.status; ElMessage.success(status==='waiting_client'?'任务已生成，请在手机端连接蓝牙打印':'测试任务已发送'); await loadJobs(true) }

async function onSaveScene(scene:any) { scene.saving=true; try { await saveErpPrintScene(scene.id,{ printer_id:scene.printer_id||0,template_id:scene.template_id||0,auto_print:scene.auto_print,enabled:scene.enabled,copies:scene.copies||1,granularity:scene.granularity }); ElMessage.success('场景已保存'); await loadAll() } finally { scene.saving=false } }

const templateVisible=ref(false), templateForm=reactive<any>({}), contentInput=ref()
function openTemplate(row?:any) { Object.assign(templateForm,row?{...row}:{id:0,template_name:'',print_type:'receipt',layout_mode:'native',paper_width:58,content:'',status:1,is_default:0,sort:0}); templateVisible.value=true }
function insertVariable(key:string) { templateForm.content = `${templateForm.content || ''}{{${key}}}`; ElMessage.info(`已插入 {{${key}}}`) }
async function onSaveTemplate() { if (!templateForm.template_name || !templateForm.content) return ElMessage.warning('请填写模板名称和内容'); saving.value=true; try { await saveErpPrintTemplate(templateForm.id,{...templateForm}); templateVisible.value=false; await loadAll() } finally { saving.value=false } }
async function onRetry(row:any) { const res:any = await retryErpPrintJob(row.id); ElMessage.success(res?.data?.status === 'waiting_client' ? '任务已恢复，请在移动打印台连接蓝牙设备' : '重试任务已发送'); await Promise.all([loadJobs(), loadFailedCount()]) }
onMounted(loadAll)
</script>

<style lang="scss" scoped>
.print-center { --pc-border:#e8edf5; --pc-muted:#718096; }
.page-head,.toolbar { display:flex;align-items:flex-start;justify-content:space-between;gap:16px; }
.page-subtitle,.section-hint,.form-help { margin-top:5px;color:var(--pc-muted);font-size:13px;line-height:1.6; }
.overview-grid { display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-top:20px; }
.overview-card { display:flex;align-items:center;gap:14px;padding:17px 18px;border:1px solid var(--pc-border);border-radius:10px;background:#fbfcfe; }
.overview-card .el-icon { display:grid;width:42px;height:42px;place-items:center;border-radius:10px;font-size:22px;background:#eef4ff;color:#3975f6; }.overview-card.green .el-icon{background:#ebfaf2;color:#18a566}.overview-card.orange .el-icon{background:#fff5e8;color:#e98b18}
.overview-card b{display:block;font-size:23px;color:#172033}.overview-card span{display:block;margin-top:2px;font-size:12px;color:var(--pc-muted)}
.device-grid { display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-top:16px; }
.device-card,.device-add { min-height:164px;border:1px solid var(--pc-border);border-radius:10px;background:#fff;padding:18px;transition:.18s; }.device-card:hover,.device-add:hover{border-color:#aac4ff;box-shadow:0 7px 24px rgba(42,86,160,.08)}.device-card.off{opacity:.65}
.device-card{display:grid;grid-template-columns:48px 1fr;gap:13px}.device-icon{display:grid;width:48px;height:48px;place-items:center;border-radius:12px;background:#edf4ff;color:#3975f6;font-size:23px}.device-icon.bluetooth{background:#f1edff;color:#7459d9}
.device-title{display:flex;align-items:center;gap:7px;font-size:16px;font-weight:600;color:#1d2738}.device-meta{margin-top:8px;font-size:13px;color:#4d5a70}.device-desc{margin-top:6px;font-size:12px;color:#8a95a7}.device-actions{grid-column:1/-1;display:flex;justify-content:flex-end;padding-top:8px;border-top:1px dashed #edf0f5}.device-add{display:flex;cursor:pointer;flex-direction:column;align-items:center;justify-content:center;border-style:dashed;color:#7b8799;font-size:14px}.device-add .el-icon{margin-bottom:8px;font-size:24px;color:#4e7ff2}
.scene-list{display:flex;flex-direction:column;gap:12px}.scene-card{display:grid;grid-template-columns:46px minmax(220px,1fr) minmax(360px,1.35fr) 180px 70px;align-items:center;gap:14px;padding:15px;border:1px solid var(--pc-border);border-radius:10px}.scene-badge{display:grid;width:42px;height:42px;place-items:center;border-radius:10px;background:#eaf7ef;color:#18a566;font-size:20px}.scene-badge.label{background:#fff3e4;color:#e58917}.scene-title{font-weight:600;color:#263248}.scene-desc{margin-top:5px;color:#7d8899;font-size:12px}.scene-selects{display:grid;grid-template-columns:1fr 1fr;gap:8px}.copies-field{display:flex;align-items:center;justify-content:space-between;gap:10px;padding-left:11px;border:1px solid var(--el-border-color);border-radius:4px;color:#7a8597;font-size:12px}.copies-field .el-input-number{width:106px}.scene-switches{display:flex;flex-direction:column;gap:8px;font-size:12px;color:#677489}.scene-switches>div{display:flex;align-items:center;justify-content:space-between;gap:8px}
.job-filter{display:flex;gap:9px;margin-bottom:14px}.job-filter .el-input{width:300px}.job-filter .el-select{width:150px}.variable-box{display:flex;max-height:90px;flex-wrap:wrap;gap:7px;overflow:auto;padding:9px;border:1px solid #e5e9f0;border-radius:7px}.form-help{width:100%}
@media(max-width:1200px){.device-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.scene-card{grid-template-columns:42px 1fr 1.4fr}.scene-switches{grid-column:2/3;flex-direction:row}.scene-card>.el-button{grid-column:3/4;justify-self:end}}
</style>
