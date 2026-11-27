<template>
    <view class="capability-summary">
        <text class="capability-summary__label">可交付型号</text>
        <button class="capability-summary__row" :disabled="disabled" aria-label="管理交付型号" @tap="manage">
            <view class="capability-summary__content">
                <view class="capability-summary__total" :class="{ 'is-empty': !summary.count }"
                    ><template v-if="summary.count"
                        >已选 <text>{{ summary.count }}</text> 款型号</template
                    ><template v-else>尚未选择</template></view
                >
                <text class="capability-summary__detail">{{ summary.summaryText }}</text>
            </view>
            <view class="capability-summary__action"
                ><text>{{ summary.count ? '管理' : '选择' }}</text
                ><u-icon name="arrow-right" size="13" color="var(--primary-color, #3478cc)"
            /></view>
        </button>
    </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { summarizeCapabilities } from '../../utils/capability-summary'
import { interactionFeedback } from '../../utils/interaction-feedback'
const props = withDefaults(defineProps<{ models: any[]; disabled?: boolean }>(), { disabled: false })
const emit = defineEmits<{ (event: 'manage'): void }>()
const summary = computed(() => summarizeCapabilities(props.models))
function manage() { if (!props.disabled) { interactionFeedback('navigate'); emit('manage') } }
</script>
<style scoped lang="scss">
.capability-summary { margin: 28rpx 0; }
.capability-summary__label { display: block; font-size: 26rpx; line-height: 1.6; color: #5b6c83; margin-bottom: 16rpx; }
.capability-summary__row { display: flex; align-items: center; justify-content: space-between; gap: 20rpx; width: 100%; min-height: 128rpx; box-sizing: border-box; margin: 0; padding: 20rpx 24rpx; text-align: left; background: #f4f7fc; border: 1rpx solid #e4ebf5; border-radius: 12rpx; font-family: inherit; }
.capability-summary__row::after { border: 0; }
.capability-summary__row[disabled] { opacity: .5; background: #f4f7fc; }
.capability-summary__content { flex: 1; min-width: 0; }
.capability-summary__total { font-size: 27rpx; line-height: 1.65; color: #435978; font-weight: 500; }
.capability-summary__total>text { font-size: 31rpx; font-weight: 650; color: #293f60; }
.capability-summary__total.is-empty { color: #7e8da3; }
.capability-summary__detail { display: block; margin-top: 5rpx; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; color: #8493a9; font-size: 23rpx; line-height: 1.65; }
.capability-summary__action { flex: none; display: flex; align-items: center; gap: 8rpx; font-size: 24rpx; color: var(--primary-color, #3478cc); }
@media(max-width:340px) { .capability-summary__row { padding-left: 20rpx; padding-right: 20rpx; gap: 14rpx; } }
</style>
