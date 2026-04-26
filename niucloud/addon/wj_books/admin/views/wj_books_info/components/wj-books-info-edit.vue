<template>
    <el-dialog v-model="showDialog" :title="formData.id ? t('updateWjBooksInfo') : t('addWjBooksInfo')" width="50%" class="diy-dialog-wrap" :destroy-on-close="true">
        <el-form :model="formData" label-width="120px" ref="formRef" :rules="formRules" class="page-form" v-loading="loading">
                <el-form-item :label="t('isbn')" prop="isbn">
    <div class="flex items-center w-full">
        <el-input v-model="formData.isbn" clearable :placeholder="t('isbnPlaceholder')" class="input-width mr-2" />
        <el-button type="primary" @click="queryBookByIsbn" :loading="isbnLoading">查询</el-button>
    </div>
</el-form-item>

                <el-form-item :label="t('isbn10')" >
    <el-input v-model="formData.isbn10" clearable :placeholder="t('isbn10Placeholder')" class="input-width" />
</el-form-item>

                <el-form-item :label="t('title')" prop="title">
    <el-input v-model="formData.title" clearable :placeholder="t('titlePlaceholder')" class="input-width" />
</el-form-item>

                <el-form-item :label="t('author')" prop="author">
    <el-input v-model="formData.author" clearable :placeholder="t('authorPlaceholder')" class="input-width" />
</el-form-item>

                <el-form-item :label="t('publisher')" prop="publisher">
    <el-input v-model="formData.publisher" clearable :placeholder="t('publisherPlaceholder')" class="input-width" />
</el-form-item>

                <el-form-item :label="t('pubDate')">
    <el-input v-model="formData.pub_date" clearable :placeholder="t('pubDatePlaceholder')" class="input-width" />
</el-form-item>

                <el-form-item :label="t('price')" prop="price">
    <el-input v-model="formData.price" clearable :placeholder="t('pricePlaceholder')" class="input-width" />
</el-form-item>

                <el-form-item :label="t('binding')" >
    <el-input v-model="formData.binding" clearable :placeholder="t('bindingPlaceholder')" class="input-width" />
</el-form-item>

                <el-form-item :label="t('page')" >
    <el-input v-model="formData.page" clearable :placeholder="t('pagePlaceholder')" class="input-width" />
</el-form-item>

                <el-form-item :label="t('edition')" >
    <el-input v-model="formData.edition" clearable :placeholder="t('editionPlaceholder')" class="input-width" />
</el-form-item>

                <el-form-item :label="t('img')">
    <upload-image v-model="formData.img" />
</el-form-item>

                <el-form-item :label="t('smallImg')">
    <upload-image v-model="formData.small_img" />
</el-form-item>

                <el-form-item :label="t('gist')">
    <el-input v-model="formData.gist" type="textarea" rows="3" clearable :placeholder="t('gistPlaceholder')" class="input-width"/>
</el-form-item>

                <el-form-item :label="t('recyclePrice')" prop="recycle_price">
    <el-input v-model="formData.recycle_price" clearable :placeholder="t('recyclePricePlaceholder')" class="input-width" />
</el-form-item>

                <el-form-item :label="t('canRecycle')" prop="can_recycle">
    <el-select v-model="formData.can_recycle" :placeholder="t('canRecyclePlaceholder')" class="input-width">
        <el-option :label="'可回收'" :value="1" />
        <el-option :label="'不可回收'" :value="0" />
    </el-select>
</el-form-item>

                <el-form-item :label="t('recycleCount')">
    <el-input-number v-model="formData.recycle_count" :placeholder="t('recycleCountPlaceholder')" class="input-width" :min="0" :max="9999" />
</el-form-item>

                <el-form-item :label="t('apiJson')" >
    <el-input v-model="formData.api_json" type="textarea" rows="4" clearable :placeholder="t('apiJsonPlaceholder')" class="input-width"/>
</el-form-item>
                <el-form-item :label="t('apiQueryTime')"  class="input-width">
    <el-date-picker 
        class="flex-1 !flex"
        v-model="formData.api_query_time"
        clearable
        type="datetime"
        value-format="YYYY-MM-DD HH:mm:ss"
        :placeholder="t('apiQueryTimePlaceholder')">
    </el-date-picker>
</el-form-item>
        </el-form>

        <template #footer>
            <span class="dialog-footer">
                <el-button @click="showDialog = false">{{ t('cancel') }}</el-button>
                <el-button type="primary" :loading="loading" @click="confirm(formRef)">{{
                    t('confirm')
                }}</el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script lang="ts" setup>
import { ref, reactive, computed, watch } from 'vue'
import { useDictionary } from '@/app/api/dict'
import { t } from '@/lang'
import type { FormInstance } from 'element-plus'
import { addWjBooksInfo, editWjBooksInfo, getWjBooksInfoInfo, queryBookInfoByIsbn } from '@/addon/wj_books/api/wj_books_info'
import { ElMessage } from 'element-plus'

let showDialog = ref(false)
const loading = ref(false)
const isbnLoading = ref(false)

/**
 * 表单数据
 */
const initialFormData = {
    id: '',
    isbn: '',
    isbn10: '',
    title: '',
    author: '',
    publisher: '',
    pub_date: '',
    price: '',
    binding: '',
    page: '',
    edition: '',
    img: '',
    small_img: '',
    gist: '',
    recycle_price: '',
    can_recycle: 1,
    recycle_count: 0,
    api_json: '',
    api_query_time: '',
}
const formData: Record<string, any> = reactive({ ...initialFormData })

const formRef = ref<FormInstance>()

// 表单验证规则
const formRules = computed(() => {
    return {
    isbn: [
        { required: true, message: t('isbnPlaceholder'), trigger: 'blur' },
    ],
    title: [
        { required: true, message: t('titlePlaceholder'), trigger: 'blur' },
    ],
    author: [
        { required: true, message: t('authorPlaceholder'), trigger: 'blur' },
    ],
    publisher: [
        { required: true, message: t('publisherPlaceholder'), trigger: 'blur' },
    ],
    price: [
        { required: true, message: t('pricePlaceholder'), trigger: 'blur' },
    ],
    recycle_price: [
        { required: true, message: t('recyclePricePlaceholder'), trigger: 'blur' },
    ],
    can_recycle: [
        { required: true, message: t('canRecyclePlaceholder'), trigger: 'blur' },
    ],
    }
})

const emit = defineEmits(['complete'])

/**
 * 确认
 * @param formEl
 */
const confirm = async (formEl: FormInstance | undefined) => {
    if (loading.value || !formEl) return
    let save = formData.id ? editWjBooksInfo : addWjBooksInfo

    await formEl.validate(async (valid) => {
        if (valid) {
            loading.value = true

            let data = formData

            save(data).then(res => {
                loading.value = false
                showDialog.value = false
                emit('complete')
            }).catch(err => {
                loading.value = false
            })
        }
    })
}

// 获取字典数据
    

    
const setFormData = async (row: any = null) => {
    Object.assign(formData, initialFormData)
    loading.value = true
    if(row){
        const data = await (await getWjBooksInfoInfo(row.id)).data
        if (data) Object.keys(formData).forEach((key: string) => {
            if (data[key] != undefined) formData[key] = data[key]
        })
    }
    loading.value = false
}

// 验证手机号格式
const mobileVerify = (rule: any, value: any, callback: any) => {
    if (value && !/^1[3-9]\d{9}$/.test(value)) {
        callback(new Error(t('generateMobile')))
    } else {
        callback()
    }
}

// 验证身份证号
const idCardVerify = (rule: any, value: any, callback: any) => {
    if (value && !/^[1-9]\d{5}[1-9]\d{3}((0\d)|(1[0-2]))(([0|1|2]\d)|3[0-1])\d{3}([0-9]|X)$/.test(value)) {
        callback(new Error(t('generateIdCard')))
    } else {
        callback()
    }
}

// 验证邮箱号
const emailVerify = (rule: any, value: any, callback: any) => {
    if (value && !/\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*/.test(value)) {
        callback(new Error(t('generateEmail')))
    } else {
        callback()
    }
}

// 验证请输入整数
const numberVerify = (rule: any, value: any, callback: any) => {
    if (!Number.isInteger(value)) {
        callback(new Error(t('generateNumber')))
    } else {
        callback()
    }
}

const queryBookByIsbn = async () => {
    if (isbnLoading.value || !formData.isbn) {
        if (!formData.isbn) {
            ElMessage.warning('请输入ISBN编码');
        }
        return;
    }
    
    isbnLoading.value = true;
    try {
        const res = await queryBookInfoByIsbn(formData.isbn);
        if (res.data) {
            if (res.data.code === -1) {
                ElMessage.error(res.data.msg || '查询失败');
                return;
            }
            
            if (res.data.is_exist === 1) {
                ElMessage.warning('该ISBN图书已存在，已自动填充数据');
                // 如果是编辑模式且不是当前编辑的图书，提示用户
                if (formData.id && formData.id !== res.data.book_info.id) {
                    ElMessage.error('该ISBN已被其他图书使用，请修改ISBN');
                    return;
                }
                Object.keys(formData).forEach(key => {
                    if (res.data.book_info[key] !== undefined) {
                        formData[key] = res.data.book_info[key];
                    }
                });
            } else {
                // 新图书信息，填充表单
                const bookInfo = res.data.book_info;
                Object.keys(formData).forEach(key => {
                    if (bookInfo[key] !== undefined) {
                        formData[key] = bookInfo[key];
                    }
                });
                ElMessage.success('图书信息查询成功');
            }
        }
    } catch (error) {
        console.error('查询ISBN失败', error);
        ElMessage.error('查询失败，请稍后重试');
    } finally {
        isbnLoading.value = false;
    }
};

defineExpose({
    showDialog,
    setFormData
})
</script>

<style lang="scss" scoped></style>
<style lang="scss">
.diy-dialog-wrap .el-form-item__label{
    height: auto  !important;
}
</style>
