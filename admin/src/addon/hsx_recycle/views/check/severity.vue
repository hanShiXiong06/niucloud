<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">选项级别</div>
                    <div class="mt-1 text-sm text-gray-500">把检测选项统一打标为「正常 / 一般 / 异常」。用户改过的，重导检测目录时不会被覆盖。</div>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-3 gap-4">
                <div class="rounded-lg bg-gray-50 px-5 py-4">
                    <div class="text-sm text-gray-500">正常</div>
                    <div class="mt-1 text-2xl font-semibold text-green-600">{{ summary.normal }}</div>
                </div>
                <div class="rounded-lg bg-gray-50 px-5 py-4">
                    <div class="text-sm text-gray-500">一般</div>
                    <div class="mt-1 text-2xl font-semibold text-orange-500">{{ summary.general }}</div>
                </div>
                <div class="rounded-lg bg-gray-50 px-5 py-4">
                    <div class="text-sm text-gray-500">异常</div>
                    <div class="mt-1 text-2xl font-semibold text-red-600">{{ summary.abnormal }}</div>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <el-input v-model="search.keyword" placeholder="选项关键字" clearable class="w-48" @keyup.enter="reload" />
                <el-select v-model="search.severity" placeholder="级别" clearable class="w-32" @change="reload">
                    <el-option label="正常" value="normal" />
                    <el-option label="一般" value="general" />
                    <el-option label="异常" value="abnormal" />
                </el-select>
                <el-button @click="reload" :loading="loading">查询</el-button>
                <el-divider direction="vertical" />
                <span class="text-sm text-gray-500">已选 {{ selected.length }} 项</span>
                <el-select v-model="batchSeverity" placeholder="批量设为" class="w-32" :disabled="selected.length === 0">
                    <el-option label="正常" value="normal" />
                    <el-option label="一般" value="general" />
                    <el-option label="异常" value="abnormal" />
                </el-select>
                <el-button :disabled="selected.length === 0 || !batchSeverity" :loading="batching" @click="doBatch">批量打标</el-button>
                <el-button @click="keywordVisible = true">按关键字打标</el-button>
            </div>

            <el-table class="mt-4" :data="list" v-loading="loading" size="large" empty-text="暂无选项，导入检测目录后自动生成"
                @selection-change="(rows) => (selected = rows)">
                <el-table-column type="selection" width="44" />
                <el-table-column prop="option_label" label="选项" min-width="220" show-overflow-tooltip />
                <el-table-column label="级别" width="160">
                    <template #default="{ row }">
                        <el-select :model-value="row.severity" size="small" class="w-28" @change="(v) => changeOne(row, v)">
                            <el-option label="正常" value="normal" />
                            <el-option label="一般" value="general" />
                            <el-option label="异常" value="abnormal" />
                        </el-select>
                    </template>
                </el-table-column>
                <el-table-column label="级别标识" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag size="small" :type="tagType(row.severity)">{{ severityText(row.severity) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="改过" width="80" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.is_user_modified" size="small" type="warning">已改</el-tag>
                        <span v-else class="text-gray-300">-</span>
                    </template>
                </el-table-column>
            </el-table>
            <div class="mt-4 flex justify-end">
                <el-pagination layout="total, prev, pager, next" :total="total" :page-size="search.limit"
                    :current-page="search.page" @current-change="onPage" />
            </div>
        </el-card>

        <el-dialog v-model="keywordVisible" title="按关键字批量打标" width="460px">
            <el-form label-width="80px">
                <el-form-item label="关键字">
                    <el-input v-model="kw.keyword" placeholder="如：碎、裂、进水" />
                </el-form-item>
                <el-form-item label="设为级别">
                    <el-select v-model="kw.severity" class="w-40">
                        <el-option label="正常" value="normal" />
                        <el-option label="一般" value="general" />
                        <el-option label="异常" value="abnormal" />
                    </el-select>
                </el-form-item>
                <div class="text-xs text-gray-400">把"选项"中包含该关键字的全部选项设为所选级别。</div>
            </el-form>
            <template #footer>
                <el-button @click="keywordVisible = false">取消</el-button>
                <el-button type="primary" :loading="kwLoading" :disabled="!kw.keyword" @click="doKeyword">确认打标</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive } from 'vue'
import { ElMessage } from 'element-plus'
import { getCheckSeverityList, setCheckSeverity, batchSetCheckSeverity, setCheckSeverityByKeyword } from '@/addon/hsx_recycle/api/check_catalog'

const severityText = (s: string) => (s === 'abnormal' ? '异常' : s === 'general' ? '一般' : '正常')
const tagType = (s: string) => (s === 'abnormal' ? 'danger' : s === 'general' ? 'warning' : 'success')

const loading = ref(false)
const list = ref<any[]>([])
const total = ref(0)
const summary = reactive({ normal: 0, general: 0, abnormal: 0 })
const search = reactive({ keyword: '', severity: '', page: 1, limit: 50 })
const selected = ref<any[]>([])

function onPage(p: number) { search.page = p; loadList() }
function reload() { search.page = 1; loadList() }
async function loadList() {
    loading.value = true
    try {
        const res: any = await getCheckSeverityList(search)
        const s = res.data?.summary || {}
        summary.normal = s.normal || 0
        summary.general = s.general || 0
        summary.abnormal = s.abnormal || 0
        list.value = res.data?.page?.data || []
        total.value = res.data?.page?.total || 0
    } finally {
        loading.value = false
    }
}

async function changeOne(row: any, v: string) {
    await setCheckSeverity(row.id, v)
    row.severity = v
    row.is_user_modified = 1
    ElMessage.success('已更新')
    refreshSummary()
}

const batchSeverity = ref('')
const batching = ref(false)
async function doBatch() {
    if (selected.value.length === 0 || !batchSeverity.value) return
    batching.value = true
    try {
        await batchSetCheckSeverity(selected.value.map((x) => x.id), batchSeverity.value)
        ElMessage.success('批量打标完成')
        batchSeverity.value = ''
        loadList()
    } finally {
        batching.value = false
    }
}

const keywordVisible = ref(false)
const kwLoading = ref(false)
const kw = reactive({ keyword: '', severity: 'abnormal' })
async function doKeyword() {
    if (!kw.keyword) return
    kwLoading.value = true
    try {
        const res: any = await setCheckSeverityByKeyword(kw.keyword, kw.severity)
        ElMessage.success('打标完成')
        keywordVisible.value = false
        kw.keyword = ''
        loadList()
    } finally {
        kwLoading.value = false
    }
}

function refreshSummary() {
    getCheckSeverityList({ ...search }).then((res: any) => {
        const s = res.data?.summary || {}
        summary.normal = s.normal || 0
        summary.general = s.general || 0
        summary.abnormal = s.abnormal || 0
    }).catch(() => {})
}

loadList()
</script>
