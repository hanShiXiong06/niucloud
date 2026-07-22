<template>
    <view class="mc-list-page">
        <view class="mc-fixed-filter">
            <view class="mc-search-bar">
                <u-search v-model="keyword" placeholder="搜索卡种名称" :showAction="false" bgColor="#f1f5f9" @search="reload" @clear="reload" />
            </view>
            <u-tabs :list="tabList" :current="currentTab" lineColor="#2563eb" :lineWidth="24" :activeStyle="tabActiveStyle" :inactiveStyle="tabInactiveStyle" :itemStyle="tabItemStyle" @click="changeTab" />
        </view>

        <z-paging ref="paging" v-model="rows" :fixed="true" :paging-style="pagingStyle" @query="query">
            <template #empty><view class="mc-empty"><u-empty text="暂无卡种，先创建一张服务卡" mode="list" /></view></template>
            <view class="mc-list-wrap product-list">
                <view v-for="row in rows" :key="row.id" class="product-card mc-surface">
                    <view class="product-card__head">
                        <view class="product-card__icon"><u-icon name="order" color="#2563eb" size="22" /></view>
                        <view class="product-card__copy">
                            <strong>{{ row.product_name }}</strong>
                            <text>{{ row.item?.item_name || '服务权益' }}</text>
                        </view>
                        <u-tag :text="statusText(row.status)" :type="statusType(row.status)" plain plainFill size="mini" />
                    </view>
                    <view class="product-card__metrics">
                        <view><text>销售价</text><strong>¥{{ money(row.sale_price) }}</strong></view>
                        <view><text>权益次数</text><strong>{{ usageText(row.item) }}</strong></view>
                        <view><text>有效期</text><strong>{{ row.validity_text || '永久有效' }}</strong></view>
                    </view>
                    <view class="product-card__foot">
                        <text class="product-no">{{ row.product_no }}</text>
                        <view class="product-actions">
                            <view v-if="row.status === 'draft'" class="action-text action-text--danger" @click="remove(row)">删除</view>
                            <view class="action-text" @click="edit(row)">编辑</view>
                            <view v-if="row.status !== 'enabled'" class="action-text action-text--primary" @click="changeStatus(row, true)">启用</view>
                            <view v-else class="action-text action-text--warning" @click="changeStatus(row, false)">停用</view>
                        </view>
                    </view>
                </view>
            </view>
        </z-paging>

        <view class="mc-bottom-action"><MemberCardButton type="primary" icon="plus" text="新增卡种" @click="add" /></view>
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { deleteCardProduct, disableCardProduct, enableCardProduct, getCardProducts } from '../../api'
import MemberCardButton from '../../components/MemberCardButton.vue'

const keyword = ref('')
const status = ref('')
const rows = ref<any[]>([])
const paging = ref<any>()
const tabs = [{ name: '全部', value: '' }, { name: '已启用', value: 'enabled' }, { name: '草稿', value: 'draft' }, { name: '已停用', value: 'disabled' }]
const tabList = tabs.map(item => ({ name: item.name }))
const currentTab = computed(() => Math.max(0, tabs.findIndex(item => item.value === status.value)))
const pagingStyle = computed(() => ({ top: '178rpx', bottom: '118rpx' }))
const tabActiveStyle = { color: '#2563eb', fontWeight: '700', fontSize: '26rpx' }
const tabInactiveStyle = { color: '#64748b', fontSize: '26rpx' }
const tabItemStyle = 'padding-left: 25rpx; padding-right: 25rpx; height: 76rpx;'
const reload = () => paging.value?.reload()
const changeTab = (item: any) => { status.value = tabs[item.index]?.value || ''; reload() }
const query = async (page: number, limit: number) => {
    try {
        const result: any = await getCardProducts({ keyword: keyword.value, status: status.value, page, limit })
        paging.value?.complete(result?.data?.data || [])
    } catch { paging.value?.complete(false) }
}
const money = (value: any) => Number(value || 0).toFixed(2)
const usageText = (item: any) => item?.usage_mode === 'unlimited' ? '不限次' : `${Number(item?.total_times || 0)} 次`
const statusText = (value: string) => ({ draft: '草稿', enabled: '已启用', disabled: '已停用' }[value] || value)
const statusType = (value: string) => ({ enabled: 'success', disabled: 'warning', draft: 'info' }[value] || 'info') as any
const add = () => uni.navigateTo({ url: '/addon/hsx_member_card/pages/product/edit' })
const edit = (row: any) => uni.navigateTo({ url: `/addon/hsx_member_card/pages/product/edit?id=${row.id}` })
const changeStatus = async (row: any, enabled: boolean) => {
    const modal = await uni.showModal({ title: enabled ? '启用卡种' : '停用卡种', content: enabled ? '启用后店员可以立即销售该卡种。' : '停用后不能继续开新卡，已售会员卡不受影响。', confirmText: enabled ? '确认启用' : '确认停用' })
    if (!modal.confirm) return
    enabled ? await enableCardProduct(row.id) : await disableCardProduct(row.id)
    uni.showToast({ title: enabled ? '卡种已启用' : '卡种已停用', icon: 'success' })
    reload()
}
const remove = async (row: any) => {
    const modal = await uni.showModal({ title: '删除草稿卡种', content: `确认删除“${row.product_name}”？删除后不能恢复。`, confirmColor: '#dc2626', confirmText: '删除' })
    if (!modal.confirm) return
    await deleteCardProduct(row.id)
    uni.showToast({ title: '已删除', icon: 'success' })
    reload()
}
onShow(reload)
</script>

<style scoped lang="scss">
@import '../../styles/member-card-mobile.scss';
.mc-list-page { min-height: 100vh; background: #f5f7fb; }
.mc-fixed-filter { position: fixed; z-index: 10; top: 0; right: 0; left: 0; border-bottom: 1rpx solid #e6ebf2; background: #fff; }
.mc-search-bar { padding: 16rpx 24rpx 2rpx; }
.product-list { padding-bottom: 30rpx; }
.product-card { margin-bottom: 16rpx; overflow: hidden; }
.product-card__head { display: flex; padding: 22rpx; align-items: center; gap: 14rpx; }
.product-card__icon { display: flex; width: 58rpx; height: 58rpx; flex: 0 0 58rpx; align-items: center; justify-content: center; border-radius: 15rpx; background: #eff6ff; }
.product-card__copy { display: flex; min-width: 0; flex: 1; flex-direction: column; gap: 5rpx; }
.product-card__copy strong { overflow: hidden; font-size: 29rpx; text-overflow: ellipsis; white-space: nowrap; }
.product-card__copy text { color: #64748b; font-size: 21rpx; }
.product-card__metrics { display: grid; grid-template-columns: .8fr .75fr 1.45fr; margin: 0 22rpx; border-radius: 14rpx; background: #f8fafc; }
.product-card__metrics view { display: flex; min-width: 0; padding: 17rpx 14rpx; flex-direction: column; gap: 7rpx; }
.product-card__metrics view + view { border-left: 1rpx solid #e9eef5; }
.product-card__metrics text { color: #94a3b8; font-size: 19rpx; }
.product-card__metrics strong { overflow: hidden; font-size: 23rpx; text-overflow: ellipsis; white-space: nowrap; }
.product-card__metrics view:first-child strong { color: #ea580c; }
.product-card__foot { display: flex; min-height: 76rpx; padding: 0 22rpx; align-items: center; justify-content: space-between; gap: 15rpx; }
.product-no { min-width: 0; overflow: hidden; color: #94a3b8; font-size: 19rpx; text-overflow: ellipsis; white-space: nowrap; }
.product-actions { display: flex; flex: 0 0 auto; align-items: center; gap: 23rpx; }
.action-text { color: #475569; font-size: 23rpx; }
.action-text--primary { color: #2563eb; }.action-text--warning { color: #d97706; }.action-text--danger { color: #dc2626; }
</style>
