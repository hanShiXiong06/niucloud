<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <div class="flex justify-between items-center mb-[5px]">
                <span class="text-page-title">快递公司</span>
                <div>
                    <el-button :loading="importing" @click="importPresetsEvent">导入常用快递</el-button>
                    <el-button type="primary" @click="addEvent">新增快递公司</el-button>
                </div>
            </div>
            <div class="text-[13px] text-gray-500 mb-[14px]">维护可用的快递公司。开启「电子面单」后，公司下的业务类型与打印样式会被电子面单模板引用。</div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="searchParam">
                    <el-form-item label="公司名称">
                        <el-input v-model.trim="searchParam.company_name" placeholder="请输入公司名称" maxlength="30" clearable />
                    </el-form-item>
                    <el-form-item label="电子面单">
                        <el-select v-model="searchParam.electronic_sheet_switch" placeholder="全部" clearable class="w-[120px]">
                            <el-option label="支持" :value="1" />
                            <el-option label="不支持" :value="0" />
                        </el-select>
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="loadList()">搜索</el-button>
                        <el-button @click="resetSearch">重置</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <el-table :data="tableData.data" size="large" v-loading="tableData.loading" class="mt-[10px]">
                <template #empty>
                    <span>{{ !tableData.loading ? '暂无数据' : '' }}</span>
                </template>
                <el-table-column label="公司" min-width="180" show-overflow-tooltip>
                    <template #default="{ row }">
                        <div class="flex items-center">
                            <el-image v-if="row.logo" :src="row.logo" fit="contain" class="w-[28px] h-[28px] mr-[8px] rounded" />
                            <span>{{ row.company_name }}</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="已绑定服务商" min-width="220">
                    <template #default="{ row }">
                        <template v-if="row.bindings && row.bindings.length">
                            <el-tag v-for="b in row.bindings" :key="b.provider" size="small" class="mr-[6px]"
                                :type="b.electronic_sheet_switch ? 'success' : 'info'">
                                {{ providerName(b.provider) }}{{ b.provider_code ? '·' + b.provider_code : '' }}{{ b.electronic_sheet_switch ? '·面单' : '' }}
                            </el-tag>
                        </template>
                        <span v-else class="text-gray-400">未绑定</span>
                    </template>
                </el-table-column>
                <el-table-column label="状态" min-width="80">
                    <template #default="{ row }">
                        <span>{{ row.status ? '启用' : '停用' }}</span>
                    </template>
                </el-table-column>
                <el-table-column label="排序" prop="sort" min-width="70" />
                <el-table-column label="操作" fixed="right" min-width="120" align="right">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="editEvent(row)">编辑</el-button>
                        <el-button type="primary" link @click="deleteEvent(row.company_id)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="mt-[16px] flex justify-end">
                <el-pagination v-model:current-page="tableData.page" v-model:page-size="tableData.limit"
                    layout="total, sizes, prev, pager, next, jumper" :total="tableData.total"
                    @size-change="loadList()" @current-change="loadList" />
            </div>
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getDeliveryCompanyPageList, deleteDeliveryCompany, importDeliveryCompanyPresets } from '@/addon/hsx_recycle/api/delivery'

const router = useRouter()
const importing = ref(false)
const providerName = (p: string) => ({ kuaidi100: '快递100', yisu: '易速' }[p] || p)

const searchParam = reactive<Record<string, any>>({
    company_name: '',
    electronic_sheet_switch: ''
})

const tableData = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [] as any[]
})

const loadList = (page: number = 1) => {
    tableData.loading = true
    tableData.page = page
    getDeliveryCompanyPageList({
        page: tableData.page,
        limit: tableData.limit,
        ...searchParam
    }).then((res: any) => {
        tableData.loading = false
        tableData.data = res.data.data
        tableData.total = res.data.total
    }).catch(() => {
        tableData.loading = false
    })
}

loadList()

const resetSearch = () => {
    searchParam.company_name = ''
    searchParam.electronic_sheet_switch = ''
    loadList()
}

const importPresetsEvent = () => {
    ElMessageBox.confirm('将导入一批常用快递公司（已存在的会自动跳过），是否继续？', '导入常用快递', {
        confirmButtonText: '导入',
        cancelButtonText: '取消',
        type: 'info'
    }).then(() => {
        importing.value = true
        importDeliveryCompanyPresets().then((res: any) => {
            importing.value = false
            ElMessage.success('已导入 ' + (res.data?.count ?? 0) + ' 家')
            loadList()
        }).catch(() => { importing.value = false })
    })
}

const addEvent = () => {
    router.push('/delivery/company_edit')
}

const editEvent = (row: any) => {
    router.push('/delivery/company_edit?company_id=' + row.company_id)
}

const deleteEvent = (id: number) => {
    ElMessageBox.confirm('确定删除该快递公司吗？引用了它的电子面单模板将失效。', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
    }).then(() => {
        deleteDeliveryCompany(id).then(() => loadList(tableData.page))
    })
}
</script>

<style lang="scss" scoped></style>
