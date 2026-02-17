<?php
/**
 * wp.apiFetch Vrannemstein middleware javascript
 *
 * @package vrannemstein
 * @author Leonardo Laureti
 * @license GPL-2.0-or-later
 *
 * @api
 * @todo
 */

defined( 'ABSPATH' ) || die();

?><script id="vrannemstein-api-middleware-script">
const vrannemsteinThumbnailerMiddleware = (options, next) => {
  const debug = 0; // todo

  const isCreateMethod = !!options.method && options.method === 'POST';
  const isMediaEndpoint = !!options.path && options.path.indexOf('/wp/v2/media') !== -1 || !!options.url && options.url.indexOf('/wp/v2/media') !== -1;

  if (! (isMediaEndpoint && isCreateMethod)) {
    return next(options);
  }

  console.log('vrannemsteinThumbnailerMiddleware', {options, next});

  let $post = null;
  let attempts = 0;
  const wp_i18n = wp.i18n;
  const messages = {
    'ERR_POST': {code: 'post_process', message: wp_i18n.__('Media upload failed. If this is a photo or a large image, please scale it down and try again.')},
    'ERR_JSON': {code: 'invalid_json', message: wp_i18n.__('The response is not a valid JSON response.')}
  };

  const postProcess = (attachmentId, attachment) => {
    debug && console.log('vrannemsteinThumbnailerMiddleware', 'postProcess', {attachmentId, attachment});

    attempts++;
    const body = new FormData();
    const p = new Promise((resolve, reject) => {
      vrannemstein([attachment.source_url])
      .then((imgs) => {
        const img = imgs[0];
        const data = {};
        if (img.source_url != attachment.source_url) {
          console.warn('URL mismatch.');
          return reject();
        }
        try {
          for (const size in img.thumbs) {
            const {blob, type, filename} = img.thumbs[size];
            body.append(size, new Blob([blob], {type}), filename);
            data[size] = img.thumbs[size].data;
          }
          body.append('data', JSON.stringify(data));
          resolve();
        } catch (err) {
          console.warn('Internal Error.', err);
          reject();
        }
      })
      .catch(reject);
    });
    return new Promise((resolve, reject) => {
      p.then(() => {
        debug && console.log('vrannemsteinThumbnailerMiddleware', 'postProcess', 'p.then');

        next({
          path: `/vrannemstein/v2/attachment/${attachmentId}/post-process?action=image-subsizes`,
          method: 'POST',
          body,
          parse: true
        })
        .then(resolve)
        .catch((response) => {
          debug && console.log('vrannemsteinThumbnailerMiddleware', 'postProcess', 'p.then', 'next.catch', {response});

          if (! attempts) {
            postProcess(attachmentId, post);
            return resolve(response);
          }
          reject(response);
        });
      }).catch((response) => {
        debug && console.log('vrannemsteinThumbnailerMiddleware', 'postProcess', 'p.catch', {response});

        if (! attempts) {
          postProcess(attachmentId, attachment);
          return resolve(response);
        }
        reject(response);
      });
    });
  };

  return next({...options, parse: false})
  .then((response) => {
    debug && console.log('vrannemsteinThumbnailerMiddleware', 'next.then', {response});

    return new Promise((resolve, reject) => {
      try {
        response.json().then((attachment) => {
          debug && console.log('vrannemsteinThumbnailerMiddleware', 'next.then', 'json.then', {attachment});

          if (attachment.media_type != 'image')
            return Promise.resolve(next);

          const attachmentId = attachment.id;
          $post = attachment;

          postProcess(attachmentId, attachment)
          .then(resolve)
          .catch((response) => {
            if (options.parse)
              response.json().then(reject);
            else
              reject(response);
          });
        });
      } catch {
        reject(messages.ERR_JSON);
      }
    });
  })
  .catch((response) => {
    debug && console.log('vrannemsteinThumbnailerMiddleware', 'next.catch', {response});

    return new Promise((resolve, reject) => {
      try {
        //todo
        const attachmentId = response.headers.get('x-wp-upload-attachment-id');

        if (attachmentId && attachmentId != $post.id)
          return reject(messages.ERR_POST);

        if (response.status >= 500 && response.status < 600) {
          postProcess(attachmentId, $post)
          .catch((response) => {
            if (options.parse)
              response.json().then(reject);
            else
              reject(messages.ERR_POST);
          });
        }
      } catch {
        reject(messages.ERR_JSON);
      }
    });
  });
}

wp && wp.apiFetch && wp.apiFetch.use(vrannemsteinThumbnailerMiddleware);
</script>

