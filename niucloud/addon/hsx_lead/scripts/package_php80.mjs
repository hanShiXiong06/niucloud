import { readFile, mkdir, writeFile, lstat, rename, rm } from 'node:fs/promises'
import { createHash, randomUUID } from 'node:crypto'
import { spawnSync } from 'node:child_process'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

// Fixed repair list: no SQL, vendor, framework, credentials or unrelated work.
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../..')
const output = path.join(root, 'output/hsx-lead-php80-fix-20261031')
const files = [
  'app/auth/GuzzleCaptchaTransport.php', 'app/auth/TwoCaptchaRecognizer.php',
  'app/dict/menu/site.php', 'app/job/SyncInventory.php', 'app/middleware/SafeErrors.php',
  'app/provider/AbstractProvider.php', 'app/provider/AiguanjiProvider.php',
  'app/provider/GuzzleJsonTransport.php', 'app/provider/JixiaoerProvider.php',
  'app/provider/ProviderDefinition.php', 'app/provider/ProviderException.php',
  'app/provider/ProviderRegistry.php', 'app/provider/SoujikeProvider.php',
  'app/provider/WanjiProvider.php', 'app/service/AuthChallengeService.php',
  'app/service/AutomaticAuthService.php', 'app/service/CaptchaConfigService.php',
  'app/service/CatalogService.php', 'app/service/ConnectionService.php',
  'app/service/Context.php', 'app/service/DealProgress.php', 'app/service/MerchantService.php',
  'app/service/NoticeConfigService.php', 'app/service/ReadService.php',
  'app/service/SyncService.php', 'app/service/WorkflowService.php',
  'app/support/Cipher.php', 'app/support/Data.php', 'app/support/PhpRuntime.php',
  'docs/PHP80_FIX.md'
].map(name => 'addon/hsx_lead/' + name).sort()
const manifest = []
for (const name of files) {
  const source = path.join(root, 'niucloud', name)
  if (!(await lstat(source)).isFile()) throw new Error('Non-file patch source rejected: ' + name)
  const bytes = await readFile(source)
  if (/eyJ[A-Za-z0-9_-]{40,}\.[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{20,}/.test(bytes.toString('utf8'))) throw new Error('Inline token rejected: ' + name)
  manifest.push({ path: name, bytes: bytes.length, sha256: createHash('sha256').update(bytes).digest('hex') })
}
await mkdir(output, { recursive: true })
const archive = path.join(output, 'hsx-lead-1.1.0-php80-backend.zip')
const temporary = path.join(output, '.patch-' + randomUUID() + '.zip')
// -X excludes platform metadata; zip paths are relative to the PHP application.
const result = spawnSync('zip', ['-q', '-X', temporary, '-@'], {
  cwd: path.join(root, 'niucloud'), input: files.join('\n') + '\n', encoding: 'utf8'
})
if (result.status !== 0) {
  await rm(temporary, { force: true })
  throw new Error('Repair archive failed')
}
await rename(temporary, archive)
const bytes = await readFile(archive)
const hash = createHash('sha256').update(bytes).digest('hex')
await writeFile(path.join(output, 'manifest.json'), JSON.stringify({
  plugin: 'hsx_lead', base_version: '1.1.0', repair: 'php80_top_level_menu',
  built_at: new Date().toISOString(), archive: path.basename(archive), archive_sha256: hash,
  php_files: 29, framework_files: [], sql_files: [], frontend_files: [], files: manifest
}, null, 2) + '\n')
await writeFile(path.join(output, 'SHA256SUMS'), hash + '  ' + path.basename(archive) + '\n')
console.log(JSON.stringify({ archive, bytes: bytes.length, php_files: 29, files: files.length, sha256: hash }))
