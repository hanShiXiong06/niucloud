import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { spawnSync } from 'node:child_process'

// Deliberately excludes installed-site/browser diagnostics and real ERP calls.
const directory = path.dirname(fileURLToPath(import.meta.url))
const php = process.env.PHP_BIN || 'php'
const mode = process.argv[2] || 'pure'
if (!['pure', 'database', 'ui'].includes(mode)) throw Error('Usage: PHP_BIN=/path/to/php node tests/run.mjs [pure|database|ui]')
const phpOptions = ['-d', 'allow_url_fopen=0', '-d', 'disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect']
const results = []
for (const name of fs.readdirSync(directory).filter(name => mode === 'ui' ? name.endsWith('-ui.mjs') : name.endsWith('.php') && name !== 'bootstrap.php' && !name.endsWith('-fixtures.php')).sort()) {
    const file = path.join(directory, name)
    const isolated = fs.readFileSync(file, 'utf8').includes('--isolated-local-mysql')
    if (mode !== 'ui' && (mode === 'database') !== isolated) continue
    const result = spawnSync(mode === 'ui' ? process.execPath : php, mode === 'ui' ? [file] : [...phpOptions, file, ...(isolated ? ['--isolated-local-mysql'] : [])], { encoding: 'utf8', timeout: 120000, maxBuffer: 1024 * 1024 })
    const output = (result.stdout + result.stderr).trim()
    const pass = result.status === 0 && !result.error
    results.push({ file: name, passed: pass, output, exit_code: result.status, signal: result.signal, runner_error: result.error?.code || null })
    console.log(`${pass ? 'PASS' : 'FAIL'} ${name}: ${output}`)
    if (!pass) console.log(JSON.stringify({ failed_file: name, exit_code: result.status, signal: result.signal, runner_error: result.error?.code || null }))
}
console.log(JSON.stringify({ mode, suites: results.length, passed: results.filter(result => result.passed).length, failed: results.filter(result => !result.passed).length, scope: mode === 'database' ? 'Owned random localhost test schemas only; no existing business rows; external network functions disabled' : mode === 'ui' ? 'Actual Vue setup/render with synthetic API doubles; no live APIs or browser visual acceptance' : 'Synthetic data and transports only; external network functions disabled' }))
if (results.some(result => !result.passed)) process.exitCode = 1
