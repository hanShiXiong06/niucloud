<template>
    <el-drawer v-model="show" :title="title" size="600px" @open="onOpen" @closed="onClosed">
        <div v-loading="loading">
            <!-- 财务对账 -->
            <div class="mb-4 rounded-lg bg-gray-50 px-4 py-3">
                <div class="mb-2 text-sm font-medium text-gray-600">财务对账（旗下 {{ finance.member_count || 0 }} 名对接人合计）</div>
                <div class="grid grid-cols-4 gap-3 text-center">
                    <div><div class="text-xs text-gray-500">应付(我欠)</div><div class="mt-1 font-semibold text-orange-600">{{ money(finance.payable) }}</div></div>
                    <div><div class="text-xs text-gray-500">应收(欠我)</div><div class="mt-1 font-semibold text-green-600">{{ money(finance.receivable) }}</div></div>
                    <div><div class="text-xs text-gray-500">可折账</div><div class="mt-1 font-semibold text-blue-600">{{ money(finance.offsetable) }}</div></div>
                    <div>
                        <div class="text-xs text-gray-500">净额</div>
                        <div class="mt-1 font-semibold" :class="Number(finance.net) > 0 ? 'text-orange-600' : (Number(finance.net) < 0 ? 'text-green-600' : 'text-gray-400')">
                            {{ money(Math.abs(Number(finance.net || 0))) }}
                            <span class="text-xs text-gray-400">{{ Number(finance.net) > 0 ? '我付' : (Number(finance.net) < 0 ? '我收' : '已平') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 主体信息 -->
            <el-form :model="form" label-width="84px" size="default">
                <el-form-item label="主体名称" required>
                    <el-input v-model.trim="form.name" placeholder="单位/个人名称" />
                </el-form-item>
                <div class="grid grid-cols-2 gap-x-4">
                    <el-form-item label="类型">
                        <el-select v-model="form.counterparty_type" class="w-full">
                            <el-option label="个人" value="individual" />
                            <el-option label="企业" value="company" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="往来角色">
                        <el-select v-model="form.role_type" class="w-full">
                            <el-option label="供应商" value="supplier" />
                            <el-option label="客户" value="customer" />
                            <el-option label="供应商兼客户" value="both" />
                            <el-option label="代卖方" value="consignor" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="联系人">
                        <el-input v-model.trim="form.contact_name" placeholder="对接人姓名" />
                    </el-form-item>
                    <el-form-item label="电话">
                        <el-input v-model.trim="form.mobile" placeholder="联系电话" />
                    </el-form-item>
                </div>
                <el-form-item label="备注">
                    <el-input v-model.trim="form.remark" type="textarea" :rows="2" placeholder="可选" />
                </el-form-item>
            </el-form>

            <!-- 对接人(会员) -->
            <div class="mt-2">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-600">对接人 / 交易人</span>
                    <el-button size="small" type="primary" plain @click="openAddMember">添加对接人</el-button>
                </div>
                <el-table :data="members" size="small" empty-text="暂无对接人">
                    <el-table-column label="姓名" min-width="120">
                        <template #default="{ row }">
                            {{ row.member?.nickname || row.member?.username || ('会员#' + row.member_id) }}
                            <el-tag v-if="row.is_finance_contact === 1" size="small" type="success" effect="light" class="ml-1">财务</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="电话" width="130">
                        <template #default="{ row }">{{ row.member?.mobile || '-' }}</template>
                    </el-table-column>
                    <el-table-column label="角色" width="90" align="center">
                        <template #default="{ row }">{{ roleText(row.relation_role) }}</template>
                    </el-table-column>
                    <el-table-column label="操作" width="70" align="center">
                        <template #default="{ row }">
                            <el-button size="small" type="danger" link @click="doRemoveMember(row)">移除</el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </div>

        <template #footer>
            <div class="flex items-center justify-between">
                <el-button type="danger" plain :loading="deleting" @click="doDelete">删除主体</el-button>
                <div>
                    <el-button @click="show = false">关闭</el-button>
                    <el-button type="primary" :loading="saving" @click="doSave">保存信息</el-button>
                </div>
            </div>
        </template>

        <!-- 添加对接人 -->
        <el-dialog v-model="addDlg.visible" title="添加对接人" width="420px" append-to-body>
            <el-form label-width="72px">
                <el-form-item label="会员" required>
                    <el-select v-model="addDlg.member_id" filterable remote :remote-method="searchMembers" :loading="addDlg.searching"
                        placeholder="搜索昵称/用户名/手机" class="w-full">
                        <el-option v-for="m in addDlg.options" :key="m.member_id"
                            :label="`${m.nickname || m.username || ('会员#' + m.member_id)}${m.mobile ? ' · ' + m.mobile : ''}${m.counterparty_name ? '（现属：' + m.counterparty_name + '）' : ''}`"
                            :value="m.member_id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="角色">
                    <el-select v-model="addDlg.relation_role" class="w-full">
                        <el-option label="业务对接" value="business" />
                        <el-option label="负责人" value="owner" />
                        <el-option label="财务" value="finance" />
                    </el-select>
                </el-form-item>
                <el-form-item label="财务联系人">
                    <el-switch v-model="addDlg.is_finance_contact" :active-value="1" :inactive-value="0" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="addDlg.visible = false">取消</el-button>
                <el-button type="primary" :loading="addDlg.submitting" @click="submitAddMember">确定</el-button>
            </template>
        </el-dialog>
    </el-drawer>
</template>

<script lang="ts" setup>
import { ref, reactive, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    getErpCounterpartyDetail,
    saveErpCounterparty,
    addErpCounterpartyMember,
    removeErpCounterpartyMember,
    deleteErpCounterparty,
    getErpMemberOptions,
} from '@/addon/hsx_erp/api/counterparty'

const props = defineProps<{ modelValue: boolean; entityId: number }>()
const emit = defineEmits(['update:modelValue', 'changed'])

const show = computed({
    get: () => props.modelValue,
    set: (v: boolean) => emit('update:modelValue', v),
})

const money = (v: any) => '¥' + Number(v || 0).toFixed(2)
const roleText = (r: string) => (r === 'owner' ? '负责人' : r === 'finance' ? '财务' : '业务')

const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const finance = reactive<any>({ payable: 0, receivable: 0, net: 0, offsetable: 0, member_count: 0 })
const members = ref<any[]>([])
const form = reactive<any>({ name: '', counterparty_type: 'individual', role_type: 'supplier', contact_name: '', mobile: '', remark: '', status: 1 })
const title = computed(() => '主体 · ' + (form.name || ''))

async function onOpen() {
    if (!props.entityId) return
    loading.value = true
    try {
        const res: any = await getErpCounterpartyDetail(props.entityId)
        const d = res.data || {}
        Object.assign(form, {
            name: d.name || '', counterparty_type: d.counterparty_type || 'individual',
            role_type: d.role_type || 'supplier', contact_name: d.contact_name || '',
            mobile: d.mobile || '', remark: d.remark || '', status: d.status ?? 1,
        })
        Object.assign(finance, d.finance || {})
        members.value = d.members || []
    } finally {
        loading.value = false
    }
}
function onClosed() {
    members.value = []
    Object.assign(finance, { payable: 0, receivable: 0, net: 0, offsetable: 0, member_count: 0 })
}

async function doSave() {
    if (!form.name) return ElMessage.warning('请填写主体名称')
    saving.value = true
    try {
        await saveErpCounterparty(props.entityId, {
            counterparty_type: form.counterparty_type, role_type: form.role_type,
            name: form.name, mobile: form.mobile, contact_name: form.contact_name,
            remark: form.remark, status: form.status,
        })
        ElMessage.success('已保存')
        emit('changed')
    } finally {
        saving.value = false
    }
}

async function doDelete() {
    try {
        await ElMessageBox.confirm('删除该主体将解除其所有对接人归属（财务记录不受影响）。确认删除？', '删除主体', { type: 'warning' })
    } catch { return }
    deleting.value = true
    try {
        await deleteErpCounterparty(props.entityId)
        ElMessage.success('已删除')
        show.value = false
        emit('changed')
    } finally {
        deleting.value = false
    }
}

async function doRemoveMember(row: any) {
    try {
        await ElMessageBox.confirm('从该主体移除此对接人？', '移除对接人', { type: 'warning' })
    } catch { return }
    await removeErpCounterpartyMember(props.entityId, row.member_id)
    ElMessage.success('已移除')
    await onOpen()
    emit('changed')
}

const addDlg = reactive<any>({ visible: false, member_id: undefined, relation_role: 'business', is_finance_contact: 0, options: [], searching: false, submitting: false })
function openAddMember() {
    Object.assign(addDlg, { visible: true, member_id: undefined, relation_role: 'business', is_finance_contact: 0, options: [] })
}
async function searchMembers(kw: string) {
    addDlg.searching = true
    try {
        const res: any = await getErpMemberOptions({ keyword: kw })
        addDlg.options = res.data || []
    } finally {
        addDlg.searching = false
    }
}
async function submitAddMember() {
    if (!addDlg.member_id) return ElMessage.warning('请选择会员')
    addDlg.submitting = true
    try {
        await addErpCounterpartyMember(props.entityId, {
            member_id: addDlg.member_id, relation_role: addDlg.relation_role, is_finance_contact: addDlg.is_finance_contact,
        })
        ElMessage.success('已添加')
        addDlg.visible = false
        await onOpen()
        emit('changed')
    } catch (e: any) {
        ElMessage.error(e?.message || '添加失败')
    } finally {
        addDlg.submitting = false
    }
}
</script>
