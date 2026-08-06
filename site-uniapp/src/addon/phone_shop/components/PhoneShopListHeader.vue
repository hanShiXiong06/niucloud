<template>
    <view class="list-head" :class="{ 'list-head--mp': isMp }">
        <!-- #ifdef MP -->
        <view class="custom-nav" :style="customNavStyle">
            <view class="nav-back" @tap="handleNavAction">
                <u-icon :name="canGoBack ? 'arrow-left' : 'home'" color="#172033" size="21" />
            </view>
            <view class="search-box search-box--compact">
                <u-icon name="search" color="#9098a3" size="17" />
                <input
                    class="search-input"
                    :value="modelValue"
                    :placeholder="placeholder"
                    placeholder-class="header-placeholder"
                    confirm-type="search"
                    @input="onInput"
                    @confirm="emit('search')"
                />
                <u-icon v-if="modelValue" name="close-circle-fill" color="#c4c8cf" size="17" @click="clear" />
                <view v-if="showScan" class="nav-action nav-action--scan" @click="emit('scan')">
                    <u-icon name="scan" color="var(--primary-color)" size="19" />
                </view>
            </view>
        </view>
        <!-- #endif -->

        <!-- #ifndef MP -->
        <view class="search-box">
            <u-icon name="search" color="#9098a3" size="18" />
            <input
                class="search-input"
                :value="modelValue"
                :placeholder="placeholder"
                placeholder-class="header-placeholder"
                confirm-type="search"
                @input="onInput"
                @confirm="emit('search')"
            />
            <u-icon v-if="modelValue" name="close-circle-fill" color="#c4c8cf" size="18" @click="clear" />
            <view v-if="showScan" class="nav-action nav-action--scan" @click="emit('scan')">
                <u-icon name="scan" color="var(--primary-color)" size="20" />
            </view>
        </view>
        <!-- #endif -->

        <view class="filter-row">
            <scroll-view scroll-x class="tab-scroll" :show-scrollbar="false">
                <view class="tab-list">
                    <view
                        v-for="tab in tabs"
                        :key="String(tab.value)"
                        class="tab-item"
                        :class="{ 'tab-item--active': active === tab.value }"
                        @click="emit('change', tab.value)"
                    >
                        {{ tab.label }}
                    </view>
                </view>
            </scroll-view>
            <view v-if="showRefresh" class="refresh-action" @click="emit('refresh')">
                <u-icon name="reload" color="#64748b" size="17" />
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
    modelValue: string
    placeholder?: string
    tabs: Array<{ label: string, value: any }>
    active: any
    isMp: boolean
    customNavStyle?: string
    canGoBack?: boolean
    showScan?: boolean
    showRefresh?: boolean
    handleNavAction: () => void
}>(), {
    placeholder: '搜索关键词',
    customNavStyle: '',
    canGoBack: true,
    showScan: false,
    showRefresh: true,
})

const emit = defineEmits(['update:modelValue', 'search', 'clear', 'scan', 'refresh', 'change'])

function onInput(event: any) {
    emit('update:modelValue', String(event?.detail?.value || ''))
}

function clear() {
    emit('update:modelValue', '')
    emit('clear')
}
</script>

<style scoped lang="scss">
.list-head { position: fixed; z-index: 100; top: 0; right: 0; left: 0; padding: 20rpx 24rpx 14rpx; background: var(--page-bg-color); box-sizing: border-box; }
.list-head--mp { padding: 0 18rpx 12rpx; border-bottom: 1rpx solid #eef2f7; background: #fff; box-shadow: 0 4rpx 14rpx rgba(15, 23, 42, .04); }
.custom-nav { width: 100%; display: flex; align-items: center; gap: 8rpx; box-sizing: border-box; }
.nav-back { width: 56rpx; height: 56rpx; flex-shrink: 0; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.nav-back:active { background: #f2f4f7; }
.search-box { height: 72rpx; padding: 0 22rpx; border-radius: 36rpx; background: #fff; display: flex; align-items: center; gap: 12rpx; box-sizing: border-box; }
.search-box--compact { height: 60rpx; min-width: 0; flex: 1; padding: 0 12rpx 0 16rpx; border-radius: 30rpx; background: #f5f7fa; }
.search-input { min-width: 0; flex: 1; color: #334155; font-size: 24rpx; }
.nav-action { position: relative; width: 44rpx; height: 44rpx; flex-shrink: 0; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.nav-action--scan { margin-left: 4rpx; background: #eff3ff; }
.filter-row { height: 70rpx; display: flex; align-items: center; gap: 12rpx; }
.list-head--mp .filter-row { height: 76rpx; padding: 0 6rpx; }
.tab-scroll { min-width: 0; flex: 1; white-space: nowrap; }
.tab-list { display: inline-flex; align-items: center; gap: 12rpx; padding-right: 12rpx; }
.tab-item { height: 52rpx; padding: 0 25rpx; border: 2rpx solid transparent; border-radius: 27rpx; background: #fff; color: #64748b; display: flex; align-items: center; justify-content: center; font-size: 24rpx; box-sizing: border-box; }
.list-head--mp .tab-item { background: #f6f7f9; }
.tab-item--active { border-color: var(--primary-color); background: var(--primary-color-light, #e6fff5); color: var(--primary-color); font-weight: 600; }
.refresh-action { width: 54rpx; height: 54rpx; flex-shrink: 0; border-radius: 50%; background: #f6f7f9; display: flex; align-items: center; justify-content: center; }
</style>
