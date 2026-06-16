<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">往来单位</div>
                    <div class="mt-1 text-sm text-gray-500">会员是具体经办人，往来主体是最终结算对象。一个门店可关联多名会员，账款统一归集到门店。</div>
                </div>
                <el-button type="primary" @click="openEdit()">新增往来单位</el-button>
            </div>
            <el-form :inline="true" class="mt-5">
                <el-form-item label="关键词">
                    <el-input v-model.trim="search.keyword" clearable placeholder="名称 / 手机号 / 编号" @keyup.enter="loadData" />
                </el-form-item>
                <el-form-item label="角色">
                    <el-select v-model="search.role_type" clearable class="!w-[140px]" @change="loadData">
                        <el-option label="供应方" value="supplier" />
                        <el-option label="客户" value="customer" />
                        <el-option label="双向往来" value="both" />
                        <el-option label="代卖委托人" value="consignor" />
                    </el-select>
                </el-form-item>
                <el-form-item label="类型">
                    <el-select v-model="search.counterparty_type" clearable class="!w-[120px]" @change="loadData">
                        <el-option label="个人" value="individual" />
                        <el-option label="门店/企业" value="company" />
                    </el-select>
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="search.status" clearable class="!w-[110px]" @change="loadData">
                        <el-option label="启用" :value="1" />
                        <el-option label="停用" :value="0" />
                    </el-select>
                </el-form-item>
                <el-form-item label="手机号">
                    <el-input v-model.trim="search.mobile" clearable class="!w-[140px]" placeholder="手机号" @keyup.enter="loadData" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadData">查询</el-button>
                    <el-button @click="resetSearch">重置</el-button>
                </el-form-item>
            </el-form>
            <el-table :data="table.data" v-loading="table.loading" size="large" @sort-change="onSort">
                <el-table-column prop="counterparty_no" label="编号" min-width="190" sortable="custom" />
                <el-table-column prop="name" label="往来单位" min-width="180" />
                <el-table-column label="类型" width="100">
                    <template #default="{ row }">{{ row.counterparty_type === 'company' ? '企业' : '个人' }}</template>
                </el-table-column>
                <el-table-column label="角色" width="120">
                    <template #default="{ row }">{{ roleName(row.role_type) }}</template>
                </el-table-column>
                <el-table-column prop="mobile" label="手机号" width="140" />
                <el-table-column prop="contact_name" label="联系人" width="120" />
                <el-table-column prop="member_count" label="关联会员" width="100" align="center">
                    <template #default="{ row }">{{ row.member_count || 0 }} 人</template>
                </el-table-column>
                <el-table-column label="来源" width="120">
                    <template #default="{ row }">{{ sourceName(row) }}</template>
                </el-table-column>
                <el-table-column label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 1 ? 'success' : 'info'">{{ row.status === 1 ? '启用' : '停用' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="100" align="center">
                    <template #default="{ row }"><el-button type="primary" link @click="openEdit(row)">编辑/归属</el-button></template>
                </el-table-column>

                <template #empty>
                    <EmptyState
                        v-if="search.keyword || search.role_type"
                        icon="search"
                        title="没有符合条件的往来单位"
                        description="换个关键词或角色筛选再试试。"
                    />
                    <EmptyState
                        v-else
                        icon="folder"
                        title="还没有往来单位"
                        description="往来单位是回收客户、供应商、代卖委托方等结算主体；手工建档或回收同步时也会自动生成。"
                    >
                        <template #action>
                            <el-button type="primary" @click="openEdit()">新增往来单位</el-button>
                        </template>
                    </EmptyState>
                </template>
            </el-table>
            <div class="mt-4 flex justify-end">
                <el-pagination v-model:current-page="table.page" v-model:page-size="table.limit"
                    layout="total, sizes, prev, pager, next" :total="table.total"
                    @size-change="loadData" @current-change="loadData" />
            </div>
        </el-card>

        <el-dialog v-model="dialog.visible" :title="dialog.form.id ? '编辑往来主体' : '新增往来主体'" width="720px">
            <el-form label-width="100px">
                <div class="grid grid-cols-2 gap-x-4">
                    <el-form-item label="单位类型">
                        <el-select v-model="dialog.form.counterparty_type" class="w-full">
                            <el-option label="个人" value="individual" />
                            <el-option label="门店 / 企业" value="company" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="往来角色">
                        <el-select v-model="dialog.form.role_type" class="w-full">
                            <el-option label="供应方" value="supplier" />
                            <el-option label="客户" value="customer" />
                            <el-option label="双向往来" value="both" />
                            <el-option label="代卖委托人" value="consignor" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="名称" required><el-input v-model.trim="dialog.form.name" /></el-form-item>
                    <el-form-item label="手机号"><el-input v-model.trim="dialog.form.mobile" /></el-form-item>
                    <el-form-item label="联系人"><el-input v-model.trim="dialog.form.contact_name" /></el-form-item>
                    <el-form-item label="税号"><el-input v-model.trim="dialog.form.tax_no" /></el-form-item>
                    <el-form-item label="开户行"><el-input v-model.trim="dialog.form.bank_name" /></el-form-item>
                    <el-form-item label="银行账号"><el-input v-model.trim="dialog.form.bank_account" /></el-form-item>
                    <el-form-item label="状态"><el-switch v-model="dialog.form.status" :active-value="1" :inactive-value="0" /></el-form-item>
                </div>
                <el-divider content-position="left">会员归属</el-divider>
                <el-alert type="info" :closable="false" class="mb-4"
                    title="请选择平台已有会员。会员改绑到本主体后，仅影响后续业务归集，历史账目不会被改写。" />
                <el-form-item label="关联会员">
                    <el-select v-model="dialog.form.member_ids" multiple filterable remote reserve-keyword
                        :remote-method="searchMembers" :loading="memberLoading" class="w-full"
                        placeholder="输入会员编号、昵称、账号或手机号搜索" @change="handleMemberChange">
                        <el-option v-for="item in memberOptions" :key="item.member_id"
                            :label="memberLabel(item)" :value="item.member_id">
                            <div class="flex justify-between gap-4">
                                <span>{{ memberLabel(item) }}</span>
                                <span v-if="item.counterparty_name && item.counterparty_id !== dialog.form.id"
                                    class="text-orange-500">当前归属：{{ item.counterparty_name }}</span>
                            </div>
                        </el-option>
                    </el-select>
                </el-form-item>
                <el-form-item label="财务联系人">
                    <el-select v-model="dialog.form.finance_member_id" clearable class="w-full"
                        placeholder="可选，用于该主体对账和结算联系">
                        <el-option v-for="item in selectedMembers" :key="item.member_id"
                            :label="memberLabel(item)" :value="item.member_id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="备注"><el-input v-model.trim="dialog.form.remark" type="textarea" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="dialog.loading" @click="submit">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useListQuery } from '@/addon/hsx_erp/composables/useListQuery'
import { ElMessage } from 'element-plus'
import {
    getErpCounterpartyList, saveErpCounterparty, getErpMemberOptions, getErpCounterpartyMembers
} from '@/addon/hsx_erp/api/counterparty'
import EmptyState from '@/addon/hsx_erp/components/empty-state/index.vue'

const { search, table, loadList: loadData, reset: resetSearch, onSort } = useListQuery({
    api: getErpCounterpartyList,
    defaults: { keyword: '', role_type: '', counterparty_type: '', status: '' as any, mobile: '', sort_field: '', sort_order: '' },
    pageSize: 20,
    immediate: false,
})
const emptyForm = () => ({
    id: 0, counterparty_type: 'individual', role_type: 'supplier', name: '', mobile: '',
    contact_name: '', tax_no: '', bank_name: '', bank_account: '', status: 1, remark: '',
    member_ids: [] as number[], finance_member_id: 0
})
const dialog = reactive<any>({ visible: false, loading: false, form: emptyForm() })
const memberOptions = reactive<any[]>([])
const memberLoading = ref(false)

const openEdit = async (row: any = {}) => {
    dialog.form = { ...emptyForm(), ...row }
    memberOptions.splice(0)
    if (Number(row.id || 0) > 0) {
        const res: any = await getErpCounterpartyMembers(Number(row.id))
        const relations = res.data || []
        const members = relations.map((item: any) => ({
            ...item.member,
            counterparty_id: Number(row.id),
            counterparty_name: row.name
        }))
        memberOptions.push(...members)
        dialog.form.member_ids = members.map((item: any) => Number(item.member_id))
        dialog.form.finance_member_id = Number(
            relations.find((item: any) => Number(item.is_finance_contact) === 1)?.member_id || 0
        )
    }
    dialog.visible = true
}
const submit = async () => {
    if (!dialog.form.name) return ElMessage.warning('请填写往来单位名称')
    dialog.loading = true
    try {
        const members = dialog.form.member_ids.map((memberId: number) => ({
            member_id: memberId,
            relation_role: memberId === dialog.form.finance_member_id ? 'finance' : 'business',
            is_finance_contact: memberId === dialog.form.finance_member_id ? 1 : 0
        }))
        await saveErpCounterparty(Number(dialog.form.id || 0), { ...dialog.form, members })
        ElMessage.success('往来单位已保存')
        dialog.visible = false
        await loadData()
    } finally {
        dialog.loading = false
    }
}
const searchMembers = async (keyword: string) => {
    if (!keyword.trim()) return
    memberLoading.value = true
    try {
        const res: any = await getErpMemberOptions({ keyword })
        const selected = memberOptions.filter(item => dialog.form.member_ids.includes(Number(item.member_id)))
        const merged = [...selected, ...(res.data || [])]
        const unique = new Map(merged.map(item => [Number(item.member_id), item]))
        memberOptions.splice(0, memberOptions.length, ...unique.values())
    } finally {
        memberLoading.value = false
    }
}
const handleMemberChange = (ids: number[]) => {
    if (!ids.includes(Number(dialog.form.finance_member_id))) dialog.form.finance_member_id = 0
}
const selectedMembers = computed(() =>
    memberOptions.filter(item => dialog.form.member_ids.includes(Number(item.member_id)))
)
const memberLabel = (item: any) => {
    const name = item.nickname || item.username || `会员${item.member_id}`
    return `${name}（ID:${item.member_id}${item.mobile ? ` / ${item.mobile}` : ''}）`
}
const roleName = (role: string) => ({
    supplier: '供应方', customer: '客户', both: '双向往来', consignor: '代卖委托人'
}[role] || role)
const sourceName = (row: any) => {
    if (row.source_plugin === 'niucloud' && row.source_type === 'member') return '平台会员'
    if (row.source_plugin === 'hsx_recycle') return '回收订单客户'
    return '手工建立'
}

onMounted(loadData)
</script>
