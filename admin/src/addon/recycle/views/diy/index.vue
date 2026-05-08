<template>
  <div class="w-full">
    <component :is="diyEditComponent" v-if="diyStore.currentComponent != 'edit-page'">
      <template #style>
        <!-- 样式设置区域 -->
      </template>
    </component>
  </div>
</template>

<script lang="ts" setup>
import { computed, markRaw } from 'vue';
import useDiyStore from '@/stores/modules/diy';
import EditRecycleCategory from './components/edit-recycle-category.vue';
import EditRecycleOrderOverview from './components/edit-recycle-order-overview.vue';
import EditRecycleSpiderQuotationList from './components/edit-recycle-spider-quotation-list.vue';

const diyStore = useDiyStore();

// 编辑组件映射
const componentMap: Record<string, any> = {
  EditRecycleCategory,
  EditRecycleOrderOverview,
  EditRecycleSpiderQuotationList
};

const diyEditComponent = computed(() => {
  const component = componentMap[diyStore.currentComponent] || null;
  return component ? markRaw(component) : null;
});

// 组件类别数据
const recycleComponents = [
  {
    type: 'recycle',
    name: '回收组件',
    components: [
      {
        name: 'RecycleCategory',
        title: '回收分类导航',
        icon: 'iconfont iconfenlei',
        componentTitle: '回收分类导航',
        componentName: 'RecycleCategory',
        componentType: 'EditRecycleCategory',
        isDelete: false,
        isDisabled: false,
        allPages: true,
        defaultDataList: {},
        extra: {},
        marginTop: 5,
        paddingTop: 10,
        paddingBottom: 10,
        marginBottom: 5,
        bgColor: '#ffffff',
        textColor: '#333333',
        imageUrl: '',
        value: {
          dataSource: 'tree',
          displayMode: 'multiFixed',
          rowCount: 4,
          list: [],
          treeMaxCount: 20,
          apiItemLimit: 15,
          imageSize: 35,
          aroundRadius: 5,
          font: {
            size: 14,
            weight: 400,
            color: '#333333'
          },
          treeStyle: {
            showFirstLevelIcon: true,
            firstLevelImageSize: 50,
            firstLevelFontSize: 18,
            firstLevelFontColor: '#333333',
            firstLevelFontWeight: 500,
            secondLevelImageSize: 36,
            secondLevelFontSize: 14,
            secondLevelFontColor: '#666666',
            secondLevelOpacity: 0.85,
            groupMargin: 20,
            secondLevelPadding: 20,
            showDivider: true,
            dividerColor: '#f0f0f0'
          },
          margin: {
            top: 10,
            bottom: 10,
            both: 12
          }
        }
      },
      {
        name: 'RecycleOrderOverview',
        title: '订单数据概况',
        icon: 'iconfont iconshujukanban',
        componentTitle: '订单数据概况',
        componentName: 'RecycleOrderOverview',
        componentType: 'EditRecycleOrderOverview',
        isDelete: false,
        isDisabled: false,
        allPages: true,
        defaultDataList: {},
        extra: {},
        marginTop: 5,
        paddingTop: 10,
        paddingBottom: 10,
        marginBottom: 5,
        bgColor: '#ffffff',
        textColor: '#333333',
        imageUrl: ''
      },
      {
        name: 'RecycleSpiderQuotationList',
        title: '爬虫报价单',
        icon: 'iconfont iconshangpinliebiaopc',
        componentTitle: '爬虫报价单',
        componentName: 'RecycleSpiderQuotationList',
        componentType: 'EditRecycleSpiderQuotationList',
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
          title: '实时报价',
          subtitle: '按数据源同步展示回收报价',
          showHeader: true,
          actionText: '查看',
          sourceId: 0,
          limit: 6,
          onlyHot: false,
          showCategoryTabs: true,
          categoryDefaultMode: 'first',
          tabStyleType: 'pill',
          showTabScrollCue: true,
          stickyTabs: false,
          stickyTabsOffsetMode: 'auto',
          stickyTabsOffset: 0,
          tabThemeColor: '#2563EB',
          tabActiveBgColor: '',
          tabInactiveBgColor: '',
          tabActiveTextColor: '',
          tabInactiveTextColor: '#475569',
          tabHeight: 64,
          tabRadius: 32,
          tabFontSize: 26,
          tabFontWeight: 600,
          tabSidePadding: 18,
          secondaryTabCustom: false,
          secondaryTabStyleType: 'pill',
          secondaryTabThemeColor: '#2563EB',
          secondaryTabActiveBgColor: '',
          secondaryTabInactiveBgColor: '',
          secondaryTabActiveTextColor: '',
          secondaryTabInactiveTextColor: '#475569',
          secondaryTabHeight: 64,
          secondaryTabRadius: 32,
          secondaryTabFontSize: 26,
          secondaryTabFontWeight: 600,
          secondaryTabSidePadding: 18,
          flatGroupMode: 'level2',
          showGroupCount: true,
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
          groupTitleColor: '#111827',
          groupCountColor: '#94A3B8',
          groupTitleSize: 22,
          groupTitleWeight: 500,
          groupTitleAlign: 'left',
          itemTitleColor: '#111827',
          itemTitleSize: 28,
          itemMetaColor: '#6B7280',
          itemImageRadius: 20,
          margin: {
            top: 10,
            bottom: 10,
            both: 12
          }
        }
      }
    ]
  }
];

// 加入到全局组件列表中
if (diyStore.components) {
  diyStore.components.push(...recycleComponents);
}
</script>

<style lang="scss" scoped>
.diy-index {
  height: 100%;
}
</style>
