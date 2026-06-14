<template>
    <PremiumTheme class="check-catalog-page">
        <section class="page-toolbar">
            <div>
                <div class="page-title">检测目录</div>
                <div class="page-subtitle">型号 → 检测项（全 ID 映射，长文本只存一份）。50 万级数据用数据库工具/LOAD DATA 灌入，本页只看与查。</div>
            </div>
            <div class="toolbar-actions">
                <el-button :loading="loading" @click="loadList">刷新</el-button>
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
    </PremiumTheme>
</template>

<script lang="ts" setup>
import PremiumTheme from '@/addon/hsx_recycle/components/PremiumTheme.vue'
import { onMounted, reactive, ref } from 'vue'
import { getCheckCatalogList } from '@/addon/hsx_recycle/api/check_catalog'

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
</style>
