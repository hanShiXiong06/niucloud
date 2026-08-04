<template>
    <el-dialog
        v-model="dialogVisible"
        title="新增并关联型号"
        width="520px"
        destroy-on-close
        append-to-body
        @open="resetForm"
    >
        <el-alert
            type="info"
            :closable="false"
            show-icon
            title="仅在型号库确实没有该设备时新增。保存后会立即关联到当前设备。"
            class="quick-model-alert"
        />
        <el-form label-width="88px" @submit.prevent>
            <el-form-item label="上级分类" required>
                <el-cascader
                    v-model="form.parent_path"
                    :props="parentProps"
                    placeholder="选择品类、品牌或系列"
                    clearable
                    class="quick-model-field"
                />
                <div class="quick-model-tip">安卓设备通常选择到对应品牌或系列，再新增具体型号。</div>
            </el-form-item>
            <el-form-item label="型号名称" required>
                <el-input
                    v-model="form.node_name"
                    maxlength="100"
                    show-word-limit
                    placeholder="例如 Galaxy S25 Ultra"
                    @keyup.enter="submit"
                />
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="dialogVisible = false">取消</el-button>
            <el-button type="primary" :loading="saving" @click="submit">新增并关联</el-button>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { ensureRecycleDeviceModelDictChild, getRecycleDeviceModelDictChildren } from '@/addon/hsx_recycle/api/recycle_device_model_dict'

const props = withDefaults(defineProps<{
    visible: boolean
    suggestedName?: string
}>(), {
    suggestedName: ''
})

const emit = defineEmits<{
    (e: 'update:visible', value: boolean): void
    (e: 'created', value: Record<string, any>): void
}>()

const dialogVisible = computed({
    get: () => props.visible,
    set: value => emit('update:visible', value)
})
const saving = ref(false)
const form = reactive<{ parent_path: Array<number | string>; node_name: string }>({
    parent_path: [],
    node_name: ''
})

const parentProps = {
    value: 'id',
    label: 'node_name',
    emitPath: true,
    checkStrictly: true,
    lazy: true,
    lazyLoad: async (node: any, resolve: (nodes: any[]) => void) => {
        const pid = node && node.level > 0 ? node.value : 0
        try {
            const res = await getRecycleDeviceModelDictChildren({ pid, status: 1, limit: 300 })
            resolve((res.data || []).map((item: any) => ({
                ...item,
                leaf: Number(item.has_children || 0) !== 1
            })))
        } catch (error) {
            console.error('加载型号上级分类失败:', error)
            resolve([])
        }
    }
}

const resetForm = () => {
    form.parent_path = []
    form.node_name = String(props.suggestedName || '').trim()
}

const submit = async () => {
    const parentId = Number(form.parent_path[form.parent_path.length - 1] || 0)
    const nodeName = String(form.node_name || '').trim()
    if (!parentId) {
        ElMessage.warning('请选择型号所属的上级分类或系列')
        return
    }
    if (!nodeName) {
        ElMessage.warning('请输入型号名称')
        return
    }
    saving.value = true
    try {
        const res = await ensureRecycleDeviceModelDictChild({ parent_id: parentId, node_name: nodeName })
        emit('created', res.data || {})
        dialogVisible.value = false
    } finally {
        saving.value = false
    }
}
</script>

<style lang="scss" scoped>
.quick-model-alert { margin-bottom: 18px; }
.quick-model-field { width: 100%; }
.quick-model-tip {
    margin-top: 5px;
    color: var(--el-text-color-secondary);
    font-size: 12px;
    line-height: 18px;
}
</style>
