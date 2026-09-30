<template>
    <HsxPage title="物流接入与上手" subtitle="先跑通一笔真实业务，再批量使用。原有快递鸟无需迁移。" content-width="narrow" class="express-page" :loading="loading">
        <template #extra><el-button @click="router.push('/hsx_express/tasks')">运单与打印记录</el-button></template>
        <HsxNotice v-if="loadError" type="error" title="配置读取失败" :description="loadError" :default-expanded="true" :closable="false" />
        <section class="section">
            <div class="section-title"><span class="step">1</span><h2>选择起步方案</h2></div>
            <div class="plans">
                <button type="button" class="plan" :class="{ selected: form.scene === 'waybill_web' }" @click="selectScene('waybill_web')">
                    <span class="plan-badge">建议先从这里开始</span><strong>电脑打印面单</strong><span>生成面单文件，电脑打开后打印。不必先购买云打印机。</span><small>需要已开通的电子面单账号和匹配纸张的打印设备。</small>
                </button>
                <button type="button" class="plan" :class="{ selected: form.scene === 'waybill_cloud' }" @click="selectScene('waybill_cloud')">
                    <span class="plan-badge secondary">已有兼容设备</span><strong>云打印面单</strong><span>面单发送到指定云打印机，并等待打印结果回调。</span><small>购机前先确认快递100支持的设备型号、模板和纸张。</small>
                </button>
                <article class="plan guide-plan"><span class="plan-badge secondary">独立业务</span><strong>回收上门取件</strong><span>由快递员上门收件，不等于店内电子面单打印。</span><small>目前继续使用回收插件的寄件通道，需单独开通、配置。</small><el-button link type="primary" @click="router.push('/third_party/express_order')">前往回收寄件配置 →</el-button></article>
            </div>
        </section>
        <el-form label-position="top" :model="form" :disabled="loading || saving || !!loadError" @submit.prevent="saveAndCheck">
            <section class="section">
                <div class="section-title"><span class="step">2</span><h2>选择快递，准备本站账号</h2><a href="https://api.kuaidi100.com/" target="_blank" rel="noopener noreferrer">打开快递100开放平台 ↗</a></div>
                <div class="form-grid">
                    <el-form-item label="快递公司" required><el-select v-model="form.carrier" filterable aria-label="快递公司" placeholder="搜索并选择已开通的快递，例如中通" @change="carrierChanged"><el-option v-for="item in options.carriers || []" :key="item.value" :label="item.label" :value="item.value" /></el-select><div class="help">仅需配置当前选择的一家公司。更换快递后需重新填写该公司的账号、产品和模板，原运单继续使用原账号。</div></el-form-item>
                    <el-form-item label="快递产品（需确认开通）" required><el-select v-model="form.exp_type" filterable :disabled="!products.length" aria-label="快递产品" placeholder="从官方产品选项中选择" @change="form.use_ack = 0"><el-option v-for="item in products" :key="item.value" :label="item.label" :value="item.value" /></el-select><div class="help">目录不是本站已开通清单。请按网点确认的产品选择，不需要手写产品名。</div></el-form-item>
                </div>
                <HsxNotice v-if="selectedCarrier && !selectedCarrier.available" type="warning" :title="selectedCarrier.label + ' · 暂不能启用当前打印方案'" :description="selectedCarrier.unavailable_reason" :default-expanded="true" :closable="false" />
                <HsxNotice title="快递100接口账号与快递公司面单账号分别配置" description="上方选哪家快递，下方只要求该公司的资料。已开通中通时，无需填写顺丰月结号或顺丰密钥。接口套餐费不等于运费或硬件费用，请使用本站自己的账号。" :default-expanded="true" />
                <div class="form-grid">
                    <el-form-item label="快递100 Key"><el-input v-model="form.key" type="password" show-password autocomplete="new-password" :placeholder="secretPlaceholder('key')" /><div class="help">从快递100开放平台的账号/授权信息获取，不是快递月结号。</div></el-form-item>
                    <el-form-item label="快递100 Secret"><el-input v-model="form.secret" type="password" show-password autocomplete="new-password" :placeholder="secretPlaceholder('secret')" /><div class="help">与上面的 Key 属于同一个账号。已保存时留空即可保留。</div></el-form-item>
                </div>
                <template v-if="selectedCarrier">
                    <h3 class="account-title">{{ selectedCarrier.label }} · 网点电子面单资料</h3>
                    <div class="form-grid" data-testid="carrier-account-fields">
                        <el-form-item v-for="field in accountFields" :key="form.carrier + field.key" :label="field.label" :required="field.required">
                            <el-input v-model.trim="form[field.key]" :aria-label="field.label" :type="field.secret ? 'password' : 'text'" :show-password="field.secret" autocomplete="new-password" :placeholder="field.secret ? secretPlaceholder(field.key) : '填写' + field.label" />
                            <div class="help">{{ field.help }} <span class="field-code">{{ field.api_key }}</span></div>
                        </el-form-item>
                    </div>
                    <div v-if="fixedFields.length" class="help">接口固定标识由系统自动填写：<span v-for="field in fixedFields" :key="field.key">{{ field.api_key }}={{ field.fixed }} </span>，不用额外申请。</div>
                    <div class="help">仅适用于网点面单账号；菜鸟、淘宝等第三方授权账号不能直接混填。<a :href="options.catalog?.source || 'https://api.kuaidi100.com/document/5f0ff6e82977d50a94e10237'" target="_blank" rel="noopener noreferrer">查看官方参数字典 ↗</a><span v-if="options.catalog?.checked_at">（核对日期 {{ options.catalog.checked_at }}）</span></div>
                </template>
            </section>
            <section class="section">
                <div class="section-title"><span class="step">3</span><h2>设置结算与打印</h2><a href="https://api.kuaidi100.com/document/dianzimiandanV2" target="_blank" rel="noopener noreferrer">官方面单文档 ↗</a></div>
                <div class="form-grid">
                    <el-form-item label="运费付款方式"><el-select v-model="form.pay_type" :disabled="!selectedCarrier" placeholder="选择付款方式" @change="form.use_ack = 0"><el-option v-for="item in selectedCarrier?.pay_types || []" :key="item.value" :label="item.label" :value="item.value" /></el-select><div class="help">按当前快递的能力提供选项，仍需确认本站账号权限，与商城商品付款方式无关。</div></el-form-item>
                    <el-form-item label="快递100面单模板 ID"><el-input v-model.trim="form.template_id" placeholder="从快递100模板管理复制 ID" /><div class="help">使用当前快递、打印方式与纸张尺寸对应的 V2 模板；不是模板名称。</div></el-form-item>
                    <el-form-item v-if="form.scene === 'waybill_cloud'" label="云打印机设备码 SIID"><el-input v-model.trim="form.device_id" placeholder="复制已绑定设备的 SIID" /><div class="help">在快递100设备管理中获取。确认设备在线、纸张正确，并属于当前账号。</div></el-form-item>
                    <el-form-item label="本站公网 HTTPS 根地址"><el-input v-model.trim="form.callback_base_url" placeholder="例如 https://example.com，不含业务路径" /><div class="help">服务器必须可被快递100访问；不要填 localhost。回调路径由系统生成。</div></el-form-item>
                </div>
                <el-checkbox v-model="form.use_ack" :true-label="1" :false-label="0" class="ack">我已向承运商 / 快递100确认：此账号开通了所选产品、付款方式及电子面单权限。</el-checkbox>
                <div class="enable-row"><el-switch v-model="form.enabled" :disabled="!selectedCarrier?.available" :active-value="1" :inactive-value="0" /><div><strong>启用当前快递配置，允许商城取号</strong><p>开启后仍需在商城电子面单设置中选择本插件。更换快递需重新启用；关闭不会取消历史运单，也不会自动切换快递鸟。</p></div></div>
            </section>
            <section class="section">
                <div class="section-title"><span class="step">4</span><h2>保存并检查配置</h2><el-tag :type="readiness.ready && !dirty ? 'success' : 'info'">{{ dirty ? '有未保存修改' : readiness.ready ? '配置项已齐全' : '待配置检查' }}</el-tag></div>
                <HsxNotice type="warning" title="配置检查不下单、不扣费，也不代表真实发货已验证" description="这里只检查必填项与格式。账号权限、服务地区、月结额度、打印机兼容性，需要下一步真实试单确认。" :default-expanded="true" />
                <div v-if="readiness.checks?.length && !dirty" class="checks"><div v-for="item in readiness.checks" :key="item.key" class="check"><el-tag :type="item.passed ? 'success' : 'warning'" size="small">{{ item.passed ? '已满足' : '待处理' }}</el-tag><div><strong>{{ item.label }}</strong><p>{{ item.message }}</p></div></div></div>
                <div v-if="savedCallback" class="callback"><span>系统生成的回调地址</span><code>{{ savedCallback }}</code></div>
                <div class="save-actions"><el-button type="primary" :loading="saving" native-type="submit">保存并检查配置</el-button><span>只保存本站配置；不发起快递订单。</span></div>
            </section>
        </el-form>
        <section class="section">
            <div class="section-title"><span class="step">5</span><h2>从商城跑通一笔真实发货</h2></div>
            <ol class="trial-steps"><li>在商城电子面单设置中选择「快递100」，再从真实订单进入面单发货，填写真实寄件地址。</li><li>选择一笔确实需要发出的订单，核对收件人、地址、快递产品后取号。此步会调用真实服务，可能计费。</li><li>{{ form.scene === 'waybill_cloud' ? '检查云打印机出纸，并在运单记录中查看打印回执。' : '打开生成的面单文件，在电脑打印并核对条码清晰度及纸张尺寸。' }}</li><li>核对并贴单，按商城流程完成发货。取号成功、打印成功，都不代表快递已揽收。</li></ol>
            <div class="trial-actions"><el-button :disabled="!readiness.ready || dirty || !!loadError" type="primary" plain @click="router.push('/phone_shop/delivery/electronic_sheet/config')">去商城选择面单服务</el-button><el-button @click="router.push('/phone_shop/order/index')">商城订单发货</el-button><el-button @click="router.push('/hsx_express/tasks')">查看运单与异常</el-button></div>
            <HsxFold title="第一次失败了怎么办？" summary="先看明确原因；结果不确定时不重复下单"><ul class="faq"><li>账号 / 产品未开通：找快递100或签约网点核对权限，再修改配置。</li><li>地址或电话不完整：修改原业务订单资料，不在日志里另建订单。</li><li>面单已生成但没出纸：检查设备与纸张，补打原运单，不重新取号。</li><li>请求超时、结果待核实：先查询快递100原任务；不要把超时当作失败或切换服务商重下。</li><li>需要取消：取消快递运单不会自动关闭商城订单，也不会自动退款。</li></ul></HsxFold>
        </section>
    </HsxPage>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { HsxFold, HsxNotice, HsxPage } from '@/addon/hsx_components/core'
import { checkExpressConfig, getExpressConfig, saveExpressConfig } from '../../api'
import { applyCarrierSelection, configDefaults, configForEditing, requestError } from '../../utils/presentation'

const router = useRouter()
const loading = ref(false), saving = ref(false), loadError = ref('')
const form = reactive<Record<string, any>>(configDefaults())
const stored = ref<Record<string, any>>({}), options = ref<Record<string, any>>({})
const readiness = ref<Record<string, any>>({ ready: false, checks: [] })
const savedFingerprint = ref(''), savedCallback = ref('')
const dirty = computed(() => JSON.stringify(form) !== savedFingerprint.value)
const selectedCarrier = computed(() => (options.value.carriers || []).find((item: any) => item.value === form.carrier))
const products = computed(() => selectedCarrier.value?.products || [])
const accountFields = computed(() => (selectedCarrier.value?.fields || []).filter((field: any) => field.fixed === null))
const fixedFields = computed(() => (selectedCarrier.value?.fields || []).filter((field: any) => field.fixed !== null))
function secretPlaceholder(field: string) {
    const sameAccount = !field.startsWith('partner_') || stored.value.carrier === form.carrier
    return sameAccount && stored.value[`has_${field}`] ? '已保存，留空保留；输入新值可替换' : '尚未配置，请从服务商或签约网点获取'
}
function selectScene(scene: string) { if (!loading.value && !saving.value && !loadError.value) form.scene = scene }
function carrierChanged() { applyCarrierSelection(form, selectedCarrier.value) }
async function load() {
    loading.value = true
    loadError.value = ''
    try {
        const result = await getExpressConfig()
        stored.value = result.data || {}
        options.value = stored.value.options || {}
        Object.assign(form, configForEditing(stored.value))
        readiness.value = stored.value.readiness || { ready: false, checks: [] }
        savedCallback.value = stored.value.callback_url || ''
        savedFingerprint.value = JSON.stringify(form)
    } catch (error: any) { loadError.value = requestError(error, '请刷新页面重试；读取失败时不能覆盖已有配置。') }
    finally { loading.value = false }
}
async function saveAndCheck() {
    if (saving.value || loading.value || loadError.value) return
    saving.value = true
    try {
        await saveExpressConfig({ ...form })
        await load()
        if (loadError.value) return
        const result = await checkExpressConfig()
        readiness.value = result.data || { ready: false, checks: [] }
        ElMessage.success(readiness.value.ready ? '配置检查通过；请继续真实试单验证' : '配置已保存，请处理检查清单中的待办项')
    } catch (error: any) { ElMessage.error(requestError(error, '保存或检查未完成，请核对提示后重试')) }
    finally { saving.value = false }
}
onMounted(load)
</script>

<style scoped lang="scss">
.express-page { color: #1f2937; }
.section { margin-bottom: 16px; padding: 20px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; }
.section-title { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 16px; }
.section-title h2 { margin: 0; font-size: 16px; }.section-title a { margin-left: auto; font-size: 12px; color: var(--el-color-primary); }
.step { display: inline-flex; justify-content: center; align-items: center; width: 26px; height: 26px; border-radius: 50%; background: #eef2ff; color: #4338ca; font-weight: 600; }
.account-title { margin: 12px 0 0; font-size: 14px; }.field-code { color: #94a3b8; font-size: 11px; }.help a { color: var(--el-color-primary); }
.plans { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 12px; }.plan { display: flex; flex-direction: column; align-items: flex-start; gap: 10px; margin: 0; padding: 16px; border: 1px solid #dce2eb; border-radius: 8px; background: #fff; text-align: left; font: inherit; color: inherit; cursor: pointer; }.plan.selected { border-color: var(--el-color-primary); background: var(--el-color-primary-light-9); box-shadow: 0 0 0 1px var(--el-color-primary); }.plan:focus-visible { outline: 2px solid var(--el-color-primary); outline-offset: 3px; }.plan strong { font-size: 15px; }.plan > span:not(.plan-badge) { font-size: 13px; line-height: 1.7; }.plan small { color: #64748b; line-height: 1.7; }.plan-badge { color: #4338ca; font-size: 11px; font-weight: 600; }.plan-badge.secondary { color: #64748b; }.guide-plan { cursor: default; }
.form-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 0 20px; margin-top: 16px; }.form-grid :deep(.el-select) { width: 100%; }.help { margin-top: 5px; color: #64748b; font-size: 12px; line-height: 1.6; }.ack { height: auto; align-items: flex-start; }.ack :deep(.el-checkbox__label) { white-space: normal; line-height: 1.7; }.ack :deep(.el-checkbox__input) { margin-top: 4px; }
.enable-row { display: flex; align-items: flex-start; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid #edf0f5; }.enable-row strong { font-size: 13px; }.enable-row p { margin: 5px 0 0; font-size: 12px; line-height: 1.6; color: #64748b; }.checks { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 14px; margin-top: 18px; }.check { display: flex; align-items: flex-start; gap: 10px; }.check strong { font-size: 13px; }.check p { margin: 4px 0 0; color: #64748b; font-size: 12px; line-height: 1.6; }.callback { display: grid; gap: 6px; margin-top: 16px; padding: 12px; background: #f8fafc; font-size: 12px; }.callback code { word-break: break-all; }.save-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-top: 20px; }.save-actions span { color: #64748b; font-size: 12px; }.trial-steps,.faq { margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.9; }.trial-steps li,.faq li { margin: 7px 0; }.trial-actions { display: flex; flex-wrap: wrap; gap: 10px; margin: 18px 0; }.trial-actions .el-button + .el-button { margin-left: 0; }
@media(max-width:1100px) { .plans { grid-template-columns: repeat(2,minmax(0,1fr)); }.guide-plan { grid-column: 1 / -1; }.section { padding: 16px; } }
@media(max-width:640px) { .plans,.form-grid,.checks { grid-template-columns: 1fr; }.section-title a { margin-left: 36px; }.section { padding: 14px; }.guide-plan { grid-column: auto; } }
</style>
