jQuery(document).ready(function($) {

    /* Block bots */
    if (ajax_object.no_bots == '1') {
        if (/bot|googlebot|crawler|spider|robot|crawling|baidu/i.test(navigator.userAgent)) {
            return;
        }
    }

    /* Test for cookies */
    if (ajax_object.use_cookie == '1') {
        var cookieObj = new wpuPostViewsCookies(),
            cookie_id = 'wpupostviewscookie_' + ajax_object.post_id;
        if (cookieObj.readCookie(cookie_id) == '1') {
            return;
        }
        cookieObj.createCookie(cookie_id, '1', ajax_object.cookie_days);
    }

    /* Count a view */
    jQuery.post(ajax_object.ajax_url, {
        'action': 'wpupostviews_track_view',
        'date': Date.now(),
        'post_id': ajax_object.post_id,
    }, function(response) {});
});

/* Source : http://ppk.developpez.com/tutoriels/javascript/gestion-cookies-javascript/ */
var wpuPostViewsCookies = function() {
    var self = this;
    self.createCookie = function(name, value, days) {
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            var expires = "; expires=" + date.toGMTString();
        }
        else var expires = "";
        document.cookie = name + "=" + value + expires + "; path=/";
    };

    self.readCookie = function(name) {
        var nameEQ = name + "=";
        var ca = document.cookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) == ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    };

    self.eraseCookie = function(name) {
        createCookie(name, "", -1);
    };
}