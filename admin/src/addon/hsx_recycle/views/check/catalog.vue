<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">检测目录</div>
                    <div class="mt-1 text-sm text-gray-500">按型号维护质检项与可选项（如拍机堂导出）。用 CSV 导入，大数据量无压力。</div>
                </div>
                <div class="flex gap-2">
                    <el-button @click="openBatches">导入记录</el-button>
                    <el-button type="primary" @click="importVisible = true">导入检测目录</el-button>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-3">
                <el-input v-model="search.model_key" placeholder="型号" clearable class="w-44" @keyup.enter="reload" />
                <el-input v-model="search.group_name" placeholder="分类" clearable class="w-44" @keyup.enter="reload" />
                <el-input v-model="search.keyword" placeholder="检测项 / 型号关键字" clearable class="w-56" @keyup.enter="reload" />
                <el-button @click="reload" :loading="loading">查询</el-button>
            </div>

            <el-table class="mt-4" :data="list" v-loading="loading" size="large" empty-text="暂无检测目录，请先导入">
                <el-table-column prop="model_key" label="型号" min-width="150" show-overflow-tooltip />
                <el-table-column prop="group_name" label="分类" width="130" show-overflow-tooltip />
                <el-table-column prop="field_name" label="检测项" min-width="150" show-overflow-tooltip />
                <el-table-column prop="default_option" label="默认选项" width="130" show-overflow-tooltip />
                <el-table-column label="全部选项" min-width="220">
                    <template #default="{ row }">
                        <span class="text-gray-500 text-sm">{{ optionText(row.options_json) }}</span>
                    </template>
                </el-table-column>
                <el-table-column label="改过" width="70" align="center">
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

        <!-- 导入 -->
        <el-dialog v-model="importVisible" title="导入检测目录(CSV)" width="560px">
            <el-alert type="info" :closable="false" class="mb-3"
                title="列顺序：型号, 产品ID, 检测项, 分类, 默认选项, 全部选项(用 | 分隔)。请用 CSV(UTF-8)，Excel 可另存为 CSV。" />
            <el-upload drag :auto-upload="false" :limit="1" :on-change="onFileChange" :on-remove="() => (file = null)" accept=".csv,.txt">
                <div class="el-upload__text">把 CSV 拖到这里，或<em>点击选择</em></div>
            </el-upload>
            <template #footer>
                <el-button @click="importVisible = false">取消</el-button>
                <el-button type="primary" :loading="importing" :disabled="!file" @click="doImport">开始导入</el-button>
            </template>
        </el-dialog>

        <!-- 导入记录 -->
        <el-dialog v-model="batchesVisible" title="导入记录" width="640px">
            <el-table :data="batches" size="small" v-loading="batchesLoading" empty-text="暂无导入记录">
                <el-table-column prop="id" label="批次" width="70" />
                <el-table-column prop="file_name" label="文件" min-width="160" show-overflow-tooltip />
                <el-table-column prop="total_rows" label="行数" width="90" align="right" />
                <el-table-column prop="created_rows" label="新增" width="80" align="right" />
                <el-table-column prop="updated_rows" label="更新" width="80" align="right" />
                <el-table-column label="时间" min-width="150">
                    <template #default="{ row }">{{ row.create_at ? formatTime(row.create_at) : '-' }}</template>
                </el-table-column>
            </el-table>
        </el-dialog>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive } from 'vue'
import { ElMessage } from 'element-plus'
import { getCheckCatalogList, getCheckCatalogBatches, importCheckCatalog } from '@/addon/hsx_recycle/api/check_catalog'

const formatTime = (t: number) => new Date(t * 1000).toLocaleString()
const loading = ref(false)
const list = ref<any[]>([])
const total = ref(0)
const search = reactive({ model_key: '', group_name: '', keyword: '', page: 1, limit: 20 })

function optionText(v: any): string {
    if (!v) return '-'
    try {
        const arr = typeof v === 'string' ? JSON.parse(v) : v
        return Array.isArray(arr) ? arr.join(' | ') : String(v)
    } catch {
        return String(v)
    }
}
function onPage(p: number) { search.page = p; loadList() }
function reload() { search.page = 1; loadList() }
async function loadList() {
    loading.value = true
    try {
        const res: any = await getCheckCatalogList(search)
        list.value = res.data?.data || []
        total.value = res.data?.total || 0
    } finally {
        loading.value = false
    }
}

// 导入
const importVisible = ref(false)
const importing = ref(false)
const file = ref<any>(null)
function onFileChange(f: any) { file.value = f }
async function doImport() {
    if (!file.value?.raw) return
    const fd = new FormData()
    fd.append('file', file.value.raw)
    fd.append('source', 'paijitang')
    importing.value = true
    try {
        const res: any = await importCheckCatalog(fd)
        ElMessage.success('导入完成')
        importVisible.value = false
        file.value = null
        reload()
    } finally {
        importing.value = false
    }
}

// 导入记录
const batchesVisible = ref(false)
const batchesLoading = ref(false)
const batches = ref<any[]>([])
async function openBatches() {
    batchesVisible.value = true
    batchesLoading.value = true
    try {
        const res: any = await getCheckCatalogBatches()
        batches.value = res.data || []
    } finally {
        batchesLoading.value = false
    }
}

loadList()
</script>
