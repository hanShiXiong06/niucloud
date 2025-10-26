<template>
    <div class="main-container">
        <el-card class="card !border-none mb-[15px]" shadow="never">
            <el-page-header :content="pageName" :icon="ArrowLeft" @back="router.push({ path: '/home_service/help/list' })" />
        </el-card>

        <el-card class="box-card !border-none" shadow="never">
            <el-form :model="formData" label-width="90px" ref="formRef" :rules="formRules" class="page-form" v-loading="loading">
                <el-form-item :label="t('title')" prop="name">
                    <el-input v-model.trim="formData.name" clearable :placeholder="t('titlePlaceholder')" class="input-width" maxlength="20" />
                </el-form-item>

                <el-form-item :label="t('categoryName')" prop="category_id">
                    <el-select v-model="formData.category_id" clearable :placeholder="t('categoryIdPlaceholder')" class="input-width">
                        <el-option :label="item['label']" :value="item['value']" v-for="(item,index) in categoryList" :key="index" />
                    </el-select>
                </el-form-item>
				<el-form-item :label="t('typeSouce')" prop="type">
				    <el-select v-model="formData.type" clearable :placeholder="t('typePlaceholder')" class="input-width">
				        <el-option :label="item['label']" :value="item['value']" v-for="(item,index) in typeList" :key="index" />
				    </el-select>
				</el-form-item>
				<el-form-item :label="t('isShow')">
				    <el-radio-group v-model="formData.is_show" :placeholder="t('isShowPlaceholder')">
				        <el-radio :label="1">{{ t('show') }}</el-radio>
				        <el-radio :label="0">{{ t('hidden') }}</el-radio>
				    </el-radio-group>
				</el-form-item>
				<el-form-item :label="t('sort')" prop="sort">
				    <el-input-number v-model="formData.sort" :min="0" />
				</el-form-item>
                <el-form-item :label="t('content')" prop="content">
                    <editor v-model="formData.content" />
                </el-form-item>
            </el-form>
        </el-card>
        <div class="fixed-footer-wrap">
            <div class="fixed-footer">
                <el-button type="primary" @click="onSave(formRef)">{{ t('save') }}</el-button>
                <el-button @click="back()">{{ t('cancel') }}</el-button>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, computed } from 'vue'
import { t } from '@/lang'
import type { FormInstance } from 'element-plus'
import { ArrowLeft } from "@element-plus/icons-vue"
import { gethelpInfo, gethelpCategoryAll, addhelp, edithelp,gethelpType ,gethelpCategoryList} from '@/addon/home_service/api/help'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'

const route = useRoute()
const router = useRouter()
const id: number = parseInt(route.query.id as string || '0')
const loading = ref(false)
const categoryList = ref([])
const pageName = route.meta.title

const typeList = ref([])
	
	const gethelpTypeFn = () =>{
		gethelpType().then((res)=>{
			console.log(res)
			Object.keys(res.data).forEach((item,index)=>{
				let obj = {
					label:res.data[item],
					value:item
				}
				typeList.value.push(obj)
			})
		})
	}
	gethelpTypeFn()
	
	
/**
 * 表单数据
 */
const initialFormData = {
    id: '',
    category_id: '',
    name: '',
	type:'',
    content: '',
    visit: '',
    is_show: 1,
    sort: 0,
	help_id:''
}

const formData: Record<string, any> = reactive({ ...initialFormData })

const setFormData = async (id: number = 0) => {
    loading.value = true
    Object.assign(formData, initialFormData)
    if (id) {
        const data = await (await gethelpInfo(id)).data
        if (!data || Object.keys(data).length == 0) {
            ElMessage.error(t('helpNull'))
            setTimeout(() => {
                router.go(-1)
            }, 2000)
            return false
        }
        Object.keys(formData).forEach((key: string) => {
            if (data[key] != undefined) formData[key] = data[key]
        })
        loading.value = false
    } else {
        loading.value = false
    }
}
if (id) setFormData(id)

const gethelpCategoryListFn = () =>{
		gethelpCategoryList().then((res)=>{
			res.data.data.forEach((item,index)=>{
				let obj = {
					label:item.category_name,
					value:item.category_id
				}
				categoryList.value.push(obj)
			})
		})
	}
	gethelpCategoryListFn()
const formRef = ref<FormInstance>()

// 表单验证规则
const formRules = computed(() => {
    return {
        name: [
            { required: true, message: t('titlePlaceholder'), trigger: 'blur' }
        ],
        category_id: [
            { required: true, message: t('categoryIdPlaceholder'), trigger: 'blur' }
        ],
		type: [
		    { required: true, message: t('typePlaceholder'), trigger: 'blur' }
		],
        content: [
            { required: true, message: t('contentPlaceholder'), trigger: 'blur' },
            {
                validator: (rule: any, value: string, callback: any) => {
                    const content = value.replace(/<[^<>]+>/g, '').replace(/&nbsp;/gi, '')
                    if (!content && value.indexOf('img') === -1) {
                        callback(new Error(t('contentPlaceholder')))
                    } else callback()
                },
                trigger: ['blur', 'change']
            }
        ]
    }
})

const onSave = async (formEl: FormInstance | undefined) => {
    if (loading.value || !formEl) return
    await formEl.validate(async (valid) => {
        if (valid) {
            loading.value = true
            const data = formData
            const save = id ? edithelp : addhelp
			
			console.log(data)
            save(data).then(res => {
                loading.value = false
                back()
            }).catch(() => {
                loading.value = false
            })
        }
    })
}

const back = () => {
    router.push({ path: '/home_service/help/list' })
}
</script>

<style lang="scss" scoped>
.edui-default .edui-editor {
    border: none!important;
    z-index: 1!important;
}
</style>
