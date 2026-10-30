<template>
    <view class="mall-category-page" :style="pageStyle">
        <ErpPageHeader title="选择商城分类" />
        <view class="mall-category-page__body">
            <ErpMallCategorySelect
                standalone
                :model-value="selectedId"
                @change="choose"
                @close="back"
                @keyboard-height="keyboardHeight = $event"
            />
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, getCurrentInstance, ref } from 'vue'
import { onLoad, onResize } from '@dcloudio/uni-app'
import ErpPageHeader from '@/addon/hsx_erp/components/ErpPageHeader.vue'
import ErpMallCategorySelect from '@/addon/hsx_erp/components/ErpMallCategorySelect.vue'
import type { MallCategoryOption } from '@/addon/hsx_erp/hooks/mallCategoryPicker'

const instance = getCurrentInstance()
const selectedId = ref(0)
const keyboardHeight = ref(0)
const windowInfo = typeof uni.getWindowInfo === 'function' ? uni.getWindowInfo() : uni.getSystemInfoSync()
const viewportHeight = ref(Number(windowInfo.windowHeight) || 640)
const visibleHeight = ref(viewportHeight.value)
let viewportWidth = Number(windowInfo.windowWidth) || 0
// 原生先 resize 再报键盘高度时，不能对已经缩小的窗口再减一次键盘。
const pageStyle = computed(() => ({ height: `${Math.max(0, Math.min(visibleHeight.value, viewportHeight.value - keyboardHeight.value))}px` }))
let leaving = false

onLoad((query: any) => {
    const id = Number(query?.selected_id)
    selectedId.value = Number.isSafeInteger(id) && id > 0 ? id : 0
})
onResize((event: any) => {
    const height = Number(event?.size?.windowHeight)
    const width = Number(event?.size?.windowWidth)
    if (!(height > 0)) return
    visibleHeight.value = height
    if (width > 0 && width !== viewportWidth) {
        viewportWidth = width
        viewportHeight.value = height + keyboardHeight.value
    } else if (height > viewportHeight.value) viewportHeight.value = height
})

function back() {
    if (leaving) return
    leaving = true
    uni.hideKeyboard()
    uni.navigateBack({ delta: 1, fail: () => { leaving = false; uni.reLaunch({ url: '/addon/hsx_erp/pages/stock/list' }) } })
}
function choose(option: MallCategoryOption) {
    if (leaving) return
    const channel = (instance?.proxy as any)?.getOpenerEventChannel?.()
    if (!channel || typeof channel.emit !== 'function') {
        uni.showToast({ title: '未找到原上架表单，请返回设备后重新选择', icon: 'none' })
        return
    }
    channel.emit('mallCategorySelected', option)
    back()
}
</script>

<style scoped>
.mall-category-page { display:flex; flex-direction:column; width:100%; min-width:0; overflow:hidden; background:#fff; box-sizing:border-box; }
.mall-category-page__body { flex:1; min-height:0; min-width:0; padding-bottom:env(safe-area-inset-bottom); box-sizing:border-box; overflow:hidden; }
</style>
