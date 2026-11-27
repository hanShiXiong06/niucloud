<template>
  <PremiumTheme class="w-full">
    <component :is="diyEditComponent" v-if="diyEditComponent">
      <template #style>
        <!-- 样式设置区域 -->
      </template>
    </component>
  </PremiumTheme>
</template>

<script lang="ts" setup>
import PremiumTheme from '@/addon/hsx_recycle/components/PremiumTheme.vue'
import { computed, markRaw, watch } from 'vue';
import useDiyStore from '@/stores/modules/diy';
import EditPage from '@/app/views/diy/components/edit-page.vue';
import EditRecycleCategory from './components/edit-recycle-category.vue';
import EditRecycleOrderOverview from './components/edit-recycle-order-overview.vue';
import EditRecycleSendButton from './components/edit-recycle-send-button.vue';

const diyStore = useDiyStore();

const isPlainObject = (value: any) => value && typeof value == 'object' && !Array.isArray(value);

const cloneDefault = (value: any): any => {
  if (!isPlainObject(value)) return value;

  const data: any = {};
  Object.keys(value).forEach((key) => {
    data[key] = cloneDefault(value[key]);
  });
  return data;
};

const fillDefaults = (target: any, defaults: any) => {
  Object.keys(defaults).forEach((key) => {
    if (target[key] === undefined || target[key] === null) {
      target[key] = cloneDefault(defaults[key]);
      return;
    }

    if (isPlainObject(target[key]) && isPlainObject(defaults[key])) {
      fillDefaults(target[key], defaults[key]);
    }
  });
};

const recyclePageGlobalDefaults = {
  topStatusBar: {
    control: true,
    isShow: true,
    bgColor: '#ffffff',
    rollBgColor: '#ffffff',
    style: 'style-1',
    styleName: '风格1',
    textColor: '#333333',
    rollTextColor: '#333333',
    textAlign: 'center',
    inputPlaceholder: '请输入搜索关键词',
    imgUrl: '',
    link: { name: '' }
  },
  bottomTabBar: {
    control: true,
    isShow: true,
    designNav: {
      title: '',
      key: 'hsx_recycle'
    }
  },
  copyright: {
    control: true,
    isShow: false,
    textColor: '#ccc'
  },
  popWindow: {
    imgUrl: '',
    imgWidth: '',
    imgHeight: '',
    count: 'once',
    show: 0,
    link: { name: '' }
  },
  template: {
    margin: {
      top: 0,
      bottom: 0,
      both: 0
    }
  }
};

const ensureRecyclePageGlobal = () => {
  if (diyStore.currentComponent !== 'edit-page') return;

  diyStore.global = diyStore.global || {};
  fillDefaults(diyStore.global, recyclePageGlobalDefaults);
};

watch(
  () => [diyStore.currentComponent, diyStore.global],
  () => ensureRecyclePageGlobal(),
  { immediate: true }
);

// 编辑组件映射
const componentMap: Record<string, any> = {
  'edit-page': EditPage,
  'edit-recycle-category': EditRecycleCategory,
  'edit-recycle-order-overview': EditRecycleOrderOverview,
  'edit-recycle-send-button': EditRecycleSendButton,
  EditRecycleCategory,
  EditRecycleOrderOverview,
  EditRecycleSendButton
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
        name: 'RecycleSendButton',
        title: '去发货',
        icon: 'iconfont iconfenlei',
        componentTitle: '去发货',
        componentName: 'RecycleSendButton',
        componentType: 'EditRecycleSendButton',
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
          title: '去发货',
          buttonText: '去发货',
          buttonColor: '#FFFFFF',
          buttonBgColor: '#FF6B00',
          buttonBgUrl: '',
          buttonBgAlpha: 0,
          buttonBgSize: '100% 100%',
          buttonBgRadius: 8,
          buttonBgMargin: {
            top: 10,
            bottom: 10,
            both: 10
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
