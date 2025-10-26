<template>
    <!-- 内容 -->
    <div class="content-wrap" v-show="diyStore.editTab == 'content'">
        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">{{t('activeCubeBlockContent')}}</h3>
            <el-form label-width="80px" class="px-[10px]">
                <el-form-item :label="t('左侧图片')">
                    <upload-image v-model="diyStore.editComponent.bgUrl" :limit="1"/>
                </el-form-item>
            </el-form>
			<el-form label-width="80px" class="px-[10px]">
			    <el-form-item :label="t('文字简介')">
			        <el-input v-model.trim="diyStore.editComponent.desc" :placeholder="t('请输入文字')" clearable maxlength="14" show-word-limit/>
			    </el-form-item>
			</el-form>
			<el-form label-width="80px" class="px-[10px]">
			    <el-form-item :label="t('右侧标题')">
			        <el-input v-model.trim="diyStore.editComponent.title" :placeholder="t('请输入文字')" clearable maxlength="4" show-word-limit/>
			    </el-form-item>
			</el-form>
        </div>
    </div>

    <!-- 样式 -->
    <div class="style-wrap" v-show="diyStore.editTab == 'style'">
        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">{{ t('组件样式') }}</h3>
            <el-form label-width="100px" class="px-[10px]">
                <el-form-item :label="t('textColor')">
                    <el-color-picker v-model="diyStore.editComponent.textColor"/>
                </el-form-item>
            </el-form>
			<el-form label-width="100px" class="px-[10px]">
			    <el-form-item :label="t('标题颜色')">
			        <el-color-picker v-model="diyStore.editComponent.titleTextColor"/>
			    </el-form-item>
			</el-form>
			<el-form label-width="100px" class="px-[10px]">
			    <el-form-item :label="t('标题背景色')">
			        <el-color-picker v-model="diyStore.editComponent.titleBgColor"/>
			    </el-form-item>
			</el-form>
        </div>

        <!-- 组件样式 -->
        <slot name="style"></slot>
    </div>
</template>

<script lang="ts" setup>
import { t } from '@/lang'
import useDiyStore from '@/stores/modules/diy'
console.log(1212)
const diyStore = useDiyStore()
diyStore.editComponent.ignore = ['componentBgUrl'] // 忽略公共属性

defineExpose({})

</script>

<style lang="scss" scoped></style>
