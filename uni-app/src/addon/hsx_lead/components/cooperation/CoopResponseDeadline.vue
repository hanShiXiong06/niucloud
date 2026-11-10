<template>
    <view v-if="clock.visible.value" class="coop-response-deadline" :class="{ 'is-compact':compact, 'is-overdue':clock.overdue.value }">
        <view class="coop-response-deadline__line"><u-icon name="clock" :size="compact ? '12' : '16'" :color="clock.overdue.value ? '#a66a32' : '#758293'" /><text>{{ clock.overdue.value ? '接单提醒时间已到' : '接单提醒倒计时' }}</text><text v-if="!clock.overdue.value && clock.formatted.value" class="coop-response-deadline__digits">{{ clock.formatted.value }}</text><text v-else-if="!clock.overdue.value" class="coop-response-deadline__waiting">等待确认</text></view>
        <text v-if="!compact" class="coop-response-deadline__hint">{{ isPublisher ? clock.overdue.value ? '原门店仍可接单。需要调整时，可重新选择门店。' : '提醒时间到后仍保留原交付，不会自动更换门店。' : clock.overdue.value ? '仍可接受本次交付；发布方重新选店后将不可再接单。' : '请及时确认，提醒时间到后仍可接单。' }}</text>
        <text v-else-if="clock.overdue.value" class="coop-response-deadline__hint">{{ isPublisher ? '仍保留原门店' : '仍可接单' }}</text>
    </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { useResponseDeadline } from '../../composables/useResponseDeadline'
const props=withDefaults(defineProps<{ lead:Record<string,any>; active?:boolean; compact?:boolean; direction?:string }>(),{active:true,compact:false,direction:''})
const clock=useResponseDeadline(()=>props.lead,()=>props.active)
const isPublisher=computed(()=>props.direction ? props.direction==='outgoing' : !props.lead.can_accept && props.lead.can_withdraw===true)
</script>
<style scoped lang="scss">
.coop-response-deadline{padding:24rpx 28rpx;margin:0 24rpx 20rpx;background:#f2f5f8;border:1rpx solid #e4e9ef;border-radius:14rpx}.coop-response-deadline__line{display:flex;align-items:center;gap:12rpx;color:#647386;font-size:25rpx;line-height:1.6}.coop-response-deadline__digits{margin-left:auto;font-size:34rpx;font-weight:650;letter-spacing:2rpx;font-variant-numeric:tabular-nums;color:#394a60}.coop-response-deadline__waiting{margin-left:auto;font-size:24rpx;color:#929ba6}.coop-response-deadline__hint{display:block;margin-top:10rpx;font-size:22rpx;line-height:1.8;color:#85909e}.coop-response-deadline.is-overdue{background:#fff8ef;border-color:#f0e1cf}.coop-response-deadline.is-overdue .coop-response-deadline__line{color:#9c6d3c}.coop-response-deadline.is-compact{display:flex;align-items:center;justify-content:space-between;gap:12rpx;padding:14rpx 18rpx;margin:20rpx 0 0;border-radius:8rpx}.is-compact .coop-response-deadline__line{font-size:22rpx;gap:8rpx}.is-compact .coop-response-deadline__digits{font-size:24rpx;letter-spacing:1rpx;margin-left:8rpx}.is-compact .coop-response-deadline__hint{margin:0;font-size:21rpx;flex:none}
</style>
