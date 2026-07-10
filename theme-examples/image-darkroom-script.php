<?php
/**
 * Image darkroom script example from Vrannemstein examples theme
 * 
 * Adds the Image darkroom example in WP Media List page
 *
 * @package theme-examples
 * @version 0.1
 * @author Leonardo Laureti
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || die();

?><script id="image-darkroom-script">
/**
 * @requires vrannemstein
 * @this {HTMLElement} table
 * @param {Event} event
 */
function image_darkroom_modal(event) {
  const $config = window.vrannemstein_config && {...vrannemstein_config};
  const $hooks = window.vrannemstein_hooks || {};
  const quality_defaults = window.quality_factors || {};
  const image_sizes = $config.image_sizes || {};
  const quality_values = {};
  const sizes = Object.keys(image_sizes).sort((a, b) => (image_sizes[a].width || image_sizes[a].height) - (image_sizes[b].width || image_sizes[b].height));

  const row = this.closest('tr');
  const img = row.querySelector('img');
  const src = img.src;

  /** reflects: vrannemsteinBulkResizer#bulkImageSourceUrl() */
  let source_url = src.replace(/-\d+x\d+(\.[^\.]+)$/, '$1');
  const img_url = source_url;

  /** hook: vrannemstein_hooks.bulkImageSourceUrl() */
  if ($hooks.bulkImageSourceUrl && $hooks.bulkImageSourceUrl instanceof Function)
    source_url = $hooks.bulkImageSourceUrl(source_url, src);

  const modal = document.createElement('dialog');
  modal.className = 'image-darkroom-modal';
  modal.id = 'image-darkroom';
  modal.style = 'display: block; position: fixed; inset: 0; width: auto; height: auto; padding: calc(32px + 20px + 20px) calc(160px + 20px); overflow: hidden; overflow-y: scroll; z-index: 9999; border: 2px solid transparent; color: #fff; background: rgb(47.85, 47.85, 47.85);';

  const btn0 = document.createElement('button');
  btn0.type = 'button';
  btn0.className = 'button button-primary button-compact close';
  btn0.style = 'position: fixed; inset: calc(32px + 20px) 32px auto auto; --wp-admin-theme-color: rgb(12.15, 12.15, 12.15);';
  btn0.onclick = close;
  btn0.innerText = 'close';

  const btn1 = document.createElement('button');
  btn1.type = 'button';
  btn1.className = 'button button-primary button-small copyresult';
  btn1.style = 'position: fixed; inset: auto 32px calc(32px + 20px + 20px) auto; text-transform: uppercase;';
  btn1.onclick = copyResult;
  btn1.innerText = 'copy result';

  const wrap = document.createElement('div');
  wrap.className = 'wrap';
  wrap.style = 'margin: 0; padding: 10px 0 25px;';

  const label = document.createElement('label');
  label.style = 'margin-top: 10px;';
  label.innerText = 'Result: ';
  const textarea = document.createElement('textarea');
  textarea.id = 'result';
  textarea.style = 'display: block; width: 95%; margin-top: 10px;';

  label.append(textarea);
  modal.append(btn0);
  modal.append(btn1);
  modal.append(wrap);
  modal.append(label);
  document.body.classList.add('modal-open'); 
  document.body.append(modal);

  /**
   * @param {Event} event
   */
  function close(event) {
    modal.remove();
    document.body.classList.remove('modal-open');
    this.blur();
  }

  /**
   * @param {Event} event
   */
  function copyResult(event) {
    navigator.clipboard.writeText(textarea.value);
    this.blur();
  }

  /**
   * @requires vrannemstein
   * @param {Event} event
   */
  function change(event) {
    if (! this.value)
      return;

    const input = this;
    const Q = input.value;
    const {size, source_url, dest_url, subsample_mode} = input.dataset;
    input.disabled = true;

    const $config = window.vrannemstein_config;
    const $hooks = window.vrannemstein_hooks || {};
    const image_sizes = {...$config.image_sizes};
    const imageWriteOpts = $hooks.imageWriteOpts;

    $config.image_sizes = {};
    $config.image_sizes[size] = image_sizes[size];
    $hooks.imageWriteOpts = (opts, writeOpts, sizes, source_url, extname) => {
      opts.Q = parseInt(Q);
      opts.subsample_mode = parseInt(subsample_mode);

      console.info('writeOpts', 'darkroom', opts);

      return opts;
    };

    result(size, Q);

    vrannemstein([{source_url, dest_url}])
    .then((imgs) => {
      if (imgs.length !== 1)
        return Promise.reject();

      const {blob, mime: type} = imgs[0].thumbs[size];
      const data = new Blob([blob], {type});
      const obj = URL.createObjectURL(data);

      const row = input.closest('.row');
      const anchor = row.querySelector('.image');
      const img = anchor.querySelector('img');
      const info = row.querySelector('.info');

      const src = anchor.href = img.src = obj;

      fileinfo(info);

      const x = document.createElement('img');
      x.onload = function() {
        fileinfo('imagesize', ({width, height} = this), info);
      };
      x.onabort = x.onerror = x.ontimeout = function() {
        console.error(`Image Load Error: ${this.src}`);
      };
      x.src = src;

      fileinfo('filename', src, info);
      fileinfo('filetype', type, info);
      fileinfo('filesize', data.size, info);

      return Promise.resolve();
    })
    .catch(err => {
      console.error(err);
      return Promise.reject();
    })
    .finally(() => {
      $config.image_sizes = image_sizes;
      if (imageWriteOpts)
        $hooks.imageWriteOpts = imageWriteOpts;
      input.disabled = false;
    });
  }

  /**
   * @inner
   */
  function body() {
    for (const size of sizes) {
      const {width: mw, height: mh, crop} = image_sizes[size];
      const filename = source_url.replace(/.+\/([^/]+)/, '$1');
      const extname = filename.replace(/.+(\.[^\.]+)$/, '$1');
      const src = /[0-9]{4}\/[0-9]{2}/.test(source_url) ? source_url.replace(/.+\/([0-9]{4})\/([0-9]{2})\/(.+)\.([^/]+)$/, '$1/$2/$3.$4') : source_url.replace(/.+\/(.+)\.([^/]+)$/, '$1.$2');
      const name = `${size} ${mw}x${mh}`;

      let width = mw;
      if (size === 'full')
        width = 2048 / 2;

      let save_opts;
      if (/\.(jpg|jpeg|jpe)/.test(extname))
        save_opts = 'jpegsave';
      else if (/\.webp/.test(extname))
        save_opts = 'webpsave';
      else if (/\.avif/.test(extname))
        save_opts = 'avifsave';

      let Q = $config[save_opts].Q ?? 1;
      let subsample_mode = $config[save_opts].subsample_mode ?? 0; // 0 = auto, 1 = yuv420, 2 = yuv444

      Q = quality_defaults[size] ?? Q;
      if (size === 'full')
        subsample_mode = 2;

      if (! quality_values[src])
        quality_values[src] = {};
      quality_values[src][size] = Q;

      const row = document.createElement('div');
      row.className = 'row';
      row.id = size;
      row.style = 'margin: 0 0 72px;';

      const title = document.createElement('h5');
      title.innerText = name;

      const grid = document.createElement('div');
      grid.className = 'grid';
      grid.style = 'display: grid; grid: auto / calc(100% - (350px + 160px)) 350px; gap: 0 82px; align-items: center;';

      const content = document.createElement('div');
      content.className = 'content';
      const anchor = document.createElement('a');
      anchor.className = 'image';
      anchor.href = '#';
      anchor.target = '_blank';
      anchor.style = 'display: block; overflow: hidden; overflow-x: scroll;';
      const img = document.createElement('img');
      img.alt = name;
      img.style = `width: ${width}px; height: auto;`;

      const side = document.createElement('side');
      side.className = 'side';
      side.style = 'margin-top: 64px;'
      const label = document.createElement('label');
      label.innerText = 'Q = ';
      label.title = 'Quality';
      const input = document.createElement('input');
      input.type = 'number';
      input.className = 'quality';
      input.name = 'Q[]';
      input.min = 1;
      input.max = 100;
      input.placeholder = input.value = Q;
      input.onchange = change;
      input.dataset.size = size;
      input.dataset.source_url = source_url;
      input.dataset.dest_url = img_url;
      input.dataset.subsample_mode = subsample_mode;
      const info = document.createElement('div');
      info.className = 'info';
      info.style = 'margin-top: 32px;'

      anchor.append(img);
      content.append(anchor);
      label.append(input);

      if (/jpg|jpeg|jpe/.test(extname))
        side.append(label);
      side.append(info);

      grid.append(content);
      grid.append(side);
      row.append(title);
      row.append(grid);
      wrap.append(row);

      image(size, anchor, img, info, input);
    }

    result();
  }

  /**
   * @param {string} size
   * @param {HTMLElement} anchor
   * @param {HTMLElement} img
   * @param {HTMLElement} info
   * @param {HTMLElement} input
   */
  function image(size, anchor, img, info, input) {
    let {width: mw, height: mh, crop} = image_sizes[size];

    const x = document.createElement('img');
    x.onload = function() {
      const {width: src_w, height: src_h} = this;
      if (size === 'full') {
        mw = src_w;
        mh = src_h;
      }
      const {dst_w, dst_h} = imgConstraints(src_w, src_h, crop, mw, mh);

      if (dst_w > src_w || dst_h > src_h) {
        img.alt = '';
        input.disabled = true;
        input.parentElement && (input.parentElement.style = 'display: none;');

        return;
      } else {
        anchor.href = img.src = this.src.replace(/(\.[^\.]+)$/, size !== 'full' ? `-${dst_w}x${dst_h}$1` : '$1');
      }

      const src = img.src;

      fileinfo('filename', src, info);

      const x = document.createElement('img');
      x.onload = function() {
        fileinfo('imagesize', ({width, height} = this), info);
      };
      x.onabort = x.onerror = x.ontimeout = function() {
        console.error(`Image Load Error: ${this.src}`);
      };
      x.src = src;

      fetch(src, {mode: 'same-origin'})
      .then((response) => {
        if (! response.ok)
          throw new Error(`HTTP Error status: ${response.status}`);

        const contentType = response.headers.get('content-type');
        const contentLength = response.headers.get('content-length');

        fileinfo('filetype', contentType, info);
        fileinfo('filesize', contentLength, info);
      })
      .catch(err => console.error(err));
    };
    x.onabort = x.onerror = x.ontimeout = function() {
      console.error(`Image Load Error: ${this.src}`);
    };
    x.src = img_url;
  };

  /**
   * @param {string|HTMLElement} arg0 id or info
   * @param {HTMLElement} [anchor]
   * @param {HTMLElement} [img]
   * @param {HTMLElement} [info]
   */
  function fileinfo(arg0, data, info) {
    switch (arg0) {
      case 'imagesize':
      case 'filename':
      case 'filetype':
      case 'filesize':
      break;
      default:
        if (arg0 && arg0.nodeType) {
          while (arg0.firstChild)
            arg0.firstChild.remove();
        }
        return;
    }

    const id = arg0;
    const text = document.createElement('span');
    text.className = `info-${id}`;
    text.style = 'display: block;';

    if (id === 'imagesize') {
      const {width, height} = data;
      text.innerText = `Image size: ${width} x ${height} px`;
      info.prepend(text);
      return;
    } else if (id === 'filename') {
      const filename = data.replace(/.+\/([^/]+)/, /^blob:/.test(data) ? 'blob:$1' : '$1');
      text.style = 'display: block; width: calc(350px + 82px + 20px); overflow: hidden; white-space: nowrap; text-overflow: ellipsis;'
      text.innerText = `Filename: ${filename}`;
      text.title = filename;
    } else if (id === 'filetype') {
      const filetype = data.replace('image/', '').toUpperCase();
      text.innerText = `File type: ${filetype}`;
    } else if (id === 'filesize') {
      const filesize = (data / 1e3).toFixed(3);
      text.innerText = `File size: ${filesize} bytes`;
    }
    info.append(text);
  }

  /**
   * @param {string} [size]
   * @param {number} [Q=1]
   */
  function result(size, Q) {
    if (size && Object.keys(quality_values).length === 1) {
      const src = Object.keys(quality_values)[0];
      quality_values[src][size] = Q ?? 1;
    }

    textarea.value = JSON.stringify(quality_values).replace(/(,|:)/g, '$1 ');
  }

  /**
   * @param {number} src_w
   * @param {number} src_h
   * @param {boolean} crop
   * @param {number} mw
   * @param {number} mh
   */
  function imgConstraints(src_w, src_h, crop, mw, mh) {
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

    return {dst_w, dst_h, ratio};
  }

  body();
}


/**
 * @inner
 */
function image_darkroom() {
  const table = document.querySelector('.wp-list-table.media');

  if (! table)
    return;

  const rows = table.querySelectorAll('tbody tr');
  rows.forEach((row) => {
    const actions = row.querySelector('.row-actions');
    const action = document.createElement('span');
    action.className = 'image-darkroom';
	  const btn = document.createElement('button');
	  btn.type = 'button';
	  btn.className = 'button-link';
	  btn.onclick = function(event) { event.preventDefault(); image_darkroom_modal.call(this, event); };
	  btn.innerText = 'Darkroom';

    action.append(btn);
    actions.lastElementChild.append(' | ');
	  actions.lastElementChild.after(action);
  });
}

image_darkroom();
</script>
