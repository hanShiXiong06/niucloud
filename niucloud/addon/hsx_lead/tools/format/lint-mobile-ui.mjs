// Mobile design-spec check for hsx_lead (docs/MOBILE_DESIGN_SPEC.md).
//
// Default: checks only lines added or changed relative to origin/main (committed, uncommitted and
// untracked files under niucloud/addon/hsx_lead/uni-app), so legacy code never blocks a commit.
//   node niucloud/addon/hsx_lead/tools/format/lint-mobile-ui.mjs            # changed lines, exit 1 on violations
//   node niucloud/addon/hsx_lead/tools/format/lint-mobile-ui.mjs --all      # whole tree, report only (exit 0)
//   node ... --base=<ref>                                                   # compare with another ref
import { execFileSync } from 'node:child_process'
import { readFileSync, readdirSync, statSync, existsSync } from 'node:fs'
import path from 'node:path'

const args = process.argv.slice(2)
const all = args.includes('--all')
const baseRef = (args.find((a) => a.startsWith('--base=')) || '--base=origin/main').slice(7)
const git = (...a) => execFileSync('git', a, { encoding: 'utf8', maxBuffer: 1 << 28 }).trim()
const root = git('rev-parse', '--show-toplevel')
const scope = 'niucloud/addon/hsx_lead/uni-app'

// Files that may keep their own colors (spec §1 exceptions) and the token source itself.
const EXEMPT = [
    /\/styles\/tokens\.scss$/,
    /\/components\/diy\//,
    /\/utils\/membership-(skin|visual)\.ts$/,
    /\/components\/cooperation\/CoopInvitePoster\.vue$/,
    /\/components\/cooperation\/CoopLevelBadge\.vue$/
]
const CHECKED = /\.(vue|scss|css|ts)$/

const RULES = [
    {
        id: 'bare-color',
        test: (line, file) =>
            !file.endsWith('.ts') && /(^|[^&\w-])#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{5})?\b/.test(line) && !/^\s*(\/\/|\*|\/\*)/.test(line),
        message: '裸 hex 颜色：改用 tokens.scss 的 $hsx-* 令牌（规范 §1）'
    },
    {
        id: 'bare-rgb',
        test: (line, file) => !file.endsWith('.ts') && /\brgba?\(\s*\d/.test(line) && !/^\s*(\/\/|\*|\/\*)/.test(line),
        message: 'rgb()/rgba() 字面量：改用令牌（规范 §1）'
    },
    {
        id: 'small-font',
        test: (line) => /font-size:\s*(?:[0-9]|1[01])(?:\.\d+)?px/.test(line),
        message: '字号小于 12px（规范 §3）'
    },
    {
        id: 'transition-all',
        test: (line) => /transition:\s*all\b/.test(line),
        message: '不要写 transition: all，列出具体属性并使用动效令牌（规范 §5）'
    },
    {
        id: 'raw-toast',
        // The lookahead consumes the spaces itself: with `title:\s*(?!quote)` the \s* can back off to zero
        // and the lookahead then sees a space, flagging literal titles such as `title: '已复制'`.
        test: (line) => /uni\.showToast\(\{\s*title:(?!\s*['"`])/.test(line),
        message: '动态文字提示请用 coopNotice()，避免被微信截断（规范 §7）'
    },
    {
        id: 'button-negative-margin',
        test: (line) => /button[^{]*\{[^}]*margin[^;}]*:\s*[^;}]*-\d/.test(line),
        message: '按钮负外边距会造成点击区域重叠，改用 hsx-tap-target 或把元素做到 44px（规范 §5）'
    }
]

function walk(dir, out = []) {
    for (const name of readdirSync(dir)) {
        if (name === 'node_modules') continue
        const p = path.join(dir, name)
        statSync(p).isDirectory() ? walk(p, out) : out.push(p)
    }
    return out
}

/** Map of repo-relative file -> array of [lineNumber, text] to check. */
function changedLines() {
    const result = new Map()
    let base
    try {
        base = git('merge-base', baseRef, 'HEAD')
    } catch {
        console.error(`找不到对比基线 ${baseRef}，请先 git fetch，或用 --base=<ref> 指定`)
        process.exit(2)
    }
    // Committed + uncommitted changes of tracked files, compared with the merge base.
    const diff = git('diff', '-U0', '--no-color', base, '--', scope)
    let file = null
    let lineNo = 0
    for (const raw of diff.split('\n')) {
        if (raw.startsWith('+++ ')) {
            file = raw.startsWith('+++ b/') ? raw.slice(6) : null
            continue
        }
        const hunk = raw.match(/^@@ -\d+(?:,\d+)? \+(\d+)(?:,\d+)? @@/)
        if (hunk) {
            lineNo = Number(hunk[1])
            continue
        }
        if (!file || raw.startsWith('---')) continue
        if (raw.startsWith('+')) {
            if (!result.has(file)) result.set(file, [])
            result.get(file).push([lineNo, raw.slice(1)])
            lineNo++
        }
    }
    // Untracked new files count in full.
    const untracked = git('ls-files', '--others', '--exclude-standard', '--', scope)
    for (const rel of untracked ? untracked.split('\n') : []) {
        const lines = readFileSync(path.join(root, rel), 'utf8').split(/\r?\n/)
        result.set(rel, lines.map((text, i) => [i + 1, text]))
    }
    return result
}

function allLines() {
    const result = new Map()
    const dir = path.join(root, scope)
    if (!existsSync(dir)) return result
    for (const abs of walk(dir)) {
        const rel = path.relative(root, abs).split(path.sep).join('/')
        result.set(rel, readFileSync(abs, 'utf8').split(/\r?\n/).map((text, i) => [i + 1, text]))
    }
    return result
}

const targets = all ? allLines() : changedLines()
const findings = []
for (const [file, lines] of targets) {
    if (!CHECKED.test(file) || EXEMPT.some((re) => re.test('/' + file))) continue
    for (const [lineNo, text] of lines) {
        for (const rule of RULES) if (rule.test(text, file)) findings.push({ file, lineNo, rule, text: text.trim() })
    }
}

if (all) {
    const byRule = {}
    for (const f of findings) byRule[f.rule.id] = (byRule[f.rule.id] || 0) + 1
    console.log(`全量扫描 ${scope}（仅报告，不阻塞）：`)
    for (const rule of RULES) console.log(`  ${rule.id.padEnd(24)} ${byRule[rule.id] || 0}`)
    process.exit(0)
}

if (!findings.length) {
    console.log(`移动端设计规范检查通过（相对 ${baseRef} 的新增/改动行）。`)
    process.exit(0)
}
for (const f of findings) console.log(`${f.file}:${f.lineNo}  [${f.rule.id}] ${f.rule.message}\n    ${f.text.slice(0, 160)}`)
console.log(`\n${findings.length} 处不符合移动端设计规范：niucloud/addon/hsx_lead/docs/MOBILE_DESIGN_SPEC.md`)
process.exit(1)
