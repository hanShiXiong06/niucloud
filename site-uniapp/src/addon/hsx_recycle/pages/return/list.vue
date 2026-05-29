<template>
    <view class="return-list-page">
        <!-- #ifdef MP -->
        <RecyclePageHeader title="退回订单" :fill="false" class="recycle-list-header">
            <view class="nav-search-box" @tap.stop>
                <text class="nc-iconfont nc-icon-sousuo-duanV6xx1 nav-search-icon" @click="reloadList()"></text>
                <input
                    v-model="keyword"
                    class="nav-search-input"
                    maxlength="50"
                    placeholder="搜索退回单号"
                    confirm-type="search"
                    @confirm="reloadList"
                />
                <text
                    v-if="keyword"
                    class="nc-iconfont nc-icon-cuohaoV6mm nav-search-clear"
                    @click="keyword = ''; reloadList()"
                ></text>
            </view>
        </RecyclePageHeader>
        <!-- #endif -->

        <view class="page-header" :style="pageHeaderStyle">
            <!-- #ifndef MP -->
            <view class="search-wrap">
                <view class="search-box">
                    <input
                        v-model="keyword"
                        class="search-input"
                        maxlength="50"
                        placeholder="搜索退回单号"
                        confirm-type="search"
                        @confirm="reloadList"
                    />
                    <text v-if="keyword" class="nc-iconfont nc-icon-cuohaoV6xx1 search-clear" @click="keyword = ''; reloadList()"></text>
                    <text class="nc-iconfont nc-icon-sousuo-duanV6xx1 search-icon" @click="reloadList()"></text>
                </view>
            </view>
            <!-- #endif -->

            <scroll-view scroll-x class="status-scroll">
                <view class="status-row">
                    <view
                        v-for="(item, index) in statusTabs"
                        :key="getStatusKey(item, index)"
                        class="status-chip"
                        :class="{ 'status-chip--active': isActiveStatus(getStatusValue(item)) }"
                        @click="switchStatus(getStatusValue(item))"
                    >
                        <text>{{ getStatusLabel(item) }}</text>
                        <text v-if="hasStatusCount(item)" class="status-chip__count">{{ item.count }}</text>
                    </view>
                </view>
            </scroll-view>
        </view>

        <z-paging
            ref="pagingRef"
            v-model="list"
            :auto="true"
            :fixed="true"
            :default-page-size="10"
            :paging-style="pagingStyle"
            @query="queryList"
        >
            <view class="list-content">
                <view
                    v-for="item in list"
                    :key="item.id"
                    class="return-card"
                    @click="toDetail(item.id)"
                >
                    <view class="return-card__header">
                        <view class="return-card__no" @click.stop="copyNo(item.order_no)">
                            <text>{{ item.order_no || '-' }}</text>
                            <text class="nc-iconfont nc-icon-fuzhiV6xx1 copy-icon"></text>
                        </view>
                        <text class="return-card__status" :class="getStatusClass(item.status)">
                            {{ item.status_name || '-' }}
                        </text>
                    </view>

                    <view class="member-row">
                        <image v-if="getMemberAvatar(item)" class="member-avatar" :src="getMemberAvatar(item)" mode="aspectFill" />
                        <view v-else class="member-avatar member-avatar--empty">
                            <text>{{ getMemberInitial(item) }}</text>
                        </view>
                        <view class="member-main">
                            <view class="member-name">{{ getMemberName(item) }}</view>
                            <view class="member-mobile" @click.stop="makePhoneCall(getMemberMobile(item))">
                                <text class="nc-iconfont nc-icon-dianhuaV6xx member-mobile__icon"></text>
                                <text>{{ getMemberMobile(item) }}</text>
                            </view>
                        </view>
                    </view>

                    <view class="summary-strip">
                        <view class="summary-node">
                            <text class="summary-node__label">快递单号</text>
                            <text class="summary-node__value">{{ item.express_no || '-' }}</text>
                        </view>
                        <view class="summary-node">
                            <text class="summary-node__label">设备数量</text>
                            <text class="summary-node__value">{{ getDeviceCount(item) }} 台</text>
                        </view>
                    </view>

                    <view class="card-footer">
                        <text>创建时间：{{ formatTime(item.create_at) }}</text>
                        <text class="card-footer__link">查看详情</text>
                    </view>
                </view>
            </view>
        </z-paging>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { copy, redirect } from '@/utils/common'
import { getReturnOrderList, getReturnOrderStatusList } from '@/addon/hsx_recycle/api/return-order'
import { formatTime, makePhoneCall } from '@/addon/hsx_recycle/utils/helper'
import RecyclePageHeader from '@/addon/hsx_recycle/components/RecyclePageHeader.vue'
import { useRecycleListHeader } from '@/addon/hsx_recycle/hooks/useRecycleListHeader'
import { useRecyclePaging } from '@/addon/hsx_recycle/hooks/useRecyclePaging'
import { getStatusKey, getStatusLabel, getStatusValue, hasStatusCount, isSameStatus } from '@/addon/hsx_recycle/hooks/useRecycleStatusTabs'
import { getRecycleMemberAvatar, getRecycleMemberInitial, getRecycleMemberMobile, getRecycleMemberName } from '@/addon/hsx_recycle/hooks/useRecycleMember'

const { pagingRef, list, reload, complete } = useRecyclePaging()
const keyword = ref('')
const currentStatus = ref<number | string>('')
const statusTabs = ref<any[]>([{ status: '', name: '全部' }])
const { pageHeaderStyle, pagingStyle } = useRecycleListHeader()

const loadStatuses = async () => {
    const res: any = await getReturnOrderStatusList()
    statusTabs.value = [{ status: '', name: '全部' }, ...(res.data || [])]
}

const queryList = async (pageNo: number, pageSize: number) => {
    try {
        const res: any = await getReturnOrderList({
            page: pageNo,
            limit: pageSize,
            order_no: keyword.value,
            express_no: '',
            status: currentStatus.value,
            create_at: []
        })
        complete(res.data?.data || [])
    } catch (e) {
        complete(false)
    }
}

const reloadList = () => reload()

const switchStatus = (status: number | string) => {
    currentStatus.value = status
    reloadList()
}

const isActiveStatus = (status: number | string) => {
    return isSameStatus(currentStatus.value, status)
}

const toDetail = (id: number | string) => {
    redirect({ url: '/addon/hsx_recycle/pages/return/detail', param: { id } })
}

const copyNo = (value: string) => copy(value || '')

const getMemberName = (item: any) => {
    return getRecycleMemberName(item, '未知会员')
}

const getMemberMobile = (item: any) => {
    return getRecycleMemberMobile(item)
}

const getMemberAvatar = (item: any) => {
    return getRecycleMemberAvatar(item)
}

const getMemberInitial = (item: any) => {
    return getRecycleMemberInitial(item, '未知会员')
}

const getDeviceCount = (item: any) => {
    return item.return_devices?.length || item.returnDevices?.length || item.device_count || item.count || 0
}

const getStatusClass = (status: number) => {
    if (status === 2) return 'is-success'
    if (status === 3) return 'is-muted'
    if (status === 1) return 'is-warning'
    return 'is-primary'
}

onShow(() => {
    loadStatuses()
    reload()
})
</script>

<style scoped lang="scss">
.return-list-page {
    min-height: 100vh;
    background: #f5f7fa;
}

.recycle-list-header {
    z-index: 20;
}

.nav-search-box {
    flex: 1;
    min-width: 0;
    height: 64rpx;
    border-radius: 32rpx;
    background: #f3f6fb;
    display: flex;
    align-items: center;
    padding: 0 18rpx;
    margin-left: 8rpx;
    margin-right: 22rpx;
}

.nav-search-input {
    flex: 1;
    height: 64rpx;
    font-size: 24rpx;
    color: #1f2937;
}

.nav-search-icon,
.nav-search-clear {
    flex-shrink: 0;
    font-size: 26rpx;
    color: #8c8c8c;
}

.nav-search-icon {
    margin-right: 10rpx;
}

.nav-search-clear {
    margin-left: 10rpx;
}

.page-header {
    position: fixed;
    left: 0;
    top: 0;
    right: 0;
    z-index: 10;
    background: #fff;
    box-shadow: 0 6rpx 18rpx rgba(15, 23, 42, 0.04);
}

.search-wrap {
    padding: 18rpx 20rpx 14rpx;
}

.search-box {
    height: 72rpx;
    background: #f6f7fb;
    border-radius: 999rpx;
    display: flex;
    align-items: center;
    padding: 0 20rpx;
}

.search-input {
    flex: 1;
    font-size: 26rpx;
}

.search-clear,
.search-icon {
    flex-shrink: 0;
    font-size: 26rpx;
    color: #8c8c8c;
}

.search-clear {
    margin-right: 8rpx;
}

.status-scroll {
    border-top: 1rpx solid #f1f5f9;
}

.status-row {
    display: flex;
    align-items: center;
    gap: 18rpx;
    padding: 16rpx 20rpx 18rpx;
    white-space: nowrap;
}

.status-chip {
    display: inline-flex;
    align-items: center;
    gap: 8rpx;
    padding: 12rpx 20rpx;
    border-radius: 999rpx;
    background: #f5f7fa;
    color: #64748b;
    font-size: 24rpx;
}

.status-chip--active {
    background: #eff6ff;
    color: #2563eb;
}

.status-chip__count {
    min-width: 32rpx;
    height: 32rpx;
    border-radius: 16rpx;
    background: rgba(37, 99, 235, 0.12);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 20rpx;
}

.list-content {
    padding: 20rpx;
}

.return-card {
    background: #fff;
    border-radius: 18rpx;
    padding: 24rpx;
    margin-bottom: 20rpx;
    box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.04);
}

.return-card__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16rpx;
}

.return-card__no {
    min-width: 0;
    display: flex;
    align-items: center;
    font-size: 24rpx;
    color: #64748b;
}

.copy-icon {
    margin-left: 8rpx;
}

.return-card__status {
    flex-shrink: 0;
    font-size: 26rpx;
    font-weight: 700;
}

.is-primary {
    color: #2563eb;
}

.is-warning {
    color: #d97706;
}

.is-success {
    color: #16a34a;
}

.is-muted {
    color: #94a3b8;
}

.member-row {
    display: flex;
    align-items: center;
    min-width: 0;
    margin-top: 18rpx;
}

.member-avatar {
    width: 72rpx;
    height: 72rpx;
    border-radius: 36rpx;
    flex-shrink: 0;
    background: #e8eef8;
}

.member-avatar--empty {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #2563eb;
    font-size: 28rpx;
    font-weight: 700;
}

.member-main {
    flex: 1;
    min-width: 0;
    margin-left: 16rpx;
}

.member-name {
    font-size: 30rpx;
    font-weight: 600;
    color: #1f2937;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.member-mobile {
    display: flex;
    align-items: center;
    min-width: 0;
    margin-top: 8rpx;
    font-size: 23rpx;
    color: #64748b;
}

.member-mobile__icon {
    flex-shrink: 0;
    margin-right: 8rpx;
    font-size: 24rpx;
    color: #94a3b8;
}

.summary-strip {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 180rpx;
    gap: 12rpx;
    margin-top: 18rpx;
    padding: 18rpx;
    border-radius: 16rpx;
    background: #f8fafc;
}

.summary-node {
    min-width: 0;
}

.summary-node__label {
    display: block;
    font-size: 20rpx;
    color: #8c8c8c;
}

.summary-node__value {
    display: block;
    margin-top: 8rpx;
    font-size: 24rpx;
    font-weight: 600;
    color: #1f2937;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.card-footer {
    margin-top: 14rpx;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16rpx;
    font-size: 22rpx;
    color: #999;
}

.card-footer__link {
    flex-shrink: 0;
    color: #2563eb;
}
</style>
