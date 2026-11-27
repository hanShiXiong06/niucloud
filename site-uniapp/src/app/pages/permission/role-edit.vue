<template>
    <view class="role-form-page" :style="themeColor()">
        <view class="form-section">
            <u-form :model="form" labelWidth="150rpx">
                <u-form-item label="角色名称" required>
                    <u-input v-model.trim="form.role_name" border="none" maxlength="10" placeholder="例如：质检员、财务" />
                </u-form-item>
                <u-form-item label="启用角色">
                    <u-switch v-model="enabled" activeColor="var(--primary-color)" />
                </u-form-item>
            </u-form>
        </view>

        <view class="permission-head">
            <view>
                <text class="permission-title">功能权限</text>
                <text class="permission-desc">按模块授权，已选 {{ selectedCount }} 项</text>
            </view>
            <view class="select-all" @click="toggleAll">
                <view class="check-box" :class="{ checked: allSelected }">
                    <u-icon v-if="allSelected" name="checkbox-mark" color="#fff" size="14" />
                </view>
                <text>全选</text>
            </view>
        </view>

        <view v-if="loading" class="loading-wrap">
            <u-loading-icon mode="circle" />
        </view>

        <view v-else class="module-list">
            <view v-for="group in groups" :key="group.menu_key" class="permission-module">
                <view class="module-head">
                    <view class="module-select" @click="toggleBranch(group)">
                        <view class="check-box" :class="{ checked: isBranchAllSelected(group) }">
                            <u-icon v-if="isBranchAllSelected(group)" name="checkbox-mark" color="#fff" size="14" />
                        </view>
                        <view>
                            <text class="module-name">{{ group.menu_name }}</text>
                            <text class="module-count">{{ branchSelectedCount(group) }}/{{ branchKeys(group).length }}</text>
                        </view>
                    </view>
                    <view class="expand-button" @click="toggleExpand(group.menu_key)">
                        <text>{{ expandedKeys.includes(group.menu_key) ? '收起' : '展开' }}</text>
                        <u-icon :name="expandedKeys.includes(group.menu_key) ? 'arrow-up' : 'arrow-down'" color="#8d949d" size="14" />
                    </view>
                </view>

                <view v-if="expandedKeys.includes(group.menu_key)" class="module-body">
                    <view
                        v-for="node in flattenChildren(group)"
                        :key="node.menu_key"
                        class="permission-row"
                        :style="{ paddingLeft: `${Math.max(0, (node._level || 0) - 1) * 32 + 22}rpx` }"
                        @click="toggleBranch(node)"
                    >
                        <view class="check-box" :class="{ checked: isSelected(node.menu_key) }">
                            <u-icon v-if="isSelected(node.menu_key)" name="checkbox-mark" color="#fff" size="14" />
                        </view>
                        <view class="permission-copy">
                            <text class="permission-name">{{ node.menu_name }}</text>
                            <text v-if="(node.children || []).length" class="child-count">包含 {{ countLeaves(node) }} 项</text>
                        </view>
                    </view>
                </view>
            </view>
            <u-empty v-if="!groups.length" mode="data" text="暂无可授权菜单" marginTop="80" />
        </view>

        <view class="form-footer">
            <u-button
                type="primary"
                text="保存角色"
                :loading="saving"
                :customStyle="{ height: '88rpx', borderRadius: '12rpx' }"
                @click="submit"
            />
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { addRole, editRole, getRoleInfo, getSiteMenus } from '@/app/api/permission'

type MenuNode = {
    menu_key: string
    menu_name: string
    children?: MenuNode[]
    _level?: number
}

const roleId = ref(0)
const loading = ref(true)
const saving = ref(false)
const groups = ref<MenuNode[]>([])
const selectedKeys = ref<string[]>([])
const expandedKeys = ref<string[]>([])
const parentMap = new Map<string, string>()
const nodeMap = new Map<string, MenuNode>()

const form = reactive({
    role_name: '',
    status: 1
})

const enabled = computed({
    get: () => Number(form.status) === 1,
    set: (value: boolean) => { form.status = value ? 1 : 0 }
})
const selectedCount = computed(() => selectedKeys.value.length)
const allMenuKeys = computed(() => groups.value.flatMap(group => branchKeys(group)))
const allSelected = computed(() => allMenuKeys.value.length > 0 && allMenuKeys.value.every(isSelected))

onLoad(async (options: any) => {
    roleId.value = Number(options?.role_id || 0)
    try {
        const requests: Promise<any>[] = [getSiteMenus()]
        if (roleId.value) requests.push(getRoleInfo(roleId.value))
        const results = await Promise.all(requests)
        groups.value = normalizeMenus(results[0].data || [])
        buildIndex(groups.value)
        expandedKeys.value = groups.value.slice(0, 2).map(item => item.menu_key)
        if (roleId.value) {
            const detail = results[1].data || {}
            form.role_name = detail.role_name || ''
            form.status = Number(detail.status) === 1 ? 1 : 0
            const keys = new Set<string>(
                (detail.rules || []).map(String).filter((key: string) => nodeMap.has(key))
            )
            rebuildParents(keys)
            selectedKeys.value = Array.from(keys)
        }
    } finally {
        loading.value = false
    }
})

const normalizeMenus = (items: any[], level = 0): MenuNode[] => items
    .filter(item => item?.menu_key)
    .map(item => ({
        ...item,
        menu_key: String(item.menu_key),
        menu_name: item.menu_name || item.menu_short_name || item.menu_key,
        _level: level,
        children: normalizeMenus(item.children || [], level + 1)
    }))

const buildIndex = (items: MenuNode[], parentKey = '') => {
    items.forEach(item => {
        nodeMap.set(item.menu_key, item)
        if (parentKey) parentMap.set(item.menu_key, parentKey)
        buildIndex(item.children || [], item.menu_key)
    })
}

const branchKeys = (node: MenuNode): string[] => [
    node.menu_key,
    ...(node.children || []).flatMap(branchKeys)
]

const leafKeys = (node: MenuNode): string[] => {
    if (!(node.children || []).length) return [node.menu_key]
    return (node.children || []).flatMap(leafKeys)
}

const countLeaves = (node: MenuNode) => leafKeys(node).length
const isSelected = (key: string) => selectedKeys.value.includes(key)
const isBranchAllSelected = (node: MenuNode) => branchKeys(node).every(isSelected)
const branchSelectedCount = (node: MenuNode) => branchKeys(node).filter(isSelected).length

const rebuildParents = (keys: Set<string>) => {
    nodeMap.forEach(node => {
        if ((node.children || []).length) keys.delete(node.menu_key)
    })
    let changed = true
    while (changed) {
        changed = false
        Array.from(keys).forEach(key => {
            let parent = parentMap.get(key)
            while (parent) {
                if (!keys.has(parent)) {
                    keys.add(parent)
                    changed = true
                }
                parent = parentMap.get(parent)
            }
        })
    }
}

const toggleBranch = (node: MenuNode) => {
    const keys = new Set(selectedKeys.value)
    const targets = branchKeys(node)
    const remove = targets.every(key => keys.has(key))
    targets.forEach(key => remove ? keys.delete(key) : keys.add(key))
    rebuildParents(keys)
    selectedKeys.value = Array.from(keys)
}

const toggleAll = () => {
    selectedKeys.value = allSelected.value ? [] : Array.from(new Set(allMenuKeys.value))
}

const toggleExpand = (key: string) => {
    const index = expandedKeys.value.indexOf(key)
    if (index >= 0) expandedKeys.value.splice(index, 1)
    else expandedKeys.value.push(key)
}

const flattenChildren = (node: MenuNode): MenuNode[] => (node.children || []).flatMap(child => [
    child,
    ...flattenChildren(child)
])

const submit = async () => {
    if (saving.value) return
    if (!form.role_name) {
        uni.showToast({ title: '请输入角色名称', icon: 'none' })
        return
    }
    if (!selectedKeys.value.length) {
        uni.showToast({ title: '请至少选择一项权限', icon: 'none' })
        return
    }
    saving.value = true
    const data = {
        role_name: form.role_name,
        status: form.status,
        rules: [...selectedKeys.value]
    }
    try {
        if (roleId.value) await editRole(roleId.value, data)
        else await addRole(data)
        setTimeout(() => uni.navigateBack(), 350)
    } finally {
        saving.value = false
    }
}
</script>

<style lang="scss" scoped>
.role-form-page {
    min-height: 100vh;
    padding: 22rpx 24rpx 150rpx;
    box-sizing: border-box;
    background: var(--page-bg-color);
}
.form-section {
    padding: 12rpx 28rpx;
    border-radius: 16rpx;
    background: #fff;
}
.permission-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 32rpx 6rpx 18rpx;
}
.permission-title,
.permission-desc {
    display: block;
}
.permission-title {
    color: #202124;
    font-size: 30rpx;
    font-weight: 600;
}
.permission-desc {
    margin-top: 7rpx;
    color: #969da7;
    font-size: 22rpx;
}
.select-all,
.module-select,
.expand-button,
.permission-row {
    display: flex;
    align-items: center;
}
.select-all {
    gap: 10rpx;
    color: #59616c;
    font-size: 24rpx;
}
.check-box {
    width: 34rpx;
    height: 34rpx;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2rpx solid #c8ced6;
    border-radius: 6rpx;
    box-sizing: border-box;
}
.check-box.checked {
    border-color: var(--primary-color);
    background: var(--primary-color);
}
.loading-wrap {
    display: flex;
    justify-content: center;
    padding: 120rpx 0;
}
.permission-module {
    overflow: hidden;
    margin-bottom: 18rpx;
    border-radius: 12rpx;
    background: #fff;
}
.module-head {
    min-height: 94rpx;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24rpx;
}
.module-select {
    flex: 1;
    gap: 18rpx;
}
.module-name {
    color: #252a31;
    font-size: 28rpx;
    font-weight: 600;
}
.module-count {
    margin-left: 12rpx;
    color: #a0a6ae;
    font-size: 21rpx;
}
.expand-button {
    gap: 7rpx;
    padding: 20rpx 0 20rpx 24rpx;
    color: #7d858f;
    font-size: 23rpx;
}
.module-body {
    border-top: 1rpx solid #edf0f3;
}
.permission-row {
    min-height: 86rpx;
    gap: 18rpx;
    padding-right: 22rpx;
    border-bottom: 1rpx solid #f0f2f4;
    box-sizing: border-box;
}
.permission-row:last-child {
    border-bottom: 0;
}
.permission-copy {
    min-width: 0;
    flex: 1;
}
.permission-name {
    color: #3d434c;
    font-size: 25rpx;
}
.child-count {
    margin-left: 14rpx;
    color: #a2a8b0;
    font-size: 21rpx;
}
.form-footer {
    position: fixed;
    z-index: 10;
    right: 0;
    bottom: 0;
    left: 0;
    padding: 18rpx 24rpx calc(18rpx + env(safe-area-inset-bottom));
    border-top: 1rpx solid #edf0f3;
    background: #fff;
}
:deep(.u-form-item__body) {
    min-height: 96rpx;
    padding: 0 !important;
    align-items: center;
}
:deep(.u-form-item__body__left__content__label) {
    color: #30343b;
    font-size: 28rpx;
}
</style>
