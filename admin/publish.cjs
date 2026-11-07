// Preserve the manual publish entry, without removing the live directory.
const { publishSafe } = require('./scripts/publish-safe.cjs')
const controller = new AbortController()
for (const signal of ['SIGINT', 'SIGTERM']) process.once(signal, () => controller.abort())
publishSafe({ signal: controller.signal })
    .then(result => console.log(JSON.stringify(result)))
    .catch(error => { console.error('[发布未完成] ' + error.message); process.exitCode = error.code === 'ABORT_ERR' ? 130 : 1 })
