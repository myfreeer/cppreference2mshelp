#!/bin/bash
for i in "$@"
do
  case $i in
    _7Z=*)
      _7Z="${i#*=}"
      shift # past argument=value
    ;;
    VERSION=*)
      VERSION="${i#*=}"
      shift # past argument=value
    ;;
    UPSTREAM=*)
      UPSTREAM="${i#*=}"
      shift # past argument=value
    ;;
    *)
      # unknown option
    ;;
  esac
done

set -e

if [[ "${UPSTREAM}" = "p12tic" ]]; then
  git clone https://github.com/p12tic/cppreference-doc.git --depth=1
  cd cppreference-doc
  git apply -3 ../zh-p12tic.diff
else
  git clone https://github.com/PeterFeicht/cppreference-doc.git --depth=1
  cd cppreference-doc
  git apply -3 ../zh.diff
fi

git apply -3 ../preprocess_cssless.diff

VERSION="${VERSION:-$(date +%Y%m%d)}"
sed -i "/^VERSION=/cVERSION=${VERSION}" Makefile
make source


# init files and vars
startup_scripts_replace="startup_scripts.js"
startup_scripts_path="$(find | grep -iP 'load\.php.*?modules=startup&only=scripts.*?' | head -1)"

site_scripts_replace="site_scripts.js"
site_scripts_path="$(find | grep -iP 'load\.php.*?modules=site&only=scripts.*?' | head -1)"

site_modules_replace="site_modules.css"
site_modules_path="$(find | grep -iP 'load\.php.*?modules=site&only=styles.*?' | head -1)"

skin_scripts_replace="skin_scripts.js"
skin_scripts_path="$(find | grep -iP 'load\.php.*?modules=skins.*&only=scripts.*?' | head -1)"

ext_replace="ext.css"
ext_path="$(find | grep -iP 'load\.php.*?modules=.*ext.*&only=styles.*?' | head -1)"

LIST="startup_scripts site_scripts site_modules skin_scripts ext"
extra_fonts="DejaVuSans.ttf DejaVuSans-Bold.ttf DejaVuSansMono.ttf DejaVuSansMono-Bold.ttf"

_7Z="${_7Z:-$(which 7z)}"
CPUS="$(cat /proc/cpuinfo | grep -c '^processor')"

# package un-processed files
"${_7Z}" a -mx9 -myx9  -mqs "../cppreference-unprocessed-${VERSION}.7z" ./reference
tar caf "../cppreference-unprocessed-${VERSION}.tar.xz" reference
#rm -rf ./reference
#"${_7Z}" x ../cppreference-unprocessed-20210212.7z

# https://gist.github.com/cdown/1163649/8a35c36fdd24b373788a7057ed483a5bcd8cd43e
url_encode() {
    local _length="${#1}"
    for (( _offset = 0 ; _offset < _length ; _offset++ )); do
        _print_offset="${1:_offset:1}"
        case "${_print_offset}" in
            [a-zA-Z0-9.~_-]) printf "${_print_offset}" ;;
            ' ') printf + ;;
            *) printf '%%%X' "'${_print_offset}" ;;
        esac
    done
}

copy_file(){
    local var=$1
    local path="$(eval echo "\${${var}_path}")"
    local replace="$(eval echo "\${${var}_replace}")"
    local dir="$(dirname "${path}")"
    cp -f -T "${path}" "${dir}/${replace}"
}

remove_file(){
    local var=$1
    local path="$(eval echo "\${${var}_path}")"
    local name="$(basename "${path}")"
    find -iname "${name}" | xargs rm -f
}

replace_in_html(){
    local var=$1
    local path="$(eval echo "\${${var}_path}")"
    local replace="$(eval echo "\${${var}_replace}")"
    local name="$(basename "${path}")"
    local encoded_name="$(url_encode "${name}")"
    find ./ -iname '*.html' -type f | xargs -P "${CPUS}" sed -i "s/${name}/${replace}/gi"
    find ./ -iname '*.html' -type f | xargs -P "${CPUS}" sed -i "s/${encoded_name}/${replace}/gi"
}

echo pre-processing...
for i in $LIST; do copy_file $i; done

# backup extra fonts
mkdir -p font_temp
for i in $extra_fonts; do
    find -iname $i -exec cp {} font_temp/$i \;
done

# original preprocess
make doc_html

# restore extra fonts
if [[ -d 'reference/common' ]]; then
    font_path='reference/common'
elif [[ -d 'output/common' ]]; then
    font_path='output/common'
fi
if [[ -d $font_path ]]; then
for i in $extra_fonts; do
    cp -f font_temp/$i $font_path/$i
done
fi
rm -rf font_temp

find ./ -iname '*.html' -type f | xargs -P "${CPUS}" sed -i "s/ - cppreference.com//g"

echo post-processing...
for i in $LIST; do
    echo processing $i
    remove_file $i
    replace_in_html $i
done

find -iname "${startup_scripts_replace}" | xargs sed -i 's/document\.write/void /ig'
find -iname "${site_scripts_replace}" | xargs sed -i '1 i if(window.mw)'
find -iname "${skin_scripts_replace}" | xargs sed -i '1 i if(window.mw)'
find -iname '*.css' | xargs sed -i -r 's/\.\.\/([^.]+?)\.ttf/\1.ttf/ig'

# workaround navbar-inv-tab.png
find -iname '*.css' | xargs sed -i -r 's/https?:\/\/..\.cppreference\.com\/mwiki\/skins\/cppreference2\/images/skins\/cppreference2\/images/ig'
pushd "${font_path}/skins/cppreference2/images"
wget -nv 'https://en.cppreference.com/mwiki/skins/cppreference2/images/navbar-inv-tab.png'
popd
echo Cleaning up carbonads scripts
find ./ -iname '*.html' -type f | xargs -P "${CPUS}" sed -i -r 's/<script.+?carbonads\.com\/carbon\.js.+?<\/script>//ig' 
echo Done.

rm -rf 'reference/zh.cppreference.com'

# build doc_devhelp doc_doxygen
mkdir -p output
mv -f reference output/
make doc_doxygen doc_devhelp

# package processed files
cd output
"${_7Z}" a -mx9 -myx9 -mqs "../../html-book-${VERSION}.7z" ./reference cppreference-doc-zh-c.devhelp2 cppreference-doc-zh-cpp.devhelp2 cppreference-doxygen-web.tag.xml cppreference-doxygen-local.tag.xml devhelp-index-c.xml devhelp-index-cpp.xml link-map.xml
tar caf "../../html-book-${VERSION}.tar.xz" reference cppreference-doc-zh-c.devhelp2 cppreference-doc-zh-cpp.devhelp2 cppreference-doxygen-web.tag.xml cppreference-doxygen-local.tag.xml devhelp-index-c.xml devhelp-index-cpp.xml link-map.xml
cd ..

# build qch book
make doc_qch
"${_7Z}" a -mx9 -myx9 -mqs "../qch-book-${VERSION}.7z" ./output/*.qch
tar caf "../qch-book-${VERSION}.tar.xz"  ./output/*.qch

# move processed files to parent folder
# for make_chm.sh
mv -f output/reference/* ../
cd ..

set +e
