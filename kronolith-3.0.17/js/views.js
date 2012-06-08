var eventTabs = null;
var Views = {

    hash: $H(),
    keys: [],

    get: function(key)
    {
        return this.hash.get(key);
    },

    push: function(key, val)
    {
        this.hash.set(key, val);
        this.keys.push(key);
        if (this.hash.size() > 10) {
            this.hash.unset(this.keys.pop());
        }
    },

    invalidate: function()
    {
        this.hash = $H();
        this.keys = [];
    }

};

function ShowView(view, date, cache)
{
    if (typeof Ajax.Updater == 'undefined') {
        return true;
    }

    if (Object.isUndefined(cache)) {
        cache = true;
    }

    // Build the request URL for later use, and as a hash key.
    var params = $H({ view: view });
    if (typeof date == 'object') {
        params.update(date);
    } else {
        params.set('date', date);
    }

    var url = KronolithVar.view_url + (KronolithVar.view_url.include('?') ? '&' : '?') + params.toQueryString();

    // Look for cached views.
    if (Views.get(url)) {
        $('page').update(Views.get(url));
        _ShowView();
    } else {
        // Add the Loading ... notice.
        $('page').appendChild(new Element('DIV', { id: 'pageLoading' }).update(KronolithText.loading));
        $('pageLoading').clonePosition('page');
        new Effect.Opacity('pageLoading', { from: 0.0, to: 0.5 });

        // Update the page div.
        if (cache) {
            new Ajax.Updater('page', url, { onComplete: function() { Views.push(url, $('page').innerHTML); _ShowView(); } });
        } else {
            new Ajax.Updater('page', url, { onComplete: _ShowView });
        }
    }

    return false;
}

function _ShowView()
{
    if (Horde && Horde.stripeAllElements) {
        Horde.stripeAllElements();
    }
    if (typeof Horde_ToolTips != 'undefined') {
        Horde_ToolTips.out();
        Horde_ToolTips.attachBehavior();
    }

    var titleDiv = $('view_title');
    if (titleDiv && titleDiv.firstChild && titleDiv.firstChild.nodeValue) {
        document.title = KronolithVar.page_title + titleDiv.firstChild.nodeValue;
    }

    var viewVars = $('view_vars');
    if (viewVars) {
        KronolithView = viewVars.readAttribute('view');
        KronolithDate = new Date(viewVars.readAttribute('date'));
    }
}

function ShowTab(tab)
{
    if (eventTabs == null) {
        eventTabs = $('page').select('.tabset ul li');
    }

    eventTabs.each(function(c) {
        var t = $(c.id.substring(3));
        if (!t) {
            return;
        }
        if (c.id == 'tab' + tab) {
            c.addClassName('activeTab');
            t.show();
        } else {
            c.removeClassName('activeTab');
            t.hide();
        }
    });

    return false;
}
