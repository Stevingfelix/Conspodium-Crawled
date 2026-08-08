(function ($) {
    "use strict";

    var WidgetStickyHandler = function ($scope, $) {

        $scope[0].classList.add('adv-sticky-elementor');
        var sticky_settings = $scope.data('settings') || {};
        var sticky_container = $scope[0];

        var activeDevices = sticky_settings.sticel_sticky_devices || ['desktop', 'tablet', 'mobile'];
        var currentDevice = elementorFrontend.getCurrentDeviceMode();

        if (!activeDevices.includes(currentDevice)) {
            return;
        }

        if (sticky_settings.sticel_sticky_enabled === 'yes') {

            var Admin_offset = 0;
            var baseFontSize = parseFloat(getComputedStyle(document.documentElement).fontSize); // for rem/em
            var parentHeight = $scope[0].parentElement.offsetHeight; // for %
            var vhBase = window.innerHeight; // for vh

            /** Offset */
            var sticky_offset = sticky_settings.sticel_sticky_top?.size,
                sticky_offset_unit = sticky_settings.sticel_sticky_top.unit || 'px';

            if (document.body.classList.contains('admin-bar')) {
                switch (sticky_offset_unit) {
                    case 'px':
                        Admin_offset += 32;
                        break;
                    case 'vh':
                        Admin_offset += (32 / vhBase) * 100;
                        break;
                    case 'rem':
                        Admin_offset += 32 / baseFontSize;
                        break;
                    case 'em':
                        Admin_offset += 32 / parseFloat(getComputedStyle(sticky_container).fontSize);
                        break;
                    case '%':
                        Admin_offset += (32 / parentHeight) * 100;
                        break;
                }
            }

            var logoImages = sticky_container.querySelectorAll('img');


            var logo_change = sticky_settings?.sticky_logo_enable
            var imageWidget = $scope.find('.elementor-widget-image img');

            if ('yes' === logo_change && imageWidget.length > 0) {

                const wrapper = document.createElement('div');
                wrapper.classList.add('sticky-logo-wrapper');

                imageWidget[0].parentNode.insertBefore(wrapper, imageWidget[0]);
                wrapper.appendChild(imageWidget[0]);

                imageWidget[0].classList.add('sticky-change-logo-old');

                const clonedDiv = imageWidget[0].cloneNode(true);
                clonedDiv.src = sticky_settings?.sticky_logo_image?.url;
                clonedDiv.srcset = sticky_settings?.sticky_logo_image?.url;
                clonedDiv.classList.add('sticky-change-logo-new');
                const imgWidth = clonedDiv.getAttribute('width');
                const imgHeight = clonedDiv.getAttribute('height');

                if (imgWidth) clonedDiv.style.width = imgWidth + 'px';
                if (imgHeight) clonedDiv.style.height = imgHeight + 'px';
                
                wrapper.appendChild(clonedDiv);
            }


            /** Padding */
            var sticky_padding_top = sticky_settings.sticel_sticky_padding?.top ?? '',
                sticky_padding_left = sticky_settings.sticel_sticky_padding?.left ?? '',
                sticky_padding_right = sticky_settings.sticel_sticky_padding?.right ?? '',
                sticky_padding_bottom = sticky_settings.sticel_sticky_padding?.bottom ?? '',

                sticky_padding_unit = sticky_settings.sticel_sticky_padding.unit || 'px';

            /* Border reduis */
            var sticky_border_r_top = sticky_settings.sticel_sticky_border_reduis?.top || 0,
                sticky_border_r_left = sticky_settings.sticel_sticky_border_reduis?.left || 0,
                sticky_border_r_right = sticky_settings.sticel_sticky_border_reduis?.right || 0,
                sticky_border_r_bottom = sticky_settings.sticel_sticky_border_reduis?.bottom || 0,

                sticky_border_r_unit = sticky_settings.sticel_sticky_border_reduis.unit || 'px';

            /** Rotation */
            var sticky_rotate_x = sticky_settings.sticel_rotate_x?.size || 0,
                sticky_rotate_x_unit = sticky_settings.sticel_rotate_x?.unit || 'deg';

            var sticky_rotate_y = sticky_settings.sticel_rotate_y?.size || 0,
                sticky_rotate_y_unit = sticky_settings.sticel_rotate_y?.unit || 'deg';


            /** Width */
            var sticky_width = sticky_settings.sticel_sticky_width?.size || '',
                sticky_width_unit = sticky_settings.sticel_sticky_width?.unit || 'px';

            /** BG */
            var sticky_bg_color = sticky_settings.sticel_sticky_background || '';

            /** Animation */
            var sticky_animation = sticky_settings.sticel_sticky_animation || 'none';

            /** Hide header after scroll */
            var hide_on_scroll_down = false;

            /** Delay scroll */
            var stickyDelay = parseInt(sticky_settings.sticel_sticky_delay || 0);

            var lastScrollTop = 0;


            /**Shrink Logo */

            var shrinkLogoHeight = sticky_settings?.sticel_logo_shrink_height?.size

            if (window.innerWidth > 1025) {
                sticky_offset = sticky_settings.sticel_sticky_top?.size;
                sticky_offset_unit = sticky_settings.sticel_sticky_top.unit || 'px';

                if (document.body.classList.contains('admin-bar')) {
                    sticky_offset = sticky_settings.sticel_sticky_top?.size + Admin_offset;
                    sticky_offset_unit = sticky_settings.sticel_sticky_top.unit || 'px';
                }

                sticky_padding_top = sticky_settings.sticel_sticky_padding?.top ?? '',
                    sticky_padding_left = sticky_settings.sticel_sticky_padding?.left ?? '',
                    sticky_padding_right = sticky_settings.sticel_sticky_padding?.right ?? '',
                    sticky_padding_bottom = sticky_settings.sticel_sticky_padding?.bottom ?? '',
                    sticky_padding_unit = sticky_settings.sticel_sticky_padding.unit ?? 'px';

                sticky_border_r_top = sticky_settings.sticel_sticky_border_reduis?.top ?? '',
                    sticky_border_r_left = sticky_settings.sticel_sticky_border_reduis?.left ?? '',
                    sticky_border_r_right = sticky_settings.sticel_sticky_border_reduis?.right ?? '',
                    sticky_border_r_bottom = sticky_settings.sticel_sticky_border_reduis?.bottom ?? '',

                    sticky_border_r_unit = sticky_settings.sticel_sticky_border_reduis.unit ?? 'px';


                sticky_rotate_x = sticky_settings.sticel_rotate_x?.size || 0;
                sticky_rotate_x_unit = sticky_settings.sticel_rotate_x?.unit || 'deg';
                sticky_rotate_y = sticky_settings.sticel_rotate_y?.size || 0;
                sticky_rotate_y_unit = sticky_settings.sticel_rotate_y?.unit || 'deg';

                sticky_width = sticky_settings.sticel_sticky_width?.size || '';
                sticky_width_unit = sticky_settings.sticel_sticky_width?.unit || 'px';

                sticky_bg_color = sticky_settings.sticel_sticky_background || '';

                hide_on_scroll_down = sticky_settings.hide_on_scroll_down === 'yes';

                stickyDelay = sticky_settings.sticel_sticky_delay || 0;

                shrinkLogoHeight = sticky_settings?.sticel_logo_shrink_height?.size;


            } else if (window.innerWidth <= 1024 && window.innerWidth > 767) {
                sticky_offset = sticky_settings.sticel_sticky_top_tablet?.size;
                sticky_offset_unit = sticky_settings.sticel_sticky_top_tablet?.unit || 'px';

                sticky_padding_top = sticky_settings.sticel_sticky_padding_tablet?.top ?? '';
                sticky_padding_left = sticky_settings.sticel_sticky_padding_tablet?.left ?? '';
                sticky_padding_right = sticky_settings.sticel_sticky_padding_tablet?.right ?? '';
                sticky_padding_bottom = sticky_settings.sticel_sticky_padding_tablet?.bottom ?? '';
                sticky_padding_unit = sticky_settings.sticel_sticky_padding_tablet?.unit ?? 'px';

                sticky_border_r_top = sticky_settings.sticel_sticky_border_reduis_tablet?.top ?? '';
                sticky_border_r_left = sticky_settings.sticel_sticky_border_reduis_tablet?.left ?? '';
                sticky_border_r_right = sticky_settings.sticel_sticky_border_reduis_tablet?.right ?? '';
                sticky_border_r_bottom = sticky_settings.sticel_sticky_border_reduis_tablet?.bottom ?? '';

                sticky_border_r_unit = sticky_settings.sticel_sticky_border_reduis_tablet.unit ?? 'px';


                sticky_rotate_x = sticky_settings.sticel_rotate_x_tablet?.size || 0;
                sticky_rotate_x_unit = sticky_settings.sticel_rotate_x_tablet?.unit || 'deg';

                sticky_width = sticky_settings.sticel_sticky_width?.size || '';
                sticky_width_unit = sticky_settings.sticel_sticky_width?.unit || 'px';

                sticky_bg_color = sticky_settings.sticel_sticky_background_tablet || '';

                hide_on_scroll_down = sticky_settings.hide_on_scroll_down_tablet === 'yes';

                stickyDelay = sticky_settings.sticel_sticky_delay_tablet || 0;

                shrinkLogoHeight = sticky_settings?.sticel_logo_shrink_height_tablet?.size || shrinkLogoHeight;

            } else if (window.innerWidth <= 767) {
                sticky_offset = sticky_settings.sticel_sticky_top_mobile?.size;
                sticky_offset_unit = sticky_settings.sticel_sticky_top_mobile?.unit || 'px';

                sticky_padding_top = sticky_settings.sticel_sticky_padding_mobile?.top ?? '';
                sticky_padding_left = sticky_settings.sticel_sticky_padding_mobile?.left ?? '';
                sticky_padding_right = sticky_settings.sticel_sticky_padding_mobile?.right ?? '';
                sticky_padding_bottom = sticky_settings.sticel_sticky_padding_mobile?.bottom ?? '';
                sticky_padding_unit = sticky_settings.sticel_sticky_padding_mobile?.unit ?? 'px';

                sticky_border_r_top = sticky_settings.sticel_sticky_border_reduis_mobile?.top ?? '';
                sticky_border_r_left = sticky_settings.sticel_sticky_border_reduis_mobile?.left ?? '';
                sticky_border_r_right = sticky_settings.sticel_sticky_border_reduis_mobile?.right ?? '';
                sticky_border_r_bottom = sticky_settings.sticel_sticky_border_reduis_mobile?.bottom ?? '';
                sticky_border_r_unit = sticky_settings.sticel_sticky_border_reduis_tablet.unit ?? 'px';

                sticky_rotate_x = sticky_settings.sticel_rotate_x_mobile?.size || 0;
                sticky_rotate_x_unit = sticky_settings.sticel_rotate_x_mobile?.unit || 'deg';

                sticky_width = sticky_settings.sticel_sticky_width_mobile?.size || '';
                sticky_width_unit = sticky_settings.sticel_sticky_width_mobile?.unit || 'px';

                sticky_bg_color = sticky_settings.sticel_sticky_background_mobile || '';

                hide_on_scroll_down = sticky_settings.hide_on_scroll_down_mobile === 'yes';

                stickyDelay = sticky_settings.sticel_sticky_delay_mobile || 0;

                shrinkLogoHeight = sticky_settings?.sticel_logo_shrink_height_mobile?.size || shrinkLogoHeight;

            }

            var originalOffsetTop = sticky_container.offsetTop + sticky_container.getBoundingClientRect().top + window.scrollY - document.documentElement.scrollTop;


            var stickySpacer = null;

            function onScrollStickyHandler() {

                var scrollTop = window.scrollY || document.documentElement.scrollTop;

                // if (scrollTop >= originalOffsetTop - parseInt(sticky_offset)) {
                // if (scrollTop > parseInt(sticky_offset)) {
                // if (scrollTop >= originalOffsetTop - sticky_offset) {
                //  if (scrollTop > 0 && scrollTop >= originalOffsetTop - parseInt(sticky_offset)) {
                // if (scrollTop > 0 && scrollTop >= originalOffsetTop - parseInt(sticky_offset) + stickyDelay) {
                // if (scrollTop > 0 && scrollTop >= originalOffsetTop - parseInt(sticky_offset) + parseInt(stickyDelay)) {

                if (scrollTop > parseInt(stickyDelay)) {

                    if (!stickySpacer) {
                        stickySpacer = document.createElement('div');
                        stickySpacer.classList.add('sticel-sticky-placeholder');

                        var containerHeight = sticky_container.offsetHeight;
                        stickySpacer.style.height = containerHeight + 'px';
                        stickySpacer.style.width = '100%';
                        stickySpacer.style.display = 'block';

                        sticky_container.parentNode.insertBefore(stickySpacer, sticky_container);
                    }

                    sticky_container.classList.add('yes-is-sticky');
                    document.body.classList.add('yes-is-sticky');

                    sticky_container.classList.remove('sticel-anim-fade-in', 'sticel-anim-slide-up', 'sticel-anim-slide-down', 'sticel-anim-zoom-in', 'sticel-anim-flip-in');

                    if (sticky_animation !== 'none') {
                        sticky_container.classList.add('sticel-anim-' + sticky_animation);
                    }

                    if (sticky_container.classList.contains('yes-is-sticky')) {
                        sticky_container.style.position = 'fixed';
                        sticky_container.style.top = sticky_offset + sticky_offset_unit;

                        sticky_container.style.padding = sticky_padding_top + sticky_padding_unit + ' ' + sticky_padding_right + sticky_padding_unit + ' ' + sticky_padding_bottom + sticky_padding_unit + ' ' + sticky_padding_left + sticky_padding_unit;
                        sticky_container.style.borderRadius = sticky_border_r_top + sticky_border_r_unit + ' ' + sticky_border_r_right + sticky_border_r_unit + ' ' + sticky_border_r_bottom + sticky_border_r_unit + ' ' + sticky_border_r_left + sticky_border_r_unit;

                        sticky_container.style.transform = 'rotateX(' + sticky_rotate_x + sticky_rotate_x_unit + ') rotateY(' + sticky_rotate_y + sticky_rotate_y_unit + ')';

                        sticky_container.style.width = sticky_width + sticky_width_unit;
                        sticky_container.style.backgroundColor = sticky_bg_color;
                    }

                    if (hide_on_scroll_down) {
                        if (scrollTop > lastScrollTop) {
                            sticky_container.classList.add('sticel-hide-on-scroll');
                        } else {
                            sticky_container.classList.remove('sticel-hide-on-scroll');
                        }
                        lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
                    }

                    logoImages.forEach(function (img) {
                        if (!img.dataset.originalHeight) {
                            img.dataset.originalHeight = img.offsetHeight;
                        }

                        img.classList.add('sticel-logo-shrink');

                        img.style.height = img.dataset.originalHeight + 'px';

                        img.offsetHeight;

                        img.style.height = shrinkLogoHeight + 'px';
                    });

                    if ('yes' === logo_change && imageWidget.length > 0) {
                        var sticky_old_logo = sticky_container.querySelector('.sticky-change-logo-old');
                        var sticky_new_logo = sticky_container.querySelector('.sticky-change-logo-new');
                        sticky_old_logo.style.opacity = '0';
                        sticky_old_logo.style.visibility = 'hidden';
                        sticky_old_logo.style.position = 'absolute';
                        sticky_new_logo.style.opacity = '1';
                        sticky_new_logo.style.visibility = 'visible';
                        var imgWidth = sticky_new_logo.getAttribute('width');
                        var imgHeight = sticky_new_logo.getAttribute('height');
                        if (imgWidth) sticky_new_logo.style.width = imgWidth + 'px';
                        if (imgHeight) sticky_new_logo.style.height = imgHeight + 'px';
                    }

                } else {
                    if (stickySpacer) {
                        stickySpacer.remove();
                        stickySpacer = null;
                    }

                    sticky_container.style.position = '';
                    sticky_container.style.top = '';
                    sticky_container.style.padding = '';
                    sticky_container.style.borderRadius = '';

                    sticky_container.style.transform = '';
                    sticky_container.style.transform = 'rotateX(0deg) rotateY(0deg)';

                    sticky_container.style.width = '';
                    sticky_container.style.backgroundColor = '';

                    const animClasses = [
                        'sticel-anim-fade-in',
                        'sticel-anim-slide-up',
                        'sticel-anim-slide-down',
                        'sticel-anim-zoom-in',
                        'sticel-anim-flip-in',
                        'sticel-anim-rotate-in',
                        'sticel-anim-bounce-in',
                        'sticel-anim-slide-left',
                        'sticel-anim-slide-right',
                        'sticel-anim-fade-in-up',
                        'sticel-anim-fade-in-down',
                        'sticel-anim-fade-in-left',
                        'sticel-anim-fade-in-right',
                        'sticel-anim-zoom-out'
                    ];

                    sticky_container.classList.remove(...animClasses);

                    logoImages.forEach(function (img) {
                        const originalHeight = img.dataset.originalHeight;

                        img.classList.add('sticel-logo-shrink');

                        img.style.height = originalHeight + 'px';
                    });

                    if ('yes' === logo_change && imageWidget.length > 0) {
                        var sticky_new_logo = sticky_container.querySelector('.sticky-change-logo-new');
                        var sticky_old_logo = sticky_container.querySelector('.sticky-change-logo-old');
                        sticky_new_logo.style.opacity = '0';
                        sticky_new_logo.style.visibility = 'hidden';
                        sticky_old_logo.style.opacity = '1';
                        sticky_old_logo.style.visibility = 'visible';
                        sticky_old_logo.style.position = 'absolute';
                    }

                    sticky_container.classList.remove('yes-is-sticky');
                    document.body.classList.remove('yes-is-sticky');
                }
            }

            window.addEventListener('scroll', onScrollStickyHandler);
            onScrollStickyHandler();

        }

        const backToTop = document.getElementById("sticky-back-to-top");

        if (backToTop) {
            window.addEventListener("scroll", () => {
                backToTop.style.display = window.scrollY > 300 ? "flex" : "none";
            });

            backToTop.addEventListener("click", () => {
                backToTop.classList.add("clicked-animation");
                window.scrollTo({
                    top: 0,
                    behavior: "smooth"
                });
                setTimeout(() => {
                    backToTop.classList.remove("clicked-animation");
                }, 500);
            });
        }


    };

    $(window).on('elementor/frontend/init', function () {
        elementorFrontend.hooks.addAction(
            'frontend/element_ready/container',
            WidgetStickyHandler
        );
        elementorFrontend.hooks.addAction(
            'frontend/element_ready/section',
            WidgetStickyHandler
        );
    });

})(jQuery);