import { defineComponent, type PropType } from 'vue'
import './style.scss'

type StackDirection = 'row' | 'column'
type StackAlign = 'stretch' | 'start' | 'center' | 'end' | 'baseline'
type StackJustify = 'start' | 'center' | 'end' | 'between' | 'around' | 'evenly'

const alignMap = { stretch: 'stretch', start: 'flex-start', center: 'center', end: 'flex-end', baseline: 'baseline' }
const justifyMap = { start: 'flex-start', center: 'center', end: 'flex-end', between: 'space-between', around: 'space-around', evenly: 'space-evenly' }

export default defineComponent({
    name: 'HsxStack',
    props: {
        direction: { type: String as PropType<StackDirection>, default: 'row' },
        align: { type: String as PropType<StackAlign>, default: 'center' },
        justify: { type: String as PropType<StackJustify>, default: 'start' },
        gap: { type: [Number, String], default: 12 },
        wrap: { type: Boolean, default: false },
        tag: { type: String, default: 'div' }
    },
    setup(props, { slots, attrs }) {
        return () => {
            const Tag = props.tag as any
            const gap = typeof props.gap === 'number' ? `${props.gap}px` : props.gap
            return <Tag {...attrs} class={['hsx-stack', attrs.class]} style={[attrs.style as any, {
                flexDirection: props.direction,
                alignItems: alignMap[props.align],
                justifyContent: justifyMap[props.justify],
                flexWrap: props.wrap ? 'wrap' : 'nowrap',
                gap
            }]}>{slots.default?.()}</Tag>
        }
    }
})

