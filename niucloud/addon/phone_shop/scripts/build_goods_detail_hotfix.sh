#!/usr/bin/env bash
# Build the detail release and its add-only supplement from the same dependency manifest.
set -eu
if [[ $# != 1 || ! -d "$1" ]]; then
    echo "Usage: bash build_goods_detail_hotfix.sh EXISTING_OUTPUT_DIRECTORY" >&2
    exit 1
fi
out=$(cd "$1" && pwd -P)
cd "$(dirname "$0")/../../.."
plugin=addon/phone_shop
supplement=(
    "$plugin/app/service/core/goods/CoreMemoryFilterService.php"
    "$plugin/app/service/core/goods/CoreModelSpecFacetService.php"
    "$plugin/app/support/GoodsMemorySpec.php"
    "$plugin/app/support/GoodsScopedSpec.php"
    "$plugin/app/model/goods/GoodsSpecGroup.php"
    "$plugin/app/model/goods/GoodsSpecItem.php"
)
detail=(
    "$plugin/app/service/api/goods/GoodsService.php"
    "$plugin/app/service/core/upgrade/GoodsDetailIndexes.php"
    "$plugin/scripts/upgrade_goods_detail_indexes.php"
)
for path in "${supplement[@]}" "${detail[@]}"; do
    [[ -s "$path" ]] || { echo "Missing release dependency: $path" >&2; exit 1; }
done
tmp=$(mktemp -d "$out/.phone-shop-bundle.XXXXXXXX")
trap 'rm -f "$tmp/supplement.zip" "$tmp/detail.zip"; rmdir "$tmp"' EXIT
zip -q -X "$tmp/supplement.zip" "${supplement[@]}"
zip -q -X "$tmp/detail.zip" "${supplement[@]}" "${detail[@]}"
for archive in "$tmp/supplement.zip" "$tmp/detail.zip"; do unzip -tqq "$archive"; done
mv "$tmp/supplement.zip" "$out/phone_shop-memory-filter-supplement-20261125.zip"
mv "$tmp/detail.zip" "$out/phone_shop-goods-detail-hotfix-20261125.zip"
