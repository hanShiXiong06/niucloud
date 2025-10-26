<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <span class="text-page-title">设备查询配置</span>
                    <p class="text-sm text-gray-500 mt-1">配置第三方设备查询服务的API密钥和基础URL</p>
                </div>
            </div>

            <!-- 配置卡片区域 -->
            <div v-loading="loading" class="config-cards-container"><!-- 阿里云配置卡片 -->
                <el-card class="config-card" shadow="hover">
                    <template #header>
                        <div class="flex justify-between items-center">
                            <div class="flex items-center">
                                <div class="config-icon bg-orange-500">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-lg font-semibold">阿里云配置</h3>
                                    <p class="text-xs text-gray-500">物流查询服务</p>
                                </div>
                            </div>
                            <el-switch
                                v-model="configAli.status"
                                :active-value="1"
                                :inactive-value="0"
                                :disabled="!configAli.id"
                                @change="updateStatus(configAli)"
                            />
                        </div>
                    </template>

                    <el-form :model="configAli" label-width="100px" label-position="left">
                        <el-form-item label="配置名称">
                            <el-input v-model="configAli.name" placeholder="请输入配置名称，如：阿里云物流解析" disabled />
                        </el-form-item>
                        <el-form-item label="API密钥">
                            <el-input
                                v-model="configAli.api_key"
                                placeholder="请输入API密钥"
                                type="password"
                                show-password
                            />
                        </el-form-item>
                        <el-form-item label="API基础URL">
                            <el-input
                                v-model="configAli.base_url"
                                placeholder="https://kzexpress.market.alicloudapi.com"
                                disabled
                            />
                        </el-form-item>
                        
                    </el-form>

                    <div class="flex justify-end gap-2 mt-4">
                        <el-button @click="resetConfig(configAli)" v-if="configAli.id">重置</el-button>
                        <el-button type="primary" @click="saveConfig(configAli, 'alicloud')">
                            {{ configAli.id ? '保存配置' : '创建配置' }}
                        </el-button>
                    </div>

                    <div v-if="configAli.id" class="config-meta">
                        <el-divider />
                        <div class="text-xs text-gray-500 flex justify-between">
                            <span>创建时间：{{ timeStampTurnTime(configAli.create_at) }}</span>
                            <span v-if="configAli.update_at">更新时间：{{ timeStampTurnTime(configAli.update_at) }}</span>
                        </div>
                    </div>
                </el-card>
                <!-- 3023data 配置卡片 -->
                <el-card class="config-card" shadow="hover">
                    <template #header>
                        <div class="flex justify-between items-center">
                            <div class="flex items-center">
                                <div class="config-icon bg-blue-500">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-lg font-semibold">3023data 配置</h3>
                                    <p class="text-xs text-gray-500">苹果设备保修查询服务</p>
                                </div>
                            </div>
                            <el-switch
                                v-model="config3023.status"
                                :active-value="1"
                                :inactive-value="0"
                                :disabled="!config3023.id"
                                @change="updateStatus(config3023)"
                            />
                        </div>
                    </template>

                    <el-form :model="config3023" label-width="100px" label-position="left">
                        <el-form-item label="配置名称">
                            <el-input v-model="config3023.name" placeholder="请输入配置名称，如：admin" disabled />
                        </el-form-item>
                        <el-form-item label="API密钥">
                            <el-input
                                v-model="config3023.api_key"
                                placeholder="请输入API密钥"
                                type="password"
                                show-password
                            />
                        </el-form-item>
                        <el-form-item label="API基础URL">
                            <el-input
                                v-model="config3023.base_url"
                                placeholder="http://api.3023data.com"
                                disabled
                            />
                        </el-form-item>

                    </el-form>

                    <div class="flex justify-end gap-2 mt-4">
                        <el-button @click="resetConfig(config3023)" v-if="config3023.id">重置</el-button>
                        <el-button type="primary" @click="saveConfig(config3023, '3023')">
                            {{ config3023.id ? '保存配置' : '创建配置' }}
                        </el-button>
                    </div>

                    <div v-if="config3023.id" class="config-meta">
                        <el-divider />
                        <div class="text-xs text-gray-500 flex justify-between">
                            <span>创建时间：{{ timeStampTurnTime(config3023.create_at) }}</span>
                            <span v-if="config3023.update_at">更新时间：{{ timeStampTurnTime(config3023.update_at) }}</span>
                        </div>
                    </div>
                </el-card>

                
            </div>
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { timeStampTurnTime } from '@/utils/common'
import {
    getDeviceQueryConfigList,
    addDeviceQueryConfig,
    editDeviceQueryConfig,
    updateDeviceQueryConfigStatus
} from '@/addon/recycle/api/device_query_config'
import {
    getDeviceQueryApiList
} from '@/addon/recycle/api/device_query_api'

const loading = ref(false)
const apiOptions3023 = ref<Array<{label: string, value: string}>>([])
const apiOptionsAli = ref<Array<{label: string, value: string}>>([])

// 3023data 配置
const config3023 = reactive({
    id: 0,
    name: '3023',
    api_key: '',
    base_url: 'http://api.3023data.com',
    enabled_apis: '/apple/coverage-capacity',
    timeout: 30,
    max_retry: 3,
    cache_time: 3600,
    daily_limit: 1000,
    balance: 0.00,
    status: 1,
    remark: '',
    create_at: 0,
    update_at: 0
})

// 原始数据备份（用于重置）
const original3023 = reactive({ ...config3023 })

// 阿里云配置
const configAli = reactive({
    id: 0,
    name: 'alicloudapi',
    api_key: '',
    base_url: 'https://kzexpress.market.alicloudapi.com',
    enabled_apis: '/api-mall/api/express/query',
    timeout: 30,
    max_retry: 3,
    cache_time: 3600,
    daily_limit: 1000,
    balance: 0.00,
    status: 1,
    remark: '',
    create_at: 0,
    update_at: 0
})

// 原始数据备份（用于重置）
const originalAli = reactive({ ...configAli })

// 加载API列表数据
const loadApiList = async (visible?: boolean) => {
    if (!visible || (apiOptions3023.value.length > 0 && apiOptionsAli.value.length > 0)) return

    try {
        const response = await getDeviceQueryApiList({ page: 1, limit: 100 })
        const apiList = response.data.data || []

        // 将API数据转换为选项格式
        const options3023: Array<{label: string, value: string}> = []
        const optionsAli: Array<{label: string, value: string}> = []

        apiList.forEach((item: any) => {
            if (item && typeof item === 'object') {
                const option = {
                    label: item.name,
                    value: item.api_list
                }

                // 根据 base_url 分类
                if (item.api_list && item.api_list.includes('apple')) {
                    options3023.push(option)
                } else {
                    optionsAli.push(option)
                }
            }
        })

        apiOptions3023.value = options3023
        apiOptionsAli.value = optionsAli
    } catch (error) {
        console.error('加载API列表失败:', error)
    }
}

// 加载配置列表数据
const loadConfigs = async () => {
    loading.value = true
    try {
        const data = await getDeviceQueryConfigList({ page: 1, limit: 100 })
        const configs = data.data.data || []

        // 根据 base_url 分配到不同的配置对象
        configs.forEach((item: any) => {
            if (item.base_url && item.base_url.includes('3023data.com')) {
                // 3023data 配置
                Object.assign(config3023, item)
                Object.assign(original3023, item)
            } else if (item.base_url && item.base_url.includes('alicloudapi.com')) {
                // 阿里云配置
                Object.assign(configAli, item)
                Object.assign(originalAli, item)
            }
        })
    } catch (error) {
        console.error('加载配置失败:', error)
        ElMessage.error('加载配置失败')
    } finally {
        loading.value = false
    }
}

// 保存配置
const saveConfig = async (config: any, type: string) => {
    // 验证必填项
    if (!config.name || !config.api_key || !config.base_url) {
        ElMessage.warning('请填写完整的配置信息')
        return
    }

    try {
        loading.value = true

        const saveData = {
            name: config.name,
            api_key: config.api_key,
            base_url: config.base_url,
            enabled_apis: config.enabled_apis || '',
            timeout: config.timeout,
            max_retry: config.max_retry,
            cache_time: config.cache_time,
            daily_limit: config.daily_limit,
            balance: config.balance,
            status: config.status,
            remark: config.remark
        }

        if (config.id) {
            // 更新现有配置
            await editDeviceQueryConfig(config.id, saveData)
            ElMessage.success('配置更新成功')
        } else {
            // 创建新配置
            const result = await addDeviceQueryConfig(saveData)
            config.id = result.data.id
            ElMessage.success('配置创建成功')
        }

        // 重新加载配置
        await loadConfigs()
    } catch (error) {
        console.error('保存配置失败:', error)
        ElMessage.error('保存配置失败')
    } finally {
        loading.value = false
    }
}

// 更新状态
const updateStatus = async (config: any) => {
    if (!config.id) return

    try {
        await updateDeviceQueryConfigStatus(config.id, config.status)
        ElMessage.success(config.status === 1 ? '配置已启用' : '配置已禁用')
    } catch (error) {
        // 恢复状态
        config.status = config.status === 1 ? 0 : 1
        console.error('更新状态失败:', error)
        ElMessage.error('更新状态失败')
    }
}

// 重置配置
const resetConfig = (config: any) => {
    if (config.base_url.includes('3023data.com')) {
        Object.assign(config, original3023)
    } else {
        Object.assign(config, originalAli)
    }
    ElMessage.info('已重置为原始配置')
}

onMounted(() => {
    loadConfigs()
})
</script>

<script lang="ts">
export default {
    name: 'DeviceQueryConfig'
}
</script>

<style lang="scss" scoped>
.config-cards-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 24px;

    @media (max-width: 1200px) {
        grid-template-columns: 1fr;
    }
}

.config-card {
    transition: all 0.3s ease;

    &:hover {
        transform: translateY(-2px);
    }

    :deep(.el-card__header) {
        padding: 20px;
        background: linear-gradient(135deg, #f5f7fa 0%, #f9fafb 100%);
        border-bottom: 1px solid #e5e7eb;
    }

    :deep(.el-card__body) {
        padding: 24px;
    }
}

.config-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.bg-blue-500 {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
}

.bg-orange-500 {
    background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
}

.config-meta {
    margin-top: 16px;
    padding-top: 16px;
}
</style>
