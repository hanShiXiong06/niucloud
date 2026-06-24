<template>
    <div class="content-wrap" v-show="diyStore.editTab == 'content'">
        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">校园树洞</h3>
            <el-form label-width="100px" class="px-[10px]">
                <el-form-item label="标题">
                    <el-input v-model="diyStore.editComponent.title" placeholder="区块标题" maxlength="20" />
                </el-form-item>
                <el-form-item label="显示更多">
                    <el-switch v-model="diyStore.editComponent.showMore" />
                </el-form-item>
                <el-form-item label="更多文案">
                    <el-input v-model="diyStore.editComponent.moreText" placeholder="查看更多" maxlength="10" />
                </el-form-item>
                <el-form-item label="更多链接">
                    <diy-link v-model="diyStore.editComponent.moreUrl" />
                </el-form-item>
                <el-form-item label="条数">
                    <el-input-number v-model="diyStore.editComponent.num" :min="1" :max="30" />
                </el-form-item>
                <el-form-item label="分类ID">
                    <el-input-number v-model="diyStore.editComponent.category_id" :min="0" />
                    <div class="text-gray-400 text-xs mt-1">0 表示全部分类</div>
                </el-form-item>
                <el-form-item label="排序">
                    <el-radio-group v-model="diyStore.editComponent.sort">
                        <el-radio label="new">最新</el-radio>
                        <el-radio label="hot">最热</el-radio>
                    </el-radio-group>
                </el-form-item>
            </el-form>
        </div>
    </div>
    <div class="style-wrap" v-show="diyStore.editTab == 'style'">
        <slot name="style"></slot>
    </div>
</template>

<script lang="ts" setup>
import useDiyStore from '@/stores/modules/diy'
const diyStore = useDiyStore()
diyStore.editComponent.ignore = []
defineExpose({})
</script>

<style lang="scss" scoped></style>
