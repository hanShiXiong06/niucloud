import { readFile, readdir, mkdir, writeFile, lstat, realpath, rename, rm } from 'node:fs/promises'
import { createHash, randomUUID } from 'node:crypto'
import { spawnSync } from 'node:child_process'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

// Plugin sources plus explicit, separately documented maintenance entries; not an implicit installer.
// Only this plugin's installation sources are read. Mounted application sources
// may not exist after uninstall and are deliberately not a packaging dependency.
const plugin = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const root = path.resolve(plugin, '../../..')
const prefix = 'niucloud/addon/hsx_lead'
if (path.join(root, prefix) !== plugin) throw new Error('Unexpected plugin location')
const args = process.argv.slice(2)
if (args.some(arg => arg !== '--check')) throw new Error('Usage: node scripts/package.mjs [--check]')

const info = JSON.parse(await readFile(path.join(plugin, 'info.json'), 'utf8'))
const version = info.version
if (info.key !== 'hsx_lead' || typeof version !== 'string' || !/^\d+\.\d+\.\d+$/.test(version)) {
    throw new Error('Invalid plugin identity or version')
}
const releaseDate = process.env.HSX_LEAD_RELEASE_DATE || new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Shanghai', year: 'numeric', month: '2-digit', day: '2-digit'
}).format(new Date()).replaceAll('-', '')
if (!/^\d{8}$/.test(releaseDate)) throw new Error('Release date must be YYYYMMDD')

const runtimeDirectories = ['app', 'admin', 'uni-app', 'package', 'resource']
const installationFiles = ['Addon.php', 'info.json', 'README.md', 'install.sql', 'uninstall.sql', 'sql/install.sql', 'sql/uninstall.sql']
// Dedicated, additive runtime schema only. Do not allow arbitrary SQL files via an extension glob.
const schemaFiles = ['sql/notice-runtime.sql', 'sql/manual-receipts.sql', 'sql/virtual-payment.sql']
const guideFiles = ['docs/COOPERATION_GUIDE.md', 'docs/ERP_INTEGRATION.md', 'docs/CONFIGURATION.md', 'docs/DELIVERY_EXPERIENCE.md', 'docs/PROMOTION_INTEGRATION.md', 'docs/TEST_REPORT.md', 'docs/PHASE_ONE_GUIDE.md', 'docs/PHASE_ONE_TEST_REPORT.md', 'docs/LOCAL_ACCEPTANCE_20261105.md', 'docs/PAYMENT_PROFILE_DIY_20261105.md', 'docs/NATIVE_DIY_CONNECTION_UI_20261105.md', 'docs/BUSINESS_LICENSE_20261105.md', 'docs/MOBILE_UI_AUDIT_20261105.md', 'docs/UPGRADE_COMMAND_20261105.md']
// Do not include the development scripts directory or temporary repair tools.
guideFiles.push('docs/CATALOG_RETURN_FIX_20261105.md', 'docs/INVENTORY_INITIAL_LOAD_FIX_20261105.md', 'docs/ROLE_TABS_UI_20261105.md')
guideFiles.push('docs/PHONE_SERVICES_20261106.md')
guideFiles.push('docs/ENTITY_MEMBERSHIP_20261106.md')
guideFiles.push('docs/ENTITY_MEMBERSHIP_TEST_20261106.md')
guideFiles.push('docs/DIY_APPEARANCE_20261106.md')
guideFiles.push('docs/POSTER_SHARE_20261106.md')
guideFiles.push('docs/MINIPROGRAM_LOGIN_LAYOUT_20261106.md', 'docs/DEPOSIT_REFUND_IDENTITY_20261106.md')
guideFiles.push('docs/FIRST_LIST_LOAD_20261106.md')
guideFiles.push('docs/ERP_CHANNEL_ALIASES_20261106.md')
guideFiles.push('docs/PROMOTION_SOURCE_FIX_20261106.md')
guideFiles.push('docs/COOPERATION_ADMIN_RECOVERY_20261108.md','docs/CATALOG_ITEM_MANAGEMENT.md','docs/CATALOG_CAPABILITY_FIX.md')
guideFiles.push('docs/CODE_REVIEW_20261108.md')
guideFiles.push('docs/ORDER_REFUND_AND_ERP_20261108.md')
guideFiles.push('docs/MOBILE_LOGIN_SEARCH_PROMOTION_FIXES_20261108.md','docs/MEMBERSHIP_PAYMENT_RECOVERY_20261108.md')
guideFiles.push('docs/NOTICE_FRAMEWORK_PUSH_20261108.md')
guideFiles.push('docs/MERCHANT_LIST_RELATIONS_20261108.md')
guideFiles.push('docs/PROMOTION_LOGIN_ENTRY_20261108.md')
guideFiles.push('docs/MEMBER_ENTITLEMENTS_PROMOTION_SUMMARY_20261108.md')
guideFiles.push('docs/MENU_GROUPS_20261109.md', 'docs/NOTICE_RUNTIME_UPGRADE_20261109.md', 'docs/NOTICE_POLISH_20261109.md')
guideFiles.push('docs/PAYMENT_HELP_PHONE_SEARCH.md')
guideFiles.push('docs/MANUAL_RECEIPTS_AND_MEMBERSHIP_REFUND.md')
guideFiles.push('docs/MANUAL_FINANCE_REGRESSION.md')
guideFiles.push('docs/MANUAL_BALANCE_PAYMENT.md')
guideFiles.push('docs/VIRTUAL_PAYMENT_DEVELOPMENT.md')
guideFiles.push('docs/MEMBERSHIP_PRODUCT_ID_AND_EXPERIENCE.md')
guideFiles.push('docs/JKB_INTEGRATION.md')
const maintenanceFiles = ['scripts/upgrade.php', 'scripts/upgrade-entity-membership.php', 'scripts/clean-retired-membership.php', 'scripts/diagnose-promotion-config.php', 'scripts/upgrade-notice-runtime.php', 'scripts/upgrade-menu-groups.php', 'scripts/upgrade-manual-receipts.php', 'scripts/upgrade-virtual-payment.php']
const bannedDirectories = new Set(['.git', 'node_modules', 'dist', 'build', 'output', 'tests', 'scripts', 'runtime', '.vite', '__MACOSX'])
const allowedExtensions = new Set(['.php', '.vue', '.ts', '.js', '.mjs', '.json', '.css', '.scss', '.sass', '.less', '.png', '.svg', '.jpg', '.jpeg', '.webp', '.gif', '.ico', '.woff', '.woff2', '.ttf'])
const files = []

function safeName(relative) {
    if (relative.split('/').some(name => !name || name === '.' || name === '..' || /[\r\n\\\x00]/.test(name))) {
        throw new Error('Unsafe release path')
    }
    if (/\.env(?:\.|$)|curl\.txt|\.(pem|key|zip|log|bak|tmp|sqlite|db)$|~$/i.test(relative)) {
        throw new Error('Unexpected release file: ' + relative)
    }
}

async function inspect(relative, expectedDirectory = false) {
    safeName(relative)
    const absolute = path.join(plugin, relative)
    const stats = await lstat(absolute)
    if (stats.isSymbolicLink()) throw new Error('Symlink rejected: ' + relative)
    if (expectedDirectory && !stats.isDirectory()) throw new Error('Missing runtime directory: ' + relative)
    if (stats.isDirectory()) {
        const entries = await readdir(absolute, { withFileTypes: true })
        for (const entry of entries) {
            if (entry.name === '.DS_Store') continue
            if (entry.name.startsWith('.') || bannedDirectories.has(entry.name)) {
                throw new Error('Unexpected runtime directory or file: ' + path.posix.join(relative, entry.name))
            }
            await inspect(path.posix.join(relative, entry.name))
        }
    } else if (stats.isFile()) {
        if (!installationFiles.includes(relative) && !schemaFiles.includes(relative) && !guideFiles.includes(relative) && !allowedExtensions.has(path.extname(relative).toLowerCase())) {
            throw new Error('Unexpected runtime extension: ' + relative)
        }
        files.push(relative)
    } else throw new Error('Unsupported release object: ' + relative)
}

for (const folder of runtimeDirectories) await inspect(folder, true)
for (const file of [...installationFiles, ...schemaFiles, ...guideFiles, ...maintenanceFiles]) await inspect(file)
files.sort()
if (new Set(files).size !== files.length) throw new Error('Duplicate release source')

const pageSource = await readFile(path.join(plugin, 'package/uni-app-pages.php'), 'utf8')
const mobilePages = ['pages/index', 'pages/workbench/index', 'pages/stores/index', 'pages/wallet/index', 'pages/profile/index', 'pages/invite/index', 'pages/task/index', 'pages/catalog/index', 'pages/entity/index', 'pages/membership/index', 'pages/orders/index', 'pages/notifications/index']
const declaredPages = [...pageSource.matchAll(/"path"\s*:\s*"([^"]+)"/g)].map(match => match[1])
if (JSON.stringify([...declaredPages].sort()) !== JSON.stringify([...mobilePages].sort())) {
    throw new Error('The installer must register exactly the current mobile pages, including the public invitation entry and protected notification preferences')
}
for (const page of mobilePages) if (!files.includes('uni-app/' + page + '.vue')) {
    throw new Error('Registered mobile page is missing: ' + page)
}
for (const name of ['install.sql', 'uninstall.sql']) {
    const [entry, canonical] = await Promise.all([readFile(path.join(plugin, name)), readFile(path.join(plugin, 'sql', name))])
    if (!entry.equals(canonical)) throw new Error('SQL installation entries differ: ' + name)
}
const noticeRuntimeSql = await readFile(path.join(plugin, schemaFiles[0]), 'utf8')
const noticeRuntimeClean = noticeRuntimeSql.replace(/^\s*--[^\r\n]*$/gm, '')
const noticeStatement = /CREATE TABLE IF NOT EXISTS `\{\{prefix\}\}(hsx_lead_notice_[a-z_]+)`\s*\([\s\S]*?\)\s*ENGINE\s*=\s*InnoDB\s+DEFAULT\s+CHARSET\s*=\s*utf8mb4\s+COMMENT\s*=\s*'[^']*'\s*;/gi
const noticeStatements = [...noticeRuntimeClean.matchAll(noticeStatement)]
const noticeTables = ['hsx_lead_notice_task', 'hsx_lead_notice_attempt', 'hsx_lead_notice_preference']
if (noticeStatements.length !== noticeTables.length || noticeRuntimeClean.replace(noticeStatement, '').trim()
    || JSON.stringify(noticeStatements.map(match => match[1]).sort()) !== JSON.stringify([...noticeTables].sort())) {
    throw new Error('The notice runtime SQL must contain only the three dedicated CREATE TABLE definitions')
}
const freshSchema = await readFile(path.join(plugin, 'sql/install.sql'), 'utf8')
for (const statement of noticeStatements) if (!freshSchema.includes(statement[0])) throw new Error('Notice runtime SQL differs from the fresh installation schema: ' + statement[1])
const manualSql = await readFile(path.join(plugin, 'sql/manual-receipts.sql'), 'utf8')
const manualClean = manualSql.replace(/^\s*--[^\r\n]*$/gm, '')
const manualStatement = /CREATE TABLE IF NOT EXISTS `\{\{prefix\}\}(hsx_lead_manual_receipt)`\s*\([\s\S]*?\)\s*ENGINE\s*=\s*InnoDB\s+DEFAULT\s+CHARSET\s*=\s*utf8mb4\s+COMMENT\s*=\s*'[^']*'\s*;/gi
const manualStatements = [...manualClean.matchAll(manualStatement)]
if (manualStatements.length !== 1 || manualClean.replace(manualStatement, '').trim() || !freshSchema.includes(manualStatements[0][0])) throw new Error('Manual receipt upgrade must be exactly its canonical CREATE TABLE, with no financial-data mutation')
const virtualClean = (await readFile(path.join(plugin, 'sql/virtual-payment.sql'), 'utf8')).replace(/^\s*--[^\r\n]*$/gm, '')
const virtualPattern = /CREATE TABLE IF NOT EXISTS `\{\{prefix\}\}(hsx_lead_virtual_[a-z_]+)`\s*\([\s\S]*?\)\s*ENGINE\s*=\s*InnoDB\s+DEFAULT\s+CHARSET\s*=\s*utf8mb4\s+COMMENT\s*=\s*'[^']*'\s*;/gi
const virtualStatements = [...virtualClean.matchAll(virtualPattern)]
const virtualTables = ['hsx_lead_virtual_order', 'hsx_lead_virtual_refund']
if (virtualStatements.length !== 2 || virtualClean.replace(virtualPattern, '').trim()
    || JSON.stringify(virtualStatements.map(match=>match[1]).sort()) !== JSON.stringify([...virtualTables].sort())
    || virtualStatements.some(statement=>!freshSchema.includes(statement[0]))) throw new Error('Virtual payment schema must contain only its two canonical provider-record tables')

const content = new Map()
const fileManifest = []
for (const relative of files) {
    const buffer = await readFile(path.join(plugin, relative))
    const source = buffer.toString('utf8')
    if (/eyJ[A-Za-z0-9_-]{40,}\.[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{20,}/.test(source)
        || /-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/.test(source)) {
        throw new Error('Possible inline authentication secret: ' + relative)
    }
    content.set(relative, buffer)
    fileManifest.push({ path: prefix + '/' + relative, bytes: buffer.length, sha256: createHash('sha256').update(buffer).digest('hex') })
}
const manifest = {
    plugin: 'hsx_lead', version, package_type: 'fresh-install-source', built_at: new Date().toISOString(),
    archive_root: prefix, framework_files: [],
    required_framework_updates: [
        ...['niucloud/app/service/core/pay/CorePayChannelService.php','niucloud/app/dict/pay/PayChannelDict.php','niucloud/app/service/core/pay/CorePayService.php'].map(file=>({path:file,reason:'Optional driver metadata restricts terminal/trade scope and opts into pending-trade reuse. No plugin business rules are hardcoded. Ordinary payment drivers keep their existing behavior.'})),
        ...['CoreWeappServeService.php','CoreWeappMessageSecurity.php','CoreWeappMessageEncryptor.php','CoreWeappAuthenticatedMessage.php'].map(file=>({path:'niucloud/app/service/core/weapp/'+file,reason:'Optional authenticated-message context reuses the effective native SDK message configuration and a single decrypt, isolates signed inner fields, and keeps the normal message chain; no plugin names or financial business rules.'})),
        { path: 'niucloud/app/service/admin/wxoplatform/OplatformServerService.php', reason: 'The native authorized-miniapp message entry emits the same optional authenticated hook after unique tenant and signed authorizer binding; existing review handling remains unchanged.' },
        { path: 'niucloud/app/service/admin/pay/PayChannelService.php', reason: 'Literal secret-mask preservation and optional PayType config_normalizer consume the owning driver extension; no membership catalog is hardcoded into the framework.' },
        ...['components/pay/pay.vue','utils/virtual-payment.ts'].map(file=>({path:'uni-app/src/'+file,reason:'Optional generic cashier client_action obtains fresh WeChat login proof and invokes a native payment action; no plugin names or entitlement fulfillment in the framework client.'})),
        { path: 'niucloud/app/service/admin/member/MemberService.php', reason: 'List/detail use one optional batched member entitlement display extension, without changing personal levels.' },
        { path: 'niucloud/app/service/core/member/CoreMemberEntitlementDisplayService.php', reason: 'Generic read-only event aggregation, bounded batches and explicit unavailable presentation; no plugin queries in the framework.' },
        ...['views/member/member.vue','views/member/components/detail-member.vue','views/member/components/member-entitlements.vue','utils/member-entitlements.ts'].map(file=>({path:'admin/src/app/'+file,reason:'Generic optional entitlement rendering alongside unchanged personal membership; rebuild administration frontend.'})),
        { path: 'niucloud/app/service/admin/weapp/WeappTemplateService.php', reason: 'Generic pending-definition capability and early retrieval guard; required before exposing unconfigured notification scenes. Empty public IDs never reach deletion or provider calls.' },
        { path: 'admin/src/app/views/channel/weapp/template.vue', reason: 'Generic pending-template explanation plus disabled retrieval/new-enable actions; rebuild the administration frontend after applying.' },
        { path: 'niucloud/app/service/core/member/CoreMemberAccountService.php', reason: 'Generic MemberAccount event includes the stable account_log_id after the ledger row is created and before transaction commit. Optional consumers use that source identity instead of guessing or replaying historical money. Apply separately; no plugin names or source queries are added to the framework.' },
        { path: 'niucloud/app/listener/notice/Sms.php', reason: 'Optional generic return_receipt contract preserves old callers, reports confirmed framework SMS acceptance, and treats post-submit errors as unknown instead of replaying potentially billable messages. No plugin names, recipient rules or business templates are hardcoded in the framework.' },
        ...['hooks/useLogin.ts','utils/auth.ts','app/pages/auth/index.vue','app/pages/auth/login.vue'].map(file=>({path:'uni-app/src/'+file,reason:'Generic login cancellation fix: cancel pending login navigation and return to a registered public page instead of a protected-page loop. Supplied separately; no plugin names or plugin-specific conditions in these framework files.'})),
        { path: 'uni-app/src/components/top-tabbar/top-tabbar.vue', reason: 'Earlier optional generic typography and H5 visibility support for the existing DIY header; not changed by this login fix. This source archive does not contain framework files.' }
    ],
    maintenance_entry: { path: prefix + '/scripts/upgrade.php', check: 'php addon/hsx_lead/scripts/upgrade.php --check', apply: 'php addon/hsx_lead/scripts/upgrade.php --apply', executes_ddl: false, compiles_frontends: false },
    virtual_payment_schema_entry: {
        path: prefix + '/scripts/upgrade-virtual-payment.php', sql: prefix + '/sql/virtual-payment.sql',
        check: 'php addon/hsx_lead/scripts/upgrade-virtual-payment.php --check', apply: 'php addon/hsx_lead/scripts/upgrade-virtual-payment.php --apply',
        executes_ddl: true, requires_explicit_apply: true, adds_tables: 2, adds_columns: 0, tables: virtualTables,
        alters_existing_tables: false, clears_business_data: false, stores_secrets_or_sessions: false,
        pre_ddl_backup: 'private_schema_metadata_only_not_a_full_database_backup', tested_live_provider: false,
        fresh_install_already_contains_tables: true
    },
    entity_schema_entry: { path: prefix + '/scripts/upgrade-entity-membership.php', check: 'php addon/hsx_lead/scripts/upgrade-entity-membership.php --check', apply: 'php addon/hsx_lead/scripts/upgrade-entity-membership.php --apply', executes_ddl: true, adds_tables: 1, adds_columns: 3, clears_business_data: false },
    notice_runtime_schema_entry: {
        path: prefix + '/scripts/upgrade-notice-runtime.php', sql: prefix + '/sql/notice-runtime.sql',
        check: 'php addon/hsx_lead/scripts/upgrade-notice-runtime.php --check', apply: 'php addon/hsx_lead/scripts/upgrade-notice-runtime.php --apply',
        executes_ddl: true, requires_explicit_apply: true, adds_tables: 3, adds_columns: 0, tables: noticeTables,
        alters_existing_tables: false, clears_business_data: false, replays_historical_events: false,
        pre_ddl_backup: 'private_schema_metadata_only_not_a_full_database_backup', mysql_ddl_atomic_batch: false,
        fresh_install_already_contains_tables: true
    },
    menu_groups_entry: {
        path: prefix + '/scripts/upgrade-menu-groups.php', check: 'php addon/hsx_lead/scripts/upgrade-menu-groups.php --check', apply: 'php addon/hsx_lead/scripts/upgrade-menu-groups.php --apply',
        executes_ddl: false, requires_explicit_apply: true, adds_navigation_groups: 6,
        preserves_existing_business_permissions: true, grants_new_api_permissions: false,
        clears_business_data: false, backs_up_affected_menu_and_role_rows: true
    },
    manual_receipts_schema_entry: {
        path: prefix + '/scripts/upgrade-manual-receipts.php', sql: prefix + '/sql/manual-receipts.sql',
        check: 'php addon/hsx_lead/scripts/upgrade-manual-receipts.php --check', apply: 'php addon/hsx_lead/scripts/upgrade-manual-receipts.php --apply',
        executes_ddl: true, requires_explicit_apply: true, adds_tables: 1, adds_columns: 0,
        tables: ['hsx_lead_manual_receipt'], alters_existing_tables: false, clears_business_data: false,
        replays_historical_payments: false, changes_member_balances: false, fresh_install_already_contains_table: true,
        backup: 'private_schema_metadata_only_not_a_full_database_backup'
    },
    notification_event_dependency: {
        framework_path: 'niucloud/app/service/core/member/CoreMemberAccountService.php', event: 'MemberAccount', required_additional_field: 'account_log_id',
        source_identity: 'member_account_log.id', timing: 'after_ledger_insert_before_transaction_commit',
        packaged_framework_source: false, historical_backfill: false
    },
    notification_upgrade_order: [
        { step: 'backup', manual: true, scope: 'database_deployment_files_private_uploads' },
        { step: 'notice_runtime_schema', check: 'php addon/hsx_lead/scripts/upgrade-notice-runtime.php --check', apply: 'php addon/hsx_lead/scripts/upgrade-notice-runtime.php --apply' },
        { step: 'menu_groups', check: 'php addon/hsx_lead/scripts/upgrade-menu-groups.php --check', apply: 'php addon/hsx_lead/scripts/upgrade-menu-groups.php --apply' },
        { step: 'mount_plugin', check: 'php addon/hsx_lead/scripts/upgrade.php --check', apply: 'php addon/hsx_lead/scripts/upgrade.php --apply' },
        { step: 'framework_and_frontend', manual: true, note: 'Apply separately listed generic framework dependencies, then rebuild and publish admin/mobile sources. Never overlay shared generated manifests.' },
        { step: 'workers_and_scheduler', manual: true, note: 'Reload existing managed workers; inspect and explicitly enable the new recovery schedule. Do not start duplicate consumers.' },
        { step: 'channel_configuration_and_acceptance', manual: true, note: 'Configure real templates and channel switches, obtain explicit member consent, then verify a new test transaction. Neither a build nor a queued task proves real delivery.' }
    ],
    retired_test_cleanup_entry: { path: prefix + '/scripts/clean-retired-membership.php', check: 'php addon/hsx_lead/scripts/clean-retired-membership.php --check', apply: 'php addon/hsx_lead/scripts/clean-retired-membership.php --apply --confirm-all-plugin-data-is-test', allowed_database: 'localhost_mysql_only', scope: 'all_plugin_sites_must_be_test', production_migration: false, external_refunds: false, requires_explicit_test_confirmation: true, backs_up_affected_rows: true, deletes_retired_test_facts_and_columns: true },
    source_directories: runtimeDirectories.map(directory => prefix + '/' + directory),
    mobile_pages: mobilePages.map(page => 'addon/hsx_lead/' + page),
    generated_configuration_files: [{
        path: 'uni-app/src/pages.json', source: prefix + '/package/uni-app-pages.php',
        reason: 'Plugin mobile pages, including membership/team/payment orders, protected notification preferences and the public invitation entry, are registered by the framework plugin page compiler. Do not overlay the shared page manifest.'
    }, {
        path: 'uni-app/src/addon/components/diy/group/index.vue', source: prefix + '/uni-app/components/diy',
        reason: 'The framework registers the cooperation home entry and four member-center DIY components in its generated group. Regenerate on the target project; do not overlay the shared component group.'
    }],
    installation: [
        'Copy the plugin directory into the target project and use the framework application installation and site authorization workflow.',
        'Integrate plugin admin/mobile sources and resources with the framework compiler, then build and publish the frontends.',
        'Configure site business rules, payment, notification, withdrawal and the existing scheduler before accepting real business.'
    ],
    existing_installation: [
        'Back up deployment files, database and private uploads before replacing source files.',
        'If the base membership schema is missing, explicitly preflight scripts/upgrade-entity-membership.php and follow its separate backup/apply instructions; do not run unrelated schema upgrades blindly.',
        'For this notification/navigation update, from the PHP project root preflight/apply scripts/upgrade-notice-runtime.php, then scripts/upgrade-menu-groups.php, then scripts/upgrade.php. The first command explicitly creates only three missing notification tables; no business facts or historical messages are reset or replayed.',
        'The maintenance entry backs up its own affected targets before synchronization; it cannot back up source files already overwritten by the user.',
        'clean-retired-membership.php is an optional destructive localhost-only TEST reset, not a normal upgrade or production refund. Run --check first; --apply requires --confirm-all-plugin-data-is-test for every plugin site and a complete database backup.',
        'Rebuild and publish admin/mobile frontends separately; PHP maintenance completion is not frontend deployment or payment verification.'
    ],
    excluded: ['mounted frontend directories', 'framework source', 'tests', 'unlisted development or migration scripts', 'temporary tools', 'credentials', 'database data', 'compiled frontend output'],
    files: fileManifest
}
// Allows the isolated contract test to inspect the actual validated plan under --check.
export { manifest }
if (args.includes('--check')) {
    console.log(JSON.stringify({ version, package_type: manifest.package_type, source_files: files.length, mobile_pages: mobilePages.length, framework_files: 0, notice_runtime_tables: noticeTables.length, explicit_notice_upgrade_order: manifest.notification_upgrade_order.filter(step => step.check).map(step => step.step), writes: false }))
} else {
    const outputRoot = path.join(root, 'output')
    await mkdir(outputRoot, { recursive: true })
    if (await realpath(outputRoot) !== outputRoot) throw new Error('Symlink output directory rejected')
    const output = path.join(outputRoot, 'hsx-lead-' + version + '-release-' + releaseDate)
    await mkdir(output, { recursive: true })
    if (await realpath(output) !== output) throw new Error('Symlink release directory rejected')
    const nonce = randomUUID()
    const stage = path.join(output, '.source-' + nonce)
    const temporary = path.join(output, '.package-' + nonce + '.zip')
    const archive = path.join(output, 'hsx-lead-' + version + '-source.zip')
    try {
        // Archive the exact bytes that were hashed, not files that may change
        // between manifest generation and zip reading the working tree.
        for (const [relative, buffer] of content) {
            const target = path.join(stage, prefix, relative)
            await mkdir(path.dirname(target), { recursive: true })
            await writeFile(target, buffer, { flag: 'wx' })
        }
        const result = spawnSync('zip', ['-q', '-X', temporary, '-@'], {
            cwd: stage, input: fileManifest.map(file => file.path).join('\n') + '\n', encoding: 'utf8'
        })
        if (result.error || result.status !== 0) throw new Error('Archive creation failed; zip command must be available')
        const zip = await readFile(temporary)
        const hash = createHash('sha256').update(zip).digest('hex')
        await rename(temporary, archive)
        await writeFile(path.join(output, 'manifest.json'), JSON.stringify({ ...manifest, archive: { file: path.basename(archive), bytes: zip.length, sha256: hash } }, null, 2) + '\n')
        await writeFile(path.join(output, 'SHA256SUMS'), hash + '  ' + path.basename(archive) + '\n')
        console.log(JSON.stringify({ archive, bytes: zip.length, source_files: files.length, sha256: hash, mobile_pages: mobilePages.length, framework_files: 0 }))
    } finally {
        await rm(stage, { recursive: true, force: true })
        await rm(temporary, { force: true })
    }
}
