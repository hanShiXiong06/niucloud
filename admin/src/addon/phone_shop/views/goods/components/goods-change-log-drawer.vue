<template>
    <el-drawer v-model="visible" title="商品修改记录" size="min(920px, 94vw)" append-to-body destroy-on-close @closed="cancelPending">
        <div class="log-summary">
            <strong>{{ goods.goods_name || '商品' }}</strong>
            <span v-if="imei">IMEI / 串号：{{ imei }}</span>
            <p>仅记录升级后的实际修改。显示修改前后值；保存失败不会产生成功记录。</p>
        </div>
        <div v-loading="loading" class="log-body">
            <el-result v-if="error" icon="warning" title="记录加载失败" :sub-title="error">
                <template #extra><el-button @click="load">重新加载</el-button></template>
            </el-result>
            <el-empty v-else-if="!loading && !rows.length" description="暂无修改记录，升级前的操作不会补造日志" />
            <el-collapse v-else v-model="expanded">
                <el-collapse-item v-for="row in rows" :key="row.id" :name="row.id">
                    <template #title>
                        <div class="log-heading">
                            <strong>{{ row.operator_name || '系统任务' }}</strong>
                            <span>{{ row.source_name }}</span>
                            <time>{{ formatTime(row.create_time) }}</time>
                            <span class="log-count">{{ row.changes.length }} 项变更</span>
                        </div>
                    </template>
                    <el-table :data="row.changes" size="small" border table-layout="fixed">
                        <el-table-column label="修改内容" width="155">
                            <template #default="{ row: change }">
                                <strong>{{ change.label }}</strong>
                                <div v-if="change.sku" class="log-sku">{{ change.sku }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column v-for="side in sides" :key="side.key" :label="side.label" min-width="160">
                            <template #default="{ row: change }">
                                <details v-if="String(change[side.key] || '').length > 100" class="log-value">
                                    <summary>{{ String(change[side.key]).slice(0, 80) }}… 展开</summary>
                                    <pre>{{ change[side.key] }}</pre>
                                </details>
                                <div v-else class="log-value">{{ change[side.key] ?? '未设置' }}</div>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-collapse-item>
            </el-collapse>
        </div>
        <template #footer>
            <el-pagination v-model:current-page="page" :page-size="15" :total="total" small layout="total, prev, pager, next" @current-change="load" />
        </template>
    </el-drawer>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { getGoodsChangeLogs } from '@/addon/phone_shop/api/goods'

const visible = ref(false)
const goods = ref<Record<string, any>>({})
const imei = computed(() => (goods.value.goodsSku || goods.value.goods_sku || {}).sku_no || '')
const page = ref(1), total = ref(0), loading = ref(false), error = ref('')
const rows = ref<any[]>([]), expanded = ref<number[]>([])
const sides = [{ key: 'before_text', label: '修改前' }, { key: 'after_text', label: '修改后' }]
let requestId = 0
const formatTime = (value: number) => new Date(Number(value) * 1000).toLocaleString('zh-CN', { hour12: false })
const cancelPending = () => { requestId++; loading.value = false }

const load = async () => {
    const id = ++requestId
    loading.value = true
    error.value = ''
    rows.value = []
    try {
        const res = await getGoodsChangeLogs(Number(goods.value.goods_id), { page: page.value, limit: 15 })
        if (id !== requestId) return
        rows.value = res.data.data || []
        total.value = Number(res.data.total || 0)
        expanded.value = rows.value.length ? [rows.value[0].id] : []
    } catch (e: any) {
        if (id === requestId) error.value = e?.msg || e?.message || '暂时无法获取，请重试'
    } finally {
        if (id === requestId) loading.value = false
    }
}
const open = (item: Record<string, any>) => {
    goods.value = item
    page.value = 1
    total.value = 0
    expanded.value = []
    visible.value = true
    void load()
}
defineExpose({ open })
</script>

<style scoped>
.log-summary { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 16px; margin-bottom: 16px; }
.log-summary p { width: 100%; margin: 0; font-size: 12px; color: #64748b; }
.log-summary > span { font-size: 12px; color: #475569; overflow-wrap: anywhere; }
.log-body { min-height: 140px; }
.log-heading { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 12px; padding: 8px 12px 8px 0; line-height: 22px; }
.log-heading time, .log-count { color: #64748b; font-size: 12px; }
.log-sku { font-size: 11px; color: #64748b; overflow-wrap: anywhere; }
.log-value { font-size: 12px; line-height: 1.6; white-space: pre-wrap; overflow-wrap: anywhere; }
.log-value summary { cursor: pointer; color: #475569; }
.log-value pre { font: inherit; white-space: pre-wrap; overflow-wrap: anywhere; margin: 6px 0 0; }
:deep(.el-collapse-item__header) { height: auto; min-height: 48px; }
:deep(.el-drawer__footer) { display: flex; justify-content: flex-end; border-top: 1px solid #e5e7eb; }
</style>
