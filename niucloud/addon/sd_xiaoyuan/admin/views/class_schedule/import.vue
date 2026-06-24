<template>
    <div class="import-container">
        <el-card>
            <template #header>
                <span>导入班级课表</span>
            </template>

            <el-form :model="importForm" label-width="120px">
                <el-form-item label="选择学校" required>
                    <el-select v-model="importForm.school_id" placeholder="请选择学校">
                        <el-option v-for="s in schoolList" :key="s.id" :label="s.name" :value="s.id" />
                    </el-select>
                </el-form-item>

                <el-form-item label="文件格式说明">
                    <div class="file-tip">支持 CSV/TSV/Excel 文件，表头需包含：nj, bjdm, bjmc, weekday, jcxx, xn, xq_meta, kcmc, rkjs, skdd, skbj, xf, skzs</div>
                </el-form-item>

                <el-form-item label="上传文件">
                    <el-upload
                        ref="uploadRef"
                        :auto-upload="false"
                        :limit="1"
                        accept=".csv,.tsv,.xlsx,.xls,.txt"
                        :on-change="handleFileChange"
                        :on-remove="handleFileRemove"
                    >
                        <el-button type="primary">选择文件</el-button>
                    </el-upload>
                </el-form-item>

                <el-form-item>
                    <el-button type="success" @click="handleImport" :loading="importing" :disabled="!selectedFile || !importForm.school_id">
                        开始导入
                    </el-button>
                    <el-button @click="goBack">返回</el-button>
                </el-form-item>
            </el-form>

            <el-result v-if="importResult" icon="success" title="导入完成">
                <template #sub-title>
                    <p>新建班级：{{ importResult.class_count }} 个</p>
                    <p>导入课程：{{ importResult.course_count }} 条</p>
                    <p>跳过记录：{{ importResult.skip_count }} 条</p>
                </template>
                <template #extra>
                    <el-button type="primary" @click="goBack">返回课表列表</el-button>
                </template>
            </el-result>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { importClassSchedule } from '@/addon/sd_xiaoyuan/api/schoolClass'
import { getAllSchools } from '@/addon/sd_xiaoyuan/api/school'

const router = useRouter()
const schoolList = ref<any[]>([])
const selectedFile = ref<any>(null)
const importing = ref(false)
const importResult = ref<any>(null)

const importForm = reactive({
    school_id: 0
})

onMounted(() => {
    loadSchools()
})

const loadSchools = async () => {
    try {
        const res: any = await getAllSchools()
        schoolList.value = res.data || []
    } catch (e) { console.error(e) }
}

const handleFileChange = (file: any) => {
    selectedFile.value = file.raw
}

const handleFileRemove = () => {
    selectedFile.value = null
}

const handleImport = async () => {
    if (!importForm.school_id) {
        ElMessage.warning('请选择学校')
        return
    }
    if (!selectedFile.value) {
        ElMessage.warning('请先上传文件')
        return
    }

    const formData = new FormData()
    formData.append('file', selectedFile.value)
    formData.append('school_id', String(importForm.school_id))

    importing.value = true
    try {
        const res: any = await importClassSchedule(formData)
        importResult.value = res.data
        ElMessage.success('导入完成')
    } catch (e: any) {
        ElMessage.error(e.message || '导入失败')
    } finally {
        importing.value = false
    }
}

const goBack = () => {
    router.push({ path: '/sd_xiaoyuan/class_schedule/list' })
}
</script>

<style lang="scss" scoped>
.import-container { padding: 20px; }
.file-tip {
    color: #909399;
    font-size: 13px;
    line-height: 1.6;
}
</style>
