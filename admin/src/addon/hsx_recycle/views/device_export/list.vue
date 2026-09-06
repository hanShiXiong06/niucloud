<template>
    <!--回收设备导出-->
    <PremiumTheme class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <PageHeader :title="pageName" description="按 IMEI / 型号 / 分类 / 时间等条件筛选，导出设备明细或同步到 ERP。" />
            <HsxNotice default-expanded v-if="syncHealthError" class="mt-4" type="warning" :closable="false" show-icon :title="syncHealthError">
                <el-button link type="primary" :loading="syncHealthLoading || deviceTableData.loading" @click="loadDeviceList">刷新并重试</el-button>
            </HsxNotice>

            <el-card class="box-card !border-none my-[20px] table-search-wrap" shadow="never">
                <HsxSearchPanel>
                    <el-form :inline="true" :model="deviceTableData.searchParam" ref="searchFormRef">
                        <el-form-item :label="t('imei')" prop="imei">
                            <el-input v-model.trim="deviceTableData.searchParam.imei" class="!w-[200px]" :placeholder="t('请输入IMEI')" />
                        </el-form-item>

                        <el-form-item :label="t('型号名称')" prop="model">
                            <el-input v-model.trim="deviceTableData.searchParam.model" class="!w-[200px]" :placeholder="t('请输入型号名称')" />
                        </el-form-item>

                        <el-form-item :label="t('分类')" prop="category_id">
                            <el-select v-model="deviceTableData.searchParam.category_id" clearable :placeholder="t('请选择分类')" class="input-width">
                                <el-option :label="t('请选择分类')" value="" />
                                <el-option :label="item.name" :value="item.id" v-for="item in categoryList" :key="item.id" />
                            </el-select>
                        </el-form-item>

                        <el-form-item label="导出状态" prop="export_status">
                            <el-select v-model="deviceTableData.searchParam.export_status" class="!w-[150px]">
                                <el-option label="全部" value="" />
                                <el-option label="未导出" value="unexported" />
                            </el-select>
                        </el-form-item>

                        <el-form-item label="入库类型" prop="warehouse_type">
                            <el-select v-model="deviceTableData.searchParam.warehouse_type" class="!w-[150px]">
                                <el-option label="全部" value="" />
                                <el-option label="回收入库" value="owned" />
                                <el-option label="代卖入库" value="consign" />
                            </el-select>
                        </el-form-item>

                        <el-form-item :label="t('回收时间')" prop="update_at">
                            <el-date-picker
                                v-model="deviceTableData.searchParam.update_at"
                                type="daterange"
                                range-separator="至"
                                value-format="YYYY-MM-DD"
                                :start-placeholder="t('startDate')"
                                :end-placeholder="t('endDate')"
                                format="YYYY-MM-DD"
                                unlink-panels
                                clearable
                                :shortcuts="dateRangeShortcuts"
                        />
                        </el-form-item>

                        <el-form-item>
                            <el-button type="primary" @click="handleSearch">{{ t('search') }}</el-button>
                            <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
                            <el-button type="primary" @click="exportEvent" :disabled="deviceTableData.total === 0">
                            {{ selectedDevices.length > 0 ? `导出选中 (${selectedDevices.length})` : t('export') }}
                            </el-button>
                            <el-button
                                type="success"
                                :loading="erpSyncLoading"
                                :disabled="selectedDevices.length === 0 || !canSyncRows(selectedDevices)"
                                @click="syncErpEvent"
                        >
                            同步 ERP{{ selectedDevices.length > 0 ? ` (${selectedDevices.length})` : '' }}
                            </el-button>
                        </el-form-item>
                    </el-form>
                </HsxSearchPanel>
            </el-card>

            <div class="mt-[10px]">
                <el-table :data="deviceTableData.data" size="large" v-loading="deviceTableData.loading" :row-class-name="tableRowClassName" @selection-change="handleSelectionChange">
                    <template #empty>
                        <EmptyState
                            v-if="!deviceTableData.loading"
                            icon="search"
                            title="没有符合条件的设备"
                            description="调整 IMEI / 型号 / 分类 / 回收时间等筛选条件再试试。"
                        />
                    </template>

                    <el-table-column type="selection" width="50" align="center" />

                    <!-- 设备：型号 + 串号 + 分类 -->
                    <el-table-column label="设备" min-width="230">
                        <template #default="{ row }">
                            <div class="font-medium text-gray-800">{{ row.model || '未知型号' }}</div>
                            <div class="mt-0.5 text-xs text-gray-500">IMEI {{ row.imei || '—' }}</div>
                            <div v-if="row.category_name" class="text-xs text-gray-400">{{ row.category_name }}</div>
                        </template>
                    </el-table-column>

                    <!-- 价格：收货价 + 就地编辑的卖货价 -->
                    <el-table-column label="价格" min-width="180">
                        <template #default="{ row }">
                            <div class="flex items-center justify-between gap-2 text-xs">
                                <span class="text-gray-500">收货价</span>
                                <strong class="text-[var(--el-color-danger)]">¥{{ row.final_price || '0.00' }}</strong>
                            </div>
                            <div class="mt-1 flex items-center justify-between gap-2 text-xs">
                                <span class="text-gray-500">卖货价</span>
                                <el-input
                                    v-model="row.sell_price"
                                    size="small"
                                    type="number"
                                    :min="0"
                                    placeholder="0.00"
                                    style="width: 110px"
                                    @blur="saveSellPrice(row)"
                                    @keyup.enter="saveSellPrice(row)"
                                >
                                    <template #prefix>¥</template>
                                </el-input>
                            </div>
                        </template>
                    </el-table-column>

                    <!-- 来源：供货商 + 报价人 -->
                    <el-table-column label="来源" min-width="160">
                        <template #default="{ row }">
                            <div v-if="row.order?.member" class="flex items-center gap-2">
                                <el-avatar :size="22" :src="img(row.order.member.headimg)" v-if="row.order.member.headimg">
                                    <el-icon><User /></el-icon>
                                </el-avatar>
                                <span class="text-sm text-gray-800">{{ row.order.member.nickname || row.order.member.username || '未知' }}</span>
                            </div>
                            <span v-else class="text-sm text-gray-400">散户/未知</span>
                            <div class="mt-0.5 text-xs text-gray-400">
                                报价：{{ row.price_user ? (row.priceUser?.real_name || row.priceUser?.username || '未知') : '—' }}
                            </div>
                        </template>
                    </el-table-column>

                    <!-- 状态：设备状态 + 入库类型 + ERP 同步（柔和药丸） -->
                    <el-table-column label="状态" min-width="150">
                        <template #default="{ row }">
                            <div class="flex flex-wrap items-center gap-1">
                                <el-tag size="small" type="success" effect="plain">{{ row.status_name }}</el-tag>
                                <el-tag size="small" effect="plain" :type="row.dispose_type === 'consign' || row.status === 9 ? 'warning' : 'info'">
                                    {{ row.dispose_type === 'consign' || row.status === 9 ? '代卖入库' : '回收入库' }}
                                </el-tag>
                                <el-tag v-if="isSyncStatusUncertain(row)" size="small" type="warning" effect="plain">{{ syncHealthLoading ? '同步状态查询中' : '同步状态待确认' }}</el-tag>
                                <el-tooltip v-else-if="row.erp_sync" :content="row.erp_sync.asset_no || ''" placement="top">
                                    <el-tag size="small" effect="plain" :type="erpStatusMeta(row).type">{{ erpStatusMeta(row).label }}</el-tag>
                                </el-tooltip>
                                <el-tag v-else size="small" type="info" effect="plain">未同步</el-tag>
                                <el-tag v-if="erpPlacementMissing(row)" size="small" type="danger" effect="plain">缺少入库位置</el-tag>
                            </div>
                            <div v-if="row.consignmentOrder?.consignment_no || row.consignment_order?.consignment_no" class="mt-1 text-xs text-[var(--el-color-primary)]">
                                {{ row.consignmentOrder?.consignment_no || row.consignment_order?.consignment_no }}
                            </div>
                        </template>
                    </el-table-column>

                    <!-- 时间：回收 + 导出 -->
                    <el-table-column label="时间" width="178">
                        <template #default="{ row }">
                            <div class="text-xs leading-5 text-gray-600">
                                <div><span class="text-gray-400">回收 </span>{{ row.update_at || '—' }}</div>
                                <div v-if="row.export_time && row.export_time > 0"><span class="text-gray-400">导出 </span>{{ formatTimestamp(row.export_time) }}</div>
                                <el-tag v-else size="small" type="info" effect="plain">未导出</el-tag>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="操作" width="130" align="center" fixed="right">
                        <template #default="{ row }">
                            <el-tooltip content="查看详情" placement="top">
                                <el-button type="primary" link :icon="View" @click="viewDeviceDetail(row)" aria-label="查看详情" />
                            </el-tooltip>
                            <!-- 仅当检测到该设备下游同步"卡住"时才显示；同步正常时不出现，避免误操作 -->
                            <el-tooltip
                                v-if="isStuck(row)"
                                :content="`同步失效：${stuckReason(row)}。仅此时需要点「重新同步」补齐下游（如中台拍照），同步正常无需操作。`"
                                placement="top"
                            >
                                <el-button
                                    type="warning"
                                    link
                                    :icon="RefreshRight"
                                    :loading="resyncLoadingId === row.id"
                                    :disabled="!canSyncRows([row])"
                                    @click="handleResync(row)"
                                    aria-label="重新同步"
                                >重新同步</el-button>
                            </el-tooltip>
                        </template>
                    </el-table-column>
                </el-table>

                <div class="mt-[16px] flex justify-end">
                    <el-pagination
                        v-model:current-page="deviceTableData.page"
                        v-model:page-size="deviceTableData.limit"
                        layout="total, sizes, prev, pager, next, jumper"
                        :total="deviceTableData.total"
                        @size-change="loadDeviceList()"
                        @current-change="loadDeviceList"
                    />
                </div>
            </div>

            <export-sure ref="exportSureDialog" :show="flag" type="recycle_device" :searchParam="exportSearchParam" @close="handleClose" />
        </el-card>

        <!-- 设备详情对话框 -->
        <HsxDialog
            v-model="detailDialogVisible"
            title="设备详情"
            width="900px"
            :destroy-on-close="true"
        >
            <div v-if="currentDevice" class="space-y-4">
                <!-- 基本信息 -->
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <h4 class="text-base font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <span class="w-1 h-5 bg-blue-500 rounded"></span>
                        基本信息
                    </h4>
                    <el-descriptions :column="2" border size="default">
                        <el-descriptions-item label="设备型号" label-class-name="font-medium">
                            {{ currentDevice.model }}
                        </el-descriptions-item>
                        <el-descriptions-item label="IMEI" label-class-name="font-medium">
                            {{ currentDevice.imei }}
                        </el-descriptions-item>
                        <el-descriptions-item label="分类" label-class-name="font-medium">
                            {{ currentDevice.category_name }}
                        </el-descriptions-item>
                        <el-descriptions-item label="状态" label-class-name="font-medium">
                            <el-tag type="success">{{ currentDevice.status_name }}</el-tag>
                        </el-descriptions-item>
                        <el-descriptions-item label="收货价" label-class-name="font-medium">
                            <span class="text-orange-600 font-semibold text-base">¥{{ currentDevice.final_price || '0.00' }}</span>
                        </el-descriptions-item>
                        <el-descriptions-item label="卖货价格" label-class-name="font-medium">
                            <span class="text-green-600 font-semibold text-base">¥{{ currentDevice.sell_price || '0.00' }}</span>
                        </el-descriptions-item>
                        <el-descriptions-item label="回收时间" :span="2" label-class-name="font-medium">
                            {{ currentDevice.update_at }}
                        </el-descriptions-item>
                    </el-descriptions>
                </div>

                <!-- 供货商信息 -->
                <div v-if="currentDevice.order?.member" class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border border-blue-200 p-4">
                    <h4 class="text-base font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <span class="w-1 h-5 bg-indigo-500 rounded"></span>
                        供货商信息
                    </h4>
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-base font-semibold text-gray-900">
                                {{ currentDevice.order.member.nickname || currentDevice.order.member.username || '未知用户' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2 text-gray-600">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
                            </svg>
                            <span>{{ currentDevice.order.member.mobile || '未绑定手机' }}</span>
                        </div>
                    </div>
                </div>

                <!-- 买家质检结果 -->
                <div v-if="currentDevice.check_result_buyer || buyerCheckImages.length > 0" class="bg-white rounded-lg border border-green-300 overflow-hidden">
                    <div class="bg-gradient-to-r from-green-500 to-emerald-500 px-4 py-3">
                        <h4 class="text-base font-semibold text-white flex items-center gap-2">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            买家质检结果
                        </h4>
                    </div>

                    <div class="p-4 space-y-4">
                        <!-- 质检文字结果 -->
                        <div v-if="currentDevice.check_result_buyer" class="bg-green-50 rounded-lg p-4 border-l-4 border-green-500">
                            <div class="text-sm text-gray-700 leading-relaxed whitespace-pre-line font-medium">
                                {{ currentDevice.check_result_buyer }}
                            </div>
                        </div>

                        <!-- 质检图片 -->
                        <div v-if="buyerCheckImages.length > 0">
                            <div class="flex items-center gap-2 mb-3">
                                <svg class="w-4 h-4 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-sm font-medium text-gray-700">质检图片 ({{ buyerCheckImages.length }})</span>
                            </div>
                            <div class="grid grid-cols-4 gap-3">
                                <div
                                    v-for="(imgUrl, index) in buyerCheckImages"
                                    :key="index"
                                    class="relative group cursor-pointer rounded-lg overflow-hidden"
                                    @click="previewImages(buyerCheckImages, index)"
                                >
                                    <el-image
                                        :src="img(imgUrl)"
                                        fit="cover"
                                        class="w-full h-28 border-2 border-gray-200 group-hover:border-green-500 transition-all duration-200"
                                        lazy
                                    >
                                        <template #error>
                                            <div class="w-full h-28 bg-gray-100 flex flex-col items-center justify-center">
                                                <el-icon class="text-gray-400 text-2xl"><Picture /></el-icon>
                                                <span class="text-xs text-gray-400 mt-1">加载失败</span>
                                            </div>
                                        </template>
                                    </el-image>
                                    <!-- 悬浮遮罩 -->
                                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition-all duration-200 flex items-center justify-center">
                                        <svg class="w-8 h-8 text-white opacity-0 group-hover:opacity-100 transition-opacity duration-200" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                            <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <!-- 图片序号 -->
                                    <div class="absolute top-2 left-2 bg-black bg-opacity-60 text-white text-xs px-2 py-1 rounded">
                                        {{ index + 1 }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 无质检结果提示 -->
                        <div v-if="!currentDevice.check_result_buyer && buyerCheckImages.length === 0" class="text-center py-8">
                            <svg class="w-16 h-16 text-gray-300 mx-auto mb-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                            <p class="text-gray-400 text-sm">暂无买家质检结果</p>
                        </div>
                    </div>
                </div>
            </div>
        </HsxDialog>

        <HsxDialog :confirm-loading="placementSubmitting" v-model="placementDialogVisible" title="补全 ERP 入库位置" width="520px" destroy-on-close>
            <HsxNotice default-expanded
                type="warning"
                :closable="false"
                show-icon
                :title="`有 ${placementPendingRows.length} 台设备缺少仓库或库位，补全后将立即重新同步。`"
            />
            <el-form label-position="top" class="mt-5">
                <el-form-item label="入库仓库" required>
                    <el-select v-model="placementForm.target_warehouse_id" class="w-full" placeholder="请选择仓库" @change="onRepairWarehouseChange">
                        <el-option
                            v-for="warehouse in erpWarehouses"
                            :key="warehouse.id"
                            :label="warehouse.warehouse_name"
                            :value="Number(warehouse.id)"
                            :disabled="!(warehouse.locations || []).length"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item label="具体库位" required>
                    <el-select v-model="placementForm.target_location_id" class="w-full" placeholder="请选择库位" :disabled="!placementForm.target_warehouse_id">
                        <el-option v-for="location in repairLocations" :key="location.id" :label="location.location_name" :value="Number(location.id)" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button :disabled="placementSubmitting" @click="placementDialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="placementSubmitting" :disabled="(!canSyncRows(placementMode === 'single' ? placementPendingRows : placementSyncRows)) || (placementSubmitting)" @click="submitPlacementRepair">补全并同步</el-button>
            </template>
        </HsxDialog>

        <!-- 图片预览 -->
        <el-image-viewer
            v-if="imageViewerVisible"
            :url-list="previewImageList"
            :initial-index="previewImageIndex"
            @close="imageViewerVisible = false"
        />
    </PremiumTheme>
</template>

<script lang="ts" setup>
import { HsxSearchPanel, HsxDialog, HsxNotice, useFeedback } from '@/addon/hsx_components/core'
import { reactive, ref, computed } from 'vue'
import { t } from '@/lang'
import { FormInstance, ElImageViewer, ElMessageBox } from 'element-plus'
import { useRoute } from 'vue-router'
import { getRecycleDeviceList, syncRecycleDevicesToErp, updateDevice, getDeviceSyncHealth, resyncRecycleDevice } from '@/addon/hsx_recycle/api/device_export'
import { getSaleDestinationOptions } from '@/addon/hsx_recycle/api/recycle_order'
import { img } from '@/utils/common'
import { View, User, Picture, RefreshRight } from '@element-plus/icons-vue'
import PageHeader from '@/addon/hsx_recycle/components/PageHeader.vue'
import EmptyState from '@/addon/hsx_recycle/components/empty-state/index.vue'
import PremiumTheme from '@/addon/hsx_recycle/components/PremiumTheme.vue'
const hsxFeedback = useFeedback()


const route = useRoute()
const pageName = route.meta.title

const deviceTableData = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam: {
        imei: String(route.query.imei || ''),
        model: String(route.query.model || ''),
        category_id: String(route.query.category_id || ''),
        update_at: [],
        status: '',
        warehouse_type: String(route.query.warehouse_type || ''),
        export_status: String(route.query.export_status || '')
    }
})

const searchFormRef = ref<FormInstance>()

// 设备分类列表
const categoryList = ref([
    { id: 1, name: '手机' },
    { id: 2, name: '平板' },
    { id: 3, name: '笔记本' },
    { id: 4, name: '手表' },
    { id: 5, name: '其他' }
])

const formatDate = (date: Date): string => {
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, '0')
    const d = String(date.getDate()).padStart(2, '0')
    return `${y}-${m}-${d}`
}

/**
 * 获取最近 N 天日期范围，默认包含今天
 */
const getRecentDateRange = (days: number): string[] => {
    const endDate = new Date()
    const startDate = new Date()
    startDate.setDate(startDate.getDate() - Math.max(days - 1, 0))
    return [formatDate(startDate), formatDate(endDate)]
}

const setDefaultDateRange = () => {
    deviceTableData.searchParam.update_at = getRecentDateRange(7)
}

const dateRangeShortcuts = [
    {
        text: '今天',
        value: () => getRecentDateRange(1)
    },
    {
        text: '近7天',
        value: () => getRecentDateRange(7)
    },
    {
        text: '近15天',
        value: () => getRecentDateRange(15)
    },
    {
        text: '近30天',
        value: () => getRecentDateRange(30)
    }
]

const handleSearch = () => {
    deviceTableData.page = 1
    loadDeviceList()
}

const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    deviceTableData.searchParam.warehouse_type = ''
    deviceTableData.searchParam.export_status = ''
    setDefaultDateRange()
    deviceTableData.page = 1
    loadDeviceList()
}

/**
 * 处理时间范围，添加 00:00:00 - 23:59:59
 */
const formatTimeRange = (dateRange: string[]) => {
    if (!dateRange || dateRange.length !== 2) return []
    return [
        `${dateRange[0]} 00:00:00`,
        `${dateRange[1]} 23:59:59`
    ]
}

/**
 * 获取设备列表
 */
const loadDeviceList = () => {
    deviceTableData.loading = true
    syncHealthRequestId++
    syncHealthConfirmed.value = false
    syncHealthLoading.value = false
    syncHealthError.value = ''
    const searchParam = {
        ...deviceTableData.searchParam,
        update_at: formatTimeRange(deviceTableData.searchParam.update_at),
        page: deviceTableData.page,
        limit: deviceTableData.limit
    }

    getRecycleDeviceList(searchParam).then((res: any) => {
        deviceTableData.loading = false
        deviceTableData.data = res.data.data
        deviceTableData.total = res.data.total
        loadSyncHealth()
    }).catch(() => {
        deviceTableData.loading = false
        syncHealthError.value = '设备列表加载失败，同步状态暂无法确认，请稍后重试或检查服务。'
    })
}

// 下游同步健康度：仅"卡住"(stuck)的设备才显示「重新同步」。装了 ERP 才有数据；未装则全为健康、按钮不显示。
const syncHealthMap = ref<Record<number, any>>({})
const syncHealthLoading = ref(false)
const syncHealthError = ref('')
const syncHealthConfirmed = ref(false)
let syncHealthRequestId = 0
const syncHealthUnavailable = computed(() => deviceTableData.loading || syncHealthLoading.value || !!syncHealthError.value || !syncHealthConfirmed.value)
const resyncLoadingId = ref<number | null>(null)

const isSyncStatusUncertain = (row: any) => syncHealthUnavailable.value || !syncHealthMap.value[row.id] || Boolean(syncHealthMap.value[row.id]?.unknown)
const canSyncRows = (rows: any[]) => rows.length > 0 && !syncHealthUnavailable.value && rows.every(row => !isSyncStatusUncertain(row))
const ensureSyncStatusConfirmed = (rows: any[]) => {
    if (canSyncRows(rows)) return true
    hsxFeedback.warning(syncHealthError.value || '同步状态暂无法确认，请先刷新查询或检查服务；本次未执行同步。')
    return false
}

const isStuck = (row: any) => Boolean(syncHealthMap.value[row.id]?.stuck)
const stuckReason = (row: any) => syncHealthMap.value[row.id]?.reason || '该设备下游同步未完成'
const erpPlacementMissing = (row: any) => !Number(row.target_warehouse_id) || !Number(row.target_location_id)

type ErpWarehouse = { id: number; warehouse_name: string; is_default?: number; locations?: Array<{ id: number; location_name: string }> }
const erpWarehouses = ref<ErpWarehouse[]>([])
const placementDialogVisible = ref(false)
const placementSubmitting = ref(false)
const placementMode = ref<'single' | 'bulk'>('single')
const placementPendingRows = ref<any[]>([])
const placementSyncRows = ref<any[]>([])
const placementForm = reactive({ target_warehouse_id: 0, target_location_id: 0 })
const repairLocations = computed(() => erpWarehouses.value.find(item => Number(item.id) === Number(placementForm.target_warehouse_id))?.locations || [])
const defaultRepairPlacement = computed(() => {
    const warehouse = erpWarehouses.value.find(item => Number(item.is_default) === 1 && (item.locations || []).length)
        || erpWarehouses.value.find(item => (item.locations || []).length)
    const location = warehouse?.locations?.[0]
    return warehouse && location
        ? { target_warehouse_id: Number(warehouse.id), target_location_id: Number(location.id) }
        : null
})

const loadErpWarehouses = async () => {
    try {
        const res: any = await getSaleDestinationOptions()
        erpWarehouses.value = res?.data?.erp_connected ? (res?.data?.warehouses || []) : []
    } catch (e) {
        erpWarehouses.value = []
    }
}

const onRepairWarehouseChange = () => { placementForm.target_location_id = 0 }
const openPlacementRepair = (rows: any[], mode: 'single' | 'bulk', syncRows: any[] = rows) => {
    placementPendingRows.value = rows
    placementSyncRows.value = syncRows
    placementMode.value = mode
    placementForm.target_warehouse_id = 0
    placementForm.target_location_id = 0
    placementDialogVisible.value = true
}

const loadSyncHealth = async () => {
    const requestId = ++syncHealthRequestId
    syncHealthLoading.value = true
    syncHealthConfirmed.value = false
    syncHealthError.value = ''
    const ids = (deviceTableData.data || []).map((r: any) => r.id).filter(Boolean)
    if (!ids.length) {
        syncHealthMap.value = {}
        syncHealthConfirmed.value = true
        syncHealthLoading.value = false
        return
    }
    try {
        const res: any = await getDeviceSyncHealth(ids)
        if (requestId !== syncHealthRequestId) return
        if (!ids.every(id => res?.data?.[id] && typeof res.data[id] === 'object')) throw new Error('同步健康数据不完整')
        syncHealthMap.value = res?.data || {}
        syncHealthConfirmed.value = true
    } catch (e) {
        if (requestId !== syncHealthRequestId) return
        syncHealthMap.value = {}
        syncHealthError.value = '同步状态暂无法确认，请稍后重试或检查服务。状态确认前已暂停同步操作。'
    } finally {
        if (requestId === syncHealthRequestId) syncHealthLoading.value = false
    }
}

const handleResync = async (row: any) => {
    if (!ensureSyncStatusConfirmed([row])) return
    if (erpPlacementMissing(row) && !defaultRepairPlacement.value) {
        openPlacementRepair([row], 'single')
        return
    }
    resyncLoadingId.value = row.id
    try {
        const placement = erpPlacementMissing(row) ? (defaultRepairPlacement.value || {}) : {}
        const res: any = await resyncRecycleDevice(row.id, placement)
        const r = res?.data || {}
        if (r.has_asset === false) {
            hsxFeedback.warning('该设备尚未在 ERP 建立资产，已尝试重新入库同步，请稍候刷新查看')
        } else if ((r.flushed || 0) > 0) {
            hsxFeedback.success(`已重新投递 ${r.flushed} 条下游事件，中台拍照等步骤将补齐`)
        } else if ((r.still_failed || 0) > 0) {
            hsxFeedback.error(`仍有 ${r.still_failed} 条事件失败，请检查下游插件日志`)
        } else {
            hsxFeedback.success('已触发重新同步')
        }
        loadDeviceList()
    } catch (error: any) {
        hsxFeedback.error(error?.message || '重新同步失败')
    } finally {
        resyncLoadingId.value = null
    }
}

/**
 * 保存卖货价格
 */
const saveSellPrice = async (row: any) => {
    try {
        const newPrice = parseFloat(row.sell_price)
        if (isNaN(newPrice) || newPrice < 0) {
            hsxFeedback.warning('请输入有效的价格')
            return
        }

        await updateDevice(row.id, {
            sell_price: newPrice
        })

        hsxFeedback.success('卖货价格更新成功')
    } catch (error) {
        console.error('更新卖货价格失败:', error)
        hsxFeedback.error('更新失败，请重试')
    }
}

/**
 * 设备详情对话框
 */
const detailDialogVisible = ref(false)
const currentDevice = ref<any>(null)

const buyerCheckImages = computed(() => {
    if (!currentDevice.value?.check_images_buyer) return []
    try {
        const images = JSON.parse(currentDevice.value.check_images_buyer)
        return Array.isArray(images) ? images : []
    } catch {
        return []
    }
})

const viewDeviceDetail = (row: any) => {
    currentDevice.value = row
    detailDialogVisible.value = true
}

/**
 * 图片预览
 */
const imageViewerVisible = ref(false)
const previewImageList = ref<string[]>([])
const previewImageIndex = ref(0)

const previewImages = (images: string[], index: number) => {
    previewImageList.value = images.map(url => img(url))
    previewImageIndex.value = index
    imageViewerVisible.value = true
}

/**
 * 设备导出
 */
const exportSureDialog = ref(null)
const flag = ref(false)
const selectedDevices = ref<any[]>([])
const erpSyncLoading = ref(false)

const handleSelectionChange = (selection: any[]) => {
    selectedDevices.value = selection
}

const erpStatusMeta = (row: any) => {
    if (isSyncStatusUncertain(row)) return { label: '同步状态待确认', type: 'warning' as const }
    if (row.erp_sync?.inventory_status === 'in_stock') {
        return { label: '已入库', type: 'success' as const }
    }
    if (row.erp_sync?.inventory_status === 'pending_in') {
        return { label: '待入库', type: 'warning' as const }
    }
    if (row.erp_sync?.inventory_status === 'inbound_rejected') {
        return { label: '入库驳回', type: 'danger' as const }
    }
    return { label: '已同步', type: 'primary' as const }
}

const syncErpEvent = async () => {
    if (selectedDevices.value.length === 0) {
        hsxFeedback.warning('请先勾选需要同步的设备')
        return
    }
    if (!ensureSyncStatusConfirmed(selectedDevices.value)) return

    const syncCandidates = selectedDevices.value.filter((row: any) => !row.erp_sync)
    if (!syncCandidates.length) {
        hsxFeedback.info('所选设备均已同步到 ERP，无需重复同步')
        return
    }
    const missingRows = syncCandidates.filter(erpPlacementMissing)
    if (missingRows.length && !defaultRepairPlacement.value) {
        openPlacementRepair(missingRows, 'bulk', syncCandidates)
        return
    }

    const skippedCount = selectedDevices.value.length - syncCandidates.length
    const defaultHint = missingRows.length ? `；其中 ${missingRows.length} 台缺少位置，将自动入库到默认仓库和默认库位` : ''
    const confirmMessage = `确定同步 ${syncCandidates.length} 台设备到 ERP 吗${defaultHint}？${skippedCount ? ` 已自动跳过 ${skippedCount} 台已同步设备。` : ''}`

    try {
        await ElMessageBox.confirm(confirmMessage, '批量同步 ERP', {
            confirmButtonText: '确认同步',
            cancelButtonText: '取消',
            type: 'warning'
        })
        if (!ensureSyncStatusConfirmed(syncCandidates)) return
        erpSyncLoading.value = true
        const placement = missingRows.length ? (defaultRepairPlacement.value || {}) : {}
        const res: any = await syncRecycleDevicesToErp(syncCandidates.map((row: any) => row.id), ['self_erp'], placement)
        const result = (res?.data?.results || []).find((item: any) => item?.target === 'self_erp')
        const created = Number(result?.created_count || 0)
        const existing = Number(result?.existing_count || 0)
        hsxFeedback.success(`ERP 同步完成：新增 ${created} 台，已存在 ${existing} 台`)
        selectedDevices.value = []
        loadDeviceList()
    } catch (error: any) {
        if (error !== 'cancel' && error !== 'close') {
            hsxFeedback.error(error?.msg || error?.message || 'ERP 同步失败')
        }
    } finally {
        erpSyncLoading.value = false
    }
}

const submitPlacementRepair = async () => {
    if (!ensureSyncStatusConfirmed(placementMode.value === 'single' ? placementPendingRows.value : placementSyncRows.value)) return
    if (!placementForm.target_warehouse_id || !placementForm.target_location_id) {
        hsxFeedback.warning('请选择入库仓库和具体库位')
        return
    }
    const placement = { ...placementForm }
    placementSubmitting.value = true
    try {
        if (placementMode.value === 'single') {
            await resyncRecycleDevice(placementPendingRows.value[0].id, placement)
        } else {
            const selectedIds = placementSyncRows.value.map((row: any) => row.id)
            await syncRecycleDevicesToErp(selectedIds, ['self_erp'], placement)
            selectedDevices.value = []
        }
        hsxFeedback.success('入库位置已补全，ERP 同步已重新执行')
        placementDialogVisible.value = false
        loadDeviceList()
    } catch (error: any) {
        hsxFeedback.error(error?.msg || error?.message || '补全并同步失败')
    } finally {
        placementSubmitting.value = false
    }
}

/**
 * 导出参数：勾选了设备则传 device_ids，否则按搜索条件全量导出
 * 注意：update_at 需要经过 formatTimeRange 处理，加上 00:00:00 和 23:59:59，
 * 否则后端会把结束日期解析为当天 00:00:00，导致数据丢失
 */
const exportSearchParam = computed(() => {
    const base = {
        ...deviceTableData.searchParam,
        update_at: formatTimeRange(deviceTableData.searchParam.update_at)
    }
    if (selectedDevices.value.length > 0) {
        return {
            ...base,
            device_ids: selectedDevices.value.map((row: any) => row.id)
        }
    }
    return base
})

const handleClose = (val: boolean) => {
    flag.value = val
}
const exportEvent = () => {
    // 判断要导出的设备列表（选中的 or 当前页全部）
    const devicesToExport = selectedDevices.value.length > 0 ? selectedDevices.value : deviceTableData.data

    // 检查要导出的设备中是否包含已导出记录
    const hasExported = devicesToExport.some((row: any) => row.export_time && row.export_time > 0)
    if (hasExported) {
        ElMessageBox.confirm(
            '当前导出范围中包含已导出的设备记录，是否继续导出？',
            '提示',
            {
                confirmButtonText: '继续导出',
                cancelButtonText: '取消',
                type: 'warning'
            }
        ).then(() => {
            flag.value = true
        }).catch(() => {
            // 用户取消
        })
    } else {
        flag.value = true
    }
}

/**
 * 时间戳转日期字符串
 */
const formatTimestamp = (timestamp: number): string => {
    if (!timestamp || timestamp <= 0) return ''
    const date = new Date(timestamp * 1000)
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, '0')
    const d = String(date.getDate()).padStart(2, '0')
    const h = String(date.getHours()).padStart(2, '0')
    const min = String(date.getMinutes()).padStart(2, '0')
    const s = String(date.getSeconds()).padStart(2, '0')
    return `${y}-${m}-${d} ${h}:${min}:${s}`
}

/**
 * 已导出行灰色样式
 */
const tableRowClassName = ({ row }: { row: any }) => {
    if (row.export_time && row.export_time > 0) {
        return 'exported-row'
    }
    return ''
}

// 初始化加载，默认回收时间为最近 7 天（含今天）
setDefaultDateRange()
loadErpWarehouses()
loadDeviceList()
</script>

<style lang="scss" scoped>
:deep(.exported-row) {
    background-color: #f5f5f5 !important;
    color: #999;
}
:deep(.exported-row td) {
    background-color: #f5f5f5 !important;
}
</style>
