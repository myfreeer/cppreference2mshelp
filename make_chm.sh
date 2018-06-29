#!/bin/bash
for i in "$@"
do
  case $i in
    PHP=*)
      PHP="${i#*=}"
      shift # past argument=value
    ;;
    ICONV=*)
      ICONV="${i#*=}"
      shift # past argument=value
    ;;
    HHC=*)
      HHC="${i#*=}"
      shift # past argument=value
    ;;
    *)
      # unknown option
    ;;
  esac
done

PHP="${PHP:-$(which php)}"
ICONV="${ICONV:-$(which iconv)}"
HHC="${HHC:-$(which hhc)}"
CPUS="$(cat /proc/cpuinfo | grep -c '^processor')"

# 在执行下一步之前，移动reference或output目录下文件到当前目录
rm -rf chmhelp

# 防止loadHTMLFile乱码
find -iname '*.html' | xargs -P "${CPUS}" sed -i 's/<head>/<head><meta charset="utf-8">/ig'

# 生成用于打包chm的文件
"${PHP}" make_chm.php

# 设置ie兼容性，使样式正确显示
cd chmhelp
find -iname '*.html' | xargs -P "${CPUS}" sed -i 's/<head>/<head><meta http-equiv="x-ua-compatible" content="ie=edge">/ig'
cd ..

mkdir -p chm_temp
for i in cppreference.{hhc,hhk,hhp}; do
sed -i '/chmhelp\\首页.html/d' "${i}"
mv "${i}" "chm_temp/${i}"
"${ICONV}" -c -f UTF-8 -t GBK "chm_temp/${i}" > "${i}"
done
rm -rf chm_temp

"${HHC}" cppreference.hhp