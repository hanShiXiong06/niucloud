<template>
    <!-- 触发区域：显示已选名称 + 清除按钮 -->
    <div class="erp-party-select" @click="open">
        <el-input
            :model-value="displayName"
            :placeholder="placeholder"
            readonly
            :clearable="clearable"
            @clear.stop="handleClear"
        >
            <template #suffix>
                <el-icon v-if="!modelValue" class="cursor-pointer text-gray-400"><Search /></el-icon>
            </template>
        </el-input>
    </div>

    <!-- 选择弹窗 -->
    <el-dialog
        v-model="visible"
        :title="dialogTitle"
        width="640px"
        :append-to-body="true"
        destroy-on-close
    >
        <div class="flex gap-2 mb-3">
            <el-input
                v-model="keyword"
                placeholder="搜索名称/手机/编号"
                clearable
                style="flex:1"
                @keyup.enter="doSearch"
            />
            <el-button type="primary" :icon="Search" @click="doSearch">搜索</el-button>
        </div>

        <el-table
            v-loading="loading"
            :data="tableData"
            size="small"
            highlight-current-row
            @current-change="handleCurrentChange"
        >
            <el-table-column prop="party_name" label="名称" min-width="140" />
            <el-table-column prop="contact_name" label="联系人" width="90" />
            <el-table-column prop="contact_mobile" label="手机" width="120" />
            <el-table-column prop="m_no" label="编号" width="100" />
            <el-table-column label="操作" width="70" fixed="right">
                <template #default="{ row }">
                    <el-button type="primary" link size="small" @click="selectRow(row)">选择</el-button>
                </template>
            </el-table-column>
        </el-table>

        <div class="mt-3 flex justify-between items-center">
            <el-pagination
                v-model:current-page="page"
                :page-size="10"
                :total="total"
                layout="prev, pager, next"
                small background
                @current-change="loadData"
            />
            <!-- 快速新建 -->
            <div v-if="allowCreate" class="flex gap-2 items-center">
                <el-input
                    v-model="quickName"
                    placeholder="输入名称快速新建"
                    size="small"
                    style="width:160px"
                    @keyup.enter="quickCreate"
                />
                <el-button type="success" size="small" @click="quickCreate">新建并选择</el-button>
            </div>
        </div>
    </el-dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Search } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import request from '@/utils/request'

interface Party {
    id: number
    party_name: string
    contact_name?: string
    contact_mobile?: string
    m_no?: string
}

const props = withDefaults(defineProps<{
    modelValue?: number | null
    /** 已选名称（可外部传入回显，不传则自动查询） */
    partyName?: string
    placeholder?: string
    /** supplier=供应商 / customer=客户 / all=不限 */
    partyType?: 'supplier' | 'customer' | 'all'
    clearable?: boolean
    allowCreate?: boolean
}>(), {
    modelValue: null,
    partyName: '',
    placeholder: '点击选择往来单位',
    partyType: 'all',
    clearable: true,
    allowCreate: true,
})

const emit = defineEmits<{
    (e: 'update:modelValue', val: number | null): void
    (e: 'update:partyName', val: string): void
    (e: 'change', party: Party | null): void
}>()

const visible = ref(false)
const loading = ref(false)
const keyword = ref('')
const tableData = ref<Party[]>([])
const page = ref(1)
const total = ref(0)
const quickName = ref('')

const dialogTitle = computed(() => {
    const map = { supplier: '选择供应商', customer: '选择客户', all: '选择往来单位' }
    return map[props.partyType]
})

const displayName = computed(() =>
    props.partyName || (props.modelValue ? `ID:${props.modelValue}` : '')
)

function open() {
    visible.value = true
    keyword.value = ''
    page.value = 1
    loadData()
}

async function loadData() {
    loading.value = true
    try {
        const params: Record<string, any> = {
            keyword: keyword.value,
            page: page.value,
            limit: 10,
        }
        if (props.partyType !== 'all') {
            params.party_type = props.partyType
        }
        const res = await request.get('erp/counterparty/options', { params })
        tableData.value = res.data?.data || res.data || []
        total.value = res.data?.total || tableData.value.length
    } finally {
        loading.value = false
    }
}

function doSearch() {
    page.value = 1
    loadData()
}

function handleCurrentChange(row: Party | null) {
    if (row) selectRow(row)
}

function selectRow(row: Party) {
    emit('update:modelValue', row.id)
    emit('update:partyName', row.party_name)
    emit('change', row)
    visible.value = false
}

function handleClear() {
    emit('update:modelValue', null)
    emit('update:partyName', '')
    emit('change', null)
}

async function quickCreate() {
    const name = quickName.value.trim()
    if (!name) {
        ElMessage.warning('请输入名称')
        return
    }
    try {
        const res = await request.post('erp/counterparty/quick_contact', {
            party_name: name,
            party_type: props.partyType === 'all' ? 'customer' : props.partyType,
        })
        const party = res.data as Party
        selectRow(party)
        quickName.value = ''
    } catch {
        ElMessage.error('新建失败')
    }
}
</script>

<style scoped>
.erp-party-select { cursor: pointer; }
.erp-party-select :deep(.el-input__inner) { cursor: pointer; }
</style>
