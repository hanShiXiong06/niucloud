<template>
    <view class="asset-hub">
        <z-paging
            ref="paging"
            v-model="list"
            :fixed="true"
            :auto="true"
            :empty-view-text="emptyText"
            @query="queryList"
        >
            <!-- 固定头部：紧凑分段筛选 + 搜索（不占大块空间，留足列表区） -->
            <template #top>
                <view class="hub-top">
                    <view class="hub-head">
                        <view class="hub-head__title">资产中台</view>
                        <view class="hub-scan" @click="scanCode">
                            <u-icon name="scan" color="#ffffff" size="16"></u-icon>
                            <text class="hub-scan__txt">扫码拍照</text>
                        </view>
                    </view>

                    <view class="seg">
                        <view
                            class="seg__item seg__item--photo"
                            :class="{ 'is-active': currentType === 'photo' }"
                            @click="selectTab('photo')"
                        >
                            <text class="seg__label">待拍照</text>
                            <text class="seg__count">{{ stats.photo || 0 }}</text>
                        </view>
                        <view
                            class="seg__item seg__item--price"
                            :class="{ 'is-active': currentType === 'price' }"
                            @click="selectTab('price')"
                        >
                            <text class="seg__label">待定价</text>
                            <text class="seg__count">{{ stats.price || 0 }}</text>
                        </view>
                        <view
                            class="seg__item seg__item--done"
                            :class="{ 'is-active': currentType === 'completed' }"
                            @click="selectTab('completed')"
                        >
                            <text class="seg__label">已完成</text>
                            <text class="seg__count">{{ stats.completed || 0 }}</text>
                        </view>
                    </view>

                    <u-search
                        v-model="keyword"
                        :show-action="false"
                        shape="square"
                        placeholder="搜索型号 / IMEI / SN / 资产编号"
                        bgColor="#ffffff"
                        :custom-style="{ margin: '0 24rpx 14rpx' }"
                        @search="reloadList"
                        @clear="reloadList"
                    ></u-search>
                </view>
            </template>

            <!-- 队列（z-paging 自动上拉加载 / 下拉刷新） -->
            <view class="task-list">
                <view v-for="item in list" :key="`${currentType}-${item.id}`" class="task-card">
                    <view class="task-card__head">
                        <text class="task-card__name">{{ item.model || item.asset_no || `设备 ${item.id}` }}</text>
                        <u-tag
                            :text="item.status_name || item.status"
                            size="mini"
                            plain
                            plainFill
                            :type="statusTagType(item)"
                        ></u-tag>
                    </view>

                    <view class="task-card__meta">
                        <text>IMEI {{ item.imei || '-' }}</text>
                        <text class="task-card__meta-sep">·</text>
                        <text>SN {{ item.sn || '-' }}</text>
                    </view>
                    <view v-if="item.asset_no" class="task-card__asset">资产编号 {{ item.asset_no }}</view>

                    <view class="info-grid">
                        <view class="info-grid__cell">
                            <text class="info-grid__k">成本</text>
                            <text class="info-grid__v">¥{{ money(item.recycle_final_price ?? item.final_price) }}</text>
                        </view>
                        <view class="info-grid__cell">
                            <text class="info-grid__k">图片</text>
                            <text class="info-grid__v">{{ item.image_count || 0 }} 张</text>
                        </view>
                        <view class="info-grid__cell">
                            <text class="info-grid__k">销售价</text>
                            <text class="info-grid__v info-grid__v--price">¥{{ money(item.sale_price) }}</text>
                        </view>
                    </view>

                    <view class="task-card__foot">
                        <view class="next-step">
                            <u-icon name="play-right-fill" color="#94a3b8" size="12"></u-icon>
                            <text>{{ nextAction(item) }}</text>
                        </view>
                        <view class="task-card__actions">
                            <u-button
                                v-if="!completedMode"
                                size="mini"
                                :plain="true"
                                :custom-style="locBtnStyle"
                                @click="openSetLocation(item)"
                            >{{ item.location_id ? '改库位' : '设库位' }}</u-button>
                            <u-button
                                size="mini"
                                type="primary"
                                :color="needsPhoto(item) ? '#fa5c1e' : '#3c9cff'"
                                :custom-style="{ minWidth: '150rpx' }"
                                @click="handleItem(item)"
                            >{{ actionText(item) }}</u-button>
                        </view>
                    </view>
                </view>
            </view>
        </z-paging>

        <SetLocationPopup v-model:show="setLocVisible" :asset="setLocAsset" @success="reloadList" />
    </view>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { getAssetList, getAssetStats } from '@/addon/hsx_device_asset/api/device_asset'
import SetLocationPopup from '@/addon/hsx_device_asset/components/SetLocationPopup.vue'

type TaskTab = 'photo' | 'price' | 'completed'

const LABEL: Record<string, string> = { photo: '待拍照', price: '待定价', completed: '已完成' }

const paging = ref<any>(null)
const list = ref<any[]>([])
const activeTab = ref<TaskTab>('photo')
const completedMode = ref(false)
const keyword = ref('')
const stats = reactive<Record<string, number>>({ photo: 0, price: 0, completed: 0, total: 0 })

const setLocVisible = ref(false)
const setLocAsset = ref<any>(null)
const openSetLocation = (item: any) => { setLocAsset.value = item; setLocVisible.value = true }

const currentType = computed(() => completedMode.value ? 'completed' : activeTab.value)
const emptyText = computed(() => completedMode.value ? '暂无已完成记录' : `暂无${LABEL[currentType.value]}任务`)
const locBtnStyle = { color: '#64748b', borderColor: '#e2e8f0', marginRight: '14rpx' }

onLoad((options: any) => {
    const tab = String(options?.tab || '')
    if (['photo', 'price'].includes(tab)) activeTab.value = tab as TaskTab
    if (tab === 'completed') completedMode.value = true
})

// 从拍照/定价详情返回时刷新：首屏由 z-paging 自动加载（跳过），之后每次返回都重拉列表与计数
let firstShow = true
onShow(() => {
    if (firstShow) {
        firstShow = false
        return
    }
    paging.value?.reload()
})

const loadStats = async () => {
    try {
        const res: any = await getAssetStats()
        Object.assign(stats, res.data || {})
    } catch {
        // 统计失败不影响列表
    }
}

// z-paging 查询回调：pageNo/pageSize 由组件驱动，自动上拉加载、下拉刷新
const queryList = async (pageNo: number, pageSize: number) => {
    if (pageNo === 1) loadStats()
    try {
        const res: any = await getAssetList({
            keyword: keyword.value,
            page: pageNo,
            limit: pageSize,
            task_type: currentType.value
        })
        paging.value?.complete(res.data?.data || [])
    } catch (e) {
        paging.value?.complete(false)
    }
}

const reloadList = () => paging.value?.reload()

const selectTab = (key: TaskTab) => {
    if (key === 'completed') {
        if (completedMode.value) return
        completedMode.value = true
    } else {
        if (!completedMode.value && activeTab.value === key) return
        completedMode.value = false
        activeTab.value = key
    }
    keyword.value = ''
    reloadList()
}

const needsPhoto = (item: any) => ['wait_photo', 'photoing', 'photo_review', 'photo_rejected'].includes(item.status)
const needsPrice = (item: any) => item.status === 'wait_price'

const statusTagType = (item: any) => {
    if (item.status === 'photo_rejected' || item.status === 'photo_review') return 'warning'
    if (needsPhoto(item)) return 'error'
    if (needsPrice(item)) return 'primary'
    return 'success'
}

const handleItem = (item: any) => {
    if (needsPhoto(item)) {
        return uni.navigateTo({ url: `/addon/hsx_device_asset/pages/photo/capture?id=${item.id}` })
    }
    uni.navigateTo({ url: `/addon/hsx_device_asset/pages/price/detail?id=${item.id}` })
}
const actionText = (item: any) => needsPhoto(item) ? '去拍照' : (needsPrice(item) ? '直接定价' : '查看')
const nextAction = (item: any) => needsPhoto(item)
    ? '下一步：拍照或复检'
    : (needsPrice(item) ? '下一步：查看资料并定价' : '流程已完成，可查看资料')
const money = (value: any) => Number(value || 0).toFixed(2)

const scanCode = () => uni.scanCode({
    onlyFromCamera: false,
    success: (res) => {
        const value = String(res.result || '').trim()
        const id = /^\d+$/.test(value) ? value : (value.match(/[?&]id=(\d+)/)?.[1] || '')
        if (!id) return uni.showToast({ title: '未识别到资产ID', icon: 'none' })
        uni.navigateTo({ url: `/addon/hsx_device_asset/pages/photo/capture?id=${id}` })
    }
})
</script>

<style lang="scss" scoped>
.asset-hub {
    height: 100vh;
    background: #f5f7fb;
}

.hub-top {
    padding: 16rpx 0 0;
    background: #f5f7fb;
}

/* 标题行（紧凑） */
.hub-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24rpx 14rpx;
}
.hub-head__title {
    color: #0f172a;
    font-size: 34rpx;
    font-weight: 800;
}
.hub-scan {
    display: flex;
    align-items: center;
    gap: 8rpx;
    padding: 12rpx 22rpx;
    border-radius: 999rpx;
    background: linear-gradient(135deg, #3c9cff, #2b85e4);
    box-shadow: 0 6rpx 16rpx rgba(60, 156, 255, 0.3);
}
.hub-scan__txt {
    color: #fff;
    font-size: 24rpx;
    font-weight: 600;
}

/* 分段筛选：紧凑、带数量、常驻 */
.seg {
    display: flex;
    gap: 12rpx;
    padding: 0 24rpx 14rpx;
}
.seg__item {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10rpx;
    height: 76rpx;
    border-radius: 16rpx;
    background: #fff;
    box-shadow: 0 4rpx 14rpx rgba(15, 23, 42, 0.04);
}
.seg__label {
    color: #475569;
    font-size: 26rpx;
    font-weight: 600;
}
.seg__count {
    min-width: 36rpx;
    height: 36rpx;
    padding: 0 8rpx;
    border-radius: 18rpx;
    line-height: 36rpx;
    text-align: center;
    background: #f1f5f9;
    color: #64748b;
    font-size: 22rpx;
}
.seg__item--photo.is-active {
    background: #fa5c1e;
}
.seg__item--price.is-active {
    background: #3c9cff;
}
.seg__item--done.is-active {
    background: #22c55e;
}
.seg__item.is-active .seg__label {
    color: #fff;
}
.seg__item.is-active .seg__count {
    background: rgba(255, 255, 255, 0.25);
    color: #fff;
}

/* 队列卡片 */
.task-list {
    display: flex;
    flex-direction: column;
    gap: 18rpx;
    padding: 4rpx 24rpx 24rpx;
}
.task-card {
    padding: 24rpx;
    border-radius: 20rpx;
    background: #fff;
    box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.05);
}
.task-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16rpx;
}
.task-card__name {
    flex: 1;
    color: #0f172a;
    font-size: 30rpx;
    font-weight: 700;
    line-height: 40rpx;
}
.task-card__meta {
    margin-top: 10rpx;
    color: #64748b;
    font-size: 23rpx;
}
.task-card__meta-sep { margin: 0 10rpx; color: #cbd5e1; }
.task-card__asset {
    margin-top: 4rpx;
    color: #94a3b8;
    font-size: 22rpx;
}
.info-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12rpx;
    margin-top: 20rpx;
}
.info-grid__cell {
    padding: 16rpx;
    border-radius: 14rpx;
    background: #f8fafc;
}
.info-grid__k {
    display: block;
    color: #94a3b8;
    font-size: 21rpx;
}
.info-grid__v {
    display: block;
    margin-top: 8rpx;
    color: #0f172a;
    font-size: 26rpx;
    font-weight: 600;
}
.info-grid__v--price { color: #fa5c1e; }
.task-card__foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16rpx;
    margin-top: 22rpx;
    padding-top: 18rpx;
    border-top: 1rpx solid #eef2f7;
}
.next-step {
    display: flex;
    align-items: center;
    gap: 8rpx;
    flex: 1;
    color: #64748b;
    font-size: 23rpx;
}
.task-card__actions {
    display: flex;
    align-items: center;
}
</style>
