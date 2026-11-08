(function ($) {
  'use strict';

  var _syncTimer = null;
  var _observer = null;
  var _hooksAttached = false;
  var STORAGE_KEY = 'grouped-cms-menu-open';

  // ── Options (data attributes on the menu <ul>, set from YAML config) ──────
  // data-segment-matching: a link matches the current URL only on a "/" boundary.
  // data-remember-open:    groups opened by hand stay open and are remembered.

  function menuOption($menu, name) {
    return $menu.first().attr('data-' + name) === '1';
  }

  // ── Remembered open groups (localStorage can throw or be empty) ───────────

  function readOpen() {
    try {
      var raw = window.localStorage.getItem(STORAGE_KEY);
      var list = raw ? JSON.parse(raw) : [];
      return Array.isArray(list) ? list : [];
    } catch (e) {
      return [];
    }
  }

  function writeOpen(list) {
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(list));
    } catch (e) {
      /* storage unavailable — the state lasts until the next page load */
    }
  }

  function rememberGroup(code, open) {
    if (!code) return;
    var list = readOpen().filter(function (c) {
      return c !== code;
    });
    if (open) list.push(code);
    writeOpen(list);
  }

  function setOpened(group, open) {
    var $g = $(group);
    $g.toggleClass('opened', open);
    $g.children('a').first().attr('aria-expanded', open ? 'true' : 'false');
  }

  // ── URL matching ──────────────────────────────────────────────────────────

  function pathMatches(pathname, linkPath, segmentMatching) {
    if (!segmentMatching) return pathname.startsWith(linkPath);
    var base = linkPath.replace(/\/+$/, '') + '/';
    return (pathname.replace(/\/+$/, '') + '/').startsWith(base);
  }

  // ── Core sync ──────────────────────────────────────────────────────────────
  // Pass 1: .current in DOM (set by SS on render / PJAX class update).
  //         SS sets .current on the group <li> itself during PJAX, and on
  //         both group + child <li>s on initial HTTP render.
  // Pass 2: URL-path matching — always runs to find the active child link.
  //         Also fills activeGroup when Pass 1 found nothing. A child's
  //         data-also-active paths count as its own.
  // Child .current: SS sets it on HTTP render but NOT on PJAX, so we
  //         mirror it via URL matching every time we sync.

  function syncOpenedToActive() {
    var $menu = $('.cms-menu:not(.collapsed) .branded-menu');
    if (!$menu.length) return;

    var segmentMatching = menuOption($menu, 'segment-matching');
    var rememberOpen = menuOption($menu, 'remember-open');
    var $groups = $menu.find('.branded-menu__list-item--children');
    var activeGroup = null;
    var activeChild = null;
    var pathname = window.location.pathname;
    var bestLen = 0;

    // Pass 1: check SS-managed .current on the group element.
    $groups.each(function () {
      var $g = $(this);
      if ($g.hasClass('current') || $g.find('.branded-menu__list-item.current').length) {
        activeGroup = this;
        return false;
      }
    });

    // Pass 2: URL matching — runs unconditionally to resolve the active child.
    $groups.each(function () {
      $(this)
        .find('.branded-menu--child a[href]')
        .each(function () {
          var link = this;
          var $item = $(link).closest('.branded-menu__list-item');
          var paths = [];
          try {
            paths.push(new URL(link.href, window.location.origin).pathname);
          } catch (e) {
            /* invalid href — skip */
          }
          ($item.attr('data-also-active') || '').split(/\s+/).forEach(function (p) {
            if (!p) return;
            try {
              // Relative to <base>, as the menu's own links are.
              paths.push(new URL(p, document.baseURI).pathname);
            } catch (e) {
              /* invalid path — skip */
            }
          });
          paths.forEach(function (linkPath) {
            if (pathMatches(pathname, linkPath, segmentMatching) && linkPath.length > bestLen) {
              bestLen = linkPath.length;
              activeChild = $item[0];
              if (!activeGroup) {
                activeGroup = $(link).closest('.branded-menu__list-item--children')[0];
              }
            }
          });
        });
    });

    // Sync accordion open state: the active group is open. Without remember-open the
    // others close (an accordion); with it, groups opened by hand stay as they were.
    var remembered = rememberOpen ? readOpen() : [];
    $groups.each(function () {
      if (this === activeGroup) {
        setOpened(this, true);
      } else if (rememberOpen) {
        if (remembered.indexOf($(this).attr('data-group')) !== -1) setOpened(this, true);
      } else if (activeGroup) {
        setOpened(this, false);
      }
    });

    // Sync child .current — SS only sets it on HTTP render, not on PJAX.
    // Use URL matching to fill the gap whenever we have a match.
    if (bestLen > 0) {
      $menu.find('.branded-menu--child .branded-menu__list-item').removeClass('current');
      if (activeChild) {
        $(activeChild).addClass('current');
      }
    }
  }

  function scheduleSync() {
    clearTimeout(_syncTimer);
    // 300 ms gives SS time to update .current after replaceState
    _syncTimer = setTimeout(syncOpenedToActive, 300);
  }

  function addCollapsedTooltips() {
    $(
      '.cms-menu .branded-menu__list-item > a, ' +
        '.cms-menu .branded-menu__list-item--children > a:first-child'
    ).each(function () {
      var $link = $(this);
      if ($link.attr('title')) return;
      var label = $link.find('.text').text().trim();
      if (label) $link.attr('title', label);
    });
  }

  function attachNavigationHooks() {
    if (_hooksAttached) return;
    _hooksAttached = true;

    // replaceState — SS uses this for every PJAX URL update (confirmed via diagnostics).
    // Wrapping it means we fire only on actual navigation, not background XHR.
    var origReplace = history.replaceState.bind(history);
    history.replaceState = function (state, title, url) {
      origReplace(state, title, url);
      scheduleSync();
    };

    // popstate — back / forward.
    window.addEventListener('popstate', scheduleSync);
  }

  function observeMenu() {
    // MutationObserver — belt-and-suspenders: fires precisely when SS toggles
    // .current in the DOM, regardless of replaceState timing.
    var menuEl = document.getElementById('cms-menu');
    if (menuEl && !_observer) {
      _observer = new MutationObserver(function (mutations) {
        var relevant = mutations.some(function (m) {
          if (m.type !== 'attributes' || m.attributeName !== 'class') return false;
          // SS sets .current on group <li>s during PJAX, never on child items.
          // Ignore child-item class changes (those are managed by us) to avoid loops.
          if (!m.target.classList.contains('branded-menu__list-item--children')) return false;
          var oldHad = (m.oldValue || '').split(/\s+/).indexOf('current') !== -1;
          return oldHad !== m.target.classList.contains('current');
        });
        if (relevant) scheduleSync();
      });
      _observer.observe(menuEl, {
        subtree: true,
        attributes: true,
        attributeFilter: ['class'],
        attributeOldValue: true,
      });
    }
  }

  // ── entwine ────────────────────────────────────────────────────────────────

  $.entwine('ss', function ($) {
    $('#cms-menu').entwine({
      onmatch: function () {
        syncOpenedToActive();
        addCollapsedTooltips();
        attachNavigationHooks();
        observeMenu();
        this._super();
      },
      onunmatch: function () {
        if (_observer) {
          _observer.disconnect();
          _observer = null;
        }
        this._super();
      },
    });

    // A group header expands and collapses its group. The CMS's own menu click handler
    // would navigate to the header's link (the first child) instead, so this more specific
    // rule takes the click. In the collapsed icon rail the children cannot show, so there
    // the header still opens the first child.
    $('.cms-menu__list li.branded-menu__list-item--children > a').entwine({
      onclick: function (e) {
        if (this.closest('.cms-menu').hasClass('collapsed')) {
          this._super(e);
          return;
        }
        if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey) {
          // Modifier or middle click: let the browser open the link in a new tab.
          return;
        }
        e.preventDefault();
        var $group = this.parent();
        var open = !$group.hasClass('opened');
        setOpened($group[0], open);
        if (this.closest('.branded-menu').attr('data-remember-open') === '1') {
          rememberGroup($group.attr('data-group'), open);
        }
      },
    });
  });
})(jQuery);
