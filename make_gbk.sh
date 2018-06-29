#!/ban/bash
mv chmhelp chmhelp1
mkdir chmhelp
cd chmhelp1
CPUS="$(cat /proc/cpuinfo | grep -c '^processor')"
find -iname '*.html' | xargs -P "${CPUS}" sed -i 's/<meta http-equiv="Content-Type" content="text\/html; charset=utf-8" \/>/<meta http-equiv="Content-Type" content="text\/html; charset=gbk" \/>/'
ls | grep .html | xargs -P "${CPUS}" -I {} sh -c "iconv -c -f UTF-8 -t GBK '{}' > '../chmhelp/{}'"
cp -n ./* ../chmhelp/
cd ..
rm -rf chmhelp1