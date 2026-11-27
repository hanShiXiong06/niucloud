<template>
    <div>
        <div v-show="diyStore.editTab === 'content'" class="edit-attr-item-wrap">
            <el-form label-width="90px" class="px-[10px]">
                <el-form-item label="搜索提示">
                    <el-input v-model="diyStore.editComponent.placeholder" placeholder="搜索型号，查回收价" maxlength="40" show-word-limit />
                </el-form-item>
                <el-form-item label="报价源">
                    <el-select v-model="diyStore.editComponent.sourceId" filterable class="w-full">
                        <el-option :value="0" label="全部报价源" />
                        <el-option v-for="source in sources" :key="source.id" :value="source.id" :label="source.source_name" />
                    </el-select>
                </el-form-item>
            </el-form>
        </div>
        <div v-show="diyStore.editTab === 'style'" class="edit-attr-item-wrap">
            <el-form label-width="90px" class="px-[10px]">
                <el-form-item label="按钮颜色"><el-color-picker v-model="diyStore.editComponent.buttonColor" :predefine="diyStore.predefineColors" /></el-form-item>
                <el-form-item label="背景颜色"><el-color-picker v-model="diyStore.editComponent.componentStartBgColor" show-alpha :predefine="diyStore.predefineColors" /></el-form-item>
            </el-form>
        </div>
    </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import useDiyStore from '@/stores/modules/diy'
import { getQuoteSourceAll } from '@/addon/recycle_quote_spider/api/quote'

const diyStore = useDiyStore()
const sources = ref<{ id: number; source_name: string }[]>([])
diyStore.editComponent.verify = () => ({ code: true, message: '' })
onMounted(async () => {
    try {
        const result = await getQuoteSourceAll()
        sources.value = Array.isArray(result.data) ? result.data : []
    } catch { sources.value = [] }
})
</script>
