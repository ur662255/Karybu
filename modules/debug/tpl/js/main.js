(function($) {

    $(function() {

        $(document).keydown(function(e) {
            if (e.which == '123' /*F12*/ && e.ctrlKey && e.altKey && e.shiftKey) {
                $.dToolbar('toggle');
            }
        });

        if (typeof(_isPoped) !== 'undefined') { // make it minimized by default when in a popup
            $.dToolbar('changeState', 'closed', true);
        } else {
            if ($.dToolbar().data('state')) {
                $.dToolbar('changeState', $.dToolbar().data('state'), true);
            } else {
                $.dToolbar('changeState', 'full');
            }
        }

        $.dToolbar('toggleIcon').on('click', function() {
            if ($.dToolbar('isUp')) $.dToolbar('minimize');
            else $.dToolbar('maximize');
        });

        $.dToolbar('closeIcon').on('click', function() {
            $.dToolbar('close');
        });

        $.dToolbar('tabs').on('click', function(e) {
            var index = $(this).data('debug-index');
            var contentElement = $.dToolbar('getContent', index);
            if (!contentElement.is(':visible')) {
                $.dToolbar('showTab', $(this));
                $.dToolbar('maximize');
            }
            e.preventDefault()
        });

        var mustBeShown;
        if (!$.dToolbar('tabs').hasClass('active')) {
            mustBeShown = $.dToolbar('tab', 0)
        } else {
            mustBeShown = $.dToolbar('tabs').filter('.active');
        }
        $.dToolbar('showTab', mustBeShown, true);

        $('.queries span.time', $.dToolbar()).stampToTime();

        $.dToolbar('closedButton').on('click', function(){
            $.dToolbar('maximize');
        });

        // click on php error line
        $('.php_errors li > p, .failed_queries li > p', $.dToolbar()).on('click', function() {
            $(this).siblings('.error_description').toggle();
        });

        // show Ctrl+C prompt on click on value
        $('.php_errors li li span', $.dToolbar()).on('click', function() {
            var txt = $(this).parent().children('.value').text();
            if (txt.length > 3) {
                prompt("Copy to clipboard: Ctrl+C, Enter", txt);
            }
        });
        $('.failed_queries .error_description li', $.dToolbar()).on('click', function(){
            var txt = $(this).find('.value').text().replace(/^\s+|\s+$/g, '');
            if (txt.length > 3) {
                prompt("Copy to clipboard: Ctrl+C, Enter", txt);
            }
        });

        // add tooltips
        if($.fn.tooltip){
            $('.queries li', $.dToolbar()).each(function(){
                var title = $(this).attr('title', '').attr('title');
                //title += $('.meta.time', $(this)).text() + "\n";
                //title += $('.meta.elapsed_time', $(this)).text() + "\n";
                title += $('.meta.query_name', $(this)).text();
                $(this).attr('title', title);
            }).tooltip();
            $('.failed_queries li').tooltip();
        }
    });

    var methods = {
        get : function( options ) {
            return $('#debug-toolbar');
        },
        tab : function( i ) {
            var tabs = $.dToolbar('tabs');
            return i != null ? tabs.eq(i) : tabs;
        },
        tabs : function() {
            return $('#debug-tabs', $.dToolbar()).find('li');
        },
        tabContent : function( i ) {

        },
        getActiveTab : function() {
            var tabs = $.dToolbar('tabs');
            return tabs.find('li.active');
        },
        content : function() {
            return $('.debug-content', $.dToolbar());
        },
        statusBar: function() {
            return $('.status', $.dToolbar());
        },
        toggleIcon : function() {
            return $('a.toggle', $.dToolbar());
        },
        closeIcon : function() {
            return $('a.hide', $.dToolbar());
        },
        isUp : function() {
            return $.dToolbar('content').is(':visible');
        },
        isMinimized : function() {
            return !$.dToolbar('isUp') && $.dToolbar('tabs').is(':visible');
        },
        getContent : function(index) {
            return $('#debug_tab_' + index, $.dToolbar());
        },
        closedButton : function() {
            return $.dToolbar().next('.bottom-sticky').show();
        },
        checkBottomPadding : function() {
            if ($.dToolbar().data('padding-added') == 'da') {
                $('body').css('padding-bottom', '-=' + $('.debug-nav', $.dToolbar()).height() + 'px');
                $.dToolbar().removeData('padding-added');
            }
            else {
                $.dToolbar().data('padding-added', 'da');
                $('body').css('padding-bottom', '+=' + $('.debug-nav', $.dToolbar()).height() + 'px');
            }
        },

        msg1 : function(msg) {
            var container = $('.debug-nav p.pull-right', $.dToolbar());
            if (typeof(msg) == 'string') {
                container.text(msg);
            }
            return container;
        },
        msg2 : function(msg) {
            var container = $('div.status > p', $.dToolbar());
            if (typeof(msg) == 'string') {
                container.text(msg).show();
            }
            return container;
        },

        toggle : function() {
            if ($.dToolbar('isUp')) return $.dToolbar('minimize');
            if ($.dToolbar('isMinimized')) return $.dToolbar('close');
            return $.dToolbar('maximize');
        },
        minimize : function(noAjax) {
            $.dToolbar('content').hide();
            $.dToolbar('statusBar').hide();
            $.dToolbar('toggleIcon').removeClass('up');
            $.dToolbar('checkBottomPadding');
            if (!noAjax) {
                $.dToolbar('ajax', { state: 'minimized' });
            }
            return $.dToolbar();
        },
        maximize : function() {
            $.dToolbar('content').show();
            $.dToolbar('statusBar').show();
            $.dToolbar('toggleIcon').addClass('up');
            $.dToolbar('ajax', { state: 'full' });
            $.dToolbar('closedButton').hide();
            return $.dToolbar().show();
        },
        close : function(noAjax) {
            $.dToolbar('checkBottomPadding');
            $.dToolbar('closedButton').show();
            if (!noAjax) {
                $.dToolbar('ajax', { state: 'closed' });
            }
            return $.dToolbar().hide();
        },
        setTabActive : function(tab) {
            $.dToolbar('tabs').removeClass('active');
            tab.addClass('active');
        },
        showTab : function(tab, noAjax) {
            $.dToolbar('setTabActive', tab);
            contentElement = $('#debug_tab_' + tab.data('debug-index'), $.dToolbar());
            $('.tab-content', $.dToolbar()).hide();
            contentElement.show();
            $.dToolbar('msg1', '');
            $.dToolbar('msg2', '');
            $('.message', contentElement).each(function(){
                var messageType;
                if ($(this).hasClass('top')) {
                    $.dToolbar('msg1', $(this).text());
                }
                else if ($(this).hasClass('bottom')) {
                    $.dToolbar('msg2', $(this).text());
                }
            });
            if (!noAjax) {
                $.dToolbar('ajax', { tab: tab.data('debug-index') });
            }
        },
        changeState : function(state, noAjax) {
            if (state == 'full') {
                $.dToolbar().show();
            }
            else if (state == 'minimized') {
                $.dToolbar('minimize', noAjax).show();
            }
            else if (state == 'closed') {
                $.dToolbar('close', noAjax);
            }
            else $.error('wrong state ' + state);
            return $.dToolbar();
        },

        ajax : function(data) {
            $.exec_json('debug.procDebugSaveToolbarSettings', data, function(ret) {
                if (ret.message != 'success') {
                    $.dToolbar('msg1', ret.message);
                }
            });
        }
    };

    $.dToolbar = function( method ) {
        // Method calling logic
        if ( methods[method] ) {
            return methods[ method ].apply( this, Array.prototype.slice.call( arguments, 1 ));
        } else if ( typeof method === 'object' || ! method ) {
            return methods.get.apply( this, arguments );
        } else {
            return $.error( 'Method ' +  method + ' does not exist on jQuery.dToolbar' );
        }
    };

    $.fn.stampToTime = function() {
        return this.each(function(){
            var timestamp = $(this).text();
            var time = new Date(timestamp * 1000);
            var pad = '00';
            var n = time.getHours();
            var h = (pad+n).slice(-pad.length);
            n = time.getMinutes();
            var m = (pad+n).slice(-pad.length);
            n = time.getSeconds();
            var s = (pad+n).slice(-pad.length);
            n = time.setMilliseconds(2);
            var ms = (pad+n).slice(-pad.length);
            $(this).text(h + ':' + m + ':' + s + ':' + ms);
        });
    }

})(jQuery);