<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
        <div class="report-page">
        <div class="page-head">
            <div><div class="text-page-title">经营报告</div><p>日报、周报和月报按统一口径生成，企业微信只负责送达。</p></div>
            <el-button :icon="Setting" @click="configVisible = true">报告设置</el-button>
        </div>

        <div class="toolbar">
            <el-segmented v-model="query.report_type" :options="typeOptions" @change="loadReports" />
            <div class="toolbar-actions">
                <el-dropdown @command="generateReport">
                    <el-button type="primary" :loading="generating"><el-icon><Plus /></el-icon>立即生成<el-icon class="el-icon--right"><ArrowDown /></el-icon></el-button>
                    <template #dropdown><el-dropdown-menu><el-dropdown-item command="daily">昨日经营日报</el-dropdown-item><el-dropdown-item command="weekly">上周经营周报</el-dropdown-item><el-dropdown-item command="monthly">上月经营月报</el-dropdown-item></el-dropdown-menu></template>
                </el-dropdown>
                <el-button :icon="Refresh" circle title="刷新" @click="loadReports" />
            </div>
        </div>

        <el-table :data="reports" v-loading="loading" row-key="id" @row-click="openReport">
            <el-table-column label="报告" min-width="260"><template #default="{ row }"><div class="report-title">{{ row.title }}</div><div class="secondary">{{ formatPeriod(row) }}</div></template></el-table-column>
            <el-table-column label="回收入库" width="110"><template #default="{ row }">{{ summary(row).recycle_in_count || 0 }} 台</template></el-table-column>
            <el-table-column label="销售" width="100"><template #default="{ row }">{{ summary(row).sale_count || 0 }} 台</template></el-table-column>
            <el-table-column label="库存" width="100"><template #default="{ row }">{{ summary(row).stock_count || 0 }} 台</template></el-table-column>
            <el-table-column label="动销率" width="100"><template #default="{ row }">{{ Number(summary(row).turnover_rate || 0).toFixed(2) }}%</template></el-table-column>
            <el-table-column label="通知" width="140"><template #default="{ row }"><el-tag :type="statusType(row.notify_status)" effect="plain">{{ statusText(row.notify_status) }}</el-tag></template></el-table-column>
            <el-table-column label="生成时间" width="180"><template #default="{ row }">{{ formatTime(row.generated_at) }}</template></el-table-column>
            <el-table-column label="操作" width="130" align="right"><template #default="{ row }"><el-button link type="primary" @click.stop="openReport(row)">查看</el-button><el-button v-if="['failed', 'skipped'].includes(row.notify_status)" link type="warning" @click.stop="retry(row)">补推</el-button></template></el-table-column>
        </el-table>
        <div class="pagination"><el-pagination v-model:current-page="query.page" v-model:page-size="query.limit" layout="total, prev, pager, next" :total="total" @current-change="loadReports" /></div>

        <el-drawer v-model="detailVisible" size="min(760px, 92vw)" :title="current?.title || '经营报告'">
            <template v-if="current">
                <div class="metric-grid">
                    <div v-for="item in metricItems" :key="item.key" class="metric"><span>{{ item.label }}</span><strong>{{ item.value }}{{ item.unit }}</strong></div>
                </div>
                <h3>员工产出</h3>
                <el-table :data="snapshot.staff || []" size="small"><el-table-column prop="name" label="员工" /><el-table-column prop="role_name" label="工作" /><el-table-column prop="count" label="完成量" width="100" /><el-table-column prop="amount" label="业务金额" width="130"><template #default="{ row }">¥{{ Number(row.amount || 0).toFixed(2) }}</template></el-table-column></el-table>
                <h3>货盘结构</h3>
                <el-table :data="snapshot.categories || []" size="small"><el-table-column prop="name" label="分类" /><el-table-column prop="in_count" label="入库" width="90" /><el-table-column prop="sale_count" label="销售" width="90" /><el-table-column prop="stock_count" label="库存" width="90" /></el-table>
                <h3>数据来源</h3>
                <div class="providers"><el-tag v-for="item in current.providers_json || []" :key="item.provider" :type="item.available ? 'success' : 'danger'" effect="plain">{{ item.provider_name }} · {{ item.available ? '已接入' : (item.error || '异常') }}</el-tag></div>
            </template>
        </el-drawer>

        <el-dialog v-model="configVisible" title="经营报告设置" width="560px">
            <el-form label-width="110px" v-loading="configLoading">
                <el-form-item label="启用报告"><el-switch v-model="config.enabled" :active-value="1" :inactive-value="0" /></el-form-item>
                <el-form-item label="报告周期"><el-checkbox v-model="config.daily_enabled" :true-value="1" :false-value="0">日报</el-checkbox><el-checkbox v-model="config.weekly_enabled" :true-value="1" :false-value="0">周报</el-checkbox><el-checkbox v-model="config.monthly_enabled" :true-value="1" :false-value="0">月报</el-checkbox></el-form-item>
                <el-form-item label="推送时间"><el-time-select v-model="config.send_time" start="00:00" step="00:05" end="23:55" /></el-form-item>
                <el-form-item label="老板接收人"><el-select v-model="config.receiver_uids" multiple filterable class="w-full" placeholder="选择接收经营报告的员工"><el-option v-for="item in config.staff_options || []" :key="item.uid" :label="item.name" :value="item.uid" /></el-select><div class="form-help">接收人还需要在企业微信插件中绑定成员 UserID。</div></el-form-item>
                <el-form-item label="敏感数据"><el-checkbox v-model="config.show_finance" :true-value="1" :false-value="0">展示收支</el-checkbox><el-checkbox v-model="config.show_profit" :true-value="1" :false-value="0">展示毛利</el-checkbox></el-form-item>
            </el-form>
            <template #footer><el-button @click="configVisible = false">取消</el-button><el-button type="primary" :loading="configSaving" @click="saveConfig">保存</el-button></template>
        </el-dialog>
        </div>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { ArrowDown, Plus, Refresh, Setting } from '@element-plus/icons-vue'
import { generatePerformanceReport, getPerformanceConfig, getPerformanceReport, getPerformanceReports, retryPerformanceReport, savePerformanceConfig } from '../../api'

const typeOptions = [{ label: '全部', value: '' }, { label: '日报', value: 'daily' }, { label: '周报', value: 'weekly' }, { label: '月报', value: 'monthly' }]
const query = reactive({ report_type: '', page: 1, limit: 15 })
const reports = ref<any[]>([]), total = ref(0), loading = ref(false), generating = ref(false)
const detailVisible = ref(false), current = ref<any>(null)
const configVisible = ref(false), configLoading = ref(false), configSaving = ref(false)
const config = reactive<any>({ enabled: 0, daily_enabled: 1, weekly_enabled: 1, monthly_enabled: 1, send_time: '09:10', receiver_uids: [], show_finance: 1, show_profit: 1, staff_options: [] })
const snapshot = computed(() => current.value?.snapshot_json || {})
const metricItems = computed(() => { const s = snapshot.value.summary || {}; return [
    { key: 'recycle', label: '回收入库', value: s.recycle_in_count || 0, unit: ' 台' }, { key: 'sale', label: '销售出库', value: s.sale_count || 0, unit: ' 台' },
    { key: 'stock', label: '当前库存', value: s.stock_count || 0, unit: ' 台' }, { key: 'turnover', label: '动销率', value: Number(s.turnover_rate || 0).toFixed(2), unit: '%' },
    { key: 'amount', label: '销售额', value: `¥${Number(s.sale_amount || 0).toFixed(2)}`, unit: '' }, { key: 'profit', label: '销售毛利', value: `¥${Number(s.sale_profit || 0).toFixed(2)}`, unit: '' }
] })
const loadReports = async () => { loading.value = true; try { const data: any = (await getPerformanceReports(query)).data || {}; reports.value = data.data || []; total.value = data.total || 0 } finally { loading.value = false } }
const loadConfig = async () => { configLoading.value = true; try { Object.assign(config, (await getPerformanceConfig()).data || {}) } finally { configLoading.value = false } }
const saveConfig = async () => { configSaving.value = true; try { Object.assign(config, (await savePerformanceConfig(config)).data || {}); configVisible.value = false; ElMessage.success('经营报告设置已保存') } finally { configSaving.value = false } }
const generateReport = async (type: string) => { generating.value = true; try { const row: any = (await generatePerformanceReport(type)).data; ElMessage.success('报告已生成并进入通知队列'); await loadReports(); if (row?.id) openReport(row) } finally { generating.value = false } }
const openReport = async (row: any) => { current.value = (await getPerformanceReport(Number(row.id))).data; detailVisible.value = true }
const retry = async (row: any) => { await retryPerformanceReport(Number(row.id)); ElMessage.success('已重新派发报告通知'); loadReports() }
const summary = (row: any) => row.summary_json || {}
const statusText = (value: string) => ({ pending: '待入队', success: '已进入通知队列', failed: '入队失败', skipped: '待补充配置' }[value] || value)
const statusType = (value: string) => ({ success: 'success', failed: 'danger', skipped: 'warning', pending: 'info' }[value] || 'info') as any
const formatTime = (value: any) => value ? new Date(Number(value) * 1000).toLocaleString('zh-CN', { hour12: false }) : '—'
const formatPeriod = (row: any) => `${formatTime(row.start_at).slice(0, 10)} 至 ${formatTime(row.end_at).slice(0, 10)}`
watch(configVisible, value => { if (value) loadConfig() })
onMounted(() => { loadReports(); loadConfig() })
</script>

<style scoped lang="scss">
.report-page { min-height: 100%; }
.page-head, .toolbar, .toolbar-actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.page-head { align-items: flex-start; margin-bottom: 20px; }.page-head p { margin: 6px 0 0; color: var(--el-text-color-secondary); }
.toolbar { margin-bottom: 14px; padding: 14px 16px; border-radius: 4px; background: var(--el-fill-color-lighter); }.report-title { font-weight: 600; }.secondary, .form-help { margin-top: 4px; color: var(--el-text-color-secondary); font-size: 12px; }.pagination { display: flex; justify-content: flex-end; margin-top: 16px; }
.metric-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 24px; }
.metric { min-height: 82px; padding: 14px; display: flex; flex-direction: column; justify-content: space-between; border: 1px solid var(--el-border-color-lighter); border-radius: 4px; background: var(--el-bg-color); }.metric span { color: var(--el-text-color-secondary); font-size: 13px; }.metric strong { font-size: 22px; }
h3 { margin: 24px 0 10px; font-size: 16px; }.providers { display: flex; flex-wrap: wrap; gap: 8px; }.w-full { width: 100%; }
@media (max-width: 700px) { .metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }.page-head, .toolbar { align-items: stretch; flex-direction: column; } }
</style>
