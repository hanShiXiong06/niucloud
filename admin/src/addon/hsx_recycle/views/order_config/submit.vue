<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never" v-loading="loading">
            <div class="page-head">
                <div>
                    <span class="text-page-title">下单设置</span>
                    <div class="page-desc">控制用户端回收下单入口、设备登记按钮和可用提交方式。</div>
                </div>
                <el-button type="primary" :loading="saving" @click="save">保存设置</el-button>
            </div>

            <div class="config-layout">
                <section class="config-section">
                    <div class="section-title">前台通知</div>
                    <div class="setting-row">
                        <div>
                            <div class="setting-title">下单页通知</div>
                            <div class="setting-desc">开启后，在用户下单页顶部展示一条通知，用于说明回收时效、活动规则或注意事项。</div>
                        </div>
                        <el-switch v-model="form.notice.enabled" :active-value="1" :inactive-value="0" />
                    </div>
                    <div v-if="form.notice.enabled" class="notice-form">
                        <el-input v-model.trim="form.notice.title" maxlength="30" show-word-limit placeholder="通知标题，如：回收下单须知" />
                        <el-input
                            v-model.trim="form.notice.content"
                            type="textarea"
                            :rows="4"
                            maxlength="500"
                            show-word-limit
                            placeholder="请输入通知内容，支持普通文本"
                        />
                    </div>
                </section>

                <section class="config-section">
                    <div class="section-title">设备登记</div>
                    <div class="setting-row">
                        <div>
                            <div class="setting-title">允许用户添加设备明细</div>
                            <div class="setting-desc">开启后，下单页显示“添加设备”按钮；关闭后，用户仍可直接选择数量提交订单。</div>
                        </div>
                        <el-switch v-model="form.device_add_enabled" :active-value="1" :inactive-value="0" />
                    </div>
                    <div class="setting-row mt-[18px]">
                        <div>
                            <div class="setting-title">默认下单数量</div>
                            <div class="setting-desc">用户没有添加设备明细时，数量选择器默认显示这个数量。</div>
                        </div>
                        <el-input-number v-model="form.default_count" :min="1" :max="99" controls-position="right" />
                    </div>
                </section>

                <section class="config-section">
                    <div class="section-title">提交方式</div>
                    <div class="mode-grid">
                        <div class="mode-card" :class="{ active: form.delivery_modes.mail }" @click="toggleMode('mail')">
                            <div class="mode-head">
                                <div>
                                    <div class="mode-title">邮寄到店</div>
                                    <div class="mode-desc">用户填写快递单号，或使用平台快递上门取件。</div>
                                </div>
                                <el-switch v-model="form.delivery_modes.mail" :active-value="1" :inactive-value="0" @click.stop />
                            </div>
                        </div>
                        <div class="mode-card" :class="{ active: form.delivery_modes.self }" @click="toggleMode('self')">
                            <div class="mode-head">
                                <div>
                                    <div class="mode-title">自送到店</div>
                                    <div class="mode-desc">用户不填写快递信息，直接到门店交付设备。</div>
                                </div>
                                <el-switch v-model="form.delivery_modes.self" :active-value="1" :inactive-value="0" @click.stop />
                            </div>
                        </div>
                    </div>
                    <el-alert class="mt-[14px]" type="warning" :closable="false" show-icon>
                        <template #title>至少需要开启一种提交方式。若两种都关闭，保存时会自动恢复为全部开启。</template>
                    </el-alert>
                </section>

                <section class="config-section">
                    <div class="section-title">个人资料与打款</div>
                    <div class="setting-row">
                        <div>
                            <div class="setting-title">要求用户完善个人资料</div>
                            <div class="setting-desc">开启后，下单页会提醒用户完善资料；关闭后，不再强提醒姓名、手机号、身份证和收款方式。</div>
                        </div>
                        <el-switch v-model="form.profile.enabled" :active-value="1" :inactive-value="0" />
                    </div>

                    <div v-if="form.profile.enabled" class="profile-panel">
                        <div class="setting-row">
                            <div>
                                <div class="setting-title">要求收款方式</div>
                                <div class="setting-desc">开启后，用户需要添加足够数量的收款方式，避免回收款无法正常打款。</div>
                            </div>
                            <el-switch v-model="form.profile.payment_required" :active-value="1" :inactive-value="0" />
                        </div>
                        <div v-if="form.profile.payment_required" class="setting-row">
                            <div>
                                <div class="setting-title">最低收款方式数量</div>
                                <div class="setting-desc">默认 1 种；设置为 2 时，用户至少要提交两种收款方式。</div>
                            </div>
                            <el-input-number v-model="form.profile.payment_min_count" :min="1" :max="5" controls-position="right" />
                        </div>
                        <div class="setting-row">
                            <div>
                                <div class="setting-title">采集身份证信息</div>
                                <div class="setting-desc">开启后，身份证号和身份证照片为必填；关闭后，用户可只维护基础资料与收款方式。</div>
                            </div>
                            <el-switch v-model="form.profile.id_card_required" :active-value="1" :inactive-value="0" />
                        </div>
                    </div>
                </section>

                <section class="config-section">
                    <div class="section-title">平台快递包邮</div>
                    <div class="setting-row">
                        <div>
                            <div class="setting-title">前台快递名称</div>
                            <div class="setting-desc">展示给用户看的名称，选择快递线路时会自动填入线路名，保存前可手动改成更短的展示名。</div>
                        </div>
                        <el-input v-model.trim="form.platform_delivery.display_name" maxlength="20" show-word-limit class="setting-input" placeholder="京东快递" />
                    </div>
                    <div class="setting-row">
                        <div>
                            <div class="setting-title">最低包邮数量</div>
                            <div class="setting-desc">用户选择“邮寄到店”时，只有达到该数量才允许使用平台快递下单；未达到时仍可手动填写快递单号。</div>
                        </div>
                        <el-input-number v-model="form.platform_delivery.free_shipping_min_count" :min="1" :max="99" controls-position="right" />
                    </div>
                    <div class="setting-row">
                        <div>
                            <div class="setting-title">默认快递服务商</div>
                            <div class="setting-desc">用户端使用平台快递时实际下单的服务商，来源于第三方快递配置中已开启的服务商。</div>
                        </div>
                        <el-select
                            v-model="form.platform_delivery.provider"
                            class="setting-input"
                            placeholder="请选择快递服务商"
                            :disabled="!form.platform_delivery.provider_options.length"
                            @change="handleProviderChange"
                        >
                            <el-option
                                v-for="item in form.platform_delivery.provider_options"
                                :key="item.provider"
                                :label="item.provider_name"
                                :value="item.provider"
                            />
                        </el-select>
                    </div>
                    <div class="setting-row">
                        <div>
                            <div class="setting-title">默认快递线路</div>
                            <div class="setting-desc">用户端平台快递实际使用的产品线路，来源于第三方配置中已启用的亿速产品。</div>
                        </div>
                        <el-select
                            v-model="form.platform_delivery.product_code"
                            class="setting-input"
                            placeholder="请选择快递线路"
                            :disabled="!currentProductOptions.length"
                            @change="handleProductChange"
                        >
                            <el-option
                                v-for="item in currentProductOptions"
                                :key="item.product_code"
                                :label="formatProductLabel(item)"
                                :value="item.product_code"
                            />
                        </el-select>
                    </div>
                </section>

                <section class="config-section">
                    <div class="section-title">公众号关注提醒</div>
                    <div class="setting-row">
                        <div>
                            <div class="setting-title">下单成功后弹出公众号二维码</div>
                            <div class="setting-desc">开启后，用户提交订单成功会看到关注公众号弹窗；必须先上传公众号二维码图片。</div>
                        </div>
                        <el-switch v-model="form.follow_official_account.enabled" :active-value="1" :inactive-value="0" />
                    </div>
                    <div v-if="form.follow_official_account.enabled" class="media-config-panel">
                        <div class="setting-row">
                            <div>
                                <div class="setting-title">公众号名称</div>
                                <div class="setting-desc">展示在二维码上方，方便用户确认关注对象。</div>
                            </div>
                            <el-input v-model.trim="form.follow_official_account.wechat_name" maxlength="30" show-word-limit class="setting-input" placeholder="请输入公众号名称" />
                        </div>
                        <div class="setting-row">
                            <div>
                                <div class="setting-title">弹窗标题</div>
                                <div class="setting-desc">默认显示“关注公众号”。</div>
                            </div>
                            <el-input v-model.trim="form.follow_official_account.title" maxlength="30" show-word-limit class="setting-input" placeholder="关注公众号" />
                        </div>
                        <div class="setting-row">
                            <div>
                                <div class="setting-title">提示文案</div>
                                <div class="setting-desc">展示在二维码上方，说明关注后的用途。</div>
                            </div>
                            <el-input v-model.trim="form.follow_official_account.content" maxlength="120" show-word-limit class="setting-input" placeholder="关注公众号，及时接收订单状态通知" />
                        </div>
                        <div class="setting-row align-start">
                            <div>
                                <div class="setting-title">公众号二维码</div>
                                <div class="setting-desc">开启提醒时必填，用户可长按识别关注。</div>
                            </div>
                            <upload-image v-model="form.follow_official_account.qr_code" :limit="1" width="120px" height="120px" image-text="上传二维码" />
                        </div>
                    </div>
                </section>

                <section class="config-section">
                    <div class="section-title">订单客服</div>
                    <div class="setting-row">
                        <div>
                            <div class="setting-title">开启订单详情客服入口</div>
                            <div class="setting-desc">开启后，用户可在订单详情中联系工作人员，支持微信客服或自定义客服二维码。</div>
                        </div>
                        <el-switch v-model="form.customer_service.enabled" :active-value="1" :inactive-value="0" />
                    </div>
                    <div v-if="form.customer_service.enabled" class="media-config-panel">
                        <div class="setting-row">
                            <div>
                                <div class="setting-title">客服方式</div>
                                <div class="setting-desc">微信客服适合小程序原生客服；二维码客服适合添加指定工作人员。</div>
                            </div>
                            <el-radio-group v-model="form.customer_service.type">
                                <el-radio-button label="wechat">微信客服</el-radio-button>
                                <el-radio-button label="qrcode">客服二维码</el-radio-button>
                            </el-radio-group>
                        </div>
                        <div class="setting-row">
                            <div>
                                <div class="setting-title">入口标题</div>
                                <div class="setting-desc">展示在订单详情客服入口和弹窗标题中。</div>
                            </div>
                            <el-input v-model.trim="form.customer_service.title" maxlength="30" show-word-limit class="setting-input" placeholder="联系客服" />
                        </div>
                        <div class="setting-row">
                            <div>
                                <div class="setting-title">提示文案</div>
                                <div class="setting-desc">说明客服可以处理的事项。</div>
                            </div>
                            <el-input v-model.trim="form.customer_service.content" maxlength="120" show-word-limit class="setting-input" placeholder="如需议价或咨询订单进度，请联系客服处理" />
                        </div>
                        <div v-if="form.customer_service.type === 'qrcode'" class="setting-row align-start">
                            <div>
                                <div class="setting-title">客服二维码</div>
                                <div class="setting-desc">选择二维码客服时必填，用户可长按添加工作人员。</div>
                            </div>
                            <upload-image v-model="form.customer_service.qrcode" :limit="1" width="120px" height="120px" image-text="上传二维码" />
                        </div>
                    </div>
                </section>

                <section class="config-section">
                    <div class="section-title">订单确认</div>
                    <div class="setting-row">
                        <div>
                            <div class="setting-title">允许用户拒绝出售</div>
                            <div class="setting-desc">开启后，用户在订单详情的每台设备上可以自主点击“拒绝出售”；关闭后，用户端隐藏该按钮，只能由管理员代用户拒绝出售并处理退回。</div>
                        </div>
                        <el-switch v-model="form.allow_user_reject_sale" :active-value="1" :inactive-value="0" />
                    </div>
                </section>

                <section class="config-section">
                    <div class="section-title">前台主题配色</div>
                    <div class="section-tip">报价详情页、下单页和订单页统一使用框架“主题风格”中的 hsx_recycle 配色。这里不再单独维护颜色，避免前台出现两套主题不一致。</div>
                    <div class="theme-entry">
                        <div>
                            <div class="setting-title">统一主题配置</div>
                            <div class="setting-desc">请到“装修管理 / 主题风格”中编辑回收系统配色，保存后前台会通过下单配置接口统一读取。</div>
                        </div>
                        <el-button type="primary" plain @click="goThemeStyle">打开主题风格</el-button>
                    </div>
                </section>

                <section class="config-section muted">
                    <div class="section-title">后续配置预留</div>
                    <div class="placeholder-list">
                        <div>门店签收字段控制</div>
                        <div>下单按钮文案</div>
                        <div>不同分类的包邮规则</div>
                    </div>
                </section>
            </div>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { getOrderSubmitConfig, saveOrderSubmitConfig, type OrderSubmitConfig } from '@/addon/hsx_recycle/api/order_config'

const loading = ref(false)
const saving = ref(false)
const router = useRouter()

const defaultPriceDetailThemeColors = {
    page_bg: '#F3F4F6',
    card_bg: '#FFFFFF',
    soft_bg: '#F7F7F8',
    line: '#E5E7EB',
    text_main: '#1F2937',
    text_sub: '#6B7280',
    brand: '#3B82F6',
    brand_deep: '#4F46E5',
    price: '#2563EB',
    notice_bg: '#FFF8ED',
    notice_text: '#F59E0B',
    toolbar_bg: '#FFFFFF',
    button_bg: '#111827',
    button_text: '#FFFFFF',
    series_active_bg: '#111827',
    series_active_text: '#FFFFFF',
    series_inactive_bg: '#F8FAFC',
    series_inactive_text: '#475569',
    model_head_bg: '#F8FAFC',
    model_brand_bg: '#111827',
    model_brand_text: '#FFFFFF'
}

const form = reactive<OrderSubmitConfig>({
    device_add_enabled: 1,
    notice: {
        enabled: 0,
        title: '下单提示',
        content: ''
    },
    default_count: 1,
    delivery_modes: {
        mail: 1,
        self: 1
    },
    profile: {
        enabled: 1,
        payment_required: 1,
        payment_min_count: 1,
        id_card_required: 1
    },
    platform_delivery: {
        display_name: '京东快递',
        free_shipping_min_count: 1,
        provider: 'yisu',
        provider_name: '亿速物流',
        provider_options: [],
        product_code: '',
        product_name: '',
        product_options: []
    },
    follow_official_account: {
        enabled: 0,
        wechat_name: '',
        qr_code: '',
        title: '关注公众号',
        content: '关注公众号，及时接收订单状态通知'
    },
    customer_service: {
        enabled: 0,
        type: 'wechat',
        qrcode: '',
        title: '联系客服',
        content: '如需议价或咨询订单进度，请联系客服处理'
    },
    allow_user_reject_sale: 1,
    price_detail_theme: {
        template_key: 'classic_blue',
        theme_name: '默认蓝',
        colors: { ...defaultPriceDetailThemeColors }
    }
})

const goThemeStyle = () => {
    router.push('/diy/theme_style')
}

const normalize = (data: Partial<OrderSubmitConfig> = {}) => {
    form.device_add_enabled = data.device_add_enabled ? 1 : 0
    form.notice.enabled = data.notice?.enabled ? 1 : 0
    form.notice.title = data.notice?.title || '下单提示'
    form.notice.content = data.notice?.content || ''
    form.default_count = Math.max(1, Math.min(99, Number(data.default_count || 1)))
    form.delivery_modes.mail = data.delivery_modes?.mail ? 1 : 0
    form.delivery_modes.self = data.delivery_modes?.self ? 1 : 0
    form.profile.enabled = data.profile?.enabled === 0 ? 0 : 1
    form.profile.payment_required = data.profile?.payment_required === 0 ? 0 : 1
    form.profile.payment_min_count = Math.max(1, Math.min(5, Number(data.profile?.payment_min_count || 1)))
    form.profile.id_card_required = data.profile?.id_card_required === 0 ? 0 : 1
    form.platform_delivery.display_name = data.platform_delivery?.display_name || ''
    form.platform_delivery.free_shipping_min_count = Math.max(1, Math.min(99, Number(data.platform_delivery?.free_shipping_min_count || 1)))
    form.platform_delivery.provider_options = normalizeProviderOptions(data.platform_delivery?.provider_options)
    form.platform_delivery.provider = data.platform_delivery?.provider || form.platform_delivery.provider_options[0]?.provider || 'yisu'
    form.platform_delivery.provider_name = data.platform_delivery?.provider_name || ''
    form.platform_delivery.product_options = normalizeProductOptions(data.platform_delivery?.product_options)
    form.platform_delivery.product_code = data.platform_delivery?.product_code || ''
    form.platform_delivery.product_name = data.platform_delivery?.product_name || ''
    ensureProviderSelection()
    ensureProductSelection()
    if (!form.platform_delivery.display_name) {
        form.platform_delivery.display_name = form.platform_delivery.product_name || '京东快递'
    }
    form.follow_official_account.enabled = data.follow_official_account?.enabled ? 1 : 0
    form.follow_official_account.wechat_name = data.follow_official_account?.wechat_name || ''
    form.follow_official_account.qr_code = data.follow_official_account?.qr_code || ''
    form.follow_official_account.title = data.follow_official_account?.title || '关注公众号'
    form.follow_official_account.content = data.follow_official_account?.content || '关注公众号，及时接收订单状态通知'
    form.customer_service.enabled = data.customer_service?.enabled ? 1 : 0
    form.customer_service.type = data.customer_service?.type === 'qrcode' ? 'qrcode' : 'wechat'
    form.customer_service.qrcode = data.customer_service?.qrcode || ''
    form.customer_service.title = data.customer_service?.title || '联系客服'
    form.customer_service.content = data.customer_service?.content || '如需议价或咨询订单进度，请联系客服处理'
    form.allow_user_reject_sale = data.allow_user_reject_sale === 0 ? 0 : 1
    normalizeTheme(data.price_detail_theme)
    if (!form.delivery_modes.mail && !form.delivery_modes.self) {
        form.delivery_modes.mail = 1
        form.delivery_modes.self = 1
    }
}

const normalizeProviderOptions = (options: OrderSubmitConfig['platform_delivery']['provider_options'] = []) => {
    const list = Array.isArray(options) ? options : []
    const result = list
        .filter(item => item && item.provider)
        .map(item => ({
            provider: item.provider,
            provider_name: item.provider_name || item.provider,
            is_default: Number(item.is_default || 0),
            support_quote: Boolean(item.support_quote),
            support_cancel: Boolean(item.support_cancel),
            support_track: Boolean(item.support_track)
        }))

    return result.length ? result : [{
        provider: 'yisu',
        provider_name: '亿速物流',
        is_default: 1,
        support_quote: true,
        support_cancel: true,
        support_track: true
    }]
}

const ensureProviderSelection = (syncProductDisplayName = false) => {
    const selected = form.platform_delivery.provider_options.find(item => item.provider === form.platform_delivery.provider)
        || form.platform_delivery.provider_options.find(item => item.is_default)
        || form.platform_delivery.provider_options[0]

    if (!selected) {
        form.platform_delivery.provider = ''
        form.platform_delivery.provider_name = ''
        return
    }

    form.platform_delivery.provider = selected.provider
    form.platform_delivery.provider_name = selected.provider_name
    ensureProductSelection(syncProductDisplayName)
}

const handleProviderChange = () => {
    ensureProviderSelection(true)
}

const normalizeProductOptions = (options: OrderSubmitConfig['platform_delivery']['product_options'] = []) => {
    const list = Array.isArray(options) ? options : []
    return list
        .filter(item => item && item.provider && item.product_code)
        .map(item => ({
            provider: item.provider,
            product_code: item.product_code,
            product_name: item.product_name || item.product_code,
            express_type: item.express_type || '',
            logo: item.logo || ''
        }))
}

const currentProductOptions = computed(() => {
    return form.platform_delivery.product_options.filter(item => item.provider === form.platform_delivery.provider)
})

const ensureProductSelection = (syncDisplayName = false) => {
    const selected = currentProductOptions.value.find(item => item.product_code === form.platform_delivery.product_code)
        || currentProductOptions.value[0]

    if (!selected) {
        form.platform_delivery.product_code = ''
        form.platform_delivery.product_name = ''
        return
    }

    form.platform_delivery.product_code = selected.product_code
    form.platform_delivery.product_name = selected.product_name
    if (syncDisplayName) {
        form.platform_delivery.display_name = selected.product_name
    }
}

const handleProductChange = () => {
    ensureProductSelection(true)
}

const formatProductLabel = (item: OrderSubmitConfig['platform_delivery']['product_options'][number]) => {
    return item.express_type ? `${item.product_name}（${item.express_type}）` : item.product_name
}

const normalizeTheme = (theme: Partial<OrderSubmitConfig['price_detail_theme']> = {}) => {
    form.price_detail_theme.template_key = theme.template_key || 'framework_theme'
    form.price_detail_theme.theme_name = theme.theme_name || '主题风格'
    form.price_detail_theme.colors = {
        ...defaultPriceDetailThemeColors,
        ...(theme.colors || {})
    }
}

const load = async () => {
    loading.value = true
    try {
        const res = await getOrderSubmitConfig()
        normalize(res.data || {})
    } finally {
        loading.value = false
    }
}

const toggleMode = (key: 'mail' | 'self') => {
    form.delivery_modes[key] = form.delivery_modes[key] ? 0 : 1
}

const save = async () => {
    if (!form.delivery_modes.mail && !form.delivery_modes.self) {
        ElMessage.warning('至少需要开启一种提交方式')
        return
    }
    if (form.notice.enabled && !form.notice.content.trim()) {
        ElMessage.warning('请填写通知内容')
        return
    }
    if (!form.platform_delivery.display_name.trim()) {
        ElMessage.warning('请填写前台快递名称')
        return
    }
    ensureProviderSelection()
    if (!form.platform_delivery.provider) {
        ElMessage.warning('请选择默认快递服务商')
        return
    }
    ensureProductSelection()
    if (!form.platform_delivery.product_code) {
        ElMessage.warning('请选择默认快递线路，请先在第三方快递配置中启用至少一条产品线路')
        return
    }
    if (form.profile.enabled && form.profile.payment_required && form.profile.payment_min_count < 1) {
        ElMessage.warning('最低收款方式数量不能小于 1')
        return
    }
    if (form.follow_official_account.enabled && !form.follow_official_account.qr_code) {
        ElMessage.warning('开启公众号关注提醒前，请先上传公众号二维码')
        return
    }
    if (form.customer_service.enabled && form.customer_service.type === 'qrcode' && !form.customer_service.qrcode) {
        ElMessage.warning('选择客服二维码模式前，请先上传客服二维码')
        return
    }

    saving.value = true
    try {
        await saveOrderSubmitConfig(form)
    } finally {
        saving.value = false
    }
}

onMounted(load)
</script>

<style scoped lang="scss">
.page-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.page-desc {
    margin-top: 6px;
    font-size: 13px;
    color: #6b7280;
}

.config-layout {
    display: grid;
    gap: 16px;
    margin-top: 20px;
}

.config-section {
    padding: 18px;
    border: 1px solid #ebeef5;
    border-radius: 8px;
    background: #fff;
}

.config-section.muted {
    background: #fafafa;
}

.notice-form {
    display: grid;
    gap: 12px;
    margin-top: 16px;
}

.profile-panel {
    display: grid;
    gap: 18px;
    padding-top: 18px;
    margin-top: 18px;
    border-top: 1px dashed #e5e7eb;
}

.media-config-panel {
    display: grid;
    gap: 18px;
    padding-top: 18px;
    margin-top: 18px;
    border-top: 1px dashed #e5e7eb;
}

.section-title {
    margin-bottom: 14px;
    font-size: 15px;
    font-weight: 600;
    color: #1f2937;
}

.section-tip {
    margin: -4px 0 16px;
    font-size: 13px;
    line-height: 1.6;
    color: #6b7280;
}

.setting-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;

    & + & {
        margin-top: 18px;
    }
}

.setting-row.align-start {
    align-items: flex-start;
}

.setting-input {
    width: 280px;
    flex-shrink: 0;
}

.setting-title,
.mode-title {
    font-size: 14px;
    font-weight: 600;
    color: #111827;
}

.setting-desc,
.mode-desc {
    margin-top: 6px;
    font-size: 13px;
    color: #6b7280;
    line-height: 1.5;
}

.mode-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.mode-card {
    padding: 16px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    cursor: pointer;
}

.mode-card.active {
    border-color: var(--el-color-primary);
    background: var(--el-color-primary-light-9);
}

.mode-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.placeholder-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.placeholder-list div {
    padding: 8px 12px;
    border-radius: 999px;
    background: #f3f4f6;
    color: #6b7280;
    font-size: 13px;
}

.theme-entry {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 14px;
    border: 1px solid #edf0f5;
    border-radius: 8px;
    background: #fafafa;
}

@media (max-width: 768px) {
    .theme-entry {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
