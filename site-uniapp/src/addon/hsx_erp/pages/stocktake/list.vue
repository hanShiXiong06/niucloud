<template>
    <view class="erp-page stocktake-page">
        <ErpListHeader
            v-model="keyword"
            placeholder="仓库 / 操作人"
            :show-scan="false"
            :compact-mp="true"
            @search="reload"
        >
            <template #below>
                <ErpQuickFilterBar :items="quickFilters" @change="onQuickFilter" />
            </template>
        </ErpListHeader>

        <z-paging
            ref="pagingRef"
            v-model="list"
            :fixed="true"
            :default-page-size="15"
            :paging-style="pagingStyle"
            @query="queryList"
        >
            <template #empty>
                <u-empty mode="list" text="暂无盘点任务" />
            </template>

            <view class="list-wrap">
                <ErpStocktakeSummary
                    v-for="row in list"
                    :key="row.id"
                    class="task-summary"
                    :data="row"
                    compact
                    show-footer
                    link-text="查看"
                    @click="goDetail(row)"
                />
            </view>
        </z-paging>

        <view class="fab fab--label" @click="openCreate">
            <u-icon name="plus" color="#fff" size="20" />
            <text class="fab__text">新建盘点</text>
        </view>

        <u-popup
            :show="createVisible"
            mode="bottom"
            round="20"
            :safe-area-inset-bottom="true"
            @close="createVisible = false"
        >
            <view class="create-sheet">
                <view class="sheet-head">
                    <view>
                        <text class="sheet-title">新建库存盘点</text>
                        <text class="sheet-sub">选择盘点范围，系统自动生成当前库存快照</text>
                    </view>
                    <u-icon name="close" size="20" color="#94a3b8" @click="createVisible = false" />
                </view>

                <view class="form-section">
                    <u-cell-group :border="false">
                        <u-cell title="盘点范围" :label="scopeText" isLink @click="warehouseVisible = true">
                            <template #icon>
                                <view class="cell-icon blue"><u-icon name="map" color="#3b6ef5" size="18" /></view>
                            </template>
                        </u-cell>
                    </u-cell-group>
                </view>

                <view class="form-section mode-section">
                    <text class="section-label">盘点方式</text>
                    <u-tabs
                        :list="modeTabs"
                        :current="workflowModeIndex"
                        lineWidth="28"
                        lineHeight="4"
                        :activeStyle="tabActiveStyle"
                        :inactiveStyle="tabInactiveStyle"
                        @change="changeWorkflowMode"
                    />
                    <view class="mode-tip">
                        <u-icon name="info-circle" color="#64748b" size="15" />
                        <text>{{ workflowModeHelp }}</text>
                    </view>
                </view>

                <view class="form-section remark-section">
                    <text class="section-label">盘点说明</text>
                    <u-textarea
                        v-model="form.remark"
                        maxlength="200"
                        placeholder="选填，例如：月底全仓盘点"
                        height="120rpx"
                        :customStyle="textareaStyle"
                    />
                </view>

                <view class="sheet-actions">
                    <u-button type="primary" :loading="creating" text="创建并开始盘点" @click="createTask" />
                </view>
            </view>
        </u-popup>

        <ErpWarehousePopup
            v-model:show="warehouseVisible"
            v-model:warehouse-id="form.warehouse_id"
            v-model:warehouse-name="form.warehouse_name"
            v-model:location-id="form.location_id"
            v-model:location-name="form.location_name"
            :allow-warehouse-only="true"
        />
    </view>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { createMobileStocktake, getMobileStocktakeList } from '@/addon/hsx_erp/api/erp'
import { useListHeader } from '@/addon/hsx_erp/hooks/useListHeader'
import ErpListHeader from '@/addon/hsx_erp/components/ErpListHeader.vue'
import ErpStocktakeSummary from '@/addon/hsx_erp/components/ErpStocktakeSummary.vue'
import ErpWarehousePopup from '@/addon/hsx_erp/components/ErpWarehousePopup.vue'
import ErpQuickFilterBar from '@/addon/hsx_erp/components/ErpQuickFilterBar.vue'

const { pagingStyle } = useListHeader({ tabs: true, compactMp: true, h5TopRpx: 178 })
const keyword = ref('')
const status = ref('')
const workflowMode = ref('')
const list = ref<any[]>([])
const pagingRef = ref<any>(null)
const createVisible = ref(false)
const warehouseVisible = ref(false)
const creating = ref(false)

const tabs = [
    { label: '全部', value: '' },
    { label: '盘点中', value: 'counting' },
    { label: '待复核', value: 'pending_review' },
    { label: '已完成', value: 'completed' },
]
const quickFilters = computed(() => [
    { key: 'status', label: '盘点状态', title: '盘点状态', value: status.value, options: tabs },
    { key: 'workflow_mode', label: '盘点方式', title: '盘点方式', value: workflowMode.value, options: [
        { label: '全部方式', value: '' }, { label: '快速盘点', value: 'simple' }, { label: '分工复核', value: 'team' },
    ] },
])

const modeTabs = [
    { name: '快速盘点', value: 'simple' },
    { name: '分工复核', value: 'team' },
]

const form = reactive<any>({
    warehouse_id: 0,
    warehouse_name: '',
    location_id: 0,
    location_name: '',
    workflow_mode: 'simple',
    remark: '',
})

const scopeText = computed(() => form.warehouse_name
    ? `${form.warehouse_name}${form.location_name ? ` / ${form.location_name}` : ' / 全部库位'}`
    : '请选择仓库或库位')
const workflowModeIndex = computed(() => form.workflow_mode === 'team' ? 1 : 0)
const workflowModeHelp = computed(() => form.workflow_mode === 'simple'
    ? '同一人扫码、核对并完成，适合日常快速盘点。'
    : '盘点人与复核人分工，适合多人门店；请在 PC 端创建。')

const tabActiveStyle = { color: '#2563eb', fontWeight: '650', fontSize: '27rpx' }
const tabInactiveStyle = { color: '#64748b', fontSize: '27rpx' }
const textareaStyle = { background: '#f8fafc', padding: '18rpx', borderRadius: '12rpx' }

const reload = () => pagingRef.value?.reload()

function changeStatus(value: string) {
    status.value = value
    reload()
}
const onQuickFilter = ({ key, value }: { key: string; value: string | number }) => {
    if (key === 'status') return changeStatus(String(value))
    workflowMode.value = String(value)
    reload()
}

function changeWorkflowMode(index: any) {
    const selected = modeTabs[Number(index?.index ?? index)] || modeTabs[0]
    form.workflow_mode = selected.value
}

async function queryList(page: number, limit: number) {
    try {
        const response: any = await getMobileStocktakeList({
            keyword: keyword.value,
            status: status.value,
            workflow_mode: workflowMode.value,
            page,
            limit,
        })
        pagingRef.value?.complete(response?.data?.data || [])
    } catch (_) {
        pagingRef.value?.complete(false)
    }
}

function openCreate() {
    Object.assign(form, {
        warehouse_id: 0,
        warehouse_name: '',
        location_id: 0,
        location_name: '',
        workflow_mode: 'simple',
        remark: '',
    })
    createVisible.value = true
}

async function createTask() {
    if (!form.warehouse_id) {
        uni.showToast({ title: '请选择盘点仓库', icon: 'none' })
        return
    }
    if (form.workflow_mode === 'team') {
        uni.showToast({ title: '分工复核请在 PC 端创建并指定复核人', icon: 'none' })
        return
    }

    creating.value = true
    try {
        const response: any = await createMobileStocktake({ ...form, scope_type: 'all' })
        const taskId = Number(response?.data?.id || response?.data || 0)
        if (!taskId) throw new Error('盘点任务创建成功，但未返回任务编号')
        createVisible.value = false
        uni.navigateTo({ url: `/addon/hsx_erp/pages/stocktake/detail?id=${taskId}` })
    } finally {
        creating.value = false
    }
}

function goDetail(row: any) {
    uni.navigateTo({ url: `/addon/hsx_erp/pages/stocktake/detail?id=${Number(row.id)}` })
}

onShow(reload)
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';

.stocktake-page {
    padding-bottom: 120rpx;
}

.task-summary:not(:first-child) {
    margin: 0 24rpx 20rpx;
}

.create-sheet {
    padding: 30rpx 28rpx 24rpx;
}

.sheet-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24rpx;
    margin-bottom: 26rpx;
}

.sheet-title,
.sheet-sub {
    display: block;
}

.sheet-title {
    color: #0f172a;
    font-size: 32rpx;
    font-weight: 700;
}

.sheet-sub {
    margin-top: 7rpx;
    color: #94a3b8;
    font-size: 22rpx;
}

.form-section {
    margin-top: 18rpx;
    border: 1rpx solid #eef2f7;
    border-radius: 18rpx;
    background: #fff;
    overflow: hidden;
}

.list-wrap{
    margin: 0 24rpx;

}
.mode-section,
.remark-section {
    padding: 22rpx 24rpx;
}

.section-label {
    display: block;
    margin-bottom: 8rpx;
    color: #334155;
    font-size: 26rpx;
    font-weight: 600;
}

.mode-tip {
    display: flex;
    align-items: flex-start;
    gap: 8rpx;
    margin-top: 10rpx;
    padding: 14rpx 16rpx;
    border-radius: 12rpx;
    background: #f8fafc;
    color: #64748b;
    font-size: 21rpx;
    line-height: 1.55;
}

.cell-icon {
    width: 56rpx;
    height: 56rpx;
    margin-right: 16rpx;
    border-radius: 14rpx;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cell-icon.blue {
    background: #eff6ff;
}

.sheet-actions {
    margin-top: 26rpx;
}
</style>
