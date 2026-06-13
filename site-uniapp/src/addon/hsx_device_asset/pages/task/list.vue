<template>
    <view class="task-page">
        <view class="page-head">
            <view><view class="page-title">我的待办</view><view class="page-desc">只显示归你负责库位的设备，办完一台继续下一台</view></view>
            <view class="scan-action" @click="scanCode"><text class="nc-iconfont nc-icon-saoyisaoV6xx scan-action__icon"></text>扫码</view>
        </view>
        <scroll-view scroll-x class="tab-scroll"><view class="tab-row">
            <view v-for="item in tabs" :key="item.key" class="tab-item" :class="{ active: activeTab === item.key }" @click="changeTab(item.key)">
                <text>{{ item.label }}</text><text class="tab-count">{{ stats[item.key] || 0 }}</text>
            </view>
        </view></scroll-view>
        <view class="search-row"><input v-model.trim="keyword" placeholder="搜索型号、IMEI、SN、资产编号" confirm-type="search" @confirm="reload" /><view class="search-btn" @click="reload">查询</view></view>
        <view v-if="loading" class="state-tip">加载中...</view>
        <view v-else-if="!list.length" class="state-tip">当前状态暂无任务</view>
        <view v-else class="task-list">
            <view v-for="item in list" :key="`${activeTab}-${item.id}`" class="task-card">
                <view class="task-card__head"><view class="device-name">{{ item.model || item.asset_no || `设备 ${item.id}` }}</view><view class="status-tag">{{ activeTab === "pool" ? "待入库" : (item.status_name || item.status) }}</view></view>
                <view class="device-meta">IMEI {{ item.imei || "-" }}</view><view class="device-meta">SN {{ item.sn || "-" }}<text v-if="item.asset_no"> · {{ item.asset_no }}</text></view>
                <view class="info-grid">
                    <view><text>成本</text><strong>¥{{ money(item.recycle_final_price ?? item.final_price) }}</strong></view>
                    <view v-if="activeTab !== 'pool'"><text>图片</text><strong>{{ item.image_count || 0 }} 张</strong></view>
                    <view v-if="activeTab !== 'pool'"><text>销售价</text><strong>¥{{ money(item.sale_price) }}</strong></view>
                    <view v-else><text>回收状态</text><strong>{{ item.status_name || "已完成" }}</strong></view>
                </view>
                <view class="card-footer"><view class="next-step">{{ nextAction(item) }}</view><view v-if="activeTab !== 'pool'" class="loc-btn" @click.stop="openSetLocation(item)">{{ item.location_id ? '改库位' : '设库位' }}</view><button class="action-btn" :loading="item._loading" @click="handleItem(item)">{{ actionText(item) }}</button></view>
            </view>
        </view>
        <view v-if="list.length < total && !loading" class="load-more" @click="loadMore">加载更多</view>

        <SetLocationPopup v-model:show="setLocVisible" :asset="setLocAsset" @success="reload" />
    </view>
</template>
<script setup lang="ts">
import { computed, reactive, ref } from "vue"
import { onLoad, onShow } from "@dcloudio/uni-app"
import { getAssetList, getAssetPool, getAssetStats, importAssets } from "@/addon/hsx_device_asset/api/device_asset"
import SetLocationPopup from "@/addon/hsx_device_asset/components/SetLocationPopup.vue"
type TaskTab = "pending" | "pool" | "photo" | "price" | "completed"
const setLocVisible = ref(false)
const setLocAsset = ref<any>(null)
const openSetLocation = (item: any) => { setLocAsset.value = item; setLocVisible.value = true }
const activeTab = ref<TaskTab>("photo")
const keyword = ref("")
const list = ref<any[]>([])
const loading = ref(false)
const page = ref(1)
const total = ref(0)
const stats = reactive<Record<TaskTab, number>>({ pending: 0, pool: 0, photo: 0, price: 0, completed: 0 })
// 中台只做拍照+定价，不做入库：页签只留 待拍照 → 待定价 → 已完成
const tabs = computed(() => [
    { key: "photo" as TaskTab, label: "待拍照" }, { key: "price" as TaskTab, label: "待定价" },
    { key: "completed" as TaskTab, label: "已完成" }
])
onLoad((options: any) => { const tab = String(options?.tab || "") as TaskTab; if (["photo", "price", "completed"].includes(tab)) activeTab.value = tab })
onShow(() => reload())
const loadStats = async () => { const res: any = await getAssetStats(); Object.assign(stats, res.data || {}) }
const loadList = async (append = false) => {
    loading.value = true
    try {
        const params = { keyword: keyword.value, page: page.value, limit: 10 }
        const res: any = activeTab.value === "pool" ? await getAssetPool(params) : await getAssetList({ ...params, task_type: activeTab.value })
        const data = res.data?.data || []
        list.value = append ? [...list.value, ...data] : data
        total.value = Number(res.data?.total || 0)
    } finally { loading.value = false }
}
const reload = async () => { page.value = 1; await Promise.all([loadList(), loadStats()]) }
const loadMore = async () => { if (list.value.length >= total.value) return; page.value += 1; await loadList(true) }
const changeTab = (tab: TaskTab) => { if (activeTab.value === tab) return; activeTab.value = tab; keyword.value = ""; reload() }
const needsPhoto = (item: any) => ["wait_photo", "photoing", "photo_review", "photo_rejected"].includes(item.status)
const needsPrice = (item: any) => ["wait_price", "priced"].includes(item.status)
const handleItem = async (item: any) => {
    if (activeTab.value === "pool") {
        item._loading = true
        try { await importAssets([Number(item.id)]); uni.showToast({ title: "已入库", icon: "success" }); await reload() } finally { item._loading = false }
        return
    }
    if (needsPhoto(item)) return uni.navigateTo({ url: `/addon/hsx_device_asset/pages/photo/capture?id=${item.id}` })
    uni.navigateTo({ url: `/addon/hsx_device_asset/pages/price/detail?id=${item.id}` })
}
const actionText = (item: any) => activeTab.value === "pool" ? "确认入库" : (needsPhoto(item) ? "去拍照" : (needsPrice(item) ? "直接定价" : "查看"))
const nextAction = (item: any) => activeTab.value === "pool" ? "下一步：建立资产并进入拍照" : (needsPhoto(item) ? "下一步：拍照或复检" : (needsPrice(item) ? "下一步：查看资料并定价" : "流程已完成，可查看资料"))
const money = (value: any) => Number(value || 0).toFixed(2)
const scanCode = () => uni.scanCode({ onlyFromCamera: false, success: (res) => {
    const value = String(res.result || "").trim(); const id = /^\d+$/.test(value) ? value : (value.match(/[?&]id=(\d+)/)?.[1] || "")
    if (!id) return uni.showToast({ title: "未识别到资产ID", icon: "none" })
    const path = activeTab.value === "photo" ? "photo/capture" : "price/detail"
    uni.navigateTo({ url: `/addon/hsx_device_asset/pages/${path}?id=${id}` })
} })
</script>
<style lang="scss" scoped>
.task-page{min-height:100vh;padding:24rpx;background:#f5f7fb;box-sizing:border-box}.page-head{display:flex;align-items:center;justify-content:space-between;padding:8rpx 4rpx 22rpx}.page-title{color:#111827;font-size:38rpx;font-weight:750}.page-desc{margin-top:8rpx;color:#64748b;font-size:24rpx}.scan-action{display:flex;align-items:center;padding:14rpx 24rpx;border-radius:999rpx;color:var(--hsx-primary);background:var(--hsx-primary-100);font-size:25rpx}.scan-action__icon{margin-right:6rpx;font-size:24rpx}.tab-scroll{width:100%;white-space:nowrap}.tab-row{display:flex;gap:12rpx;padding-bottom:18rpx}.tab-item{display:flex;align-items:center;gap:10rpx;padding:18rpx 24rpx;border-radius:16rpx;color:#475569;background:#fff;font-size:26rpx}.tab-item.active{color:#fff;background:var(--hsx-primary)}.tab-count{min-width:34rpx;height:34rpx;padding:0 6rpx;border-radius:17rpx;line-height:34rpx;text-align:center;background:rgba(148,163,184,.18);font-size:21rpx}.search-row{display:grid;grid-template-columns:1fr 120rpx;gap:12rpx;margin-bottom:20rpx}.search-row input{height:76rpx;padding:0 22rpx;border-radius:16rpx;background:#fff;font-size:25rpx}.search-btn{height:76rpx;border-radius:16rpx;color:#fff;background:#0f172a;line-height:76rpx;text-align:center;font-size:25rpx}.task-list{display:grid;gap:18rpx}.task-card{padding:24rpx;border-radius:20rpx;background:#fff;box-shadow:0 8rpx 24rpx rgba(15,23,42,.05)}.task-card__head{display:flex;align-items:flex-start;justify-content:space-between;gap:16rpx}.device-name{flex:1;color:#111827;font-size:31rpx;font-weight:700}.status-tag{padding:7rpx 14rpx;border-radius:999rpx;color:var(--hsx-primary);background:var(--hsx-primary-50);font-size:21rpx}.device-meta{margin-top:8rpx;color:#64748b;font-size:23rpx}.info-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10rpx;margin-top:20rpx}.info-grid view{padding:15rpx;border-radius:14rpx;background:#f8fafc}.info-grid text{display:block;color:#94a3b8;font-size:21rpx}.info-grid strong{display:block;margin-top:7rpx;color:#0f172a;font-size:25rpx}.card-footer{display:flex;align-items:center;gap:18rpx;margin-top:22rpx;padding-top:18rpx;border-top:1rpx solid #eef2f7}.next-step{flex:1;color:#64748b;font-size:23rpx}.loc-btn{padding:0 22rpx;height:68rpx;line-height:68rpx;border-radius:34rpx;color:var(--hsx-primary);background:var(--hsx-primary-50);font-size:23rpx;margin-right:16rpx}.action-btn{width:180rpx;height:68rpx;margin:0;border-radius:34rpx;color:#fff;background:var(--hsx-primary);line-height:68rpx;font-size:25rpx}.state-tip,.load-more{padding:80rpx 0;color:#94a3b8;text-align:center;font-size:25rpx}.load-more{padding:32rpx 0;color:var(--hsx-primary)}
</style>
