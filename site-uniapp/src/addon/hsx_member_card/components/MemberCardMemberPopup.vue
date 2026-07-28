<template>
    <view class="relative z-[12000]">
        <u-popup :show="props.show" mode="bottom" :safe-area-inset-bottom="true" round="20" :zIndex="12000" @close="close">
            <view class="box-border h-[78vh] max-h-[900rpx] bg-white px-[24rpx] pt-[28rpx]" :style="{ paddingBottom: 'calc(20rpx + env(safe-area-inset-bottom))' }">
                <view class="flex items-start justify-between gap-[16rpx]">
                    <view class="flex min-w-0 flex-1 flex-col">
                        <text class="text-[32rpx] font-bold leading-[1.35] text-[#334155]">选择购卡客户</text>
                        <text class="mt-[7rpx] text-[23rpx] leading-[1.45] text-[#8290a5]">姓名、手机号或会员号均可检索</text>
                    </view>
                    <view class="-mt-[8rpx] flex h-[56rpx] w-[56rpx] items-center justify-center rounded-full bg-[#f8fafc]" @click="close"><u-icon name="close" size="20" color="#94a3b8" /></view>
                </view>
                <view class="my-[24rpx] mb-[10rpx] flex items-center justify-between gap-[16rpx]">
                    <view class="min-w-0 flex-1">
                        <u-search v-model="keyword" placeholder="搜索姓名 / 手机号 / 会员号" :showAction="false" bgColor="#f4f7fb" @search="load" @clear="load" />
                    </view>
                    <view class="flex h-[64rpx] items-center gap-[7rpx] whitespace-nowrap rounded-[32rpx] border border-[#bfdbfe] bg-[#eff6ff] px-[19rpx] text-[24rpx] font-medium text-[#2563eb]" @click="openCreate"><u-icon name="plus" color="#2563eb" size="15" /><text>新建</text></view>
                </view>
                <scroll-view scroll-y :style="{ height: 'calc(100% - 142rpx)' }">
                    <view v-if="loading" class="py-[80rpx]"><u-loading-icon text="客户加载中" /></view>
                    <view
                        v-for="row in rows"
                        :key="row.member_id"
                        class="flex min-h-[100rpx] items-center justify-between gap-[16rpx] border-b border-[#eef2f7] px-[6rpx] py-[13rpx]"
                        hover-class="bg-[#f8fbff]"
                        @click="choose(row)"
                    >
                        <view class="flex h-[64rpx] w-[64rpx] flex-none items-center justify-center rounded-full bg-[#dbeafe] text-[27rpx] font-bold text-[#2563eb]">{{ String(row.display_name || '客').slice(0, 1) }}</view>
                        <view class="flex min-w-0 flex-1 flex-col gap-[6rpx]">
                            <text class="truncate text-[28rpx] font-semibold text-[#3f4d63]">{{ row.display_name || '未命名客户' }}</text>
                            <text class="text-[23rpx] text-[#748399]">{{ row.mobile_masked || '暂无手机号' }}</text>
                        </view>
                        <view class="flex min-w-[58rpx] flex-col items-center"><text class="text-[28rpx] font-bold text-[#2563eb]">{{ row.card_count || 0 }}</text><text class="text-[21rpx] text-[#8290a5]">张卡</text></view>
                        <u-icon name="arrow-right" color="#cbd5e1" size="16" />
                    </view>
                    <view v-if="!loading && !rows.length" class="py-[80rpx]"><u-empty text="没有找到客户" mode="search" /></view>
                </scroll-view>
            </view>
        </u-popup>

        <u-popup :show="createVisible" mode="center" round="18" :zIndex="12100" @close="createVisible = false">
            <view class="box-border w-[620rpx] bg-white px-[28rpx] pb-[28rpx] pt-[30rpx]">
                <view class="mb-[20rpx] flex items-center gap-[14rpx]">
                    <view class="flex h-[56rpx] w-[56rpx] items-center justify-center rounded-[15rpx] bg-[#eff6ff]"><u-icon name="account" color="#2563eb" size="20" /></view>
                    <view class="flex flex-col gap-[5rpx]">
                        <text class="text-[30rpx] font-bold text-[#334155]">快速创建客户</text>
                        <text class="text-[22rpx] text-[#8290a5]">填写姓名和手机号后立即选中</text>
                    </view>
                </view>
                <view class="mt-[13rpx] rounded-[14rpx] border border-[#dfe6ef] bg-[#f8fafc] px-[15rpx] py-[21rpx]"><u-input v-model="create.name" border="none" placeholder="客户姓名" prefixIcon="account" /></view>
                <view class="mt-[13rpx] rounded-[14rpx] border border-[#dfe6ef] bg-[#f8fafc] px-[15rpx] py-[21rpx]"><u-input v-model="create.mobile" border="none" type="number" maxlength="11" placeholder="11 位手机号" prefixIcon="phone" /></view>
                <view class="mt-[13rpx] rounded-[14rpx] border border-[#dfe6ef] bg-[#f8fafc] px-[15rpx] py-[21rpx]"><u-input v-model="create.password" border="none" type="password" maxlength="32" placeholder="初始登录密码" prefixIcon="lock-fill" @input="passwordCustomized = true" /></view>
                <view class="mt-[11rpx] flex items-center gap-[7rpx] text-[21rpx] text-[#64748b]"><u-icon name="info-circle" color="#64748b" size="13" /><text>默认取手机号后六位，可在创建前修改</text></view>
                <view class="mt-[22rpx] flex gap-[14rpx]">
                    <view class="w-[42%]"><MemberCardButton compact text="取消" @click="createVisible = false" /></view>
                    <view class="min-w-0 flex-1"><MemberCardButton compact type="primary" icon="checkmark" text="创建并选中" :loading="creating" @click="submitCreate" /></view>
                </view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { getCardMemberOptions, memberCardRequestId, quickCreateCardMember } from '../api'
import MemberCardButton from './MemberCardButton.vue'

const props = withDefaults(defineProps<{ show?: boolean }>(), { show: false })
const emit = defineEmits(['update:show', 'select'])
const keyword = ref('')
const rows = ref<any[]>([])
const loading = ref(false)
const createVisible = ref(false)
const creating = ref(false)
const create = ref({ name: '', mobile: '', password: '' })
const passwordCustomized = ref(false)

const close = () => {
    createVisible.value = false
    emit('update:show', false)
}
const openCreate = () => { createVisible.value = true }
const load = async () => {
    loading.value = true
    try {
        const result: any = await getCardMemberOptions({ keyword: keyword.value.trim(), limit: 40 })
        const data = result?.data
        rows.value = Array.isArray(data) ? data : (Array.isArray(data?.list) ? data.list : [])
    } catch (error) {
        rows.value = []
        uni.showToast({ title: '客户加载失败，请稍后重试', icon: 'none' })
    } finally {
        loading.value = false
    }
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
watch(() => props.show, visible => {
    if (visible) {
        keyword.value = ''
        load()
    } else {
        createVisible.value = false
    }
}, { immediate: true })
watch(() => create.value.mobile, mobile => {
    if (!passwordCustomized.value) create.value.password = /^1\d{5,10}$/.test(mobile) ? mobile.slice(-6) : ''
})
watch(createVisible, visible => {
    if (!visible) return
    create.value = { name: '', mobile: '', password: '' }
    passwordCustomized.value = false
})
</script>
