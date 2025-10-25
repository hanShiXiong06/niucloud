# 用户搜索选择组件 (MemberSelect)

一个支持实时搜索、分页和用户信息展示的用户选择组件。

## 功能特点

- ✅ 实时搜索用户（支持昵称、手机号、用户编号）
- ✅ 防抖处理，避免频繁API调用
- ✅ 用户头像展示
- ✅ 用户详细信息显示（昵称、手机号、余额、等级）
- ✅ 支持分页加载（可选）
- ✅ 空状态友好提示
- ✅ 加载状态显示
- ✅ 支持清除选择
- ✅ 支持双向数据绑定

## 基础用法

```vue
<template>
    <member-select
        v-model="selectedMemberId"
        placeholder="请输入用户昵称、手机号或用户编号搜索"
        @change="handleMemberChange"
    />
</template>

<script setup>
import { ref } from 'vue'
import MemberSelect from '@/components/member-select/index.vue'

const selectedMemberId = ref(null)

const handleMemberChange = (memberId, memberInfo) => {
    console.log('选中的用户ID:', memberId)
    console.log('用户信息:', memberInfo)
    // memberInfo 包含完整的用户信息：
    // {
    //   member_id: number,
    //   nickname: string,
    //   mobile: string,
    //   headimg: string,
    //   balance: string,
    //   member_level_name: string
    // }
}
</script>
```

## API

### Props

| 属性名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| modelValue | `number \| string \| null` | `null` | 选中的用户ID，支持v-model |
| placeholder | `string` | `'请输入用户昵称、手机号或用户编号搜索'` | 输入框占位符 |
| showPagination | `boolean` | `false` | 是否显示分页控制 |

### Events

| 事件名 | 参数 | 说明 |
|--------|------|------|
| update:modelValue | `(value: number \| string \| null)` | v-model绑定，当选择变化时触发 |
| change | `(memberId: number \| string \| null, memberInfo: Member \| null)` | 选择变化时触发，返回用户ID和完整用户信息 |

### 用户信息对象 (Member)

```typescript
interface Member {
    member_id: number        // 用户ID
    member_no: string        // 用户编号
    nickname: string         // 用户昵称
    mobile: string           // 手机号
    headimg: string          // 头像URL
    balance: string          // 账户余额
    member_level_name: string // 会员等级名称
    status: number           // 用户状态
}
```

## 使用场景

### 1. 在搜索表单中使用

```vue
<template>
    <el-form-item label="用户">
        <member-select
            v-model="searchForm.member_id"
            placeholder="搜索用户"
            @change="handleMemberChange"
        />
    </el-form-item>
</template>

<script setup>
const searchForm = reactive({
    member_id: null
})

const handleMemberChange = (memberId) => {
    searchForm.member_id = memberId
    // 触发搜索
    search()
}
</script>
```

### 2. 在订单表单中使用

```vue
<template>
    <el-form :model="orderForm">
        <el-form-item label="下单用户" required>
            <member-select
                v-model="orderForm.member_id"
                placeholder="请选择下单用户"
                @change="handleOrderMemberChange"
            />
        </el-form-item>
    </el-form>
</template>

<script setup>
const orderForm = reactive({
    member_id: null,
    // ... 其他订单字段
})

const handleOrderMemberChange = (memberId, memberInfo) => {
    orderForm.member_id = memberId
    
    // 可以根据用户信息自动填充其他字段
    if (memberInfo) {
        orderForm.customer_name = memberInfo.nickname
        orderForm.customer_mobile = memberInfo.mobile
    }
}
</script>
```

### 3. 带分页的使用

```vue
<template>
    <member-select
        v-model="selectedMemberId"
        :show-pagination="true"
        placeholder="搜索用户（支持分页）"
        @change="handleMemberChange"
    />
</template>
```

## 搜索规则

组件支持以下搜索条件：

1. **关键词搜索** (`keyword`): 支持用户昵称、手机号、用户编号的模糊搜索
2. **最小搜索长度**: 2个字符（避免过于频繁的API调用）
3. **防抖延迟**: 300ms（用户停止输入300ms后才发起搜索）
4. **状态过滤**: 只显示正常状态的用户 (`status = 1`)

## 样式定制

组件提供了完整的样式，包括：

- 用户选项的卡片式布局
- 头像显示（支持默认头像）
- 用户信息的分层显示
- 不同信息类型的颜色区分
- 响应式布局适配

如需自定义样式，可以通过CSS覆盖相应的类名：

```css
/* 自定义选项样式 */
.member-select-component .member-option {
    /* 你的自定义样式 */
}

/* 自定义头像样式 */
.member-select-component .member-avatar {
    /* 你的自定义样式 */
}
```

## 注意事项

1. **API依赖**: 组件依赖 `@/app/api/member` 中的 `getMemberList` 方法
2. **图片处理**: 使用 `@/utils/common` 中的 `img` 方法处理图片URL
3. **防抖处理**: 使用 `lodash-es` 的 `debounce` 方法
4. **Element Plus**: 依赖 Element Plus 组件库
5. **TypeScript**: 完全支持TypeScript类型定义

## 开发建议

1. 在大数据量场景下建议开启分页功能
2. 根据实际业务需求调整搜索防抖时间
3. 可以根据需要扩展更多的搜索条件（如注册渠道、会员等级等）
4. 建议在表单验证中检查用户选择的有效性 