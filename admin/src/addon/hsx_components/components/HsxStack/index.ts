import { defineComponent, h, type PropType } from 'vue'
import './style.scss'

type StackDirection = 'row' | 'column'
type StackAlign = 'stretch' | 'start' | 'center' | 'end' | 'baseline'
type StackJustify = 'start' | 'center' | 'end' | 'between' | 'around' | 'evenly'

const alignMap: Record<StackAlign, string> = { stretch: 'stretch', start: 'flex-start', center: 'center', end: 'flex-end', baseline: 'baseline' }
const justifyMap: Record<StackJustify, string> = { start: 'flex-start', center: 'center', end: 'flex-end', between: 'space-between', around: 'space-around', evenly: 'space-evenly' }

export default defineComponent({
    name: 'HsxStack',
    inheritAttrs: false,
    props: {
        direction: { type: String as PropType<StackDirection>, default: 'row' },
        align: { type: String as PropType<StackAlign>, default: 'center' },
        justify: { type: String as PropType<StackJustify>, default: 'start' },
        gap: { type: [Number, String], default: 12 },
        wrap: { type: Boolean, default: false },
        tag: { type: String, default: 'div' }
    },
    setup(props, { slots, attrs }) {
        return () => h(props.tag, {
            ...attrs,
            class: ['hsx-stack', attrs.class],
            style: [attrs.style, {
                flexDirection: props.direction,
                alignItems: alignMap[props.align],
                justifyContent: justifyMap[props.justify],
                flexWrap: props.wrap ? 'wrap' : 'nowrap',
                gap: typeof props.gap === 'number' ? `${props.gap}px` : props.gap
            }]
        }, slots.default?.())
    }
})
