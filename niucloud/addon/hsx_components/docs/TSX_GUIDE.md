# Template、TSX 与低代码 Schema 使用规范

组件库同时支持 Vue Template 和 TSX。两种语法使用同一个组件实现、同一份类型、同一套设计令牌，
不维护“Template 版组件”和“TSX 版组件”两套源码。

## 1. 选择规则

- 普通页面、静态布局、设计稿还原：优先 Template，结构更直观。
- 动态列、字段工厂、权限组合、复杂插槽、运行时渲染：优先 TSX。
- 表单和 CRUD 低代码：优先 Schema；遇到业务特殊字段，通过注册组件和插槽扩展。
- 不允许为了“统一写法”把所有页面强制改成 TSX，也不允许在业务里复制 Schema 渲染器。

后台与 UniApp 工程都已启用 `@vitejs/plugin-vue-jsx`，`tsconfig` 使用 Vue JSX 类型源。

## 2. 相同组件，两种写法

```vue
<script setup lang="ts">
import { HsxButton, HsxStack } from '@/addon/hsx_components'
</script>

<template>
    <HsxStack justify="end" :gap="8">
        <HsxButton>取消</HsxButton>
        <HsxButton type="primary" :action="save">保存</HsxButton>
    </HsxStack>
</template>
```

```tsx
import { defineComponent } from 'vue'
import { HsxButton, HsxStack } from '@/addon/hsx_components'

export default defineComponent(() => () => (
    <HsxStack justify="end" gap={8}>
        <HsxButton>取消</HsxButton>
        <HsxButton type="primary" action={save}>保存</HsxButton>
    </HsxStack>
))
```

`HsxStack` 本身使用 TSX 实现，用来持续验证组件库的 TSX 构建链；它仍能被 Template 页面正常使用。

## 3. UniApp TSX

UniApp TSX 必须使用跨端标签和组件，不直接访问 DOM、`window`、`document`。震动、弹窗、Toast、
剪贴板等能力统一通过 Hooks 调用。

```tsx
import { defineComponent } from 'vue'
import { HsxButton, HsxStack, useModal } from '@/addon/hsx_components'

export default defineComponent(() => {
    const modal = useModal()
    const remove = () => modal.danger('确认删除这条记录吗？')
    return () => (
        <HsxStack justify="end" gap={16}>
            <HsxButton haptic="light">取消</HsxButton>
            <HsxButton type="error" haptic="heavy" action={remove}>删除</HsxButton>
        </HsxStack>
    )
})
```

Schema 表单可直接使用 `HsxSchemaForm`。它只是 `HsxForm` 的强类型 TSX 门面，验证、联动、异步选项
和弹层仍来自同一实现：

```tsx
import { defineComponent, ref } from 'vue'
import { HsxSchemaForm, defineMobileFormSchema } from '@/addon/hsx_components'

export default defineComponent(() => {
    const model = ref({ grade: '' })
    const schema = defineMobileFormSchema([
        { prop: 'grade', label: '成色', component: 'select', options: [
            { label: 'A 级', value: 'A' },
            { label: 'B+ 级', value: 'B+' }
        ] }
    ])
    return () => <HsxSchemaForm v-model={model.value} schema={schema} />
})
```

## 4. 低代码边界

`HsxBlockRenderer` 只渲染白名单组件：title、text、progress、grid、stack、card。业务扩展使用
具名 `slot` 节点；不使用小程序不支持的动态 `<component :is>`。它适合运营模块、信息卡片和
轻量页面拼装，不负责执行任意字符串脚本。

```ts
const schema = [
    { type: 'title', props: { title: '卖家信用', size: 'card' } },
    { type: 'text', text: '描述准确率、退货率、售后时长', props: { lines: 2 } },
    { type: 'progress', props: { percentage: 92, label: '可信度', tone: 'success' } }
]
```

```vue
<HsxBlockRenderer :schema="schema" />
```

Schema 只描述界面结构和参数。支付、资金、权限、交易状态等关键规则必须在后端和业务服务层执行，
不能放入前端 JSON。
