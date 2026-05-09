<template>
  <view :style="themeColor()">
    <loading-page :loading="diy.getLoading()"></loading-page>

    <view v-show="!diy.getLoading()">
      <view class="diy-template-wrap bg-index" :style="diy.pageStyle()">
        <diy-group ref="diyGroupRef" :data="diy.data" />
      </view>
    </view>

    <!-- #ifdef MP-WEIXIN -->
    <wx-privacy-popup ref="wxPrivacyPopupRef"></wx-privacy-popup>
    <!-- #endif -->
  </view>
</template>

<script setup lang="ts">
import { ref, nextTick } from 'vue'
import { useDiy } from '@/hooks/useDiy'
import diyGroup from '@/addon/components/diy/group/index.vue'

const diy = useDiy({
  name: 'DIY_RECYCLE_MEMBER_INDEX'
})

const diyGroupRef = ref<InstanceType<typeof diyGroup> | null>(null)
const wxPrivacyPopupRef: any = ref(null)

diy.onLoad()

diy.onShow(() => {
  diyGroupRef.value?.refresh()
  // #ifdef MP
  nextTick(() => {
    if (wxPrivacyPopupRef.value) wxPrivacyPopupRef.value.proactive()
  })
  // #endif
})

diy.onHide()
diy.onUnload()
diy.onPageScroll()
</script>

<style lang="scss" scoped>
@import '@/styles/diy.scss';
</style>

<style lang="scss">
.diy-template-wrap {
  /* #ifdef MP */
  .child-diy-template-wrap {
    ::v-deep .diy-group {
      > .draggable-element.top-fixed-diy {
        display: block !important;
      }
    }
  }

  /* #endif */
}
</style>
