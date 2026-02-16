<?php
/**
 * Vrannemstein bulk actions javascript
 *
 * @package vrannemstein
 * @author Leonardo Laureti
 * @license GPL-2.0-or-later
 *
 * @todo
 */

defined( 'ABSPATH' ) || die();

?><script id="vrannemstein-bulk-actions-script">
function vrannemstein_bulk_thumbnails() {
  const mode = wp.media ? 1 : 0; // (0 list, 1 grid)

  //
  let iter = 0;

  function generate(data) {
    const images = [];
    let i = 0;
    for (const obj of data) {
      let source_url = mode ? obj.attributes.url : obj.src;
      source_url = source_url.replace(/-\d+x\d+/, '');

      if (iter && i++ == iter)
        break;

      images.push(source_url);
    }

    //todo testing
    vrannemstein(images).then(thumbnails => console.log(thumbnails));
  }

  function bulk_select() {
    const form = document.querySelector('form#posts-filter');

    function submit(event) {
      const bulk = this.querySelector('select[name="action"]');

      if (bulk.value === 'bulk-thumbnails') {
        event.preventDefault();

        if (this.querySelector('input[name="media[]"]:checked')) {
          const elements = [];

          this.querySelectorAll('input[name="media[]"]:checked').forEach((element) => {
            elements.push(element.closest('tr').querySelector('img'));
          });
          generate(elements);
        }
      }
    }

    form && form.addEventListener('submit', submit);
  }

  function bulk_button() {
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
      controller: controller,
      priority: -75,
      click: function() {
        var data = [];
        var selection = controller.state().get('selection');

        if (! selection.length)
          return;

        selection.each(function(model) {
          data.push(model);
        });

        if (data.length) {
          generate(data);
          controller.trigger('selection:action:done');
        } else {
          controller.trigger('selection:action:done');
        }
      }
    });
    view.BulkThumbnailsButton = BulkThumbnailsButton;

    if (controller.isModeActive('grid'))
      toolbar.secondary.views.add(button, {at: -2});

    controller.on('select:activate select:deactivate', toggler, frame);

    //
    frame.on('select:activate', () => console.log('activate'), frame);
    frame.on('select:deactivate', () => console.log('deactivate'), frame);
  }

  function test() {
    iter = 2;
    const images = [];
    const elements = document.querySelectorAll('.media img, .thumbnail img');
    generate(elements);
  }
  // test();

  mode && bulk_button() || bulk_select();
}

(function(wp, jQuery) {
  jQuery(function() {
    wp.media && wp.media.frame && wp.media.frames.browse && wp.media.frames.browse.browserView.collection.once( 'attachments:received', vrannemstein_bulk_thumbnails );
  });

  wp.media || vrannemstein_bulk_thumbnails();
})(wp, jQuery);
</script>

