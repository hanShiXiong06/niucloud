<template>
    <el-dialog v-model="dialogVisible" title="" width="600px" :destroy-on-close="true" class="price-form-dialog">
        <template #header>
            <div class="flex justify-between items-center p-4 bg-gradient-to-r from-orange-600 to-red-600 text-white rounded-t-lg">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold">设备定价</h3>
                        <p class="text-orange-100 text-xs" v-if="deviceData.model">
                            {{ deviceData.model }}
                        </p>
                    </div>
                </div>
                <div v-if="deviceData.imei" class="text-right">
                    <div class="text-xs text-orange-100">IMEI</div>
                    <div class="font-mono text-sm">{{ deviceData.imei }}</div>
                </div>
            </div>
        </template>

        <div class="p-4 bg-gray-50 space-y-4 max-h-[70vh] overflow-y-auto">
            <!-- 设备信息和价格对比卡片 -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-2 bg-gradient-to-r from-gray-50 to-orange-50 border-b">
                    <div class="flex items-center space-x-2">
                        <svg class="w-4 h-4 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="font-medium text-gray-800 text-sm">价格对比</span>
                    </div>
                    <el-tag size="small" type="info" effect="plain">
                        ID: {{ deviceData.id }}
                    </el-tag>
                </div>
                
                <div class="p-4">
                    <!-- 设备基本信息 -->
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div class="bg-gray-50 rounded-md p-2">
                            <div class="text-xs text-gray-500">设备型号</div>
                            <div class="text-sm font-medium text-gray-900 truncate">{{ deviceData.model || '未知型号' }}</div>
                        </div>
                        <div class="bg-gray-50 rounded-md p-2">
                            <div class="text-xs text-gray-500">IMEI码</div>
                            <div class="text-sm font-mono text-gray-900">{{ deviceData.imei || '无IMEI' }}</div>
                        </div>
                    </div>

                    <!-- 价格对比区域 -->
                    <div class="flex items-center justify-between bg-gradient-to-r from-blue-50 to-orange-50 rounded-lg p-4 border">
                        <!-- 之前价格 -->
                        <div v-if="deviceData.before_price" class="text-center flex-1">
                            <div class="text-xs text-gray-500 mb-1">之前定价</div>
                            <div class="text-lg font-bold text-blue-600">¥{{ deviceData.before_price || '0.00' }}</div>
                        </div>
                        
                        <!-- 箭头 -->
                        <div v-if="deviceData.before_price" class="flex items-center justify-center px-4">
                            <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        
                        <!-- 最终价格 -->
                        <div class="text-center flex-1">
                            <div class="text-xs text-gray-500 mb-1">最终定价</div>
                            <div 
                                :class="[
                                    'text-lg font-bold',
                                    priceChangeClass === 'price-increase' ? 'text-green-600' :
                                    priceChangeClass === 'price-decrease' ? 'text-red-600' : 'text-orange-600'
                                ]"
                            >
                                ¥{{ deviceForm.final_price || '0.00' }}
                            </div>
                        </div>
                    </div>

                    <!-- 价格变化指示 -->
                    <div v-if="deviceData.before_price && deviceForm.final_price" class="mt-3 text-center">
                        <span 
                            :class="[
                                'inline-flex items-center text-xs font-medium px-2 py-1 rounded',
                                priceChangeClass === 'price-increase' ? 'bg-green-100 text-green-700' :
                                priceChangeClass === 'price-decrease' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700'
                            ]"
                        >
                            {{ getPriceChangeText() }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- 质检结果卡片 -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden" v-if="deviceData.check_result">
                <div class="flex items-center space-x-2 px-4 py-2 bg-gradient-to-r from-gray-50 to-green-50 border-b">
                    <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span class="font-medium text-gray-800 text-sm">质检结果</span>
                </div>
                <div class="p-4">
                    <div class="bg-green-50 rounded-md p-3 border border-green-200">
                        <div class="text-xs text-green-700 leading-relaxed">
                            {{ deviceData.check_result }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- 定价表单卡片 -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="flex items-center space-x-2 px-4 py-2 bg-gradient-to-r from-gray-50 to-purple-50 border-b">
                    <svg class="w-4 h-4 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                    </svg>
                    <span class="font-medium text-gray-800 text-sm">定价信息</span>
                </div>
                
                <div class="p-4">
                    <el-form :model="deviceForm" label-position="top" class="space-y-4">
                        
                        <!-- 最终价格输入 -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <svg class="w-4 h-4 inline mr-1 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/>
                                </svg>
                                最终价格 <span class="text-red-500">*</span>
                            </label>
                            <el-input-number 
                                v-model="deviceForm.final_price" 
                                placeholder="请输入最终价格" 
                                :step="10" 
                                :min="0"
                                style="width: 100%;" 
                                @change="updatePriceClass"
                                class="w-full"
                            />
                            <div class="mt-1 text-xs text-gray-500">
                                输入设备的最终定价金额，将影响用户的实际收益
                            </div>
                        </div>

                        <!-- 价格备注 -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <svg class="w-4 h-4 inline mr-1 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                </svg>
                                价格备注
                            </label>
                            <el-input 
                                v-model="deviceForm.remark" 
                                type="textarea" 
                                rows="3" 
                                placeholder="请输入定价理由或扣费说明"
                                maxlength="200" 
                                show-word-limit
                                class="w-full"
                            />
                            <div class="mt-1 text-xs text-gray-500">
                                详细说明定价的依据和理由，便于后续审核和记录
                            </div>
                        </div>

                        <!-- 表单验证提示 -->
                        <div v-if="!isFormValid && deviceForm.final_price !== undefined" 
                            class="bg-red-50 border border-red-200 rounded-md p-3">
                            <div class="flex items-center">
                                <svg class="w-4 h-4 text-red-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-sm text-red-700">请输入有效的价格（大于等于0）</span>
                            </div>
                        </div>
                        
                        <!-- 成功提示 -->
                        <div v-else-if="isFormValid" 
                            class="bg-green-50 border border-green-200 rounded-md p-3">
                            <div class="flex items-center">
                                <svg class="w-4 h-4 text-green-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-sm text-green-700">表单填写完整，可以提交定价</span>
                            </div>
                        </div>

                    </el-form>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end space-x-3 px-4 pb-4">
                <el-button 
                    @click="handleCancel" 
                    class="px-4 py-2"
                >
                    取消
                </el-button>
                <el-button 
                    type="primary" 
                    @click="handleConfirm" 
                    :disabled="!isFormValid"
                    class="px-6 py-2"
                >
                    <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    确认定价
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { ref, defineProps, defineEmits, watch, computed, reactive } from 'vue'
import { ElMessage } from 'element-plus'

// 定义设备信息接口
interface DeviceInfo {
    id?: string | number;
    model?: string;
    imei?: string;
    before_price?: string | number;
    check_result?: string;
    final_price?: string | number;
    remark?: string;
    status?: number;
    [key: string]: any;
}

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    device: {
        type: Object as () => DeviceInfo,
        default: () => ({})
    }
})

const emit = defineEmits(['update:visible', 'confirm', 'cancel'])

// 内部状态
const dialogVisible = ref(props.visible)
const deviceData = ref<DeviceInfo>({ ...props.device })
const deviceForm = reactive<{
    final_price: number | undefined;
    remark: string;
}>({
    final_price: typeof props.device.final_price === 'number'
        ? props.device.final_price
        : typeof props.device.final_price === 'string'
            ? parseFloat(props.device.final_price) || undefined
            : undefined,
    remark: props.device.remark || ''
})

// 计算属性：判断表单是否有效
const isFormValid = computed(() => {
    return typeof deviceForm.final_price === 'number' && deviceForm.final_price >= 0
})

// 计算价格变化比较的样式类
const priceChangeClass = ref('')

const updatePriceClass = () => {
    const initialPrice = typeof deviceData.value.before_price === 'string'
        ? parseFloat(deviceData.value.before_price)
        : (deviceData.value.before_price || 0)

    if (!deviceForm.final_price || initialPrice === 0) {
        priceChangeClass.value = ''
        return
    }

    if (deviceForm.final_price > initialPrice) {
        priceChangeClass.value = 'price-increase'
    } else if (deviceForm.final_price < initialPrice) {
        priceChangeClass.value = 'price-decrease'
    } else {
        priceChangeClass.value = ''
    }
}

// 获取价格变化文本
const getPriceChangeText = () => {
    const initialPrice = typeof deviceData.value.before_price === 'string'
        ? parseFloat(deviceData.value.before_price)
        : (deviceData.value.before_price || 0)
    
    if (!deviceForm.final_price || initialPrice === 0) return ''
    
    const diff = deviceForm.final_price - initialPrice
    if (diff > 0) return `上涨 ¥${diff.toFixed(2)}`
    if (diff < 0) return `下降 ¥${Math.abs(diff).toFixed(2)}`
    return '价格不变'
}

// 监听visible属性变化
watch(() => props.visible, (newVal) => {
    dialogVisible.value = newVal
})

// 监听device属性变化
watch(() => props.device, (newVal) => {
    deviceData.value = { ...newVal }
    
    deviceForm.final_price = typeof newVal.final_price === 'number'
        ? newVal.final_price
        : typeof newVal.final_price === 'string'
            ? parseFloat(newVal.final_price) || undefined
            : undefined
    deviceForm.remark = newVal.remark || ''
   
    deviceData.value.status = 4
    
    updatePriceClass()
}, { deep: true })

// 监听内部visible状态变化，同步到父组件
watch(dialogVisible, (newVal) => {
    emit('update:visible', newVal)
})

// 处理取消操作
const handleCancel = () => {
    dialogVisible.value = false
    emit('cancel')
}

// 处理确认操作
const handleConfirm = () => {
    if (!isFormValid.value) {
        ElMessage.warning('请输入有效的价格')
        return
    }

    emit('confirm', {
        id: deviceData.value.id,
        final_price: deviceForm.final_price,
        status: deviceData.value.status,
        remark: deviceForm.remark
    })
}
</script>

<style lang="scss" scoped>
.price-form-dialog {
    :deep(.el-dialog) {
        border-radius: 12px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        overflow: hidden;
        max-width: 650px;
    }

    :deep(.el-dialog__header) {
        padding: 0;
        border: none;
    }
    
    :deep(.el-dialog__body) {
        padding: 0;
        background-color: #f9fafb;
    }

    :deep(.el-dialog__footer) {
        padding: 0;
        border-top: 1px solid #e5e7eb;
        background-color: #f9fafb;
    }

    :deep(.el-dialog__headerbtn) {
        top: 1rem;
        right: 1rem;
        
        .el-dialog__close {
            color: white;
            font-size: 1.125rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 0.375rem;
            padding: 0.25rem;
            
            &:hover {
                background: rgba(255, 255, 255, 0.3);
                color: white;
            }
        }
    }
}

// 自定义滚动条
:deep(.el-dialog__body) {
    &::-webkit-scrollbar {
        width: 6px;
    }
    
    &::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 3px;
    }
    
    &::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 3px;
        
        &:hover {
            background: #94a3b8;
        }
    }
}

// 动画效果
@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.bg-white {
    animation: slideUp 0.4s ease-out;
}

.bg-white:nth-child(2) {
    animation-delay: 0.1s;
}

.bg-white:nth-child(3) {
    animation-delay: 0.2s;
}

.bg-white:nth-child(4) {
    animation-delay: 0.3s;
}
</style>