<template>
    <view class="p-5 bg-gray-100 min-h-screen">
        <!-- 页面头部 -->
        <view class="bg-gradient-to-br from-indigo-500 to-purple-600 px-8 py-10 mb-5 rounded-3xl text-white text-center shadow-xl">
            <view class="text-2xl font-bold mb-2">设备报价生成器</view>
            <view class="text-sm opacity-90">选择设备类型和品牌，生成专属报价图片</view>
        </view>

        <!-- 筛选区域 -->
        <view class="bg-white p-8 mb-5 rounded-3xl shadow-lg">
            <!-- 设备类型选择 -->
            <view class="mb-10">
                <view class="text-xl font-bold text-gray-800 mb-5">设备类型</view>
                <view class="device-type-grid">
                    <view 
                        v-for="type in deviceTypes" 
                        :key="type.value"
                        class="flex-1 p-6 bg-gray-50 rounded-2xl text-center border-2 border-transparent transition-all duration-200"
                        :class="{ 
                            'active bg-blue-50 border-blue-400 shadow-md': filterForm.device_type === type.value 
                        }"
                        @click="selectDeviceType(type.value)"
                    >
                        <view class="text-3xl mb-2">{{ type.icon }}</view>
                        <view class="text-base font-bold text-gray-800 mb-1">{{ type.name }}</view>
                        <view class="text-sm text-gray-600" v-if="statistics.device_type_distribution">
                            {{ getDeviceTypeCount(type.value) }}台
                        </view>
                    </view>
                </view>
            </view>

            <!-- 品牌选择 -->
            <view class="mb-10">
                <view class="text-xl font-bold text-gray-800 mb-5">选择品牌</view>
                <view class="brand-grid">
                    <view 
                        v-for="brand in brandList" 
                        :key="brand.id"
                        class="p-6 bg-gray-50 rounded-2xl text-center border-2 border-transparent transition-all duration-200"
                        :class="{ 
                            'active bg-orange-50 border-orange-400 shadow-md': filterForm.brand_id === brand.id 
                        }"
                        @click="selectBrand(brand.id)"
                    >
                        <view class="w-16 h-16 mx-auto mb-3 rounded-2xl bg-white flex items-center justify-center shadow-sm">
                            <image 
                                :src="getBrandLogo(brand.brand_code)" 
                                class="w-12 h-12"
                                mode="aspectFit"
                            />
                        </view>
                        <view class="text-base font-bold text-gray-800 mb-1">{{ brand.brand_name }}</view>
                        <view class="text-sm text-gray-600">{{ brand.device_models_count || 0 }}款</view>
                    </view>
                </view>
            </view>

            <!-- 关键词搜索 -->
            <view>
                <view class="text-xl font-bold text-gray-800 mb-5">关键词搜索（可选）</view>
                <input 
                    v-model="filterForm.keyword"
                    class="search-input w-full p-6 bg-gray-50 rounded-2xl text-base"
                    placeholder="搜索型号、网络型号、容量"
                    @input="onKeywordInput"
                />
            </view>
        </view>

        <!-- 数据预览区域 -->
        <view class="bg-white p-8 mb-5 rounded-3xl shadow-lg" v-if="showPreview">
            <view class="flex justify-between items-center mb-5">
                <view class="text-xl font-bold text-gray-800">数据预览</view>
                <view class="text-base text-gray-600">共 {{ deviceList.length }} 个设备</view>
            </view>
            
            <view>
                <scroll-view scroll-y class="h-96">
                    <view 
                        v-for="device in deviceList.slice(0, 10)" 
                        :key="device.id"
                        class="flex justify-between items-center p-5 bg-gray-50 rounded-xl mb-4"
                    >
                        <view class="flex-1">
                            <view class="text-base font-bold text-gray-800 mb-1">{{ device.model_name }}</view>
                            <view class="text-sm text-gray-600">
                                {{ device.network_model }} · {{ device.capacity }}
                            </view>
                        </view>
                        <view class="text-right">
                            <view class="text-xs text-gray-600 mb-1">高保充新</view>
                            <view class="text-base font-bold text-red-500">
                                ¥{{ device.currentPrice?.price_data?.['高保充新'] || 0 }}
                            </view>
                        </view>
                    </view>
                    
                    <view v-if="deviceList.length > 10" class="text-center p-5 text-gray-600 text-base">
                        还有 {{ deviceList.length - 10 }} 个设备...
                    </view>
                </scroll-view>
            </view>
        </view>

        <!-- 操作按钮区域 -->
        <view class="flex gap-5 mb-10 px-4">
            <button 
                class="flex-1 py-4 px-6 rounded-2xl text-lg font-bold border-none transition-all duration-200"
                :class="canPreview 
                    ? 'bg-blue-500 text-white shadow-lg active:scale-95' 
                    : 'bg-gray-300 text-gray-500'"
                :disabled="!canPreview"
                @click="previewData"
            >
                预览数据 ({{ filteredCount }})
            </button>
            
            <button 
                class="flex-1 py-4 px-6 rounded-2xl text-lg font-bold border-none transition-all duration-200"
                :class="canGenerate 
                    ? 'bg-gradient-to-r from-green-500 to-green-600 text-white shadow-lg active:scale-95' 
                    : 'bg-gray-300 text-gray-500'"
                :disabled="!canGenerate"
                @click="generateImage"
            >
                生成报价图片
            </button>
        </view>

        <!-- 图片生成器组件 -->
        <view v-if="showImageGenerator" class="mt-6">
            <PriceImageGenerator 
                ref="imageGeneratorRef"
                :device-list="deviceList"
                :brand-info="selectedBrandInfo"
                :device-type="filterForm.device_type"
                :items-per-page="20"
            />
            
            <!-- 图片操作按钮 -->
            <view class="flex flex-col gap-3 mt-6 px-4">
                <!-- 第一行：生成和下载 -->
                <view class="flex gap-3">
                    <button 
                        class="flex-1 bg-gradient-to-r from-purple-500 to-purple-600 text-white py-4 px-6 rounded-2xl font-bold text-lg shadow-lg"
                        :disabled="isGenerating"
                        @click="generateImage"
                    >
                        <text v-if="!isGenerating">🎨 生成图片</text>
                        <text v-else>⏳ 生成中...</text>
                    </button>
                    
                    <button 
                        class="flex-1 bg-gradient-to-r from-indigo-500 to-indigo-600 text-white py-4 px-6 rounded-2xl font-bold text-lg shadow-lg"
                        :disabled="!hasGeneratedImage"
                        :class="!hasGeneratedImage ? 'opacity-50' : ''"
                        @click="handleDownloadImage"
                    >
                        💾 下载图片
                    </button>
                </view>
                
                <!-- 第二行：保存和分享 -->
                <view class="flex gap-3">
                    <button 
                        class="flex-1 bg-gradient-to-r from-green-500 to-green-600 text-white py-3 px-6 rounded-xl font-medium text-base shadow-lg"
                        :disabled="!hasGeneratedImage"
                        :class="!hasGeneratedImage ? 'opacity-50' : ''"
                        @click="handleSaveImage"
                    >
                        📱 保存到相册
                    </button>
                    
                    <button 
                        class="flex-1 bg-gradient-to-r from-blue-500 to-blue-600 text-white py-3 px-6 rounded-xl font-medium text-base shadow-lg"
                        :disabled="!hasGeneratedImage"
                        :class="!hasGeneratedImage ? 'opacity-50' : ''"
                        @click="handleShareImage"
                    >
                        📤 分享图片
                    </button>
                </view>
            </view>
        </view>

        <!-- 生成加载提示 -->
        <view class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" v-if="isGenerating">
            <view class="bg-white rounded-2xl p-8 mx-6 text-center max-w-sm w-full">
                <view class="text-6xl mb-4">📱</view>
                <view class="text-xl font-bold text-gray-800 mb-4">准备生成报价图片</view>
                <view class="text-sm text-gray-600">请稍等片刻...</view>
            </view>
        </view>
    </view>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { getDeviceList, getBrandList, getStatistics } from '@/addon/hsx_recycle/api/price'
import PriceImageGenerator from './components/PriceImageGenerator.vue'

// 响应式数据
const filterForm = reactive({
    device_type: '',
    brand_id: '',
    keyword: ''
})

const deviceList = ref([])
const brandList = ref([])
const statistics = ref({})
const showPreview = ref(false)
const showImageGenerator = ref(false)
const isGenerating = ref(false)
const imageGeneratorRef = ref(null)

// 设备类型配置
const deviceTypes = [
    { value: '', name: '全部类型', icon: '📱', count: 0 },
    { value: 'phone', name: '手机', icon: '📱', count: 0 },
    { value: 'tablet', name: '平板', icon: '📟', count: 0 },
    { value: 'watch', name: '手表', icon: '⌚', count: 0 }
]

// 计算属性
const canPreview = computed(() => {
    return filterForm.device_type || filterForm.brand_id || filterForm.keyword
})

const canGenerate = computed(() => {
    return showPreview.value && deviceList.value.length > 0
})

const hasGeneratedImage = computed(() => {
    return imageGeneratorRef.value?.generatedImageUrl || false
})

const filteredCount = computed(() => {
    return deviceList.value.length
})

const selectedBrandInfo = computed(() => {
    if (!filterForm.brand_id || !brandList.value.length) return {}
    return brandList.value.find(brand => brand.id === filterForm.brand_id) || {}
})

// 方法定义
const selectDeviceType = (type) => {
    filterForm.device_type = type
    showPreview.value = false
}

const selectBrand = (brandId) => {
    filterForm.brand_id = brandId
    showPreview.value = false
}

const onKeywordInput = () => {
    showPreview.value = false
}

const getDeviceTypeCount = (type) => {
    if (!statistics.value.device_type_distribution) return 0
    if (!type) {
        // 全部类型的总数
        return statistics.value.total_devices || 0
    }
    const item = statistics.value.device_type_distribution.find(d => d.device_type === type)
    return item ? item.count : 0
}

const getBrandLogo = (brandCode) => {
    // 这里可以根据品牌代码返回对应的logo图片
    const logoMap = {
        'apple': '/static/images/brands/apple.png',
        'huawei': '/static/images/brands/huawei.png',
        'samsung': '/static/images/brands/samsung.png',
        'xiaomi': '/static/images/brands/xiaomi.png',
        'oppo': '/static/images/brands/oppo.png',
        'vivo': '/static/images/brands/vivo.png'
    }
    return logoMap[brandCode] || '/static/images/brands/default.png'
}

const previewData = async () => {
    try {
        uni.showLoading({ title: '加载中...' })
        
        const params = {
            device_type: filterForm.device_type,
            brand_id: filterForm.brand_id,
            keyword: filterForm.keyword,
            page: 1,
            limit: 100 // 预览最多100条
        }
        
        const res = await getDeviceList(params)
        if (res.code === 1) {
            deviceList.value = res.data.data || []
            showPreview.value = true
            
            uni.showToast({
                title: `找到 ${deviceList.value.length} 个设备`,
                icon: 'success'
            })
        } else {
            uni.showToast({
                title: res.msg || '数据加载失败',
                icon: 'error'
            })
        }
    } catch (error) {
        console.error('预览数据失败:', error)
        uni.showToast({
            title: '数据加载失败',
            icon: 'error'
        })
    } finally {
        uni.hideLoading()
    }
}

const generateImage = async () => {
    try {
        isGenerating.value = true
        
        // 显示图片生成器组件
        showImageGenerator.value = true
        
        // 等待组件渲染完成后再调用生成方法
        await new Promise(resolve => setTimeout(resolve, 300))
        
        // 调用图片生成器组件的方法
        if (imageGeneratorRef.value) {
            await imageGeneratorRef.value.generateImage()
        }
        
        isGenerating.value = false
        
    } catch (error) {
        console.error('生成图片失败:', error)
        isGenerating.value = false
        
        uni.showToast({
            title: '生成失败',
            icon: 'error'
        })
    }
}

const handleSaveImage = async () => {
    if (imageGeneratorRef.value) {
        await imageGeneratorRef.value.saveToAlbum()
    }
}

const handleDownloadImage = async () => {
    if (imageGeneratorRef.value) {
        await imageGeneratorRef.value.downloadImage()
    }
}

const handleShareImage = async () => {
    if (imageGeneratorRef.value) {
        await imageGeneratorRef.value.shareImage()
    }
}

// 数据加载
const loadBrandList = async () => {
    try {
        const res = await getBrandList()
        if (res.code === 1) {
            brandList.value = res.data || []
        }
    } catch (error) {
        console.error('加载品牌列表失败:', error)
    }
}

const loadStatistics = async () => {
    try {
        const res = await getStatistics()
        if (res.code === 1) {
            statistics.value = res.data || {}
        }
    } catch (error) {
        console.error('加载统计信息失败:', error)
    }
}

// 生命周期
onMounted(() => {
    loadBrandList()
    loadStatistics()
})
</script>

<style scoped>
/* 全部使用 Tailwind CSS，保留极少量必要的自定义样式 */

/* 设备类型网格的特殊间距 */
.device-type-grid {
    display: flex;
    gap: 10px;
}

/* 品牌网格布局 */
.brand-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}

/* 活跃状态的变换效果 */
.device-type-item.active,
.brand-item.active {
    transform: translateY(-2px);
    transition: all 0.2s ease;
}

/* 搜索输入框样式 */
.search-input {
    width: 100%;
    border: none;
    outline: none;
}
</style>