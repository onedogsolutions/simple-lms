(function ($) {
  'use strict';

  // PREMIUM: Add color picker widget to all fields with .wpc-color-picker class:
  $('.wpc-color-picker').wpColorPicker();

  // PREMIUM: Completion URL autocomplete:
  var cache = {};
  var post_id = ($('#post_ID').length > 0) ? $('#post_ID').val() : 0;
  $("#completion_redirect_to").autocomplete({
    delay: 500,
    source: function (request, response) {
      var term = request.term;
      if (term in cache) {
        response(cache[term]);
        return;
      }
      $.getJSON(SimpleLMS.url + "?action=wpc_post_lookup&post_id=" + post_id + "&nonce=" + SimpleLMS.nonce, request, function (data, status, xhr) {
        cache[term] = data;
        response(data);
      });
    },
    select: function (event, ui) {
      $('#completion_redirect_url').val(ui.item.link);
      $("#completion_redirect_to").val(ui.item.label);
      return false;
    }
  }).on('keyup', function (e) {
    if (e.target.value == '') {
      $('#completion_redirect_url').val('');
    }
  });

  // we create a copy of the WP inline edit post function
  var $wp_inline_edit = inlineEditPost.edit;
  // and then we overwrite the function with our own code
  inlineEditPost.edit = function (id) {

    // "call" the original WP edit function
    // we don't want to leave WordPress hanging
    $wp_inline_edit.apply(this, arguments);

    // now we take care of our business

    // get the post ID
    var $post_id = 0;
    if (typeof (id) == 'object')
      $post_id = parseInt(this.getId(id));

    if ($post_id > 0) {

      // define the edit row
      var $edit_row = $('#edit-' + $post_id);

      // get the completable button status
      var $completable = $('#completable-' + $post_id).text();
      // populate the completable button status
      $edit_row.find('input[name="onedog.solutionsmpletable]"]').attr('checked', $completable != '—');
      if ($completable != '—') {
        var $completable_course = $('#completable-course-' + $post_id).text();
        $edit_row.find('.inline-edit-group.onedog.solutionsurse-container').show();
        $edit_row.find('.course-toggle option[value="' + $completable_course + '"]').prop('selected', true);//.val($completable_course);
      } else {
        $edit_row.find('.inline-edit-group.onedog.solutionsurse-container').hide();
      }

    }

  };

  $(document).on('click', '.wpc_delete_button', function (e) {
    e.preventDefault();

    if (confirm('Are you sure you want to delete this button?\n\nNote: Remember to remove any shortcodes for this button or it will continue to be shown.')) {
      var elm = this;
      var post_id = $(this).data('post-id');
      var button = $(this).data('button');

      $.getJSON(SimpleLMS.url + "?action=wpc_delete_button", { post_id: post_id, button: button, nonce: SimpleLMS.nonce }, function (data, status, xhr) {
        console.log(data);
        $(elm).parent().fadeOut(1000);
      });
    }

    return false;
  });

  $(document).on('click', '.wpc_delete_all', function (e) {
    e.preventDefault();

    if (confirm('Are you sure you want to reset all buttons for this post?\n\nNote: Remember to remove any unwanted shortcodes for buttons or they will continue to be registered.')) {
      var elm = this;
      var post_id = $(this).data('post-id');

      $.getJSON(SimpleLMS.url + "?action=wpc_delete_button", { post_id: post_id, nonce: SimpleLMS.nonce }, function (data, status, xhr) {
        console.log("wpc_delete_all: " + data);
        $('.wpc-buttons-container').fadeOut(1000, function () {
          $('.wpc-buttons-container').html("Your post's buttons have been removed. Once you save your post, any buttons from shortcodes will be re-registered.").fadeIn(1000);
        });
      });
    }

    return false;
  });

  $(document).on('click', '.wpc_reset_button', function (e) {
    e.preventDefault();

    if (confirm('Are you sure you want to delete all user activity data for this button? It can NOT be restored once deleted.')) {
      var elm = this;
      var button = $(this).data('button-id');

      $.getJSON(SimpleLMS.url + "?action=wpc_reset_button", { button: button, nonce: SimpleLMS.nonce }, function (data, status, xhr) {
        console.log("wpc_reset_button: " + data);
        //$(elm).parent().fadeOut(1000);
        // display a message or swap out table record with new data?
        window.location = window.location.href;
      });
    }

    return false;
  });

  $(document).on('click', '.dev-mode-nag .notice-dismiss', function () {
    $.ajax(ajaxurl, {
      type: 'POST',
      data: { action: 'dismissed_devmode_notice_handler' }
    });
  });

  $(document).on('click', '.license-nag .notice-dismiss', function () {
    $.ajax(ajaxurl, {
      type: 'POST',
      data: { action: 'dismissed_license_notice_handler' }
    });
  });

})(jQuery);
