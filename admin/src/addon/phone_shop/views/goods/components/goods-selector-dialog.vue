<template>
    <el-dialog v-model="showDialog" title="选择商品" width="800px">
        <el-form :inline="true">
            <el-form-item label="商品名称">
                <el-input
                    v-model="searchKeyword"
                    placeholder="输入商品名称搜索"
                    clearable
                    @keyup.enter="loadGoodsList"
                />
            </el-form-item>
            <el-form-item>
                <el-button type="primary" @click="loadGoodsList">搜索</el-button>
            </el-form-item>
        </el-form>

        <el-table
            :data="goodsList"
            v-loading="loading"
            @row-click="handleRowClick"
            style="cursor: pointer"
        >
            <el-table-column label="商品信息" min-width="300">
                <template #default="{ row }">
                    <div class="flex items-center">
                        <el-image
                            v-if="row.goods_cover"
                            :src="img(row.goods_cover)"
                            style="width: 60px; height: 60px"
                            fit="cover"
                        />
                        <div class="ml-2">
                            <div>{{ row.goods_name }}</div>
                            <div class="text-sm text-gray-500">{{ row.goodsSku.sku_name }}</div>
                        </div>
                    </div>
                </template>
            </el-table-column>
            <el-table-column label="价格" width="100">
                <template #default="{ row }">
                    ¥{{ row.goodsSku.price }}
                </template>
            </el-table-column>
            <el-table-column label="库存" width="100">
                <template #default="{ row }">
                    {{ row.stock }}
                </template>
            </el-table-column>
        </el-table>

        <div class="mt-4 flex justify-end">
            <el-pagination
                v-model:current-page="page"
                v-model:page-size="limit"
                :total="total"
                layout="total, prev, pager, next"
                @current-change="loadGoodsList"
            />
        </div>
    </el-dialog>
</template>

<script lang="ts" setup>
import { ref, watch } from 'vue'
import { getGoodsPageList } from '@/addon/phone_shop/api/goods'
import { img } from '@/utils/common'

const props = defineProps<{
    modelValue: boolean
}>()

const emit = defineEmits(['update:modelValue', 'confirm'])

const showDialog = ref(false)
const loading = ref(false)
const searchKeyword = ref('')
const goodsList = ref<any[]>([])
const page = ref(1)
const limit = ref(10)
const total = ref(0)

// 监听modelValue变化
watch(() => props.modelValue, (val) => {
    showDialog.value = val
    if (val) {
        loadGoodsList()
    }
})

// 监听showDialog变化
watch(showDialog, (val) => {
    emit('update:modelValue', val)
})

/**
 * 加载商品列表
 */
const loadGoodsList = async () => {
    loading.value = true
    try {
        const res = await getGoodsPageList({
            page: page.value,
            limit: limit.value,
            goods_name: searchKeyword.value,
            status: 1 // 只显示上架商品
        })
        goodsList.value = res.data.data || []
        total.value = res.data.total || 0
    } catch (error) {
        console.error('加载商品列表失败:', error)
    } finally {
        loading.value = false
    }
}

/**
 * 处理行点击
 */
const handleRowClick = (row: any) => {
    emit('confirm', {
        goods_id: row.goods_id,
        sku_id: row.goodsSku.sku_id,
        goods_name: row.goods_name,
        sku_name: row.goodsSku.sku_name,
        goods_cover: row.goods_cover,
        price: row.goodsSku.price,
        stock: row.stock
    })
    showDialog.value = false
}
</script>

<style lang="scss" scoped>
.flex {
    display: flex;
}
.items-center {
    align-items: center;
}
.justify-end {
    justify-content: flex-end;
}
.ml-2 {
    margin-left: 8px;
}
.mt-4 {
    margin-top: 16px;
}
.text-sm {
    font-size: 14px;
}
.text-gray-500 {
    color: #6b7280;
}
</style>
