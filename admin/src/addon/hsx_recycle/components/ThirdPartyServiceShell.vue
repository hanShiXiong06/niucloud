<template>
    <PremiumTheme class="service-page">
        <el-card class="!border-none" shadow="never">
            <PageHeader :title="title" :description="description">
                <template #actions>
                    <el-button :loading="loading" @click="$emit('refresh')">刷新</el-button>
                    <el-button v-if="testable" :loading="testing" @click="$emit('test')">测试连接</el-button>
                    <el-button type="primary" :loading="saving" @click="$emit('save')">保存配置</el-button>
                </template>
            </PageHeader>

            <div class="status-strip">
                <div class="status-main">
                    <div class="status-icon"><el-icon><Connection /></el-icon></div>
                    <div>
                        <div class="status-title">{{ providerLabel }}</div>
                        <div class="status-desc">{{ statusText }}</div>
                    </div>
                </div>
                <div class="status-data">
                    <div><span>服务状态</span><el-tag :type="statusMeta.type">{{ statusMeta.label }}</el-tag></div>
                    <div><span>今日调用</span><strong>{{ capability?.today?.total_calls || 0 }}</strong></div>
                    <div><span>今日失败</span><strong :class="{ danger: Number(capability?.today?.failed_calls || 0) > 0 }">{{ capability?.today?.failed_calls || 0 }}</strong></div>
                    <div><span>平均耗时</span><strong>{{ capability?.today?.avg_duration || 0 }}ms</strong></div>
                </div>
            </div>

            <div class="service-layout" v-loading="loading">
                <div class="service-main"><slot /></div>
                <aside class="service-aside">
                    <section v-if="features.length" class="aside-card">
                        <h3>服务能力</h3>
                        <div class="feature-list">
                            <div v-for="item in features" :key="item"><el-icon><CircleCheck /></el-icon>{{ item }}</div>
                        </div>
                    </section>
                    <section v-if="steps.length" class="aside-card">
                        <h3>使用流程</h3>
                        <div v-for="(step, index) in steps" :key="step" class="flow-item">
                            <span>{{ index + 1 }}</span><p>{{ step }}</p>
                        </div>
                    </section>
                    <slot name="aside" />
                </aside>
            </div>
        </el-card>
    </PremiumTheme>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { CircleCheck, Connection } from '@element-plus/icons-vue'
import PremiumTheme from '@/addon/hsx_recycle/components/PremiumTheme.vue'
import PageHeader from '@/addon/hsx_recycle/components/PageHeader.vue'

const props = withDefaults(defineProps<{
    title: string
    description: string
    providerLabel: string
    capability?: any
    loading?: boolean
    saving?: boolean
    testing?: boolean
    testable?: boolean
    features?: string[]
    steps?: string[]
}>(), {
    capability: () => ({}),
    loading: false,
    saving: false,
    testing: false,
    testable: true,
    features: () => [],
    steps: () => []
})

defineEmits(['refresh', 'save', 'test'])

const statusMeta = computed(() => ({
    running: { label: '运行中', type: 'success' },
    ready: { label: '配置就绪', type: 'success' },
    error: { label: '最近失败', type: 'danger' },
    incomplete: { label: '待补全', type: 'warning' },
    disabled: { label: '已停用', type: 'info' }
} as Record<string, any>)[props.capability?.status] || { label: '读取中', type: 'info' })

const statusText = computed(() => {
    if (props.capability?.missing_fields?.length) return `缺少配置：${props.capability.missing_fields.join('、')}`
    if (props.capability?.last_call?.error_msg) return `最近失败：${props.capability.last_call.error_msg}`
    return props.capability?.enabled === 0 ? '服务已停用，业务流程不会调用此能力' : '配置完成后，业务流程将按当前指定服务商执行'
})
</script>

<style scoped lang="scss">
.service-page { padding: 20px; }
.status-strip { display: flex; justify-content: space-between; gap: 24px; margin-top: 20px; padding: 18px 20px; border: 1px solid #e5e7eb; border-radius: 10px; background: linear-gradient(135deg, #f8fbff 0%, #fff 72%); }
.status-main { display: flex; align-items: center; gap: 12px; min-width: 260px; }
.status-icon { display: flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 10px; color: var(--el-color-primary); background: #eaf2ff; font-size: 22px; }
.status-title { color: #111827; font-size: 15px; font-weight: 700; }
.status-desc { max-width: 520px; margin-top: 5px; color: #64748b; font-size: 12px; line-height: 1.5; }
.status-data { display: grid; grid-template-columns: repeat(4, minmax(90px, 1fr)); gap: 24px; }
.status-data > div { display: flex; flex-direction: column; justify-content: center; gap: 7px; }
.status-data span { color: #94a3b8; font-size: 12px; }
.status-data strong { color: #111827; font-size: 17px; }.status-data strong.danger { color: #dc2626; }
.service-layout { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 18px; margin-top: 18px; }
.service-main { min-width: 0; padding: 20px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; }
.service-aside { display: flex; flex-direction: column; gap: 14px; }
.aside-card { padding: 18px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; }
.aside-card h3 { margin: 0 0 14px; color: #111827; font-size: 14px; }
.feature-list { display: flex; flex-direction: column; gap: 11px; }.feature-list > div { display: flex; align-items: center; gap: 8px; color: #475569; font-size: 13px; }.feature-list .el-icon { color: #16a34a; }
.flow-item { display: flex; align-items: flex-start; gap: 10px; margin-top: 12px; }.flow-item span { display: flex; align-items: center; justify-content: center; flex: 0 0 22px; height: 22px; border-radius: 50%; color: var(--el-color-primary); background: #eff6ff; font-size: 12px; font-weight: 700; }.flow-item p { margin: 1px 0 0; color: #64748b; font-size: 12px; line-height: 1.55; }
@media (max-width: 1200px) { .status-strip { flex-direction: column; }.service-layout { grid-template-columns: 1fr; }.service-aside { display: grid; grid-template-columns: repeat(2, 1fr); } }
</style>
