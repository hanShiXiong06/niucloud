<template>
    <view class="consignment-list-page">
        <!-- #ifdef MP -->
        <RecyclePageHeader title="代卖订单" :fill="false" class="recycle-list-header">
            <view class="nav-search-box" @tap.stop>
                <text class="nc-iconfont nc-icon-sousuo-duanV6xx1 nav-search-icon" @click="reloadList()"></text>
                <input
                    v-model="keyword"
                    class="nav-search-input"
                    maxlength="50"
                    placeholder="搜索代卖单号、订单、IMEI、手机号"
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
                        placeholder="搜索代卖单号、来源订单、IMEI、手机号"
                        confirm-type="search"
                        @confirm="reloadList"
                    />
                    <text
                        v-if="keyword"
                        class="nc-iconfont nc-icon-cuohaoV6xx1 search-clear"
                        @click="keyword = ''; reloadList()"
                    ></text>
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
                    class="order-card"
                    @click="toDetail(item.id)"
                >
                    <view class="order-card__header">
                        <view class="order-card__no" @click.stop="copyNo(item.consignment_no)">
                            <text>{{ item.consignment_no || '-' }}</text>
                            <text class="nc-iconfont nc-icon-fuzhiV6xx1 copy-icon"></text>
                        </view>
                        <text class="order-card__status" :class="getOrderStatusClass(item.status)">
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

                    <view class="device-box">
                        <view class="device-row">
                            <text class="device-label">设备</text>
                            <text class="device-value">{{ item.device_model || '-' }}</text>
                        </view>
                        <view class="device-row">
                            <text class="device-label">IMEI</text>
                            <text class="device-value">{{ item.device_imei || '-' }}</text>
                        </view>
                        <view class="device-row">
                            <text class="device-label">来源订单</text>
                            <text class="device-value">{{ item.source_order_no || '-' }}</text>
                        </view>
                    </view>

                    <view class="amount-grid">
                        <view class="amount-item">
                            <text class="amount-label">回收报价</text>
                            <text class="amount-value">¥{{ formatMoney(item.quote_price) }}</text>
                        </view>
                        <view class="amount-item">
                            <text class="amount-label">挂牌价</text>
                            <text class="amount-value">¥{{ formatMoney(item.listing_price) }}</text>
                        </view>
                        <view class="amount-item">
                            <text class="amount-label">成交价</text>
                            <text class="amount-value">¥{{ formatMoney(item.sold_price) }}</text>
                        </view>
                        <view class="amount-item">
                            <text class="amount-label">客户结算</text>
                            <text class="amount-value text-[#18a058]">¥{{ formatMoney(item.settlement_amount) }}</text>
                        </view>
                    </view>

                    <view class="card-footer">
                        <text>创建时间：{{ formatTime(item.create_time) }}</text>
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
import { getConsignmentOrderList, getConsignmentStatusOptions } from '@/addon/hsx_recycle/api/consignment'
import { formatMoney, formatTime, makePhoneCall } from '@/addon/hsx_recycle/utils/helper'
import RecyclePageHeader from '@/addon/hsx_recycle/components/RecyclePageHeader.vue'
import { useRecycleListHeader } from '@/addon/hsx_recycle/hooks/useRecycleListHeader'
import { useRecyclePaging } from '@/addon/hsx_recycle/hooks/useRecyclePaging'
import { getStatusKey, getStatusLabel, getStatusValue, hasStatusCount, isSameStatus } from '@/addon/hsx_recycle/hooks/useRecycleStatusTabs'
import { getRecycleMemberAvatar, getRecycleMemberInitial, getRecycleMemberMobile, getRecycleMemberName } from '@/addon/hsx_recycle/hooks/useRecycleMember'

const { pagingRef, list, reload, complete } = useRecyclePaging()
const keyword = ref('')
const currentStatus = ref<number | string>('')
const statusTabs = ref<any[]>([{ label: '全部', value: '' as number | string }])
const { pageHeaderStyle, pagingStyle } = useRecycleListHeader()

const loadStatusTabs = async () => {
    const res: any = await getConsignmentStatusOptions()
    statusTabs.value = [{ label: '全部', value: '' }, ...(res.data || [])]
}

const queryList = async (pageNo: number, pageSize: number) => {
    try {
        const res: any = await getConsignmentOrderList({
            page: pageNo,
            limit: pageSize,
            keyword: keyword.value,
            status: currentStatus.value
        })
        complete(res.data?.data || [])
    } catch (e) {
        complete(false)
    }
}

const reloadList = () => {
    reload()
}

const switchStatus = (value: number | string) => {
    currentStatus.value = value
    reloadList()
}

const isActiveStatus = (value: number | string) => {
    return isSameStatus(currentStatus.value, value)
}

const toDetail = (id: number | string) => {
    redirect({ url: '/addon/hsx_recycle/pages/consignment/detail', param: { id } })
}

const copyNo = (value: string) => copy(value || '')

const getMemberName = (item: any) => {
    return getRecycleMemberName(item)
}

const getMemberMobile = (item: any) => {
    return getRecycleMemberMobile(item)
}

const getMemberAvatar = (item: any) => {
    return getRecycleMemberAvatar(item)
}

const getMemberInitial = (item: any) => {
    return getRecycleMemberInitial(item)
}

const getOrderStatusClass = (status: number) => {
    if (status === 4) return 'is-success'
    if (status === 5 || status === 6) return 'is-muted'
    if (status === 2 || status === 3) return 'is-warning'
    return 'is-primary'
}

onShow(() => {
    loadStatusTabs()
    reload()
})
</script>

<style scoped lang="scss">
.consignment-list-page {
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

.order-card {
    background: #fff;
    border-radius: 18rpx;
    padding: 24rpx;
    margin-bottom: 20rpx;
    box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.04);
}

.order-card__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16rpx;
}

.order-card__no {
    min-width: 0;
    display: flex;
    align-items: center;
    font-size: 24rpx;
    color: #64748b;
}

.copy-icon {
    margin-left: 8rpx;
}

.order-card__status {
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

.device-box {
    margin-top: 18rpx;
    padding: 16rpx 18rpx;
    border-radius: 16rpx;
    background: #f8fafc;
}

.device-row {
    display: flex;
    align-items: center;
    gap: 20rpx;
    min-width: 0;
    font-size: 22rpx;
    line-height: 36rpx;
}

.device-label {
    width: 88rpx;
    flex-shrink: 0;
    color: #94a3b8;
}

.device-value {
    flex: 1;
    min-width: 0;
    color: #475569;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.amount-grid {
    margin-top: 16rpx;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16rpx;
    background: #f8fafc;
    border-radius: 16rpx;
    padding: 18rpx;
}

.amount-item {
    display: flex;
    flex-direction: column;
    gap: 8rpx;
}

.amount-label {
    font-size: 22rpx;
    color: #8a8f99;
}

.amount-value {
    font-size: 26rpx;
    font-weight: 600;
    color: #222;
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
