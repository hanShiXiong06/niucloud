import { readFile, writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const directory = dirname(fileURLToPath(import.meta.url));
const sourcePath = join(directory, 'oa-attendance-quick-submit.js');
const htmlPath = join(directory, 'oa-attendance-bookmarklet.html');

const source = await readFile(sourcePath, 'utf8');
const html = await readFile(htmlPath, 'utf8');
const escapedSource = JSON.stringify(source)
  .slice(1, -1)
  .replace(/`/g, '\\`')
  .replace(/\$\{/g, '\\${');
const updatedHtml = html.replace(
  /const source = `.*?`;/s,
  `const source = \`${escapedSource}\`;`
);

await writeFile(htmlPath, updatedHtml);
console.log(`Updated ${htmlPath}`);
