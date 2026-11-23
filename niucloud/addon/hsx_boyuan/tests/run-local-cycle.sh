#!/bin/sh
# Re-run the same device from opening to sale. Only a disposable LOCAL MySQL schema is used.
# From any directory: sh /path/to/tests/run-local-cycle.sh [--evidence]
set -eu
case "${1:-}" in ""|--evidence|--guard-suite|--recycle-origin) ;; *) echo '用法：sh run-local-cycle.sh [--evidence | --guard-suite | --recycle-origin]' >&2; exit 2;; esac
[ "$#" -le 1 ] || exit 2
here=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd -P)
if [ -z "${PHP_BIN:-}" ]; then
    if [ -x "$HOME/Library/PhpWebStudy/app/static-php-8.0.30/bin/php" ]; then
        PHP_BIN="$HOME/Library/PhpWebStudy/app/static-php-8.0.30/bin/php"
    else
        PHP_BIN=php
    fi
fi
"$PHP_BIN" -r 'exit(PHP_MAJOR_VERSION === 8 ? 0 : 2);' || { echo '请用 PHP_BIN 选择本机 PHP 8 CLI。' >&2; exit 2; }
exec "$PHP_BIN" -d error_reporting=22527 -d allow_url_fopen=0 \
    -d disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,dns_get_record,gethostbyname,gethostbynamel \
    "$here/opening-inbound-outbound.php" --isolated-local-mysql "$@"
