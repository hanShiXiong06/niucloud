<template>
    <el-drawer v-model="visible" title="发送本批上新提醒" size="min(560px, 100vw)" append-to-body @closed="stop">
        <div v-loading="loading" class="arrival-notice">
            <el-alert v-if="error" :title="error" type="error" :closable="false" />
            <template v-if="preview">
                <div class="notice-stats">
                    <div><strong>{{ preview.saleable_count }}</strong><span>本批当前可售</span></div>
                    <div><strong>{{ preview.member_count }}</strong><span>已同意本批上新提醒</span></div>
                    <div><strong>{{ preview.excluded_count }}</strong><span>不可售，已排除</span></div>
                </div>
                <el-alert v-if="!preview.capability?.enabled" :title="preview.capability?.reason" type="warning" :closable="false" />
                <p class="notice-help">未上架、已售、锁定、无库存或未允许线上销售的商品不会推送。只发送给本站“上新提醒”订阅客户；原分类/筛选订阅独立保留，不叠加逐台通知。</p>
                <el-alert title="一人一批一条，不是永久群发授权" description="微信一次性订阅有额度限制；微信受理不代表客户已经阅读。发送前会再次核验商品及订阅状态。" type="info" :closable="false" />
                <section v-if="notice" class="notice-result">
                    <div class="notice-result__title">{{ stateText(notice.status) }}</div>
                    <p>{{ notice.message }}</p>
                    <el-progress :percentage="progress" :status="notice.status === 'failed' ? 'exception' : undefined" />
                    <el-alert v-if="notice.error_message" :title="notice.error_message" type="error" :closable="false" />
                    <el-alert v-if="notice.can_resume" title="超过一分钟未更新进度，可检查并恢复任务。仍在执行的任务会被保护，不会重复发送。" type="warning" :closable="false" />
                    <p v-if="notice.result_json?.unknown" class="notice-help">“待核实”表示微信响应未确认，为避免重复消息，不自动重发这部分客户。</p>
                    <el-table :data="results" max-height="300" size="small" empty-text="暂无客户结果">
                        <el-table-column prop="member_name" label="客户" min-width="105"><template #default="{row}">{{ row.member_name || `会员 ${row.member_id}` }}</template></el-table-column>
                        <el-table-column prop="result" label="处理结果" min-width="240" />
                    </el-table>
                    <el-pagination small layout="prev, pager, next" :total="resultTotal" :page-size="15" v-model:current-page="resultPage" @current-change="loadResults" />
                </section>
            </template>
        </div>
        <template #footer>
            <el-button @click="visible = false">关闭</el-button>
            <el-button :loading="loading" @click="load">刷新状态</el-button>
            <el-button v-if="notice && (notice.can_resume || ['failed', 'partial'].includes(notice.status))" type="primary" :loading="sending" @click="retry">{{ notice.can_resume ? '检查并恢复任务' : '重试未发送/失败项' }}</el-button>
            <el-button v-else-if="!notice" type="primary" :disabled="!canSend" :loading="sending" @click="send">发送上新提醒</el-button>
        </template>
    </el-drawer>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { ElMessageBox } from 'element-plus'
import { getGoodsArrivalPreview, getGoodsArrivalResults, retryGoodsArrivalNotice, sendGoodsArrivalNotice } from '@/addon/phone_shop/api/goods'
const visible = ref(false)
const loading = ref(false)
const sending = ref(false)
const error = ref('')
const preview = ref<any>(null)
const importId = ref(0)
const resultPage = ref(1)
const resultTotal = ref(0)
const results = ref<any[]>([])
const notice = computed(() => preview.value?.notice_task)
const canSend = computed(() => !loading.value && !error.value && preview.value?.capability?.enabled && preview.value.saleable_count > 0 && preview.value.member_count > 0)
const progress = computed(() => notice.value?.total_rows ? Math.min(100, Math.round(notice.value.processed_rows / notice.value.total_rows * 100)) : 0)
let timer: ReturnType<typeof setTimeout> | undefined
let generation = 0
const stop = () => { generation++; if (timer) clearTimeout(timer); timer = undefined; loading.value = false; sending.value = false }
const loadResults = async () => {
    if (!notice.value?.id) return
    const current = generation
    const page = resultPage.value
    try {
        const res: any = await getGoodsArrivalResults(notice.value.id, page)
        if (current !== generation || page !== resultPage.value) return
        results.value = res.data?.data || []
        resultTotal.value = Number(res.data?.total || 0)
    } catch (e: any) { if (current === generation) error.value = e?.msg || e?.message || '客户结果加载失败，请刷新' }
}
const load = async () => {
    if (!visible.value || loading.value) return
    if (timer) clearTimeout(timer)
    const current = generation
    loading.value = true
    error.value = ''
    try {
        const res: any = await getGoodsArrivalPreview(importId.value)
        if (current !== generation) return
        preview.value = res.data
        await loadResults()
        if (current !== generation) return
        if (['queued', 'processing'].includes(notice.value?.status)) timer = setTimeout(load, 3000)
    } catch (e: any) { if (current === generation) error.value = e?.msg || e?.message || '通知数据加载失败，请刷新重试' }
    finally { if (current === generation) loading.value = false }
}
const open = (id: number) => { stop(); importId.value = id; preview.value = null; results.value = []; resultPage.value = 1; visible.value = true; void load() }
const send = async () => {
    if (!canSend.value || sending.value) return
    const current = generation
    const id = importId.value
    sending.value = true
    try {
        try { await ElMessageBox.confirm(`将通知 ${preview.value.member_count} 位订阅客户，本批可售 ${preview.value.saleable_count} 台。实际发送以微信授权额度和回执为准，是否发送？`, '确认发送微信上新提醒', { type: 'warning', confirmButtonText: '确认发送', cancelButtonText: '暂不发送' }) }
        catch { return }
        if (current !== generation) return
        await sendGoodsArrivalNotice(id)
        if (current === generation) await load()
    } catch (e: any) { if (current === generation) error.value = e?.msg || e?.message || '通知任务提交失败，请刷新核对后重试' }
    finally { if (current === generation) sending.value = false }
}
const retry = async () => {
    if (sending.value) return
    const current = generation
    sending.value = true
    try { await retryGoodsArrivalNotice(notice.value.id); if (current === generation) await load() }
    catch (e: any) { if (current === generation) error.value = e?.msg || e?.message || '重试提交失败，请刷新核对' }
    finally { if (current === generation) sending.value = false }
}
const states: Record<string, string> = { queued: '等待队列处理', processing: '正在发送', completed: '本批处理完成', partial: '部分通知未发送成功', failed: '通知任务中断' }
const stateText = (value: string) => states[value] || value
defineExpose({ open })
onBeforeUnmount(stop)
</script>

<style scoped>
.arrival-notice{min-height:120px;color:#334155}
.notice-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:20px}
.notice-stats>div{display:flex;flex-direction:column;gap:6px;padding:14px 10px;border-radius:10px;background:#f8fafc}
.notice-stats strong{font-size:26px}.notice-stats span,.notice-help{font-size:13px;color:#64748b;line-height:1.7}
.notice-help{margin:16px 0}.notice-result{margin-top:24px}.notice-result__title{font-size:16px;font-weight:600}.notice-result p{line-height:1.7;font-size:13px}.notice-result .el-alert{margin:12px 0}
</style>
