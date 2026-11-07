<template>
    <view class="coop-merchant-avatar" :class="['is-' + size, { 'is-member': showMember, 'is-highlighted': showHighlight, 'is-emphasized': showEmphasis, 'has-effect': showEffect }]" :style="skinStyle" role="img" :aria-label="`${name || '合作门店'}头像`">
        <view v-if="showHighlight" class="coop-merchant-avatar__wing coop-merchant-avatar__wing--left" aria-hidden="true"><view class="coop-merchant-avatar__feather is-feather-one" /><view class="coop-merchant-avatar__feather is-feather-two" /><view class="coop-merchant-avatar__feather is-feather-three" /><image class="coop-merchant-avatar__wing-art" :src="wingImage" mode="aspectFit" /></view>
        <view v-if="showHighlight" class="coop-merchant-avatar__wing coop-merchant-avatar__wing--right" aria-hidden="true"><view class="coop-merchant-avatar__feather is-feather-one" /><view class="coop-merchant-avatar__feather is-feather-two" /><view class="coop-merchant-avatar__feather is-feather-three" /><image class="coop-merchant-avatar__wing-art" :src="wingImage" mode="aspectFit" /></view>
        <view v-if="showEffect" class="coop-merchant-avatar__halo" aria-hidden="true" />
        <view class="coop-merchant-avatar__photo">
            <image v-if="source && !failed" class="coop-merchant-avatar__image" :src="source" mode="aspectFill" @error="failed = true" />
            <view v-else class="coop-merchant-avatar__fallback"><u-icon name="home" :size="size === 'large' ? '30' : size === 'small' ? '17' : '25'" color="#8593a2" /></view>
            <view v-if="showEffect" class="coop-merchant-avatar__glint" />
        </view>

    </view>
</template>
<script setup lang="ts">
import highlightWing from '../../static/cooperation/visuals/reliable-wing.png'
import { computed, ref, watch } from 'vue'
import { img } from '@/utils/common'
import { membershipSkin, membershipSkinVars } from '../../utils/membership-skin'
import { bundledImage } from '../../utils/bundled-image'
const props = withDefaults(defineProps<{ image?: string; name?: string; highlighted?: boolean; active?: boolean; rank?: number; appearance?: Record<string, any>; size?: 'default' | 'large' | 'small' }>(), { image: '', name: '', highlighted: false, active: false, rank: 0, appearance: () => ({}), size: 'default' })
const failed = ref(false)
const wingImage = bundledImage(highlightWing)
const source = computed(() => typeof props.image === 'string' && props.image ? img(props.image) : '')
// A selected tier or a visual object alone is never proof of purchased rights.
const showMember = computed(() => props.active === true && Number.isSafeInteger(props.rank) && props.rank > 0)
const skin = computed(() => membershipSkin(props.appearance, props.rank))
const skinStyle = computed(() => showMember.value ? membershipSkinVars(props.appearance, props.rank) : {})
const showEffect = computed(() => showMember.value && skin.value.avatarEffect === true)
// Every effective paid tier may use its own configured wings. Highlight only
// changes emphasis; it never grants the effect to unpaid or expired accounts.
const showHighlight = computed(() => showEffect.value)
const showEmphasis = computed(() => showHighlight.value && props.highlighted === true)
watch(() => props.image, () => { failed.value = false })
</script>
<style scoped lang="scss">
.coop-merchant-avatar { position: relative; width: 96rpx; height: 96rpx; padding: 5rpx; border: 2rpx solid #e4eaf2; border-radius: 24rpx; background: #fff; box-sizing: border-box; flex: none; }
.coop-merchant-avatar__photo { position: relative; z-index: 1; width: 100%; height: 100%; overflow: hidden; border-radius: 17rpx; background: #edf2f6; }
.coop-merchant-avatar__image,.coop-merchant-avatar__fallback { width: 100%; height: 100%; }
.coop-merchant-avatar__fallback { display: flex; align-items: center; justify-content: center; }
.coop-merchant-avatar.is-member { border-color:transparent;background:var(--lead-member-gradient); }
.coop-merchant-avatar.is-highlighted { margin-left: 26rpx; margin-right: 32rpx; }
.coop-merchant-avatar__wing { position: absolute; z-index: 0; top: 8rpx; width: 54rpx; height: 78rpx; pointer-events: none; }
.coop-merchant-avatar__wing--left { left: -42rpx; transform-origin: 100% 75%; animation: coop-avatar-wing-flap-left 5.6s ease-in-out infinite; }
.coop-merchant-avatar__wing--right { right: -42rpx; transform-origin: 0% 75%; animation: coop-avatar-wing-flap-right 5.6s ease-in-out infinite; }
.is-emphasized .coop-merchant-avatar__wing { animation-duration:3.8s; }
.coop-merchant-avatar__feather { position:absolute;left:6%;bottom:14%;width:86%;height:24%;border:1rpx solid var(--lead-member-accent);border-radius:90% 5% 90% 12%;background:linear-gradient(145deg,#fff,var(--lead-member-start) 35%,var(--lead-member-end));box-sizing:border-box;transform-origin:100% 75%; }
.is-feather-one { transform:rotate(46deg);width:95%;bottom:22%; }.is-feather-two { transform:rotate(23deg);width:88%;bottom:18%; }.is-feather-three { transform:rotate(0);width:76%;bottom:14%; }
.coop-merchant-avatar__wing-art { position:absolute;display:block;left:0;top:0;width:100%;height:100%;opacity:.16;pointer-events:none; }
.coop-merchant-avatar__wing--right .coop-merchant-avatar__feather { left:auto;right:6%;transform-origin:0% 75%;border-radius:5% 90% 12% 90%; }
.coop-merchant-avatar__wing--right .is-feather-one { transform:rotate(-46deg); }.coop-merchant-avatar__wing--right .is-feather-two { transform:rotate(-23deg); }
.coop-merchant-avatar__wing--right .coop-merchant-avatar__wing-art { transform: scaleX(-1); }
.coop-merchant-avatar__halo { position: absolute; z-index: 0; inset: 0; border-radius: inherit; pointer-events: none; background:var(--lead-member-gradient); background-size: 300% 100%; animation: coop-avatar-border-flow 4.8s ease-in-out infinite; }
.coop-merchant-avatar__glint { position: absolute; z-index: 2; top: -30%; bottom: -30%; left: -75%; width: 60%; background: linear-gradient(90deg,transparent,rgba(255,255,255,.65),transparent); transform: translateX(0) skewX(-20deg); animation: coop-avatar-glint 4.8s ease-in-out infinite; pointer-events: none; }
.is-large { width: 116rpx; height: 116rpx; border-radius: 27rpx; }.is-large .coop-merchant-avatar__photo { border-radius: 20rpx; }
.is-large.is-highlighted { margin-left: 28rpx; margin-right: 28rpx; }.is-large .coop-merchant-avatar__wing { top: 10rpx; width: 64rpx; height: 96rpx; }.is-large .coop-merchant-avatar__wing--left { left: -49rpx; }.is-large .coop-merchant-avatar__wing--right { right: -49rpx; }
.is-small { width: 72rpx; height: 72rpx; padding: 3rpx; border-radius: 18rpx; }.is-small .coop-merchant-avatar__photo { border-radius: 12rpx; }.is-small.is-highlighted { margin-left: 18rpx; margin-right: 18rpx; }.is-small .coop-merchant-avatar__wing { top: 5rpx; width: 34rpx; height: 58rpx; }.is-small .coop-merchant-avatar__wing--left { left: -26rpx; }.is-small .coop-merchant-avatar__wing--right { right: -26rpx; }
@keyframes coop-avatar-wing-flap-left { 0%,100% { transform: rotate(-5deg); } 50% { transform: rotate(5deg); } }
@keyframes coop-avatar-wing-flap-right { 0%,100% { transform: rotate(5deg); } 50% { transform: rotate(-5deg); } }
@keyframes coop-avatar-border-flow { 0%,100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }
@keyframes coop-avatar-glint { 0% { transform: translateX(0) skewX(-20deg); } 27%,100% { transform: translateX(350%) skewX(-20deg); } }
@media (prefers-reduced-motion: reduce) { .coop-merchant-avatar__wing { animation: none; transform: none; }.coop-merchant-avatar__halo { animation: none; }.coop-merchant-avatar__glint { animation: none; display: none; } }
</style>
