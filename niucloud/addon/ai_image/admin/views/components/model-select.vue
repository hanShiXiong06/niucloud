loadStoreList<template>

    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <el-form :inline="true" :model="storeTable.searchParam" ref="searchFormRef">

                <!-- <el-form-item :label="t('type')" prop="type">
                    <el-select v-model="storeTable.searchParam.type" placeholder="请选择活动类型" clearable>
                        <el-option v-for="(item, index) in storeTypeList" :key="index" :label="item" :value="index" />
                    </el-select>
                </el-form-item> -->
                <el-form-item :label="t('title')" prop="title">
                    <el-input v-model="storeTable.searchParam.title" :placeholder="t('titlePlaceholder')" />
                </el-form-item>

                <el-form-item class="">
                    <el-button type="primary" @click="loadStoreList()">{{ t('search') }}</el-button>
                    <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
                </el-form-item>
            </el-form>


            <div class="mt-[20px]">
                <el-table :data="storeTable.data" size="large" v-loading="storeTable.loading"
                    @row-click="handleRowClick" :row-class-name="tableRowClassName">
                    <template #empty>
                        <span>{{ !storeTable.loading ? t('emptyData') : '' }}</span>
                    </template>

                    <el-table-column prop="name" label="名称" min-width="120" :show-overflow-tooltip="true" />

                    <el-table-column prop="desc" label="描述" min-width="120" :show-overflow-tooltip="true" />

                    <el-table-column width="50">
                        <template #default="{ row }">
                            <el-icon v-if="isSelected(row)" class="check-icon text-primary">
                                <Check />
                            </el-icon>
                        </template>
                    </el-table-column>

                </el-table>
                <div class="mt-[16px] flex justify-end">
                    <el-pagination v-model:current-page="storeTable.page" v-model:page-size="storeTable.limit"
                        layout="total, sizes, prev, pager, next, jumper" :total="storeTable.total"
                        @size-change="loadStoreList()" @current-change="loadStoreList" />
                </div>
            </div>


        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, computed } from "vue";
import { t } from "@/lang";
import { getAiimageModelList } from '@/addon/ai_image/api/aiimagemodel'
import { img } from "@/utils/common";
import { FormInstance } from "element-plus";
import { ArrowDown, Loading, Check } from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';

// 定义API返回类型
interface ApiResponse {
    code: number;
    data?: any;
    msg?: string;
}

// 定义驱动类型
interface Driver {
    name: string;
    type: string;
}

// 定义活动项类型
interface StoreivityItem {
    id: number;
    name: string;
    sort: number;
}

const prop = defineProps({
    storeId: {
        type: [Number, String],
        default: ''
    }
})

const storeId = computed(() => {
    return prop.storeId
})

const dialogVisible = ref(false);

const openSelector = () => {
    dialogVisible.value = true;
    loadStoreList();
};

const storeTable = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [] as StoreivityItem[],
    searchParam: {
        name: "",
    },
});

const searchFormRef = ref<FormInstance>();

// 加载活动列表
const loadStoreList = async (page: number = 1) => {
    storeTable.loading = true;

    const res = await getAiimageModelList({
        page: page || storeTable.page,
        limit: storeTable.limit,
        ...storeTable.searchParam,
    })
    if (res.code === 1 && res.data) {
        storeTable.data = (res.data.data || []).map((item) => ({
            ...item,
            saving: false
        }));
        storeTable.total = res.data.total || 0;
        storeTable.page = page || storeTable.page;
        storeTable.loading = false;
    }

};

// 初始加载
loadStoreList();

const selectData = ref<StoreivityItem>();

// 选中行样式
const tableRowClassName = ({ row }: { row: StoreivityItem }) => {
    return isSelected(row) ? 'selected-row' : '';
};

// 检查是否选中
const isSelected = (row: StoreivityItem) => {
    return selectData.value && selectData.value.id === row.id;
};

// 行点击事件处理
const handleRowClick = (row: StoreivityItem) => {
    handleSelect(row);
};

// 选择活动
const handleSelect = (row: StoreivityItem) => {
    selectData.value = row;
    dialogVisible.value = false;
};

const getData = () => {
    if (!selectData.value) {
        ElMessage({
            type: 'warning',
            message: '请选择商家'
        })
        return
    }

    return {
        name: 'AI_IMAGE_BASE_LINK',
        title: selectData.value.name,
        url: '/addon/ai_image/pages/create?model_id=' + selectData.value.id,
        storeion: '',
        storeId: selectData.value.id
    }
}

defineExpose({
    getData
})

// 重置表单
const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return;
    formEl.resetFields();
    storeTable.searchParam.store_name = "";
    storeTable.searchParam.type = "";
    storeTable.searchParam.title = "";
    loadStoreList(1);
};


</script>

<style lang="scss" scoped>
.select-box {
    cursor: pointer;

    :deep(.el-input) {
        .el-input__wrapper {
            cursor: pointer;
        }

        .el-input__inner {
            cursor: pointer;
        }

        .el-input__suffix {
            cursor: pointer;
        }
    }
}

.saving-container {
    .loading-icon-container {
        position: relative;
        width: 60px;
        height: 60px;

        .loading-icon {
            position: absolute;
            font-size: 3rem;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            animation: loading-animation 2s infinite;
            color: var(--el-color-primary);
        }
    }

    .success-message {
        color: var(--el-color-success);
        animation: message-fade 0.3s ease-in-out;
    }

    .error-message {
        color: var(--el-color-danger);
        animation: message-fade 0.3s ease-in-out;
    }
}

:deep(.el-table) {
    .selected-row {
        background-color: rgba(var(--el-color-primary-rgb), 0.1);
        transition: all 0.3s ease;
        border-left: 3px solid var(--el-color-primary);
        font-weight: 500;
        color: var(--el-color-primary);
        box-shadow: inset 0 0 10px rgba(var(--el-color-primary-rgb), 0.05);
    }

    tr {
        cursor: pointer;
        transition: all 0.3s;
        border-left: 3px solid transparent;

        &:hover {
            background-color: rgba(var(--el-color-primary-rgb), 0.05);
            border-left-color: rgba(var(--el-color-primary-rgb), 0.3);
        }
    }
}

.text-primary {
    color: var(--el-color-primary);
}

.check-icon {
    font-size: 18px;
    animation: checkmark-appear 0.3s ease-in-out;
}

@keyframes loading-animation {
    0% {
        transform: translate(-50%, -50%) rotate(0deg) scale(1);
    }

    50% {
        transform: translate(-50%, -50%) rotate(180deg) scale(1.2);
    }

    100% {
        transform: translate(-50%, -50%) rotate(360deg) scale(1);
    }
}

@keyframes message-fade {
    0% {
        opacity: 0;
        transform: translateY(10px);
    }

    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes checkmark-appear {
    0% {
        opacity: 0;
        transform: translateY(-50%) scale(0.5);
    }

    100% {
        opacity: 1;
        transform: translateY(-50%) scale(1);
    }
}
</style>