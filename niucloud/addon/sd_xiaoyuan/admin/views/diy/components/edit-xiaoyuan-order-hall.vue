<template>
    <div class="content-wrap" v-show="diyStore.editTab == 'content'">
        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">订单大厅设置</h3>
            <el-form label-width="100px" class="px-[10px]">
                <el-form-item label="标题">
                    <el-input v-model.trim="diyStore.editComponent.title" placeholder="请输入标题" clearable />
                </el-form-item>
                <el-form-item label="显示更多">
                    <el-switch v-model="diyStore.editComponent.showMore" />
                </el-form-item>
                <el-form-item label="更多文字" v-if="diyStore.editComponent.showMore">
                    <el-input v-model.trim="diyStore.editComponent.moreText" placeholder="更多文字" clearable />
                </el-form-item>
                <el-form-item label="更多链接" v-if="diyStore.editComponent.showMore">
                    <diy-link v-model="diyStore.editComponent.moreLink" />
                </el-form-item>
                <el-form-item label="显示数量">
                    <el-input-number v-model="diyStore.editComponent.num" :min="1" :max="20" />
                </el-form-item>
            </el-form>
            
            <h3 class="mb-[10px] mt-[20px]">标签配置</h3>
            <div class="tab-list">
                <div class="tab-item" v-for="(tab, idx) in diyStore.editComponent.tabs" :key="idx">
                    <el-form label-width="80px" class="px-[10px]" inline>
                        <el-form-item label="名称">
                            <el-input v-model="tab.name" placeholder="标签名称" size="small" style="width: 80px" />
                        </el-form-item>
                        <el-form-item label="类型">
                            <el-input v-model="tab.type" placeholder="类型" size="small" style="width: 80px" />
                        </el-form-item>
                        <el-form-item label="显示">
                            <el-switch v-model="tab.isShow" size="small" />
                        </el-form-item>
                    </el-form>
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
import { ensureFieldLink } from '../diyLinkInit'

const diyStore = useDiyStore()
diyStore.editComponent.ignore = []

watch(
    () => diyStore.editComponent,
    (component) => {
        ensureFieldLink(component, 'moreLink', '/addon/sd_xiaoyuan/pages/order/hall')
    },
    { immediate: true, deep: true }
)

defineExpose({})
</script>

<style lang="scss" scoped>
.tab-list {
    max-height: 300px;
    overflow-y: auto;
}
.tab-item {
    background: #f9f9f9;
    padding: 10px;
    margin-bottom: 10px;
    border-radius: 4px;
}
</style>
