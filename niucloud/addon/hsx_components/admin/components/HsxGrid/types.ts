export type HsxGridBreakpoint = 'xs' | 'sm' | 'md' | 'lg' | 'xl'

export type HsxResponsiveNumber = number | Partial<Record<HsxGridBreakpoint, number>>

export interface HsxGridSurfaceProps {
    background?: string
    backgroundColor?: string
    gradient?: string
    backgroundImage?: string
    backgroundSize?: string
    backgroundPosition?: string
    backgroundRepeat?: string
    overlay?: string
    color?: string
    padding?: string | number
    radius?: string | number
    border?: string
    shadow?: boolean | string
    minHeight?: string | number
}
