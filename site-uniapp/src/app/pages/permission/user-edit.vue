<template>
    <view class="form-page" :style="themeColor()">
        <view class="form-section">
            <view class="section-title">基本信息</view>
            <view class="avatar-row">
                <text class="field-label">头像</text>
                <upload-img v-model="form.head_img" :max-count="1" />
            </view>
            <u-form :model="form" labelWidth="150rpx">
                <u-form-item label="登录账号" required>
                    <u-input
                        v-model.trim="form.username"
                        :disabled="isEdit"
                        border="none"
                        maxlength="20"
                        placeholder="请输入登录账号"
                    />
                </u-form-item>
                <u-form-item label="真实姓名" required>
                    <u-input v-model.trim="form.real_name" border="none" maxlength="10" placeholder="方便团队识别" />
                </u-form-item>
                <u-form-item :label="isEdit ? '重置密码' : '登录密码'" :required="!isEdit">
                    <u-input
                        v-model.trim="form.password"
                        type="password"
                        border="none"
                        placeholder="至少 6 位；编辑时留空则不修改"
                    />
                </u-form-item>
                <u-form-item label="确认密码" :required="!isEdit">
                    <u-input
                        v-model.trim="form.confirm_password"
                        type="password"
                        border="none"
                        placeholder="再次输入密码"
                    />
                </u-form-item>
            </u-form>
        </view>

        <view class="form-section">
            <view class="section-head">
                <view>
                    <view class="section-title no-margin">分配角色</view>
                    <view class="section-desc">管理员将获得所选角色权限的合集</view>
                </view>
                <text class="selected-count">已选 {{ form.role_ids.length }}</text>
            </view>
            <view v-if="roles.length" class="role-grid">
                <view
                    v-for="role in roles"
                    :key="role.role_id"
                    class="role-option"
                    :class="{ active: hasRole(role.role_id), disabled: role.disabled }"
                    @click="toggleRole(role)"
                >
                    <u-icon
                        :name="hasRole(role.role_id) ? 'checkbox-mark' : 'plus'"
                        :color="hasRole(role.role_id) ? '#fff' : '#7b8490'"
                        size="15"
                    />
                    <text>{{ role.role_name }}</text>
                </view>
            </view>
            <view v-else class="empty-roles">暂无可用角色，请先创建角色</view>
        </view>

        <view class="form-section status-row">
            <view>
                <view class="section-title no-margin">允许登录</view>
                <view class="section-desc">关闭后该管理员会立即退出登录</view>
            </view>
            <u-switch v-model="enabled" activeColor="var(--primary-color)" />
        </view>

        <view class="form-footer">
            <u-button
                type="primary"
                text="保存管理员"
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
import {
    addSiteUser,
    editSiteUser,
    getAllRoles,
    getSiteUserInfo
} from '@/app/api/permission'

const uid = ref(0)
const isEdit = computed(() => uid.value > 0)
const roles = ref<any[]>([])
const saving = ref(false)
const enabled = computed({
    get: () => Number(form.status) === 1,
    set: (value: boolean) => { form.status = value ? 1 : 0 }
})

const form = reactive({
    username: '',
    real_name: '',
    head_img: '',
    password: '',
    confirm_password: '',
    status: 1,
    role_ids: [] as string[]
})

onLoad(async (options: any) => {
    uid.value = Number(options?.uid || 0)
    const requests: Promise<any>[] = [getAllRoles()]
    if (uid.value) requests.push(getSiteUserInfo(uid.value))
    try {
        const results = await Promise.all(requests)
        roles.value = (results[0].data || []).map((item: any) => ({
            ...item,
            role_id: String(item.role_id)
        }))
        if (uid.value) {
            const detail = results[1].data || {}
            form.username = detail.username || ''
            form.real_name = detail.real_name || ''
            form.head_img = detail.head_img || ''
            form.status = Number(detail.status) === 1 ? 1 : 0
            form.role_ids = (detail.role_ids || []).map(String)
        }
    } catch (error) {
        // Request helper already displays the server error.
    }
})

const hasRole = (roleId: string | number) => form.role_ids.includes(String(roleId))

const toggleRole = (role: any) => {
    if (role.disabled) {
        uni.showToast({ title: '当前账号无权分配该角色', icon: 'none' })
        return
    }
    const roleId = String(role.role_id)
    const index = form.role_ids.indexOf(roleId)
    if (index >= 0) form.role_ids.splice(index, 1)
    else form.role_ids.push(roleId)
}

const validate = () => {
    if (!isEdit.value && !form.username) return '请输入登录账号'
    if (!form.real_name) return '请输入真实姓名'
    if (!isEdit.value && form.password.length < 6) return '登录密码至少 6 位'
    if (form.password && form.password.length < 6) return '新密码至少 6 位'
    if (form.password !== form.confirm_password) return '两次输入的密码不一致'
    if (!form.role_ids.length) return '请至少选择一个角色'
    return ''
}

const submit = async () => {
    if (saving.value) return
    const message = validate()
    if (message) {
        uni.showToast({ title: message, icon: 'none' })
        return
    }
    saving.value = true
    const data: Record<string, any> = {
        username: form.username,
        real_name: form.real_name,
        head_img: form.head_img,
        status: form.status,
        role_ids: [...form.role_ids]
    }
    if (form.password) data.password = form.password
    try {
        if (isEdit.value) await editSiteUser(uid.value, data)
        else await addSiteUser({ ...data, password: form.password })
        setTimeout(() => uni.navigateBack(), 350)
    } finally {
        saving.value = false
    }
}
</script>

<style lang="scss" scoped>
.form-page {
    min-height: 100vh;
    padding: 22rpx 24rpx 150rpx;
    box-sizing: border-box;
    background: var(--page-bg-color);
}
.form-section {
    margin-bottom: 20rpx;
    padding: 28rpx;
    border-radius: 16rpx;
    background: #fff;
}
.section-title {
    margin-bottom: 22rpx;
    color: #202124;
    font-size: 30rpx;
    font-weight: 600;
}
.section-title.no-margin {
    margin: 0;
}
.section-desc {
    margin-top: 8rpx;
    color: #9aa1aa;
    font-size: 22rpx;
}
.avatar-row {
    display: flex;
    align-items: center;
    min-height: 130rpx;
    border-bottom: 1rpx solid #edf0f3;
}
.field-label {
    width: 150rpx;
    color: #30343b;
    font-size: 28rpx;
}
.section-head,
.status-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.selected-count {
    color: var(--primary-color);
    font-size: 24rpx;
}
.role-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
    margin-top: 26rpx;
}
.role-option {
    min-width: 180rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8rpx;
    padding: 18rpx 24rpx;
    border: 1rpx solid #dfe3e8;
    border-radius: 8rpx;
    color: #4e5969;
    font-size: 25rpx;
    box-sizing: border-box;
}
.role-option.active {
    border-color: var(--primary-color);
    background: var(--primary-color);
    color: #fff;
}
.role-option.disabled {
    opacity: .45;
}
.empty-roles {
    padding: 40rpx 0 12rpx;
    color: #a0a6ae;
    font-size: 25rpx;
    text-align: center;
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
