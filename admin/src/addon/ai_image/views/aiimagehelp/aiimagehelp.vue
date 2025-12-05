<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center">
                <span class="text-lg">{{pageName}}</span>
                <el-button type="primary" @click="addEvent">
                    {{ t('addAiimageHelp') }}
                </el-button>
            </div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="aiimageHelpTable.searchParam" ref="searchFormRef">
                    <el-form-item :label="t('title')" prop="title">
                        <el-input v-model="aiimageHelpTable.searchParam.title" :placeholder="t('titlePlaceholder')" />
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="loadAiimageHelpList()">{{ t('search') }}</el-button>
                        <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">
                <el-table :data="aiimageHelpTable.data" size="large" v-loading="aiimageHelpTable.loading">
                    <template #empty>
                        <span>{{ !aiimageHelpTable.loading ? t('emptyData') : '' }}</span>
                    </template>
                    <el-table-column prop="cat_id" :label="t('catId')" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column prop="title" :label="t('title')" min-width="120" :show-overflow-tooltip="true"/>
                    
                     <el-table-column :label="t('image')" width="100" align="left">
                        <template #default="{ row }">
                            <el-avatar v-if="row.image" :src="img(row.image)" />
                            <el-avatar v-else icon="UserFilled" />
                        </template>
                    </el-table-column>
                    <el-table-column prop="desc" :label="t('desc')" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column prop="content" :label="t('content')" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column prop="view_num" :label="t('viewNum')" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column prop="sort" :label="t('sort')" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column :label="t('operation')" fixed="right" min-width="120">
                       <template #default="{ row }">
                           <el-button type="primary" link @click="editEvent(row)">{{ t('edit') }}</el-button>
                           <el-button type="primary" link @click="deleteEvent(row.id)">{{ t('delete') }}</el-button>
                       </template>
                    </el-table-column>

                </el-table>
                <div class="mt-[16px] flex justify-end">
                    <el-pagination v-model:current-page="aiimageHelpTable.page" v-model:page-size="aiimageHelpTable.limit"
                        layout="total, sizes, prev, pager, next, jumper" :total="aiimageHelpTable.total"
                        @size-change="loadAiimageHelpList()" @current-change="loadAiimageHelpList" />
                </div>
            </div>

            
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, watch } from 'vue'
import { t } from '@/lang'
import { useDictionary } from '@/app/api/dict'
import { getAiimageHelpList, deleteAiimageHelp } from '@/addon/ai_image/api/aiimagehelp'
import { img } from '@/utils/common'
import { ElMessageBox,FormInstance } from 'element-plus'
import { useRouter } from 'vue-router'
import { useRoute } from 'vue-router'
const route = useRoute()
const pageName = route.meta.title;

let aiimageHelpTable = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam:{
      "title":""
    }
})

const searchFormRef = ref<FormInstance>()

// 选中数据
const selectData = ref<any[]>([])

// 字典数据
    

/**
 * 获取帮助中心列表
 */
const loadAiimageHelpList = (page: number = 1) => {
    aiimageHelpTable.loading = true
    aiimageHelpTable.page = page

    getAiimageHelpList({
        page: aiimageHelpTable.page,
        limit: aiimageHelpTable.limit,
         ...aiimageHelpTable.searchParam
    }).then(res => {
        aiimageHelpTable.loading = false
        aiimageHelpTable.data = res.data.data
        aiimageHelpTable.total = res.data.total
    }).catch(() => {
        aiimageHelpTable.loading = false
    })
}
loadAiimageHelpList()

const router = useRouter()

/**
 * 添加帮助中心
 */
const addEvent = () => {
    router.push('/aiimagehelp/aiimagehelp_edit')
}

/**
 * 编辑帮助中心
 * @param data
 */
const editEvent = (data: any) => {
    router.push('/aiimagehelp/aiimagehelp_edit?id='+data.id)
}

/**
 * 删除帮助中心
 */
const deleteEvent = (id: number) => {
    ElMessageBox.confirm(t('aiimageHelpDeleteTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning',
        }
    ).then(() => {
        deleteAiimageHelp(id).then(() => {
            loadAiimageHelpList()
        }).catch(() => {
        })
    })
}

    

const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    loadAiimageHelpList()
}
</script>

<style lang="scss" scoped>
/* 多行超出隐藏 */
.multi-hidden {
		word-break: break-all;
		text-overflow: ellipsis;
		overflow: hidden;
		display: -webkit-box;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
	}
</style>
