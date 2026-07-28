<template>
    <view class="permission-page" :style="themeColor()">
        <view class="search-bar">
            <u-search
                v-model="keyword"
                placeholder="搜索登录账号或姓名"
                :showAction="false"
                bgColor="#f3f4f6"
                height="36"
                @search="reload"
                @clear="reload"
            />
            <view class="role-link" @click="toRoleList">
                <u-icon name="account-fill" color="var(--primary-color)" size="21" />
                <text>角色</text>
            </view>
        </view>

        <z-paging
            ref="pagingRef"
            v-model="users"
            :fixed="true"
            :auto="true"
            :default-page-size="15"
            :paging-style="{ top: '104rpx' }"
            @query="queryUsers"
        >
            <view class="list-wrap">
                <view v-for="item in users" :key="item.uid" class="user-card">
                    <view class="user-main">
                        <u-avatar
                            :src="img(item.head_img)"
                            :default-url="img('static/resource/images/default_headimg.png')"
                            size="48"
                            leftIcon="none"
                        />
                        <view class="user-content">
                            <view class="name-line">
                                <text class="real-name">{{ item.real_name || item.username }}</text>
                                <view class="status-dot" :class="{ disabled: Number(item.status) !== 1 }" />
                                <text class="status-text">{{ Number(item.status) === 1 ? '正常' : '已停用' }}</text>
                            </view>
                            <text class="username">账号：{{ item.username }}</text>
                            <view class="role-row">
                                <text v-if="Number(item.is_admin) === 1" class="role-tag owner">站点负责人</text>
                                <text v-for="role in item.role_array || []" v-else :key="role" class="role-tag">{{ role }}</text>
                                <text v-if="Number(item.is_admin) !== 1 && !(item.role_array || []).length" class="no-role">未分配角色</text>
                            </view>
                        </view>
                    </view>

                    <view v-if="Number(item.is_admin) !== 1" class="card-actions">
                        <view class="action" @click="editUser(item)">编辑</view>
                        <view class="action" @click="toggleStatus(item)">{{ Number(item.status) === 1 ? '停用' : '启用' }}</view>
                        <view class="action danger" @click="removeUser(item)">删除</view>
                    </view>
                    <view v-else class="owner-tip">站点负责人不可停用或删除</view>
                </view>
            </view>

            <template #empty>
                <u-empty mode="data" text="暂无管理员" marginTop="160" />
            </template>
        </z-paging>

        <view class="add-fab" @click="addUser">
            <u-icon name="plus" color="#fff" size="28" />
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { img, redirect } from '@/utils/common'
import {
    deleteSiteUser,
    getSiteUserList,
    lockSiteUser,
    unlockSiteUser
} from '@/app/api/permission'

const pagingRef = ref<any>(null)
const users = ref<any[]>([])
const keyword = ref('')
const initialized = ref(false)

const queryUsers = (pageNo: number, pageSize: number) => {
    getSiteUserList({
        page: pageNo,
        limit: pageSize,
        username: keyword.value.trim()
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

const addUser = () => redirect({ url: '/app/pages/permission/user-edit' })
const editUser = (item: any) => redirect({ url: `/app/pages/permission/user-edit?uid=${item.uid}` })
const toRoleList = () => redirect({ url: '/app/pages/permission/role-list' })

const toggleStatus = (item: any) => {
    const enabled = Number(item.status) === 1
    uni.showModal({
        title: enabled ? '停用管理员' : '启用管理员',
        content: enabled
            ? `停用后「${item.real_name || item.username}」将立即退出登录，确定继续？`
            : `确定恢复「${item.real_name || item.username}」的登录权限？`,
        success: ({ confirm }) => {
            if (!confirm) return
            const action = enabled ? lockSiteUser : unlockSiteUser
            action(Number(item.uid)).then(reload)
        }
    })
}

const removeUser = (item: any) => {
    uni.showModal({
        title: '删除管理员',
        content: `确定将「${item.real_name || item.username}」移出当前站点？该账号本身不会被删除。`,
        confirmColor: '#e5484d',
        success: ({ confirm }) => {
            if (!confirm) return
            deleteSiteUser(Number(item.uid)).then(reload)
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
.role-link {
    width: 82rpx;
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
.user-card {
    margin-bottom: 20rpx;
    padding: 26rpx 26rpx 0;
    background: #fff;
    border-radius: 16rpx;
}
.user-main {
    display: flex;
    align-items: flex-start;
}
.user-content {
    min-width: 0;
    flex: 1;
    margin-left: 22rpx;
}
.name-line {
    display: flex;
    align-items: center;
}
.real-name {
    max-width: 320rpx;
    overflow: hidden;
    color: #202124;
    font-size: 31rpx;
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.status-dot {
    width: 12rpx;
    height: 12rpx;
    margin-left: 18rpx;
    border-radius: 50%;
    background: #22a06b;
}
.status-dot.disabled {
    background: #c2c8d0;
}
.status-text {
    margin-left: 8rpx;
    color: #7a828d;
    font-size: 22rpx;
}
.username {
    display: block;
    margin-top: 8rpx;
    color: #8b929c;
    font-size: 24rpx;
}
.role-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10rpx;
    margin-top: 18rpx;
}
.role-tag {
    padding: 7rpx 14rpx;
    border-radius: 6rpx;
    background: #f1f4f8;
    color: #52606f;
    font-size: 22rpx;
    line-height: 1.2;
}
.role-tag.owner {
    background: #eef7f3;
    color: var(--primary-color);
}
.no-role {
    color: #c1484d;
    font-size: 23rpx;
}
.card-actions {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    margin-top: 24rpx;
    border-top: 1rpx solid #edf0f3;
}
.action {
    padding: 22rpx 0;
    color: #4e5969;
    font-size: 25rpx;
    text-align: center;
}
.action + .action {
    border-left: 1rpx solid #edf0f3;
}
.action.danger {
    color: #d94c4c;
}
.owner-tip {
    margin-top: 24rpx;
    padding: 20rpx 0;
    border-top: 1rpx solid #edf0f3;
    color: #a0a6ae;
    font-size: 23rpx;
    text-align: center;
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
