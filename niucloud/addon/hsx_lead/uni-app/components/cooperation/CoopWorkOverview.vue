<template>
    <view class="coop-work-overview">
        <button
            v-for="tile in tiles"
            :key="tile.status + tile.stat"
            class="coop-work-overview__tile"
            :class="['is-' + tile.tone, { 'is-active': activeStatus === tile.status }]"
            hover-class="is-pressed"
            :aria-label="tile.label + ' ' + count(tile.stat)"
            :disabled="disabled"
            @tap="emit('focus', direction, tile.status)"
        >
            <view class="coop-work-overview__icon"><u-icon :name="tile.icon" size="20" color="currentColor" /></view>
            <view class="coop-work-overview__text">
                <text class="coop-work-overview__count">{{ count(tile.stat) }}</text>
                <text class="coop-work-overview__label">{{ tile.label }}</text>
            </view>
        </button>
    </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'
// Work summary above the role list. Tapping a tile filters the list by that status.
type Direction = 'outgoing' | 'incoming'
type Tile = { status: string; stat: string; icon: string; label: string; tone: 'accent' | 'success' | 'warning' }
const props = withDefaults(
    defineProps<{ direction: Direction; stats: Record<string, any>; activeStatus?: string; disabled?: boolean }>(),
    { activeStatus: '', disabled: false }
)
const emit = defineEmits<{ (event: 'focus', direction: Direction, status: string): void }>()
const TILES: Record<Direction, Tile[]> = {
    outgoing: [
        { status: 'selecting', stat: 'selecting', icon: 'home', label: '待选门店', tone: 'warning' },
        { status: '', stat: 'outgoing', icon: 'order', label: '发出线索进度', tone: 'success' }
    ],
    incoming: [
        { status: 'assigned', stat: 'assigned', icon: 'order', label: '等待接单', tone: 'accent' },
        { status: 'following', stat: 'following', icon: 'chat', label: '跟进客户', tone: 'success' },
        { status: 'unpaid', stat: 'unpaid', icon: 'order', label: '成交待付佣', tone: 'warning' }
    ]
}
const tiles = computed(() => TILES[props.direction])
function count(key: string) {
    const value = props.stats?.[key]
    return value === undefined || value === null || value === '' ? '—' : String(value)
}
</script>
<style scoped lang="scss">
@import '../../styles/tokens.scss';

.coop-work-overview {
    display: flex;
    gap: $hsx-space-2;
    padding: $hsx-space-3 $hsx-space-3 $hsx-space-2;
}
.coop-work-overview__tile {
    @include hsx-button-reset;
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: $hsx-space-2;
    min-height: 60px;
    padding: $hsx-space-2 $hsx-space-3;
    border: 1px solid transparent;
    border-radius: $hsx-radius-lg;
    background: $hsx-surface-card;
    text-align: left;
    transition: border-color $hsx-duration-fast $hsx-ease-out, transform $hsx-duration-fast $hsx-ease-out;
    &.is-pressed { transform: scale(.98); }
    &[disabled] { background: $hsx-surface-card; color: inherit; }
}
.coop-work-overview__icon {
    flex: none;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: $hsx-radius-md;
}
.coop-work-overview__tile.is-accent .coop-work-overview__icon { background: $hsx-accent-soft; color: $hsx-accent-text; }
.coop-work-overview__tile.is-success .coop-work-overview__icon { background: $hsx-success-soft; color: $hsx-success-text; }
.coop-work-overview__tile.is-warning .coop-work-overview__icon { background: $hsx-warning-soft; color: $hsx-warning-text; }
.coop-work-overview__tile.is-active { border-color: $hsx-accent; }
.coop-work-overview__text {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.coop-work-overview__count {
    @include hsx-number;
    font-size: $hsx-font-title;
    font-weight: $hsx-weight-bold;
    line-height: $hsx-line-height-tight;
    color: $hsx-text-primary;
}
.coop-work-overview__label {
    @include hsx-ellipsis;
    font-size: $hsx-font-caption;
    line-height: $hsx-line-height-tight;
    color: $hsx-text-secondary;
}
</style>
