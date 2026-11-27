<template>
    <el-dialog
        v-model="showDialog"
        title="会员微信收款方式"
        width="min(920px, calc(100vw - 32px))"
        :destroy-on-close="true"
    >
        <el-form :model="formData" label-position="top" class="virtual-pay-form" :disabled="loading">
            <el-form-item label="本业务微信收款方式">
                <el-radio-group v-model="formData.config.payment_mode">
                    <el-radio-button label="standard">普通微信支付</el-radio-button>
                    <el-radio-button label="virtual">微信虚拟支付</el-radio-button>
                </el-radio-group>
                <p class="form-tip">用户端统一显示一个“微信支付”入口，不会同时列出两种方式，也不会在失败后自动切换。</p>
            </el-form-item>

            <template v-if="formData.config.payment_mode === 'virtual'">
                <el-alert
                    title="仅用于本业务的会员订单；成交协作费和其他业务不切换。配置保存不代表支付权限已恢复。"
                    type="info"
                    :closable="false"
                    show-icon
                    class="virtual-pay-alert"
                />
                <div class="virtual-pay-grid">
                    <el-form-item label="支付环境">
                        <el-select v-model="formData.config.env">
                            <el-option label="开发测试（沙箱）" :value="1" />
                            <el-option label="正式环境" :value="0" />
                        </el-select>
                        <p
                            class="form-tip"
                            >{{ formData.config.env === 1 ? '当前为开发测试环境。正式收款前请切换正式环境，并填写正式 AppKey。' : '当前为正式环境，请核对正式 AppKey 及已发布商品；保存配置不会发起付款。' }}</p
                        >
                    </el-form-item>
                    <el-form-item label="OfferID" required>
                        <el-input
                            v-model.trim="formData.config.offer_id"
                            placeholder="虚拟支付基本配置中的 OfferID"
                            maxlength="64"
                            autocomplete="off"
                        />
                    </el-form-item>
                </div>
                <el-form-item :label="formData.config.env === 1 ? '沙箱 AppKey' : '正式 AppKey'" required>
                    <el-input
                        v-if="formData.config.env === 1"
                        v-model.trim="formData.config.sandbox_app_key"
                        type="password"
                        show-password
                        autocomplete="new-password"
                        placeholder="填写新值可替换已保存的密钥"
                    />
                    <el-input
                        v-else
                        v-model.trim="formData.config.app_key"
                        type="password"
                        show-password
                        autocomplete="new-password"
                        placeholder="填写新值可替换已保存的密钥"
                    />
                    <p class="form-tip"
                        >密钥仅在服务端使用；已保存的掩码无需重新输入。小程序 AppID
                        沿用本站小程序配置，不在这里另填。</p
                    >
                </el-form-item>

                <div class="virtual-pay-section-heading"
                    ><h3>关联微信商品</h3
                    ><el-link href="/site/member/level" target="_blank" rel="noopener">管理会员等级</el-link></div
                >
                <p class="form-tip"
                    >会员等级、周期和价格沿用会员权益。填写微信后台已发布的商品
                    ID，核对同价后再勾选启用；这里只修改关联，不创建或修改微信商品，也不重建会员等级和价格。</p
                >
                <el-alert
                    v-if="levelLoadError"
                    :title="levelLoadError"
                    type="warning"
                    :closable="false"
                    class="virtual-pay-alert"
                />
                <el-alert
                    v-else-if="productCoverageWarning"
                    :title="productCoverageWarning"
                    type="warning"
                    :closable="false"
                    class="virtual-pay-alert"
                />
                <div class="virtual-pay-products">
                    <el-table
                        v-loading="catalogLoading"
                        :data="catalogRows"
                        :empty-text="catalogLoading ? '正在读取已有会员周期' : '暂无已开启的购买周期，请先在会员等级中配置'"
                        row-key="key"
                    >
                        <el-table-column label="发布确认" width="155"
                            ><template #default="{ row }"
                                ><el-checkbox
                                    :model-value="row.enabled"
                                    :disabled="!validProductId(row.product_id) || row.conflict"
                                    @update:model-value="updateBinding(row,'enabled',$event)"
                                    >已发布并核价</el-checkbox
                                ></template
                            ></el-table-column
                        >
                        <el-table-column label="会员等级" min-width="145"
                            ><template #default="{ row }"
                                ><strong>{{ row.level_name }}</strong
                                ><span class="form-tip">{{ row.level_code }}</span></template
                            ></el-table-column
                        >
                        <el-table-column label="购买周期" min-width="120"
                            ><template #default="{ row }"
                                >{{ row.cycle_name }}<span class="form-tip">{{ row.months }}个月</span></template
                            ></el-table-column
                        >
                        <el-table-column label="当前服务费" min-width="145"
                            ><template #default="{ row }"
                                ><strong>{{ productPrice(row) }}</strong
                                ><span v-if="row.price_changed" class="form-tip virtual-pay-error"
                                    >原绑定 {{ formatPrice(row.binding_amount_cents) }}</span
                                ></template
                            ></el-table-column
                        >
                        <el-table-column label="微信已发布商品 ID" min-width="270"
                            ><template #default="{ row }">
                                <div class="virtual-pay-product-id"
                                    ><el-input
                                        :model-value="row.product_id"
                                        maxlength="20"
                                        :disabled="row.conflict"
                                        placeholder="例如 lead_v1_year99"
                                        @update:model-value="updateBinding(row,'product_id',$event)"
                                    /><el-button :disabled="!row.product_id" @click="copyProductId(row.product_id)"
                                        >复制</el-button
                                    ></div
                                >
                                <span v-if="row.conflict" class="form-tip virtual-pay-error"
                                    >已有重复关联，请联系平台核对</span
                                >
                                <template v-else>
                                    <span
                                        class="form-tip"
                                        >{{ row.product_id && !validProductId(row.product_id) ? '仅支持1～20位字母、数字和下划线，不支持短横线或空格' : row.enabled ? '已确认在微信发布并核价，保存后启用' : '填写后仍未启用，请核对发布状态与价格后勾选' }}</span
                                    >
                                    <el-checkbox
                                        v-if="row.price_changed"
                                        :model-value="row.confirm_price_change"
                                        @update:model-value="updateBinding(row,'confirm_price_change',$event)"
                                        >已在微信核对当前价格</el-checkbox
                                    >
                                </template>
                            </template></el-table-column
                        >
                    </el-table>
                </div>
                <p class="form-tip"
                    >价格由会员配置读取，支付前仍核验实际报价；不会自动创建微信商品。已绑定价格发生变化时，须先在微信核对再确认关联。</p
                >

                <el-collapse class="virtual-pay-more">
                    <el-collapse-item
                        :title="`特殊报价及其他已保存关联（${specialProducts.length}）`"
                        name="special-quotes"
                    >
                        <p class="form-tip"
                            >原有补差、押金及经验费报价关联原样保留，不会被当前会员价格覆盖。新增关联须选择已存在的会员订单，只引用其冻结报价，不创建订单、不恢复已关闭订单，也不发起付款。</p
                        >
                        <el-table v-if="specialProducts.length" :data="specialProducts" row-key="_key"
                            ><el-table-column label="会员等级" prop="level_code" min-width="120" /><el-table-column
                                label="周期"
                                prop="cycle"
                                min-width="110"
                            /><el-table-column label="原绑定金额" min-width="135"
                                ><template #default="{ row }">{{ productPrice(row) }}</template></el-table-column
                            ><el-table-column label="微信商品 ID" prop="product_id" min-width="190" /><el-table-column
                                label="状态"
                                min-width="85"
                                ><template
                                    #default="{ row }"
                                    >{{ row.enabled ? '已启用' : '未启用' }}</template
                                ></el-table-column
                            ></el-table
                        >
                        <h4 class="virtual-pay-quote-title">从已有会员订单关联</h4>
                        <el-form-item label="已有会员订单"
                            ><el-select
                                v-model="selectedQuoteOrder"
                                filterable
                                remote
                                clearable
                                :remote-method="quoteCandidates.search"
                                :loading="quoteCandidates.loading.value"
                                placeholder="搜索单号、门店、会员或手机号"
                                no-data-text="未找到可引用的会员订单"
                                class="virtual-pay-quote-select"
                                @visible-change="open => open && openQuoteOrders()"
                                ><el-option
                                    v-for="item in quoteCandidates.options.value"
                                    :key="item.id"
                                    :value="Number(item.id)"
                                    :label="quoteOrderLabel(item)"
                                    :disabled="!item.quote_amount_cents" /></el-select
                            ><p v-if="quoteCandidates.error.value" class="form-tip virtual-pay-error" role="alert"
                                >{{ quoteCandidates.error.value }}
                                <el-button
                                    link
                                    type="primary"
                                    :loading="quoteCandidates.loading.value"
                                    @click="quoteCandidates.load()"
                                    >重新读取</el-button
                                ></p
                            ><p class="form-tip"
                                >可选择待付、已付或已关闭的合法历史报价；查不到订单或没有权限时，请到
                                <el-link href="/site/hsx_lead/cooperation/orders" target="_blank" rel="noopener"
                                    >支付订单</el-link
                                >
                                核对。</p
                            ></el-form-item
                        >
                        <div v-if="chosenQuoteOrder" class="virtual-pay-quote-summary"
                            ><strong>{{ quoteOrderLabel(chosenQuoteOrder) }}</strong
                            ><span>等级、周期及金额由原订单读取，不在此处修改。</span></div
                        >
                        <el-form-item label="对应的微信已发布商品 ID"
                            ><div class="virtual-pay-product-id"
                                ><el-input
                                    v-model="quoteProductId"
                                    maxlength="20"
                                    :disabled="!chosenQuoteOrder"
                                    placeholder="填写与原单总价一致的已发布商品 ID"
                                /><el-button :disabled="!quoteProductId" @click="copyProductId(quoteProductId)"
                                    >复制</el-button
                                ></div
                            ><el-checkbox
                                v-model="quotePublished"
                                :disabled="!chosenQuoteOrder || !validProductId(quoteProductId)"
                                class="virtual-pay-quote-confirm"
                                >此商品已在微信发布，且价格与原单金额一致</el-checkbox
                            ><el-button
                                :disabled="!chosenQuoteOrder || !validProductId(quoteProductId) || !quotePublished || quoteCandidates.loading.value"
                                class="virtual-pay-quote-add"
                                @click="addOrderQuote"
                                >加入本次关联</el-button
                            ><p class="form-tip"
                                >ID仅支持1～20位字母、数字和下划线。只加入当前编辑草稿，点击底部“保存配置”才会保存；不会创建或修改微信商品。</p
                            ></el-form-item
                        >
                        <el-alert
                            v-if="quoteDraftError"
                            :title="quoteDraftError"
                            type="warning"
                            :closable="false"
                            class="virtual-pay-alert"
                        />
                        <el-table v-if="orderQuoteDrafts.length" :data="orderQuoteDrafts" row-key="order_id"
                            ><el-table-column label="本次引用订单" prop="order_no" min-width="190" /><el-table-column
                                label="原单金额"
                                min-width="120"
                                ><template #default="{ row }">{{ productPrice(row) }}</template></el-table-column
                            ><el-table-column label="微信商品 ID" prop="product_id" min-width="190" /><el-table-column
                                label="操作"
                                width="125"
                                ><template #default="{ row }"
                                    ><el-button link type="danger" @click="removeOrderQuote(row.order_id)"
                                        >移除本次关联</el-button
                                    ></template
                                ></el-table-column
                            ></el-table
                        >
                    </el-collapse-item>
                    <el-collapse-item title="消息推送与安全配置" name="callback">
                        <el-form-item label="当前站点消息回调 URL">
                            <div class="virtual-pay-callback"
                                ><el-input
                                    :model-value="callbackUrl"
                                    readonly
                                    :placeholder="callbackLoading ? '正在读取当前小程序回调地址' : callbackManaged ? '已沿用开放平台消息事件地址' : '点击读取，沿用本站小程序消息服务器地址'"
                                /><el-button
                                    :loading="callbackLoading"
                                    @click="readCallbackUrl"
                                    >{{ callbackUrl || callbackManaged ? '重新读取' : '读取地址' }}</el-button
                                ><el-button :disabled="!callbackUrl || callbackLoading" @click="copyCallbackUrl"
                                    >复制</el-button
                                ></div
                            >
                            <p v-if="callbackManaged" class="form-tip" role="status"
                                >当前小程序通过开放平台授权，沿用平台已配置的消息事件地址，无需在这里另填回调。</p
                            >
                            <p
                                v-if="callbackError"
                                class="form-tip virtual-pay-error"
                                role="alert"
                                >{{ callbackError }}</p
                            >
                            <p class="form-tip"
                                >沿用牛云小程序消息服务器地址，使用安全模式、XML
                                格式。这里只读取和复制，不修改已配置的消息服务器、Token 或 EncodingAESKey。</p
                            >
                        </el-form-item>
                        <p class="form-tip"
                            >Token、EncodingAESKey 及消息加密方式直接沿用牛云当前小程序消息服务，不再重复填写；虚拟支付
                            AppKey 与消息配置不是同一把密钥。</p
                        ><el-link href="/site/channel/weapp/config" target="_blank" rel="noopener"
                            >查看小程序消息配置</el-link
                        >
                    </el-collapse-item>
                    <el-collapse-item title="押金和购买经验的适用范围" name="costs">
                        <el-form-item label="允许包含押金"
                            ><el-switch v-model="formData.config.allow_deposit"
                        /></el-form-item>
                        <el-form-item label="允许包含购买经验费用"
                            ><el-switch v-model="formData.config.allow_experience"
                        /></el-form-item>
                        <p class="form-tip"
                            >默认关闭。仅在腾讯已明确核准对应收费项与退款方式后开启；勾选不是申请开通，也不会改变分项账务或邀请分佣基数。</p
                        >
                    </el-collapse-item>
                </el-collapse>
            </template>
            <el-alert
                v-else
                title="会员订单沿用本站普通微信支付配置，其他业务的收款方式不变。"
                type="info"
                :closable="false"
                show-icon
            />
        </el-form>
        <template #footer
            ><el-button :disabled="loading" @click="cancel">取消</el-button
            ><el-button
                type="primary"
                :loading="loading || formData.config.payment_mode === 'virtual' && catalogLoading"
                @click="confirm"
                >保存配置</el-button
            ></template
        >
    </el-dialog>
</template>

<script lang="ts" setup>
import { computed, reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { cloneDeep } from 'lodash-es'
import { getCooperationConfig, getCooperationOrders } from '@/addon/hsx_lead/api/cooperation'
import { useRemoteOptions, remoteOptionRows } from '../../../composables/useRemoteOptions'
import useUserStore from '@/stores/modules/user'
import storage from '@/utils/storage'

type Product = Record<string, any> & { _key: number; product_id: string; amount_cents: number | undefined; level_code: string; cycle: string; enabled: boolean; source?: string }
type BindingDraft = { product_id: string; enabled: boolean; confirm_price_change: boolean }
let productKey = 0
const fresh = () => ({ type: 'hsx_lead_virtualpay', channel: 'weapp', status: 0, is_default: 0, config: {
    payment_mode: 'standard', env: 1, offer_id: '', app_key: '', sandbox_app_key: '',
    allow_deposit: false, allow_experience: false, products: [] as Product[]
} })
const showDialog = ref(false), loading = ref(false), levelLoadError = ref(''), levels = ref<any[]>([])
const formData: Record<string, any> = reactive(fresh())
const initData = ref<any>(null)
const originalProducts = ref<Record<string, any>[]>([]), bindings = reactive<Record<string, BindingDraft>>({}), catalogReady = ref(false), catalogLoading = ref(false)
const callbackUrl = ref(''), callbackError = ref(''), callbackLoading = ref(false), callbackManaged = ref(false)
let callbackVersion = 0, callbackScope = '', loadVersion = 0
const user = useUserStore(), settingsActor = () => `${user.token || ''}:${user.siteInfo?.site_id || ''}:${storage.get('token') || ''}:${storage.get('siteId') || ''}`, formActor = ref(settingsActor())
const selectedQuoteOrder = ref<number>(), quoteProductId = ref(''), quotePublished = ref(false), quoteDraftError = ref(''), orderQuoteDrafts = ref<Record<string, any>[]>([])
const emit = defineEmits(['complete'])
const enabled = (value: unknown) => [true, 1, '1'].includes(value as any)
const product = (value: any = {}): Product => ({ ...value, _key: ++productKey, product_id: String(value.product_id || ''), amount_cents: value.amount_cents === undefined ? undefined : Number(value.amount_cents), level_code: String(value.level_code || ''), cycle: String(value.cycle || ''), enabled: enabled(value.enabled) })
const validProductId = (value: unknown) => typeof value === 'string' && /^[A-Za-z0-9_]{1,20}$/.test(value)
const amountCents = (value: unknown) => { const text = String(value ?? ''); if (!/^[0-9]+\.[0-9]{2}$/.test(text)) return undefined; const cents = Number(text.replace('.', '')); return Number.isSafeInteger(cents) && cents > 0 && cents <= 2147483647 ? cents : undefined }
const quoteAmountCents = (value: unknown) => String(value) === '0.00' ? 0 : amountCents(value)
const quoteCandidates = useRemoteOptions<Record<string, any>>({
    request: async keyword => {
        if (!showDialog.value || settingsActor() !== formActor.value) throw new Error('scope changed')
        const actor = settingsActor()
        try {
            const result = await getCooperationOrders({ purpose: 'membership', keyword, page: 1, limit: 20 })
            const rows = remoteOptionRows<Record<string, any>>(result, row => row.id)
            if (rows.some(row => row.purpose !== 'membership' || !['unpaid','paid','closed','refunded'].includes(String(row.status)) || quoteAmountCents(row.amount) === undefined)) throw new Error('invalid quote candidates')
            return rows.map(row => ({ ...row, quote_amount_cents: quoteAmountCents(row.amount) }))
        } finally { if (!showDialog.value || actor !== settingsActor() || actor !== formActor.value) quoteCandidates.reset() }
    }, key: row => Number(row.id), selectedKey: () => selectedQuoteOrder.value,
    scope: () => `${showDialog.value}:${settingsActor()}:${loadVersion}`,
    errorText: () => '会员订单读取失败，请检查订单查看权限后重试，也可到支付订单页核对。', fallback: '会员订单暂不可用'
})
const chosenQuoteOrder = computed(() => quoteCandidates.options.value.find(row => Number(row.id) === Number(selectedQuoteOrder.value)))
watch(() => chosenQuoteOrder.value?.id, () => { quoteProductId.value = ''; quotePublished.value = false; quoteDraftError.value = '' }, { flush: 'sync' })
watch(() => JSON.stringify([chosenQuoteOrder.value?.id, chosenQuoteOrder.value?.amount, quoteProductId.value]), () => { quotePublished.value = false; quoteDraftError.value = '' }, { flush: 'sync' })
function openQuoteOrders() { if (!quoteCandidates.loading.value && !quoteCandidates.options.value.length && !quoteCandidates.error.value) void quoteCandidates.load('') }
function quoteOrderLabel(row: Record<string, any>) { return [row.order_no || `订单 #${row.id}`, row.level_name || row.level_code || '主体会员', row.cycle_name || row.cycle, row.quote_amount_cents === 0 ? '无需收款，不关联商品' : formatPrice(row.quote_amount_cents), ({ unpaid:'待支付',paid:'已支付',closed:'已关闭',refunded:'已退款' } as Record<string,string>)[String(row.status)] || '状态待核对'].filter(Boolean).join(' · ') }
function addOrderQuote() {
    quoteDraftError.value = ''
    const row = chosenQuoteOrder.value, id = quoteProductId.value
    if (!showDialog.value || settingsActor() !== formActor.value) { quoteDraftError.value = '站点或登录已变化，请重新打开配置'; return }
    if (!row || !Number.isSafeInteger(Number(row.id)) || Number(row.id) <= 0 || row.purpose !== 'membership' || !row.quote_amount_cents) { quoteDraftError.value = '请先选择已有会员订单，金额由原单读取'; return }
    if (!validProductId(id)) { quoteDraftError.value = '请填写微信已发布商品 ID，仅支持1～20位字母、数字和下划线'; return }
    if (!quotePublished.value) { quoteDraftError.value = '请先核对微信商品已发布且与原单同价，再勾选确认'; return }
    const existing = projectedProducts()
    if (existing.some(item => item.product_id === id)) { quoteDraftError.value = '该微信商品 ID 已有关联，请核对原关联'; return }
    if (existing.some(item => Number(item.order_id) === Number(row.id) || row.level_code && row.cycle && item.level_code === row.level_code && item.cycle === row.cycle && Number(item.amount_cents) === row.quote_amount_cents)) { quoteDraftError.value = '该订单或相同会员周期金额已有关联，请核对原关联'; return }
    orderQuoteDrafts.value.push({ source:'order_quote',order_id:Number(row.id),amount_cents:row.quote_amount_cents,product_id:id,enabled:true,order_no:String(row.order_no || `订单 #${row.id}`) })
    selectedQuoteOrder.value = undefined; quoteProductId.value = ''; quotePublished.value = false
}
function removeOrderQuote(id: number) { orderQuoteDrafts.value = orderQuoteDrafts.value.filter(row => row.order_id !== Number(id)); quoteDraftError.value = '' }
function resetQuoteDraft() { quoteCandidates.reset(); selectedQuoteOrder.value = undefined; quoteProductId.value = ''; quotePublished.value = false; quoteDraftError.value = ''; orderQuoteDrafts.value = [] }
const formatPrice = (value: unknown) => Number.isSafeInteger(value) && Number(value) > 0 ? `￥${(Number(value) / 100).toFixed(2)}` : '价格待核对'
const productPrice = (row: Record<string, any>) => formatPrice(row.amount_cents)
const catalogRows = computed(() => levels.value.flatMap(level => (Array.isArray(level.cycles) ? level.cycles : []).filter((period: any) => enabled(period.enabled)).map((period: any) => {
    const key = `${level.code}:${period.key}`, cents = amountCents(period.price)
    const matches = formData.config.products.map((row: Product, index: number) => ({ row, index })).filter(({ row }: { row: Product }) => row.level_code === level.code && row.cycle === period.key && (row.source === 'catalog' || !row.source && row.amount_cents === cents))
    const explicit = matches.filter(({ row }: { row: Product }) => row.source === 'catalog'), candidates = explicit.length ? explicit : matches
    const found = candidates[0], previous = found?.row, draft = bindings[key]
    const productId = draft?.product_id ?? previous?.product_id ?? ''
    return { key, level_code: String(level.code), level_name: String(level.name || level.code), cycle: String(period.key), cycle_name: String(period.name || period.key), months: period.months, amount_cents: cents,
        previous_index: found?.index ?? -1, binding_amount_cents: previous?.amount_cents, price_changed: !!previous && previous.amount_cents !== cents, conflict: candidates.length > 1,
        previous_product_id: previous?.product_id ?? '',
        product_id: productId, enabled: draft?.enabled ?? previous?.enabled ?? false, confirm_price_change: draft?.confirm_price_change === true }
})))
const specialProducts = computed<Product[]>(() => { const managed = new Set(catalogRows.value.map(row => row.previous_index)); return formData.config.products.filter((_row: Product, index: number) => !managed.has(index)) })
function updateBinding(row: Record<string, any>, key: keyof BindingDraft, value: unknown) {
    if (row.conflict || !['product_id', 'enabled', 'confirm_price_change'].includes(key)) return
    const draft: BindingDraft = { product_id: row.product_id, enabled: row.enabled, confirm_price_change: row.confirm_price_change }
    if (key === 'product_id') {
        const next = String(value ?? '')
        if (next === row.product_id) return
        draft.product_id = next; draft.enabled = false; draft.confirm_price_change = false
    } else if (key === 'enabled') draft.enabled = value === true && validProductId(draft.product_id)
    else draft.confirm_price_change = value === true
    bindings[row.key] = draft
}
async function copyProductId(id: string) {
    const turn = loadVersion, actor = formActor.value
    const current = () => showDialog.value && turn === loadVersion && actor === settingsActor() && actor === formActor.value
    if (!id || !current()) return
    try {
        const { useClipboard } = await import('@vueuse/core')
        if (!current()) return
        await useClipboard({ legacy: true }).copy(id)
        if (current()) ElMessage.success('微信商品 ID 已复制')
    } catch (_) { if (current()) ElMessage.warning('复制未完成，请选中上方商品 ID 手动复制') }
}
const productCoverageWarning = computed(() => {
    const changed = catalogRows.value.filter(row => row.enabled && row.price_changed && !row.confirm_price_change)
    if (changed.length) return `${changed.slice(0, 3).map(row => `${row.level_name} · ${row.cycle_name}`).join('、')}的会员价格已变化，请先在微信核对再确认关联。`
    const missing = catalogRows.value.filter(row => !row.enabled || !row.product_id).map(row => `${row.level_name} · ${row.cycle_name}`)
    return missing.length ? `尚未开启微信商品关联：${missing.slice(0, 3).join('、')}${missing.length > 3 ? `等 ${missing.length} 项` : ''}。这些周期暂不使用当前普通关联；已有特殊报价关联仍保留。` : ''
})
function clearCallback() { callbackVersion++; callbackUrl.value = ''; callbackError.value = ''; callbackLoading.value = false; callbackManaged.value = false; callbackScope = '' }
async function readCallbackUrl() {
    if (callbackLoading.value || !showDialog.value) return
    const turn = ++callbackVersion
    callbackLoading.value = true; callbackUrl.value = ''; callbackError.value = ''; callbackManaged.value = false; callbackScope = ''
    try {
        // Load the framework's tenant-aware read API only when explicitly requested.
        const [{ getWeappConfig }, { default: storage }] = await Promise.all([import('@/app/api/weapp'), import('@/utils/storage')])
        const site = Number(storage.get('siteId')), scope = `${storage.get('token') || ''}:${site}`
        const current = () => turn === callbackVersion && showDialog.value && scope === `${storage.get('token') || ''}:${Number(storage.get('siteId'))}`
        if (!Number.isSafeInteger(site) || site <= 0) throw new Error('missing site')
        if (!current()) return
        const response: any = await getWeappConfig()
        if (!current()) return
        if (enabled(response.data?.is_authorization)) { callbackManaged.value = true; return }
        const url = new URL(String(response.data?.serve_url || ''))
        if (!['https:', 'http:'].includes(url.protocol) || url.username || url.password || url.search || url.hash || !url.pathname.endsWith(`/api/weapp/serve/${site}`)) throw new Error('invalid callback')
        callbackUrl.value = url.href; callbackScope = scope
    } catch (_) { if (turn === callbackVersion && showDialog.value) callbackError.value = '回调地址未读取成功，请在“小程序配置”中核对当前站点的消息服务器地址后重试。' }
    finally { if (turn === callbackVersion) callbackLoading.value = false }
}
async function copyCallbackUrl() {
    if (!callbackUrl.value || callbackLoading.value) return
    const value = callbackUrl.value, scope = callbackScope, turn = callbackVersion
    try {
        const { default: storage } = await import('@/utils/storage')
        const current = () => showDialog.value && turn === callbackVersion && scope === `${storage.get('token') || ''}:${Number(storage.get('siteId'))}`
        if (!current()) { clearCallback(); ElMessage.warning('站点或登录已变化，请重新读取回调地址'); return }
        const { useClipboard } = await import('@vueuse/core')
        if (!current()) { clearCallback(); ElMessage.warning('站点或登录已变化，请重新读取回调地址'); return }
        const { copy } = useClipboard({ legacy: true })
        await copy(value)
        ElMessage.success('回调地址已复制')
    } catch (_) { ElMessage.warning('复制未完成，请选中上方地址手动复制') }
}
watch(showDialog, open => { if (!open) { clearCallback(); loadVersion++; catalogLoading.value = false; resetQuoteDraft() } })
watch(settingsActor, () => { resetQuoteDraft(); if (showDialog.value) showDialog.value = false })
function validation(): string {
    const config = formData.config
    if (settingsActor() !== formActor.value) return '站点或登录已变化，请重新打开当前站点的配置'
    if (formData.channel !== 'weapp') return '此配置仅支持微信小程序渠道'
    if (!['standard', 'virtual'].includes(config.payment_mode)) return '请选择本业务微信收款方式'
    if (config.payment_mode !== 'virtual') return ''
    if (![0, 1].includes(config.env)) return '请选择有效支付环境'
    if (!/^[0-9]{1,32}$/.test(config.offer_id)) return '请填写虚拟支付基本配置中的数字 OfferID'
    if (!(config.env === 1 ? config.sandbox_app_key : config.app_key)) return '请填写当前环境的 AppKey'
    if (!catalogReady.value || levelLoadError.value) return '会员目录未读取成功，请重新打开后核对，当前不改写商品关联'
    for (const row of catalogRows.value) {
        if (row.conflict) return `${row.level_name} · ${row.cycle_name}存在重复关联，请联系平台核对原配置`
        if (row.enabled && row.price_changed && !row.confirm_price_change) return `${row.level_name} · ${row.cycle_name}的价格已变化，请先在微信核对当前价格并勾选确认`
        if (row.product_id && row.product_id !== row.previous_product_id && !validProductId(row.product_id)) return '微信商品 ID 仅支持1～20位字母、数字和下划线，不支持短横线或空格'
        if (row.enabled && (!row.amount_cents || !validProductId(row.product_id))) return '请核对已发布微信商品 ID 与当前价格；ID仅支持1～20位字母、数字和下划线'
    }
    const projected = projectedProducts()
    if (projected.length > 300) return '已保存的微信商品关联超过300项，请联系平台核对，当前不删除原关联'
    const selected = projected.filter((row: Record<string, any>) => enabled(row.enabled))
    if (!selected.length) return '请至少为一个已有会员周期确认微信商品已发布并核价，再勾选启用'
    const seen = new Set<string>(), productIds = new Set<string>()
    for (const row of selected) {
        if (productIds.has(row.product_id)) return '一个微信商品 ID 只能关联一项会员周期与价格，请使用各自已发布的商品 ID'
        productIds.add(row.product_id)
        if (!row.level_code || !row.cycle) continue
        const key = JSON.stringify([row.level_code, row.cycle, Number(row.amount_cents)])
        if (seen.has(key)) return '同一会员等级、周期和价格只能配置一个启用商品'
        seen.add(key)
    }
    return ''
}
function projectedProducts() {
    const rows = catalogRows.value, out = originalProducts.value.map((value, index) => {
        const row = rows.find(item => item.previous_index === index)
        if (!row) return cloneDeep(value)
        const next: Record<string, any> = { ...cloneDeep(value), level_code: row.level_code, cycle: row.cycle, product_id: row.product_id, amount_cents: row.amount_cents, enabled: row.enabled }
        delete next.confirm_price_change
        if (value.source === 'catalog' || bindings[row.key]) next.source = 'catalog'
        if (row.confirm_price_change) next.confirm_price_change = true
        return next
    })
    for (const row of rows) if (row.previous_index < 0 && row.product_id && bindings[row.key]) out.push({ source: 'catalog', level_code: row.level_code, cycle: row.cycle, product_id: row.product_id, amount_cents: row.amount_cents, enabled: row.enabled })
    for (const row of orderQuoteDrafts.value) out.push({ source:'order_quote',order_id:row.order_id,amount_cents:row.amount_cents,product_id:row.product_id,enabled:true })
    return out
}
function output(project = true) {
    const data = cloneDeep(formData)
    data.config.products = project ? projectedProducts() : cloneDeep(originalProducts.value)
    if (project) { delete data.config.callback_token; delete data.config.encoding_aes_key }
    return data
}
function confirm() {
    if (loading.value) return
    const error = validation()
    if (error) { ElMessage.warning(error); return }
    emit('complete', output())
    showDialog.value = false
}
function cancel() {
    if (initData.value) emit('complete', cloneDeep(initData.value))
    showDialog.value = false
}
async function setFormData(data: any = null) {
    clearCallback()
    resetQuoteDraft(); formActor.value = settingsActor()
    Object.keys(bindings).forEach(key => delete bindings[key]); levels.value = []; catalogReady.value = false; catalogLoading.value = true
    const next = fresh(), saved = cloneDeep(data || {})
    Object.assign(next, saved)
    next.config = { ...fresh().config, ...(saved.config || {}) }
    next.channel = saved.redio_key ? saved.redio_key.split('_')[0] : (saved.channel || 'weapp')
    next.type = 'hsx_lead_virtualpay'
    next.status = Number(saved.status || 0)
    next.config.env = Number(next.config.env)
    next.config.allow_deposit = enabled(next.config.allow_deposit)
    next.config.allow_experience = enabled(next.config.allow_experience)
    originalProducts.value = Array.isArray(next.config.products) ? cloneDeep(next.config.products) : []
    next.config.products = originalProducts.value.map(product)
    Object.assign(formData, next)
    initData.value = output(false)
    levelLoadError.value = ''
    const version = ++loadVersion
    try {
        const result = await getCooperationConfig()
        if (version === loadVersion) {
            if (!Array.isArray(result.data?.membership_levels)) throw new Error('missing catalogue')
            levels.value = result.data.membership_levels; catalogReady.value = true
        }
    } catch (_) {
        if (version === loadVersion) levelLoadError.value = '会员等级未读取成功，已保留原商品配置；请确认业务配置查看权限后重新打开。'
    } finally { if (version === loadVersion) catalogLoading.value = false }
}
const getEnableError = () => validation()
const enableVerify = () => getEnableError() === ''
defineExpose({ showDialog, setFormData, enableVerify, getEnableError })
</script>

<style lang="scss" scoped>
.virtual-pay-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 20px; }
.virtual-pay-alert { margin-bottom: 18px; }
.virtual-pay-section-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin: 22px 0 8px; h3 { margin: 0; font-size: 16px; } }
.virtual-pay-products { margin-top: 12px; overflow-x: auto; .el-table { min-width: 760px; } .el-input-number, .el-select { width: 100%; } }
.virtual-pay-products .form-tip { display: block; }
.virtual-pay-product-id { display: flex; width: 100%; gap: 8px; .el-input { flex: 1; min-width: 0; } .el-button { margin-left: 0; } }
.virtual-pay-quote-confirm { display: flex; max-width: 100%; margin-top: 12px; white-space: normal; :deep(.el-checkbox__label) { white-space: normal; line-height: 1.6; } }
.virtual-pay-quote-title { margin: 18px 0 12px; font-size: 14px; }.virtual-pay-quote-select { width: 100%; }.virtual-pay-quote-summary { display: flex; flex-direction: column; gap: 8px; margin: 0 0 16px; font-size: 13px; color: var(--el-text-color-secondary); }.virtual-pay-quote-add { margin-top: 10px; }
.virtual-pay-more { margin-top: 20px; }
.virtual-pay-callback { display: flex; width: 100%; gap: 8px; .el-input { flex: 1; min-width: 0; } .el-button + .el-button { margin-left: 0; } }
.virtual-pay-error { color: var(--el-color-danger); }
.form-tip { width: 100%; margin: 7px 0 0; color: var(--el-text-color-secondary); font-size: 12px; line-height: 1.7; }
@media (max-width: 600px) { .virtual-pay-grid { grid-template-columns: 1fr; } }
</style>
