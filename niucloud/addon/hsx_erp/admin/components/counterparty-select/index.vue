<template>
    <div class="counterparty-select">
        <div v-if="roleType === 'supplier'" class="role-filter">
            <el-check-tag v-for="item in supplierFilters" :key="item.value" :checked="roleFilter === item.value" @change="roleFilter=item.value; onSearch('')">{{ item.label }}</el-check-tag>
        </div>
        <el-select
            v-model="picked"
            filterable
            remote
            clearable
            class="flex-1"
            :remote-method="onSearch"
            :loading="loading || resolving"
            :placeholder="placeholder"
            @change="onPick"
            @clear="onClear"
            @visible-change="visible => visible && !options.length && onSearch('')"
        >
            <el-option v-for="item in options" :key="item.member_id" :value="item.member_id" :label="labelOf(item)">
                <div class="member-option">
                    <div class="member-option__info">
                        <span class="font-medium">{{ nameOf(item) }}</span>
                        <span class="ml-1 text-xs text-gray-400">{{ item.mobile || '无手机' }}</span>
                        <el-tag v-if="item.party_name || item.counterparty_name" size="small" effect="plain" class="ml-2">{{ item.party_name || item.counterparty_name }}</el-tag>
                        <span v-else class="ml-2 text-xs text-orange-500">无主体 · 选后自动建</span>
                        <el-tag v-for="role in item.role_flags || []" :key="role" size="small" type="info" class="ml-1">{{ roleLabel(role) }}</el-tag>
                        <el-tag v-if="creditTag(item)" size="small" :type="creditTag(item).type" class="ml-1">{{ creditTag(item).label }}</el-tag>
                    </div>
                    <div class="member-option__actions">
                        <el-tooltip v-if="item.party_id && roleType === 'customer'" content="客户信用设置" placement="top">
                            <el-button link type="warning" :icon="Lock" aria-label="客户信用设置" @mousedown.stop @click.stop="openCredit(item)" />
                        </el-tooltip>
                        <el-tooltip content="修改会员昵称" placement="top">
                            <el-button link type="primary" :icon="EditPen" aria-label="修改会员昵称" @mousedown.stop @click.stop="editMemberNickname(item)" />
                        </el-tooltip>
                    </div>
                </div>
            </el-option>
        </el-select>
        <el-button @click="createVisible = true">新建</el-button>

        <HsxDialog :confirm-loading="creating" v-model="createVisible" title="新增客户或往来主体" width="460px" append-to-body @closed="resetCreate" :destroy-on-close="false">
            <el-segmented v-model="createMode" :options="[{label:'客户账号',value:'member'},{label:'仅往来主体',value:'party'}]" class="mb-4" />
            <el-form label-width="84px">
                <el-form-item label="姓名" required>
                    <el-input v-model.trim="createForm.name" placeholder="对接人姓名" />
                </el-form-item>
                <el-form-item label="手机号" :required="createMode === 'member'">
                    <el-input v-model.trim="createForm.mobile" placeholder="用于建档和对账锚点" />
                </el-form-item>
                <el-form-item label="M号">
                    <el-input v-model.trim="createForm.m_no" placeholder="可选，客户M号/业务编号" />
                </el-form-item>
            </el-form>
            <div class="-mt-2 mb-2 text-xs text-gray-400">{{ createMode === 'member' ? '创建可登录客户账号并绑定 ERP 主体。' : '只建立业务往来主体，适合临时客户、供应商和整备服务商。' }}</div>
            <template #footer>
                <el-button :disabled="creating" @click="createVisible = false">取消</el-button>
                <el-button :disabled="creating" type="primary" :loading="creating" @click="doCreate">建并选用</el-button>
            </template>
        </HsxDialog>
        <ErpPartyCreditDialog v-model="creditVisible" :party="creditParty" @saved="onCreditSaved" />
    </div>
</template>

<script setup lang="ts">
import { HsxDialog, useFeedback } from '@/addon/hsx_components/core'
import { ref, watch } from 'vue'
import { ElMessageBox } from 'element-plus'
import { EditPen, Lock } from '@element-plus/icons-vue'
import { getErpMemberOptions, quickCreateErpParty, resolveErpContact } from '@/addon/hsx_erp/api/counterparty'
import { editMemberDetail } from '@/app/api/member'
import ErpPartyCreditDialog from '@/addon/hsx_erp/components/ErpPartyCreditDialog.vue'
const hsxFeedback = useFeedback()


const props = withDefaults(defineProps<{
    modelValue?: number | string
    roleType?: string
    placeholder?: string
    valueField?: 'party_id' | 'counterparty_id' | 'member_id' | 'id'
}>(), {
    modelValue: 0,
    roleType: 'customer',
    placeholder: '搜索姓名 / 手机号选择对接人',
    valueField: 'party_id'
})

const emit = defineEmits(['update:modelValue', 'resolved'])

const picked = ref<number | undefined>(undefined)
const options = ref<any[]>([])
const loading = ref(false)
const resolving = ref(false)
const creditVisible = ref(false)
const creditParty = ref<any>(null)
const roleFilter = ref('all')
const supplierFilters = [{label:'全部人员',value:'all'},{label:'采购供货商',value:'purchase_supplier'},{label:'整备服务商',value:'refurbish_provider'}]
const roleLabel = (role:string) => ({purchase_supplier:'采购',refurbish_provider:'整备',sale_customer:'销售',recycle_customer:'回收'} as Record<string,string>)[role] || '其他'
const creditTag = (item: any) => {
    const profile = item?.credit_profile
    if (!profile) return null
    if (profile.policy === 'blocked') return { label: '暂停交易', type: 'danger' }
    if (profile.policy === 'cash_only' || profile.can_credit === false) return { label: '仅现结', type: 'warning' }
    if (profile.has_outstanding) return { label: `欠款 ¥${Number(profile.outstanding_amount || 0).toFixed(2)}`, type: 'warning' }
    return null
}

const nameOf = (item: any) => item.nickname || item.username || item.member_name || '姓名未登记'
const labelOf = (item: any) => {
    const partyName = item.party_name || item.counterparty_name
    return [nameOf(item), item.mobile, partyName].filter(Boolean).join(' / ')
}

async function onSearch(keyword: string) {
    loading.value = true
    try {
        const res: any = await getErpMemberOptions({ keyword: keyword || '', role_filter: roleFilter.value })
        options.value = res?.data || []
    } catch (error: any) {
        options.value = []
        hsxFeedback.error(error?.msg || error?.message || '对接人列表加载失败')
    } finally {
        loading.value = false
    }
}

async function onPick(memberId: number) {
    if (!memberId) return onClear()
    resolving.value = true
    try {
        const res: any = await resolveErpContact({ member_id: memberId, role_type: roleFilter.value !== 'all' ? roleFilter.value : props.roleType })
        const data = normalize(res?.data || {})
        emit('update:modelValue', data[props.valueField] || data.party_id || 0)
        emit('resolved', data)
        if (data.auto_created) hsxFeedback.success(`已为「${data.member_name || ''}」自动创建往来主体`)
    } catch (error: any) {
        hsxFeedback.error(error?.msg || error?.message || '对接人解析失败')
        onClear()
    } finally {
        resolving.value = false
    }
}

async function editMemberNickname(item: any) {
    const memberId = Number(item?.member_id || 0)
    if (!memberId) return hsxFeedback.warning('该记录没有关联会员')
    try {
        const { value } = await ElMessageBox.prompt(
            '这里只修改会员昵称，不会修改 ERP 往来主体名称和历史单据。',
            '修改会员昵称',
            {
                confirmButtonText: '保存',
                cancelButtonText: '取消',
                inputValue: nameOf(item),
                inputPlaceholder: '请输入会员昵称',
                inputValidator: value => {
                    const name = String(value || '').trim()
                    if (!name) return '会员昵称不能为空'
                    if (name.length > 50) return '会员昵称不能超过50个字符'
                    return true
                }
            }
        )
        const nickname = String(value || '').trim()
        await editMemberDetail({ member_id: memberId, field: 'nickname', value: nickname })
        item.nickname = nickname
    } catch (error: any) {
        if (error !== 'cancel' && error !== 'close') {
            hsxFeedback.error(error?.msg || error?.message || '会员昵称修改失败')
        }
    }
}

function openCredit(item: any) {
    if (!Number(item?.party_id || 0)) return hsxFeedback.warning('请先选择该会员建立往来主体')
    creditParty.value = item
    creditVisible.value = true
}

function onCreditSaved(profile: any) {
    if (creditParty.value) creditParty.value.credit_profile = profile
}

function onClear() {
    picked.value = undefined
    emit('update:modelValue', 0)
    emit('resolved', null)
}

const createVisible = ref(false)
const createMode = ref<'member' | 'party'>('member')
const creating = ref(false)
const createForm = ref({ name: '', mobile: '', m_no: '' })

function resetCreate() {
    createForm.value = { name: '', mobile: '', m_no: '' }
}

async function doCreate() {
    if (!createForm.value.name) return hsxFeedback.warning('请填写姓名')
    if (createMode.value === 'member' && !createForm.value.mobile) return hsxFeedback.warning('请填写手机号')
    creating.value = true
    try {
        const roleType = roleFilter.value !== 'all' ? roleFilter.value : props.roleType
        const res: any = createMode.value === 'member'
            ? await resolveErpContact({ ...createForm.value, role_type: roleType })
            : await quickCreateErpParty({ ...createForm.value, role_type: roleType })
        const data = normalize(res?.data || {})
        if (data.member_id) options.value = [{ member_id: data.member_id, nickname: data.member_name, mobile: data.mobile, party_name: data.party_name, m_no: data.m_no }, ...options.value]
        picked.value = Number(data.member_id || 0) || undefined
        emit('update:modelValue', data[props.valueField] || data.party_id || 0)
        emit('resolved', data)
        createVisible.value = false
        hsxFeedback.success('对接人已创建并选用')
    } catch (error: any) {
        hsxFeedback.error(error?.msg || error?.message || '对接人创建失败')
    } finally {
        creating.value = false
    }
}

function normalize(data: any) {
    return {
        ...data,
        id: data.party_id || data.counterparty_id || data.id || 0,
        party_id: data.party_id || data.counterparty_id || data.id || 0,
        party_name: data.party_name || data.counterparty_name || data.name || '',
        counterparty_id: data.counterparty_id || data.party_id || data.id || 0,
        counterparty_name: data.counterparty_name || data.party_name || data.name || ''
    }
}

watch(() => props.modelValue, value => {
    if (!value) picked.value = undefined
})
</script>

<style scoped>
.counterparty-select {
    display: flex;
    flex-wrap: wrap;
    width: 100%;
    align-items: center;
    gap: 8px;
}
.role-filter { display:flex; width:100%; gap:6px; }
.member-option { display:flex; align-items:center; justify-content:space-between; gap:12px; width:100%; }
.member-option__info { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.member-option__actions { display:flex; align-items:center; flex:none; }
</style>
