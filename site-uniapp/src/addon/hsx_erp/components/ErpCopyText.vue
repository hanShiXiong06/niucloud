<template>
    <view class="erp-copy-text" :class="{ 'erp-copy-text--block': block }" @click.stop="open">
        <text class="erp-copy-text__value">{{ value || emptyText }}</text>
        <text v-if="value" class="erp-copy-text__action">查看</text>
    </view>
    <u-popup :show="visible" mode="center" border-radius="24rpx" @close="visible = false">
        <view class="copy-popup">
            <text class="copy-popup__title">{{ title }}</text>
            <text class="copy-popup__value" selectable>{{ value }}</text>
            <view class="copy-popup__actions">
                <u-button @click="visible = false">关闭</u-button>
                <u-button type="primary" @click="copy">一键复制</u-button>
            </view>
        </view>
    </u-popup>
</template>

<script setup lang="ts">
import { ref } from 'vue'
const props = withDefaults(defineProps<{ value?: string | number; title?: string; emptyText?: string; block?: boolean }>(), {
    value: '', title: '完整内容', emptyText: '-', block: false,
})
const visible = ref(false)
function open() { if (String(props.value || '')) visible.value = true }
function copy() {
    uni.setClipboardData({ data: String(props.value || ''), success: () => uni.showToast({ title: '已复制', icon: 'success' }) })
}
</script>

<style scoped lang="scss">
.erp-copy-text { min-width:0; display:inline-flex; align-items:center; gap:8rpx; vertical-align:middle; }
.erp-copy-text--block { width:100%; }
.erp-copy-text__value { min-width:0; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:inherit; }
.erp-copy-text__action { flex:none; color:#3b6ef5; font-size:20rpx; }
.copy-popup { width:620rpx; max-width:86vw; padding:30rpx; box-sizing:border-box; background:#fff; }
.copy-popup__title,.copy-popup__value { display:block; }
.copy-popup__title { color:#0f172a; font-size:30rpx; font-weight:700; }
.copy-popup__value { margin-top:20rpx; padding:20rpx; border-radius:12rpx; background:#f8fafc; color:#334155; font-size:25rpx; line-height:1.55; word-break:break-all; }
.copy-popup__actions { display:grid; grid-template-columns:1fr 1.5fr; gap:16rpx; margin-top:24rpx; }
</style>
