<template>
    <view class="coop-lead-card" hover-class="is-pressed" :hover-stay-time="60" @tap="$emit('open', lead.id)">
        <view class="coop-lead-card__head">
            <view class="coop-lead-card__customer"
                >{{ lead.customer_name || '客户需求'
                }}<text
                    class="coop-business-tag"
                    >{{ lead.business_name || businessLabels[lead.business_type] || '业务需求' }}</text
                >
            </view>
            <text
                class="coop-status"
                :class="['is-' + lead.status, 'tone-' + statusTone]"
                >{{ lead.status_label || coopStatusLabels[lead.status] || '待核对' }}</text
            >
        </view>
        <CoopModels :items="lead.demands || lead.models || []" />
        <view class="coop-lead-card__meta">
            <u-icon name="map" size="14" color="currentColor" />
            <text>{{ lead.region_text || '地区未填写' }}</text>
        </view>
        <view v-if="counterparty?.name" class="coop-lead-card__meta">
            <u-icon name="home" size="14" color="currentColor" />
            <text>{{ direction === 'incoming' ? '来自' : '交付给' }} {{ counterparty.name }}</text>
        </view>
        <view v-if="assigneeName" class="coop-lead-card__meta">
            <u-icon name="account" size="14" color="currentColor" />
            <text>接收成员 · {{ assigneeName }}</text>
        </view>
        <CoopResponseDeadline :lead="lead" :active="active" :direction="direction" compact />
        <view class="coop-lead-card__foot">
            <text class="coop-lead-card__time">{{ coopDate(lead.update_time || lead.create_time) }}</text>
            <button
                v-if="lead.can_cancel"
                class="coop-card-cancel"
                hover-stop-propagation
                @tap.stop="$emit('cancel', lead.id)"
                >取消线索</button
            >
            <button
                class="coop-card-action"
                :class="{ primary: primaryAction }"
                hover-class="is-pressed"
                hover-stop-propagation
                @tap.stop="$emit(lead.can_assign ? 'select' : 'open', lead.id)"
                >{{ lead.can_assign ? '选择门店' : lead.can_accept ? '查看并接单' : lead.can_report ? '反馈结果' : lead.can_pay ? '去付佣' : '查看详情' }}</button
            >
        </view>
    </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { businessLabels, coopDate, coopStatusLabels, type CoopRow, type Direction } from '../../api/cooperation'
import CoopModels from './CoopModels.vue'
import CoopResponseDeadline from './CoopResponseDeadline.vue'
const props = withDefaults(defineProps<{ lead: CoopRow; direction: Direction; active?: boolean }>(), { active: true })
defineEmits<{ (event: 'open' | 'select' | 'cancel', id: number): void }>()
const counterparty = computed(() => props.direction === 'incoming' ? props.lead.publisher : props.lead.receiver)
const assigneeName = computed(() => props.lead.is_internal ? String(props.lead.internal_assignee?.name || props.lead.internal_assignee_name || '') : '')
const primaryAction = computed(() => !!(props.lead.can_assign || props.lead.can_accept || props.lead.can_report || props.lead.can_pay))
// Status colour by meaning (spec §4): waiting on someone = warning, in progress = accent, done = success.
const STATUS_TONES: Record<string, string> = { selecting: 'warning', assigned: 'warning', unpaid: 'warning', following: 'accent', paid: 'success' }
const statusTone = computed(() => STATUS_TONES[String(props.lead.status)] || 'neutral')
</script>
<style scoped lang="scss">
@import '../../styles/tokens.scss';

.coop-lead-card {
    @include hsx-card;
    margin-bottom: $hsx-space-3;
    transition: transform $hsx-duration-fast $hsx-ease-out;
    &.is-pressed { transform: scale(.99); }
}
.coop-lead-card__head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: $hsx-space-3;
    margin-bottom: $hsx-space-3;
}
.coop-lead-card__customer {
    min-width: 0;
    font-size: $hsx-font-title-sm;
    font-weight: $hsx-weight-bold;
    line-height: $hsx-line-height;
    color: $hsx-text-primary;
    word-break: break-word;
}
.coop-business-tag {
    display: inline-block;
    margin-left: $hsx-space-2;
    vertical-align: 1px;
    @include hsx-status-tag(neutral);
    font-weight: $hsx-weight-regular;
}
.coop-status {
    flex: none;
    &.tone-warning { @include hsx-status-tag(warning); }
    &.tone-accent { @include hsx-status-tag(accent); }
    &.tone-success { @include hsx-status-tag(success); }
    &.tone-neutral { @include hsx-status-tag(neutral); }
}
.coop-lead-card__meta {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: $hsx-space-2;
    font-size: $hsx-font-body-sm;
    line-height: $hsx-line-height;
    color: $hsx-text-secondary;
    > text { min-width: 0; @include hsx-ellipsis; }
}
.coop-lead-card__foot {
    display: flex;
    align-items: center;
    gap: $hsx-space-2;
    margin-top: $hsx-space-3;
    padding-top: $hsx-space-3;
    border-top: 1px solid $hsx-border;
}
.coop-lead-card__time {
    flex: 1;
    min-width: 0;
    font-size: $hsx-font-caption;
    color: $hsx-text-muted;
    @include hsx-number;
}
.coop-card-cancel {
    @include hsx-button-reset;
    flex: none;
    min-height: $hsx-tap-min;
    padding: 0 $hsx-space-2;
    line-height: $hsx-tap-min;
    font-size: $hsx-font-body-sm;
    color: $hsx-text-secondary;
    white-space: nowrap;
}
.coop-card-action {
    @include hsx-button-secondary;
    @include hsx-tap-target;
    flex: none;
    min-height: $hsx-control-h-sm;
    line-height: $hsx-control-h-sm;
    font-size: $hsx-font-body-sm;
    transition: transform $hsx-duration-fast $hsx-ease-out;
    &.is-pressed { transform: scale(.97); }
    &.primary {
        border-color: transparent;
        background: $hsx-accent;
        color: $hsx-text-on-fill;
        font-weight: $hsx-weight-medium;
    }
}
</style>
