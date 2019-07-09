#!/usr/bin/env bash
#
#"ERROR: unable to load module
#`/usr/lib/x86_64-linux-gnu/ImageMagick-6.9.7//modules-Q16/coders/png.la':
#file not found @ error/module.c/OpenModule/1302"
source ~/.bashrc
/usr/bin/zbarimg --raw -Sdisable -Sqrcode.enable -q $1 2>&1