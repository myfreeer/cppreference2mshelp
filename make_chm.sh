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
    _7Z=*)
      _7Z="${i#*=}"
      shift # past argument=value
    ;;
    VERSION=*)
      VERSION="${i#*=}"
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
_7Z="${_7Z:-$(which 7z)}"
VERSION="${VERSION:-$(date +%Y%m%d)}"
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

sed "s/cppreference\.chm/cppreference-zh-${VERSION}\.chm/" cppreference.hhp > "cppreference-zh-${VERSION}.hhp"

# Compile and package UTF-8 version
"${HHC}" "cppreference-zh-${VERSION}.hhp"
"${_7Z}" a -mx9 "cppreference-zh-${VERSION}-chm-project.7z" "cppreference-zh-${VERSION}.hhp" cppreference.{hhc,hhk} hh{a.dll,c.exe} chmhelp/*

# Convert HTML to GBK
mv chmhelp chmhelp1
mkdir -p chmhelp
cd chmhelp1

find -iname '*.html' | xargs -P "${CPUS}" sed -i 's/<meta http-equiv="Content-Type" content="text\/html; charset=utf-8" \/>/<meta http-equiv="Content-Type" content="text\/html; charset=gbk" \/>/'
ls | grep .html | xargs -P "${CPUS}" -I {} sh -c "${ICONV} -c -f UTF-8 -t GBK '{}' > '../chmhelp/{}'"
cp -n ./* ../chmhelp/
cd ..
rm -rf chmhelp1

sed "s/cppreference\.chm/cppreference-zh-${VERSION}-gbk\.chm/" cppreference.hhp > "cppreference-zh-${VERSION}-gbk.hhp"

# Compile and package GBK version
"${HHC}" "cppreference-zh-${VERSION}-gbk.hhp"
"${_7Z}" a -mx9 "cppreference-zh-${VERSION}-chm-project-gbk.7z" "cppreference-zh-${VERSION}-gbk.hhp" cppreference.{hhc,hhk} hh{a.dll,c.exe} chmhelp/*