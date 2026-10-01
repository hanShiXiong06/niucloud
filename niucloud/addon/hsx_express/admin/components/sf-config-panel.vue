<template>
    <section class="sf-config" v-loading="loading">
        <div class="sf-heading"><div><h2>{{ isWaybill ? '顺丰直连 · 电子面单' : '顺丰直连 · 上门取件' }}</h2><p>{{ isWaybill ? '门店发货：取号 → 打印面单（无需下载）→ 贴单交件 → 商城确认发货。' : '回收预约：提交回收单 → 请求顺丰取件 → 核对原单受理结果；实际安排以顺丰返回为准。' }}</p></div><el-tag :type="form.environment === 'sandbox' ? 'warning' : 'danger'">{{ form.environment === 'sandbox' ? '沙箱测试 · 非真实寄件' : '正式环境 · 可能计费' }}</el-tag></div>
        <HsxNotice :title="isWaybill ? '电子面单不等于预约快递员上门' : '上门取件不要求客户打印面单'" :description="isWaybill ? '此配置只用于商城发货面单。生成 PDF 不能证明打印机已出纸、已交件或已揽收；上门取件使用另一套独立配置。' : '此配置只用于回收预约取件，不需要填写打印模板。与商城电子面单分别开通、分别启用，不能用面单权限代替预约权限。'" :default-expanded="true" :closable="false" />
        <p v-if="!isWaybill" class="sf-help">当前可按原单核对建单结果；派员、取件时间与联系电话的推送回调仍需开通并实单验收。没有收到的信息不会显示为“已派员”或虚构取件时间。</p>
        <HsxNotice v-if="loadError" type="error" title="顺丰配置读取失败" :description="loadError" :default-expanded="true" :closable="false"><template #actions><el-button link type="primary" @click="load">重新读取</el-button></template></HsxNotice>
        <el-form label-position="top" :model="form" :disabled="busy" @submit.prevent="save">
            <div class="sf-section">
                <h3>1. 选择环境，填写对应接口账号</h3>
                <div class="sf-grid">
                    <el-form-item label="运行环境" required><el-select v-model="form.environment" @change="identityChanged"><el-option v-for="item in environments" :key="item.value" :label="item.label" :value="item.value" /></el-select><div class="sf-help">沙箱凭据与正式凭据不能混用；更换环境会停用并要求重新填写校验码。</div></el-form-item>
                    <el-form-item label="顺丰顾客编码 / Client Code" required><el-input v-model.trim="form.client_code" autocomplete="off" placeholder="从顺丰开放平台已开通应用中获取" @change="identityChanged" /><div class="sf-help">这是顺丰直连接口身份，不是快递100 Key，也不是月结卡号。</div></el-form-item>
                    <el-form-item label="顺丰校验码 / Check Word" required><SavedCredentialInput :key="credentialRevision" v-model="form.check_word" field="sf_check_word" label="顺丰校验码" :saved="hasSavedSecret" :disabled="busy" /><div class="sf-help">已保存的密钥不返回原文。更换后保存才生效，留空保留原值。</div><el-button v-if="hasSavedSecret" link type="danger" @click="requestClearSecret">清空已保存校验码</el-button><div v-if="clearSecret" class="sf-warning">{{ form.check_word ? '保存时使用新校验码替换原值。' : '待清空：保存后原校验码将移除，此配置保持停用。' }}</div></el-form-item>
                    <el-form-item :label="Number(form.pay_method) === 3 ? '获授权的第三方月结卡号' : '顺丰月结卡号（选填）'" :required="Number(form.pay_method) === 3"><el-input v-model.trim="form.monthly_card" autocomplete="off" :disabled="Number(form.pay_method) === 2" :placeholder="Number(form.pay_method) === 2 ? '到付不填写月结卡号' : Number(form.pay_method) === 3 ? '填写获得授权的第三方月结卡号' : '寄付可填月结卡号；留空按现结处理'" @change="form.use_ack = 0" /><div class="sf-help">{{ Number(form.pay_method) === 2 ? '到付由收件方结算，不能同时提交本站月结卡号。' : Number(form.pay_method) === 3 ? '第三方付必须填写已经获得授权的月结卡号；不能借用未授权的账号。' : form.environment === 'sandbox' ? '寄付留空表示现结；测试月结请使用顺丰提供的沙箱卡号，不填写虚构号码或正式卡号。' : '寄付支持现结或月结；使用月结时，由本站签约网点提供卡号并确认应用授权关系。' }}</div></el-form-item>
                </div>
                <HsxFold title="这些参数从哪里获取？" summary="顺丰直连账号与两个产品的开通范围"><ul class="sf-guide"><li>管理员在顺丰开放平台创建或进入本站应用，获取当前环境的 Client Code 和 Check Word。</li><li v-if="isWaybill">PDF 模板：进入当前应用的「查看 API → 基础通用 API → 云打印面单转PDF接口 → 查看」，复制分配给当前应用的模板编码；不要照抄接口请求示例，也不要使用其他账号的模板。确认模板尺寸与打印纸匹配。</li><li>联系顺丰客户经理确认{{ isWaybill ? '下单与电子面单 PDF' : '预约取件' }}接口、产品、月结账号以及付款方式已开通。</li><li>仅有月结账号，不代表接口权限已经开通。沙箱通过，也不代表正式账号已授权。</li><li>电子面单和上门取件的启用状态独立，本页只保存「{{ isWaybill ? '电子面单' : '上门取件' }}」。</li></ul><a href="https://open.sf-express.com/" target="_blank" rel="noopener noreferrer">顺丰开放平台 ↗</a></HsxFold>
            </div>
            <div class="sf-section">
                <h3>2. 确认产品与业务设置</h3>
                <div class="sf-grid">
                    <el-form-item label="已申请使用的顺丰产品" required><el-select v-model="form.product_code" :disabled="!products.length" placeholder="从服务端官方参考目录选择" @change="form.use_ack = 0"><el-option v-for="item in products" :key="item.value" :label="item.label" :value="String(item.value)" /></el-select><div class="sf-help">这是参考目录，不是本站账号已开通清单；不能通过手写产品名绕过授权。</div></el-form-item>
                    <el-form-item label="运费付款方式" required><el-select v-model="form.pay_method" :disabled="!payMethods.length" placeholder="选择本站已开通的付款方式" @change="paymentChanged"><el-option v-for="item in payMethods" :key="item.value" :label="item.label" :value="Number(item.value)" /></el-select><div class="sf-help">寄付可现结或月结；到付不使用本站月结卡号。与客户购买商品的支付方式不是一回事。</div></el-form-item>
                    <el-form-item v-if="isWaybill" label="顺丰面单模板编码" required><el-input v-model.trim="form.template_code" placeholder="复制当前应用已分配的 PDF 模板编码" /><div class="sf-help">获取路径见上方说明；不要照抄接口示例，也不是快递100模板 ID。模板填错时，修正保存后只需重新获取原单 PDF，不必重新取号。</div></el-form-item>
                </div>
                <HsxFold title="回调与高级信息" summary="当前以原单查询核实结果；不填写不代表已启用推送回调"><el-form-item label="本站公网 HTTPS 根地址（可选）"><el-input v-model.trim="form.callback_base_url" placeholder="例如 https://example.com，不带路径" /><div class="sf-help">仅保存配置，不会自动为你在顺丰侧订阅或开通回调。</div></el-form-item></HsxFold>
                <el-checkbox v-model="form.use_ack" :true-label="1" :false-label="0" class="sf-ack">我已确认：当前环境的本站账号开通了本页产品、接口能力和付款方式。</el-checkbox>
                <div class="sf-enable"><el-switch v-model="form.enabled" :active-value="1" :inactive-value="0" /><div><strong>启用{{ isWaybill ? '顺丰电子面单' : '顺丰上门取件' }}</strong><p>{{ form.environment === 'sandbox' ? '当前仍为沙箱，只验证接口联调，不用于真实客户寄件。' : '正式环境将按业务操作调用顺丰，可能产生真实运单、预约及费用。' }}</p></div></div>
            </div>
            <div class="sf-section sf-save">
                <div class="sf-actions"><el-button type="primary" :loading="saving" native-type="submit">保存并检查配置</el-button><el-tag :type="!dirty && readiness.ready ? 'success' : 'info'">{{ dirty ? '有未保存修改' : readiness.ready ? '配置项已齐全' : '待配置' }}</el-tag><span>只保存与检查配置，不下单、不预约、不打印。</span></div>
                <div v-if="!dirty && !loadError && !loading" class="sf-checks"><HsxNotice v-if="failedChecks.length" type="warning" :title="'还需处理 ' + failedChecks.length + ' 项'" :default-expanded="true" :closable="false"><ul class="sf-guide"><li v-for="item in failedChecks" :key="item.key">{{ item.label }}：{{ item.message }}</li></ul></HsxNotice><HsxFold v-if="passedChecks.length" title="已通过的配置检查" :summary="passedChecks.length + ' 项 · 不代表真实服务可用'"><ul class="sf-guide"><li v-for="item in passedChecks" :key="item.key">{{ item.label }}</li></ul></HsxFold></div>
            </div>
        </el-form>
        <div class="sf-next"><strong>下一步：{{ isWaybill ? '商城选择顺丰直连，再验证面单' : '回收选择顺丰直连，再验证预约' }}</strong><p>{{ isWaybill ? '在商城电子面单设置选择顺丰直连。先沙箱联调，转正式并确认账号权限后，再使用真实待发货订单；PDF 成功不等于已出纸或已交件。' : '回到回收上门取件配置选择顺丰直连。沙箱只做联调，正式上线前用真实预约核对受理结果、取件安排和客户通知；客户无需打印。' }}</p><el-button type="primary" plain @click="router.push(isWaybill ? '/phone_shop/delivery/electronic_sheet/config' : '/third_party/express_order')">{{ isWaybill ? '去商城选择面单服务' : '去回收设置取件渠道' }}</el-button></div>
    </section>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { HsxFold, HsxNotice } from '@/addon/hsx_components/core'
import SavedCredentialInput from './saved-credential-input.vue'
import { checkSfConfig, getSfConfig, saveSfConfig } from '../api'
import { requestError } from '../utils/presentation'
import { sfDefaults, sfForEditing, sfIdentityChanged, sfSavePayload, type SfScene } from '../utils/sf'

const props = defineProps<{ scene: SfScene }>()
const router = useRouter(), form = reactive<Record<string, any>>(sfDefaults())
const loading = ref(false), saving = ref(false), loadError = ref(''), clearSecret = ref(false), credentialRevision = ref(0)
const stored = ref<Record<string, any>>({}), options = ref<Record<string, any>>({}), readiness = ref<Record<string, any>>({ ready: false, checks: [] }), fingerprint = ref('')
const isWaybill = computed(() => props.scene === 'waybill')
const busy = computed(() => loading.value || saving.value || !!loadError.value)
const dirty = computed(() => JSON.stringify({ ...form, clearSecret: clearSecret.value }) !== fingerprint.value)
const environments = computed(() => options.value.environments || [{ value: 'sandbox', label: '沙箱测试（非真实寄件）' }, { value: 'production', label: '正式环境（真实业务，可能计费）' }])
const products = computed(() => options.value.products || []), payMethods = computed(() => options.value.pay_methods || [])
const failedChecks = computed(() => (readiness.value.checks || []).filter((item: any) => !item.passed))
const passedChecks = computed(() => (readiness.value.checks || []).filter((item: any) => item.passed))
const hasSavedSecret = computed(() => !!stored.value.has_check_word && !clearSecret.value && !sfIdentityChanged(form, stored.value))
function identityChanged() { form.enabled = 0; form.use_ack = 0; form.check_word = ''; clearSecret.value = true; credentialRevision.value++ }
function paymentChanged() {
    form.use_ack = 0
    if (Number(form.pay_method) === 2 && form.monthly_card) {
        form.monthly_card = ''
        ElMessage.info('已选择到付，本页月结卡号已清空；保存后生效，原有运单不受影响')
    }
}
async function requestClearSecret() {
    try { await ElMessageBox.confirm('保存后将清空此业务的顺丰校验码并停用新请求，另一业务配置不会修改。确定标记清空？', '清空校验码', { type: 'warning', confirmButtonText: '标记清空', cancelButtonText: '保留' }) } catch { return }
    identityChanged()
}
async function load() {
    loading.value = true; loadError.value = ''
    try {
        const response = await getSfConfig(props.scene)
        const data = response.data
        if (!data || typeof data !== 'object' || Array.isArray(data)) throw new Error('配置返回不完整，请确认顺丰后端已更新')
        stored.value = data; options.value = data.options || {}; readiness.value = data.readiness || { ready: false, checks: [] }
        Object.assign(form, sfForEditing(data)); clearSecret.value = false; credentialRevision.value++
        fingerprint.value = JSON.stringify({ ...form, clearSecret: false })
    } catch (error) { loadError.value = requestError(error, '读取失败，请重试；读取成功前不会覆盖配置') }
    finally { loading.value = false }
}
async function save() {
    if (busy.value) return
    if (Number(form.pay_method) === 2 && form.monthly_card) return ElMessage.warning('到付不能同时填写月结卡号，请清空后再保存')
    if (form.enabled && form.environment === 'production' && (stored.value.environment !== 'production' || !stored.value.enabled)) {
        try { await ElMessageBox.confirm('开启后，后续业务操作可能生成真实顺丰运单或预约并计费。请确认本站正式账号、月结关系与接口权限已经开通。本次保存本身不下单。', '启用顺丰正式业务', { type: 'warning', confirmButtonText: '已核实，启用正式环境', cancelButtonText: '返回检查' }) } catch { return }
    }
    saving.value = true
    try {
        const saved = await saveSfConfig(props.scene, sfSavePayload(form, clearSecret.value || sfIdentityChanged(form, stored.value)))
        const saveNotice = typeof saved.data?.save_notice === 'string' ? saved.data.save_notice : ''
        await load(); if (loadError.value) return
        const response = await checkSfConfig(props.scene); readiness.value = response.data || { ready: false, checks: [] }
        if (saveNotice) ElMessage.warning(saveNotice)
        else ElMessage.success(readiness.value.ready ? '配置项已通过检查；尚未验证真实顺丰服务' : '已保存，请继续处理未完成的配置项')
    } catch (error) { ElMessage.error(requestError(error, '保存未完成，请核对提示后重试')) }
    finally { saving.value = false }
}
defineExpose({ dirty, loading, saving })
onMounted(load)
</script>

<style scoped>
.sf-config{color:#1f2937}.sf-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:14px}.sf-heading h2{margin:0;font-size:17px}.sf-heading p,.sf-next p{margin:6px 0 0;font-size:12px;line-height:1.8;color:#64748b}.sf-section{margin-top:14px;padding:18px 20px;border:1px solid #e5e7eb;border-radius:8px;background:white}.sf-section h3{font-size:15px;margin:0 0 15px}.sf-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 18px}.sf-grid :deep(.el-select){width:100%}.sf-grid :deep(.el-form-item){min-width:0}.sf-help{width:100%;margin-top:5px;color:#64748b;font-size:12px;line-height:1.7}.sf-warning{font-size:12px;line-height:1.7;color:#b45309}.sf-guide{padding-left:19px;margin:0 0 10px;font-size:13px;color:#475569;line-height:1.8}.sf-config a{color:var(--el-color-primary);font-size:13px}.sf-ack{margin-top:16px;align-items:flex-start;height:auto}.sf-ack :deep(.el-checkbox__label){white-space:normal;line-height:1.7}.sf-ack :deep(.el-checkbox__input){margin-top:4px}.sf-enable{display:flex;gap:12px;align-items:flex-start;margin-top:15px}.sf-enable strong{font-size:13px}.sf-enable p{font-size:12px;line-height:1.7;color:#64748b;margin:3px 0 0}.sf-actions{display:flex;align-items:center;flex-wrap:wrap;gap:10px}.sf-actions>span{font-size:12px;color:#64748b}.sf-checks{display:grid;gap:10px;margin-top:14px}.sf-next{padding:18px 20px;margin-top:14px;border:1px solid #e5e7eb;border-radius:8px;background:#fff}.sf-next strong{font-size:14px}.sf-next .el-button{margin-top:12px}@media(max-width:640px){.sf-grid{grid-template-columns:1fr}.sf-heading{flex-wrap:wrap}.sf-section,.sf-next{padding:14px}}
</style>
