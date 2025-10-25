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

const diyStore = useDiyStore();

// 编辑组件映射
const componentMap: Record<string, any> = {
  EditRecycleCategory,
  EditRecycleOrderOverview
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
        imageUrl: ''
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