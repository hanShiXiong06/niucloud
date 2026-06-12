<template>
    <PremiumTheme class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <PageHeader title="易速产品配置" description="启用的产品将在快递下单时显示报价，建议只启用 1-2 个常用产品。">
                <template #actions>
                    <el-button type="primary" @click="handleBatchSave">
                        <template #icon>
                            <el-icon><Check /></el-icon>
                        </template>
                        保存配置
                    </el-button>
                </template>
            </PageHeader>

            <el-card class="box-card !border-none mt-[20px]" shadow="never">
                <el-alert
                    title="提示"
                    type="info"
                    description="启用的产品将在快递下单时显示报价，建议只启用1-2个常用产品"
                    :closable="false"
                    class="mb-4"
                />

                <!-- 按类型分组显示 -->
                <el-tabs v-model="activeTab">
                    <el-tab-pane label="快递类" name="快递">
                        <el-table v-loading="loading" :data="getProductsByType('快递')" stripe style="width: 100%">
                            <el-table-column prop="product_code" label="产品代码" width="100" />
                            <el-table-column prop="product_name" label="产品名称" width="200" />
                            <el-table-column label="状态" width="100">
                                <template #default="{ row }">
                                    <el-switch
                                        v-model="row.status"
                                        :active-value="1"
                                        :inactive-value="0"
                                    />
                                </template>
                            </el-table-column>
                            <el-table-column label="排序" width="150">
                                <template #default="{ row }">
                                    <el-input-number
                                        v-model="row.sort"
                                        :min="0"
                                        :max="999"
                                        size="small"
                                    />
                                </template>
                            </el-table-column>
                            <el-table-column label="说明">
                                <template #default="{ row }">
                                    <span class="text-gray-500">{{ getProductDesc(row.product_code) }}</span>
                                </template>
                            </el-table-column>
                        </el-table>
                    </el-tab-pane>

                    <el-tab-pane label="重货类" name="重货">
                        <el-table v-loading="loading" :data="getProductsByType('重货')" stripe style="width: 100%">
                            <el-table-column prop="product_code" label="产品代码" width="100" />
                            <el-table-column prop="product_name" label="产品名称" width="200" />
                            <el-table-column label="状态" width="100">
                                <template #default="{ row }">
                                    <el-switch
                                        v-model="row.status"
                                        :active-value="1"
                                        :inactive-value="0"
                                    />
                                </template>
                            </el-table-column>
                            <el-table-column label="排序" width="150">
                                <template #default="{ row }">
                                    <el-input-number
                                        v-model="row.sort"
                                        :min="0"
                                        :max="999"
                                        size="small"
                                    />
                                </template>
                            </el-table-column>
                            <el-table-column label="说明">
                                <template #default="{ row }">
                                    <span class="text-gray-500">{{ getProductDesc(row.product_code) }}</span>
                                </template>
                            </el-table-column>
                        </el-table>
                    </el-tab-pane>

                    <el-tab-pane label="得物类" name="得物">
                        <el-table v-loading="loading" :data="getProductsByType('得物')" stripe style="width: 100%">
                            <el-table-column prop="product_code" label="产品代码" width="100" />
                            <el-table-column prop="product_name" label="产品名称" width="200" />
                            <el-table-column label="状态" width="100">
                                <template #default="{ row }">
                                    <el-switch
                                        v-model="row.status"
                                        :active-value="1"
                                        :inactive-value="0"
                                    />
                                </template>
                            </el-table-column>
                            <el-table-column label="排序" width="150">
                                <template #default="{ row }">
                                    <el-input-number
                                        v-model="row.sort"
                                        :min="0"
                                        :max="999"
                                        size="small"
                                    />
                                </template>
                            </el-table-column>
                            <el-table-column label="说明">
                                <template #default="{ row }">
                                    <span class="text-gray-500">{{ getProductDesc(row.product_code) }}</span>
                                </template>
                            </el-table-column>
                        </el-table>
                    </el-tab-pane>
                </el-tabs>
            </el-card>
        </el-card>
    </PremiumTheme>
</template>

<script setup lang="ts">
import PremiumTheme from '@/addon/hsx_recycle/components/PremiumTheme.vue'
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Check } from '@element-plus/icons-vue'
import PageHeader from '@/addon/hsx_recycle/components/PageHeader.vue'
import { getYisuProductList, batchUpdateYisuProduct } from '@/addon/hsx_recycle/api/yisu'

const loading = ref(false)
const activeTab = ref('快递')
const productList = ref<any[]>([])

// 产品说明映射
const productDescMap: Record<string, string> = {
    '1': '申通快递 - 经济实惠',
    '2': '圆通快递 - 速度快',
    '3': '德邦快递 - 大件优选',
    '5': '顺丰标快 - 时效保障',
    '10': '极兔速递 - 性价比高',
    '11': '中通快递 - 覆盖广',
    '12': '韵达快递 - 服务好',
    '13': '京东快递 - 品质保证',
    '36': '菜鸟裹裹 - 便捷寄件',
    '47': 'EMS特快 - 全国覆盖',
    '59': '京东快递(电池) - 电池专用',
    '76': '京东快递(3KG内小件) - 小件优选',
    '95': '安能小件 - 小件优选',
}

// 加载产品列表
const loadProductList = async () => {
    loading.value = true
    try {
        const res = await getYisuProductList()
        productList.value = res.data || []
    } catch (error) {
        ElMessage.error('加载产品列表失败')
    } finally {
        loading.value = false
    }
}

// 按类型获取产品
const getProductsByType = (type: string) => {
    return productList.value.filter(item => {
        const code = parseInt(item.product_code)
        if (type === '快递') {
            return [1, 2, 3, 5, 10, 11, 12, 13, 36, 47, 76, 95, 59 ].includes(code)
        } else if (type === '重货') {
            return [21, 23, 24, 25, 39, 40, 42, 48, 53, 67, 68, 71, 86, 97, 101, 102, 103].includes(code)
        } else if (type === '得物') {
            return [28, 29, 30, 32, 34, 35, 88].includes(code)
        }
        return false
    })
}

// 获取产品说明
const getProductDesc = (code: string) => {
    return productDescMap[code] || ''
}

// 批量保存
const handleBatchSave = async () => {
    try {
        await ElMessageBox.confirm('确定要保存当前配置吗？', '提示', {
            confirmButtonText: '确定',
            cancelButtonText: '取消',
            type: 'warning'
        })

        loading.value = true
        const products = productList.value.map(item => ({
            product_code: item.product_code,
            product_name: item.product_name,
            logo: item.logo || '',
            status: item.status,
            sort: item.sort || 0
        }))

        await batchUpdateYisuProduct({ products })
        ElMessage.success('保存成功')
        await loadProductList()
    } catch (error: any) {
        if (error !== 'cancel') {
            ElMessage.error(error.message || '保存失败')
        }
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    loadProductList()
})
</script>

<style scoped lang="scss">
.main-container {
    padding: 20px;
}
</style>

