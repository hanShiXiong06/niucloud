import prettier from 'prettier'
import { parse as parseTemplate } from '@vue/compiler-dom'
import { parse as parseSfc } from '@vue/compiler-sfc'

// NodeTypes from Vue's template AST. These const-enum members have no runtime export in Vue 3.2.
const ELEMENT = 1
const TEXT = 2
const INTERPOLATION = 5
const layoutOnly = /^[\t\r\n\f ]*$/

function templateBlock(source, filename) {
    const { descriptor, errors } = parseSfc(source, { filename })
    if (errors.length) throw new Error(`${filename}: ${errors.map(e => e.message ?? e).join('; ')}`)
    const block = descriptor.template
    if (!block) return null
    if (block.lang && block.lang !== 'html') throw new Error(`${filename}: unsupported Vue template language ${block.lang}`)
    return {
        source: block.content,
        offset: block.loc.start.offset,
        ast: parseTemplate(block.content, { comments: true, whitespace: 'preserve' })
    }
}

const children = node => (node.children || []).filter(child => child.type !== TEXT || !layoutOnly.test(child.content))

function verbatimContents(node) {
    if (node.isSelfClosing) return ''
    // Vue has already recognized the element and its attributes. Their end offsets
    // let us find the actual '>' without confusing one inside a quoted attribute.
    const source = node.loc.source
    const lastAttr = node.props?.at(-1)
    const searchFrom = lastAttr ? lastAttr.loc.end.offset - node.loc.start.offset : node.tag.length + 1
    const start = source.indexOf('>', searchFrom), end = source.lastIndexOf('</')
    if (start < 0 || end <= start) throw new Error(`Cannot locate content of <${node.tag}>`)
    return source.slice(start + 1, end)
}

// Prettier 3.3.3 loses some rendered space between adjacent elements and grows blank
// lines in wrapped {{ expressions }} with embedded languages off. Patch only actual
// template node boundaries, never search/replace arbitrary SFC/script/style text.
export function restoreTemplateWhitespace(original, printed, filename) {
    const before = templateBlock(original, filename)
    const after = templateBlock(printed, filename)
    if (!before && !after) return printed
    const fail = reason => { throw new Error(`${filename}: ${reason}; refusing to format`) }
    if (!before || !after) fail('template added or removed')
    const edits = []
    function visit(a, b) {
        if (a.type !== b.type || a.tag !== b.tag) fail('template node mismatch')
        if (a.tag === 'pre' || a.tag === 'textarea') {
            if (verbatimContents(a) !== verbatimContents(b)) fail('verbatim text changed')
            return
        }
        if (a.props?.some(p => p.name === 'v-pre' || p.name === 'pre')) {
            if (a.loc.source !== b.loc.source) fail('v-pre subtree changed')
            return
        }
        if (b.type === INTERPOLATION) {
            const end = b.loc.end.offset - 2
            if (after.source.slice(end, end + 2) !== '}}') fail('unknown interpolation delimiter')
            const body = after.source.slice(b.loc.start.offset + 2, end)
            // Only trailing layout outside the expression. Keep the last physical newline
            // (including CRLF and indentation): a // comment must never swallow the delimiter.
            const blankLines = body.match(/(?:\r?\n[\t ]*){2,}$/u)?.[0]
            if (blankLines) {
                const lastLine = blankLines.match(/\r?\n[\t ]*$/u)[0]
                edits.push([end - blankLines.length, end, lastLine])
            }
        }
        const oldNodes = children(a)
        const newNodes = children(b)
        if (oldNodes.length !== newNodes.length) fail('template children changed')
        for (let i = 0; i < oldNodes.length; i++) {
            const x = oldNodes[i], y = newNodes[i]
            if (i && x.type === ELEMENT && oldNodes[i - 1].type === ELEMENT &&
                y.type === ELEMENT && newNodes[i - 1].type === ELEMENT) {
                const oldGap = before.source.slice(oldNodes[i - 1].loc.end.offset, x.loc.start.offset)
                const start = newNodes[i - 1].loc.end.offset, end = y.loc.start.offset
                const gap = after.source.slice(start, end)
                if (/^[\t ]+$/u.test(oldGap) && gap !== ' ') {
                    if (!layoutOnly.test(gap)) fail('unexpected content between sibling elements')
                    edits.push([start, end, ' '])
                }
            }
            visit(x, y)
        }
    }
    visit(before.ast, after.ast)
    let output = printed, boundary = after.source.length
    // Descending offsets keep all original AST positions valid.
    for (const [start, end, text] of edits.sort((a, b) => b[0] - a[0])) {
        if (start < 0 || start > end || end > boundary) fail('overlapping template edits')
        output = output.slice(0, after.offset + start) + text + output.slice(after.offset + end)
        boundary = start
    }
    return output
}

export async function formatVue(source, options) {
    if (options.embeddedLanguageFormatting !== 'off') {
        throw new Error(`${options.filepath}: Vue requires embeddedLanguageFormatting: off`)
    }
    for (let pass = 0; pass < 3; pass++) {
        const next = restoreTemplateWhitespace(source, await prettier.format(source, options), options.filepath)
        if (next === source) return next
        source = next
    }
    throw new Error(`${options.filepath}: Vue layout did not stabilize within three passes`)
}
