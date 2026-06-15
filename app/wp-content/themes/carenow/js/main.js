/**
* isMobile
* headerFixed
* responsiveMenu
* themesflatSearch
* detectViewport
* blogLoadMore
* commingsoon
* goTop
* retinaLogos
* customizable_carousel
* parallax
* iziModal
* bg_particles
* pagetitleVideo
* toggleExtramenu
* removePreloader
*/

;(function($) {

    "use strict";

    var isMobile = {
        Android: function() {
            return navigator.userAgent.match(/Android/i);
        },
        BlackBerry: function() {
            return navigator.userAgent.match(/BlackBerry/i);
        },
        iOS: function() {
            return navigator.userAgent.match(/iPhone|iPad|iPod/i);
        },
        Opera: function() {
            return navigator.userAgent.match(/Opera Mini/i);
        },
        Windows: function() {
            return navigator.userAgent.match(/IEMobile/i);
        },
        any: function() {
            return (isMobile.Android() || isMobile.BlackBerry() || isMobile.iOS() || isMobile.Opera() || isMobile.Windows());
        }
    };

    var Modal_Right = function() {
        const body = $('body');
        const modalMenu = $('.modal-menu-left');
        const modalMenuBody = modalMenu.children('.modal-menu__body');

        if (modalMenu.length) {
            const open = function() {
                modalMenu.addClass('modal-menu--open');
            };
            const close = function() {
                modalMenu.removeClass('modal-menu--open');
            };

            $('.modal-menu-left-btn').on('click', function() {
                open();
            });
            $('.modal-menu__backdrop, .modal-menu__close').on('click', function() {
                close();
            });
        }

        modalMenu.on('click', function(event) {
            const trigger = $(this);
            const item = trigger.closest('[data-modal-menu-item]');
            let panel = item.data('panel');

            if (!panel) {
                panel = item.children('[data-modal-menu-panel]').children('.modal-menu__panel');

                if (panel.length) {
                    modalMenuBody.append(panel);
                    item.data('panel', panel);
                    panel.width(); // force reflow
                }
            }

            if (panel && panel.length) {
                event.preventDefault();
            }
        });
        $('.modal-menu__body #mainnav-secondary .menu li').each(function(n) {
            if ($('.modal-menu__body #mainnav-secondary .menu li:has(ul)').find(">span").length == 0) {
               $('.modal-menu__body #mainnav-secondary .menu li:has(ul)').append('<span class="carenow-icon-chevron-right"></span>');
             }
            $(this).find('.sub-menu').css({display: 'none'});
        });
        $('.modal-menu__body  #mainnav-secondary .menu li:has(ul) > span').on('click', function (e) {
            e.preventDefault();
            $(this).closest('li').children('.sub-menu').slideToggle();
            $(this).closest('li').toggleClass('opened');
        });
    };

    var menu_Modal_Left = function() {
        var menuType = 'desktop';

        $(window).on('load resize', function() {
            var currMenuType = 'desktop';
            var adminbar = $('#wpadminbar').height();

            if ( matchMedia( 'only screen and (max-width: 1024px)' ).matches ) {                
                currMenuType = 'mobile';                              
            }

            if ( currMenuType !== menuType ) {
                menuType = currMenuType;

                if ( currMenuType === 'mobile' ) {
                    var $mobileMenu = $('#mainnav').hide();
                    var hasChildMenu = $('#mainnav_canvas').find('li:has(ul)');
                    hasChildMenu.children('ul').hide();
                    if (hasChildMenu.find(">span").length == 0) {
                        hasChildMenu.children('a').after('<span class="btn-submenu"></span>');
                    }
                    $('.btn-menu').removeClass('active');
                    $('.canvas-nav-wrap .inner-canvas-nav').css({'padding-top': adminbar});
                    $('.canvas-nav-wrap .canvas-menu-close').css({'top': (adminbar + 30)});
                } else {
                    var $mobileMenu = $('#mainnav').show();
                    $('.canvas-nav-wrap .inner-canvas-nav').css({'padding-top': adminbar});
                    $('.canvas-nav-wrap .canvas-menu-close').css({'top': (adminbar + 30)}); 
                    $('#header').find('.canvas-nav-wrap').removeClass('active');            
                }
            }
        });

        $('.btn-menu').on('click', function() {
            $(this).closest('#header').find('.canvas-nav-wrap').toggleClass('active');
        });

        $('.canvas-nav-wrap .overlay-canvas-nav').on('click', function(e) {
            $(this).closest('#header').find('.canvas-nav-wrap').toggleClass('active');
        });

        $(document).on('click', '#mainnav_canvas li .btn-submenu', function(e) {
            $(this).toggleClass('active').next('ul').slideToggle(300);
            e.stopImmediatePropagation();
        });        
    };

    var headerFixed = function() { 
        if ( $('body').hasClass('header_sticky') ) {
            var header = $('#header'),
            hd_height = $('#header').height(),
            injectSpace = $('<div />', { height: hd_height }).insertAfter($('#header'));   
            injectSpace.hide(); 
            $(window).on('load scroll resize', function() {
                if ( matchMedia( 'only screen and (min-width: 992px)' ).matches ) {  
                    var top_height = $('.themesflat-top').height(),
                        wpadminbar = $('#wpadminbar').height();
                    if (top_height == undefined) {
                        top_height = 0;
                    } 
                    if ( matchMedia( 'only screen and (max-width: 600px)' ).matches ) {                
                        if ( $(window).scrollTop() >= top_height + wpadminbar ) { 
                            header.addClass('header-sticky'); 
                            $('.header-sticky').removeAttr('style');                                      
                            injectSpace.show();
                        } else { 
                            $('.header-sticky').removeAttr('style'); 
                            header.removeClass('header-sticky');
                            injectSpace.hide();
                        }                            
                    }else {
                        if ( $(window).scrollTop() >= top_height + hd_height ) { 
                            header.addClass('header-sticky');  
                            $('.header-sticky').css('top',wpadminbar);                                     
                            injectSpace.show();
                        } else { 
                            $('.header-sticky').removeAttr('style'); 
                            header.removeClass('header-sticky');
                            injectSpace.hide();
                        }                     
                    }  
                }
            })
        }      
    } 

    var themesflatSearch = function () {
       $(document).on('click', function(e) {   
            var clickID = e.target.id;   
            if ( ( clickID != 's' ) ) {
                $('.top-search').removeClass('show');   
                $('.show-search').removeClass('active');             
            } 
        });

        $('.show-search').on('click', function(event){
            event.stopPropagation();
        });

        $('.search-form').on('click', function(event){
            event.stopPropagation();
        });        

        $('.show-search').on('click', function (e) {           
            if( !$( this ).hasClass( "active" ) )
                $( this ).addClass( 'active' );
            else
                $( this ).removeClass( 'active' );
             e.preventDefault();

            if( !$('.top-search' ).hasClass( "show" ) )
                $( '.top-search' ).addClass( 'show' );
            else
                $( '.top-search' ).removeClass( 'show' );
        });
    };

    var parallax = function() {
        if ( $().parallax && isMobile.any() == null ) {
            $('.parallax').parallax("50%", -0.5);           
        }
    };

    var pagetitleVideo = function(){
        if ( $('.page-title').hasClass('video') ) {
            jQuery(function () {          
               jQuery("#ptbgVideo").YTPlayer();     
            });
        }
    };

    var blogLoadMore = function() { 
        var $container = $('.wrap-blog-article');
        if ( $('body').hasClass('page-template') ) {
            var $container = $('.wrap-blog-article');
        }   

        $('.navigation.loadmore.blog a').on('click', function(e) {
            e.preventDefault(); 
            var $item = '.item';
            $('<span/>', {
                class: 'infscr-loading',
                text: 'Loading...',
            }).appendTo($container);

            $.ajax({
                type: "GET",
                url: $(this).attr('href'),
                dataType: "html",
                success: function( out ) {
                    var result = $(out).find($item);  
                    var nextlink = $(out).find('.navigation.loadmore.blog a').attr('href');

                    result.css({ opacity: 0 });
                    if ($container.hasClass('blog-masonry')) {
                        $container.append(result).imagesLoaded(function () {
                            result.css({ opacity: 1 });
                            $container.masonry('appended', result);
                        });
                    }
                    else {
                        result.css({ opacity: 1 });
                        $container.append(result);
                    }

                    if ( nextlink != undefined ) {
                        $('.navigation.loadmore.blog a').attr('href', nextlink);
                        $container.find('.infscr-loading').remove();
                    } else {
                        $container.find('.infscr-loading').addClass('no-ajax').text('All posts loaded.').delay(2000).queue(function() {$(this).remove();});
                        $('.navigation.loadmore.blog').remove();
                    }
                    customizable_carousel();
                    iziModal();
                }
            })
        })       
    } 

    var goTop = function() {
        $(window).scroll(function() {
            if ( $(this).scrollTop() > 500 ) {
                $('.go-top').addClass('show');
            } else {
                $('.go-top').removeClass('show');
            }
        });

        $('.go-top').on('click', function(event) { 
            event.preventDefault();           
            $("html, body").animate({ scrollTop: 0 }, 0);            
        });
    };

    var customizable_carousel_div = function() {
        var owl_carousel = $("div.customizable-carousel");
        if (owl_carousel.length > 0) {
            owl_carousel.each(function() {
                var $this = $(this),
                    $items = ($this.data('items')) ? $this.data('items') : 1,
                    $loop = ($this.attr('data-loop')) ? $this.data('loop') : true,
                    $navdots = ($this.data('nav-dots')) ? $this.data('nav-dots') : false,
                    $navarrows = ($this.data('nav-arrows')) ? $this.data('nav-arrows') : false,
                    $autoplay = ($this.attr('data-autoplay')) ? $this.data('autoplay') : false,
                    $autospeed = ($this.attr('data-autospeed')) ? $this.data('autospeed') : 3500,
                    $smartspeed = ($this.attr('data-smartspeed')) ? $this.data('smartspeed') : 950,
                    $autohgt = ($this.data('autoheight')) ? $this.data('autoheight') : false,
                    $space = ($this.attr('data-space')) ? $this.data('space') : 15;

                $(this).owlCarousel({
                    loop: $loop,
                    items: $items,
                    responsive: {
                        0: {
                            items: ($this.data('xs-items')) ? $this.data('xs-items') : 1,
                            nav: false
                        },
                        600: {
                            items: ($this.data('sm-items')) ? $this.data('sm-items') : 2,
                            nav: false
                        },
                        1000: {
                            items: ($this.data('md-items')) ? $this.data('md-items') : 3
                        },
                        1240:{
                            items: $items
                        }
                    },
                    dots: $navdots,
                    autoplayTimeout: $autospeed,
                    smartSpeed: $smartspeed,
                    autoHeight: $autohgt,
                    margin: $space,
                    nav: $navarrows,
                    navText: ['<i class="carenow-icon-chevron-left"></i>','<i class="carenow-icon-chevron-right"></i>'],
                    autoplay: $autoplay,
                    autoplayHoverPause: true
                });
            });
        }
    }; 

    var bg_bottom = function() {
        $(window).on('load resize', function() {
            var width_span = $('.copyright span').outerWidth()+100;
            $('.bottom .bg_copyright').css('min-width',width_span);
        })
    }  

    var remove_tag = function() {
        $('.wpcf7-form').find('p br').closest('p').remove();
    }


    var logo = function() {
        $(window).on('load resize', function() {
            if ( matchMedia( 'only screen and (min-width: 1441px)' ).matches ) {  
                var topbarHeight = $('.themesflat-top').outerHeight();
                var headerHeight = $('#header').outerHeight();
                var topbar_header_height = topbarHeight+headerHeight;
                $("#header.header-style1 .logo").css({"margin-top":"-"+topbarHeight+"px","min-height":""+topbar_header_height+"px"});
            }else {
                $("#header.header-style1 .logo").css({"margin-top":"unset","min-height":"unset"});
            }
        });
    };    

    var removePreloader = function() {
        $("#preloader").fadeOut('slow',function(){
            setTimeout(function() {
                $("#preloader").remove();
            }, 1000);
        });
    };

    var scrollToWooNotice = function() {
        if ( $('.woocommerce-notices-wrapper').children().length > 0 ) {

            $('html, body').animate({
                scrollTop: $('.woocommerce-notices-wrapper').offset().top - 20
            }, 500);

        }
    }

    var scrollToCF7Notice = function () {
        document.addEventListener( 'wpcf7invalid', function( event ) {

            setTimeout( function() {
                $('html').stop().animate({
                    scrollTop: $(".wpcf7-not-valid").first().offset().top - 40,
                }, 400, 'swing');
            }, 100);

        }, false );
    }

    var subscriptionFilter = function() {
        if ( $('#subscription-filter').length === 0 ) {
            return true;
        }

        $( "#subscription-filter .vertical-buttons__input input" ).on( "change", function() {

            var target = $(this).val();

            $('.subscribe-type[data-subtype!="' + target + '"]').hide();
            $('.subscribe-type[data-subtype="' + target + '"]').show();
        });
    }

    var lightboxImages = function() {

        // Только страницы заболеваний, препаратов и опросников
        let body_cl = document.body.classList;

        if (!body_cl.contains('disease-template-default')
            && !body_cl.contains('substance-template-default')
            && !body_cl.contains('custom_quiz-template-default')) {
            return true;
        }

        const container = document.querySelector('main#main');

        $(container).find('img').each(function( index ) {
            if ( $( this).parent('a').length !== 0 ) {
                return;
            }
            $( this ).wrap('<a href="' + $( this ).attr('src') + '" data-fslightbox="zoom-gallery"></a>').parent();
        });

        refreshFsLightbox();
    }

    var blockedContentTooltip = function () {
        $('.wp-block-details__locked').each(function(n) {
            new bootstrap.Tooltip($(this).get(0));

            $(this).on('show.bs.tooltip', function () {

                setTimeout(function (that) {
                    $(that).tooltip("hide");
                }, 6000, this);
            });
        });
    }

    // Скопировать ссылку на статью
    var copyArticleLink = function () {

        const buttons = document.querySelectorAll('a.podelitsya-article[data-href]');

        if (buttons.length === 0) {
            return;
        }

        buttons.forEach(el => {

            new bootstrap.Tooltip(el, {
                'title': 'Ссылка скопирована',
                'trigger': 'click'
            });

            $(el).on('show.bs.tooltip', function () {
                setTimeout(function (el) {
                    $(el).tooltip("hide");
                }, 2000, this);
            });

            el.addEventListener('click', function(e) {
                e.preventDefault();
                navigator.clipboard.writeText(el.dataset.href);
            });
        });
    }

    // Открыть доступ к статье
    var shareArticleSubscriber = function () {

        const share_article_wrapper = document.querySelector('.share-article__wrapper');
        const create_share_link = document.querySelector('.share-article__create');

        if ( ! create_share_link ) {
            return;
        }

        const post_id = share_article_wrapper.querySelector('input[name="post_id"]').value;
        const share_article_nonce = share_article_wrapper.querySelector('input[name="share_article_nonce"]').value;
        const share_article_current_usages = document.querySelector('.share-article__usages .share-article__usages_current');

        create_share_link.addEventListener('click', function (e) {
            e.preventDefault();
            create_share_link.classList.add('is-loading');

            const data = new FormData();
            data.append('action', 'medvise_create_share_article_token');
            data.append('post_id', post_id);
            data.append('nonce', share_article_nonce);

            fetch('/wp-admin/admin-ajax.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: data
            })
                .then(response => response.text())
                .then(result => {
                    result = JSON.parse(result);
                    console.log(result);
                    if (result.success) {
                        share_article_wrapper.innerHTML =
                            `<input type="text" class="input-text" name="share_link" value="` +
                            `${location.origin}${location.pathname}?access_token=${result.data.token}"`
                            + ` disabled="">` +
                            `<button class="themesflat-button share-article__copy"><i class="fa-solid fa-copy"></i></button>`;

                        share_article_current_usages.textContent = result.data.usages;
                    }
                    else {
                        alert(result.data);
                    }
                })
                .catch(error => {
                    console.error('Ошибка:', error);
                });
        });
    }

    function copyShareArticleLink() {

        const share_article_wrapper = document.querySelector('.share-article__wrapper');

        if ( ! share_article_wrapper ) {
            return;
        }

        document.addEventListener('click', function (e) {

            const wrapper = e.target.closest('.share-article__wrapper');

            if ( ! wrapper ) {
                return true;
            }

            // Проверяем, что клик именно по кнопке копирования
            if ( ! e.target.classList.contains('share-article__copy')
                && ! e.target.parentNode.classList.contains('share-article__copy')
            ) {
                return true;
            }

            e.preventDefault();

            const button = wrapper.querySelector('.themesflat-button');
            const link = wrapper.querySelector('input[name="share_link"]');

            new bootstrap.Tooltip(button, {
                'title': 'Ссылка скопирована',
                'trigger': 'click'
            });

            $(button).tooltip("show");

            $(button).on('show.bs.tooltip', function () {
                setTimeout(function (el) {
                    $(button).tooltip("hide");
                }, 2000, this);
            });

            navigator.clipboard.writeText(link.value);
        });
    }

    function medviseOhlpLayout() {
        const viewers = document.querySelectorAll('.medvise-ohlp-viewer');

        if ( ! viewers.length ) {
            return;
        }

        document.body.classList.add('medvise-ohlp-layout-active');

        function isDesktopView() {
            return window.innerWidth > 900;
        }

        function syncNavOffset(viewer) {
            const nav = viewer.querySelector('.medvise-ohlp-nav');
            const alignTarget = viewer.closest('.content-area') || viewer.closest('#main.post-wrap, main.post-wrap, .post-wrap');

            if ( ! nav || ! alignTarget || ! isDesktopView() ) {
                viewer.style.setProperty('--medvise-ohlp-nav-offset', '0px');
                return;
            }

            const viewerTop = viewer.getBoundingClientRect().top;
            const targetTop = alignTarget.getBoundingClientRect().top;
            const offset = Math.max(0, Math.round(viewerTop - targetTop));
            viewer.style.setProperty('--medvise-ohlp-nav-offset', offset + 'px');
        }

        viewers.forEach(function(viewer) {
            const contentArea = viewer.closest('.content-area');
            const container = viewer.closest('#themesflat-content > .container, .page-wrap > .container');

            if ( contentArea ) {
                contentArea.classList.add('medvise-ohlp-content-area');
            }

            if ( container ) {
                container.classList.add('medvise-ohlp-container');
            }

            syncNavOffset(viewer);

            window.addEventListener('resize', function() {
                window.clearTimeout(viewer._medviseOhlpLayoutTimer);
                viewer._medviseOhlpLayoutTimer = window.setTimeout(function() {
                    syncNavOffset(viewer);
                }, 120);
            });

            window.addEventListener('load', function() {
                syncNavOffset(viewer);
            });
        });
    }

    function medviseOhlpMobilePdf() {
        const viewers = document.querySelectorAll('.medvise-ohlp-viewer');
        const mobileQuery = window.matchMedia ? window.matchMedia('(max-width: 900px)') : null;
        let pdfJsPromise = null;

        if ( ! viewers.length ) {
            return;
        }

        function isMobileView() {
            return mobileQuery ? mobileQuery.matches : window.innerWidth <= 900;
        }

        function assetUrl(path) {
            return window.location.origin + path;
        }

        function loadPdfJs() {
            if ( window.pdfjsLib ) {
                window.pdfjsLib.GlobalWorkerOptions.workerSrc = assetUrl('/wp-content/themes/carenow/js/vendor/pdfjs/pdf.worker.min.js');
                return Promise.resolve(window.pdfjsLib);
            }

            if ( pdfJsPromise ) {
                return pdfJsPromise;
            }

            pdfJsPromise = new Promise(function(resolve, reject) {
                const existingScript = document.querySelector('script[data-medvise-pdfjs="1"]');

                if ( existingScript ) {
                    existingScript.addEventListener('load', function() {
                        if ( window.pdfjsLib ) {
                            resolve(window.pdfjsLib);
                        } else {
                            reject(new Error('PDF.js не загрузился'));
                        }
                    });
                    existingScript.addEventListener('error', reject);
                    return;
                }

                const script = document.createElement('script');
                script.src = assetUrl('/wp-content/themes/carenow/js/vendor/pdfjs/pdf.min.js');
                script.async = true;
                script.setAttribute('data-medvise-pdfjs', '1');
                script.onload = function() {
                    if ( window.pdfjsLib ) {
                        resolve(window.pdfjsLib);
                    } else {
                        reject(new Error('PDF.js не загрузился'));
                    }
                };
                script.onerror = reject;
                document.head.appendChild(script);
            }).then(function(pdfjsLib) {
                pdfjsLib.GlobalWorkerOptions.workerSrc = assetUrl('/wp-content/themes/carenow/js/vendor/pdfjs/pdf.worker.min.js');
                return pdfjsLib;
            });

            return pdfJsPromise;
        }

        function parseNumber(value) {
            const parsed = parseFloat(value);
            return Number.isFinite(parsed) ? parsed : null;
        }

        function cssEscape(value) {
            if ( window.CSS && typeof window.CSS.escape === 'function' ) {
                return window.CSS.escape(value);
            }

            return String(value).replace(/["\\]/g, '\\$&');
        }

        function readPosition(link, prefix) {
            const page = parseInt(link.getAttribute(prefix + 'page'), 10);
            const y = parseNumber(link.getAttribute(prefix + 'y'));
            const pageHeight = parseNumber(link.getAttribute(prefix + 'page-height'));

            if ( ! page || y === null ) {
                return null;
            }

            return {
                page: page,
                y: y,
                pageHeight: pageHeight
            };
        }

        function samePosition(first, second) {
            if ( ! first || ! second ) {
                return false;
            }

            return first.page === second.page && Math.abs(first.y - second.y) < 4;
        }

        function resolveLinkPosition(root, currentLink) {
            const direct = readPosition(currentLink, 'data-');

            if ( direct ) {
                return direct;
            }

            const links = Array.prototype.slice.call(root.querySelectorAll('.medvise-ohlp-nav-link'));
            const currentIndex = links.indexOf(currentLink);
            const documentId = currentLink.getAttribute('data-document-id');
            const frame = documentId ? root.querySelector('.medvise-ohlp-pdf-frame[data-document-id="' + cssEscape(documentId) + '"]') : null;
            const framePageHeight = frame ? parseNumber(frame.getAttribute('data-page-height')) : null;
            const framePageCount = frame ? parseInt(frame.getAttribute('data-page-count') || '0', 10) : 0;
            const fallbackPageHeight = framePageHeight || parseNumber(currentLink.getAttribute('data-page-height')) || 842;

            if ( currentIndex < 0 || ! documentId ) {
                return null;
            }

            for ( let index = currentIndex - 1; index >= 0; index-- ) {
                const link = links[index];

                if ( link.getAttribute('data-document-id') !== documentId ) {
                    continue;
                }

                const previous = readPosition(link, 'data-');

                if ( ! previous ) {
                    continue;
                }

                let page = previous.page;
                let y = previous.y + 140;

                while ( y > fallbackPageHeight - 120 ) {
                    y -= fallbackPageHeight;
                    page += 1;
                }

                if ( framePageCount ) {
                    page = Math.min(page, framePageCount);
                }

                return {
                    page: Math.max(1, page),
                    y: Math.max(0, y),
                    pageHeight: fallbackPageHeight
                };
            }

            for ( let index = currentIndex + 1; index < links.length; index++ ) {
                const link = links[index];

                if ( link.getAttribute('data-document-id') !== documentId ) {
                    continue;
                }

                const next = readPosition(link, 'data-');

                if ( next ) {
                    return {
                        page: next.page,
                        y: Math.max(0, next.y - 140),
                        pageHeight: next.pageHeight || fallbackPageHeight
                    };
                }
            }

            return null;
        }

        function findNextPosition(root, currentLink, start) {
            const links = Array.prototype.slice.call(root.querySelectorAll('.medvise-ohlp-nav-link'));
            const currentIndex = links.indexOf(currentLink);
            const documentId = currentLink.getAttribute('data-document-id');

            if ( currentIndex < 0 ) {
                return null;
            }

            for ( let index = currentIndex + 1; index < links.length; index++ ) {
                const link = links[index];
                if ( link.getAttribute('data-document-id') !== documentId ) {
                    break;
                }

                const next = readPosition(link, 'data-');
                if ( next && ! samePosition(start, next) ) {
                    return next;
                }
            }

            return null;
        }

        function ensureMobileHolder(frame) {
            const documentId = frame.getAttribute('data-document-id') || '';
            let holder = frame.parentNode.querySelector('.medvise-ohlp-mobile-pdf[data-document-id="' + cssEscape(documentId) + '"]');

            if ( holder ) {
                return holder;
            }

            holder = document.createElement('div');
            holder.className = 'medvise-ohlp-mobile-pdf medvise-ohlp-rendered-pdf';
            holder.setAttribute('data-document-id', documentId);
            holder.setAttribute('aria-live', 'polite');
            frame.insertAdjacentElement('afterend', holder);
            return holder;
        }

        function setStatus(holder, text, pdfSrc) {
            holder.textContent = '';

            const status = document.createElement('div');
            status.className = 'medvise-ohlp-mobile-status';
            status.textContent = text;
            holder.appendChild(status);

            if ( pdfSrc ) {
                const link = document.createElement('a');
                link.href = pdfSrc;
                link.textContent = 'Открыть PDF';
                link.target = '_blank';
                link.rel = 'noopener';
                status.appendChild(document.createTextNode(' '));
                status.appendChild(link);
            }
        }

        function pageBottomForRange(pageNumber, end, fallbackHeight) {
            if ( end && end.page === pageNumber ) {
                return Math.max(0, end.y - 8);
            }

            return fallbackHeight;
        }

        function scrollToMobilePdf(holder) {
            if ( ! holder ) {
                return;
            }

            const header = document.querySelector('#header');
            const headerHeight = header ? Math.min(90, Math.max(0, header.getBoundingClientRect().height || 0)) : 0;
            const top = holder.getBoundingClientRect().top + window.scrollY - headerHeight - 12;
            window.scrollTo({
                top: Math.max(0, top),
                behavior: 'smooth'
            });
        }

        async function renderRange(root, link) {
            const documentId = link.getAttribute('data-document-id');
            const frame = documentId ? root.querySelector('.medvise-ohlp-pdf-frame[data-document-id="' + cssEscape(documentId) + '"]') : null;
            const start = resolveLinkPosition(root, link);

            if ( ! frame || ! start ) {
                return false;
            }

            const holder = ensureMobileHolder(frame);
            const pdfSrc = frame.getAttribute('data-pdf-src') || (frame.getAttribute('src') || '').split('#')[0];
            const end = readPosition(link, 'data-end-') || findNextPosition(root, link, start);
            const renderToken = Date.now() + ':' + Math.random();
            const width = Math.max(280, Math.floor(holder.clientWidth || root.clientWidth || frame.clientWidth || 360));
            const renderKey = [pdfSrc, start.page, start.y, end && end.page, end && end.y, width].join('|');

            if ( holder.getAttribute('data-render-key') === renderKey && holder.children.length ) {
                holder.scrollTop = 0;
                return holder;
            }

            holder.setAttribute('data-render-token', renderToken);
            holder.setAttribute('data-render-key', renderKey);
            setStatus(holder, 'Загрузка PDF...', null);

            try {
                const pdfjsLib = await loadPdfJs();
                const loadingTask = pdfjsLib.getDocument({ url: pdfSrc });
                const pdf = await loadingTask.promise;

                if ( holder.getAttribute('data-render-token') !== renderToken ) {
                    return holder;
                }

                holder.textContent = '';

                const firstPage = Math.max(1, start.page);
                const lastPage = end ? Math.min(pdf.numPages, Math.max(firstPage, end.page)) : firstPage;
                const deviceScale = Math.min(window.devicePixelRatio || 1, 2);

                for ( let pageNumber = firstPage; pageNumber <= lastPage; pageNumber++ ) {
                    const page = await pdf.getPage(pageNumber);

                    if ( holder.getAttribute('data-render-token') !== renderToken ) {
                        return holder;
                    }

                    const baseViewport = page.getViewport({ scale: 1 });
                    const pageHeight = pageNumber === start.page && start.pageHeight ? start.pageHeight : baseViewport.height;
                    const top = pageNumber === firstPage ? Math.max(0, start.y - 8) : 0;
                    const bottom = Math.min(pageHeight, pageBottomForRange(pageNumber, end, pageHeight));

                    if ( bottom <= top + 12 ) {
                        continue;
                    }

                    const cssScale = width / baseViewport.width;
                    const renderViewport = page.getViewport({ scale: cssScale * deviceScale });
                    const canvas = document.createElement('canvas');
                    const context = canvas.getContext('2d');
                    const pageWrap = document.createElement('div');

                    canvas.width = Math.floor(renderViewport.width);
                    canvas.height = Math.floor(renderViewport.height);
                    canvas.style.width = (renderViewport.width / deviceScale) + 'px';
                    canvas.style.height = (renderViewport.height / deviceScale) + 'px';
                    canvas.style.marginTop = '-' + (top * cssScale) + 'px';

                    pageWrap.className = 'medvise-ohlp-mobile-page';
                    pageWrap.style.width = width + 'px';
                    pageWrap.style.height = Math.max(80, (bottom - top) * cssScale) + 'px';
                    pageWrap.appendChild(canvas);
                    holder.appendChild(pageWrap);

                    await page.render({
                        canvasContext: context,
                        viewport: renderViewport
                    }).promise;
                }

                if ( ! holder.children.length ) {
                    setStatus(holder, 'Раздел PDF не найден.', pdfSrc);
                }
            } catch (error) {
                setStatus(holder, 'Не удалось загрузить PDF.', pdfSrc);
                console.error('Ошибка PDF-просмотрщика ОХЛП:', error);
            }

            holder.scrollTop = 0;
            if ( ! isMobileView() ) {
                root.classList.add('is-pdfjs-active');
            }
            return holder;
        }

        function initViewer(root) {
            if ( root.dataset.ohlpPdfReady === '1' ) {
                return;
            }

            root.dataset.ohlpPdfReady = '1';
            const links = Array.prototype.slice.call(root.querySelectorAll('.medvise-ohlp-nav-link'));
            const scroller = root.querySelector('.medvise-ohlp-scroll');

            links.forEach(function(link) {
                link.addEventListener('click', function(event) {
                    if ( ! link.getAttribute('data-document-id') ) {
                        return;
                    }

                    event.preventDefault();
                    event.stopImmediatePropagation();

                    renderRange(root, link).then(function(holder) {
                        if ( holder ) {
                            holder.scrollTop = 0;
                            if ( isMobileView() && scroller ) {
                                scroller.scrollTo({ top: 0, behavior: 'smooth' });
                            }
                            if ( isMobileView() ) {
                                scrollToMobilePdf(holder);
                            }
                        }
                    });

                    links.forEach(function(item) {
                        item.classList.remove('is-active');
                    });
                    link.classList.add('is-active');
                }, true);
            });

            function renderInitialMobileRange() {
                if ( ! isMobileView() ) {
                    return;
                }

                const hashTarget = window.location.hash ? root.querySelector('.medvise-ohlp-nav-link[href="' + cssEscape(window.location.hash) + '"]') : null;
                const active = root.querySelector('.medvise-ohlp-nav-link.is-active[data-page]');
                const first = root.querySelector('.medvise-ohlp-nav-link[data-page]');
                const initial = hashTarget || active || first;

                if ( initial ) {
                    renderRange(root, initial).then(function(holder) {
                        if ( hashTarget && holder ) {
                            scrollToMobilePdf(holder);
                        }
                    });
                    initial.classList.add('is-active');
                }
            }

            renderInitialMobileRange();

            window.addEventListener('resize', function() {
                window.clearTimeout(root._medviseOhlpResizeTimer);
                root._medviseOhlpResizeTimer = window.setTimeout(renderInitialMobileRange, 160);
            });
        }

        viewers.forEach(initViewer);
    }

// Dom Ready
$(function() {
    Modal_Right();
    menu_Modal_Left();
    headerFixed();
    themesflatSearch(); 
    parallax();
    pagetitleVideo();
    blogLoadMore();
    goTop();
    remove_tag();
    customizable_carousel_div();
    bg_bottom(); 
    logo();    
    removePreloader();
    scrollToWooNotice();
    scrollToCF7Notice();
    subscriptionFilter();
    lightboxImages();
    blockedContentTooltip();
    copyArticleLink();
    shareArticleSubscriber();
    copyShareArticleLink();
    medviseOhlpLayout();
    medviseOhlpMobilePdf();
});
})(jQuery);
