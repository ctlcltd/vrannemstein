<?php
/**
 * Vrannemstein bulk resizer javascript
 *
 * @package vrannemstein
 * @version 0.1.7
 * @author Leonardo Laureti
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || die();

?><script id="vrannemstein-bulk-resizer-script">
/**
 * @function anonymous function
 * @description "vrannemstein-bulk-resizer-script" init function
 *
 * @requires wp
 * @requires jQuery
 * @requires vrannemstein
 * @requires wp.media
 * @requires wp.apiFetch
 * @requires wp.i18n
 * @param {wp} wp
 * @param {jQuery} jQuery
 */
(function(wp, jQuery) {
  const debug = true;

  const NOTICE_DISMISS_DELAY = 5e3;
  /**
   * @param {string} type
   * @param {string} message
   * @param {boolean} dismiss
   * @ignore
   */
  const notice = (type, message, dismiss) => {
    const element = document.querySelector('.wp-header-end') || document.querySelector('.wrap h1, .wrap h2');
    const notice = document.createElement('div');
    const p = document.createElement('p');
    notice.className = `${type} notice`;
    p.innerText = message;
    notice.append(p);
    element.after(notice);

    if (dismiss) {
      setTimeout(() => {
        notice.remove();
      }, NOTICE_DISMISS_DELAY);
    }
  };
  /**
   * @param {mixed} err
   * @ignore
   */
  const errorNotice = (err) => {
    console.error(err);

    const wp_i18n = wp.i18n;
    const message = wp_i18n.__('An unknown error occurred during creation. Please try again.');
    notice('error', message);
  };
  /**
   * @param {boolean} dismiss
   * @ignore
   */
  const successNotice = (dismiss) => {
    const wp_i18n = wp.i18n;
    const message = wp_i18n.__('Done');
    notice('updated', message, dismiss);
  };

  /**
   * @global
   * @constructor
   * @see wp.media
   * @see wp.apiFetch
   */
  function vrannemsteinBulkResizer() {
    const mode = wp.media ? 1 : 0; // (0 list, 1 grid)

    /**
     * @see vrannemstein
     * @see wp.apiFetch
     * @param {Array} items
     * @return {Promise}
     */
    function createImageSubsizes(items) {
      debug && console.log('createImageSubsizes', {items});

      const refs = {};
      const images = [];
      for (const item of items) {
        const {attachmentId, attachment} = item;
        const {source_url, dest_url} = attachment;
        refs[source_url] = items.indexOf(item);
        images.push({source_url, dest_url});
      }
      const data = [];
      return vrannemstein(images, true)
      .then((imgs) => {
        for (const img of imgs) {
          try {
            const body = new FormData();
            const i = refs[img.source_url];
            const {attachmentId, attachment} = items[i];
            const sizes = {};
            for (const size in img.thumbs) {
              const {blob, type, filename} = img.thumbs[size];
              body.append(size, new Blob([blob], {type}), filename);
              sizes[size] = img.thumbs[size].sizes;
            }
            body.append('data', JSON.stringify(sizes));
            data.push({body, attachmentId, attachment});
          } catch (err) {
            return err; //forward
          }
        }
      })
      .then(() => {
        const p = [];
        for (const {body, attachmentId} of data) {
          p.push(
            wp.apiFetch({
              path: `/vrannemstein/v2/attachment/${attachmentId}/post-process?action=image-subsizes`,
              method: 'POST',
              body,
              parse: false
            })
          );
        }
        return Promise.all(p);
      });
    }

    /**
     * @static
     * @param {string} src
     * @return {string}
     */
    function bulkImageSourceUrl(src) {
      const $hooks = window.vrannemstein_hooks || {};
      const source_url = src.replace(/-\d+x\d+(\.[^\.]+)$/, '$1');

      /**
       * @function external:vrannemstein_hooks.bulkImageSourceUrl
       * @param {string} source_url
       * @param {string} src
       * @return {string}
       */
      if ($hooks.bulkImageSourceUrl && $hooks.bulkImageSourceUrl instanceof Function)
        return $hooks.bulkImageSourceUrl(source_url, src);

      return source_url;
    }
    $fn.bulkImageSourceUrl = bulkImageSourceUrl;

    /**
     * @static
     * @param {string} dst
     * @return {string}
     */
    function bulkImageDestUrl(dst) {
      const $hooks = window.vrannemstein_hooks || {};
      const dest_url = dst.replace(/-\d+x\d+(\.[^\.]+)$/, '$1');

      /**
       * @function external:vrannemstein_hooks.bulkImageDestUrl
       * @param {string} dest_url
       * @param {string} dst
       * @return {string}
       */
      if ($hooks.bulkImageDestUrl && $hooks.bulkImageDestUrl instanceof Function)
        return $hooks.bulkImageDestUrl(dest_url, dst);

      return dest_url;
    }
    $fn.bulkImageDestUrl = bulkImageDestUrl;

    /**
     * @inner
     */
    function bulkMediaList() {
      debug && console.log('bulkMediaList');

      const form = document.querySelector('form#posts-filter');
      function submit(event) {
        const bulk = this.querySelector('select[name="action"]');

        if (bulk.value === 'bulk-resizer') {
          event.preventDefault();

          if (this.querySelector('input[name="media[]"]:checked')) {
            const data = [];
            const elements = this.querySelectorAll('input[name="media[]"]:checked');
            elements.forEach((element) => {
              const row = element.closest('tr');
              const img = row.querySelector('img');
              if (img) {
                const attachmentId = element.value;
                const src = img.src;
                const source_url = bulkImageSourceUrl(src);
                const dest_url = bulkImageDestUrl(src);
                data.push({attachmentId, attachment: {id: attachmentId, source_url, dest_url}, batch: true});
              }
            });
            createImageSubsizes(data)
            .then(() => {
              elements.forEach((element) => {
                element.checked = false;
              });
              successNotice(true);
            })
            .catch(err => errorNotice(err));
          }
        }
      }
      form && form.addEventListener('submit', submit);
    }

    /**
     * @see wp.media
     */
    function bulkMediaGrid() {
      debug && console.log('bulkMediaGrid');

      const frame = wp.media.frames.browse;
      const view = wp.media.view;
      const controller = frame.browserView.controller;
      const toolbar = frame.browserView.toolbar;
      const l10n = view.l10n;
      const Button = view.Button;
      const BulkResizerButton = Button.extend({
        initialize: function() {
          Button.prototype.initialize.apply(this, arguments);
          this.controller.on('selection:toggle', this.toggleDisabled, this);
          this.controller.on('select:activate', this.toggleDisabled, this);
        },
        toggleDisabled: function() {
          this.model.set('disabled', ! this.controller.state().get('selection').length);
        },
        render: function() {
          Button.prototype.render.apply(this, arguments);
          this.$el.addClass('bulk-resizer-button hidden');
          if (! this.controller.isModeActive('select')) {
            this.$el.addClass('hidden');
          } else {
            this.$el.removeClass('hidden');
          }
          this.toggleDisabled();
          return this;
        }
      });
      const toggler = () => {
        if (controller.isModeActive('select'))
          toolbar.$('.bulk-resizer-button').removeClass('hidden');
        else
          toolbar.$('.bulk-resizer-button').addClass('hidden');
      };
      const button = new BulkResizerButton({
        filters: controller.state().get('filterable'),
        style: 'primary',
        disabled: true,
        text: l10n.update,
        controller,
        priority: -75,
        click: function() {
          const items = [];
          const selection = controller.state().get('selection');
          if (! selection.length)
            return;
          selection.each(function(model) {
            items.push(model);
          });
          if (items.length) {
            const data = [];
            for (const item of items) {
              const attachmentId = item.id;
              const src = item.attributes.url;
              const source_url = bulkImageSourceUrl(src);
              const dest_url = bulkImageDestUrl(src);
              data.push({attachmentId, attachment: {id: attachmentId, source_url, dest_url}, batch: true});
            }
            createImageSubsizes(data)
            .then(() => {
              controller.trigger('selection:action:done');
              successNotice(true);
            })
            .catch(err => errorNotice(err));
          } else {
            controller.trigger('selection:action:done');
          }
        }
      });
      view.BulkResizerButton = BulkResizerButton;

      if (controller.isModeActive('grid'))
        toolbar.secondary.views.add(button, {at: -2});

      controller.on('select:activate select:deactivate', toggler, frame);
    }

    if (wp.apiFetch)
      mode ? bulkMediaGrid() : bulkMediaList();
  }
  const $fn = window.vrannemsteinBulkResizer = vrannemsteinBulkResizer;

  wp = wp || {};
  jQuery(function() {
    wp.media && wp.media.frame && wp.media.frames.browse && wp.media.frames.browse.browserView.collection.once('attachments:received', $fn);
  });
  wp.media || $fn();
})(wp, jQuery);
</script>

