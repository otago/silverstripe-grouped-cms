<ul class="cms-menu__list branded-menu" $GroupedMenuAttributes>
  <% if $BrandedMainMenu %>
    <% loop $BrandedMainMenu %>
      <li
        class="branded-menu__list-item {$LinkingMode} {$FirstLast}<% if $Children %> branded-menu__list-item--children children<% if $LinkingMode == 'current' %> opened<% end_if %><% end_if %><% if $MenuDivider %> branded-menu__list-item--divider<% end_if %>"
        id="Menu-{$Code}"
        title="{$Title.ATT}"
        <% if $Children %>data-group="{$Code.ATT}"<% end_if %>
        <% if $MenuHeading %>data-menu-heading="{$MenuHeading.ATT}"<% end_if %>
        <% if $AlsoActivePaths %>data-also-active="{$AlsoActivePaths.ATT}"<% end_if %>
      >
        <a href="{$Link}" $AttributesHTML<% if $Children %> aria-expanded="<% if $LinkingMode == 'current' %>true<% else %>false<% end_if %>" aria-controls="Menu-{$Code}-children"<% end_if %>>
          <% if $Children %>
            <span class="menu__icon {$Icon}"></span>
            <span class="text">{$Title}</span>
            <% if $Badge %><span class="branded-menu__badge<% if $BadgeTone %> branded-menu__badge--{$BadgeTone}<% end_if %>">{$Badge}</span><% end_if %>
            <span class="menu__icon menu__icon--chevron font-icon-right-open"></span>
          <% else %>
            <% if $IconClass %>
              <span class="menu__icon {$IconClass}"></span>
            <% else_if $HasCSSIcon %>
              <span class="menu__icon icon icon-16 icon-{$Icon}">&nbsp;</span>
            <% else %>
              <span class="menu__icon font-icon-circle-star">&nbsp;</span>
            <% end_if %>
            <span class="text">{$Title}</span>
            <% if $Badge %><span class="branded-menu__badge<% if $BadgeTone %> branded-menu__badge--{$BadgeTone}<% end_if %>">{$Badge}</span><% end_if %>
          <% end_if %>
        </a>
        <% if $Children %>
          <ul class="branded-menu branded-menu--child" id="Menu-{$Code}-children">
            <% loop $Children %>
              <li class="branded-menu__list-item $LinkingMode $FirstLast" id="Menu-{$Code}"<% if $AlsoActivePaths %> data-also-active="{$AlsoActivePaths.ATT}"<% end_if %>>
                <a href="{$Link}" $AttributesHTML>
                  <% if $IconClass %>
                    <span class="menu__icon {$IconClass}"></span>
                  <% else_if $HasCSSIcon %>
                    <span class="menu__icon icon icon-16 icon-{$Icon}">&nbsp;</span>
                  <% else %>
                    <span class="menu__icon font-icon-circle-star">&nbsp;</span>
                  <% end_if %>
                  <span class="text"><% if $SectionTitle %>$SectionTitle<% else %>$Title<% end_if %></span>
                  <% if $Badge %><span class="branded-menu__badge<% if $BadgeTone %> branded-menu__badge--{$BadgeTone}<% end_if %>">{$Badge}</span><% end_if %>
                </a>
              </li>
            <% end_loop %>
          </ul>
        <% end_if %>
      </li>
    <% end_loop %>
  <% end_if %>
</ul>
