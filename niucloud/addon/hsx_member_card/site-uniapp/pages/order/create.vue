<template>
    <view class="mc-page mc-page--action">
        <view class="mc-content">
            <MemberCardState
                v-if="loading || loadError"
                :loading="loading"
                :error="loadError"
                action="重新加载"
                @action="load"
            />
            <template v-else-if="result">
                <view class="mc-card">
                    <MemberCardState :text="result.success ? '开卡成功' : '订单已保存，需跟进收款'" />
                    <view class="mc-selection">
                        <view class="mc-title">{{ member.display_name }}</view>
                        <view class="mc-sub">{{ selectedProduct?.product_name }}</view>
                        <view class="mc-money">¥{{ money(result.amount) }}</view>
                    </view>
                    <MemberCardNotice
                        :tone="result.success ? 'success' : 'warning'"
                        :text="
                            result.success
                                ? '会员卡已生成，可在会员详情查看权益。收款情况以订单详情为准。'
                                : '请在原订单内处理，不要为同一笔业务重复开卡。'
                        "
                    />
                    <view class="mc-detail-row">
                        <text>订单号</text>
                        <text selectable @click="copyText(result.order_no)">{{ result.order_no }}</text>
                    </view>
                </view>
                <MemberCardButton type="primary" text="查看本次订单" @click="viewOrder" />
                <view style="margin-top: 16rpx"><MemberCardButton text="为下一位客户开卡" @click="reset" /></view>
            </template>
            <template v-else>
                <view class="mc-open-steps">
                    <u-steps :current="member.member_id ? (selectedProduct ? 2 : 1) : 0" dot activeColor="#2868ce">
                        <u-steps-item title="选择客户" /><u-steps-item title="选择卡种" /><u-steps-item
                            title="确认收款"
                        />
                    </u-steps>
                </view>
                <view class="mc-card mc-card--flat">
                    <u-cell
                        :title="member.member_id ? member.display_name : '选择购卡客户'"
                        :label="member.member_id ? member.mobile_masked : '查找已有客户，也可以直接新增'"
                        :value="member.member_id ? '更换' : '选择'"
                        isLink
                        center
                        :border="false"
                        :titleStyle="{ fontSize: '16px', fontWeight: '600', color: '#25364e' }"
                        @click="memberVisible = true"
                    >
                        <template #icon
                            ><view class="mc-cell-icon"><u-icon name="account" color="#3e68a1" size="23" /></view
                        ></template>
                    </u-cell>
                </view>
                <view class="mc-card">
                    <view class="mc-between">
                        <text class="mc-section-title" style="margin: 0">服务卡种</text>
                        <text class="mc-link" @click="productVisible = true">
                            {{ selectedProduct ? '更换' : '选择卡种' }}
                        </text>
                    </view>
                    <view v-if="selectedProduct" class="mc-card-preview" @click="productVisible = true">
                        <view class="mc-between">
                            <text class="mc-title mc-grow">{{ selectedProduct.product_name }}</text>
                            <text class="mc-money">¥{{ money(selectedProduct.sale_price) }}</text>
                        </view>
                        <view class="mc-sub">{{ rights(selectedProduct) }} · {{ selectedProduct.validity_text }}</view>
                    </view>
                    <MemberCardState
                        v-else-if="!products.length"
                        text="暂无已启用卡种"
                        action="去设置卡种"
                        @action="go('product/list')"
                    />
                    <view v-else class="mc-sub" style="padding: 20rpx 0 4rpx" @click="productVisible = true">
                        从 {{ products.length }} 个可用卡种中选择
                    </view>
                    <template v-if="bindingMode !== 'member'">
                        <MemberCardNotice
                            :text="
                                bindingMode === 'imei'
                                    ? '一机一卡：核销时需核验相同 IMEI。'
                                    : '型号专属：核销时需核验适用型号。'
                            "
                        />
                        <view v-if="bindingMode === 'imei'" class="mc-input mc-row">
                            <view class="mc-grow">
                                <u-input v-model="form.bind_imei" border="none" placeholder="输入设备 IMEI（必填）" />
                            </view>
                            <text class="mc-link" @click="scanImei">扫码</text>
                        </view>
                        <view class="mc-input" style="margin-top: 14rpx">
                            <u-input
                                v-model="form.bind_model"
                                border="none"
                                :placeholder="bindingMode === 'imei' ? '设备型号（选填）' : '适用型号（必填）'"
                            />
                        </view>
                    </template>
                </view>
                <view class="mc-card">
                    <view class="mc-section-title">本次收款</view>
                    <MemberCardSegmented
                        v-if="Number(config.allow_receivable) === 1"
                        v-model="form.settlement_mode"
                        :options="[
                            { label: '已现场收款', value: 'immediate' },
                            { label: '记应收，稍后收款', value: 'receivable' }
                        ]"
                    />
                    <view v-else class="mc-payment-method"
                        ><u-icon name="checkmark-circle-fill" size="17" color="#347a57" /><text
                            >登记现场收款</text
                        ></view
                    >
                    <u-cell
                        v-if="form.settlement_mode === 'immediate' && Number(selectedProduct?.sale_price) !== 0"
                        title="到账账户"
                        :value="accountName || '请选择'"
                        isLink
                        required
                        :customStyle="{ margin: '0 -15px' }"
                        :titleStyle="{ fontSize: '14px', color: '#53637a' }"
                        @click="accountVisible = true"
                    />
                    <MemberCardNotice
                        v-if="
                            form.settlement_mode === 'immediate' &&
                            !availableAccounts.length &&
                            Number(selectedProduct?.sale_price) !== 0
                        "
                        tone="warning"
                        text="暂无可用账户，请先完成收款设置。"
                        action="去设置"
                        @action="go('config/payment')"
                    />
                    <view class="mc-sub" style="margin-top: 16rpx">
                        {{
                            form.settlement_mode === 'immediate'
                                ? '仅登记已实际收到的款项，系统不会自动向客户扣款。'
                                : '提交后生成应收记录，未收到的款项不会计入实收。'
                        }}
                    </view>
                    <MemberCardCollapse
                        v-show="form.settlement_mode === 'immediate'"
                        title="收款凭证"
                        :summary="uploading ? '正在上传，请稍候' : voucherUrls ? '已添加，点击查看' : '选填'"
                    >
                        <MemberCardVoucherUploader v-model="voucherUrls" @uploading="uploading = $event" />
                    </MemberCardCollapse>
                    <MemberCardCollapse title="业务备注" :summary="form.remark ? '已填写' : '选填'">
                        <u-textarea v-model="form.remark" maxlength="255" placeholder="活动来源、特殊约定等" count />
                    </MemberCardCollapse>
                </view>
                <MemberCardNotice
                    v-if="submitError"
                    tone="error"
                    :text="submitError"
                    action="查看订单"
                    @action="go('order/list')"
                />
            </template>
        </view>
        <MemberCardActionBar v-if="!result && !loadError" fixed>
            <view class="mc-actionbar__summary">
                <view class="mc-small">本次应收</view>
                <view class="mc-money">{{ selectedProduct ? '¥' + money(selectedProduct.sale_price) : '—' }}</view>
            </view>
            <view class="mc-actionbar__button">
                <MemberCardButton
                    type="primary"
                    :text="form.settlement_mode === 'receivable' ? '记应收并开卡' : '确认收款并开卡'"
                    :loading="submitting"
                    :disabled="loading || uploading"
                    :loadingText="uploading ? '凭证上传中' : '开卡中…'"
                    @click="submit"
                />
            </view>
        </MemberCardActionBar>
        <MemberCardMemberPopup :show="memberVisible" @update:show="memberVisible = $event" @select="onMemberSelected" />
        <MemberCardSheet v-model:show="productVisible" title="选择服务卡种" subtitle="价格与权益以卡种设置为准">
            <u-search
                v-model="productKeyword"
                placeholder="搜索卡种名称"
                shape="square"
                bgColor="#f2f4f7"
                :height="40"
                :showAction="false"
            />
            <view
                v-for="product in visibleProducts"
                :key="product.id"
                class="mc-select"
                @click="selectProduct(product)"
            >
                <u-icon
                    :name="
                        Number(form.product_id) === Number(product.id) ? 'checkmark-circle-fill' : 'checkmark-circle'
                    "
                    color="#536e96"
                    size="22"
                />
                <view class="mc-grow">
                    <view>{{ product.product_name }}</view>
                    <view class="mc-sub">{{ rights(product) }} · {{ product.validity_text }}</view>
                </view>
                <text>¥{{ money(product.sale_price) }}</text>
            </view>
            <MemberCardState v-if="!visibleProducts.length" text="没有匹配的卡种" />
        </MemberCardSheet>
        <MemberCardSheet
            v-model:show="accountVisible"
            title="选择到账账户"
            subtitle="请与实际收到款项的账户保持一致"
            height="65vh"
        >
            <view
                v-for="account in availableAccounts"
                :key="account.id"
                class="mc-select"
                @click="selectAccount(account)"
            >
                <view class="mc-grow">{{ account.name }}</view>
                <u-icon
                    v-if="Number(form.capital_account_id) === Number(account.id)"
                    name="checkmark"
                    color="#536e96"
                    size="20"
                />
            </view>
            <MemberCardState
                v-if="!availableAccounts.length"
                text="暂无可用账户"
                action="去设置"
                @action="openAccountConfig"
            />
        </MemberCardSheet>
    </view>
</template>
<script setup lang="ts">
// H5 的页面样式会被自动隔离，公共组件样式通过脚本统一加载。
// #ifdef H5
import '../../styles/mobile.scss'
// #endif
import { computed, reactive, ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import {
    createCardOrder,
    getCardMember,
    getCardProductOptions,
    getMemberCardConfig,
    memberCardRequestId
} from '../../api'
import MemberCardButton from '../../components/MemberCardButton.vue'
import MemberCardMemberPopup from '../../components/MemberCardMemberPopup.vue'
import MemberCardVoucherUploader from '../../components/MemberCardVoucherUploader.vue'
import MemberCardSheet from '../../components/MemberCardSheet.vue'
import MemberCardState from '../../components/MemberCardState.vue'
import MemberCardNotice from '../../components/MemberCardNotice.vue'
import MemberCardCollapse from '../../components/MemberCardCollapse.vue'
import MemberCardActionBar from '../../components/MemberCardActionBar.vue'
import MemberCardSegmented from '../../components/MemberCardSegmented.vue'
import {
    money,
    accountOptions,
    copyText,
    errorText,
    markMemberCardChanged,
    memberCardVersion
} from '../../utils/presentation'
const memberVisible = ref(false),
    accountVisible = ref(false),
    productVisible = ref(false)
const loading = ref(true),
    submitting = ref(false),
    uploading = ref(false)
const loadError = ref(''),
    submitError = ref(''),
    productKeyword = ref(''),
    voucherUrls = ref('')
const products = ref<any[]>([]),
    config = ref<any>({ capital_account_options: [] }),
    member = ref<any>({}),
    result = ref<any>(null)
const form = reactive<any>({
    product_id: 0,
    bind_imei: '',
    bind_model: '',
    settlement_mode: 'immediate',
    capital_account_id: 0,
    remark: ''
})
const availableAccounts = computed(() => accountOptions(config.value))
const accountName = computed(
    () => availableAccounts.value.find((item: any) => Number(item.id) === Number(form.capital_account_id))?.name || ''
)
const selectedProduct = computed(() => products.value.find((item) => Number(item.id) === Number(form.product_id)))
const bindingMode = computed(() => selectedProduct.value?.item?.binding_mode || 'member')
const visibleProducts = computed(() =>
    products.value.filter((item) =>
        String(item.product_name || '')
            .toLowerCase()
            .includes(productKeyword.value.trim().toLowerCase())
    )
)
const rights = (item: any) => (item.item?.usage_mode === 'unlimited' ? '不限次' : (item.item?.total_times || 0) + ' 次')
const onMemberSelected = (value: any) => {
    member.value = value || {}
    memberVisible.value = false
}
const selectProduct = (value: any) => {
    if (Number(form.product_id) !== Number(value.id)) {
        form.bind_imei = ''
        form.bind_model = ''
    }
    form.product_id = Number(value.id)
    productVisible.value = false
}
const scanImei = () =>
    uni.scanCode({
        success: (res) => {
            form.bind_imei = String(res.result || '').trim()
        },
        fail: () => uni.showToast({ title: '未读取到串号，可手动填写', icon: 'none' })
    })
const selectAccount = (value: any) => {
    form.capital_account_id = Number(value.id)
    accountVisible.value = false
}
const openAccountConfig = () => {
    accountVisible.value = false
    go('config/payment')
}
const go = (path: string) => uni.navigateTo({ url: '/addon/hsx_member_card/pages/' + path })
const viewOrder = () =>
    uni.redirectTo({ url: '/addon/hsx_member_card/pages/order/list?order_id=' + result.value.order_id })
let initialMemberId = 0,
    seen = memberCardVersion(),
    initialized = false
let attemptKey = '',
    attemptId = ''
const load = async () => {
    loading.value = true
    loadError.value = ''
    try {
        const [p, c]: any = await Promise.all([getCardProductOptions(), getMemberCardConfig()])
        products.value = p?.data || []
        config.value = c?.data || { capital_account_options: [] }
        if (!accountName.value)
            form.capital_account_id = Number(
                availableAccounts.value.find(
                    (a: any) => Number(a.id) === Number(config.value.default_capital_account_id)
                )?.id ||
                    availableAccounts.value[0]?.id ||
                    0
            )
        if (Number(config.value.allow_receivable) !== 1) form.settlement_mode = 'immediate'
        if (form.product_id && !selectedProduct.value) form.product_id = 0
        if (initialMemberId && !member.value.member_id)
            member.value = ((await getCardMember(initialMemberId)) as any)?.data?.member || {}
        seen = memberCardVersion()
        initialized = true
    } catch (e) {
        loadError.value = errorText(e, '开卡资料加载失败，请重试')
    } finally {
        loading.value = false
    }
}
const reset = () => {
    result.value = null
    member.value = {}
    initialMemberId = 0
    voucherUrls.value = ''
    submitError.value = ''
    form.bind_imei = ''
    form.bind_model = ''
    form.remark = ''
    attemptKey = ''
    void load()
}
const submit = async () => {
    if (submitting.value || uploading.value || loading.value || result.value) return
    let message = ''
    if (!member.value.member_id) message = '请先选择购卡客户'
    else if (!selectedProduct.value) message = '请选择可用卡种'
    else if (bindingMode.value === 'imei' && !form.bind_imei.trim()) message = '请填写或扫描设备 IMEI'
    else if (bindingMode.value === 'model' && !form.bind_model.trim()) message = '请填写适用型号'
    else if (form.settlement_mode === 'immediate' && Number(selectedProduct.value.sale_price) > 0 && !accountName.value)
        message = '请选择实际到账账户'
    if (message) {
        uni.showToast({ title: message, icon: 'none' })
        return
    }
    const payload = {
        ...form,
        bind_imei: form.bind_imei.trim(),
        bind_model: form.bind_model.trim(),
        member_id: member.value.member_id,
        voucher_urls: form.settlement_mode === 'immediate' ? voucherUrls.value.split(',').filter(Boolean) : []
    }
    const key = JSON.stringify(payload)
    if (key !== attemptKey) {
        attemptKey = key
        attemptId = memberCardRequestId('issue')
    }
    submitting.value = true
    submitError.value = ''
    try {
        const data: any = (await createCardOrder({ ...payload, request_id: attemptId }))?.data
        if (!data?.order_id) throw new Error('missing order')
        result.value = data
        markMemberCardChanged()
        uni.pageScrollTo({ scrollTop: 0, duration: 200 })
    } catch (e) {
        submitError.value = errorText(e, '尚未确认开卡结果，请先查看订单。相同内容重试会沿用本次请求。')
    } finally {
        submitting.value = false
    }
}
onLoad((options: any) => {
    initialMemberId = Number(options?.member_id || 0)
    void load()
})
onShow(() => {
    if (initialized && !result.value && seen !== memberCardVersion()) void load()
})
</script>
<style lang="scss">
// 小程序从页面样式入口加载，避免脚本样式被当前页面的样式块覆盖。
// #ifndef H5
@import '../../styles/mobile.scss';
// #endif
</style>
