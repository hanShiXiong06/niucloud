<template>
    <div class="content-wrap" v-show="diyStore.editTab == 'content'">
        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">功能入口设置</h3>
            <el-form label-width="100px" class="px-[10px]">
                <el-form-item label="每行显示">
                    <el-radio-group v-model="diyStore.editComponent.column">
                        <el-radio :label="2">2个</el-radio>
                        <el-radio :label="3">3个</el-radio>
                    </el-radio-group>
                </el-form-item>
            </el-form>
            
            <h3 class="mb-[10px] mt-[20px]">入口配置</h3>
            <div class="banner-list">
                <div class="banner-item" v-for="(item, idx) in diyStore.editComponent.list" :key="idx">
                    <el-form label-width="80px" class="px-[10px]">
                        <el-form-item label="标题">
                            <el-input v-model="item.title" placeholder="标题" size="small" />
                        </el-form-item>
                        <el-form-item label="描述">
                            <el-input v-model="item.desc" placeholder="描述" size="small" />
                        </el-form-item>
                        <el-form-item label="图片">
                            <upload-image v-model="item.imageUrl" :limit="1" />
                        </el-form-item>
                        <el-form-item label="图标">
                            <el-input v-model="item.icon" placeholder="图标名称(可选兜底)" size="small" />
                        </el-form-item>
                        <el-form-item label="背景色">
                            <el-input v-model="item.bgColor" placeholder="渐变色或纯色(无图时生效)" size="small" />
                        </el-form-item>
                        <el-form-item label="链接">
                            <diy-link v-model="item.link" />
                        </el-form-item>
                        <el-form-item label="显示">
                            <el-switch v-model="item.isShow" size="small" />
                        </el-form-item>
                    </el-form>
                    <el-divider />
                </div>
            </div>
        </div>
    </div>

    <div class="style-wrap" v-show="diyStore.editTab == 'style'">
        <slot name="style"></slot>
    </div>
</template>

<script lang="ts" setup>
import { watch } from 'vue'
import useDiyStore from '@/stores/modules/diy'
import { ensureListLinks } from '../diyLinkInit'

const diyStore = useDiyStore()
diyStore.editComponent.ignore = []

watch(
    () => diyStore.editComponent?.list,
    (list) => {
        if (!Array.isArray(list)) {
            diyStore.editComponent.list = []
            return
        }
        ensureListLinks(list)
    },
    { immediate: true, deep: true }
)

defineExpose({})
</script>

<style lang="scss" scoped>
.banner-list {
    max-height: 400px;
    overflow-y: auto;
}
.banner-item {
    background: #f9f9f9;
    padding: 10px;
    margin-bottom: 10px;
    border-radius: 4px;
}
</style>
