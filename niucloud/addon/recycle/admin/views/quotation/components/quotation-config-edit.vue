<template>
    <el-dialog v-model="showDialog" :title="formData.id ? '编辑报价单配置' : '添加报价单配置'" width="60%" class="diy-dialog-wrap" :destroy-on-close="true">
        <el-form :model="formData" label-width="120px" ref="formRef" :rules="formRules" class="page-form" v-loading="loading">
            <!-- CURL解析区域 -->
            <el-form-item label="CURL命令解析">
                <div class="curl-parse-area">
                    <el-input
                        v-model="curlCommand"
                        type="textarea"
                        :rows="4"
                        placeholder="请粘贴curl命令，然后点击解析按钮自动填充表单..."
                        class="curl-input"
                    />
                    <el-button type="primary" @click="parseCurl" :disabled="!curlCommand.trim()" style="margin-top: 10px;">
                        解析CURL命令
                    </el-button>
                </div>
            </el-form-item>
            
            <el-divider content-position="left">基础配置</el-divider>
            
            <el-form-item label="报价ID" prop="quotation_id">
                <el-input v-model="formData.quotation_id" clearable placeholder="请输入报价ID" class="input-width" />
            </el-form-item>
            
            <el-form-item label="价格名称" prop="price_name">
                <el-input v-model="formData.price_name" clearable placeholder="请输入价格名称" class="input-width" />
            </el-form-item>
            
            <el-form-item label="配置名称" prop="config_name">
                <el-input v-model="formData.config_name" clearable placeholder="请输入配置名称" class="input-width" />
            </el-form-item>
            
            <el-form-item label="Authorization Token" prop="authorization_token">
                <el-input v-model="formData.authorization_token" type="textarea" :rows="3" clearable placeholder="请输入Authorization Token" class="input-width" />
            </el-form-item>
            
            <el-form-item label="OpenId" prop="open_id">
                <el-input v-model="formData.open_id" clearable placeholder="请输入OpenId" class="input-width" />
            </el-form-item>
            
            <el-form-item label="是否启用" prop="is_enable">
                <el-switch v-model="formData.is_enable" :active-value="1" :inactive-value="0" />
            </el-form-item>
            
            <el-form-item label="自动请求" prop="auto_request">
                <el-switch v-model="formData.auto_request" :active-value="1" :inactive-value="0" />
            </el-form-item>
            
            <el-form-item label="请求时间" prop="request_time" v-if="formData.auto_request === 1">
                <el-time-picker 
                    v-model="formData.request_time" 
                    format="HH:mm" 
                    value-format="HH:mm" 
                    placeholder="请选择请求时间" 
                    class="input-width"
                    style="width: 100%"
                />
            </el-form-item>
        </el-form>

        <template #footer>
            <span class="dialog-footer">
                <el-button @click="showDialog = false">取消</el-button>
                <el-button type="primary" :loading="loading" @click="confirm(formRef)">
                    确定
                </el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script lang="ts" setup>
import { ref, reactive, computed } from 'vue'
import { t } from '@/lang'
import type { FormInstance } from 'element-plus'
import { ElMessage } from 'element-plus'
import { addQuotationConfig, editQuotationConfig, getQuotationConfigInfo } from '@/addon/recycle/api/quotation'

let showDialog = ref(false)
const loading = ref(false)
const curlCommand = ref('')

/**
 * 表单数据
 */
const initialFormData = {
    id: '',
    quotation_id: '',
    price_name: '',
    config_name: '',
    authorization_token: '',
    open_id: '',
    is_enable: 1,
    auto_request: 0,
    request_time: ''
}
const formData: Record<string, any> = reactive({ ...initialFormData })

const formRef = ref<FormInstance>()

/**
 * 解析CURL命令
 */
const parseCurl = () => {
    if (!curlCommand.value.trim()) {
        ElMessage.warning('请输入curl命令')
        return
    }

    try {
        let curlStr = curlCommand.value.trim()
        
        // 处理多行格式：移除反斜杠和换行符
        curlStr = curlStr.replace(/\\\s*\n\s*/g, ' ').replace(/\\\s*$/g, '')
        
        // 提取URL - 匹配单引号、双引号或无引号的URL
        let urlMatch = curlStr.match(/curl\s+[-XGET\s]*['"]?([^'"]*https?:\/\/[^'"]+)['"]?/i) || 
                      curlStr.match(/['"]?(https?:\/\/[^'\s"\\]+)['"]?/i)
        
        if (!urlMatch) {
            // 尝试更宽松的匹配
            urlMatch = curlStr.match(/(https?:\/\/[^\s'"]+)/i)
        }
        
        if (!urlMatch) {
            throw new Error('未找到URL地址，请检查curl命令格式')
        }
        
        let url = urlMatch[1] || urlMatch[0]
        // 清理引号和末尾的反斜杠
        url = url.replace(/^['"]+|['"]+$/g, '').replace(/\\+$/, '').trim()
        
        // 解析URL参数
        const urlObj = new URL(url)
        const params = new URLSearchParams(urlObj.search)
        
        // 提取quotation_id
        const quotationId = params.get('quotation_id') || ''
        if (quotationId) {
            formData.quotation_id = quotationId
        }
        
        // 提取price_name（URL解码）
        const priceName = params.get('price_name') || ''
        if (priceName) {
            try {
                formData.price_name = decodeURIComponent(priceName)
            } catch (e) {
                // 如果解码失败，使用原始值
                formData.price_name = priceName
            }
        }
        
        // 提取Authorization header - 支持多种格式
        // 格式1: -H 'Authorization: xxx'
        // 格式2: -H "Authorization: xxx"
        // 格式3: -H Authorization: xxx
        let authMatch = curlStr.match(/-H\s+['"]?Authorization:\s*([^'"]+?)(?:['"]|\s|$)/i) ||
                       curlStr.match(/Authorization:\s*([^\n\r'"]+)/i)
        
        if (authMatch) {
            let authValue = authMatch[1].trim()
            // 移除可能的引号和反斜杠
            authValue = authValue.replace(/^['"]+|['"]+$/g, '').replace(/\\+$/, '').trim()
            formData.authorization_token = authValue
        }
        
        // 提取OpenId header
        let openIdMatch = curlStr.match(/-H\s+['"]?OpenId:\s*([^'"]+?)(?:['"]|\s|$)/i) ||
                         curlStr.match(/OpenId:\s*([^\n\r'"]+)/i)
        
        if (openIdMatch) {
            let openIdValue = openIdMatch[1].trim()
            // 移除可能的引号和反斜杠
            openIdValue = openIdValue.replace(/^['"]+|['"]+$/g, '').replace(/\\+$/, '').trim()
            formData.open_id = openIdValue
        }
        
        // 如果没有配置名称，自动生成一个
        if (!formData.config_name && quotationId && priceName) {
            const decodedPriceName = decodeURIComponent(priceName)
            formData.config_name = `报价${quotationId}_${decodedPriceName}`
        }
        
        // 验证必填字段
        if (!formData.quotation_id) {
            ElMessage.warning('警告：未找到quotation_id参数')
        }
        if (!formData.price_name) {
            ElMessage.warning('警告：未找到price_name参数')
        }
        if (!formData.authorization_token) {
            ElMessage.warning('警告：未找到Authorization header')
        }
        if (!formData.open_id) {
            ElMessage.warning('警告：未找到OpenId header')
        }
        
        ElMessage.success('CURL命令解析成功！已自动填充表单字段')
        
        // 清空curl命令
        curlCommand.value = ''
        
    } catch (error: any) {
        ElMessage.error('解析失败：' + (error.message || '格式不正确，请检查curl命令格式'))
        console.error('CURL解析错误：', error)
    }
}

// 表单验证规则
const formRules = computed(() => {
    return {
        quotation_id: [
            { required: true, message: '请输入报价ID', trigger: 'blur' }
        ],
        price_name: [
            { required: true, message: '请输入价格名称', trigger: 'blur' }
        ],
        config_name: [
            { required: true, message: '请输入配置名称', trigger: 'blur' }
        ],
        authorization_token: [
            { required: true, message: '请输入Authorization Token', trigger: 'blur' }
        ],
        open_id: [
            { required: true, message: '请输入OpenId', trigger: 'blur' }
        ]
    }
})

const emit = defineEmits(['complete'])

/**
 * 确认
 */
const confirm = async (formEl: FormInstance | undefined) => {
    if (loading.value || !formEl) return
    let save = formData.id ? editQuotationConfig : addQuotationConfig

    await formEl.validate(async (valid) => {
        if (valid) {
            loading.value = true

            let data = { ...formData }
            if (!data.auto_request) {
                data.request_time = ''
            }

            save(data).then(res => {
                loading.value = false
                showDialog.value = false
                emit('complete')
            }).catch(err => {
                loading.value = false
            })
        }
    })
}

const setFormData = async (row: any = null) => {
    Object.assign(formData, initialFormData)
    curlCommand.value = '' // 重置curl命令
    loading.value = true
    if (row) {
        const data = await (await getQuotationConfigInfo(row.id)).data
        if (data) {
            Object.keys(formData).forEach((key: string) => {
                if (data[key] != undefined) formData[key] = data[key]
            })
        }
    }
    loading.value = false
}

defineExpose({
    showDialog,
    setFormData
})
</script>

<style lang="scss" scoped>
.curl-parse-area {
    width: 100%;
    
    .curl-input {
        width: 100%;
    }
}
</style>
<style lang="scss">
.diy-dialog-wrap .el-form-item__label {
    height: auto !important;
}
</style>

