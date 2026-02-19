<?php
/**
 * Vrannemstein post processing mixin javascript
 *
 *   postProcess
 *   vrannemsteinThumbnailerMiddleware (wp.apiFetch)
 *   pluploadProcessing (wp-plupload)
 *   pluploadTryout
 *   blockEditorMediaUpload (editor.MediaUpload)
 *   mceEditorMediaUpload (tinymce#WP_Medialib)
 * 
 * @package vrannemstein
 * @author Leonardo Laureti
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || die();

?><script id="vrannemstein-postprocess-script">
(function(wp, jQuery) {
  const debug = true;

  /**
   * @private
   * @mixin
   */
  const vrannemsteinPostProcessing = Object.freeze({
    /**
     * @param {object} options
     * @param {'wp.apiFetch'} next
     * @param {boolean} batch
     * @return {Promise}
     */
    getPostProcess: (options, next, batch) => {
      let attempts = 0;
      const postProcess = (attachmentId, attachment) => {
        attempts++;
        const body = new FormData();
        const p = new Promise((resolve, reject) => {
          vrannemstein([attachment.source_url], batch)
          .then((imgs) => {
            try {
              const img = imgs[0];
              if (img.source_url != attachment.source_url) {
                return console.error('URL mismatch.');
              }
              const sizes = {};
              for (const size in img.thumbs) {
                const {blob, type, filename} = img.thumbs[size];
                body.append(size, new Blob([blob], {type}), filename);
                sizes[size] = img.thumbs[size].data;
              }
              body.append('data', JSON.stringify(sizes));
              resolve();
            } catch (err) {
              return console.error(err);
            }
          })
          .catch(reject);
        });
        return new Promise((resolve, reject) => {
          p.then(() => {
            debug && console.log('postProcess', 'request', {attachmentId, attachment});

            next({
              path: `/vrannemstein/v2/attachment/${attachmentId}/post-process?action=image-subsizes`,
              method: 'POST',
              body,
              parse: false
            })
            .then((response) => response.json().then(resolve).catch(reject))
            .catch(reject);
          }).catch((response) => {
            console.warn('postProcess', 'error', {response});

            if (! attempts) {
              postProcess(attachmentId, attachment);
              return resolve(response);
            }
            reject(response);
          });
        });
      };
      return postProcess;
    },

    /**
     * @lends vrannemsteinThumbnailerMiddleware
     * @param {object} options
     * @param {'wp.apiFetch'} next
     * @return {Promise|void}
     */
    ThumbnailerMiddleware: (options, next) => {
      const isCreateMethod = !!options.method && options.method === 'POST';
      const isMediaEndpoint = !!options.path && options.path.indexOf('/wp/v2/media') !== -1 || !!options.url && options.url.indexOf('/wp/v2/media') !== -1;

      if (! (isMediaEndpoint && isCreateMethod)) {
        return next(options);
      }

      debug && console.log('ThumbnailerMiddleware', {options});

      const wp_i18n = wp.i18n;
      const messages = {
        'ERR_JSON': {code: 'invalid_json', message: wp_i18n.__('The response is not a valid JSON response.')}
      };
      const postProcess = $fn.getPostProcess(options, next);
      let $post = null;

      return next({...options, parse: false})
      .then((response) => {
        debug && console.log('afterMediaUpload', {response});

        return new Promise((resolve, reject) => {
          try {
            response.json().then((attachment) => {
              debug && console.log('afterMediaUpload', {attachment});

              if (attachment.media_type != 'image')
                return Promise.resolve(next);

              const attachmentId = attachment.id;
              $post = attachment;
              postProcess(attachmentId, attachment)
              .then(resolve)
              .catch(reject);
            });
          } catch {
            reject(messages.ERR_JSON);
          }
        });
      })
      .catch((response) => {
        console.warn('afterMediaUpload', 'failed', {response});

        return new Promise((resolve, reject) => {
          try {
            const attachmentId = response.headers.get('x-wp-upload-attachment-id');

            if (attachmentId && attachmentId != $post.id)
              return reject(messages.ERR_POST);

            if (response.status >= 500 && response.status < 600) {
              postProcess(attachmentId, $post)
              .then(resolve)
              .catch(reject);
            }
          } catch {
            reject(messages.ERR_JSON);
          }
        });
      });
    },

    /**
     * @external 'wp.apiFetch'
     * @param {object} uploader
     */
    puploadProcessing: (uploader) => {
      debug && console.log('puploadProcessing');

      const data = [];
      const complete = (up, files) => {
        debug && console.log('pupload.UploadComplete', {data});

        if (data.length && files.length) {
          const p = [];
          for (const item of data) {
            const {filename, attachmentId, attachment} = item;
            if (attachment) {
              p.push(Promise.resolve(attachment));
            } else if (attachmentId) {
              p.push(
                wp.apiFetch({
                  path: `/wp/v2/media/${attachmentId}`,
                  method: 'GET',
                  parse: true
                })
              );
            }
          }
          const posts = [];
          Promise.all(p)
          .then((response) => {
            for (const attachment of response) {
              posts.push({attachmentId: attachment.id, attachment});
            }
          })
          .then(() => {
            const postProcess = $fn.getPostProcess(null, wp.apiFetch, true);
            const p = [];
            for (const {attachmentId, attachment} of posts) {
              p.push(postProcess(attachmentId, attachment));
            }
            return Promise.all(p);
          })
          .catch(err => console.error(err))
        }
      };
      const uploaded = (up, file, response) => {
        debug && console.log('pupload.FileUploaded', {response});

        if (response.status < 400 && file && /^image\//.test(file.type)) {
          try {
            const body = response.response;
            let attachmentId, attachment;
            if (body && isNaN(body)) {
              try {
                const response = JSON.parse(body);
                if (response.success === true) {
                  attachmentId = response.data.id;
                  attachment = response.data;
                  attachment.source_url = attachment.url;
                } else {
                  return;
                }
              } catch (err) {
                if (err instanceof SyntaxError)
                  attachmentId = body.replace(/^<pre>(\d+)<\/pre>$/, '$1');
                else
                  throw err;
              }
            } else if (body) {
              attachmentId = parseInt(body);
            } else {
              return;
            }
            data.push({filename: file.name, attachmentId, attachment});
          } catch(err) {
            console.warn('Malformed data.', err);
          }
        }
      };
      const init = () => {
        uploader.bind('FileUploaded', uploaded);
        uploader.bind('UploadComplete', complete);
      };
      const destroy = () => {
        uploader.unbind('FileUploaded', uploaded);
        uploader.unbind('UploadComplete', complete);
      };
      uploader.bind('Init', init);
      uploader.bind('Destroy', destroy);
    },

    /**
     * @external 'wp.media'
     * @external 'wp.apiFetch'
     * @param {object|undefined} uploader
     */
    pluploadTryout: (uploader) => {
      debug && console.log('puploadTryout');

      try {
        uploader = uploader ?? wp.media.frame.uploader.uploader.uploader;
        if (wp.apiFetch)
          $fn.puploadProcessing(uploader);
      } catch (err) {
        console.warn(err);
      }
    },

    /**
     * @see wp.hooks.filters.editor.MediaUpload
     *
     * @param {ReactComponent} component
     * @return {ReactComponent}
     */
    blockEditorMediaUpload: (component) => {
      debug && console.log('blockEditorMediaUpload');

      class $MediaUpload extends component {
        onOpen() {
          super.onOpen();
          $fn.pluploadTryout();
        }
      }
      return $MediaUpload;
    },

    /**
     * @external tinymce
     */
    mceEditorMediaUpload: () => {
      debug && console.log('mceEditorMediaUpload');

      const execCommand = (event) => {
        if (event.command === 'WP_Medialib') {
          $fn.pluploadTryout();
        }
      };
      const addEditor = (event) => {
        event.editor.on('execCommand', execCommand);
      };
      const removeEditor = (event) => {
        event.editor.off('ExecCommand', execCommand);
      };
      tinymce.on('AddEditor', addEditor);
      tinymce.on('RemoveEditor', removeEditor);
    }
  });
  const $fn = vrannemsteinPostProcessing;
  const vrannemsteinThumbnailerMiddleware = $fn.ThumbnailerMiddleware;

  wp = wp || {};
  jQuery(function() {
    //todo media upload browser mode
    window.uploader && uploader_init && $fn.puploadTryout(window.uploader);
    wp.media && wp.media.frame && wp.media.frames.browse && wp.media.frame.on('uploader:ready', $fn.pluploadTryout);
  });
  wp.hooks && wp.hooks.addFilter('editor.MediaUpload', 'vrannemstein', $fn.blockEditorMediaUpload);
  window.tinymce && $fn.mceEditorMediaUpload();
  wp.apiFetch && wp.apiFetch.use(vrannemsteinThumbnailerMiddleware);
})(wp, jQuery);
</script>

