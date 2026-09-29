<template>
    <ThirdPartyServiceShell title="上门取件服务" description="管理员固定渠道、快递公司和付款方式；客户提交回收单后自动预约，不用选择快递。"
        :provider-label="form.provider === 'kuaidi100' ? '快递100 · 本站独立账号' : '易速 · 原渠道'"
        :capability="capabilityInfo" :loading="loading" :saving="saving" :testing="testing" :testable="false"
        :features="['固定承运商，不自动换商', '预约与回收订单状态分别记录', '失败可处理，未知结果不重复叫件']"
        :steps="['填写本站账号及回调地址', '选择已开通的承运商和产品', '核对费用承担及客户通知', '保存配置并进行授权后的实单验收']"
        @refresh="load" @save="saveConfig">
        <el-form label-position="top" class="service-form">
            <div class="section-heading"><div><h3>1. 新预约使用哪个渠道</h3><p>更换只影响新预约，已有订单仍沿原渠道处理。请保留原账号凭证。</p></div><el-switch v-model="form.enabled" :active-value="1" :inactive-value="0" active-text="启用新预约" /></div>
            <el-form-item label="接入渠道"><el-select v-model="form.provider" class="!w-full"><el-option label="快递100上门取件" value="kuaidi100" /><el-option label="易速（保留原渠道）" value="yisu" /></el-select></el-form-item>
            <template v-if="form.provider === 'kuaidi100'">
                <el-alert title="各站点使用自己的快递100账号结算；平台不代充值、不代扣运费。" type="info" :closable="false" show-icon />
                <div class="section-heading next"><div><h3>2. 本站账号与服务模式</h3><p>请先开通对应的上门取件产品。配置检查通过不代表已有寄件权限。</p></div></div>
                <el-row :gutter="16">
                    <el-col :xs="24" :sm="12"><el-form-item label="授权 Key"><el-input v-model="form.kuaidi100.api_key" show-password autocomplete="off" /></el-form-item></el-col>
                    <el-col :xs="24" :sm="12"><el-form-item label="Secret"><el-input v-model="form.kuaidi100.secret" show-password autocomplete="off" /></el-form-item></el-col>
                    <el-col :xs="24" :sm="12"><el-form-item label="服务模式"><el-select v-model="form.kuaidi100.mode" class="!w-full"><el-option label="线上支付：与快递100结算" value="online" /><el-option label="线下支付：直接向快递员支付" value="offline" /></el-select></el-form-item></el-col>
                    <el-col :xs="24" :sm="12"><el-form-item label="运行环境"><el-select v-model="form.kuaidi100.environment" class="!w-full"><el-option label="正式环境（真实预约，可能扣费）" value="production" /><el-option label="测试环境（需要测试账号）" value="sandbox" /></el-select></el-form-item></el-col>
                </el-row>
                <p class="form-tip">已保存凭证显示为 ******，保留该值不修改。请勿把正式凭证用于测试环境；更换账号后旧单需恢复原账号才能处理。</p>
                <div class="section-heading next"><div><h3>3. 回收固定取件安排</h3><p>服务端按此配置执行，客户端不能改成其他快递公司。</p></div></div>
                <el-row :gutter="16">
                    <el-col :xs="24" :sm="12"><el-form-item label="承运公司编码"><el-select v-model="form.kuaidi100.carrier_code" filterable allow-create default-first-option class="!w-full" @change="carrierChanged"><el-option label="顺丰速运（shunfeng）" value="shunfeng" /><el-option label="京东快递（jd）" value="jd" /></el-select><div class="form-tip">其他公司填写官方编码，不是中文名称。</div></el-form-item></el-col>
                    <el-col :xs="24" :sm="12"><el-form-item label="客户看到的快递名称"><el-input v-model="form.kuaidi100.carrier_name" placeholder="实际承运商名称" /></el-form-item></el-col>
                    <el-col :xs="24" :sm="12"><el-form-item label="已开通的产品名称"><el-input v-model="form.kuaidi100.service_type" placeholder="如顺丰标快，须与合同一致" /><div class="form-tip">产品名称及账号权限须由快递100确认，不能任意起名。</div></el-form-item></el-col>
                    <el-col :xs="24" :sm="12"><el-form-item label="向快递付运费的一方"><el-select v-model="form.kuaidi100.payment" class="!w-full"><el-option label="寄付（寄件方）" value="SHIPPER" /><el-option label="到付（收件方）" value="CONSIGNEE" :disabled="form.kuaidi100.mode === 'online'" /></el-select><div class="form-tip">线上模式仅支持寄付，线下模式仍受承运商限制。费用未知不等于免费。</div></el-form-item></el-col>
                    <el-col :span="24" v-if="form.kuaidi100.mode === 'online'"><el-form-item label="合作渠道 ID（选填）"><el-input v-model="form.kuaidi100.channel_sw" placeholder="仅在商务提供时填写" /></el-form-item></el-col>
                </el-row>
                <el-alert v-if="form.kuaidi100.mode === 'online' && form.kuaidi100.payment === 'CONSIGNEE'" title="当前模式不支持到付，请明确改为寄付后保存；系统不会自动改变付款方。" type="error" :closable="false" />
                <el-collapse class="next"><el-collapse-item title="回调与高级设置（部署人员配置）" name="callback">
                    <el-form-item label="本站公开 HTTPS 回调地址"><el-input v-model="form.kuaidi100.callback_url" placeholder="https://本站域名/api/recycle/express/kuaidi100_push" /><div class="form-tip">路径固定为 /api/recycle/express/kuaidi100_push，不填 record_id，系统下单时自动补充。不能填 localhost；首次保存自动生成校验密钥，每单独立验签。无需电子面单或打印机。</div></el-form-item>
                    <el-form-item label="接口超时（秒）"><el-input-number v-model="form.kuaidi100.timeout" :min="3" :max="60" /></el-form-item>
                </el-collapse-item></el-collapse>
            </template>
            <template v-else>
                <el-row :gutter="16"><el-col :xs="24" :sm="12"><el-form-item label="App ID"><el-input v-model="form.yisu.appid" /></el-form-item></el-col><el-col :xs="24" :sm="12"><el-form-item label="App Secret"><el-input v-model="form.yisu.app_secret" show-password /></el-form-item></el-col><el-col :span="24"><el-form-item label="回调地址"><el-input v-model="form.yisu.callback_url" /></el-form-item></el-col></el-row>
                <el-collapse><el-collapse-item title="易速接口高级配置" name="yisu"><el-form-item label="服务地址"><el-input v-model="form.yisu.base_url" /></el-form-item><el-form-item label="接口版本"><el-input v-model="form.yisu.version" /></el-form-item><el-form-item label="超时（秒）"><el-input-number v-model="form.yisu.timeout" :min="3" :max="120" /></el-form-item><el-form-item v-for="item in pathFields" :key="item.key" :label="item.label"><el-input v-model="form.yisu.api_paths[item.key]" /></el-form-item></el-collapse-item></el-collapse>
            </template>
            <div class="check-row"><el-button :loading="testing" @click="test">检查已保存配置</el-button><span>只检查格式与必填项，不发起真实寄件。请先保存再检查。</span></div>
        </el-form>
        <el-collapse class="next" @change="loadNoticeOnce"><el-collapse-item title="客户取件通知（独立保存，不影响回收下单）" name="notice">
            <el-alert title="快递公司的短信不代替本站通知。未授权或发送失败时，客户仍可在订单中查看取件安排。" type="info" :closable="false" />
            <div v-loading="noticeLoading" class="notice-form">
                <div v-for="channel in noticeChannels" :key="channel.key" class="notice-channel">
                    <div class="section-heading"><h3>{{ channel.label }}</h3><el-switch v-model="noticeForm[channel.key].enabled" :active-value="1" :inactive-value="0" /></div>
                    <el-input v-model="noticeForm[channel.key].template_id" placeholder="该账号已开通的消息模板 ID" />
                    <div v-for="(row, index) in noticeForm[channel.key].content" :key="index" class="mapping-row"><el-select v-model="row.variable" placeholder="业务字段"><el-option v-for="(label, key) in noticeVariables" :key="key" :label="label" :value="key" /></el-select><el-input v-model="row.keyword" placeholder="实际模板字段，如 thing1" /><el-button text type="danger" @click="noticeForm[channel.key].content.splice(index, 1)">移除</el-button></div>
                    <el-button text type="primary" @click="noticeForm[channel.key].content.push({ label: '', variable: '', keyword: '' })">添加字段对应</el-button>
                </div>
                <p class="form-tip">字段代码须与实际微信模板一致。模板审核、授权次数以微信平台为准，不随意套用示例。</p><el-button type="primary" :loading="noticeSaving" :disabled="!noticeLoaded" @click="saveNotice">保存通知配置</el-button>
            </div>
        </el-collapse-item></el-collapse>
        <template #aside><section class="aside-action-card"><h3>管理入口</h3><el-button v-if="form.provider === 'yisu'" class="!w-full" @click="router.push('/third_party/express_product')">管理易速产品</el-button><el-button class="!w-full !ml-0 mt-[8px]" @click="router.push('/express/order_record')">预约与运单记录</el-button><p class="form-tip">预约失败不删除回收单；未知结果先核实，避免重复叫件。快递鸟、直连渠道将在适配器验收后开放，不展示未实现的选项。</p></section></template>
    </ThirdPartyServiceShell>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import ThirdPartyServiceShell from '@/addon/hsx_recycle/components/ThirdPartyServiceShell.vue'
import { useThirdPartyServiceConfig } from '@/addon/hsx_recycle/composables/useThirdPartyServiceConfig'
import { getPickupNoticeConfig, setPickupNoticeConfig } from '@/addon/hsx_recycle/api/pickup_notice'
const router = useRouter()
const defaults = { enabled: 1, provider: 'yisu', kuaidi100: { api_key: '', secret: '', callback_salt: '', mode: 'online', environment: 'production', carrier_code: 'shunfeng', carrier_name: '顺丰速运', service_type: '', payment: 'SHIPPER', channel_sw: '', callback_url: '', timeout: 30 }, yisu: { base_url: 'http://open.yisuopen.com', appid: '', app_secret: '', version: 'V1.0', timeout: 30, callback_url: '', api_paths: { quote: '/openApi/getPrice', create: '/openApi/doOrder', cancel: '/openApi/doCancel', modify: '/openApi/doModify', detail: '/openApi/getOrderDetail', waybillPdf: '/openApi/getWaybillPdf', fund: '/openApi/fund' } } }
const { form, loading, saving, testing, capabilityInfo, load, save, test } = useThirdPartyServiceConfig('express_order', defaults)
const carrierChanged = (code: string) => { form.kuaidi100.carrier_name = ({ shunfeng: '顺丰速运', jd: '京东快递' } as Record<string, string>)[code] || '' }
const saveConfig = async () => {
    if (form.provider === 'kuaidi100' && form.enabled && form.kuaidi100.mode === 'online' && form.kuaidi100.payment !== 'SHIPPER') return ElMessage.warning('线上模式不支持到付，请先调整付款方式')
    await save()
}
const pathFields = [{ key: 'quote', label: '报价' }, { key: 'create', label: '下单' }, { key: 'cancel', label: '取消' }, { key: 'modify', label: '改约' }, { key: 'detail', label: '详情' }, { key: 'waybillPdf', label: '原面单接口' }, { key: 'fund', label: '资金查询' }]
const noticeChannels = [{ key: 'weapp', label: '微信小程序订阅消息' }, { key: 'wechat', label: '微信公众号消息' }]
const emptyChannel = () => ({ enabled: 0, template_id: '', content: [] as any[] })
const noticeForm = reactive<Record<string, any>>({ weapp: emptyChannel(), wechat: emptyChannel() })
const noticeVariables = ref<Record<string, string>>({})
const noticeLoading = ref(false), noticeSaving = ref(false), noticeLoaded = ref(false)
const loadNoticeOnce = async (names: any) => {
    if (!Array.isArray(names) || !names.includes('notice') || noticeLoaded.value || noticeLoading.value) return
    noticeLoading.value = true
    try { const res: any = await getPickupNoticeConfig(); for (const { key } of noticeChannels) Object.assign(noticeForm[key], emptyChannel(), res.data?.[key] || {}); noticeVariables.value = res.data?.variables || {}; noticeLoaded.value = true }
    catch (error: any) { ElMessage.error(error?.message || '通知配置读取失败，请确认后端已更新') }
    finally { noticeLoading.value = false }
}
const saveNotice = async () => {
    noticeSaving.value = true
    try { const data = JSON.parse(JSON.stringify(noticeForm)); for (const { key } of noticeChannels) for (const row of data[key].content) row.label = noticeVariables.value[row.variable] || row.variable; await setPickupNoticeConfig(data); ElMessage.success('通知配置已保存；发送仍需客户授权与有效模板') }
    finally { noticeSaving.value = false }
}
onMounted(load)
</script>

<style scoped lang="scss">
.service-form{max-width:920px}.section-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:16px}.section-heading h3,.aside-action-card h3{margin:0;font-size:15px;color:#1f2937}.section-heading p,.form-tip{margin:6px 0;color:#64748b;font-size:12px;line-height:1.7}.next{margin-top:24px}.check-row{display:flex;align-items:center;gap:12px;margin-top:20px}.check-row span{color:#64748b;font-size:12px}.aside-action-card{padding:18px;border:1px solid #e5e7eb;border-radius:10px}.notice-form{padding-top:16px}.notice-channel{margin-bottom:20px}.mapping-row{display:flex;gap:8px;margin:10px 0}.mapping-row :deep(.el-select),.mapping-row :deep(.el-input){flex:1;min-width:0}@media(max-width:700px){.section-heading,.check-row{flex-direction:column}.mapping-row{flex-wrap:wrap}.mapping-row :deep(.el-select),.mapping-row :deep(.el-input){flex-basis:100%}}
</style>
