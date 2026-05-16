<template>
  <div class="edit-recycle-spider-quotation-list">
    <div class="content-wrap" v-show="diyStore.editTab == 'content'">
      <div class="edit-attr-item-wrap preview-guide">
        <div class="guide-title">装修预览说明</div>
        <div class="guide-text">
          装修时组件使用模拟分类和报价数据，不会请求真实接口；保存到页面后，前端会按报价源、分类、热门和显示数量读取真实报价。
        </div>
      </div>

      <div class="edit-attr-item-wrap">
        <h3 class="mb-[10px]">基础内容</h3>
        <el-form label-width="90px" class="px-[10px]">
          <el-form-item label="显示头部">
            <el-switch v-model="diyStore.editComponent.showHeader" />
            <div class="form-tip">关闭后标题、副标题和刷新按钮都会隐藏。</div>
          </el-form-item>
          <el-form-item v-if="diyStore.editComponent.showHeader" label="标题">
            <el-input v-model="diyStore.editComponent.title" placeholder="请输入标题" clearable maxlength="20" show-word-limit />
          </el-form-item>
          <el-form-item v-if="diyStore.editComponent.showHeader" label="副标题">
            <el-input v-model="diyStore.editComponent.subtitle" placeholder="请输入副标题" clearable maxlength="30" show-word-limit />
          </el-form-item>
        </el-form>
      </div>

      <div class="edit-attr-item-wrap">
        <h3 class="mb-[10px]">数据筛选</h3>
        <el-form label-width="90px" class="px-[10px]">
          <el-form-item label="报价源">
            <el-select v-model="diyStore.editComponent.sourceId" clearable filterable placeholder="全部报价源" class="w-full">
              <el-option :value="0" label="全部报价源" />
              <el-option v-for="item in sourceList" :key="item.id" :value="item.id" :label="item.source_name" />
            </el-select>
            <div class="form-tip">装修预览不切换真实报价源，前台访问时才按这里读取真实数据。</div>
          </el-form-item>
          <el-form-item label="显示数量">
            <el-input-number v-model="diyStore.editComponent.limit" :min="0" />
            <div class="form-tip">填 0 表示不限制，前台会读取符合条件的全部报价项。</div>
          </el-form-item>
          <el-form-item label="只看热门">
            <el-switch v-model="diyStore.editComponent.onlyHot" />
          </el-form-item>
        </el-form>
      </div>

      <div class="edit-attr-item-wrap">
        <h3 class="mb-[10px]">交互方式</h3>
        <el-form label-width="90px" class="px-[10px]">
          <el-form-item label="分类切换">
            <el-switch v-model="diyStore.editComponent.showCategoryTabs" />
            <div class="form-tip">开启后用户先选一级分类，再选该一级下的二级分类；关闭后直接展示报价列表。</div>
          </el-form-item>
          <el-form-item v-if="diyStore.editComponent.showCategoryTabs" label="分类层级">
            <div class="form-tip static-tip">前端会自动按一级分类、二级分类分两行展示，避免不同层级混在同一排。</div>
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
            <el-form-item label="Tab吸顶">
              <el-switch v-model="diyStore.editComponent.stickyTabs" />
              <div class="form-tip">开启后，用户向上滚动到该组件位置时，分类 Tab 会停留在顶部，方便连续筛选报价。</div>
            </el-form-item>
            <template v-if="diyStore.editComponent.stickyTabs">
              <el-form-item label="顶部避让">
                <el-radio-group v-model="diyStore.editComponent.stickyTabsOffsetMode">
                  <el-radio label="auto">自动</el-radio>
                  <el-radio label="top">贴顶</el-radio>
                  <el-radio label="manual">手动</el-radio>
                </el-radio-group>
                <div class="form-tip">自动会按小程序顶部胶囊估算；贴顶固定为 0px；如果页面用了自定义头部，建议切到手动。</div>
              </el-form-item>
              <el-form-item v-if="diyStore.editComponent.stickyTabsOffsetMode !== 'top'" :label="diyStore.editComponent.stickyTabsOffsetMode === 'manual' ? '顶部距离' : '额外距离'">
                <el-slider v-model="diyStore.editComponent.stickyTabsOffset" show-input size="small" class="ml-[10px]" :min="0" :max="260" />
                <div class="form-tip">{{ diyStore.editComponent.stickyTabsOffsetMode === 'manual' ? 'Tab 固定时距离屏幕顶部的距离。' : '在自动避让基础上额外增加的距离。' }}</div>
              </el-form-item>
            </template>
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
          <el-form-item v-if="diyStore.editComponent.showHeader" label="刷新按钮">
            <el-switch v-model="diyStore.editComponent.showRefresh" />
          </el-form-item>
          <el-form-item label="展示样式">
            <el-radio-group v-model="diyStore.editComponent.displayStyle">
              <el-radio label="list">列表</el-radio>
              <el-radio label="graphic">图文导航</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item v-if="diyStore.editComponent.displayStyle === 'list'" label="按钮文字">
            <el-input v-model="diyStore.editComponent.actionText" placeholder="请输入按钮文字" clearable maxlength="8" show-word-limit />
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
      <div class="edit-attr-item-wrap" v-if="diyStore.editComponent.showHeader || diyStore.editComponent.displayStyle === 'list'">
        <h3 class="mb-[10px]">颜色设置</h3>
        <el-form label-width="90px" class="px-[10px]">
          <el-form-item v-if="diyStore.editComponent.showHeader" label="标题颜色">
            <el-color-picker v-model="diyStore.editComponent.titleColor" show-alpha :predefine="diyStore.predefineColors" />
          </el-form-item>
          <el-form-item v-if="diyStore.editComponent.showHeader" label="副标题">
            <el-color-picker v-model="diyStore.editComponent.subtitleColor" show-alpha :predefine="diyStore.predefineColors" />
          </el-form-item>
          <el-form-item v-if="diyStore.editComponent.displayStyle === 'list'" label="按钮颜色">
            <el-color-picker v-model="diyStore.editComponent.buttonColor" show-alpha :predefine="diyStore.predefineColors" />
          </el-form-item>
        </el-form>
      </div>

      <div class="edit-attr-item-wrap">
        <h3 class="mb-[10px]">分类样式</h3>
        <el-form label-width="90px" class="px-[10px]">
          <el-form-item label="分组标题">
            <el-switch v-model="diyStore.editComponent.showGroupTitle" />
            <div class="form-tip">关闭后整个分组头部隐藏，标题和右侧数量都会消失。</div>
          </el-form-item>
          <el-form-item label="数量颜色">
            <el-color-picker v-model="diyStore.editComponent.groupCountColor" show-alpha :predefine="diyStore.predefineColors" />
          </el-form-item>
          <template v-if="diyStore.editComponent.showGroupTitle !== false">
            <el-form-item label="标题颜色">
              <el-color-picker v-model="diyStore.editComponent.groupTitleColor" show-alpha :predefine="diyStore.predefineColors" />
            </el-form-item>
            <el-form-item label="标题背景">
              <el-color-picker v-model="diyStore.editComponent.groupTitleBgColor" show-alpha :predefine="diyStore.predefineColors" />
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
            <el-form-item label="标题圆角">
              <el-slider v-model="diyStore.editComponent.groupTitleRadius" show-input size="small" class="ml-[10px]" :min="0" :max="48" />
            </el-form-item>
            <el-form-item label="左右留白">
              <el-slider v-model="diyStore.editComponent.groupTitlePaddingX" show-input size="small" class="ml-[10px]" :min="0" :max="48" />
            </el-form-item>
            <el-form-item label="上下留白">
              <el-slider v-model="diyStore.editComponent.groupTitlePaddingY" show-input size="small" class="ml-[10px]" :min="0" :max="32" />
            </el-form-item>
          </template>
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
          <el-form-item label="二级样式">
            <el-switch v-model="diyStore.editComponent.secondaryTabCustom" />
            <div class="form-tip">关闭时二级分类 Tab 完全跟随上方样式；打开后可以单独设计二级分类的样式。</div>
          </el-form-item>
          <template v-if="diyStore.editComponent.secondaryTabCustom">
            <el-form-item label="二级样式">
              <el-radio-group v-model="diyStore.editComponent.secondaryTabStyleType">
                <el-radio label="pill">胶囊</el-radio>
                <el-radio label="card">卡片</el-radio>
                <el-radio label="underline">下划线</el-radio>
              </el-radio-group>
            </el-form-item>
            <el-form-item label="二级主题色">
              <el-color-picker v-model="diyStore.editComponent.secondaryTabThemeColor" show-alpha :predefine="diyStore.predefineColors" />
            </el-form-item>
            <el-form-item label="二级选中背景">
              <el-color-picker v-model="diyStore.editComponent.secondaryTabActiveBgColor" show-alpha :predefine="diyStore.predefineColors" />
            </el-form-item>
            <el-form-item label="二级未选背景">
              <el-color-picker v-model="diyStore.editComponent.secondaryTabInactiveBgColor" show-alpha :predefine="diyStore.predefineColors" />
            </el-form-item>
            <el-form-item label="二级选中文字">
              <el-color-picker v-model="diyStore.editComponent.secondaryTabActiveTextColor" show-alpha :predefine="diyStore.predefineColors" />
            </el-form-item>
            <el-form-item label="二级未选文字">
              <el-color-picker v-model="diyStore.editComponent.secondaryTabInactiveTextColor" show-alpha :predefine="diyStore.predefineColors" />
            </el-form-item>
            <el-form-item label="二级高度">
              <el-slider v-model="diyStore.editComponent.secondaryTabHeight" show-input size="small" class="ml-[10px]" :min="44" :max="96" />
            </el-form-item>
            <el-form-item label="二级圆角">
              <el-slider v-model="diyStore.editComponent.secondaryTabRadius" show-input size="small" class="ml-[10px]" :min="0" :max="48" />
            </el-form-item>
            <el-form-item label="二级字号">
              <el-slider v-model="diyStore.editComponent.secondaryTabFontSize" show-input size="small" class="ml-[10px]" :min="20" :max="34" />
            </el-form-item>
            <el-form-item label="二级粗细">
              <el-radio-group v-model="diyStore.editComponent.secondaryTabFontWeight">
                <el-radio :label="400">常规</el-radio>
                <el-radio :label="500">中等</el-radio>
                <el-radio :label="600">半粗</el-radio>
                <el-radio :label="700">加粗</el-radio>
              </el-radio-group>
            </el-form-item>
            <el-form-item label="二级留白">
              <el-slider v-model="diyStore.editComponent.secondaryTabSidePadding" show-input size="small" class="ml-[10px]" :min="8" :max="40" />
            </el-form-item>
          </template>
        </el-form>
      </div>

      <div class="edit-attr-item-wrap">
        <h3 class="mb-[10px]">报价项样式</h3>
        <el-form label-width="90px" class="px-[10px]">
          <el-form-item label="热门标识">
            <el-switch v-model="diyStore.editComponent.showHotBadge" />
            <div class="form-tip">报价项开启热门后显示，列表、图文导航和详情页都会使用这个标识。</div>
          </el-form-item>
          <template v-if="diyStore.editComponent.showHotBadge !== false">
            <el-form-item label="热门图标">
              <upload-image v-model="diyStore.editComponent.hotBadgeImage" :limit="1" tips="不上传时使用默认热门样式，建议透明 PNG" />
            </el-form-item>
            <el-form-item label="图标大小">
              <el-slider v-model="diyStore.editComponent.hotBadgeSize" show-input size="small" class="ml-[10px]" :min="24" :max="80" />
            </el-form-item>
          </template>
          <el-form-item label="文字颜色">
            <el-color-picker v-model="diyStore.editComponent.itemTitleColor" show-alpha :predefine="diyStore.predefineColors" />
          </el-form-item>
          <el-form-item label="文字字号">
            <el-slider v-model="diyStore.editComponent.itemTitleSize" show-input size="small" class="ml-[10px]" :min="20" :max="36" />
          </el-form-item>
          <el-form-item v-if="diyStore.editComponent.displayStyle === 'list'" label="辅助文字">
            <el-color-picker v-model="diyStore.editComponent.itemMetaColor" show-alpha :predefine="diyStore.predefineColors" />
          </el-form-item>
          <el-form-item v-if="diyStore.editComponent.displayStyle === 'graphic'" label="图片圆角">
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
  if (diyStore.editComponent.limit === undefined || diyStore.editComponent.limit === null || diyStore.editComponent.limit === '') diyStore.editComponent.limit = 6
  if (diyStore.editComponent.onlyHot === undefined) diyStore.editComponent.onlyHot = false
  if (diyStore.editComponent.showCategoryTabs === undefined) diyStore.editComponent.showCategoryTabs = true
  if (!diyStore.editComponent.categoryDefaultMode || diyStore.editComponent.categoryDefaultMode === 'all') diyStore.editComponent.categoryDefaultMode = 'first'
  if (!diyStore.editComponent.tabStyleType) diyStore.editComponent.tabStyleType = 'pill'
  if (diyStore.editComponent.showTabScrollCue === undefined) diyStore.editComponent.showTabScrollCue = true
  if (diyStore.editComponent.stickyTabs === undefined) diyStore.editComponent.stickyTabs = false
  if (!diyStore.editComponent.stickyTabsOffsetMode) diyStore.editComponent.stickyTabsOffsetMode = 'auto'
  if (diyStore.editComponent.stickyTabsOffset === undefined) diyStore.editComponent.stickyTabsOffset = 0
  if (!diyStore.editComponent.tabThemeColor) diyStore.editComponent.tabThemeColor = diyStore.editComponent.buttonColor || '#2563EB'
  if (diyStore.editComponent.tabActiveBgColor === undefined) diyStore.editComponent.tabActiveBgColor = ''
  if (diyStore.editComponent.tabInactiveBgColor === undefined) diyStore.editComponent.tabInactiveBgColor = ''
  if (diyStore.editComponent.tabActiveTextColor === undefined) diyStore.editComponent.tabActiveTextColor = ''
  if (!diyStore.editComponent.tabInactiveTextColor) diyStore.editComponent.tabInactiveTextColor = '#475569'
  if (!diyStore.editComponent.tabHeight) diyStore.editComponent.tabHeight = 64
  if (diyStore.editComponent.tabRadius === undefined) diyStore.editComponent.tabRadius = 32
  if (!diyStore.editComponent.tabFontSize) diyStore.editComponent.tabFontSize = 26
  if (!diyStore.editComponent.tabFontWeight) diyStore.editComponent.tabFontWeight = 600
  if (!diyStore.editComponent.tabSidePadding) diyStore.editComponent.tabSidePadding = 18
  if (diyStore.editComponent.secondaryTabCustom === undefined) diyStore.editComponent.secondaryTabCustom = false
  if (!diyStore.editComponent.secondaryTabStyleType) diyStore.editComponent.secondaryTabStyleType = diyStore.editComponent.tabStyleType || 'pill'
  if (!diyStore.editComponent.secondaryTabThemeColor) diyStore.editComponent.secondaryTabThemeColor = diyStore.editComponent.tabThemeColor || diyStore.editComponent.buttonColor || '#2563EB'
  if (diyStore.editComponent.secondaryTabActiveBgColor === undefined) diyStore.editComponent.secondaryTabActiveBgColor = ''
  if (diyStore.editComponent.secondaryTabInactiveBgColor === undefined) diyStore.editComponent.secondaryTabInactiveBgColor = ''
  if (diyStore.editComponent.secondaryTabActiveTextColor === undefined) diyStore.editComponent.secondaryTabActiveTextColor = ''
  if (!diyStore.editComponent.secondaryTabInactiveTextColor) diyStore.editComponent.secondaryTabInactiveTextColor = '#475569'
  if (!diyStore.editComponent.secondaryTabHeight) diyStore.editComponent.secondaryTabHeight = diyStore.editComponent.tabHeight || 64
  if (diyStore.editComponent.secondaryTabRadius === undefined) diyStore.editComponent.secondaryTabRadius = diyStore.editComponent.tabRadius ?? 32
  if (!diyStore.editComponent.secondaryTabFontSize) diyStore.editComponent.secondaryTabFontSize = diyStore.editComponent.tabFontSize || 26
  if (!diyStore.editComponent.secondaryTabFontWeight) diyStore.editComponent.secondaryTabFontWeight = diyStore.editComponent.tabFontWeight || 600
  if (!diyStore.editComponent.secondaryTabSidePadding) diyStore.editComponent.secondaryTabSidePadding = diyStore.editComponent.tabSidePadding || 18
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
  if (diyStore.editComponent.showGroupTitle === undefined) diyStore.editComponent.showGroupTitle = true
  if (!diyStore.editComponent.groupTitleColor) diyStore.editComponent.groupTitleColor = '#111827'
  if (diyStore.editComponent.groupTitleBgColor === undefined) diyStore.editComponent.groupTitleBgColor = 'transparent'
  if (!diyStore.editComponent.groupCountColor) diyStore.editComponent.groupCountColor = '#94A3B8'
  if (!diyStore.editComponent.groupTitleSize) diyStore.editComponent.groupTitleSize = 22
  if (!diyStore.editComponent.groupTitleWeight) diyStore.editComponent.groupTitleWeight = 500
  if (!diyStore.editComponent.groupTitleAlign) diyStore.editComponent.groupTitleAlign = 'left'
  if (diyStore.editComponent.groupTitleRadius === undefined) diyStore.editComponent.groupTitleRadius = 0
  if (diyStore.editComponent.groupTitlePaddingX === undefined) diyStore.editComponent.groupTitlePaddingX = 0
  if (diyStore.editComponent.groupTitlePaddingY === undefined) diyStore.editComponent.groupTitlePaddingY = 0
  if (diyStore.editComponent.showHotBadge === undefined) diyStore.editComponent.showHotBadge = true
  if (diyStore.editComponent.hotBadgeImage === undefined) diyStore.editComponent.hotBadgeImage = ''
  if (!diyStore.editComponent.hotBadgeSize) diyStore.editComponent.hotBadgeSize = 38
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

  .preview-guide {
    border: 1px solid #dbeafe;
    background: #eff6ff;
    box-shadow: none;

    .guide-title {
      color: #1d4ed8;
      font-size: 14px;
      font-weight: 600;
      margin-bottom: 6px;
    }

    .guide-text {
      color: #475569;
      font-size: 12px;
      line-height: 1.6;
    }
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
