import type { Component, InjectionKey } from 'vue'

export type PermissionChecker = (permission?: string | string[]) => boolean

export const HSX_PERMISSION_CHECKER: InjectionKey<PermissionChecker> = Symbol('HSX_PERMISSION_CHECKER')
export const HSX_FORM_COMPONENTS: InjectionKey<Record<string, Component>> = Symbol('HSX_FORM_COMPONENTS')
