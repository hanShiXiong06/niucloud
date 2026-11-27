<script lang="ts">
export default { name: 'HsxCard' }
</script>

<script setup lang="ts">

withDefaults(
    defineProps<{
        title?: string
        subtitle?: string
        status?: string
        padding?: string
        clickable?: boolean
    }>(),
    {
        title: '',
        subtitle: '',
        status: '',
        padding: 'var(--hsx-mobile-card-padding, 14px)',
        clickable: false
    }
)

const emit = defineEmits<{ (event: 'click'): void }>()
</script>

<template>
    <view
        class="hsx-card"
        :class="{ 'hsx-card--clickable': clickable }"
        :style="{ padding }"
        @click="emit('click')"
    >
        <view v-if="title || subtitle || status || $slots.header" class="hsx-card__header">
            <slot name="header">
                <view class="hsx-card__heading">
                    <text v-if="title" class="hsx-card__title">{{ title }}</text>
                    <text v-if="subtitle" class="hsx-card__subtitle">{{ subtitle }}</text>
                </view>
                <text v-if="status" class="hsx-card__status">{{ status }}</text>
            </slot>
        </view>
        <view class="hsx-card__body"><slot /></view>
        <view v-if="$slots.footer" class="hsx-card__footer"><slot name="footer" /></view>
    </view>
</template>

<style scoped lang="scss">
.hsx-card {
    box-sizing: border-box;
    border-radius: 14px;
    border: 1px solid var(--hsx-mobile-border, #e6ebf2);
    background: var(--hsx-mobile-bg-surface, #fff);
    box-shadow: 0 7px 18px rgba(22, 42, 83, .08);
}

.hsx-card--clickable:active {
    background: var(--hsx-mobile-bg-muted, #f7f8fa);
}

.hsx-card__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}

.hsx-card__heading {
    display: flex;
    min-width: 0;
    flex: 1;
    flex-direction: column;
}

.hsx-card__title {
    overflow: hidden;
    color: var(--hsx-mobile-text-primary, #202124);
    font-size: var(--hsx-mobile-font-subtitle, 15px);
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hsx-card__subtitle {
    margin-top: 4px;
    color: var(--hsx-mobile-text-secondary, #8a8f99);
    font-size: var(--hsx-mobile-font-caption, 12px);
}

.hsx-card__status {
    color: var(--hsx-mobile-price, #ff5a36);
    font-size: var(--hsx-mobile-font-body, 14px);
}

.hsx-card__footer {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--hsx-mobile-border, #f1f2f4);
}
</style>
