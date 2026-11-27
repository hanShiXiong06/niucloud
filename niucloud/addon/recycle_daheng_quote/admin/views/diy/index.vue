<template>
  <div class="w-full">
    <component :is="diyEditComponent" v-if="diyStore.currentComponent != 'edit-page'">
      <template #style></template>
    </component>
  </div>
</template>

<script lang="ts" setup>
import { computed, markRaw } from 'vue'
import useDiyStore from '@/stores/modules/diy'
import EditRecycleQuotationList from './components/edit-recycle-quotation-list.vue'

const diyStore = useDiyStore()

const componentMap: Record<string, any> = {
  EditRecycleQuotationList
}

const diyEditComponent = computed(() => {
  const component = componentMap[diyStore.currentComponent] || null
  return component ? markRaw(component) : null
})

const dahengQuoteComponents = [
  {
    type: 'recycle_daheng_quote',
    name: 'DH速收报价',
    components: [
      {
        name: 'RecycleQuotationList',
        title: 'DH报价单',
        icon: 'iconfont iconshangpinliebiaopc',
        componentTitle: 'DH报价单',
        componentName: 'RecycleQuotationList',
        componentType: 'EditRecycleQuotationList',
        isDelete: false,
        isDisabled: false,
        allPages: true,
        defaultDataList: {},
        extra: {},
        marginTop: 5,
        paddingTop: 10,
        paddingBottom: 10,
        marginBottom: 5,
        value: {
          title: '今日报价',
          subtitle: '实时同步回收报价单',
          actionText: '查看',
          limit: 5,
          showRefresh: true,
          displayStyle: 'list',
          navRowCount: 4,
          navImageSize: 40,
          navAroundRadius: 20,
          componentStartBgColor: '',
          componentEndBgColor: '',
          componentGradientAngle: 'to bottom',
          componentBgUrl: '',
          componentBgAlpha: 0,
          topRounded: 0,
          bottomRounded: 0,
          titleColor: '#111827',
          subtitleColor: '#6B7280',
          buttonColor: '#2563EB',
          margin: {
            top: 10,
            bottom: 10,
            both: 12
          }
        }
      }
    ]
  }
]

if (diyStore.components) {
  diyStore.components.push(...dahengQuoteComponents)
}
</script>
