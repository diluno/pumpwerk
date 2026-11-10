#!/bin/sh
# Renders the app icon PNGs from icon.svg (needs ImageMagick).
set -e
cd "$(dirname "$0")/../.."
for size in 180 192 512; do
    magick -background none -density 600 resources/icons/icon.svg -resize "${size}x${size}" -strip "public/icons/icon-${size}.png"
done
magick -background none -density 600 public/favicon.svg -resize 48x48 -strip public/favicon.ico
