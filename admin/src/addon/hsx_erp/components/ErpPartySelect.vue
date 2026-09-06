<template>
    <!-- 触发区域：显示已选名称 + 清除按钮 -->
    <div v-bind="attrs" class="erp-party-select" @click="open">
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
    <HsxDialog
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
        <div v-if="partyType === 'supplier'" class="mb-3 flex gap-2">
            <el-check-tag v-for="item in supplierFilters" :key="item.value" :checked="activeRoleFilter === item.value" @change="activeRoleFilter = item.value; doSearch()">{{ item.label }}</el-check-tag>
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
            <el-table-column label="身份" min-width="120">
                <template #default="{ row }"><el-tag v-for="role in row.role_flags || []" :key="role" size="small" class="mr-1">{{ roleLabel(role) }}</el-tag></template>
            </el-table-column>
            <el-table-column label="信用" width="110">
                <template #default="{ row }"><el-tag v-if="row.credit_profile?.policy !== 'normal' || row.credit_profile?.has_outstanding" size="small" :type="row.credit_profile?.can_sale === false ? 'danger' : 'warning'">{{ row.credit_profile?.policy_label || '有欠款' }}</el-tag><span v-else class="text-gray-400">正常</span></template>
            </el-table-column>
            <el-table-column label="操作" width="150" fixed="right">
                <template #default="{ row }">
                    <el-button type="primary" link size="small" @click.stop="openManage(row)">管理</el-button>
                    <el-button v-if="(row.role_flags || []).includes('sale_customer')" type="warning" link size="small" @click.stop="openCredit(row)">信用</el-button>
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
                    placeholder="输入名称新增主体"
                    size="small"
                    style="width:160px"
                    @keyup.enter="quickCreate"
                />
                <el-button type="success" size="small" @click="quickCreate">新增主体</el-button>
            </div>
        </div>
    </HsxDialog>
    <HsxDialog :confirm-loading="manageSaving" v-model="manageVisible" title="往来主体身份管理" width="520px" append-to-body :destroy-on-close="false">
        <el-form label-width="86px"><el-form-item label="主体名称"><el-input v-model="manageForm.party_name" /></el-form-item><el-form-item label="联系电话"><el-input v-model="manageForm.contact_mobile" /></el-form-item><el-form-item label="多重身份"><el-checkbox-group v-model="manageForm.role_flags"><el-checkbox v-for="item in roleOptions" :key="item.value" :label="item.value">{{ item.label }}</el-checkbox></el-checkbox-group></el-form-item><el-form-item label="业务分组"><el-select v-model="manageForm.group_keys" multiple allow-create filterable default-first-option placeholder="选择或输入分组"><el-option label="重点客户" value="key_customer" /><el-option label="长期合作" value="long_term" /><el-option label="整备服务" value="refurbish" /></el-select></el-form-item></el-form>
        <template #footer><el-button :disabled="manageSaving" @click="manageVisible=false">取消</el-button><el-button :disabled="manageSaving" type="primary" :loading="manageSaving" @click="saveManage">保存</el-button></template>
    </HsxDialog>
    <ErpPartyCreditDialog v-model="creditVisible" :party="creditParty" @saved="onCreditSaved" />
</template>

<script lang="ts">
export default {
    inheritAttrs: false,
}
</script>

<script setup lang="ts">
import { HsxDialog, useFeedback } from '@/addon/hsx_components/core'
import { ref, computed, useAttrs } from 'vue'
import { Search } from '@element-plus/icons-vue'

import request from '@/utils/request'
import ErpPartyCreditDialog from '@/addon/hsx_erp/components/ErpPartyCreditDialog.vue'
const hsxFeedback = useFeedback()


interface Party {
    id: number
    party_name: string
    contact_name?: string
    contact_mobile?: string
    m_no?: string
    role_flags?: string[]
    credit_profile?: any
}

const attrs = useAttrs()

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
const manageVisible = ref(false)
const manageSaving = ref(false)
const creditVisible = ref(false)
const creditParty = ref<any>(null)
const manageForm = ref<any>({ id: 0, party_name: '', contact_mobile: '', role_flags: [], group_keys: [] })
const roleOptions = [{label:'采购供货商',value:'purchase_supplier'},{label:'销售客户',value:'sale_customer'},{label:'回收客户',value:'recycle_customer'},{label:'整备服务商',value:'refurbish_provider'}]
const activeRoleFilter = ref('all')
const supplierFilters = [
    { label: '全部', value: 'all' },
    { label: '采购供货商', value: 'purchase_supplier' },
    { label: '整备服务商', value: 'refurbish_provider' },
]
const roleLabel = (role: string) => ({ purchase_supplier: '采购供货商', refurbish_provider: '整备服务商', sale_customer: '销售客户', recycle_customer: '回收客户' } as Record<string, string>)[role] || '其他'

const dialogTitle = computed(() => {
    const map = { supplier: '选择供应商', customer: '选择客户', all: '选择往来单位' }
    return map[props.partyType]
})

const displayName = computed(() =>
    props.partyName || (props.modelValue ? '已选择往来单位' : '')
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
            paginate: 1,
        }
        if (activeRoleFilter.value !== 'all') params.role_type = activeRoleFilter.value
        else if (props.partyType !== 'all') params.role_type = props.partyType
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

function openManage(row: Party) { manageForm.value = { ...row, role_flags: [...(row.role_flags || [])], group_keys: [...((row as any).group_keys || [])] }; manageVisible.value = true }
function openCredit(row: Party) { creditParty.value = row; creditVisible.value = true }
function onCreditSaved(profile: any) { if (creditParty.value) creditParty.value.credit_profile = profile }
async function saveManage() {
    if (!manageForm.value.party_name || !manageForm.value.role_flags.length) return hsxFeedback.warning('请填写名称并至少选择一个身份')
    manageSaving.value = true
    try { await request.post(`erp/counterparty/update/${manageForm.value.id}`, manageForm.value); hsxFeedback.success('主体身份已更新'); manageVisible.value = false; await loadData() }
    finally { manageSaving.value = false }
}

async function quickCreate() {
    const name = quickName.value.trim()
    if (!name) {
        hsxFeedback.warning('请输入名称')
        return
    }
    try {
        const res = await request.post('erp/counterparty/quick_party', {
            name,
            role_type: activeRoleFilter.value !== 'all' ? activeRoleFilter.value : (props.partyType === 'all' ? 'customer' : props.partyType),
        })
        const party = res.data as Party
        selectRow(party)
        quickName.value = ''
    } catch {
        hsxFeedback.error('新建失败')
    }
}
</script>

<style scoped>
.erp-party-select { width: 100%; min-width: 0; cursor: pointer; }
.erp-party-select :deep(.el-input) { width: 100%; min-width: 0; }
.erp-party-select :deep(.el-input__inner) { cursor: pointer; }
</style>
