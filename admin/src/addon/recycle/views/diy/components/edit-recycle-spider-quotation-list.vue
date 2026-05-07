<template>
  <div class="edit-recycle-spider-quotation-list">
    <div class="content-wrap" v-show="diyStore.editTab == 'content'">
      <div class="edit-attr-item-wrap">
        <h3 class="mb-[10px]">内容设置</h3>
        <el-form label-width="90px" class="px-[10px]">
          <el-form-item label="标题">
            <el-input v-model="diyStore.editComponent.title" placeholder="请输入标题" clearable maxlength="20" show-word-limit />
          </el-form-item>
          <el-form-item label="副标题">
            <el-input v-model="diyStore.editComponent.subtitle" placeholder="请输入副标题" clearable maxlength="30" show-word-limit />
          </el-form-item>
          <el-form-item label="显示头部">
            <el-switch v-model="diyStore.editComponent.showHeader" />
          </el-form-item>
          <el-form-item label="按钮文字">
            <el-input v-model="diyStore.editComponent.actionText" placeholder="请输入按钮文字" clearable maxlength="8" show-word-limit />
          </el-form-item>
          <el-form-item label="报价源">
            <el-select v-model="diyStore.editComponent.sourceId" clearable filterable placeholder="全部报价源" class="w-full">
              <el-option :value="0" label="全部报价源" />
              <el-option v-for="item in sourceList" :key="item.id" :value="item.id" :label="item.source_name" />
            </el-select>
          </el-form-item>
          <el-form-item label="显示数量">
            <el-input-number v-model="diyStore.editComponent.limit" :min="0" :max="100" />
            <div class="form-tip">填 0 表示不限制，最多 100 条。</div>
          </el-form-item>
          <el-form-item label="只看热门">
            <el-switch v-model="diyStore.editComponent.onlyHot" />
          </el-form-item>
          <el-form-item label="分类切换">
            <el-switch v-model="diyStore.editComponent.showCategoryTabs" />
          </el-form-item>
          <el-form-item v-if="diyStore.editComponent.showCategoryTabs" label="Tab层级">
            <el-radio-group v-model="diyStore.editComponent.categoryTabDepth">
              <el-radio :label="1">一级</el-radio>
              <el-radio :label="2">二级</el-radio>
              <el-radio :label="0">全部</el-radio>
            </el-radio-group>
          </el-form-item>
          <template v-if="diyStore.editComponent.showCategoryTabs">
            <el-form-item label="Tab样式">
              <el-radio-group v-model="diyStore.editComponent.tabStyleType">
                <el-radio label="pill">胶囊</el-radio>
                <el-radio label="card">卡片</el-radio>
                <el-radio label="underline">下划线</el-radio>
              </el-radio-group>
              <div class="form-tip">胶囊和卡片更像可点击按钮，适合首页报价导航。</div>
            </el-form-item>
            <el-form-item label="滑动提示">
              <el-switch v-model="diyStore.editComponent.showTabScrollCue" />
              <div class="form-tip">分类较多时右侧显示渐隐箭头，提示用户可以横向滑动。</div>
            </el-form-item>
          </template>
          <el-form-item label="列表分组">
            <el-radio-group v-model="diyStore.editComponent.flatGroupMode">
              <el-radio label="none">不分组</el-radio>
              <el-radio label="level1">一级分类</el-radio>
              <el-radio label="level2">二级分类</el-radio>
              <el-radio label="path">完整路径</el-radio>
            </el-radio-group>
            <div class="form-tip">Tab 开启时，当前 Tab 内的数据也会按这里的规则继续分组。</div>
          </el-form-item>
          <el-form-item v-if="diyStore.editComponent.flatGroupMode !== 'none'" label="分组数量">
            <el-switch v-model="diyStore.editComponent.showGroupCount" />
          </el-form-item>
          <el-form-item label="刷新按钮">
            <el-switch v-model="diyStore.editComponent.showRefresh" />
          </el-form-item>
          <el-form-item label="展示样式">
            <el-radio-group v-model="diyStore.editComponent.displayStyle">
              <el-radio label="list">列表</el-radio>
              <el-radio label="graphic">图文导航</el-radio>
            </el-radio-group>
          </el-form-item>
          <template v-if="diyStore.editComponent.displayStyle === 'graphic'">
            <el-form-item label="每行数量">
              <el-radio-group v-model="diyStore.editComponent.navRowCount">
                <el-radio :label="3">3个</el-radio>
                <el-radio :label="4">4个</el-radio>
                <el-radio :label="5">5个</el-radio>
              </el-radio-group>
            </el-form-item>
          </template>
        </el-form>
      </div>

      <div class="edit-attr-item-wrap">
        <h3 class="mb-[10px]">数据说明</h3>
        <div class="help-text">
          这里展示的是报价爬虫插件同步到本地的报价项。前台只读取已显示的报价源、分类、报价项和行价格。
        </div>
      </div>
    </div>

    <div class="style-wrap" v-show="diyStore.editTab == 'style'">
      <div class="edit-attr-item-wrap">
        <h3 class="mb-[10px]">颜色设置</h3>
        <el-form label-width="90px" class="px-[10px]">
          <el-form-item label="标题颜色">
            <el-color-picker v-model="diyStore.editComponent.titleColor" show-alpha :predefine="diyStore.predefineColors" />
          </el-form-item>
          <el-form-item label="副标题">
            <el-color-picker v-model="diyStore.editComponent.subtitleColor" show-alpha :predefine="diyStore.predefineColors" />
          </el-form-item>
          <el-form-item label="按钮颜色">
            <el-color-picker v-model="diyStore.editComponent.buttonColor" show-alpha :predefine="diyStore.predefineColors" />
          </el-form-item>
        </el-form>
      </div>

      <div class="edit-attr-item-wrap">
        <h3 class="mb-[10px]">分类样式</h3>
        <el-form label-width="90px" class="px-[10px]">
          <el-form-item label="标题颜色">
            <el-color-picker v-model="diyStore.editComponent.groupTitleColor" show-alpha :predefine="diyStore.predefineColors" />
          </el-form-item>
          <el-form-item label="数量颜色">
            <el-color-picker v-model="diyStore.editComponent.groupCountColor" show-alpha :predefine="diyStore.predefineColors" />
          </el-form-item>
          <el-form-item label="标题字号">
            <el-slider v-model="diyStore.editComponent.groupTitleSize" show-input size="small" class="ml-[10px]" :min="16" :max="40" />
          </el-form-item>
          <el-form-item label="标题粗细">
            <el-radio-group v-model="diyStore.editComponent.groupTitleWeight">
              <el-radio :label="400">常规</el-radio>
              <el-radio :label="500">中等</el-radio>
              <el-radio :label="600">半粗</el-radio>
              <el-radio :label="700">加粗</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="标题对齐">
            <el-radio-group v-model="diyStore.editComponent.groupTitleAlign">
              <el-radio label="left">居左</el-radio>
              <el-radio label="center">居中</el-radio>
            </el-radio-group>
          </el-form-item>
        </el-form>
      </div>

      <div class="edit-attr-item-wrap" v-if="diyStore.editComponent.showCategoryTabs">
        <h3 class="mb-[10px]">Tab样式</h3>
        <el-form label-width="90px" class="px-[10px]">
          <el-form-item label="主题色">
            <el-color-picker v-model="diyStore.editComponent.tabThemeColor" show-alpha :predefine="diyStore.predefineColors" />
          </el-form-item>
          <el-form-item label="选中背景">
            <el-color-picker v-model="diyStore.editComponent.tabActiveBgColor" show-alpha :predefine="diyStore.predefineColors" />
            <div class="form-tip">不填时使用主题色或组件默认样式。</div>
          </el-form-item>
          <el-form-item label="未选背景">
            <el-color-picker v-model="diyStore.editComponent.tabInactiveBgColor" show-alpha :predefine="diyStore.predefineColors" />
          </el-form-item>
          <el-form-item label="选中文字">
            <el-color-picker v-model="diyStore.editComponent.tabActiveTextColor" show-alpha :predefine="diyStore.predefineColors" />
          </el-form-item>
          <el-form-item label="未选文字">
            <el-color-picker v-model="diyStore.editComponent.tabInactiveTextColor" show-alpha :predefine="diyStore.predefineColors" />
          </el-form-item>
          <el-form-item label="边框颜色">
            <el-color-picker v-model="diyStore.editComponent.tabBorderColor" show-alpha :predefine="diyStore.predefineColors" />
          </el-form-item>
          <el-form-item label="Tab高度">
            <el-slider v-model="diyStore.editComponent.tabHeight" show-input size="small" class="ml-[10px]" :min="44" :max="96" />
          </el-form-item>
          <el-form-item label="圆角">
            <el-slider v-model="diyStore.editComponent.tabRadius" show-input size="small" class="ml-[10px]" :min="0" :max="48" />
          </el-form-item>
          <el-form-item label="文字字号">
            <el-slider v-model="diyStore.editComponent.tabFontSize" show-input size="small" class="ml-[10px]" :min="20" :max="34" />
          </el-form-item>
          <el-form-item label="文字粗细">
            <el-radio-group v-model="diyStore.editComponent.tabFontWeight">
              <el-radio :label="400">常规</el-radio>
              <el-radio :label="500">中等</el-radio>
              <el-radio :label="600">半粗</el-radio>
              <el-radio :label="700">加粗</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="左右留白">
            <el-slider v-model="diyStore.editComponent.tabSidePadding" show-input size="small" class="ml-[10px]" :min="8" :max="40" />
          </el-form-item>
        </el-form>
      </div>

      <div class="edit-attr-item-wrap">
        <h3 class="mb-[10px]">报价项样式</h3>
        <el-form label-width="90px" class="px-[10px]">
          <el-form-item label="文字颜色">
            <el-color-picker v-model="diyStore.editComponent.itemTitleColor" show-alpha :predefine="diyStore.predefineColors" />
          </el-form-item>
          <el-form-item label="文字字号">
            <el-slider v-model="diyStore.editComponent.itemTitleSize" show-input size="small" class="ml-[10px]" :min="20" :max="36" />
          </el-form-item>
          <el-form-item label="辅助文字">
            <el-color-picker v-model="diyStore.editComponent.itemMetaColor" show-alpha :predefine="diyStore.predefineColors" />
          </el-form-item>
          <el-form-item label="图片圆角">
            <el-slider v-model="diyStore.editComponent.itemImageRadius" show-input size="small" class="ml-[10px]" :min="0" :max="50" />
          </el-form-item>
        </el-form>
      </div>

      <div class="edit-attr-item-wrap" v-if="diyStore.editComponent.displayStyle === 'graphic'">
        <h3 class="mb-[10px]">图文导航</h3>
        <el-form label-width="90px" class="px-[10px]">
          <el-form-item label="图片大小">
            <el-slider v-model="diyStore.editComponent.navImageSize" show-input size="small" class="ml-[10px]" :min="24" :max="64" />
          </el-form-item>
        </el-form>
      </div>

      <slot name="style"></slot>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { onMounted, ref } from 'vue'
import useDiyStore from '@/stores/modules/diy'
import { getQuoteSourceAll } from '@/addon/recycle_quote_spider/api/quote'

const diyStore = useDiyStore()
const sourceList = ref<any[]>([])

diyStore.editComponent.verify = () => {
  return { code: true, message: '' }
}

async function loadSources() {
  try {
    const res = await getQuoteSourceAll()
    sourceList.value = Array.isArray(res?.data) ? res.data : []
  } catch (error) {
    sourceList.value = []
  }
}

onMounted(() => {
  if (!diyStore.editComponent.title) diyStore.editComponent.title = '实时报价'
  if (!diyStore.editComponent.subtitle) diyStore.editComponent.subtitle = '按数据源同步展示回收报价'
  if (diyStore.editComponent.showHeader === undefined) diyStore.editComponent.showHeader = true
  if (!diyStore.editComponent.actionText) diyStore.editComponent.actionText = '查看'
  if (diyStore.editComponent.sourceId === undefined) diyStore.editComponent.sourceId = 0
  if (!diyStore.editComponent.limit) diyStore.editComponent.limit = 6
  if (diyStore.editComponent.onlyHot === undefined) diyStore.editComponent.onlyHot = false
  if (diyStore.editComponent.showCategoryTabs === undefined) diyStore.editComponent.showCategoryTabs = true
  if (!diyStore.editComponent.categoryDefaultMode || diyStore.editComponent.categoryDefaultMode === 'all') diyStore.editComponent.categoryDefaultMode = 'first'
  if (diyStore.editComponent.categoryTabDepth === undefined) diyStore.editComponent.categoryTabDepth = 0
  if (!diyStore.editComponent.tabStyleType) diyStore.editComponent.tabStyleType = 'pill'
  if (diyStore.editComponent.showTabScrollCue === undefined) diyStore.editComponent.showTabScrollCue = true
  if (!diyStore.editComponent.tabThemeColor) diyStore.editComponent.tabThemeColor = diyStore.editComponent.buttonColor || '#2563EB'
  if (diyStore.editComponent.tabActiveBgColor === undefined) diyStore.editComponent.tabActiveBgColor = ''
  if (diyStore.editComponent.tabInactiveBgColor === undefined) diyStore.editComponent.tabInactiveBgColor = ''
  if (diyStore.editComponent.tabActiveTextColor === undefined) diyStore.editComponent.tabActiveTextColor = ''
  if (!diyStore.editComponent.tabInactiveTextColor) diyStore.editComponent.tabInactiveTextColor = '#475569'
  if (diyStore.editComponent.tabBorderColor === undefined) diyStore.editComponent.tabBorderColor = ''
  if (!diyStore.editComponent.tabHeight) diyStore.editComponent.tabHeight = 64
  if (diyStore.editComponent.tabRadius === undefined) diyStore.editComponent.tabRadius = 32
  if (!diyStore.editComponent.tabFontSize) diyStore.editComponent.tabFontSize = 26
  if (!diyStore.editComponent.tabFontWeight) diyStore.editComponent.tabFontWeight = 600
  if (!diyStore.editComponent.tabSidePadding) diyStore.editComponent.tabSidePadding = 18
  if (!diyStore.editComponent.flatGroupMode) diyStore.editComponent.flatGroupMode = 'level2'
  if (diyStore.editComponent.showGroupCount === undefined) diyStore.editComponent.showGroupCount = true
  if (diyStore.editComponent.showRefresh === undefined) diyStore.editComponent.showRefresh = true
  if (!diyStore.editComponent.displayStyle) diyStore.editComponent.displayStyle = 'list'
  if (!diyStore.editComponent.navRowCount) diyStore.editComponent.navRowCount = 4
  if (!diyStore.editComponent.navImageSize) diyStore.editComponent.navImageSize = 40
  if (diyStore.editComponent.navAroundRadius === undefined) diyStore.editComponent.navAroundRadius = 20
  if (!diyStore.editComponent.componentStartBgColor) diyStore.editComponent.componentStartBgColor = ''
  if (!diyStore.editComponent.componentEndBgColor) diyStore.editComponent.componentEndBgColor = ''
  if (!diyStore.editComponent.componentGradientAngle) diyStore.editComponent.componentGradientAngle = 'to bottom'
  if (!diyStore.editComponent.componentBgUrl) diyStore.editComponent.componentBgUrl = ''
  if (diyStore.editComponent.componentBgAlpha === undefined) diyStore.editComponent.componentBgAlpha = 0
  if (diyStore.editComponent.topRounded === undefined) diyStore.editComponent.topRounded = 0
  if (diyStore.editComponent.bottomRounded === undefined) diyStore.editComponent.bottomRounded = 0
  if (!diyStore.editComponent.titleColor) diyStore.editComponent.titleColor = '#111827'
  if (!diyStore.editComponent.subtitleColor) diyStore.editComponent.subtitleColor = '#6B7280'
  if (!diyStore.editComponent.buttonColor) diyStore.editComponent.buttonColor = '#2563EB'
  if (!diyStore.editComponent.groupTitleColor) diyStore.editComponent.groupTitleColor = '#111827'
  if (!diyStore.editComponent.groupCountColor) diyStore.editComponent.groupCountColor = '#94A3B8'
  if (!diyStore.editComponent.groupTitleSize) diyStore.editComponent.groupTitleSize = 22
  if (!diyStore.editComponent.groupTitleWeight) diyStore.editComponent.groupTitleWeight = 500
  if (!diyStore.editComponent.groupTitleAlign) diyStore.editComponent.groupTitleAlign = 'left'
  if (!diyStore.editComponent.itemTitleColor) diyStore.editComponent.itemTitleColor = '#111827'
  if (!diyStore.editComponent.itemTitleSize) diyStore.editComponent.itemTitleSize = 28
  if (!diyStore.editComponent.itemMetaColor) diyStore.editComponent.itemMetaColor = '#6B7280'
  if (diyStore.editComponent.itemImageRadius === undefined) diyStore.editComponent.itemImageRadius = 20
  if (!diyStore.editComponent.margin) {
    diyStore.editComponent.margin = {
      top: 10,
      bottom: 10,
      both: 12
    }
  }

  loadSources()
})
</script>

<style lang="scss" scoped>
.edit-recycle-spider-quotation-list {
  padding: 10px;

  .edit-attr-item-wrap {
    margin-bottom: 20px;
    padding: 10px;
    border-radius: 4px;
    background-color: #fff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  }

  .help-text {
    font-size: 13px;
    line-height: 1.6;
    color: #6b7280;
  }

  .form-tip {
    width: 100%;
    margin-top: 6px;
    color: #6b7280;
    font-size: 12px;
    line-height: 1.5;
  }
}
</style>
