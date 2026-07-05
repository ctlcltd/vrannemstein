<?php
/**
 * Vrannemstein thumbnailer javascript function
 *
 * @package vrannemstein
 * @version 0.1.6
 * @author Leonardo Laureti
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || die();

?><script id="vrannemstein-thumbnailer-script">
/**
 * @public
 * @global
 * @async
 * @constructor
 * @param {array} images
 * @param {boolean} batch
 */
async function vrannemstein(images, batch) {
  const $fn = vrannemstein;
  /** @external vrannemstein_hooks */
  const $hooks = window.vrannemstein_hooks || {};
  /**
   * @external vrannemstein_config
   * @see globalThis.vrannemstein_config
   */
  const options = vrannemstein_config;

  const log = (...message) => (options.verbosity & 1) && console.log.call(console, ...message);
  const info = (...message) => (options.verbosity & 2) && console.info.call(console, ...message);
  const error = (...message) => (options.verbosity & 4) && console.error.call(console, ...message);

  let em_opts = {};
  for (const key of ['noImageDecoding', 'dynamicLibraries', 'preInit', 'preRun', 'postRun', 'onAbort', 'onRuntimeInitialized']) {
    if (Object.keys(options).indexOf(key) !== -1)
      em_opts[key] = options[key];
  }
  if (options.debug && ! em_opts.preRun) {
    em_opts.preRun = (module) => {
      module.ENV.VIPS_INFO = 1;
      module.ENV.VIPS_LEAK = 1;
    };
  }
  console.log({...em_opts});

  /**
   * @protected
   * @virtual
   * @async
   * @requires globalThis.Vips
   * @external Vips
   */
  const $vips = async(options) => {
    if (batch && $fn.$vips)
      return $fn.$vips;
    else if (batch)
      return $fn.$vips = Vips(options);
    else
      return Vips(options);
  };
  const vips = await $vips(em_opts);

  log('vips loaded', vips.version());

  /**
   * @private
   * @param {string} filename
   * @param {object} sizes
   * @return {string}
   */
  function imageDestFilename(filename, sizes) {
    const {dst_w, dst_h} = sizes;
    const dest_filename = filename.replace(/(\.[^\.]+)$/, `-${dst_w}x${dst_h}$1`);

    /**
     * @function external:vrannemstein_hooks.imageDestFilename
     * @param {string} dest_filename
     * @param {string} filename
     * @param {object} sizes
     * @param {string} sizes.size
     * @param {number} sizes.src_w
     * @param {number} sizes.src_h
     * @param {number} sizes.dst_w
     * @param {number} sizes.dst_h
     * @param {boolean} sizes.crop
     * @return {string}
     */
    if ($hooks.imageDestFilename && $hooks.imageDestFilename instanceof Function)
      return $hooks.imageDestFilename(dest_filename, filename, sizes);

    return dest_filename;
  }

  /**
   * @private
   * @param {object} thumbOpts
   * @param {object} sizes
   * @param {string} source_url
   * @param {string} extname
   * @return {object}
   */
  function imageThumbOpts(thumbOpts, sizes, source_url, extname) {
    const {src_w, src_h, dst_w, dst_h} = sizes;
    const opts = (dst_w > src_w || dst_h > src_h) ? null : thumbOpts;

    /**
     * @function external:vrannemstein_hooks.imageThumbOpts
     * @param {object} opts
     * @param {object} thumbOpts
     * @param {boolean} [thumbOpts.preShrink=true]
     * @param {boolean} [thumbOpts.resize=true]
     * @param {boolean} [thumbOpts.crop=true]
     * @param {number} [thumbOpts.density]
     * @param {object} [thumbOpts.reduce]
     * @param {object} [thumbOpts.smartcrop]
     * @param {object} sizes
     * @param {string} sizes.size
     * @param {number} sizes.src_w
     * @param {number} sizes.src_h
     * @param {number} sizes.dst_w
     * @param {number} sizes.dst_h
     * @param {boolean} sizes.crop
     * @param {string} source_url
     * @param {string} extname
     * @return {(object|null)}
     */
    if ($hooks.imageThumbOpts && $hooks.imageThumbOpts instanceof Function)
      return $hooks.imageThumbOpts(opts, thumbOpts, sizes, source_url, extname);
      
    return opts;
  }

  /**
   * @private
   * @param {object} readOpts
   * @param {string} source_url
   * @param {string} extname
   * @return {object}
   */
  function imageReadOpts(readOpts, source_url, extname) {
    /**
     * @function external:vrannemstein_hooks.imageReadOpts
     * @param {object} opts
     * @param {object} readOpts
     * @param {string} source_url
     * @param {string} extname
     * @return {object}
     */
    if ($hooks.imageReadOpts && $hooks.imageReadOpts instanceof Function)
      return $hooks.imageReadOpts({...readOpts}, readOpts, source_url, extname);
      
    return readOpts;
  }

  /**
   * @private
   * @param {object} writeOpts
   * @param {object} sizes
   * @param {string} source_url
   * @param {string} extname
   * @return {object}
   */
  function imageWriteOpts(writeOpts, sizes, source_url, extname) {
    /**
     * @function external:vrannemstein_hooks.imageWriteOpts
     * @param {object} opts
     * @param {object} writeOpts
     * @param {object} sizes
     * @param {string} sizes.size
     * @param {number} sizes.src_w
     * @param {number} sizes.src_h
     * @param {number} sizes.dst_w
     * @param {number} sizes.dst_h
     * @param {boolean} sizes.crop
     * @param {string} source_url
     * @param {string} extname
     * @return {object}
     */
    if ($hooks.imageWriteOpts && $hooks.imageWriteOpts instanceof Function)
      return $hooks.imageWriteOpts({...writeOpts}, writeOpts, sizes, source_url, extname);
      
    return writeOpts;
  }

  /**
   * @private
   * @param {number} src_w
   * @param {number} src_h
   * @param {number} dst_w
   * @param {number} dst_h
   * @param {boolean} crop
   * @return {object}
   */
  function shrinking(src_w, src_h, dst_w, dst_h, crop) {
    let hshrink = 1.0;
    let vshrink = 1.0;
    if (dst_w != 0 && dst_h != 0) {
      hshrink = src_w / dst_w;
      vshrink = src_h / dst_h;
      if (crop) {
        if (hshrink < vshrink)
          vshrink = hshrink;
        else
          hshrink = vshrink;
      }
    } else if (dst_w != 0) {
      hshrink = src_w / dst_w;
      vshrink = hshrink;
    } else if (dst_h != 0) {
      vshrink = src_h / dst_h;
      hshrink = vshrink;
    }
    hshrink = parseFloat(Math.min(hshrink, src_w));
    vshrink = parseFloat(Math.min(vshrink, src_h));
    const shrink = Math.min(hshrink, vshrink);
    return {hshrink, vshrink, shrink};
  }

  /**
   * @private
   * @param {number} shrink
   * @return {number}
   */
  function preShrink(shrink) {
    let s = 1;
    if (shrink >= 8 * s) s = 8;
    else if (shrink >= 4 * s) s = 4;
    else if (shrink >= 2 * s) s = 2;
    if (s > 1 && parseInt(shrink) == s) s /= 2;
    return s;
  }

  /**
   * @private
   * @async
   * @param {ArrayBuffer} blob
   * @param {object} writeOpts
   * @param {string} name
   * @param {object} data
   */
  async function thumbnail(blob, writeOpts, name, data) {
    const {source_url, mime, dest_url, extname, thumbOpts, src_w, src_h, dst_w, dst_h, crop} = data;
    let readOpts = {};

    /**
     * @function external:vrannemstein_hooks.writeXmp
     * @param {string} source_url
     * @return {string}
     */
    const xmpData = $hooks.writeXmp && $hooks.writeXmp instanceof Function ? $hooks.writeXmp(source_url) : null;
    /**
     * @function external:vrannemstein_hooks.writeExif
     * @param {string} source_url
     * @return {string}
     */
    const exifData = $hooks.writeExif && $hooks.writeExif instanceof Function ? $hooks.writeExif(source_url) : null;
    /**
     * @function external:vrannemstein_hooks.writeIptc
     * @param {string} source_url
     * @return {string}
     */
    const iptcData = $hooks.writeIptc && $hooks.writeIptc instanceof Function ? $hooks.writeIptc(source_url) : null;

    if (thumbOpts.preShrink && /image\/jpeg/.test(mime)) {
      let {shrink} = shrinking(src_w, src_h, dst_w, dst_h, crop);
      readOpts.shrink = preShrink(shrink); // integer
      info(' ', 'thumb shrink', name, {initial: shrink, preShrink: readOpts.shrink});
    }

    const r_opts = imageReadOpts(readOpts, source_url, extname);

    const image = vips.Image.newFromBuffer(blob, r_opts);
    image.setDeleteOnClose(true);
    let thumb = image;

    /**
     * @function external:vrannemstein_hooks.imagePreprocessor
     * @param {Vips} vips
     * @param {VipsImage} image
     * @param {string} source_url
     * @param {object} readOpts
     * @param {object} writeOpts
     * @param {VipsImage} thumb
     */
    if ($hooks.imagePreprocessor && $hooks.imagePreprocessor instanceof Function) {
      thumb = $hooks.imagePreprocessor(vips, thumb, source_url, readOpts, writeOpts);
    }

    if (thumbOpts.resize) {
      let {hshrink, vshrink} = shrinking(image.width, image.height, dst_w, dst_h, crop);
      info(' ', 'thumb reduce', name, {hshrink, vshrink, crop});
      thumb = thumb.reduce(hshrink, vshrink, thumbOpts.reduce ?? options.reduce ?? null);
    }

    if (thumbOpts.crop && crop) {
      thumb = thumb.smartcrop(dst_w, dst_h, thumbOpts.smartcrop ?? options.smartcrop ?? null);
    }

    if (writeOpts.keep && (writeOpts.keep & 1) && (thumbOpts.density || options.density)) {
      const density = thumbOpts.density || options.density;
      thumb = thumb.copy({xres: density / 25.4, yres: density / 25.4}); // px/mm
    }

    if (xmpData != null) {
      thumb.remove('xmp-data');
      if (xmpData instanceof Uint8Array) {
        thumb.setBlob('xmp-data', xmpData);
      } else if (typeof xmpData === 'string') {
        const xmp_data = new Uint8Array([...xmpData].map((c) => c.codePointAt(0)));
        thumb.setBlob('xmp-data', xmp_data);
      }
    }
    if (exifData != null) {
      const fields = thumb.getFields();
      for (let i = 0; i < fields.size(); i++) {
        if (/exif-/.test(fields.get(i)))
          thumb.remove(fields.get(i))
      }
      if (exifData instanceof Uint8Array) {
        thumb.setBlob('exif-data', exifData);
      } else if (exifData instanceof Object) {
        Object.entries(exifData).flatMap(a => Object.entries(a[1]).map(b => {
          return [a[0].toLowerCase() + '-' + b[0], b[1]]
        })).forEach(a => {
          thumb.setString(`exif-${a[0]}`, a[1]);
        });
      }
    }
    if (iptcData != null) {
      thumb.remove('iptc-data');
      if (iptcData instanceof Uint8Array) {
        thumb.setBlob('iptc-data', iptcData);
      }
    }

    log('thumbnail', name, {w: thumb.width, h: thumb.height});
  
    /**
     * @function external:vrannemstein_hooks.imagePostprocessor
     * @param {Vips} vips
     * @param {VipsImage} image
     * @param {string} source_url
     * @param {object} readOpts
     * @param {object} writeOpts
     * @return {VipsImage}
     */
    if ($hooks.imagePostprocessor && $hooks.imagePostprocessor instanceof Function) {
      thumb = $hooks.imagePostprocessor(vips, thumb, source_url, readOpts, writeOpts);
    }

    const out = thumb.writeToBuffer(extname, writeOpts);
    thumb = null;

    return out;
  }

  /**
   * @private
   * @async
   * @param {string} source_url
   * @param {string} dest_url
   * @param {ArrayBuffer} blob
   * @return {object}
   */
  async function image(source_url, dest_url, blob) {
    const filename = dest_url.replace(/.+\/([^/]+)/, '$1');
    const extname = filename.replace(/.+(\.[^\.]+)$/, '$1');

    const r_opts = imageReadOpts(null, source_url, extname);

    const source = vips.Image.newFromBuffer(blob, r_opts);
    source.setDeleteOnClose(true);

    const loader = source.getString('vips-loader');

    if (! /^(jpeg|png|gif|webp|svg|avif|heif|jxl|ppm|tiff|raw|rad|uhdr|analyze6|csv|matrix|vips)/.test(loader)) {
      return error('Not supported file format input.', {loader});
    }

    let mime, writeOpts;

    if (/jpg|jpeg|jpe/.test(extname)) {
      mime = 'image/jpeg';
      writeOpts = options.jpegsave;
    } else if (/png/.test(extname)) {
      mime = 'image/png';
      writeOpts = options.pngsave;
    } else if (/gif/.test(extname)) {
      mime = 'image/gif';
      writeOpts = options.gifsave;
    } else if (/webp/.test(extname)) {
      mime = 'image/webp';
      writeOpts = options.webpsave;
    } else if (/avif/.test(extname)) {
      // heifsave compression VipsForeignHeifCompression(1 hevc, 2 avc, 3 jpeg, 4 av1)
      // heifsave encoder VipsForeignHeifCompression(0 auto, 1 aom, 2 rav1e, 3 svt, 4 x265)
      const opts = {compression: 4, encoder: 1};
      mime = 'image/avif';
      writeOpts = {...options.avifsave, ...opts};
    } else if (options.checkMimeType ?? true) {
      return error('Not supported file format output.', {extname});
    }

    // libvips header.h  VIPS_META_XMP_NAME "xmp-data"
    if (options.readXmp && source.getTypeof('xmp-data') != 0) {
      const xmp_data = source.getBlob('xmp-data');
      const xmpData = String.fromCodePoint(...xmp_data);

      /**
       * @function external:vrannemstein_hooks.readXmp
       * @param {string} xmpData
       * @param {string} source_url
       * @return {string}
       */
      if ($hooks.readXmp && $hooks.readXmp instanceof Function)
        $hooks.readXmp(xmpData, source_url);
    }
    // libvips header.h  VIPS_META_EXIF_NAME "exif-data"
    if (options.readExif && source.getTypeof('exif-data') != 0) {
      const exif_data = source.getBlob('exif-data');
      const exifData = String.fromCodePoint(...exif_data);

      /**
       * @function external:vrannemstein_hooks.readExif
       * @param {string} exifData
       * @param {string} source_url
       * @return {string}
       */
      if ($hooks.readExif && $hooks.readExif instanceof Function)
        $hooks.readExif(exifData, source_url);
    }
    // libvips header.h  VIPS_META_IPTC_NAME "iptc-data"
    if (options.readIptc && source.getTypeof('iptc-data') != 0) {
      const iptc_data = source.getBlob('iptc-data');
      const iptcData = String.fromCodePoint(...iptc_data);

      /**
       * @function external:vrannemstein_hooks.readIptc
       * @param {string} iptcData
       * @param {string} source_url
       * @return {string}
       */
      if ($hooks.readIptc && $hooks.readIptc instanceof Function)
        $hooks.readIptc(iptcData, source_url);
    }

    const image_sizes = options.image_sizes;
    const thumbs = {};

    for (const size in image_sizes) {
      const {width: mw, height: mh, crop} = image_sizes[size];
      const name = `${filename}[${size}]`;

      const {width: src_w, height: src_h} = source;
      const hv = src_w / src_h;
      const ratio = hv >= 1 ? src_w / src_h : src_h / src_w;

      let dst_w = mw;
      let dst_h = mh;

      if (mw == mh) {
        dst_w = hv >= 1 ? mw : Math.round(mw / ratio);
        dst_h = hv >= 1 ? Math.round(mh / ratio) : mh;
      } else if (mw != 0) {
        dst_w = mw;
        dst_h = hv >= 1 ? Math.round(mw / ratio) : Math.round(mw * ratio);
      } else if (mh != 0) {
        dst_w = hv >= 1 ? Math.round(mh / ratio) : Math.round(mh * ratio);
        dst_h = mh;
      }
      if (crop) {
        dst_w = dst_h = Math.max(dst_w, dst_h);
      }

      info('thumb', name, {dst_w, dst_h, mw, mh, crop});

      const sizes = {size, src_w, src_h, dst_w, dst_h, crop};
      const thumbOpts = imageThumbOpts({preShrink: true, resize: true, crop: true}, sizes, source_url, extname);

      if (! thumbOpts) {
        info(' ', 'thumb skip', name, {src_w, src_h});
        continue;
      }

      const w_opts = imageWriteOpts(writeOpts, sizes, source_url, extname);
      const dest_filename = imageDestFilename(filename, sizes);
      const thumb = await thumbnail(blob, w_opts, name, {source_url, mime, dest_url, extname, thumbOpts, ...sizes});
      thumbs[size] = {blob: thumb, mime, filename: dest_filename, sizes};
    };

    return {source_url, thumbs};
  }

  if (! options.image_sizes)
    throw new Error('Misleading configuration "image_sizes"');

  const p = [];
  for (const {source_url, dest_url} of images) {
    p.push(
      new Promise((resolve, reject) => {
        fetch(source_url, {mode: 'same-origin'})
        .then((response) => {
            if (! response.ok)
              throw new Error(`HTTP Error status: ${response.status}`);

            return response.arrayBuffer();
        })
        .catch(err => reject(err))
        .then(blob => image(source_url, dest_url, blob))
        .then(thumbs => resolve(thumbs));
      })
    );
  }

  return Promise.all(p);
}
</script>

