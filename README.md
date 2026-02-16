# Vrannemstein

Vrannemstein is a WordPress plugin to make image thumbnails via the client-side.

It uses [wasm-vips](https://github.com/kleisauke/wasm-vips), a WebAssembly (Emscripten) flavor of [libvips](https://www.libvips.org/) (vips image processing library).

🚧 *Under development* 🚧

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

> [!TIP]
> See the `www-config-sample` folder for examples to configure the server properly.

Next, overrides the plugin configuration from your theme or plugin, through the `'vrannemstein_config'` filter.

See [vranemstein.php](https://github.com/ctlcltd/vrannemstein/blob/main/vrannemstein.php) and [wasm-vips/lib/vips.d.ts](https://github.com/kleisauke/wasm-vips/blob/master/lib/vips.d.ts) for the full options.

```php
function config_example( $config ) {
    // manipulate the options array

    return array(
        'verbosity' => 7, // flags (0 none, 1 log, 2 info, 4 error, 7 all)
        'debug' => true, // debug wasm-vips
        'reduce' => array(
            'kernel' => 1 // resample kernel, VipsKernel(0 nearest, 1 linear, 2 cubic, 3 mitchell, 4 lanczos2, 5 lanczos3, 6 mks2013, 7 mks2021)
        ),
        'jpegsave' => array(
            'Q' => 82 // defaults wp 82, php 75, gd 75, vips 75
        ),
        'gifsave' => array(
            'dither' => 0, // default 1, min 0, max 1
        ),
        'webpsave' => array(
            'Q' => 86 // defaults wp 86, php 80, gd 75, vips 75
        ),
        'avifsave' => array( // avifsave is heifsave in vips
            'Q' => 52 // defaults wp 50, php 52, vips 50
        )
    );
}

add_filter( 'vrannemstein_config', 'config_example' );
```

To syncronously read and write metadata (Xmp, Exif, Iptc), use the javascript function prototype properties: `readxmp`, `readexif`, `readiptc`, and: `writexmp`, `writeexif`, `writeiptc`, respectively. Don't forget to allow configuration to read from and to keep metadata according, through config filtering `array( 'readxmp' => true, 'readexif' => true, 'readiptc' => true, 'jpegsave' => array( 'keep' => 7 ), ... )`.

```js
vrannemstein.readxmp = (xmpData, source_url) => console.log('readxmp', xmpData);
vrannemstein.readexif = (exifData, source_url) => console.log('readexif', exifData);
vrannemstein.readiptc = (iptcData, source_url) => console.log('readiptc', iptcData);

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

Licensed under the terms of the [GNU GPLv2 License](LICENSE-GPL-2.0-or-later), version 2 or any later version.

