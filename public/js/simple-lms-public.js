var SimpleLMS = (function ($) {
  'use strict';

  var $this;
  var completable_list;

  // Helper for Fetch API
  function wpc_fetch(endpoint, data = {}) {
    var url = simple_lms_vars.root + endpoint;
    var headers = {
      'Content-Type': 'application/json',
      'X-WP-Nonce': simple_lms_vars.nonce
    };

    return fetch(url, {
      method: 'POST',
      headers: headers,
      body: JSON.stringify(data)
    })
      .then(response => {
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        return response.json();
      });
  }

  $(function () {
    // add click event handlers for plugin completion buttons...
    $('body').on('click', 'a.wpc-button-complete', function (e) {
      e.preventDefault();
      $this = $(this);
      var button_id = $(this).data('button');
      var button_classes = $this.attr('class').split(' ');
      button_classes = button_classes.filter(function (c) { return c.substr(0, 4) !== 'wpc-' })

      var data = {
        button: button_id,
        old_button_text: $this.find('.wpc-inactive').html(),
        new_button_text: $this.data('button-text'),
        class: button_classes.join(' ')
      }
      if ($this.attr('style')) {
        data['style'] = $this.attr('style');
      }

      // change button to disable and indicate saving...
      $this.attr('disabled', 'disabled').find('span').toggle();
      emptyCache();

      wpc_fetch('mark-completed', data)
        .then(response => {
          wpc_handleResponse(response);
        })
        .catch(error => {
          $this.attr('disabled', false).html('Error');
          alert("Uh oh! We ran into an error marking the button as completed.");
          console.error('Error:', error);
        });

      return false;
    });

    $('body').on('click', 'a.wpc-button-completed', function (e) {
      e.preventDefault();
      $this = $(this);
      var button_id = $(this).data('button');
      var button_classes = $this.attr('class').split(' ');
      button_classes = button_classes.filter(function (c) { return c.substr(0, 4) !== 'wpc-' })

      var data = {
        button: button_id,
        old_button_text: $this.find('.wpc-inactive').html(),
        new_button_text: $this.data('button-text'),
        class: button_classes.join(' ')
      }
      if ($this.attr('style')) {
        data['style'] = $this.attr('style');
      }

      // change button to disable and indicate saving...
      $this.attr('disabled', 'disabled').find('span').toggle();
      emptyCache();

      wpc_fetch('mark-uncompleted', data)
        .then(response => {
          wpc_handleResponse(response);
        })
        .catch(error => {
          $this.attr('disabled', false).html('Error');
          alert("Uh oh! We ran into an error marking the button as no longer complete.");
          console.error('Error:', error);
        });

      return false;
    });

    // Clean up confirms, so they are used in event handler when JS is enabled:
    $('a.wpc-reset-link').each(function (index) {
      var confirm_text = this.onclick.toString().match(/\(confirm\(\'(.*)\'\)\)/)[1];
      $(this).data('confirm', confirm_text);
      this.onclick = null;
    });

    $('body').on('click', 'a.wpc-reset-link', function (e) {
      e.preventDefault();
      $this = $(this);

      if (confirm($this.data('confirm'))) {
        var data = {
          course: $this.data('course')
        }

        // change button to disable and indicate saving...
        $this.attr('disabled', 'disabled').find('span').toggle();
        emptyCache();

        wpc_fetch('reset', data)
          .then(response => {
            wpc_handleResponse(response);
          })
          .catch(error => {
            $this.attr('disabled', false).html('Error');
            alert("Uh oh! We ran into an error reseting your completion data.");
            console.error('Error:', error);
          });
      }

      return false;
    });

    // Async loading of content:
    $('.wpc-button-loading').each(function (index) {
      var $button = $(this);
      var data = {
        button_id: $button.data('button'),
        old_button_text: $button.data('complete-text'),
        new_button_text: $button.data('incomplete-text'),
        redirect: $button.data('redirect')
      };

      wpc_fetch('get-button', data)
        .then(response => {
          wpc_handleResponse(response);
        })
        .catch(error => {
          $button.html('Error');
          console.error('Error:', error);
        });
    });

    if ($('.wpc-graph-loading').length > 0) {
      wpc_fetch('get-graphs', {})
        .then(response => {
          wpc_handleResponse(response);
        })
        .catch(error => {
          console.error('Error:', error);
        });
    }

    $('.wpc-content-loading').each(function (index) {
      var $content = $(this);
      // get the ids/atts for each requested content block?
      var data = {
        type: $content.data('type'),
        unique_id: $content.data('unique-id')
      };

      wpc_fetch('get-content', data)
        .then(response => {
          wpc_handleResponse(response);
        })
        .catch(error => {
          console.error('Error:', error);
        });
    });

    // PREMIUM:
    // Cleanup any cached lesson indicators
    $(document).find('.wpc-lesson').removeClass('wpc-lesson').removeClass('wpc-lesson-completed').removeClass('wpc-lesson-complete').removeClass('wpc-lesson-partial');
    // If we already have completable-list stored, no use hitting server:
    completable_list = {};
    if (localStorage && localStorage.getItem('onedog.solutionsmpletable-list-' + simple_lms_vars.user_id) && (localStorage.getItem('onedog.solutionsmpletable-list-' + simple_lms_vars.user_id) != '')) {
      // remove localStorage if we just removed data...
      if (window.location.search.includes("wpc_reset=success")) {
        localStorage.removeItem('onedog.solutionsmpletable-list-' + simple_lms_vars.user_id);
      } else {
        completable_list = JSON.parse(localStorage.getItem('onedog.solutionsmpletable-list-' + simple_lms_vars.user_id));
      }
    }
    // but make sure we don't have an old and out of date cache... 
    if (completable_list &&
      completable_list['timestamp'] &&
      // only use cache if it isn't more than 24 hours old:
      (completable_list['timestamp'] > ((new Date).getTime() / 1000 - (60 * 60 * 24))) &&
      (
        // only use cache if there's hasn't been updates to site's completability settings:
        (simple_lms_vars.updated_at == '') ||
        (completable_list['timestamp'] > parseInt(simple_lms_vars.updated_at))
      ) &&
      (completable_list['timestamp'] > parseInt(simple_lms_vars.last_activity_at))
    ) {
      wpc_appendLinkClasses(completable_list);
      var counter = 0;
      var interval = setInterval(function () {
        wpc_appendLinkClasses(completable_list);
        if (counter >= 5) {
          clearInterval(interval);
        }
        counter++;
      }, 1000);
    } else {
      wpc_fetchCompletionList();
    }

    // try to catch clicking logout links, so we can clear wpc localstorage
    $(document).on('click', 'a[href*="wp-login.php?action=logout"]', function (e) {
      localStorage.removeItem('onedog.solutionsmpletable-list-' + simple_lms_vars.user_id);
    });
  });

  jQuery(document.body).on('post-load', function () {
    wpc_appendLinkClasses(completable_list);
  });

  function wpc_appendLinkClasses(response) {
    if (!response) return;
    $('a[href]:not(.wpc-lesson)').each(function () {
      var found_link = false;
      var href = $(this).attr('href');
      if (!href) return;

      if (response[href] !== undefined) {
        found_link = response[href];
      } else if (response[href + '/'] !== undefined) {
        found_link = response[href + '/'];
      } else if (response[href.replace(/\/$/, "")]) {
        found_link = response[href.replace(/\/$/, "")];
      } else if (response[href.replace('https://', 'http://')]) {
        found_link = response[href.replace('https://', 'http://')];
      } else if (response[href.replace('http://', 'https://')]) {
        found_link = response[href.replace('http://', 'https://')];
      }

      if (found_link && !$(this).hasClass('wpc-lesson')) {
        $(this).addClass('wpc-lesson');
        if (found_link['id']) {
          $(this).addClass('wpc-lesson-' + found_link['id']);
        }
        if (found_link['status'] == 'incomplete') {
          $(this).addClass('wpc-lesson-complete');
        } else {
          $(this).addClass('wpc-lesson-' + found_link['status']);
        }
      }
    });
  }

  function wpc_handleResponse(response) {
    if ($this && $this.data('redirect')) {
      window.location.href = $this.data('redirect');
      return;
    }
    for (var x in response) {
      if ('' + x == 'redirect') {
        window.location.href = response[x];
      } else if ('' + x == 'lesson-completed') {
        $('a.wpc-lesson-' + response[x]).addClass('wpc-lesson-completed');
        $('a.wpc-lesson-' + response[x]).removeClass('wpc-lesson-complete').removeClass('wpc-lesson-partial');
      } else if ('' + x == 'lesson-partial') {
        $('a.wpc-lesson-' + response[x]).addClass('wpc-lesson-partial');
        $('a.wpc-lesson-' + response[x]).removeClass('wpc-lesson-completed').removeClass('wpc-lesson-complete');
      } else if ('' + x == 'lesson-incomplete') {
        $('a.wpc-lesson-' + response[x]).addClass('wpc-lesson-complete');
        $('a.wpc-lesson-' + response[x]).removeClass('wpc-lesson-completed').removeClass('wpc-lesson-partial');
      } else if (response[x] == 'show') {
        $(x).show();
      } else if (response[x] == 'hide') {
        $(x).hide();
      } else if (response[x] == 'trigger') {
        $(document).trigger(x);
      } else if (x == 'peer-pressure') {
        $('.wpc-peer-pressure-' + response[x]['post_id']).each(function (index) {
          var copy = '';
          if (response[x]['user_completed']) {
            copy = $(this).data('completed');
          } else if (response[x]['{number}'] == '0') {
            copy = $(this).data('zero');
          } else if (response[x]['{number}'] == '1') {
            copy = $(this).data('single');
          } else {
            copy = $(this).data('plural');
          }
          copy = copy.replace(new RegExp('{number}', 'g'), response[x]['{number}']);
          copy = copy.replace(new RegExp('{percentage}', 'g'), response[x]['{percentage}']);
          copy = copy.replace(new RegExp('{next_with_ordinal}', 'g'), response[x]['{next_with_ordinal}']);
          $(this).html(copy);
        });
      } else if (x == 'fetch') {
        // clean up cached completion list in local storage:
        if (localStorage) {
          emptyCache();
          wpc_fetchCompletionList();
        }
      } else if (x == 'wpc-reset') {
        var $container = $this.parent();
        var resetMessage = "Uh oh! We ran into an error reseting your completion data.";
        var resetClass = "failed";
        if (response[x] == 'success') {
          resetMessage = $this.data('success-text');
          resetClass = "success";
        } else if (response[x] == 'no-change') {
          resetMessage = $this.data('no-change-text');
          resetClass = "success";
        } else {
          resetMessage = $this.data('failure-text');
        }
        $container.find('.wpc-reset-message').addClass(resetClass).html(resetMessage).show();
        $container.find('a').hide();
      } else if ('' + x.indexOf('.wpc-button[data') == 0) {
        $('' + x).replaceWith(response[x]);
      } else if ('' + x.indexOf('[data-') >= 0) {
        var d = x.substring(x.indexOf('[data-') + 1, x.indexOf(']'));
        $('' + x).attr(d, response[x]);
      } else if ('' + x.indexOf('data-') == 0) {
        $('[' + x + ']').attr('' + x, response[x]);
      } else {
        $('' + x).replaceWith(response[x]);
      }
    }
  }

  function wpc_fetchCompletionList() {
    wpc_fetch('get-completable-list', {})
      .then(response => {
        if (!response['timestamp'] || !response['user']) return;
        if (localStorage && response['user'] && (response['user'] > 0)) { localStorage.setItem("onedog.solutionsmpletable-list-" + simple_lms_vars.user_id, JSON.stringify(response)); }
        completable_list = response;
        wpc_appendLinkClasses(response);
        // This is for pages that have delayed loading of links that might need marking completion:
        var counter = 0;
        var interval = setInterval(function () {
          wpc_appendLinkClasses(response);
          if (counter >= 5) {
            clearInterval(interval);
          }
          counter++;
        }, 1000);
      })
      .catch(error => {
        console.error('Error:', error);
      });
  }

  function emptyCache() {
    // clean up cached completion list in local storage:
    if (localStorage && localStorage.getItem('onedog.solutionsmpletable-list-' + simple_lms_vars.user_id)) { localStorage.removeItem('onedog.solutionsmpletable-list-' + simple_lms_vars.user_id); }
  }

  function complete(button_id) {
    if ((button_id == undefined) && simple_lms_vars.post_id) {
      button_id = simple_lms_vars.post_id;
    }
    var data = {
      button: button_id
    }

    emptyCache();

    wpc_fetch('mark-completed', data)
      .then(response => {
        wpc_handleResponse(response);
      })
      .catch(error => {
        alert("Uh oh! We ran into an error marking this post as completed.");
        console.error('Error:', error);
      });
  }

  function uncomplete(button_id) {
    if ((button_id == undefined) && simple_lms_vars.post_id) {
      button_id = simple_lms_vars.post_id;
    }
    var data = {
      button: button_id
    }

    emptyCache();

    wpc_fetch('mark-uncompleted', data)
      .then(response => {
        wpc_handleResponse(response);
      })
      .catch(error => {
        alert("Uh oh! We ran into an error marking the post as no longer complete.");
        console.error('Error:', error);
      });
  }

  function linkify() {
    if (localStorage && localStorage.getItem('onedog.solutionsmpletable-list-' + simple_lms_vars.user_id) && (localStorage.getItem('onedog.solutionsmpletable-list-' + simple_lms_vars.user_id) != '')) {
      completable_list = JSON.parse(localStorage.getItem('onedog.solutionsmpletable-list-' + simple_lms_vars.user_id));
      wpc_appendLinkClasses(completable_list);
      var counter = 0;
      var interval = setInterval(function () {
        wpc_appendLinkClasses(completable_list);
        if (counter >= 5) {
          clearInterval(interval);
        }
        counter++;
      }, 1000);
    } else {
      wpc_fetchCompletionList();
    }
  }

  return {
    complete: complete,
    uncomplete: uncomplete,
    linkify: linkify
  };

})(jQuery);
