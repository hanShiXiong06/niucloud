<template>
    <view class="coop-experience" :style="{ '--experience-color': tone }">
        <view class="coop-experience__heading"><text>会员经验</text><text>{{ completedText }}</text></view>
        <view class="coop-experience__value"><text>{{ valueText }}</text><text>经验</text></view>
        <view v-if="hasSources" class="coop-experience__sources"><text>真实付佣 {{ experienceText(experience.earned_value) }}</text><text>补足经验 {{ experienceText(experience.purchased_value) }}</text></view>
        <template v-if="nextLevel && hasTarget">
            <view class="coop-experience__target"><text>下一等级 · {{ nextLevel.name }}</text><text>{{ targetText }} 经验</text></view>
            <u-line-progress v-if="percentage !== null" :percentage="percentage" :active-color="tone" inactive-color="#ededed" :height="5" :show-text="false" />
            <view class="coop-experience__hint"><text>{{ nextLevel.experience_eligible === true ? '经验已达标，可自主选择升级' : nextLevel.experience_eligible === false ? '还差 ' + remainingText + ' 经验' : '成长资格核验中' }}</text><text v-if="percentage !== null">{{ percentage }}%</text></view>
        </template>
        <view v-else class="coop-experience__hint"><text>{{ maxed ? '已达当前最高等级，经验继续累计' : '暂无下一等级，经验继续累计' }}</text></view>
    </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import type { CoopRow } from '../../api/cooperation'
import { experienceText, membershipLevelColor } from '../../utils/entity-membership'
const props = withDefaults(defineProps<{ experience: CoopRow; nextLevel?: CoopRow | null; maxed?: boolean; color?: string }>(), { nextLevel: null, maxed: false, color: '' })
const valueText = computed(() => experienceText(props.experience?.value))
const hasSources = computed(() => experienceText(props.experience?.earned_value) !== '—' && experienceText(props.experience?.purchased_value) !== '—')
const completedText = computed(() => Number.isSafeInteger(props.experience?.completed_orders) && props.experience.completed_orders >= 0 ? `已完成付佣 ${props.experience.completed_orders} 单` : '付佣记录待核对')
const hasTarget = computed(() => experienceText(props.nextLevel?.experience_required) !== '—')
const targetText = computed(() => experienceText(props.nextLevel?.experience_required))
const remainingText = computed(() => experienceText(props.nextLevel?.experience_remaining))
const tone = computed(() => membershipLevelColor({ level_color: props.nextLevel?.level_color || props.color }))
const percentage = computed(() => { const value = props.nextLevel?.progress_percent; return typeof value === 'number' && Number.isFinite(value) ? Math.max(0, Math.min(100, Math.round(value * 10) / 10)) : null })
</script>
<style scoped>
.coop-experience { margin-top:16px;padding-top:14px;border-top:1px solid #ededed; }
.coop-experience__heading,.coop-experience__target,.coop-experience__hint { display:flex;align-items:center;justify-content:space-between;gap:12px;line-height:20px; }
.coop-experience__heading { font-size:13px;font-weight:600;color:#333; }
.coop-experience__heading>text:last-child { font-size:12px;font-weight:400;color:#888; }
.coop-experience__value { display:flex;align-items:baseline;gap:6px;margin:5px 0 12px; }
.coop-experience__value>text:first-child { font-size:27px;font-weight:700;line-height:34px;color:var(--experience-color,var(--primary-color,#ff4444)); }
.coop-experience__value>text:last-child { font-size:12px;color:#777; }
.coop-experience__sources { display:flex;flex-wrap:wrap;gap:5px 16px;color:#888;font-size:11px;line-height:18px;margin:-6px 0 12px; }
.coop-experience__target { font-size:12px;color:#555;margin-bottom:8px; }
.coop-experience__target>text:first-child { flex:1;min-width:0;overflow-wrap:break-word; }
.coop-experience__target>text:last-child { flex:none;white-space:nowrap; }
.coop-experience__hint { font-size:12px;color:#777;margin-top:8px; }
.coop-experience__hint>text:first-child { min-width:0;flex:1; }
.coop-experience__hint>text:last-child:not(:first-child) { flex:none;white-space:nowrap; }
</style>
