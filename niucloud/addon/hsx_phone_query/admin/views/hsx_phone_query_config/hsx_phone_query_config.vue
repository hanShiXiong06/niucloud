<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <template #header>
                <div class="flex items-center justify-between">
                    <span class="text-lg">{{ pageName || '手机查询配置' }}</span>
                    <el-button type="primary" :loading="loading" @click="saveConfig">保存并生效</el-button>
                </div>
            </template>

            <el-form label-width="110px" v-loading="loading">
                <el-card class="config-section !border-none" shadow="never">
                    <template #header>
                        <div class="flex items-center justify-between">
                            <div class="font-medium">查询渠道</div>
                            <el-tag :type="channelChanged ? 'warning' : (setupReady ? 'success' : 'warning')" effect="plain">
                                {{ channelChanged ? '待保存' : (setupReady ? `当前启用：${activeChannel.name}` : '当前渠道未配置') }}
                            </el-tag>
                        </div>
                    </template>

                    <el-alert
                        class="mb-[16px]"
                        :type="channelChanged ? 'warning' : (setupReady ? 'success' : 'warning')"
                        :closable="false"
                        :title="channelChanged ? `已选择 ${activeChannel.name}，点击保存并生效后才会切换查询渠道` : (setupReady ? `${activeChannel.name} 已作为唯一查询渠道` : '请选择一个查询渠道并填写密钥')"
                        show-icon
                    />

                    <el-form-item label="查询渠道">
                        <el-radio-group v-model="config.default_channel_key" class="provider-selector" @change="activateChannel">
                            <el-radio-button
                                v-for="channel in config.channels"
                                :key="channel.key"
                                :label="channel.key"
                            >
                                {{ channel.name }}
                            </el-radio-button>
                        </el-radio-group>
                    </el-form-item>

                    <template v-if="isGkdtProvider(activeChannel.provider)">
                        <el-form-item label="AppID">
                            <el-input v-model="activeChannel.appid" clearable placeholder="填写爱查 AppID" />
                        </el-form-item>
                        <el-form-item label="Secret">
                            <el-input v-model="activeChannel.secret" clearable placeholder="填写爱查 Secret" show-password />
                        </el-form-item>
                    </template>

                    <template v-if="activeChannel.provider === 'path_query'">
                        <el-form-item label="API Key">
                            <el-input v-model="activeChannel.token" clearable placeholder="填写 3023 API Key" show-password />
                        </el-form-item>
                    </template>
                </el-card>

                <el-collapse class="mt-[16px]">
                    <el-collapse-item title="详情展示" name="display">
                        <el-row :gutter="16">
                            <el-col :span="12">
                                <el-form-item label="报告品牌">
                                    <el-input v-model="config.display_config.brand_name" placeholder="手机查询报告" />
                                </el-form-item>
                                <el-form-item label="说明文案">
                                    <el-input v-model="config.display_config.support_text" type="textarea" :rows="3" />
                                </el-form-item>
                                <el-form-item label="设备图片">
                                    <el-switch v-model="config.display_config.detail.show_device_image" :active-value="1" :inactive-value="0" />
                                </el-form-item>
                                <el-form-item label="隐藏串号">
                                    <el-switch v-model="config.display_config.detail.mask_query_code" :active-value="1" :inactive-value="0" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="12">
                                <el-form-item label="启用水印">
                                    <el-switch v-model="config.display_config.watermark.enabled" :active-value="1" :inactive-value="0" />
                                </el-form-item>
                                <el-form-item label="水印文字">
                                    <el-input v-model="config.display_config.watermark.text" placeholder="仅供参考" />
                                </el-form-item>
                                <el-form-item label="分享图">
                                    <el-switch v-model="config.display_config.share.poster_enabled" :active-value="1" :inactive-value="0" />
                                </el-form-item>
                                <el-form-item label="分享标题">
                                    <el-input v-model="config.display_config.share.title" />
                                </el-form-item>
                                <el-form-item label="客服电话">
                                    <el-input v-model="config.display_config.share.customer_phone" placeholder="用于分享海报，可为空" />
                                </el-form-item>
                            </el-col>
                        </el-row>
                    </el-collapse-item>

                    <el-collapse-item title="高级设置：接口地址和映射" name="advanced">
                        <el-tabs v-model="activeTab">
                            <el-tab-pane label="当前渠道参数" name="channels">
                                <el-form-item label="渠道名称">
                                    <el-input v-model="activeChannel.name" />
                                </el-form-item>
                                <el-form-item label="接口地址">
                                    <el-input v-model="activeChannel.base_url" />
                                </el-form-item>
                                <el-form-item label="请求方式">
                                    <el-select v-model="activeChannel.method" class="w-full">
                                        <el-option label="GET" value="GET" />
                                        <el-option label="POST" value="POST" />
                                    </el-select>
                                </el-form-item>

                                <template v-if="isGkdtProvider(activeChannel.provider)">
                                    <el-form-item label="服务ID参数">
                                        <el-input v-model="activeChannel.service_id_key" placeholder="默认 key" />
                                    </el-form-item>
                                    <el-form-item label="返回样式">
                                        <el-input v-model="activeChannel.style" placeholder="默认 11" />
                                    </el-form-item>
                                </template>

                                <template v-if="activeChannel.provider === 'path_query'">
                                    <el-form-item label="鉴权位置">
                                        <el-select v-model="activeChannel.auth_type" class="w-full">
                                            <el-option label="Header" value="header" />
                                            <el-option label="Query" value="query" />
                                        </el-select>
                                    </el-form-item>
                                    <el-form-item label="鉴权字段">
                                        <el-input v-model="activeChannel.auth_key" placeholder="默认 key" />
                                    </el-form-item>
                                </template>

                                <el-form-item label="超时秒数">
                                    <el-input-number v-model="activeChannel.timeout" :min="1" :max="300" />
                                </el-form-item>
                            </el-tab-pane>

                            <el-tab-pane label="接口映射" name="mappings">
                                <div class="flex justify-end mb-[12px]">
                                    <el-button type="primary" plain @click="addMapping">新增映射</el-button>
                                </div>

                                <el-table :data="activeMappings" border>
                                    <el-table-column label="启用" width="80">
                                        <template #default="{ row }">
                                            <el-switch v-model="row.enabled" :active-value="1" :inactive-value="0" />
                                        </template>
                                    </el-table-column>
                                    <el-table-column label="服务编码" min-width="170">
                                        <template #default="{ row }">
                                            <el-input v-model="row.service_code" placeholder="apple_coverage" />
                                        </template>
                                    </el-table-column>
                                    <el-table-column label="接口值" min-width="190">
                                        <template #default="{ row }">
                                            <el-input v-model="row.endpoint_value" placeholder="10101 或 /apple/coverage" />
                                        </template>
                                    </el-table-column>
                                    <el-table-column label="参数名" width="120">
                                        <template #default="{ row }">
                                            <el-select v-model="row.query_param" class="w-full">
                                                <el-option label="序列号 sn" value="sn" />
                                                <el-option label="IMEI imei" value="imei" />
                                                <el-option label="爱查 code" value="code" />
                                                <el-option label="条码 barcode" value="barcode" />
                                                <el-option label="IP ip" value="ip" />
                                                <el-option label="手机号 phone" value="phone" />
                                            </el-select>
                                        </template>
                                    </el-table-column>
                                    <el-table-column label="成本价" width="120">
                                        <template #default="{ row }">
                                            <el-input v-model="row.cost_price" />
                                        </template>
                                    </el-table-column>
                                    <el-table-column label="操作" width="90" fixed="right">
                                        <template #default="{ $index }">
                                            <el-button type="danger" link @click="removeMapping($index)">删除</el-button>
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </el-tab-pane>
                        </el-tabs>
                    </el-collapse-item>
                </el-collapse>
            </el-form>
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { computed, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import {
    getHsxPhoneQueryProviderConfig,
    saveHsxPhoneQueryProviderConfig
} from '@/addon/hsx_phone_query/api/hsx_phone_query_config'

const route = useRoute()
const pageName = route.meta.title
const loading = ref(false)
const activeTab = ref('channels')
const savedChannelKey = ref('')

const config = reactive<any>({
    enabled: 1,
    default_channel_key: '3023_main',
    channels: [],
    mappings: [],
    display_config: createDefaultDisplayConfig(),
    setup_status: {}
})

const activeChannel = computed(() => {
    return config.channels.find((channel: any) => channel.key === config.default_channel_key) || {}
})

const activeMappings = computed(() => {
    return config.mappings.filter((mapping: any) => mapping.channel_key === config.default_channel_key)
})

const setupReady = computed(() => hasChannelCredential(activeChannel.value))
const channelChanged = computed(() => {
    return !!savedChannelKey.value && config.default_channel_key !== savedChannelKey.value
})

const isGkdtProvider = (provider: string) => ['gkdt_query', 'service_id_query'].includes(provider)

const hasChannelCredential = (channel: any) => {
    if (isGkdtProvider(channel?.provider)) return !!channel.appid && !!channel.secret
    if (channel?.provider === 'path_query') return !!channel.token
    return false
}

const loadConfig = async () => {
    loading.value = true
    try {
        const res = await getHsxPhoneQueryProviderConfig()
        Object.assign(config, res.data || {})
        config.channels = config.channels || []
        config.mappings = config.mappings || []
        config.display_config = mergeDisplayConfig(config.display_config)
        normalizeActiveChannel()
        savedChannelKey.value = config.default_channel_key
    } finally {
        loading.value = false
    }
}

const saveConfig = async () => {
    normalizeActiveChannel()
    if (!setupReady.value) {
        ElMessage.warning('请先填写当前服务商密钥')
        return
    }

    loading.value = true
    try {
        const data = JSON.parse(JSON.stringify(config))
        data.enabled = 1
        delete data.setup_status
        await saveHsxPhoneQueryProviderConfig(data)
        await loadConfig()
    } finally {
        loading.value = false
    }
}

const activateChannel = (key: string) => {
    if (!key) return
    config.default_channel_key = key
    config.channels.forEach((channel: any) => {
        channel.enabled = channel.key === key ? 1 : 0
    })
}

const normalizeActiveChannel = () => {
    const current = config.channels.find((channel: any) => channel.key === config.default_channel_key)
    const key = current?.key || config.channels.find((channel: any) => channel.enabled)?.key || config.channels?.[0]?.key
    if (key) activateChannel(key)
}

const addMapping = () => {
    config.mappings.push({
        service_code: '',
        channel_key: config.default_channel_key,
        endpoint_type: activeChannel.value.provider === 'path_query' ? 'path' : 'service_id',
        endpoint_value: '',
        query_param: activeChannel.value.provider === 'path_query' ? 'sn' : 'code',
        cost_price: 0,
        enabled: 1
    })
}

const removeMapping = (index: number) => {
    const target = activeMappings.value[index]
    const realIndex = config.mappings.indexOf(target)
    if (realIndex >= 0) config.mappings.splice(realIndex, 1)
}

function createDefaultDisplayConfig() {
    return {
        brand_name: '手机查询报告',
        support_text: '查询结果仅供交易验机参考',
        detail: {
            show_device_image: 1,
            show_empty_fields: 0,
            show_raw_result: 0,
            mask_query_code: 0
        },
        watermark: {
            enabled: 1,
            text: '仅供参考',
            color: 'rgba(18, 24, 38, 0.06)',
            size: 88,
            opacity: 0.72,
            rotate: -24
        },
        share: {
            enabled: 1,
            poster_enabled: 1,
            title: '设备查询报告',
            subtitle: '长按识别或分享给客户查看',
            footer: '报告由系统自动生成',
            customer_phone: ''
        }
    }
}

function mergeDisplayConfig(incoming: any = {}) {
    const base = createDefaultDisplayConfig()
    return {
        ...base,
        ...incoming,
        detail: { ...base.detail, ...(incoming.detail || {}) },
        watermark: { ...base.watermark, ...(incoming.watermark || {}) },
        share: { ...base.share, ...(incoming.share || {}) }
    }
}

loadConfig()
</script>

<style lang="scss" scoped>
.config-section {
    background: var(--el-fill-color-lighter);
}

.provider-selector {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
</style>
