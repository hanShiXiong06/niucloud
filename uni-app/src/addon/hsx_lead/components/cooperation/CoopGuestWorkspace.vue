<template>
    <view class="coop-guest-workspace">
        <view class="coop-guest-hero">
            <view class="coop-guest-hero__copy">
                <text class="coop-guest-hero__eyebrow">{{ outgoing ? '把客户交给合适的门店' : '让门店能力承接客户需求' }}</text>
                <text class="coop-guest-hero__title">{{ outgoing ? '客户在当地\n交付也在当地' : '做好本地交付\n接住每一份信任' }}</text>
            </view>
            <image class="coop-guest-hero__image" :src="heroImage" mode="aspectFit" aria-hidden="true" />
        </view>
        <text class="coop-guest-intro">{{ outgoing ? '发布客户需求，按地区和业务找到交付门店，后续进度在这里跟进。' : '登记门店的经营范围，承接商户交付的客户，跟进、成交，一处处理。' }}</text>

        <view class="coop-guest-primary">
            <u-button :custom-style="cooperationButtonStyle" type="primary" shape="circle" :disabled="enabled === false" :text="enabled === false ? '协作服务暂未开放' : outgoing ? '发布客户需求' : '登记门店交付能力'" @click="act(outgoing ? 'publish' : 'profile')" />
        </view>
        <view v-if="error" class="coop-guest-notice" role="status"><text>业务信息暂未更新</text><button :disabled="loading" @tap="emit('retry')">重新加载</button></view>

        <view v-if="loading && !entries.length" class="coop-guest-services-loading" aria-busy="true"><u-loading-icon size="16" /><text>正在读取本站协作业务</text></view>
        <view v-else-if="entries.length" class="coop-guest-services">
            <view class="coop-guest-section-heading"><text>本站协作业务</text><text class="coop-guest-section-heading__aside">{{ entries.length }} 项服务</text></view>
            <view class="coop-guest-services__grid">
                <button v-for="item in entries" :key="item.key" class="coop-guest-service" hover-class="coop-guest-pressed" :disabled="enabled === false" @tap="act('business', item.key)">
                    <view class="coop-guest-service__icon" :style="{ color: item.iconColor, background: item.iconBgColor }"><CoopServiceLineIcon :shape="item.shape" :size="25" /></view>
                    <text>{{ item.name }}</text>
                </button>
            </view>
        </view>

        <view class="coop-guest-guide">
            <view class="coop-guest-section-heading"><text>{{ outgoing ? '从需求到交付' : '从接单到成交' }}</text></view>
            <view v-for="(step, index) in steps" :key="step.title" class="coop-guest-step">
                <view class="coop-guest-step__number"><text>{{ index + 1 }}</text><view v-if="index < steps.length - 1" class="coop-guest-step__line" /></view>
                <view class="coop-guest-step__content"><text>{{ step.title }}</text><text>{{ step.description }}</text></view>
            </view>
        </view>
        <button class="coop-guest-progress" hover-class="coop-guest-pressed" :disabled="enabled === false" @tap="act('progress')"><view><u-icon :name="outgoing ? 'order' : 'chat'" size="20" color="currentColor" /><text>{{ outgoing ? '查看我发出的需求' : '查看交付给我的客户' }}</text></view><u-icon name="arrow-right" size="13" color="#8b95a5" /></button>
    </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import CoopServiceLineIcon from './CoopServiceLineIcon.vue'
import { cooperationButtonStyle } from '../../utils/mobile-ui'
import { bundledImage } from '../../utils/bundled-image'
import { presentBusinessEntries } from '../../utils/cooperation-business-presentation'
import storeDiscoveryArt from '../../static/cooperation/visuals/store-discovery.png'
import deliveryCapabilityArt from '../../static/cooperation/visuals/delivery-capability.png'

const props = withDefaults(defineProps<{
    direction?: 'outgoing' | 'incoming'
    services?: { key: string; name: string; dimension?: string }[]
    enabled?: boolean | null
    loading?: boolean
    error?: string
}>(), { direction: 'outgoing', services: () => [], enabled: null, loading: false, error: '' })
const emit = defineEmits<{ (event: 'action', value: { kind: string; business_type?: string }): void; (event: 'retry'): void }>()
const outgoing = computed(() => props.direction === 'outgoing')
const heroImage = computed(() => bundledImage(outgoing.value ? storeDiscoveryArt : deliveryCapabilityArt))
// Only the server's public projection supplies services. No synthetic leads,
// customer contacts, merchant counts or default enabled businesses are shown.
const entries = computed(() => presentBusinessEntries(props.services).slice(0, 30))
const steps = computed(() => outgoing.value ? [
    { title: '说明客户需要什么', description: '选择业务，补充需求与客户所在地区' },
    { title: '选择合适的交付门店', description: '按地区与交付能力匹配，由你选择门店' },
    { title: '跟进交付与协作收益', description: '查看接单、成交和后续结算进度' }
] : [
    { title: '登记门店与经营范围', description: '让有对应需求的商户找到你' },
    { title: '确认接单，联系客户', description: '核对需求后，由门店与客户直接沟通' },
    { title: '反馈结果，完成协作', description: '成交后按订单约定支付协作费用' }
])
function act(kind: string, business_type?: string) {
    if (props.enabled === false) return
    emit('action', { kind, ...(business_type ? { business_type } : {}) })
}
</script>

<style scoped lang="scss">
.coop-guest-workspace { min-height:100%;padding:22px 16px 24px;box-sizing:border-box;background:#fff;color:#202124; }
.coop-guest-hero { display:flex;align-items:center;justify-content:space-between;gap:8px;min-width:0; }
.coop-guest-hero__copy { flex:1;min-width:0; }
.coop-guest-hero__eyebrow { display:block;color:#637188;font-size:12px;line-height:20px; }
.coop-guest-hero__title { display:block;margin-top:8px;font-size:24px;line-height:1.5;font-weight:700;white-space:pre-line; }
.coop-guest-hero__image { display:block;flex:none;width:102px;height:112px; }
.coop-guest-intro { display:block;margin-top:12px;font-size:14px;line-height:24px;color:#647184; }
.coop-guest-primary { margin-top:20px; }
.coop-guest-section-heading { display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:16px;font-size:16px;line-height:24px;font-weight:600; }
.coop-guest-section-heading__aside { color:#7b8595;font-size:12px;font-weight:400; }
.coop-guest-services { margin-top:28px; }
.coop-guest-services__grid { display:flex;flex-wrap:wrap;row-gap:16px; }
.coop-guest-service { display:flex;flex-direction:column;align-items:center;gap:8px;width:25%;min-width:0;min-height:76px;padding:0 4px;margin:0;border:0;border-radius:8px;background:transparent;box-sizing:border-box;color:#374356;font-size:13px;line-height:20px; }
.coop-guest-service::after,.coop-guest-progress::after,.coop-guest-notice button::after { border:0; }
.coop-guest-service>text { display:block;max-width:100%;word-break:break-all; }
.coop-guest-service__icon { display:flex;align-items:center;justify-content:center;flex:none;width:44px;height:44px;border-radius:12px; }
.coop-guest-services-loading { display:flex;align-items:center;justify-content:center;gap:8px;min-height:88px;color:#7b8595;font-size:13px; }
.coop-guest-guide { margin-top:28px;padding-top:24px;border-top:1px solid #eef0f3; }
.coop-guest-step { display:flex;gap:12px;min-height:68px; }
.coop-guest-step__number { position:relative;flex:none;width:24px;text-align:center; }
.coop-guest-step__number>text { display:block;position:relative;z-index:1;width:24px;height:24px;border-radius:50%;background:#eef4fc;color:#4f6f99;line-height:24px;font-size:12px;font-weight:600; }
.coop-guest-step__line { position:absolute;top:29px;bottom:6px;left:11px;width:1px;background:#e6ebf2; }
.coop-guest-step__content { flex:1;min-width:0;padding-bottom:18px; }
.coop-guest-step__content>text { display:block;font-size:14px;line-height:24px; }
.coop-guest-step__content>text+text { margin-top:2px;font-size:12px;line-height:20px;color:#748093; }
.coop-guest-progress { display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:52px;margin:4px 0 0;padding:12px 14px;border:0;border-radius:8px;background:#f5f7fa;color:#485c78;box-sizing:border-box;font-size:14px;line-height:22px;text-align:left; }
.coop-guest-progress>view { display:flex;align-items:center;gap:10px;min-width:0; }
.coop-guest-progress>view>text { min-width:0; }
.coop-guest-notice { display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:12px;color:#687589;font-size:12px;line-height:20px; }
.coop-guest-notice button { min-height:44px;padding:0 8px;margin:0;border:0;background:transparent;color:var(--coop-primary,#3478cc);font-size:12px;line-height:44px; }
.coop-guest-pressed { opacity:.72; }
@media(max-width:340px) { .coop-guest-hero__title { font-size:22px; }.coop-guest-hero__image { width:86px;height:102px; }.coop-guest-service { width:33.3333%; } }
</style>
