<template>
    <PremiumTheme class="check-severity-page">
        <section class="page-toolbar">
            <div>
                <div class="page-title">选项级别</div>
                <div class="page-subtitle">按选项文本标一次、全局生效：异常红(danger) / 一般灰(info) / 正常绿(success)。改过的不会被重导覆盖。</div>
            </div>
            <div class="toolbar-actions">
                <el-button :loading="loading" @click="loadList">刷新</el-button>
            </div>
        </section>

        <el-row :gutter="16" class="summary-row">
            <el-col :span="8"><el-statistic title="正常" :value="summary.normal" /></el-col>
            <el-col :span="8"><el-statistic title="一般" :value="summary.general" /></el-col>
            <el-col :span="8"><el-statistic title="异常" :value="summary.abnormal" /></el-col>
        </el-row>

        <el-form :inline="true" class="filter-form" @submit.prevent>
            <el-form-item label="级别">
                <el-select v-model="query.severity" clearable class="!w-[120px]" placeholder="全部" @change="handleSearch">
                    <el-option label="正常" value="normal" />
                    <el-option label="一般" value="general" />
                    <el-option label="异常" value="abnormal" />
                </el-select>
            </el-form-item>
            <el-form-item label="关键字">
                <el-input v-model.trim="query.keyword" clearable class="!w-[200px]" placeholder="选项文本，如 碎屏" @keyup.enter="handleSearch" @clear="handleSearch" />
            </el-form-item>
            <el-form-item>
                <el-button type="primary" @click="handleSearch">查询</el-button>
                <el-button @click="handleReset">重置</el-button>
            </el-form-item>
        </el-form>

        <div class="batch-bar">
            <span class="batch-bar__tip">按关键字一键打标：</span>
            <el-input v-model.trim="kw.keyword" clearable class="!w-[200px]" placeholder="含此文本的选项，如 碎/裂" />
            <el-select v-model="kw.severity" class="!w-[110px]">
                <el-option label="设为异常" value="abnormal" />
                <el-option label="设为一般" value="general" />
                <el-option label="设为正常" value="normal" />
            </el-select>
            <el-button type="warning" :disabled="!kw.keyword" :loading="kwLoading" @click="applyKeyword">应用</el-button>

            <el-divider direction="vertical" />
            <span class="batch-bar__tip">选中 {{ selected.length }} 项：</span>
            <el-button size="small" :disabled="!selected.length" @click="batchSet('normal')">正常</el-button>
            <el-button size="small" type="info" :disabled="!selected.length" @click="batchSet('general')">一般</el-button>
            <el-button size="small" type="danger" :disabled="!selected.length" @click="batchSet('abnormal')">异常</el-button>
        </div>

        <el-table :data="list" v-loading="loading" size="large" border @selection-change="selected = $event" empty-text="暂无选项（请先灌入检测目录）">
            <el-table-column type="selection" width="46" />
            <el-table-column prop="text" label="选项文本" min-width="260" show-overflow-tooltip />
            <el-table-column label="当前级别" width="120" align="center">
                <template #default="{ row }">
                    <el-tag :type="sevType(row.severity)" effect="light">{{ sevLabel(row.severity) }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column label="改过" width="80" align="center">
                <template #default="{ row }">
                    <el-tag v-if="row.is_user_modified" size="small" type="warning" effect="plain">已改</el-tag>
                    <span v-else class="muted">-</span>
                </template>
            </el-table-column>
            <el-table-column label="设置级别" width="240" align="center">
                <template #default="{ row }">
                    <el-radio-group :model-value="row.severity" size="small" @change="(v: string) => setOne(row, v)">
                        <el-radio-button label="normal">正常</el-radio-button>
                        <el-radio-button label="general">一般</el-radio-button>
                        <el-radio-button label="abnormal">异常</el-radio-button>
                    </el-radio-group>
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
import { ElMessage } from 'element-plus'
import {
    getCheckSeverityList,
    setCheckSeverity,
    batchSetCheckSeverity,
    setCheckSeverityByKeyword,
} from '@/addon/hsx_recycle/api/check_catalog'

const loading = ref(false)
const kwLoading = ref(false)
const list = ref<any[]>([])
const selected = ref<any[]>([])
const summary = reactive({ normal: 0, general: 0, abnormal: 0 })
const query = reactive({ severity: '', keyword: '' })
const kw = reactive({ keyword: '', severity: 'abnormal' })
const page = reactive({ page: 1, limit: 50, total: 0 })

const sevType = (s: string) => (s === 'abnormal' ? 'danger' : s === 'general' ? 'info' : 'success')
const sevLabel = (s: string) => (s === 'abnormal' ? '异常' : s === 'general' ? '一般' : '正常')

async function loadList() {
    loading.value = true
    try {
        const res: any = await getCheckSeverityList({
            severity: query.severity,
            keyword: query.keyword,
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
function handleSearch() { page.page = 1; loadList() }
function handleReset() { query.severity = ''; query.keyword = ''; handleSearch() }

async function setOne(row: any, severity: string) {
    await setCheckSeverity(row.id, severity)
    ElMessage.success('已更新')
    loadList()
}
async function batchSet(severity: string) {
    if (!selected.value.length) return
    await batchSetCheckSeverity(selected.value.map((r) => r.id), severity)
    ElMessage.success(`已设置 ${selected.value.length} 项`)
    loadList()
}
async function applyKeyword() {
    if (!kw.keyword) return
    kwLoading.value = true
    try {
        const res: any = await setCheckSeverityByKeyword(kw.keyword, kw.severity)
        ElMessage.success(`已更新 ${res.data || 0} 项`)
        kw.keyword = ''
        loadList()
    } finally {
        kwLoading.value = false
    }
}

onMounted(loadList)
</script>

<style lang="scss" scoped>
.summary-row { margin: 8px 0 20px; }
.filter-form { margin-bottom: 4px; }
.batch-bar { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; padding: 10px 14px; background: var(--el-fill-color-lighter); border-radius: 8px; }
.batch-bar__tip { font-size: 13px; color: var(--el-text-color-secondary); }
.pager { margin-top: 16px; display: flex; justify-content: flex-end; }
.muted { color: var(--el-text-color-placeholder); }
</style>
