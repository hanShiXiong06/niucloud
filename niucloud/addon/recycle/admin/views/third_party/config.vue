<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <div class="flex justify-between items-center">
                <div>
                    <span class="text-page-title">服务能力中心</span>
                    <div class="text-[13px] text-gray-500 mt-[6px]">配置、监测和诊断回收业务依赖的第三方 API。</div>
                </div>
                <div>
                    <el-button :loading="overviewLoading" @click="loadOverview">刷新状态</el-button>
                    <el-button @click="loadDefault">恢复默认</el-button>
                    <el-button type="primary" :loading="saving" @click="saveConfig">保存配置</el-button>
                </div>
            </div>

            <div class="capability-summary mt-[20px]" v-loading="overviewLoading">
                <div class="summary-metric">
                    <div class="metric-label">今日总调用</div>
                    <div class="metric-value">{{ overview.today.total_calls || 0 }}</div>
                </div>
                <div class="summary-metric">
                    <div class="metric-label">成功</div>
                    <div class="metric-value success">{{ overview.today.success_calls || 0 }}</div>
                </div>
                <div class="summary-metric">
                    <div class="metric-label">失败</div>
                    <div class="metric-value danger">{{ overview.today.failed_calls || 0 }}</div>
                </div>
                <div class="summary-metric">
                    <div class="metric-label">成功率</div>
                    <div class="metric-value">{{ overview.today.success_rate || 0 }}%</div>
                </div>
            </div>

            <div class="capability-grid mt-[16px]">
                <div
                    v-for="item in overview.capabilities"
                    :key="item.key"
                    class="capability-card"
                    :class="{ active: activeTab === item.key }"
                    @click="activeTab = item.key"
                >
                    <div class="card-head">
                        <div>
                            <div class="capability-name">{{ item.name }}</div>
                            <div class="capability-provider">{{ item.provider_label }}</div>
                        </div>
                        <el-tag :type="statusMeta(item.status).type" effect="light">{{ statusMeta(item.status).label }}</el-tag>
                    </div>

                    <div class="card-stats">
                        <div>
                            <span>今日调用</span>
                            <strong>{{ item.today?.total_calls || 0 }}</strong>
                        </div>
                        <div>
                            <span>失败</span>
                            <strong :class="{ danger: (item.today?.failed_calls || 0) > 0 }">{{ item.today?.failed_calls || 0 }}</strong>
                        </div>
                        <div>
                            <span>均耗时</span>
                            <strong>{{ item.today?.avg_duration || 0 }}ms</strong>
                        </div>
                    </div>

                    <div class="capability-actions">
                        <el-tag v-for="action in item.actions" :key="action" size="small">{{ action }}</el-tag>
                    </div>

                    <div v-if="item.missing_fields?.length" class="capability-warning">
                        缺少配置：{{ item.missing_fields.join('、') }}
                    </div>
                    <div v-else-if="item.last_call?.error_msg" class="capability-warning">
                        最近失败：{{ item.last_call.error_msg }}
                    </div>
                    <div v-else class="capability-last">
                        最近调用：{{ item.last_call?.create_at ? formatTime(item.last_call.create_at) : '暂无调用' }}
                    </div>
                </div>
            </div>

            <el-tabs v-model="activeTab" class="mt-[20px]">
                <el-tab-pane label="亿速快递" name="express_order">
                    <div class="tab-intro">
                        <div class="intro-title">快递发件能力</div>
                        <div class="intro-text">用于回收订单发件、取消订单、轨迹查询和余额查询。当前只保留亿速快递，安果不再作为新流程能力。</div>
                    </div>
                    <div class="express-flow">
                        <div class="flow-step">
                            <div class="step-index">1</div>
                            <div class="step-body">
                                <div class="step-title">账号与接口配置</div>
                                <el-form :model="form.express_order" label-width="110px" class="express-config-form">
                                    <el-row :gutter="12">
                                        <el-col :span="6">
                                            <el-form-item label="启用">
                                                <el-switch v-model="form.express_order.enabled" :active-value="1" :inactive-value="0" />
                                            </el-form-item>
                                        </el-col>
                                        <el-col :span="6">
                                            <el-form-item label="服务商">
                                                <el-select v-model="form.express_order.provider" disabled>
                                                    <el-option label="亿速快递" value="yisu" />
                                                </el-select>
                                            </el-form-item>
                                        </el-col>
                                        <el-col :span="12">
                                            <el-form-item label="接口地址">
                                                <el-input v-model="form.express_order.yisu.base_url" placeholder="http://open.yisuopen.com" />
                                            </el-form-item>
                                        </el-col>
                                    </el-row>
                                    <el-form-item label="回调地址">
                                        <el-input v-model="form.express_order.yisu.callback_url" placeholder="https://gl.hsxbk.top/api/tk_jhkd/yisunotice" />
                                    </el-form-item>
                                    <el-row :gutter="12">
                                        <el-col :span="8">
                                            <el-form-item label="AppID">
                                                <el-input v-model="form.express_order.yisu.appid" />
                                            </el-form-item>
                                        </el-col>
                                        <el-col :span="8">
                                            <el-form-item label="AppSecret">
                                                <el-input v-model="form.express_order.yisu.app_secret" type="password" show-password />
                                            </el-form-item>
                                        </el-col>
                                        <el-col :span="4">
                                            <el-form-item label="版本号">
                                                <el-input v-model="form.express_order.yisu.version" />
                                            </el-form-item>
                                        </el-col>
                                        <el-col :span="4">
                                            <el-form-item label="超时">
                                                <el-input-number v-model="form.express_order.yisu.timeout" :min="1" :max="120" />
                                            </el-form-item>
                                        </el-col>
                                    </el-row>
                                    <el-collapse>
                                        <el-collapse-item title="接口路径配置" name="api-paths">
                                            <el-row :gutter="12">
                                                <el-col v-for="item in expressApiPathFields" :key="item.key" :span="8">
                                                    <el-form-item :label="item.label">
                                                        <el-input v-model="form.express_order.yisu.api_paths[item.key]" />
                                                    </el-form-item>
                                                </el-col>
                                            </el-row>
                                        </el-collapse-item>
                                    </el-collapse>
                                </el-form>
                            </div>
                        </div>

                        <div class="flow-step">
                            <div class="step-index">2</div>
                            <div class="step-body">
                                <div class="step-head">
                                    <div>
                                        <div class="step-title">快递产品配置</div>
                                        <div class="step-desc">启用后的产品会进入报价和下单流程，建议只打开常用产品，减少用户选择成本。</div>
                                    </div>
                                    <el-button type="primary" :loading="yisuProductSaving" @click="saveYisuProducts">保存产品</el-button>
                                </div>
                                <el-tabs v-model="activeProductType">
                                    <el-tab-pane label="快递" name="快递" />
                                    <el-tab-pane label="重货" name="重货" />
                                    <el-tab-pane label="得物" name="得物" />
                                </el-tabs>
                                <el-table v-loading="yisuProductLoading" :data="filteredYisuProducts" size="small" border>
                                    <el-table-column prop="product_code" label="代码" width="80" />
                                    <el-table-column prop="product_name" label="产品" min-width="140" />
                                    <el-table-column label="启用" width="90">
                                        <template #default="{ row }">
                                            <el-switch v-model="row.status" :active-value="1" :inactive-value="0" />
                                        </template>
                                    </el-table-column>
                                    <el-table-column label="排序" width="130">
                                        <template #default="{ row }">
                                            <el-input-number v-model="row.sort" :min="0" :max="999" size="small" />
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </div>
                        </div>

                        <div class="flow-step">
                            <div class="step-index">3</div>
                            <div class="step-body">
                                <div class="step-head">
                                    <div>
                                        <div class="step-title">运单工作台</div>
                                        <div class="step-desc">日常发件、报价、下单、取消/拦截、获取面单和查看订单流程，都在快递运单记录中完成。</div>
                                    </div>
                                    <div class="flex gap-[8px]">
                                        <el-button :loading="expressConsoleLoading.fund" @click="runExpressFund">查询资金</el-button>
                                        <el-button type="primary" @click="goExpressWorkbench">进入运单记录</el-button>
                                    </div>
                                </div>

                                <div v-if="expressFund" class="fund-strip">
                                    <div><span>账户余额</span><strong>¥{{ expressFund.balance || 0 }}</strong></div>
                                    <div><span>推广费</span><strong>¥{{ expressFund.commission || 0 }}</strong></div>
                                    <div><span>积分</span><strong>{{ expressFund.integral || 0 }}</strong></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </el-tab-pane>

                <el-tab-pane label="快递查询" name="express_query">
                    <div class="tab-intro">
                        <div class="intro-title">物流轨迹查询能力</div>
                        <div class="intro-text">用于查询用户寄件、平台发件和退回物流轨迹。客户能在这里确认查询服务是否可用。</div>
                    </div>
                    <el-form :model="form.express_query" label-width="150px" class="max-w-[760px]">
                        <el-form-item label="启用">
                            <el-switch v-model="form.express_query.enabled" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                        <el-form-item label="服务商">
                            <el-select v-model="form.express_query.provider" disabled>
                                <el-option label="阿里云市场快递查询" value="ali_express" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="接口域名">
                            <el-input v-model="form.express_query.ali_express.base_url" placeholder="https://kzexpress.market.alicloudapi.com" />
                        </el-form-item>
                        <el-form-item label="接口路径">
                            <el-input v-model="form.express_query.ali_express.api_path" placeholder="/api-mall/api/express/query" />
                        </el-form-item>
                        <el-form-item label="AppCode">
                            <el-input v-model="form.express_query.ali_express.api_key" type="password" show-password />
                        </el-form-item>
                        <el-form-item label="超时时间">
                            <el-input-number v-model="form.express_query.ali_express.timeout" :min="1" :max="120" />
                        </el-form-item>
                    </el-form>
                </el-tab-pane>

                <el-tab-pane label="地址解析" name="address_parse">
                    <div class="tab-intro">
                        <div class="intro-title">客户地址解析能力</div>
                        <div class="intro-text">用于发快递时识别客户姓名、手机号、省市区和详细地址，并和系统地区数据匹配。</div>
                    </div>
                    <el-form :model="form.address_parse" label-width="150px" class="max-w-[860px]">
                        <el-form-item label="启用">
                            <el-switch v-model="form.address_parse.enabled" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                        <el-form-item label="服务商">
                            <el-select v-model="form.address_parse.provider" disabled>
                                <el-option label="腾讯云市场地址解析" value="tencent_cloud_market_address" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="接口域名">
                            <el-input v-model="form.address_parse.tencent_cloud_market_address.base_url" placeholder="https://ap-guangzhou.cloudmarket-apigw.com" />
                        </el-form-item>
                        <el-form-item label="接口路径">
                            <el-input v-model="form.address_parse.tencent_cloud_market_address.api_path" placeholder="/service-3qtg8hpi/identify_address" />
                        </el-form-item>
                        <el-form-item label="SecretId">
                            <el-input v-model="form.address_parse.tencent_cloud_market_address.secret_id" type="password" show-password />
                        </el-form-item>
                        <el-form-item label="SecretKey">
                            <el-input v-model="form.address_parse.tencent_cloud_market_address.secret_key" type="password" show-password />
                        </el-form-item>
                        <el-form-item label="超时时间">
                            <el-input-number v-model="form.address_parse.tencent_cloud_market_address.timeout" :min="1" :max="60" />
                        </el-form-item>
                    </el-form>
                </el-tab-pane>

                <el-tab-pane label="设备查询" name="device_query">
                    <div class="tab-intro">
                        <div class="intro-title">设备查询能力</div>
                        <div class="intro-text">用于后台质检时查询 IMEI、SN、保修、激活锁和其他设备辅助信息。查询项、渠道、接口路径、服务 ID 和质检弹窗按钮都在设备查询配置中管理。</div>
                    </div>
                    <div class="device-query-entry">
                        <div class="device-query-entry__main">
                            <div class="entry-title">设备查询配置工作台</div>
                            <div class="entry-desc">在这里维护可查询项目、第三方渠道、接口映射和成本。打开“质检显示”后，按钮会自动出现在设备质检弹窗里，不需要再改前端代码。</div>
                            <div class="entry-tags">
                                <el-tag effect="plain">查询项开关</el-tag>
                                <el-tag effect="plain" type="success">3023 路径接口</el-tag>
                                <el-tag effect="plain" type="warning">爱查服务 ID</el-tag>
                                <el-tag effect="plain" type="info">成本记录</el-tag>
                            </div>
                        </div>
                        <div class="device-query-entry__actions">
                            <el-button type="primary" @click="goDeviceQueryConfig">进入设备查询配置</el-button>
                            <el-button @click="goDeviceQueryResult">查看查询结果</el-button>
                        </div>
                    </div>
                </el-tab-pane>

                <el-tab-pane label="芯烨云打印" name="printer">
                    <div class="tab-intro">
                        <div class="intro-title">云打印能力</div>
                        <div class="intro-text">用于设备标签、订单标签和模板打印。打印机账号、打印机绑定和模板仍在打印模块里管理。</div>
                    </div>
                    <el-form :model="form.printer" label-width="150px" class="max-w-[760px]">
                        <el-form-item label="启用">
                            <el-switch v-model="form.printer.enabled" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                        <el-form-item label="服务商">
                            <el-select v-model="form.printer.provider" disabled>
                                <el-option label="芯烨云" value="xpyun" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="接口地址">
                            <el-input v-model="form.printer.xpyun.base_url" placeholder="https://open.xpyun.net/api/openapi" />
                        </el-form-item>
                        <el-form-item label="标签打印路径">
                            <el-input v-model="form.printer.xpyun.print_label_path" placeholder="/xprinter/printLabel" />
                        </el-form-item>
                        <el-form-item label="超时时间">
                            <el-input-number v-model="form.printer.xpyun.timeout" :min="1" :max="120" />
                        </el-form-item>
                        <el-form-item label="连接超时">
                            <el-input-number v-model="form.printer.xpyun.connect_timeout" :min="1" :max="60" />
                        </el-form-item>
                    </el-form>
                </el-tab-pane>

            </el-tabs>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { apiThirdPartyConfig, apiThirdPartyConfigDefault, apiThirdPartyConfigOverview, apiThirdPartyConfigSave } from '@/addon/recycle/api/third_party'
import { batchUpdateYisuProduct, getYisuProductList } from '@/addon/recycle/api/yisu'
import { getExpressFund } from '@/addon/recycle/api/express'

const router = useRouter()
const activeTab = ref('express_order')
const saving = ref(false)
const overviewLoading = ref(false)
const overview = reactive<any>({
    today: {
        total_calls: 0,
        success_calls: 0,
        failed_calls: 0,
        success_rate: 0
    },
    capabilities: []
})

const defaultForm = {
    express_order: {
        enabled: 1,
        provider: 'yisu',
        yisu: {
            base_url: 'http://open.yisuopen.com',
            appid: '',
            app_secret: '',
            version: 'V1.0',
            timeout: 30,
            callback_url: 'https://gl.hsxbk.top/api/tk_jhkd/yisunotice',
            api_paths: {
                quote: '/openApi/getPrice',
                create: '/openApi/doOrder',
                cancel: '/openApi/doCancel',
                modify: '/openApi/doModify',
                detail: '/openApi/getOrderDetail',
                waybillPdf: '/openApi/getWaybillPdf',
                fund: '/openApi/fund'
            }
        }
    },
    express_query: {
        enabled: 1,
        provider: 'ali_express',
        ali_express: {
            base_url: 'https://kzexpress.market.alicloudapi.com',
            api_key: '',
            api_path: '/api-mall/api/express/query',
            timeout: 30
        }
    },
    address_parse: {
        enabled: 1,
        provider: 'tencent_cloud_market_address',
        tencent_cloud_market_address: {
            base_url: 'https://ap-guangzhou.cloudmarket-apigw.com',
            api_path: '/service-3qtg8hpi/identify_address',
            secret_id: '',
            secret_key: '',
            timeout: 10
        }
    },
    device_query: {
        enabled: 1,
        provider: '3023',
        '3023': {
            base_url: 'http://api.3023data.com',
            api_key: '',
            timeout: 30
        }
    },
    printer: {
        enabled: 1,
        provider: 'xpyun',
        xpyun: {
            base_url: 'https://open.xpyun.net/api/openapi',
            print_label_path: '/xprinter/printLabel',
            timeout: 30,
            connect_timeout: 10
        }
    }
}

const clone = (data: Record<string, any>) => JSON.parse(JSON.stringify(data))
const mergeDeep = (target: Record<string, any>, source: Record<string, any>) => {
    const result = clone(target)
    Object.keys(source || {}).forEach((key) => {
        if (
            source[key]
            && typeof source[key] === 'object'
            && !Array.isArray(source[key])
            && result[key]
            && typeof result[key] === 'object'
            && !Array.isArray(result[key])
        ) {
            result[key] = mergeDeep(result[key], source[key])
        } else {
            result[key] = source[key]
        }
    })
    return result
}

const form = reactive<any>(clone(defaultForm))
const expressFund = ref<any>(null)
const activeProductType = ref('快递')
const yisuProductList = ref<any[]>([])
const yisuProductLoading = ref(false)
const yisuProductSaving = ref(false)
const expressConsoleLoading = reactive<Record<string, boolean>>({
    fund: false
})

const expressApiPathFields = [
    { key: 'quote', label: '预估运费' },
    { key: 'create', label: '运单下单' },
    { key: 'cancel', label: '取消/拦截' },
    { key: 'modify', label: '运单修改' },
    { key: 'detail', label: '运单详情' },
    { key: 'waybillPdf', label: '面单PDF' },
    { key: 'fund', label: '资金信息' }
]

const filteredYisuProducts = computed(() => {
    return yisuProductList.value.filter(item => (item.express_type || '快递') === activeProductType.value)
})

const assignForm = (data: Record<string, any>) => {
    Object.assign(form, mergeDeep(defaultForm, data || {}))
}

const loadOverview = async () => {
    overviewLoading.value = true
    try {
        const res = await apiThirdPartyConfigOverview()
        Object.assign(overview, {
            today: res.data?.today || {},
            capabilities: res.data?.capabilities || []
        })
    } finally {
        overviewLoading.value = false
    }
}

const loadConfig = async () => {
    const res = await apiThirdPartyConfig()
    assignForm(res.data)
}

const loadDefault = async () => {
    try {
        await ElMessageBox.confirm('恢复默认只会重置页面表单，保存后才会生效。确定恢复默认配置吗？', '提示', {
            type: 'warning'
        })
        const res = await apiThirdPartyConfigDefault()
        assignForm(res.data)
    } catch (error) {}
}

const saveConfig = async () => {
    saving.value = true
    try {
        await apiThirdPartyConfigSave(form)
        ElMessage.success('保存成功')
        await loadConfig()
        await loadOverview()
    } finally {
        saving.value = false
    }
}

const loadYisuProducts = async () => {
    yisuProductLoading.value = true
    try {
        const res = await getYisuProductList()
        yisuProductList.value = res.data || []
    } finally {
        yisuProductLoading.value = false
    }
}

const saveYisuProducts = async () => {
    yisuProductSaving.value = true
    try {
        const products = yisuProductList.value.map(item => ({
            product_code: item.product_code,
            product_name: item.product_name,
            logo: item.logo || '',
            status: item.status,
            sort: item.sort || 0
        }))
        await batchUpdateYisuProduct({ products })
        ElMessage.success('产品配置已保存')
        await loadYisuProducts()
    } finally {
        yisuProductSaving.value = false
    }
}

const runExpressFund = async () => {
    expressConsoleLoading.fund = true
    try {
        const res = await getExpressFund()
        expressFund.value = res.data || {}
        ElMessage.success('资金查询成功')
        await loadOverview()
    } finally {
        expressConsoleLoading.fund = false
    }
}

const goExpressWorkbench = () => {
    router.push('/express/order_record')
}

const goDeviceQueryConfig = () => {
    router.push('/device_query/config')
}

const goDeviceQueryResult = () => {
    router.push('/device_query/result')
}


const statusMeta = (status: string) => {
    const map: Record<string, { label: string, type: '' | 'success' | 'warning' | 'info' | 'danger' }> = {
        running: { label: '运行中', type: 'success' },
        ready: { label: '可用', type: 'success' },
        error: { label: '最近失败', type: 'danger' },
        incomplete: { label: '待配置', type: 'warning' },
        disabled: { label: '已停用', type: 'info' }
    }
    return map[status] || { label: '未知', type: 'info' }
}

const formatTime = (time: number) => {
    if (!time) return '暂无'
    let value: any = time
    if (typeof value === 'string' && /^\d+$/.test(value)) value = Number(value)
    const date = typeof value === 'number'
        ? new Date(value < 1000000000000 ? value * 1000 : value)
        : new Date(value)
    if (Number.isNaN(date.getTime())) return '暂无'
    const pad = (num: number) => String(num).padStart(2, '0')
    return `${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

onMounted(() => {
    loadConfig()
    loadOverview()
    loadYisuProducts()
})
</script>

<style lang="scss" scoped>
.capability-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

.summary-metric {
    border: 1px solid #ebeef5;
    border-radius: 8px;
    padding: 14px 16px;
    background: #fff;
}

.metric-label {
    color: #606266;
    font-size: 13px;
}

.metric-value {
    margin-top: 8px;
    color: #1f2937;
    font-size: 24px;
    font-weight: 700;
    line-height: 1;
}

.success {
    color: #16a34a;
}

.danger {
    color: #dc2626;
}

.capability-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 12px;
}

.capability-card {
    min-height: 180px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 14px;
    background: #fff;
    cursor: pointer;
    transition: border-color .2s, box-shadow .2s;
}

.capability-card:hover,
.capability-card.active {
    border-color: var(--el-color-primary);
    box-shadow: 0 8px 24px rgba(31, 41, 55, .08);
}

.card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}

.capability-name {
    color: #111827;
    font-size: 15px;
    font-weight: 700;
}

.capability-provider {
    margin-top: 4px;
    color: #6b7280;
    font-size: 12px;
}

.card-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
    margin-top: 16px;
}

.card-stats span {
    display: block;
    color: #6b7280;
    font-size: 12px;
}

.card-stats strong {
    display: block;
    margin-top: 5px;
    color: #111827;
    font-size: 16px;
}

.capability-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 14px;
}

.capability-warning {
    margin-top: 12px;
    color: #b45309;
    font-size: 12px;
    line-height: 1.5;
}

.capability-last {
    margin-top: 12px;
    color: #6b7280;
    font-size: 12px;
}

.tab-intro {
    max-width: 860px;
    margin-bottom: 18px;
    padding: 14px 16px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #f9fafb;
}

.intro-title {
    color: #111827;
    font-size: 15px;
    font-weight: 700;
}

.intro-text {
    margin-top: 6px;
    color: #6b7280;
    font-size: 13px;
    line-height: 1.6;
}

.express-flow {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.flow-step {
    display: grid;
    grid-template-columns: 34px minmax(0, 1fr);
    gap: 12px;
    max-width: 1280px;
}

.step-index {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    color: #fff;
    font-weight: 700;
    background: var(--el-color-primary);
    border-radius: 50%;
}

.step-body {
    padding: 16px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fff;
}

.step-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
}

.step-title {
    color: #111827;
    font-size: 15px;
    font-weight: 700;
}

.step-desc {
    margin-top: 5px;
    color: #6b7280;
    font-size: 13px;
}

.fund-strip {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}

.fund-strip > div {
    padding: 12px;
    border: 1px solid #ebeef5;
    border-radius: 8px;
    background: #f9fafb;
}

.fund-strip span {
    display: block;
    color: #6b7280;
    font-size: 12px;
}

.fund-strip strong {
    display: block;
    margin-top: 6px;
    color: #111827;
    font-size: 20px;
}

.device-query-entry {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    max-width: 980px;
    padding: 18px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fff;
}

.device-query-entry__main {
    min-width: 0;
}

.entry-title {
    color: #111827;
    font-size: 16px;
    font-weight: 700;
}

.entry-desc {
    max-width: 680px;
    margin-top: 8px;
    color: #606266;
    font-size: 13px;
    line-height: 1.7;
}

.entry-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 14px;
}

.device-query-entry__actions {
    display: flex;
    flex-shrink: 0;
    gap: 8px;
}

.express-config-form :deep(.el-input-number) {
    width: 100%;
}

@media (max-width: 1280px) {
    .capability-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 900px) {
    .capability-summary,
    .capability-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .device-query-entry,
    .device-query-entry__actions {
        flex-direction: column;
    }
}
</style>
