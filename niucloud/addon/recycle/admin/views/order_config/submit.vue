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
                            <div class="setting-desc">展示给用户看的快递名称，不影响管理端真实快递服务商配置。</div>
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
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { getOrderSubmitConfig, saveOrderSubmitConfig, type OrderSubmitConfig } from '@/addon/recycle/api/order_config'

const loading = ref(false)
const saving = ref(false)

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
        free_shipping_min_count: 1
    }
})

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
    form.platform_delivery.display_name = data.platform_delivery?.display_name || '京东快递'
    form.platform_delivery.free_shipping_min_count = Math.max(1, Math.min(99, Number(data.platform_delivery?.free_shipping_min_count || 1)))
    if (!form.delivery_modes.mail && !form.delivery_modes.self) {
        form.delivery_modes.mail = 1
        form.delivery_modes.self = 1
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
    if (form.profile.enabled && form.profile.payment_required && form.profile.payment_min_count < 1) {
        ElMessage.warning('最低收款方式数量不能小于 1')
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

.section-title {
    margin-bottom: 14px;
    font-size: 15px;
    font-weight: 600;
    color: #1f2937;
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
</style>
