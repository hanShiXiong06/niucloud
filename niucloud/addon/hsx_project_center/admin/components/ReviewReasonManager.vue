<template>
    <el-dialog v-model="visible" title="常用退回问题设置" width="760px" destroy-on-close>
        <el-alert type="info" :closable="false" title="这些话术只用于当前项目。审核时可直接选择，也可以继续修改后再发送给客户。" />
        <div class="mt-[16px] flex items-center justify-between">
            <div>
                <div class="font-medium text-[#344054]">{{ projectTitle || '当前项目' }}</div>
                <div class="mt-[3px] text-[12px] text-[#98a2b3]">建议保留 3～10 条真正高频的问题，避免下拉内容过多。</div>
            </div>
            <el-button type="primary" plain @click="addReason">新增问题</el-button>
        </div>

        <div v-loading="loading" class="reason-list">
            <el-empty v-if="!rows.length && !loading" description="暂无常用问题，点击右上角新增" />
            <div v-for="(item, index) in rows" :key="item.id || index" class="reason-card">
                <div class="reason-index">{{ index + 1 }}</div>
                <div class="min-w-0 flex-1">
                    <div class="grid grid-cols-2 gap-[10px]">
                        <el-input v-model="item.title" maxlength="50" placeholder="下拉标题，例如：图片不清晰" />
                        <el-input-number v-model="item.sort" class="!w-full" :min="-9999" :max="9999" controls-position="right" placeholder="排序" />
                    </div>
                    <el-input v-model="item.message" class="mt-[10px]" type="textarea" :rows="2" maxlength="500" show-word-limit placeholder="客户需要修改的问题说明（必填）" />
                    <el-input v-model="item.example" class="mt-[10px]" type="textarea" :rows="2" maxlength="500" show-word-limit placeholder="正确示例或修改建议（选填）" />
                </div>
                <div class="flex flex-col gap-[6px]">
                    <el-button link :disabled="index === 0" @click="move(index, -1)">上移</el-button>
                    <el-button link :disabled="index === rows.length - 1" @click="move(index, 1)">下移</el-button>
                    <el-button link type="danger" @click="remove(index)">删除</el-button>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-[10px]">
                <el-button @click="visible = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="save">保存配置</el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getProjectCenterReviewReasons, saveProjectCenterReviewReasons } from '@/addon/hsx_project_center/api'

const emit = defineEmits(['saved'])
const visible = ref(false)
const loading = ref(false)
const saving = ref(false)
const projectId = ref(0)
const projectTitle = ref('')
const rows = ref<any[]>([])

const newId = () => `reason_${Date.now()}_${Math.random().toString(16).slice(2, 8)}`

async function load() {
    loading.value = true
    try {
        const res:any = await getProjectCenterReviewReasons(projectId.value)
        rows.value = JSON.parse(JSON.stringify(res.data || []))
    } finally {
        loading.value = false
    }
}

async function open(id:number, title = '') {
    projectId.value = Number(id || 0)
    projectTitle.value = String(title || '')
    visible.value = true
    await load()
}

function addReason() {
    rows.value.push({ id: newId(), title: '', message: '', example: '', sort: rows.value.length ? Math.min(...rows.value.map(item => Number(item.sort || 0))) - 10 : 100 })
}

async function remove(index:number) {
    await ElMessageBox.confirm('确认删除这条常用问题吗？已经产生的历史审核记录不会受影响。', '删除常用问题', { type: 'warning' })
    rows.value.splice(index, 1)
}

function move(index:number, offset:number) {
    const target = index + offset
    if (target < 0 || target >= rows.value.length) return
    const current = rows.value[index]
    rows.value[index] = rows.value[target]
    rows.value[target] = current
    rows.value.forEach((item, idx) => { item.sort = (rows.value.length - idx) * 10 })
}

async function save() {
    const empty = rows.value.find(item => !String(item.message || '').trim())
    if (empty) return ElMessage.warning('请填写完整的问题说明，空白项可直接删除')
    saving.value = true
    try {
        const res:any = await saveProjectCenterReviewReasons(projectId.value, rows.value)
        rows.value = JSON.parse(JSON.stringify(res.data || []))
        ElMessage.success('常用问题已保存')
        visible.value = false
        emit('saved', rows.value)
    } finally {
        saving.value = false
    }
}

defineExpose({ open })
</script>

<style scoped>
.reason-list { max-height: 58vh; overflow-y: auto; margin-top: 14px; padding-right: 4px; }
.reason-card { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 10px; padding: 14px; border: 1px solid #e7ecf3; border-radius: 12px; background: #fff; }
.reason-index { display: flex; width: 26px; height: 26px; flex: none; align-items: center; justify-content: center; border-radius: 8px; background: #eef4ff; color: #155eef; font-size: 12px; font-weight: 600; }
</style>
