import { defineComponent, type PropType } from 'vue'

type Direction = 'row' | 'column'
type Align = 'stretch' | 'start' | 'center' | 'end' | 'baseline'
type Justify = 'start' | 'center' | 'end' | 'between' | 'around' | 'evenly'
const alignMap = { stretch: 'stretch', start: 'flex-start', center: 'center', end: 'flex-end', baseline: 'baseline' }
const justifyMap = { start: 'flex-start', center: 'center', end: 'flex-end', between: 'space-between', around: 'space-around', evenly: 'space-evenly' }

export default defineComponent({
    name: 'HsxStack',
    props: {
        direction: { type: String as PropType<Direction>, default: 'row' }, align: { type: String as PropType<Align>, default: 'center' },
        justify: { type: String as PropType<Justify>, default: 'start' }, gap: { type: [Number, String], default: 16 }, wrap: { type: Boolean, default: false }
    },
    setup(props, { slots, attrs }) {
        return () => {
            const View = 'view' as any
            return <View {...attrs} class={['hsx-mobile-stack', attrs.class]} style={[attrs.style as any, {
                display: 'flex', minWidth: 0, flexDirection: props.direction, alignItems: alignMap[props.align], justifyContent: justifyMap[props.justify],
                flexWrap: props.wrap ? 'wrap' : 'nowrap', gap: typeof props.gap === 'number' ? `${props.gap}rpx` : props.gap
            }]}>{slots.default?.()}</View>
        }
    }
})
