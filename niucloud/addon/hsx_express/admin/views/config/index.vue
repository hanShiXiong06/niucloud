<template>
    <HsxPage title="物流接入与上手" subtitle="配置本站账号，再从商城验证首单。原有快递鸟无需迁移。" content-width="narrow" class="express-page" :loading="loading">
        <template #extra><el-button @click="router.push('/hsx_express/tasks')">运单与打印记录</el-button></template>
        <el-tabs v-model="activeConfig" :before-leave="beforeConfigChange" class="config-tabs">
            <el-tab-pane label="快递100 · 面单" name="kuaidi100" />
            <el-tab-pane label="顺丰直连 · 面单" name="sf_waybill" />
            <el-tab-pane label="顺丰直连 · 上门取件" name="sf_pickup" />
        </el-tabs>
        <SfConfigPanel v-if="activeConfig !== 'kuaidi100'" :key="activeConfig" ref="sfPanel" :scene="activeConfig === 'sf_pickup' ? 'pickup' : 'waybill'" />
        <template v-else>
        <HsxNotice v-if="loadError" type="error" title="配置读取失败" :description="loadError" :default-expanded="true" :closable="false">
            <template #actions><el-button link type="primary" :loading="loading" @click="load">重新读取</el-button></template>
        </HsxNotice>
        <el-form label-position="top" :model="form" :disabled="busy" @submit.prevent="saveAndCheck">
            <section class="section">
                <div class="section-title"><span class="step">1</span><h2>选择快递与打印方式</h2></div>
                <div class="plans" role="group" aria-label="打印方式">
                    <button type="button" class="plan" :class="{ selected: form.scene === 'waybill_web' }" :aria-pressed="form.scene === 'waybill_web'" :disabled="busy" @click="selectScene('waybill_web')">
                        <strong>电脑打印面单 <span class="plan-badge">推荐起步</span></strong><span>生成文件，在电脑上打印</span>
                    </button>
                    <button type="button" class="plan" :class="{ selected: form.scene === 'waybill_cloud' }" :aria-pressed="form.scene === 'waybill_cloud'" :disabled="busy" @click="selectScene('waybill_cloud')">
                        <strong>云打印面单</strong><span>发送至已绑定的兼容打印机</span>
                    </button>
                </div>
                <div class="form-grid">
                    <el-form-item label="快递公司" required>
                        <el-select v-model="form.carrier" filterable aria-label="快递公司" placeholder="选择本站已开通的快递" @change="carrierChanged"><el-option v-for="item in options.carriers || []" :key="item.value" :label="item.label" :value="item.value" /></el-select>
                    </el-form-item>
                    <el-form-item label="快递产品" required>
                        <el-select v-model="form.exp_type" filterable :disabled="!products.length" aria-label="快递产品" placeholder="选择网点已为本站开通的产品" @change="form.use_ack = 0"><el-option v-for="item in products" :key="item.value" :label="item.label" :value="item.value" /></el-select>
                    </el-form-item>
                </div>
                <HsxNotice v-if="selectedCarrier && !selectedCarrier.available" type="warning" :title="selectedCarrier.label + ' · 暂不能启用当前打印方案'" :description="selectedCarrier.unavailable_reason" :default-expanded="true" :closable="false" />
                <HsxFold title="方案说明与回收上门取件" summary="产品开通、设备要求、切换影响">
                    <ul class="guide-list"><li>产品列表是参考目录，不代表本站已开通；按签约网点确认的产品选择。</li><li>电脑打印需要匹配纸张的打印设备；云打印购机前先确认快递100支持的型号、模板和纸张。</li><li>更换快递会清空表单中的快递账号、产品和模板，并关闭启用。原运单继续使用原账号；快递100 Key / Secret 保留。</li><li>回收上门取件是独立服务，不是店内打面单。<el-button link type="primary" @click="router.push('/third_party/express_order')">前往回收寄件配置 →</el-button></li></ul>
                </HsxFold>
            </section>

            <section class="section">
                <div class="section-title"><span class="step">2</span><h2>账号与密钥</h2><span class="section-summary">两套账号分别填写</span></div>
                <div class="account-block">
                    <div class="account-heading"><h3>快递100 · 接口账号</h3><a href="https://api.kuaidi100.com/" target="_blank" rel="noopener noreferrer">去快递100获取 ↗</a></div>
                    <div class="form-grid">
                        <el-form-item v-for="field in platformCredentials" :key="credentialRevision + field.key" :label="field.label" required>
                            <SavedCredentialInput v-model="form[field.key]" :field="field.key" :label="field.label" :saved="hasSavedCredential(field.key)" :disabled="busy" />
                        </el-form-item>
                    </div>
                </div>
                <div v-if="selectedCarrier" class="account-block carrier-account">
                    <div class="account-heading"><h3>{{ selectedCarrier.label }} · 电子面单账号</h3><span class="help">由签约网点提供</span></div>
                    <div class="form-grid carrier-fields" data-testid="carrier-account-fields">
                        <el-form-item v-for="field in accountFields" :key="credentialRevision + form.carrier + field.key" :label="accountFieldLabel(field)" :required="field.required">
                            <SavedCredentialInput v-if="field.secret" v-model="form[field.key]" :field="field.key" :label="accountFieldLabel(field)" :saved="hasSavedCredential(field.key)" :disabled="busy" />
                            <el-input v-else v-model.trim="form[field.key]" :aria-label="field.label" :placeholder="'填写' + field.label" autocomplete="off" />
                            <div v-if="field.key === 'partner_key'" class="help">这是{{ selectedCarrier.label }}的密钥，不是上面的快递100 Key。</div>
                        </el-form-item>
                    </div>
                </div>
                <p v-else class="help empty-carrier">先选择快递公司，这里会显示该公司的面单账号和密钥。</p>
                <HsxFold title="账号从哪里获取？" summary="字段说明与官方文档">
                    <p class="guide-text">快递100 Key / Secret 在快递100账号授权信息中获取。下方快递公司账号由签约网点或客户经理提供，两者不能混填。接口套餐费不等于运费或硬件费用。</p>
                    <p class="guide-text">已保存的凭据显示 ******，不会返回原文。点击“更换”后输入新值并保存；留空或取消更换保留原值。</p>
                    <dl v-if="selectedCarrier" class="field-guide"><template v-for="field in accountFields" :key="field.key"><dt>{{ accountFieldLabel(field) }} <code>{{ field.api_key }}</code></dt><dd>{{ field.help }}</dd></template></dl>
                    <p v-if="fixedFields.length" class="guide-text">接口固定标识由系统自动填写：<code v-for="field in fixedFields" :key="field.key">{{ field.api_key }}={{ field.fixed }} </code>，无需申请。</p>
                    <p class="guide-text">仅适用于网点面单账号，不能混填菜鸟、淘宝等第三方授权账号。<a :href="options.catalog?.source || 'https://api.kuaidi100.com/document/5f0ff6e82977d50a94e10237'" target="_blank" rel="noopener noreferrer">官方参数字典 ↗</a><span v-if="options.catalog?.checked_at">（核对 {{ options.catalog.checked_at }}）</span></p>
                </HsxFold>
            </section>

            <section class="section">
                <div class="section-title"><span class="step">3</span><h2>打印设置与启用</h2><a href="https://api.kuaidi100.com/document/dianzimiandanV2" target="_blank" rel="noopener noreferrer">面单文档 ↗</a></div>
                <div class="form-grid settings-grid">
                    <el-form-item label="运费付款方式" required><el-select v-model="form.pay_type" :disabled="!selectedCarrier" placeholder="选择本站已开通的付款方式" @change="form.use_ack = 0"><el-option v-for="item in selectedCarrier?.pay_types || []" :key="item.value" :label="item.label" :value="item.value" /></el-select><div class="help">按网点约定选择，与商城商品付款方式无关。</div></el-form-item>
                    <el-form-item label="快递100面单模板 ID" required><el-input v-model.trim="form.template_id" placeholder="从快递100模板管理复制 ID" /><div class="help">选择匹配当前快递、打印方式与纸张的 V2 模板。</div></el-form-item>
                    <el-form-item v-if="form.scene === 'waybill_cloud'" label="云打印机设备码 SIID" required><el-input v-model.trim="form.device_id" placeholder="复制已绑定设备的 SIID" /><div class="help">从快递100设备管理中获取，确认设备已绑定且在线。</div></el-form-item>
                </div>
                <HsxFold title="回调与服务器配置" :summary="form.callback_base_url || (form.scene === 'waybill_cloud' ? '云打印必填公网 HTTPS 根地址' : '电脑打印可选，云打印必填')" :default-open="form.scene === 'waybill_cloud'" :reset-key="form.scene">
                    <el-form-item label="本站公网 HTTPS 根地址" :required="form.scene === 'waybill_cloud'"><el-input v-model.trim="form.callback_base_url" aria-label="本站公网 HTTPS 根地址" placeholder="例如 https://example.com，不含业务路径" /><div class="help">服务器须可被快递100访问，不要填 localhost；回调路径由系统生成。</div></el-form-item>
                    <div v-if="savedCallback && !dirty" class="callback"><span>已保存的回调地址</span><code>{{ savedCallback }}</code></div>
                    <div v-else-if="dirty" class="help">修改后保存，将按保存结果生成回调地址。</div>
                </HsxFold>
                <el-checkbox v-model="form.use_ack" :true-label="1" :false-label="0" class="ack">我已确认：账号开通了所选快递产品、付款方式及电子面单权限。</el-checkbox>
                <div class="enable-row"><el-switch v-model="form.enabled" :disabled="!selectedCarrier?.available" :active-value="1" :inactive-value="0" aria-label="启用当前快递配置" /><div><strong>启用当前快递配置</strong><p>开启后，商城还需选择「快递100」。关闭不取消历史运单，也不自动改用快递鸟。</p></div></div>

                <div class="save-actions"><el-button type="primary" :loading="saving" native-type="submit">保存并检查配置</el-button><el-tag :type="readiness.ready && !dirty ? 'success' : 'info'">{{ loadError ? '读取失败' : loading ? '读取中' : dirty ? '有未保存修改' : readiness.ready ? '配置项已齐全' : '待完善' }}</el-tag><span>只保存配置，不下单、不扣费。</span></div>
                <div v-if="!dirty && !loadError && !loading" class="check-results">
                    <div v-if="failedChecks.length" class="failed-checks" data-testid="failed-checks" role="status"><strong>还需处理 {{ failedChecks.length }} 项</strong><ul><li v-for="item in failedChecks" :key="item.key"><b>{{ item.label }}</b>：{{ item.message }}</li></ul></div>
                    <HsxFold v-if="passedChecks.length" title="已通过的配置检查" :summary="passedChecks.length + ' 项满足 · 仅必填与格式检查'" data-testid="passed-checks">
                        <ul class="passed-checks"><li v-for="item in passedChecks" :key="item.key"><span aria-hidden="true">✓</span>{{ item.label }}</li></ul>
                    </HsxFold>
                    <p v-if="readiness.ready" class="help">配置齐全不代表真实发货已验证；账号权限、额度和实际打印还需首单验证。</p>
                </div>
            </section>
        </el-form>

        <section class="section next-step">
            <div class="section-title"><h2>下一步：从商城发出第一单</h2><span class="section-summary">真实取号可能计费，请核对后操作</span></div>
            <div class="trial-actions"><el-button :disabled="!readiness.ready || dirty || busy" type="primary" plain @click="router.push('/phone_shop/delivery/electronic_sheet/config')">去商城选择面单服务</el-button><el-button @click="router.push('/phone_shop/order/index')">商城订单发货</el-button></div>
            <HsxFold title="首次使用与异常处理" summary="商城配置、打印、交件与失败处理">
                <ol class="guide-list"><li>在商城电子面单设置选择「快递100」，配置默认寄件地址与物流公司。<template v-if="selectedCarrier">当前快递100公司编码为 <code>{{ form.carrier }}</code>，不是账号 Key；在商城物流公司中唯一对应此编码。</template></li><li>选择真实待发货订单，核对地址、商品和快递产品后取号；此步会调用真实服务，可能计费。</li><li>{{ form.scene === 'waybill_cloud' ? '检查云打印机实际出纸，并在运单记录查看打印回执。' : '打开生成的面单文件打印，核对条码清晰度和纸张尺寸。' }}</li><li>贴单、交件后按商城流程确认发货。取号成功、打印成功都不等于快递已揽收。</li></ol>
                <h3 class="guide-subtitle">遇到问题</h3><ul class="guide-list"><li>账号或产品未开通：联系快递100或签约网点核对权限。</li><li>地址、电话不完整：修改原业务订单资料，不在日志里另建订单。</li><li>面单已生成但未出纸：检查设备与纸张，补打原单，不重新取号。</li><li>请求超时或结果待核实：先核对原任务，勿重复取号或换渠道重下。</li><li>取消快递运单不自动关闭商城订单，也不自动退款。</li></ul>
                <el-button link type="primary" @click="router.push('/hsx_express/tasks')">查看运单与异常 →</el-button>
            </HsxFold>
        </section>
        </template>
    </HsxPage>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { HsxFold, HsxNotice, HsxPage } from '@/addon/hsx_components/core'
import SavedCredentialInput from '../../components/saved-credential-input.vue'
import SfConfigPanel from '../../components/sf-config-panel.vue'
import { checkExpressConfig, getExpressConfig, saveExpressConfig } from '../../api'
import { applyCarrierSelection, configDefaults, configForEditing, requestError } from '../../utils/presentation'

const router = useRouter(), route = useRoute()
const activeConfig = ref(route.query.provider === 'sf_direct' ? route.query.scene === 'pickup' ? 'sf_pickup' : 'sf_waybill' : 'kuaidi100')
const sfPanel = ref<any>(null)
const loading = ref(false), saving = ref(false), loadError = ref('')
const busy = computed(() => loading.value || saving.value || !!loadError.value)
const form = reactive<Record<string, any>>(configDefaults())
const stored = ref<Record<string, any>>({}), options = ref<Record<string, any>>({})
const readiness = ref<Record<string, any>>({ ready: false, checks: [] })
const savedFingerprint = ref(''), savedCallback = ref(''), credentialRevision = ref(0)
const platformCredentials = [{ key: 'key', label: '快递100 Key' }, { key: 'secret', label: '快递100 Secret' }]
const dirty = computed(() => JSON.stringify(form) !== savedFingerprint.value)
const selectedCarrier = computed(() => (options.value.carriers || []).find((item: any) => item.value === form.carrier))
const products = computed(() => selectedCarrier.value?.products || [])
const accountFields = computed(() => (selectedCarrier.value?.fields || []).filter((field: any) => field.fixed === null))
const fixedFields = computed(() => (selectedCarrier.value?.fields || []).filter((field: any) => field.fixed !== null))
const failedChecks = computed(() => (readiness.value.checks || []).filter((item: any) => !item.passed))
const passedChecks = computed(() => (readiness.value.checks || []).filter((item: any) => item.passed))
function hasSavedCredential(field: string) {
    const sameAccount = !field.startsWith('partner_') || stored.value.carrier === form.carrier
    return sameAccount && !!stored.value[`has_${field}`]
}
function accountFieldLabel(field: Record<string, any>) {
    return field.key === 'partner_key' ? `${selectedCarrier.value.label}打单 Key（partnerKey）` : field.label
}
function selectScene(scene: string) { if (!busy.value) form.scene = scene }
function carrierChanged() { applyCarrierSelection(form, selectedCarrier.value) }
async function beforeConfigChange(next: string | number) {
    const sf = sfPanel.value
    if (activeConfig.value === 'kuaidi100' ? loading.value || saving.value : sf?.loading || sf?.saving) {
        ElMessage.warning('正在读取或保存，请完成后再切换配置')
        return false
    }
    const unsaved = activeConfig.value === 'kuaidi100' ? !!savedFingerprint.value && dirty.value : sf?.dirty
    if (unsaved) {
        try { await ElMessageBox.confirm('切换将放弃当前未保存的配置，已保存的其他业务配置不受影响。', '切换物流配置', { confirmButtonText: '放弃修改并切换', cancelButtonText: '继续编辑', type: 'warning' }) } catch { return false }
    }
    const query = { ...route.query }
    if (next === 'kuaidi100') { delete query.provider; delete query.scene; void load() }
    else { query.provider = 'sf_direct'; query.scene = next === 'sf_pickup' ? 'pickup' : 'waybill' }
    await router.replace({ query })
    return true
}
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
        credentialRevision.value++
    } catch (error: any) { loadError.value = requestError(error, '请刷新页面重试；读取失败时不能覆盖已有配置。') }
    finally { loading.value = false }
}
async function saveAndCheck() {
    if (busy.value) return
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
onMounted(() => { if (activeConfig.value === 'kuaidi100') void load() })
</script>

<style scoped lang="scss">
.express-page { color: #1f2937; }
.section { margin-bottom: 14px; padding: 18px 20px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; }
.section-title { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 16px; }
.section-title h2 { margin: 0; font-size: 15px; }.section-title a,.account-heading a { margin-left: auto; font-size: 12px; }
.section-summary { color: #64748b; font-size: 12px; }.step { display: inline-flex; justify-content: center; align-items: center; width: 24px; height: 24px; border-radius: 50%; background: var(--el-color-primary-light-9); color: var(--el-color-primary); font-weight: 600; font-size: 13px; }
.plans { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 12px; }.plan { display: flex; flex-direction: column; align-items: flex-start; gap: 5px; margin: 0; padding: 10px 14px; border: 1px solid #dce2eb; border-radius: 6px; background: #fff; text-align: left; font: inherit; color: inherit; cursor: pointer; }.plan.selected { border-color: var(--el-color-primary); background: var(--el-color-primary-light-9); }.plan:focus-visible { outline: 2px solid var(--el-color-primary); outline-offset: 2px; }.plan:disabled { cursor: not-allowed; opacity: .65; }.plan strong { font-size: 14px; line-height: 22px; }.plan > span { color: #64748b; font-size: 12px; line-height: 20px; }.plan-badge { margin-left: 6px; color: var(--el-color-primary); font-size: 11px; font-weight: 400; white-space: nowrap; }
.account-block + .account-block { margin-top: 2px; padding-top: 16px; border-top: 1px solid #edf0f5; }.account-heading { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }.account-heading h3 { margin: 0; font-size: 14px; line-height: 22px; }.account-heading .help { width: auto; margin: 0 0 0 auto; }
.form-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 0 20px; margin-top: 14px; }.settings-grid { margin-top: 0; }.form-grid :deep(.el-form-item) { min-width: 0; margin-bottom: 16px; }.form-grid :deep(.el-form-item__label) { height: auto; margin-bottom: 6px; line-height: 20px; }.form-grid :deep(.el-select) { width: 100%; }.help { width: 100%; margin-top: 5px; color: #64748b; font-size: 12px; line-height: 1.6; }.empty-carrier { margin: 0 0 16px; }.express-page a { color: var(--el-color-primary); }
.section :deep(.hsx-fold) + :deep(.hsx-fold) { margin-top: 10px; }.section :deep(.hsx-notice) { margin-bottom: 12px; }.guide-list { margin: 0; padding-left: 20px; color: #475569; font-size: 13px; line-height: 1.8; }.guide-list li + li { margin-top: 6px; }.guide-text { margin: 0 0 10px; color: #475569; font-size: 13px; line-height: 1.7; }.guide-text:last-child { margin-bottom: 0; }.field-guide { margin: 0 0 10px; font-size: 13px; line-height: 1.7; }.field-guide dt { font-weight: 500; }.field-guide dd { margin: 0 0 8px; color: #64748b; }.field-guide code { color: #64748b; font-size: 12px; }.guide-subtitle { margin: 14px 0 8px; font-size: 13px; }
.ack { height: auto; align-items: flex-start; margin-top: 16px; }.ack :deep(.el-checkbox__label) { white-space: normal; line-height: 1.7; }.ack :deep(.el-checkbox__input) { margin-top: 4px; }.enable-row { display: flex; align-items: flex-start; gap: 12px; margin-top: 14px; }.enable-row strong { font-size: 13px; }.enable-row p { margin: 3px 0 0; font-size: 12px; line-height: 1.6; color: #64748b; }
.callback { display: grid; gap: 6px; padding: 10px; background: #f8fafc; font-size: 12px; }.callback code { word-break: break-all; }.save-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-top: 18px; padding-top: 16px; border-top: 1px solid #edf0f5; }.save-actions > span:not(.el-tag) { color: #64748b; font-size: 12px; }.check-results { margin-top: 12px; }.failed-checks { margin-bottom: 12px; padding: 12px; border-radius: 6px; background: var(--el-color-warning-light-9); font-size: 13px; line-height: 1.7; }.failed-checks ul { margin: 6px 0 0; padding-left: 20px; }.failed-checks b { font-weight: 500; }.passed-checks { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 8px; list-style: none; margin: 0; padding: 0; font-size: 13px; }.passed-checks span { margin-right: 8px; color: var(--el-color-success); }
.trial-actions { display: flex; flex-wrap: wrap; gap: 10px; margin: 0 0 14px; }.trial-actions .el-button + .el-button { margin-left: 0; }
@media(min-width:1100px) { .carrier-fields { grid-template-columns: repeat(3,minmax(0,1fr)); } }
@media(max-width:640px) { .form-grid,.passed-checks { grid-template-columns: 1fr; }.section { padding: 14px; }.plans { gap: 8px; }.plan { padding: 10px; }.plan-badge { display: block; margin-left: 0; }.section-summary { flex-basis: 100%; }.section :deep(.hsx-fold__trigger) { flex-wrap: wrap; }.section :deep(.hsx-fold__title) { flex: 1; }.section :deep(.hsx-fold__summary) { order: 1; flex-basis: 100%; padding-left: 22px; }.account-heading .help { margin-left: 0; } }
</style>
