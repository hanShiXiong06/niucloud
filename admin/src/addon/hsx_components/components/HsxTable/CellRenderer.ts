import { defineComponent, type PropType, type VNodeChild } from 'vue'
export default defineComponent({
    name: 'HsxTableCellRenderer',
    props: { renderer: { type: Function as PropType<(context: any) => VNodeChild>, required: true }, context: { type: Object as PropType<Record<string, any>>, required: true } },
    setup(props) { return () => props.renderer(props.context) as any }
})

