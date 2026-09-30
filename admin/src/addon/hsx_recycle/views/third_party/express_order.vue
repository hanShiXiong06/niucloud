<template>
    <ThirdPartyServiceShell title="上门取件服务" description="管理员固定渠道、快递公司和付款方式；客户提交回收单后自动预约，不用选择快递。"
        :provider-label="savedProviderLabel"
        :capability="readableCapability" :loading="loading" :saving="saving" :testing="testing" :testable="false"
        :features="['固定承运商，不自动换商', '预约与回收订单状态分别记录', '失败可处理，未知结果不重复叫件']"
        :steps="['按开通指引准备本站账号', '从官方目录选择公司和产品', '配置框架通知与公开回调', '保存检查，再做沙箱和实单验收']"
        @refresh="refreshConfig" @save="saveConfig">
        <el-alert v-if="configDirty" title="有未保存的取件配置，尚未生效。上方服务状态与统计对应已保存的渠道；请保存后再检查。" type="warning" :closable="false" show-icon class="draft-alert" />
        <el-alert v-else-if="!configLoaded" title="尚未成功读取本站配置，请刷新后再编辑或保存。" type="warning" :closable="false" show-icon class="draft-alert" />
        <el-form label-position="top" class="service-form" :disabled="configBusy || !configLoaded">
            <div class="section-heading"><div><h3>1. 新预约使用哪个渠道</h3><p>更换只影响新预约，已有订单仍沿原渠道处理。请保留原账号凭证。</p></div><el-switch v-model="form.enabled" :active-value="1" :inactive-value="0" active-text="启用新预约" /></div>
            <el-form-item label="接入渠道"><el-select v-model="form.provider" class="!w-full"><el-option label="快递100上门取件" value="kuaidi100" /><el-option label="易速（保留原渠道）" value="yisu" /></el-select></el-form-item>
            <template v-if="form.provider === 'kuaidi100'">
                <div class="onboarding-banner"><div><strong>第一次配置？按指引完成，不需要猜产品名称</strong><p>各站点使用自己的账号。选择产品不会开通账号，也不会发起寄件。</p></div><el-button type="primary" plain @click="guideVisible = true">查看完整接入指引</el-button></div>
                <div class="section-heading next"><div><h3>2. 本站账号与服务模式</h3><p>请先开通对应的上门取件产品。配置检查通过不代表已有寄件权限。</p></div></div>
                <el-row :gutter="16">
                    <el-col :xs="24" :sm="12"><el-form-item label="授权 Key"><el-input v-model="form.kuaidi100.api_key" show-password autocomplete="off" /><div class="form-tip">正式账号：快递100企业管理后台 → 我的信息 → 企业信息。<a :href="officialLinks.credentials" target="_blank" rel="noopener noreferrer">查看图文指引</a></div></el-form-item></el-col>
                    <el-col :xs="24" :sm="12"><el-form-item label="Secret"><el-input v-model="form.kuaidi100.secret" show-password autocomplete="off" /><div class="form-tip">从对应寄件服务的企业后台获取；找不到时联系账号客户经理，不能使用快递查询接口的其他密钥代替。</div></el-form-item></el-col>
                    <el-col :xs="24" :sm="12"><el-form-item label="服务模式"><el-select v-model="form.kuaidi100.mode" class="!w-full" @change="modeChanged"><el-option label="线上支付：与快递100结算" value="online" /><el-option label="线下支付：直接向快递员支付" value="offline" /></el-select></el-form-item></el-col>
                    <el-col :xs="24" :sm="12"><el-form-item label="运行环境"><el-select v-model="form.kuaidi100.environment" class="!w-full"><el-option label="正式环境（真实预约，可能扣费）" value="production" /><el-option label="测试环境（需要测试账号）" value="sandbox" /></el-select></el-form-item></el-col>
                </el-row>
                <p class="form-tip">已保存凭证显示为 ******，保留该值不修改。请勿把正式凭证用于测试环境；更换账号后旧单需恢复原账号才能处理。</p>
                <div class="section-heading next"><div><h3>3. 回收固定取件安排</h3><p>服务端按此配置执行，客户端不能改成其他快递公司。</p></div></div>
                <el-row :gutter="16">
                    <el-col :xs="24" :sm="12"><el-form-item label="上门取件公司"><el-select v-model="form.kuaidi100.carrier_code" filterable :loading="catalogLoading" :disabled="!catalogLoaded" placeholder="从官方目录选择快递公司" class="!w-full" @change="carrierChanged"><el-option v-for="carrier in carrierOptions" :key="carrier.code" :label="carrier.name" :value="carrier.code" :disabled="carrier.disabled"><span>{{ carrier.name }}</span><small v-if="carrier.disabled" class="option-note"> · 暂不支持</small></el-option></el-select><div class="form-tip">公司名称和接口编码由系统对应，不需要手填。</div></el-form-item></el-col>
                    <el-col :xs="24" :sm="12"><el-form-item label="寄件产品"><el-select v-model="form.kuaidi100.service_type" :disabled="!selectedCarrier || selectedCarrier.disabled || !catalogLoaded" placeholder="请选择寄件产品" class="!w-full"><el-option v-for="product in productOptions" :key="product.value" :label="product.label" :value="product.value" /></el-select><div class="form-tip">{{ selectedProduct?.note || '名称来自快递100官方文档；请向客户经理确认本站账号已开通所选产品。' }}</div></el-form-item></el-col>
                    <el-col :span="24"><div class="catalog-source"><span>官方参考目录 · {{ catalog.verified_at || '待读取' }} 核对</span><a v-if="modeSource" :href="modeSource" target="_blank" rel="noopener noreferrer">查看本模式官方原文 ↗</a><el-button text :loading="catalogLoading" @click="loadCatalog">重新读取目录</el-button></div><el-alert v-if="catalogError" :title="catalogError" type="error" :closable="false" show-icon /><el-alert v-else-if="selectionIssue" :title="selectionIssue" type="warning" :closable="false" show-icon /><p class="form-tip">{{ selectedMode?.note }} 目录是插件按官方文档维护的参考，不是账号授权或当地运力查询；重新读取不会自动抓取官方更新。</p></el-col>
                    <el-col :xs="24" :sm="12"><el-form-item label="向快递付运费的一方"><el-select v-model="form.kuaidi100.payment" class="!w-full"><el-option label="寄付（寄件方）" value="SHIPPER" /><el-option label="到付（收件方）" value="CONSIGNEE" :disabled="!canConsignee" /></el-select><div class="form-tip">{{ form.kuaidi100.mode === 'online' ? '本站快递100账号承担结算，请提前确认余额；客户不会在此重复支付。' : '线下向快递员结算；圆通、中通不支持到付。' }} 费用未知不等于免费。</div></el-form-item></el-col>
                </el-row>
                <el-alert v-if="form.kuaidi100.mode === 'online' && form.kuaidi100.payment === 'CONSIGNEE'" title="当前模式不支持到付，请明确改为寄付后保存；系统不会自动改变付款方。" type="error" :closable="false" />
                <el-collapse class="next"><el-collapse-item title="回调与高级设置（部署人员配置）" name="callback">
                    <el-form-item v-if="form.kuaidi100.mode === 'online'" label="合作渠道 ID（选填）"><el-input v-model="form.kuaidi100.channel_sw" placeholder="没有商务提供的 channelSw 就留空" /><div class="form-tip">只有同品牌多合作通道才需要。请向快递100客户经理索取，不是公司编码。<a :href="officialLinks.channel" target="_blank" rel="noopener noreferrer">多通道说明</a></div></el-form-item>
                    <el-form-item label="本站公开 HTTPS 回调地址"><el-input v-model="form.kuaidi100.callback_url" placeholder="https://本站域名/api/recycle/express/kuaidi100_push" /><div class="form-tip">路径固定为 /api/recycle/express/kuaidi100_push，不填 record_id，系统下单时自动补充。不能填 localhost；首次保存自动生成校验密钥，每单独立验签。无需电子面单或打印机。</div></el-form-item>
                    <el-form-item label="接口超时（秒）"><el-input-number v-model="form.kuaidi100.timeout" :min="3" :max="60" /></el-form-item>
                </el-collapse-item></el-collapse>
            </template>
            <template v-else>
                <el-row :gutter="16"><el-col :xs="24" :sm="12"><el-form-item label="App ID"><el-input v-model="form.yisu.appid" /></el-form-item></el-col><el-col :xs="24" :sm="12"><el-form-item label="App Secret"><el-input v-model="form.yisu.app_secret" show-password /></el-form-item></el-col><el-col :span="24"><el-form-item label="回调地址"><el-input v-model="form.yisu.callback_url" /></el-form-item></el-col></el-row>
                <el-collapse><el-collapse-item title="易速接口高级配置" name="yisu"><el-form-item label="服务地址"><el-input v-model="form.yisu.base_url" /></el-form-item><el-form-item label="接口版本"><el-input v-model="form.yisu.version" /></el-form-item><el-form-item label="超时（秒）"><el-input-number v-model="form.yisu.timeout" :min="3" :max="120" /></el-form-item><el-form-item v-for="item in pathFields" :key="item.key" :label="item.label"><el-input v-model="form.yisu.api_paths[item.key]" /></el-form-item></el-collapse-item></el-collapse>
            </template>
            <div class="check-row"><el-button :loading="testing" :disabled="configDirty || !configLoaded || configBusy" @click="checkSavedConfig">检查已保存配置</el-button><span>只检查格式与必填项，不验证账号权限、回调可达性、余额或当地运力，也不发起真实寄件。</span></div>
        </el-form>
        <el-collapse class="next" @change="loadNoticeOnce"><el-collapse-item title="4. 客户取件通知 · 使用系统消息管理" name="notice">
            <el-alert title="快递公司的短信不代替本站通知。未授权或发送失败时，客户仍可在订单中查看取件安排。" type="info" :closable="false" />
            <el-button v-if="!noticeLoaded && !noticeLoading" class="mt-[12px]" @click="loadNoticeOnce(['notice'])">重新读取通知配置</el-button>
            <div class="notice-actions"><el-button type="primary" plain @click="router.push('/setting/notice/template')">通知开关与消息管理</el-button><el-button @click="pickupReceiptsVisible = true">取件通知执行记录</el-button><el-button @click="router.push('/setting/notice/records')">系统通知记录</el-button><el-button :loading="noticeLoading" @click="refreshNotice">刷新通知状态</el-button></div>
            <p class="form-tip">模板和字段由插件固定提供，不需要手填。先获取小程序「回收预约取件状态通知」模板，再到「设置 → 消息管理」开启小程序通知。公众号模板尚待匹配，保持关闭即可。此处只展示框架状态，不单独保存通知参数。</p>
            <el-alert v-if="noticeError" :title="noticeError" type="error" :closable="false" show-icon class="mt-[12px]" />
            <div v-if="noticeLoaded" v-loading="noticeLoading" class="notice-status-grid">
                <div v-for="channel in noticeChannels" :key="channel.key" class="notice-status">
                    <strong>{{ channel.label }}</strong>
                    <el-tag :type="noticeReadiness[channel.key]?.enabled ? 'success' : 'info'">{{ noticeReadiness[channel.key]?.enabled ? '系统开关已开启' : '系统开关未开启' }}</el-tag>
                    <p v-if="!noticeReadiness[channel.key]?.catalog_ready">{{ noticeReadiness[channel.key]?.catalog_sync_message || '取件通知公共模板尚待开发方核实，当前未开放获取；无需管理员填写模板 ID 或字段映射。' }}</p>
                    <p v-else>{{ noticeReadiness[channel.key]?.template_ready ? '框架模板已获取；实际发送仍需有效账号与客户授权。' : '框架模板尚未就绪，请到对应渠道消息模板页查看并获取。' }}</p>
                    <p v-if="noticeReadiness[channel.key]?.missing?.length" class="form-tip">{{ noticeReadiness[channel.key].missing.join('；') }}</p>
                    <p class="form-tip">{{ noticeReadiness[channel.key]?.authorization_note || channel.authorizationNote }}</p>
                    <el-button class="mt-[12px]" plain @click="router.push(channel.path)">{{ noticeReadiness[channel.key]?.catalog_ready ? '获取' : '查看' }}{{ channel.shortLabel }}消息模板</el-button>
                </div>
            </div>
        </el-collapse-item></el-collapse>
        <template #aside><section class="aside-action-card"><h3>先看指引，再开通</h3><p class="form-tip">谁负责准备账号、每项在哪里获取、失败后怎么办，都在接入指引中。</p><el-button class="!w-full" type="primary" plain @click="guideVisible = true">开通与验收指引</el-button><el-divider /><h3>管理入口</h3><el-button v-if="form.provider === 'yisu'" class="!w-full mt-[12px]" @click="router.push('/third_party/express_product')">管理易速产品</el-button><el-button class="!w-full !ml-0 mt-[8px]" @click="router.push('/express/order_record')">预约与运单记录</el-button><el-button class="!w-full !ml-0 mt-[8px]" @click="router.push('/setting/notice/template')">系统消息管理</el-button><el-button class="!w-full !ml-0 mt-[8px]" @click="router.push('/setting/notice/records')">系统通知记录</el-button><p class="form-tip">预约失败不删除回收单；未知结果先核实，避免重复叫件。快递鸟、直连渠道将在适配器验收后开放，不展示未实现的选项。</p></section></template>
    </ThirdPartyServiceShell>
    <PickupSetupGuide v-model="guideVisible" :catalog="catalog" />
    <PickupNoticeReceipts v-model="pickupReceiptsVisible" />
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { onBeforeRouteLeave, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import ThirdPartyServiceShell from '@/addon/hsx_recycle/components/ThirdPartyServiceShell.vue'
import PickupSetupGuide from '@/addon/hsx_recycle/components/PickupSetupGuide.vue'
import PickupNoticeReceipts from '@/addon/hsx_recycle/views/third_party/components/PickupNoticeReceipts.vue'
import { getPickupProductGuide } from '@/addon/hsx_recycle/api/pickup_catalog'
import { useThirdPartyServiceConfig } from '@/addon/hsx_recycle/composables/useThirdPartyServiceConfig'
import { getPickupNoticeConfig } from '@/addon/hsx_recycle/api/pickup_notice'
import { apiThirdPartyConfigSave, apiThirdPartyConfigTest } from '@/addon/hsx_recycle/api/third_party'
const router = useRouter()
const guideVisible = ref(false)
const pickupReceiptsVisible = ref(false)
const officialLinks = { credentials: 'https://api.kuaidi100.com/document/chakankey', channel: 'https://api.kuaidi100.com/document/shang-jia-ji-jian-duo-tong-dao', online: 'https://api.kuaidi100.com/document/603cb649a62a19500e19866b', offline: 'https://api.kuaidi100.com/document/cduan-ji-jian-jie-kou-wen-dang' }
type ProductOption = { value: string; label: string; note?: string }
type CarrierOption = { code: string; name: string; products: ProductOption[]; disabled?: boolean; reason?: string }
type ModeOption = { value: string; note?: string; carriers: CarrierOption[] }
const catalog = ref<{ verified_at?: string; modes: ModeOption[]; sources?: any[] }>({ modes: [] })
const catalogLoaded = ref(false), catalogLoading = ref(false), catalogError = ref('')
const loadCatalog = async () => {
    if (catalogLoading.value) return
    catalogLoading.value = true
    try {
        const res: any = await getPickupProductGuide()
        if (!Array.isArray(res.data?.modes) || !res.data.modes.length) throw new Error('产品目录返回不完整，请确认回收插件后端已更新')
        catalog.value = res.data; catalogLoaded.value = true; catalogError.value = ''
    } catch (error: any) {
        catalogLoaded.value = false; catalogError.value = error?.msg || error?.message || '产品目录读取失败，请重试；不可通过手写产品绕过校验'
    } finally { catalogLoading.value = false }
}
const defaults = { enabled: 1, provider: 'yisu', kuaidi100: { api_key: '', secret: '', callback_salt: '', mode: 'online', environment: 'production', carrier_code: 'shunfeng', carrier_name: '顺丰速运', service_type: '', payment: 'SHIPPER', channel_sw: '', callback_url: '', timeout: 30 }, yisu: { base_url: 'http://open.yisuopen.com', appid: '', app_secret: '', version: 'V1.0', timeout: 30, callback_url: '', api_paths: { quote: '/openApi/getPrice', create: '/openApi/doOrder', cancel: '/openApi/doCancel', modify: '/openApi/doModify', detail: '/openApi/getOrderDetail', waybillPdf: '/openApi/getWaybillPdf', fund: '/openApi/fund' } } }
const { form, loading, saving, testing, capabilityInfo, load } = useThirdPartyServiceConfig('express_order', defaults)
const readableCapability = computed(() => {
    const labels: Record<string, string> = { api_key: '授权 Key', secret: 'Secret', callback_url: '公开回调地址', callback_salt: '回调校验密钥（保存时自动生成）', carrier_code: '上门取件公司', service_type: '寄件产品', base_url: '服务地址', appid: 'App ID', app_secret: 'App Secret' }
    return { ...capabilityInfo.value, missing_fields: (capabilityInfo.value?.missing_fields || []).map((key: string) => labels[key] || key) }
})
const configLoaded = ref(false)
const savedConfig = ref('')
const configDirty = computed(() => configLoaded.value && JSON.stringify(form) !== savedConfig.value)
const configBusy = computed(() => loading.value || saving.value || testing.value)
const selectedMode = computed(() => catalog.value.modes.find(item => item.value === form.kuaidi100.mode))
const carrierOptions = computed(() => selectedMode.value?.carriers || [])
const selectedCarrier = computed(() => carrierOptions.value.find(item => item.code === form.kuaidi100.carrier_code))
const productOptions = computed(() => selectedCarrier.value?.products || [])
const selectedProduct = computed(() => productOptions.value.find(item => item.value === form.kuaidi100.service_type))
const modeSource = computed(() => form.kuaidi100.mode === 'offline' ? officialLinks.offline : officialLinks.online)
const canConsignee = computed(() => form.kuaidi100.mode === 'offline' && !['yuantong', 'zhongtong'].includes(form.kuaidi100.carrier_code))
const selectionIssue = computed(() => {
    if (!catalogLoaded.value) return ''
    if (form.kuaidi100.carrier_code && !selectedCarrier.value) return '已保存的公司不在当前模式参考目录中，请重新选择；不会自动替换承运商。'
    if (selectedCarrier.value?.disabled) return selectedCarrier.value.reason || '当前适配器暂不支持此公司，请选择其他公司'
    if (form.kuaidi100.service_type && !selectedProduct.value) return '原产品与当前公司或模式不匹配，请重新选择；不会自动更改产品或付款方。'
    return ''
})
const savedProviderLabel = computed(() => {
    if (!configLoaded.value) return '本站已保存渠道 · 待读取'
    return JSON.parse(savedConfig.value).provider === 'kuaidi100' ? '快递100 · 本站已保存渠道' : '易速 · 本站已保存渠道'
})
const errorText = (error: any, fallback: string) => error?.msg || error?.message || fallback
const readConfig = async () => {
    await load()
    savedConfig.value = JSON.stringify(form)
    configLoaded.value = true
}
const confirmDiscard = async (message: string) => {
    try { await ElMessageBox.confirm(message, '有未保存的修改', { confirmButtonText: '放弃修改', cancelButtonText: '继续编辑', type: 'warning' }); return true }
    catch { return false }
}
const refreshConfig = async () => {
    if (configBusy.value) return
    if (configDirty.value && !await confirmDiscard('刷新将覆盖未保存的取件配置；通知配置不受影响。是否继续？')) return
    try { await readConfig() }
    catch (error: any) { ElMessage.error(errorText(error, '取件配置读取失败，请重试；尚未保存任何更改')) }
}
const carrierChanged = (code: string) => {
    form.kuaidi100.carrier_name = carrierOptions.value.find(item => item.code === code)?.name || ''
    form.kuaidi100.service_type = ''
    ElMessage.info('快递公司已变更，请重新选择寄件产品并核对付款方')
}
const modeChanged = () => {
    const carrier = carrierOptions.value.find(item => item.code === form.kuaidi100.carrier_code && !item.disabled)
    if (!carrier) { form.kuaidi100.carrier_code = ''; form.kuaidi100.carrier_name = '' }
    form.kuaidi100.service_type = ''
    ElMessage.info('服务模式已变更，请重新选择产品并核对账号及付款方式；系统不会自动切换付款方')
}
const configValidationError = (data: any): string => {
    if (!['kuaidi100', 'yisu'].includes(data.provider)) return '请选择受支持的接入渠道'
    // 停用时允许保存未完成的草稿，不阻碍关闭新预约。
    if (!Number(data.enabled)) return ''
    const config = data[data.provider]
    const required: Record<string, string> = data.provider === 'kuaidi100'
        ? { api_key: '授权 Key', secret: 'Secret', carrier_code: '上门取件公司', carrier_name: '快递公司名称（选择公司后自动生成）', service_type: '寄件产品（从官方目录选择）', callback_url: '本站公开 HTTPS 回调地址' }
        : { appid: 'App ID', app_secret: 'App Secret', base_url: '易速服务地址' }
    for (const [key, label] of Object.entries(required)) if (!String(config?.[key] ?? '').trim()) return `启用新预约前，请填写${label}`
    const timeout = Number(config.timeout)
    if (!Number.isInteger(timeout) || timeout < 3 || timeout > (data.provider === 'kuaidi100' ? 60 : 120)) return `接口超时须为 3 至 ${data.provider === 'kuaidi100' ? 60 : 120} 秒的整数`
    if (data.provider !== 'kuaidi100') return ''
    if (!['online', 'offline'].includes(config.mode) || !['production', 'sandbox'].includes(config.environment)) return '请选择有效的服务模式与运行环境'
    if (!/^[a-z][a-z0-9_]{1,39}$/.test(config.carrier_code)) return '承运公司编码须为官方小写英文编码，不能填写中文名称'
    if (!['SHIPPER', 'CONSIGNEE'].includes(config.payment)) return '请选择运费付款方'
    if (config.mode === 'online' && config.payment !== 'SHIPPER') return '线上模式不支持到付，请明确改为寄付后保存'
    if (config.payment === 'CONSIGNEE' && ['yuantong', 'zhongtong'].includes(config.carrier_code)) return '当前承运商不支持到付，请明确调整付款方式'
    if (!catalogLoaded.value) return '请先成功读取官方参考目录，再选择寄件公司和产品；可先停用新预约保存草稿'
    const mode = catalog.value.modes.find(item => item.value === config.mode)
    const carrier = mode?.carriers.find(item => item.code === config.carrier_code)
    if (!carrier) return '当前服务模式不支持所选公司，请从目录重新选择'
    if (carrier.disabled) return carrier.reason || '当前适配器暂不支持所选公司'
    if (!carrier.products.some(item => item.value === config.service_type)) return '寄件产品与服务模式、快递公司不匹配，请从目录重新选择'
    try {
        const url = new URL(config.callback_url)
        const host = url.hostname.toLowerCase().replace(/\.$/, '')
        if (url.protocol !== 'https:' || url.username || url.password || url.hash || !host.includes('.')
            || /(^localhost$|\.localhost$|\.local$|^(0|10|127|169\.254|192\.168)\.|^172\.(1[6-9]|2\d|3[01])\.)/.test(host)) return '回调地址须为本站公开 HTTPS 地址，不能使用本机或局域网地址'
        if (!url.pathname.replace(/\/$/, '').endsWith('/api/recycle/express/kuaidi100_push')) return '回调地址路径应为 /api/recycle/express/kuaidi100_push'
        if (url.searchParams.has('record_id')) return '回调地址不填写 record_id，系统会在下单时自动补充'
    } catch { return '请输入完整有效的 HTTPS 回调地址' }
    return ''
}
const saveConfig = async () => {
    if (configBusy.value) return
    if (!configLoaded.value) return ElMessage.warning('请先成功读取本站配置，避免覆盖已有设置')
    const data = JSON.parse(JSON.stringify(form))
    for (const section of ['kuaidi100', 'yisu']) for (const key of Object.keys(data[section] || {})) if (typeof data[section][key] === 'string') data[section][key] = data[section][key].trim()
    const validationError = configValidationError(data)
    if (validationError) return ElMessage.warning(validationError)
    if (data.provider === 'kuaidi100' && Number(data.enabled)) data.kuaidi100.carrier_name = catalog.value.modes.find(item => item.value === data.kuaidi100.mode)!.carriers.find(item => item.code === data.kuaidi100.carrier_code)!.name
    saving.value = true
    try {
        await apiThirdPartyConfigSave({ express_order: data })
        ElMessage.success(Number(data.enabled) ? '取件配置已保存；未发起真实寄件，账号权限与可用运力仍需实单验收' : '取件配置已保存，新预约已停用；已有订单仍沿原渠道处理')
        try { await readConfig() }
        catch { configLoaded.value = false; ElMessage.warning('保存请求已成功，但最新配置读取失败。请刷新确认，勿重复保存') }
    } catch (error: any) { ElMessage.error(errorText(error, '未能确认配置保存结果，请刷新核实后重试')) }
    finally { saving.value = false }
}
const checkSavedConfig = async () => {
    if (configBusy.value) return
    if (!configLoaded.value) return ElMessage.warning('请先读取本站配置')
    if (configDirty.value) return ElMessage.warning('当前修改尚未保存，请先保存后再检查')
    testing.value = true
    try {
        const res: any = await apiThirdPartyConfigTest('express_order')
        if (res.data?.success === true) ElMessage.success('已保存配置的格式检查通过；未验证账号权限、回调可达性、余额及当地运力，也未发起真实寄件')
        else ElMessage.warning(res.data?.message || '已保存配置检查未通过，请补充配置后重新保存')
    } catch (error: any) { ElMessage.error(errorText(error, '配置检查失败，请稍后重试；未发起真实寄件')) }
    finally { testing.value = false }
}
const pathFields = [{ key: 'quote', label: '报价' }, { key: 'create', label: '下单' }, { key: 'cancel', label: '取消' }, { key: 'modify', label: '改约' }, { key: 'detail', label: '详情' }, { key: 'waybillPdf', label: '原面单接口' }, { key: 'fund', label: '资金查询' }]
const noticeChannels = [
    { key: 'weapp', label: '微信小程序订阅消息', shortLabel: '小程序', path: '/channel/weapp/message', authorizationNote: '客户须主动订阅；订阅次数、账号类目及模板权限由微信校验。' },
    { key: 'wechat', label: '微信公众号消息', shortLabel: '公众号', path: '/channel/wechat/message', authorizationNote: '客户须关注并绑定本站公众号；公众号与模板需具备相应发送权限。' }
]
const noticeReadiness = ref<Record<string, any>>({})
const noticeLoading = ref(false), noticeLoaded = ref(false), noticeError = ref('')
const loadNoticeOnce = async (names: any) => {
    if (!Array.isArray(names) || !names.includes('notice') || noticeLoaded.value || noticeLoading.value) return
    noticeLoading.value = true
    noticeError.value = ''
    try {
        const res: any = await getPickupNoticeConfig()
        const data = res.data
        if (!data || !noticeChannels.every(({ key }) => data[key] && typeof data[key] === 'object' && !Array.isArray(data[key]))) throw new Error('通知状态返回不完整，请确认回收插件后端已更新')
        noticeReadiness.value = Object.fromEntries(noticeChannels.map(({ key }) => {
            const item = data[key]
            return [key, {
                enabled: Number(item.enabled) === 1,
                catalog_ready: item.catalog_ready === true,
                catalog_sync_message: typeof item.catalog_sync_message === 'string' ? item.catalog_sync_message : typeof data.catalog_sync_message === 'string' ? data.catalog_sync_message : '',
                template_ready: item.template_ready === true,
                ready: item.ready === true,
                missing: Array.isArray(item.missing) ? item.missing.filter((value: unknown) => typeof value === 'string') : [],
                authorization_note: typeof item.authorization_note === 'string' ? item.authorization_note : ''
            }]
        }))
        noticeLoaded.value = true
    } catch (error: any) {
        noticeReadiness.value = {}
        noticeError.value = errorText(error, '通知状态读取失败，请重试或确认后端已更新')
        ElMessage.error(noticeError.value)
    }
    finally { noticeLoading.value = false }
}
const refreshNotice = async () => {
    if (noticeLoading.value) return
    noticeLoaded.value = false
    await loadNoticeOnce(['notice'])
}
onBeforeRouteLeave(async () => {
    if (configBusy.value) { ElMessage.warning('正在读取、保存或检查配置，请完成后再离开'); return false }
    if (configDirty.value) return confirmDiscard('离开页面将放弃未保存的取件配置，是否继续？')
    return true
})
onMounted(async () => { await Promise.all([refreshConfig(), loadCatalog()]) })
</script>

<style scoped lang="scss">
.draft-alert{margin-bottom:20px}
.onboarding-banner{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px;background:#f0f7ff;border:1px solid #dbeafe;border-radius:8px;margin-bottom:20px}.onboarding-banner strong{font-size:14px;color:#1e3a5f}.onboarding-banner p{margin:6px 0 0;font-size:12px;color:#526880;line-height:1.7}.form-tip a,.catalog-source a{color:var(--el-color-primary);text-decoration:none}.catalog-source{display:flex;align-items:center;flex-wrap:wrap;gap:12px;color:#64748b;font-size:12px}.option-note{font-size:11px;color:#94a3b8}.notice-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px}.notice-actions :deep(.el-button){margin:0}.notice-status-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin:16px 0}.notice-status{border:1px solid #e5e7eb;border-radius:8px;padding:14px;font-size:13px;line-height:1.7}.notice-status strong{display:block;margin-bottom:8px}.notice-status p{color:#64748b;font-size:12px;margin:8px 0 0}@media(max-width:700px){.onboarding-banner{align-items:flex-start;flex-direction:column}}
.service-form{max-width:920px}.section-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:16px}.section-heading h3,.aside-action-card h3{margin:0;font-size:15px;color:#1f2937}.section-heading p,.form-tip{margin:6px 0;color:#64748b;font-size:12px;line-height:1.7}.next{margin-top:24px}.check-row{display:flex;align-items:center;gap:12px;margin-top:20px}.check-row span{color:#64748b;font-size:12px}.aside-action-card{padding:18px;border:1px solid #e5e7eb;border-radius:10px}@media(max-width:700px){.section-heading,.check-row{flex-direction:column}}
</style>
