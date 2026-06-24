<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never" v-loading="loading">
            <div class="text-page-title">收款设置</div>
            <div class="mt-1 text-sm text-gray-500">控制销售/非财务能否当场收款,还是只生成应收应付、由财务统一清账。</div>

            <el-form class="mt-6" label-width="160px" style="max-width: 720px">
                <el-form-item label="允许当场收款(现结)">
                    <el-switch v-model="form.allow_instant_settle" :active-value="1" :inactive-value="0" />
                    <div class="mt-1 text-xs text-gray-400">
                        开启:销售/非财务可在收银台、出库处理挂单时<b>当场收款清账</b>。<br />
                        关闭:下单只生成<b>应收 / 应付</b>,销售只能填备注做参考,最终清账由<b>财务</b>在财务中心完成。
                    </div>
                </el-form-item>
            </el-form>

            <div class="mt-2 pl-[160px]">
                <el-button type="primary" :loading="saving" @click="onSave">保存</el-button>
            </div>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue'
import { getErpConfig, saveErpConfig } from '@/addon/hsx_erp/api/config'

const loading = ref(false)
const saving = ref(false)
const form = reactive<{ allow_instant_settle: number }>({ allow_instant_settle: 1 })

const load = async () => {
    loading.value = true
    try {
        const res: any = await getErpConfig()
        const d = res?.data || {}
        form.allow_instant_settle = Number(d.allow_instant_settle ?? 1)
    } finally {
        loading.value = false
    }
}

const onSave = async () => {
    saving.value = true
    try {
        await saveErpConfig({ allow_instant_settle: form.allow_instant_settle })
        await load()
    } finally {
        saving.value = false
    }
}

onMounted(load)
</script>
