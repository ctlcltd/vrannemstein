<?php
/**
 * Vrannemstein post processing javascript
 *
 *   postProcess
 *   vrannemsteinThumbnailerMiddleware (wp.apiFetch)
 *   pluploadProcessing (wp-plupload)
 *   blockEditorMediaUpload (editor.MediaUpload)
 *   mceEditorMediaUpload (tinymce#WP_Medialib)
 * 
 * @package vrannemstein
 * @author Leonardo Laureti
 * @license GPL-2.0-or-later
 *
 * @api
 * @todo
 */

defined( 'ABSPATH' ) || die();

?><script id="vrannemstein-postprocess-script">
(function(wp, jQuery) {
  const debug = true;

  const getPostProcess = (options, next) => {
    let attempts = 0;
    const postProcess = (attachmentId, attachment) => {
      attempts++;
      const body = new FormData();
      const p = new Promise((resolve, reject) => {
        vrannemstein([attachment.source_url])
        .then((imgs) => {
          const img = imgs[0];
          const data = {};
          if (img.source_url != attachment.source_url) {
            throw 'URL mismatch.';
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
            throw err;
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
        }).catch((err) => {
          console.warn('postProcess', 'error', {err});

          if (! attempts) {
            postProcess(attachmentId, attachment);
            return resolve(response);
          }
          reject(response);
        });
      });
    };
    return postProcess;
  };

  const vrannemsteinThumbnailerMiddleware = (options, next) => {
    const isCreateMethod = !!options.method && options.method === 'POST';
    const isMediaEndpoint = !!options.path && options.path.indexOf('/wp/v2/media') !== -1 || !!options.url && options.url.indexOf('/wp/v2/media') !== -1;

    if (! (isMediaEndpoint && isCreateMethod)) {
      return next(options);
    }

    debug && console.log('vrannemsteinThumbnailerMiddleware', {options});

    const wp_i18n = wp.i18n;
    const messages = {
      'ERR_JSON': {code: 'invalid_json', message: wp_i18n.__('The response is not a valid JSON response.')}
    };
    const postProcess = getPostProcess(options, next);
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
  }

  function puploadProcessing(uploader) {
    debug && console.log('puploadProcessing');

    const data = [];
    const complete = (up, files) => {
      debug && console.log('pupload.UploadComplete', {files});

      if (data.length && files.length) {
        //todo
        debug && console.log(data);
      }
    };
    const uploaded = (up, file, response) => {
      debug && console.log('pupload.FileUploaded', {file, response});

      if (response.status < 400 && file && /^image\//.test(file.type))
        data.push({filename: file.name, attachmentId: parseInt(response.response)});
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
  }

  function blockEditorMediaUpload(component) {
    debug && console.log('blockEditorMediaUpload');

    class $MediaUpload extends component {
      onOpen() {
        super.onOpen();
        const uploader = wp.media.frame.uploader.uploader.uploader;
        puploadProcessing(uploader);
      }
    }
    return $MediaUpload;
  }

  function mceEditorMediaUpload() {
    debug && console.log('mceEditorMediaUpload');

    const execCommand = (event) => {
      if (event.command === 'WP_Medialib') {
        const uploader = wp.media.frame.uploader.uploader.uploader;
        puploadProcessing(uploader);
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

  jQuery(function() {
    window.uploader && uploader_init && puploadProcessing(window.uploader);
  });
  wp.hooks && wp.hooks.addFilter('editor.MediaUpload', 'vrannemstein', blockEditorMediaUpload);
  tinymce && mceEditorMediaUpload();

  wp.apiFetch && wp.apiFetch.use(vrannemsteinThumbnailerMiddleware);
})(wp, jQuery);
</script>

