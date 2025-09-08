<?php
/**
 * Plugin Name: Elementor Loop Grid & Swiper ARIA Fix
 * Description: Normalises ARIA roles for Elementor Loop Grids and Swiper carousels to satisfy Lighthouse/axe “required children” checks. Front end only.
 * Author: Salt Media LTD - Christopher Sheppard
 * Version: 1.0.0
 * License: GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action('wp_enqueue_scripts', function () {

    if ( is_admin() ) return;

    // Footer script, no external file, tiny + self-contained
    wp_register_script('sm-elementor-aria-fix', '', [], '1.0.0', true);

    $js = <<<'JS'
(function () {
  const ready = (fn) => {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn, { once: true });
    } else {
      fn();
    }
  };

  function normaliseLiveRegion(root = document) {
    root.querySelectorAll('span.swiper-notification').forEach((el) => {
      el.setAttribute('aria-live', 'polite');
      el.setAttribute('role', 'status');
      el.setAttribute('aria-atomic', 'true');
    });
  }

  function labelSlidesAsListitems(carousel) {
    const wrapper = carousel.querySelector('.swiper-wrapper');
    if (!wrapper) return;

    const slides = wrapper.querySelectorAll('.swiper-slide');
    const count = slides.length;

    carousel.setAttribute('role', 'list');
    carousel.setAttribute('aria-roledescription', 'carousel');

    wrapper.setAttribute('role', 'presentation');

    slides.forEach((slide, i) => {
      slide.setAttribute('role', 'listitem');
      slide.setAttribute('aria-roledescription', 'slide');
      slide.setAttribute('aria-label', 'Slide ' + (i + 1) + ' of ' + count);
    });
  }

  function normalisePlainLoopGrid(container) {
    container.setAttribute('role', 'list');
    container.removeAttribute('aria-roledescription');

    const items = Array.from(container.children).filter((el) => el.nodeType === 1);
    items.forEach((item) => {
      item.setAttribute('role', 'listitem');
    });
  }

  function fixAll(root = document) {
    root.querySelectorAll('div.swiper.elementor-loop-container.elementor-grid').forEach((carousel) => {
      labelSlidesAsListitems(carousel);
    });

    root.querySelectorAll('div.elementor-loop-container.elementor-grid:not(.swiper)').forEach((container) => {
      normalisePlainLoopGrid(container);
    });

    normaliseLiveRegion(root);
  }

  // Initial pass
  ready(fixAll);

  // Elementor lifecycle hooks (if present)
  document.addEventListener('elementor/frontend/init', function () {
    try {
      const frontend = window.elementorFrontend;
      if (frontend && frontend.hooks) {
        frontend.hooks.addAction('frontend/element_ready/global', fixAll);
        frontend.hooks.addAction('frontend/element_ready/loop-grid.default', fixAll);
        frontend.hooks.addAction('frontend/element_ready/posts.default', fixAll);
        frontend.hooks.addAction('frontend/element_ready/container', fixAll);
      }
    } catch (e) {}
  }, { once: true });

  // Catch dynamic DOM changes (AJAX filters, lazy init, etc.)
  try {
    const mo = new MutationObserver((muts) => {
      for (const m of muts) {
        if (m.addedNodes && m.addedNodes.length) {
          m.addedNodes.forEach((n) => {
            if (n.nodeType === 1) fixAll(n);
          });
        }
        if (m.type === 'attributes' && (m.attributeName === 'class' || m.attributeName === 'aria-live' || m.attributeName === 'role')) {
          fixAll(document);
        }
      }
    });
    mo.observe(document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'role', 'aria-live'] });
  } catch (e) {}
})();
JS;

    wp_add_inline_script('sm-elementor-aria-fix', $js);
    wp_enqueue_script('sm-elementor-aria-fix');

}, 20);
