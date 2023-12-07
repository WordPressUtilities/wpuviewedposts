var wpupostviews_object = wpupostviews_object || {};

jQuery(document).ready(function wpupostviews_domready() {
    'use strict';

    /* Prevent counter action */
    if (wpupostviews_object.dntviews == '1') {
        return;
    }

    /* Block bots */
    if (wpupostviews_object.no_bots == '1' && /bot|crawl|spider|slurp|curl|baidu/i.test(navigator.userAgent)) {
        return;
    }

    /* Test for cookies */
    if (wpupostviews_object.use_cookie == '1') {
        var cookieObj = new wpuPostViewsCookies(),
            cookie_id = 'wpupostviewscookie_' + wpupostviews_object.post_id;
        if (cookieObj.readCookie(cookie_id) == '1') {
            return;
        }
        cookieObj.createCookie(cookie_id, '1', wpupostviews_object.cookie_days);
    }


    (function(wpupostviews_object){
        setTimeout(function(){
            /* Count a view */
            jQuery.post(wpupostviews_object.ajax_url, {
                'action': 'wpupostviews_track_view',
                'date': Date.now(),
                'post_id': wpupostviews_object.post_id,
            }, function() {});
        },100);
    }(wpupostviews_object));
});

/* Source : http://ppk.developpez.com/tutoriels/javascript/gestion-cookies-javascript/ */
var wpuPostViewsCookies = function() {
    'use strict';
    var self = this;
    self.createCookie = function(name, value, days) {
        var expires = "";
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toGMTString();
        }
        document.cookie = name + "=" + value + expires + "; path=/";
    };

    self.readCookie = function(name) {
        var nameEQ = name + "=";
        var ca = document.cookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) == ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) {
                return c.substring(nameEQ.length, c.length);
            }
        }
        return null;
    };
};
