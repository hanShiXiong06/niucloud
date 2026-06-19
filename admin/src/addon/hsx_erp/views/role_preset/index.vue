<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-lg font-medium">角色与权限</span>
                    <div class="mt-1 text-sm text-gray-500">一键生成 5 个推荐角色并预设好权限；生成后可在「系统设置 → 角色管理」自由增删调整。</div>
                </div>
                <el-button
                    v-permission="'hsx_erp_role_preset_generate'"
                    type="primary"
                    :loading="generating"
                    @click="onGenerate"
                >一键生成推荐角色</el-button>
            </div>

            <el-alert class="mt-4" type="info" :closable="false"
                title="说明：按角色把『能看到的菜单 + 能点的按钮 + 能查的数据』打包好。员工分配对应角色后，没权限的按钮直接不显示。已存在的同名角色会用推荐配置更新（不影响已分配的人）。" />

            <el-table :data="list" v-loading="loading" class="mt-4" empty-text="暂无数据">
                <el-table-column prop="role_name" label="推荐角色" width="140">
                    <template #default="{ row }">
                        <span class="font-medium">{{ row.role_name }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="desc" label="职责说明" min-width="320" show-overflow-tooltip />
                <el-table-column label="权限点数" width="110" align="center">
                    <template #default="{ row }">{{ row.key_count }}</template>
                </el-table-column>
                <el-table-column label="状态" width="120" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.exists" type="success" effect="light">已存在</el-tag>
                        <el-tag v-else type="info" effect="light">待生成</el-tag>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getRolePresetPreview, generateRolePreset } from '@/addon/hsx_erp/api/role_preset'

const loading = ref(false)
const generating = ref(false)
const list = ref<any[]>([])

const load = async () => {
    loading.value = true
    try {
        const res: any = await getRolePresetPreview()
        list.value = Array.isArray(res?.data) ? res.data : []
    } finally {
        loading.value = false
    }
}

const onGenerate = async () => {
    const hasExist = list.value.some((r) => r.exists)
    await ElMessageBox.confirm(
        hasExist
            ? '将创建缺失的推荐角色，并用推荐配置更新已存在的同名角色。继续？'
            : '将创建 5 个推荐角色并预设好权限。继续？',
        '一键生成推荐角色',
        { type: 'warning' },
    )
    generating.value = true
    try {
        const res: any = await generateRolePreset({ overwrite: 1 })
        const d = res?.data || {}
        const parts: string[] = []
        if (d.created?.length) parts.push(`新建 ${d.created.length} 个`)
        if (d.updated?.length) parts.push(`更新 ${d.updated.length} 个`)
        if (d.skipped?.length) parts.push(`跳过 ${d.skipped.length} 个`)
        ElMessage.success('完成：' + (parts.join('，') || '无变化') + '。去「角色管理」给员工分配角色即可')
        await load()
    } finally {
        generating.value = false
    }
}

onMounted(load)
</script>
