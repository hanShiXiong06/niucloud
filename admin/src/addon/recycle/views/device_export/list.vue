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

                    <el-table-column prop="final_price" :label="t('定价')" min-width="100" align="right">
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

                    <el-table-column :label="t('订单编号')" min-width="180" show-overflow-tooltip>
                        <template #default="{ row }">
                            {{ row.order?.order_no || '' }}
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
            width="800px"
            :destroy-on-close="true"
        >
            <div v-if="currentDevice" class="space-y-4">
                <!-- 基本信息 -->
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="设备型号">{{ currentDevice.model }}</el-descriptions-item>
                    <el-descriptions-item label="IMEI">{{ currentDevice.imei }}</el-descriptions-item>
                    <el-descriptions-item label="分类">{{ currentDevice.category_name }}</el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag type="success">{{ currentDevice.status_name }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="定价">¥{{ currentDevice.final_price || '0.00' }}</el-descriptions-item>
                    <el-descriptions-item label="卖货价格">¥{{ currentDevice.sell_price || '0.00' }}</el-descriptions-item>
                    <el-descriptions-item label="回收时间" :span="2">{{ currentDevice.update_at }}</el-descriptions-item>
                </el-descriptions>

                <!-- 供货商信息 -->
                <div v-if="currentDevice.order?.member" class="bg-gray-50 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">供货商信息</h4>
                    <div class="flex items-center gap-4">
                        <el-avatar :size="60" :src="img(currentDevice.order.member.headimg)" v-if="currentDevice.order.member.headimg">
                            <el-icon><User /></el-icon>
                        </el-avatar>
                        <div>
                            <div class="font-medium">{{ currentDevice.order.member.nickname || currentDevice.order.member.username || '未知用户' }}</div>
                            <div class="text-sm text-gray-500">{{ currentDevice.order.member.mobile || '-' }}</div>
                        </div>
                    </div>
                </div>

                <!-- 买家质检结果 -->
                <div v-if="currentDevice.check_result_buyer" class="bg-green-50 rounded-lg p-4 border border-green-200">
                    <h4 class="text-sm font-medium text-green-800 mb-2">买家质检结果</h4>
                    <div class="text-sm text-green-700 leading-relaxed whitespace-pre-line">
                        {{ currentDevice.check_result_buyer }}
                    </div>
                </div>

                <!-- 买家质检图片 -->
                <div v-if="buyerCheckImages.length > 0" class="space-y-2">
                    <h4 class="text-sm font-medium text-gray-800">买家质检图片</h4>
                    <div class="grid grid-cols-4 gap-3">
                        <div
                            v-for="(imgUrl, index) in buyerCheckImages"
                            :key="index"
                            class="relative group cursor-pointer"
                            @click="previewImages(buyerCheckImages, index)"
                        >
                            <el-image
                                :src="img(imgUrl)"
                                fit="cover"
                                class="w-full h-24 rounded border-2 border-gray-200 group-hover:border-green-400 transition-colors"
                                lazy
                            >
                                <template #error>
                                    <div class="w-full h-24 bg-gray-100 rounded flex items-center justify-center">
                                        <el-icon class="text-gray-400"><Picture /></el-icon>
                                    </div>
                                </template>
                            </el-image>
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
