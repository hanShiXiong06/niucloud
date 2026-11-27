<template>
    <view class="permission-page" :style="themeColor()">
        <view class="search-bar">
            <u-search
                v-model="keyword"
                placeholder="搜索角色名称"
                :showAction="false"
                bgColor="#f3f4f6"
                height="36"
                @search="reload"
                @clear="reload"
            />
            <view class="user-link" @click="toUserList">
                <u-icon name="account" color="var(--primary-color)" size="21" />
                <text>管理员</text>
            </view>
        </view>

        <z-paging
            ref="pagingRef"
            v-model="roles"
            :fixed="true"
            :auto="true"
            :default-page-size="15"
            :paging-style="{ top: '104rpx' }"
            @query="queryRoles"
        >
            <view class="list-wrap">
                <view v-for="item in roles" :key="item.role_id" class="role-card">
                    <view class="role-main">
                        <view class="role-icon">
                            <u-icon name="account-fill" color="var(--primary-color)" size="23" />
                        </view>
                        <view class="role-info">
                            <text class="role-name">{{ item.role_name }}</text>
                            <text class="role-time">创建于 {{ item.create_time || '--' }}</text>
                        </view>
                        <view class="status-control">
                            <u-switch
                                :modelValue="Number(item.status) === 1"
                                size="20"
                                activeColor="var(--primary-color)"
                                @change="changeStatus(item, $event)"
                            />
                            <text>{{ Number(item.status) === 1 ? '启用' : '停用' }}</text>
                        </view>
                    </view>
                    <view class="card-actions">
                        <view class="action" @click="editItem(item)">编辑权限</view>
                        <view class="action danger" @click="removeItem(item)">删除角色</view>
                    </view>
                </view>
            </view>

            <template #empty>
                <u-empty mode="data" text="暂无角色" marginTop="160" />
            </template>
        </z-paging>

        <view class="add-fab" @click="addItem">
            <u-icon name="plus" color="#fff" size="28" />
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { redirect } from '@/utils/common'
import { deleteRole, getRoleList, updateRoleStatus } from '@/app/api/permission'

const pagingRef = ref<any>(null)
const roles = ref<any[]>([])
const keyword = ref('')
const initialized = ref(false)
const statusLoading = ref<number[]>([])

const queryRoles = (pageNo: number, pageSize: number) => {
    getRoleList({
        page: pageNo,
        limit: pageSize,
        role_name: keyword.value.trim()
    }).then((res: any) => {
        pagingRef.value?.complete(res.data?.data || [])
    }).catch(() => {
        pagingRef.value?.complete(false)
    })
}

const reload = () => pagingRef.value?.reload()

onShow(() => {
    if (initialized.value) reload()
    initialized.value = true
})

const toUserList = () => redirect({ url: '/app/pages/permission/user-list' })
const addItem = () => redirect({ url: '/app/pages/permission/role-edit' })
const editItem = (item: any) => redirect({ url: `/app/pages/permission/role-edit?role_id=${item.role_id}` })

const changeStatus = async (item: any, value: boolean) => {
    const roleId = Number(item.role_id)
    if (statusLoading.value.includes(roleId)) return
    statusLoading.value.push(roleId)
    try {
        await updateRoleStatus(roleId, value ? 1 : 0)
        item.status = value ? 1 : 0
    } finally {
        statusLoading.value = statusLoading.value.filter(id => id !== roleId)
    }
}

const removeItem = (item: any) => {
    uni.showModal({
        title: '删除角色',
        content: `确定删除「${item.role_name}」？已被管理员使用的角色不能删除。`,
        confirmColor: '#e5484d',
        success: ({ confirm }) => {
            if (!confirm) return
            deleteRole(Number(item.role_id)).then(reload)
        }
    })
}
</script>

<style lang="scss" scoped>
.permission-page {
    min-height: 100vh;
    background: var(--page-bg-color);
}
.search-bar {
    position: fixed;
    z-index: 20;
    top: 0;
    left: 0;
    right: 0;
    display: flex;
    align-items: center;
    gap: 22rpx;
    padding: 14rpx 24rpx;
    background: #fff;
    box-sizing: border-box;
}
.user-link {
    width: 92rpx;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2rpx;
    color: var(--primary-color);
    font-size: 20rpx;
}
.list-wrap {
    padding: 22rpx 24rpx 140rpx;
}
.role-card {
    margin-bottom: 20rpx;
    padding: 26rpx 26rpx 0;
    border-radius: 16rpx;
    background: #fff;
}
.role-main {
    display: flex;
    align-items: center;
}
.role-icon {
    width: 82rpx;
    height: 82rpx;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12rpx;
    background: #eef7f3;
}
.role-info {
    min-width: 0;
    flex: 1;
    margin-left: 20rpx;
}
.role-name {
    display: block;
    overflow: hidden;
    color: #202124;
    font-size: 30rpx;
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.role-time {
    display: block;
    margin-top: 8rpx;
    color: #9aa1aa;
    font-size: 22rpx;
}
.status-control {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8rpx;
    color: #8b929c;
    font-size: 20rpx;
}
.card-actions {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    margin-top: 24rpx;
    border-top: 1rpx solid #edf0f3;
}
.action {
    padding: 22rpx 0;
    color: var(--primary-color);
    font-size: 25rpx;
    text-align: center;
}
.action + .action {
    border-left: 1rpx solid #edf0f3;
}
.action.danger {
    color: #d94c4c;
}
.add-fab {
    position: fixed;
    z-index: 30;
    right: 34rpx;
    bottom: calc(40rpx + env(safe-area-inset-bottom));
    width: 96rpx;
    height: 96rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: var(--primary-color);
    box-shadow: 0 12rpx 28rpx rgba(0, 0, 0, .16);
}
</style>
