<template>
  <div class="edit-recycle-category">
    <!-- 内容设置 -->
    <div class="content-wrap" v-show="diyStore.editTab == 'content'">
      <div class="edit-attr-item-wrap">
        <h3 class="mb-[10px]">布局设置</h3>
        <el-form label-width="90px" class="px-[10px]">
          <el-form-item label="数据显示">
            <el-radio-group v-model="diyStore.editComponent.dataSource">
              <el-radio label="manual">手动配置</el-radio>
              <el-radio label="api">热门推荐</el-radio>
              <el-radio label="tree">分类数据</el-radio>
            </el-radio-group>
          </el-form-item>

          <el-form-item label="显示模式">
            <el-radio-group v-model="diyStore.editComponent.displayMode">
              <el-radio label="singleSlide">单行滑动</el-radio>
              <el-radio label="multiFixed">多行固定</el-radio>
            </el-radio-group>
          </el-form-item>

          <el-form-item label="每行显示">
            <el-radio-group v-model="diyStore.editComponent.rowCount">
              <el-radio :label="3">3个</el-radio>
              <el-radio :label="4">4个</el-radio>
              <el-radio :label="5">5个</el-radio>
            </el-radio-group>
          </el-form-item>

          <!-- 树形分类数据配置选项 -->
          <el-form-item v-if="diyStore.editComponent.dataSource === 'tree'" label="显示规则">
            <p class="text-sm text-gray-600 mb-[10px]">只显示有二级分类的一级分类，点击可预览图片</p>
          </el-form-item>

          <el-form-item v-if="diyStore.editComponent.dataSource === 'tree'" label="最大数量">
            <el-slider 
              v-model="diyStore.editComponent.treeMaxCount" 
              show-input 
              size="small" 
              class="ml-[10px]" 
              :min="6" 
              :max="50"
              :step="1"
            />
            <p class="text-sm text-gray-400 mt-[5px]">限制显示的分类数量（包含一级和二级）</p>
          </el-form-item>

          <el-form-item v-if="diyStore.editComponent.dataSource === 'api'" label="数据数量">
            <el-slider 
              v-model="diyStore.editComponent.apiItemLimit" 
              show-input 
              size="small" 
              class="ml-[10px]" 
              :min="6" 
              :max="50"
              :step="1"
            />
            <p class="text-sm text-gray-400 mt-[5px]">限制显示的热门分类数量</p>
          </el-form-item>
        </el-form>
      </div>
      
      <div class="edit-attr-item-wrap" v-if="diyStore.editComponent.dataSource === 'manual'">
        <h3 class="mb-[10px]">分类列表 (手动配置)</h3>
        <el-form label-width="90px" class="px-[10px]">
          <p class="text-sm text-gray-400 mb-[10px]">最多添加50个分类，拖动调整顺序。</p>
          <div ref="categoryListRef">
            <div v-for="(item, index) in diyStore.editComponent.list" :key="item.id"
                 class="item-wrap p-[10px] pb-0 relative border border-dashed border-gray-300 mb-[16px]">
              
              <el-form-item label="分类图标">
                <upload-image v-model="item.image" :limit="1" tips="建议尺寸：100x100像素" />
              </el-form-item>

              <el-form-item label="预览图片">
                <upload-image v-model="item.images" :limit="1" tips="可选，建议尺寸：200x200像素" />
              </el-form-item>

              <el-form-item label="分类名称">
                <el-input v-model.trim="item.title" placeholder="请输入分类名称" clearable maxlength="10" show-word-limit />
              </el-form-item>

              <el-form-item label="跳转链接">
                <diy-link v-model="item.link" />
              </el-form-item>

              <div class="del absolute cursor-pointer z-[2] top-[-8px] right-[-8px]"
                   v-show="diyStore.editComponent.list.length > 1"
                   @click="diyStore.editComponent.list.splice(index, 1)">
                <icon name="element CircleCloseFilled" color="#bbb" size="20px" />
              </div>
            </div>
          </div>

          <el-button v-show="!diyStore.editComponent.list || diyStore.editComponent.list.length < 50" class="w-full" @click="addRecycleCategory">
            添加回收分类
          </el-button>
        </el-form>
      </div>
    </div>

    <!-- 样式设置 -->
    <div class="style-wrap" v-show="diyStore.editTab == 'style'">
      <div class="edit-attr-item-wrap">
        <h3 class="mb-[10px]">图片设置</h3>
        <el-form label-width="90px" class="px-[10px]">
          <el-form-item label="图片尺寸">
            <el-slider 
              v-model="diyStore.editComponent.imageSize" 
              show-input 
              size="small" 
              class="ml-[10px]" 
              :min="20" 
              :max="50"
              :step="1"
            />
          </el-form-item>
          
          <el-form-item label="图片圆角">
            <el-slider 
              v-model="diyStore.editComponent.aroundRadius" 
              show-input 
              size="small" 
              class="ml-[10px]" 
              :min="0" 
              :max="15"
              :step="1"
            />
          </el-form-item>
        </el-form>
      </div>

      <div class="edit-attr-item-wrap">
        <h3 class="mb-[10px]">文字设置</h3>
        <el-form label-width="90px" class="px-[10px]">
          <el-form-item label="字体大小">
            <el-slider 
              v-model="diyStore.editComponent.font.size" 
              show-input 
              size="small" 
              class="ml-[10px]" 
              :min="12" 
              :max="16"
              :step="1"
            />
          </el-form-item>
          
          <el-form-item label="字体粗细">
            <el-radio-group v-model="diyStore.editComponent.font.weight">
              <el-radio :label="400">常规</el-radio>
              <el-radio :label="700">粗体</el-radio>
            </el-radio-group>
          </el-form-item>
          
          <el-form-item label="字体颜色">
            <el-color-picker v-model="diyStore.editComponent.font.color" show-alpha :predefine="diyStore.predefineColors" />
          </el-form-item>
        </el-form>
      </div>

      <!-- 树形分类专用样式配置 -->
      <div class="edit-attr-item-wrap" v-if="diyStore.editComponent.dataSource === 'tree'">
        <h3 class="mb-[10px]">树形分类样式</h3>
        <el-form label-width="120px" class="px-[10px]">
          <!-- 一级分类样式 -->
          <h4 class="text-sm font-medium text-gray-700 mb-[10px]">一级分类样式</h4>
          
          <el-form-item label="是否显示图标">
            <el-switch v-model="diyStore.editComponent.treeStyle.showFirstLevelIcon" />
          </el-form-item>

          <el-form-item v-if="diyStore.editComponent.treeStyle.showFirstLevelIcon" label="一级图标尺寸">
            <el-slider 
              v-model="diyStore.editComponent.treeStyle.firstLevelImageSize" 
              show-input 
              size="small" 
              class="ml-[10px]" 
              :min="30" 
              :max="80"
              :step="2"
            />
          </el-form-item>

          <el-form-item label="一级字体大小">
            <el-slider 
              v-model="diyStore.editComponent.treeStyle.firstLevelFontSize" 
              show-input 
              size="small" 
              class="ml-[10px]" 
              :min="14" 
              :max="24"
              :step="1"
            />
          </el-form-item>

          <el-form-item label="一级字体颜色">
            <el-color-picker v-model="diyStore.editComponent.treeStyle.firstLevelFontColor" show-alpha :predefine="diyStore.predefineColors" />
          </el-form-item>

          <el-form-item label="一级字体粗细">
            <el-radio-group v-model="diyStore.editComponent.treeStyle.firstLevelFontWeight">
              <el-radio :label="400">常规</el-radio>
              <el-radio :label="500">中等</el-radio>
              <el-radio :label="700">粗体</el-radio>
            </el-radio-group>
          </el-form-item>

          <!-- 二级分类样式 -->
          <h4 class="text-sm font-medium text-gray-700 mb-[10px] mt-[20px]">二级分类样式</h4>

          <el-form-item label="二级图标尺寸">
            <el-slider 
              v-model="diyStore.editComponent.treeStyle.secondLevelImageSize" 
              show-input 
              size="small" 
              class="ml-[10px]" 
              :min="20" 
              :max="60"
              :step="2"
            />
          </el-form-item>

          <el-form-item label="二级字体大小">
            <el-slider 
              v-model="diyStore.editComponent.treeStyle.secondLevelFontSize" 
              show-input 
              size="small" 
              class="ml-[10px]" 
              :min="10" 
              :max="18"
              :step="1"
            />
          </el-form-item>

          <el-form-item label="二级字体颜色">
            <el-color-picker v-model="diyStore.editComponent.treeStyle.secondLevelFontColor" show-alpha :predefine="diyStore.predefineColors" />
          </el-form-item>

          <el-form-item label="二级透明度">
            <el-slider 
              v-model="diyStore.editComponent.treeStyle.secondLevelOpacity" 
              show-input 
              size="small" 
              class="ml-[10px]" 
              :min="0.5" 
              :max="1"
              :step="0.05"
            />
          </el-form-item>

          <!-- 布局设置 -->
          <h4 class="text-sm font-medium text-gray-700 mb-[10px] mt-[20px]">布局设置</h4>

          <el-form-item label="分组间距">
            <el-slider 
              v-model="diyStore.editComponent.treeStyle.groupMargin" 
              show-input 
              size="small" 
              class="ml-[10px]" 
              :min="10" 
              :max="40"
              :step="2"
            />
          </el-form-item>

          <el-form-item label="二级左右边距">
            <el-slider 
              v-model="diyStore.editComponent.treeStyle.secondLevelPadding" 
              show-input 
              size="small" 
              class="ml-[10px]" 
              :min="0" 
              :max="40"
              :step="2"
            />
          </el-form-item>

          <el-form-item label="显示分隔线">
            <el-switch v-model="diyStore.editComponent.treeStyle.showDivider" />
          </el-form-item>

          <el-form-item v-if="diyStore.editComponent.treeStyle.showDivider" label="分隔线颜色">
            <el-color-picker v-model="diyStore.editComponent.treeStyle.dividerColor" show-alpha :predefine="diyStore.predefineColors" />
          </el-form-item>
        </el-form>
      </div>

      <!-- 组件样式 -->
      <slot name="style"></slot>
    </div>
  </div>
</template>

<script lang="ts" setup>
// 回收分类导航组件编辑器
import { ref, reactive, watch, onMounted, nextTick } from 'vue';
import useDiyStore from '@/stores/modules/diy';
import Sortable from 'sortablejs';
import { range } from 'lodash-es'; // 如果用到 range
import { t } from '@/lang'; // 如果用到 t 函数

const diyStore = useDiyStore();
diyStore.editComponent.ignore = diyStore.editComponent.ignore || []; // 初始化ignore

// 初始化默认值
const ensureDefaultValues = () => {
  diyStore.editComponent.dataSource = diyStore.editComponent.dataSource || 'manual'; // 默认为手动配置
  diyStore.editComponent.displayMode = diyStore.editComponent.displayMode || 'singleSlide';
  diyStore.editComponent.rowCount = diyStore.editComponent.rowCount || 4;
  diyStore.editComponent.list = diyStore.editComponent.list || [];
  
  // 树形分类配置默认值
  diyStore.editComponent.treeMaxCount = diyStore.editComponent.treeMaxCount || 20; // 默认最多20个
  
  // API配置默认值
  diyStore.editComponent.apiItemLimit = diyStore.editComponent.apiItemLimit || 15; // 默认最多15个
  
  diyStore.editComponent.list.forEach((item: any) => {
    if (!item.id) item.id = diyStore.generateRandom();
    item.link = item.link || { name: '' };
  });
  
  if (!diyStore.editComponent.font) {
    diyStore.editComponent.font = {
      size: 14,
      weight: 400,
      color: '#333333'
    };
  }
  
  // 图片样式默认值
  diyStore.editComponent.imageSize = diyStore.editComponent.imageSize || 35;
  diyStore.editComponent.aroundRadius = diyStore.editComponent.aroundRadius || 5;
  
  // 树形分类样式默认值
  if (!diyStore.editComponent.treeStyle) {
    diyStore.editComponent.treeStyle = {
      // 一级分类样式
      showFirstLevelIcon: true,
      firstLevelImageSize: 50,
      firstLevelFontSize: 18,
      firstLevelFontColor: '#333333',
      firstLevelFontWeight: 500,
      
      // 二级分类样式
      secondLevelImageSize: 36,
      secondLevelFontSize: 14,
      secondLevelFontColor: '#666666',
      secondLevelOpacity: 0.85,
      
      // 布局设置
      groupMargin: 20,
      secondLevelPadding: 20,
      showDivider: true,
      dividerColor: '#f0f0f0'
    };
  }
  
  // margin等其他样式属性的默认值，如果需要的话
  // if (!diyStore.editComponent.margin) {
  //   diyStore.editComponent.margin = {
  //     top: 10,
  //     bottom: 10,
  //     both: 10
  //   };
  // }
};

// 监听组件数据变化，确保新添加的项有id
watch(() => diyStore.editComponent.list, (newList) => {
  if (newList) {
    newList.forEach((item: any) => {
      if (!item.id) item.id = diyStore.generateRandom();
      item.link = item.link || { name: '' }; // 确保新项也有默认link
    });
  }
}, { deep: true });


// 组件验证
diyStore.editComponent.verify = (index: number) => {
  const res = { code: true, message: '' };
  const component = diyStore.value[index]; // 或者直接用 diyStore.editComponent

  if (!component.list || component.list.length === 0) {
    // res.code = false; // 根据需求看是否至少要有一项
    // res.message = "请至少添加一个回收分类";
    // return res;
  }

  for (const item of component.list) {
    if (!item.title) {
      res.code = false;
      res.message = t('categoryNamePlaceholder') || "请输入分类名称"; // t函数需要定义或替换
      return res;
    }
    if (!item.image) {
      res.code = false;
      res.message = t('categoryImagePlaceholder') || "请上传分类图标";
      return res;
    }
    // 可根据需求添加对 item.images (预览图) 和 item.link 的校验
  }
  
  return res;
};

const addRecycleCategory = () => {
  if (!diyStore.editComponent.list) {
    diyStore.editComponent.list = [];
  }
  if (diyStore.editComponent.list.length < 50) {
    diyStore.editComponent.list.push({
      id: diyStore.generateRandom(),
      image: '',       // 图标
      images: '',      // 预览图
      title: '分类名称',
      link: { name: '' }, // 默认链接
      // 如果有其他像graphic-nav那样的label等属性，可以在这里初始化
    });
  }
};

const categoryListRef = ref<HTMLElement | null>(null);

onMounted(() => {
  ensureDefaultValues(); // 确保所有默认值都设置了
  nextTick(() => {
    if (categoryListRef.value && diyStore.editComponent.list) {
      Sortable.create(categoryListRef.value, {
        group: 'recycle-category-item',
        animation: 200,
        handle: '.item-wrap', // 指定可拖拽的元素
        onEnd: event => {
          if (event.oldIndex !== undefined && event.newIndex !== undefined) {
            const tempList = diyStore.editComponent.list;
            const movedItem = tempList.splice(event.oldIndex, 1)[0];
            tempList.splice(event.newIndex, 0, movedItem);
            // Sortable.js 可能会自己更新DOM，但我们操作的是数据源
            // 无需手动调用 sortable.sort
          }
        }
      });
    }
  });
});

</script>

<style lang="scss" scoped>
.edit-recycle-category {
  padding: 10px;
  
  .item-wrap {
    background-color: #f7f8fa; // 给个背景色，方便拖拽时区分
    .del {
      display: none;
    }

    &:hover {
      .del {
        display: block;
      }
    }
  }
  
  .edit-attr-item-wrap {
    margin-bottom: 20px;
    padding: 10px;
    border-radius: 4px;
    background-color: #fff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  }
}
</style> 