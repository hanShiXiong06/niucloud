<template>
    <div class="main-container" v-loading="loading">

        <!--返回-->
        <el-card class="card !border-none" shadow="never">
            <el-page-header :content="id ? t('editLevel') : t('addLevel')" :icon="ArrowLeft" @back="back" />
        </el-card>
        <!--返回 end-->

        <el-card class="card mt-[15px] !border-none" shadow="never">
            <el-form class="page-form" :model="formData" label-width="130px" ref="formRef" :rules="formRules" v-if="!loading">
                <div class="text text-[14px] leading-[25px]">{{ t('levelInfo') }}</div>
                <el-card class="card !border-none" shadow="never">
                    <el-form-item v-if="formData.is_default == '0'" :label="t('levelWeight')" prop="level_num">
                        <div>
                            <div class="flex">
                                <template v-for="(item, index) in config.levelWeightList" :key="item.id">
                                    <div v-if="config.levelWeightDisableList.includes(item.id) && item.id != level_num"
                                        class="w-[62px] h-[32px] mr-[10px] leading-[32px] text-center bg-[var(--el-color-info-light-8)] rounded-[4px] cursor-not-allowed">
                                        {{ item.name }}</div>
                                    <div v-else
                                        class="w-[62px] h-[32px] mr-[10px] leading-[30px] text-center border-[1px] border-solid border-[var(--el-border-color)] rounded-[4px] cursor-pointer"
                                        :class="{ '!border-[var(--el-color-primary)]': item.id == formData.level_num }"
                                        @click="levelWeightChange(item.id)">{{ item.name }}</div>
                                    <el-input style="display: none;" v-model.trim="formData.level_num" clearable class="input-width" />
                                </template>
                            </div>
                        </div>
                    </el-form-item>
                    <el-form-item :label="t('levelName')" prop="level_name">
                        <el-input v-model.trim="formData.level_name" maxlength="25" show-word-limit clearable :placeholder="t('levelNamePlaceholder')" class="input-width" />
                    </el-form-item>
                    <el-form-item :label="t('oneRate')" prop="order_rate">
                        <div>
                            <el-input v-model.trim="formData.order_rate" maxlength="6" clearable class="input-width" @keyup="filterDigit($event)">
                                <template #append>%</template>
                            </el-input>
                            <p class="text-[var(--el-text-color-secondary)] text-[12px] leading-[25px]">{{ t('oneRatePlaceholder') }}</p>
                        </div>
                    </el-form-item>
					<template v-if="formData.is_default == '0'">
					    <div class="text text-[14px] leading-[25px] py-[20px]">{{ t('titleTwo') }}</div>
							<el-form-item :label="t('tjOne')" prop="achievement">
							    <div>
							       完成业绩满 <el-input v-model.trim="formData.achievement" maxlength="6" clearable class="input-width ml-1" @keyup="filterDigit($event)">
							            <template #append>元</template>
							        </el-input>
							        <p class="text-[var(--el-text-color-secondary)] text-[12px] leading-[25px]">{{ t('tjOnePlaceholder') }}</p>
							    </div>
							</el-form-item>
							<el-form-item :label="t('tjtwo')" prop="order_num" >
							    <div>
							        服务订单数达到<el-input v-model.trim="formData.order_num" clearable maxlength="6" class="input-width ml-1" >
							            <template #append>个</template>
							        </el-input>
									<p class="text-[var(--el-text-color-secondary)] text-[12px] leading-[25px]">{{ t('师傅服务订单数达到规定数量') }}</p>
							    </div>
							</el-form-item>
					</template>
                </el-card>
                
            </el-form>
        </el-card>

        <div class="fixed-footer-wrap">
            <div class="fixed-footer">
                <el-button type="primary" @click="save()">{{ t('save') }}</el-button>
                <el-button @click="back()">{{ t('back') }}</el-button>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, computed } from "vue";
import { t } from "@/lang";
import { getLevelDetail, getFenxiaoLevelNum, addFenxiaoLevel, editFenxiaoLevel } from '@/addon/home_service/api/level'
import { FormInstance } from 'element-plus'
import { ArrowLeft } from '@element-plus/icons-vue'
import { useRoute, useRouter } from 'vue-router'
import { filterDigit, filterNumber } from '@/utils/common'

const route = useRoute();
const router = useRouter();
const pageName = route.meta.title;

const config = ref<any>({
    levelWeightList: [
        { id: 1, name: '一级' },
        { id: 2, name: '二级' },
        { id: 3, name: '三级' },
        { id: 4, name: '四级' },
        { id: 5, name: '五级' },
        { id: 6, name: '六级' },
        { id: 7, name: '七级' },
        { id: 8, name: '八级' },
        { id: 9, name: '九级' },
        { id: 10, name: '十级' }
    ],
    levelWeightDisableList: [],
    cardList: []
})
/**
 * 表单数据
 */
const initialFormData = {
    id: 0,
    is_default: '0',
    level_num: 0,
    level_name: '',
    order_rate: '',
    additional_rate: '',
    upgrade_type: '1',
    card_ids: [],
	achievement:'',
	order_num:''
}
const formData: Record<string, any> = reactive({ ...initialFormData });
const loading = ref<Boolean>(false)
const level_num = ref(0)
// 正则表达式
const regExp = {
    required: /[\S]+/,
    number: /^\d{0,10}$/,
    digit: /^\d{0,10}(.?\d{0,2})$/,
    special: /^\d{0,10}(.?\d{0,3})$/
}
const oneRateCheck = (rule: any, value: any, callback: any) => {
    if (!value) {
        return callback(new Error(t('oneRatePlaceholderOne')))
    } else if (!regExp.digit.test(value)) {
        return callback(new Error(t('oneRatePlaceholderTwo')))
    } else if (value >= 100) {
        return callback(new Error(t('oneRatePlaceholderThree')))
    } else if (value < 0) {
        return callback(new Error(t('oneRatePlaceholderFour')))
    } else {
        return callback()
    }
}
const twoRateCheck = (rule: any, value: any, callback: any) => {
    if (!value) {
        return callback(new Error(t('twoRatePlaceholderOne')))
    } else if (!regExp.digit.test(value)) {
        return callback(new Error(t('twoRatePlaceholderTwo')))
    } else if (value >= 100) {
        return callback(new Error(t('twoRatePlaceholderThree')))
    } else if (value < 0) {
        return callback(new Error(t('twoRatePlaceholderFour')))
    } else {
        return callback()
    }
}
const levelNum = (rule: any, value: any, callback: any) => {
    if (!value) {
        return callback(new Error(t('levelWeightPlaceholder')))
    } else {
        return callback()
    }
}
const formRules = computed(() => {
    return {
        level_num: [{ required: true, validator: levelNum, trigger: 'change' }],
        level_name: [{ required: true, message: t('levelNamePlaceholder'), trigger: 'blur' }],
        order_rate: [{ required: true, validator: oneRateCheck, trigger: 'blur' }],
        additional_rate: [{ required: true, validator: twoRateCheck, trigger: 'blur' }],
        card_ids: [{ type: 'array', required: true, message: t('upgradeMethodPlaceholder'), trigger: 'change' }],
		order_num: [{ required: true, message: t('tjtwoPlaceholderInpt'), trigger: 'blur' }],
		achievement: [{ required: true, message: t('tjOnePlaceholderInpt'), trigger: 'blur' }],
    }
})
const formRef = ref<FormInstance>()

const levelWeightChange = (id: Number) => {
    formData.level_num = id
}
const upgradeMethodChange = (item: any) => {
    item.is_checkbox = item.is_checkbox ? 0 : 1

    if (item.is_checkbox) {
        formData.card_ids.push(item.card_id)
    } else {
        let index = formData.card_ids.indexOf(item.card_id)
        formData.card_ids.splice(index, 1)
    }
    formRef.value?.validateField('card_ids')
}
const upgradeMethodDelete = (item: any) => {
    item.is_checkbox = 0
    let index = formData.card_ids.indexOf(item.card_id)
    formData.card_ids.splice(index, 1)
    formRef.value?.validateField('card_ids')
}
const level = ref(1)
//获取详情
const getLevelDetailFn = (id: any) => {
    loading.value = true
    getLevelDetail(formData.id).then((res) => {
        Object.keys(formData).forEach((key: any) => {
            if (res.data[key] != undefined) formData[key] = res.data[key].toString()
        })
        Object.values(config.value.cardList).forEach((el: any) => {
            if (res.data[el.key] != undefined) {
                formData[el.key] = res.data[el.key]
                if (!formData.card_ids.includes(el.card_id) && el.is_checkbox) {
                    formData.card_ids.push(el.card_id)
                }
            }
        })
        level_num.value = res.data.level_num
        loading.value = false
    }).catch(() => {
        loading.value = false
    })
}
formData.id = route.query.id || 0
getLevelDetailFn()
//获取分销等级权重已设置列表
const getFenxiaoLevelNumFn = () => {
    loading.value = true
    getFenxiaoLevelNum().then((res: any) => {
        config.value.levelWeightDisableList = res.data.map((el: any) => el.level_num)
    }).catch(() => {
        loading.value = false
    })
}
getFenxiaoLevelNumFn()
const repeat = ref<boolean>(false)
const save = () => {
    formRef.value?.validate((valid) => {
        if (valid) {
            if (repeat.value) return
            repeat.value = true
            let api = formData.id ? editFenxiaoLevel : addFenxiaoLevel
            Object.values(config.value.cardList).forEach((el: any) => {
                if (!el.is_checkbox) delete formData[el.key]
            })
            api(formData).then((res) => {
                repeat.value = false
                back()
            }).catch(() => {
                repeat.value = false
            })
        }
    })
}
const back = () => {
    router.push('/home_service/technician/level')
};
</script>

<style lang="scss" scoped>
    .el-form-item {

        .el-form-item {
            margin-bottom: 18px;
        }
    }

    .el-input.el-input-group--append {
        width: 150px;
    }
</style>
