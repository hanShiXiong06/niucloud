<template>
    <div class="content-wrap" v-show="diyStore.editTab == 'content'">
        <div class="edit-attr-item-wrap">
            <h3 class="mb-[10px]">次卡网格设置</h3>
            <el-form label-width="100px" class="px-[10px]">
                <el-form-item label="是否显示">
                    <el-switch v-model="diyStore.editComponent.isShow" />
                </el-form-item>
            </el-form>

            <h3 class="mb-[10px] mt-[20px]">次卡项配置</h3>
            <div class="card-list">
                <div class="card-item" v-for="(item, idx) in diyStore.editComponent.list" :key="item.cardType || idx">
                    <el-form label-width="80px" class="px-[10px]">
                        <el-form-item label="类型">
                            <span>{{ cardTypeLabel(item.cardType) }}</span>
                        </el-form-item>
                        <el-form-item label="名称">
                            <el-input v-model="item.name" placeholder="卡片名称" size="small" />
                        </el-form-item>
                        <el-form-item label="副标题">
                            <el-input v-model="item.subtitle" placeholder="副标题" size="small" />
                        </el-form-item>
                        <el-form-item label="显示">
                            <el-switch v-model="item.isShow" size="small" />
                        </el-form-item>
                    </el-form>
                    <el-divider />
                </div>
            </div>

            <h3 class="mb-[10px] mt-[20px]">申请接单卡片</h3>
            <el-form label-width="100px" class="px-[10px]">
                <el-form-item label="显示申请">
                    <el-switch v-model="diyStore.editComponent.showApply" />
                </el-form-item>
                <template v-if="diyStore.editComponent.showApply">
                    <el-form-item label="标题">
                        <el-input v-model.trim="diyStore.editComponent.applyTitle" placeholder="申请接单" clearable />
                    </el-form-item>
                    <el-form-item label="副标题">
                        <el-input v-model.trim="diyStore.editComponent.applySubtitle" placeholder="成为校园跑腿员" clearable />
                    </el-form-item>
                </template>
            </el-form>
        </div>
    </div>

    <div class="style-wrap" v-show="diyStore.editTab == 'style'">
        <slot name="style"></slot>
    </div>
</template>

<script lang="ts" setup>
import { watch } from 'vue'
import useDiyStore from '@/stores/modules/diy'

const diyStore = useDiyStore()
diyStore.editComponent.ignore = []

const defaultList = [
    { cardType: 'EXPRESS', name: '快递卡', subtitle: '代取快递更省心', isShow: true },
    { cardType: 'ERRAND', name: '跑腿卡', subtitle: '校园跑腿一键下单', isShow: true },
    { cardType: 'PRINT', name: '打印卡', subtitle: '代打印省时省力', isShow: true },
]

const cardTypeLabel = (type: string) => {
    const map: Record<string, string> = {
        EXPRESS: '快递卡',
        ERRAND: '跑腿卡',
        PRINT: '打印卡',
    }
    return map[type] || type
}

const ensureList = () => {
    const component = diyStore.editComponent
    if (!component.path) component.path = 'edit-xiaoyuan-card-grid'
    if (!component.componentTitle) component.componentTitle = '次卡网格'
    if (!Array.isArray(component.list) || !component.list.length) {
        component.list = defaultList.map((item) => ({ ...item }))
        return
    }
    defaultList.forEach((def) => {
        const row = component.list.find((item: any) => item.cardType === def.cardType)
        if (!row) {
            component.list.push({ ...def })
            return
        }
        if (row.name === undefined || row.name === '') row.name = def.name
        if (row.subtitle === undefined || row.subtitle === '') row.subtitle = def.subtitle
        if (row.isShow === undefined) row.isShow = true
        if (!row.cardType) row.cardType = def.cardType
    })
    if (component.isShow === undefined) component.isShow = true
    if (component.showApply === undefined) component.showApply = true
    if (!component.applyTitle) component.applyTitle = '申请接单'
    if (!component.applySubtitle) component.applySubtitle = '成为校园跑腿员'
}

watch(
    () => diyStore.editComponent,
    () => {
        ensureList()
    },
    { immediate: true, deep: true }
)

defineExpose({})
</script>

<style lang="scss" scoped>
.card-list {
    max-height: 360px;
    overflow-y: auto;
}
.card-item {
    background: #f9f9f9;
    padding: 10px;
    margin-bottom: 10px;
    border-radius: 4px;
}
</style>
