<template>
    <PremiumTheme class="device-query-page">
        <el-card class="!border-none" shadow="never">
            <PageHeader title="查机服务" description="按服务商维护查询目录，质检、验机等业务直接消费已启用的服务。">
                <template #actions>
                    <div class="header-actions">
                        <span class="global-switch-label">查机功能</span>
                        <el-switch v-model="config.enabled" :active-value="1" :inactive-value="0" @change="saveGlobalStatus" />
                        <el-button :loading="loading" @click="loadData">
                            <el-icon><Refresh /></el-icon>
                            刷新
                        </el-button>
                    </div>
                </template>
            </PageHeader>

            <div class="summary-grid" v-loading="loading">
                <div class="summary-item">
                    <div class="summary-icon is-provider"><el-icon><Connection /></el-icon></div>
                    <div><span>已接入服务商</span><strong>{{ enabledProviderCount }}<em> / {{ providerCards.length }}</em></strong></div>
                </div>
                <div class="summary-item">
                    <div class="summary-icon is-service"><el-icon><Grid /></el-icon></div>
                    <div><span>当前服务目录</span><strong>{{ providerServiceRows.length }}</strong></div>
                </div>
                <div class="summary-item">
                    <div class="summary-icon is-active"><el-icon><CircleCheck /></el-icon></div>
                    <div><span>已启用服务</span><strong>{{ enabledServiceCount }}</strong></div>
                </div>
                <div class="summary-item">
                    <div class="summary-icon is-check"><el-icon><View /></el-icon></div>
                    <div><span>质检可用</span><strong>{{ visibleServiceCount }}</strong></div>
                </div>
            </div>

            <section class="provider-section">
                <div class="section-heading">
                    <div>
                        <h2>选择服务商</h2>
                        <p>切换后只维护该服务商的账号和服务目录，不再单独处理“查询渠道”。</p>
                    </div>
                </div>
                <div class="provider-grid" v-loading="loading">
                    <button
                        v-for="item in providerCards"
                        :key="item.key"
                        type="button"
                        class="provider-card"
                        :class="{ active: activeProviderKey === item.key }"
                        @click="activeProviderKey = item.key"
                    >
                        <span class="provider-mark">{{ item.name.slice(0, 1) }}</span>
                        <span class="provider-content">
                            <span class="provider-name-row">
                                <strong>{{ item.name }}</strong>
                                <el-tag v-if="config.default_channel_key === item.key" size="small" effect="plain">默认</el-tag>
                            </span>
                            <span class="provider-meta">{{ item.service_count }} 项服务 · {{ item.enabled_count }} 项启用</span>
                        </span>
                        <span class="provider-state" :class="item.enabled ? 'is-on' : 'is-off'">
                            <i></i>{{ item.enabled ? '使用中' : '未启用' }}
                        </span>
                    </button>
                </div>
            </section>

            <section v-if="currentProvider" class="provider-console">
                <div class="provider-console-main">
                    <div class="provider-logo">{{ currentProvider.name.slice(0, 1) }}</div>
                    <div class="provider-summary">
                        <div class="provider-title-line">
                            <h2>{{ currentProvider.name }}</h2>
                            <el-tag :type="currentProvider.enabled ? 'success' : 'info'">
                                {{ currentProvider.enabled ? '已启用' : '未启用' }}
                            </el-tag>
                            <el-tag :type="providerSecretReady ? 'success' : 'warning'" effect="plain">
                                {{ providerSecretReady ? '账号已配置' : '待配置账号' }}
                            </el-tag>
                        </div>
                        <p>{{ currentProvider.base_url || '暂未填写服务地址' }}</p>
                    </div>
                </div>
                <div class="provider-actions">
                    <el-button v-if="config.default_channel_key !== currentProvider.key" @click="setDefaultProvider">设为默认</el-button>
                    <el-button v-if="currentProvider.provider === 'path_query'" :loading="balanceLoading" @click="queryBalance">查询余额</el-button>
                    <el-button @click="openProviderDialog"><el-icon><Setting /></el-icon>服务商设置</el-button>
                    <el-switch
                        :model-value="currentProvider.enabled"
                        inline-prompt
                        active-text="启用"
                        inactive-text="停用"
                        :active-value="1"
                        :inactive-value="0"
                        @change="saveProviderStatus"
                    />
                </div>
            </section>

            <section class="catalog-section">
                <div class="section-heading catalog-heading">
                    <div>
                        <h2>查询服务</h2>
                        <p>这里是可复用的基础服务目录。质检等业务只读取服务编码、名称和查询结果。</p>
                    </div>
                    <div class="catalog-actions">
                        <el-button v-if="sortDirty" type="success" :loading="saving" @click="saveSort">保存排序</el-button>
                        <el-button v-if="providerPresetRows.length" :loading="saving" @click="restoreProviderDefaults">
                            <el-icon><RefreshLeft /></el-icon>
                            恢复默认目录
                        </el-button>
                        <el-button type="primary" :disabled="!currentProvider" @click="openServiceDialog()">
                            <el-icon><Plus /></el-icon>
                            新增服务
                        </el-button>
                    </div>
                </div>

                <el-tabs v-model="categoryFilter" class="service-category-tabs">
                    <el-tab-pane v-for="item in categoryTabs" :key="item.value" :name="item.value">
                        <template #label>
                            <span class="category-tab-label">{{ item.label }}<em>{{ item.count }}</em></span>
                        </template>
                    </el-tab-pane>
                </el-tabs>

                <div class="filter-bar">
                    <el-input v-model.trim="keyword" clearable placeholder="搜索服务名称或编码" class="keyword-input">
                        <template #prefix><el-icon><Search /></el-icon></template>
                    </el-input>
                    <el-select v-model="statusFilter" clearable placeholder="全部状态" class="filter-select">
                        <el-option label="已启用" :value="1" />
                        <el-option label="已停用" :value="0" />
                    </el-select>
                    <span class="filter-result">
                        {{ sortHint }} · 共 {{ filteredRows.length }} 项
                    </span>
                </div>

                <div ref="serviceTableShellRef" v-loading="loading" class="service-table-shell">
                <el-table :data="filteredRows" class="service-table" row-key="code">
                    <el-table-column label="排序" width="96" align="center">
                        <template #default="{ row }">
                            <div class="sort-actions">
                                <el-tooltip :content="dragDisabled ? '清空检索条件后可拖动' : '按住拖动排序'" placement="top">
                                    <el-icon class="drag-handle" :class="{ 'is-disabled': dragDisabled }"><Rank /></el-icon>
                                </el-tooltip>
                                <span>{{ row.sort }}</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="服务" min-width="230">
                        <template #default="{ row }">
                            <div class="service-name"><strong>{{ row.name }}</strong><span>{{ row.code }}</span></div>
                        </template>
                    </el-table-column>
                    <el-table-column label="分类" width="100">
                        <template #default="{ row }">{{ optionLabel(categoryOptions, row.category) }}</template>
                    </el-table-column>
                    <el-table-column label="查询号码" width="110">
                        <template #default="{ row }">{{ optionLabel(queryTypeOptions, row.query_type) }}</template>
                    </el-table-column>
                    <el-table-column :label="currentEndpointLabel" min-width="180" show-overflow-tooltip>
                        <template #default="{ row }"><span class="endpoint-value">{{ row.mapping.endpoint_value || '-' }}</span></template>
                    </el-table-column>
                    <el-table-column label="参考成本" width="110" align="right">
                        <template #default="{ row }">¥{{ money(row.mapping.cost_price ?? row.cost_price) }}</template>
                    </el-table-column>
                    <el-table-column label="质检显示" width="100" align="center">
                        <template #default="{ row }">
                            <el-switch v-model="row.show_in_check" :active-value="1" :inactive-value="0" @change="saveServiceSwitch(row, 'show_in_check')" />
                        </template>
                    </el-table-column>
                    <el-table-column label="启用" width="86" align="center">
                        <template #default="{ row }">
                            <el-switch v-model="row.mapping.enabled" :active-value="1" :inactive-value="0" @change="saveServiceSwitch(row, 'enabled')" />
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="176" align="right" fixed="right">
                        <template #default="{ row }">
                            <el-button link type="primary" @click="testService(row)">测试</el-button>
                            <el-button link type="primary" @click="openServiceDialog(row)">编辑</el-button>
                            <el-button link type="danger" @click="removeService(row)">删除</el-button>
                        </template>
                    </el-table-column>
                    <template #empty>
                        <el-empty description="当前服务商还没有查询服务">
                            <el-button type="primary" @click="openServiceDialog()">新增服务</el-button>
                        </el-empty>
                    </template>
                </el-table>
                </div>
            </section>
        </el-card>

        <el-dialog v-model="providerDialogVisible" title="服务商设置" width="620px" destroy-on-close>
            <el-form label-width="100px" class="dialog-form">
                <el-form-item label="服务商名称" required><el-input v-model.trim="providerForm.name" maxlength="40" /></el-form-item>
                <el-form-item label="服务地址" required><el-input v-model.trim="providerForm.base_url" /></el-form-item>
                <template v-if="isGkdtProviderForm">
                    <el-form-item label="AppID" required>
                        <el-input v-model.trim="providerForm.appid" placeholder="填写爱查助手 AppID" />
                    </el-form-item>
                    <el-form-item label="Secret" required>
                        <el-input v-model.trim="providerForm.secret" type="password" show-password placeholder="填写爱查助手 Secret" />
                        <div class="field-tip">系统会按 appid、code、key、style、time 自动生成 MD5 签名，Secret 不会发送到前端查询结果。</div>
                    </el-form-item>
                    <el-form-item label="返回语言">
                        <el-select v-model="providerForm.style" class="w-full">
                            <el-option label="中文（style=11）" value="11" />
                            <el-option label="英文（style=0）" value="0" />
                        </el-select>
                    </el-form-item>
                </template>
                <el-form-item v-else label="API Key" required>
                    <el-input v-model.trim="providerForm.token" type="password" show-password placeholder="填写 3023 提供的 API Key" />
                    <div class="field-tip">请求时通过 Header 的 key 字段传递；已保存的密钥以 ****** 回显。</div>
                </el-form-item>
                <el-row :gutter="16">
                    <el-col :span="12"><el-form-item label="请求超时"><el-input-number v-model="providerForm.timeout" :min="5" :max="600" controls-position="right" /></el-form-item></el-col>
                    <el-col :span="12"><el-form-item label="优先级"><el-input-number v-model="providerForm.priority" :min="0" :max="999" controls-position="right" /></el-form-item></el-col>
                </el-row>
                <el-form-item label="启用服务商"><el-switch v-model="providerForm.enabled" :active-value="1" :inactive-value="0" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="providerDialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="saveProvider">保存</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="serviceDialogVisible" :title="serviceForm._editing ? '编辑查询服务' : '新增查询服务'" width="720px" destroy-on-close>
            <el-alert class="dialog-alert" type="info" :closable="false" show-icon title="服务保存后可被质检、验机等模块直接消费，无需再次配置业务映射。" />
            <el-form label-width="104px" class="dialog-form">
                <el-row :gutter="16">
                    <el-col :span="12"><el-form-item label="服务名称" required><el-input v-model.trim="serviceForm.name" placeholder="例如：苹果保修查询" /></el-form-item></el-col>
                    <el-col :span="12"><el-form-item label="服务编码" required><el-input v-model.trim="serviceForm.code" :disabled="serviceForm._editing" placeholder="例如：apple_coverage" /></el-form-item></el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12"><el-form-item label="服务分类" required><el-select v-model="serviceForm.category" class="w-full"><el-option v-for="item in categoryOptions" :key="item.value" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
                    <el-col :span="12"><el-form-item label="查询号码" required><el-select v-model="serviceForm.query_type" class="w-full"><el-option v-for="item in queryTypeOptions" :key="item.value" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
                </el-row>
                <el-form-item :label="currentEndpointLabel" required>
                    <el-input v-model.trim="serviceForm.endpoint_value" :placeholder="endpointPlaceholder" />
                </el-form-item>
                <el-row :gutter="16">
                    <el-col :span="8"><el-form-item label="参考成本"><el-input-number v-model="serviceForm.cost_price" :min="0" :precision="3" :step="0.1" controls-position="right" class="w-full" /></el-form-item></el-col>
                    <el-col :span="8"><el-form-item label="缓存天数"><el-input-number v-model="serviceForm.cache_days" :min="0" :max="3650" controls-position="right" class="w-full" /></el-form-item></el-col>
                    <el-col :span="8"><el-form-item label="排序"><el-input-number v-model="serviceForm.sort" :min="0" :max="99999" controls-position="right" class="w-full" /></el-form-item></el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12"><el-form-item label="结果类型"><el-select v-model="serviceForm.result_handler" class="w-full"><el-option v-for="item in resultHandlerOptions" :key="item.value" :label="item.label" :value="item.value" /></el-select></el-form-item></el-col>
                    <el-col :span="12"><el-form-item label="质检显示"><el-switch v-model="serviceForm.show_in_check" :active-value="1" :inactive-value="0" /></el-form-item></el-col>
                </el-row>
                <el-form-item label="启用服务"><el-switch v-model="serviceForm.enabled" :active-value="1" :inactive-value="0" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="serviceDialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="saveService">保存</el-button>
            </template>
        </el-dialog>
    </PremiumTheme>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { CircleCheck, Connection, Grid, Plus, Rank, Refresh, RefreshLeft, Search, Setting, View } from '@element-plus/icons-vue'
import Sortable from 'sortablejs'
import PremiumTheme from '@/addon/hsx_recycle/components/PremiumTheme.vue'
import PageHeader from '@/addon/hsx_recycle/components/PageHeader.vue'
import {
    getDeviceQueryChannelBalance,
    getDeviceQueryConfigCenter,
    saveDeviceQueryConfigCenter,
    testDeviceQueryConnection
} from '@/addon/hsx_recycle/api/device_query_config'

const fallbackOptions = {
    categories: [
        { label: '苹果', value: 'apple' },
        { label: '安卓', value: 'android' },
        { label: 'IMEI', value: 'imei' },
        { label: '其他', value: 'other' }
    ],
    query_types: [
        { label: 'IMEI', value: 'imei' },
        { label: '序列号', value: 'sn' },
        { label: 'IMEI / 序列号', value: 'imei_or_sn' },
        { label: '条码', value: 'barcode' },
        { label: 'IP 地址', value: 'ip' },
        { label: '手机号', value: 'phone' }
    ],
    result_handlers: [
        { label: '保修信息', value: 'coverage' },
        { label: '激活锁', value: 'activationlock' },
        { label: '监管锁', value: 'mdm' },
        { label: '通用结果', value: 'generic' }
    ],
    providers: [] as any[],
    provider_service_presets: {} as Record<string, any[]>
}

const loading = ref(false)
const saving = ref(false)
const balanceLoading = ref(false)
const activeProviderKey = ref('')
const keyword = ref('')
const categoryFilter = ref('all')
const statusFilter = ref<number | ''>('')
const sortDirty = ref(false)
const services = ref<any[]>([])
const channels = ref<any[]>([])
const mappings = ref<any[]>([])
const dictionaries = reactive<any>({ ...fallbackOptions })
const config = reactive<any>({ enabled: 1, cache_enabled: 1, default_cache_ttl: 2592000, default_channel_key: '' })

const providerDialogVisible = ref(false)
const serviceDialogVisible = ref(false)
const providerForm = reactive<any>({})
const serviceForm = reactive<any>({})
const serviceTableShellRef = ref<HTMLElement>()
let serviceSortable: Sortable | null = null

const unwrapData = (res: any) => {
    if (res?.data?.config || res?.data?.list) return res.data
    if (res?.data?.data?.config || res?.data?.data?.list) return res.data.data
    return res?.data || res || {}
}

const categoryOptions = computed(() => dictionaries.categories?.length ? dictionaries.categories : fallbackOptions.categories)
const queryTypeOptions = computed(() => dictionaries.query_types?.length ? dictionaries.query_types : fallbackOptions.query_types)
const resultHandlerOptions = computed(() => dictionaries.result_handlers?.length ? dictionaries.result_handlers : fallbackOptions.result_handlers)

const providerCards = computed(() => {
    const providerMap = new Map<string, any>()
    ;(dictionaries.providers || []).forEach((item: any) => providerMap.set(item.key, { ...item }))
    channels.value.forEach((item: any) => providerMap.set(item.key, { ...(providerMap.get(item.key) || {}), ...item }))
    return Array.from(providerMap.values()).map((provider: any) => {
        const providerMappings = mappings.value.filter(item => item.channel_key === provider.key)
        return {
            ...provider,
            enabled: Number(provider.enabled || 0),
            service_count: new Set(providerMappings.map(item => item.service_code)).size,
            enabled_count: new Set(providerMappings.filter(item => Number(item.enabled) === 1).map(item => item.service_code)).size
        }
    }).sort((a: any, b: any) => Number(b.priority || 0) - Number(a.priority || 0))
})

const currentProvider = computed<any>(() => providerCards.value.find((item: any) => item.key === activeProviderKey.value) || null)
const providerSecretReady = computed(() => {
    if (!currentProvider.value || currentProvider.value.auth_type === 'none') return true
    if (['gkdt_query', 'service_id_query'].includes(String(currentProvider.value.provider || ''))) {
        return String(currentProvider.value.appid || '').trim() !== '' && String(currentProvider.value.secret || '').trim() !== ''
    }
    return String(currentProvider.value.token || '').trim() !== ''
})
const isGkdtProviderForm = computed(() => ['gkdt_query', 'service_id_query'].includes(String(providerForm.provider || '')))

const providerServiceRows = computed(() => {
    const mappingMap = new Map<string, any>()
    mappings.value
        .filter(item => item.channel_key === activeProviderKey.value)
        .forEach(item => { if (!mappingMap.has(item.service_code)) mappingMap.set(item.service_code, item) })
    return services.value
        .filter(item => mappingMap.has(item.code))
        .map(item => ({ ...item, mapping: mappingMap.get(item.code) }))
        .sort((a, b) => Number(a.sort || 0) - Number(b.sort || 0) || String(a.name).localeCompare(String(b.name), 'zh-CN'))
})

const filteredRows = computed(() => {
    const searchText = keyword.value.toLowerCase()
    return providerServiceRows.value.filter(row => {
        if (categoryFilter.value !== 'all' && row.category !== categoryFilter.value) return false
        if (statusFilter.value !== '' && Number(row.mapping.enabled) !== Number(statusFilter.value)) return false
        if (!searchText) return true
        return [row.name, row.code, row.mapping.endpoint_value].some(value => String(value || '').toLowerCase().includes(searchText))
    })
})

const categoryTabs = computed(() => [
    { label: '全部服务', value: 'all', count: providerServiceRows.value.length },
    ...categoryOptions.value.map((item: any) => ({
        ...item,
        count: providerServiceRows.value.filter(row => row.category === item.value).length
    }))
])
const providerPresetRows = computed<any[]>(() => {
    const presets = dictionaries.provider_service_presets || {}
    return Array.isArray(presets[activeProviderKey.value]) ? presets[activeProviderKey.value] : []
})

const enabledProviderCount = computed(() => providerCards.value.filter((item: any) => item.enabled).length)
const enabledServiceCount = computed(() => providerServiceRows.value.filter(row => Number(row.mapping.enabled) === 1).length)
const visibleServiceCount = computed(() => providerServiceRows.value.filter(row => Number(row.mapping.enabled) === 1 && Number(row.show_in_check) === 1).length)
const dragDisabled = computed(() => loading.value || saving.value || Boolean(keyword.value || statusFilter.value !== '') || filteredRows.value.length < 2)
const sortHint = computed(() => {
    if (keyword.value || statusFilter.value !== '') return '清空搜索和状态筛选后可拖拽排序'
    if (filteredRows.value.length < 2) return '当前分类服务不足 2 项'
    return categoryFilter.value === 'all' ? '拖动左侧手柄调整全部顺序' : '拖动左侧手柄调整当前分类顺序'
})
const currentEndpointLabel = computed(() => currentProvider.value?.endpoint_label || '接口标识')
const endpointPlaceholder = computed(() => currentProvider.value?.endpoint_type === 'service_id' ? '填写服务商的服务 ID，例如 10101' : '填写接口路径，例如 /apple/coverage')

const optionLabel = (options: any[], value: string) => options.find(item => item.value === value)?.label || value || '-'
const money = (value: any) => Number(value || 0).toFixed(Number(value || 0) % 1 === 0 ? 2 : 3)
const requestErrorMessage = (error: any, fallback: string) => String(
    error?.msg || error?.message || error?.data?.msg || error?.data?.message || fallback
)

const loadData = async () => {
    loading.value = true
    try {
        const data = unwrapData(await getDeviceQueryConfigCenter())
        const sourceConfig = data.config || {}
        Object.assign(config, sourceConfig)
        services.value = Array.isArray(sourceConfig.services) ? sourceConfig.services : (data.list || [])
        channels.value = Array.isArray(sourceConfig.channels) ? sourceConfig.channels : []
        mappings.value = Array.isArray(sourceConfig.mappings) ? sourceConfig.mappings : []
        Object.assign(dictionaries, fallbackOptions, data.dictionaries || {})
        const keys = providerCards.value.map((item: any) => item.key)
        if (!keys.includes(activeProviderKey.value)) {
            activeProviderKey.value = keys.includes(config.default_channel_key) ? config.default_channel_key : (keys[0] || '')
        }
        sortDirty.value = false
    } finally {
        loading.value = false
    }
}

const persistConfig = async (message = '保存成功') => {
    saving.value = true
    try {
        await saveDeviceQueryConfigCenter({
            enabled: config.enabled,
            cache_enabled: config.cache_enabled,
            default_cache_ttl: config.default_cache_ttl,
            default_channel_key: config.default_channel_key,
            services: services.value,
            channels: channels.value,
            mappings: mappings.value
        })
        ElMessage.success(message)
    } finally {
        saving.value = false
    }
}

const normalizeProviderRecord = (provider: any) => ({
    key: String(provider?.key || ''),
    name: String(provider?.name || ''),
    provider: String(provider?.provider || ''),
    endpoint_type: String(provider?.endpoint_type || 'path'),
    endpoint_label: String(provider?.endpoint_label || ''),
    base_url: String(provider?.base_url || ''),
    method: String(provider?.method || 'POST').toUpperCase(),
    token: String(provider?.token || ''),
    appid: String(provider?.appid || ''),
    secret: String(provider?.secret || ''),
    style: String(provider?.style || '11'),
    auth_type: String(provider?.auth_type || 'bearer'),
    auth_key: String(provider?.auth_key || 'Authorization'),
    service_id_key: String(provider?.service_id_key || 'service_id'),
    priority: Number(provider?.priority || 0),
    enabled: Number(provider?.enabled || 0),
    timeout: Number(provider?.timeout || 300),
    connect_timeout: Number(provider?.connect_timeout || 10),
    balance_warning: Number(provider?.balance_warning || 20),
    verify_ssl: Number(provider?.verify_ssl ?? 1)
})

const ensureProviderStored = () => {
    const index = channels.value.findIndex(item => item.key === activeProviderKey.value)
    if (index >= 0) return index
    const provider = currentProvider.value
    if (!provider) return -1
    channels.value.push(normalizeProviderRecord(provider))
    return channels.value.length - 1
}

const saveGlobalStatus = () => persistConfig(config.enabled ? '查机功能已启用' : '查机功能已停用')

const saveProviderStatus = async (enabled: number) => {
    const index = ensureProviderStored()
    if (index < 0) return
    channels.value[index].enabled = Number(enabled)
    await persistConfig(Number(enabled) === 1 ? '服务商已启用' : '服务商已停用')
    await loadData()
}

const setDefaultProvider = async () => {
    config.default_channel_key = activeProviderKey.value
    await persistConfig('默认服务商已更新')
}

const openProviderDialog = () => {
    if (!currentProvider.value) return
    Object.keys(providerForm).forEach(key => delete providerForm[key])
    Object.assign(providerForm, currentProvider.value, {
        token: currentProvider.value.token || '',
        appid: currentProvider.value.appid || '',
        secret: currentProvider.value.secret || '',
        style: String(currentProvider.value.style || '11')
    })
    providerDialogVisible.value = true
}

const saveProvider = async () => {
    if (!providerForm.name || !providerForm.base_url) return ElMessage.warning('请填写服务商名称和服务地址')
    if (isGkdtProviderForm.value) {
        if (!String(providerForm.appid || '').trim() || !String(providerForm.secret || '').trim()) return ElMessage.warning('请填写爱查助手 AppID 和 Secret')
    } else if (providerForm.auth_type !== 'none' && !String(providerForm.token || '').trim()) {
        return ElMessage.warning('请填写服务商 API Key')
    }
    const index = channels.value.findIndex(item => item.key === providerForm.key)
    const record = normalizeProviderRecord(providerForm)
    if (index >= 0) channels.value[index] = { ...channels.value[index], ...record }
    else channels.value.push(record)
    await persistConfig('服务商设置已保存')
    providerDialogVisible.value = false
    await loadData()
}

const queryBalance = async () => {
    if (!currentProvider.value) return
    balanceLoading.value = true
    try {
        const res: any = await getDeviceQueryChannelBalance(currentProvider.value.key)
        const data = res?.data?.data || res?.data || res || {}
        ElMessage.success(`当前余额：¥${money(data.balance)}`)
    } catch (error: any) {
        ElMessage.error(requestErrorMessage(error, '余额查询失败'))
    } finally {
        balanceLoading.value = false
    }
}

const resetServiceForm = () => {
    Object.keys(serviceForm).forEach(key => delete serviceForm[key])
    const maxSort = Math.max(0, ...providerServiceRows.value.map(item => Number(item.sort || 0)))
    Object.assign(serviceForm, {
        _editing: false,
        code: '', name: '', category: 'other', query_type: 'imei', result_handler: 'generic',
        endpoint_value: '', cost_price: 0, cache_days: 30, sort: maxSort + 10,
        enabled: 1, show_in_check: 0
    })
}

const openServiceDialog = (row?: any) => {
    if (!currentProvider.value) return ElMessage.warning('请先选择服务商')
    resetServiceForm()
    if (row) {
        Object.assign(serviceForm, {
            _editing: true,
            code: row.code,
            name: row.name,
            category: row.category,
            query_type: row.query_type,
            result_handler: row.result_handler || 'generic',
            endpoint_value: row.mapping.endpoint_value,
            cost_price: Number(row.mapping.cost_price ?? row.cost_price ?? 0),
            cache_days: Math.round(Number(row.cache_ttl || 0) / 86400),
            sort: Number(row.sort || 0),
            enabled: Number(row.mapping.enabled || 0),
            show_in_check: Number(row.show_in_check || 0)
        })
    }
    serviceDialogVisible.value = true
}

const saveService = async () => {
    const code = String(serviceForm.code || '').trim()
    if (!serviceForm.name || !code || !serviceForm.endpoint_value) return ElMessage.warning('请补全服务名称、编码和接口标识')
    if (!/^[a-z][a-z0-9_]*$/i.test(code)) return ElMessage.warning('服务编码只能使用字母、数字和下划线，并以字母开头')
    const duplicate = mappings.value.some(item => item.channel_key === activeProviderKey.value && item.service_code === code)
    if (!serviceForm._editing && duplicate) return ElMessage.warning('当前服务商已存在相同服务编码')

    const serviceIndex = services.value.findIndex(item => item.code === code)
    const serviceRecord = {
        ...(serviceIndex >= 0 ? services.value[serviceIndex] : {}),
        code,
        name: serviceForm.name,
        category: serviceForm.category,
        query_type: serviceForm.query_type,
        result_handler: serviceForm.result_handler,
        cost_price: Number(serviceForm.cost_price || 0),
        cache_ttl: Number(serviceForm.cache_days || 0) * 86400,
        sort: Number(serviceForm.sort || 0),
        enabled: 1,
        show_in_check: Number(serviceForm.show_in_check || 0)
    }
    if (serviceIndex >= 0) services.value[serviceIndex] = serviceRecord
    else services.value.push(serviceRecord)

    const mappingIndex = mappings.value.findIndex(item => item.channel_key === activeProviderKey.value && item.service_code === code)
    const mappingRecord = {
        ...(mappingIndex >= 0 ? mappings.value[mappingIndex] : {}),
        service_code: code,
        channel_key: activeProviderKey.value,
        enabled: Number(serviceForm.enabled || 0),
        endpoint_type: currentProvider.value.endpoint_type || (['gkdt_query', 'service_id_query'].includes(currentProvider.value.provider) ? 'service_id' : 'path'),
        endpoint_value: serviceForm.endpoint_value,
        query_param: ['gkdt_query', 'service_id_query'].includes(currentProvider.value.provider) ? 'code' : serviceForm.query_type,
        cost_price: Number(serviceForm.cost_price || 0),
        retry_on: mappingIndex >= 0 ? (mappings.value[mappingIndex].retry_on || [410, 502, 503]) : [410, 502, 503],
        switch_on_404: mappingIndex >= 0 ? Number(mappings.value[mappingIndex].switch_on_404 || 0) : 0,
        switch_on_no_data: mappingIndex >= 0 ? Number(mappings.value[mappingIndex].switch_on_no_data || 0) : 0
    }
    if (mappingIndex >= 0) mappings.value[mappingIndex] = mappingRecord
    else mappings.value.push(mappingRecord)

    await persistConfig(serviceForm._editing ? '查询服务已更新' : '查询服务已添加')
    serviceDialogVisible.value = false
    await loadData()
}

const saveServiceSwitch = async (row: any, field: string) => {
    const serviceIndex = services.value.findIndex(item => item.code === row.code)
    const mappingIndex = mappings.value.findIndex(item => item.channel_key === activeProviderKey.value && item.service_code === row.code)
    if (field === 'show_in_check' && serviceIndex >= 0) services.value[serviceIndex].show_in_check = Number(row.show_in_check)
    if (field === 'enabled' && mappingIndex >= 0) mappings.value[mappingIndex].enabled = Number(row.mapping.enabled)
    await persistConfig('服务状态已更新')
}

const removeService = async (row: any) => {
    await ElMessageBox.confirm(`确定从 ${currentProvider.value.name} 删除“${row.name}”吗？`, '删除服务', { type: 'warning' })
    mappings.value = mappings.value.filter(item => !(item.channel_key === activeProviderKey.value && item.service_code === row.code))
    if (!mappings.value.some(item => item.service_code === row.code)) services.value = services.value.filter(item => item.code !== row.code)
    await persistConfig('查询服务已删除')
    await loadData()
}

const testService = async (row: any) => {
    if (!currentProvider.value) return ElMessage.warning('请先选择服务商')
    if (Number(currentProvider.value.enabled || 0) !== 1) {
        return ElMessage.warning(`请先启用并保存“${currentProvider.value.name}”服务商`)
    }
    if (!providerSecretReady.value) {
        return ElMessage.warning(`请先在服务商设置中完善“${currentProvider.value.name}”的账号密钥`)
    }
    if (Number(row?.mapping?.enabled || 0) !== 1) {
        return ElMessage.warning('请先启用该查询服务')
    }
    try {
        const { value } = await ElMessageBox.prompt(`输入用于测试“${row.name}”的${optionLabel(queryTypeOptions.value, row.query_type)}`, '测试查询服务', {
            confirmButtonText: '开始测试', cancelButtonText: '取消', inputPlaceholder: '请输入查询号码', inputPattern: /\S+/, inputErrorMessage: '查询号码不能为空'
        })
        const res: any = await testDeviceQueryConnection(row.code, { query_code: value, query_type: row.query_type, channel_key: activeProviderKey.value })
        const data = res?.data?.data || res?.data || res || {}
        ElMessage.success(`查询成功${data.response_time ? `，耗时 ${data.response_time}ms` : ''}`)
    } catch (error: any) {
        if (error === 'cancel' || error === 'close') return
        ElMessage.error({
            message: requestErrorMessage(error, '测试失败'),
            duration: 6000,
            showClose: true
        })
    }
}

const initServiceSortable = async () => {
    await nextTick()
    serviceSortable?.destroy()
    serviceSortable = null
    const tbody = serviceTableShellRef.value?.querySelector('.el-table__body-wrapper tbody')
    if (!tbody) return
    serviceSortable = Sortable.create(tbody, {
        handle: '.drag-handle',
        animation: 220,
        disabled: dragDisabled.value,
        ghostClass: 'service-row-ghost',
        chosenClass: 'service-row-chosen',
        onEnd: ({ oldIndex, newIndex }) => {
            if (oldIndex == null || newIndex == null || oldIndex === newIndex || dragDisabled.value) return
            const orderedRows = [...filteredRows.value]
            const movedRow = orderedRows.splice(oldIndex, 1)[0]
            if (!movedRow) return
            orderedRows.splice(newIndex, 0, movedRow)
            const sortSlots = filteredRows.value
                .map(row => Number(row.sort || 0))
                .sort((a, b) => a - b)
            orderedRows.forEach((row, index) => {
                const service = services.value.find(item => item.code === row.code)
                if (service) service.sort = sortSlots[index] ?? (index + 1) * 10
            })
            sortDirty.value = true
        }
    })
}

const saveSort = async () => {
    await persistConfig('服务排序已保存')
    sortDirty.value = false
    await loadData()
}

const restoreProviderDefaults = async () => {
    if (!currentProvider.value || !providerPresetRows.value.length) return
    await ElMessageBox.confirm(
        `将以系统字典重建“${currentProvider.value.name}”的服务目录；账号密钥和其他服务商配置不会改变。是否继续？`,
        '恢复默认目录',
        { type: 'warning', confirmButtonText: '恢复默认', cancelButtonText: '取消' }
    )

    const providerKey = activeProviderKey.value
    const previousCodes = new Set(
        mappings.value
            .filter(item => item.channel_key === providerKey)
            .map(item => String(item.service_code || ''))
            .filter(Boolean)
    )
    const presetCodes = new Set(
        providerPresetRows.value
            .map(item => String(item?.service?.code || item?.mapping?.service_code || ''))
            .filter(Boolean)
    )

    mappings.value = mappings.value.filter(item => item.channel_key !== providerKey)
    services.value = services.value.filter(service => {
        const code = String(service.code || '')
        if (!previousCodes.has(code) || presetCodes.has(code)) return true
        return mappings.value.some(mapping => String(mapping.service_code || '') === code)
    })

    providerPresetRows.value.forEach(item => {
        const service = { ...(item.service || {}) }
        const mapping = { ...(item.mapping || {}), channel_key: providerKey, enabled: 1 }
        const code = String(service.code || mapping.service_code || '')
        if (!code) return
        service.code = code
        mapping.service_code = code
        const serviceIndex = services.value.findIndex(row => String(row.code || '') === code)
        if (serviceIndex >= 0) services.value[serviceIndex] = { ...services.value[serviceIndex], ...service }
        else services.value.push(service)
        mappings.value.push(mapping)
    })

    categoryFilter.value = 'all'
    keyword.value = ''
    statusFilter.value = ''
    await persistConfig(`${currentProvider.value.name}默认目录已恢复`)
    await loadData()
}

watch(dragDisabled, disabled => serviceSortable?.option('disabled', disabled))
watch(
    [activeProviderKey, keyword, categoryFilter, statusFilter, () => providerServiceRows.value.map(row => row.code).join('|')],
    initServiceSortable,
    { flush: 'post' }
)

onMounted(async () => {
    await loadData()
    await initServiceSortable()
})
onBeforeUnmount(() => serviceSortable?.destroy())
</script>

<style scoped lang="scss">
.device-query-page { padding: 20px; }
.header-actions { display: flex; align-items: center; gap: 10px; }
.global-switch-label { color: #64748b; font-size: 13px; }
.summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-top: 20px; }
.summary-item { display: flex; align-items: center; gap: 13px; min-height: 92px; padding: 16px 18px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; }
.summary-icon { display: flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 10px; font-size: 20px; }
.summary-icon.is-provider { color: #2563eb; background: #eff6ff; }.summary-icon.is-service { color: #7c3aed; background: #f5f3ff; }.summary-icon.is-active { color: #16a34a; background: #f0fdf4; }.summary-icon.is-check { color: #d97706; background: #fffbeb; }
.summary-item span { display: block; color: #64748b; font-size: 12px; }.summary-item strong { display: block; margin-top: 5px; color: #111827; font-size: 24px; line-height: 1; }.summary-item em { color: #94a3b8; font-size: 14px; font-style: normal; font-weight: 500; }
.provider-section, .catalog-section { margin-top: 26px; }
.section-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 13px; }.section-heading h2 { margin: 0; color: #111827; font-size: 16px; }.section-heading p { margin: 5px 0 0; color: #64748b; font-size: 12px; line-height: 1.6; }
.provider-grid { display: grid; grid-template-columns: repeat(3, minmax(220px, 1fr)); gap: 12px; }
.provider-card { display: flex; align-items: center; gap: 12px; min-height: 78px; padding: 14px; border: 1px solid #e5e7eb; border-radius: 9px; color: inherit; background: #fff; cursor: pointer; text-align: left; transition: .18s ease; }.provider-card:hover { border-color: #93c5fd; box-shadow: 0 6px 18px rgba(15, 23, 42, .06); }.provider-card.active { border-color: var(--el-color-primary); background: #f8fbff; box-shadow: 0 0 0 1px rgba(37, 99, 235, .08); }
.provider-mark, .provider-logo { display: flex; flex: 0 0 auto; align-items: center; justify-content: center; color: #2563eb; background: #eaf2ff; font-weight: 700; }.provider-mark { width: 40px; height: 40px; border-radius: 9px; font-size: 16px; }.provider-logo { width: 46px; height: 46px; border-radius: 10px; font-size: 18px; }
.provider-content { min-width: 0; flex: 1; }.provider-name-row { display: flex; align-items: center; gap: 7px; }.provider-name-row strong { overflow: hidden; color: #111827; font-size: 14px; text-overflow: ellipsis; white-space: nowrap; }.provider-meta { display: block; margin-top: 6px; color: #94a3b8; font-size: 12px; }
.provider-state { display: inline-flex; flex: 0 0 auto; align-items: center; gap: 5px; color: #94a3b8; font-size: 12px; }.provider-state i { width: 7px; height: 7px; border-radius: 50%; background: #cbd5e1; }.provider-state.is-on { color: #15803d; }.provider-state.is-on i { background: #22c55e; }
.provider-console { display: flex; align-items: center; justify-content: space-between; gap: 20px; margin-top: 14px; padding: 17px 18px; border: 1px solid #dbe6f4; border-radius: 10px; background: #f8fbff; }.provider-console-main { display: flex; min-width: 0; align-items: center; gap: 13px; }.provider-summary { min-width: 0; }.provider-title-line { display: flex; align-items: center; gap: 8px; }.provider-title-line h2 { margin: 0; color: #111827; font-size: 16px; }.provider-summary p { overflow: hidden; max-width: 620px; margin: 6px 0 0; color: #64748b; font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }.provider-actions, .catalog-actions { display: flex; flex: 0 0 auto; align-items: center; gap: 8px; }
.catalog-heading { align-items: center; }.service-category-tabs { margin-bottom: 12px; }.service-category-tabs :deep(.el-tabs__header) { margin: 0; }.service-category-tabs :deep(.el-tabs__nav-wrap::after) { height: 1px; background: #eef2f6; }.service-category-tabs :deep(.el-tabs__item) { height: 44px; padding: 0 22px; color: #64748b; }.service-category-tabs :deep(.el-tabs__item.is-active) { color: var(--el-color-primary); font-weight: 600; }.category-tab-label { display: inline-flex; align-items: center; gap: 7px; }.category-tab-label em { min-width: 20px; padding: 1px 6px; border-radius: 10px; color: #94a3b8; background: #f1f5f9; font-size: 11px; font-style: normal; line-height: 18px; text-align: center; }.service-category-tabs :deep(.el-tabs__item.is-active) .category-tab-label em { color: var(--el-color-primary); background: #eff6ff; }.filter-bar { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; padding: 12px; border: 1px solid #eef2f6; border-radius: 8px; background: #f8fafc; }.keyword-input { width: 340px; }.filter-select { width: 150px; }.filter-result { margin-left: auto; color: #94a3b8; font-size: 12px; }
.service-table { width: 100%; border-top: 1px solid #eef2f6; }.service-name strong, .service-name span { display: block; }.service-name strong { color: #111827; font-size: 14px; font-weight: 600; }.service-name span { margin-top: 4px; color: #94a3b8; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; }.endpoint-value { color: #475569; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; }.sort-actions { display: inline-flex; align-items: center; justify-content: center; gap: 8px; }.sort-actions span { min-width: 30px; color: #64748b; font-size: 12px; text-align: center; }.drag-handle { color: #64748b; font-size: 18px; cursor: grab; transition: color .15s ease; }.drag-handle:hover { color: var(--el-color-primary); }.drag-handle:active { cursor: grabbing; }.drag-handle.is-disabled { color: #cbd5e1; cursor: not-allowed; }.service-table :deep(.service-row-ghost td) { background: #eff6ff !important; }.service-table :deep(.service-row-chosen td) { box-shadow: inset 3px 0 0 var(--el-color-primary); }
.dialog-alert { margin-bottom: 18px; }.dialog-form { padding: 2px 8px 0; }.field-tip { margin-top: 6px; color: #94a3b8; font-size: 12px; line-height: 1.5; }
@media (max-width: 1200px) { .summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }.provider-grid { grid-template-columns: repeat(2, minmax(220px, 1fr)); }.provider-console { align-items: flex-start; flex-direction: column; }.provider-actions { width: 100%; justify-content: flex-end; } }
</style>
