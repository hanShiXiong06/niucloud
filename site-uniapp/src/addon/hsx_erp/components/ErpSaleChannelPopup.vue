<template>
    <u-popup :show="show" mode="bottom" :safe-area-inset-bottom="true" border-radius="32rpx" @close="close">
        <view class="channel-popup">
            <view class="channel-header">
                <text class="channel-title">销售渠道</text>
                <u-icon name="close" size="20" color="#94a3b8" @click="close" />
            </view>

            <view class="channel-add">
                <view class="channel-input-wrap">
                    <u-input
                        v-model="draftName"
                        placeholder="新增渠道，如：门店/同行/小程序"
                        border="none"
                        clearable
                        :customStyle="inputStyle"
                    />
                </view>
                <view class="channel-btn-wrap">
                    <u-button size="small" type="primary" icon="plus" :loading="saving" @click="addChannel">新增</u-button>
                </view>
            </view>

            <view class="channel-list">
                <u-radio-group v-model="selectedChannel" placement="column" @change="selectChannel">
                    <view
                        v-for="(item, index) in channels"
                        :key="item"
                        class="channel-item"
                        :class="{ selected: item === modelValue }"
                    >
                        <view class="channel-main" v-if="editingIndex !== index" @click="selectChannel(item)">
                            <u-radio
                                :name="item"
                                :label="item"
                                activeColor="#3b6ef5"
                                labelColor="#0f172a"
                                :labelSize="'28rpx'"
                            />
                        </view>
                        <view class="channel-edit" v-else>
                            <u-input v-model="editingName" border="none" placeholder="请输入渠道名称" :customStyle="inputStyle" />
                        </view>
                        <view class="channel-actions" @click.stop>
                            <template v-if="editingIndex === index">
                                <u-button size="mini" type="primary" :loading="saving" @click="saveEdit(index)">保存</u-button>
                                <u-button size="mini" plain @click="cancelEdit">取消</u-button>
                            </template>
                            <template v-else>
                                <u-button size="mini" plain @click="startEdit(index)">编辑</u-button>
                                <u-button size="mini" plain type="error" @click="removeChannel(index)">删除</u-button>
                            </template>
                        </view>
                    </view>
                </u-radio-group>
                <view v-if="!channels.length && !loading" class="channel-empty">暂无渠道，请先新增</view>
                <view v-if="loading" class="channel-loading"><u-loading-icon size="24" /></view>
            </view>
        </view>
    </u-popup>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { getErpSaleChannels, saveErpSaleChannels } from '@/addon/hsx_erp/api/erp'

const props = withDefaults(defineProps<{
    show: boolean
    modelValue?: string
}>(), {
    show: false,
    modelValue: '',
})

const emit = defineEmits<{
    (e: 'update:show', v: boolean): void
    (e: 'update:modelValue', v: string): void
}>()

const loading = ref(false)
const saving = ref(false)
const channels = ref<string[]>([])
const draftName = ref('')
const selectedChannel = ref(props.modelValue)
const editingIndex = ref(-1)
const editingName = ref('')
const inputStyle = { width: '100%', background: 'transparent', padding: '0 8rpx', boxSizing: 'border-box' }

watch(() => props.show, (v) => {
    if (v) loadChannels()
})
watch(() => props.modelValue, (v) => {
    selectedChannel.value = v
})

async function loadChannels() {
    loading.value = true
    try {
        const res: any = await getErpSaleChannels()
        channels.value = normalizeChannels(res?.data || [])
    } finally {
        loading.value = false
    }
}

async function persist(next: string[]) {
    saving.value = true
    try {
        channels.value = normalizeChannels(next)
        const res: any = await saveErpSaleChannels(channels.value)
        channels.value = normalizeChannels(res?.data || channels.value)
    } finally {
        saving.value = false
    }
}

async function addChannel() {
    const name = draftName.value.trim()
    if (!name) {
        uni.showToast({ title: '请输入渠道名称', icon: 'none' })
        return
    }
    if (channels.value.includes(name)) {
        uni.showToast({ title: '渠道已存在', icon: 'none' })
        return
    }
    await persist([...channels.value, name])
    draftName.value = ''
    emit('update:modelValue', name)
    selectedChannel.value = name
}

function startEdit(index: number) {
    editingIndex.value = index
    editingName.value = channels.value[index]
}

async function saveEdit(index: number) {
    const oldName = channels.value[index]
    const name = editingName.value.trim()
    if (!name) {
        uni.showToast({ title: '请输入渠道名称', icon: 'none' })
        return
    }
    const duplicated = channels.value.some((item, i) => i !== index && item === name)
    if (duplicated) {
        uni.showToast({ title: '渠道已存在', icon: 'none' })
        return
    }
    const next = [...channels.value]
    next[index] = name
    await persist(next)
    if (props.modelValue === oldName) {
        emit('update:modelValue', name)
        selectedChannel.value = name
    }
    cancelEdit()
}

function cancelEdit() {
    editingIndex.value = -1
    editingName.value = ''
}

function removeChannel(index: number) {
    const name = channels.value[index]
    uni.showModal({
        title: '删除渠道',
        content: `确认删除「${name}」？`,
        confirmText: '删除',
        confirmColor: '#dc2626',
        success: async (res) => {
            if (!res.confirm) return
            const next = channels.value.filter((_, i) => i !== index)
            await persist(next)
            if (props.modelValue === name) {
                emit('update:modelValue', '')
                selectedChannel.value = ''
            }
        }
    })
}

function selectChannel(name: string) {
    if (editingIndex.value >= 0) return
    selectedChannel.value = name
    emit('update:modelValue', name)
    close()
}

function close() {
    emit('update:show', false)
}

function normalizeChannels(rows: any[]) {
    const seen = new Set<string>()
    return rows.map(row => typeof row === 'string' ? row : row?.name || row?.label || row?.value || '')
        .map(row => String(row).trim())
        .filter(row => {
            if (!row || seen.has(row)) return false
            seen.add(row)
            return true
        })
}
</script>

<style scoped lang="scss">
.channel-popup { min-height: 55vh; max-height: 80vh; padding: 28rpx 32rpx; box-sizing: border-box; display: flex; flex-direction: column; }
.channel-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:20rpx; }
.channel-title { font-size:32rpx; font-weight:700; color:#0f172a; }
.channel-add { display:flex; align-items:center; gap:12rpx; margin-bottom:20rpx; }
.channel-input-wrap { flex:1; min-width:0; height:72rpx; background:#f8fafc; border:1rpx solid #e2e8f0; border-radius:10rpx; display:flex; align-items:center; padding:0 12rpx; box-sizing:border-box; }
.channel-input-wrap :deep(.u-input) { width:100%; flex:1; }
.channel-input-wrap :deep(.u-input__content) { width:100%; flex:1; }
.channel-list { flex:1; overflow-y:auto; }
.channel-item { display:flex; align-items:center; justify-content:space-between; gap:16rpx; min-height:92rpx; border-bottom:1rpx solid #f1f5f9; }
.channel-item.selected { background:#f8fbff; }
.channel-main { display:flex; align-items:center; gap:12rpx; min-width:0; flex:1; overflow:hidden; }
.channel-edit { flex:1; min-width:0; height:64rpx; background:#f8fafc; border:1rpx solid #e2e8f0; border-radius:10rpx; display:flex; align-items:center; padding:0 12rpx; box-sizing:border-box; }
.channel-edit :deep(.u-input) { width:100%; flex:1; }
.channel-edit :deep(.u-input__content) { width:100%; flex:1; }
.channel-actions { display:flex; align-items:center; gap:8rpx; flex-shrink:0; }
.channel-empty { text-align:center; color:#94a3b8; font-size:26rpx; padding:48rpx 0; }
.channel-loading { display:flex; justify-content:center; padding:48rpx; }
</style>
