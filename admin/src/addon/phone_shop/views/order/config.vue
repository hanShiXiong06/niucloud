<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <div class="flex justify-between items-center">
                <span class="text-page-title">{{ pageName }}</span>
            </div>

            <el-form :model="formData" label-width="95" ref="formRef" :rules="rules" class="page-form" v-loading="loading">
                <el-card class="box-card !border-none" shadow="never">
                    <div class="flex justify-start items-center">
                        <h3 class="panel-title !text-sm pl-[15px]">{{ t('closeOrderInfo') }}</h3>
                        <el-form-item class="ml-[-80px]" prop="is_close">
                            <el-checkbox v-model="formData.is_close"  true-label="1" false-label="2" />
                        </el-form-item>
                    </div>
                    <el-form-item prop="close_length" v-if="formData.is_close == '1'">
                        <div>
                            <p class="!text-sm">
                                <span>{{ t('closeOrderInfoLeft') }}</span>
                                <el-input v-model.trim="formData.close_length" class="!w-[120px] mx-[10px]" @keyup="filterNumber($event)" clearable />
                                <span>{{ t('closeOrderInfoRight') }}</span>
                            </p>
                            <p class="text-[12px] text-[#a9a9a9] leading-normal  mt-[5px]">{{ t('closeOrderInfoBottom') }}</p>
                        </div>
                    </el-form-item>
                </el-card>
                <el-card class="box-card !border-none" shadow="never">
                    <div class="flex justify-start items-center">
                        <h3 class="panel-title !text-sm pl-[15px]">{{ t('confirm') }}</h3>
                        <el-form-item  class="ml-[-80px]"  prop="is_finish">
                            <el-checkbox v-model="formData.is_finish"  true-label="1" false-label="2" />
                        </el-form-item>
                    </div>
                    <el-form-item prop="finish_length" v-if="formData.is_finish == '1'">
                        <div>
                            <p class="!text-sm">
                                <span>{{ t('confirmLeft') }}</span>
                                <el-input v-model.trim="formData.finish_length" class="!w-[120px] mx-[10px]" @keyup="filterNumber($event)" clearable />
                                <span>{{ t('confirmRight') }}</span>
                            </p>
                            <p class="text-[12px] text-[#a9a9a9] leading-normal  mt-[5px]">{{ t('confirmBottom') }}</p>
                        </div>
                    </el-form-item>
                </el-card>
                <el-card class="box-card !border-none" shadow="never">
                    <div class="flex justify-start items-center">
                        <h3 class="panel-title !text-sm pl-[15px]">{{ t('refund') }}</h3>
                        <el-form-item  class="ml-[-80px]" prop="no_allow_refund">
                            <el-checkbox v-model="formData.no_allow_refund"  :true-label="1" :false-label="2" />
                        </el-form-item>
                    </div>
                    <el-form-item prop="refund_length" v-if="formData.no_allow_refund == '1'">
                        <div>
                            <p class="!text-sm">
                                <span>{{ t('refundLeft') }}</span>
                                <el-input v-model.trim="formData.refund_length" class="!w-[120px] mx-[10px]" @keyup="filterNumber($event)" clearable />
                                <span>{{ t('refundRight') }}</span>
                            </p>
                            <p class="text-[12px] text-[#a9a9a9] leading-normal  mt-[5px]">{{ t('refundBottom') }}</p>
                        </div>
                    </el-form-item>
                </el-card>
                <!-- 万能表单 -->
                <el-card class="box-card !border-none" shadow="never">
                    <h3 class="panel-title !text-sm pl-[15px]">{{ t('diyForm') }}</h3>
                    <el-form-item>
                        <el-select v-model="formData.form_id" :placeholder="t('diyFormPlaceholder')" clearable>
                            <el-option v-for="item in diyFormOptions" :key="item.form_id" :label="item.page_title" :value="item.form_id" />
                        </el-select>
                        <div class="ml-[10px]">
                            <span class="cursor-pointer text-primary mr-[10px]" @click="refreshDiyForm(true)">{{ t('refresh') }}</span>
                            <span class="cursor-pointer text-primary" @click="toDiyFormEvent">{{ t('addDiyForm') }}</span>
                        </div>
                    </el-form-item>
                </el-card>
                <el-card class="box-card !border-none" shadow="never">
                    <h3 class="panel-title !text-sm pl-[15px]">{{ t('evaluate') }}</h3>
                    <el-form-item prop="refund_length">
                        <span>{{ t('isEvaluate') }}</span>
                        <el-radio-group class="mx-[10px]" v-model="formData.is_evaluate">
                            <el-radio :label="1">{{ t('isEvaluateOpen') }}</el-radio>
                            <el-radio :label="0">{{ t('isEvaluateClose') }}</el-radio>
                        </el-radio-group>
                    </el-form-item>
                    <el-form-item prop="refund_length">
                        <span>{{ t('evaluateIsToExamine') }}</span>
                        <el-radio-group class="mx-[10px]" v-model="formData.evaluate_is_to_examine">
                            <el-radio :label="1">{{ t('isEvaluateOpen') }}</el-radio>
                            <el-radio :label="0">{{ t('isEvaluateClose') }}</el-radio>
                        </el-radio-group>
                    </el-form-item>
                    <el-form-item prop="refund_length">
                        <span>{{ t('evaluateIsShow') }}</span>
                        <el-radio-group class="mx-[10px]" v-model="formData.evaluate_is_show">
                            <el-radio :label="1">{{ t('isEvaluateOpen') }}</el-radio>
                            <el-radio :label="0">{{ t('isEvaluateClose') }}</el-radio>
                        </el-radio-group>
                    </el-form-item>
                </el-card>
                <el-card class="box-card !border-none" shadow="never">
                    <h3 class="panel-title !text-sm pl-[15px]">商城成交方式</h3>
                    <el-alert
                        title="建议将线下支付设为首选：客户提交后立即锁定设备，由业务员在订单详情完成收款、挂账和交付。"
                        type="info"
                        :closable="false"
                        show-icon
                        class="mb-[18px]"
                    />
                    <el-form-item label="线下支付">
                        <el-switch v-model="formData.offline_order_enabled" :active-value="1" :inactive-value="0" />
                        <span class="ml-[12px] text-[12px] text-[#999]">提交订单后锁定设备，由负责人接手；付款凭证可按下方配置开放</span>
                    </el-form-item>
                    <el-form-item label="设为首选" v-if="formData.offline_order_enabled == 1">
                        <el-switch v-model="formData.offline_order_default" :active-value="1" :inactive-value="0" />
                    </el-form-item>
                    <el-form-item label="同行线下支付" v-if="formData.offline_order_enabled == 1">
                        <el-switch v-model="formData.offline_peer_enabled" :active-value="1" :inactive-value="0" />
                    </el-form-item>
                    <el-form-item label="处理时限" v-if="formData.offline_order_enabled == 1">
                        <el-input-number
                            v-model="formData.offline_timeout_minutes"
                            :min="15"
                            :max="10080"
                            :step="15"
                            controls-position="right"
                        />
                        <span class="ml-[8px]">分钟</span>
                        <span class="ml-[12px] text-[12px] text-[#999]">超时未处理将按未付款订单规则释放库存</span>
                    </el-form-item>
                    <el-form-item label="客户提示" v-if="formData.offline_order_enabled == 1">
                        <el-input
                            v-model.trim="formData.offline_contact_tip"
                            maxlength="120"
                            show-word-limit
                            class="!w-[620px]"
                        />
                    </el-form-item>
                    <el-form-item label="付款二维码" v-if="formData.offline_order_enabled == 1">
                        <div>
                            <upload-image v-model="formData.offline_payment_qrcode" :limit="1" width="120px" height="120px" image-text="上传收款码" />
                            <div class="mt-[6px] text-[12px] text-[#999]">客户提交线下订单后可在订单详情查看并上传付款凭证；不配置时仅展示联系门店。</div>
                        </div>
                    </el-form-item>
                    <el-form-item label="付款说明" v-if="formData.offline_order_enabled == 1">
                        <el-input v-model.trim="formData.offline_payment_tip" type="textarea" :rows="2" maxlength="200" show-word-limit class="!w-[620px]" />
                    </el-form-item>
                    <el-form-item label="客户上传凭证" v-if="formData.offline_order_enabled == 1">
                        <el-switch v-model="formData.offline_voucher_enabled" :active-value="1" :inactive-value="0" />
                        <span class="ml-[12px] text-[12px] text-[#999]">开启后，客户可在订单详情上传付款记录，负责人审核后确认收款</span>
                    </el-form-item>
                    <el-form-item label="处理后继续锁单" v-if="formData.offline_order_enabled == 1">
                        <el-switch v-model="formData.offline_hold_on_progress" :active-value="1" :inactive-value="0" />
                        <span class="ml-[12px] text-[12px] text-[#999]">客户提交凭证或员工确认已联系后暂停自动关闭；建议开启</span>
                    </el-form-item>
                    <template v-if="formData.offline_order_enabled == 1">
                        <el-divider content-position="left">订单负责人</el-divider>
                        <el-alert
                            title="选中的人员都会收到企业微信待办；默认负责人同时展示给客户，负责主动联系、找机、收款和交付。"
                            type="success"
                            :closable="false"
                            show-icon
                            class="mb-[18px]"
                        />
                        <el-form-item label="接单管理员" required>
                            <el-select v-model="selectedHandlerUids" multiple filterable collapse-tags collapse-tags-tooltip class="!w-[620px]" placeholder="选择一个或多个接单人员">
                                <el-option v-for="user in allUserList" :key="user.uid" :label="user.real_name || user.username || user.mobile" :value="Number(user.uid)" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="默认负责人" required>
                            <el-select v-model="formData.offline_default_handler_uid" class="!w-[320px]" placeholder="客户到店默认寻找的负责人" @change="syncDefaultHandlerContact">
                                <el-option v-for="user in selectedHandlers" :key="user.uid" :label="user.name" :value="Number(user.uid)" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="客户看到的联系人">
                            <div class="flex gap-[10px]">
                                <el-input v-model.trim="formData.offline_contact_name" class="!w-[240px]" maxlength="100" placeholder="联系人姓名" />
                                <el-input v-model.trim="formData.offline_contact_mobile" class="!w-[240px]" maxlength="30" placeholder="联系电话" />
                            </div>
                            <div class="w-full mt-[5px] text-[12px] text-[#999]">用于下单成功通知和订单详情，可与账号姓名、手机号不同。</div>
                        </el-form-item>
                    </template>
                    <el-form-item label="线上下单">
                        <el-switch v-model="formData.online_order_enabled" :active-value="1" :inactive-value="0" />
                        <span class="ml-[12px] text-[12px] text-[#999]">实际可用方式与牛云支付中心保持同步</span>
                    </el-form-item>
                    <el-form-item label="当前支付渠道" v-if="formData.online_order_enabled == 1">
                        <div class="w-full">
                            <div v-if="enabledPayChannels.length" class="grid grid-cols-1 xl:grid-cols-2 gap-[10px] max-w-[760px]">
                                <div
                                    v-for="channel in enabledPayChannels"
                                    :key="channel.key"
                                    class="rounded-[8px] border border-solid border-[#e5e7eb] bg-[#fafbfc] px-[14px] py-[11px]"
                                >
                                    <div class="flex items-center justify-between gap-[12px]">
                                        <span class="font-medium text-[#1f2937]">{{ channel.name }}</span>
                                        <el-tag size="small" type="success" effect="plain">已同步</el-tag>
                                    </div>
                                    <div class="mt-[8px] flex flex-wrap gap-[6px]">
                                        <el-tag v-for="payType in channel.payTypes" :key="payType.key" size="small" effect="plain">
                                            {{ payType.name }}
                                        </el-tag>
                                    </div>
                                </div>
                            </div>
                            <el-empty v-else :image-size="56" description="支付中心暂未启用在线支付方式" class="!py-[8px] max-w-[760px]" />
                            <div class="mt-[8px] flex items-center gap-[10px]">
                                <span class="text-[12px] text-[#999]">渠道开关、商户号与密钥仍由支付中心统一管理，订单设置只消费启用结果。</span>
                                <el-button link type="primary" @click="toPayCenter">前往支付中心</el-button>
                            </div>
                        </div>
                    </el-form-item>
                    <el-form-item label="同行线上下单" v-if="formData.online_order_enabled == 1">
                        <el-switch v-model="formData.peer_online_enabled" :active-value="1" :inactive-value="0" />
                    </el-form-item>
                    <template v-if="formData.online_order_enabled == 1 && formData.peer_online_enabled == 1">
                        <el-form-item label="默认线上手续费">
                            <el-input-number
                                v-model="peerFeePercent"
                                :min="0"
                                :max="20"
                                :precision="3"
                                :step="0.1"
                                controls-position="right"
                            />
                            <span class="ml-[8px]">%</span>
                            <span class="ml-[12px] text-[12px] text-[#999]">订单创建时尚未选择具体网关，当前作为同行在线成交的统一费率快照</span>
                        </el-form-item>
                        <el-form-item label="手续费承担">
                            <el-radio-group v-model="formData.peer_fee_bearer">
                                <el-radio label="merchant">商家承担</el-radio>
                                <el-radio label="customer">同行客户承担</el-radio>
                            </el-radio-group>
                            <div class="w-full text-[12px] text-[#999] leading-[20px] mt-[5px]">
                                零售价始终不追加手续费；同行客户承担时，系统按净额反推支付金额，确保扣费后覆盖同行销售价。
                            </div>
                        </el-form-item>
                    </template>
                </el-card>
                <el-card class="box-card !border-none" shadow="never">
                    <h3 class="panel-title !text-sm pl-[15px]">{{ t('invoice') }}</h3>
                    <el-form-item>
                        <span>{{ t('isInvoice') }}</span>
                        <el-radio-group class="mx-[10px]" v-model="formData.is_invoice">
                            <el-radio label="2">{{ t('isInvoiceClose') }}</el-radio>
                            <el-radio label="1">{{ t('isInvoiceOpen') }}</el-radio>
                        </el-radio-group>
                    </el-form-item>
                    <el-form-item class="invoice">
                        <div class="flex">
                            <div>{{ t('invoiceContent') }}</div>
                            <div class="mx-[10px]">
                                <el-form-item :prop="`invoice_content[${index}]`" v-for="(item, index) in formData.invoice_content" :key="index" :rules="[{ validator: (rule:any, value:any, callback:Function) => {
                                            if (formData.is_invoice === '1') {
                                                if (value === ''){
                                                    return callback(t('invoicePlaceholder'))
                                                } else {
                                                    return callback()
                                                }
                                            } else {
                                                return callback()
                                            }
                                        }, trigger: 'blur' }]">
                                    <div :class="['w-[120px] relative', index ? 'mt-[15px]' : '']" >
                                        <el-input v-model.trim="formData.invoice_content[index]" class="!w-[120px]" clearable />
                                        <el-icon v-if="index" color="rgba(0, 0, 0, 0.3)" class="!absolute right-[-6px] top-[-5px]" @click="clearInvoiceContent(index)">
                                            <CircleCloseFilled />
                                        </el-icon>
                                    </div>
                                </el-form-item>
                            </div>
                            <el-button @click="insertInvoiceContent">{{ t('insert') }}</el-button>
                        </div>
                    </el-form-item>
                    <el-form-item prop="invoice_type">
                        <span class="mr-[10px]">{{ t('invoiceType') }}</span>
                        <el-checkbox-group v-model="formData.invoice_type">
                            <el-checkbox label="1">{{ t('electronicInvoice') }}</el-checkbox>
                            <el-checkbox label="2">{{ t('paperInvoice') }}</el-checkbox>
                        </el-checkbox-group>
                    </el-form-item>
                </el-card>
            </el-form>
            <div class="fixed-footer-wrap" v-if="!loading">
                <div class="fixed-footer">
                    <el-button type="primary" @click="onSave(formRef)">{{ t('save') }}</el-button>
                </div>
            </div>
        </el-card>
    </div>
</template>
<script lang="ts" setup>
import { computed, ref,reactive } from 'vue'
import { t } from '@/lang'
import { getConfig, setConfig } from '@/addon/phone_shop/api/order'
import { useRoute,useRouter } from 'vue-router'
import { filterNumber } from '@/utils/common'
import { getDiyFormList } from '@/app/api/diy_form'
import { getPayConfigList } from '@/app/api/sys'
import { getAllUserList } from '@/app/api/user'
import { ElMessage } from 'element-plus'

const route = useRoute()
const router = useRouter()

const pageName = route.meta.title
const formData = ref({
    close_length: '10',
    finish_length: '1',
    invoice_content: [''],
    invoice_type: [],
    is_close: '1',
    is_finish: '1',
    is_invoice: '1',
    no_allow_refund: '1',
    refund_length: '1',
    is_evaluate: 1,
    evaluate_is_to_examine: 1,
    evaluate_is_show: 1,
    form_id: '',
    online_order_enabled: 1,
    peer_online_enabled: 1,
    peer_fee_rate: '0.006000',
    peer_fee_bearer: 'merchant',
    offline_order_enabled: 1,
    offline_order_default: 1,
    offline_peer_enabled: 1,
    offline_timeout_minutes: 20,
    offline_contact_tip: '提交后将锁定设备，业务员会尽快联系您确认收款与交付方式。',
    offline_payment_qrcode: '',
    offline_payment_tip: '请在锁单有效期内完成转账并上传付款凭证；如已与门店人员确认，可由工作人员直接处理。',
    offline_voucher_enabled: 1,
    offline_hold_on_progress: 1,
    offline_handlers: [] as any[],
    offline_default_handler_uid: 0,
    offline_contact_name: '',
    offline_contact_mobile: ''
})

const allUserList = ref<any[]>([])
const selectedHandlers = computed(() => formData.value.offline_handlers || [])
const selectedHandlerUids = computed<number[]>({
    get: () => selectedHandlers.value.map((item: any) => Number(item.uid)),
    set: (uids: number[]) => {
        formData.value.offline_handlers = uids.map(uid => {
            const user: any = allUserList.value.find(item => Number(item.uid) === Number(uid)) || {}
            return { uid: Number(uid), name: user.real_name || user.username || user.mobile || `用户#${uid}`, mobile: user.mobile || '' }
        })
        if (!uids.includes(Number(formData.value.offline_default_handler_uid))) {
            formData.value.offline_default_handler_uid = Number(uids[0] || 0)
            syncDefaultHandlerContact()
        }
    }
})

const syncDefaultHandlerContact = () => {
    const handler: any = selectedHandlers.value.find((item: any) => Number(item.uid) === Number(formData.value.offline_default_handler_uid))
    if (!handler) return
    formData.value.offline_contact_name = handler.name || ''
    formData.value.offline_contact_mobile = handler.mobile || ''
}

const loadAllUsers = async() => {
    const res: any = await getAllUserList({})
    allUserList.value = Array.isArray(res.data) ? res.data : []
}

const peerFeePercent = computed({
    get: () => Number(formData.value.peer_fee_rate || 0) * 100,
    set: (value: number) => {
        formData.value.peer_fee_rate = (Number(value || 0) / 100).toFixed(6)
    }
})
const payChannelData = ref<any[]>([])
const enabledPayChannels = computed(() => {
    const source: any = payChannelData.value || []
    const channelMap = new Map<string, any>()
    Object.values(source).forEach((channel: any) => {
        const key = String(channel?.key || '')
        if (!key || channelMap.has(key)) return
        const payTypes = (channel.pay_type || []).filter((item: any) => Number(item.status) === 1)
        if (!payTypes.length) return
        channelMap.set(key, {
            key,
            name: channel.name || channel.title || key,
            payTypes
        })
    })
    return Array.from(channelMap.values())
})

const loadPayChannels = async() => {
    try {
        const res: any = await getPayConfigList()
        payChannelData.value = res.data || []
    } catch (e) {
        payChannelData.value = []
    }
}

const toPayCenter = () => {
    router.push('/setting/pay')
}

const validCloseLength = (rule:any, value:any, callback:Function) => {
    if (formData.value.is_close != '2') {
        if (value == '') {
            return callback(new Error(t('CloseLengthPlaceholder')))
        } else if (Number(value) >= 10 && Number(value) <= 1440) {
            return callback()
        } else {
            return callback(new Error(t('closeOrderInfoBottom')))
        }
    } else {
        return callback()
    }
}

const validFinishLength = (rule:any, value:any, callback:Function) => {
    if (formData.value.is_finish != '2') {
        if (value == '') {
            return callback(new Error(t('finishLengthPlaceholder')))
        } else if (Number(value) >= 1 && Number(value) <= 30) {
            return callback()
        } else {
            return callback(new Error(t('confirmBottom')))
        }
    } else {
        return callback()
    }
}

const validRefundLength = (rule:any, value:any, callback:Function) => {
    if (formData.value.no_allow_refund != '2') {
        if (value == '') {
            return callback(new Error(t('validRefundLengthPlaceholder')))
        } else if (Number(value) >= 1 && Number(value) <= 30) {
            return callback()
        } else {
            return callback(new Error(t('refundBottom')))
        }
    } else {
        return callback()
    }
}

const validInvoiceType = (rule:any, value:any, callback:Function) => {
    if (formData.value.is_invoice === '1') {
        if (!value.length) {
            return callback(new Error(t('invoiceTypePlaceholder')))
        } else {
            return callback()
        }
    } else {
        return callback()
    }
}

const rules = ref({
    close_length: [
        { validator: validCloseLength, trigger: 'blur' }
    ],
    finish_length: [
        { validator: validFinishLength, trigger: 'blur' }
    ],
    refund_length: [
        { validator: validRefundLength, trigger: 'blur' }
    ],
    invoice_type: [
        { validator: validInvoiceType, trigger: 'change' }
    ]
})

/** ***************** 万能表单-start *************************/
// 万能表单列表下拉框
const diyFormOptions = reactive([])
// 跳转到万能表单列表，添加表单
const toDiyFormEvent = () => {
    const url = router.resolve({
        path: '/diy_form/list'
    })
    window.open(url.href)
}

// 刷新万能表单
const refreshDiyForm = (bool = false) => {
    getDiyFormList({
        type: 'DIY_FROM_ORDER_PAYMENT',
        status: 1
    }).then((res) => {
        const data = res.data
        if (data) {
            diyFormOptions.splice(0, diyFormOptions.length, ...data)
            if (bool) {
                ElMessage({
                    message: t('refreshSuccess'),
                    type: 'success'
                })
            }
        }
    })
}

refreshDiyForm()
/** *****************万能表单-end *************************/

const loading = ref(false)
const getConfigFn = () => {
    loading.value = true
    getConfig().then(res => {
        Object.values(res.data).forEach(el => {
            formData.value = Object.assign(formData.value, el)
        })
        formData.value.form_id = res.data.form_id;
        if (!formData.value.invoice_content.length) formData.value.invoice_content.push('')
        loading.value = false
    }).catch(() => {
        loading.value = false
    })
}
const insertInvoiceContent = () => {
    formData.value.invoice_content.push('')
}
const clearInvoiceContent = (index:number) => {
    formData.value.invoice_content.splice(index, 1)
}
getConfigFn()
loadPayChannels()
loadAllUsers()
const formRef = ref()

const onSave = async (formEl: any) => {
    await formEl.validate(async (valid:any) => {
        if (valid) {
            if (formData.value.offline_order_enabled == 1 && !formData.value.offline_handlers.length) {
                ElMessage.warning('请至少选择一位线下订单接单管理员')
                return
            }
            loading.value = true
            setConfig(formData.value).then(res => {
                getConfigFn()
            }).catch(() => {
                loading.value = false
            })
        }
    })
}
</script>
<style lang="scss" scoped>
</style>
