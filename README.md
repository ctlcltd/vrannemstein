# Vrannemstein

Vrannemstein is a WordPress plugin to make image thumbnails via the client-side.

It uses [wasm-vips](https://github.com/kleisauke/wasm-vips), a WebAssembly (Emscripten) flavor of [libvips](https://www.libvips.org/) (Vips image processing library).

For a more official way see at the [wordpress/gutenberg](https://github.com/wordpress/gutenberg/tree/HEAD/packages/vips) repository.


## Usage

Clone this repository in your WordPress plugins directory using `git`, or download tarball clicking "Code" and "Download ZIP".

From the `vrannemstein` directory, run `npm install` to download `wasm-vips`.

```
cd wordpress/wp-content/plugins
git clone https://github.com/ctlcltd/vrannemstein.git
cd vrannemstein
npm install
```

To update the plugin, using `git` and `npm`.
```
cd wordpress/wp-content/plugins/vrannemstein
git pull
npm update
```

Activate the plugin in the "Plugins" administration page (from WP-CLI `wp plugin activate vrannemstein`).


## Setup

Overrides the plugin configuration from your WordPress theme or plugin, through the `"vrannemstein_config"` filter.

See [vranemstein.php](https://github.com/ctlcltd/vrannemstein/blob/main/vrannemstein.php) and [wasm-vips/lib/vips.d.ts](https://github.com/kleisauke/wasm-vips/blob/master/lib/vips.d.ts) for the full options.

```php
function config_example( $config ) {
    // manipulate the options array

    return array(
        'verbosity' => 7, // flags (0 none, 1 log, 2 info, 4 error, 7 all)
        'debug' => false, // debug wasm-vips, default false
        'image_sizes' => $config['image_sizes'], // default wp_get_registered_image_subsizes()
        'noImageDecoding' => true, // disallow browser image decoding on wasm-vips load, default true
        'dynamicLibraries' => array('vips-heif.wasm'), // dynamic wasm-vips libraries [vips-jxl.wasm, vips-heif.wasm, vips-resvg.wasm]
        'checkMimeType' => true, // check for input file MIME type, default true
        // 'density' => 72, // metadata resolution in dpi, default 72 (false = use source resolution from exif data)
        // 'readXmp' => true, // allow vrannemstein_hooks.readXmp hook, default false
        // 'readExif' => true, // allow vrannemstein_hooks.readExif hook, default false
        // 'readIptc' => true, // allow vrannemstein_hooks.readIptc hook, default false
        'reduce' => array(
            'centre' => true, // sampling offset, default true, (deprecated parameter)
            'kernel' => 5 // resample kernel, default 5, VipsKernel(0 nearest, 1 linear, 2 cubic, 3 mitchell, 4 lanczos2, 5 lanczos3, 6 mks2013, 7 mks2021)
        ),
        'smartcrop' => array(
            'interesting' => 1 // crop area, default 3, VipsInteresting(0 none, 1 centre, 2 entropy, 3 attention, 4 low, 5 high, 6 all)
        ),
        'jpegsave' => array(
            'Q' => 86, // quality factor, defaults wp 82, php 75, gd 75, vips 75
            'interlace' => false, // progressive jpeg, default false
            'optimize_coding' => true, // optimize huffman tables, defaults gd false, vips false, sharp-js true
            'quant_table' => 3, // quantization table, default 0, defaults gd 0, mozjpeg 3, vips 0, enum (0 JPEG Annex K, 1 flat, 2 MSSIM tuned, 3 mozjpeg default, 4 PSNR-HVS-M tuned, 5, 6, 7, 8)
            'trellis_quant' => true, // trellis code quantization, default false
            'subsample_mode' => 0, // jpeg chroma subsampling, default 0, VipsForeignSubsample(0 auto, 1 YUV420, 2 YUV444)
            'keep' => 3 // keep metadata flags, VipsForeignKeep(0 none, 1 exif, 2 xmp, 4 iptc, 8 icc, 16 other, 31 all)
        ),
        'pngsave' => array(
            'Q' => 100, // quantization value, default 100, min 0, max 100
            'compression' => 9, // compression ratio, default 6, min 1, max 10
            'dither' => 0, // dithering value, default 100, min 0, max 100
            'interlace' => false, // progressive png, default false
            'palette' => true, // PNG-8 256 colors palette, default false
            'bitdepth' => 8, // png image bit-depth, default 8, min 1, max 16, enum (1 mono, 2 mono+alpha, 4 PNG-8, 8 PNG-24, 16 PNG-48)
            'effort' => 7, // cpu effort on quantization, default 7, min 1, max 10
            'keep' => 3 // keep metadata flags, VipsForeignKeep(0 none, 1 exif, 2 xmp, 4 iptc, 8 icc, 16 other, 31 all)
        ),
        'gifsave' => array( // gifsave is cgifsave in vips
            'dither' => 1, // dithering value, default 1, min 0, max 1
            'interlace' => false, // progressive gif, default false
            'bitdepth' => 8, // gif palette bit-depth, default 8, min 1, max 8
            'effort' => 7, // cpu effort on quantization, default 7, min 1, max 10
            'keep' => 3 // keep metadata flags, VipsForeignKeep(0 none, 1 exif, 2 xmp, 4 iptc, 8 icc, 16 other, 31 all)
        ),
        'webpsave' => array(
            'Q' => 88, // quality factor, defaults wp 86, php 80, gd 75, vips 75
            'smart_deblock' => true, // webp smart deblocking filter, default false
            'smart_subsample' => true, // webp smart chroma subsample, default false
            'exact' => true, // preserve color alpha, default false
            'effort' => 4, // cpu effort on file size, default 4, min 0, max 6
            'preset' => 0, // lossy presets, default 0, VipsForeignWebpPreset(0 default, 1 picture, 2 photo, 3 drawing, 4 icon, 5 text)
            'keep' => 3 // keep metadata flags, VipsForeignKeep(0 none, 1 exif, 2 xmp, 4 iptc, 8 icc, 16 other, 31 all)
        ),
        'avifsave' => array( // avifsave is heifsave in vips
            'Q' => 55, // quality factor, defaults wp 50, php 52, vips 50
            'bitdepth' => 10, // av1 image bit-depth, default 12, min 8, max 12
            'effort' => 4, // av1 cpu effort value, default 4, min 0, max 9
            'subsample_mode' => 0, // av1 chroma subsampling, default 0, VipsForeignSubsample(0 auto, 1 YUV420, 2 YUV444)
            'keep' => 3 // keep metadata flags, VipsForeignKeep(0 none, 1 exif, 2 xmp, 4 iptc, 8 icc, 16 other, 31 all)
        )
    );
}

add_filter( 'vrannemstein_config', 'config_example' );
```

## Bulk Resizer

This plugin comes with a built-in bulk resizer action to request thumbnails resize on demand, directly from the "Media" Library page.

To turn off the bulk action, filters `"vrannemstein_bulk_resizer"` from your theme or plugin.

```php
add_filter( 'vrannemstein_bulk_resizer', '__return_false' );
```

## Hooks [javascript]

To use javascript hooks, export a global property named `vrannemstein_hooks`.

To syncronously read and write metadata (Xmp, Exif, Iptc), use the function hooks: `vrannemstein_hooks.readXmp`, `vrannemstein_hooks.readExif`, `vrannemstein_hooks.readIptc`, and: `vrannemstein_hooks.writeXmp`, `vrannemstein_hooks.writeExif`, `vrannemstein_hooks.writeIptc`, respectively.

Don't forget to allow configuration to read from and to keep metadata according, through config filtering `array( 'readXmp' => true, 'readExif' => true, 'readIptc' => true, 'jpegsave' => array( 'keep' => 7 ), ... )`.

```js
var vrannemstein_hooks = {};

vrannemstein_hooks.readXmp = (xmpData, source_url) => console.log('readXmp', xmpData, source_url);
vrannemstein_hooks.readExif = (exifData, source_url) => console.log('readExif', exifData, source_url);
vrannemstein_hooks.readIptc = (iptcData, source_url) => console.log('readIptc', iptcData, source_url);

vrannemstein_hooks.writeXmp = (source_url) => '<x:xmpmeta xmlns:x="adobe:ns:meta/"></x:xmpmeta>';
vrannemstein_hooks.writeExif = (source_url) => ({ IFD2: { UserComment: 'test' } });
vrannemstein_hooks.writeIptc = (source_url) => (new Uint8Array());

vrannemstein_hooks.postProcessImageSourceUrl = (src) => src.replace(/\.jpe?g$/i, '.jxl');
vrannemstein_hooks.postProcessImageDestUrl = (dst) => dst.replace(/\.[^\.]*$/, '.webp');

vrannemstein_hooks.bulkImageSourceUrl = (source_url, src) => source_url.replace(/\.jpe?g$/i, '.tiff');
vrannemstein_hooks.bulkImageDestUrl = (dest_url, dst) => dest_url.replace(/\.[^\.]*$/, '.avif');

vrannemstein_hooks.imageThumbOpts = (opts, thumbOpts, sizes, source_url, extname) => ({preShrink: false, resize: false, crop: false});
vrannemstein_hooks.imageReadOpts = (opts, readOpts, source_url, extname) => ({shrink: 1, ...opts});
vrannemstein_hooks.imageWriteOpts = (opts, writeOpts, sizes, source_url, extname) => ({Q: 95, subsample_mode: 2, ...opts});
vrannemstein_hooks.imageDestFilename = (dest_filename, filename, sizes) => filename.replace(/(\.[^\.]*)$/, `-${sizes.dst_w}x${sizes.dst_h}$1`);

vrannemstein_hooks.imagePreprocessor = (vips, image, source_url, readOpts, writeOpts) => void;
vrannemstein_hooks.imagePostprocessor = (vips, image, source_url, readOpts, writeOpts) => void;
```

## Examples

Some powerful examples.

> [!TIP]
> See the folder "examples-theme" for Twenty Twenty-Five Child Theme with examples.

Replace the image source path:

```js
vrannemstein_hooks.bulkImageSourceUrl = (source_url, src) => {
  return source_url.replace('/wp-content/uploads', '/wp-content/images-hd-src');
};
```

Change the quality factor on a per-size basis:

```js
var quality_factors = {
  'thumbnail': 78,
  'medium': 76,
  'medium_large': 76,
  'large': 78,
  'full': 92,
  'post-thumbnail': 77,
  '1536x1536': 69,
  '2048x2048': 68
};

vrannemstein_hooks.imageWriteOpts = (opts, writeOpts, sizes, source_url, extname) => {
  const {size} = sizes;

  if (/\.(jpg|jpeg|jpe)$/i.test(extname)) {
    opts.Q = quality_factors[size];
  }

  return opts;
};
```

Change the resample kernel by image format:
```js
vrannemstein_hooks.imageThumbOpts = (opts, thumbOpts, sizes, source_url, extname) => {
  if (/\.(webp|avif)$/i.test(extname)) {
    thumbOpts.reduce = {kernel: 1}; // VipsKernel(1 linear)
  }

  return opts;
}
```

Exclude an image from resize:

```js
vrannemstein_hooks.imageThumbOpts = (opts, thumbOpts, sizes, source_url, extname) => {
  const filename = source_url.replace(/.+\/([^/]+)/, '$1');

  if (filename === 'animated.gif') {
    return null;
  }

  return opts;
}
```

Grayscale an image using the pre-processor hook:

```js
vrannemstein_hooks.imagePreprocessor = (vips, image, source_url, readOpts, writeOpts) => {
  const filename = source_url.replace(/.+\/([^/]+)/, '$1');

  if (filename === 'colors.jpg') {
    const grayscale = image.colourspace('b-w');

    return grayscale.copy({'interpretation': 'srgb'});
  }

  return image;
}
```

## Cross-Origin Policies

A typical usage requires to configure your web server with the `wasm-vips` specific Cross-Origin policies, to enforce CORS isolation and to allow the `SharedArrayBuffer` browser feature.

```
Cross-Origin-Embedder-Policy: require-corp
Cross-Origin-Opener-Policy: same-origin
```

> [!TIP]
> See the folder "www-config-sample" for examples to configure the server properly.


## License

Licensed under the terms of the [GNU GPLv2 License](https://github.com/ctlcltd/vrannemstein/blob/main/LICENSE-GPL-2.0-or-later), version 2 or any later version.

