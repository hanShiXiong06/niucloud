import fs from "node:fs/promises";
import path from "node:path";
import process from "node:process";
import { FileBlob, SpreadsheetFile } from "@oai/artifact-tool";

const LEVELS = new Set(["正常", "一般", "异常"]);
const CONFIRMATIONS = new Set(["已确认", "待确认"]);
const REQUIRED = ["系统选项ID", "质检项上下文", "选项文本", "当前级别"];
const OUTPUT_HEADERS = ["建议级别", "确认状态", "标注备注"];

function parseArgs(argv) {
  const [command, ...rest] = argv;
  const args = { command };
  for (let i = 0; i < rest.length; i += 1) {
    const token = rest[i];
    if (!token.startsWith("--")) throw new Error(`无法识别参数: ${token}`);
    const key = token.slice(2);
    const next = rest[i + 1];
    if (!next || next.startsWith("--")) args[key] = true;
    else {
      args[key] = next;
      i += 1;
    }
  }
  return args;
}

function requireAbsoluteXlsx(filePath, label) {
  if (!filePath) throw new Error(`缺少 --${label}`);
  if (!path.isAbsolute(filePath)) throw new Error(`--${label} 必须是绝对路径: ${filePath}`);
  if (path.extname(filePath).toLowerCase() !== ".xlsx") throw new Error(`仅支持 .xlsx: ${filePath}`);
}

function clean(value) {
  if (value === null || value === undefined) return "";
  return String(value).trim();
}

function excelColumn(index) {
  let n = index + 1;
  let out = "";
  while (n > 0) {
    const r = (n - 1) % 26;
    out = String.fromCharCode(65 + r) + out;
    n = Math.floor((n - 1) / 26);
  }
  return out;
}

async function loadWorkbook(input) {
  const stat = await fs.stat(input);
  if (!stat.isFile()) throw new Error(`输入路径不是文件: ${input}`);
  const blob = await FileBlob.load(input);
  return SpreadsheetFile.importXlsx(blob);
}

function findSheetAndHeaders(workbook) {
  const candidates = workbook.worksheets.items || [];
  const ordered = [...candidates].sort((a, b) => (a.name === "级别标注" ? -1 : b.name === "级别标注" ? 1 : 0));
  for (const sheet of ordered) {
    const used = sheet.getUsedRange(true);
    if (!used) continue;
    const values = used.values || [];
    const scanRows = Math.min(values.length, 20);
    for (let r = 0; r < scanRows; r += 1) {
      const headers = (values[r] || []).map(clean);
      if (REQUIRED.every((h) => headers.includes(h))) {
        const map = Object.fromEntries(headers.map((h, i) => [h, i]).filter(([h]) => h));
        return { sheet, values, headerRowIndex: r, headers, map };
      }
    }
  }
  throw new Error(`未找到同时包含 ${REQUIRED.join("、")} 的工作表`);
}

function extractRows(found) {
  const rows = [];
  const seenIds = new Map();
  for (let r = found.headerRowIndex + 1; r < found.values.length; r += 1) {
    const source = found.values[r] || [];
    const id = clean(source[found.map["系统选项ID"]]);
    const context = clean(source[found.map["质检项上下文"]]);
    const optionText = clean(source[found.map["选项文本"]]);
    const currentLevel = clean(source[found.map["当前级别"]]);
    if (!id && !context && !optionText && !currentLevel) continue;
    if (!id) throw new Error(`第 ${r + 1} 行缺少系统选项ID`);
    if (seenIds.has(id)) throw new Error(`系统选项ID重复: ${id}（第 ${seenIds.get(id)}、${r + 1} 行）`);
    seenIds.set(id, r + 1);
    rows.push({ row_number: r + 1, system_option_id: id, context, option_text: optionText, current_level: currentLevel });
  }
  if (!rows.length) throw new Error("工作表没有可分析的数据行");
  return rows;
}

function groupRows(rows) {
  const groups = [];
  const indexes = new Map();
  for (const row of rows) {
    const key = row.context || `__missing_context_row_${row.row_number}`;
    if (!indexes.has(key)) {
      indexes.set(key, groups.length);
      groups.push({ group_id: `g${String(groups.length + 1).padStart(6, "0")}`, context: row.context, items: [] });
    }
    groups[indexes.get(key)].items.push(row);
  }
  return groups;
}

async function prepare(args) {
  requireAbsoluteXlsx(args.input, "input");
  if (!args["work-dir"] || !path.isAbsolute(args["work-dir"])) throw new Error("--work-dir 必须是绝对路径");
  const workbook = await loadWorkbook(args.input);
  const found = findSheetAndHeaders(workbook);
  const rows = extractRows(found);
  const groups = groupRows(rows);
  await fs.mkdir(args["work-dir"], { recursive: true });
  await fs.mkdir(path.join(args["work-dir"], "decisions"), { recursive: true });
  const manifest = {
    version: 1,
    input: args.input,
    sheet_name: found.sheet.name,
    header_row: found.headerRowIndex + 1,
    row_count: rows.length,
    group_count: groups.length,
    required_headers: REQUIRED,
    output_headers: OUTPUT_HEADERS,
    rows: rows.map((r) => ({ row_number: r.row_number, system_option_id: r.system_option_id })),
  };
  const manifestPath = path.join(args["work-dir"], "manifest.json");
  const groupsPath = path.join(args["work-dir"], "groups.jsonl");
  await fs.writeFile(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`, "utf8");
  await fs.writeFile(groupsPath, `${groups.map((g) => JSON.stringify(g)).join("\n")}\n`, "utf8");
  return { command: "prepare", manifest: manifestPath, groups: groupsPath, rows: rows.length, groups_count: groups.length, sheet: found.sheet.name };
}

async function readDecisionFiles(decisionsPath) {
  const stat = await fs.stat(decisionsPath);
  let files = [];
  if (stat.isDirectory()) {
    files = (await fs.readdir(decisionsPath)).filter((f) => f.endsWith(".jsonl")).sort().map((f) => path.join(decisionsPath, f));
  } else files = [decisionsPath];
  if (!files.length) throw new Error(`未找到决策 JSONL: ${decisionsPath}`);
  const groups = [];
  for (const file of files) {
    const lines = (await fs.readFile(file, "utf8")).split(/\r?\n/).filter((line) => line.trim());
    for (let i = 0; i < lines.length; i += 1) {
      try { groups.push(JSON.parse(lines[i])); }
      catch (error) { throw new Error(`${file} 第 ${i + 1} 行不是有效 JSON: ${error.message}`); }
    }
  }
  return groups;
}

function defaultOutput(input) {
  const dir = path.dirname(input);
  const ext = path.extname(input);
  const stem = path.basename(input, ext);
  return path.join(dir, `${stem}_语义分析结果${ext}`);
}

async function uniqueOutput(candidate) {
  try {
    await fs.access(candidate);
    const ext = path.extname(candidate);
    const stem = candidate.slice(0, -ext.length);
    const stamp = new Date().toISOString().replace(/[-:TZ.]/g, "").slice(0, 14);
    return `${stem}_${stamp}${ext}`;
  } catch {
    return candidate;
  }
}

function flattenAndValidate(decisionGroups, manifest) {
  const expected = new Map(manifest.rows.map((r) => [Number(r.row_number), String(r.system_option_id)]));
  const decisions = new Map();
  for (const group of decisionGroups) {
    if (!group || !Array.isArray(group.items)) throw new Error(`决策组格式错误: ${JSON.stringify(group)}`);
    for (const item of group.items) {
      const row = Number(item.row_number);
      const id = clean(item.system_option_id);
      const level = clean(item.suggested_level);
      const confirmation = clean(item.confirmation_status);
      const note = clean(item.note);
      if (!expected.has(row)) throw new Error(`决策包含未知行号: ${row}`);
      if (expected.get(row) !== id) throw new Error(`第 ${row} 行ID不匹配，期望 ${expected.get(row)}，实际 ${id}`);
      if (decisions.has(row)) throw new Error(`第 ${row} 行存在重复决策`);
      if (!CONFIRMATIONS.has(confirmation)) throw new Error(`第 ${row} 行确认状态必须为 已确认 或 待确认`);
      if (confirmation === "已确认" && !LEVELS.has(level)) throw new Error(`第 ${row} 行已确认，但建议级别无效: ${level}`);
      if (confirmation === "待确认" && level && !LEVELS.has(level)) throw new Error(`第 ${row} 行建议级别无效: ${level}`);
      if (confirmation === "待确认" && !note) throw new Error(`第 ${row} 行待确认时必须填写标注备注`);
      decisions.set(row, { id, level, confirmation, note });
    }
  }
  const missing = [...expected.keys()].filter((row) => !decisions.has(row));
  if (missing.length) throw new Error(`有 ${missing.length} 行缺少决策，示例: ${missing.slice(0, 10).join(", ")}`);
  return decisions;
}

async function apply(args) {
  requireAbsoluteXlsx(args.input, "input");
  if (!args.manifest || !path.isAbsolute(args.manifest)) throw new Error("--manifest 必须是绝对路径");
  if (!args.decisions || !path.isAbsolute(args.decisions)) throw new Error("--decisions 必须是绝对路径");
  const manifest = JSON.parse(await fs.readFile(args.manifest, "utf8"));
  if (path.resolve(manifest.input) !== path.resolve(args.input)) throw new Error("manifest 与输入工作簿不匹配");
  const decisions = flattenAndValidate(await readDecisionFiles(args.decisions), manifest);
  const workbook = await loadWorkbook(args.input);
  const found = findSheetAndHeaders(workbook);
  if (found.sheet.name !== manifest.sheet_name || found.headerRowIndex + 1 !== manifest.header_row) throw new Error("工作簿结构已变化，请重新执行 prepare");

  let nextCol = found.headers.length;
  for (const header of OUTPUT_HEADERS) {
    if (found.map[header] === undefined) {
      found.map[header] = nextCol;
      found.sheet.getCell(found.headerRowIndex, nextCol).values = [[header]];
      nextCol += 1;
    }
  }
  const counts = { 正常: 0, 一般: 0, 异常: 0, 待确认: 0 };
  for (const [rowNumber, decision] of decisions) {
    const rowIndex = rowNumber - 1;
    found.sheet.getCell(rowIndex, found.map["建议级别"]).values = [[decision.level]];
    found.sheet.getCell(rowIndex, found.map["确认状态"]).values = [[decision.confirmation]];
    found.sheet.getCell(rowIndex, found.map["标注备注"]).values = [[decision.note]];
    if (decision.confirmation === "待确认") counts.待确认 += 1;
    else counts[decision.level] += 1;
  }

  const output = await uniqueOutput(args.output ? path.resolve(args.output) : defaultOutput(args.input));
  requireAbsoluteXlsx(output, "output");
  await fs.mkdir(path.dirname(output), { recursive: true });
  const blob = await SpreadsheetFile.exportXlsx(workbook);
  await blob.save(output);
  return { command: "apply", output, rows: decisions.size, counts };
}

async function verify(args) {
  requireAbsoluteXlsx(args.input, "input");
  const workbook = await loadWorkbook(args.input);
  const found = findSheetAndHeaders(workbook);
  const rows = extractRows(found);
  for (const header of OUTPUT_HEADERS) if (found.map[header] === undefined) throw new Error(`输出缺少列: ${header}`);
  const counts = { 正常: 0, 一般: 0, 异常: 0, 待确认: 0, 空建议: 0 };
  const errors = [];
  for (const row of rows) {
    const values = found.values[row.row_number - 1] || [];
    const level = clean(values[found.map["建议级别"]]);
    const confirmation = clean(values[found.map["确认状态"]]);
    const note = clean(values[found.map["标注备注"]]);
    if (confirmation === "待确认") counts.待确认 += 1;
    else if (confirmation === "已确认" && LEVELS.has(level)) counts[level] += 1;
    else errors.push(`第 ${row.row_number} 行状态或级别无效`);
    if (!level) counts.空建议 += 1;
    if (confirmation === "待确认" && !note) errors.push(`第 ${row.row_number} 行待确认但无备注`);
  }
  if (errors.length) throw new Error(`校验失败: ${errors.slice(0, 10).join("；")}`);
  let preview = "";
  if (args["preview-dir"]) {
    if (!path.isAbsolute(args["preview-dir"])) throw new Error("--preview-dir 必须是绝对路径");
    await fs.mkdir(args["preview-dir"], { recursive: true });
    const lastRow = Math.min(found.values.length, found.headerRowIndex + 31);
    const lastCol = Math.max(...Object.values(found.map));
    const range = `A${found.headerRowIndex + 1}:${excelColumn(lastCol)}${lastRow}`;
    const image = await workbook.render({ sheetName: found.sheet.name, range, scale: 1.5, format: "png" });
    preview = path.join(args["preview-dir"], "preview.png");
    await fs.writeFile(preview, new Uint8Array(await image.arrayBuffer()));
  }
  return { command: "verify", input: args.input, sheet: found.sheet.name, rows: rows.length, counts, preview };
}

async function main() {
  const args = parseArgs(process.argv.slice(2));
  let result;
  if (args.command === "prepare") result = await prepare(args);
  else if (args.command === "apply") result = await apply(args);
  else if (args.command === "verify") result = await verify(args);
  else throw new Error("用法: severity_workbook.mjs prepare|apply|verify [参数]");
  process.stdout.write(`${JSON.stringify(result, null, 2)}\n`);
}

main().catch((error) => {
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exitCode = 1;
});
