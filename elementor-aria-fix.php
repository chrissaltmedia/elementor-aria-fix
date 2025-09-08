<?php
/**
 * Plugin Name: Elementor Loop Grid & Swiper ARIA Fix (No-Required-Children)
 * Description: Removes problematic ARIA roles (list/listbox/etc.) from Elementor Loop Grids & Swiper and applies safe roles that have no required-children to pass Lighthouse/axe.
 * Author: Salt Media LTD - Christopher Sheppard
 * Version: 1.0.4
 * License: GPL-2.0+
 */
if ( ! defined('ABSPATH') ) exit;

add_action('wp_footer', function () {
	if ( is_admin() || wp_doing_ajax() ) return;

	$uri = $_SERVER['REQUEST_URI'] ?? '';
	if ( strpos($uri, 'elementor-preview=') !== false ) return;

	$js = <<<'JS'
(function () {
  // --- utilities -------------------------------------------------------------
  const ready = (fn) => {
    if (document.readyState === 'loading') {
      // IMPORTANT: don't pass the event object to fn
      document.addEventListener('DOMContentLoaded', () => fn(), { once: true });
    } else {
      fn();
    }
  };

  const SELECTOR_CAROUSEL = 'div.swiper.elementor-loop-container.elementor-grid';
  const SELECTOR_WRAPPER  = '.swiper-wrapper';
  const SELECTOR_SLIDE    = '.swiper-slide';
  const BAD_ROLES         = new Set(['list','listbox','menu','menubar','tablist','tree','grid','table']);

  function stripBadRole(el) {
    const roles = (el.getAttribute('role') || '').toLowerCase().trim().split(/\s+/).filter(Boolean);
    if (roles.some(r => BAD_ROLES.has(r))) {
      el.removeAttribute('role');
    }
  }

  function applySafeCarouselRoles(root) {
    root.querySelectorAll(SELECTOR_CAROUSEL).forEach((carousel) => {
      stripBadRole(carousel);
      carousel.setAttribute('role', 'group');
      carousel.setAttribute('aria-roledescription', 'carousel');

      const wrapper = carousel.querySelector(SELECTOR_WRAPPER);
      if (wrapper) {
        stripBadRole(wrapper);
        wrapper.setAttribute('role', 'presentation');
        wrapper.removeAttribute('aria-roledescription');
      }

      const slides = carousel.querySelectorAll(SELECTOR_SLIDE);
      const total = slides.length;
      slides.forEach((slide, i) => {
        stripBadRole(slide);
        slide.setAttribute('role', 'group');
        slide.setAttribute('aria-roledescription', 'slide');
        slide.setAttribute('aria-label', 'Slide ' + (i + 1) + ' of ' + total);
      });

      carousel.querySelectorAll('span.swiper-notification').forEach((n) => {
        n.setAttribute('role', 'status');
        n.setAttribute('aria-live', 'polite');
        n.setAttribute('aria-atomic', 'true');
        n.removeAttribute('aria-roledescription');
      });
    });

    // Non-swiper loop grids: remove structural roles entirely
    root.querySelectorAll('div.elementor-loop-container.elementor-grid:not(.swiper)').forEach((grid) => {
      stripBadRole(grid);
      grid.removeAttribute('role');
      grid.removeAttribute('aria-roledescription');
      Array.from(grid.children).forEach(stripBadRole);
    });

    // Any stray notifications
    root.querySelectorAll('span.swiper-notification').forEach((n) => {
      n.setAttribute('role','status');
      n.setAttribute('aria-live','polite');
      n.setAttribute('aria-atomic','true');
      n.removeAttribute('aria-roledescription');
    });
  }

  function fixAll(root) {
    // Defensive: if an event or non-node slips in, default to document
    if (!root || typeof root.querySelectorAll !== 'function') root = document;
    applySafeCarouselRoles(root);
  }

  // Initial + after load (covers late Swiper init)
  ready(() => fixAll(document));
  window.addEventListener('load', () => fixAll(document));

  // Elementor lifecycle hooks
  document.addEventListener('elementor/frontend/init', function () {
    try {
      const fe = window.elementorFrontend;
      if (fe && fe.hooks) {
        const again = () => fixAll(document);
        fe.hooks.addAction('frontend/element_ready/global', again);
        fe.hooks.addAction('frontend/element_ready/loop-grid.default', again);
        fe.hooks.addAction('frontend/element_ready/posts.default', again);
        fe.hooks.addAction('frontend/element_ready/container', again);
      }
    } catch(e) {}
  }, { once:true });

  // MutationObserver: re-apply when roles change or nodes are added
  try {
    let scheduled = false;
    const schedule = () => {
      if (scheduled) return;
      scheduled = true;
      requestAnimationFrame(() => { scheduled = false; fixAll(document); });
    };
    const mo = new MutationObserver((muts) => {
      for (const m of muts) {
        if (m.type === 'childList' && m.addedNodes && m.addedNodes.length) { schedule(); break; }
        if (m.type === 'attributes' && m.attributeName === 'role') { schedule(); break; }
      }
    });
    mo.observe(document.documentElement, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['role']
    });
  } catch(e) {}
})();
JS;

	if ( function_exists('wp_print_inline_script_tag') ) {
		wp_print_inline_script_tag( $js );
	} else {
		echo "<script>{$js}</script>";
	}
}, 100);
