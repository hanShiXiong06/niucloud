<template>
    <u-popup
        :show="show"
        mode="bottom"
        round="20"
        :z-index="10140"
        :safe-area-inset-bottom="true"
        @close="handleClose"
    >
        <view class="recycle-member-picker" @touchmove.stop.prevent="noop">
            <view class="recycle-member-picker__header">
                <view class="recycle-member-picker__heading">
                    <text class="recycle-member-picker__title">选择客户</text>
                    <text class="recycle-member-picker__subtitle">选择本站已有客户，订单将记录在该客户名下</text>
                </view>
                <view class="recycle-member-picker__close" @tap="handleClose">
                    <text class="nc-iconfont nc-icon-guanbiV6xx1"></text>
                </view>
            </view>

            <view class="recycle-member-picker__search">
                <text class="nc-iconfont nc-icon-sousuo-duanV6xx1 recycle-member-picker__search-icon"></text>
                <input
                    v-model="keyword"
                    class="recycle-member-picker__input"
                    placeholder="搜索昵称、手机号或会员编号"
                    confirm-type="search"
                    :maxlength="80"
                    @confirm="handleSearch"
                />
                <view v-if="keyword" class="recycle-member-picker__clear" @tap="clearSearch">
                    <text class="nc-iconfont nc-icon-cuohaoV6xx1"></text>
                </view>
                <view class="recycle-member-picker__search-button" @tap="handleSearch">搜索</view>
            </view>

            <view v-if="selectedMember" class="recycle-member-picker__selected" @tap="selectMember(selectedMember)">
                <text class="recycle-member-picker__selected-label">当前客户</text>
                <view class="recycle-member-picker__selected-info">
                    <text class="recycle-member-picker__name">{{ memberName(selectedMember) }}</text>
                    <text class="recycle-member-picker__meta">{{ selectedMember.mobile || '未绑定手机号' }}</text>
                </view>
                <text class="recycle-member-picker__check">已选</text>
            </view>

            <scroll-view
                scroll-y
                class="recycle-member-picker__list"
                :scroll-top="scrollTop"
                :lower-threshold="80"
                @scrolltolower="loadMore"
            >
                <view v-if="loading && !members.length" class="recycle-member-picker__state">正在加载客户…</view>
                <view v-else-if="error && !members.length" class="recycle-member-picker__state">
                    <text>{{ error }}</text>
                    <view class="recycle-member-picker__retry" @tap="retry">重新加载</view>
                </view>
                <view v-else-if="!members.length" class="recycle-member-picker__state">
                    <text>{{ submittedKeyword ? '未找到匹配客户' : '本站暂无可选择的客户' }}</text>
                    <text v-if="submittedKeyword" class="recycle-member-picker__empty-tip">试试手机号、昵称或会员编号</text>
                </view>

                <view
                    v-for="member in members"
                    :key="member.member_id"
                    class="recycle-member-picker__item"
                    :class="{ 'recycle-member-picker__item--selected': isSelected(member) }"
                    @tap="selectMember(member)"
                >
                    <u-avatar :src="member.headimg" :size="'70rpx'" />
                    <view class="recycle-member-picker__item-info">
                        <text class="recycle-member-picker__name">{{ memberName(member) }}</text>
                        <text class="recycle-member-picker__meta">{{ member.mobile || '未绑定手机号' }}</text>
                        <text v-if="member.member_no" class="recycle-member-picker__number">会员编号 {{ member.member_no }}</text>
                    </view>
                    <text v-if="isSelected(member)" class="recycle-member-picker__check">已选</text>
                    <text v-else class="recycle-member-picker__choose">选择</text>
                </view>

                <view v-if="members.length" class="recycle-member-picker__more">
                    <text v-if="loading">正在加载…</text>
                    <view v-else-if="error" class="recycle-member-picker__retry" @tap="retry">{{ error }}，点击重试</view>
                    <view v-else-if="hasMore" class="recycle-member-picker__retry" @tap="loadMore">加载更多</view>
                    <text v-else>已显示全部客户</text>
                </view>
            </scroll-view>
        </view>
    </u-popup>
</template>

<script lang="ts">
export interface RecycleMemberOption {
    member_id: number
    member_no: string
    nickname: string
    username: string
    mobile: string
    headimg: string
}
</script>

<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { searchMemberList } from '@/addon/hsx_recycle/api/order'

const props = withDefaults(defineProps<{
    show: boolean
    selectedMember?: RecycleMemberOption | null
}>(), {
    selectedMember: null
})

const emit = defineEmits<{
    (event: 'update:show', value: boolean): void
    (event: 'select', member: RecycleMemberOption): void
    (event: 'close'): void
}>()

const PAGE_SIZE = 20
const keyword = ref('')
const submittedKeyword = ref('')
const members = ref<RecycleMemberOption[]>([])
const page = ref(0)
const hasMore = ref(true)
const loading = ref(false)
const error = ref('')
const scrollTop = ref(0)
let requestVersion = 0

const noop = () => {}
const memberName = (member: RecycleMemberOption) => member.nickname || member.username || member.member_no || `会员${ member.member_id }`
const isSelected = (member: RecycleMemberOption) => Number(props.selectedMember?.member_id) === member.member_id

const normalizeMember = (item: Record<string, any>): RecycleMemberOption => ({
    member_id: Number(item.member_id),
    member_no: String(item.member_no || ''),
    nickname: String(item.nickname || ''),
    username: String(item.username || ''),
    mobile: String(item.mobile || ''),
    headimg: String(item.headimg || item.head_img || '')
})

const loadMembers = async (reset = false) => {
    if (!props.show || (!reset && (loading.value || !hasMore.value))) return

    const version = ++requestVersion
    const nextPage = reset ? 1 : page.value + 1
    if (reset) {
        members.value = []
        page.value = 0
        hasMore.value = true
        // 触发 scroll-view 回到顶部，即便原本 scrollTop 已经是 0。
        scrollTop.value = scrollTop.value === 0 ? 1 : 0
    }
    loading.value = true
    error.value = ''

    try {
        const response: any = await searchMemberList({
            page: nextPage,
            limit: PAGE_SIZE,
            keyword: submittedKeyword.value
        })
        if (version !== requestVersion || !props.show) return

        const payload = response?.data
        const rows = Array.isArray(payload) ? payload : payload?.data
        if (!Array.isArray(rows)) throw new Error('客户数据格式异常，请重试')

        const options = rows
            .filter(item => item && typeof item === 'object' && Number(item.member_id) > 0)
            .map(normalizeMember)
        const merged = reset ? options : [...members.value, ...options]
        const seen = new Set<number>()
        members.value = merged.filter(item => {
            if (seen.has(item.member_id)) return false
            seen.add(item.member_id)
            return true
        })
        page.value = nextPage

        const total = Number(payload?.total)
        const lastPage = Number(payload?.last_page)
        hasMore.value = Number.isFinite(lastPage) && lastPage > 0
            ? nextPage < lastPage
            : Number.isFinite(total) && total >= 0
                ? members.value.length < total
                : rows.length >= PAGE_SIZE
    } catch (reason: any) {
        if (version !== requestVersion || !props.show) return
        error.value = String(reason?.msg || reason?.message || '客户加载失败，请重试')
    } finally {
        if (version === requestVersion) loading.value = false
    }
}

const handleSearch = () => {
    submittedKeyword.value = keyword.value.trim()
    loadMembers(true)
}

const clearSearch = () => {
    keyword.value = ''
    submittedKeyword.value = ''
    loadMembers(true)
}

const loadMore = () => loadMembers()
const retry = () => loadMembers(page.value === 0)

const handleClose = () => {
    ++requestVersion
    loading.value = false
    uni.hideKeyboard()
    emit('update:show', false)
    emit('close')
}

const selectMember = (member: RecycleMemberOption) => {
    emit('select', { ...member })
    handleClose()
}

watch(() => props.show, (show) => {
    if (show) {
        keyword.value = ''
        submittedKeyword.value = ''
        loadMembers(true)
    } else {
        ++requestVersion
        loading.value = false
    }
}, { immediate: true })

onBeforeUnmount(() => { ++requestVersion })
</script>

<style scoped lang="scss">
.recycle-member-picker {
    display: flex;
    flex-direction: column;
    height: 78vh;
    max-height: 1100rpx;
    overflow: hidden;
    background: #fff;
    border-radius: 24rpx 24rpx 0 0;

    &__header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 30rpx 28rpx 22rpx;
    }

    &__heading { display: flex; flex: 1; min-width: 0; flex-direction: column; }
    &__title { font-size: 32rpx; font-weight: 600; color: var(--hsx-text-strong, #1f2937); }
    &__subtitle { margin-top: 8rpx; font-size: 23rpx; line-height: 34rpx; color: var(--hsx-text-secondary, #6b7280); }
    &__close { padding: 6rpx 0 12rpx 24rpx; font-size: 34rpx; color: #909399; }

    &__search {
        display: flex;
        align-items: center;
        flex-shrink: 0;
        margin: 0 28rpx 20rpx;
        padding: 0 20rpx;
        height: 80rpx;
        border-radius: 14rpx;
        background: #f5f6f8;
    }
    &__search-icon { font-size: 30rpx; color: #909399; margin-right: 12rpx; }
    &__input { flex: 1; min-width: 0; font-size: 26rpx; height: 80rpx; }
    &__clear { padding: 14rpx; font-size: 26rpx; color: #909399; }
    &__search-button { margin-left: 12rpx; padding: 12rpx 0 12rpx 18rpx; font-size: 26rpx; font-weight: 500; color: var(--hsx-primary, #3867ef); }

    &__selected { display: flex; align-items: center; margin: 0 28rpx 16rpx; padding: 18rpx; border-radius: 14rpx; background: #f0f5ff; }
    &__selected-label { margin-right: 18rpx; font-size: 23rpx; color: var(--hsx-primary, #3867ef); }
    &__selected-info { display: flex; flex: 1; min-width: 0; flex-direction: column; }
    &__list { flex: 1; min-height: 0; height: 0; }
    &__item { display: flex; align-items: center; margin: 0 28rpx; padding: 22rpx 0; border-bottom: 1rpx solid #edf0f4; }
    &__item--selected { background: #f8faff; }
    &__item-info { display: flex; flex: 1; min-width: 0; flex-direction: column; margin: 0 18rpx; }
    &__name { font-size: 28rpx; font-weight: 500; line-height: 40rpx; color: var(--hsx-text-strong, #1f2937); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    &__meta { margin-top: 2rpx; font-size: 25rpx; line-height: 36rpx; color: #6b7280; }
    &__number { margin-top: 2rpx; font-size: 22rpx; color: #909399; }
    &__check, &__choose { flex-shrink: 0; font-size: 25rpx; color: var(--hsx-primary, #3867ef); }
    &__check { font-weight: 600; }
    &__state { display: flex; flex-direction: column; align-items: center; padding: 100rpx 28rpx; text-align: center; font-size: 26rpx; line-height: 40rpx; color: #6b7280; }
    &__empty-tip { margin-top: 12rpx; font-size: 23rpx; color: #909399; }
    &__retry { color: var(--hsx-primary, #3867ef); padding: 16rpx 12rpx; }
    &__more { padding: 22rpx 20rpx 34rpx; text-align: center; font-size: 23rpx; line-height: 34rpx; color: #909399; }
}
</style>
