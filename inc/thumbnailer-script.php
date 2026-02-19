<?php
/**
 * Vrannemstein thumbnailer javascript function
 *
 * @package vrannemstein
 * @author Leonardo Laureti
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || die();

?><script id="vrannemstein-thumbnailer-script">
/**
 * @global
 * @async
 * @constructor
 * @external 'vrannemstein_config'
 * @param {array} images
 * @param {boolean} batch
 */
async function vrannemstein(images, batch) {
  const $fn = vrannemstein;
  const options = vrannemstein_config;

  Object.defineProperties($fn, {
    /**
     * @public
     * @virtual
     * @member {Function} readxmp
     * @param {string} xmpData
     * @param {string} source_url
     */
    readxmp: {
      configurable: options.readXmp,
      writable: true,
      value: $fn.readxmp instanceof Function ? $fn.readxmp : (xmpData, source_url) => undefined
    },
    /**
     * @public
     * @virtual
     * @member {Function} readexif
     * @param {string} exifData
     * @param {string} source_url
     */
    readexif: {
      configurable: options.readExif,
      writable: true,
      value: $fn.readexif instanceof Function ? $fn.readexif : (exifData, source_url) => undefined
    },
    /**
     * @public
     * @virtual
     * @member {Function} readiptc
     * @param {string} iptcData
     * @param {string} source_url
     */
    readiptc: {
      configurable: options.readIptc,
      writable: true,
      value: $fn.readiptc instanceof Function ? $fn.readiptc : (iptcData, source_url) => undefined
    },
    /**
     * @public
     * @virtual
     * @member {Function} writexmp
     * @param {string} source_url
     */
    writexmp: {
      writable: true,
      value: $fn.writexmp instanceof Function ? $fn.writexmp : (source_url) => undefined
    },
    /**
     * @public
     * @virtual
     * @member {Function} writexmp
     * @param {string} source_url
     */
    writeexif: {
      writable: true,
      value: $fn.writeexif instanceof Function ? $fn.writeexif : (source_url) => undefined
    },
    /**
     * @public
     * @virtual
     * @member {Function} writeiptc
     * @param {string} source_url
     */
    writeiptc: {
      writable: true,
      value: $fn.writeiptc instanceof Function ? $fn.writeiptc : (source_url) => undefined
    }
  });
  const log = (...message) => (options.verbosity & 1) && console.log.call(console, ...message);
  const info = (...message) => (options.verbosity & 2) && console.info.call(console, ...message);
  const error = (...message) => (options.verbosity & 4) && console.error.call(console, ...message);

  /**
   * @private
   * @async
   * @external Vips
   * @external 'vrannemstein.$vips'
   */
  const $vips = async(options) => {
    if (batch && $fn.$vips)
      return $fn.$vips;
    else if (batch)
      return $fn.$vips = Vips(options);
    else
      return Vips(options);
  };
  const vips = await $vips({
    dynamicLibraries: options.dynamicLibraries,
    noImageDecoding: options.noImageDecoding,
    preRun: (module) => {
      if (options.debug) {
        module.ENV.VIPS_INFO = 1;
        module.ENV.VIPS_LEAK = 1;
      }
    }
  });

  log('vips loaded', vips.version());

  /**
   * @private
   * @param {ArrayBuffer} blob
   * @param {string} type
   * @param {string} name
   * @return {URL}
   */
  function tourl(blob, type, name) {
    const obj = URL.createObjectURL(new Blob([blob], {type}));
    const a = document.createElement('a');
    a.href = obj;
    a.innerText = `thumbnail ${name}`;
    document.body.appendChild(a);
    return obj;
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
  function jpegShrink(shrink) {
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
    const {source_url, mime, extname, src_w, src_h, dst_w, dst_h, crop} = data;
    let readOpts = {};

    const xmpData = $fn.writexmp instanceof Function ? $fn.writexmp(source_url) : null;
    const exifData = $fn.writeexif instanceof Function ? $fn.writeexif(source_url) : null;
    const iptcData = $fn.writeiptc instanceof Function ? $fn.writeiptc(source_url) : null;

    if (/image\/jpeg/.test(mime)) {
      let {shrink} = shrinking(src_w, src_h, dst_w, dst_h, crop);
      readOpts.shrink = jpegShrink(shrink); // integer
      info(' ', 'thumb shrink', name, {initial: shrink, jpeg: readOpts.shrink});
    }

    using image = vips.Image.newFromBuffer(blob, readOpts);
    image.setDeleteOnClose(true);

    //todo test equal bits full size
    // if (hshrink > 1 && vshrink > 1)
    let {hshrink, vshrink} = shrinking(image.width, image.height, dst_w, dst_h, crop);
    info(' ', 'thumb reduce', name, {hshrink, vshrink, crop});
    let thumb = image.reduce(hshrink, vshrink, options.reduce);

    if (crop)
      thumb = thumb.smartcrop(dst_w, dst_h, options.smartcrop);

    if (writeOpts.keep && (writeOpts.keep & 1))
      thumb = thumb.copy({xres: 72 / 25.4, yres: 72 / 25.4}); // px/mm

    if (xmpData) {
      if (xmpData instanceof Uint8Array) {
        thumb.setBlob('xmp-data', xmpData);
      } else if (xmpData instanceof String) {
        const xmp_data = new Uint8Array([...xmpData].map((c) => c.codePointAt(0)));
        thumb.setBlob('xmp-data', xmp_data);
      }
    }
    if (exifData) {
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
    if (iptcData && iptcData instanceof Uint8Array) {
      thumb.setBlob('iptc-data', iptcData);
    }

    log('thumbnail', name, {w: thumb.width, h: thumb.height});

    const out = thumb.writeToBuffer(extname, writeOpts);
    thumb = null;
    // return tourl(out, mime, name); // testing

    return out;
  }

  /**
   * @private
   * @async
   * @param {string} source_url
   * @param {ArrayBuffer} blob
   * @return {object}
   */
  async function image(source_url, blob) {
    const filename = source_url.replace(/.+\/([^/]+)/, '$1');
    const extname = filename.replace(/.+(\.[^\.]+)$/, '$1');

    using source = vips.Image.newFromBuffer(blob);
    source.setDeleteOnClose(true);

    let mime, writeOpts;
    const loader = source.getString('vips-loader');

    if (/^jpeg/.test(loader)) {
      mime = 'image/jpeg', writeOpts = options.jpegsave ?? null;
    } else if (/^png/.test(loader)) {
      mime = 'image/png', writeOpts = options.pngsave ?? null;
    } else if (/^gif/.test(loader)) {
      mime = 'image/gif', writeOpts = options.gifsave ?? null;
    } else if (/^webp/.test(loader)) {
      mime = 'image/webp', writeOpts = options.webpsave ?? null;
    } else if (/^avif/.test(loader)) {
      // heifsave compression VipsForeignHeifCompression(1 hevc, 2 avc, 3 jpeg, 4 av1)
      // heifsave encoder VipsForeignHeifCompression(0 auto, 1 aom, 2 rav1e, 3 svt, 4 x265)
      const opts = {compression: 4, encoder: 1};
      mime = 'image/avif', writeOpts = {...(options.avifsave ?? null), ...opts};
    } else {
      return error('Not supported mime-type.', {loader});
    }

    // libvips header.h  VIPS_META_XMP_NAME "xmp-data"
    if (options.readXmp && source.getTypeof('xmp-data') != 0) {
      const xmp_data = source.getBlob('xmp-data');
      const xmpData = String.fromCodePoint(...xmp_data);
      if ($fn.readxmp instanceof Function)
        $fn.readxmp.call(this, xmpData, source_url);
    }
    // libvips header.h  VIPS_META_EXIF_NAME "exif-data"
    if (options.readExif && source.getTypeof('exif-data') != 0) {
      const exif_data = source.getBlob('exif-data');
      const exifData = String.fromCodePoint(...exif_data);
      if ($fn.readexif instanceof Function)
        $fn.readexif.call(this, exifData, source_url);
    }
    // libvips header.h  VIPS_META_IPTC_NAME "iptc-data"
    if (options.readIptc && source.getTypeof('iptc-data') != 0) {
      const iptc_data = source.getBlob('iptc-data');
      const iptcData = String.fromCodePoint(...iptc_data);
      if ($fn.readiptc instanceof Function)
        $fn.readiptc.call(this, iptcData, source_url);
    }

    const image_sizes = options.image_sizes;
    const thumbs = {};

    //todo medium == medium-large to skip
    for (const size in image_sizes) {
      const {width: mw, height: mh, crop} = image_sizes[size];
      const name = `${filename}[${size}]`;

      const {width: src_w, height: src_h} = source;
      const hv = src_w / src_h;
      const ratio = hv >= 1 ? src_w / src_h : src_h / src_w;

      let dst_w = mw;
      let dst_h = mh;

      if (mw == mh) {
        dst_w = hv >= 1 ? mw : Math.ceil(mw / ratio);
        dst_h = hv >= 1 ? Math.ceil(mh / ratio) : mh;
      } else if (mw != 0) {
        dst_w = mw;
        dst_h = Math.ceil(mw / ratio);
      } else if (mh != 0) {
        dst_w = Math.ceil(mh / ratio);
        dst_h = mh;
      }
      if (crop) {
        dst_w = dst_h = Math.max(dst_w, dst_h);
      }

      info('thumb', name, {dst_w, dst_h, mw, mh, crop});

      if (dst_w > src_w || dst_h > src_h) {
        info(' ', 'thumb skip', name, {src_w, src_h});
        continue;
      }

      const fname = filename.replace(/(\.[^\.]+)$/, `-${dst_w}x${dst_h}$1`);
      const data = {src_w, src_h, dst_w, dst_h, crop};
      const thumb = await thumbnail(blob, writeOpts, name, {source_url, mime, extname, ...data});
      thumbs[size] = {blob: thumb, mime, filename: fname, data};
    };

    return {source_url, thumbs};
  }

  const p = [];
  for (const source_url of images) {
    if (! /\.(jpg|jpeg|jpe|gif|png|webp|avif)$/i.test(source_url)) {
      error('Not supported file format.');
      continue;
    }
    p.push(
      new Promise((resolve, reject) => {
        fetch(source_url, {mode: 'same-origin'})
        .then((response) => {
            if (! response.ok)
              throw new Error(`HTTP Error status: ${response.status}`);

            return response.arrayBuffer();
        })
        .catch(err => reject(err))
        .then(blob => image(source_url, blob))
        .then(thumbs => resolve(thumbs));
      })
    );
  }

  return Promise.all(p);
}

// Object.defineProperty(vrannemstein, 'readxmp', {
//   configurable: true,
//   value: (xmpData, source_url) => console.log('readxmp', xmpData, source_url)
// });
// Object.defineProperty(vrannemstein, 'readexif', {
//   configurable: true,
//   value: (exifData, source_url) => console.log('readexif', exifData, source_url)
// });
// Object.defineProperty(vrannemstein, 'readiptc', {
//   configurable: true,
//   value: (iptcData, source_url) => console.log('readiptc', iptcData, source_url)
// });
// Object.defineProperty(vrannemstein, 'writexmp', {
//   configurable: true,
//   value: (source_url) => '<x:xmpmeta xmlns:x="adobe:ns:meta/"></x:xmpmeta>'
// });
// Object.defineProperty(vrannemstein, 'writeexif', {
//   configurable: true,
//   value: (source_url) => ({IFD2: {UserComment: 'test'}})
// });
// Object.defineProperty(vrannemstein, 'writeiptc', {
//   configurable: true,
//   value: (source_url) => (new Uint8Array())
// });

// vrannemstein.readxmp = (xmpData, source_url) => console.log('readxmp', xmpData, source_url);
// vrannemstein.readexif = (exifData, source_url) => console.log('readexif', exifData, source_url);
// vrannemstein.readiptc = (iptcData, source_url) => console.log('readiptc', iptcData, source_url);
// vrannemstein.writexmp = (source_url) => '<x:xmpmeta xmlns:x="adobe:ns:meta/"></x:xmpmeta>';
// vrannemstein.writeexif = (source_url) => ({ IFD2: { UserComment: 'test' } });
// vrannemstein.writeiptc = (source_url) => (new Uint8Array());
</script>

