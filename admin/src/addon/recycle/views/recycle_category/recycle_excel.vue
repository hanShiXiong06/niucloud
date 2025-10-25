<template>
    <div class="min-h-screen bg-gray-50">
     <!-- 页面头部 -->
     <div class="bg-white border-b border-gray-200 px-6 py-4 mb-6">
     <div class="flex items-center justify-between">
    <div>
    <h1 class="text-2xl font-bold text-gray-900">Excel数据管理</h1>
    <p class="text-sm text-gray-600 mt-1">查看和管理Excel导入的设备价格数据</p>
    </div>
    <div class="flex space-x-3">
    <el-button type="primary" @click="handleImport">
     <el-icon><Upload /></el-icon>
     导入数据
    </el-button>
    <el-button type="success" @click="handleExport">
     <el-icon><Download /></el-icon>
     导出数据
    </el-button>
    <el-button type="success" @click="clearCache">
     <el-icon><Clear /></el-icon>
     清理缓存
    </el-button>
    <!-- 下载为excel图片 -->
    <el-button type="success" @click="downloadExcel">
     <el-icon><Download /></el-icon>
     存为图片
    </el-button>
    </div>
     </div>
     </div>
    
     <!-- 统计卡片 -->
     <div class="px-6 mb-6">
     <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <div class="bg-white rounded-lg shadow p-6">
    <div class="flex items-center">
     <div class="flex-shrink-0">
     <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
      <el-icon class="text-white"><Iphone /></el-icon>
     </div>
     </div>
     <div class="ml-4">
     <p class="text-sm font-medium text-gray-600">设备总数</p>
     <p class="text-2xl font-bold text-gray-900">{{ statistics.total_devices || 0 }}</p>
     </div>
    </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
    <div class="flex items-center">
     <div class="flex-shrink-0">
     <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
      <el-icon class="text-white"><PriceTag /></el-icon>
     </div>
     </div>
     <div class="ml-4">
     <p class="text-sm font-medium text-gray-600">价格记录</p>
     <p class="text-2xl font-bold text-gray-900">{{ statistics.total_prices || 0 }}</p>
     </div>
    </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
    <div class="flex items-center">
     <div class="flex-shrink-0">
     <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
      <el-icon class="text-white"><CollectionTag /></el-icon>
     </div>
     </div>
     <div class="ml-4">
     <p class="text-sm font-medium text-gray-600">品牌数量</p>
     <p class="text-2xl font-bold text-gray-900">{{ statistics.total_brands || 0 }}</p>
     </div>
    </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
    <div class="flex items-center">
     <div class="flex-shrink-0">
     <div class="w-8 h-8 bg-orange-500 rounded-md flex items-center justify-center">
      <el-icon class="text-white"><Document /></el-icon>
     </div>
     </div>
     <div class="ml-4">
     <p class="text-sm font-medium text-gray-600">导入记录</p>
     <p class="text-2xl font-bold text-gray-900">{{ statistics.import_records || 0 }}</p>
     </div>
    </div>
    </div>
     </div>
     </div>
    
     <!-- 筛选和搜索 -->
     <div class="bg-white mx-6 mb-6 p-6 rounded-lg shadow">
     <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
    <div>
    <label class="block text-sm font-medium text-gray-700 mb-2">设备类型</label>
    <el-select
     v-model="searchForm.device_type"
     placeholder="选择类型"
     clearable
     class="w-full"
    >
     <el-option label="全部类型" value="" />
     <el-option label="手机" value="phone" />
     <el-option label="平板" value="tablet" />
     <el-option label="手表" value="watch" />
    </el-select>
    </div>
    <div>
    <label class="block text-sm font-medium text-gray-700 mb-2">品牌筛选</label>
    <el-select v-model="searchForm.brand_id" placeholder="选择品牌" clearable class="w-full">
     <el-option label="全部品牌" value="" />
     <el-option
      v-for="brand in brandList"
      :key="brand.id"
      :label="brand.brand_name"
      :value="brand.id"
     />
    </el-select>
    </div>
    
    <div>
    <label class="block text-sm font-medium text-gray-700 mb-2">关键词搜索</label>
    <el-input
     v-model="searchForm.keyword"
     placeholder="搜索型号、网络型号、容量"
     clearable
     class="w-full"
    />
    </div>
    <!--  加入时间筛选 -->
    <div>
    <label class="block text-sm font-medium text-gray-700 mb-2">报价时间</label>
    
    <el-date-picker
     v-model="searchForm.create_at"
     value-format="YYYY-MM-DD HH:mm:ss"
     :start-placeholder="'startDate'"
     :end-placeholder="'endDate'"
     format="YYYY-MM-DD HH:mm"
     date-format="YYYY/MM/DD ddd"
     time-format="A hh:mm"
    />
    </div>
    
    <div class="flex items-end">
    <el-button type="primary" @click="searchDevices" class="w-full">搜索</el-button>
    </div>
     </div>
     </div>
    
     <!-- 设备列表 -->
     <div class="bg-white mx-6 mb-6 rounded-lg shadow">
     <div class="px-6 py-4 border-b border-gray-200">
    <div class="flex items-center justify-between">
    <h3 class="text-lg font-medium text-gray-900">设备列表</h3>
    <div class="flex space-x-2">
     <el-button size="small" @click="handleRefresh">刷新</el-button>
     <el-button
     size="small"
     type="danger"
     :disabled="selectedDevices.length === 0"
     @click="handleBatchDelete"
     >
     批量删除 ({{ selectedDevices.length }})
     </el-button>
    </div>
    </div>
     </div>
    
     <div class="overflow-x-auto">
    <el-table
    :data="deviceList"
    v-loading="loading"
    @selection-change="handleSelectionChange"
    :span-method="objectSpanMethod"
    border
    class="w-full"
    >
    <el-table-column type="selection" width="55" align="center" />
    
    <el-table-column label="型号" min-width="180" align="center">
     <template #default="{ row }">
     <div class="text-center">{{ row.model_name }}</div>
     </template>
    </el-table-column>
    
    <el-table-column label="网络型号" min-width="120" align="center">
     <template #default="{ row }">
     <div class="text-center">{{ row.network_model }}</div>
     </template>
    </el-table-column>
    
    <el-table-column label="容量" min-width="150" align="center">
     <template #default="{ row }">
     <div class="text-center">{{ row.capacity }}</div>
     </template>
    </el-table-column>
    
    <el-table-column label="高保充新" min-width="100" align="center">
     <template #default="{ row }">
     <div
      class="text-center font-medium"
      :class="
      row.currentPrice?.price_data?.['高保充新'] ? 'text-red-600' : 'text-gray-400'
      "
     >
      {{ row.currentPrice?.price_data?.['高保充新'] || '-' }}
     </div>
     </template>
    </el-table-column>
    
    <el-table-column label="充新" min-width="100" align="center">
     <template #default="{ row }">
     <div
      class="text-center font-medium"
      :class="
      row.currentPrice?.price_data?.['充新'] ? 'text-orange-600' : 'text-gray-400'
      "
     >
      {{ row.currentPrice?.price_data?.['充新'] || '-' }}
     </div>
     </template>
    </el-table-column>
    
    <el-table-column label="靓机" min-width="100" align="center">
     <template #default="{ row }">
     <div
      class="text-center font-medium"
      :class="row.currentPrice?.price_data?.['靓机'] ? 'text-green-600' : 'text-gray-400'"
     >
      {{ row.currentPrice?.price_data?.['靓机'] || '-' }}
     </div>
     </template>
    </el-table-column>
    
    <el-table-column label="小花" min-width="100" align="center">
     <template #default="{ row }">
     <div
      class="text-center font-medium"
      :class="row.currentPrice?.price_data?.['小花'] ? 'text-blue-600' : 'text-gray-400'"
     >
      {{ row.currentPrice?.price_data?.['小花'] || '-' }}
     </div>
     </template>
    </el-table-column>
    
    <el-table-column label="大花" min-width="100" align="center">
     <template #default="{ row }">
     <div
      class="text-center font-medium"
      :class="
      row.currentPrice?.price_data?.['大花'] ? 'text-purple-600' : 'text-gray-400'
      "
     >
      {{ row.currentPrice?.price_data?.['大花'] || '-' }}
     </div>
     </template>
    </el-table-column>
    
    <el-table-column label="外爆" min-width="100" align="center">
     <template #default="{ row }">
     <div
      class="text-center font-medium"
      :class="
      row.currentPrice?.price_data?.['外壳'] ? 'text-yellow-600' : 'text-gray-400'
      "
     >
      {{ row.currentPrice?.price_data?.['外爆'] || '-' }}
     </div>
     </template>
    </el-table-column>
    
    <el-table-column label="内爆" min-width="100" align="center">
     <template #default="{ row }">
     <div
      class="text-center font-medium"
      :class="
      row.currentPrice?.price_data?.['内爆'] ? 'text-indigo-600' : 'text-gray-400'
      "
     >
      {{ row.currentPrice?.price_data?.['内爆'] || '-' }}
     </div>
     </template>
    </el-table-column>
    
    <el-table-column label="备注" min-width="120" align="center">
     <template #default="{ row }">
     <div class="text-center text-gray-500">{{ row.remark || '-' }}</div>
     </template>
    </el-table-column>
    
    <el-table-column label="操作" width="120" fixed="right" align="center">
     <template #default="{ row }">
     <div class="flex justify-center space-x-2">
      <el-button size="small" type="primary" plain @click="handleEditPrice(row)">
      编辑
      </el-button>
     </div>
     </template>
    </el-table-column>
    </el-table>
     </div>
     </div>
    
     <!-- 设备详情弹窗 -->
     <el-dialog v-model="detailVisible" title="设备详情" width="800px">
     <div v-if="currentDevice" class="space-y-6">
    <!-- 基本信息 -->
    <div>
    <h4 class="text-lg font-medium mb-3">基本信息</h4>
    <div class="grid grid-cols-2 gap-4">
     <div>
     <label class="block text-sm font-medium text-gray-700">设备型号</label>
     <p class="mt-1">{{ currentDevice.model_name }}</p>
     </div>
     <div>
     <label class="block text-sm font-medium text-gray-700">网络型号</label>
     <p class="mt-1">{{ currentDevice.network_model }}</p>
     </div>
     <div>
     <label class="block text-sm font-medium text-gray-700">容量</label>
     <p class="mt-1">{{ currentDevice.capacity }}</p>
     </div>
     <div>
     <label class="block text-sm font-medium text-gray-700">设备类型</label>
     <p class="mt-1">{{ getDeviceTypeName(currentDevice.device_type) }}</p>
     </div>
    </div>
    </div>
    
    <!-- 价格信息 -->
    <div>
    <h4 class="text-lg font-medium mb-3">价格信息</h4>
    <div class="grid grid-cols-3 gap-4">
     <div
     v-for="(price, type) in currentDevice.price_detail"
     :key="type"
     class="bg-gray-50 p-3 rounded"
     >
     <label class="block text-sm font-medium text-gray-700">{{ type }}</label>
     <p class="text-lg font-bold text-red-600">¥{{ price }}</p>
     </div>
    </div>
    </div>
     </div>
     </el-dialog>
    
     <!-- 编辑价格弹窗 -->
     <el-dialog v-model="editPriceVisible" title="编辑价格" width="600px">
     <div v-if="currentDevice">
    <el-form :model="priceForm" label-width="100px">
    <div class="mb-4">
     <h4 class="text-lg font-medium">
     {{ currentDevice.model_name }} - {{ currentDevice.capacity }}
     </h4>
    </div>
    
    <el-form-item v-for="(value, type) in priceForm.price_data" :key="type" :label="type">
     <el-input-number
     v-model="priceForm.price_data[type]"
     :min="0"
     :precision="0"
     controls-position="right"
     class="w-full"
     />
    </el-form-item>
    </el-form>
    
    <div class="flex justify-end space-x-3 mt-6">
    <el-button @click="editPriceVisible = false">取消</el-button>
    <el-button type="primary" @click="handleSavePrice" :loading="saveLoading">保存</el-button>
    </div>
     </div>
     </el-dialog>
    
     <!-- Excel上传组件 -->
     <ExcelUpload v-model="uploadVisible" @success="handleImportSuccess" />
    </div>
    </template>
    
    <script setup lang="ts">
    import { clearCache } from '@/app/api/sys'
    import request from '@/utils/request'
    import {
    CollectionTag,
    Document,
    Download,
    Iphone,
    PriceTag,
    Upload
    } from '@element-plus/icons-vue'
    import { ElMessage, ElMessageBox } from 'element-plus'
    import { onMounted, reactive, ref, watch } from 'vue'
    import ExcelUpload from '../../components/excel-upload/ExcelUpload.vue'
    
    // 定义接口类型
    interface Device {
    id: number
    model_name: string
    network_model: string
    capacity: string
    device_type: string
    brand: {
     brand_name: string
     brand_code: string
    }
    price_detail: Record<string, number>
    price?: {
     is_current: boolean
    }
    create_at: string
    }
    
    interface Brand {
    id: number
    brand_name: string
    brand_code: string
    }
    
    interface Statistics {
    total_devices?: number
    total_prices?: number
    total_brands?: number
    import_records?: number
    }
    
    // 响应式数据
    const loading = ref(false)
    const deviceList = ref<Device[]>([])
    const brandList = ref<Brand[]>([])
    const selectedDevices = ref<Device[]>([])
    const statistics = ref<Statistics>({})
    const detailVisible = ref(false)
    const editPriceVisible = ref(false)
    const currentDevice = ref<Device | null>(null)
    const saveLoading = ref(false)
    const uploadVisible = ref(false)
    
    // 搜索表单
    const searchForm = reactive({
    brand_id: '',
    device_type: '',
    keyword: ''
    })
    
    // 分页数据
    const pagination = reactive({
    page: 1,
    limit: 20,
    total: 0
    })
    
    // 价格编辑表单
    const priceForm = reactive({
    device_id: 0,
    price_data: {}
    })
    
    // 类型定义
    interface ApiResponse<T = any> {
    code: number
    msg?: string
    data: T
    }
    
    // 工具函数
    interface SpanMethodProps {
    row: Device
    column: { property: string }
    rowIndex: number
    columnIndex: number
    }
    
    const objectSpanMethod = ({ row, column, rowIndex, columnIndex }: SpanMethodProps) => {
    // 型号列
    if (columnIndex === 1) {
     if (
     rowIndex > 0 &&
     deviceList.value[rowIndex].model_name === deviceList.value[rowIndex - 1].model_name
     ) {
     return {
    rowspan: 0,
    colspan: 0
     }
     } else {
     let count = 1
     for (let i = rowIndex + 1; i < deviceList.value.length; i++) {
    if (deviceList.value[i].model_name === row.model_name) {
    count++
    } else {
    break
    }
     }
     return {
    rowspan: count,
    colspan: 1
     }
     }
    }
    // 容量列
    if (columnIndex === 3) {
     if (
     rowIndex > 0 &&
     deviceList.value[rowIndex].model_name === deviceList.value[rowIndex - 1].model_name &&
     deviceList.value[rowIndex].capacity === deviceList.value[rowIndex - 1].capacity
     ) {
     return {
    rowspan: 0,
    colspan: 0
     }
     } else {
     let count = 1
     for (let i = rowIndex + 1; i < deviceList.value.length; i++) {
    if (
    deviceList.value[i].model_name === row.model_name &&
    deviceList.value[i].capacity === row.capacity
    ) {
    count++
    } else {
    break
    }
     }
     return {
    rowspan: count,
    colspan: 1
     }
     }
    }
    
    // 网络型号列
    if (columnIndex === 2) {
     if (
     rowIndex > 0 &&
     deviceList.value[rowIndex].model_name === deviceList.value[rowIndex - 1].model_name &&
     deviceList.value[rowIndex].network_model === deviceList.value[rowIndex - 1].network_model
     ) {
     return {
    rowspan: 0,
    colspan: 0
     }
     } else {
     let count = 1
     for (let i = rowIndex + 1; i < deviceList.value.length; i++) {
    if (
    deviceList.value[i].model_name === row.model_name &&
    deviceList.value[i].network_model === row.network_model
    ) {
    count++
    } else {
    break
    }
     }
     return {
    rowspan: count,
    colspan: 1
     }
     }
    }
    return {
     rowspan: 1,
     colspan: 1
    }
    }
    
    type PriceType = '高保充新' | '充新' | '靓机'
    
    const getPriceCardClass = (type: PriceType | string) => {
    const classMap: Record<PriceType, string> = {
     高保充新: 'bg-red-50',
     充新: 'bg-orange-50',
     靓机: 'bg-green-50'
    }
    return classMap[type as PriceType] || 'bg-gray-50'
    }
    
    const getPriceTextClass = (type: PriceType | string) => {
    const classMap: Record<PriceType, string> = {
     高保充新: 'text-red-600',
     充新: 'text-orange-600',
     靓机: 'text-green-600'
    }
    return classMap[type as PriceType] || 'text-gray-600'
    }
    
    // API 函数
    const getDeviceList = async (params: any): Promise<ApiResponse> => {
    return await request.get('recycle/excel/lists', { params })
    }
    
    const getBrandList = async (): Promise<ApiResponse> => {
    return await request.get('recycle/excel/brands')
    }
    
    const getStatistics = async (): Promise<ApiResponse> => {
    return await request.get('recycle/excel/statistics')
    }
    
    const updatePrice = async (params: any): Promise<ApiResponse> => {
    return await request.put('recycle/excel/price', params)
    }
    
    const batchDelete = async (params: any): Promise<ApiResponse> => {
    return await request.delete('recycle/excel/batch', params)
    }
    
    // 下载为图片
    const downloadExcel = async () => {
    return await request.post('recycle/price_image/generate')
    }
    // check_environment
    const checkEnvironment = async () => {
    return await request.get('recycle/price_image/test_mapping')
    }

    checkEnvironment()
    
    // 生命周期
    onMounted(() => {
    loadDeviceList()
    loadBrandList()
    loadStatistics()
    })
    
    // 监听搜索条件变化
    watch([() => searchForm.brand_id, () => searchForm.device_type], () => {
    pagination.page = 1
    loadDeviceList()
    })
    
    // 加载设备列表
    const loadDeviceList = async () => {
    try {
     loading.value = true
     const params = {
     page: pagination.page,
     limit: pagination.limit,
     ...searchForm
     }
    
     const response = await getDeviceList(params)
     if (response.code === 1) {
     deviceList.value = response.data || []
     }
    } catch (error) {
     ElMessage.error('加载设备列表失败')
     console.error(error)
    } finally {
     loading.value = false
    }
    }
    
    // 加载品牌列表
    const loadBrandList = async () => {
    try {
     const response = await getBrandList()
     if (response.code === 1) {
     brandList.value = response.data || []
     }
    } catch (error) {
     console.error('加载品牌列表失败:', error)
    }
    }
    
    // 加载统计信息
    const loadStatistics = async () => {
    try {
     const response = await getStatistics()
     if (response.code === 1) {
     statistics.value = response.data || {}
     }
    } catch (error) {
     console.error('加载统计信息失败:', error)
    }
    }
    
    // 搜索设备
    const searchDevices = () => {
    pagination.page = 1
    loadDeviceList()
    }
    
    // 刷新数据
    const handleRefresh = () => {
    loadDeviceList()
    loadStatistics()
    }
    
    // 选择变化
    const handleSelectionChange = (selection: any[]) => {
    selectedDevices.value = selection
    }
    
    // 查看详情
    const handleViewDetail = (device: any) => {
    currentDevice.value = device
    detailVisible.value = true
    }
    
    // 编辑价格
    const handleEditPrice = (device: any) => {
    currentDevice.value = device
    priceForm.device_id = device.id
    priceForm.price_data = { ...device.price_detail }
    editPriceVisible.value = true
    }
    
    // 保存价格
    const handleSavePrice = async () => {
    try {
     saveLoading.value = true
     const response = await updatePrice(priceForm)
     if (response.code === 1) {
     ElMessage.success('价格更新成功')
     editPriceVisible.value = false
     loadDeviceList()
     } else {
     ElMessage.error(response.msg || '价格更新失败')
     }
    } catch (error) {
     ElMessage.error('价格更新失败')
     console.error(error)
    } finally {
     saveLoading.value = false
    }
    }
    
    // 批量删除
    const handleBatchDelete = async () => {
    if (selectedDevices.value.length === 0) {
     ElMessage.warning('请选择要删除的设备')
     return
    }
    
    try {
     await ElMessageBox.confirm(
     `确定要删除选中的 ${selectedDevices.value.length} 个设备吗？此操作不可恢复。`,
     '确认删除',
     {
    confirmButtonText: '确定删除',
    cancelButtonText: '取消',
    type: 'warning'
     }
     )
    
     const ids = selectedDevices.value.map((item: any) => item.id)
     const response = await batchDelete({ ids })
    
     if (response.code === 1) {
     ElMessage.success(response.msg || '批量删除成功')
     loadDeviceList()
     loadStatistics()
     } else {
     ElMessage.error(response.msg || '批量删除失败')
     }
    } catch (error) {
     if (error !== 'cancel') {
     ElMessage.error('批量删除失败')
     console.error(error)
     }
    }
    }
    
    // 分页变化
    const handleSizeChange = (size: number) => {
    pagination.limit = size
    pagination.page = 1
    loadDeviceList()
    }
    
    const handleCurrentChange = (page: number) => {
    pagination.page = page
    loadDeviceList()
    }
    
    // 导入和导出
    const handleImport = () => {
    uploadVisible.value = true
    }
    
    const handleImportSuccess = () => {
    // 导入成功后刷新数据
    loadDeviceList()
    loadStatistics()
    ElMessage.success('数据导入成功')
    }
    
    const handleExport = async () => {
    try {
     // 导出当前列表数据
     const params = {
     ...searchForm
     }
     const response = await request.get('recycle/excel/export', {
     params,
     responseType: 'blob'
     })
    
     // 创建下载链接
     const blob = new Blob([response.data])
     const url = window.URL.createObjectURL(blob)
     const link = document.createElement('a')
     link.href = url
     link.download = `设备价格数据_${new Date().toISOString().slice(0, 10)}.xlsx`
     link.click()
     window.URL.revokeObjectURL(url)
    
     ElMessage.success('数据导出成功')
    } catch (error) {
     ElMessage.error('数据导出失败')
     console.error(error)
    }
    }
    
    // 工具函数
    const getBrandTagType = (brandCode: string) => {
    const typeMap: { [key: string]: string } = {
     apple: 'primary',
     huawei: 'success',
     samsung: 'warning',
     xiaomi: 'danger',
     oppo: 'info'
    }
    return typeMap[brandCode] || ''
    }
    
    const getDeviceTypeTag = (type: string) => {
    const tagMap: { [key: string]: string } = {
     phone: 'primary',
     tablet: 'success',
     watch: 'warning'
    }
    return tagMap[type] || ''
    }
    
    const getDeviceTypeName = (type: string) => {
    const nameMap: { [key: string]: string } = {
     phone: '手机',
     tablet: '平板',
     watch: '手表'
    }
    return nameMap[type] || type
    }
    </script>
    
    <style scoped>
    /* Tailwind CSS已包含所需样式 */
    </style>
    
    