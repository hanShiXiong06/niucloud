<template>
    <u-popup :show="show" mode="bottom" :safe-area-inset-bottom="true" round="20" @close="close">
        <view class="member-sheet">
            <view class="sheet-head">
                <view><text class="sheet-title">选择购卡客户</text><text class="sheet-sub">支持姓名、手机号和会员号检索</text></view>
                <u-icon name="close" size="20" color="#94a3b8" @click="close" />
            </view>
            <view class="search-row">
                <u-search v-model="keyword" placeholder="搜索客户" :showAction="false" bgColor="#f1f5f9" @search="load" @clear="load" />
                <view class="create-link" @click="createVisible = true"><u-icon name="plus" color="#2563eb" size="15" /><text>新建</text></view>
            </view>
            <scroll-view scroll-y class="member-list">
                <view v-if="loading" class="loading"><u-loading-icon text="客户加载中" /></view>
                <view v-for="row in rows" :key="row.member_id" class="member-row" @click="choose(row)">
                    <view class="avatar">{{ String(row.display_name || '客').slice(0, 1) }}</view>
                    <view class="member-main"><strong>{{ row.display_name }}</strong><text>{{ row.mobile_masked }}</text></view>
                    <view class="card-count"><text>{{ row.card_count || 0 }}</text><small>张卡</small></view>
                    <u-icon name="arrow-right" color="#cbd5e1" size="16" />
                </view>
                <view v-if="!loading && !rows.length" class="empty"><u-empty text="没有找到客户" mode="search" /></view>
            </scroll-view>
        </view>
    </u-popup>

    <u-popup :show="createVisible" mode="center" round="16" @close="createVisible = false">
        <view class="create-box">
            <view class="create-head">
                <view class="create-icon"><u-icon name="account" color="#2563eb" size="20" /></view>
                <view><text>快速创建客户</text><small>填写资料后立即选中</small></view>
            </view>
            <view class="field"><u-input v-model="create.name" border="none" placeholder="客户姓名" prefixIcon="account" /></view>
            <view class="field"><u-input v-model="create.mobile" border="none" type="number" maxlength="11" placeholder="11 位手机号" prefixIcon="phone" /></view>
            <view class="field"><u-input v-model="create.password" border="none" type="password" maxlength="32" placeholder="初始登录密码" prefixIcon="lock-fill" @input="passwordCustomized = true" /></view>
            <view class="password-tip"><u-icon name="info-circle" color="#64748b" size="13" /><text>默认取手机号后六位，可在创建前修改</text></view>
            <view class="actions">
                <MemberCardButton compact text="取消" @click="createVisible = false" />
                <MemberCardButton compact type="primary" icon="checkmark" text="创建并选中" :loading="creating" @click="submitCreate" />
            </view>
        </view>
    </u-popup>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { getCardMemberOptions, memberCardRequestId, quickCreateCardMember } from '../api'
import MemberCardButton from './MemberCardButton.vue'

const props = defineProps<{ show: boolean }>()
const emit = defineEmits(['update:show', 'select'])
const keyword = ref('')
const rows = ref<any[]>([])
const loading = ref(false)
const createVisible = ref(false)
const creating = ref(false)
const create = ref({ name: '', mobile: '', password: '' })
const passwordCustomized = ref(false)

const close = () => emit('update:show', false)
const load = async () => {
    loading.value = true
    try { rows.value = ((await getCardMemberOptions({ keyword: keyword.value, limit: 40 })) as any)?.data || [] }
    finally { loading.value = false }
}
const choose = (row: any) => { emit('select', row); close() }
const submitCreate = async () => {
    if (!create.value.name.trim() || !/^1\d{10}$/.test(create.value.mobile)) return uni.showToast({ title: '请填写姓名和正确手机号', icon: 'none' })
    if (create.value.password.length < 6 || create.value.password.length > 32 || /\s/.test(create.value.password)) return uni.showToast({ title: '密码需为6至32位且不能含空格', icon: 'none' })
    creating.value = true
    try {
        const data: any = (await quickCreateCardMember({ ...create.value, request_id: memberCardRequestId('member') }))?.data
        uni.showToast({ title: data.created ? '客户已创建' : '已找到原客户', icon: 'none' })
        choose({ member_id: data.member_id, display_name: data.member_name, mobile_masked: data.mobile_masked, card_count: 0 })
        createVisible.value = false
        create.value = { name: '', mobile: '', password: '' }
        passwordCustomized.value = false
    } finally { creating.value = false }
}
watch(() => props.show, visible => { if (visible) { keyword.value = ''; load() } })
watch(() => create.value.mobile, mobile => {
    if (!passwordCustomized.value) create.value.password = /^1\d{5,10}$/.test(mobile) ? mobile.slice(-6) : ''
})
watch(createVisible, visible => {
    if (!visible) return
    create.value = { name: '', mobile: '', password: '' }
    passwordCustomized.value = false
})
</script>

<style scoped lang="scss">
.member-sheet { height: min(900rpx, 78vh); padding: 30rpx 26rpx calc(20rpx + env(safe-area-inset-bottom)); box-sizing: border-box; background: #fff; }
.sheet-head, .search-row, .member-row, .actions { display: flex; align-items: center; justify-content: space-between; gap: 16rpx; }
.sheet-head { align-items: flex-start; }
.sheet-title, .sheet-sub { display: block; }
.sheet-title { color: #0f172a; font-size: 32rpx; font-weight: 750; }
.sheet-sub { margin-top: 6rpx; color: #94a3b8; font-size: 22rpx; }
.search-row { margin: 24rpx 0 10rpx; }
.search-row :deep(.u-search) { min-width: 0; flex: 1; }
.create-link { display: flex; height: 62rpx; padding: 0 17rpx; align-items: center; gap: 6rpx; border: 1rpx solid #bfdbfe; border-radius: 31rpx; background: #eff6ff; color: #2563eb; font-size: 23rpx; white-space: nowrap; }
.member-list { height: calc(100% - 142rpx); }
.member-row { min-height: 92rpx; padding: 12rpx 4rpx; border-bottom: 1rpx solid #eef2f7; }
.avatar { display: flex; width: 62rpx; height: 62rpx; flex: 0 0 62rpx; align-items: center; justify-content: center; border-radius: 50%; background: #dbeafe; color: #2563eb; font-size: 25rpx; font-weight: 700; }
.member-main { display: flex; min-width: 0; flex: 1; flex-direction: column; gap: 6rpx; }
.member-main strong { font-size: 27rpx; }
.member-main text { color: #64748b; font-size: 22rpx; }
.card-count { display: flex; min-width: 56rpx; flex-direction: column; align-items: center; }
.card-count text { color: #2563eb; font-size: 26rpx; font-weight: 700; }
.card-count small { color: #94a3b8; font-size: 18rpx; }
.loading, .empty { padding: 80rpx 0; }
.create-box { width: 620rpx; padding: 26rpx; box-sizing: border-box; }
.create-head { display: flex; margin-bottom: 18rpx; align-items: center; gap: 13rpx; }
.create-icon { display: flex; width: 54rpx; height: 54rpx; align-items: center; justify-content: center; border-radius: 14rpx; background: #eff6ff; }
.create-head > view:last-child { display: flex; flex-direction: column; gap: 5rpx; }
.create-head text { font-size: 30rpx; font-weight: 750; }
.create-head small { color: #94a3b8; font-size: 20rpx; }
.field { margin-top: 12rpx; padding: 20rpx 14rpx; border: 1rpx solid #dfe6ef; border-radius: 13rpx; background: #f8fafc; }
.password-tip { display: flex; margin-top: 10rpx; align-items: center; gap: 7rpx; color: #64748b; font-size: 20rpx; }
.actions { display: grid; grid-template-columns: 1fr 1.35fr; margin-top: 20rpx; }
</style>
