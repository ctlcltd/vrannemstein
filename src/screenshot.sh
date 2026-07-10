#!/usr/bin/env sh

avifenc --codec aom --ignore-exif --ignore-xmp --ignore-icc -q 55 --depth 8 --speed 4 -a tune=iq -a sharpness=1 screenshot.png screenshot.avif

