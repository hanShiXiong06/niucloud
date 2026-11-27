// Dev-only: wrap over-long PHP lines without changing the AST.
//
// Two layout passes, repeated until stable:
//   1. conditions: break before the outermost `||` / `&&` of a long line;
//   2. arrays:     put each element of the outermost long array literal on its own line.
// Both only insert a line break + indentation between two tokens (and drop the
// spaces they replace). Whitespace between tokens never changes a PHP AST, so the
// result must pass tools/format/ast-compare.php unchanged. No trailing commas are added.
//
// A small lexer skips single/double-quoted strings (incl. {$...} interpolation),
// line and block comments. Any file with heredoc/nowdoc, a closing `?>` in code,
// unbalanced brackets or a construct the lexer does not understand is left
// untouched and reported.
//
// Usage: node wrap-conditions.mjs [--limit=160] [--check] <file-or-dir>...

import { readFileSync, writeFileSync, statSync, readdirSync } from 'node:fs'
import path from 'node:path'

const args = process.argv.slice(2)
const limit = Number((args.find((a) => a.startsWith('--limit=')) || '--limit=160').slice(8))
const checkOnly = args.includes('--check')
const targets = args.filter((a) => !a.startsWith('--'))
if (!targets.length) {
    console.error('Usage: node wrap-conditions.mjs [--limit=160] [--check] <file-or-dir>...')
    process.exit(2)
}

class Unsupported extends Error {}

function collect(target, out) {
    const stat = statSync(target)
    if (stat.isDirectory()) {
        for (const name of readdirSync(target)) {
            if (name === 'vendor' || name === 'node_modules') continue
            collect(path.join(target, name), out)
        }
    } else if (target.endsWith('.php')) out.push(target)
    return out
}

const IDENT = /[A-Za-z0-9_\x80-￿]/
// After these words a `[` starts an array literal, not an index access.
const LITERAL_AFTER_WORD = new Set([
    'return', 'yield', 'echo', 'print', 'in', 'as', 'else', 'case', 'throw', 'and', 'or', 'xor', 'fn', 'use', 'from'
])

/**
 * Lexes PHP code regions. Returns:
 *  ops:    `||` / `&&` positions with paren+bracket depth
 *  arrays: array-literal bracket pairs {open, close, depth, id}
 *  commas: {index, owner} where owner is the id of the innermost open bracket/paren/brace
 *  lineStartDepth / lineStartCode per physical line
 */
function lex(src) {
    const ops = []
    const arrays = []
    const commas = []
    const lineStartDepth = [0]
    const lineStartCode = [true]
    const stack = []
    let nextId = 1
    let depth = 0
    let state = 'code'
    let interpBraces = 0
    let prevSig = ''
    let word = ''
    let lastWord = ''
    if (!src.startsWith('<?php')) throw new Unsupported('does not start with <?php')
    let i = 5
    const n = src.length
    while (i < n) {
        const c = src[i]
        const d = src[i + 1]
        if (c === '\n') {
            if (state === 'lc') state = 'code'
            lineStartDepth.push(depth)
            lineStartCode.push(state === 'code')
            if (word) {
                lastWord = word
                word = ''
            }
            i++
            continue
        }
        switch (state) {
            case 'code':
            case 'interp': {
                if (c === "'") {
                    state = state === 'code' ? 'sq' : 'sq-interp'
                    prevSig = c
                    word = ''
                    lastWord = ''
                    i++
                    continue
                }
                if (c === '"' || c === '`') {
                    if (state === 'interp') throw new Unsupported('double quote inside interpolation')
                    state = c === '"' ? 'dq' : 'bt'
                    prevSig = c
                    word = ''
                    lastWord = ''
                    i++
                    continue
                }
                if (c === '/' && d === '/') {
                    if (state === 'interp') throw new Unsupported('comment inside interpolation')
                    state = 'lc'
                    i += 2
                    continue
                }
                if (c === '#' && d !== '[') {
                    if (state === 'interp') throw new Unsupported('comment inside interpolation')
                    state = 'lc'
                    i++
                    continue
                }
                if (c === '/' && d === '*') {
                    if (state === 'interp') throw new Unsupported('comment inside interpolation')
                    state = 'bc'
                    i += 2
                    continue
                }
                if (c === '<' && src.startsWith('<<<', i)) throw new Unsupported('heredoc/nowdoc')
                if (c === '?' && d === '>') throw new Unsupported('closing tag in code')
                if (state === 'interp') {
                    if (c === '{') interpBraces++
                    else if (c === '}' && --interpBraces === 0) state = 'dq'
                    i++
                    continue
                }
                if (c === ' ' || c === '\t' || c === '\r') {
                    if (word) {
                        lastWord = word
                        word = ''
                    }
                    i++
                    continue
                }
                if (IDENT.test(c) || c === '$') {
                    word = IDENT.test(c) && IDENT.test(prevSig) && word ? word + c : c === '$' ? '' : c
                    if (c !== '$' && !word) word = c
                    prevSig = c
                    i++
                    continue
                }
                if (word) {
                    lastWord = word
                    word = ''
                }
                if (c === '(' || c === '[' || c === '{') {
                    let isArray = false
                    if (c === '[') {
                        isArray = !(IDENT.test(prevSig) || prevSig === ')' || prevSig === ']' || prevSig === '}' || prevSig === "'" || prevSig === '"' || prevSig === '$')
                        if (!isArray && IDENT.test(prevSig) && LITERAL_AFTER_WORD.has(lastWord.toLowerCase())) isArray = true
                    }
                    stack.push({ ch: c, index: i, depth, id: nextId++, isArray })
                    if (c !== '{') depth++
                } else if (c === ')' || c === ']' || c === '}') {
                    const open = stack.pop()
                    const want = c === ')' ? '(' : c === ']' ? '[' : '{'
                    if (!open || open.ch !== want) throw new Unsupported('unbalanced brackets')
                    if (c !== '}') depth--
                    if (open.isArray) arrays.push({ open: open.index, close: i, depth: open.depth, id: open.id })
                } else if (c === ',') {
                    commas.push({ index: i, owner: stack.length ? stack[stack.length - 1].id : 0 })
                } else if ((c === '|' && d === '|') || (c === '&' && d === '&')) {
                    // Operators inside an array literal are left to the array pass (one element per line).
                    ops.push({ index: i, depth, inArray: stack.some((s) => s.isArray) })
                    prevSig = c
                    i += 2
                    continue
                }
                prevSig = c
                i++
                continue
            }
            case 'sq':
            case 'sq-interp':
                if (c === '\\') {
                    // Never step over a newline: line bookkeeping happens at the top of the loop.
                    i += d === '\n' ? 1 : 2
                    continue
                }
                if (c === "'") state = state === 'sq' ? 'code' : 'interp'
                i++
                continue
            case 'dq':
            case 'bt':
                if (c === '\\') {
                    i += d === '\n' ? 1 : 2
                    continue
                }
                if ((c === '"' && state === 'dq') || (c === '`' && state === 'bt')) {
                    state = 'code'
                    i++
                    continue
                }
                if (c === '{' && d === '$') {
                    if (state === 'bt') throw new Unsupported('interpolation in backticks')
                    state = 'interp'
                    interpBraces = 1
                    i += 2
                    continue
                }
                if (c === '$' && d === '{') throw new Unsupported('${ } interpolation')
                i++
                continue
            case 'lc':
                i++
                continue
            case 'bc':
                if (c === '*' && d === '/') {
                    state = 'code'
                    i += 2
                    continue
                }
                i++
                continue
        }
    }
    if (state !== 'code' && state !== 'lc') throw new Unsupported('unterminated ' + state)
    if (depth !== 0 || stack.length) throw new Unsupported('unbalanced brackets')
    return { ops, arrays, commas, lineStartDepth, lineStartCode }
}

function lineIndex(src) {
    const starts = [0]
    for (let k = 0; k < src.length; k++) if (src[k] === '\n') starts.push(k + 1)
    const of = (pos) => {
        let lo = 0
        let hi = starts.length - 1
        while (lo < hi) {
            const mid = (lo + hi + 1) >> 1
            if (starts[mid] <= pos) lo = mid
            else hi = mid - 1
        }
        return lo
    }
    const text = (ln) => {
        const end = ln + 1 < starts.length ? starts[ln + 1] - 1 : src.length
        return src.slice(starts[ln], end).replace(/\r$/, '')
    }
    return { starts, of, text }
}

const spaceBefore = (src, pos, floor) => {
    let from = pos
    while (from > floor && (src[from - 1] === ' ' || src[from - 1] === '\t')) from--
    return from
}
const spaceAfter = (src, pos) => {
    let to = pos
    while (src[to] === ' ' || src[to] === '\t') to++
    return to
}

function applyEdits(src, edits) {
    edits.sort((a, b) => b.from - a.from)
    let out = src
    for (const e of edits) out = out.slice(0, e.from) + e.text + out.slice(e.to)
    return out
}

function wrapConditions(src, eol) {
    const { ops, lineStartDepth, lineStartCode } = lex(src)
    const lines = lineIndex(src)
    const byLine = new Map()
    for (const op of ops) {
        if (op.inArray) continue
        const ln = lines.of(op.index)
        if (!byLine.has(ln)) byLine.set(ln, [])
        byLine.get(ln).push(op)
    }
    const edits = []
    let wrapped = 0
    for (const [ln, lineOps] of byLine) {
        const text = lines.text(ln)
        if (!lineStartCode[ln] || text.length <= limit) continue
        const target = Math.min(...lineOps.map((op) => op.depth))
        const indent = text.match(/^[ \t]*/)[0] + '    '.repeat(Math.max(1, target - lineStartDepth[ln]))
        let any = false
        for (const op of lineOps.filter((o) => o.depth === target)) {
            const from = spaceBefore(src, op.index, lines.starts[ln])
            if (from === lines.starts[ln]) continue
            edits.push({ from, to: op.index, text: eol + indent })
            any = true
        }
        if (any) wrapped++
    }
    return { out: applyEdits(src, edits), wrapped }
}

function wrapArrays(src, eol) {
    const { arrays, commas, lineStartDepth, lineStartCode } = lex(src)
    const lines = lineIndex(src)
    const commasByOwner = new Map()
    for (const comma of commas) {
        if (!commasByOwner.has(comma.owner)) commasByOwner.set(comma.owner, [])
        commasByOwner.get(comma.owner).push(comma.index)
    }
    // Candidate: same-line array literal on a long line, with at least two direct elements separators.
    const candidates = arrays.filter((a) => {
        const ln = lines.of(a.open)
        return (
            ln === lines.of(a.close) &&
            lineStartCode[ln] &&
            lines.text(ln).length > limit &&
            (commasByOwner.get(a.id) || []).length >= 2
        )
    })
    // Outermost candidates only; inner ones are handled on the next pass if still long.
    const outer = candidates.filter((a) => !candidates.some((b) => b !== a && b.open < a.open && b.close > a.close))
    const edits = []
    for (const a of outer) {
        const ln = lines.of(a.open)
        const leading = lines.text(ln).match(/^[ \t]*/)[0]
        const rel = Math.max(0, a.depth - lineStartDepth[ln])
        const elementIndent = eol + leading + '    '.repeat(rel + 1)
        const closeIndent = eol + leading + '    '.repeat(rel)
        edits.push({ from: a.open + 1, to: spaceAfter(src, a.open + 1), text: elementIndent })
        for (const comma of commasByOwner.get(a.id)) {
            const next = spaceAfter(src, comma + 1)
            if (src[next] === ']') continue // existing trailing comma: the closing break below handles it
            edits.push({ from: comma + 1, to: next, text: elementIndent })
        }
        edits.push({ from: spaceBefore(src, a.close, a.open + 1), to: a.close, text: closeIndent })
    }
    return { out: applyEdits(src, edits), wrapped: outer.length }
}

function wrap(src) {
    const eol = src.includes('\r\n') ? '\r\n' : '\n'
    let out = src
    let conditions = 0
    let arrays = 0
    for (let pass = 0; pass < 8; pass++) {
        const c = wrapConditions(out, eol)
        const a = wrapArrays(c.out, eol)
        conditions += c.wrapped
        arrays += a.wrapped
        if (a.out === out) break
        out = a.out
    }
    return { out, conditions, arrays }
}

let changedFiles = 0
let totalConditions = 0
let totalArrays = 0
const skipped = []
for (const file of targets.flatMap((t) => collect(t, []))) {
    const src = readFileSync(file, 'utf8')
    let result
    try {
        result = wrap(src)
    } catch (error) {
        if (error instanceof Unsupported) {
            skipped.push(`${file}: ${error.message}`)
            continue
        }
        throw error
    }
    if (result.out === src) continue
    changedFiles++
    totalConditions += result.conditions
    totalArrays += result.arrays
    if (!checkOnly) writeFileSync(file, result.out, 'utf8')
    console.log(`${checkOnly ? 'would wrap' : 'wrapped'} ${result.conditions} condition line(s), ${result.arrays} array(s): ${file}`)
}
console.log(`\n${changedFiles} file(s), ${totalConditions} condition line(s), ${totalArrays} array(s) wrapped (limit ${limit}).`)
if (skipped.length) console.log(`Skipped (left untouched):\n  ${skipped.join('\n  ')}`)
if (checkOnly && changedFiles) process.exit(1)
