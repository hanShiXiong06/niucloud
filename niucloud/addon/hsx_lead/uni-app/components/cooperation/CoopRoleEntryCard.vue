<template>
    <view
        class="coop-role-card"
        :class="['is-'+layout, { 'is-borderless': !shown(settings.showBorder), 'with-motion': shown(settings.animate) }]"
        :style="cardStyle"
        role="button"
        :aria-label="settings.title"
        @tap="go"
    >
        <view v-if="frame.hasImage" class="coop-role-card__mask" :style="frame.mask" />
        <view class="coop-role-card__content">
            <view class="coop-role-card__copy"
                ><text v-if="settings.title" class="coop-role-card__title">{{ settings.title }}</text
                ><text v-if="settings.subtitle" class="coop-role-card__subtitle">{{ settings.subtitle }}</text
                ><button
                    v-if="settings.buttonText"
                    class="coop-role-card__button"
                    :class="{'is-solid': settings.buttonStyle==='solid', 'is-link': settings.buttonStyle!=='solid'}"
                    hover-class="coop-role-card__pressed"
                    @tap.stop="go"
                    ><text>{{ settings.buttonText }}</text
                    ><u-icon name="arrow-right" size="11" color="currentColor" /></button
            ></view>
            <image v-if="picture" class="coop-role-card__art" :src="picture" mode="aspectFit" /><CoopBusinessIcon
                v-else-if="shown(settings.showImage)"
                :name="role==='customers'?'customer':'store'"
                :color="settings.iconColor"
                :size="layout==='classic'?'large':'default'"
            />
        </view>
    </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import useDiyStore from '@/app/stores/diy'
import { img } from '@/utils/common'
import CoopBusinessIcon from './CoopBusinessIcon.vue'
import { cooperationDiyFrame } from '../../utils/cooperation-diy-frame'
import { cooperationDiyVars } from '../../utils/cooperation-diy-style'
import { interactionFeedback } from '../../utils/interaction-feedback'
import { bundledImage } from '../../utils/bundled-image'
import storeDiscoveryArt from '../../static/cooperation/visuals/store-discovery.png'
import deliveryCapabilityArt from '../../static/cooperation/visuals/delivery-capability.png'
const props = defineProps(['role', 'appearance', 'layout', 'preview'])
const store = useDiyStore()
const role = computed(() => props.role === 'customers' ? 'customers' : 'stores')
const settings = computed(() => {
    const value = props.appearance || {}, customer = role.value === 'customers', link = value.buttonStyle === 'link' || value.buttonStyle === undefined && customer
    const rounded = value.itemRadius === undefined || value.itemRadius === null || value.itemRadius === '' ? 8 : value.itemRadius
    return { title:customer?'找客户':'找门店', subtitle:customer?'查看交付给我的客户':'发布客户需求，找门店交付', buttonText:customer?'查看客户':'去找门店', buttonStyle:link?'link':'solid', buttonColor:'#398aff', buttonTextColor:link?'#398aff':'#fff', image:'', usePresetImages:1, showImage:1, showBorder:1, animate:1, layoutPreset:'classic', titleSize:20, ...value, topRounded:value.topRounded??rounded, bottomRounded:value.bottomRounded??rounded }
})
const shown = (value: unknown) => ![false, 0, '0'].includes(value as any)
const layout = computed(() => ['classic','compact','clean','soft'].includes(props.layout || settings.value.layoutPreset) ? props.layout || settings.value.layoutPreset : 'classic')
const frame = computed(() => cooperationDiyFrame(settings.value, img))
const cardStyle = computed(() => ({ ...cooperationDiyVars(settings.value), ...frame.value.style }))
const picture = computed(() => {
    if (!shown(settings.value.showImage)) return ''
    if (typeof settings.value.image === 'string' && settings.value.image.trim()) return img(settings.value.image.trim())
    if (!shown(settings.value.usePresetImages) || !['classic','soft'].includes(layout.value)) return ''
    return bundledImage(role.value === 'customers' ? deliveryCapabilityArt : storeDiscoveryArt)
})
function go() {
    if (props.preview === true || store.mode === 'decorate') return
    interactionFeedback('navigate')
    uni.navigateTo({ url: role.value === 'customers' ? '/addon/hsx_lead/pages/workbench/index' : '/addon/hsx_lead/pages/stores/index' })
}
</script>
<style scoped lang="scss">
.coop-role-card { box-sizing:border-box;min-width:0;width:100%;border:1px solid var(--lead-diy-border,#e4e8ef);color:var(--lead-diy-title,#202124); }
.coop-role-card.is-borderless { border:0; }
.coop-role-card__mask { pointer-events:none; }
.coop-role-card__content { position:relative;display:flex;align-items:center;gap:16px;padding:var(--lead-diy-content-padding,16px);min-height:132px;box-sizing:border-box; }
.coop-role-card__copy { flex:1;min-width:0; }
.coop-role-card__title { display:block;font-size:var(--lead-diy-title-size,20px);font-weight:650;line-height:1.5;overflow-wrap:anywhere; }
.coop-role-card__subtitle { display:block;margin-top:6px;color:var(--lead-diy-text,#606266);font-size:var(--lead-diy-text-size,13px);line-height:1.7;overflow-wrap:anywhere; }
.coop-role-card__button { display:inline-flex;align-items:center;justify-content:center;gap:8px;margin:14px 0 0;padding:0 15px;min-height:44px;line-height:1.5;font-size:13px;font-weight:600;border:0;border-radius:var(--lead-diy-button-radius,6px); }
.coop-role-card__button::after { border:0; }
.coop-role-card__button.is-solid { background:var(--lead-diy-button,var(--primary-color,#398aff));color:var(--lead-diy-button-text,#fff); }
.coop-role-card__button.is-link { background:transparent;padding:0;color:var(--lead-diy-button,var(--primary-color,#398aff)); }
.coop-role-card__art { display:block;flex:none;width:100px;height:100px; }
.coop-role-card__pressed { opacity:.65; }
.is-compact .coop-role-card__content,.is-soft .coop-role-card__content { flex-direction:column-reverse;align-items:flex-start;gap:12px; }
.is-compact .coop-role-card__art { width:40px;height:40px; }
.is-soft .coop-role-card__content { align-items:center;text-align:center; }
.is-soft .coop-role-card__art { width:64px;height:64px; }
.is-clean .coop-role-card__content { min-height:86px; }
.is-clean .coop-role-card__art { width:44px;height:44px; }
.with-motion .coop-role-card__art { animation:coop-role-float 5s ease-in-out infinite; }
@keyframes coop-role-float { 0%,100%{transform:translateY(0)}50%{transform:translateY(-3px)} }
@media(max-width:350px){.coop-role-card__content{gap:10px}.coop-role-card__art{width:76px;height:76px}}
@media(prefers-reduced-motion:reduce){.with-motion .coop-role-card__art{animation:none}}
</style>
