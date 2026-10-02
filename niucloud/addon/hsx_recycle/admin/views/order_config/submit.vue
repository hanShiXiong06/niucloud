<template>
    <HsxPage title="下单设置" class="order-settings" content-width="standard" header-sticky>
        <template #extra>
            <div class="page-actions">
                <span v-if="isDirty" class="save-status" role="status">有未保存的修改</span>
                <el-button v-if="activeTab !== 'erp'" type="primary" :icon="Check" :loading="saving" :disabled="!dataLoaded || loading" @click="save">保存设置</el-button>
            </div>
        </template>

        <div v-if="loadError" class="load-error" role="alert">
            <el-alert :title="loadError" type="error" :closable="false" show-icon />
            <el-button :icon="Refresh" :loading="loading" @click="load">重新加载</el-button>
        </div>
        <el-skeleton v-else-if="!dataLoaded" :rows="7" animated class="settings-skeleton" />

        <el-tabs v-else v-model="activeTab" :tab-position="compactNavigation ? 'top' : 'left'" class="settings-tabs">
            <el-tab-pane name="customer">
                <template #label><span class="nav-label"><el-icon><User /></el-icon>客户下单</span></template>
                <div class="settings-panel">
                    <header class="panel-heading"><h2>客户下单</h2></header>
                    <el-alert v-if="validationError && validationTab === 'customer'" :title="validationError" class="validation-error" type="error" :closable="false" show-icon />
                    <section class="settings-section">
                        <h3>设备登记</h3>
                        <SettingRow id="device-add" label="允许添加设备明细" hint="关闭后，客户仅填写设备数量。">
                            <el-switch v-model="form.device_add_enabled" :active-value="1" :inactive-value="0" aria-label="允许添加设备明细" />
                        </SettingRow>
                        <SettingRow id="default-count" label="默认设备数量">
                            <el-input-number v-model="form.default_count" :min="1" :max="99" controls-position="right" aria-label="默认设备数量" />
                        </SettingRow>
                    </section>
                    <section class="settings-section">
                        <h3>客户资料</h3>
                        <SettingRow id="profile-enabled" label="要求完善个人资料">
                            <el-switch v-model="form.profile.enabled" :active-value="1" :inactive-value="0" aria-label="要求完善个人资料" />
                        </SettingRow>
                        <div v-if="form.profile.enabled" class="dependent-fields">
                            <SettingRow id="payment-required" label="要求添加收款方式">
                                <el-switch v-model="form.profile.payment_required" :active-value="1" :inactive-value="0" aria-label="要求添加收款方式" />
                            </SettingRow>
                            <SettingRow v-if="form.profile.payment_required" id="payment-count" label="最低收款方式数量">
                                <el-input-number v-model="form.profile.payment_min_count" :min="1" :max="5" controls-position="right" aria-label="最低收款方式数量" />
                            </SettingRow>
                            <SettingRow id="id-card-required" label="身份证信息必填" hint="包括身份证号和身份证照片。">
                                <el-switch v-model="form.profile.id_card_required" :active-value="1" :inactive-value="0" aria-label="身份证信息必填" />
                            </SettingRow>
                        </div>
                    </section>
                    <section class="settings-section">
                        <h3>订单查看与确认</h3>
                        <SettingRow id="reject-sale" label="允许客户拒绝出售" hint="关闭后，需由管理员处理拒售和退回。">
                            <el-switch v-model="form.allow_user_reject_sale" :active-value="1" :inactive-value="0" aria-label="允许客户拒绝出售" />
                        </SettingRow>
                        <SettingRow id="inspection-result" label="向客户显示质检结果">
                            <el-switch v-model="form.order_detail.show_inspection_result" :active-value="1" :inactive-value="0" aria-label="向客户显示质检结果" />
                        </SettingRow>
                        <SettingRow id="inspection-images" label="向客户显示质检图片">
                            <el-switch v-model="form.order_detail.show_inspection_images" :active-value="1" :inactive-value="0" aria-label="向客户显示质检图片" />
                        </SettingRow>
                    </section>
                </div>
            </el-tab-pane>

            <el-tab-pane name="delivery">
                <template #label><span class="nav-label"><el-icon><Van /></el-icon>配送与取货</span></template>
                <div class="settings-panel">
                    <header class="panel-heading"><h2>配送与取货</h2></header>
                    <el-alert v-if="validationError && validationTab === 'delivery'" :title="validationError" class="validation-error" type="error" :closable="false" show-icon />
                    <section id="delivery-modes" class="settings-section">
                        <h3>下单渠道 <span class="section-note">至少开启一种</span></h3>
                        <SettingRow id="delivery-mail" label="邮寄到店" hint="填写快递单号，或使用平台快递。">
                            <el-switch v-model="form.delivery_modes.mail" :active-value="1" :inactive-value="0" aria-label="邮寄到店" />
                        </SettingRow>
                        <SettingRow id="delivery-self" label="自送到店">
                            <el-switch v-model="form.delivery_modes.self" :active-value="1" :inactive-value="0" aria-label="自送到店" />
                        </SettingRow>
                        <SettingRow id="delivery-vehicle" label="物流车配送" hint="设备到站后，由取货责任人前往领取。">
                            <el-switch v-model="form.delivery_modes.logistics_vehicle" :active-value="1" :inactive-value="0" aria-label="物流车配送" />
                        </SettingRow>
                        <div v-if="form.delivery_modes.logistics_vehicle" class="dependent-fields">
                            <SettingRow id="arrival-mode" label="预计到达" hint="按预计时间推送取货任务。">
                                <el-radio-group v-model="form.logistics_vehicle.arrival_mode" aria-label="预计到达">
                                    <el-radio-button label="half_day">半天到达</el-radio-button>
                                    <el-radio-button label="next_day">次日到达</el-radio-button>
                                </el-radio-group>
                            </SettingRow>
                            <SettingRow v-if="form.logistics_vehicle.arrival_mode === 'half_day'" id="arrival-cutoff" label="上午订单截止时间">
                                <el-time-select v-model="form.logistics_vehicle.morning_cutoff" start="06:00" step="00:30" end="18:00" aria-label="上午订单截止时间" />
                            </SettingRow>
                            <SettingRow v-if="form.logistics_vehicle.arrival_mode === 'half_day'" id="arrival-today" label="上午单当天取货时间">
                                <el-time-select v-model="form.logistics_vehicle.same_day_time" start="08:00" step="00:30" end="23:30" aria-label="上午单当天取货时间" />
                            </SettingRow>
                            <SettingRow id="arrival-next-day" label="次日取货时间" hint="下午单或次日到达的订单使用此时间。">
                                <el-time-select v-model="form.logistics_vehicle.next_day_time" start="06:00" step="00:30" end="18:00" aria-label="次日取货时间" />
                            </SettingRow>
                        </div>
                    </section>
                    <section class="settings-section">
                        <h3>平台快递</h3>
                        <template v-if="isSfPickup">
                            <SettingRow id="delivery-provider" label="上门取件渠道" hint="沿用本站已选择的渠道，不在此重复配置账号。">
                                <el-tag type="info">顺丰直连 · 上门取件</el-tag>
                            </SettingRow>
                            <SettingRow id="delivery-product" label="快递产品" hint="与物流服务中的顺丰上门取件配置保持一致。">
                                <span>{{ form.platform_delivery.product_name || '暂无可用取件产品' }}</span>
                            </SettingRow>
                            <SettingRow id="delivery-name" label="客户看到的快递名称">
                                <span>{{ form.platform_delivery.display_name }}</span>
                            </SettingRow>
                            <p v-if="!currentProductOptions.length" class="field-hint">顺丰上门取件尚未就绪，请核对正式环境、账号及取件开关；不会自动改用易速，不影响客户自行寄件。</p>
                            <el-button plain @click="openSfPickupConfig">查看顺丰取件配置</el-button>
                        </template>
                        <template v-else>
                        <SettingRow id="delivery-provider" label="快递服务商">
                            <el-select v-model="form.platform_delivery.provider" placeholder="请选择快递服务商" :disabled="!form.platform_delivery.provider_options.length" @change="handleProviderChange">
                                <el-option v-for="item in form.platform_delivery.provider_options" :key="item.provider" :label="item.provider_name" :value="item.provider" />
                            </el-select>
                        </SettingRow>
                        <SettingRow id="delivery-product" label="快递线路">
                            <el-select v-model="form.platform_delivery.product_code" placeholder="请选择快递线路" :disabled="!currentProductOptions.length" @change="handleProductChange">
                                <el-option v-for="item in currentProductOptions" :key="item.product_code" :label="formatProductLabel(item)" :value="item.product_code" />
                            </el-select>
                        </SettingRow>
                        <p v-if="!currentProductOptions.length" class="field-hint">暂无可用的平台快递线路，不影响保存。邮寄到店仍可由客户自行寄件、填写单号。</p>
                        <SettingRow id="delivery-name" label="客户看到的快递名称">
                            <el-input v-model.trim="form.platform_delivery.display_name" maxlength="20" show-word-limit placeholder="如：京东快递" />
                        </SettingRow>
                        </template>
                        <SettingRow id="delivery-free-count" label="上门取件最低台数" hint="满多少台可以预约上门取件；未满仍可自行寄件。这个数量不代表包邮。">
                            <el-input-number v-model="form.platform_delivery.free_shipping_min_count" :min="1" :max="99" controls-position="right" aria-label="上门取件最低台数" />
                        </SettingRow>
                        <SettingRow id="delivery-payment-tips" label="客户运费说明" hint="只修改客户看到的说明，不改变快递的扣款方式。请确认与实际承担方式一致。" stacked>
                            <el-input v-model.trim="form.platform_delivery.payment_tips" type="textarea" :rows="2" maxlength="120" show-word-limit placeholder="直接写清楚谁付运费，如：运费由商家承担，您无需支付。" />
                            <div class="delivery-copy-presets">
                                <el-button size="small" @click="form.platform_delivery.payment_tips = '运费由商家承担，您无需支付。'">填入：商家承担</el-button>
                                <el-button size="small" @click="form.platform_delivery.payment_tips = '运费由您承担，寄件时请向快递员支付。'">填入：寄件人承担</el-button>
                            </div>
                            <p class="field-hint">客户看到：{{ form.platform_delivery.payment_tips || '运费由谁承担，请先联系门店确认。' }}</p>
                        </SettingRow>
                        <SettingRow id="delivery-pickup-schedule" label="每日取件时段" hint="顺丰营业时段内默认立即取件：当前时间至下一整点；距整点剩余15分钟及以内，改约下一小时。客户也可选择未来三天。" stacked>
                            <div class="pickup-schedule-controls">
                                <el-time-select v-model="form.platform_delivery.pickup_schedule.start" start="00:00" step="00:30" end="23:30" placeholder="开始时间" aria-label="取件开始时间" />
                                <span>至</span>
                                <el-time-select v-model="form.platform_delivery.pickup_schedule.end" start="00:00" step="00:30" end="23:30" placeholder="结束时间" aria-label="取件结束时间" />
                            </div>
                        </SettingRow>
                        <SettingRow id="delivery-pickup-cutoff" label="当天预约截止" hint="包括立即取件在内，到达此时间后不再接当天预约，默认安排明天。实际到达以快递员联系为准。">
                            <el-time-select v-model="form.platform_delivery.pickup_schedule.cutoff" start="00:00" step="00:30" end="23:30" placeholder="截止时间" aria-label="当天预约截止时间" />
                        </SettingRow>
                    </section>
                </div>
            </el-tab-pane>

            <el-tab-pane name="workflow">
                <template #label><span class="nav-label"><el-icon><Connection /></el-icon>业务流转</span></template>
                <div class="settings-panel">
                    <header class="panel-heading"><h2>业务流转</h2></header>
                    <el-alert v-if="validationError && validationTab === 'workflow'" :title="validationError" class="validation-error" type="error" :closable="false" show-icon />
                    <section class="settings-section">
                        <h3>订单流转</h3>
                        <SettingRow id="flow-mode" label="流转方式" stacked>
                            <el-radio-group v-model="flowModeChoice" @change="handleFlowModeChange" aria-label="流转方式">
                                <el-radio-button label="order">整单流转</el-radio-button>
                                <el-radio-button label="device">按设备流转</el-radio-button>
                            </el-radio-group>
                            <p class="field-hint">{{ flowModeMeta.desc }}</p>
                        </SettingRow>
                        <el-alert title="保存后仅对新订单生效，已有订单保持原流转方式。" type="info" :closable="false" show-icon />
                    </section>
                    <section class="settings-section">
                        <h3>代卖业务</h3>
                        <SettingRow id="consignment-enabled" label="启用代卖" hint="将回收设备转入独立的代卖订单。">
                            <el-switch v-model="form.consignment.enabled" :active-value="1" :inactive-value="0" aria-label="启用代卖" />
                        </SettingRow>
                        <div v-if="form.consignment.enabled" class="dependent-fields">
                            <SettingRow id="consignment-confirm" label="转代卖时二次确认"><el-switch v-model="form.consignment.transfer_confirm_required" :active-value="1" :inactive-value="0" aria-label="转代卖时二次确认" /></SettingRow>
                            <SettingRow id="consignment-entry" label="向客户展示代卖入口"><el-switch v-model="form.consignment.user_entry_enabled" :active-value="1" :inactive-value="0" aria-label="向客户展示代卖入口" /></SettingRow>
                            <SettingRow id="consignment-view" label="允许客户查看代卖进度"><el-switch v-model="form.consignment.user_view_enabled" :active-value="1" :inactive-value="0" aria-label="允许客户查看代卖进度" /></SettingRow>
                            <SettingRow id="consignment-title" label="入口标题"><el-input v-model.trim="form.consignment.user_title" maxlength="20" show-word-limit placeholder="代卖订单" /></SettingRow>
                            <SettingRow id="consignment-desc" label="入口说明"><el-input v-model.trim="form.consignment.user_desc" maxlength="80" show-word-limit placeholder="查看代卖进度、成交与结算结果" /></SettingRow>
                            <SettingRow id="consignment-notice" label="发送代卖通知"><el-switch v-model="form.consignment.notice_enabled" :active-value="1" :inactive-value="0" aria-label="发送代卖通知" /></SettingRow>
                            <SettingRow id="consignment-print" label="启用代卖打印"><el-switch v-model="form.consignment.print_enabled" :active-value="1" :inactive-value="0" aria-label="启用代卖打印" /></SettingRow>
                            <SettingRow id="consignment-fee" label="向客户显示服务收益" hint="显示成交价与结算金额的差额，请谨慎开启。"><el-switch v-model="form.consignment.show_service_fee" :active-value="1" :inactive-value="0" aria-label="向客户显示服务收益" /></SettingRow>
                        </div>
                    </section>
                </div>
            </el-tab-pane>

            <el-tab-pane name="notifications">
                <template #label><span class="nav-label"><el-icon><Bell /></el-icon>通知与客服</span></template>
                <div class="settings-panel">
                    <header class="panel-heading"><h2>通知与客服</h2></header>
                    <el-alert v-if="validationError && validationTab === 'notifications'" :title="validationError" class="validation-error" type="error" :closable="false" show-icon />
                    <section class="settings-section">
                        <SettingRow id="notice-enabled" label="下单页通知"><el-switch v-model="form.notice.enabled" :active-value="1" :inactive-value="0" aria-label="下单页通知" /></SettingRow>
                        <div v-if="form.notice.enabled" class="dependent-fields">
                            <SettingRow id="notice-title" label="通知标题"><el-input v-model.trim="form.notice.title" maxlength="30" show-word-limit placeholder="下单提示" /></SettingRow>
                            <SettingRow id="notice-content" label="通知内容" stacked><el-input v-model.trim="form.notice.content" type="textarea" :rows="3" maxlength="500" show-word-limit placeholder="请输入通知内容" /></SettingRow>
                            <SettingRow id="notice-url" label="跳转 URL" stacked hint="选填，留空仅展示通知。站内填写 /addon/... 或 /app/... 页面路径，可携带参数；外部填写完整 https:// 网址，小程序需配置业务域名。">
                                <el-input v-model.trim="form.notice.url" maxlength="1000" clearable placeholder="粘贴页面路径或活动入口地址" />
                                <p class="field-hint">营销任务：到「营销任务」点击对应活动的「领取入口」，复制地址到这里。客户须在任务页面主动领取；点击通知不会自动领取，也不会阻止正常下单。</p>
                            </SettingRow>
                            <SettingRow v-if="form.notice.url" id="notice-link-text" label="跳转按钮文字"><el-input v-model.trim="form.notice.link_text" maxlength="12" show-word-limit placeholder="例如：先领任务再下单" /></SettingRow>
                        </div>
                    </section>
                    <section class="settings-section">
                        <SettingRow id="follow-enabled" label="下单后提醒关注公众号"><el-switch v-model="form.follow_official_account.enabled" :active-value="1" :inactive-value="0" aria-label="下单后提醒关注公众号" /></SettingRow>
                        <div v-if="form.follow_official_account.enabled" class="dependent-fields">
                            <SettingRow id="follow-name" label="公众号名称"><el-input v-model.trim="form.follow_official_account.wechat_name" maxlength="30" show-word-limit placeholder="公众号名称" /></SettingRow>
                            <SettingRow id="follow-title" label="弹窗标题"><el-input v-model.trim="form.follow_official_account.title" maxlength="30" show-word-limit placeholder="关注公众号" /></SettingRow>
                            <SettingRow id="follow-content" label="提示文案"><el-input v-model.trim="form.follow_official_account.content" maxlength="120" show-word-limit placeholder="关注公众号，及时接收订单状态通知" /></SettingRow>
                            <SettingRow id="follow-qr" label="公众号二维码"><upload-image v-model="form.follow_official_account.qr_code" :limit="1" width="100px" height="100px" image-text="上传二维码" /></SettingRow>
                        </div>
                    </section>
                    <section class="settings-section">
                        <SettingRow id="service-enabled" label="订单客服入口"><el-switch v-model="form.customer_service.enabled" :active-value="1" :inactive-value="0" aria-label="订单客服入口" /></SettingRow>
                        <div v-if="form.customer_service.enabled" class="dependent-fields">
                            <SettingRow id="service-type" label="客服方式"><el-radio-group v-model="form.customer_service.type"><el-radio-button label="wechat">微信客服</el-radio-button><el-radio-button label="qrcode">客服二维码</el-radio-button></el-radio-group></SettingRow>
                            <SettingRow id="service-title" label="入口标题"><el-input v-model.trim="form.customer_service.title" maxlength="30" show-word-limit placeholder="联系客服" /></SettingRow>
                            <SettingRow id="service-content" label="提示文案"><el-input v-model.trim="form.customer_service.content" maxlength="120" show-word-limit placeholder="如需议价或咨询订单进度，请联系客服处理" /></SettingRow>
                            <SettingRow v-if="form.customer_service.type === 'qrcode'" id="service-qr" label="客服二维码"><upload-image v-model="form.customer_service.qrcode" :limit="1" width="100px" height="100px" image-text="上传二维码" /></SettingRow>
                        </div>
                    </section>
                    <section class="settings-section">
                        <SettingRow id="work-wechat-enabled" label="企业微信群通知" hint="群机器人通知；员工个人待办仍由协同插件管理。"><el-switch v-model="form.work_wechat.enabled" :active-value="1" :inactive-value="0" aria-label="企业微信群通知" /></SettingRow>
                        <el-collapse v-if="form.work_wechat.enabled" v-model="activeChannel" accordion class="channel-list">
                            <el-collapse-item v-for="item in workWechatChannelMetas" :key="item.key" :name="item.key">
                                <template #title>
                                    <span class="channel-title">{{ item.title }}</span>
                                    <span class="channel-status" :class="{ enabled: form.work_wechat.channels[item.key].enabled }">{{ form.work_wechat.channels[item.key].enabled ? '已开启' : '未开启' }}</span>
                                </template>
                                <SettingRow :id="'channel-' + item.key" label="启用通知" :hint="item.desc"><el-switch v-model="form.work_wechat.channels[item.key].enabled" :active-value="1" :inactive-value="0" :aria-label="'启用' + item.title" /></SettingRow>
                                <div v-if="form.work_wechat.channels[item.key].enabled" class="dependent-fields">
                                    <SettingRow :id="'channel-name-' + item.key" label="群名称"><el-input v-model.trim="form.work_wechat.channels[item.key].name" maxlength="30" placeholder="群名称" /></SettingRow>
                                    <SettingRow :id="'channel-webhook-' + item.key" label="Webhook 地址"><el-input v-model.trim="form.work_wechat.channels[item.key].webhook_url" type="password" show-password maxlength="1000" placeholder="群机器人 Webhook 地址" /></SettingRow>
                                    <SettingRow :id="'channel-dedupe-' + item.key" label="同单限频（分钟）"><el-input-number v-model="form.work_wechat.channels[item.key].dedupe_minutes" :min="0" :max="1440" controls-position="right" /></SettingRow>
                                    <SettingRow :id="'channel-limit-' + item.key" label="每日上限" hint="0 为不限。"><el-input-number v-model="form.work_wechat.channels[item.key].daily_limit" :min="0" :max="999" controls-position="right" /></SettingRow>
                                    <SettingRow v-if="item.key === 'order_urge'" id="channel-cooldown" label="用户催办间隔（小时）" hint="0 为不限。"><el-input-number v-model="form.work_wechat.channels[item.key].user_cooldown_hours" :min="0" :max="720" controls-position="right" /></SettingRow>
                                </div>
                            </el-collapse-item>
                        </el-collapse>
                    </section>
                </div>
            </el-tab-pane>

            <el-tab-pane name="tools">
                <template #label><span class="nav-label"><el-icon><Monitor /></el-icon>设备与工具</span></template>
                <div class="settings-panel">
                    <header class="panel-heading"><h2>设备与工具</h2></header>
                    <el-alert v-if="validationError && validationTab === 'tools'" :title="validationError" class="validation-error" type="error" :closable="false" show-icon />
                    <section id="device-bridge" class="settings-section">
                        <SettingRow label="设备桥" hint="安装包由 SaaS 平台统一提供。">
                            <el-button :icon="Monitor" @click="bridgeHelpVisible = true">安装与帮助</el-button>
                        </SettingRow>
                    </section>
                    <section class="settings-section">
                        <SettingRow id="theme-style" label="前台主题配色">
                            <el-button :icon="Brush" @click="goThemeStyle">主题风格</el-button>
                        </SettingRow>
                    </section>
                </div>
            </el-tab-pane>

            <el-tab-pane name="erp" lazy>
                <template #label><span class="nav-label"><el-icon><Link /></el-icon>ERP 联动</span></template>
                <div class="settings-panel erp-panel"><ErpIntegrationSettings /></div>
            </el-tab-pane>
        </el-tabs>
        <DeviceBridgeHelpDialog v-model="bridgeHelpVisible" :allow-read="false" />
    </HsxPage>
</template>

<script setup lang="ts">
import { HsxPage, useFeedback } from '@/addon/hsx_components/core'
import { Bell, Brush, Check, Connection, Link, Monitor, Refresh, User, Van } from '@element-plus/icons-vue'
import { useMediaQuery } from '@vueuse/core'
import SettingRow from './components/OrderSettingRow.vue'
import ErpIntegrationSettings from './components/ErpIntegrationSettings.vue'
import DeviceBridgeHelpDialog from '@/addon/hsx_recycle/components/device-entry/DeviceBridgeHelpDialog.vue'
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { onBeforeRouteLeave, useRouter } from 'vue-router'
import { ElMessageBox } from 'element-plus'
import { getOrderSubmitConfig, saveOrderSubmitConfig, type OrderSubmitConfig } from '@/addon/hsx_recycle/api/order_config'
const hsxFeedback = useFeedback()
const bridgeHelpVisible = ref(false)


const loading = ref(false)
const saving = ref(false)
const dataLoaded = ref(false)
const loadError = ref('')
const savedSnapshot = ref('')
type SettingsTab = 'customer' | 'delivery' | 'workflow' | 'notifications' | 'tools' | 'erp'
const activeTab = ref<SettingsTab>('customer')
const compactNavigation = useMediaQuery('(max-width: 760px)')
const flowModeChoice = ref<'order' | 'device'>('order')
const activeChannel = ref('')
const validationTab = ref<SettingsTab>('customer')
const validationError = ref('')
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
        content: '',
        url: '',
        link_text: '查看详情'
    },
    default_count: 1,
    delivery_modes: {
        mail: 1,
        self: 1,
        logistics_vehicle: 0
    },
    logistics_vehicle: {
        arrival_mode: 'half_day',
        morning_cutoff: '12:00',
        same_day_time: '16:00',
        next_day_time: '09:00'
    },
    profile: {
        enabled: 1,
        payment_required: 1,
        payment_min_count: 1,
        id_card_required: 1
    },
    payment: {
        mode: 'order'
    },
    flow: {
        mode: 'order'
    },
    platform_delivery: {
        display_name: '京东快递',
        free_shipping_min_count: 1,
        payment_tips: '',
        pickup_schedule: { start: '09:00', end: '18:00', cutoff: '16:00' },
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
    order_detail: {
        show_inspection_result: 1,
        show_inspection_images: 1
    },
    consignment: {
        enabled: 0,
        user_entry_enabled: 1,
        user_view_enabled: 1,
        transfer_confirm_required: 1,
        notice_enabled: 1,
        print_enabled: 1,
        show_service_fee: 0,
        user_title: '代卖订单',
        user_desc: '查看代卖进度、成交与结算结果'
    },
    work_wechat: {
        enabled: 0,
        channels: {
            order_notice: { enabled: 0, name: '订单通知群', webhook_url: '', dedupe_minutes: 10, daily_limit: 0 },
            order_urge: { enabled: 1, name: '订单催办群', webhook_url: '', dedupe_minutes: 10, daily_limit: 5, user_cooldown_hours: 12 },
            return_order: { enabled: 0, name: '退货处理群', webhook_url: '', dedupe_minutes: 10, daily_limit: 0 },
            finance: { enabled: 0, name: '财务打款群', webhook_url: '', dedupe_minutes: 10, daily_limit: 0 },
            exception: { enabled: 0, name: '异常预警群', webhook_url: '', dedupe_minutes: 30, daily_limit: 0 }
        }
    },
    price_detail_theme: {
        template_key: 'classic_blue',
        theme_name: '默认蓝',
        colors: { ...defaultPriceDetailThemeColors }
    }
})

const isDirty = computed(() => dataLoaded.value && savedSnapshot.value !== JSON.stringify(form))

const goThemeStyle = () => {
    router.push('/diy/theme_style')
}

const workWechatChannelMetas = [
    { key: 'order_notice', title: '订单通知群', desc: '用于新订单、订单状态等普通订单通知。建议按需开启，避免刷屏。' },
    { key: 'order_urge', title: '订单催办群', desc: '用户在订单详情点击“催一下”后，推送到这个群。' },
    { key: 'return_order', title: '退货处理群', desc: '用于退货申请、退货待处理、退货超时等通知。' },
    { key: 'finance', title: '财务打款群', desc: '用于待打款、打款失败、付款凭证异常等财务通知。' },
    { key: 'exception', title: '异常预警群', desc: '用于高金额、超时未处理、异常订单等预警。' }
] as const

const normalize = (data: Partial<OrderSubmitConfig> = {}) => {
    form.device_add_enabled = data.device_add_enabled ? 1 : 0
    form.notice.enabled = data.notice?.enabled ? 1 : 0
    form.notice.title = data.notice?.title || '下单提示'
    form.notice.content = data.notice?.content || ''
    form.notice.url = data.notice?.url || ''
    form.notice.link_text = data.notice?.link_text || '查看详情'
    form.default_count = Math.max(1, Math.min(99, Number(data.default_count || 1)))
    form.delivery_modes.mail = data.delivery_modes?.mail ? 1 : 0
    form.delivery_modes.self = data.delivery_modes?.self ? 1 : 0
    form.delivery_modes.logistics_vehicle = data.delivery_modes?.logistics_vehicle ? 1 : 0
    form.logistics_vehicle.arrival_mode = data.logistics_vehicle?.arrival_mode === 'next_day' ? 'next_day' : 'half_day'
    form.logistics_vehicle.morning_cutoff = data.logistics_vehicle?.morning_cutoff || '12:00'
    form.logistics_vehicle.same_day_time = data.logistics_vehicle?.same_day_time || '16:00'
    form.logistics_vehicle.next_day_time = data.logistics_vehicle?.next_day_time || '09:00'
    form.profile.enabled = data.profile?.enabled === 0 ? 0 : 1
    form.profile.payment_required = data.profile?.payment_required === 0 ? 0 : 1
    form.profile.payment_min_count = Math.max(1, Math.min(5, Number(data.profile?.payment_min_count || 1)))
    form.profile.id_card_required = data.profile?.id_card_required === 0 ? 0 : 1
    form.flow.mode = data.flow?.mode === 'device' || data.payment?.mode === 'device' ? 'device' : 'order'
    flowModeChoice.value = form.flow.mode
    form.payment.mode = form.flow.mode
    form.platform_delivery.display_name = data.platform_delivery?.display_name || ''
    form.platform_delivery.free_shipping_min_count = Math.max(1, Math.min(99, Number(data.platform_delivery?.free_shipping_min_count || 1)))
    form.platform_delivery.payment_tips = data.platform_delivery?.payment_tips || ''
    form.platform_delivery.pickup_schedule = {
        start: data.platform_delivery?.pickup_schedule?.start || '09:00',
        end: data.platform_delivery?.pickup_schedule?.end || '18:00',
        cutoff: data.platform_delivery?.pickup_schedule?.cutoff || '16:00'
    }
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
    form.order_detail.show_inspection_result = data.order_detail?.show_inspection_result === 0 ? 0 : 1
    form.order_detail.show_inspection_images = data.order_detail?.show_inspection_images === 0 ? 0 : 1
    form.consignment.enabled = data.consignment?.enabled ? 1 : 0
    form.consignment.user_entry_enabled = data.consignment?.user_entry_enabled === 0 ? 0 : 1
    form.consignment.user_view_enabled = data.consignment?.user_view_enabled === 0 ? 0 : 1
    form.consignment.transfer_confirm_required = data.consignment?.transfer_confirm_required === 0 ? 0 : 1
    form.consignment.notice_enabled = data.consignment?.notice_enabled === 0 ? 0 : 1
    form.consignment.print_enabled = data.consignment?.print_enabled === 0 ? 0 : 1
    form.consignment.show_service_fee = data.consignment?.show_service_fee ? 1 : 0
    form.consignment.user_title = data.consignment?.user_title || '代卖订单'
    form.consignment.user_desc = data.consignment?.user_desc || '查看代卖进度、成交与结算结果'
    normalizeWorkWechat(data.work_wechat)
    normalizeTheme(data.price_detail_theme)
    if (!form.delivery_modes.mail && !form.delivery_modes.self && !form.delivery_modes.logistics_vehicle) {
        form.delivery_modes.mail = 1
        form.delivery_modes.self = 1
    }
}

const normalizeWorkWechat = (config: Partial<OrderSubmitConfig['work_wechat']> = {}) => {
    form.work_wechat.enabled = config.enabled ? 1 : 0
    const channels = config.channels || {}
    workWechatChannelMetas.forEach((meta) => {
        const current = form.work_wechat.channels[meta.key]
        const saved = channels[meta.key] || {}
        current.enabled = saved.enabled ? 1 : 0
        current.name = saved.name || current.name
        current.webhook_url = saved.webhook_url || ''
        current.dedupe_minutes = Math.max(0, Math.min(1440, Number(saved.dedupe_minutes ?? current.dedupe_minutes)))
        current.daily_limit = Math.max(0, Math.min(999, Number(saved.daily_limit ?? current.daily_limit)))
        // 订单催办群额外保存用户端“催一下”冷却时间（小时）
        if (current.user_cooldown_hours !== undefined) {
            current.user_cooldown_hours = Math.max(0, Math.min(720, Number(saved.user_cooldown_hours ?? current.user_cooldown_hours)))
        }
    })
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

const isSfPickup = computed(() => form.platform_delivery.provider === 'sf_direct')
const openSfPickupConfig = () => {
    const target = router.resolve('/hsx_express/config?provider=sf_direct&scene=pickup')
    if (!target.matched.length) {
        hsxFeedback.info('未找到顺丰配置入口，请确认已更新物流插件并分配配置权限')
        return
    }
    window.open(target.href, '_blank', 'noopener,noreferrer')
}

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

const flowModeMeta = computed(() => {
    if (form.flow.mode === 'device') {
        return {
            title: '按设备流转',
            desc: '新订单下的设备可以部分质检、部分确认、部分打款。订单只做整体进度汇总，全部设备闭环后才会完成。'
        }
    }
    return {
        title: '整单流转',
        desc: '新订单需要等待全部设备完成质检后统一确认、统一打款。适合流程简单、按批次结算的商家。'
    }
})

const handleFlowModeChange = async (value: 'order' | 'device') => {
    if (form.flow.mode === value) return
    const oldValue = form.flow.mode
    const message = value === 'device'
        ? '确认切换为按设备流转吗？保存后，新订单将支持部分设备先确认、先打款。历史订单仍保持创建时的流转模式，不会自动改变。'
        : '确认切换为整单流转吗？保存后，新订单将按整单统一确认、统一打款。历史订单仍保持创建时的流转模式，不会自动改变。'
    try {
        await ElMessageBox.confirm(message, '切换订单流转模式', {
            confirmButtonText: '确认切换',
            cancelButtonText: '取消',
            type: 'warning'
        })
        form.flow.mode = value
        form.payment.mode = value
        hsxFeedback.info('已切换选项，点击保存设置后生效')
    } catch (e) {
        flowModeChoice.value = oldValue
        form.flow.mode = oldValue
        form.payment.mode = oldValue
    }
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
    if (loading.value) return
    loading.value = true
    loadError.value = ''
    try {
        const res = await getOrderSubmitConfig()
        if (!res.data || typeof res.data !== 'object' || Array.isArray(res.data)) throw new Error('配置数据不完整')
        normalize(res.data)
        savedSnapshot.value = JSON.stringify(form)
        dataLoaded.value = true
    } catch {
        dataLoaded.value = false
        loadError.value = '设置加载失败，暂不能修改或保存。请重新加载。'
    } finally {
        loading.value = false
    }
}

const showValidation = async (tab: SettingsTab, field: string, message: string) => {
    activeTab.value = tab
    validationTab.value = tab
    validationError.value = message
    await nextTick()
    const target = document.getElementById(field)
    target?.scrollIntoView({ block: 'center', behavior: 'smooth' })
    target?.querySelector<HTMLElement>('input:not(:disabled), textarea, button, [role="switch"]')?.focus({ preventScroll: true })
}

const save = async () => {
    if (saving.value || loading.value || !dataLoaded.value) return
    validationError.value = ''
    if (!form.delivery_modes.mail && !form.delivery_modes.self && !form.delivery_modes.logistics_vehicle) {
        return showValidation('delivery', 'delivery-modes', '至少需要开启一种下单渠道')
    }
    if (form.notice.enabled && !form.notice.content.trim()) {
        return showValidation('notifications', 'notice-content', '请填写通知内容')
    }
    if (form.notice.url && (!/^(\/(addon|app)\/|https?:\/\/)/i.test(form.notice.url) || /[\s\\<>"\u0000-\u001f]/.test(form.notice.url))) {
        return showValidation('notifications', 'notice-url', '请填写站内页面路径或完整 http(s) 网址，不要包含空格')
    }
    const schedule = form.platform_delivery.pickup_schedule
    const minuteOf = (value: string) => Number(value.slice(0, 2)) * 60 + Number(value.slice(3))
    if (![schedule.start, schedule.end, schedule.cutoff].every(value => /^(?:[01]\d|2[0-3]):[0-5]\d$/.test(value)) || schedule.start >= schedule.end) {
        return showValidation('delivery', 'delivery-pickup-schedule', '请设置有效的取件时段，结束时间须晚于开始时间')
    }
    if (minuteOf(schedule.end) - minuteOf(schedule.cutoff) < 30) {
        return showValidation('delivery', 'delivery-pickup-cutoff', '当天预约截止时间须至少早于取件结束时间30分钟')
    }
    ensureProviderSelection()
    ensureProductSelection()
    // 自行寄件不依赖平台线路；未开放邮寄或没有可用线路时，不阻止保存其他设置。
    if (form.delivery_modes.mail && currentProductOptions.value.length > 0) {
        if (!form.platform_delivery.display_name.trim()) {
            return showValidation('delivery', 'delivery-name', '请填写客户看到的快递名称')
        }
        if (!form.platform_delivery.provider) {
            return showValidation('delivery', 'delivery-provider', '请选择默认快递服务商')
        }
        if (!form.platform_delivery.product_code) {
            return showValidation('delivery', 'delivery-product', '请选择默认快递线路')
        }
    }
    if (form.profile.enabled && form.profile.payment_required && form.profile.payment_min_count < 1) {
        return showValidation('customer', 'payment-count', '最低收款方式数量不能小于 1')
    }
    if (form.follow_official_account.enabled && !form.follow_official_account.qr_code) {
        return showValidation('notifications', 'follow-qr', '开启公众号关注提醒前，请先上传公众号二维码')
    }
    if (form.customer_service.enabled && form.customer_service.type === 'qrcode' && !form.customer_service.qrcode) {
        return showValidation('notifications', 'service-qr', '选择客服二维码模式前，请先上传客服二维码')
    }
    if (form.consignment.enabled && !form.consignment.user_title.trim()) {
        return showValidation('workflow', 'consignment-title', '请填写代卖入口标题')
    }
    if (form.work_wechat.enabled) {
        for (const meta of workWechatChannelMetas) {
            const channel = form.work_wechat.channels[meta.key]
            if (channel.enabled && !channel.webhook_url.trim()) {
                activeChannel.value = meta.key
                return showValidation('notifications', `channel-webhook-${meta.key}`, `请填写${channel.name || meta.title}的 Webhook 地址`)
            }
        }
    }

    saving.value = true
    try {
        form.payment.mode = form.flow.mode
        // 固定本次提交的数据；请求期间的新修改仍保留为未保存状态。
        const snapshot = JSON.stringify(form)
        await saveOrderSubmitConfig(JSON.parse(snapshot))
        savedSnapshot.value = snapshot
    } catch (error: any) {
        validationTab.value = activeTab.value === 'erp' ? 'customer' : activeTab.value
        activeTab.value = validationTab.value
        validationError.value = error?.msg || error?.message || '保存失败，修改已保留，请重试。'
    } finally {
        saving.value = false
    }
}

const beforeUnload = (event: BeforeUnloadEvent) => {
    if (!isDirty.value) return
    event.preventDefault()
    event.returnValue = ''
}

onBeforeRouteLeave(async () => {
    if (!isDirty.value) return true
    try {
        await ElMessageBox.confirm('设置尚未保存，确定离开吗？', '未保存的修改', {
            confirmButtonText: '离开', cancelButtonText: '继续编辑', type: 'warning'
        })
        return true
    } catch {
        return false
    }
})

onMounted(() => {
    load()
    window.addEventListener('beforeunload', beforeUnload)
})
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload))
</script>

<style scoped lang="scss">
.order-settings { min-width: 0; }
.page-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 14px; }
.save-status { color: var(--el-color-warning-dark-2); font-size: 12px; }
.load-error { display: flex; align-items: center; gap: 16px; }
.settings-skeleton { padding: 24px; }
.settings-tabs { background: var(--el-bg-color); min-height: 640px; }
.settings-tabs :deep(.el-tabs__header.is-left) { margin: 0; width: 180px; padding-top: 20px; }
.settings-tabs :deep(.el-tabs__nav.is-left) { width: 100%; }
.settings-tabs :deep(.el-tabs__nav-wrap.is-left::after) { width: 1px; background: var(--el-border-color-lighter); }
.settings-tabs :deep(.el-tabs__item.is-left) { justify-content: flex-start; padding: 0 20px; height: 48px; font-size: 14px; }
.settings-tabs :deep(.el-tabs__item.is-active) { background: var(--el-color-primary-light-9); }
.settings-tabs :deep(.el-tabs__content) { min-width: 0; padding: 0; }
.nav-label { display: inline-flex; align-items: center; gap: 10px; white-space: nowrap; }
.nav-label .el-icon { font-size: 17px; }
.settings-panel { box-sizing: border-box; padding: 28px 32px; max-width: 1020px; margin: 0 auto; }
.panel-heading { display: flex; align-items: center; min-width: 0; padding-bottom: 20px; border-bottom: 1px solid var(--el-border-color-lighter); }
.panel-heading h2 { margin: 0; font-size: 18px; font-weight: 600; line-height: 26px; color: var(--el-text-color-primary); }
.settings-section { padding: 24px 0; border-bottom: 1px solid var(--el-border-color-lighter); scroll-margin-top: 100px; }
.settings-section:last-child { border-bottom: none; padding-bottom: 0; }
.settings-section > h3 { margin: 0 0 4px; font-size: 14px; font-weight: 600; color: var(--el-text-color-primary); }
.section-note { display: inline-block; margin-left: 10px; font-size: 12px; font-weight: 400; color: var(--el-text-color-secondary); }
.dependent-fields { padding-left: 18px; border-left: 2px solid var(--el-border-color-lighter); }
.field-hint { margin: 12px 0 0; font-size: 13px; color: var(--el-text-color-secondary); line-height: 1.7; }
.delivery-copy-presets, .pickup-schedule-controls { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
.delivery-copy-presets :deep(.el-button + .el-button) { margin-left: 0; }
.pickup-schedule-controls :deep(.el-select) { width: 150px; }
.field-warning { margin: 0 0 12px; color: var(--el-color-warning-dark-2); font-size: 12px; line-height: 1.7; }
.validation-error { margin-top: 16px; }
.channel-list { border-top: none; margin-top: 12px; }
.channel-title { font-size: 13px; font-weight: 500; }
.channel-status { margin-left: 12px; font-size: 12px; color: var(--el-text-color-placeholder); }
.channel-status.enabled { color: var(--el-color-success); }
.channel-list :deep(.el-collapse-item__content) { padding-bottom: 12px; }
.erp-panel :deep(.erp-integration) { border: none; border-radius: 0; padding: 0; }

@media (max-width: 1100px) {
    .settings-tabs :deep(.el-tabs__header.is-left) { width: 154px; }
    .settings-tabs :deep(.el-tabs__item.is-left) { padding: 0 14px; }
    .settings-panel { padding: 24px; }
}
@media (max-width: 760px) {
    .settings-tabs :deep(.el-tabs__header.is-top) { margin: 0; }
    .settings-tabs :deep(.el-tabs__nav-wrap.is-top) { margin: 0; overflow-x: auto; padding: 0; }
    .settings-tabs :deep(.el-tabs__nav-scroll) { overflow: visible; }
    .settings-tabs :deep(.el-tabs__nav.is-top) { display: flex; float: none; width: max-content; transform: none !important; }
    .settings-tabs :deep(.el-tabs__item.is-top) { display: inline-flex; height: 44px; padding: 0 14px; border-bottom: 2px solid transparent; }
    .settings-tabs :deep(.el-tabs__item.is-active) { border-bottom-color: var(--el-color-primary); }
    .settings-tabs :deep(.el-tabs__active-bar), .settings-tabs :deep(.el-tabs__nav-prev), .settings-tabs :deep(.el-tabs__nav-next) { display: none; }
    .settings-tabs :deep(.el-tabs__nav-wrap.is-top::after) { width: 100%; height: 1px; top: auto; bottom: 0; }
    .settings-panel { padding: 20px 16px; }
    .settings-section { padding: 20px 0; }
    .load-error { flex-wrap: wrap; }
    .dependent-fields { padding-left: 12px; }
}
@media (prefers-reduced-motion: reduce) {
    .order-settings :deep(*) { scroll-behavior: auto; }
}
</style>
