#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
拍机堂检测模板导入脚本

将拍机堂导出的 Excel(型号/产品ID/检测项/分类/默认选项/全部选项)导入为
hsx_recycle 插件的质检模板四件套(template/group/field/option),并按
product_source_id 将每个型号节点(recycle_device_model_dict)绑定到对应模板
(recycle_template_binding)。

- 相同检测项内容的型号共用一套模板(内容签名去重),绑定仍精确到型号节点
- 可重跑:只清理本脚本导入的数据(template_key 前缀 pjt_、绑定 remark 标记),
  不影响手工创建的模板与绑定
- 产出 SQL 文件后用 mysql 客户端导入

用法: python3 import_pjt_check_templates.py <excel路径> [输出sql路径]
"""
import hashlib
import json
import sys
import time

import pandas as pd
import subprocess

SITE_ID = 100005
KEY_PREFIX = 'pjt_'
BINDING_REMARK = '拍机堂导入'
SCENE_KEY = 'manual_device_label'
TABLE_PREFIX = 'saas_'
MYSQL = ['mysql', '-h', 'localhost', '-uroot', '-proot', '--default-character-set=utf8mb4', 'saas_']

GROUP_KEY_MAP = {
    '基本问题': 'basic_issue',
    '主观问题': 'subjective_issue',
    '功能使用问题': 'function_issue',
    '外壳问题': 'shell_issue',
    '显示问题': 'display_issue',
}


def esc(s: str) -> str:
    return str(s).replace('\\', '\\\\').replace("'", "\\'")


def mysql_query(sql: str) -> list:
    out = subprocess.run(MYSQL + ['-N', '-e', sql], capture_output=True, text=True)
    if out.returncode != 0:
        raise RuntimeError(out.stderr)
    return [line.split('\t') for line in out.stdout.strip().split('\n') if line]


def group_key_for(name: str) -> str:
    if name in GROUP_KEY_MAP:
        return GROUP_KEY_MAP[name]
    return 'grp_' + hashlib.md5(name.encode()).hexdigest()[:8]


def main():
    excel_path = sys.argv[1] if len(sys.argv) > 1 else '/Users/a123/Desktop/拍机堂检测模板.xlsx'
    sql_path = sys.argv[2] if len(sys.argv) > 2 else '/tmp/pjt_import.sql'
    now = int(time.time())

    print('读取 Excel ...')
    df = pd.read_excel(excel_path, header=0, dtype=str)
    df.columns = ['model', 'product_id', 'field', 'group', 'default', 'options']
    df = df.fillna('')
    df['product_id'] = df['product_id'].str.strip()
    df = df[df['product_id'] != '']
    print(f'有效行数: {len(df)}')

    print('读取型号节点映射 ...')
    rows = mysql_query(
        f"SELECT product_source_id, id, node_name FROM {TABLE_PREFIX}recycle_device_model_dict "
        f"WHERE site_id={SITE_ID} AND product_source_id!=''")
    node_by_pid = {r[0]: (int(r[1]), r[2]) for r in rows}
    print(f'型号节点数: {len(node_by_pid)}')

    maxids = {}
    for t in ('check_template', 'check_group', 'check_field', 'check_option', 'template_binding'):
        maxids[t] = int(mysql_query(f"SELECT IFNULL(MAX(id),0) FROM {TABLE_PREFIX}recycle_{t}")[0][0])

    # 按产品分组,保持 Excel 原始行顺序生成内容签名
    print('聚合产品检测项并按内容签名去重 ...')
    templates = {}        # sig -> {'items': [...], 'first_model': str, 'count': int}
    product_sig = {}      # product_id -> sig
    skipped_products = 0
    for pid, g in df.groupby('product_id', sort=False):
        if pid not in node_by_pid:
            skipped_products += 1
            continue
        items = []
        for _, r in g.iterrows():
            field = r['field'].strip()
            if not field:
                continue
            options = [o.strip() for o in r['options'].split('|') if o.strip()]
            default = r['default'].strip()
            if default and default not in options:
                options.append(default)
            items.append((field, r['group'].strip() or '检测项', default, tuple(options)))
        if not items:
            skipped_products += 1
            continue
        sig = hashlib.md5(json.dumps(items, ensure_ascii=False).encode()).hexdigest()
        product_sig[pid] = sig
        if sig not in templates:
            templates[sig] = {'items': items, 'first_model': g['model'].iloc[0], 'count': 1}
        else:
            templates[sig]['count'] += 1
    print(f'独特模板: {len(templates)}, 绑定型号: {len(product_sig)}, 跳过(无匹配节点/无检测项): {skipped_products}')

    print('生成 SQL ...')
    tpl_id = maxids['check_template']
    grp_id = maxids['check_group']
    fld_id = maxids['check_field']
    opt_id = maxids['check_option']
    sig_tpl_id = {}

    tpl_rows, grp_rows, fld_rows, opt_rows, bind_rows = [], [], [], [], []
    for sig, info in templates.items():
        tpl_id += 1
        sig_tpl_id[sig] = tpl_id
        name = f"拍机堂-{info['first_model']}"
        if info['count'] > 1:
            name += f"等{info['count']}款"
        tpl_rows.append(f"({tpl_id},{SITE_ID},'{KEY_PREFIX}{sig[:16]}','{esc(name[:120])}','pjt',0,1,0,1,{now},{now})")

        group_ids = {}
        for sort_f, (field, group, default, options) in enumerate(info['items']):
            if group not in group_ids:
                grp_id += 1
                group_ids[group] = grp_id
                grp_rows.append(
                    f"({grp_id},{SITE_ID},{tpl_id},'{group_key_for(group)}','{esc(group[:120])}','',{len(group_ids)},1,{now},{now})")
            fld_id += 1
            component = 'radio' if options else 'input'
            mode = 'single' if options else ''
            default_value = ''
            if options and default:
                default_value = str(options.index(default) + 1)
            fld_rows.append(
                f"({fld_id},{SITE_ID},{tpl_id},{group_ids[group]},'item_{sort_f + 1}','{esc(field[:120])}',"
                f"'{component}','{mode}','','','{default_value}',0,1,1,0,1,'',0,'empty_only',{sort_f + 1},NULL,{now},{now})")
            for sort_o, opt in enumerate(options):
                opt_id += 1
                is_def = 1 if (default and opt == default) else 0
                opt_rows.append(
                    f"({opt_id},{SITE_ID},{fld_id},'{esc(opt[:120])}','{sort_o + 1}',{is_def},1,{sort_o + 1},NULL,{now},{now})")

    for pid, sig in product_sig.items():
        node_id, _ = node_by_pid[pid]
        bind_rows.append(
            f"({SITE_ID},'model_dict',{node_id},'{SCENE_KEY}',{sig_tpl_id[sig]},0,1,1,0,'{BINDING_REMARK}',{now},{now})")

    with open(sql_path, 'w', encoding='utf-8') as f:
        f.write("SET NAMES utf8mb4;\nSET unique_checks=0;\nSET foreign_key_checks=0;\n")
        # 清理本脚本上一次导入的数据(不碰手工数据)
        f.write(f"DELETE o FROM {TABLE_PREFIX}recycle_check_option o JOIN {TABLE_PREFIX}recycle_check_field fd ON o.field_id=fd.id JOIN {TABLE_PREFIX}recycle_check_template t ON fd.template_id=t.id WHERE t.site_id={SITE_ID} AND t.template_key LIKE '{KEY_PREFIX}%';\n")
        f.write(f"DELETE fd FROM {TABLE_PREFIX}recycle_check_field fd JOIN {TABLE_PREFIX}recycle_check_template t ON fd.template_id=t.id WHERE t.site_id={SITE_ID} AND t.template_key LIKE '{KEY_PREFIX}%';\n")
        f.write(f"DELETE g FROM {TABLE_PREFIX}recycle_check_group g JOIN {TABLE_PREFIX}recycle_check_template t ON g.template_id=t.id WHERE t.site_id={SITE_ID} AND t.template_key LIKE '{KEY_PREFIX}%';\n")
        f.write(f"DELETE FROM {TABLE_PREFIX}recycle_check_template WHERE site_id={SITE_ID} AND template_key LIKE '{KEY_PREFIX}%';\n")
        f.write(f"DELETE FROM {TABLE_PREFIX}recycle_template_binding WHERE site_id={SITE_ID} AND remark='{BINDING_REMARK}';\n")

        def dump(table, columns, rows, batch=2000):
            for i in range(0, len(rows), batch):
                f.write(f"INSERT INTO {TABLE_PREFIX}{table} ({columns}) VALUES\n" + ",\n".join(rows[i:i + batch]) + ";\n")

        dump('recycle_check_template',
             'id,site_id,template_key,template_name,scene,is_default,status,sort,version,create_at,update_at', tpl_rows)
        dump('recycle_check_group',
             'id,site_id,template_id,group_key,group_name,description,sort,status,create_at,update_at', grp_rows)
        dump('recycle_check_field',
             'id,site_id,template_id,group_id,field_key,field_name,component,selection_mode,unit,placeholder,'
             'default_value,is_required,is_show,seller_visible,buyer_visible,result_visible,result_template,'
             'api_fill_enabled,api_fill_policy,sort,extra_config,create_at,update_at', fld_rows)
        dump('recycle_check_option',
             'id,site_id,field_id,option_label,option_value,is_default,is_show,sort,extra_config,create_at,update_at', opt_rows)
        # 型号节点已有手工绑定时仅更新质检模板,保留其打印模板配置
        for i in range(0, len(bind_rows), 2000):
            f.write(f"INSERT INTO {TABLE_PREFIX}recycle_template_binding "
                    "(site_id,target_type,target_id,scene_key,check_template_id,print_template_id,inherit_enabled,status,sort,remark,create_at,update_at) VALUES\n"
                    + ",\n".join(bind_rows[i:i + 2000])
                    + f"\nON DUPLICATE KEY UPDATE check_template_id=VALUES(check_template_id), remark='{BINDING_REMARK}', update_at={now};\n")
        f.write("SET unique_checks=1;\nSET foreign_key_checks=1;\n")

    print(f'SQL 已生成: {sql_path}')
    print(f'模板 {len(tpl_rows)} / 分组 {len(grp_rows)} / 字段 {len(fld_rows)} / 选项 {len(opt_rows)} / 绑定 {len(bind_rows)}')


if __name__ == '__main__':
    main()
