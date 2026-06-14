<template>
    <PremiumTheme class="check-catalog-page">
        <section class="page-toolbar">
            <div>
                <div class="page-title">检测目录</div>
                <div class="page-subtitle">型号 → 检测项（全 ID 映射，长文本只存一份）。50 万级数据用数据库工具/LOAD DATA 灌入，本页只看与查。</div>
            </div>
            <div class="toolbar-actions">
                <el-button type="primary" @click="triggerUpload">导入 CSV</el-button>
                <el-button :loading="loading" @click="loadList">刷新</el-button>
                <input ref="fileInput" type="file" accept=".csv,.txt" style="display:none" @change="onFileChange" />
            </div>
        </section>

        <div class="stat-row">
            <div class="stat-card"><div class="stat-label">型号数</div><div class="stat-value">{{ summary.models }}</div></div>
            <div class="stat-card"><div class="stat-label">数据行</div><div class="stat-value">{{ summary.data_rows }}</div></div>
            <div class="stat-card"><div class="stat-label">检测项</div><div class="stat-value">{{ summary.fields }}</div></div>
            <div class="stat-card"><div class="stat-label">选项</div><div class="stat-value">{{ summary.options }}</div></div>
        </div>

        <el-form :inline="true" class="filter-form" @submit.prevent>
            <el-form-item label="型号">
                <el-input v-model.trim="query.model_key" clearable class="!w-[220px]" placeholder="如 1MORE_AERO" @keyup.enter="handleSearch" @clear="handleSearch" />
            </el-form-item>
            <el-form-item label="产品ID">
                <el-input v-model.trim="query.product_id" clearable class="!w-[140px]" placeholder="产品ID" @keyup.enter="handleSearch" @clear="handleSearch" />
            </el-form-item>
            <el-form-item>
                <el-button type="primary" @click="handleSearch">查询</el-button>
                <el-button @click="handleReset">重置</el-button>
            </el-form-item>
        </el-form>

        <el-table :data="list" v-loading="loading" size="large" border empty-text="暂无数据（请先灌入检测目录）">
            <el-table-column prop="model_key" label="型号" min-width="150" show-overflow-tooltip />
            <el-table-column prop="group_name" label="分类" width="120" show-overflow-tooltip />
            <el-table-column prop="field_name" label="检测项" min-width="150" show-overflow-tooltip />
            <el-table-column label="默认项" min-width="160" show-overflow-tooltip>
                <template #default="{ row }">{{ row.default_option || '-' }}</template>
            </el-table-column>
            <el-table-column label="选项（按级别着色）" min-width="320">
                <template #default="{ row }">
                    <el-tag
                        v-for="opt in row.options"
                        :key="opt.id"
                        :type="sevType(opt.severity)"
                        effect="light"
                        class="opt-tag"
                    >{{ opt.label }}</el-tag>
                </template>
            </el-table-column>
        </el-table>

        <div class="pager">
            <el-pagination
                v-model:current-page="page.page"
                v-model:page-size="page.limit"
                layout="total, sizes, prev, pager, next, jumper"
                :total="page.total"
                @size-change="loadList"
                @current-change="loadList"
            />
        </div>

        <el-dialog v-model="imp.visible" title="导入检测目录" width="460px" :close-on-click-modal="false" :show-close="!imp.running">
            <div class="imp-file">{{ imp.fileName }}</div>
            <el-progress :percentage="impPercent" :status="imp.done ? 'success' : undefined" />
            <div class="imp-line">已处理 {{ imp.processed }} / {{ imp.total }} 行{{ imp.running ? '（后端慢慢跑，请勿关闭）' : '' }}</div>
            <div class="imp-line muted">新增 {{ imp.inserted }} · 更新 {{ imp.updated }} · 未变跳过 {{ imp.skipped_same }} · 用户改过跳过 {{ imp.skipped_user }}</div>
            <template #footer>
                <el-button :disabled="imp.running" @click="imp.visible = false">{{ imp.done ? '完成' : '关闭' }}</el-button>
            </template>
        </el-dialog>
    </PremiumTheme>
</template>

<script lang="ts" setup>
import PremiumTheme from '@/addon/hsx_recycle/components/PremiumTheme.vue'
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { getCheckCatalogList, uploadCheckCatalog, importChunkCheckCatalog } from '@/addon/hsx_recycle/api/check_catalog'

const loading = ref(false)
const list = ref<any[]>([])
const summary = reactive({ models: 0, data_rows: 0, fields: 0, options: 0 })
const query = reactive({ model_key: '', product_id: '' })
const page = reactive({ page: 1, limit: 20, total: 0 })

// 级别 → Element 标签色：异常红 / 一般灰 / 正常绿
const sevType = (s: string) => (s === 'abnormal' ? 'danger' : s === 'general' ? 'info' : 'success')

async function loadList() {
    loading.value = true
    try {
        const res: any = await getCheckCatalogList({
            model_key: query.model_key,
            product_id: query.product_id,
            page: page.page,
            limit: page.limit,
        })
        const data = res.data || {}
        Object.assign(summary, data.summary || {})
        list.value = data.page?.data || []
        page.total = Number(data.page?.total || 0)
    } finally {
        loading.value = false
    }
}
function handleSearch() {
    page.page = 1
    loadList()
}
function handleReset() {
    query.model_key = ''
    query.product_id = ''
    handleSearch()
}

// 上传 CSV → 后端分批慢慢跑
const fileInput = ref<HTMLInputElement>()
const imp = reactive({
    visible: false, running: false, done: false, fileName: '',
    total: 0, processed: 0, inserted: 0, updated: 0, skipped_same: 0, skipped_user: 0,
    batch_id: 0, token: '',
})
const impPercent = computed(() => (imp.total ? Math.min(100, Math.floor((imp.processed / imp.total) * 100)) : (imp.done ? 100 : 0)))
function triggerUpload() {
    fileInput.value?.click()
}
async function onFileChange(e: Event) {
    const input = e.target as HTMLInputElement
    const f = input.files?.[0]
    input.value = ''
    if (!f) return
    Object.assign(imp, {
        visible: true, running: true, done: false, fileName: f.name,
        total: 0, processed: 0, inserted: 0, updated: 0, skipped_same: 0, skipped_user: 0,
    })
    try {
        const fd = new FormData()
        fd.append('file', f)
        const up: any = await uploadCheckCatalog(fd)
        imp.batch_id = up.data.batch_id
        imp.token = up.data.token
        imp.total = Number(up.data.total_rows || 0)
        let offset = 0
        // 循环跑分片，直到 done
        // eslint-disable-next-line no-constant-condition
        while (true) {
            const res: any = await importChunkCheckCatalog({ batch_id: imp.batch_id, token: imp.token, offset, limit: 2000 })
            const d = res.data
            imp.inserted = d.inserted; imp.updated = d.updated
            imp.skipped_same = d.skipped_same; imp.skipped_user = d.skipped_user
            imp.processed = d.inserted + d.updated + d.skipped_same + d.skipped_user
            offset = Number(d.next_offset || 0)
            if (d.done) break
        }
        imp.done = true
        ElMessage.success('导入完成')
        loadList()
    } catch (err) {
        ElMessage.error('导入中断，请重试')
    } finally {
        imp.running = false
    }
}

onMounted(loadList)
</script>

<style lang="scss" scoped>
.stat-row { display: flex; gap: 16px; margin-bottom: 16px; }
.stat-card { flex: 1; padding: 16px 20px; background: var(--el-fill-color-light); border-radius: 10px; }
.stat-label { font-size: 13px; color: var(--el-text-color-secondary); }
.stat-value { margin-top: 6px; font-size: 22px; font-weight: 600; }
.filter-form { margin-bottom: 8px; }
.opt-tag { margin: 0 6px 6px 0; }
.pager { margin-top: 16px; display: flex; justify-content: flex-end; }
.imp-file { margin-bottom: 12px; font-weight: 500; }
.imp-line { margin-top: 8px; font-size: 13px; }
.muted { color: var(--el-text-color-secondary); }
</style>
