(function ($) {
  function runScrollAnimations() {
    const viewportHeight = $(window).height();
    const scrollTop = $(window).scrollTop();

    $('.animation').each(function () {
      const $element = $(this);
      const elementTop = $element.offset().top;
      const animationClass = $element.attr('data-animation');
      const delay = $element.attr('data-delay');

      if (!animationClass || elementTop >= scrollTop + viewportHeight - 10) {
        return;
      }

      if (delay) {
        $element.css('animation-delay', delay);
      }

      if (!$element.hasClass(animationClass)) {
        $element.addClass(animationClass);
      }
    });
  }

  function initScrollAnimations() {
    runScrollAnimations();
    $(window).on('scroll resize', runScrollAnimations);
  }

  function initHeaderScrollState() {
    const header = document.querySelector('header[role="banner"]');

    if (!header) {
      return;
    }

    const forceScrolled = header.classList.contains('is-scrolled');
    let isTicking = false;

    const syncHeaderState = () => {
      header.classList.toggle(
        'is-scrolled',
        forceScrolled || window.scrollY > 0,
      );
      isTicking = false;
    };

    const requestHeaderSync = () => {
      if (isTicking) {
        return;
      }

      isTicking = true;
      window.requestAnimationFrame(syncHeaderState);
    };

    syncHeaderState();
    window.addEventListener('scroll', requestHeaderSync, { passive: true });
    window.addEventListener('resize', requestHeaderSync);
  }

  function initMenuToggle() {
    const menuToggle = document.getElementById('menuToggle');
    const header = menuToggle ? menuToggle.closest('header') : null;

    if (!menuToggle || !header) {
      return;
    }

    menuToggle.addEventListener('click', () => {
      const isOpen = header.classList.toggle('menu-open');

      menuToggle.setAttribute('aria-expanded', String(isOpen));
      menuToggle.setAttribute(
        'aria-label',
        isOpen ? 'Close navigation' : 'Open navigation',
      );
    });
  }

  function getNextTabIndex(eventKey, currentIndex, total) {
    if (eventKey === 'ArrowRight') {
      return (currentIndex + 1) % total;
    }

    if (eventKey === 'ArrowLeft') {
      return (currentIndex - 1 + total) % total;
    }

    if (eventKey === 'Home') {
      return 0;
    }

    if (eventKey === 'End') {
      return total - 1;
    }

    return null;
  }

  function initTabGroup(groupElement, config) {
    const triggers = Array.from(
      groupElement.querySelectorAll(config.triggerSelector),
    );
    const panels = Array.from(
      groupElement.querySelectorAll(config.panelSelector),
    );

    if (!triggers.length || !panels.length) {
      return;
    }

    const activateTab = target => {
      triggers.forEach(trigger => {
        const isActive = trigger.dataset[config.triggerKey] === target;

        trigger.classList.toggle('is-active', isActive);
        trigger.setAttribute('aria-selected', String(isActive));
        trigger.setAttribute('tabindex', isActive ? '0' : '-1');
      });

      panels.forEach(panel => {
        const panelValue = panel.dataset[config.panelKey];
        const isActive =
          config.matchPanel?.(panelValue, target, panel) ??
          panelValue === target;

        panel.classList.toggle('is-active', isActive);
        panel.hidden = !isActive;
        panel.setAttribute('aria-hidden', String(!isActive));

        if (!config.enterClass) {
          return;
        }

        panel.classList.remove(config.enterClass);

        if (isActive) {
          void panel.offsetWidth;
          panel.classList.add(config.enterClass);
        }
      });
    };

    triggers.forEach((trigger, index) => {
      trigger.addEventListener('click', () => {
        activateTab(trigger.dataset[config.triggerKey]);
      });

      trigger.addEventListener('keydown', event => {
        const nextIndex = getNextTabIndex(event.key, index, triggers.length);

        if (nextIndex === null) {
          return;
        }

        event.preventDefault();

        const nextTrigger = triggers[nextIndex];
        nextTrigger.focus();
        activateTab(nextTrigger.dataset[config.triggerKey]);
      });
    });

    const initialTrigger =
      triggers.find(trigger => trigger.classList.contains('is-active')) ||
      triggers[0];

    activateTab(initialTrigger.dataset[config.triggerKey]);
  }

  function initTabs() {
    document.querySelectorAll('[data-suite-tabs]').forEach(groupElement => {
      initTabGroup(groupElement, {
        triggerSelector: '[data-suite-trigger]',
        panelSelector: '[data-suite-panel]',
        triggerKey: 'suiteTrigger',
        panelKey: 'suitePanel',
        enterClass: 'suite-panel-enter',
      });
    });

    document.querySelectorAll('[data-catalog-tabs]').forEach(groupElement => {
      initTabGroup(groupElement, {
        triggerSelector: '[data-catalog-trigger]',
        panelSelector: '[data-catalog-panel]',
        triggerKey: 'catalogTrigger',
        panelKey: 'catalogPanel',
        enterClass: 'catalog-panel-enter',
        matchPanel: (panelValue, target) =>
          target === 'all' || panelValue === target,
      });
    });
  }

  $('.copy-link').click(function (e) {
    e.preventDefault();

    let copyText = $(this).find('.copy-url');
    let copiedValue = copyText.val();

    if (!copiedValue) {
      return;
    }

    let textarea = document.createElement('textarea');
    textarea.value = copiedValue;
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);

    let tooltip = $(this).find('.tooltip');

    tooltip.stop(true, true).fadeIn();

    setTimeout(function () {
      tooltip.fadeOut();
    }, 2500);
  });

  function initAccordions() {
    const accordions = document.querySelectorAll('.accordion');

    accordions.forEach(accordion => {
      const items = Array.from(
        accordion.querySelectorAll('[data-accordion-item]'),
      );

      if (!items.length) {
        return;
      }

      const setActiveItem = activeItem => {
        items.forEach(item => {
          const trigger = item.querySelector('[data-accordion-trigger]');
          const panel = item.querySelector('[data-accordion-panel]');
          const isActive = item === activeItem;

          item.classList.toggle('is-active', isActive);

          if (trigger) {
            trigger.setAttribute('aria-expanded', String(isActive));
          }

          if (panel) {
            panel.hidden = false;
            panel.setAttribute('aria-hidden', String(!isActive));
          }
        });
      };

      items.forEach(item => {
        const trigger = item.querySelector('[data-accordion-trigger]');

        if (!trigger) {
          return;
        }

        trigger.addEventListener('click', () => {
          if (item.classList.contains('is-active')) {
            return;
          }

          setActiveItem(item);
        });
      });

      const initialActiveItem =
        items.find(item => item.classList.contains('is-active')) || items[0];

      setActiveItem(initialActiveItem);
    });
  }

  function initModals() {
    $('[data-toggle=modal]').on('click', function (event) {
      event.preventDefault();

      const target = $(this).data('target');

      if (!target) {
        return;
      }

      $(target).fadeIn();
    });

    $('.modal-close').on('click', function () {
      $(this).closest('.modal').fadeOut();
    });

    $('.modal .modal-inner').on('click', function (event) {
      event.stopPropagation();
    });
  }

  if ($('.support-request-form').length > 0) {
    $.validator.setDefaults({
      ignore: [],
    });

    $('.support-request-form').each(function () {
      $(this).validate({
        rules: {
          support_name: {
            required: true,
          },
          support_email: {
            required: true,
            email: true,
          },
          support_type: {
            required: true,
          },
          support_plugin: {
            required: true,
          },
          support_subject: {
            required: true,
          },
          support_issue: {
            required: true,
          },
          support_privacy: {
            required: true,
          },
        },

        messages: {
          support_name: {
            required: 'Please enter your name.',
          },
          support_email: {
            required: 'Please enter your email.',
            email: 'Please enter a valid email address.',
          },
          support_type: {
            required: 'Please select a support type.',
          },
          support_plugin: {
            required: 'Please select a plugin or suite.',
          },
          support_subject: {
            required: 'Please enter a subject.',
          },
          support_issue: {
            required: 'Please describe the issue.',
          },
          support_privacy: {
            required: 'You must accept the privacy consent.',
          },
        },

        errorElement: 'p',

        errorPlacement: function (error, element) {
          if (element.attr('name') === 'support_privacy') {
            error.appendTo(
              element.closest('.support-consent-wrap').find('.error'),
            );
          } else {
            error.appendTo(element.closest('.support-field').find('.error'));
          }
        },

        highlight: function (element) {
          $(element).addClass('is-error');
        },

        unhighlight: function (element) {
          $(element).removeClass('is-error');
          $(element)
            .closest('.support-field, .support-consent-wrap')
            .find('.error')
            .empty();
        },
      });
    });
  }

  if ($('.newsletter-form').length > 0) {
    $.validator.setDefaults({
      ignore: [],
    });

    $('.newsletter-form').each(function () {
      $(this).validate({
        rules: {
          email: {
            required: true,
            email: true,
          },
        },

        messages: {
          email: {
            required: 'Please enter your email.',
            email: 'Please enter a valid email address.',
          },
        },

        errorElement: 'p',

        errorPlacement: function (error, element) {
          error.appendTo(element.closest('.newsletter-field').find('.error'));
        },

        highlight: function (element) {
          $(element).addClass('is-error');
          $(element).closest('.border').addClass('is-error');
        },

        unhighlight: function (element) {
          $(element).removeClass('is-error');
          $(element).closest('.border').removeClass('is-error');
          $(element).closest('.newsletter-field').find('.error').empty();
        },
      });
    });
  }

  function initSelectFieldStates() {
    const selectFields = document.querySelectorAll('.select-field select');

    selectFields.forEach(select => {
      const field = select.closest('.select-field');

      if (!field) {
        return;
      }

      const openField = () => {
        field.classList.add('is-open');
      };

      const closeField = () => {
        field.classList.remove('is-open');
      };

      select.addEventListener('mousedown', openField);
      select.addEventListener('focus', openField);
      select.addEventListener('blur', closeField);
      select.addEventListener('change', () => {
        closeField();
        select.blur();
      });
      select.addEventListener('keydown', event => {
        if (event.key === 'Escape' || event.key === 'Tab') {
          closeField();
        }
      });
    });
  }

  function initFaqMore() {
    document.querySelectorAll('[data-faq-more]').forEach(button => {
      button.addEventListener('click', () => {
        const section = button.closest('.faq');
        if (!section) {
          return;
        }

        const expanded = section.classList.toggle('is-expanded');
        section.querySelectorAll('.accordion-item.is-extra').forEach(item => {
          item.hidden = !expanded;
        });
        button.textContent = expanded
          ? button.dataset.labelLess || 'See less'
          : button.dataset.labelMore || 'See more';
      });
    });
  }

  $(function () {
    initScrollAnimations();
    initHeaderScrollState();
    initMenuToggle();
    initTabs();
    initAccordions();
    initModals();
    initSelectFieldStates();
    initFaqMore();
  });
})(jQuery);
