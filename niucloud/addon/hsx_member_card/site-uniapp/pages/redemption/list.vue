<template>
    <view class="list-page">
        <view class="fixed-head">
            <view class="search-row"><u-search v-model="keyword" placeholder="客户 / 手机号 / 卡号 / 操作人" :showAction="false" bgColor="#f1f5f9" @search="reload" @clear="reload" /></view>
            <view class="mc-tabs"><u-tabs :list="tabList" :current="currentTab" lineColor="#2563eb" :lineWidth="24" :activeStyle="tabActiveStyle" :inactiveStyle="tabInactiveStyle" :itemStyle="tabItemStyle" @click="changeTab" /></view>
        </view>
        <z-paging ref="paging" v-model="rows" :fixed="true" :paging-style="pagingStyle" @query="query">
            <template #empty><view class="mc-empty"><u-empty text="暂无核销记录" mode="list" /></view></template>
            <view class="mc-list-wrap">
                <view v-for="row in rows" :key="row.id" class="record mc-surface">
                    <view class="record-head">
                        <view class="service-icon" :class="{ reversed: row.status !== 'success' }"><u-icon :name="row.status === 'success' ? 'checkmark-circle' : 'reload'" :color="row.status === 'success' ? '#16a34a' : '#64748b'" size="20" /></view>
                        <view class="service-copy"><strong>{{ row.item_name }}</strong><text>{{ row.holder_name }} · {{ mask(row.holder_mobile) }}</text></view>
                        <u-tag :text="row.status === 'success' ? '核销成功' : '已冲正'" :type="row.status === 'success' ? 'success' : 'info'" plain plainFill size="mini" />
                    </view>
                    <view class="result-row">
                        <view><text>权益次数</text><strong>{{ row.before_remaining }} → {{ row.after_remaining }}</strong></view>
                        <view class="income"><text>本次确认收入</text><strong>¥{{ money(row.recognized_amount) }}</strong></view>
                    </view>
                    <view class="record-meta">
                        <view class="mc-meta-line"><u-icon name="account" color="#94a3b8" size="14" /><text>操作人 {{ row.operator_name || '—' }}</text></view>
                        <view class="mc-meta-line"><u-icon name="clock" color="#94a3b8" size="14" /><text>{{ time(row.occurred_at) }}</text></view>
                        <view class="mc-meta-line"><u-icon name="file-text" color="#94a3b8" size="14" /><text>{{ row.redeem_no }}</text></view>
                    </view>
                    <view v-if="row.status === 'success'" class="reverse-action"><MemberCardButton type="warning" plain compact icon="reload" text="冲正并恢复次数" @click="reverse(row)" /></view>
                </view>
            </view>
        </z-paging>
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getCardRedemptions, memberCardRequestId, reverseCardRedemption } from '../../api'
import MemberCardButton from '../../components/MemberCardButton.vue'

const keyword = ref('')
const status = ref('')
const rows = ref<any[]>([])
const paging = ref<any>()
const tabs = [{ name: '全部', value: '' }, { name: '核销成功', value: 'success' }, { name: '已冲正', value: 'reversed' }]
const tabList = tabs.map(item => ({ name: item.name }))
const currentTab = computed(() => Math.max(0, tabs.findIndex(item => item.value === status.value)))
const pagingStyle = computed(() => ({ top: '178rpx', bottom: '0' }))
const tabActiveStyle = { color: '#2563eb', fontWeight: '700', fontSize: '26rpx' }
const tabInactiveStyle = { color: '#64748b', fontSize: '26rpx' }
const tabItemStyle = 'padding-left: 28rpx; padding-right: 28rpx; height: 78rpx;'
const reload = () => paging.value?.reload()
const changeTab = (item: any) => { status.value = tabs[item.index]?.value || ''; reload() }
const query = async (page: number, limit: number) => {
    try {
        const result: any = await getCardRedemptions({ keyword: keyword.value, status: status.value, page, limit })
        paging.value?.complete(result?.data?.data || [])
    } catch { paging.value?.complete(false) }
}
const money = (value: any) => Number(value || 0).toFixed(2)
const mask = (value: string) => /^\d{11}$/.test(value || '') ? `${value.slice(0, 3)}****${value.slice(-4)}` : (value || '—')
const time = (value: any) => value ? new Date(Number(value) * 1000).toLocaleString('zh-CN', { hour12: false }).slice(0, 16) : '—'
const reverse = async (row: any) => {
    const modal = await uni.showModal({ title: '核销冲正', editable: true, placeholderText: '必须填写冲正原因', content: '冲正会恢复本次扣减的次数，原记录永久保留。', confirmText: '确认冲正' })
    if (!modal.confirm) return
    const reason = String((modal as any).content || '').trim()
    if (!reason) return uni.showToast({ title: '必须填写冲正原因', icon: 'none' })
    await reverseCardRedemption(row.id, { request_id: memberCardRequestId('reverse'), reason })
    uni.showToast({ title: '冲正成功', icon: 'success' })
    reload()
}
onShow(reload)
</script>

<style scoped lang="scss">
@import '../../styles/member-card-mobile.scss';
.list-page { min-height: 100vh; background: #f5f7fb; }
.fixed-head { position: fixed; z-index: 10; top: 0; right: 0; left: 0; border-bottom: 1rpx solid #e6ebf2; background: #fff; }
.search-row { padding: 16rpx 24rpx 0; }
.record { margin-bottom: 16rpx; padding: 22rpx; }
.record-head { display: flex; align-items: center; gap: 13rpx; }
.service-icon { display: flex; width: 56rpx; height: 56rpx; flex: 0 0 56rpx; align-items: center; justify-content: center; border-radius: 14rpx; background: #ecfdf3; }
.service-icon.reversed { background: #f1f5f9; }
.service-copy { display: flex; min-width: 0; flex: 1; flex-direction: column; gap: 5rpx; }
.service-copy strong { font-size: 27rpx; }
.service-copy text { color: #64748b; font-size: 21rpx; }
.result-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); margin: 18rpx 0; border: 1rpx solid #edf1f6; border-radius: 14rpx; background: #f8fafc; }
.result-row > view { display: flex; padding: 17rpx 19rpx; flex-direction: column; gap: 7rpx; }
.result-row > view:first-child { border-right: 1rpx solid #edf1f6; }
.result-row text { color: #64748b; font-size: 20rpx; }
.result-row strong { font-size: 26rpx; }
.result-row .income strong { color: #16a34a; }
.record-meta { display: grid; gap: 9rpx; }
.reverse-action { margin-top: 17rpx; }
</style>
