# Vrannemstein

Vrannemstein is a WordPress plugin to make image thumbnails via the client-side.

It uses [wasm-vips](https://github.com/kleisauke/wasm-vips), a WebAssembly (Emscripten) flavor of [libvips](https://www.libvips.org/) (vips image processing library).

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
            'center' => true, // default true
            'kernel' => 5 // resample kernel, default 5, VipsKernel(0 nearest, 1 linear, 2 cubic, 3 mitchell, 4 lanczos2, 5 lanczos3, 6 mks2013, 7 mks2021)
        ),
        'smartcrop' => array(
            'interesting' => 1 // default 3, VipsInteresting(0 none, 1 centre, 2 entropy, 3 attention, 4 low, 5 high, 6 all)
        ),
        'jpegsave' => array(
            'Q' => 85, // quality factor, defaults wp 82, php 75, gd 75, vips 75
            'interlace' => false, // progressive jpeg, default false
            'optimize_coding' => true, // defaults gd false, vips false, sharp-js true
            'quant_table' => 3, // defaults gd 0, mozjpeg 3, vips 0
            'trellis_quant' => true, // default false
            'subsample_mode' => 0, // jpeg chroma subsample, default 0, VipsForeignSubsample(0 auto, 1 YUV420, 2 YUV444)
            'keep' => 3 // keep metadata flags, VipsForeignKeep(0 none, 1 exif, 2 xmp, 4 iptc, 8 icc, 16 other, 31 all)
        ),
        'pngsave' => array(
            'Q' => 100, // quantization value, default 100, min 0, max 100
            'compression' => 9, // default 6, min 1, max 10
            'dither' => 0, // default 100, min 0, max 100
            'interlace' => false, // progressive png, default false
            'palette' => true, // png 8-bit 256 colors palette, default false
            'bitdepth' => 8, // palette bit-depth, default 8, min 1, max 8
            'effort' => 7, // cpu effort on quantization, default 7, min 1, max 10
            'keep' => 3 // keep metadata flags, VipsForeignKeep(0 none, 1 exif, 2 xmp, 4 iptc, 8 icc, 16 other, 31 all)
        ),
        'gifsave' => array(
            'dither' => 1, // default 1, min 0, max 1
            'interlace' => false, // progressive gif, default false
            'bitdepth' => 8, // palette bit-depth, default 8, min 1, max 8
            'effort' => 7, // cpu effort on quantization, default 7, min 1, max 10
            'keep' => 3 // keep metadata flags, VipsForeignKeep(0 none, 1 exif, 2 xmp, 4 iptc, 8 icc, 16 other, 31 all)
        ),
        'webpsave' => array(
            'Q' => 88, // quality factor, defaults wp 86, php 80, gd 75, vips 75
            'smart_deblock' => true, // default false
            'smart_subsample' => true, // default false
            'exact' => true, // preserve color alpha, default false
            'effort' => 4, // cpu effort on file size, default 4, min 0, max 6
            'keep' => 3 // keep metadata flags, VipsForeignKeep(0 none, 1 exif, 2 xmp, 4 iptc, 8 icc, 16 other, 31 all)
        ),
        'avifsave' => array( // avifsave is heifsave in vips
            'Q' => 55, // quality factor, defaults wp 50, php 52, vips 50
            'bitdepth' => 10, // default 12, min 8, max 12
            'subsample_mode' => 0, // av1 chroma subsample, default 0, VipsForeignSubsample(0 auto, 1 YUV420, 2 YUV444)
            'effort' => 4, // cpu effort value, default 4, min 0, max 9
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

Replace the image source path:

```js
vrannemstein_hooks.bulkImageSourceUrl = (source_url, src) => {
  return source_url.replace('/wp-content/uploads', '/wp-content/images-hd-src');
};
```

Change the quality factor on a per-size basis:

```js
var quality_factors = {
  'thumbnail': 80,
  'medium': 82,
  'medium_large': 82,
  'large': 86,
  'full': 86,
  'post-thumbnail': 80,
  '1536x1536': 80,
  '2048x2048': 80
};

vrannemstein_hooks.imageWriteOpts = (opts, writeOpts, sizes, source_url, extname) => {
  const {size} = sizes;

  if (/\.jpe?g$/i.test(extname)) {
    opts.Q = quality_factors[size];
  }

  return opts;
};
```

Change the resample kernel per image format:
```js
vrannemstein_hooks.imageThumbOpts = (opts, thumbOpts, sizes, source_url, extname) => {
  if (/\.(webp|avif)/i).test(extname)) {
    thumbOpts.reduce = {kernel: 1}; // VipsKernel(1 linear)
  }

  return opts;
}
```

Exclude an image from the bulk resizer:

```js
vrannemstein_hooks.imageThumbOpts = (opts, thumbOpts, sizes, source_url, extname) => {
  const filename = source_url.replace(/.+\/([^/]+)/, '$1');

  if (extname === '.gif' && filename === 'animated.gif') {
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
> See the `www-config-sample` folder for examples to configure the server properly.


## License

Licensed under the terms of the [GNU GPLv2 License](https://github.com/ctlcltd/vrannemstein/blob/main/LICENSE-GPL-2.0-or-later), version 2 or any later version.

