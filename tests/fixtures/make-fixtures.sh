#!/usr/bin/env bash
# Builds the TIFF fixtures of the rule tiff_zip: `bash tests/fixtures/make-fixtures.sh`.
# Needs ImageMagick 7, libtiff-tools, exiftool, Python 3 and liblcms2; the
# ICC profiles are written by make-icc.py, so no third-party profile is
# embedded.
set -euo pipefail
cd "$(dirname "$0")"
W=$(mktemp -d)
trap 'rm -rf "$W"' EXIT
python3 make-icc.py "$W"

image() { magick -size 64x48 gradient:'#c03020-#2040d0' -depth 8 "$@"; }

# Metadata as a camera or scanning software delivers it: IFD0, ExifIFD
# without the fields ExifTool adds on its own, XMP, IPTC.
metadata() {
  exiftool -q -overwrite_original -Artist='QJ Fotograf' -ImageDescription='QJ Testbild' \
    -ExifIFD:DateTimeOriginal='2020:01:02 03:04:05' -XMP-dc:Title='QJ Titel' \
    -IPTC:ObjectName='QJ Objekt' "$1"
  exiftool -q -overwrite_original -ExifIFD:ColorSpace= -ExifIFD:ComponentsConfiguration= \
    -ExifIFD:FlashpixVersion= "$1"
}

image -compress none -profile "$W/qj-rgb-d65.icc" uncompressed.tif && metadata uncompressed.tif
tiffcp -c lzw uncompressed.tif lzw.tif && metadata lzw.tif
image -compress none -profile "$W/qj-rgb-wide.icc" icc_romm.tif && metadata icc_romm.tif
tiffcp -c zip:2 uncompressed.tif deflate_predictor2.tif
tiffcp -c jpeg -r 16 uncompressed.tif jpeg.tif
image -depth 16 -compress none rgb16.tif
image -colorspace gray -compress none gray.tif
image -colorspace cmyk -compress none cmyk.tif
magick -size 64x48 pattern:checkerboard -monochrome -compress group4 g4.tif

# Full page plus a reduced preview in IFD1 (NewSubfileType 1).
magick -size 16x12 gradient:'#c03020-#2040d0' -depth 8 -compress none "$W/preview.tif"
tiffcp -c none uncompressed.tif "$W/preview.tif" preview_ifd1.tif
tiffset -d 1 -s 254 1 preview_ifd1.tif
metadata preview_ifd1.tif

# Three full pages.
image -compress none "$W/p1.tif"
magick -size 64x48 gradient:'#20a040-#f0e010' -depth 8 -compress none "$W/p2.tif"
magick -size 64x48 gradient:'#000000-#ffffff' -depth 8 -compress none "$W/p3.tif"
tiffcp -c none "$W/p1.tif" "$W/p2.tif" "$W/p3.tif" multipage.tif

ls -l *.tif
