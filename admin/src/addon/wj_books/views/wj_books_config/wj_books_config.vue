<template>
    <div class="main-container">
        <!-- 平台设置 -->
        <el-card class="box-card !border-none mb-4" shadow="never">
            <template #header>
                <div class="flex justify-between items-center">
                    <span class="text-lg font-medium">平台设置</span>
                </div>
            </template>
            
            <div v-loading="loading.platform">
                <el-form :model="formData" label-width="180px" ref="platformFormRef" :rules="platformRules">
                    <el-form-item label="平台名称" prop="platform_name">
                        <el-input v-model="formData.platform_name" placeholder="请输入平台名称" style="width: 400px;" />
                    </el-form-item>
                    <el-form-item label="上门回收最低图书数量" prop="min_book_count">
                        <el-input-number v-model="formData.min_book_count" :min="1" :max="100" />
                    </el-form-item>
                    <el-form-item label="拒收书籍可取回期限(天)" prop="rejected_book_retrieve_days">
                        <el-input-number v-model="formData.rejected_book_retrieve_days" :min="1" :max="30" />
                    </el-form-item>
                    <el-form-item label="回收价调整比例(%)" prop="price_adjust_rate">
                        <el-input-number v-model="formData.price_adjust_rate" :step="0.1" :precision="2" style="width: 180px;" />
                        <span class="ml-2 text-gray-500 text-sm">在自动算价结果基础上按比例调整，10 表示上调10%，-10 表示下调10%</span>
                    </el-form-item>
                    <el-form-item label="订单回收须知" prop="recycle_notice_content">
                        <el-input
                            v-model="formData.recycle_notice_content"
                            type="textarea"
                            :rows="8"
                            maxlength="5000"
                            show-word-limit
                            placeholder="请输入回收须知内容，支持换行"
                            style="width: 600px;"
                        />
                    </el-form-item>
                    
                    <el-form-item>
                        <el-button type="primary" @click="submitForm(platformFormRef, 'platform')">保存设置</el-button>
                    </el-form-item>
                </el-form>
            </div>
        </el-card>
        
        <!-- 图书API设置 -->
        <el-card class="box-card !border-none mb-4" shadow="never">
            <template #header>
                <div class="flex justify-between items-center">
                    <span class="text-lg font-medium">图书API设置</span>
                </div>
            </template>
            
            <div v-loading="loading.bookApi">
                <el-form :model="formData" label-width="180px" ref="bookApiFormRef" :rules="bookApiRules">
                    <el-form-item label="是否启用图书API" prop="book_api_enabled">
                        <el-switch v-model="formData.book_api_enabled" :active-value="1" :inactive-value="0" />
                        <span class="ml-2 text-gray-500 text-sm">启用后将调用外部API查询图书信息[能查到的书籍都可以回收]，否则只使用内部数据库[只回收内部数据库有的书籍]</span>
                    </el-form-item>
                    <el-form-item label="图书数据API来源" prop="book_api_provider">
                        <el-radio-group v-model="formData.book_api_provider">
                            <el-radio label="0">聚合数据<span class="ml-1 text-gray-500 text-sm">入口: juhe.cn</span></el-radio>
                            <el-radio label="1">
                                LikeApi
                                <span class="ml-1 text-gray-500 text-sm">入口: likeapi.cn 【自建ISBN接口数据库更划算】</span>
                                <el-button type="primary" size="small" class="ml-2" @click="openLikeApi">访问</el-button>
                            </el-radio>
                        </el-radio-group>
                    </el-form-item>
                    <el-form-item :label="formData.book_api_provider === '0' ? '聚合数据API密钥' : 'LikeApi密钥'" prop="book_api_key">
                        <el-input v-model="formData.book_api_key" :placeholder="formData.book_api_provider === '0' ? '请输入聚合数据API密钥' : '请输入LikeApi密钥'" style="width: 400px;" show-password />
                    </el-form-item>
                    
                    <el-form-item>
                        <el-button type="primary" @click="submitForm(bookApiFormRef, 'bookApi')">保存设置</el-button>
                    </el-form-item>
                </el-form>
            </div>
        </el-card>
        
        <!-- 云洋物流API设置 -->
        <el-card class="box-card !border-none mb-4" shadow="never">
            <template #header>
                <div class="flex justify-between items-center">
                    <div class="flex items-center">
                        <span class="text-lg font-medium">云洋物流API设置</span>
                        <el-button type="primary" size="small" class="ml-2" @click="openYunyang">入口</el-button>
                    </div>
                </div>
            </template>
            
            <div v-loading="loading.logistics">
                <el-form :model="formData" label-width="180px" ref="logisticsFormRef" :rules="logisticsRules">
                    <el-form-item label="自动下单云洋快递" prop="yunyang_auto_order">
                        <el-switch v-model="formData.yunyang_auto_order" :active-value="1" :inactive-value="0" />
                        <span class="ml-2 text-gray-500 text-sm">开启后用户下单将自动调用云洋物流API发快递，关闭则不进行云洋下单</span>
                    </el-form-item>
                    <el-form-item label="云洋物流AppID" prop="yunyang_appid">
                        <el-input v-model="formData.yunyang_appid" placeholder="请输入云洋物流AppID" style="width: 400px;" />
                    </el-form-item>
                    <el-form-item label="云洋物流AppSecret" prop="yunyang_app_secret">
                        <el-input v-model="formData.yunyang_app_secret" placeholder="请输入云洋物流AppSecret" style="width: 400px;" show-password />
                    </el-form-item>
                    <el-form-item label="回调通知URL" prop="yunyang_callback_url">
                        <el-input v-model="formData.yunyang_callback_url" placeholder="回调地址将自动生成" style="width: 400px;" disabled />
                        <span class="ml-2 text-gray-500 text-sm">格式: 域名 + /api/express_callback?site_id= + 站点ID</span>
                    </el-form-item>
                    <el-form-item label="指定快递类型" prop="yunyang_channel_subtag">
                        <el-select v-model="formData.yunyang_channel_subtag" placeholder="请选择快递类型" style="width: 200px;">
                            <el-option label="京东" value="京东" />
                            <el-option label="德邦" value="德邦" />
                            <el-option label="顺丰" value="顺丰" />
                            <el-option label="申通" value="申通" />
                            <el-option label="极兔" value="极兔" />
                            <el-option label="圆通" value="圆通" />
                            <el-option label="中通" value="中通" />
                            <el-option label="韵达" value="韵达" />
                            <el-option label="菜鸟" value="菜鸟" />
                        </el-select>
                    </el-form-item>
                    
                    <el-form-item>
                        <el-button type="primary" @click="submitForm(logisticsFormRef, 'logistics')">保存设置</el-button>
                    </el-form-item>
                </el-form>
            </div>
        </el-card>
        
        <!-- 收件人信息 -->
        <el-card class="box-card !border-none" shadow="never">
            <template #header>
                <div class="flex justify-between items-center">
                    <span class="text-lg font-medium">默认收件人信息</span>
                </div>
            </template>
            
            <div v-loading="loading.receiver">
                <el-form :model="formData" label-width="180px" ref="receiverFormRef" :rules="receiverRules">
                    <el-form-item label="收件人姓名" prop="express_receiver_name">
                        <el-input v-model="formData.express_receiver_name" placeholder="请输入默认收件人姓名" style="width: 200px;" />
                    </el-form-item>
                    <el-form-item label="收件人电话" prop="express_receiver_mobile">
                        <el-input v-model="formData.express_receiver_mobile" placeholder="请输入默认收件人电话" style="width: 200px;" />
                    </el-form-item>
                    <el-form-item label="收件省份" prop="express_receiver_province">
                        <el-input v-model="formData.express_receiver_province" placeholder="请输入默认收件省份" style="width: 200px;" />
                    </el-form-item>
                    <el-form-item label="收件城市" prop="express_receiver_city">
                        <el-input v-model="formData.express_receiver_city" placeholder="请输入默认收件城市" style="width: 200px;" />
                    </el-form-item>
                    <el-form-item label="收件区县" prop="express_receiver_county">
                        <el-input v-model="formData.express_receiver_county" placeholder="请输入默认收件区县" style="width: 200px;" />
                    </el-form-item>
                    <el-form-item label="收件街道" prop="express_receiver_town">
                        <el-input v-model="formData.express_receiver_town" placeholder="请输入默认收件街道" style="width: 200px;" />
                    </el-form-item>
                    <el-form-item label="收件详细地址" prop="express_receiver_location">
                        <el-input v-model="formData.express_receiver_location" placeholder="请输入默认收件详细地址" style="width: 400px;" />
                    </el-form-item>
                    
                    <el-form-item>
                        <el-button type="primary" @click="submitForm(receiverFormRef, 'receiver')">保存设置</el-button>
                    </el-form-item>
                </el-form>
            </div>
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, onMounted, computed, watch, nextTick } from 'vue'
import { getWjBooksConfig, updateWjBooksConfig } from '@/addon/wj_books/api/wj_books_config'
import { useRoute } from 'vue-router'
import { ElMessage, FormInstance, FormRules } from 'element-plus'

const route = useRoute()
const pageName = route.meta.title
const siteId = ref('')

// 分别设置各个表单的加载状态
const loading = reactive({
    platform: false,
    bookApi: false,
    logistics: false,
    receiver: false
})

// 表单引用
const platformFormRef = ref<FormInstance>()
const bookApiFormRef = ref<FormInstance>()
const logisticsFormRef = ref<FormInstance>()
const receiverFormRef = ref<FormInstance>()

// 表单数据
const formData = reactive({
    platform_name: '二手书回收平台',
    min_book_count: 5,
    rejected_book_retrieve_days: 2,
    price_adjust_rate: 0,
    recycle_notice_content: '',
    book_api_enabled: 1,
    book_api_provider: '0',
    book_api_key: '',
    yunyang_appid: '',
    yunyang_app_secret: '',
    yunyang_callback_url: '',
    yunyang_channel_subtag: '京东',
    yunyang_auto_order: 1,
    express_receiver_name: '',
    express_receiver_mobile: '',
    express_receiver_province: '',
    express_receiver_city: '',
    express_receiver_county: '',
    express_receiver_town: '',
    express_receiver_location: ''
})

// API密钥验证规则计算属性
const bookApiKeyRule = computed(() => {
    return [
        { 
            required: true, 
            message: formData.book_api_provider === '0' ? 
                '请输入聚合数据API密钥' : 
                '请输入LikeApi密钥', 
            trigger: 'blur' 
        }
    ]
})

// 监听API提供商变化，更新表单验证规则
watch(() => formData.book_api_provider, (newVal) => {
    bookApiRules.book_api_key = bookApiKeyRule.value
})

// 平台设置验证规则
const platformRules = reactive<FormRules>({
    platform_name: [
        { required: true, message: '请输入平台名称', trigger: 'blur' }
    ],
    min_book_count: [
        { required: true, message: '请输入上门回收最低图书数量', trigger: 'blur' }
    ],
    rejected_book_retrieve_days: [
        { required: true, message: '请输入拒收书籍可取回期限', trigger: 'blur' }
    ],
    price_adjust_rate: [
        { required: true, message: '请输入回收价调整比例', trigger: 'blur' }
    ],
    recycle_notice_content: [
        { required: true, message: '请输入订单回收须知', trigger: 'blur' }
    ]
})

// 图书API设置验证规则
const bookApiRules = reactive<FormRules>({
    book_api_key: [
        { required: true, message: '请输入图书API密钥', trigger: 'blur' }
    ]
})

// 云洋物流API设置验证规则
const logisticsRules = reactive<FormRules>({
    yunyang_appid: [
        { required: true, message: '请输入云洋物流AppID', trigger: 'blur' }
    ],
    yunyang_app_secret: [
        { required: true, message: '请输入云洋物流AppSecret', trigger: 'blur' }
    ],
    yunyang_callback_url: [],
    yunyang_channel_subtag: [
        { required: true, message: '请选择指定快递类型', trigger: 'change' }
    ]
})

// 收件人信息验证规则
const receiverRules = reactive<FormRules>({
    express_receiver_name: [
        { required: true, message: '请输入收件人姓名', trigger: 'blur' }
    ],
    express_receiver_mobile: [
        { required: true, message: '请输入收件人电话', trigger: 'blur' },
        { pattern: /^1[3-9]\d{9}$/, message: '请输入正确的手机号码', trigger: 'blur' }
    ],
    express_receiver_province: [
        { required: true, message: '请输入收件省份', trigger: 'blur' }
    ],
    express_receiver_city: [
        { required: true, message: '请输入收件城市', trigger: 'blur' }
    ],
    express_receiver_county: [
        { required: true, message: '请输入收件区县', trigger: 'blur' }
    ],
    express_receiver_town: [
        { required: true, message: '请输入收件街道', trigger: 'blur' }
    ],
    express_receiver_location: [
        { required: true, message: '请输入收件详细地址', trigger: 'blur' }
    ]
})

// 获取配置
const loadConfig = () => {
    // 设置所有部分的加载状态
    Object.keys(loading).forEach(key => {
        loading[key] = true
    })

    getWjBooksConfig().then(res => {
        if (res.data && res.code === 1) {
            // 先清除所有表单验证状态
            nextTick(() => {
                platformFormRef.value?.clearValidate()
                bookApiFormRef.value?.clearValidate()
                logisticsFormRef.value?.clearValidate()
                receiverFormRef.value?.clearValidate()
            })

            Object.assign(formData, res.data)
            // 更新API密钥验证规则
            bookApiRules.book_api_key = bookApiKeyRule.value

            // 获取站点ID
            if (res.data.site_id) {
                siteId.value = res.data.site_id
            } else {
                // 如果没有站点ID，则使用默认值2
                siteId.value = '2'
            }

            // 自动生成回调URL
            generateCallbackUrl()
        }

        // 关闭所有部分的加载状态
        Object.keys(loading).forEach(key => {
            loading[key] = false
        })
    }).catch(() => {
        // 关闭所有部分的加载状态
        Object.keys(loading).forEach(key => {
            loading[key] = false
        })

        // 设置默认站点ID并生成回调URL
        siteId.value = '2'
        generateCallbackUrl()
    })
}

// 生成回调URL
const generateCallbackUrl = () => {
    if (siteId.value) {
        // 获取当前域名
        const domain = window.location.origin
        formData.yunyang_callback_url = `${domain}/api/express_callback?site_id=${siteId.value}`
    }
}

// 提交表单
const submitForm = async (formEl: FormInstance | undefined, section: string) => {
    if (!formEl) return
    
    await formEl.validate((valid) => {
        if (valid) {
            loading[section] = true
            updateWjBooksConfig(formData).then((res) => {
                loading[section] = false
            }).catch(() => {
                loading[section] = false
            })
        }
    })
}

// 打开云洋物流官网
const openYunyang = () => {
    window.open('https://open.yunyangwl.com/#/homePage', '_blank')
}

// 打开LikeApi网站
const openLikeApi = () => {
    window.open('http://www.likeapi.cn', '_blank')
}

onMounted(() => {
    loadConfig()
    // 初始化API密钥验证规则
    bookApiRules.book_api_key = bookApiKeyRule.value
})
</script>

<style lang="scss" scoped>
.box-card {
    margin-bottom: 20px;
}
</style> 
