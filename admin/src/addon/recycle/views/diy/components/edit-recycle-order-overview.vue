<template>
  <div class="edit-recycle-order-overview">
    <!-- 内容设置 -->
    <div class="content-wrap" v-show="diyStore.editTab == 'content'">
      <div class="edit-attr-item-wrap">
        <h3 class="mb-[10px]">文本设置</h3>
        <el-form label-width="90px" class="px-[10px]">
          <el-form-item label="标题">
            <el-input 
              v-model="diyStore.editComponent.title" 
              placeholder="请输入标题" 
              clearable 
              maxlength="20" 
              show-word-limit
            />
          </el-form-item>
          
          <el-form-item label="查看全部">
            <el-input 
              v-model="diyStore.editComponent.viewAllText" 
              placeholder="请输入查看全部文本" 
              clearable 
              maxlength="10" 
              show-word-limit
            />
          </el-form-item>
          
          <el-form-item label="待质检">
            <el-input 
              v-model="diyStore.editComponent.pendingReceiptText" 
              placeholder="请输入待质检文本" 
              clearable 
              maxlength="10" 
              show-word-limit
            />
          </el-form-item>
          
          <el-form-item label="处理中">
            <el-input 
              v-model="diyStore.editComponent.processingText" 
              placeholder="请输入处理中文本" 
              clearable 
              maxlength="10" 
              show-word-limit
            />
          </el-form-item>
          
          <el-form-item label="已质检">
            <el-input 
              v-model="diyStore.editComponent.shippedText" 
              placeholder="请输入已质检文本" 
              clearable 
              maxlength="10" 
              show-word-limit
            />
          </el-form-item>
          
          <el-form-item label="待确认文本">
            <el-input 
              v-model="diyStore.editComponent.pendingConfirmText" 
              placeholder="请输入待确认文本" 
              clearable 
              maxlength="10" 
              show-word-limit
            />
          </el-form-item>
        </el-form>
      </div>
    </div>

    <!-- 样式设置 -->
    <div class="style-wrap" v-show="diyStore.editTab == 'style'">
      <div class="edit-attr-item-wrap">
        <h3 class="mb-[10px]">颜色设置</h3>
        <el-form label-width="90px" class="px-[10px]">
          <el-form-item label="标题颜色">
            <el-color-picker 
              v-model="diyStore.editComponent.titleColor" 
              show-alpha 
              :predefine="diyStore.predefineColors" 
            />
          </el-form-item>
          
          <el-form-item label="查看全部">
            <el-color-picker 
              v-model="diyStore.editComponent.viewAllColor" 
              show-alpha 
              :predefine="diyStore.predefineColors" 
            />
          </el-form-item>
          
          <el-form-item label="数字颜色">
            <el-color-picker 
              v-model="diyStore.editComponent.numberColor" 
              show-alpha 
              :predefine="diyStore.predefineColors" 
            />
          </el-form-item>
          
          <el-form-item label="标签颜色">
            <el-color-picker 
              v-model="diyStore.editComponent.labelColor" 
              show-alpha 
              :predefine="diyStore.predefineColors" 
            />
          </el-form-item>
        </el-form>
      </div>

      <div class="edit-attr-item-wrap">
        <h3 class="mb-[10px]">背景设置</h3>
        <el-form label-width="90px" class="px-[10px]">
          <el-form-item label="背景颜色">
            <div class="flex items-center">
              <el-color-picker 
                v-model="diyStore.editComponent.componentStartBgColor" 
                show-alpha 
                :predefine="diyStore.predefineColors" 
              />
              <span class="mx-2">至</span>
              <el-color-picker 
                v-model="diyStore.editComponent.componentEndBgColor" 
                show-alpha 
                :predefine="diyStore.predefineColors" 
              />
            </div>
            <div class="mt-2">
              <el-radio-group v-model="diyStore.editComponent.componentGradientAngle" size="small">
                <el-radio-button label="to right">从左到右</el-radio-button>
                <el-radio-button label="to bottom">从上到下</el-radio-button>
              </el-radio-group>
            </div>
          </el-form-item>
        </el-form>
      </div>

      <!-- 组件样式 -->
      <slot name="style"></slot>
    </div>
  </div>
</template>

<script lang="ts" setup>
// 回收订单概况组件编辑器
import { onMounted } from 'vue';
import useDiyStore from '@/stores/modules/diy';

const diyStore = useDiyStore();

// 组件验证
diyStore.editComponent.verify = (index: number) => {
  const res = { code: true, message: '' };
  
  // 这里可以添加自定义验证规则
  
  return res;
};

onMounted(() => {
  // 确保组件有默认值
  if (!diyStore.editComponent.title) {
    diyStore.editComponent.title = '发货订单';
  }
  
  if (!diyStore.editComponent.viewAllText) {
    diyStore.editComponent.viewAllText = '全部';
  }
  
  if (!diyStore.editComponent.pendingReceiptText) {
    diyStore.editComponent.pendingReceiptText = '待收货';
  }
  
  if (!diyStore.editComponent.processingText) {
    diyStore.editComponent.processingText = '处理中';
  }
  
  if (!diyStore.editComponent.shippedText) {
    diyStore.editComponent.shippedText = '已发货';
  }
  
  if (!diyStore.editComponent.pendingConfirmText) {
    diyStore.editComponent.pendingConfirmText = '待确认';
  }
  
  if (!diyStore.editComponent.titleColor) {
    diyStore.editComponent.titleColor = '#333333';
  }
  
  if (!diyStore.editComponent.viewAllColor) {
    diyStore.editComponent.viewAllColor = '#999999';
  }
  
  if (!diyStore.editComponent.numberColor) {
    diyStore.editComponent.numberColor = '#FF6B00';
  }
  
  if (!diyStore.editComponent.labelColor) {
    diyStore.editComponent.labelColor = '#666666';
  }
  
  if (!diyStore.editComponent.margin) {
    diyStore.editComponent.margin = {
      top: 10,
      bottom: 10,
      both: 10
    };
  }
});
</script>

<style lang="scss" scoped>
.edit-recycle-order-overview {
  padding: 10px;
  
  .edit-attr-item-wrap {
    margin-bottom: 20px;
    padding: 10px;
    border-radius: 4px;
    background-color: #fff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
  }
}
</style> 