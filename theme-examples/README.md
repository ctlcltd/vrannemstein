# Theme Examples

Vrannemstein examples theme [Twenty Twenty-Five Child Theme].

For testing purpose only.


## Screenshot

Image Darkroom in WP Media List page, testing quality factor per size.

![Screenshot: Image Darkroom in WP Media List page, testing quality factor per size](https://github.com/ctlcltd/vrannemstein/raw/main/theme-examples/screenshot.avif)


## Usage

Copy the folder "theme-examples" in the WordPress themes directory.

See [functions.php](https://github.com/ctlcltd/vrannemstein/blob/main/theme-examples/functions.php) and [examples.php](https://github.com/ctlcltd/vrannemstein/blob/main/theme-examples/examples.php) files.

> [!IMPORTANT]
> Use a fresh WordPress install in a testing environment.

See the example comment description in [examples.php](https://github.com/ctlcltd/vrannemstein/blob/main/theme-examples/examples.php) for file requirements.

Usage example:

Resample by format (".webp" or ".avif" file requirement), place in theme "functions.php" or "wp-config.php" file:
```php
define( 'EXAMPLES__hooks_resample_by_format', true );
```

