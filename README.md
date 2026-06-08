# Vrannemstein

Vrannemstein is a WordPress plugin to make image thumbnails via the client-side.

It uses [wasm-vips](https://github.com/kleisauke/wasm-vips), a WebAssembly (Emscripten) flavor of [libvips](https://www.libvips.org/) (vips image processing library).

*Under testing*

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

Activate the plugin on WordPress Plugins (from WP-CLI `wp plugin activate vrannemstein`).

A typical usage requires to configure your web server with the `wasm-vips` specific Cross-Origin policies, to enforce CORS isolation and to allow the `SharedArrayBuffer` browser feature.

```
Cross-Origin-Embedder-Policy: require-corp
Cross-Origin-Opener-Policy: same-origin
```

> [!TIP]
> See the `www-config-sample` folder for examples to configure the server properly.

Next, overrides the plugin configuration from your theme or plugin, through the `'vrannemstein_config'` filter.

See [vranemstein.php](https://github.com/ctlcltd/vrannemstein/blob/main/vrannemstein.php) and [wasm-vips/lib/vips.d.ts](https://github.com/kleisauke/wasm-vips/blob/master/lib/vips.d.ts) for the full options.

```php
function config_example( $config ) {
    // manipulate the options array

    return array(
        'verbosity' => 7, // flags (0 none, 1 log, 2 info, 4 error, 7 all)
        'debug' => false, // debug wasm-vips
        'dynamicLibraries' => [], // dynamic wasm-vips libraries
        'noImageDecoding' => true, // disallow browser image decoding wasm-vips default true
        'image_sizes' => $config['image_sizes'], // default wp_get_registered_image_subsizes()
        'density' => 72, // metadata resolution in dpi, default 72 (false = exif)
        // 'readXmp' => true,
        // 'readExif' => true,
        // 'readIptc' => true,
        'reduce' => array(
            'center' => true, // default true
            'kernel' => 5 // resample kernel default 5, VipsKernel(0 nearest, 1 linear, 2 cubic, 3 mitchell, 4 lanczos2, 5 lanczos3, 6 mks2013, 7 mks2021)
        ),
        'smartcrop' => array(
            'interesting' => 1 // default 3, VipsInteresting(0 none, 1 centre, 2 entropy, 3 attention, 4 low, 5 high, 6 all)
        ),
        'jpegsave' => array(
            'Q' => 85, // defaults wp 82, php 75, gd 75, vips 75
            'interlace' => false, // progressive jpeg default false
			'optimize_coding' => true, // defaults gd false, vips false, sharp-js true
			'quant_table' => 3, // defaults gd 0, mozjpeg 3, vips 0
			'trellis_quant' => true, // default false
            'subsample_mode' => 0, // jpeg chroma subsample default 0, VipsForeignSubsample(0 auto, 1 on, 2 off)
            'keep' => 3 // VipsForeignKeep(0 none, 1 exif, 2 xmp, 4 iptc, 8 icc, 16 other, 31 all)
        ),
        'pngsave' => array(
            'Q' => 100, // quantization default 100, min 0, max 100
            'compression' => 9, // default 6, min 1, max 10
            'dither' => 0, // default 100, min 0, max 100
            'interlace' => false, // progressive png default false
            'palette' => true, // png 8-bit 256 colors palette default false
			'bitdepth' => 8, // palette bit depth default 8, min 1, max 8
            'effort' => 7, // cpu effort quantization default 7, min 1, max 10
            'keep' => 3 // VipsForeignKeep(0 none, 1 exif, 2 xmp, 4 iptc, 8 icc, 16 other, 31 all)
        ),
        'cgifsave' => array(
            'dither' => 1, // default 1, min 0, max 1
            'interlace' => false, // progressive gif default false
            'bitdepth' => 8, // palette bit depth default 8, min 1, max 8
            'effort' => 7, // cpu effort quantization default 7, min 1, max 10
            'keep' => 3 // VipsForeignKeep(0 none, 1 exif, 2 xmp, 4 iptc, 8 icc, 16 other, 31 all)
        ),
        'webpsave' => array(
            'Q' => 88, // defaults wp 86, php 80, gd 75, vips 75
            'smart_deblock' => true, // default false
            'smart_subsample' => true, // default false
            'exact' => true, // preserve color alpha default false
            'effort' => 4, // cpu effort on file size, default 4, min 0, max 6
            'keep' => 3 // VipsForeignKeep(0 none, 1 exif, 2 xmp, 4 iptc, 8 icc, 16 other, 31 all)
        ),
        'avifsave' => array( // avifsave is heifsave in vips
            'Q' => 55, // defaults wp 50, php 52, vips 50
            'bitdepth' => 10, // default 12, min 8, max 12
            'subsample_mode' => 0, // av1 chroma subsample default 0, VipsForeignSubsample(0 auto, 1 on, 2 off)
            'effort' => 4, // cpu effort default 4, min 0, max 9
            'keep' => 3 // VipsForeignKeep(0 none, 1 exif, 2 xmp, 4 iptc, 8 icc, 16 other, 31 all)
        )
    );
}

add_filter( 'vrannemstein_config', 'config_example' );
```

To syncronously read and write metadata (Xmp, Exif, Iptc), use the javascript function prototype properties: `readxmp`, `readexif`, `readiptc`, and: `writexmp`, `writeexif`, `writeiptc`, respectively.

Don't forget to allow configuration to read from and to keep metadata according, through config filtering `array( 'readxmp' => true, 'readexif' => true, 'readiptc' => true, 'jpegsave' => array( 'keep' => 7 ), ... )`.

```js
vrannemstein.readxmp = (xmpData, source_url) => console.log('readxmp', xmpData, source_url);
vrannemstein.readexif = (exifData, source_url) => console.log('readexif', exifData, source_url);
vrannemstein.readiptc = (iptcData, source_url) => console.log('readiptc', iptcData, source_url);

vrannemstein.writexmp = (source_url) => '<x:xmpmeta xmlns:x="adobe:ns:meta/"></x:xmpmeta>';
vrannemstein.writeexif = (source_url) => ({ IFD2: { UserComment: 'test' } });
vrannemstein.writeiptc = (source_url) => (new Uint8Array());
```

## Bulk Actions

This plugin comes with a built-in bulk action to request thumbnails update on demand, directly from the WP Media Library.

To turn off bulk actions, filters `'vrannemstein_bulk_actions'` from your theme or plugin.

```php
add_filter( 'vrannemstein_bulk_actions', '__return_false' );
```

## License

Licensed under the terms of the [GNU GPLv2 License](https://github.com/ctlcltd/vrannemstein/blob/main/LICENSE-GPL-2.0-or-later), version 2 or any later version.

