<?php
/**
 * Vrannemstein bulk actions javascript
 *
 * @package vrannemstein
 * @author Leonardo Laureti
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || die();

?><script id="vrannemstein-bulk-actions-script">
(function(wp, jQuery) {
  const debug = true;

  /**
   * @private
   * @constructor
   * @external 'wp.media'
   * @external 'wp.apiFetch'
   */
  function vrannemsteinBulkThumbnails() {
    const mode = wp.media ? 1 : 0; // (0 list, 1 grid)

    /**
     * @private
     * @external vrannemstein
     * @external 'wp.apiFetch'
     * @param {array} items
     * @return {Promise}
     */
    function createImageSubsizes(items) {
      debug && console.log('createImageSubsizes', {items});

      const refs = {};
      const images = [];
      for (const item of items) {
        const {attachmentId, attachment} = item;
        const source_url = attachment.source_url;
        refs[source_url] = items.indexOf(item);
        images.push(source_url);
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
              sizes[size] = img.thumbs[size].data;
            }
            body.append('data', JSON.stringify(sizes));
            data.push({body, attachmentId, attachment});
          } catch (err) {
            console.error(err);
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
     * @private
     * @param {string} src
     * @return {string}
     */
    function imageSourceUrl(src) {
      return src.replace(/-\d+x\d+(\.[^\.]+)$/, '$1');
    }

    /**
     * @private
     */
    function bulkMediaList() {
      debug && console.log('bulkMediaList');

      const form = document.querySelector('form#posts-filter');
      function submit(event) {
        const bulk = this.querySelector('select[name="action"]');

        if (bulk.value === 'bulk-thumbnails') {
          event.preventDefault();

          if (this.querySelector('input[name="media[]"]:checked')) {
            const data = [];
            const elements = this.querySelectorAll('input[name="media[]"]:checked');
            elements.forEach((element) => {
              const row = element.closest('tr');
              const img = row.querySelector('img');
              if (img) {
                const attachmentId = element.value;
                const source_url = imageSourceUrl(img.src);
                data.push({attachmentId, attachment: {id: attachmentId, source_url}, batch: true});
              }
            });
            createImageSubsizes(data)
            .then(() => {
              elements.forEach((element) => {
                element.checked = false;
              });
            })
            .catch(err => console.error(err));
          }
        }
      }
      form && form.addEventListener('submit', submit);
    }

    /**
     * @private
     * @external 'wp.media'
     */
    function bulkMediaGrid() {
      debug && console.log('bulkMediaGrid');

      const frame = wp.media.frames.browse;
      const view = wp.media.view;
      const controller = frame.browserView.controller;
      const toolbar = frame.browserView.toolbar;
      const l10n = view.l10n;
      const Button = view.Button;
      const BulkThumbnailsButton = Button.extend({
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
          this.$el.addClass('bulk-thumbnails-button hidden');
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
          toolbar.$('.bulk-thumbnails-button').removeClass('hidden');
        else
          toolbar.$('.bulk-thumbnails-button').addClass('hidden');
      }
      const button = new BulkThumbnailsButton({
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
              const source_url = imageSourceUrl(item.attributes.url);
              data.push({attachmentId, attachment: {id: attachmentId, source_url}, batch: true});
            }
            createImageSubsizes(data)
            .then(() => controller.trigger('selection:action:done'))
            .catch(err => console.error(err));
          } else {
            controller.trigger('selection:action:done');
          }
        }
      });
      view.BulkThumbnailsButton = BulkThumbnailsButton;

      if (controller.isModeActive('grid'))
        toolbar.secondary.views.add(button, {at: -2});

      controller.on('select:activate select:deactivate', toggler, frame);
    }

    if (wp.apiFetch)
      mode ? bulkMediaGrid() : bulkMediaList();
  }
  const $fn = vrannemsteinBulkThumbnails;

  wp = wp || {};
  jQuery(function() {
    wp.media && wp.media.frame && wp.media.frames.browse && wp.media.frames.browse.browserView.collection.once('attachments:received', $fn);
  });
  wp.media || $fn();
})(wp, jQuery);
</script>

