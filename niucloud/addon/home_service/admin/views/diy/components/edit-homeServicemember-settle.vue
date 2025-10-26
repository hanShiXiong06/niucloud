<template>
    <!-- 内容 -->
    <div class="content-wrap" v-show="diyStore.editTab == 'content'">
        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">{{ t('titleContent') }}</h3>
            <el-form label-width="80px" class="px-[10px]">
                <el-form-item :label="t('lefttitle')">
                    <el-input v-model.trim="diyStore.editComponent.leftText" :placeholder="t('titlePlaceholder')" clearable maxlength="6" show-word-limit/>
                </el-form-item>
            </el-form>
			<el-form label-width="80px" class="px-[10px]">
			    <el-form-item :label="t('leftDesc')">
			        <el-input v-model.trim="diyStore.editComponent.leftDesc" :placeholder="t('descPlaceholder')" clearable maxlength="10" show-word-limit/>
			    </el-form-item>
			</el-form>
			<el-form label-width="80px" class="px-[10px]">
			    <el-form-item :label="t('righttitle')">
			        <el-input v-model.trim="diyStore.editComponent.rightText" :placeholder="t('titlePlaceholder')" clearable maxlength="6" show-word-limit/>
			    </el-form-item>
			</el-form>
			<el-form label-width="80px" class="px-[10px]">
			    <el-form-item :label="t('rightDesc')">
			        <el-input v-model.trim="diyStore.editComponent.rightDesc" :placeholder="t('descPlaceholder')" clearable maxlength="10" show-word-limit/>
			    </el-form-item>
			</el-form>
        </div>
    </div>

    <!-- 样式 -->
    <div class="style-wrap" v-show="diyStore.editTab == 'style'">
        <!-- 组件样式 -->
        <slot name="style"></slot>

    </div>
</template>

<script lang="ts" setup>
import { t } from '@/lang'
import { ref } from 'vue'
import useDiyStore from '@/stores/modules/diy'

const diyStore = useDiyStore()
diyStore.editComponent.ignore = ['componentBgUrl'] // 忽略公共属性

defineExpose({})

</script>

<style lang="scss" scoped></style>
