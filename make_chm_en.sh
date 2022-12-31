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

sed -e 's/C \/ C++ 参考文档/C \/ C++ Documentation/g' -e 's/0x804 中文(简体，中国)/0x409 English (United States)/g' -e 's/$scriptDir\.DIRECTORY_SEPARATOR \."zh"/$scriptDir.DIRECTORY_SEPARATOR ."en"/' make_chm.php  > make_chm_en.php

# 生成用于打包chm的文件
"${PHP}" make_chm_en.php

# 设置ie兼容性，使样式正确显示
cd chmhelp
find -iname '*.html' | xargs -P "${CPUS}" sed -i 's/<head>/<head><meta http-equiv="x-ua-compatible" content="ie=edge">/ig'
cd ..

sed "s/cppreference\.chm/cppreference-en-${VERSION}\.chm/" cppreference.hhp > "cppreference-en-${VERSION}.hhp"

# Compile and package UTF-8 version
"${HHC}" "cppreference-en-${VERSION}.hhp"
"${_7Z}" a -mx9 "cppreference-en-${VERSION}-chm-project.7z" "cppreference-en-${VERSION}.hhp" cppreference.{hhc,hhk} hh{a.dll,c.exe} chmhelp/*
tar caf "cppreference-en-${VERSION}-chm-project.tar.xz" "cppreference-en-${VERSION}.hhp" cppreference.{hhc,hhk} hh{a.dll,c.exe} chmhelp/*
