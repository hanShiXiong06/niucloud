<template>
    <!--回收设备导出-->
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center">
                <span class="text-page-title">{{ pageName }}</span>
            </div>

            <el-card class="box-card !border-none my-[20px] table-search-wrap" shadow="never">
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

                    <el-form-item :label="t('回收时间')" prop="update_at">
                        <el-date-picker
                            v-model="deviceTableData.searchParam.update_at"
                            type="daterange"
                            value-format="YYYY-MM-DD"
                            :start-placeholder="t('startDate')"
                            :end-placeholder="t('endDate')"
                            format="YYYY-MM-DD"
                        />
                    </el-form-item>

                    <el-form-item>
                        <el-button type="primary" @click="loadDeviceList()">{{ t('search') }}</el-button>
                        <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
                        <el-button type="primary" @click="exportEvent" :disabled="deviceTableData.total === 0">{{ t('export') }}</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">
                <el-table :data="deviceTableData.data" size="large" v-loading="deviceTableData.loading">
                    <template #empty>
                        <span>{{ !deviceTableData.loading ? t('emptyData') : '' }}</span>
                    </template>

                    <el-table-column prop="imei" :label="t('imei')" min-width="120" />
                    <el-table-column prop="model" :label="t('型号')" min-width="150" show-overflow-tooltip />
                    <el-table-column prop="category_name" :label="t('分类')" min-width="100" align="center" />

                    <el-table-column prop="final_price" label="收货价" min-width="100" align="right">
                        <template #default="{ row }">
                            ¥{{ row.final_price || '0.00' }}
                        </template>
                    </el-table-column>

                    <el-table-column prop="sell_price" label="卖货价格" min-width="120" align="right">
                        <template #default="{ row }">
                            <el-input
                                v-model="row.sell_price"
                                size="small"
                                type="number"
                                :min="0"
                                placeholder="0.00"
                                style="width: 100px"
                                @blur="saveSellPrice(row)"
                                @keyup.enter="saveSellPrice(row)"
                            >
                                <template #prefix>¥</template>
                            </el-input>
                        </template>
                    </el-table-column>

                    <el-table-column label="供货商" min-width="120" align="center">
                        <template #default="{ row }">
                            <div v-if="row.order?.member" class="flex items-center gap-2">
                                <el-avatar :size="24" :src="img(row.order.member.headimg)" v-if="row.order.member.headimg">
                                    <el-icon><User /></el-icon>
                                </el-avatar>
                                <span>{{ row.order.member.nickname || row.order.member.username || '未知' }}</span>
                            </div>
                            <span v-else class="text-gray-400">-</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="报价人" min-width="100" align="center">
                        <template #default="{ row }">
                            <span v-if="row.price_user">{{ row.priceUser.real_name || row.priceUser.username || '未知' }}</span>
                            <span v-else class="text-gray-400">-</span>
                        </template>
                    </el-table-column>

                    <el-table-column prop="status_name" :label="t('status')" min-width="100" align="center">
                        <template #default="{ row }">
                            <el-tag type="success">{{ row.status_name }}</el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('回收时间')" min-width="150" align="center">
                        <template #default="{ row }">
                            {{ row.update_at || '' }}
                        </template>
                    </el-table-column>

                    <el-table-column label="操作" min-width="100" align="center" fixed="right">
                        <template #default="{ row }">
                            <el-button
                                type="primary"
                                link
                                :icon="View"
                                @click="viewDeviceDetail(row)"
                            >
                                查看详情
                            </el-button>
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

            <export-sure ref="exportSureDialog" :show="flag" type="recycle_device" :searchParam="deviceTableData.searchParam" @close="handleClose" />
        </el-card>

        <!-- 设备详情对话框 -->
        <el-dialog
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
        </el-dialog>

        <!-- 图片预览 -->
        <el-image-viewer
            v-if="imageViewerVisible"
            :url-list="previewImageList"
            :initial-index="previewImageIndex"
            @close="imageViewerVisible = false"
        />
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, computed } from 'vue'
import { t } from '@/lang'
import { FormInstance, ElMessage, ElImageViewer } from 'element-plus'
import { useRoute } from 'vue-router'
import { getRecycleDeviceList, updateDevice } from '@/addon/recycle/api/device_export'
import { img } from '@/utils/common'
import { View, User, Picture } from '@element-plus/icons-vue'

const route = useRoute()
const pageName = route.meta.title

const deviceTableData = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam: {
        imei: '',
        model: '',
        category_id: '',
        update_at: [],
        status: 5  // 固定为已回收状态
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

const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
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
    }).catch(() => {
        deviceTableData.loading = false
    })
}

/**
 * 保存卖货价格
 */
const saveSellPrice = async (row: any) => {
    try {
        const newPrice = parseFloat(row.sell_price)
        if (isNaN(newPrice) || newPrice < 0) {
            ElMessage.warning('请输入有效的价格')
            return
        }

        await updateDevice(row.id, {
            sell_price: newPrice
        })

        ElMessage.success('卖货价格更新成功')
    } catch (error) {
        console.error('更新卖货价格失败:', error)
        ElMessage.error('更新失败，请重试')
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
const handleClose = (val: boolean) => {
    flag.value = val
}
const exportEvent = () => {
    flag.value = true
}

// 初始化加载
loadDeviceList()
</script>

<style lang="scss" scoped></style>
