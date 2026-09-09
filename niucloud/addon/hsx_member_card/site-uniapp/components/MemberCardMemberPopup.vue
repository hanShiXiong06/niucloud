<template>
    <MemberCardSheet
        :show="show"
        :title="creatingMode ? '新增客户' : '选择购卡客户'"
        :subtitle="creatingMode ? '已有手机号将关联原客户，不重复创建' : '姓名、手机号或会员号均可检索'"
        :busy="creating"
        :height="creatingMode ? '68vh' : '72vh'"
        @update:show="emit('update:show', $event)"
    >
        <template v-if="creatingMode">
            <u-form :model="form" labelWidth="88" :labelStyle="{ fontSize: '14px', color: '#53637a' }">
                <u-form-item label="客户姓名" required borderBottom>
                    <u-input v-model="form.name" border="none" maxlength="50" placeholder="填写姓名" />
                </u-form-item>
                <u-form-item label="手机号" required borderBottom>
                    <u-input
                        v-model="form.mobile"
                        type="number"
                        maxlength="11"
                        border="none"
                        placeholder="11 位手机号"
                    />
                </u-form-item>
                <u-form-item label="初始密码" required borderBottom>
                    <u-input
                        v-model="form.password"
                        type="password"
                        maxlength="32"
                        border="none"
                        placeholder="6–32 位"
                        @input="passwordCustomized = true"
                    />
                </u-form-item>
            </u-form>
            <view class="mc-sub">沿用现有开户规则：默认手机号后六位，可在创建前修改。请提醒客户登录后修改密码。</view>
            <MemberCardNotice v-if="createError" tone="error" :text="createError" />
        </template>
        <template v-else>
            <u-search
                v-model="keyword"
                placeholder="姓名 / 手机号 / 会员号"
                shape="square"
                bgColor="#f2f4f7"
                :height="40"
                :animation="false"
                actionText="搜索"
                @search="load"
                @custom="load"
                @clear="load"
            />
            <MemberCardState
                v-if="loading || error || !rows.length"
                :loading="loading"
                :error="error"
                text="未找到客户，可以直接新增"
                :action="error ? '重新加载' : ''"
                @action="load"
            />
            <template v-else>
                <u-cell-group :border="false">
                    <u-cell
                        v-for="row in rows"
                        :key="row.member_id"
                        :title="row.display_name || '未命名客户'"
                        :label="row.mobile_masked || '未留手机号'"
                        :value="(row.card_count || 0) + ' 张卡'"
                        isLink
                        center
                        :customStyle="{ margin: '0 -15px' }"
                        @click="choose(row)"
                    >
                        <template #icon
                            ><view class="mc-avatar">{{ String(row.display_name || '客').slice(0, 1) }}</view></template
                        >
                    </u-cell>
                </u-cell-group>
                <view v-if="rows.length >= 40" class="mc-sub">显示前 40 位客户，请输入手机号缩小范围。</view>
            </template>
        </template>
        <template #footer>
            <view class="mc-sheet__actions">
                <view>
                    <MemberCardButton :text="creatingMode ? '返回选择' : '关闭'" :disabled="creating" @click="back" />
                </view>
                <view>
                    <MemberCardButton
                        type="primary"
                        :text="creatingMode ? '创建并选中' : '新增客户'"
                        :loading="creating"
                        @click="primary"
                    />
                </view>
            </view>
        </template>
    </MemberCardSheet>
</template>
<script setup lang="ts">
import { ref, watch, onBeforeUnmount } from 'vue'
import { getCardMemberOptions, memberCardRequestId, quickCreateCardMember } from '../api'
import MemberCardSheet from './MemberCardSheet.vue'
import MemberCardButton from './MemberCardButton.vue'
import MemberCardState from './MemberCardState.vue'
import MemberCardNotice from './MemberCardNotice.vue'
import { errorText, markMemberCardChanged } from '../utils/presentation'
const props = withDefaults(defineProps<{ show?: boolean }>(), { show: false })
const emit = defineEmits(['update:show', 'select'])
const keyword = ref(''),
    error = ref(''),
    createError = ref('')
const rows = ref<any[]>([])
const loading = ref(false),
    creatingMode = ref(false),
    creating = ref(false)
const form = ref({ name: '', mobile: '', password: '' })
const passwordCustomized = ref(false)
let sequence = 0
let attemptKey = '',
    attemptId = ''
const load = async () => {
    const ticket = ++sequence
    loading.value = true
    error.value = ''
    try {
        const result: any = await getCardMemberOptions({ keyword: keyword.value.trim(), limit: 40 })
        if (ticket !== sequence) return
        rows.value = Array.isArray(result?.data) ? result.data : result?.data?.list || []
    } catch (e) {
        if (ticket === sequence) {
            rows.value = []
            error.value = errorText(e, '客户加载失败')
        }
    } finally {
        if (ticket === sequence) loading.value = false
    }
}
const choose = (row: any) => {
    emit('select', row)
    emit('update:show', false)
}
const back = () => {
    if (creating.value) return
    if (creatingMode.value) creatingMode.value = false
    else emit('update:show', false)
}
const primary = () => {
    if (creatingMode.value) void submitCreate()
    else {
        creatingMode.value = true
        createError.value = ''
    }
}
const submitCreate = async () => {
    if (creating.value) return
    if (!form.value.name.trim() || !/^1\d{10}$/.test(form.value.mobile)) {
        createError.value = '请填写姓名和正确的 11 位手机号'
        return
    }
    if (form.value.password.length < 6 || form.value.password.length > 32 || /\s/.test(form.value.password)) {
        createError.value = '密码需为 6–32 位且不能含空格'
        return
    }
    const key = JSON.stringify(form.value)
    if (key !== attemptKey) {
        attemptKey = key
        attemptId = memberCardRequestId('member')
    }
    creating.value = true
    createError.value = ''
    try {
        const data: any = (
            await quickCreateCardMember({ ...form.value, name: form.value.name.trim(), request_id: attemptId })
        )?.data
        if (!data?.member_id) throw new Error('missing member')
        markMemberCardChanged()
        choose({
            member_id: data.member_id,
            display_name: data.member_name,
            mobile_masked: data.mobile_masked,
            card_count: 0
        })
        form.value = { name: '', mobile: '', password: '' }
        passwordCustomized.value = false
        attemptKey = ''
    } catch (e) {
        createError.value = errorText(e, '未能确认创建结果，请用手机号查询后再试')
    } finally {
        creating.value = false
    }
}
watch(
    () => props.show,
    (visible) => {
        if (visible) {
            creatingMode.value = false
            keyword.value = ''
            void load()
        } else sequence++
    },
    { immediate: true }
)
watch(
    () => form.value.mobile,
    (mobile) => {
        if (!passwordCustomized.value) form.value.password = /^1\d{5,10}$/.test(mobile) ? mobile.slice(-6) : ''
    }
)
onBeforeUnmount(() => {
    sequence++
})
</script>
