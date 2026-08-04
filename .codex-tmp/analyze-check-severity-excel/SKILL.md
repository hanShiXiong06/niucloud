---
name: analyze-check-severity-excel
description: Read an exported Excel workbook of inspection options, reason semantically from each inspection context and option text, classify rows as 正常/一般/异常, preserve the source workbook, and write a new annotated .xlsx file. Use when the user supplies an absolute Excel path and asks to identify abnormal inspection choices, prepare 建议级别/确认状态/标注备注 for import, or review a 质检选项级别 workbook.
---

# Analyze Check Severity Excel

Require an explicit absolute `.xlsx` path. Never guess a file, overwrite the source workbook, or treat the existing `当前级别` as ground truth.

Read [references/severity-rubric.md](references/severity-rubric.md) completely before classifying rows.

## Workflow

1. Validate that the input path is absolute, exists, and ends in `.xlsx`. If the path is missing, ask only for the absolute path.
2. Load bundled workspace dependencies. Create a conversation-specific temporary directory, copy `scripts/severity_workbook.mjs` into it, and link that directory's `node_modules` to the loader-provided Node packages. Do not install packages.
3. Run `prepare`:

   ```bash
   "$NODE" "$WORK_DIR/severity_workbook.mjs" prepare \
     --input "$INPUT_XLSX" \
     --work-dir "$WORK_DIR/task"
   ```

   This validates the workbook and writes `manifest.json` plus `groups.jsonl`. It groups sibling options by `质检项上下文` so that severity can be judged comparatively.
4. Read `groups.jsonl` in context-group batches. Keep every group intact. Use semantic reasoning over both the full context and all sibling options; do not finalize labels with keyword matching alone.
5. Write one or more `decisions/*.jsonl` files. Each line must be one complete group:

   ```json
   {"group_id":"g000001","items":[{"row_number":2,"system_option_id":"123","suggested_level":"正常","confirmation_status":"已确认","note":"明确表示功能正常"}]}
   ```

   Use only `正常`, `一般`, or `异常` for confirmed rows. For genuinely ambiguous rows, use an empty `suggested_level`, `待确认`, and a concise note explaining what a human must check. Produce exactly one decision for every extracted row.
6. Run `apply`:

   ```bash
   "$NODE" "$WORK_DIR/severity_workbook.mjs" apply \
     --input "$INPUT_XLSX" \
     --manifest "$WORK_DIR/task/manifest.json" \
     --decisions "$WORK_DIR/task/decisions"
   ```

   Unless `--output` is supplied, this writes a sibling file named `<原文件名>_语义分析结果.xlsx`. If that name exists, it appends a timestamp.
7. Run `verify` against the new file and inspect the generated preview image:

   ```bash
   "$NODE" "$WORK_DIR/severity_workbook.mjs" verify \
     --input "$OUTPUT_XLSX" \
     --preview-dir "$WORK_DIR/verify"
   ```

   Verify row counts, allowed values, confirmation counts, blank suggestions, and visual preservation. Fix any failure before returning.
8. Return the absolute output path as a clickable file link and summarize counts for `正常`, `一般`, `异常`, and `待确认`.

## Workbook contract

- Prefer the worksheet named `级别标注`; otherwise locate the first worksheet containing the required headers.
- Required source columns: `系统选项ID`, `质检项上下文`, `选项文本`, `当前级别`.
- Output columns: `建议级别`, `确认状态`, `标注备注`. Add them only if missing.
- Preserve row order, IDs, source text, formulas, other worksheets, and existing formatting.
- Never modify `当前级别`; the application import flow decides whether to apply confirmed suggestions.
- Stop with a precise error when IDs are duplicated, headers are ambiguous, or decisions do not cover all extracted rows.

## Safety and quality

- Treat IDs as text so long numeric IDs are not rounded.
- Keep explanations short and specific; do not paste chain-of-thought into cells.
- Classify relative numeric or graded choices by comparing siblings within the same context.
- Mark low-confidence or contradictory meanings as `待确认` instead of inventing certainty.
- Never send workbook contents to external services unless the user explicitly requests it.

