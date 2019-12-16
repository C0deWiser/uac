function oauth(url, callback) {
    let popup = window.open(url, 'oauth', 'height=600,width=400,dialog=yes');
    if (callback)
        var interval = setInterval(function () {
            if (popup.closed) {
                clearInterval(interval);
                callback();
            }
        }, 200);
    return popup;
};
