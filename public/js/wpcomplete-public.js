var wpcomplete = (function( $ ) {
  'use strict';

  var $this;
  var completable_list;
  
  $(function() {
    // add click event handlers for plugin completion buttons...
    $('body').on('click', 'a.wpc-button-complete', function(e) {
      e.preventDefault();
      $this = $(this);
      var button_id = $(this).data('button');
      var button_classes = $this.attr('class').split(' ');
      button_classes = button_classes.filter(function(c) { return c.substr(0, 4) !== 'wpc-' })

      var data = {
        _ajax_nonce: wpcompletable.nonce,
        action: 'mark_completed',
        button: button_id,
        old_button_text: $this.find('.wpc-inactive').html(),
        new_button_text: $this.data('button-text'),
        class: button_classes.join(' ')
      }
      if ( $this.attr('style') ) {
        data['style'] = $this.attr('style');
      }

      // change button to disable and indicate saving...
      $this.attr('disabled', 'disabled').find('span').toggle();
      emptyCache();
      
      $.ajax({
        url: wpcompletable.ajax_url + "?" + (new Date).getTime(),
        type: 'POST',
        dataType: 'json',
        data: data,
        success: wpc_handleResponse,
        error: function(xhr, textStatus, errorThrown) {
          $this.attr('disabled', false).html('Error');
          alert("Uh oh! We ran into an error marking the button as completed.");
          console.log(textStatus);
          console.log(errorThrown);
        }
      });
      return false;
    });
    $('body').on('click', 'a.wpc-button-completed', function(e) {
      e.preventDefault();
      $this = $(this);
      var button_id = $(this).data('button');
      var button_classes = $this.attr('class').split(' ');
      button_classes = button_classes.filter(function(c) { return c.substr(0, 4) !== 'wpc-' })

      var data = {
        _ajax_nonce: wpcompletable.nonce,
        action: 'mark_uncompleted',
        button: button_id,
        old_button_text: $this.find('.wpc-inactive').html(),
        new_button_text: $this.data('button-text'),
        class: button_classes.join(' ')
      }
      if ( $this.attr('style') ) {
        data['style'] = $this.attr('style');
      }

      // change button to disable and indicate saving...
      $this.attr('disabled', 'disabled').find('span').toggle();
      emptyCache();

      $.ajax({
        url: wpcompletable.ajax_url + "?" + (new Date).getTime(),
        type: 'POST',
        dataType: 'json',
        data: data,
        success: wpc_handleResponse,
        error: function(xhr, textStatus, errorThrown) {
          $this.attr('disabled', false).html('Error');
          alert("Uh oh! We ran into an error marking the button as no longer complete.");
          console.log(textStatus);
          console.log(errorThrown);
        }
      });
      return false;
    });

    // Clean up confirms, so they are used in event handler when JS is enabled:
    $('a.wpc-reset-link').each( function( index ) {
      var confirm_text = this.onclick.toString().match(/\(confirm\(\'(.*)\'\)\)/)[1];
      $(this).data('confirm', confirm_text);
      this.onclick = null;
    });

    $('body').on('click', 'a.wpc-reset-link', function(e) {
      e.preventDefault();
      $this = $(this);

      if ( confirm( $this.data('confirm') ) ) {
        var data = {
          _ajax_nonce: $this.data('nonce'),
          action: 'reset',
          course: $this.data('course')
        }

        // change button to disable and indicate saving...
        $this.attr('disabled', 'disabled').find('span').toggle();
        emptyCache();

        $.ajax({
          url: wpcompletable.ajax_url + "?" + (new Date).getTime(),
          type: 'POST',
          dataType: 'json',
          data: data,
          success: wpc_handleResponse,
          error: function(xhr, textStatus, errorThrown) {
            $this.attr('disabled', false).html('Error');
            alert("Uh oh! We ran into an error reseting your completion data.");
            console.log(textStatus);
            console.log(errorThrown);
          }
        });
      }

      return false;
    });

    // Async loading of content:
    $('.wpc-button-loading').each(function( index ) {
      var $button = $(this);
      $.ajax({
        url: wpcompletable.ajax_url + "?" + (new Date).getTime(),
        type: 'POST',
        dataType: 'json',
        data: {
          _ajax_nonce: wpcompletable.nonce,
          action: 'get_button',
          button_id: $button.data('button'),
          old_button_text: $button.data('complete-text'),
          new_button_text: $button.data('incomplete-text'),
          redirect: $button.data('redirect')
        },
        success: wpc_handleResponse,
        error: function(xhr, textStatus, errorThrown) {
          $button.html('Error');
          //alert("Uh oh! We ran into an error marking the button as no longer complete.");
          console.log(textStatus);
          console.log(errorThrown);
        }
      });
    });

    if ($('.wpc-graph-loading').length > 0) {
      $.ajax({
        url: wpcompletable.ajax_url + "?" + (new Date).getTime(),
        type: 'POST',
        dataType: 'json',
        data: {
          _ajax_nonce: wpcompletable.nonce,
          action: 'get_graphs'
        },
        success: wpc_handleResponse,
        error: function(xhr, textStatus, errorThrown) {
          //alert("Uh oh! We ran into an error loading your graphs.");
          console.log(textStatus);
          console.log(errorThrown);
        }
      });
    }

    $('.wpc-content-loading').each(function( index ) {
      var $content = $(this);
      // get the ids/atts for each requested content block?
      $.ajax({
        url: wpcompletable.ajax_url + "?" + (new Date).getTime(),
        type: 'POST',
        dataType: 'json',
        data: {
          _ajax_nonce: wpcompletable.nonce,
          action: 'get_content',
          type: $content.data('type'),
          unique_id: $content.data('unique-id')
        },
        success: wpc_handleResponse,
        error: function(xhr, textStatus, errorThrown) {
          //alert("Uh oh! We ran into an error loading your content.");
          console.log(textStatus);
          console.log(errorThrown);
        }
      });
    });

    // PREMIUM:
    // Cleanup any cached lesson indicators
    $(document).find('.wpc-lesson').removeClass('wpc-lesson').removeClass('wpc-lesson-completed').removeClass('wpc-lesson-complete').removeClass('wpc-lesson-partial');
    // If we already have completable-list stored, no use hitting server:
    completable_list = {};
    if ( localStorage && localStorage.getItem('wpcomplete.completable-list-' + wpcompletable.user_id) && ( localStorage.getItem('wpcomplete.completable-list-' + wpcompletable.user_id) != '' ) ) {
      // remove localStorage if we just removed data...
      if ( window.location.search.includes("wpc_reset=success") ) {
        localStorage.removeItem('wpcomplete.completable-list-' + wpcompletable.user_id);
      } else {
        completable_list = JSON.parse(localStorage.getItem('wpcomplete.completable-list-' + wpcompletable.user_id));
      }
    }
    // but make sure we don't have an old and out of date cache... 
    if ( completable_list && 
        completable_list['timestamp'] && 
        // only use cache if it isn't more than 24 hours old:
        ( completable_list['timestamp'] > ((new Date).getTime()/1000 - (60*60*24)) ) && 
        ( 
          // only use cache if there's hasn't been updates to site's completability settings:
          (wpcompletable.updated_at == '') || 
          ( completable_list['timestamp'] > parseInt(wpcompletable.updated_at) ) 
        ) &&
        ( completable_list['timestamp'] > parseInt(wpcompletable.last_activity_at) ) 
      ) {
      wpc_appendLinkClasses(completable_list);
      var counter = 0;
      var interval = setInterval(function() {
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
    $(document).on('click', 'a[href*="wp-login.php?action=logout"]', function(e) {
      localStorage.removeItem('wpcomplete.completable-list-' + wpcompletable.user_id);
    });
  });

  jQuery( document.body ).on( 'post-load', function() {
    wpc_appendLinkClasses(completable_list);
  });

  function wpc_appendLinkClasses(response) {
    $('a[href]:not(.wpc-lesson)').each(function() {
      var found_link = false;
      if (response[$(this).attr('href')] !== undefined) {
        found_link = response[$(this).attr('href')];
      } else if (response[$(this).attr('href') + '/'] !== undefined) {
        found_link = response[$(this).attr('href') + '/'];        
      } else if (response[$(this).attr('href').replace(/\/$/, "")]) {
        found_link = response[$(this).attr('href').replace(/\/$/, "")];
      } else if (response[$(this).attr('href').replace('https://', 'http://')]) {
        found_link = response[$(this).attr('href').replace('https://', 'http://')];
      } else if (response[$(this).attr('href').replace('http://', 'https://')]) {
        found_link = response[$(this).attr('href').replace('http://', 'https://')];
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
      if (''+x == 'redirect') {
        window.location.href = response[x];
      } else if (''+x == 'lesson-completed') {
        $('a.wpc-lesson-' + response[x]).addClass('wpc-lesson-completed');
        $('a.wpc-lesson-' + response[x]).removeClass('wpc-lesson-complete').removeClass('wpc-lesson-partial');
      } else if (''+x == 'lesson-partial') {
        $('a.wpc-lesson-' + response[x]).addClass('wpc-lesson-partial');
        $('a.wpc-lesson-' + response[x]).removeClass('wpc-lesson-completed').removeClass('wpc-lesson-complete');
      } else if (''+x == 'lesson-incomplete') {
        $('a.wpc-lesson-' + response[x]).addClass('wpc-lesson-complete');
        $('a.wpc-lesson-' + response[x]).removeClass('wpc-lesson-completed').removeClass('wpc-lesson-partial');
      } else if (response[x] == 'show') {
        $(x).show();
      } else if (response[x] == 'hide') {
        $(x).hide();
      } else if (response[x] == 'trigger') {
        $(document).trigger(x);
      } else if (x == 'peer-pressure') {
        $('.wpc-peer-pressure-' + response[x]['post_id']).each(function(index) {
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
        if ( localStorage ) {
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
      } else if (''+x.indexOf('.wpc-button[data') == 0) {
        $(''+x).replaceWith(response[x]);
      } else if (''+x.indexOf('[data-') >= 0) {
        var d = x.substring(x.indexOf('[data-')+1, x.indexOf(']'));
        $(''+x).attr(d, response[x]);
      } else if (''+x.indexOf('data-') == 0) {
        $('['+x+']').attr(''+x, response[x]);
      } else {
        $(''+x).replaceWith(response[x]);
      }
    }
  }

  function wpc_fetchCompletionList() {
    // Do ajax call to backend to get a list of ALL the completable lessons.
    // Then filter through each link on the page and add specific classes to completed and incomplete links.
    $.ajax({
      url: wpcompletable.ajax_url + "?" + (new Date).getTime(),
      type: 'POST',
      dataType: 'json',
      data: {
        _ajax_nonce: wpcompletable.nonce,
        action: 'get_completable_list'
      },
      success: function(response) {
        if ( !response['timestamp'] || !response['user'] ) return false;
        if ( localStorage && response['user'] && ( response['user'] > 0 ) ) { localStorage.setItem("wpcomplete.completable-list-" + wpcompletable.user_id, JSON.stringify(response)); }
        completable_list = response;
        //console.log(response);
        wpc_appendLinkClasses(response);
        // This is for pages that have delayed loading of links that might need marking completion:
        var counter = 0;
        var interval = setInterval(function() {
          wpc_appendLinkClasses(response);
          if (counter >= 5) {
            clearInterval(interval);
          }
          counter++;
        }, 1000);
      },
      error: function(xhr, textStatus, errorThrown) {
        console.log(textStatus);
        console.log(errorThrown);
      }
    });
  }

  function emptyCache() {
    // clean up cached completion list in local storage:
    if ( localStorage && localStorage.getItem('wpcomplete.completable-list-' + wpcompletable.user_id) ) { localStorage.removeItem('wpcomplete.completable-list-' + wpcompletable.user_id); }
  }

  function complete(button_id) {
    if ( ( button_id == undefined ) && wpcompletable.post_id ) {
      button_id = wpcompletable.post_id;
    }
    var data = {
      _ajax_nonce: wpcompletable.nonce,
      action: 'mark_completed',
      button: button_id
    }

    emptyCache();
    
    $.ajax({
      url: wpcompletable.ajax_url + "?" + (new Date).getTime(),
      type: 'POST',
      dataType: 'json',
      data: data,
      success: wpc_handleResponse,
      error: function(xhr, textStatus, errorThrown) {
        alert("Uh oh! We ran into an error marking this post as completed.");
        console.log(textStatus);
        console.log(errorThrown);
      }
    });
  }

  function uncomplete(button_id) {
    if ( ( button_id == undefined ) && wpcompletable.post_id ) {
      button_id = wpcompletable.post_id;
    }
    var data = {
      _ajax_nonce: wpcompletable.nonce,
      action: 'mark_uncompleted',
      button: button_id
    }

    emptyCache();

    $.ajax({
      url: wpcompletable.ajax_url + "?" + (new Date).getTime(),
      type: 'POST',
      dataType: 'json',
      data: data,
      success: wpc_handleResponse,
      error: function(xhr, textStatus, errorThrown) {
        alert("Uh oh! We ran into an error marking the post as no longer complete.");
        console.log(textStatus);
        console.log(errorThrown);
      }
    });
  }

  function linkify() {
    if ( localStorage && localStorage.getItem('wpcomplete.completable-list-' + wpcompletable.user_id) && ( localStorage.getItem('wpcomplete.completable-list-' + wpcompletable.user_id) != '' ) ) {
      completable_list = JSON.parse(localStorage.getItem('wpcomplete.completable-list-' + wpcompletable.user_id));
      wpc_appendLinkClasses(completable_list);
      var counter = 0;
      var interval = setInterval(function() {
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

})( jQuery );
