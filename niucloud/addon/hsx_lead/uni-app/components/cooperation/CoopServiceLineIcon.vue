<template>
    <view class="service-line-icon" :style="iconStyle" aria-hidden="true">
        <view class="service-line-icon__drawing" :class="'is-' + actualShape" :style="drawingStyle">
            <template v-if="actualShape === 'phone'"
                ><view class="service-stroke phone-back" /><view class="service-stroke phone-front" /><view
                    class="service-stroke phone-speaker" /><view class="service-stroke phone-home"
            /></template>
            <template v-else-if="actualShape === 'new'"
                ><view class="service-stroke new-device" /><view class="service-stroke new-plus-h" /><view
                    class="service-stroke new-plus-v" /><view class="service-stroke new-home"
            /></template>
            <template v-else-if="actualShape === 'rent'"
                ><view class="service-stroke rent-calendar" /><view class="service-stroke rent-divider" /><view
                    class="service-stroke rent-binding rent-binding--left" /><view
                    class="service-stroke rent-binding rent-binding--right" /><view
                    class="service-stroke rent-clock" /><view class="service-stroke rent-hour" /><view
                    class="service-stroke rent-minute"
            /></template>
            <template v-else-if="actualShape === 'exchange'"
                ><view class="service-stroke exchange-line exchange-line--top" /><view
                    class="service-stroke exchange-head exchange-head--right" /><view
                    class="service-stroke exchange-line exchange-line--bottom" /><view
                    class="service-stroke exchange-head exchange-head--left"
            /></template>
            <template v-else-if="actualShape === 'wifi'"
                ><view class="service-stroke wifi-wave wifi-wave--outer" /><view
                    class="service-stroke wifi-wave wifi-wave--middle" /><view class="service-stroke wifi-dot"
            /></template>
            <template v-else-if="actualShape === 'repair'"
                ><view class="service-stroke repair-handle" /><view class="service-stroke repair-head" /><view
                    class="service-stroke repair-hole"
            /></template>
            <template v-else
                ><view class="service-stroke recycle-roof" /><view class="service-stroke recycle-house" /><view
                    class="service-stroke recycle-stem" /><view class="service-stroke recycle-arrow"
            /></template>
        </view>
    </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'

// Small, local linework: no remote font/SVG support or canvas lifecycle is
// required on the Mini Program. The labelled parent remains the only action.
const props = withDefaults(defineProps<{ shape?: string; color?: string; size?: number }>(), { shape: 'phone', color: '', size: 30 })
const shapes = ['phone', 'new', 'rent', 'exchange', 'wifi', 'repair', 'recycle']
const actualShape = computed(() => shapes.includes(props.shape) ? props.shape : 'phone')
const actualSize = computed(() => Number.isFinite(Number(props.size)) ? Math.max(16, Math.min(48, Math.round(Number(props.size)))) : 30)
const iconStyle = computed(() => ({ width: actualSize.value + 'px', height: actualSize.value + 'px', color: props.color || 'inherit' }))
const drawingStyle = computed(() => ({ transform: `scale(${actualSize.value / 32})` }))
</script>
<style scoped lang="scss">
.service-line-icon { display:inline-flex;align-items:center;justify-content:center;flex:none;position:relative;vertical-align:middle;pointer-events:none; }
.service-line-icon__drawing { position:relative;flex:none;width:32px;height:32px;transform-origin:center; }
.service-stroke { position:absolute;box-sizing:border-box;border-color:currentColor; }
.phone-front { left:12px;top:3px;width:16px;height:26px;border:1.8px solid;border-radius:3px; }
.phone-back { left:5px;top:8px;width:5px;height:21px;border:1.8px solid;border-right:0;border-radius:3px 0 0 3px; }
.phone-speaker { left:17px;top:7px;width:6px;border-top:1.8px solid;border-radius:1px; }
.phone-home { left:18px;top:24px;width:4px;border-top:1.8px solid;border-radius:1px; }
.new-device { left:8px;top:3px;width:17px;height:26px;border:1.8px solid;border-radius:3px; }
.new-plus-h { left:12px;top:14px;width:9px;border-top:1.8px solid;border-radius:1px; }
.new-plus-v { left:15.6px;top:10.5px;height:9px;border-left:1.8px solid;border-radius:1px; }
.new-home { left:14.5px;top:24px;width:4px;border-top:1.8px solid;border-radius:1px; }
.rent-calendar { left:4px;top:7px;width:24px;height:22px;border:1.8px solid;border-radius:3px; }
.rent-divider { left:5px;top:13px;width:22px;border-top:1.8px solid; }
.rent-binding { top:3px;height:7px;border-left:1.8px solid;border-radius:1px; }.rent-binding--left { left:10px; }.rent-binding--right { left:22px; }
.rent-clock { left:11px;top:16px;width:10px;height:10px;border:1.8px solid;border-radius:50%; }
.rent-hour { left:15.2px;top:18px;height:4px;border-left:1.6px solid; }.rent-minute { left:15.2px;top:20.4px;width:3.8px;border-top:1.6px solid; }
.exchange-line { left:4px;width:24px;border-top:1.8px solid;border-radius:1px; }.exchange-line--top { top:10px; }.exchange-line--bottom { top:23px; }
.exchange-head { width:8px;height:8px;border-top:1.8px solid;border-right:1.8px solid; }.exchange-head--right { right:5px;top:6.9px;transform:rotate(45deg); }.exchange-head--left { left:5px;top:19.9px;transform:rotate(225deg); }
.wifi-wave { border:1.8px solid;border-left-color:transparent;border-bottom-color:transparent;border-radius:50%;transform:rotate(-45deg); }
.wifi-wave--outer { width:27px;height:27px;left:2.5px;top:4px; }.wifi-wave--middle { width:16px;height:16px;left:8px;top:15px; }
.wifi-dot { left:14.5px;top:27px;width:3px;height:3px;border:1.5px solid;border-radius:50%; }
.repair-handle { left:14px;top:12px;width:7px;height:20px;border:1.8px solid;border-radius:4px;transform:rotate(45deg);transform-origin:center top;border-top:0; }
.repair-head { left:14px;top:2px;width:15px;height:15px;border:1.8px solid;border-top-color:transparent;border-radius:50%;transform:rotate(45deg); }
.repair-hole { left:4.5px;top:26px;width:3px;height:3px;border:1.2px solid;border-radius:50%; }
.recycle-roof { left:7px;top:6px;width:18px;height:18px;border-left:1.8px solid;border-top:1.8px solid;transform:rotate(45deg);border-radius:2px 0 0; }
.recycle-house { left:7px;top:13px;width:18px;height:17px;border:1.8px solid;border-top:0;border-radius:0 0 3px 3px; }
.recycle-stem { left:15.1px;top:14px;height:10px;border-left:1.8px solid; }
.recycle-arrow { left:12.5px;top:18px;width:7px;height:7px;border-bottom:1.8px solid;border-right:1.8px solid;transform:rotate(45deg); }
</style>
