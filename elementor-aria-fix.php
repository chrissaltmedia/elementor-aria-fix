<?php
/**
 * Plugin Name: Elementor Loop Grid & Swiper ARIA Fix
 * Description: Normalises ARIA roles for Elementor Loop Grids and Swiper carousels to satisfy Lighthouse/axe “required children” checks. Front end only.
 * Author: Salt Media LTD - Christopher Sheppard
 * Version: 1.0.1
 * License: GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Print a single inline script in the footer; no dummy <script src=""> tags.
add_action('wp_footer', function () {
	if ( is_admin() || wp_doing_ajax() ) return;

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
    items.forEach((item) => item.setAttribute('role', 'listitem'));
  }

  function fixAll(root = document) {
    root.querySelectorAll('div.swiper.elementor-loop-container.elementor-grid').forEach(labelSlidesAsListitems);
    root.querySelectorAll('div.elementor-loop-container.elementor-grid:not(.swiper)').forEach(normalisePlainLoopGrid);
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

  // MutationObserver for dynamic changes (AJAX filters, lazy init). Keep it lightweight.
  try {
    let scheduled = false;
    const scheduleFix = () => {
      if (scheduled) return;
      scheduled = true;
      requestAnimationFrame(() => { scheduled = false; fixAll(document); });
    };
    const mo = new MutationObserver((muts) => {
      for (const m of muts) {
        if (m.addedNodes && m.addedNodes.length) { scheduleFix(); break; }
      }
    });
    mo.observe(document.documentElement, { childList: true, subtree: true });
  } catch (e) {}
})();
JS;

	// Print safely (WP 6.3+). If you're on older WP, echo the <script> tag instead.
	if ( function_exists('wp_print_inline_script_tag') ) {
		wp_print_inline_script_tag( $js );
	} else {
		echo "<script>{$js}</script>";
	}
}, 100);
