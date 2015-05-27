/*
   scp.js

   osTicket SCP
   Copyright (c) osTicket.com

 */

function checkbox_checker(formObj, min, max) {

    var max = max || 0;
    var min = min || 1;
    var checked=$('input:checkbox:checked', formObj).length;
    var action= action?action:"process";
    if (max>0 && checked > max ){
        msg=__("You're limited to only {0} selections.\n") .replace('{0}', max);
        msg=msg + __("You have made {0} selections.\n").replace('{0}', checked);
        msg=msg + __("Please remove {0} selection(s).").replace('{0}', checked-max);
        $.sysAlert(__('Alert'), msg);

        return (false);
    }

    if (checked< min ){
        $.sysAlert( __('Alert'),
                __("Please make at least {0} selections. {1} checked so far.")
                .replace('{0}', min)
                .replace('{1}', checked)
                );

        return (false);
    }

    return checked;
}


var scp_prep = function() {

    $("input[autofocus]:visible:enabled:first").each(function() {
      if ($(this).val())
        $(this).blur();
    });
    $('table.list input:checkbox').bind('click, change', function() {
        $(this)
            .parents("tr:first")
            .toggleClass("highlight", this.checked);
     });

    $('table.list input:checkbox:checked').trigger('change');

    $('#selectAll').click(function(e) {
        e.preventDefault();
        var target = $(this).attr('href').substr(1, $(this).attr('href').length);
        $(this).closest('form')
            .find('input:enabled:checkbox.'+target)
            .prop('checked', true)
            .trigger('change');

        return false;
     });


    $('#selectNone').click(function(e) {
        e.preventDefault();
        var target = $(this).attr('href').substr(1, $(this).attr('href').length);
        $(this).closest('form')
            .find('input:enabled:checkbox.'+target)
            .prop('checked', false)
            .trigger('change');
        return false;
     });

    $('#selectToggle').click(function(e) {
        e.preventDefault();
        var target = $(this).attr('href').substr(1, $(this).attr('href').length);
        $(this).closest('form')
            .find('input:enabled:checkbox.'+target)
            .each(function() {
                $(this)
                    .prop('checked', !$(this).is(':checked'))
                    .trigger('change');
             });
        return false;
     });

    $('#actions :submit.button:not(.no-confirm)').bind('click', function(e) {

        var formObj = $(this).closest('form');
        e.preventDefault();
        if($('.dialog#confirm-action p#'+this.name+'-confirm').length == 0) {
            alert('Unknown action '+this.name+' - get technical help.');
        } else if(checkbox_checker(formObj, 1)) {
            var action = this.name;
            $('.dialog#confirm-action').undelegate('.confirm');
            $('.dialog#confirm-action').delegate('input.confirm', 'click.confirm', function(e) {
                e.preventDefault();
                $('.dialog#confirm-action').hide();
                $.toggleOverlay(false);
                $('input#action', formObj).val(action);
                formObj.submit();
                return false;
             });
            $.toggleOverlay(true);
            $('.dialog#confirm-action .confirm-action').hide();
            $('.dialog#confirm-action p#'+this.name+'-confirm')
            .show()
            .parent('div').show().trigger('click');
        }

        return false;
     });

    $('a.confirm-action').click(function(e) {
        $dialog = $('.dialog#confirm-action');
        if ($($(this).attr('href')+'-confirm', $dialog).length) {
            e.preventDefault();
            var action = $(this).attr('href').substr(1, $(this).attr('href').length);

            $('input#action', $dialog).val(action);
            $.toggleOverlay(true);
            $('.confirm-action', $dialog).hide();
            $('p'+$(this).attr('href')+'-confirm', $dialog)
            .show()
            .parent('div').show().trigger('click');

            return false;
        }
     });

    var warnOnLeave = function (el) {
        var fObj = el.closest('form');
        if(!fObj.data('changed')){
            fObj.data('changed', true);
            $('input[type=submit]', fObj).css('color', 'red');
            $(window).bind('beforeunload', function(e) {
                return __('Are you sure you want to leave? Any changes or info you\'ve entered will be discarded!');
            });
            $(document).on('pjax:beforeSend.changed', function(e) {
                return confirm(__('Are you sure you want to leave? Any changes or info you\'ve entered will be discarded!'));
            });
        }
    };

    $("form#save :input[name]").change(function() {
        if (!$(this).is('.nowarn')) warnOnLeave($(this));
    });

    $("form#save :input[type=reset]").click(function() {
        var fObj = $(this).closest('form');
        if(fObj.data('changed')){
            $('input[type=submit]', fObj).removeAttr('style');
            $('label', fObj).removeAttr('style');
            $('label', fObj).removeClass('strike');
            fObj.data('changed', false);
            $(window).unbind('beforeunload');
        }
    });

    $('form#save, form:has(table.list)').submit(function() {
        $(window).unbind('beforeunload');
        $('#overlay, #loading').show();
        return true;
     });

    $('select#tpl_options').change(function() {
        var $this = $(this), form = $this.closest('form');
        if ($this.val() % 1 !== 0) {
            $('[name="a"]', form).val('implement');
            $this.attr('name', 'code_name');
        }
        form.submit();
     });

    $(document).on('click', ".clearrule",function() {
        $(this).closest("tr").find(":input").val('');
        return false;
     });


    //Canned attachments.
    $('.canned_attachments, .faq_attachments').delegate('input:checkbox', 'click', function(e) {
        var elem = $(this);
        if(!$(this).is(':checked') && confirm(__("Are you sure you want to remove this attachment?"))==true) {
            elem.parent().addClass('strike');
        } else {
            elem.attr('checked', 'checked');
            elem.parent().removeClass('strike');
        }
     });

    $('form select#cannedResp').change(function() {

        var fObj = $(this).closest('form');
        var cid = $(this).val();
        var tid = $(':input[name=id]',fObj).val();
        $(this).find('option:first').attr('selected', 'selected').parent('select');

        var $url = 'ajax.php/kb/canned-response/'+cid+'.json';
        if (tid)
            $url =  'ajax.php/tickets/'+tid+'/canned-resp/'+cid+'.json';

        $.ajax({
                type: "GET",
                url: $url,
                dataType: 'json',
                cache: false,
                success: function(canned){
                    //Canned response.
                    var box = $('#response',fObj),
                        redactor = box.data('redactor');
                    if(canned.response) {
                        if (redactor)
                            redactor.insert.html(canned.response);
                        else
                            box.val(box.val() + canned.response);

                        if (redactor)
                            redactor.observe.load();
                    }
                    //Canned attachments.
                    var ca = $('.attachments', fObj);
                    if(canned.files && ca.length) {
                        var fdb = ca.find('.dropzone').data('dropbox');
                        $.each(canned.files,function(i, j) {
                          fdb.addNode(j);
                        });
                    }
                }
            })
            .done(function() { })
            .fail(function() { });
    });


    /* Get config settings from the backend */
    getConfig().then(function(c) {
        // Datepicker
        $('.dp').datepicker({
            numberOfMonths: 2,
            showButtonPanel: true,
            buttonImage: './images/cal.png',
            showOn:'both',
            dateFormat: $.translate_format(c.date_format||'m/d/Y')
        });

    });

    /* Typeahead tickets lookup */
    var last_req;
    $('#basic-ticket-search').typeahead({
        source: function (typeahead, query) {
            if (last_req) last_req.abort();
            last_req = $.ajax({
                url: "ajax.php/tickets/lookup?q="+query,
                dataType: 'json',
                success: function (data) {
                    typeahead.process(data);
                }
            });
        },
        onselect: function (obj) {
            var form = $('#basic-ticket-search').closest('form');
            form.find('input[name=search-type]').val('email');
            $('#basic-ticket-search').val(obj.value);
            form.submit();
        },
        property: "matches"
    });

    /* Typeahead user lookup */
    $('.email.typeahead').typeahead({
        source: function (typeahead, query) {
            if(query.length > 2) {
                if (last_req) last_req.abort();
                last_req = $.ajax({
                    url: "ajax.php/users?q="+query,
                    dataType: 'json',
                    success: function (data) {
                        typeahead.process(data);
                    }
                });
            }
        },
        onselect: function (obj) {
            var fObj=$('.email.typeahead').closest('form');
            if(obj.name)
                $('.auto.name', fObj).val(obj.name);
        },
        property: "email"
    });

    $('.staff-username.typeahead').typeahead({
        source: function (typeahead, query) {
            if(query.length > 2) {
                if (last_req) last_req.abort();
                last_req = $.ajax({
                    url: "ajax.php/users/staff?q="+query,
                    dataType: 'json',
                    success: function (data) {
                        typeahead.process(data);
                    }
                });
            }
        },
        onselect: function (obj) {
            var fObj=$('.staff-username.typeahead').closest('form');
            $.each(['first','last','email','phone','mobile'], function(i,k) {
                if (obj[k]) $('.auto.'+k, fObj).val(obj[k]);
            });
        },
        property: "username"
    });

    //Dialog
    $('.dialog').resize(function() {
        var w = $(window), $this=$(this);
        $this.css({
            top : (w.innerHeight() / 7),
            left : (w.width() - $this.outerWidth()) / 2
        });
        $this.hasClass('draggable') && $this.draggable({handle:'.drag-handle'});
    });


    $('.dialog').each(function() {
        $this=$(this);
        $this.resize();
        $this.hasClass('draggable') && $this.draggable({handle:'.drag-handle'});
    });

    $('.dialog').delegate('input.close, a.close', 'click', function(e) {
        e.preventDefault();
        var $dialog = $(this).parents('div.dialog');
        $dialog.off('blur.redactor');
        $dialog
        .hide()
        .removeAttr('style');
        $.toggleOverlay(false);

        return false;
    });

    /* loading ... */
    $("#loading").css({
        top  : ($(window).height() / 3),
        left : ($(window).width() - $("#loading").outerWidth()) / 2
    });

    $('#advanced-search').delegate('#statusId, #flag', 'change', function() {
        switch($(this).children('option:selected').data('state')) {
            case 'closed':
                $('select#assignee')
                .attr('disabled','disabled')
                .find('option:first')
                .attr('selected', 'selected');
                $('select#flag')
                .attr('disabled','disabled')
                .find('option:first')
                .attr('selected', 'selected');
                $('select#staffId').removeAttr('disabled');
                break;
            case 'open':
                $('select#staffId')
                .attr('disabled','disabled')
                .find('option:first')
                .attr('selected', 'selected');
                $('select#assignee').removeAttr('disabled');
                $('select#flag').removeAttr('disabled');
                break;
            default:
                $('select#staffId').removeAttr('disabled');
                $('select#assignee').removeAttr('disabled');
                $('select#flag').removeAttr('disabled');
        }
    });

    $('#advanced-search form#search').submit(function(e) {
        e.preventDefault();
        var fObj = $(this);
        var elem = $('#advanced-search');
        $('#result-count').html('');
        fixupDatePickers.call(this);
        $.ajax({
                url: "ajax.php/tickets/search",
                data: fObj.serialize(),
                dataType: 'json',
                beforeSend: function ( xhr ) {
                   $('.buttons', elem).hide();
                   $('.spinner', elem).show();
                   return true;
                },
                success: function (resp) {

                    if(resp.success) {
                        $('#result-count').html('<div class="success">' + resp.success +'</div>');
                    } else if (resp.fail) {
                        $('#result-count').html('<div class="fail">' + resp.fail +'</div>');
                    } else {
                        $('#result-count').html('<div class="fail">Unknown error</div>');
                    }
                }
            })
            .done( function () {
             })
            .fail( function () {
                $('#result-count').html('<div class="fail">'
                    + __('Advanced search failed - try again!') + '</div>');
            })
            .always( function () {
                $('.spinner', elem).hide();
                $('.buttons', elem).show();
             });
    });

   // Return a helper with preserved width of cells
   var fixHelper = function(e, ui) {
      ui.children().each(function() {
          $(this).width($(this).width());
      });
      return ui;
   };

   // Sortable tables for dynamic forms objects
   $('.sortable-rows').sortable({
       'helper': fixHelper,
       'cursor': 'move',
       'stop': function(e, ui) {
           var attr = ui.item.parent('tbody').data('sort'),
               offset = parseInt($('#sort-offset').val(), 10) || 0;
           warnOnLeave(ui.item);
           $('input[name^='+attr+']', ui.item.parent('tbody')).each(function(i, el) {
               $(el).val(i + 1 + offset);
           });
       }
   });

   // Make translatable fields translatable
   $('input[data-translate-tag], textarea[data-translate-tag]').translatable();

   if (window.location.hash) {
     $('ul.tabs li a[href="' + window.location.hash + '"]').trigger('click');
   }

   // Make sticky bars float on scroll
   // Thanks, https://stackoverflow.com/a/17166225/1025836
   $('div.sticky.bar:not(.stop)').each(function() {
     var $that = $(this),
         placeholder = $('<div class="sticky placeholder">').insertBefore($that),
         offset = $that.offset(),
         top = offset.top - parseFloat($that.css('marginTop').replace(/auto/, 100)),
         stop = $('div.sticky.bar.stop'),
         stopAt,
         visible = false;

     if (stop.length) {
       var onmove = function() {
         // Recalc when pictures pop in
         stopAt = stop.offset().top;
       };
       $('#ticket_thread .thread-body img').each(function() {
         this.onload = onmove;
       });
       onmove();
     }

     // Drop the sticky bar on PJAX navigation
     $(document).on('pjax:click', function() {
         placeholder.removeAttr('style');
         $that.stop().removeClass('fixed');
         $(window).off('.sticky');
     });

     $that.find('.content').width($that.width());
     $(window).on('scroll.sticky', function (event) {
       // what the y position of the scroll is
       var y = $(this).scrollTop();

       // whether that's below the form
       if (y >= top && (!stopAt || stopAt > y)) {
         // if so, add the fixed class
         if (!visible) {
           visible = true;
           setTimeout(function() {
             $that.addClass('fixed').css('top', '-'+$that.height()+'px')
                .animate({top:0}, {easing: 'swing', duration:'fast'});
             placeholder.height($that.height());
             $that.find('[data-dropdown]').dropdown('hide');
           }, 1);
         }
       } else {
         // otherwise remove it
         if (visible) {
           visible = false;
           setTimeout(function() {
             placeholder.removeAttr('style');
             $that.find('[data-dropdown]').dropdown('hide');
             $that.stop().removeClass('fixed');
           }, 1);
         }
       }
    });
  });

  $('[data-toggle="tooltip"]').tooltip()
};

$(document).ready(scp_prep);
$(document).on('pjax:end', scp_prep);
var fixupDatePickers = function() {
    // Reformat dates
    $('.dp', $(this)).each(function(i, e) {
        var $e = $(e),
            d = $e.datepicker('getDate');
        if (!d || $e.data('fixed')) return;
        var day = ('0'+d.getDate()).substr(-2),
            month = ('0'+(d.getMonth()+1)).substr(-2),
            year = d.getFullYear();
        $e.val(year+'-'+month+'-'+day);
        $e.data('fixed', true);
        $e.on('change', function() { $(this).data('fixed', false); });
    });
};
$(document).on('submit', 'form', fixupDatePickers);

    /************ global inits *****************/

//Add CSRF token to the ajax requests.
// Many thanks to https://docs.djangoproject.com/en/dev/ref/contrib/csrf/ + jared.
$(document).ajaxSend(function(event, xhr, settings) {

    function sameOrigin(url) {
        // url could be relative or scheme relative or absolute
        var host = document.location.host; // host + port
        var protocol = document.location.protocol;
        var sr_origin = '//' + host;
        var origin = protocol + sr_origin;
        // Allow absolute or scheme relative URLs to same origin
        return (url == origin || url.slice(0, origin.length + 1) == origin + '/') ||
            (url == sr_origin || url.slice(0, sr_origin.length + 1) == sr_origin + '/') ||
            // or any other URL that isn't scheme relative or absolute i.e
            // relative.
            !(/^(\/\/|http:|https:).*/.test(url));
    }

    function safeMethod(method) {
        return (/^(GET|HEAD|OPTIONS|TRACE)$/.test(method));
    }
    if (!safeMethod(settings.type) && sameOrigin(settings.url)) {
        xhr.setRequestHeader("X-CSRFToken", $("meta[name=csrf_token]").attr("content"));
    }

});

/* Get config settings from the backend */
jQuery.fn.exists = function() { return this.length>0; };

$.translate_format = function(str) {
    var translation = {
        'DD':   'oo',
        'D':    'o',
        'EEEE': 'DD',
        'EEE':  'D',
        'MMMM': '||',   // Double replace necessary
        'MMM':  '|',
        'MM':   'mm',
        'M':    'm',
        '||':   'MM',
        '|':    'M',
        'yyyy': '`',
        'yyy':  '`',
        'yy':   'y',
        '`':    'yy'
    };
    // Change PHP formats to datepicker ones
    $.each(translation, function(php, jqdp) {
        str = str.replace(php, jqdp);
    });
    return str;
};
$(document).keydown(function(e) {

    if (e.keyCode == 27 && !$('#overlay').is(':hidden')) {
        $('div.dialog').hide();
        $.toggleOverlay(false);

        e.preventDefault();
        e.stopPropagation();
        return false;
    }
});


$(document).on('focus', 'form.spellcheck textarea, form.spellcheck input[type=text]', function() {
  var $this = $(this);
  if ($this.attr('lang') !== undefined)
    return;
  var lang = $(this).closest('[lang]').attr('lang');
  if (lang)
    $(this).attr({'spellcheck':'true', 'lang': lang});
});

$.toggleOverlay = function (show) {
  if (typeof(show) === 'undefined') {
    return $.toggleOverlay(!$('#overlay').is(':visible'));
  }
  if (show) {
    $('#overlay').stop().hide().fadeIn();
    $('body').css('overflow', 'hidden');
  }
  else {
    $('#overlay').stop().fadeOut();
    $('body').css('overflow', 'auto');
  }
};

$.dialog = function (url, codes, cb, options) {
    options = options||{};

    if (codes && !$.isArray(codes))
        codes = [codes];

    var $popup = $('.dialog#popup');

    $popup.attr('class',
        function(pos, classes) {
            return classes.replace(/\bsize-\S+/g, '');
    });

    $popup.addClass(options.size ? ('size-'+options.size) : 'size-normal');

    $.toggleOverlay(true);
    $('div.body', $popup).empty().hide();
    $('div#popup-loading', $popup).show()
        .find('h1').css({'margin-top':function() { return $popup.height()/3-$(this).height()/3}});
    $popup.resize().show();
    $('div.body', $popup).load(url, function () {
        $('div#popup-loading', $popup).hide();
        $('div.body', $popup).slideDown({
            duration: 300,
            queue: false,
            complete: function() {
                if (options.onshow) options.onshow();
                $(this).removeAttr('style');
            }
        });
        $("input[autofocus]:visible:enabled:first", $popup).focus();
        var submit_button = null;
        $(document).off('.dialog');
        $(document).on('click.dialog',
            '#popup input[type=submit], #popup button[type=submit]',
            function(e) { submit_button = $(this); });
        $(document).on('submit.dialog', '.dialog#popup form', function(e) {
            e.preventDefault();
            var $form = $(this),
                data = $form.serialize();
            if (submit_button) {
                data += '&' + escape(submit_button.attr('name')) + '='
                    + escape(submit_button.attr('value'));
            }
            $('div#popup-loading', $popup).show()
                .find('h1').css({'margin-top':function() { return $popup.height()/3-$(this).height()/3}});
            $.ajax({
                type:  $form.attr('method'),
                url: 'ajax.php/'+$form.attr('action').substr(1),
                data: data,
                cache: false,
                success: function(resp, status, xhr) {
                    if (xhr && xhr.status && codes
                        && $.inArray(xhr.status, codes) != -1) {
                        $.toggleOverlay(false);
                        $popup.hide();
                        $('div.body', $popup).empty();
                        if (cb && (false === cb(xhr, resp)))
                            // Don't fire event if callback returns false
                            return;
                        var done = $.Event('dialog:close');
                        $popup.trigger(done, [resp, status, xhr]);
                    } else {
                        try {
                            var json = $.parseJSON(resp);
                            if (json.redirect) return window.location.href = json.redirect;
                        }
                        catch (e) { }
                        $('div.body', $popup).html(resp);
                        $popup.effect('shake');
                        $('#msg_notice, #msg_error', $popup).delay(5000).slideUp();
                    }
                }
            })
            .done(function() {
                $('div#popup-loading', $popup).hide();
            })
            .fail(function() { });
            return false;
        });
     });
    if (options.onload) { options.onload(); }
 };

$.sysAlert = function (title, msg, cb) {
    var $dialog =  $('.dialog#alert');
    if ($dialog.length) {
        $.toggleOverlay(true);
        $('#title', $dialog).html(title);
        $('#body', $dialog).html(msg);
        $dialog.resize().show();
        if (cb)
            $dialog.find('input.ok.close').click(cb);
    } else {
        alert(msg);
    }
};

$.confirm = function(message, title, options) {
    title = title || __('Please Confirm');
    options = options || {};
    var D = $.Deferred(),
      $popup = $('.dialog#popup'),
      hide = function() {
          $.toggleOverlay(false);
          $popup.hide();
      };
      $('div#popup-loading', $popup).hide();
      var body = $('div.body', $popup).empty()
        .append($('<h3></h3>').text(title))
        .append($('<a class="close" href="#"><i class="icon-remove-circle"></i></a>'))
        .append($('<hr/>'))
        .append($('<p class="confirm-action"></p>')
            .text(message)
        ).append($('<div></div>')
            .append($('<b>').text(__('Please confirm to continue.')))
        );

      if (Object.keys(options).length)
          body.append('<hr>');
      $.each(options, function(k, v) {
        body.append($('<div>')
          .html('&nbsp;'+v)
          .prepend($('<input type="checkbox">')
            .attr('name', k)
          )
        );
      });

      body.append($('<hr style="margin-top:1em"/>'))
        .append($('<p class="full-width"></p>')
            .append($('<span class="buttons pull-left"></span>')
                .append($('<input type="button" class="close"/>')
                    .attr('value', __('Cancel'))
                    .click(function() { hide(); })
            )).append($('<span class="buttons pull-right"></span>')
                .append($('<input type="button"/>')
                    .attr('value', __('OK'))
                    .click(function() {  hide(); D.resolve(body.find('input').serializeArray()); })
        ))).append($('<div class="clear"></div>'));
    $.toggleOverlay(true);
    $popup.resize().show();
    return D.promise();
};

$.userLookup = function (url, cb) {
    $.dialog(url, 201, function (xhr) {
        var user = $.parseJSON(xhr.responseText);
        if (cb) return cb(user);
    }, {
        onshow: function() { $('#user-search').focus(); }
    });
};

$.orgLookup = function (url, cb) {
    $.dialog(url, 201, function (xhr) {
        var org = $.parseJSON(xhr.responseText);
        if (cb) cb(org);
    }, {
        onshow: function() { $('#org-search').focus(); }
    });
};

$.uid = 1;

// Tabs
$(document).on('click.tab', 'ul.tabs li a', function(e) {
    e.preventDefault();
    var $this = $(this),
        $ul = $(this).closest('ul'),
        $container = $('#'+$ul.attr('id')+'_container');
    if (!$container.length)
        $container = $ul.parent();

    var $tab = $($this.attr('href'), $container);
    if (!$tab.length && $(this).data('url').length > 1) {
        var url = $this.data('url');
        if (url.charAt(0) == '#')
            url = 'ajax.php/' + url.substr(1);
        $tab = $('<div>')
            .addClass('tab_content')
            .attr('id', $this.attr('href').substr(1)).hide();
        $container.append(
            $tab.load(url, function () {
                // TODO: Add / hide loading spinner
            })
         );
    }
    else {
        $tab.addClass('tab_content');
        $.changeHash($(this).attr('href'), true);
    }

    if ($tab.length) {
        $ul.children('li.active').removeClass('active');
        $(this).closest('li').addClass('active');
        $container.children('.tab_content').hide();
        $tab.fadeIn('fast');
        return false;
    }

});
$.changeHash = function(hash, quiet) {
  if (quiet) {
    hash = hash.replace( /^#/, '' );
    var fx, node = $( '#' + hash );
    if ( node.length ) {
      node.attr( 'id', '' );
      fx = $( '<div></div>' )
              .css({
                  position:'absolute',
                  visibility:'hidden',
                  top: $(document).scrollTop() + 'px'
              })
              .attr( 'id', hash )
              .appendTo( document.body );
    }
    document.location.hash = hash;
    if ( node.length ) {
      fx.remove();
      node.attr( 'id', hash );
    }
  }
  else {
    document.location.hash = hash;
  }
};

// Forms — submit, stay on same tab
$(document).on('submit', 'form', function() {
    $(this).attr('action', $(this).attr('action') + window.location.hash);
});

//Collaborators
$(document).on('click', 'a.collaborator, a.collaborators', function(e) {
    e.preventDefault();
    var url = 'ajax.php/'+$(this).attr('href').substr(1);
    $.dialog(url, 201, function (xhr) {
       $('input#emailcollab').show();
       $('#recipients').text(xhr.responseText);
       $('.tip_box').remove();
    }, {
        onshow: function() { $('#user-search').focus(); }
    });
    return false;
 });

// NOTE: getConfig should be global
getConfig = (function() {
    var dfd = $.Deferred(),
        requested = false;
    return function() {
        return dfd;
    };
})();

$(document).on('pjax:click', function(options) {
    // Release ticket lock (maybe)
    if ($.autoLock !== undefined)
        $.autoLock.releaseLock();
    // Stop all animations
    $(document).stop(false, true);

    // Remove tips and clear any pending timer
    $('.tip, .help-tips, .previewfaq, .preview').each(function() {
        if ($(this).data('timer'))
            clearTimeout($(this).data('timer'));
    });
    $('.tip_box, .typeahead.dropdown-menu').remove();
});

$(document).on('pjax:start', function() {
    // Cancel save-changes warning banner
    $(document).unbind('pjax:beforeSend.changed');
    $(window).unbind('beforeunload');
    // Close popups
    $('.dialog .body').empty().parent().hide();
    $.toggleOverlay(false);
    // Close tooltips
    $('.tip_box').remove();
    // Cancel refreshes
    clearInterval(window.ticket_refresh);
});

$(document).on('pjax:send', function(event) {

    if ($('#loadingbar').length !== 0) {
        $('#loadingbar').remove();
    }

    $("body").append("<div id='loadingbar'></div>");
    $("#loadingbar").addClass("waiting").append($("<dt/><dd/>"));

    // right
    $('#loadingbar').stop(false, true).width((50 + Math.random() * 30) + "%");
    $('#overlay').css('background-color','white');
    $.toggleOverlay(true);
});

$(document).on('pjax:complete', function() {
    // right
    $("#loadingbar").width("101%").delay(200).fadeOut(400, function() {
        $(this).remove();
    });
    $.toggleOverlay(false);
    $('#overlay').removeAttr('style');
});

// Enable PJAX for the staff interface
if ($.support.pjax) {
  $(document).on('click', 'a', function(event) {
    var $this = $(this);
    if (!$this.hasClass('no-pjax')
        && !$this.closest('.no-pjax').length
        && $this.attr('href')[0] != '#')
      $.pjax.click(event, {container: $this.data('pjaxContainer') || $('#pjax-container'), timeout: 2000});
  })
}

// Quick note interface
$(document).on('click.note', '.quicknote .action.edit-note', function() {
    var note = $(this).closest('.quicknote'),
        body = note.find('.body'),
        T = $('<textarea>').text(body.html());
    if (note.closest('.dialog, .tip_box').length)
        T.addClass('no-bar small');
    body.replaceWith(T);
    $.redact(T);
    $(T).redactor('focus');
    note.find('.action.edit-note').hide();
    note.find('.action.save-note').show();
    note.find('.action.cancel-edit').show();
    $('#new-note-box').hide();
    return false;
});
$(document).on('click.note', '.quicknote .action.cancel-edit', function() {
    var note = $(this).closest('.quicknote'),
        T = note.find('textarea'),
        body = $('<div class="body">');
    body.load('ajax.php/note/' + note.data('id'), function() {
      try { T.redactor('destroy'); } catch (e) {}
      T.replaceWith(body);
      note.find('.action.save-note').hide();
      note.find('.action.cancel-edit').hide();
      note.find('.action.edit-note').show();
      $('#new-note-box').show();
    });
    return false;
});
$(document).on('click.note', '.quicknote .action.save-note', function() {
    var note = $(this).closest('.quicknote'),
        T = note.find('textarea');
    $.post('ajax.php/note/' + note.data('id'),
      { note: T.redactor('get') },
      function(html) {
        var body = $('<div class="body">').html(html);
        try { T.redactor('destroy'); } catch (e) {}
        T.replaceWith(body);
        note.find('.action.save-note').hide();
        note.find('.action.cancel-edit').hide();
        note.find('.action.edit-note').show();
        $('#new-note-box').show();
      },
      'html'
    );
    return false;
});
$(document).on('click.note', '.quicknote .delete', function() {
  var that = $(this),
      id = $(this).closest('.quicknote').data('id');
  $.ajax('ajax.php/note/' + id, {
    type: 'delete',
    success: function() {
      that.closest('.quicknote').animate(
        {height: 0, opacity: 0}, 'slow', function() {
          $(this).remove();
      });
    }
  });
  return false;
});
$(document).on('click', '#new-note', function() {
  var note = $(this).closest('.quicknote'),
    T = $('<textarea>'),
    button = $('<input type="button">').val(__('Create'));
    button.click(function() {
      $.post('ajax.php/' + note.data('url'),
        { note: T.redactor('get'), no_options: note.hasClass('no-options') },
        function(response) {
          $(T).redactor('destroy').replaceWith(note);
          $(response).show('highlight').insertBefore(note.parent());
          $('.submit', note.parent()).remove();
        },
        'html'
      );
    });
    if (note.closest('.dialog, .tip_box').length)
        T.addClass('no-bar small');
    note.replaceWith(T);
    $('<p>').addClass('submit').css('text-align', 'center')
        .append(button).appendTo(T.parent());
    $.redact(T);
    $(T).redactor('focus');
    return false;
});

function __(s) {
  if ($.oststrings && $.oststrings[s])
    return $.oststrings[s];
  return s;
}

// Thanks, http://stackoverflow.com/a/487049
function addSearchParam(key, value) {
    key = encodeURI(key); value = encodeURI(value);

    var kvp = document.location.search.substr(1).split('&');
    var i=kvp.length; var x;
    while (i--) {
        x = kvp[i].split('=');
        if (x[0]==key) {
            x[1] = value;
            kvp[i] = x.join('=');
            break;
        }
    }
    if(i<0) {kvp[kvp.length] = [key,value].join('=');}

    //this will reload the page, it's likely better to store this until finished
    return kvp.join('&');
}
