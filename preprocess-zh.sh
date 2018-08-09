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
    *)
      # unknown option
    ;;
  esac
done

set -e
git clone https://github.com/PeterFeicht/cppreference-doc.git --depth=1
cd cppreference-doc
git apply -3 ../zh.diff
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
VERSION="${VERSION:-$(date +%Y%m%d)}"
CPUS="$(cat /proc/cpuinfo | grep -c '^processor')"

# package un-processed files
"${_7Z}" a -mx9 -myx9 "cppreference-unprocessed-${VERSION}.7z" ./reference

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
echo Done.

# package processed files
"${_7Z}" a -mx9 -myx9 "html-book-${VERSION}.7z" ./reference

# move processed files to parent folder
# for make_chm.sh
mv -f reference/* ../
cd ..

set +e
