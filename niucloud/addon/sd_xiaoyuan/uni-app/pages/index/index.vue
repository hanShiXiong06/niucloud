<template>
    <view>
        <loading-page :loading="diy.getLoading()"></loading-page>

        <view v-show="!diy.getLoading()">
            <view class="diy-template-wrap bg-index" :style="diy.pageStyle()">
                <diy-group ref="diyGroupRef" :data="diy.data" />
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useDiy } from '@/hooks/useDiy'
import { useShare } from '@/hooks/useShare'
import diyGroup from '@/addon/components/diy/group/index.vue'

const { setShare } = useShare()

const diy = useDiy({ name: 'DIY_SD_XIAOYUAN_INDEX' })

const diyGroupRef: any = ref(null)

diy.onLoad()

diy.onShow((data: any) => {
    let share = data.share ? JSON.parse(data.share) : null
    setShare(share)
    if (diyGroupRef.value) diyGroupRef.value.refresh()
})

diy.onHide()
diy.onUnload()
diy.onPageScroll()
</script>

<style lang="scss" scoped>
@import '@/styles/diy.scss';
</style>
