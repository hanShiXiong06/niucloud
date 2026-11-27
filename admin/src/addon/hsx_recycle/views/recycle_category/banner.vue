<template>
    <PremiumTheme class="banner-manage">
        <el-card shadow="never">
            <PageHeader title="轮播图管理">
                <template #actions>
                    <el-button type="primary" @click="handleAdd">
                        添加轮播图
                    </el-button>
                </template>
            </PageHeader>

            <div class="mt-[10px]">
                <el-table :data="tableData" v-loading="loading">
                    <template #empty>
                        <EmptyState
                            v-if="!loading"
                            icon="document"
                            title="暂无轮播图"
                            description="点右上角「添加轮播图」配置首页轮播。"
                        />
                    </template>
                    <el-table-column label="轮播图" min-width="200">
                        <template #default="{ row }">
                            <el-image style="width: 200px; height: 100px" :src="img(row.image[0])" fit="cover"
                                :preview-src-list="row.image" />
                        </template>
                    </el-table-column>
                    <el-table-column prop="sort" label="排序" width="150">
                        <template #default="{ row }">
                            <el-input-number v-model="row.sort" :min="0"
                                @change="(value: number) => handleSortChange(row.id, value)" />
                        </template>
                    </el-table-column>
                    <el-table-column prop="create_at" label="创建时间" min-width="160">
                        <template #default="{ row }">
                            {{ new Date(row.create_at * 1000).toLocaleString() }}
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" fixed="right" align="right" width="150">
                        <template #default="{ row }">
                            <el-button type="primary" link @click="handleEdit(row)">编辑</el-button>
                            <el-button type="danger" link @click="handleDelete(row)">删除</el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </el-card>

        <!-- Banner编辑组件 -->
        <banner-edit ref="bannerEditRef" @complete="getList" />
    </PremiumTheme>
</template>

<script lang="ts" setup>
import PremiumTheme from '@/addon/hsx_recycle/components/PremiumTheme.vue'
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getBannerList, deleteBanner, changeBannerSort } from '@/addon/hsx_recycle/api/recycle_category'
import { img } from '@/utils/common'
import BannerEdit from '@/addon/hsx_recycle/views/recycle_category/components/banner-edit.vue'
import PageHeader from '@/addon/hsx_recycle/components/PageHeader.vue'
import EmptyState from '@/addon/hsx_recycle/components/empty-state/index.vue'

const loading = ref(false)
const tableData = ref([])
const bannerEditRef = ref(false)

// 获取列表
const getList = async () => {
    loading.value = true
    try {
        const res = await getBannerList()
        tableData.value = res.data
    } catch (error) {
        console.error(error)
    }
    loading.value = false
}

// bannerEditRef

// 添加
const handleAdd = () => {
    bannerEditRef.value?.setFormData()
    bannerEditRef.value.showDialog = true
}

// 编辑
const handleEdit = (row: any) => {
    bannerEditRef.value?.setFormData(row)
    bannerEditRef.value.showDialog = true
}

// 删除
const handleDelete = (row: any) => {
    ElMessageBox.confirm('确定要删除该轮播图吗？', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
    }).then(async () => {
        try {
            await deleteBanner(row.id)
            ElMessage.success('删除成功')
            getList()
        } catch (error) {
            console.error(error)
        }
    })
}

// 排序变更
const handleSortChange = async (id: number, sort: number) => {
    try {
        await changeBannerSort(id, sort)
        ElMessage.success('排序修改成功')
        getList()
    } catch (error) {
        console.error(error)
    }
}

onMounted(() => {
    getList()
})
</script>

<style lang="scss" scoped>
.banner-manage {
    .el-card {
        margin-bottom: 20px;
    }
}
</style>