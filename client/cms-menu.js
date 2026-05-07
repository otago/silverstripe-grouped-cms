(function ($) {
  'use strict';

  var _syncTimer = null;
  var _observer = null;

  // ── Core sync ──────────────────────────────────────────────────────────────
  // Pass 1: .current in DOM (set by SS on render / PJAX class update).
  //         SS sets .current on the group <li> itself during PJAX, and on
  //         both group + child <li>s on initial HTTP render.
  // Pass 2: URL-path matching — always runs to find the active child link.
  //         Also fills activeGroup when Pass 1 found nothing.
  // Child .current: SS sets it on HTTP render but NOT on PJAX, so we
  //         mirror it via URL matching every time we sync.

  function syncOpenedToActive() {
    var $menu = $('.cms-menu:not(.collapsed) .branded-menu');
    if (!$menu.length) return;

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
          try {
            var linkPath = new URL(this.href, window.location.origin).pathname;
            if (pathname.startsWith(linkPath) && linkPath.length > bestLen) {
              bestLen = linkPath.length;
              activeChild = $(this).closest('.branded-menu__list-item')[0];
              if (!activeGroup) {
                activeGroup = $(this).closest('.branded-menu__list-item--children')[0];
              }
            }
          } catch (e) {
            /* invalid href — skip */
          }
        });
    });

    if (!activeGroup) return;

    // Sync accordion open state.
    $groups.each(function () {
      $(this).toggleClass('opened', this === activeGroup);
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
    // replaceState — SS uses this for every PJAX URL update (confirmed via diagnostics).
    // Wrapping it means we fire only on actual navigation, not background XHR.
    var origReplace = history.replaceState.bind(history);
    history.replaceState = function (state, title, url) {
      origReplace(state, title, url);
      scheduleSync();
    };

    // popstate — back / forward.
    window.addEventListener('popstate', scheduleSync);

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
  });
})(jQuery);
