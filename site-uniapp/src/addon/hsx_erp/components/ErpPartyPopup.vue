<!--
  ErpPartyPopup - 往来单位选择弹窗（对齐PC端 CounterpartySelect 逻辑）

  正确流程：
  1. 搜索会员列表（erp/counterparty/member_options）
  2. 用户选中会员
  3. 调用 resolve_contact(member_id, role_type) 获取或创建往来主体
  4. 统一返回 party_id + party_name + member_name
-->
<template>
    <u-popup :show="show" mode="bottom" :safe-area-inset-bottom="true" border-radius="32rpx" @close="close">
        <view class="popup-wrap">
            <view class="popup-header">
                <text class="popup-title">{{ editingMember ? '修改会员昵称' : (existingOnly ? '选择往来主体' : (roleType === 'supplier' ? '选择供应商' : '选择客户')) }}</text>
                <view class="popup-header__actions">
                    <text v-if="editingMember" class="create-link" @click="cancelMemberEdit">返回列表</text>
                    <text v-else-if="existingOnly && partyId" class="create-link" @click="clearSelection">清除筛选</text>
                    <text v-else-if="!existingOnly" class="create-link" @click="showCreate = !showCreate">{{ showCreate ? '返回列表' : '新增' }}</text>
                    <u-icon name="close" size="20" color="#94a3b8" @click="close" />
                </view>
            </view>
            <view v-if="editingMember" class="create-panel">
                <view class="member-edit-summary">
                    <text class="member-edit-summary__name">{{ editingMember.nickname || editingMember.username || '未命名会员' }}</text>
                    <text class="member-edit-summary__meta">{{ editingMember.mobile || '未填写手机号' }}{{ editingMember.member_no ? ` · ${editingMember.member_no}` : '' }}</text>
                </view>
                <u-input v-model="editNickname" border="surround" maxlength="50" placeholder="请输入会员昵称" clearable />
                <text class="create-help">这里只修改会员昵称，不会修改 ERP 往来主体名称和历史单据。</text>
                <view class="create-submit"><u-button type="primary" :loading="savingNickname" text="保存昵称" @click="saveMemberNickname" /></view>
            </view>
            <view v-else-if="showCreate" class="create-panel">
                <view class="create-tabs">
                    <view :class="{ active: createMember }" @click="createMember = true">新增客户账号</view>
                    <view :class="{ active: !createMember }" @click="createMember = false">仅新增往来主体</view>
                </view>
                <text class="create-help">{{ createMember ? '适合需要登录、查看订单的客户；自动建立会员和 ERP 主体。' : '适合临时客户或服务商，不创建登录账号。' }}</text>
                <u-input v-model="createName" border="surround" placeholder="姓名或主体名称" />
                <u-input v-model="createMobile" border="surround" :placeholder="createMember ? '手机号（必填）' : '联系电话（选填）'" />
                <u-button type="primary" :loading="creating" text="保存并选择" @click="createAndSelect" />
            </view>
            <template v-else>
            <view class="popup-search">
                <u-search
                    v-model="keyword"
                    :placeholder="existingOnly ? '搜索主体名称 / 联系人 / 手机 / M号' : '搜索姓名 / 手机号 / 会员号'"
                    :showAction="false"
                    bgColor="#f1f5f9"
                    height="34"
                    @search="onSearch"
                    @clear="onSearch"
                />
                <text class="popup-hint">{{ existingOnly ? '选择已有往来主体进行精确筛选，不会新建或修改往来关系' : (roleType === 'supplier' ? '全部会员都可以作为供货商，选择后自动建立供应商往来关系' : '全部会员都可以作为客户，选择后自动建立客户往来关系') }}</text>
                <view v-if="roleType === 'supplier'" class="role-filters">
                    <view v-for="item in supplierFilters" :key="item.value" :class="{ active: activeRoleFilter === item.value }" @click="setRoleFilter(item.value)">{{ item.label }}</view>
                </view>
            </view>
            <scroll-view scroll-y class="popup-list" lower-threshold="120" @scrolltolower="loadMore">
                <view v-if="loading" class="popup-loading"><u-loading-icon size="24" /></view>
                <template v-else>
                    <view
                        v-for="item in list"
                        :key="item.member_id"
                        class="party-item"
                        :class="{ selected: item.party_id === partyId }"
                        @click="selectMember(item)"
                    >
                        <view class="party-item__main">
                            <view class="party-item__left">
                                <view class="party-item__name-row">
                                    <text class="party-item__name">
                                        {{ item.party_name || item.counterparty_name || item.nickname || item.username || '未命名' }}
                                    </text>
                                    <view v-if="!existingOnly" class="party-item__edit" @click.stop="beginMemberEdit(item)">
                                        <u-icon name="edit-pen" color="#2563eb" size="14" />
                                        <text>改会员名</text>
                                    </view>
                                </view>
                                <text v-if="item.party_name && (item.nickname || item.username)" class="party-item__member">
                                    会员：{{ item.nickname || item.username }}
                                </text>
                            </view>
                            <view class="party-item__right">
                                <text class="party-item__mobile">{{ item.mobile }}</text>
                                <text v-if="item.m_no" class="party-item__mno">M号 {{ item.m_no }}</text>
                                 <text v-if="item.party_id" class="party-item__badge">
                                    <u-tag text="已有往来主体" type="success" plain plainFill size="mini" />
                                </text>
                                <text v-if="creditLabel(item)" class="party-item__badge">
                                    <u-tag :text="creditLabel(item)" :type="item.credit_profile?.can_sale === false ? 'error' : 'warning'" plain plainFill size="mini" />
                                </text>
                            </view>
                        </view>

                    </view>
                    <view class="popup-empty" v-if="!list.length && !loading">
                        <u-empty mode="search" text="未找到匹配的会员" :image-size="60" />
                        <text class="popup-empty__hint">请先在会员模块添加该用户</text>
                    </view>
                    <view v-if="list.length" class="load-more">
                        <u-loading-icon v-if="loadingMore" size="18" text="加载更多" />
                        <text v-else>{{ hasMore ? '继续上滑加载更多' : '已经到底了' }}</text>
                    </view>
                </template>
            </scroll-view>
            </template>
        </view>
        <u-overlay :show="resolving">
            <view class="resolving-wrap">
                <u-loading-icon size="32" color="#fff" />
                <text class="resolving-text">正在处理往来主体...</text>
            </view>
        </u-overlay>
    </u-popup>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import request from '@/utils/request'
import { editMemberField } from '@/app/api/member'

const props = withDefaults(defineProps<{
    show: boolean
    roleType?: 'supplier' | 'customer'
    partyId?: number
    partyName?: string
    memberName?: string
    /** 仅选择已绑定主体，用于列表筛选；不会创建主体或修改会员关系。 */
    existingOnly?: boolean
    initialRoleFilter?: 'all' | 'purchase_supplier' | 'refurbish_provider'
    roleContext?: 'supplier' | 'customer' | 'purchase_supplier' | 'refurbish_provider' | 'recycle_customer'
}>(), {
    show: false,
    roleType: 'customer',
    partyId: 0,
    partyName: '',
    memberName: '',
    existingOnly: false,
    initialRoleFilter: 'all',
    roleContext: undefined,
})

const emit = defineEmits<{
    (e: 'update:show', v: boolean): void
    (e: 'update:partyId', v: number): void
    (e: 'update:partyName', v: string): void
    (e: 'update:memberName', v: string): void
    (e: 'select', v: any): void
}>()

const keyword = ref('')
const list = ref<any[]>([])
const loading = ref(false)
const loadingMore = ref(false)
const resolving = ref(false)
const creating = ref(false)
const savingNickname = ref(false)
const showCreate = ref(false)
const editingMember = ref<any>(null)
const editNickname = ref('')
const createMember = ref(true)
const createName = ref('')
const createMobile = ref('')
const activeRoleFilter = ref<'all' | 'purchase_supplier' | 'refurbish_provider'>('all')
const supplierFilters = [
    { label: '全部人员', value: 'all' as const },
    { label: '采购供货商', value: 'purchase_supplier' as const },
    { label: '整备服务商', value: 'refurbish_provider' as const },
]
const page = ref(1)
const hasMore = ref(true)
const creditLabel = (item: any) => {
    const profile = item?.credit_profile
    if (!profile) return ''
    if (profile.policy === 'blocked') return '暂停交易'
    if (profile.policy === 'cash_only' || profile.can_credit === false) return '仅现结'
    if (profile.has_outstanding) return `欠款 ¥${Number(profile.outstanding_amount || 0).toFixed(2)}`
    return ''
}

watch(() => props.show, (v) => {
    if (v) {
        keyword.value = ''
        showCreate.value = false
        editingMember.value = null
        editNickname.value = ''
        activeRoleFilter.value = props.initialRoleFilter
        search(true)
    }
})

async function search(reset = true) {
    if (reset) { page.value = 1; hasMore.value = true; list.value = [] }
    if (!hasMore.value || loading.value || loadingMore.value) return
    if (reset) loading.value = true
    else loadingMore.value = true
    try {
        // 筛选场景直接查询往来主体，避免一个主体绑定多个会员时出现重复项。
        // 业务选择场景仍从会员入口解析/创建主体，保留原有快速建档能力。
        const endpoint = props.existingOnly ? 'erp/counterparty/options' : 'erp/counterparty/member_options'
        const params: Record<string, any> = { keyword: keyword.value, page: page.value, limit: 30, paginate: 1 }
        if (activeRoleFilter.value !== 'all') {
            if (props.existingOnly) params.role_type = activeRoleFilter.value
            else params.role_filter = activeRoleFilter.value
        }
        const res: any = await request.get(endpoint, params, { showLoading: false })
        const rows = Array.isArray(res?.data) ? res.data : (res?.data?.data || [])
        list.value = reset ? rows : [...list.value, ...rows]
        const current = Number(res?.data?.current_page || page.value)
        const last = Number(res?.data?.last_page || current)
        hasMore.value = current < last
        if (hasMore.value) page.value = current + 1
    } catch { if (reset) list.value = [] }
    finally { loading.value = false; loadingMore.value = false }
}

function loadMore() { search(false) }
function onSearch() { search(true) }
function setRoleFilter(value: 'all' | 'purchase_supplier' | 'refurbish_provider') { activeRoleFilter.value = value; search(true) }

function beginMemberEdit(member: any) {
    if (!Number(member?.member_id || 0)) return uni.showToast({ title: '该记录没有关联会员', icon: 'none' })
    showCreate.value = false
    editingMember.value = member
    editNickname.value = String(member.nickname || member.username || '').trim()
}

function cancelMemberEdit() {
    editingMember.value = null
    editNickname.value = ''
}

async function saveMemberNickname() {
    const member = editingMember.value
    const nickname = editNickname.value.trim()
    if (!member?.member_id) return uni.showToast({ title: '该记录没有关联会员', icon: 'none' })
    if (!nickname) return uni.showToast({ title: '请输入会员昵称', icon: 'none' })
    savingNickname.value = true
    try {
        await editMemberField(Number(member.member_id), 'nickname', nickname)
        member.nickname = nickname
        cancelMemberEdit()
    } catch (e: any) {
        uni.showToast({ title: e?.msg || e?.message || '会员昵称修改失败', icon: 'none' })
    } finally {
        savingNickname.value = false
    }
}

async function selectMember(member: any) {
    if (props.existingOnly) {
        const partyId = Number(member?.party_id || member?.counterparty_id || 0)
        if (partyId <= 0) return uni.showToast({ title: '往来主体数据异常', icon: 'none' })
        const partyName = String(member.party_name || member.counterparty_name || '').trim()
        const memberName = String(member.nickname || member.username || '').trim()
        const party = { ...member, party_id: partyId, party_name: partyName, member_name: memberName }
        emit('update:partyId', partyId)
        emit('update:partyName', partyName)
        emit('update:memberName', memberName)
        emit('select', party)
        close()
        return
    }
    resolving.value = true
    try {
        // 第二步：获取已有主体 or 自动创建并绑定
        const res: any = await request.post('erp/counterparty/resolve_contact', {
            member_id: member.member_id,
            name: member.party_name || member.counterparty_name || member.nickname || member.username,
            mobile: member.mobile,
            role_type: props.roleContext || (activeRoleFilter.value !== 'all' ? activeRoleFilter.value : props.roleType),
        })
        const party = res?.data || {}
        const partyId = Number(party.party_id || party.counterparty_id || 0)
        if (partyId <= 0) { uni.showToast({ title: '获取往来主体失败', icon: 'none' }); return }
        const partyName = String(party.party_name || party.counterparty_name || '').trim()
        const memberName = String(party.member_name || member.nickname || member.username || '').trim()
        const selectedParty = { ...party, party_id: partyId, party_name: partyName, member_name: memberName }
        emit('update:partyId', partyId)
        emit('update:partyName', partyName)
        emit('update:memberName', memberName)
        emit('select', selectedParty)
        close()
    } catch (e: any) {
        uni.showToast({ title: e?.message || '操作失败', icon: 'none' })
    } finally { resolving.value = false }
}

async function createAndSelect() {
    if (!createName.value.trim()) return uni.showToast({ title: '请填写名称', icon: 'none' })
    if (createMember.value && !createMobile.value.trim()) return uni.showToast({ title: '请填写手机号', icon: 'none' })
    creating.value = true
    try {
        const roleType = props.roleContext || (activeRoleFilter.value !== 'all' ? activeRoleFilter.value : props.roleType)
        const url = createMember.value ? 'erp/counterparty/quick_contact' : 'erp/counterparty/quick_party'
        const res: any = await request.post(url, { name: createName.value.trim(), mobile: createMobile.value.trim(), role_type: roleType })
        const party = res?.data || {}
        if (!party.party_id && !party.id) throw new Error('创建往来主体失败')
        const partyId = Number(party.party_id || party.id)
        const partyName = String(party.party_name || party.counterparty_name || createName.value.trim())
        emit('update:partyId', partyId)
        emit('update:partyName', partyName)
        const memberName = String(party.member_name || (createMember.value ? createName.value.trim() : ''))
        emit('update:memberName', memberName)
        emit('select', { ...party, party_id: partyId, party_name: partyName, member_name: memberName })
        createName.value = ''; createMobile.value = ''; close()
    } catch (e: any) { uni.showToast({ title: e?.message || '创建失败', icon: 'none' }) }
    finally { creating.value = false }
}

function close() { emit('update:show', false) }
function clearSelection() {
    emit('update:partyId', 0)
    emit('update:partyName', '')
    emit('update:memberName', '')
    emit('select', null)
    close()
}
</script>

<style scoped lang="scss">
.popup-wrap { height: 78vh; display: flex; flex-direction: column; }
.popup-header { display: flex; align-items: center; justify-content: space-between; padding: 28rpx 32rpx 16rpx; }
.popup-title { font-size: 32rpx; font-weight: 700; color: #0f172a; }
.popup-header__actions { display:flex; align-items:center; gap:24rpx; }
.create-link { color:#2563eb; font-size:24rpx; font-weight:600; }
.role-filters,.create-tabs { display:flex; gap:12rpx; margin-top:16rpx; }
.role-filters view,.create-tabs view { padding:10rpx 18rpx; border-radius:999rpx; background:#f1f5f9; color:#64748b; font-size:21rpx; }
.role-filters view.active,.create-tabs view.active { background:#dbeafe; color:#2563eb; font-weight:600; }
.create-panel { display:flex; flex-direction:column; gap:18rpx; padding:0 28rpx 28rpx; }
.create-help { color:#64748b; font-size:21rpx; line-height:1.5; }
.create-submit { width:100%; }
.member-edit-summary { padding:18rpx 20rpx; border-radius:8rpx; background:#f8fafc; }
.member-edit-summary__name,.member-edit-summary__meta { display:block; }
.member-edit-summary__name { color:#0f172a; font-size:27rpx; font-weight:600; }
.member-edit-summary__meta { margin-top:5rpx; color:#64748b; font-size:22rpx; }
.popup-search { padding: 0 24rpx 16rpx; }
.popup-hint { display:block; margin-top:12rpx; color:#64748b; font-size:21rpx; line-height:1.45; }
.popup-list { flex: 1; overflow-y: auto; padding: 0 24rpx; gap: 16rpx; box-sizing: border-box; }
.popup-loading { display: flex; justify-content: center; padding: 48rpx; }
.popup-empty { padding: 32rpx 0; text-align: center; }
.popup-empty__hint { font-size: 24rpx; color: #94a3b8; display: block; margin-top: 12rpx; }
.load-more { display:flex; align-items:center; justify-content:center; min-height:80rpx; color:#94a3b8; font-size:21rpx; }
.party-item {
    padding: 20rpx 12rpx; border-radius: 8rpx; border-bottom: 1rpx solid #f1f5f9;
    &.selected { background: #eff6ff; }
    &:active { background: #f8fafc; }
}
.party-item__main { display: flex; align-items: flex-start; justify-content: space-between; }
.party-item__left { flex: 1; }
.party-item__right { text-align: right; flex-shrink: 0; }
.party-item__name-row { display:flex; align-items:center; gap:12rpx; min-width:0; }
.party-item__name { font-size: 28rpx; font-weight: 600; color: #0f172a; display: block; }
.party-item__edit { display:flex; align-items:center; flex:none; gap:4rpx; padding:5rpx 8rpx; color:#2563eb; font-size:20rpx; }
.party-item__member { font-size: 22rpx; color: #94a3b8; display: block; margin-top: 4rpx; }
.party-item__mobile { font-size: 26rpx; color: #64748b; display: block; }
.party-item__mno { font-size: 22rpx; color: #94a3b8; display: block; margin-top: 4rpx; }
.party-item__badge { margin-top: 8rpx; }
.resolving-wrap { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; gap: 16rpx; }
.resolving-text { font-size: 28rpx; color: #fff; }
</style>
