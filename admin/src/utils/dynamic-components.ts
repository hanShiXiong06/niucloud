// One lazy registry for framework and add-on extension points. Keep the full
// /src/... keys used by backend dictionaries; sharing must not narrow plug-ins.
export const dynamicComponentModules: Record<string, () => Promise<any>> = import.meta.glob('@/**/*.vue')
