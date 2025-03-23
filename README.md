# cppreference2mshelp
cppreference.com html archive converter to microsoft help (for Windows Visual Studio 2012+) and good old .chm help (for any Windows and even some other platforms)

## Badges
[![Downloads](https://img.shields.io/github/downloads/myfreeer/cppreference2mshelp/total.svg)](https://github.com/myfreeer/cppreference2mshelp/releases)
[![Latest Release](https://img.shields.io/github/downloads/myfreeer/cppreference2mshelp/latest/total.svg)](https://github.com/myfreeer/cppreference2mshelp/releases/latest)
[![Latest Release](https://img.shields.io/github/release/myfreeer/cppreference2mshelp.svg)](https://github.com/myfreeer/cppreference2mshelp/releases/latest)
[![GitHub license](https://img.shields.io/github/license/myfreeer/cppreference2mshelp.svg)](LICENSE) 

## Prerequires:

1) Windows x64 (LOL, I'm mad). Actually any system will be ok, you should use another tools in this case to make cab file, zip file and digitally sign cab (for Visual Studio Help). There is an alternative for [HTML Help Workshop](https://www.microsoft.com/en-us/download/details.aspx?id=21138) from Microsoft (for chm based help), see [here](https://github.com/myfreeer/cppreference2mshelp/issues/34) for more detail.
2) php at least 5.6.1
3) cppreference.com offline archive (*Html book*) downloaded from this link: http://en.cppreference.com/w/Cppreference:Archives
4) [HTML Help Workshop](https://www.microsoft.com/en-us/download/details.aspx?id=21138) from Microsoft to build .chm help file.
5) [Tidy](http://www.html-tidy.org/) (html fixer), [signtool.exe](https://msdn.microsoft.com/en-us/library/8s9b9yaz(v=vs.110).aspx) and [makecab.exe](https://technet.microsoft.com/en-us/library/hh875545(v=ws.11).aspx) from Microsoft SDK to build Visual Studio help files.
6) digital certificate to sign cabs for Visual Studio Help (because unsigned cabs will not be installed) CHM help does not require certificate
7) msys2 latest with `pacman -S p7zip mingw-w64-x86_64-python3-pip mingw-w64-x86_64-python3-lxml`
8) GNU libiconv 1.15 or later (`pacman -S mingw-w64-x86_64-iconv`)
9) `pip3 install premailer` and `pacman -S mingw-w64-x86_64-python3-qt` for qch help

## Ok, what's inside?

**compile_c.cmd** and **compile_cpp.cmd** - main command files that do the all job. Use **compile_c.cmd** for c documentation and **compile_cpp.cmd** for c++ documentation. 

**compile_c_zip.ps1** and **compile_cpp_zip.ps1** - uses powershell to make zip files.

**config.txt** - config for a great tool *tidy*: http://www.html-tidy.org/ ( we need it to convert htmls to xhtmls and fix errors ).

**cppreference.msha** and **creference.msha** - config files for *Microsoft Help System*. Actually a headers for Help system, *Microsoft Help* will get the info from them to install the help packs.

**make_xhtmls.php** - ugly php preprocessor script that fixes and converts thousands of htmls from cppreference to xhtml and Microsoft Help format.

**make_chm.php** - another ugly script that makes chm help files: **cppreference.hhc** - table of contents, **cppreference.hhk** - keywords index and _Html Help_ project file - **cppreference.hhp** (for both c and c++). Launch this script directly to make chm help file.

**makecab.exe** - tool to make a cab file for Microsoft Help System. Can be found in Microsoft SDK.

**signtool.exe** - tool to sign cab files. Can be found in Microsoft SDK.

**tidy.exe** and **tidy.dll** - tool to convert html to xhtml and fix many other problems. Can be downloaded here: http://www.html-tidy.org/ 

## Step-by-step instructions for Microsoft Help Files for Visual Studio:

1) download [cppreference.com](http://en.cppreference.com/w/Cppreference:Archives) offline archive and unpack it. Only *Html book* format is acceptable.
2) download this repo and unpack it to the **reference** folder inside unpacked archive (from cppreference.com).
3) folders structure should be like this one:

![screenshot](https://github.com/crea7or/cppreference2mshelp/raw/master/folders.png)

Folders **common** and **en** is from archive from cppreference, all other folders is owned by script.

4) now you can start the scripts and build the documentation (200+ seconds for c++ documentation 4000 files and a lot less for c documentation).

5) In result you should have two new files in this folder: **cppreference.cab** and **creference.cab** - use them with appropriate **cppreference.msha** and **creference.msha** files to install created help files.

**Note:** *This repo contains pre-build and signed files that are ready to install into Visual Studio 2012, Visual Studio 2013 (maybe 2015 and 2017 too - I can't test it right now - let me know if you'll do ).*

**Note:** *If you want to build chm help file, launch* **make_chm.php**. Pre-built **cppreference.chm** file is also included into this repo.*

**Note:** *HTML Help Workshop will not work correctly if you will not associate .hhp files with it (a project file of HHW), you'll receive a lot Errors "HHC5003"*

Enjoy!
