<template>
    <div class="excel-upload">
        <!-- 上传对话框 -->
        <el-dialog
            v-model="visible"
            title="Excel数据导入"
            width="600px"
            :close-on-click-modal="false"
            @close="handleClose"
        >
            <div class="upload-container">
                <!-- 说明信息 -->
                <div class="mb-4">
                    <el-alert
                        title="导入说明"
                        type="info"
                        show-icon
                        :closable="false"
                    >
                        <p>1. 请确保Excel文件格式正确，包含品牌、型号、容量和价格信息</p>
                        <p>2. 系统将自动识别工作表中的品牌信息并创建相应的设备型号</p>
                        <p>3. 支持 .xlsx 和 .xls 格式，文件大小不超过20MB</p>
                    </el-alert>
                </div>

                <!-- 文件上传区域 -->
                <div class="text-center">
                    <el-upload
                        v-bind="uploadConfig"
                        class="upload-excel"
                        drag
                        :show-file-list="false"
                        accept=".xlsx,.xls"
                        :disabled="uploading"
                    >
                        <el-icon class="el-icon--upload"><upload-filled /></el-icon>
                        <div class="el-upload__text">
                            将Excel文件拖到此处，或<em>点击上传</em>
                        </div>
                        <div class="el-upload__tip">
                            只能上传 .xlsx/.xls 文件，且不超过20MB
                        </div>
                    </el-upload>
                </div>

                <!-- 上传进度 -->
                <div v-if="uploading" class="upload-progress mt-4">
                    <el-progress 
                        :percentage="uploadProgress" 
                        :status="uploadStatus"
                        :stroke-width="8"
                    />
                    <p class="text-center mt-2">{{ uploadStatusText }}</p>
                </div>

                <!-- 导入结果 -->
                <div v-if="importResult" class="import-result mt-4">
                    <el-result
                        :icon="importResult.success ? 'success' : 'error'"
                        :title="importResult.success ? '导入成功' : '导入失败'"
                    >
                        <template #sub-title>
                            <div v-if="importResult.success">
                                <p>成功导入品牌: {{ importResult.stats?.brands_inserted || 0 }} 个</p>
                                <p>成功导入型号: {{ importResult.stats?.models_inserted || 0 }} 个</p>
                                <p>成功导入价格: {{ importResult.stats?.prices_inserted || 0 }} 条</p>
                                <p>总处理行数: {{ importResult.stats?.total_processed || 0 }} 行</p>
                            </div>
                            <div v-else>
                                <p class="text-red-500">{{ importResult.message }}</p>
                            </div>
                        </template>
                    </el-result>
                </div>
            </div>

            <!-- 对话框底部按钮 -->
            <template #footer>
                <div class="dialog-footer">
                    <el-button @click="handleClose" :disabled="uploading">
                        {{ importResult ? '关闭' : '取消' }}
                    </el-button>
                    <el-button 
                        v-if="importResult?.success" 
                        type="success" 
                        @click="handleComplete"
                    >
                        完成
                    </el-button>
                </div>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { ElMessage, UploadFile } from 'element-plus'
import { UploadFilled } from '@element-plus/icons-vue'
import { getToken } from '@/utils/common'
import storage from '@/utils/storage'

interface Props {
    modelValue: boolean
}

interface Emits {
    (e: 'update:modelValue', value: boolean): void
    (e: 'success'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

// 响应式数据
const visible = ref(false)
const uploading = ref(false)
const uploadProgress = ref(0)
const uploadStatus = ref<'success' | 'exception' | undefined>(undefined)
const uploadStatusText = ref('')
const importResult = ref<any>(null)

// 上传配置
const uploadConfig = computed(() => ({
    action: `${import.meta.env.VITE_APP_BASE_URL}recycle/excel_import/upload`,
    headers: {
        [import.meta.env.VITE_REQUEST_HEADER_TOKEN_KEY]: getToken(),
        [import.meta.env.VITE_REQUEST_HEADER_SITEID_KEY]: storage.get('siteId') || 0
    },
    beforeUpload: beforeUpload,
    onSuccess: handleUploadSuccess,
    onError: handleUploadError,
    onProgress: handleUploadProgress
}))

// 监听props变化
watch(() => props.modelValue, (val) => {
    visible.value = val
})

watch(visible, (val) => {
    emit('update:modelValue', val)
    if (val) {
        resetForm()
    }
})

// 方法
const handleClose = () => {
    visible.value = false
    resetForm()
}

const resetForm = () => {
    uploading.value = false
    uploadProgress.value = 0
    uploadStatus.value = undefined
    uploadStatusText.value = ''
    importResult.value = null
}

// 文件上传前验证
const beforeUpload = (file: File) => {
    const isExcel = file.type === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' || 
                   file.type === 'application/vnd.ms-excel'
    const isLt20M = file.size / 1024 / 1024 < 20

    if (!isExcel) {
        ElMessage.error('只能上传Excel文件(.xlsx, .xls)!')
        return false
    }
    if (!isLt20M) {
        ElMessage.error('文件大小不能超过20MB!')
        return false
    }

    uploading.value = true
    uploadProgress.value = 0
    uploadStatus.value = undefined
    uploadStatusText.value = '正在上传文件...'
    importResult.value = null

    return true
}

// 上传进度
const handleUploadProgress = (event: any) => {
    if (event.percent) {
        uploadProgress.value = Math.floor(event.percent)
        if (uploadProgress.value < 50) {
            uploadStatusText.value = '正在上传文件...'
        } else if (uploadProgress.value < 100) {
            uploadStatusText.value = '文件上传中...'
        } else {
            uploadStatusText.value = '正在处理数据...'
        }
    }
}

// 上传成功
const handleUploadSuccess = async (response: any, uploadFile: UploadFile) => {
    uploading.value = false
    
    if (response.code === 1) {
        uploadProgress.value = 100
        uploadStatus.value = 'success'
        uploadStatusText.value = '导入完成'
        
        importResult.value = {
            success: true,
            message: response.msg || '导入成功',
            stats: response.data.stats,
            batch_id: response.data.batch_id
        }
        
        ElMessage.success('Excel数据导入成功')
        emit('success')
    } else {
        uploadStatus.value = 'exception'
        uploadStatusText.value = '导入失败'
        
        importResult.value = {
            success: false,
            message: response.msg || '导入失败'
        }
        
        ElMessage.error(response.msg || '导入失败')
    }
}

// 上传失败
const handleUploadError = (error: any) => {
    uploading.value = false
    uploadStatus.value = 'exception'
    uploadStatusText.value = '上传失败'
    
    importResult.value = {
        success: false,
        message: '文件上传失败，请重试'
    }
    
    ElMessage.error('文件上传失败')
    console.error(error)
}

const handleComplete = () => {
    handleClose()
}
</script>

<style scoped>
.excel-upload {
    /* 组件样式 */
}

.upload-container {
    padding: 20px 0;
}

.upload-excel {
    margin: 20px 0;
}

.upload-excel :deep(.el-upload) {
    width: 100%;
}

.upload-progress {
    padding: 20px;
    background-color: #f8f9fa;
    border-radius: 8px;
}

.import-result {
    border-radius: 8px;
    overflow: hidden;
}

.el-alert :deep(.el-alert__content) {
    text-align: left;
}

.el-alert p {
    margin: 4px 0;
}
</style> 