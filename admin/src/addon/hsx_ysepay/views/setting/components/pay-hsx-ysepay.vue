<template>
    <el-dialog v-model="showDialog" title="配置银盛支付" width="620px" :destroy-on-close="true">
        <el-alert
            title="JS支付与聚合收银台共用证书、查单、退款和回调配置，仅支付入口不同。"
            type="info"
            :closable="false"
            class="mb-[18px]"
        />

        <el-tabs v-model="activeTab">
            <el-tab-pane label="基础配置" name="basic">
                <el-form ref="formRef" :model="formData" :rules="formRules" label-width="126px" class="page-form">
                    <el-form-item label="运行环境" prop="config.environment">
                        <el-radio-group v-model="formData.config.environment">
                            <el-radio-button label="sandbox">测试环境</el-radio-button>
                            <el-radio-button label="production">正式环境</el-radio-button>
                        </el-radio-group>
                    </el-form-item>
                    <el-form-item label="支付接入模式">
                        <el-radio-group v-model="formData.config.access_mode">
                            <el-radio-button label="js_pay">JS支付</el-radio-button>
                            <el-radio-button label="cashier">聚合收银台</el-radio-button>
                        </el-radio-group>
                        <div class="form-tip">
                            JS支付用于微信公众号和微信小程序原生拉起；H5、PC、App 等渠道请选择聚合收银台。
                        </div>
                    </el-form-item>
                    <el-form-item label="发起方商户号" prop="config.cert_id">
                        <el-input v-model.trim="formData.config.cert_id" maxlength="32" placeholder="银盛下发的 certId" clearable />
                    </el-form-item>
                    <el-form-item label="收款商户号" prop="config.merc_id">
                        <el-input v-model.trim="formData.config.merc_id" maxlength="32" placeholder="实际收款商户 mercId" clearable />
                    </el-form-item>
                    <el-form-item label="业务代码" prop="config.busi_code">
                        <el-input v-model.trim="formData.config.busi_code" maxlength="16" placeholder="请填写银盛分配的业务代码" clearable />
                    </el-form-item>
                    <template v-if="formData.config.access_mode === 'js_pay'">
                        <el-form-item label="收款商户名称">
                            <el-input v-model.trim="formData.config.merc_name" maxlength="50" placeholder="选填" clearable />
                        </el-form-item>
                        <el-form-item v-if="formData.channel !== 'weapp'" label="公众号 AppID">
                            <el-input v-model.trim="formData.config.wechat_app_id" maxlength="32" placeholder="公众号 AppID" clearable />
                        </el-form-item>
                        <el-form-item v-if="formData.channel !== 'wechat'" label="小程序 AppID">
                            <el-input v-model.trim="formData.config.weapp_app_id" maxlength="32" placeholder="微信小程序 AppID" clearable />
                        </el-form-item>
                    </template>
                    <el-form-item label="支付有效期">
                        <el-input-number
                            v-model="formData.config.payment_valid_time"
                            :min="1"
                            :max="formData.config.access_mode === 'cashier' ? 30 : 1440"
                        />
                        <span class="ml-[8px] text-[var(--el-text-color-secondary)]">分钟</span>
                    </el-form-item>
                    <el-form-item v-if="formData.config.access_mode === 'cashier'" label="支付方式">
                        <el-select v-model="formData.config.pay_mode" placeholder="按当前渠道自动选择" clearable>
                            <el-option label="按当前渠道自动选择" value="" />
                            <el-option label="支付宝服务窗收银台（26）" value="26" />
                            <el-option label="微信公众号收银台（28）" value="28" />
                            <el-option label="微信小程序收银台（29）" value="29" />
                            <el-option label="银联行业收银台（30）" value="30" />
                        </el-select>
                        <div class="form-tip">一般保持自动即可；银盛运营明确要求时再覆盖。</div>
                    </el-form-item>
                </el-form>
            </el-tab-pane>

            <el-tab-pane label="证书" name="certificate">
                <el-form :model="formData" label-width="126px" class="page-form">
                    <el-form-item label="商户 PFX 证书" required>
                        <div class="certificate-row">
                            <el-upload
                                :auto-upload="false"
                                :show-file-list="false"
                                accept=".pfx,.p12"
                                :on-change="handlePfxChange"
                            >
                                <el-button>选择 PFX 文件</el-button>
                            </el-upload>
                            <el-tag v-if="formData.config.merchant_private_cert" type="success" effect="plain">
                                {{ formData.config.merchant_private_cert_filename || '已配置' }}
                            </el-tag>
                        </div>
                        <div class="form-tip">使用银盛下发的 RSA 商户私钥证书，文件内容会转为 Base64 后加密保存。</div>
                    </el-form-item>
                    <el-form-item label="PFX 证书密码" required>
                        <el-input
                            v-model="formData.config.merchant_private_cert_password"
                            type="password"
                            show-password
                            autocomplete="new-password"
                            placeholder="请输入证书密码"
                        />
                    </el-form-item>
                    <el-form-item label="银盛公钥证书" required>
                        <div class="certificate-row">
                            <el-upload
                                :auto-upload="false"
                                :show-file-list="false"
                                accept=".cer,.crt,.pem"
                                :on-change="handlePublicCertChange"
                            >
                                <el-button>选择 CER 文件</el-button>
                            </el-upload>
                            <el-tag v-if="formData.config.ysepay_public_cert" type="success" effect="plain">
                                {{ formData.config.ysepay_public_cert_filename || '已配置' }}
                            </el-tag>
                        </div>
                        <div class="form-tip">用于验证银盛同步响应和异步通知签名，不能使用商户自己的证书替代。</div>
                    </el-form-item>
                </el-form>
            </el-tab-pane>

            <el-tab-pane label="高级配置" name="advanced">
                <el-form :model="formData" label-width="126px" class="page-form">
                    <el-form-item v-if="formData.config.access_mode === 'cashier'" label="付款限制">
                        <el-select v-model="formData.config.limit_pay">
                            <el-option label="不限制" value="0" />
                            <el-option label="仅储蓄卡" value="1" />
                            <el-option label="仅信用卡" value="2" />
                            <el-option label="仅余额" value="3" />
                            <el-option label="禁用余额" value="4" />
                        </el-select>
                    </el-form-item>
                    <el-form-item v-if="formData.config.access_mode === 'cashier'" label="进入即支付">
                        <el-switch v-model="fastPayEnabled" />
                        <div class="form-tip">开启后进入银盛收银台会直接拉起支付控件。</div>
                    </el-form-item>
                    <el-form-item v-if="formData.config.access_mode === 'cashier'" label="小程序版本">
                        <el-select v-model="formData.config.mini_program_env">
                            <el-option label="正式版" value="release" />
                            <el-option label="体验版" value="trial" />
                            <el-option label="开发版" value="develop" />
                        </el-select>
                    </el-form-item>
                    <el-form-item v-if="formData.config.access_mode === 'cashier'" label="门店编号">
                        <el-input v-model.trim="formData.config.store_id" maxlength="64" placeholder="选填" clearable />
                    </el-form-item>
                    <el-form-item v-if="formData.config.access_mode === 'cashier'" label="预下单版本">
                        <el-input v-model.trim="formData.config.preorder_version" maxlength="10" placeholder="6.3" />
                    </el-form-item>
                    <template v-else>
                        <el-form-item label="限制信用卡">
                            <el-switch
                                :model-value="formData.config.limit_credit_pay === '1'"
                                @change="formData.config.limit_credit_pay = $event ? '1' : '0'"
                            />
                        </el-form-item>
                        <el-form-item label="允许重复支付">
                            <el-switch
                                :model-value="formData.config.allow_repeat_pay === 'Y'"
                                @change="formData.config.allow_repeat_pay = $event ? 'Y' : 'N'"
                            />
                        </el-form-item>
                        <el-form-item label="JS接口版本">
                            <el-input v-model.trim="formData.config.js_pay_version" maxlength="10" placeholder="1.3" />
                        </el-form-item>
                        <el-form-item label="JS支付网关">
                            <el-input v-model.trim="formData.config.js_gateway_base_url" placeholder="选填，必须为 HTTPS" clearable />
                            <div class="form-tip">通常留空，只有银盛明确提供独立地址时填写。</div>
                        </el-form-item>
                    </template>
                    <el-form-item label="请求超时">
                        <el-input-number v-model="formData.config.timeout" :min="3" :max="60" />
                        <span class="ml-[8px] text-[var(--el-text-color-secondary)]">秒</span>
                    </el-form-item>
                    <el-form-item label="自定义网关">
                        <el-input v-model.trim="formData.config.gateway_base_url" placeholder="选填，必须为 HTTPS" clearable />
                    </el-form-item>
                    <el-form-item label="查单网关">
                        <el-input v-model.trim="formData.config.query_gateway_base_url" placeholder="选填，必须为 HTTPS" clearable />
                        <div class="form-tip">用于银盛提供独立测试查单域名的场景，正式环境通常无需填写。</div>
                    </el-form-item>
                </el-form>
            </el-tab-pane>
        </el-tabs>

        <template #footer>
            <el-button @click="cancel">取消</el-button>
            <el-button type="primary" :loading="loading" @click="confirm(formRef)">保存配置</el-button>
        </template>
    </el-dialog>
</template>

<script lang="ts" setup>
import { computed, reactive, ref } from 'vue'
import type { FormInstance, FormRules, UploadFile } from 'element-plus'
import { ElMessage } from 'element-plus'
import { cloneDeep } from 'lodash-es'

const createInitialData = () => ({
    type: 'hsx_ysepay',
    channel: '',
    status: 0,
    is_default: 0,
    config: {
        environment: 'sandbox',
        access_mode: 'js_pay',
        cert_id: '',
        merc_id: '',
        merc_name: '',
        busi_code: '',
        wechat_app_id: '',
        weapp_app_id: '',
        merchant_private_cert: '',
        merchant_private_cert_filename: '',
        merchant_private_cert_password: '',
        ysepay_public_cert: '',
        ysepay_public_cert_filename: '',
        payment_valid_time: 30,
        limit_credit_pay: '0',
        allow_repeat_pay: 'N',
        js_pay_version: '1.3',
        js_gateway_base_url: '',
        pay_mode: '',
        limit_pay: '0',
        is_fast_pay: '01',
        mini_program_env: 'release',
        store_id: '',
        preorder_version: '6.3',
        timeout: 15,
        gateway_base_url: '',
        query_gateway_base_url: ''
    }
})

const showDialog = ref(false)
const loading = ref(false)
const activeTab = ref('basic')
const initData = ref<any>(null)
const formData: Record<string, any> = reactive(createInitialData())
const formRef = ref<FormInstance>()
const emit = defineEmits(['complete'])

const formRules: FormRules = {
    'config.cert_id': [{ required: true, message: '请输入发起方商户号', trigger: 'blur' }],
    'config.merc_id': [{ required: true, message: '请输入收款商户号', trigger: 'blur' }],
    'config.busi_code': [{ required: true, message: '请输入业务代码', trigger: 'blur' }]
}

const fastPayEnabled = computed({
    get: () => formData.config.is_fast_pay === '01',
    set: (value: boolean) => {
        formData.config.is_fast_pay = value ? '01' : '00'
    }
})

const readCertificate = (file: UploadFile, field: string, filenameField: string) => {
    if (!file.raw) return
    const reader = new FileReader()
    reader.onload = () => {
        const result = String(reader.result || '')
        formData.config[field] = result.includes(',') ? result.split(',')[1] : result
        formData.config[filenameField] = file.name
    }
    reader.onerror = () => ElMessage.error('证书读取失败，请重新选择')
    reader.readAsDataURL(file.raw)
}

const handlePfxChange = (file: UploadFile) => {
    readCertificate(file, 'merchant_private_cert', 'merchant_private_cert_filename')
}

const handlePublicCertChange = (file: UploadFile) => {
    readCertificate(file, 'ysepay_public_cert', 'ysepay_public_cert_filename')
}

const certificateReady = () => {
    if (!formData.config.merchant_private_cert || !formData.config.merchant_private_cert_password) {
        activeTab.value = 'certificate'
        ElMessage.warning('请配置商户 PFX 证书和证书密码')
        return false
    }
    if (!formData.config.ysepay_public_cert) {
        activeTab.value = 'certificate'
        ElMessage.warning('请配置银盛平台公钥证书')
        return false
    }
    return true
}

const accessModeReady = () => {
    if (formData.config.access_mode !== 'js_pay') return true
    if (!['wechat', 'weapp'].includes(formData.channel)) {
        activeTab.value = 'basic'
        ElMessage.warning('当前渠道不支持 JS 支付，请选择聚合收银台')
        return false
    }
    const appId = formData.channel === 'weapp'
        ? formData.config.weapp_app_id
        : formData.config.wechat_app_id
    if (!appId) {
        activeTab.value = 'basic'
        ElMessage.warning(formData.channel === 'weapp' ? '请填写微信小程序 AppID' : '请填写微信公众号 AppID')
        return false
    }
    return true
}

const confirm = async (formEl?: FormInstance) => {
    if (!formEl || loading.value) return
    const valid = await formEl.validate().catch(() => false)
    if (!valid || !certificateReady() || !accessModeReady()) return
    emit('complete', cloneDeep(formData))
    showDialog.value = false
}

const cancel = () => {
    if (initData.value) setFormData(initData.value)
    emit('complete', cloneDeep(formData))
    showDialog.value = false
}

const setFormData = async (data: any = null) => {
    loading.value = true
    initData.value = cloneDeep(data)
    Object.assign(formData, createInitialData())
    if (data) {
        const merged = createInitialData()
        Object.assign(merged, cloneDeep(data))
        merged.config = { ...createInitialData().config, ...(cloneDeep(data.config) || {}) }
        merged.channel = data.redio_key ? data.redio_key.split('_')[0] : (data.channel || '')
        merged.status = Number(merged.status)
        Object.assign(formData, merged)
    }
    activeTab.value = 'basic'
    loading.value = false
}

const enableVerify = () => {
    return Boolean(
        formData.config.cert_id
        && formData.config.merc_id
        && formData.config.busi_code
        && (formData.config.access_mode !== 'js_pay'
            || (formData.channel === 'wechat' && formData.config.wechat_app_id)
            || (formData.channel === 'weapp' && formData.config.weapp_app_id))
        && formData.config.merchant_private_cert
        && formData.config.merchant_private_cert_password
        && formData.config.ysepay_public_cert
    )
}

defineExpose({ showDialog, setFormData, enableVerify })
</script>

<style lang="scss" scoped>
.certificate-row {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
}
</style>
